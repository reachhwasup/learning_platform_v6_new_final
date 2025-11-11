<?php
/*
File: /api/admin/export_user_details.php
Description: Export detailed assessment results for a specific user/assessment
*/

// Authenticate and initialize
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin (since auth_check.php might not exist in this path)
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Admin Authentication ---
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access Denied: You must be an administrator to access this feature.');
}

// --- Validate input - accept either assessment_id or user_id ---
$assessment_id = null;
$user_id = null;
$export_all_attempts = false;

if (isset($_GET['user_id']) && filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    $user_id = (int)$_GET['user_id'];
    $export_all_attempts = true; // Export all attempts for this user
} elseif (isset($_GET['assessment_id']) && filter_var($_GET['assessment_id'], FILTER_VALIDATE_INT)) {
    $assessment_id = (int)$_GET['assessment_id'];
}

if (!$user_id && !$assessment_id) {
    die('Invalid assessment ID or user ID provided.');
}

// --- Check for PhpSpreadsheet ---
$vendor_autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    die('Error: The required library PhpSpreadsheet is not installed. Please run "composer install" in the project root.');
}
require_once $vendor_autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    if ($export_all_attempts) {
        // --- Fetch all attempts for this user ---
        $sql_attempts = "SELECT 
                            u.first_name, u.last_name, u.staff_id, u.position, d.name as department_name,
                            fa.id as assessment_id, fa.score, fa.status, fa.completed_at,
                            (SELECT COUNT(*) FROM final_assessments WHERE user_id = ? AND completed_at < fa.completed_at) + 1 as attempt_number
                        FROM final_assessments fa
                        JOIN users u ON fa.user_id = u.id
                        LEFT JOIN departments d ON u.department_id = d.id
                        WHERE fa.user_id = ?
                        ORDER BY fa.completed_at ASC";
        $stmt_attempts = $pdo->prepare($sql_attempts);
        $stmt_attempts->execute([$user_id, $user_id]);
        $all_attempts = $stmt_attempts->fetchAll();

        if (empty($all_attempts)) {
            die('No assessment attempts found for this user.');
        }

        // For each attempt, fetch question details
        $attempts_with_details = [];
        foreach ($all_attempts as $attempt) {
            $aid = $attempt['assessment_id'];
            
            $sql_details = "SELECT 
                                q.id as question_id,
                                q.question_text,
                                ua.selected_option_id,
                                qo_selected.option_text as selected_answer,
                                (SELECT GROUP_CONCAT(qo_correct.option_text SEPARATOR '; ') 
                                 FROM question_options qo_correct 
                                 WHERE qo_correct.question_id = q.id AND qo_correct.is_correct = 1) as correct_answers,
                                ua.is_correct
                            FROM user_answers ua
                            JOIN questions q ON ua.question_id = q.id
                            JOIN question_options qo_selected ON ua.selected_option_id = qo_selected.id
                            WHERE ua.assessment_id = ?
                            ORDER BY q.id";
            
            $stmt_details = $pdo->prepare($sql_details);
            $stmt_details->execute([$aid]);
            $question_details_raw = $stmt_details->fetchAll();

            // Group multiple answers for the same question
            $question_details = [];
            foreach ($question_details_raw as $row) {
                $question_id = $row['question_id'];
                
                if (!isset($question_details[$question_id])) {
                    $question_details[$question_id] = [
                        'question_text' => $row['question_text'],
                        'selected_answers' => [],
                        'correct_answers' => $row['correct_answers'],
                        'is_correct' => true
                    ];
                }
                
                $question_details[$question_id]['selected_answers'][] = $row['selected_answer'];
                
                if (!$row['is_correct']) {
                    $question_details[$question_id]['is_correct'] = false;
                }
            }
            
            $attempts_with_details[] = [
                'attempt_info' => $attempt,
                'questions' => $question_details
            ];
        }
        
    } else {
        // --- Single attempt export (legacy support) ---
        $sql_assessment = "SELECT u.first_name, u.last_name, u.staff_id, u.position, d.name as department_name,
                                  fa.score, fa.status, fa.completed_at 
                           FROM final_assessments fa
                           JOIN users u ON fa.user_id = u.id
                           LEFT JOIN departments d ON u.department_id = d.id
                           WHERE fa.id = ?";
        $stmt_assessment = $pdo->prepare($sql_assessment);
        $stmt_assessment->execute([$assessment_id]);
        $assessment = $stmt_assessment->fetch();

        if (!$assessment) {
            die('Assessment not found.');
        }

        $sql_details = "SELECT 
                            q.id as question_id,
                            q.question_text,
                            ua.selected_option_id,
                            qo_selected.option_text as selected_answer,
                            (SELECT GROUP_CONCAT(qo_correct.option_text SEPARATOR '; ') 
                             FROM question_options qo_correct 
                             WHERE qo_correct.question_id = q.id AND qo_correct.is_correct = 1) as correct_answers,
                            ua.is_correct
                        FROM user_answers ua
                        JOIN questions q ON ua.question_id = q.id
                        JOIN question_options qo_selected ON ua.selected_option_id = qo_selected.id
                        WHERE ua.assessment_id = ?
                        ORDER BY q.id";
        
        $stmt_details = $pdo->prepare($sql_details);
        $stmt_details->execute([$assessment_id]);
        $question_details_raw = $stmt_details->fetchAll();

        $question_details = [];
        foreach ($question_details_raw as $row) {
            $question_id = $row['question_id'];
            
            if (!isset($question_details[$question_id])) {
                $question_details[$question_id] = [
                    'question_text' => $row['question_text'],
                    'selected_answers' => [],
                    'correct_answers' => $row['correct_answers'],
                    'is_correct' => true
                ];
            }
            
            $question_details[$question_id]['selected_answers'][] = $row['selected_answer'];
            
            if (!$row['is_correct']) {
                $question_details[$question_id]['is_correct'] = false;
            }
        }
        
        // Wrap single attempt in same format as multiple attempts
        $attempts_with_details = [[
            'attempt_info' => $assessment,
            'questions' => $question_details
        ]];
    }

} catch (PDOException $e) {
    error_log("Export User Details Error: " . $e->getMessage());
    die('A database error occurred while generating the report. Details: ' . $e->getMessage());
}

// --- Create Spreadsheet ---
$spreadsheet = new Spreadsheet();

// Get user info from first attempt
$first_attempt = $attempts_with_details[0]['attempt_info'];
$user_name_full = $first_attempt['first_name'] . ' ' . $first_attempt['last_name'];

// --- Create Summary Sheet ---
$summary_sheet = $spreadsheet->getActiveSheet();
$summary_sheet->setTitle('Summary');

$summary_sheet->setCellValue('A1', 'Assessment Report - All Attempts');
$summary_sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$summary_sheet->mergeCells('A1:F1');

$row = 3;
$summary_sheet->setCellValue('A' . $row, 'Name:');
$summary_sheet->setCellValue('B' . $row, $user_name_full);
$summary_sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$summary_sheet->setCellValue('A' . $row, 'Staff ID:');
$summary_sheet->setCellValue('B' . $row, $first_attempt['staff_id']);
$summary_sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$summary_sheet->setCellValue('A' . $row, 'Position:');
$summary_sheet->setCellValue('B' . $row, $first_attempt['position'] ?? 'N/A');
$summary_sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$summary_sheet->setCellValue('A' . $row, 'Department:');
$summary_sheet->setCellValue('B' . $row, $first_attempt['department_name'] ?? 'N/A');
$summary_sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$summary_sheet->setCellValue('A' . $row, 'Total Attempts:');
$summary_sheet->setCellValue('B' . $row, count($attempts_with_details));
$summary_sheet->getStyle('A' . $row)->getFont()->setBold(true);

// --- Summary Table ---
$row += 3;
$summary_sheet->setCellValue('A' . $row, 'All Attempts Summary');
$summary_sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$summary_sheet->mergeCells('A' . $row . ':E' . $row);

$row += 2;
$headers = ['Attempt #', 'Date & Time', 'Score', 'Status', 'Result'];
$summary_sheet->fromArray($headers, NULL, 'A' . $row);

$header_style = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '075985']]
];
$summary_sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($header_style);

$row++;
foreach ($attempts_with_details as $attempt_data) {
    $attempt = $attempt_data['attempt_info'];
    $attempt_num = isset($attempt['attempt_number']) ? $attempt['attempt_number'] : 1;
    
    $summary_sheet->fromArray([
        $attempt_num,
        date('M d, Y H:i', strtotime($attempt['completed_at'])),
        (int)$attempt['score'] . '%',
        ucfirst($attempt['status']),
        $attempt['status'] === 'passed' ? 'PASSED' : 'FAILED'
    ], NULL, 'A' . $row);
    
    // Color coding
    if ($attempt['status'] === 'passed') {
        $summary_sheet->getStyle('E' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('D1FAE5');
        $summary_sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('065F46');
    } else {
        $summary_sheet->getStyle('E' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('FEE2E2');
        $summary_sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('991B1B');
    }
    
    $row++;
}

// Auto-size columns in summary
foreach (range('A', 'E') as $columnID) {
    $summary_sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// --- Create separate sheet for each attempt ---
foreach ($attempts_with_details as $index => $attempt_data) {
    $attempt = $attempt_data['attempt_info'];
    $questions = $attempt_data['questions'];
    $attempt_num = isset($attempt['attempt_number']) ? $attempt['attempt_number'] : ($index + 1);
    
    // Create new sheet
    $sheet = $spreadsheet->createSheet();
    $sheet_title = 'Attempt ' . $attempt_num;
    $sheet->setTitle($sheet_title);
    
    // Header
    $sheet->setCellValue('A1', 'Attempt #' . $attempt_num . ' - Detailed Results');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->mergeCells('A1:F1');
    
    $row = 3;
    $sheet->setCellValue('A' . $row, 'Completed:');
    $sheet->setCellValue('B' . $row, date('M d, Y H:i', strtotime($attempt['completed_at'])));
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Final Score:');
    $sheet->setCellValue('B' . $row, (int)$attempt['score'] . '%');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Status:');
    $sheet->setCellValue('B' . $row, ucfirst($attempt['status']));
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    
    // Questions section
    $row += 3;
    $sheet->setCellValue('A' . $row, 'Question Details');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->mergeCells('A' . $row . ':F' . $row);
    
    $row += 2;
    $question_headers = ['Q#', 'Question', 'Your Answer', 'Correct Answer(s)', 'Result'];
    $sheet->fromArray($question_headers, NULL, 'A' . $row);
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($header_style);
    
    $row++;
    $question_number = 1;
    foreach ($questions as $question_id => $detail) {
        $result = $detail['is_correct'] ? 'Correct' : 'Incorrect';
        $selected_answers_text = implode('; ', $detail['selected_answers']);
        
        $sheet->fromArray([
            $question_number,
            $detail['question_text'],
            $selected_answers_text,
            $detail['correct_answers'],
            $result
        ], NULL, 'A' . $row);
        
        // Color coding for result
        if ($detail['is_correct']) {
            $sheet->getStyle('E' . $row)->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('D1FAE5');
            $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('065F46');
        } else {
            $sheet->getStyle('E' . $row)->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('FEE2E2');
            $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('991B1B');
        }
        
        $row++;
        $question_number++;
    }
    
    // Auto-size columns
    foreach (range('A', 'E') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    $sheet->getColumnDimension('B')->setWidth(50);
    $sheet->getColumnDimension('C')->setWidth(30);
    $sheet->getColumnDimension('D')->setWidth(30);
}

// Set active sheet to summary
$spreadsheet->setActiveSheetIndex(0);

// --- Set Headers for Download ---
$user_name = str_replace(' ', '_', $user_name_full);
$filename = "assessment_all_attempts_{$user_name}_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// --- Output the file ---
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
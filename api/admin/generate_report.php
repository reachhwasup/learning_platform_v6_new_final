<?php
/*
File: /api/admin/generate_report.php (CORRECTED)
Description: This version is updated to correctly handle the 'all' status for exporting all results.
*/

// Authenticate and initialize
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// --- Admin Authentication ---
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access Denied: You must be an administrator to access this feature.');
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

// --- Validate Input (FIXED) ---
$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['passed', 'failed', 'all'])) {
    die('Invalid report type specified.');
}

// --- Fetch Data from Database (FIXED) ---
try {
    // This query is now matched to your database schema
    $sql = "
        SELECT 
            u.first_name, 
            u.last_name,
            u.staff_id,
            d.name as department_name,
            fa.score,
            fa.completed_at,
            q.question_text,
            qo.option_text AS selected_answer,
            (SELECT GROUP_CONCAT(correct_qo.option_text SEPARATOR '; ') FROM question_options correct_qo WHERE correct_qo.question_id = q.id AND correct_qo.is_correct = 1) AS correct_answer,
            ua.is_correct AS was_correct
        FROM final_assessments fa
        JOIN users u ON fa.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        JOIN user_answers ua ON fa.id = ua.assessment_id
        JOIN questions q ON ua.question_id = q.id
        JOIN question_options qo ON ua.selected_option_id = qo.id
    ";
    
    // Conditionally add WHERE clause if not exporting all results
    $params = [];
    if ($status !== 'all') {
        $sql .= " WHERE fa.status = :status";
        $params['status'] = $status;
    }
    
    $sql .= " ORDER BY u.last_name, u.first_name, fa.completed_at, q.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Report Generation Error: " . $e->getMessage());
    die('A database error occurred while generating the report. Details: ' . $e->getMessage());
}

// --- Create Spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(ucfirst($status) . ' Details');

// --- Set Headers ---
$headers = [
    'First Name', 
    'Last Name', 
    'Staff ID', 
    'Department', 
    'Final Score (Points)', 
    'Completion Date',
    'Question',
    'User\'s Answer',
    'Correct Answer(s)',
    'Result'
];
$sheet->fromArray($headers, NULL, 'A1');

// Style the header row
$header_style = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '075985']]
];
$sheet->getStyle('A1:J1')->applyFromArray($header_style);


// --- Populate Data ---
$row_index = 2;
foreach ($results as $row) {
    $sheet->fromArray([
        $row['first_name'],
        $row['last_name'],
        $row['staff_id'],
        $row['department_name'] ?? 'N/A',
        (int)$row['score'],
        date('Y-m-d H:i:s', strtotime($row['completed_at'])),
        $row['question_text'],
        $row['selected_answer'],
        $row['correct_answer'],
        $row['was_correct'] ? 'Correct' : 'Incorrect'
    ], NULL, 'A' . $row_index);
    $row_index++;
}

// --- Auto-size columns ---
foreach (range('A', 'J') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}


// --- Set Headers for Download ---
$filename = "detailed_assessment_report_{$status}_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// --- Output the file ---
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>

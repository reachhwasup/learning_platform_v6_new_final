<?php
/*
File: /api/admin/export_basic.php
Description: Export basic assessment results matching the table columns only
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

// --- Get Filter Values (same as reports page) ---
$filter_dept = isset($_GET['department']) && $_GET['department'] !== '' ? (int)$_GET['department'] : null;
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['passed', 'failed', 'all']) ? $_GET['status'] : 'all';

try {
    // --- Build SQL Query with Filters (same as reports page) ---
    $sql = "SELECT 
                u.first_name, u.last_name, u.staff_id, u.position, d.name as department_name,
                fa.id as assessment_id, fa.score, fa.status, fa.completed_at,
                (SELECT COUNT(*) FROM final_assessments fa_count WHERE fa_count.user_id = u.id) as attempt_count
            FROM users u
            JOIN final_assessments fa ON u.id = fa.user_id
            -- This join ensures we only get the row for the user's LATEST assessment
            JOIN (
                SELECT user_id, MAX(completed_at) AS max_completed_at 
                FROM final_assessments 
                GROUP BY user_id
            ) latest_fa ON fa.user_id = latest_fa.user_id AND fa.completed_at = latest_fa.max_completed_at
            LEFT JOIN departments d ON u.department_id = d.id";
    
    $where_clauses = [];
    $params = [];

    if ($filter_dept) {
        $where_clauses[] = "u.department_id = :dept_id";
        $params[':dept_id'] = $filter_dept;
    }
    if ($filter_status && $filter_status !== 'all') {
        $where_clauses[] = "fa.status = :status";
        $params[':status'] = $filter_status;
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY fa.completed_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Basic Export Error: " . $e->getMessage());
    die('A database error occurred while generating the report. Details: ' . $e->getMessage());
}

// --- Create Spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set title based on filter
$sheet_title = 'All Assessments';
if ($filter_status === 'passed') {
    $sheet_title = 'Passed Assessments';
} elseif ($filter_status === 'failed') {
    $sheet_title = 'Failed Assessments';
}
$sheet->setTitle($sheet_title);

// --- Set Headers (matching table columns) ---
$headers = [
    'No.',
    'Name', 
    'Staff ID', 
    'Position', 
    'Department', 
    'Attempt',
    'Score (%)', 
    'Status', 
    'Date Completed'
];
$sheet->fromArray($headers, NULL, 'A1');

// Style the header row
$header_style = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '075985']]
];
$sheet->getStyle('A1:I1')->applyFromArray($header_style);

// --- Populate Data ---
$row_index = 2;
$counter = 1;
foreach ($results as $row) {
    $sheet->fromArray([
        $counter,
        $row['first_name'] . ' ' . $row['last_name'],
        $row['staff_id'],
        $row['position'] ?? 'N/A',
        $row['department_name'] ?? 'N/A',
        (int)$row['attempt_count'],
        (int)$row['score'],
        ucfirst($row['status']),
        date('M d, Y H:i', strtotime($row['completed_at']))
    ], NULL, 'A' . $row_index);
    $row_index++;
    $counter++;
}

// --- Auto-size columns ---
foreach (range('A', 'I') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// --- Set Headers for Download ---
$status_suffix = $filter_status === 'all' ? 'all' : $filter_status;
$filename = "assessment_report_{$status_suffix}_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// --- Output the file ---
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
<?php
/**
 * Dashboard Data API Endpoint (Corrected)
 *
 * This script provides accurate data for the charts on the admin dashboard.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// --- Admin Authentication ---
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$response = [
    'signupData' => [],
    'assessmentData' => [],
    'overallProgressData' => [],
    'monthlyActivityData' => [],
];

try {
    // 1. Get User count per department
    $sql_signups = "SELECT d.name, COUNT(u.id) as user_count
                    FROM departments d
                    LEFT JOIN users u ON d.id = u.department_id AND u.role = 'user'
                    GROUP BY d.id
                    ORDER BY d.name";
    $stmt_signups = $pdo->query($sql_signups);
    $response['signupData'] = $stmt_signups->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get Passed/Failed data per department based on LATEST attempt
    $sql_assessments = "SELECT 
                            d.name,
                            SUM(CASE WHEN latest_fa.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
                            SUM(CASE WHEN latest_fa.status = 'failed' THEN 1 ELSE 0 END) as failed_count
                        FROM departments d
                        LEFT JOIN users u ON d.id = u.department_id AND u.role = 'user'
                        LEFT JOIN (
                            SELECT 
                                fa.user_id, 
                                fa.status
                            FROM final_assessments fa
                            INNER JOIN (
                                SELECT user_id, MAX(completed_at) AS max_completed_at
                                FROM final_assessments
                                GROUP BY user_id
                            ) latest ON fa.user_id = latest.user_id AND fa.completed_at = latest.max_completed_at
                        ) latest_fa ON u.id = latest_fa.user_id
                        GROUP BY d.id
                        ORDER BY d.name";
    $stmt_assessments = $pdo->query($sql_assessments);
    $response['assessmentData'] = $stmt_assessments->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get Overall User Progress data
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $completed_users = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM final_assessments WHERE status = 'passed'")->fetchColumn();
    $in_progress_users = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_progress WHERE user_id NOT IN (SELECT user_id FROM final_assessments WHERE status = 'passed')")->fetchColumn();
    $not_started_users = $total_users - $completed_users - $in_progress_users;

    $response['overallProgressData'] = [
        'labels' => ['Completed', 'In Progress', 'Not Started'],
        'data' => [$completed_users, $in_progress_users, $not_started_users > 0 ? $not_started_users : 0]
    ];

    // 4. Get Monthly Completion Activity (last 6 months)
    $sql_monthly = "SELECT 
                        DATE_FORMAT(completed_at, '%Y-%m') as month,
                        COUNT(*) as completions
                    FROM final_assessments
                    WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY month
                    ORDER BY month ASC";
    $stmt_monthly = $pdo->query($sql_monthly);
    $monthly_raw_data = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);

    $monthly_labels = [];
    $monthly_completions = [];
    $date = new DateTime();
    $date->modify('-5 months');
    for ($i = 0; $i < 6; $i++) {
        $month_key = $date->format('Y-m');
        $monthly_labels[] = $date->format('M Y');
        $completions_for_month = 0;
        foreach($monthly_raw_data as $row) {
            if ($row['month'] === $month_key) {
                $completions_for_month = (int)$row['completions'];
                break;
            }
        }
        $monthly_completions[] = $completions_for_month;
        $date->modify('+1 month');
    }

    $response['monthlyActivityData'] = [
        'labels' => $monthly_labels,
        'data' => $monthly_completions
    ];


} catch (PDOException $e) {
    error_log("Dashboard Data API Error: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode($response);
    exit;
}

echo json_encode($response);
?>

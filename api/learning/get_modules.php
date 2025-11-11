<?php
/**
 * Get Modules API Endpoint
 *
 * Fetches a list of all modules and their status for the currently logged-in user.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response_data = [];

try {
    // 1. Fetch all modules in the correct order
    $sql_modules = "SELECT id, title, description, module_order FROM modules ORDER BY module_order ASC";
    $stmt_modules = $pdo->query($sql_modules);
    $all_modules = $stmt_modules->fetchAll();

    // 2. Fetch all module IDs that the current user has completed
    $sql_progress = "SELECT module_id FROM user_progress WHERE user_id = :user_id";
    $stmt_progress = $pdo->prepare($sql_progress);
    $stmt_progress->execute(['user_id' => $user_id]);
    $completed_modules = $stmt_progress->fetchAll(PDO::FETCH_COLUMN);

    // 3. Determine the status of each module
    foreach ($all_modules as $index => $module) {
        $status = 'locked';
        
        $is_completed = in_array($module['id'], $completed_modules);
        
        // Determine if the module is unlocked
        $is_unlocked = false;
        if ($index === 0) {
            $is_unlocked = true; // First module is always unlocked
        } else {
            $previous_module_id = $all_modules[$index - 1]['id'];
            if (in_array($previous_module_id, $completed_modules)) {
                $is_unlocked = true; // Unlocked because the previous one is complete
            }
        }
        
        if ($is_completed) {
            $status = 'completed';
        } elseif ($is_unlocked) {
            $status = 'unlocked';
        }
        
        $module['status'] = $status;
        $response_data[] = $module;
    }

    echo json_encode(['success' => true, 'data' => $response_data]);

} catch (PDOException $e) {
    error_log("Get Modules API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

?>

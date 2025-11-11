<?php
/**
 * Track Progress API Endpoint
 *
 * Marks a learning module as completed for the current user.
 */

header('Content-Type: application/json');

require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Authenticate user
if (!is_logged_in()) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

// 2. Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// 3. Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['module_id']) || !filter_var($input['module_id'], FILTER_VALIDATE_INT)) {
    $response['message'] = 'Invalid module ID provided.';
    echo json_encode($response);
    exit;
}

$module_id = (int)$input['module_id'];
$user_id = $_SESSION['user_id'];

try {
    // 4. Check if progress is already recorded to prevent duplicates
    $sql_check = "SELECT id FROM user_progress WHERE user_id = :user_id AND module_id = :module_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['user_id' => $user_id, 'module_id' => $module_id]);

    if ($stmt_check->fetch()) {
        // Progress already exists, which is fine. Send a success response.
        $response['success'] = true;
        $response['message'] = 'Progress already recorded.';
    } else {
        // 5. Insert new progress record
        $sql_insert = "INSERT INTO user_progress (user_id, module_id) VALUES (:user_id, :module_id)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute(['user_id' => $user_id, 'module_id' => $module_id]);

        if ($stmt_insert->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Progress successfully recorded.';
        } else {
            $response['message'] = 'Failed to record progress.';
        }
    }

} catch (PDOException $e) {
    error_log("Track Progress Error: " . $e->getMessage());
    $response['message'] = 'A server error occurred while saving progress.';
}

echo json_encode($response);
?>

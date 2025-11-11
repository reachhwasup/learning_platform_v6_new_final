<?php
/**
 * Get Video API Endpoint
 *
 * Fetches video details for a specific module ID.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if (!isset($_GET['module_id']) || !filter_var($_GET['module_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'Invalid module ID.']);
    exit;
}

$module_id = (int)$_GET['module_id'];

try {
    $sql = "SELECT id, title, description, video_path FROM videos WHERE module_id = :module_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['module_id' => $module_id]);
    $video = $stmt->fetch();

    if ($video) {
        echo json_encode(['success' => true, 'data' => $video]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No video found for this module.']);
    }

} catch (PDOException $e) {
    error_log("Get Video API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

?>

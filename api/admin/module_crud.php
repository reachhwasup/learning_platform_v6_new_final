<?php
/**
 * Module CRUD API Endpoint (CORRECTED for Separated Workflow)
 *
 * Handles Create, Read, Update, Delete for module metadata and PDFs ONLY.
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

$response = ['success' => false, 'message' => 'Invalid request.'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add_module':
            if (empty($_POST['title']) || !isset($_POST['module_order'])) {
                throw new Exception('Title and Module Order are required.');
            }

            // Check if module order already exists
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE module_order = ?");
            $check_stmt->execute([$_POST['module_order']]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('A module with order ' . $_POST['module_order'] . ' already exists. Please use a different order number.');
            }

            $sql = "INSERT INTO modules (title, description, module_order) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['title'], $_POST['description'], $_POST['module_order']]);
            $module_id = $pdo->lastInsertId();

            // Handle video upload (now required)
            if (empty($_FILES['video_file']['name'])) {
                throw new Exception('Video file is required when creating a module.');
            }

            $upload_dir = '../../uploads/videos/';
            $thumbnail_dir = '../../uploads/thumbnails/';
            
            // Create directories if they don't exist
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (!is_dir($thumbnail_dir)) mkdir($thumbnail_dir, 0755, true);

            // Validate video file MIME type
            $video_file = $_FILES['video_file'];
            $allowed_video_types = ['video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-matroska'];
            
            if (!validate_file_type($video_file['tmp_name'], $allowed_video_types)) {
                throw new Exception('Invalid video file type. Only MP4, AVI, MOV, and MKV are allowed.');
            }
            
            // Validate video file size (max 500MB)
            $max_video_size = 500 * 1024 * 1024; // 500MB
            if ($video_file['size'] > $max_video_size) {
                throw new Exception('Video file size too large. Maximum size is 500MB.');
            }

            // Upload video file with secure filename
            $video_ext = pathinfo($video_file['name'], PATHINFO_EXTENSION);
            $video_filename = secure_filename('video_' . $module_id . '_' . time() . '.' . $video_ext);
            $video_path = $upload_dir . $video_filename;

            if (!move_uploaded_file($video_file['tmp_name'], $video_path)) {
                throw new Exception('Failed to upload video file.');
            }

            // Upload thumbnail if provided
            $thumbnail_path = null;
            $thumb_filename = null;
            if (!empty($_FILES['thumbnail_file']['name'])) {
                $thumbnail_file = $_FILES['thumbnail_file'];
                
                // Validate thumbnail MIME type
                $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validate_file_type($thumbnail_file['tmp_name'], $allowed_image_types)) {
                    throw new Exception('Invalid thumbnail file type. Only JPEG, PNG, GIF, and WEBP are allowed.');
                }
                
                // Validate thumbnail file size (max 5MB)
                $max_thumb_size = 5 * 1024 * 1024; // 5MB
                if ($thumbnail_file['size'] > $max_thumb_size) {
                    throw new Exception('Thumbnail file size too large. Maximum size is 5MB.');
                }
                
                $thumb_ext = pathinfo($thumbnail_file['name'], PATHINFO_EXTENSION);
                $thumb_filename = secure_filename('thumb_' . $module_id . '_' . time() . '.' . $thumb_ext);
                $thumbnail_path = $thumbnail_dir . $thumb_filename;
                
                if (!move_uploaded_file($thumbnail_file['tmp_name'], $thumbnail_path)) {
                    $thumb_filename = null; // Don't fail if thumbnail upload fails
                }
            }

            // Insert video record using module title as video title
            $video_sql = "INSERT INTO videos (module_id, title, description, video_path, thumbnail_path, duration, video_order, upload_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $video_stmt = $pdo->prepare($video_sql);
            $video_stmt->execute([
                $module_id,
                $_POST['title'], // Use module title as video title
                $_POST['description'], // Use module description as video description
                $video_filename, // Store just the filename
                $thumbnail_path ? $thumb_filename : null, // Store just the filename
                $_POST['video_duration'] ?? null,
                1, // Always set to 1 since one module = one video
                $_SESSION['user_id'] // Admin user ID who is uploading
            ]);

            $response = ['success' => true, 'message' => 'Module added successfully with video.', 'module_id' => $module_id];
            break;

        case 'get_module':
            $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $module = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($module) {
                // Also fetch video info if exists
                $video_stmt = $pdo->prepare("SELECT * FROM videos WHERE module_id = ?");
                $video_stmt->execute([$_GET['id']]);
                $video = $video_stmt->fetch(PDO::FETCH_ASSOC);
                $module['video'] = $video ?: null;
                
                $response = ['success' => true, 'data' => $module];
            } else {
                throw new Exception('Module not found.');
            }
            break;

        case 'edit_module':
            if (empty($_POST['module_id']) || empty($_POST['title']) || !isset($_POST['module_order'])) {
                throw new Exception('Module ID, Title, and Order are required.');
            }
            $module_id = $_POST['module_id'];

            // Check if module order already exists (excluding current module)
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE module_order = ? AND id != ?");
            $check_stmt->execute([$_POST['module_order'], $module_id]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('A module with order ' . $_POST['module_order'] . ' already exists. Please use a different order number.');
            }

            $sql = "UPDATE modules SET title = ?, description = ?, module_order = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['title'], $_POST['description'], $_POST['module_order'], $module_id]);
            
            // Check if video exists for this module
            $check_video = $pdo->prepare("SELECT id, video_path, thumbnail_path FROM videos WHERE module_id = ?");
            $check_video->execute([$module_id]);
            $existing_video = $check_video->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_video) {
                // Update video title, description, and duration
                $video_update_sql = "UPDATE videos SET title = ?, description = ?, duration = ? WHERE module_id = ?";
                $video_update_stmt = $pdo->prepare($video_update_sql);
                $video_update_stmt->execute([
                    $_POST['title'], 
                    $_POST['description'], 
                    $_POST['video_duration'] ?? null,
                    $module_id
                ]);
                
                // Handle video file replacement if uploaded
                if (!empty($_FILES['video_file']['name'])) {
                    $upload_dir = '../../uploads/videos/';
                    $upload_dir_realpath = realpath($upload_dir);
                    
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    // Validate uploaded file MIME type
                    $allowed_video_types = ['video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-matroska'];
                    $video_file = $_FILES['video_file'];
                    
                    if (!validate_file_type($video_file['tmp_name'], $allowed_video_types)) {
                        throw new Exception('Invalid video file type. Only MP4, AVI, MOV, and MKV are allowed.');
                    }
                    
                    // Delete old video file before uploading new one
                    if ($existing_video['video_path']) {
                        $old_video_path = $upload_dir . basename($existing_video['video_path']); // Prevent traversal
                        safe_unlink($old_video_path, $upload_dir_realpath);
                    }
                    
                    $video_ext = pathinfo($video_file['name'], PATHINFO_EXTENSION);
                    $video_filename = secure_filename('video_' . $module_id . '_' . time() . '.' . $video_ext);
                    $video_path = $upload_dir . $video_filename;
                    
                    if (move_uploaded_file($video_file['tmp_name'], $video_path)) {
                        // Update video path in database
                        $update_path_sql = "UPDATE videos SET video_path = ? WHERE module_id = ?";
                        $update_path_stmt = $pdo->prepare($update_path_sql);
                        $update_path_stmt->execute([$video_filename, $module_id]);
                    }
                }
                
                // Handle thumbnail replacement if uploaded
                if (!empty($_FILES['thumbnail_file']['name'])) {
                    $thumbnail_dir = '../../uploads/thumbnails/';
                    $thumbnail_dir_realpath = realpath($thumbnail_dir);
                    
                    if (!is_dir($thumbnail_dir)) mkdir($thumbnail_dir, 0755, true);
                    
                    // Validate uploaded file MIME type
                    $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $thumbnail_file = $_FILES['thumbnail_file'];
                    
                    if (!validate_file_type($thumbnail_file['tmp_name'], $allowed_image_types)) {
                        throw new Exception('Invalid thumbnail file type. Only JPEG, PNG, GIF, and WEBP are allowed.');
                    }
                    
                    // Delete old thumbnail file before uploading new one
                    if ($existing_video['thumbnail_path']) {
                        $old_thumb_path = $thumbnail_dir . basename($existing_video['thumbnail_path']); // Prevent traversal
                        safe_unlink($old_thumb_path, $thumbnail_dir_realpath);
                    }
                    
                    $thumbnail_file = $_FILES['thumbnail_file'];
                    
                    // Validate file type using MIME
                    $allowed_thumb_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!validate_file_type($thumbnail_file['tmp_name'], $allowed_thumb_types)) {
                        $response = ['success' => false, 'message' => 'Invalid thumbnail file type. Only JPEG, PNG, GIF, and WEBP are allowed.'];
                        break;
                    }
                    
                    // Check file size (5MB limit for thumbnails)
                    if ($thumbnail_file['size'] > 5 * 1024 * 1024) {
                        $response = ['success' => false, 'message' => 'Thumbnail file size exceeds 5MB limit.'];
                        break;
                    }
                    
                    $thumb_ext = pathinfo($thumbnail_file['name'], PATHINFO_EXTENSION);
                    $thumb_filename = secure_filename('thumb_' . $module_id . '_' . time() . '.' . $thumb_ext);
                    $thumbnail_path = $thumbnail_dir . $thumb_filename;
                    
                    if (move_uploaded_file($thumbnail_file['tmp_name'], $thumbnail_path)) {
                        // Update thumbnail path in database
                        $update_thumb_sql = "UPDATE videos SET thumbnail_path = ? WHERE module_id = ?";
                        $update_thumb_stmt = $pdo->prepare($update_thumb_sql);
                        $update_thumb_stmt->execute([$thumb_filename, $module_id]);
                    }
                }
            }
            // If no video exists, that's okay - old modules may not have videos
            
            $response = ['success' => true, 'message' => 'Module updated successfully.'];
            break;
        
        case 'delete_module':
            $module_id = $_POST['module_id'] ?? 0;
            if (!$module_id) {
                throw new Exception('Module ID is required.');
            }
            
            // Fetch and delete associated files (including thumbnail)
            $stmt = $pdo->prepare("
                SELECT v.video_path, v.thumbnail_path 
                FROM modules m
                LEFT JOIN videos v ON m.id = v.module_id
                WHERE m.id = ?
            ");
            $stmt->execute([$module_id]);
            
            if ($paths = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $video_dir = realpath('../../uploads/videos/');
                $thumbnail_dir = realpath('../../uploads/thumbnails/');
                
                // Delete video file with path validation
                if ($paths['video_path']) {
                    $video_path = '../../uploads/videos/' . basename($paths['video_path']);
                    safe_unlink($video_path, $video_dir);
                }
                
                // Delete thumbnail with path validation
                if ($paths['thumbnail_path']) {
                    $thumbnail_path = '../../uploads/thumbnails/' . basename($paths['thumbnail_path']);
                    safe_unlink($thumbnail_path, $thumbnail_dir);
                }
            }
            
            // Delete database records (videos will be cascade deleted if foreign key is set)
            $stmt_del = $pdo->prepare("DELETE FROM modules WHERE id = ?");
            $stmt_del->execute([$module_id]);
            
            $response = ['success' => true, 'message' => 'Module and all associated files deleted successfully.'];
            break;
    }
} catch (PDOException | Exception $e) {
    error_log($e->getMessage());
    $response['message'] = 'A server error occurred. Please try again or contact support.';
}

echo json_encode($response);
?>

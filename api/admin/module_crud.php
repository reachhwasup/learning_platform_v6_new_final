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

            // Upload video file
            $video_file = $_FILES['video_file'];
            $video_ext = pathinfo($video_file['name'], PATHINFO_EXTENSION);
            $video_filename = 'video_' . $module_id . '_' . time() . '.' . $video_ext;
            $video_path = $upload_dir . $video_filename;

            if (!move_uploaded_file($video_file['tmp_name'], $video_path)) {
                throw new Exception('Failed to upload video file.');
            }

            // Upload thumbnail if provided
            $thumbnail_path = null;
            $thumb_filename = null;
            if (!empty($_FILES['thumbnail_file']['name'])) {
                $thumbnail_file = $_FILES['thumbnail_file'];
                $thumb_ext = pathinfo($thumbnail_file['name'], PATHINFO_EXTENSION);
                $thumb_filename = 'thumb_' . $module_id . '_' . time() . '.' . $thumb_ext;
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
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    // Delete old video file before uploading new one
                    if ($existing_video['video_path']) {
                        $old_video_path = $upload_dir . $existing_video['video_path'];
                        if (file_exists($old_video_path)) {
                            if (unlink($old_video_path)) {
                                error_log("Deleted old video: " . $existing_video['video_path']);
                            } else {
                                error_log("Failed to delete old video: " . $existing_video['video_path']);
                            }
                        }
                    }
                    
                    $video_file = $_FILES['video_file'];
                    $video_ext = pathinfo($video_file['name'], PATHINFO_EXTENSION);
                    $video_filename = 'video_' . $module_id . '_' . time() . '.' . $video_ext;
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
                    if (!is_dir($thumbnail_dir)) mkdir($thumbnail_dir, 0755, true);
                    
                    // Delete old thumbnail file before uploading new one
                    if ($existing_video['thumbnail_path']) {
                        $old_thumb_path = $thumbnail_dir . $existing_video['thumbnail_path'];
                        if (file_exists($old_thumb_path)) {
                            if (unlink($old_thumb_path)) {
                                error_log("Deleted old thumbnail: " . $existing_video['thumbnail_path']);
                            } else {
                                error_log("Failed to delete old thumbnail: " . $existing_video['thumbnail_path']);
                            }
                        }
                    }
                    
                    $thumbnail_file = $_FILES['thumbnail_file'];
                    $thumb_ext = pathinfo($thumbnail_file['name'], PATHINFO_EXTENSION);
                    $thumb_filename = 'thumb_' . $module_id . '_' . time() . '.' . $thumb_ext;
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
                // Delete video file
                if ($paths['video_path'] && file_exists('../../uploads/videos/' . $paths['video_path'])) {
                    if (unlink('../../uploads/videos/' . $paths['video_path'])) {
                        error_log("Deleted video: " . $paths['video_path']);
                    }
                }
                
                // Delete thumbnail
                if ($paths['thumbnail_path'] && file_exists('../../uploads/thumbnails/' . $paths['thumbnail_path'])) {
                    if (unlink('../../uploads/thumbnails/' . $paths['thumbnail_path'])) {
                        error_log("Deleted thumbnail: " . $paths['thumbnail_path']);
                    }
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
    $response['message'] = 'A server error occurred: ' . $e->getMessage();
}

echo json_encode($response);
?>

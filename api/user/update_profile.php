<?php
/**
 * Update Profile API Endpoint (FIXED)
 *
 * This version now correctly updates the session after a successful profile picture change.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$response = ['success' => false, 'message' => 'Invalid request.'];
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_info':
            $sql = "UPDATE users SET first_name = ?, last_name = ?, gender = ?, dob = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['dob'], $user_id]);
            
            // Also update the session with the new name
            $_SESSION['user_first_name'] = $_POST['first_name'];

            $response = ['success' => true, 'message' => 'Profile information updated successfully!'];
            break;

        case 'change_password':
            if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
                throw new Exception('All password fields are required.');
            }
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception('New passwords do not match.');
            }
            if (strlen($_POST['new_password']) < 8) {
                throw new Exception('New password must be at least 8 characters long.');
            }

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_hash = $stmt->fetchColumn();

            if (password_verify($_POST['current_password'], $current_hash)) {
                $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->execute([$new_hash, $user_id]);
                $response = ['success' => true, 'message' => 'Password changed successfully.'];
            } else {
                throw new Exception('Incorrect current password.');
            }
            break;

        case 'change_picture':
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception('Failed to create upload directory.');
                    }
                }

                $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                
                $stmt_old = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt_old->execute([$user_id]);
                $old_pic = $stmt_old->fetchColumn();
                if ($old_pic && $old_pic !== 'default_avatar.png' && file_exists($upload_dir . $old_pic)) {
                    unlink($upload_dir . $old_pic);
                }

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $file_name)) {
                    $stmt_update = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt_update->execute([$file_name, $user_id]);

                    // --- FIX: Update the session variable immediately ---
                    $_SESSION['user_profile_picture'] = $file_name;

                    $response = [
                        'success' => true, 
                        'message' => 'Profile picture updated.',
                        'new_path' => 'uploads/profile_pictures/' . $file_name
                    ];
                } else {
                    throw new Exception('Failed to move uploaded file.');
                }
            } else {
                throw new Exception('File upload error.');
            }
            break;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>

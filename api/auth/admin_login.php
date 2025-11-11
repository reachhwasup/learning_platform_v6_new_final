<?php
/**
 * Admin Login API Endpoint
 *
 * Handles admin authentication, checking for the 'admin' role specifically.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.'
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['username']) || empty($_POST['username']) || !isset($_POST['password']) || empty($_POST['password'])) {
    $response['message'] = 'Username and password are required.';
    echo json_encode($response);
    exit;
}

$username = $_POST['username'];
$password = $_POST['password'];

try {
    $sql = "SELECT id, password, role, first_name, last_name, status, failed_login_attempts, password_reset_required, profile_picture 
            FROM users 
            WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response['message'] = 'Invalid username or password.';
        echo json_encode($response);
        exit;
    }

    // --- ROLE CHECK: Ensure the user is an admin ---
    if ($user['role'] !== 'admin') {
        $response['message'] = 'Access denied. You do not have administrator privileges.';
        echo json_encode($response);
        exit;
    }

    // --- STATUS CHECK ---
    if ($user['status'] === 'locked') {
        $response['message'] = 'Your account has been locked. Please contact another administrator.';
        echo json_encode($response);
        exit;
    }
    if ($user['status'] === 'inactive') {
        $response['message'] = 'Your account is inactive.';
        echo json_encode($response);
        exit;
    }

    // --- PASSWORD VERIFICATION ---
    if (password_verify($password, $user['password'])) {
        // SUCCESSFUL LOGIN
        $updateSql = "UPDATE users SET failed_login_attempts = 0 WHERE id = :id";
        $pdo->prepare($updateSql)->execute(['id' => $user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_first_name'] = $user['first_name'];
        $_SESSION['user_last_name'] = $user['last_name'];
        $_SESSION['logged_in_at'] = time();
        $_SESSION['password_reset_required'] = (bool)$user['password_reset_required'];
        $_SESSION['user_profile_picture'] = $user['profile_picture'];

        $response['success'] = true;
        $response['message'] = 'Login successful! Redirecting...';
        
        // Determine redirect URL
        if ($user['password_reset_required']) {
            $response['redirect_url'] = '../force_change_password.php'; // Path from API folder
        } else {
            $response['redirect_url'] = '../admin/index.php'; // Path from API folder
        }

    } else {
        // FAILED LOGIN
        $new_attempts = $user['failed_login_attempts'] + 1;
        $sql_fail = "UPDATE users SET failed_login_attempts = :attempts WHERE id = :id";
        if ($new_attempts >= 5) {
            $sql_fail = "UPDATE users SET failed_login_attempts = :attempts, status = 'locked' WHERE id = :id";
        }
        $pdo->prepare($sql_fail)->execute(['attempts' => $new_attempts, 'id' => $user['id']]);
        
        $response['message'] = 'Invalid username or password.';
    }

} catch (PDOException $e) {
    error_log("Admin Login Error: " . $e->getMessage());
    $response['message'] = 'A server error occurred.';
}

echo json_encode($response);
?>

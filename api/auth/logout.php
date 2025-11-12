<?php
/**
 * Logout Script
 *
 * This script destroys the user's session, effectively logging them out,
 * and then redirects them to the main login page.
 */

// 1. Start the session to access session data.
// It's important to start the session before you can destroy it.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Check if user is admin before destroying session
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// 3. Unset all of the session variables.
$_SESSION = array();

// 3. If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finally, destroy the session.
session_destroy();

// 5. Redirect to the appropriate login page based on user role.
// Admins go to admin login, regular users go to user login.
if ($isAdmin) {
    header("Location: ../../admin/login.php");
} else {
    header("Location: ../../login.php");
}
exit(); // Ensure no further code is executed after redirection.
?>

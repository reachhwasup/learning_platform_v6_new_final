<?php
/**
 * Authentication Check Script (UPDATED)
 *
 * This script verifies that a user is logged in and enforces a password change if required.
 */

// Include the core functions which also starts the session.
require_once 'functions.php';

// Check if the user is logged in.
if (!is_logged_in()) {
    // If not, destroy any potential partial session data.
    session_destroy();
    // Redirect to the login page and stop script execution.
    redirect('login.php');
}

// --- NEW: Enforce Password Change ---
$current_page = basename($_SERVER['PHP_SELF']);

// Check if the password reset flag is set in the session.
if (isset($_SESSION['password_reset_required']) && $_SESSION['password_reset_required'] === true) {
    // If the flag is set, the user is ONLY allowed to access the password change page or the logout script.
    if ($current_page !== 'force_change_password.php' && $current_page !== 'logout.php') {
        // If they try to go anywhere else, force them to the change password page.
        redirect('force_change_password.php');
    }
}

// Optional: Also check if the user is a normal user, not an admin,
// if this check is for user-specific pages.
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    // Admins should be on their own dashboard, not the user one.
    // Make sure the admin is not also trapped by the password reset check on their own pages.
    if ($current_page !== 'force_change_password.php') {
         redirect('admin/index.php');
    }
}
?>

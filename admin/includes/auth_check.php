<?php
/**
 * Admin Authentication Check Script
 *
 * Verifies that the current user is a logged-in administrator.
 * If not, they are redirected to the admin login page.
 */

// This file assumes it's being included from within the /admin/ directory.
// So we need to go up one level to find the main 'includes' folder.
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in at all
if (!is_logged_in()) {
    redirect('../admin/login.php');
}

// Check if the logged-in user has the 'admin' role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // If not an admin, destroy session and redirect to admin login
    session_destroy();
    redirect('../admin/login.php');
}
?>

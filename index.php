<?php
/**
 * Main Entry Point
 *
 * This script checks if a user is logged in.
 * - If logged in, it redirects them to their dashboard.
 * - If not logged in, it redirects them to the login page.
 */

// Include the core functions file which also starts the session.
require_once 'includes/functions.php';

// Check user's login status and role
if (is_logged_in()) {
    // If a user is logged in, redirect to the appropriate dashboard
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        redirect('admin/index.php');
    } else {
        redirect('dashboard.php');
    }
} else {
    // If no one is logged in, show the main login page
    redirect('login.php');
}
?>

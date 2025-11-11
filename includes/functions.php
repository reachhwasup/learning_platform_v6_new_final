<?php
/**
 * Core Functions File
 *
 * This file contains essential functions that are used throughout the application.
 * Currently, it's responsible for starting the user session.
 */

// Start the session if it's not already started.
// This must be called at the beginning of any script that needs access to session variables.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * A simple helper function to securely output data to the screen.
 * This helps prevent XSS (Cross-Site Scripting) attacks.
 *
 * @param string|null $data The data to be escaped.
 * @return string The escaped data.
 */
function escape($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Checks if a user is logged in.
 *
 * @return bool True if the user is logged in, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirects to a specified URL.
 *
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

?>

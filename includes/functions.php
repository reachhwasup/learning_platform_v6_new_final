<?php
/**
 * Core Functions File
 *
 * This file contains essential functions that are used throughout the application.
 * Currently, it's responsible for starting the user session.
 */

// Secure session configuration - MUST be set BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookies
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
    ini_set('session.use_strict_mode', 1); // Prevent session fixation
    session_start();
}

// Security Headers - Prevent various attacks
header("X-Frame-Options: SAMEORIGIN"); // Prevent clickjacking
header("X-Content-Type-Options: nosniff"); // Prevent MIME sniffing
header("X-XSS-Protection: 1; mode=block"); // XSS protection
header("Referrer-Policy: strict-origin-when-cross-origin"); // Control referrer information
header("Permissions-Policy: geolocation=(), microphone=(), camera=()"); // Disable unnecessary features
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;"); // CSP

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
 * Sanitize output for HTML attributes (URLs, IDs, etc.)
 * 
 * @param string|null $data The data to sanitize
 * @return string The sanitized data
 */
function sanitize_attr($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize output for JavaScript contexts
 * 
 * @param string|null $data The data to sanitize
 * @return string The sanitized data
 */
function sanitize_js($data) {
    return json_encode($data ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Sanitize filename to prevent directory traversal
 * 
 * @param string $filename The filename to sanitize
 * @return string The sanitized filename
 */
function secure_filename($filename) {
    // Remove any path components
    $filename = basename($filename);
    
    // Remove special characters except alphanumeric, dot, dash, underscore
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    // Remove multiple dots (prevent ../)
    $filename = preg_replace('/\.+/', '.', $filename);
    
    return $filename;
}

/**
 * Validate file path to prevent directory traversal attacks
 * 
 * @param string $file_path The file path to validate
 * @param string $base_dir The base directory that files must be within
 * @return bool True if path is safe, false otherwise
 */
function validate_file_path($file_path, $base_dir) {
    // Get the real absolute path
    $real_base = realpath($base_dir);
    $real_path = realpath($file_path);
    
    // If realpath returns false, file doesn't exist or path is invalid
    if ($real_path === false || $real_base === false) {
        return false;
    }
    
    // Check if the real path starts with the base directory
    return strpos($real_path, $real_base) === 0;
}

/**
 * Safely delete a file with path validation
 * 
 * @param string $file_path The file path to delete
 * @param string $base_dir The base directory that files must be within
 * @return bool True if file was deleted, false otherwise
 */
function safe_unlink($file_path, $base_dir) {
    // Validate the path is within allowed directory
    if (!validate_file_path($file_path, $base_dir)) {
        error_log("Path traversal attempt blocked: " . $file_path);
        return false;
    }
    
    // Check file exists and is a file (not directory)
    if (!file_exists($file_path) || !is_file($file_path)) {
        return false;
    }
    
    // Attempt to delete
    return @unlink($file_path);
}

/**
 * Validate file upload MIME type
 * 
 * @param string $file_path The uploaded file path
 * @param array $allowed_types Array of allowed MIME types
 * @return bool True if MIME type is allowed
 */
function validate_file_type($file_path, $allowed_types) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    return in_array($mime_type, $allowed_types);
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

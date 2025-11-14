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
header("X-Frame-Options: DENY"); // Prevent clickjacking - deny all framing
header("X-Content-Type-Options: nosniff"); // Prevent MIME sniffing
header("X-XSS-Protection: 1; mode=block"); // XSS protection
header("Referrer-Policy: strict-origin-when-cross-origin"); // Control referrer information
header("Permissions-Policy: geolocation=(), microphone=(), camera=()"); // Disable unnecessary features
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; frame-ancestors 'none';"); // CSP with frame-ancestors

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
 * Check and process remember me token for auto-login
 * 
 * @return bool True if user was auto-logged in, false otherwise
 */
function check_remember_me() {
    // Skip if already logged in
    if (isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Randomly cleanup expired tokens (1% chance)
    if (rand(1, 100) === 1) {
        cleanup_expired_remember_tokens();
    }
    
    // Check if remember token cookie exists
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    require_once __DIR__ . '/db_connect.php';
    
    try {
        $token = $_COOKIE['remember_token'];
        $hashed_token = hash('sha256', $token);
        
        // Find valid token in database
        $sql = "SELECT rt.user_id, u.role, u.first_name, u.last_name, u.staff_id, u.position, 
                       u.phone_number, u.department_id, u.status, u.password_reset_required, u.profile_picture
                FROM remember_tokens rt
                JOIN users u ON rt.user_id = u.id
                WHERE rt.token = :token 
                AND rt.expires_at > NOW()
                AND u.status = 'active'";
        
        $stmt = $GLOBALS['pdo']->prepare($sql);
        $stmt->execute(['token' => $hashed_token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Valid token found - log user in
            session_regenerate_id(true);
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['user_role'] = $result['role'];
            $_SESSION['user_first_name'] = $result['first_name'];
            $_SESSION['user_last_name'] = $result['last_name'];
            $_SESSION['user_staff_id'] = $result['staff_id'];
            $_SESSION['user_position'] = $result['position'];
            $_SESSION['user_phone_number'] = $result['phone_number'];
            $_SESSION['user_department_id'] = $result['department_id'];
            $_SESSION['logged_in_at'] = time();
            $_SESSION['password_reset_required'] = (bool)$result['password_reset_required'];
            $_SESSION['user_profile_picture'] = $result['profile_picture'];
            $_SESSION['auto_logged_in'] = true;
            
            return true;
        } else {
            // Invalid or expired token - remove cookie
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
            return false;
        }
    } catch (PDOException $e) {
        error_log("Remember me error: " . $e->getMessage());
        return false;
    }
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
 * Clean up expired remember me tokens (should be called periodically)
 */
function cleanup_expired_remember_tokens() {
    require_once __DIR__ . '/db_connect.php';
    
    try {
        $sql = "DELETE FROM remember_tokens WHERE expires_at < NOW()";
        $GLOBALS['pdo']->exec($sql);
    } catch (PDOException $e) {
        error_log("Error cleaning up expired tokens: " . $e->getMessage());
    }
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

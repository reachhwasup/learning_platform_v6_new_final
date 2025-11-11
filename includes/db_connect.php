<?php
/**
 * Database Connection Script
 *
 * This script connects to the MySQL database using PDO.
 * It sets up the connection details and a PDO object for database interactions.
 * Remember to replace the placeholder credentials with your actual database details.
 */

// --- Database Configuration ---
// Define your database connection parameters.
define('DB_HOST', 'localhost'); // Your database host (usually 'localhost')
define('DB_NAME', 'security_awareness_db_v6'); // The name of your database
define('DB_USER', 'root');      // Your database username (default for XAMPP is 'root')
define('DB_PASS', '');          // Your database password (default for XAMPP is empty)
define('DB_CHARSET', 'utf8mb4'); // The character set

// --- Data Source Name (DSN) ---
// This string tells PDO which driver to use and how to connect.
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// --- PDO Connection Options ---
// An array of options to configure the PDO connection.
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

// --- Establish the Connection ---
try {
    // Create a new PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If the connection fails, stop the script and display an error message.
    // In a production environment, you would log this error instead of showing it to the user.
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration and try again later.");
}
?>

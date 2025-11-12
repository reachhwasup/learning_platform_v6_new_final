<?php
/**
 * Security Logging Endpoint
 * 
 * Receives and logs suspicious activity from the client-side security.js script.
 * This helps track potential security violations and user attempts to bypass protections.
 */

require_once '../../includes/db_connect.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate data
if (!isset($data['action']) || !isset($data['timestamp'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Get user and system information
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$action = $data['action'] ?? 'unknown';
$url = $data['url'] ?? $_SERVER['HTTP_REFERER'] ?? 'unknown';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$timestamp = date('Y-m-d H:i:s');

// Check if security_logs table exists, create if not
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS security_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        url TEXT,
        timestamp DATETIME NOT NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_timestamp (timestamp),
        INDEX idx_ip_address (ip_address),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table might already exist or creation failed
    error_log("Security logs table check: " . $e->getMessage());
}

// Insert the log entry
try {
    $stmt = $conn->prepare("
        INSERT INTO security_logs (user_id, action, ip_address, user_agent, url, timestamp)
        VALUES (:user_id, :action, :ip_address, :user_agent, :url, :timestamp)
    ");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent,
        ':url' => $url,
        ':timestamp' => $timestamp
    ]);
    
    // Check for excessive violations (5 or more in the last hour)
    if ($user_id) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as violation_count
            FROM security_logs
            WHERE user_id = :user_id
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If too many violations, suggest logout
        if ($result['violation_count'] >= 5) {
            // Log the excessive violations
            error_log("SECURITY ALERT: User ID {$user_id} has {$result['violation_count']} security violations in the last hour from IP {$ip_address}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Activity logged',
                'action' => 'logout', // Signal to client to force logout
                'violation_count' => $result['violation_count']
            ]);
            exit;
        }
    }
    
    // Check for excessive violations from same IP (10 or more in the last hour)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as violation_count
        FROM security_logs
        WHERE ip_address = :ip_address
        AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([':ip_address' => $ip_address]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['violation_count'] >= 10) {
        // Log the IP-based excessive violations
        error_log("SECURITY ALERT: IP {$ip_address} has {$result['violation_count']} security violations in the last hour");
        
        echo json_encode([
            'success' => true,
            'message' => 'Activity logged',
            'action' => 'block',
            'violation_count' => $result['violation_count']
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Activity logged'
    ]);
    
} catch (PDOException $e) {
    error_log("Security log insertion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to log activity'
    ]);
}

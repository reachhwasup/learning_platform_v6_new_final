<?php
session_start();
// Mock session data for testing
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['full_name'] = 'Test User';

// Capture the rendered output
ob_start();
try {
    include 'final_assessment.php';
    $output = ob_get_clean();

    // Count script tags
    preg_match_all('/<script/', $output, $matches);
    echo "Number of <script> tags: " . count($matches[0]) . "\n";

    // Find startAssessment
    preg_match_all('/startAssessment/', $output, $matches2);
    echo "Number of 'startAssessment' references: " . count($matches2[0]) . "\n";

    // Check for syntax errors
    $lines = explode("\n", $output);
    echo "Total output lines: " . count($lines) . "\n";

    echo "\nCheck complete!\n";
} catch (Exception $e) {
    ob_end_clean();
    echo "Error: " . $e->getMessage() . "\n";
}
?>

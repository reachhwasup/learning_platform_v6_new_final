<?php
/**
 * User Signup API Endpoint
 *
 * Handles new user registration.
 */

header('Content-Type: application/json');

require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// --- Input Gathering & Basic Validation ---
$required_fields = ['first_name', 'last_name', 'email', 'staff_id', 'position', 'password', 'password_confirm', 'department_id'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $response['message'] = 'Please fill in all required fields.';
        echo json_encode($response);
        exit;
    }
}

$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$email = trim($_POST['email']);
$staff_id = trim($_POST['staff_id']);
$position = trim($_POST['position']);
$password = $_POST['password'];
$password_confirm = $_POST['password_confirm'];
$department_id = $_POST['department_id'];
// Optional fields
$gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
$dob = !empty($_POST['dob']) ? $_POST['dob'] : null;


// --- Advanced Validation ---
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email format.';
    echo json_encode($response);
    exit;
}
if (strlen($password) < 8) {
    $response['message'] = 'Password must be at least 8 characters long.';
    echo json_encode($response);
    exit;
}
if ($password !== $password_confirm) {
    $response['message'] = 'Passwords do not match.';
    echo json_encode($response);
    exit;
}

try {
    // --- Check for Duplicates ---
    $sql = "SELECT id FROM users WHERE email = :email OR staff_id = :staff_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email, 'staff_id' => $staff_id]);
    if ($stmt->fetch()) {
        $response['message'] = 'An account with this email or Staff ID already exists.';
        echo json_encode($response);
        exit;
    }

    // --- Hash Password ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- Insert User into Database ---
    $sql = "INSERT INTO users (first_name, last_name, email, staff_id, password, position, department_id, gender, dob) 
            VALUES (:first_name, :last_name, :email, :staff_id, :password, :position, :department_id, :gender, :dob)";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'staff_id' => $staff_id,
        'password' => $hashed_password,
        'position' => $position,
        'department_id' => $department_id,
        'gender' => $gender,
        'dob' => $dob
    ]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Account created successfully! You will be redirected to the login page.';
    } else {
        $response['message'] = 'Failed to create account. Please try again.';
    }

} catch (PDOException $e) {
    error_log("Signup Error: " . $e->getMessage());
    $response['message'] = 'A server error occurred. Please try again later.';
}

echo json_encode($response);
?>

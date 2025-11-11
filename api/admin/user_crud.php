<?php
/**
 * User CRUD API Endpoint (BULK UPLOAD FINAL FIX)
 *
 * Handles all admin actions for users, with corrected logic for bulk uploads
 * supporting both CSV and XLSX files.
 */

require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$response = ['success' => false, 'message' => 'Invalid request.'];
$action = $_REQUEST['action'] ?? null;

try {
    switch ($action) {
        case 'get':
            $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');
            
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, staff_id, position, phone_number, gender, department_id, role, status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $response = ['success' => true, 'user' => $user];
            } else {
                throw new Exception('User not found.');
            }
            break;

        case 'add':
            $pdo->beginTransaction();
            $required = ['first_name', 'last_name', 'staff_id', 'department_id', 'role', 'status'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required.');
            }

            $username = generate_unique_username($pdo, $_POST['first_name'], $_POST['last_name']);
            $password = $_POST['password'] ?: 'APD@123456789';
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (first_name, last_name, username, staff_id, position, phone_number, gender, password, department_id, role, status, password_reset_required) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $username, $_POST['staff_id'],
                $_POST['position'], $_POST['phone_number'], $_POST['gender'], $hashed_password, 
                $_POST['department_id'], $_POST['role'], $_POST['status']
            ]);
            $new_user_id = $pdo->lastInsertId();

            $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
            $pdo->prepare($hist_sql)->execute([$new_user_id, $hashed_password]);

            $pdo->commit();
            $response = ['success' => true, 'message' => 'User added successfully.'];
            break;

        case 'update':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');

            if ($user_id == $_SESSION['user_id'] && $_POST['role'] !== 'admin') {
                throw new Exception('Admins cannot change their own role.');
            }

            $sql = "UPDATE users SET first_name=?, last_name=?, staff_id=?, position=?, phone_number=?, gender=?, department_id=?, role=?, status=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['staff_id'],
                $_POST['position'], $_POST['phone_number'], $_POST['gender'], $_POST['department_id'],
                $_POST['role'], $_POST['status'], $user_id
            ]);

            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 12) {
                    throw new Exception('New password must be at least 12 characters.');
                }
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $pass_sql = "UPDATE users SET password = ?, password_reset_required = 0, password_last_changed = NOW() WHERE id = ?";
                $pdo->prepare($pass_sql)->execute([$hashed_password, $user_id]);
                $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
                $pdo->prepare($hist_sql)->execute([$user_id, $hashed_password]);
            }

            $response = ['success' => true, 'message' => 'User updated successfully.'];
            break;

        case 'delete':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');
            if ($user_id == $_SESSION['user_id']) throw new Exception('Admins cannot delete their own account.');

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $response = ['success' => true, 'message' => 'User deleted successfully.'];
            break;
        
        case 'reset_password':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');

            $pdo->beginTransaction();
            $new_password = 'APD@123456789';
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $sql = "UPDATE users SET password = ?, password_reset_required = 1, password_last_changed = NOW() WHERE id = ?";
            $pdo->prepare($sql)->execute([$hashed_password, $user_id]);

            $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
            $pdo->prepare($hist_sql)->execute([$user_id, $hashed_password]);

            $pdo->commit();
            $response = ['success' => true, 'message' => "Password has been reset to: $new_password"];
            break;

        case 'unlock':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');
            
            $sql = "UPDATE users SET status = 'active', failed_login_attempts = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $response = ['success' => true, 'message' => 'User account has been unlocked.'];
            break;

        case 'bulk_upload':
            if (!isset($_FILES['user_file']) || $_FILES['user_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error.');
            }
            $file_path = $_FILES['user_file']['tmp_name'];
            $file_name = $_FILES['user_file']['name'];
            
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['csv', 'xlsx'];
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Please upload a CSV or XLSX file.');
            }

            $spreadsheet = IOFactory::load($file_path);
            $sheet = $spreadsheet->getActiveSheet();
            
            $header_row = null;
            $data_rows = [];
            $found_header = false;

            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $current_row_data = [];
                foreach ($cellIterator as $cell) {
                    $current_row_data[] = $cell->getFormattedValue();
                }

                if (empty(array_filter($current_row_data, function($a) { return $a !== null && $a !== ''; }))) {
                    continue;
                }

                if (!$found_header) {
                    $header_row = $current_row_data;
                    $found_header = true;
                } else {
                    $data_rows[] = $current_row_data;
                }
            }

            if ($header_row === null) {
                throw new Exception('Could not find a header row in the uploaded file. The file might be empty or formatted incorrectly.');
            }

            $header_aliases = [
                'staff_id' => ['id', 'staffid', 'staff_id'],
                'first_name' => ['firstname', 'first_name'],
                'last_name' => ['lastname', 'last_name'],
                'gender' => ['gender'],
                'position' => ['position'],
                'department_name' => ['department', 'department_name'],
                'phone_number' => ['phone', 'phonenumber', 'phone_number']
            ];

            $column_map = [];
            $raw_headers = [];
            foreach ($header_row as $index => $header_name) {
                if ($header_name) {
                    $raw_headers[] = $header_name;
                    $cleaned_header = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', trim($header_name));
                    $normalized_header = strtolower(preg_replace('/[^a-z0-9]/i', '', $cleaned_header));
                    
                    foreach ($header_aliases as $canonical_name => $aliases) {
                        if (in_array($normalized_header, $aliases)) {
                            $column_map[$canonical_name] = $index;
                            break;
                        }
                    }
                }
            }

            if (empty($column_map)) {
                $raw_header_string = !empty($raw_headers) ? '"' . implode('", "', array_map('htmlspecialchars', $raw_headers)) . '"' : 'None';
                throw new Exception(
                    "Could not find any valid headers in the uploaded file. The script read the following headers: [{$raw_header_string}]. Please ensure headers are in the first row and are not empty."
                );
            }

            $required_headers = ['staff_id', 'first_name', 'last_name', 'department_name'];
            foreach ($required_headers as $required_header) {
                if (!isset($column_map[$required_header])) {
                    $found_headers = !empty($column_map) ? '"' . implode('", "', array_keys($column_map)) . '"' : 'None';
                    throw new Exception(
                        "Missing required column for '{$required_header}'. Please check your file's column headers. " .
                        "Found the following valid headers: {$found_headers}."
                    );
                }
            }

            $depts_stmt = $pdo->query("SELECT name, id FROM departments");
            $departments = $depts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $departments_lower = array_change_key_case($departments, CASE_LOWER);

            $success_count = 0;
            $fail_count = 0;
            $failed_entries = [];

            $pdo->beginTransaction();
            foreach ($data_rows as $row_index => $row) {
                $staff_id = trim($row[$column_map['staff_id']] ?? '');
                
                if (empty($staff_id)) {
                    continue;
                }

                $first_name = trim($row[$column_map['first_name']] ?? '');
                $last_name = trim($row[$column_map['last_name']] ?? '');
                $department_name_csv = trim($row[$column_map['department_name']] ?? '');
                
                $gender = isset($column_map['gender']) ? trim($row[$column_map['gender']] ?? null) : null;
                $position = isset($column_map['position']) ? trim($row[$column_map['position']] ?? null) : null;
                $phone_number = isset($column_map['phone_number']) ? trim($row[$column_map['phone_number']] ?? null) : null;

                try {
                    if (empty($first_name) || empty($last_name) || empty($department_name_csv)) {
                        throw new Exception("Missing required fields (first_name, last_name, or department_name).");
                    }
                    
                    $cleaned_dept_name = preg_replace('/[[:^print:]]/', '', $department_name_csv);
                    $department_name_lower = strtolower($cleaned_dept_name);
                    
                    $department_id = $departments_lower[$department_name_lower] ?? false;
                    if ($department_id === false) {
                        throw new Exception("Department '{$department_name_csv}' not found.");
                    }
                    
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE staff_id = ?");
                    $stmt_check->execute([$staff_id]);
                    if ($stmt_check->fetch()) {
                        throw new Exception("Staff ID already exists.");
                    }

                    $username = generate_unique_username($pdo, $first_name, $last_name);
                    $password = 'APD@123456789';
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO users (staff_id, first_name, last_name, username, gender, position, department_id, phone_number, password, password_reset_required) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$staff_id, $first_name, $last_name, $username, $gender, $position, $department_id, $phone_number, $hashed_password]);
                    $new_user_id = $pdo->lastInsertId();

                    $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
                    $pdo->prepare($hist_sql)->execute([$new_user_id, $hashed_password]);
                    
                    $success_count++;
                } catch (Exception $e) {
                    $fail_count++;
                    $spreadsheet_row_num = $row_index + 2;
                    $failed_entries[] = "Row {$spreadsheet_row_num} (Staff ID '{$staff_id}'): " . $e->getMessage();
                }
            }
            $pdo->commit();

            $message = "$success_count users imported successfully.";
            if ($fail_count > 0) {
                $message .= " $fail_count users failed to import.";
            }
            $response = ['success' => true, 'message' => $message, 'failed_entries' => $failed_entries];
            break;

        case 'delete_all_normal_users':
            $stmt = $pdo->prepare("DELETE FROM users WHERE role = 'user'");
            $stmt->execute();
            $count = $stmt->rowCount();
            $response = ['success' => true, 'message' => "Successfully deleted {$count} normal users."];
            break;

        default:
            throw new Exception('Invalid action specified.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    $response['message'] = 'A database error occurred. Please check the logs.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

// --- Helper Functions ---

function generate_unique_username(PDO $pdo, string $first_name, string $last_name): string {
    $base_username = strtolower(preg_replace('/[^a-zA-Z]/', '', $first_name) . '.' . preg_replace('/[^a-zA-Z]/', '', $last_name));
    $username = $base_username;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch() === false) {
            return $username;
        }
        $username = $base_username . $counter;
        $counter++;
    }
}

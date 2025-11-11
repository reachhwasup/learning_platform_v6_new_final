<?php
/**
 * Question CRUD API Endpoint
 *
 * Handles all operations for questions and their options, including bulk import and deleting all.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// --- Admin Authentication ---
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// --- Check for PhpSpreadsheet library ---
$vendor_autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    echo json_encode(['success' => false, 'message' => 'Error: The required library PhpSpreadsheet is not installed. Please run "composer install" in the project root.']);
    exit;
}
require_once $vendor_autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;

$response = ['success' => false, 'message' => 'Invalid request.'];

// --- Handle GET requests (for fetching single question for edit modal) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_question') {
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Question ID not provided.']);
        exit;
    }
    try {
        $pdo->beginTransaction();
        $stmt_q = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt_q->execute([$_GET['id']]);
        $question = $stmt_q->fetch();

        if ($question) {
            $stmt_o = $pdo->prepare("SELECT id, option_text, is_correct FROM question_options WHERE question_id = ?");
            $stmt_o->execute([$_GET['id']]);
            $options = $stmt_o->fetchAll();
            $question['options'] = $options;
            $response = ['success' => true, 'data' => $question];
        } else {
            $response['message'] = 'Question not found.';
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $response['message'] = 'Database error.';
    }
    echo json_encode($response);
    exit;
}


// --- Handle POST requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Action: Import Questions from File ---
    if (isset($_POST['action']) && $_POST['action'] === 'import_questions') {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload failed or no file selected.']);
            exit;
        }

        $file = $_FILES['import_file']['tmp_name'];
        
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $imported_count = 0;

            $pdo->beginTransaction();

            for ($row = 2; $row <= $highestRow; $row++) {
                $question_text = trim($sheet->getCell('A' . $row)->getValue());
                if (empty($question_text)) continue;

                $question_type = strtolower(trim($sheet->getCell('B' . $row)->getValue()));
                $association = strtolower(trim($sheet->getCell('C' . $row)->getValue()));
                $module_order = trim($sheet->getCell('D' . $row)->getValue());

                $is_final = ($association === 'final_exam');
                $module_id = null;

                if (!$is_final) {
                    $stmt_mod = $pdo->prepare("SELECT id FROM modules WHERE module_order = ?");
                    $stmt_mod->execute([$module_order]);
                    $module_id = $stmt_mod->fetchColumn();
                    if (!$module_id) continue; 
                }

                $sql_q = "INSERT INTO questions (question_text, question_type, module_id, is_final_exam_question) VALUES (?, ?, ?, ?)";
                $stmt_q = $pdo->prepare($sql_q);
                $stmt_q->execute([$question_text, $question_type, $module_id, $is_final]);
                $question_id = $pdo->lastInsertId();

                for ($col_index = 0; $col_index < 5; $col_index++) {
                    $option_text_col = chr(ord('E') + ($col_index * 2));
                    $is_correct_col = chr(ord('F') + ($col_index * 2));

                    $option_text = trim($sheet->getCell($option_text_col . $row)->getValue());
                    if (empty($option_text)) continue;

                    $is_correct_val = strtoupper(trim($sheet->getCell($is_correct_col . $row)->getValue()));
                    $is_correct = ($is_correct_val === 'TRUE' || $is_correct_val === '1');

                    $sql_o = "INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                    $stmt_o = $pdo->prepare($sql_o);
                    $stmt_o->execute([$question_id, $option_text, $is_correct]);
                }
                $imported_count++;
            }

            $pdo->commit();
            $response = ['success' => true, 'message' => "Successfully imported {$imported_count} questions."];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Import Error: " . $e->getMessage());
            $response['message'] = 'An error occurred during import: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }

    // --- Action: Manual Add/Edit/Delete ---
    if (!isset($_POST['action'])) {
        echo json_encode($response);
        exit;
    }

    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        switch ($action) {
            case 'add_question':
            case 'edit_question':
                if (empty($_POST['question_text']) || empty($_POST['question_type']) || !isset($_POST['options']) || !is_array($_POST['options'])) {
                    throw new Exception('Required fields are missing.');
                }
                if ($_POST['association_type'] === 'module' && empty($_POST['module_id'])) {
                    throw new Exception('Please select a module.');
                }

                $is_final = $_POST['association_type'] === 'final_exam';
                $module_id = $is_final ? null : $_POST['module_id'];

                if ($action === 'add_question') {
                    $sql = "INSERT INTO questions (question_text, question_type, module_id, is_final_exam_question) VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_POST['question_text'], $_POST['question_type'], $module_id, $is_final]);
                    $question_id = $pdo->lastInsertId();
                } else {
                    $question_id = $_POST['question_id'];
                    $sql = "UPDATE questions SET question_text = ?, question_type = ?, module_id = ?, is_final_exam_question = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_POST['question_text'], $_POST['question_type'], $module_id, $is_final, $question_id]);
                    
                    $stmt_del = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
                    $stmt_del->execute([$question_id]);
                }

                $sql_option = "INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
                $stmt_option = $pdo->prepare($sql_option);
                
                // For single choice questions, get the correct answer index from radio button
                $correct_answer_index = isset($_POST['correct_answer']) ? $_POST['correct_answer'] : null;
                
                foreach ($_POST['options'] as $index => $option) {
                    // For single choice (radio), check if this option's index matches the selected one
                    // For multiple choice (checkbox), check if is_correct is set
                    if ($_POST['question_type'] === 'single') {
                        $is_correct = ($index == $correct_answer_index) ? 1 : 0;
                    } else {
                        $is_correct = isset($option['is_correct']) ? 1 : 0;
                    }
                    $stmt_option->execute([$question_id, $option['text'], $is_correct]);
                }
                
                $response = ['success' => true, 'message' => 'Question saved successfully.'];
                break;

            case 'delete_question':
                if (empty($_POST['question_id'])) {
                    throw new Exception('Question ID is required.');
                }
                $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([$_POST['question_id']]);
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => true, 'message' => 'Question deleted.'];
                } else {
                    $response['message'] = 'Question not found.';
                }
                break;

            case 'delete_all_questions':
                // This will delete all questions and their options due to ON DELETE CASCADE
                $pdo->exec("TRUNCATE TABLE questions");
                $response = ['success' => true, 'message' => 'All questions have been deleted.'];
                break;
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        $response['message'] = 'A server error occurred: ' . $e->getMessage();
    }
}

echo json_encode($response);

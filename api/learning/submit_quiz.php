<?php
// api/learning/submit_quiz.php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid request.'];
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'submit'; // Default action is 'submit'

try {
    $module_id = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
    if (!$module_id) {
        throw new Exception('Module ID is missing.');
    }

    if ($action === 'clear_answers') {
        // Clear previous answers for this module
        $sql_delete = "DELETE FROM user_answers WHERE user_id = ? AND question_id IN (SELECT id FROM questions WHERE module_id = ?)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([$user_id, $module_id]);
        $response = ['success' => true];

    } else { // 'submit' action
        $answers = $_POST['answers'] ?? [];
        if (empty($answers)) {
            throw new Exception('No answers were submitted.');
        }

        $question_ids = array_keys($answers);
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));

        // Get all correct options for the submitted questions
        $sql_correct = "SELECT question_id, GROUP_CONCAT(id ORDER BY id) as correct_ids 
                        FROM question_options 
                        WHERE question_id IN ($placeholders) AND is_correct = 1 
                        GROUP BY question_id";
        $stmt_correct = $pdo->prepare($sql_correct);
        $stmt_correct->execute($question_ids);
        $correct_options = $stmt_correct->fetchAll(PDO::FETCH_KEY_PAIR);

        $score = 0;
        $pdo->beginTransaction();

        // First, clear any previous answers for this specific set of questions for this user
        $sql_delete = "DELETE FROM user_answers WHERE user_id = ? AND question_id IN ($placeholders)";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute(array_merge([$user_id], $question_ids));

        // Now, insert new answers and calculate score
        $sql_insert = "INSERT INTO user_answers (user_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($answers as $question_id => $selected) {
            $user_correct_for_question = false;
            $correct_answer_ids = explode(',', $correct_options[$question_id] ?? '');
            
            if (is_array($selected)) { // Multiple choice
                sort($selected);
                $user_answer_str = implode(',', $selected);
                if ($user_answer_str === ($correct_options[$question_id] ?? '')) {
                    $score++;
                    $user_correct_for_question = true;
                }
                foreach ($selected as $option_id) {
                    $is_option_correct = in_array($option_id, $correct_answer_ids);
                    $stmt_insert->execute([$user_id, $question_id, $option_id, $is_option_correct]);
                }
            } else { // Single choice
                if (in_array($selected, $correct_answer_ids)) {
                    $score++;
                    $user_correct_for_question = true;
                }
                $stmt_insert->execute([$user_id, $question_id, $selected, $user_correct_for_question]);
            }
        }
        
        $pdo->commit();
        $response = ['success' => true, 'score' => $score];
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
    error_log($e->getMessage());
}

echo json_encode($response);
?>

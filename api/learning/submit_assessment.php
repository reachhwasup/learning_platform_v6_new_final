<?php
/**
 * Submit Final Assessment API Endpoint
 *
 * Receives answers, calculates score, and saves results.
 * Links each answer to the specific assessment attempt.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Authenticate user and check request method
if (!is_logged_in()) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_answers = $_POST['answers'] ?? [];
$total_questions = count($user_answers);
$correct_answers = 0;

if (empty($user_answers)) {
    $response['message'] = 'No answers were submitted.';
    echo json_encode($response);
    exit;
}

try {
    $pdo->beginTransaction();

    // First, create the assessment record to get an ID
    // We will update the score later.
    $sql_insert_assessment = "INSERT INTO final_assessments (user_id, score, status) VALUES (?, 0, 'pending')";
    $stmt_insert = $pdo->prepare($sql_insert_assessment);
    $stmt_insert->execute([$user_id]);
    $assessment_id = $pdo->lastInsertId();

    $question_ids = array_keys($user_answers);
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    
    $sql_correct = "SELECT question_id, id FROM question_options WHERE question_id IN ($placeholders) AND is_correct = 1";
    $stmt_correct = $pdo->prepare($sql_correct);
    $stmt_correct->execute($question_ids);
    $correct_options_db = $stmt_correct->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

    // Grade the submission and log answers with the new assessment_id
    foreach ($user_answers as $question_id => $selected_options) {
        $selected_options = is_array($selected_options) ? $selected_options : [$selected_options];
        $correct_ids_for_q = $correct_options_db[$question_id] ?? [];
        
        sort($selected_options);
        sort($correct_ids_for_q);

        $is_question_correct = ($selected_options == $correct_ids_for_q);
        if ($is_question_correct) {
            $correct_answers++;
        }

        // Log each selected option for this attempt
        foreach ($selected_options as $selected_option_id) {
             $sql_log_answer = "INSERT INTO user_answers (user_id, assessment_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?, ?)";
             $stmt_log = $pdo->prepare($sql_log_answer);
             $stmt_log->execute([$user_id, $assessment_id, $question_id, $selected_option_id, $is_question_correct]);
        }
    }

    // Calculate score
    $points_per_question = 5;
    $score_in_points = $correct_answers * $points_per_question;
    $passing_score_points = 80;
    $total_possible_points = $total_questions * $points_per_question;
    $status = ($score_in_points >= $passing_score_points) ? 'passed' : 'failed';

    // Now, update the assessment record with the final score and status
    $sql_update_assessment = "UPDATE final_assessments SET score = ?, status = ? WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update_assessment);
    $stmt_update->execute([$score_in_points, $status, $assessment_id]);

    $pdo->commit();
    $response = [
        'success' => true,
        'message' => 'Assessment graded successfully.',
        'score' => $score_in_points,
        'total_score' => $total_possible_points,
        'status' => $status
    ];

} catch (PDOException $e) {
    if($pdo->inTransaction()){
        $pdo->rollBack();
    }
    error_log("Submit Assessment Error: " . $e->getMessage());
    $response['message'] = 'A server error occurred while submitting the assessment.';
}

echo json_encode($response);

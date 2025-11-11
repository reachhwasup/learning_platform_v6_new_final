<?php
$page_title = 'Final Assessment';
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$error = '';
$message = '';
$can_take_assessment = false;
$reason = '';
$show_results = false;
$result_data = [];

try {
    // 2. Check if user has completed all modules AND their corresponding quizzes
    $total_modules = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
    
    // This query now calculates the number of fully completed modules.
    // A module is considered complete if the video is watched AND the quiz is taken (if one exists).
    $sql_completed_modules = "
        SELECT COUNT(DISTINCT up.module_id)
        FROM user_progress up
        WHERE up.user_id = :user_id AND (
            -- Condition 1: The module has no quiz, so watching the video is enough.
            (SELECT COUNT(*) FROM questions q WHERE q.module_id = up.module_id) = 0
            OR
            -- Condition 2: The module has a quiz, and the user has submitted answers for it.
            EXISTS (
                SELECT 1
                FROM user_answers ua
                JOIN questions q_ua ON ua.question_id = q_ua.id
                WHERE ua.user_id = up.user_id AND q_ua.module_id = up.module_id
            )
        )";
    $stmt_completed = $pdo->prepare($sql_completed_modules);
    $stmt_completed->execute([':user_id' => $user_id]);
    $completed_modules = $stmt_completed->fetchColumn();

    if ($total_modules > 0 && $completed_modules >= $total_modules) {
        $can_take_assessment = true;
    } else {
        $reason = "You must complete all learning modules and their quizzes before taking the final assessment.";
    }

    // 3. Check assessment status FIRST - if passed on FIRST attempt or any attempt, block access
    $has_passed = false;
    $latest_attempt = null;
    $attempt_count = 0;
    $first_attempt = null;
    
    // Get total attempt count
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM final_assessments WHERE user_id = ?");
    $stmt_count->execute([$user_id]);
    $attempt_count = $stmt_count->fetchColumn();
    
    if ($attempt_count > 0) {
        // Get the FIRST attempt to check if user passed on first try
        $stmt_first = $pdo->prepare("SELECT status, score, completed_at FROM final_assessments WHERE user_id = ? ORDER BY completed_at ASC LIMIT 1");
        $stmt_first->execute([$user_id]);
        $first_attempt = $stmt_first->fetch();
        
        // Also get latest attempt for display purposes
        $stmt_latest = $pdo->prepare("SELECT status, score, completed_at FROM final_assessments WHERE user_id = ? ORDER BY completed_at DESC LIMIT 1");
        $stmt_latest->execute([$user_id]);
        $latest_attempt = $stmt_latest->fetch();
        
        // If user passed on FIRST attempt, block all retakes
        if ($first_attempt && $first_attempt['status'] === 'passed') {
            $has_passed = true;
            $can_take_assessment = false;
            $reason = "Congratulations! You passed on your first attempt. No retakes are allowed.";
        }
        // If user didn't pass on first attempt, but passed later, still block
        elseif ($latest_attempt && $latest_attempt['status'] === 'passed') {
            $has_passed = true;
            $can_take_assessment = false;
            $reason = "Congratulations! You have already passed the final assessment.";
        }
    }
    
    // 4. Initialize questions array and fetch questions if user is eligible
    $questions = [];
    $options = [];
    if ($can_take_assessment) {
        $sql_questions = "SELECT id, question_text, question_type FROM questions WHERE is_final_exam_question = 1 ORDER BY RAND() LIMIT 20";
        $stmt_questions = $pdo->query($sql_questions);
        $questions = $stmt_questions->fetchAll();

        if (!empty($questions)) {
            $question_ids = array_column($questions, 'id');
            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
            $sql_options = "SELECT id, question_id, option_text FROM question_options WHERE question_id IN ($placeholders) ORDER BY RAND()";
            $stmt_options = $pdo->prepare($sql_options);
            $stmt_options->execute($question_ids);
            
            while ($row = $stmt_options->fetch()) {
                $options[$row['question_id']][] = $row;
            }
        }
    }
    
    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_take_assessment && !empty($questions)) {
        $submitted_answers = $_POST['answers'] ?? [];
        
        if (empty($submitted_answers)) {
            $error = "Please answer all questions before submitting.";
        } else {
            $question_ids = array_keys($submitted_answers);
            
            if (count($question_ids) < count($questions)) {
                 $error = "Please answer all " . count($questions) . " questions before submitting.";
            } else {
                // Process the answers
                $placeholders = rtrim(str_repeat('?,', count($question_ids)), ',');
                $sql = "SELECT qo.question_id, qo.id as answer_id FROM question_options qo WHERE qo.question_id IN ($placeholders) AND qo.is_correct = 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($question_ids);
                $correct_options_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $correct_answers_map = [];
                foreach ($correct_options_raw as $opt) {
                    $correct_answers_map[$opt['question_id']][] = $opt['answer_id'];
                }

                $score = 0;
                $user_responses_to_save = [];
                
                foreach ($submitted_answers as $q_id => $user_selection) {
                    if (!is_array($user_selection)) {
                        $user_selection = [$user_selection];
                    }

                    $correct_selection = $correct_answers_map[$q_id] ?? [];
                    sort($user_selection);
                    sort($correct_selection);

                    $is_question_correct = ($user_selection == $correct_selection);
                    if ($is_question_correct) {
                        $score += 5; // 5 points per question
                    }

                    foreach ($user_selection as $selected_option_id) {
                        $user_responses_to_save[] = [
                            'question_id' => $q_id,
                            'selected_option_id' => $selected_option_id,
                            'is_correct' => $is_question_correct
                        ];
                    }
                }
    
                $status = ($score >= 80) ? 'passed' : 'failed';
    
                $pdo->beginTransaction();

                $sql_assessment = "INSERT INTO final_assessments (user_id, score, status) VALUES (?, ?, ?)";
                $stmt_assessment = $pdo->prepare($sql_assessment);
                $stmt_assessment->execute([$user_id, $score, $status]);
                $assessment_id = $pdo->lastInsertId();

                $sql_answers = "INSERT INTO user_answers (user_id, assessment_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?, ?)";
                $stmt_answers = $pdo->prepare($sql_answers);
                foreach ($user_responses_to_save as $response) {
                    $stmt_answers->execute([$user_id, $assessment_id, $response['question_id'], $response['selected_option_id'], $response['is_correct']]);
                }
                
                $pdo->commit();

                $show_results = true;
                $result_data = ['score' => $score, 'total_score' => count($questions) * 5, 'status' => $status];
                $can_take_assessment = false;
            }
        }
    }

} catch (PDOException $e) {
    if($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Final Assessment Page Error: " . $e->getMessage());
    die("An error occurred while loading the assessment. Please try again later.");
}

$page_title = 'Final Assessment';
require_once 'includes/header.php';
?>

<style>
.gradient-bg {
    background: linear-gradient(135deg, #0052cc 0%, #0041a3 100%);
}

.card-shadow {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.progress-ring {
    transform: rotate(-90deg);
}

.progress-ring-circle {
    transition: stroke-dashoffset 0.35s;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

.animate-bounce-slow {
    animation: bounce 2s infinite;
}

.question-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    position: relative;
}

.question-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.question-slide {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.option-card {
    transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    border: none !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
}

/* Remove focus outline from cards */
.option-card:focus,
.option-card:focus-visible,
.option-card:focus-within {
    outline: none !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06) !important;
    border: none !important;
}

/* Single-choice questions: full card is clickable */
.option-card[data-question-type="single"]:hover {
    transform: translateX(4px);
    background-color: #f9fafb;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: none !important;
}

/* Multiple-choice questions: full card is clickable */
.option-card[data-question-type="multiple"]:hover {
    transform: translateX(4px);
    background-color: #f9fafb;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: none !important;
}

.option-card.selected {
    background: linear-gradient(to right, #d1fae5 0%, #a7f3d0 100%);
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
    border: none !important;
}

.option-card[data-question-type="single"].selected {
    transform: translateX(4px);
    border: none !important;
}

/* Checkmark styling for checkboxes and radio buttons */
.option-card input[type="checkbox"],
.option-card input[type="radio"] {
    cursor: pointer;
    outline: none !important;
    box-shadow: none !important;
}

/* Remove ALL focus outlines and borders */
.option-card input[type="checkbox"]:focus,
.option-card input[type="radio"]:focus,
.option-card input[type="checkbox"]:focus-visible,
.option-card input[type="radio"]:focus-visible {
    outline: none !important;
    box-shadow: none !important;
    border-color: #d1d5db !important;
}

.option-card input[type="checkbox"]:checked:focus,
.option-card input[type="radio"]:checked:focus,
.option-card input[type="checkbox"]:checked:focus-visible,
.option-card input[type="radio"]:checked:focus-visible {
    outline: none !important;
    box-shadow: none !important;
    border-color: #10b981 !important;
}

.option-card input[type="radio"] {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    width: 1.5rem;
    height: 1.5rem;
    border: 3px solid #d1d5db;
    border-radius: 50%;
    background-color: white;
    position: relative;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.option-card input[type="radio"]:checked {
    border-color: #10b981;
    background-color: #10b981;
    border-width: 3px;
}

.option-card input[type="radio"]:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-weight: bold;
    font-size: 14px;
    line-height: 1;
}

.option-card input[type="checkbox"] {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    width: 1.5rem;
    height: 1.5rem;
    background-color: white;
    border: 3px solid #d1d5db;
    border-radius: 0.25rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
    position: relative;
}

.option-card input[type="checkbox"]:checked {
    background-color: #10b981;
    border-color: #10b981;
}

.option-card input[type="checkbox"]:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-weight: bold;
    font-size: 14px;
    line-height: 1;
}

/* Prevent text selection and interaction for multiple choice labels */
.pointer-events-none {
    pointer-events: none;
    user-select: none;
}

.pulse-animation {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}

/* Confetti Animation */
.confetti {
    position: fixed;
    width: 10px;
    height: 10px;
    top: -10px;
    z-index: 1000;
    animation: confetti-fall 3s linear forwards;
}

@keyframes confetti-fall {
    to {
        transform: translateY(100vh) rotate(360deg);
        opacity: 0;
    }
}

/* Question Navigator */
.question-navigator {
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}

.nav-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #d1d5db;
    transition: all 0.3s ease;
    cursor: pointer;
}

.nav-dot.answered {
    background: #10b981;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.nav-dot.active {
    transform: scale(1.5);
    box-shadow: 0 0 12px rgba(0, 82, 204, 0.6);
}

.nav-dot:hover {
    transform: scale(1.3);
}

/* Timer Badge */
.timer-badge {
    animation: timer-pulse 2s ease-in-out infinite;
}

@keyframes timer-pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(0, 82, 204, 0.7);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(0, 82, 204, 0);
    }
}

/* Enhanced Progress Bar */
.progress-bar-wrapper {
    position: relative;
    overflow: hidden;
}

.progress-bar-shimmer {
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    100% {
        left: 100%;
    }
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-center;
    z-index: 9999;
}

.loading-overlay.active {
    display: flex;
}

/* Mobile Optimizations */
@media (max-width: 640px) {
    .question-card {
        padding: 1rem;
    }
    
    .option-card {
        padding: 0.75rem;
    }
    
    .question-navigator {
        position: relative;
        top: 0;
        max-height: none;
    }
}

/* Accessibility Improvements */
.option-card:focus-within {
    outline: 2px solid #0052cc;
    outline-offset: 2px;
}

/* Print Styles */
@media print {
    .question-navigator,
    .timer-badge,
    button {
        display: none !important;
    }
}
</style>

<div class="min-h-screen bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if ($error): ?>
            <!-- Enhanced Error Message -->
            <div class="bg-red-50 border-l-4 border-red-500 rounded-r-lg shadow-lg p-6 mb-8 animate-bounce-slow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-bold text-red-800">Assessment Error</h3>
                        <p class="text-red-700 mt-1"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($show_results): ?>
            <!-- Enhanced Results Display -->
            <div class="bg-white rounded-3xl card-shadow overflow-hidden">
                <div class="<?= $result_data['status'] === 'passed' ? 'bg-green-500' : 'bg-red-500' ?> px-8 py-12 text-center">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                        <?php if ($result_data['status'] === 'passed'): ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        <?php else: ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-4xl font-bold text-white mb-4">
                        <?= $result_data['status'] === 'passed' ? 'Congratulations!' : 'Keep Trying!' ?>
                    </h2>
                    <p class="text-xl text-white/90">
                        <?php if ($result_data['status'] === 'passed'): ?>
                            You have successfully passed the assessment!
                        <?php else: ?>
                            You can retake the assessment until you pass.
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="p-8">
                    <!-- Score Display -->
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center">
                            <div class="relative w-32 h-32">
                                <svg class="w-32 h-32 progress-ring">
                                    <circle cx="64" cy="64" r="56" stroke="#e5e7eb" stroke-width="12" fill="transparent"></circle>
                                    <circle cx="64" cy="64" r="56" stroke="<?= $result_data['status'] === 'passed' ? '#10b981' : '#ef4444' ?>" 
                                            stroke-width="12" fill="transparent" 
                                            stroke-dasharray="<?= 2 * 3.14159 * 56 ?>" 
                                            stroke-dashoffset="<?= 2 * 3.14159 * 56 * (1 - ($result_data['score'] / $result_data['total_score'])) ?>"
                                            class="progress-ring-circle"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-gray-800"><?= (int)$result_data['score'] ?></div>
                                        <div class="text-sm text-gray-600">/ <?= (int)$result_data['total_score'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-lg text-gray-600 mt-4">
                            Score: <?= round(($result_data['score'] / $result_data['total_score']) * 100, 1) ?>% 
                            <span class="text-sm">(Passing: 80%)</span>
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <?php if ($result_data['status'] === 'passed'): ?>
                            <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                                Back to Dashboard
                            </a>
                        <?php else: ?>
                            <a href="final_assessment.php" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Try Again
                            </a>
                            <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-all duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                                Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif (!$can_take_assessment): ?>
            <!-- Enhanced Not Eligible Message -->
            <div class="bg-white rounded-3xl card-shadow overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-12 text-center">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                        <?php if ($has_passed): ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        <?php else: ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-4xl font-bold text-white mb-4">Assessment Status</h2>
                    <p class="text-xl text-white/90"><?= htmlspecialchars($reason) ?></p>
                </div>
                
                <div class="p-8 text-center">
                    <?php if ($has_passed): ?>
                        <!-- Show Score and Status for Passed Users -->
                        <?php 
                        // Determine which attempt to display based on when they passed
                        $display_attempt = ($first_attempt && $first_attempt['status'] === 'passed') ? $first_attempt : $latest_attempt;
                        $passed_on_first = ($first_attempt && $first_attempt['status'] === 'passed');
                        ?>
                        <div class="max-w-2xl mx-auto mb-8">
                            <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-8">
                                <div class="text-center mb-6">
                                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-500 rounded-full mb-4">
                                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-2xl font-bold text-green-800 mb-2">Assessment Passed!</h3>
                                    <p class="text-green-700">
                                        You have successfully completed the final assessment!
                                    </p>
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                                    <div class="bg-white rounded-xl p-4 shadow-sm">
                                        <div class="text-3xl font-bold text-green-600 mb-1"><?= $display_attempt['score'] ?></div>
                                        <div class="text-sm text-gray-600">Your Score</div>
                                    </div>
                                    <div class="bg-white rounded-xl p-4 shadow-sm">
                                        <div class="text-lg font-bold text-purple-600 mb-1"><?= date('M d, Y', strtotime($display_attempt['completed_at'])) ?></div>
                                        <div class="text-sm text-gray-600">Completion Date</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            Back to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            Back to Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif (empty($questions)): ?>
            <!-- No Questions Available -->
            <div class="bg-white rounded-3xl card-shadow p-8 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gray-100 rounded-full mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Assessment Not Ready</h2>
                <p class="text-xl text-gray-600 mb-8">The final assessment is not available yet. Please contact an administrator.</p>
                <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-all duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Back to Dashboard
                </a>
            </div>

        <?php elseif ($can_take_assessment && !empty($questions)): ?>
            <!-- Introduction Screen (shown when assessment hasn't started) -->
            <div id="intro-screen">
                <div class="bg-white rounded-3xl card-shadow overflow-hidden max-w-4xl mx-auto">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-12 text-center">
                        <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        </div>
                        <h2 class="text-4xl font-bold text-white mb-4">Final Assessment</h2>
                        <p class="text-xl text-white/90">Test your knowledge and earn your certification</p>
                    </div>
                    
                    <!-- Assessment Overview -->
                    <div class="p-8">
                        <?php if ($has_passed): ?>
                            <!-- Already Passed Notice -->
                            <div class="bg-green-50 border-l-4 border-green-400 p-6 mb-8 rounded-r-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            <strong>Congratulations!</strong> 
                                            You've already passed this assessment. You can retake it to improve your score.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Assessment Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 text-center border-2 border-blue-200">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-700 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="text-3xl font-bold text-blue-900 mb-2"><?= count($questions) ?></div>
                                <div class="text-sm font-medium text-blue-700">Total Questions</div>
                            </div>
                            
                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 text-center border-2 border-green-200">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-green-600 to-green-700 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="text-3xl font-bold text-green-900 mb-2">80%</div>
                                <div class="text-sm font-medium text-green-700">Passing Score</div>
                            </div>
                            
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-6 text-center border-2 border-purple-200">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-purple-600 to-purple-700 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                    </svg>
                                </div>
                                <div class="text-3xl font-bold text-purple-900 mb-2">5</div>
                                <div class="text-sm font-medium text-purple-700">Points per Question</div>
                            </div>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-8 mb-8 border-2 border-blue-100">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                                <svg class="w-7 h-7 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Assessment Instructions
                            </h3>
                            <ul class="space-y-4 text-gray-700">
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-4 mt-0.5">1</div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 mb-1">Answer all <?= count($questions) ?> questions</p>
                                        <p class="text-sm text-gray-600">Navigate through questions one at a time using Next and Previous buttons</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-4 mt-0.5">2</div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 mb-1">Score 80% or higher to pass</p>
                                        <p class="text-sm text-gray-600">You need <?= ceil(count($questions) * 0.8) ?> correct answers out of <?= count($questions) ?> questions</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-4 mt-0.5">3</div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 mb-1">Each question is worth 5 points</p>
                                        <p class="text-sm text-gray-600">Maximum possible score: <?= count($questions) * 5 ?> points</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-4 mt-0.5">4</div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 mb-1">Take your time</p>
                                        <p class="text-sm text-gray-600">There is no time limit - read each question carefully before answering</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-4 mt-0.5">5</div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 mb-1">Review before submitting</p>
                                        <p class="text-sm text-gray-600">You can navigate back to any question to review or change your answer</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Start Button -->
                        <div class="text-center">
                            <button type="button" onclick="startAssessment()" class="inline-flex items-center justify-center px-12 py-5 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-xl font-bold rounded-2xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:scale-105 shadow-2xl hover:shadow-blue-500/50">
                                <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Start Assessment
                            </button>
                            <p class="text-sm text-gray-500 mt-4">Good luck! Click the button when you're ready to begin</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Assessment Quiz Form (hidden initially) -->
            <div id="quiz-screen" style="display: none;">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                    <!-- Question Navigator Sidebar -->
                    <div class="lg:col-span-1 order-2 lg:order-1">
                        <div class="question-navigator bg-white rounded-2xl shadow-lg p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                Questions
                            </h3>
                            
                            <!-- Timer (Optional) -->
                            <div class="mb-6 p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border-2 border-blue-200">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-blue-900">Time Elapsed</span>
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div id="timer" class="text-3xl font-bold text-blue-700 tabular-nums">00:00</div>
                            </div>
                            
                            <!-- Question Grid -->
                            <div class="grid grid-cols-5 gap-3 mb-6">
                                <?php foreach ($questions as $q_index => $question): ?>
                                    <button type="button" 
                                            class="nav-dot" 
                                            data-question-index="<?= $q_index ?>"
                                            data-question-id="<?= $question['id'] ?>"
                                            onclick="goToQuestion(<?= $q_index ?>)"
                                            title="Question <?= $q_index + 1 ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Legend -->
                            <div class="space-y-2 text-xs text-gray-600 border-t pt-4">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full bg-gray-300 mr-2"></div>
                                    <span>Not answered</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                                    <span>Answered</span>
                                </div>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="mt-6 p-4 bg-gray-50 rounded-xl">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-gray-800" id="nav-progress-count">0</div>
                                    <div class="text-xs text-gray-600">of <?= count($questions) ?> answered</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Assessment Content -->
                    <div class="lg:col-span-3 order-1 lg:order-2">
                        <div class="bg-white rounded-3xl card-shadow overflow-hidden">
                            <!-- Quiz Header -->
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between">
                                    <div>
                                        <h2 class="text-2xl font-bold text-white mb-1">Final Assessment</h2>
                                        <p class="text-white/90 text-sm" id="question-counter">Question 1 of <?= count($questions) ?></p>
                                    </div>
                                    <div class="flex space-x-2 mt-4 sm:mt-0">
                                        <div class="bg-white/20 rounded-lg px-4 py-2 text-center">
                                            <div class="text-white/80 font-semibold text-xs">Pass Score</div>
                                            <div class="text-xl font-bold text-white">80%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                            <!-- Enhanced Progress Bar -->
                            <div class="px-8 pt-6 pb-4 border-b">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-sm font-semibold text-gray-700 flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                        Overall Progress
                                    </span>
                                    <span class="text-sm font-bold text-blue-600">
                                        <span id="progress-count">0</span> / <?= count($questions) ?>
                                        <span class="text-xs text-gray-500 ml-1">(<span id="progress-percentage">0</span>%)</span>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-4 progress-bar-wrapper shadow-inner">
                                    <div id="progress-bar" class="bg-gradient-to-r from-blue-500 via-blue-600 to-blue-700 h-4 rounded-full transition-all duration-500 ease-out relative overflow-hidden" style="width: 0%">
                                        <div class="progress-bar-shimmer"></div>
                                    </div>
                                </div>
                            </div>
                        
                            <form id="assessment-form" method="POST" action="final_assessment.php" class="p-8">
                                <!-- Single Question Display -->
                                <div id="questions-container">
                                    <?php foreach ($questions as $q_index => $question): ?>
                                        <div class="question-slide" 
                                             data-question-id="<?= $question['id'] ?>"
                                             data-question-index="<?= $q_index ?>"
                                             id="question-<?= $q_index ?>"
                                             style="display: <?= $q_index === 0 ? 'block' : 'none' ?>;">
                                            
                                            <!-- Question Card -->
                                            <div class="question-card bg-gradient-to-br from-gray-50 to-white border-2 border-gray-200 rounded-2xl p-8 shadow-sm min-h-[400px]">
                                                <!-- Question Header -->
                                                <div class="flex items-start space-x-4 mb-8">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow-lg">
                                                            <span class="text-white font-bold text-lg"><?= $q_index + 1 ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="flex items-center space-x-2 mb-3">
                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $question['question_type'] === 'single' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                                                                <?php if ($question['question_type'] === 'single'): ?>
                                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <circle cx="10" cy="10" r="8"></circle>
                                                                    </svg>
                                                                    Single Choice
                                                                <?php else: ?>
                                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <rect x="4" y="4" width="12" height="12" rx="2"></rect>
                                                                    </svg>
                                                                    Multiple Choice
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="text-xs text-gray-500 font-semibold bg-gray-100 px-2 py-1 rounded">5 points</span>
                                                        </div>
                                                        <h3 class="text-xl font-bold text-gray-900 leading-relaxed">
                                                            <?= htmlspecialchars($question['question_text']) ?>
                                                        </h3>
                                                    </div>
                                                </div>

                                                <!-- Answer Options -->
                                                <div class="space-y-3">
                                                    <?php if (!empty($options[$question['id']])): ?>
                                                        <?php foreach ($options[$question['id']] as $option_index => $option): ?>
                                                            <?php if ($question['question_type'] === 'single'): ?>
                                                                <!-- Single Choice - Full card clickable -->
                                                                <div class="option-card block p-5 rounded-xl bg-white hover:bg-gray-50 transition-all cursor-pointer" 
                                                                     data-question-type="single">
                                                                    <div class="flex items-center gap-6">
                                                                        <label class="contents cursor-pointer">
                                                                            <div class="flex-shrink-0 ml-4">
                                                                                <input type="radio" 
                                                                                       name="answers[<?= $question['id'] ?>]" 
                                                                                       value="<?= $option['id'] ?>" 
                                                                                       class="h-6 w-6 text-blue-600 border-gray-300 rounded-full focus:outline-none focus:ring-0"
                                                                                       onchange="updateProgress()">
                                                                            </div>
                                                                            <div class="flex-shrink-0">
                                                                                <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 border-2 border-gray-300 rounded-lg flex items-center justify-center text-base font-bold text-gray-700 shadow-sm">
                                                                                    <?= chr(65 + $option_index) ?>
                                                                                </div>
                                                                            </div>
                                                                            <span class="text-gray-800 font-medium flex-1 pr-12 text-base"><?= htmlspecialchars($option['option_text']) ?></span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <!-- Multiple Choice - Full card clickable -->
                                                                <div class="option-card block p-5 rounded-xl bg-white hover:bg-gray-50 transition-all cursor-pointer" 
                                                                     data-question-type="multiple">
                                                                    <div class="flex items-center gap-6">
                                                                        <label class="contents cursor-pointer">
                                                                            <div class="flex-shrink-0 ml-4">
                                                                                <input type="checkbox" 
                                                                                       name="answers[<?= $question['id'] ?>][]" 
                                                                                       value="<?= $option['id'] ?>" 
                                                                                       class="h-6 w-6 text-blue-600 border-gray-300 rounded cursor-pointer focus:outline-none focus:ring-0"
                                                                                       onchange="updateProgress()">
                                                                            </div>
                                                                            <div class="flex-shrink-0">
                                                                                <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 border-2 border-gray-300 rounded-lg flex items-center justify-center text-base font-bold text-gray-700 shadow-sm">
                                                                                    <?= chr(65 + $option_index) ?>
                                                                                </div>
                                                                            </div>
                                                                            <span class="text-gray-800 font-medium flex-1 pr-12 text-base"><?= htmlspecialchars($option['option_text']) ?></span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Navigation Buttons -->
                                            <div class="mt-8 flex items-center justify-between">
                                                <button type="button" 
                                                        onclick="previousQuestion()" 
                                                        id="prev-btn-<?= $q_index ?>"
                                                        class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                                        <?= $q_index === 0 ? 'disabled' : '' ?>>
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                    </svg>
                                                    Previous
                                                </button>

                                                <?php if ($q_index < count($questions) - 1): ?>
                                                    <button type="button" 
                                                            onclick="nextQuestion()" 
                                                            id="next-btn-<?= $q_index ?>"
                                                            class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-all shadow-lg hover:shadow-xl">
                                                        Next
                                                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                        </svg>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            onclick="showSubmitConfirmation()" 
                                                            id="submit-btn"
                                                            class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all shadow-xl hover:shadow-2xl disabled:opacity-50 disabled:cursor-not-allowed">
                                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        Submit Assessment
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Unexpected State - Redirect -->
            <div class="bg-white rounded-3xl card-shadow p-8 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gray-100 rounded-full mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Unexpected State</h2>
                <p class="text-xl text-gray-600 mb-8">Please return to the dashboard and try again.</p>
                <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200">
                    Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Debug: Check if script is loading
console.log('Script loaded!');
console.log('Can take assessment:', <?= json_encode($can_take_assessment) ?>);
console.log('Questions empty:', <?= json_encode(empty($questions)) ?>);

// Global variables - Always defined
let currentQuestionIndex = 0;
const totalQuestions = <?= !empty($questions) ? count($questions) : 0 ?>;
let startTime = null;
let timerInterval = null;

// Start Assessment Function - Always available
window.startAssessment = function() {
    console.log('Start Assessment clicked!');
    const introScreen = document.getElementById('intro-screen');
    const quizScreen = document.getElementById('quiz-screen');
    
    console.log('Intro screen:', introScreen);
    console.log('Quiz screen:', quizScreen);
    
    if (introScreen && quizScreen) {
        introScreen.style.display = 'none';
        quizScreen.style.display = 'block';
        
        // Start timer
        startTime = Date.now();
        if (typeof updateTimer === 'function') {
            timerInterval = setInterval(updateTimer, 1000);
        }
        
        // Show first question
        if (typeof showQuestion === 'function') {
            showQuestion(0);
        }
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        console.error('Could not find intro-screen or quiz-screen elements');
    }
};

<?php if ($can_take_assessment && !empty($questions)): ?>

// Update Timer
function updateTimer() {
    const timerElement = document.getElementById('timer');
    if (!timerElement || !startTime) return;
    
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    timerElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// Show specific question
function showQuestion(index) {
    // Hide all questions
    document.querySelectorAll('.question-slide').forEach(slide => {
        slide.style.display = 'none';
    });
    
    // Show the current question
    const questionSlide = document.getElementById('question-' + index);
    if (questionSlide) {
        questionSlide.style.display = 'block';
        currentQuestionIndex = index;
        
        // Update question counter
        document.getElementById('question-counter').textContent = `Question ${index + 1} of ${totalQuestions}`;
        
        // Update navigator active state
        updateNavigatorActive(index);
        
        // Scroll to top smoothly
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Next Question
window.nextQuestion = function() {
    if (currentQuestionIndex < totalQuestions - 1) {
        showQuestion(currentQuestionIndex + 1);
    }
};

// Previous Question
window.previousQuestion = function() {
    if (currentQuestionIndex > 0) {
        showQuestion(currentQuestionIndex - 1);
    }
};

// Go to specific question (from navigator)
window.goToQuestion = function(index) {
    showQuestion(index);
};

// Update navigator active dot
function updateNavigatorActive(index) {
    document.querySelectorAll('.nav-dot').forEach((dot, i) => {
        if (i === index) {
            dot.classList.add('active');
        } else {
            dot.classList.remove('active');
        }
    });
}

// Show submit confirmation
window.showSubmitConfirmation = function() {
    const answeredQuestions = countAnsweredQuestions();
    
    if (answeredQuestions < totalQuestions) {
        const unanswered = totalQuestions - answeredQuestions;
        if (!confirm(`You have ${unanswered} unanswered question(s).\n\nAre you sure you want to submit? Unanswered questions will be marked as incorrect.`)) {
            return;
        }
    }
    
    const confirmMessage = `Are you sure you want to submit your assessment?\n\nThis action cannot be undone.\n\nAnswered: ${answeredQuestions} of ${totalQuestions} questions`;
    
    if (confirm(confirmMessage)) {
        submitAssessment();
    }
};

// Submit assessment via AJAX
function submitAssessment() {
    showLoadingOverlay();
    
    const form = document.getElementById('assessment-form');
    
    // Collect all checked inputs
    const answers = {};
    const checkedInputs = form.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked');
    
    checkedInputs.forEach(input => {
        const name = input.name;
        const value = input.value;
        
        // Extract question ID from name like "answers[123]" or "answers[123][]"
        const match = name.match(/answers\[(\d+)\]/);
        if (match) {
            const questionId = match[1];
            
            // Check if it's a multiple choice question (has [] in name)
            if (name.includes('[]')) {
                // Multiple choice - collect as array
                if (!answers[questionId]) {
                    answers[questionId] = [];
                }
                answers[questionId].push(value);
            } else {
                // Single choice - single value
                answers[questionId] = value;
            }
        }
    });
    
    // Create FormData and append answers
    const formData = new FormData();
    for (const [questionId, answer] of Object.entries(answers)) {
        if (Array.isArray(answer)) {
            // Multiple choice - append each value
            answer.forEach(val => {
                formData.append(`answers[${questionId}][]`, val);
            });
        } else {
            // Single choice
            formData.append(`answers[${questionId}]`, answer);
        }
    }
    
    fetch('api/learning/submit_assessment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingOverlay();
        
        if (data.success) {
            // Show results modal instead of redirecting
            showResultsModal(data.score, data.total_score, data.status);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        hideLoadingOverlay();
        alert('An error occurred while submitting your assessment. Please try again.');
    });
}

// Show results modal
function showResultsModal(score, totalScore, status) {
    const isPassed = status === 'passed';
    const percentage = Math.round((score / totalScore) * 100);
    
    const modal = document.getElementById('results-modal');
    const scoreDisplay = document.getElementById('result-score');
    const statusBadge = document.getElementById('result-status');
    const statusMessage = document.getElementById('result-message');
    const modalIcon = document.getElementById('result-icon');
    const modalHeader = document.getElementById('result-header');
    
    // Update modal content
    scoreDisplay.textContent = percentage + '%';
    
    if (isPassed) {
        modalHeader.className = 'bg-gradient-to-r from-green-600 to-emerald-700 px-8 py-6 rounded-t-2xl';
        modalIcon.innerHTML = '<svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        statusBadge.innerHTML = '<span class="px-6 py-2 bg-green-100 text-green-800 text-xl font-bold rounded-full">PASSED</span>';
        statusMessage.innerHTML = '<p class="text-2xl font-bold text-gray-800 mb-2">🎉 Congratulations!</p><p class="text-gray-600">You have successfully passed the Security Awareness Assessment!</p>';
    } else {
        modalHeader.className = 'bg-gradient-to-r from-orange-500 to-red-600 px-8 py-6 rounded-t-2xl';
        modalIcon.innerHTML = '<svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        statusBadge.innerHTML = '<span class="px-6 py-2 bg-orange-100 text-orange-800 text-xl font-bold rounded-full">NOT PASSED</span>';
        statusMessage.innerHTML = '<p class="text-2xl font-bold text-gray-800 mb-2">Keep Learning!</p><p class="text-gray-600">You need 80% to pass. Review the modules and try again.</p>';
    }
    
    modal.classList.remove('hidden');
}

// Go back to dashboard
function goToDashboard() {
    window.location.href = 'dashboard.php';
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
    
    // Re-enable all inputs
    document.querySelectorAll('input, button').forEach(el => {
        el.disabled = false;
    });
}

// Count answered questions
function countAnsweredQuestions() {
    let count = 0;
    document.querySelectorAll('.question-slide').forEach(slide => {
        const inputs = slide.querySelectorAll('input[type="radio"], input[type="checkbox"]');
        const hasAnswer = Array.from(inputs).some(input => input.checked);
        if (hasAnswer) count++;
    });
    return count;
}

document.addEventListener('DOMContentLoaded', function() {
    // Enhanced option selection with visual feedback
    const progressBar = document.getElementById('progress-bar');
    const progressCount = document.getElementById('progress-count');
    const progressPercentage = document.getElementById('progress-percentage');
    const navProgressCount = document.getElementById('nav-progress-count');
    
    // Enhanced option click handling
    document.querySelectorAll('.option-card').forEach(option => {
        const questionType = option.getAttribute('data-question-type');
        
        // Add click handler for both single and multiple choice questions
        option.addEventListener('click', function(e) {
            // Don't trigger if clicking directly on input
            if (e.target.tagName === 'INPUT') return;
            
            const input = this.querySelector('input');
            const questionSlide = this.closest('.question-slide');
            
            if (input && input.type === 'radio') {
                // Single choice: Remove selected from all options in this question
                questionSlide.querySelectorAll('.option-card').forEach(opt => {
                    opt.classList.remove('selected');
                });
                input.checked = true;
                this.classList.add('selected');
                updateProgress();
            } else if (input && input.type === 'checkbox') {
                // Multiple choice: Toggle the checkbox
                input.checked = !input.checked;
                this.classList.toggle('selected', input.checked);
                updateProgress();
            }
        });
        
        // Handle input change for direct clicks on checkbox/radio
        const input = option.querySelector('input');
        if (input) {
            input.addEventListener('change', function() {
                const optionCard = this.closest('.option-card');
                const questionSlide = this.closest('.question-slide');
                
                if (this.type === 'radio') {
                    // Remove selected class from all radio options in this question
                    questionSlide.querySelectorAll('.option-card').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    optionCard.classList.add('selected');
                } else if (this.type === 'checkbox') {
                    // Toggle selected class for checkboxes
                    optionCard.classList.toggle('selected', this.checked);
                }
                
                updateProgress();
            });
        }
    });

    // Update progress function
    window.updateProgress = function() {
        const questions = document.querySelectorAll('.question-slide');
        let answeredQuestions = 0;
        
        questions.forEach((question, index) => {
            const inputs = question.querySelectorAll('input[type="radio"], input[type="checkbox"]');
            const hasAnswer = Array.from(inputs).some(input => input.checked);
            
            if (hasAnswer) {
                answeredQuestions++;
                
                // Update navigator dot
                const dot = document.querySelector(`.nav-dot[data-question-index="${index}"]`);
                if (dot) {
                    dot.classList.add('answered');
                }
            } else {
                // Update navigator dot
                const dot = document.querySelector(`.nav-dot[data-question-index="${index}"]`);
                if (dot) {
                    dot.classList.remove('answered');
                }
            }
        });
        
        const progressPercent = Math.round((answeredQuestions / totalQuestions) * 100);
        if (progressBar) progressBar.style.width = progressPercent + '%';
        if (progressCount) progressCount.textContent = answeredQuestions;
        if (progressPercentage) progressPercentage.textContent = progressPercent;
        if (navProgressCount) navProgressCount.textContent = answeredQuestions;
    };

    // Loading overlay
    window.showLoadingOverlay = function() {
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.className = 'loading-overlay active';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block">
                    <svg class="animate-spin h-16 w-16 text-white mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div class="text-white text-xl font-semibold">Submitting your assessment...</div>
                <div class="text-white/80 text-sm mt-2">Please wait, this may take a moment</div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        // Disable all inputs
        document.querySelectorAll('input, button').forEach(el => {
            el.disabled = true;
        });
    };

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Don't trigger if user is typing in an input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        // Navigate questions with arrow keys
        if (e.key === 'ArrowRight' && currentQuestionIndex < totalQuestions - 1) {
            e.preventDefault();
            nextQuestion();
        } else if (e.key === 'ArrowLeft' && currentQuestionIndex > 0) {
            e.preventDefault();
            previousQuestion();
        }
        
        // Number keys to select options (1-9)
        if (e.key >= '1' && e.key <= '9') {
            const optionIndex = parseInt(e.key) - 1;
            const currentSlide = document.getElementById('question-' + currentQuestionIndex);
            if (currentSlide) {
                const options = currentSlide.querySelectorAll('.option-card input');
                if (options[optionIndex]) {
                    options[optionIndex].click();
                }
            }
        }
    });

    // Initialize progress
    updateProgress();
});
<?php endif; // End of main assessment script condition ?>

<?php if ($show_results && $result_data['status'] === 'passed'): ?>
// Confetti animation for passed assessment
function createConfetti() {
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    const confettiCount = 100;
    
    for (let i = 0; i < confettiCount; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            confetti.style.animationDelay = (Math.random() * 0.5) + 's';
            document.body.appendChild(confetti);
            
            setTimeout(() => {
                confetti.remove();
            }, 4000);
        }, i * 30);
    }
}

// Trigger confetti on page load
window.addEventListener('load', () => {
    setTimeout(createConfetti, 500);
});
<?php endif; // End of confetti condition ?>
</script>

<!-- Results Modal -->
<div id="results-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-black bg-opacity-75" aria-hidden="true"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <!-- Modal Header -->
            <div id="result-header" class="bg-gradient-to-r from-green-600 to-emerald-700 px-8 py-6 rounded-t-2xl">
                <div class="flex flex-col items-center">
                    <div id="result-icon" class="mb-4">
                        <!-- Icon will be inserted by JavaScript -->
                    </div>
                    <div id="result-status" class="mb-2">
                        <!-- Status badge will be inserted by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="px-8 py-8 text-center">
                <div id="result-message" class="mb-6">
                    <!-- Message will be inserted by JavaScript -->
                </div>
                
                <div class="mb-6">
                    <div class="text-gray-600 text-sm mb-2">Your Score</div>
                    <div id="result-score" class="text-6xl font-bold text-blue-600">
                        <!-- Score will be inserted by JavaScript -->
                    </div>
                    <div class="text-gray-500 text-sm mt-2">(Passing score: 80%)</div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col gap-3">
                    <button onclick="goToDashboard()" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Back to Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>

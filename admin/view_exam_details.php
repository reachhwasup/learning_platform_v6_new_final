<?php
$page_title = 'Exam Details';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// Validate input - accept either assessment_id or user_id
$user_id = null;
if (isset($_GET['user_id']) && filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    $user_id = (int)$_GET['user_id'];
} elseif (isset($_GET['assessment_id']) && filter_var($_GET['assessment_id'], FILTER_VALIDATE_INT)) {
    // Legacy support - get user_id from assessment_id
    $assessment_id = (int)$_GET['assessment_id'];
    try {
        $sql_get_user = "SELECT user_id FROM final_assessments WHERE id = ?";
        $stmt_get_user = $pdo->prepare($sql_get_user);
        $stmt_get_user->execute([$assessment_id]);
        $user_data = $stmt_get_user->fetch();
        
        if ($user_data) {
            $user_id = $user_data['user_id'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user_id: " . $e->getMessage());
    }
}

if (!$user_id) {
    redirect('reports.php');
}

try {
    // Fetch user details and ALL their assessment attempts
    $sql_assessments = "SELECT 
                            u.first_name, u.last_name, u.staff_id,
                            fa.id as assessment_id,
                            fa.score, 
                            fa.status, 
                            fa.completed_at,
                            (SELECT COUNT(*) FROM final_assessments WHERE user_id = ? AND completed_at < fa.completed_at) + 1 as attempt_number
                        FROM final_assessments fa
                        JOIN users u ON fa.user_id = u.id
                        WHERE fa.user_id = ?
                        ORDER BY fa.completed_at ASC";
    $stmt_assessments = $pdo->prepare($sql_assessments);
    $stmt_assessments->execute([$user_id, $user_id]);
    $all_attempts = $stmt_assessments->fetchAll();

    if (empty($all_attempts)) {
        redirect('reports.php');
    }
    
    // Get user info from first attempt
    $user_info = $all_attempts[0];
    
    // For each attempt, fetch the detailed answers
    $attempts_with_details = [];
    foreach ($all_attempts as $attempt) {
        $aid = $attempt['assessment_id'];
        
        // Fetch all questions and user's answers for this specific assessment
        $sql_details = "SELECT 
                            q.id as question_id,
                            q.question_text, 
                            qo.id as option_id,
                            qo.option_text, 
                            qo.is_correct, 
                            (SELECT COUNT(*) FROM user_answers ua WHERE ua.assessment_id = ? AND ua.question_id = q.id AND ua.selected_option_id = qo.id) as was_selected
                        FROM questions q
                        JOIN question_options qo ON q.id = qo.question_id
                        WHERE q.id IN (SELECT DISTINCT question_id FROM user_answers WHERE assessment_id = ?)
                        ORDER BY q.id, qo.id";
        $stmt_details = $pdo->prepare($sql_details);
        $stmt_details->execute([$aid, $aid]);
        $details_raw = $stmt_details->fetchAll();

        // Group results by question ID for easier processing
        $details = [];
        foreach ($details_raw as $row) {
            $details[$row['question_id']]['question_text'] = $row['question_text'];
            $details[$row['question_id']]['options'][] = $row;
        }
        
        $attempts_with_details[] = [
            'attempt_info' => $attempt,
            'details' => $details
        ];
    }

} catch (PDOException $e) {
    error_log("View Exam Details Error: " . $e->getMessage());
    die("An error occurred while fetching exam details.");
}

$page_title = 'Exam Details for ' . htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
require_once 'includes/header.php';
?>
<div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/20 to-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-[1800px]">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <a href="reports.php" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-semibold transition-colors group">
                    <svg class="w-5 h-5 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Reports
                </a>
                <a href="../api/admin/export_user_details.php?user_id=<?= $user_id ?>" 
                   class="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export to Excel
                </a>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h3>
                                <p class="text-blue-100 text-xs mt-0.5">Staff ID: <?= htmlspecialchars($user_info['staff_id']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="text-right bg-white bg-opacity-20 px-4 py-2 rounded-lg backdrop-blur-sm">
                        <p class="text-blue-100 text-xs font-medium mb-0.5">Total Attempts</p>
                        <p class="text-3xl font-bold text-white"><?= count($all_attempts) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Attempts -->
        <?php foreach ($attempts_with_details as $index => $attempt_data): ?>
            <?php 
                $attempt = $attempt_data['attempt_info'];
                $details = $attempt_data['details'];
                $is_passed = $attempt['status'] === 'passed';
                $attempt_id = "attempt-{$index}";
                $is_first = ($index === 0);
            ?>
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-4">
                <!-- Attempt Header (Clickable) -->
                <button type="button" onclick="toggleAttempt('<?= $attempt_id ?>')" class="w-full bg-gradient-to-r <?= $is_passed ? 'from-green-600 to-emerald-700' : 'from-red-600 to-red-700' ?> px-6 py-4 hover:opacity-95 transition-opacity">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="flex items-center gap-3 text-left">
                            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm flex-shrink-0">
                                <?php if ($is_passed): ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-lg font-bold text-white">Attempt #<?= $attempt['attempt_number'] ?></h4>
                                    <span class="px-2.5 py-0.5 text-xs font-bold uppercase rounded-full <?= $is_passed ? 'bg-green-200 text-green-900' : 'bg-red-200 text-red-900' ?>">
                                        <?= ucfirst($attempt['status']) ?>
                                    </span>
                                    <svg id="<?= $attempt_id ?>-chevron" class="w-5 h-5 text-white transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div class="flex items-center gap-2 text-white text-opacity-90">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-xs font-medium"><?= date('M d, Y', strtotime($attempt['completed_at'])) ?> at <?= date('H:i', strtotime($attempt['completed_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white bg-opacity-20 px-3 py-1.5 rounded-lg backdrop-blur-sm">
                            <p class="text-white text-opacity-90 text-xs font-medium mb-0.5 text-center">Final Score</p>
                            <div class="flex items-baseline justify-center">
                                <p class="text-2xl font-bold text-white"><?= (int)$attempt['score'] ?></p>
                                <span class="text-sm text-white text-opacity-90 ml-1">%</span>
                            </div>
                        </div>
                    </div>
                </button>

                <!-- Questions and Answers (Collapsible) -->
                <div id="<?= $attempt_id ?>-content" class="px-8 py-8 hidden">
                    <?php if (empty($details)): ?>
                        <div class="text-center py-16">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-gray-500 text-lg">No detailed answers were recorded for this exam attempt.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-8">
                            <?php $question_number = 1; ?>
                            <?php foreach ($details as $question_id => $question_data): ?>
                                <div class="question-block border-t border-gray-200 pt-8 first:border-t-0 first:pt-0">
                                    <!-- Question Header -->
                                    <div class="flex items-start gap-4 mb-6">
                                        <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-lg flex items-center justify-center shadow-md">
                                            <span class="text-white font-bold text-lg"><?= $question_number++ ?></span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-semibold text-lg text-gray-900 leading-relaxed"><?= htmlspecialchars($question_data['question_text']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Answer Options -->
                                    <div class="ml-14 space-y-3">
                                        <?php foreach ($question_data['options'] as $option): ?>
                                            <?php
                                                $li_class = 'p-4 border-2 rounded-xl flex justify-between items-center transition-all duration-200 hover:shadow-md';
                                                $badges = '';
                                                $icon = '';

                                                // Determine highlighting and badges
                                                if ($option['is_correct'] && $option['was_selected']) {
                                                    // User selected the correct answer
                                                    $li_class .= ' bg-gradient-to-r from-green-50 to-emerald-50 border-green-500 shadow-sm';
                                                    $icon = '<svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                                                    $badges .= '<span class="inline-flex items-center gap-1 text-xs font-bold uppercase bg-green-600 text-white px-3 py-1.5 rounded-full shadow-sm"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>Correct</span>';
                                                } elseif ($option['is_correct'] && !$option['was_selected']) {
                                                    // Correct answer not selected by user
                                                    $li_class .= ' bg-green-50 border-green-400';
                                                    $icon = '<svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                                                    $badges .= '<span class="inline-flex items-center gap-1 text-xs font-bold uppercase bg-green-100 text-green-800 px-3 py-1.5 rounded-full">Correct Answer</span>';
                                                } elseif (!$option['is_correct'] && $option['was_selected']) {
                                                    // User selected wrong answer
                                                    $li_class .= ' bg-gradient-to-r from-red-50 to-pink-50 border-red-500 shadow-sm';
                                                    $icon = '<svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                                                    $badges .= '<span class="inline-flex items-center gap-1 text-xs font-bold uppercase bg-red-600 text-white px-3 py-1.5 rounded-full shadow-sm"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/></svg>Your Answer</span>';
                                                } else {
                                                    // Not selected, not correct
                                                    $li_class .= ' border-gray-200 bg-gray-50';
                                                    $icon = '<svg class="w-6 h-6 text-gray-300 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
                                                }
                                            ?>
                                            <div class="<?= $li_class ?>">
                                                <div class="flex items-center flex-1">
                                                    <?= $icon ?>
                                                    <span class="text-gray-800 font-medium"><?= htmlspecialchars($option['option_text']) ?></span>
                                                </div>
                                                <div class="flex gap-2"><?= $badges ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleAttempt(attemptId) {
    const content = document.getElementById(attemptId + '-content');
    const chevron = document.getElementById(attemptId + '-chevron');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        chevron.classList.add('rotate-180');
    } else {
        content.classList.add('hidden');
        chevron.classList.remove('rotate-180');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
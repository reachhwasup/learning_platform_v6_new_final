<?php
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// Validate user_id from URL
if (!isset($_GET['user_id']) || !filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    redirect('manage_users.php');
}
$user_id = (int)$_GET['user_id'];

try {
    // Fetch the specific user's details
    $stmt_user = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch();

    if (!$user) {
        // User not found, redirect back
        redirect('manage_users.php');
    }

    // Fetch all modules
    $stmt_modules = $pdo->query("SELECT id, title, module_order FROM modules ORDER BY module_order ASC");
    $all_modules = $stmt_modules->fetchAll();

    // Fetch the user's module progress
    $stmt_progress = $pdo->prepare("SELECT module_id, completed_at FROM user_progress WHERE user_id = ?");
    $stmt_progress->execute([$user_id]);
    $user_progress = $stmt_progress->fetchAll(PDO::FETCH_KEY_PAIR);

    // Fetch all final assessment attempts for this user, including start and end times
    $stmt_assessments = $pdo->prepare("SELECT id, score, status, completed_at, quiz_started_at, quiz_ended_at FROM final_assessments WHERE user_id = ? ORDER BY completed_at DESC");
    $stmt_assessments->execute([$user_id]);
    $assessment_history = $stmt_assessments->fetchAll();


} catch (PDOException $e) {
    error_log("View User Progress Error: " . $e->getMessage());
    die("An error occurred while fetching user progress.");
}

$page_title = 'Learning Progress for ' . escape($user['first_name'] . ' ' . $user['last_name']);
require_once 'includes/header.php';
?>

<div class="container mx-auto">
    <div class="mb-6">
        <a href="manage_users.php" class="text-primary hover:underline">&larr; Back to All Users</a>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6">
        <div class="border-b border-gray-200 pb-4 mb-6">
            <h3 class="text-2xl font-bold text-gray-900"><?= escape($user['first_name'] . ' ' . $user['last_name']) ?></h3>
            <p class="text-sm text-gray-500"><?= escape($user['email']) ?></p>
        </div>

        <!-- Module History Section -->
        <h4 class="text-xl font-semibold text-gray-800 mb-4">Module History</h4>
        <div class="space-y-4">
            <?php if (empty($all_modules)): ?>
                <p class="text-gray-500">There are no modules in the system yet.</p>
            <?php else: ?>
                <?php foreach ($all_modules as $module): ?>
                    <?php
                        $is_completed = array_key_exists($module['id'], $user_progress);
                        $completion_date = $is_completed ? date('M d, Y H:i', strtotime($user_progress[$module['id']])) : null;
                    ?>
                    <div class="flex items-center justify-between p-4 border rounded-lg <?= $is_completed ? 'bg-green-50' : 'bg-gray-50' ?>">
                        <div class="flex items-center">
                            <?php if ($is_completed): ?>
                                <span class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white mr-4">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </span>
                            <?php else: ?>
                                <span class="flex-shrink-0 w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-gray-500 mr-4">
                                     <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </span>
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold text-lg text-gray-800">Module <?= escape($module['module_order']) ?>: <?= escape($module['title']) ?></p>
                                <?php if ($is_completed): ?>
                                    <p class="text-sm text-green-700">Completed on: <?= $completion_date ?></p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500">Not yet completed</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if ($is_completed): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    Completed
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-700">
                                    Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Final Assessment History Section -->
        <div class="flex items-center justify-between mt-10 mb-4 pt-6 border-t border-gray-200">
            <h4 class="text-xl font-semibold text-gray-800">Final Assessment History</h4>
            <span class="text-sm font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Total Attempts: <?= count($assessment_history) ?></span>
        </div>
        <div class="bg-white rounded-lg overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase">Quiz Start</th>
                        <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase">Quiz End</th>
                        <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                        <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase">Score</th>
                        <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assessment_history)): ?>
                        <tr><td colspan="5" class="text-center py-10 text-gray-500">This user has not attempted the final assessment yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($assessment_history as $attempt): ?>
                            <tr>
                                <td class="px-5 py-5 border-b border-gray-200 text-sm"><?= $attempt['quiz_started_at'] ? date('M d, Y H:i:s', strtotime($attempt['quiz_started_at'])) : '—' ?></td>
                                <td class="px-5 py-5 border-b border-gray-200 text-sm"><?= $attempt['quiz_ended_at'] ? date('M d, Y H:i:s', strtotime($attempt['quiz_ended_at'])) : '—' ?></td>
                                <td class="px-5 py-5 border-b border-gray-200 text-sm">
                                    <?php if ($attempt['status'] === 'passed'): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">Passed</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 text-sm font-bold"><?= escape($attempt['score']) ?> Points</td>
                                <td class="px-5 py-5 border-b border-gray-200 text-sm">
                                    <a href="view_exam_details.php?assessment_id=<?= $attempt['id'] ?>" class="text-primary hover:underline">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

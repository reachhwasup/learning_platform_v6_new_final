<?php
// Start session and output buffering
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$page_title = 'Information Security Awareness Training';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Include database connection
require_once 'includes/db_connect.php';

// Initialize variables
$all_modules_with_status = [];
$in_progress_modules = [];
$error_message = null;

try {
    if (!isset($pdo) || !$pdo) {
        throw new Exception("Database connection not available");
    }

    // FIXED: Use separate queries approach (no complex subqueries with parameter issues)
    
    // 1. Get all modules with basic info
    $sql_modules = "
        SELECT m.id, m.title, m.description, m.module_order, v.thumbnail_path
        FROM modules m
        LEFT JOIN videos v ON m.id = v.module_id
        ORDER BY m.module_order ASC
    ";
    $stmt_modules = $pdo->prepare($sql_modules);
    $stmt_modules->execute();
    $modules = $stmt_modules->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get user progress (which modules have been watched)
    $sql_progress = "SELECT module_id FROM user_progress WHERE user_id = ?";
    $stmt_progress = $pdo->prepare($sql_progress);
    $stmt_progress->execute([$user_id]);
    $watched_modules = $stmt_progress->fetchAll(PDO::FETCH_COLUMN);

    // 3. Get modules that have quizzes
    $sql_quiz_modules = "SELECT DISTINCT module_id FROM questions WHERE module_id IS NOT NULL";
    $stmt_quiz_modules = $pdo->prepare($sql_quiz_modules);
    $stmt_quiz_modules->execute();
    $modules_with_quiz = $stmt_quiz_modules->fetchAll(PDO::FETCH_COLUMN);

    // 4. Get modules where user has taken quiz
    $sql_taken_quizzes = "
        SELECT DISTINCT q.module_id
        FROM questions q
        JOIN user_answers ua ON q.id = ua.question_id
        WHERE ua.user_id = ? AND q.module_id IS NOT NULL
    ";
    $stmt_taken_quizzes = $pdo->prepare($sql_taken_quizzes);
    $stmt_taken_quizzes->execute([$user_id]);
    $quiz_taken_modules = $stmt_taken_quizzes->fetchAll(PDO::FETCH_COLUMN);

    // Process modules to determine status and progress percentage
    $completed_module_ids = [];
    $all_modules = []; // Initialize the array
    
    foreach ($modules as $module) {
        $module_id = $module['id'];
        $has_watched_video = in_array($module_id, $watched_modules);
        $has_quiz = in_array($module_id, $modules_with_quiz);
        $has_taken_quiz = in_array($module_id, $quiz_taken_modules);
        
        // Add the flags to module data
        $module['has_watched_video'] = $has_watched_video;
        $module['has_quiz'] = $has_quiz;
        $module['has_taken_quiz'] = $has_taken_quiz;
        
        // A module is completed if video is watched AND (no quiz OR quiz is taken)
        if ($has_watched_video && (!$has_quiz || $has_taken_quiz)) {
            $completed_module_ids[] = $module_id;
        }
        
        $all_modules[] = $module;
    }

    // Now process each module for status and progress
    foreach ($all_modules as $index => $module) {
        $is_completed = in_array($module['id'], $completed_module_ids);
        
        // Check if this is the introduction module (first module, order 0)
        $module['is_introduction'] = ($module['module_order'] == 0);
        
        // Add display number - for introduction show as 1, but don't display "Module X:"
        // For others, adjust index to account for introduction being order 0
        if ($module['is_introduction']) {
            $module['display_number'] = 1;
        } else {
            // Count how many modules with order 0 exist before this one
            $intro_count = 0;
            foreach ($all_modules as $check_mod) {
                if ($check_mod['module_order'] == 0) {
                    $intro_count++;
                }
                if ($check_mod['id'] == $module['id']) {
                    break;
                }
            }
            $module['display_number'] = $index + 1 - $intro_count;
        }
        
        // A module is unlocked if it's the first one OR the previous module is completed
        $is_unlocked = ($index === 0 || ($index > 0 && in_array($all_modules[$index - 1]['id'], $completed_module_ids)));

        $status = 'locked';
        $progress_percentage = 0;

        if ($is_completed) {
            $status = 'completed';
            $progress_percentage = 100;
        } elseif ($is_unlocked) {
            if ($module['has_watched_video'] && $module['has_quiz'] && !$module['has_taken_quiz']) {
                // Video watched but quiz not taken
                $status = 'in_progress';
                $progress_percentage = 50;
            } elseif ($module['has_watched_video'] && !$module['has_quiz']) {
                // Video watched and no quiz required - should be completed
                $status = 'completed';
                $progress_percentage = 100;
                // Add to completed list if not already there
                if (!in_array($module['id'], $completed_module_ids)) {
                    $completed_module_ids[] = $module['id'];
                }
            } elseif ($module['has_watched_video']) {
                // Video watched but other conditions not met
                $status = 'in_progress';
                $progress_percentage = 50;
            } else {
                // Module unlocked but not started
                $status = 'in_progress';
                $progress_percentage = 0;
            }
        }
        
        $module['status'] = $status;
        $module['progress_percentage'] = $progress_percentage;
        $all_modules_with_status[] = $module;

        if ($status === 'in_progress') {
            $in_progress_modules[] = $module;
        }
    }

} catch (PDOException $e) {
    error_log("Dashboard PDO Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the dashboard. Please try again later.";
}

// Include header
require_once 'includes/header.php';

// End output buffering
ob_end_flush();
?>

<?php if ($error_message): ?>
<div class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="max-w-md w-full">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
require_once 'includes/footer.php';
exit;
endif; 
?>

<style>
/* Performance optimization for sidebar transitions */
.main-content * {
    will-change: auto;
}

/* Disable expensive transitions during sidebar animation */
body.transitioning .module-card,
body.transitioning .module-row {
    transition: none !important;
}
</style>

<!-- Welcome Banner Section -->
<div class="relative bg-cover bg-center h-56 sm:h-64 lg:h-72" style="background-image: url('assets/images/welcome_banner.png');">
    <div class="absolute inset-0 bg-black bg-opacity-60 flex flex-col items-center justify-center text-center p-6">
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-3 leading-tight">
            Information Security Awareness Training
        </h1>
        <p class="text-base sm:text-lg lg:text-xl text-gray-100 font-medium">
            Welcome back, <?= htmlspecialchars($_SESSION['user_first_name'] ?? 'User') ?>! 🎓
        </p>
        <p class="text-sm sm:text-base text-gray-200 mt-2 max-w-md mx-auto">
            Continue your learning journey and unlock your potential
        </p>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="p-4 sm:p-6">
    <div class="mx-auto px-2 sm:px-4 lg:px-6 -mt-12 sm:-mt-16 relative z-10 pb-12">
        
        <!-- Search Bar Section -->
        <div class="bg-white p-4 rounded-lg shadow-lg mb-8">
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="text" 
                       id="module-search" 
                       placeholder="Search modules by title..." 
                       class="block w-full rounded-md border-gray-300 py-3 pl-10 pr-3 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
        </div>

        <!-- In Progress Section -->
        <div class="mb-12">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                In Progress (<?= count($in_progress_modules) ?>)
            </h2>
            
            <?php if (empty($in_progress_modules)): ?>
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-8 rounded-lg shadow-md text-center border border-blue-200">
                    <svg class="mx-auto h-12 w-12 text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Ready to Start Learning?</h3>
                    <p class="text-gray-600 mb-4">You have no modules in progress. Start your security awareness journey below!</p>
                    <button onclick="document.getElementById('modules-container').scrollIntoView({behavior: 'smooth'})" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Browse All Modules
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($in_progress_modules as $module): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-500 hover:shadow-lg transition-shadow">
                            <h3 class="text-lg font-bold text-gray-800 mb-2">
                                <?php if ($module['is_introduction']): ?>
                                    <?= htmlspecialchars($module['title']) ?>
                                <?php else: ?>
                                    Module <?= htmlspecialchars($module['display_number']) ?>: <?= htmlspecialchars($module['title']) ?>
                                <?php endif; ?>
                            </h3>
                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between mb-1">
                                    <span class="text-xs font-medium text-gray-500">Progress</span>
                                    <span class="text-xs font-medium text-gray-500"><?= $module['progress_percentage'] ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: <?= $module['progress_percentage'] ?>%"></div>
                                </div>
                            </div>
                            <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" 
                               class="block w-full text-center bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                Continue Learning
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Courses Section -->
        <div id="modules-container">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl font-semibold text-gray-900 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    All Courses (<?= count($all_modules_with_status) ?>)
                </h2>
                
                <div class="flex space-x-2">
                    <button id="grid-view-btn" 
                            class="p-2 rounded-md bg-blue-600 text-white transition-colors" 
                            title="Grid View">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                    </button>
                    <button id="list-view-btn" 
                            class="p-2 rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300 transition-colors" 
                            title="List View">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Grid View Container -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="modules-grid-container">
                <?php foreach ($all_modules_with_status as $module): ?>
                    <?php 
                    if (!empty($module['thumbnail_path']) && file_exists('uploads/thumbnails/' . $module['thumbnail_path'])) {
                        $thumbnail_url = 'uploads/thumbnails/' . htmlspecialchars($module['thumbnail_path']);
                    } else {
                        $thumbnail_url = null;
                    }
                    ?>
                    <div class="bg-white shadow-lg rounded-lg overflow-hidden hover:shadow-xl transition-shadow module-card" 
                         data-title="<?= strtolower(htmlspecialchars($module['title'] ?? '')) ?>"
                         data-description="<?= strtolower(htmlspecialchars($module['description'] ?? '')) ?>">
                        
                        <?php if ($module['status'] !== 'locked'): ?>
                            <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" class="block">
                        <?php endif; ?>
                        
                        <div class="relative">
                            <?php if ($thumbnail_url): ?>
                                <img class="w-full h-48 object-cover" 
                                     src="<?= $thumbnail_url ?>" 
                                     alt="Module <?= htmlspecialchars($module['display_number']) ?> Thumbnail"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <?php endif; ?>
                            
                            <div class="w-full h-48 bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-2xl" 
                                 <?= $thumbnail_url ? 'style="display:none;"' : '' ?>>
                                <?php if ($module['is_introduction']): ?>
                                    Introduction
                                <?php else: ?>
                                    Module <?= htmlspecialchars($module['display_number']) ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($module['status'] === 'locked'): ?>
                                <div class="absolute inset-0 bg-black bg-opacity-70 flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-white mb-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-white text-sm font-medium text-center px-4">Complete previous modules</span>
                                </div>
                            <?php elseif ($module['status'] === 'completed'): ?>
                                <div class="absolute top-2 right-2 bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    COMPLETED
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($module['status'] !== 'locked'): ?>
                            </a>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">
                                <?php if ($module['is_introduction']): ?>
                                    <?= htmlspecialchars($module['title']) ?>
                                <?php else: ?>
                                    Module <?= htmlspecialchars($module['display_number']) ?>: <?= htmlspecialchars($module['title']) ?>
                                <?php endif; ?>
                            </h3>
                            
                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between mb-1">
                                    <span class="text-xs font-medium text-gray-500">Progress</span>
                                    <span class="text-xs font-medium text-gray-500"><?= $module['progress_percentage'] ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <?php
                                    $progress_color = 'bg-gray-200';
                                    if ($module['status'] === 'completed') {
                                        $progress_color = 'bg-green-500';
                                    } elseif ($module['status'] === 'in_progress') {
                                        $progress_color = 'bg-yellow-500';
                                    }
                                    ?>
                                    <div class="<?= $progress_color ?> h-2 rounded-full" style="width: <?= $module['progress_percentage'] ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($module['status'] === 'locked'): ?>
                                    <button disabled class="w-full bg-gray-300 text-gray-500 font-bold py-2 px-4 rounded-lg cursor-not-allowed">
                                        Locked
                                    </button>
                                <?php else: ?>
                                    <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" 
                                       class="block w-full text-center bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                        <?= $module['status'] === 'completed' ? 'Review Module' : 'Start Module' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- List View Container -->
            <div class="hidden space-y-4" id="modules-list-container">
                <?php foreach ($all_modules_with_status as $module): ?>
                    <div class="bg-white shadow-md rounded-lg p-4 flex items-center space-x-4 hover:shadow-lg transition-shadow module-row" 
                         data-title="<?= strtolower(htmlspecialchars($module['title'] ?? '')) ?>"
                         data-description="<?= strtolower(htmlspecialchars($module['description'] ?? '')) ?>">
                        
                        <div class="flex-shrink-0">
                            <?php if ($module['status'] === 'completed'): ?>
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            <?php elseif ($module['status'] === 'locked'): ?>
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            <?php else: ?>
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-grow">
                            <h3 class="text-lg font-semibold text-gray-800">
                                <?php if ($module['is_introduction']): ?>
                                    <?= htmlspecialchars($module['title']) ?>
                                <?php else: ?>
                                    Module <?= htmlspecialchars($module['display_number']) ?>: <?= htmlspecialchars($module['title']) ?>
                                <?php endif; ?>
                            </h3>
                             <!-- Progress Bar -->
                            <div class="mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <?php
                                    $progress_color = 'bg-gray-200';
                                    if ($module['status'] === 'completed') {
                                        $progress_color = 'bg-green-500';
                                    } elseif ($module['status'] === 'in_progress') {
                                        $progress_color = 'bg-yellow-500';
                                    }
                                    ?>
                                    <div class="<?= $progress_color ?> h-2.5 rounded-full" style="width: <?= $module['progress_percentage'] ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex-shrink-0 w-28 text-center">
                            <?php if ($module['status'] === 'completed'): ?>
                                <span class="font-medium text-green-600">Completed</span>
                            <?php elseif ($module['status'] === 'in_progress'): ?>
                                <span class="font-medium text-yellow-600"><?= $module['progress_percentage'] ?>%</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-shrink-0">
                            <?php if ($module['status'] === 'locked'): ?>
                                <button disabled class="bg-gray-200 text-gray-500 font-bold py-2 px-4 rounded-lg cursor-not-allowed">
                                    Locked
                                </button>
                            <?php else: ?>
                                <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" 
                                   class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                    <?= $module['status'] === 'completed' ? 'Review' : 'Start' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="no-results-message" class="hidden text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No modules found</h3>
                <p class="text-gray-500 mb-4">Try adjusting your search terms or browse all available modules.</p>
                <button onclick="clearSearch()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    Clear Search
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gridViewBtn = document.getElementById('grid-view-btn');
    const listViewBtn = document.getElementById('list-view-btn');
    const gridContainer = document.getElementById('modules-grid-container');
    const listContainer = document.getElementById('modules-list-container');
    const searchInput = document.getElementById('module-search');
    const noResultsMessage = document.getElementById('no-results-message');
    
    let searchTimeout;

    // View switching functionality
    function switchToGridView() {
        gridContainer.classList.remove('hidden');
        listContainer.classList.add('hidden');
        gridViewBtn.classList.add('bg-blue-600', 'text-white');
        gridViewBtn.classList.remove('bg-gray-200', 'text-gray-600');
        listViewBtn.classList.add('bg-gray-200', 'text-gray-600');
        listViewBtn.classList.remove('bg-blue-600', 'text-white');
    }

    function switchToListView() {
        listContainer.classList.remove('hidden');
        gridContainer.classList.add('hidden');
        listViewBtn.classList.add('bg-blue-600', 'text-white');
        listViewBtn.classList.remove('bg-gray-200', 'text-gray-600');
        gridViewBtn.classList.add('bg-gray-200', 'text-gray-600');
        gridViewBtn.classList.remove('bg-blue-600', 'text-white');
    }

    gridViewBtn.addEventListener('click', switchToGridView);
    listViewBtn.addEventListener('click', switchToListView);

    // Search functionality
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const moduleCards = document.querySelectorAll('.module-card');
        const moduleRows = document.querySelectorAll('.module-row');
        let visibleCountGrid = 0;
        let visibleCountList = 0;

        moduleCards.forEach(card => {
            const title = card.dataset.title || '';
            const description = card.dataset.description || '';
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                card.style.display = 'block';
                visibleCountGrid++;
            } else {
                card.style.display = 'none';
            }
        });

        moduleRows.forEach(row => {
            const title = row.dataset.title || '';
            const description = row.dataset.description || '';
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                row.style.display = 'flex';
                visibleCountList++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        const isGridActive = !gridContainer.classList.contains('hidden');
        const isListActive = !listContainer.classList.contains('hidden');
        
        if ((isGridActive && visibleCountGrid === 0) || (isListActive && visibleCountList === 0)) {
            noResultsMessage.classList.remove('hidden');
        } else {
            noResultsMessage.classList.add('hidden');
        }
    }

    // Clear search function
    window.clearSearch = function() {
        searchInput.value = '';
        performSearch();
        searchInput.focus();
    };

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
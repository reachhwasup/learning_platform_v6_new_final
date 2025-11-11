<?php
// 1. Authentication and Initialization
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

// 2. Input Validation
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    redirect('dashboard.php');
}
$module_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // 3. Fetch all modules to determine the correct order and previous/next module
    $all_modules_stmt = $pdo->query("SELECT id, module_order FROM modules ORDER BY module_order ASC");
    $all_modules = $all_modules_stmt->fetchAll();

    $current_module_index = -1;
    $next_module_id = null;
    $display_number = 1; // Initialize display number
    $is_introduction = false;

    foreach ($all_modules as $index => $mod) {
        if ($mod['id'] == $module_id) {
            $current_module_index = $index;
            
            // Check if this is introduction module
            $is_introduction = ($mod['module_order'] == 0);
            
            if ($is_introduction) {
                $display_number = 1;
            } else {
                // Count modules with order 0 before this one
                $intro_count = 0;
                foreach ($all_modules as $check_mod) {
                    if ($check_mod['module_order'] == 0) {
                        $intro_count++;
                    }
                    if ($check_mod['id'] == $module_id) {
                        break;
                    }
                }
                $display_number = $index + 1 - $intro_count;
            }
            
            if (isset($all_modules[$index + 1])) {
                $next_module_id = $all_modules[$index + 1]['id'];
            }
            break;
        }
    }

    if ($current_module_index === -1) {
        redirect('dashboard.php');
    }

    // 4. Fetch user's completed modules
    $stmt_progress = $pdo->prepare("SELECT module_id FROM user_progress WHERE user_id = :user_id");
    $stmt_progress->execute(['user_id' => $user_id]);
    $completed_modules = $stmt_progress->fetchAll(PDO::FETCH_COLUMN);

    // 5. Authorization Check
    $is_completed = in_array($module_id, $completed_modules);
    $is_locked = true;

    if ($current_module_index === 0) {
        $is_locked = false;
    } else {
        $previous_module_id = $all_modules[$current_module_index - 1]['id'];
        if (in_array($previous_module_id, $completed_modules)) {
            $is_locked = false;
        }
    }
    
    if ($is_completed) {
        $is_locked = false;
    }

    if ($is_locked) {
        redirect('dashboard.php');
    }

    // 6. Fetch module details
    $sql_module = "SELECT m.title, m.description, m.module_order, v.video_path 
                     FROM modules m
                     LEFT JOIN videos v ON m.id = v.module_id
                     WHERE m.id = :module_id";
    $stmt_module = $pdo->prepare($sql_module);
    $stmt_module->execute(['module_id' => $module_id]);
    $module = $stmt_module->fetch();

    // 7. Fetch 4 Random Quiz Questions
    $sql_questions = "SELECT q.id, q.question_text, q.question_type FROM questions q WHERE q.module_id = :module_id ORDER BY RAND() LIMIT 4";
    $stmt_questions = $pdo->prepare($sql_questions);
    $stmt_questions->execute(['module_id' => $module_id]);
    $questions = $stmt_questions->fetchAll();

    // 8. Fetch Randomized Options for each question
    $options = [];
    if (!empty($questions)) {
        $question_ids = array_column($questions, 'id');
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $sql_options = "SELECT id, question_id, option_text FROM question_options WHERE question_id IN ($placeholders) ORDER BY RAND()";
        $stmt_options = $pdo->prepare($sql_options);
        $stmt_options->execute($question_ids);
        $all_options_raw = $stmt_options->fetchAll();
        foreach($all_options_raw as $option) {
            $options[$option['question_id']][] = $option;
        }
    }
    
    // 9. Check if user has taken the quiz before
    $has_taken_quiz = false;
    if ($is_completed && !empty($questions)) {
        $quiz_check_sql = "SELECT COUNT(*) FROM user_answers WHERE user_id = :user_id AND question_id IN (SELECT id FROM questions WHERE module_id = :module_id)";
        $quiz_check_stmt = $pdo->prepare($quiz_check_sql);
        $quiz_check_stmt->execute(['user_id' => $user_id, 'module_id' => $module_id]);
        $answered_count = $quiz_check_stmt->fetchColumn();
        $has_taken_quiz = $answered_count > 0;
    }
    
    // Determine the next step URL
    $next_step_url = $next_module_id ? "view_module.php?id=$next_module_id" : 'final_assessment.php';
    $next_step_text = $next_module_id ? 'Next Module' : 'Final Assessment';

} catch (PDOException $e) {
    error_log("View Module Error: " . $e->getMessage());
    die("An error occurred while loading the module. Please try again later.");
}

// Set page title - show just title for introduction (module_order 0), otherwise "Module X: Title"
if ($is_introduction) {
    $page_title = htmlspecialchars($module['title']);
} else {
    $page_title = 'Module ' . htmlspecialchars($display_number) . ': ' . htmlspecialchars($module['title']);
}
require_once 'includes/header.php';
?>

<style>
    #video-player-container:fullscreen #custom-controls {
        opacity: 1;
    }
    
    /* Fixed 16:9 Aspect Ratio Video Player */
    .video-16-9-fixed {
        position: relative;
        width: 100%;
        max-width: 1280px; /* Maximum width for large screens */
        margin: 0 auto;
        background: #000;
    }
    
    /* 16:9 aspect ratio container */
    .video-16-9-fixed::before {
        content: "";
        display: block;
        padding-top: 56.25%; /* 16:9 Aspect Ratio (9/16 = 0.5625 = 56.25%) */
    }
    
    .video-16-9-fixed video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: contain; /* Keeps video aspect ratio, adds black bars if needed */
    }
</style>

<div class="bg-white shadow-md rounded-lg p-6">
    <!-- Header with Back and Next buttons -->
    <div class="flex justify-between items-center pb-4 mb-6 border-b">
        <a href="dashboard.php" class="inline-flex items-center text-gray-600 hover:text-gray-900 transition-colors font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Back
        </a>
        <div class="flex items-center space-x-3">
            <?php if ($is_completed && !empty($questions)): ?>
                <button id="header-take-quiz-btn" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                    <?= $has_taken_quiz ? 'Re-take Quiz' : 'Take Quiz' ?>
                </button>
            <?php endif; ?>
            <?php if ($is_completed): ?>
            <a href="<?= $next_step_url ?>" class="inline-flex items-center bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                <?= $next_step_text ?>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Module Title -->
    <h2 class="text-3xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($module['title']) ?></h2>
    <p class="text-gray-600 mb-6"><?= htmlspecialchars($module['description']) ?></p>

    <!-- Video Player Section -->
    <div id="video-container">
        <?php if (empty($module['video_path'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Video Not Found</p>
                <p>The video for this module has not been uploaded yet. Please contact an administrator.</p>
            </div>
        <?php else: ?>
            <div id="video-player-container" class="video-16-9-fixed relative rounded-lg overflow-hidden bg-black mx-auto mb-1">
                <video id="learning-video" data-module-id="<?= $module_id ?>">
                    <source src="uploads/videos/<?= htmlspecialchars($module['video_path']) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                
                <!-- Custom Controls -->
                <div id="custom-controls" class="absolute bottom-0 left-0 right-0 h-14 bg-black bg-opacity-60 text-white flex items-center px-4 opacity-0 transition-opacity duration-300 z-10">
                    <!-- Video player controls will be injected by JS if needed -->
                </div>
            </div>
            
            <div id="no-quiz-alert" class="hidden mt-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                <p class="font-bold">Module Complete!</p>
                <p>This module does not have a quiz. You can now proceed to the next step.</p>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Pre-Quiz Modal -->
<div id="pre-quiz-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <!-- FIX: Removed padding classes that prevented vertical centering -->
    <div class="flex items-center justify-center min-h-screen p-4 text-center">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button type="button" id="close-pre-quiz-modal" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.79 4 4s-1.79 4-4 4-4-1.79-4-4c0-1.167.353-2.24.954-3.102M12 15.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 17.5a4.5 4.5 0 100-9 4.5 4.5 0 000 9z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="pre-quiz-modal-title">Are you ready to take the quiz?</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="pre-quiz-modal-description">You have completed the video. You can now take the quiz to complete this module.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a href="<?= $next_step_url ?>" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 sm:ml-3 sm:w-auto sm:text-sm">
                    <?= $next_step_text ?>
                </a>
                <button type="button" id="pre-quiz-take-quiz-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">Take Quiz</button>
                <button type="button" id="pre-quiz-rewatch-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">Re-watch Video</button>
            </div>
        </div>
    </div>
</div>

<!-- Quiz Modal -->
<div id="quiz-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <!-- FIX: Removed padding classes that prevented vertical centering -->
    <div class="flex items-center justify-center min-h-screen p-4 text-center">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button type="button" id="close-quiz-modal-btn" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="quiz-form">
                <input type="hidden" name="module_id" value="<?= $module_id ?>">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-2xl leading-6 font-bold text-gray-900 text-center" id="quiz-modal-title">Module Test</h3>
                    <div class="mt-4" id="quiz-modal-body">
                        <?php if (!empty($questions)): ?>
                            <?php foreach ($questions as $q_index => $question): ?>
                                <div class="question-slide hidden" data-question-index="<?= $q_index ?>">
                                    <p class="font-semibold text-lg text-gray-800"><?= ($q_index + 1) . '. ' . htmlspecialchars($question['question_text']) ?></p>
                                    <div class="mt-4 space-y-2">
                                        <?php foreach ($options[$question['id']] as $option): ?>
                                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                                <input type="<?= $question['question_type'] === 'single' ? 'radio' : 'checkbox' ?>" 
                                                       name="answers[<?= $question['id'] ?>]<?= $question['question_type'] === 'multiple' ? '[]' : '' ?>" 
                                                       value="<?= $option['id'] ?>" 
                                                       class="h-5 w-5 text-primary focus:ring-primary border-gray-300">
                                                <span class="ml-3 text-gray-700"><?= htmlspecialchars($option['option_text']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-between items-center" id="quiz-modal-footer">
                    <div id="quiz-progress" class="text-sm text-gray-500"></div>
                    <div id="quiz-nav-buttons" class="flex items-center space-x-3">
                        <button type="button" id="quiz-prev-btn" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">Previous</button>
                        <button type="button" id="quiz-next-btn" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm">Next</button>
                        <button type="submit" id="quiz-submit-btn" class="hidden inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:text-sm">Submit Quiz</button>
                    </div>
                    <div id="quiz-result-buttons" class="hidden flex items-center space-x-3">
                        <button type="button" id="rewatch-btn" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">Re-watch Video</button>
                        <button type="button" id="retake-btn" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-500 text-base font-medium text-white hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:text-sm">Re-take Quiz</button>
                        <a href="<?= $next_step_url ?>" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm">
                            <?= $next_step_text ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('learning-video');
    const noQuizAlert = document.getElementById('no-quiz-alert');
    const preQuizModal = document.getElementById('pre-quiz-modal');
    const quizModal = document.getElementById('quiz-modal');
    const quizForm = document.getElementById('quiz-form');

    if (!video) return;

    // --- Video Player Controls ---
    const videoContainer = document.getElementById('video-player-container'); 
    const customControls = document.getElementById('custom-controls');
    
    let isModuleCompleted = <?php echo json_encode($is_completed); ?>;
    let hasTakenQuiz = <?php echo json_encode($has_taken_quiz); ?>;
    let seekHandler = null;
    let headerTakeQuizBtn = document.getElementById('header-take-quiz-btn');

    function setupVideoControls() {
        if (isModuleCompleted) {
            video.controls = true;
            customControls.style.display = 'none';
            if (seekHandler) {
                video.removeEventListener('timeupdate', seekHandler);
                seekHandler = null;
            }
        } else {
            video.controls = false;
            customControls.style.display = 'flex';
            customControls.innerHTML = `
                <button id="play-pause-btn" class="p-2 hover:bg-gray-700 rounded">
                    <svg id="play-icon" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                    <svg id="pause-icon" class="w-6 h-6 hidden" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>
                </button>
                <div class="flex items-center mx-2 group">
                    <button id="mute-btn" class="p-2 hover:bg-gray-700 rounded">
                        <svg id="volume-high-icon" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg>
                        <svg id="volume-off-icon" class="w-6 h-6 hidden" viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L7 9.01V11h1.73l4.01-4.01L12 4z"></path></svg>
                    </button>
                    <input id="volume-slider" type="range" min="0" max="1" step="0.1" value="1" class="w-0 group-hover:w-24 transition-all duration-300">
                </div>
                <div class="text-sm ml-2">
                    <span id="current-time">00:00</span> / <span id="duration">00:00</span>
                </div>
                <div class="flex-grow"></div>
                <button id="fullscreen-btn" class="p-2 hover:bg-gray-700 rounded">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"></path></svg>
                </button>
            `;
            
            const playPauseBtn = document.getElementById('play-pause-btn');
            const playIcon = document.getElementById('play-icon');
            const pauseIcon = document.getElementById('pause-icon');
            const muteBtn = document.getElementById('mute-btn');
            const volumeHighIcon = document.getElementById('volume-high-icon');
            const volumeOffIcon = document.getElementById('volume-off-icon');
            const volumeSlider = document.getElementById('volume-slider');
            const currentTimeEl = document.getElementById('current-time');
            const durationEl = document.getElementById('duration');
            const fullscreenBtn = document.getElementById('fullscreen-btn');

            videoContainer.addEventListener('mouseenter', () => { customControls.style.opacity = '1'; });
            videoContainer.addEventListener('mouseleave', () => { if (!video.paused) customControls.style.opacity = '0'; });
            const togglePlay = () => { video.paused ? video.play() : video.pause(); };
            playPauseBtn.addEventListener('click', togglePlay);
            video.addEventListener('click', togglePlay);
            video.addEventListener('play', () => { playIcon.classList.add('hidden'); pauseIcon.classList.remove('hidden'); });
            video.addEventListener('pause', () => { pauseIcon.classList.add('hidden'); playIcon.classList.remove('hidden'); customControls.style.opacity = '1'; });
            muteBtn.addEventListener('click', () => { video.muted = !video.muted; });
            video.addEventListener('volumechange', () => {
                volumeSlider.value = video.volume;
                if (video.muted || video.volume === 0) {
                    volumeHighIcon.classList.add('hidden');
                    volumeOffIcon.classList.remove('hidden');
                } else {
                    volumeOffIcon.classList.add('hidden');
                    volumeHighIcon.classList.remove('hidden');
                }
            });
            volumeSlider.addEventListener('input', (e) => { video.volume = e.target.value; video.muted = e.target.value == 0; });
            const formatTime = (timeInSeconds) => { const result = new Date(timeInSeconds * 1000).toISOString().substr(14, 5); return result; };
            video.addEventListener('loadedmetadata', () => { if(video.duration) durationEl.textContent = formatTime(video.duration); });
            video.addEventListener('timeupdate', () => { currentTimeEl.textContent = formatTime(video.currentTime); });
            fullscreenBtn.addEventListener('click', () => {
                if (document.fullscreenElement) { document.exitFullscreen(); } 
                else { videoContainer.requestFullscreen().catch(err => alert(`Error: ${err.message}`)); }
            });

            const createSeekHandler = () => {
                let lastPlayedTime = 0;
                return () => {
                    if (!video.seeking && (video.currentTime > lastPlayedTime + 1.5)) {
                        video.currentTime = lastPlayedTime;
                    }
                    lastPlayedTime = video.currentTime;
                };
            };
            seekHandler = createSeekHandler();
            video.addEventListener('timeupdate', seekHandler);
        }
    }

    setupVideoControls();

    // --- Completion Logic ---
    let progressTracked = false;
    video.addEventListener('ended', () => {
        const wasAlreadyCompleted = isModuleCompleted;
        if (!progressTracked && !wasAlreadyCompleted) {
             const moduleId = video.dataset.moduleId;
             if (moduleId) {
                 fetch('api/learning/track_progress.php', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json' },
                     body: JSON.stringify({ module_id: moduleId })
                 })
                 .then(res => res.json())
                 .then(data => { 
                     if (data.success) {
                         progressTracked = true;
                         isModuleCompleted = true;
                         setupVideoControls();
                         
                         const hasQuiz = <?php echo json_encode(!empty($questions)); ?>;

                         const headerButtonsContainer = document.querySelector('.flex.justify-between.items-center.pb-4 .flex.items-center.space-x-4');
                         if (headerButtonsContainer) {
                             headerButtonsContainer.innerHTML = ''; 

                             const nextStepUrl = '<?= $next_step_url ?>';
                             const nextStepText = '<?= $next_step_text ?>';

                             if (hasQuiz) {
                                 const newBtn = document.createElement('button');
                                 newBtn.id = 'header-take-quiz-btn';
                                 newBtn.className = 'bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors text-sm';
                                 newBtn.textContent = 'Take Quiz';
                                 newBtn.addEventListener('click', startQuiz);
                                 headerButtonsContainer.appendChild(newBtn);
                                 headerTakeQuizBtn = newBtn;
                             }

                             const nextStepLink = document.createElement('a');
                             nextStepLink.href = nextStepUrl;
                             nextStepLink.className = 'text-primary hover:text-primary-dark transition-colors flex items-center text-sm font-semibold';
                             nextStepLink.innerHTML = `
                                 ${nextStepText}
                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                     <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                 </svg>
                             `;
                             headerButtonsContainer.appendChild(nextStepLink);
                         }

                         if (hasQuiz) {
                             showPreQuizModal();
                         } else {
                             noQuizAlert.classList.remove('hidden');
                         }
                     }
                 });
             }
        } else {
            const hasQuiz = <?php echo json_encode(!empty($questions)); ?>;
            if (hasQuiz) {
                showPreQuizModal();
            } else {
                noQuizAlert.classList.remove('hidden');
            }
        }
    });

    // --- Quiz Logic ---
    if (quizForm) {
        const questions = quizForm.querySelectorAll('.question-slide');
        const nextBtn = document.getElementById('quiz-next-btn');
        const prevBtn = document.getElementById('quiz-prev-btn');
        const submitBtn = document.getElementById('quiz-submit-btn');
        const progressText = document.getElementById('quiz-progress');
        const modalBody = document.getElementById('quiz-modal-body');
        const modalTitle = document.getElementById('quiz-modal-title');
        const quizNavButtons = document.getElementById('quiz-nav-buttons');
        const quizResultButtons = document.getElementById('quiz-result-buttons');
        const preQuizTakeBtn = document.getElementById('pre-quiz-take-quiz-btn');
        const preQuizRewatchBtn = document.getElementById('pre-quiz-rewatch-btn');
        const closePreQuizModalBtn = document.getElementById('close-pre-quiz-modal');
        const closeQuizModalBtn = document.getElementById('close-quiz-modal-btn');
        let currentQuestion = 0;

        function showQuestion(index) {
            questions.forEach((q, i) => q.classList.toggle('hidden', i !== index));
            progressText.textContent = `Question ${index + 1} of ${questions.length}`;
            prevBtn.style.display = index === 0 ? 'none' : 'inline-flex';
            nextBtn.style.display = index === questions.length - 1 ? 'none' : 'inline-flex';
            submitBtn.style.display = index === questions.length - 1 ? 'inline-flex' : 'none';
        }

        function startQuiz() {
            currentQuestion = 0;
            modalTitle.textContent = 'Module Test';
            
            modalBody.innerHTML = '';
            
            questions.forEach(q => modalBody.appendChild(q));
            
            quizForm.reset();

            quizNavButtons.style.display = 'flex';
            quizNavButtons.classList.remove('hidden');
            quizResultButtons.style.display = 'none';
            quizResultButtons.classList.add('hidden');
            showQuestion(0);
            quizModal.classList.remove('hidden');
        }

        function showPreQuizModal() {
            preQuizTakeBtn.textContent = hasTakenQuiz ? 'Re-take Quiz' : 'Take Quiz';
            preQuizModal.classList.remove('hidden');
        }

        if (headerTakeQuizBtn) {
            headerTakeQuizBtn.addEventListener('click', () => {
                const hasQuiz = <?php echo json_encode(!empty($questions)); ?>;
                if (hasQuiz) {
                    startQuiz();
                }
            });
        }

        preQuizTakeBtn.addEventListener('click', () => {
            preQuizModal.classList.add('hidden');
            startQuiz();
        });

        preQuizRewatchBtn.addEventListener('click', () => {
            preQuizModal.classList.add('hidden');
            video.currentTime = 0;
            video.play();
        });
        
        closePreQuizModalBtn.addEventListener('click', () => {
            preQuizModal.classList.add('hidden');
        });

        if (closeQuizModalBtn) {
            closeQuizModalBtn.addEventListener('click', () => {
                quizModal.classList.add('hidden');
            });
        }

        nextBtn.addEventListener('click', () => {
            if (currentQuestion < questions.length - 1) {
                currentQuestion++;
                showQuestion(currentQuestion);
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentQuestion > 0) {
                currentQuestion--;
                showQuestion(currentQuestion);
            }
        });

        quizForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            
            fetch('api/learning/submit_quiz.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    hasTakenQuiz = true; 
                    
                    if (headerTakeQuizBtn) {
                        headerTakeQuizBtn.textContent = 'Re-take Quiz';
                    }

                    modalTitle.textContent = 'Result';
                    modalBody.innerHTML = `
                        <div class="text-center p-8">
                            <h3 class="text-2xl font-bold text-gray-800">Quiz Completed!</h3>
                            <p class="mt-4 text-lg">Your answers are corrected <span class="font-bold text-green-600">${data.score ?? 0}</span> of ${questions.length} questions.</p>
                        </div>
                    `;
                    quizNavButtons.style.display = 'none';
                    quizResultButtons.style.display = 'flex';
                    quizResultButtons.classList.remove('hidden');
                } else {
                    alert(data.message || 'Failed to submit quiz.');
                }
            })
            .catch(error => {
                console.error('Quiz submission error:', error);
                alert('A server error occurred.');
            });
        });

        document.getElementById('retake-btn').addEventListener('click', startQuiz);
        
        document.getElementById('rewatch-btn').addEventListener('click', () => {
            quizModal.classList.add('hidden');
            video.currentTime = 0;
            video.play();
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

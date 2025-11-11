<?php
$page_title = 'Manage Questions';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// --- Filter Logic ---
$filter = $_GET['filter'] ?? 'all';
$where_clause = '';
$params = [];

if ($filter === 'final_exam') {
    $where_clause = 'WHERE q.is_final_exam_question = 1';
} elseif (str_starts_with($filter, 'module_')) {
    $module_id = (int)substr($filter, 7);
    if ($module_id > 0) {
        $where_clause = 'WHERE q.module_id = :module_id AND q.is_final_exam_question = 0';
        $params[':module_id'] = $module_id;
    }
}

// --- Pagination Logic ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

try {
    // Fetch modules for dropdowns
    $modules = $pdo->query("SELECT id, title, module_order FROM modules ORDER BY module_order ASC")->fetchAll();

    // Get total number of questions for pagination based on filter
    $total_records_sql = "SELECT COUNT(*) FROM questions q " . $where_clause;
    $total_records_stmt = $pdo->prepare($total_records_sql);
    $total_records_stmt->execute($params);
    $total_records = $total_records_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch questions for the current page based on filter
    $sql = "SELECT q.id, q.question_text, q.question_type, q.is_final_exam_question, m.title as module_title 
            FROM questions q 
            LEFT JOIN modules m ON q.module_id = m.id 
            {$where_clause}
            ORDER BY m.module_order, q.id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    // Bind parameters from filter and pagination
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $questions = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Manage Questions Error: " . $e->getMessage());
    $modules = [];
    $questions = [];
    $total_pages = 0;
}

require_once 'includes/header.php';
?>

<div class="max-w-[2000px] mx-auto px-4 sm:px-6 py-6">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Questions</h1>
        <p class="text-gray-600">Create, edit, and organize quiz questions for modules and final exam</p>
    </div>

    <!-- Action Bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Section: Action Buttons -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <button id="add-question-btn" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-xl shadow-md transition-all duration-200 hover:shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add New Question
                    </button>
                    <button id="delete-all-questions-btn" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-5 rounded-xl shadow-md transition-all duration-200 hover:shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete All Questions
                    </button>
                </div>
            </div>

            <!-- Right Section: Import -->
            <div class="border-l border-gray-200 pl-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Import Questions
                </h3>
                <form id="import-form" enctype="multipart/form-data" class="space-y-3">
                    <div class="flex gap-3">
                        <input type="file" name="import_file" required accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" 
                               class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-5 rounded-lg shadow-md transition-colors">
                            Import
                        </button>
                    </div>
                    <div id="import-feedback" class="text-sm"></div>
                    <a href="../assets/templates/question_template.csv" download class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download CSV Template
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
        <form method="GET" class="flex items-center gap-4">
            <label for="filter" class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filter by:
            </label>
            <select name="filter" id="filter" onchange="this.form.submit()" class="flex-1 max-w-md rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Questions</option>
                <option value="final_exam" <?= $filter === 'final_exam' ? 'selected' : '' ?>>Final Exam Questions</option>
                <optgroup label="Module Questions">
                    <?php foreach ($modules as $module): ?>
                        <option value="module_<?= $module['id'] ?>" <?= $filter === 'module_' . $module['id'] ? 'selected' : '' ?>>
                            Module <?= $module['module_order'] ?>: <?= escape($module['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
            <span class="text-sm text-gray-500">Showing <strong><?= count($questions) ?></strong> of <strong><?= $total_records ?></strong> questions</span>
        </form>
    </div>

    <!-- Questions Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No.</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Question</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Associated With</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($questions)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-gray-500 text-lg font-medium">No questions found for this filter</p>
                                    <p class="text-gray-400 text-sm mt-1">Try selecting a different filter or add a new question</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($questions as $index => $q): ?>
                            <tr id="question-row-<?= $q['id'] ?>" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <div class="flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-lg font-semibold">
                                        <?= $offset + $index + 1 ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <p class="line-clamp-2"><?= escape($q['question_text']) ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($q['question_type'] === 'single'): ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <circle cx="10" cy="10" r="8"></circle>
                                            </svg>
                                            Single Choice
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <rect x="4" y="4" width="12" height="12" rx="2"></rect>
                                            </svg>
                                            Multiple Choice
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($q['is_final_exam_question']): ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gradient-to-r from-purple-100 to-pink-100 text-purple-800 border border-purple-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                            </svg>
                                            Final Exam
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 border border-blue-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                            </svg>
                                            <?= escape($q['module_title'] ?? 'N/A') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="editQuestion(<?= $q['id'] ?>)" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="deleteQuestion(<?= $q['id'] ?>)" class="inline-flex items-center gap-1 text-red-600 hover:text-red-800 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
        <div class="py-8 flex justify-center">
            <nav class="flex items-center gap-2">
                <a href="?filter=<?= $filter ?>&page=<?= max(1, $current_page - 1) ?>" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors <?= $current_page == 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Previous
                </a>
                
                <div class="flex gap-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?filter=<?= $filter ?>&page=<?= $i ?>" 
                           class="px-4 py-2 text-sm font-medium rounded-lg transition-colors <?= $i == $current_page ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <a href="?filter=<?= $filter ?>&page=<?= min($total_pages, $current_page + 1) ?>" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors <?= $current_page == $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    Next
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Question Modal -->
<div id="question-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="document.getElementById('question-modal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl transform transition-all sm:max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <form id="question-form">
                <!-- Modal Header -->
                <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white" id="question-modal-title">Add Question</h3>
                        <button type="button" onclick="document.getElementById('question-modal').classList.add('hidden')" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="px-6 py-6 space-y-5">
                    <input type="hidden" name="question_id" id="question_id">
                    <input type="hidden" name="action" id="question-form-action">

                    <!-- Association Type -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Association Type</label>
                        <select name="association_type" id="association_type" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="module">Module Quiz</option>
                            <option value="final_exam">Final Exam</option>
                        </select>
                    </div>

                    <!-- Module Selection -->
                    <div id="module-select-container">
                        <label for="module_id" class="block text-sm font-semibold text-gray-700 mb-2">Select Module</label>
                        <select name="module_id" id="module_id" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Choose a module</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?= $module['id'] ?>">Module <?= $module['module_order'] ?>: <?= escape($module['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Question Text -->
                    <div>
                        <label for="question_text" class="block text-sm font-semibold text-gray-700 mb-2">Question Text</label>
                        <textarea name="question_text" id="question_text" rows="4" required 
                                  class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                  placeholder="Enter your question here..."></textarea>
                    </div>

                    <!-- Question Type -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Question Type</label>
                        <select name="question_type" id="question_type" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="single">Single Answer</option>
                            <option value="multiple">Multiple Answers</option>
                        </select>
                    </div>
                    
                    <!-- Answer Options -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Answer Options</label>
                        <div id="options-container" class="space-y-3">
                            <!-- Options will be dynamically added here by JavaScript -->
                        </div>
                        <button type="button" id="add-option-btn" 
                                class="mt-3 inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Another Option
                        </button>
                    </div>
                </div>

                <!-- Feedback Message -->
                <div id="question-form-feedback" class="px-6 text-sm"></div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex flex-row-reverse gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-md transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Question
                    </button>
                    <button type="button" id="question-cancel-btn" class="px-6 py-2.5 bg-white hover:bg-gray-100 text-gray-700 font-semibold rounded-xl border border-gray-300 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="document.getElementById('delete-modal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl transform transition-all sm:max-w-lg w-full">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 rounded-t-2xl">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h3 id="delete-modal-title" class="text-xl font-bold text-white"><i class="fas fa-exclamation-triangle mr-2"></i>Confirm Deletion</h3>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-6">
                <p id="delete-modal-message" class="text-gray-600 text-base mb-2">Are you sure you want to delete this question?</p>
                <p class="text-gray-500 text-sm">This action cannot be undone. The question and all its options will be permanently removed.</p>
                <input type="hidden" id="delete_question_id" value="">
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex flex-row-reverse gap-3">
                <button type="button" id="confirm-delete-btn" class="inline-flex items-center gap-2 px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl shadow-md transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Question
                </button>
                <button type="button" id="cancel-delete-btn" class="px-6 py-2.5 bg-white hover:bg-gray-100 text-gray-700 font-semibold rounded-xl border border-gray-300 transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
<?php require_once 'includes/footer.php'; ?>

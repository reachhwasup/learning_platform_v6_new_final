<?php
$page_title = 'Manage Modules';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// Fetch all modules to display in the table
try {
    $stmt = $pdo->query("SELECT id, title, description, module_order FROM modules ORDER BY module_order ASC");
    $modules = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Manage Modules Error: " . $e->getMessage());
    $modules = []; // Prevent page crash on DB error
}

require_once 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50/50">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Learning Modules</h1>
                    <p class="text-gray-600 mt-1">Manage your security awareness training content</p>
                </div>
                <button id="add-module-btn" class="inline-flex items-center px-6 py-3 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200" style="background: linear-gradient(135deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%);">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add New Module
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg border border-blue-400 p-6 text-white transform hover:scale-105 transition-all duration-200 hover:shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium mb-1">Total Modules</p>
                        <p class="text-4xl font-bold"><?= count($modules) ?></p>
                        <p class="text-blue-100 text-xs mt-2">Learning content items</p>
                    </div>
                    <div class="p-4 bg-white/20 backdrop-blur-sm rounded-2xl">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-lg border border-purple-400 p-6 text-white transform hover:scale-105 transition-all duration-200 hover:shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium mb-1">Active Modules</p>
                        <p class="text-4xl font-bold"><?= count($modules) ?></p>
                        <p class="text-purple-100 text-xs mt-2">Ready for training</p>
                    </div>
                    <div class="p-4 bg-white/20 backdrop-blur-sm rounded-2xl">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modules Content -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <?php if (empty($modules)): ?>
                <!-- Empty State -->
                <div class="text-center py-16">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No modules yet</h3>
                    <p class="text-gray-500 mb-6 max-w-md mx-auto">Get started by creating your first learning module to organize your security awareness content.</p>
                    <button id="add-module-btn-empty" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Create First Module
                    </button>
                </div>
            <?php else: ?>
                <!-- Desktop View -->
                <div class="hidden md:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Order</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Module</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="modules-table-body" class="divide-y divide-gray-200 bg-white">
                                <?php foreach ($modules as $module): ?>
                                    <tr id="module-row-<?= $module['id'] ?>" class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50 transition-all duration-200 group">
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            <div class="flex items-center justify-center w-10 h-10 text-white rounded-xl font-bold text-sm shadow-md group-hover:shadow-lg group-hover:scale-110 transition-all duration-200" style="background: linear-gradient(135deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%);">
                                                <?= escape($module['module_order']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <div class="flex items-center space-x-3">
                                                <div class="flex-shrink-0">
                                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors"><?= escape($module['title']) ?></h4>
                                                    <p class="text-xs text-gray-500 mt-0.5">Module <?= escape($module['module_order']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <p class="text-sm text-gray-600 max-w-md line-clamp-2 leading-relaxed"><?= escape($module['description']) ?></p>
                                        </td>
                                        <td class="px-6 py-5 text-right">
                                            <div class="flex items-center justify-end space-x-2">
                                                <button onclick="editModule(<?= $module['id'] ?>)" class="inline-flex items-center px-4 py-2 text-xs font-semibold text-blue-700 bg-blue-100 hover:bg-blue-600 hover:text-white rounded-lg transition-all duration-200 shadow-sm hover:shadow-md transform hover:-translate-y-0.5">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Edit
                                                </button>
                                                <button onclick="deleteModule(<?= $module['id'] ?>)" class="inline-flex items-center px-4 py-2 text-xs font-semibold text-red-700 bg-red-100 hover:bg-red-600 hover:text-white rounded-lg transition-all duration-200 shadow-sm hover:shadow-md transform hover:-translate-y-0.5">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile View -->
                <div class="md:hidden">
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($modules as $module): ?>
                            <div id="module-card-<?= $module['id'] ?>" class="p-6 hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50 transition-all duration-200 group">
                                <div class="flex items-start space-x-4 mb-4">
                                    <div class="flex-shrink-0">
                                        <div class="flex items-center justify-center w-12 h-12 text-white rounded-xl font-bold text-base shadow-md group-hover:shadow-lg group-hover:scale-110 transition-all duration-200" style="background: linear-gradient(135deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%);">
                                            <?= escape($module['module_order']) ?>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                                </svg>
                                            </div>
                                            <h4 class="text-base font-bold text-gray-900 group-hover:text-blue-600 transition-colors truncate"><?= escape($module['title']) ?></h4>
                                        </div>
                                        <p class="text-xs text-gray-500 mb-3">Module <?= escape($module['module_order']) ?></p>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mb-4 leading-relaxed line-clamp-3"><?= escape($module['description']) ?></p>
                                <div class="flex flex-wrap gap-2">
                                    <button onclick="editModule(<?= $module['id'] ?>)" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 text-xs font-semibold text-blue-700 bg-blue-100 hover:bg-blue-600 hover:text-white rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="deleteModule(<?= $module['id'] ?>)" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 text-xs font-semibold text-red-700 bg-red-100 hover:bg-red-600 hover:text-white rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Enhanced Add/Edit Module Modal -->
<div id="module-modal" class="fixed z-50 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <form id="module-form" enctype="multipart/form-data">
                <div class="bg-white px-6 pt-6 pb-4">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900" id="modal-title">Add New Module</h3>
                        <button type="button" id="close-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-6">
                        <input type="hidden" name="module_id" id="module_id">
                        <input type="hidden" name="action" id="form-action">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Module Title</label>
                                <input type="text" name="title" id="title" required 
                                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                                       placeholder="Enter module title">
                            </div>
                            <div>
                                <label for="module_order" class="block text-sm font-semibold text-gray-700 mb-2">Display Order</label>
                                <input type="number" name="module_order" id="module_order" required 
                                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                                       placeholder="1" min="0">
                                <p class="mt-2 text-xs text-gray-500">
                                    <svg class="w-4 h-4 inline-block mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <strong>Tip:</strong> Use <strong>0</strong> for Introduction module, then <strong>1, 2, 3...</strong> for other modules. Users will see "Module 1, 2, 3..." regardless of order value.
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                            <textarea name="description" id="description" rows="4" 
                                      class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all resize-none"
                                      placeholder="Describe what this module covers..."></textarea>
                        </div>

                        <!-- Video Upload Section -->
                        <div id="video-section" class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Module Video
                            </h4>

                            <div class="space-y-4">
                                <div>
                                    <label for="video_file" class="block text-sm font-semibold text-gray-700 mb-2">Video File <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="file" name="video_file" id="video_file" accept="video/mp4,video/webm,video/ogg" required
                                               class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                        <p class="mt-2 text-xs text-gray-500">
                                            Supported formats: MP4, WebM, OGG. Max size: 500MB
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label for="thumbnail_file" class="block text-sm font-semibold text-gray-700 mb-2">Video Thumbnail (Optional)</label>
                                    <div class="relative">
                                        <input type="file" name="thumbnail_file" id="thumbnail_file" accept="image/jpeg,image/jpg,image/png,image/webp"
                                               class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                        <p class="mt-2 text-xs text-gray-500">
                                            Supported formats: JPG, PNG, WebP. Recommended size: 1280x720px
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label for="video_duration" class="block text-sm font-semibold text-gray-700 mb-2">Video Duration (Optional)</label>
                                    <input type="text" name="video_duration" id="video_duration" 
                                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition-all"
                                           placeholder="e.g., 5:30 or 10:45">
                                    <p class="mt-2 text-xs text-gray-500">
                                        Format: MM:SS (e.g., 5:30 for 5 minutes 30 seconds)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="form-feedback" class="px-6 py-2 text-sm text-red-600 bg-red-50 border-l-4 border-red-400 hidden"></div>
                
                <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row sm:justify-end space-y-3 sm:space-y-0 sm:space-x-3">
                    <button type="button" id="cancel-btn" class="w-full sm:w-auto px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 font-medium transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="w-full sm:w-auto px-6 py-3 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200" style="background: linear-gradient(135deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%);">
                        <span class="flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Module
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="delete-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-6 pt-6 pb-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0 bg-red-100 rounded-full p-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2" id="delete-modal-title">Delete Module</h3>
                        <div class="text-sm text-gray-600 space-y-2">
                            <p class="font-medium">Are you sure you want to delete this module?</p>
                            <div class="bg-red-50 border-l-4 border-red-400 p-3 rounded">
                                <p class="text-red-800 font-medium">⚠️ Warning: This action cannot be undone!</p>
                                <ul class="mt-2 text-xs text-red-700 space-y-1">
                                    <li>• All associated videos will be deleted</li>
                                    <li>• All quiz questions will be removed</li>
                                    <li>• User progress data will be lost</li>
                                    <li>• Video files will be permanently deleted</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end space-y-3 space-y-reverse sm:space-y-0 sm:space-x-3">
                <button type="button" id="cancel-delete-btn" class="w-full sm:w-auto px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 font-medium transition-colors">
                    Cancel
                </button>
                <button type="button" id="confirm-delete-btn" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
                    <span class="flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Yes, Delete Module
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Animation for module rows */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}

/* Modal animations */
.modal-enter {
    opacity: 0;
    transform: scale(0.95);
}

.modal-enter-active {
    opacity: 1;
    transform: scale(1);
    transition: opacity 200ms, transform 200ms;
}

.modal-exit {
    opacity: 1;
    transform: scale(1);
}

.modal-exit-active {
    opacity: 0;
    transform: scale(0.95);
    transition: opacity 200ms, transform 200ms;
}
</style>

<script src="../assets/js/admin.js"></script>
<?php require_once 'includes/footer.php'; ?>
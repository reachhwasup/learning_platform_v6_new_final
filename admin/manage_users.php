<?php
$page_title = 'Manage Users';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// --- Filter & Pagination Logic ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$filter_dept = isset($_GET['department']) && $_GET['department'] !== '' ? (int)$_GET['department'] : null;
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'locked']) ? $_GET['status'] : null;
$search_term = isset($_GET['search']) && trim($_GET['search']) !== '' ? trim($_GET['search']) : null;
$user_type = isset($_GET['type']) && in_array($_GET['type'], ['user', 'admin']) ? $_GET['type'] : 'user';

try {
    $total_modules_stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $total_modules = $total_modules_stmt->fetchColumn();
    $total_modules = $total_modules > 0 ? $total_modules : 1;

    $where_clauses = [$user_type === 'admin' ? "u.role = 'admin'" : "u.role = 'user'"];
    $params = [];

    // Fixed: Use positional parameters instead of named parameters to avoid duplicate param issues
    if ($search_term) {
        $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.staff_id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
        $search_pattern = "%{$search_term}%";
        // We'll add these 5 times later when executing
    }

    if ($filter_dept !== null) {
        $where_clauses[] = "u.department_id = ?";
    }
    if ($filter_status) {
        $where_clauses[] = "u.status = ?";
    }
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);

    // Build params array in correct order
    $query_params = [];
    if ($search_term) {
        // Add search pattern 5 times for the 5 LIKE clauses
        $query_params[] = $search_pattern;
        $query_params[] = $search_pattern;
        $query_params[] = $search_pattern;
        $query_params[] = $search_pattern;
        $query_params[] = $search_pattern;
    }
    if ($filter_dept !== null) {
        $query_params[] = $filter_dept;
    }
    if ($filter_status) {
        $query_params[] = $filter_status;
    }

    // Debug logging
    error_log("Search SQL: SELECT COUNT(*) FROM users u " . $where_sql);
    error_log("Search params: " . print_r($query_params, true));

    $total_records_sql = "SELECT COUNT(*) FROM users u " . $where_sql;
    $total_records_stmt = $pdo->prepare($total_records_sql);
    $total_records_stmt->execute($query_params);
    $total_records = $total_records_stmt->fetchColumn();
    
    // Fix: Ensure $total_records is always defined
    $total_records = $total_records !== false ? (int)$total_records : 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 0;

    // Fetch users based on type
    if ($user_type === 'admin') {
        $sql_users = "SELECT 
                        u.id, u.first_name, u.last_name, u.username, u.staff_id, u.position, u.phone_number, u.gender, u.role, u.status, d.name as department_name
                      FROM users u 
                      LEFT JOIN departments d ON u.department_id = d.id 
                      {$where_sql}
                      ORDER BY u.first_name, u.last_name
                      LIMIT ? OFFSET ?";
    } else {
        $sql_users = "SELECT 
                        u.id, u.first_name, u.last_name, u.username, u.staff_id, u.position, u.phone_number, u.gender, u.role, u.status, d.name as department_name, 
                        (
                            SELECT COUNT(DISTINCT up.module_id)
                            FROM user_progress up
                            WHERE up.user_id = u.id AND (
                                (SELECT COUNT(*) FROM questions q WHERE q.module_id = up.module_id) = 0
                                OR
                                EXISTS (
                                    SELECT 1
                                    FROM user_answers ua
                                    JOIN questions q_ua ON ua.question_id = q_ua.id
                                    WHERE ua.user_id = u.id AND q_ua.module_id = up.module_id
                                )
                            )
                        ) as completed_modules
                      FROM users u 
                      LEFT JOIN departments d ON u.department_id = d.id 
                      {$where_sql}
                      ORDER BY u.first_name, u.last_name
                      LIMIT ? OFFSET ?";
    }
                  
    $stmt_users = $pdo->prepare($sql_users);
    
    // Bind all the filter params first, then LIMIT and OFFSET
    $bind_params = $query_params;
    $bind_params[] = $records_per_page;
    $bind_params[] = $offset;
    
    $stmt_users->execute($bind_params);
    $users = $stmt_users->fetchAll();

    // Debug result count (remove after testing)
    error_log("Search found " . count($users) . " users");
    
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    error_log("Manage Users Error: " . $e->getMessage());
    $users = [];
    $departments = [];
    $total_pages = 0;
}

require_once 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">User Management</h2>
            <p class="text-sm text-gray-600 mt-1">Manage system users and administrators</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button id="add-user-btn" class="inline-flex items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-sm transition-all duration-200 hover:shadow-md">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add User
            </button>
            <button id="bulk-upload-btn" class="inline-flex items-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-sm transition-all duration-200 hover:shadow-md">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Bulk Upload
            </button>
            <?php if ($user_type === 'user'): ?>
            <button id="delete-all-btn" class="inline-flex items-center px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg shadow-sm transition-all duration-200 hover:shadow-md">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete All Users
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="GET" action="manage_users.php">
            <!-- Hidden field to maintain user type -->
            <input type="hidden" name="type" value="<?= $user_type ?>">
            
            <div class="flex items-center gap-4 mb-4">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Filter & Search
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search User</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" name="search" id="search" class="pl-10 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all" placeholder="Name, Staff ID, Username..." value="<?= htmlspecialchars($search_term ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select name="department" id="department" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($filter_dept == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="filter_status" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($filter_status === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($filter_status === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        <option value="locked" <?= ($filter_status === 'locked') ? 'selected' : '' ?>>Locked</option>
                    </select>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex justify-center items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-sm transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filter
                    </button>
                    <a href="manage_users.php?type=<?= $user_type ?>" class="inline-flex justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear
                    </a>
                </div>
                <span class="text-sm text-gray-500">Showing <strong><?= count($users) ?></strong> of <strong><?= $total_records ?></strong> <?= $user_type === 'admin' ? 'administrators' : 'users' ?></span>
            </div>
        </form>
    </div>

    <!-- Search Results Info -->
    <?php if ($search_term || $filter_dept || $filter_status): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-blue-900">
                        Found <span class="font-bold"><?= isset($total_records) ? $total_records : 0 ?></span> <?= $user_type === 'admin' ? 'administrators' : 'users' ?>
                        <?php if ($search_term): ?>
                            matching "<span class="font-bold"><?= htmlspecialchars($search_term) ?></span>"
                        <?php endif; ?>
                        <?php if ($filter_dept && !empty($departments)): ?>
                            <?php 
                            $dept_name = 'Unknown';
                            foreach ($departments as $dept) {
                                if ($dept['id'] == $filter_dept) {
                                    $dept_name = $dept['name'];
                                    break;
                                }
                            }
                            ?>
                            in department "<span class="font-bold"><?= htmlspecialchars($dept_name) ?></span>"
                        <?php endif; ?>
                        <?php if ($filter_status): ?>
                            with status "<span class="font-bold"><?= htmlspecialchars(ucfirst($filter_status)) ?></span>"
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <?php if ($user_type === 'admin'): ?>
                        <svg class="w-6 h-6 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <h3 class="text-xl font-bold text-gray-900">Administrators</h3>
                    <?php else: ?>
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h3 class="text-xl font-bold text-gray-900">Normal Users</h3>
                    <?php endif; ?>
                </div>
                <span class="px-3 py-1 <?= $user_type === 'admin' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800' ?> text-sm font-semibold rounded-full">
                    <?= count($users) ?> <?= $user_type === 'admin' ? 'admins' : 'users' ?>
                </span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Staff ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Position</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Department</th>
                        <?php if ($user_type === 'user'): ?>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Progress</th>
                        <?php endif; ?>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="<?= $user_type === 'user' ? '9' : '8' ?>" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p class="mt-3 text-gray-500 font-medium">
                                    <?php if ($search_term || $filter_dept || $filter_status): ?>
                                        No <?= $user_type === 'admin' ? 'administrators' : 'users' ?> found matching your search criteria.
                                    <?php else: ?>
                                        No <?= $user_type === 'admin' ? 'administrators' : 'users' ?> found.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $index => $user): ?>
                            <?php 
                            if ($user_type === 'user') {
                                $progress_percentage = $total_modules > 0 ? round(($user['completed_modules'] / $total_modules) * 100) : 0;
                            }
                            ?>
                            <tr id="user-row-<?= $user['id'] ?>" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $offset + $index + 1 ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-r <?= $user_type === 'admin' ? 'from-orange-500 to-orange-600' : 'from-blue-500 to-blue-600' ?> flex items-center justify-center text-white font-bold">
                                                <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium"><?= htmlspecialchars($user['staff_id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($user['username']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($user['position'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></td>
                                <?php if ($user_type === 'user'): ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-24 bg-gray-200 rounded-full h-2.5 mr-2">
                                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2.5 rounded-full transition-all" style="width: <?= $progress_percentage ?>%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-700"><?= $progress_percentage ?>%</span>
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-green-800 bg-green-100">
                                            <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-1.5"></span>
                                            Active
                                        </span>
                                    <?php elseif ($user['status'] === 'inactive'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-gray-800 bg-gray-100">
                                            <span class="w-1.5 h-1.5 bg-gray-600 rounded-full mr-1.5"></span>
                                            Inactive
                                        </span>
                                    <?php elseif ($user['status'] === 'locked'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-red-800 bg-red-100">
                                            <span class="w-1.5 h-1.5 bg-red-600 rounded-full mr-1.5"></span>
                                            Locked
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($user['status'] === 'locked' && $user_type === 'user'): ?>
                                            <button onclick="unlockUser(<?= $user['id'] ?>)" class="inline-flex items-center px-2.5 py-1.5 bg-green-100 hover:bg-green-200 text-green-700 text-xs font-semibold rounded-lg transition-colors" title="Unlock User">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="editUser(<?= $user['id'] ?>)" class="inline-flex items-center px-2.5 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-semibold rounded-lg transition-colors" title="Edit">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <?php if ($user_type === 'admin' && $user['id'] == $_SESSION['user_id']): ?>
                                            <!-- Don't show reset/delete for current admin -->
                                        <?php else: ?>
                                            <button onclick="resetPassword(<?= $user['id'] ?>)" class="inline-flex items-center px-2.5 py-1.5 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 text-xs font-semibold rounded-lg transition-colors" title="Reset Password">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                                </svg>
                                            </button>
                                            <button onclick="deleteUser(<?= $user['id'] ?>)" class="inline-flex items-center px-2.5 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold rounded-lg transition-colors" title="Delete">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
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
                <?php 
                // Build pagination parameters preserving all filters
                $pagination_params_array = ['type' => $user_type];
                if ($filter_dept !== null) {
                    $pagination_params_array['department'] = $filter_dept;
                }
                if ($filter_status !== null && $filter_status !== '') {
                    $pagination_params_array['status'] = $filter_status;
                }
                if ($search_term !== null && $search_term !== '') {
                    $pagination_params_array['search'] = $search_term;
                }
                $pagination_params = http_build_query($pagination_params_array);
                $param_separator = !empty($pagination_params) ? '&' : '';
                
                // Smart pagination - show limited page numbers
                $max_visible_pages = 7;
                $start_page = max(1, $current_page - floor($max_visible_pages / 2));
                $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
                
                // Adjust start_page if we're near the end
                if ($end_page - $start_page < $max_visible_pages - 1) {
                    $start_page = max(1, $end_page - $max_visible_pages + 1);
                }
                ?>
                <a href="?page=<?= max(1, $current_page - 1) ?><?= $param_separator . $pagination_params ?>" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors <?= $current_page == 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Previous
                </a>
                
                <div class="flex gap-1">
                    <?php if ($start_page > 1): ?>
                        <a href="?page=1<?= $param_separator . $pagination_params ?>" 
                           class="px-4 py-2 text-sm font-medium rounded-lg transition-colors text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            1
                        </a>
                        <?php if ($start_page > 2): ?>
                            <span class="px-2 py-2 text-sm text-gray-500">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?= $i ?><?= $param_separator . $pagination_params ?>" 
                           class="px-4 py-2 text-sm font-medium rounded-lg transition-colors <?= $i == $current_page ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="px-2 py-2 text-sm text-gray-500">...</span>
                        <?php endif; ?>
                        <a href="?page=<?= $total_pages ?><?= $param_separator . $pagination_params ?>" 
                           class="px-4 py-2 text-sm font-medium rounded-lg transition-colors text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            <?= $total_pages ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <a href="?page=<?= min($total_pages, $current_page + 1) ?><?= $param_separator . $pagination_params ?>" 
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

<!-- Add/Edit User Modal -->
<div id="user-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('user-modal').classList.add('hidden')"></div>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full">
            <form id="user-form">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white" id="user-modal-title">Add New User</h3>
                        <button type="button" onclick="document.getElementById('user-modal').classList.add('hidden')" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="bg-white px-6 py-6">
                    <input type="hidden" name="user_id" id="user_id">
                    <input type="hidden" name="action" id="user-form-action">
                    
                    <div class="space-y-5">
                        <!-- Personal Information Section -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Personal Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                    <input name="first_name" id="first_name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Enter first name">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                    <input name="last_name" id="last_name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Enter last name">
                                </div>
                                <div>
                                    <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff ID <span class="text-red-500">*</span></label>
                                    <input name="staff_id" id="staff_id" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Enter staff ID">
                                </div>
                                <div>
                                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                    <select name="gender" id="gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Work Information Section -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Work Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                                    <input name="position" id="position" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Enter position">
                                </div>
                                <div>
                                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                                    <select name="department_id" id="department_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= htmlspecialchars($dept['id']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                    <input name="phone_number" id="phone_number" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Enter phone number">
                                </div>
                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                                    <select name="role" id="role" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Account Status Section -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                Account Status
                            </h4>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="user_status" class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                                    <select name="status" id="user_status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="locked">Locked</option>
                                    </select>
                                </div>
                                <div class="bg-blue-50 rounded-lg p-3">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div class="text-sm text-blue-800">
                                            <p class="font-medium mb-1">Default Credentials</p>
                                            <p class="text-xs">• Username will be auto-generated as <strong>firstname.lastname</strong></p>
                                            <p class="text-xs">• Default password: <strong>APD@123456789</strong></p>
                                            <p class="text-xs">• User will be required to change password on first login</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error/Success Message -->
                <div id="user-form-feedback" class="px-6 py-2 text-sm"></div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button" id="user-cancel-btn" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div id="bulk-upload-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('bulk-upload-modal').classList.add('hidden')"></div>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full">
            <form id="bulk-upload-form" enctype="multipart/form-data">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <h3 class="text-xl font-bold text-white">Bulk Upload Users</h3>
                        </div>
                        <button type="button" onclick="document.getElementById('bulk-upload-modal').classList.add('hidden')" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="bg-white px-6 py-6">
                    <div class="space-y-4">
                        <div>
                            <label for="user_file" class="block text-sm font-medium text-gray-700 mb-2">
                                Upload CSV or Excel File <span class="text-red-500">*</span>
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-green-400 transition-colors">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="user_file" class="relative cursor-pointer bg-white rounded-md font-medium text-green-600 hover:text-green-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-green-500">
                                            <span>Upload a file</span>
                                            <input id="user_file" name="user_file" type="file" class="sr-only" required accept=".csv, .xlsx">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">CSV or XLSX up to 10MB</p>
                                </div>
                            </div>
                            <p id="file-name" class="mt-2 text-sm text-gray-600 hidden"></p>
                        </div>

                        <!-- Instructions -->
                        <div class="bg-green-50 rounded-lg p-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="text-sm text-green-800">
                                    <p class="font-medium mb-2">File Requirements:</p>
                                    <ul class="text-xs space-y-1 list-disc list-inside">
                                        <li>Format: CSV or Excel (.xlsx)</li>
                                        <li>Required columns: <code class="bg-white px-1 rounded">staff_id</code>, <code class="bg-white px-1 rounded">first_name</code>, <code class="bg-white px-1 rounded">last_name</code>, <code class="bg-white px-1 rounded">gender</code>, <code class="bg-white px-1 rounded">position</code>, <code class="bg-white px-1 rounded">department_name</code>, <code class="bg-white px-1 rounded">phone_number</code></li>
                                        <li>Default password will be set to: <strong>APD@123456789</strong></li>
                                        <li>Usernames will be auto-generated as <strong>firstname.lastname</strong></li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="../templates/question_template.csv" class="inline-flex items-center text-xs font-medium text-green-700 hover:text-green-800 underline" download>
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            Download Template
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error/Success Message -->
                <div id="bulk-form-feedback" class="px-6 py-2 text-sm"></div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button" id="bulk-cancel-btn" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 border border-transparent rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Upload and Create Users
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Modal Handling ---
    const userModal = document.getElementById('user-modal');
    const bulkModal = document.getElementById('bulk-upload-modal');
    const addUserBtn = document.getElementById('add-user-btn');
    const bulkUploadBtn = document.getElementById('bulk-upload-btn');
    const userCancelBtn = document.getElementById('user-cancel-btn');
    const bulkCancelBtn = document.getElementById('bulk-cancel-btn');
    const userForm = document.getElementById('user-form');
    const bulkForm = document.getElementById('bulk-upload-form');
    const deleteAllBtn = document.getElementById('delete-all-btn');

    // Fix 7: Add real-time search functionality
    const searchInput = document.getElementById('search');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                // Auto-submit form after user stops typing for 500ms
                if (searchInput.value.length >= 2 || searchInput.value.length === 0) {
                    searchInput.closest('form').submit();
                }
            }, 500);
        });
    }

    if (addUserBtn) {
        addUserBtn.addEventListener('click', () => {
            userForm.reset();
            document.getElementById('user-modal-title').textContent = 'Add New User';
            document.getElementById('user-form-action').value = 'add';
            userModal.classList.remove('hidden');
        });
    }

    if (bulkUploadBtn) {
        bulkUploadBtn.addEventListener('click', () => {
            if (bulkForm) bulkForm.reset();
            const fileNameDisplay = document.getElementById('file-name');
            if (fileNameDisplay) fileNameDisplay.classList.add('hidden');
            bulkModal.classList.remove('hidden');
        });
    }

    // Show selected file name in bulk upload
    const userFileInput = document.getElementById('user_file');
    if (userFileInput) {
        userFileInput.addEventListener('change', function(e) {
            const fileNameDisplay = document.getElementById('file-name');
            if (this.files && this.files[0]) {
                fileNameDisplay.textContent = '📄 Selected: ' + this.files[0].name;
                fileNameDisplay.classList.remove('hidden');
            } else {
                fileNameDisplay.classList.add('hidden');
            }
        });
    }

    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', deleteAllUsers);
    }
    
    if (userCancelBtn) {
        userCancelBtn.addEventListener('click', () => userModal.classList.add('hidden'));
    }

    if (bulkCancelBtn) {
        bulkCancelBtn.addEventListener('click', () => bulkModal.classList.add('hidden'));
    }

    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const feedbackDiv = document.getElementById('user-form-feedback');
            
            fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        userModal.classList.add('hidden');
                        location.reload();
                    } else {
                        feedbackDiv.textContent = data.message;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    feedbackDiv.textContent = 'An error occurred. Please try again.';
                });
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'bulk_upload');
            const feedbackDiv = document.getElementById('bulk-form-feedback');
            feedbackDiv.textContent = 'Uploading, please wait...';
            feedbackDiv.className = 'px-6 py-2 text-sm text-blue-600';

            fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let feedbackHTML = `<p>${data.message}</p>`;
                        
                        if (data.failed_entries && data.failed_entries.length > 0) {
                            feedbackHTML += '<p class="mt-2 font-semibold text-left">Failure Details:</p>';
                            feedbackHTML += '<ul class="list-disc list-inside text-left max-h-40 overflow-y-auto border rounded-md p-2 bg-red-50">';
                            data.failed_entries.forEach(error => {
                                feedbackHTML += `<li><small>${error}</small></li>`;
                            });
                            feedbackHTML += '</ul>';
                            feedbackDiv.className = 'px-6 py-2 text-sm text-red-600 text-center';
                        } else {
                            feedbackDiv.className = 'px-6 py-2 text-sm text-green-600';
                            setTimeout(() => location.reload(), 2000);
                        }

                        feedbackDiv.innerHTML = feedbackHTML;
                    } else {
                        feedbackDiv.textContent = 'Error: ' + data.message;
                        feedbackDiv.className = 'px-6 py-2 text-sm text-red-600';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    feedbackDiv.textContent = 'An error occurred during upload.';
                    feedbackDiv.className = 'px-6 py-2 text-sm text-red-600';
                });
        });
    }
});

// --- CRUD Functions (must be in global scope) ---
function editUser(id) {
    const userForm = document.getElementById('user-form');
    const userModal = document.getElementById('user-modal');
    
    fetch(`../api/admin/user_crud.php?action=get&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                userForm.reset();
                document.getElementById('user-modal-title').textContent = 'Edit User';
                document.getElementById('user-form-action').value = 'update';
                document.getElementById('user_id').value = data.user.id;
                document.getElementById('first_name').value = data.user.first_name;
                document.getElementById('last_name').value = data.user.last_name;
                document.getElementById('staff_id').value = data.user.staff_id;
                document.getElementById('position').value = data.user.position;
                document.getElementById('phone_number').value = data.user.phone_number;
                document.getElementById('gender').value = data.user.gender;
                document.getElementById('department_id').value = data.user.department_id;
                document.getElementById('role').value = data.user.role;
                document.getElementById('user_status').value = data.user.status;
                document.getElementById('password').placeholder = 'New Password (leave blank to keep current)';
                userModal.classList.remove('hidden');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while fetching user data.');
        });
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', id);
        fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('User deleted successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the user.');
            });
    }
}

function deleteAllUsers() {
    // Create custom confirmation modal
    const modalHtml = `
        <div id="delete-all-modal" class="fixed z-50 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mr-4">
                                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Delete All Users</h3>
                                <p class="text-sm text-red-100 mt-1">This action cannot be undone</p>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="bg-white px-6 py-6">
                        <div class="space-y-4">
                            <div class="bg-red-50 rounded-lg p-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <div class="text-sm text-red-800">
                                        <p class="font-semibold mb-2">⚠️ WARNING: Critical Action</p>
                                        <p class="mb-2">You are about to permanently delete:</p>
                                        <ul class="list-disc list-inside space-y-1 ml-2">
                                            <li><strong>All normal users</strong> (excluding administrators)</li>
                                            <li><strong>All user progress data</strong></li>
                                            <li><strong>All assessment results</strong></li>
                                            <li><strong>All related records</strong></li>
                                        </ul>
                                        <p class="mt-3 font-semibold text-red-900">This operation is IRREVERSIBLE and cannot be recovered!</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <p class="text-gray-700 font-medium">Are you absolutely sure you want to continue?</p>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-50 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" onclick="document.getElementById('delete-all-modal').remove()" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </button>
                        <button type="button" onclick="confirmDeleteAllUsers()" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 border border-transparent rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors shadow-sm">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Yes, Delete All Users
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function confirmDeleteAllUsers() {
    document.getElementById('delete-all-modal').remove();
    
    const formData = new FormData();
    formData.append('action', 'delete_all_normal_users');
    fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting users.');
        });
}

function resetPassword(id) {
    if (confirm('Are you sure you want to reset this user\'s password? They will be forced to create a new one on their next login.')) {
        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('user_id', id);
        fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Extract password from message "Password has been reset to: APD@123456789"
                    const password = data.message.split(': ')[1];
                    
                    // Create a custom alert with copy button
                    const modalHtml = `
                        <div id="password-reset-modal" class="fixed z-50 inset-0 overflow-y-auto">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                                    <div class="text-center">
                                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">Password Reset Successful</h3>
                                        <p class="text-sm text-gray-500 mb-4">The new temporary password is:</p>
                                        <div class="flex items-center justify-center space-x-2 mb-4">
                                            <input type="text" id="reset-password-value" value="${password}" readonly 
                                                   class="bg-gray-100 border border-gray-300 rounded px-4 py-2 font-mono text-lg text-center" 
                                                   style="width: 200px;">
                                            <button onclick="copyPasswordToClipboard()" 
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors flex items-center">
                                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                                Copy
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-500 mb-4">User will be required to change this password on their next login.</p>
                                        <button onclick="closePasswordResetModal()" 
                                                class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded transition-colors">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Insert modal into page
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while resetting the password.');
            });
    }
}

function copyPasswordToClipboard() {
    const passwordInput = document.getElementById('reset-password-value');
    const copyButton = event.target.closest('button');
    const originalHTML = copyButton.innerHTML;
    
    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(passwordInput.value).then(() => {
            showCopySuccess(copyButton, originalHTML);
        }).catch(() => {
            // Fallback to older method
            fallbackCopy(passwordInput, copyButton, originalHTML);
        });
    } else {
        // Use fallback method for older browsers or non-HTTPS
        fallbackCopy(passwordInput, copyButton, originalHTML);
    }
}

function fallbackCopy(passwordInput, copyButton, originalHTML) {
    try {
        passwordInput.select();
        passwordInput.setSelectionRange(0, 99999); // For mobile devices
        
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess(copyButton, originalHTML);
        } else {
            alert('Failed to copy. Please select the password and press Ctrl+C to copy manually.');
        }
    } catch (err) {
        console.error('Fallback copy failed:', err);
        alert('Failed to copy. Please select the password and press Ctrl+C to copy manually.');
    }
    
    // Remove selection
    window.getSelection().removeAllRanges();
}

function showCopySuccess(copyButton, originalHTML) {
    copyButton.innerHTML = `
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Copied!
    `;
    copyButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
    copyButton.classList.add('bg-green-600');
    
    setTimeout(() => {
        copyButton.innerHTML = originalHTML;
        copyButton.classList.remove('bg-green-600');
        copyButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }, 2000);
}

function closePasswordResetModal() {
    const modal = document.getElementById('password-reset-modal');
    if (modal) {
        modal.remove();
    }
}

function unlockUser(id) {
    if (confirm('Are you sure you want to unlock this user\'s account?')) {
        const formData = new FormData();
        formData.append('action', 'unlock');
        formData.append('user_id', id);
        fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('User account unlocked successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while unlocking the user.');
            });
    }
}
</script>

<?php 
// Helper function for array_find (PHP < 8.0 compatibility)
if (!function_exists('array_find')) {
    function array_find($array, $callback) {
        foreach ($array as $item) {
            if ($callback($item)) {
                return $item;
            }
        }
        return null;
    }
}

require_once 'includes/footer.php';
?>
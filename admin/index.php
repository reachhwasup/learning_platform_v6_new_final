<?php
$page_title = 'Admin Dashboard';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// Fetch stats for dashboard cards
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    if ($total_users === false) $total_users = 0;
} catch (PDOException $e) {
    error_log("Total Users Error: " . $e->getMessage());
    $total_users = 0;
}

try {
    $total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($total_admins === false) $total_admins = 0;
} catch (PDOException $e) {
    error_log("Total Admins Error: " . $e->getMessage());
    $total_admins = 0;
}

try {
    $total_modules = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
    if ($total_modules === false) $total_modules = 0;
} catch (PDOException $e) {
    error_log("Total Modules Error: " . $e->getMessage());
    $total_modules = 0;
}

try {
    $passed_exams = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM final_assessments WHERE status = 'passed'")->fetchColumn();
    if ($passed_exams === false) $passed_exams = 0;
} catch (PDOException $e) {
    error_log("Passed Exams Error: " . $e->getMessage());
    $passed_exams = 0;
}

// Fetch users with quiz attempts by department
$multi_attempts_users = [];
$dept_summary = [];

try {
    // Simpler query - get all users who have taken assessments
    $multi_attempts_query = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.staff_id,
            COALESCE(d.name, 'Unassigned') as department_name,
            COUNT(fa.id) as attempt_count,
            MAX(fa.completed_at) as last_attempt,
            MAX(fa.score) as best_score
        FROM final_assessments fa
        INNER JOIN users u ON fa.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'user'
        GROUP BY u.id, u.first_name, u.last_name, u.staff_id, d.name
        ORDER BY attempt_count DESC, last_attempt DESC
        LIMIT 20
    ";
    $stmt = $pdo->query($multi_attempts_query);
    $multi_attempts_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Multi Attempts Query Error: " . $e->getMessage());
    $multi_attempts_users = [];
}

try {
    // Get summary by department - only show departments with quiz attempts
    $dept_summary_query = "
        SELECT 
            COALESCE(d.name, 'Unassigned') as department_name,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(fa.id) as total_attempts,
            ROUND(COUNT(fa.id) * 1.0 / COUNT(DISTINCT u.id), 1) as avg_attempts
        FROM final_assessments fa
        INNER JOIN users u ON fa.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'user'
        GROUP BY d.name
        ORDER BY user_count DESC, total_attempts DESC
    ";
    $stmt = $pdo->query($dept_summary_query);
    $dept_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Department Summary Error: " . $e->getMessage());
    $dept_summary = [];
}

require_once 'includes/header.php';
?>

<!-- Debug Info: 
Total Users: <?= $total_users ?> 
Total Admins: <?= $total_admins ?> 
Total Modules: <?= $total_modules ?> 
Passed Exams: <?= $passed_exams ?>
Multi-attempt users count: <?= count($multi_attempts_users) ?>
Dept summary count: <?= count($dept_summary) ?>
-->

<style>
    .card-hover {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .stat-card {
        background: white;
        border: 1px solid rgba(229, 231, 235, 1);
    }
    
    .chart-container {
        background: white;
        border: 1px solid rgba(229, 231, 235, 1);
    }
    
    .icon-gradient-blue {
        background: linear-gradient(135deg, #0a6fa7, #085a8a);
    }
    
    .icon-gradient-purple {
        background: linear-gradient(135deg, #7c3aed, #5b21b6);
    }
    
    .icon-gradient-green {
        background: linear-gradient(135deg, #10b981, #059669);
    }
    
    .icon-gradient-orange {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }
</style>

<!-- Dashboard Content -->
<div class="space-y-8">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="stat-card p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Users</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $total_users ?></p>
                    <div class="flex items-center mt-3">
                        <span class="text-xs text-green-600 font-semibold bg-green-50 px-2 py-1 rounded-full">↗ 12%</span>
                        <span class="text-xs text-gray-500 ml-2">vs last month</span>
                    </div>
                </div>
                <div class="icon-gradient-blue text-white p-4 rounded-xl shadow-md">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-600 mb-1">Training Modules</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $total_modules ?></p>
                    <div class="flex items-center mt-3">
                        <span class="text-xs text-blue-600 font-semibold bg-blue-50 px-2 py-1 rounded-full">→ 0%</span>
                        <span class="text-xs text-gray-500 ml-2">no change</span>
                    </div>
                </div>
                <div class="icon-gradient-purple text-white p-4 rounded-xl shadow-md">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-600 mb-1">Exam Completions</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $passed_exams ?></p>
                    <div class="flex items-center mt-3">
                        <span class="text-xs text-green-600 font-semibold bg-green-50 px-2 py-1 rounded-full">↗ 23%</span>
                        <span class="text-xs text-gray-500 ml-2">vs last month</span>
                    </div>
                </div>
                <div class="icon-gradient-green text-white p-4 rounded-xl shadow-md">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-600 mb-1">Administrators</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $total_admins ?></p>
                    <div class="flex items-center mt-3">
                        <span class="text-xs text-orange-600 font-semibold bg-orange-50 px-2 py-1 rounded-full">↗ 5%</span>
                        <span class="text-xs text-gray-500 ml-2">vs last month</span>
                    </div>
                </div>
                <div class="icon-gradient-orange text-white p-4 rounded-xl shadow-md">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Quiz Attempts by Department Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Department Summary -->
        <div class="chart-container p-6 rounded-xl shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                Quiz Attempts by Department
            </h3>
            <?php if (!empty($dept_summary)): ?>
                <div class="space-y-3">
                    <?php foreach ($dept_summary as $dept): ?>
                        <div class="p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg border border-indigo-100 hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($dept['department_name'] ?? 'Unassigned') ?></h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">
                                    <?= $dept['user_count'] ?> users
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="flex items-center text-gray-600">
                                    <svg class="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    <span class="font-medium"><?= $dept['total_attempts'] ?></span>
                                    <span class="ml-1">total</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <svg class="w-4 h-4 mr-1.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                    <span class="font-medium"><?= number_format($dept['avg_attempts'], 1) ?></span>
                                    <span class="ml-1">avg</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-sm">No multi-attempt data available</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Users with Multiple Attempts -->
        <div class="chart-container p-6 rounded-xl shadow-sm lg:col-span-2">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center justify-between">
                <span class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    Users Quiz Attempt History
                </span>
                <span class="text-xs font-normal text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                    Top 20
                </span>
            </h3>
            <?php if (!empty($multi_attempts_users)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Department</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Attempts</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Best Score</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Attempt</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($multi_attempts_users as $user): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-9 w-9 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($user['staff_id']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold 
                                            <?= $user['attempt_count'] == 1 ? 'bg-green-100 text-green-800' : ($user['attempt_count'] > 3 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') ?>">
                                            <?= $user['attempt_count'] ?>x
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="text-sm font-semibold text-gray-900"><?= $user['best_score'] ?></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-xs text-gray-600">
                                        <?= date('M d, Y', strtotime($user['last_attempt'])) ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <a href="view_user_progress.php?user_id=<?= $user['id'] ?>" 
                                           class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-sm font-medium">No assessment attempts yet</p>
                    <p class="text-xs mt-1">Data will appear here when users complete assessments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="chart-container p-6 rounded-xl shadow-sm card-hover">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900">Training Progress Overview</h3>
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-xs text-gray-600">Live</span>
                </div>
            </div>
            <div class="relative h-80">
                <canvas id="overallProgressChart"></canvas>
            </div>
        </div>

        <div class="chart-container p-6 rounded-xl shadow-sm card-hover">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900">Monthly Activity Trends</h3>
                <select class="text-xs border border-gray-300 rounded-lg px-3 py-1.5 text-gray-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option>Last 6 months</option>
                    <option>Last 12 months</option>
                </select>
            </div>
            <div class="relative h-80">
                <canvas id="monthlyActivityChart"></canvas>
            </div>
        </div>

        <div class="chart-container p-6 rounded-xl shadow-sm card-hover">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900">Department Distribution</h3>
                <div class="bg-blue-50 px-3 py-1.5 rounded-lg">
                    <span class="text-xs font-semibold text-blue-700">Active Users</span>
                </div>
            </div>
            <div class="relative h-80">
                <canvas id="usersByDepartmentChart"></canvas>
            </div>
        </div>

        <div class="chart-container p-6 rounded-xl shadow-sm card-hover">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900">Assessment Performance</h3>
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-1.5">
                        <div class="w-2.5 h-2.5 bg-green-500 rounded-full"></div>
                        <span class="text-xs text-gray-600">Passed</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <div class="w-2.5 h-2.5 bg-red-500 rounded-full"></div>
                        <span class="text-xs text-gray-600">Failed</span>
                    </div>
                </div>
            </div>
            <div class="relative h-80">
                <canvas id="assessmentDepartmentChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="chart-container p-6 rounded-xl shadow-sm card-hover">
            <h4 class="font-bold text-gray-900 mb-5 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Quick Actions
            </h4>
            <div class="space-y-3">
                <a href="manage_users.php" class="group flex items-center px-4 py-3 bg-blue-50 hover:bg-blue-600 rounded-lg transition-all duration-200">
                    <svg class="w-5 h-5 mr-3 text-blue-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    <span class="text-sm font-semibold text-blue-700 group-hover:text-white transition-colors">Add New User</span>
                </a>
                <a href="manage_modules.php" class="group flex items-center px-4 py-3 bg-purple-50 hover:bg-purple-600 rounded-lg transition-all duration-200">
                    <svg class="w-5 h-5 mr-3 text-purple-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span class="text-sm font-semibold text-purple-700 group-hover:text-white transition-colors">Create Module</span>
                </a>
                <a href="reports.php" class="group flex items-center px-4 py-3 bg-green-50 hover:bg-green-600 rounded-lg transition-all duration-200">
                    <svg class="w-5 h-5 mr-3 text-green-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="text-sm font-semibold text-green-700 group-hover:text-white transition-colors">Generate Report</span>
                </a>
            </div>
        </div>

        <div class="chart-container p-6 rounded-xl shadow-sm card-hover">
            <h4 class="font-bold text-gray-900 mb-5 flex items-center">
                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Recent Activity
            </h4>
            <div class="space-y-3">
                <div class="flex items-start space-x-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <div class="w-2.5 h-2.5 bg-green-500 rounded-full mt-1.5 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">New user registered</p>
                        <p class="text-xs text-gray-500 mt-0.5">5 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <div class="w-2.5 h-2.5 bg-blue-500 rounded-full mt-1.5 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">Module completed</p>
                        <p class="text-xs text-gray-500 mt-0.5">12 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <div class="w-2.5 h-2.5 bg-purple-500 rounded-full mt-1.5 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">Assessment submitted</p>
                        <p class="text-xs text-gray-500 mt-0.5">23 minutes ago</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="chart-container p-6 rounded-xl shadow-sm card-hover">
            <h4 class="font-bold text-gray-900 mb-5 flex items-center">
                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                System Status
            </h4>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm font-semibold text-gray-900">Database</span>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-green-700 bg-green-100">Online</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm font-semibold text-gray-900">Video Server</span>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-green-700 bg-green-100">Online</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm font-semibold text-gray-900">API Status</span>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-green-700 bg-green-100">Online</span>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Include Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Chart.js default configuration
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 20;
    
    // Fetch data for charts from our API endpoint
    fetch('../api/admin/dashboard_data.php')
        .then(response => response.json())
        .then(data => {
            if (!data || !data.signupData || !data.assessmentData) {
                console.error('Invalid data structure received from API.');
                return;
            }

            // Enhanced color palette - using blue theme
            const colorPalette = [
                '#0a6fa7', '#085a8a', '#064a73', '#0891b2', 
                '#0e7490', '#155e75', '#164e63', '#083344'
            ];

            // --- Chart 1: Users by Department (Pie Chart) ---
            const signupCtx = document.getElementById('usersByDepartmentChart').getContext('2d');
            new Chart(signupCtx, {
                type: 'doughnut',
                data: {
                    labels: data.signupData.map(d => d.name),
                    datasets: [{
                        label: 'Users',
                        data: data.signupData.map(d => d.user_count),
                        backgroundColor: colorPalette,
                        borderWidth: 0,
                        cutout: '65%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });

            // --- Chart 2: Assessment Results by Department (Bar Chart) ---
            const assessmentCtx = document.getElementById('assessmentDepartmentChart').getContext('2d');
            new Chart(assessmentCtx, {
                type: 'bar',
                data: {
                    labels: data.assessmentData.map(d => d.name),
                    datasets: [
                        {
                            label: 'Passed',
                            data: data.assessmentData.map(d => d.passed_count),
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderColor: '#10b981',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false
                        },
                        {
                            label: 'Failed',
                            data: data.assessmentData.map(d => d.failed_count),
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: '#ef4444',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { padding: 20 }
                        }
                    }
                }
            });

            // --- Chart 3: Overall Training Progress (Doughnut Chart) ---
            const progressCtx = document.getElementById('overallProgressChart').getContext('2d');
            new Chart(progressCtx, {
                type: 'doughnut',
                data: {
                    labels: data.overallProgressData.labels,
                    datasets: [{
                        label: 'User Progress',
                        data: data.overallProgressData.data,
                        backgroundColor: ['#10b981', '#f59e0b', '#6b7280'],
                        borderWidth: 0,
                        cutout: '65%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12, weight: '500' }
                            }
                        }
                    }
                }
            });

            // --- Chart 4: Monthly Assessment Completions (Area Chart) ---
            const monthlyCtx = document.getElementById('monthlyActivityChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: data.monthlyActivityData.labels,
                    datasets: [{
                        label: 'Assessments Completed',
                        data: data.monthlyActivityData.data,
                        borderColor: '#0a6fa7',
                        backgroundColor: 'rgba(10, 111, 167, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: '#0a6fa7',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        })
        .catch(error => console.error('Error fetching dashboard data:', error));
});
</script>

<?php require_once 'includes/footer.php'; ?>
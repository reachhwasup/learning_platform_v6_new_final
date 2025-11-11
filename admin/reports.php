<?php
$page_title = 'Assessment Reports';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// --- Get Filter Values ---
$filter_dept = isset($_GET['department']) && $_GET['department'] !== '' ? (int)$_GET['department'] : null;
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['passed', 'failed']) ? $_GET['status'] : null;

try {
    // --- Build SQL Query with Filters ---
    // Group by user and get all their attempts
    $sql = "SELECT 
                u.id as user_id,
                u.first_name, u.last_name, u.username, u.staff_id, u.position, 
                d.name as department_name,
                COUNT(fa.id) as total_attempts,
                MAX(fa.score) as best_score,
                MAX(CASE WHEN fa.status = 'passed' THEN 1 ELSE 0 END) as has_passed
            FROM users u
            JOIN final_assessments fa ON u.id = fa.user_id
            LEFT JOIN departments d ON u.department_id = d.id";
    
    $where_clauses = [];
    $params = [];

    if ($filter_dept) {
        $where_clauses[] = "u.department_id = :dept_id";
        $params[':dept_id'] = $filter_dept;
    }
    if ($filter_status) {
        $where_clauses[] = "EXISTS (SELECT 1 FROM final_assessments WHERE user_id = u.id AND status = :status)";
        $params[':status'] = $filter_status;
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " GROUP BY u.id, u.first_name, u.last_name, u.username, u.staff_id, u.position, d.name
              ORDER BY u.last_name ASC, u.first_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_results = $stmt->fetchAll();
    
    // Fetch departments for the filter dropdown
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
    
    // Get total attempts count
    $total_attempts = $pdo->query("SELECT COUNT(*) FROM final_assessments")->fetchColumn();

} catch (PDOException $e) {
    error_log("Reports Page Error: " . $e->getMessage());
    $all_results = [];
    $departments = [];
    $total_attempts = 0;
}

require_once 'includes/header.php';

// Helper function to render the results table
function render_results_table($title, $users, $status_context, $color) {
    $gradient_colors = [
        'blue' => 'linear-gradient(135deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%)',
        'green' => 'linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%)',
        'red' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #b91c1c 100%)'
    ];
    $bg_gradient = $gradient_colors[$color] ?? $gradient_colors['blue'];
    
    echo "<div class='mb-8'>";
    echo "<div class='bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden'>";
    
    // Header
    echo "<div class='px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4'>";
    echo "<h2 class='text-xl font-bold text-gray-900 flex items-center'>";
    echo "<span class='inline-flex items-center justify-center w-8 h-8 rounded-lg text-white mr-3' style='background: {$bg_gradient};'>" . count($users) . "</span>";
    echo htmlspecialchars($title);
    echo "</h2>";
    
    echo "<div class='flex flex-wrap gap-2'>";
    $export_params = http_build_query(['status' => $status_context] + $_GET);
    echo "<div class='relative inline-block text-left' x-data='{ open: false }' @click.away='open = false'>";
    echo "<button @click='open = !open' class='inline-flex items-center px-4 py-2 text-white font-semibold text-sm rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200' style='background: {$bg_gradient};'>";
    echo "<svg class='w-4 h-4 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>";
    echo "Export";
    echo "<svg class='w-4 h-4 ml-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/></svg>";
    echo "</button>";
    echo "<div x-show='open' x-transition class='absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50'>";
    echo "<div class='py-1'>";
    echo "<a href='../api/admin/export_basic.php?{$export_params}' class='flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 transition-colors'>";
    echo "<svg class='w-4 h-4 mr-3 text-gray-500' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>";
    echo "<div><div class='font-medium'>Export Summary</div><div class='text-xs text-gray-500'>Basic user information</div></div>";
    echo "</a>";
    echo "<a href='../api/admin/generate_report.php?{$export_params}' class='flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 transition-colors'>";
    echo "<svg class='w-4 h-4 mr-3 text-gray-500' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>";
    echo "<div><div class='font-medium'>Export Details</div><div class='text-xs text-gray-500'>Full assessment data</div></div>";
    echo "</a>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";

    // Table
    echo "<div class='overflow-x-auto'>";
    echo "<table class='min-w-full divide-y divide-gray-200'>";
    echo "<thead class='bg-gray-50'><tr>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>No.</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Name</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Username</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Staff ID</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Position</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Department</th>
            <th class='px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider'>Total Attempts</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Best Score</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Status</th>
            <th class='px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider'>Action</th>
          </tr></thead>";
    echo "<tbody class='bg-white divide-y divide-gray-200'>";

    if (empty($users)) {
        echo "<tr><td colspan='10' class='px-6 py-12 text-center'>";
        echo "<div class='flex flex-col items-center justify-center'>";
        echo "<div class='w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4'>";
        echo "<svg class='w-8 h-8 text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>";
        echo "</div>";
        echo "<p class='text-gray-500 font-medium'>No results found in this category</p>";
        echo "</div></td></tr>";
    } else {
        $counter = 1;
        foreach ($users as $user) {
            $status_badge_class = $user['has_passed'] 
                ? 'bg-green-100 text-green-800 border border-green-200' 
                : 'bg-red-100 text-red-800 border border-red-200';
            $status_text = $user['has_passed'] ? 'Passed' : 'Not Passed';
            
            echo "<tr class='hover:bg-gray-50 transition-colors'>
                    <td class='px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900'>" . $counter . "</td>
                    <td class='px-6 py-4 whitespace-nowrap'>
                        <div class='flex items-center'>
                            <div class='flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center'>
                                <span class='text-blue-600 font-semibold text-xs'>" . strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) . "</span>
                            </div>
                            <div class='ml-3'>
                                <p class='text-sm font-semibold text-gray-900'>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</p>
                            </div>
                        </div>
                    </td>
                    <td class='px-6 py-4 whitespace-nowrap text-sm text-gray-600'>" . htmlspecialchars($user['username']) . "</td>
                    <td class='px-6 py-4 whitespace-nowrap text-sm text-gray-600'>" . htmlspecialchars($user['staff_id']) . "</td>
                    <td class='px-6 py-4 text-sm text-gray-600'>" . htmlspecialchars($user['position'] ?? 'N/A') . "</td>
                    <td class='px-6 py-4 text-sm text-gray-600'>" . htmlspecialchars($user['department_name'] ?? 'N/A') . "</td>
                    <td class='px-6 py-4 whitespace-nowrap text-center'>
                        <span class='inline-flex items-center justify-center px-3 py-1 text-white text-sm font-bold rounded-lg' style='background: {$bg_gradient};'>
                            <svg class='w-4 h-4 mr-1' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'/></svg>
                            " . htmlspecialchars($user['total_attempts']) . "
                        </span>
                    </td>
                    <td class='px-6 py-4 whitespace-nowrap'>
                        <div class='flex items-center'>
                            <span class='text-lg font-bold text-gray-900'>" . (int)$user['best_score'] . "</span>
                            <span class='text-sm text-gray-500 ml-1'>%</span>
                        </div>
                    </td>
                    <td class='px-6 py-4 whitespace-nowrap'>
                        <span class='px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {$status_badge_class}'>" . $status_text . "</span>
                    </td>
                    <td class='px-6 py-4 whitespace-nowrap text-sm'>
                        <a href='view_exam_details.php?user_id={$user['user_id']}' class='inline-flex items-center px-3 py-1.5 text-blue-700 bg-blue-100 hover:bg-blue-600 hover:text-white font-semibold rounded-lg transition-all duration-200 shadow-sm hover:shadow-md'>
                            <svg class='w-4 h-4 mr-1' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'/></svg>
                            View All
                        </a>
                    </td>
                  </tr>";
            $counter++;
        }
    }
    echo "</tbody></table></div></div></div>";
}
?>

<div class="min-h-screen bg-gray-50/50">
    <div class="container mx-auto px-4 py-8 max-w-[1600px]">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Assessment Reports</h1>
                    <p class="text-gray-600 mt-1">View and export user assessment results</p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg border border-blue-400 p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium mb-1">Total Attempts</p>
                        <p class="text-4xl font-bold"><?= $total_attempts ?></p>
                        <p class="text-blue-100 text-xs mt-2">All assessment attempts</p>
                    </div>
                    <div class="p-4 bg-white/20 backdrop-blur-sm rounded-2xl">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-lg border border-purple-400 p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium mb-1">Unique Users</p>
                        <p class="text-4xl font-bold"><?= count($all_results) ?></p>
                        <p class="text-purple-100 text-xs mt-2">Users who took assessment</p>
                    </div>
                    <div class="p-4 bg-white/20 backdrop-blur-sm rounded-2xl">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                Filter Reports
            </h3>
            <form method="GET" action="reports.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="department" class="block text-sm font-semibold text-gray-700 mb-2">Department</label>
                    <select name="department" id="department" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($filter_dept == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="status" id="status" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        <option value="">All Statuses</option>
                        <option value="passed" <?= ($filter_status === 'passed') ? 'selected' : '' ?>>Passed</option>
                        <option value="failed" <?= ($filter_status === 'failed') ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-6 py-3 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200" style="background: linear-gradient(135deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%);">
                        <span class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            Apply Filter
                        </span>
                    </button>
                </div>
            </form>
        </div>

    <?php
        // Render single table with all attempts
        render_results_table('All Assessment Attempts', $all_results, 'all', 'blue');
    ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
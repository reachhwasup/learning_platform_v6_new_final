<?php
/**
 * Enhanced User Panel Sidebar - Fixed Version
 */

// Check user progress for sidebar display
$sidebar_total_modules = 0;
$sidebar_completed_modules = 0;
$sidebar_can_take_assessment = false;
$sidebar_has_passed = false;

try {
    if (isset($_SESSION['user_id'])) {
        // Get total modules and user progress
        $sidebar_total_modules = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
        $stmt_sidebar_progress = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ?");
        $stmt_sidebar_progress->execute([$_SESSION['user_id']]);
        $sidebar_completed_modules = $stmt_sidebar_progress->fetchColumn();
        
        // Check if user can take assessment
        $sidebar_can_take_assessment = ($sidebar_total_modules > 0 && $sidebar_completed_modules >= $sidebar_total_modules);
        
        // Check if user has already passed
        $stmt_sidebar_passed = $pdo->prepare("SELECT id FROM final_assessments WHERE user_id = ? AND status = 'passed' LIMIT 1");
        $stmt_sidebar_passed->execute([$_SESSION['user_id']]);
        $sidebar_has_passed = $stmt_sidebar_passed->fetch() ? true : false;
    }
} catch (Exception $e) {
    // Silently handle errors for sidebar
    error_log("Sidebar assessment check error: " . $e->getMessage());
}
?>

<style>
.sidebar-gradient {
    background: linear-gradient(180deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%);
    backdrop-filter: blur(20px);
}

.glass-effect {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.nav-item {
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transition: left 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-item::before {
    left: 100%;
}

.nav-item:hover {
    transform: translateX(4px);
    background: rgba(255, 255, 255, 0.15);
}

.active-nav {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.15));
    border-left: 4px solid #0a6fa7;
    transform: translateX(2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.active-nav span {
    color: #ffffff !important;
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
}

.nav-item span {
    color: #ffffff !important;
    font-weight: 600;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
}

.nav-badge {
    animation: pulse 2s infinite;
}
.locked-nav {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.locked-nav:hover {
    background: none !important;
    transform: none !important;
}
.passed-nav {
    background: rgba(34, 197, 94, 0.1);
    border-left: 4px solid #22c55e;
}

.sidebar-progress-card {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(8px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.sidebar-progress-card:hover {
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.2);
}

.progress-title {
    background: linear-gradient(135deg, #ffffff, #f1f5f9);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    text-shadow: none;
}

.progress-ring {
    transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.section-title {
    background: linear-gradient(135deg, #ffffff, #e2e8f0);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.assessment-section {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.section-title {
    background: linear-gradient(135deg, #ffffff, #e2e8f0);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

.sidebar-text {
    color: #ffffff !important;
}

.nav-item span {
    color: #ffffff !important;
    font-weight: 600;
}

.sidebar-progress-card {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(8px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.sidebar-progress-card:hover {
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.2);
}

.progress-title {
    background: linear-gradient(135deg, #ffffff, #f1f5f9);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}
</style>

<!-- Sidebar -->
<aside id="user-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 md:w-72 flex-shrink-0 sidebar-gradient text-white flex flex-col shadow-2xl" style="color: #ffffff !important;">
    
    <!-- Header Section -->
    <div class="h-20 flex items-center justify-center px-6 border-b border-white border-opacity-20 bg-black bg-opacity-20">
        <a href="dashboard.php" class="flex items-center justify-center group">
            <div class="relative">
                <img src="assets/images/logo.png" alt="Company Logo" class="h-12 transition-transform duration-300 group-hover:scale-110 drop-shadow-lg"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <!-- Fallback logo -->
                <div class="h-12 w-12 bg-gradient-to-br from-[#0a6fa7] to-[#0d7bb8] rounded-xl flex items-center justify-center shadow-lg" style="display: none;">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <div class="absolute -inset-2 bg-white bg-opacity-10 rounded-full blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </div>
        </a>

        <!-- Mobile Close Button -->
        <button id="sidebar-close-button" class="md:hidden absolute right-4 p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-all duration-200 hover:rotate-90">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Progress Summary (if modules exist) -->
    <?php if ($sidebar_total_modules > 0): ?>
    <div class="px-6 py-5 border-b border-white border-opacity-10 animate-fade-in">
        <div class="sidebar-progress-card rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-xs font-bold progress-title uppercase tracking-wider">LEARNING PROGRESS</p>
                    <p class="text-lg font-bold text-white mt-2" style="text-shadow: none;">
                        <?= $sidebar_completed_modules ?> / <?= $sidebar_total_modules ?> Modules
                    </p>
                    <p class="text-sm" style="color: #bfdbfe !important; font-weight: 500; text-shadow: none;">1 courses available</p>
                </div>
                <div class="relative w-16 h-16">
                    <?php 
                    $progress_percentage = $sidebar_total_modules > 0 ? ($sidebar_completed_modules / $sidebar_total_modules) * 100 : 0;
                    $circumference = 2 * pi() * 28;
                    ?>
                    <svg class="w-16 h-16 transform -rotate-90">
                        <circle
                            class="text-white text-opacity-10"
                            stroke-width="3"
                            stroke="currentColor"
                            fill="transparent"
                            r="28"
                            cx="32"
                            cy="32"
                        />
                        <circle
                            class="text-white progress-ring"
                            stroke-width="3"
                            stroke="currentColor"
                            fill="transparent"
                            r="28"
                            cx="32"
                            cy="32"
                            stroke-dasharray="<?= $circumference ?>"
                            stroke-dashoffset="<?= $circumference * (1 - $progress_percentage / 100) ?>"
                            stroke-linecap="round"
                        />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-sm font-bold text-white"><?= round($progress_percentage) ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Section -->
    <nav class="flex-1 px-4 py-6 space-y-6 overflow-y-auto scrollbar-thin">
        <div class="space-y-3 animate-fade-in">
            <p class="px-2 text-sm font-bold uppercase tracking-wider" style="color: #ffffff !important; font-weight: 700 !important;">Main Menu</p>

            <div class="space-y-1">
                <!-- Dashboard -->
                <a href="dashboard.php" class="nav-item flex items-center px-4 py-3 rounded-xl transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active-nav' : '' ?>">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center mr-4">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                    </div>
                    <span class="font-semibold" style="color: #ffffff !important; font-weight: 600 !important;">Dashboard</span>
                    <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
                        <div class="ml-auto w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
                    <?php endif; ?>
                </a>

                <!-- Help & Support -->
                <a href="help_support.php" class="nav-item flex items-center px-4 py-3 rounded-xl transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'help_support.php' ? 'active-nav' : '' ?>">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center mr-4">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="font-semibold" style="color: #ffffff !important; font-weight: 600 !important;">Help & Support</span>
                    <?php if (basename($_SERVER['PHP_SELF']) == 'help_support.php'): ?>
                        <div class="ml-auto w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Divider -->
        <div class="border-t border-white border-opacity-10"></div>

        <!-- Assessment Section -->
        <div class="space-y-3 animate-fade-in">
            <p class="px-2 text-sm font-bold uppercase tracking-wider" style="color: #ffffff !important; font-weight: 700 !important;">
                Assessment
            </p>

            <div class="space-y-1">
                <!-- Final Assessment -->
                <?php
                $assessment_classes = "nav-item flex items-center px-4 py-3 rounded-xl transition-all duration-300 group";
                $assessment_link = "final_assessment.php";
                
                if (!$sidebar_can_take_assessment) {
                    $assessment_classes .= " locked-nav";
                    $assessment_link = "#";
                } elseif ($sidebar_has_passed) {
                    $assessment_classes .= " passed-nav";
                } else {
                    $assessment_classes .= "";
                }
                
                if (basename($_SERVER['PHP_SELF']) == 'final_assessment.php') {
                    $assessment_classes .= " active-nav";
                }
                ?>
                
                <div class="relative group">
                    <a href="<?= $assessment_link ?>" class="<?= $assessment_classes ?>" 
                       <?= !$sidebar_can_take_assessment ? 'onclick="showAssessmentTooltip(event)"' : '' ?>>
                        <div class="w-10 h-10 rounded-xl <?= $sidebar_has_passed ? 'bg-gradient-to-br from-green-500 to-emerald-600' : ($sidebar_can_take_assessment ? 'bg-gradient-to-br from-yellow-500 to-orange-500' : 'bg-gradient-to-br from-gray-500 to-gray-600') ?> flex items-center justify-center mr-4">
                            <?php if ($sidebar_has_passed): ?>
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php elseif ($sidebar_can_take_assessment): ?>
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="font-semibold" style="color: #ffffff !important; font-weight: 600 !important;">
                                Final Assessment
                            </span>
                            <?php if ($sidebar_has_passed): ?>
                                <div class="text-xs text-green-400 mt-1 font-medium">Passed ✓ No retakes</div>
                            <?php elseif (!$sidebar_can_take_assessment): ?>
                                <div class="text-xs text-gray-400 mt-1">
                                    Complete <?= $sidebar_total_modules - $sidebar_completed_modules ?> more module<?= ($sidebar_total_modules - $sidebar_completed_modules) > 1 ? 's' : '' ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-yellow-300 mt-1 font-medium">Ready to take!</div>
                            <?php endif; ?>
                        </div>
                        <?php if (basename($_SERVER['PHP_SELF']) == 'final_assessment.php'): ?>
                            <div class="ml-2 w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Tooltip for locked assessment -->
                    <?php if (!$sidebar_can_take_assessment): ?>
                    <div id="assessment-tooltip" class="hidden absolute left-full top-0 ml-2 bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap z-50 shadow-lg">
                        Complete all <?= $sidebar_total_modules ?> learning modules first
                        <div class="absolute left-0 top-1/2 transform -translate-y-1/2 -translate-x-1 w-2 h-2 bg-gray-900 rotate-45"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Footer Section -->
    <div class="p-6 border-t border-white border-opacity-10">
        <a href="api/auth/logout.php" class="nav-item flex items-center px-4 py-3 rounded-xl transition-all duration-300 bg-red-500 bg-opacity-10 hover:bg-red-500 hover:bg-opacity-20 group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center mr-4">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </div>
            <span class="font-semibold text-white group-hover:text-red-300">Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 z-20 bg-black bg-opacity-50 md:hidden hidden transition-opacity duration-200"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('user-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const sidebarCloseButton = document.getElementById('sidebar-close-button');
    const mainContent = document.getElementById('main-content');

    // Initialize sidebar state based on screen size
    function initializeSidebar() {
        if (window.innerWidth >= 768) {
            // Desktop: sidebar visible, content shifted
            sidebar.classList.remove('-translate-x-full');
            if (mainContent) {
                mainContent.classList.add('sidebar-open');
                mainContent.classList.remove('sidebar-closed');
            }
            if (overlay) {
                overlay.classList.add('hidden');
            }
        } else {
            // Mobile: sidebar hidden
            sidebar.classList.add('-translate-x-full');
            if (overlay) {
                overlay.classList.add('hidden');
            }
            document.body.classList.remove('overflow-hidden');
        }
    }

    // Initialize on load
    initializeSidebar();

    // Reinitialize on window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(initializeSidebar, 150);
    });

    // Close sidebar function
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        if (overlay) {
            overlay.classList.add('hidden');
        }
        document.body.classList.remove('overflow-hidden');
        
        if (window.innerWidth >= 768 && mainContent) {
            mainContent.classList.remove('sidebar-open');
            mainContent.classList.add('sidebar-closed');
        }
    }

    // Close button click
    if (sidebarCloseButton) {
        sidebarCloseButton.addEventListener('click', closeSidebar);
    }

    // Overlay click
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
            closeSidebar();
        }
    });

    // Add smooth scrolling to nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Add click ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'absolute inset-0 bg-white bg-opacity-20 rounded-xl transform scale-0 transition-transform duration-300';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.classList.add('scale-100');
                setTimeout(() => {
                    ripple.remove();
                }, 300);
            }, 10);
        });
    });
});

// Function to show assessment tooltip
function showAssessmentTooltip(event) {
    event.preventDefault();
    const tooltip = document.getElementById('assessment-tooltip');
    if (tooltip) {
        tooltip.classList.remove('hidden');
        setTimeout(() => {
            tooltip.classList.add('hidden');
        }, 3000);
    }
}
</script>
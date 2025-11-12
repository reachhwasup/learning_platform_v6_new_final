<?php
require_once 'functions.php';

// --- Determine the correct profile picture path ---
$profile_pic_path = 'assets/images/default_avatar.jpg';
if (isset($_SESSION['user_profile_picture']) && $_SESSION['user_profile_picture'] !== 'default_avatar.jpg' && $_SESSION['user_profile_picture'] !== 'default_avatar.png') {
    $user_pic = 'uploads/profile_pictures/' . $_SESSION['user_profile_picture'];
    if (file_exists($user_pic)) {
        $profile_pic_path = $user_pic;
    }
}

// Get current page for breadcrumb
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_names = [
    'dashboard' => 'Dashboard',
    'profile' => 'My Profile',
    'final_assessment' => 'Final Assessment'
];
$current_page_name = htmlspecialchars($page_names[$current_page] ?? ucfirst(str_replace('_', ' ', $current_page)), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Security Awareness Platform' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { 
                        'primary': '#0052cc', 
                        'primary-dark': '#0041a3', 
                        'secondary': '#f4f5f7', 
                        'accent': '#ffab00',
                        'gradient-start': '#667eea',
                        'gradient-end': '#764ba2'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-down': 'slideDown 0.3s ease-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes bounceGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        .glass-effect {
            backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        /* Sidebar toggle animations */
        .main-content {
            transition: left 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 288px; /* Default: sidebar visible */
            background: inherit;
            will-change: left;
        }
        
        .main-content.sidebar-closed {
            left: 0; /* Sidebar hidden - full width */
        }
        
        @media (max-width: 767px) {
            .main-content {
                left: 0 !important; /* On mobile, sidebar overlays */
            }
        }
        
        /* Ensure sidebar has smooth transitions */
        #user-sidebar {
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), 
                        opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
            will-change: transform, opacity;
        }
        
        /* Hidden state with smooth transition */
        #user-sidebar.-translate-x-full {
            transform: translateX(-100%);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        
        @media (max-width: 767px) {
            .main-content {
                left: 0 !important; /* On mobile, sidebar overlays */
            }
        }
    </style>
    
    <!-- Security Script - Prevent Code Inspection -->
    <script src="assets/js/security.js?v=<?= time() ?>"></script>
</head>
<body class="h-full bg-gradient-to-br from-gray-50 to-blue-50" style="overflow-x: hidden;">
    <?php require_once 'user_sidebar.php'; ?>
    <div class="main-content sidebar-open" id="main-content">
        
        <!-- Enhanced Header -->
        <header class="glass-effect border-b border-gray-200 z-20 relative shadow-sm">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    
                    <!-- Left Section: Mobile Menu + Icon + Title + Breadcrumb -->
                    <div class="flex items-center space-x-4 flex-1">
                        <!-- Sidebar Toggle Button (Hamburger) - Visible on all screens -->
                        <button id="sidebarToggle" class="p-2 rounded-xl hover:bg-white hover:shadow-md transition-all duration-200 hover:scale-105 text-gray-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        
                        <!-- Page Icon + Title & Breadcrumb -->
                        <div class="flex items-center space-x-3">
                            <!-- Dynamic Page Icon -->
                            <div class="flex-shrink-0">
                                <?php
                                $page_icons = [
                                    'dashboard' => '<svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>',
                                    'view_module' => '<svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>',
                                    'profile' => '<svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
                                    'final_assessment' => '<svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>'
                                ];
                                echo $page_icons[$current_page] ?? '<svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
                                ?>
                            </div>
                            
                            <!-- Title & Breadcrumb -->
                            <div class="flex flex-col">
                                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">
                                    <?= isset($page_title) ? htmlspecialchars($page_title) : $current_page_name ?>
                                </h1>
                                <nav class="hidden sm:flex items-center space-x-2 text-sm text-gray-500">
                                    <a href="dashboard.php" class="hover:text-blue-600 transition-colors">Home</a>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <span class="text-gray-900 font-medium"><?= $current_page_name ?></span>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <!-- Right Section: Actions + Profile -->
                    <div class="flex items-center space-x-3">

                        <!-- Theme Toggle (Desktop) -->
                        <button id="theme-toggle" class="hidden md:flex p-2 rounded-xl text-gray-500 hover:bg-white hover:text-gray-700 hover:shadow-md transition-all duration-200 group">
                            <svg class="h-5 w-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                        </button>

                        <!-- Divider (Desktop) -->
                        <div class="hidden md:block w-px h-6 bg-gray-300"></div>

                        <!-- Profile Dropdown -->
                        <div class="relative" id="profile-dropdown-container">
                            <button id="profile-dropdown-button" class="flex items-center space-x-3 p-2 rounded-xl bg-white shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <!-- User Info (Hidden on Mobile) -->
                                <div class="hidden lg:block text-right">
                                    <p class="text-sm font-semibold text-gray-900 leading-tight">
                                        <?= htmlspecialchars(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? 'User')) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 leading-tight">
                                        <?= htmlspecialchars($_SESSION['user_position'] ?? 'Team Member') ?>
                                    </p>
                                </div>
                                <!-- Profile Picture -->
                                <div class="relative">
                                    <img class="h-10 w-10 rounded-full object-cover border-2 border-white shadow-sm" 
                                         src="<?= htmlspecialchars($profile_pic_path) ?>" 
                                         alt="Profile Picture">
                                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 border-2 border-white rounded-full"></div>
                                </div>
                                <!-- Dropdown Arrow -->
                                <svg class="h-4 w-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="profile-dropdown-menu" class="hidden absolute right-0 mt-3 w-64 origin-top-right rounded-2xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none z-50 animate-slide-down border" role="menu">
                                <div class="p-1" role="none">
                                    <!-- Menu Items -->
                                    <div class="py-2">
                                        <a href="profile.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 rounded-xl mx-2 transition-all duration-200 group" role="menuitem">
                                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-3 group-hover:bg-blue-200 transition-colors">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">My Profile</p>
                                                <p class="text-xs text-gray-500">Manage your account</p>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Logout Section -->
                                    <div class="border-t border-gray-100 py-2">
                                        <a href="api/auth/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 rounded-xl mx-2 transition-all duration-200 group" role="menuitem">
                                            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center mr-3 group-hover:bg-red-200 transition-colors">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">Sign Out</p>
                                                <p class="text-xs text-gray-500">End your session</p>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-blue-50">
            <div class="container mx-auto px-4 py-6">
            <!-- Page content will be inserted here by individual pages -->

<script>
// Enhanced Header Script
document.addEventListener('DOMContentLoaded', function() {
    const profileDropdownButton = document.getElementById('profile-dropdown-button');
    const profileDropdownMenu = document.getElementById('profile-dropdown-menu');
    const profileDropdownContainer = document.getElementById('profile-dropdown-container');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const themeToggle = document.getElementById('theme-toggle');

    // Profile dropdown functionality
    if (profileDropdownButton) {
        profileDropdownButton.addEventListener('click', function(event) {
            event.stopPropagation();
            profileDropdownMenu.classList.toggle('hidden');
            
            // Rotate arrow
            const arrow = this.querySelector('svg:last-child');
            if (arrow) {
                arrow.style.transform = profileDropdownMenu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
    }

    // Theme toggle functionality
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            // Add your theme toggle logic here
            document.body.classList.toggle('dark');
            
            // Update icon
            const icon = this.querySelector('svg');
            if (document.body.classList.contains('dark')) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>';
            } else {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>';
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (profileDropdownContainer && !profileDropdownContainer.contains(event.target)) {
            profileDropdownMenu.classList.add('hidden');
            const arrow = profileDropdownButton?.querySelector('svg:last-child');
            if (arrow) {
                arrow.style.transform = 'rotate(0deg)';
            }
        }
    });

    // Sidebar toggle button functionality
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('user-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const mainContent = document.getElementById('main-content');
            
            if (sidebar) {
                // Check if sidebar is currently hidden
                const isHidden = sidebar.classList.contains('-translate-x-full');
                
                if (window.innerWidth < 768) {
                    // Mobile behavior
                    if (isHidden) {
                        // Show sidebar
                        sidebar.classList.remove('-translate-x-full');
                        if (overlay) overlay.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    } else {
                        // Hide sidebar
                        sidebar.classList.add('-translate-x-full');
                        if (overlay) overlay.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }
                } else {
                    // Desktop behavior
                    // Add transitioning class to disable expensive transitions
                    document.body.classList.add('transitioning');
                    
                    if (isHidden) {
                        // Show sidebar
                        sidebar.classList.remove('-translate-x-full');
                        if (mainContent) {
                            mainContent.classList.add('sidebar-open');
                            mainContent.classList.remove('sidebar-closed');
                        }
                    } else {
                        // Hide sidebar
                        sidebar.classList.add('-translate-x-full');
                        if (mainContent) {
                            mainContent.classList.remove('sidebar-open');
                            mainContent.classList.add('sidebar-closed');
                        }
                    }
                    
                    // Remove transitioning class after animation completes
                    setTimeout(() => {
                        document.body.classList.remove('transitioning');
                    }, 200);
                }
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape key functionality
        if (e.key === 'Escape') {
            // Close search modal (if it exists)
            const searchModal = document.getElementById('search-modal');
            if (searchModal && !searchModal.classList.contains('hidden')) {
                searchModal.classList.add('hidden');
            }
            // Close profile dropdown
            if (!profileDropdownMenu.classList.contains('hidden')) {
                profileDropdownMenu.classList.add('hidden');
                const arrow = profileDropdownButton?.querySelector('svg:last-child');
                if (arrow) {
                    arrow.style.transform = 'rotate(0deg)';
                }
            }
            // Close notifications dropdown
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) {
                notificationsDropdown.classList.add('hidden');
            }
        }

        // Ctrl/Cmd + K to open search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchModal = document.getElementById('search-modal');
            const searchInput = document.getElementById('search-input');
            if (searchModal) {
                searchModal.classList.remove('hidden');
                setTimeout(() => searchInput?.focus(), 100);
            }
        }
    });

    // Search modal background click to close
    const searchModal = document.getElementById('search-modal');
    if (searchModal) {
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) {
                searchModal.classList.add('hidden');
            }
        });
    }

    // Add smooth animations to interactive elements
    const interactiveElements = document.querySelectorAll('button, a');
    interactiveElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            if (this.classList.contains('hover:scale-105')) {
                this.style.transform = 'scale(1.05)';
            }
        });
        
        element.addEventListener('mouseleave', function() {
            if (this.classList.contains('hover:scale-105')) {
                this.style.transform = 'scale(1)';
            }
        });
    });
});
</script>
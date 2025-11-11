<?php
/**
 * Admin Panel Sidebar
 */
?>
<!-- Sidebar -->
<aside class="w-64 flex-shrink-0 text-white flex flex-col shadow-xl" style="background: linear-gradient(180deg, #0a6fa7 0%, #085a8a 50%, #064a73 100%);">
    <!-- Logo Section -->
    <div class="h-16 flex items-center justify-center px-4" style="border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
        <a href="index.php" class="flex items-center space-x-2">
            <img src="../assets/images/logo.png" alt="Company Logo" class="h-10 transition-transform hover:scale-105">
        </a>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
        <a href="index.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group" style="transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='transparent'; this.style.transform='translateX(0)';">
            <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span class="font-medium">Dashboard</span>
        </a>
        
        <!-- User Management Dropdown -->
        <div class="relative" x-data="{ open: false }" @click.away="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 rounded-lg transition-all duration-200 group" style="transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)';" onmouseout="if(!this.classList.contains('active')) this.style.background='transparent';">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <span class="font-medium">User Management</span>
                </div>
                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform -translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="ml-4 mt-1 space-y-1" x-cloak>
                <a href="manage_users.php?type=user" @click="open = false" class="flex items-center px-4 py-2.5 rounded-lg transition-all duration-200 text-sm" style="transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='transparent'; this.style.transform='translateX(0)';">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span class="font-medium">Manage Users</span>
                </a>
                <a href="manage_users.php?type=admin" @click="open = false" class="flex items-center px-4 py-2.5 rounded-lg transition-all duration-200 text-sm" style="transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='transparent'; this.style.transform='translateX(0)';">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <span class="font-medium">Manage Admins</span>
                </a>
            </div>
        </div>
        
        <a href="manage_modules.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group" style="transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='transparent'; this.style.transform='translateX(0)';">
            <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
            <span class="font-medium">Manage Modules</span>
        </a>
        
        <a href="manage_questions.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group" style="transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='transparent'; this.style.transform='translateX(0)';">
            <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="font-medium">Manage Questions</span>
        </a>
        
        <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 group" style="transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='transparent'; this.style.transform='translateX(0)';">
            <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span class="font-medium">Reports</span>
        </a>
    </nav>
    
    <!-- Footer Section -->
    <div class="px-4 py-4" style="border-top: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="text-xs text-blue-200 text-center">
            Admin Panel v6.0
        </div>
    </div>
</aside>

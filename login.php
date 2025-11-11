<?php
// This page doesn't need a session check, as it's the entry point for non-logged-in users.
// We can include functions to start the session for when the form is processed.
require_once 'includes/functions.php';

// If a user is already logged in, redirect them away from the login page.
if (is_logged_in()) {
    // Redirect admins to their dashboard, users to theirs
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        redirect('admin/index.php');
    } else {
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Security Awareness Platform</title>
    <!-- Tailwind CSS for modern styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { 
                        'primary': '#0a6fa7', 
                        'primary-dark': '#085a8a', 
                        'secondary': '#f4f5f7', 
                        'accent': '#0891b2',
                        'gradient-start': '#0a6fa7',
                        'gradient-end': '#0891b2'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.8s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slide-down': 'slideDown 0.4s ease-out',
                        'bounce-subtle': 'bounceSubtle 0.6s ease-out',
                    }
                }
            }
        }
    </script>
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes bounceSubtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-10px) rotate(1deg); }
            66% { transform: translateY(5px) rotate(-1deg); }
        }
        .glass-effect {
            backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #0a6fa7 0%, #0891b2 100%);
            position: relative;
            overflow: hidden;
        }
        .triangle-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            background-image: 
                linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%),
                linear-gradient(-45deg, transparent 40%, rgba(255,255,255,0.05) 50%, transparent 60%);
            background-size: 60px 60px, 40px 40px;
            background-position: 0 0, 20px 20px;
            animation: triangleMove 20s linear infinite;
        }
        .triangle-pattern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                conic-gradient(from 0deg at 50% 50%, transparent 0deg, rgba(255,255,255,0.08) 120deg, transparent 240deg);
            background-size: 80px 80px;
            animation: triangleRotate 30s linear infinite reverse;
        }
        .triangle-pattern::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                60deg,
                transparent,
                transparent 10px,
                rgba(255,255,255,0.03) 10px,
                rgba(255,255,255,0.03) 20px
            );
            animation: triangleSlide 25s linear infinite;
        }
        @keyframes triangleMove {
            0% { transform: translateX(0) translateY(0); }
            25% { transform: translateX(-20px) translateY(-20px); }
            50% { transform: translateX(-40px) translateY(0); }
            75% { transform: translateX(-20px) translateY(20px); }
            100% { transform: translateX(0) translateY(0); }
        }
        @keyframes triangleRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes triangleSlide {
            0% { transform: translateX(0); }
            100% { transform: translateX(60px); }
        }
        .floating-shapes::before,
        .floating-shapes::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
        }
        .floating-shapes::before {
            width: 200px;
            height: 200px;
            background: #0a6fa7;
            top: 10%;
            left: 10%;
            animation: float 8s ease-in-out infinite;
        }
        .floating-shapes::after {
            width: 150px;
            height: 150px;
            background: #0891b2;
            bottom: 15%;
            right: 10%;
            animation: float 6s ease-in-out infinite reverse;
        }
        .input-focused {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(10, 111, 167, 0.1);
        }
        .input-icon {
            z-index: 10;
            pointer-events: none;
            background: transparent;
        }
        .input-icon svg {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            color: #9CA3AF;
            transition: color 0.2s ease;
        }
        .group:focus-within .input-icon svg {
            color: #0a6fa7 !important;
        }
        .input-field-with-icon {
            position: relative;
        }
        #togglePassword {
            z-index: 15 !important;
            pointer-events: auto;
        }
        #togglePassword svg {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .custom-input-focus:focus {
            border-color: #0a6fa7 !important;
            box-shadow: 0 0 0 4px rgba(10, 111, 167, 0.2) !important;
        }
        .triangle-shape-1 {
            position: absolute;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.05);
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            animation: triangleFloat1 15s ease-in-out infinite;
        }
        .triangle-shape-2 {
            position: absolute;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.08);
            clip-path: polygon(0% 0%, 100% 50%, 0% 100%);
            animation: triangleFloat2 18s ease-in-out infinite;
        }
        .triangle-shape-3 {
            position: absolute;
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.03);
            clip-path: polygon(50% 0%, 100% 100%, 0% 100%);
            animation: triangleFloat3 12s ease-in-out infinite;
        }
        @keyframes triangleFloat1 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(90deg); }
            50% { transform: translateY(-10px) rotate(180deg); }
            75% { transform: translateY(-15px) rotate(270deg); }
        }
        @keyframes triangleFloat2 {
            0%, 100% { transform: translateX(0px) rotate(0deg); }
            25% { transform: translateX(20px) rotate(120deg); }
            50% { transform: translateX(10px) rotate(240deg); }
            75% { transform: translateX(-10px) rotate(360deg); }
        }
        @keyframes triangleFloat3 {
            0%, 100% { transform: translate(0px, 0px) rotate(0deg); }
            33% { transform: translate(-15px, -10px) rotate(60deg); }
            66% { transform: translate(10px, -20px) rotate(180deg); }
        }
        .forgot-password-message {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, opacity 0.3s ease-out;
            opacity: 0;
        }
        .forgot-password-message.show {
            max-height: 300px;
            opacity: 1;
        }
    </style>
</head>
<body class="h-full gradient-bg relative overflow-hidden">
    
    <!-- Triangle Background Pattern -->
    <div class="triangle-pattern absolute inset-0 pointer-events-none"></div>
    
    <!-- Floating Background Shapes -->
    <div class="floating-shapes absolute inset-0 pointer-events-none"></div>
    
    <!-- Additional Decorative Elements -->
    <div class="absolute -top-40 -right-40 w-80 h-80 bg-white/5 rounded-full blur-3xl animate-pulse-slow"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-white/5 rounded-full blur-3xl animate-pulse-slow" style="animation-delay: 1.5s;"></div>
    
    <!-- Triangle Geometric Shapes -->
    <div class="absolute top-1/4 left-8 w-0 h-0 pointer-events-none opacity-20" 
         style="border-left: 20px solid transparent; border-right: 20px solid transparent; border-bottom: 35px solid rgba(255,255,255,0.1); animation: float 8s ease-in-out infinite;"></div>
    <div class="absolute bottom-1/3 right-12 w-0 h-0 pointer-events-none opacity-15" 
         style="border-left: 15px solid transparent; border-right: 15px solid transparent; border-top: 26px solid rgba(255,255,255,0.08); animation: float 10s ease-in-out infinite reverse; animation-delay: -2s;"></div>
    <div class="absolute top-1/2 right-1/4 w-0 h-0 pointer-events-none opacity-10" 
         style="border-left: 12px solid transparent; border-right: 12px solid transparent; border-bottom: 20px solid rgba(255,255,255,0.06); animation: float 12s ease-in-out infinite; animation-delay: -4s;"></div>
    
    <!-- Modern Triangle Shapes -->
    <div class="triangle-shape-1 top-20 left-16 pointer-events-none"></div>
    <div class="triangle-shape-2 bottom-32 right-20 pointer-events-none"></div>
    <div class="triangle-shape-3 top-1/3 right-8 pointer-events-none"></div>
    <div class="triangle-shape-1 bottom-20 left-1/4 pointer-events-none" style="animation-delay: -5s;"></div>
    <div class="triangle-shape-2 top-16 right-1/3 pointer-events-none" style="animation-delay: -8s;"></div>
    
    <div class="flex items-center justify-center min-h-screen p-4 relative z-10">
        <div class="w-full max-w-md animate-fade-in">
            
            <!-- Main Login Card -->
            <div class="glass-card rounded-3xl shadow-2xl p-8 relative overflow-hidden">
                
                <!-- Decorative gradient overlay -->
                <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent pointer-events-none rounded-3xl"></div>
                
                <div class="relative z-10">
                    <!-- Header Section -->
                    <div class="text-center mb-8">
                        <!-- Logo -->
                        <div class="mb-6 animate-slide-up">
                            <img src="assets/images/logo_blue.png" 
                                 alt="Security Awareness Platform Logo" 
                                 class="mx-auto h-16 w-auto drop-shadow-lg hover:scale-105 transition-transform duration-300"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <!-- Fallback if logo doesn't exist -->
                            <div class="mx-auto h-16 w-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg" style="display: none;">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                        </div>
                        
                        <h1 class="text-3xl font-bold text-gray-800 mb-2 animate-slide-up" style="animation-delay: 0.1s;">
                            Welcome Back
                        </h1>
                        <p class="text-gray-600 animate-slide-up" style="animation-delay: 0.2s;">
                            Sign in to your Security Awareness Platform
                        </p>
                    </div>

                    <!-- Login Form -->
                    <form id="loginForm" class="space-y-6">
                        
                        <!-- Message Area for success/error feedback -->
                        <div id="messageArea" class="hidden rounded-2xl p-4 text-sm font-medium animate-slide-up">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div id="messageIcon" class="w-5 h-5"></div>
                                </div>
                                <div id="messageText" class="flex-1"></div>
                            </div>
                        </div>

                        <!-- Forgot Password Information Message -->
                        <div id="forgotPasswordInfo" class="forgot-password-message">
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl p-5 animate-slide-down">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 animate-bounce-subtle">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-base font-semibold text-blue-900 mb-2">
                                            Password Reset Information
                                        </h3>
                                        <p class="text-sm text-blue-800 mb-3">
                                            For security reasons, password resets must be handled by your system administrator.
                                        </p>
                                        
                                        <!-- Contact Info -->
                                        <div class="bg-white/60 backdrop-blur-sm rounded-xl p-3 border border-blue-100">
                                            <p class="text-xs font-semibold text-blue-900 mb-2 flex items-center">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                Contact Administrator:
                                            </p>
                                            <div class="text-xs text-blue-800 space-y-1">
                                                <!-- <p><strong>Email:</strong> admin@company.com</p> -->
                                                <p><strong>Phone:</strong> +1 (555) 123-4567 ext. 1001</p>
                                            </div>
                                        </div>

                                        <!-- Quick Actions -->
                                        <div class="mt-3 flex space-x-2">
                                            <!-- <a href="mailto:admin@company.com?subject=Password Reset Request&body=Hello Administrator,%0D%0A%0D%0AI would like to request a password reset for my account.%0D%0A%0D%0AUsername: [Your Username]%0D%0AEmployee ID: [Your Employee ID]%0D%0A%0D%0AThank you." 
                                               class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                                Send Email
                                            </a> -->
                                            <button type="button" id="closeForgotInfo" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Username Input -->
                        <div class="animate-slide-up" style="animation-delay: 0.3s;">
                            <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                                Username
                            </label>
                            <div class="relative group input-field-with-icon">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center input-icon">
                                    <svg class="h-5 w-5 text-gray-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <input type="text" id="username" name="username" required 
                                       class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-2xl transition-all duration-200 bg-white/50 backdrop-blur-sm placeholder-gray-400 custom-input-focus"
                                       placeholder="e.g., firstname.lastname"
                                       onfocus="this.classList.add('input-focused')"
                                       onblur="this.classList.remove('input-focused')">
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="animate-slide-up" style="animation-delay: 0.4s;">
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Password
                            </label>
                            <div class="relative group input-field-with-icon">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center input-icon">
                                    <svg class="h-5 w-5 text-gray-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <input type="password" id="password" name="password" required
                                       class="w-full pl-12 pr-12 py-4 border-2 border-gray-200 rounded-2xl transition-all duration-200 bg-white/50 backdrop-blur-sm placeholder-gray-400 custom-input-focus"
                                       placeholder="Enter your password"
                                       onfocus="this.classList.add('input-focused')"
                                       onblur="this.classList.remove('input-focused')">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-4 flex items-center z-10">
                                    <svg id="eyeIcon" class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="animate-slide-up pt-6" style="animation-delay: 0.5s;">
                            <button type="submit" id="loginButton" 
                                    class="w-full text-white font-bold py-4 px-6 rounded-2xl focus:outline-none focus:ring-4 transition-all duration-200 flex items-center justify-center disabled:opacity-75 disabled:cursor-not-allowed shadow-lg hover:shadow-xl transform hover:scale-105 active:scale-95"
                                    style="background: linear-gradient(135deg, #0a6fa7 0%, #0891b2 100%); border: none; box-shadow: 0 4px 15px rgba(10, 111, 167, 0.3);"
                                    onmouseover="this.style.background='linear-gradient(135deg, #085a8a 0%, #0670a3 100%)'; this.style.boxShadow='0 6px 20px rgba(10, 111, 167, 0.4)'"
                                    onmouseout="this.style.background='linear-gradient(135deg, #0a6fa7 0%, #0891b2 100%)'; this.style.boxShadow='0 4px 15px rgba(10, 111, 167, 0.3)'"
                                    onfocus="this.style.boxShadow='0 0 0 4px rgba(10, 111, 167, 0.2)'">
                                <span id="buttonText">Sign In</span>
                                <div id="buttonLoader" class="hidden ml-3">
                                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Additional Links -->
                    <div class="mt-8 text-center animate-slide-up" style="animation-delay: 0.6s;">
                        <div class="flex items-center justify-center space-x-4 text-sm text-gray-500">
                            <a href="#" id="forgotPasswordLink" class="hover:text-blue-600 transition-colors font-medium flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Forgot Password?
                            </a>
                            <!-- <span class="text-gray-300">|</span> -->
                            <!-- <a href="#" class="hover:text-blue-600 transition-colors font-medium flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Need Help?
                            </a> -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-center mt-8 animate-fade-in" style="animation-delay: 0.7s;">
                <p class="text-white/80 text-sm backdrop-blur-sm bg-white/10 rounded-full px-6 py-2 inline-block">
                    © 2025 Security Awareness Platform. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');
            const messageArea = document.getElementById('messageArea');
            const messageText = document.getElementById('messageText');
            const messageIcon = document.getElementById('messageIcon');
            const loginButton = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const buttonLoader = document.getElementById('buttonLoader');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            // Forgot Password Elements
            const forgotPasswordLink = document.getElementById('forgotPasswordLink');
            const forgotPasswordInfo = document.getElementById('forgotPasswordInfo');
            const closeForgotInfo = document.getElementById('closeForgotInfo');

            // Forgot Password functionality
            forgotPasswordLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (forgotPasswordInfo.classList.contains('show')) {
                    // Hide the message
                    forgotPasswordInfo.classList.remove('show');
                    forgotPasswordLink.innerHTML = `
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Forgot Password?
                    `;
                } else {
                    // Show the message
                    forgotPasswordInfo.classList.add('show');
                    forgotPasswordLink.innerHTML = `
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                        </svg>
                        Hide Info
                    `;
                    
                    // Hide any existing error messages
                    messageArea.classList.add('hidden');
                }
            });

            // Close forgot password info
            closeForgotInfo.addEventListener('click', function() {
                forgotPasswordInfo.classList.remove('show');
                forgotPasswordLink.innerHTML = `
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Forgot Password?
                `;
            });

            // Password visibility toggle
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'text') {
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                    `;
                } else {
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    `;
                }
            });

            // Form submission
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();

                // Hide forgot password info when submitting
                if (forgotPasswordInfo.classList.contains('show')) {
                    forgotPasswordInfo.classList.remove('show');
                    forgotPasswordLink.innerHTML = `
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Forgot Password?
                    `;
                }

                // Show loading state
                loginButton.disabled = true;
                buttonText.textContent = 'Signing In...';
                buttonLoader.classList.remove('hidden');
                messageArea.classList.add('hidden');

                const formData = new FormData(loginForm);

                // Send data to the server
                fetch('api/auth/user_login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        return response.text().then(text => { 
                            throw new Error("Server returned a non-JSON response:\n" + text);
                        });
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Handle Success
                        messageText.textContent = data.message;
                        messageIcon.innerHTML = `
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        `;
                        messageArea.className = 'rounded-2xl p-4 text-sm font-medium animate-slide-up bg-green-50 border border-green-200 text-green-800';
                        messageArea.classList.remove('hidden');

                        // Keep loading state and redirect
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 1000);

                    } else {
                        // Handle Failure
                        messageText.textContent = data.message || 'An unknown error occurred.';
                        messageIcon.innerHTML = `
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        `;
                        messageArea.className = 'rounded-2xl p-4 text-sm font-medium animate-slide-up bg-red-50 border border-red-200 text-red-800';
                        messageArea.classList.remove('hidden');
                        
                        // Reset button state
                        resetButtonState();
                    }
                })
                .catch(error => {
                    console.error('Login Fetch Error:', error);
                    messageText.textContent = 'A network or server error occurred. Please try again.';
                    messageIcon.innerHTML = `
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    `;
                    messageArea.className = 'rounded-2xl p-4 text-sm font-medium animate-slide-up bg-red-50 border border-red-200 text-red-800';
                    messageArea.classList.remove('hidden');

                    resetButtonState();
                });
            });

            function resetButtonState() {
                loginButton.disabled = false;
                buttonText.textContent = 'Sign In';
                buttonLoader.classList.add('hidden');
            }

            // Add floating animation to logo on hover
            const logo = document.querySelector('img[alt*="Logo"]');
            if (logo) {
                logo.addEventListener('mouseenter', function() {
                    this.style.animation = 'float 2s ease-in-out infinite';
                });
                logo.addEventListener('mouseleave', function() {
                    this.style.animation = '';
                });
            }
        });
    </script>

</body>
</html>
<?php
// Add this PHP code at the very top of your HTML file
session_start();

// Initialize variables to avoid undefined variable warnings
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Both password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 12) {
        $error = 'Password must be at least 12 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[\W_]/', $new_password)) {
        $error = 'Password must contain at least one special character.';
    } else {
        // Password validation passed - update the password
        try {
            // Database connection - Use your existing connection
            require_once 'includes/db_connect.php';
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Debug: Check if user_id exists in session
            if (!isset($_SESSION['user_id'])) {
                throw new Exception("User ID not found in session");
            }
            
            // Update the password and clear the password reset flag
            // Using your exact database column names
            $stmt = $pdo->prepare("UPDATE users SET 
                password = ?, 
                password_reset_required = 0, 
                password_last_changed = CURRENT_TIMESTAMP,
                failed_login_attempts = 0
                WHERE id = ?");
            
            $result = $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            // Check if the query executed successfully
            if (!$result) {
                throw new Exception("Database query failed: " . implode(", ", $stmt->errorInfo()));
            }
            
            // Verify the update affected a row
            if ($stmt->rowCount() === 0) {
                throw new Exception("No user found with ID: " . $_SESSION['user_id']);
            }
            
            // CRITICAL: Clear ALL possible session flags that might trigger redirect
            // This is the key one your auth_check.php is looking for:
            unset($_SESSION['password_reset_required']);
            
            // Clear other possible flags too
            unset($_SESSION['force_password_change']);
            unset($_SESSION['password_expired']);
            unset($_SESSION['temp_password']);
            unset($_SESSION['first_login']);
            unset($_SESSION['password_change_required']);
            unset($_SESSION['must_change_password']);
            
            // Set positive flags
            $_SESSION['password_changed'] = true;
            $_SESSION['last_password_change'] = time();
            $_SESSION['password_change_completed'] = true;
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $message = 'Password updated successfully!';
            
            // Optional: Log the successful password change
            error_log("Password changed successfully for user ID: " . $_SESSION['user_id']);
            
            // DEBUG: Log session state after password change
            error_log("Session after password change: " . print_r($_SESSION, true));
            
        } catch (Exception $e) {
            $error = 'Failed to update password. Please try again.';
            // Log the actual error for debugging
            error_log("Password update failed: " . $e->getMessage());
            error_log("User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
            error_log("Session data: " . print_r($_SESSION, true));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Your Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        'accent': '#0891b2'
                    }
                }
            }
        }
    </script>
    
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a6fa7 0%, #0891b2 100%);
            min-height: 100vh;
        }
        
        .glass-morphism {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #0a6fa7 0%, #0891b2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .input-focus {
            transition: all 0.3s ease;
        }
        
        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(10, 111, 167, 0.25);
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #0a6fa7 0%, #0891b2 100%);
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px -10px rgba(10, 111, 167, 0.5);
        }
        
        .check-animation {
            animation: checkmark 0.3s ease-in-out;
        }
        
        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex min-h-screen items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <!-- Logo and Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-lg mb-4">
                    <svg class="w-12 h-12 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
                <h2 class="text-4xl font-bold text-white mb-2">
                    Secure Your Account
                </h2>
                <p class="text-white/80">
                    Create a strong password to protect your data
                </p>
            </div>

            <!-- Main Card -->
            <div class="glass-morphism rounded-3xl shadow-2xl p-8 relative overflow-hidden">
                
                <!-- Decorative gradient overlay -->
                <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent pointer-events-none rounded-3xl"></div>
                
                <div class="relative z-10">
                <!-- Success Message -->
                <?php if (!empty($message)): ?>
                <div id="successMessage">
                    <div class="text-center p-6">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Password Updated Successfully!</h3>
                        <p class="text-gray-600">Redirecting to your dashboard...</p>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-primary to-accent h-2 rounded-full animate-pulse" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                    <script>
                        // Automatic redirect after successful password update
                        setTimeout(function() {
                            // Modify this redirect URL as needed
                            window.location.href = 'dashboard.php';
                        }, 2000);
                    </script>
                </div>
                <?php else: ?>

                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                <div id="errorMessage" class="mb-6">
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-red-700 text-sm font-medium"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dynamic Alert Container for JavaScript alerts -->
                <div id="alertContainer" class="hidden"></div>

                <!-- Password Form -->
                <form method="POST" action="" class="space-y-6" id="passwordForm">
                    <div>
                        <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">
                            New Password
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                name="new_password" 
                                id="new_password" 
                                required 
                                class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-4 focus:ring-primary/20 focus:outline-none"
                                placeholder="Enter your new password"
                                onkeyup="checkPasswordStrength()"
                            >
                            <button type="button" onclick="togglePassword('new_password')" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                        <!-- Password Strength Indicator -->
                        <div class="mt-2">
                            <div class="password-strength bg-gray-200 w-full" id="strengthBar"></div>
                            <p class="text-xs mt-1 text-gray-500" id="strengthText"></p>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                name="confirm_password" 
                                id="confirm_password" 
                                required 
                                class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-4 focus:ring-primary/20 focus:outline-none"
                                placeholder="Confirm your new password"
                                onkeyup="checkPasswordMatch()"
                            >
                            <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                            <span id="matchIndicator" class="absolute right-12 top-3.5 hidden">
                                <svg class="w-5 h-5 text-green-500 check-animation" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <!-- Requirements Checklist -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs font-semibold text-gray-700 mb-3">Password Requirements:</p>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center" id="req-length">
                                <svg class="w-4 h-4 mr-2 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-600">12+ characters</span>
                            </div>
                            <div class="flex items-center" id="req-upper">
                                <svg class="w-4 h-4 mr-2 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-600">Uppercase letter</span>
                            </div>
                            <div class="flex items-center" id="req-lower">
                                <svg class="w-4 h-4 mr-2 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-600">Lowercase letter</span>
                            </div>
                            <div class="flex items-center" id="req-number">
                                <svg class="w-4 h-4 mr-2 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-600">Number</span>
                            </div>
                            <div class="flex items-center" id="req-special">
                                <svg class="w-4 h-4 mr-2 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-600">Special character</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-gradient w-full py-3 px-4 rounded-xl text-white font-semibold text-sm focus:outline-none focus:ring-4 focus:ring-primary/20">
                        Update Password
                    </button>
                </form>
                <?php endif; ?>

                <!-- Security Tips -->
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <div class="flex items-start space-x-2">
                        <svg class="w-5 h-5 text-primary mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <p class="text-xs text-gray-500">
                            <span class="font-semibold">Security tip:</span> Use a unique password that you haven't used on other sites. Consider using a password manager.
                        </p>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            const requirements = {
                length: password.length >= 12,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[\W_]/.test(password)
            };

            // Update requirement indicators
            updateRequirement('req-length', requirements.length);
            updateRequirement('req-upper', requirements.upper);
            updateRequirement('req-lower', requirements.lower);
            updateRequirement('req-number', requirements.number);
            updateRequirement('req-special', requirements.special);

            // Calculate strength
            Object.values(requirements).forEach(met => {
                if (met) strength++;
            });

            // Update strength bar
            strengthBar.style.width = (strength * 20) + '%';
            
            if (strength === 0) {
                strengthBar.className = 'password-strength bg-gray-200';
                strengthText.textContent = '';
            } else if (strength <= 2) {
                strengthBar.className = 'password-strength bg-red-400';
                strengthText.textContent = 'Weak password';
                strengthText.className = 'text-xs mt-1 text-red-500';
            } else if (strength <= 4) {
                strengthBar.className = 'password-strength bg-yellow-400';
                strengthText.textContent = 'Moderate password';
                strengthText.className = 'text-xs mt-1 text-yellow-600';
            } else {
                strengthBar.className = 'password-strength bg-green-400';
                strengthText.textContent = 'Strong password';
                strengthText.className = 'text-xs mt-1 text-green-600';
            }

            // Check if password meets all requirements when user stops typing
            clearTimeout(window.passwordAlertTimeout);
            window.passwordAlertTimeout = setTimeout(() => {
                if (password.length > 0) {
                    validatePasswordRequirements();
                }
            }, 1000);
        }

        function updateRequirement(reqId, isMet) {
            const element = document.getElementById(reqId);
            const icon = element.querySelector('svg');
            const text = element.querySelector('span');
            
            if (isMet) {
                icon.classList.remove('text-gray-300');
                icon.classList.add('text-green-500', 'check-animation');
                text.classList.remove('text-gray-600');
                text.classList.add('text-green-700');
            } else {
                icon.classList.remove('text-green-500');
                icon.classList.add('text-gray-300');
                text.classList.remove('text-green-700');
                text.classList.add('text-gray-600');
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchIndicator = document.getElementById('matchIndicator');
            
            if (confirmPassword && password === confirmPassword) {
                matchIndicator.classList.remove('hidden');
            } else {
                matchIndicator.classList.add('hidden');
            }
        }

        function validatePasswordRequirements() {
            const password = document.getElementById('new_password').value;
            const passwordField = document.getElementById('new_password');
            
            const requirements = {
                length: password.length >= 12,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[\W_]/.test(password)
            };

            const allMet = Object.values(requirements).every(req => req === true);
            
            if (!allMet && password.length > 0) {
                let missingReqs = [];
                if (!requirements.length) missingReqs.push('at least 12 characters');
                if (!requirements.upper) missingReqs.push('an uppercase letter');
                if (!requirements.lower) missingReqs.push('a lowercase letter');
                if (!requirements.number) missingReqs.push('a number');
                if (!requirements.special) missingReqs.push('a special character');
                
                showAlert('Your password still needs ' + missingReqs.join(', ') + '.', 'warning');
                passwordField.classList.add('border-yellow-500');
                passwordField.classList.remove('border-gray-200', 'border-green-500');
            } else if (allMet) {
                passwordField.classList.add('border-green-500');
                passwordField.classList.remove('border-gray-200', 'border-yellow-500');
                hideAlert();
            }
        }

        function showAlert(message, type = 'error') {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'warning' ? 'bg-yellow-50 border-yellow-500' : 'bg-red-50 border-red-500';
            const iconColor = type === 'warning' ? 'text-yellow-500' : 'text-red-500';
            const textColor = type === 'warning' ? 'text-yellow-700' : 'text-red-700';
            
            alertContainer.innerHTML = `
                <div class="${alertClass} border-l-4 p-4 rounded-xl mb-4 animate-pulse">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 ${iconColor} mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <p class="${textColor} text-sm font-medium">${message}</p>
                    </div>
                </div>
            `;
            alertContainer.classList.remove('hidden');
        }

        function hideAlert() {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.classList.add('hidden');
        }

        // Form submission validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('passwordForm');
            
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                // Check all requirements
                const requirements = {
                    length: password.length >= 12,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[\W_]/.test(password)
                };

                const allMet = Object.values(requirements).every(req => req === true);
                
                // Prevent submission if requirements not met
                if (!allMet) {
                    e.preventDefault();
                    let missingReqs = [];
                    if (!requirements.length) missingReqs.push('at least 12 characters');
                    if (!requirements.upper) missingReqs.push('an uppercase letter');
                    if (!requirements.lower) missingReqs.push('a lowercase letter');
                    if (!requirements.number) missingReqs.push('a number');
                    if (!requirements.special) missingReqs.push('a special character');
                    
                    showAlert('Password requirements not met! Your password needs ' + missingReqs.join(', ') + '.', 'error');
                    document.getElementById('new_password').focus();
                    return false;
                }
                
                // Check if passwords match
                if (password !== confirmPassword) {
                    e.preventDefault();
                    showAlert('Passwords do not match. Please make sure both passwords are identical.', 'error');
                    document.getElementById('confirm_password').focus();
                    return false;
                }
            });

            // Also validate when user leaves the password field
            document.getElementById('new_password').addEventListener('blur', function() {
                if (this.value.length > 0) {
                    validatePasswordRequirements();
                }
            });
        });
    </script>
</body>
</html>
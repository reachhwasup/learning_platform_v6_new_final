<?php
$page_title = 'My Profile';
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];

try {
    // Fetch current user data
    $stmt_user = $pdo->prepare(
        "SELECT u.first_name, u.last_name, u.username, u.staff_id, u.gender, u.position, u.phone_number, u.profile_picture, d.name as department_name 
         FROM users u
         LEFT JOIN departments d ON u.department_id = d.id
         WHERE u.id = ?"
    );
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch();

    if (!$user) {
        // If user somehow doesn't exist, log them out.
        redirect('api/auth/logout.php');
    }

    // Check if the user has passed the final assessment to display the badge
    $stmt_badge = $pdo->prepare("SELECT id FROM final_assessments WHERE user_id = ? AND status = 'passed' LIMIT 1");
    $stmt_badge->execute([$user_id]);
    $has_passed = $stmt_badge->fetch();

    // --- Determine the correct profile picture path ---
    $profile_picture_filename = $user['profile_picture'] ?? null;
    // Check if a specific user picture exists and is not the default name
    if ($profile_picture_filename && $profile_picture_filename !== 'default_avatar.jpg' && file_exists('uploads/profile_pictures/' . $profile_picture_filename)) {
        $profile_picture_path = 'uploads/profile_pictures/' . htmlspecialchars($profile_picture_filename);
    } else {
        // Otherwise, use the default avatar
        $profile_picture_path = 'assets/images/default_avatar.jpg';
    }

} catch (PDOException $e) {
    error_log("Profile Page Error: " . $e->getMessage());
    die("An error occurred while loading your profile.");
}

require_once 'includes/header.php';
?>

<style>
    /* Triangle Pattern Animation */
    .triangle-pattern {
        background-image: 
            linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.1) 75%, rgba(255,255,255,0.1)),
            linear-gradient(-45deg, rgba(255,255,255,0.1) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.1) 75%, rgba(255,255,255,0.1));
        background-size: 60px 60px;
        background-position: 0 0, 30px 30px;
        animation: triangleMove 20s linear infinite;
    }

    .triangle-pattern::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: conic-gradient(from 0deg at 50% 50%, 
            rgba(255,255,255,0.08) 0deg, 
            transparent 90deg, 
            rgba(255,255,255,0.08) 180deg, 
            transparent 270deg, 
            rgba(255,255,255,0.08) 360deg);
        background-size: 40px 40px;
        animation: triangleRotate 30s linear infinite;
    }

    .triangle-pattern::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: repeating-linear-gradient(
            90deg,
            transparent,
            transparent 35px,
            rgba(255,255,255,0.03) 35px,
            rgba(255,255,255,0.03) 70px
        );
        background-size: 80px 80px;
        animation: triangleSlide 25s linear infinite;
    }

    @keyframes triangleMove {
        0% { background-position: 0 0, 30px 30px; }
        100% { background-position: 60px 60px, 90px 90px; }
    }

    @keyframes triangleRotate {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes triangleSlide {
        0% { transform: translateX(0); }
        100% { transform: translateX(80px); }
    }
</style>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Profile Header Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            <!-- Cover Background -->
            <div class="h-32 sm:h-40 bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 relative">
                <!-- Triangle Pattern -->
                <div class="triangle-pattern absolute inset-0"></div>
                <div class="absolute inset-0 bg-black/10"></div>
            </div>
            
            <!-- Profile Info -->
            <div class="relative px-6 pb-8 -mt-16">
                <div class="flex flex-col sm:flex-row items-center sm:items-end space-y-4 sm:space-y-0 sm:space-x-6">
                    <!-- Profile Picture -->
                    <div class="relative group">
                        <img id="profile-pic-display" 
                             src="<?= $profile_picture_path ?>" 
                             alt="Profile Picture" 
                             class="w-32 h-32 rounded-full border-4 border-white shadow-xl object-cover bg-white group-hover:scale-105 transition-transform duration-300" 
                             onerror="this.src='assets/images/default_avatar.jpg'">
                        
                        <!-- Upload Overlay -->
                        <form id="picture-form" class="absolute inset-0">
                            <label for="profile_picture" class="absolute inset-0 bg-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </label>
                            <input type="file" name="profile_picture" id="profile_picture" class="hidden" accept="image/*">
                        </form>
                        
                        <!-- Online Status -->
                        <div class="absolute bottom-2 right-2 w-6 h-6 bg-green-400 border-3 border-white rounded-full shadow-lg"></div>
                    </div>
                    
                    <!-- User Info -->
                    <div class="text-center sm:text-left flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 mb-2">
                            <h1 class="text-2xl sm:text-3xl font-bold text-white">
                                <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                            </h1>
                            <?php if ($has_passed): ?>
                                <div class="inline-flex items-center bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium mt-2 sm:mt-0">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a.75.75 0 00-1.06-1.06L9 10.94l-1.72-1.72a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.06 0l4.25-4.25z" clip-rule="evenodd" />
                                    </svg>
                                    Certified
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-base text-gray-700 font-semibold mb-1"><?= htmlspecialchars($user['position'] ?? 'Team Member') ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($user['department_name'] ?? 'No Department') ?></p>
                        
                        <!-- Status Message -->
                        <div id="picture-feedback" class="mt-3 text-sm"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <div class="flex items-center mb-8">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-3">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">Personal Information</h3>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">First Name</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['first_name'] ?? 'Not provided') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Username</label>
                                <p class="text-lg text-gray-900 font-medium">
                                    <span class="bg-gray-100 px-3 py-1 rounded-lg font-mono">@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Department</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['department_name'] ?? 'Not assigned') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Gender</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['gender'] ?? 'Not specified') ?></p>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Last Name</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['last_name'] ?? 'Not provided') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Staff ID</label>
                                <p class="text-lg text-gray-900 font-medium">
                                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-lg font-mono"><?= htmlspecialchars($user['staff_id'] ?? 'N/A') ?></span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Phone Number</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['phone_number'] ?? 'Not provided') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Position</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['position'] ?? 'Not specified') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pictureInput = document.getElementById('profile_picture');

    // Handle profile picture change automatically on file selection
    pictureInput.addEventListener('change', function() {
        if (pictureInput.files.length > 0) {
            const formData = new FormData();
            formData.append('profile_picture', pictureInput.files[0]);
            formData.append('action', 'change_picture');
            submitFormData(formData, 'picture-feedback', (data) => {
                // Update image on success
                if (data.success && data.new_path) {
                    document.getElementById('profile-pic-display').src = data.new_path + '?t=' + new Date().getTime();
                }
            });
        }
    });

    function submitFormData(formData, feedbackDivId, callback) {
        const feedbackDiv = document.getElementById(feedbackDivId);
        feedbackDiv.innerHTML = `
            <div class="flex items-center justify-center space-x-2 text-blue-600">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Uploading...</span>
            </div>
        `;
        feedbackDiv.className = 'mt-3 text-sm animate-fade-in';

        // This path assumes the API endpoint is in api/user/
        fetch('api/user/update_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                feedbackDiv.innerHTML = `
                    <div class="flex items-center justify-center space-x-2 text-green-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>${data.message}</span>
                    </div>
                `;
                feedbackDiv.className = 'mt-3 text-sm animate-fade-in';
            } else {
                feedbackDiv.innerHTML = `
                    <div class="flex items-center justify-center space-x-2 text-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span>${data.message}</span>
                    </div>
                `;
                feedbackDiv.className = 'mt-3 text-sm animate-fade-in';
            }
            
            if (data.success && callback) {
                callback(data);
            }
            
            // Clear feedback after 3 seconds
            setTimeout(() => {
                feedbackDiv.innerHTML = '';
            }, 3000);
        })
        .catch(error => {
            console.error('Error:', error);
            feedbackDiv.innerHTML = `
                <div class="flex items-center justify-center space-x-2 text-red-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Upload failed. Please try again.</span>
                </div>
            `;
            feedbackDiv.className = 'mt-3 text-sm animate-fade-in';
            
            // Clear feedback after 3 seconds
            setTimeout(() => {
                feedbackDiv.innerHTML = '';
            }, 3000);
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
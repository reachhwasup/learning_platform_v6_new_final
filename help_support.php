<?php
$page_title = 'Help & Support';
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// FAQ data - you can move this to database later
$faqs = [
    [
        'category' => 'Getting Started',
        'questions' => [
            [
                'question' => 'How do I access my learning materials?',
                'answer' => 'Navigate to "Learning Materials" from the main menu. You can download PDF materials for modules you have completed.'
            ],
            [
                'question' => 'How do I take the final assessment?',
                'answer' => 'Complete all required modules first. Once eligible, you\'ll find the "Final Assessment" option in the assessment section of your dashboard.'
            ],
            [
                'question' => 'Can I retake the final assessment?',
                'answer' => 'Yes, if you don\'t pass on your first attempt, you can retake the assessment. Check with your administrator for specific retake policies.'
            ]
        ]
    ],
    [
        'category' => 'Technical Issues',
        'questions' => [
            [
                'question' => 'I can\'t log into my account',
                'answer' => 'Ensure you\'re using the correct username and password. If you\'ve forgotten your password, contact your IT administrator for a reset.'
            ],
            [
                'question' => 'The website is loading slowly',
                'answer' => 'This might be due to network connectivity. Try refreshing the page, clearing your browser cache, or switching to a different browser.'
            ],
            [
                'question' => 'I\'m having trouble uploading my profile picture',
                'answer' => 'Ensure your image is in JPG, PNG format and under 5MB. If problems persist, try using a different browser or contact support.'
            ]
        ]
    ],
    [
        'category' => 'Account Management',
        'questions' => [
            [
                'question' => 'How do I update my profile information?',
                'answer' => 'Go to "My Profile" and click "Edit Profile". You can update your personal information and profile picture there.'
            ],
            [
                'question' => 'How do I change my password?',
                'answer' => 'Password changes must be done through your IT administrator or the company\'s main authentication system.'
            ],
            [
                'question' => 'Who can see my learning progress?',
                'answer' => 'Your direct supervisor and HR department can view your learning progress and completion status.'
            ]
        ]
    ]
];

require_once 'includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        
        <!-- Hero Section -->
        <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 rounded-2xl shadow-xl text-white p-8 mb-8">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-6">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Help & Support</h1>
                <p class="text-xl text-blue-100 max-w-2xl mx-auto">
                    Get the help you need to make the most of your security awareness training experience
                </p>
            </div>
        </div>

        <!-- Quick Help Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Quick Start Guide</h3>
                <p class="text-gray-600 mb-4">New to the platform? Learn how to navigate and complete your training efficiently.</p>
                <button onclick="scrollToSection('getting-started')" class="text-blue-600 hover:text-blue-700 font-semibold flex items-center">
                    Get Started
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Live Chat Support</h3>
                <p class="text-gray-600 mb-4">Get instant help from our support team during business hours (8 AM - 5 PM).</p>
                <button onclick="openChatSupport()" class="text-blue-600 hover:text-blue-700 font-semibold flex items-center">
                    Start Chat
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-shadow">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Email Support</h3>
                <p class="text-gray-600 mb-4">Send us an email for detailed inquiries. We typically respond within 24 hours.</p>
                <a href="mailto:infosec@apdbank.com.kh" class="text-blue-600 hover:text-blue-700 font-semibold flex items-center">
                    Send Email
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Search FAQ -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-8">
            <div class="max-w-xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-900 text-center mb-6">Search Frequently Asked Questions</h2>
                <div class="relative">
                    <input type="text" id="faq-search" placeholder="Search for answers..." 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent pl-10">
                    <svg class="absolute left-3 top-3.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- FAQ Sections -->
        <div class="space-y-8" id="faq-container">
            <?php foreach ($faqs as $index => $category): ?>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden" id="<?= strtolower(str_replace(' ', '-', $category['category'])) ?>">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <?php
                            $icons = [
                                '<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>',
                                '<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>',
                                '<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
                                '<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'
                            ];
                            echo $icons[$index] ?? $icons[0];
                            ?>
                        </div>
                        <?= htmlspecialchars($category['category']) ?>
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($category['questions'] as $qIndex => $qa): ?>
                        <div class="faq-item border border-gray-200 rounded-lg">
                            <button class="faq-question w-full text-left p-4 hover:bg-gray-50 transition-colors flex justify-between items-center" 
                                    onclick="toggleFAQ(this)">
                                <span class="font-semibold text-gray-900 pr-4"><?= htmlspecialchars($qa['question']) ?></span>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="faq-answer hidden px-4 pb-4">
                                <p class="text-gray-600 leading-relaxed"><?= htmlspecialchars($qa['answer']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Contact Information -->
        <div class="mt-12 bg-white rounded-xl shadow-lg border border-gray-200 p-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Still Need Help?</h2>
                <p class="text-lg text-gray-600">Our support team is here to assist you</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Phone Support</h3>
                    <p class="text-gray-600 mb-2">Call us during business hours</p>
                    <p class="text-lg font-semibold text-blue-600">+855 23 225 333</p>
                    <p class="text-sm text-gray-500">Monday - Friday: 8:00 AM - 5:00 PM</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Email Support</h3>
                    <p class="text-gray-600 mb-2">Send us a detailed message</p>
                    <p class="text-lg font-semibold text-green-600">infosec@apdbank.com.kh</p>
                    <p class="text-sm text-gray-500">Response within 24 hours</p>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="mt-8 bg-green-50 border border-green-200 rounded-xl p-6">
            <div class="flex items-center justify-center">
                <div class="w-3 h-3 bg-green-400 rounded-full mr-3 animate-pulse"></div>
                <span class="text-green-800 font-semibold">All systems operational</span>
                <span class="text-green-600 ml-2">• Last updated: <?= date('F j, Y g:i A') ?></span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('faq-search');
    const faqItems = document.querySelectorAll('.faq-item');
    
    // FAQ Search functionality
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question span').textContent.toLowerCase();
            const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();
            
            if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                item.style.display = 'block';
                // Highlight matching text if search term exists
                if (searchTerm.length > 0) {
                    item.classList.add('ring-2', 'ring-blue-200');
                } else {
                    item.classList.remove('ring-2', 'ring-blue-200');
                }
            } else {
                item.style.display = 'none';
                item.classList.remove('ring-2', 'ring-blue-200');
            }
        });
        
        // Show/hide category sections based on visible items
        document.querySelectorAll('#faq-container > div').forEach(section => {
            const visibleItems = section.querySelectorAll('.faq-item[style="display: block"], .faq-item:not([style*="display: none"])');
            if (searchTerm.length > 0 && visibleItems.length === 0) {
                section.style.display = 'none';
            } else {
                section.style.display = 'block';
            }
        });
    });
});

function toggleFAQ(button) {
    const answer = button.nextElementSibling;
    const icon = button.querySelector('svg');
    
    if (answer.classList.contains('hidden')) {
        answer.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
        button.classList.add('bg-gray-50');
    } else {
        answer.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
        button.classList.remove('bg-gray-50');
    }
}

function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
}

function openChatSupport() {
    // Implement your chat support integration here
    alert('Chat support feature will be implemented with your preferred chat solution (e.g., Intercom, Zendesk Chat, etc.)');
}
</script>

<?php require_once 'includes/footer.php'; ?>
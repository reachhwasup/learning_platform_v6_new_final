/**
 * Custom Video Player Logic
 *
 * This script handles the functionality for the learning video player.
 * - Disables seeking to ensure users watch the content.
 * - Tracks video completion to unlock the quiz.
 * - Provides custom play/pause controls.
 */
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('learning-video');
    const playPauseBtn = document.getElementById('play-pause-btn');
    const quizContainer = document.getElementById('quiz-container');

    if (!video) {
        return; // Exit if no video element is found on the page
    }

    // --- Prevent Seeking ---
    let lastPlayedTime = 0;
    video.addEventListener('timeupdate', () => {
        // If the user tries to jump forward more than a small amount, reset them.
        if (!video.seeking && (video.currentTime > lastPlayedTime + 1)) {
            video.currentTime = lastPlayedTime;
        }
        lastPlayedTime = video.currentTime;
    });

    // Disable the default controls visually
    video.controls = false;

    // --- Custom Play/Pause Button ---
    playPauseBtn.addEventListener('click', () => {
        if (video.paused || video.ended) {
            video.play();
        } else {
            video.pause();
        }
    });

    // Update button text based on video state
    video.addEventListener('play', () => {
        playPauseBtn.textContent = 'Pause';
    });
    video.addEventListener('pause', () => {
        playPauseBtn.textContent = 'Play';
    });


    // --- Handle Video Completion ---
    let progressTracked = false; // Flag to prevent multiple API calls
    video.addEventListener('ended', () => {
        console.log('Video has ended.');
        playPauseBtn.textContent = 'Replay';

        if (progressTracked) {
             // If already tracked, just ensure quiz is visible
            quizContainer.classList.remove('hidden');
            return;
        }

        const moduleId = video.dataset.moduleId;
        if (!moduleId) {
            console.error('Module ID not found on video element.');
            return;
        }

        // Send request to server to mark module as complete
        fetch('api/learning/track_progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ module_id: moduleId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Progress tracked successfully.');
                progressTracked = true;
                // Show the quiz
                if (quizContainer) {
                    quizContainer.classList.remove('hidden');
                    // Scroll to the quiz smoothly
                    quizContainer.scrollIntoView({ behavior: 'smooth' });
                }
            } else {
                console.error('Failed to track progress:', data.message);
                alert('An error occurred while saving your progress. Please refresh the page.');
            }
        })
        .catch(error => {
            console.error('Error tracking progress:', error);
            alert('A network error occurred. Please check your connection and refresh the page.');
        });
    });

    // --- Quiz Form Submission ---
    const quizForm = document.getElementById('quiz-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(quizForm);
            const resultDiv = document.getElementById('quiz-result');
            
            resultDiv.textContent = 'Submitting...';
            resultDiv.className = 'mt-6 text-center text-blue-600';

            fetch('api/learning/submit_quiz.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.textContent = 'Quiz submitted successfully! You will be redirected to the dashboard.';
                    resultDiv.className = 'mt-6 text-center text-green-600';
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    resultDiv.textContent = data.message || 'Failed to submit quiz. Please try again.';
                    resultDiv.className = 'mt-6 text-center text-red-600';
                }
            })
            .catch(error => {
                console.error('Quiz submission error:', error);
                resultDiv.textContent = 'A server error occurred.';
                resultDiv.className = 'mt-6 text-center text-red-600';
            });
        });
    }
});

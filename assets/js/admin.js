/**
 * Admin Panel JavaScript
 *
 * Handles dynamic interactions for the admin interface, such as modals and AJAX calls for CRUD operations.
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- Module Management ---
    const moduleModal = document.getElementById('module-modal');
    if (moduleModal) {
        const addBtn = document.getElementById('add-module-btn');
        const cancelBtn = document.getElementById('cancel-btn');
        const closeBtn = document.getElementById('close-modal');
        const form = document.getElementById('module-form');
        const feedbackDiv = document.getElementById('form-feedback');

        const openModal = () => moduleModal.classList.remove('hidden');
        const closeModal = () => {
            moduleModal.classList.add('hidden');
            form.reset();
            if (feedbackDiv) {
                feedbackDiv.classList.add('hidden');
                feedbackDiv.classList.remove('text-blue-600', 'bg-blue-50', 'border-blue-400');
                feedbackDiv.classList.add('text-red-600', 'bg-red-50', 'border-red-400');
                feedbackDiv.textContent = '';
            }
        };

        addBtn.addEventListener('click', () => {
            form.reset();
            document.getElementById('modal-title').textContent = 'Add New Module';
            document.getElementById('form-action').value = 'add_module';
            document.getElementById('module_id').value = '';
            // Clear video duration field
            document.getElementById('video_duration').value = '';
            // Make video required for adding
            document.getElementById('video_file').required = true;
            // Show video section
            const videoSection = document.getElementById('video-section');
            if (videoSection) videoSection.style.display = 'block';
            // Remove current video info if exists
            const videoInfo = document.getElementById('current-video-info');
            if (videoInfo) videoInfo.remove();
            console.log('Opening add module modal, action set to:', document.getElementById('form-action').value);
            openModal();
        });

        cancelBtn.addEventListener('click', closeModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            console.log('Form submitted');
            const formData = new FormData(form);
            
            // Log form data for debugging
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // Check if video file is being uploaded
            const videoFile = formData.get('video_file');
            const hasVideo = videoFile && videoFile.size > 0;
            
            // Show uploading message if video is present
            if (hasVideo && feedbackDiv) {
                feedbackDiv.classList.remove('hidden');
                feedbackDiv.classList.remove('text-red-600', 'bg-red-50', 'border-red-400');
                feedbackDiv.classList.add('text-blue-600', 'bg-blue-50', 'border-blue-400');
                feedbackDiv.innerHTML = '<div class="flex items-center"><svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Uploading video... Please wait, this may take a few minutes.</div>';
            }
            
            fetch('../api/admin/module_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    closeModal();
                    location.reload(); 
                } else {
                    if (feedbackDiv) {
                        feedbackDiv.classList.remove('text-blue-600', 'bg-blue-50', 'border-blue-400');
                        feedbackDiv.classList.add('text-red-600', 'bg-red-50', 'border-red-400');
                        feedbackDiv.classList.remove('hidden');
                        feedbackDiv.textContent = data.message || 'An error occurred.';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (feedbackDiv) {
                    feedbackDiv.classList.remove('text-blue-600', 'bg-blue-50', 'border-blue-400');
                    feedbackDiv.classList.add('text-red-600', 'bg-red-50', 'border-red-400');
                    feedbackDiv.classList.remove('hidden');
                    feedbackDiv.textContent = 'A network or server error occurred.';
                }
            });
        });
    }

    // --- User Management ---
    const userModal = document.getElementById('user-modal');
    if (userModal) {
        const addBtn = document.getElementById('add-user-btn');
        const cancelBtn = document.getElementById('user-cancel-btn');
        const form = document.getElementById('user-form');
        const feedbackDiv = document.getElementById('user-form-feedback');
        const addUserFields = document.getElementById('add-user-fields');
        const userInfoReadonly = document.getElementById('user-info-readonly');

        const openUserModal = () => userModal.classList.remove('hidden');
        const closeUserModal = () => {
            userModal.classList.add('hidden');
            form.reset();
            feedbackDiv.textContent = '';
        };

        addBtn.addEventListener('click', () => {
            form.reset();
            document.getElementById('user-modal-title').textContent = 'Add New User';
            document.getElementById('user-form-action').value = 'add_user';
            document.getElementById('user_id').value = '';
            addUserFields.style.display = 'block';
            userInfoReadonly.style.display = 'none';
            openUserModal();
        });

        cancelBtn.addEventListener('click', closeUserModal);

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(form);

            fetch('../api/admin/user_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeUserModal();
                    location.reload();
                } else {
                    feedbackDiv.textContent = data.message || 'An error occurred.';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedbackDiv.textContent = 'A network or server error occurred.';
            });
        });
    }

    // --- Question Management ---
    const questionModal = document.getElementById('question-modal');
    if (questionModal) {
        const addBtn = document.getElementById('add-question-btn');
        const cancelBtn = document.getElementById('question-cancel-btn');
        const form = document.getElementById('question-form');
        const feedbackDiv = document.getElementById('question-form-feedback');
        const optionsContainer = document.getElementById('options-container');
        const addOptionBtn = document.getElementById('add-option-btn');
        const associationType = document.getElementById('association_type');
        const moduleSelectContainer = document.getElementById('module-select-container');
        const importForm = document.getElementById('import-form');
        const importFeedback = document.getElementById('import-feedback');
        const deleteAllBtn = document.getElementById('delete-all-questions-btn');

        const openQuestionModal = () => questionModal.classList.remove('hidden');
        const closeQuestionModal = () => {
            questionModal.classList.add('hidden');
            form.reset();
            optionsContainer.innerHTML = ''; // Clear dynamic options
            feedbackDiv.textContent = '';
        };

        const createOptionInput = (option = {}, index = 0) => {
            const inputType = document.getElementById('question_type').value === 'single' ? 'radio' : 'checkbox';
            const optionDiv = document.createElement('div');
            optionDiv.className = 'flex items-center space-x-2';
            // For radio buttons, use the same name so only one can be selected
            const correctInputName = inputType === 'radio' ? 'correct_answer' : `options[${index}][is_correct]`;
            optionDiv.innerHTML = `
                <input type="${inputType}" name="${correctInputName}" value="${index}" class="h-5 w-5 text-primary focus:ring-primary border-gray-300" ${option.is_correct ? 'checked' : ''}>
                <input type="text" name="options[${index}][text]" value="${option.text || ''}" placeholder="Option text" required class="flex-grow rounded-md border-gray-300 shadow-sm">
                <input type="hidden" name="options[${index}][id]" value="${option.id || ''}">
                <button type="button" class="remove-option-btn text-red-500 hover:text-red-700">&times;</button>
            `;
            return optionDiv;
        };
        
        const renderOptions = (options = []) => {
            optionsContainer.innerHTML = '';
            let initialOptions = options.length > 0 ? options : [{}, {}]; // Start with 2 empty options if new
            initialOptions.forEach((opt, i) => optionsContainer.appendChild(createOptionInput(opt, i)));
        };

        addOptionBtn.addEventListener('click', () => {
            const index = optionsContainer.children.length;
            optionsContainer.appendChild(createOptionInput({}, index));
        });
        
        optionsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-option-btn')) {
                e.target.parentElement.remove();
            }
        });

        associationType.addEventListener('change', () => {
            moduleSelectContainer.style.display = associationType.value === 'module' ? 'block' : 'none';
        });

        document.getElementById('question_type').addEventListener('change', () => renderOptions());

        addBtn.addEventListener('click', () => {
            form.reset();
            document.getElementById('question-modal-title').textContent = 'Add New Question';
            document.getElementById('question-form-action').value = 'add_question';
            document.getElementById('question_id').value = '';
            associationType.dispatchEvent(new Event('change'));
            renderOptions();
            openQuestionModal();
        });

        cancelBtn.addEventListener('click', closeQuestionModal);

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(form);
            fetch('../api/admin/question_crud.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeQuestionModal();
                    location.reload();
                } else {
                    feedbackDiv.textContent = data.message || 'An error occurred.';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedbackDiv.textContent = 'A network or server error occurred.';
            });
        });

        importForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(importForm);
            formData.append('action', 'import_questions');
            importFeedback.textContent = 'Importing...';
            importFeedback.className = 'mt-2 text-sm text-blue-600';

            fetch('../api/admin/question_crud.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                importFeedback.textContent = data.message;
                importFeedback.className = `mt-2 text-sm ${data.success ? 'text-green-600' : 'text-red-600'}`;
                if (data.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                importFeedback.textContent = 'A network error occurred.';
                importFeedback.className = 'mt-2 text-sm text-red-600';
            });
        });

        deleteAllBtn.addEventListener('click', function() {
            const deleteModal = document.getElementById('delete-modal');
            const modalTitle = document.getElementById('delete-modal-title');
            const modalMessage = document.getElementById('delete-modal-message');
            const confirmBtn = document.getElementById('confirm-delete-btn');
            const cancelBtn = document.getElementById('cancel-delete-btn');
            const questionIdInput = document.getElementById('delete_question_id');
            
            // Clear question ID (not needed for delete all)
            questionIdInput.value = '';
            
            // Update modal for delete all
            modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Confirm Delete All Questions';
            modalMessage.textContent = 'Are you absolutely sure you want to delete ALL questions from the database? This will permanently delete every question and cannot be undone.';
            
            // Show the modal
            deleteModal.classList.remove('hidden');
            
            // Handle confirm button
            const confirmHandler = () => {
                deleteModal.classList.add('hidden');
                performDeleteAllQuestions();
                confirmBtn.removeEventListener('click', confirmHandler);
                cancelBtn.removeEventListener('click', cancelHandler);
            };
            
            // Handle cancel button
            const cancelHandler = () => {
                deleteModal.classList.add('hidden');
                confirmBtn.removeEventListener('click', confirmHandler);
                cancelBtn.removeEventListener('click', cancelHandler);
            };
            
            confirmBtn.addEventListener('click', confirmHandler);
            cancelBtn.addEventListener('click', cancelHandler);
            
            // Close modal when clicking backdrop
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    deleteModal.classList.add('hidden');
                    confirmBtn.removeEventListener('click', confirmHandler);
                    cancelBtn.removeEventListener('click', cancelHandler);
                }
            });
        });

        function performDeleteAllQuestions() {
            const formData = new FormData();
            formData.append('action', 'delete_all_questions');

            fetch('../api/admin/question_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Failed to delete questions: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('A network or server error occurred.');
            });
        }
    }
});

// --- Global Functions ---

function editModule(id) {
    const modal = document.getElementById('module-modal');
    fetch(`../api/admin/module_crud.php?action=get_module&id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const module = data.data;
            document.getElementById('modal-title').textContent = 'Edit Module';
            document.getElementById('form-action').value = 'edit_module';
            document.getElementById('module_id').value = module.id;
            document.getElementById('title').value = module.title;
            document.getElementById('description').value = module.description;
            document.getElementById('module_order').value = module.module_order;
            
            // Show video section and populate video info if exists
            const videoSection = document.getElementById('video-section');
            if (module.video && videoSection) {
                videoSection.style.display = 'block';
                // Populate video duration if exists
                if (module.video.duration) {
                    document.getElementById('video_duration').value = module.video.duration;
                }
                // Show current video info
                const videoFileInput = document.getElementById('video_file');
                videoFileInput.required = false; // Video upload is optional when editing
                
                // Add info text showing current video
                let videoInfo = document.getElementById('current-video-info');
                if (!videoInfo) {
                    videoInfo = document.createElement('div');
                    videoInfo.id = 'current-video-info';
                    videoInfo.className = 'mb-2 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800';
                    videoFileInput.parentElement.insertBefore(videoInfo, videoFileInput);
                }
                videoInfo.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><strong>Current video:</strong> ${module.video.video_path.split('/').pop()}</span>
                    </div>
                    <p class="mt-1 text-xs">Leave empty to keep current video, or upload a new one to replace it.</p>
                `;
            } else if (videoSection) {
                // No video exists, hide the section
                videoSection.style.display = 'none';
            }
            
            modal.classList.remove('hidden');
        } else { alert(data.message); }
    })
    .catch(error => console.error('Error fetching module data:', error));
}

function deleteModule(id) {
    const deleteModal = document.getElementById('delete-modal');
    const confirmBtn = document.getElementById('confirm-delete-btn');
    const cancelBtn = document.getElementById('cancel-delete-btn');
    
    if (!deleteModal) {
        // Fallback to confirm dialog if modal doesn't exist
        if (!confirm('Are you sure you want to delete this module? This will also delete all associated videos and questions. This action cannot be undone.')) {
            return;
        }
        performDelete(id);
        return;
    }
    
    // Show the modal
    deleteModal.classList.remove('hidden');
    
    // Handle confirm button
    const confirmHandler = () => {
        deleteModal.classList.add('hidden');
        performDelete(id);
        confirmBtn.removeEventListener('click', confirmHandler);
        cancelBtn.removeEventListener('click', cancelHandler);
    };
    
    // Handle cancel button
    const cancelHandler = () => {
        deleteModal.classList.add('hidden');
        confirmBtn.removeEventListener('click', confirmHandler);
        cancelBtn.removeEventListener('click', cancelHandler);
    };
    
    confirmBtn.addEventListener('click', confirmHandler);
    cancelBtn.addEventListener('click', cancelHandler);
}

function performDelete(id) {
    const formData = new FormData();
    formData.append('action', 'delete_module');
    formData.append('module_id', id);
    fetch('../api/admin/module_crud.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`module-row-${id}`);
            if (row) row.remove();
        } else { alert('Failed to delete module: ' + data.message); }
    })
    .catch(error => { console.error('Error:', error); alert('A network or server error occurred.'); });
}

function editUser(id) {
    const modal = document.getElementById('user-modal');
    fetch(`../api/admin/user_crud.php?action=get_user&id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const user = data.data;
            document.getElementById('user-modal-title').textContent = 'Edit User';
            document.getElementById('user-form-action').value = 'edit_user';
            document.getElementById('user_id').value = user.id;
            document.getElementById('role').value = user.role;
            document.getElementById('status').value = user.status;

            const userInfoDiv = document.getElementById('user-info-readonly');
            userInfoDiv.innerHTML = `
                <p><strong class="font-medium text-gray-700">Name:</strong> ${escapeHTML(user.first_name)} ${escapeHTML(user.last_name)}</p>
                <p><strong class="font-medium text-gray-700">Email:</strong> ${escapeHTML(user.email)}</p>
                <p><strong class="font-medium text-gray-700">Staff ID:</strong> ${escapeHTML(user.staff_id)}</p>
            `;
            userInfoDiv.style.display = 'block';
            document.getElementById('add-user-fields').style.display = 'none';

            modal.classList.remove('hidden');
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error fetching user data:', error));
}

function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user? All their progress and assessment data will be lost permanently.')) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', id);
    fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`user-row-${id}`);
            if (row) row.remove();
        } else { alert('Failed to delete user: ' + data.message); }
    })
    .catch(error => { console.error('Error:', error); alert('A network or server error occurred.'); });
}

function resetPassword(userId) {
    if (!confirm('Are you sure you want to reset the password for this user? A new temporary password will be generated.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'reset_password');
    formData.append('user_id', userId);

    fetch('../api/admin/user_crud.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.prompt(
                "Password has been reset successfully. Please provide this temporary password to the user.\n\nNew Password:", 
                data.new_password
            );
        } else {
            alert('Failed to reset password: ' + (data.message || 'Unknown error.'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('A network or server error occurred during the password reset.');
    });
}

window.editQuestion = function(id) {
    fetch(`../api/admin/question_crud.php?action=get_question&id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const q = data.data;
            document.getElementById('question-modal-title').textContent = 'Edit Question';
            document.getElementById('question-form-action').value = 'edit_question';
            document.getElementById('question_id').value = q.id;
            document.getElementById('question_text').value = q.question_text;
            document.getElementById('question_type').value = q.question_type;
            
            const assocType = document.getElementById('association_type');
            assocType.value = q.is_final_exam_question == 1 ? 'final_exam' : 'module';
            assocType.dispatchEvent(new Event('change'));

            if (q.is_final_exam_question != 1) {
                document.getElementById('module_id').value = q.module_id;
            }
            
            const optionsContainer = document.getElementById('options-container');
            optionsContainer.innerHTML = '';
            q.options.forEach((opt, i) => {
                const inputType = q.question_type === 'single' ? 'radio' : 'checkbox';
                const optionDiv = document.createElement('div');
                optionDiv.className = 'flex items-center space-x-2';
                // For radio buttons, use the same name so only one can be selected
                const correctInputName = inputType === 'radio' ? 'correct_answer' : `options[${i}][is_correct]`;
                optionDiv.innerHTML = `
                    <input type="${inputType}" name="${correctInputName}" value="${i}" class="h-5 w-5 text-primary focus:ring-primary border-gray-300" ${opt.is_correct == 1 ? 'checked' : ''}>
                    <input type="text" name="options[${i}][text]" value="${escapeHTML(opt.option_text)}" required class="flex-grow rounded-md border-gray-300 shadow-sm">
                `;
                optionsContainer.appendChild(optionDiv);
            });

            document.getElementById('question-modal').classList.remove('hidden');
        } else { alert(data.message); }
    });
}

window.deleteQuestion = function(id) {
    const deleteModal = document.getElementById('delete-modal');
    const modalMessage = document.getElementById('delete-modal-message');
    const modalTitle = document.getElementById('delete-modal-title');
    const confirmBtn = document.getElementById('confirm-delete-btn');
    const cancelBtn = document.getElementById('cancel-delete-btn');
    const questionIdInput = document.getElementById('delete_question_id');
    
    // Set the question ID
    questionIdInput.value = id;
    
    // Reset modal to default state for single question
    modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Confirm Deletion';
    modalMessage.textContent = 'Are you sure you want to permanently delete this question? This action cannot be undone.';
    
    // Show the modal
    deleteModal.classList.remove('hidden');
    
    // Handle confirm button
    const confirmHandler = () => {
        deleteModal.classList.add('hidden');
        performQuestionDelete(id);
        confirmBtn.removeEventListener('click', confirmHandler);
        cancelBtn.removeEventListener('click', cancelHandler);
    };
    
    // Handle cancel button
    const cancelHandler = () => {
        deleteModal.classList.add('hidden');
        confirmBtn.removeEventListener('click', confirmHandler);
        cancelBtn.removeEventListener('click', cancelHandler);
    };
    
    confirmBtn.addEventListener('click', confirmHandler);
    cancelBtn.addEventListener('click', cancelHandler);
    
    // Close modal when clicking backdrop
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            deleteModal.classList.add('hidden');
            confirmBtn.removeEventListener('click', confirmHandler);
            cancelBtn.removeEventListener('click', cancelHandler);
        }
    });
}

function performQuestionDelete(id) {
    const formData = new FormData();
    formData.append('action', 'delete_question');
    formData.append('question_id', id);
    fetch('../api/admin/question_crud.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`question-row-${id}`);
            if (row) row.remove();
        } else { 
            alert('Failed to delete question: ' + data.message); 
        }
    })
    .catch(error => { 
        console.error('Error:', error); 
        alert('A network or server error occurred.'); 
    });
}

function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    var p = document.createElement("p");
    p.appendChild(document.createTextNode(str));
    return p.innerHTML;
}

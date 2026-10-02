/**
 * Sub Application Form Draft Autosave Functionality
 * Handles automatic saving of form data as drafts
 */

document.addEventListener('DOMContentLoaded', function() {
    // Basic autosave functionality for sub-applications
    const form = document.getElementById('subApplicationForm');
    const statusText = document.getElementById('draftStatusText');
    const lastSavedText = document.getElementById('draftLastSavedText');
    const progressBar = document.getElementById('draftProgressBar');
    const progressValue = document.getElementById('draftProgressValue');
    
    if (!form) {
        console.warn('[Draft Autosave] Sub-application form not found.');
        return;
    }

    // Track last save time
    let lastSaveTime = null;
    let autoSaveInterval = null;
    
    // Update status display
    function updateStatus(message, type = 'info') {
        if (statusText) {
            statusText.textContent = message;
            statusText.className = `text-sm ${type === 'error' ? 'text-red-600' : type === 'success' ? 'text-green-600' : 'text-gray-600'}`;
        }
    }

    // Update last saved time display
    function updateLastSaved() {
        if (lastSaveTime && lastSavedText) {
            const now = new Date();
            const diff = Math.floor((now - lastSaveTime) / (1000 * 60));
            if (diff === 0) {
                lastSavedText.textContent = 'Just now';
            } else if (diff === 1) {
                lastSavedText.textContent = 'Last saved: 1 minute ago';
            } else {
                lastSavedText.textContent = `Last saved: ${diff} minutes ago`;
            }
        }
    }

    // Calculate and update progress
    function updateProgress() {
        let filledFields = 0;
        let totalFields = 0;

        // Count form inputs
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type !== 'hidden' && input.name) {
                totalFields++;
                if (input.type === 'checkbox' || input.type === 'radio') {
                    if (input.checked) filledFields++;
                } else if (input.type === 'file') {
                    if (input.files && input.files.length > 0) filledFields++;
                } else {
                    if (input.value && input.value.trim()) filledFields++;
                }
            }
        });

        const progress = totalFields > 0 ? Math.round((filledFields / totalFields) * 100) : 0;
        
        if (progressBar) {
            progressBar.style.width = `${progress}%`;
        }
        if (progressValue) {
            progressValue.textContent = `${progress}%`;
        }

        return progress;
    }

    // Save draft function
    function saveDraft(options = {}) {
        if (!window.SUB_APPLICATION_DRAFT_ENDPOINTS) {
            console.error('[Draft Autosave] Draft endpoints not configured');
            return;
        }
        
        const formData = new FormData(form);
        
        // Ensure land_use is included in the draft
        const resolvedLandUse = resolveLandUseValue();
        if (resolvedLandUse) {
            formData.set('land_use', resolvedLandUse);
        }
        
        const progress = updateProgress();
        
        // Include metadata
        formData.set('progress_percent', progress);
        formData.set('last_completed_step', getCurrentStep());
        formData.set('is_auto_save', options.auto ? '1' : '0');
        
        if (options.auto) {
            updateStatus('Auto-saving...', 'info');
        } else {
            updateStatus('Saving draft...', 'info');
        }

        fetch(window.SUB_APPLICATION_DRAFT_ENDPOINTS.save, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                lastSaveTime = new Date();
                updateStatus(options.auto ? 'Auto-saved' : 'Draft saved', 'success');
                
                if (options.flash !== false && typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Draft Saved',
                        text: 'Your application has been saved as a draft.',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
                
                // Update draft metadata
                if (data.draft) {
                    const draftIdInput = document.getElementById('draftIdInput');
                    const draftVersionInput = document.getElementById('draftVersionInput');
                    if (draftIdInput) draftIdInput.value = data.draft.id;
                    if (draftVersionInput) draftVersionInput.value = data.draft.version || 1;
                }
            } else {
                updateStatus('Failed to save draft', 'error');
                console.error('Draft save failed:', data.message);
            }
        })
        .catch(error => {
            updateStatus('Error saving draft', 'error');
            console.error('Draft save error:', error);
        });
    }

    // Helper functions
    function getCurrentStep() {
        const activeStep = document.querySelector('.form-section.active-tab');
        if (activeStep) {
            const stepId = activeStep.id;
            return parseInt(stepId.replace('step', '')) || 1;
        }
        return 1;
    }

    function resolveLandUseValue() {
        // Try to get land_use from various sources
        const landUseInput = document.querySelector('input[name="land_use"]');
        const landUseSelect = document.querySelector('select[name="land_use"]');
        const landUseRadio = document.querySelector('input[name="land_use"]:checked');
        
        if (landUseRadio) return landUseRadio.value;
        if (landUseSelect) return landUseSelect.value;
        if (landUseInput) return landUseInput.value;
        
        return null;
    }

    // Auto-save every 30 seconds
    function startAutoSave() {
        if (autoSaveInterval) clearInterval(autoSaveInterval);
        
        autoSaveInterval = setInterval(() => {
            saveDraft({ auto: true, flash: false });
        }, 30000);
    }

    // Save on form changes (debounced)
    let saveTimeout;
    function debouncedSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            saveDraft({ flash: false });
        }, 2000);
    }

    // Event listeners
    form.addEventListener('input', debouncedSave);
    form.addEventListener('change', debouncedSave);

    // Update last saved time every minute
    setInterval(updateLastSaved, 60000);

    // Initialize
    updateStatus('Draft ready', 'info');
    updateProgress();
    startAutoSave();
    
    console.log('[Draft Autosave] Initialized successfully');
    
    // Make saveDraft available globally for manual saves
    window.saveDraft = saveDraft;
});
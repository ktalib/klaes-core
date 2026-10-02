(function (window, document) {
    'use strict';

    const SubApplicationDraftAutosave = {
        init() {
            console.log('[SubApplicationDraftAutosave] Initializing...');
            this.form = document.getElementById('subApplicationForm');
            if (!this.form) {
                console.warn('[SubApplicationDraftAutosave] Sub-application form not found.');
                return;
            }
            console.log('[SubApplicationDraftAutosave] Form found:', this.form);

            this.statusText = document.getElementById('draftStatusText');
            this.lastSavedText = document.getElementById('draftLastSavedText');
            this.progressBar = document.getElementById('draftProgressBar');
            this.progressValue = document.getElementById('draftProgressValue');
            this.historyButton = document.getElementById('draftHistoryButton');
            this.shareButton = document.getElementById('draftShareButton');
            this.exportButton = document.getElementById('draftExportButton');
            this.collaboratorCountEl = document.getElementById('draftCollaboratorCount');

            this.bootstrap = window.SUB_APPLICATION_DRAFT_BOOTSTRAP || {};
            this.endpoints = window.SUB_APPLICATION_DRAFT_ENDPOINTS || {};
            
            console.log('[SubApplicationDraftAutosave] Bootstrap data:', this.bootstrap);
            console.log('[SubApplicationDraftAutosave] Endpoints:', this.endpoints);
            this.beforeUnloadHandler = null;

            this.state = {
                draftId: this.form.dataset.draftId || this.bootstrap.draft_id || null,
                unitFileNo: this.bootstrap.unit_file_no || null,
                version: Number(this.form.dataset.draftVersion || this.bootstrap.version || 1),
                autoSaveFrequency: Number(this.form.dataset.autoSaveFrequency || this.bootstrap.auto_save_frequency || 30),
                isSaving: false,
                isFetching: false,
                hasPendingChanges: false,
                debouncedTimer: null,
                autoSaveTimer: null,
                sessionWarningTimer: null,
                lastSaveTrigger: null,
                lastResponse: null,
                lockToken: null,
                silenceChanges: false,
                mode: this.bootstrap.mode || (Array.isArray(this.bootstrap.drafts) && this.bootstrap.drafts.length ? 'draft' : 'fresh'),
                isSUA: this.bootstrap.is_sua || false,
            };

            this.frequencySelect = null;
            this.lookupForm = null;
            this.lookupInput = null;
            this.lookupFeedback = null;
            this.lookupCurrentDisplay = null;
            this.lookupCopyButton = null;
            this.modeButtons = {
                fresh: null,
                draft: null,
            };
            this.draftPanel = null;
            this.draftSelect = null;
            this.draftLoadButton = null;
            this.draftEmptyState = null;

            this.installBeforeUnload();
            this.attachListeners();
            this.injectFrequencySelector();
            this.setupDraftLocator();
            this.setupModeSwitch();

            if (this.frequencySelect) {
                this.setAutoSaveFrequency(this.state.autoSaveFrequency);
            }

            this.restoreFormState(this.bootstrap.form_state || {});
            this.checkForRecoveryPrompt();
            this.startAutoSaveTimer();
            this.scheduleSessionWarning();
            this.updateStatus('Draft ready', 'ready');
            this.updateProgressUi(this.bootstrap.progress_percent || 0, this.bootstrap.last_completed_step || 1);
        },

        attachListeners() {
            const changeHandler = this.handleFieldChange.bind(this);
            this.form.addEventListener('input', changeHandler, true);
            this.form.addEventListener('change', changeHandler, true);

            const nextButtons = document.querySelectorAll('[id^="nextStep"], [data-step-action="next"], .next-step-btn');
            nextButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.manualSave({ flash: false });
                });
            });

            const manualSaveButtons = document.querySelectorAll('[data-draft-save], #printApplicationSlip');
            manualSaveButtons.forEach(btn => {
                btn.addEventListener('click', (event) => {
                    if (btn.id === 'printApplicationSlip') {
                        event.preventDefault();
                    }
                    this.manualSave({ flash: true });
                });
            });

            if (this.historyButton) {
                this.historyButton.addEventListener('click', () => {
                    this.openHistoryModal();
                });
            }

            if (this.shareButton) {
                this.shareButton.addEventListener('click', () => {
                    this.openShareModal();
                });
            }

            if (this.exportButton) {
                this.exportButton.addEventListener('click', () => {
                    this.exportDraft();
                });
            }
        },

        handleFieldChange(event) {
            console.log('[SubApplicationDraftAutosave] Field changed:', event.target.name, event.target.value);
            if (this.state.silenceChanges) {
                return;
            }

            this.state.hasPendingChanges = true;
            this.updateStatus('Changes detected', 'pending');

            // Debounce auto-save
            if (this.state.debouncedTimer) {
                clearTimeout(this.state.debouncedTimer);
            }

            this.state.debouncedTimer = setTimeout(() => {
                this.autoSave({ trigger: 'debounced' });
            }, 2000);
        },

        autoSave(options = {}) {
            console.log('[SubApplicationDraftAutosave] Auto-save triggered:', options);
            if (this.state.isSaving || this.state.isFetching) {
                console.log('[SubApplicationDraftAutosave] Already saving or fetching, skipping...');
                return;
            }

            const trigger = options.trigger || 'auto';
            const flash = options.flash || false;

            this.state.isSaving = true;
            this.state.lastSaveTrigger = trigger;

            if (flash) {
                this.updateStatus('Saving...', 'saving');
            }

            const formData = this.collectFormData();
            const payload = {
                draft_id: this.state.draftId,
                sub_application_id: this.form.dataset.subApplicationId || null,
                main_application_id: this.form.dataset.mainApplicationId || null,
                is_sua: this.state.isSUA,
                form_state: formData,
                metadata: {
                    progress_percent: this.calculateProgress(formData),
                    last_completed_step: this.inferCurrentStep(),
                    auto_save_frequency: this.state.autoSaveFrequency,
                    trigger: trigger,
                    client_version: this.state.version,
                    lock_token: this.state.lockToken,
                    collaborators: this.bootstrap.collaborators || [],
                }
            };

            fetch(this.endpoints.save, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                if (response.status === 409) {
                    return response.json().then(data => {
                        this.handleConflict(data);
                        throw new Error('Version conflict');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.handleSaveSuccess(data, flash);
                } else {
                    throw new Error(data.message || 'Save failed');
                }
            })
            .catch(error => {
                this.handleSaveError(error, flash);
            })
            .finally(() => {
                this.state.isSaving = false;
            });
        },

        manualSave(options = {}) {
            this.autoSave({ ...options, trigger: 'manual' });
        },

        collectFormData() {
            const formData = new FormData(this.form);
            const data = {};

            for (let [key, value] of formData.entries()) {
                if (key.startsWith('_')) continue;

                if (data[key]) {
                    if (Array.isArray(data[key])) {
                        data[key].push(value);
                    } else {
                        data[key] = [data[key], value];
                    }
                } else {
                    data[key] = value;
                }
            }

            return data;
        },

        calculateProgress(formData) {
            const requiredFields = [
                'applicant_type',
                'buyer_id',
                'unit_no',
                'land_use',
                'unit_measurement',
                'shared_areas'
            ];

            let completed = 0;
            const total = requiredFields.length;

            requiredFields.forEach(field => {
                const value = formData[field];
                if (field === 'shared_areas') {
                    if (Array.isArray(value) && value.length > 0) {
                        completed++;
                    }
                } else if (Array.isArray(value)) {
                    if (value.some(v => v && v.toString().trim() !== '')) {
                        completed++;
                    }
                } else if (value && value.toString().trim() !== '') {
                    completed++;
                }
            });

            return Math.round((completed / total) * 100);
        },

        inferCurrentStep() {
            const formData = this.collectFormData();
            const stepThresholds = {
                1: ['applicant_type', 'buyer_id'],
                2: ['shared_areas'],
                3: ['unit_measurement', 'land_use'],
                4: ['unit_no']
            };

            let currentStep = 1;

            Object.entries(stepThresholds).forEach(([step, fields]) => {
                const stepNum = parseInt(step);
                fields.forEach(field => {
                    const value = formData[field];
                    if (value && value.toString().trim() !== '') {
                        currentStep = Math.max(currentStep, stepNum);
                    }
                });
            });

            return currentStep;
        },

        handleSaveSuccess(data, flash) {
            this.state.draftId = data.draft_id;
            this.state.version = data.version;
            this.state.hasPendingChanges = false;
            this.state.lastResponse = data;

            if (data.unit_file_no) {
                this.state.unitFileNo = data.unit_file_no;
            }

            this.updateStatus('Saved', 'saved');
            this.updateProgressUi(data.progress_percent, data.last_completed_step);

            if (flash) {
                this.showToast('Draft saved successfully', 'success');
            }

            // Update form data attributes
            this.form.dataset.draftId = data.draft_id;
            this.form.dataset.draftVersion = data.version;
        },

        handleSaveError(error, flash) {
            console.error('[SubApplicationDraftAutosave] Save failed:', error);
            this.updateStatus('Save failed', 'error');

            if (flash) {
                this.showToast('Failed to save draft: ' + error.message, 'error');
            }
        },

        handleConflict(data) {
            this.showToast('A newer version exists. Reloading...', 'warning');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        },

        updateStatus(text, type) {
            if (this.statusText) {
                this.statusText.textContent = text;
                this.statusText.className = `font-bold ${
                    type === 'ready' ? 'text-green-700' :
                    type === 'pending' ? 'text-yellow-700' :
                    type === 'saving' ? 'text-blue-700' :
                    type === 'saved' ? 'text-green-700' :
                    type === 'error' ? 'text-red-700' : 'text-gray-700'
                }`;
            }
        },

        updateProgressUi(progress, step) {
            if (this.progressBar) {
                this.progressBar.style.width = `${progress}%`;
            }
            if (this.progressValue) {
                this.progressValue.textContent = `${Math.round(progress)}%`;
            }
        },

        showToast(message, type) {
            if (window.Swal) {
                Swal.fire({
                    icon: type,
                    title: message,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        },

        startAutoSaveTimer() {
            if (this.state.autoSaveTimer) {
                clearInterval(this.state.autoSaveTimer);
            }

            this.state.autoSaveTimer = setInterval(() => {
                if (this.state.hasPendingChanges && !this.state.isSaving) {
                    this.autoSave({ trigger: 'auto' });
                }
            }, this.state.autoSaveFrequency * 1000);
        },

        setAutoSaveFrequency(seconds) {
            this.state.autoSaveFrequency = seconds;
            this.startAutoSaveTimer();
        },

        restoreFormState(formState) {
            if (!formState || Object.keys(formState).length === 0) {
                return;
            }

            this.state.silenceChanges = true;

            Object.entries(formState).forEach(([name, value]) => {
                const element = this.form.querySelector(`[name="${name}"]`);
                if (element) {
                    if (element.type === 'checkbox' || element.type === 'radio') {
                        element.checked = value === element.value;
                    } else if (element.tagName === 'SELECT') {
                        element.value = value;
                    } else {
                        element.value = value;
                    }
                }
            });

            setTimeout(() => {
                this.state.silenceChanges = false;
            }, 100);
        },

        checkForRecoveryPrompt() {
            const lastSave = localStorage.getItem('subApplicationDraftLastSave');
            if (lastSave && Date.now() - parseInt(lastSave) < 300000) { // 5 minutes
                this.showToast('Recovered from previous session', 'info');
            }
        },

        scheduleSessionWarning() {
            this.state.sessionWarningTimer = setTimeout(() => {
                this.showToast('Your session will expire soon. Save your work.', 'warning');
            }, 25 * 60 * 1000); // 25 minutes
        },

        installBeforeUnload() {
            this.beforeUnloadHandler = (event) => {
                if (this.state.hasPendingChanges) {
                    event.preventDefault();
                    event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return event.returnValue;
                }
            };

            window.addEventListener('beforeunload', this.beforeUnloadHandler);
        },

        injectFrequencySelector() {
            // Implementation for frequency selector
        },

        setupDraftLocator() {
            // Implementation for draft locator
        },

        setupModeSwitch() {
            // Implementation for mode switch
        },

        openHistoryModal() {
            // Implementation for history modal
        },

        openShareModal() {
            // Implementation for share modal
        },

        exportDraft() {
            if (!this.state.draftId) {
                this.showToast('No draft to export', 'error');
                return;
            }

            window.open(this.endpoints.export.replace('__DRAFT_ID__', this.state.draftId), '_blank');
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SubApplicationDraftAutosave.init());
    } else {
        SubApplicationDraftAutosave.init();
    }

    // Make it globally available
    window.SubApplicationDraftAutosave = SubApplicationDraftAutosave;

})(window, document);
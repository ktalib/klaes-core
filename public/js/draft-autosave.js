(function (window, document) {
    'use strict';

    const DraftAutosave = {
        init() {
            this.form = document.getElementById('primaryApplicationForm');
            if (!this.form) {
                console.warn('[DraftAutosave] Primary application form not found.');
                return;
            }

            this.statusText = document.getElementById('draftStatusText');
            this.lastSavedText = document.getElementById('draftLastSavedText');
            this.progressBar = document.getElementById('draftProgressBar');
            this.progressValue = document.getElementById('draftProgressValue');
            this.historyButton = document.getElementById('draftHistoryButton');
            this.shareButton = document.getElementById('draftShareButton');
            this.exportButton = document.getElementById('draftExportButton');
            this.collaboratorCountEl = document.getElementById('draftCollaboratorCount');

            this.bootstrap = window.SUB_APPLICATION_DRAFT_BOOTSTRAP || window.PRIMARY_DRAFT_BOOTSTRAP || {};
            this.endpoints = window.SUB_APPLICATION_DRAFT_ENDPOINTS || window.PRIMARY_DRAFT_ENDPOINTS || {};
            this.beforeUnloadHandler = null;

            // Populate bootstrap with form data attributes if missing
            if (!this.bootstrap.sub_application_id && this.form.dataset.subApplicationId) {
                this.bootstrap.sub_application_id = Number(this.form.dataset.subApplicationId);
            }
            if (!this.bootstrap.main_application_id && this.form.dataset.mainApplicationId) {
                this.bootstrap.main_application_id = Number(this.form.dataset.mainApplicationId);
            }

            this.state = {
                draftId: this.form.dataset.draftId || this.bootstrap.draft_id || null,
                npFileNo: this.bootstrap.np_file_no || null,
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
                    this.openShareDialog();
                });
            }

            if (this.exportButton) {
                this.exportButton.addEventListener('click', () => {
                    this.exportDraft();
                });
            }
        },

        injectFrequencySelector() {
            const container = document.getElementById('draftStatusContainer');
            if (!container) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-center gap-2';

            const label = document.createElement('span');
            label.className = 'text-xs text-blue-700 font-medium';
            label.textContent = 'Auto-save every';

            const select = document.createElement('select');
            select.className = 'text-xs border border-blue-200 rounded-md px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400';
            [15, 30, 45, 60, 90, 120].forEach(seconds => {
                const option = document.createElement('option');
                option.value = seconds;
                option.textContent = `${seconds}s`;
                if (seconds === this.state.autoSaveFrequency) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            select.addEventListener('change', () => {
                this.setAutoSaveFrequency(Number(select.value));
                this.updateStatus(`Auto-save set to every ${select.value} seconds`, 'info');
            });

            this.frequencySelect = select;
            wrapper.appendChild(label);
            wrapper.appendChild(select);
            container.appendChild(wrapper);
        },

        setAutoSaveFrequency(value) {
            const parsed = Number(value);
            const fallback = Number.isFinite(parsed) && parsed > 0 ? parsed : 30;

            this.state.autoSaveFrequency = fallback;

            if (this.frequencySelect) {
                const optionValues = Array.from(this.frequencySelect.options).map(option => Number(option.value));
                if (!optionValues.includes(fallback)) {
                    const option = document.createElement('option');
                    option.value = fallback;
                    option.textContent = `${fallback}s`;
                    this.frequencySelect.appendChild(option);
                }
                this.frequencySelect.value = String(fallback);
            }

            this.resetAutoSaveTimer();
        },

        setupDraftLocator() {
            this.lookupForm = document.getElementById('draftLocatorForm');
            this.lookupInput = document.getElementById('draftLocatorInput');
            this.lookupFeedback = document.getElementById('draftLocatorFeedback');
            this.lookupCurrentDisplay = document.getElementById('draftLocatorCurrentId');
            this.lookupCopyButton = document.getElementById('draftLocatorCopyButton');

            this.updateDraftLocatorDisplay();

            if (this.lookupForm && this.lookupInput) {
                this.lookupForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const value = (this.lookupInput.value || '').trim();

                    if (!value) {
                        this.showDraftLocatorFeedback('Enter a file number to load.', 'error');
                        return;
                    }

                    if (this.state.isFetching) {
                        this.showDraftLocatorFeedback('A draft is already loading. Please wait…', 'info');
                        return;
                    }

                    this.loadDraftByIdentifier(value);
                });
            }

            if (this.lookupCopyButton) {
                this.lookupCopyButton.addEventListener('click', () => {
                    const value = this.state.npFileNo || this.bootstrap.np_file_no || '';

                    if (!value) {
                        this.showDraftLocatorFeedback('No file number to copy yet.', 'error');
                        return;
                    }

                    const copyPromise = navigator.clipboard && navigator.clipboard.writeText
                        ? navigator.clipboard.writeText(value)
                        : new Promise((resolve, reject) => {
                            const tempInput = document.createElement('input');
                            tempInput.value = value;
                            document.body.appendChild(tempInput);
                            tempInput.select();

                            try {
                                const successful = document.execCommand('copy');
                                document.body.removeChild(tempInput);
                                successful ? resolve() : reject(new Error('Copy command rejected'));
                            } catch (err) {
                                document.body.removeChild(tempInput);
                                reject(err);
                            }
                        });

                    copyPromise
                        .then(() => {
                            this.showDraftLocatorFeedback('File number copied to clipboard.', 'success');
                        })
                        .catch(() => {
                            this.showDraftLocatorFeedback('Unable to copy automatically. Please copy manually.', 'error');
                        });
                });
            }
        },

        setupModeSwitch() {
            this.modeButtons = {
                fresh: document.getElementById('draftModeFreshButton'),
                draft: document.getElementById('draftModeDraftButton'),
            };
            this.draftPanel = document.getElementById('draftModeDraftPanel');
            this.draftSelect = document.getElementById('draftListSelect');
            this.draftLoadButton = document.getElementById('draftListLoadButton');
            this.draftEmptyState = document.getElementById('draftListEmpty');

            this.markCurrentDraftInSummaries();
            this.populateDraftList(this.bootstrap.drafts || []);

            const initialMode = this.state.mode || (Array.isArray(this.bootstrap.drafts) && this.bootstrap.drafts.length ? 'draft' : 'fresh');
            this.setMode(initialMode, { silent: true });

            if (this.modeButtons.fresh) {
                this.modeButtons.fresh.addEventListener('click', () => {
                    const previousMode = this.state.mode;
                    this.confirmFreshStart()
                        .then((proceed) => {
                            if (!proceed) {
                                return;
                            }
                            this.setMode('fresh');
                            this.startFreshApplication({ previousMode });
                        });
                });
            }

            if (this.modeButtons.draft) {
                this.modeButtons.draft.addEventListener('click', () => {
                    this.setMode('draft');
                });
            }

            if (this.draftSelect) {
                this.draftSelect.addEventListener('change', () => {
                    this.hideDraftLocatorFeedback();
                });

                this.draftSelect.addEventListener('dblclick', () => {
                    const value = (this.draftSelect.value || '').trim();
                    if (value) {
                        this.loadDraftByIdentifier(value);
                    }
                });
            }

            if (this.draftLoadButton && this.draftSelect) {
                this.draftLoadButton.addEventListener('click', () => {
                    const selectedId = this.draftSelect.value;

                    if (!selectedId) {
                        this.showDraftLocatorFeedback('Select a file number to continue.', 'error');
                        return;
                    }

                    this.loadDraftByIdentifier(selectedId);
                });
            }
        },

        setMode(mode, options = {}) {
            const { silent = false } = options;
            const resolvedMode = mode === 'fresh' || mode === 'draft' ? mode : 'draft';
            this.state.mode = resolvedMode;
            this.bootstrap.mode = resolvedMode;
            window.PRIMARY_DRAFT_BOOTSTRAP = this.bootstrap;

            if (this.modeButtons.fresh && this.modeButtons.draft) {
                const activeClasses = ['bg-blue-600', 'text-white', 'border-blue-600'];
                const inactiveClasses = ['text-blue-700', 'hover:bg-blue-50', 'border-blue-200'];

                const applyClasses = (button, isActive) => {
                    if (!button) {
                        return;
                    }
                    const baseClasses = ['px-3', 'py-1.5', 'text-xs', 'font-semibold', 'rounded-md', 'transition', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-400', 'focus:ring-offset-1', 'border'];
                    button.className = baseClasses.join(' ');
                    (isActive ? activeClasses : inactiveClasses).forEach(cls => button.classList.add(cls));
                };

                applyClasses(this.modeButtons.fresh, resolvedMode === 'fresh');
                applyClasses(this.modeButtons.draft, resolvedMode === 'draft');
            }

            if (this.draftPanel) {
                const hasDrafts = Array.isArray(this.bootstrap.drafts) && this.bootstrap.drafts.length > 0;
                this.draftPanel.classList.toggle('hidden', resolvedMode !== 'draft' || !hasDrafts);
            }

            if (!silent && resolvedMode === 'draft' && this.draftSelect && Array.isArray(this.bootstrap.drafts) && this.bootstrap.drafts.length) {
                const currentDraftId = this.state.draftId;

                if (currentDraftId) {
                    const matchingOption = Array.from(this.draftSelect.options).find(option => option.dataset.draftId === currentDraftId);
                    if (matchingOption) {
                        this.draftSelect.value = matchingOption.value;
                    }
                }

                if (!this.draftSelect.value) {
                    const firstSummary = this.bootstrap.drafts[0];
                    this.draftSelect.value = firstSummary.np_file_no || firstSummary.draft_id;
                }
            }
        },

        confirmFreshStart() {
            if (!this.state.hasPendingChanges) {
                return Promise.resolve(true);
            }

            if (window.Swal) {
                return Swal.fire({
                    title: 'Start a fresh application?',
                    text: 'Your current draft will stay saved so you can come back to it later.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Start fresh',
                    cancelButtonText: 'Stay here',
                }).then(result => result.isConfirmed);
            }

            const confirmed = window.confirm('Start a fresh application? Your current draft will remain saved.');
            return Promise.resolve(confirmed);
        },

        startFreshApplication(options = {}) {
            const { previousMode = 'draft' } = options;
            if (!this.endpoints.start) {
                this.updateStatus('Fresh draft endpoint not configured.', 'error');
                return Promise.resolve(null);
            }

            this.updateStatus('Preparing a fresh draft…', 'loading');

            return fetch(this.endpoints.start, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                body: JSON.stringify({
                    application_id: this.bootstrap.application_id || null,
                }),
            })
                .then(async (response) => {
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || payload.success === false) {
                        const message = payload.message || `Unable to start a fresh draft (status ${response.status})`;
                        throw new Error(message);
                    }

                    this.applyLoadedDraft(payload, {
                        resetForm: true,
                        message: payload.message || 'Fresh draft ready',
                        status: 'info',
                        showToast: true,
                    });

                    this.setMode('fresh', { silent: true });

                    return payload;
                })
                .catch((error) => {
                    console.error('[DraftAutosave] Fresh draft error', error);
                    this.updateStatus('Unable to start a fresh draft', 'error');

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Unable to start fresh application',
                            text: error.message || 'Please try again shortly.',
                        });
                    }

                    this.setMode(previousMode || 'draft', { silent: true });
                    return null;
                });
        },

        populateDraftList(drafts = []) {
            if (!this.draftSelect) {
                this.bootstrap.drafts = drafts;
                return;
            }

            const summaries = Array.isArray(drafts) ? drafts : [];
            this.bootstrap.drafts = summaries;

            const currentValue = this.draftSelect.value;
            this.draftSelect.innerHTML = '';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select a draft to resume';
            this.draftSelect.appendChild(defaultOption);

            summaries.forEach(summary => {
                const option = document.createElement('option');
                const optionValue = summary.np_file_no || summary.draft_id;
                option.value = optionValue;
                option.dataset.draftId = summary.draft_id;
                const baseLabel = summary.label || `Draft ${summary.draft_id.slice(0, 8)}`;
                if (summary.np_file_no && !baseLabel.includes(summary.np_file_no)) {
                    option.textContent = `${summary.np_file_no} • ${baseLabel}`;
                } else {
                    option.textContent = baseLabel;
                }
                if (summary.is_current || summary.draft_id === this.state.draftId) {
                    option.selected = true;
                }
                this.draftSelect.appendChild(option);
            });

            if (this.draftEmptyState) {
                this.draftEmptyState.classList.toggle('hidden', summaries.length > 0);
            }

            if (!this.draftSelect.value && currentValue) {
                this.draftSelect.value = currentValue;
            }

            if (!this.draftSelect.value && this.state.mode === 'draft' && summaries.length) {
                this.draftSelect.value = summaries[0].np_file_no || summaries[0].draft_id;
            }

            if (this.draftPanel) {
                this.draftPanel.classList.toggle('hidden', this.state.mode !== 'draft' || summaries.length === 0);
            }
        },

        updateDraftSummaries(drafts) {
            if (!Array.isArray(drafts)) {
                return;
            }

            this.bootstrap.drafts = drafts;
            this.markCurrentDraftInSummaries();
            this.populateDraftList(this.bootstrap.drafts);
        },

        upsertDraftSummary(summary) {
            if (!summary || !summary.draft_id) {
                return;
            }

            if (!Array.isArray(this.bootstrap.drafts)) {
                this.bootstrap.drafts = [];
            }

            const drafts = this.bootstrap.drafts.slice();
            const index = drafts.findIndex(item => item.draft_id === summary.draft_id);
            const enhancedSummary = {
                ...summary,
                last_saved_at: summary.last_saved_at || summary.lastSavedAt || null,
                is_current: summary.draft_id === this.state.draftId,
                np_file_no: summary.np_file_no || summary.npFileNo || summary.file_number || summary.fileNo || null,
            };

            if (index >= 0) {
                drafts[index] = { ...drafts[index], ...enhancedSummary };
            } else {
                drafts.unshift(enhancedSummary);
            }

            drafts.sort((a, b) => {
                const aTime = a.last_saved_at ? new Date(a.last_saved_at).getTime() : 0;
                const bTime = b.last_saved_at ? new Date(b.last_saved_at).getTime() : 0;
                return bTime - aTime;
            });

            this.bootstrap.drafts = drafts;
            this.populateDraftList(this.bootstrap.drafts);
        },

        markCurrentDraftInSummaries() {
            if (!Array.isArray(this.bootstrap.drafts)) {
                return;
            }

            const currentId = this.state.draftId;
            this.bootstrap.drafts = this.bootstrap.drafts.map(summary => ({
                ...summary,
                is_current: summary.draft_id === currentId,
            }));
        },

        updateDraftLocatorDisplay() {
            if (!this.lookupCurrentDisplay) {
                return;
            }

            const currentFileNo = this.state.npFileNo || this.bootstrap.np_file_no || '';
            this.lookupCurrentDisplay.textContent = currentFileNo || 'Not assigned yet';
            this.lookupCurrentDisplay.classList.toggle('text-blue-700', Boolean(currentFileNo));
            this.lookupCurrentDisplay.classList.toggle('text-gray-400', !currentFileNo);
        },

        showDraftLocatorFeedback(message, variant = 'info') {
            if (!this.lookupFeedback) {
                return;
            }

            const variants = {
                info: 'text-blue-700',
                success: 'text-green-600',
                error: 'text-red-600',
            };

            const variantClass = variants[variant] || variants.info;
            this.lookupFeedback.className = `mt-1 text-xs font-medium ${variantClass}`;
            this.lookupFeedback.textContent = message;
            this.lookupFeedback.classList.remove('hidden');
        },

        hideDraftLocatorFeedback() {
            if (!this.lookupFeedback) {
                return;
            }

            this.lookupFeedback.className = 'mt-1 text-xs font-medium hidden';
            this.lookupFeedback.textContent = '';
        },

        loadDraftByIdentifier(identifier) {
            return this.fetchDraft(identifier, {
                fromLookup: true,
                message: 'Draft loaded successfully',
                status: 'info',
                showToast: true,
            });
        },

        fetchDraft(identifier, options = {}) {
            if (!identifier || !this.endpoints.load) {
                return Promise.reject(new Error('Draft identifier or load endpoint missing.'));
            }

            const {
                fromLookup = false,
                message = 'Draft loaded',
                status = 'info',
                showToast = false,
                resetForm = true,
                announceStatus = true,
            } = options;

            const url = this.endpoints.load.replace('__DRAFT_ID__', encodeURIComponent(identifier));

            if (announceStatus) {
                this.updateStatus('Loading draft…', 'loading');
            }

            if (fromLookup) {
                this.showDraftLocatorFeedback('Fetching draft details…', 'info');
            }

            this.state.isFetching = true;

            return fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(async (response) => {
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const errorMessage = payload.message || `Unable to load draft (status ${response.status})`;
                        throw new Error(errorMessage);
                    }

                    if (payload.success === false) {
                        throw new Error(payload.message || 'Unable to load draft');
                    }

                    return payload;
                })
                .then((payload) => {
                    this.applyLoadedDraft(payload, {
                        resetForm,
                        message,
                        status,
                        showToast,
                        fromLookup,
                    });

                    if (fromLookup) {
                        this.showDraftLocatorFeedback('Draft loaded successfully.', 'success');
                        if (this.lookupInput) {
                            this.lookupInput.value = '';
                            this.lookupInput.blur();
                        }
                    }

                    return payload;
                })
                .catch((error) => {
                    console.error('[DraftAutosave] Draft fetch error', error);

                    if (fromLookup) {
                        this.showDraftLocatorFeedback(error.message || 'Unable to load draft.', 'error');
                    } else if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Unable to load draft',
                            text: error.message || 'An unknown error occurred while loading the draft.',
                        });
                    }

                    this.updateStatus('Unable to load draft', 'error');
                    throw error;
                })
                .finally(() => {
                    this.state.isFetching = false;
                });
        },

        applyLoadedDraft(payload, options = {}) {
            if (!payload || typeof payload !== 'object') {
                return;
            }

            const {
                resetForm = true,
                message = 'Draft loaded',
                status = 'info',
                showToast = false,
                fromLookup = false,
            } = options;

            this.restoreFormState(payload.form_state || {}, {
                resetForm,
                announce: false,
            });

            this.state.draftId = payload.draft_id;
            const resolvedFileNo = payload.np_file_no
                || (payload.form_state && payload.form_state.np_fileno)
                || this.state.npFileNo
                || null;
            this.state.npFileNo = resolvedFileNo;
            this.state.version = payload.version ?? 1;
            this.state.lockToken = null;
            this.state.hasPendingChanges = false;

            this.bootstrap.draft_id = payload.draft_id;
            this.bootstrap.np_file_no = resolvedFileNo;
            this.bootstrap.application_id = payload.application_id || null;
            this.bootstrap.form_state = payload.form_state || {};
            this.bootstrap.progress_percent = payload.progress_percent || 0;
            this.bootstrap.last_completed_step = payload.last_completed_step || 1;
            this.bootstrap.auto_save_frequency = payload.auto_save_frequency || this.state.autoSaveFrequency || 30;
            this.bootstrap.version = payload.version ?? 1;
            this.bootstrap.collaborators = payload.collaborators || [];
            this.bootstrap.last_saved_at = payload.last_saved_at || null;
            this.bootstrap.analytics = payload.analytics || {};
            if (Array.isArray(payload.drafts)) {
                this.updateDraftSummaries(payload.drafts);
            } else {
                this.markCurrentDraftInSummaries();
                this.populateDraftList(this.bootstrap.drafts || []);
            }

            this.form.dataset.draftId = payload.draft_id;
            this.form.dataset.draftVersion = payload.version ?? 1;

            const idInput = document.getElementById('draftIdInput');
            const versionInput = document.getElementById('draftVersionInput');
            const stepInput = document.getElementById('draftStepInput');

            if (idInput) {
                idInput.value = payload.draft_id || '';
            }

            if (versionInput) {
                versionInput.value = payload.version ?? 1;
            }

            if (stepInput) {
                stepInput.value = payload.last_completed_step ?? 1;
            }

            this.updateProgressUi(payload.progress_percent, payload.last_completed_step);
            this.updateLastSaved(payload.last_saved_at);
            this.updateAnalytics(payload.analytics);
            this.pushVersionHistory(payload.versions || []);

            if (this.historyButton && ((payload.versions && payload.versions.length) || (payload.version && payload.version > 1))) {
                this.historyButton.classList.remove('hidden');
            }

            this.setAutoSaveFrequency(payload.auto_save_frequency || this.bootstrap.auto_save_frequency || 30);
            this.updateDraftLocatorDisplay();

            if (!fromLookup) {
                this.hideDraftLocatorFeedback();
            }

            if (fromLookup) {
                this.setMode('draft', { silent: true });
            }

            if (message) {
                this.updateStatus(message, status);
            }

            if (showToast && window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: message,
                    showConfirmButton: false,
                    timer: 2000,
                });
            }

            window.PRIMARY_DRAFT_BOOTSTRAP = this.bootstrap;
        },

        handleFieldChange(event) {
            if (this.state.silenceChanges) {
                return;
            }

            if (event && event.target) {
                console.log('[DraftAutosave] Field changed:', {
                    name: event.target.name,
                    type: event.target.type,
                });
            }

            this.state.hasPendingChanges = true;
            this.queueDebouncedSave();
        },

        queueDebouncedSave() {
            if (this.state.debouncedTimer) {
                clearTimeout(this.state.debouncedTimer);
            }

            console.log('[DraftAutosave] Queueing debounced save in 3s');

            this.state.debouncedTimer = setTimeout(() => {
                console.log('[DraftAutosave] Debounced save timer fired');
                this.saveDraft('debounced');
            }, 3000);
        },

        manualSave(options = {}) {
            if (this.state.isSaving) {
                return Promise.resolve();
            }

            return this.saveDraft('manual', options.flash !== false);
        },

        startAutoSaveTimer() {
            if (this.state.autoSaveTimer) {
                clearInterval(this.state.autoSaveTimer);
            }

            this.state.autoSaveTimer = setInterval(() => {
                if (!this.state.isSaving) {
                    this.saveDraft('auto');
                }
            }, this.state.autoSaveFrequency * 1000);
        },

        resetAutoSaveTimer() {
            this.startAutoSaveTimer();
        },

        saveDraft(trigger, showToast = false, options = {}) {
            const { basePayload = null } = options || {};

            console.log('[DraftAutosave] saveDraft called', { trigger, hasPendingChanges: this.state.hasPendingChanges });

            if (!this.state.hasPendingChanges && trigger === 'auto') {
                console.log('[DraftAutosave] Skipping auto-save - no pending changes');
                return Promise.resolve();
            }

            if (!this.endpoints.save) {
                console.warn('[DraftAutosave] Save endpoint not configured.');
                return Promise.resolve();
            }

            if (this.state.isSaving) {
                console.log('[DraftAutosave] Already saving, skipping...');
                return Promise.resolve();
            }

            this.state.isSaving = true;
            this.state.lastSaveTrigger = trigger;
            this.updateStatus('Saving…', 'saving');

            let payload;

            if (basePayload) {
                try {
                    payload = JSON.parse(JSON.stringify(basePayload));
                } catch (error) {
                    payload = { ...basePayload };
                }
            } else {
                payload = this.buildPayload(trigger);
            }

            if (!payload.metadata || typeof payload.metadata !== 'object') {
                payload.metadata = {};
            }

            console.log('[DraftAutosave] Built payload for save', {
                endpoint: this.endpoints.save,
                draft_id: payload.draft_id,
                form_state_keys: Object.keys(payload.form_state || {}),
                has_np_fileno: !!(payload.form_state && payload.form_state.np_fileno),
                payload_size: JSON.stringify(payload).length,
            });

            const request = fetch(this.endpoints.save, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                body: JSON.stringify(payload),
            })
                .then(async (response) => {
                    this.state.lastResponse = response;

                    console.log('[DraftAutosave] Save response received', { status: response.status, ok: response.ok });

                    if (response.status === 409) {
                        const data = await response.json();
                        this.handleConflict(data);
                        return null;
                    }

                    if (response.status === 423) {
                        const data = await response.json().catch(() => ({}));
                        throw new Error(data.message || 'Draft is locked.');
                    }

                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        console.error('[DraftAutosave] Save failed', { status: response.status, error: errorData });
                        throw new Error(errorData.message || `Draft save failed with status ${response.status}`);
                    }

                    return response.json();
                })
                .then((data) => {
                    if (!data) {
                        return;
                    }

                    console.log('[DraftAutosave] Draft saved successfully', {
                        draft_id: data.draft_id,
                        version: data.version,
                        np_file_no: data.np_file_no,
                        is_gap_filled: data.gap_filling_info?.is_gap_filled || false,
                    });

                    this.state.draftId = data.draft_id;
                    const savedFileNo = data.np_file_no
                        || (payload && payload.form_state && payload.form_state.np_fileno)
                        || this.state.npFileNo
                        || null;
                    this.state.npFileNo = savedFileNo;
                    
                    // Show gap-filling notification if applicable
                    if (data.gap_filling_info && data.gap_filling_info.is_gap_filled) {
                        this.showGapFillingNotification(data.gap_filling_info);
                    }
                    this.state.version = data.version;
                    this.state.lockToken = data.lock_token || null;
                    this.state.hasPendingChanges = false;

                    this.form.dataset.draftId = data.draft_id;
                    this.form.dataset.draftVersion = data.version;

                    const idInput = document.getElementById('draftIdInput');
                    const versionInput = document.getElementById('draftVersionInput');
                    const stepInput = document.getElementById('draftStepInput');

                    if (idInput) idInput.value = data.draft_id;
                    if (versionInput) versionInput.value = data.version;
                    if (stepInput) stepInput.value = payload.metadata.last_completed_step;

                    this.bootstrap.draft_id = data.draft_id;
                    this.bootstrap.np_file_no = savedFileNo;
                    this.bootstrap.version = data.version;
                    this.bootstrap.progress_percent = data.progress_percent;
                    this.bootstrap.last_completed_step = data.last_completed_step;
                    this.bootstrap.auto_save_frequency = data.auto_save_frequency || this.state.autoSaveFrequency;
                    this.bootstrap.last_saved_at = data.last_saved_at || null;
                    this.bootstrap.analytics = data.analytics || this.bootstrap.analytics || {};
                    if (data.draft_summary) {
                        this.upsertDraftSummary(data.draft_summary);
                    } else {
                        this.markCurrentDraftInSummaries();
                        this.populateDraftList(this.bootstrap.drafts || []);
                    }
                    window.PRIMARY_DRAFT_BOOTSTRAP = this.bootstrap;

                    this.updateProgressUi(data.progress_percent, data.last_completed_step);
                    this.updateStatus('All changes saved', 'saved');
                    this.updateAnalytics(data.analytics);
                    this.updateLastSaved(data.last_saved_at);
                    this.updateDraftLocatorDisplay();
                    if (this.historyButton) {
                        this.historyButton.classList.remove('hidden');
                    }

                    if (showToast && window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Draft saved',
                            showConfirmButton: false,
                            timer: 2000,
                        });
                    }
                })
                .catch((error) => {
                    console.error('[DraftAutosave] Save failed', {
                        error: error.message,
                        stack: error.stack,
                        endpoint: this.endpoints.save,
                    });
                    this.updateStatus('Auto-save failed. Retrying…', 'error');
                    this.state.hasPendingChanges = true;

                    if (showToast && window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: 'Draft save failed',
                            text: error.message,
                            showConfirmButton: true,
                        });
                    }
                })
                .finally(() => {
                    this.state.isSaving = false;
                    console.log('[DraftAutosave] Save operation completed');
                });

            return request;
        },

        buildPayload(trigger) {
            const formState = this.serializeForm();
            const progress = this.calculateProgress(formState);
            const lastStep = this.getActiveStep();

            if (formState && typeof formState === 'object' && formState.np_fileno) {
                this.state.npFileNo = formState.np_fileno;
                this.bootstrap.np_file_no = formState.np_fileno;
            }

            // Get sub_application_id and main_application_id from bootstrap or form data attributes
            const subApplicationId = this.bootstrap.sub_application_id 
                || (this.form.dataset.subApplicationId ? Number(this.form.dataset.subApplicationId) : null);
            const mainApplicationId = this.bootstrap.main_application_id 
                || (this.form.dataset.mainApplicationId ? Number(this.form.dataset.mainApplicationId) : null);

            return {
                draft_id: this.state.draftId,
                sub_application_id: subApplicationId,
                main_application_id: mainApplicationId,
                form_state: formState,
                metadata: {
                    progress_percent: progress,
                    last_completed_step: lastStep,
                    auto_save_frequency: this.state.autoSaveFrequency,
                    trigger,
                    client_version: this.state.version,
                    lock_token: this.state.lockToken,
                    collaborators: this.bootstrap.collaborators || [],
                },
            };
        },

        serializeForm() {
            console.log('[DraftAutosave] Starting form serialization');
            
            const result = {};
            const radioGroups = new Map();
            const elements = Array.from(this.form ? this.form.elements || [] : []);
            
            console.log('[DraftAutosave] Total form elements:', elements.length);

            // Field name mappings: ensure consistency with PrimaryFormController->store()
            // Maps: formFieldName -> expectedDatabaseFieldName
            const FIELD_NAME_MAPPINGS = {
                'applicant_type': 'applicantType',
                'residential_type': 'residenceType',
                'identification_type': 'idType',
                // Owner email specifically maps to owner_email (not email)
                // But form already uses owner_email, so no mapping needed
            };

            const ensureArray = (key) => {
                if (!Array.isArray(result[key])) {
                    result[key] = [];
                }
            };

            elements.forEach((element) => {
                if (!element) {
                    return;
                }

                const rawName = element.name || (element.dataset ? element.dataset.draftField : null);
                if (!rawName) {
                    return;
                }

                if (element.dataset && element.dataset.draftExclude === 'true') {
                    return;
                }

                const type = (element.type || '').toLowerCase();
                const isMultiple = rawName.endsWith('[]');
                const baseName = isMultiple ? rawName.slice(0, -2) : rawName;
                
                // Apply field name mapping to ensure consistency with store() method
                const key = FIELD_NAME_MAPPINGS[baseName] || baseName;

                if (type === 'file') {
                    const files = Array.from(element.files || []);
                    if (!result.__files) {
                        result.__files = {};
                    }
                    result.__files[key] = files.map((file) => ({
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        lastModified: file.lastModified,
                    }));
                    return;
                }

                if (type === 'checkbox') {
                    if (isMultiple) {
                        ensureArray(key);
                        if (element.checked) {
                            const checkedValue = element.value === 'on' ? 'on' : element.value;
                            result[key].push(checkedValue);
                        }
                    } else {
                        const checkedValue = element.value === 'on' ? true : (element.value ?? true);
                        result[key] = element.checked ? checkedValue : false;
                    }
                    return;
                }

                if (type === 'radio') {
                    if (!radioGroups.has(key)) {
                        radioGroups.set(key, null);
                    }
                    if (element.checked) {
                        radioGroups.set(key, element.value);
                    }
                    return;
                }

                if (element.tagName === 'SELECT' && element.multiple) {
                    result[key] = Array.from(element.options)
                        .filter((option) => option.selected)
                        .map((option) => option.value);
                    return;
                }

                let value;
                if (element.hasAttribute('contenteditable')) {
                    value = element.innerHTML;
                } else if (element.tagName === 'TEXTAREA' && element.dataset && element.dataset.draftSerialize === 'html') {
                    value = element.innerHTML;
                } else {
                    value = element.value;
                }

                if (isMultiple) {
                    ensureArray(key);
                    result[key].push(value);
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(result, key)) {
                    if (!Array.isArray(result[key])) {
                        result[key] = [result[key]];
                    }
                    result[key].push(value);
                } else {
                    result[key] = value;
                }
            });

            radioGroups.forEach((value, key) => {
                result[key] = value;
            });

            const extraDraftFields = this.form ? this.form.querySelectorAll('[data-draft-field]:not([name])') : [];
            extraDraftFields.forEach((element) => {
                const key = element.dataset.draftField;
                if (!key || Object.prototype.hasOwnProperty.call(result, key)) {
                    return;
                }

                const serializeMode = element.dataset.draftSerialize || 'text';
                if (serializeMode === 'html') {
                    result[key] = element.innerHTML;
                } else {
                    result[key] = element.textContent;
                }
            });

            console.log('[DraftAutosave] Serialization complete:', {
                totalKeys: Object.keys(result).length,
                keys: Object.keys(result).sort(),
                hasFiles: !!result.__files,
                arrayFields: Object.keys(result).filter(k => Array.isArray(result[k])),
                sampleData: Object.fromEntries(Object.entries(result).slice(0, 10))
            });

            return result;
        },

        restoreFormState(state, options = {}) {
            console.log('[DraftAutosave] Starting form restoration', { stateKeys: Object.keys(state).length });
            
            const { resetForm = false, announce = true } = options;
            const keys = state && typeof state === 'object' ? Object.keys(state) : [];

            let applied = false;

            this.state.silenceChanges = true;

            try {
                if (resetForm) {
                    this.form.reset();
                }

                if (!keys.length) {
                    console.log('[DraftAutosave] No keys to restore');
                    return;
                }

                // Reverse field name mappings for restoration
                // Maps: databaseFieldName -> formFieldName
                const REVERSE_FIELD_MAPPINGS = {
                    'applicantType': 'applicant_type',
                    'residenceType': 'residential_type',
                    'idType': 'identification_type',
                };

                // Avoid overwriting security sensitive values
                const disallowed = ['_token', 'password', 'password_confirmation'];
                const arrayAssignmentIndices = {};
                const dynamicContext = this.prepareDynamicCollections(state);
                const skipKeys = dynamicContext && dynamicContext.skipKeys ? dynamicContext.skipKeys : null;

                keys.forEach((key) => {
                    if (disallowed.includes(key) || key === '__files') {
                        return;
                    }

                    if (skipKeys && skipKeys.has(key)) {
                        return;
                    }

                    const value = state[key];
                    
                    // Try mapped form field name first, fallback to direct key
                    const formFieldName = REVERSE_FIELD_MAPPINGS[key] || key;
                    let elements = this.form.querySelectorAll(`[name="${formFieldName}"]`);

                    // Try with array notation if value is array
                    if (!elements.length && Array.isArray(value)) {
                        elements = this.form.querySelectorAll(`[name="${formFieldName}[]"]`);
                    }

                    // If still no match with mapped name, try original key
                    if (!elements.length && formFieldName !== key) {
                        elements = this.form.querySelectorAll(`[name="${key}"]`);
                        if (!elements.length && Array.isArray(value)) {
                            elements = this.form.querySelectorAll(`[name="${key}[]"]`);
                        }
                    }

                    // Handle bracket notation (for nested arrays like records[0][field])
                    if (!elements.length && typeof key === 'string' && key.includes('[')) {
                        elements = this.form.querySelectorAll(`[name='${key}']`);
                    }
                    if (!elements.length && typeof formFieldName === 'string' && formFieldName.includes('[')) {
                        elements = this.form.querySelectorAll(`[name='${formFieldName}']`);
                    }

                    if (!elements.length) {
                        console.log('[DraftAutosave] No elements found for key:', key, 'formFieldName:', formFieldName);
                        return;
                    }

                    console.log('[DraftAutosave] Restoring field:', key, 'to', elements.length, 'elements');
                    applied = true;

                    elements.forEach((element) => {
                        if (element.type === 'checkbox') {
                            if (Array.isArray(value)) {
                                element.checked = value.some((entry) => entry === element.value || entry === true);
                            } else {
                                element.checked = Boolean(value === element.value || value === true);
                            }
                            element.dispatchEvent(new Event('change', { bubbles: true }));
                            return;
                        }

                        if (element.type === 'radio') {
                            element.checked = element.value === value;
                            if (element.checked) {
                                element.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            return;
                        }

                        if (element.tagName === 'SELECT' && element.multiple && Array.isArray(value)) {
                            Array.from(element.options).forEach((option) => {
                                option.selected = value.includes(option.value);
                            });
                            element.dispatchEvent(new Event('change', { bubbles: true }));
                            return;
                        }

                        if (Array.isArray(value)) {
                            const index = arrayAssignmentIndices[key] || 0;
                            const assignedValue = value[index];
                            arrayAssignmentIndices[key] = index + 1;
                            element.value = assignedValue !== undefined ? assignedValue : '';
                        } else if (typeof value === 'object' && value !== null) {
                            element.value = JSON.stringify(value);
                        } else {
                            element.value = value;
                        }

                        element.dispatchEvent(new Event('input', { bubbles: true }));
                        element.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });

                if (this.form) {
                    const draftFieldNodes = this.form.querySelectorAll('[data-draft-field]:not([name])');
                    draftFieldNodes.forEach((node) => {
                        const fieldKey = node.dataset.draftField;
                        if (!fieldKey || !Object.prototype.hasOwnProperty.call(state, fieldKey)) {
                            return;
                        }

                        const fieldValue = state[fieldKey];
                        const serializeMode = node.dataset.draftSerialize || 'text';
                        if (serializeMode === 'html') {
                            node.innerHTML = fieldValue || '';
                        } else {
                            node.textContent = fieldValue || '';
                        }
                    });
                }
            } finally {
                this.state.silenceChanges = false;
                this.state.hasPendingChanges = false;
            }

            if (announce && applied) {
                this.updateStatus('Recovered draft data', 'info');
            }
        },

        prepareDynamicCollections(state) {
            const skipKeys = new Set();

            if (!state || typeof state !== 'object') {
                return { skipKeys };
            }

            try {
                this.ensureBuyerRowsFromState(state, skipKeys);
            } catch (error) {
                console.warn('[DraftAutosave] Unable to hydrate buyer rows', error);
            }

            try {
                this.ensureMultipleOwnersFromState(state, skipKeys);
            } catch (error) {
                console.warn('[DraftAutosave] Unable to hydrate multiple owner rows', error);
            }

            return { skipKeys };
        },

        ensureBuyerRowsFromState(state, skipKeys) {
            const recordKeys = Object.keys(state || {}).filter((key) => /^records\[\d+\]/.test(key));
            const records = this.parseBuyerRecords(state);
            const count = records.length;

            if (Array.isArray(state.records)) {
                skipKeys.add('records');
            }

            if (count === 0 && recordKeys.length === 0) {
                return;
            }

            if (typeof window.populateBuyersFromState === 'function') {
                window.populateBuyersFromState(records);
                recordKeys.forEach((key) => skipKeys.add(key));
            } else if (typeof window.ensureBuyerRowCount === 'function') {
                window.ensureBuyerRowCount(Math.max(1, count || this.detectBuyerRecordMax(recordKeys)));
            } else {
                this.ensureBuyerRowStructureFallback(Math.max(1, count || this.detectBuyerRecordMax(recordKeys)));
            }
        },

        detectBuyerRecordMax(recordKeys) {
            if (!Array.isArray(recordKeys) || recordKeys.length === 0) {
                return 0;
            }

            const indices = recordKeys.map((key) => {
                const match = key.match(/^records\[(\d+)\]/);
                return match ? Number(match[1]) : 0;
            });

            return Math.max(...indices) + 1;
        },

        parseBuyerRecords(state) {
            const records = Array.isArray(state.records) ? state.records.map((record) => ({ ...(record || {}) })) : [];

            Object.entries(state || {}).forEach(([key, value]) => {
                const match = key.match(/^records\[(\d+)\]\[(.+)\]$/);
                if (!match) {
                    return;
                }

                const index = Number(match[1]);
                const field = match[2];

                if (!records[index]) {
                    records[index] = {};
                }

                records[index][field] = value;
            });

            // Only keep records that carry at least one buyer-identifying value. A fresh
            // application ships with a blank buyer row whose fields get serialized into
            // the draft. We must ignore auto-defaulted fields (e.g. landUse, which is
            // pre-filled to the fixed land use on non-mixed forms) when deciding whether
            // a row is real - otherwise every blank row looks "meaningful" and the
            // buyers container gets auto-shown on reload.
            const identityFields = [
                'buyerTitle', 'customTitle',
                'firstName', 'middleName', 'surname',
                'unit_no', 'block_no', 'blockNo',
                'sectionNumber', 'unitMeasurement', 'cubicMeasurement',
            ];

            return records.filter((record) => {
                if (!record || typeof record !== 'object') {
                    return false;
                }

                return identityFields.some((field) => String(record[field] ?? '').trim() !== '');
            });
        },

        ensureBuyerRowStructureFallback(targetCount) {
            const container = document.getElementById('buyers-container');
            if (!container) {
                return;
            }

            const desired = Math.max(1, Number.isFinite(Number(targetCount)) ? Number(targetCount) : 1);
            let current = container.querySelectorAll('.buyer-row').length;

            while (current < desired) {
                if (typeof window.addBuyer === 'function') {
                    window.addBuyer();
                } else {
                    break;
                }
                current = container.querySelectorAll('.buyer-row').length;
            }
        },

        ensureMultipleOwnersFromState(state, skipKeys) {
            const parsed = this.parseMultipleOwnerData(state);
            if (!parsed || parsed.count === 0) {
                return;
            }

            if (typeof window.populateMultipleOwnersFromState === 'function') {
                window.populateMultipleOwnersFromState(parsed.payload);
            } else if (typeof window.ensureOwnerRowCount === 'function') {
                window.ensureOwnerRowCount(parsed.count);
            } else {
                this.ensureOwnerRowStructureFallback(parsed.count);
            }

            parsed.skipKeys.forEach((key) => skipKeys.add(key));
        },

        parseMultipleOwnerData(state) {
            if (!state || typeof state !== 'object') {
                return null;
            }

            const names = Array.isArray(state.multiple_owners_names) ? state.multiple_owners_names : [];
            const addresses = Array.isArray(state.multiple_owners_address) ? state.multiple_owners_address : [];
            const emails = Array.isArray(state.multiple_owners_email) ? state.multiple_owners_email : [];
            const phones = Array.isArray(state.multiple_owners_phone) ? state.multiple_owners_phone : [];

            const idTypes = {};
            Object.entries(state).forEach(([key, value]) => {
                const match = key.match(/^multiple_owners_identification_type\[(\d+)\]$/);
                if (match) {
                    idTypes[key] = value;
                }
            });

            const count = Math.max(names.length, addresses.length, emails.length, phones.length, Object.keys(idTypes).length);

            if (count === 0) {
                return null;
            }

            const payload = {
                multiple_owners_names: names,
                multiple_owners_address: addresses,
                multiple_owners_email: emails,
                multiple_owners_phone: phones,
                ...idTypes,
            };

            const skipKeys = new Set([
                'multiple_owners_names',
                'multiple_owners_address',
                'multiple_owners_email',
                'multiple_owners_phone',
                ...Object.keys(idTypes),
            ]);

            return { count, payload, skipKeys };
        },

        ensureOwnerRowStructureFallback(targetCount) {
            const container = document.getElementById('ownersContainer');
            if (!container) {
                return;
            }

            let current = container.children.length;
            const desired = Math.max(0, Number.isFinite(Number(targetCount)) ? Number(targetCount) : 0);

            while (current < desired) {
                if (typeof window.addOwnerRow === 'function') {
                    window.addOwnerRow();
                } else {
                    break;
                }
                current = container.children.length;
            }
        },

        checkForRecoveryPrompt() {
            if (!this.bootstrap || !this.bootstrap.form_state || Object.keys(this.bootstrap.form_state).length === 0) {
                return;
            }

            if (window.Swal) {
                Swal.fire({
                    title: 'Resume working on this draft?',
                    text: 'We found autosaved changes. Continue from where you left off?',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, continue',
                    cancelButtonText: 'Start fresh',
                }).then((result) => {
                    if (!result.isConfirmed) {
                        this.form.reset();
                        this.state.hasPendingChanges = false;
                    }
                });
            }
        },

        handleConflict(data) {
            this.updateStatus('Draft out of sync', 'error');

            if (!window.Swal) {
                return;
            }

            Swal.fire({
                title: 'Newer changes detected',
                html: '<p class="text-left">Someone else saved a newer version of this draft. Choose how to proceed.</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#f97316',
                confirmButtonText: 'Reload latest',
                cancelButtonText: 'Keep my version',
            }).then((result) => {
                if (result.isConfirmed) {
                    this.fetchLatestDraft();
                } else {
                    this.state.version = data.server_version || this.state.version;
                    this.state.hasPendingChanges = true;
                }
            });
        },

        fetchLatestDraft() {
            if (!this.state.draftId) {
                return Promise.resolve(null);
            }

            return this.fetchDraft(this.state.draftId, {
                message: 'Draft synchronized with server',
                status: 'info',
                showToast: false,
                resetForm: true,
                announceStatus: true,
            });
        },

        updateStatus(message, state) {
            if (this.statusText) {
                this.statusText.textContent = message;
                this.statusText.dataset.state = state;
            }
        },

        updateProgressUi(progress, step) {
            if (this.progressBar) {
                this.progressBar.style.width = `${Math.min(100, Math.max(0, progress || 0))}%`;
            }

            if (this.progressValue) {
                this.progressValue.textContent = `${Math.round(progress || 0)}%`;
            }

            const stepInput = document.getElementById('draftStepInput');
            if (stepInput) {
                stepInput.value = step;
            }
        },

        updateLastSaved(isoString) {
            if (!this.lastSavedText) {
                return;
            }

            if (!isoString) {
                this.lastSavedText.textContent = 'Last saved: just now';
                return;
            }

            const date = new Date(isoString);
            this.lastSavedText.textContent = `Last saved: ${date.toLocaleString()}`;
        },

        calculateProgress(formState) {
            const required = [
                'applicantType',
                'scheme_no',
                'property_street_name',
                'property_lga',
                'property_state',
                'land_use',
                'records',
            ];

            let completed = 0;
            required.forEach((field) => {
                const value = formState[field];
                if (Array.isArray(value)) {
                    if (value.length > 0) {
                        completed += 1;
                    }
                    return;
                }
                if (value && value !== '[]') {
                    completed += 1;
                }
            });

            return Math.round((completed / required.length) * 100);
        },

        getActiveStep() {
            const activeSection = document.querySelector('.form-section.active');
            if (!activeSection) {
                return Number(document.getElementById('draftStepInput')?.value || 1);
            }
            const stepNumber = parseInt(activeSection.id.replace('step', ''), 10);
            return Number.isNaN(stepNumber) ? 1 : stepNumber;
        },

        installBeforeUnload() {
            this.beforeUnloadHandler = (event) => {
                if (this.state.hasPendingChanges) {
                    event.preventDefault();
                    event.returnValue = '';
                    return '';
                }
                return undefined;
            };

            window.addEventListener('beforeunload', this.beforeUnloadHandler);
        },

        scheduleSessionWarning() {
            // Session warning disabled - no longer showing session expiry alerts
            return;
        },

     

        openHistoryModal() {
            if (!this.state.draftId || !this.endpoints.analytics || !window.Swal) {
                return;
            }

            const url = this.endpoints.analytics.replace('__DRAFT_ID__', this.state.draftId);

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(res => res.json())
                .then((data) => {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to load analytics');
                    }

                    const analytics = data.analytics || {};
                    const versions = data.versions || [];
                    const versionItems = versions.length
                        ? versions.map((version, index) => {
                            const timestamp = version.created_at ? new Date(version.created_at).toLocaleString() : 'Unknown';
                            return `<button type="button" data-version-index="${index}" class="w-full text-left px-3 py-2 border border-blue-100 rounded-md hover:bg-blue-50 transition">`
                                + `<span class="block text-sm font-semibold text-blue-900">Version ${version.version}</span>`
                                + `<span class="block text-xs text-blue-600">Saved ${timestamp}</span>`
                                + `</button>`;
                        }).join('')
                        : '<p class="text-sm text-gray-500">No version history yet.</p>';

                    const html = `
                        <div class="grid gap-4 text-left">
                            <div class="space-y-1">
                                <p><strong>Total saves:</strong> ${analytics.total_saves || 0}</p>
                                <p><strong>Manual saves:</strong> ${analytics.manual_saves || 0}</p>
                                <p><strong>Auto-saves:</strong> ${analytics.auto_saves || 0}</p>
                                <p><strong>Debounced saves:</strong> ${analytics.debounced_saves || 0}</p>
                                <p><strong>Collaborators:</strong> ${analytics.collaborator_count || (analytics.collaborators ? analytics.collaborators.length : 1)}</p>
                                <p><strong>Versions:</strong> ${versions.length}</p>
                            </div>
                            <div class="space-y-2">
                                <h4 class="text-sm font-semibold text-blue-900">Version history</h4>
                                <div class="space-y-2 max-h-48 overflow-y-auto" id="draftVersionList">${versionItems}</div>
                                <pre id="draftVersionPreview" class="bg-gray-900 text-green-200 text-xs rounded-md p-3 overflow-x-auto max-h-48">Select a version to preview its snapshot.</pre>
                            </div>
                        </div>
                    `;

                    Swal.fire({
                        title: 'Draft activity',
                        html,
                        icon: 'info',
                        width: 720,
                        didOpen: () => {
                            const popup = Swal.getPopup();
                            if (!popup) {
                                return;
                            }

                            const preview = popup.querySelector('#draftVersionPreview');
                            popup.querySelectorAll('[data-version-index]').forEach(button => {
                                button.addEventListener('click', () => {
                                    const index = Number(button.dataset.versionIndex);
                                    const snapshot = versions[index]?.snapshot || {};
                                    preview.textContent = JSON.stringify(snapshot, null, 2);
                                });
                            });
                        }
                    });
                })
                .catch((error) => {
                    console.error('[DraftAutosave] Analytics error', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Unable to load draft analytics',
                    });
                });
        },

        updateAnalytics(analytics) {
            if (!analytics) {
                return;
            }

            if (this.collaboratorCountEl) {
                if (Array.isArray(analytics.collaborators)) {
                    this.collaboratorCountEl.textContent = analytics.collaborators.length;
                } else if (typeof analytics.collaborator_count === 'number') {
                    this.collaboratorCountEl.textContent = analytics.collaborator_count;
                }
            }
        },

        pushVersionHistory(versions) {
            this.bootstrap.versions = versions;
        },

        openShareDialog() {
            if (!this.state.draftId || !this.endpoints.share) {
                return;
            }

            const prompt = window.Swal ? Swal : null;
            if (!prompt) {
                const entry = window.prompt('Enter user IDs separated by commas');
                if (entry) {
                    const ids = entry.split(',').map(value => parseInt(value.trim(), 10)).filter(Number.isInteger);
                    this.shareDraft(ids);
                }
                return;
            }

            prompt.fire({
                title: 'Share draft',
                input: 'text',
                inputLabel: 'Enter user IDs separated by commas',
                inputPlaceholder: 'e.g. 12, 42, 73',
                showCancelButton: true,
                confirmButtonText: 'Share access',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Provide at least one user ID';
                    }

                    const invalid = value.split(',').map(val => val.trim()).filter(val => val && !/^\d+$/.test(val));
                    if (invalid.length) {
                        return `Invalid IDs: ${invalid.join(', ')}`;
                    }

                    return null;
                }
            }).then(result => {
                if (result.isConfirmed && result.value) {
                    const ids = result.value.split(',').map(val => parseInt(val.trim(), 10)).filter(Number.isInteger);
                    this.shareDraft(ids);
                }
            });
        },

        shareDraft(userIds) {
            if (!userIds.length) {
                return;
            }

            fetch(this.endpoints.share, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                body: JSON.stringify({
                    draft_id: this.state.draftId,
                    user_ids: userIds,
                    role: 'editor'
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to share draft');
                    }

                    this.updateStatus('Draft shared successfully', 'info');
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Draft shared',
                            text: 'Invited collaborators can now access this draft.'
                        });
                    }

                    if (Array.isArray(data.collaborators) && this.collaboratorCountEl) {
                        this.collaboratorCountEl.textContent = data.collaborators.length;
                    }
                })
                .catch(error => {
                    console.error('[DraftAutosave] Share error', error);
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Unable to share draft',
                            text: error.message
                        });
                    }
                });
        },

        exportDraft() {
            if (!this.state.draftId || !this.endpoints.export) {
                return;
            }

            const url = this.endpoints.export.replace('__DRAFT_ID__', this.state.draftId);
            window.open(url, '_blank');
        },

        cleanupTimers() {
            if (this.state.debouncedTimer) {
                clearTimeout(this.state.debouncedTimer);
                this.state.debouncedTimer = null;
            }

            if (this.state.autoSaveTimer) {
                clearInterval(this.state.autoSaveTimer);
                this.state.autoSaveTimer = null;
            }

            if (this.state.sessionWarningTimer) {
                clearTimeout(this.state.sessionWarningTimer);
                this.state.sessionWarningTimer = null;
            }
        },

        finalizeAfterSubmit() {
            this.cleanupTimers();
            this.state.hasPendingChanges = false;
            this.state.draftId = null;
            this.state.version = 0;
            if (this.beforeUnloadHandler) {
                window.removeEventListener('beforeunload', this.beforeUnloadHandler);
                this.beforeUnloadHandler = null;
            }
            this.updateStatus('Draft submitted', 'submitted');
        },

        getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) {
                return meta.getAttribute('content');
            }

            const tokenInput = this.form.querySelector('input[name="_token"]');
            return tokenInput ? tokenInput.value : '';
        },
        
        /**
         * Show gap-filling notification when a file number fills a gap
         * 
         * @param {Object} gapInfo - Gap filling information
         * @param {boolean} gapInfo.is_gap_filled - Whether this is a gap-filled number
         * @param {string} gapInfo.file_number - The assigned file number
         * @param {string} gapInfo.reason - Reason for gap
         * @param {string} gapInfo.next_new_number - What the next NEW number would be
         */
        showGapFillingNotification(gapInfo) {
            if (!gapInfo || !gapInfo.is_gap_filled) {
                return;
            }

            console.info('[DraftAutosave] Gap-filled file number assigned:', gapInfo.file_number);

            const notification = document.createElement('div');
            notification.className = 'gap-filling-notification bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded shadow-sm animate-fade-in';
            notification.setAttribute('role', 'alert');
            notification.setAttribute('aria-live', 'polite');
            
            notification.innerHTML = `
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-blue-800 mb-1">
                            📋 File Number Assigned: <span class="font-bold">${this.escapeHtml(gapInfo.file_number)}</span>
                        </h3>
                        <div class="text-sm text-blue-700 space-y-2">
                            <p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-200 text-blue-800 mr-2">
                                    🔄 Gap-Filled
                                </span>
                                ${this.escapeHtml(gapInfo.reason)}
                            </p>
                            <p class="text-xs text-blue-600">
                                ℹ️ You are filling a gap to maintain sequential numbering. 
                                The next <strong>new</strong> file number will be <strong>${this.escapeHtml(gapInfo.next_new_number)}</strong>.
                            </p>
                            <p class="text-xs text-blue-600 font-medium">
                                ✓ This ensures no gaps in the final file number sequence!
                            </p>
                        </div>
                    </div>
                    <div class="ml-3 flex-shrink-0">
                        <button type="button" 
                                class="gap-notification-dismiss inline-flex text-blue-400 hover:text-blue-600 focus:outline-none transition-colors"
                                aria-label="Dismiss notification">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" 
                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" 
                                      clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            `;
            
            // Insert at top of form
            const targetElement = this.form.parentElement || document.querySelector('.form-container') || this.form;
            targetElement.insertBefore(notification, targetElement.firstChild);
            
            // Add indicator to file number field
            this.addGapFillingIndicator(gapInfo.file_number);
            
            // Add dismiss button handler
            const dismissButton = notification.querySelector('.gap-notification-dismiss');
            dismissButton.addEventListener('click', () => {
                this.dismissNotification(notification);
            });
            
            // Auto-dismiss after 15 seconds
            setTimeout(() => {
                this.dismissNotification(notification);
            }, 15000);
        },
        
        /**
         * Add visual indicator to file number field
         */
        addGapFillingIndicator(fileNumber) {
            const fileNumberInput = document.querySelector('#np_fileno') 
                || document.querySelector('input[name="np_fileno"]')
                || document.querySelector('[data-field="np_fileno"]');
            
            if (!fileNumberInput || !fileNumberInput.parentElement) {
                return;
            }
            
            // Remove existing indicator
            const existingIndicator = fileNumberInput.parentElement.querySelector('.gap-filled-indicator');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            const indicator = document.createElement('span');
            indicator.className = 'gap-filled-indicator inline-flex items-center ml-2 px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 animate-fade-in';
            indicator.innerHTML = `
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Gap-Filled
            `;
            indicator.title = 'This file number fills a gap from an expired/released reservation';
            
            // Insert after input or after input's container
            if (fileNumberInput.nextSibling) {
                fileNumberInput.parentElement.insertBefore(indicator, fileNumberInput.nextSibling);
            } else {
                fileNumberInput.parentElement.appendChild(indicator);
            }
        },
        
        /**
         * Dismiss notification with animation
         */
        dismissNotification(notification) {
            if (!notification) return;
            
            notification.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        },
        
        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    };

    window.PrimaryDraftAutosave = DraftAutosave;

    document.addEventListener('DOMContentLoaded', () => DraftAutosave.init());
})(window, document);

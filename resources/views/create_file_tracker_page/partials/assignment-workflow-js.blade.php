<script>
(function (window, document, $) {
    const AssignmentWorkflow = {
        state: {
            options: null,
            optionsPromise: null,
            context: null,
            busy: false,
        },
        select2Supported: Boolean($ && $.fn && $.fn.select2),
        init() {
            this.cacheDom();
            if (!this.dialog) {
                console.warn('Assignment dialog markup not found.');
                return;
            }
            this.bindEvents();
            if (window.assignmentPermissions?.canAssign) {
                this.prefetchOnIdle();
            }
        },
        cacheDom() {
            this.dialog = document.getElementById('assignment-dialog');
            this.content = this.dialog ? this.dialog.querySelector('.dialog-content') : null;
            this.closeBtn = document.getElementById('assignment-close');
            this.cancelBtn = document.getElementById('assignment-cancel');
            this.skipBtn = document.getElementById('assignment-skip');
            this.submitBtn = document.getElementById('assignment-submit');
            this.officeSelect = document.getElementById('assignment-office');
            this.userSelect = document.getElementById('assignment-user');
            this.noteField = document.getElementById('assignment-note');
            this.emailToggle = document.getElementById('assignment-email');
            this.smsToggle = document.getElementById('assignment-sms');
            this.loadingState = document.getElementById('assignment-loading');
            this.formSection = document.getElementById('assignment-form');
            this.summarySection = document.getElementById('assignment-summary');
            this.statusBanner = document.getElementById('assignment-status');
            this.alertBox = document.getElementById('assignment-alert');
            this.userHint = document.getElementById('assignment-user-hint');
        },
        bindEvents() {
            this.closeBtn?.addEventListener('click', () => this.close());
            this.cancelBtn?.addEventListener('click', () => this.close());
            this.skipBtn?.addEventListener('click', () => this.completeWithoutAssignment());
            this.submitBtn?.addEventListener('click', () => this.submit());
            if (this.dialog) {
                this.dialog.addEventListener('click', (event) => {
                    if (event.target === this.dialog && !this.state.busy) {
                        this.close();
                    }
                });
            }
            this.officeSelect?.addEventListener('change', () => this.onOfficeChanged());
        },
        prefetchOnIdle() {
            if (!window.assignmentPermissions?.canAssign) {
                return;
            }
            const triggerFetch = () => {
                this.fetchOptions().catch(() => {});
            };
            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(triggerFetch, { timeout: 3000 });
            } else {
                window.setTimeout(triggerFetch, 1200);
            }
        },
        fetchOptions() {
            if (this.state.options) {
                return Promise.resolve(this.state.options);
            }
            if (this.state.optionsPromise) {
                return this.state.optionsPromise;
            }
            this.state.optionsPromise = $.ajax({
                url: '/api/file-trackings/assignment/options',
                method: 'GET',
                dataType: 'json'
            }).then((response) => {
                if (response?.success && response.data) {
                    const offices = Array.isArray(response.data.offices) ? response.data.offices : [];
                    const users = Array.isArray(response.data.users) ? response.data.users : [];
                    this.state.options = { offices, users };
                    return this.state.options;
                }
                const message = response?.message || 'Unable to load assignment directory.';
                throw new Error(message);
            }).catch((error) => {
                const message = error?.message || 'Unable to load assignment directory.';
                throw new Error(message);
            });
            return this.state.optionsPromise;
        },
        showDialog() {
            if (!this.dialog) {
                return;
            }
            this.dialog.classList.add('show');
            this.refreshIcons();
        },
        close() {
            if (!this.dialog || this.state.busy) {
                return;
            }
            this.dialog.classList.remove('show');
            this.resetForm();
            this.clearAlert();
            this.updateStatus(null);
            this.state.context = null;
        },
        resetForm() {
            if (this.officeSelect) {
                if (this.select2Supported) {
                    this.destroySelect2(this.officeSelect);
                }
                this.officeSelect.value = '';
            }
            if (this.userSelect) {
                if (this.select2Supported) {
                    this.destroySelect2(this.userSelect);
                }
                this.userSelect.innerHTML = '<option value="">Select officer</option>';
            }
            if (this.noteField) {
                this.noteField.value = '';
            }
            if (this.emailToggle) {
                this.emailToggle.checked = false;
            }
            if (this.smsToggle) {
                this.smsToggle.checked = false;
            }
            if (this.summarySection) {
                this.summarySection.innerHTML = '';
            }
            this.setLoading(true);
        },
        onOfficeChanged() {
            this.populateUserSelect();
        },
        populateOfficeSelect() {
            if (!this.officeSelect || !this.state.options) {
                return;
            }
            const currentValue = this.officeSelect.value;
            const options = ['<option value="">Select office</option>'];
            this.state.options.offices.forEach((office) => {
                const id = office?.id ?? '';
                const label = [office?.name || office?.code || `Office #${id}`, office?.code ? `(${office.code})` : null]
                    .filter(Boolean)
                    .join(' ');
                options.push(`<option value="${id}">${this.escapeHtml(label)}</option>`);
            });
            this.officeSelect.innerHTML = options.join('');
            if (currentValue && this.state.options.offices.some((office) => String(office?.id) === String(currentValue))) {
                this.officeSelect.value = currentValue;
            } else {
                this.officeSelect.value = '';
            }
            this.enableSelect2(this.officeSelect, 'Select office');
        },
        populateUserSelect() {
            if (!this.userSelect || !this.state.options) {
                return;
            }
            const users = this.state.options.users
                .slice()
                .sort((a, b) => (a.name || '').localeCompare(b.name || ''));

            const options = ['<option value="">Select officer</option>'];
            users.forEach((user) => {
                const id = user?.id ?? '';
                const departmentLabel = user?.department_id ? `Dept ${user.department_id}` : null;
                const rolesLabel = Array.isArray(user?.roles) && user.roles.length > 0
                    ? `Roles: ${user.roles.join(', ')}`
                    : null;
                const meta = [departmentLabel, rolesLabel].filter(Boolean).join(' • ');
                const label = [user?.name || `User #${id}`, meta ? `(${meta})` : null]
                    .filter(Boolean)
                    .join(' ');
                options.push(`<option value="${id}">${this.escapeHtml(label)}</option>`);
            });

            this.userSelect.innerHTML = options.join('');
            this.enableSelect2(this.userSelect, 'Select officer');
        },
        populateSummary() {
            if (!this.summarySection || !this.state.context?.tracker) {
                return;
            }
            const tracker = this.state.context.tracker;
            const activeEntry = this.state.context.activeEntry;
            const fileLabel = tracker.fileNo || tracker.trackingId || `File #${tracker.id}`;
            const fileName = tracker.fileName || 'Untitled file';
            const officeName = tracker.currentOffice || tracker.currentOfficeId || '—';
            const officeCode = tracker.currentOfficeId || '—';
            const logInDate = activeEntry?.logInDate || '—';
            const logInTime = activeEntry?.logInTime || '—';

            this.summarySection.innerHTML = `
                <div class="space-y-3">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-base font-semibold text-gray-900">${this.escapeHtml(fileName)}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-600">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 font-mono font-semibold text-blue-700">${this.escapeHtml(fileLabel)}</span>
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 font-mono font-semibold text-red-700">${this.escapeHtml(tracker.trackingId || fileLabel)}</span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-600">
                            <span class="block font-semibold text-gray-700">Current office</span>
                            <span>${this.escapeHtml(officeName)} (${this.escapeHtml(officeCode)})</span>
                        </div>
                    </div>
                    <div class="rounded-md bg-white p-3 text-sm text-gray-600 shadow-sm">
                        <div class="flex items-center gap-2 text-gray-700">
                            <i data-lucide="clock" class="h-4 w-4 text-blue-500"></i>
                            <span>Logged in ${this.escapeHtml(logInDate)} at ${this.escapeHtml(logInTime)}</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Logging out will timestamp this entry and notify the assignee instantly.</p>
                    </div>
                </div>
            `;
            this.refreshIcons();
        },
        updateStatus(message, type = 'info') {
            if (!this.statusBanner) {
                return;
            }
            if (!message) {
                this.statusBanner.classList.add('hidden');
                this.statusBanner.textContent = '';
                return;
            }
            const styles = {
                info: 'border-blue-100 bg-blue-50 text-blue-700',
                success: 'border-green-100 bg-green-50 text-green-700',
                warning: 'border-amber-100 bg-amber-50 text-amber-700',
                danger: 'border-red-100 bg-red-50 text-red-700'
            };
            this.statusBanner.className = `mt-4 rounded-md px-4 py-3 text-sm ${styles[type] || styles.info}`;
            this.statusBanner.textContent = message;
        },
        setLoading(isLoading) {
            if (this.loadingState) {
                this.loadingState.classList.toggle('hidden', !isLoading);
            }
            if (this.formSection) {
                this.formSection.classList.toggle('hidden', Boolean(isLoading));
            }
        },
        setBusy(state) {
            this.state.busy = Boolean(state);
            if (this.submitBtn) {
                this.submitBtn.disabled = this.state.busy;
                if (this.state.busy && !this.submitBtn.dataset.originalLabel) {
                    this.submitBtn.dataset.originalLabel = this.submitBtn.innerHTML;
                    this.submitBtn.innerHTML = '<i data-lucide="loader" class="mr-2 h-4 w-4 animate-spin"></i>Processing...';
                } else if (!this.state.busy && this.submitBtn.dataset.originalLabel) {
                    this.submitBtn.innerHTML = this.submitBtn.dataset.originalLabel;
                    delete this.submitBtn.dataset.originalLabel;
                    this.refreshIcons();
                }
            }
            if (this.skipBtn) {
                this.skipBtn.disabled = this.state.busy;
            }
            if (this.cancelBtn) {
                this.cancelBtn.disabled = this.state.busy;
            }
        },
        clearAlert() {
            if (this.alertBox) {
                this.alertBox.classList.add('hidden');
                this.alertBox.textContent = '';
            }
        },
        showAlert(message) {
            if (!this.alertBox) {
                alert(message);
                return;
            }
            this.alertBox.textContent = message;
            this.alertBox.classList.remove('hidden');
        },
        enableSelect2(element, placeholder) {
            if (!this.select2Supported || !element) {
                return;
            }
            const $element = $(element);
            if ($element.data('select2')) {
                $element.select2('destroy');
            }
            $element.select2({
                dropdownParent: $(this.content),
                width: '100%',
                placeholder: placeholder || '',
                allowClear: true
            });
        },
        destroySelect2(element) {
            if (!this.select2Supported || !element) {
                return;
            }
            const $element = $(element);
            if ($element.data('select2')) {
                $element.select2('destroy');
            }
        },
        escapeHtml(value) {
            return (value ?? '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
        refreshIcons() {
            if (window.lucide?.createIcons) {
                window.lucide.createIcons();
            }
        },
        getOfficeById(id) {
            if (!id || !this.state.options) {
                return null;
            }
            return this.state.options.offices.find((office) => String(office?.id) === String(id)) || null;
        },
        getUserById(id) {
            if (!id || !this.state.options) {
                return null;
            }
            return this.state.options.users.find((user) => String(user?.id) === String(id)) || null;
        },
        buildNotificationBody(tracker, note, office, user) {
            const parts = [];
            const fileLabel = tracker.fileNo || tracker.trackingId || `File #${tracker.id}`;
            const readableName = tracker.fileName ? `${tracker.fileName}` : 'this file';
            parts.push(`You have been assigned ${fileLabel} (${readableName}).`);
            if (office?.name) {
                parts.push(`Destination office: ${office.name}${office.code ? ` (${office.code})` : ''}.`);
            }
            if (note) {
                parts.push(`Instructions: ${note}`);
            }
            if (user?.name) {
                parts.push(`Assigned by ${window.currentUser?.name || 'System'}.`);
            }
            return parts.join(' ');
        },
        buildSuccessMessage(tracker, office, user) {
            const targetOffice = office?.name ? office.name : (office?.code ? `Office ${office.code}` : 'the selected office');
            const targetUser = user?.name || 'the selected officer';
            const fileLabel = tracker.fileNo || tracker.trackingId || `File #${tracker.id}`;
            return `${fileLabel} logged out and assigned to ${targetUser} in ${targetOffice}.`;
        },
        openPanel({ source } = {}) {
            if (!this.dialog) {
                return false;
            }
            this.state.context = { mode: 'panel', source };
            this.resetForm();
            this.clearAlert();
            this.setLoading(false);
            if (this.formSection) {
                this.formSection.classList.add('hidden');
            }
            if (this.summarySection) {
                this.summarySection.innerHTML = `
                    <div class="space-y-3 text-sm text-gray-700">
                        <p>Select a file from the tracker log to begin a new assignment.</p>
                        <p class="text-xs text-gray-500">Tip: open a tracker, use the actions menu, and choose <span class="font-semibold">Log Out</span> to forward it with assignment details.</p>
                    </div>
                `;
            }
            this.updateStatus('Ready when you are. Choose a tracker to open this window automatically.', 'info');
            this.showDialog();
            return true;
        },
        openForLogOut({ tracker, logId, activeEntry }) {
            if (!window.assignmentPermissions?.canAssign) {
                return false;
            }
            if (!tracker || !logId) {
                alert('Unable to locate this tracker. Please refresh and try again.');
                return true;
            }
            if (!tracker.id) {
                alert('Please save this tracker before logging out movements.');
                return true;
            }
            const effectiveEntry = activeEntry || (tracker.logEntries || []).find((entry) => (entry.status || '').toLowerCase() === 'active');
            if (!effectiveEntry || effectiveEntry.logId !== logId) {
                alert('This log entry is no longer active.');
                return true;
            }
            this.state.context = { mode: 'logout', tracker, logId, activeEntry: effectiveEntry };
            this.resetForm();
            this.clearAlert();
            this.populateSummary();
            this.updateStatus('Confirm the destination office and officer to complete this handover.', 'info');
            this.showDialog();
            this.fetchOptions()
                .then(() => {
                    this.setLoading(false);
                    this.populateOfficeSelect();
                    this.populateUserSelect();
                    this.populateSummary();
                })
                .catch((error) => {
                    this.setLoading(false);
                    this.showAlert(error?.message || 'Unable to load assignment directory.');
                });
            return true;
        },
        async submit() {
            if (this.state.busy) {
                return;
            }
            this.clearAlert();
            const tracker = this.state.context?.tracker;
            if (!tracker) {
                this.showAlert('Unable to determine the file tracker context.');
                return;
            }
            if (typeof window.requestCompleteMovement !== 'function' || typeof window.requestAssignment !== 'function') {
                this.showAlert('Assignment services are not available. Please try again later.');
                return;
            }
            const officeId = this.officeSelect?.value || '';
            const userId = this.userSelect?.value || '';
            const note = this.noteField?.value?.trim() || '';
            const sendEmail = Boolean(this.emailToggle?.checked);
            const sendSms = Boolean(this.smsToggle?.checked);

            if (!officeId || !userId) {
                this.showAlert('Select both the destination office and the officer to continue.');
                return;
            }

            const office = this.getOfficeById(officeId);
            const user = this.getUserById(userId);
            const officeIdInt = Number(office?.id ?? officeId);
            const userIdInt = Number(user?.id ?? userId);

            if (!Number.isFinite(officeIdInt) || !Number.isFinite(userIdInt)) {
                this.showAlert('Invalid assignment selection.');
                return;
            }

            const now = new Date();
            const logOutTime = now.toTimeString().split(' ')[0].substring(0, 5);
            const fileLabel = tracker.fileNo || tracker.trackingId || `File #${tracker.id}`;
            const notificationTitle = `File assignment: ${fileLabel}`;
            const notificationBody = this.buildNotificationBody(tracker, note, office, user);

            try {
                this.setBusy(true);
                const movementResponse = await window.requestCompleteMovement(tracker, {
                    log_out_time: logOutTime
                });

                if (!movementResponse?.success || !movementResponse.data) {
                    const message = movementResponse?.message || 'Unable to log out this file entry.';
                    throw new Error(message);
                }

                const normalizedMovement = transformApiTracker(movementResponse.data);
                if (normalizedMovement) {
                    upsertLocalTracker(normalizedMovement, { render: false });
                    this.state.context.tracker = normalizedMovement;
                }

                const assignmentResponse = await window.requestAssignment(this.state.context.tracker, {
                    assigned_office_id: officeIdInt,
                    assigned_to: userIdInt,
                    reason: note || null,
                    notification_title: notificationTitle,
                    notification_body: notificationBody,
                    send_email: sendEmail,
                    send_sms: sendSms
                });

                if (!assignmentResponse?.success || !assignmentResponse.data) {
                    const message = assignmentResponse?.message || 'Unable to assign this file.';
                    throw new Error(message);
                }

                const normalizedAssignment = transformApiTracker(assignmentResponse.data);
                if (normalizedAssignment) {
                    upsertLocalTracker(normalizedAssignment);
                } else {
                    updateFileTrackersTable();
                }

                const successMessage = this.buildSuccessMessage(this.state.context.tracker, office, user);
                if (typeof showNotification === 'function') {
                    showNotification(successMessage, 'success');
                }
                this.close();
            } catch (error) {
                const message = error?.responseJSON?.message || error?.message || 'Unable to complete the assignment.';
                this.showAlert(message);
            } finally {
                this.setBusy(false);
            }
        },
        async completeWithoutAssignment() {
            if (this.state.busy) {
                return;
            }
            const tracker = this.state.context?.tracker;
            if (!tracker) {
                this.close();
                return;
            }
            if (typeof window.requestCompleteMovement !== 'function') {
                this.showAlert('Logout service unavailable.');
                return;
            }
            try {
                this.setBusy(true);
                const now = new Date();
                const logOutTime = now.toTimeString().split(' ')[0].substring(0, 5);
                const response = await window.requestCompleteMovement(tracker, {
                    log_out_time: logOutTime
                });

                if (!response?.success || !response.data) {
                    const message = response?.message || 'Unable to log out this file entry.';
                    throw new Error(message);
                }

                const normalized = transformApiTracker(response.data);
                if (normalized) {
                    upsertLocalTracker(normalized);
                } else {
                    updateFileTrackersTable();
                }

                if (typeof showNotification === 'function') {
                    showNotification('File logged out successfully (no assignment sent).', 'info');
                }
                this.close();
            } catch (error) {
                const message = error?.responseJSON?.message || error?.message || 'Unable to complete the logout.';
                this.showAlert(message);
            } finally {
                this.setBusy(false);
            }
        }
    };

    window.AssignmentWorkflow = AssignmentWorkflow;
    AssignmentWorkflow.init();
})(window, document, window.jQuery);
</script>

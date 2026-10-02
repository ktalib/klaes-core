<script>
    // Quick Actions functionality
    const QuickActions = {
        searchRequest: null,

        // Initialize quick actions
        init: function () {
            this.bindEvents();
            console.log('Quick Actions initialized');
        },

        // Bind event listeners
        bindEvents: function () {
            // Search Files
            $('#quick-search-files').on('click', () => this.searchFiles());

            // Office List
            $('#quick-office-list').on('click', () => this.showOfficeList());

            // Track Status
            $('#quick-track-status').on('click', () => this.trackStatus());

            // Statistics
            $('#quick-statistics').on('click', () => this.showStatistics());

            // Assignment Center
            $('#quick-assignment-center').on('click', () => this.showAssignmentCenter());

            // Update Status
            $('#quick-update-status').on('click', () => this.updateStatus());

            // Scan QR — New KANGIS department log
            $('#quick-scan-qr').on('click', () => this.scanQr());

            // Update Movement functionality moved to history table action menu
        },

        // Search Files functionality
        searchFiles: function () {
            this.showModal('search-files-modal', 'Search File Trackers', this.renderSearchFilesContent());
        },

        // Show office list
        showOfficeList: function () {
            this.showModal('office-list-modal', 'Office Directory', this.renderOfficeListContent());
        },

        // Track status functionality
        trackStatus: function () {
            this.showModal('track-status-modal', 'Track File Status', this.renderTrackStatusContent());
        },

        // Show statistics
        showStatistics: function () {
            this.loadStatistics().then(stats => {
                this.showModal('statistics-modal', 'File Tracking Statistics', this.renderStatisticsContent(stats));
            });
        },

        // Assignment center entrypoint
        showAssignmentCenter: function () {
            if (this.launchAssignmentPanel('quick-action')) {
                return;
            }

            this.showModal(
                'assignment-center-modal',
                'Assignment Center',
                this.renderAssignmentContent()
            );
        },

        // Update file status (registry log back)
        updateStatus: function () {
            this.showModal(
                'update-status-modal',
                'Log File Back to Registry',
                this.renderUpdateStatusContent()
            );
        },

        // Scan QR code to log New KANGIS file into next department
        scanQr: function (options = {}) {
            this.showModal(
                'scan-qr-modal',
                'Scan QR · Log to Next Department',
                this.renderScanQrContent()
            );

            const prefillIdentifier = (options.prefillIdentifier || '').toString().trim();
            if (!prefillIdentifier) {
                return;
            }

            const autoLookup = options.autoLookup !== false;
            setTimeout(() => {
                const input = $('#scan-qr-input');
                if (!input.length) {
                    return;
                }

                input.val(prefillIdentifier);
                input.trigger('input');

                if (autoLookup) {
                    input.trigger($.Event('keydown', { key: 'Enter' }));
                }

                input.trigger('focus');

                if (typeof showToast === 'function') {
                    showToast('Tracking ID / File Number prefilled. Confirm and click "Log to Next Dept".', 'info');
                }
            }, 120);
        },

        // Update file movement details
        updateMovement: function (trackingId = '') {
            this.showModal(
                'update-movement-modal',
                'Update File Movement',
                this.renderUpdateMovementContent(trackingId)
            );
        },

        // Generic modal display function
        showModal: function (modalId, title, content) {
            // Remove existing modal if present
            $(`#${modalId}`).remove();

            const modal = $(`
                <div id="${modalId}" class="fixed inset-0 bg-black bg-opacity-60 z-[110] flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-bold flex items-center gap-2">
                                    <i data-lucide="layers" class="w-5 h-5"></i>
                                    ${title}
                                </h3>
                                <button class="modal-close p-2 hover:bg-white/20 rounded transition-colors">
                                    <i data-lucide="x" class="w-6 h-6"></i>
                                </button>
                            </div>
                        </div>
                        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                            ${content}
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Bind close events
            modal.find('.modal-close').on('click', () => this.closeModal(modalId));
            modal.on('click', (e) => {
                if (e.target === modal[0]) this.closeModal(modalId);
            });

            // Initialize Lucide icons in modal
            lucide.createIcons();

            // Bind specific modal events after content is loaded
            this.bindModalEvents(modalId);
        },

        // Bind modal-specific events
        bindModalEvents: function (modalId) {
            const self = this;

            if (modalId === 'search-files-modal') {
                $('#perform-search').on('click', function () {
                    self.performFileSearch();
                });
                $('#clear-search').on('click', function () {
                    $('#search-query').val('');
                    self.displaySearchPlaceholder();
                    self.focusSearchInput(true);
                });
                $('#search-query').on('keypress', function (e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        self.performFileSearch();
                    }
                });
                setTimeout(() => {
                    self.focusSearchInput(true);
                }, 40);
            }

            if (modalId === 'track-status-modal') {
                $('#track-search').on('click', function () {
                    self.performTrackSearch();
                });
                $('#track-input').on('keypress', function (e) {
                    if (e.which === 13) {
                        self.performTrackSearch();
                    }
                });
            }

            if (modalId === 'assignment-center-modal') {
                $('#assignment-open-panel').on('click', function () {
                    if (self.launchAssignmentPanel('assignment-modal')) {
                        self.closeModal(modalId);
                        return;
                    }

                    if (typeof showNotification === 'function') {
                        showNotification('Assignment panel is not available yet. Follow the checklist to continue.', 'info');
                    }
                });
            }

            if (modalId === 'update-status-modal') {
                let trackingLookupTimer = null;

                // No longer loading offices for dropdown as it is now a display-only field populated from file info

                function getActiveLookupValue() {
                    return $('#modal-update-tracking-id').val().trim();
                }

                function checkStatusUpdateModalForm() {
                    const lookupValue = getActiveLookupValue();
                    const status = $('#modal-status-update').val();
                    const office = $('#modal-registry-office').val();
                    const numPages = ($('#modal-num-pages').val() || '').trim();
                    const numPagesInt = parseInt(numPages, 10);
                    const numPagesValid = !isNaN(numPagesInt) && numPagesInt >= 1 && numPagesInt <= 99999;
                    const btn = $('#modal-update-status-btn');
                    const hasFileInfo = !$('#modal-file-preview').hasClass('hidden');
                    const delayReasonRequired = !$('#modal-delay-reason-section').hasClass('hidden');
                    const delayReasonValid = !delayReasonRequired || ($('#modal-delay-reason').val() || '').trim().length > 0;

                    if (lookupValue && status && office && hasFileInfo && numPagesValid && delayReasonValid) {
                        btn.prop('disabled', false);
                    } else {
                        btn.prop('disabled', true);
                    }
                }

                // Live page-count reconciliation against the original log-out count.
                function updatePageDiscrepancyNotice() {
                    const $input = $('#modal-num-pages');
                    const orig = parseInt($input.attr('data-original-pages'), 10);
                    const val = parseInt(($input.val() || '').trim(), 10);
                    const $disc = $('#modal-page-discrepancy');
                    const $txt = $('#modal-page-discrepancy-text');

                    if (!isNaN(orig) && !isNaN(val) && val !== orig) {
                        const diff = val - orig;
                        if (diff < 0) {
                            $txt.text(`Warning: ${Math.abs(diff)} page(s) appear to be MISSING (logged out with ${orig}, returning ${val}).`);
                        } else {
                            $txt.text(`Note: ${diff} extra page(s) compared to log-out (logged out with ${orig}, returning ${val}).`);
                        }
                        $disc.removeClass('hidden');
                    } else {
                        $disc.addClass('hidden');
                    }
                }

                function fetchFileInfo(identifier) {
                    const normalizedValue = (identifier || '').trim();
                    if (!normalizedValue) {
                        $('#modal-file-preview').addClass('hidden');
                                $("#modal-status-update, #modal-status-notes, #modal-num-pages, #modal-registry-office").prop("disabled", true);
                                $("#modal-page-count-section").addClass("hidden");
                                $('#modal-original-pages-notice, #modal-page-discrepancy').addClass('hidden');
                                $('#modal-num-pages').val('').removeAttr('data-original-pages');
                                $('#modal-delay-reason-section, #modal-delay-reason-notice, #modal-delay-reason-error').addClass('hidden');
                                $('#modal-delay-reason').prop('disabled', true).removeAttr('required').val('');
                        checkStatusUpdateModalForm();
                        return;
                    }

                    $('#preview-file-name').text('Loading file information...');
                    $('#modal-file-preview').removeClass('hidden');

                    $.ajax({
                        url: `/filetracker/get-file-info/${encodeURIComponent(normalizedValue)}?by=tracking_id`,
                        method: 'GET',
                        success: function (response) {
                            if (response.success) {
                                const data = response.data;
                                $('#preview-file-name').text(data.file_name);
                                $('#preview-file-number').text(data.file_number);
                                $('#preview-location').text(data.current_location || 'N/A');
                                $('#preview-handler').text(data.current_handler || 'N/A');

                                const statusSpan = $('#preview-status');
                                statusSpan.text(data.current_status || 'N/A');
                                statusSpan.removeClass('bg-gray-100 bg-green-100 bg-yellow-100 bg-red-100 text-gray-800 text-green-800 text-yellow-800 text-red-800');

                                switch (data.current_status) {
                                    case 'active':
                                    case 'ACTIVE':
                                    case 'Log-in':
                                        statusSpan.addClass('bg-green-100 text-green-800');
                                        break;
                                    case 'pending':
                                        statusSpan.addClass('bg-yellow-100 text-yellow-800');
                                        break;
                                    case 'overdue':
                                        statusSpan.addClass('bg-red-100 text-red-800');
                                        break;
                                    case 'COMPLETED':
                                        statusSpan.addClass('bg-blue-100 text-blue-800');
                                        break;
                                    case 'CANCELLED':
                                    case 'Cancelled':
                                        statusSpan.addClass('bg-gray-100 text-gray-800');
                                        break;
                                    default:
                                        statusSpan.addClass('bg-gray-100 text-gray-800');
                                }

                                // Enable status, notes and the Registry (Origin) dropdown so the
                                // user can pick the registry (auto-selected when we can resolve it).
                                $("#modal-status-update, #modal-status-notes, #modal-num-pages, #modal-registry-office").prop("disabled", false);
                                $("#modal-page-count-section").removeClass("hidden");
                                $('#modal-update-status-btn').data('database-id', data.id);
                                $('#modal-update-status-btn').data('table', data.table || 'file_tracker');
                                // A commissioned file still on DIIT has no tracker row,
                                // so the log-back is addressed by file number instead of
                                // by id (table === 'diit'). Harmless for real trackers.
                                $('#modal-update-status-btn').data('file-number', data.file_number || '');

                                // Reason for Delay: required only when the file is Amber (due soon)
                                // or Red (overdue) at the moment it's being logged back to Registry.
                                const timelineStatus = data.timeline_status || null;
                                const $delaySection = $('#modal-delay-reason-section');
                                const $delayField = $('#modal-delay-reason');
                                const $delayNotice = $('#modal-delay-reason-notice');
                                const $delayNoticeText = $('#modal-delay-reason-notice-text');
                                if (timelineStatus === 'amber' || timelineStatus === 'red') {
                                    $delaySection.removeClass('hidden');
                                    $delayField.prop('disabled', false).attr('required', true);
                                    $delayNoticeText.text(timelineStatus === 'red'
                                        ? 'This file is past its expected timeline. Please state the reason for the delay.'
                                        : 'This file is close to its expected timeline. Please state the reason for the delay, if any.');
                                    $delayNotice.removeClass('hidden');
                                } else {
                                    $delaySection.addClass('hidden');
                                    $delayField.prop('disabled', true).removeAttr('required').val('');
                                    $('#modal-delay-reason-error').addClass('hidden');
                                    $delayNotice.addClass('hidden');
                                }

                                // Original page count recorded when the file was logged out.
                                const $numInput = $('#modal-num-pages');
                                const originalPages = data.num_pages;
                                if (originalPages !== null && originalPages !== undefined && String(originalPages).trim() !== '') {
                                    $('#modal-original-pages-value').text(originalPages);
                                    $('#modal-original-pages-notice').removeClass('hidden');
                                    $numInput.attr('data-original-pages', originalPages);
                                } else {
                                    $('#modal-original-pages-notice').addClass('hidden');
                                    $numInput.removeAttr('data-original-pages');
                                }
                                // Leave the returned-pages field empty so the user consciously
                                // counts and enters the pages being returned.
                                $numInput.val('');
                                updatePageDiscrepancyNotice();

                                // Auto-select the origin registry when known; otherwise leave it on
                                // "Select Registry (Origin)" for the user to choose.
                                const originCode = data.origin_office_code || data.origin_registry_code || data.originOfficeCode;
                                const originName = data.origin_office_name || data.origin_registry || data.originOfficeName;
                                const $registrySelect = $('#modal-registry-office');

                                if (originCode && $registrySelect.find(`option[value="${originCode}"]`).length) {
                                    $registrySelect.val(originCode);
                                } else if (originName && $registrySelect.find(`option[data-name="${originName}"]`).length) {
                                    $registrySelect.val($registrySelect.find(`option[data-name="${originName}"]`).val());
                                } else {
                                    $registrySelect.val('');
                                }
                            } else {
                                $('#preview-file-name').text('File not found');
                                $('#preview-file-number, #preview-location, #preview-handler').text('N/A');
                                $("#modal-status-update, #modal-status-notes, #modal-num-pages, #modal-registry-office").prop("disabled", true);
                                $("#modal-page-count-section").addClass("hidden");
                                $('#modal-delay-reason-section, #modal-delay-reason-notice').addClass('hidden');
                                $('#modal-delay-reason').prop('disabled', true).removeAttr('required').val('');
                                $('#modal-registry-office').val('');
                                // Drop the previous file's target so a stale id / file
                                // number can never be submitted against this lookup.
                                $('#modal-update-status-btn').removeData('database-id').removeData('table').removeData('file-number');
                            }
                            checkStatusUpdateModalForm();
                        },
                        error: function (xhr) {
                            // A 404 is "no such file", not a failure — say so rather than
                            // reporting a fault the officer cannot act on.
                            $('#preview-file-name').text(
                                xhr && xhr.status === 404
                                    ? ((xhr.responseJSON && xhr.responseJSON.message) || 'File not found')
                                    : 'Error loading file information'
                            );
                            $('#modal-update-status-btn').removeData('database-id').removeData('table').removeData('file-number');
                            $('#preview-file-number, #preview-location, #preview-handler').text('N/A');
                                $("#modal-status-update, #modal-status-notes, #modal-num-pages, #modal-registry-office").prop("disabled", true);
                                $("#modal-page-count-section").addClass("hidden");
                                $('#modal-delay-reason-section, #modal-delay-reason-notice').addClass('hidden');
                                $('#modal-delay-reason').prop('disabled', true).removeAttr('required').val('');
                            $('#modal-registry-office').val('');
                            checkStatusUpdateModalForm();
                        }
                    });
                }

                $('#modal-update-tracking-id').on('input', function () {
                    const trackingId = $(this).val().trim();
                    if (trackingId.length > 3) {
                        clearTimeout(trackingLookupTimer);
                        trackingLookupTimer = setTimeout(() => fetchFileInfo(trackingId), 500);
                    } else {
                                $("#modal-status-update, #modal-status-notes, #modal-num-pages, #modal-registry-office").prop("disabled", true);
                                $("#modal-page-count-section").addClass("hidden");
                        $('#modal-registry-office').val('');
                    }
                    checkStatusUpdateModalForm();
                });

                $('#modal-status-update, #modal-registry-office').on('change', checkStatusUpdateModalForm);

                $('#modal-num-pages').on('input', function () {
                    updatePageDiscrepancyNotice();
                    checkStatusUpdateModalForm();
                });

                $('#modal-delay-reason').on('input', function () {
                    if ($(this).val().trim()) {
                        $('#modal-delay-reason-error').addClass('hidden');
                    }
                    checkStatusUpdateModalForm();
                });

                $('#modal-scan-tracking').on('click', function () {
                    // Wire the scan button to populate the tracking-id field from a camera / text input
                    // For desktop: focus the tracking ID input for keyboard/barcode-gun entry
                    const $input = $('#modal-update-tracking-id');
                    $input.val('').trigger('focus');
                    showToast('Scan or type the tracking ID / file number', 'info');
                });

                $('#modal-update-status-btn').on('click', function () {
                    const btn = $(this);
                    const lookupValue = getActiveLookupValue();
                    const status = $('#modal-status-update').val();
                    const notes = $('#modal-status-notes').val().trim();
                    const registryOfficeCode = $('#modal-registry-office').val();
                    const registryOfficeName = $('#modal-registry-office option:selected').data('name') || $('#modal-registry-office option:selected').text();
                    const databaseId = btn.data('database-id');
                    const table = btn.data('table') || 'file_tracker';
                    const fileNumber = btn.data('file-number') || '';

                    const numPagesRaw = ($('#modal-num-pages').val() || '').trim();
                    const numPagesInt = parseInt(numPagesRaw, 10);
                    if (isNaN(numPagesInt) || numPagesInt < 1 || numPagesInt > 99999) {
                        $('#modal-num-pages-error').removeClass('hidden');
                        if (typeof showToast === 'function') {
                            showToast('Please enter a valid number of pages being returned (1–99,999).', 'error');
                        }
                        return;
                    }
                    $('#modal-num-pages-error').addClass('hidden');

                    if (!lookupValue || !status || !registryOfficeCode || !databaseId) {
                        if (typeof showToast === 'function') {
                            showToast('Please fill in all required fields and ensure file is found', 'error');
                        }
                        return;
                    }

                    const delayReasonRequired = !$('#modal-delay-reason-section').hasClass('hidden');
                    const delayReason = ($('#modal-delay-reason').val() || '').trim();
                    if (delayReasonRequired && !delayReason) {
                        $('#modal-delay-reason-error').removeClass('hidden');
                        if (typeof showToast === 'function') {
                            showToast('Please state the reason for the delay before logging this file back.', 'error');
                        }
                        return;
                    }
                    $('#modal-delay-reason-error').addClass('hidden');

                    btn.prop('disabled', true).html('<i data-lucide="loader-2" class="h-4 w-4 mr-2 animate-spin"></i>Logging Back...');
                    lucide.createIcons();

                    $.ajax({
                        url: `/filetracker/update-status/${databaseId}`,
                        method: 'POST',
                        data: {
                            status: status,
                            registry_office_code: registryOfficeCode,
                            registry_office_name: registryOfficeName,
                            notes: notes,
                            num_pages: numPagesInt,
                            delay_reason: delayReasonRequired ? delayReason : null,
                            table: table,
                            file_number: fileNumber,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function (response) {
                            if (response.success) {
                                if (typeof showToast === 'function') {
                                    showToast(response.message, 'success');
                                }

                                // Surface any page discrepancy recorded by the server.
                                if (response.data && response.data.page_discrepancy_note) {
                                    if (typeof showToast === 'function') {
                                        showToast(response.data.page_discrepancy_note, 'warning');
                                    }
                                }

                                const info = `File "${response.data.file_info.file_name}" logged back to Registry. Status changed from "${response.data.old_status}" to "${response.data.new_status}" at ${response.data.updated_at}`;
                                $('#modal-file-info').text(info);
                                $('#modal-status-info').removeClass('hidden');

                                $('#modal-update-tracking-id').val('');
                                $('#modal-status-update').val('');
                                $('#modal-status-notes').val('');
                                $('#modal-registry-office').val('');
                                $('#modal-num-pages').val('').removeAttr('data-original-pages');
                                $('#modal-original-pages-notice, #modal-page-discrepancy').addClass('hidden');
                                $('#modal-page-count-section').addClass('hidden');
                                $('#modal-delay-reason').val('').prop('disabled', true).removeAttr('required');
                                $('#modal-delay-reason-section, #modal-delay-reason-notice, #modal-delay-reason-error').addClass('hidden');
                                $('#modal-file-preview').addClass('hidden');
                                $('#modal-status-update, #modal-status-notes, #modal-num-pages, #modal-registry-office').prop('disabled', true);
                                checkStatusUpdateModalForm();

                                setTimeout(() => self.closeModal('update-status-modal'), 3000);
                            } else if (typeof showToast === 'function') {
                                showToast(response.message || 'Failed to update status', 'error');
                            }
                        },
                        error: function (xhr) {
                            let errorMessage = 'Error updating status';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            if (typeof showToast === 'function') {
                                showToast(errorMessage, 'error');
                            }
                        },
                        complete: function () {
                            btn.html('<i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>Log Back to Registry');
                            lucide.createIcons();
                            checkStatusUpdateModalForm();
                        }
                    });
                });
            }
            if (modalId === 'scan-qr-modal') {
                const self = this;
                let scanLookupTimer = null;
                let resolvedTrackerId = null;  // DB id for confirmed submit

                // New KANGIS 8-transfer workflow: FROM -> TO per step
                const knStepTransitions = {
                    1: { from: 'Customer Service',      to: 'One Stop Shop (OSS)' },
                    2: { from: 'One Stop Shop (OSS)',   to: 'Verification' },
                    3: { from: 'Verification',          to: 'Geometry (GIS)' },
                    4: { from: 'Geometry (GIS)',        to: 'Vetting Committee' },
                    5: { from: 'Vetting Committee',     to: 'Pagination (Deeds)' },
                    6: { from: 'Pagination (Deeds)',    to: 'Production (GIS)' },
                    7: { from: 'Production (GIS)',      to: 'Collection (DG)' },
                    8: { from: 'Collection (DG)',       to: 'KANGIS Registry' },
                };
                const knMaxStep = 8;

                function showScanError(msg) {
                    $('#scan-qr-error').text(msg).removeClass('hidden');
                    $('#scan-qr-file-preview').addClass('hidden');
                    $('#scan-qr-submit-btn').prop('disabled', true);
                    resolvedTrackerId = null;
                }

                function hideScanError() {
                    $('#scan-qr-error').addClass('hidden');
                }

                function renderStepPreview(stepNumber) {
                    const current = Math.max(1, Math.min(knMaxStep, parseInt(stepNumber, 10) || 1));
                    const $list = $('#scan-qr-step-list');
                    if (!$list.length) return;
                    let html = '';
                    for (let n = 1; n <= knMaxStep; n++) {
                        const t = knStepTransitions[n];
                        const isDone    = n < current;
                        const isCurrent = n === current;
                        const badgeCls = isDone
                            ? 'bg-green-600'
                            : (isCurrent ? 'bg-amber-600 ring-2 ring-amber-200' : 'bg-gray-300');
                        const lineCls  = isCurrent ? 'text-amber-800 font-semibold' : (isDone ? 'text-green-700' : 'text-gray-500');
                        const stateLbl = isDone
                            ? '<span class="ml-2 text-[10px] font-bold uppercase text-green-600">Completed</span>'
                            : (isCurrent ? '<span class="ml-2 text-[10px] font-bold uppercase text-amber-700">Current</span>' : '');
                        const icon = isDone
                            ? '<i data-lucide="check" class="h-3 w-3 text-white"></i>'
                            : '<span class="text-[10px] font-bold text-white">' + n + '</span>';
                        html += '' +
                            '<li class="ml-4">' +
                              '<span class="absolute -left-[14px] flex items-center justify-center w-6 h-6 rounded-full ' + badgeCls + ' ring-2 ring-white">' + icon + '</span>' +
                              '<div class="text-xs ' + lineCls + '">' + t.from + ' \u2192 ' + t.to + stateLbl + '</div>' +
                            '</li>';
                    }
                    $list.html(html);
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }

                function lookupAndPreview(identifier) {
                    if (!identifier) return;
                    hideScanError();
                    $('#scan-qr-file-title').text('Looking up...');
                    $('#scan-qr-file-preview').removeClass('hidden');
                    $('#scan-qr-submit-btn').prop('disabled', true);
                    resolvedTrackerId = null;

                    $.ajax({
                        url: `/create-file-tracker/search`,
                        method: 'GET',
                        data: { query: identifier },
                        success: function (response) {
                            // Response shape: { success, data: { status, tracker:{} | record:{} } }
                            if (!response?.success) {
                                showScanError('No tracker found for: ' + identifier);
                                return;
                            }

                            const status  = response?.data?.status;
                            let tracker   = response?.data?.tracker;
                            let currentStep;

                            if (status === 'archive' && response?.data?.record) {
                                // File indexed but no tracker yet — first scan will auto-create at step 1
                                const rec = response.data.record;
                                currentStep = 1;
                                resolvedTrackerId = null; // scanAndLog will create it
                                $('#scan-qr-file-title').text(rec.file_name || rec.file_number || identifier);
                                $('#scan-qr-file-number').text(rec.file_number || rec.tracking_id || '-');
                                renderStepPreview(currentStep);
                                $('#scan-qr-file-preview').removeClass('hidden');
                                hideScanError();
                                $('#scan-qr-submit-btn').prop('disabled', false);
                                return;
                            }

                            if (!tracker) {
                                showScanError('No tracker found for: ' + identifier);
                                return;
                            }

                            currentStep = parseInt(tracker.workflow_step || tracker.workflowStep || 1);
                            resolvedTrackerId = tracker.id || tracker.trackerId;
                            $('#scan-qr-file-title').text(tracker.file_title || tracker.fileTitle || tracker.file_name || identifier);
                            $('#scan-qr-file-number').text(tracker.file_number || tracker.fileNumber || '-');
                            renderStepPreview(currentStep);
                            $('#scan-qr-file-preview').removeClass('hidden');
                            hideScanError();

                            if (currentStep >= knMaxStep) {
                                showScanError('This file has completed all ' + knMaxStep + ' workflow stages.');
                            } else {
                                $('#scan-qr-submit-btn').prop('disabled', false);
                            }
                        },
                        error: function () {
                            showScanError('Lookup failed. Check the tracking ID and try again.');
                        }
                    });
                }

                // Auto-focus input and trigger lookup on change (supports barcode gun which sends Enter)
                setTimeout(() => { $('#scan-qr-input').trigger('focus'); }, 80);

                $('#scan-qr-input').on('input', function () {
                    const val = $(this).val().trim();
                    clearTimeout(scanLookupTimer);
                    if (val.length > 3) {
                        scanLookupTimer = setTimeout(() => lookupAndPreview(val), 400);
                    } else {
                        $('#scan-qr-file-preview').addClass('hidden');
                        hideScanError();
                        $('#scan-qr-submit-btn').prop('disabled', true);
                        resolvedTrackerId = null;
                    }
                });

                $('#scan-qr-input').on('keydown', function (e) {
                    if (e.key === 'Enter') {
                        clearTimeout(scanLookupTimer);
                        lookupAndPreview($(this).val().trim());
                    }
                });

                $('#scan-qr-cancel-btn').on('click', () => self.closeModal('scan-qr-modal'));

                $('#scan-qr-submit-btn').on('click', function () {
                    const identifier = $('#scan-qr-input').val().trim();
                    const notes      = $('#scan-qr-notes').val().trim();
                    if (!identifier) return;

                    const btn = $(this);
                    const originalHtml = btn.html();
                    btn.prop('disabled', true).html('<i data-lucide="loader-2" class="h-4 w-4 mr-2 animate-spin inline"></i>Logging...');
                    lucide.createIcons();

                    $.ajax({
                        url: '/api/mobile/tracker/scan-and-log',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            qr_code:  identifier,
                            notes:    notes,
                            skip_sms: true,
                            _token:   csrfToken,
                        }),
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        success: function (response) {
                            if (response.success) {
                                const nextOffice = response.next_office || response.tracker?.current_office || '';
                                const step       = response.workflow_step || '';
                                const trackerId  = response.tracker?.id || null;
                                const phone      = response.phone || '';
                                const defaultMsg = response.default_sms_message || '';

                                let resultHtml = '<strong>Logged successfully!</strong>';
                                if (nextOffice) resultHtml += '<br>File is now at: <strong>' + nextOffice + '</strong>';
                                if (step) resultHtml += ' (Step ' + step + ')';
                                $('#scan-qr-result').html(resultHtml).removeClass('hidden');

                                // Reset for next scan
                                $('#scan-qr-input').val('').trigger('focus');
                                $('#scan-qr-file-preview').addClass('hidden');
                                btn.html(originalHtml).prop('disabled', true);
                                lucide.createIcons();
                                resolvedTrackerId = null;

                                if (typeof loadTrackers === 'function') loadTrackers();

                                // Swal: review & send SMS to the applicant
                                if (typeof Swal !== 'undefined' && trackerId) {
                                    const safePhone = $('<div>').text(phone || '').html();
                                    const smsBody = defaultMsg || ('Dear Applicant, your file ' + (response.tracker?.file_number || '') + ' has been logged at ' + nextOffice + '. - KANGIS Registry.');
                                    const phoneBlock = phone
                                        ? '<div class="text-left text-sm text-gray-600 mb-2">Phone: <strong>' + safePhone + '</strong></div>'
                                        : '<div class="text-left text-sm text-amber-700 mb-2">No phone on file — enter one below to send an SMS.</div>' +
                                          '<input id="scan-qr-sms-phone" type="text" placeholder="08xxxxxxxxx" class="swal2-input" style="width:100%;">';
                                    Swal.fire({
                                        title: 'Notify applicant by SMS',
                                        html:
                                            phoneBlock +
                                            '<input type="hidden" id="scan-qr-sms-body" value="' + $('<div>').text(smsBody).html() + '">' +
                                            '<p class="text-xs text-gray-500 mt-2 text-left">Send the standard notification to the applicant, or skip.</p>',
                                        showCancelButton: true,
                                        confirmButtonText: '<i class="fa fa-paper-plane"></i> Send SMS',
                                        cancelButtonText: 'Skip',
                                        confirmButtonColor: '#0ea5e9',
                                        focusConfirm: false,
                                        preConfirm: () => {
                                            const body = (document.getElementById('scan-qr-sms-body')?.value || '').trim();
                                            const entered = document.getElementById('scan-qr-sms-phone');
                                            const finalPhone = phone || (entered?.value || '').trim();
                                            if (!body) {
                                                Swal.showValidationMessage('Message cannot be empty.');
                                                return false;
                                            }
                                            if (!finalPhone) {
                                                Swal.showValidationMessage('Phone number is required.');
                                                return false;
                                            }
                                            return { body: body, phone: finalPhone };
                                        }
                                    }).then((result) => {
                                        if (!result.isConfirmed || !trackerId) return;
                                        $.ajax({
                                            url: '/api/mobile/tracker/' + trackerId + '/send-sms',
                                            method: 'POST',
                                            contentType: 'application/json',
                                            data: JSON.stringify({ message: result.value.body, phone: result.value.phone, _token: csrfToken }),
                                            headers: { 'X-CSRF-TOKEN': csrfToken }
                                        }).done(function (res) {
                                            Swal.fire({
                                                icon: res.success ? 'success' : 'warning',
                                                title: res.success ? 'SMS sent' : 'SMS not sent',
                                                text: res.message || '',
                                                timer: 2200,
                                                showConfirmButton: false
                                            });
                                        }).fail(function (xhr) {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'SMS failed',
                                                text: xhr.responseJSON?.message || 'Server error while sending SMS.'
                                            });
                                        });
                                    });
                                }
                            } else {
                                btn.html(originalHtml).prop('disabled', false);
                                lucide.createIcons();
                                showScanError(response.message || 'Log failed. Try again.');
                            }
                        },
                        error: function (xhr) {
                            btn.html(originalHtml).prop('disabled', false);
                            lucide.createIcons();
                            const msg = xhr.responseJSON?.message || 'Server error. Try again.';
                            showScanError(msg);
                        }
                    });
                });
            }

            if (modalId === 'update-movement-modal') {
                const $destinationSelect = $('#modal-destination-office');
                const $receivingOfficerSelect = $('#modal-receiving-officer');

                const setSelectPlaceholder = ($select, message) => {
                    if (!$select || !$select.length) {
                        return;
                    }
                    $select.html(`<option value="">${message}</option>`);
                };

                const hydrateDestinationOffices = () => {
                    if (typeof ensureOfficeDirectory === 'function' && typeof populateOfficeSelect === 'function') {
                        ensureOfficeDirectory()
                            .then(() => {
                                populateOfficeSelect($destinationSelect, {
                                    placeholder: 'Select destination office',
                                    includeCustomOption: false
                                });
                            })
                            .catch(() => {
                                setSelectPlaceholder($destinationSelect, 'Unable to load offices');
                            });
                    } else {
                        setSelectPlaceholder($destinationSelect, 'Office list unavailable');
                    }
                };

                const hydrateReceivingOfficers = () => {
                    if (typeof ensureReceivingDirectoryReady === 'function' && typeof populateReceivingOfficerOptionsForElement === 'function') {
                        ensureReceivingDirectoryReady()
                            .then(() => {
                                populateReceivingOfficerOptionsForElement($receivingOfficerSelect, '', {
                                    includeCustomOption: false,
                                    placeholder: 'Select receiving officer',
                                    dropdownParent: $receivingOfficerSelect.closest('.space-y-2')
                                });
                            })
                            .catch(() => {
                                setSelectPlaceholder($receivingOfficerSelect, 'Unable to load officers');
                            });
                    } else {
                        setSelectPlaceholder($receivingOfficerSelect, 'Directory unavailable');
                    }
                };

                hydrateDestinationOffices();
                hydrateReceivingOfficers();

                // Form validation
                function checkMovementUpdateModalForm() {
                    const trackingId = $('#modal-movement-tracking-id').val().trim();
                    const office = $('#modal-destination-office').val();
                    const officer = $('#modal-receiving-officer').val();
                    const btn = $('#modal-update-movement-btn');

                    if (trackingId && office && officer) {
                        btn.prop('disabled', false);
                    } else {
                        btn.prop('disabled', true);
                    }
                }

                // Auto-lookup file info if trackingId is provided
                const initialTrackingId = $('#modal-movement-tracking-id').val().trim();
                console.log('Initial tracking ID:', initialTrackingId);
                if (initialTrackingId) {
                    // Immediate lookup
                    QuickActions.lookupFileMovementInfo(initialTrackingId);
                    // Also set a timeout as backup
                    setTimeout(() => {
                        QuickActions.lookupFileMovementInfo(initialTrackingId);
                    }, 500);
                }

                // Event listeners
                $('#modal-movement-tracking-id, #modal-destination-office, #modal-receiving-officer').on('input change', checkMovementUpdateModalForm);

                // Add event handlers for tracking ID lookup
                $('#modal-search-movement-tracking').on('click', function () {
                    const trackingId = $('#modal-movement-tracking-id').val().trim();
                    if (trackingId) {
                        QuickActions.lookupFileMovementInfo(trackingId);
                    }
                });

                $('#modal-movement-tracking-id').on('keyup', function (e) {
                    if (e.key === 'Enter') {
                        const trackingId = $(this).val().trim();
                        if (trackingId) {
                            QuickActions.lookupFileMovementInfo(trackingId);
                        }
                    }
                });

                // Movement update submission
                $('#modal-update-movement-btn').on('click', function () {
                    const btn = $(this);
                    const trackingId = $('#modal-movement-tracking-id').val().trim();
                    const office = $('#modal-destination-office').val();
                    const officer = $('#modal-receiving-officer').val();
                    const notes = $('#modal-movement-notes').val().trim();
                    const immediateAcceptance = $('#modal-immediate-acceptance').is(':checked');

                    if (!trackingId || !office || !officer) {
                        if (typeof showToast === 'function') {
                            showToast('Please fill in all required fields', 'error');
                        }
                        return;
                    }

                    // This would integrate with the existing movement update functionality
                    if (typeof showToast === 'function') {
                        showToast('Movement update functionality will be integrated with existing system', 'info');
                    }

                    setTimeout(() => self.closeModal('update-movement-modal'), 1500);
                });
            }
        },

        // Close modal
        closeModal: function (modalId) {
            if (modalId === 'search-files-modal' && this.searchRequest) {
                try {
                    this.searchRequest.abort();
                } catch (error) {
                    console.warn('Unable to abort search request:', error);
                }
                this.searchRequest = null;
            }
            $(`#${modalId}`).fadeOut(200, function () {
                $(this).remove();
            });
        },

        // Render assignment center content
        renderAssignmentContent: function () {
            const panelAvailable = Boolean(window.AssignmentWorkflow && typeof window.AssignmentWorkflow.openPanel === 'function');
            const availabilityMessage = panelAvailable
                ? 'Log out a file from the movement history to assign it instantly, or open the panel for a dedicated workspace.'
                : 'Assignments can be coordinated manually for now. Follow the workflow checklist below to keep everyone informed.';

            return `
                <div class="space-y-6 text-left">
                    <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-5">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                                <i data-lucide="users" class="h-5 w-5"></i>
                            </span>
                            <div class="space-y-1">
                                <p class="text-base font-semibold text-indigo-800">Coordinate assignments in one place</p>
                                <p class="text-sm text-indigo-700">${this.escapeHtml(availabilityMessage)}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button id="assignment-open-panel" type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700" ${panelAvailable ? '' : 'data-state="disabled"'}>
                                <i data-lucide="layout-panel-left" class="mr-2 h-4 w-4"></i>
                                ${panelAvailable ? 'Open Assignment Panel' : 'Assignment Panel (Coming Soon)'}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 bg-white p-5">
                            <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <i data-lucide="send" class="h-4 w-4 text-gray-500"></i>
                                Start a new assignment
                            </h4>
                            <ol class="mt-3 space-y-2 text-sm text-gray-600 list-decimal list-inside">
                                <li>Open the file tracker from the log panel.</li>
                                <li>Choose <span class="font-medium">Log Out</span> on the active movement.</li>
                                <li>Confirm the next office, officer, and instructions in the prompt.</li>
                                <li>Submit to timestamp the logout and notify the assignee instantly.</li>
                            </ol>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-5">
                            <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <i data-lucide="check-circle" class="h-4 w-4 text-gray-500"></i>
                                Review pending assignments
                            </h4>
                            <ul class="mt-3 space-y-2 text-sm text-gray-600 list-disc list-inside">
                                <li>Use the <span class="font-medium">Pending Assignments</span> filter in the log.</li>
                                <li>Acknowledge tasks with the <span class="font-medium">Accept</span> or <span class="font-medium">Reject</span> buttons.</li>
                                <li>Every response logs a movement and updates notifications.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-5">
                        <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="info" class="h-4 w-4 text-gray-500"></i>
                            Need an overview?
                        </h4>
                        <p class="mt-2 text-sm text-gray-600">
                            Track recent assignment activity from the movement log and the notifications tray. This quick action will evolve into a live dashboard once the new assignment APIs are wired into the interface.
                        </p>
                    </div>
                </div>
            `;
        },

        // Try to open the shared assignment panel if available
        launchAssignmentPanel: function (source) {
            if (window.AssignmentWorkflow && typeof window.AssignmentWorkflow.openPanel === 'function') {
                try {
                    window.AssignmentWorkflow.openPanel({ source: source || 'quick-action' });
                    return true;
                } catch (error) {
                    console.warn('Unable to open assignment panel:', error);
                }
            }

            return false;
        },

        // Render search files content
        renderSearchFilesContent: function () {
            return `
                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="space-y-4 p-6">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                                <div class="relative flex-1">
                                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" id="search-query" placeholder="Enter tracking ID, file number, or applicant name" class="w-full rounded-lg border border-gray-200 py-3 pl-10 pr-4 text-sm text-gray-700 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40" />
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button id="clear-search" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">
                                        <i data-lucide="rotate-ccw" class="mr-2 h-4 w-4"></i>
                                        Clear
                                    </button>
                                    <button id="perform-search" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                        <i data-lucide="search" class="mr-2 h-4 w-4"></i>
                                        Search
                                    </button>
                                </div>
                            </div>
                            <div class="rounded-lg border border-blue-100 bg-blue-50/70 p-4 text-sm text-blue-800">
                                <div class="flex items-start gap-3">
                                    <i data-lucide="scan" class="mt-0.5 h-4 w-4"></i>
                                    <div class="space-y-1">
                                        <p class="font-medium">Scan-ready lookup</p>
                                        <p>Paste or scan a tracking ID. We check live trackers first, then the legacy archive (<code>fileNumber</code>) table automatically.</p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">Examples: ST-RES-2025-14, TRA-000542, Alhaji Ibrahim Dantata</p>
                        </div>
                    </div>

                    <div id="search-results" class="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
                        ${this.renderSearchEmptyState()}
                    </div>
                </div>
            `;
        },

        renderSearchEmptyState: function () {
            return `
                <div class="flex flex-col items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-50">
                        <i data-lucide="search" class="h-6 w-6 text-blue-400"></i>
                    </div>
                    <p class="text-base font-semibold text-gray-700">Search File Trackers</p>
                    <p class="text-sm text-gray-500">We will search active trackers and legacy archive records when you run a lookup.</p>
                    <p class="text-xs text-gray-400">Tip: scanning a QR pastes the tracking ID automatically.</p>
                </div>
            `;
        },

        displaySearchPlaceholder: function () {
            $('#search-results').html(this.renderSearchEmptyState());
            lucide.createIcons();
        },

        renderLoadingState: function (query) {
            const label = query ? `<span class="font-semibold">${this.escapeHtml(query)}</span>` : 'your request';
            return `
                <div class="space-y-4 py-16 text-center text-gray-600">
                    <div class="mx-auto inline-flex h-12 w-12 animate-spin items-center justify-center rounded-full border-2 border-blue-100 border-t-blue-600"></div>
                    <p class="text-sm font-medium">Checking live trackers and archives for ${label}...</p>
                </div>
            `;
        },

        focusSearchInput: function (selectAll = false) {
            const input = document.getElementById('search-query');
            if (!input) {
                return;
            }

            input.focus({ preventScroll: true });

            if (selectAll) {
                requestAnimationFrame(() => {
                    try {
                        input.select();
                    } catch (error) {
                        console.warn('Unable to select search input text:', error);
                    }
                });
            }
        },

        // Perform file search
        performFileSearch: function () {
            const rawQuery = $('#search-query').val();
            const query = (rawQuery || '').trim();

            if (!query) {
                this.displaySearchMessage('Enter a tracking ID or file number to begin.', 'info');
                this.focusSearchInput(true);
                return;
            }

            if (this.searchRequest) {
                this.searchRequest.abort();
                this.searchRequest = null;
            }

            $('#search-results').html(this.renderLoadingState(query));

            const self = this;

            this.searchRequest = $.ajax({
                url: '/create-file-tracker/search',
                method: 'GET',
                dataType: 'json',
                data: { query }
            })
                .done(function (response) {
                    if (response && response.success && response.data) {
                        if (response.data.status === 'tracked' && response.data.tracker) {
                            const normalized = typeof transformApiTracker === 'function'
                                ? transformApiTracker(response.data.tracker)
                                : response.data.tracker;

                            if (normalized) {
                                if (typeof upsertLocalTracker === 'function') {
                                    upsertLocalTracker(normalized);
                                }
                                self.displayTrackedResult(normalized, query);
                            } else {
                                self.displaySearchMessage('Tracker located, but the response could not be processed.', 'warning', query);
                            }
                            return;
                        }

                        if (response.data.status === 'archive' && response.data.record) {
                            self.displayArchiveResult(response.data.record, query);
                            return;
                        }
                    }

                    const message = (response && response.message) || 'No tracker or archive entry found for that reference.';
                    self.displaySearchMessage(message, 'warning', query);
                })
                .fail(function (xhr, status) {
                    if (status === 'abort') {
                        return;
                    }

                    const message = xhr?.responseJSON?.message || 'Unable to search right now. Please try again.';
                    const type = xhr?.status === 404 ? 'warning' : 'error';
                    self.displaySearchMessage(message, type, query);
                })
                .always(function () {
                    self.searchRequest = null;
                    self.focusSearchInput(true);
                });
        },
        displayTrackedResult: function (tracker, query) {
            const self = this;
            const officeCode = tracker.currentOfficeId || tracker.currentOffice || '';
            const office = (window.officeData && window.officeData[officeCode]) || { name: tracker.currentOffice || '—', code: officeCode };
            const hasActive = Array.isArray(tracker.logEntries) && tracker.logEntries.some(entry => (entry.status || '').toLowerCase() === 'active');
            const statusLabel = hasActive ? 'Active' : 'Completed';
            const statusClass = hasActive ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700';
            const createdAt = tracker.createdAt ? self.formatDate(tracker.createdAt) : '—';
            const fileNo = tracker.fileNo || '—';
            const trackingId = tracker.trackingId || query || '—';
            const fileName = tracker.fileName || 'Unnamed File';

            // Row with a leading lucide icon. `tone` tints the value (holder, warnings).
            const row = (icon, label, value, tone) => {
                if (!value) return '';
                const toneClass = tone === 'accent' ? 'text-blue-700'
                    : tone === 'warn' ? 'text-amber-700'
                    : tone === 'muted' ? 'text-gray-500 font-medium'
                    : 'text-gray-900';
                return `
                <div class="flex items-center justify-between gap-4 py-2.5 border-b border-gray-100 last:border-0">
                    <span class="flex items-center gap-2 text-xs font-semibold text-gray-500">
                        <i data-lucide="${icon}" class="h-3.5 w-3.5 text-gray-400"></i>${label}
                    </span>
                    <span class="text-xs font-bold text-right ${toneClass}">${self.escapeHtml(value)}</span>
                </div>`;
            };

            // Where the file physically is. `tracker.status` is the WORKFLOW status
            // (submitted / recommended / approved / …), never 'IN_TRANSIT', so keying the
            // holder off it always fell through to the archive branch and printed
            // "Receiving Officer: Archive" for files that were plainly logged out.
            // The physical state comes from the active movement, with the resolver's
            // logged-out / duration fields (IN_TRANSIT-only) as the fallback signal.
            const activeEntry = (tracker.logEntries || []).find(
                entry => (entry.status || '').toLowerCase() === 'active'
            ) || null;
            const isInTransit = Boolean(activeEntry)
                || String(tracker.status || '').toUpperCase() === 'IN_TRANSIT'
                || Boolean(tracker.loggedOutAt || tracker.durationWithHolder);

            const holderName = activeEntry?.receivingOfficerName
                || tracker.receivingOfficerName
                || tracker.receivingOfficer?.name
                || null;
            const holderOffice = activeEntry?.receivingOfficeName
                || tracker.currentOffice
                || tracker.receivingOfficeName
                || null;
            const lastHolder = holderName
                || (tracker.logEntries || []).slice().reverse().find(e => e.receivingOfficerName)?.receivingOfficerName
                || null;

            let dept = (tracker.receivingDepartment || tracker.department || '').trim();
            if (dept && !/department$/i.test(dept)) dept = dept + ' Department';

            const locationRows = isInTransit
                ? row('map-pin', 'Current Location', holderOffice)
                    + row('user-check', 'Receiving Officer (holder)', holderName || 'Not recorded', holderName ? 'accent' : 'muted')
                    + row('building-2', 'Department', dept)
                    + row('log-out', 'Logged Out', tracker.loggedOutAt)
                    + row('timer', 'Duration with holder', tracker.durationWithHolder, 'warn')
                : row('map-pin', 'Current Location', tracker.currentOffice || tracker.registry || 'Registry / Archive')
                    + row('archive', 'Held By', 'In Registry / Archive — no officer holding', 'muted')
                    + row('user', 'Last held by', lastHolder, 'muted');

            const locationBadge = isInTransit
                ? '<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-700"><i data-lucide="send" class="h-3 w-3"></i>In Transit</span>'
                : '<span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold text-blue-700"><i data-lucide="archive" class="h-3 w-3"></i>In Archive</span>';

            const detailsHtml = `
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                        <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-600">
                            <i data-lucide="map-pinned" class="h-3.5 w-3.5"></i> File Location
                        </span>
                        ${locationBadge}
                    </div>
                    <div class="px-4 py-1">
                        ${row('library', 'Registry', tracker.registry || 'Registry / Archive')}
                        ${row('layout-grid', 'Shelf/Rack', tracker.rackShelfLocation || 'Not assigned', tracker.rackShelfLocation ? undefined : 'muted')}
                        ${locationRows}
                    </div>
                </div>
            `;

            const html = `
                <div class="space-y-4 text-left">
                    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                            <i data-lucide="check-circle" class="h-5 w-5"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-base font-semibold text-emerald-700">Active tracker located</p>
                            <p class="text-sm text-emerald-800/80">We added the tracker to the log panel. Use the shortcuts below to jump to it.</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 text-sm font-bold text-gray-900">
                                    <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>${self.escapeHtml(fileNo)}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-gray-500">${self.escapeHtml(fileName)}</p>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-2 text-[11px] text-gray-500">
                            <span class="inline-flex items-center gap-1"><i data-lucide="hash" class="h-3 w-3"></i>${self.escapeHtml(trackingId)}</span>
                            <span class="inline-flex items-center gap-1"><i data-lucide="calendar" class="h-3 w-3"></i>Created ${self.escapeHtml(createdAt)}</span>
                        </div>
                    </div>

                    ${detailsHtml}

                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden text-left" data-movement-timeline>
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wide text-gray-600 flex items-center gap-2">
                            <i data-lucide="route" class="h-3.5 w-3.5"></i> Movement Timeline
                        </div>
                        <div class="px-4 pb-3 pt-3">
                            <div class="modal-movement-timeline"></div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700" data-action="focus-tracker">
                            <i data-lucide="panel-left-open" class="mr-2 h-4 w-4"></i>
                            Show in Log
                        </button>
                        <button type="button" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900" data-action="view-details">
                            <i data-lucide="eye" class="mr-2 h-4 w-4"></i>
                            View Details
                        </button>
                        <button type="button" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900" data-action="copy-tracking">
                            <i data-lucide="clipboard" class="mr-2 h-4 w-4"></i>
                            Copy Tracking ID
                        </button>
                    </div>
                </div>
            `;

            $('#search-results').html(html);
            lucide.createIcons();

            const container = $('#search-results');

            const movementDetails = container.find('[data-movement-timeline]')[0];
            if (movementDetails) {
                self.toggleMovementTimeline(fileNo, movementDetails, tracker.originRegistry, tracker.rackShelfLocation);
            }

            container.find('[data-action="focus-tracker"]').on('click', function () {
                self.closeModal('search-files-modal');
                switchMainTab('logs');

                setTimeout(() => {
                    const searchField = document.getElementById('search-logs');
                    if (searchField) {
                        searchField.value = trackingId;
                        if (typeof filterTrackerLogs === 'function') {
                            filterTrackerLogs(trackingId);
                        }
                    }
                    if (typeof highlightTrackerCard === 'function') {
                        highlightTrackerCard(trackingId);
                    }
                }, 200);
            });

            container.find('[data-action="view-details"]').on('click', function () {
                if (typeof window.showTrackerDetails === 'function') {
                    window.showTrackerDetails(trackingId);
                }
            });

            container.find('[data-action="copy-tracking"]').on('click', function () {
                if (!trackingId || trackingId === '—') {
                    return;
                }

                if (!navigator.clipboard || !navigator.clipboard.writeText) {
                    if (typeof showNotification === 'function') {
                        showNotification('Clipboard access is not available in this browser.', 'error');
                    }
                    return;
                }

                navigator.clipboard.writeText(trackingId).then(() => {
                    if (typeof showNotification === 'function') {
                        showNotification(`Tracking ID ${trackingId} copied to clipboard.`, 'success');
                    }
                }).catch(() => {
                    if (typeof showNotification === 'function') {
                        showNotification('Unable to copy the tracking ID.', 'error');
                    }
                });
            });
        },

        displayArchiveResult: function (record, query) {
            const self = this;
            const fileNumber = self.escapeHtml(record.file_number || '—');
            const trackingId = self.escapeHtml(record.tracking_id || '—');
            const fileName = self.escapeHtml(record.file_name || 'Unknown applicant');
            const location = self.escapeHtml(record.location || 'Archive');
            const source = self.escapeHtml(record.type || 'Legacy record');
            const created = record.created_at ? self.escapeHtml(self.formatDate(record.created_at)) : '—';
            const reason = self.escapeHtml(record.decommissioning_reason || '—');

            const html = `
                <div class="space-y-5 text-left">
                    <div class="mb-3 rounded-lg border border-gray-200 mt-4 text-left" data-movement-timeline>
                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 flex items-center gap-2">
                            <i data-lucide="route" class="h-3.5 w-3.5"></i> Movement Timeline
                        </div>
                        <div class="px-4 pb-3 pt-3">
                            <div class="modal-movement-timeline"></div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <button type="button" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700" data-action="prefill-tracker">
                            <i data-lucide="plus" class="mr-2 h-4 w-4"></i>
                            Prepare New Tracker
                        </button>
                        <button type="button" class="inline-flex items-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50" data-action="clear-search">
                            <i data-lucide="rotate-ccw" class="mr-2 h-4 w-4"></i>
                            New Search
                        </button>
                    </div>
                </div>
            `;

            $('#search-results').html(html);
            lucide.createIcons();

            const container = $('#search-results');

            const movementDetails = container.find('[data-movement-timeline]')[0];
            if (movementDetails) {
                self.toggleMovementTimeline(record.file_number, movementDetails, record.location, record.rack_shelf_location);
            }

            container.find('[data-action="prefill-tracker"]').on('click', function () {
                if (typeof prefillCreateFormFromArchive === 'function') {
                    prefillCreateFormFromArchive(record);
                    if (typeof showNotification === 'function') {
                        showNotification('Archive details applied to the create form.', 'success');
                    }
                }
                self.closeModal('search-files-modal');
            });

            container.find('[data-action="clear-search"]').on('click', function () {
                $('#search-query').val('');
                self.displaySearchPlaceholder();
                self.focusSearchInput(true);
            });
        },

        displaySearchMessage: function (message, type = 'info', query = '') {
            const icons = {
                info: { icon: 'info', bg: 'bg-blue-50', color: 'text-blue-600' },
                warning: { icon: 'search-x', bg: 'bg-amber-50', color: 'text-amber-600' },
                error: { icon: 'alert-triangle', bg: 'bg-red-50', color: 'text-red-600' }
            };

            const config = icons[type] || icons.info;
            const queryLine = query
                ? `<p class="text-xs text-gray-500">Lookup reference: <span class="font-medium">${this.escapeHtml(query)}</span></p>`
                : '';

            const html = `
                <div class="space-y-4 py-16 text-center text-gray-600">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full ${config.bg}">
                        <i data-lucide="${config.icon}" class="h-6 w-6 ${config.color}"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-700">${this.escapeHtml(message)}</p>
                    ${queryLine}
                </div>
            `;

            $('#search-results').html(html);
            lucide.createIcons();
        },

        formatDate: function (value) {
            if (!value) {
                return '—';
            }

            let date;

            if (value instanceof Date) {
                date = value;
            } else {
                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) {
                    return value;
                }
                date = parsed;
            }

            return `${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
        },

        escapeHtml: function (value) {
            return (value ?? '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        // Render office list content
        renderOfficeListContent: function () {
            if (!window.officeData) {
                return '<div class="text-center py-8 text-gray-500"><p>Office data not available</p></div>';
            }

            let content = `
                <div class="space-y-4">
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900">Office Directory (${Object.keys(window.officeData).length} offices)</h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Files</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
            `;

            Object.entries(window.officeData).forEach(([id, office]) => {
                const activeFiles = this.getActiveFilesForOffice(id);
                content += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <i data-lucide="building" class="w-4 h-4 text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">${office.name}</div>
                                    <div class="text-sm text-gray-500">Office ID: ${id}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                ${office.code}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">${office.department}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                ${activeFiles} files
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                            <button onclick="QuickActions.viewOfficeFiles('${id}')" class="text-blue-600 hover:text-blue-900 mr-3">View Files</button>
                            <button onclick="QuickActions.contactOffice('${id}')" class="text-green-600 hover:text-green-900">Contact</button>
                        </td>
                    </tr>
                `;
            });

            content += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            return content;
        },

        // Get active files count for an office
        getActiveFilesForOffice: function (officeId) {
            if (!window.fileTrackers || !Array.isArray(window.fileTrackers)) {
                return 0;
            }
            return window.fileTrackers.filter(tracker => tracker.currentOffice === officeId).length;
        },

        // View files for specific office
        viewOfficeFiles: function (officeId) {
            if (!window.officeData || !window.officeData[officeId]) {
                console.warn('Office data not found for office:', officeId);
                return;
            }

            const office = window.officeData[officeId];
            const files = window.fileTrackers ? window.fileTrackers.filter(tracker => tracker.currentOffice === officeId) : [];

            this.showModal('office-files-modal', `Files in ${office.name}`, this.renderOfficeFilesContent(files, office));
        },

        // Render office files content
        renderOfficeFilesContent: function (files, office) {
            if (files.length === 0) {
                return `
                    <div class="text-center py-8 text-gray-500">
                        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                        <p>No files currently in ${office.name}</p>
                    </div>
                `;
            }

            let content = `
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900">Files in ${office.name} (${files.length} files)</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Here</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
            `;

            files.forEach(file => {
                const daysInOffice = this.calculateDaysInOffice(file);
                const priorityClass = `priority-${file.priority}`;

                content += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                ${file.fileNumber}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">${file.fileName}</div>

                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">
                                ${file.priority}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">${daysInOffice} days</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                            <button onclick="viewDetails('${file.trackingId}')" class="text-blue-600 hover:text-blue-900">View</button>
                        </td>
                    </tr>
                `;
            });

            content += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            return content;
        },

        // Calculate days in current office
        calculateDaysInOffice: function (file) {
            // Simplified calculation - in real app would use actual timestamps
            return Math.floor(Math.random() * 30) + 1;
        },

        // Contact office function
        contactOffice: function (officeId) {
            if (!window.officeData || !window.officeData[officeId]) {
                alert('Office information not available');
                return;
            }

            const office = window.officeData[officeId];
            alert(`Contact information for ${office.name} would be displayed here.`);
        },

        // Render track status content
        renderTrackStatusContent: function () {
            return `
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Enter File Number or Tracking ID</label>
                        <div class="flex gap-2">
                            <input type="text" id="track-input" placeholder="e.g., RES-2015-4859 or TRK-..." class="form-input flex-1">
                            <button id="track-search" class="btn-primary">
                                <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                Track
                            </button>
                        </div>
                    </div>
                    <div id="tracking-results" class="mt-4">
                        <div class="text-center py-8 text-gray-500">
                            <i data-lucide="map-pin" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                            <p>Enter a file number or tracking ID to track its current status and movement history</p>
                        </div>
                    </div>
                </div>
            `;
        },

        // Perform track search
        performTrackSearch: function () {
            const searchTerm = $('#track-input').val().trim();
            if (!searchTerm) {
                alert('Please enter a file number or tracking ID');
                return;
            }

            $('#tracking-results').html(`
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-600">Tracking...</p>
                </div>
            `);

            // Simulate API call
            setTimeout(() => {
                const tracker = this.findTracker(searchTerm);
                this.displayTrackingResults(tracker, searchTerm);
            }, 1000);
        },

        // Find tracker by file number or tracking ID
        findTracker: function (searchTerm) {
            if (!window.fileTrackers || !Array.isArray(window.fileTrackers)) {
                console.warn('fileTrackers array not found');
                return null;
            }

            return window.fileTrackers.find(tracker =>
                (tracker.fileNumber && tracker.fileNumber.toLowerCase() === searchTerm.toLowerCase()) ||
                (tracker.trackingId && tracker.trackingId.toLowerCase() === searchTerm.toLowerCase())
            );
        },

        // Display tracking results
        displayTrackingResults: function (tracker, searchTerm) {
            if (!tracker) {
                $('#tracking-results').html(`
                    <div class="text-center py-8 text-gray-500">
                        <i data-lucide="file-x" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                        <p>No file tracker found for "${searchTerm}"</p>
                        <p class="text-sm mt-2">Please check the file number or tracking ID and try again</p>
                    </div>
                `);
                lucide.createIcons();
                return;
            }

            const currentOffice = (window.officeData && window.officeData[tracker.currentOffice]) || { name: 'Unknown', code: 'UNK' };
            const movementHistory = this.generateMovementHistory(tracker);

            const content = `
                <div class="space-y-6">
                    <!-- Current Status Card -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-blue-900">Current Status</h3>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                Active
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-blue-600 font-medium">File Number</p>
                                <p class="text-blue-900 font-semibold">${tracker.fileNumber}</p>
                            </div>
                            <div>
                                <p class="text-sm text-blue-600 font-medium">File Name</p>
                                <p class="text-blue-900 font-semibold">${tracker.fileName}</p>
                            </div>
                            <div>
                                <p class="text-sm text-blue-600 font-medium">Priority</p>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium priority-${tracker.priority}">
                                    ${tracker.priority}
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <div class="flex items-center gap-2">
                                <i data-lucide="map-pin" class="w-5 h-5 text-blue-600"></i>
                                <span class="text-blue-900 font-medium">Currently at: ${currentOffice.name} (${currentOffice.code})</span>
                            </div>
                        </div>
                    </div>

                    <!-- Movement History -->
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                <i data-lucide="clock" class="w-4 h-4"></i>
                                Movement History
                            </h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Office</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Office</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
            `;

            movementHistory.forEach((movement, index) => {
                const isLatest = index === 0;
                const fromOffice = window.officeData[movement.fromOffice] || { name: 'Unknown', code: 'UNK' };
                const toOffice = window.officeData[movement.toOffice] || { name: 'Unknown', code: 'UNK' };

                content += `
                    <tr class="${isLatest ? 'bg-blue-50' : 'hover:bg-gray-50'}">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                            <div>${movement.date}</div>
                            <div class="text-xs text-gray-500">${movement.time}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${fromOffice.name}</div>
                            <div class="text-xs text-gray-500">${fromOffice.code}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${toOffice.name}</div>
                            <div class="text-xs text-gray-500">${toOffice.code}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${isLatest ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                ${movement.action}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">${movement.notes}</td>
                    </tr>
                `;
            });

            content += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            $('#tracking-results').html(content);
        },

        // Generate movement history for a tracker
        generateMovementHistory: function (tracker) {
            const movements = [
                {
                    date: '2024-12-12',
                    time: '14:30',
                    fromOffice: 'OFF-001',
                    toOffice: tracker.currentOffice,
                    action: 'Transferred',
                    notes: 'For processing and review'
                },
                {
                    date: '2024-12-11',
                    time: '09:15',
                    fromOffice: 'OFF-012',
                    toOffice: 'OFF-001',
                    action: 'Received',
                    notes: 'Initial registration'
                },
                {
                    date: '2024-12-10',
                    time: '16:45',
                    fromOffice: 'OFF-009',
                    toOffice: 'OFF-012',
                    action: 'Created',
                    notes: 'File tracker created'
                }
            ];

            return movements;
        },

        // Render statistics content
        renderStatisticsContent: function (stats) {
            return `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center gap-3">
                            <i data-lucide="file-text" class="w-8 h-8 text-blue-600"></i>
                            <div>
                                <p class="text-2xl font-bold text-blue-900">${stats.total || 0}</p>
                                <p class="text-sm text-blue-600">Total Trackers</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="flex items-center gap-3">
                            <i data-lucide="check-circle" class="w-8 h-8 text-green-600"></i>
                            <div>
                                <p class="text-2xl font-bold text-green-900">${stats.active || 0}</p>
                                <p class="text-sm text-green-600">Active</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <div class="flex items-center gap-3">
                            <i data-lucide="clock" class="w-8 h-8 text-orange-600"></i>
                            <div>
                                <p class="text-2xl font-bold text-orange-900">${stats.pending || 0}</p>
                                <p class="text-sm text-orange-600">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <div class="flex items-center gap-3">
                            <i data-lucide="alert-triangle" class="w-8 h-8 text-red-600"></i>
                            <div>
                                <p class="text-2xl font-bold text-red-900">${stats.overdue || 0}</p>
                                <p class="text-sm text-red-600">Overdue</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-3">By Priority</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm">High</span>
                                <span class="text-sm font-medium">${stats.priority?.High || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm">Medium</span>
                                <span class="text-sm font-medium">${stats.priority?.Medium || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm">Low</span>
                                <span class="text-sm font-medium">${stats.priority?.Low || 0}</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-3">Recent Activity</h4>
                        <div class="space-y-2">
                            <div class="text-sm text-gray-600">Last 24 hours: ${stats.recent?.day || 0} trackers</div>
                            <div class="text-sm text-gray-600">This week: ${stats.recent?.week || 0} trackers</div>
                            <div class="text-sm text-gray-600">This month: ${stats.recent?.month || 0} trackers</div>
                        </div>
                    </div>
                </div>
            `;
        },

        // Render bulk operations content
        renderBulkOperationsContent: function () {
            return `
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <button class="bulk-action-card" data-action="bulk-move">
                            <i data-lucide="move" class="w-8 h-8 text-blue-600 mb-2"></i>
                            <h4 class="font-medium">Bulk Move</h4>
                            <p class="text-sm text-gray-600">Move multiple files to same office</p>
                        </button>
                        <button class="bulk-action-card" data-action="bulk-priority">
                            <i data-lucide="flag" class="w-8 h-8 text-orange-600 mb-2"></i>
                            <h4 class="font-medium">Update Priority</h4>
                            <p class="text-sm text-gray-600">Change priority for multiple files</p>
                        </button>
                        <button class="bulk-action-card" data-action="bulk-archive">
                            <i data-lucide="archive" class="w-8 h-8 text-green-600 mb-2"></i>
                            <h4 class="font-medium">Bulk Archive</h4>
                            <p class="text-sm text-gray-600">Archive completed trackers</p>
                        </button>
                        <button class="bulk-action-card" data-action="bulk-export">
                            <i data-lucide="download" class="w-8 h-8 text-purple-600 mb-2"></i>
                            <h4 class="font-medium">Batch Export</h4>
                            <p class="text-sm text-gray-600">Export selected trackers</p>
                        </button>
                    </div>
                </div>
                <style>
                .bulk-action-card {
                    @apply p-4 border border-gray-200 rounded-lg text-center hover:bg-gray-50 transition-colors;
                }
                </style>
            `;
        },

        // Render export content
        renderExportContent: function () {
            return `
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Export Format</label>
                        <select id="export-format" class="form-input">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF Report</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" id="export-from" class="form-input">
                            <input type="date" id="export-to" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Include</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" checked class="mr-2"> File Details
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" checked class="mr-2"> Movement History
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Office Information
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button id="export-cancel" class="btn-secondary">Cancel</button>
                        <button id="export-download" class="btn-primary">Download</button>
                    </div>
                </div>
            `;
        },

        // Render print labels content
        renderPrintLabelsContent: function () {
            return `
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Label Type</label>
                        <select id="label-type" class="form-input">
                            <option value="qr">QR Code Labels</option>
                            <option value="barcode">Barcode Labels</option>
                            <option value="simple">Simple Text Labels</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Label Size</label>
                        <select id="label-size" class="form-input">
                            <option value="small">Small (2" x 1")</option>
                            <option value="medium">Medium (3" x 2")</option>
                            <option value="large">Large (4" x 3")</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Files</label>
                        <select id="print-files" multiple class="form-input h-32">
                            <!-- Options will be populated dynamically -->
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button id="print-cancel" class="btn-secondary">Cancel</button>
                        <button id="print-generate" class="btn-primary">Generate Labels</button>
                    </div>
                </div>
            `;
        },

        // Render settings content
        renderSettingsContent: function () {
            return `
                <div class="space-y-6">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">General Settings</h4>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Auto-refresh tracker list
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Show notifications
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Enable sound alerts
                            </label>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Default Values</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Default Priority</label>
                                <select class="form-input">
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Default Office</label>
                                <select class="form-input">
                                    <option value="">Select office...</option>
                                    ${Object.entries(window.officeData || {}).map(([id, office]) =>
                `<option value="${id}">${office.name}</option>`
            ).join('')}
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button id="settings-cancel" class="btn-secondary">Cancel</button>
                        <button id="settings-save" class="btn-primary">Save Settings</button>
                    </div>
                </div>
            `;
        },

        // Load statistics from API
        loadStatistics: function () {
            return $.ajax({
                url: '/api/file-trackers/dashboard',
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            }).then(response => {
                if (response.success) {
                    return response.data;
                }
                return {};
            }).catch(() => {
                return {
                    total: 0,
                    active: 0,
                    pending: 0,
                    overdue: 0,
                    priority: { High: 0, Medium: 0, Low: 0 },
                    recent: { day: 0, week: 0, month: 0 }
                };
            });
        },

        // Render Update Status modal content
        renderScanQrContent: function () {
            return `
                <div class="space-y-6">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="scan-line" class="h-5 w-5 text-amber-600 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-semibold text-amber-900">New KANGIS \u2014 Scan QR to Log File</p>
                                <p class="text-sm text-amber-700 mt-1">Scan or type the Tracking ID from the KANGIS Tracking Sheet. The file will be automatically logged into the next department based on the current workflow step.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="scan-qr-input" class="block text-sm font-medium text-gray-700">Tracking ID / File Number *</label>
                        <div class="relative">
                            <input type="text" id="scan-qr-input"
                                   placeholder="Scan QR or type tracking ID here..."
                                   autocomplete="off"
                                   class="block w-full px-3 py-2 pr-10 border border-amber-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-base font-mono">
                            <i data-lucide="scan-line" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-amber-400 pointer-events-none"></i>
                        </div>
                        <p class="text-xs text-gray-500">Place cursor here, then scan the QR code with a barcode/QR scanner or type the value manually.</p>
                    </div>

                    <div id="scan-qr-file-preview" class="hidden p-4 bg-gray-50 border border-gray-200 rounded-lg">
                        <div class="flex items-start gap-3">
                            <i data-lucide="file-text" class="h-5 w-5 text-gray-600 mt-0.5"></i>
                            <div class="flex-1 space-y-2 text-sm">
                                <p class="font-semibold text-gray-900" id="scan-qr-file-title">-</p>
                                <div class="text-xs text-gray-600">
                                    <span>File Number:</span>
                                    <span id="scan-qr-file-number" class="font-mono font-semibold text-gray-800 ml-1">-</span>
                                </div>
                                <div class="mt-2 p-3 bg-white border border-amber-200 rounded-md">
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Workflow Step</div>
                                    <ol id="scan-qr-step-list" class="relative border-l-2 border-amber-200 ml-3 space-y-2"></ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="scan-qr-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

                    <div class="space-y-2">
                        <label for="scan-qr-notes" class="block text-sm font-medium text-gray-700">Notes <span class="text-gray-400">(optional)</span></label>
                        <input type="text" id="scan-qr-notes" placeholder="e.g. Received by Sani at Vetting..."
                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" id="scan-qr-cancel-btn"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" id="scan-qr-submit-btn" disabled
                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed min-w-[140px]">
                            <i data-lucide="check-circle" class="h-4 w-4 mr-2"></i>
                            Log to Next Dept
                        </button>
                    </div>

                    <div id="scan-qr-result" class="hidden p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800"></div>
                </div>
            `;
        },

                renderUpdateStatusContent: function () {
            return `
                <div class="space-y-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="info" class="h-5 w-5 text-blue-600 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-semibold text-blue-900">Log File Back to Registry (Origin)</p>
                                <p class="text-sm text-blue-700 mt-1">Update file status and assign to Registry office. Available statuses: Log-in, Cancelled</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label for="modal-update-tracking-id" class="block text-sm font-medium text-gray-700">Tracking ID *</label>
                            <div class="relative">
                                <input type="text" id="modal-update-tracking-id" placeholder="Enter or scan tracking ID" class="block w-full px-3 py-2 pr-20 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <div class="absolute inset-y-0 right-0 flex">
                                    <button type="button" id="modal-scan-tracking" class="flex items-center px-2 border-l border-gray-300 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Scan QR Code">
                                        <i data-lucide="scan-line" class="h-4 w-4"></i>
                                    </button>
                                    <button type="button" id="modal-search-tracking" class="flex items-center px-2 border-l border-gray-300 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-colors rounded-r-md" title="Search tracking records">
                                        <i data-lucide="search" class="h-4 w-4"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">Provide the exact tracking ID to see file details</p>
                        </div>

                        <div id="modal-file-preview" class="hidden p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <i data-lucide="file-text" class="h-5 w-5 text-gray-600 mt-0.5"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900" id="preview-file-name">Loading...</p>
                                    <div class="mt-2 space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>File Number:</span>
                                            <span id="preview-file-number" class="font-mono">-</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Current Location:</span>
                                            <span id="preview-location">-</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Current Status:</span>
                                            <span id="preview-status" class="px-2 py-1 rounded text-xs font-medium">-</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Handler:</span>
                                            <span id="preview-handler">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="modal-registry-office" class="block text-sm font-medium text-gray-700">Registry (Origin) *</label>
                            <select id="modal-registry-office" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" disabled>
                                <option value="">Select Registry (Origin)</option>
                                @isset($registries)
                                    @foreach($registries as $registry)
                                        <option value="{{ $registry->registry_code }}" data-name="{{ $registry->name }}">{{ $registry->name }}</option>
                                    @endforeach
                                @endisset
                            </select>
                            <p class="text-xs text-gray-500">The file will be logged back to the selected origin Registry</p>
                        </div>

                        <div class="space-y-2">
                            <label for="modal-status-update" class="block text-sm font-medium text-gray-700">Status *</label>
                            <select id="modal-status-update" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" disabled>
                                <option value="">Select status</option>
                                <option value="Log-in">Log-in</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="Completed">Completed</option>
                            </select>
                            <p class="text-xs text-gray-500">Update file status</p>
                        </div>

                        <div id="modal-page-count-section" class="hidden space-y-2">
                            <label for="modal-num-pages" class="block text-sm font-medium text-gray-700">Number of Pages Returned <span class="text-red-500">*</span></label>
                            <input type="number" id="modal-num-pages" name="num_pages" min="1" max="99999" required placeholder="Enter total pages…" class="block w-1/2 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" disabled>
                            {{-- Informational notice: original page count recorded at log-out --}}
                            <div id="modal-original-pages-notice" class="hidden flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                                <i data-lucide="info" class="h-4 w-4 text-blue-500 shrink-0 mt-0.5"></i>
                                <p class="text-xs text-blue-800">
                                    This file had <strong id="modal-original-pages-value">—</strong> pages when it was logged out. Please enter the number of pages being returned.
                                </p>
                            </div>
                            {{-- Discrepancy warning, shown live when returned pages differ from the original --}}
                            <div id="modal-page-discrepancy" class="hidden flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2">
                                <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-500 shrink-0 mt-0.5"></i>
                                <p class="text-xs text-amber-800" id="modal-page-discrepancy-text"></p>
                            </div>
                            <p id="modal-num-pages-error" class="hidden text-xs text-red-600">Please enter a valid number of pages (1–99,999).</p>
                        </div>

                        {{-- Reason for Delay: only shown/required when the file's timeline status
                             is Amber (due soon) or Red (overdue) at the moment it's logged back. --}}
                        <div id="modal-delay-reason-section" class="hidden space-y-2">
                            <label for="modal-delay-reason" class="block text-sm font-medium text-gray-700">Reason for Delay <span class="text-red-500">*</span></label>
                            <div id="modal-delay-reason-notice" class="hidden flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 mb-1">
                                <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-500 shrink-0 mt-0.5"></i>
                                <p class="text-xs text-amber-800" id="modal-delay-reason-notice-text"></p>
                            </div>
                            <textarea id="modal-delay-reason" rows="2" placeholder="Explain why this file is being returned late / close to its deadline…" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500" disabled></textarea>
                            <p id="modal-delay-reason-error" class="hidden text-xs text-red-600">Please state the reason for the delay.</p>
                        </div>

                        <div class="space-y-2">
                            <label for="modal-status-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="modal-status-notes" rows="3" placeholder="Optional notes about logging back to registry..." class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" disabled></textarea>
                        </div>
                    </div>

                    <div id="modal-status-info" class="hidden p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <i data-lucide="check-circle" class="h-4 w-4 text-green-600 mt-0.5"></i>
                            <div id="modal-file-info" class="text-sm text-green-800"></div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" id="modal-update-status-btn" class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                            <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                            Log Back to Registry
                        </button>
                    </div>
                </div>
            `;
        },
// Render Update Movement modal content
        renderUpdateMovementContent: function (trackingId = '') {
            return `
                <div class="space-y-6">
                    <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="move" class="h-5 w-5 text-teal-600 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-semibold text-teal-900">Update File Movement</p>
                                <p class="text-sm text-teal-700 mt-1">Transfer file to a new office or update current location information</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label for="modal-movement-tracking-id" class="block text-sm font-medium text-gray-700">Tracking ID *</label>
                            <div class="relative">
                                <input type="text" id="modal-movement-tracking-id" value="${trackingId}" placeholder="Enter tracking ID" class="block w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button type="button" id="modal-search-movement-tracking" class="absolute inset-y-0 right-0 flex items-center px-3 border-l border-gray-300 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-colors rounded-r-md">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Current File Information -->
                        <div id="modal-movement-file-info" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-900 mb-3 flex items-center gap-2">
                                <i data-lucide="info" class="w-4 h-4"></i>
                                Current File Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="text-xs font-semibold text-blue-700 uppercase">File Name:</span>
                                    <div id="modal-current-file-name" class="text-blue-900 font-medium">—</div>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-blue-700 uppercase">File Number:</span>
                                    <div id="modal-current-file-number" class="text-blue-900 font-mono">—</div>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-blue-700 uppercase">Current Office:</span>
                                    <div id="modal-current-office" class="text-blue-900 font-medium">—</div>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-blue-700 uppercase">Current Receiving Officer:</span>
                                    <div id="modal-current-officer" class="text-blue-900 font-medium">—</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label for="modal-destination-office" class="block text-sm font-medium text-gray-700">Destination Office *</label>
                                <select id="modal-destination-office" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select destination office</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label for="modal-receiving-officer" class="block text-sm font-medium text-gray-700">Receiving Officer *</label>
                                <select id="modal-receiving-officer" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select receiving officer</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <label for="modal-movement-notes" class="block text-sm font-medium text-gray-700">Transfer Notes</label>
                            <textarea id="modal-movement-notes" rows="3" placeholder="Reason for the file transfer..." class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <label class="flex items-start gap-2 text-sm text-amber-800">
                                <input type="checkbox" id="modal-immediate-acceptance" class="mt-1 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                <span>
                                    Receiver is present — mark as accepted immediately
                                    <span class="block text-xs text-amber-700 mt-1">Only check this when the receiving officer physically confirms the handover</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="modal-movement-info" class="hidden p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <i data-lucide="check-circle" class="h-4 w-4 text-green-600 mt-0.5"></i>
                            <div id="modal-movement-file-info" class="text-sm text-green-800"></div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" id="modal-update-movement-btn" class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                            <i data-lucide="move" class="h-4 w-4 mr-2"></i>
                            Update Movement
                        </button>
                    </div>
                </div>
            `;
        }
    };

    // Add lookup methods to QuickActions
    QuickActions.lookupFileMovementInfo = function (trackingId) {
        console.log('Looking up file info for:', trackingId);
        if (!trackingId || trackingId.trim() === '') {
            this.clearFileMovementInfo();
            return;
        }

        // Show loading state
        const infoDiv = $('#modal-movement-file-info');
        infoDiv.removeClass('hidden');
        $('#modal-current-file-name').text('Loading...');
        $('#modal-current-file-number').text('Loading...');
        $('#modal-current-office').text('Loading...');
        $('#modal-current-officer').text('Loading...');

        // Search in current tracker list first
        if (typeof allTrackers !== 'undefined' && Array.isArray(allTrackers)) {
            console.log('Searching in', allTrackers.length, 'trackers');
            const tracker = allTrackers.find(t => {
                const matches = t.trackingId === trackingId ||
                    t.tracking_id === trackingId ||
                    t.id === trackingId ||
                    (t.trackingId && t.trackingId.includes(trackingId)) ||
                    (t.tracking_id && t.tracking_id.includes(trackingId));
                console.log('Checking tracker:', t.trackingId || t.tracking_id || t.id, 'matches:', matches);
                return matches;
            });

            if (tracker) {
                console.log('Found tracker locally:', tracker);
                this.displayFileMovementInfo(tracker);
                return;
            }
        }

        console.log('Making API call for:', trackingId);
        // If not found locally, make API call
        $.ajax({
            url: '/create-file-tracker/search',
            method: 'GET',
            data: { query: trackingId },
            success: (response) => {
                console.log('API response:', response);
                if (response.success && response.data) {
                    const payload = response.data;
                    if (payload.tracker) {
                        this.displayFileMovementInfo(payload.tracker);
                        return;
                    }

                    if (payload.record) {
                        const archiveTracker = Object.assign({}, payload.record, {
                            current_office_name: payload.record.current_office_name
                                || payload.record.location
                                || 'Archive Records',
                            receiving_officer_name: payload.record.receiving_officer_name
                                || payload.record.current_handler
                                || 'Archive Desk'
                        });
                        this.displayFileMovementInfo(archiveTracker);
                        return;
                    }

                    this.displayFileMovementInfo(payload);
                } else {
                    this.showFileMovementError('File not found');
                }
            },
            error: (xhr, status, error) => {
                console.error('API error:', xhr, status, error);
                this.showFileMovementError('Error looking up file information');
            }
        });
    };

    QuickActions.displayFileMovementInfo = function (tracker) {
        console.log('Displaying tracker info:', tracker);
        const infoDiv = $('#modal-movement-file-info');
        infoDiv.removeClass('hidden');

        const fileName = tracker.file_title || tracker.fileTitle || tracker.fileName || tracker.file_name || tracker.FileName || '—';
        const fileNumber = tracker.file_number || tracker.fileNumber || tracker.fileNo || tracker.file_no || tracker.st_file_no || tracker.NewKANGISFileNo || tracker.kangisFileNo || tracker.mlsfNo || '—';
        const currentOffice = tracker.current_office_name || tracker.currentOffice || tracker.receivingOfficeName || tracker.receiving_office_name || tracker.location || tracker.current_location || '—';
        const currentOfficer = tracker.receiving_officer_name || tracker.receivingOfficerName || tracker.currentOfficer || tracker.current_officer || tracker.current_handler || '—';

        console.log('Setting values:', { fileName, fileNumber, currentOffice, currentOfficer });

        $('#modal-current-file-name').text(fileName);
        $('#modal-current-file-number').text(fileNumber);
        $('#modal-current-office').text(currentOffice);
        $('#modal-current-officer').text(currentOfficer);
    };

    QuickActions.showFileMovementError = function (message) {
        console.log('Showing error:', message);
        const infoDiv = $('#modal-movement-file-info');
        infoDiv.removeClass('hidden');

        $('#modal-current-file-name').text(message);
        $('#modal-current-file-number').text('—');
        $('#modal-current-office').text('—');
        $('#modal-current-officer').text('—');
    };

    QuickActions.clearFileMovementInfo = function () {
        $('#modal-movement-file-info').addClass('hidden');
    };

    // ── Movement Timeline functions ported from quick_search.blade.php
    QuickActions.toggleMovementTimeline = async function (fileNumber, detailsEl, originRegistry, rackShelf) {
        const container = detailsEl.querySelector('.modal-movement-timeline');
        if (!container || !detailsEl) return;
        if (container.dataset.loaded === '1') return;

        const esc = QuickActions.escapeHtml;
        const TRACK_URL = '/api/file-trackers/track';

        container.innerHTML = `<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">Loading movement history for <strong>${esc(fileNumber)}</strong>…</div>`;

        // A file commissioned through KLAES has not reached the archive yet — its DIIT
        // "File Commissioning" line opens the timeline instead of this row. Set from the
        // response below (is_commissioned).
        let isCommissionedFile = false;

        const archiveHomeRowHtml = `
            <div class="relative pl-6 pb-3 text-left">
                <span class="absolute left-[6px] top-0 bottom-0 w-0.5 bg-gray-200"></span>
                <span class="absolute left-0 top-px h-[15px] w-[15px] rounded-full bg-emerald-500 border-2 border-gray-200 flex items-center justify-center">
                    <i data-lucide="archive" class="h-2 w-2 text-white"></i>
                </span>
                <div class="flex items-start justify-between gap-2 flex-wrap">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Archive / Registry</div>
                        <div class="text-[13px] font-bold text-gray-900">${esc(originRegistry || 'Registry / Archive')}${rackShelf ? ` — Shelf/Rack ${esc(rackShelf)}` : ''}</div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap">In Archive</span>
                </div>
            </div>`;

        const homeRow = () => (isCommissionedFile ? '' : archiveHomeRowHtml);

        try {
            const res  = await fetch(`${TRACK_URL}/${encodeURIComponent(fileNumber)}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => null);
            isCommissionedFile = Boolean(json && json.data && json.data.is_commissioned);
            // A 404 just means the file has never been tracked — that is an empty
            // history, not a failure.
            const neverTracked = res.status === 404 || (json && json.success === false && /not found/i.test(String(json.message || '')));
            if (neverTracked) {
                container.innerHTML = `${homeRow()}<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">No movement history available for this file.</div>`;
            } else if (!json || !json.success || !json.data) {
                container.innerHTML = `${homeRow()}<div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-600">Could not load movement history.</div>`;
            } else {
                const currentLogs = Array.isArray(json.data.movement_history) ? json.data.movement_history : [];
                const priorLogs   = Array.isArray(json.data.prior_movements) ? json.data.prior_movements : [];
                const allLogs     = [...priorLogs, ...currentLogs].sort(QuickActions.compareMovementEntries);
                if (!allLogs.length) {
                    container.innerHTML = `${homeRow()}<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">No movement history available for this file.</div>`;
                } else {
                    const approvalPurposes = ['recommendation', 'approval'];
                    const movementLogs = allLogs.filter(e => !approvalPurposes.includes(String(e.purpose || '').toLowerCase()));
                    const approvalLogs = allLogs.filter(e => approvalPurposes.includes(String(e.purpose || '').toLowerCase()));
                    const trackerMeta = {
                        // id -> photo URL for every officer named on the log, resolved
                        // server-side in one primed query (see FileTrackerApiController::track).
                        officerPhotos: json.data.officer_photos || {},
                        requestPurposeName: json.data.request_purpose_name || '',
                        timelineStatus: json.data.timeline_status || null,
                        daysUntilDeadline: (json.data.days_until_deadline === null || json.data.days_until_deadline === undefined) ? null : Number(json.data.days_until_deadline),
                        expectedReturnDate: json.data.deadline || null,
                    };
                    container.innerHTML = `
                        ${priorLogs.length ? `<div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[11px] text-gray-500">Tracking cycles for this file.</div>` : ''}
                        <div>
                            ${homeRow()}
                            ${movementLogs.length ? movementLogs.map(e => QuickActions.renderMovementRow(e, trackerMeta)).join('') : `<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">No physical movement entries found. Approval steps may still be present below.</div>`}
                        </div>
                        ${approvalLogs.length ? `
                        <div class="mt-4 mb-2 text-[10px] font-extrabold uppercase tracking-wider text-gray-500">Workflow Approvals</div>
                        <div class="space-y-2">${approvalLogs.map(e => QuickActions.renderApprovalRow(e)).join('')}</div>` : ''}`;
                    container.dataset.loaded = '1';
                }
            }
        } catch (e) {
            container.innerHTML = `<div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-600">Error loading movement history: ${esc(e.message)}</div>`;
        }
        if (window.lucide) window.lucide.createIcons();
    };

    QuickActions.compareMovementEntries = function (a, b) {
        return QuickActions.movementEntryTimestamp(a) - QuickActions.movementEntryTimestamp(b);
    };

    QuickActions.movementEntryTimestamp = function (entry) {
        const parseDateTime = (date, time) => {
            const d = (date || '').toString().trim();
            if (!d) return null;
            const t = (time || '').toString().trim() || '00:00';
            const parsed = Date.parse(`${d} ${t}`);
            return Number.isNaN(parsed) ? null : parsed;
        };
        const inTs = parseDateTime(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
        if (inTs !== null) return inTs;
        const outTs = parseDateTime(entry.log_out_date || entry.logOutDate, entry.log_out_time || entry.logOutTime);
        if (outTs !== null) return outTs;
        const createdTs = Date.parse(entry.created_at || entry.createdAt || '');
        return Number.isNaN(createdTs) ? Number.POSITIVE_INFINITY : createdTs;
    };

    QuickActions.mtToAmPm = function (timeStr) {
        if (!timeStr) return '';
        const parts = timeStr.toString().trim().split(':');
        if (parts.length < 2) return timeStr;
        let h = parseInt(parts[0], 10);
        const m = parts[1].padStart(2, '0');
        if (isNaN(h)) return timeStr;
        const period = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${m} ${period}`;
    };

    QuickActions.formatMovementDate = function (date, time) {
        const esc = QuickActions.escapeHtml;
        const d = (date || '').toString().trim();
        if (!d) return '—';
        if (!time) return esc(d);
        return `${esc(d)} ${esc(QuickActions.mtToAmPm(time))}`;
    };

    QuickActions.resolveMovementStatus = function (entry) {
        const rawOverride = (entry.status_label || entry.statusLabel || entry.new_status || entry.newStatus || '').toString().trim();
        const rawStatus = (entry.status || '').toString().trim().toLowerCase();
        const normalize = (value) => value.toLowerCase().replace(/_/g, ' ');

        if (rawOverride) {
            switch (normalize(rawOverride)) {
                case 'log-in':
                case 'log in':
                    return { label: 'Log-in', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
                case 'log-out':
                case 'log out':
                    return { label: 'Log-out', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
                case 'pending acceptance':
                case 'in-transit':
                case 'in transit':
                    return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
                case 'rejected':
                    return { label: 'Rejected', style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
                case 'cancelled':
                case 'canceled':
                    return { label: 'Cancelled', style: 'background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;' };
                default:
                    return { label: rawOverride, style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
            }
        }
        switch (rawStatus) {
            case 'pending_acceptance':
                return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
            case 'active':
                return { label: 'Log-out', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
            case 'completed':
                return { label: 'Log-in', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
            case 'rejected':
                return { label: 'Rejected', style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
            default:
                return { label: String(entry.status || 'Completed').replace(/_/g, ' '), style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
        }
    };

    QuickActions.formatTimelineMeta = function (meta) {
        const byStatus = {
            green:   { color: '#166534', bg: '#d1fae5', border: '#a7f3d0' },
            amber:   { color: '#78350f', bg: '#fef9c3', border: '#fde68a' },
            red:     { color: '#b91c1c', bg: '#fee2e2', border: '#fecaca' },
            pending: { color: '#475569', bg: '#e2e8f0', border: '#cbd5e1' },
        };
        if (!meta || !meta.timelineStatus || !byStatus[meta.timelineStatus]) return null;
        if (meta.timelineStatus === 'pending') {
            return { label: 'Pending', icon: 'fa-clock', ...byStatus.pending };
        }
        const days = meta.daysUntilDeadline;
        let label;
        if (days === null || days === undefined) {
            label = { green: 'On Track', amber: 'Due Soon', red: 'Overdue' }[meta.timelineStatus];
        } else if (days > 0) {
            label = `${days} day${days === 1 ? '' : 's'} left`;
        } else if (days === 0) {
            label = 'Due today';
        } else {
            const abs = Math.abs(days);
            label = `${abs} day${abs === 1 ? '' : 's'} overdue`;
        }
        return { label, ...byStatus[meta.timelineStatus] };
    };

    QuickActions.formatExpectedReturnDate = function (value) {
        const esc = QuickActions.escapeHtml;
        if (!value) return '—';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return esc(String(value).slice(0, 10));
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        return `${dd}/${mm}/${d.getFullYear()}`;
    };

    QuickActions.renderMovementRow = function (entry, trackerMeta) {
        const esc = QuickActions.escapeHtml;
        const office = esc(entry.office_name || entry.office || entry.receiving_office_name || 'Unknown');
        const status = QuickActions.resolveMovementStatus(entry);
        const statusLower = (status.label || '').trim().toLowerCase();
        const isCompletedStatus = statusLower === 'completed' || statusLower === 'complete' || (entry.status || '').toString().trim().toLowerCase() === 'completed';
        const officerNameRaw = isCompletedStatus
            ? 'Archive'
            : (entry.receiving_officer_name || entry.receivingOfficerName || entry.accepted_by_name || '-');
        const officer = esc(officerNameRaw);
        // A completed row reads "Archive" — a place, not the officer who handled it — so it
        // gets no face even when the entry still carries an officer id.
        const officerPhoto = (!isCompletedStatus && trackerMeta && trackerMeta.officerPhotos)
            ? (trackerMeta.officerPhotos[String(entry.receiving_officer_id ?? entry.receivingOfficerId ?? '')] || null)
            : null;
        const officerHtml = window.UserAvatar
            ? window.UserAvatar.withName(officerPhoto, officerNameRaw, 24)
            : officer;
        const hasLogIn = status.label === 'Log-in' || status.label === 'Completed';
        const inDate = hasLogIn
            ? QuickActions.formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime)
            : '-';
        const outDateRaw = entry.log_out_date || entry.logOutDate;
        const outTimeRaw = entry.log_out_time || entry.logOutTime;
        const outDate = outDateRaw
            ? QuickActions.formatMovementDate(outDateRaw, outTimeRaw)
            : ['active', 'pending_acceptance', 'in-transit', 'in transit'].includes((entry.status || '').toString().trim().toLowerCase())
                ? 'In transit' : '-';
        const notes = entry.notes ? `<div class="mt-2 text-xs text-gray-500">${esc(entry.notes)}</div>` : '';
        const timelineMeta = QuickActions.formatTimelineMeta(trackerMeta);
        const requestPurposeName = trackerMeta && trackerMeta.requestPurposeName ? esc(trackerMeta.requestPurposeName) : '—';
        const expectedReturnDate = QuickActions.formatExpectedReturnDate(trackerMeta && trackerMeta.expectedReturnDate);
        const delayReason = entry.delay_reason ? esc(entry.delay_reason) : '—';

        return `
            <div class="relative pl-6 pb-4 text-left">
                <span class="absolute left-[6px] top-0 bottom-0 w-0.5 bg-gray-200"></span>
                <span class="absolute left-0 top-px h-[15px] w-[15px] rounded-full bg-indigo-600 border-2 border-gray-200 flex items-center justify-center">
                    <i data-lucide="truck" class="h-2 w-2 text-white"></i>
                </span>
                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Office</div>
                            <div class="text-[13px] font-bold text-gray-900">${office}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap" style="${status.style}">${esc(status.label)}</span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                        <!-- Officer photo per log row. Reuses the same UserAvatar the
                             "Receiving Officer (holder)" line below the timeline uses, so one
                             face renders identically in both places; it falls back to initials
                             for rows that name a place ("Archive") rather than a person, and
                             for officers with no photo on file. -->
                        <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Receiving Officer</div><div class="text-gray-800 font-semibold">${officerHtml}</div></div>
                        <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Timeline</div><div>${timelineMeta ? `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold" style="background:${timelineMeta.bg};color:${timelineMeta.color};border:1px solid ${timelineMeta.border};">${timelineMeta.icon ? `<i class="fas ${timelineMeta.icon}"></i>` : ''}${esc(timelineMeta.label)}</span>` : '<span class="text-gray-400">—</span>'}</div></div>
                        <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Log In</div><div class="text-gray-800 font-semibold">${inDate}</div></div>
                        <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Log Out</div><div class="text-gray-800 font-semibold">${outDate}</div></div>
                        <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Request Purpose</div><div class="text-gray-800 font-semibold">${requestPurposeName}</div></div>
                        <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Expected Return</div><div class="text-gray-800 font-semibold">${expectedReturnDate}</div></div>
                        <div class="col-span-2"><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Delay Reason</div><div class="text-gray-800 font-semibold">${delayReason}</div></div>
                    </div>
                    ${notes}
                </div>
            </div>`;
    };

    QuickActions.renderApprovalRow = function (entry) {
        const esc = QuickActions.escapeHtml;
        const office = esc(entry.office_name || entry.office || entry.receiving_office_name || 'Unknown');
        const officer = esc(entry.receiving_officer_name || entry.accepted_by_name || '—');
        const eventDate = QuickActions.formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
        const purposeLabel = String(entry.purpose || '').toLowerCase() === 'recommendation' ? 'Recommendation' : 'Approval';
        const notes = entry.notes ? `<div class="mt-2 text-xs text-gray-500">${esc(entry.notes)}</div>` : '';
        return `
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-left">
                <div class="flex items-start justify-between gap-2 flex-wrap">
                    <div>
                        <div class="text-xs font-bold text-gray-800">${purposeLabel}</div>
                        <div class="mt-1 text-xs text-gray-500">${eventDate}</div>
                    </div>
                    <div class="text-right text-xs">
                        <div class="font-semibold text-gray-800">${office}</div>
                        <div class="text-gray-500">${officer}</div>
                    </div>
                </div>
                ${notes}
            </div>`;
    };

    window.QuickActions = QuickActions;

    // Custom styles for forms
    $('head').append(`
        <style>
        .form-input {
            @apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500;
        }
        .btn-primary {
            @apply inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500;
        }
        .btn-secondary {
            @apply inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500;
        }
        </style>
    `);
</script>







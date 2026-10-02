/**
 * Global File Number Modal Plugin
 * A comprehensive jQuery plugin for file number input across different systems
 * Supports MLS, KANGIS, and New KANGIS formats with smart selectors and validation
 */
 
(function ($) {
    'use strict';

    // Global configuration
    const CONFIG = {
        STORAGE_KEY: 'globalFileno',
        RECENT_LIMIT: 5,
        API_ENDPOINTS: {
            MLS_FILES: '/api/file-numbers/mls',
            KANGIS_FILES: '/api/file-numbers/kangis',
            NEWKANGIS_FILES: '/api/file-numbers/newkangis',
            LOOKUP: '/api/file-numbers/lookup'
        }
    };

    // Default configuration applied on every modal open to avoid leaking state between pages
    const DEFAULT_CONFIG = {
        currentTab: 'mls',
        targetFields: [],
        callback: null,
        initialValue: null,
        initialTab: null,
        autoPopulateGenericFields: true,
        excludePrefixes: []  // Array of uppercase file-number prefixes to hide (e.g. ['CON'])
    };

    // Main GlobalFileNoModal object
    window.GlobalFileNoModal = {
        // Configuration
        config: $.extend(true, {}, DEFAULT_CONFIG),

        // Cache for loaded data
        cache: {
            mlsFiles: [],
            kangisFiles: [],
            newkangisFiles: []
        },

        // Initialize the modal
        init: function () {
            this.bindEvents();
            this.loadRecentSelections();

            // Force all tab content to hidden first
            $('.fileno-tab-content').addClass('hidden').css('display', 'none');

            // Remember the default tab without triggering data loads while hidden
            this.config.currentTab = DEFAULT_CONFIG.currentTab;
            console.log('GlobalFileNoModal initialized');
        },

        // Open modal with options
        open: function (options = {}) {
            const modal = $('#global-fileno-modal');
            if (modal.length === 0) {
                console.error('Global file number modal not found in DOM');
                return false;
            }

            // Reset configuration each time to prevent stale callbacks/targets from previous opens
            this.config = $.extend(true, {}, DEFAULT_CONFIG, options);

            // If caller wants to exclude prefixes, force a fresh Select2 population so
            // the filter is always applied, even if the cache was primed by a previous open.
            if (this.config.excludePrefixes && this.config.excludePrefixes.length > 0) {
                ['mls', 'kangis', 'newkangis'].forEach(function (tab) {
                    $('#' + tab + '-smart-selector').removeData('select2-initialized');
                });
            }

            // Set initial tab
            const initialTab = this.config.initialTab || DEFAULT_CONFIG.currentTab;

            // Show modal
            modal.removeClass('hidden').addClass('flex');

            // Ensure proper display and z-index
            modal.css({
                'display': 'flex',
                'position': 'fixed',
                'z-index': '9999'
            });

            modal.focus();

            // Activate the requested tab now that the modal is visible so Select2 can size correctly
            this.switchTab(initialTab);

            // Lazily initialise smart selectors now that the modal is mounted and visible
            this.initializeSmartSelectors(true);

            // Pre-populate if initial value provided
            if (this.config.initialValue) {
                this.populateFromValue(this.config.initialValue);
            }

            // Emit open event
            $(document).trigger('fileno-modal:opened', [this.config]);

            console.log('Modal opened successfully');
            return true;
        },

        // Close modal
        close: function () {
            const modal = $('#global-fileno-modal');

            // Ensure any Select2 dropdowns are closed so the search UI doesn't linger on the page
            modal.find('select').each(function () {
                const $select = $(this);
                const instance = $select.data('select2');
                if (instance) {
                    $select.select2('close');
                }
            });

            modal.addClass('hidden').removeClass('flex');

            // Ensure proper hiding
            modal.css('display', 'none');

            this.resetForm();
            $(document).trigger('fileno-modal:closed');
            console.log('Modal closed successfully');
        },

        // Switch between tabs
        switchTab: function (tabName) {
            if (!tabName) return;
            try { console.log('[GlobalFileNoModal] switchTab start', { tabName }); } catch (e) { }

            // Update active tab button
            $('.fileno-tab-btn').removeClass('bg-white text-blue-600 shadow-sm').addClass('text-gray-700 hover:text-gray-900');
            const $activeTab = $(`.fileno-tab-btn[data-tab="${tabName}"]`);
            $activeTab.removeClass('text-gray-700 hover:text-gray-900').addClass('bg-white text-blue-600 shadow-sm');

            // Show/hide tab content
            $('.fileno-tab-content').addClass('hidden').css('display', 'none');
            const $activeContent = $(`.fileno-tab-content[data-tab="${tabName}"]`);
            $activeContent.removeClass('hidden');
            // Force display update in case Tailwind hidden isn't refreshing
            $activeContent.css('display', 'block');

            if ($activeContent.length === 0) {
                console.error('Tab content not found for: ' + tabName);
            } else {
                console.log('Found tab content, setting visible for: ' + tabName);
            }

            // Update current tab
            this.config.currentTab = tabName;

            // Load data for the tab if needed
            this.loadTabData(tabName);

            // Update preview
            this.updatePreview();

            // Initialize the input method UI for this tab
            this.initializeTabUI(tabName);
            try { console.log('[GlobalFileNoModal] switchTab complete', { tabName }); } catch (e) { }
        },

        // Initialize tab-specific UI elements
        initializeTabUI: function (tabName) {
            // Set up radio button styling
            const checkedRadio = $(`input[name="${tabName}-input-method"]:checked`);
            if (checkedRadio.length > 0) {
                const $label = checkedRadio.closest('label');
                const $allLabels = $(`input[name="${tabName}-input-method"]`).closest('label');

                $allLabels.removeClass('bg-white shadow-md').addClass('hover:bg-white/50');
                $label.removeClass('hover:bg-white/50').addClass('bg-white shadow-md');

                // Show appropriate input section
                const method = checkedRadio.val();
                $(`.${tabName}-input-section`).addClass('hidden');
                $(`.${tabName}-input-section[data-method="${method}"]`).removeClass('hidden');
            }
        },

        // Load data for specific tab
        loadTabData: function (tabName) {
            const loadingId = `${tabName}-loading`;
            const selector = $(`#${tabName}-smart-selector`);

            // Always load data for smart selectors, even if not currently active
            // Check if already loaded and initialized
            const cacheKey = `${tabName}Files`;
            if (this.cache[cacheKey] && this.cache[cacheKey].length > 0 && selector.data('select2-initialized')) {
                return;
            }

            // If we have cached data but selector not initialized, initialize it
            if (this.cache[cacheKey] && this.cache[cacheKey].length > 0) {
                this.populateSmartSelector(tabName, this.cache[cacheKey]);
                return;
            }

            // Show loading
            $(`#${loadingId}`).removeClass('hidden');

            // Load initial data from API
            const endpoint = CONFIG.API_ENDPOINTS[`${tabName.toUpperCase()}_FILES`];

            $.ajax({
                url: endpoint,
                method: 'GET',
                data: {
                    limit: 20, // Get first 20 records for initial display
                    initial: true,
                    exclude_matched: this.config.exclude_matched || ''
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            })
                .done((response) => {
                    if (response.success && Array.isArray(response.files)) {
                        this.cache[cacheKey] = response.files;
                        this.populateSmartSelector(tabName, response.files);
                    } else {
                        console.warn(`API returned invalid data for ${tabName}. Response:`, response);
                        this.showError(`Failed loading ${tabName.toUpperCase()} data`);
                    }
                })
                .fail((xhr, status, error) => {
                    console.warn(`API request failed for ${tabName}:`, { status, error, response: xhr?.responseText });
                    this.showError(`API error loading ${tabName.toUpperCase()} data`);
                })
                .always(() => {
                    $(`#${loadingId}`).addClass('hidden');
                });
        },

        // Populate smart selector with data
        populateSmartSelector: function (tabName, files) {
            const selector = $(`#${tabName}-smart-selector`);

            // Clear existing options except the first placeholder
            selector.find('option:not(:first)').remove();

            // Apply caller-requested prefix exclusions (e.g. hide CON-* in OSS FEFR context)
            const _excludePfx = (this.config.excludePrefixes || []).map(p => p.toUpperCase());
            const filteredFiles = _excludePfx.length > 0
                ? files.filter(file => {
                    const fn = (this.extractFileNumber(file, tabName) || '').toUpperCase();
                    return !_excludePfx.some(prefix => fn.startsWith(prefix));
                })
                : files;

            // Add initial batch of files (at least 10)
            const initialFiles = filteredFiles.slice(0, Math.max(10, filteredFiles.length));
            initialFiles.forEach(file => {
                const fileNumber = this.extractFileNumber(file, tabName);
                if (fileNumber) {
                    const option = $('<option>', {
                        value: JSON.stringify(file),
                        text: fileNumber,
                        'data-fileno': fileNumber
                    });
                    selector.append(option);
                }
            });

            // Initialize Select2 with pre-loaded options and search capability
            if (typeof $ !== 'undefined' && $.fn.select2) {
                // Destroy existing Select2 if it exists
                if (selector.data('select2')) {
                    selector.select2('destroy');
                }

                // Initialize Select2 with custom configuration
                selector.select2({
                    placeholder: `Select or search ${tabName.toUpperCase()} file number...`,
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#global-fileno-modal'),
                    minimumInputLength: 0, // Allow dropdown to open without typing
                    ajax: {
                        delay: 250,
                        transport: function (params, success, failure) {
                            // If no search term, return the pre-loaded options
                            if (!params.data.term || params.data.term.length === 0) {
                                const results = [];
                                selector.find('option:not(:first)').each(function () {
                                    results.push({
                                        id: $(this).val(),
                                        text: $(this).text(),
                                        fileNumber: $(this).data('fileno')
                                    });
                                });
                                success({
                                    results: results.slice(0, 15) // Show first 15 items
                                });
                                return;
                            }

                            // For actual searches, make API call
                            const endpoint = CONFIG.API_ENDPOINTS[`${tabName.toUpperCase()}_FILES`];

                            $.ajax({
                                url: endpoint,
                                method: 'GET',
                                data: {
                                    search: params.data.term,
                                    limit: 20,
                                    exclude_matched: GlobalFileNoModal.config.exclude_matched || ''
                                },
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                                    'Content-Type': 'application/json'
                                }
                            })
                                .done(function (response) {
                                    if (response.success && Array.isArray(response.files)) {
                                        let results = response.files.map(file => {
                                            const fileNumber = GlobalFileNoModal.extractFileNumber(file, tabName);
                                            return {
                                                id: JSON.stringify(file),
                                                text: fileNumber,
                                                fileNumber: fileNumber
                                            };
                                        });
                                        // Apply prefix exclusions for contexts like OSS FEFR
                                        const _excludePfxSearch = (GlobalFileNoModal.config.excludePrefixes || []).map(p => p.toUpperCase());
                                        if (_excludePfxSearch.length > 0) {
                                            results = results.filter(r => !_excludePfxSearch.some(p => (r.text || '').toUpperCase().startsWith(p)));
                                        }
                                        success({ results: results });
                                    } else {
                                        failure();
                                    }
                                })
                                .fail(function () {
                                    failure();
                                });
                        }
                    },
                    templateResult: function (data) {
                        if (data.loading) {
                            return data.text;
                        }

                        // Custom formatting for file numbers
                        const $result = $(`
                            <div class="select2-file-result">
                                <div class="file-number">${data.text || data.fileNumber}</div>
                            </div>
                        `);
                        return $result;
                    },
                    templateSelection: function (data) {
                        return data.text || data.fileNumber || data.id;
                    }
                });

                // Mark as initialized
                selector.data('select2-initialized', true);

                console.log(`Select2 initialized for ${tabName} with ${initialFiles.length} pre-loaded options`);
            }
        },

        // Extract file number from file object based on tab
        extractFileNumber: function (file, tabName) {
            switch (tabName) {
                case 'mls':
                    // Try multiple field name variations
                    const mlsNum = file.mlsFNo || file.file_number || file.mls_file_no || 
                                  file.mlsf_no || file.mlsFNumber || file.mls_number ||
                                  file.fileNumber || file.fileno;
                    if (mlsNum) {
                        console.log('[GlobalFileNoModal] Extracted MLS file number:', mlsNum);
                    }
                    return mlsNum;
                case 'kangis':
                    return file.kangisFileNo || file.kangis_file_no || file.kangis_number || 
                           file.kangisFNo || file.kangis_number;
                case 'newkangis':
                    return file.NewKANGISFileNo || file.new_kangis_file_no || 
                           file.newkangis_file_no || file.new_kangis_number;
                default:
                    return file.file_number || file.fileNumber || '';
            }
        },

        // Update preview based on current tab and values with enhanced styling
        updatePreview: function () {
            const tabName = this.config.currentTab;
            let preview = '';
            let fileData = null; // Store file details if a smart selection was made

            // Handle No Official File Number toggle for MLS
            if (tabName === 'mls' && $('#modal-no-file-number-toggle').is(':checked')) {
                preview = $('#modal-temp-value').text();
                if (preview === 'Pending...' || preview === 'Generating...') {
                    preview = '';
                }
            } else {
                switch (tabName) {
                    case 'mls':
                        preview = this.generateMLSPreview();
                        break;
                    case 'kangis':
                        preview = this.generateKANGISPreview();
                        break;
                    case 'newkangis':
                        preview = this.generateNewKANGISPreview();
                        break;
                }

                // If the input method is smart, extract the selected JSON data
                const inputMethod = $(`input[name="${tabName}-input-method"]:checked`).val();
                if (inputMethod === 'smart') {
                    const selected = $(`#${tabName}-smart-selector`).val();
                    if (selected) {
                        try {
                            fileData = JSON.parse(selected);
                        } catch(e) {}
                    }
                }
            }

            // Update preview display with enhanced styling
            const $preview = $(`#${tabName}-preview`);
            const $detailsContainer = $(`#${tabName}-details-container`);
            if (preview) {
                const colorClass = tabName === 'mls' ? 'text-blue-900' :
                    tabName === 'kangis' ? 'text-green-900' : 'text-purple-900';
                $preview.html(`<span class="${colorClass} font-bold">${preview}</span>`);
                $preview.removeClass('text-gray-400');
                this.showSuccess('✓ File number ready');

                // Show UI card details if fileData is present
                if (fileData) {
                    const fileName = fileData.FileName || fileData.file_name || fileData.file_title || '—';
                    const lga = fileData.LGA || fileData.lga || fileData.fi_lga || fileData.ma_lga || '—';
                    const location = fileData.Location || fileData.location || fileData.ma_location || '—';
                    const landUse = fileData.LandUse || fileData.land_use || fileData.ma_land_use || '—';

                    const labelColor = tabName === 'mls' ? 'text-blue-600' :
                        tabName === 'kangis' ? 'text-green-600' : 'text-purple-600';
                    const borderColor = tabName === 'mls' ? 'border-blue-100' :
                        tabName === 'kangis' ? 'border-green-100' : 'border-purple-100';

                    const esc = (v) => $('<div>').text(v).html();
                    const detailsHtml = `
                    <div class="mt-3 bg-white border ${borderColor} rounded-lg p-4 shadow-sm">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider ${labelColor} mb-1">File Name</p>
                                <p class="text-sm font-medium text-gray-800 break-words">${esc(fileName)}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider ${labelColor} mb-1">LGA</p>
                                <p class="text-sm font-medium text-gray-800 break-words">${esc(lga)}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider ${labelColor} mb-1">Location</p>
                                <p class="text-sm font-medium text-gray-800 break-words">${esc(location)}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider ${labelColor} mb-1">Land Use</p>
                                <p class="text-sm font-medium text-gray-800 break-words">${esc(landUse)}</p>
                            </div>
                        </div>
                    </div>`;
                    
                    $detailsContainer.html(detailsHtml).removeClass('hidden');
                } else {
                    $detailsContainer.addClass('hidden').empty();
                }
            } else {
                $preview.html('<span class="text-gray-400 font-normal">-</span>');
                $detailsContainer.addClass('hidden').empty();
                $('#validation-message').text('').removeClass('text-green-600 bg-green-50 px-3 py-1 rounded-lg border border-green-200');
            }

            // Update copy button state with enhanced styling
            const copyBtn = $(`#${tabName}-copy-btn`);
            const applyBtn = $('#apply-fileno-btn');

            if (preview) {
                copyBtn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                applyBtn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
            } else {
                copyBtn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                applyBtn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
            }
        },

        // Generate MLS preview
        generateMLSPreview: function () {
            const inputMethod = $('input[name="mls-input-method"]:checked').val();

            if (inputMethod === 'smart') {
                const selected = $('#mls-smart-selector').val();
                if (selected) {
                    try {
                        const fileData = JSON.parse(selected);
                        return this.extractFileNumber(fileData, 'mls');
                    } catch (e) {
                        console.error('Error parsing selected MLS file:', e);
                        return '';
                    }
                }
                return '';
            }

            // Manual entry
            const fileType = $('#mls-file-type').val();

            switch (fileType) {
                case 'regular':
                case 'temporary': {
                    const prefix = $('#mls-prefix').val();
                    const year = $('#mls-year').val();
                    const serial = $('#mls-serial').val();

                    if (prefix && year && serial) {
                        const baseFileNo = `${prefix}-${year}-${serial}`;
                        return fileType === 'temporary' ? `${baseFileNo}(T)` : baseFileNo;
                    }
                    break;
                }
                case 'extension': {
                    const prefix = $('#mls-extension-prefix').val();
                    const year = $('#mls-extension-year').val();
                    const serial = $('#mls-extension-serial').val();

                    if (prefix && year && serial) {
                        return `${prefix}-${year}-${serial}-EXTENSION`;
                    }
                    break;
                }
                case 'miscellaneous': {
                    const middlePrefix = $('#mls-middle-prefix').val();
                    const serial = $('#mls-misc-serial').val();
                    if (middlePrefix && serial) {
                        return `MISC-${middlePrefix}-${serial}`;
                    }
                    break;
                }
                case 'sit': {
                    const year = $('#mls-sit-year').val();
                    const serial = $('#mls-sit-serial').val();
                    if (year && serial) {
                        const isTemp = $('#mls-sit-temporary').is(':checked');
                        return isTemp ? `SIT-${year}-${serial} (T)` : `SIT-${year}-${serial}`;
                    }
                    break;
                }
                case 'sltr': {
                    const serial = $('#mls-sltr-serial').val();
                    if (serial) {
                        return `SLTR-${serial}`;
                    }
                    break;
                }
                case 'old_mls': {
                    const serial = $('#mls-old-serial').val();
                    if (serial) {
                        return `KN ${serial}`;
                    }
                    break;
                }
                case 'klaes_temp_fileno': {
                    const prefix = $('#mls-klaes-temp-prefix').val() || 'TEMP';
                    const serial = $('#mls-klaes-temp-serial').val();
                    if (serial) {
                        const paddedSerial = serial.toString().padStart(5, '0');
                        return `${prefix}-${paddedSerial}`;
                    }
                    break;
                }
                case 'dciv':
                case 'lpcc':
                case 'lpcc_dciv': {
                    const prefix = $('#mls-dciv-lpcc-prefix').val();
                    const year = $('#mls-dciv-lpcc-year').val();
                    const serial = $('#mls-dciv-lpcc-serial').val();
                    if (prefix && year && serial) {
                        return `${prefix}-${year}-${serial}`;
                    }
                    break;
                }
                case 'gkn': {
                    const prefix = $('#mls-gkn-prefix').val() || 'GKN';
                    const serial = $('#mls-gkn-serial').val();
                    if (serial) {
                        const isTemp = $('#mls-gkn-temporary').is(':checked');
                        let fileNo;
                        if (prefix === 'MISC') {
                            fileNo = `MISC KN ${serial}`;
                        } else {
                            fileNo = `${prefix}-${serial}`;
                        }
                        return isTemp ? `${fileNo} (T)` : fileNo;
                    }
                    break;
                }
            }

            return '';
        },

        // Generate KANGIS preview
        generateKANGISPreview: function () {
            const inputMethod = $('input[name="kangis-input-method"]:checked').val();

            if (inputMethod === 'smart') {
                const selected = $('#kangis-smart-selector').val();
                if (selected) {
                    try {
                        const fileData = JSON.parse(selected);
                        return this.extractFileNumber(fileData, 'kangis');
                    } catch (e) {
                        return '';
                    }
                }
                return '';
            }

            // Manual entry - require both prefix and number
            const prefix = $('#kangis-prefix').val().trim();
            const number = $('#kangis-number').val().trim();

            if (prefix && number) {
                return `${prefix} ${number}`;
            }

            return '';
        },

        // Generate New KANGIS preview
        generateNewKANGISPreview: function () {
            const inputMethod = $('input[name="newkangis-input-method"]:checked').val();

            if (inputMethod === 'smart') {
                const selected = $('#newkangis-smart-selector').val();
                if (selected) {
                    try {
                        const fileData = JSON.parse(selected);
                        return this.extractFileNumber(fileData, 'newkangis');
                    } catch (e) {
                        return '';
                    }
                }
                return '';
            }

            // Manual entry - require both prefix and number
            const prefix = $('#newkangis-prefix').val().trim();
            const number = $('#newkangis-number').val().trim();

            if (prefix && number) {
                return `${prefix}${number}`;
            }

            return '';
        },

        // Apply the selected file number
        apply: async function () {
            const tabName = this.config.currentTab;
            
            // Get the raw file number from the source, not the preview
            let fileNumber = '';
            let isTempMode = false;

            // MLS temp mode: when no-official-file toggle is enabled, apply the generated TEMP value directly.
            if (tabName === 'mls' && $('#modal-no-file-number-toggle').is(':checked')) {
                const tempValue = ($('#modal-temp-value').text() || '').trim();
                if (tempValue && tempValue !== 'Pending...' && tempValue !== 'Generating...') {
                    fileNumber = tempValue;
                    isTempMode = true;
                }
            }
            
            // Determine input method for this tab
            const inputMethod = $(`input[name="${tabName}-input-method"]:checked`).val();
            
            if (!fileNumber && inputMethod === 'smart') {
                // For smart selectors, get the actual selected file data
                const selector = $(`#${tabName}-smart-selector`);
                const selected = selector.val();
                
                if (selected) {
                    try {
                        const fileData = JSON.parse(selected);
                        fileNumber = this.extractFileNumber(fileData, tabName);
                        console.log('[GlobalFileNoModal] Smart selector extracted file number:', fileNumber, 'from data:', fileData);
                    } catch (e) {
                        console.error('Error parsing selected file data:', e);
                        fileNumber = '';
                    }
                }
            } else if (!fileNumber && inputMethod === 'manual') {
                // For manual entry, get the preview (which is already the file number)
                const previewElement = $(`#${tabName}-preview span`).length ? $(`#${tabName}-preview span`) : $(`#${tabName}-preview`);
                fileNumber = (previewElement.text() || '').trim();
            }
            
            console.log('[GlobalFileNoModal] apply() called', { tabName, inputMethod, fileNumber });

            if (!fileNumber || fileNumber === '' || fileNumber === '-') {
                console.warn('[GlobalFileNoModal] No file number, aborting apply');
                return;
            }

            const applyBtn = $('#apply-fileno-btn');
            const originalLabel = applyBtn.text();
            applyBtn.prop('disabled', true).text('Applying...');

            let record = null;
            let fetchFailed = false;

            try {
                try {
                    record = await this.fetchFileDetails(fileNumber, tabName);
                } catch (error) {
                    fetchFailed = true;
                    console.error('Failed to fetch file number details', error);
                }

                // Smart selector should only apply records that are still resolvable from source APIs.
                // This blocks stale local "recent selections" that no longer exist in backing tables.
                // Exception: TEMP file numbers (no-official-file toggle) are not in the API by design.
                if (!isTempMode && inputMethod === 'smart' && !record) {
                    this.showError('Selected file number could not be resolved. Please choose a valid file from the search results.');
                    return;
                }

                this.addToRecentSelections(tabName, fileNumber);
                this.populateTargetFields(fileNumber, tabName);

                const trackingId = record?.tracking_id || record?.trackingId || record?.tra || '';
                const fileTitle = record?.file_title || record?.file_name || '';

                if (this.config.callback && typeof this.config.callback === 'function') {
                    console.log('[GlobalFileNoModal] Calling callback with fileNumber:', fileNumber);
                    this.config.callback({
                        fileNumber: fileNumber,
                        tab: tabName,
                        system: tabName.toUpperCase(),
                        tracking_id: trackingId,
                        trackingId: trackingId,
                        tra: trackingId,
                        file_title: fileTitle,
                        file_name: fileTitle,
                        record
                    });
                } else {
                    console.warn('[GlobalFileNoModal] No callback configured or callback is not a function');
                }

                $(document).trigger('fileno-modal:applied', [{
                    fileNumber: fileNumber,
                    tab: tabName,
                    system: tabName.toUpperCase(),
                    tracking_id: trackingId,
                    trackingId: trackingId,
                    tra: trackingId,
                    file_title: fileTitle,
                    file_name: fileTitle,
                    record
                }]);

                if (fetchFailed) {
                    this.showError('File number applied, but details could not be fetched.');
                } else {
                    this.showSuccess(`${tabName.toUpperCase()} file number applied successfully`);
                }

                setTimeout(() => {
                    this.close();
                }, 1000);
            } finally {
                applyBtn.prop('disabled', false).text(originalLabel);
            }
        },

        // Populate target form fields
        populateTargetFields: function (fileNumber, tabName) {
            const fieldNames = ['fileno', 'file_no', 'file_number', 'filenumber'];
            const specificFieldNames = {
                'mls': ['mlsFNo', 'mls_file_no'],
                'kangis': ['kangisFileNo', 'kangis_file_no'],
                'newkangis': ['NewKANGISFileno', 'new_kangis_file_no']
            };

            // Populate generic file number fields (only if enabled)
            if (this.config.autoPopulateGenericFields !== false) {
                fieldNames.forEach(fieldName => {
                    const field = $(`[name="${fieldName}"]`);
                    if (field.length > 0) {
                        field.val(fileNumber).trigger('change');
                    }
                });
            }

            // Populate system-specific fields
            if (specificFieldNames[tabName]) {
                specificFieldNames[tabName].forEach(fieldName => {
                    const field = $(`[name="${fieldName}"]`);
                    if (field.length > 0) {
                        field.val(fileNumber).trigger('change');
                    }
                });
            }

            // If specific target fields were provided
            if (this.config.targetFields && this.config.targetFields.length > 0) {
                this.config.targetFields.forEach(fieldSelector => {
                    const field = $(fieldSelector);
                    if (field.length > 0) {
                        field.val(fileNumber).trigger('change');
                    }
                });
            }
        },

        fetchFileDetails: async function (fileNumber, tabName) {
            if (!fileNumber) {
                return null;
            }

            const params = { file_number: fileNumber };

            switch (tabName) {
                case 'mls':
                    params.mlsf_no = fileNumber;
                    break;
                case 'kangis':
                    params.kangis_file_no = fileNumber;
                    break;
                case 'newkangis':
                    params.new_kangis_file_no = fileNumber;
                    break;
                default:
                    break;
            }

            try {
                const response = await $.ajax({
                    url: CONFIG.API_ENDPOINTS.LOOKUP,
                    method: 'GET',
                    data: params,
                    timeout: 5000,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (response?.success && response.data) {
                    return response.data;
                }

                return null;
            } catch (error) {
                if (error?.status === 404) {
                    return null;
                }

                throw error;
            }
        },

        // Copy to clipboard functionality
        copyToClipboard: function (tabName) {
            const preview = $(`#${tabName}-preview`).text();
            if (preview && preview !== 'No file number generated') {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(preview).then(() => {
                        this.showSuccess('Copied to clipboard!');
                    }).catch(() => {
                        this.fallbackCopy(preview);
                    });
                } else {
                    this.fallbackCopy(preview);
                }
            }
        },

        // Fallback copy method
        fallbackCopy: function (text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showSuccess('Copied to clipboard!');
        },

        // Add to recent selections
        addToRecentSelections: function (tabName, fileNumber) {
            const storageKey = `${CONFIG.STORAGE_KEY}_recent_${tabName}`;
            let recent = JSON.parse(localStorage.getItem(storageKey) || '[]');

            // Remove if already exists
            recent = recent.filter(item => item !== fileNumber);

            // Add to beginning
            recent.unshift(fileNumber);

            // Limit to recent items
            recent = recent.slice(0, CONFIG.RECENT_LIMIT);

            // Save to localStorage
            localStorage.setItem(storageKey, JSON.stringify(recent));

            // Update display
            this.displayRecentSelections(tabName, recent);
        },

        // Load recent selections from localStorage
        loadRecentSelections: function () {
            ['mls', 'kangis', 'newkangis'].forEach(tabName => {
                const storageKey = `${CONFIG.STORAGE_KEY}_recent_${tabName}`;
                const recent = JSON.parse(localStorage.getItem(storageKey) || '[]');
                this.displayRecentSelections(tabName, recent);
            });
        },

        // Display recent selections
        displayRecentSelections: function (tabName, recent) {
            const container = $(`#${tabName}-recent-selections`);
            container.empty();

            if (recent.length === 0) {
                container.append('<span class="text-sm text-gray-500">No recent selections</span>');
                return;
            }

            recent.forEach(item => {
                const badge = $(`
                    <button type="button" class="inline-flex items-center px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200 transition-colors recent-selection-btn"
                            data-fileno="${item}">
                        ${item}
                        <i data-lucide="x" class="w-3 h-3 ml-1 opacity-50 hover:opacity-100"></i>
                    </button>
                `);
                container.append(badge);
            });
        },

        // Reset form to initial state
        resetForm: function () {
            try {
                console.log("Resetting form...");

                // Reset all form inputs (excluding radio buttons, file inputs, and readonly fields)
                $('#global-fileno-modal input:not([type="radio"]):not([type="file"]):not([readonly]), #global-fileno-modal select').val('').trigger('change');

                // Reset radio buttons to smart selector and trigger the change
                $('input[name$="-input-method"][value="smart"]').prop('checked', true);

                // Force toggle input sections visibility for all tabs
                ['mls', 'kangis', 'newkangis'].forEach(tabName => {
                    $(`.${tabName}-input-section`).addClass('hidden');
                    $(`.${tabName}-input-section[data-method="smart"]`).removeClass('hidden');
                });

                // Reset file type to regular
                $('#mls-file-type').val('regular').trigger('change');

                // Reset previews to empty dash instead of "No file number generated"
                $('.fileno-tab-content [id$="-preview"]').html('<span class="text-gray-400 font-normal">-</span>');

                // Reset validation message
                $('#validation-message').text('');

                // Disable apply button
                $('#apply-fileno-btn').prop('disabled', true);

                // Disable copy buttons
                $('[id$="-copy-btn"]').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');

                // Show success message
                this.showSuccess('Form has been reset');

                // Don't switch tabs, stay on current tab
                const currentTab = this.config.currentTab;
                if (currentTab) {
                    // Reload tab data - this will refresh the smart selectors
                    this.cache[`${currentTab}Files`] = []; // Clear cache to force reload
                    this.loadTabData(currentTab);
                }

                console.log("Form reset complete");
            } catch (error) {
                console.error("Error during form reset:", error);
            }
        },

        // Show error message with enhanced styling
        showError: function (message) {
            $('#validation-message')
                .text(message)
                .removeClass('text-green-600 bg-green-50 border-green-200')
                .addClass('text-red-600 bg-red-50 px-3 py-1 rounded-lg border border-red-200');
        },

        // Show success message with enhanced styling
        showSuccess: function (message) {
            $('#validation-message')
                .text(message)
                .removeClass('text-red-600 bg-red-50 border-red-200')
                .addClass('text-green-600 bg-green-50 px-3 py-1 rounded-lg border border-green-200');
            setTimeout(() => {
                $('#validation-message').text('').removeClass('text-green-600 bg-green-50 px-3 py-1 rounded-lg border border-green-200');
            }, 3000);
        },

        // Initialize smart selectors (Select2 or similar)
        initializeSmartSelectors: function (force = false) {
            const modal = this;

            if (typeof $.fn.select2 === 'undefined') {
                console.warn('Select2 library not loaded; smart selectors will use native dropdowns');
                return;
            }

            const select2Config = {
                mls: {
                    placeholder: 'Search MLS file numbers…',
                    endpoint: CONFIG.API_ENDPOINTS.MLS_FILES
                },
                kangis: {
                    placeholder: 'Search KANGIS file numbers…',
                    endpoint: CONFIG.API_ENDPOINTS.KANGIS_FILES
                },
                newkangis: {
                    placeholder: 'Search New KANGIS file numbers…',
                    endpoint: CONFIG.API_ENDPOINTS.NEWKANGIS_FILES
                }
            };

            Object.entries(select2Config).forEach(([tabName, cfg]) => {
                const $element = $(`#${tabName}-smart-selector`);

                if (!$element.length) {
                    return;
                }

                const alreadyInitialized = Boolean($element.data('select2-initialized'));

                if (alreadyInitialized && force) {
                    if ($element.data('select2')) {
                        $element.select2('destroy');
                    }
                    $element.removeData('select2-initialized');
                } else if (alreadyInitialized && !force) {
                    return;
                }

                $element.select2({
                    width: '100%',
                    dropdownParent: $('#global-fileno-modal'),
                    placeholder: cfg.placeholder,
                    allowClear: true,
                    minimumInputLength: 0,
                    ajax: {
                        url: cfg.endpoint,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                search: params.term || '',
                                limit: 40,
                                exclude_matched: modal.config.exclude_matched || ''
                            };
                        },
                        processResults: function (data) {
                            const files = Array.isArray(data?.files) ? data.files : [];

                            const results = files.map(file => {
                                const fileNumber = modal.extractFileNumber(file, tabName);

                                if (!fileNumber) {
                                    return null;
                                }

                                return {
                                    id: JSON.stringify(file),
                                    text: fileNumber
                                };
                            }).filter(Boolean);

                            return {
                                results
                            };
                        },
                        cache: true
                    },
                    language: {
                        searching: function () {
                            return 'Searching…';
                        },
                        noResults: function () {
                            return 'No file numbers found';
                        },
                        inputTooShort: function () {
                            return 'Start typing to search file numbers';
                        }
                    }
                });

                $element.data('select2-initialized', true);

                $element.on('select2:open', function () {
                    const $dropdown = $('.select2-container--open .select2-search__field');
                    if ($dropdown.length) {
                        $dropdown.attr('placeholder', cfg.placeholder);
                        $dropdown.trigger('focus');
                    }
                });

                $element.on('select2:select select2:clear', function () {
                    modal.updatePreview();
                });
            });
        },

        // Populate form from existing value
        populateFromValue: function (value) {
            // Try to determine which tab this value belongs to
            let detectedTab = 'mls'; // default

            if (value.includes(' ') && !value.includes('-')) {
                detectedTab = 'kangis';
            } else if (value.includes('/') && !value.includes(' ')) {
                detectedTab = 'newkangis';
            }

            this.switchTab(detectedTab);

            // Set the appropriate fields based on detected tab
            // This is a simplified version - can be enhanced for better parsing
            if (detectedTab === 'kangis') {
                const parts = value.split(' ');
                if (parts.length >= 2) {
                    $('#kangis-prefix').val(parts.slice(0, -1).join(' '));
                    $('#kangis-number').val(parts[parts.length - 1]);
                }
            } else if (detectedTab === 'newkangis') {
                // New KANGIS prefix is fixed as KN; extract trailing digits as serial.
                const rawValue = String(value || '').trim();
                const serialMatch = rawValue.match(/(\d+)$/);
                $('#newkangis-prefix').val('KN');
                $('#newkangis-number').val(serialMatch ? serialMatch[1] : '');
            }

            // Update preview
            setTimeout(() => {
                this.updatePreview();
            }, 100);
        },

        // Bind all event handlers
        bindEvents: function () {
            const self = this;

            // Tab switching - REMOVED as now using direct onclick in HTML
            // Leaving as backup in case needed
            /*
            $(document).on('click', '.fileno-tab-btn', function(e) {
                e.preventDefault();
                const tabName = $(this).data('tab');
                console.log('[GlobalFileNoModal] tab button clicked', tabName);
                if (tabName) {
                    self.switchTab(tabName);
                }
            });
            */

            // Input method switching with enhanced UI updates
            $(document).on('change', 'input[name$="-input-method"]', function () {
                try {
                    const tabName = this.name.split('-')[0];
                    const method = this.value;

                    console.log(`Input method changed for ${tabName} to ${method}`);

                    // Update radio button indicators
                    const $label = $(this).closest('label');
                    const $allLabels = $(`input[name="${this.name}"]`).closest('label');

                    $allLabels.removeClass('bg-white shadow-md').addClass('hover:bg-white/50');
                    $label.removeClass('hover:bg-white/50').addClass('bg-white shadow-md');

                    // Show/hide appropriate sections
                    $(`.${tabName}-input-section`).addClass('hidden');
                    $(`.${tabName}-input-section[data-method="${method}"]`).removeClass('hidden');

                    console.log(`Showing ${tabName} section with method ${method}`);

                    // Load data if switching to smart selector
                    if (method === 'smart') {
                        self.loadTabData(tabName);
                    }

                    self.updatePreview();
                } catch (error) {
                    console.error("Error in input method change handler:", error);
                }
            });

            // MLS file type change
            $(document).on('change', '#mls-file-type', function () {
                const fileType = this.value;

                // Show/hide appropriate field groups
                $('.mls-type-fields').addClass('hidden');
                $(`.mls-type-fields[data-type*="${fileType}"]`).removeClass('hidden');

                // Update Prefix for DCIV/LPCC
                if (fileType === 'dciv' || fileType === 'lpcc') {
                    $('#mls-dciv-lpcc-prefix').val(fileType.toUpperCase());
                } else if (fileType === 'lpcc_dciv') {
                    // It's already a dropdown, we just need to ensure a value is selected if empty
                    if (!$('#mls-dciv-lpcc-prefix').val()) {
                        $('#mls-dciv-lpcc-prefix').val('LPCC');
                    }
                }

                self.updatePreview();
            });

            // Input changes for preview updates
            $(document).on('input change', '#global-fileno-modal input, #global-fileno-modal select', function () {
                self.updatePreview();
            });

            // Copy buttons
            $(document).on('click', '[id$="-copy-btn"]', function () {
                const tabName = this.id.replace('-copy-btn', '');
                self.copyToClipboard(tabName);
            });

            // Recent selection clicks
            $(document).on('click', '.recent-selection-btn', function (e) {
                e.preventDefault();
                const fileNumber = $(this).data('fileno');
                self.populateFromValue(fileNumber);
            });

            // Keyboard shortcuts
            $(document).on('keydown', function (e) {
                if ($('#global-fileno-modal').hasClass('hidden')) return;

                // Escape key to close
                if (e.keyCode === 27) {
                    self.close();
                }

                // Enter key to apply (if apply button is enabled)
                if (e.keyCode === 13 && !$('#apply-fileno-btn').prop('disabled')) {
                    self.apply();
                }

                // Tab keys for tab switching
                if (e.ctrlKey && e.keyCode >= 49 && e.keyCode <= 51) {
                    const tabIndex = e.keyCode - 49;
                    const tabs = ['mls', 'kangis', 'newkangis'];
                    if (tabs[tabIndex]) {
                        self.switchTab(tabs[tabIndex]);
                    }
                }
            });

            // Initialize year field with current year
            $('#mls-year, #mls-sit-year, #mls-dciv-lpcc-year, #mls-extension-year').val(new Date().getFullYear());

            // Enhanced preview updates with animations
            $(document).on('input change', '#global-fileno-modal input, #global-fileno-modal select', function () {
                const $preview = $(`#${self.config.currentTab}-preview`);
                $preview.addClass('animate-pulse');
                setTimeout(() => {
                    $preview.removeClass('animate-pulse');
                }, 200);
            });
        }
    };

    // jQuery plugin wrapper
    $.fn.globalFileNoModal = function (options) {
        const settings = $.extend({
            targetFields: [],
            initialTab: 'mls',
            callback: null
        }, options);

        return this.each(function () {
            const $trigger = $(this);

            $trigger.on('click', function (e) {
                e.preventDefault();

                // Set target fields if not explicitly provided
                if (settings.targetFields.length === 0) {
                    const form = $trigger.closest('form');
                    if (form.length > 0) {
                        settings.targetFields = [
                            'input[name="fileno"]',
                            'input[name="file_no"]',
                            'input[name="file_number"]',
                            'input[name="filenumber"]'
                        ];
                    }
                }

                GlobalFileNoModal.open(settings);
            });
        });
    };

    // Initialize when document is ready
    $(document).ready(function () {
        if ($('#global-fileno-modal').length > 0) {
            GlobalFileNoModal.init();

            // Auto-initialize buttons with data-fileno-modal attribute
            $('[data-fileno-modal]').each(function () {
                const $btn = $(this);
                const options = $btn.data('fileno-modal-options') || {};
                $btn.globalFileNoModal(options);
            });
        }
    });

})(jQuery);

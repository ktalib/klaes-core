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
            ST_FILES: '/api/file-numbers/st',
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
        excludePrefixes: [],  // Array of uppercase file-number prefixes to hide (e.g. ['CON'])
        allowedTabs: null,    // Array of registry tab names to show (e.g. ['mls']); null = show all
        tempOnly: false,      // MLPP tab only: restrict the smart selector to temporary "(T)" file numbers
        blockedYears: null,   // Array of 4-digit years this caller refuses (e.g. [2026]); null/[] = accept any year
        blockedYearMessage: null, // Override for the refusal text; null = the generic wording below
        manualOnly: false         // true = hide Smart Selector and open every tab on Manual Entry
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

            // MLS temp-file toggle: when the "(T)"-only state differs from the previous
            // open, drop the cached MLS list and force a fresh Select2 build so the
            // dropdown reloads with (or without) the temp filter applied.
            if (Boolean(this.config.tempOnly) !== Boolean(this._lastTempOnly)) {
                this.cache.mlsFiles = [];
                $('#mls-smart-selector').removeData('select2-initialized');
            }
            this._lastTempOnly = Boolean(this.config.tempOnly);

            // Restrict which registry tabs are visible when the caller scopes the
            // selector by module/registry (e.g. Land module → MLS only). null/empty
            // means show every tab (default behaviour for all other callers).
            const allowedTabs = (Array.isArray(this.config.allowedTabs) && this.config.allowedTabs.length)
                ? this.config.allowedTabs
                : null;
            this.applyAllowedTabs(allowedTabs);

            // Set initial tab — fall back to the first allowed tab when the requested
            // one is hidden by the allowedTabs scope.
            let initialTab = this.config.initialTab || DEFAULT_CONFIG.currentTab;
            if (allowedTabs && allowedTabs.indexOf(initialTab) === -1) {
                initialTab = allowedTabs[0];
            }

            // Show modal
            modal.removeClass('hidden').addClass('flex');

            // Ensure proper display and z-index
            modal.css({
                'display': 'flex',
                'position': 'fixed',
                'z-index': '2000000'
            });

            modal.focus();

            // Activate the requested tab now that the modal is visible so Select2 can size correctly
            this.switchTab(initialTab);
            this.applyManualOnly(Boolean(this.config.manualOnly));

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

        // Manual-entry-only mode: hide each tab's Smart Selector / Manual Entry toggle
        // and select Manual Entry. Turning it off restores the toggle and Smart Selector.
        applyManualOnly: function (on) {
            const wasOn = Boolean(this._manualOnly);
            this._manualOnly = on;
            if (!on && !wasOn) return;
            $('input[name$="-input-method"][value="manual"]').each(function () {
                $(this).closest('label').parent().toggleClass('hidden', on);
            });
            const pick = on ? 'manual' : 'smart';
            $('input[name$="-input-method"][value="' + pick + '"]').each(function () {
                if (!this.checked) $(this).prop('checked', true).trigger('change');
            });
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

            // Update current tab state
            this.config.currentTab = tabName;

            // Update header and tab button styling based on theme
            this.updateHeader(tabName);

            // Show/hide tab content
            $('.fileno-tab-content').addClass('hidden').css('display', 'none');
            const $activeContent = $(`.fileno-tab-content[data-tab="${tabName}"]`);
            $activeContent.removeClass('hidden');
            // Force display update in case Tailwind hidden isn't refreshing
            $activeContent.css('display', 'block');

            if ($activeContent.length === 0) {
                console.error('Tab content not found for: ' + tabName);
            }

            // Load data for the tab if needed
            this.loadTabData(tabName);

            // Update preview
            this.updatePreview();

            // Initialize the input method UI for this tab
            this.initializeTabUI(tabName);
            try { console.log('[GlobalFileNoModal] switchTab complete', { tabName }); } catch (e) { }
        },

        // Show only the registry tabs allowed for the current context. Passing null
        // (or an empty list) reveals every tab — the default for callers that don't scope.
        applyAllowedTabs: function (allowed) {
            const $buttons = $('.fileno-tab-btn');
            if (!allowed || !allowed.length) {
                $buttons.show();
                return;
            }
            $buttons.each(function () {
                const tab = $(this).attr('data-tab');
                $(this).toggle(allowed.indexOf(tab) !== -1);
            });
        },

        // What the officer reads for a tab, as opposed to the tab's key.
        //
        // The keys stay lowercase identifiers ('mls'), the API_ENDPOINTS lookup stays
        // MLS_FILES, and the `system` value handed to callers stays 'MLS' because
        // consumers compare against it. Only the visible wording is mapped.
        tabLabel: function (tabName) {
            const labels = { mls: 'MLPP' };
            const key = String(tabName || '').toLowerCase();
            return labels[key] || String(tabName || '').toUpperCase();
        },

        // Update modal header based on active tab
        updateHeader: function (tabName) {
            const headerConfig = {
                'mls': {
                    title: 'MLPP File Number Selector',
                    icon: 'building-2',
                    gradient: 'from-blue-500 via-blue-600 to-indigo-700',
                    color: 'text-blue-600'
                },
                'kangis': {
                    title: 'KANGIS (Legacy) Selector',
                    icon: 'map',
                    gradient: 'from-green-600 via-green-700 to-emerald-800',
                    color: 'text-green-600'
                },
                'newkangis': {
                    title: 'New KANGIS Format Selector',
                    icon: 'map-pin',
                    gradient: 'from-purple-600 via-purple-700 to-violet-800',
                    color: 'text-purple-600'
                },
                'sltr': {
                    title: 'SLTR File Number Selector',
                    icon: 'file-text',
                    gradient: 'from-indigo-600 via-indigo-700 to-blue-800',
                    color: 'text-indigo-600'
                },
                'old_mls': {
                    title: 'Old MLPP (KN) Selector',
                    icon: 'archive',
                    gradient: 'from-yellow-600 via-yellow-700 to-orange-800',
                    color: 'text-yellow-600'
                },
                'sit': {
                    title: 'SIT File Number Selector',
                    icon: 'file-digit',
                    gradient: 'from-pink-600 via-pink-700 to-rose-800',
                    color: 'text-pink-600'
                },
                'dciv': {
                    title: 'DCIv File Number Selector',
                    icon: 'folder-open',
                    gradient: 'from-teal-600 via-teal-700 to-cyan-800',
                    color: 'text-teal-600'
                },
                'gkn': {
                    title: 'GKN (Survey Registry) Selector',
                    icon: 'map-pinned',
                    gradient: 'from-orange-600 via-orange-700 to-red-800',
                    color: 'text-orange-600'
                },
                'st': {
                    title: 'ST (Sectional Titling) Selector',
                    icon: 'layers',
                    gradient: 'from-violet-600 via-violet-700 to-purple-800',
                    color: 'text-violet-600'
                }
            };

            const config = headerConfig[tabName] || headerConfig['mls'];

            // Update title
            $('#modal-header-title').text(config.title);

            // Update icon
            const $icon = $('#modal-header-icon');
            $icon.attr('data-lucide', config.icon);
            if (window.lucide) {
                window.lucide.createIcons();
            }

            // Update gradient
            const $header = $('#modal-header-container');
            // Remove all possible gradients first
            Object.values(headerConfig).forEach(c => {
                $header.removeClass(c.gradient);
            });
            $header.addClass(config.gradient);

            // Update tab button text color to match theme
            $('.fileno-tab-btn').each(function() {
                const btnTab = $(this).data('tab');
                const btnConfig = headerConfig[btnTab];
                if (btnTab === tabName) {
                    $(this).removeClass('text-gray-700').addClass(btnConfig.color + ' bg-white shadow-sm font-bold');
                } else {
                    $(this).removeClass(btnConfig.color + ' bg-white shadow-sm font-bold').addClass('text-gray-700');
                }
            });
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

        // Whether the MLS selector should surface every registry's file numbers
        // (KANGIS, New KANGIS, SLTR, SIT, DCIV, GKN, …) from the indexing table.
        // Suppressed when a caller scopes the selector for registry matching so
        // those lands/ST/SLTR contexts stay MLS/Lands-only.
        includeAllMlsRegistries: function () {
            const scoped = !!this.config.exclude_matched
                || (Array.isArray(this.config.excludePrefixes) && this.config.excludePrefixes.length > 0);
            return !scoped;
        },

        // Load data for specific tab
        loadTabData: function (tabName) {
            if (['sltr', 'old_mls', 'sit', 'dciv', 'gkn'].includes(tabName)) return; // Only manual entry for these
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
                    exclude_matched: this.config.exclude_matched || '',
                    all_registries: (tabName === 'mls' && this.includeAllMlsRegistries()) ? 1 : 0,
                    // MLS temp-file toggle: restrict initial list to "(T)" files
                    temp_only: (tabName === 'mls' && this.config.tempOnly) ? 1 : 0
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
                        this.showError(`Failed loading ${GlobalFileNoModal.tabLabel(tabName)} data`);
                    }
                })
                .fail((xhr, status, error) => {
                    console.warn(`API request failed for ${tabName}:`, { status, error, response: xhr?.responseText });
                    this.showError(`API error loading ${GlobalFileNoModal.tabLabel(tabName)} data`);
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
                        'data-fileno': fileNumber,
                        'data-temp': file.temp_file_no || file.temp_fileno || ''
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
                    placeholder: `Select or search ${GlobalFileNoModal.tabLabel(tabName)} file number...`,
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
                                        fileNumber: $(this).data('fileno'),
                                        tempFileNo: $(this).data('temp')
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
                                    exclude_matched: GlobalFileNoModal.config.exclude_matched || '',
                                    all_registries: (tabName === 'mls' && GlobalFileNoModal.includeAllMlsRegistries()) ? 1 : 0,
                                    // MLS temp-file toggle: restrict search results to "(T)" files
                                    temp_only: (tabName === 'mls' && GlobalFileNoModal.config.tempOnly) ? 1 : 0
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
                                                fileNumber: fileNumber,
                                                tempFileNo: file.temp_file_no || file.temp_fileno || ''
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
                        return GlobalFileNoModal.renderFileOption(data);
                    },
                    templateSelection: function (data) {
                        // Selection chip shows only the real file number (the value that gets applied)
                        return data.fileNumber || data.text || data.id;
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
                                  file.fileNumber || file.fileno || file.temp_file_no;
                    if (mlsNum) {
                        console.log('[GlobalFileNoModal] Extracted MLS file number:', mlsNum);
                    }
                    return mlsNum;
                case 'st':
                    const stNum = file.file_number || file.mlsFNo || file.fileno;
                    if (stNum) {
                        console.log('[GlobalFileNoModal] Extracted ST file number:', stNum);
                    }
                    return stNum;
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

        // Render a dropdown row: plain single-line, always the record's own main file number.
        renderFileOption: function (data) {
            if (data.loading) {
                return data.text;
            }

            const fileNo = data.fileNumber || data.text || '';
            const $result = $('<div class="select2-file-result"><div class="file-number"></div></div>');
            $result.find('.file-number').text(fileNo);
            return $result;
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
                    case 'sltr':
                        preview = this.generateSLTRPreview();
                        break;
                    case 'old_mls':
                        preview = this.generateOldMLSPreview();
                        break;
                    case 'sit':
                        preview = this.generateSITPreview();
                        break;
                    case 'dciv':
                        preview = this.generateDCIVPreview();
                        break;
                    case 'gkn':
                        preview = this.generateGKNPreview();
                        break;
                    case 'st':
                        preview = this.generateSTPreview();
                        break;
                }

                // If the input method is smart, extract the selected JSON data
                const inputMethod = $(`input[name="${tabName}-input-method"]:checked`).val() || 'manual';
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
                    tabName === 'kangis' ? 'text-green-900' : 
                    tabName === 'newkangis' ? 'text-purple-900' :
                    tabName === 'sltr' ? 'text-indigo-900' :
                    tabName === 'old_mls' ? 'text-yellow-900' :
                    tabName === 'sit' ? 'text-pink-900' :
                    tabName === 'dciv' ? 'text-teal-900' :
                    tabName === 'gkn' ? 'text-orange-900' :
                    tabName === 'st' ? 'text-violet-900' : 'text-gray-900';
                $preview.html(`<span class="${colorClass} font-bold">${preview}</span>`);
                $preview.removeClass('text-gray-400');

                // Say it as soon as the number is built rather than only on Apply. Apply
                // stays clickable on purpose — clicking it is what raises the alert that
                // spells out why the file cannot be indexed.
                const previewBlockedYear = this.blockedYearFor(preview);
                if (previewBlockedYear !== null) {
                    this.showError(this.buildBlockedYearMessage(preview, previewBlockedYear));
                } else {
                    this.showSuccess('✓ File number ready');
                }

                // Render the file-details card. Paint immediately from any
                // smart-selected data for instant feedback, then enrich from the
                // lookup API — which returns the complete record (File Name, LGA,
                // Location, Land Use) — so KANGIS, SLTR and the other registries
                // display full file details just like the MLS tab.
                if ($detailsContainer.length) {
                    if (fileData) {
                        $detailsContainer.html(this.buildDetailsCard(tabName, fileData)).removeClass('hidden');
                    }
                    this.loadFileDetailsCard(tabName, preview, fileData);
                }
            } else {
                $preview.html('<span class="text-gray-400 font-normal">-</span>');
                if ($detailsContainer.length) {
                    $detailsContainer.addClass('hidden').empty();
                }
                this.cancelDetailsLoad(tabName);
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

        // Build the file-details card HTML shared by every registry tab.
        // Reads both the sparse smart-selector shape (FileName/location) and the
        // richer lookup-API shape (file_name/lga/land_use) so it renders complete
        // details regardless of the source.
        buildDetailsCard: function (tabName, data) {
            data = data || {};
            const fileName = data.FileName || data.file_name || data.file_title || '—';
            const lga = data.LGA || data.lga || data.fi_lga || data.ma_lga || '—';
            const location = data.Location || data.location || data.ma_location || data.district || '—';
            const landUse = data.LandUse || data.land_use || data.ma_land_use || '—';

            const labelColors = {
                mls: 'text-blue-600', kangis: 'text-green-600', newkangis: 'text-purple-600',
                sltr: 'text-indigo-600', old_mls: 'text-yellow-600', sit: 'text-pink-600',
                dciv: 'text-teal-600', gkn: 'text-orange-600'
            };
            const borderColors = {
                mls: 'border-blue-100', kangis: 'border-green-100', newkangis: 'border-purple-100',
                sltr: 'border-indigo-100', old_mls: 'border-yellow-100', sit: 'border-pink-100',
                dciv: 'border-teal-100', gkn: 'border-orange-100'
            };
            const labelColor = labelColors[tabName] || 'text-gray-600';
            const borderColor = borderColors[tabName] || 'border-gray-100';

            const esc = (v) => $('<div>').text(v == null || v === '' ? '—' : v).html();
            return `
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
        },

        // Fetch the complete record for a file number from the lookup API and
        // (re)render the details card with it. Debounced and sequence-guarded per
        // tab so rapid typing/selection only paints the latest result. Any fields
        // missing from the lookup fall back to the smart-selector data.
        loadFileDetailsCard: function (tabName, fileNumber, fileData) {
            const $container = $(`#${tabName}-details-container`);
            if (!$container.length) return;

            fileNumber = (fileNumber || '').toString().trim();
            if (!fileNumber || fileNumber === '-') return;

            this._detailsTimers = this._detailsTimers || {};
            this._detailsRequestSeq = this._detailsRequestSeq || {};

            if (this._detailsTimers[tabName]) {
                clearTimeout(this._detailsTimers[tabName]);
            }

            const seq = (this._detailsRequestSeq[tabName] || 0) + 1;
            this._detailsRequestSeq[tabName] = seq;

            this._detailsTimers[tabName] = setTimeout(() => {
                this.fetchFileDetails(fileNumber, tabName)
                    .then((record) => {
                        // Drop stale responses or ones for a tab we've left.
                        if (this._detailsRequestSeq[tabName] !== seq) return;
                        if (this.config.currentTab !== tabName) return;
                        if (!record) return; // keep any card already painted from fileData

                        // Merge: lookup values win, but keep smart-selector values
                        // for anything the lookup left blank.
                        const merged = $.extend({}, fileData || {});
                        Object.keys(record).forEach((k) => {
                            const v = record[k];
                            if (v !== null && v !== undefined && v !== '') {
                                merged[k] = v;
                            }
                        });

                        $container.html(this.buildDetailsCard(tabName, merged)).removeClass('hidden');
                    })
                    .catch(() => { /* leave any existing card in place on error */ });
            }, 300);
        },

        // Cancel a pending details lookup and invalidate its result (used when the
        // preview is cleared so a late response can't repopulate the card).
        cancelDetailsLoad: function (tabName) {
            if (this._detailsTimers && this._detailsTimers[tabName]) {
                clearTimeout(this._detailsTimers[tabName]);
                this._detailsTimers[tabName] = null;
            }
            this._detailsRequestSeq = this._detailsRequestSeq || {};
            this._detailsRequestSeq[tabName] = (this._detailsRequestSeq[tabName] || 0) + 1;
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
                        return `${prefix}-${year}-${serial} AND EXTENSION`;
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
                        const isTemp = $('#mls-dciv-file-type').val() === 'temporary';
                        const fileNo = `${prefix}-${year}-${serial}`;
                        return isTemp ? `${fileNo}(T)` : fileNo;
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

        // Generate SLTR preview
        generateSLTRPreview: function () {
            const serial = $('#mls-sltr-serial').val();
            return serial ? `SLTR-${serial}` : '';
        },

        // Generate Old MLS preview
        generateOldMLSPreview: function () {
            const serial = $('#mls-old-serial').val();
            return serial ? `KN ${serial}` : '';
        },

        // Generate SIT preview
        generateSITPreview: function () {
            const year = $('#mls-sit-year').val();
            const serial = $('#mls-sit-serial').val();
            if (year && serial) {
                const isTemp = $('#mls-sit-temporary').is(':checked');
                return isTemp ? `SIT-${year}-${serial} (T)` : `SIT-${year}-${serial}`;
            }
            return '';
        },

        // Generate DCIV preview
        generateDCIVPreview: function () {
            const prefix = $('#mls-dciv-lpcc-prefix').val();
            const year = $('#mls-dciv-lpcc-year').val();
            const serial = $('#mls-dciv-lpcc-serial').val();
            if (prefix && year && serial) {
                const isTemp = $('#mls-dciv-file-type').val() === 'temporary';
                const fileNo = `${prefix}-${year}-${serial}`;
                return isTemp ? `${fileNo}(T)` : fileNo;
            }
            return '';
        },

        // Generate GKN preview
        generateGKNPreview: function () {
            const prefix = $('#mls-gkn-prefix').val() || 'GKN';
            const serial = $('#mls-gkn-serial').val();
            if (serial) {
                const isTemp = $('#mls-gkn-temporary').is(':checked');
                let fileNo = prefix === 'MISC' ? `MISC KN ${serial}` : `${prefix}-${serial}`;
                return isTemp ? `${fileNo} (T)` : fileNo;
            }
            return '';
        },

        // Generate ST (Sectional Titling) preview — format depends on type
        generateSTPreview: function () {
            const inputMethod = $('input[name="st-input-method"]:checked').val();
            
            if (inputMethod === 'smart') {
                const selected = $('#st-smart-selector').val();
                if (selected) {
                    try {
                        const fileData = JSON.parse(selected);
                        return this.extractFileNumber(fileData, 'st');
                    } catch (e) {
                        return '';
                    }
                }
                return '';
            }

            const type    = $('#mls-st-type').val() || 'primary';
            const landuse = $('#mls-st-landuse').val() || 'COM';
            const year    = $('#mls-st-year').val();
            const serial  = $('#mls-st-serial').val();
            if (!landuse || !year || !serial) return '';
            const base = `ST-${landuse}-${year}-${serial}`;
            if (type === 'primary') {
                return base;
            }
            // SuA / PuA — append unit sequence (zero-padded to 3)
            const unit = $('#mls-st-unit').val();
            if (!unit) return '';
            return `${base}-${String(unit).padStart(3, '0')}`;
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
            const fileType = $('#kangis-file-type').val();
            const prefix = $('#kangis-prefix').val().trim();
            const number = $('#kangis-number').val().trim();

            if (prefix && number) {
                const fileNo = `${prefix} ${number}`;
                return fileType === 'temporary' ? `${fileNo}(T)` : fileNo;
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

        // --- Blocked-year policy -------------------------------------------------
        // A caller can refuse whole years (config.blockedYears). The file indexing
        // interface uses this to keep current-cycle files out of the backlog, since
        // the generation module already raises those with their particulars complete.

        // Pull the four-digit year out of a file number, for the registry formats that
        // carry one: MLS regular/temporary/extension (IND-2026-2222, CON-RES-RC-2026-10),
        // SIT (SIT-2026-1), DCIV/LPCC (LPCC-2026-1) and ST (ST-COM-2026-1, plus the
        // -001 unit suffix on SuA/PuA). In all of them the year is a hyphen-delimited
        // segment of its own, never the first and never the last.
        //
        // KANGIS ("MLKN 2026"), New KANGIS ("KN2026"), SLTR ("SLTR-2026"), Old MLS
        // ("KN 2026") and GKN ("GKN-2026" / "MISC KN 2026") put a SERIAL where the year
        // would otherwise sit. Requiring a hyphen on both sides of the segment leaves
        // every one of those unmatched, so a 2026 serial is never mistaken for a 2026
        // file and those registries stay fully indexable.
        extractFileNumberYear: function (fileNumber) {
            const base = String(fileNumber || '')
                .toUpperCase()
                .replace(/\s*\(\s*T\s*\)\s*$/, '')     // temporary marker: "(T)" or " (T)"
                .replace(/\s+AND\s+EXTENSION\s*$/, '')  // "RES-2026-10 AND EXTENSION"
                .trim();

            const parts = base.split('-');
            if (parts.length < 3) return null;

            // Skip the prefix (which may itself be hyphenated, as in CON-RES-RC) and the
            // trailing serial. Take the first plausible year so that ST-COM-2019-2026-001
            // reads as 2019 — the later segment there is the serial, not a second year.
            for (let i = 1; i <= parts.length - 2; i++) {
                if (!/^\d{4}$/.test(parts[i])) continue;
                const year = parseInt(parts[i], 10);
                if (year >= 1900 && year <= 2100) return year;
            }

            return null;
        },

        // The year this file number falls in, when the caller refuses that year. Null
        // otherwise — including for every format that carries no year at all.
        blockedYearFor: function (fileNumber) {
            const blocked = Array.isArray(this.config.blockedYears)
                ? this.config.blockedYears.map(Number).filter(Number.isInteger)
                : [];
            if (!blocked.length) return null;

            const year = this.extractFileNumberYear(fileNumber);
            return (year !== null && blocked.indexOf(year) !== -1) ? year : null;
        },

        buildBlockedYearMessage: function (fileNumber, year) {
            return this.config.blockedYearMessage
                || `${fileNumber} is a ${year} file. ${year} files cannot be indexed from the file indexing interface.`;
        },

        // Refuse the number and leave the selector open on what the officer built, so
        // they can correct the year in place rather than start the pick over.
        rejectBlockedYear: function (fileNumber, year) {
            const message = this.buildBlockedYearMessage(fileNumber, year);
            this.showError(message);

            if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                Swal.fire({
                    icon: 'error',
                    title: `${year} files cannot be indexed`,
                    text: message,
                    confirmButtonText: 'Set another FileNo Year',
                    confirmButtonColor: '#4f46e5',
                    // Lifts the card above the selector — see the .fileno-selector-alert
                    // rule in components/global-fileno-modal.blade.php.
                    customClass: { container: 'fileno-selector-alert' },
                    // Backstop for the stylesheet rule in components/global-fileno-modal.blade.php:
                    // this selector sits at z-index 2000000 and the alert has to outrank it.
                    // willOpen, not didOpen — didOpen runs only after the open animation has
                    // finished, by which point the card has already painted behind the selector.
                    willOpen: (el) => {
                        const container = el.closest('.swal2-container') || el.parentElement;
                        if (container) container.style.zIndex = '2000001';
                    },
                });
            } else {
                window.alert(message);
            }
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
            const inputMethod = $(`input[name="${tabName}-input-method"]:checked`).val() || 'manual';
            
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

            // Years the caller refuses (the indexing form passes 2026). Checked before
            // anything is recorded, so a refused number never reaches recent selections,
            // the target fields or the caller's callback.
            const blockedYear = this.blockedYearFor(fileNumber);
            if (blockedYear !== null) {
                console.warn('[GlobalFileNoModal] Blocked year, aborting apply', { fileNumber, blockedYear });
                this.rejectBlockedYear(fileNumber, blockedYear);
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
                    this.showSuccess(`${GlobalFileNoModal.tabLabel(tabName)} file number applied successfully`);
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

            // Auto-fill General Registry based on the selected tab (skip for related file number contexts)
            if (this.config.autoPopulateGenericFields !== false) {
                const tabRegistryMap = {
                    'mls':       'Lands Registry',
                    'kangis':    'KANGIS Registry',
                    'newkangis': 'KANGIS Registry',
                    'sltr':      'SLTR Registry',
                    'sit':       'SIT Registry',
                    'dciv':      'DCIV Registry',
                    'gkn':       'Survey Registry',
                    'st':        'ST Registry'
                };
                const registryValue = tabRegistryMap[tabName];
                if (registryValue) {
                    const registryField = $('#general-registry, #general_registry');
                    registryField.each(function () {
                        const $field = $(this);
                        const matchingOption = $field.find(`option`).filter(function () {
                            return $(this).val().toLowerCase() === registryValue.toLowerCase();
                        });
                        if (matchingOption.length > 0) {
                            $field.val(matchingOption.val()).trigger('change');
                        }
                    });
                }
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
                $('#global-fileno-modal input:not([type="radio"]):not([type="checkbox"]):not([type="file"]):not([readonly]), #global-fileno-modal select').val('').trigger('change');
                $('#global-fileno-modal input[type="checkbox"]').prop('checked', false).trigger('change');

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

        // Refresh the modal in place: drop cached file lists, rebuild the smart
        // selectors with fresh server data and reset the form — no page reload.
        refresh: function (btn) {
            try {
                console.log("Refreshing modal...");

                // Spin the refresh icon for visual feedback
                const $icon = btn ? $(btn).find('i') : $();
                $icon.addClass('animate-spin');

                // Drop every cached file list so selectors re-fetch fresh data
                this.cache.mlsFiles = [];
                this.cache.kangisFiles = [];
                this.cache.newkangisFiles = [];

                // Tear down existing Select2 instances so they rebuild with new data
                ['mls', 'kangis', 'newkangis'].forEach((tab) => {
                    const $sel = $(`#${tab}-smart-selector`);
                    if ($sel.data('select2')) {
                        $sel.select2('destroy');
                    }
                    $sel.removeData('select2-initialized');
                });

                // resetForm() clears inputs/previews and reloads the current tab's data
                this.resetForm();

                // Re-render lucide icons in case markup was swapped during reset
                if (window.lucide) {
                    window.lucide.createIcons();
                    // createIcons replaces the <i>, so re-grab and stop the spin shortly after
                    const $freshIcon = btn ? $(btn).find('i, svg') : $();
                    setTimeout(() => $freshIcon.removeClass('animate-spin'), 600);
                } else {
                    setTimeout(() => $icon.removeClass('animate-spin'), 600);
                }

                this.showSuccess('Modal refreshed');
                console.log("Modal refresh complete");
            } catch (error) {
                console.error("Error refreshing modal:", error);
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
                    placeholder: 'Search MLPP file numbers…',
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
                            const data = {
                                search: params.term || '',
                                limit: 40,
                                exclude_matched: modal.config.exclude_matched || ''
                            };
                            // MLS temp-file toggle: only surface "(T)" temporary file numbers
                            if (tabName === 'mls' && modal.config.tempOnly) {
                                data.temp_only = 1;
                            }
                            return data;
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
                                    text: fileNumber,
                                    fileNumber: fileNumber,
                                    tempFileNo: file.temp_file_no || file.temp_fileno || ''
                                };
                            }).filter(Boolean);

                            return {
                                results
                            };
                        },
                        cache: true
                    },
                    templateResult: function (data) {
                        return GlobalFileNoModal.renderFileOption(data);
                    },
                    templateSelection: function (data) {
                        // Selection chip shows only the real file number (the value that gets applied)
                        return data.fileNumber || data.text || data.id;
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

            // Initialize year field with current year (skip on file indexing create page)
            if (!window.location.pathname.includes('/fileindexing/create')) {
                $('#mls-year, #mls-sit-year, #mls-dciv-lpcc-year, #mls-extension-year, #mls-st-year').val(new Date().getFullYear());
            }

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

//   {public function 
//     get system data:=1
//     if access: null;
//     $btn.GlobalFileNoModal = function (options) {
//         const settings = $.extend({
//             targetFields: [],
//             initialTab: 'mls',
//             callback: null
//         }, options);
//         get.success.OnClick = function (e) {
//             e.preventDefault();

//             $mgmt = $btn.closest('form');
//   }

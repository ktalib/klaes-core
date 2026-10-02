    <script>
        // Wait for DOM and libraries to load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Check for ST filter from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const stFilterActive = urlParams.get('url') === 'st';
            
            const LABEL_CAPACITY = 100;
            const RANGE_OPTIONS = {
                '1-30': { start: 1, end: 30 },
                '31-60': { start: 31, end: 60 },
                '61-90': { start: 61, end: 90 },
                '91-100': { start: 91, end: 100 },
            };
            const defaultRange = RANGE_OPTIONS['1-30'];

            // Application state
            let state = {
                selectedFiles: [],
                labelSize: "30-in-1",
                labelFormat: "qrcode",
                activeTab: "files",
                copies: 1,
                selectedTemplate: "30-in-1",
                showHistory: false,
                searchTerm: "",
                orientation: "portrait",
                showAdvancedOptions: false,
                batchMode: true,
                batchStartNumber: 1,
                batchCount: (defaultRange.end - defaultRange.start) + 1,
                registryId: '1',
                registryBatchNo: '',
                rackPrimary: 'A',
                rackSecondary: '',
                shelfNumber: '1',
                fullLabel: 'A1',
                rangeKey: '1-30',
                rangeStart: defaultRange.start,
                rangeEnd: defaultRange.end,
                availableFiles: [],
                generatedBatches: [],
                currentPage: 1,
                totalPages: 1,
                loading: false,
                stFilterActive: stFilterActive, // Add ST filter state
                currentBatchId: null,
                preparedRecords: null,
                preparedBaseEntries: null,
                preparedSignature: null,
                previewPrepared: false,
                preparedBatchResponse: null,
                previewInFlight: false,
                previewPromise: null,
                labelStatistics: null,
                rackLabelStatus: null,
                registryProgress: null,
                excludeAssignedFromBatch: false,
            };

            setRangeSelection(state.rangeKey);

            const batchStartInputEl = document.getElementById('batchStart');
            if (batchStartInputEl) {
                batchStartInputEl.dataset.userOverride = 'false';
            }

            const batchRangeSelectEl = document.getElementById('batchRange');
            if (batchRangeSelectEl) {
                batchRangeSelectEl.dataset.userOverride = 'false';
            }

            function resetPreparedState(options = {}) {
                state.preparedRecords = null;
                state.preparedBaseEntries = null;
                state.preparedSignature = null;
                state.previewPrepared = false;
                state.preparedBatchResponse = null;
                state.previewPromise = null;
                state.previewInFlight = false;
                state.labelStatistics = null;
                updateRackStatisticsDisplay(null);

                if (!options.preserveBatchId) {
                    state.currentBatchId = null;
                }
            }

            function toggleBatchModeUI(isActive) {
                const batchControlsEl = document.getElementById('batchControls');
                const batchModeCardEl = document.getElementById('batchModeCard');

                if (!batchControlsEl || !batchModeCardEl) {
                    return;
                }

                if (isActive) {
                    batchControlsEl.classList.remove('hidden');
                    batchModeCardEl.classList.remove('bg-slate-50', 'border-slate-200');
                    batchModeCardEl.classList.add('bg-white', 'border-blue-200', 'shadow-sm');
                } else {
                    batchControlsEl.classList.add('hidden');
                    batchModeCardEl.classList.remove('bg-white', 'border-blue-200', 'shadow-sm');
                    batchModeCardEl.classList.add('bg-slate-50', 'border-slate-200');
                }
            }

            function hasSelectionForBatch() {
                if (state.batchMode) {
                    return state.selectedFiles.length > 0;
                }

                return state.selectedFiles.length > 0;
            }

            function setRangeSelection(rangeKey) {
                const config = RANGE_OPTIONS[rangeKey] || RANGE_OPTIONS['1-30'];
                state.rangeKey = rangeKey;
                state.rangeStart = config.start;
                state.rangeEnd = config.end;
                state.batchCount = (config.end - config.start) + 1;
            }

            function updateFullLabelDisplay() {
                const primary = (state.rackPrimary || '').toUpperCase().trim();
                const secondary = (state.rackSecondary || '').toUpperCase().trim();
                const shelf = (state.shelfNumber || '').toString().trim();
                const label = `${primary}${secondary}${shelf}`;
                state.fullLabel = label;

                const labelInput = document.getElementById('fullLabelInput');
                if (labelInput) {
                    labelInput.value = label;
                }

                const labelValue = document.getElementById('fullLabelValue');
                if (labelValue) {
                    labelValue.textContent = label || '—';
                }

                return label;
            }

            function updateRackLabelStatusDisplay() {
                const counterEl = document.getElementById('rackLabelCounterDisplay');
                const statusEl = document.getElementById('rackLabelStatusText');

                if (!counterEl || !statusEl) {
                    return;
                }

                const status = state.rackLabelStatus;
                if (!status) {
                    counterEl.textContent = `0 / ${LABEL_CAPACITY}`;
                    statusEl.textContent = 'Awaiting selection';
                    statusEl.classList.remove('text-red-600');
                    statusEl.classList.add('text-slate-500');
                    const labelValue = document.getElementById('fullLabelValue');
                    if (labelValue) {
                        labelValue.textContent = state.fullLabel || '—';
                    }
                    return;
                }

                const capacity = Number(status.capacity ?? LABEL_CAPACITY);
                const used = Number(status.counter ?? 0);
                counterEl.textContent = `${used} / ${capacity}`;

                if (status.is_full) {
                    statusEl.textContent = 'Full – choose another label';
                    statusEl.classList.remove('text-slate-500');
                    statusEl.classList.add('text-red-600');
                } else {
                    const remaining = Math.max(0, capacity - used);
                    statusEl.textContent = `${remaining} slots remaining`;
                    statusEl.classList.remove('text-red-600');
                    statusEl.classList.add('text-slate-500');
                }

                const labelValue = document.getElementById('fullLabelValue');
                if (labelValue) {
                    labelValue.textContent = status.full_label || state.fullLabel || '—';
                }
            }

            function syncRegistryProgress(progress, options = {}) {
                state.registryProgress = progress || null;

                if (!progress) {
                    return;
                }

                const forceUpdate = options.force === true;
                const nextStartValue = Number(progress.next_start);
                const capacity = Number(progress.capacity ?? LABEL_CAPACITY);
                const hasCapacity = Number.isFinite(capacity) && capacity > 0;
                const canApplyStart = Number.isFinite(nextStartValue)
                    && nextStartValue > 0
                    && (!hasCapacity || nextStartValue <= capacity + 1);

                const batchStartInput = document.getElementById('batchStart');
                if (batchStartInput && canApplyStart) {
                    const currentValue = parseInt(batchStartInput.value, 10);
                    const userOverride = batchStartInput.dataset?.userOverride === 'true';
                    if (forceUpdate || !userOverride || Number.isNaN(currentValue) || currentValue < nextStartValue) {
                        batchStartInput.value = nextStartValue;
                        batchStartInput.dataset.userOverride = 'false';
                        state.batchStartNumber = nextStartValue;
                    }
                } else if (canApplyStart) {
                    state.batchStartNumber = nextStartValue;
                }

                const nextRangeKey = progress.next_range_key;
                if (nextRangeKey && RANGE_OPTIONS[nextRangeKey]) {
                    const rangeSelect = document.getElementById('batchRange');
                    if (rangeSelect) {
                        const userOverride = rangeSelect.dataset?.userOverride === 'true';
                        if (forceUpdate || !userOverride || rangeSelect.value !== nextRangeKey) {
                            rangeSelect.value = nextRangeKey;
                            rangeSelect.dataset.userOverride = 'false';
                            setRangeSelection(nextRangeKey);
                        }
                    } else {
                        setRangeSelection(nextRangeKey);
                    }
                }

                if (typeof progress.remaining === 'number' && progress.remaining > 0 && progress.remaining < state.batchCount) {
                    state.batchCount = progress.remaining;
                    state.rangeEnd = Math.min(state.rangeStart + progress.remaining - 1, LABEL_CAPACITY);
                }
            }

            function setRegistryBatchSelection(value, labelText) {
                const selectElement = window.jQuery ? window.jQuery('#registryBatchSelect') : null;
                if (!selectElement || !selectElement.length || !value) {
                    return;
                }

                const existingOption = selectElement.find(`option[value=\"${value}\"]`);
                if (!existingOption.length) {
                    const option = new Option(labelText || value, value, true, true);
                    selectElement.append(option);
                }

                selectElement.val(value).trigger('change');
            }

            async function fetchRackLabelStatus(label) {
                const normalized = (label || '').trim();

                if (normalized === '') {
                    state.rackLabelStatus = null;
                    updateRackLabelStatusDisplay();
                    return;
                }

                try {
                    const params = new URLSearchParams({ full_label: normalized });
                    if (state.registryId) {
                        params.append('registry', state.registryId);
                    }
                    const response = await fetch(`${API.rackLabelStatus}?${params.toString()}`);
                    if (!response.ok) {
                        throw new Error('Unable to fetch rack label status.');
                    }
                    const data = await response.json();
                    if (data.success) {
                        state.rackLabelStatus = data.data;
                        syncRegistryProgress(data.data?.registry_progress || null);
                    } else {
                        state.rackLabelStatus = null;
                        syncRegistryProgress(null);
                    }
                } catch (error) {
                    console.warn('Failed to load rack label status', error);
                    state.rackLabelStatus = null;
                    syncRegistryProgress(null);
                } finally {
                    updateRackLabelStatusDisplay();
                }
            }

            function initializeRegistryBatchSelect() {
                if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                    return;
                }

                const $select = jQuery('#registryBatchSelect');
                if (!$select.length) {
                    return;
                }

                $select.select2({
                    placeholder: $select.data('placeholder') || 'Search registry batch...',
                    width: '100%',
                    allowClear: true,
                    ajax: {
                        url: API.registryBatches,
                        delay: 250,
                        data(params) {
                            return {
                                q: params.term || '',
                                page: params.page || 1,
                                per_page: 25,
                                registry: state.registryId,
                                order: 'asc',
                            };
                        },
                        processResults(response) {
                            const items = Array.isArray(response?.data) ? response.data : [];
                            return {
                                results: items,
                                pagination: {
                                    more: response?.pagination?.has_more ?? false,
                                },
                            };
                        },
                    },
                });

                $select.on('select2:select', (event) => {
                    const selected = event?.params?.data;
                    state.registryBatchNo = selected?.id || '';
                    resetPreparedState();
                });

                $select.on('select2:clear', () => {
                    state.registryBatchNo = '';
                    resetPreparedState();
                    syncRegistryProgress(null);
                });
            }

            async function loadInitialRegistryBatches() {
                if (typeof jQuery === 'undefined') {
                    return;
                }

                const $select = jQuery('#registryBatchSelect');
                if (!$select.length) {
                    return;
                }

                try {
                    const params = new URLSearchParams({
                        per_page: 100,
                        registry: state.registryId,
                        order: 'asc',
                    });
                    const response = await fetch(`${API.registryBatches}?${params.toString()}`);
                    if (!response.ok) {
                        throw new Error('Unable to load registry batches.');
                    }

                    const data = await response.json();
                    if (!data.success || !Array.isArray(data.data)) {
                        return;
                    }

                    $select.empty();
                    data.data.forEach((item) => {
                        const option = new Option(item.text || item.registry_batch_no, item.id, false, false);
                        $select.append(option);
                    });

                    if (state.registryBatchNo) {
                        $select.val(state.registryBatchNo).trigger('change');
                    }
                } catch (error) {
                    console.warn('Failed to load registry batch options', error);
                }
            }

            function computePreparationSignature() {
                const source = state.batchMode ? 'grouping' : 'file_indexings';
                const selectedIds = [...state.selectedFiles]
                    .map((value) => (value !== undefined && value !== null ? value.toString() : ''))
                    .filter((value) => value !== '')
                    .sort();

                return JSON.stringify({
                    source,
                    selectedIds,
                    batchMode: state.batchMode,
                    batchCount: state.batchCount,
                    registryBatchNo: state.registryBatchNo,
                    registryId: state.registryId,
                    fullLabel: state.fullLabel,
                    rangeKey: state.rangeKey,
                    selectedTemplate: state.selectedTemplate,
                    orientation: state.orientation,
                });
            }

            function buildPreparedEntriesFromItems(items) {
                if (!Array.isArray(items) || items.length === 0) {
                    return { records: [], baseEntries: [] };
                }

                const availableById = new Map(
                    state.availableFiles.map((file) => [String(file.id), file])
                );

                const availableByFileNumber = new Map(
                    state.availableFiles
                        .map((file) => {
                            const key = (file.file_number ?? '').toString().trim();
                            return key ? [key, file] : null;
                        })
                        .filter(Boolean)
                );

                const rawRecords = items.map((item, index) => {
                    const normalizedItem = item ? { ...item } : {};

                    if (normalizedItem.qr_code_data && typeof normalizedItem.qr_code_data === 'string') {
                        try {
                            normalizedItem.qr_code_data = JSON.parse(normalizedItem.qr_code_data);
                        } catch (error) {
                            normalizedItem.qr_code_data = {};
                        }
                    }

                    const qrData = (normalizedItem.qr_code_data && typeof normalizedItem.qr_code_data === 'object')
                        ? normalizedItem.qr_code_data
                        : {};

                    const fallbackId = normalizedItem.batch_item_id ?? `batch-item-${index}`;
                    const rawId = normalizedItem.file_indexing_id ?? normalizedItem.id ?? fallbackId;
                    const idKey = String(rawId);

                    const baseById = availableById.get(idKey);
                    const baseByFileNumber = normalizedItem.file_number
                        ? availableByFileNumber.get(String(normalizedItem.file_number).trim())
                        : undefined;

                    const baseRecord = baseById || baseByFileNumber || {};
                    const combined = { ...baseRecord, ...normalizedItem };

                    combined.id = rawId;

                    const explicitFullLabel = combined.shelf_full_label
                        ?? baseRecord.shelf_full_label
                        ?? null;

                    const shelfCandidate = explicitFullLabel
                        ?? combined.shelf_label
                        ?? combined.shelf_value
                        ?? combined.shelf_location
                        ?? baseRecord.shelf_label
                        ?? baseRecord.shelf_value
                        ?? baseRecord.shelf_location
                        ?? null;

                    const normalizedShelfLabel = shelfCandidate
                        ? normalizeLocationValue(shelfCandidate)
                        : 'Shelf/Rack-N/A';

                    const shelfValue = explicitFullLabel
                        ? explicitFullLabel
                        : getDisplayShelfValue(normalizedShelfLabel, shelfCandidate);

                    combined.shelf_full_label = explicitFullLabel ?? combined.shelf_full_label ?? null;
                    combined.shelf_value = shelfValue ?? combined.shelf_value ?? null;

                    const trackingId = combined.tracking_id
                        ?? qrData.tracking_id
                        ?? baseRecord.tracking_id
                        ?? baseRecord.indexing_tracking_id
                        ?? baseRecord.grouping_tracking_id
                        ?? null;

                    const qrValue = combined.qr_value
                        ?? qrData.tracking_id
                        ?? trackingId
                        ?? combined.file_number
                        ?? baseRecord.file_number
                        ?? null;

                    return {
                        ...combined,
                        shelf_label: normalizedShelfLabel,
                        shelf_value: shelfValue || 'N/A',
                        tracking_id: trackingId,
                        qr_value: qrValue,
                        qr_code_data: qrData,
                    };
                });

                const records = sortByFileNumber(rawRecords);

                const baseEntries = records.map((record) => {
                    const { primaryNumber, secondaryNumber, isSTContext } = deriveFileNumbers(record);
                    const trackingId = (record.tracking_id ?? record.qr_value ?? record.file_number ?? '').toString();

                    return {
                        id: record.id,
                        fileNumber: primaryNumber || record.file_number || record.originalFileNumber || null,
                        primaryFileNumber: primaryNumber || record.file_number || null,
                        secondaryFileNumber: secondaryNumber || null,
                        originalFileNumber: record.file_number || null,
                        isSTFile: isSTContext,
                        shelfLabel: record.shelf_label || 'Shelf/Rack-N/A',
                        shelfValue: record.shelf_value || 'N/A',
                        trackingId,
                        fileTitle: record.file_title || '',
                        qrValue: record.qr_value || trackingId || record.file_number || '',
                    };
                });

                return { records, baseEntries };
            }

            async function preparePreviewDataIfNeeded(force = false) {
                const signature = computePreparationSignature();

                if (!force && state.previewPrepared && state.preparedSignature === signature && Array.isArray(state.preparedBaseEntries) && state.preparedBaseEntries.length) {
                    return state.preparedBatchResponse;
                }

                if (state.previewInFlight && state.previewPromise) {
                    return state.previewPromise;
                }

                state.previewInFlight = true;

                const promise = persistBatchForPrinting()
                    .then((result) => {
                        const labelItems = result && result.data && Array.isArray(result.data.label_items)
                            ? result.data.label_items
                            : [];
                        const { records, baseEntries } = buildPreparedEntriesFromItems(labelItems);

                        state.preparedRecords = records;
                        state.preparedBaseEntries = baseEntries;
                        state.previewPrepared = true;
                        state.preparedSignature = signature;
                        state.preparedBatchResponse = result;
                        state.labelStatistics = (result && result.data && result.data.label_statistics)
                            ? result.data.label_statistics
                            : null;
                        if (result?.data?.rack_label_status) {
                            state.rackLabelStatus = result.data.rack_label_status;
                            updateRackLabelStatusDisplay();
                        }
                        if (result?.data?.registry_progress) {
                            syncRegistryProgress(result.data.registry_progress);
                        } else if (result?.data?.rack_label_status?.registry_progress) {
                            syncRegistryProgress(result.data.rack_label_status.registry_progress);
                        }
                        state.previewInFlight = false;
                        state.previewPromise = null;

                        if (result.data && result.data.batch_id) {
                            state.currentBatchId = result.data.batch_id;
                        }

                        return result;
                    })
                    .catch((error) => {
                        state.previewInFlight = false;
                        state.previewPromise = null;
                        resetPreparedState();
                        throw error;
                    });

                state.previewPromise = promise;
                return promise;
            }

            function refreshPreview(force = false) {
                if (!hasSelectionForBatch()) {
                    resetPreparedState();
                    updatePreview();
                    return;
                }

                // Preview should be a read-only step; defer batch persistence to the final print action.
                updatePreview();
            }

            const PRINT_TEMPLATE_URL = "{{ route('printlabel.print-template') }}";

            // API endpoints
            const API = {
                files: '/printlabel/api/files',
                createBatch: '/printlabel/api/batch',
                batches: '/printlabel/api/batches',
                batchDetails: '/printlabel/api/batch/',
                batchForPrinting: '/printlabel/api/batch/',
                markPrinted: '/printlabel/api/batch/',
                deleteBatch: '/printlabel/api/batch/',
                statistics: '/printlabel/api/statistics',
                groupingPreview: '/printlabel/api/grouping/preview',
                registryBatches: '/printlabel/api/registry-batches',
                rackLabelStatus: '/printlabel/api/rack-label/status'
            };

            function showError(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message,
                    confirmButtonColor: '#3b82f6'
                });
                console.error(message);
            }

            function showSuccess(message) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: message,
                    timer: 3000,
                    showConfirmButton: false
                });
                console.log(message);
            }

            function showLoading(message = 'Loading...') {
                Swal.fire({
                    title: 'Please wait...',
                    text: message,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                state.loading = true;
                console.log(message);
            }

            function hideLoading() {
                if (state.loading) {
                    Swal.close();
                    state.loading = false;
                }
            }

            // API functions
            async function fetchAvailableFiles(search = '', page = 1, options = {}) {
                const shouldShowModal = options.showModal !== false;
                if (shouldShowModal) {
                    showLoading('Loading files...');
                }
                let fallbackSuccessMessage = null;

                try {
                    const params = new URLSearchParams({
                        search: search,
                        page: page,
                        per_page: 30,
                    });

                    if (state.stFilterActive) {
                        params.append('st_filter', 'true');
                    }

                    const response = await fetch(`${API.files}?${params.toString()}`);

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Server responded with ${response.status}. ${errorText.substring(0, 120)}`);
                    }

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Unable to fetch available files.');
                    }

                    resetPreparedState();

                    state.availableFiles = Array.isArray(data.data) ? data.data : [];
                    state.labelStatistics = data.label_statistics ?? null;
                    state.currentPage = data.pagination?.current_page ?? 1;
                    state.totalPages = data.pagination?.last_page ?? 1;

                    const fallbackInfo = data.fallback && typeof data.fallback === 'object'
                        ? data.fallback
                        : null;

                    if (fallbackInfo && fallbackInfo.source === 'grouping') {
                        const rawRegistryBatch = (fallbackInfo.registry_batch ?? '').toString().trim();
                        const effectiveRegistryBatch = ((fallbackInfo.effective_registry_batch ?? rawRegistryBatch) || 'fallback-preview').toString().trim();
                        const displayRegistryBatch = rawRegistryBatch !== '' ? rawRegistryBatch : 'N/A';

                        state.batchMode = true;
                        state.registryBatchNo = effectiveRegistryBatch;
                        state.selectedFiles = state.availableFiles.map((record) => record.id);

                        const fallbackBatchModeCheckbox = document.getElementById('batchMode');
                        if (fallbackBatchModeCheckbox) {
                            fallbackBatchModeCheckbox.checked = true;
                        }

                        setRegistryBatchSelection(effectiveRegistryBatch, displayRegistryBatch);
                        toggleBatchModeUI(true);

                        fallbackSuccessMessage = `Loaded ${state.availableFiles.length} grouping record${state.availableFiles.length === 1 ? '' : 's'} from registry_batch_no ${displayRegistryBatch}.`;
                    }

                    const batchModeCheckboxEl = document.getElementById('batchMode');
                    if (batchModeCheckboxEl && !fallbackInfo) {
                        batchModeCheckboxEl.checked = !!state.batchMode;
                    }
                    toggleBatchModeUI(!!state.batchMode);

                    renderFileList();
                    updateCounts();
                    updateSelectAllCheckbox();

                    if (state.activeTab === 'preview') {
                        updateRackStatisticsDisplay(state.labelStatistics);
                    }
                } catch (error) {
                    console.error('Failed to fetch available files', error);
                    showError(`Failed to fetch files: ${error.message || error}`);
                } finally {
                    hideLoading();
                    if (fallbackSuccessMessage) {
                        showSuccess(fallbackSuccessMessage);
                    }
                }
            }

            function createLabelBatch() {
                if (state.selectedFiles.length === 0) {
                    showError('Please select at least one file');
                    return;
                }

                // For ST mode, allow any number of files. For regular mode, keep 30 file limit
                if (!state.stFilterActive && state.selectedFiles.length > 30) {
                    showError('Cannot select more than 30 files per batch');
                    return;
                }

                showLoading('Creating batch...');
                
                fetch(API.createBatch, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        source: 'file_indexings',
                        file_ids: state.selectedFiles,
                        label_format: state.selectedTemplate,
                        orientation: state.orientation,
                        batch_size: state.batchCount
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const warnings = (data.data && data.data.warnings) ? data.data.warnings : {};
                        const missing = Array.isArray(warnings.missing_references) ? warnings.missing_references : [];
                        const duplicates = Array.isArray(warnings.duplicate_references) ? warnings.duplicate_references : [];

                        if (missing.length || duplicates.length) {
                            const parts = [];
                            if (missing.length) {
                                parts.push('Unable to locate indexed files for: ' + missing.join(', '));
                            }
                            if (duplicates.length) {
                                parts.push('Duplicate entries skipped: ' + duplicates.join(', '));
                            }

                            const warningMessage = 'Error creating batch: ' + parts.join(' | ');
                            showError(warningMessage);
                        } else {
                            showSuccess('Batch ' + data.data.batch_number + ' created successfully with ' + data.data.file_count + ' files');
                        }

                        state.selectedFiles = [];
                        updateCounts();
                        renderFileList();
                        fetchAvailableFiles(); // Refresh the file list
                        fetchGeneratedBatches(); // Refresh batches
                        switchTab('generated'); // Switch to generated tab
                    } else {
                        showError(data.message);
                    }
                    hideLoading();
                })
                .catch(error => {
                    showError('Failed to create batch: ' + error.message);
                    hideLoading();
                });
            }

            function fetchGeneratedBatches(status, page, options) {
                status = status || '';
                page = page || 1;
                const settings = options || {};
                const silent = settings.silent === true;

                if (!silent) {
                    showLoading('Loading batches...');
                }
                const params = new URLSearchParams({
                    status: status,
                    page: page,
                    per_page: 20
                });
                
                fetch(API.batches + '?' + params)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            state.generatedBatches = data.data;
                            renderBatchList();
                        } else {
                            showError(data.message);
                        }
                        if (!silent) {
                            hideLoading();
                        }
                    })
                    .catch(error => {
                        showError('Failed to fetch batches: ' + error.message);
                        if (!silent) {
                            hideLoading();
                        }
                    });
            }

            function markBatchAsPrinted(batchId) {
                showLoading('Marking batch as printed...');
                
                fetch(API.markPrinted + batchId + '/print', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess('Batch marked as printed successfully');
                        fetchGeneratedBatches(); // Refresh batches
                        fetchStatistics(); // Refresh statistics
                    } else {
                        showError(data.message);
                    }
                    hideLoading();
                })
                .catch(error => {
                    showError('Failed to mark batch as printed: ' + error.message);
                    hideLoading();
                });
            }

            function deleteBatch(batchId) {
                if (!confirm('Are you sure you want to delete this batch?')) {
                    return;
                }

                showLoading('Deleting batch...');
                
                fetch(API.deleteBatch + batchId, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess('Batch deleted successfully');
                        fetchGeneratedBatches(); // Refresh batches
                        fetchStatistics(); // Refresh statistics
                    } else {
                        showError(data.message);
                    }
                    hideLoading();
                })
                .catch(error => {
                    showError('Failed to delete batch: ' + error.message);
                    hideLoading();
                });
            }

            function fetchStatistics() {
                // Build URL with ST filter if active
                let statisticsUrl = API.statistics;
                if (state.stFilterActive) {
                    const params = new URLSearchParams();
                    params.append('st_filter', 'true');
                    statisticsUrl += '?' + params.toString();
                }
                
                fetch(statisticsUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateStatistics(data.data);
                        } else {
                            console.error('Failed to fetch statistics:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Failed to fetch statistics:', error.message);
                    });
            }

            function updateStatistics(stats) {
                // Only update available files count if we're in ST mode (url=st) AND the element exists
                const availableFilesElement = document.getElementById('availableFilesCount');
                if (state.stFilterActive && availableFilesElement) {
                    availableFilesElement.textContent = stats.available_files;
                }
                if (document.getElementById('totalBatchesCount')) {
                    document.getElementById('totalBatchesCount').textContent = stats.total_batches;
                }
                if (document.getElementById('generatedBatchesCount')) {
                    document.getElementById('generatedBatchesCount').textContent = stats.generated_batches;
                }
                if (document.getElementById('printedBatchesCount')) {
                    document.getElementById('printedBatchesCount').textContent = stats.printed_batches;
                }
                if (document.getElementById('completedBatchesCount')) {
                    document.getElementById('completedBatchesCount').textContent = stats.completed_batches;
                }
            }

            function normalizeLocationValue(rawValue) {
                if (!rawValue || rawValue === 'null') {
                    return 'Shelf/Rack-N/A';
                }
                const value = String(rawValue).trim();
                if (!value) {
                    return 'Shelf/Rack-N/A';
                }
                return /^shelf\/?rack/i.test(value)
                    ? value.replace(/\s+/g, ' ')
                    : `Shelf/Rack-${value}`;
            }

            function extractShelfValue(locationText) {
                if (!locationText) {
                    return 'N/A';
                }
                const cleaned = locationText.replace(/^(Shelf\/Rack[-:\s]*)/i, '').trim();
                return cleaned || 'N/A';
            }

            function getDisplayShelfValue(primary, secondary) {
                const candidates = [primary, secondary]
                    .map((value) => (value ?? '').toString().trim())
                    .filter((value) => {
                        if (!value) {
                            return false;
                        }

                        const upper = value.toUpperCase();
                        return upper !== 'N/A' && upper !== 'SHELF/RACK-N/A' && upper !== 'NULL' && upper !== '';
                    });

                if (!candidates.length) {
                    return 'N/A';
                }

                const cleanValue = candidates[0].replace(/^(Shelf\/Rack[-:\s]*)/i, '').trim();
                return cleanValue || 'N/A';
            }

            function pickFirstNonEmptyValue(values) {
                if (!Array.isArray(values)) {
                    return '';
                }

                for (let index = 0; index < values.length; index += 1) {
                    const candidate = values[index];
                    if (candidate === undefined || candidate === null) {
                        continue;
                    }

                    const normalized = candidate.toString().trim();
                    if (normalized !== '') {
                        return normalized;
                    }
                }

                return '';
            }

            function getFileNumberSortKey(payload) {
                if (!payload || typeof payload !== 'object') {
                    return '';
                }

                const candidates = [
                    payload.primaryFileNumber,
                    payload.primary_number,
                    payload.fileNumber,
                    payload.file_number,
                    payload.originalFileNumber,
                    payload.secondaryFileNumber,
                    payload.secondary_number,
                    payload.np_fileno,
                    payload.fileno,
                ];

                const picked = pickFirstNonEmptyValue(candidates);
                return picked.toUpperCase();
            }

            function sortByFileNumber(list) {
                if (!Array.isArray(list)) {
                    return [];
                }

                if (list.length < 2) {
                    return list.slice();
                }

                const numericLocaleOptions = { numeric: true, sensitivity: 'base' };

                return list
                    .map((item, index) => ({
                        item,
                        index,
                        key: getFileNumberSortKey(item),
                    }))
                    .sort((a, b) => {
                        const aHasKey = a.key !== '';
                        const bHasKey = b.key !== '';

                        if (aHasKey && !bHasKey) {
                            return -1;
                        }

                        if (!aHasKey && bHasKey) {
                            return 1;
                        }

                        if (!aHasKey && !bHasKey) {
                            return a.index - b.index;
                        }

                        let comparison = 0;

                        if (typeof a.key.localeCompare === 'function') {
                            try {
                                comparison = a.key.localeCompare(b.key, undefined, numericLocaleOptions);
                            } catch (error) {
                                comparison = a.key < b.key ? -1 : (a.key > b.key ? 1 : 0);
                            }
                        } else {
                            comparison = a.key < b.key ? -1 : (a.key > b.key ? 1 : 0);
                        }

                        if (comparison !== 0) {
                            return comparison;
                        }

                        return a.index - b.index;
                    })
                    .map((entry) => entry.item);
            }

            function buildShelfDisplayLabel() {
                const args = Array.prototype.slice.call(arguments);
                const flattened = [];

                args.forEach((value) => {
                    if (Array.isArray(value)) {
                        value.forEach((inner) => flattened.push(inner));
                    } else {
                        flattened.push(value);
                    }
                });

                const candidates = flattened
                    .map((value) => (value ?? '').toString().trim())
                    .filter((value) => {
                        if (!value) {
                            return false;
                        }

                        const upper = value.toUpperCase();
                        return upper !== 'N/A' && upper !== 'SHELF/RACK-N/A' && upper !== 'NULL';
                    });

                if (!candidates.length) {
                    return '';
                }

                const best = candidates[0];
                const cleaned = best.replace(/^Shelf\/?Rack[-:\s]*/i, '').trim();

                return cleaned || '';
            }

            function deriveFileNumbers(file) {
                const normalize = (value) => {
                    if (value === undefined || value === null) {
                        return '';
                    }
                    return String(value).trim();
                };

                const motherNp = normalize(file?.mother_np_fileno);
                const subNp = normalize(file?.sub_np_fileno);
                const motherLegacy = normalize(file?.mother_fileno);
                const subLegacy = normalize(file?.sub_fileno);
                const stFill = normalize(file?.st_fillno);
                const indexingNumber = normalize(file?.file_number);

                const isSubApplication = Boolean(
                    file?.subapplication_id ||
                    subNp ||
                    subLegacy
                );

                const pickFirst = (...values) => values.find((value) => Boolean(value));

                let primaryNumber;
                let secondaryNumber;

                if (state.stFilterActive && isSubApplication) {
                    primaryNumber = pickFirst(
                        subLegacy,
                        motherLegacy,
                        indexingNumber,
                        subNp,
                        motherNp
                    );

                    secondaryNumber = pickFirst(
                        subNp,
                        motherNp,
                        stFill,
                        motherLegacy,
                        subLegacy
                    );
                } else {
                    primaryNumber = pickFirst(
                        motherNp,
                        subNp,
                        indexingNumber,
                        motherLegacy,
                        subLegacy
                    );

                    secondaryNumber = pickFirst(
                        motherLegacy,
                        subLegacy,
                        stFill
                    );
                }

                if (secondaryNumber && secondaryNumber === primaryNumber) {
                    const fallbackCandidates = (state.stFilterActive && isSubApplication)
                        ? [
                            motherNp,
                            subNp,
                            stFill,
                            motherLegacy,
                            subLegacy,
                            indexingNumber,
                        ]
                        : [
                            motherLegacy,
                            subLegacy,
                            stFill,
                            motherNp,
                            subNp,
                        ];

                    secondaryNumber = pickFirst(
                        ...fallbackCandidates.filter((value) => value && value !== primaryNumber)
                    );
                }

                const isSTContext = Boolean(
                    state.stFilterActive ||
                    motherNp ||
                    subNp ||
                    motherLegacy ||
                    subLegacy ||
                    stFill
                );

                return {
                    primaryNumber: primaryNumber || indexingNumber || null,
                    secondaryNumber: secondaryNumber || null,
                    isSTContext,
                };
            }

            function getSelectedFilesData() {
                return state.selectedFiles
                    .map((fileId) => state.availableFiles.find((f) => f.id === fileId))
                    .filter((file) => file !== undefined);
            }

            function collectBaseLabelEntries() {
                if (Array.isArray(state.preparedBaseEntries) && state.preparedBaseEntries.length) {
                    return sortByFileNumber(state.preparedBaseEntries.map((entry) => ({ ...entry })));
                }

                const entries = [];

                if (state.batchMode && state.selectedFiles.length === 0) {
                    const total = Math.max(1, parseInt(state.batchCount || 0, 10));
                    for (let i = 0; i < total; i++) {
                        const fileNumber = generateBatchFileNumber(i);
                        const rawShelf = `Shelf/Rack-${String.fromCharCode(65 + Math.floor(i / 10))}${((i % 10) + 1)
                            .toString()
                            .padStart(2, '0')}`;
                        const shelfLabel = normalizeLocationValue(rawShelf);
                        const shelfValue = extractShelfValue(shelfLabel);
                        const trackingId = `TRK-${fileNumber}`;
                        const qrValue = trackingId;

                        entries.push({
                            id: `batch-${fileNumber}`,
                            fileNumber,
                            primaryFileNumber: fileNumber,
                            secondaryFileNumber: null,
                            isSTFile: false,
                            shelfLabel,
                            shelfValue,
                            trackingId,
                            fileTitle: '',
                            qrValue,
                        });
                    }
                    return sortByFileNumber(entries);
                }

                const selectedFiles = getSelectedFilesData();
                const fallbackFullLabel = (state.fullLabel || updateFullLabelDisplay() || '').toString().trim();
                const fallbackNormalizedShelf = fallbackFullLabel ? normalizeLocationValue(fallbackFullLabel) : '';
                selectedFiles.forEach((file) => {
                    const rawFullLabel = (file.shelf_full_label ?? '').toString().trim();

                    const primarySources = [
                        rawFullLabel,
                        file.shelf_value,
                        file.shelf_label,
                        file.shelf_location
                    ];
                    const secondarySources = [
                        file.grouping_shelf_rack,
                        file.grouping_registry_batch_no || file.grouping_sys_batch_no
                    ];

                    const primarySource = primarySources.find((value) => value);
                    const secondarySource = secondarySources.find((value) => value);

                    const normalizedPrimaryShelf = primarySource
                        ? normalizeLocationValue(primarySource)
                        : '';
                    const normalizedSecondaryShelf = secondarySource
                        ? normalizeLocationValue(secondarySource)
                        : '';
                    const shelfLabel = normalizedPrimaryShelf
                        || normalizedSecondaryShelf
                        || fallbackNormalizedShelf
                        || 'Shelf/Rack-N/A';
                    let shelfValue = rawFullLabel
                        || getDisplayShelfValue(
                            normalizedPrimaryShelf,
                            normalizedSecondaryShelf,
                            fallbackFullLabel
                        );
                    if (!shelfValue || shelfValue === 'N/A') {
                        shelfValue = fallbackFullLabel || 'N/A';
                    }
                    const trackingIdSource = (file.tracking_id ?? '').toString().trim();
                    const fallbackTrackingId = file.batch_no
                        ? `BATCH-${file.batch_no}`
                        : file.id
                            ? `IDX-${file.id}`
                            : `IDX-${Math.random().toString(36).slice(2, 8)}`;
                    const trackingId = (trackingIdSource || fallbackTrackingId || '').toString();

                    let qrValue = trackingId;
                    if (file.qr_code_data) {
                        if (typeof file.qr_code_data === 'string') {
                            qrValue = file.qr_code_data;
                        } else {
                            try {
                                qrValue = String(file.qr_code_data.tracking_id || trackingId);
                            } catch (error) {
                                qrValue = trackingId;
                            }
                        }
                    }

                    const { primaryNumber, secondaryNumber, isSTContext } = deriveFileNumbers(file);
                    const displayPrimary = primaryNumber || file.file_number || '';
                    const displaySecondary = secondaryNumber;

                    entries.push({
                        id: file.id,
                        fileNumber: displayPrimary,
                        primaryFileNumber: primaryNumber || null,
                        secondaryFileNumber: displaySecondary || null,
                        originalFileNumber: file.file_number,
                        isSTFile: isSTContext,
                        shelfLabel,
                        shelfValue,
                        trackingId,
                        fileTitle: file.file_title || '',
                        qrValue,
                    });
                });

                return sortByFileNumber(entries);
            }

            function buildLabelPayload() {
                const baseEntries = collectBaseLabelEntries();
                const copies = Math.max(1, parseInt(state.copies || 1, 10));
                const labels = [];

                baseEntries.forEach((entry) => {
                    for (let copyIndex = 0; copyIndex < copies; copyIndex++) {
                        labels.push({
                            file_number: entry.fileNumber,
                            primary_number: entry.primaryFileNumber || entry.fileNumber,
                            secondary_number: entry.secondaryFileNumber,
                            is_st: entry.isSTFile,
                            shelf_label: entry.shelfLabel,
                            shelf_value: entry.shelfValue,
                            file_title: entry.fileTitle,
                            tracking_id: entry.trackingId,
                            qr_value: entry.qrValue,
                            copy_number: copyIndex + 1,
                            copy_total: copies,
                        });
                    }
                });

                const templateSelect = document.getElementById('labelTemplate');
                const sizeSelect = document.getElementById('labelSize');

                const templateLabel = templateSelect
                    ? templateSelect.selectedOptions[0].text.split(' - ')[0]
                    : state.selectedTemplate;

                const sizeLabel = sizeSelect ? sizeSelect.selectedOptions[0].text : state.labelSize;

                const pages = labels.length === 0 ? 0 : Math.ceil(labels.length / 30);

                return {
                    baseEntries,
                    labels,
                    previewEntries: baseEntries.slice(0, 12),
                    summary: {
                        files: baseEntries.length,
                        copies,
                        totalLabels: labels.length,
                        pages,
                        templateLabel,
                        sizeLabel,
                        formatLabel: state.labelFormat === 'barcode' ? 'Barcode' : 'QR Code',
                    },
                    meta: {
                        template: state.selectedTemplate,
                        format: state.labelFormat,
                        orientation: state.orientation,
                        stFilter: state.stFilterActive,
                        batchMode: state.batchMode,
                        batchId: state.currentBatchId || null,
                        generatedAt: new Date().toISOString(),
                        labelStatistics: state.labelStatistics,
                    },
                };
            }

            async function persistBatchForPrinting() {
                const selectedRecords = getSelectedFilesData();

                if (!state.batchMode && selectedRecords.length === 0) {
                    throw new Error('Please select at least one file before printing.');
                }

                const source = state.batchMode ? 'grouping' : 'file_indexings';
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const payload = {
                    source,
                    label_format: state.selectedTemplate,
                    orientation: state.orientation,
                };

                if (source === 'grouping') {
                    const effectiveBatchSize = Number.isInteger(parseInt(state.batchCount, 10))
                        ? Math.max(1, Math.min(parseInt(state.batchCount, 10), 100))
                        : selectedRecords.length;

                    payload.batch_size = effectiveBatchSize;
                    payload.registry = state.registryId;
                    payload.registry_batch_no = (state.registryBatchNo || '').toString().trim();
                    payload.range_key = state.rangeKey;
                    payload.range_start = state.rangeStart;
                    payload.range_end = state.rangeEnd;
                    payload.full_label = state.fullLabel || updateFullLabelDisplay();
                    payload.rack_primary = state.rackPrimary;
                    payload.rack_secondary = state.rackSecondary || null;
                    payload.shelf = parseInt(state.shelfNumber, 10);

                    if (!payload.registry_batch_no) {
                        throw new Error('Select a registry batch number before printing.');
                    }
                    payload.records = selectedRecords.map((record, index) => ({
                        id: Number(record.id),
                        file_number: record.file_number || record.primaryFileNumber || null,
                        file_title: record.file_title || '',
                        plot_number: record.plot_number || null,
                        district: record.district || null,
                        lga: record.lga || null,
                        registry_batch_no: record.registry_batch_no || state.registryBatchNo || null,
                        land_use_type: record.land_use_type || record.landuse || null,
                        tracking_id: record.tracking_id || null,
                        awaiting_fileno: record.awaiting_fileno || null,
                        indexing_mls_fileno: record.indexing_mls_fileno || null,
                        shelf_label: record.shelf_label || record.shelf_location || null,
                        label_position: index + 1,
                    }));

                    if (!payload.records.length) {
                        throw new Error('No grouping records available to create a batch.');
                    }
                } else {
                    payload.batch_size = selectedRecords.length;
                    payload.file_ids = selectedRecords.map((record) => Number(record.id));

                    if (!payload.file_ids.length) {
                        throw new Error('Please select at least one file before printing.');
                    }
                }

                showLoading('Saving batch details...');

                try {
                    // Set a timeout for the request (60 seconds)
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 60000);

                    const response = await fetch(API.createBatch, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(payload),
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Server error:', errorText);
                        throw new Error(`Server error (${response.status}): ${errorText.substring(0, 200)}`);
                    }

                    const data = await response.json();
                    hideLoading();

                    if (!data.success) {
                        throw new Error(data.message || 'Unable to create label batch.');
                    }

                    return data;
                } catch (error) {
                    hideLoading();
                    if (error.name === 'AbortError') {
                        throw new Error('Request timed out after 60 seconds. The batch may still be processing.');
                    }
                    console.error('Error in persistBatchForPrinting:', error);
                    throw error;
                }
            }

            function embedQrImages(payload) {
                if (!payload || !Array.isArray(payload.labels) || payload.labels.length === 0) {
                    return payload;
                }

                if (payload.meta && payload.meta.qrImagesEmbedded) {
                    return payload;
                }

                if (typeof QRious === 'undefined') {
                    return payload;
                }

                payload.labels.forEach((label) => {
                    if (label.qr_image) {
                        return;
                    }

                    let qrValue = label.qr_value;
                    if (qrValue && typeof qrValue !== 'string') {
                        qrValue = String(qrValue);
                    }

                    if (!qrValue) {
                        return;
                    }

                    try {
                        const qrString = String(qrValue).trim();
                        if (!qrString) {
                            return;
                        }
                        const qr = new QRious({
                            value: qrString,
                            size: 240,
                            level: 'M',
                            background: '#ffffff',
                            foreground: '#000000',
                        });
                        label.qr_image = qr.toDataURL();
                    } catch (error) {
                        console.warn('Failed to embed QR image for label', label.file_number, error);
                    }
                });

                if (payload.meta) {
                    payload.meta.qrImagesEmbedded = true;
                } else {
                    payload.meta = { qrImagesEmbedded: true };
                }

                return payload;
            }

            function renderEmptyPreview(previewContent) {
                previewContent.innerHTML = `
                    <div class="preview-empty">
                        <div class="preview-empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="16" rx="2" ry="2"></rect>
                                <path d="M3 10h18"></path>
                                <path d="M8 6v4"></path>
                            </svg>
                        </div>
                        <p class="text-sm">No files selected yet.</p>
                        <p class="text-xs text-slate-500">Pick some files and configure the settings to see the labels here.</p>
                    </div>
                `;
            }

            function renderPreview(payload) {
                const previewContent = document.getElementById('previewContent');
                if (!previewContent) {
                    return;
                }

                if (!payload.baseEntries.length) {
                    renderEmptyPreview(previewContent);
                    return;
                }

                previewContent.innerHTML = '';

                const grid = document.createElement('div');
                grid.className = 'label-preview-grid';

                payload.previewEntries.forEach((entry, index) => {
                    const card = document.createElement('div');
                    card.className = 'label-preview-card';

                    const qrWrap = document.createElement('div');
                    qrWrap.className = 'label-preview-qr';

                    const canvas = document.createElement('canvas');
                    canvas.width = 180;
                    canvas.height = 180;
                    qrWrap.appendChild(canvas);

                    const meta = document.createElement('div');
                    meta.className = 'label-preview-meta';

                    const primaryLine = document.createElement('div');
                    primaryLine.className = 'label-preview-file';
                    primaryLine.textContent = entry.primaryFileNumber || entry.fileNumber || '—';
                    meta.appendChild(primaryLine);

                    if (entry.secondaryFileNumber && (entry.isSTFile || entry.secondaryFileNumber !== entry.primaryFileNumber)) {
                        const secondaryLine = document.createElement('div');
                        secondaryLine.className = 'label-preview-file label-preview-file--secondary';
                        secondaryLine.textContent = entry.secondaryFileNumber;
                        meta.appendChild(secondaryLine);
                    }

                    const shelfDisplayValue = buildShelfDisplayLabel(entry.shelfLabel, entry.shelfValue);
                    const shelfLine = document.createElement('div');
                    shelfLine.className = 'label-preview-location';
                    shelfLine.textContent = shelfDisplayValue
                        ? `Shelf/Rack: ${shelfDisplayValue}`
                        : 'Shelf/Rack: N/A';
                    meta.appendChild(shelfLine);

                    card.appendChild(qrWrap);
                    card.appendChild(meta);
                    grid.appendChild(card);

                    setTimeout(() => {
                        if (typeof QRious !== 'undefined') {
                            try {
                                new QRious({
                                    element: canvas,
                                    value: String(entry.qrValue || '').trim(),
                                    size: 180,
                                    level: 'M',
                                    background: '#ffffff',
                                    foreground: '#111827',
                                });
                            } catch (error) {
                                console.error('QR preview error:', error);
                                const ctx = canvas.getContext('2d');
                                ctx.fillStyle = '#f3f4f6';
                                ctx.fillRect(0, 0, 180, 180);
                                ctx.fillStyle = '#9ca3af';
                                ctx.textAlign = 'center';
                                ctx.font = '12px Arial';
                                ctx.fillText('QR error', 90, 95);
                            }
                        }
                    }, 40 + index * 20);
                });

                previewContent.appendChild(grid);

                if (payload.baseEntries.length > payload.previewEntries.length) {
                    const overflow = document.createElement('p');
                    overflow.className = 'text-xs text-gray-500 mt-3 text-center';
                    overflow.textContent = `Showing ${payload.previewEntries.length} of ${payload.baseEntries.length} labels. All labels will print.`;
                    previewContent.appendChild(overflow);
                }
            }

            function updateRackStatisticsDisplay(statistics) {
                const headlineEl = document.getElementById('rackStatsHeadline');
                const contentEl = document.getElementById('rackStatsContent');

                if (!headlineEl || !contentEl) {
                    return;
                }

                if (!statistics || typeof statistics !== 'object') {
                    headlineEl.textContent = '';
                    contentEl.innerHTML = '<p class="text-sm text-slate-500">Rack usage statistics will appear once labels are generated.</p>';
                    return;
                }

                const totalUsed = Number(statistics.total_used ?? 0);
                const totalCapacity = Number(statistics.total_capacity ?? 0);
                const totalRemaining = Number(statistics.total_remaining ?? 0);

                if (totalCapacity > 0) {
                    headlineEl.textContent = `Used ${totalUsed} of ${totalCapacity} slots, ${totalRemaining} remaining`;
                } else {
                    headlineEl.textContent = '';
                }

                const labels = Array.isArray(statistics.labels) ? statistics.labels : [];

                if (!labels.length) {
                    const nextLabel = statistics.next_label;
                    const fallback = nextLabel && nextLabel.label
                        ? `Next available rack label ${nextLabel.label} has ${Number(nextLabel.capacity ?? statistics.label_capacity ?? 0)} free slots.`
                        : 'Rack usage statistics will appear once labels are generated.';
                    contentEl.innerHTML = `<p class="text-sm text-slate-500">${fallback}</p>`;
                    return;
                }

                const fragment = document.createDocumentFragment();

                labels.forEach((label) => {
                    const capacity = Number(label.capacity ?? statistics.label_capacity ?? 0);
                    const used = Number(label.used ?? 0);
                    const remaining = Number(label.remaining ?? Math.max(0, capacity - used));
                    const safeCapacity = capacity > 0 ? capacity : 1;
                    const percent = Math.max(0, Math.min(100, Math.round((used / safeCapacity) * 100)));

                    const wrapper = document.createElement('div');
                    wrapper.className = 'flex flex-col sm:flex-row sm:items-center sm:justify-between border border-slate-200 rounded-md bg-white p-3';

                    const textWrap = document.createElement('div');
                    textWrap.className = 'flex flex-col';

                    const title = document.createElement('span');
                    title.className = 'text-sm font-medium text-slate-700';
                    const labelName = (label.label || '').toString().trim();
                    title.textContent = labelName || (label.label_id ? `Label ${label.label_id}` : 'Rack Label');
                    textWrap.appendChild(title);

                    const details = document.createElement('span');
                    details.className = 'text-xs text-slate-500 mt-1';
                    details.textContent = `${used} used, ${remaining} remaining of ${capacity}`;
                    textWrap.appendChild(details);

                    if (label.status && label.status !== 'active') {
                        const status = document.createElement('span');
                        status.className = 'text-xs text-amber-600 mt-1';
                        status.textContent = String(label.status).toUpperCase();
                        textWrap.appendChild(status);
                    }

                    wrapper.appendChild(textWrap);

                    const barOuter = document.createElement('div');
                    barOuter.className = 'w-full sm:w-40 h-2 bg-slate-200 rounded-full overflow-hidden mt-3 sm:mt-0 sm:ml-4';

                    const barInner = document.createElement('div');
                    barInner.className = 'h-full bg-blue-500';
                    barInner.style.width = `${percent}%`;
                    barOuter.appendChild(barInner);

                    wrapper.appendChild(barOuter);

                    fragment.appendChild(wrapper);
                });

                if (statistics.next_label && statistics.next_label.label) {
                    const nextNotice = document.createElement('p');
                    nextNotice.className = 'text-xs text-slate-500 pt-2';
                    const nextCapacity = Number(statistics.next_label.capacity ?? statistics.label_capacity ?? 0);
                    nextNotice.textContent = `Next available label ${statistics.next_label.label} has ${nextCapacity} slots ready.`;
                    fragment.appendChild(nextNotice);
                }

                contentEl.innerHTML = '';
                contentEl.appendChild(fragment);
            }

            function updatePrintSummaryCard(payload) {
                const printSummary = document.getElementById('printSummary');
                if (!printSummary) {
                    return;
                }

                if (!payload.baseEntries.length) {
                    printSummary.innerHTML = '<p class="preview-note">Once you select files, we’ll summarise the number of labels, copies, and template details here.</p>';
                    return;
                }

                const { files, copies, totalLabels, pages, templateLabel, sizeLabel, formatLabel } = payload.summary;
                const printedOn = new Date().toLocaleString();

                printSummary.innerHTML = `
                    <div class="flex justify-between">
                        <span class="text-sm">Labels selected:</span>
                        <span class="text-sm font-medium">${files}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Copies per label:</span>
                        <span class="text-sm font-medium">${copies}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Total labels to print:</span>
                        <span class="text-sm font-medium">${totalLabels}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Expected pages:</span>
                        <span class="text-sm font-medium">${pages || 1}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Template:</span>
                        <span class="text-sm font-medium">${templateLabel}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Label size:</span>
                        <span class="text-sm font-medium">${sizeLabel}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Format:</span>
                        <span class="text-sm font-medium">${formatLabel}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Prepared on:</span>
                        <span class="text-sm font-medium">${printedOn}</span>
                    </div>
                `;
            }

            // Rendering functions
            function renderFileList() {
                const fileListContent = document.getElementById("fileListContent");
                
                if (state.availableFiles.length === 0) {
                    const emptyTitle = state.batchMode
                        ? 'Load records to begin'
                        : 'No files available for label printing';
                    const emptyDescription = state.batchMode
                        ? 'Select your registry parameters and use "Load Records" to populate this list.'
                        : 'Files need to have a batch number and not already have labels printed';

                    fileListContent.innerHTML = `
                        <div class="p-8 text-center text-gray-500">
                            <div class="mb-2">
                                <i data-lucide="file-text" class="h-8 w-8 mx-auto text-gray-400"></i>
                            </div>
                            <p>${emptyTitle}</p>
                            <p class="text-sm">${emptyDescription}</p>
                        </div>
                    `;
                    lucide.createIcons();
                    return;
                }

                const filteredFiles = filterFiles();
                fileListContent.innerHTML = filteredFiles
                    .map((file) => {
                        // Handle shelf/rack data from multiple sources
                        const rawFullLabel = (file.shelf_full_label ?? '').toString().trim();

                        const shelfSources = [
                            file.shelf_value,
                            file.shelf_label,
                            file.shelf_location,
                            file.grouping_shelf_rack,
                            file.grouping_registry_batch_no || file.grouping_sys_batch_no
                        ].filter(value => value && value !== 'null' && value !== '');

                        const normalizedPrimaryShelf = shelfSources.length > 0 
                            ? normalizeLocationValue(shelfSources[0])
                            : '';
                        
                        const normalizedSecondaryShelf = shelfSources.length > 1
                            ? normalizeLocationValue(shelfSources[1])
                            : '';

                        const rawPrimaryShelf = shelfSources.length > 0 ? shelfSources[0] : '';
                        const rawSecondaryShelf = shelfSources.length > 1 ? shelfSources[1] : '';

                        const locationDisplay = rawFullLabel
                            || buildShelfDisplayLabel(
                                rawPrimaryShelf,
                                rawSecondaryShelf,
                                normalizedPrimaryShelf,
                                normalizedSecondaryShelf
                            );

                        const trackingBadge = file.tracking_id
                            ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">Tracking: ${file.tracking_id}</span>`
                            : '';
                        const awaitingBadge = file.awaiting_fileno
                            ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">Awaiting: ${file.awaiting_fileno}</span>`
                            : '';
                        const indexingBadge = file.indexing_mls_fileno
                            ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Indexing: ${file.indexing_mls_fileno}</span>`
                            : '';
                        const landUseBadge = file.land_use_type
                            ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">${file.land_use_type}</span>`
                            : '';
                        const metaBadge = file.batch_no
                            ? `<span class="text-xs text-gray-500">Batch: ${file.batch_no}</span>`
                            : '';

                        const detailBadges = [
                            trackingBadge,
                            awaitingBadge,
                            indexingBadge,
                            metaBadge,
                        ].filter(Boolean).join(' ');

                        const isGroupingRecord = Boolean(
                            file.grouping_registry_batch_no ||
                            file.grouping_sys_batch_no ||
                            (state.batchMode && !file.batch_no)
                        );

                        const statusBadgeClass = isGroupingRecord
                            ? 'bg-amber-100 text-amber-800'
                            : 'bg-green-100 text-green-800';
                        const statusBadgeIcon = isGroupingRecord ? 'layers' : 'check-circle';
                        const statusBadgeLabel = isGroupingRecord ? 'Grouping' : 'Indexed';

                        return `
                    <div class="flex items-center p-4">
                        <input type="checkbox" id="${
                            file.id
                        }" class="file-checkbox mr-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" ${
                            state.selectedFiles.includes(file.id) ? "checked" : ""
                        }>
                        <div class="flex flex-1 items-center gap-3">
                            <i data-lucide="file-text" class="h-8 w-8 text-blue-500"></i>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-blue-600">${
                                        file.file_number
                                    }</p>
                                    ${landUseBadge}
                                </div>
                                <p class="text-sm text-gray-600 mt-1">${
                                    file.file_title || 'No title'
                                }</p>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    ${detailBadges}
                                    <span class="text-xs text-gray-500">Plot: ${file.plot_number || 'N/A'}</span>
                                    <span class="text-xs text-gray-500">District: ${file.district || 'N/A'}</span>
                                    <span class="text-xs text-gray-500">LGA: ${file.lga || 'N/A'}</span>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBadgeClass}">
                                <i data-lucide="${statusBadgeIcon}" class="h-3 w-3 mr-1"></i>
                                ${statusBadgeLabel}
                            </span>
                        </div>
                    </div>
                `;
                    })
                    .join("");

                // Re-initialize icons
                lucide.createIcons();

                // Add event listeners to checkboxes
                document.querySelectorAll(".file-checkbox").forEach((checkbox) => {
                    checkbox.addEventListener("change", function () {
                        const fileId = parseInt(this.id);
                        if (this.checked) {
                            // For ST mode, remove the file limit. For regular mode, keep 30 file limit
                            if (!state.stFilterActive && state.selectedFiles.length >= 30) {
                                this.checked = false;
                                showError('Cannot select more than 30 files per batch');
                                return;
                            }
                            if (!state.selectedFiles.includes(fileId)) {
                                state.selectedFiles.push(fileId);
                            }
                        } else {
                            state.selectedFiles = state.selectedFiles.filter(
                                (id) => id !== fileId
                            );
                        }
                        resetPreparedState();
                        updateCounts();
                        updateSelectAllCheckbox();
                    });
                });
            }

            function renderBatchList() {
                const batchListContent = document.getElementById('batchListContent');
                
                if (state.generatedBatches.length === 0) {
                    batchListContent.innerHTML = `
                        <div class="p-8 text-center text-gray-500">
                            <div class="mb-2">
                                <i data-lucide="package" class="h-8 w-8 mx-auto text-gray-400"></i>
                            </div>
                            <p>No batches generated yet</p>
                            <p class="text-sm">Create your first batch in the "Select Files" tab</p>
                        </div>
                    `;
                    lucide.createIcons();
                    return;
                }

                let html = '';
                const statusColors = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'generated': 'bg-blue-100 text-blue-800',
                    'printed': 'bg-green-100 text-green-800',
                    'completed': 'bg-gray-100 text-gray-800'
                };
                
                for (let i = 0; i < state.generatedBatches.length; i++) {
                    const batch = state.generatedBatches[i];
                    const statusClass = statusColors[batch.status] || 'bg-gray-100 text-gray-800';
                    const createdDate = new Date(batch.created_at).toLocaleDateString();
                    const creatorName = batch.creator ? batch.creator.name : 'Unknown';
                    const statusCapitalized = batch.status.charAt(0).toUpperCase() + batch.status.slice(1);
                    
                    html += `<div class="p-3 grid grid-cols-7 gap-4 hover:bg-gray-50">
                        <div class="font-medium">${batch.batch_number}</div>
                        <div class="text-sm">${createdDate}</div>
                        <div class="text-sm">${batch.batch_items ? batch.batch_items.length : 0}/${batch.batch_size}</div>
                        <div class="text-sm">${batch.label_format}</div>
                        <div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                ${statusCapitalized}
                            </span>
                        </div>
                        <div class="text-sm">${creatorName}</div>
                        <div class="flex gap-1">
                            <button onclick="viewBatchDetails(${batch.id})" class="p-1 text-blue-600 hover:text-blue-800" title="View Details">
                                <i data-lucide="eye" class="h-4 w-4"></i>
                            </button>`;
                    
                    if (batch.status === 'generated') {
                        html += `<button onclick="printBatchLabels(${batch.id})" class="p-1 text-green-600 hover:text-green-800" title="Print Labels">
                                    <i data-lucide="printer" class="h-4 w-4"></i>
                                </button>`;
                    }
                    
                    if (batch.status !== 'printed' && batch.status !== 'completed') {
                        html += `<button onclick="deleteBatch(${batch.id})" class="p-1 text-red-600 hover:text-red-800" title="Delete Batch">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>`;
                    }
                    
                    html += `</div>
                    </div>`;
                }
                
                batchListContent.innerHTML = html;
                lucide.createIcons();
            }

            function viewBatchDetails(batchId) {
                showLoading('Loading batch details...');
                
                fetch(API.batchDetails + batchId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayBatchDetailsModal(data.data);
                        } else {
                            showError(data.message);
                        }
                        hideLoading();
                    })
                    .catch(error => {
                        showError('Failed to fetch batch details: ' + error.message);
                        hideLoading();
                    });
            }

            function displayBatchDetailsModal(batch) {
                const modalHTML = `
                    <div id="batchDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                            <div class="mt-3">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Batch Details: ${batch.batch_number}</h3>
                                    <button onclick="closeBatchDetailsModal()" class="text-gray-400 hover:text-gray-600">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Batch Number</p>
                                        <p class="text-sm text-gray-900">${batch.batch_number}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Status</p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            ${batch.status.charAt(0).toUpperCase() + batch.status.slice(1)}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Label Format</p>
                                        <p class="text-sm text-gray-900">${batch.label_format}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Created</p>
                                        <p class="text-sm text-gray-900">${new Date(batch.created_at).toLocaleDateString()}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Files Count</p>
                                        <p class="text-sm text-gray-900">${batch.batch_items ? batch.batch_items.length : 0}/${batch.batch_size}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Created By</p>
                                        <p class="text-sm text-gray-900">${batch.creator ? batch.creator.name : 'Unknown'}</p>
                                    </div>
                                </div>

                                ${batch.batch_items && batch.batch_items.length > 0 ? `
                                    <div class="mb-4">
                                        <h4 class="text-md font-medium text-gray-900 mb-2">Files in this Batch</h4>
                                        <div class="max-h-60 overflow-y-auto border rounded-md">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">File Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    ${batch.batch_items.map(item => `
                                                        <tr>
                                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">${item.file_number}</td>
                                                            <td class="px-4 py-2 text-sm text-gray-500">${item.file_title || 'No title'}</td>
                                                            <td class="px-4 py-2 text-sm text-gray-500">${item.shelf_location || 'N/A'}</td>
                                                            <td class="px-4 py-2 text-sm">
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${item.is_printed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                                                    ${item.is_printed ? 'Printed' : 'Pending'}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ` : ''}

                                <div class="flex justify-end space-x-3">
                                    ${batch.status === 'generated' ? `
                                        <button onclick="markBatchAsPrinted(${batch.id}); closeBatchDetailsModal();" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                                            Mark as Printed
                                        </button>
                                    ` : ''}
                                    ${batch.status !== 'printed' && batch.status !== 'completed' ? `
                                        <button onclick="deleteBatch(${batch.id}); closeBatchDetailsModal();" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">
                                            Delete Batch
                                        </button>
                                    ` : ''}
                                    <button onclick="closeBatchDetailsModal()" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHTML);
                lucide.createIcons();
            }

            function closeBatchDetailsModal() {
                const modal = document.getElementById('batchDetailsModal');
                if (modal) {
                    modal.remove();
                }
            }

            function printBatchLabels(batchId) {
                showLoading('Loading batch for printing...');
                
                fetch(API.batchForPrinting + batchId + '/print')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Load the batch data into the state for printing
                            const batch = data.data.batch;
                            const files = data.data.files;
                            
                            resetPreparedState();

                            // Clear current selection and load batch files
                            state.availableFiles = files; // Set the files as available for preview
                            state.selectedFiles = files.map(file => file.id);
                            state.selectedTemplate = batch.label_format;
                            state.labelFormat = batch.label_format === 'qr_code' ? 'qrcode' : 'barcode';
                            const requestedOrientation = (batch.orientation || 'portrait').toLowerCase();
                            state.orientation = requestedOrientation === 'portrait' ? 'portrait' : 'portrait';
                            state.batchMode = false; // We're printing existing files, not generating new ones

                            state.labelStatistics = data.data && data.data.label_statistics
                                ? data.data.label_statistics
                                : null;
                            if (data.data?.rack_label_status) {
                                state.rackLabelStatus = data.data.rack_label_status;
                                updateRackLabelStatusDisplay();
                            }

                            const signature = computePreparationSignature();
                            const { records, baseEntries } = buildPreparedEntriesFromItems(files);

                            state.preparedRecords = records;
                            state.preparedBaseEntries = baseEntries;
                            state.previewPrepared = true;
                            state.preparedSignature = signature;
                            state.preparedBatchResponse = {
                                success: true,
                                data: {
                                    batch_id: batchId,
                                    batch_number: batch.batch_number,
                                    file_count: files.length,
                                    source: 'existing',
                                    registry_batch_no: batch.registry_batch_no ?? batch.sys_batch_no ?? null,
                                    warnings: [],
                                    label_items: files,
                                    label_statistics: state.labelStatistics,
                                },
                            };
                            state.currentBatchId = batchId;
                            
                            // Update UI elements
                            document.getElementById('labelTemplate').value = batch.label_format;
                            document.querySelector(`[data-format="${state.labelFormat}"]`).click();
                            const orientationOption = document.querySelector(`[data-orientation="${state.orientation}"]`);
                            if (orientationOption && orientationOption.dataset.disabled !== 'true') {
                                orientationOption.click();
                            } else {
                                const portraitOption = document.querySelector('[data-orientation="portrait"]');
                                if (portraitOption) {
                                    state.orientation = 'portrait';
                                    portraitOption.click();
                                }
                            }
                            
                            // Switch to preview tab to show the labels ready for printing
                            switchTab('preview');
                            
                            showSuccess(`Loaded batch ${batch.batch_number} with ${files.length} files for printing`);
                            
                            // Store the batch ID for when we actually print (already set above)
                        } else {
                            showError(data.message);
                        }
                        hideLoading();
                    })
                    .catch(error => {
                        showError('Failed to load batch for printing: ' + error.message);
                        hideLoading();
                    });
            }

            // Make functions globally accessible
            window.viewBatchDetails = viewBatchDetails;
            window.printBatchLabels = printBatchLabels;
            window.markBatchAsPrinted = markBatchAsPrinted;
            window.deleteBatch = deleteBatch;
            window.closeBatchDetailsModal = closeBatchDetailsModal;

            // Utility functions
            function generateBatchFileNumber(index) {
                const fileNumber = (state.batchStartNumber + index)
                    .toString()
                    .padStart(4, "0");
                return fileNumber;
            }

            function updateCounts() {
                document.getElementById("selectedFilesCount").textContent = state.selectedFiles.length;
                document.getElementById(
                    "selectionStatus"
                ).textContent = `${state.selectedFiles.length} of ${state.availableFiles.length} selected`;
                
                // Update button states based on selection
                updateButtonStates();
            }

            function updateButtonStates() {
                const selectedCount = state.selectedFiles.length;
                
                // Dynamic limits based on mode - ST mode has no upper limit
                const maxFiles = state.stFilterActive ? 999999 : 30; // Effectively unlimited for ST mode
                const minFiles = 1;
                
                const isValidSelection = selectedCount >= minFiles && selectedCount <= maxFiles;
                const printBtn = document.getElementById("printBtn");
                const continueToSettingsBtn = document.getElementById("continueToSettingsBtn");
                
                // Update selection feedback with mode-specific messaging
                const selectionStatus = document.getElementById("selectionStatus");
                if (selectionStatus) {
                    if (state.stFilterActive) {
                        selectionStatus.textContent = `${selectedCount} files selected`;
                        selectionStatus.classList.remove("text-red-600"); // No limits for ST
                    } else {
                        selectionStatus.textContent = `${selectedCount} of max ${maxFiles} files selected`;
                        if (selectedCount > maxFiles) {
                            selectionStatus.classList.add("text-red-600");
                            selectionStatus.textContent += ` (exceeds limit!)`;
                        } else {
                            selectionStatus.classList.remove("text-red-600");
                        }
                    }
                }
                
                // Enable buttons based on valid selection
                if (printBtn) {
                    printBtn.disabled = !isValidSelection;
                    if (isValidSelection) {
                        printBtn.classList.remove("opacity-50", "cursor-not-allowed");
                        printBtn.classList.add("hover:bg-blue-700");
                    } else {
                        printBtn.classList.add("opacity-50", "cursor-not-allowed");
                        printBtn.classList.remove("hover:bg-blue-700");
                    }
                }
                
                if (continueToSettingsBtn) {
                    continueToSettingsBtn.disabled = !isValidSelection;
                    if (isValidSelection) {
                        continueToSettingsBtn.classList.remove("opacity-50", "cursor-not-allowed");
                        continueToSettingsBtn.classList.add("hover:bg-blue-700");
                    } else {
                        continueToSettingsBtn.classList.add("opacity-50", "cursor-not-allowed");
                        continueToSettingsBtn.classList.remove("hover:bg-blue-700");
                    }
                }
                
                // Update tab accessibility
                updateTabAccessibility();

                if (state.activeTab === 'preview') {
                    refreshPreview(true);
                }
            }

            function filterFiles() {
                return state.availableFiles.filter(
                    (file) =>
                        !state.searchTerm ||
                        file.file_number.toLowerCase().includes(state.searchTerm.toLowerCase()) ||
                        (file.file_title && file.file_title.toLowerCase().includes(state.searchTerm.toLowerCase())) ||
                        (file.plot_number && file.plot_number.toLowerCase().includes(state.searchTerm.toLowerCase())) ||
                        (file.district && file.district.toLowerCase().includes(state.searchTerm.toLowerCase())) ||
                        (file.lga && file.lga.toLowerCase().includes(state.searchTerm.toLowerCase()))
                );
            }

            function updateTabAccessibility() {
                const selectedCount = state.selectedFiles.length;
                // ST mode has no upper limit, regular mode limited to 30
                const maxFiles = state.stFilterActive ? 999999 : 30;
                const hasValidSelection = selectedCount >= 1 && selectedCount <= maxFiles;
                
                // Get tab buttons
                const settingsTab = document.querySelector('[data-tab="settings"]');
                const previewTab = document.querySelector('[data-tab="preview"]');
                
                // Settings tab accessibility
                if (settingsTab) {
                    if (hasValidSelection) {
                        settingsTab.classList.remove('opacity-50', 'cursor-not-allowed');
                        settingsTab.style.pointerEvents = 'auto';
                        settingsTab.title = 'Configure label settings';
                    } else {
                        settingsTab.classList.add('opacity-50', 'cursor-not-allowed');
                        settingsTab.style.pointerEvents = 'none';
                        settingsTab.title = `Select ${state.stFilterActive ? 'at least 1 SUA file' : 'up to 30 files'} first`;
                    }
                }
                
                // Preview tab accessibility (requires settings to be configured)
                if (previewTab) {
                    const hasSettings = state.labelFormat && state.labelSize;
                    if (hasValidSelection && hasSettings) {
                        previewTab.classList.remove('opacity-50', 'cursor-not-allowed');
                        previewTab.style.pointerEvents = 'auto';
                        previewTab.title = 'Preview and print labels';
                    } else {
                        previewTab.classList.add('opacity-50', 'cursor-not-allowed');
                        previewTab.style.pointerEvents = 'none';
                        if (!hasValidSelection) {
                            previewTab.title = `Select ${state.stFilterActive ? 'at least 1 SUA file' : 'up to 30 files'} first`;
                        } else {
                            previewTab.title = 'Configure label settings first';
                        }
                    }
                }
            }

            function updateSelectAllCheckbox() {
                const selectAllCheckbox = document.getElementById("selectAll");
                const filteredFiles = filterFiles();
                selectAllCheckbox.checked =
                    state.selectedFiles.length === filteredFiles.length &&
                    filteredFiles.length > 0;
            }

            function switchTab(tabName) {
                // Update tab buttons
                document.querySelectorAll(".tab-btn").forEach((btn) => {
                    btn.classList.remove("active", "border-blue-500", "text-blue-600");
                    btn.classList.add("border-transparent", "text-gray-500");
                });
                document
                    .querySelector(`[data-tab="${tabName}"]`)
                    .classList.add("active", "border-blue-500", "text-blue-600");
                document
                    .querySelector(`[data-tab="${tabName}"]`)
                    .classList.remove("border-transparent", "text-gray-500");

                // Update tab content
                document.querySelectorAll(".tab-content").forEach((content) => {
                    content.classList.remove("active");
                });
                document.getElementById(`${tabName}-tab`).classList.add("active");

                state.activeTab = tabName;
                if (tabName === "preview") {
                    refreshPreview();
                } else if (tabName === "generated") {
                    fetchGeneratedBatches();
                } else if (tabName === "settings") {
                    // Update settings tab with real data
                    updateSettingsPreview();
                }
            }

            function updateSettingsPreview() {
                // Update the settings tab to show real selected files data
                const selectedFilesData = state.selectedFiles.map(fileId => 
                    state.availableFiles.find(f => f.id === fileId)
                ).filter(file => file !== undefined);

                // Update any preview elements in the settings tab
                if (selectedFilesData.length > 0) {
                    console.log('Selected files for settings:', selectedFilesData);
                    // You can add specific UI updates here if needed
                }
            }

            function updatePreview() {
                const previewDescription = document.getElementById("previewDescription");

                const payload = buildLabelPayload();

                if (previewDescription) {
                    if (!payload.baseEntries.length) {
                        previewDescription.textContent = state.batchMode
                            ? "Enable batch generation to preview labels, or select specific files first."
                            : "No files selected yet. Choose files and configure settings to generate a preview.";
                    } else {
                        const labelWord = payload.summary.files === 1 ? "label" : "labels";
                        const copyWord = payload.summary.copies === 1 ? "copy" : "copies";
                        const pageWord = payload.summary.pages === 1 ? "page" : "pages";
                        previewDescription.textContent = `Previewing ${Math.min(payload.previewEntries.length, payload.summary.files)} ${labelWord} (${payload.summary.copies} ${copyWord} each). Printing will cover ${payload.summary.pages || 1} ${pageWord}.`;
                    }
                }

                renderPreview(payload);
                updatePrintSummaryCard(payload);
                updateRackStatisticsDisplay(state.labelStatistics);

                return payload;
            }

            function openPrintWindowWithPayload(payload) {
                const payloadKey = `printlabel-${Date.now()}`;

                try {
                    localStorage.setItem(payloadKey, JSON.stringify(payload));
                } catch (error) {
                    console.warn('Unable to persist print payload in localStorage', error);
                }

                const printUrl = `${PRINT_TEMPLATE_URL}?payloadKey=${encodeURIComponent(payloadKey)}`;
                let printWindow = null;

                try {
                    printWindow = window.open('', '_blank', 'width=980,height=720,scrollbars=1');
                } catch (error) {
                    console.warn('Failed to open print window', error);
                }

                if (!printWindow) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Enable pop-ups',
                        text: 'We couldn’t open the print window. Please allow pop-ups for this site and click Print again.',
                        confirmButtonColor: '#3b82f6',
                    });
                    return null;
                }

                try {
                    printWindow.document.write('<!DOCTYPE html><html><head><title>Preparing labels…</title><style>body{font-family:Inter,Segoe UI,Arial,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc;color:#475569;}</style></head><body><div>Preparing label print view…</div></body></html>');
                    printWindow.document.close();
                } catch (error) {
                    console.warn('Unable to prime print window content', error);
                }

                try {
                    printWindow.focus();
                } catch (error) {
                    console.warn('Unable to focus print window', error);
                }

                const navigateToTemplate = () => {
                    try {
                        printWindow.location.replace(printUrl);
                    } catch (error) {
                        try {
                            printWindow.location.href = printUrl;
                        } catch (navigationError) {
                            console.warn('Unable to navigate print window to template', navigationError);
                        }
                    }
                };

                setTimeout(navigateToTemplate, 25);

                const message = {
                    type: 'print-labels',
                    payload,
                };

                const sendMessage = () => {
                    if (printWindow.closed) {
                        return;
                    }

                    try {
                        printWindow.postMessage(message, window.location.origin);
                    } catch (err) {
                        console.warn('Unable to postMessage to print window yet', err);
                    }
                };

                // Initial attempt after a short delay to allow the window to boot.
                setTimeout(sendMessage, 500);

                // A few retries to improve reliability on slower machines.
                let attempts = 0;
                const retryTimer = setInterval(() => {
                    attempts += 1;
                    if (attempts > 5 || printWindow.closed) {
                        clearInterval(retryTimer);
                        return;
                    }
                    sendMessage();
                }, 800);

                return printWindow;
            }

            async function printLabels(event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }

                let batchResult;

                try {
                    batchResult = await preparePreviewDataIfNeeded();
                } catch (error) {
                    hideLoading();
                    showError(error.message || 'Unable to prepare batch for printing.');
                    return;
                }

                const payload = buildLabelPayload();

                if (!payload.baseEntries.length) {
                    showError('Nothing to print. Please select files or generate batch records.');
                    return;
                }

                payload.autoPrint = true;
                payload.notice = 'Generated via Print Labels interface';
                payload.meta.copies = payload.summary.copies;
                payload.meta.templateLabel = payload.summary.templateLabel;
                payload.meta.sizeLabel = payload.summary.sizeLabel;
                payload.meta.formatLabel = payload.summary.formatLabel;
                payload.meta.batchId = state.currentBatchId || payload.meta.batchId || null;

                if (batchResult?.data?.batch_number) {
                    payload.meta.batchNumber = batchResult.data.batch_number;
                }

                if (batchResult?.data?.source) {
                    payload.meta.batchSource = batchResult.data.source;
                }

                const registryMeta = batchResult?.data?.registry_batch_no || batchResult?.data?.sys_batch_no || null;
                if (registryMeta) {
                    payload.meta.registryBatchNo = registryMeta;
                }

                embedQrImages(payload);
                updateRackStatisticsDisplay(state.labelStatistics);

                const printWindow = openPrintWindowWithPayload(payload);

                if (!printWindow) {
                    return;
                }

                const successMessage = batchResult?.data?.batch_number
                    ? `Batch ${batchResult.data.batch_number} prepared. Print window ready.`
                    : 'Print window prepared. Use your browser’s print dialog to finish printing.';

                showSuccess(successMessage);

                // Refresh batch summaries to reflect the newly created batch
                fetchGeneratedBatches('', 1, { silent: true });
                fetchStatistics();
            }

            function handlePrintWindowMessages(event) {
                if (event.origin !== window.location.origin) {
                    return;
                }

                if (!event.data || typeof event.data !== 'object') {
                    return;
                }

                if (event.data.type === 'print-labels:afterprint') {
                    if (state.currentBatchId) {
                        setTimeout(() => {
                            if (confirm('Have you successfully printed the labels? This will mark the batch as printed.')) {
                                markBatchAsPrinted(state.currentBatchId);
                            }
                            state.currentBatchId = null;
                        }, 250);
                    }
                }
            }

            window.addEventListener('message', handlePrintWindowMessages);

            // Initialize button states
            updateButtonStates();
            const batchModeCheckbox = document.getElementById('batchMode');
            if (batchModeCheckbox) {
                batchModeCheckbox.checked = state.batchMode;
            }
            toggleBatchModeUI(state.batchMode);

            renderFileList();
            fetchStatistics();

            // Tab switching
            document.querySelectorAll(".tab-btn").forEach((btn) => {
                btn.addEventListener("click", function () {
                    switchTab(this.dataset.tab);
                });
            });

            // History toggle
            if (document.getElementById("historyBtn")) {
                document
                    .getElementById("historyBtn")
                    .addEventListener("click", function () {
                        state.showHistory = !state.showHistory;
                        document.getElementById("printHistory").style.display =
                            state.showHistory ? "block" : "none";
                    });
            }

            if (document.getElementById("closeHistoryBtn")) {
                document
                    .getElementById("closeHistoryBtn")
                    .addEventListener("click", function () {
                        state.showHistory = false;
                        document.getElementById("printHistory").style.display = "none";
                    });
            }

            // Reset form
            document
                .getElementById("resetBtn")
                .addEventListener("click", function () {
                    resetPreparedState();
                    state.selectedFiles = [];
                    state.labelSize = "30-in-1";
                    state.labelFormat = "qrcode";
                    state.copies = 1;
                    state.selectedTemplate = "30-in-1";
                    state.orientation = "portrait";
                    state.showAdvancedOptions = false;
                    state.batchMode = true;
                    state.batchStartNumber = 1;
                    state.batchCount = (RANGE_OPTIONS['1-30'].end - RANGE_OPTIONS['1-30'].start) + 1;
                    state.registryBatchNo = '';
                    state.registryId = '1';
                    state.rackPrimary = 'A';
                    state.rackSecondary = '';
                    state.shelfNumber = '1';
                    state.fullLabel = 'A1';
                    state.rangeKey = '1-30';
                    state.rangeStart = RANGE_OPTIONS['1-30'].start;
                    state.rangeEnd = RANGE_OPTIONS['1-30'].end;
                    state.excludeAssignedFromBatch = false;
                    state.rackLabelStatus = null;
                    state.registryProgress = null;
                    state.availableFiles = [];

                    // Reset UI
                    document.getElementById("copies").value = 1;
                    document.getElementById("batchMode").checked = true;
                    toggleBatchModeUI(true);
                    const batchStartControl = document.getElementById("batchStart");
                    if (batchStartControl) {
                        batchStartControl.value = 1;
                        batchStartControl.dataset.userOverride = 'false';
                    }
                    const registrySelectEl = document.getElementById("registrySelect");
                    if (registrySelectEl) {
                        registrySelectEl.value = '1';
                    }
                    if (typeof jQuery !== 'undefined') {
                        jQuery('#registryBatchSelect').val(null).trigger('change');
                    }
                    loadInitialRegistryBatches();
                    const batchRangeSelectEl = document.getElementById("batchRange");
                    if (batchRangeSelectEl) {
                        batchRangeSelectEl.value = '1-30';
                        batchRangeSelectEl.dataset.userOverride = 'false';
                    }
                    const rackPrimarySelectEl = document.getElementById("rackPrimarySelect");
                    if (rackPrimarySelectEl) {
                        rackPrimarySelectEl.value = 'A';
                    }
                    const rackSecondarySelectEl = document.getElementById("rackSecondarySelect");
                    if (rackSecondarySelectEl) {
                        rackSecondarySelectEl.value = '';
                    }
                    const shelfNumberSelectEl = document.getElementById("shelfNumberSelect");
                    if (shelfNumberSelectEl) {
                        shelfNumberSelectEl.value = '1';
                    }
                    updateFullLabelDisplay();
                    updateRackLabelStatusDisplay();
                    syncRegistryProgress(null);
                    fetchRackLabelStatus(state.fullLabel);
                    const excludeToggle = document.getElementById("excludeAssignedToggle");
                    if (excludeToggle) {
                        excludeToggle.checked = false;
                    }

                    const portraitOption = document.querySelector('[data-orientation="portrait"]');
                    const landscapeOption = document.querySelector('[data-orientation="landscape"]');
                    if (portraitOption) {
                        portraitOption.classList.add('selected');
                        const radio = portraitOption.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = true;
                        }
                    }
                    if (landscapeOption) {
                        landscapeOption.classList.remove('selected');
                        const radio = landscapeOption.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = false;
                        }
                    }

                    renderFileList();
                    updateCounts();
                    updateSelectAllCheckbox();
                    if (state.activeTab === 'preview') {
                        refreshPreview(true);
                    }
                });

            // Search functionality
            document
                .getElementById("searchInput")
                .addEventListener("input", function () {
                    state.searchTerm = this.value;
                    if (state.batchMode) {
                        renderFileList();
                        updateCounts();
                        return;
                    }

                    // Debounce the search for non-batch queries
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        fetchAvailableFiles(state.searchTerm);
                    }, 500);
                });

            // Select all functionality
            document
                .getElementById("selectAll")
                .addEventListener("change", function () {
                    const filteredFiles = filterFiles();
                    if (this.checked) {
                        state.selectedFiles = [
                            ...new Set([
                                ...state.selectedFiles,
                                ...filteredFiles.map((f) => f.id),
                            ]),
                        ];
                    } else {
                        const filteredIds = filteredFiles.map((f) => f.id);
                        state.selectedFiles = state.selectedFiles.filter(
                            (id) => !filteredIds.includes(id)
                        );
                    }
                    resetPreparedState();
                    renderFileList();
                    updateCounts();
                });

            // Batch mode toggle
            document
                .getElementById("batchMode")
                .addEventListener("change", function () {
                    state.batchMode = this.checked;
                    toggleBatchModeUI(this.checked);
                    resetPreparedState();
                    if (state.batchMode) {
                        state.availableFiles = [];
                        state.selectedFiles = [];
                        renderFileList();
                        updateCounts();
                    } else {
                        fetchAvailableFiles('', 1, { showModal: false });
                    }
                    if (state.activeTab === 'preview') {
                        refreshPreview(true);
                    }
                });

            document
                .getElementById("batchStart")
                .addEventListener("change", function () {
                    this.dataset.userOverride = 'true';
                    state.batchStartNumber = parseInt(this.value);
                    resetPreparedState();
                    if (state.activeTab === 'preview') {
                        refreshPreview(true);
                    }
                });

            const registrySelect = document.getElementById("registrySelect");
            if (registrySelect) {
                registrySelect.addEventListener("change", function () {
                    state.registryId = (this.value || '1').toString();
                    state.registryBatchNo = '';
                    resetPreparedState();
                    syncRegistryProgress(null);
                    if (typeof jQuery !== 'undefined') {
                        jQuery('#registryBatchSelect').val(null).trigger('change');
                    }
                    loadInitialRegistryBatches();
                });
            }

            const excludeAssignedToggle = document.getElementById("excludeAssignedToggle");
            if (excludeAssignedToggle) {
                excludeAssignedToggle.addEventListener("change", function () {
                    state.excludeAssignedFromBatch = this.checked;
                });
            }

            const batchRangeSelect = document.getElementById("batchRange");
            if (batchRangeSelect) {
                batchRangeSelect.addEventListener("change", function () {
                    this.dataset.userOverride = 'true';
                    setRangeSelection(this.value || '1-30');
                    resetPreparedState();
                });
            }

            const rackPrimarySelect = document.getElementById("rackPrimarySelect");
            if (rackPrimarySelect) {
                rackPrimarySelect.addEventListener("change", function () {
                    state.rackPrimary = (this.value || 'A').toUpperCase();
                    const label = updateFullLabelDisplay();
                    fetchRackLabelStatus(label);
                });
            }

            const rackSecondarySelect = document.getElementById("rackSecondarySelect");
            if (rackSecondarySelect) {
                rackSecondarySelect.addEventListener("change", function () {
                    state.rackSecondary = (this.value || '').toUpperCase();
                    const label = updateFullLabelDisplay();
                    fetchRackLabelStatus(label);
                });
            }

            const shelfNumberSelect = document.getElementById("shelfNumberSelect");
            if (shelfNumberSelect) {
                shelfNumberSelect.addEventListener("change", function () {
                    state.shelfNumber = (this.value || '1').toString();
                    const label = updateFullLabelDisplay();
                    fetchRackLabelStatus(label);
                });
            }

            document
                .getElementById("generateBatchBtn")
                .addEventListener("click", function () {
                    if (!state.batchMode) {
                        showError('Enable batch mode to generate records.');
                        return;
                    }

                    const triggerButton = this;
                    triggerButton.disabled = true;
                    triggerButton.classList.add('opacity-50', 'cursor-not-allowed');

                    const startInput = parseInt(document.getElementById("batchStart").value, 10);
                    const domRegistryValue = (() => {
                        if (state.registryBatchNo && state.registryBatchNo !== '') {
                            return state.registryBatchNo;
                        }

                        if (typeof jQuery !== 'undefined') {
                            const selected = jQuery('#registryBatchSelect').val();
                            if (selected && selected !== '') {
                                return selected;
                            }
                        }

                        const selectElement = document.getElementById('registryBatchSelect');
                        if (selectElement && selectElement.value) {
                            return selectElement.value;
                        }

                        return '';
                    })();

                    const registryValue = (domRegistryValue || '').toString().trim();

                    const start = Number.isNaN(startInput) ? 1 : Math.max(1, startInput);
                    const limit = Math.max(1, Math.min(state.batchCount, 100));

                    if (registryValue === '') {
                        showError('Select a registry batch number before loading records.');
                        triggerButton.disabled = false;
                        triggerButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        return;
                    }

                    if (state.rackLabelStatus?.is_full) {
                        showError('The selected rack/shelf label is full. Please choose the next shelf.');
                        triggerButton.disabled = false;
                        triggerButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        return;
                    }

                    state.batchStartNumber = start;
                    state.batchCount = limit;
                    state.registryBatchNo = registryValue;

                    const params = new URLSearchParams({
                        registry_batch_no: registryValue,
                        start: start,
                        limit: limit,
                    });
                    if (state.registryId) {
                        params.append('registry', state.registryId);
                    }
                    if (state.excludeAssignedFromBatch) {
                        params.append('exclude_assigned', 'true');
                    }

                    const loaderTimer = setTimeout(() => {
                        showLoading('Loading grouping records...');
                    }, 250);

                    fetch(`${API.groupingPreview}?${params.toString()}`)
                        .then((response) => response.json())
                        .then((payload) => {
                            clearTimeout(loaderTimer);
                            if (!payload.success) {
                                if (payload?.data?.registry_progress) {
                                    syncRegistryProgress(payload.data.registry_progress, { force: true });
                                }
                                throw new Error(payload.message || 'Unable to load grouping records.');
                            }

                            const records = (payload.data && payload.data.records) ? payload.data.records : [];

                            if (!Array.isArray(records) || records.length === 0) {
                                hideLoading();
                                showError('No records found for the selected batch.');
                                return;
                            }

                            const normalizedRecords = records.map((record) => ({
                                ...record,
                                id: Number(record.id),
                                file_title: record.file_title || record.tracking_id || 'No title',
                                land_use_type: record.land_use_type || record.landuse || 'File',
                                batch_no: record.batch_no || payload.data?.requested?.registry_batch_no || registryValue,
                                registry_batch_no: record.registry_batch_no || payload.data?.requested?.registry_batch_no || registryValue,
                            }));

                            resetPreparedState();

                            const statsPayload = payload.data && payload.data.label_statistics
                                ? payload.data.label_statistics
                                : null;

                            state.labelStatistics = statsPayload;
                            if (payload.data?.rack_label_status) {
                                state.rackLabelStatus = payload.data.rack_label_status;
                                updateRackLabelStatusDisplay();
                            }
                            if (payload.data?.registry_progress) {
                                syncRegistryProgress(payload.data.registry_progress);
                            } else if (payload.data?.rack_label_status?.registry_progress) {
                                syncRegistryProgress(payload.data.rack_label_status.registry_progress);
                            }
                            state.availableFiles = normalizedRecords;
                            state.selectedFiles = normalizedRecords.map((record) => record.id);
                            state.searchTerm = '';

                            const searchInput = document.getElementById('searchInput');
                            if (searchInput) {
                                searchInput.value = '';
                            }

                            renderFileList();
                            updateCounts();
                            updateSelectAllCheckbox();

                            if (state.loading) {
                                hideLoading();
                            }

                            const requestedBatch = payload.data?.requested?.registry_batch_no || registryValue;
                            const totalLoaded = normalizedRecords.length;
                            showSuccess(`Loaded ${totalLoaded} record${totalLoaded === 1 ? '' : 's'} from registry_batch_no ${requestedBatch}.`);

                            if (state.activeTab === 'preview') {
                                refreshPreview(true);
                            }
                        })
                        .catch((error) => {
                            clearTimeout(loaderTimer);
                            if (state.loading) {
                                hideLoading();
                            }
                            showError(error.message || 'Unable to load grouping records.');
                        })
                        .finally(() => {
                            clearTimeout(loaderTimer);
                            triggerButton.disabled = false;
                            triggerButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        });
                });

            initializeRegistryBatchSelect();
            loadInitialRegistryBatches();
            updateFullLabelDisplay();
            updateRackLabelStatusDisplay();
            fetchRackLabelStatus(state.fullLabel);

            // Label format selection
            document.querySelectorAll(".label-format-option").forEach((option) => {
                option.addEventListener("click", function () {
                    document
                        .querySelectorAll(".label-format-option")
                        .forEach((opt) => opt.classList.remove("selected"));
                    this.classList.add("selected");
                    state.labelFormat = this.dataset.format;
                    updateTabAccessibility(); // Update tab accessibility when format changes
                });
            });

            // Orientation selection
            document.querySelectorAll(".orientation-option").forEach((option) => {
                option.addEventListener("click", function () {
                    if (this.dataset.disabled === 'true') {
                        return;
                    }
                    document
                        .querySelectorAll(".orientation-option")
                        .forEach((opt) => {
                            if (opt.dataset.disabled === 'true') {
                                opt.classList.remove('selected');
                                const input = opt.querySelector('input[type="radio"]');
                                if (input) {
                                    input.checked = false;
                                }
                                return;
                            }
                            opt.classList.remove('selected');
                        });
                    this.classList.add("selected");
                    state.orientation = this.dataset.orientation;
                    resetPreparedState();
                    if (state.activeTab === 'preview') {
                        refreshPreview(true);
                    }
                    const targetRadio = document.querySelector(
                        `input[value="${this.dataset.orientation}"]`
                    );
                    if (targetRadio) {
                        targetRadio.checked = true;
                    }
                });
            });

            // Advanced options toggle
            if (document.getElementById("advancedToggle")) {
                document
                    .getElementById("advancedToggle")
                    .addEventListener("click", function () {
                        state.showAdvancedOptions = !state.showAdvancedOptions;
                        document.getElementById("advancedOptions").style.display =
                            state.showAdvancedOptions ? "block" : "none";
                        this.textContent = state.showAdvancedOptions
                            ? "Hide Advanced"
                            : "Show Advanced";
                    });
            }

            // Copies input
            document
                .getElementById("copies")
                .addEventListener("change", function () {
                    state.copies = parseInt(this.value);
                });

            // Template and size selection
            document
                .getElementById("labelTemplate")
                .addEventListener("change", function () {
                    state.selectedTemplate = this.value;
                    resetPreparedState();
                    if (state.activeTab === 'preview') {
                        refreshPreview(true);
                    }
                });

            document
                .getElementById("labelSize")
                .addEventListener("change", function () {
                    state.labelSize = this.value;
                    updateTabAccessibility(); // Update tab accessibility when size changes
                });

            // Navigation buttons
            document
                .getElementById("continueToSettingsBtn")
                .addEventListener("click", function () {
                    if (this.disabled) return;
                    
                    const selectedCount = state.selectedFiles.length;
                    const maxFiles = state.stFilterActive ? 999999 : 30; // No limit for ST mode
                    const minFiles = 1;
                    
                    if (selectedCount < minFiles) {
                        showError(`Please select at least ${minFiles} file${minFiles > 1 ? 's' : ''} to continue.`);
                        return;
                    }
                    
                    if (!state.stFilterActive && selectedCount > maxFiles) {
                        showError(`Too many files selected. Maximum ${maxFiles} files allowed. Currently selected: ${selectedCount}`);
                        return;
                    }
                    
                    switchTab("settings");
                });

            document
                .getElementById("backToFilesBtn")
                .addEventListener("click", function () {
                    switchTab("files");
                });

            document
                .getElementById("continueToPreviewBtn")
                .addEventListener("click", function () {
                    switchTab("preview");
                });

            document
                .getElementById("backToSettingsBtn")
                .addEventListener("click", function () {
                    switchTab("settings");
                });

            // Action buttons
            if (document.getElementById("duplicateBtn")) {
                document
                    .getElementById("duplicateBtn")
                    .addEventListener("click", function () {
                        state.copies = state.copies + 1;
                        document.getElementById("copies").value = state.copies;
                        alert(
                            `Duplicated selected labels. Now printing ${state.copies} copies of each.`
                        );
                    });
            }

            if (document.getElementById("exportPdfBtn")) {
                document
                    .getElementById("exportPdfBtn")
                    .addEventListener("click", function () {
                        alert("Exporting labels as PDF...");
                    });
            }

            if (document.getElementById("saveTemplateBtn")) {
                document
                    .getElementById("saveTemplateBtn")
                    .addEventListener("click", function () {
                        const templateName = prompt("Enter a name for this template:");
                        if (templateName) {
                            alert(`Template "${templateName}" saved successfully!`);
                        }
                    });
            }

            if (document.getElementById("importTemplateBtn")) {
                document
                    .getElementById("importTemplateBtn")
                    .addEventListener("click", function () {
                        alert(
                            "Import template functionality would open a file dialog here"
                        );
                    });
            }

            // Print buttons - now calls backend to create batch
            document
                .getElementById("printBtn")
                .addEventListener("click", function () {
                    if (this.disabled) return;

                    if (!hasSelectionForBatch()) {
                        showError('Please select at least one file before previewing.');
                        return;
                    }

                    switchTab('preview');
                });

            // Status filter for batches
            if (document.getElementById("statusFilter")) {
                document
                    .getElementById("statusFilter")
                    .addEventListener("change", function () {
                        fetchGeneratedBatches(this.value);
                    });
            }

            // Refresh batches button
            if (document.getElementById("refreshBatchesBtn")) {
                document
                    .getElementById("refreshBatchesBtn")
                    .addEventListener("click", function () {
                        fetchGeneratedBatches();
                        fetchStatistics();
                    });
            }

            // Final print button - now calls the printLabels function
            document
                .getElementById("finalPrintBtn")
                .addEventListener("click", printLabels);

            // Initialize the page
            updateCounts();
            updateTabAccessibility(); // Initialize tab accessibility

        });
    </script>

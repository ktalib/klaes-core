<!-- Custom Styles for Dropdown   -->
<style>
    .dropdown-menu {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border-radius: 8px;
        overflow: hidden;
        animation: fadeIn 0.15s ease-out;
    }   

    .dropdown-menu button {
        transition: all 0.15s ease;
    }

    .dropdown-menu button:hover {
        transform: translateX(2px);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Ensure dropdown appears above DataTable elements */
    .dropdown-menu {
        z-index: 1050 !important;
        background-color: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        min-width: 14rem;
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }

    /* .action-dropdown-menu is styled in mlsfno.blade.php -- kept in one place so the
       min-width / show / hidden rules can't disagree between the two stylesheets. */
</style>

<!-- jsPDF Library for PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js">    </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>

@php
    // Fetch all commissioning sheets and build a normalized lookup map (trimmed, uppercased keys)
    $commissioningSheetsRaw = \DB::connection('sqlsrv')
        ->table('file_commissioning_sheets')
        ->select('id', 'file_number')
        ->get();
    $commissioningSheets = [];
    foreach ($commissioningSheetsRaw as $sheet) {
        $key = strtoupper(trim($sheet->file_number));
        $commissioningSheets[$key] = [
            'id' => $sheet->id,
            'file_number' => $sheet->file_number
        ];
    }
@endphp

<script>
    console.log('DEBUG: mls_js.blade.php script tag reached');
    
    function openGeneratorModalMain() {
        console.log('openGeneratorModalMain() called');
        const modalEl = document.getElementById('generateModal');
        if (modalEl) {
            modalEl.classList.remove('hidden');
        }
        
        // Ensure Printer Manager is closed to prevent conflicts
        if (typeof closePrinterManager === 'function') {
            closePrinterManager();
        }
        
        if (typeof resetTrackingIdDisplay === 'function') resetTrackingIdDisplay('--');
        if (typeof setActionButtonsDisabled === 'function') setActionButtonsDisabled(true);
        if (typeof resetForm === 'function') resetForm();

        // Fetch latest serial numbers from database
        if (typeof getNextSerialNumber === 'function') getNextSerialNumber();

        // Set current time for commission time input
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const timeInput = document.getElementById('commissionTime');
        if (timeInput) {
            timeInput.value = `${hours}:${minutes}`;
        }

        // Ensure the serial number is properly set when modal opens
        setTimeout(() => {
            if (typeof updateAlpineSerialNumber === 'function') updateAlpineSerialNumber();
        }, 100);

    }

    // Create a JS object for quick lookup (normalized keys)
    const commissioningSheetsMap = @json($commissioningSheets);

    // Provide server time to prevent client-side time issues
    const getServerTime = () => {
        const serverTime = "{{ date('Y-m-d H:i') }}";
        const [datePart, timePart] = serverTime.split(' ');
        return { date: datePart, time: timePart };
    };

    // Helper function to format 24h time string to 12h AM/PM
    const formatTimeToAMPM = (timeStr) => {
        if (!timeStr || typeof timeStr !== 'string') return '';
        if (timeStr.includes('AM') || timeStr.includes('PM')) return timeStr;
        
        const parts = timeStr.split(':');
        if (parts.length < 2) return timeStr;
        
        let hours = parseInt(parts[0]);
        let minutes = parts[1].substring(0, 2);
        
        if (isNaN(hours)) return timeStr;
        
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        
        return `${String(hours).padStart(2, '0')}:${minutes} ${ampm}`;
    };

    // Helper function to get commissioning sheet id for a row
    function getCommissioningSheetId(row) {
        const fileNumbers = [row.mlsfNo, row.kangisFileNo, row.NewKANGISFileNo]
            .filter(fn => fn && fn !== 'N/A' && fn.trim() !== '')
            .map(fn => fn.trim().toUpperCase());
        // ...
        for (const fn of fileNumbers) {
            if (commissioningSheetsMap[fn]) {
                return commissioningSheetsMap[fn].id;
            }
        }
        return null;
    }

    const groupingLookupState = {
        debounceHandle: null,
        inFlightRequestId: 0,
        lastSuccessfulNumber: null
    };

    const GROUPING_LOOKUP_TIMEOUT_MS = 4500;
    const GENERATE_REQUEST_TIMEOUT_MS = 60000;

    function setActionButtonsDisabled(isDisabled) {
        const overrideBtn = document.getElementById('overrideButton');
        const generateBtn = document.getElementById('generateButton');

        [overrideBtn, generateBtn].forEach((button) => {
            if (!button) {
                return;
            }

            button.disabled = isDisabled;

            if (isDisabled) {
                button.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    // Helper function to render file numbers with badges
    function renderFileNumberBadge(fileNumber) {
        if (!fileNumber || fileNumber === 'N/A' || fileNumber.trim() === '') {
            return '\u003cspan class="text-gray-400 text-sm"\u003e-\u003c/span\u003e';
        }
        // Determine badge class based on file number prefix
        let badgeClass = 'badge-default';
        const upperFileNumber = fileNumber.toUpperCase();

        if (upperFileNumber.startsWith('RES') || upperFileNumber.includes('CON-RES')) {
            badgeClass = 'badge-res';
        } else if (upperFileNumber.startsWith('COM') || upperFileNumber.includes('CON-COM')) {
            badgeClass = 'badge-com';
        } else if (upperFileNumber.startsWith('IND') || upperFileNumber.includes('CON-IND')) {
            badgeClass = 'badge-ind';
        } else if (upperFileNumber.startsWith('AG') || upperFileNumber.includes('CON-AG')) {
            badgeClass = 'badge-ag';
        }

        return `\u003cspan class="file-badge ${badgeClass}"\u003e${fileNumber}\u003c/span\u003e`;
    }

    // Derive a human-readable File Type label from a file number's prefix.
    // e.g. CON-COM-2026-429 -> "Conversion", RES-2024-12 -> "Residential".
    // Prefix-based guessing is only a fallback — prefer the record's actual
    // mls_file_no.source via getCommissioningSourceLabel() whenever available.
    function getCommissioningFileType(fileNumber) {
        if (!fileNumber) return '';
        let up = String(fileNumber).toUpperCase().trim();
        const isTemporary = up.endsWith('(T)');
        if (isTemporary) up = up.replace(/\(T\)\s*$/, '').trim();

        let label = '';
        if (up.startsWith('CON-') || up === 'CON') {
            label = 'Conversion';
        } else if (up.startsWith('SLTR')) {
            label = 'Statutory Right of Occupancy';
        } else if (up.startsWith('SIT')) {
            label = 'SIT';
        } else if (up.startsWith('RES')) {
            label = 'Residential';
        } else if (up.startsWith('COM')) {
            label = 'Commercial';
        } else if (up.startsWith('IND')) {
            label = 'Industrial';
        } else if (up.startsWith('AG')) {
            label = 'Agricultural';
        } else if (up.startsWith('KN')) {
            label = 'Kano Heritage';
        } else if (up.startsWith('MISC')) {
            label = 'Miscellaneous';
        }

        if (isTemporary) {
            label = label ? `Temporary - ${label}` : 'Temporary File';
        }
        return label;
    }

    // Resolve the label shown after the File No on the commissioning sheet.
    // The authoritative value is the record's source (mls_file_no.source, e.g.
    // "Conversion", "Change of Purpose", "OP Resettlement"); the file-number
    // prefix is only used when no source is stored for the record.
    function getCommissioningSourceLabel(source, fileNumber) {
        const src = String(source || '').trim();
        if (src && src.toUpperCase() !== 'N/A') return src;
        return getCommissioningFileType(fileNumber);
    }

    // Fetch the record's source and stamp it on the hidden cs_source input so the
    // modal badge and Generate & Print flow use the real source, not a prefix guess.
    async function populateCommissioningSource(fileNumber) {
        const sourceInput = document.getElementById('cs_source');
        if (!sourceInput) return;

        const key = String(fileNumber || '').trim();
        sourceInput.value = '';
        sourceInput.dataset.fileNo = key.toUpperCase();
        updateCommissioningFileType();
        if (!key) return;

        try {
            const response = await fetch(`{{ route("mls-fileno.show", ":id") }}`.replace(':id', encodeURIComponent(key)));
            const payload = await response.json();
            const record = (payload && payload.success && payload.data) ? payload.data : payload;
            const input = document.getElementById('cs_file_number');
            const stillSameFile = input && input.value.trim().toUpperCase() === sourceInput.dataset.fileNo;
            if (record && record.source && stillSameFile) {
                sourceInput.value = String(record.source).trim();
                updateCommissioningFileType();
            }
        } catch (e) {
            console.warn('Could not resolve file source for commissioning sheet', e);
        }
    }

    // Reflect the detected File Type as a badge next to the File No input in the commissioning modal.
    function updateCommissioningFileType() {
        const input = document.getElementById('cs_file_number');
        const badge = document.getElementById('cs_file_type_badge');
        if (!input || !badge) return;

        // Drop a stale source when the user edits the file number manually.
        const sourceInput = document.getElementById('cs_source');
        if (sourceInput && sourceInput.dataset.fileNo !== input.value.trim().toUpperCase()) {
            sourceInput.value = '';
        }

        const label = getCommissioningSourceLabel(sourceInput ? sourceInput.value : '', input.value);
        if (label) {
            badge.textContent = label;
            badge.classList.remove('hidden');
        } else {
            badge.textContent = '';
            badge.classList.add('hidden');
        }
    }

    function resetTrackingIdDisplay(text = '--') {
        const displayEl = document.getElementById('trackingIdDisplay');
        if (displayEl) {
            displayEl.textContent = text;
            // Add visual cue for empty/reset state
            displayEl.classList.remove('text-green-600', 'text-blue-600');
            displayEl.classList.add('text-red-600');
        }
        
        const inputEl = document.getElementById('trackingIdInput');
        if (inputEl) {
            inputEl.value = '';
        }
        
        // Clear last successful number to ensure reactivity when switching back and forth
        groupingLookupState.lastSuccessfulNumber = null;
        setActionButtonsDisabled(true);
    }

    function applyTrackingIdToDisplay(trackingId, fallbackText = '--') {
        const displayValue = trackingId || fallbackText;
        
        const inputEl = document.getElementById('trackingIdInput');
        if (inputEl) {
            inputEl.value = trackingId || '';
        }

        const displayEl = document.getElementById('trackingIdDisplay');
        if (displayEl) {
            displayEl.textContent = displayValue;
            
            // Visual feedback - always red as per user request
            displayEl.classList.remove('text-green-600', 'text-blue-600', 'text-gray-500');
            displayEl.classList.add('text-red-600');
            
            // Detect prefix from Tracking ID (e.g. CON-COM from CON-COM-2026-262)
            if (trackingId && trackingId.includes('-')) {
                const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
                const state = modalEl?._x_dataStack ? modalEl._x_dataStack[0] : null;
                
                if (state) {
                    const parts = trackingId.split('-');
                    const yearIndex = parts.findIndex(p => /^(19|20)\d{2}$/.test(p));
                    let detectedPrefix = '';
                    if (yearIndex > 0) {
                        detectedPrefix = parts.slice(0, yearIndex).join('-').toUpperCase();
                    } else if (parts.length > 1) {
                        // Fallback: take first part if no year found but hyphen exists
                        detectedPrefix = parts[0].toUpperCase();
                    }
                    
                    if (detectedPrefix && state.allAllPrefixes) {
                        const bestMatch = state.allAllPrefixes.find(p => p.prefix === detectedPrefix);
                        if (bestMatch) {
                            state.prefix = bestMatch.prefix;
                            // Small delay to ensure prefix is set before triggering change
                            setTimeout(() => {
                                if (typeof state.handlePrefixChange === 'function') {
                                    state.handlePrefixChange();
                                }
                            }, 50);
                        }
                    }
                }
            }

            if (!trackingId) {
                // Clear cache if not found so we can try again if user re-enters
                groupingLookupState.lastSuccessfulNumber = null;
            }
        }

        const summaryTrackingEl = document.getElementById('summaryTrackingId');
        if (summaryTrackingEl) {
            summaryTrackingEl.textContent = displayValue;
        }
    }

    function isEligibleForGroupingLookup(value) {
        const sanitized = (value || '').replace(/[^A-Z0-9]/gi, '');
        return sanitized.length >= 5 && /\d/.test(sanitized);
    }

    function deriveGroupingLookupCandidate(fileOption, previewText, existingFileNo) {
        let preview = (previewText || '').trim();
        const existing = (existingFileNo || '').trim();

        // If it's a batch range (e.g., "RES-2024-1 to RES-2024-10"), take only the first part
        if (preview.includes(' to ')) {
            preview = preview.split(' to ')[0].trim();
        }

        // If we have an existing file number (from OP capture, etc.),
        // it's the primary candidate for finding the record in the grouping/index table.
        // For subdivision and merger, we use the selected File Options (preview) instead of the temp app number.
        if (existing !== '' && (fileOption === 'normal' || fileOption === 'regrant' || fileOption === 'resettlement' || fileOption === 'temporary' || fileOption === 'extension')) {
            return existing;
        }

        if (!fileOption) {
            return preview;
        }

        switch (fileOption) {
            case 'extension':
                return preview.replace(/\s+AND\s+EXTENSION$/i, '').trim();
            case 'temporary':
                return preview.replace(/\(T\)\s*$/i, '').trim();
            case 'old_mls':
                return preview.replace(/\s+/g, '-');
            default:
                return preview;
        }
    }

    function queueGroupingLookup(fileNumber) {
        // Clean trailing hyphens from file number (common with extension/temporary files)
        let cleaned = (fileNumber || '').trim().replace(/-+$/, '').toUpperCase();
        const normalized = cleaned;

        if (!normalized || normalized === '-' || !isEligibleForGroupingLookup(normalized)) {
            resetTrackingIdDisplay('--');
            return;
        }

        if (groupingLookupState.lastSuccessfulNumber === normalized) {
            return;
        }

        if (groupingLookupState.debounceHandle) {
            clearTimeout(groupingLookupState.debounceHandle);
        }

        groupingLookupState.debounceHandle = setTimeout(() => {
            performGroupingLookup(normalized);
        }, 350);
    }

    async function performGroupingLookup(fileNumber) {
        groupingLookupState.debounceHandle = null;
        const requestId = Date.now();
        groupingLookupState.inFlightRequestId = requestId;

        const displayEl = document.getElementById('trackingIdDisplay');
        if (displayEl) {
            displayEl.textContent = 'Looking...';
        }

        setActionButtonsDisabled(true);

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        const controller = new AbortController();
        const timeoutHandle = setTimeout(() => controller.abort(), GROUPING_LOOKUP_TIMEOUT_MS);

        try {
            // Get the actual preview text (the target MLS number) from Alpine state
            // Target the element that actually holds the x-data
            const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
            const state = modalEl?._x_dataStack ? modalEl._x_dataStack[0] : {};
            const targetMlsNumber = state.preview || '';
            
            // Find registry name if possible
            const registrySelect = document.getElementById('mlsfRegistry');
            const registryName = registrySelect?.options[registrySelect.selectedIndex]?.text || 'Lands Registry';

            const payload = { 
                mls_file_number: targetMlsNumber || '-',
                awaiting_file_number: fileNumber,
                registry: registryName
            };
            
            console.log('Grouping Lookup Request:', payload);

            const response = await fetch("{{ route('api.grouping.link-mls') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                signal: controller.signal,
                body: JSON.stringify(payload)
            });

            clearTimeout(timeoutHandle);

            const responseData = await response.json();

            if (groupingLookupState.inFlightRequestId !== requestId) {
                return;
            }

            if (response.ok && responseData.success) {
                const trackingId = (responseData.data?.tracking_id ?? '').trim();
                applyTrackingIdToDisplay(trackingId, '--');

                groupingLookupState.lastSuccessfulNumber = fileNumber;
                setActionButtonsDisabled(trackingId === '');

                if (trackingId === '') {
                    applyTrackingIdToDisplay('', 'Not Found');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tracking ID Missing',
                        text: 'Tracking ID must come from grouping table for this file number. Please update grouping record before generating.',
                        confirmButtonColor: '#f59e0b'
                    });
                }
            } else {
                handleGroupingLookupError(responseData, response.status);
            }
        } catch (error) {
            clearTimeout(timeoutHandle);
            if (groupingLookupState.inFlightRequestId === requestId) {
                applyTrackingIdToDisplay('', 'Not Found');
                setActionButtonsDisabled(true);
            }
            console.error('Grouping lookup failed:', error);
        }
    }

    function handleGroupingLookupError(payload, status) {
        resetTrackingIdDisplay('--');

        /* 
        if (status === 409) {
            Swal.fire({
                icon: 'warning',
                title: 'Mapping Conflict',
                text: payload?.message || 'Grouping record is mapped to a different MLS file number.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }
        */

        if (status === 404) {
            applyTrackingIdToDisplay('', 'Not Found');
            setActionButtonsDisabled(true);
            console.warn('Grouping record not found for MLS file number lookup.', payload);
            return;
        }

        if (status >= 500) {
            Swal.fire({
                icon: 'error',
                title: 'Grouping Lookup Failed',
                text: payload?.message || 'Unable to link grouping record right now.',
                confirmButtonColor: '#ef4444'
            });
            applyTrackingIdToDisplay('', 'Not Found');
            setActionButtonsDisabled(true);
        }
    }
    // ...

    let table;
    let nextSerialNo = 1;
    let isOverrideMode = false;

    // Loading utility functions
    function showLoadingButton(buttonElement, originalText) {
        if (buttonElement) {
            buttonElement.disabled = true;
            buttonElement.innerHTML = `
                    <i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i>
                    Loading...
                `;
            lucide.createIcons();
        }
    }

    function hideLoadingButton(buttonElement, originalText) {
        if (buttonElement) {
            buttonElement.disabled = false;
            buttonElement.innerHTML = originalText;
            lucide.createIcons();
        }
    }

    function showGlobalLoading(message = 'Processing...') {
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function hideGlobalLoading() {
        Swal.close();
    }

    function formatLandUse(landUse, mlsfNo) {
        let landUsePrefix = '';

        // If landUse is provided, use it as the prefix base
        if (landUse && landUse !== 'N/A' && landUse.trim() !== '') {
            landUsePrefix = landUse.trim().toUpperCase();
        } else if (mlsfNo && mlsfNo !== 'N/A' && mlsfNo.trim() !== '') {
            // Otherwise, derive it from the MLS File Number
            const parts = mlsfNo.split('-');
            if (parts.length > 0) {
                landUsePrefix = parts[0].trim().toUpperCase();
                // Handle CON- prefix (e.g., CON-COM-...) - use the second part if available and valid
                if (landUsePrefix === 'CON' && parts.length > 1) {
                    const secondPart = parts[1].trim().toUpperCase();
                    if (isNaN(secondPart)) {
                        landUsePrefix = secondPart;
                    }
                }
            }
        }

        if (!landUsePrefix || isNaN(landUsePrefix) === false) {
            return '-';
        }

        // Map prefixes to full names and colors (Uppercased as requested)
        const mapping = {
            'RES': { name: 'RESIDENTIAL', class: 'bg-green-100 text-green-800' },
            'COM': { name: 'COMMERCIAL', class: 'bg-blue-100 text-blue-800' },
            'IND': { name: 'INDUSTRIAL', class: 'bg-orange-100 text-orange-800' },
            'AG': { name: 'AGRICULTURAL', class: 'bg-lime-100 text-lime-800' },
            'KN': { name: 'KANO HERITAGE', class: 'bg-purple-100 text-purple-800' },
            'MISC': { name: 'MISCELLANEOUS', class: 'bg-gray-100 text-gray-800' },
            'SLTR': { name: 'STATUTORY RIGHT OF OCCUPANCY', class: 'bg-indigo-100 text-indigo-800' },
            'SIT': { name: 'SIT', class: 'bg-yellow-100 text-yellow-800' }
        };

        let displayText = landUsePrefix;
        let badgeClass = 'bg-gray-100 text-gray-800'; // Default color

        // Check for direct match or partial match
        for (const [key, config] of Object.entries(mapping)) {
            if (landUsePrefix.includes(key)) {
                displayText = config.name;
                badgeClass = config.class;
                break;
            }
        }

        return `<span class="px-2 inline-flex text-xs leading-5 font-bold rounded-full ${badgeClass}">${displayText}</span>`;
    }


    // --- Batch Viewing Logic (Moved to Top) ---

    // Expose to window to ensure availability
    window.viewBatch = function(batchNo) {
        if (!batchNo) {
            console.error('viewBatch called without batchNo');
            return;
        }
        console.log('Opening batch view for:', batchNo);

        const modal = document.getElementById('batchDetailsModal');
        const title = document.getElementById('batchModalTitle');
        const subtitle = document.getElementById('batchModalSubtitle');
        const tableBody = document.getElementById('batchDetailsTableBody');

        if (!modal) {
             window.alert('ERROR: batchDetailsModal NOT found!');
             console.error('CRITICAL: batchDetailsModal element NOT found in DOM!');
             return;
        }

        // Move modal to body if it's not already there to avoid clipping/containment issues
        if (modal.parentNode !== document.body) {
            console.log('Moving modal to document body for higher visibility...');
            document.body.appendChild(modal);
        }

        console.log('Modal found, forcing show...');
        console.log('Modal style before:', modal.getAttribute('style'));
        console.log('Modal classes before:', modal.className);

        title.innerText = `Batch Details: ${batchNo}`;
        subtitle.innerText = `Viewing all records grouped under batch ${batchNo}`;
        tableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><div class="flex items-center justify-center"><i data-lucide="loader" class="w-6 h-6 animate-spin mr-2"></i>Loading records...</div></td></tr>';

        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Force Show modal with ultra-aggressive styling
        modal.classList.remove('hidden');
        modal.style.setProperty('display', 'block', 'important');
        modal.style.setProperty('opacity', '1', 'important');
        modal.style.setProperty('visibility', 'visible', 'important');
        modal.style.setProperty('z-index', '999999', 'important');
        modal.style.setProperty('position', 'fixed', 'important');
        modal.style.setProperty('top', '0', 'important');
        modal.style.setProperty('left', '0', 'important');
        modal.style.setProperty('width', '100%', 'important');
        modal.style.setProperty('height', '100%', 'important');
        
        document.body.style.overflow = 'hidden';
        console.log('Modal display property set to:', modal.style.display);
        console.log('Modal rect:', modal.getBoundingClientRect());

        // Fetch batch records
        fetch('{{ route("mls-fileno.batch-records") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ batch_no: batchNo })
        })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data && result.data.length > 0) {
                    console.log(`Found ${result.data.length} records for batch ${batchNo}`);
                    renderBatchTable(result.data);
                } else {
                    console.warn('No records found for batch:', batchNo);
                    tableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">No records found for this batch.</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error fetching batch records:', error);
                tableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">Failed to load batch data.</td></tr>';
            });
    }; // End viewBatch

    function renderBatchTable(records) {
        const tableBody = document.getElementById('batchDetailsTableBody');
        tableBody.innerHTML = '';

        records.forEach(record => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 transition-colors cursor-default';
            row.innerHTML = `
                <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">${(record.mlsfNo || record.full_file_number || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.file_name || record.FileName || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                    <div class="flex flex-col space-y-1">
                        <div>${formatLandUse(record.land_use, record.full_file_number || record.mlsfNo)}</div>
                        <div class="text-[10px] text-gray-400 font-bold tracking-tight">${(record.purpose_name && record.purpose_name !== 'N/A') ? record.purpose_name.toUpperCase() : '-'}</div>
                    </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.plot_no || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.location || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${(record.lga || 'N/A').toUpperCase()}</td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium space-x-2">
                    <button onclick="editRecord(${record.filenumber_id})" class="inline-flex p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Record">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                    </button>
                    <button onclick="openFilePrinterManager('${record.filenumber_id}', '${record.mlsfNo || record.full_file_number || ''}', '${record.batch_no}', true)" class="inline-flex p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Print Document">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });

        // Re-initialize Lucide icons for the new buttons
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    window.closeBatchDetailsModal = function() {
        const modal = document.getElementById('batchDetailsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            modal.style.opacity = '0';
            modal.style.visibility = 'hidden';
        }
        document.body.style.overflow = '';
    }

    // Server-side role flag, exposed once for JS-side conditional logic
    window.MLSF_IS_ADMIN = {{ (Auth::user() && Auth::user()->assign_role === 'Supper Admin') ? 'true' : 'false' }};

    $(document).ready(function () {
        resetTrackingIdDisplay('--');
        setActionButtonsDisabled(true);

        // Conditionally-prepended checkbox column (Supper Admin only)
        const mlsfCheckboxColumn = {
            data: 'id',
            name: 'select',
            title: '',
            orderable: false,
            searchable: false,
            className: 'text-center mlsf-select-cell',
            width: '32px',
            render: function (data, type, row) {
                if (type !== 'display') return data;
                const checked = window.mlsfSelectedIds && window.mlsfSelectedIds.has(String(data)) ? 'checked' : '';
                return '<input type="checkbox" class="mlsf-row-check w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500 cursor-pointer" value="' + data + '" ' + checked + '>';
            }
        };

        // Initialize DataTable with performance optimizations
        // Initialize DataTable
        table = $('#mlsfTable').DataTable({
            processing: true,
            serverSide: true,
            deferRender: true, // Improve performance for large datasets
            stateSave: false,
            stateLoadCallback: function() { return null; },
            ajax: {
                url: '{{ route("file-numbers.data") }}',
                type: 'GET',
                timeout: 120000, // 120 second timeout for large production datasets
                data: function (d) {
                    d.source = 'New'; // Filter for Generated records only
                    console.log('DataTables request:', d);
                    return d;
                },
                dataSrc: function (json) {
                    console.log('DataTables response:', json);
                    if (json.error) {
                        console.error('Server error:', json.error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            text: json.error,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                    const allRows = json.data || [];
                    return allRows;
                },
                error: function (xhr, error, code) {
                    console.error('DataTables AJAX error:', error);
                    console.error('Status:', xhr.status);
                    console.error('Response:', xhr.responseText);

                    let errorMessage = 'Failed to load file numbers. Please check your connection and try again.';

                    if (xhr.status === 500) {
                        errorMessage = 'Server error occurred. Please contact the administrator.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Data endpoint not found. Please contact the administrator.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Request timed out or connection was interrupted. Please retry.';
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Error Loading Data',
                        text: errorMessage,
                        confirmButtonColor: '#ef4444',
                        footer: `<small>Error Code: ${xhr.status} - ${error}</small>`
                    });
                }
            },
            // Optimize DOM structure for better performance
            dom: '<"top"flp>rt<"bottom"ip><"clear">',
            columns: (function () { const cols = [
                {
                    data: null,
                    title: 'S/N',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: 'customer_type',
                    name: 'customer_type',
                    title: 'Customer Type',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return `<span class="text-gray-700 font-bold">${data || 'N/A'}</span>`;
                    }
                },
                {
                    data: 'source',
                    name: 'source',
                    title: 'Source',
                    defaultContent: 'N/A',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        const sourceValue = (data || row.source || row.file_source || row.file_option || 'N/A').toString();
                        const srcLower = sourceValue.toLowerCase();
                        const isOP = srcLower.includes('op resettlement') || srcLower.includes('op direct allocation');
                        if (isOP) {
                            const subLabel = sourceValue.replace(/^op\s+/i, '').toUpperCase();
                            const subColor = srcLower.includes('direct') ? 'color:#7c3aed' : 'color:#ea580c';
                            return `<div style="line-height:1.3">
                                <div style="font-size:12px;font-weight:700;color:#0369a1;white-space:nowrap">Occupancy Permit (OP)</div>
                                <div style="font-size:11px;font-weight:600;white-space:nowrap;${subColor}">${subLabel}</div>
                            </div>`;
                        }
                        return sourceValue !== 'N/A'
                            ? `<span style="display:inline-flex;align-items:center;padding:1px 8px;border-radius:9999px;font-size:12px;font-weight:600;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8">${sourceValue}</span>`
                            : 'N/A';
                    }
                },
                {
                    data: 'mlsfNo',
                    name: 'mlsfNo',
                    title: 'MLS File No',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        let display = data ? data.toUpperCase() : 'N/A';
                        const sourceTempFileno = row.source_temp_fileno ? String(row.source_temp_fileno).toUpperCase() : '';
                        const sourcePropId = row.source_prop_id || '';
                        const sourceCaptureId = row.source_instrument_capture_id || '';
                        const sourcePraId = row.source_pra_id || '';

                        // Format as range if batch
                        let isRange = false;
                        if (row.batch_no && row.batch_count > 1 && row.batch_first_file) {
                            const lastFile = display;
                            const firstFile = row.batch_first_file.toUpperCase();

                            // Extract parts separated by dash (Expect format like PREFIX-YEAR-SERIAL)
                            const lastParts = lastFile.split('-');
                            const firstParts = firstFile.split('-');

                            if (lastParts.length >= 2 && firstParts.length === lastParts.length) {
                                // Check if prefix parts (everything except last numeric) match
                                const lastPrefix = lastParts.slice(0, -1).join('-');
                                const firstPrefix = firstParts.slice(0, -1).join('-');

                                if (lastPrefix === firstPrefix) {
                                    const lastSerial = lastParts[lastParts.length - 1];
                                    const firstSerial = firstParts[firstParts.length - 1];

                                    if (firstSerial !== lastSerial) {
                                        display = `${lastPrefix}-${firstSerial}-${lastSerial}`;
                                        isRange = true;
                                    }
                                }
                            }
                        }

                        let html;
                        if (isRange) {
                            html = `<div class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-indigo-100 text-indigo-800 border border-indigo-200">
                                ${display}
                            </div>`;
                        } else {
                            html = `<div class="font-bold text-gray-900">${display}</div>`;
                        }
                        if (row.batch_no && row.batch_count > 1) {
                            html += `
                                <div class="mt-1">
                                    <button onclick="viewBatch('${row.batch_no}')" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors border border-blue-200">
                                        <i data-lucide="layers" class="w-3 h-3 mr-1"></i>
                                        Group (${row.batch_count})
                                    </button>
                                </div>`;
                            // Re-initialize Lucide icons after render
                            setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 100);
                        }

                        const canResolveSourceRow = !!(sourceCaptureId || sourcePraId);
                        if (sourceTempFileno && sourceTempFileno !== 'N/A' && canResolveSourceRow) {
                            html += `
                                <div class="mt-1">
                                    <button
                                        type="button"
                                        class="js-temp-file-link text-[11px] font-bold text-indigo-600 underline underline-offset-2 hover:text-indigo-800"
                                        data-prop-id="${sourcePropId}"
                                        data-source-capture-id="${sourceCaptureId}"
                                        data-source-pra-id="${sourcePraId}"
                                        data-temp-fileno="${sourceTempFileno}"
                                        data-new-mls-fileno="${display}"
                                        data-new-party-name="${String(row.FileName || '').replace(/\"/g, '&quot;')}"
                                    >
                                        ${sourceTempFileno}
                                    </button>
                                </div>`;
                        }

                        return html;
                    }
                },
                {
                    data: 'FileName',
                    name: 'FileName',
                    title: 'File Title',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'land_use',
                    name: 'land_use',
                    title: 'Land Use',
                    defaultContent: '-',
                    render: function (data, type, row) {
                        const landUseBadge = formatLandUse(data, row.mlsfNo);
                        const purpose = (row.purpose_name && row.purpose_name !== 'N/A') ? row.purpose_name.toUpperCase() : '-';
                        return `
                            <div class="flex flex-col space-y-1">
                                <div>${landUseBadge}</div>
                                <div class="text-[10px] text-gray-400 font-bold tracking-tight">${purpose}</div>
                            </div>
                        `;
                    }
                },
                {
                    data: 'tp_no',
                    name: 'tp_no',
                    title: 'TP No',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'plot_no',
                    name: 'plot_no',
                    title: 'Plot No',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'lga',
                    name: 'lga',
                    title: 'LGA',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'location',
                    name: 'location',
                    title: 'Location',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'created_by',
                    name: 'created_by',
                    title: 'Commissioned By',
                    defaultContent: 'System',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'SYSTEM';
                    }
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    title: 'Time Commissioned',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data !== 'N/A') {
                            const date = new Date(data);
                            return date.toLocaleTimeString('en-US', {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });
                        }
                        return data || 'N/A';
                    }
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    title: 'Date Commissioned',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data !== 'N/A') {
                            const date = new Date(data);
                            return date.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            });
                        }
                        return data || 'N/A';
                    }
                },
                {
                    data: null,
                    name: 'action',
                    title: 'Actions',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    width: '220px',
                    render: function (data, type, row) {
                        // Check if record has a batch_no
                        const hasBatchNo = row.batch_no && row.batch_no !== null && row.batch_no.trim() !== '';
                        const batchNo = hasBatchNo ? row.batch_no : '';

                        return `
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="openFilePrinterManager(${row.id}, '${row.mlsfNo}', '${batchNo}')"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors shadow-sm"
                                        title="Print">
                                    <i data-lucide="printer" class="w-4 h-4 mr-1.5"></i>
                                    <span class="text-xs font-semibold">Print</span>
                                </button>

                                <div class="action-dropdown shadow-sm border border-gray-200 rounded-lg">
                                    <button class="flex items-center space-x-2 px-3 py-1.5 bg-white text-gray-700 hover:bg-gray-50 transition-colors focus:outline-none" title="More actions">
                                        <i data-lucide="ellipsis" class="w-4 h-4 text-gray-400"></i>
                                    </button>
                                    <div class="action-dropdown-menu">
                                    <!-- Edit Record -->
                                    <button onclick="openEditModalFromAction(event, ${row.id}, '${row.type || ''}')"
                                            class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 flex items-center space-x-3">
                                        <i data-lucide="edit-3" class="w-4 h-4 text-slate-500"></i>
                                        <span class="font-medium">Edit</span>
                                    </button>

                                    <!-- Direct Allocation -->
                                    <button onclick="directAllocation(${row.id})" 
                                            class="w-full text-left px-4 py-2.5 text-sm text-green-700 hover:bg-green-50 flex items-center space-x-3">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-green-500"></i>
                                        <span class="font-medium">Update Allocation data</span>
                                    </button>

                                    <div class="border-t border-gray-100 my-1"></div>

                                    <!-- Delete Record -->
                                    @if(Auth::user() && Auth::user()->assign_role === 'Supper Admin')
                                    <button onclick="deleteRecord(${row.id})" 
                                            class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center space-x-3">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        <span class="font-medium">Delete Record</span>
                                    </button>
                                    @endif
                                </div>
                            </div>
                            </div>
                        `;
                    }
                }
            ]; if (window.MLSF_IS_ADMIN) cols.unshift(mlsfCheckboxColumn); return cols; })(),
            order: [[window.MLSF_IS_ADMIN ? 12 : 11, 'desc']],
            pageLength: 20,
            lengthMenu: [[10, 20, 25, 50, 100], [10, 20, 25, 50, 100]],
            responsive: true,
            scrollCollapse: true,
            scroller: {
                displayBuffer: 9
            },
            searchDelay: 500, // Delay search to reduce server requests
            language: {
                processing: '<div class="flex items-center justify-center"><i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i>Loading file numbers...</div>',
                emptyTable: '<div class="text-center py-8"><div class="text-gray-400 mb-2"><i data-lucide="database" class="w-12 h-12 mx-auto mb-2"></i></div><h3 class="text-lg font-medium text-gray-900 mb-1">No file numbers found</h3><p class="text-gray-500">Start by generating your first MLS file number using the button above.</p></div>',
                zeroRecords: '<div class="text-center py-8"><div class="text-gray-400 mb-2"><i data-lucide="search" class="w-12 h-12 mx-auto mb-2"></i></div><h3 class="text-lg font-medium text-gray-900 mb-1">No matching records found</h3><p class="text-gray-500">Try adjusting your search criteria.</p></div>',
                info: "Showing _START_ to _END_ of _TOTAL_ file numbers",
                infoEmpty: "No file numbers available",
                infoFiltered: "(filtered from _MAX_ total file numbers)",
                lengthMenu: "Show _MENU_ file numbers per page",
                search: "Search file numbers:",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            drawCallback: function (settings) {
                // Optimized icon initialization - use requestAnimationFrame for better performance
                requestAnimationFrame(function () {
                    lucide.createIcons();
                });
                if (window.MLSF_IS_ADMIN && typeof refreshSelectAllState === 'function') {
                    refreshSelectAllState();
                }
            },
            initComplete: function (settings, json) {
                console.log('DataTable initialized:', {
                    recordsTotal: json?.recordsTotal || 0,
                    recordsFiltered: json?.recordsFiltered || 0,
                    dataLength: json?.data?.length || 0
                });

                // Show a message if no data is available
                if (json && json.recordsTotal === 0) {
                    console.log('No records found in database');
                }
            }
        });

        // Get next serial number
        getNextSerialNumber();

        // Load existing file numbers for extension dropdown
        loadExistingFileNumbers();

        async function showTempFileDetailsByPropId(propId, sourceCaptureId, sourcePraId, tempFilenoLabel, newMlsFilenoLabel, newPartyName) {
            if (!propId && !sourceCaptureId && !sourcePraId) return;

            Swal.fire({
                title: 'Loading Temp File Details...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const qs = new URLSearchParams();
                if (sourceCaptureId) {
                    qs.set('source_capture_id', String(sourceCaptureId));
                } else if (sourcePraId) {
                    qs.set('pra_id', String(sourcePraId));
                } else if (propId) {
                    // Last-resort fallback only; can be ambiguous when prop_id was
                    // incorrectly reused across multiple OP source rows.
                    qs.set('prop_id', String(propId));
                }

                const res = await fetch(`/mls-fileno/temp-file-details?${qs.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const body = await res.json();

                if (!res.ok || !body || body.success !== true || !body.data) {
                    throw new Error((body && body.message) ? body.message : 'Temp file details not found.');
                }
 
                const row = body.data;
                const sourceLabelMap = {
                    instrument_capture: 'Instrument Capture',
                    pra: 'PRA Fallback'
                };
                const sourceLabel = sourceLabelMap[String(body.source || '').toLowerCase()] || 'Unknown';
                const esc = (v) => {
                    const s = String(v ?? '—');
                    return s
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                };

                const normalizeText = (value, mode = 'general') => {
                    const raw = String(value ?? '').trim();
                    if (!raw) return '—';

                    const title = raw
                        .toLowerCase()
                        .replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });

                    if (mode === 'name' || mode === 'location') {
                        return title;
                    }

                    if (mode === 'land_use') {
                        const map = {
                            RES: 'Residential',
                            RESIDENTIAL: 'Residential',
                            COM: 'Commercial',
                            COMMERCIAL: 'Commercial',
                            IND: 'Industrial',
                            INDUSTRIAL: 'Industrial',
                            AGR: 'Agricultural',
                            AGRICULTURAL: 'Agricultural'
                        };
                        return map[raw.toUpperCase()] || title;
                    }

                    return title
                        .replace(/\bOp\b/g, 'OP')
                        .replace(/\bLga\b/g, 'LGA')
                        .replace(/\bTp\b/g, 'TP');
                };

                const rawTempParty1 = String(row.party_1_name ?? '').trim();
                const rawTempParty2 = String(row.party_2_name ?? '').trim();
                const rawNewPartyName = String(newPartyName ?? '').trim();
                const normalizeName = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');

                // A valid source_pra_id can exist for normal OP commissioning too.
                // Treat it as change-of-name only when the entered new name actually differs.
                const isChangeOfName = rawNewPartyName !== ''
                    && rawTempParty2 !== ''
                    && normalizeName(rawNewPartyName) !== normalizeName(rawTempParty2);

                const tempParty1 = normalizeText(rawTempParty1, 'name');
                const tempParty2 = normalizeText(rawTempParty2, 'name');
                const newAppParty1 = isChangeOfName
                    ? normalizeText(rawTempParty2, 'name')
                    : tempParty1;
                const newAppParty2 = isChangeOfName
                    ? normalizeText(rawNewPartyName, 'name')
                    : normalizeText(rawTempParty2 || rawNewPartyName, 'name');

                const html = `
                    <div class="text-left text-sm font-sans">
                        <div class="hidden">
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                                Prop ID: <strong class="text-slate-700 ml-0.5">${esc(row.prop_id)}</strong>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
                                Source: <strong class="text-slate-700 ml-0.5">${esc(sourceLabel)}</strong>
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-sky-600 text-white font-extrabold text-sm">1</span>
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-sky-700">Occupancy Permit</span>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Temp File No</div><div class="font-mono font-bold text-slate-800 text-xs">${esc(row.temp_fileno)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-orange-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 7h10M7 12h10M7 17h5"/><rect x="2" y="2" width="20" height="20" rx="3"/></svg>
                                        <div><div class="text-[10px] text-slate-400">OP Type</div><div class="font-semibold text-orange-700 text-xs">${esc(normalizeText(row.op_type))}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h8m-8 6h16"/></svg>
                                        <div><div class="text-[10px] text-slate-400">OP Serial No</div><div class="font-bold text-slate-800 text-xs">${esc(row.op_serial_number)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Registration No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.registration_number)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.orig_party_1_name || row.party_1_name, 'name'))}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.orig_party_2_name || row.party_2_name, 'name'))}</div></div>
                                    </div>
                                    <div class="col-span-2 flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Property</div><div class="font-semibold text-slate-800 text-xs leading-snug">${esc(normalizeText(row.property_description || row.property_location, 'location'))}</div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-600 text-white font-extrabold text-sm">2</span>
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-emerald-700">Transfer of Title</span>
                                </div>
                                <div class="mb-3 flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <div><div class="text-[10px] text-slate-400">New MLS File No</div><div class="font-mono font-extrabold text-emerald-800 text-sm">${esc(newMlsFilenoLabel || row.mlsFNo)}</div></div>
                                </div>
                                <div class="mb-3 flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h8m-8 6h16"/></svg>
                                    <div><div class="text-[10px] text-slate-400">OP Serial No</div><div class="font-bold text-emerald-800 text-xs">${esc(row.op_serial_number)}</div></div>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-xs">${esc(newAppParty1)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-xs">${esc(newAppParty2)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h4l3 9 4-16 3 7h4"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Land Use</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.land_use, 'land_use'))}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                        <div><div class="text-[10px] text-slate-400">TP No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.tp_no)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="15" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                                        <div><div class="text-[10px] text-slate-400">Plot No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.plot_number)}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7m-14 0v8a1 1 0 001 1h3m10-9l2 2m-2-2v8a1 1 0 01-1 1h-3m-6 0l6-6"/></svg>
                                        <div><div class="text-[10px] text-slate-400">LGA</div><div class="font-semibold text-slate-800 text-xs">${esc(normalizeText(row.lga))}</div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                Swal.fire({
                    icon: 'info',
                    title: `Occupancy Permit (OP) Details (${esc(tempFilenoLabel || row.temp_fileno || '—')}/${esc(newMlsFilenoLabel || row.mlsFNo || '—')})`,
                    html,
                    width: 900,
                    confirmButtonText: 'Close',
                });
            } catch (err) {
                Swal.fire('Error', err.message || 'Unable to load temp file details.', 'error');
            }
        }

        $(document).off('click.tempFileDetails', '.js-temp-file-link').on('click.tempFileDetails', '.js-temp-file-link', function (e) {
            e.preventDefault();
            const propId = $(this).data('prop-id');
            const sourceCaptureId = $(this).data('source-capture-id');
            const sourcePraId = $(this).data('source-pra-id');
            const tempFileno = $(this).data('temp-fileno');
            const newMlsFileno = $(this).data('new-mls-fileno');
            const newPartyName = $(this).data('new-party-name');
            showTempFileDetailsByPropId(propId, sourceCaptureId, sourcePraId, tempFileno, newMlsFileno, newPartyName);
        });
    });

    function closeGenerateModal() {
        document.getElementById('generateModal').classList.add('hidden');
    }

    function resetForm() {
        // Reset the main form
        const form = document.getElementById('generateForm');
        if (form) {
            form.reset();
        }

        // Reset Alpine.js component data
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            const component = modalContainer._x_dataStack[0];

            // Reset all form fields to initial values
            component.applicationType = 'new';
            component.appTypeRadio = 'new';
            component.allocatedByFilter = null;
            component.defaultAllocationType = '';
            component._currentAllocationSourceType = 'default';
            component.fileName = '';
            component.landUse = '';
            component.fileOption = '';
            // Without this the top FILE TYPE select keeps displaying the previous choice
            // while fileOption reads '' — the label and the submitted value then disagree.
            component.fileTypeWorkflow = '';
            component.existingFileNo = '';
            component.middlePrefix = 'KN';
            component.year = new Date().getFullYear();
            component.serialNo = nextSerialNo;
            component.preview = '-';
            component.sourceInstrumentCaptureId = '';
            component.sourcePropId = '';
            component.sourcePraId = '';
            component.sourceOpSerialNumber = '';
            component.sourceRegistrationNumber = '';
            component.sourceSerialNo = '';
            component.sourcePageNo = '';
            component.sourceVolumeNo = '';
            component.sourceOriginalOwner = '';
            component.requireOpSource = false;
            component.subSource = '';

            // Reset related file fields
            component.relatedFileNo = '';
            component.relatedFileTitle = '';
            component.relatedFileIndexingId = '';
            component.isRecertificationPrefix = false;

            // Reset old file fields (Re-Issuance of FileNo)
            component.oldFileNo = '';
            component.oldFileTitle = '';
            component.oldFileIndexingId = '';

            // Update the preview
            component.updatePreview();
        }

        // Reset override mode
        isOverrideMode = false;

        // Reset form field states
        const yearInput = document.getElementById('year');
        const serialInput = document.getElementById('serialNo');

        if (yearInput) {
            yearInput.readOnly = true;
            yearInput.classList.remove('bg-white', 'text-gray-900');
            yearInput.classList.add('bg-gray-100', 'text-gray-600');
            yearInput.value = new Date().getFullYear();
        }

        if (serialInput) {
            serialInput.readOnly = true;
            serialInput.classList.remove('bg-white', 'text-gray-900');
            serialInput.classList.add('bg-gray-100', 'text-gray-600');
            serialInput.value = nextSerialNo;
        }

        // Reset radio buttons to default (Direct Allocation)
        const newRadio = document.querySelector('input[name="application_type"][value="new"]');
        if (newRadio) {
            newRadio.checked = true;
        }

        // Reset allocation source radio to Default
        const defaultSourceRadio = document.querySelector('input[name="allocation_source_type"][value="default"]');
        if (defaultSourceRadio) {
            defaultSourceRadio.checked = true;
        }

        // Clear all text inputs
        const textInputs = form.querySelectorAll('input[type="text"]:not([disabled])');
        textInputs.forEach(input => {
            if (input.id !== 'middlePrefix') { // Keep middle prefix as 'KN'
                input.value = '';
            }
        });

        // Reset all select dropdowns
        const selects = form.querySelectorAll('select');
        selects.forEach(select => {
            select.selectedIndex = 0;
        });

        // Reset hidden sections
        document.getElementById('middlePrefixSection')?.classList.add('hidden');
        document.getElementById('yearSection')?.classList.add('hidden');

        // Reset manual input toggles to default (use dropdown) - Alpine.js will handle this automatically
        // when the component data is reset, but we can also trigger it manually
        setTimeout(() => {
            const extensionInputRadios = form.querySelectorAll('input[name="extension_input_type"][value="false"]');
            extensionInputRadios.forEach(radio => {
                radio.checked = true;
            });

            const temporaryInputRadios = form.querySelectorAll('input[name="temporary_input_type"][value="false"]');
            temporaryInputRadios.forEach(radio => {
                radio.checked = true;
            });
        }, 100);

        // Update the preview one more time
        updatePreview();
        resetTrackingIdDisplay('--');
        setActionButtonsDisabled(true);
    }

    function updateApplicationType(type) {
        const newOptions = document.getElementById('newOptions');
        const conversionOptions = document.getElementById('conversionOptions');
        const rcOptions = document.querySelector('optgroup[label="RC Options"]');
        const landUseSelect = document.getElementById('landUse');

        if (type === 'new') {
            newOptions.style.display = 'block';
            conversionOptions.style.display = 'none';
            rcOptions.style.display = 'block';
        } else {
            newOptions.style.display = 'none';
            conversionOptions.style.display = 'block';
            rcOptions.style.display = 'none'; // Hide RC options for conversion
        }

        // Reset land use selection
        landUseSelect.value = '';
        updatePreview();
    }

    // Separate function for just updating preview without form manipulation
    function updatePreviewOnly() {
        const serialNo = document.getElementById('serialNo')?.value;
        const year = document.getElementById('year')?.value;
        const landUse = document.getElementById('landUse')?.value;
        const fileOption = document.getElementById('fileOption')?.value;

        // Get existing file number from Alpine.js component instead of DOM
        let existingFileNo = '';
        let extensionType = 'file';
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            existingFileNo = modalContainer._x_dataStack[0].existingFileNo;
            extensionType = modalContainer._x_dataStack[0].extensionType || 'file';
        }

        const middlePrefix = document.getElementById('middlePrefix')?.value || '';
        const preview = document.getElementById('mlsfPreview');

        // Preview generation logic only
        let previewText = '-';

        if (fileOption === 'extension' && existingFileNo) {
            const baseExt = existingFileNo.trim().replace(/-$/, '');
            previewText = extensionType === 'plot' ? baseExt : baseExt + ' AND EXTENSION';
        } else if (fileOption === 'temporary' && existingFileNo) {
            previewText = existingFileNo + '(T)';
        } else if (fileOption === 'miscellaneous' && middlePrefix && serialNo && year) {
            previewText = `MISC-${middlePrefix}-${year}-${serialNo}`;
        } else if (fileOption === 'old_mls' && serialNo) {
            previewText = `KN ${serialNo}`;
        } else if (fileOption === 'sltr' && serialNo) {
            previewText = `SLTR-${serialNo}`;
        } else if (fileOption === 'sit' && serialNo) {
            previewText = `SIT-${year}-${serialNo}`;
        } else if ((fileOption === 'normal' || fileOption === 'regrant' || fileOption === 'resettlement') && serialNo && year && landUse) {
            previewText = `${landUse}-${year}-${serialNo}`;
        }

        if (preview) {
            preview.textContent = previewText;

            if (previewText !== '-') {
                preview.classList.remove('text-gray-400');
                preview.classList.add('text-green-600');
            } else {
                preview.classList.remove('text-green-600');
                preview.classList.add('text-gray-400');
            }
        }

        const lookupCandidate = deriveGroupingLookupCandidate(fileOption, previewText, existingFileNo);
        if (lookupCandidate) {
            queueGroupingLookup(lookupCandidate);
        } else {
            resetTrackingIdDisplay('--');
        } 
    }

    async function updatePreview() {
        const serialNoField = document.getElementById('serialNo');
        const year = document.getElementById('year')?.value;
        const landUse = document.getElementById('landUse')?.value;
        const prefix = document.getElementById('prefix')?.value; // Get Prefix
        const fileOption = document.getElementById('fileOption')?.value;
        let serialNo = serialNoField?.value;

        // Get existing file number from Alpine.js component instead of DOM
        let existingFileNo = '';
        let extensionType = 'file';
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            existingFileNo = modalContainer._x_dataStack[0].existingFileNo;
            extensionType = modalContainer._x_dataStack[0].extensionType || 'file';
        }

        const middlePrefix = document.getElementById('middlePrefix')?.value || '';
        const preview = document.getElementById('mlsfPreview');
        const serialDescription = document.getElementById('serialNoDescription');

        // Only call updateGenerateForm if elements exist and not during text input for miscellaneous files
        if (document.getElementById('middlePrefixSection') &&
            document.getElementById('yearSection')) {
            // Don't reset form if we're actively typing in miscellaneous serial field
            const isTypingInMiscSerial = fileOption === 'miscellaneous' &&
                document.activeElement === serialNoField &&
                serialNoField.getAttribute('data-text-field') === 'true';

            if (!isTypingInMiscSerial) {
                updateGenerateForm(fileOption);
            }
        }

        // Attempt reservation for normal flow
        const hasCode = prefix || landUse;
        const batchModeToggled = document.querySelector('[x-model="batchMode"]')?.checked;
        const batchQty = parseInt(document.getElementById('batchQuantity')?.value || '1');

        if ((fileOption === 'normal' || fileOption === 'regrant' || fileOption === 'resettlement') && hasCode && year && !isOverrideMode) {
            try {
                if (typeof commissionModalReservation !== 'undefined') {
                    if (batchModeToggled && batchQty > 1) {
                        const batchResult = await commissionModalReservation.reserveBatch(prefix || landUse, landUse || prefix, year, batchQty);
                        if (batchResult?.success) {
                            serialNo = batchResult.startSerial;
                            if (serialNoField) {
                                serialNoField.value = batchResult.startSerial;
                            }
                            if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
                                const alpineData = modalContainer._x_dataStack[0];
                                alpineData.serialNo = batchResult.startSerial;
                                alpineData.serialRangePreview = `${batchResult.startSerial} to ${batchResult.endSerial}`;
                            }
                        }
                    } else {
                        const reserveResult = await commissionModalReservation.reserve(prefix || landUse, landUse || prefix, year);
                        if (reserveResult?.success) {
                            serialNo = reserveResult.serialNumber;
                            if (serialNoField) {
                                serialNoField.value = reserveResult.serialNumber;
                            }
                            if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
                                modalContainer._x_dataStack[0].serialNo = reserveResult.serialNumber;
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('[Reservation] Failed to reserve serial', error);
            }
        }

        // Preview generation logic - exact same as capture_existing
        let previewText = '-';

        // Generate base file number for extension/temporary fallback
        let baseFileNumber = '';
        if (serialNo && year) {
            const code = prefix || landUse;
            if (code) {
                baseFileNumber = `${code}-${year}-${serialNo}`;
            }
        }

        if (fileOption === 'extension') {
            const baseExt = (existingFileNo || baseFileNumber).trim().replace(/-$/, '');
            previewText = extensionType === 'plot' ? baseExt : baseExt + ' AND EXTENSION';
        } else if (fileOption === 'temporary') {
            previewText = (existingFileNo || baseFileNumber) + '(T)';
        } else if (fileOption === 'miscellaneous' && middlePrefix && serialNo && year) {
            previewText = `MISC-${middlePrefix}-${year}-${serialNo}`;
        } else if (fileOption === 'old_mls' && serialNo) {
            previewText = `KN ${serialNo}`;
        } else if (fileOption === 'sltr' && serialNo) {
            previewText = `SLTR-${serialNo}`;
        } else if (fileOption === 'sit' && serialNo) {
            previewText = `SIT-${year}-${serialNo}`;
        } else if ((fileOption === 'normal' || fileOption === 'regrant' || fileOption === 'resettlement') && serialNo && year) {
            const code = prefix || landUse;
            if (code) {
                if (batchModeToggled && batchQty > 1) {
                    const endSerial = parseInt(serialNo) + batchQty - 1;
                    previewText = `${code}-${year}-${serialNo} to ${code}-${year}-${endSerial}`;
                } else {
                    previewText = `${code}-${year}-${serialNo}`;
                }
            }
        }

        preview.textContent = previewText;

        // Update Alpine.js preview property (only if not in batch mode)
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            const alpineData = modalContainer._x_dataStack[0];
            if (!alpineData.batchMode) {
                alpineData.preview = previewText;
            }
        }

        if (previewText !== '-') {
            preview.classList.remove('text-gray-400');
            preview.classList.add('text-green-600');
        } else {
            preview.classList.remove('text-green-600');
            preview.classList.add('text-gray-400');
        }

        const lookupCandidate = deriveGroupingLookupCandidate(fileOption, previewText, existingFileNo);
        if (lookupCandidate) {
            queueGroupingLookup(lookupCandidate);
        } else {
            resetTrackingIdDisplay('--');
        }
    }

    // Add the dedicated form update function like capture_existing
    function updateGenerateForm(type) {
        const middlePrefixSection = document.getElementById('middlePrefixSection');
        const yearSection = document.getElementById('yearSection');
        const serialNoField = document.getElementById('serialNo');
        const serialDescription = document.getElementById('serialNoDescription');

        // Hide all sections first
        middlePrefixSection.classList.add('hidden');
        yearSection.classList.add('hidden');

        // Reset serial number field properties and remove all restrictive attributes
        serialNoField.type = 'text';
        serialNoField.removeAttribute('min');
        serialNoField.removeAttribute('max');
        serialNoField.removeAttribute('step');
        serialNoField.removeAttribute('maxlength');
        serialNoField.removeAttribute('pattern');

        // Reset text field tracking attribute
        serialNoField.removeAttribute('data-text-field');

        if (type === 'normal' || type === 'regrant' || type === 'resettlement') {
            yearSection.classList.remove('hidden');
            // For normal/regrant/resettlement files, keep serial as number for auto-padding
            serialNoField.type = 'number';
            serialNoField.setAttribute('min', '1');
            serialNoField.setAttribute('max', '9999');
            serialNoField.placeholder = 'Auto-generated';
            serialNoField.readOnly = true;
            serialNoField.classList.add('bg-gray-100', 'text-gray-600');
            serialNoField.classList.remove('bg-white', 'text-gray-900');
            serialNoField.value = nextSerialNo;

            if (serialDescription) {
                serialDescription.textContent = 'Auto-generated';
                serialDescription.className = 'text-xs text-gray-500 mt-1';
            }
        } else if (type === 'temporary') {
            // Temporary files use the toggle section in right column
            serialNoField.placeholder = 'Not required for temporary files';
            serialNoField.value = '';
            serialNoField.readOnly = true;
            serialNoField.classList.add('bg-gray-100', 'text-gray-600');
            serialNoField.classList.remove('bg-white', 'text-gray-900');

            if (serialDescription) {
                serialDescription.textContent = 'Select existing file for temporary version';
                serialDescription.className = 'text-xs text-blue-600 mt-1 font-medium';
            }
        } else if (type === 'extension') {
            // Extension files use the toggle section in right column
            yearSection.classList.remove('hidden');
            serialNoField.placeholder = 'Not required for extensions';
            serialNoField.value = '';
            serialNoField.readOnly = true;
            serialNoField.classList.add('bg-gray-100', 'text-gray-600');
            serialNoField.classList.remove('bg-white', 'text-gray-900');

            if (serialDescription) {
                serialDescription.textContent = 'Not required for extensions';
                serialDescription.className = 'text-xs text-gray-500 mt-1';
            }
        } else if (type === 'miscellaneous' || type === 'sltr' || type === 'sit' || type === 'old_mls') {
            if (type === 'miscellaneous') {
                middlePrefixSection.classList.remove('hidden');
                yearSection.classList.remove('hidden');
            } else if (type === 'sit') {
                yearSection.classList.remove('hidden');
            }
            // Make serial number plain text and editable for these types
            serialNoField.type = 'text';
            serialNoField.readOnly = false;
            serialNoField.classList.remove('bg-gray-100', 'text-gray-600');
            serialNoField.classList.add('bg-white', 'text-gray-900');

            // Only clear value if it's not already in a text field state (initial setup)
            if (serialNoField.getAttribute('data-text-field') !== 'true') {
                serialNoField.value = '';
                serialNoField.setAttribute('data-text-field', 'true');
            }

            // Completely remove all input restrictions for text fields
            serialNoField.removeAttribute('min');
            serialNoField.removeAttribute('max');
            serialNoField.removeAttribute('step');
            serialNoField.removeAttribute('maxlength');
            serialNoField.removeAttribute('pattern');
            serialNoField.setAttribute('inputmode', 'text');

            if (type === 'miscellaneous') {
                serialNoField.placeholder = 'Enter custom serial (e.g., 001, ABC123)';
            } else if (type === 'sltr') {
                serialNoField.placeholder = 'Enter SLTR serial (e.g., 001, 2024-001)';
            } else if (type === 'sit') {
                serialNoField.placeholder = 'Enter SIT serial (e.g., 001, 2024-001)';
            } else if (type === 'old_mls') {
                serialNoField.placeholder = 'Enter Old MLS number (e.g., 5467, 34874857488758)';
            }

            if (serialDescription) {
                serialDescription.textContent = 'Manual entry';
                serialDescription.className = 'text-xs text-blue-600 mt-1 font-medium';
            }
        }
    }

    function loadExistingFileNumbers() {
        fetch('{{ route("file-numbers.existing") }}')
            .then(response => response.json())
            .then(data => {
                // Populate extension dropdown
                const extensionSelect = document.getElementById('extensionFileNo');
                if (extensionSelect) {  
                    extensionSelect.innerHTML = '<option value="">Select existing file number...</option>';

                    data.forEach(fileNo => {
                        const option = document.createElement('option');
                        option.value = fileNo.mlsfNo;
                        option.textContent = fileNo.mlsfNo;
                        extensionSelect.appendChild(option);
                    });
                }

                // Populate temporary dropdown
                const temporarySelect = document.getElementById('temporaryFileNo');
                if (temporarySelect) {
                    temporarySelect.innerHTML = '<option value="">Select existing file number...</option>';

                    data.forEach(fileNo => {
                        const option = document.createElement('option');
                        option.value = fileNo.mlsfNo;
                        option.textContent = fileNo.mlsfNo;
                        temporarySelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading existing file numbers:', error);
            });
    }

    // Store serial numbers per land use
    let serialNumbersMap = {};

    function getNextSerialNumber() {
        // Fetch serial status for all land uses
        fetch('{{ route("mls-fileno.serial-status") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    // Build map of land use to next serial
                    serialNumbersMap = {};
                    data.data.forEach(item => {
                        // Next serial is last_serial + 1
                        serialNumbersMap[item.land_use] = item.last_serial + 1;
                    });
                    console.log('Serial numbers map loaded for year ' + (data.data[0] ? data.data[0].year : ''), serialNumbersMap);

                    // Update the modal if it's open
                    const modalElement = document.getElementById('generateModal');
                    if (modalElement && !modalElement.classList.contains('hidden')) {
                        updateAlpineSerialNumber();
                    }
                } else {
                    console.error('Failed to load serial numbers:', data.message);
                }
            })
            .catch(error => {
                console.error('Error getting serial numbers:', error);
                serialNumbersMap = {}; // Reset to empty map
            });
    }

    // Function to get next serial for specific land use
    function getNextSerialForLandUse(landUse) {
        if (!landUse) return 1;
        // Remove any prefix like "CON-" to get the base land use
        const baseLandUse = landUse.replace(/^CON-/, '');
        const nextSerial = serialNumbersMap[landUse] || serialNumbersMap[baseLandUse] || 1;
        console.log(`Next serial for ${landUse}:`, nextSerial);
        return nextSerial;
    }

    // Function to update Alpine.js component with new serial number
    function updateAlpineSerialNumber() {
        // Try to update via Alpine component method
        const modalContainer = document.querySelector('[x-data^="fileNumberGenerator"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            const component = modalContainer._x_dataStack[0];
            if (component.refreshSerialNumber) {
                component.refreshSerialNumber();
                return;
            }
        }

        // Fallback to direct DOM manipulation
        const serialNoElement = document.getElementById('serialNo');
        if (serialNoElement && nextSerialNo && !isOverrideMode) {
            serialNoElement.value = nextSerialNo;
            // Trigger both input and change events to ensure Alpine.js updates
            serialNoElement.dispatchEvent(new Event('input', { bubbles: true }));
            serialNoElement.dispatchEvent(new Event('change', { bubbles: true }));
            updatePreview();
        }
    }

    function showOverrideModal() {
        document.getElementById('overrideModal').classList.remove('hidden');
        document.getElementById('overrideYear').value = document.getElementById('year').value;
        document.getElementById('overrideSerialNo').value = document.getElementById('serialNo').value;
    }

    function closeOverrideModal() {
        document.getElementById('overrideModal').classList.add('hidden');
    }



    
    function submitOverrideForm(event) {
        event.preventDefault();

        const overrideYear = document.getElementById('overrideYear').value;
        const overrideSerialNo = document.getElementById('overrideSerialNo').value;
        const overrideExtension = document.getElementById('overrideExtension').checked;

        // Apply override values to main form
        const yearInput = document.getElementById('year');
        const serialInput = document.getElementById('serialNo');

        yearInput.value = overrideYear;
        serialInput.value = overrideSerialNo;

        // Enable manual editing
        isOverrideMode = true;
        yearInput.readOnly = false;
        serialInput.readOnly = false;
        yearInput.classList.remove('bg-gray-100', 'text-gray-600');
        serialInput.classList.remove('bg-gray-100', 'text-gray-600');
        yearInput.classList.add('bg-white', 'text-gray-900');
        serialInput.classList.add('bg-white', 'text-gray-900');

        // Trigger events to update Alpine.js state
        yearInput.dispatchEvent(new Event('input', { bubbles: true }));
        serialInput.dispatchEvent(new Event('input', { bubbles: true }));

        if (overrideExtension) {
            document.querySelector('input[name="file_option"][value="extension"]').checked = true;
        }

        updatePreview();
        closeOverrideModal();
    }

    function openMigrationModal() {
        document.getElementById('migrationModal').classList.remove('hidden');
    }

    function closeMigrationModal() {
        document.getElementById('migrationModal').classList.add('hidden');
    }

    function submitMigrationForm(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        // Show global loading
        showGlobalLoading('Migrating data... Please wait.');

        const formData = new FormData(document.getElementById('migrationForm'));

        fetch('{{ route("file-numbers.migrate") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    });
                    closeMigrationModal();
                    table.ajax.reload();
                    updateStats();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred during migration',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while migrating data',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function submitForm(event) {
        event.preventDefault();

        // Check for batch mode
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        let alpineData = null;
        if (modalContainer && modalContainer._x_dataStack) {
            alpineData = modalContainer._x_dataStack[0];

            // If batch mode is active, show summary modal instead
            if (alpineData.batchMode) {
                showBatchSummaryModal(alpineData);
                return;
            }

            // Plot Extension: isolated workflow that keeps the original file number.
            // Routed to its own endpoint instead of the standard generate flow.
            if (alpineData.fileOption === 'extension' && alpineData.extensionType === 'plot') {
                submitPlotExtension(event, alpineData);
                return;
            }
        }

        // LGA is required when Application Type is Conversion
        if (alpineData && alpineData.applicationType === 'conversion') {
            const lgaValue = (alpineData.lga || '').toString().trim();
            if (!lgaValue) {
                Swal.fire({
                    icon: 'warning',
                    title: 'LGA Required',
                    text: 'Please select an LGA for Conversion applications.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
        }

        // Related file is required for Recertification (-RC) prefixes
        if (alpineData && alpineData.isRecertificationPrefix) {
            const relatedFile = (alpineData.relatedFileNo || '').toString().trim();
            const relatedTitle = (alpineData.relatedFileTitle || '').toString().trim();
            if (!relatedFile) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Related File Required',
                    text: 'Please select the original file number that this recertification relates to.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
            if (!relatedTitle) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Title Required',
                    text: 'Please enter the file title for the related original file.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
        }

        // The old (duplicate) file number is what a Re-Issuance re-issues, so it is required.
        if (alpineData && alpineData.fileOption === 'reissuance') {
            const oldFileNo = (alpineData.oldFileNo || '').toString().trim();
            if (!oldFileNo) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Old FileNo Required',
                    text: 'Please select the old (duplicate) file number being re-issued.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
        }

        // File Name is required for all new file number generations
        {
            const fileNameValue = (alpineData && alpineData.fileName
                ? alpineData.fileName
                : (document.getElementById('fileName')?.value || '')).toString().trim();
            if (!fileNameValue) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Name Required',
                    text: 'Please enter a File Name before generating the file number.',
                    confirmButtonColor: '#f59e0b'
                }).then(() => {
                    const fileNameInput = document.getElementById('fileName');
                    if (fileNameInput) fileNameInput.focus();
                });
                return;
            }
        }

        // Gender is required for all file commissionings.
        {
            const genderValue = (alpineData && alpineData.gender
                ? alpineData.gender
                : (document.getElementById('gender')?.value || '')).toString().trim();
            if (!genderValue) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Gender Required',
                    text: 'Please select a Gender (Male or Female) before generating the file number.',
                    confirmButtonColor: '#f59e0b'
                }).then(() => {
                    const genderInput = document.getElementById('gender');
                    if (genderInput) genderInput.focus();
                });
                return;
            }
        }

        const submitBtn = event.submitter || event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';

        if (submitBtn) {
            showLoadingButton(submitBtn, originalText);
        }

        showGlobalLoading('Generating file number...');

        const formData = new FormData(document.getElementById('generateForm'));

        // The Direct Allocation / Conversion radios are bound to appTypeRadio (a
        // separate, purely visual property) so picking "Conversion" doesn't knock
        // the form out of Change of Purpose mode — see updateApplicationType().
        // That means the native radio's checked value in the DOM (what FormData
        // reads by default) no longer matches the real applicationType Alpine is
        // tracking, so it must be set explicitly here.
        if (alpineData) {
            formData.set('application_type', alpineData.applicationType);
        }

        // These two drive resolveSourceValue() on the backend (e.g. "OP Direct
        // Allocation" / "OP Resettlement" vs plain "Direct Allocation"), but
        // neither has a named form element in the DOM — without this they never
        // reach the server and every "new" application silently falls back to
        // the generic "Direct Allocation" source label, even when it was
        // commissioned from a captured Occupancy Permit.
        formData.set('allocated_by_filter', alpineData ? (alpineData.allocatedByFilter || '') : '');
        formData.set('default_allocation_type', alpineData ? (alpineData.defaultAllocationType || '') : '');

        // Typed related files (primary + any added via "Add Another Related File").
        // related_fileno still carries the primary number on its own for the existing
        // -RC / PRA lineage paths that read it.
        if (alpineData && typeof alpineData.relatedFilesPayload === 'function') {
            formData.set('related_files', JSON.stringify(alpineData.relatedFilesPayload()));
        }

        // Use prefix as land_use if available (for normal files)
        const prefix = alpineData ? alpineData.prefix : document.getElementById('prefix')?.value;
        const fileOption = alpineData ? alpineData.fileOption : document.getElementById('fileOption')?.value;
        // Alpine owns fileOption; post it unconditionally so the value can't diverge from
        // what the form shows (e.g. Re-grant silently arriving as "normal", which made the
        // backend resolve Source as "Direct Allocation").
        if (fileOption) {
            formData.set('file_option', fileOption);
        }
        if (prefix && (fileOption === 'normal' || fileOption === 'regrant' || fileOption === 'resettlement')) {
            formData.set('land_use', prefix);
        }

        // SIT files: force land_use to 'SIT' and customer_type to 'Government'
        if (fileOption === 'sit') {
            formData.set('land_use', 'SIT');
            formData.set('customer_type', 'Government');
        } else {
            // Reason only applies to SIT files; drop any stale value for other types
            formData.delete('sit_reason');
        }

        // Old (duplicated) file number only applies to Re-Issuance of FileNo;
        // drop any stale value for every other file type.
        if (fileOption === 'reissuance') {
            formData.set('old_fileno', (alpineData ? (alpineData.oldFileNo || '') : '').toString().trim());
        } else {
            formData.delete('old_fileno');
        }

        // Handle Direct Allocation (is_allocated check)
        if (alpineData && alpineData.applicationType === 'new' && alpineData.allocationId) {
            formData.set('allocation_id', alpineData.allocationId);
            // Ensure file_name is correctly set from Alpine if it wasn't picked up
            if (!formData.get('file_name')) {
                formData.set('file_name', alpineData.fileName);
            }
        }

        // Debug: Log the form data
        console.log('Form data being submitted:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }

        const trackingIdValue = (formData.get('tracking_id') || '').toString().trim();
        if (trackingIdValue === '') {
            hideGlobalLoading();
            if (submitBtn) {
                hideLoadingButton(submitBtn, originalText);
            }
            Swal.fire({
                icon: 'warning',
                title: 'Tracking ID Required',
                text: 'Tracking ID must be fetched from grouping table before generating this file number.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        let sourceCaptureId = (formData.get('source_instrument_capture_id') || '').toString().trim();
        const hasPreSyncedPraContext = !!(window.pendingExistingOpPraContext && window.pendingExistingOpPraContext.prop_id);
        if (hasPreSyncedPraContext && window.pendingExistingOpPraContext.source_pra_id) {
            formData.set('source_pra_id', String(window.pendingExistingOpPraContext.source_pra_id));
        }

        // Manual Capture Existing OP path: PRA has already been pre-synced before
        // opening commission modal, so source capture ID is optional here.
        if (hasPreSyncedPraContext && sourceCaptureId === '') {
            formData.set('require_op_source', '0');
            if (alpineData) {
                alpineData.requireOpSource = false;
            }
        }

        // OSS Change-of-Name commissioning should allow normal generation without OP linkage.
        const currentUrl = new URL(window.location.href);
        const isOssCommissioningFlow =
            currentUrl.pathname.includes('/lands-one-stop-shop/') ||
            currentUrl.searchParams.get('source') === 'lands-one-stop-shop';

        // The OSS posts to this same endpoint, so the resulting mls_file_no row is
        // otherwise indistinguishable from one raised here. Report the origin so the
        // server can stamp system_sub_type and keep OSS files out of the MLS file
        // list — see MlsFileNoController::resolveSystemSubType().
        formData.set('oss_commission', isOssCommissioningFlow ? '1' : '0');

        if (isOssCommissioningFlow && sourceCaptureId === '' && !hasPreSyncedPraContext) {
            formData.set('require_op_source', '0');
            if (alpineData) {
                alpineData.requireOpSource = false;
            }
        }

        if (alpineData && alpineData.requireOpSource === true && sourceCaptureId === '' && !hasPreSyncedPraContext && window.lastCommissionSourceOp?.id) {
            // Recover linkage when a duplicate commission modal/form instance exists.
            sourceCaptureId = String(window.lastCommissionSourceOp.id).trim();
            formData.set('source_instrument_capture_id', sourceCaptureId);
            formData.set('source_prop_id', window.lastCommissionSourceOp.prop_id || '');
            formData.set('source_op_serial_number', window.lastCommissionSourceOp.op_serial_number || '');
            formData.set('source_registration_number', window.lastCommissionSourceOp.registration_number || '');
            formData.set('source_serial_no', window.lastCommissionSourceOp.serial_no || '');
            formData.set('source_page_no', window.lastCommissionSourceOp.page_no || '');
            formData.set('source_volume_no', window.lastCommissionSourceOp.volume_no || '');
            formData.set('source_original_owner', window.lastCommissionSourceOp.original_owner || '');
            formData.set('require_op_source', '1');
        }

        if (alpineData && alpineData.requireOpSource === true && sourceCaptureId === '' && !hasPreSyncedPraContext && !isOssCommissioningFlow) {
            hideGlobalLoading();
            if (submitBtn) {
                hideLoadingButton(submitBtn, originalText);
            }
            Swal.fire({
                icon: 'warning',
                title: 'Source OP Required',
                text: 'Please capture/select the Occupancy Permit (OP) record first so commissioning can mirror to PRA, deed, and instrument tables.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // Single-file confirmation gate. Sits after every validation so the summary
        // is never shown for a submission that would have been rejected anyway.
        // confirmBatchGeneration() sets singleSummaryConfirmed and replays this event.
        if (!singleSummaryConfirmed && alpineData) {
            hideGlobalLoading();
            if (submitBtn) {
                hideLoadingButton(submitBtn, originalText);
            }
            showSingleSummaryModal(alpineData, event);
            return;
        }
        singleSummaryConfirmed = false;

        // Where the file goes next, chosen on the Generation Summary. The backend
        // turns it into the file's second tracking line.
        if (pendingSummaryDestination) {
            Object.entries(pendingSummaryDestination).forEach(([key, value]) => formData.set(key, value));
            pendingSummaryDestination = null;
        }

        // Send to new MLS generation endpoint
        const controller = new AbortController();
        const timeoutHandle = setTimeout(() => controller.abort(), GENERATE_REQUEST_TIMEOUT_MS);

        fetch('{{ route("mls-fileno.generate") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            signal: controller.signal
        })
            .then(response => response.json())
            .then(async data => {
                clearTimeout(timeoutHandle);
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }

                if (data.success) {
                    const generatedFileNumber = (
                        data?.data?.file_number ||
                        data?.data?.full_file_number ||
                        data?.data?.mlsfNo ||
                        data?.file_number ||
                        ''
                    ).toString().trim();
                    const commissionedFileName = (
                        data?.data?.file_name ||
                        data?.data?.FileName ||
                        alpineData?.fileName ||
                        ''
                    ).toString().trim();

                    // Confirm the active reservation so it is marked as used
                   
                    if (typeof commissionModalReservation !== 'undefined') {
                        try {
                            await commissionModalReservation.confirm();
                        } catch (e) {
                            console.warn('[Reservation] Confirm failed', e);
                        }
                    }


                    getNextSerialNumber();

                    // Clear cache to ensure fresh data on next load
                    fetch('{{ route("file-numbers.clear-cache") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    }).catch(e => console.warn('Cache clear failed:', e));

                    // If a new (non-existing) OP was captured, submit the PRA record
                    // now with the real commissioned file number.
                    if (typeof window.submitPendingNewOpPra === 'function' && window.pendingNewOpFormData) {
                        await window.submitPendingNewOpPra(generatedFileNumber);
                    }

                    // Existing OP flow: after temp-file PRA sync on capture, update PRA
                    // to the commissioned file number using the same prop_id.
                    // Grantor = original allottee (from source_op_party_2), NOT KSG.
                    if (typeof window.submitPendingExistingOpPra === 'function' && window.pendingExistingOpPraContext) {
                        await window.submitPendingExistingOpPra(
                            generatedFileNumber,
                            commissionedFileName
                        );
                    }
                                   let successTitle = 'File Number Generated!';
                    let summaryTitle = 'Commission Summary';
                    let summaryIcon = 'layers';
                    let summaryColor = 'blue';

                    const applicationType = data.data?.application_type;
                    if (applicationType === 'subdivision') {
                        successTitle = 'Subdivision Completed!';
                        summaryTitle = 'Subdivision Summary';
                        summaryIcon = 'scissors';
                        summaryColor = 'blue';
                    } else if (applicationType === 'merger') {
                        successTitle = 'Merger Completed!';
                        summaryTitle = 'Merger Summary';
                        summaryIcon = 'git-merge';
                        summaryColor = 'indigo';
                    } else if (applicationType === 'extension') {
                        successTitle = 'Extension Completed!';
                        summaryTitle = 'Extension Summary';
                        summaryIcon = 'maximize';
                        summaryColor = 'teal';
                    } else if (applicationType === 'separation') {
                        successTitle = 'Separation Completed!';
                        summaryTitle = 'Separation Summary';
                        summaryIcon = 'split';
                        summaryColor = 'violet';
                    }

                    const motherFileNo = data.data?.mother_file_no || 'N/A';
                    const sourceFiles = data.data?.source_files || [];
                    const generatedFileNumberValue = data.data?.file_number || 'N/A';

                    Swal.fire({
                        width: '650px',
                        html: `
                        <div class="text-left">
                            <!-- Success Header (Compact) -->
                            <div class="flex items-center gap-3 mb-5 bg-emerald-50/50 p-3 rounded-2xl border border-emerald-100">
                                <div class="bg-emerald-500 text-white p-2 rounded-xl shadow-sm">
                                    <i data-lucide="badge-check" class="h-6 w-6"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-800 tracking-tight leading-none">${successTitle}</h2>
                                    <p class="text-[10px] text-emerald-600 font-bold mt-1 uppercase tracking-wider">Commissioning Successful</p>
                                </div>
                            </div>

                            <div class="mb-4 text-sm text-gray-600 leading-relaxed px-1">
                                ${data.message}. The file has been successfully commissioned
                            </div>

                            <!-- Unified Summary Card -->
                            <div class="mb-5 p-4 bg-${summaryColor}-50/50 border border-${summaryColor}-100 rounded-3xl relative overflow-hidden">
                                <div class="absolute -right-4 -top-4 opacity-[0.03] text-${summaryColor}-900">
                                    <i data-lucide="${summaryIcon}" class="w-24 h-24"></i>
                                </div>
                                <div class="relative z-10">
                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="bg-${summaryColor}-500 text-white p-1 rounded-lg">
                                            <i data-lucide="${summaryIcon}" class="h-3 w-3"></i>
                                        </div>
                                        <span class="text-[10px] font-black text-${summaryColor}-700 uppercase tracking-tight">${summaryTitle}</span>
                                    </div>

                                    <ul class="text-xs text-${summaryColor}-800 space-y-2 px-1 font-medium">
                                        ${applicationType === 'subdivision' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>Mother File <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${motherFileNo}</b> has been retired and moved decommissioned.</span>
                                            </li>
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>New plot fragment commissioned.</span>
                                            </li>
                                        ` : ''}
                                        ${applicationType === 'merger' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span><b>${sourceFiles.length}</b> source files retired and consolidated.</span>
                                            </li>
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>New consolidated plot record commissioned.</span>
                                            </li>
                                        ` : ''}
                                        ${applicationType === 'extension' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>Original File <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${motherFileNo}</b> expanded and re-commissioned.</span>
                                            </li>
                                        ` : ''}
                                        ${applicationType === 'separation' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>Mother File <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${motherFileNo}</b> has been retired and decommissioned.</span>
                                            </li>
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>New separated plot fragment commissioned.</span>
                                            </li>
                                        ` : ''}
                                        <li class="flex items-start gap-2.5">
                                            <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                            <span>${data.data.parent_prop_id
                                                ? `Lineage established via Parent Property ID linkage${data.data.prop_id ? `: <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${data.data.prop_id}</b>` : ''}.`
                                                : `New Property ID generated${data.data.prop_id ? `: <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${data.data.prop_id}</b>` : ''}.`}</span>
                                        </li>
                                        {{-- Scan folder created at commissioning (EdmsScanUploadFolderService),
                                             so scanning can start before the file is ever indexed. --}}
                                        ${data.edms_folder && data.edms_folder.path ? `
                                        <li class="flex items-start gap-2.5">
                                            <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                            <span>${data.edms_folder.existed ? 'Scan folder already present' : 'EDMS scan folder created'}:
                                                <b class="font-black text-${summaryColor}-900 break-all">${data.edms_folder.path}</b></span>
                                        </li>` : ''}
                                    </ul>
                                </div>
                            </div>

                            {{-- Which tables the commissioning actually wrote to, rendered by the
                                 shared record-summary-card.js so this card matches the one shown
                                 after file indexing and after ST commissioning. --}}
                            ${(typeof renderRecordSummaryGroups === 'function' && data.storage_summary)
                                ? `<div class="mb-5 p-4 bg-slate-50/70 border border-slate-200 rounded-3xl">
                                       <span class="block text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">WHERE THE RECORD WENT</span>
                                       ${renderRecordSummaryGroups(data.storage_summary)}
                                   </div>`
                                : ''}

                            <!-- Generated File -->
                            <div class="mb-5">
                                <span class="block text-[9px] text-gray-400 font-black uppercase tracking-widest mb-2 px-1">NEW COMMISSIONED FILE</span>
                                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-3 shadow-inner-sm text-center">
                                    <p class="text-xl font-mono font-black text-blue-900 leading-relaxed">${generatedFileNumberValue}</p>
                                </div>
                            </div>

                            ${data.decommission_summary && data.decommission_summary.archived.length > 0 ? `
                            <div class="mt-4 p-4 bg-amber-50/50 border border-amber-100 rounded-2xl">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="bg-amber-500 text-white p-1 rounded-lg">
                                        <i data-lucide="archive" class="h-3 w-3"></i>
                                    </div>
                                    <p class="text-[10px] font-black text-amber-800 uppercase tracking-tight">Source Files Decommissioned & Archived</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    ${data.decommission_summary.archived.map(f => `<span class="px-2.5 py-1 bg-white border border-amber-200 text-[10px] font-black text-amber-700 rounded-lg shadow-sm">${f}</span>`).join('')}
                                </div>
                            </div>` : ''}

                            ${data.skipped_serials && data.skipped_serials.length > 0 ? `
                            <div class="mt-4 p-4 bg-amber-50/50 border border-amber-100 rounded-2xl">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="bg-amber-500 text-white p-1 rounded-lg">
                                        <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                                    </div>
                                    <p class="text-[10px] font-black text-amber-800 uppercase tracking-tight">Serials Already In Use — Skipped</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    ${data.skipped_serials.map(s => `<span class="px-2.5 py-1 bg-white border border-amber-200 text-[10px] font-black text-amber-700 rounded-lg shadow-sm">${s.file_number}</span>`).join('')}
                                </div>
                                <p class="mt-2 text-[10px] text-amber-600 italic font-medium px-1">${data.notice || 'These serials were already taken, so the next free number was assigned instead.'}</p>
                            </div>` : ''}
                        </div>`,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'rounded-[2.5rem] border-none shadow-2xl p-7',
                            confirmButton: 'rounded-xl px-8 py-3 font-bold text-sm tracking-wide shadow-lg shadow-emerald-200'
                        },
                        didOpen: () => {
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        }
                    }).then(() => {
                        // If table has no AJAX source (server-rendered), full page reload
                        if (!table.ajax || !table.ajax.url()) {
                            window.location.reload();
                        }
                    });

                    // Reset the form and close modal
                    resetForm();
                    closeGenerateModal();
                    try { table.ajax.reload(); } catch(e) { /* server-rendered table, reload handled above */ }
                    try { updateStats(); } catch(e) { /* may not exist on all pages */ }
                } else if (data.duplicate) {
                    const conflictingNo = data.conflicting_file_number || '';
                    const suggestedNo = data.suggested_file_number || '';
                    const confirmResult = await Swal.fire({
                        icon: 'warning',
                        title: 'File Number Already Exists',
                        html: `<p>The file number <strong>${conflictingNo}</strong> is already in use.</p>` +
                              (suggestedNo ? `<p>Would you like to use <strong>${suggestedNo}</strong> instead?</p>` : ''),
                        showCancelButton: true,
                        confirmButtonText: 'Yes, use next available',
                        cancelButtonText: 'No, cancel',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6b7280'
                    });
                    if (confirmResult.isConfirmed && suggestedNo) {
                        formData.set('force_file_number', suggestedNo);
                        showGlobalLoading();
                        if (submitBtn) showLoadingButton(submitBtn, 'Generating...');
                        fetch('{{ route("mls-fileno.generate") }}', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                        })
                        .then(r => r.json())
                        .then(async retryData => {
                            hideGlobalLoading();
                            if (submitBtn) hideLoadingButton(submitBtn, originalText);
                            if (retryData.success) {
                                getNextSerialNumber();
                                fetch('{{ route("file-numbers.clear-cache") }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                                }).catch(() => {});
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'File Number Generated!',
                                    text: `File number ${retryData.data?.file_number || suggestedNo} has been generated successfully.`,
                                    confirmButtonColor: '#22c55e'
                                });
                                resetForm();
                                closeGenerateModal();
                                if (!table.ajax || !table.ajax.url()) {
                                    window.location.reload();
                                } else {
                                    try { table.ajax.reload(); } catch(e) {}
                                    try { updateStats(); } catch(e) {}
                                }
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error!', text: retryData.message || 'An error occurred', confirmButtonColor: '#ef4444' });
                            }
                        })
                        .catch(() => {
                            hideGlobalLoading();
                            if (submitBtn) hideLoadingButton(submitBtn, originalText);
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while generating the file number', confirmButtonColor: '#ef4444' });
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                clearTimeout(timeoutHandle);
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }
                console.error('Error:', error);
                const isTimeout = error && (error.name === 'AbortError' || String(error).includes('aborted'));
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: isTimeout
                        ? 'Generation timed out on the server. Please retry. If it repeats, we need to optimize the backend generate flow.'
                        : 'An error occurred while generating the file number',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Summary Modal Functions
    // The same modal serves batch and single generation; summaryModalMode decides
    // which confirm path runs and what the header reads.
    let summaryModalMode = 'batch';
    // The original submit event, replayed once the user confirms a single generation.
    let pendingSingleSubmitEvent = null;
    // Set for one pass only, so the replayed submit skips the confirmation gate.
    let singleSummaryConfirmed = false;

    function setSummaryModalHeader(title, subtitle) {
        const titleEl = document.querySelector('#batchSummaryModal h3 span');
        const subtitleEl = document.querySelector('#batchSummaryModal h3 + p');
        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = subtitle;
    }

    // ── Generation Summary → "Next Destination" ────────────────────────────────
    // A commissioned file is created at the File Commissioning Office and moves on
    // for processing, so the summary asks where it goes next. The choice is posted
    // with the generation request and opens the file's tracking with two lines:
    // the File Commissioning line, then the trip to the office picked here.
    let summaryOfficeCache = null;
    // Destination captured when the summary is confirmed, read by both submit paths.
    let pendingSummaryDestination = null;

    async function loadSummaryDestinationOffices() {
        const deptSelect = document.getElementById('summaryDepartment');
        const officeSelect = document.getElementById('summaryUnitOffice');
        if (!deptSelect || !officeSelect) return;

        if (!summaryOfficeCache) {
            try {
                const res = await fetch('{{ route("filetracker.get-offices") }}', { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                summaryOfficeCache = (json && json.success && Array.isArray(json.data)) ? json.data : [];
            } catch (e) {
                console.warn('[Commissioning] Could not load offices', e);
                summaryOfficeCache = [];
            }
        }

        // The File Commissioning Office is where the file already is — it cannot be
        // its own next destination.
        const offices = summaryOfficeCache.filter(o => (o.office_code || '') !== 'FCO');
        const departments = [...new Set(offices.map(o => (o.department || '').trim()).filter(Boolean))].sort();

        const previousDept = deptSelect.value;
        deptSelect.innerHTML = '<option value="">Select department…</option>'
            + departments.map(d => `<option value="${d}">${d}</option>`).join('');
        if (previousDept && departments.includes(previousDept)) {
            deptSelect.value = previousDept;
        }

        const fillOffices = () => {
            const dept = deptSelect.value;
            const matching = offices.filter(o => (o.department || '').trim() === dept);
            officeSelect.disabled = !dept;
            officeSelect.innerHTML = dept
                ? '<option value="">Select unit / office…</option>'
                    + matching.map(o => `<option value="${o.office_code}" data-name="${o.office_name}">${o.office_name}${o.office_code ? ` (${o.office_code})` : ''}</option>`).join('')
                : '<option value="">Select a department first</option>';
        };

        deptSelect.onchange = fillOffices;
        fillOffices();
    }

    /**
     * The chosen destination, or null when nothing is selected. Callers treat null
     * as "no destination": the file then keeps only its default commissioning line.
     */
    function summaryDestinationPayload() {
        const deptSelect = document.getElementById('summaryDepartment');
        const officeSelect = document.getElementById('summaryUnitOffice');
        const officeCode = officeSelect ? officeSelect.value : '';

        if (!officeCode) return null;

        const selected = officeSelect.options[officeSelect.selectedIndex];
        return {
            destination_department: deptSelect ? deptSelect.value : '',
            destination_office_code: officeCode,
            destination_office_name: selected ? (selected.dataset.name || selected.textContent.trim()) : ''
        };
    }

    /** True when a destination is selected; warns and returns false when not. */
    function summaryDestinationSelected() {
        if (summaryDestinationPayload()) return true;

        Swal.fire({
            icon: 'warning',
            title: 'Next Destination Required',
            text: 'Select the department and unit/office this file goes to after commissioning.',
            confirmButtonColor: '#f59e0b'
        });
        return false;
    }

    function summaryLocationRow(fileNo, index, details) {
        return `
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900 mb-1">${fileNo}</p>
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                            <div class="col-span-2"><span class="font-medium">Name:</span> ${details.file_name || '-'}</div>
                            <div><span class="font-medium">Plot:</span> ${details.plotNo || 'N/A'}</div>
                            <div><span class="font-medium">TP:</span> ${details.tpNo || 'N/A'}</div>
                            <div><span class="font-medium">Location:</span> ${details.location || 'N/A'}</div>
                            <div><span class="font-medium">LGA:</span> ${details.lga || 'N/A'}</div>
                        </div>
                    </div>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">#${index}</span>
                </div>
        `;
    }

    // Single-file confirmation step. Called from submitForm() after every validation
    // has passed but before the generate request fires; confirmBatchGeneration()
    // replays the stored submit event to actually generate.
    function showSingleSummaryModal(alpineData, event) {
        summaryModalMode = 'single';
        pendingSingleSubmitEvent = event;

        const serial = parseInt(alpineData.serialNo);
        const hasSerial = !isNaN(serial);
        const code = alpineData.prefix || alpineData.landUse;
        const fileNo = hasSerial ? `${code}-${alpineData.year}-${serial}` : (alpineData.preview || '-');

        setSummaryModalHeader('Generation Summary', 'Review the details before generating this file');

        document.getElementById('summaryBatchSize').textContent = '1 file';
        document.getElementById('summarySerialRange').textContent = hasSerial ? String(serial) : '-';
        document.getElementById('summaryLandUse').textContent = alpineData.landUseFullText || alpineData.landUse || '-';
        document.getElementById('summaryFileName').textContent = alpineData.fileName || '-';
        document.getElementById('summaryTotalFiles').textContent = '1';
        document.getElementById('summaryFileNumbers').textContent = fileNo;

        const locationList = document.getElementById('summaryLocationList');
        locationList.innerHTML = '';
        const entryDiv = document.createElement('div');
        entryDiv.className = 'mb-3 pb-3 border-b border-gray-200 last:border-0';
        entryDiv.innerHTML = summaryLocationRow(fileNo, 1, {
            file_name: alpineData.fileName,
            plotNo: alpineData.plotNo,
            tpNo: alpineData.tpNo,
            location: alpineData.location || alpineData.district,
            lga: alpineData.lga
        });
        locationList.appendChild(entryDiv);

        document.getElementById('batchSummaryModal').classList.remove('hidden');
        loadSummaryDestinationOffices();

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function showBatchSummaryModal(alpineData) {
        summaryModalMode = 'batch';
        pendingSingleSubmitEvent = null;
        setSummaryModalHeader('Batch Generation Summary', 'Review the details before generating files');

        // Persist the currently displayed applicant into its entry before validating,
        // otherwise the file name typed for the active entry would be missed.
        if (typeof alpineData.saveCurrentApplicantToEntry === 'function') {
            alpineData.saveCurrentApplicantToEntry();
        }

        // File Name is required for every file in the batch (mirrors single-generation validation).
        // Effective name per record = entry.file_name, falling back to the global file name.
        const globalFileName = (alpineData.fileName || '').toString().trim();
        const missingEntries = [];
        for (let i = 0; i < alpineData.batchQuantity; i++) {
            const entry = alpineData.locationEntries[i];
            const entryName = (entry && entry.file_name ? entry.file_name : '').toString().trim();
            if (!entryName && !globalFileName) {
                missingEntries.push(i + 1);
            }
        }
        if (missingEntries.length > 0) {
            const allMissing = missingEntries.length === parseInt(alpineData.batchQuantity);
            Swal.fire({
                icon: 'warning',
                title: 'File Name Required',
                text: allMissing
                    ? 'Please enter a File Name before generating the batch file numbers.'
                    : 'Please enter a File Name for file(s) #' + missingEntries.join(', #') + ' before generating the batch.',
                confirmButtonColor: '#f59e0b'
            }).then(() => {
                // Move to the first entry missing a name so the user can fix it
                const targetIndex = missingEntries[0] - 1;
                if (alpineData.currentEntryIndex !== targetIndex) {
                    alpineData.currentEntryIndex = targetIndex;
                    if (typeof alpineData.loadApplicantFromEntry === 'function') {
                        alpineData.loadApplicantFromEntry();
                    }
                    if (typeof alpineData.loadLocationFieldsForEntry === 'function') {
                        alpineData.$nextTick(() => alpineData.loadLocationFieldsForEntry());
                    }
                }
                const fileNameInput = document.getElementById('fileName');
                if (fileNameInput) fileNameInput.focus();
            });
            return;
        }

        // Populate summary data
        const startSerial = parseInt(alpineData.serialNo);
        const endSerial = startSerial + parseInt(alpineData.batchQuantity) - 1;

        document.getElementById('summaryBatchSize').textContent = `${alpineData.batchQuantity} files`;
        document.getElementById('summarySerialRange').textContent = `${startSerial} to ${endSerial}`;
        document.getElementById('summaryLandUse').textContent = alpineData.landUseFullText || alpineData.landUse;
        document.getElementById('summaryFileName').textContent = alpineData.fileName || '-';
        document.getElementById('summaryTotalFiles').textContent = alpineData.batchQuantity;

        // Set current time for commission time input
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const timeString = `${hours}:${minutes}`;
        const timeInput = document.getElementById('commissionTime');
        if (timeInput) {
            timeInput.value = timeString;
        }

        // Generate file number preview
        const code = alpineData.prefix || alpineData.landUse;
        const fileNumberPreview = `${code}-${alpineData.year}-${startSerial} to ${code}-${alpineData.year}-${endSerial}`;
        document.getElementById('summaryFileNumbers').textContent = fileNumberPreview;

        // Populate location details list
        const locationList = document.getElementById('summaryLocationList');
        locationList.innerHTML = '';

        alpineData.locationEntries.forEach((entry, index) => {
            const serial = startSerial + index;
            const fileNo = `${code}-${alpineData.year}-${serial}`;

            const entryDiv = document.createElement('div');
            entryDiv.className = 'mb-3 pb-3 border-b border-gray-200 last:border-0';
            entryDiv.innerHTML = summaryLocationRow(fileNo, index + 1, entry);
            locationList.appendChild(entryDiv);
        });

        // Show the modal
        document.getElementById('batchSummaryModal').classList.remove('hidden');
        loadSummaryDestinationOffices();

        // Re-initialize lucide icons for the modal
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function closeBatchSummaryModal() {
        document.getElementById('batchSummaryModal').classList.add('hidden');
        pendingSingleSubmitEvent = null;
        summaryModalMode = 'batch';
    }

    function confirmBatchGeneration() {
        // Where the file goes after commissioning is part of the confirmation, so
        // it is validated before either mode proceeds. Captured here because the
        // single-file path replays the submit after the modal is hidden.
        if (!summaryDestinationSelected()) {
            return;
        }
        pendingSummaryDestination = summaryDestinationPayload();

        // Single mode: close the summary and replay the original submit, which now
        // skips the confirmation gate and proceeds straight to generation.
        if (summaryModalMode === 'single') {
            const event = pendingSingleSubmitEvent;
            document.getElementById('batchSummaryModal').classList.add('hidden');
            pendingSingleSubmitEvent = null;
            summaryModalMode = 'batch';
            if (event) {
                singleSummaryConfirmed = true;
                submitForm(event);
            }
            return;
        }

        const confirmBtn = document.getElementById('confirmBatchButton');
        const originalText = confirmBtn.innerHTML;

        showLoadingButton(confirmBtn, originalText);
        showGlobalLoading('Generating batch file numbers...');

        // Get Alpine.js data
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (!modalContainer || !modalContainer._x_dataStack) {
            console.error('Alpine.js component not found');
            hideGlobalLoading();
            hideLoadingButton(confirmBtn, originalText);
            return;
        }
        const alpineData = modalContainer._x_dataStack[0];

        // Save current applicant data to the current entry before submitting
        if (typeof alpineData.saveCurrentApplicantToEntry === 'function') {
            alpineData.saveCurrentApplicantToEntry();
        }

        // Ensure location entries are synced if "Apply to All" is checked but not triggered
        if (alpineData.applyLocationToAll && alpineData.locationEntries.length > 0) {
            const currentEntry = alpineData.locationEntries[alpineData.currentEntryIndex] || alpineData.locationEntries[0];
            if (currentEntry) {
                for (let i = 0; i < alpineData.batchQuantity; i++) {
                     if (alpineData.locationEntries[i]) {
                         alpineData.locationEntries[i].plotNo = currentEntry.plotNo;
                         alpineData.locationEntries[i].tpNo = currentEntry.tpNo;
                         alpineData.locationEntries[i].location = currentEntry.location;
                         alpineData.locationEntries[i].lga = currentEntry.lga;
                     }
                }
            }
        }

        // Prepare batch data
        const batchData = {
            batch_mode: true,
            batch_quantity: alpineData.batchQuantity,
            application_type: alpineData.applicationType,
            file_option: alpineData.fileOption,
            file_name: alpineData.fileName,
            land_use: alpineData.prefix || alpineData.landUse,
            year: alpineData.year,
            serial_start: alpineData.serialNo,
            location_entries: alpineData.locationEntries,
            commissioned_by: document.getElementById('commissionedBy').value,
            commission_date: document.getElementById('commissionDate').value,
            commission_time: document.getElementById('commissionTime').value,
            customer_type: alpineData.customerType,
            purpose_id: alpineData.purpose,
            sub_source: alpineData.subSource || '',
            // Origin marker — see the matching set in the single-file submit handler.
            oss_commission: (window.location.pathname.includes('/lands-one-stop-shop/')
                || new URL(window.location.href).searchParams.get('source') === 'lands-one-stop-shop') ? 1 : 0,
            allocated_by_filter: alpineData.allocatedByFilter || '',
            default_allocation_type: alpineData.defaultAllocationType || null,
            // Plot management app IDs — critical for decommissioning and lineage
            subdivision_app_id: alpineData.subdivisionAppId || null,
            merger_app_id: alpineData.mergerAppId || null,
            separation_app_id: alpineData.separationAppId || null,
            // Contact fields
            phone_no: document.getElementById('generatePhoneNo')?.value || '',
            address: document.getElementById('generateAddress')?.value || '',
            rep_phone_no: document.getElementById('generateRepPhoneNo')?.value || '',
            rep_address: document.getElementById('generateRepAddress')?.value || '',
            // Typed related files apply to every file in the batch
            related_files: JSON.stringify(alpineData.relatedFilesPayload ? alpineData.relatedFilesPayload() : []),
            // Every file in the batch is dispatched to the same next destination.
            ...(pendingSummaryDestination || {})
        };
        pendingSummaryDestination = null;

        // Send batch generation request
        fetch('{{ route("mls-fileno.generate-batch") }}', {
            method: 'POST',
            body: JSON.stringify(batchData),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
            .then(response => response.json())
            .then(async data => {
                hideGlobalLoading();
                hideLoadingButton(confirmBtn, originalText);

                if (data.success) {
                    // Confirm batch reservations when generation succeeds
                    if (typeof commissionModalReservation !== 'undefined') {
                        try {
                            await commissionModalReservation.confirmBatch();
                        } catch (e) {
                            console.warn('[Reservation] Batch confirm failed', e);
                        }
                    }

                    // Update next serial number
                    getNextSerialNumber();

                    // Clear cache
                    fetch('{{ route("file-numbers.clear-cache") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    }).catch(e => console.warn('Cache clear failed:', e));

                    // Batch OP Capture → Commissioning: link each generated file to its OP
                    // (OP i ↔ file i) with a shared prop_id. Set by copReopenCommissionForBatch.
                    if (window.pendingOpBatch && window.pendingOpBatch.op_batch
                        && Array.isArray(data.files) && data.files.length) {
                        try {
                            const linkResp = await fetch('{{ route("lands-one-stop-shop.applications.op-link-commissioned") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                },
                                body: JSON.stringify({ op_batch: window.pendingOpBatch.op_batch, files: data.files }),
                            });
                            const linkData = await linkResp.json();
                            console.log('OP↔commissioned-file linking:', linkData);
                        } catch (e) {
                            console.warn('OP linking failed', e);
                        }
                        window.pendingOpBatch = null;
                        // Batch is now commissioned — retire the "Back to OP Batch" button, since
                        // its records are no longer editable.
                        if (alpineData) alpineData.pendingOpBatchId = '';
                    }

                    let batchSuccessTitle = 'Batch Generated!';
                    let summaryTitle = 'Batch Summary';
                    let summaryIcon = 'layers';
                    let summaryColor = 'blue';

                    const applicationType = data.data?.application_type;
                    if (applicationType === 'subdivision') {
                        batchSuccessTitle = 'Subdivision Completed!';
                        summaryTitle = 'Subdivision Summary';
                        summaryIcon = 'scissors';
                        summaryColor = 'blue';
                    } else if (applicationType === 'merger') {
                        batchSuccessTitle = 'Merger Completed!';
                        summaryTitle = 'Merger Summary';
                        summaryIcon = 'git-merge';
                        summaryColor = 'indigo';
                    } else if (applicationType === 'extension') {
                        batchSuccessTitle = 'Extension Completed!';
                        summaryTitle = 'Extension Summary';
                        summaryIcon = 'maximize';
                        summaryColor = 'teal';
                    } else if (applicationType === 'separation') {
                        batchSuccessTitle = 'Separation Completed!';
                        summaryTitle = 'Separation Summary';
                        summaryIcon = 'split';
                        summaryColor = 'violet';
                    }

                    const motherFileNo = data.data?.mother_file_no || 'N/A';
                    const sourceFiles = data.data?.source_files || [];

                    Swal.fire({
                        width: '650px',
                        html: `
                        <div class="text-left">
                            <!-- Success Header (Compact) -->
                            <div class="flex items-center gap-3 mb-5 bg-emerald-50/50 p-3 rounded-2xl border border-emerald-100">
                                <div class="bg-emerald-500 text-white p-2 rounded-xl shadow-sm">
                                    <i data-lucide="badge-check" class="h-6 w-6"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-800 tracking-tight leading-none">${batchSuccessTitle}</h2>
                                    <p class="text-[10px] text-emerald-600 font-bold mt-1 uppercase tracking-wider">Commissioning Successful</p>
                                </div>
                            </div>

                            <div class="mb-4 text-sm text-gray-600 leading-relaxed px-1">
                                ${data.message}. The files have been successfully commissioned and are ready for use in the active registry.
                            </div>

                            <!-- Unified Summary Card -->
                            <div class="mb-5 p-4 bg-${summaryColor}-50/50 border border-${summaryColor}-100 rounded-3xl relative overflow-hidden">
                                <div class="absolute -right-4 -top-4 opacity-[0.03] text-${summaryColor}-900">
                                    <i data-lucide="${summaryIcon}" class="w-24 h-24"></i>
                                </div>
                                <div class="relative z-10">
                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="bg-${summaryColor}-500 text-white p-1 rounded-lg">
                                            <i data-lucide="${summaryIcon}" class="h-3 w-3"></i>
                                        </div>
                                        <span class="text-[10px] font-black text-${summaryColor}-700 uppercase tracking-tight">${summaryTitle}</span>
                                    </div>

                                    <ul class="text-xs text-${summaryColor}-800 space-y-2 px-1 font-medium">
                                        ${applicationType === 'subdivision' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>Mother File <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${motherFileNo}</b> has been retired and moved decommissioned.</span>
                                            </li>
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span><b>${data.files.length}</b> new plot fragments commissioned.</span>
                                            </li>
                                        ` : ''}
                                        ${applicationType === 'merger' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span><b>${sourceFiles.length}</b> source files retired and consolidated.</span>
                                            </li>
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>New consolidated plot record commissioned.</span>
                                            </li>
                                        ` : ''}
                                        ${applicationType === 'extension' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>Original File <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${motherFileNo}</b> expanded and re-commissioned.</span>
                                            </li>
                                        ` : ''}
                                        ${applicationType === 'separation' ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span>Mother File <b class="font-black text-${summaryColor}-900 underline decoration-2 underline-offset-2">${motherFileNo}</b> has been retired and decommissioned.</span>
                                            </li>
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span><b>${data.files.length}</b> new separated plot fragments commissioned.</span>
                                            </li>
                                        ` : ''}
                                        <li class="flex items-start gap-2.5">
                                            <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                            <span>${data.data.parent_prop_id
                                                ? 'Lineage established via Parent Property ID linkage.'
                                                : `New Property ID generated for each of the <b>${data.files.length}</b> files.`}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Generated Files -->
                            <div class="mb-5">
                                <span class="block text-[9px] text-gray-400 font-black uppercase tracking-widest mb-2 px-1">NEW COMMISSIONED FILES</span>
                                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-3 shadow-inner-sm">
                                    <p class="text-sm font-mono font-black text-blue-900 leading-relaxed">${data.files.join(', ')}</p>
                                </div>
                            </div>

                            ${data.decommission_summary && data.decommission_summary.archived.length > 0 ? `
                            <div class="mt-4 p-4 bg-amber-50/50 border border-amber-100 rounded-2xl">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="bg-amber-500 text-white p-1 rounded-lg">
                                        <i data-lucide="archive" class="h-3 w-3"></i>
                                    </div>
                                    <p class="text-[10px] font-black text-amber-800 uppercase tracking-tight">Source Files Decommissioned & Archived</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    ${data.decommission_summary.archived.map(f => `<span class="px-2.5 py-1 bg-white border border-amber-200 text-[10px] font-black text-amber-700 rounded-lg shadow-sm">${f}</span>`).join('')}
                                </div>
                                <p class="mt-2 text-[10px] text-amber-600 italic font-medium px-1">These files are no longer in the active registry but are stored in the secure archives.</p>
                            </div>` : ''}

                            ${data.skipped_serials && data.skipped_serials.length > 0 ? `
                            <div class="mt-4 p-4 bg-amber-50/50 border border-amber-100 rounded-2xl">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="bg-amber-500 text-white p-1 rounded-lg">
                                        <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                                    </div>
                                    <p class="text-[10px] font-black text-amber-800 uppercase tracking-tight">Serials Already In Use — Skipped</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    ${data.skipped_serials.map(s => `<span class="px-2.5 py-1 bg-white border border-amber-200 text-[10px] font-black text-amber-700 rounded-lg shadow-sm">${s.file_number}</span>`).join('')}
                                </div>
                                <p class="mt-2 text-[10px] text-amber-600 italic font-medium px-1">${data.notice || 'These serials were already taken, so the next free numbers were assigned instead.'}</p>
                            </div>` : ''}
                        </div>`,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'rounded-[2.5rem] border-none shadow-2xl p-7',
                            confirmButton: 'rounded-xl px-8 py-3 font-bold text-sm tracking-wide shadow-lg shadow-emerald-200'
                        },
                        didOpen: () => {
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        }
                    });

                    // Close modals and reset
                    closeBatchSummaryModal();
                    closeGenerateModal();
                    resetForm();
                    table.ajax.reload();
                    updateStats();
                } else {
                    let errorMessage = data.message || 'An error occurred during batch generation';
                    if (data.errors) {
                        errorMessage += ':\n' + Object.values(data.errors).flat().join('\n');
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Batch Generation Failed',
                        text: errorMessage,
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(confirmBtn, originalText);
                console.error('Batch generation error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while generating batch file numbers',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function openEditModalFromAction(event, id, type) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        document.querySelectorAll('.action-dropdown-menu').forEach(d => {
            d.classList.add('hidden');
            d.classList.remove('show');
        });

        editRecord(id, type);
    }

    function editRecord(id, type) {
        // Show loading while fetching record details
        showGlobalLoading('Loading record details...');

        // Plot Extensions live in a separate table and reuse ids that collide with
        // fileNumber ids, so flag the entity to resolve the correct record.
        const isPlotExtension = (type || '') === 'Plot Extension';
        let showUrl = `{{ route("file-numbers.show", ":id") }}`.replace(':id', id);
        if (isPlotExtension) {
            showUrl += (showUrl.includes('?') ? '&' : '?') + 'entity=plot_extension';
        }

        fetch(showUrl)
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();

                // Remember the entity so the save path targets the same table.
                const entityField = document.getElementById('editEntity');
                if (entityField) entityField.value = data.entity || '';

                // Populate fields
                document.getElementById('editId').value = data.id;
                document.getElementById('editMlsfNo').value = data.mlsfNo || data.kangisFileNo || '';
                document.getElementById('editFileName').value = data.FileName || '';
                document.getElementById('editPlotNo').value = data.plot_no || '';
                document.getElementById('editTpNo').value = data.tp_no || '';
                document.getElementById('editLocation').value = data.location || '';
                document.getElementById('editLga').value = data.lga || '';
                
                // Populate District if element exists
                if (document.getElementById('editDistrict')) {
                    setDistrictField('editDistrict', 'editDistrictOther', 'editDistrictValue', data.district || '');
                }

                const customerTypeSelect = document.getElementById('editCustomerType');
                if (customerTypeSelect) {
                    // Normalise stored value (e.g. "INDIVIDUAL") to the option casing.
                    const ct = (data.customer_type || '').toString().trim();
                    const match = Array.from(customerTypeSelect.options)
                        .find(o => o.value.toLowerCase() === ct.toLowerCase());
                    customerTypeSelect.value = match ? match.value : 'Individual';
                }
                document.getElementById('editPurpose').value = data.purpose_id || '';
                
                // New Fields
                if (document.getElementById('editPhoneNo')) {
                    document.getElementById('editPhoneNo').value = data.phone_no || data.PhoneNo || '';
                }
                if (document.getElementById('editAddress')) {
                    document.getElementById('editAddress').value = data.address || '';
                }

                const modal = document.getElementById('editModal');
                if (modal) {
                     // TELEPORT: Move modal to body
                    if (modal.parentNode !== document.body) {
                        document.body.appendChild(modal);
                    }
                    modal.classList.remove('hidden');
                    
                    // Ultra-aggressive styling to ensure it's on top
                    modal.style.setProperty('display', 'block', 'important');
                    modal.style.setProperty('z-index', '1000005', 'important');
                    modal.style.setProperty('position', 'fixed', 'important');
                    modal.style.setProperty('top', '0', 'important');
                    modal.style.setProperty('left', '0', 'important');
                    modal.style.setProperty('width', '100%', 'important');
                    modal.style.setProperty('height', '100%', 'important');
                } else {
                    console.error('Edit Modal not found!');
                    Swal.fire('Error', 'Edit Modal element missing', 'error');
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to load record details',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    // --- District "Other (specify)" handling for Edit & Direct Allocation modals ---
    // Populates a district select from a stored value. If the value is not one of the
    // listed options, selects "Other" and reveals the specify input pre-filled with it.
    function setDistrictField(selectId, otherInputId, hiddenId, value) {
        const sel = document.getElementById(selectId);
        const other = document.getElementById(otherInputId);
        const hidden = document.getElementById(hiddenId);
        if (!sel) return;
        const optionValues = Array.from(sel.options).map(o => o.value);
        const val = value || '';
        if (!val) {
            sel.value = '';
            if (other) { other.style.display = 'none'; other.value = ''; }
            if (hidden) hidden.value = '';
        } else if (optionValues.includes(val) && val !== 'Other') {
            sel.value = val;
            if (other) { other.style.display = 'none'; other.value = ''; }
            if (hidden) hidden.value = val;
        } else {
            sel.value = 'Other';
            if (other) { other.style.display = ''; other.value = val; }
            if (hidden) hidden.value = val;
        }
        // Sync the Select2 widget display without re-firing its bridged change handler
        if (window.jQuery && $(sel).hasClass('select2-hidden-accessible')) {
            $(sel).trigger('change.select2');
        }
    }

    // Make the Edit / Direct Allocation District dropdowns searchable (Select2),
    // mirroring initDistrictSelect2() used for the file-number generator form.
    function initEditDistrictSelect2() {
        try {
            if (!window.jQuery || typeof $.fn.select2 === 'undefined') return;
            const $select = $('#editDistrict');
            if ($select.length === 0) return;
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            const parent = document.getElementById('editDistrictWrap')
                || document.getElementById('editModal');
            $select.select2({
                placeholder: 'Search or select district',
                allowClear: true,
                dropdownParent: $(parent),
                width: '100%'
            });
            $select.on('select2:open', () => {
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-search__field');
                    if (searchField) searchField.focus();
                }, 100);
            });
            $select.on('change', function () {
                onEditDistrictChange(this.value);
            });
        } catch (e) {
            console.error('Error in initEditDistrictSelect2():', e);
        }
    }

    function initDaDistrictSelect2() {
        try {
            if (!window.jQuery || typeof $.fn.select2 === 'undefined') return;
            const $select = $('#daDistrict');
            if ($select.length === 0) return;
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            const parent = document.getElementById('daDistrictWrap')
                || document.getElementById('directAllocationModal');
            $select.select2({
                placeholder: 'Search or select district',
                allowClear: true,
                dropdownParent: $(parent),
                width: '100%'
            });
            $select.on('select2:open', () => {
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-search__field');
                    if (searchField) searchField.focus();
                }, 100);
            });
            $select.on('change', function () {
                onDaDistrictChange(this.value);
            });
        } catch (e) {
            console.error('Error in initDaDistrictSelect2():', e);
        }
    }

    function onEditDistrictChange(val) {
        const other = document.getElementById('editDistrictOther');
        const hidden = document.getElementById('editDistrictValue');
        if (val === 'Other') {
            if (other) { other.style.display = ''; other.focus(); }
            if (hidden) hidden.value = other ? other.value.trim() : '';
        } else {
            if (other) { other.style.display = 'none'; other.value = ''; }
            if (hidden) hidden.value = val;
        }
        updateEditLocation();
    }

    function onEditDistrictOtherInput(val) {
        const hidden = document.getElementById('editDistrictValue');
        if (hidden) hidden.value = val;
        updateEditLocation();
    }

    function onDaDistrictChange(val) {
        const other = document.getElementById('daDistrictOther');
        const hidden = document.getElementById('daDistrictValue');
        if (val === 'Other') {
            if (other) { other.style.display = ''; other.focus(); }
            if (hidden) hidden.value = other ? other.value.trim() : '';
        } else {
            if (other) { other.style.display = 'none'; other.value = ''; }
            if (hidden) hidden.value = val;
        }
        updateDirectAllocationLocation();
    }

    function onDaDistrictOtherInput(val) {
        const hidden = document.getElementById('daDistrictValue');
        if (hidden) hidden.value = val;
        updateDirectAllocationLocation();
    }

    function updateEditLocation() {
        const district = document.getElementById('editDistrictValue')?.value.trim() || '';
        const lga = document.getElementById('editLga')?.value.trim() || '';

        // Location is composed from district/LGA only — the plot number is a
        // separate field and must not be echoed into the location string.
        let locationDetails = [];
        if (district) locationDetails.push(district);
        if (lga) locationDetails.push(lga);
        locationDetails.push('KANO');

        const locationString = locationDetails.join(', ');

        const locationInput = document.getElementById('editLocation');
        if (locationInput) {
            locationInput.value = locationString.toUpperCase();
        }
    }

    // Attach listeners for Edit Modal fields
    $(document).ready(function() {
        $(document).on('input change', '#editPlotNo, #editLga, #editDistrict', function() {
            updateEditLocation();
        });
        // Make the Edit / Direct Allocation District dropdowns searchable
        initEditDistrictSelect2();
        initDaDistrictSelect2();
    });

    function submitEditForm(event, confirmTransactionChange = false) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        // Show global loading
        showGlobalLoading('Updating record...');

        const id = document.getElementById('editId').value;
        const formData = new FormData(document.getElementById('editForm'));
        if (confirmTransactionChange) {
            formData.set('confirm_transaction_change', '1');
        }

        fetch(`{{ route("file-numbers.update", ":id") }}`.replace(':id', id), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json().then(data => ({ status: response.status, data })))
            .then(({ status, data }) => {
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    });

                    document.querySelectorAll('.action-dropdown-menu').forEach(d => {
                        d.classList.add('hidden');
                        d.classList.remove('show');
                    });

                    closeEditModal();
                    table.ajax.reload();
                } else if (status === 409 && data.requires_confirmation) {
                    // File has recorded transactions — ask the user to confirm before propagating the name change
                    Swal.fire({
                        icon: 'warning',
                        title: 'Transactions Found',
                        text: data.message,
                        showCancelButton: true,
                        confirmButtonText: 'Yes, update everywhere',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#f59e0b',
                        cancelButtonColor: '#6b7280'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitEditForm(event, true);
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while updating the record',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function directAllocation(id) {
        // Show loading while fetching record details
        showGlobalLoading('Loading record details...');

        fetch(`{{ route("file-numbers.show", ":id") }}`.replace(':id', id))
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                
                // Populate fields
                document.getElementById('daEditId').value = data.id;
                document.getElementById('daPlotNo').value = data.plot_no || '';
                document.getElementById('daLocation').value = data.location || '';
                document.getElementById('daLga').value = data.lga || '';
                
                // Populate District if element exists
                if (document.getElementById('daDistrict')) {
                    setDistrictField('daDistrict', 'daDistrictOther', 'daDistrictValue', data.district || '');
                }

                // phone_no might be empty if the column doesn't exist, we will handle that
                if (document.getElementById('daPhoneNo')) {
                    document.getElementById('daPhoneNo').value = data.phone_no || data.PhoneNo || '';
                }
                
                const modal = document.getElementById('directAllocationModal');
                if (modal) {
                     // TELEPORT: Move modal to body
                    if (modal.parentNode !== document.body) {
                        document.body.appendChild(modal);
                    }
                    modal.classList.remove('hidden');
                    
                    // Ultra-aggressive styling to ensure it's on top
                    modal.style.setProperty('display', 'block', 'important');
                    modal.style.setProperty('z-index', '1000005', 'important');
                    modal.style.setProperty('position', 'fixed', 'important');
                    modal.style.setProperty('top', '0', 'important');
                    modal.style.setProperty('left', '0', 'important');
                    modal.style.setProperty('width', '100%', 'important');
                    modal.style.setProperty('height', '100%', 'important');
                } else {
                    console.error('Direct Allocation Modal not found!');
                    Swal.fire('Error', 'Direct Allocation Modal element missing', 'error');
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to load record details',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function closeDirectAllocationModal() {
        const modal = document.getElementById('directAllocationModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function updateDirectAllocationLocation() {
        const plotNo = document.getElementById('daPlotNo')?.value.trim() || '';
        const district = document.getElementById('daDistrictValue')?.value.trim() || '';
        const lga = document.getElementById('daLga')?.value.trim() || '';
        
        let parts = [];
        if (plotNo) {
            parts.push(`PLOT NUMBER ${plotNo}`);
        }
        
        let locationDetails = [];
        if (district) locationDetails.push(district);
        if (lga) locationDetails.push(lga);
        locationDetails.push('KANO');
        
        const plotPart = parts.join(' ');
        const addressPart = locationDetails.join(', ');
        
        const locationString = [plotPart, addressPart].filter(Boolean).join(' ');
        
        const locationInput = document.getElementById('daLocation');
        if (locationInput) {
            locationInput.value = locationString.toUpperCase();
        }
    }

    // Attach listeners for Direct Allocation fields
    $(document).ready(function() {
        $(document).on('input change', '#daPlotNo, #daLga, #daDistrict', function() {
            updateDirectAllocationLocation();
        });
    });

    function submitDirectAllocationForm(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        // Show global loading
        showGlobalLoading('Updating allocation...');

        const id = document.getElementById('daEditId').value;
        const formData = new FormData(document.getElementById('directAllocationForm'));

        // Since it's direct allocation, we might not have file_name, but the controller requires file_name.
        // We will fetch the existing file name and append it to formData.
        fetch(`{{ route("file-numbers.show", ":id") }}`.replace(':id', id))
            .then(response => response.json())
            .then(data => {
                if (data.FileName) {
                    formData.append('file_name', data.FileName);
                }
                
                return fetch(`{{ route("file-numbers.update", ":id") }}`.replace(':id', id), {
                    method: 'POST', // Form specifies @method('PUT') but we send as POST with _method=PUT from the blade directive
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });
            })
            .then(response => {
                return response.json();
            })
            .then(data => {
                hideGlobalLoading();
                if (submitBtn) {
                    hideLoadingButton(submitBtn, originalText);
                }

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message || 'Direct allocation updated successfully',
                        confirmButtonColor: '#10b981'
                    });
                    closeDirectAllocationModal();
                    if (typeof table !== 'undefined' && table.ajax) {
                        table.ajax.reload(null, false);
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to update direct allocation',
                    confirmButtonColor: '#ef4444'
                });
            });
    }
 
    function deleteRecord(id) {
        Swal.fire({
            title: 'Are you sure?',
            html: `
                <div class="text-center">
                    <p class="mb-4 text-slate-500 text-sm">You won't be able to revert this! This action will execute a <strong>Master Cascade Delete</strong> and permanently purge this record from the following 5 tables:</p>
                    <div class="inline-block text-left bg-slate-50 p-4 rounded-xl border border-slate-200/80 w-full max-w-md mx-auto shadow-inner">
                        <ul class="space-y-2 text-slate-700 font-semibold text-xs list-decimal list-inside">
                            <li><span class="font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100/50">MlsfileNo</span></li>
                            <li><span class="font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100/50">fileNumber</span></li>
                            <li><span class="font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100/50">Entity Table</span></li>
                            <li><span class="font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100/50">Customers</span></li>
                            <li><span class="font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100/50">File Indexings</span></li>
                        </ul>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280', 
            confirmButtonText: 'Yes, delete it!',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ route("file-numbers.destroy", ":id") }}`.replace(':id', id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(response.statusText);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        Swal.showValidationMessage(
                            `Request failed: ${error}`
                        );
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const data = result.value;
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    });
                    table.ajax.reload();
                    updateStats();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            }
        });
    }

    // ============================================================
    // BULK DELETE — Supper Admin only (no-op when MLSF_IS_ADMIN is false)
    // ============================================================
    window.mlsfSelectedIds = new Set();

    function updateBulkDeleteUI() {
        const bar = document.getElementById('mlsfBulkActionsBar');
        const countEl = document.getElementById('mlsfSelectedCount');
        const count = window.mlsfSelectedIds.size;
        if (countEl) countEl.textContent = count;
        if (bar) {
            if (count > 0) {
                bar.classList.remove('hidden');
                bar.classList.add('flex');
            } else {
                bar.classList.add('hidden');
                bar.classList.remove('flex');
            }
        }
    }

    function clearMlsfSelection() {
        window.mlsfSelectedIds.clear();
        document.querySelectorAll('#mlsfTable tbody .mlsf-row-check').forEach(cb => { cb.checked = false; });
        const selectAll = document.getElementById('mlsfSelectAll');
        if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
        updateBulkDeleteUI();
    }

    function refreshSelectAllState() {
        const rowChecks = document.querySelectorAll('#mlsfTable tbody .mlsf-row-check');
        const selectAll = document.getElementById('mlsfSelectAll');
        if (!selectAll) return;
        if (rowChecks.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }
        let checkedOnPage = 0;
        rowChecks.forEach(cb => { if (cb.checked) checkedOnPage++; });
        selectAll.checked = checkedOnPage === rowChecks.length;
        selectAll.indeterminate = checkedOnPage > 0 && checkedOnPage < rowChecks.length;
    }

    // Delegated handler — works for rows rendered on any page draw
    $(document).on('change', '#mlsfTable tbody .mlsf-row-check', function () {
        const id = String(this.value);
        if (this.checked) {
            window.mlsfSelectedIds.add(id);
        } else {
            window.mlsfSelectedIds.delete(id);
        }
        updateBulkDeleteUI();
        refreshSelectAllState();
    });

    // Select-all (current page)
    $(document).on('change', '#mlsfSelectAll', function () {
        const checked = this.checked;
        document.querySelectorAll('#mlsfTable tbody .mlsf-row-check').forEach(cb => {
            cb.checked = checked;
            const id = String(cb.value);
            if (checked) {
                window.mlsfSelectedIds.add(id);
            } else {
                window.mlsfSelectedIds.delete(id);
            }
        });
        updateBulkDeleteUI();
    });

    function bulkDeleteSelectedRecords() {
        const ids = Array.from(window.mlsfSelectedIds);
        if (ids.length === 0) {
            Swal.fire({ icon: 'info', title: 'No records selected', text: 'Tick the rows you want to delete first.' });
            return;
        }
        if (ids.length > 200) {
            Swal.fire({ icon: 'warning', title: 'Too many records', text: 'Please select 200 or fewer records per batch.' });
            return;
        }

        Swal.fire({
            title: `Delete ${ids.length} record(s)?`,
            html: `
                <div class="text-center">
                    <p class="mb-3 text-slate-600 text-sm">This will execute a <strong>Master Cascade Delete</strong> for <strong>${ids.length}</strong> selected record(s), purging them from <strong>5 tables</strong>:</p>
                    <div class="inline-block text-left bg-slate-50 p-3 rounded-lg border border-slate-200 w-full max-w-md mx-auto shadow-inner">
                        <ul class="space-y-1 text-slate-700 font-semibold text-xs list-decimal list-inside">
                            <li><span class="font-mono text-indigo-600">MlsfileNo</span></li>
                            <li><span class="font-mono text-indigo-600">fileNumber</span></li>
                            <li><span class="font-mono text-indigo-600">Entity Table</span></li>
                            <li><span class="font-mono text-indigo-600">Customers</span></li>
                            <li><span class="font-mono text-indigo-600">File Indexings</span></li>
                        </ul>
                    </div>
                    <p class="mt-3 text-xs text-red-600 font-semibold">All deletes run in one transaction — any failure rolls back every record.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Yes, delete ${ids.length}!`,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ route('file-numbers.bulk-destroy') }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ids: ids })
                })
                .then(response => response.json().then(json => ({ ok: response.ok, json })))
                .catch(err => {
                    Swal.showValidationMessage(`Request failed: ${err}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then(result => {
            if (!result.isConfirmed || !result.value) return;
            const { ok, json } = result.value;
            if (ok && json && json.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    html: `${json.message || ''}<br><small class="text-gray-500">Cascade totals — fileNumber: ${json.totals?.fileNumber ?? 0}, mls_file_no: ${json.totals?.mls_file_no ?? 0}, entities: ${json.totals?.entities_staging ?? 0}, customers: ${json.totals?.customers_staging ?? 0}, indexings: ${json.totals?.file_indexings ?? 0}</small>`,
                    confirmButtonColor: '#10b981'
                });
                window.mlsfSelectedIds.clear();
                updateBulkDeleteUI();
                table.ajax.reload(null, false);
                updateStats();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: (json && json.message) || 'Bulk delete failed.',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    }

    function updateStats() {
        fetch('{{ route("file-numbers.stats") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const totalEl = document.getElementById('stat-total');
                    const todayEl = document.getElementById('stat-today');
                    const monthEl = document.getElementById('stat-month');

                    if (totalEl) totalEl.textContent = new Intl.NumberFormat().format(data.data.total);
                    if (todayEl) todayEl.textContent = new Intl.NumberFormat().format(data.data.today);
                    if (monthEl) monthEl.textContent = new Intl.NumberFormat().format(data.data.month);
                }
            })
            .catch(error => {
                console.error('Error updating stats:', error);
            });
    }

    function testDatabaseConnection() {
        // Show loading for database test
        showGlobalLoading('Testing database connection...');

        fetch('{{ route("file-numbers.test-db") }}')
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();

                if (data.success) {
                    let message = `Database Connection Test Results:\n\n`;
                    message += `✅ Connection: ${data.connection}\n`;
                    message += `✅ Database: ${data.database_name}\n`;
                    message += `✅ Table Exists: ${data.table_exists ? 'Yes' : 'No'}\n`;
                    message += `✅ Record Count: ${data.record_count}\n`;
                    message += `✅ Server: ${data.server_info.substring(0, 50)}...\n\n`;

                    if (data.columns && data.columns.length > 0) {
                        message += `Table Columns:\n`;
                        data.columns.forEach(col => {
                            message += `- ${col.COLUMN_NAME} (${col.DATA_TYPE})\n`;
                        });
                    }

                    if (data.sample_records && data.sample_records.length > 0) {
                        message += `\nSample Records:\n`;
                        data.sample_records.forEach((record, index) => {
                            message += `${index + 1}. ${record.mlsfNo || record.kangisFileNo || 'No ID'}\n`;
                        });
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Database Test Successful',
                        text: message,
                        confirmButtonColor: '#10b981',
                        customClass: {
                            content: 'text-left'
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Database Test Failed',
                        text: data.error || 'Unknown error occurred',
                        confirmButtonColor: '#ef4444',
                        footer: '<small>Check the browser console for more details</small>'
                    });
                    console.error('Database test error:', data);
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Database test error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Database Test Failed',
                    text: 'Failed to connect to test endpoint: ' + error.message,
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function debugTableData() {
        // Show loading for debug data
        showGlobalLoading('Debugging table data...');

        fetch('{{ route("file-numbers.debug-data") }}')
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();

                if (data.success) {
                    console.log('Raw Data:', data.raw_data);
                    console.log('Formatted Data:', data.formatted_data);

                    let message = `Debug Data Results:\n\n`;
                    message += `Raw Records Found: ${data.raw_data.length}\n`;
                    message += `Formatted Records: ${data.formatted_data.length}\n\n`;

                    if (data.raw_data.length > 0) {
                        message += `Raw Data Sample:\n`;
                        data.raw_data.slice(0, 3).forEach((record, index) => {
                            message += `${index + 1}. ID: ${record.id}\n`;
                            message += `   kangisFileNo: "${record.kangisFileNo}"\n`;
                            message += `   NewKANGISFileNo: "${record.NewKANGISFileNo}"\n`;
                            message += `   FileName: "${record.FileName}"\n`;
                            message += `   mlsfNo: "${record.mlsfNo}"\n\n`;
                        });
                    }

                    if (data.formatted_data.length > 0) {
                        message += `Formatted Data Sample:\n`;
                        data.formatted_data.slice(0, 3).forEach((record, index) => {
                            message += `${index + 1}. ID: ${record.id}\n`;
                            message += `   kangisFileNo: "${record.kangisFileNo}"\n`;
                            message += `   NewKANGISFileNo: "${record.NewKANGISFileNo}"\n`;
                            message += `   FileName: "${record.FileName}"\n`;
                            message += `   mlsfNo: "${record.mlsfNo}"\n\n`;
                        });
                    }

                    Swal.fire({
                        icon: 'info',
                        title: 'Debug Data Results',
                        text: message,
                        confirmButtonColor: '#8b5cf6',
                        customClass: {
                            content: 'text-left'
                        },
                        width: '600px'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Debug Failed',
                        text: data.error || 'Unknown error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                    console.error('Debug error:', data);
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Debug error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Debug Failed',
                    text: 'Failed to connect to debug endpoint: ' + error.message,
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Simplified commissioning sheet function for better performance
    function openCommissioningSheet(recordId, fileNo, fileName, plotNo, tpNo, location, lga, trackingId, createdAt, createdBy) {
        // Pre-fill the commissioning sheet modal with available data
        document.getElementById('cs_file_number').value = fileNo || '';
        populateCommissioningSource(fileNo);
        document.getElementById('cs_file_name').value = fileName || '';
        document.getElementById('cs_name_allottee').value = fileName || '';
        document.getElementById('cs_plot_number').value = (plotNo && plotNo !== 'N/A') ? plotNo : '';
        document.getElementById('cs_tp_number').value = (tpNo && tpNo !== 'N/A') ? tpNo : '';
        document.getElementById('cs_location').value = (location && location !== 'N/A') ? location : '';
        document.getElementById('cs_lga').value = (lga && lga !== 'N/A') ? lga : '';
        document.getElementById('cs_tracking_id').value = trackingId || '';

        // Handle Date and Time from createdAt if available
        if (createdAt && createdAt !== 'N/A' && createdAt !== '') {
            const date = new Date(createdAt);

            // Set Date (YYYY-MM-DD format for input type="date")
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            document.getElementById('cs_date_created').value = `${year}-${month}-${day}`;

            // Set Time (HH:MM format for input type="time")
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            document.getElementById('cs_time_created').value = `${hours}:${minutes}`;
        } else {
            // Fallback to server time instead of client time
            const serverInfo = getServerTime();
            document.getElementById('cs_date_created').value = serverInfo.date;
            document.getElementById('cs_time_created').value = serverInfo.time;
        }

        // Handle Commission             ed By
        if (createdBy && createdBy !== 'N/A' && createdBy !== '') {
            document.getElementById('cs_created_by').value = createdBy;
        } else {
            // Fallback to current user
            @if(Auth::check())
                document.getElementById('cs_created_by').value = '{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}';
            @endif
        }

        // Open the modal
        document.getElementById('commissioningSheetModal').classList.remove('hidden');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.relative')) {
            closeAllDropdowns();
        }
    });

    // Add event listeners for form inputs
    // Add event listeners for form inputs with safety checks
    document.addEventListener('DOMContentLoaded', function () {
        const serialNoEl = document.getElementById('serialNo');
        if (serialNoEl) serialNoEl.addEventListener('input', updatePreview);

        const yearEl = document.getElementById('year');
        if (yearEl) yearEl.addEventListener('input', updatePreview);

        const landUseEl = document.getElementById('landUse');
        if (landUseEl) landUseEl.addEventListener('change', updatePreview);

        // Add event listeners for file option dropdown
        const fileOptionEl = document.getElementById('fileOption');
        if (fileOptionEl) fileOptionEl.addEventListener('change', updatePreview);

        // Add event listener for existing file number dropdown
        const existingFileNoEl = document.getElementById('existingFileNo');
        if (existingFileNoEl) existingFileNoEl.addEventListener('change', updatePreview);

        // Add event for middle prefix input if present
        const middlePrefixEl = document.getElementById('middlePrefix');
        if (middlePrefixEl) {
            middlePrefixEl.addEventListener('input', updatePreview);
        }
    });

    // Alpine.js component for file number generator
    function fileNumberGenerator() {
        console.log('fileNumberGenerator() function is being defined/called');
        return {
            // Data properties
            applicationType: 'new',
            // Backing value for the visible "Direct Allocation / Conversion" radios.
            // Kept separate from applicationType so that picking "Conversion" while
            // the File Type dropdown is on Change of Purpose doesn't detach the form
            // from CoP mode — see updateApplicationType().
            appTypeRadio: 'new',
            allocatedByFilter: null,
            defaultAllocationType: '',
            _currentAllocationSourceType: 'default', // Track current allocation source type
            fileName: '',
            hasCustomFileName: true, // Toggle for custom file name input - default to checked
            allocationId: null,
            landUse: '',
            landUseId: '',
            landUseFullText: '',
            // Dependent Data
            purposes: [],
            prefixes: [],
            allAllPrefixes: @json($allPrefixes),
            landUses: @json($landUses),
            purpose: '',
            prefix: '',
            customerType: '',
            gender: '',
            // Default to normal file
            fileOption: 'normal',
            // Backs the top "FILE TYPE" select. Kept separate from fileOption because that
            // select also expresses workflows (e.g. change_of_purpose) which map to an
            // applicationType rather than a fileOption. Must be reset with fileOption.
            fileTypeWorkflow: '',
            // Reason captured for SIT files
            sitReason: '',
            // Extension category: 'file' (existing AND EXTENSION flow) or 'plot' (keep original number)
            extensionType: 'file',
            _lastFileOption: 'normal', // tracks previous fileOption to detect transitions
            existingFileNo: '',
            middlePrefix: 'KN',
            year: new Date().getFullYear(),
            serialNo: '',
            plotNo: '',
            tpNo: '',
            tpSearchQuery: '',
            tpSearchResults: [],
            tpSearchLoading: false,
            tpSearchOpen: false,
            tpSearchTimer: null,
            tpFocusIndex: -1,
            location: '',
            lga: '',
            district: '',
            districtIsOther: false,
            preview: '-',
            // New fields for reset logic
            phone_no: '',
            address: '',
            rep_phone_no: '',
            rep_address: '',
            isInherited: false, // Flag to track if fields were auto-populated from existing file
            sourceInstrumentCaptureId: '',
            sourcePropId: '',
            sourcePraId: '',
            sourceOpSerialNumber: '',
            sourceRegistrationNumber: '',
            sourceSerialNo: '',
            sourcePageNo: '',
            sourceVolumeNo: '',
            sourceOriginalOwner: '',
            requireOpSource: false,
            subSource: '',

            // Related File Properties (Recertification -RC)
            hasRelatedFile: false,
            relatedFileNo: '',
            relatedFileTitle: '',
            relatedFileIndexingId: '',
            // Relationship kind for the primary related file; one of relatedFileTypes.
            relatedFileType: '',
            // Free text shown only when the type is 'Other'; becomes the stored type.
            relatedFileTypeOther: '',
            // Related files beyond the first. Same shape as the primary row:
            // {file_no, title, type, indexing_id}. A file can relate to several others
            // (e.g. a merger drawing on multiple mother files).
            extraRelatedFiles: [],
            relatedFileTypes: [
                'Re-grant',
                'Resettlement',
                'Subdivision',
                'Merger',
                'Change of Purpose',
                'Mother File',
                'Temporary File',
                'Dummy File',
                'Other'
            ],
            isRecertificationPrefix: false,

            // Old File Properties (Re-Issuance of FileNo). Picked with the same
            // GlobalFileNoModal as a related file, but stored separately: the old
            // (duplicated) number is written to mls_file_no.old_fileno.
            oldFileNo: '',
            oldFileTitle: '',
            oldFileIndexingId: '',

            // Subdivision / Merger / Separation Properties
            subdivisionAppId: '',
            mergerAppId: '',
            separationAppId: '',
            subdivisionFileNo: '',
            mergerFileNo: '',
            separationFileNo: '',

            // Change of Purpose Properties
            changeOfPurposeAppId: '',
            originalFileNo: '',
            copApplicantName: '',
            copCurrentLandUse: '',
            copNewPurpose: '',
            newPurpose: '', // Added missing property to fix ReferenceError

            // Batch Mode Properties
            hideBatchMode: window.commissionModalHideBatchMode === true,
            batchMode: false,
            batchQuantity: 2,
            // Set by the Batch Capture OP hand-off while an uncommissioned batch is awaiting
            // commissioning; drives the "Back to OP Batch" button. Empty outside that flow.
            pendingOpBatchId: '',
            locationEntries: [],
            currentEntryIndex: 0,
            serialRangePreview: '-',
            applyLocationToAll: false, // Toggle for batch location sync
            // Allottees (each OP's Party 2) by batch sequence, set by the Batch Capture OP
            // hand-off (copReopenCommissionForBatch). Index i pairs with applicant i, and
            // becomes the ToT's Party 1 at link time. Empty outside that flow.
            opBatchAllottees: [],

            // Initialize the Select2 for Direct Allocation
            init() {
                try {
                    console.log('Alpine fileNumberGenerator init() started');
                    
                    // Default serial for new forms
                    this.serialNo = '';

                    // Make the District dropdown searchable (Select2)
                    this.$nextTick(() => this.initDistrictSelect2());

                    this.$watch('applicationType', (value) => {
                        console.log('applicationType watched change:', value);
                        if (value === 'new') {
                            if (this.allocatedByFilter === 'Allocation List') {
                                this.$nextTick(() => {
                                    this.initAllocationSelect2();
                                });
                            }
                        } else {
                            this.allocatedByFilter = '';
                        }
                        this.updateApplicationType();
                    });

                    // Trigger on first load if default is 'new' and allocation list is selected
                    if (this.applicationType === 'new') {
                        if (this.allocatedByFilter === 'Allocation List') {
                            console.log('Initial applicationType is new, calling initAllocationSelect2');
                            this.$nextTick(() => {
                                this.initAllocationSelect2();
                            });
                        }
                    }
                    
                    this.$watch('prefix', (value) => {
                        console.log('prefix watched change:', value);
                        if (value) {
                            this.handlePrefixChange();
                        }
                    });

                    this.$watch('landUseId', (value) => {
                        console.log('landUseId watched change:', value);
                        if (value) {
                            this.fetchDependentData(value);
                        } else if (this.fileOption !== 'sit') {
                            this.purposes = [];
                            this.purpose = '';
                        }
                    });

                    this.updatePreview();

                } catch (e) {
                    console.error('Error in Alpine init():', e);
                }
            },

            debounceTpSearch() {
                clearTimeout(this.tpSearchTimer);
                const q = this.tpSearchQuery.trim();
                if (q.length < 1) {
                    this.tpSearchResults = [];
                    this.tpSearchOpen = false;
                    this.tpFocusIndex = -1;
                    return;
                }
                this.tpSearchLoading = true;
                this.tpSearchOpen = true;
                this.tpSearchTimer = setTimeout(() => {
                    fetch('{{ route("instruments.tpLookups.search") }}?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        this.tpSearchResults = (data.results || []).filter(r => r.id !== '__other__');
                        this.tpSearchLoading = false;
                        this.tpSearchOpen = true;
                        this.tpFocusIndex = -1;
                    })
                    .catch(e => {
                        console.error('[TP Search] fetch error:', e);
                        this.tpSearchLoading = false;
                    });
                }, 300);
            },

            selectTpResult(result) {
                this.tpNo = result.id;
                this.tpSearchQuery = result.text;
                this.tpSearchOpen = false;
                this.tpSearchResults = [];
                this.tpFocusIndex = -1;
                const hidden = document.getElementById('generator_tp_no_val');
                if (hidden) hidden.value = result.id;
                if (this.batchMode) this.updateLocationEntry('tpNo', result.id);
            },

            clearTpNo() {
                this.tpNo = '';
                this.tpSearchQuery = '';
                this.tpSearchResults = [];
                this.tpSearchOpen = false;
                this.tpFocusIndex = -1;
                const hidden = document.getElementById('generator_tp_no_val');
                if (hidden) hidden.value = '';
                if (this.batchMode) this.updateLocationEntry('tpNo', '');
            },

            tpFocusNext() {
                if (!this.tpSearchOpen) return;
                this.tpFocusIndex = Math.min(this.tpFocusIndex + 1, this.tpSearchResults.length - 1);
            },

            tpFocusPrev() {
                if (!this.tpSearchOpen) return;
                this.tpFocusIndex = Math.max(this.tpFocusIndex - 1, 0);
            },

            tpSelectFocused() {
                if (this.tpFocusIndex >= 0 && this.tpSearchResults[this.tpFocusIndex]) {
                    this.selectTpResult(this.tpSearchResults[this.tpFocusIndex]);
                }
            },

            buildLocation() {
                // In batch mode the per-entry values are the source of truth (the plot
                // input only updates the entry, not the top-level this.plotNo), so read
                // from the current entry to avoid building the string from stale data.
                let dist = this.district, lga = this.lga;
                if (this.batchMode && this.locationEntries[this.currentEntryIndex]) {
                    const entry = this.locationEntries[this.currentEntryIndex];
                    dist = entry.district;
                    lga = entry.lga;
                }
                // Plot number is captured in its own field; don't repeat it inside the
                // auto-filled Location string (it read e.g. "463, ABBA ABDULLAHI AV, FAGGE").
                const parts = [dist, lga].filter(v => v && v.toString().trim());
                const loc = parts.join(', ').toUpperCase();
                this.location = loc;
                if (this.batchMode) {
                    this.updateLocationEntry('location', loc);
                }
            },

            onDistrictChange(val) {
                if (val === 'Other') {
                    this.districtIsOther = true;
                    this.district = '';
                    if (this.batchMode) this.updateLocationEntry('district', '');
                } else {
                    this.districtIsOther = false;
                    this.district = val;
                    if (this.batchMode) this.updateLocationEntry('district', val);
                    this.buildLocation();
                }
            },

            onDistrictOtherInput(val) {
                this.district = val;
                if (this.batchMode) this.updateLocationEntry('district', val);
                this.buildLocation();
            },

            onLgaChange(val) {
                this.lga = val;
                if (this.batchMode) this.updateLocationEntry('lga', val);
                this.buildLocation();
            },

            loadDistrictField(districtVal) {
                const sel = document.getElementById('generator_district');
                if (!sel) return;
                const optionValues = Array.from(sel.options).map(o => o.value);
                if (!districtVal) {
                    sel.value = '';
                    this.districtIsOther = false;
                } else if (optionValues.includes(districtVal)) {
                    sel.value = districtVal;
                    this.districtIsOther = false;
                } else {
                    sel.value = 'Other';
                    this.districtIsOther = true;
                    const otherInput = document.getElementById('generator_district_other');
                    if (otherInput) otherInput.value = districtVal;
                }
                // Sync the Select2 widget display without re-firing Alpine's @change
                if (window.jQuery && $(sel).hasClass('select2-hidden-accessible')) {
                    $(sel).trigger('change.select2');
                }
            },

            initDistrictSelect2() {
                try {
                    if (!window.jQuery || typeof $.fn.select2 === 'undefined') return;
                    const $select = $('#generator_district');
                    if ($select.length === 0) return;

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    // Anchor the dropdown to the field's own relative wrapper so it
                    // opens directly under the select (avoids Select2 mis-positioning
                    // it at the top of the scrollable fixed modal).
                    const parent = document.getElementById('generatorDistrictWrap')
                        || document.getElementById('generateModal');

                    $select.select2({
                        placeholder: 'Search or select district',
                        allowClear: true,
                        dropdownParent: $(parent),
                        width: '100%'
                    });

                    // Focus the search box when the dropdown opens
                    $select.on('select2:open', () => {
                        setTimeout(() => {
                            const searchField = document.querySelector('.select2-search__field');
                            if (searchField) searchField.focus();
                        }, 100);
                    });

                    // Select2 emits its change through jQuery's .trigger('change'),
                    // which does NOT invoke Alpine's addEventListener-based @change.
                    // Bridge it explicitly so selecting "Other" reveals the specify
                    // field and location rebuilds for normal districts.
                    const self = this;
                    $select.on('change', function () {
                        self.onDistrictChange(this.value);
                    });
                } catch (e) {
                    console.error('Error in initDistrictSelect2():', e);
                }
            },

            loadLocationFieldsForEntry() {
                const entry = this.locationEntries[this.currentEntryIndex];
                if (!entry) return;
                // Plot Number
                this.plotNo = entry.plotNo || '';
                // TP Number (custom search)
                this.tpNo = entry.tpNo || '';
                this.tpSearchQuery = entry.tpNo || '';
                this.tpSearchOpen = false;
                const hidden = document.getElementById('generator_tp_no_val');
                if (hidden) hidden.value = entry.tpNo || '';
                // District
                this.district = entry.district || '';
                this.loadDistrictField(entry.district || '');
                // LGA
                this.lga = entry.lga || '';
                const lgaSel = document.getElementById('generator_lga');
                if (lgaSel) lgaSel.value = entry.lga || '';
                // Rebuild auto location
                this.buildLocation();
            },

            // Push the current Alpine district/lga values into the native <select>
            // widgets. These selects have no x-model, so assigning this.district /
            // this.lga during a backfill does not move the dropdowns (and the LGA
            // select would submit an empty value). Call this after any flow that
            // backfills location from an existing/related file.
            syncLocationSelects() {
                // District: loadDistrictField handles the native select, the
                // "Other" specify input and the Select2 display in one place.
                this.loadDistrictField(this.district || '');

                // LGA: plain native select — set its value if the option exists.
                const lgaSel = document.getElementById('generator_lga');
                if (lgaSel) {
                    const lgaVal = this.lga || '';
                    const normalizedLga = this.normalizeLocationToken(lgaVal);
                    const matchedOption = Array.from(lgaSel.options).find(o =>
                        this.normalizeLocationToken(o.value) === normalizedLga
                        || this.normalizeLocationToken(o.text) === normalizedLga
                    );
                    lgaSel.value = matchedOption ? matchedOption.value : '';
                    this.lga = matchedOption ? matchedOption.value : '';
                    if (window.jQuery && $(lgaSel).hasClass('select2-hidden-accessible')) {
                        $(lgaSel).trigger('change.select2');
                    }
                }
            },

            initAllocationSelect2() {
                const self = this;
                if (!this.allocatedByFilter) {
                    console.log('initAllocationSelect2() skipping: no filter selected');
                    return;
                }
                try {
                    console.log('initAllocationSelect2() started, filter:', this.allocatedByFilter);
                    const $select = $('#allocationSelect');
                    if ($select.length === 0) return;

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    // Pre-filter options before initializing Select2
                    $select.find('option').each(function() {
                        const $opt = $(this);
                        const optAllocatedBy = $opt.attr('data-allocated-by');

                        // "Allocation List" is the catch-all: show every unallocated entry
                        // regardless of allocated_by (these rows come straight from
                        // allocation_list_stage, where allocated_by is typically blank).
                        const isMergedAllocationList = self.allocatedByFilter === 'Allocation List';
                        const matchesMergedFilter = isMergedAllocationList;
                        const matchesSpecificFilter = !isMergedAllocationList && optAllocatedBy === self.allocatedByFilter;

                        if (matchesMergedFilter || matchesSpecificFilter) {
                            $opt.prop('disabled', false).show();
                        } else if ($opt.val() === "") {
                            $opt.prop('disabled', false).show(); // Keep the placeholder
                        } else {
                            $opt.prop('disabled', true).hide();
                        }
                    });

                    $select.select2({
                        placeholder: this.allocatedByFilter === 'Allocation List'
                            ? 'Search and select from Allocation List'
                            : `Search and select from ${this.allocatedByFilter} list`,
                        allowClear: true,
                        dropdownParent: document.getElementById('generateModal'),
                        width: '100%',
                        templateResult: function(data) {
                            if (!data.id) return data.text;
                            if ($(data.element).prop('disabled')) return null;
                            return data.text;
                        }
                    });

                    $select.on('select2:open', () => {
                        setTimeout(() => {
                            const searchField = document.querySelector('.select2-search__field');
                            if (searchField) searchField.focus();
                        }, 150);
                    });

                    $select.on('select2:select', (e) => {
                        const data = e.params.data;
                        const $option = $(data.element);
                        
                        this.$nextTick(() => {
                            this.allocationId = data.id;
                            this.fileName = $option.attr('data-full-name') || '';
                            
                            const allotteeAddress = $option.attr('data-allottee-address');
                            if (allotteeAddress && allotteeAddress.trim() && allotteeAddress !== 'null') {
                                this.address = allotteeAddress.trim().toUpperCase();
                            } else {
                                let addressParts = [];
                                const plot = $option.attr('data-plot');
                                const district = $option.attr('data-district');
                                const lga = $option.attr('data-lga');
                                const state = $option.attr('data-state');

                                if (plot && plot.trim() && plot !== 'null') addressParts.push(`Plot ${plot}`);
                                if (district && district.trim() && district !== 'null') addressParts.push(district);
                                if (lga && lga.trim() && lga !== 'null') addressParts.push(lga);
                                if (state && state.trim() && state !== 'null') addressParts.push(state);
                                
                                this.address = addressParts.join(', ').toUpperCase();
                            }
                            this.isInherited = false;
                            this.updatePreview();
                        });
                    });

                    $select.on('select2:unselect', () => {
                        this.allocationId = null;
                        this.fileName = '';
                        this.address = '';
                        this.updatePreview();
                    });
                } catch (e) {
                    console.error('Error in initAllocationSelect2():', e);
                }
            },

            handleAllocationFilterChange() {
                console.log('Allocation Filter Changed to:', this.allocatedByFilter);
                this.allocationId = null;
                this.fileName = '';
                this.address = '';

                if (this.allocatedByFilter !== '') {
                    this.$nextTick(() => {
                        this.initAllocationSelect2();
                    });
                }
                
                this.$nextTick(() => {
                    this.updatePreview();
                });
            },

            // Computed properties
            get showYearSection() {
                return this.fileOption !== 'old_mls' && this.fileOption !== 'sltr';
            },

            get isYearEditable() {
                return isOverrideMode;
            },

            get isSerialEditable() {
                return this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls' || isOverrideMode;
            },

            get isSerialReadonly() {
                // Readonly for normal (auto-generated), temporary, extension, and SIT types
                return (this.fileOption === 'normal' || this.fileOption === 'regrant' || this.fileOption === 'resettlement' || this.fileOption === 'reissuance' || this.fileOption === 'temporary' || this.fileOption === 'extension' || this.fileOption === 'sit') && !isOverrideMode;
            },

            get isSerialDisabled() {
                // Disable for extension and temporary types (not needed)
                return (this.fileOption === 'extension' || this.fileOption === 'temporary') && !isOverrideMode;
            },

            get serialFieldType() {
                return (this.fileOption === 'normal' || this.fileOption === 'regrant' || this.fileOption === 'resettlement' || this.fileOption === 'reissuance') && !isOverrideMode ? 'number' : 'text';
            },

            get yearFieldClass() {
                return this.isYearEditable ? 'w-full px-3 py-2 border border-gray-300 rounded-md text-gray-900' : 'w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600';
            },

            get serialFieldClass() {
                if (this.isSerialDisabled) {
                    return 'w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-500 cursor-not-allowed';
                } else if (this.isSerialReadonly) {
                    return 'w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-700';
                } else {
                    return 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900';
                }
            },

            get serialPlaceholder() {
                if (this.fileOption === 'extension') {
                    return 'Not required for extensions';
                } else if (this.fileOption === 'temporary') {
                    return 'Not required for temporary files';
                } else if (this.fileOption === 'miscellaneous') {
                    return 'Enter custom serial (e.g., 001, ABC123)';
                } else if (this.fileOption === 'sltr') {
                    return 'Enter SLTR serial (e.g., 001, 2024-001)';
                } else if (this.fileOption === 'sit') {
                    return 'Auto-generated';
                } else if (this.fileOption === 'old_mls') {
                    return 'Enter Old MLS number (e.g., 5467, 34874857488758)';
                } else {
                    return 'Auto-generated';
                }
            },

            get serialDescription() {
                if (this.fileOption === 'extension') {
                    return 'Not required for extensions';
                } else if (this.fileOption === 'temporary') {
                    return 'Select existing file for temporary version';
                } else if (this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls') {
                    return 'Manual entry ';
                } else {
                    return 'Auto-generated';
                }
            },

            get serialDescriptionClass() {
                if (this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls' || this.fileOption === 'temporary') {
                    return 'text-blue-600 font-medium';
                } else {
                    return 'text-gray-500';
                }
            },

            get yearDescription() {
                return this.isYearEditable ? 'Editable' : 'Auto-filled';
            },

            get previewClass() {
                return this.preview !== '-' ? 'text-green-600' : 'text-gray-400';
            },

            get isOpFormHidden() {
                // Hide the form below allocation-source checkboxes when the
                // OP allocation source is selected but no OP capture has
                // been completed yet.  This keeps the user focused on the
                // OP capture step before filling the rest of the form.

                // Change of Name flow: always require OP capture first,
                // regardless of which allocation source radio is selected.
                if (this.subSource === 'OP Change of Name') {
                    if (this.sourceInstrumentCaptureId) return false;
                    if (this.sourcePropId) return false;
                    if (window.pendingExistingOpPraContext && window.pendingExistingOpPraContext.prop_id) return false;
                    return true;
                }

                // Determine if OP mode is active — either via requireOpSource
                // (set by OSS page) or _currentAllocationSourceType (MLS page).
                // In both cases the OP allocation radio must actually be selected
                // (allocatedByFilter === '') — not just flagged.
                var isOpMode = (this.requireOpSource && this.allocatedByFilter === '') ||
                    (this._currentAllocationSourceType === 'op' && this.allocatedByFilter === '');

                if (!isOpMode) return false;

                // OP capture already completed — show the form.
                if (this.sourceInstrumentCaptureId) return false;
                if (this.sourcePropId) return false;
                if (window.pendingExistingOpPraContext && window.pendingExistingOpPraContext.prop_id) return false;
                return true;
            },

            get isBatchModeLocked() {
                // Batch mode is no longer locked for any mode.
                return false;
            },

            // Batch Mode Computed Properties
            // Initial Data
            landUses: @json($landUses), // Available for lookups if needed
            allAllPrefixes: @json($allPrefixes ?? []), // The full raw list from controller

            // Computed
            get filledEntriesCount() {
                if (!this.batchMode) return 0;
                return this.locationEntries.filter(entry =>
                    entry.plotNo || entry.tpNo || entry.location || entry.lga || entry.district
                ).length;
            },

            get filteredPrefixes() {
                // Filter the FULL list based on application type
                // Note: We use 'allAllPrefixes' which is the raw list from controller
                if (!this.allAllPrefixes) return [];

                const useConversionPrefixes = this.applicationType === 'conversion'
                    || (
                        this.applicationType === 'change_of_purpose'
                        && this.appTypeRadio === 'conversion'
                    );

                if (useConversionPrefixes) {
                    return this.allAllPrefixes.filter(p => p.prefix.includes('CON-'));
                } else {
                    return this.allAllPrefixes.filter(p => !p.prefix.includes('CON-'));
                }
            },

            // Methods
            normalizeLandUseCode(value) {
                let text = (value || '').toString().trim().toUpperCase();
                if (!text) return '';
                text = text.replace(/^CON-/, '').replace(/-RC$/, '');
                if (text === 'RES' || text.includes('RESIDENTIAL')) return 'RES';
                if (text === 'COM' || text.includes('COMMERCIAL')) return 'COM';
                if (text === 'IND' || text.includes('INDUSTRIAL')) return 'IND';
                if (['AG', 'AGR', 'AGRIC'].includes(text) || text.includes('AGRICULTURAL')) return 'AG';
                return text.split(/[\s(-]+/)[0];
            },

            findLandUseByCode(code) {
                const normalizedCode = this.normalizeLandUseCode(code);
                if (!normalizedCode || !Array.isArray(this.landUses)) return null;

                return this.landUses.find(l => this.normalizeLandUseCode(l.landuse) === normalizedCode)
                    || null;
            },

            normalizeLocationToken(value) {
                return (value || '').toString().trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
            },

            stripPlotFromLocation(location, plotNo = '') {
                const raw = (location || '').toString().trim();
                if (!raw) return '';

                const parts = raw.split(',').map(part => part.trim()).filter(Boolean);
                if (parts.length <= 1) return raw.toUpperCase();

                const first = parts[0];
                const normalizedFirst = this.normalizeLocationToken(first.replace(/^PLOT\s+/i, ''));
                const normalizedPlot = this.normalizeLocationToken(plotNo);

                if (
                    (normalizedPlot && normalizedFirst === normalizedPlot)
                    || /^PLOT\s+/i.test(first)
                    || /^[A-Z]?\d+[A-Z]?$/i.test(first)
                    || /^[A-Z]+-\d+[A-Z]?$/i.test(first)
                ) {
                    parts.shift();
                }

                return parts.join(', ').toUpperCase();
            },

            // Older Change of Purpose records stored only the composite `location`
            // string ("STREET, DISTRICT, LGA, STATE") — district / lga were never
            // persisted. Recover them by matching the comma-separated tokens against
            // the generator's own District / LGA option lists; a token that matches
            // no district but sits directly before the matched LGA is kept as a
            // free-text district (the form's "Other (specify)" case).
            deriveLocationParts(locationStr) {
                const result = { district: '', lga: '' };
                const parts = (locationStr || '').toString()
                    .split(',').map(p => p.trim()).filter(Boolean);
                if (!parts.length) return result;

                const optionValues = (selectId) => {
                    const sel = document.getElementById(selectId);
                    return sel ? Array.from(sel.options).map(o => o.value).filter(Boolean) : [];
                };
                const matchToken = (token, values) => values.find(v =>
                    this.normalizeLocationToken(v) === this.normalizeLocationToken(token)
                ) || '';

                const districts = optionValues('generator_district').filter(v => v !== 'Other');
                const lgas = optionValues('generator_lga');

                let lgaIndex = -1;
                parts.forEach((token, i) => {
                    if (!result.district) {
                        const d = matchToken(token, districts);
                        if (d) { result.district = d; return; }
                    }
                    const l = matchToken(token, lgas);
                    if (l && !result.lga) { result.lga = l; lgaIndex = i; }
                });

                // No listed district: the token just before the LGA is the district
                // the user typed under "Other".
                if (!result.district && lgaIndex > 0) {
                    const candidate = parts[lgaIndex - 1];
                    if (this.normalizeLocationToken(candidate) !== 'OTHER') {
                        result.district = candidate;
                    }
                }

                return result;
            },

            findPrefixForLandUseCode(code) {
                const normalizedCode = this.normalizeLandUseCode(code);
                if (!normalizedCode || !Array.isArray(this.allAllPrefixes)) return null;

                const candidates = this.filteredPrefixes.length ? this.filteredPrefixes : this.allAllPrefixes;
                const preferredPrefix = (
                    this.applicationType === 'conversion'
                    || (this.applicationType === 'change_of_purpose' && this.appTypeRadio === 'conversion')
                )
                    ? `CON-${normalizedCode}`
                    : normalizedCode;

                return candidates.find(p => (p.prefix || '').toString().toUpperCase() === preferredPrefix)
                    || candidates.find(p => (p.prefix || '').toString().toUpperCase() === normalizedCode)
                    || candidates.find(p => (p.prefix || '').toString().toUpperCase().startsWith(`${normalizedCode}-`))
                    || candidates.find(p => (p.prefix || '').toString().toUpperCase().startsWith(`${preferredPrefix}-`))
                    || candidates.find(p => {
                        const lu = this.landUses.find(l => l.id == p.land_use_id);
                        return lu && this.normalizeLandUseCode(lu.landuse) === normalizedCode;
                    })
                    || null;
            },

            handlePrefixChange(event) {
                if (event) {
                    console.log('handlePrefixChange called via event', event.target.value);
                }
                // Find the selected prefix object from our full list
                // We can't rely just on the filtered list index, so find by string
                const selectedPrefixStr = this.prefix; 
                if (!selectedPrefixStr) {
                    this.landUse = '';
                    this.purpose = '';
                    this.purposes = [];
                    return;
                }

                const prefixObj = this.allAllPrefixes.find(p => p.prefix === selectedPrefixStr);
                const mappedLandUseCode = this.normalizeLandUseCode(selectedPrefixStr);
                const mappedLandUse = this.findLandUseByCode(mappedLandUseCode);
                
                if (prefixObj && prefixObj.land_use_id) {
                      // 1. Find the Land Use Code based on ID (to set the model)
                     // We need to iterate our select options or look up in landUses array
                     // The landUses array from PHP has 'id', 'landuse' (name).
                     // But our model 'this.landUse' expects the CODE (e.g., 'RES', 'COM').
                     // We need a way to map ID -> CODE. 
                     // Let's use the HTML select options logic to be consistent or just map it here.
                     
                     const lu = mappedLandUse || this.landUses.find(l => l.id == prefixObj.land_use_id);
                     if (lu) {
                        const code = mappedLandUseCode || this.normalizeLandUseCode(lu.landuse);
                         
                        this.landUse = code;
                        
                        // Auto-set Customer Type based on Land Use
                        if (code === 'COM' || code === 'IND') {
                            this.customerType = 'Corporate';
                        } else {
                            this.customerType = 'Individual';
                        }
                        
                        // 2. Fetch Purposes for this Land Use ID
                        const resolvedLandUseId = mappedLandUse ? mappedLandUse.id : prefixObj.land_use_id;
                        if (this.landUseId == resolvedLandUseId && this.purposes.length === 0) {
                            this.fetchDependentData(resolvedLandUseId);
                        }
                        this.landUseId = resolvedLandUseId;
                        // fetchDependentData will be triggered by watcher on landUseId if it changes
                      }
                } else if (mappedLandUse) {
                    this.landUse = mappedLandUseCode;
                    this.customerType = (mappedLandUseCode === 'COM' || mappedLandUseCode === 'IND') ? 'Corporate' : 'Individual';
                    this.landUseId = mappedLandUse.id;
                }
                
                this.updatePreview();

                // Detect recertification prefix (-RC)
                this.isRecertificationPrefix = selectedPrefixStr.includes('-RC');
                if (!this.isRecertificationPrefix) {
                    this.clearRelatedFile();
                }
            },

            // Open GlobalFileNoModal to select related file (recertification)
            // index === null targets the primary related file (relatedFileNo/Title, which
            // the -RC validation and PRA lineage still read); an integer targets that slot
            // in extraRelatedFiles.
            openRelatedFileModal(index = null) {
                if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
                    alert('File number selector is not available. Please refresh the page.');
                    return;
                }
                const self = this;
                GlobalFileNoModal.open({
                    callback: function(data) {
                        if (!data || !data.fileNumber) return;

                        if (index !== null) {
                            const row = self.extraRelatedFiles[index];
                            if (!row) return;
                            row.file_no = (data.fileNumber || '').toString().replace(/[\s-]+$/, '').trim();
                            row.title = (
                                data.file_name
                                || data.file_title
                                || (data.record && (data.record.file_name || data.record.FileName || data.record.file_title))
                                || ''
                            ).toString().trim();
                            row.indexing_id = (data.record && data.record.id) || '';
                            return;
                        }

                        // Legacy KN/KANGIS records store the file number with a trailing dash
                        // (e.g. "KN 3456-"); strip any trailing dash/whitespace so the related
                        // file number is clean for any selected file.
                        self.relatedFileNo = (data.fileNumber || '').toString().replace(/[\s-]+$/, '').trim();
                        // The modal already resolves the best available title (file_title || file_name)
                        // and passes it at the top level; fall back to record fields, then leave blank
                        // so the officer can type it manually for legacy files with no stored title.
                        self.relatedFileTitle = (
                            data.file_name
                            || data.file_title
                            || (data.record && (data.record.file_name || data.record.FileName || data.record.file_title))
                            || ''
                        ).toString().trim();
                        self.relatedFileIndexingId = (data.record && data.record.id) || '';

                        // Inherit the property location from the original/related file
                        // (e.g. recertification keeps the same property) so District,
                        // LGA and Location backfill on the card.
                        self.backfillLocationFromFile(self.relatedFileNo);
                    }
                });
            },

            // Pull only the location fields (location, district, lga) for a file from
            // the centralized lookup and sync the dropdowns. Used when linking a
            // related/original file where we inherit the property location but keep
            // the new file's own name, prefix and land use.
            backfillLocationFromFile(fileNumber) {
                const clean = (fileNumber || '').toString().replace(/[\s-]+$/, '').trim();
                if (!clean) return;
                const self = this;
                const baseUrl = "{{ route('api.file-numbers.lookup') }}";
                fetch(`${baseUrl}?file_number=${encodeURIComponent(clean)}`)
                    .then(r => r.json())
                    .then(res => {
                        if (!res || !res.success || !res.data) return;
                        const m = res.data;
                        if (m.location) self.location = (m.location || '').toString().toUpperCase();
                        if (m.lga) self.lga = m.lga;
                        if (m.district) self.district = m.district;
                        if (m.location || m.lga || m.district) {
                            self.$nextTick(() => self.syncLocationSelects());
                        }
                    })
                    .catch(err => console.error('Related file location backfill failed:', err));
            },

            // Open GlobalFileNoModal to select the old (duplicated) file being re-issued.
            openOldFileModal() {
                if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
                    alert('File number selector is not available. Please refresh the page.');
                    return;
                }
                const self = this;
                GlobalFileNoModal.open({
                    callback: function(data) {
                        if (!data || !data.fileNumber) return;

                        // Legacy KN/KANGIS records store a trailing dash (e.g. "KN 3456-").
                        self.oldFileNo = (data.fileNumber || '').toString().replace(/[\s-]+$/, '').trim();
                        self.oldFileTitle = (
                            data.file_name
                            || data.file_title
                            || (data.record && (data.record.file_name || data.record.FileName || data.record.file_title))
                            || ''
                        ).toString().trim();
                        self.oldFileIndexingId = (data.record && data.record.id) || '';

                        // Re-issuance covers the same property, so inherit its location.
                        self.backfillLocationFromFile(self.oldFileNo);
                    }
                });
            },

            clearOldFile() {
                this.oldFileNo = '';
                this.oldFileTitle = '';
                this.oldFileIndexingId = '';
            },

            clearRelatedFile() {
                this.relatedFileNo = '';
                this.relatedFileTitle = '';
                this.relatedFileIndexingId = '';
                this.relatedFileType = '';
                this.relatedFileTypeOther = '';
                this.extraRelatedFiles = [];
            },

            addRelatedFile() {
                this.extraRelatedFiles.push({ file_no: '', title: '', type: '', type_other: '', indexing_id: '' });
            },

            removeRelatedFile(index) {
                this.extraRelatedFiles.splice(index, 1);
            },

            // Every related file the officer entered, primary row first, as
            // {file_no, title, type, indexing_id}. Rows without a file number are dropped
            // so a half-filled "Add more" row can't reach the backend.
            relatedFilesPayload() {
                const rows = [{
                    file_no: this.relatedFileNo,
                    title: this.relatedFileTitle,
                    type: this.relatedFileType,
                    type_other: this.relatedFileTypeOther,
                    indexing_id: this.relatedFileIndexingId
                }].concat(this.extraRelatedFiles || []);

                return rows
                    .map(r => ({
                        file_no: (r.file_no || '').toString().trim(),
                        title: (r.title || '').toString().trim(),
                        type: (r.type || '').toString().trim(),
                        type_other: (r.type_other || '').toString().trim(),
                        indexing_id: r.indexing_id || ''
                    }))
                    .filter(r => r.file_no !== '');
            },

            // Modified to only fetch Purposes (and potentially Prefixes but we have them already)
            // renaming conceptually to fetchPurposes might be better but keeping name for minimal diff
            fetchDependentData(landUseId) {
                if (!landUseId) {
                    this.purposes = [];
                    // this.prefixes = []; // Don't clear prefixes as they drive the selection
                    this.purpose = '';
                    // this.prefix = ''; 
                    return;
                }

                showGlobalLoading('Fetching purposes...');

                fetch(`{{ route('mls-fileno.get-dependent-data') }}?land_use_id=${landUseId}`)
                    .then(response => response.json())
                    .then(data => {
                        this.purposes = data.purposes || [];
                        // this.prefixes = data.prefixes || []; // We use the global list now
                        
                        // Reset purpose
                        this.purpose = '';
                    })
                    .catch(error => {
                        console.error('Error fetching dependent data:', error);
                        this.purposes = [];
                        // this.prefixes = []; // Don't touch prefixes
                    })
                    .finally(() => {
                         hideGlobalLoading();
                    });
            },

            fetchAllPurposes() {
                fetch(`{{ route('mls-fileno.get-dependent-data') }}?land_use_id=all`)
                    .then(response => response.json())
                    .then(data => {
                        this.purposes = data.purposes || [];
                        this.purpose = '';
                    })
                    .catch(error => {
                        console.error('Error fetching all purposes:', error);
                        this.purposes = [];
                    });
            },



            updateApplicationType() {
                // While File Type is Change of Purpose, the Direct Allocation /
                // Conversion radios (bound to appTypeRadio, not applicationType)
                // are informational only — some CoP files are also conversions,
                // but applicationType (and the "source" the backend receives)
                // must stay 'change_of_purpose' so the CoP application picker
                // stays visible and the file still commissions through the CoP
                // branch instead of falling into generic Conversion handling.
                if (this.fileTypeWorkflow === 'change_of_purpose') {
                    this.applicationType = 'change_of_purpose';
                    this.updatePreview();
                    return;
                }

                this.applicationType = this.appTypeRadio;

                this.landUse = '';
                this.prefix = ''; // Reset prefix when application type changes

                if (this.applicationType === 'new') {
                    this.allocatedByFilter = '';
                } else {
                    this.allocatedByFilter = '';
                    this.defaultAllocationType = '';
                    // Leaving OP mode – clear OP-specific flags so they
                    // don't interfere with Conversion / Allocation List.
                    this.requireOpSource = false;
                    window.ossOpContext = false;
                }

                // Reset file option to normal if SIT is selected but conversion type is chosen
                if (this.fileOption === 'sit' && this.applicationType === 'conversion') {
                    this.fileOption = 'normal';
                }

                // Conversion links to exactly one existing file. Drop any extra related-file
                // rows carried over from another type so they don't post silently while the
                // "Add Another Related File" button is hidden.
                if (this.applicationType === 'conversion') {
                    this.extraRelatedFiles = [];
                }

                // Enforce OP rule after app type change.
                if (this.applicationType === 'new' && this.allocatedByFilter === '') {
                    // this.batchMode = false; // Allow batch mode for direct allocation
                    // this.locationEntries = [];
                    // this.currentEntryIndex = 0;
                    // this.serialRangePreview = '-';
                }
                this.updatePreview();
            },

            openSubdivisionFileModal() {
                if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
                    alert('File number selector is not available. Please refresh the page.');
                    return;
                }
                const self = this;
                GlobalFileNoModal.open({
                    callback: function(data) {
                        if (!data || !data.fileNumber) return;
                        
                        showGlobalLoading('Verifying subdivision...');
                        fetch(`{{ route('plot-subdivision.find-by-file', '') }}/${encodeURIComponent(data.fileNumber)}`)
                            .then(response => response.json())
                            .then(res => {
                                hideGlobalLoading();
                                if (res.success) {
                                    self.subdivisionFileNo = data.fileNumber;
                                    self.relatedFileNo = data.fileNumber;
                                    self.subdivisionAppId = res.data.id;
                                    
                                    // Backfill details — use file_title from mother file only, not applicant_name
                                    self.fileName = res.data.file_title || '';
                                    self.plotNo = res.data.plot_no || '';
                                    self.lga = res.data.lga || '';
                                    self.district = res.data.district || '';

                                    // Construct location from available components
                                    let locParts = [];
                                    if (res.data.house_no) locParts.push(res.data.house_no);
                                    if (res.data.street_name) locParts.push(res.data.street_name);
                                    if (res.data.district) locParts.push(res.data.district);
                                    self.location = locParts.length > 0 ? locParts.join(', ').toUpperCase() : '';
                                    self.$nextTick(() => self.syncLocationSelects());

                                    self.isInherited = true;

                                    // Detect full prefix from source file number (e.g. IND-RC from IND-RC-2026-4)
                                    let detectedPrefix = '';
                                    if (data.fileNumber) {
                                        const parts = data.fileNumber.split('-');
                                        const yearIndex = parts.findIndex(p => /^(19|20)\d{2}$/.test(p));
                                        if (yearIndex > 0) {
                                            detectedPrefix = parts.slice(0, yearIndex).join('-').toUpperCase();
                                        } else {
                                            detectedPrefix = parts[0].toUpperCase();
                                        }
                                    }

                                    /*  
                                    // Auto-detect Conversion type - REMOVED: Transaction type should remain subdivision/merger
                                    if (detectedPrefix.startsWith('CON-')) {
                                        self.applicationType = 'conversion';
                                        self.updateApplicationType();
                                    }
                                    */

                                    self.$nextTick(() => {
                                        if (detectedPrefix && self.allAllPrefixes) {
                                            // Find exact match first
                                            let bestPrefix = self.allAllPrefixes.find(p => p.prefix === detectedPrefix);
                                            
                                            if (!bestPrefix) {
                                                // Try partial match
                                                bestPrefix = self.allAllPrefixes.find(p => p.prefix.startsWith(detectedPrefix) || detectedPrefix.startsWith(p.prefix));
                                            }
                                            
                                            if (bestPrefix) {
                                                self.prefix = bestPrefix.prefix;
                                                // Sync landUseId and purposes
                                                self.handlePrefixChange({ target: { value: self.prefix } });
                                            }
                                        }
                                    });

                                    // Fallback for landUseId if prefix match fails
                                    if (!self.prefix && self.landUses) {
                                        const luCode = (res.data.land_use || detectedPrefix.split('-')[0]).toUpperCase();
                                        const luEntity = self.landUses.find(l => {
                                            const name = l.landuse.toUpperCase();
                                            return (luCode === 'RES' && name.includes('RESIDENTIAL')) ||
                                                   (luCode === 'COM' && name.includes('COMMERCIAL')) ||
                                                   (luCode === 'IND' && name.includes('INDUSTRIAL')) ||
                                                   (luCode.startsWith('AG') && name.includes('AGRICULTURAL'));
                                        });
                                        if (luEntity) self.landUseId = luEntity.id;
                                    }

                                    self.batchMode = true;
                                    
                                    // Populate locationEntries for batch mode FIRST to prevent watcher from overwriting
                                    const bQty = parseInt(res.data.num_plots) || 0;
                                    if (bQty > 0) {
                                        let entries = [];
                                        for (let i = 0; i < bQty; i++) {
                                            entries.push({
                                                plotNo: res.data.plot_no || '',
                                                tpNo: res.data.tp_no || '',
                                                location: self.location,
                                                lga: self.lga,
                                                district: self.district,
                                                tracking_id: null
                                            });
                                        }
                                        self.locationEntries = entries;
                                    }
                                    
                                    self.batchQuantity = bQty;

                                    // Trigger Alpine reactivity
                                    self.$nextTick(() => {
                                        if (typeof self.updatePreview === 'function') {
                                            self.updatePreview();
                                        }
                                    });
                                    
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Subdivision Found',
                                        text: `This file has an approved subdivision for ${res.data.num_plots} plots. Batch mode enabled.`,
                                        timer: 3000,
                                        showConfirmButton: false
                                    });
                                } else {
                                    Swal.fire('Not Found', res.message, 'warning');
                                }
                            })
                            .catch(err => {
                                hideGlobalLoading();
                                console.error(err);
                                Swal.fire('Error', 'Failed to lookup subdivision application.', 'error');
                            });
                    }
                });
            },

            openMergerFileModal() {
                const self = this;
                const modal = document.getElementById('mergerFileModal');
                const searchInput = document.getElementById('mergerSearchInput');
                
                if (!modal) {
                    alert('Merger selection modal not found.');
                    return;
                }

                modal.classList.remove('hidden');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                
                // Load initial list
                searchMergerFiles('');

                // Register callback
                window._mergerCallback = (data) => {
                    self.mergerFileNo = data.temp_file_no; // Display Temp File No as Source
                    
                    // Use source file numbers for related link
                    if (data.source_file_nos && Array.isArray(data.source_file_nos) && data.source_file_nos.length > 0) {
                        self.relatedFileNo = data.source_file_nos.join(', ');
                        self.relatedFileTitle = 'Consolidated Titles';
                    } else {
                        self.relatedFileNo = data.temp_file_no;
                        self.relatedFileTitle = 'Source Merger File';
                    }
                    
                    self.mergerAppId = data.id;
                    
                    // Backfill details
                    self.fileName = data.applicant_name || data.file_title || '';
                    self.plotNo = data.plot_no || '';
                    self.lga = data.lga || '';
                    self.district = data.district || '';

                    // Construct location from available components
                    let locParts = [];
                    if (data.house_no) locParts.push(data.house_no);
                    if (data.street_name) locParts.push(data.street_name);
                    if (data.district) locParts.push(data.district);
                    self.location = locParts.length > 0 ? locParts.join(', ').toUpperCase() : '';
                    self.$nextTick(() => self.syncLocationSelects());
                    
                    // Detect full prefix from source file number (e.g. CON-IND from CON-IND-2026-1)
                    let detectedPrefix = '';
                    if (data.temp_file_no || data.file_no) {
                        const fn = data.temp_file_no || data.file_no;
                        const parts = fn.split('-');
                        const yearIndex = parts.findIndex(p => /^(19|20)\d{2}$/.test(p));
                        if (yearIndex > 0) {
                            detectedPrefix = parts.slice(0, yearIndex).join('-').toUpperCase();
                        } else {
                            detectedPrefix = parts[0].toUpperCase();
                        }
                    }

                    /*
                    // Auto-detect Conversion type - REMOVED: Transaction type should remain subdivision/merger
                    if (detectedPrefix.startsWith('CON-')) {
                        self.applicationType = 'conversion';
                        self.updateApplicationType();
                    }
                    */

                    self.$nextTick(() => {
                        if (detectedPrefix && self.allAllPrefixes) {
                            // Find exact match first
                            let bestPrefix = self.allAllPrefixes.find(p => p.prefix === detectedPrefix);
                            
                            if (!bestPrefix) {
                                // Try partial match
                                bestPrefix = self.allAllPrefixes.find(p => p.prefix.startsWith(detectedPrefix) || detectedPrefix.startsWith(p.prefix));
                            }
                            
                            if (bestPrefix) {
                                self.prefix = bestPrefix.prefix;
                                // Sync landUseId and purposes
                                self.handlePrefixChange({ target: { value: self.prefix } });
                            }
                        }
                    });

                    // Fallback for landUseId if prefix match fails
                    if (!self.prefix && self.landUses) {
                        const luCode = (data.land_use || detectedPrefix.split('-')[0]).toUpperCase();
                        const luEntity = self.landUses.find(l => {
                            const name = l.landuse.toUpperCase();
                            return (luCode === 'RES' && name.includes('RESIDENTIAL')) ||
                                   (luCode === 'COM' && name.includes('COMMERCIAL')) ||
                                   (luCode === 'IND' && name.includes('INDUSTRIAL')) ||
                                   (luCode.startsWith('AG') && name.includes('AGRICULTURAL'));
                        });
                        if (luEntity) self.landUseId = luEntity.id;
                    }

                    self.isInherited = true;

                    self.updatePreview();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Merger Selected',
                        text: `Source: ${data.temp_file_no}`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                };
            },

            openSeparationFileModal() {
                if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
                    alert('File number selector is not available. Please refresh the page.');
                    return;
                }
                const self = this;
                GlobalFileNoModal.open({
                    callback: function(data) {
                        if (!data || !data.fileNumber) return;

                        showGlobalLoading('Verifying separation...');
                        fetch(`{{ route('plot-separation.find-by-file', '') }}/${encodeURIComponent(data.fileNumber)}`)
                            .then(response => response.json())
                            .then(res => {
                                hideGlobalLoading();
                                if (res.success) {
                                    self.separationFileNo = data.fileNumber;
                                    self.relatedFileNo = data.fileNumber;
                                    self.separationAppId = res.data.id;

                                    // Backfill details — use file_title from mother file only
                                    self.fileName = res.data.file_title || '';
                                    self.plotNo = res.data.plot_no || '';
                                    self.lga = res.data.lga || '';
                                    self.district = res.data.district || '';

                                    // Construct location from available components
                                    let locParts = [];
                                    if (res.data.house_no) locParts.push(res.data.house_no);
                                    if (res.data.street_name) locParts.push(res.data.street_name);
                                    if (res.data.district) locParts.push(res.data.district);
                                    self.location = locParts.length > 0 ? locParts.join(', ').toUpperCase() : '';
                                    self.$nextTick(() => self.syncLocationSelects());

                                    self.isInherited = true;

                                    // Detect full prefix from source file number
                                    let detectedPrefix = '';
                                    if (data.fileNumber) {
                                        const parts = data.fileNumber.split('-');
                                        const yearIndex = parts.findIndex(p => /^(19|20)\d{2}$/.test(p));
                                        if (yearIndex > 0) {
                                            detectedPrefix = parts.slice(0, yearIndex).join('-').toUpperCase();
                                        } else {
                                            detectedPrefix = parts[0].toUpperCase();
                                        }
                                    }

                                    self.$nextTick(() => {
                                        if (detectedPrefix && self.allAllPrefixes) {
                                            let bestPrefix = self.allAllPrefixes.find(p => p.prefix === detectedPrefix);
                                            if (!bestPrefix) {
                                                bestPrefix = self.allAllPrefixes.find(p => p.prefix.startsWith(detectedPrefix) || detectedPrefix.startsWith(p.prefix));
                                            }
                                            if (bestPrefix) {
                                                self.prefix = bestPrefix.prefix;
                                                self.handlePrefixChange({ target: { value: self.prefix } });
                                            }
                                        }
                                    });

                                    // Fallback for landUseId if prefix match fails
                                    if (!self.prefix && self.landUses) {
                                        const luCode = (res.data.land_use || detectedPrefix.split('-')[0]).toUpperCase();
                                        const luEntity = self.landUses.find(l => {
                                            const name = l.landuse.toUpperCase();
                                            return (luCode === 'RES' && name.includes('RESIDENTIAL')) ||
                                                   (luCode === 'COM' && name.includes('COMMERCIAL')) ||
                                                   (luCode === 'IND' && name.includes('INDUSTRIAL')) ||
                                                   (luCode.startsWith('AG') && name.includes('AGRICULTURAL'));
                                        });
                                        if (luEntity) self.landUseId = luEntity.id;
                                    }

                                    self.batchMode = true;

                                    // Populate locationEntries for batch mode
                                    const bQty = parseInt(res.data.num_plots) || 0;
                                    if (bQty > 0) {
                                        let entries = [];
                                        for (let i = 0; i < bQty; i++) {
                                            entries.push({
                                                plotNo: res.data.plot_no || '',
                                                tpNo: res.data.tp_no || '',
                                                location: self.location,
                                                lga: self.lga,
                                                district: self.district,
                                                tracking_id: null
                                            });
                                        }
                                        self.locationEntries = entries;
                                    }

                                    self.batchQuantity = bQty;

                                    self.$nextTick(() => {
                                        if (typeof self.updatePreview === 'function') {
                                            self.updatePreview();
                                        }
                                    });

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Separation Found',
                                        text: `This file has an approved separation for ${res.data.num_plots} plots. Batch mode enabled.`,
                                        timer: 3000,
                                        showConfirmButton: false
                                    });
                                } else {
                                    Swal.fire('Not Found', res.message, 'warning');
                                }
                            })
                            .catch(err => {
                                hideGlobalLoading();
                                console.error(err);
                                Swal.fire('Error', 'Failed to lookup separation application.', 'error');
                            });
                    }
                });
            },

            openCopFileSelector() {
                const modal = document.getElementById('copFileModal');
                const searchInput = document.getElementById('copSearchInput');
                
                if (!modal) {
                    alert('Change of Purpose selection modal not found.');
                    return;
                }

                modal.classList.remove('hidden');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                
                // Load initial list
                searchCopFiles('');

                // Register callback
                window._copCallback = (data) => {
                    this.originalFileNo = data.file_no;
                    this.relatedFileNo = data.file_no;
                    this.changeOfPurposeAppId = data.id;
                    this.copApplicantName = data.applicant_name;
                    
                    // These are used for the summary display in Blade (which we should add to Alpine data if missing)
                    this.copCurrentLandUse = data.land_use;
                    this.copNewPurpose = data.purpose;
                    
                    // Set prefix/land use from the CoP application's new purpose.
                    const newLuCode = this.normalizeLandUseCode(data.purpose || data.new_purpose || '');
                    this.newPurpose = newLuCode;
                    this.landUse = newLuCode;

                    const matchedPrefix = this.findPrefixForLandUseCode(newLuCode);
                    if (matchedPrefix) {
                        this.prefix = matchedPrefix.prefix;
                        this.handlePrefixChange({ target: { value: this.prefix } });
                    } else if (this.landUses) {
                        const luEntity = this.landUses.find(l => this.normalizeLandUseCode(l.landuse) === newLuCode);
                        if (luEntity) {
                            this.landUseId = luEntity.id;
                        }
                    }

                    // Backfill details
                    this.fileName = data.applicant_name || '';
                    this.plotNo = data.plot_no || '';
                    this.location = this.stripPlotFromLocation(data.location || '', this.plotNo);
                    // district / lga are only present on newer CoP records; fall back
                    // to parsing them out of the location string for older ones.
                    const derived = this.deriveLocationParts(data.location || '');
                    this.lga = data.lga || derived.lga || '';
                    this.district = data.district || derived.district || '';
                    this.isInherited = true;
                    this.$nextTick(() => {
                        this.syncLocationSelects();
                        this.updatePreview();
                        if (typeof getNextSerialNumber === 'function') {
                            getNextSerialNumber();
                        }
                    });
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Application Selected',
                        text: `File No: ${data.file_no}`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                };
            },

            /**
             * Backs the top "FILE TYPE" select. This lives here as a method rather than as an
             * inline @change expression on purpose: the previous inline version opened with
             * `let val = ...`, and Alpine only tolerates a leading let/const by special-casing
             * it into an async IIFE. Anything that defeats that detection makes the whole
             * handler a SyntaxError that fails silently in the console — the select then shows
             * the picked type while fileOption never changes, and the file gets committed with
             * the wrong Source. A plain method call has no such failure mode.
             */
            applyFileTypeWorkflow(val) {
                this.fileTypeWorkflow = val;

                if (val === 'change_of_purpose') {
                    this.applicationType = 'change_of_purpose';
                    this.fileOption = 'normal';
                    this.updateApplicationType();
                    return;
                }

                // Restore applicationType from the Direct Allocation / Conversion radio
                // (appTypeRadio is the source of truth for that choice — applicationType
                // may still be 'change_of_purpose' left over from CoP mode here).
                this.applicationType = this.appTypeRadio || 'new';

                // Re-grant numbers like a normal file but keeps its own tag so the backend
                // records Source as "Re-grant" instead of "Direct Allocation".
                this.fileOption = val === '' ? 'normal' : val;
                this.updateFileOption();
            },

            updateFileOption() {
                // Detect a fresh transition INTO the extension file type so we can
                // prompt for the extension category (File vs Plot) exactly once.
                const switchingToExtension = this.fileOption === 'extension' && this._lastFileOption !== 'extension';

                // Clear serial number when changing file option to special types
                if (this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls') {
                    this.serialNo = '';
                    this.isInherited = false;
                } else if (this.fileOption === 'sit') {
                    // SIT: auto-serial, no land use, customer type is always Government
                    this.landUse = 'SIT';
                    this.customerType = 'Government';
                    this.isInherited = false;
                    // Load the auto-generated serial for SIT
                    this.serialNo = getNextSerialForLandUse('SIT');
                    // Load all purposes (not filtered by land use)
                    this.fetchAllPurposes();
                } else if (this.fileOption === 'extension' || this.fileOption === 'temporary') {
                    this.serialNo = '';
                    this.existingFileNo = ''; // Clear existing file selection
                    this.isInherited = false;
                    // Reset fields that might have been inherited
                    this.fileName = '';
                    this.plotNo = '';
                    this.tpNo = '';
                    this.location = '';
                    this.lga = '';
                    this.phone_no = '';
                    this.address = '';
                    this.rep_phone_no = '';
                    this.rep_address = '';
                    if (this.fileOption === 'extension') {
                        // Reset category until the user explicitly picks one in the popup.
                        this.extensionType = '';
                    }
                } else if (this.fileOption === 'normal' || this.fileOption === 'regrant' || this.fileOption === 'resettlement' || this.fileOption === 'reissuance') {
                    this.isInherited = false;
                    // Reset SIT-specific overrides if switching from SIT
                    if (this.landUse === 'SIT') {
                        this.landUse = '';
                        this.customerType = '';
                        this.purposes = [];
                        this.purpose = '';
                        this.prefix = '';
                    }
                    // Reset to auto-generated for normal/regrant files based on land use
                    if (this.landUse || this.prefix) {
                        this.serialNo = getNextSerialForLandUse(this.prefix || this.landUse);
                    }
                } else if (this.fileOption === 'subdivision' || this.fileOption === 'merger' || this.fileOption === 'separation') {
                    this.serialNo = '';
                    this.subdivisionAppId = '';
                    this.mergerAppId = '';
                    this.separationAppId = '';
                    this.subdivisionFileNo = '';
                    this.mergerFileNo = '';
                    this.separationFileNo = '';
                    this.batchMode = false;
                    this.isInherited = false;

                    // Reset fields
                    this.fileName = '';
                    this.plotNo = '';
                    this.tpNo = '';
                    this.location = '';
                    this.lga = '';
                }

                this._lastFileOption = this.fileOption;

                // Prompt for extension category (File vs Plot) on a fresh switch to Extension.
                if (switchingToExtension && typeof window.openExtensionTypeModal === 'function') {
                    window.openExtensionTypeModal();
                }

                this.updatePreview();
            },

            updatePreview() {
                // Auto-update serial number when land use changes for normal, subdivision, merger, and temporary files
                if (['normal', 'regrant', 'resettlement', 'reissuance', 'subdivision', 'merger', 'separation', 'temporary'].includes(this.fileOption) && (this.landUse || this.prefix) && !isOverrideMode) {
                    if (this.fileOption === 'temporary' && this.existingFileNo) {
                        // Keep extracted serial from existing file for temporary files
                    } else {
                        const newSerial = getNextSerialForLandUse(this.prefix || this.landUse);
                        if (this.serialNo !== newSerial) {
                            this.serialNo = newSerial;
                        }
                    }
                }

                // Auto-update serial for SIT files using MlsSerialControl
                if (this.fileOption === 'sit' && !isOverrideMode) {
                    const sitSerial = getNextSerialForLandUse('SIT');
                    if (this.serialNo !== sitSerial) {
                        this.serialNo = sitSerial;
                    }
                }

                let previewText = '-';

                // Generate base file number for extension/temporary fallback
                let baseFileNumber = '';
                if (this.serialNo && this.year) {
                    const code = this.prefix || this.landUse;
                    if (code) {
                        baseFileNumber = `${code}-${this.year}-${this.serialNo}`;
                    }
                }

                if (this.fileOption === 'extension') {
                    const baseExt = (this.existingFileNo || baseFileNumber).trim().replace(/-$/, '');
                    // Plot Extension retains the original file number unchanged.
                    previewText = this.extensionType === 'plot' ? baseExt : baseExt + ' AND EXTENSION';
                } else if (this.fileOption === 'temporary') {
                    // For temporary files, use baseFileNumber (next available) + (T) as per user request
                    previewText = baseFileNumber + '(T)';
                } else if (this.fileOption === 'miscellaneous' && this.middlePrefix && this.serialNo && this.year) {
                    previewText = `MISC-${this.middlePrefix}-${this.year}-${this.serialNo}`;
                } else if (this.fileOption === 'old_mls' && this.serialNo) {
                    previewText = `KN ${this.serialNo}`;
                } else if (this.fileOption === 'sltr' && this.serialNo) {
                    previewText = `SLTR-${this.serialNo}`;
                } else if (this.fileOption === 'sit' && this.serialNo) {
                    previewText = `SIT-${this.year}-${this.serialNo}`;
                } else if (['normal', 'regrant', 'resettlement', 'reissuance', 'subdivision', 'merger'].includes(this.fileOption) && this.serialNo && this.year) {
                    const code = this.prefix || this.landUse;
                    if (code) {
                         previewText = `${code}-${this.year}-${this.serialNo}`;
                    }
                }

                this.preview = previewText;

                // Update Land Use Full Text to show only exact land use (e.g., Commercial instead of COM - Commercial)
                if (this.fileOption === 'sit') {
                    // SIT files don't have land use - show only purpose if selected
                    this.landUseFullText = '';
                } else if (this.landUse) {
                    const lu = (this.landUse || '').toUpperCase();
                    if (lu.includes('COM')) {
                        this.landUseFullText = 'Commercial';
                    } else if (lu.includes('IND')) {
                        this.landUseFullText = 'Industrial';
                    } else if (lu.includes('AG')) {
                        this.landUseFullText = 'Agricultural';
                    } else if (lu.includes('RES')) {
                        this.landUseFullText = 'Residential';
                    } else {
                        // Fallback logic for any other prefixes
                        const select = document.getElementById('landUse');
                        if (select && select.selectedIndex >= 0) {
                            const text = select.options[select.selectedIndex].text;
                            this.landUseFullText = text.includes(' - ') ? text.split(' - ')[1] : text;
                        } else {
                            this.landUseFullText = this.landUse;
                        }
                    }
                } else {
                    this.landUseFullText = '-';
                }

                // Append Purpose if available
                if (this.purpose) {
                     // Find purpose name from array
                     const selectedPurpose = this.purposes.find(p => p.id == this.purpose);
                     if (selectedPurpose) {
                         this.landUseFullText += ` (${selectedPurpose.name})`;
                     }
                }

                // Trigger grouping lookup to enable/disable Generate button.
                // Change of Purpose looks up the grouping table by the NEW (target)
                // file number, e.g. IND-2026-242 — that's what gets pre-registered
                // there, and it's exactly what MlsFileNoController's own
                // tryFetchTrackingIdFromGrouping($fullFileNumber) reads server-side
                // during commissioning. originalFileNo (the OLD file) was never in
                // that table, which is why looking it up under the old number
                // always came back "Not Found".
                if (this.applicationType === 'change_of_purpose') {
                    if (previewText && previewText !== '-' && typeof queueGroupingLookup === 'function') {
                        queueGroupingLookup(previewText);
                    } else if (typeof resetTrackingIdDisplay === 'function') {
                        resetTrackingIdDisplay('--');
                    }
                } else if (typeof deriveGroupingLookupCandidate === 'function' && typeof queueGroupingLookup === 'function') {
                    const candidate = this.existingFileNo || this.subdivisionFileNo || this.mergerFileNo;
                    const lookupCandidate = deriveGroupingLookupCandidate(this.fileOption, previewText, candidate);
                    if (lookupCandidate) {
                        queueGroupingLookup(lookupCandidate);
                    } else {
                        // If no candidate, we might need to reset.
                        // But rely on global functions to handle reset if needed.
                        // Actually, global updatePreview calls resetTrackingIdDisplay('--') if no candidate.
                        if (typeof resetTrackingIdDisplay === 'function') {
                             resetTrackingIdDisplay('--');
                        }
                    }
                }

                // If in batch mode, call updateBatchPreview to override this.preview with range
                if (this.batchMode) {
                    this.updateBatchPreview();
                }
            },

            // Method to refresh serial number from external call
            refreshSerialNumber() {
                if ((this.fileOption === 'normal' || this.fileOption === 'regrant' || this.fileOption === 'resettlement' || this.fileOption === '') && !isOverrideMode) {
                    const code = this.prefix || this.landUse;
                    if (code) {
                        this.serialNo = getNextSerialForLandUse(code);
                        this.updatePreview();
                    }
                }
            },

            // Batch Mode Methods
            toggleBatchMode() {
                // Toggling by hand starts a fresh batch, so any allottees carried over from a
                // previous Batch Capture OP hand-off no longer line up — drop them.
                this.opBatchAllottees = [];

                if (this.isBatchModeLocked) {
                    this.batchMode = false;
                    this.locationEntries = [];
                    this.currentEntryIndex = 0;
                    this.serialRangePreview = '-';
                    return;
                }

                if (this.batchMode) {
                    // Initialize location entries array
                    this.initializeLocationEntries();
                    this.updateBatchPreview();
                } else {
                    // Clear batch data
                    this.locationEntries = [];
                    this.currentEntryIndex = 0;
                    this.serialRangePreview = '-';
                }
            },

            initializeLocationEntries() {
                this.locationEntries = [];
                for (let i = 0; i < this.batchQuantity; i++) {
                    this.locationEntries.push({
                        plotNo: '',
                        tpNo: '',
                        location: '',
                        lga: '',
                        district: '',
                        tracking_id: null,
                        file_name: this.fileName || '',
                        phone_no: this.phone_no || '',
                        address: this.address || ''
                    });
                }
                this.currentEntryIndex = 0;
            },

            updateLocationEntry(field, value) {
                if (this.locationEntries[this.currentEntryIndex]) {
                    this.locationEntries[this.currentEntryIndex][field] = value;
                    
                    // Sync main state if field matches
                    if (field === 'file_name') this.fileName = value;
                    if (field === 'phone_no') this.phone_no = value;
                    if (field === 'address') this.address = value;

                    if (this.applyLocationToAll && ['plotNo', 'tpNo', 'location', 'lga', 'district'].includes(field)) {
                        for (let i = 0; i < this.batchQuantity; i++) {
                            if (this.locationEntries[i]) {
                                this.locationEntries[i][field] = value;
                            }
                        }
                    }
                }
            },

            previousEntry() {
                if (this.currentEntryIndex > 0) {
                    this.saveCurrentApplicantToEntry();
                    this.currentEntryIndex--;
                    this.loadApplicantFromEntry();
                    this.$nextTick(() => this.loadLocationFieldsForEntry());
                }
            },

            nextEntry() {
                if (this.currentEntryIndex < this.batchQuantity - 1) {
                    this.saveCurrentApplicantToEntry();
                    this.currentEntryIndex++;
                    if (!this.locationEntries[this.currentEntryIndex]) {
                        this.locationEntries[this.currentEntryIndex] = {
                            plotNo: '',
                            tpNo: '',
                            location: '',
                            lga: '',
                            district: '',
                            tracking_id: null,
                            file_name: this.fileName || '',
                            phone_no: this.phone_no || '',
                            address: this.address || ''
                        };
                    } else {
                        this.loadApplicantFromEntry();
                    }
                    this.$nextTick(() => this.loadLocationFieldsForEntry());
                }
            },

            saveCurrentApplicantToEntry() {
                if (this.locationEntries[this.currentEntryIndex]) {
                    this.locationEntries[this.currentEntryIndex].file_name = this.fileName;
                    this.locationEntries[this.currentEntryIndex].phone_no = this.phone_no;
                    this.locationEntries[this.currentEntryIndex].address = this.address;
                }
            },

            loadApplicantFromEntry() {
                const entry = this.locationEntries[this.currentEntryIndex];
                if (entry) {
                    this.fileName = entry.file_name || '';
                    this.phone_no = entry.phone_no || '';
                    this.address = entry.address || '';
                }
            },

            isEntryFilled(index) {
                if (!this.locationEntries[index]) return false;
                const entry = this.locationEntries[index];
                return entry.plotNo || entry.tpNo || entry.location || entry.lga || entry.district;
            },

            updateBatchPreview() {
                if (!this.batchMode) return;

                // Adjust location entries array size when quantity changes
                // This must happen before any early returns to keep array in sync with batchQuantity
                const currentLength = this.locationEntries.length;
                if (this.batchQuantity > currentLength) {
                    // Add new entries
                    for (let i = currentLength; i < this.batchQuantity; i++) {
                        this.locationEntries.push({
                            plotNo: '',
                            tpNo: '',
                            location: '',
                            lga: '',
                            district: '',
                            tracking_id: null,
                            file_name: this.fileName || '',
                            phone_no: this.phone_no || '',
                            address: this.address || ''
                        });
                    }
                } else if (this.batchQuantity < currentLength) {
                    // Remove extra entries
                    this.locationEntries = this.locationEntries.slice(0, this.batchQuantity);
                    // Adjust current index if needed
                    if (this.currentEntryIndex >= this.batchQuantity) {
                        this.currentEntryIndex = this.batchQuantity - 1;
                    }
                }

                if (!this.serialNo || !(this.prefix || this.landUse) || !this.year) {
                    this.serialRangePreview = '-';
                    return;
                }

                // Calculate serial range (no zero-padding)
                const startSerial = parseInt(this.serialNo);
                const endSerial = startSerial + parseInt(this.batchQuantity) - 1;
                this.serialRangePreview = `${startSerial} to ${endSerial}`;

                // Update preview to show range
                const code = this.prefix || this.landUse;
                if (code && this.year && startSerial) {
                    if (this.batchQuantity > 1) {
                        this.preview = `${code}-${this.year}-${startSerial}-${endSerial}`;
                    } else {
                        this.preview = `${code}-${this.year}-${startSerial}`;
                    }
                }

                // Perform batch grouping lookup
                this.queueBatchGroupingLookup();
            },

            queueBatchGroupingLookup() {
                if (this.batchLookupHandle) {
                    clearTimeout(this.batchLookupHandle);
                }

                this.batchLookupHandle = setTimeout(() => {
                    this.performBatchGroupingLookup();
                }, 500);
            },

            async performBatchGroupingLookup() {
                if (!this.batchMode || !this.landUse || !this.year || !this.serialNo) return;

                const startSerial = parseInt(this.serialNo);
                const code = this.prefix || this.landUse;
                const fileNumbers = [];
                for (let i = 0; i < this.batchQuantity; i++) {
                    fileNumbers.push(`${code}-${this.year}-${startSerial + i}`);
                }

                try {
                    const response = await fetch("{{ route('api.grouping.bulk-lookup') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ file_numbers: fileNumbers })
                    });

                    const payload = await response.json();
                    if (payload.success && payload.data) {
                        // Create a map for fast lookup
                        const trackingMap = {};
                        payload.data.forEach(item => {
                            if (item.found && item.data && item.data.tracking_id) {
                                trackingMap[item.file_number] = item.data.tracking_id;
                            }
                        });

                        // Update location entries with found tracking IDs
                        this.locationEntries.forEach((entry, index) => {
                            const fileNo = fileNumbers[index];
                            if (trackingMap[fileNo]) {
                                entry.tracking_id = trackingMap[fileNo];
                            }
                        });

                        // Update summary display if only one tracking ID found for the first one
                        const firstTrackingId = trackingMap[fileNumbers[0]];
                        if (firstTrackingId) {
                            const displayEl = document.getElementById('trackingIdDisplay');
                            if (displayEl) displayEl.textContent = firstTrackingId;

                            const summaryTrackingEl = document.getElementById('summaryTrackingId');
                            if (summaryTrackingEl) summaryTrackingEl.textContent = firstTrackingId;

                            const inputEl = document.getElementById('trackingIdInput');
                            if (inputEl) inputEl.value = firstTrackingId;
                        }
                    }
                } catch (error) {
                    console.error('Batch grouping lookup failed:', error);
                }
            },

            // Apply current location details to all files in batch
            applyLocationToBatch() {
                if (!this.batchMode || this.batchQuantity < 2) {
                    return;
                }

                // Get current location details from the current entry
                const currentEntry = this.locationEntries[this.currentEntryIndex];
                if (!currentEntry) {
                    console.error('Apply to batch failed: current entry at index ' + this.currentEntryIndex + ' is undefined');
                    return;
                }

                // Rebuild the auto-location string from the current entry so it always
                // reflects the latest plot / district / LGA regardless of input timing.
                const builtLocation = [currentEntry.plotNo, currentEntry.district, currentEntry.lga]
                    .filter(v => v && v.toString().trim())
                    .join(', ')
                    .toUpperCase();

                const locationData = {
                    plotNo: currentEntry.plotNo || '',
                    tpNo: currentEntry.tpNo || '',
                    district: currentEntry.district || '',
                    lga: currentEntry.lga || '',
                    location: builtLocation
                };

                // Apply to all entries in the batch
                // Use the length of locationEntries instead of batchQuantity for safety
                const count = Math.min(this.batchQuantity, this.locationEntries.length);
                for (let i = 0; i < count; i++) {
                    if (this.locationEntries[i]) {
                        this.locationEntries[i] = {
                            ...this.locationEntries[i],
                            ...locationData
                        };
                    } else {
                        console.warn('Location entry at index ' + i + ' is missing during batch apply');
                    }
                }

                // Keep the top-level bound fields in sync with what was just applied
                this.district = locationData.district;
                this.lga = locationData.lga;
                this.location = builtLocation;

                // Show success notification
                Swal.fire({
                    icon: 'success',
                    title: 'Location Applied',
                    text: `Location details applied to all ${count} files in the batch`,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Refresh icons after the DOM updates
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            }
        }
    }

    // Commissioning Sheet Functions
    function openCommissioningSheetModal() {
        document.getElementById('commissioningSheetModal').classList.remove('hidden');
    }

    // Global function to be called from the modal or button
    window.openTemporaryFileSelector = function() {
        console.log('[FileNumberGenerator] openTemporaryFileSelector called');
        if (typeof GlobalFileNoModal !== 'undefined') {
            GlobalFileNoModal.open({
                callback: function (fileData) {
                    console.log('[FileNumberGenerator] Temporary file selector callback:', fileData);
                    if (fileData && fileData.fileNumber) {
                        // Clean the file number by removing trailing hyphens
                        let cleanedFileNumber = fileData.fileNumber.trim().replace(/-+$/, '');
                        console.log('[FileNumberGenerator] File number received:', fileData.fileNumber, '-> cleaned:', cleanedFileNumber);
                        // Find the Alpine controller on the generate modal
                        const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
                        if (modalEl) {
                            // Try Alpine v3 data access
                            try {
                                if (window.Alpine) {
                                    const data = window.Alpine.$data(modalEl);
                                    data.existingFileNo = cleanedFileNumber;
                                    
                                    // Extract prefix, year, and serial from the existing file number
                                    const parts = cleanedFileNumber.split('-');
                                    const yearIndex = parts.findIndex(p => /^\d{4}$/.test(p));
                                    if (yearIndex !== -1) {
                                        const extractedPrefix = parts.slice(0, yearIndex).join('-');
                                        const extractedYear = parseInt(parts[yearIndex]);
                                        const extractedSerial = parts.slice(yearIndex + 1).join('-');
                                        
                                        data.year = extractedYear;
                                        data.serialNo = extractedSerial;
                                        
                                        if (extractedPrefix) {
                                            const foundPrefix = data.allAllPrefixes.find(p => p.prefix === extractedPrefix);
                                            if (foundPrefix) {
                                                data.prefix = foundPrefix.prefix;
                                                data.handlePrefixChange();
                                            } else {
                                                const foundLu = data.landUses.find(l => {
                                                    const name = l.landuse.toUpperCase();
                                                    return (extractedPrefix === 'RES' && name.includes('RESIDENTIAL')) ||
                                                           (extractedPrefix === 'COM' && name.includes('COMMERCIAL')) ||
                                                           (extractedPrefix === 'IND' && name.includes('INDUSTRIAL')) ||
                                                           (extractedPrefix.startsWith('AG') && name.includes('AGRICULTURAL'));
                                                });
                                                if (foundLu) {
                                                    let code = '';
                                                    const name = foundLu.landuse.toUpperCase();
                                                    if (name.includes('RESIDENTIAL')) code = 'RES';
                                                    else if (name.includes('COMMERCIAL')) code = 'COM';
                                                    else if (name.includes('INDUSTRIAL')) code = 'IND';
                                                    else if (name.includes('AGRICULTURAL')) code = 'AG';
                                                    else code = name.substring(0, 3);
                                                    
                                                    data.landUse = code;
                                                    data.landUseId = foundLu.id;
                                                }
                                            }
                                        }
                                    }

                                    data.updatePreview();
                                    console.log('[FileNumberGenerator] Alpine data updated, existing file no:', data.existingFileNo, 'year:', data.year, 'serial:', data.serialNo);
                                } else {
                                    // Fallback if Alpine not global
                                    const displayInput = document.getElementById('displayTemporaryFileNo');
                                    if (displayInput) {
                                        displayInput.value = cleanedFileNumber;
                                        displayInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        displayInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            } catch (e) {
                                console.error('Error updating Alpine data:', e);
                            }
                        }
                        
                        // Important: Queue grouping lookup to fetch tracking ID for this file
                        console.log('[FileNumberGenerator] Calling queueGroupingLookup with:', cleanedFileNumber);
                        queueGroupingLookup(cleanedFileNumber);

                        // Auto-fill applicant details from the selected file
                        fetchTrackingIdForFile(cleanedFileNumber).then(() => {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'info',
                                    title: 'Details Auto-Filled',
                                    text: 'Details auto-filled based on selected file number. Please review before submitting.',
                                    showConfirmButton: false,
                                    timer: 5000,
                                    timerProgressBar: true
                                });
                            }
                        });
                    } else {
                        console.warn('[FileNumberGenerator] fileData.fileNumber is empty or undefined:', fileData);
                    }
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Global file number selector is not loaded.'
            });
        }
    };

    // Global function for Extension File Selector
    window.openExtensionFileSelector = function() {
        console.log('[FileNumberGenerator] openExtensionFileSelector called');
        if (typeof GlobalFileNoModal !== 'undefined') {
            GlobalFileNoModal.open({
                callback: function (fileData) {
                    console.log('[FileNumberGenerator] Extension file selector callback:', fileData);
                    if (fileData && fileData.fileNumber) {
                        // Clean the file number by removing trailing hyphens
                        let cleanedFileNumber = fileData.fileNumber.trim().replace(/-+$/, '');
                        console.log('[FileNumberGenerator] File number received:', fileData.fileNumber, '-> cleaned:', cleanedFileNumber);
                        // Find the Alpine controller on the generate modal
                        const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
                        if (modalEl) {
                            // Try Alpine v3 data access
                            try {
                                if (window.Alpine) {
                                    const data = window.Alpine.$data(modalEl);
                                    data.existingFileNo = cleanedFileNumber;
                                    data.updatePreview();
                                    console.log('[FileNumberGenerator] Alpine data updated, existing file no:', data.existingFileNo);
                                } else {
                                    // Fallback if Alpine not global
                                    const displayInput = document.getElementById('displayExtensionFileNo');
                                    if (displayInput) {
                                        displayInput.value = cleanedFileNumber;
                                        displayInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        displayInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            } catch (e) {
                                console.error('Error updating Alpine data:', e);
                            }
                        }
                        
                        // Important: Queue grouping lookup to fetch tracking ID for this file
                        console.log('[FileNumberGenerator] Calling queueGroupingLookup with:', cleanedFileNumber);
                        queueGroupingLookup(cleanedFileNumber);

                        // Auto-fill applicant details from the selected file
                        fetchTrackingIdForFile(cleanedFileNumber).then(() => {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'info',
                                    title: 'Details Auto-Filled',
                                    text: 'Details auto-filled based on selected file number. Please review before submitting.',
                                    showConfirmButton: false,
                                    timer: 5000,
                                    timerProgressBar: true
                                });
                            }
                        });
                    } else {
                        console.warn('[FileNumberGenerator] fileData.fileNumber is empty or undefined:', fileData);
                    }
                }
            });
        } else {
             Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'File selector module not loaded. Please refresh the page.',
                confirmButtonColor: '#ef4444'
            });
        }
    };

    // ----- Extension Type (File vs Plot) helpers -----

    function getGeneratorAlpineData() {
        const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
        if (modalEl && window.Alpine) {
            try { return window.Alpine.$data(modalEl); } catch (e) { /* noop */ }
        }
        if (modalEl && modalEl._x_dataStack && modalEl._x_dataStack[0]) {
            return modalEl._x_dataStack[0];
        }
        return null;
    }

    window.openExtensionTypeModal = function () {
        const modal = document.getElementById('extensionTypeModal');
        if (modal) {
            modal.classList.remove('hidden');
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        }
    };

    function closeExtensionTypeModal() {
        const modal = document.getElementById('extensionTypeModal');
        if (modal) modal.classList.add('hidden');
    }

    window.selectExtensionType = function (type) {
        const data = getGeneratorAlpineData();
        if (data) {
            data.extensionType = type;
            if (typeof data.updatePreview === 'function') data.updatePreview();
        }
        closeExtensionTypeModal();
        // Both kinds require picking an existing file — open the selector for convenience.
        if (typeof window.openExtensionFileSelector === 'function') {
            window.openExtensionFileSelector();
        }
    };

    // Cancelling the choice reverts the file type back to Normal.
    window.cancelExtensionTypeSelection = function () {
        closeExtensionTypeModal();
        const data = getGeneratorAlpineData();
        if (data) {
            data.fileOption = 'normal';
            data.extensionType = 'file';
            if (typeof data.updateFileOption === 'function') {
                data.updateFileOption();
            } else if (typeof data.updatePreview === 'function') {
                data.updatePreview();
            }
        }
    };

    // Plot Extension submission: keep the original file number, save an isolated transaction.
    function submitPlotExtension(event, alpineData) {
        const originalFileNo = (alpineData.existingFileNo || '').toString().trim();
        if (!originalFileNo) {
            Swal.fire({
                icon: 'warning',
                title: 'Existing File Required',
                text: 'Please select an existing file number for the Plot Extension.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        const fileNameValue = (alpineData.fileName || '').toString().trim();
        if (!fileNameValue) {
            Swal.fire({
                icon: 'warning',
                title: 'File Name Required',
                text: 'Please enter a File Name for the Plot Extension.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        const submitBtn = event.submitter || event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) showLoadingButton(submitBtn, originalText);
        showGlobalLoading('Saving Plot Extension...');

        const payload = {
            original_file_no: originalFileNo,
            tracking_id: (document.getElementById('trackingIdInput')?.value || '').trim(),
            file_name: fileNameValue,
            land_use: alpineData.landUse || '',
            purpose_id: alpineData.purpose || '',
            location: alpineData.location || '',
            lga: alpineData.lga || '',
            district: alpineData.district || '',
            plot_no: alpineData.plotNo || '',
            tp_no: alpineData.tpNo || '',
            customer_type: alpineData.customerType || '',
            phone_no: alpineData.phone_no || '',
            address: alpineData.address || '',
            // isInherited is set when the lookup auto-populated details for an indexed file
            is_indexed: alpineData.isInherited ? 1 : 0,
        };

        fetch('{{ route("mls-fileno.plot-extension.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                if (submitBtn) hideLoadingButton(submitBtn, originalText);

                if (data.success) {
                    if (typeof closeGenerateModal === 'function') closeGenerateModal();
                    try { table.ajax.reload(); } catch (e) { /* server-rendered fallback */ }
                    Swal.fire({
                        icon: 'success',
                        title: 'Plot Extension Saved',
                        text: data.message || ('Plot Extension saved for ' + originalFileNo + '.'),
                        confirmButtonColor: '#10b981'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Save Failed',
                        text: data.message || 'Could not save the Plot Extension.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(err => {
                hideGlobalLoading();
                if (submitBtn) hideLoadingButton(submitBtn, originalText);
                console.error('[PlotExtension] submit failed', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to save the Plot Extension. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Support function to fetch tracking ID and auto-populate fields
    async function fetchTrackingIdForFile(fileNumber) {
        if (!fileNumber) return;
        
        try {
            // Use the centralized lookup endpoint (escaped for JS/Blade context)
            const baseUrl = "{{ route('api.file-numbers.lookup') }}";
            const response = await fetch(`${baseUrl}?file_number=${encodeURIComponent(fileNumber)}`);
            const data = await response.json();
            
            if (data.success && data.data) {
                const match = data.data;
                
                // 1. Update Tracking ID UI (Legacy support)
                if (match && match.tracking_id) {
                    const displayEl = document.getElementById('trackingIdDisplay');
                    if (displayEl) displayEl.textContent = match.tracking_id;
                    
                    const inputEl = document.getElementById('trackingIdInput');
                    if (inputEl) inputEl.value = match.tracking_id;
                }

                // 2. Update Alpine Data (Preferred)
                const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
                if (modalEl && window.Alpine) {
                    const alpineData = window.Alpine.$data(modalEl);
                    
                    // Mark as inherited to trigger UI effects
                    alpineData.isInherited = true;

                    // Populate fields if they exist in match
                    if (match.file_name) alpineData.fileName = match.file_name;
                    if (match.location) alpineData.location = (match.location || '').toString().toUpperCase();
                    if (match.lga) alpineData.lga = match.lga;
                    if (match.district) alpineData.district = match.district;
                    if ((match.lga || match.district) && typeof alpineData.syncLocationSelects === 'function') {
                        alpineData.syncLocationSelects();
                    }
                    if (match.plot_no) alpineData.plotNo = match.plot_no;
                    if (match.tp_no) alpineData.tpNo = match.tp_no;
                    if (match.phone_no) alpineData.phone_no = match.phone_no;
                    if (match.address) alpineData.address = match.address;
                    if (match.rep_phone_no) alpineData.rep_phone_no = match.rep_phone_no;
                    if (match.rep_address) alpineData.rep_address = match.rep_address;

                    // Specialized handling for Land Use and Prefix
                    if (match.land_use || fileNumber) {
                        let luCode = '';
                        let matchedPrefix = null;

                        if (match.land_use) {
                            // 1. Success from API/DB join
                            const luName = match.land_use.toUpperCase();
                            if (luName.includes('RESIDENTIAL')) luCode = 'RES';
                            else if (luName.includes('COMMERCIAL')) luCode = 'COM';
                            else if (luName.includes('INDUSTRIAL')) luCode = 'IND';
                            else if (luName.includes('AGRICULTURAL')) luCode = 'AG';
                            else luCode = luName.substring(0, 3);
                        } else {
                            // 2. Fallback: Parse from file number string (e.g., "AG-RC-2026-32")
                            console.log('No land_use in data, attempting fallback from file number:', fileNumber);
                            const parts = fileNumber.split('-');
                            if (parts.length > 0) {
                                const prefixFound = parts[0]; // e.g., "AG" or "RES"
                                matchedPrefix = alpineData.allAllPrefixes.find(p => p.prefix === prefixFound || p.prefix === `CON-${prefixFound}`);
                                if (matchedPrefix) {
                                    luCode = prefixFound;
                                }
                            }
                        }

                        if (luCode) {
                            alpineData.landUse = luCode;
                            
                            // Try to find a matching prefix for the dropdown
                            if (!matchedPrefix) {
                                if (alpineData.applicationType === 'conversion') {
                                    matchedPrefix = alpineData.allAllPrefixes.find(p => p.prefix.includes('CON-') && p.prefix.includes(luCode));
                                } else {
                                    matchedPrefix = alpineData.allAllPrefixes.find(p => !p.prefix.includes('CON-') && p.prefix.includes(luCode));
                                }
                            }

                            if (matchedPrefix) {
                                alpineData.prefix = matchedPrefix.prefix;
                                // Fetch purposes for this land use ID to keep UI consistent
                                if (matchedPrefix.land_use_id) {
                                    alpineData.fetchDependentData(matchedPrefix.land_use_id);
                                }
                            }
                        }
                    }

                    alpineData.updatePreview();
                    console.log('Successfully auto-populated fields from file:', fileNumber);
                }
            }
        } catch (error) {
            console.error('Error fetching file details:', error);
        }
    }

    // Function to handle commissioning sheet from dropdown
    function openCommissioningSheetFromDropdown(button) {
        openCommissioningSheetForRowData(button);
    }

    // Function to handle commissioning sheet with row data
    function openCommissioningSheetForRowData(button) {
        try {
            // Get data from button attributes
            const mlsfNo = button.getAttribute('data-mlsf-no');
            const kangisNo = button.getAttribute('data-kangis-no');
            const newKangisNo = button.getAttribute('data-new-kangis-no');
            const fileName = button.getAttribute('data-file-name');
            const plotNo = button.getAttribute('data-plot-no');
            const tpNo = button.getAttribute('data-tp-no');
            const location = button.getAttribute('data-location');
            const lga = button.getAttribute('data-lga');
            const trackingId = button.getAttribute('data-tracking-id');

            console.log('Button data attributes:', {
                mlsfNo, kangisNo, newKangisNo, fileName, plotNo, tpNo, location, lga, trackingId
            });

            // Determine the best file number to use (prioritize mlsfNo, then kangisNo, then newKangisNo)
            let fileNumber = '';
            if (mlsfNo && mlsfNo !== 'N/A' && mlsfNo.trim() !== '') {
                fileNumber = mlsfNo;
            } else if (kangisNo && kangisNo !== 'N/A' && kangisNo.trim() !== '') {
                fileNumber = kangisNo;
            } else if (newKangisNo && newKangisNo !== 'N/A' && newKangisNo.trim() !== '') {
                fileNumber = newKangisNo;
            }

            // Clean up other fields (replace 'N/A' with empty string)
            const cleanFileName = (fileName && fileName !== 'N/A') ? fileName : '';
            const cleanPlotNo = (plotNo && plotNo !== 'N/A') ? plotNo : '';
            const cleanTpNo = (tpNo && tpNo !== 'N/A') ? tpNo : '';
            const cleanLocation = (location && location !== 'N/A') ? location : '';
            const cleanLga = (lga && lga !== 'N/A') ? lga : '';

            openCommissioningSheetForFile(fileNumber, cleanFileName, cleanPlotNo, cleanTpNo, cleanLocation, cleanLga, trackingId);

        } catch (error) {
            console.error('Error reading button data:', error);
            // Fallback - just open the modal without pre-filling
            openCommissioningSheetModal();
        }
    }

    function openCommissioningSheetForFile(fileNumber, fileName, plotNo, tpNo, location, lga, trackingId) {
        // Debug logging
        console.log('Opening commissioning sheet with data:', {
            fileNumber: fileNumber,
            fileName: fileName,
            plotNo: plotNo,
            tpNo: tpNo,
            location: location,
            lga: lga,
            trackingId: trackingId
        });

        // Set current time
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('cs_time_created').value = `${hours}:${minutes}`;

        // Open the modal
        document.getElementById('commissioningSheetModal').classList.remove('hidden');

        // Pre-fill         the form with fil           e data
        document.getElementById('cs_file_number').value = fileNumber || '';
        populateCommissioningSource(fileNumber);
        document.getElementById('cs_file_name').value = fileName || '';
        document.getElementById('cs_plot_number').value = plotNo || '';
        document.getElementById('cs_tp_number').value = tpNo || '';
        document.getElementById('cs_location').value = location || '';
        document.getElementById('cs_lga').value = lga || '';
        document.getElementById('cs_tracking_id').value = trackingId || '';

        // Set today's date
        document.getElementById('cs_date_created').value = new Date().toISOString().split('T')[0];

        // Set current user if available
        @if(Auth::check())
            document.getElementById('cs_created_by').value = '{{ Auth::user()->name }}';
        @endif

        // Set allottee field to same value as file name
        document.getElementById('cs_name_allottee').value = fileName || '';

        // Show data load status if any data was pre-filled
        const statusElement = document.getElementById('dataLoadStatus');
        if (fileNumber || fileName || plotNo || tpNo || location) {
            statusElement.classList.remove('hidden');
            setTimeout(() => {
                statusElement.classList.add('hidden');
            }, 5000); // Hide after 5 seconds
        }
    }

    function closeCommissioningSheetModal() {
        document.getElementById('commissioningSheetModal').classList.add('hidden');
        // Hide status message
        document.getElementById('dataLoadStatus').classList.add('hidden');
        // Reset form
        document.getElementById('commissioningSheetForm').reset();
        // Set today's date
        document.getElementById('cs_date_created').value = new Date().toISOString().split('T')[0];
    }

    function submitCommissioningSheet(event) {
        event.preventDefault();

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading on button
        showLoadingButton(submitBtn, originalText);

        const formData = new FormData(document.getElementById('commissioningSheetForm'));

        fetch('{{ route("commissioning-sheet.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                hideLoadingButton(submitBtn, originalText);

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Commissioning sheet saved successfully',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        closeCommissioningSheetModal();
                        // Reload the table to update the commissioning sheet status
                        if (typeof table !== 'undefined' && table.ajax) {
                            table.ajax.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to save commissioning sheet',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideLoadingButton(submitBtn, originalText);
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while saving the commissioning sheet',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    function generateAndPrintCommissioningSheet() {
        const form = document.getElementById('commissioningSheetForm');
        const formData = new FormData(form);
        // Validate required fields
        const fileNumber = formData.get('file_number');
        if (!fileNumber) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'File number is required',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // Show loading
        showGlobalLoading('Saving commissioning sheet...');

        // Save to DB first, then generate/print
        fetch('{{ route("commissioning-sheet.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Commissioning sheet saved. Generating PDF...',
                        showConfirmButton: false,
                        timer: 1000
                    });
                    // Now generate/print PDF
                    generateCommissioningSheetPDF(formData);
                    // Optionally reload table
                    if (typeof table !== 'undefined' && table.ajax) {
                        table.ajax.reload();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to save commissioning sheet',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while saving the commissioning sheet',
                });
            });
    }


    // Helper function to fetch images as base64 (shared or private)
    async function getImageBase64(url) {
        if (!url) return null;
        try {
            const response = await fetch(url, {
                mode: 'cors',
                headers: { 'Accept': 'image/*' }
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const blob = await response.blob();
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        } catch (error) {
            console.warn(`Failed to fetch image from ${url}:`, error);
            return null;
        }
    }

    async function generateCommissioningSheetPDF(formData, watermarkText = 'ORIGINAL') {
        try {
            showGlobalLoading('Generating PDF...');

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Fetch logos
            const logo1Base64 = await getImageBase64('/assets/logo/logo1.png') || await getImageBase64('/assets/logo/logo1.jpg') || await getImageBase64('/assets/logo/logoKlase.png');
            const logo2Base64 = await getImageBase64('/assets/logo/logo3.jpeg') || await getImageBase64('/assets/logo/las.jpeg') || await getImageBase64('/assets/logo/logo3.jpg');
            // LAS logo for footer
            const leftFooterLogoBase64 = await getImageBase64('/assets/logo/Klase.png');
            // Try PNG first, fallback to JPEG
            // Try local asset path for LAS logo
            let footerLogoBase64 = await getImageBase64('/assets/logo/las.png');
            if (!footerLogoBase64) {
                footerLogoBase64 = await getImageBase64('/assets/logo/las.jpg');
            }
            // Fallback to remote if local fails
            if (!footerLogoBase64) {
                footerLogoBase64 = await getImageBase64('http://app.klaes.ng/assets/logo/las.png');
            }
            if (!footerLogoBase64) {
                footerLogoBase64 = await getImageBase64('http://app.klaes.ng/assets/logo/las.jpg');
            }

            // Add Header
            if (logo1Base64) doc.addImage(logo1Base64, 'JPEG', 20, 12, 20, 20);
            if (logo2Base64) doc.addImage(logo2Base64, 'JPEG', 170, 12, 20, 20);

            // Detect temporary file
            const fileNumberVal = formData.get('file_number') || '';
            const isTemporaryFile = fileNumberVal.endsWith('(T)');

            doc.setFontSize(14);
            doc.setFont("helvetica", "bold");
            doc.text("MINISTRY OF LAND & PHYSICAL PLANNING", 105, 18, { align: "center" });
            doc.setFontSize(12);
            doc.text("DEPARTMENT OF LAND", 105, 26, { align: "center" });
            doc.setFontSize(11);
            if (isTemporaryFile) {
                doc.text("TEMPORARY FILE COMMISSIONING SHEET", 105, 36, { align: "center" });
            } else {
                doc.text("FILE COMMISSIONING SHEET", 105, 36, { align: "center" });
            }


            // Add Watermark
            doc.setTextColor(255, 0, 0);
            doc.setGState(doc.GState({ opacity: 0.2 }));
            doc.setFontSize(45);
            doc.text(watermarkText, 105, 148.5, { align: "center", angle: 45, baseline: 'middle' });
            doc.setGState(doc.GState({ opacity: 1.0 }));
            doc.setTextColor(0, 0, 0);

            // Time/Date/TrackingID Logic
            const trackingId = formData.get('tracking_id') || '';
            if (trackingId) {
                try {
                    const qrContainer = document.createElement('div');
                    new QRCode(qrContainer, { text: trackingId, width: 128, height: 128 });
                    const canvas = qrContainer.querySelector('canvas');
                    if (canvas) {
                        doc.addImage(canvas.toDataURL('image/png'), 'PNG', 92.5, 42, 25, 25);
                    }
                } catch (e) { console.warn('QR failed', e); }
            }

            // Body content
            doc.setFontSize(10);
            doc.setFont("helvetica", "normal");
            let y = 85;
            // SIT files carry a reason that should print directly after the Location.
            const isSitFile = String(fileNumberVal || '').toUpperCase().startsWith('SIT-');
            // Append the record's source after the File No, e.g. "CON-COM-2026-429 (Conversion)".
            const fileTypeLabel = getCommissioningSourceLabel(formData.get('source'), fileNumberVal);
            const fileNoDisplay = fileTypeLabel ? `${fileNumberVal} (${fileTypeLabel})` : fileNumberVal;
            const fields = [
                ['File No/(File Type):', fileNoDisplay],
                ['File Name:', formData.get('file_name')],
                ['Plot No:', formData.get('plot_number')],
                ['TP No:', formData.get('tp_number')],
                ['Location:', formData.get('location')],
                ...(isSitFile ? [['Reason:', formData.get('sit_reason')]] : []),
                ['Time Commissioned:', formatTimeToAMPM(formData.get('time_created'))],
                ['Date Commissioned:', formData.get('date_created')],
                ['Commissioned by:', formData.get('created_by')]
            ];

            const valueMaxWidth = 185 - 72; // width of the value column (line runs 70 -> 185, text starts at 72)
            const reasonLineHeight = 5;     // vertical spacing between wrapped reason lines (mm)

            fields.forEach(([label, value]) => {
                doc.setFont("helvetica", "bold");
                doc.text(label, 25, y);
                doc.setFont("helvetica", "normal");

                const text = String(value || '');

                // Long values (File No, File Name, SIT reason, Location) wrap within the value column and grow over as many lines as needed.
                if (label === 'File No/(File Type):' || label === 'File Name:' || label === 'Reason:' || label === 'Location:') {
                    const lines = doc.splitTextToSize(text, valueMaxWidth);
                    lines.forEach((ln, i) => {
                        doc.text(ln, 72, y + i * reasonLineHeight);
                    });
                    const lastY = y + (Math.max(lines.length, 1) - 1) * reasonLineHeight;
                    doc.line(70, lastY + 2, 185, lastY + 2);
                    y = lastY + 12;
                } else {
                    doc.text(text, 72, y);
                    doc.line(70, y + 2, 185, y + 2);
                    y += 12;
                }
            });

            // Signatures & Footer (line first, caption underneath)
            y += 15;
            doc.line(25, y, 75, y); doc.line(125, y, 175, y);
            doc.text("Created by Signature", 50, y + 6, { align: "center" });
            doc.text("Approved by Signature", 150, y + 6, { align: "center" });

            if (leftFooterLogoBase64) doc.addImage(leftFooterLogoBase64, 'JPEG', 15, 272, 18, 18);
            if (footerLogoBase64) doc.addImage(footerLogoBase64, 'JPEG', 165, 272, 28, 12);

            hideGlobalLoading();
            doc.save(`commissioning-sheet-${formData.get('file_number')}.pdf`);

        } catch (error) {
            hideGlobalLoading();
            console.error('PDF Error:', error);
            Swal.fire('Error', 'Failed to generate PDF: ' + error.message, 'error');
        }
    }


    async function generateBatchCommissioningSheetPDF(records, batchNo, watermarkText = 'ORIGINAL') {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Fetch logos once and reuse for all pages.
            const logo1Base64 = await getImageBase64('/assets/logo/logo1.png') || await getImageBase64('/assets/logo/logo1.jpg') || await getImageBase64('/assets/logo/logoKlase.png');
            const logo2Base64 = await getImageBase64('/assets/logo/logo3.jpeg') || await getImageBase64('/assets/logo/las.jpeg') || await getImageBase64('/assets/logo/logo3.jpg');
            const leftFooterLogoBase64 = await getImageBase64('/assets/logo/Klase.png');
            const footerLogoBase64 = await getImageBase64('/assets/logo/las.jpeg');

            const totalPages = records.length;

            for (let i = 0; i < records.length; i++) {
                if (i > 0) doc.addPage();

                const row = records[i] || {};

                // Detect temporary file for this record
                const rowFileNo = row.full_file_number || row.mlsf_no || row.file_number || '';
                const isTempFile = rowFileNo.endsWith('(T)');


                // Header logos
                if (logo1Base64) doc.addImage(logo1Base64, 'JPEG', 20, 12, 20, 20);
                if (logo2Base64) doc.addImage(logo2Base64, 'JPEG', 170, 12, 20, 20);

                // Header text
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.text('MINISTRY OF LAND & PHYSICAL PLANNING', 105, 18, { align: 'center' });
                doc.setFontSize(12);
                doc.text('DEPARTMENT OF LAND', 105, 26, { align: 'center' });
                doc.setFontSize(11);
                if (isTempFile) {
                    doc.text('TEMPORARY FILE COMMISSIONING SHEET', 105, 36, { align: 'center' });
                } else {
                    doc.text('FILE COMMISSIONING SHEET', 105, 36, { align: 'center' });
                }


                // Watermark
                doc.setTextColor(255, 0, 0);
                doc.setGState(doc.GState({ opacity: 0.2 }));
                doc.setFontSize(45);
                doc.text(watermarkText, 105, 148.5, { align: 'center', angle: 45, baseline: 'middle' });
                doc.setGState(doc.GState({ opacity: 1.0 }));
                doc.setTextColor(0, 0, 0);

                // QR per record
                const trackingId = row.tracking_id || '';
                if (trackingId) {
                    try {
                        const qrContainer = document.createElement('div');
                        new QRCode(qrContainer, { text: trackingId, width: 128, height: 128 });
                        const canvas = qrContainer.querySelector('canvas');
                        if (canvas) doc.addImage(canvas.toDataURL('image/png'), 'PNG', 92.5, 42, 25, 25);
                    } catch (e) {
                        console.warn('QR failed', e);
                    }
                }

                // Body fields for this row/page
                doc.setFontSize(10);
                let y = 85;
                const leftMargin = 25;
                const textStartX = 72;

                const createdAt = row.created_at ? new Date(row.created_at) : new Date();

                const isSitRow = String(rowFileNo || '').toUpperCase().startsWith('SIT-');
                // Append the record's source after the File No, e.g. "CON-COM-2026-429 (Conversion)".
                const rowFileTypeLabel = getCommissioningSourceLabel(row.source, rowFileNo);
                const rowFileNoDisplay = rowFileTypeLabel ? `${rowFileNo} (${rowFileTypeLabel})` : rowFileNo;
                const fields = [
                    ['File No/(File Type):', rowFileNoDisplay],
                    ['File Name:', row.file_name || ''],
                    ['Plot No:', row.plot_no || 'N/A'],
                    ['TP No:', row.tp_no || 'N/A'],
                    ['Location:', row.location || 'N/A'],
                    ...(isSitRow ? [['Reason:', row.sit_reason || '']] : []),
                    ['Time Commissioned:', createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })],
                    ['Date Commissioned:', createdAt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })],
                    ['Commissioned by:', row.created_by || '']
                ];

                const valueMaxWidth = 185 - textStartX; // width of the value column
                const reasonLineHeight = 5;             // vertical spacing between wrapped reason lines (mm)

                fields.forEach(([label, value]) => {
                    doc.setFont('helvetica', 'bold');
                    doc.text(label, leftMargin, y);
                    doc.setFont('helvetica', 'normal');

                    const text = String(value || '');

                    // Long values (File No, File Name, SIT reason, Location) wrap within the value column and grow over as many lines as needed.
                    if (label === 'File No/(File Type):' || label === 'File Name:' || label === 'Reason:' || label === 'Location:') {
                        const lines = doc.splitTextToSize(text, valueMaxWidth);
                        lines.forEach((ln, idx) => {
                            doc.text(ln, textStartX, y + idx * reasonLineHeight);
                        });
                        const lastY = y + (Math.max(lines.length, 1) - 1) * reasonLineHeight;
                        doc.line(70, lastY + 2, 185, lastY + 2);
                        y = lastY + 12;
                    } else {
                        doc.text(text, textStartX, y);
                        doc.line(70, y + 2, 185, y + 2);
                        y += 12;
                    }
                });

                // Signatures (line first, caption underneath)
                y += 15;
                doc.line(25, y, 75, y);
                doc.line(125, y, 175, y);
                doc.text('Created by Signature', 50, y + 6, { align: 'center' });
                doc.text('Approved by Signature', 150, y + 6, { align: 'center' });

                // Footer
                doc.setFontSize(8);
                doc.text(`Generated on ${new Date().toLocaleString()} | Batch: ${batchNo || 'N/A'} | Page ${i + 1} of ${totalPages}`, 105, 270, { align: 'center' });
                if (leftFooterLogoBase64) doc.addImage(leftFooterLogoBase64, 'JPEG', 15, 272, 18, 18);
                if (footerLogoBase64) doc.addImage(footerLogoBase64, 'JPEG', 150, 272, 45, 18);
            }

            hideGlobalLoading();
            doc.save(`batch-commissioning-sheet-${batchNo}.pdf`);

            Swal.fire({
                icon: 'success',
                title: 'Processed',
                html: `Batch commissioning sheet generated successfully.<br><strong>${records.length}</strong> page${records.length === 1 ? '' : 's'} for batch <strong>${batchNo}</strong>.`,
                confirmButtonColor: '#10b981'
            });
        } catch (error) {
            hideGlobalLoading();
            console.error('Error generating batch PDF:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to generate batch PDF: ' + error.message,
                confirmButtonColor: '#ef4444'
            });
        }
    }

    // Generate Batch PDF for all records in a batch (or date)
    async function generateBatchPDF(identifier, status = 'Original', scope = 'batch') {
        try {
            if (!identifier) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Identifier Required',
                    text: 'Please select a valid batch or date first.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // Show loading with info
            showGlobalLoading('Fetching records...');

            // Fetch all records
            const requestBody = scope === 'date' 
                ? { scope: 'date', date: identifier }
                : { batch_no: identifier };

            const response = await fetch('{{ route("mls-fileno.batch-records") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ ...requestBody, include_printed: (scope !== 'date') })
            });

            const result = await response.json();

            if (!result.success || !result.data || result.data.length === 0) {
                hideGlobalLoading();
                Swal.fire({
                    icon: 'error',
                    title: 'No Records Found',
                    text: result.message || 'No records found for this ' + scope,
                    confirmButtonColor: '#ef4444'
                });
                return;
            }

            const records = result.data;
            const count = result.count;

            // Show notification about generating PDF
            Swal.fire({
                icon: 'info',
                title: 'Processing',
                html: `Total Number Batch for Commissioning Sheet is processing for ${scope} <strong>${identifier}</strong>`,
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });

            // Wait a moment for the notification to be visible
            await new Promise(resolve => setTimeout(resolve, 500));

            showGlobalLoading(`Generating summary PDF with ${count} records...`);

            // Determine watermark text based on status
            const watermarkText = (status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';

            // Call the dedicated PDF generation function
            await generateBatchCommissioningSheetPDF(records, identifier, watermarkText);

        } catch (error) {
            hideGlobalLoading();
            console.error('Error generating batch PDF:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to generate batch PDF: ' + error.message,
                confirmButtonColor: '#ef4444'
            });
        }
    }

    // Generate one consolidated commissioning sheet for all files commissioned in the last 24 hours.
    async function generateDaily24hPDF(status = 'Original') {
        try {
            showGlobalLoading('Fetching all commissioned files for the last 24 hours...');

            const response = await fetch('{{ route("mls-fileno.batch-records") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ scope: 'daily_24h' })
            });

            const result = await response.json();

            if (!result.success || !result.data || result.data.length === 0) {
                hideGlobalLoading();
                Swal.fire({
                    icon: 'warning',
                    title: 'No Records in Last 24 Hours',
                    text: result.message || 'No commissioned files found in the last 24 hours.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            const records = result.data;
            const watermarkText = (status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';
            const now = new Date();
            const label = `DAILY-24H-${now.toISOString().slice(0, 10)}`;

            await generateBatchCommissioningSheetPDF(records, label, watermarkText);
        } catch (error) {
            hideGlobalLoading();
            console.error('Error generating 24-hour batch PDF:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to generate last-24-hours commissioning sheet: ' + error.message,
                confirmButtonColor: '#ef4444'
            });
        }
    }

    // Dropdown Functions
    function toggleDropdown(button) {
        // Close all other dropdowns first
        closeAllDropdowns();

        // Toggle the clicked dropdown
        const dropdown = button.nextElementSibling;
        if (dropdown && dropdown.classList.contains('dropdown-menu')) {
            dropdown.classList.toggle('hidden');
        }
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
    }



    // View existing commissioning sheet
    function viewCommissioningSheet(commissioningSheetId) {
        if (!commissioningSheetId) {
            alert('Commissioning sheet ID not found');
            return;
        }

        // Show loading
        showGlobalLoading('Loading commissioning sheet...');

        // Fetch the commissioning sheet data
        fetch(`/commissioning-sheet/${commissioningSheetId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                hideGlobalLoading();

                if (data.success && data.data) {
                    // Convert the data object to FormData format expected by generateCommissioningSheetPDF
                    const formData = new FormData();

                    // Map the response data to FormData
                    const responseData = data.data;
                    formData.append('file_number', responseData.file_number || '');
                    formData.append('file_name', responseData.file_name || '');
                    formData.append('name_or_allottee', responseData.name_or_allottee || '');
                    formData.append('plot_number', responseData.plot_number || '');
                    formData.append('tp_number', responseData.tp_number || '');
                    formData.append('location', responseData.location || '');
                    formData.append('sit_reason', responseData.sit_reason || '');
                    formData.append('date_created', responseData.date_created || responseData.created_at || '');
                    formData.append('created_by', responseData.created_by || '');

                    // Extract and append time from created_at if not explicitly provided
                    if (responseData.created_at) {
                        const date = new Date(responseData.created_at);
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        formData.append('time_created', `${hours}:${minutes}`);
                    }

                    // Generate PDF with the FormData
                    generateCommissioningSheetPDF(formData);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error loading commissioning sheet',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error loading commissioning sheet data',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.relative')) {
            closeAllDropdowns();
        }
    });

    // Add event listeners for form inputs
    document.addEventListener('DOMContentLoaded', function () {
        // Load serial status when page loads
        if (document.getElementById('content-serial-init')) {
            loadSerialStatus();
        }

        // Initiali    ze Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // ==================== TAB MANAGEMENT ====================

    /**
       * Switch between tabs
     */
    window.switchTab = function (tabName) {
        // Update tab buttons
        const tabs = ['generator', 'serial-init', 'consolidation'];
        tabs.forEach(tab => {
            const button = document.getElementById(`tab-${tab}`);
            const content = document.getElementById(`content-${tab}`);

            if (!button || !content) return;

            if (tab === tabName) {
                button.classList.add('active');
                button.classList.remove('border-transparent', 'text-gray-500');
                button.classList.add('border-blue-600', 'text-blue-600');
                content.classList.remove('hidden');
            } else {
                button.classList.remove('active');
                button.classList.remove('border-blue-600', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
                content.classList.add('hidden');
            }
        });

        // Refresh icons after DOM changes
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Load serial status if switching to serial-init tab
        if (tabName === 'serial-init') {
            loadSerialStatus();
        }
    };

    // ==================== SERIAL INITIALIZATION ====================

    /**
     * Load serial control status from server
     */
    window.loadSerialStatus = function () {
        const tableBody = document.getElementById('serialTableBody');
        if (!tableBody) return;

        // Show loading state
        tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        <i data-lucide="loader" class="w-6 h-6 inline-block animate-spin"></i>
                        <p class="mt-2">Loading serial control data...</p>
                    </td>
                </tr>
            `;

        fetch('{{ route("mls-fileno.serial-status") }}')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch serial status');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    renderSerialTable(data.data);
                } else {
                    throw new Error(data.message || 'Invalid response format');
                }
            })
            .catch(error => {
                console.error('Error loading serial status:', error);
                tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-red-500">
                                <i data-lucide="alert-circle" class="w-6 h-6 inline-block"></i>
                                <p class="mt-2">Error loading data: ${error.message}</p>
                                <button onclick="loadSerialStatus()" class="mt-4 text-blue-600 hover:text-blue-700">
                                    Try Again
                                </button>
                            </td>
                        </tr>
                    `;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
    };

    /**
     * Render serial control table
     */
    function renderSerialTable(data) {
        const tableBody = document.getElementById('serialTableBody');
        if (!tableBody) return;

        if (!data || data.length === 0) {
            tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No serial control data found
                        </td>
                    </tr>
                `;
            return;
        }

        let html = '';
        data.forEach(item => {
            const statusBadge = getStatusBadge(item);
            const actionButton = getActionButton(item);
            const initializedAt = item.initialized_at
                ? new Date(item.initialized_at).toLocaleDateString()
                : '-';

            html += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-medium text-gray-900">${item.land_use}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">${item.year}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input 
                                type="number" 
                                id="serial-${item.land_use}" 
                                value="${item.last_serial}" 
                                ${item.is_locked ? 'disabled readonly' : ''}
                                class="w-24 px-2 py-1 border rounded ${item.is_locked ? 'bg-gray-100 text-gray-500' : 'border-gray-300'}"
                                min="0"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">${statusBadge}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${item.initialized_by || '-'}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${initializedAt}</td>
                        <td class="px-4 py-3 whitespace-nowrap">${actionButton}</td>
                    </tr>
                `;
        });

        tableBody.innerHTML = html;

        // Refresh icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Get status badge HTML
     */
    function getStatusBadge(item) {
        if (item.is_locked) {
            return `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <i data-lucide="lock" class="w-3 h-3 mr-1"></i>
                        Locked
                    </span>
                `;
        } else if (item.is_initialized) {
            return `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                        Initialized
                    </span>
                `;
        } else {
            return `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <i data-lucide="minus-circle" class="w-3 h-3 mr-1"></i>
                        Not Initialized
                    </span>
                `;
        }
    }

    /**
     * Get action button HTML
     */
    function getActionButton(item) {
        if (item.is_locked) {
            return `
                    <div class="flex items-center space-x-2 text-gray-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                        <span class="text-xs">Locked</span>
                    </div>
                `;
        } else {
            return `
                    <button 
                        onclick="initializeSerial('${item.land_use}', ${item.year})"
                        class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center space-x-1">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        <span>Initialize</span>
                    </button>
                `;
        }
    }

    /**
     * Initialize serial number for a land use
     */
    window.initializeSerial = function (landUse, year) {
        const inputElement = document.getElementById(`serial-${landUse}`);
        if (!inputElement) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Could not find serial input field'
            });
            return;
        }

        const lastSerial = parseInt(inputElement.value);

        if (isNaN(lastSerial) || lastSerial < 0) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Value',
                text: 'Please enter a valid serial number (0 or greater)'
            });
            return;
        }

        // Show confirmation dialog with warning
        Swal.fire({
            title: 'Initialize Serial Number?',
            html: `
                    <div class="text-left">
                        <p class="mb-4">You are about to initialize the serial number for:</p>
                        <ul class="list-disc list-inside mb-4 text-gray-700">
                            <li><strong>Land Use:</strong> ${landUse}</li>
                            <li><strong>Year:</strong> ${year}</li>
                            <li><strong>Last Serial:</strong> ${lastSerial}</li>
                        </ul>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
                            <p class="text-sm text-yellow-700">
                                <strong>⚠️ Warning:</strong> This action cannot be undone!
                                Once locked, the serial number cannot be modified through the UI.
                            </p>
                        </div>
                        <p class="text-sm">The next generated file number will be: <strong>${landUse}-${year}-${lastSerial + 1}</strong></p>
                    </div>
                `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Initialize & Lock',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            customClass: {
                popup: 'text-left'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                performInitialization(landUse, year, lastSerial);
            }
        });
    };

    /**
     * Perform the actual initialization
     */
    function performInitialization(landUse, year, lastSerial) {
        // Show loading
        Swal.fire({
            title: 'Initializing...',
            text: 'Please wait while we initialize the serial number',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("mls-fileno.initialize-serial") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                land_use: landUse,
                year: year,
                last_serial: lastSerial
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Serial number initialized and locked successfully',
                        confirmButtonColor: '#10b981'
                    });

                    // Refresh the table
                    loadSerialStatus();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: data.message || 'Could not initialize serial number'
                    });
                }
            })
            .catch(error => {
                console.error('Error initializing serial:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while initializing the serial number'
                });
            });
    }

    /**
     * Refresh serial table
     */
    window.refreshSerialTable = function () {
        loadSerialStatus();
    };

    window.generateConversionApplication = function (id, fileNo) {
        Swal.fire({
            title: 'Property Acquisition Method',
            html: `
                <div class="text-left space-y-3 p-2">
                    <p class="text-sm text-gray-600 mb-4">Please select how the property for <strong>${fileNo}</strong> was acquired:</p>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="a" class="w-4 h-4 text-blue-600" checked>
                            <span class="text-sm font-medium text-gray-700">a. By Purchase</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="b" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">b. By Inheritance</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="c" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">c. By Gift</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="d" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">d. Direct Local Government Allocation</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="e" class="w-4 h-4 text-blue-600" id="method_other_radio">
                            <span class="text-sm font-medium text-gray-700">e. Any other (Specify)</span>
                        </label>
                    </div>
                    <div id="other_specify_container" class="mt-3 hidden animate-fadeIn">
                        <input type="text" id="other_specify_input" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" 
                               placeholder="Please specify other method...">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generate Document',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const radios = document.querySelectorAll('input[name="acquisition_method"]');
                const otherContainer = document.getElementById('other_specify_container');
                const otherInput = document.getElementById('other_specify_input');

                radios.forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        if (e.target.value === 'e') {
                            otherContainer.classList.remove('hidden');
                            otherInput.focus();
                        } else {
                            otherContainer.classList.add('hidden');
                        }
                    });
                });
            },
            preConfirm: () => {
                const selectedMethod = document.querySelector('input[name="acquisition_method"]:checked').value;
                const otherValue = document.getElementById('other_specify_input').value;

                if (selectedMethod === 'e' && !otherValue.trim()) {
                    Swal.showValidationMessage('Please specify the other acquisition method');
                    return false;
                }

                return { method: selectedMethod, other: otherValue };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { method, other } = result.value;
                // Prefer the file number string over the numeric id so the backend can
                // resolve by fileNumber.mlsfNo and fall back to plot_extensions. This also
                // avoids loading a different row that shares the same numeric id.
                const convKey = (fileNo && fileNo !== '--' && fileNo !== 'N/A')
                    ? encodeURIComponent(fileNo)
                    : id;
                let url = `/file-numbers/conversion-application/${convKey}?method=${method}`;
                if (method === 'e' && other) {
                    url += `&other=${encodeURIComponent(other)}`;
                }
                // Open in new tab
                window.open(url, '_blank');
            }
        });
    }

    window.generateBatchConversionApplication = function (batchNo) {
        Swal.fire({
            title: 'Batch Property Acquisition Method',
            html: `
                <div class="text-left space-y-3 p-2">
                    <p class="text-sm text-gray-600 mb-4">Please select how the properties in batch <strong>${batchNo}</strong> were acquired:</p>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="a" class="w-4 h-4 text-blue-600" checked>
                            <span class="text-sm font-medium text-gray-700">a. By Purchase</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="b" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">b. By Inheritance</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="c" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">c. By Gift</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="d" class="w-4 h-4 text-blue-600">
                            <span class="text-sm font-medium text-gray-700">d. Director Local Government Allocation</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="batch_acquisition_method" value="e" class="w-4 h-4 text-blue-600" id="batch_method_other_radio">
                            <span class="text-sm font-medium text-gray-700">e. Any other (Specify)</span>
                        </label>
                    </div>
                    <div id="batch_other_specify_container" class="mt-3 hidden animate-fadeIn">
                        <input type="text" id="batch_other_specify_input" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" 
                               placeholder="Please specify other method...">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generate Batch Document',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const radios = document.querySelectorAll('input[name="batch_acquisition_method"]');
                const otherContainer = document.getElementById('batch_other_specify_container');
                const otherInput = document.getElementById('batch_other_specify_input');

                radios.forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        if (e.target.value === 'e') {
                            otherContainer.classList.remove('hidden');
                            otherInput.focus();
                        } else {
                            otherContainer.classList.add('hidden');
                        }
                    });
                });
            },
            preConfirm: () => {
                const selectedMethod = document.querySelector('input[name="batch_acquisition_method"]:checked').value;
                const otherValue = document.getElementById('batch_other_specify_input').value;

                if (selectedMethod === 'e' && !otherValue.trim()) {
                    Swal.showValidationMessage('Please specify the other acquisition method');
                    return false;
                }

                return { method: selectedMethod, other: otherValue };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { method, other } = result.value;

                // Show processing notice
                Swal.fire({
                    icon: 'info',
                    title: 'Processing',
                    text: 'Total Number Batch for Conversion Application is processing...',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    didClose: () => {
                        let url = `{{ route("file-numbers.batch-conversion-application", ":batchNo") }}`.replace(':batchNo', batchNo) + `?method=${method}`;
                        if (method === 'e' && other) {
                            url += `&other=${encodeURIComponent(other)}`;
                        }
                        // Open in new tab
                        window.open(url, '_blank');

                        // Show success notice
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Processed',
                                text: 'Total Number Batch for Conversion Application processed!',
                                confirmButtonColor: '#10b981'
                            });
                        }, 500);
                    }
                });
            }
        });
    }

    // Initialize Action Dropdowns with Smart Positioning to prevent clipping
    document.addEventListener('click', function (e) {
        // Toggle Dropdown
        const button = e.target.closest('.action-dropdown button');
        if (button) {
            e.stopPropagation();
            const dropdown = button.nextElementSibling;

            // Close all other dropdowns
            document.querySelectorAll('.action-dropdown-menu').forEach(d => {
                if (d !== dropdown) {
                    d.classList.add('hidden');
                    d.classList.remove('show');
                }
            });

            if (dropdown && dropdown.classList.contains('action-dropdown-menu')) {
                const isHidden = dropdown.classList.contains('hidden') || getComputedStyle(dropdown).display === 'none';

                if (isHidden) {
                    // Show first to measure
                    dropdown.classList.remove('hidden');
                    dropdown.classList.add('show');

                    // Fixed Positioning Logic
                    const rect = button.getBoundingClientRect();
                    const dropdownRect = dropdown.getBoundingClientRect();
                    const dropdownWidth = dropdownRect.width || 220;
                    const dropdownHeight = dropdownRect.height || 200;
                    const windowWidth = window.innerWidth;
                    const windowHeight = window.innerHeight;

                    dropdown.style.position = 'fixed';
                    dropdown.style.zIndex = '9999';

                    // Horizontal Positioning
                    // Default to aligning left edge with button left edge
                    let leftPos = rect.left;

                    // Check right overflow: if it goes off screen, align right edge to window right - margin
                    if (leftPos + dropdownWidth > windowWidth - 10) {
                        leftPos = windowWidth - dropdownWidth - 10;
                    }

                    // Don't go off screen left
                    if (leftPos < 10) leftPos = 10;

                    dropdown.style.left = `${leftPos}px`;
                    dropdown.style.right = 'auto'; // clear right

                    // Vertical Positioning
                    const spaceBelow = windowHeight - rect.bottom;
                    const spaceAbove = rect.top;

                    // Smart Decision:
                    // 1. If not enough space below, try above.
                    // 2. OR if we are in the bottom 40% of screen, prefer above (better UX).
                    // 3. Ensure we have enough space above before flipping.

                    const notEnoughBelow = spaceBelow < dropdownHeight;
                    const inBottomZone = rect.top > (windowHeight * 0.6);
                    const hasSpaceAbove = spaceAbove > dropdownHeight;

                    if ((notEnoughBelow || inBottomZone) && hasSpaceAbove) {
                        // Show Above
                        dropdown.style.top = 'auto';
                        dropdown.style.bottom = `${windowHeight - rect.top + 2}px`; // 2px margin above button
                    } else {
                        // Show Below (Default)
                        dropdown.style.top = `${rect.bottom + 2}px`; // 2px margin below button
                        dropdown.style.bottom = 'auto';
                    }

                } else {
                    closeActionDropdown(dropdown);
                }
            }
            return;
        }

        // Close when clicking outside
        if (!e.target.closest('.action-dropdown-menu')) {
            document.querySelectorAll('.action-dropdown-menu').forEach(closeActionDropdown);
        }
    });

    // Hide and clear the inline fixed-position styles, so the menu falls back to its
    // stylesheet position instead of reopening at stale coordinates.
    function closeActionDropdown(dropdown) {
        dropdown.classList.add('hidden');
        dropdown.classList.remove('show');
        dropdown.style.position = '';
        dropdown.style.left = '';
        dropdown.style.right = '';
        dropdown.style.top = '';
        dropdown.style.bottom = '';
        dropdown.style.zIndex = '';
    }

    // Inline coordinates are computed for one viewport size; close on resize/orientation
    // change rather than leaving the menu stranded.
    window.addEventListener('resize', function () {
        document.querySelectorAll('.action-dropdown-menu.show').forEach(closeActionDropdown);
    });


    // Close dropdowns on scroll to keep fixed position valid
    window.addEventListener('scroll', function () {
        document.querySelectorAll('.action-dropdown-menu.show').forEach(closeActionDropdown);
    }, true);

    // Printer Manager Logic
    let pmState = {
        id: null,
        batchNo: null,
        selectedBatchNo: null,
        fileNo: null, // We need to fetch this or pass it
        mode: 'Individual', // Individual, Batch or Daily24h
        docType: 'Commissioning Sheet',
        allowed: false
    };
    const DAILY_PRINT_REFERENCE = 'MLS-DAILY-24H';

    function openFilePrinterManager(id, arg2, arg3, forceSingle = false) {
        // Ensure Generator Modal is closed to prevent conflicts
        if (typeof closeGenerateModal === 'function') {
            closeGenerateModal();
        }

        const modal = document.getElementById('printerManagerModal');
        
        if (modal) {
            // TELEPORT: Move modal to body to break out of any stacking contexts
            if (modal.parentNode !== document.body) {
                document.body.appendChild(modal);
            }

            modal.classList.remove('hidden');
            
            // Ultra-aggressive styling to ensure it's on top of Batch Details
            modal.style.setProperty('display', 'block', 'important');
            modal.style.setProperty('z-index', '1000010', 'important'); 
            modal.style.setProperty('position', 'fixed', 'important');
            modal.style.setProperty('top', '0', 'important');
            modal.style.setProperty('left', '0', 'important');
            modal.style.setProperty('width', '100%', 'important');
            modal.style.setProperty('height', '100%', 'important');
            
            // Ensure icons are rendered
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } else {
            console.error('Printer Manager Modal element NOT found!');
            return;
        }

        try {
            pmState.id = id;

            // Handle variable arguments to support both JS-render (3 args) and PHP-render (2 args)
            if (arg3 !== undefined) {
                // Called as (id, fileNo, batchNo) from JS DataTables render
                pmState.fileNo = arg2;
                pmState.batchNo = arg3;
            } else {
                // Called as (id, batchNo) from Controller (Capture Existing)
                pmState.batchNo = arg2;

                // Try to lookup fileNo from DataTables if available, or default to placeholder
                try {
                    const table = $('#mlsfTable').DataTable();
                    const rowData = table.row((idx, data) => data.id == id).data();
                    pmState.fileNo = rowData ? rowData.mlsfNo : '--';
                } catch (e) {
                    console.warn('Could not lookup file number for Printer Manager', e);
                    pmState.fileNo = '--';
                }
            }

            console.log('PM State initialized:', pmState);

            const batchNo = pmState.batchNo;

            // Set Display Texts
            const refDisplay = document.getElementById('pmRefDisplay');
            const batchDisplay = document.getElementById('pmBatchDisplay');
            if (refDisplay) refDisplay.innerText = pmState.fileNo || '--';
            if (batchDisplay) batchDisplay.innerText = batchNo || 'N/A';

            pmState.selectedBatchNo = batchNo || null;

            loadPrintableBatches(pmState.selectedBatchNo);

            syncDocTypeForMode();

            // Toggle visibility of batch info
            const batchContainer = document.getElementById('pmBatchContainer');
            if (batchContainer) {
                if (batchNo && batchNo !== 'null' && batchNo !== 'undefined') {
                    batchContainer.classList.remove('hidden');
                } else {
                    batchContainer.classList.add('hidden');
                }
            }

            const batchBtn = document.getElementById('pmModeBatch');
            if (batchBtn) {
                const shouldDisableBatch = !!forceSingle;
                batchBtn.disabled = shouldDisableBatch;

                if (shouldDisableBatch) {
                    batchBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    batchBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            // Reset to default - force Individual if forced or no batchNo, else prefer Batch if avail
            if (forceSingle) {
                setPrintMode('Individual');
            } else {
                setPrintMode(batchNo ? 'Batch' : 'Individual');
            }

            // Sync print status from server
            checkPrintStatus();
        } catch (error) {
            console.error('Error initializing Printer Manager:', error);
        }
    }


    function syncDocTypeForMode() {
        const docTypeSelect = document.getElementById('pmDocType');
        if (!docTypeSelect) return;

        const conversionOption = docTypeSelect.querySelector('option[value="Application for Conversion"]');
        if (!conversionOption) return;

        const isConversionFile = pmState.fileNo && (
            String(pmState.fileNo).trim().toUpperCase().startsWith('CON-')
        );

        const allowConversion = pmState.mode !== 'Daily24h' && isConversionFile;

        if (allowConversion) {
            conversionOption.hidden = false;
            conversionOption.disabled = false;
            conversionOption.style.display = '';
        } else {
            conversionOption.hidden = true;
            conversionOption.disabled = true;
            conversionOption.style.display = 'none';
            if (docTypeSelect.value === 'Application for Conversion') {
                docTypeSelect.value = 'Commissioning Sheet';
            }
        }
    }

    function onBatchSelectionChange() {
        const selector = document.getElementById('pmBatchSelect');
        if (!selector) return;

        pmState.selectedBatchNo = selector.value || null;

        const batchDisplay = document.getElementById('pmBatchDisplay');
        if (batchDisplay) {
            batchDisplay.innerText = pmState.selectedBatchNo || 'N/A';
        }

        checkPrintStatus();
    }

    async function loadPrintableBatches(preselectBatchNo = null) {
        const selector = document.getElementById('pmBatchSelect');
        if (!selector) return;

        try {
            selector.innerHTML = '<option value="">Loading unprinted batches...</option>';

            const response = await fetch('{{ route("mls-fileno.printable-batches") }}');
            const payload = await response.json();

            if (!payload.success) {
                throw new Error(payload.message || 'Failed to load printable batches');
            }

            const options = ['<option value="">Select an unprinted batch...</option>'];
            (payload.data || []).forEach((row) => {
                const batchNo = row.batch_no || '';
                if (!batchNo) return;
                const count = Number(row.total_records || 0);
                options.push(`<option value="${batchNo}">${batchNo} (${count} file${count === 1 ? '' : 's'})</option>`);
            });

            selector.innerHTML = options.join('');

            const preferred = preselectBatchNo || pmState.selectedBatchNo;
            if (preferred && selector.querySelector(`option[value="${preferred}"]`)) {
                selector.value = preferred;
                pmState.selectedBatchNo = preferred;
            } else {
                pmState.selectedBatchNo = selector.value || null;
            }

            const batchDisplay = document.getElementById('pmBatchDisplay');
            if (batchDisplay) {
                batchDisplay.innerText = pmState.selectedBatchNo || 'N/A';
            }
        } catch (error) {
            console.error('Failed to load printable batches', error);
            selector.innerHTML = '<option value="">Unable to load batch list</option>';
            pmState.selectedBatchNo = null;
        }
    }

    function closePrinterManager() {
        console.log('Closing Printer Manager...');
        const modal = document.getElementById('printerManagerModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        
        // Reset pmState
        pmState = {
            id: null,
            batchNo: null,
            selectedBatchNo: null,
            fileNo: null,
            mode: 'Individual',
            docType: 'Commissioning Sheet',
            allowed: false
        };
    }

    function setPrintMode(mode) {
        pmState.mode = mode;

        // UI Update
        const indBtn = document.getElementById('pmModeIndividual');
        const batchBtn = document.getElementById('pmModeBatch');
        const dailyBtn = document.getElementById('pmModeDaily24h');

        [indBtn, batchBtn, dailyBtn].forEach((btn) => {
            if (!btn) return;
            btn.classList.remove('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        });

        if (mode === 'Individual' && indBtn) {
            indBtn.classList.add('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        } else if (mode === 'Batch' && batchBtn) {
            batchBtn.classList.add('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        } else if (mode === 'Daily24h' && dailyBtn) {
            dailyBtn.classList.add('bg-gray-100', 'ring-2', 'ring-blue-700', 'text-blue-700', 'z-10');
        }

        const refDisplay = document.getElementById('pmRefDisplay');
        const batchContainer = document.getElementById('pmBatchContainer');
        const batchSelectorContainer = document.getElementById('pmBatchSelectorContainer');
        if (mode === 'Daily24h') {
            if (refDisplay) refDisplay.innerText = 'All Users (24hrs)';
            if (batchContainer) batchContainer.classList.add('hidden');
            if (batchSelectorContainer) batchSelectorContainer.classList.add('hidden');
        } else {
            if (refDisplay) refDisplay.innerText = pmState.fileNo || '--';
            if (batchContainer) {
                if (pmState.batchNo && pmState.batchNo !== 'null' && pmState.batchNo !== 'undefined') {
                    batchContainer.classList.remove('hidden');
                } else {
                    batchContainer.classList.add('hidden');
                }
            }

            if (batchSelectorContainer) {
                if (mode === 'Batch') {
                    batchSelectorContainer.classList.remove('hidden');
                    loadPrintableBatches(pmState.selectedBatchNo || pmState.batchNo || null);
                } else {
                    batchSelectorContainer.classList.add('hidden');
                }
            }
        }

        syncDocTypeForMode();

        checkPrintStatus();
    }

    async function checkPrintStatus() {
        console.log('checkPrintStatus called for state:', pmState);
        const docTypeSelect = document.getElementById('pmDocType');
        if (!docTypeSelect) {
            console.error('pmDocType selector not found!');
            return;
        }
        const docType = docTypeSelect.value;
        pmState.docType = docType;

        if (pmState.mode === 'Batch' && !(pmState.selectedBatchNo || pmState.batchNo)) {
            const btn = document.getElementById('pmExecuteBtn');
            const btnText = document.getElementById('pmBtnText');
            const count = document.getElementById('pmCountDisplay');
            const badge = document.getElementById('pmStatusBadge');
            if (count) count.innerText = '--';
            if (badge) badge.innerText = 'Select Batch';
            if (btn) btn.disabled = true;
            if (btnText) btnText.innerText = 'Select Batch ID';
            pmState.allowed = false;
            return;
        }

        let reference = pmState.mode === 'Batch'
            ? (pmState.selectedBatchNo || pmState.batchNo)
            : (pmState.mode === 'Daily24h' ? DAILY_PRINT_REFERENCE : pmState.fileNo);
        if (!reference || reference === '--' || reference === 'N/A') {
            reference = document.getElementById('pmRefDisplay')?.innerText;
        }
        console.log('Reference for status check:', reference);
        
        if (pmState.mode === 'Individual' && !pmState.fileNo) {
            console.warn('Individual mode but no file number set');
            return;
        }

        // Show loading state
        const btnText = document.getElementById('pmBtnText');
        const executeBtn = document.getElementById('pmExecuteBtn');
        if (btnText) btnText.innerText = 'Checking Status...';
        if (executeBtn) executeBtn.disabled = true;

        try {
            let batchParam = '';
            const batchRef = pmState.selectedBatchNo || pmState.batchNo;
            if (pmState.mode === 'Batch' && batchRef && batchRef !== 'null' && batchRef !== 'undefined') {
                batchParam = `&batch_no=${encodeURIComponent(batchRef)}`;
            }
            
            const url = `/file-numbers/get-print-status?reference=${encodeURIComponent(reference)}&type=${pmState.mode}&doc_type=${encodeURIComponent(docType)}${batchParam}`;
            console.log('Fetching print status from:', url);
            
            const response = await fetch(url);
            const data = await response.json();

            // Update UI
            document.getElementById('pmCountDisplay').innerText = data.count;
            const badge = document.getElementById('pmStatusBadge');
            badge.innerText = data.status;
            pmState.status = data.status; // Store status for use in generation

            if (data.status === 'Original') {
                badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
            } else if (data.status === 'Certified True Copy') {
                badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800';
            } else {
                badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800';
            }

            pmState.allowed = data.allowed;

            const btn = document.getElementById('pmExecuteBtn');
            if (data.allowed) {
                btn.disabled = false;
                btn.className = 'w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-lg shadow-lg transform transition hover:scale-[1.02] flex items-center justify-center gap-2';
                document.getElementById('pmBtnText').innerText = data.status === 'Original' ? 'Print Original' : 'Print Certified True Copy';
            } else {
                btn.disabled = true;
                btn.className = 'w-full py-3 px-4 bg-gray-300 text-gray-500 font-bold rounded-lg cursor-not-allowed flex items-center justify-center gap-2';
                document.getElementById('pmBtnText').innerText = 'Print Limit Reached';
            }

        } catch (error) {
            console.error('Error checking print status:', error);
            document.getElementById('pmBtnText').innerText = 'System Error';
        }
    }

    async function executePrintProtocol() {
        if (!pmState.allowed) return;

        if (pmState.mode === 'Batch' && !(pmState.selectedBatchNo || pmState.batchNo)) {
            await Swal.fire({
                icon: 'warning',
                title: 'Batch Required',
                text: 'Please select a batch ID from the dropdown before printing.',
                target: document.getElementById('printerManagerModal') || 'body'
            });
            return;
        }

        // Confirm if creating copy
        const isCopy = document.getElementById('pmBtnText').innerText.includes('Certified True Copy');
        if (isCopy) {
            const result = await Swal.fire({
                title: 'Print Certified True Copy?',
                text: "This will count as your final allowed copy.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Print Copy',
                target: document.getElementById('printerManagerModal') || 'body'
            });
            if (!result.isConfirmed) return;
        }

        // Record Print
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            // Ensure we have a reference. Fallback to DOM display if state is lost.
            let reference = pmState.mode === 'Batch'
                ? (pmState.selectedBatchNo || pmState.batchNo)
                : (pmState.mode === 'Daily24h' ? DAILY_PRINT_REFERENCE : pmState.fileNo);
            if (!reference || reference === '--' || reference === 'N/A') {
                reference = document.getElementById('pmRefDisplay')?.innerText;
            }

            if (!reference || reference === '--' || reference === 'N/A') {
                console.error('Print attempted with invalid reference:', { reference, state: pmState });
                await Swal.fire({
                    icon: 'error',
                    title: 'Reference Missing',
                    text: 'System could not identify the file number. Please close and reopen the Printer Manager.',
                    target: document.getElementById('printerManagerModal') || 'body'
                });
                return;
            }

            const recordResponse = await fetch("{{ route('file-numbers.record-print') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    reference: reference,
                    type: pmState.mode,
                    doc_type: pmState.docType
                })
            });

            const recordData = await recordResponse.json();

            if (recordData.success) {
                // Refresh status to reflect the new print
                checkPrintStatus();

                // Trigger PDF Generation
                if (pmState.docType === 'Commissioning Sheet') {
                    if (pmState.mode === 'Batch') {
                        const selectedBatch = pmState.selectedBatchNo || pmState.batchNo;
                        generateBatchPDF(selectedBatch, pmState.status);
                    } else if (pmState.mode === 'Daily24h') {
                        generateDaily24hPDF(pmState.status);
                    } else {
                        // Individual Commissioning Sheet
                        // Use file number string (not numeric id) so mls-fileno.show looks up
                        // by fileNumber.mlsfNo first, then falls back to mls_file_no.full_file_number.
                        // This prevents temp files (only in mls_file_no) from accidentally loading
                        // a different fileNumber row that shares the same numeric id.
                        const lookupKey = (pmState.fileNo && pmState.fileNo !== '--' && pmState.fileNo !== 'N/A')
                            ? encodeURIComponent(pmState.fileNo)
                            : pmState.id;
                        fetchAndGenerateCommissioningSheet(lookupKey, pmState.status);
                    }
                } else {
                    // Conversion Application
                    if (pmState.mode === 'Batch') {
                        generateBatchConversionApplication(pmState.batchNo);
                    } else {
                        // Individual Conversion
                        generateConversionApplication(pmState.id, pmState.fileNo);
                    }
                }

                // Refresh Status
                checkPrintStatus();

                if (pmState.mode === 'Batch') {
                    loadPrintableBatches();
                }

                closePrinterManager();

            } else {
                Swal.fire('Error', recordData.message || 'Could not record print job', 'error');
            }

        } catch (error) {
            console.error('Print execution failed:', error);
            Swal.fire('Error', 'System communication failed', 'error');
        } 
    } 

    // Helper to fetch data and generate individual Commissioning Sheet
    function fetchAndGenerateCommissioningSheet(id, status = 'Original') {
        showGlobalLoading('Preparing Commissioning Sheet...');

        // Use the more robust mls-fileno.show which handles both ID and file number identifiers
        fetch(`{{ route("mls-fileno.show", ":id") }}`.replace(':id', id))
            .then(response => {
                if (!response.ok) {
                    console.error('API responded with error status:', response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Commissioning Sheet data received:', data);
                // Handle both wrapped (success/data) and flat response formats
                const record = (data.success && data.data) ? data.data : data;
                
                if (record && (record.mlsfNo || record.full_file_number || record.mlsf_no || record.kangisFileNo || record.NewKANGISFileNo || record.st_file_no || record.id)) {
                    // map API data to form fields expected by generateCommissioningSheetPDF
                    const formData = new FormData();
                    formData.append('file_number', record.mlsfNo || record.full_file_number || record.mlsf_no || record.kangisFileNo || record.NewKANGISFileNo || record.st_file_no || '');
                    formData.append('file_name', record.FileName || '');
                    formData.append('name_or_allottee', record.FileName || ''); // Auto-filled from file name
                    formData.append('plot_number', record.plot_no || '');
                    formData.append('tp_number', record.tp_no || '');
                    formData.append('location', record.location || '');
                    formData.append('lga', record.lga || '');
                    formData.append('tracking_id', record.tracking_id || '');
                    formData.append('sit_reason', record.sit_reason || '');
                    formData.append('source', record.source || '');

                    // Add current time/date/user
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    formData.append('time_created', `${hours}:${minutes}`);
                    formData.append('date_created', now.toISOString().split('T')[0]);

                    @if(Auth::check())
                        formData.append('created_by', '{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}');
                    @else
                        formData.append('created_by', 'System User');
                    @endif

                    // Generate PDF
                    const watermarkText = (status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL';
                    generateCommissioningSheetPDF(formData, watermarkText);
                } else {
                    hideGlobalLoading();
                    const msg = (data && data.message) ? data.message : 'Could not fetch file details';
                    Swal.fire('Error', msg, 'error');
                }
            })
            .catch(error => {
                hideGlobalLoading();
                console.error('Error fetching file details:', error);
                Swal.fire('Error', 'Failed to fetch file details', 'error');
            });
    }

</script>
<script>
    /**
     * Opens the shared Capture Occupancy Permit modal from Commission modal
     * and preselects OP type based on Commission selection.
     */
    function openCommissionOpCaptureModal(selection) {
        const normalized = String(selection || '').toLowerCase() === 'resettlement' ? 'resettlement' : 'direct';
        const opType = normalized === 'resettlement' ? 'OP Resettlement' : 'OP Direct Allocation';

        // Batch Capture OP: when Batch Mode is ON (qty > 1) and the reused Batch Capture OP
        // card is present (the OSS applications page), open the OP stepper (OP i of N) instead
        // of the single-record registration dialog. Otherwise fall through to single capture.
        const alpineRoot = document.querySelector('[x-data="fileNumberGenerator()"]');
        const alpineData = (alpineRoot && alpineRoot._x_dataStack) ? alpineRoot._x_dataStack[0] : null;
        // Batch Mode already on (the OSS toggle): still offer to resume an uncommissioned batch
        // rather than always starting a fresh one. The quantity already entered is the default.
        if (alpineData && alpineData.batchMode && parseInt(alpineData.batchQuantity) > 1
            && typeof window.openBatchCaptureOp === 'function') {
            if (typeof window.copPromptBatchStart === 'function' && typeof Swal !== 'undefined') {
                window.copPromptBatchStart(opType, (qty, resume) => {
                    if (!qty) { alpineData.defaultAllocationType = ''; return; }
                    alpineData.batchQuantity = qty;
                    if (typeof alpineData.toggleBatchMode === 'function') alpineData.toggleBatchMode();
                    window.openBatchCaptureOp(qty, opType, resume);
                });
                return;
            }
            window.openBatchCaptureOp(parseInt(alpineData.batchQuantity), opType);
            return;
        }

        // Picking an OP type with Batch Mode still off: ask whether this is a batch or a
        // single OP. Batch turns the Batch Mode toggle on and opens the OP stepper; Single
        // proceeds straight to the existing one-record dialog.
        if (alpineData && typeof window.openBatchCaptureOp === 'function' && typeof Swal !== 'undefined') {
            promptOpBatchOrSingle(alpineData, normalized, opType);
            return;
        }

        openSingleOpRegistrationDialog(normalized);
    }

    /**
     * Batch-or-Single prompt shown when an OP type (Direct Allocation / Resettlement) is
     * picked. Choosing Batch enables Batch Mode with the entered quantity and opens the
     * Batch Capture OP stepper; Single leaves Batch Mode off.
     */
    function promptOpBatchOrSingle(alpineData, normalized, opType) {
        // Third way out, offered as a footer link because the three buttons are already spoken
        // for. Both paths above end in commissioning a NEW file number; this one is for a file
        // that was already commissioned without an OP, so it skips commissioning entirely and
        // goes straight to the Capture OP card. Only offered where that card is on the page.
        const hasCommissionedFlow = typeof window.openCaptureOpForCommissionedFile === 'function';

        Swal.fire({
            title: 'Occupancy Permit (OP)',
            html: `<p class="text-sm text-gray-600">Are you capturing a <strong>single</strong> OP, or a <strong>batch</strong> of OPs for ${opType}?</p>`,
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Batch',
            denyButtonText: 'Single',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            denyButtonColor: '#059669',
            footer: hasCommissionedFlow
                ? '<button type="button" id="opAlreadyCommissionedLink" class="text-sm text-blue-600 hover:text-blue-800 hover:underline font-medium">'
                    + 'The file number is already commissioned — capture the OP for it'
                    + '</button>'
                : '',
            didOpen: () => {
                const link = document.getElementById('opAlreadyCommissionedLink');
                if (!link) return;
                link.addEventListener('click', () => {
                    // Closing resolves the promise as a dismissal, which resets the OP type
                    // selection below — correct here, since we are not commissioning.
                    Swal.close();
                    // The commission form is irrelevant to this flow; don't leave it half-filled
                    // behind the card.
                    if (typeof window.closeCommissionFileNoModal === 'function') {
                        window.closeCommissionFileNoModal();
                    } else {
                        const m = document.getElementById('generateModal');
                        if (m) m.classList.add('hidden');
                    }
                    window.openCaptureOpForCommissionedFile();
                });
            },
        }).then((result) => {
            if (result.isConfirmed) {
                // Batch: offer a new batch or resuming an uncommissioned one. The quantity is
                // asked for inside that flow (a resumed batch brings its own count).
                if (typeof window.copPromptBatchStart !== 'function') {
                    Swal.fire({ icon: 'warning', title: 'Batch capture unavailable', text: 'The OP batch card is not loaded on this page.' });
                    alpineData.defaultAllocationType = '';
                    return;
                }
                window.copPromptBatchStart(opType, (qty, resume) => {
                    if (!qty) { alpineData.defaultAllocationType = ''; return; }   // cancelled
                    alpineData.batchMode = true;
                    alpineData.batchQuantity = qty;
                    if (typeof alpineData.toggleBatchMode === 'function') alpineData.toggleBatchMode();
                    window.openBatchCaptureOp(qty, opType, resume);
                });
                return;
            }

            if (result.isDenied) {
                alpineData.batchMode = false;
                if (typeof alpineData.toggleBatchMode === 'function') alpineData.toggleBatchMode();
                openSingleOpRegistrationDialog(normalized);
                return;
            }

            // Cancelled: undo the OP type selection so the user can choose again.
            alpineData.defaultAllocationType = '';
        });
    }

    // The original single-record OP capture: opens the shared registration dialog.
    function openSingleOpRegistrationDialog(normalized) {
        if (typeof window.openRegistrationDialog !== 'function') {
            Swal.fire({
                icon: 'warning',
                title: 'Capture Modal Unavailable',
                text: 'Capture Occupancy Permit modal is not available on this page.'
            });
            return;
        }

        // Ensure OP-specific flow and labels match OSS OP capture behavior.
        window.ossOpSubmitLabel = 'Capture Existing OP';
        window.ossOpContext = true;
        // Commissioning mirror inserts depend on an existing OP source capture record.
        window.requireOpLookupForCommission = true;
        window.prefillOpTypeFromCommission = normalized;

        // Keep commission modal open; user will return to click Generate.

        window.openRegistrationDialog('occupancy-permit');

        // Fallback preselect in case downstream script has not yet applied it.
        setTimeout(() => {
            const targetId = normalized === 'resettlement' ? 'op_type_resettlement' : 'op_type_direct_allocation';
            const target = document.getElementById(targetId);
            if (target) {
                target.checked = true;
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, 120);
    }

    window.openCommissionOpCaptureModal = openCommissionOpCaptureModal;

    // STANDALONE BATCH PRINT MODAL
    // =====================================================================

    // Latest counts for the selected date. Commissioning sheets and Applications for
    // Conversion are printed (and logged) independently, so each has its own tally.
    let bpCounts = { sheetsUnprinted: 0, conversionTotal: 0, conversionUnprinted: 0, loaded: false };

    // The Generate button stays enabled as long as at least one TICKED document still
    // has something to print — a date whose sheets are all printed can still owe
    // Applications for Conversion.
    function updateBatchPrintButtonState() {
        const printBtn = document.getElementById("bpPrintBtn");
        if (!printBtn) return;

        if (!bpCounts.loaded) {
            printBtn.disabled = true;
            return;
        }

        const wantCommissioning = document.getElementById("bpDocCommissioning")?.checked;
        const wantConversion = document.getElementById("bpDocConversion")?.checked;

        const hasWork = (wantCommissioning && bpCounts.sheetsUnprinted > 0)
            || (wantConversion && bpCounts.conversionUnprinted > 0);

        printBtn.disabled = !hasWork;
    }

    async function onBatchPrintDateChange() {
        const dateInput = document.getElementById("bpDateSelect");
        const countInfo = document.getElementById("bpCountInfo");
        const countText = document.getElementById("bpCountText");
        const printBtn = document.getElementById("bpPrintBtn");
        const noBatchesNotice = document.getElementById("bpNoBatchesNotice");

        const selectedDate = dateInput ? dateInput.value : "";
        bpCounts = { sheetsUnprinted: 0, conversionTotal: 0, conversionUnprinted: 0, loaded: false };

        if (!selectedDate) {
            if (countInfo) countInfo.classList.add("hidden");
            if (noBatchesNotice) noBatchesNotice.classList.add("hidden");
            if (printBtn) printBtn.disabled = true;
            return;
        }

        if (countInfo) countInfo.classList.add("hidden");
        if (noBatchesNotice) noBatchesNotice.classList.add("hidden");
        if (printBtn) printBtn.disabled = true;

        try {
            const response = await fetch("{{ route('mls-fileno.batch-records') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                },
                body: JSON.stringify({ scope: "date", date: selectedDate })
            });

            const payload = await response.json();

            if (payload.total_count > 0) {
                // Populate the new statistic boxes
                const totalEl = document.getElementById("bpTotalCount");
                const printedEl = document.getElementById("bpPrintedCount");
                const unprintedEl = document.getElementById("bpUnprintedCount");
                const remainingEl = document.getElementById("bpRemainingText");

                if (totalEl) totalEl.textContent = payload.total_count;
                if (printedEl) printedEl.textContent = payload.printed_count;
                if (unprintedEl) unprintedEl.textContent = payload.unprinted_count;
                if (remainingEl) remainingEl.textContent = payload.unprinted_count;

                bpCounts = {
                    sheetsUnprinted: Number(payload.unprinted_count) || 0,
                    conversionTotal: Number(payload.conversion_total_count) || 0,
                    conversionUnprinted: Number(payload.conversion_unprinted_count) || 0,
                    loaded: true
                };

                // Conversion breakdown — only meaningful when the date has CON- files.
                const convNote = document.getElementById("bpConversionNote");
                if (convNote) {
                    if (bpCounts.conversionTotal > 0) {
                        document.getElementById("bpConvTotalText").textContent = bpCounts.conversionTotal;
                        document.getElementById("bpConvPrintedText").textContent = Number(payload.conversion_printed_count) || 0;
                        document.getElementById("bpConvUnprintedText").textContent = bpCounts.conversionUnprinted;
                        convNote.classList.remove("hidden");
                    } else {
                        convNote.classList.add("hidden");
                    }
                }

                if (countInfo) countInfo.classList.remove("hidden");
                if (typeof lucide !== "undefined") lucide.createIcons();

                updateBatchPrintButtonState();
            } else {
                if (noBatchesNotice) noBatchesNotice.classList.remove("hidden");
            }

        } catch (err) {
            console.error("onBatchPrintDateChange error:", err);
            if (noBatchesNotice) noBatchesNotice.classList.remove("hidden");
        }
    }

    async function openBatchPrintModal() {
        const modal = document.getElementById("batchPrintModal");
        if (modal) {
            modal.classList.remove("hidden");
            modal.style.display = "";
        }
        
        const dateInput = document.getElementById("bpDateSelect");
        if (dateInput && !dateInput.value) {
            const today = new Date().toISOString().split("T")[0];
            dateInput.value = today;
        }
        
        // Re-evaluate the button whenever the document selection changes.
        ["bpDocCommissioning", "bpDocConversion"].forEach(id => {
            const cb = document.getElementById(id);
            if (cb && !cb.dataset.bpBound) {
                cb.addEventListener("change", updateBatchPrintButtonState);
                cb.dataset.bpBound = "1";
            }
        });

        await onBatchPrintDateChange();
        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    function closeBatchPrintModal() {
        const modal = document.getElementById("batchPrintModal");
        if (modal) {
            modal.classList.add("hidden");
            modal.style.display = "none";
        }
        const countInfo = document.getElementById("bpCountInfo");
        if (countInfo) countInfo.classList.add("hidden");
        const printBtn = document.getElementById("bpPrintBtn");
        if (printBtn) printBtn.disabled = true;
    }

    // Application for Conversion step: show the CON- files for this date with their
    // recipient LGA (READONLY — for review, not editing) and pick the acquisition
    // method, then open the generated documents. Returns true if it ran (or there was
    // nothing to do), false if the operator cancelled.
    async function reviewConversionForBatch(selectedDate, csrfToken) {
        const escHtml = (s) => (s || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Load the CON- files for this date whose Application for Conversion has NOT
        // been printed yet. Note the document_type: the commissioning-sheet print log
        // must not hide files that still owe a conversion application.
        let conFiles = [];
        try {
            const resp = await fetch('{{ route("mls-fileno.batch-records") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ scope: 'date', date: selectedDate, document_type: 'Application for Conversion' })
            });
            const payload = await resp.json();
            const records = Array.isArray(payload.data) ? payload.data : [];
            conFiles = records.filter(r => (r.full_file_number || '').toString().trim().toUpperCase().startsWith('CON-'));
        } catch (e) {
            console.warn('Could not load conversion files', e);
        }

        if (conFiles.length === 0) {
            const allPrinted = bpCounts.conversionTotal > 0;
            await Swal.fire({
                icon: 'info',
                title: allPrinted ? 'Already Generated' : 'No Conversion Files',
                text: allPrinted
                    ? `All ${bpCounts.conversionTotal} Application(s) for Conversion for this date have already been generated.`
                    : 'There are no Conversion (CON-) files for this date, so no Application for Conversion was generated.',
                confirmButtonColor: '#10b981'
            });
            return true; // nothing to do, but not a cancellation
        }

        const rowsHtml = conFiles.map(r => {
            const fno = (r.full_file_number || '').toString();
            const lga = (r.lga && r.lga !== 'N/A') ? r.lga : '—';
            const name = r.file_name ? ` — ${escHtml(r.file_name)}` : '';
            return `
                <div class="border border-gray-200 rounded-lg p-2.5 mb-2">
                    <div class="text-xs font-semibold text-gray-800">${escHtml(fno)}${name}</div>
                    <div class="text-xs text-gray-500 mt-1">Recipient LGA:
                        <span class="font-semibold text-gray-700">${escHtml(lga)}</span>
                        <span class="text-gray-400">(read-only)</span>
                    </div>
                </div>`;
        }).join('');

        const methodOptions = [
            ['a', 'By Purchase'],
            ['b', 'By Inheritance'],
            ['c', 'By Gift'],
            ['d', 'Direct Local Government Allocation'],
            ['e', 'Any other (Specify)'],
        ].map(([v, label], i) => `
            <label class="flex items-center space-x-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer">
                <input type="radio" name="bp_acq_method" value="${v}" class="w-4 h-4 text-emerald-600" ${i === 0 ? 'checked' : ''}>
                <span class="text-sm text-gray-700">${label}</span>
            </label>`).join('');

        const result = await Swal.fire({
            title: 'Application for Conversion',
            html: `
                <p class="text-sm text-gray-600 text-left mb-2">
                    ${conFiles.length} conversion file(s). Review the recipient
                    (<strong>The Chairman, [LGA] Local Government</strong>), then choose the acquisition method.
                </p>
                <div class="text-left mb-3" style="max-height: 12rem; overflow-y: auto; padding-right: 4px;">${rowsHtml}</div>
                <div class="text-left border-t border-gray-100 pt-2">
                    <p class="text-sm font-semibold text-gray-700 mb-1">Acquisition method</p>
                    ${methodOptions}
                    <input type="text" id="bp_acq_other" placeholder="Please specify other method..."
                           class="hidden w-full mt-2 px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
            `,
            width: 540,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Generate Applications',
            cancelButtonText: 'Cancel',
            focusConfirm: false,
            didOpen: () => {
                const other = document.getElementById('bp_acq_other');
                document.querySelectorAll('input[name="bp_acq_method"]').forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        if (e.target.value === 'e') { other.classList.remove('hidden'); other.focus(); }
                        else { other.classList.add('hidden'); }
                    });
                });
            },
            preConfirm: () => {
                const method = document.querySelector('input[name="bp_acq_method"]:checked').value;
                const other = (document.getElementById('bp_acq_other').value || '').trim();
                if (method === 'e' && !other) {
                    Swal.showValidationMessage('Please specify the other acquisition method');
                    return false;
                }
                return { method, other };
            }
        });

        if (!result.isConfirmed) return false; // operator cancelled

        // Open the server-rendered Application for Conversion document(s) for this date,
        // limited to the files just reviewed.
        const { method, other } = result.value;
        const fileNumbers = conFiles.map(r => (r.full_file_number || '').toString().trim()).filter(Boolean);
        let url = `{{ route('file-numbers.date-conversion-application') }}?date=${encodeURIComponent(selectedDate)}&method=${encodeURIComponent(method)}`;
        url += `&files=${encodeURIComponent(fileNumbers.join(','))}`;
        if (method === 'e' && other) url += `&other=${encodeURIComponent(other)}`;
        window.open(url, '_blank');

        // Log the conversion print separately from the commissioning sheet, so these
        // files drop out of the "awaiting Application for Conversion" tally.
        try {
            await fetch("{{ route('file-numbers.record-print') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    reference: selectedDate,
                    type: 'Date',
                    doc_type: 'Application for Conversion',
                    file_numbers: fileNumbers
                })
            });
        } catch (e) {
            console.warn('Could not record conversion print', e);
        }

        return true;
    }

    async function executeBatchPrint() {
        const dateInput = document.getElementById("bpDateSelect");
        const selectedDate = dateInput ? dateInput.value : "";

        if (!selectedDate) {
            Swal.fire({ icon: "warning", title: "No Date Selected", text: "Please select a date before printing." });
            return;
        }

        const wantCommissioning = document.getElementById("bpDocCommissioning")?.checked;
        const wantConversion = document.getElementById("bpDocConversion")?.checked;

        if (!wantCommissioning && !wantConversion) {
            Swal.fire({ icon: "warning", title: "Nothing Selected", text: "Please tick at least one document to generate." });
            return;
        }

        const printBtn = document.getElementById("bpPrintBtn");
        if (printBtn) printBtn.disabled = true;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

            // Application for Conversion: review readonly recipient LGA + pick method first,
            // so a cancellation here stops before anything is generated.
            if (wantConversion) {
                const proceed = await reviewConversionForBatch(selectedDate, csrfToken);
                if (!proceed) {
                    if (printBtn) printBtn.disabled = false;
                    return; // operator cancelled
                }
            }

            // Commissioning Sheets: the normal batch commissioning-sheet print.
            // Skipped when every sheet for the date is already printed — the operator
            // may have ticked it only alongside the conversion applications.
            if (wantCommissioning && bpCounts.sheetsUnprinted > 0) {
                await generateBatchPDF(selectedDate, "Original", "date");

                // ONLY record the print AFTER the PDF has been successfully generated
                const recordResponse = await fetch("{{ route('file-numbers.record-print') }}", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken },
                    body: JSON.stringify({ reference: selectedDate, type: "Date", doc_type: "Commissioning Sheet" })
                });
                const recordData = await recordResponse.json();
                if (!recordData.success) {
                    console.warn("Record print warning:", recordData.message);
                }
            }

            closeBatchPrintModal();

        } catch (err) {
            console.error("executeBatchPrint error:", err);
            Swal.fire({ icon: "error", title: "Print Failed", text: "An unexpected error occurred. Please try again." });
            if (printBtn) printBtn.disabled = false;
        }
    }

    window.openBatchPrintModal = openBatchPrintModal;
    window.closeBatchPrintModal = closeBatchPrintModal;
    window.onBatchPrintDateChange = onBatchPrintDateChange;
    window.executeBatchPrint = executeBatchPrint;

    // ==================== CONSOLIDATION REPORT ====================

    /** In-memory store of fetched report data (for export) */
    let _crReportData = [];

    /**
     * Fetch consolidation report data from backend based on current filter values.
     */
    window.fetchConsolidationReport = async function () {
        const dateFrom = document.getElementById('crDateFrom')?.value || '';
        const dateTo   = document.getElementById('crDateTo')?.value || '';
        const fileYear = document.getElementById('crFileYear')?.value || '';
        const prefix   = document.getElementById('crPrefix')?.value || '';

        // Build query string
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo)   params.append('date_to', dateTo);
        if (fileYear) params.append('file_year', fileYear);
        if (prefix)   params.append('prefix', prefix);

        const tbody = document.getElementById('crPreviewBody');
        if (!tbody) return;

        // Loading state
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                    <div class="flex flex-col items-center gap-2">
                        <i data-lucide="loader" class="w-8 h-8 text-emerald-500 animate-spin"></i>
                        <span class="text-sm">Fetching consolidation data...</span>
                    </div>
                </td>
            </tr>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const response = await fetch(`{{ route("file-numbers.consolidation-report") }}?${params.toString()}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Server error');
            }

            _crReportData = result.data || [];
            const count = result.count || 0;

            // Update UI
            document.getElementById('crRecordCount').textContent = `${count} record${count !== 1 ? 's' : ''}`;
            document.getElementById('crExportBtns').classList.remove('hidden');
            document.getElementById('crFilterSummary').classList.remove('hidden');

            // Build summary badge text
            const parts = [];
            if (fileYear) parts.push(`Year: ${fileYear}`);
            if (prefix)   parts.push(`Prefix: ${prefix}`);
            if (dateFrom || dateTo) parts.push(`Date: ${dateFrom || '…'} → ${dateTo || '…'}`);
            document.getElementById('crSummaryBadge').textContent = parts.length ? parts.join(' | ') : 'All Records';

            if (count === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-3">
                                <i data-lucide="inbox" class="w-10 h-10 text-gray-300"></i>
                                <span class="text-sm">No records found matching the selected filters</span>
                            </div>
                        </td>
                    </tr>
                `;
                if (typeof lucide !== 'undefined') lucide.createIcons();
                return;
            }

            // Render rows
            let html = '';
            _crReportData.forEach((row, idx) => {
                const createdAt = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
                html += `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm text-gray-500 font-medium">${idx + 1}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">${row.full_file_number || '-'}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">${row.file_name || '-'}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">${row.land_use_full || row.land_use || '-'}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">${row.location || '-'}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">${row.lga || '-'}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">${createdAt}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;

        } catch (error) {
            console.error('Consolidation report error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-red-500">
                        <div class="flex flex-col items-center gap-2">
                            <i data-lucide="alert-circle" class="w-8 h-8 text-red-400"></i>
                            <span class="text-sm">Error: ${error.message}</span>
                            <button onclick="fetchConsolidationReport()" class="mt-2 text-emerald-600 hover:text-emerald-700 text-sm font-medium">Try Again</button>
                        </div>
                    </td>
                </tr>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    };

    /**
     * Reset all consolidation report filters to defaults.
     */
    window.resetConsolidationFilters = function () {
        document.getElementById('crDateFrom').value = '';
        document.getElementById('crDateTo').value = '';
        document.getElementById('crFileYear').value = '{{ date("Y") }}';
        document.getElementById('crPrefix').value = '';
        document.getElementById('crExportBtns').classList.add('hidden');
        document.getElementById('crFilterSummary').classList.add('hidden');

        _crReportData = [];
        document.getElementById('crPreviewBody').innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                    <div class="flex flex-col items-center gap-3">
                        <i data-lucide="file-bar-chart" class="w-10 h-10 text-gray-300"></i>
                        <span class="text-sm">Apply filters and click <strong>"Generate Report"</strong> to preview data</span>
                    </div>
                </td>
            </tr>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    /**
     * Export the consolidation report as PDF.
     * Layout mirrors the Instrument Export template (ministry header, logos, autoTable).
     */
    window.exportConsolidationPDF = async function () {
        if (!_crReportData || _crReportData.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Data', text: 'Please generate a report first.' });
            return;
        }

        try {
            showGlobalLoading('Generating PDF...');

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

            // Fetch logos
            const logo1 = await getImageBase64('/assets/logo/logo1.png') || await getImageBase64('/assets/logo/logo1.jpg');
            const logo2 = await getImageBase64('/assets/logo/logo3.jpeg') || await getImageBase64('/assets/logo/las.jpeg');

            // Header
            if (logo1) doc.addImage(logo1, 'JPEG', 15, 8, 18, 18);
            if (logo2) doc.addImage(logo2, 'JPEG', 265, 8, 18, 18);

            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.text('MINISTRY OF LAND & PHYSICAL PLANNING', 148, 14, { align: 'center' });
            doc.setFontSize(11);
            doc.text('DEPARTMENT OF LAND', 148, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            doc.text('MLS FILE NUMBER CONSOLIDATION REPORT', 148, 27, { align: 'center' });

            // Filter summary line
            const parts = [];
            const fileYear = document.getElementById('crFileYear')?.value;
            const prefix   = document.getElementById('crPrefix')?.value;
            const dateFrom = document.getElementById('crDateFrom')?.value;
            const dateTo   = document.getElementById('crDateTo')?.value;
            if (fileYear) parts.push(`Year: ${fileYear}`);
            if (prefix)   parts.push(`Prefix: ${prefix}`);
            if (dateFrom || dateTo) parts.push(`Date: ${dateFrom || '…'} - ${dateTo || '…'}`);
            if (parts.length) {
                doc.setFontSize(8);
                doc.setTextColor(100);
                doc.text(`Filters: ${parts.join(' | ')}`, 148, 32, { align: 'center' });
                doc.setTextColor(0);
            }

            // Coat of Arms watermark (centered)
            const coatOfArms = await getImageBase64('/assets/logo/Nigerian-Coat-of-Arms.png') || await getImageBase64('/assets/logo/court_of arms.jpeg');
            if (coatOfArms) {
                const pageW = doc.internal.pageSize.getWidth();
                const pageH = doc.internal.pageSize.getHeight();
                const wmSize = 90;
                doc.setGState(doc.GState({ opacity: 0.06 }));
                doc.addImage(coatOfArms, 'PNG', (pageW - wmSize) / 2, (pageH - wmSize) / 2, wmSize, wmSize);
                doc.setGState(doc.GState({ opacity: 1.0 }));
            }

            // Table data
            const tableHead = [['SN', 'File Number', 'File Name / Allottee', 'Land Use', 'Location', 'LGA', 'Date Commissioned']];
            const tableBody = _crReportData.map((row, i) => {
                const d = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
                return [i + 1, row.full_file_number || '-', row.file_name || '-', row.land_use_full || row.land_use || '-', row.location || '-', row.lga || '-', d];
            });

            doc.autoTable({
                startY: 36,
                head: tableHead,
                body: tableBody,
                styles: { fontSize: 8, cellPadding: 2, lineWidth: 0.1, lineColor: [200, 200, 200] },
                headStyles: {
                    fillColor: [16, 185, 129],
                    textColor: 255,
                    fontStyle: 'bold',
                    fontSize: 8
                },
                alternateRowStyles: { fillColor: [245, 245, 245] },
                margin: { left: 10, right: 10 },
                didDrawPage: function (data) {
                    const pg = doc.internal.pageSize;
                    // Coat of Arms watermark on every page
                    if (coatOfArms) {
                        const wmSize = 90;
                        doc.setGState(doc.GState({ opacity: 0.06 }));
                        doc.addImage(coatOfArms, 'PNG', (pg.getWidth() - wmSize) / 2, (pg.getHeight() - wmSize) / 2, wmSize, wmSize);
                        doc.setGState(doc.GState({ opacity: 1.0 }));
                    }
                    // Footer on every page
                    const pageCount = doc.internal.getNumberOfPages();
                    doc.setFontSize(7);
                    doc.setTextColor(150);
                    doc.text(
                        `Generated on ${new Date().toLocaleString()} | Page ${data.pageNumber} of ${pageCount} | Total Records: ${_crReportData.length}`,
                        148, pg.getHeight() - 8,
                        { align: 'center' }
                    );
                }
            });

            hideGlobalLoading();

            const filename = `consolidation-report${fileYear ? '-' + fileYear : ''}${prefix ? '-' + prefix : ''}.pdf`;
            doc.save(filename);

            Swal.fire({
                icon: 'success',
                title: 'PDF Generated',
                html: `Report exported successfully.<br><strong>${_crReportData.length}</strong> record${_crReportData.length !== 1 ? 's' : ''}.`,
                confirmButtonColor: '#10b981'
            });
        } catch (error) {
            hideGlobalLoading();
            console.error('PDF export error:', error);
            Swal.fire({ icon: 'error', title: 'PDF Error', text: 'Failed to generate PDF: ' + error.message });
        }
    };

    /**
     * Export the consolidation report as Excel (CSV download).
     */
    window.exportConsolidationExcel = function () {
        if (!_crReportData || _crReportData.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Data', text: 'Please generate a report first.' });
            return;
        }

        try {
            const headers = ['SN', 'File Number', 'File Name', 'Land Use', 'Customer Type', 'Location', 'LGA', 'Source', 'Commissioned By', 'Date'];
            const csvRows = [headers.join(',')];

            _crReportData.forEach((row, i) => {
                const d = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB') : '';
                const line = [
                    i + 1,
                    `"${(row.full_file_number || '').replace(/"/g, '""')}"`,
                    `"${(row.file_name || '').replace(/"/g, '""')}"`,
                    `"${(row.land_use_full || row.land_use || '').replace(/"/g, '""')}"`,
                    `"${(row.customer_type || '').replace(/"/g, '""')}"`,
                    `"${(row.location || '').replace(/"/g, '""')}"`,
                    `"${(row.lga || '').replace(/"/g, '""')}"`,
                    `"${(row.source || '').replace(/"/g, '""')}"`,
                    `"${(row.created_by || '').replace(/"/g, '""')}"`,
                    d
                ];
                csvRows.push(line.join(','));
            });

            const csvContent = csvRows.join('\n');
            const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);

            const fileYear = document.getElementById('crFileYear')?.value || '';
            const prefix   = document.getElementById('crPrefix')?.value || '';
            link.download = `consolidation-report${fileYear ? '-' + fileYear : ''}${prefix ? '-' + prefix : ''}.csv`;
            link.click();
            URL.revokeObjectURL(link.href);

            Swal.fire({
                icon: 'success',
                title: 'Excel/CSV Exported',
                html: `<strong>${_crReportData.length}</strong> record${_crReportData.length !== 1 ? 's' : ''} exported successfully.`,
                confirmButtonColor: '#10b981'
            });
        } catch (error) {
            console.error('Excel export error:', error);
            Swal.fire({ icon: 'error', title: 'Export Error', text: 'Failed to export: ' + error.message });
        }
    };

    // --- Merger Selection Modal Functions ---
    window.closeMergerFileModal = function() {
        document.getElementById('mergerFileModal').classList.add('hidden');
    };

    window.searchMergerFiles = function(query) {
        const tableBody = document.getElementById('mergerResultsTable');
        if (!tableBody) return;

        fetch(`{{ route('plot-merger.approved-list') }}?search=${encodeURIComponent(query || '')}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    tableBody.innerHTML = res.data.length ? '' : '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No approved applications found.</td></tr>';
                    res.data.forEach(item => {
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50 cursor-pointer transition-colors border-b last:border-0';
                        row.innerHTML = `
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">${item.temp_file_no}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${item.file_no || '-'}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="font-semibold uppercase">${item.applicant_name || 'N/A'}</div>
                                <div class="text-[10px] text-gray-400 uppercase truncate max-w-xs">${item.file_title || ''}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" 
                                        class="px-3 py-1 bg-orange-600 text-white text-xs font-bold rounded hover:bg-orange-700 transition-colors">
                                    Select
                                </button>
                            </td>
                        `;
                        row.onclick = () => selectMergerFile(item);
                        tableBody.appendChild(row);
                    });
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            })
            .catch(err => {
                console.error('Failed to search merger files:', err);
                tableBody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">Error loading data.</td></tr>';
            });
    };

    window.selectMergerFile = function(item) {
        if (window._mergerCallback) {
            window._mergerCallback(item);
        }
        closeMergerFileModal();
    };

    window.searchCopFiles = function(query) {
        const resultsContainer = document.getElementById('copSearchResults');
        if (!resultsContainer) return;

        resultsContainer.innerHTML = `
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        `;

        fetch(`{{ route("change-of-purpose.search-approved") }}?term=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data && res.data.length > 0) {
                    let html = '<div class="space-y-2">';
                    res.data.forEach(item => {
                        html += `
                            <div class="p-4 border rounded-lg hover:bg-gray-50 cursor-pointer transition-colors" 
                                 onclick="selectCopFile(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-bold text-gray-900">${item.file_no}</div>
                                        <div class="text-sm text-gray-600">${item.applicant_name}</div>
                                        <div class="text-xs text-gray-400 mt-1">${item.location || 'No location'}</div>
                                    </div>
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Approved</span>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    resultsContainer.innerHTML = html;
                } else {
                    resultsContainer.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            No approved applications found for "${query}"
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error(err);
                resultsContainer.innerHTML = '<div class="text-center py-8 text-red-500">Error loading applications</div>';
            });
    };

    window.closeCopFileModal = function() {
        const modal = document.getElementById('copFileModal');
        if (modal) modal.classList.add('hidden');
    };

    window.selectCopFile = function(item) {
        if (window._copCallback) {
            window._copCallback(item);
        }
        closeCopFileModal();
    };

    // Explicitly export key functions to window for Alpine and global access
    window.openGeneratorModalMain = openGeneratorModalMain;
    window.fileNumberGenerator = fileNumberGenerator;
</script>

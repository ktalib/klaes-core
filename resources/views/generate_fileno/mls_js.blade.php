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
{{-- Captures the File Tracking Sheet for page 2 of the commissioning sheet. --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>

@php
    // The shared modal already supplies this lookup; standalone generator pages load it here.
    if (!isset($modalCommissioningSheets)) {
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
    }
@endphp

<script>
    console.log('DEBUG: mls_js.blade.php script tag reached');

    // The TP lookup's "Other" row (InstrumentController::searchTpLookups appends it to
    // every response). It is a UI sentinel only — it must never be stored as a TP number,
    // which is why picking it clears tpNo until the specify box is filled in.
    const TP_OTHER = '__other__';
    
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
    const commissioningSheetsMap = window.modalCommissioningSheetsMap || @json($commissioningSheets);

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

    /**
     * Read a Generate response without losing the reason it failed.
     *
     * `response.json()` on its own turns every non-JSON reply into the same opaque
     * SyntaxError, and the caller's .catch then shows "An error occurred while
     * generating the file number" for causes that need completely different repairs:
     * a validation rejection the officer could have fixed, a session that expired, a
     * server error, or — the one seen in production — a perfectly good 422 whose body
     * had PHP notice output (`<br /><b>Warning</b>: ...`) written in front of it by
     * display_errors.
     *
     * That last case is recoverable here: the framework's JSON is intact, just not at
     * the start of the body, so it is salvaged from the first brace and the officer
     * gets their field errors while the php.ini fix is applied separately. Everything
     * else is rethrown as an Error carrying enough to say something true.
     */
    async function parseGenerateResponse(response) {
        const body = await response.text();

        let data = null;
        try {
            data = JSON.parse(body);
        } catch (e) {
            // Salvage JSON that had output written ahead of it.
            const brace = body.indexOf('{');
            if (brace > 0) {
                try { data = JSON.parse(body.slice(brace)); } catch (e2) { /* not salvageable */ }
            }

            if (data) {
                console.warn(
                    '[mls-fileno] Response body had ' + brace + ' bytes of output before the JSON. ' +
                    'This is PHP display_errors writing into the response — see the leading bytes:',
                    body.slice(0, 200)
                );
            }
        }

        if (!data) {
            const err = new Error('unparseable_response');
            err.mlsStatus = response.status;
            err.mlsBody = body.slice(0, 400);
            throw err;
        }

        if (!response.ok) {
            const err = new Error('http_error');
            err.mlsStatus = response.status;
            err.mlsData = data;
            throw err;
        }

        return data;
    }

    /**
     * Turn a parseGenerateResponse failure into something an officer can act on.
     * Laravel's 422 body carries the per-field messages; showing them is the whole
     * difference between "fix the Land Use" and "an error occurred".
     */
    function describeGenerateFailure(error) {
        const status = error && error.mlsStatus;
        const data = error && error.mlsData;

        if (status === 419) {
            return 'Your session expired while the form was open. Please reload the page and sign in again — nothing was commissioned.';
        }

        if (status === 401 || status === 403) {
            return 'You are not signed in, or not permitted to commission this file. Please reload the page and sign in again.';
        }

        if (status === 422 && data) {
            // The OP Smart Detector's own refusal. It is not a field validation
            // failure — the form is fine, the OP is simply being commissioned in the
            // wrong stream — so it carries its own message and must not be flattened
            // into the generic "check the highlighted fields" text below.
            if (data.error_type === 'op_commissioning_flow_mismatch') {
                return data.message;
            }

            const fields = data.errors && typeof data.errors === 'object' ? Object.values(data.errors) : [];
            const messages = fields.reduce((all, list) => all.concat(list), []).filter(Boolean);

            if (messages.length) {
                return messages.slice(0, 6).join('\n');
            }

            return data.message || 'The form was rejected. Please check the highlighted fields.';
        }

        if (error && error.message === 'unparseable_response') {
            // The status line was fine or not; either way the body was not JSON at
            // all, so there is nothing to quote back except what it started with.
            return 'The server replied with something this page could not read' +
                (status ? ' (HTTP ' + status + ')' : '') +
                '. This is a server configuration problem, not your data — please report it with the time shown.';
        }

        if (data && data.message) {
            return data.message;
        }

        return 'An error occurred while generating the file number' + (status ? ' (HTTP ' + status + ')' : '');
    }

    /**
     * Dress a generate failure for Swal.
     *
     * Almost everything that comes back is a genuine error and gets the red
     * "Error!" treatment. The OP Smart Detector's refusal is not: nothing went
     * wrong, the OP is just in the wrong commissioning stream, and showing it in
     * red under "Error!" reads as a system fault and sends officers hunting for a
     * bug instead of switching flow. Give it the same amber warning shape the
     * client-side copy of the detector already uses, so the officer sees one
     * consistent message whichever side caught it.
     */
    function generateFailureDialog(error, overrideText) {
        const data = error && error.mlsData;
        const text = overrideText || describeGenerateFailure(error);

        if (data && data.error_type === 'op_commissioning_flow_mismatch' && !overrideText) {
            const detector = data.op_smart_detector || {};
            return {
                icon: 'warning',
                title: detector.correct_flow === 'Land' ? 'Land OP Detected' : 'OSS Change of Ownership Detected',
                text: text,
                confirmButtonText: 'Review file title',
                confirmButtonColor: '#f59e0b'
            };
        }

        return { icon: 'error', title: 'Error!', text: text, confirmButtonColor: '#ef4444' };
    }

    /** Is a duplex currently driving the modal? */
    function duplexIsDriving() {
        const el = document.querySelector('[x-data^="fileNumberGenerator"]');
        return !!(el && el._x_dataStack && el._x_dataStack[0] && el._x_dataStack[0].duplexRecordId);
    }

    function setActionButtonsDisabled(isDisabled) {
        const overrideBtn = document.getElementById('overrideButton');
        const generateBtn = document.getElementById('generateButton');

        // The gate below exists to stop a file being commissioned before its grouping
        // record has a Tracking ID. A duplex has no single awaiting file number to look
        // up — each stage resolves or mints its own tracking id at commit — so the
        // lookup can never succeed and would leave Generate disabled forever.
        if (isDisabled && duplexIsDriving()) {
            isDisabled = false;
        }

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
        setCommissioningOldFileNumber('');
        setCommissioningRelatedFileNumber('');
        updateCommissioningFileType();
        if (!key) return;

        const stillOnSameFile = () => {
            const input = document.getElementById('cs_file_number');
            return input && input.value.trim().toUpperCase() === sourceInput.dataset.fileNo;
        };

        try {
            const response = await fetch(`{{ route("mls-fileno.show", ":id") }}`.replace(':id', encodeURIComponent(key)));
            const payload = await response.json();
            const record = (payload && payload.success && payload.data) ? payload.data : payload;
            if (record && record.source && stillOnSameFile()) {
                sourceInput.value = String(record.source).trim();
                updateCommissioningFileType();
            }
        } catch (e) {
            console.warn('Could not resolve file source for commissioning sheet', e);
        }

        // The old (duplicated) number a Re-Issuance replaces and the file this one was
        // raised from. Both come back paired with their KANGIS/land counterpart, and
        // both leave their row out when there is none.
        const links = await fetchCommissioningFileLinks(key);
        if (stillOnSameFile()) {
            setCommissioningOldFileNumber(links.old_file_number, key);
            setCommissioningRelatedFileNumber(links.related_file_number, key);
        }
    }

    // Show/hide the Old File No line on the commissioning modal. An empty value keeps
    // the row hidden so the printed sheet never carries a blank rule.
    function setCommissioningOldFileNumber(value, fileNumber = '') {
        const input = document.getElementById('cs_old_file_number');
        const wrap = document.getElementById('cs_old_file_number_wrap');
        if (!input) return;

        let old = String(value || '').trim();
        // An entry equal to the file's own number is not a previous number.
        if (old && fileNumber && old.toUpperCase() === String(fileNumber).trim().toUpperCase()) {
            old = '';
        }

        input.value = old;
        if (wrap) wrap.classList.toggle('hidden', old === '');
    }

    // Same treatment for the Related File No line: a file with no related file keeps
    // the row off the sheet entirely.
    function setCommissioningRelatedFileNumber(value, fileNumber = '') {
        const input = document.getElementById('cs_related_file_number');
        const wrap = document.getElementById('cs_related_file_number_wrap');
        if (!input) return;

        let related = String(value || '').trim();
        // A file is not related to itself.
        if (related && fileNumber && related.toUpperCase() === String(fileNumber).trim().toUpperCase()) {
            related = '';
        }

        input.value = related;
        if (wrap) wrap.classList.toggle('hidden', related === '');
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
                    await previewTrackingIdForNewFile(fileNumber, requestId);
                }
            } else {
                if (response.status === 404) {
                    await previewTrackingIdForNewFile(fileNumber, requestId);
                } else {
                    handleGroupingLookupError(responseData, response.status);
                }
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

    async function previewTrackingIdForNewFile(fileNumber, requestId) {
        const modalEl = document.querySelector('[x-data^="fileNumberGenerator"]');
        const state = modalEl?._x_dataStack ? modalEl._x_dataStack[0] : null;
        const isNewFile = state && !state.existingFileNo && state.fileOption !== 'temporary';
        if (!isNewFile) {
            applyTrackingIdToDisplay('', 'Not Found');
            setActionButtonsDisabled(true);
            return;
        }

        try {
            const response = await fetch("{{ route('mls-fileno.preview-tracking-id') }}?preferred=", {
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            if (groupingLookupState.inFlightRequestId !== requestId) return;

            const trackingId = (payload.data?.tracking_id ?? '').trim();
            applyTrackingIdToDisplay(trackingId, 'Not Found');
            setActionButtonsDisabled(trackingId === '');
        } catch (error) {
            if (groupingLookupState.inFlightRequestId === requestId) {
                applyTrackingIdToDisplay('', 'Not Found');
                setActionButtonsDisabled(true);
            }
            console.error('Tracking ID preview failed:', error);
        }
    }

    function handleGroupingLookupError(payload, status) {
        resetTrackingIdDisplay('--');

        /* 
        if (status === 409) {
            Swal.fire({
                icon: 'warning',
                title: 'Mapping Conflict',
                text: payload?.message || 'Grouping record is mapped to a different MLPP file number.',
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

            // filenumber_id is resolved by joining mls_file_no.tracking_id to fileNumber,
            // and a handful of batch members do not resolve. Those rows used to render a
            // live Edit button that fired GET /file-numbers/null and 500'd on an int cast.
            const linked = record.filenumber_id !== null
                && record.filenumber_id !== undefined
                && String(record.filenumber_id) !== ''
                && String(record.filenumber_id) !== 'null';

            // A member the registry has since retired reads as a live file number unless
            // the row says otherwise. The number it became sits directly beneath it, the
            // same way the register's own table carries a related file, so the officer can
            // follow the parcel forward instead of reading a number that no longer answers.
            const fileNo = (record.mlsfNo || record.full_file_number || 'N/A').toUpperCase();
            let numberCell = record.superseded
                ? `<span class="text-gray-400 line-through">${fileNo}</span>`
                : fileNo;

            if (record.superseded) {
                const more = record.superseded_count > 1
                    ? ` <span class="text-[10px] font-bold text-sky-500">+${record.superseded_count - 1} more</span>`
                    : '';

                numberCell += record.superseded_by
                    ? `<div class="mt-1 text-[11px] font-normal whitespace-nowrap">
                           <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-700 border border-sky-200 mr-1">REL</span>
                           <span class="font-semibold text-sky-700">${String(record.superseded_by).toUpperCase()}</span>${more}
                       </div>`
                    : `<div class="mt-1 text-[11px] font-normal">
                           <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-500 border border-gray-200">RETIRED</span>
                       </div>`;

                if (record.superseded_reason) {
                    numberCell += `<div class="mt-0.5 text-[10px] text-gray-400 whitespace-nowrap">${String(record.superseded_reason).replace(/</g, '&lt;')}</div>`;
                }

                row.className = 'bg-sky-50/40 hover:bg-sky-50 transition-colors cursor-default';
            }

            row.innerHTML = `
                <td class="px-4 py-3 whitespace-nowrap align-top text-sm font-bold text-gray-900">${numberCell}</td>
                <td class="px-4 py-3 whitespace-nowrap align-top text-sm text-gray-700">${(record.file_name || record.FileName || 'N/A').toUpperCase()}</td>
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
                    ${linked ? `
                    <button onclick="editRecord(${record.filenumber_id})" class="inline-flex p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Record">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                    </button>` : `
                    <span class="inline-flex p-1.5 text-gray-300 cursor-not-allowed" title="No matching file number record — cannot be edited">
                        <i data-lucide="pencil-off" class="w-4 h-4"></i>
                    </span>`}
                    <button onclick="openFilePrinterManager('${record.filenumber_id}', '${record.mlsfNo || record.full_file_number || ''}', '${record.batch_no}', true)" class="inline-flex p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Print Document">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </button>
                    ${(window.MLSF_IS_ADMIN && linked) ? `
                    <button onclick="deleteBatchMember(${record.filenumber_id}, '${String(record.mlsfNo || record.full_file_number || '').replace(/'/g, "\\\\'")}', '${record.batch_no || ''}')" class="inline-flex p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Master Delete this file">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>` : ''}
                </td>
            `;
            tableBody.appendChild(row);
        });

        // Re-initialize Lucide icons for the new buttons
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    /**
     * Master-delete ONE file from inside the Group modal.
     *
     * The list only ever exposed the batch's newest member, so every other file in a batch
     * was unreachable by Delete — the only way to remove one was to delete the head
     * repeatedly until the grouping surfaced it. This deletes exactly the file named on
     * the row, and never its siblings.
     */
    window.deleteBatchMember = function (id, fileNo, batchNo) {
        Swal.fire({
            title: `Delete ${fileNo}?`,
            html: `
                <div class="text-center">
                    <p class="mb-3 text-slate-500 text-sm">
                        This permanently purges <strong>${fileNo}</strong> from ${MLSF_CASCADE_TABLES.length} tables.
                        The other files in batch ${batchNo} are <strong>not</strong> affected.
                    </p>
                    ${mlsfCascadeTableList()}
                </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete this file',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ route("file-numbers.destroy", ":id") }}`.replace(':id', id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ entity: 'file_number', apply_to_batch: 0 })
                })
                    .then(r => r.json())
                    .catch(err => { Swal.showValidationMessage(`Request failed: ${err}`); });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then(result => {
            if (!result.isConfirmed || !result.value) return;

            if (result.value.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', text: result.value.message, confirmButtonColor: '#10b981' });
                if (batchNo) viewBatch(batchNo);
                if (typeof table !== 'undefined' && table.ajax) table.ajax.reload(null, false);
                updateStats();
            } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: result.value.message || 'Delete failed.', confirmButtonColor: '#ef4444' });
            }
        });
    };

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

                // The value carries the row's SOURCE TABLE, not just its id. Three tables
                // feed this list and their ids collide, so a bare id told the server
                // nothing about which file was ticked — temporary RES-1993-2644(T) is
                // mls_file_no id 1166, and fileNumber 1166 is a different, live file.
                const key = mlsfSelectionKey(row);
                const checked = window.mlsfSelectedIds && window.mlsfSelectedIds.has(key) ? 'checked' : '';
                const batchCount = parseInt(row.batch_count, 10) || 1;

                // A batch row stands for many files; say so next to the box so a bulk
                // delete's real reach is visible before it is confirmed.
                const badge = batchCount > 1
                    ? `<span class="block text-[9px] font-bold text-amber-600 leading-none mt-0.5" title="This row represents ${batchCount} files">×${batchCount}</span>`
                    : '';

                if (mlsfRowEntity(row.type) !== 'file_number') {
                    return '<span class="text-gray-300" title="Not held in the file number register — cannot be master-deleted here">&mdash;</span>';
                }

                return '<input type="checkbox" class="mlsf-row-check w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500 cursor-pointer" value="'
                    + key + '" data-batch-count="' + batchCount + '" ' + checked + '>' + badge;
            }
        };

        // Which table a list row actually came from. Mirrors App\Support\MlsRowTarget so
        // the client and the server speak the same vocabulary.
        window.mlsfRowEntity = function (rowType) {
            const t = String(rowType || '').trim().toLowerCase();
            if (t === 'plot extension') return 'plot_extension';
            if (t === 'temporary') return 'temporary';
            return 'file_number';
        };

        // "F:123" / "T:1166" / "P:4" — parsed by MlsRowTarget::parseKey().
        window.mlsfSelectionKey = function (row) {
            const entity = mlsfRowEntity(row.type);
            const prefix = entity === 'plot_extension' ? 'P' : (entity === 'temporary' ? 'T' : 'F');
            return prefix + ':' + row.id;
        };

        // Initialize DataTable with performance optimizations
        // Initialize DataTable

    // ── Table "View Map" modal ────────────────────────────────────────────────
    // Uses the same google.maps facade as the generator's own map (see
    // partials/maps_scripts — it is Leaflet behind a shim), so no extra library.
    let mlsfMapModalMap = null;
    let mlsfMapModalMarker = null;

    function openMlsfMapModal(lat, lon, fileNo, fileTitle) {
        const latNum = parseFloat(lat);
        const lonNum = parseFloat(lon);
        const modal = document.getElementById('mlsfMapModal');
        if (!modal || !isFinite(latNum) || !isFinite(lonNum)) return;

        const subtitle = document.getElementById('mlsfMapModalSubtitle');
        if (subtitle) {
            subtitle.textContent = [fileNo, fileTitle].filter(v => v && v !== 'N/A').join(' — ');
        }
        const latEl = document.getElementById('mlsfMapModalLat');
        const lngEl = document.getElementById('mlsfMapModalLng');
        if (latEl) latEl.textContent = latNum.toFixed(6);
        if (lngEl) lngEl.textContent = lonNum.toFixed(6);

        const external = document.getElementById('mlsfMapModalExternal');
        if (external) {
            external.href = `https://www.openstreetmap.org/?mlat=${latNum}&mlon=${lonNum}#map=17/${latNum}/${lonNum}`;
        }

        modal.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();

        if (typeof google === 'undefined' || !google.maps) {
            console.warn('[mlsf-map] map library not ready');
            return;
        }

        const canvas = document.getElementById('mlsfMapModalCanvas');
        const position = { lat: latNum, lng: lonNum };

        // The canvas has no size until the modal is visible, so build/resize the map
        // after the browser has laid it out — otherwise it renders as a grey box.
        requestAnimationFrame(function () {
            if (!mlsfMapModalMap) {
                mlsfMapModalMap = new google.maps.Map(canvas, {
                    center: position,
                    zoom: 17,
                    mapTypeId: 'satellite',
                    streetViewControl: true,
                    fullscreenControl: true
                });
            }

            google.maps.event.trigger(mlsfMapModalMap, 'resize');
            mlsfMapModalMap.setCenter(position);

            if (!mlsfMapModalMarker) {
                mlsfMapModalMarker = new google.maps.Marker({
                    position: position,
                    map: mlsfMapModalMap,
                    title: fileNo || 'Property location'
                });
            } else {
                mlsfMapModalMarker.setPosition(position);
            }
        });
    }

    function closeMlsfMapModal() {
        const modal = document.getElementById('mlsfMapModal');
        if (modal) modal.classList.add('hidden');
    }

    window.openMlsfMapModal = openMlsfMapModal;
    window.closeMlsfMapModal = closeMlsfMapModal;

    // ── Phone quick actions ──────────────────────────────────────────────────────
    // Call / SMS / WhatsApp / Email for the applicant on a row.
    //
    // Call and WhatsApp hand off to the device. SMS does NOT: it opens a compose box
    // and the server sends the message through the Ministry's Bulk-SMS.ng account, so
    // it goes out under KANOMLPP and is recorded in the SMS Control Centre log. An
    // `sms:` link would have depended on the desktop having an SMS app registered,
    // which the counter machines do not.
    //
    // Email is built but stays disabled: no email address is stored for a commissioned
    // file anywhere (customers_staging holds 0 of 132,952), so opening a mailto: with
    // nothing in it would only look broken. Give the row an `email` and it lights up.

    // Who the compose box is addressed to. Set when the quick-action card opens, so
    // Send cannot be pointed at a row other than the one that was clicked.
    let mlsfSmsTarget = null;

    function mlsfWhatsappNumber(raw) {
        const digits = String(raw || '').replace(/\D/g, '');
        if (!digits) return '';
        // Nigerian numbers are keyed locally as 0XXXXXXXXXX; wa.me needs the country
        // code and no leading zero. Anything already international is left alone.
        if (digits.startsWith('234')) return digits;
        if (digits.startsWith('0')) return '234' + digits.slice(1);
        return digits;
    }

    function openMlsfPhoneActions(row) {
        const modal = document.getElementById('mlsfPhoneActionsModal');
        const body = document.getElementById('mlsfPhoneActionsBody');
        const subtitle = document.getElementById('mlsfPhoneActionsSubtitle');
        if (!modal || !body || !row) return;

        const number = String(row.phone_no || '').trim();
        if (!number) return;

        const dial = number.replace(/[^\d+]/g, '');
        const wa = mlsfWhatsappNumber(number);
        const email = String(row.email || '').trim();
        const fileNo = String(row.mlsfNo || row.primaryFileNo || '').toUpperCase();
        const name = String(row.FileName || '').trim();

        const who = [name && name !== 'N/A' ? name : null, fileNo, number].filter(Boolean).join(' · ');

        if (subtitle) {
            subtitle.textContent = who;
        }

        // Held for the compose box, which opens from this card.
        mlsfSmsTarget = { phone: number, fileNo: fileNo, who: who };

        // An action that runs script here rather than leaving the page. Written as a
        // button so it cannot be middle-clicked into a new tab like the links can.
        const scriptAction = (handler, icon, label, tone) => `
            <button type="button" onclick="${handler}"
                    class="flex flex-col items-center justify-center gap-1.5 rounded-lg border ${tone} px-3 py-4 text-xs font-semibold transition-colors">
                <i data-lucide="${icon}" class="h-5 w-5"></i>
                <span>${label}</span>
            </button>`;

        const action = (href, icon, label, tone, enabled, title) => enabled
            ? `<a href="${href}" ${href.startsWith('http') ? 'target="_blank" rel="noopener"' : ''}
                  class="flex flex-col items-center justify-center gap-1.5 rounded-lg border ${tone} px-3 py-4 text-xs font-semibold transition-colors">
                   <i data-lucide="${icon}" class="h-5 w-5"></i>
                   <span>${label}</span>
               </a>`
            : `<span title="${escapeMlsfHtml(title || '')}"
                     class="flex cursor-not-allowed flex-col items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-4 text-xs font-semibold text-gray-300">
                   <i data-lucide="${icon}" class="h-5 w-5"></i>
                   <span>${label}</span>
               </span>`;

        body.innerHTML = [
            action(`tel:${dial}`, 'phone', 'Call', 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white hover:border-emerald-600', true),
            scriptAction('openMlsfSmsCompose()', 'message-square', 'SMS', 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-600 hover:text-white hover:border-sky-600'),
            action(`https://wa.me/${wa}`, 'message-circle', 'WhatsApp', 'border-green-200 bg-green-50 text-green-700 hover:bg-green-600 hover:text-white hover:border-green-600', Boolean(wa)),
            action(`mailto:${email}`, 'mail', 'Email', 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600', Boolean(email), 'No email address on record for this file'),
        ].join('');

        modal.classList.remove('hidden');
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function closeMlsfPhoneActions() {
        document.getElementById('mlsfPhoneActionsModal')?.classList.add('hidden');
    }

    // ── SMS compose ──────────────────────────────────────────────────────────────
    /**
     * Does this look like a Nigerian mobile line at all?
     *
     * BulkSmsNgService::normalizeNumber only checks LENGTH, so an eleven-digit
     * number that is not a handset — 02264394121 sits on IND-2026-277 — normalises
     * happily to 2342264394121 and is accepted and billed by a gateway that can
     * never deliver it. Every Nigerian mobile number begins 070/080/081/090/091, so
     * the leading digit of the national part answers this on its own.
     *
     * This WARNS, it does not block: the officer can see the number and may know
     * something this rule does not. Deliberately not pushed down into the shared
     * normaliser, which the nine catalogue messages also run through.
     */
    function mlsfLooksLikeMobile(raw) {
        let digits = String(raw || '').replace(/\D/g, '');
        if (digits.startsWith('234')) digits = digits.slice(3);
        else if (digits.startsWith('0')) digits = digits.slice(1);
        return digits.length === 10 && /^[789]/.test(digits);
    }

    function mlsfSmsNotice(text, tone) {
        const box = document.getElementById('mlsfSmsComposeNotice');
        if (!box) return;

        if (!text) {
            box.classList.add('hidden');
            box.textContent = '';
            return;
        }

        const tones = {
            error: 'border-red-200 bg-red-50 text-red-700',
            warn: 'border-amber-200 bg-amber-50 text-amber-800',
            ok: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        };

        box.className = 'rounded-md border px-3 py-2 text-xs ' + (tones[tone] || tones.warn);
        box.textContent = text;
    }

    function updateMlsfSmsCount() {
        const field = document.getElementById('mlsfSmsComposeText');
        const label = document.getElementById('mlsfSmsComposeCount');
        const send = document.getElementById('mlsfSmsComposeSend');
        if (!field || !label) return;

        const len = field.value.length;
        // 160 characters to a page; the gateway bills each page separately, and a
        // message of zero characters still has zero pages, not one.
        const pages = len === 0 ? 0 : Math.ceil(len / 160);

        label.textContent = `${len} character${len === 1 ? '' : 's'} · ${pages} page${pages === 1 ? '' : 's'}`;
        if (send) send.disabled = field.value.trim() === '';
    }

    function openMlsfSmsCompose() {
        if (!mlsfSmsTarget || !mlsfSmsTarget.phone) return;

        // The quick-action card is replaced by the compose box rather than stacked
        // behind it — two dimmed overlays read as a stuck screen.
        closeMlsfPhoneActions();

        const modal = document.getElementById('mlsfSmsComposeModal');
        const field = document.getElementById('mlsfSmsComposeText');
        const subtitle = document.getElementById('mlsfSmsComposeSubtitle');
        if (!modal || !field) return;

        // Start from the wording currently configured in Bulk SMS Management,
        // rather than an empty box that every officer has to type from scratch.
        field.value = '';
        field.disabled = false;
        if (subtitle) subtitle.textContent = mlsfSmsTarget.who || mlsfSmsTarget.phone;
        mlsfSmsNotice(
            mlsfLooksLikeMobile(mlsfSmsTarget.phone)
                ? ''
                : `${mlsfSmsTarget.phone} does not look like a Nigerian mobile number. The gateway will accept and bill it, but nothing will arrive. Check the number on the file before sending.`,
            'warn'
        );
        updateMlsfSmsCount();

        fetch('{{ route('file-numbers.contact-sms-template', [], false) }}?file_number=' + encodeURIComponent(mlsfSmsTarget.fileNo || '')
            + '&application_type=' + encodeURIComponent('File Commissioning'), { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => {
                if (data && data.success && typeof data.message === 'string') {
                    field.value = data.message;
                    updateMlsfSmsCount();
                }
            })
            .catch(() => {});

        modal.classList.remove('hidden');
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
        setTimeout(() => field.focus(), 50);
    }

    function closeMlsfSmsCompose() {
        document.getElementById('mlsfSmsComposeModal')?.classList.add('hidden');
    }

    async function sendMlsfSms() {
        const field = document.getElementById('mlsfSmsComposeText');
        const send = document.getElementById('mlsfSmsComposeSend');
        if (!field || !mlsfSmsTarget) return;

        const message = field.value.trim();
        if (!message) {
            mlsfSmsNotice('Type a message before sending.', 'error');
            return;
        }

        // Locked while the request is in flight. The gateway allows up to 40 seconds,
        // and a second click would send — and bill — the message twice.
        const original = send ? send.innerHTML : '';
        if (send) {
            send.disabled = true;
            send.innerHTML = '<i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i><span>Sending...</span>';
            if (window.lucide) window.lucide.createIcons();
        }
        field.disabled = true;
        mlsfSmsNotice('');

        try {
            const response = await fetch('{{ route('file-numbers.send-sms', [], false) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    phone: mlsfSmsTarget.phone,
                    message: message,
                    file_number: mlsfSmsTarget.fileNo || null,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (data.success) {
                closeMlsfSmsCompose();
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Message sent',
                        // The gateway's own caveat, not a flourish: this account is on
                        // the promotional route, so "accepted" is not "delivered".
                        text: data.message || 'Accepted by the gateway.',
                        confirmButtonColor: '#0284c7',
                    });
                }
                return;
            }

            mlsfSmsNotice(data.message || 'The message could not be sent.', 'error');
        } catch (e) {
            console.error('Error sending SMS:', e);
            mlsfSmsNotice('The message could not be sent — the server did not answer.', 'error');
        } finally {
            field.disabled = false;
            if (send) {
                send.innerHTML = original;
                send.disabled = false;
                if (window.lucide) window.lucide.createIcons();
            }
            updateMlsfSmsCount();
        }
    }

    $(document).on('input', '#mlsfSmsComposeText', updateMlsfSmsCount);

    window.openMlsfPhoneActions = openMlsfPhoneActions;
    window.closeMlsfPhoneActions = closeMlsfPhoneActions;
    window.openMlsfSmsCompose = openMlsfSmsCompose;
    window.closeMlsfSmsCompose = closeMlsfSmsCompose;
    window.sendMlsfSms = sendMlsfSms;

    $(document).on('click', '#mlsfTable tbody .mlsf-phone-btn', function () {
        const rowData = table ? table.row($(this).closest('tr')).data() : null;
        if (rowData) openMlsfPhoneActions(rowData);
    });

    // ── Related File Numbers card ────────────────────────────────────────────────
    // The full list already travels with the row (FileNumberController reads the
    // related_file_number register once per page), so opening the card costs no
    // round-trip.
    function escapeMlsfHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // One reading of "what is this file related to", used by both the cell renderer
    // and the card. The server sends related_files -- the full related_file_number
    // register for the row -- but a row whose register read failed, or a source that
    // has no register rows at all (temporary numbers, plot extensions), still carries
    // the legacy related_old_fileno string, so that is the fallback rather than a
    // silently empty list.
    function mlsfRelatedFilesFor(row) {
        if (!row) return [];

        const list = Array.isArray(row.related_files) ? row.related_files : [];
        if (list.length) {
            return list.filter(e => e && String(e.file_no || '').trim() !== '');
        }

        const relOld = row.related_old_fileno;
        if (!relOld || !relOld.kind || relOld.kind === 'none' || !relOld.value || relOld.value === 'N/A') {
            return [];
        }

        // Written as a comma-joined string by formatRelatedOrOldFileNo().
        const type = relOld.kind === 'old' ? 'Old File Number' : 'Related File';
        return String(relOld.value)
            .split(',')
            .map(v => v.trim())
            .filter(v => v !== '')
            .map(v => ({ file_no: v, type: type }));
    }

    function openMlsfRelatedFilesModal(fileNumber, relatedFiles) {
        const modal = document.getElementById('mlsfRelatedFilesModal');
        const body = document.getElementById('mlsfRelatedFilesBody');
        const subtitle = document.getElementById('mlsfRelatedFilesSubtitle');
        if (!modal || !body) return;

        const rows = Array.isArray(relatedFiles) ? relatedFiles : [];
        if (subtitle) {
            subtitle.textContent = `${String(fileNumber || '').toUpperCase()} — ${rows.length} related file number${rows.length === 1 ? '' : 's'}`;
        }

        body.innerHTML = rows.length
            ? rows.map((entry, index) => `
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-xs text-gray-400">${index + 1}</td>
                    <td class="px-5 py-2.5 font-mono text-sm font-bold text-sky-700">${escapeMlsfHtml(String(entry.file_no || '').toUpperCase())}${
                        String(entry.alias || '').trim()
                            ? `<div class="font-normal text-[11px] text-sky-600/80" title="Same file, KANGIS number">${escapeMlsfHtml(String(entry.alias).toUpperCase())}</div>`
                            : ''
                    }</td>
                    <td class="px-5 py-2.5 text-sm text-gray-700">${
                        entry.title
                            ? escapeMlsfHtml(String(entry.title).toUpperCase())
                            : '<span class="text-gray-300">&mdash;</span>'
                    }</td>
                    <td class="px-5 py-2.5">
                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600">
                            ${escapeMlsfHtml(entry.type || 'Related File')}
                        </span>
                    </td>
                </tr>`).join('')
            : '<tr><td colspan="4" class="px-5 py-8 text-center text-sm italic text-gray-400">No related file numbers recorded for this file.</td></tr>';

        modal.classList.remove('hidden');
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function closeMlsfRelatedFilesModal() {
        document.getElementById('mlsfRelatedFilesModal')?.classList.add('hidden');
    }

    window.openMlsfRelatedFilesModal = openMlsfRelatedFilesModal;
    window.closeMlsfRelatedFilesModal = closeMlsfRelatedFilesModal;

    $(document).on('click', '#mlsfTable tbody .mlsf-related-files-btn', function () {
        const rowData = table ? table.row($(this).closest('tr')).data() : null;
        if (!rowData) return;
        openMlsfRelatedFilesModal(rowData.mlsfNo || rowData.primaryFileNo, mlsfRelatedFilesFor(rowData));
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMlsfMapModal();
            closeMlsfRelatedFilesModal();
            closeMlsfPhoneActions();
            closeMlsfSmsCompose();
        }
    });

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
                                <div style="font-size:11px;font-weight:600;white-space:nowrap;${subColor}">${subLabel}</div>
                                <div style="font-size:12px;font-weight:700;color:#0369a1;white-space:nowrap">Occupancy Permit (OP)</div>
                            </div>`;
                        }
                        // source is only the Application Type (Direct Allocation /
                        // Conversion); the File Type picked beside it (Re-grant,
                        // Subdivision, ...) lives in file_option and, being the more
                        // specific of the two, is what the badge shows when there is one.
                        const fileTypeLabels = {
                            temporary: 'Temporary', extension: 'Extension', miscellaneous: 'Miscellaneous',
                            change_of_purpose: 'Change of Purpose', resettlement: 'Resettlement',
                            regrant: 'Re-grant', reissuance: 'Re-Issuance', recertification: 'Recertification',
                            subdivision: 'Subdivision', merger: 'Merger', separation: 'Separation', duplex: 'Duplex'
                        };
                        const fileOpt = String(row.file_option || '').trim().toLowerCase();
                        const fileTypeLabel = fileTypeLabels[fileOpt] || '';
                        const badgeValue = fileTypeLabel || sourceValue;
                        if (type !== 'display') return badgeValue;
                        return badgeValue !== 'N/A'
                            ? `<span style="display:inline-flex;align-items:center;padding:1px 8px;border-radius:9999px;font-size:12px;font-weight:600;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8">${badgeValue}</span>`
                            : 'N/A';
                    }
                },
                {
                    // The applicant's photograph, filed at commissioning and replaceable
                    // from the Edit modal. Resolved server-side from the scannings/EDMS
                    // record, primed one page at a time rather than one query per row.
                    data: 'passport_url',
                    name: 'passport_url',
                    title: 'Passport',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    defaultContent: '',
                    render: function (data, type, row) {
                        // Sorting and the CSV/PDF exports want a word, not an <img>.
                        if (type !== 'display') return data ? 'Yes' : 'No';

                        if (!data) {
                            return '<span class="inline-flex items-center justify-center w-9 h-11 rounded border border-dashed border-gray-300 bg-gray-50 text-gray-300" title="No passport on record"><i data-lucide="user" class="w-4 h-4"></i></span>';
                        }

                        const title = String(row.FileName || '').replace(/"/g, '&quot;');
                        return `<img src="${data}" alt="Passport" title="${title}"
                                     loading="lazy"
                                     onerror="this.outerHTML='<span class=\'text-gray-300 text-[10px]\'>&mdash;</span>'"
                                     class="w-9 h-11 object-cover rounded border border-gray-200 mx-auto cursor-zoom-in"
                                     onclick="openMlsfPassportPreview('${data}', '${title}')">`;
                    }
                },
                {
                    data: 'mlsfNo',
                    name: 'mlsfNo',
                    title: 'MLPP File No',
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

                        // A file the registry has retired is still listed — the row is the
                        // record of its issue — but printed like a live one it reads as a
                        // number that still answers. Struck through, with what replaced it
                        // directly beneath, the same way the batch modal shows it.
                        const sup = row.superseded || null;

                        let html;
                        if (isRange) {
                            html = `<div class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-indigo-100 text-indigo-800 border border-indigo-200">
                                ${display}
                            </div>`;
                        } else if (sup) {
                            html = `<div class="font-bold text-gray-400 line-through">${display}</div>`;
                        } else {
                            html = `<div class="font-bold text-gray-900">${display}</div>`;
                        }

                        if (sup) {
                            const more = sup.count > 1
                                ? ` <span class="text-[10px] font-bold text-sky-500">+${sup.count - 1} more</span>`
                                : '';

                            html += sup.successor
                                ? `<div class="mt-1 text-[11px] whitespace-nowrap">
                                       <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-700 border border-sky-200 mr-1">REL</span>
                                       <span class="font-semibold text-sky-700">${String(sup.successor).toUpperCase().replace(/</g, '&lt;')}</span>${more}
                                   </div>`
                                : `<div class="mt-1 text-[11px]">
                                       <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-500 border border-gray-200">RETIRED</span>
                                   </div>`;

                            if (sup.reason) {
                                html += `<div class="mt-0.5 text-[10px] text-gray-400">${String(sup.reason).replace(/</g, '&lt;')}</div>`;
                            }
                        }
                        // The related / old file numbers belong to this file number, so
                        // they sit directly beneath it. The badge says which kind it is —
                        // amber OLD for a previous number ("Old File Number" ticked on the
                        // Edit modal, stored on mls_file_no.old_fileno), sky REL for a
                        // related one — rather than blurring the two together.
                        // Every related number recorded for this file, primary first.
                        // mlsfRelatedFilesFor() merges the register with the two legacy
                        // columns; the click handler calls the same function, so the card
                        // always lists exactly what the count promises.
                        const relOld = row.related_old_fileno;
                        const relatedList = mlsfRelatedFilesFor(row);
                        if (relatedList.length) {
                            // The number carries its badge's colour so the two read as one
                            // label: amber for a previous (old) number, sky for a related
                            // one. Grey text next to a coloured badge made the value look
                            // like an afterthought rather than part of the same fact.
                            const isOld = (relOld && relOld.kind === 'old')
                                || String(relatedList[0].type || '').toLowerCase() === 'old file number';
                            const relBadge = isOld
                                ? '<span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-700 border border-amber-200 mr-1">OLD</span>'
                                : '<span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-700 border border-sky-200 mr-1">REL</span>';
                            const relValueClass = isOld ? 'text-amber-700' : 'text-sky-700';

                            // A recertification is not its own Root of Title — the file it
                            // was raised from is — so the marker rides on this number
                            // rather than under the title. The server decides which.
                            const rotRef = String(row.root_of_title_ref || '').trim().toUpperCase();

                            // Only ONE number is printed in the cell. A file can carry many
                            // — a merger quotes every source file, 30 is not unusual — and
                            // printing them all pushed the column onto that many lines. When
                            // one of them is the Root of Title that is the one worth showing;
                            // otherwise the first.
                            const headline = (rotRef && relatedList.find(e => String(e.file_no).trim().toUpperCase() === rotRef))
                                || relatedList[0];
                            const headlineNo = String(headline.file_no).trim().toUpperCase();
                            const rootMark = (rotRef && headlineNo === rotRef)
                                ? ' <span class="font-semibold text-violet-700" title="Root of Title">-RoT</span>'
                                : '';

                            // A related file registered under both numbering systems is
                            // named by both, the way the commissioning sheet prints it
                            // ("COM-2016-327 (MLKN 1516)"). The alias is the same file,
                            // so it shares the line rather than counting as another
                            // related file. Blank for anything with no KANGIS pairing.
                            const headlineAlias = String(headline.alias || '').trim().toUpperCase();
                            const aliasMark = headlineAlias
                                ? ` <span class="font-semibold ${relValueClass} opacity-80" title="Same file, KANGIS number">(${headlineAlias.replace(/</g, '&lt;')})</span>`
                                : '';

                            html += `<div class="mt-1 text-[11px]">${relBadge}<span class="font-semibold ${relValueClass}">${headlineNo.replace(/</g, '&lt;')}</span>${aliasMark}${rootMark}</div>`;

                            // The rest open in a card rather than in the cell — the same
                            // idiom as the Related FileNo column on the Indexed Files table.
                            if (relatedList.length > 1) {
                                html += `<div class="mt-1">
                                    <button type="button" class="mlsf-related-files-btn inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-200 hover:bg-sky-600 hover:text-white hover:border-sky-600 transition-colors">
                                        <i data-lucide="link" class="w-3 h-3"></i>
                                        <span>View all ${relatedList.length} related</span>
                                    </button>
                                </div>`;
                            }
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

                        // An ST conversion commissions the CON land file (shown above) and
                        // its ST primary together — show the ST number stacked underneath.
                        const stFileNo = String(row.stFileNo || '').trim();
                        if (stFileNo && stFileNo !== 'N/A' && stFileNo !== display) {
                            html += `
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200" title="ST file number">
                                        ${stFileNo}
                                    </span>
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
                        const title = data ? String(data).toUpperCase() : 'N/A';

                        // Sorting and the CSV/PDF exports want the title alone.
                        if (type !== 'display') return title;

                        let html = `<div>${title.replace(/</g, '&lt;')}</div>`;

                        // Mirror Legal Search's compact "-RoT" marker directly beneath
                        // the title. The server applies the commissioning-source rule and
                        // also recognises a manually indexed Root of Title.
                        if (row.is_root_of_title) {
                            html += '<div class="mt-0.5 text-[11px] font-semibold text-violet-700">-RoT</div>';
                        }

                        // The applicant's phone sits under their name, the way the
                        // related/old number sits under the file number — it belongs to
                        // the person, not to a column of its own. Clicking it opens the
                        // Call / SMS / WhatsApp / Email card. Most files carry no number
                        // (it lives on file_indexings for the ones that do), and those
                        // simply show nothing rather than an empty-looking row.
                        const phone = String(row.phone_no || '').trim();
                        if (phone) {
                            html += `<div class="mt-1">
                                <button type="button" class="mlsf-phone-btn inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white hover:border-emerald-600">
                                    <i data-lucide="phone" class="h-3 w-3"></i>
                                    <span>${escapeMlsfHtml(phone)}</span>
                                </button>
                            </div>`;
                        }

                        return html;
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
                    data: 'house_no',
                    name: 'house_no',
                    title: 'House No',
                    defaultContent: 'N/A',
                    render: function (data, type, row) {
                        return data ? data.toUpperCase() : 'N/A';
                    }
                },
                {
                    data: 'street_name',
                    name: 'street_name',
                    title: 'Street Name',
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
                    data: 'district',
                    name: 'district',
                    title: 'District',
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
                        const label = data ? String(data).toUpperCase() : 'SYSTEM';

                        // Sorting, filtering and the CSV/PDF exports need the bare text.
                        if (type !== 'display' || !data) {
                            return label;
                        }

                        // created_by holds a user id on some rows and a typed name on others;
                        // the shared profile card (js/user-profile-card.js) resolves either.
                        const raw = String(data).trim();
                        const attr = /^\d+$/.test(raw)
                            ? 'data-user-id="' + raw + '"'
                            : 'data-user-name="' + raw.replace(/"/g, '&quot;') + '"';

                        return '<span class="upc-trigger" data-user-card ' + attr + ' title="View profile">' + label.replace(/</g, '&lt;') + '</span>';
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
                // Longitude / Latitude come from file_indexings — the map pin this page
                // saves on Generate lands there, not on fileNumber or mls_file_no. Most
                // legacy files were never pinned, so N/A is the normal reading.
                {
                    data: 'longitude',
                    name: 'longitude',
                    title: 'Longitude',
                    defaultContent: 'N/A',
                    searchable: false,
                    render: function (data, type, row) {
                        if (!data || data === 'N/A') {
                            return '<span class="text-gray-400">N/A</span>';
                        }
                        // Plain value — the Map column owns opening the location.
                        return `<span class="font-mono text-xs text-gray-700">${data}</span>`;
                    }
                },
                {
                    data: 'latitude',
                    name: 'latitude',
                    title: 'Latitude',
                    defaultContent: 'N/A',
                    searchable: false,
                    render: function (data, type, row) {
                        if (!data || data === 'N/A') {
                            return '<span class="text-gray-400">N/A</span>';
                        }
                        return `<span class="font-mono text-xs text-gray-700">${data}</span>`;
                    }
                },
                {
                    data: null,
                    name: 'map',
                    title: 'Map',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        const lat = row.latitude && row.latitude !== 'N/A' ? row.latitude : '';
                        const lon = row.longitude && row.longitude !== 'N/A' ? row.longitude : '';
                        if (!lat || !lon) {
                            return '<span class="text-gray-300" title="No coordinates pinned on this file">&mdash;</span>';
                        }
                        const label = String(row.mlsfNo || row.primaryFileNo || '').replace(/"/g, '&quot;');
                        const title = String(row.FileName || '').replace(/"/g, '&quot;');
                        return `<button type="button"
                                    onclick="openMlsfMapModal('${lat}', '${lon}', '${label}', '${title}')"
                                    class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-indigo-50 px-3 py-1.5 text-[11px] font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                    <span>View Map</span>
                                </button>`;
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
                        const batchCount = parseInt(row.batch_count, 10) || 1;

                        // This list is a UNION of three tables whose ids collide. Temporary
                        // rows carry an mls_file_no id and Plot Extension rows a
                        // plot_extensions id, so the fileNumber-keyed operations below are
                        // not merely unsupported for them — they resolve to a DIFFERENT
                        // file. Edit knows how to route itself; Delete and Update Allocation
                        // do not, so they are withheld. (The server refuses them too, for
                        // pages cached before this shipped.)
                        const rowEntity = mlsfRowEntity(row.type);
                        const isFileNumberRow = rowEntity === 'file_number';

                        // Escaped for the onclick attributes below: a file number is
                        // free text and an apostrophe would close the handler early.
                        const moveFileNo = String(row.mlsfNo || '').replace(/&/g, '&amp;').replace(/'/g, '&#39;');

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
                                        <span class="font-medium">Edit${batchCount > 1 ? ` <span class="text-[10px] font-semibold text-amber-700">(batch of ${batchCount})</span>` : ''}</span>
                                    </button>

                                    ${isFileNumberRow ? `
                                    <!-- Direct Allocation -->
                                    <button onclick="directAllocation(${row.id}, '${row.type || ''}')"
                                            class="w-full text-left px-4 py-2.5 text-sm text-green-700 hover:bg-green-50 flex items-center space-x-3">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-green-500"></i>
                                        <span class="font-medium">Update Allocation data</span>
                                    </button>
                                    ` : ''}

                                    @if(Auth::user() && Auth::user()->assign_role === 'Supper Admin')
                                    ${isFileNumberRow ? `
                                    <!-- Re-file a wrongly commissioned file under the OSS registry -->
                                    <button onclick="moveCommissioningRegistry(event, '${moveFileNo}', 'OSS')"
                                            class="w-full text-left px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 flex items-center space-x-3">
                                        <i data-lucide="arrow-right-left" class="w-4 h-4 text-blue-500"></i>
                                        <span class="font-medium">Move to OSS File Commissioning</span>
                                    </button>
                                    ` : ''}
                                    @endif

                                    <div class="border-t border-gray-100 my-1"></div>

                                    <!-- Delete Record -->
                                    @if(Auth::user() && Auth::user()->assign_role === 'Supper Admin')
                                    ${isFileNumberRow ? `
                                    <button onclick="deleteRecord(${row.id}, '${row.type || ''}', '${batchNo}', ${batchCount})"
                                            class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center space-x-3">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        <span class="font-medium">Delete Record${batchCount > 1 ? ` (${batchCount} files)` : ''}</span>
                                    </button>
                                    ` : `
                                    <div class="px-4 py-2.5 text-xs text-gray-400 italic">
                                        ${row.type || 'This record'} cannot be master-deleted here
                                    </div>
                                    `}
                                    @endif
                                </div>
                            </div>
                            </div>
                        `;
                    }
                }
            ]; if (window.MLSF_IS_ADMIN) cols.unshift(mlsfCheckboxColumn); return cols; })(),
            // Default sort: newest commissioning first. Resolved from the header text
            // rather than a fixed index — DataTables 1.10 cannot order by column name, and
            // a hard-coded index silently sorts the wrong column the moment the table is
            // reordered (which is exactly what happened when the coordinate columns moved).
            // The fallback indexes match the current layout, with/without the admin
            // checkbox column that the thead renders conditionally.
            order: [[(function () {
                const headers = Array.from(document.querySelectorAll('#mlsfTable thead th'));
                const idx = headers.findIndex(th => th.textContent.trim() === 'Time Commissioned');
                return idx >= 0 ? idx : (window.MLSF_IS_ADMIN ? 15 : 14);
            })(), 'desc']],
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
                emptyTable: '<div class="text-center py-8"><div class="text-gray-400 mb-2"><i data-lucide="database" class="w-12 h-12 mx-auto mb-2"></i></div><h3 class="text-lg font-medium text-gray-900 mb-1">No file numbers found</h3><p class="text-gray-500">Start by generating your first MLPP file number using the button above.</p></div>',
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

                // Opening a source OP does not imply that a Transfer of Title exists.
                // Show the second panel only when the source explicitly identifies one.
                const hasActualTransferOfTitle = /transfer\s+of\s+title/i.test(
                    String(row.linked_transaction_type || row.transaction_type || '')
                );

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
                        <div class="${hasActualTransferOfTitle ? 'grid grid-cols-1 md:grid-cols-2 gap-4' : 'grid grid-cols-1 gap-4'}">
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
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4" style="${hasActualTransferOfTitle ? '' : 'display:none'}">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-600 text-white font-extrabold text-sm">2</span>
                                    <span class="text-xs font-extrabold uppercase tracking-widest text-emerald-700">Transfer of Title</span>
                                </div>
                                <div class="mb-3 flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <div><div class="text-[10px] text-slate-400">New MLPP File No</div><div class="font-mono font-extrabold text-emerald-800 text-sm">${esc(newMlsFilenoLabel || row.mlsFNo)}</div></div>
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
                    // Keep the heading about the source OP only. The commissioned
                    // file is context, not evidence of a Transfer of Title.
                    title: `Occupancy Permit (OP) Details (${esc(tempFilenoLabel || row.temp_fileno || '—')})`,
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
            if (typeof component.clearPassport === 'function') {
                component.clearPassport();
            } else {
                component.passport = '';
            }
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
            component.relatedFileTitleLocked = false;
            component.relatedFileIndexingId = '';
            component.isRecertificationPrefix = false;
            component.recertificationWorkflow = false;

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

    // ----- Extension helpers -----
    // An extension is marked on the PLOT number ("463 & EXTENSION"), which is always
    // applied. The file number suffix (" AND EXTENSION") is optional: ticking the
    // "keep file number as-is" checkbox in the extension panel drops it.
    const EXTENSION_PLOT_SUFFIX = ' & EXTENSION';

    function stripExtensionPlotSuffix(value) {
        return (value || '').toString().replace(/\s*&\s*EXTENSION\s*$/i, '').trim();
    }

    function withExtensionPlotSuffix(value) {
        const base = stripExtensionPlotSuffix(value);
        return base ? base + EXTENSION_PLOT_SUFFIX : '';
    }

    // Builds the extension file number preview, honouring the "keep as-is" checkbox.
    function buildExtensionPreview(baseFileNo, suppressSuffix) {
        const base = (baseFileNo || '').toString().trim().replace(/-$/, '');
        if (!base) return '';
        return suppressSuffix ? base : base + ' AND EXTENSION';
    }

    // Separate function for just updating preview without form manipulation
    function updatePreviewOnly() {
        const serialNo = document.getElementById('serialNo')?.value;
        const year = document.getElementById('year')?.value;
        const landUse = document.getElementById('landUse')?.value;
        const fileOption = document.getElementById('fileOption')?.value;

        // Get existing file number from Alpine.js component instead of DOM
        let existingFileNo = '';
        let suppressExtensionSuffix = false;
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            existingFileNo = modalContainer._x_dataStack[0].existingFileNo;
            suppressExtensionSuffix = !!modalContainer._x_dataStack[0].suppressExtensionSuffix;
        }

        const middlePrefix = document.getElementById('middlePrefix')?.value || '';
        const preview = document.getElementById('mlsfPreview');

        // Preview generation logic only
        let previewText = '-';

        if (fileOption === 'extension' && existingFileNo) {
            previewText = buildExtensionPreview(existingFileNo, suppressExtensionSuffix);
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

    /**
     * Is a reclaimed (missing) serial currently being held?
     *
     * Several code paths auto-fill the serial field from the counter — updatePreview(),
     * updateGenerateForm() and updateAlpineSerialNumber() all write to it — and each one
     * would silently undo a deliberate pick. They all consult this first.
     */
    window.mlsfHoldingReclaimedSerial = function () {
        const modal = document.querySelector('[x-data="fileNumberGenerator()"]');
        const data = modal && modal._x_dataStack && modal._x_dataStack[0];
        return !!(data && data.useReclaimedSerial && data.reclaimedSerial);
    };

    async function updatePreview() {
        const serialNoField = document.getElementById('serialNo');
        const year = document.getElementById('year')?.value;
        const landUse = document.getElementById('landUse')?.value;
        const prefix = document.getElementById('prefix')?.value; // Get Prefix
        const fileOption = document.getElementById('fileOption')?.value;
        let serialNo = serialNoField?.value;

        // Get existing file number from Alpine.js component instead of DOM
        let existingFileNo = '';
        let suppressExtensionSuffix = false;
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
            existingFileNo = modalContainer._x_dataStack[0].existingFileNo;
            suppressExtensionSuffix = !!modalContainer._x_dataStack[0].suppressExtensionSuffix;
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

        if ((fileOption === 'normal' || fileOption === 'regrant' || fileOption === 'resettlement') && hasCode && year && !isOverrideMode
            && !window.mlsfHoldingReclaimedSerial()) {
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
            previewText = buildExtensionPreview(existingFileNo || baseFileNumber, suppressExtensionSuffix) || '-';
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
            if (!window.mlsfHoldingReclaimedSerial()) {
                serialNoField.value = nextSerialNo;
            }

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
                serialNoField.placeholder = 'Enter Old MLPP number (e.g., 5467, 34874857488758)';
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
        // Returns the fetch so a caller can wait for the map before reading it. The
        // duplex plan's serial preview is computed FROM serialNumbersMap, so refreshing
        // the plan before the map has landed would just re-render the old numbers.
        return fetch('{{ route("mls-fileno.serial-status") }}')
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
        if (serialNoElement && nextSerialNo && !isOverrideMode && !window.mlsfHoldingReclaimedSerial()) {
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

    // OP Smart Detector: a Land OP keeps the allottee as the file title, while
    // an OSS OP is a Change of Ownership and therefore has a different title.
    // The server repeats this check so it cannot be bypassed, but doing it here
    // gives the officer a clear answer before the generation request is sent.
    function normaliseOpCommissioningName(name) {
        return String(name || '')
            .toUpperCase()
            .replace(/\b(ALHAJI|ALH|HAJIYA|HAJ|MALLAM|MAL|DR|MR|MRS|MISS|CHIEF|HON)\.?\b/g, '')
            .replace(/[^A-Z0-9]+/g, '');
    }

    function getOpCommissioningFlowIssue(formData, isOssCommissioningFlow) {
        // The OP context lives on the Alpine component; the hidden inputs are only a
        // mirror of it. Read the form first, then fall back to the component itself,
        // so a page whose form is missing one of those inputs cannot silently disable
        // the detector — which is exactly how the inline MLS form behaved: no
        // source_* inputs at all, so there was never an allottee to compare.
        const opContext = (() => {
            const el = document.querySelector('[x-data="fileNumberGenerator()"]');
            return (el && el._x_dataStack && el._x_dataStack[0]) || {};
        })();
        const lastOp = window.lastCommissionSourceOp || {};

        const pick = (formKey, ...fallbacks) => {
            const fromForm = String(formData.get(formKey) || '').trim();
            if (fromForm) return fromForm;
            for (const value of fallbacks) {
                const candidate = String(value || '').trim();
                if (candidate) return candidate;
            }
            return '';
        };

        const sourceId = pick('source_instrument_capture_id', opContext.sourceInstrumentCaptureId, lastOp.id);
        const sourcePraId = pick('source_pra_id', opContext.sourcePraId, lastOp.source_pra_id);
        const allottee = pick('source_original_owner', opContext.sourceOriginalOwner, lastOp.original_owner);
        const fileTitle = pick('file_name', opContext.fileName);

        // Do not classify an ordinary direct allocation. The backend will still
        // inspect a linked OP if a stale browser does not have its allottee name.
        if ((!sourceId && !sourcePraId) || !allottee || !fileTitle) return null;

        const allotteeKey = normaliseOpCommissioningName(allottee);
        const fileTitleKey = normaliseOpCommissioningName(fileTitle);
        if (!allotteeKey || !fileTitleKey) return null;

        const isSameName = allotteeKey === fileTitleKey;

        if (!isOssCommissioningFlow && !isSameName) {
            return {
                title: 'OSS Change of Ownership Detected',
                text: 'The file title differs from the OP allottee (' + allottee + '). This OP must be processed in OSS File Commissioning.'
            };
        }

        if (isOssCommissioningFlow && isSameName) {
            return {
                title: 'Land OP Detected',
                text: 'The file title matches the OP allottee (' + allottee + '), so no Change of Ownership was detected. Process this OP in Land File Commissioning.'
            };
        }

        return null;
    }

    /**
     * Run the detector as soon as the File Name is entered, not only on Generate.
     *
     * The submit-time check is the one that actually refuses the commissioning, and
     * it stays. But an officer who types a new owner's name into a Land OP has
     * already made the decision by then, and only finds out after filling the rest
     * of the card — and if they never press Generate (closing the modal, or moving
     * on), nothing ever tells them. Telling them at the point of entry is both
     * earlier and where the mistake is.
     *
     * Bound to 'change', so it fires when the field is left after an edit — never
     * per keystroke, and never for a value Alpine wrote programmatically during the
     * OP hand-off (assigning .value does not dispatch a change event).
     */
    let lastOpFlowWarningFor = null;

    function runLiveOpFlowCheck() {
        const input = document.getElementById('fileName');
        if (!input) return;

        const form = input.form || document.getElementById('generateForm');
        if (!form) return;

        const typed = String(input.value || '').trim();
        if (!typed || typed === lastOpFlowWarningFor) return;

        const currentUrl = new URL(window.location.href);
        const isOssCommissioningFlow =
            currentUrl.pathname.includes('/lands-one-stop-shop/') ||
            currentUrl.searchParams.get('source') === 'lands-one-stop-shop' ||
            currentUrl.searchParams.get('type') === 'change-of-name';

        const issue = getOpCommissioningFlowIssue(new FormData(form), isOssCommissioningFlow);
        if (!issue) {
            // Corrected: allow the same warning again if they go back to a bad name.
            lastOpFlowWarningFor = null;
            return;
        }

        // Only once per distinct name, or every tab-out through the field nags.
        lastOpFlowWarningFor = typed;

        Swal.fire({
            icon: 'warning',
            title: issue.title,
            text: issue.text,
            confirmButtonText: 'Review file title',
            confirmButtonColor: '#f59e0b'
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'fileName') {
            runLiveOpFlowCheck();
        }
    });

    function submitForm(event) {
        event.preventDefault();

        // Check for batch mode
        const modalContainer = document.querySelector('[x-data="fileNumberGenerator()"]');
        let alpineData = null;
        if (modalContainer && modalContainer._x_dataStack) {
            alpineData = modalContainer._x_dataStack[0];

            // A duplex is several commissionings in a declared order, so it runs
            // through its own committer rather than this form's single/batch paths.
            if (alpineData.fileOption === 'duplex') {
                window.commissionDuplexFromModal(alpineData);
                return;
            }

            // A Change of Purpose over several plots is one file becoming many, with
            // the source retired once. That is the batch engine's shape, not this
            // form's single path, so it runs through ChangeOfPurposeBatchCommitService.
            if (alpineData.applicationType === 'change_of_purpose'
                && alpineData.copAppId
                && Number(alpineData.copPlotCount) > 1) {
                window.commissionCopPlotsFromModal(alpineData);
                return;
            }

            // If batch mode is active, show summary modal instead
            if (alpineData.batchMode) {
                showBatchSummaryModal(alpineData);
                return;
            }
        }

        // Applicant phone. The inline onsubmit="" runs before the shared helper's
        // own submit listener, so nigerian-phone.js cannot stop this form on its
        // own — ask it here. Placed AFTER the batch-mode return above: a batch is
        // validated per entry on its own summary modal, not here.
        const applicantPhone = document.getElementById('generatePhoneNo');
        if (applicantPhone && window.NigerianPhone && !window.NigerianPhone.validateField(applicantPhone, false)) {
            Swal.fire({
                icon: 'warning',
                title: 'Phone No of Applicant Required',
                text: 'Enter an 11-digit phone number starting with 0, such as 08012345678.',
                confirmButtonColor: '#f59e0b'
            });
            applicantPhone.focus();
            return;
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

        // Related-file inputs are hidden for workflows that provide their own source
        // selector (notably subdivision). A stale -RC prefix must never validate a
        // hidden native form control and prevent Generate from running.
        const recertificationNeedsRelatedFile = alpineData
            && alpineData.isRecertificationPrefix
            && !['subdivision', 'merger', 'separation', 'temporary', 'reissuance'].includes(String(alpineData.fileOption || ''))
            && alpineData.applicationType !== 'change_of_purpose';
        if (recertificationNeedsRelatedFile) {
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

        // A Re-grant replaces one specific existing file, so it cannot be commissioned without
        // naming it. The panel marks both fields required, but the file-number input is
        // readonly (it is filled by the picker, not typed), and a readonly control is barred
        // from HTML5 constraint validation — so the native `required` never fires and this is
        // what actually stops it. The server refuses it too; this only saves the round trip.
        if (alpineData && alpineData.isRegrantOption) {
            const motherFile = (alpineData.relatedFileNo || '').toString().trim();
            const motherTitle = (alpineData.relatedFileTitle || '').toString().trim();
            if (!motherFile) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Mother File Required',
                    text: 'Please select the Mother File that this Re-grant replaces.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
            if (!motherTitle) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Mother File Title Required',
                    text: 'Please enter the file title for the selected Mother File.',
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

        // Passport applies to Individual single-file submissions only — Corporate,
        // Multiple and Government files have no single applicant to photograph, so the
        // field is hidden and unvalidated for them. It is an image now, so presence is
        // read off the file input rather than a typed value.
        {
            const customerTypeValue = (alpineData && alpineData.customerType
                ? alpineData.customerType
                : (document.getElementById('customerType')?.value || '')).toString().trim();
            const passportInput = document.getElementById('generatePassport');
            // This script is shared with commissioning cards that carry no passport field
            // at all (the OSS FC / FEFR change-of-ownership cards reuse the global
            // commission-fileno modal). Those must not be blocked by a control they
            // never render, so the requirement hangs off the field being present.
            // A passport is required for a single Individual commissioning. For a
            // subdivision batch it remains available to upload, but must not block
            // the entire batch when the existing records have no passport image.
            const isBatchSubmission = !!(alpineData && alpineData.batchMode);
            const requiresPassport = !!passportInput && !isBatchSubmission && customerTypeValue === 'Individual';
            const hasPassport = !!(passportInput && passportInput.files && passportInput.files.length > 0);

            if (requiresPassport && !hasPassport) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Passport Required',
                    text: 'Please upload the applicant\'s passport photograph before generating the file number.',
                    confirmButtonColor: '#f59e0b'
                }).then(() => {
                    if (passportInput) passportInput.focus();
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

        // Passport is an Individual-only field. The customer type can also be set
        // without the select firing its change handler (SIT forces Government,
        // inheritance sets it), so drop the image here too rather than trusting the
        // hidden field to be empty — otherwise a photo picked before switching type
        // still gets filed.
        {
            const customerTypeValue = (alpineData && alpineData.customerType
                ? alpineData.customerType
                : (document.getElementById('customerType')?.value || '')).toString().trim();
            if (customerTypeValue !== 'Individual') {
                formData.delete('passport');
            }
        }

        if (alpineData) {
            const currentCoords = alpineData.getCurrentEntryCoordinates
                ? alpineData.getCurrentEntryCoordinates()
                : { latitude: alpineData.latitude, longitude: alpineData.longitude };

            formData.set('latitude', currentCoords.latitude ?? '');
            formData.set('longitude', currentCoords.longitude ?? '');
        }

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

        // A serial reclaimed from the missing-numbers list. Sent only while the toggle is
        // on AND a number is actually selected, so an abandoned choice cannot leak into a
        // later submission; anything else falls through to the normal counter.
        formData.delete('reclaimed_serial');
        if (alpineData && alpineData.useReclaimedSerial && alpineData.reclaimedSerial
            && ['normal', 'regrant', 'resettlement'].includes(fileOption)) {
            formData.set('reclaimed_serial', alpineData.reclaimedSerial);
        }

        // Extension files: the plot number always carries the "& EXTENSION" marker, while
        // the " AND EXTENSION" suffix on the file number itself is opt-out. Both are set
        // here so a value inherited without passing through the input still posts correctly.
        if (fileOption === 'extension') {
            formData.set('plot_no', withExtensionPlotSuffix(formData.get('plot_no')));
            formData.set('suppress_extension_suffix', (alpineData && alpineData.suppressExtensionSuffix) ? '1' : '0');
        } else {
            formData.delete('suppress_extension_suffix');
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
            // Carried through so the old_file_numbers ledger row keeps the title the
            // old file was held under, not just the bare number.
            formData.set('old_fileno_title', (alpineData ? (alpineData.oldFileTitle || '') : '').toString().trim());
        } else {
            formData.delete('old_fileno');
            formData.delete('old_fileno_title');
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
            currentUrl.searchParams.get('source') === 'lands-one-stop-shop' ||
            currentUrl.searchParams.get('type') === 'change-of-name';

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

        const opFlowIssue = getOpCommissioningFlowIssue(formData, isOssCommissioningFlow);
        if (opFlowIssue) {
            hideGlobalLoading();
            if (submitBtn) {
                hideLoadingButton(submitBtn, originalText);
            }
            Swal.fire({
                icon: 'warning',
                title: opFlowIssue.title,
                text: opFlowIssue.text,
                confirmButtonText: 'Review file title',
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
            .then(parseGenerateResponse)
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
                                        {{-- Counterpart folios: the same file number's folder in the Cadastral
                                             and Physical Planning registries, where the file also physically
                                             sits. Listed one per registry with its own path — the operator
                                             walking to that registry needs the path, not a count. --}}
                                        ${data.edms_folder && data.edms_folder.folios ? Object.entries(data.edms_folder.folios).map(([folioRegistry, folio]) => folio && folio.path ? `
                                        <li class="flex items-start gap-2.5">
                                            <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                            <span>${folioRegistry} folio ${folio.existed ? 'already present' : 'created'}:
                                                <b class="font-black text-${summaryColor}-900 break-all">${folio.path}</b></span>
                                        </li>` : '').join('') : ''}
                                        {{-- Passport photograph filed into that folder and registered as a
                                             scan document, so the file shows up in Scan Upload / Page Typing. --}}
                                        ${data.passport_upload ? `
                                        <li class="flex items-start gap-2.5">
                                            <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                            <span>${data.passport_upload.stored
                                                ? `Passport photograph filed${data.passport_upload.scanning_id ? ' and listed under Scan Upload / Page Typing' : ''}: <b class="font-black text-${summaryColor}-900 break-all">${data.passport_upload.path}</b>`
                                                : 'Passport photograph could not be saved — please upload it from Scan Upload.'}</span>
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
                        .then(parseGenerateResponse)
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
                        .catch(error => {
                            hideGlobalLoading();
                            if (submitBtn) hideLoadingButton(submitBtn, originalText);
                            console.error('Generate (override retry) failed:', error, error && error.mlsBody);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: describeGenerateFailure(error),
                                confirmButtonColor: '#ef4444'
                            });
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
                // The body is logged as well as shown: describeGenerateFailure keeps
                // the dialog short, while the console keeps whatever the server
                // actually sent for whoever reads the report afterwards.
                if (error && error.mlsBody) {
                    console.error('Generate response body (first 400 bytes):', error.mlsBody);
                }
                Swal.fire(generateFailureDialog(
                    error,
                    isTimeout
                        ? 'Generation timed out on the server. Please retry. If it repeats, we need to optimize the backend generate flow.'
                        : null
                ));
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

        await loadSummaryReceivingOfficers();
    }

    // The officers to choose from. Same directory the File Tracking screen uses
    // (MLPP staff plus the other_receiving_officers list), so a name picked here is
    // the same name that screen would show. The list is not scoped by office —
    // nothing maps officers to offices — so every officer is offered.
    //
    // 832 names is far too many for a plain <select>, so it is a searchable Select2
    // with an "+ Add Other Officer..." escape hatch, exactly as /create-file-tracker
    // does it — same sentinel option, same matcher, same create endpoint.
    let summaryOfficerCache = null;
    const SUMMARY_ADD_OTHER_OFFICER = '__add_other_officer__';

    async function loadSummaryReceivingOfficers() {
        const select = document.getElementById('summaryReceivingOfficer');
        if (!select) return;

        if (!summaryOfficerCache) {
            try {
                const res = await fetch('/api/file-trackings/assignment/options', { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                summaryOfficerCache = (json && json.success && json.data && Array.isArray(json.data.users))
                    ? json.data.users
                    : [];
            } catch (e) {
                console.warn('[Commissioning] Could not load receiving officers', e);
                summaryOfficerCache = [];
            }
        }

        renderSummaryOfficerOptions(select.value);
    }

    function renderSummaryOfficerOptions(selectedValue = '') {
        const select = document.getElementById('summaryReceivingOfficer');
        if (!select) return;

        const officers = (summaryOfficerCache || []).filter(u => u && u.name);
        const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');

        const options = ['<option value=""></option>'];
        officers.forEach(u => {
            const name = String(u.name);
            options.push(
                `<option value="${esc(u.id)}" data-name="${esc(name)}" data-search-term="${esc(name.toLowerCase())}">${esc(name)}</option>`
            );
        });
        // Always offered, even when the directory failed to load — typing the name by
        // hand is exactly what this is for.
        options.push(`<option value="${SUMMARY_ADD_OTHER_OFFICER}" data-search-term="other add new">+ Add Other Officer...</option>`);

        select.innerHTML = options.join('');
        select.value = selectedValue || '';

        if (!(window.jQuery && $.fn.select2)) return;

        const $select = $(select);
        if ($select.data('select2')) {
            $select.select2('destroy');
        }

        // The card sits inside the Generation Summary modal; without a dropdownParent
        // inside it, the Select2 panel is clipped by the modal's own scroll box.
        const parent = $select.closest('.bg-white.border, [id$="Modal"]');

        $select.select2({
            placeholder: officers.length ? 'Search and select receiving officer' : 'Directory unavailable — add the officer',
            allowClear: true,
            width: '100%',
            dropdownParent: parent && parent.length ? parent : undefined,
            matcher: function (params, data) {
                if (!params.term || !String(params.term).trim()) return data;
                if (!data.element) return null;
                const term = String(params.term).toLowerCase();
                const tokens = (data.element.getAttribute('data-search-term') || '').toLowerCase();
                return tokens.includes(term) ? data : null;
            }
        });

        $select.off('change.summaryOfficer').on('change.summaryOfficer', function () {
            if ($(this).val() === SUMMARY_ADD_OTHER_OFFICER) {
                // Never leave the sentinel selected: it is an action, not a person.
                $(this).val('').trigger('change.select2');
                openSummaryAddOfficerDialog();
            }
        });
    }

    // ── "+ Add Other Officer..." ─────────────────────────────────────────────────
    // Built on demand and parented to <body>: the summary card is itself inside a
    // modal, and nesting one dialog in another inherits its stacking and its scroll
    // box. Built once, then reused.
    function summaryAddOfficerDialog() {
        let el = document.getElementById('summaryAddOfficerDialog');
        if (el) return el;

        el = document.createElement('div');
        el.id = 'summaryAddOfficerDialog';
        el.className = 'fixed inset-0 hidden';
        el.style.zIndex = '100020';
        el.innerHTML = `
            <div class="absolute inset-0 bg-black/50" data-close="1"></div>
            <div class="relative mx-auto my-24 w-[92%] max-w-xl rounded-lg bg-white shadow-xl">
                <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Add Receiving Officer</h3>
                        <p class="text-xs text-gray-500">Create the officer record without leaving this page.</p>
                    </div>
                    <button type="button" data-close="1" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">&times;</button>
                </div>
                <div class="grid grid-cols-1 gap-4 px-5 py-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" id="summaryNewOfficerFirstName" maxlength="255"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                               placeholder="e.g. Aisha">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" id="summaryNewOfficerLastName" maxlength="255"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                               placeholder="e.g. Musa">
                    </div>
                    <p id="summaryNewOfficerError" class="md:col-span-2 hidden text-xs font-medium text-red-600"></p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
                    <button type="button" data-close="1" class="rounded-md border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancel</button>
                    <button type="button" id="summaryCreateOfficerBtn"
                            class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                        Create Officer
                    </button>
                </div>
            </div>`;

        document.body.appendChild(el);

        el.addEventListener('click', (e) => {
            if (e.target.closest('[data-close]')) closeSummaryAddOfficerDialog();
        });
        el.querySelector('#summaryCreateOfficerBtn').addEventListener('click', createSummaryReceivingOfficer);

        return el;
    }

    function openSummaryAddOfficerDialog() {
        const el = summaryAddOfficerDialog();
        el.querySelector('#summaryNewOfficerFirstName').value = '';
        el.querySelector('#summaryNewOfficerLastName').value = '';
        const err = el.querySelector('#summaryNewOfficerError');
        err.textContent = '';
        err.classList.add('hidden');
        el.classList.remove('hidden');
        setTimeout(() => el.querySelector('#summaryNewOfficerFirstName').focus(), 50);
    }

    function closeSummaryAddOfficerDialog() {
        document.getElementById('summaryAddOfficerDialog')?.classList.add('hidden');
    }

    async function createSummaryReceivingOfficer() {
        const el = summaryAddOfficerDialog();
        const first = el.querySelector('#summaryNewOfficerFirstName').value.trim();
        const last = el.querySelector('#summaryNewOfficerLastName').value.trim();
        const err = el.querySelector('#summaryNewOfficerError');
        const btn = el.querySelector('#summaryCreateOfficerBtn');

        const fail = (message) => {
            err.textContent = message;
            err.classList.remove('hidden');
        };

        if (!first || !last) {
            return fail('Please provide both first name and last name.');
        }

        err.classList.add('hidden');
        btn.disabled = true;

        try {
            const res = await fetch("{{ route('create-file-tracker.other-receiving-officers.store', [], false) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({ first_name: first, last_name: last })
            });

            const json = await res.json();
            if (!json || !json.success || !json.data) {
                return fail(json?.message || 'Could not create the officer. Please try again.');
            }

            // Into the same cache the dropdown is built from, then selected — so the
            // officer the operator just typed is the one the sheet will print.
            const created = { id: json.data.id, name: json.data.name || `${first} ${last}`.trim() };
            summaryOfficerCache = (summaryOfficerCache || []).concat([created])
                .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));

            renderSummaryOfficerOptions(created.id);
            if (window.jQuery && $.fn.select2) {
                $('#summaryReceivingOfficer').trigger('change.select2');
            }

            closeSummaryAddOfficerDialog();
        } catch (e) {
            console.error('[Commissioning] Could not create receiving officer', e);
            fail('Could not reach the server. Please try again.');
        } finally {
            btn.disabled = false;
        }
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
        const officerSelect = document.getElementById('summaryReceivingOfficer');
        const officerOption = officerSelect ? officerSelect.options[officerSelect.selectedIndex] : null;

        return {
            destination_department: deptSelect ? deptSelect.value : '',
            destination_office_code: officeCode,
            destination_office_name: selected ? (selected.dataset.name || selected.textContent.trim()) : '',
            // Optional. Blank leaves the movement row's Receiving Officer as N/A,
            // which is what every commissioned file recorded before this field existed.
            destination_receiving_officer_id: officerSelect ? officerSelect.value : '',
            destination_receiving_officer_name: officerOption ? (officerOption.dataset.name || '') : ''
        };
    }

    /** True when a destination is selected; warns and returns false when not. */
    function summaryDestinationSelected() {
        const destination = summaryDestinationPayload();

        if (!destination) {
            Swal.fire({
                icon: 'warning',
                title: 'Next Destination Required',
                text: 'Select the department and unit/office this file goes to after commissioning.',
                confirmButtonColor: '#f59e0b'
            });
            return false;
        }

        // The officer is part of the destination, not an extra: the Movement History row
        // on the File Tracking Sheet names who took the file, and that row reading "N/A"
        // is what this field was added to fix. "+ Add Other Officer..." means a name
        // missing from the directory is never a reason to be stuck here.
        if (!String(destination.destination_receiving_officer_name || '').trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Receiving Officer Required',
                text: 'Select who receives the file at that office. Choose "+ Add Other Officer..." if the name is not in the list.',
                confirmButtonColor: '#f59e0b'
            });
            return false;
        }

        return true;
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

        // When supplied, the shared passport is sent as multipart data and filed
        // against every child. It is optional for an existing subdivision batch.
        const batchPassportInput = document.getElementById('generatePassport');
        const batchPassportFile = batchPassportInput && batchPassportInput.files
            ? batchPassportInput.files[0]
            : null;

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
                         alpineData.locationEntries[i].houseNo = currentEntry.houseNo;
                         alpineData.locationEntries[i].streetName = currentEntry.streetName;
                         alpineData.locationEntries[i].plotNo = currentEntry.plotNo;
                         alpineData.locationEntries[i].tpNo = currentEntry.tpNo;
                         alpineData.locationEntries[i].location = currentEntry.location;
                         alpineData.locationEntries[i].lga = currentEntry.lga;
                         alpineData.locationEntries[i].latitude = currentEntry.latitude;
                         alpineData.locationEntries[i].longitude = currentEntry.longitude;
                     }
                }
            }
        }

        // Same safety net for the applicant fields when "Apply Applicant Details to All"
        // is checked but the button was never pressed.
        if (alpineData.applyApplicantToAll && alpineData.locationEntries.length > 0) {
            const currentApplicant = alpineData.locationEntries[alpineData.currentEntryIndex] || alpineData.locationEntries[0];
            if (currentApplicant) {
                for (let i = 0; i < alpineData.batchQuantity; i++) {
                    if (alpineData.locationEntries[i]) {
                        alpineData.locationEntries[i].file_name = currentApplicant.file_name;
                        alpineData.locationEntries[i].phone_no = currentApplicant.phone_no;
                        alpineData.locationEntries[i].address = currentApplicant.address;
                    }
                }
            }
        }

        // OP Smart Detector, per entry. The single-file path runs this in submitForm();
        // a batch has one allottee per captured OP, so each entry is tested against its
        // own. Named here rather than left to the server so the officer is told which
        // entry to correct while the form is still in front of them.
        const batchIsOssFlow = (window.location.pathname.includes('/lands-one-stop-shop/')
            || new URL(window.location.href).searchParams.get('source') === 'lands-one-stop-shop'
            || new URL(window.location.href).searchParams.get('type') === 'change-of-name');
        const batchAllottees = Array.isArray(alpineData.opBatchAllottees) ? alpineData.opBatchAllottees : [];

        for (let i = 0; i < batchAllottees.length; i++) {
            const allottee = String(batchAllottees[i] || '').trim();
            const entryTitle = String((alpineData.locationEntries[i] || {}).file_name || '').trim();
            if (!allottee || !entryTitle) continue;

            const sameName = normaliseOpCommissioningName(allottee) === normaliseOpCommissioningName(entryTitle);
            const stopForDetector = () => {
                hideGlobalLoading();
                hideLoadingButton(confirmBtn, originalText);
            };

            if (!batchIsOssFlow && !sameName) {
                stopForDetector();
                Swal.fire({
                    icon: 'warning',
                    title: 'OSS Change of Ownership Detected',
                    text: 'Entry ' + (i + 1) + ': the file title "' + entryTitle + '" differs from the OP allottee ('
                        + allottee + '). This OP must be processed in OSS File Commissioning.',
                    confirmButtonText: 'Review file title',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            if (batchIsOssFlow && sameName) {
                stopForDetector();
                Swal.fire({
                    icon: 'warning',
                    title: 'Land OP Detected',
                    text: 'Entry ' + (i + 1) + ': the file title matches the OP allottee (' + allottee
                        + '), so no Change of Ownership was detected. Process this OP in Land File Commissioning.',
                    confirmButtonText: 'Review file title',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }
        }

        // Prepare batch data
        const normalizeApplicationId = (value) => {
            const numericValue = Number(value);
            return Number.isInteger(numericValue) && numericValue > 0 ? numericValue : null;
        };

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
            // The applicant's phone as typed on the form. The per-entry copy is only
            // written for the entry on screen (saveCurrentApplicantToEntry), so unless
            // "Apply Applicant Details to All" was used the rest of the batch reached
            // the server with no number. Sent batch-wide, the way file_name is, and used
            // server-side only for the entries that carry none of their own.
            phone_no: alpineData.phone_no || '',
            sub_source: alpineData.subSource || '',
            // Preserve OP context in batch mode so the same Smart Detector is
            // applied before any child can be commissioned in the wrong flow.
            source_instrument_capture_id: alpineData.sourceInstrumentCaptureId || '',
            source_pra_id: alpineData.sourcePraId || '',
            source_original_owner: alpineData.sourceOriginalOwner || '',
            // A batch captured through the OP Batch card has no single source OP —
            // it has one allottee per entry, which the three fields above cannot
            // carry. Without this the detector's source guard skipped the entire
            // batch. Order matches locationEntries, which is how both were built.
            op_batch: window.pendingOpBatch?.op_batch || '',
            op_batch_allottees: JSON.stringify(
                Array.isArray(alpineData.opBatchAllottees) ? alpineData.opBatchAllottees : []
            ),
            // Origin marker — see the matching set in the single-file submit handler.
            oss_commission: (window.location.pathname.includes('/lands-one-stop-shop/')
                || new URL(window.location.href).searchParams.get('source') === 'lands-one-stop-shop'
                || new URL(window.location.href).searchParams.get('type') === 'change-of-name') ? 1 : 0,
            allocated_by_filter: alpineData.allocatedByFilter || '',
            default_allocation_type: alpineData.defaultAllocationType || null,
            // Plot management app IDs — critical for decommissioning and lineage
            subdivision_app_id: normalizeApplicationId(alpineData.subdivisionAppId),
            merger_app_id: normalizeApplicationId(alpineData.mergerAppId),
            separation_app_id: normalizeApplicationId(alpineData.separationAppId),
            // Contact fields
            phone_no: document.getElementById('generatePhoneNo')?.value || '',
            // Optional; the applicant's LAAS Portal email (see the single-file form).
            email: (document.getElementById('generateEmail')?.value || '').trim(),
            address: document.getElementById('generateAddress')?.value || '',
            rep_phone_no: document.getElementById('generateRepPhoneNo')?.value || '',
            rep_address: document.getElementById('generateRepAddress')?.value || '',
            // Typed related files apply to every file in the batch
            related_files: JSON.stringify(alpineData.relatedFilesPayload ? alpineData.relatedFilesPayload() : []),
            // Every file in the batch is dispatched to the same next destination.
            ...(pendingSummaryDestination || {})
        };
        pendingSummaryDestination = null;

        // Send multipart only when a passport is present. This preserves the
        // lean JSON request used by Corporate and other non-photo batches.
        let batchRequestBody = JSON.stringify(batchData);
        let batchRequestHeaders = {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        if (batchPassportFile) {
            const formData = new FormData();
            Object.entries(batchData).forEach(([key, value]) => {
                const formValue = typeof value === 'object'
                    ? JSON.stringify(value)
                    : typeof value === 'boolean'
                        ? (value ? '1' : '0')
                        : (value ?? '');
                formData.append(key, formValue);
            });
            formData.append('passport', batchPassportFile);
            batchRequestBody = formData;
            batchRequestHeaders = {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            };
        }

        // Send batch generation request
        fetch('{{ route("mls-fileno.generate-batch") }}', {
            method: 'POST',
            body: batchRequestBody,
            headers: batchRequestHeaders
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
                                        ${data.oss_application_summary &&
                                          ((data.oss_application_summary.created || 0) +
                                           (data.oss_application_summary.updated || 0) +
                                           (data.oss_application_summary.unchanged || 0)) > 0 ? `
                                            <li class="flex items-start gap-2.5">
                                                <div class="mt-1 w-1 h-1 rounded-full bg-${summaryColor}-400"></div>
                                                <span><b>${(data.oss_application_summary.created || 0) + (data.oss_application_summary.updated || 0) + (data.oss_application_summary.unchanged || 0)}</b> application record(s) are now available under <b>${data.data?.system_sub_type === 'OSS' ? 'Change of Ownership' : 'No Change of Ownership'}</b>.</span>
                                            </li>
                                        ` : ''}
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
                } else if (data.error_type === 'op_commissioning_flow_mismatch') {
                    // Same shape as the client-side check above — reached when a stale
                    // browser did not send the per-entry allottees, so the server was the
                    // one that caught it. Nothing was committed; the batch is still open.
                    const detector = data.op_smart_detector || {};
                    Swal.fire({
                        icon: 'warning',
                        title: detector.correct_flow === 'Land' ? 'Land OP Detected' : 'OSS Change of Ownership Detected',
                        text: data.message,
                        confirmButtonText: 'Review file title',
                        confirmButtonColor: '#f59e0b'
                    });
                } else if (data.error_type === 'batch_phone_required') {
                    // Something to fill in, not a failure: nothing was committed and the
                    // form is still open, so this reads as a prompt rather than an error.
                    Swal.fire({
                        icon: 'warning',
                        title: 'Phone Number Required',
                        text: data.message,
                        confirmButtonColor: '#2563eb'
                    });
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

    /**
     * Fill in (or hide) the "part of a batch" panel on the Edit card.
     *
     * The list collapses a batch into ONE row labelled with a range, so without this the
     * form silently edits a single member — the newest — while the user believes they are
     * editing everything the range covers. Scope always resets to "this file only": an
     * unwanted single edit is easy to undo, a batch-wide one is not.
     */
    function populateEditBatchScope(data) {
        const panel = document.getElementById('editBatchScope');
        const batchNoField = document.getElementById('editBatchNo');
        if (!panel || !batchNoField) return;

        const count = parseInt(data.batch_count, 10) || 1;
        const batchNo = data.batch_no || '';

        batchNoField.value = batchNo;

        // Reset the scope on every open, so a previous "all files" choice cannot leak
        // into the next record edited.
        const thisFileRadio = panel.querySelector('input[name="apply_to_batch"][value="0"]');
        if (thisFileRadio) thisFileRadio.checked = true;

        if (count < 2 || !batchNo) {
            panel.classList.add('hidden');
            return;
        }

        const fileNo = data.mlsfNo || data.kangisFileNo || 'this file';
        const setText = (elId, text) => {
            const el = document.getElementById(elId);
            if (el) el.textContent = text;
        };

        setText('editBatchCount', count);
        setText('editBatchCountAll', count);
        setText('editBatchThisFile', String(fileNo).toUpperCase());
        setText('editBatchRange', `${batchNo} — ${data.batch_range || ''}`);

        panel.classList.remove('hidden');

        if (window.lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
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
        // A batch row in the Group modal can carry a null filenumber_id; firing the
        // request anyway produced an int-conversion 500 and left a blank form open.
        if (id === null || id === undefined || id === '' || String(id) === 'null') {
            hideGlobalLoading();
            Swal.fire({
                icon: 'warning',
                title: 'Record not linked',
                text: 'This file has no matching file number record, so it cannot be edited here.',
            });
            return;
        }

        // Show loading while fetching record details
        showGlobalLoading('Loading record details...');

        // Plot Extensions and Temporary files live in separate tables and reuse ids that
        // collide with fileNumber ids, so flag the entity to resolve the correct record.
        // Without this a temporary row loaded — and then saved over — a different file.
        const entity = mlsfRowEntity(type);
        let showUrl = `{{ route("file-numbers.show", ":id") }}`.replace(':id', id);
        if (entity !== 'file_number') {
            showUrl += (showUrl.includes('?') ? '&' : '?') + 'entity=' + encodeURIComponent(entity);
        }

        fetch(showUrl)
            .then(response => response.json())
            .then(data => {
                hideGlobalLoading();

                if (data && data.success === false) {
                    Swal.fire({ icon: 'error', title: 'Error!', text: data.message || 'Record not found' });
                    return;
                }

                // file-numbers.show returns {success: true, data: {...}}. Normalize
                // that envelope before populating the edit controls; older deployments
                // returned the record directly, so retain that form as a fallback.
                data = (data && data.data && typeof data.data === 'object') ? data.data : (data || {});

                // Remember the entity so the save path targets the same table.
                const entityField = document.getElementById('editEntity');
                if (entityField) entityField.value = data.entity || entity || '';

                populateEditBatchScope(data);

                // Populate fields
                document.getElementById('editId').value = data.id;
                document.getElementById('editMlsfNo').value = data.mlsfNo || data.kangisFileNo || '';
                document.getElementById('editFileName').value = data.FileName || '';
                document.getElementById('editPlotNo').value = data.plot_no || '';
                setEditTpField(data.tp_no || '');
                // A stored location is hand-authored data by definition — protect it from
                // the auto-composer until the user clears the field themselves.
                const editLocationEl = document.getElementById('editLocation');
                editLocationEl.value = data.location || '';
                editLocationEl.dataset.userEdited = (data.location || '').trim() === '' ? '0' : '1';
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
                const editLandUse = document.getElementById('editLandUse');
                if (editLandUse) {
                    // Older commissioned rows may not have mls_file_no.land_use populated,
                    // even though the prefix in the file number is authoritative (RES-...,
                    // COM-..., etc.). Use that prefix as a fallback so the edit card does
                    // not incorrectly remain on "Select Land Use".
                    const filePrefix = (data.mlsfNo || data.kangisFileNo || '').toString().split('-')[0].trim();
                    const storedLandUse = (data.land_use || data.land_use_type || filePrefix || '')
                        .toString().trim().toUpperCase();
                    const landOption = Array.from(editLandUse.options).find(o =>
                        o.value.toUpperCase() === storedLandUse || o.textContent.trim().toUpperCase() === storedLandUse
                    );
                    editLandUse.value = landOption ? landOption.value : '';
                    filterEditPurposesByLandUse(editLandUse.value);
                }
                const editPurpose = document.getElementById('editPurpose');
                if (editPurpose) editPurpose.value = data.purpose_id || '';
                validateEditPurposeLandUse();
                const editGender = document.getElementById('editGender');
                if (editGender) {
                    // Depending on whether the row came from fileNumber or mls_file_no,
                    // SQL Server may expose the value as `gender` or `Gender`. Accept both
                    // (and the common abbreviated M/F values) when restoring the edit form.
                    const rawGender = data.gender ?? data.Gender ?? data.gender_type ?? data.sex ?? '';
                    const normalizedGender = rawGender.toString().trim().toLowerCase();
                    const gender = normalizedGender === 'm' ? 'male'
                        : (normalizedGender === 'f' ? 'female' : normalizedGender);
                    const option = Array.from(editGender.options).find(o => o.value.toLowerCase() === gender);
                    editGender.value = option ? option.value : '';
                }
                
                // New Fields
                if (document.getElementById('editPhoneNo')) {
                    document.getElementById('editPhoneNo').value = data.phone_no || data.PhoneNo || '';
                }
                if (document.getElementById('editAddress')) {
                    document.getElementById('editAddress').value = data.address || '';
                }

                // Passport already on record (show() resolves it from oss_applications,
                // falling back to the file's EDMS scan row). No photo leaves the empty slot.
                setEditPassportPreview(data.passport_url || '', data.passport_url ? 'Passport on record' : 'No passport on record');

                // Related / Old file number share one input. An existing old_fileno wins
                // and ticks the checkbox, which relabels the field.
                const relatedInput = document.getElementById('editRelatedFileNo');
                if (relatedInput) {
                    const oldFileNo = (data.old_fileno || '').toString().trim();
                    relatedInput.value = oldFileNo || parseRelatedFileNo(data.related_fileno);
                    const oldCheckbox = document.getElementById('editIsOldFileNo');
                    if (oldCheckbox) oldCheckbox.checked = oldFileNo !== '';
                    // No stored title for the linked file — it only appears after a pick.
                    const relatedTitleEl = document.getElementById('editRelatedFileTitle');
                    if (relatedTitleEl) relatedTitleEl.textContent = '';
                    if (relatedInput.value) {
                        fetch(`{{ route('api.file-numbers.lookup') }}?file_number=${encodeURIComponent(relatedInput.value)}`)
                            .then(r => r.json())
                            .then(result => {
                                const record = result && result.data;
                                const title = record && (record.file_name || record.file_title || record.FileName);
                                if (title && relatedTitleEl) relatedTitleEl.textContent = title;
                            }).catch(() => {});
                    }
                    onEditIsOldFileNoChange(oldFileNo !== '');
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

    // ---- Edit modal passport ---------------------------------------------------
    // One slot, three states: the photograph already filed against the file, a
    // replacement picked here, or an explicit (but reversible until Save) removal.

    function setEditPassportPreview(url, statusText) {
        const img = document.getElementById('editPassportPreview');
        const placeholder = document.getElementById('editPassportPlaceholder');
        const status = document.getElementById('editPassportStatus');
        const input = document.getElementById('editPassport');
        const removal = document.getElementById('editRemovePassport');
        const removeButton = document.getElementById('editPassportRemoveButton');

        // Clearing the picker matters: one modal serves every row, so a file left selected
        // from a previous edit would otherwise be uploaded against the wrong file number.
        if (input) input.value = '';
        if (removal) removal.value = '0';
        if (input) input.dataset.currentUrl = url || '';
        if (removeButton) {
            removeButton.textContent = 'Remove Passport Photograph';
            removeButton.classList.toggle('hidden', !url);
            removeButton.classList.remove('text-emerald-600', 'hover:text-emerald-700');
            removeButton.classList.add('text-red-600', 'hover:text-red-700');
        }

        if (img) {
            if (url) {
                img.src = url;
                img.classList.remove('hidden');
            } else {
                img.removeAttribute('src');
                img.classList.add('hidden');
            }
        }
        if (placeholder) placeholder.classList.toggle('hidden', !!url);
        if (status) status.textContent = statusText || (url ? 'Passport on record' : 'No passport on record');
    }

    function onEditPassportChange(input) {
        const file = input && input.files ? input.files[0] : null;
        if (!file) return;

        const img = document.getElementById('editPassportPreview');
        const placeholder = document.getElementById('editPassportPlaceholder');
        const status = document.getElementById('editPassportStatus');
        const removal = document.getElementById('editRemovePassport');
        const removeButton = document.getElementById('editPassportRemoveButton');

        // Checked here as well as server-side, so an oversized photo is caught before the
        // operator fills in the rest of the form and submits.
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'Passport too large',
                text: 'The passport photograph must be 2MB or smaller.',
                confirmButtonColor: '#ef4444'
            });
            input.value = '';
            return;
        }

        // A new upload replaces rather than removes the passport.
        if (removal) removal.value = '0';
        if (removeButton) {
            removeButton.textContent = 'Remove Passport Photograph';
            removeButton.classList.remove('hidden', 'text-emerald-600', 'hover:text-emerald-700');
            removeButton.classList.add('text-red-600', 'hover:text-red-700');
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            if (img) {
                img.src = e.target.result;
                img.classList.remove('hidden');
            }
            if (placeholder) placeholder.classList.add('hidden');
            if (status) status.textContent = file.name + ' — replaces the current photo on save';
        };
        reader.readAsDataURL(file);
    }

    function toggleEditPassportRemoval() {
        const input = document.getElementById('editPassport');
        const img = document.getElementById('editPassportPreview');
        const placeholder = document.getElementById('editPassportPlaceholder');
        const status = document.getElementById('editPassportStatus');
        const removal = document.getElementById('editRemovePassport');
        const removeButton = document.getElementById('editPassportRemoveButton');
        if (!removal || !removeButton) return;

        if (removal.value !== '1') {
            // Nothing is deleted until the form is saved.
            removal.value = '1';
            if (input) input.value = '';
            if (img) { img.removeAttribute('src'); img.classList.add('hidden'); }
            if (placeholder) placeholder.classList.remove('hidden');
            if (status) status.textContent = 'Passport photograph will be removed when changes are saved';
            removeButton.textContent = 'Keep Passport Photograph';
            removeButton.classList.remove('text-red-600', 'hover:text-red-700');
            removeButton.classList.add('text-emerald-600', 'hover:text-emerald-700');
            return;
        }

        removal.value = '0';
        const currentUrl = input ? (input.dataset.currentUrl || '') : '';
        if (img && currentUrl) { img.src = currentUrl; img.classList.remove('hidden'); }
        if (placeholder) placeholder.classList.toggle('hidden', !!currentUrl);
        if (status) status.textContent = currentUrl ? 'Passport on record' : 'No passport on record';
        removeButton.textContent = 'Remove Passport Photograph';
        removeButton.classList.remove('text-emerald-600', 'hover:text-emerald-700');
        removeButton.classList.add('text-red-600', 'hover:text-red-700');
    }

    // related_fileno is stored as a JSON array on fileNumber, but older rows hold a
    // plain string. Render either shape as a comma-separated list for the edit field.
    function parseRelatedFileNo(raw) {
        const val = (raw === null || raw === undefined) ? '' : raw.toString().trim();
        if (!val) return '';
        if (val.startsWith('[')) {
            try {
                const arr = JSON.parse(val);
                return Array.isArray(arr) ? arr.filter(Boolean).join(', ') : '';
            } catch (e) {
                return '';
            }
        }
        return val;
    }

    // Show the title/clear affordances only while a file number is actually set.
    function syncEditRelatedFileNoUi() {
        const input = document.getElementById('editRelatedFileNo');
        const clearBtn = document.getElementById('editRelatedFileNoClear');
        const titleEl = document.getElementById('editRelatedFileTitle');
        const hasValue = !!(input && input.value.trim());

        if (clearBtn) {
            clearBtn.classList.toggle('hidden', !hasValue);
            clearBtn.classList.toggle('inline-flex', hasValue);
        }
        if (titleEl) {
            titleEl.classList.toggle('hidden', !(hasValue && titleEl.textContent.trim()));
        }
    }

    function filterEditPurposesByLandUse(value) {
        const land = document.getElementById('editLandUse');
        const purposes = document.getElementById('editPurpose');
        if (!land || !purposes) return;
        const landId = land.options[land.selectedIndex]?.dataset.landUseId || '';
        Array.from(purposes.options).forEach(option => {
            if (!option.value) { option.hidden = false; return; }
            option.hidden = !!landId && option.dataset.landUseId !== landId;
        });
        validateEditPurposeLandUse();
    }

    function validateEditPurposeLandUse() {
        const land = document.getElementById('editLandUse');
        const purpose = document.getElementById('editPurpose');
        if (!land || !purpose || !purpose.value) return;
        const landId = land.options[land.selectedIndex]?.dataset.landUseId || '';
        const selected = purpose.options[purpose.selectedIndex];
        if (landId && selected && selected.dataset.landUseId !== landId) purpose.value = '';
    }

    // Reuse the global file-number selector (components/global-fileno-modal) so the
    // Edit modal links a real, resolvable file instead of free text. The global modal
    // sits at z-index 2000000, above the teleported edit modal.
    function openEditRelatedFileModal() {
        if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
            Swal.fire('Error', 'File number selector is not available. Please refresh the page.', 'error');
            return;
        }

        GlobalFileNoModal.open({
            callback: function (data) {
                if (!data || !data.fileNumber) return;

                const input = document.getElementById('editRelatedFileNo');
                const titleEl = document.getElementById('editRelatedFileTitle');
                // Legacy KN/KANGIS records store a trailing dash (e.g. "KN 3456-").
                if (input) {
                    input.value = (data.fileNumber || '').toString().replace(/[\s-]+$/, '').trim();
                }
                if (titleEl) {
                    titleEl.textContent = (
                        data.file_name
                        || data.file_title
                        || (data.record && (data.record.file_name || data.record.FileName || data.record.file_title))
                        || ''
                    ).toString().trim();
                }
                syncEditRelatedFileNoUi();
            }
        });
    }

    function clearEditRelatedFileNo() {
        const input = document.getElementById('editRelatedFileNo');
        const titleEl = document.getElementById('editRelatedFileTitle');
        if (input) input.value = '';
        if (titleEl) titleEl.textContent = '';
        syncEditRelatedFileNoUi();
    }

    // The Edit modal carries one file-number field. Ticking "Old File Number" relabels
    // it and flips the hidden flag so the backend saves it as the old file number
    // (mls_file_no.old_fileno) instead of a related file number.
    function onEditIsOldFileNoChange(checked) {
        const isOld = !!checked;
        const label = document.getElementById('editRelatedFileNoLabel');
        const hidden = document.getElementById('editIsOldFileNoValue');
        const input = document.getElementById('editRelatedFileNo');
        const hint = document.getElementById('editRelatedFileNoHint');

        if (hidden) hidden.value = isOld ? '1' : '0';
        if (label) {
            label.innerHTML = '<i data-lucide="link" class="w-4 h-4 inline mr-1"></i>'
                + (isOld ? 'Old File Number' : 'Related File Number');
        }
        if (hint) {
            hint.textContent = isOld
                ? "Saved as this file's previous (old) file number."
                : 'Pick the file this one relates to.';
        }
        if (input) {
            input.placeholder = isOld ? 'Select old file number' : 'Select related file number';
        }
        syncEditRelatedFileNoUi();
        if (window.lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
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

    // ── Edit modal TP Number: searchable lookup + "Other (specify)" ─────────────
    // The generator form's picker is Alpine; this modal is plain JS, so the same
    // behaviour is repeated here rather than shared. #editTpNo holds the search term and
    // is never posted; #editTpNoValue is. See TP_OTHER for why the sentinel is kept out
    // of the stored value.
    let editTpSearchTimer = null;

    // Both boxes write through here, so the posted value, the clear button and the
    // specify box can never disagree about what the TP number is.
    function setEditTpValue(value) {
        const hidden = document.getElementById('editTpNoValue');
        if (hidden) hidden.value = value || '';
        const clear = document.getElementById('editTpClear');
        if (clear) clear.style.display = (value || document.getElementById('editTpNo')?.value) ? '' : 'none';
    }

    // Fills the control from a loaded record. A stored number is shown in the search box;
    // there is no way to tell whether it once came from the lookup or from "Other", and it
    // does not matter — editing it re-runs the same search.
    function setEditTpField(value) {
        const search = document.getElementById('editTpNo');
        const other = document.getElementById('editTpNoOther');
        if (search) search.value = value || '';
        if (other) { other.style.display = 'none'; other.value = ''; }
        closeEditTpSearch();
        setEditTpValue(value || '');
    }

    function closeEditTpSearch() {
        const box = document.getElementById('editTpResults');
        if (box) box.style.display = 'none';
    }

    function renderEditTpResults(results) {
        const box = document.getElementById('editTpResults');
        if (!box) return;

        // The lookup already appends its own "Other" row; re-adding it here keeps the
        // escape reachable when the search returned nothing at all.
        const rows = (results || []).filter(r => r.id !== TP_OTHER);
        rows.push({ id: TP_OTHER, text: 'Other (specify)' });

        box.innerHTML = rows.map(r => {
            const isOther = r.id === TP_OTHER;
            const cls = isOther
                ? 'px-3 py-2 text-sm cursor-pointer italic text-blue-700 border-t border-gray-200 hover:bg-gray-50'
                : 'px-3 py-2 text-sm cursor-pointer hover:bg-gray-50';
            const id = String(r.id).replace(/"/g, '&quot;');
            const text = String(r.text).replace(/</g, '&lt;');
            return `<div class="${cls}" data-tp-id="${id}">${text}</div>`;
        }).join('');

        box.querySelectorAll('[data-tp-id]').forEach(el => {
            // mousedown, not click: the search box's blur would close the list first.
            el.addEventListener('mousedown', e => {
                e.preventDefault();
                selectEditTpResult(el.getAttribute('data-tp-id'), el.textContent.trim());
            });
        });

        box.style.display = '';
    }

    function onEditTpSearchInput(value) {
        clearTimeout(editTpSearchTimer);

        const other = document.getElementById('editTpNoOther');
        if (other) { other.style.display = 'none'; other.value = ''; }

        // Typing over a chosen number makes it the value: the officer may be entering a
        // TP number the lookup has never seen, and the field must not silently keep the
        // old one just because nothing was picked from the list.
        setEditTpValue(String(value || '').trim().toUpperCase());

        const q = String(value || '').trim();
        if (q.length < 1) {
            closeEditTpSearch();
            return;
        }

        editTpSearchTimer = setTimeout(() => {
            fetch('{{ route("instruments.tpLookups.search") }}?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => renderEditTpResults(data.results || []))
            .catch(e => console.error('[Edit TP Search] fetch error:', e));
        }, 300);
    }

    function selectEditTpResult(id, text) {
        const search = document.getElementById('editTpNo');
        const other = document.getElementById('editTpNoOther');
        closeEditTpSearch();

        if (id === TP_OTHER) {
            // Nothing is committed yet — the number is whatever they type next.
            if (search) search.value = 'Other';
            if (other) { other.style.display = ''; other.value = ''; other.focus(); }
            setEditTpValue('');
            return;
        }

        if (search) search.value = text;
        if (other) { other.style.display = 'none'; other.value = ''; }
        setEditTpValue(id);
    }

    function onEditTpOtherInput(value) {
        setEditTpValue(String(value || '').trim().toUpperCase());
    }

    function clearEditTpNo() {
        setEditTpField('');
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
        const locationInput = document.getElementById('editLocation');
        if (!locationInput) return;

        // A location the user typed themselves is real data — "NO 5 AHMADU BELLO WAY,
        // NASSARAWA" is not something this function can reconstruct. Auto-composition is
        // a convenience for a blank field, so once the field has been edited by hand it
        // is left alone. The save propagates Location to three tables, so clobbering it
        // here was not a cosmetic bug.
        if (locationInput.dataset.userEdited === '1') return;

        const district = document.getElementById('editDistrictValue')?.value.trim() || '';
        const lga = document.getElementById('editLga')?.value.trim() || '';

        // Location is composed from district/LGA only — the plot number is a
        // separate field and must not be echoed into the location string.
        let locationDetails = [];
        if (district) locationDetails.push(district);
        if (lga) locationDetails.push(lga);
        locationDetails.push('KANO');

        locationInput.value = locationDetails.join(', ').toUpperCase();
    }

    // Attach listeners for Edit Modal fields
    $(document).ready(function() {
        // #editPlotNo is deliberately NOT here. The composed string never contained the
        // plot number, yet typing one still triggered the rebuild and wiped whatever
        // Location held — so correcting a plot number silently destroyed the address.
        $(document).on('input change', '#editLga, #editDistrict', function() {
            updateEditLocation();
        });

        // Typing in Location marks it as hand-authored; clearing it hands control back.
        $(document).on('input', '#editLocation', function () {
            this.dataset.userEdited = this.value.trim() === '' ? '0' : '1';
        });
        // Make the Edit / Direct Allocation District dropdowns searchable
        initEditDistrictSelect2();
        initDaDistrictSelect2();
    });

    function submitEditForm(event, confirmTransactionChange = false, confirmBatchDivergence = false) {
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
        if (confirmBatchDivergence) {
            formData.set('confirm_batch_divergence', '1');
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
                } else if (status === 409 && data.requires_batch_confirmation) {
                    // The files in this batch do not currently agree on a field being
                    // changed. ~1 in 5 batches genuinely differ per file, and flattening
                    // them is silent and irreversible — so show what would be overwritten.
                    const rows = (data.divergent || []).map(d =>
                        `<tr>
                            <td class="px-2 py-1 text-left font-semibold text-slate-700">${d.label}</td>
                            <td class="px-2 py-1 text-left text-slate-600">${(d.values || []).join(' · ')}</td>
                         </tr>`).join('');

                    Swal.fire({
                        icon: 'warning',
                        title: 'Files in this batch differ',
                        html: `
                            <p class="text-sm text-slate-600 mb-3">${data.message || ''}</p>
                            <div class="max-h-52 overflow-y-auto border border-slate-200 rounded-lg">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-2 py-1 text-left">Field</th>
                                            <th class="px-2 py-1 text-left">Current values across the batch</th>
                                        </tr>
                                    </thead>
                                    <tbody>${rows}</tbody>
                                </table>
                            </div>`,
                        showCancelButton: true,
                        confirmButtonText: `Yes, overwrite all ${data.batch_count || ''} files`,
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#f59e0b',
                        cancelButtonColor: '#6b7280'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitEditForm(event, confirmTransactionChange, true);
                        }
                    });
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
                            submitEditForm(event, true, confirmBatchDivergence);
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

    function directAllocation(id, rowType) {
        // Same collision as Edit and Delete: this posts to file-numbers.update, which
        // resolves the id against `fileNumber`. On a Temporary or Plot Extension row that
        // is a different file, so the allocation data would land on someone else's record.
        // The menu withholds the button for those rows; this is the second line of defence.
        const entity = mlsfRowEntity(rowType);
        if (entity !== 'file_number') {
            Swal.fire({
                icon: 'info',
                title: 'Not available here',
                text: `${rowType || 'This record'} records are not held in the file number register.`,
            });
            return;
        }

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
        const district = document.getElementById('daDistrictValue')?.value.trim() || '';
        const lga = document.getElementById('daLga')?.value.trim() || '';
        
        // A location says where the property is, so it is district + LGA + state.
        // The plot number identifies the parcel rather than locating it, and it is
        // captured in its own field — it never joins this string.
        let locationDetails = [];
        if (district) locationDetails.push(district);
        if (lga) locationDetails.push(lga);
        locationDetails.push('KANO');

        const locationString = locationDetails.join(', ');

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
        // Be explicit about the target table rather than letting the server default.
        formData.set('entity', 'file_number');

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
 
    /**
     * Full-size view of a passport thumbnail from the list.
     *
     * The thumbnails are 36px wide so the row stays readable; a clerk checking a face
     * against a file needs more than that, and opening the Edit modal just to see the
     * photo is a lot of clicks for a look.
     */
    window.openMlsfPassportPreview = function (url, title) {
        if (!url) return;
        Swal.fire({
            title: title || 'Passport',
            imageUrl: url,
            imageAlt: 'Passport photograph',
            imageWidth: 260,
            showConfirmButton: false,
            showCloseButton: true,
        });
    };

    // The five tables the cascade purges, plus the old-file-number ledger this screen's
    // own Edit modal writes — it used to survive the delete and keep naming a file that
    // no longer existed.
    const MLSF_CASCADE_TABLES = [
        'MlsfileNo', 'fileNumber', 'Entity Table', 'Customers', 'File Indexings',
        'Old File Numbers',
        // Commissioning publishes the file to the OSS applications list and opens a
        // tracking request; both used to survive the delete, so a purged file kept
        // appearing on /lands-one-stop-shop/applications and in File Tracking.
        'OSS Applications', 'File Tracking',
    ];

    function mlsfCascadeTableList() {
        return `<div class="inline-block text-left bg-slate-50 p-4 rounded-xl border border-slate-200/80 w-full max-w-md mx-auto shadow-inner">
                    <ul class="space-y-2 text-slate-700 font-semibold text-xs list-decimal list-inside">
                        ${MLSF_CASCADE_TABLES.map(t => `<li><span class="font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100/50">${t}</span></li>`).join('')}
                    </ul>
                </div>`;
    }

    function deleteRecord(id, rowType, batchNo, batchCount) {
        // Belt and braces: these rows no longer render a Delete button, but a stale page
        // might. The server refuses them as well.
        const entity = mlsfRowEntity(rowType);
        if (entity !== 'file_number') {
            Swal.fire({
                icon: 'info',
                title: 'Not available here',
                text: `${rowType || 'This record'} records are not held in the file number register and cannot be master-deleted from this screen.`,
            });
            return;
        }

        const count = parseInt(batchCount, 10) || 1;
        const isBatch = count > 1 && batchNo;

        // A batch row is drawn as one line but stands for N files. Deleting only the id it
        // carries removed one member and left the row on screen — redrawn from the next
        // member — which read as a failed delete. Say what the row really covers, and make
        // the scope an explicit choice defaulting to the safe one.
        const scopeChoice = isBatch ? `
            <div class="mt-4 text-left bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p class="text-xs font-semibold text-amber-900 mb-2">
                    This row represents <strong>${count} files</strong> in batch ${batchNo}.
                </p>
                <label class="flex items-center gap-2 text-xs text-amber-900 cursor-pointer mb-1">
                    <input type="radio" name="mlsfDeleteScope" value="0" checked> Delete <strong>this file only</strong>
                </label>
                <label class="flex items-center gap-2 text-xs text-amber-900 cursor-pointer">
                    <input type="radio" name="mlsfDeleteScope" value="1"> Delete <strong>all ${count} files</strong> in the batch
                </label>
            </div>` : '';

        Swal.fire({
            title: 'Are you sure?',
            html: `
                <div class="text-center">
                    <p class="mb-4 text-slate-500 text-sm">You won't be able to revert this! This action will execute a <strong>Master Cascade Delete</strong> and permanently purge this record from the following ${MLSF_CASCADE_TABLES.length} tables:</p>
                    ${mlsfCascadeTableList()}
                    ${scopeChoice}
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const scopeEl = document.querySelector('input[name="mlsfDeleteScope"]:checked');
                const applyToBatch = !!(scopeEl && scopeEl.value === '1');

                return fetch(`{{ route("file-numbers.destroy", ":id") }}`.replace(':id', id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        entity: entity,
                        batch_no: batchNo || '',
                        apply_to_batch: applyToBatch ? 1 : 0
                    })
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

    // How many FILES each selected row stands for. A batch row is drawn as one line but
    // covers N files, so the selection count alone understates what a bulk delete reaches
    // — three ticked 100-file batches once read as "3 records".
    window.mlsfSelectedBatchCounts = new Map();

    function mlsfSelectedFileCount() {
        let total = 0;
        window.mlsfSelectedIds.forEach(key => {
            total += window.mlsfSelectedBatchCounts.get(key) || 1;
        });
        return total;
    }

    function mlsfSelectedBatchRows() {
        let rows = 0;
        window.mlsfSelectedIds.forEach(key => {
            if ((window.mlsfSelectedBatchCounts.get(key) || 1) > 1) rows++;
        });
        return rows;
    }

    function updateBulkDeleteUI() {
        const bar = document.getElementById('mlsfBulkActionsBar');
        const countEl = document.getElementById('mlsfSelectedCount');
        const count = window.mlsfSelectedIds.size;
        const files = mlsfSelectedFileCount();
        if (countEl) countEl.textContent = files > count ? `${count} (${files} files)` : count;
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
        window.mlsfSelectedBatchCounts.clear();
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
            window.mlsfSelectedBatchCounts.set(id, parseInt(this.dataset.batchCount, 10) || 1);
        } else {
            window.mlsfSelectedIds.delete(id);
            window.mlsfSelectedBatchCounts.delete(id);
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
                window.mlsfSelectedBatchCounts.set(id, parseInt(cb.dataset.batchCount, 10) || 1);
            } else {
                window.mlsfSelectedIds.delete(id);
                window.mlsfSelectedBatchCounts.delete(id);
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

        // A ticked row can stand for a whole batch, so the count that matters is FILES,
        // not rows: three ticked 100-file batches used to read as "3 records".
        const fileCount = mlsfSelectedFileCount();
        const batchRows = mlsfSelectedBatchRows();
        const hasBatches = batchRows > 0;

        if (ids.length > 200) {
            Swal.fire({ icon: 'warning', title: 'Too many records', text: 'Please select 200 or fewer rows per batch.' });
            return;
        }

        const scopeChoice = hasBatches ? `
            <div class="mt-3 text-left bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p class="text-xs font-semibold text-amber-900 mb-2">
                    ${batchRows} of the selected rows are batches — together the selection covers <strong>${fileCount} files</strong>.
                </p>
                <label class="flex items-center gap-2 text-xs text-amber-900 cursor-pointer mb-1">
                    <input type="radio" name="mlsfBulkScope" value="0" checked> Delete <strong>one file per row</strong> (${ids.length} files)
                </label>
                <label class="flex items-center gap-2 text-xs text-amber-900 cursor-pointer">
                    <input type="radio" name="mlsfBulkScope" value="1"> Delete <strong>every file in each batch</strong> (${fileCount} files)
                </label>
            </div>` : '';

        Swal.fire({
            title: `Delete ${ids.length} row(s)?`,
            html: `
                <div class="text-center">
                    <p class="mb-3 text-slate-600 text-sm">This will execute a <strong>Master Cascade Delete</strong> for the selected row(s), purging them from <strong>${MLSF_CASCADE_TABLES.length} tables</strong>:</p>
                    ${mlsfCascadeTableList()}
                    ${scopeChoice}
                    <p class="mt-3 text-xs text-red-600 font-semibold">All deletes run in one transaction — any failure rolls back every record.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Yes, delete!`,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const scopeEl = document.querySelector('input[name="mlsfBulkScope"]:checked');
                const applyToBatch = !!(scopeEl && scopeEl.value === '1');

                return fetch(`{{ route('file-numbers.bulk-destroy') }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ids: ids, apply_to_batch: applyToBatch ? 1 : 0 })
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
                // Rows the server declined to touch used to vanish behind a green tick:
                // a temporary row in the selection was silently skipped and reported as a
                // success. Say what was left behind.
                const notDeleted = []
                    .concat((json.missing_ids || []).map(id => `#${id} — no matching file record`))
                    .concat((json.skipped || []).map(s => `#${s.id} — ${s.reason}`));

                const warning = notDeleted.length
                    ? `<div class="mt-3 text-left bg-amber-50 border border-amber-200 rounded-lg p-2">
                           <p class="text-xs font-bold text-amber-900 mb-1">${notDeleted.length} row(s) were NOT deleted:</p>
                           <ul class="text-[11px] text-amber-800 list-disc list-inside space-y-0.5">
                               ${notDeleted.slice(0, 8).map(t => `<li>${t}</li>`).join('')}
                               ${notDeleted.length > 8 ? `<li>+${notDeleted.length - 8} more</li>` : ''}
                           </ul>
                       </div>`
                    : '';

                Swal.fire({
                    icon: notDeleted.length ? 'warning' : 'success',
                    title: notDeleted.length ? 'Partially deleted' : 'Deleted!',
                    html: `${json.message || ''}<br><small class="text-gray-500">Cascade totals — fileNumber: ${json.totals?.fileNumber ?? 0}, mls_file_no: ${json.totals?.mls_file_no ?? 0}, entities: ${json.totals?.entities_staging ?? 0}, customers: ${json.totals?.customers_staging ?? 0}, indexings: ${json.totals?.file_indexings ?? 0}, old file numbers: ${json.totals?.old_file_numbers ?? 0}, OSS applications: ${json.totals?.oss_applications ?? 0}, file tracking: ${json.totals?.file_tracking ?? 0}</small>${warning}`,
                    confirmButtonColor: '#10b981'
                });
                window.mlsfSelectedIds.clear();
                window.mlsfSelectedBatchCounts.clear();
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
    const GENERATOR_MAP_KANO_CENTER = { lat: 12.0022, lng: 8.5920 };

    function normalizeGeneratorCoord(value) {
        const numeric = parseFloat(value);
        if (!isFinite(numeric)) {
            return null;
        }
        return Math.round(numeric * 1e7) / 1e7;
    }

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
            // Most commissioned files are individuals — default the card to it.
            customerType: 'Individual',
            gender: '',
            // Passport is an image upload filed into the new file number's EDMS folder.
            // `passport` holds only the chosen file's name — the image itself stays on the
            // native file input so FormData posts it as-is; `passportPreview` is the object
            // URL behind the thumbnail.
            passport: '',
            passportPreview: '',
            handlePassportChange(event) {
                const input = event.target;
                const file = input.files && input.files[0];

                if (!file) {
                    this.clearPassport();
                    return;
                }

                if (!/^image\/(jpeg|jpg|png)$/i.test(file.type)) {
                    Swal.fire({ icon: 'warning', title: 'Unsupported File', text: 'Passport must be a JPG or PNG image.', confirmButtonColor: '#f59e0b' });
                    this.clearPassport();
                    return;
                }

                // Matches the server rule (max:2048), so an oversized image is refused here
                // instead of coming back as a 422 after the operator filled the whole form.
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({ icon: 'warning', title: 'File Too Large', text: 'Passport image must be 2MB or smaller.', confirmButtonColor: '#f59e0b' });
                    this.clearPassport();
                    return;
                }

                if (this.passportPreview) {
                    URL.revokeObjectURL(this.passportPreview);
                }

                this.passport = file.name;
                this.passportPreview = URL.createObjectURL(file);
            },
            clearPassport() {
                if (this.passportPreview) {
                    URL.revokeObjectURL(this.passportPreview);
                }
                this.passport = '';
                this.passportPreview = '';
                const input = document.getElementById('generatePassport');
                if (input) input.value = '';
            },
            // Default to normal file
            fileOption: 'normal',
            // Backs the top "FILE TYPE" select. Kept separate from fileOption because that
            // select also expresses workflows (e.g. change_of_purpose) which map to an
            // applicationType rather than a fileOption. Must be reset with fileOption.
            fileTypeWorkflow: '',
            // Reason captured for SIT files
            sitReason: '',
            // Extension has a single form: a new file number suffixed " AND EXTENSION".
            // Retained so the existing `extension_type` form field still posts a value.
            extensionType: 'file',
            // Ticked = commission the selected file number exactly as-is, with no
            // " AND EXTENSION" suffix. The plot number still carries " & EXTENSION"
            // either way — that is what identifies the record as an extension.
            suppressExtensionSuffix: false,
            _lastFileOption: 'normal', // tracks previous fileOption to detect transitions
            existingFileNo: '',
            middlePrefix: 'KN',
            year: new Date().getFullYear(),
            serialNo: '',
            houseNo: '',
            streetName: '',
            plotNo: '',
            tpNo: '',
            tpSearchQuery: '',
            tpSearchResults: [],
            tpSearchLoading: false,
            tpSearchOpen: false,
            tpSearchTimer: null,
            tpFocusIndex: -1,
            // "Other" is a real option on the TP lookup (the search endpoint appends it to
            // every response). Picking it opens a free-text box, because the TP number the
            // officer holds is not always one the lookup table knows about yet. tpNo stays
            // empty until they type — the sentinel must never be saved as the TP number.
            tpIsOther: false,
            tpOtherValue: '',
            location: '',
            lga: '',
            district: '',
            districtIsOther: false,
            streetNameIsOther: false,
            latitude: '',
            longitude: '',
            mapCoordSource: '',
            _locationMap: null,
            _locationMarker: null,
            _locationGeocoder: null,
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
            // True once a selected related file supplied its own title: that title is the
            // register's, not the officer's, so the field locks. It stays false for a
            // legacy record that stored no title, which is the case the field is still
            // typable for. Clearing the related file unlocks it again.
            relatedFileTitleLocked: false,
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
                'Recertification',
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
            // True while File Option is "Re-grant". A Re-grant always replaces a specific
            // existing file, so the relationship is known before the officer says anything:
            // the Related File panel becomes a mandatory "Mother File" panel and its type is
            // locked, exactly as isRecertificationPrefix does for an -RC number.
            //
            // A getter rather than stored state, because fileOption is assigned from several
            // places and a flag that has to be kept in step with it would eventually fall out
            // of step — leaving the panel labelled "Related File" on a Re-grant.
            get isRegrantOption() {
                return this.fileOption === 'regrant';
            },
            // Set by the File Type dropdown's "Recertification" entry. It does not change
            // how the number is built — an RC file numbers like a normal one — it narrows
            // the Prefix list to the -RC prefixes so the officer cannot pick a number that
            // contradicts the file type. isRecertificationPrefix stays the authority on
            // whether the Related File panel is mandatory, since it reads the real prefix.
            recertificationWorkflow: false,

            // Old File Properties (Re-Issuance of FileNo). Picked with the same
            // GlobalFileNoModal as a related file, but stored separately: the old
            // (duplicated) number is written to mls_file_no.old_fileno.
            oldFileNo: '',
            oldFileTitle: '',
            oldFileIndexingId: '',

            // Subdivision / Merger / Separation Properties
            subdivisionAppId: '',
            subdivisionPlanned: 0,
            subdivisionCommissioned: 0,
            subdivisionRemaining: 0,
            mergerAppId: '',
            separationAppId: '',
            subdivisionFileNo: '',
            mergerFileNo: '',
            separationFileNo: '',

            // Change of Purpose Properties
            changeOfPurposeAppId: '',
            // Set when the picked Change of Purpose covers SEVERAL PLOTS: one file,
            // fanned out into that many new numbers, the source retired once. The
            // form's own single-file path cannot do that, so it routes elsewhere.
            copAppId: '',
            copPlotCount: 0,
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
            applyApplicantToAll: false, // Toggle for batch applicant sync (File Options card)
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
                    this.$nextTick(() => { this.initDistrictSelect2(); this.initStreetNameSelect2(); });

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
                    this.$nextTick(() => this.refreshLocationMapState());

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
                        this.tpSearchResults = this.withTpOtherOption(data.results || []);
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

            // The lookup already appends {id:'__other__'} to every response; this only
            // guarantees it survives a partial result set. It rides along with real search
            // results and never opens a list of its own — a dropdown offering nothing but
            // "Other" the moment the field is touched is noise.
            withTpOtherOption(results) {
                const list = (results || []).filter(r => r.id !== TP_OTHER);

                return [...list, { id: TP_OTHER, text: 'Other (specify)' }];
            },

            selectTpResult(result) {
                this.tpFocusIndex = -1;
                this.tpSearchOpen = false;
                this.tpSearchResults = [];

                if (result.id === TP_OTHER) {
                    // Nothing is committed yet — the number is whatever they type next.
                    this.tpIsOther = true;
                    this.tpSearchQuery = 'Other';
                    this.setTpNo('');
                    this.$nextTick(() => document.getElementById('tp_other_input')?.focus());
                    return;
                }

                this.tpIsOther = false;
                this.tpOtherValue = '';
                this.tpSearchQuery = result.text;
                this.setTpNo(result.id);
            },

            // The typed-in TP number, once "Other" is chosen. Stored upper-cased to match
            // how every other TP number on this form is held.
            onTpOtherInput(value) {
                this.tpOtherValue = value;
                this.setTpNo(String(value || '').trim().toUpperCase());
            },

            // One writer for the three places a TP number has to land: Alpine state, the
            // hidden input the form actually posts, and the current batch entry.
            setTpNo(value) {
                this.tpNo = value;
                const hidden = document.getElementById('generator_tp_no_val');
                if (hidden) hidden.value = value;
                if (this.batchMode) this.updateLocationEntry('tpNo', value);
            },

            clearTpNo() {
                this.tpSearchQuery = '';
                this.tpSearchResults = [];
                this.tpSearchOpen = false;
                this.tpFocusIndex = -1;
                this.tpIsOther = false;
                this.tpOtherValue = '';
                this.setTpNo('');
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
                // In batch mode the per-entry values are the source of truth (the
                // district / LGA inputs only update the entry, not the top-level
                // properties), so read from the current entry to avoid building the
                // string from stale data.
                let dist = this.district, lga = this.lga;
                if (this.batchMode && this.locationEntries[this.currentEntryIndex]) {
                    const entry = this.locationEntries[this.currentEntryIndex];
                    dist = entry.district;
                    lga = entry.lga;
                }
                // The Location line describes WHERE the property is, so it is built
                // from district and LGA only. The plot number is never part of it —
                // a plot identifies the parcel, it does not locate it, and it is
                // already captured in its own field. Every other place that composes
                // a location in this file follows the same rule.
                const parts = [dist, lga].filter(v => v && v.toString().trim());
                const loc = parts.join(', ').toUpperCase();
                this.location = loc;
                if (this.batchMode) {
                    this.updateLocationEntry('location', loc);
                }
            },

            // An extension file's plot number always reads "<plot> & EXTENSION" — that
            // marker is what identifies the parcel as extended, and it stays even when
            // the file number itself is kept as-is (suppressExtensionSuffix).
            // Idempotent: safe to call on every blur / inherit / file-option change.
            syncExtensionPlotSuffix() {
                const apply = (value) => this.fileOption === 'extension'
                    ? withExtensionPlotSuffix(value)
                    : stripExtensionPlotSuffix(value);

                if (this.batchMode && this.locationEntries[this.currentEntryIndex]) {
                    const entry = this.locationEntries[this.currentEntryIndex];
                    const next = apply(entry.plotNo);
                    if (entry.plotNo !== next) this.updateLocationEntry('plotNo', next);
                    return;
                }

                const next = apply(this.plotNo);
                if (this.plotNo !== next) this.plotNo = next;
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
                    this.autoPinLocationOnMap();
                }
            },

            onDistrictOtherInput(val) {
                this.district = val;
                if (this.batchMode) this.updateLocationEntry('district', val);
                this.buildLocation();
                this.autoPinLocationOnMap();
            },

            onLgaChange(val) {
                this.lga = val;
                if (this.batchMode) this.updateLocationEntry('lga', val);
                this.buildLocation();
                this.autoPinLocationOnMap();
            },

            getCurrentEntryCoordinates() {
                if (!this.batchMode || !this.locationEntries[this.currentEntryIndex]) {
                    return {
                        latitude: normalizeGeneratorCoord(this.latitude),
                        longitude: normalizeGeneratorCoord(this.longitude)
                    };
                }

                const entry = this.locationEntries[this.currentEntryIndex];
                return {
                    latitude: normalizeGeneratorCoord(entry.latitude),
                    longitude: normalizeGeneratorCoord(entry.longitude)
                };
            },

            setCurrentEntryCoordinates(lat, lng) {
                const normLat = normalizeGeneratorCoord(lat);
                const normLng = normalizeGeneratorCoord(lng);

                this.latitude = normLat ?? '';
                this.longitude = normLng ?? '';

                if (this.batchMode && this.locationEntries[this.currentEntryIndex]) {
                    this.locationEntries[this.currentEntryIndex].latitude = normLat ?? '';
                    this.locationEntries[this.currentEntryIndex].longitude = normLng ?? '';

                    if (this.applyLocationToAll) {
                        for (let i = 0; i < this.batchQuantity; i++) {
                            if (!this.locationEntries[i]) continue;
                            this.locationEntries[i].latitude = normLat ?? '';
                            this.locationEntries[i].longitude = normLng ?? '';
                        }
                    }
                }
            },

            setMapCoordSource(text) {
                this.mapCoordSource = text || '';
                const sourceEl = document.getElementById('generatorMapCoordSource');
                if (sourceEl) {
                    sourceEl.textContent = this.mapCoordSource ? `(${this.mapCoordSource})` : '';
                }
            },

            updateMapCoordinateDisplays(lat, lng) {
                const latEl = document.getElementById('generatorLatDisplay');
                const lngEl = document.getElementById('generatorLngDisplay');
                if (latEl) latEl.textContent = lat ?? '-';
                if (lngEl) lngEl.textContent = lng ?? '-';
            },

            ensureLocationMap(lat, lng, zoom = 17, recenter = true) {
                if (typeof google === 'undefined' || !google.maps) {
                    return false;
                }

                const mapCanvas = document.getElementById('generatorMapCanvas');
                if (!mapCanvas) {
                    return false;
                }

                const center = {
                    lat: normalizeGeneratorCoord(lat) ?? GENERATOR_MAP_KANO_CENTER.lat,
                    lng: normalizeGeneratorCoord(lng) ?? GENERATOR_MAP_KANO_CENTER.lng,
                };

                if (!this._locationMap) {
                    this._locationMap = new google.maps.Map(mapCanvas, {
                        center,
                        zoom,
                        mapTypeId: 'satellite',
                        streetViewControl: true,
                        fullscreenControl: true
                    });

                    this._locationMap.addListener('click', (e) => {
                        this.setMapPin(e.latLng.lat(), e.latLng.lng(), false, 'Adjusted manually');
                    });
                } else if (recenter) {
                    this._locationMap.panTo(center);
                }

                google.maps.event.trigger(this._locationMap, 'resize');
                this._locationMap.setCenter(center);

                return true;
            },

            setMapPin(lat, lng, recenter = true, sourceLabel = 'Pinned on map') {
                const normLat = normalizeGeneratorCoord(lat);
                const normLng = normalizeGeneratorCoord(lng);
                if (normLat === null || normLng === null) {
                    return;
                }

                if (!this.ensureLocationMap(normLat, normLng, 17, recenter)) {
                    return;
                }

                const markerPosition = { lat: normLat, lng: normLng };

                if (!this._locationMarker) {
                    this._locationMarker = new google.maps.Marker({
                        position: markerPosition,
                        map: this._locationMap,
                        draggable: true,
                        title: 'Drag to adjust exact plot position'
                    });

                    this._locationMarker.addListener('dragend', (e) => {
                        this.setMapPin(e.latLng.lat(), e.latLng.lng(), false, 'Adjusted manually');
                    });
                } else {
                    this._locationMarker.setPosition(markerPosition);
                }

                this.setCurrentEntryCoordinates(normLat, normLng);
                this.updateMapCoordinateDisplays(normLat, normLng);
                this.setMapCoordSource(sourceLabel);

                const mapWrapper = document.getElementById('generatorMapWrapper');
                const mapEmpty = document.getElementById('generatorMapEmpty');
                if (mapWrapper) mapWrapper.classList.remove('hidden');
                if (mapEmpty) mapEmpty.classList.add('hidden');
            },

            clearMapPin() {
                this.setCurrentEntryCoordinates('', '');
                this.updateMapCoordinateDisplays('-', '-');
                this.setMapCoordSource('');

                if (this._locationMarker) {
                    this._locationMarker.setMap(null);
                    this._locationMarker = null;
                }

                const mapWrapper = document.getElementById('generatorMapWrapper');
                const mapEmpty = document.getElementById('generatorMapEmpty');
                if (mapWrapper) mapWrapper.classList.add('hidden');
                if (mapEmpty) mapEmpty.classList.remove('hidden');
            },

            refreshLocationMapState() {
                const coords = this.getCurrentEntryCoordinates();
                if (coords.latitude !== null && coords.longitude !== null) {
                    this.setMapPin(coords.latitude, coords.longitude, true, this.mapCoordSource || 'Stored coordinates');
                } else {
                    this.clearMapPin();
                    // Stepping onto a batch entry that has an LGA but no pin yet: geocode
                    // it the same way picking the LGA would have.
                    this.autoPinLocationOnMap();
                }
            },

            // Auto-pin: the LGA/District pick is itself the geocode input, so the map
            // pins itself as soon as the location is known and "Pin on Map" becomes a
            // manual re-run rather than a required step. A pin the user placed or
            // dragged is never overwritten — only geocoded/empty pins are refreshed.
            autoPinLocationOnMap() {
                const manualSources = ['Pinned on map', 'Adjusted manually', 'Click map to set pin'];
                if (manualSources.includes(this.mapCoordSource || '')) {
                    return;
                }

                clearTimeout(this._autoPinTimer);
                this._autoPinTimer = setTimeout(() => {
                    // The user may still be mid-edit (District then LGA); re-read state
                    // at fire time rather than trusting the values from the trigger.
                    this.pinCurrentLocationOnMap({ auto: true });
                }, 450);
            },

            pinCurrentLocationOnMap(options = {}) {
                const auto = options.auto === true;

                // In batch mode the per-entry values are the source of truth (same
                // reason buildLocation() reads them), so pin the entry on screen.
                let dist = this.district, lga = this.lga;
                if (this.batchMode && this.locationEntries[this.currentEntryIndex]) {
                    const entry = this.locationEntries[this.currentEntryIndex];
                    dist = entry.district;
                    lga = entry.lga;
                }
                const currentDistrict = (dist || '').toString().trim();
                const currentLga = (lga || '').toString().trim();

                // The Location field is auto-built as "<district>, <lga>", so feeding
                // it plus the district again produced "ABASAWA, ALBASU, ABASAWA,
                // Albasu, ..." which the geocoder cannot resolve. Districts are also
                // local names the gazetteer rarely knows, so the LGA-only query is the
                // reliable one: try district-qualified first, fall back to LGA alone.
                const queries = [];
                if (currentDistrict && currentLga) {
                    queries.push([currentDistrict, currentLga, 'Kano', 'Nigeria'].join(', '));
                }
                if (currentLga) {
                    queries.push([currentLga, 'Kano', 'Nigeria'].join(', '));
                } else if (currentDistrict) {
                    queries.push([currentDistrict, 'Kano', 'Nigeria'].join(', '));
                }

                if (typeof google === 'undefined' || !google.maps) {
                    // An auto-pin fires while the modal is still opening, so the library
                    // may genuinely not be there yet — retry quietly instead of nagging.
                    if (auto) {
                        clearTimeout(this._autoPinTimer);
                        this._autoPinTimer = setTimeout(() => this.pinCurrentLocationOnMap({ auto: true }), 1000);
                        return;
                    }
                    Swal.fire({
                        icon: 'warning',
                        title: 'Map not ready',
                        text: 'Map library is still loading. Please try again in a moment.',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }

                const showMapShell = () => {
                    const mapWrapper = document.getElementById('generatorMapWrapper');
                    const mapEmpty = document.getElementById('generatorMapEmpty');
                    if (mapWrapper) mapWrapper.classList.remove('hidden');
                    if (mapEmpty) mapEmpty.classList.add('hidden');
                };

                // Never pan away from a pin that is already set - the marker would
                // still exist but sit off-screen, looking to the user like it vanished.
                const fallbackToManual = (label) => {
                    const coords = this.getCurrentEntryCoordinates();
                    if (coords.latitude !== null && coords.longitude !== null) {
                        this.setMapPin(coords.latitude, coords.longitude, true, this.mapCoordSource || 'Pinned on map');
                        return;
                    }
                    this.ensureLocationMap(GENERATOR_MAP_KANO_CENTER.lat, GENERATOR_MAP_KANO_CENTER.lng, 13, true);
                    showMapShell();
                    this.setMapCoordSource(label);
                };

                if (!queries.length) {
                    // Nothing to geocode yet (no LGA picked). On an auto-run leave the
                    // empty-state card alone so the panel does not flash a blank map.
                    if (!auto) {
                        fallbackToManual('Click map to set pin');
                    }
                    return;
                }

                if (!this._locationGeocoder) {
                    this._locationGeocoder = new google.maps.Geocoder();
                }

                const tryQuery = (index) => {
                    if (index >= queries.length) {
                        if (auto) {
                            // Nothing resolved. Say so on the card and let the user click
                            // the map (or the button) — do not disturb the current view.
                            this.setMapCoordSource('Could not locate - click Pin on Map or click the map');
                            return;
                        }
                        fallbackToManual('Geocode failed - click map to set pin');
                        return;
                    }

                    this._locationGeocoder.geocode({ address: queries[index] }, (results, status) => {
                        if (status === 'OK' && results && results[0] && results[0].geometry && results[0].geometry.location) {
                            const loc = results[0].geometry.location;
                            this.setMapPin(loc.lat(), loc.lng(), true, 'Geocoded from location');
                            return;
                        }
                        tryQuery(index + 1);
                    });
                };

                tryQuery(0);
            },

            applyBackfilledCoordinates(lat, lng, sourceLabel = 'Backfilled from existing file') {
                const normLat = normalizeGeneratorCoord(lat);
                const normLng = normalizeGeneratorCoord(lng);
                if (normLat === null || normLng === null) {
                    return;
                }
                this.setMapPin(normLat, normLng, true, sourceLabel);
            },

            // Street Name mirrors District: a picked value, or "Other" revealing a
            // specify box. `streetName` always holds the resolved name — the hidden
            // input posts that, so the backend never receives the literal "Other".
            onStreetNameChange(val) {
                if (val === 'Other') {
                    this.streetNameIsOther = true;
                    this.streetName = '';
                    if (this.batchMode) this.updateLocationEntry('streetName', '');
                } else {
                    this.streetNameIsOther = false;
                    this.streetName = val;
                    if (this.batchMode) this.updateLocationEntry('streetName', val);
                }
            },

            onStreetNameOtherInput(val) {
                this.streetName = val;
                if (this.batchMode) this.updateLocationEntry('streetName', val);
            },

            // Put a stored value back on screen: an exact option is selected, anything
            // else (a street typed under "Other", or one since removed from the
            // register) reopens the specify box carrying it.
            loadStreetNameField(streetVal) {
                const sel = document.getElementById('generator_street_name');
                if (!sel) return;

                const otherInput = document.getElementById('generator_street_name_other');
                const optionValues = Array.from(sel.options).map(o => o.value);

                if (!streetVal) {
                    sel.value = '';
                    this.streetNameIsOther = false;
                    if (otherInput) otherInput.value = '';
                } else if (optionValues.includes(streetVal)) {
                    sel.value = streetVal;
                    this.streetNameIsOther = false;
                    if (otherInput) otherInput.value = '';
                } else {
                    sel.value = 'Other';
                    this.streetNameIsOther = true;
                    if (otherInput) otherInput.value = streetVal;
                }

                this.streetName = streetVal || '';

                if (window.jQuery && $(sel).hasClass('select2-hidden-accessible')) {
                    $(sel).trigger('change.select2');
                }
            },

            initStreetNameSelect2() {
                try {
                    if (!window.jQuery || typeof $.fn.select2 === 'undefined') return;
                    const $select = $('#generator_street_name');
                    if ($select.length === 0) return;

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    // 800+ streets: searchable, and anchored to the field's own
                    // wrapper so the panel opens under it inside the scrolling modal.
                    const parent = document.getElementById('generatorStreetNameWrap')
                        || document.getElementById('generateModal');

                    $select.select2({
                        placeholder: 'Search or select street name',
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

                    // Select2 fires change through jQuery, which Alpine's @change does
                    // not hear — bridge it, or picking "Other" never opens the box.
                    const self = this;
                    $select.on('change', function () {
                        self.onStreetNameChange(this.value);
                    });
                } catch (e) {
                    console.error('Error in initStreetNameSelect2():', e);
                }
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
                this.houseNo = entry.houseNo || '';
                this.loadStreetNameField(entry.streetName || '');
                this.plotNo = entry.plotNo || '';
                // TP Number (custom search). A number typed through "Other" is restored
                // into the specify box, not the search box, or stepping back through the
                // batch would show it as a lookup hit that was never picked.
                this.tpNo = entry.tpNo || '';
                this.tpIsOther = !!entry.tpIsOther;
                this.tpOtherValue = entry.tpIsOther ? (entry.tpNo || '') : '';
                this.tpSearchQuery = entry.tpIsOther ? 'Other' : (entry.tpNo || '');
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
                this.latitude = entry.latitude || '';
                this.longitude = entry.longitude || '';
                // Rebuild auto location
                this.buildLocation();
                this.refreshLocationMapState();
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

                // Street Name works the same way, and needs the same treatment after
                // a backfill — assigning this.streetName alone moves no widget.
                this.loadStreetNameField(this.streetName || '');

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

                            // An allocation row describes the PARCEL, so it
                            // backfills the Location Details fields — Plot
                            // Number, District and LGA — and the read-only
                            // Location line that buildLocation() composes from
                            // those three.
                            //
                            // It deliberately does NOT touch "Address of
                            // Applicant": that is where the allottee lives, and
                            // an allocation sheet does not record it. Writing
                            // the parcel's location there put a district into
                            // the applicant's address field.
                            const clean = (name) => {
                                const value = $option.attr(name);
                                return (value && value.trim() && value !== 'null') ? value.trim() : '';
                            };

                            const plot = clean('data-plot');
                            const district = clean('data-district');
                            const lga = clean('data-lga');

                            if (plot) {
                                this.plotNo = plot;
                                if (this.batchMode) this.updateLocationEntry('plotNo', plot);
                            }
                            if (district) {
                                this.district = district;
                                if (this.batchMode) this.updateLocationEntry('district', district);
                            }
                            if (lga) {
                                this.lga = lga;
                                if (this.batchMode) this.updateLocationEntry('lga', lga);
                            }

                            // The District/LGA widgets carry no x-model, so
                            // assigning the Alpine values alone leaves them
                            // reading "Select District" / "Select LGA" and
                            // submits an empty LGA. syncLocationSelects() moves
                            // the native selects and handles a district that is
                            // not in the list via the "Other" specify input.
                            this.syncLocationSelects();
                            this.buildLocation();

                            this.isInherited = false;
                            this.updatePreview();
                        });
                    });

                    $select.on('select2:unselect', () => {
                        this.allocationId = null;
                        this.fileName = '';

                        // Clear the same parcel fields the selection filled, so
                        // an abandoned pick cannot leave one allottee's plot
                        // and district sitting under the next one.
                        this.plotNo = '';
                        this.district = '';
                        this.lga = '';
                        if (this.batchMode) {
                            this.updateLocationEntry('plotNo', '');
                            this.updateLocationEntry('district', '');
                            this.updateLocationEntry('lga', '');
                        }
                        this.syncLocationSelects();
                        this.buildLocation();

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

            // ── Reclaimed (missing) serial numbers ────────────────────────────────
            // Every one of these is initialised explicitly. An Alpine `:disabled` bound to
            // an undefined property evaluates to "" and DISABLES the control, which fails
            // silently and looks like a dead field.
            useReclaimedSerial: false,
            reclaimedSerial: '',
            // Which prefix/year the loaded list belongs to, so a refresh triggered for an
            // unrelated reason does not throw away a pick that is still valid.
            reclaimedLoadedFor: '',
            reclaimedLoading: false,
            reclaimedNotice: '',
            reclaimedNoticeKind: 'info',

            get reclaimedNoticeClass() {
                if (this.reclaimedNoticeKind === 'warn') return 'text-amber-700 font-medium';
                if (this.reclaimedNoticeKind === 'error') return 'text-red-600 font-medium';
                return 'text-gray-500';
            },

            onToggleReclaimedSerial() {
                if (this.useReclaimedSerial) {
                    this.loadReclaimedSerials();
                    return;
                }

                // Turning it off hands the field back to the counter, so the form never
                // submits a stale reclaimed serial the officer has since abandoned.
                this.reclaimedSerial = '';
                this.reclaimedNotice = '';
                this.reclaimedLoadedFor = '';
                this.serialNo = getNextSerialForLandUse(this.prefix || this.landUse);
                this.updatePreview();
            },

            onPickReclaimedSerial() {
                if (!this.reclaimedSerial) {
                    this.serialNo = getNextSerialForLandUse(this.prefix || this.landUse);
                    this.updatePreview();
                    return;
                }

                this.serialNo = this.reclaimedSerial;
                this.updatePreview();
            },

            loadReclaimedSerials() {
                const landUse = (this.prefix || this.landUse || '').toString().trim();

                if (!landUse) {
                    this.reclaimedNotice = 'Pick a land use / prefix first.';
                    this.reclaimedNoticeKind = 'warn';
                    return;
                }

                const select = document.getElementById('reclaimedSerialSelect');
                this.reclaimedLoading = true;
                this.reclaimedSerial = '';
                this.reclaimedNotice = '';
                this.reclaimedLoadedFor = `${landUse}-${this.year}`;

                const url = `{{ route('mls-fileno.reclaimable-serials') }}?land_use=${encodeURIComponent(landUse)}&year=${encodeURIComponent(this.year)}&limit=200`;

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(payload => {
                        this.reclaimedLoading = false;

                        if (!payload || !payload.success) {
                            this.reclaimedNotice = (payload && payload.message) || 'Could not load the missing serial numbers.';
                            this.reclaimedNoticeKind = 'error';
                            return;
                        }

                        const data = payload.data || {};
                        const serials = data.serials || [];
                        const blocked = data.blocked || [];

                        if (select) {
                            // Rebuilt rather than patched: the list is only valid for the
                            // land use and year it was fetched for.
                            select.querySelectorAll('option:not([value=""])').forEach(o => o.remove());

                            serials.forEach(entry => {
                                const option = document.createElement('option');
                                option.value = entry.serial;
                                // Say WHERE the number came from. "Freed by delete" was
                                // issued by this system and purged; "never issued" merely
                                // has no record here, and a paper file may still carry it.
                                const tag = entry.origin === 'deleted' ? 'freed by delete' : 'never issued';
                                option.textContent = `${entry.file_number}  —  ${tag}`;
                                select.appendChild(option);
                            });
                        }

                        // Nothing is said when the list has entries — the dropdown already
                        // shows what is available, and the running commentary was noise in a
                        // panel this small. An EMPTY list still speaks up, since a blank
                        // dropdown with no explanation reads as a failure. The window is named
                        // there because digital numbering did not start at 1 (RES-2026 begins
                        // at 565, CON-COM-2026 at 48), so "nothing found" needs its range.
                        if (serials.length === 0) {
                            const window = `${landUse}-${data.year}-${data.digital_floor ?? '?'} to ${(data.current_serial ?? 1) - 1}`;
                            this.reclaimedNotice = `No reusable serials in ${window}.`;
                            this.reclaimedNoticeKind = 'warn';
                        } else {
                            this.reclaimedNotice = '';
                            this.reclaimedNoticeKind = 'info';
                        }

                        // Numbers a delete released that are still held elsewhere (by a pra
                        // or PropID_Master row the cascade does not purge) are deliberately
                        // NOT listed here — the detail was noisy in the panel. The endpoint
                        // still returns them under `blocked`, so it can be surfaced again
                        // without a backend change.
                        if (blocked.length > 0) {
                            console.info(`${blocked.length} deleted serial(s) not yet reusable for ${landUse}-${data.year}:`, blocked);
                        }
                    })
                    .catch(err => {
                        this.reclaimedLoading = false;
                        this.reclaimedNotice = 'Could not load the missing serial numbers.';
                        this.reclaimedNoticeKind = 'error';
                        console.error('reclaimable-serials failed:', err);
                    });
            },

            get isYearEditable() {
                return isOverrideMode;
            },

            get isSerialEditable() {
                return this.fileOption === 'miscellaneous' || this.fileOption === 'sltr' || this.fileOption === 'old_mls' || isOverrideMode;
            },

            get isSerialReadonly() {
                // Readonly for normal (auto-generated), temporary, extension, and SIT types.
                // A reclaimed serial keeps it readonly too — the value comes from the
                // dropdown, which only offers numbers already verified as free.
                return (this.fileOption === 'normal' || this.fileOption === 'regrant' || this.fileOption === 'resettlement' || this.fileOption === 'reissuance' || this.fileOption === 'temporary' || this.fileOption === 'extension' || this.fileOption === 'sit') && !isOverrideMode;
            },

            get isSerialDisabled() {
                // A duplex allocates its own serials, per stage, out of the series each
                // stage's parcels belong to. The number shown is a preview of what the
                // engine will take; typing over it would change nothing and mislead.
                if (this.duplexRecordId) return true;

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
                    return 'Enter Old MLPP number (e.g., 5467, 34874857488758)';
                } else {
                    return 'Auto-generated';
                }
            },

            get serialDescription() {
                if (this.useReclaimedSerial && this.reclaimedSerial) {
                    return 'Reusing missing serial ' + this.reclaimedSerial;
                }
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
                    entry.houseNo || entry.streetName || entry.plotNo || entry.tpNo || entry.location || entry.lga || entry.district
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

                const list = useConversionPrefixes
                    ? this.allAllPrefixes.filter(p => p.prefix.includes('CON-'))
                    : this.allAllPrefixes.filter(p => !p.prefix.includes('CON-'));

                // The Recertification file type keeps only the -RC prefixes. Picking an RC
                // prefix directly is still a valid route on its own, so this narrows the
                // list only when the file type asks for it.
                return this.recertificationWorkflow
                    ? list.filter(p => String(p.prefix || '').toUpperCase().includes('-RC'))
                    : list;
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

                // A stored location often carries an older spelling of the same plot —
                // most often one without the "& EXTENSION" marker the field only gains
                // on blur — so a common prefix counts as the same head, not a new token.
                // Guarded at 3 characters so a one-digit plot cannot swallow a street.
                const sameHead = normalizedPlot && normalizedFirst && (
                    normalizedFirst === normalizedPlot
                    || (Math.min(normalizedFirst.length, normalizedPlot.length) >= 3
                        && (normalizedFirst.startsWith(normalizedPlot) || normalizedPlot.startsWith(normalizedFirst)))
                );

                if (
                    sameHead
                    || /^PLOT\s+/i.test(first)
                    || /^[A-Z]?\d+[A-Z]?$/i.test(first)
                    || /^[A-Z]+-\d+[A-Z]?$/i.test(first)
                ) {
                    parts.shift();
                }

                return parts.join(', ').toUpperCase();
            },

            // Clean an inherited Location string for display: a stored location often
            // still leads with the plot number, which no longer belongs in a location.
            // Idempotent, so it is safe to call after every backfill.
            locationWithoutPlot(location, plotNo = '') {
                return this.stripPlotFromLocation(location, plotNo);
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
                if (this.isRecertificationPrefix) {
                    // The relationship is already known from the prefix — the related file
                    // an RC number points at is the original it recertifies — so the type
                    // is pre-picked rather than left for the officer to restate. A type
                    // already chosen by hand is not overwritten.
                    if (!this.relatedFileType) {
                        this.relatedFileType = 'Recertification';
                    }
                }
                // Prefix controls the new file's numbering, not its selected
                // parents. Preserve primary and additional related files when
                // changing prefixes (including switching away from -RC).
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
                            row.title_locked = row.title !== '';
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
                        const relatedTitle = (
                            data.file_name
                            || data.file_title
                            || (data.record && (data.record.file_name || data.record.FileName || data.record.file_title))
                            || ''
                        ).toString().trim();
                        self.relatedFileTitle = relatedTitle;
                        self.relatedFileTitleLocked = relatedTitle !== '';

                        // A related file's title is also the best default for the new
                        // file's name. Only backfill an empty field so an officer's
                        // deliberate name is never overwritten by a later selection.
                        if (!(self.fileName || '').toString().trim() && relatedTitle) {
                            self.fileName = relatedTitle;
                        }
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
                        // The plot IS inherited from the related file — but into its own
                        // Plot Number field, not into the Location line. A related file
                        // (e.g. CON-COM-2014-123) contributes its address only, so the
                        // line stays "DISTRICT, LGA, STATE" and any plot the stored
                        // string happened to lead with is dropped rather than repeated.
                        if (m.plot_no) {
                            self.plotNo = m.plot_no;
                            self.syncExtensionPlotSuffix();
                        }
                        if (m.location) self.location = self.stripPlotFromLocation(m.location, self.plotNo);
                        if (m.lga) self.lga = m.lga;
                        if (m.district) self.district = m.district;
                        if (m.latitude && m.longitude) {
                            self.applyBackfilledCoordinates(m.latitude, m.longitude, 'Backfilled from linked file');
                        }
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
                this.relatedFileTitleLocked = false;
                this.relatedFileIndexingId = '';
                // Under Re-grant the relationship is fixed by the file option, not by the
                // file that was chosen — clearing the selection must not clear the lock,
                // or the next pick would be stored with no relationship at all.
                this.relatedFileType = this.isRegrantOption ? 'Mother File' : '';
                this.relatedFileTypeOther = '';
                this.extraRelatedFiles = [];
            },

            addRelatedFile() {
                this.extraRelatedFiles.push({ file_no: '', title: '', title_locked: false, type: '', type_other: '', indexing_id: '' });
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
                                    self.houseNo = res.data.house_no || '';
                                    self.streetName = res.data.street_name || '';
                                    self.plotNo = res.data.plot_no || '';
                                    self.lga = res.data.lga || '';
                                    self.district = res.data.district || '';

                                    // Construct location from available components. The
                                    // plot number is deliberately absent — a location
                                    // says where the property is, as in buildLocation().
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

                                    // A final chunk of exactly one plot cannot use batch mode:
                                    // generateBatch validates batch_quantity min:2. Fall back to the
                                    // single-file path, which books that one fragment the same way.
                                    self.batchMode = (parseInt(res.data.next_batch_size) || 0) !== 1;

                                    // Batch mode mints at most batch_cap (200) files per run, so a
                                    // subdivision bigger than that is commissioned in chunks —
                                    // 500 plots = 200 + 200 + 100. next_batch_size is what is left
                                    // capped at 200; run the generator again for each remaining chunk.
                                    const planned      = parseInt(res.data.planned_plots ?? res.data.num_plots) || 0;
                                    const alreadyDone  = parseInt(res.data.commissioned_count) || 0;
                                    const remaining    = parseInt(res.data.remaining_plots ?? planned);
                                    const batchCap     = parseInt(res.data.batch_cap) || 200;
                                    const bQty = parseInt(res.data.next_batch_size) || Math.min(remaining || planned, batchCap);
                                    self.subdivisionPlanned      = planned;
                                    self.subdivisionCommissioned = alreadyDone;
                                    self.subdivisionRemaining    = remaining;
                                    if (bQty > 1) {
                                        let entries = [];
                                        for (let i = 0; i < bQty; i++) {
                                            entries.push({
                                                houseNo: res.data.house_no || '',
                                                streetName: res.data.street_name || '',
                                                plotNo: res.data.plot_no || '',
                                                tpNo: res.data.tp_no || '',
                                                location: self.location,
                                                lga: self.lga,
                                                district: self.district,
                                                latitude: '',
                                                longitude: '',
                                                tracking_id: null
                                            });
                                        }
                                        self.locationEntries = entries;
                                    }

                                    self.batchQuantity = bQty > 1 ? bQty : 1;
                                    if (bQty === 1) {
                                        self.locationEntries = [];
                                    }

                                    // Trigger Alpine reactivity
                                    self.$nextTick(() => {
                                        if (typeof self.updatePreview === 'function') {
                                            self.updatePreview();
                                        }
                                    });
                                    
                                    if (alreadyDone > 0 || bQty < planned) {
                                        const left = Math.max(remaining - bQty, 0);
                                        Swal.fire({
                                            icon: 'info',
                                            title: 'Subdivision Found (chunked)',
                                            html: `Approved for <b>${planned}</b> plots &mdash; <b>${alreadyDone}</b> already commissioned.<br>` +
                                                  `This run will generate <b>${bQty}</b> file numbers (max ${batchCap} per batch).` +
                                                  (left > 0 ? `<br>Come back and repeat for the remaining <b>${left}</b>.` : `<br>This completes the subdivision.`),
                                            confirmButtonText: 'Continue'
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Subdivision Found',
                                            text: `This file has an approved subdivision for ${planned} plots. Batch mode enabled.`,
                                            timer: 3000,
                                            showConfirmButton: false
                                        });
                                    }
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
                    // Both titles describe the merger itself, not something typed — lock
                    // them the same way a selected file's own title is locked.
                    self.relatedFileTitleLocked = true;
                    
                    self.mergerAppId = data.id;
                    
                    // Backfill details
                    self.fileName = data.applicant_name || data.file_title || '';
                    self.houseNo = data.house_no || '';
                    self.streetName = data.street_name || '';
                    self.plotNo = data.plot_no || '';
                    self.lga = data.lga || '';
                    self.district = data.district || '';

                    // Construct location from available components. The plot number
                    // is deliberately absent — a location says where the property is,
                    // as in buildLocation().
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
                                    self.houseNo = res.data.house_no || '';
                                    self.streetName = res.data.street_name || '';
                                    self.plotNo = res.data.plot_no || '';
                                    self.lga = res.data.lga || '';
                                    self.district = res.data.district || '';

                                    // Construct location from available components. The
                                    // plot number is deliberately absent — a location
                                    // says where the property is, as in buildLocation().
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
                                                houseNo: res.data.house_no || '',
                                                streetName: res.data.street_name || '',
                                                plotNo: res.data.plot_no || '',
                                                tpNo: res.data.tp_no || '',
                                                location: self.location,
                                                lga: self.lga,
                                                district: self.district,
                                                latitude: '',
                                                longitude: '',
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

                    // How many plots this one file covers, and so how many numbers
                    // commissioning will mint off it.
                    this.copAppId = data.id || '';
                    this.copPlotCount = Number(data.plot_count || 1);
                    
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
                    this.houseNo = data.house_no || '';
                    this.streetName = data.street_name || '';
                    this.plotNo = data.plot_no || '';
                    this.location = this.locationWithoutPlot(data.location || '', this.plotNo);
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
            // --- Duplex: several parcel updates commissioned as one instruction ---
            duplexRecordId: '',
            duplexRef: '',
            duplexFileCount: 0,
            duplexSources: '',

            // The plan grouped the way it is actually commissioned: one group per stage,
            // each owning a slice of locationEntries. The slice boundaries are not a
            // display choice — they are the arithmetic DuplexCommitService walks with
            // its own cursor, so entry N on this screen is the file stage K commissions.
            duplexStages: [],
            duplexStageIndex: 0,

            /** The stage that owns a given per-file entry, or null when no duplex drives. */
            duplexStageFor(entryIndex) {
                if (!this.duplexRecordId || !this.duplexStages.length) return null;

                return this.duplexStages.find(
                    g => entryIndex >= g.entry_start && entryIndex < g.entry_start + g.entry_count
                ) || null;
            },

            duplexStage() {
                return this.duplexStageFor(this.currentEntryIndex);
            },

            /**
             * Point the card at the stage the current file belongs to.
             *
             * Land use, prefix and serial belong to the STAGE, not to the duplex: a plan
             * that changes purpose issues its files out of two different series, and
             * showing the duplex's own land use named the wrong one for every file.
             */
            syncDuplexStage() {
                const g = this.duplexStage();
                if (!g) return;

                this.duplexStageIndex = this.duplexStages.indexOf(g);

                if (g.land_use_code) this.landUse = g.land_use_code;
                if (g.land_use) this.prefix = g.land_use;
                if (g.application_type) this.applicationType = g.application_type;

                // Each file in a stage takes the next serial in that stage's series, so
                // the number shown moves with the entry rather than sitting on the
                // stage's first.
                if (g.serial_start) {
                    this.serialNo = g.serial_start + (this.currentEntryIndex - g.entry_start);
                }

                this.serialRangePreview = g.serial_preview || '--';
                this.updatePreview();
            },

            /** Jump to a stage's first file. */
            goToDuplexStage(i) {
                const g = this.duplexStages[i];
                if (!g) return;

                this.saveCurrentApplicantToEntry();
                this.currentEntryIndex = g.entry_start;
                this.loadApplicantFromEntry();
                this.syncDuplexStage();
                this.$nextTick(() => this.loadLocationFieldsForEntry());
            },

            /**
             * How the counter reads. "1 of 86" told the officer nothing about what the
             * file is; the stage and the position within it do.
             */
            duplexEntryLabel() {
                const g = this.duplexStage();
                if (!g) return '';

                return `${g.label} — ${this.currentEntryIndex - g.entry_start + 1} of ${g.entry_count}`;
            },

            /**
             * The files an Apply-to-All touches.
             *
             * This stage's, when a duplex drives. Copying one holder across a stage
             * boundary is usually wrong: a merger's or a change of purpose's file belongs
             * to the mother's holder, and subdivided plots usually do not.
             */
            duplexApplyRange() {
                const total = Math.min(this.batchQuantity, this.locationEntries.length);
                const g = this.duplexStage();

                if (!g) return { start: 0, end: total, label: `all ${total} files in the batch` };

                const end = Math.min(g.entry_start + g.entry_count, this.locationEntries.length);

                return { start: g.entry_start, end, label: `all ${end - g.entry_start} file(s) in ${g.label}` };
            },

            /** True while a duplex is deciding this field for the officer. */
            get duplexLocked() {
                return !!this.duplexRecordId;
            },

            openDuplexFileModal() {
                window._duplexCallback = (item) => {
                    this.duplexRecordId = item.id;
                    this.duplexRef = item.duplex_id;
                    this.duplexFileCount = item.file_count || 0;
                    this.duplexSources = item.sources || '';

                    // The applicant carries through to the commissioning fields so the
                    // rest of the modal reads like any other file type.
                    if (item.applicant) this.fileName = item.applicant;

                    // A duplex NEVER commissions as a single file: even one stage is a
                    // run of its own, and most carry several. Batch mode is therefore
                    // forced on and the quantity comes from the duplex, not the officer.
                    this.batchMode = true;
                    this.batchQuantity = item.file_count || 0;

                    window.renderDuplexPlan(item.id);
                };

                document.getElementById('duplexFileModal').classList.remove('hidden');
                window.searchDuplexFiles('');
            },

            applyFileTypeWorkflow(val) {
                this.fileTypeWorkflow = val;

                // Leaving the Duplex file type releases the batch controls it locked.
                if (val !== 'duplex' && this.duplexRecordId) {
                    this.duplexRecordId = '';
                    this.duplexRef = '';
                    this.duplexFileCount = 0;
                    this.duplexSources = '';
                    this.duplexStages = [];
                    this.duplexStageIndex = 0;
                    this.batchMode = false;
                    const box = document.getElementById('duplexPlanReview');
                    if (box) { box.classList.add('hidden'); box.innerHTML = ''; }
                    const bd = document.getElementById('duplexBatchBreakdown');
                    if (bd) bd.innerHTML = '';
                }

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

                // A recertification is an ordinary file number carrying an -RC prefix, so it
                // is NOT a file_option of its own: the backend reads the RC token off the
                // number. All this does is narrow the Prefix list and drop a prefix that no
                // longer belongs to it.
                this.recertificationWorkflow = val === 'recertification';
                if (this.recertificationWorkflow) {
                    if (!String(this.prefix || '').toUpperCase().includes('-RC')) {
                        this.prefix = '';
                        this.isRecertificationPrefix = false;
                    }
                    this.fileOption = 'normal';
                    this.updateFileOption();
                    return;
                }

                // Re-grant numbers like a normal file but keeps its own tag so the backend
                // records Source as "Re-grant" instead of "Direct Allocation".
                this.fileOption = val === '' ? 'normal' : val;
                this.updateFileOption();
            },

            updateFileOption() {
                // Detect a fresh transition INTO the extension file type, so the existing-file
                // selector opens exactly once rather than on every re-render.
                const switchingToExtension = this.fileOption === 'extension' && this._lastFileOption !== 'extension';

                // Re-grant: the selected existing file IS the mother file, so the relationship
                // is settled before the officer is asked. Enable and lock it on the way in.
                // Only on the transition, never on every re-render — updateFileOption() runs
                // again on unrelated changes, and re-forcing the type would overwrite an
                // officer who had deliberately changed it.
                const switchingToRegrant = this.fileOption === 'regrant' && this._lastFileOption !== 'regrant';
                if (switchingToRegrant) {
                    this.hasRelatedFile = true;
                    this.relatedFileType = 'Mother File';
                } else if (!this.isRegrantOption && this._lastFileOption === 'regrant' && this.relatedFileType === 'Mother File') {
                    // Leaving Re-grant releases the lock, but only the value the lock itself
                    // put there. A type the officer picked by hand survives the switch, and
                    // the selected file is never cleared (see clearRelatedFile's caller note).
                    this.relatedFileType = '';
                }

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
                    this.suppressExtensionSuffix = false;
                    this.isInherited = false;
                    // Reset fields that might have been inherited
                    this.fileName = '';
                    this.houseNo = '';
                    this.streetName = '';
                    this.plotNo = '';
                    this.clearTpNo();
                    this.location = '';
                    this.lga = '';
                    this.clearPassport();
                    this.phone_no = '';
                    this.address = '';
                    this.rep_phone_no = '';
                    this.rep_address = '';
                } else if (this.fileOption === 'normal' || this.fileOption === 'regrant' || this.fileOption === 'resettlement' || this.fileOption === 'reissuance') {
                    this.isInherited = false;
                    // Reset SIT-specific overrides if switching from SIT
                    if (this.landUse === 'SIT') {
                        this.landUse = '';
                        this.customerType = 'Individual';
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
                    this.subdivisionPlanned = 0;
                    this.subdivisionCommissioned = 0;
                    this.subdivisionRemaining = 0;
                    this.mergerAppId = '';
                    this.separationAppId = '';
                    this.subdivisionFileNo = '';
                    this.mergerFileNo = '';
                    this.separationFileNo = '';
                    this.batchMode = false;
                    this.isInherited = false;

                    // Reset fields
                    this.fileName = '';
                    this.houseNo = '';
                    this.streetName = '';
                    this.plotNo = '';
                    this.clearTpNo();
                    this.location = '';
                    this.lga = '';
                }

                this._lastFileOption = this.fileOption;

                // Leaving the extension type must drop the "& EXTENSION" plot marker, and
                // entering it must (re)apply it to whatever plot is already on the form.
                this.syncExtensionPlotSuffix();

                // An extension always extends an existing file, so open the selector straight
                // away — the removed File-vs-Plot modal used to do this after the choice.
                if (switchingToExtension && typeof window.openExtensionFileSelector === 'function') {
                    window.openExtensionFileSelector();
                }

                this.updatePreview();
            },

            updatePreview() {
                // A reclaimed serial is an explicit choice, so the auto-fill below must not
                // overwrite it. Without this guard the field and the preview both snapped
                // straight back to the counter's next number the instant one was picked —
                // the dropdown said 231 while the preview still read IND-2026-273.
                const holdingReclaimedSerial = this.useReclaimedSerial && this.reclaimedSerial;

                // Auto-update serial number when land use changes for normal, subdivision, merger, and temporary files
                if (!holdingReclaimedSerial && ['normal', 'regrant', 'resettlement', 'reissuance', 'subdivision', 'merger', 'separation', 'temporary'].includes(this.fileOption) && (this.landUse || this.prefix) && !isOverrideMode) {
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
                    previewText = buildExtensionPreview(this.existingFileNo || baseFileNumber, this.suppressExtensionSuffix) || '-';
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
                // While a duplex drives, the serial belongs to the current stage and is
                // set by syncDuplexStage(). Recomputing it from the form's own land use
                // would drop back to one series for the whole run.
                if (this.duplexRecordId) {
                    this.syncDuplexStage();
                    return;
                }

                // A reclaimed list is only valid for the prefix and year it was fetched
                // for, so changing the land use has to discard the current pick and reload
                // — otherwise a RES serial stays selected while the form now says COM.
                if (this.useReclaimedSerial) {
                    // Only discard the pick when the list it came from no longer applies.
                    // refreshSerialNumber() also fires after an unrelated serial-map
                    // refresh, and clearing unconditionally wiped a valid selection.
                    const wanted = `${(this.prefix || this.landUse || '').toString().trim()}-${this.year}`;

                    if (wanted !== this.reclaimedLoadedFor) {
                        this.reclaimedSerial = '';
                        this.loadReclaimedSerials();
                    }
                    return;
                }

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
                        houseNo: '',
                        streetName: '',
                        plotNo: '',
                        tpNo: '',
                        location: '',
                        lga: '',
                        district: '',
                        latitude: '',
                        longitude: '',
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

                    // Which way the TP number was entered travels with it, so stepping
                    // back to an entry reopens the specify box rather than showing the
                    // number as a lookup hit.
                    if (field === 'tpNo') {
                        this.locationEntries[this.currentEntryIndex].tpIsOther = this.tpIsOther;
                    }

                    if (this.applyLocationToAll && ['houseNo', 'streetName', 'plotNo', 'tpNo', 'location', 'lga', 'district', 'latitude', 'longitude'].includes(field)) {
                        for (let i = 0; i < this.batchQuantity; i++) {
                            if (this.locationEntries[i]) {
                                this.locationEntries[i][field] = value;
                                if (field === 'tpNo') {
                                    this.locationEntries[i].tpIsOther = this.tpIsOther;
                                }
                            }
                        }
                    }

                    if (this.applyApplicantToAll && ['file_name', 'phone_no', 'address'].includes(field)) {
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
                    // Stepping back over a stage boundary changes the series, the prefix
                    // and the serial, so the card has to follow the file.
                    this.syncDuplexStage();
                    this.$nextTick(() => this.loadLocationFieldsForEntry());
                }
            },

            nextEntry() {
                if (this.currentEntryIndex < this.batchQuantity - 1) {
                    this.saveCurrentApplicantToEntry();
                    this.currentEntryIndex++;
                    if (!this.locationEntries[this.currentEntryIndex]) {
                        this.locationEntries[this.currentEntryIndex] = {
                            houseNo: '',
                            streetName: '',
                            plotNo: '',
                            tpNo: '',
                            location: '',
                            lga: '',
                            district: '',
                            latitude: '',
                            longitude: '',
                            tracking_id: null,
                            file_name: this.fileName || '',
                            phone_no: this.phone_no || '',
                            address: this.address || ''
                        };
                    } else {
                        this.loadApplicantFromEntry();
                    }
                    // Stepping forward over a stage boundary changes the series, the
                    // prefix and the serial, so the card has to follow the file.
                    this.syncDuplexStage();
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
                return entry.houseNo || entry.streetName || entry.plotNo || entry.tpNo || entry.location || entry.lga || entry.district;
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
                            houseNo: '',
                            streetName: '',
                            plotNo: '',
                            tpNo: '',
                            location: '',
                            lga: '',
                            district: '',
                            latitude: '',
                            longitude: '',
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

                // A duplex is a batch of batches. One "start to start+N-1" range across
                // the whole run is wrong the moment two stages draw on different series
                // — a plan that changes purpose always does — so the stage's own preview
                // stands and this generic one is skipped.
                if (this.duplexRecordId) {
                    const g = this.duplexStage();
                    this.serialRangePreview = g ? g.serial_preview : '-';

                    if (g && g.land_use && this.year && g.serial_start) {
                        this.preview = g.entry_count > 1
                            ? `${g.land_use}-${this.year}-${g.serial_start}-${g.serial_start + g.entry_count - 1}`
                            : `${g.land_use}-${this.year}-${g.serial_start}`;
                    }

                    return;
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
                // No plot number here either — this must match buildLocation()
                // exactly, or a batch apply and a single entry would disagree.
                const builtLocation = [currentEntry.district, currentEntry.lga]
                    .filter(v => v && v.toString().trim())
                    .join(', ')
                    .toUpperCase();

                const locationData = {
                    houseNo: currentEntry.houseNo || '',
                    streetName: currentEntry.streetName || '',
                    plotNo: currentEntry.plotNo || '',
                    tpNo: currentEntry.tpNo || '',
                    district: currentEntry.district || '',
                    lga: currentEntry.lga || '',
                    location: builtLocation,
                    latitude: currentEntry.latitude || '',
                    longitude: currentEntry.longitude || ''
                };

                // Apply to every entry in range. Without a duplex that is the whole
                // batch; with one it is the stage being filled in, because the stages
                // describe different parcels.
                const range = this.duplexApplyRange();
                const count = range.end - range.start;
                for (let i = range.start; i < range.end; i++) {
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
                this.latitude = locationData.latitude;
                this.longitude = locationData.longitude;
                this.refreshLocationMapState();

                // Show success notification
                Swal.fire({
                    icon: 'success',
                    title: 'Location Applied',
                    text: `Location details applied to ${range.label}`,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Refresh icons after the DOM updates
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },

            // Apply the current applicant's details to every file in the batch. The rest
            // of the File Options card (file type, prefix, land use, purpose, customer
            // type, gender) is already batch-wide, so only the per-entry fields are copied.
            applyApplicantToBatch() {
                if (!this.batchMode || this.batchQuantity < 2) {
                    return;
                }

                // The applicant inputs bind to top-level state, not to the entry, so flush
                // what is on screen into the current entry before broadcasting it.
                this.saveCurrentApplicantToEntry();

                const currentEntry = this.locationEntries[this.currentEntryIndex];
                if (!currentEntry) {
                    console.error('Apply applicant to batch failed: current entry at index ' + this.currentEntryIndex + ' is undefined');
                    return;
                }

                const applicantData = {
                    file_name: currentEntry.file_name || '',
                    phone_no: currentEntry.phone_no || '',
                    address: currentEntry.address || ''
                };

                const range = this.duplexApplyRange();
                const count = range.end - range.start;
                for (let i = range.start; i < range.end; i++) {
                    if (this.locationEntries[i]) {
                        this.locationEntries[i] = {
                            ...this.locationEntries[i],
                            ...applicantData
                        };
                    } else {
                        console.warn('Location entry at index ' + i + ' is missing during batch applicant apply');
                    }
                }

                // Keep the top-level bound fields in sync with what was just applied
                this.fileName = applicantData.file_name;
                this.phone_no = applicantData.phone_no;
                this.address = applicantData.address;

                Swal.fire({
                    icon: 'success',
                    title: 'Applicant Applied',
                    text: `Applicant details applied to ${range.label}`,
                    timer: 2000,
                    showConfirmButton: false
                });

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

    // The File-vs-Plot extension choice was collapsed into a single "… AND EXTENSION"
    // extension, so the chooser modal (openExtensionTypeModal / selectExtensionType /
    // cancelExtensionTypeSelection) and the Plot Extension submitter that kept the original
    // file number are gone. Existing plot_extensions records are untouched and still render
    // in the file-numbers list; only the path that CREATES new ones was removed.
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
                    if (match.latitude && match.longitude && typeof alpineData.applyBackfilledCoordinates === 'function') {
                        alpineData.applyBackfilledCoordinates(match.latitude, match.longitude, 'Backfilled from selected file');
                    }
                    if (match.house_no) alpineData.houseNo = match.house_no;
                    // syncLocationSelects() already ran above, so a bare assignment here
                    // would set the value without moving the select. loadStreetNameField()
                    // does both, and reopens the specify box for a street not in the list.
                    if (match.street_name && typeof alpineData.loadStreetNameField === 'function') {
                        alpineData.loadStreetNameField(match.street_name);
                    }
                    if (match.plot_no) alpineData.plotNo = match.plot_no;
                    // The inherited plot must carry the extension marker (see
                    // syncExtensionPlotSuffix); it is a no-op for every other file type.
                    if (typeof alpineData.syncExtensionPlotSuffix === 'function') {
                        alpineData.syncExtensionPlotSuffix();
                    }
                    // Strip any plot number the parent file's stored Location still
                    // leads with, so an inherited line matches what buildLocation()
                    // would produce for a fresh entry.
                    if (typeof alpineData.locationWithoutPlot === 'function') {
                        alpineData.location = alpineData.locationWithoutPlot(alpineData.location, alpineData.plotNo);
                    }
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
        // Hide the Old / Related File No lines again (form.reset() clears the value, not the row)
        setCommissioningOldFileNumber('');
        setCommissioningRelatedFileNumber('');
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

    // ── Passport photograph on the commissioning sheet ────────────────────────────
    // The photograph uploaded when the file number was generated is filed into EDMS
    // (scannings row of type "Passport Photograph"), so the PDF has to ask the server
    // for it — jsPDF runs in the browser and cannot read the storage disk.
    async function fetchCommissioningPassport(fileNumber) {
        const number = String(fileNumber || '').trim();
        if (!number) return null;

        try {
            const res = await fetch('{{ route('commissioning-sheet.passport-photo') }}?file_number=' + encodeURIComponent(number), {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return null;
            const result = await res.json();
            return result && result.image ? result.image : null;
        } catch (e) {
            console.warn('Passport photograph lookup failed', e);
            return null;
        }
    }

    // The old (duplicated) number a Re-Issuance replaces, plus the district — which has
    // no field on the commissioning form at all. Reprint paths build their FormData from
    // an API row and may not carry either, so both are looked up here rather than left
    // off the sheet.
    async function fetchCommissioningFileLinks(fileNumber) {
        const empty = { old_file_number: '', related_file_number: '', district: '', source: '' };
        const number = String(fileNumber || '').trim();
        if (!number) return empty;

        try {
            const res = await fetch('{{ route('commissioning-sheet.file-links') }}?file_number=' + encodeURIComponent(number), {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return empty;
            const payload = await res.json();
            return {
                old_file_number: String(payload.old_file_number || '').trim(),
                related_file_number: String(payload.related_file_number || '').trim(),
                district: String(payload.district || '').trim(),
                // mls_file_no.source — the same value the file list shows under SOURCE.
                source: String(payload.source || '').trim()
            };
        } catch (e) {
            console.warn('File links lookup failed', e);
            return empty;
        }
    }

    // The KLAES mark at the foot of the sheet, left of the centred "Generated on" line
    // and on the same baseline as the right-hand logo. Its width follows the image's own
    // proportions so the mark is never stretched.
    function drawFooterLeftLogo(doc, image) {
        if (!image) return;

        const height = 11.5, left = 25, bottom = 290;
        let width = 31.8; // 1600x578 as filed
        try {
            const props = doc.getImageProperties(image);
            if (props && props.width && props.height) {
                width = height * (props.width / props.height);
            }
        } catch (e) { /* keep the filed proportions */ }

        try {
            doc.addImage(image, 'PNG', left, bottom - height, width, height);
        } catch (e) {
            console.warn('Could not draw the footer logo', e);
        }
    }

    // Draws a body label, shrinking it just enough to stay clear of the value column.
    // "Related FileNo/Old FileNo:" is longer than the column was drawn for; every other
    // label keeps the normal 10pt.
    function drawCommissioningLabel(doc, label, x, y, valueStartX) {
        const available = valueStartX - x - 2;
        doc.setFont('helvetica', 'bold');

        let size = 10;
        while (size > 7 && doc.getTextWidth(label) > available) {
            size -= 0.5;
            doc.setFontSize(size);
        }

        doc.text(label, x, y);
        doc.setFontSize(10);
    }

    // Prints the photograph in the right margin beside the QR code. Files commissioned
    // without a passport simply leave the space blank.
    function drawCommissioningPassport(doc, image) {
        if (!image) return;

        // Directly under the right-hand ministry logo and flush with its right edge
        // (logo occupies x 170-190, y 12-32), so the two read as one column.
        const x = 160, y = 35, w = 30, h = 34;
        try {
            const format = String(image).slice(0, 30).toUpperCase().includes('IMAGE/PNG') ? 'PNG' : 'JPEG';
            doc.addImage(image, format, x, y, w, h);
            doc.setLineWidth(0.3);
            doc.rect(x, y, w, h);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
        } catch (e) {
            console.warn('Could not draw passport photograph', e);
        }
    }

    // jsPDF applies `align` in the UNROTATED axis, so `align: 'center'` on rotated
    // text lands off-centre. Anchor at the left edge instead and walk back half the
    // string length along the rotated baseline so the text straddles the page centre.
    // `yOffset` nudges the watermark off the vertical centre (negative = higher).
    function drawCenteredWatermark(doc, text, angle = 45, size = 45, yOffset = -25) {
        const pageW = doc.internal.pageSize.getWidth();
        const pageH = doc.internal.pageSize.getHeight();
        doc.setTextColor(255, 0, 0);
        doc.setGState(doc.GState({ opacity: 0.2 }));
        doc.setFontSize(size);
        const half = doc.getTextWidth(text) / 2;
        const rad = angle * Math.PI / 180;
        doc.text(text, pageW / 2 - half * Math.cos(rad), pageH / 2 + yOffset + half * Math.sin(rad), {
            angle: angle,
            baseline: 'middle'
        });
        doc.setGState(doc.GState({ opacity: 1.0 }));
        doc.setTextColor(0, 0, 0);
    }

    // ── Page 2: the File Tracking Sheet ──────────────────────────────────────────
    // The sheet is built in JavaScript by printFileTrackerDetails() in
    // create_file_tracker_page/partials/js.blade.php, and this screen draws its
    // commissioning sheet with jsPDF, so no markup can be shared. The real sheet is
    // therefore produced by that same function and captured as page 2 — one sheet in
    // the codebase, no second copy to drift.
    //
    // It has to run on the File Tracking page itself, not on a stripped-down host: that
    // partial's start-up code binds to the tracking page's DOM, and a throw there stops
    // the script, leaving its top-level `const`s (OFFICE_TITLE_RE and friends) in the
    // temporal dead zone. The functions are hoisted and look callable, but the constants
    // they close over are not, so the sheet dies with a ReferenceError. Loading the page
    // it was written for is what makes it initialise.
    //
    // URLs stay RELATIVE. route() builds absolutes from APP_URL, and an officer browsing
    // on any other origin would get a cross-origin iframe whose contentDocument is null.
    //
    // Best-effort throughout: a commissioning sheet must still print when the tracking
    // sheet cannot be built.
    const TRACKING_PAGE_URL = "{{ route('create-file-tracker.index', [], false) }}";
    const TRACKING_LIST_URL = "{{ route('create-file-tracker.list', [], false) }}";

    function makeHiddenFrame() {
        const frame = document.createElement('iframe');
        // Off-screen rather than display:none — a hidden iframe lays nothing out and
        // html2canvas would capture an empty box. Sized for the A4 landscape sheet.
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1160px;height:1000px;border:0;visibility:hidden;';
        document.body.appendChild(frame);
        return frame;
    }

    function frameDocumentOf(frame) {
        try {
            return frame.contentDocument || null;
        } catch (e) {
            // Cross-origin reads throw in some browsers rather than returning null.
            return null;
        }
    }

    // Resolves once the tracking page's script has defined the sheet builder.
    function waitForTrackingPage(frame, timeoutMs = 25000) {
        return new Promise((resolve) => {
            const startedAt = Date.now();
            const poll = setInterval(() => {
                let win = null;
                try { win = frame.contentWindow; } catch (e) { win = null; }

                if (win && typeof win.printFileTrackerDetails === 'function'
                    && typeof win.transformApiTracker === 'function') {
                    clearInterval(poll);
                    return resolve(win);
                }

                if (Date.now() - startedAt > timeoutMs) {
                    clearInterval(poll);
                    return resolve(null);
                }
            }, 150);
        });
    }

    // Corrections applied ONLY to the copy being captured — never to the sheet the File
    // Tracking page prints.
    //
    // .info-value carries `overflow:hidden; text-overflow:ellipsis` on an *inline* span.
    // Browsers ignore overflow on non-replaced inline elements, so it renders fine on
    // screen and on paper; html2canvas honours it anyway and clips each value to the
    // line box, slicing the bottom off "IND-2026-254", "SALISU UMAR" and the rest.
    // Making the span a block box with room to breathe restores it, and dropping the
    // ellipsis keeps a long value whole rather than truncating it in the raster.
    // The sheet's logos are served from app.klaes.ng, which sends no
    // Access-Control-Allow-Origin. html2canvas requests them with crossOrigin="anonymous"
    // (allowTaint has to stay false or the canvas cannot be exported), the browser
    // refuses, and every one of them is dropped — which is why the footer mark below
    // "Honorable Commissioner" came out blank. The same files ship in this app's own
    // public/assets/logo, so the captured copy is pointed at those instead.
    //
    // Paths are root-relative: asset() builds from APP_URL, which is not necessarily the
    // origin the officer is browsing, and an absolute one would just be cross-origin again.
    const SHEET_LOCAL_IMAGES = {
        'assets/logo/ministry1.jpg': "{{ parse_url(asset('assets/logo/ministry1.jpg'), PHP_URL_PATH) }}",
        'assets/logo/ministry2.png': "{{ parse_url(asset('assets/logo/ministry2.png'), PHP_URL_PATH) }}",
        'storage/upload/logo/logo.png': "{{ parse_url(asset('assets/logo/logo.png'), PHP_URL_PATH) }}",
        'storage/upload/logo/Klase.png': "{{ parse_url(asset('assets/logo/logo.png'), PHP_URL_PATH) }}",
    };

    function localiseSheetImages(sheetDoc) {
        sheetDoc.querySelectorAll('img').forEach(img => {
            const src = img.getAttribute('src') || '';
            for (const [remote, local] of Object.entries(SHEET_LOCAL_IMAGES)) {
                if (src.includes(remote) && local) {
                    img.setAttribute('src', local);
                    // A local file needs no crossOrigin dance, and setting it would send
                    // the request as CORS for no reason.
                    img.removeAttribute('crossorigin');
                    break;
                }
            }
        });
    }

    // Resolves once every image in the sheet has loaded or failed, so nothing is
    // captured half-drawn.
    function waitForSheetImages(sheetDoc, timeoutMs = 8000) {
        const images = Array.from(sheetDoc.querySelectorAll('img'));
        const pending = images.filter(img => !img.complete);
        if (!pending.length) return Promise.resolve();

        return Promise.race([
            Promise.all(pending.map(img => new Promise(resolve => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            }))),
            new Promise(resolve => setTimeout(resolve, timeoutMs)),
        ]);
    }

    function applyCaptureFixes(sheetDoc) {
        try {
            localiseSheetImages(sheetDoc);

            const style = sheetDoc.createElement('style');
            style.textContent = `
                .info-value {
                    display: inline-block !important;
                    overflow: visible !important;
                    text-overflow: clip !important;
                    line-height: 1.6 !important;
                    padding-bottom: 1px !important;
                    max-width: 100% !important;
                }
                .info-row { align-items: start !important; }
                /* "IN-TRANSIT DIIT" is half again as long as "IN TRANSIT"; ease the
                   size so it still sits inside the page once rotated. */
                .copy-watermark { font-size: 3.4rem !important; }
            `;
            (sheetDoc.head || sheetDoc.documentElement).appendChild(style);
        } catch (e) {
            console.error('Could not apply tracking sheet capture fixes:', e);
        }
    }

    // Renders finished sheet HTML in its own frame and hands back that frame.
    function renderSheetHtml(html) {
        return new Promise((resolve) => {
            const frame = makeHiddenFrame();
            let settled = false;
            const done = () => { if (!settled) { settled = true; resolve(frame); } };

            frame.addEventListener('load', () => setTimeout(done, 150), { once: true });
            // srcdoc keeps the frame on this origin, so its document stays readable.
            frame.srcdoc = html;
            setTimeout(done, 6000);
        });
    }

    async function appendTrackingSheetPage(doc, fileNumber) {
        const number = (fileNumber || '').toString().trim();
        if (!number || typeof html2canvas === 'undefined') {
            return false;
        }

        let pageFrame = null;
        let sheetFrame = null;

        try {
            // 1. The tracking page, purely as the place its own sheet code can run.
            pageFrame = makeHiddenFrame();
            pageFrame.src = TRACKING_PAGE_URL;

            const win = await waitForTrackingPage(pageFrame);
            if (!win) {
                console.warn('Tracking sheet page skipped: the File Tracking page did not '
                    + 'initialise in its frame (check that the URL is same-origin).');
                return false;
            }

            // 2. The tracker, from the same endpoint and the same transform the File Log
            //    Table uses, so the sheet gets exactly the object it expects.
            const response = await fetch(
                `${TRACKING_LIST_URL}?per_page=25&status=all&search=${encodeURIComponent(number)}`,
                { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }
            );
            const payload = await response.json();
            const rows = (payload && payload.success && Array.isArray(payload.data)) ? payload.data : [];

            if (!rows.length) {
                // No tracking for this file — one page is the right answer.
                return false;
            }

            const norm = (v) => String(v || '').replace(/\s+/g, '').toUpperCase();
            const trackers = rows.map(r => win.transformApiTracker(r)).filter(Boolean);
            // A search can match neighbouring numbers, so prefer the exact file.
            const tracker = trackers.find(t => norm(t.fileNo) === norm(number)) || trackers[0];
            if (!tracker) return false;

            // 3. The sheet. In-Transit copy only: the black-and-white File Copy is a
            //    registry document and does not belong in the sheet that travels.
            const html = win.printFileTrackerDetails(tracker, {
                returnHtml: true,
                copyKinds: ['transit'],
                transitWatermark: 'IN-TRANSIT DIIT',
            });
            if (!html) {
                console.warn('Tracking sheet page skipped: the sheet builder returned nothing.');
                return false;
            }

            // 4. Render it, then let its own paginator split the movement history the
            //    same way the tracking page does when it prints.
            sheetFrame = await renderSheetHtml(html);
            const sheetDoc = frameDocumentOf(sheetFrame);
            if (!sheetDoc) {
                console.warn('Tracking sheet page skipped: the rendered sheet could not be read.');
                return false;
            }

            applyCaptureFixes(sheetDoc);

            try {
                sheetDoc.querySelectorAll('.print-container').forEach(root => {
                    if (typeof win.paginateTrackingSheet === 'function') {
                        win.paginateTrackingSheet(sheetDoc, root);
                    }
                });
            } catch (e) {
                console.error('Tracking sheet pagination failed:', e);
            }

            // 5. Each .print-container is one printed page, so each becomes one here.
            const pages = Array.from(sheetDoc.querySelectorAll('.print-container'));
            if (!pages.length) {
                console.warn('Tracking sheet page skipped: the sheet rendered no pages.');
                return false;
            }

            // The logos were re-pointed above, so they are loading again; capturing now
            // would catch them half-drawn or not at all.
            await waitForSheetImages(sheetDoc);

            for (const page of pages) {
                const canvas = await html2canvas(page, {
                    scale: 2,
                    useCORS: true,
                    // Must stay false: a tainted canvas cannot be exported, and the sheet
                    // pulls its ministry logo from an external host. A logo that will not
                    // load cross-origin is dropped rather than costing the whole page.
                    allowTaint: false,
                    backgroundColor: '#ffffff',
                    logging: false,
                });

                // A4 landscape, matching the sheet's own @page rule.
                const pageW = 297;
                const pageH = 210;
                const margin = 5;
                const usableW = pageW - margin * 2;
                const imgH = Math.min((canvas.height / canvas.width) * usableW, pageH - margin * 2);

                doc.addPage([pageW, pageH], 'landscape');
                doc.addImage(canvas.toDataURL('image/png'), 'PNG', margin, margin, usableW, imgH);
            }

            return true;
        } catch (error) {
            // Never cost the officer page 1.
            console.error('Could not append the tracking sheet page:', error);
            return false;
        } finally {
            [sheetFrame, pageFrame].forEach(f => {
                if (f && f.parentNode) f.parentNode.removeChild(f);
            });
        }
    }

    async function generateCommissioningSheetPDF(formData, watermarkText = 'ORIGINAL') {
        try {
            showGlobalLoading('Generating PDF...');

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            // Fetch logos
            const logo1Base64 = await getImageBase64('/assets/logo/logo1.png') || await getImageBase64('/assets/logo/logo1.jpg') || await getImageBase64('/assets/logo/logoKlase.png');
            const logo2Base64 = await getImageBase64('/assets/logo/ministry2.png') || await getImageBase64('http://app.klaes.ng/assets/logo/ministry2.png') || await getImageBase64('/assets/logo/logo3.jpeg');
            // Footer logos — local path first; the remote host is blocked by CORS off production.
            const footerLogoBase64 = await getImageBase64('/assets/logo/Left_Logo.png') || await getImageBase64('http://app.klaes.ng/assets/logo/Left_Logo.png');
            const footerLogoLeftBase64 = await getImageBase64('/assets/logo/logo.png') || await getImageBase64('/storage/upload/logo/logo.png') || await getImageBase64('http://app.klaes.ng/storage/upload/logo/logo.png');
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
            drawCenteredWatermark(doc, watermarkText);

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

            // Applicant's passport photograph, beside the QR code.
            drawCommissioningPassport(doc, await fetchCommissioningPassport(fileNumberVal));

            // Body content
            doc.setFontSize(10);
            doc.setFont("helvetica", "normal");
            let y = 85;
            // SIT files carry a reason that should print directly after the Location.
            const isSitFile = String(fileNumberVal || '').toUpperCase().startsWith('SIT-');
            // Only a Re-Issuance carries an old number, and not every file has a related
            // one; each row is dropped when its number is absent. Reprints build their
            // FormData from an API row, so fall back to a lookup.
            let oldFileNoVal = String(formData.get('old_file_number') || '').trim();
            let relatedFileNoVal = String(formData.get('related_file_number_display') || '').trim();
            // Always asked for: the commissioning form carries no District field, so the
            // lookup is the only source for that line.
            const links = await fetchCommissioningFileLinks(fileNumberVal);

            // Append the record's source after the File No, e.g. "CON-COM-2026-429 (Conversion)".
            //
            // The lookup is consulted before the prefix guess. The commissioning form has
            // no source field of its own, so getCommissioningSourceLabel() fell through to
            // the prefix — and every CON- number guesses "Conversion". A Change of Purpose
            // therefore printed "CON-RES-2026-2924 (Conversion)" while the file list,
            // reading mls_file_no.source, correctly called it a Change of Purpose.
            const fileTypeLabel = getCommissioningSourceLabel(
                formData.get('source') || links.source,
                fileNumberVal
            );
            const fileNoDisplay = fileTypeLabel ? `${fileNumberVal} (${fileTypeLabel})` : fileNumberVal;
            oldFileNoVal = oldFileNoVal || links.old_file_number;
            relatedFileNoVal = relatedFileNoVal || links.related_file_number;
            const districtVal = String(formData.get('district') || '').trim() || links.district;
            // The Related/Old FileNo line is part of the sheet's shape, not an optional
            // extra: a file with neither number prints the label over a blank rule, the
            // same as TP No or Plot No do. A file carrying both keeps a line for each.
            const relatedOrOldRows = [
                ...(oldFileNoVal ? [['Related FileNo/Old FileNo:', oldFileNoVal]] : []),
                ...(relatedFileNoVal ? [['Related FileNo/Old FileNo:', relatedFileNoVal]] : []),
            ];
            if (!relatedOrOldRows.length) {
                relatedOrOldRows.push(['Related FileNo/Old FileNo:', '']);
            }

            // The property block reads TP No > Plot No > District > Location.
            const fields = [
                ['File No/(File Type):', fileNoDisplay],
                ...relatedOrOldRows,
                ['File Name:', formData.get('file_name')],
                ['TP No:', formData.get('tp_number')],
                ['Plot No:', formData.get('plot_number')],
                ['District:', districtVal],
                ['Location:', formData.get('location')],
                ...(isSitFile ? [['Reason:', formData.get('sit_reason')]] : []),
                ['Time Commissioned:', formatTimeToAMPM(formData.get('time_created'))],
                ['Date Commissioned:', formData.get('date_created')],
                ['Commissioned by:', formData.get('created_by')]
            ];

            const valueMaxWidth = 185 - 72; // width of the value column (line runs 70 -> 185, text starts at 72)
            const reasonLineHeight = 5;     // vertical spacing between wrapped reason lines (mm)

            fields.forEach(([label, value]) => {
                drawCommissioningLabel(doc, label, 25, y, 72);
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

            // Footer logo, flush right under the body rules (which end at x=185),
            // in line with the header's right-hand logo column. 28.8x11.5mm — down from
            // the original 45x18, which stood too tall against the footer line.
            if (footerLogoBase64) doc.addImage(footerLogoBase64, 'PNG', 156.2, 278.5, 28.8, 11.5);
            drawFooterLeftLogo(doc, footerLogoLeftBase64);

            // Page 2 — the File Tracking Sheet, so it travels with the file.
            showGlobalLoading('Adding tracking sheet...');
            await appendTrackingSheetPage(doc, formData.get('file_number'));

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
            const logo2Base64 = await getImageBase64('/assets/logo/ministry2.png') || await getImageBase64('http://app.klaes.ng/assets/logo/ministry2.png') || await getImageBase64('/assets/logo/logo3.jpeg');
            const footerLogoBase64 = await getImageBase64('/assets/logo/Left_Logo.png') || await getImageBase64('http://app.klaes.ng/assets/logo/Left_Logo.png');
            const footerLogoLeftBase64 = await getImageBase64('/assets/logo/logo.png') || await getImageBase64('/storage/upload/logo/logo.png') || await getImageBase64('http://app.klaes.ng/storage/upload/logo/logo.png');

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
                drawCenteredWatermark(doc, watermarkText);

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

                // Applicant's passport photograph for this record.
                drawCommissioningPassport(doc, await fetchCommissioningPassport(rowFileNo));

                // Body fields for this row/page
                doc.setFontSize(10);
                let y = 85;
                const leftMargin = 25;
                const textStartX = 72;

                const createdAt = row.created_at ? new Date(row.created_at) : new Date();

                const isSitRow = String(rowFileNo || '').toUpperCase().startsWith('SIT-');
                // Old and related numbers, each paired with its KANGIS/land counterpart.
                // The batch rows carry neither, so both are looked up per record.
                const rowLinks = await fetchCommissioningFileLinks(rowFileNo);

                // Append the record's source after the File No, e.g. "CON-COM-2026-429
                // (Conversion)". The row usually carries it; the lookup covers the rows
                // that do not, so the sheet never falls back to guessing "Conversion"
                // from a CON- prefix (see the single-sheet builder above).
                const rowFileTypeLabel = getCommissioningSourceLabel(row.source || rowLinks.source, rowFileNo);
                const rowFileNoDisplay = rowFileTypeLabel ? `${rowFileNo} (${rowFileTypeLabel})` : rowFileNo;
                const rowOldFileNo = rowLinks.old_file_number || String(row.old_fileno || row.old_file_number || '').trim();
                const hasRowOldFileNo = rowOldFileNo !== '' && rowOldFileNo.toUpperCase() !== String(rowFileNo).trim().toUpperCase();
                const rowRelatedFileNo = rowLinks.related_file_number;
                const rowDistrict = String(row.district || '').trim() || rowLinks.district;
                // Kept even when empty, so every sheet in the batch has the same rows
                // in the same places -- see the single-sheet builder above.
                const rowRelatedOrOldRows = [
                    ...(hasRowOldFileNo ? [['Related FileNo/Old FileNo:', rowOldFileNo]] : []),
                    ...(rowRelatedFileNo ? [['Related FileNo/Old FileNo:', rowRelatedFileNo]] : []),
                ];
                if (!rowRelatedOrOldRows.length) {
                    rowRelatedOrOldRows.push(['Related FileNo/Old FileNo:', '']);
                }

                // The property block reads TP No > Plot No > District > Location.
                const fields = [
                    ['File No/(File Type):', rowFileNoDisplay],
                    ...rowRelatedOrOldRows,
                    ['File Name:', row.file_name || ''],
                    ['TP No:', row.tp_no || 'N/A'],
                    ['Plot No:', row.plot_no || 'N/A'],
                    ['District:', rowDistrict || 'N/A'],
                    ['Location:', row.location || 'N/A'],
                    ...(isSitRow ? [['Reason:', row.sit_reason || '']] : []),
                    ['Time Commissioned:', createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })],
                    ['Date Commissioned:', createdAt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })],
                    ['Commissioned by:', row.created_by || '']
                ];

                const valueMaxWidth = 185 - textStartX; // width of the value column
                const reasonLineHeight = 5;             // vertical spacing between wrapped reason lines (mm)

                fields.forEach(([label, value]) => {
                    drawCommissioningLabel(doc, label, leftMargin, y, textStartX);
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
                if (footerLogoBase64) doc.addImage(footerLogoBase64, 'PNG', 156.2, 278.5, 28.8, 11.5);
                drawFooterLeftLogo(doc, footerLogoLeftBase64);
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
                    formData.append('old_file_number', responseData.old_fileno || responseData.old_file_number || '');
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
        const tabs = ['generator', 'consolidation'];
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
    };

    // window.generateConversionApplication lives in js/shared/conversion-application-print.js
    // — the ST File Commissioning table prints the same sheet.

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
                    formData.append('old_file_number', record.old_fileno || record.old_file_number || '');

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
        // Third way out. Batch and Single both end in commissioning a NEW file number; Match OP
        // is for a file already commissioned without an OP, so it skips commissioning entirely
        // and opens the Capture OP dialog in commissioned-file mode. SweetAlert only exposes
        // three action buttons, so it is appended into the actions row by hand (didOpen) to sit
        // with the others. Only offered on pages where that dialog is present.
        const hasCommissionedFlow = typeof window.openOpForCommissionedFile === 'function';

        Swal.fire({
            title: 'Occupancy Permit (OP)',
            html: `<p class="text-sm text-gray-600">Are you capturing a <strong>single</strong> OP, or a <strong>batch</strong> of OPs for ${opType}?</p>`
                + (hasCommissionedFlow
                    ? '<p class="text-xs text-gray-500 mt-2">If the file number was commissioned without an OP,'
                        + ' choose <strong>Match OP</strong> — no new file number is generated.</p>'
                    : ''),
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Batch',
            denyButtonText: 'Single',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            denyButtonColor: '#059669',
            didOpen: () => {
                if (!hasCommissionedFlow) return;

                // Built here rather than passed as `footer` so it can join the real actions row.
                // swal2-styled gives it the same metrics as the built-in buttons; only the
                // colour is ours.
                const actions = typeof Swal.getActions === 'function' ? Swal.getActions() : null;
                if (!actions) return;

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'opAlreadyCommissionedBtn';
                btn.className = 'swal2-styled';
                btn.style.backgroundColor = '#7c3aed';
                // swal2-styled sets no colour of its own, so the button would inherit the
                // dialog's dark body text and read as unclickable against the violet.
                btn.style.color = '#fff';
                btn.textContent = 'Match OP';

                // Cancel stays last — it is the way out, and the affirmative choices belong
                // together ahead of it.
                const cancelBtn = typeof Swal.getCancelButton === 'function' ? Swal.getCancelButton() : null;
                if (cancelBtn && cancelBtn.parentNode === actions) {
                    actions.insertBefore(btn, cancelBtn);
                } else {
                    actions.appendChild(btn);
                }

                btn.addEventListener('click', () => {
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
                    window.openOpForCommissionedFile();
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

    /** Local escape — escapeMlsfHtml() lives in the other <script> block. */
    function crEscape(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * A value as the report prints it: upper case, with a dash when there is nothing.
     * The registry stores names and locations in mixed case; the report reads as a
     * register, so every cell is cased the same way rather than however it was typed.
     */
    function crCell(value) {
        const v = String(value ?? '').trim();
        return v === '' ? '-' : v.toUpperCase();
    }

    /** The Registry / Land Use group a row belongs to. */
    function crGroupOf(row) {
        return String(row?.registry_group || '').trim() || 'Unassigned Registry';
    }

    /**
     * How many rows sit in each group, counted in one pass.
     *
     * Counted up front rather than looked up per row: the report runs to thousands of
     * rows and a filter() inside the render loop would be quadratic.
     */
    function crGroupCounts(rows) {
        return (rows || []).reduce((acc, row) => {
            const g = crGroupOf(row);
            acc[g] = (acc[g] || 0) + 1;
            return acc;
        }, {});
    }

    /**
     * The related / previous numbers recorded against a report row, normalised for both
     * the preview and the PDF. The server sends them old-number-first, so a re-issued
     * file reads as OLD rather than REL — the same precedence as the main listing.
     */
    function crRelatedEntries(row) {
        return (Array.isArray(row?.related_files) ? row.related_files : [])
            .map(e => {
                const type = String(e?.type || '').trim() || 'Related File';
                return {
                    file_no: String(e?.file_no || '').trim().toUpperCase(),
                    type,
                    isOld: type.toLowerCase() === 'old file number',
                };
            })
            .filter(e => e.file_no !== '');
    }

    /**
     * The heading a report Source sits under, or '' when it has none. In Land an
     * OP Resettlement is a Direct Allocation, so the cell reads DIRECT ALLOCATION with
     * OP RESETTLEMENT beneath it. This is on the report only: the stored source is
     * untouched.
     */
    function crSourceSub(source) {
        return String(source || '').trim().toUpperCase() === 'OP RESETTLEMENT' ? 'DIRECT ALLOCATION' : '';
    }

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

            // Render rows, broken by Registry / Land Use. The server returns them already
            // ordered so each group is contiguous, so a heading goes in wherever the group
            // changes from the previous row.
            let html = '';
            const groupCounts = crGroupCounts(_crReportData);
            let currentGroup = null;

            _crReportData.forEach((row, idx) => {
                const group = crGroupOf(row);
                if (group !== currentGroup) {
                    currentGroup = group;
                    const n = groupCounts[group] || 0;
                    html += `
                        <tr class="bg-emerald-50 border-y border-emerald-200">
                            <td colspan="7" class="px-4 py-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-900">${crEscape(group)}</span>
                                    <span class="text-xs font-semibold text-emerald-700">${n} file${n === 1 ? '' : 's'}</span>
                                </div>
                            </td>
                        </tr>
                    `;
                }

                const createdAt = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
                // Related / previous numbers sit directly beneath the file number they
                // belong to, badged the way the main listing badges them: amber OLD for a
                // previous number, sky REL for a related one.
                const relatedHtml = crRelatedEntries(row).map(e => {
                    const badge = e.isOld
                        ? '<span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-700 border border-amber-200 mr-1">OLD</span>'
                        : '<span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-700 border border-sky-200 mr-1">REL</span>';
                    const valueClass = e.isOld ? 'text-amber-700' : 'text-sky-700';
                    return `<div class="mt-1 text-[11px] whitespace-nowrap">${badge}<span class="font-semibold ${valueClass}">${crEscape(e.file_no)}</span></div>`;
                }).join('');

                const sourceText = String(row.source || '').trim();
                const sourceSub = crSourceSub(sourceText);
                const sourceHtml = sourceText
                    ? `<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-800 border border-violet-200">${crEscape(sourceSub || sourceText.toUpperCase())}</span>`
                        + (sourceSub ? `<div class="mt-1 text-[11px] font-semibold text-violet-700 whitespace-nowrap">${crEscape(sourceText.toUpperCase())}</div>` : '')
                    : '<span class="text-gray-300">&mdash;</span>';

                html += `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm text-gray-500 font-medium align-top">${idx + 1}</td>
                        <td class="px-4 py-3 text-sm align-top">
                            <div class="font-semibold text-gray-900">${crEscape(crCell(row.full_file_number))}</div>
                            ${relatedHtml}
                        </td>
                        <td class="px-4 py-3 text-sm align-top">${sourceHtml}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 align-top">${crEscape(crCell(row.file_name))}</td>
                        <td class="px-4 py-3 text-sm align-top">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">${crEscape(crCell(row.land_use_full || row.land_use))}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 align-top">${crEscape(crCell(row.location))}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 align-top">${crEscape(crCell(createdAt))}</td>
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

            const parts = [];
            const fileYear = document.getElementById('crFileYear')?.value;
            const prefix   = document.getElementById('crPrefix')?.value;
            const dateFrom = document.getElementById('crDateFrom')?.value;
            const dateTo   = document.getElementById('crDateTo')?.value;
            if (fileYear) parts.push(`Year: ${fileYear}`);
            if (prefix)   parts.push(`Prefix: ${prefix}`);
            if (dateFrom || dateTo) parts.push(`Date: ${dateFrom || '…'} - ${dateTo || '…'}`);

            /**
             * Ministry header, report title, filter line and file count. Returns the Y
             * below it. The Y is tracked rather than fixed because the filter line is
             * omitted when nothing is filtered, and what follows must close the gap.
             */
            const paintHeader = (title) => {
                if (logo1) doc.addImage(logo1, 'JPEG', 15, 8, 18, 18);
                if (logo2) doc.addImage(logo2, 'JPEG', 265, 8, 18, 18);

                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.text('MINISTRY OF LAND & PHYSICAL PLANNING', 148, 14, { align: 'center' });
                doc.setFontSize(11);
                doc.text('DEPARTMENT OF LAND', 148, 20, { align: 'center' });
                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                doc.text(title, 148, 27, { align: 'center' });

                let y = 32;
                if (parts.length) {
                    doc.setFontSize(8);
                    doc.setTextColor(100);
                    doc.text(`Filters: ${parts.join(' | ')}`, 148, y, { align: 'center' });
                    doc.setTextColor(0);
                    y += 5;
                }

                // Total at the top, not only in the page footer — it is the figure the
                // department reads off the head of the report.
                doc.setFontSize(9);
                doc.setFont('helvetica', 'bold');
                doc.text(`Total No. of Files: ${_crReportData.length}`, 148, y, { align: 'center' });
                doc.setFont('helvetica', 'normal');
                return y + 6;
            };

            // Coat of Arms watermark (centered)
            const coatOfArms = await getImageBase64('/assets/logo/Nigerian-Coat-of-Arms.png') || await getImageBase64('/assets/logo/court_of arms.jpeg');
            const paintWatermark = () => {
                if (!coatOfArms) return;
                const pageW = doc.internal.pageSize.getWidth();
                const pageH = doc.internal.pageSize.getHeight();
                const wmSize = 90;
                doc.setGState(doc.GState({ opacity: 0.06 }));
                doc.addImage(coatOfArms, 'PNG', (pageW - wmSize) / 2, (pageH - wmSize) / 2, wmSize, wmSize);
                doc.setGState(doc.GState({ opacity: 1.0 }));
            };

            // ── Page 1: the totals summary ───────────────────────────────────────
            // Like the Instrument Capture Register, the register proper starts on
            // page 2; page 1 answers how many files in total and how many in each
            // Registry / Land Use group — the same groups the register is broken by.
            const groupCounts = crGroupCounts(_crReportData);
            const summaryWidth = 170;
            const summaryMargin = { left: (297 - summaryWidth) / 2, right: (297 - summaryWidth) / 2 };
            const summaryHeadStyles = { fillColor: [16, 185, 129], textColor: 255, fontStyle: 'bold', halign: 'center' };
            const summaryFootStyles = { fillColor: [226, 232, 240], textColor: [0, 0, 0], fontStyle: 'bold' };
            const summaryColumns = { 0: { cellWidth: 16, halign: 'center' }, 1: { cellWidth: 104 }, 2: { cellWidth: 50, halign: 'right' } };
            const grandTotalFoot = [['', 'GRAND TOTAL', { content: _crReportData.length.toLocaleString(), styles: { halign: 'right' } }]];

            let summaryY = paintHeader('MLPP FILE NUMBER CONSOLIDATION REPORT - SUMMARY OF TOTALS');
            paintWatermark();

            doc.autoTable({
                // One line per group, in the order the register below lists them.
                startY: summaryY,
                head: [['S/N', 'Registry / Land Use', 'Total Files']],
                body: Object.keys(groupCounts).map((name, index) => [index + 1, name.toUpperCase(), groupCounts[name].toLocaleString()]),
                foot: grandTotalFoot,
                showFoot: 'lastPage',
                theme: 'grid',
                styles: { fontSize: 9, cellPadding: 2, valign: 'middle' },
                headStyles: summaryHeadStyles,
                footStyles: summaryFootStyles,
                columnStyles: summaryColumns,
                tableWidth: summaryWidth,
                margin: summaryMargin,
                didDrawPage: function () {
                    // A long group list can spill onto a second summary page.
                    paintWatermark();
                },
            });

            // ── Page 2 onward: the register ──────────────────────────────────────
            doc.addPage();
            const tableStartY = paintHeader('MLPP FILE NUMBER CONSOLIDATION REPORT');

            // Table data
            const tableHead = [['SN', 'File Number', 'Source', 'File Name / Allottee', 'Land Use', 'Location', 'Date Commissioned']];
            let pdfGroup = null;
            const tableBody = [];

            _crReportData.forEach((row, i) => {
                const d = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

                // Heading row per Registry / Land Use, spanning the table. The rows arrive
                // group-contiguous from the server, so a change of group is the boundary.
                const group = crGroupOf(row);
                if (group !== pdfGroup) {
                    pdfGroup = group;
                    const n = groupCounts[group] || 0;
                    tableBody.push([{
                        content: `${group.toUpperCase()}  --  ${n} FILE${n === 1 ? '' : 'S'}`,
                        colSpan: 7,
                        styles: { fillColor: [209, 250, 229], textColor: [6, 78, 59], fontStyle: 'bold', halign: 'left' },
                    }]);
                }

                // Related / previous numbers go on their own lines under the file number,
                // so the cell reads as one fact. autoTable grows the row to fit. Every
                // one is printed rather than the listing's "first + N more": that badge is
                // a click target on screen and a dead end on paper.
                const related = crRelatedEntries(row)
                    .map(e => `${e.isOld ? 'OLD' : 'REL'}: ${e.file_no}`)
                    .join('\n');
                const fileNoCell = related
                    ? `${crCell(row.full_file_number)}\n${related}`
                    : crCell(row.full_file_number);

                const sourceSub = crSourceSub(row.source);

                tableBody.push([
                    i + 1,
                    fileNoCell,
                    sourceSub ? `${sourceSub}\n${crCell(row.source)}` : crCell(row.source),
                    crCell(row.file_name),
                    crCell(row.land_use_full || row.land_use),
                    crCell(row.location),
                    crCell(d),
                ]);
            });

            doc.autoTable({
                startY: tableStartY,
                head: tableHead,
                body: tableBody,
                styles: { fontSize: 8, cellPadding: 2, lineWidth: 0.1, lineColor: [200, 200, 200] },
                headStyles: {
                    fillColor: [16, 185, 129],
                    textColor: 255,
                    fontStyle: 'bold',
                    fontSize: 8,
                    halign: 'center'
                },
                alternateRowStyles: { fillColor: [245, 245, 245] },
                margin: { left: 10, right: 10 },
                didDrawPage: function () {
                    // Coat of Arms watermark on every page
                    paintWatermark();
                }
            });

            // Footer on every page — summary and register alike — written once the page
            // count is final, so "Page x of y" is the document's own numbering.
            const pageCount = doc.internal.getNumberOfPages();
            const generatedOn = new Date().toLocaleString();
            for (let p = 1; p <= pageCount; p++) {
                doc.setPage(p);
                doc.setFontSize(7);
                doc.setTextColor(150);
                doc.text(
                    `Generated on ${generatedOn} | Page ${p} of ${pageCount} | Total Records: ${_crReportData.length}`,
                    148, doc.internal.pageSize.getHeight() - 8,
                    { align: 'center' }
                );
            }
            doc.setTextColor(0);

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
            const headers = ['SN', 'Registry', 'Registry Land Use', 'File Number', 'Related/Old FileNo', 'File Name', 'Land Use', 'Customer Type', 'Location', 'LGA', 'Source', 'Commissioned By', 'Date'];
            const csvRows = [headers.join(',')];

            _crReportData.forEach((row, i) => {
                const d = row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB') : '';
                // One cell, newline-separated, so a merger quoting many files stays on
                // one spreadsheet row.
                const related = crRelatedEntries(row)
                    .map(e => `${e.isOld ? 'OLD' : 'REL'}: ${e.file_no}`)
                    .join('\n');
                const line = [
                    i + 1,
                    `"${(row.registry_name || '').replace(/"/g, '""')}"`,
                    `"${(row.registry_landuse_label || '').replace(/"/g, '""')}"`,
                    `"${(row.full_file_number || '').replace(/"/g, '""')}"`,
                    `"${related.replace(/"/g, '""')}"`,
                    `"${(row.file_name || '').replace(/"/g, '""')}"`,
                    `"${(row.land_use_full || row.land_use || '').replace(/"/g, '""')}"`,
                    `"${(row.customer_type || '').replace(/"/g, '""')}"`,
                    `"${(row.location || '').replace(/"/g, '""')}"`,
                    `"${(row.lga || '').replace(/"/g, '""')}"`,
                    `"${((crSourceSub(row.source) ? crSourceSub(row.source) + '\n' : '') + (row.source || '')).replace(/"/g, '""')}"`,
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

    // --- Duplex Selection Modal Functions ---
    window.closeDuplexFileModal = function () {
        document.getElementById('duplexFileModal').classList.add('hidden');
    };

    window.searchDuplexFiles = function (query) {
        const tableBody = document.getElementById('duplexResultsTable');
        if (!tableBody) return;

        fetch(`{{ route('duplex-parcel-update.approved-list') }}?search=${encodeURIComponent(query || '')}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;

                tableBody.innerHTML = res.data.length
                    ? ''
                    : '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No approved duplexes found.</td></tr>';

                res.data.forEach(item => {
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-gray-50 cursor-pointer transition-colors border-b last:border-0';
                    row.innerHTML = `
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900 font-mono">${item.duplex_id}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <div class="font-semibold uppercase">${item.applicant || 'N/A'}</div>
                            <div class="text-[10px] text-gray-400 font-mono truncate max-w-xs">${item.sources || ''}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">${item.stages || ''}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="px-3 py-1 bg-blue-600 text-white text-xs font-bold rounded hover:bg-blue-700 transition-colors">Select</button>
                        </td>`;
                    row.onclick = () => selectDuplexFile(item);
                    tableBody.appendChild(row);
                });

                if (typeof lucide !== 'undefined') lucide.createIcons();
            })
            .catch(() => {
                tableBody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">Error loading duplexes.</td></tr>';
            });
    };

    /**
     * Re-pick which parcels a Change of Purpose renames.
     *
     * The choice is made in Deeds and may be corrected here: the officer at the counter
     * holds the file and the layout. WHICH parcels only — the count is fixed, because
     * the approved memo, the file count and both serial ranges rest on it.
     */
    window.openDuplexSelection = async function (stageIndex) {
        const d = window._duplexPlan || {};
        const st = (d.stages || [])[stageIndex];

        if (!st) return;

        const picked = new Set(
            (st.files || []).filter(f => !f.carried).map(f => f.incoming).filter(Boolean)
        );
        const need = picked.size;

        // Every parcel this stage receives, in arrival order, so "Plot 7" means the
        // seventh parcel the previous stage produced — the only identity an officer can
        // place. A holding number cannot be recognised from the file.
        const rows = (st.files || []).map(f => {
            const id = f.incoming || f.holding;
            const on = picked.has(id);

            return `<label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-indigo-50 cursor-pointer text-left">
                        <input type="checkbox" class="dx-pick" value="${id}" ${on ? 'checked' : ''}>
                        <span class="text-xs font-semibold text-gray-700 w-20">Plot ${f.plot_index}</span>
                        <span class="font-mono text-[10px] text-gray-400 flex-1">${id}</span>
                        <span class="text-[10px] font-bold ${on ? 'text-emerald-700' : 'text-gray-300'}">
                            ${on ? st.target_land_use : 'unchanged'}</span>
                    </label>`;
        }).join('');

        const res = await Swal.fire({
            title: `Which parcels change purpose?`,
            html: `<p class="text-xs text-gray-500 mb-2">
                       ${st.label} — pick exactly <b>${need}</b> of ${(st.files || []).length}.
                       The rest keep their numbers and carry on.
                   </p>
                   <div class="max-h-72 overflow-y-auto border border-gray-200 rounded-lg p-1">${rows}</div>
                   <p class="text-[11px] text-gray-400 mt-2">
                       Changing how many parcels change purpose is a Deeds decision — the
                       approved memo states the count.
                   </p>`,
            width: 560,
            showCancelButton: true,
            confirmButtonText: 'Save selection',
            confirmButtonColor: '#4f46e5',
            preConfirm: () => {
                const chosen = Array.from(document.querySelectorAll('.dx-pick:checked')).map(el => el.value);

                if (chosen.length !== need) {
                    Swal.showValidationMessage(`Pick exactly ${need} parcel(s) — ${chosen.length} selected.`);
                    return false;
                }

                return chosen;
            },
        });

        if (!res.isConfirmed) return;

        showGlobalLoading('Saving selection…');

        const saved = await fetch(`{{ url('duplex-parcel-update') }}/${window._duplexRecordId}/stages/${st.id}/selection`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ applies_to: res.value }),
        }).then(r => r.json()).catch(e => ({ success: false, message: e.message }));

        hideGlobalLoading();

        if (!saved.success) {
            Swal.fire({ icon: 'error', title: 'Selection not saved', text: saved.message });
            return;
        }

        // Re-read the plan rather than patching it: the rows, the holders and every
        // downstream stage's input all move with the selection, and the server is the
        // one that just decided them.
        window.renderDuplexPlan(window._duplexRecordId);

        Swal.fire({ icon: 'success', title: 'Selection updated', text: saved.message, timer: 2200, showConfirmButton: false });
    };

    /**
     * Re-read the duplex plan without closing the card.
     *
     * Two things move underneath it while the card is open: the serials, when someone
     * else commissions a file in the same series, and the plan itself, when the Change
     * of Purpose selection is changed. Both are read at open and neither refreshes on
     * its own, so the officer had to close and re-pick the duplex to see either.
     *
     * Applicant details already typed survive: the seeds only fill fields that are
     * still empty, and the entry the officer is on is restored afterwards.
     */
    window.refreshDuplexPlan = async function () {
        const id = window._duplexRecordId;

        if (!id) return;

        const btn = document.getElementById('duplexRefreshBtn');
        if (btn) btn.classList.add('opacity-50', 'pointer-events-none');

        const el = document.querySelector('[x-data="fileNumberGenerator()"]');
        const a = el && el._x_dataStack ? el._x_dataStack[0] : null;
        const at = a ? a.currentEntryIndex : 0;

        try {
            // Serials first: the plan's preview is built from the map.
            await getNextSerialNumber();
            await window.renderDuplexPlan(id);

            // renderDuplexPlan opens on stage 1; put the officer back where they were.
            if (a && at > 0 && at < a.locationEntries.length) {
                a.currentEntryIndex = at;
                a.loadApplicantFromEntry();
                a.syncDuplexStage();
            }
        } finally {
            if (btn) btn.classList.remove('opacity-50', 'pointer-events-none');
        }
    };

    /**
     * Refresh the whole commissioning card in place.
     *
     * The serial map is read once when the modal opens and drives the next-serial and
     * range previews for every file type, not just a duplex — so it is reloaded
     * whoever is commissioning. A duplex's plan is refreshed on top of that, because
     * its previews are computed from the map and would otherwise redraw stale numbers.
     */
    window.refreshCommissionCard = async function () {
        const btn = document.getElementById('duplexRefreshBtn');
        if (btn) btn.classList.add('opacity-50', 'pointer-events-none');

        try {
            await getNextSerialNumber();

            if (window._duplexRecordId) {
                // refreshDuplexPlan re-reads the map itself; it is cheap and keeps the
                // two paths identical rather than half-refreshing here.
                await window.refreshDuplexPlan();
                return;
            }

            // No duplex: the serial map has moved, so the card's own preview follows it.
            const el = document.querySelector('[x-data="fileNumberGenerator()"]');
            const a = el && el._x_dataStack ? el._x_dataStack[0] : null;

            if (a && typeof a.refreshSerialNumber === 'function') a.refreshSerialNumber();
        } finally {
            if (btn) btn.classList.remove('opacity-50', 'pointer-events-none');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    };

    /**
     * Jump the commissioning card to a stage. Bound from the stage rail, which is
     * plain HTML outside Alpine's scope.
     */
    window.goToDuplexStage = function (i) {
        const el = document.querySelector('[x-data="fileNumberGenerator()"]');
        const a = el && el._x_dataStack ? el._x_dataStack[0] : null;

        if (a && typeof a.goToDuplexStage === 'function') a.goToDuplexStage(i);
    };

    window.selectDuplexFile = function (item) {
        if (window._duplexCallback) window._duplexCallback(item);
        closeDuplexFileModal();
    };

    /**
     * The whole duplex plan, inline in the commissioning modal.
     *
     * A duplex commissions several files across several stages, so the officer has to
     * see the complete chain — and what will be retired — before confirming, not a
     * source file and a plot count.
     */
    window.renderDuplexPlan = function (id) {
        const box = document.getElementById('duplexPlanReview');
        if (!box) return;

        box.classList.remove('hidden');
        box.innerHTML = '<p class="text-xs text-gray-400">Loading the duplex plan…</p>';

        window._duplexRecordId = id;

        // Returned so a caller can await it; nothing that already called this cares.
        return fetch(`{{ url('duplex-parcel-update') }}/${id}/summary`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(res => {
                if (!res.success) { box.innerHTML = '<p class="text-xs text-red-500">Could not load the plan.</p>'; return; }

                const d = res.data;
                window._duplexPlan = d;

                const alp = document.querySelector('[x-data="fileNumberGenerator()"]');
                if (alp && alp._x_dataStack && (d.sources || []).length) {
                    alp._x_dataStack[0].duplexSources = d.sources.join(', ');
                }

                // ---- The plan, grouped the way it will be commissioned --------------
                //
                // Everything here is DERIVED. The payload states what the engine will do
                // with each stage — target series, application type, parent, how many
                // files — and the card only renders it. None of it is asked of the
                // officer, because changing any of it at the counter could only break
                // the lineage the stages already settled.
                const dxYear = new Date().getFullYear();

                // Serials run per SERIES, not per duplex, and a duplex never starts a
                // series of its own. Reading the DUPLEX's land use, as this did, answers
                // for the first source file and no other: a plan whose first leg is a
                // Change of Purpose issues nothing in that series at all, so every
                // number previewed was from the wrong one. CON-COM-2003-118 changed to
                // residential and subdivided draws 83 numbers from CON-RES and 3 from
                // CON-COM — never one from COM.
                const dxNextBySeries = {};

                const dxGroups = (d.stages || []).map(st => {
                    const files  = (st.files || []).filter(f => !f.carried);
                    const series = st.target_land_use || '';
                    const single = series !== '' && series.indexOf(' / ') === -1;

                    let serialStart = null;
                    let preview     = '--';

                    if (st.consumes_serial === false) {
                        preview = 'renumbers the incoming file — no serial taken';
                    } else if (single && files.length) {
                        if (dxNextBySeries[series] === undefined) {
                            // An exact key only. getNextSerialForLandUse() falls back to
                            // the code with the prefix stripped, and CON-RES is a
                            // different series from RES — landing on the wrong one
                            // silently is worse than showing nothing.
                            const n = serialNumbersMap[series];
                            dxNextBySeries[series] = (typeof n === 'number' && n > 0) ? n : null;
                        }

                        if (dxNextBySeries[series]) {
                            serialStart = dxNextBySeries[series];
                            dxNextBySeries[series] += files.length;

                            const last = serialStart + files.length - 1;
                            preview = files.length > 1
                                ? `${series}-${dxYear}-${serialStart} … ${series}-${dxYear}-${last}`
                                : `${series}-${dxYear}-${serialStart}`;
                        } else {
                            preview = `${series} — series not initialised for ${dxYear}`;
                        }
                    }

                    return {
                        rank:             Number(st.rank),
                        type:             st.type,
                        label:            st.label,
                        land_use:         series,
                        land_use_code:    st.target_code || '',
                        prefix:           st.target_prefix || '',
                        application_type: st.application_type || st.type,
                        file_option:      st.file_option || st.type,
                        parent_files:     st.parent_files || [],
                        new_land_use:     st.new_land_use || '',
                        consumes_serial:  st.consumes_serial !== false,
                        // The slice of the per-file entries this stage owns, straight
                        // from the payload: the same arithmetic the commit walks.
                        entry_start:      Number(st.entry_start || 0),
                        entry_count:      Number(st.entry_count || files.length),
                        carried:          (st.files || []).length - files.length,
                        serial_start:     serialStart,
                        serial_preview:   preview,
                        files:            files,
                    };
                }).filter(g => g.entry_count > 0);

                window._duplexGroups = dxGroups;

                // The real batches: one run per stage. Shown so "10 files" is not a
                // number the officer has to take on trust.
                const bd = document.getElementById('duplexBatchBreakdown');
                if (bd) {
                    bd.innerHTML = dxGroups.map((g, i) => {
                        // An extension re-numbers the file it receives rather than taking
                        // a number out of the series, which is worth saying where every
                        // other stage reads "1 file".
                        const how = g.type === 'extension'
                            ? 'AND EXTENSION on the incoming file'
                            : (g.entry_count > 1 ? `batch of ${g.entry_count}` : `${g.entry_count} file`);

                        return `<button type="button" onclick="window.goToDuplexStage(${i})"
                                    class="text-left inline-flex flex-col gap-0.5 px-2.5 py-1.5 rounded-lg bg-white border border-blue-200 hover:border-blue-400 hover:bg-blue-50 transition-colors">
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-blue-800">
                                        <span class="w-4 h-4 rounded bg-blue-100 text-blue-700 text-[9px] font-black flex items-center justify-center">${g.rank}</span>
                                        ${g.label} — ${how}
                                    </span>
                                    <span class="font-mono text-[10px] text-emerald-700 pl-5">${g.serial_preview}</span>
                                </button>`;
                    }).join('');
                }

                // The quantity the panel shows is what the stages actually add up to.
                const container = document.querySelector('[x-data="fileNumberGenerator()"]');
                if (container && container._x_dataStack) {
                    const total = (d.stages || []).reduce((sum, st) => sum + (st.files || []).filter(f => !f.carried).length, 0);
                    container._x_dataStack[0].duplexFileCount = total;
                    container._x_dataStack[0].batchQuantity = total;
                    container._x_dataStack[0].duplexStages = dxGroups;
                    container._x_dataStack[0].duplexStageIndex = 0;

                    // The Tracking ID box is fed by the grouping lookup, which a duplex does
                    // not use. Say where the ids come from instead of leaving a bare "--".
                    const tid = document.getElementById('trackingIdDisplay');
                    if (tid) {
                        tid.textContent = 'Assigned per stage at commissioning';
                        tid.classList.remove('text-red-600');
                        tid.classList.add('text-blue-700');
                    }
                    setActionButtonsDisabled(false);

                    // Build the per-file entry rows so "Applicant 1 of N" and "Entry 1 of N"
                    // step through the real files, and seed each with what the duplex
                    // already knows: the officer edits from there rather than from blank.
                    const a = container._x_dataStack[0];
                    if (typeof a.updateBatchPreview === 'function') a.updateBatchPreview();

                    // Open on the first stage, with its own series, prefix and serial
                    // showing rather than the duplex's.
                    a.currentEntryIndex = 0;
                    if (typeof a.syncDuplexStage === 'function') a.syncDuplexStage();

                    // Each seed remembers WHICH STAGE it came from: an extension's file
                    // carries the "& EXTENSION" plot marker and the others do not, so the
                    // type has to survive the flatten.
                    const seeds = (d.stages || []).flatMap(st =>
                        (st.files || []).filter(f => !f.carried)
                            .map(f => Object.assign({ _stageType: st.type }, f)));

                    const dx = d.duplex || {};

                    seeds.forEach((f, i) => {
                        const e = a.locationEntries[i];
                        if (!e) return;

                        // Plot number, in order: what the stage recorded for this file, then
                        // the duplex's own. The stage panels capture size, dimensions and
                        // holder but NOT a plot number, so f.plot_no is almost always empty
                        // and the field opened blank on every duplex - the officer retyped a
                        // number the duplex already held.
                        if (!e.plotNo) {
                            const plot = f.plot_no || dx.plot_no || '';

                            // An extension's file reads "<plot> & EXTENSION". The server
                            // appends that itself, so this is only about showing the officer
                            // what will be written; withExtensionPlotSuffix is idempotent, so
                            // the value cannot end up carrying the marker twice.
                            e.plotNo = (plot && f._stageType === 'extension'
                                && typeof withExtensionPlotSuffix === 'function')
                                ? withExtensionPlotSuffix(plot)
                                : plot;
                        }

                        if (!e.file_name && f.holder) e.file_name = f.holder;
                        if (!e.lga && dx.lga) e.lga = dx.lga;
                        if (!e.district && dx.district) e.district = dx.district;
                        if (!e.location && dx.location) e.location = dx.location;
                    });
                }

                // Stage-by-stage accounting. A single "X retired, Y issued" line was wrong
                // as well as unclear: a Change of Purpose retires files the SUBDIVISION
                // just created, so the retirements are not all sources.
                let totalMinted = 0, totalRetired = 0, prevOut = (d.sources || []).length;
                const ledgerRows = (d.stages || []).map(st => {
                    const mints = (st.files || []).filter(f => !f.carried).length;
                    const retires = Number(st.rank) === 1 ? (d.sources || []).length : mints;
                    totalMinted += mints;
                    totalRetired += retires;

                    const what = Number(st.rank) === 1
                        ? `${(d.sources || []).join(', ')} retired`
                        : `${retires} of the previous stage's file(s) retired`;

                    return `<p class="text-[11px] text-amber-800 leading-relaxed">
                                <b>${st.label}:</b> ${what} &rarr; <b>${mints}</b> new file number(s).
                            </p>`;
                });
                const ledger = ledgerRows.join('');
                const activeAtEnd = ((d.stages || []).slice(-1)[0]?.files || []).length;

                // Only an approved duplex can still be corrected; a committed one is the
                // record of what was issued.
                const dxEditable = ['approved', 'in_land'].includes((d.duplex || {}).status);

                const stages = (d.stages || []).map((st, si) => {
                    const all = st.files || [];

                    // A 39- or 82-plot stage renders one line per file and buries the
                    // rest of the plan. Show the head of the list and say how many more
                    // there are — the summary sheet carries the full account.
                    const CAP = 8;
                    const shown = all.slice(0, CAP);

                    const g = dxGroups.find(x => x.rank === Number(st.rank));

                    // Each file takes the next serial in its stage's series, in row order,
                    // so the number it will receive can be named now rather than left as
                    // "to be generated" — which told the officer nothing and, on a carried
                    // file, was flatly wrong: a carried file is never generated at all, it
                    // keeps the number it arrived with. Only files that mint a number
                    // advance the counter.
                    let mint = 0;

                    const rows = shown.map(f => {
                        let becomes;

                        if (f.final) {
                            becomes = f.final;                       // already commissioned
                        } else if (f.carried) {
                            becomes = 'keeps its number';
                        } else if (g && g.serial_start) {
                            becomes = `${g.land_use}-${dxYear}-${g.serial_start + mint}`;
                        } else {
                            becomes = 'to be generated';
                        }

                        if (!f.carried) mint++;

                        // A predicted number is shown in italic until it is actually
                        // issued, so it is never mistaken for one the registry holds.
                        const style = f.carried
                            ? 'text-gray-400'
                            : (f.final ? 'text-emerald-700' : 'text-emerald-700 italic');

                        return `
                        <div class="flex items-center gap-2 text-[11px] py-0.5">
                            <span class="text-gray-400 w-14 shrink-0">Plot ${f.plot_index}</span>
                            <span class="font-mono text-gray-500 min-w-[150px]">${f.holding}</span>
                            <span class="text-gray-300">&rarr;</span>
                            <span class="font-mono font-bold ${style}">${becomes}</span>
                            ${f.carried ? '<span class="text-[9px] font-bold text-gray-400">UNCHANGED</span>' : ''}
                        </div>`;
                    }).join('')
                        + (all.length > CAP
                            ? `<p class="text-[10px] text-gray-400 pt-1">… and ${all.length - CAP} more</p>`
                            : '');

                    // Land may correct WHICH parcels take the new purpose. Never how
                    // many: the approved memo states the count, and both serial ranges
                    // are sized by it.
                    const pick = (st.type === 'change_of_purpose' && dxEditable && all.length > st.entry_count)
                        ? `<button type="button" onclick="window.openDuplexSelection(${si})"
                                class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 underline">
                                Change selection
                           </button>`
                        : '';

                    const changed = st.selection_changed_at
                        ? `<p class="text-[10px] text-amber-700 mt-1">
                               Selection changed at the Land step on ${st.selection_changed_at}.
                           </p>`
                        : '';

                    return `
                        <div class="bg-white border border-gray-200 rounded-lg p-3 mb-2">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-xs font-bold text-gray-800">${st.label}
                                    ${st.new_land_use ? `<span class="font-normal text-gray-500">&rarr; ${st.new_land_use}</span>` : ''}</p>
                                <div class="flex items-center gap-2">
                                    ${pick}
                                    <span class="text-[10px] font-bold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">#${st.rank}</span>
                                </div>
                            </div>
                            {{-- The series and the numbers this stage takes, stated rather
                                 than left for the officer to work out from the land use
                                 shown on the card. --}}
                            <p class="text-[10px] text-gray-500 mb-1.5">
                                <span class="font-mono font-semibold text-gray-700">${st.target_land_use || '—'}</span>
                                &middot; ${st.entry_count} number(s)
                                &middot; <span class="font-mono text-emerald-700">${(dxGroups.find(g => g.rank === Number(st.rank)) || {}).serial_preview || '--'}</span>
                            </p>
                            ${rows}
                            ${changed}
                        </div>`;
                }).join('');

                box.innerHTML = `
                    <div class="border-t border-blue-200 pt-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-500 mb-2">
                            Stages — in execution order</p>
                        ${stages}

                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2">
                            <p class="text-[10px] font-black uppercase tracking-wider text-amber-700 mb-1.5">What confirming does</p>
                            ${ledger}
                            <p class="text-[11px] text-amber-900 mt-2 pt-2 border-t border-amber-200">
                                <b>${totalMinted}</b> file number(s) issued in all,
                                <b>${totalRetired}</b> decommissioned,
                                <b>${activeAtEnd}</b> file(s) active at the end.
                            </p>
                        </div>
                    </div>`;
            });
    };

    /**
     * Commission a duplex from this modal.
     *
     * It does NOT go through generate/generate-batch: a duplex is several
     * commissionings in a declared order, and DuplexCommitService already runs them
     * against the same engine. Two cards follow — the duplex account, then the usual
     * commissioning summary — because the officer needs both.
     */
    window.commissionDuplexFromModal = async function (alpineData) {
        const id = alpineData.duplexRecordId;
        if (!id) {
            Swal.fire({ icon: 'warning', title: 'No duplex selected', text: 'Pick the duplex to commission first.' });
            return;
        }

        const plan = window._duplexPlan || {};
        const confirmed = await Swal.fire({
            icon: 'warning',
            title: 'Commission this duplex?',
            html: `<b>${plan.duplex ? plan.duplex.duplex_id : ''}</b> — `
                + `${(plan.planned || []).length} file number(s) will be generated and `
                + `${plan.totals ? plan.totals.retired || (plan.sources || []).length : '?'} retired. This cannot be undone.`,
            showCancelButton: true,
            confirmButtonText: 'Yes, commission',
            confirmButtonColor: '#059669',
        });
        if (!confirmed.isConfirmed) return;

        showGlobalLoading('Commissioning duplex…');

        const res = await fetch(`{{ url('duplex-parcel-update') }}/${id}/commit`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                commissioned_by: document.getElementById('commissionedBy')?.value || '',
                commission_date: document.getElementById('commissionDate')?.value || '',
                commission_time: document.getElementById('commissionTime')?.value || '',
                customer_type: alpineData.customerType || 'Individual',
                gender: alpineData.gender || 'Male',
                // Per-file applicant and location details, in the order the files are
                // generated. Left blank they fall back to what the duplex captured.
                location_entries: alpineData.locationEntries || [],
            }),
        }).then(r => r.json()).catch(e => ({ success: false, message: e.message }));

        hideGlobalLoading();

        if (!res.success) {
            Swal.fire({ icon: 'error', title: 'Commissioning failed', text: res.message });
            return;
        }

        // 1. The duplex account: every stage, holding -> real file, and the retirements.
        if (typeof window.showDuplexSummary === 'function') {
            await window.showDuplexSummary('{{ url('duplex-parcel-update') }}', id);
        }

        // 2. The commissioning summary the officer expects after any file is created.
        const files = (res.summary || []).flatMap(x => x.files || []);
        await Swal.fire({
            icon: 'success',
            title: 'Duplex commissioned',
            html: '<p class="text-sm text-gray-600 mb-2">File numbers generated:</p>'
                + '<p class="font-mono text-sm font-bold text-emerald-700">' + files.join('<br>') + '</p>',
            confirmButtonColor: '#059669',
            // The page reloads after this, so a stray click must not skip past the list
            // of numbers that were just issued.
            allowOutsideClick: false,
        });

        window.location.reload();
    };

    /**
     * Commission a Change of Purpose that covers several plots.
     *
     * It does NOT go through this form's generate path. One file becoming many is
     * the batch engine's shape - mint N numbers, retire the source once, each plot
     * its own property with the source as its parent - and
     * ChangeOfPurposeBatchCommitService runs exactly that against the same engine
     * every other commissioning uses.
     */
    window.commissionCopPlotsFromModal = async function (alpineData) {
        const id = alpineData.copAppId;
        if (!id) {
            Swal.fire({ icon: 'warning', title: 'No application selected', text: 'Pick the Change of Purpose to commission first.' });
            return;
        }

        const plots = Number(alpineData.copPlotCount) || 0;
        const confirmed = await Swal.fire({
            icon: 'warning',
            title: `Commission ${plots} plots?`,
            html: `<p class="text-sm text-gray-600 mb-2"><b>${alpineData.originalFileNo}</b> will be retired and `
                + `<b>${plots}</b> new file numbers issued \u2014 one per plot.</p>`
                + '<p class="text-xs text-gray-500">Each plot becomes its own property, with this file as its parent. '
                + 'This cannot be undone.</p>',
            showCancelButton: true,
            confirmButtonText: 'Yes, commission all',
            confirmButtonColor: '#059669',
        });
        if (!confirmed.isConfirmed) return;

        showGlobalLoading('Commissioning plots\u2026');

        const res = await fetch(`{{ url('change-of-purpose') }}/${id}/commission-plots`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                commissioned_by: document.getElementById('commissionedBy')?.value || '',
                commission_date: document.getElementById('commissionDate')?.value || '',
                commission_time: document.getElementById('commissionTime')?.value || '',
                customer_type: alpineData.customerType || 'Individual',
                gender: alpineData.gender || 'Male',
                phone_no: document.getElementById('generatePhoneNo')?.value || '',
                purpose_id: alpineData.purpose || null,
            }),
        }).then(r => r.json()).catch(e => ({ success: false, message: e.message }));

        hideGlobalLoading();

        if (!res.success) {
            Swal.fire({ icon: 'error', title: 'Commissioning failed', text: res.message });
            return;
        }

        const files = ((res.result || {}).files) || [];
        await Swal.fire({
            icon: 'success',
            title: `${files.length} file numbers issued`,
            html: '<p class="text-sm text-gray-600 mb-2">'
                + `<b>${(res.result || {}).file_no || ''}</b> retired. New numbers:</p>`
                + '<p class="font-mono text-sm font-bold text-emerald-700">' + files.join('<br>') + '</p>',
            confirmButtonColor: '#059669',
            allowOutsideClick: false,
        });

        window.location.reload();
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
                                        ${Number(item.plot_count) > 1
                                            ? `<div class="text-[10px] font-bold text-blue-600 mt-1">COVERS ${item.plot_count} PLOTS — ${item.plot_count} FILE NUMBERS WILL BE ISSUED</div>`
                                            : ''}
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

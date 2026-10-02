/**
 * File Number Search and Button State Management
 * Handles searching across property_records, registered_instruments, and CofO tables
 * Manages button states based on file number existence
 */

// Global state for file number search
window.fileNumberSearchState = {
    fileNumberExists: false,
    selectedFileData: null,
    searchResults: null,
    normalized: null,
    sources: null
};

const AUTOFILL_CONFIDENCE_THRESHOLD = 0.5;
const AUTOFILL_SUMMARY_ENABLED = false;
const AUTOFILL_FIELD_CONFIG = {
    location: { selector: '#location', event: 'input' },
    petitioner: { selector: '#petitioner', event: 'input' },
    grantor: { selector: '#grantor', event: 'input' },
    grantee: { selector: '#grantee', event: 'input' },
    'serial_no': { selector: '#serial-no', event: 'input' },
    'page_no': { selector: '#page-no', event: 'input', readonly: true },
    'volume_no': { selector: '#volume-no', event: 'input' },
    'registration_number': { selector: '#registration-number', type: 'text-display' },
    'start_date': { selector: '#start-date', event: 'change' },
    'instructions': { selector: '#instructions', event: 'input' },
    'remarks': { selector: '#remarks', event: 'input' },
    'encumbrance_type': { selector: '#encumbrance-type', event: 'change', type: 'select' },
    'instrument_type': { selector: '#instrument-type', event: 'change', type: 'select' }
};

window.caveatAutofillState = {
    appliedFields: new Set(),
    summary: [],
    initialized: false
};

function isInstrumentOverwriteEnabled() {
    const button = document.getElementById('overwrite-instrument-type');
    return !!(button && button.dataset.active === 'true');
}

function setInstrumentOverwriteButtonState(enabled) {
    const button = document.getElementById('overwrite-instrument-type');
    const label = document.getElementById('overwrite-instrument-label');

    if (!button) {
        return;
    }

    button.dataset.active = enabled ? 'true' : 'false';
    button.setAttribute('aria-pressed', enabled ? 'true' : 'false');

    if (enabled) {
        button.classList.remove('border-red-300', 'bg-red-50', 'text-red-700', 'hover:bg-red-100');
        button.classList.add('border-red-700', 'bg-red-600', 'text-white', 'hover:bg-red-700');
        if (label) label.textContent = 'Overwrite Enabled';
        return;
    }

    button.classList.remove('border-red-700', 'bg-red-600', 'text-white', 'hover:bg-red-700');
    button.classList.add('border-red-300', 'bg-red-50', 'text-red-700', 'hover:bg-red-100');
    if (label) label.textContent = 'Overwrite';
    
    // Revalidate button states when overwrite state changes
    if (typeof revalidateButtonStates === 'function') {
        revalidateButtonStates();
    }
}

function resetFormKeepFileNumber() {
    const formRoot = document.getElementById('tab-place');
    if (!formRoot) {
        return;
    }

    const keepIds = new Set([
        'file_number',
        'file_number_display',
        'file-number-input',
        'overwrite-instrument-type',
        'caveat-number',
        'created-by-first-name',
        'date-created'
    ]);

    const fields = formRoot.querySelectorAll('input, select, textarea');
    fields.forEach((field) => {
        const id = field.id || '';
        if (keepIds.has(id) || field.type === 'hidden') {
            return;
        }

        if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
        } else if (field.type === 'checkbox' || field.type === 'radio') {
            field.checked = false;
        } else {
            field.value = '';
        }

        delete field.dataset.userEdited;
        delete field.dataset.autofilled;
        delete field.dataset.autofillSource;
    });

    const registrationDisplay = document.getElementById('registration-number');
    if (registrationDisplay) {
        registrationDisplay.textContent = 'Enter Serial No. and Volume No. to generate';
        registrationDisplay.style.color = '#6b7280';
        registrationDisplay.style.fontWeight = 'normal';
    }

    const summaryContainer = document.getElementById('autofill-summary');
    const summaryList = document.getElementById('autofill-summary-list');
    if (summaryContainer) {
        summaryContainer.classList.add('hidden');
    }
    if (summaryList) {
        summaryList.innerHTML = '';
    }

    caveatAutofillState.appliedFields = new Set();
    caveatAutofillState.summary = [];
}

function setAutoFilledFormLock(locked) {
    const formRoot = document.getElementById('tab-place');
    if (!formRoot) {
        return;
    }

    const excludedIds = new Set([
        'file_number',
        'file_number_display',
        'file-number-input',
        'overwrite-instrument-type',
        'select-fileno-btn',
        'change-file-number',
        'place-caveat',
        'save-draft',
        'reset-form',
        'generate-acknowledgement',
        'petitioner',
        'grantor'
    ]);

    const fields = formRoot.querySelectorAll('input, select, textarea');
    fields.forEach((field) => {
        const id = field.id || '';
        if (excludedIds.has(id) || field.type === 'hidden') {
            return;
        }

        if (locked) {
            const isPageNo = id === 'page-no';
            const isAutofilled = field.dataset.autofilled === 'true';

            // Only lock fields that were auto-populated, keeping manual fields editable.
            if (!isPageNo && !isAutofilled) {
                return;
            }

            if (isPageNo) {
                field.readOnly = true;
            } else {
                field.disabled = true;
            }
            field.dataset.lockedByAutofill = 'true';
            field.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
            return;
        }

        if (field.dataset.lockedByAutofill === 'true') {
            delete field.dataset.lockedByAutofill;
            field.disabled = false;
            field.readOnly = false;
            field.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
        }
    });

    // Keep page number protected even when unlocking form.
    const pageNoInput = document.getElementById('page-no');
    if (pageNoInput) {
        pageNoInput.readOnly = true;
        pageNoInput.disabled = false;
        pageNoInput.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
    }
}

function syncInstrumentOverwriteState(clearOnEnable = true) {
    const instrumentSelect = document.getElementById('instrument-type');
    if (!instrumentSelect) {
        return;
    }

    if (isInstrumentOverwriteEnabled()) {
        if (clearOnEnable) {
            instrumentSelect.value = '';
            instrumentSelect.dispatchEvent(new Event('change'));
        }

        instrumentSelect.disabled = false;
        instrumentSelect.readOnly = false;
        instrumentSelect.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
        delete instrumentSelect.dataset.autofilled;
        delete instrumentSelect.dataset.autofillSource;
        setAutoFilledFormLock(false);
        return;
    }

    if (caveatAutofillState.appliedFields && caveatAutofillState.appliedFields.size > 0) {
        setAutoFilledFormLock(true);
    }
}

function setOverwriteControlVisibility(visible) {
    const container = document.getElementById('overwrite-instrument-container');
    if (!container) {
        return;
    }

    if (visible) {
        container.classList.remove('hidden');
        setInstrumentOverwriteButtonState(isInstrumentOverwriteEnabled());
        return;
    }

    container.classList.add('hidden');
    setInstrumentOverwriteButtonState(false);
    setAutoFilledFormLock(false);
}

function initializeAutofillTracking() {
    if (window.caveatAutofillState.initialized) {
        return;
    }

    Object.values(AUTOFILL_FIELD_CONFIG).forEach(config => {
        const element = document.querySelector(config.selector);
        if (!element) {
            return;
        }

        const markEdited = () => {
            element.dataset.userEdited = 'true';
        };

        if (config.type === 'text-display') {
            return;
        }

        element.addEventListener(config.event || 'input', markEdited);
    });

    window.caveatAutofillState.initialized = true;
}

function renderAutofillSummary(summaryItems, sources) {
    const container = document.getElementById('autofill-summary');
    const list = document.getElementById('autofill-summary-list');

    if (!container || !list) {
        return;
    }

    if (!AUTOFILL_SUMMARY_ENABLED) {
        container.classList.add('hidden');
        list.innerHTML = '';
        return;
    }

    if (!summaryItems || summaryItems.length === 0) {
        container.classList.add('hidden');
        list.innerHTML = '';
        return;
    }

    const rows = summaryItems.map(item => {
        const confidence = item.confidence ? ` ${(item.confidence * 100).toFixed(0)}%` : '';
        const displayValue = formatAutofillSummaryValue(item.field, item.value);
        return `
            <li class="flex items-start gap-2 py-1">
                <span class="text-blue-600 mt-1"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                <div class="text-sm text-gray-700">
                    <span class="font-medium">${item.label}</span>
                    <span class="text-gray-500">${displayValue}</span>
                    <span class="block text-xs text-gray-400">Source: ${item.source}${confidence}</span>
                </div>
            </li>
        `;
    }).join('');

    list.innerHTML = rows;
    container.classList.remove('hidden');
}

function formatAutofillSummaryValue(field, value) {
    if (value === null || value === undefined) {
        return '';
    }

    if (field === 'start_date') {
        return formatDateTimeDisplay(value);
    }

    return value;
}

function formatDateTimeDisplay(rawValue) {
    if (!rawValue) {
        return '';
    }

    if (rawValue instanceof Date && !isNaN(rawValue.getTime())) {
        return formatDateComponents({
            year: rawValue.getFullYear(),
            month: rawValue.getMonth() + 1,
            day: rawValue.getDate(),
            hour: rawValue.getHours(),
            minute: rawValue.getMinutes()
        });
    }

    const stringValue = String(rawValue).trim();
    if (!stringValue) {
        return '';
    }

    const sanitized = stringValue.replace(/(\.\d+)?(Z|[\+\-]\d{2}:?\d{2})?$/i, '');
    const match = sanitized.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2})(?::(\d{2}))?)?/);

    if (match) {
        const [, year, month, day, hourGroup, minuteGroup] = match;
        return formatDateComponents({
            year: Number(year),
            month: Number(month),
            day: Number(day),
            hour: hourGroup !== undefined ? Number(hourGroup) : null,
            minute: minuteGroup !== undefined ? Number(minuteGroup) : null
        });
    }

    return stringValue;
}

function formatDateComponents({ year, month, day, hour, minute }) {
    if (!year || !month || !day) {
        return '';
    }

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthIndex = Math.max(0, Math.min(11, (month || 1) - 1));
    const monthLabel = months[monthIndex] || String(month).padStart(2, '0');
    const dayLabel = Number.isFinite(day) ? String(day).padStart(2, '0') : String(day);

    const hasTime = Number.isFinite(hour) && Number.isFinite(minute);
    const timeLabel = hasTime ? `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}` : '';

    return `${dayLabel} ${monthLabel} ${year}${timeLabel ? `, ${timeLabel}` : ''}`;
}

function getEntryValue(source, fieldName) {
    return source?.fields?.[fieldName]?.value ?? null;
}

function formatSearchRecordCandidate(sourceKey, source) {
    const normalizeText = (value) => {
        if (value === null || value === undefined || value === '') {
            return 'N/A';
        }
        return String(value).trim().toUpperCase();
    };

    const party1 = normalizeText(getEntryValue(source, 'grantor'));
    const party2 = normalizeText(getEntryValue(source, 'grantee'));
    const instrumentType = normalizeText(getEntryValue(source, 'instrument_type'));
    const location = normalizeText(getEntryValue(source, 'location'));
    const serial = normalizeText(getEntryValue(source, 'serial_no'));
    const page = normalizeText(getEntryValue(source, 'page_no'));
    const volume = normalizeText(getEntryValue(source, 'volume_no'));
    const sourceLabel = source?.table || sourceKey;

    return `
        <label class="block rounded-lg border border-indigo-200 bg-white p-3 lookup-card cursor-pointer">
            <div class="flex items-start gap-3">
                <input type="checkbox" class="lookup-choice mt-1" data-source-key="${sourceKey}">
                <div class="flex-1">
                    <p class="text-xs font-semibold text-indigo-700 mb-2">Source: ${sourceLabel}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1 text-xs text-gray-700">
                        <p><span class="font-semibold">Party 1:</span> ${party1}</p>
                        <p><span class="font-semibold">Party 2:</span> ${party2}</p>
                        <p><span class="font-semibold">Instrument Type:</span> ${instrumentType}</p>
                        <p><span class="font-semibold">Property Location:</span> ${location}</p>
                        <p><span class="font-semibold">Serial/Page/Volume:</span> ${serial}/${page}/${volume}</p>
                    </div>
                </div>
            </div>
        </label>
    `;
}

function getSearchRecordCandidates(searchResults) {
    if (!searchResults?.sources) {
        return [];
    }

    return Object.entries(searchResults.sources).filter(([sourceKey]) => {
        return sourceKey !== 'file_indexings' && sourceKey !== 'file_number';
    });
}

function buildSelectedRecordNormalized(sourceKey, searchResults) {
    const selected = searchResults?.sources?.[sourceKey];
    const indexing = searchResults?.sources?.file_indexings;

    if (!selected) {
        return { fields: {}, source_order: [] };
    }

    const keepFields = ['grantor', 'grantee', 'instrument_type', 'location', 'serial_no', 'page_no', 'volume_no', 'registration_number'];
    const mergedFields = {};

    keepFields.forEach((field) => {
        if (selected.fields?.[field]) {
            mergedFields[field] = selected.fields[field];
        }
    });

    if (indexing?.fields?.petitioner) {
        mergedFields.petitioner = indexing.fields.petitioner;
    }

    return {
        fields: mergedFields,
        source_order: [selected.table || sourceKey, indexing?.table].filter(Boolean)
    };
}

function applySelectedRecordToForm(sourceKey, searchResults, autoApplied = false) {
    const selectedRaw = searchResults?.sources?.[sourceKey]?.raw || searchResults?.data || {};
    const normalizedSelection = buildSelectedRecordNormalized(sourceKey, searchResults);

    window.fileNumberSearchState.selectedFileData = selectedRaw;
    window.fileNumberSearchState.fileNumberExists = true;

    resetAutofillFlags();
    const appliedFields = applyNormalizedAutofill(normalizedSelection, searchResults);
    populateFormWithFileData(selectedRaw, { skipFields: appliedFields });
    updateCurrentSelectionPreview(getFileNumberFromForm(), true, searchResults?.sources?.[sourceKey]?.table || sourceKey);
    enablePlaceCaveatButton();
    showFileNumberStatus('found', {
        autoApplied,
        table: searchResults?.sources?.[sourceKey]?.table || sourceKey
    });

    if (!isInstrumentOverwriteEnabled()) {
        setAutoFilledFormLock(true);
    }
    
    // Validate form and update button states now that record data is applied
    if (typeof validateFormAndUpdateButtons === 'function') {
        validateFormAndUpdateButtons();
    }
}

function renderSearchRecordChoices(searchResults) {
    const container = document.getElementById('autofill-summary');
    const list = document.getElementById('autofill-summary-list');

    if (!container || !list) {
        return;
    }

    const candidates = getSearchRecordCandidates(searchResults);

    if (candidates.length <= 1) {
        container.classList.add('hidden');
        const primaryKey = candidates[0]?.[0] || searchResults?.primary_source || (searchResults?.sources ? Object.keys(searchResults.sources)[0] : null);
        if (primaryKey) {
            applySelectedRecordToForm(primaryKey, searchResults, true);
        }
        return;
    }

    disablePlaceCaveatButton();
    list.innerHTML = candidates.map(([sourceKey, source]) => formatSearchRecordCandidate(sourceKey, source)).join('');
    container.classList.remove('hidden');

    list.querySelectorAll('.lookup-choice').forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            if (!this.checked) {
                return;
            }

            list.querySelectorAll('.lookup-choice').forEach((other) => {
                if (other !== this) {
                    other.checked = false;
                }
            });

            const selectedSourceKey = this.getAttribute('data-source-key');
            applySelectedRecordToForm(selectedSourceKey, searchResults, false);
        });
    });
}

function applyNormalizedAutofill(normalized, response) {
    initializeAutofillTracking();

    caveatAutofillState.appliedFields = new Set();
    caveatAutofillState.summary = [];

    if (!normalized || !normalized.fields) {
        renderAutofillSummary([], response?.sources);
        return caveatAutofillState.appliedFields;
    }

    Object.entries(normalized.fields).forEach(([field, entry]) => {
        if (!entry || entry.value === undefined || entry.value === null) {
            return;
        }

        if (field === 'instrument_type' && isInstrumentOverwriteEnabled()) {
            return;
        }

        const config = AUTOFILL_FIELD_CONFIG[field];
        if (!config) {
            return;
        }

        const confidence = entry.confidence ?? 0;
        if (confidence < AUTOFILL_CONFIDENCE_THRESHOLD) {
            return;
        }

        const element = document.querySelector(config.selector);
        if (!element) {
            return;
        }

        if (element.dataset.userEdited === 'true') {
            return;
        }

        const applied = setAutofillValue(element, config, entry.value);
        if (!applied) {
            return;
        }

        element.dataset.autofilled = 'true';
        element.dataset.autofillSource = entry.source || entry.source_key || '';
        caveatAutofillState.appliedFields.add(field);

        caveatAutofillState.summary.push({
            field,
            label: getFieldLabel(field),
            value: config.type === 'text-display' ? entry.value : element.value || entry.value,
            source: entry.source || entry.source_key || 'auto',
            confidence: confidence
        });
    });

    renderAutofillSummary(caveatAutofillState.summary, response?.sources);

    if (caveatAutofillState.appliedFields.has('serial_no') || caveatAutofillState.appliedFields.has('volume_no')) {
        updateRegistrationNumber();
    }

    return caveatAutofillState.appliedFields;
}

function getFieldLabel(field) {
    const labels = {
        location: 'Location',
        petitioner: 'Applicant/Solicitor',
        grantor: 'Grantor',
        grantee: 'Grantee',
        serial_no: 'Serial No.',
        page_no: 'Page No.',
        volume_no: 'Volume No.',
        registration_number: 'Registration Number',
        start_date: 'Date Placed',
        instructions: 'Instructions',
        remarks: 'Remarks',
        encumbrance_type: 'Encumbrance Type',
        instrument_type: 'Instrument Type'
    };

    return labels[field] || field;
}

function setAutofillValue(element, config, value) {
    if (!element) {
        return false;
    }

    if (config.type === 'text-display') {
        if (value !== element.textContent) {
            element.textContent = value;
            return true;
        }
        return false;
    }

    if (config.type === 'select') {
        const select = element;
        const options = Array.from(select.options);
        let matched = options.find(option => option.value === value);

        if (!matched) {
            matched = options.find(option => option.text.trim().toLowerCase() === String(value).trim().toLowerCase());
        }

        if (!matched && value) {
            matched = new Option(`${value} (auto)`, value, true, true);
            matched.dataset.autofill = 'true';
            select.add(matched, select.options[1] || null);
        }

        if (matched) {
            select.value = matched.value;
            select.dispatchEvent(new Event(config.event || 'change'));
            return true;
        }
        return false;
    }

    const normalizedValue = value === undefined || value === null ? '' : String(value);
    const currentValue = element.value;
    if (currentValue === normalizedValue) {
        return false;
    }

    element.value = normalizedValue;
    element.dispatchEvent(new Event(config.event || 'input'));
    return true;
}

function resetAutofillFlags() {
    Object.values(AUTOFILL_FIELD_CONFIG).forEach(config => {
        const element = document.querySelector(config.selector);
        if (!element) {
            return;
        }

        if (config.type !== 'text-display') {
            delete element.dataset.userEdited;
        }

        delete element.dataset.autofilled;
        delete element.dataset.autofillSource;
    });

    caveatAutofillState.appliedFields = new Set();
    caveatAutofillState.summary = [];
    setAutoFilledFormLock(false);
}
/**
 * Search for file number across all 3 tables
 * @param {string} fileNumber - The file number to search for
 * @returns {Promise} - Promise that resolves with search results
 */
async function searchFileNumberInTables(fileNumber) {
    if (!fileNumber || fileNumber.trim() === '') {
        console.log('No file number provided for search');
        return null;
    }

    console.log('Searching for file number:', fileNumber);

    resetAutofillFlags();
    renderAutofillSummary([], null);
    setOverwriteControlVisibility(false);

    try {
        const response = await fetch('/caveat/api/search-file-number', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                file_number: fileNumber.trim()
            })
        });

        let data;
        if (!response.ok) {
            // Try to parse JSON error message from server
            try {
                const errBody = await response.json();
                const msg = errBody.error || errBody.message || `HTTP error! status: ${response.status}`;
                throw new Error(msg);
            } catch (parseErr) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
        } else {
            data = await response.json();
        }
        console.log('File number search results:', data);

        if (data && data.success) {
            window.fileNumberSearchState.searchResults = data;
            const hasSources = !!(data.sources && Object.keys(data.sources).length > 0);
            const found = !!data.found;

            window.fileNumberSearchState.fileNumberExists = found;
            window.fileNumberSearchState.selectedFileData = data.data;
            window.fileNumberSearchState.normalized = data.normalized;
            window.fileNumberSearchState.sources = data.sources;
            
            // Update Current Selection Preview for manual entry
            updateCurrentSelectionPreview(fileNumber, found, data.table);
            
            // Implement exact button logic as requested
            if (found) {
                // 2. When a file number is searched and found in any of the 3 tables
                // Save Record G�� Stay disabled & greyed out (not needed)
                // Place Caveat G�� Enabled, so the user can place a caveat immediately
                window.fileNumberSearchState.fileNumberExists = true;
                disableSaveRecordButton();
                disablePlaceCaveatButton();
                
                // Show success message (not using SweetAlert as requested)
                showToast('Record found. You can now place a caveat.', 'success');

                renderSearchRecordChoices(data);

                const candidates = getSearchRecordCandidates(data);
                showFileNumberStatus('found', {
                    autoApplied: candidates.length <= 1,
                    table: data.table || data.primary_source || (hasSources ? Object.keys(data.sources)[0] : 'Unknown')
                });
                setOverwriteControlVisibility(true);
            
            // Revalidate button states based on found sources and selected instrument
            revalidateButtonStates();
        } else {
                // 3. When a file number is searched and NOT found in any of the 3 tables
                // Save Record G Enabled (so user can create a new record in property_records)
                // Place Caveat G Disabled & greyed out until the record exists
                window.fileNumberSearchState.fileNumberExists = false;
                enableSaveRecordButton();
                disablePlaceCaveatButton();
                
                // Show toast notification as requested (not SweetAlert)
                showToast('Record Not Found. Please create the record first before placing a caveat.', 'info');

                renderAutofillSummary([], null);
                caveatAutofillState.appliedFields = new Set();
                caveatAutofillState.summary = [];
                showFileNumberStatus('not_found');
                setOverwriteControlVisibility(false);
            }
            
            return data;
        } else {
            console.error('File number search failed:', data.error);
            return null;
        }
    } catch (error) {
        console.error('Error searching file number:', error);
        
        // Show error message to user
        showToast('Failed to search for file number. Please try again.', 'error');
        
        return null;
    }
}

/**
 * Enable Place Caveat button
 */
function enablePlaceCaveatButton() {
    const placeCaveatBtn = document.getElementById('place-caveat');
    if (placeCaveatBtn) {
        placeCaveatBtn.disabled = false;
        placeCaveatBtn.className = 'px-4 py-2 bg-blue-600 text-white rounded-md flex items-center hover:bg-blue-700 transition-colors';
    }
}

/**
 * Disable Place Caveat button
 */
function disablePlaceCaveatButton() {
    const placeCaveatBtn = document.getElementById('place-caveat');
    if (placeCaveatBtn) {
        placeCaveatBtn.disabled = true;
        placeCaveatBtn.className = 'px-4 py-2 bg-gray-300 text-gray-600 rounded-md flex items-center cursor-not-allowed';
    }
}

/**
 * Enable Save Record button
 */
function enableSaveRecordButton() {
    const saveRecordBtn = document.getElementById('save-draft');
    if (saveRecordBtn) {
        saveRecordBtn.disabled = false;
        saveRecordBtn.className = 'px-4 py-2 border border-green-500 text-green-600 rounded-md flex items-center hover:bg-green-50 transition-colors';
    }
}

/**
 * Disable Save Record button
 */
function disableSaveRecordButton() {
    const saveRecordBtn = document.getElementById('save-draft');
    if (saveRecordBtn) {
        saveRecordBtn.disabled = true;
        saveRecordBtn.className = 'px-4 py-2 border rounded-md flex items-center bg-gray-300 text-gray-600 cursor-not-allowed';
    }
}

function updateButtonStates(found) {
    if (found) {
        disableSaveRecordButton();
        enablePlaceCaveatButton();
        return;
    }

    enableSaveRecordButton();
    disablePlaceCaveatButton();
}

/**
 * Show file number search status to user
 * @param {string} status - 'found' or 'not_found'
 */
function showFileNumberStatus(status, options = {}) {
    const statusContainer = document.getElementById('file-number-status');
    
    if (status === 'found') {
        const record = window.fileNumberSearchState.selectedFileData;
        const table = options.table || window.fileNumberSearchState.searchResults?.table || 'Unknown';
        const autoApplied = !!options.autoApplied;
        
        if (statusContainer) {
            statusContainer.innerHTML = `
                <div class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fa-solid fa-check-circle text-green-600 mr-2"></i>
                        <div>
                            <p class="text-sm font-medium text-green-800">File Number Found</p>
                            <p class="text-xs text-green-600">${autoApplied ? `Found in ${table} table. Form populated with existing data.` : `Found in ${table} table. Select one search record below to populate fields.`}</p>
                        </div>
                    </div>
                </div>
            `;
            statusContainer.classList.remove('hidden');
        }
        
    } else if (status === 'not_found') {
        if (statusContainer) {
            statusContainer.innerHTML = `
                <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fa-solid fa-info-circle text-yellow-600 mr-2"></i>
                        <div>
                            <p class="text-sm font-medium text-yellow-800">File Number Not Found</p>
                            <p class="text-xs text-yellow-600">Click "Save record to database" to create a new record, then you can place a caveat.</p>
                        </div>
                    </div>
                </div>
            `;
            statusContainer.classList.remove('hidden');
        }
    }
}

/**
 * Populate form with existing file data
 * @param {Object} record - The record data from database
 */
function populateFormWithFileData(record, options = {}) {
    if (!record) return;

    const skipFields = options.skipFields || new Set();
    const safeGet = (obj, keys, fallback = '') => {
        if (!obj) return fallback;
        for (const key of keys) {
            if (obj[key] !== undefined && obj[key] !== null && obj[key] !== '') {
                return obj[key];
            }
        }
        return fallback;
    };

    const fillInput = (fieldKey, selector, possibleKeys) => {
        if (skipFields.has(fieldKey)) return;

        const element = document.querySelector(selector);
        if (!element || element.dataset.userEdited === 'true') return;

        const value = safeGet(record, possibleKeys);
        if (value === '' || value === null || value === undefined) return;

        if (element.value === String(value)) return;

        element.value = value;
        element.dispatchEvent(new Event('input'));
    };

    console.log('Populating form with file data (fallback):', record, { skipFields: Array.from(skipFields) });

    fillInput('location', '#location', ['property_description', 'location', 'PropertyDescription']);
    fillInput('grantor', '#grantor', ['Grantor', 'grantor']);
    fillInput('grantee', '#grantee', ['Grantee', 'grantee']);
    fillInput('petitioner', '#petitioner', ['Assignor', 'Mortgagor', 'Grantor', 'petitioner']);

    if (!skipFields.has('serial_no')) {
        const serial = safeGet(record, ['serialNo', 'serial_no', 'SerialNo']);
        const serialInput = document.getElementById('serial-no');
        if (serialInput && serial && serialInput.dataset.userEdited !== 'true') {
            serialInput.value = serial;
            serialInput.dispatchEvent(new Event('input'));
        }
    }

    if (!skipFields.has('page_no')) {
        const page = safeGet(record, ['pageNo', 'page_no', 'PageNo']);
        const pageInput = document.getElementById('page-no');
        if (pageInput && page && pageInput.dataset.userEdited !== 'true') {
            pageInput.value = page;
        }
    }

    if (!skipFields.has('volume_no')) {
        const volume = safeGet(record, ['volumeNo', 'volume_no', 'VolumeNo']);
        const volumeInput = document.getElementById('volume-no');
        if (volumeInput && volume && volumeInput.dataset.userEdited !== 'true') {
            volumeInput.value = volume;
            volumeInput.dispatchEvent(new Event('input'));
        }
    }

    if (!skipFields.has('instrument_type')) {
        const instrumentType = safeGet(record, ['instrument_type', 'transaction_type', 'InstrumentType']);
        if (instrumentType) {
            const selectElement = document.getElementById('instrument-type');
            if (selectElement && !isInstrumentOverwriteEnabled()) {
                setAutofillValue(selectElement, { type: 'select', event: 'change' }, instrumentType);
            }
        }
    }

    if (!skipFields.has('encumbrance_type')) {
        const encumbranceType = safeGet(record, ['encumbrance_type']);
        if (encumbranceType) {
            const selectElement = document.getElementById('encumbrance-type');
            if (selectElement) {
                setAutofillValue(selectElement, { type: 'select', event: 'change' }, encumbranceType);
            }
        }
    }

    updateRegistrationNumber();
}

/**
 * Save new record to property_records table
 * @returns {Promise} - Promise that resolves when record is saved
 */
async function saveNewRecord() {
    if (window.__savingNewCaveatRecord) {
        return;
    }

    window.__savingNewCaveatRecord = true;
    const saveButton = document.getElementById('save-draft');
    const originalSaveBtnHtml = saveButton ? saveButton.innerHTML : null;

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.className = 'px-4 py-2 border rounded-md flex items-center bg-gray-300 text-gray-600 cursor-not-allowed';
        saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Saving...';
    }

    const fileNumber = getFileNumberFromForm();
    
    if (!fileNumber) {
        showToast('Please enter a file number before saving.', 'error');
        if (saveButton && originalSaveBtnHtml !== null) {
            saveButton.disabled = false;
            saveButton.innerHTML = originalSaveBtnHtml;
        }
        window.__savingNewCaveatRecord = false;
        return;
    }
    
    // Collect form data
    const formData = {
        file_number: fileNumber,
        location: document.getElementById('location')?.value || '',
        grantor: document.getElementById('grantor')?.value || '',
        grantee: document.getElementById('grantee')?.value || '',
        instrument_type: document.getElementById('instrument-type')?.options[document.getElementById('instrument-type')?.selectedIndex]?.text || '',
        transaction_date: document.getElementById('transaction-date')?.value || null,
        property_description: document.getElementById('location')?.value || ''
    };
    
    console.log('Saving new record:', formData);
    
    try {
        const response = await fetch('/caveat/api/create-property-record', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(formData)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Save record response:', data);
        
        if (data.success) {
            // Record saved successfully
            window.fileNumberSearchState.fileNumberExists = true;
            window.fileNumberSearchState.normalized = data.normalized;
            window.fileNumberSearchState.sources = data.sources;
            window.fileNumberSearchState.selectedFileData = data.data;
            
            // Show concise confirmation message
            const message = data.action === 'updated'
                ? 'Record already existed. Updated and ready for caveat placement.'
                : 'Record created successfully! You can now place a caveat.';
            showToast(message, 'success');
            
            // 4. After the user clicks "Save Record" (new record created in property_records)
            // Save Record G�� Disabled & greyed out again (since it has already been saved)
            // Place Caveat G�� Enabled, so the user can now place a caveat on the new record
            disableSaveRecordButton();
            enablePlaceCaveatButton();
            
        } else {
            throw new Error(data.error || 'Failed to save record');
        }
        
    } catch (error) {
        console.error('Error saving record:', error);
        
        showToast(error.message || 'Failed to save record. Please try again.', 'error');
    } finally {
        if (saveButton && originalSaveBtnHtml !== null) {
            if (window.fileNumberSearchState?.fileNumberExists) {
                disableSaveRecordButton();
            } else {
                saveButton.disabled = false;
                saveButton.innerHTML = originalSaveBtnHtml;
            }
        }
        window.__savingNewCaveatRecord = false;
    }
}

// Expose function globally so buttons can call it
window.saveNewRecord = saveNewRecord;

/**
 * Get file number from form inputs
 * @returns {string} - The file number
 */
function getFileNumberFromForm() {
    // Check hidden input first
    let fileNumber = document.getElementById('file_number')?.value;

    if (!fileNumber) {
        fileNumber = document.getElementById('file_number_display')?.value;
    }
    
    if (!fileNumber) {
        // Check manual input
        fileNumber = document.getElementById('file-number-input')?.value;
    }
    
    if (!fileNumber) {
        // Check selector display
        const fileNumberValue = document.getElementById('file-number-value');
        if (fileNumberValue && !fileNumberValue.textContent.includes('Search and select')) {
            fileNumber = fileNumberValue.textContent.trim();
        }
    }
    
    return fileNumber?.trim() || '';
}

/**
 * Build registration number string from serial, page, and volume numbers
 * @returns {string} - The registration number in format serial/page/volume
 * Note: renamed to avoid colliding with the global DOM-updating
 * `generateRegistrationNumber` defined in `caveat-events.js` which
 * is responsible for updating the UI element. This helper only
 * returns the computed string.
 */
function generateRegistrationString() {
    const serialNo = document.getElementById('serial-no')?.value?.trim() || '';
    const pageNo = document.getElementById('page-no')?.value?.trim() || '';
    const volumeNo = document.getElementById('volume-no')?.value?.trim() || '';

    if (serialNo && volumeNo) {
        const finalPageNo = pageNo || serialNo;
        return `${serialNo}/${finalPageNo}/${volumeNo}`;
    }
    return '';
}

function updateRegistrationNumber() {
    // Update page number to match serial number
    const serialNoInput = document.getElementById('serial-no');
    const pageNoInput = document.getElementById('page-no');
    const serialNo = serialNoInput?.value?.trim() || '';
    
    if (serialNo && pageNoInput) {
        if (window.updatePageNumber) {
            window.updatePageNumber(serialNo);
        } else {
            pageNoInput.value = serialNo;
        }
    }
    
    const registrationDisplay = document.getElementById('registration-number');
    if (registrationDisplay) {
        const regNo = generateRegistrationString();
        if (regNo) {
            registrationDisplay.textContent = regNo;
            registrationDisplay.style.color = '#1f2937';
            registrationDisplay.style.fontWeight = 'bold';
        } else {
            registrationDisplay.textContent = 'Enter Serial No. and Volume No. to generate';
            registrationDisplay.style.color = '#6b7280';
            registrationDisplay.style.fontWeight = 'normal';
        }
    }
}

/**
 * Initialize file number search functionality
 */
function initializeFileNumberSearch() {
    console.log('Initializing file number search functionality');
    
    // Add status container after file number input
    const fileNumberContainer = document.querySelector('#file-number-selector').parentElement;
    if (fileNumberContainer && !document.getElementById('file-number-status')) {
        const statusDiv = document.createElement('div');
        statusDiv.id = 'file-number-status';
        statusDiv.className = 'hidden';
        fileNumberContainer.appendChild(statusDiv);
    }
    
    // Set initial button states as per requirements:
    // 1. Default State (before file search)
    // Save Record G�� Disabled & greyed out
    // Place Caveat G�� Disabled & greyed out
    const saveRecordBtn = document.getElementById('save-draft');
    const placeCaveatBtn = document.getElementById('place-caveat');
    
    if (saveRecordBtn) {
        saveRecordBtn.disabled = true;
        saveRecordBtn.className = 'px-4 py-2 border rounded-md flex items-center bg-gray-300 text-gray-600 cursor-not-allowed';
    }
    
    if (placeCaveatBtn) {
        placeCaveatBtn.disabled = true;
        placeCaveatBtn.className = 'px-4 py-2 bg-gray-300 text-gray-600 rounded-md flex items-center cursor-not-allowed';
    }
    
    // Add event listeners for file number changes
    const fileNumberInput = document.getElementById('file-number-input');
    if (fileNumberInput) {
        fileNumberInput.addEventListener('blur', handleFileNumberChange);
        fileNumberInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleFileNumberChange();
            }
        });
    }

    const hiddenFileNumberInput = document.getElementById('file_number');
    if (hiddenFileNumberInput) {
        hiddenFileNumberInput.addEventListener('change', handleFileNumberChange);
        hiddenFileNumberInput.addEventListener('input', handleFileNumberChange);
    }

    const displayFileNumberInput = document.getElementById('file_number_display');
    if (displayFileNumberInput) {
        displayFileNumberInput.addEventListener('change', handleFileNumberChange);
        displayFileNumberInput.addEventListener('input', handleFileNumberChange);
    }
    
    // Add event listener for save record button
    if (saveRecordBtn) {
        saveRecordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!saveRecordBtn.disabled) {
                saveNewRecord();
            }
        });
    }
    
    renderAutofillSummary([], null);

    console.log('File number search functionality initialized');
}

/**
 * Handle file number change event
 */
async function handleFileNumberChange() {
    const fileNumber = getFileNumberFromForm();
    
    if (fileNumber) {
        console.log('File number changed:', fileNumber);
        
        // Update hidden input
        const hiddenInput = document.getElementById('file_number');
        if (hiddenInput) {
            hiddenInput.value = fileNumber;
        }
        
        // Search for file number
        await searchFileNumberInTables(fileNumber);
    } else {
        // Reset button states if no file number - back to default state
        window.fileNumberSearchState.fileNumberExists = false;
        window.fileNumberSearchState.selectedFileData = null;
        
        const saveRecordBtn = document.getElementById('save-draft');
        const placeCaveatBtn = document.getElementById('place-caveat');
        
        // 1. Default State (before file search)
        // Save Record G�� Disabled & greyed out
        // Place Caveat G�� Disabled & greyed out
        if (saveRecordBtn) {
            saveRecordBtn.disabled = true;
            saveRecordBtn.className = 'px-4 py-2 border rounded-md flex items-center bg-gray-300 text-gray-600 cursor-not-allowed';
        }
        
        if (placeCaveatBtn) {
            placeCaveatBtn.disabled = true;
            placeCaveatBtn.className = 'px-4 py-2 bg-gray-300 text-gray-600 rounded-md flex items-center cursor-not-allowed';
        }
        
        // Hide status
        const statusContainer = document.getElementById('file-number-status');
        if (statusContainer) {
            statusContainer.classList.add('hidden');
        }

        renderAutofillSummary([], null);
        resetAutofillFlags();
        setOverwriteControlVisibility(false);
    }
}

/**
 * Update Current Selection Preview
 * @param {string} fileNumber - The file number
 * @param {boolean} found - Whether the record was found
 * @param {string} table - The table where the record was found
 */
function updateCurrentSelectionPreview(fileNumber, found, table) {
    const previewContainer = document.getElementById('current-selection-preview');
    const previewFileNumber = document.getElementById('preview-file-number');
    
    if (previewContainer && previewFileNumber) {
        // For manual-created file numbers, display without leading zeros in the serial
        try {
            previewFileNumber.textContent = removeLeadingZerosFromFileNumber(fileNumber);
        } catch (e) {
            previewFileNumber.textContent = fileNumber;
        }
        
        previewContainer.classList.remove('hidden');
        console.log('Updated current selection preview:', fileNumber);
    }
}

/**
 * Remove leading zeros from the serial number part of a file number
 * @param {string} fileNumber - The file number to clean
 * @returns {string} - The cleaned file number without leading zeros
 */
function removeLeadingZerosFromFileNumber(fileNumber) {
    if (!fileNumber) return fileNumber;
    
    // Handle different file number patterns
    // Pattern: CON-RES-2025-0003 -> CON-RES-2025-3
    // Pattern: RES-2025-0456 -> RES-2025-456
    // Pattern: COM-2016-0208 -> COM-2016-208
    
    // Split by dashes and process the last part (serial number)
    const parts = fileNumber.split('-');
    if (parts.length >= 2) {
        // Get the last part (serial number)
        const lastPart = parts[parts.length - 1];
        
        // Remove leading zeros but keep at least one digit
        const cleanedLastPart = lastPart.replace(/^0+/, '') || '0';
        
        // Reconstruct the file number
        parts[parts.length - 1] = cleanedLastPart;
        return parts.join('-');
    }
    
    return fileNumber;
}

// Export functions for global access
window.searchFileNumberInTables = searchFileNumberInTables;
window.updateButtonStates = updateButtonStates;
window.saveNewRecord = saveNewRecord;
window.getFileNumberFromForm = getFileNumberFromForm;
window.initializeFileNumberSearch = initializeFileNumberSearch;
window.handleFileNumberChange = handleFileNumberChange;
window.enablePlaceCaveatButton = enablePlaceCaveatButton;
window.disablePlaceCaveatButton = disablePlaceCaveatButton;
window.enableSaveRecordButton = enableSaveRecordButton;
window.disableSaveRecordButton = disableSaveRecordButton;
window.isInstrumentOverwriteEnabled = isInstrumentOverwriteEnabled;
window.setOverwriteControlVisibility = setOverwriteControlVisibility;
window.revalidateButtonStates = revalidateButtonStates;

/**
 * Revalidate Save/Place button states based on selected instrument type and existing sources
 */
function revalidateButtonStates(isValid = true) {
    const isOverwrite = isInstrumentOverwriteEnabled();
    const fileNumber = getFileNumberFromForm();
    const instrumentSelect = document.getElementById('instrument-type');
    
    if (!fileNumber) return;

    const selectedInstrumentId = instrumentSelect ? instrumentSelect.value : '';
    const selectedInstrumentName = instrumentSelect && instrumentSelect.selectedIndex >= 0 ? 
        instrumentSelect.options[instrumentSelect.selectedIndex].text : '';

    const searchResults = window.fileNumberSearchState.searchResults;
    
    // If no search results or no instrument selected, use default "found" logic
    if (!searchResults || !searchResults.success || !selectedInstrumentId) {
        if (window.fileNumberSearchState.fileNumberExists && isValid) {
            disableSaveRecordButton();
            enablePlaceCaveatButton();
        } else {
            // Enable Save Record only if file number doesn't exist and form is valid
            if (!window.fileNumberSearchState.fileNumberExists && isValid) {
                enableSaveRecordButton();
            } else {
                disableSaveRecordButton();
            }
            disablePlaceCaveatButton();
        }
        return;
    }

    // Determine if the selected instrument is a Certificate of Occupancy
    const isCofO = isCertificateOfOccupancyInstrument(selectedInstrumentName);
    
    // Check if we have a match in any existing source
    let foundMatch = false;
    
    if (searchResults.sources) {
        Object.keys(searchResults.sources).forEach(key => {
            const source = searchResults.sources[key];
            const sourceTable = (source.table || key).toLowerCase();
            
            // Check if this source matches the target category (CofO or PRA/Deeds)
            const isMatchCategory = isCofO ? 
                (sourceTable.includes('cofo')) : 
                (sourceTable.includes('pra') || sourceTable.includes('registered') || sourceTable.includes('deed'));

            if (isMatchCategory) {
                // Now check if the instrument type matches
                const sourceInstrument = String(
                    source.fields?.instrument_type?.value || 
                    source.raw?.instrument_type || 
                    source.raw?.transaction_type || 
                    ''
                ).toLowerCase();
                
                const selectedLower = selectedInstrumentName.toLowerCase();
                
                // Flexible match: "Assignment" matches "Deed of Assignment"
                if (sourceInstrument.includes(selectedLower) || selectedLower.includes(sourceInstrument)) {
                    foundMatch = true;
                }
            }
        });
    }

    // Implementation of user requirement:
    // "if the selected Instrument Type is not found pra or cofo table, against the file number, 
    // the Save Record to Database should be enabled, while Place Caveat disabled"
    if (foundMatch && isValid) {
        disableSaveRecordButton();
        enablePlaceCaveatButton();
    } else {
        if (!foundMatch && isValid) {
            enableSaveRecordButton();
        } else {
            disableSaveRecordButton();
        }
        disablePlaceCaveatButton();
        
        // Show informative toast if we are in overwrite mode and instrument not found
        if (isOverwrite && !foundMatch && fileNumber) {
            showToast('Instrument type not found for this file. Please "Save Record" first.', 'info');
        }
    }
}

/**
 * Client-side helper to determine if instrument is CofO
 */
function isCertificateOfOccupancyInstrument(instrumentType) {
    if (!instrumentType) return false;
    const normalized = instrumentType.toLowerCase();
    return normalized.includes('certificate of occupancy') || 
           (normalized.includes('certificate') && (normalized.includes('occupancy') || normalized.includes('c of o') || normalized.includes('c/o')));
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeFileNumberSearch();
    initializeAutofillTracking();

    const overwriteButton = document.getElementById('overwrite-instrument-type');
    if (overwriteButton) {
        overwriteButton.dataset.active = 'false';
        overwriteButton.addEventListener('click', function() {
            setInstrumentOverwriteButtonState(true);
            resetFormKeepFileNumber();
            syncInstrumentOverwriteState(true);
        });
    }

    setOverwriteControlVisibility(false);
});

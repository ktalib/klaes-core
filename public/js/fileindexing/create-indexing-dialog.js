(function () {
    // Years a file may NOT be indexed in. Published by
    // resources/views/fileindexing/partials/year_policy.blade.php from
    // config('fileindexing.non_indexable_years'), so the list lives in one place.
    // Passed only to the pickers that set the file's OWN number — a related or
    // counterpart file may still point at one of these years.
    function nonIndexableYears() {
        // Super Admin override box (fileindexing.partials.year_override) lifts it.
        if (yearOverrideChecked()) {
            return [];
        }
        const years = window.FILEINDEXING_NON_INDEXABLE_YEARS;
        if (!Array.isArray(years)) {
            console.warn('[FileIndexing] year policy not published on this page; no year restriction applied');
            return [];
        }
        return years.map(Number).filter(Number.isInteger);
    }

    function yearOverrideChecked() {
        const box = document.getElementById('fileindexing-year-override');
        return !!(box && box.checked);
    }

    const referenceDataStore = {
        lgas: [],
        districts: [],
        isLoading: false,
        isLoaded: false,
        loadPromise: null,
    };

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            const error = new Error(`Request to ${url} failed with status ${response.status}`);
            error.status = response.status;
            throw error;
        }

        return response.json();
    }

    async function loadReferenceData(force = false) {
        if (referenceDataStore.isLoaded && !force) {
            return {
                lgas: referenceDataStore.lgas,
                districts: referenceDataStore.districts,
            };
        }

        if (referenceDataStore.loadPromise) {
            return referenceDataStore.loadPromise;
        }

        const districtSelect = document.getElementById('district-select');
        const lgaSelect = document.getElementById('lga-city');

        referenceDataStore.isLoading = true;

        const loadTask = (async () => {
            try {
                const [lgasResponse, districtsResponse] = await Promise.all([
                    fetchJson('/api/reference/lgas'),
                    fetchJson('/api/reference/districts'),
                ]);

                referenceDataStore.lgas = Array.isArray(lgasResponse?.data) ? lgasResponse.data : [];
                referenceDataStore.districts = Array.isArray(districtsResponse?.data) ? districtsResponse.data : [];
                referenceDataStore.isLoaded = true;

                populateLgaSelect(referenceDataStore.lgas);
                populateDistrictSelect(referenceDataStore.districts);

                return {
                    lgas: referenceDataStore.lgas,
                    districts: referenceDataStore.districts,
                };
            } catch (error) {
                console.error('Failed to load reference data', error);
                referenceDataStore.isLoaded = false;

                if (lgaSelect) {
                    lgaSelect.innerHTML = '<option value="">Unable to load LGAs</option>';
                }
                if (districtSelect) {
                    districtSelect.innerHTML = '<option value="">Unable to load districts</option>';
                }

                throw error;
            } finally {
                referenceDataStore.isLoading = false;
                referenceDataStore.loadPromise = null;
            }
        })();

        referenceDataStore.loadPromise = loadTask;
        return loadTask;
    }

    function populateLgaSelect(lgas, { preserveSelection = false } = {}) {
        const lgaSelect = document.getElementById('lga-city');
        if (!lgaSelect) {
            return;
        }

        const previousValue = preserveSelection ? lgaSelect.value : '';

        lgaSelect.innerHTML = '<option value="">Select LGA</option>';

        lgas.forEach((lga) => {
            const option = document.createElement('option');
            option.value = String(lga.id);
            option.textContent = lga.name;
            option.dataset.name = lga.name;
            option.dataset.code = lga.code || '';
            option.dataset.slug = lga.slug || '';
            lgaSelect.appendChild(option);
        });

        if (preserveSelection && previousValue) {
            const restored = Array.from(lgaSelect.options).some((option) => option.value === previousValue);
            if (restored) {
                lgaSelect.value = previousValue;
            }
        }
    }

    function populateDistrictSelect(districts, { preserveSelection = false } = {}) {
        const districtSelect = document.getElementById('district-select');
        if (!districtSelect) {
            return;
        }

        const previousValue = preserveSelection ? districtSelect.value : '';

        districtSelect.innerHTML = '<option value="">Select District</option>';

        districts.forEach((district) => {
            const option = document.createElement('option');
            option.value = String(district.id);
            option.textContent = district.name;
            option.dataset.name = district.name;
            option.dataset.slug = district.slug || '';
            districtSelect.appendChild(option);
        });

        const otherOption = document.createElement('option');
        otherOption.value = 'other';
        otherOption.textContent = 'Other';
        districtSelect.appendChild(otherOption);

        if (preserveSelection && previousValue) {
            const restored = Array.from(districtSelect.options).some((option) => option.value === previousValue);
            if (restored) {
                districtSelect.value = previousValue;
            }
        }
    }

    function getSelectedOptionName(selectElement) {
        if (!selectElement) {
            return '';
        }

        const option = selectElement.selectedOptions && selectElement.selectedOptions[0];
        if (!option) {
            return '';
        }

        const optionValue = (option.value ?? '').trim();
        if (optionValue === '') {
            return '';
        }

        const label = option.dataset?.name ?? option.textContent ?? optionValue;
        return (label || '').trim();
    }

    function getSelectedOptionId(selectElement) {
        if (!selectElement) {
            return null;
        }

        const option = selectElement.selectedOptions && selectElement.selectedOptions[0];
        if (!option || option.value === '' || option.value === 'other') {
            return null;
        }

        const id = Number(option.value);
        return Number.isNaN(id) ? null : id;
    }

    function markFieldInvalid(element) {
        if (!element) {
            return;
        }

        element.classList.add('error-border');

        const clear = () => {
            element.classList.remove('error-border');
            element.removeEventListener('change', clear);
            element.removeEventListener('input', clear);
        };

        element.addEventListener('change', clear);
        element.addEventListener('input', clear);
    }

    function showSelectionError(message, element) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Missing Information',
                text: message,
                icon: 'warning',
                confirmButtonText: 'OK',
            });
        } else {
            alert(message);
        }

        markFieldInvalid(element);
        element?.focus();
    }

    function getTrimmedValueById(elementId) {
        const element = document.getElementById(elementId);
        if (!element || typeof element.value !== 'string') {
            return '';
        }

        return element.value.trim();
    }

    function collectEntityDetailsForSubmit() {
        const entityName = getTrimmedValueById('entity-name');
        const physicalAddress = getTrimmedValueById('entity-physical-address');
        const entityId = getTrimmedValueById('entity-id');
        const entityType = getRadioGroupValue('entity_type') || 'Individual';

        if (![entityName, physicalAddress, entityId].some((value) => value !== '')) {
            return null;
        }

        return {
            entity_id: entityId || null,
            entity_type: entityType,
            entity_name: entityName,
            physical_address: physicalAddress,
        };
    }

    function collectCustomerDetailsForSubmit() {
        const customerName = getTrimmedValueById('customer-name');
        const accountNo = getTrimmedValueById('customer-account-no');
        const customerCode = getTrimmedValueById('customer-code');
        const email = getTrimmedValueById('customer-email');
        const phone = getTrimmedValueById('customer-phone');
        const propertyAddress = getTrimmedValueById('customer-property-address');
        const retiredBy = getTrimmedValueById('customer-retired-by');
        const reasonRetired = getTrimmedValueById('customer-reason-retired');
        const customerId = getTrimmedValueById('customer-id');
        const status = document.getElementById('customer-status')?.value || 'Active';
        const customerType = getRadioGroupValue('customer_type') || 'Individual';

        if (
            ![
                customerName,
                accountNo,
                customerCode,
                email,
                phone,
                propertyAddress,
                retiredBy,
                reasonRetired,
                customerId,
            ].some((value) => value !== '')
        ) {
            return null;
        }

        return {
            customer_id: customerId || null,
            customer_type: customerType,
            status,
            customer_name: customerName,
            account_no: accountNo,
            customer_code: customerCode,
            email,
            phone,
            property_address: propertyAddress,
            retired_by: retiredBy,
            reason_retired: reasonRetired,
        };
    }

    window.loadReferenceData = loadReferenceData;
    window.referenceDataStore = referenceDataStore;

    const groupingState = {
        record: null,
        normalizedAwaiting: null,
        mismatch: true,
    };

    const autofillState = {
        groupedIndexed: false,
        cofo: false,
    };

    const cofoAutofillState = {
        container: null,
        icon: null,
        text: null,
        lastRequestId: 0,
    };

    const fileHistoryUI = {
        container: null,
        list: null,
        empty: null,
        error: null,
        loading: null,
        lastRequestId: 0,
        timelineButton: null,
        timelineModal: null,
        timelineContent: null,
        timelineClose: null,
        timelineDismiss: null,
        entries: [],
        currentFileNumber: null,
    };

    const propertyTransactionCache = {
        byFileNumber: new Map(),
    };

    const entityCustomerState = {
        entity: null,
        customer: null,
        fileNumber: null,
        hasRecord: false,
        lastRequestId: 0,
        initialized: false,
    };

    const entityCustomerUI = {
        section: null,
        refreshBtn: null,
        refreshIcon: null,
        refreshLabel: null,
        clearBtn: null,
    };

    const indexedFeedbackState = {
        container: null,
        icon: null,
        title: null,
        body: null,
        link: null,
        form: null,
        editButton: null,
        currentFileNumber: null,
        lastRequestId: 0,
        exists: false,
        record: null,
        toastShownFor: null,
        isDuplicate: false,
        promptShownFor: null,
        lastDecision: null,
    };

    const blindRequestNoticeState = {
        shownFor: null,
    };

    /**
     * Which registry family the current file number came out of, as reported by
     * GlobalFileNoModal (its `tab`: 'mls' | 'kangis' | 'newkangis' | …).
     *
     * KANGIS variant mode used to be inferred purely from the General Registry
     * <select>, which starts empty on a fresh Create form — so picking a KANGIS
     * file number before touching that dropdown ran the whole flow as a plain
     * duplicate (edit-mode prompt + backfill of the existing record) instead of
     * as a new physical variant destined for the _N suffix. Recording the source
     * at selection time removes that ordering dependency.
     */
    const fileSelectionState = {
        system: null,
    };

    const editModeState = {
        isEditing: false,
        recordId: null,
        record: null,
        fileNumber: null,
    };

    // Guards against duplicate submissions (double-click, Enter + click,
    // or a click landing while a previous request is still in flight).
    let isSubmittingFileIndex = false;

    const editModeBannerState = {
        container: null,
        body: null,
        fileNumber: null,
    };

    const sourceFileSelectionState = {
        fileId: null,
        normalizedFileNumber: '',
    };

    const TRANSACTION_TYPE_CANONICAL_MAP = {
        'DEED OF TRANSFER': 'Deed of Transfer',
        'DEED OF ASSIGNMENT': 'Deed of Assignment',
        'ST ASSIGNMENT': 'ST Assignment',
        'CERTIFICATE OF OCCUPANCY': 'Certificate of Occupancy',
        'C OF O': 'Certificate of Occupancy',
        'ST CERTIFICATE OF OCCUPANCY': 'ST Certificate of Occupancy',
        'SLTR CERTIFICATE OF OCCUPANCY': 'SLTR Certificate of Occupancy',
        'IRREVOCABLE POWER OF ATTORNEY': 'Irrevocable Power of Attorney',
        'POWER OF ATTORNEY': 'Power of Attorney',
        'DEED OF RELEASE': 'Deed of Release',
        'DEED OF MORTGAGE': 'Deed of Mortgage',
        'TRIPARTITE MORTGAGE': 'Tripartite Mortgage',
        'DEED OF SUB LEASE': 'Deed of Sub Lease',
        'DEED OF SUBLEASE': 'Deed of Sub Lease',
        'DEED OF SUB UNDER LEASE': 'Deed of Sub Under Lease',
        'DEED OF LEASE': 'Deed of Lease',
        'INDENTURE OF LEASE': 'Indenture of Lease',
        'DEED OF VARIATION': 'Deed of Variation',
        'DEED OF SURRENDER': 'Deed of Surrender',
        'DEED OF GIFT': 'Deed of Gift',
        'LETTER OF ADMINISTRATION': 'Letter of Administration',
        'CERTIFICATE OF PURCHASE': 'Certificate of Purchase',
        'TENANCY AGREEMENT': 'Tenancy Agreement',
        'CUSTOMARY RIGHT OF OCCUPANCY': 'Customary Right of Occupancy',
        'OCCUPATION PERMIT': 'Occupation Permit',
        'OTHER': 'Other',
    };

    function normalizeTransactionTypeValue(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        const textValue = String(value).trim();
        if (textValue === '') {
            return '';
        }

        const lookupKey = textValue.toUpperCase();
        return TRANSACTION_TYPE_CANONICAL_MAP[lookupKey] || textValue;
    }

    function resolveEditModeBannerElements() {
        if (editModeBannerState.container && document.body.contains(editModeBannerState.container)) {
            return editModeBannerState;
        }

        editModeBannerState.container = document.getElementById('edit-mode-banner') || null;
        editModeBannerState.body = document.getElementById('edit-mode-banner-body') || null;
        editModeBannerState.fileNumber = document.getElementById('edit-mode-banner-fileno') || null;

        return editModeBannerState;
    }

    function showEditModeBanner(record) {
        const elements = resolveEditModeBannerElements();
        if (!elements.container) {
            return;
        }

        const fileNumber = record?.file_number
            || editModeState.fileNumber
            || indexedFeedbackState.currentFileNumber
            || '';

        if (elements.fileNumber) {
            elements.fileNumber.textContent = fileNumber ? String(fileNumber).trim() : '';
        }

        elements.container.classList.remove('hidden');

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function hideEditModeBanner() {
        const elements = resolveEditModeBannerElements();
        if (!elements.container) {
            return;
        }

        elements.container.classList.add('hidden');
    }

    function refreshSubmitButtonLabel() {
        const createBtn = document.getElementById('create-file-btn');
        if (!createBtn) {
            return;
        }

        if (!createBtn.dataset.defaultLabel) {
            createBtn.dataset.defaultLabel = createBtn.textContent?.trim() || 'Create File Index';
        }
        if (!createBtn.dataset.editLabel) {
            createBtn.dataset.editLabel = 'Update File Index';
        }

        const createSpan = createBtn.querySelector('[data-state="create"]');
        const editSpan = createBtn.querySelector('[data-state="edit"]');

        if (createSpan) {
            createSpan.textContent = createBtn.dataset.defaultLabel;
        }
        if (editSpan) {
            editSpan.textContent = createBtn.dataset.editLabel;
        }
        const nextMode = editModeState.isEditing ? 'edit' : 'create';

        if (createSpan && editSpan) {
            if (editModeState.isEditing) {
                createSpan.classList.add('hidden');
                editSpan.classList.remove('hidden');
            } else {
                editSpan.classList.add('hidden');
                createSpan.classList.remove('hidden');
            }
        } else {
            createBtn.textContent = editModeState.isEditing
                ? createBtn.dataset.editLabel
                : createBtn.dataset.defaultLabel;
        }

        createBtn.dataset.mode = nextMode;
    }

    function enterEditMode(record) {
        editModeState.isEditing = true;
        editModeState.recordId = record?.id ?? null;
        editModeState.record = record || null;
        editModeState.fileNumber = record?.file_number
            ? String(record.file_number).trim().toUpperCase()
            : null;

        const form = document.getElementById('new-file-form');
        if (form) {
            if (editModeState.recordId) {
                form.dataset.editingId = editModeState.recordId;
            } else {
                delete form.dataset.editingId;
            }
        }

        refreshSubmitButtonLabel();
        showEditModeBanner(record || editModeState.record);
        updateCreateButtonState();

        const hasCofoToggle = document.getElementById('has-cofo-toggle');
        if (hasCofoToggle) {
            hasCofoToggle.disabled = false;
        }
        unlockCofOFieldsForEditing();
    }

    /**
     * True on /fileindexing/{id}/edit and the KANGIS update screen: the page was opened
     * FOR a specific record. The create page sets window.isEditMode = false and enters
     * edit mode dynamically ("Switch to edit mode"), where leaving it again is correct.
     */
    function isDedicatedUpdatePage() {
        return !!(window.isEditMode && window.editingRecord && window.editingRecord.id);
    }

    function exitEditMode() {
        if (!editModeState.isEditing) {
            return;
        }

        // On a dedicated update page, dropping edit mode is always wrong: the record is
        // still open and still being edited. It used to happen whenever the operator
        // picked a file number that is not itself already indexed — the form flipped back
        // to "Create File Index", the button disabled itself (updateCreateButtonState()
        // only enables on isEditing or a grouping record), and the loaded details were
        // treated as a half-finished create.
        if (isDedicatedUpdatePage()) {
            console.info('[file-index update] staying in edit mode: page is bound to record #'
                + window.editingRecord.id);
            return;
        }

        editModeState.isEditing = false;
        editModeState.recordId = null;
        editModeState.record = null;
        editModeState.fileNumber = null;

        const form = document.getElementById('new-file-form');
        if (form) {
            delete form.dataset.editingId;
        }

        refreshSubmitButtonLabel();
        hideEditModeBanner();
        updateCreateButtonState();
    }

    function normalizeBooleanFlag(value) {
        if (typeof value === 'string') {
            return ['1', 'true', 'yes'].includes(value.toLowerCase());
        }
        return Boolean(value);
    }

    function setCheckboxState(elementId, value) {
        const checkbox = document.getElementById(elementId);
        if (!checkbox) {
            return;
        }
        checkbox.checked = normalizeBooleanFlag(value);
    }

    function applyDistrictFromRecord(districtName, index = 0) {
        if (!districtName) {
            return;
        }

        const districtSelect = document.getElementById(`district-select-${index}`);
        if (!districtSelect) {
            return;
        }

        const customContainer = document.getElementById(`custom-district-container-${index}`);
        const customInput = document.getElementById(`custom-district-input-${index}`);
        const districts = Array.isArray(referenceDataStore?.districts) ? referenceDataStore.districts : [];
        const matched = districts.length
            ? populateSelectFromText(districtSelect, districtName, districts, 'name')
            : false;

        applyAutofillLock([districtSelect.id], false);

        if (matched) {
            if (customContainer) {
                customContainer.classList.add('hidden');
            }
            if (customInput) {
                customInput.value = '';
                customInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            districtSelect.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        if (districtSelect.querySelector('option[value="other"]')) {
            districtSelect.value = 'other';
            districtSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (customContainer && customInput) {
            customContainer.classList.remove('hidden');
            customInput.value = districtName;
            customInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function applyLgaFromRecord(lgaName, index = 0) {
        if (!lgaName) {
            return;
        }

        const lgaSelect = document.getElementById(`lga-city-${index}`);
        if (!lgaSelect) {
            return;
        }

        const lgas = Array.isArray(referenceDataStore?.lgas) ? referenceDataStore.lgas : [];
        const matched = lgas.length
            ? populateSelectFromText(lgaSelect, lgaName, lgas, 'name')
            : false;

        if (!matched) {
            const normalized = lgaName.toString().trim();
            let option = Array.from(lgaSelect.options).find(opt => normalizeGeoText(opt.textContent) === normalizeGeoText(normalized));

            if (!option) {
                option = document.createElement('option');
                option.value = normalized;
                option.textContent = normalized;
                option.dataset.dynamic = 'true';
                lgaSelect.appendChild(option);
            }

            lgaSelect.value = option.value;
        }

        applyAutofillLock([lgaSelect.id], false);
        lgaSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Split a stored placeholder ("KNML 03471", "MLKN 001 (T)") back into the prefix
     * select, the serial input and the Temporary box, the same way the KANGIS update
     * page does. A prefix since retired from the list is added back as an option
     * rather than dropped.
     */
    function applyStoredKangisPlaceholder(stored) {
        let placeholder = String(stored || '').trim();
        if (!placeholder) return;

        const tempBox = document.getElementById('kangis-fileno-is-temp');
        if (/\(\s*T\s*\)\s*$/i.test(placeholder)) {
            placeholder = placeholder.replace(/\(\s*T\s*\)\s*$/i, '').trim();
            if (tempBox) tempBox.checked = true;
        }

        // assembleKangisPlaceholder() writes the KN prefix without a space ("KN123").
        const knJoined = placeholder.match(/^(KN)(\d+)$/i);
        const parts = knJoined ? [knJoined[1], knJoined[2]] : placeholder.split(/\s+/);
        const prefix = (parts.shift() || '').toUpperCase();
        const serial = parts.join(' ').trim();

        const prefixSelect = document.getElementById('kangis-fileno-prefix');
        if (prefixSelect && prefix) {
            let option = Array.from(prefixSelect.options).find(o => (o.value || '').toUpperCase() === prefix);
            if (!option) {
                option = new Option(prefix, prefix);
                prefixSelect.appendChild(option);
            }
            prefixSelect.value = option.value;
            prefixSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const serialInput = document.getElementById('kangis-fileno-serial');
        if (serialInput && serial) {
            serialInput.value = serial;
            serialInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function applyExistingIndexedRecord(record) {
        if (!record || !record.id) {
            return;
        }

        enterEditMode(record);
        resetGroupingState();

        const groupingIdInput = document.getElementById('grouping-id');
        if (groupingIdInput) {
            groupingIdInput.value = '';
        }

        const fileNumberValue = record.file_number ?? '';
        if (fileNumberValue) {
            const fileNumberDisplay = document.getElementById('file-number-display');
            if (fileNumberDisplay) {
                setAutoFilledValue(fileNumberDisplay, fileNumberValue, { lock: false });
                enableManualEditing(fileNumberDisplay);
            }

            const fileNumberInput = document.getElementById('fileno');
            if (fileNumberInput) {
                fileNumberInput.value = fileNumberValue;
            }
        }

        const fileTitleInput = document.getElementById('file-title');
        if (fileTitleInput) {
            setAutoFilledValue(fileTitleInput, record.file_title ?? '', { lock: false });
            applyAutofillLock([fileTitleInput.id], false);
        }

        // Populate Root of Title. Only when the looked-up record actually carries one:
        // it is required on save, so overwriting a typed value with an empty lookup
        // would silently blank the one field the officer cannot leave empty.
        if (record.root_of_title) {
            setAutoFilledValue('root-of-title', record.root_of_title, { lock: false });
        }

        // Populate Original Holder
        setAutoFilledValue('original-holder', record.original_holder ?? '', { lock: false });

        // Populate Current Holder(s)
        // If record has current_holder data, use it; otherwise use file_title
        if (record.current_holder) {
            const holdersWrapper = document.getElementById('current-holders-wrapper');
            if (holdersWrapper) {
                // Clear existing holders
                holdersWrapper.innerHTML = '';

                // Handle both array and string formats
                let holders = record.current_holder;
                if (typeof holders === 'string' && holders.trim().startsWith('[') && holders.trim().endsWith(']')) {
                    try {
                        holders = JSON.parse(holders);
                    } catch (e) {
                        console.error('Error parsing current_holder JSON:', e);
                        holders = [holders];
                    }
                }
                const holdersArray = Array.isArray(holders) ? holders : [holders];

                holdersArray.forEach((holder, index) => {
                    const holderRow = document.createElement('div');
                    holderRow.className = 'current-holder-row flex gap-2';

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'current_holder[]';
                    input.value = holder || '';
                    input.className = 'current-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm';
                    if (index === 0) {
                        input.id = 'current-holder';
                    }

                    const buttonContainer = document.createElement('div');
                    if (index === 0) {
                        const addBtn = document.createElement('button');
                        addBtn.type = 'button';
                        addBtn.id = 'add-holder-btn';
                        addBtn.className = 'inline-flex items-center px-3 py-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50';
                        addBtn.innerHTML = '<i data-lucide="plus" class="h-4 w-4 text-blue-600"></i>';
                        buttonContainer.appendChild(addBtn);
                    } else {
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'remove-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50';
                        removeBtn.innerHTML = '<i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>';
                        buttonContainer.appendChild(removeBtn);
                    }

                    holderRow.appendChild(input);
                    holderRow.appendChild(buttonContainer);
                    holdersWrapper.appendChild(holderRow);
                });

                // Reinitialize lucide icons
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            }
        } else {
            // No current_holder in record - use file_title as Current Holder
            const currentHolderInput = document.getElementById('current-holder');
            if (currentHolderInput && record.file_title) {
                currentHolderInput.value = record.file_title;
                currentHolderInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        if (record.land_use_type || record.landuse) {
            populateLandUseFromRecord(record.land_use_type ?? record.landuse, 0);
        }
        setAutoFilledValue('plot-number-0', record.plot_number ?? '', { lock: false });
        setAutoFilledValue('tp-number-0', record.tp_no ?? record.tp_number ?? '', { lock: false });
        setAutoFilledValue('lpkn-no-0', record.lpkn_no ?? '', { lock: false });
        setAutoFilledValue('location-0', record.location ?? '', { lock: false });
        if (typeof record.plot_size !== 'undefined') {
            setAutoFilledValue('plot-size-0', record.plot_size ?? '', { lock: false });
        }

        // Trigger input events for property details to ensure location auto-generation and Alpine sync
        ['plot-number-0', 'tp-number-0', 'lpkn-no-0', 'plot-size-0'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
        });

        if (typeof window.dispatchEvent === 'function') {
            window.dispatchEvent(new CustomEvent('grouping-record-updated', { detail: record }));
        }

        // Sync hidden input and visible select for file_type (Customer Type)
        (function () {
            const fileTypeValue = record.file_type ?? '';
            const hiddenInput = document.getElementById('file_type');
            const selectEl = document.getElementById('file_type_select');
            const otherInput = document.getElementById('file_type_other');
            if (hiddenInput) hiddenInput.value = fileTypeValue;
            if (selectEl) {
                // Check if the value exists as an option in the dropdown
                const optionExists = Array.from(selectEl.options).some(opt => opt.value === fileTypeValue);
                if (optionExists) {
                    selectEl.value = fileTypeValue;
                    if (otherInput) { otherInput.classList.add('hidden'); otherInput.required = false; otherInput.value = ''; }
                } else if (fileTypeValue) {
                    selectEl.value = 'Other';
                    if (otherInput) { otherInput.classList.remove('hidden'); otherInput.required = true; otherInput.value = fileTypeValue; }
                }
            }
        })();

        // Restore the Parcel Update classification flags (Dummy File / Temporary). Their
        // comment lives in the shared Remark / Comment box, which the title-status controller
        // regenerates from the selected types once we dispatch the change below.
        (function () {
            const selected = String(record.file_classification ?? '')
                .split(',').map(s => s.trim()).filter(Boolean);
            document.querySelectorAll('.ts-classification-cb').forEach(cb => {
                cb.checked = selected.includes(cb.value);
            });
            const target = document.querySelector('.ts-classification-cb:checked');
            if (target) target.dispatchEvent(new Event('change', { bubbles: true }));
        })();

        setAutoFilledValue('dob-0', record.dob ? String(record.dob).substring(0, 10) : '', { lock: false });
        setAutoFilledValue('nin-0', record.nin ?? '', { lock: false });
        setAutoFilledValue('tin-0', record.tin ?? '', { lock: false });
        setAutoFilledValue('rc-no-0', record.rc_no ?? '', { lock: false });
        setAutoFilledValue('country-code-0', record.country_code ?? '+234', { lock: false });
        setAutoFilledValue('phone-0', record.phone ?? '', { lock: false });
        setAutoFilledValue('residence_address-0', record.residence_address ?? '', { lock: false });

        // Trigger input events for personal details
        ['file-type-0', 'dob-0', 'nin-0', 'tin-0', 'rc-no-0', 'phone-0', 'residence_address-0'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
            if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Entity & Customer Details
        document.querySelectorAll('.entity-customer-cards').forEach(el => el.classList.remove('hidden'));
        if (record.entity_type) setRadioGroupValue('entity_type[0]', record.entity_type);
        setAutoFilledValue('entity-name-0', record.entity_name ?? '', { lock: false });
        setAutoFilledValue('entity-physical-address-0', record.entity_physical_address ?? '', { lock: false });

        if (record.customer_type) setRadioGroupValue('customer_type[0]', record.customer_type);
        setAutoFilledValue('customer-name-0', record.customer_name ?? '', { lock: false });
        setAutoFilledValue('customer-account-no-0', record.customer_account_no ?? '', { lock: false });
        setAutoFilledValue('customer-code-0', record.customer_code ?? '', { lock: false });
        setAutoFilledValue('customer-property-address-0', record.customer_property_address ?? '', { lock: false });

        // Trigger events for entity/customer
        ['entity-name-0', 'entity-physical-address-0', 'customer-name-0', 'customer-account-no-0', 'customer-code-0', 'customer-property-address-0'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
        });

        setAutoFilledValue('dciv-reason', record.dciv_reason ?? '', { lock: false });

        const awaitingValue = record.awaiting_file_no
            ?? record.archive_file_no
            ?? fileNumberValue;
        setAutoFilledValue('archive-file-no', fileNumberValue ?? '', { lock: false });
        setAutoFilledValue('awaiting-file-no', awaitingValue ?? '', { lock: false });

        setAutoFilledValue('tracking-id', record.tracking_id ?? '', { lock: false });

        // General Registry Fix
        const genRegistrySelect = document.getElementById('general-registry');
        if (genRegistrySelect) {
            const regValue = record.general_registry ?? record.registry ?? '';
            if (regValue) {
                // Only set value if the option exists in the dropdown
                const optionExists = Array.from(genRegistrySelect.options).some(opt => opt.value === regValue);
                if (optionExists) {
                    genRegistrySelect.value = regValue;
                    genRegistrySelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        // KANGIS FileNo Placeholder. The registry change above reveals the prefix +
        // serial fields empty; without putting the stored placeholder back, the edit
        // form showed a KANGIS record as having none and made the officer re-key it.
        applyStoredKangisPlaceholder(record.kangis_fileno_placeholder);

        setAutoFilledValue('physical-registry', record.physical_registry ?? '', { lock: false });
        const recordMdcBatch = record.mdc_batch_no
            ?? record.mdcBatchNo
            ?? record.batch_no
            ?? record.batchNo
            ?? '';
        setAutoFilledValue('mdc-batch-no', recordMdcBatch, { lock: false });
        setAutoFilledValue('registry-batch-no', record.registry_batch_no ?? '', { lock: false });
        setAutoFilledValue('sys-batch-no', record.sys_batch_no ?? '', { lock: false });
        setAutoFilledValue('shelf-rack-no', record.shelf_location ?? '', { lock: false });
        setAutoFilledValue('serial-no', record.serial_no ?? '', { lock: false });
        setAutoFilledValue('indexed-by', record.indexed_by ?? '', { lock: false });
        if (record.indexed_date) {
            setAutoFilledValue('indexed-date', String(record.indexed_date).substring(0, 10), { lock: false });
        }

        const batchHidden = document.getElementById('batch-no');
        if (batchHidden) {
            batchHidden.value = record.batch_no ?? '';
        }
        const shelfLocationHidden = document.getElementById('shelf-location');
        if (shelfLocationHidden) {
            shelfLocationHidden.value = record.shelf_location ?? '';
        }
        const shelfLabelHidden = document.getElementById('shelf_label_id');
        if (shelfLabelHidden) {
            shelfLabelHidden.value = record.shelf_label_id ?? '';
        }
        const batchIdHidden = document.getElementById('batch_id');
        if (batchIdHidden) {
            batchIdHidden.value = record.batch_id ?? record.group_no ?? '';
        }

        setCheckboxState('has-cofo', record.has_cofo);
        setCheckboxState('has-transaction', record.has_transaction);
        setCheckboxState('co-owned-plot', record.is_co_owned_plot);
        setCheckboxState('merged-plot', record.is_merged);
        setCheckboxState('is-problematic', record.is_problematic);

        // Populate Related File Numbers.
        //
        // This runs every time the operator switches to another indexed record on the same
        // page (type file number A -> edit, then file number B -> edit). The rows therefore
        // have to be rebuilt from THIS record every time: leaving the previous record's rows
        // in place would submit them against the new file and create links it never had.
        const relatedFileno = record.related_fileno;
        {
            let relatedFiles = [];
            try {
                if (typeof relatedFileno === 'string') {
                    const trimmed = relatedFileno.trim();
                    if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
                        relatedFiles = JSON.parse(trimmed);
                    } else if (trimmed !== '') {
                        relatedFiles = [trimmed];
                    }
                } else if (Array.isArray(relatedFileno)) {
                    relatedFiles = relatedFileno;
                }
            } catch (e) {
                console.error('Error parsing related_fileno:', e);
                relatedFiles = relatedFileno ? [relatedFileno] : [];
            }

            // Filter out empty values
            relatedFiles = relatedFiles.filter(f => f && String(f).trim() !== '');

            if (relatedFiles.length === 0) {
                // No related files on this record — clear anything a previously edited
                // record left behind, so nothing is carried over. One blank row stays:
                // "Add Another" works by cloning the first row, and emptying the wrapper
                // left it nothing to clone, so ticking "Has Related File?" showed no
                // field and the button did nothing.
                const wrapper = document.getElementById('related-files-wrapper');
                if (wrapper) {
                    const rows = Array.from(wrapper.querySelectorAll('.related-file-row'));
                    rows.slice(1).forEach(r => r.remove());
                    const first = rows[0];
                    if (first) {
                        const input = first.querySelector('.related-file-input');
                        if (input) input.value = '';
                    } else {
                        wrapper.innerHTML = '';
                    }
                }

                const hasRelatedCheckbox = document.getElementById('has-related-file');
                if (hasRelatedCheckbox && hasRelatedCheckbox.checked) {
                    hasRelatedCheckbox.checked = false;
                    hasRelatedCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            if (relatedFiles.length > 0) {
                const hasRelatedCheckbox = document.getElementById('has-related-file');
                if (hasRelatedCheckbox) {
                    hasRelatedCheckbox.checked = true;
                    // Trigger change to show wrapper and add button
                    hasRelatedCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const wrapper = document.getElementById('related-files-wrapper');
                if (wrapper) {
                    // Clear existing rows
                    wrapper.innerHTML = '';

                    relatedFiles.forEach((fileNo, index) => {
                        const row = document.createElement('div');
                        row.className = 'related-file-row flex gap-2';

                        const inputContainer = document.createElement('div');
                        inputContainer.className = 'flex-grow';

                        const input = document.createElement('input');
                        input.type = 'text';
                        input.name = 'related_fileno[]';
                        input.value = fileNo;
                        input.className = 'related-file-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50';
                        input.readOnly = true;
                        inputContainer.appendChild(input);

                        const buttonContainer = document.createElement('div');
                        buttonContainer.className = 'flex gap-2';

                        const selectBtn = document.createElement('button');
                        selectBtn.type = 'button';
                        selectBtn.className = 'select-related-btn inline-flex items-center px-3 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50';
                        selectBtn.title = 'Select File Number';
                        selectBtn.innerHTML = '<i data-lucide="search" class="h-4 w-4 text-indigo-600"></i>';

                        const clearBtn = document.createElement('button');
                        clearBtn.type = 'button';
                        clearBtn.className = 'clear-related-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50';
                        clearBtn.title = 'Remove/Clear';
                        clearBtn.innerHTML = `<i data-lucide="${index === 0 ? 'eraser' : 'trash-2'}" class="h-4 w-4 text-red-600"></i>`;

                        buttonContainer.appendChild(selectBtn);
                        buttonContainer.appendChild(clearBtn);

                        row.appendChild(inputContainer);
                        row.appendChild(buttonContainer);
                        wrapper.appendChild(row);
                    });

                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                        window.lucide.createIcons();
                    }
                }
            }
        }

        const hasExistingCofo = normalizeBooleanFlag(record.has_cofo);
        const hasCofoToggle = document.getElementById('has-cofo-toggle');
        const hiddenHasCofoField = document.getElementById('has-cofo');
        const cofoDetailsContainer = document.getElementById('cofo-details-container');
        if (hasCofoToggle) {
            hasCofoToggle.checked = hasExistingCofo;
        }
        if (hiddenHasCofoField) {
            hiddenHasCofoField.checked = hasExistingCofo;
        }
        if (hasExistingCofo && cofoDetailsContainer) {
            cofoDetailsContainer.classList.remove('hidden');
            unlockCofOFieldsForEditing();
        }

        // RoFO edit mode population
        const hasExistingRofo = normalizeBooleanFlag(record.has_rofo);
        const hasRofoToggleEdit = document.getElementById('has-rofo-toggle');
        const rofoDetailsContainerEdit = document.getElementById('rofo-details-container');
        if (hasRofoToggleEdit) {
            hasRofoToggleEdit.checked = hasExistingRofo;
        }
        if (hasExistingRofo && rofoDetailsContainerEdit) {
            rofoDetailsContainerEdit.classList.remove('hidden');
            const rofoFieldMap = {
                'rofo-date': record.rofo_date,
                'rofo-number': record.rofo_number || record.rofo_file_number || record.file_number,
                'rofo-file-number': record.rofo_file_number || record.file_number,
                'rofo-land-use': record.rofo_land_use,
                'rofo-grantor': record.rofo_grantor,
                'rofo-grantee': record.rofo_grantee,
            };
            Object.entries(rofoFieldMap).forEach(([id, value]) => {
                const el = document.getElementById(id);
                if (el && value) el.value = value;
            });
        }

        // Occupancy Permit edit mode population
        const hasExistingOccupancyPermit = normalizeBooleanFlag(record.has_occupancy_permit);
        const hasOccupancyPermitToggleEdit = document.getElementById('has-occupancy-permit-toggle');
        const occupancyPermitDetailsContainerEdit = document.getElementById('occupancy-permit-details-container');
        if (hasOccupancyPermitToggleEdit) {
            hasOccupancyPermitToggleEdit.checked = hasExistingOccupancyPermit;
        }
        if (hasExistingOccupancyPermit && occupancyPermitDetailsContainerEdit) {
            occupancyPermitDetailsContainerEdit.classList.remove('hidden');
            const occupancyPermitFieldMap = {
                'occupancy-permit-op-type': record.occupancy_permit_op_type,
                'occupancy-permit-op-category': record.occupancy_permit_op_category,
                'occupancy-permit-op-serial-number': record.occupancy_permit_op_serial_number,
                'occupancy-permit-date': record.occupancy_permit_date,
                'occupancy-permit-file-number': record.occupancy_permit_file_number || record.file_number,
                'occupancy-permit-land-use': record.occupancy_permit_land_use,
                'occupancy-permit-grantor': record.occupancy_permit_grantor,
                'occupancy-permit-grantee': record.occupancy_permit_grantee,
                'occupancy-permit-serial-no': record.occupancy_permit_serial_no,
                'occupancy-permit-page-no': record.occupancy_permit_page_no || record.occupancy_permit_serial_no,
                'occupancy-permit-vol-no': record.occupancy_permit_vol_no,
                'occupancy-permit-deeds-time': record.occupancy_permit_deeds_time,
                'occupancy-permit-deeds-date': record.occupancy_permit_deeds_date,
            };
            Object.entries(occupancyPermitFieldMap).forEach(([id, value]) => {
                const el = document.getElementById(id);
                if (el && value) el.value = value;
            });

            // A record saved as an LGA permit must reopen with the picker shown and the
            // registration particulars locked, not as a normal editable OP.
            const opLgaGrantorEdit = document.getElementById('occupancy-permit-lga-grantor');
            if (opLgaGrantorEdit && record.occupancy_permit_grantor) {
                const stored = String(record.occupancy_permit_grantor);
                if (!Array.from(opLgaGrantorEdit.options).some((o) => o.value === stored)) {
                    const option = document.createElement('option');
                    option.value = stored;
                    option.textContent = stored;
                    opLgaGrantorEdit.appendChild(option);
                }
                opLgaGrantorEdit.value = stored;
            }
            if (typeof window.applyOccupancyPermitLgaRules === 'function') {
                window.applyOccupancyPermitLgaRules();
            }
        }

        loadReferenceData()
            .then(() => {
                applyDistrictFromRecord(record.district, 0);
                applyLgaFromRecord(record.lga, 0);
            })
            .catch(() => {
                applyDistrictFromRecord(record.district, 0);
                applyLgaFromRecord(record.lga, 0);
            });

        if (fileNumberValue && typeof loadFileHistoryForFile === 'function') {
            loadFileHistoryForFile(fileNumberValue);
        }

        if (record?.id && fileNumberValue) {
            fetchTransactionsForRecordId(record.id, fileNumberValue);
        }
    }


    // Cache DOM references for the indexed feedback banner.
    function resolveIndexedFeedbackElements() {
        if (indexedFeedbackState.container && document.body.contains(indexedFeedbackState.container)) {
            return indexedFeedbackState;
        }

        indexedFeedbackState.container = document.getElementById('file-indexed-feedback');
        if (!indexedFeedbackState.container) {
            return indexedFeedbackState;
        }

        indexedFeedbackState.icon = indexedFeedbackState.container.querySelector('.file-indexed-feedback-icon') || null;
        indexedFeedbackState.title = document.getElementById('file-indexed-feedback-title') || null;
        indexedFeedbackState.body = document.getElementById('file-indexed-feedback-body') || null;
        indexedFeedbackState.link = document.getElementById('file-indexed-feedback-link') || null;
        indexedFeedbackState.form = indexedFeedbackState.form || document.getElementById('new-file-form');
        indexedFeedbackState.editButton = document.getElementById('file-indexed-feedback-edit-btn') || null;

        if (indexedFeedbackState.editButton && indexedFeedbackState.editButton.dataset.bound !== 'true') {
            indexedFeedbackState.editButton.addEventListener('click', () => {
                if (indexedFeedbackState.record && !isKangisVariantMode()) {
                    promptExistingIndexedRecord(indexedFeedbackState.record, { forcePrompt: true });
                }
            });
            indexedFeedbackState.editButton.dataset.bound = 'true';
        }

        return indexedFeedbackState;
    }

    function hideIndexedFeedback() {
        const elements = resolveIndexedFeedbackElements();
        if (!elements.container) {
            return;
        }

        elements.container.classList.add('hidden');
        if (elements.title) {
            elements.title.textContent = '';
        }
        if (elements.body) {
            elements.body.textContent = '';
        }
        if (elements.link) {
            elements.link.classList.add('hidden');
            elements.link.removeAttribute('href');
        }

        if (indexedFeedbackState.editButton) {
            indexedFeedbackState.editButton.classList.add('hidden');
            indexedFeedbackState.editButton.classList.remove('inline-flex');
        }

        indexedFeedbackState.exists = false;
        indexedFeedbackState.record = null;
        indexedFeedbackState.toastShownFor = null;
        indexedFeedbackState.isDuplicate = false;
        indexedFeedbackState.promptShownFor = null;
        indexedFeedbackState.lastDecision = null;

        updateCreateButtonState();
    }

    function formatIndexedTimestamp(value) {
        if (!value && value !== 0) {
            return null;
        }

        const parsed = Date.parse(value);
        if (!Number.isNaN(parsed)) {
            return new Date(parsed).toLocaleString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        const text = String(value).trim();
        return text === '' ? null : text;
    }

    function showIndexedFeedback(record, options = {}) {
        const elements = resolveIndexedFeedbackElements();
        if (!elements.container) {
            return;
        }

        const {
            viewUrl = '',
        } = options;

        const recordId = record?.id;
        const updatedAt = formatIndexedTimestamp(record?.updated_at ?? record?.created_at ?? null);
        const fileTitle = record?.file_title ? String(record.file_title).trim() : '';

        const titleText = isKangisVariantMode() ? 'Existing Kangis record' : 'Indexed record detected';
        const descriptionParts = [];

        if (recordId) {
            descriptionParts.push(`Record #${recordId}`);
        }

        if (fileTitle) {
            descriptionParts.push(fileTitle);
        }

        if (updatedAt) {
            descriptionParts.push(`Last updated ${updatedAt}`);
        }

        const bodyText = descriptionParts.join(' • ') || 'An existing indexing record already references this file number.';

        elements.container.classList.remove('hidden');
        if (elements.title) {
            elements.title.textContent = titleText;
        }
        if (elements.body) {
            elements.body.textContent = bodyText;
        }

        if (elements.link) {
            if (viewUrl) {
                elements.link.href = viewUrl;
                elements.link.classList.remove('hidden');
            } else {
                elements.link.classList.add('hidden');
                elements.link.removeAttribute('href');
            }
        }

        if (indexedFeedbackState.editButton) {
            if (isKangisVariantMode()) {
                // Hide "Switch to edit mode" in KANGIS variant mode
                indexedFeedbackState.editButton.classList.add('hidden');
                indexedFeedbackState.editButton.classList.remove('inline-flex');
            } else {
                indexedFeedbackState.editButton.classList.remove('hidden');
                indexedFeedbackState.editButton.classList.add('inline-flex');
            }
        }

        // Restyle the banner for KANGIS variant mode: blue info instead of amber warning
        if (isKangisVariantMode() && elements.container) {
            elements.container.classList.remove('bg-amber-50', 'border-amber-200', 'text-amber-800');
            elements.container.classList.add('bg-blue-50', 'border-blue-200', 'text-blue-800');
            if (elements.title) {
                elements.title.classList.remove('text-amber-800');
                elements.title.classList.add('text-blue-800');
            }
        }

        if (elements.icon && typeof window.lucide?.createIcons === 'function') {
            window.lucide.createIcons();
        }

        indexedFeedbackState.exists = true;
        indexedFeedbackState.record = record || null;

        // In KANGIS variant mode, do NOT mark as duplicate — the user is
        // intentionally creating a physical variant of an existing file number.
        if (isKangisVariantMode()) {
            indexedFeedbackState.isDuplicate = false;
            updateCreateButtonState();
            return; // skip the "already indexed" toast — not relevant here
        }

        indexedFeedbackState.isDuplicate = true;

        updateCreateButtonState();

        if (typeof Swal !== 'undefined' && indexedFeedbackState.toastShownFor !== indexedFeedbackState.currentFileNumber) {
            Swal.fire({
                title: 'File already indexed',
                text: 'An indexed record already exists. Duplicate file numbers are not allowed.',
                icon: 'warning',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
            });
            indexedFeedbackState.toastShownFor = indexedFeedbackState.currentFileNumber;
        }
    }

    /** Returns true when the user is working in KANGIS variant mode.
     *  "soft" = the file number is a KANGIS one (placeholder fields visible, or the
     *           number was picked from the modal's KANGIS tab — the dropdown may not
     *           have been set yet, and the guards must not depend on that ordering).
     *  "full" = prefix AND serial are both filled (ready to submit). */
    function isKangisVariantMode(level) {
        // In new_kn mode there is no placeholder variant — skip all KANGIS variant logic
        if (window.isNewKnMode) return false;
        const wrapper = document.getElementById('kangis-fileno-placeholder-wrapper');
        const isKangisRegistry = (wrapper && !wrapper.classList.contains('hidden'))
            || fileSelectionState.system === 'kangis';
        if (level === 'full') {
            if (!isKangisRegistry) return false;
            const prefix = (document.getElementById('kangis-fileno-prefix')?.value || '').trim();
            const serial = (document.getElementById('kangis-fileno-serial')?.value || '').trim();
            return prefix !== '' && serial !== '';
        }
        return !!isKangisRegistry;
    }

    // Exposed for the section partials rendered outside this module (cofo_details,
    // property_transaction_modal) — they need the same "this is a new physical
    // variant, not the same file" answer this module gates its own logic on.
    window.isKangisVariantMode = isKangisVariantMode;

    /**
     * Point General Registry at KANGIS and fire `change`, so handleRegistryFieldToggling()
     * reveals the KANGIS FileNo Placeholder fields. Without this the operator had to set
     * the dropdown by hand before picking the file, and the placeholder — which is what
     * makes the record a physical variant rather than a duplicate — was easy to miss.
     *
     * Skipped in new_kn mode (that mode selects KANGIS itself and has no placeholder)
     * and when the dropdown already reads KANGIS.
     */
    function selectKangisGeneralRegistry() {
        if (window.isNewKnMode) return false;

        const select = document.getElementById('general-registry');
        if (!select) return false;

        if ((select.value || '').toUpperCase().includes('KANGIS')) {
            return true;
        }

        const option = Array.from(select.options)
            .find(o => (o.value || '').toUpperCase().includes('KANGIS'));
        if (!option) {
            // No KANGIS option on this form — the file-number-derived flag in
            // isKangisVariantMode() still carries the guards.
            return false;
        }

        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    async function checkTemporaryFileAssociationInline(fileNumber) {
        if (!fileNumber) return;

        const isEditingExisting = editModeState.isEditing && editModeState.recordId;
        const isTempFileSuffix = /\(\s*T\s*\)\s*$/i.test(fileNumber);

        if (!isEditingExisting && !isTempFileSuffix && !isKangisVariantMode()) {
            try {
                const checkUrl = `/fileindexing/api/check-temp-association?file_number=${encodeURIComponent(fileNumber)}`;
                const chkResponse = await fetch(checkUrl, {
                    headers: { Accept: 'application/json' },
                });
                if (chkResponse.ok) {
                    const checkData = await chkResponse.json();
                    if (checkData.has_associated_temp && typeof Swal !== 'undefined') {
                        await Swal.fire({
                            title: 'Temporary File Detected',
                            html: `An existing temporary file <strong>${checkData.temp_record.temp_file_no}</strong> was found for this file number in the database.<br><br>` +
                                `Saving will merge this main file number into the existing temporary indexing record.<br><br>` +
                                `You can continue filling out the details.`,
                            icon: 'warning',
                            confirmButtonText: 'Proceed',
                            confirmButtonColor: '#3085d6',
                        });
                    }
                }
            } catch (err) {
                console.error('Error checking temporary file association', err);
            }
        }
    }

    async function promptExistingIndexedRecord(record, options = {}) {
        const {
            forcePrompt = false,
            variant = 'default',
            promptMessage = null,
        } = options;
        const fileNumber = record?.file_number || indexedFeedbackState.currentFileNumber || null;

        if (!forcePrompt
            && indexedFeedbackState.promptShownFor === fileNumber
            && indexedFeedbackState.lastDecision === 'declined') {
            return false;
        }

        indexedFeedbackState.promptShownFor = fileNumber;

        let shouldEdit = false;

        const promptCopy = variant === 'submission_duplicate'
            ? {
                title: 'Duplicate File Number',
                text: promptMessage || 'This file number has already been indexed. Do you want to update?',
                icon: 'warning',
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                declineAlert: null,
            }
            : {
                title: 'Edit existing record?',
                text: 'A file indexing entry already exists for this file number. Do you want to switch to edit mode and update it?',
                icon: 'question',
                confirmButtonText: 'Yes, edit record',
                cancelButtonText: 'No, keep creating',
                declineAlert: {
                    title: 'Duplicate prevented',
                    text: 'Duplicate indexing is not allowed. Choose a different file number to continue creating a new record.',
                    icon: 'info',
                },
            };

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: promptCopy.title,
                text: promptCopy.text,
                icon: promptCopy.icon,
                showCancelButton: true,
                confirmButtonText: promptCopy.confirmButtonText,
                cancelButtonText: promptCopy.cancelButtonText,
                reverseButtons: true,
            });
            shouldEdit = result.isConfirmed;
        } else {
            shouldEdit = window.confirm(promptCopy.text);
        }

        if (shouldEdit) {
            indexedFeedbackState.lastDecision = 'accepted';
            applyExistingIndexedRecord(record);
            return true;
        }

        indexedFeedbackState.lastDecision = 'declined';

        if (promptCopy.declineAlert && typeof Swal !== 'undefined' && !forcePrompt) {
            await Swal.fire({
                title: promptCopy.declineAlert.title,
                text: promptCopy.declineAlert.text,
                icon: promptCopy.declineAlert.icon,
                confirmButtonText: 'OK',
            });
        } else if (promptCopy.declineAlert && !forcePrompt) {
            alert(promptCopy.declineAlert.text);
        }

        exitEditMode();
        return false;
    }

    async function handleDuplicateSubmissionConflict(record, message = '') {
        if (!record) {
            return;
        }

        // If the KANGIS placeholder field is filled, the controller will create a new
        // grouping variant instead of a true duplicate — skip the conflict prompt and
        // show a non-blocking info banner instead.
        const kangisPlaceholderInput = document.getElementById('kangis-fileno-placeholder');
        const kangisPlaceholderValue = (kangisPlaceholderInput?.value || '').trim();
        if (kangisPlaceholderValue) {
            const selectedFileNumber = record?.file_number || groupingState.record?.awaiting_fileno || '';
            // Show a brief informational feedback strip (not blocking)
            const fb = document.getElementById('kangis-placeholder-feedback');
            const fbText = document.getElementById('kangis-placeholder-feedback-text');
            if (fb && fbText) {
                fb.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-blue-700 bg-blue-50 border-blue-200';
                fbText.innerHTML = `KANGIS physical variant detected — a new grouping record will be created for ` +
                    `<strong>${kangisPlaceholderValue}</strong> and automatically assigned a unique file number suffix.`;
                fb.classList.remove('hidden');
            }
            return; // Do not block submission
        }

        const duplicateMessage = message?.trim?.() ? message : 'This file number has already been indexed. Do you want to update?';

        if (record.file_number) {
            indexedFeedbackState.currentFileNumber = String(record.file_number).trim();
        }

        try {
            const form = document.getElementById('new-file-form');
            const viewTemplate = form?.dataset?.indexedViewUrlTemplate || '';
            const viewUrl = record.id && viewTemplate
                ? viewTemplate.replace('__ID__', encodeURIComponent(record.id))
                : '';

            showIndexedFeedback(record, {
                viewUrl,
                source: 'submission_duplicate',
            });
        } catch (renderError) {
            console.warn('Unable to render duplicate feedback banner:', renderError);
        }

        await promptExistingIndexedRecord(record, {
            forcePrompt: true,
            variant: 'submission_duplicate',
            promptMessage: duplicateMessage,
        });
    }

    async function checkFileAlreadyIndexed(fileNumber, options = {}) {
        const elements = resolveIndexedFeedbackElements();
        indexedFeedbackState.form = elements.form || document.getElementById('new-file-form');

        // Strip temporary (T) suffix so RES-2026-1(T) looks up as RES-2026-1
        const trimmed = (fileNumber || '').trim().replace(/\(\s*T\s*\)\s*$/i, '').trim();
        indexedFeedbackState.currentFileNumber = trimmed || null;

        if (indexedFeedbackState.promptShownFor
            && indexedFeedbackState.promptShownFor !== indexedFeedbackState.currentFileNumber) {
            indexedFeedbackState.promptShownFor = null;
            indexedFeedbackState.lastDecision = null;
        }

        if (!trimmed) {
            hideIndexedFeedback();
            exitEditMode();
            return null;
        }

        const form = indexedFeedbackState.form;
        const endpoint = form?.dataset?.checkIndexedUrl || '';

        if (!endpoint) {
            return null;
        }

        const requestId = indexedFeedbackState.lastRequestId + 1;
        indexedFeedbackState.lastRequestId = requestId;

        // Capture General Registry value
        const generalRegistry = document.getElementById('general-registry')?.value || '';

        const url = endpoint.includes('?')
            ? `${endpoint}&fileno=${encodeURIComponent(trimmed)}&registry=${encodeURIComponent(generalRegistry)}`
            : `${endpoint}?fileno=${encodeURIComponent(trimmed)}&registry=${encodeURIComponent(generalRegistry)}`;

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch (parseError) {
                payload = null;
            }

            if (indexedFeedbackState.lastRequestId !== requestId) {
                return null;
            }

            const blindNotice = payload?.blind_request_notice || null;
            const blindReady = !!payload?.blind_request_ready_for_indexing;
            const selectedFile = indexedFeedbackState.currentFileNumber || trimmed;

            if (blindReady && blindNotice && selectedFile
                && blindRequestNoticeState.shownFor !== selectedFile
                && !window.isEditMode) {
                const promptMessage = blindNotice.message
                    || `A blind request was sent for ${selectedFile}. Once indexing is completed, this file will be ready for logout.`;

                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        title: 'Blind Request Update',
                        text: promptMessage,
                        icon: 'info',
                        confirmButtonText: 'Continue Indexing',
                        confirmButtonColor: '#2563eb',
                    });
                } else {
                    alert(promptMessage);
                }

                blindRequestNoticeState.shownFor = selectedFile;
            }

            if (!blindReady && selectedFile && blindRequestNoticeState.shownFor === selectedFile) {
                blindRequestNoticeState.shownFor = null;
            }

            // Handle Grouping Autofill if found but not indexed
            if (payload && !payload.exists && payload.grouping_found && payload.grouping_record) {
                hideIndexedFeedback();
                exitEditMode();
                if (typeof window.populateFromGroupingRecord === 'function') {
                    window.populateFromGroupingRecord(payload.grouping_record);
                }
                return null;
            }

            if (!response.ok || !payload || !payload.exists) {
                hideIndexedFeedback();
                exitEditMode();
                return null;
            }

            const record = payload.record || {};
            const template = form?.dataset?.indexedViewUrlTemplate || '';
            const viewUrl = record.id && template
                ? template.replace('__ID__', encodeURIComponent(record.id))
                : '';

            showIndexedFeedback(record, {
                viewUrl,
                source: options.source,
            });
            if (options.source === 'initial_load' && window.isEditMode) {
                applyExistingIndexedRecord(record);
                return record;
            }

            if (editModeState.isEditing && editModeState.recordId === record.id) {
                showEditModeBanner(record);
                return record;
            }

            // When KANGIS Registry is selected, skip the "Edit existing record?" prompt —
            // the user is intentionally creating a physical variant.
            if (isKangisVariantMode()) {
                return record;
            }

            // Same on a dedicated update page. The operator is re-pointing THIS record at
            // a different file number; offering to load the other record instead would
            // replace everything on screen with that record's values.
            if (isDedicatedUpdatePage()) {
                return record;
            }

            await promptExistingIndexedRecord(record, { source: options.source });
            return record;
        } catch (error) {
            if (indexedFeedbackState.lastRequestId === requestId) {
                console.error('Indexed file lookup failed:', error);
                hideIndexedFeedback();
                exitEditMode();
            }
            return null;
        }
    }

    window.checkFileAlreadyIndexed = checkFileAlreadyIndexed;

    // Exposed for pages that already hold the exact row and must not re-resolve it by
    // file number (the KANGIS update page: checkIndexed() strips separators, so
    // "KNML 1_2" and "KNML 12" collapse to the same key and could populate the
    // wrong physical file). Additive — nothing in this file's own flow calls it.
    window.applyExistingIndexedRecord = applyExistingIndexedRecord;

    const ENTITY_TEXT_FIELD_IDS = ['entity-name', 'entity-physical-address'];
    const CUSTOMER_TEXT_FIELD_IDS = [
        'customer-name',
        'customer-email',
        'customer-phone',
        'customer-account-no',
        'customer-code',
        'customer-retired-by',
        'customer-reason-retired',
        'customer-property-address',
        'customer-status',
    ];

    const LOCKABLE_ENTITY_CUSTOMER_FIELD_IDS = ENTITY_TEXT_FIELD_IDS.concat(
        CUSTOMER_TEXT_FIELD_IDS.filter(id => id !== 'customer-status')
    );

    const REQUIRED_ENTITY_CUSTOMER_FIELD_IDS = LOCKABLE_ENTITY_CUSTOMER_FIELD_IDS.slice();
    const PROPERTY_ADDRESS_FIELD_IDS = [];
    const propertyAddressLinkState = {
        masterFieldId: null,
        listenersInitialized: false,
    };

    const ENTITY_TYPE_FIELD_NAME = 'entity_type';
    const CUSTOMER_TYPE_FIELD_NAME = 'customer_type';

    const LAND_USE_CODE_MAP = {
        RES: 'RESIDENTIAL',
        COM: 'COMMERCIAL',
        IND: 'INDUSTRIAL',
        MIX: 'COMMERCIAL AND RESIDENTIAL',
        MIXED: 'COMMERCIAL AND RESIDENTIAL',
        AG: 'AGRICULTURAL',
        AGRIC: 'AGRICULTURAL',
        MIXD: 'COMMERCIAL AND RESIDENTIAL',
        MIXE: 'COMMERCIAL AND RESIDENTIAL',
    };

    function deriveLandUseFromFileNumber(fileNumber) {
        if (!fileNumber) {
            return null;
        }

        const normalized = String(fileNumber).toUpperCase().trim();
        if (!normalized) {
            return null;
        }

        const segments = normalized.split(/[-\/\_\s]+/).filter(Boolean);
        const landUseKeys = Object.keys(LAND_USE_CODE_MAP);

        for (const segment of segments) {
            const lettersOnly = segment.replace(/[^A-Z]/g, '');
            if (!lettersOnly) {
                continue;
            }

            const candidates = new Set([lettersOnly]);

            if (lettersOnly.length > 3) {
                candidates.add(lettersOnly.slice(0, 3));
                candidates.add(lettersOnly.slice(-3));
            }

            if (lettersOnly.startsWith('ST') && lettersOnly.length > 2) {
                candidates.add(lettersOnly.slice(2));
            }

            for (const candidate of candidates) {
                if (candidate && LAND_USE_CODE_MAP[candidate]) {
                    return LAND_USE_CODE_MAP[candidate];
                }
            }

            for (const key of landUseKeys) {
                if (lettersOnly.includes(key)) {
                    return LAND_USE_CODE_MAP[key];
                }
            }
        }

        return null;
    }

    function getHistoryEntryTimestamp(entry) {
        if (!entry) {
            return Number.MAX_SAFE_INTEGER;
        }

        const candidateDates = [];
        if (isCofoHistoryEntry(entry)) {
            candidateDates.push(
                entry.cofo_date,
                entry.cofoDate,
                entry.deeds_date,
                entry.deedsDate
            );
        }
        candidateDates.push(
            entry.transaction_date,
            entry.transactionDate,
            entry.reg_date,
            entry.registration_date
        );

        const baseDate = candidateDates.find(value => {
            if (!value && value !== 0) {
                return false;
            }
            const trimmed = String(value).trim();
            return trimmed !== '' && trimmed.toLowerCase() !== 'null';
        });

        if (!baseDate) {
            return Number.MAX_SAFE_INTEGER;
        }

        const candidateTimes = [
            entry.reg_time,
            entry.regTime,
            entry.transaction_time,
            entry.transactionTime,
        ].filter(Boolean);
        const rawTime = candidateTimes.find(value => {
            const trimmed = String(value).trim();
            return trimmed !== '' && trimmed.toLowerCase() !== 'null';
        }) || '00:00:00';

        const dateTimeCandidates = [
            `${baseDate} ${rawTime}`,
            String(baseDate),
        ];

        for (const candidate of dateTimeCandidates) {
            const parsed = Date.parse(candidate);
            if (!Number.isNaN(parsed)) {
                return parsed;
            }
        }

        return Number.MAX_SAFE_INTEGER;
    }

    function sortFileHistoryEntries(entries) {
        if (!Array.isArray(entries)) {
            return [];
        }

        return entries
            .map((entry, index) => ({
                entry,
                index,
                timestamp: getHistoryEntryTimestamp(entry),
            }))
            .sort((a, b) => {
                if (a.timestamp !== b.timestamp) {
                    return a.timestamp - b.timestamp;
                }
                return a.index - b.index;
            })
            .map(item => item.entry);
    }

    function isCofoHistoryEntry(entry) {
        const rawType = entry?.transaction_type || entry?.transactionType || '';
        if (!rawType) {
            return false;
        }
        const normalized = rawType.toString().toUpperCase();
        return normalized.includes('COFO') || normalized.includes('CERTIFICATE OF OCCUPANCY');
    }

    function resolveCofoDateOverride() {
        const dateInput = document.getElementById('cofo-date');
        if (!dateInput) {
            return null;
        }

        const hasCofoToggle = document.getElementById('has-cofo-toggle');
        const hiddenHasCofoField = document.getElementById('has-cofo');
        const hasCofoEnabled = Boolean(
            (hasCofoToggle && hasCofoToggle.checked)
            || (hiddenHasCofoField && hiddenHasCofoField.checked)
        );

        if (!hasCofoEnabled) {
            return null;
        }

        const instrumentTypeSelect = document.getElementById('cofo-instrument-type');
        const instrumentValue = instrumentTypeSelect?.value || '';
        const normalizedType = instrumentValue.toString().toUpperCase();
        const isCofoInstrument =
            normalizedType.includes('COFO')
            || normalizedType.includes('CERTIFICATE OF OCCUPANCY')
            || normalizedType.includes('C OF O');

        if (!isCofoInstrument) {
            return null;
        }

        const dateValue = (dateInput.value || '').trim();
        return dateValue || null;
    }

    function getHistoryEntryPrimaryDate(entry, fallbackCofoDate = null) {
        if (!entry) {
            return null;
        }

        if (isCofoHistoryEntry(entry)) {
            const cofoCandidates = [
                fallbackCofoDate,
                entry.cofo_date,
                entry.cofoDate,
                entry.deeds_date,
                entry.deedsDate,
                entry.certificate_date,
                entry.certificateDate,
            ];

            for (const candidate of cofoCandidates) {
                if (!candidate && candidate !== 0) {
                    continue;
                }
                const trimmed = String(candidate).trim();
                if (trimmed && trimmed.toLowerCase() !== 'null') {
                    return trimmed;
                }
            }
        }

        const candidates = [
            entry.transaction_date,
            entry.reg_date,
            entry.transactionDate,
            entry.registration_date,
            entry.deeds_date,
            entry.deedsDate,
        ];

        for (const candidate of candidates) {
            if (!candidate && candidate !== 0) {
                continue;
            }
            const trimmed = String(candidate).trim();
            if (trimmed && trimmed.toLowerCase() !== 'null') {
                return trimmed;
            }
        }

        return null;
    }

    function deriveCurrentHolder(entry) {
        if (!entry || !entry.parties || typeof entry.parties !== 'object') {
            return null;
        }

        const normalize = (value) => {
            if (value === null || value === undefined) {
                return null;
            }
            const text = String(value).trim();
            return text === '' || text.toLowerCase() === 'null' ? null : text;
        };

        const partyEntries = Object.entries(entry.parties)
            .map(([role, value]) => ({
                role,
                value: normalize(value),
            }))
            .filter((item) => item.value !== null);

        if (!partyEntries.length) {
            return null;
        }

        const findByRegex = (regex) => partyEntries.find((item) => typeof item.role === 'string' && regex.test(item.role.trim()));

        const mortgagor = findByRegex(/mortgagor/i);
        if (mortgagor) {
            return {
                role: 'Mortgagor',
                name: mortgagor.value,
            };
        }

        const priorityRegexes = [
            /grantee/i,
            /assignee/i,
            /transferee/i,
            /purchaser/i,
            /donee/i,
            /lessee/i,
            /surrenderee/i,
            /beneficiary/i,
            /releasee/i,
            /owner/i,
        ];

        for (const regex of priorityRegexes) {
            const match = findByRegex(regex);
            if (match) {
                return {
                    role: typeof match.role === 'string' && match.role.trim() ? match.role : match.role?.toString() || 'Party',
                    name: match.value,
                };
            }
        }

        const fallback = partyEntries.find((item) => !/mortgagee/i.test(String(item.role || '')));
        if (fallback) {
            return {
                role: typeof fallback.role === 'string' && fallback.role.trim() ? fallback.role : 'Party',
                name: fallback.value,
            };
        }

        const mortgagee = findByRegex(/mortgagee/i);
        if (mortgagee) {
            return {
                role: typeof mortgagee.role === 'string' && mortgagee.role.trim() ? mortgagee.role : 'Mortgagee',
                name: mortgagee.value,
            };
        }

        return null;
    }

    function updateFileHistoryTimelineButtonState() {
        const elements = resolveFileHistoryElements();
        ensureFileHistoryTimelineHandlers();
        if (!elements.timelineButton) {
            return;
        }

        const hasEntries = Array.isArray(fileHistoryUI.entries) && fileHistoryUI.entries.length > 0;
        elements.timelineButton.disabled = !hasEntries;
        if (hasEntries) {
            elements.timelineButton.classList.remove('opacity-60', 'cursor-not-allowed');
        } else {
            elements.timelineButton.classList.add('opacity-60', 'cursor-not-allowed');
        }
    }

    function formatTimelineDate(value) {
        if (!value) {
            return null;
        }

        const parsed = Date.parse(value);
        if (!Number.isNaN(parsed)) {
            try {
                const formatter = new Intl.DateTimeFormat('en-GB', {
                    year: 'numeric',
                    month: 'long',
                    day: '2-digit',
                });
                return formatter.format(new Date(parsed));
            } catch (error) {
                return String(value);
            }
        }

        return String(value);
    }

    function describeTransactionAction(transactionType) {
        const raw = String(transactionType || '').trim();
        if (!raw) {
            return 'was recorded';
        }

        const upper = raw.toUpperCase();

        const keywordPhrases = [
            { keyword: 'MORTGAGE', phrase: 'was mortgaged' },
            { keyword: 'ASSIGN', phrase: 'was assigned' },
            { keyword: 'TRANSFER', phrase: 'was transferred' },
            { keyword: 'LEASE', phrase: 'was leased' },
            { keyword: 'SURRENDER', phrase: 'was surrendered' },
            { keyword: 'GRANT', phrase: 'was granted' },
            { keyword: 'SALE', phrase: 'was sold' },
            { keyword: 'PURCHASE', phrase: 'was purchased' },
            { keyword: 'CERTIFICATE OF OCCUPANCY', phrase: 'was issued a Certificate of Occupancy' },
            { keyword: 'C OF O', phrase: 'was issued a Certificate of Occupancy' },
        ];

        for (const { keyword, phrase } of keywordPhrases) {
            if (upper.includes(keyword)) {
                return phrase;
            }
        }

        return `was recorded under ${raw}`;
    }

    function deriveTimelineRecipient(entry) {
        if (!entry || !entry.parties || typeof entry.parties !== 'object') {
            return null;
        }

        const normalize = (value) => {
            if (value === null || value === undefined) {
                return null;
            }
            const text = String(value).trim();
            return text === '' || text.toLowerCase() === 'null' ? null : text;
        };

        const parties = Object.entries(entry.parties)
            .map(([role, value]) => ({ role, value: normalize(value) }))
            .filter(item => item.value !== null);

        if (!parties.length) {
            return null;
        }

        const findByRegex = (regexList) => {
            for (const regex of regexList) {
                const match = parties.find(item => typeof item.role === 'string' && regex.test(item.role));
                if (match) {
                    return match;
                }
            }
            return null;
        };

        const mortgagor = findByRegex([/mortgagor/i]);
        if (mortgagor) {
            return {
                role: 'Mortgagor',
                name: mortgagor.value,
            };
        }

        const prioritizedRoles = [
            'Grantee',
            'Assignee',
            'Transferee',
            'Purchaser',
            'Donee',
            'Mortgagee',
            'Lessee',
            'Surrenderee',
            'Beneficiary',
            'Releasee',
            'Owner',
            'Applicant',
        ];

        const prioritizedMatch = findByRegex(prioritizedRoles.map(role => new RegExp(role, 'i')));
        if (prioritizedMatch) {
            return {
                role: typeof prioritizedMatch.role === 'string' && prioritizedMatch.role.trim()
                    ? prioritizedMatch.role
                    : 'Party',
                name: prioritizedMatch.value,
            };
        }

        const firstParty = parties[0];

        if (firstParty) {
            return {
                role: typeof firstParty.role === 'string' && firstParty.role.trim() ? firstParty.role : 'Party',
                name: firstParty.value,
            };
        }

        return null;
    }

    function ensureFileHistoryTimelineHandlers() {
        const elements = fileHistoryUI.timelineModal ? fileHistoryUI : resolveFileHistoryElements();
        if (!elements.container) {
            return;
        }

        const { timelineButton, timelineClose, timelineDismiss, timelineModal } = elements;

        if (timelineButton && timelineButton.dataset.timelineBound !== 'true') {
            timelineButton.addEventListener('click', (event) => {
                if (timelineButton.disabled) {
                    return;
                }
                event.preventDefault();
                openFileHistoryTimelineModal();
            });
            timelineButton.dataset.timelineBound = 'true';
        }

        if (timelineClose && timelineClose.dataset.timelineBound !== 'true') {
            timelineClose.addEventListener('click', (event) => {
                event.preventDefault();
                closeFileHistoryTimelineModal();
            });
            timelineClose.dataset.timelineBound = 'true';
        }

        if (timelineDismiss && timelineDismiss.dataset.timelineBound !== 'true') {
            timelineDismiss.addEventListener('click', (event) => {
                event.preventDefault();
                closeFileHistoryTimelineModal();
            });
            timelineDismiss.dataset.timelineBound = 'true';
        }

        if (timelineModal && timelineModal.dataset.timelineOverlayBound !== 'true') {
            timelineModal.addEventListener('click', (event) => {
                if (event.target === timelineModal) {
                    closeFileHistoryTimelineModal();
                }
            });
            timelineModal.dataset.timelineOverlayBound = 'true';
        }
    }

    function applyLandUseFromFileNumber(fileNumber) {
        const derivedLandUse = deriveLandUseFromFileNumber(fileNumber);
        if (!derivedLandUse) {
            return false;
        }

        populateLandUseFromRecord(derivedLandUse);

        const landUseSelect = document.getElementById('land-use-type');
        if (landUseSelect) {
            landUseSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return true;
    }

    function resolveFileHistoryElements() {
        if (fileHistoryUI.container && document.body.contains(fileHistoryUI.container)) {
            return fileHistoryUI;
        }

        fileHistoryUI.container = document.getElementById('file-history-section');
        fileHistoryUI.list = document.getElementById('file-history-list');
        fileHistoryUI.empty = document.getElementById('file-history-empty');
        fileHistoryUI.error = document.getElementById('file-history-error');
        fileHistoryUI.loading = document.getElementById('file-history-loading');
        fileHistoryUI.timelineButton = document.getElementById('file-history-timeline-btn');
        fileHistoryUI.timelineModal = document.getElementById('file-history-timeline-modal');
        fileHistoryUI.timelineContent = document.getElementById('file-history-timeline-content');
        fileHistoryUI.timelineClose = document.getElementById('file-history-timeline-close');
        fileHistoryUI.timelineDismiss = document.getElementById('file-history-timeline-dismiss');

        return fileHistoryUI;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ---------------------------------------------------------------------
     * "Where did this record go?" card
     *
     * Saving one indexing form writes across a dozen tables — registry, parties,
     * CofO/PRA transactions, commissioning mirrors, bills, prop_id lineage — and
     * none of that was visible from the form, so operators could not tell what a
     * submission had actually done. The server returns `storage_summary` counting
     * the rows that now carry this file, and this renders it the same way the
     * "Move to Indexing Duplicates" confirmation renders its impact tables.
     * ------------------------------------------------------------------- */

    const SUMMARY_GROUP_PALETTE = {
        primary: { head: 'text-emerald-700', count: 'text-emerald-700', border: 'border-emerald-100' },
        parties: { head: 'text-sky-700', count: 'text-sky-700', border: 'border-sky-100' },
        records: { head: 'text-indigo-700', count: 'text-indigo-700', border: 'border-indigo-100' },
        onward: { head: 'text-amber-700', count: 'text-amber-700', border: 'border-amber-100' },
    };

    function renderStorageSummaryGroup(group) {
        if (!group || !Array.isArray(group.rows) || group.rows.length === 0) {
            return '';
        }

        const palette = SUMMARY_GROUP_PALETTE[group.tone] || SUMMARY_GROUP_PALETTE.records;

        const body = group.rows.map(row => `
            <tr class="border-t ${palette.border}">
                <td class="py-1 pr-3 text-left font-mono text-[11px] text-slate-500 align-top">${escapeHtml(row.table)}</td>
                <td class="py-1 pr-3 text-left align-top">
                    ${escapeHtml(row.label)}
                    ${row.detail ? `<span class="block text-[11px] text-slate-400 break-all">${escapeHtml(row.detail)}</span>` : ''}
                </td>
                <td class="py-1 text-right font-semibold align-top ${palette.count}">${Number(row.count).toLocaleString()}</td>
            </tr>
        `).join('');

        return `
            <div class="mt-3">
                <p class="text-[11px] font-bold uppercase tracking-wider ${palette.head} text-left">${escapeHtml(group.title)}</p>
                <table class="w-full text-xs mt-1"><tbody>${body}</tbody></table>
            </div>
        `;
    }

    /**
     * The instruments captured in this submission, one row each, with the table the
     * instrument landed in. Server-side these are read back from the rows that were
     * actually written, so anything skipped as a duplicate never appears here.
     */
    function renderCapturedInstruments(instruments) {
        if (!Array.isArray(instruments) || instruments.length === 0) {
            return '';
        }

        const body = instruments.map(item => {
            const parties = [item.grantor, item.grantee].filter(Boolean).join(' → ');
            const meta = [item.reg_no, item.date].filter(Boolean).join(' · ');

            return `
                <tr class="border-t border-violet-100">
                    <td class="py-1.5 pr-3 text-left align-top">
                        <span class="font-semibold text-slate-800">${escapeHtml(item.instrument)}</span>
                        ${parties ? `<span class="block text-[11px] text-slate-500 break-all">${escapeHtml(parties)}</span>` : ''}
                        ${meta ? `<span class="block text-[11px] text-slate-400">${escapeHtml(meta)}</span>` : ''}
                    </td>
                    <td class="py-1.5 text-right align-top text-[11px] text-violet-700 whitespace-nowrap">
                        ${escapeHtml(item.destination)}
                        <span class="block font-mono text-[10px] text-slate-400">${escapeHtml(item.table)}</span>
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <div class="mt-3">
                <p class="text-[11px] font-bold uppercase tracking-wider text-violet-700 text-left">
                    Instruments captured (${instruments.length})
                </p>
                <table class="w-full text-xs mt-1"><tbody>${body}</tbody></table>
            </div>
        `;
    }

    function renderStorageSummaryHtml(summary, message, instruments, options = {}) {
        // After capturing transactions the operator is confirming the instruments
        // themselves, not the file's whole footprint — so that card stops at the
        // instruments table and drops the destination groups below it.
        // extraHtml: caller-supplied block rendered under the instruments table. The
        // property transaction modal passes its File History Summary through here so the
        // confirmation dialog shows the same rows, destinations and NEW/UPDATED badges the
        // inline card shows, instead of a second, differently-shaped account of the save.
        const { instrumentsOnly = false, extraHtml = '' } = options;
        const groups = Array.isArray(summary.groups) ? summary.groups : [];
        const notes = Array.isArray(summary.notes) ? summary.notes : [];

        const identity = [
            ['File number', summary.file_number],
            ['File title', summary.file_title],
            ['Tracking ID', summary.tracking_id],
            ['Registry', summary.registry],
            ['Property ID', summary.prop_id],
            ['Parent property', summary.parent_prop_id],
        ].filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '');

        const identityHtml = identity.map(([label, value]) => `
            <div class="flex items-baseline gap-2">
                <span class="w-28 shrink-0 text-[11px] uppercase tracking-wider text-slate-500">${escapeHtml(label)}</span>
                <span class="flex-1 min-w-0 font-semibold text-slate-800 break-all">${escapeHtml(value)}</span>
            </div>
        `).join('');

        const notesHtml = notes.map(note => {
            const tone = note.tone === 'muted'
                ? 'text-slate-500 bg-slate-50 border-slate-200'
                : 'text-blue-700 bg-blue-50 border-blue-200';
            return `<p class="text-xs ${tone} border rounded p-2 mt-2 text-left">${escapeHtml(note.text)}</p>`;
        }).join('');

        return `
            <p class="text-sm text-slate-600 text-left" style="white-space: pre-line;">${escapeHtml(message)}</p>
            <div class="mt-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm space-y-1">
                ${identityHtml}
            </div>
            ${extraHtml
                // The File History Summary supersedes the instruments table: it lists the same
                // saved rows PLUS what was already on the file, with each row's destination and
                // whether it was new or existing. Showing both meant reading one save twice.
                ? extraHtml
                : renderCapturedInstruments(instruments)}
            ${instrumentsOnly ? '' : `
                ${groups.map(renderStorageSummaryGroup).join('')}
                ${notesHtml}
                <p class="text-[11px] text-slate-400 mt-3 text-left">
                    Counts are the rows that now carry this file — including any that already existed.
                </p>
            `}
        `;
    }

    function storageSummaryToText(summary) {
        const lines = [];
        (summary.groups || []).forEach(group => {
            lines.push(group.title + ':');
            (group.rows || []).forEach(row => {
                lines.push(`  - ${row.table}: ${row.count} (${row.label})`);
            });
        });
        (summary.notes || []).forEach(note => lines.push(note.text));
        return lines.join('\n');
    }

    /**
     * Confirmation card shown after a successful save. Falls back to the old
     * auto-dismissing toast when the server could not build a summary (it is
     * best-effort server side, so its absence must not swallow the success
     * message). Resolves once the operator dismisses it.
     */
    function showIndexingSavedCard(data, options = {}) {
        const { isUpdate = false, extraText = '', title = null, instrumentsOnly = false, extraHtml = '' } = options;
        const summary = data && data.storage_summary;
        // Present on the transaction-capture response; absent after plain indexing.
        const instruments = (data && data.instruments) || [];
        const baseMessage = (data && data.message)
            || (isUpdate ? 'File indexing updated successfully!' : 'File indexing created successfully!');
        const message = extraText ? `${baseMessage}${extraText}` : baseMessage;

        if (typeof Swal === 'undefined') {
            alert(summary ? `${message}\n\n${storageSummaryToText(summary)}` : message);
            return Promise.resolve();
        }

        const heading = title
            || (isUpdate ? 'Updated — here is where it lives' : 'Indexed — here is where it went');

        // Nothing to tabulate: keep the old lightweight toast rather than an empty card.
        // extraHtml counts as content - a save with no readable instruments still has a
        // File History Summary worth showing.
        if (!summary && instruments.length === 0 && !extraHtml) {
            // No timer: this card reports where a save landed, so it stays until the
            // operator dismisses it. A card that closes itself can take the only record of
            // what happened off screen before it has been read.
            return Swal.fire({
                title: isUpdate ? 'File index updated' : 'Success!',
                text: message,
                icon: 'success',
                confirmButtonText: 'Close',
                allowOutsideClick: false,
                allowEscapeKey: false,
            });
        }

        return Swal.fire({
            title: heading,
            html: renderStorageSummaryHtml(
                summary || { groups: [], notes: [] },
                message,
                instruments,
                { instrumentsOnly, extraHtml }
            ),
            icon: 'success',
            width: 620,
            confirmButtonText: 'Continue',
            confirmButtonColor: '#059669',
            allowOutsideClick: false,
            allowEscapeKey: false,
        });
    }

    // Shared with the property transaction modal (property_transaction_modal.blade.php),
    // which lives outside this module but shows the same card after capturing
    // instruments — so both confirmations read identically.
    window.showIndexingSavedCard = showIndexingSavedCard;

    /**
     * Submission Summary then File Snapshot, from file-snapshot-card.js.
     *
     * Guarded rather than called directly: that file is a separate <script> and
     * not every page that loads this module has been given it. A missing card is
     * an acceptable outcome; a TypeError that swallows the transaction prompt
     * after a successful save is not.
     */
    function postSubmitCards(data) {
        if (typeof window.showPostSubmitCards !== 'function') {
            return Promise.resolve();
        }

        try {
            return window.showPostSubmitCards(data) || Promise.resolve();
        } catch (error) {
            console.warn('Post-submit cards could not be shown', error);
            return Promise.resolve();
        }
    }

    function escapeAttribute(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function safeText(value, fallback = '-') {
        if (value === null || value === undefined) {
            return escapeHtml(fallback);
        }

        const stringValue = String(value).trim();
        if (!stringValue) {
            return escapeHtml(fallback);
        }

        return escapeHtml(stringValue);
    }

    function normalizeFieldId(idPrefix, label) {
        const base = `${idPrefix}-${label}`
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');

        return base || `${idPrefix}-field`;
    }

    function createInputField(label, value, { idPrefix, colSpan = 1, multiline = false } = {}) {
        const computedId = normalizeFieldId(idPrefix || 'fh', label);
        const colClass = colSpan >= 2 ? `md:col-span-${Math.min(colSpan, 4)}` : '';
        const hasValue = value !== null && value !== undefined && String(value).trim() !== '';
        const resolvedValue = hasValue ? String(value).trim() : '';
        const placeholder = hasValue ? '' : '-';

        const commonClasses = 'block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-100 text-sm text-gray-700 shadow-sm cursor-not-allowed';
        const readonlyAttrs = 'readonly tabindex="-1" aria-readonly="true"';

        if (multiline) {
            return `
                <div class="form-group ${colClass}">
                    <label for="${computedId}" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">${escapeHtml(label)}</label>
                    <textarea id="${computedId}" rows="2" class="${commonClasses} resize-y" placeholder="${escapeAttribute(placeholder)}" ${readonlyAttrs}>${escapeHtml(resolvedValue)}</textarea>
                </div>
            `;
        }

        return `
            <div class="form-group ${colClass}">
                <label for="${computedId}" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">${escapeHtml(label)}</label>
                <input type="text" id="${computedId}" class="${commonClasses}" value="${escapeAttribute(resolvedValue)}" placeholder="${escapeAttribute(placeholder)}" ${readonlyAttrs}>
            </div>
        `;
    }

    function createPartiesMarkup(parties, idPrefix) {
        if (!parties || parties.length === 0) {
            return '<p class="text-sm text-gray-500">No party information available for this record.</p>';
        }

        return `
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                ${parties.map(([role, name], partyIndex) => createInputField(role, name, {
            idPrefix: `${idPrefix}-party-${partyIndex}`,
        })).join('')}
            </div>
        `;
    }

    function buildFileHistoryTimelineMarkup(entries) {
        if (!Array.isArray(entries) || entries.length === 0) {
            return '<p class="text-sm text-gray-500">No file history records are available for this property.</p>';
        }

        const totalCount = entries.length;
        const earliestEntry = entries[0];
        const latestEntry = entries[entries.length - 1];
        const cofoDateOverride = resolveCofoDateOverride();
        const firstDate = formatTimelineDate(getHistoryEntryPrimaryDate(earliestEntry, cofoDateOverride)) || 'Unknown date';
        const lastDate = formatTimelineDate(getHistoryEntryPrimaryDate(latestEntry, cofoDateOverride)) || 'Unknown date';

        const summaryLine = `This property has ${totalCount} recorded transaction${totalCount === 1 ? '' : 's'} spanning ${firstDate} to ${lastDate}.`;

        const displayEntries = [...entries].reverse();

        const timelineItems = displayEntries.map((entry, index) => {
            const isCurrent = entry === latestEntry;
            const transactionType = entry?.transaction_type || entry?.transactionType || 'Transaction';
            const dateValue = formatTimelineDate(getHistoryEntryPrimaryDate(entry, cofoDateOverride)) || 'Unknown date';
            const actionPhrase = describeTransactionAction(transactionType);
            const recipient = deriveTimelineRecipient(entry);
            const location = entry?.location ? String(entry.location).trim() : '';
            const landUse = entry?.land_use || deriveLandUseFromFileNumber(entry?.mlsFNo || entry?.fileNumber || entry?.prop_id) || '';

            const safeDate = escapeHtml(dateValue);
            const safeAction = escapeHtml(actionPhrase);

            let sentence = `On ${safeDate}, this property ${safeAction}`;
            if (recipient?.name) {
                sentence += ` to ${escapeHtml(recipient.name)}`;
            }
            sentence += '.';
            if (isCurrent) {
                sentence += ' This transaction established the current holder.';
            }

            const metaParts = [];
            if (transactionType) {
                metaParts.push(`<span class="font-semibold text-blue-900">Transaction Type :</span> <span class="font-semibold text-blue-900">${escapeHtml(transactionType)}</span>`);
            }
            if (recipient?.name) {
                metaParts.push(`<span class="font-semibold text-blue-900">Recipient :</span> <span class="font-semibold text-blue-900">${escapeHtml(recipient.name)}</span>`);
            }
            if (landUse) {
                metaParts.push(`<span class="font-semibold text-blue-900">Land Use:</span> <span class="font-semibold text-blue-900">${escapeHtml(landUse)}</span>`);
            }
            if (location) {
                metaParts.push(`<span class="font-semibold text-blue-900">Location:</span> <span class="font-semibold text-blue-900">${escapeHtml(location)}</span>`);
            }
            if (entry?.serialNo) {
                metaParts.push(`<span class="font-semibold text-blue-900">Serial No:</span> <span class="font-semibold text-blue-900">${escapeHtml(String(entry.serialNo))}</span>`);
            }
            if (entry?.pageNo) {
                metaParts.push(`<span class="font-semibold text-blue-900">Page No:</span> <span class="font-semibold text-blue-900">${escapeHtml(String(entry.pageNo))}</span>`);
            }
            if (entry?.volumeNo) {
                metaParts.push(`<span class="font-semibold text-blue-900">Volume No:</span> <span class="font-semibold text-blue-900">${escapeHtml(String(entry.volumeNo))}</span>`);
            }

            const metaMarkup = metaParts.length
                ? `<p class="text-xs text-blue-900 font-semibold flex flex-wrap gap-1 items-center">${metaParts.join('<span class="text-blue-400 px-1">•</span>')}</p>`
                : '';

            const iconOuterClasses = isCurrent
                ? 'flex h-6 w-6 items-center justify-center rounded-full border-2 border-emerald-600 bg-emerald-600 shadow-sm'
                : 'flex h-6 w-6 items-center justify-center rounded-full border-2 border-blue-200 bg-white';
            const iconInnerClasses = isCurrent
                ? 'h-2.5 w-2.5 rounded-full bg-white'
                : 'h-2.5 w-2.5 rounded-full bg-blue-500';

            const lineMarkup = index === displayEntries.length - 1
                ? ''
                : '<span class="absolute" aria-hidden="true" style="left: -0.75rem; top: 1.65rem; bottom: -2.5rem; width: 2px; background-color: #bfdbfe;"></span>';

            return `
            <li class="relative ml-6 mb-8 pl-4 pb-8 last:mb-0 last:pb-0">
                ${lineMarkup}
                <span class="absolute -left-3 top-0 ${iconOuterClasses}" aria-hidden="true">
                    <span class="${iconInnerClasses}"></span>
                </span>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h4 class="text-sm font-semibold text-gray-800">${escapeHtml(transactionType)}</h4>
                        </div>
                        <p class="text-sm text-gray-700 leading-relaxed">${sentence}</p>
                        ${metaMarkup}
                    </div>
                </li>
            `;
        }).join('');

        return `
            <div class="space-y-4">
                <p class="text-sm text-gray-600">${escapeHtml(summaryLine)}</p>
                <ol class="relative pl-2">
                    ${timelineItems}
                </ol>
            </div>
        `;
    }

    function openFileHistoryTimelineModal() {
        const elements = resolveFileHistoryElements();
        if (!elements.timelineModal || !elements.timelineContent) {
            return;
        }

        if (!Array.isArray(fileHistoryUI.entries) || fileHistoryUI.entries.length === 0) {
            elements.timelineContent.innerHTML = '<p class="text-sm text-gray-500">Load file history before opening the timeline.</p>';
        } else {
            elements.timelineContent.innerHTML = buildFileHistoryTimelineMarkup(fileHistoryUI.entries);
        }

        if (elements.timelineModal.parentElement && elements.timelineModal.parentElement !== document.body) {
            document.body.appendChild(elements.timelineModal);
        }

        elements.timelineModal.classList.remove('hidden');
        elements.timelineModal.classList.add('flex');
        elements.timelineModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function closeFileHistoryTimelineModal() {
        const elements = resolveFileHistoryElements();
        if (!elements.timelineModal) {
            return;
        }

        elements.timelineModal.classList.add('hidden');
        elements.timelineModal.classList.remove('flex');
        elements.timelineModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    function createFileHistoryEntryMarkup(entry, index, options = {}) {
        const {
            isCurrent = false,
            currentHolder = null,
            totalCount = null,
            cofoDateOverride = null,
        } = options;
        const transactionLabel = safeText(entry?.transaction_type, 'Unknown transaction');
        const summaryTitle = entry?.transaction_type ? transactionLabel : escapeHtml(`History Record ${index + 1}`);
        const effectiveLandUse = deriveLandUseFromFileNumber(entry?.mlsFNo || entry?.fileNumber || entry?.prop_id)
            || entry?.land_use
            || null;

        const parties = [];
        if (entry?.parties && typeof entry.parties === 'object') {
            Object.entries(entry.parties).forEach(([role, name]) => {
                if (name === null || name === undefined) {
                    return;
                }
                const text = String(name).trim();
                if (text) {
                    parties.push([role, text]);
                }
            });
        }

        const partiesMarkup = createPartiesMarkup(parties, `fh-${index}`);

        const detailClasses = isCurrent
            ? 'group border border-emerald-300 rounded-lg bg-emerald-50 shadow-sm'
            : 'group border border-gray-200 rounded-lg bg-gray-50';

        const innerBorderClass = isCurrent ? 'border-emerald-200' : 'border-gray-200';

        const historyPositionLabel = typeof totalCount === 'number'
            ? `History Set ${index + 1} of ${totalCount}`
            : `History Set ${index + 1}`;

        const currentHolderBadge = isCurrent && currentHolder?.name
            ? `
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-600 text-white text-xs font-semibold px-3 py-1 shadow-sm">
                        <span>Current Holder</span>
                        <span class="flex items-center gap-1">
                            <span class="text-white">${escapeHtml(currentHolder.name)}</span>
                            ${currentHolder.role ? `<span class="px-1.5 py-0.5 rounded bg-emerald-500 text-[10px] uppercase tracking-wide">${escapeHtml(currentHolder.role)}</span>` : ''}
                        </span>
                    </span>
                `
            : '';

        const transactionDateValue = getHistoryEntryPrimaryDate(entry, cofoDateOverride) || entry?.transaction_date || entry?.reg_date || null;
        const transactionDateLabel = isCofoHistoryEntry(entry) ? 'COFO Date' : 'Transaction Date';

        return `
            <details class="${detailClasses}" ${isCurrent ? 'open' : ''} ${isCurrent ? 'data-current="true"' : ''}>
                <summary class="flex items-center justify-between gap-4 px-4 py-3 cursor-pointer list-none select-none">
                    <div class="flex items-start md:items-center gap-3 md:flex-row flex-col">
                        <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-blue-600 text-white text-xs">${index + 1}</span>
                        <div class="text-left space-y-1">
                            <h4 class="text-sm font-semibold text-gray-800">${summaryTitle}</h4>
                            <p class="text-xs text-gray-500">${transactionLabel}</p>
                            ${currentHolderBadge ? `<div>${currentHolderBadge}</div>` : ''}
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-gray-500">
                        <span>${historyPositionLabel}</span>
                        <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400 transition duration-200 group-open:rotate-180"></i>
                    </div>
                </summary>
                    <div class="px-4 pb-4 border-t ${innerBorderClass} pt-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            ${createInputField('Prop ID', entry?.prop_id, { idPrefix: `fh-${index}-prop`, colSpan: 1 })}
                            ${createInputField('MLPP File No', entry?.mlsFNo, { idPrefix: `fh-${index}-mls`, colSpan: 1 })}
                            ${createInputField(transactionDateLabel, transactionDateValue, { idPrefix: `fh-${index}-txn-date`, colSpan: 1 })}
                        ${createInputField('Registration Date', entry?.reg_date, { idPrefix: `fh-${index}-reg-date`, colSpan: 1 })}
                        ${createInputField('Registration Time', entry?.reg_time, { idPrefix: `fh-${index}-reg-time`, colSpan: 1 })}
                        ${createInputField('Land Use', effectiveLandUse, { idPrefix: `fh-${index}-landuse`, colSpan: 1 })}
                        ${createInputField('Location', entry?.location, { idPrefix: `fh-${index}-location`, colSpan: 2, multiline: true })}
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        ${createInputField('Serial No', entry?.serialNo, { idPrefix: `fh-${index}-serial`, colSpan: 1 })}
                        ${createInputField('Page No', entry?.pageNo, { idPrefix: `fh-${index}-page`, colSpan: 1 })}
                        ${createInputField('Volume No', entry?.volumeNo, { idPrefix: `fh-${index}-volume`, colSpan: 1 })}
                    </div>
                    <div class="space-y-2">
                        <h5 class="text-sm font-semibold text-gray-800">Parties</h5>
                        ${partiesMarkup}
                    </div>
                </div>
            </details>
        `;
    }

    /**
     * Paint the read-only Root of Title / Original Holder / Current Holder block
     * from the resolver's answer on the /api/file-history response (client spec
     * 2026-08-20 §12). A blank Root of Title is a legitimate result — the file has
     * nothing on record predating its Ministry grant — so it shows a dash rather
     * than borrowing the earliest party's name.
     */
    function renderTitleHolders(holders) {
        const panel = document.getElementById('file-history-title-holders');
        if (!panel) {
            return;
        }

        if (!holders) {
            panel.classList.add('hidden');
            return;
        }

        // WHICH lines print is decided by the spec table server-side and arrives
        // as `lines` — a Direct Allocation shows only two (row iii). Build the
        // rows from that list rather than assuming three fixed elements.
        const list = document.getElementById('file-history-title-lines');
        if (list) {
            const lines = Array.isArray(holders.lines) ? holders.lines : [];
            list.innerHTML = '';
            // The three lines must read as three different things, so each takes
            // its colour from the resolver's `tone`.
            const TONES = {
                amber: ['text-amber-700', 'text-amber-800'],
                emerald: ['text-emerald-700', 'text-emerald-800'],
                indigo: ['text-indigo-700', 'text-indigo-800'],
                gray: ['text-gray-500', 'text-gray-800'],
            };

            lines.forEach((line) => {
                const tone = TONES[line.tone] || TONES.gray;
                const wrap = document.createElement('div');
                wrap.className = 'flex justify-between gap-4';

                const dt = document.createElement('dt');
                dt.className = `text-xs font-semibold ${tone[0]}`;
                dt.textContent = line.label || '';

                const dd = document.createElement('dd');
                dd.className = `text-sm font-medium text-right ${line.value ? tone[1] : 'text-gray-400'}`;
                dd.textContent = line.value || '—';

                wrap.appendChild(dt);
                wrap.appendChild(dd);
                list.appendChild(wrap);
            });
        }

        const typeEl = document.getElementById('file-history-application-type');
        if (typeEl) {
            const type = holders.application_type;
            typeEl.textContent = (type && type !== 'Unknown') ? type : '';
        }

        panel.classList.remove('hidden');
    }

    function renderFileHistoryEntries(entries) {
        const elements = resolveFileHistoryElements();
        if (!elements.container) {
            return;
        }

        const sortedEntries = sortFileHistoryEntries(entries);
        const displayEntries = [...sortedEntries].reverse();
        const latestEntry = sortedEntries.length ? sortedEntries[sortedEntries.length - 1] : null;
        const latestHolder = latestEntry ? deriveCurrentHolder(latestEntry) : null;

        fileHistoryUI.entries = sortedEntries;
        updateFileHistoryTimelineButtonState();

        const propIdField = document.getElementById('prop-id-field');
        if (propIdField) {
            const firstPropEntry = sortedEntries.find((entry) => entry && entry.prop_id !== null && entry.prop_id !== undefined && String(entry.prop_id).trim() !== '');
            propIdField.value = firstPropEntry ? String(firstPropEntry.prop_id).trim() : '';
        }

        if (elements.loading) {
            elements.loading.classList.add('hidden');
        }

        if (elements.error) {
            elements.error.classList.add('hidden');
            elements.error.textContent = '';
        }

        if (!sortedEntries.length) {
            if (elements.list) {
                elements.list.innerHTML = '';
                elements.list.setAttribute('aria-busy', 'false');
            }
            if (elements.empty) {
                elements.empty.textContent = 'No file history found for this file number.';
                elements.empty.classList.remove('hidden');
            }
            if (propIdField) {
                propIdField.value = '';
            }
            return;
        }

        if (elements.empty) {
            elements.empty.classList.add('hidden');
        }

        const cofoDateOverride = resolveCofoDateOverride();

        if (elements.list) {
            elements.list.innerHTML = displayEntries.map((entry, index) => createFileHistoryEntryMarkup(entry, index, {
                isCurrent: latestEntry ? entry === latestEntry : false,
                currentHolder: latestEntry && entry === latestEntry ? latestHolder : null,
                totalCount: displayEntries.length,
                cofoDateOverride,
            })).join('');
            elements.list.setAttribute('aria-busy', 'false');
        }

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function showFileHistoryError(message) {
        const elements = resolveFileHistoryElements();
        if (!elements.container) {
            return;
        }

        fileHistoryUI.entries = [];
        renderTitleHolders(null);
        updateFileHistoryTimelineButtonState();

        if (elements.list) {
            elements.list.innerHTML = '';
            elements.list.setAttribute('aria-busy', 'false');
        }

        if (elements.loading) {
            elements.loading.classList.add('hidden');
        }

        if (elements.empty) {
            elements.empty.classList.add('hidden');
        }

        if (elements.error) {
            elements.error.textContent = message;
            elements.error.classList.remove('hidden');
        }

        const propIdField = document.getElementById('prop-id-field');
        if (propIdField) {
            propIdField.value = '';
        }
    }

    async function loadFileHistoryForFile(fileNumber) {
        const elements = resolveFileHistoryElements();
        if (!elements.container) {
            return;
        }

        // A new KANGIS physical variant is saved under its own _N number and owns no
        // dealings yet, so the base file's transactions are not its history — loading
        // them here is what made the form look like it had adopted the existing record.
        // Editing an already-saved KANGIS record still loads that record's own history.
        const isKangisVariantCreate = isKangisVariantMode()
            && !editModeState.isEditing
            && !window.isEditMode;

        const trimmed = isKangisVariantCreate ? '' : (fileNumber || '').trim();
        const requestId = ++fileHistoryUI.lastRequestId;

        if (!trimmed) {
            fileHistoryUI.entries = [];
            if (fileHistoryUI.currentFileNumber) {
                propertyTransactionCache.byFileNumber.delete(normalizeFileno(fileHistoryUI.currentFileNumber));
            }
            fileHistoryUI.currentFileNumber = null;
            renderTitleHolders(null);
            updateFileHistoryTimelineButtonState();
            if (elements.list) {
                elements.list.innerHTML = '';
                elements.list.setAttribute('aria-busy', 'false');
            }
            if (elements.loading) {
                elements.loading.classList.add('hidden');
            }
            if (elements.error) {
                elements.error.classList.add('hidden');
                elements.error.textContent = '';
            }
            if (elements.empty) {
                elements.empty.textContent = isKangisVariantCreate
                    ? 'New KANGIS physical file — it starts with no transactions of its own, so the existing file\'s history is not loaded.'
                    : 'Select a file number to load historical transactions.';
                elements.empty.classList.remove('hidden');
            }
            const propIdField = document.getElementById('prop-id-field');
            if (propIdField) {
                propIdField.value = '';
            }
            return;
        }

        if (elements.error) {
            elements.error.classList.add('hidden');
            elements.error.textContent = '';
        }

        if (elements.empty) {
            elements.empty.classList.add('hidden');
        }

        if (elements.list) {
            elements.list.innerHTML = '';
            elements.list.setAttribute('aria-busy', 'true');
        }

        if (elements.loading) {
            elements.loading.classList.remove('hidden');
        }

        fileHistoryUI.currentFileNumber = trimmed;

        try {
            const response = await fetchJson(`/api/file-history?mlsFNo=${encodeURIComponent(trimmed)}`);

            if (requestId !== fileHistoryUI.lastRequestId) {
                return;
            }

            if (!response || response.success !== true) {
                showFileHistoryError('Unable to retrieve file history at this time.');
                return;
            }

            const entries = Array.isArray(response.data) ? response.data : [];
            renderTitleHolders(response.title_holders);
            renderFileHistoryEntries(entries);

            const converted = convertFileHistoryEntriesToTransactions(entries);
            if (converted.length) {
                cacheTransactionsForFile(trimmed, converted);
            }
        } catch (error) {
            if (requestId !== fileHistoryUI.lastRequestId) {
                return;
            }

            console.error('File history request failed', error);
            showFileHistoryError('Unable to load file history right now.');
        }
    }

    window.loadFileHistoryForFile = loadFileHistoryForFile;

    const DEFAULT_INDEXER_PLACEHOLDER = 'Current User';

    function getDefaultIndexerName() {
        if (typeof getDefaultIndexerName.cached === 'string') {
            return getDefaultIndexerName.cached;
        }

        const formEl = document.getElementById('new-file-form');
        if (!formEl) {
            return DEFAULT_INDEXER_PLACEHOLDER;
        }

        const attributeValue = formEl.dataset?.defaultIndexer ? formEl.dataset.defaultIndexer.trim() : '';
        const windowValue = typeof window.currentUserName === 'string' ? window.currentUserName.trim() : '';

        const resolved = attributeValue || windowValue || DEFAULT_INDEXER_PLACEHOLDER;
        getDefaultIndexerName.cached = resolved;
        return resolved;
    }

    function getTodayDateValue() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function ensureIndexedByLocked() {
        const field = document.getElementById('indexed-by');
        if (!field) {
            return '';
        }

        const resolvedName = field.value?.trim() || getDefaultIndexerName();
        if (resolvedName) {
            field.value = resolvedName;
        }

        field.disabled = true;
        field.setAttribute('disabled', 'disabled');
        field.setAttribute('aria-disabled', 'true');
        field.readOnly = true;
        field.setAttribute('readonly', 'readonly');
        field.classList.add('cursor-not-allowed', 'bg-gray-100', 'text-gray-600');

        if (!field.dataset.hasLockWatcher) {
            field.addEventListener('focus', () => field.blur());
            field.dataset.hasLockWatcher = 'true';
        }

        return field.value?.trim() || resolvedName || '';
    }

    function ensureIndexedDateValue() {
        const field = document.getElementById('indexed-date');
        if (!field) {
            return getTodayDateValue();
        }

        const raw = (field.value || '').trim();
        const invalidValues = new Set(['', '0000-00-00', '1970-01-01', '1900-01-01', 'null', 'undefined']);

        if (!invalidValues.has(raw)) {
            return raw;
        }

        const today = getTodayDateValue();
        field.value = today;
        return today;
    }

    function generateTrackingIdSegment(length) {
        const characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let segment = '';

        for (let i = 0; i < length; i += 1) {
            const index = Math.floor(Math.random() * characters.length);
            segment += characters[index];
        }

        return segment;
    }

    function generateTrackingId() {
        return `TRK-${generateTrackingIdSegment(8)}-${generateTrackingIdSegment(5)}`;
    }

    function ensureTrackingIdExists() {
        const input = document.getElementById('tracking-id');
        if (!input) {
            return null;
        }

        let value = (input.value || '').trim();

        if (!value) {
            value = generateTrackingId();
        }

        setAutoFilledValue(input, value);

        return value;
    }

    window.generateTrackingId = generateTrackingId;
    window.ensureTrackingIdExists = ensureTrackingIdExists;

    /**
     * The tracking ID belongs to the FILE NUMBER, so re-pointing a record at a different
     * number must not keep the previous file's ID.
     *
     * Order: the ID the picked record already carries, then the file-number register
     * (/api/file-numbers/lookup, which also answers from `tra`), and only if the file has
     * no ID anywhere, a freshly generated one. Generating first would mint a second ID for
     * a file that already has one.
     */
    async function refreshTrackingIdForFileNumber(fileNumber, seedTrackingId = '') {
        const input = document.getElementById('tracking-id');
        if (!input) {
            return null;
        }

        const seed = String(seedTrackingId || '').trim();
        if (seed) {
            applyAutofillLock(['tracking-id'], false);
            setAutoFilledValue(input, seed, { lock: false });
            return seed;
        }

        const selected = String(fileNumber || '').trim();
        applyAutofillLock(['tracking-id'], false);
        input.value = '';

        if (selected) {
            try {
                const url = '/api/file-numbers/lookup?file_number=' + encodeURIComponent(selected)
                    + '&mlsf_no=' + encodeURIComponent(selected);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    const json = await response.json().catch(() => null);
                    const found = String(json?.data?.tracking_id || json?.data?.trackingId
                        || json?.data?.tra || '').trim();

                    if (found) {
                        setAutoFilledValue(input, found, { lock: false });
                        return found;
                    }
                }
            } catch (error) {
                // Fall through to generating one — a lookup outage must not block the edit.
                console.warn('[file-index] tracking ID lookup failed, generating a new one', error);
            }
        }

        return ensureTrackingIdExists();
    }

    function getCofoAutofillElements() {
        if (!cofoAutofillState.container) {
            const container = document.getElementById('cofo-autofill-status');
            if (!container) {
                return null;
            }

            cofoAutofillState.container = container;
            cofoAutofillState.icon = container.querySelector('.cofo-status-icon');
            cofoAutofillState.text = container.querySelector('.cofo-status-text');
        }

        return cofoAutofillState.container;
    }

    function setCofoAutofillStatus(type, message) {
        const container = getCofoAutofillElements();
        if (!container) {
            return;
        }

        const icon = cofoAutofillState.icon;
        const text = cofoAutofillState.text;

        const resetIcon = () => {
            if (icon) {
                icon.classList.remove('loading-spinner');
                icon.textContent = '';
            }
        };

        container.classList.remove('text-blue-600', 'text-green-600', 'text-red-600', 'text-gray-600');

        if (!message) {
            container.style.display = 'none';
            resetIcon();
            if (text) {
                text.textContent = '';
            }
            return;
        }

        container.style.display = 'block';

        let colorClass = 'text-blue-600';
        if (type === 'success') {
            colorClass = 'text-green-600';
        } else if (type === 'error') {
            colorClass = 'text-red-600';
        } else if (type === 'info') {
            colorClass = 'text-gray-600';
        }
        container.classList.add(colorClass);

        resetIcon();

        if (icon) {
            if (type === 'loading') {
                icon.classList.add('loading-spinner');
            } else if (type === 'success') {
                icon.textContent = 'OK';
            } else if (type === 'error') {
                icon.textContent = '!';
            } else if (type === 'info') {
                icon.textContent = 'i';
            }
        }

        if (text) {
            text.textContent = message;
        }
    }

    function applyAutofillLock(fieldIds, engaged) {
        const cssClass = 'autofill-locked';

        fieldIds.forEach(id => {
            const element = document.getElementById(id);
            if (!element) {
                return;
            }

            // Never unlock permanently readonly fields
            if (!engaged && element.dataset.permanentReadonly === 'true') {
                return;
            }

            const rawValue = element.value ?? '';
            const trimmedValue = typeof rawValue === 'string' ? rawValue.trim() : String(rawValue).trim();
            const shouldLock = engaged && trimmedValue !== '';

            const restoreOriginalState = () => {
                element.removeAttribute('data-autofill-locked');
                delete element.dataset.autofillLockedValue;
                element.classList.remove(cssClass);

                if (typeof element.readOnly === 'boolean') {
                    const originalReadonly = element.dataset.originalReadonly === 'true';
                    element.readOnly = originalReadonly;
                    if (!originalReadonly) {
                        element.removeAttribute('readonly');
                    }
                }

                const originalDisabled = element.dataset.originalDisabled === 'true';
                element.disabled = originalDisabled;
                if (!originalDisabled) {
                    element.removeAttribute('disabled');
                }
            };

            if (!Object.prototype.hasOwnProperty.call(element.dataset, 'originalReadonly')) {
                element.dataset.originalReadonly = element.readOnly ? 'true' : 'false';
            }

            if (!Object.prototype.hasOwnProperty.call(element.dataset, 'originalDisabled')) {
                element.dataset.originalDisabled = element.disabled ? 'true' : 'false';
            }

            if (shouldLock) {
                element.setAttribute('data-autofill-locked', 'true');
                element.dataset.autofillLockedValue = element.value ?? '';
                element.classList.add(cssClass);

                if (typeof element.readOnly === 'boolean') {
                    element.readOnly = true;
                }

                if (element.tagName === 'SELECT' || element.type === 'date' || element.type === 'time') {
                    element.disabled = true;
                }

                element.blur();
                return;
            }

            restoreOriginalState();
        });
    }

    function isAutofillLocked(element) {
        return element?.getAttribute('data-autofill-locked') === 'true';
    }

    function setAutoFilledValue(target, value, options = {}) {
        const element = typeof target === 'string' ? document.getElementById(target) : target;
        if (!element) {
            return;
        }

        const { lock = true } = options;

        let resolved = value;
        if (resolved === null || typeof resolved === 'undefined') {
            resolved = '';
        }

        const stringValue = typeof resolved === 'string' ? resolved : String(resolved);

        if ('value' in element) {
            element.value = stringValue;
            element.dispatchEvent(new Event('input', { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        } else if ('textContent' in element) {
            element.textContent = stringValue;
        }

        if (!element.id) {
            return;
        }

        if (!lock) {
            applyAutofillLock([element.id], false);
            return;
        }

        applyAutofillLock([element.id], true);
    }

    window.setAutoFilledValue = setAutoFilledValue;

    function enableManualEditing(element) {
        if (!element) {
            return;
        }

        // Never unlock permanently readonly fields
        if (element.dataset.permanentReadonly === 'true') {
            return;
        }

        element.readOnly = false;
        element.removeAttribute('readonly');
        element.classList.remove('bg-gray-50', 'autofill-locked');
    }

    function unlockCofOFieldsForEditing() {
        const ids = [
            'cofo-instrument-type',
            'cofo-date',
            'cofo-serial-no',
            'cofo-page-no',
            'cofo-vol-no',
            'cofo-number',
            'cofo-land-use',
            'cofo-period',
            'cofo-period-unit',
            'cofo-deeds-time',
            'cofo-deeds-date',
            'cofo-first-party',
            'cofo-second-party',
        ];

        applyAutofillLock(ids, false);

        ids.forEach(id => {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }
            field.readOnly = false;
            field.removeAttribute('readonly');
            field.disabled = false;
            field.classList.remove('bg-gray-100', 'autofill-locked');
        });
    }

    function resolveEntityCustomerElements() {
        if (entityCustomerUI.section && document.body.contains(entityCustomerUI.section)) {
            return entityCustomerUI;
        }

        entityCustomerUI.section = document.getElementById('entity-customer-section');

        if (!entityCustomerUI.section) {
            return entityCustomerUI;
        }

        entityCustomerUI.refreshBtn = document.getElementById('entity-customer-refresh-btn');
        entityCustomerUI.refreshIcon = document.getElementById('entity-customer-refresh-icon');
        entityCustomerUI.refreshLabel = document.getElementById('entity-customer-refresh-label');
        entityCustomerUI.clearBtn = document.getElementById('entity-customer-clear-btn');

        return entityCustomerUI;
    }

    function normalizeOwnerType(value) {
        const text = typeof value === 'string' ? value.trim() : '';
        if (!text) {
            return 'Individual';
        }

        const upper = text.toUpperCase();

        const corporateIndicators = [
            ' LTD', 'LIMITED', 'PLC', 'INC', 'CORP', 'CORPORATION', 'COMPANY', 'GROUP', 'HOLDINGS', 'ENTERPRISE', 'ENTERPRISES',
            'SERVICES', 'VENTURES', 'INDUSTRIES', 'NIG.', 'NIGERIA', 'GLOBAL', 'MINISTRY', 'AGENCY', 'BANK', 'HOTELS', 'INVESTMENT', 'RESOURCES', 'CONSULT'
        ];

        if (corporateIndicators.some(keyword => upper.includes(keyword))) {
            return 'Corporate';
        }

        const multipleIndicators = [' AND ', ' & ', ',', ';', ' ESTATE OF ', 'FAMILY', ' HEIRS', ' JOINT ', ' COMMUNITY', ' COOPERATIVE'];
        if (multipleIndicators.some(keyword => upper.includes(keyword))) {
            return 'Multiple Owners';
        }

        return 'Individual';
    }

    function normalizeEntityTypeValue(value) {
        return normalizeOwnerType(value);
    }

    function normalizeCustomerTypeValue(value) {
        const normalized = normalizeOwnerType(value);
        if (normalized === 'Multiple Owners') {
            return 'Multiple Owners';
        }
        if (normalized === 'Corporate') {
            return 'Corporate';
        }
        return 'Individual';
    }

    function getRadioGroupValue(name) {
        const checked = document.querySelector(`input[type="radio"][name="${name}"]:checked`);
        return checked ? checked.value : '';
    }

    function setRadioGroupValue(name, value) {
        const radios = document.querySelectorAll(`input[type="radio"][name="${name}"]`);
        if (!radios.length) {
            return;
        }

        const target = String(value || '').toUpperCase();
        let matched = false;

        radios.forEach(radio => {
            const matches = target !== '' && String(radio.value).toUpperCase() === target;
            radio.checked = matches;
            if (matches) {
                matched = true;
            }
        });

        if (!matched) {
            radios[0].checked = true;
        }
    }

    function enforceLockStateForField(field) {
        if (!field || !('value' in field)) {
            return;
        }

        const trimmed = typeof field.value === 'string' ? field.value.trim() : '';
        const lockedByAutofill = isAutofillLocked(field);
        const isMirroredPropertyField = field.hasAttribute('data-property-address-mirror');

        if (trimmed !== '') {
            if (lockedByAutofill) {
                setAutoFilledValue(field, field.value, { lock: true });
            } else if (isMirroredPropertyField) {
                field.classList.add('property-address-mirrored');
                if (typeof field.readOnly === 'boolean') {
                    field.readOnly = true;
                }
            } else if (field.id) {
                applyAutofillLock([field.id], false);
            }
            return;
        }

        if (field.id) {
            applyAutofillLock([field.id], false);
        }

        if (isMirroredPropertyField) {
            field.removeAttribute('data-property-address-mirror');
            field.classList.remove('property-address-mirrored');
            if (typeof field.readOnly === 'boolean') {
                field.readOnly = false;
            }
        }

        if (!field.classList.contains('border-gray-300')) {
            field.classList.add('border-gray-300');
        }
    }

    function applyAttentionStyles(field) {
        if (!field || !('value' in field)) {
            return;
        }

        const trimmed = typeof field.value === 'string' ? field.value.trim() : '';

        if (trimmed === '') {
            field.classList.remove('border-gray-300');
            field.classList.add('border-red-400', 'bg-red-50');
            return;
        }

        field.classList.remove('border-red-400', 'bg-red-50');
        if (!field.classList.contains('border-gray-300')) {
            field.classList.add('border-gray-300');
        }
    }

    function refreshEntityCustomerFieldStates() {
        LOCKABLE_ENTITY_CUSTOMER_FIELD_IDS.forEach(id => {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }

            enforceLockStateForField(field);
        });

        REQUIRED_ENTITY_CUSTOMER_FIELD_IDS.forEach(id => {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }

            applyAttentionStyles(field);
        });
    }

    function unlockPropertyAddressField(field, { force = false } = {}) {
        if (!field) {
            return;
        }

        if (!force && isAutofillLocked(field)) {
            field.removeAttribute('data-property-address-mirror');
            field.classList.remove('property-address-mirrored');
            return;
        }

        field.removeAttribute('data-property-address-mirror');
        field.classList.remove('property-address-mirrored');

        if (!isAutofillLocked(field) && typeof field.readOnly === 'boolean') {
            field.readOnly = false;
        }
    }

    function mirrorPropertyAddressValue(sourceField, targetField) {
        if (!targetField) {
            return;
        }

        targetField.value = sourceField.value;
        targetField.setAttribute('data-property-address-mirror', 'true');
        targetField.classList.add('property-address-mirrored');

        if (!isAutofillLocked(targetField) && typeof targetField.readOnly === 'boolean') {
            targetField.readOnly = true;
        }
    }

    function handleLinkedPropertyAddressInput(event) {
        const field = event.target;
        if (
            PROPERTY_ADDRESS_FIELD_IDS.length < 2
            || !field
            || !PROPERTY_ADDRESS_FIELD_IDS.includes(field.id)
        ) {
            return;
        }

        const counterpartId = field.id === PROPERTY_ADDRESS_FIELD_IDS[0]
            ? PROPERTY_ADDRESS_FIELD_IDS[1]
            : PROPERTY_ADDRESS_FIELD_IDS[0];
        const counterpart = document.getElementById(counterpartId);
        if (!counterpart) {
            return;
        }

        const trimmed = typeof field.value === 'string' ? field.value.trim() : '';

        if (trimmed === '') {
            if (propertyAddressLinkState.masterFieldId === field.id) {
                propertyAddressLinkState.masterFieldId = null;
                counterpart.value = '';
                unlockPropertyAddressField(counterpart);
            }

            if (!propertyAddressLinkState.masterFieldId) {
                unlockPropertyAddressField(field);
                unlockPropertyAddressField(counterpart);
            }

            return;
        }

        propertyAddressLinkState.masterFieldId = field.id;
        mirrorPropertyAddressValue(field, counterpart);
    }

    function initializePropertyAddressLinking() {
        if (propertyAddressLinkState.listenersInitialized) {
            return;
        }

        PROPERTY_ADDRESS_FIELD_IDS.forEach(id => {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }

            field.addEventListener('input', handleLinkedPropertyAddressInput);
        });

        propertyAddressLinkState.listenersInitialized = true;
    }

    function resetPropertyAddressLinkState({ force = false } = {}) {
        propertyAddressLinkState.masterFieldId = null;
        PROPERTY_ADDRESS_FIELD_IDS.forEach(id => {
            const field = document.getElementById(id);
            unlockPropertyAddressField(field, { force });
        });
    }

    function handleEntityCustomerFieldInput(event) {
        const field = event?.target;
        if (!field || field.readOnly || field.disabled) {
            return;
        }

        field.classList.remove('border-red-400', 'bg-red-50');
        if (!field.classList.contains('border-gray-300')) {
            field.classList.add('border-gray-300');
        }
    }

    function handleEntityCustomerFieldBlur(event) {
        const field = event?.target;
        if (!field) {
            return;
        }

        if (LOCKABLE_ENTITY_CUSTOMER_FIELD_IDS.includes(field.id)) {
            enforceLockStateForField(field);
        }

        if (REQUIRED_ENTITY_CUSTOMER_FIELD_IDS.includes(field.id)) {
            applyAttentionStyles(field);
        }
    }

    function refreshEntityCustomerClearVisibility() {
        const elements = resolveEntityCustomerElements();
        if (!elements.clearBtn) {
            return;
        }

        const allFieldIds = ENTITY_TEXT_FIELD_IDS.concat(CUSTOMER_TEXT_FIELD_IDS);
        const hasTextValue = allFieldIds.some(id => {
            const field = document.getElementById(id);
            if (!field) {
                return false;
            }

            if (id === 'customer-status') {
                const statusValue = (field.value || '').trim();
                return statusValue !== '' && statusValue !== 'Active';
            }

            if (field.tagName === 'SELECT') {
                return field.value && field.selectedIndex > 0;
            }

            return Boolean(field.value && String(field.value).trim() !== '');
        });

        const entityTypeValue = getRadioGroupValue(ENTITY_TYPE_FIELD_NAME);
        const customerTypeValue = getRadioGroupValue(CUSTOMER_TYPE_FIELD_NAME);

        const hasNonDefaultType = (
            (entityTypeValue && entityTypeValue !== 'Individual') ||
            (customerTypeValue && customerTypeValue !== 'Individual')
        );

        const shouldShow = entityCustomerState.hasRecord || hasTextValue || hasNonDefaultType;
        elements.clearBtn.classList.toggle('hidden', !shouldShow);
    }

    function updateEntityCustomerStatus(statusType, message) {
        refreshEntityCustomerClearVisibility();
        if (message) {
            console.debug(`[EntityCustomer][${statusType}] ${message}`);
        }
    }

    function setRefreshLoadingState(isLoading) {
        const elements = resolveEntityCustomerElements();
        if (!elements.refreshBtn) {
            return;
        }

        elements.refreshBtn.disabled = Boolean(isLoading);

        if (elements.refreshLabel) {
            elements.refreshLabel.textContent = isLoading ? 'Searching...' : 'Retry Lookup';
        }

        if (elements.refreshIcon) {
            elements.refreshIcon.classList.toggle('animate-spin', Boolean(isLoading));
        }
    }

    function clearEntityCustomerFields({ silent = false } = {}) {
        const textFieldIds = ENTITY_TEXT_FIELD_IDS.concat(CUSTOMER_TEXT_FIELD_IDS);

        // Hide Entity & Customer Cards
        document.querySelectorAll('.entity-customer-cards').forEach(el => el.classList.add('hidden'));

        textFieldIds.forEach(id => {
            const element = document.getElementById(id);
            if (!element) {
                return;
            }

            if (id === 'customer-status') {
                element.value = 'Active';
                return;
            }

            if ('value' in element) {
                element.value = '';
            }
        });

        const entityIdInput = document.getElementById('entity-id');
        if (entityIdInput) {
            entityIdInput.value = '';
        }

        const customerIdInput = document.getElementById('customer-id');
        if (customerIdInput) {
            customerIdInput.value = '';
        }

        setRadioGroupValue(ENTITY_TYPE_FIELD_NAME, 'Individual');
        setRadioGroupValue(CUSTOMER_TYPE_FIELD_NAME, 'Individual');

        entityCustomerState.entity = null;
        entityCustomerState.customer = null;
        entityCustomerState.hasRecord = false;

        applyAutofillLock(textFieldIds, false);
        resetPropertyAddressLinkState({ force: true });

        if (!silent) {
            updateEntityCustomerStatus('idle', 'Entity and customer details cleared.');
        } else {
            refreshEntityCustomerClearVisibility();
        }

        refreshEntityCustomerFieldStates();

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function populateEntityCustomerFields(payload, message = '') {
        const elements = resolveEntityCustomerElements();
        if (!elements.section) {
            return;
        }

        const entityRecord = payload?.entity || null;
        const customerRecord = payload?.customer || null;

        entityCustomerState.entity = entityRecord;
        entityCustomerState.customer = customerRecord;
        entityCustomerState.hasRecord = Boolean(entityRecord || customerRecord);

        if (entityCustomerState.hasRecord) {
            document.querySelectorAll('.entity-customer-cards').forEach(el => el.classList.remove('hidden'));
        }

        resetPropertyAddressLinkState({ force: false });

        const detectedEntityType = normalizeEntityTypeValue(
            entityRecord?.entity_type
            ?? payload?.detected_entity_type
            ?? (customerRecord ? customerRecord.customer_type : null)
        );

        const detectedCustomerType = normalizeCustomerTypeValue(
            customerRecord?.customer_type
            ?? payload?.detected_customer_type
            ?? detectedEntityType
        );
        const shouldLockAutoFields = entityCustomerState.hasRecord;

        setRadioGroupValue(ENTITY_TYPE_FIELD_NAME, detectedEntityType);
        setAutoFilledValue('entity-name', entityRecord?.entity_name ?? customerRecord?.customer_name ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue(
            'entity-physical-address',
            entityRecord?.physical_address
            ?? entityRecord?.mailing_address
            ?? customerRecord?.physical_address
            ?? customerRecord?.residential_address
            ?? document.getElementById('residence_address')?.value
            ?? '',
            { lock: shouldLockAutoFields }
        );

        setRadioGroupValue(CUSTOMER_TYPE_FIELD_NAME, detectedCustomerType);
        setAutoFilledValue('customer-status', customerRecord?.status ?? 'Active', { lock: false });
        setAutoFilledValue('customer-name', customerRecord?.customer_name ?? entityRecord?.entity_name ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue('customer-email', customerRecord?.email ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue('customer-phone', customerRecord?.phone ?? customerRecord?.mobile ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue('customer-account-no', customerRecord?.account_no ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue('customer-code', customerRecord?.customer_code ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue('customer-retired-by', customerRecord?.retired_by ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue('customer-reason-retired', customerRecord?.reason_retired ?? '', { lock: shouldLockAutoFields });
        setAutoFilledValue('customer-property-address', customerRecord?.property_address ?? '', { lock: shouldLockAutoFields });

        const entityIdInput = document.getElementById('entity-id');
        if (entityIdInput) {
            entityIdInput.value = entityRecord?.id ?? '';
        }

        const customerIdInput = document.getElementById('customer-id');
        if (customerIdInput) {
            customerIdInput.value = customerRecord?.id ?? '';
        }

        const statusType = entityCustomerState.hasRecord
            ? 'success'
            : 'warning';

        const messageText = message || (
            entityCustomerState.hasRecord
                ? 'Matched entity/customer records from staging tables.'
                : 'No matching entity/customer records were found. Enter the details manually.'
        );

        updateEntityCustomerStatus(statusType, messageText);

        refreshEntityCustomerClearVisibility();
        refreshEntityCustomerFieldStates();

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    async function autoFillEntityCustomerFromAPI(fileNumber, options = {}) {
        const {
            source = 'manual',
            silent = false,
        } = options;

        const elements = resolveEntityCustomerElements();
        if (!elements.section) {
            return null;
        }

        const trimmed = (fileNumber || '').trim();

        if (!trimmed) {
            clearEntityCustomerFields({ silent });
            entityCustomerState.fileNumber = null;
            setRefreshLoadingState(false);
            hideIndexedFeedback();
            return null;
        }

        entityCustomerState.fileNumber = trimmed;
        const requestId = entityCustomerState.lastRequestId + 1;
        entityCustomerState.lastRequestId = requestId;

        setRefreshLoadingState(true);
        if (!silent) {
            updateEntityCustomerStatus('loading', `Looking up entity and customer details for ${trimmed}...`);
        }

        try {
            const response = await fetch(`/api/entity-customer/by-file-number/${encodeURIComponent(trimmed)}`);
            let payload;

            try {
                payload = await response.json();
            } catch (parseError) {
                payload = null;
            }

            if (entityCustomerState.lastRequestId !== requestId) {
                return null;
            }

            if (!response.ok || !payload || !payload.success) {
                const message = payload?.message || 'Unable to retrieve entity/customer records.';
                updateEntityCustomerStatus('warning', message);
                clearEntityCustomerFields({ silent: true });
                return null;
            }

            populateEntityCustomerFields(payload.data || {}, payload.message || '');
            return payload.data || {};
        } catch (error) {
            if (entityCustomerState.lastRequestId === requestId) {
                console.error('Entity/customer lookup failed:', error);
                updateEntityCustomerStatus('error', 'Failed to load entity and customer details. You can enter them manually.');
                clearEntityCustomerFields({ silent: true });
            }
            return null;
        } finally {
            if (entityCustomerState.lastRequestId === requestId) {
                setRefreshLoadingState(false);
            }
        }
    }

    function initializeEntityCustomerSection() {
        if (entityCustomerState.initialized) {
            resolveEntityCustomerElements();
            return;
        }

        const elements = resolveEntityCustomerElements();
        if (!elements.section) {
            return;
        }

        entityCustomerState.initialized = true;

        const fileNumberInput = document.getElementById('fileno');
        const fileNumberDisplay = document.getElementById('file-number-display');

        const getCurrentFileNumber = () => {
            const hiddenValue = fileNumberInput?.value?.trim();
            if (hiddenValue) {
                return hiddenValue;
            }

            const displayValue = fileNumberDisplay?.value?.trim();
            if (displayValue) {
                return displayValue;
            }

            return '';
        };

        if (elements.refreshBtn) {
            elements.refreshBtn.addEventListener('click', () => {
                const current = getCurrentFileNumber();
                if (current) {
                    autoFillEntityCustomerFromAPI(current, { source: 'manual-refresh' });
                } else {
                    clearEntityCustomerFields({ silent: false });
                }
            });
        }

        if (elements.clearBtn) {
            elements.clearBtn.addEventListener('click', () => {
                clearEntityCustomerFields({ silent: false });
            });
        }

        const handleFileNumberManualChange = async () => {
            const current = getCurrentFileNumber();
            syncSourceFileSelectionWithCurrentFileNumber(current);

            if (current) {
                if (!editModeState.isEditing && !isKangisVariantMode()) {
                    autoFillEntityCustomerFromAPI(current, { source: 'input-change' });
                }
                await checkFileAlreadyIndexed(current, { source: 'input-change' });
                await checkTemporaryFileAssociationInline(current);
            } else {
                clearEntityCustomerFields({ silent: false });
                hideIndexedFeedback();
                exitEditMode();
            }
        };

        if (fileNumberInput) {
            fileNumberInput.addEventListener('change', handleFileNumberManualChange);
            fileNumberInput.addEventListener('blur', handleFileNumberManualChange);
        }

        if (fileNumberDisplay) {
            if (fileNumberInput) {
                fileNumberDisplay.addEventListener('input', () => {
                    fileNumberInput.value = fileNumberDisplay.value;
                    syncSourceFileSelectionWithCurrentFileNumber(fileNumberDisplay.value);
                });
            }
            fileNumberDisplay.addEventListener('change', handleFileNumberManualChange);
            fileNumberDisplay.addEventListener('blur', handleFileNumberManualChange);
        }

        const watchFieldIds = ENTITY_TEXT_FIELD_IDS.concat(CUSTOMER_TEXT_FIELD_IDS).filter(id => id !== 'customer-status');
        watchFieldIds.forEach(id => {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }

            const refreshHandler = () => refreshEntityCustomerClearVisibility();
            field.addEventListener('input', refreshHandler);
            field.addEventListener('change', refreshHandler);
        });

        [ENTITY_TYPE_FIELD_NAME, CUSTOMER_TYPE_FIELD_NAME].forEach(name => {
            const radios = document.querySelectorAll(`input[type="radio"][name="${name}"]`);
            radios.forEach(radio => {
                radio.addEventListener('change', () => refreshEntityCustomerClearVisibility());
            });
        });

        LOCKABLE_ENTITY_CUSTOMER_FIELD_IDS.forEach(id => {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }

            field.addEventListener('input', handleEntityCustomerFieldInput);
            field.addEventListener('blur', handleEntityCustomerFieldBlur);
        });

        refreshEntityCustomerFieldStates();

        // Sync Residential Address from Personal Details if Entity Address is empty
        const personalAddress = document.getElementById('residence_address');
        const entityAddress = document.getElementById('entity-physical-address');
        if (personalAddress && entityAddress) {
            const syncAddress = () => {
                if (!entityAddress.value.trim()) {
                    entityAddress.value = personalAddress.value;
                    // Trigger input to refresh visibility/state if needed
                    entityAddress.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };
            personalAddress.addEventListener('input', syncAddress);
            personalAddress.addEventListener('change', syncAddress);
            // Initial sync
            if (personalAddress.value && !entityAddress.value.trim()) {
                syncAddress();
            }
        }

        const initialFileNumber = getCurrentFileNumber();
        if (initialFileNumber) {
            autoFillEntityCustomerFromAPI(initialFileNumber, { source: 'initial-load', silent: true });
        } else {
            refreshEntityCustomerClearVisibility();
        }
    }

    async function autoFillCofODetailsFromAPI(fileNumber) {
        const trimmed = (fileNumber || '').trim();

        // A KANGIS physical variant saves under its own _N file number and starts
        // life with no instruments of its own. Pulling the base file's CofO in here
        // ticks "Has CofO" and fills the whole card with another record's details.
        if (isKangisVariantMode()) {
            cofoAutofillState.lastRequestId += 1;
            setCofoAutofillStatus(null, '');
            return null;
        }

        if (trimmed === '') {
            cofoAutofillState.lastRequestId += 1;
            setCofoAutofillStatus(null, '');
            return null;
        }

        const requestId = cofoAutofillState.lastRequestId + 1;
        cofoAutofillState.lastRequestId = requestId;

        const applyStatus = (type, message) => {
            if (cofoAutofillState.lastRequestId === requestId) {
                setCofoAutofillStatus(type, message);
            }
        };

        applyStatus('loading', `Looking up CofO record for ${trimmed}...`);

        try {
            const response = await fetch(`/api/cofo-record/${encodeURIComponent(trimmed)}`);
            let data = {};

            try {
                data = await response.json();
            } catch (parseError) {
                data = {};
            }

            if (!response.ok || !data.success || !data.data) {
                const message = data.message || `No CofO record found for ${trimmed}.`;
                applyStatus('info', message);
                return null;
            }

            const fields = data.data;

            if (cofoAutofillState.lastRequestId !== requestId) {
                return null;
            }

            const hasCofoToggle = document.getElementById('has-cofo-toggle');
            const cofoDetailsContainer = document.getElementById('cofo-details-container');
            const hiddenHasCofo = document.getElementById('has-cofo');

            if (hiddenHasCofo) {
                hiddenHasCofo.checked = true;
            }

            if (hasCofoToggle) {
                if (!hasCofoToggle.checked) {
                    hasCofoToggle.checked = true;
                    hasCofoToggle.dispatchEvent(new Event('change'));
                } else if (cofoDetailsContainer) {
                    cofoDetailsContainer.classList.remove('hidden');
                }
            }

            clearCofoFields();

            const instrumentTypeSelect = document.getElementById('cofo-instrument-type');
            if (instrumentTypeSelect) {
                const availableValues = Array.from(instrumentTypeSelect.options).map(option => option.value);
                if (fields.cofo_type && availableValues.includes(fields.cofo_type)) {
                    instrumentTypeSelect.value = fields.cofo_type;
                } else {
                    instrumentTypeSelect.value = 'Certificate of Occupancy';
                }
                instrumentTypeSelect.dispatchEvent(new Event('change'));
            }

            const cofoDate = document.getElementById('cofo-date');
            if (cofoDate) {
                const resolvedCofoDate = fields.certificate_date || fields.transaction_date || '';
                if (resolvedCofoDate) {
                    cofoDate.value = resolvedCofoDate;
                }
            }

            const cofoSerial = document.getElementById('cofo-serial-no');
            if (cofoSerial && fields.serial_no) {
                cofoSerial.value = fields.serial_no;
            }

            const cofoPage = document.getElementById('cofo-page-no');
            if (cofoPage && (fields.page_no || fields.serial_no)) {
                cofoPage.value = fields.page_no || fields.serial_no;
            }

            const cofoVolume = document.getElementById('cofo-vol-no');
            if (cofoVolume && fields.volume_no) {
                cofoVolume.value = fields.volume_no;
            }

            const cofoNumberInput = document.getElementById('cofo-number');
            if (cofoNumberInput && fields.cofo_no) {
                cofoNumberInput.value = fields.cofo_no;
            }

            const cofoLandUseInput = document.getElementById('cofo-land-use');
            if (cofoLandUseInput && fields.land_use) {
                cofoLandUseInput.value = fields.land_use;
            }

            const cofoStatusSelect = document.getElementById('cofo-status');
            if (cofoStatusSelect) {
                cofoStatusSelect.value = fields.status || 'Active';
            }

            const periodInput = document.getElementById('cofo-period');
            if (periodInput && fields.period) {
                periodInput.value = fields.period;
            }

            const periodUnitSelect = document.getElementById('cofo-period-unit');
            if (periodUnitSelect && fields.period_unit) {
                const normalized = String(fields.period_unit).toLowerCase();
                const availableOptions = Array.from(periodUnitSelect.options).map((option) => option.value.toLowerCase());
                const matchIndex = availableOptions.indexOf(normalized);
                if (matchIndex >= 0) {
                    periodUnitSelect.selectedIndex = matchIndex;
                }
            }

            const cofoDeedsDate = document.getElementById('cofo-deeds-date');
            if (cofoDeedsDate && fields.transaction_date) {
                cofoDeedsDate.value = fields.transaction_date;
            }

            const cofoDeedsTime = document.getElementById('cofo-deeds-time');
            if (cofoDeedsTime && fields.transaction_time) {
                cofoDeedsTime.value = fields.transaction_time;
            }

            const cofoTransactionDetails = document.getElementById('cofo-transaction-details');
            if (cofoTransactionDetails && (fields.grantor || fields.grantee)) {
                cofoTransactionDetails.classList.remove('hidden');
            }

            const cofoFirstPartyInput = document.getElementById('cofo-first-party');
            if (cofoFirstPartyInput && fields.grantor) {
                cofoFirstPartyInput.value = fields.grantor;
            }

            const cofoSecondPartyInput = document.getElementById('cofo-second-party');
            if (cofoSecondPartyInput && fields.grantee) {
                cofoSecondPartyInput.value = fields.grantee;
            }

            const cofoFieldIds = [
                'cofo-instrument-type',
                'cofo-date',
                'cofo-serial-no',
                'cofo-page-no',
                'cofo-vol-no',
                'cofo-deeds-time',
                'cofo-deeds-date',
                'cofo-first-party',
                'cofo-second-party',
            ];

            if (!editModeState.isEditing) {
                applyAutofillLock(cofoFieldIds, true);
            } else {
                applyAutofillLock(cofoFieldIds, false);
                unlockCofOFieldsForEditing();
            }

            autofillState.cofo = true;
            applyStatus('success', `CofO details loaded for ${trimmed}.`);

            return fields;
        } catch (error) {
            console.error('CofO lookup failed', error);
            applyStatus('error', 'Failed to load CofO details. Please try again.');
            return null;
        }
    }

    window.autoFillCofODetailsFromAPI = autoFillCofODetailsFromAPI;

    const ARCHIVE_FIELD_IDS = [
        'awaiting-file-no',
        'registry-batch-no',
        'mdc-batch-no',
        'registry',
        'shelf-rack-no',
        'sys-batch-no',
        'serial-no',
    ];

    function normalizeFileno(value) {
        if (!value && value !== 0) {
            return '';
        }
        return String(value)
            .trim()
            .toUpperCase()
            .replace(/[\s\-\/\\.,]/g, '');
    }

    function resetSourceFileSelection() {
        sourceFileSelectionState.fileId = null;
        sourceFileSelectionState.normalizedFileNumber = '';
    }

    function setSourceFileSelection(record, fileNumber) {
        const candidateId = record?.id;
        const parsedId = Number(candidateId);

        if (!Number.isFinite(parsedId)) {
            resetSourceFileSelection();
            return;
        }

        sourceFileSelectionState.fileId = parsedId;
        sourceFileSelectionState.normalizedFileNumber = normalizeFileno(
            fileNumber
            || record?.file_number
            || record?.fileno
            || record?.mlsFNo
            || record?.mlsfNo
            || record?.kangisFileNo
            || record?.NewKANGISFileNo
            || ''
        );
    }

    function syncSourceFileSelectionWithCurrentFileNumber(currentFileNumber) {
        const normalizedCurrent = normalizeFileno(currentFileNumber);
        if (!normalizedCurrent || normalizedCurrent !== sourceFileSelectionState.normalizedFileNumber) {
            resetSourceFileSelection();
        }
    }

    function getSourceFileIdForSubmit(currentFileNumber) {
        const normalizedCurrent = normalizeFileno(currentFileNumber);
        if (!normalizedCurrent || normalizedCurrent !== sourceFileSelectionState.normalizedFileNumber) {
            return null;
        }

        return sourceFileSelectionState.fileId;
    }

    function normalizeTransactionRecord(record) {
        if (!record) {
            return null;
        }

        const transactionType = normalizeTransactionTypeValue(
            record.transaction_type
            || record.transactionType
            || record.transaction_type_label
            || ''
        );

        const normalized = {
            id: record.id ?? record.transaction_id ?? null,
            file_number: record.file_number || record.fileno || record.mlsFNo || record.mlsfNo || null,
            transaction_type: transactionType,
            transaction_date: record.transaction_date || record.transactionDate || '',
            deeds_date: record.deeds_date || record.deedsDate || null,
            serialNo: record.serialNo || record.serial_no || record.serial || '',
            pageNo: record.pageNo || record.page_no || '',
            volumeNo: record.volumeNo || record.volume_no || '',
            reg_date: record.reg_date || record.regDate || null,
            reg_time: record.reg_time || record.regTime || null,
            period: record.period ?? record.periodValue ?? null,
            period_unit: record.period_unit ?? record.periodUnit ?? null,
            land_use: record.land_use || record.land_use_type || '',
            plot_no: record.plot_no || record.plot_number || '',
            lgsaOrCity: record.lgsaOrCity || record.lga || '',
            comments: record.comments || '',
            Grantor: record.Grantor || record.first_party || record.Assignor || null,
            Grantee: record.Grantee || record.second_party || record.Assignee || null,
        };

        if (!normalized.Grantor && record.parties && typeof record.parties === 'object') {
            const parties = record.parties;
            normalized.Grantor = parties.Grantor || parties.Assignor || parties.Mortgagor || null;
            normalized.Grantee = parties.Grantee || parties.Assignee || parties.Mortgagee || null;
        }

        normalized.first_party = normalized.Grantor || null;
        normalized.second_party = normalized.Grantee || null;
        normalized.transactionType = normalized.transaction_type;
        normalized.transactionDate = normalized.transaction_date;
        normalized.serial_no = normalized.serialNo;
        normalized.page_no = normalized.pageNo;
        normalized.volume_no = normalized.volumeNo;
        normalized.periodUnit = normalized.period_unit;

        return normalized;
    }

    function normalizeTransactionRecords(records) {
        if (!Array.isArray(records)) {
            return [];
        }
        return records
            .map(normalizeTransactionRecord)
            .filter(Boolean);
    }

    function cacheTransactionsForFile(fileNumber, records) {
        if (!fileNumber) {
            return;
        }
        const normalizedRecords = normalizeTransactionRecords(records);
        const key = normalizeFileno(fileNumber);

        if (!normalizedRecords.length) {
            propertyTransactionCache.byFileNumber.delete(key);
            return;
        }

        propertyTransactionCache.byFileNumber.set(key, normalizedRecords);
    }

    function getCachedTransactionsForFile(fileNumber) {
        if (!fileNumber) {
            return [];
        }
        const key = normalizeFileno(fileNumber);
        const cached = propertyTransactionCache.byFileNumber.get(key) || [];
        return cached.map(record => ({ ...record }));
    }

    function convertFileHistoryEntriesToTransactions(entries) {
        if (!Array.isArray(entries)) {
            return [];
        }
        return entries
            .map(entry => {
                if (entry && typeof entry === 'object') {
                    const clone = { ...entry };
                    if (!clone.transaction_type && clone.transactionType) {
                        clone.transaction_type = clone.transactionType;
                    }
                    if (!clone.transaction_date && clone.transactionDate) {
                        clone.transaction_date = clone.transactionDate;
                    }
                    return clone;
                }
                return entry;
            })
            .map(normalizeTransactionRecord)
            .filter(Boolean);
    }

    async function fetchTransactionsForRecordId(recordId, fileNumber) {
        const form = document.getElementById('new-file-form');
        const template = form?.dataset?.transactionsUrlTemplate || '';
        if (!template || !recordId) {
            return [];
        }

        const url = template.replace('__ID__', encodeURIComponent(recordId));

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });

            const payload = await response.json();

            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || 'Failed to load existing transactions.');
            }

            cacheTransactionsForFile(fileNumber, payload.data || []);
            return getCachedTransactionsForFile(fileNumber);
        } catch (error) {
            console.warn('Unable to load saved property transactions', error);
            return [];
        }
    }

    function resetGroupingState() {
        groupingState.record = null;
        groupingState.normalizedAwaiting = null;
        groupingState.mismatch = true;

        const groupingIdInput = document.getElementById('grouping-id');
        if (groupingIdInput) {
            groupingIdInput.value = '';
        }
    }

    function setGroupingRecord(record) {
        groupingState.record = record || null;
        groupingState.normalizedAwaiting = record ? normalizeFileno(record.awaiting_fileno) : null;
        groupingState.mismatch = false;

        const groupingIdInput = document.getElementById('grouping-id');
        if (groupingIdInput) {
            groupingIdInput.value = record?.id ?? '';
        }

        if (record) {
            document.querySelectorAll('.entity-customer-cards').forEach(el => el.classList.remove('hidden'));
        }

        // Dispatch event for Alpine to sync
        window.dispatchEvent(new CustomEvent('grouping-record-updated', { detail: record }));
    }

    function updateCreateButtonState() {
        const createBtn = document.getElementById('create-file-btn');
        if (!createBtn) {
            return;
        }

        // Check if duplicate is detected
        if (indexedFeedbackState.isDuplicate && !editModeState.isEditing) {
            createBtn.disabled = true;
            createBtn.classList.add('opacity-60', 'cursor-not-allowed');
            return;
        }

        const canSubmit = editModeState.isEditing
            || (Boolean(groupingState.record) && !groupingState.mismatch);

        if (canSubmit) {
            createBtn.disabled = false;
            createBtn.classList.remove('opacity-60', 'cursor-not-allowed');
        } else {
            createBtn.disabled = true;
            if (!createBtn.classList.contains('opacity-60')) {
                createBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }
        }
    }

    function getArchiveFieldElements() {
        return ARCHIVE_FIELD_IDS
            .map(id => document.getElementById(id))
            .filter(Boolean);
    }

    /**
     * Ask the server which Registry (1/2/3) a file number belongs to, per the
     * prefix+year rules in config/file_ranges.php. Returns null when the file
     * number is empty, unparseable, or belongs to a different registry family
     * (ST/SLTR/DCIV/KANGIS/Survey) not covered by that config.
     */
    async function detectRegistryForFileNumber(fileNumber) {
        const trimmed = (fileNumber || '').trim();
        if (!trimmed) {
            return null;
        }
        try {
            const response = await fetch(`/api/registry/detect?file_number=${encodeURIComponent(trimmed)}`);
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                return null;
            }
            return payload.registry ?? null;
        } catch (error) {
            console.error('Registry detection lookup failed', error);
            return null;
        }
    }

    /**
     * Auto-fill the Registry field, unless an admin has explicitly unlocked it
     * for a manual override (see registry-admin-unlock-btn) — auto-fill must
     * never silently clobber a deliberate admin override.
     */
    function applyRegistryFieldValue(element, value) {
        if (!element) {
            return;
        }
        if (element.dataset.adminUnlocked === 'true') {
            return;
        }
        lockArchiveField(element, value);
    }

    function lockArchiveField(element, value) {
        if (!element) {
            return;
        }

        const resolvedValue = value ?? '';
        const stringValue = typeof resolvedValue === 'string' ? resolvedValue : String(resolvedValue);
        const hasContent = stringValue.trim() !== '';

        element.value = stringValue;

        if (!hasContent) {
            // Preserve permanently readonly fields
            if (element.dataset.permanentReadonly !== 'true') {
                element.readOnly = false;
                element.removeAttribute('readonly');
            }
            element.classList.remove('bg-gray-50', 'autofill-locked', 'error-border');
            element.style.removeProperty('color');
            element.removeAttribute('data-autofill-locked');

            if (element.id === 'awaiting-file-no') {
                if (element.dataset) {
                    delete element.dataset.locked;
                    delete element.dataset.lockedValue;
                }
            }

            if (element.dataset) {
                delete element.dataset.autofillLockedValue;
            }
            return;
        }

        element.readOnly = true;
        element.setAttribute('readonly', 'readonly');
        element.setAttribute('data-autofill-locked', 'true');
        element.classList.add('bg-gray-50', 'autofill-locked');
        element.classList.remove('error-border');
        element.dataset.autofillLockedValue = element.value;

        if (!element.dataset) {
            element.dataset = {};
        }

        if (element.id === 'awaiting-file-no') {
            element.dataset.locked = 'true';
            element.dataset.lockedValue = element.value;
        }
    }

    function unlockArchiveField(element) {
        if (!element) {
            return;
        }

        // Never unlock permanently readonly fields
        if (element.dataset.permanentReadonly === 'true') {
            return;
        }

        element.readOnly = false;
        element.removeAttribute('readonly');
        element.classList.remove('bg-gray-50', 'success-border', 'error-border', 'autofill-locked');
        element.style.removeProperty('color');
        element.removeAttribute('data-autofill-locked');
        if (element.dataset) {
            delete element.dataset.autofillLockedValue;
        }

        if (element.dataset) {
            delete element.dataset.locked;
            delete element.dataset.lockedValue;
        }
    }

    function clearArchiveFields(options = {}) {
        const { preserveAwaiting = false, preserveIndexed = false, preservePrimary = false } = options;

        ARCHIVE_FIELD_IDS.forEach(id => {
            const element = document.getElementById(id);
            if (!element) {
                return;
            }

            if (preserveAwaiting && id === 'awaiting-file-no') {
                unlockArchiveField(element);
                return;
            }

            unlockArchiveField(element);
            element.value = '';

            // Registry must go back to auto-detect mode on every reset — an admin
            // override from a previous file number must not silently carry over.
            if (id === 'registry' && element.dataset) {
                delete element.dataset.adminUnlocked;
                element.setAttribute('readonly', 'readonly');
                element.classList.add('bg-gray-100', 'cursor-not-allowed');
                const unlockBtn = document.getElementById('registry-admin-unlock-btn');
                if (unlockBtn) {
                    unlockBtn.innerHTML = '<i data-lucide="lock" class="h-3 w-3 pointer-events-none"></i>';
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            }
        });

        if (!preserveIndexed) {
            const indexedBy = document.getElementById('indexed-by');
            if (indexedBy) {
                indexedBy.value = getDefaultIndexerName();
                indexedBy.classList.remove('autofill-locked');
                ensureIndexedByLocked();
            }

            const indexedDate = document.getElementById('indexed-date');
            if (indexedDate) {
                indexedDate.value = getTodayDateValue();
                indexedDate.classList.remove('autofill-locked');
                ensureIndexedDateValue();
            }
        }

        const trackingIdInput = document.getElementById('tracking-id');
        if (trackingIdInput) {
            trackingIdInput.value = '';
            trackingIdInput.style.backgroundColor = '#f9fafb';
        }

        clearCofoFields();

        const hasCofoToggle = document.getElementById('has-cofo-toggle');
        if (hasCofoToggle) {
            hasCofoToggle.checked = false;
        }

        const hiddenHasCofoField = document.getElementById('has-cofo');
        if (hiddenHasCofoField) {
            hiddenHasCofoField.checked = false;
        }

        const cofoDetailsContainer = document.getElementById('cofo-details-container');
        if (cofoDetailsContainer) {
            cofoDetailsContainer.classList.add('hidden');
        }

        setCofoAutofillStatus(null, '');

        // Reset RoFO state
        clearRofoFields();
        const hasRofoToggleClear = document.getElementById('has-rofo-toggle');
        if (hasRofoToggleClear) {
            hasRofoToggleClear.checked = false;
        }
        const rofoDetailsContainerClear = document.getElementById('rofo-details-container');
        if (rofoDetailsContainerClear) {
            rofoDetailsContainerClear.classList.add('hidden');
        }

        // Reset Occupancy Permit state
        clearOccupancyPermitFields();
        const hasOccupancyPermitToggleClear = document.getElementById('has-occupancy-permit-toggle');
        if (hasOccupancyPermitToggleClear) {
            hasOccupancyPermitToggleClear.checked = false;
        }
        const occupancyPermitDetailsContainerClear = document.getElementById('occupancy-permit-details-container');
        if (occupancyPermitDetailsContainerClear) {
            occupancyPermitDetailsContainerClear.classList.add('hidden');
        }

        const physicalRegistrySelect = document.getElementById('physical-registry');
        if (physicalRegistrySelect) {
            applyAutofillLock(['physical-registry'], false);
            physicalRegistrySelect.value = '';
        }

        const landUseSelect = document.getElementById('land-use-type');
        if (landUseSelect) {
            applyAutofillLock(['land-use-type'], false);
            landUseSelect.value = '';
        }

        // Clear SLTR fields
        const sltrSection = document.getElementById('sltr-grouping-details');
        if (sltrSection) sltrSection.classList.add('hidden');
        const subPrefixInput = document.getElementById('sub_prefix');
        const suffixInput = document.getElementById('suffix');
        if (subPrefixInput) subPrefixInput.value = '';
        if (suffixInput) suffixInput.value = '';

        if (!preservePrimary) {
            const autoFilledPrimaryFields = [
                'file-title',
                'plot-number',
                'tp-number',
                'lpkn-no',
                'location',
                'file-number-display',
                'related-file-number-display',
                'tracking-id',
            ];

            autoFilledPrimaryFields.forEach(id => {
                setAutoFilledValue(id, '', { lock: false });
            });

            clearEntityCustomerFields({ silent: true });
        }
    }

    window.__groupingState = groupingState;

    document.addEventListener('DOMContentLoaded', function () {
        resolveIndexedFeedbackElements();
        resolveFileHistoryElements();
        ensureFileHistoryTimelineHandlers();
        updateFileHistoryTimelineButtonState();

        const indexedByField = document.getElementById('indexed-by');
        if (indexedByField) {
            indexedByField.value = getDefaultIndexerName();
            indexedByField.disabled = true;
            ensureIndexedByLocked();
        }

        const indexedDateField = document.getElementById('indexed-date');
        if (indexedDateField && !indexedDateField.value) {
            indexedDateField.value = getTodayDateValue();
        }
        ensureIndexedDateValue();

        const districtSelect = document.getElementById('district-select');
        const customDistrictContainer = document.getElementById('custom-district-container');
        const customDistrictInput = document.getElementById('custom-district-input');
        const lgaSelect = document.getElementById('lga-city');

        loadReferenceData();

        if (districtSelect && customDistrictContainer && customDistrictInput) {
            districtSelect.addEventListener('change', function () {
                if (this.value === 'other') {
                    customDistrictContainer.classList.remove('hidden');
                    customDistrictInput.focus();
                } else {
                    customDistrictContainer.classList.add('hidden');
                    customDistrictInput.value = '';
                }

                if (typeof window.__updateLocationField === 'function') {
                    window.__updateLocationField();
                }
            });

            customDistrictInput.addEventListener('input', function () {
                if (typeof window.__updateLocationField === 'function') {
                    window.__updateLocationField();
                }
            });
        }

        if (lgaSelect) {
            lgaSelect.addEventListener('change', function () {
                if (typeof window.__updateLocationField === 'function') {
                    window.__updateLocationField();
                }
            });
        }

        const hasCofoToggle = document.getElementById('has-cofo-toggle');
        const cofoDetailsContainer = document.getElementById('cofo-details-container');
        const hiddenHasCofoField = document.getElementById('has-cofo');

        if (hasCofoToggle && cofoDetailsContainer) {
            hasCofoToggle.addEventListener('change', function () {
                if (this.checked) {
                    cofoDetailsContainer.classList.remove('hidden');
                    if (hiddenHasCofoField) {
                        hiddenHasCofoField.checked = true;
                    }
                    const instrumentTypeSelect = document.getElementById('cofo-instrument-type');
                    if (instrumentTypeSelect) {
                        instrumentTypeSelect.value = 'Certificate of Occupancy';
                        instrumentTypeSelect.dispatchEvent(new Event('change'));
                    }
                    if (editModeState.isEditing) {
                        unlockCofOFieldsForEditing();
                    }
                } else {
                    cofoDetailsContainer.classList.add('hidden');
                    clearCofoFields();
                    setCofoAutofillStatus(null, '');
                    if (hiddenHasCofoField) {
                        hiddenHasCofoField.checked = false;
                    }
                }
            });
        }

        // RoFO toggle
        const hasRofoToggle = document.getElementById('has-rofo-toggle');
        const rofoDetailsContainer = document.getElementById('rofo-details-container');

        if (hasRofoToggle && rofoDetailsContainer) {
            hasRofoToggle.addEventListener('change', function () {
                if (this.checked) {
                    rofoDetailsContainer.classList.remove('hidden');
                    // Auto-populate file number and RoFO number from main form
                    const mainFileNo = document.getElementById('fileno')?.value || document.getElementById('file-number-display')?.value || '';
                    const rofoFileNo = document.getElementById('rofo-file-number');
                    if (rofoFileNo && mainFileNo) {
                        rofoFileNo.value = mainFileNo;
                    }
                    const rofoNumber = document.getElementById('rofo-number');
                    if (rofoNumber && mainFileNo) {
                        rofoNumber.value = mainFileNo;
                    }
                } else {
                    rofoDetailsContainer.classList.add('hidden');
                    clearRofoFields();
                }
            });
        }

        // Occupancy Permit toggle
        const hasOccupancyPermitToggle = document.getElementById('has-occupancy-permit-toggle');
        const occupancyPermitDetailsContainer = document.getElementById('occupancy-permit-details-container');

        if (hasOccupancyPermitToggle && occupancyPermitDetailsContainer) {
            hasOccupancyPermitToggle.addEventListener('change', function () {
                if (this.checked) {
                    occupancyPermitDetailsContainer.classList.remove('hidden');
                    // Auto-populate file number from main form
                    const mainFileNo = document.getElementById('fileno')?.value || document.getElementById('file-number-display')?.value || '';
                    const opFileNo = document.getElementById('occupancy-permit-file-number');
                    if (opFileNo && mainFileNo) {
                        opFileNo.value = mainFileNo;
                    }
                } else {
                    occupancyPermitDetailsContainer.classList.add('hidden');
                    clearOccupancyPermitFields();
                }
            });
        }

        const cofoInstrumentType = document.getElementById('cofo-instrument-type');
        const cofoTransactionDetails = document.getElementById('cofo-transaction-details');
        const cofoFirstPartyLabel = document.getElementById('cofo-first-party-label');
        const cofoSecondPartyLabel = document.getElementById('cofo-second-party-label');
        const cofoFirstPartyInput = document.getElementById('cofo-first-party');
        const cofoTypeWrapper = document.getElementById('cofo-type-wrapper');
        const cofoTypeSelect = document.getElementById('cofo-type');

        function isCertificateOfOccupancyType(selectedType) {
            return /CERTIFICATE OF OCCUPANCY/i.test(String(selectedType || ''));
        }

        if (cofoInstrumentType && cofoTransactionDetails) {
            cofoInstrumentType.addEventListener('change', function () {
                if (isAutofillLocked(this)) {
                    const lockedValue = this.dataset.autofillLockedValue;
                    if (typeof lockedValue !== 'undefined') {
                        this.value = lockedValue;
                    }
                    return;
                }

                const selectedType = this.value;

                if (cofoTypeWrapper) {
                    if (isCertificateOfOccupancyType(selectedType)) {
                        cofoTypeWrapper.classList.remove('hidden');
                    } else {
                        cofoTypeWrapper.classList.add('hidden');
                        if (cofoTypeSelect) {
                            cofoTypeSelect.value = '';
                        }
                    }
                }

                if (selectedType) {
                    cofoTransactionDetails.classList.remove('hidden');

                    const partyLabels = getCofoPartyLabels(selectedType);
                    if (cofoFirstPartyLabel) cofoFirstPartyLabel.textContent = partyLabels.first;
                    if (cofoSecondPartyLabel) cofoSecondPartyLabel.textContent = partyLabels.second;

                    const govTypes = ['Certificate of Occupancy', 'ST Certificate of Occupancy', 'SLTR Certificate of Occupancy', 'Customary Right of Occupancy'];
                    if (govTypes.includes(selectedType) && cofoFirstPartyInput) {
                        cofoFirstPartyInput.value = 'KANO STATE GOVERNMENT';
                        cofoFirstPartyInput.classList.add('bg-gray-100');
                        cofoFirstPartyInput.readOnly = true;
                    } else if (cofoFirstPartyInput) {
                        cofoFirstPartyInput.value = '';
                        cofoFirstPartyInput.classList.remove('bg-gray-100');
                        cofoFirstPartyInput.readOnly = false;
                    }
                } else {
                    cofoTransactionDetails.classList.add('hidden');
                    clearCofoTransactionDetails();
                }
            });
        }

        const cofoSerialNoInput = document.getElementById('cofo-serial-no');
        const cofoPageNoInput = document.getElementById('cofo-page-no');

        if (cofoSerialNoInput && cofoPageNoInput) {
            cofoSerialNoInput.addEventListener('input', function () {
                if (isAutofillLocked(this)) {
                    const lockedValue = this.dataset.autofillLockedValue;
                    if (typeof lockedValue !== 'undefined') {
                        this.value = lockedValue;
                    }
                    return;
                }

                cofoPageNoInput.value = this.value;
            });

            cofoSerialNoInput.addEventListener('focus', function () {
                if (isAutofillLocked(this)) {
                    this.blur();
                }
            });

            if (cofoSerialNoInput.value) {
                cofoPageNoInput.value = cofoSerialNoInput.value;
            }
        }

        // ── Occupancy Permit issued by a Local Government ─────────────────────────────
        // Some conversion and direct-allocation files hold permits granted by an LGA rather
        // than by the Kano State Government. An LGA registers nothing in the state deeds
        // registry, so such a permit has no serial, page, volume, deeds date or deeds time —
        // only which Local Government granted it. Selecting either LGA OP Type therefore swaps the
        // read-only KANO STATE GOVERNMENT grantor for a picker of the 44 authorities and fixes
        // the registration particulars at 0/0/0 with the dates left blank.
        const opTypeSelect = document.getElementById('occupancy-permit-op-type');
        const opLgaGrantorSelect = document.getElementById('occupancy-permit-lga-grantor');
        const opGrantorInput = document.getElementById('occupancy-permit-grantor');
        const opLgaNote = document.getElementById('occupancy-permit-lga-note');
        const opCategorySelect = document.getElementById('occupancy-permit-op-category');
        const opCategoryGroup = document.getElementById('occupancy-permit-op-category-group');
        const opOldOpNote = document.getElementById('occupancy-permit-old-op-note');

        // A permit issued by a Local Government, which registers nothing in the State
        // deeds registry: no registration particulars, no deeds date, no time.
        //
        // A letter issued by a Local Government is NOT here: that is a Plot Allocation
        // Letter with Type 'LGA', captured on the transaction card, not a kind of permit.
        const LGA_OP_TYPES = ['LGA'];

        function isOccupancyPermitLgaType() {
            // Tolerant of the "OP " prefix the sibling screens put on their stored values.
            const normalized = String(opTypeSelect?.value || '').trim().toUpperCase().replace(/^OP\s+/, '');
            return LGA_OP_TYPES.includes(normalized);
        }

        function isOldOccupancyPermitCategory() {
            return String(opCategorySelect?.value || '').trim().toUpperCase() === 'OLD';
        }

        /**
         * Category (Old / New) is asked for EVERY permit Type, LGA included, so the field
         * never hides — only its note does.
         *
         * The registration particulars are already optional on this page (no `required`
         * attribute, and applyOccupancyPermitLgaRules() sets required=false either way),
         * so Old has nothing to relax here — the note simply states the rule. It bites on
         * the transaction card, which does block a save on a missing Serial/Vol No.
         */
        function applyOccupancyPermitCategoryRules() {
            if (opCategoryGroup) opCategoryGroup.classList.remove('hidden');
            if (opOldOpNote) opOldOpNote.classList.toggle('hidden', !isOldOccupancyPermitCategory());
        }

        function applyOccupancyPermitLgaRules() {
            applyOccupancyPermitCategoryRules();
            const isLga = isOccupancyPermitLgaType();
            const regFieldIds = [
                'occupancy-permit-serial-no',
                'occupancy-permit-page-no',
                'occupancy-permit-vol-no',
            ];
            const dateFieldIds = [
                'occupancy-permit-deeds-date',
                'occupancy-permit-deeds-time',
            ];

            if (opLgaNote) opLgaNote.classList.toggle('hidden', !isLga);
            if (opLgaGrantorSelect) {
                opLgaGrantorSelect.classList.toggle('hidden', !isLga);
                opLgaGrantorSelect.required = isLga;
            }
            if (opGrantorInput) {
                opGrantorInput.classList.toggle('hidden', isLga);
            }

            regFieldIds.concat(dateFieldIds).forEach((id) => {
                const field = document.getElementById(id);
                if (!field) return;
                // Page No is mirrored from Serial No, so it is read-only either way.
                if (id !== 'occupancy-permit-page-no') {
                    field.readOnly = isLga;
                }
                field.required = false;
                field.classList.toggle('bg-gray-100', isLga);
                field.classList.toggle('cursor-not-allowed', isLga);
            });

            if (isLga) {
                regFieldIds.forEach((id) => {
                    const field = document.getElementById(id);
                    if (field) field.value = '0';
                });
                dateFieldIds.forEach((id) => {
                    const field = document.getElementById(id);
                    if (field) field.value = '';
                });
                // The State is not one of the 44 — leave the picker unselected so the officer
                // has to name the Local Government, and mirror whatever is chosen into the
                // input, which is the field this form actually posts.
                if (opGrantorInput && opLgaGrantorSelect) {
                    const chosen = opLgaGrantorSelect.value || '';
                    opGrantorInput.value = chosen;
                }
                return;
            }

            // Switched away from LGA: drop the 0/0/0 stamp and hand the grantor back to the State.
            const stamped = regFieldIds.every((id) => (document.getElementById(id)?.value || '') === '0');
            if (stamped) {
                regFieldIds.forEach((id) => {
                    const field = document.getElementById(id);
                    if (field) field.value = '';
                });
            }
            if (opGrantorInput && opLgaGrantorSelect
                && Array.from(opLgaGrantorSelect.options).some((o) => o.value && o.value === opGrantorInput.value)) {
                opGrantorInput.value = 'KANO STATE GOVERNMENT';
            }
        }

        if (opTypeSelect) {
            opTypeSelect.addEventListener('change', applyOccupancyPermitLgaRules);
        if (opCategorySelect) {
            opCategorySelect.addEventListener('change', applyOccupancyPermitCategoryRules);
        }
        }
        if (opLgaGrantorSelect) {
            opLgaGrantorSelect.addEventListener('change', function () {
                if (opGrantorInput) opGrantorInput.value = this.value || '';
            });
        }
        // Reachable from the record-load and clear paths, which sit outside this closure.
        window.applyOccupancyPermitLgaRules = applyOccupancyPermitLgaRules;
        // Runs once so a record loaded with an LGA OP Type opens in the right shape.
        applyOccupancyPermitLgaRules();

        // Occupancy Permit Page No always mirrors Serial No (Page No is read-only).
        const occupancyPermitSerialNoInput = document.getElementById('occupancy-permit-serial-no');
        const occupancyPermitPageNoInput = document.getElementById('occupancy-permit-page-no');

        if (occupancyPermitSerialNoInput && occupancyPermitPageNoInput) {
            occupancyPermitSerialNoInput.addEventListener('input', function () {
                occupancyPermitPageNoInput.value = this.value;
            });

            if (occupancyPermitSerialNoInput.value) {
                occupancyPermitPageNoInput.value = occupancyPermitSerialNoInput.value;
            }
        }

        const refreshArchiveDetailsBtn = document.getElementById('refresh-archive-details-btn');
        if (refreshArchiveDetailsBtn) {
            refreshArchiveDetailsBtn.addEventListener('click', function () {
                const fileNumberInput = document.getElementById('fileno');
                const archiveFileNoInput = document.getElementById('archive-file-no');
                const awaitingField = document.getElementById('awaiting-file-no');

                const fileNumber = fileNumberInput?.value || archiveFileNoInput?.value || awaitingField?.value;

                if (fileNumber) {
                    this.disabled = true;
                    this.innerHTML = '<i data-lucide="loader-2" class="h-3 w-3 animate-spin"></i>';

                    autoFillArchiveDetailsFromAPI(fileNumber).finally(() => {
                        setTimeout(() => {
                            this.disabled = false;
                            this.innerHTML = '<i data-lucide="refresh-cw" class="h-3 w-3"></i>';
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }, 1000);
                    });
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Enter Awaiting File No',
                            text: 'Type the awaiting file number or select a file before refreshing.',
                            icon: 'warning',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                        });
                    }
                }
            });
        }

        // Admin-only escape hatch: unlock the auto-detected Registry field for a
        // one-off manual override. Auto-fill (detectRegistryForFileNumber) checks
        // dataset.adminUnlocked and backs off once this has been clicked, so the
        // override sticks until the dialog is reset.
        const registryAdminUnlockBtn = document.getElementById('registry-admin-unlock-btn');
        if (registryAdminUnlockBtn) {
            registryAdminUnlockBtn.addEventListener('click', function () {
                const registryField = document.getElementById('registry');
                if (!registryField) {
                    return;
                }
                const nowUnlocked = registryField.dataset.adminUnlocked !== 'true';
                registryField.dataset.adminUnlocked = nowUnlocked ? 'true' : 'false';
                registryField.readOnly = !nowUnlocked;
                if (nowUnlocked) {
                    registryField.removeAttribute('readonly');
                    registryField.classList.remove('bg-gray-100', 'cursor-not-allowed');
                    registryField.focus();
                } else {
                    registryField.setAttribute('readonly', 'readonly');
                    registryField.classList.add('bg-gray-100', 'cursor-not-allowed');
                }
                this.innerHTML = nowUnlocked
                    ? '<i data-lucide="unlock" class="h-3 w-3 pointer-events-none"></i>'
                    : '<i data-lucide="lock" class="h-3 w-3 pointer-events-none"></i>';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        }

        loadAutoAssignmentPreview();

        const refreshAssignmentBtn = document.getElementById('refresh-assignment-btn');
        if (refreshAssignmentBtn) {
            refreshAssignmentBtn.addEventListener('click', function () {
                this.disabled = true;
                this.innerHTML = '<i data-lucide="loader-2" class="h-3 w-3 animate-spin"></i>';

                loadAutoAssignmentPreview();

                setTimeout(() => {
                    this.disabled = false;
                    this.innerHTML = '<i data-lucide="refresh-cw" class="h-3 w-3"></i>';
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }, 1000);
            });
        }

        initializeFileIndexingForm();

        if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
            console.warn('jQuery not found - Select2 may not work properly');
        } else {
            console.log('jQuery loaded successfully');
        }

        if (typeof $.fn.select2 === 'undefined') {
            console.warn('Select2 not found - batch selection will use fallback');
        } else {
            console.log('Select2 loaded successfully');
        }
    });

    function refreshAvailableBatches() {
        console.log('Refreshing batch availability preview...');
        loadAutoAssignmentPreview();
    }

    window.refreshAvailableBatches = refreshAvailableBatches;

    function loadAutoAssignmentPreview() {
        const statusEl = document.getElementById('auto-assignment-status');
        const hiddenBatch = document.getElementById('batch-no');
        const hiddenSerial = document.getElementById('serial-no');
        const hiddenShelf = document.getElementById('shelf-location');
        const hiddenShelfId = document.getElementById('shelf_label_id');
        const hiddenBatchId = document.getElementById('batch_id');

        if (!statusEl) {
            return;
        }

        if (hiddenBatch) hiddenBatch.value = '';
        if (hiddenSerial) hiddenSerial.value = '';
        if (hiddenShelf) hiddenShelf.value = '';
        if (hiddenShelfId) hiddenShelfId.value = '';
        if (hiddenBatchId) hiddenBatchId.value = '';

        statusEl.textContent = 'Checking current batch availability...';

        fetch('/fileindexing/get-current-batch-status')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.current_batch) {
                    const batch = data.current_batch;
                    const batchNo = batch.batch_no;
                    const currentCount = batch.current_count;
                    const nextSerial = currentCount + 1;
                    const remaining = Math.max(0, 100 - currentCount);
                    const shelfLocation = batch.shelf_location || 'Auto-assigned';

                    statusEl.innerHTML = `
                        <span class="block">Current batch: <strong>Batch ${batchNo}</strong></span>
                        <span class="block">Next serial: <strong>#${nextSerial}</strong> (${remaining} slots remaining)</span>
                        <span class="block">Shelf location: <strong>${shelfLocation}</strong></span>
                        <span class="block text-xs text-blue-700 mt-1">Assignment confirmed when you save the file.</span>
                    `;

                    if (hiddenBatch) hiddenBatch.value = batchNo;
                    if (hiddenSerial) hiddenSerial.value = nextSerial;
                    if (hiddenShelf) hiddenShelf.value = shelfLocation;
                    if (hiddenShelfId) hiddenShelfId.value = batch.shelf_label_id || '';
                    if (hiddenBatchId) hiddenBatchId.value = batch.batch_id || batchNo;
                } else if (data.success && data.will_create_new) {
                    const nextBatchNo = data.next_batch_no;
                    const nextShelfLocation = data.next_shelf_location;

                    statusEl.innerHTML = `
                        <span class="block font-semibold text-green-700">Will create new batch: <strong>Batch ${nextBatchNo}</strong></span>
                        <span class="block">First serial: <strong>#1</strong> (99 slots remaining)</span>
                        <span class="block">Shelf location: <strong>${nextShelfLocation}</strong></span>
                        <span class="block text-xs text-green-600 mt-1">New batch will be initialized when you save.</span>
                    `;

                    if (hiddenBatch) hiddenBatch.value = nextBatchNo;
                    if (hiddenSerial) hiddenSerial.value = 1;
                    if (hiddenShelf) hiddenShelf.value = nextShelfLocation;
                    if (hiddenShelfId) hiddenShelfId.value = data.next_shelf_label_id || '';
                    if (hiddenBatchId) hiddenBatchId.value = data.next_batch_id || nextBatchNo;
                } else {
                    statusEl.innerHTML = `
                        <span class="block font-semibold text-amber-700">No current batch information available</span>
                        <span class="block text-xs text-amber-600">A new batch will be created automatically when you save.</span>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading auto assignment preview:', error);
                statusEl.innerHTML = `
                    <span class="block font-semibold text-red-700">Unable to check batch availability</span>
                    <span class="block text-xs text-red-600">Please try refreshing or contact support if the problem persists.</span>
                `;
            });
    }

    function refreshAutoAssignment() {
        loadAutoAssignmentPreview();
    }

    window.loadAutoAssignmentPreview = loadAutoAssignmentPreview;
    window.refreshAutoAssignment = refreshAutoAssignment;

    async function autoFillArchiveDetailsFromAPI(fileNumber) {
        if (editModeState.isEditing) {
            console.info('Skipping archive autofill while editing an existing record.');
            return null;
        }

        const awaitingField = document.getElementById('awaiting-file-no');
        const registryBatchField = document.getElementById('registry-batch-no');
        const mdcField = document.getElementById('mdc-batch-no');
        const registryField = document.getElementById('registry');
        const shelfField = document.getElementById('shelf-rack-no');
        const sysBatchField = document.getElementById('sys-batch-no');
        const serialField = document.getElementById('serial-no');
        const indexedByField = document.getElementById('indexed-by');
        const indexedDateField = document.getElementById('indexed-date');
        const loadingIndicator = document.getElementById('grouping-loading-indicator');

        const archiveFields = [
            awaitingField,
            registryBatchField,
            mdcField,
            registryField,
            shelfField,
            sysBatchField,
            serialField,
        ].filter(Boolean);

        if (loadingIndicator) {
            loadingIndicator.classList.remove('hidden');
        }

        const placeholders = new Map();
        archiveFields.forEach(field => {
            placeholders.set(field, field.placeholder || '');
            field.placeholder = 'Loading...';
            field.classList.remove('success-border', 'error-border');
        });

        // Strip an "AND EXTENSION" suffix for the grouping/tracking lookup only.
        // Example: "RES-2026-10 AND EXTENSION" looks up the base "RES-2026-10",
        // but the full value (with the suffix) is re-attached below so it is saved intact.
        const EXTENSION_SUFFIX_RE = /\s*AND\s+EXTENSION\s*$/i;
        const stripExtension = (val) => (val || '').replace(EXTENSION_SUFFIX_RE, '').trim();
        const hasExtensionSuffix = EXTENSION_SUFFIX_RE.test((fileNumber || '').trim());

        const awaitingValue = stripExtension((awaitingField?.value?.trim() || '').replace(/\(\s*T\s*\)\s*$/i, '').trim());
        const lookupCandidate = stripExtension((fileNumber || '').trim().replace(/\(\s*T\s*\)\s*$/i, '').trim());
        const lookupValue = lookupCandidate || awaitingValue;

        if (!lookupValue) {
            console.warn('No file number or awaiting file number provided for grouping lookup');
            archiveFields.forEach(field => {
                field.placeholder = placeholders.get(field) || '';
            });
            if (loadingIndicator) {
                loadingIndicator.classList.add('hidden');
            }
            return null;
        }

        clearArchiveFields({ preserveAwaiting: true, preservePrimary: true });
        resetGroupingState();
        updateCreateButtonState();

        try {
            console.log('Fetching grouping record for awaiting file number:', lookupValue);
            const registry = document.getElementById('general-registry')?.value || '';
            const response = await fetch(`/api/grouping/awaiting/${encodeURIComponent(lookupValue)}?registry=${encodeURIComponent(registry)}`);
            let payload = {};

            try {
                payload = await response.json();
            } catch (jsonError) {
                payload = { success: false, message: 'Unable to decode grouping API response' };
            }

            if (!response.ok || !payload.success) {
                const error = new Error(payload?.message || 'Grouping record not found');
                error.code = response.status === 404 ? 'NOT_FOUND' : 'API_ERROR';
                throw error;
            }

            if (editModeState.isEditing) {
                console.info('Edit mode activated while loading grouping data; skipping autofill response.');
                return null;
            }

            const groupingRecord = payload.data || payload;
            setGroupingRecord(groupingRecord);

            const fileNumberInput = document.getElementById('fileno');
            const fileNumberDisplay = document.getElementById('file-number-display');
            const archiveFileNoInput = document.getElementById('archive-file-no');

            const currentFileno = (fileNumberInput?.value || '').trim();
            const hasTempSuffix = /\(\s*T\s*\)\s*$/i.test(currentFileno);
            const normalizedAwaiting = groupingState.normalizedAwaiting;
            const normalizedFileNumber = normalizeFileno(stripExtension(currentFileno.replace(/\(\s*T\s*\)\s*$/i, '').trim()));
            const awaitingValueResolved = groupingRecord.awaiting_fileno ?? lookupValue;

            if (!fileNumberInput || !currentFileno || normalizedFileNumber !== normalizedAwaiting) {
                // Re-attach the suffixes the user typed so the saved file number stays intact:
                // the grouping record returns the base awaiting_fileno, but we must persist the
                // full "… AND EXTENSION" (and/or "(T)") value the user entered.
                let valueToSet = awaitingValueResolved.replace(/\(\s*T\s*\)\s*$/i, '').replace(EXTENSION_SUFFIX_RE, '').trim();
                if (hasExtensionSuffix) {
                    valueToSet += ' AND EXTENSION';
                }
                if (hasTempSuffix) {
                    valueToSet += '(T)';
                }
                if (fileNumberInput) {
                    fileNumberInput.value = valueToSet;
                }
                if (fileNumberDisplay) {
                    setAutoFilledValue(fileNumberDisplay, valueToSet);
                }
            }

            if (archiveFileNoInput) {
                setAutoFilledValue(archiveFileNoInput, awaitingValueResolved);
            }

            await checkFileAlreadyIndexed(awaitingValueResolved, { source: 'grouping-autofill' });
            if (editModeState.isEditing) {
                return groupingRecord;
            }

            lockArchiveField(awaitingField, awaitingValueResolved);

            const registryBatchSource = groupingRecord.registry_batch_no;
            const registryBatchValue = registryBatchSource === null || registryBatchSource === undefined
                ? ''
                : String(registryBatchSource).trim();
            lockArchiveField(registryBatchField, registryBatchValue);

            const mdcBatchSource = groupingRecord.mdc_batch_no
                ?? groupingRecord.mdcBatchNo
                ?? groupingRecord.batch_no
                ?? groupingRecord.batchNo
                ?? null;
            const mdcBatchValue = mdcBatchSource === null || mdcBatchSource === undefined
                ? ''
                : String(mdcBatchSource).trim();
            lockArchiveField(mdcField, mdcBatchValue);
            lockArchiveField(sysBatchField, groupingRecord.sys_batch_no ?? '');

            // The Registry field must always be derived from the file number's
            // prefix + year via config/file_ranges.php (see RegistryDetector), not
            // from the grouping record's own (often stale/manually-typed) registry
            // value or a landuse guess — that's what corrupted historical data.
            let registryValue = await detectRegistryForFileNumber(awaitingValueResolved || lookupValue);
            if (registryValue === null) {
                // Not part of the Registry 1/2/3 (Lands/SIT) family — fall back to
                // whatever the grouping record itself carries, if anything.
                registryValue = groupingRecord.registry ?? groupingRecord.registry_name ?? groupingRecord.registry_label ?? '';
            }
            applyRegistryFieldValue(registryField, registryValue);

            lockArchiveField(shelfField, groupingRecord.shelf_rack ?? '');
            lockArchiveField(serialField, groupingRecord.number ?? groupingRecord.serial_no ?? '');

            if (indexedByField) {
                const rawIndexer = groupingRecord.indexed_by ?? '';
                const normalizedIndexer = typeof rawIndexer === 'string' ? rawIndexer.trim() : String(rawIndexer || '').trim();

                if (normalizedIndexer) {
                    setAutoFilledValue(indexedByField, normalizedIndexer);
                } else {
                    const fallbackIndexer = getDefaultIndexerName();
                    if (fallbackIndexer) {
                        setAutoFilledValue(indexedByField, fallbackIndexer, { lock: false });
                    } else {
                        setAutoFilledValue(indexedByField, '', { lock: false });
                    }
                }

                ensureIndexedByLocked();
            }

            if (indexedDateField) {
                const rawDate = groupingRecord.date_index || groupingRecord.date || '';
                const normalizedDate = rawDate ? String(rawDate).substring(0, 10) : '';

                if (normalizedDate) {
                    setAutoFilledValue(indexedDateField, normalizedDate);
                } else {
                    setAutoFilledValue(indexedDateField, getTodayDateValue(), { lock: false });
                }

                ensureIndexedDateValue();
            }

            const trackingIdInput = document.getElementById('tracking-id');
            if (trackingIdInput) {
                if (groupingRecord.tracking_id) {
                    setAutoFilledValue(trackingIdInput, groupingRecord.tracking_id);
                } else if (trackingIdInput.id) {
                    applyAutofillLock(['tracking-id'], false);
                }
            }

            const physicalRegistrySelect = document.getElementById('physical-registry');
            if (physicalRegistrySelect) {
                const physicalRegistryValue = groupingRecord.physical_registry ?? '';
                setAutoFilledValue(physicalRegistrySelect, physicalRegistryValue);
            }

            if (groupingRecord.landuse) {
                populateLandUseFromRecord(groupingRecord.landuse);
            }

            await autoFillCofODetailsFromAPI(groupingRecord.awaiting_fileno ?? lookupValue);

            groupingState.mismatch = false;
            updateCreateButtonState();

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Grouping Match Found',
                    text: `Archive details loaded for ${groupingRecord.awaiting_fileno}`,
                    icon: 'success',
                    timer: 2200,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                });
            }

            return groupingRecord;
        } catch (error) {
            console.error('Error fetching archive details from API:', error);

            clearArchiveFields({ preserveAwaiting: true, preservePrimary: true });
            resetGroupingState();
            updateCreateButtonState();

            // Even without a grouping match, still try to auto-fill Registry
            // directly from the file number's prefix + year — it doesn't depend
            // on the grouping table lookup succeeding.
            const fallbackRegistry = await detectRegistryForFileNumber(fileNumber);
            if (fallbackRegistry !== null) {
                applyRegistryFieldValue(registryField, String(fallbackRegistry));
            }

            if (awaitingField) {
                awaitingField.classList.add('error-border');
                setTimeout(() => awaitingField.classList.remove('error-border'), 2500);
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: error.code === 'NOT_FOUND' ? 'Awaiting File Not Found' : 'Grouping Lookup Failed',
                    text: error.message || 'Unable to retrieve archive details. Please verify the awaiting file number.',
                    icon: error.code === 'NOT_FOUND' ? 'info' : 'error',
                    timer: 2500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                });
            }

            return null;
        } finally {
            archiveFields.forEach(field => {
                field.placeholder = placeholders.get(field) || '';
            });

            if (loadingIndicator) {
                loadingIndicator.classList.add('hidden');
            }
        }
    }

    window.autoFillArchiveDetailsFromAPI = autoFillArchiveDetailsFromAPI;

    function getCofoPartyLabels(instrumentType) {
        const partyLabels = {
            'Power of Attorney': { first: 'Grantor', second: 'Grantee' },
            'Irrevocable Power of Attorney': { first: 'Grantor', second: 'Grantee' },
            'Deed of Assignment': { first: 'Assignor', second: 'Assignee' },
            'ST Assignment': { first: 'Assignor', second: 'Assignee' },
            'Deed of Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
            'Tripartite Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
            'Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
            'ST Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
            'SLTR Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
            'Customary Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
            'Deed of Transfer': { first: 'Transferor', second: 'Transferee' },
            'Deed of Gift': { first: 'Donor', second: 'Donee' },
            'Deed of Lease': { first: 'Lessor', second: 'Lessee' },
            'Deed of Sub Lease': { first: 'Lessor', second: 'Lessee' },
            'Deed of Sub Under Lease': { first: 'Lessor', second: 'Lessee' },
            'Indenture of Lease': { first: 'Lessor', second: 'Lessee' },
            'Tenancy Agreement': { first: 'Landlord', second: 'Tenant' },
            'Deed of Release': { first: 'Releasor', second: 'Releasee' },
            'Deed of Surrender': { first: 'Surrenderor', second: 'Surrenderee' },
            'Letter of Administration': { first: 'Administrator', second: 'Beneficiary' },
            'Certificate of Purchase': { first: 'Vendor', second: 'Purchaser' },
        };

        return partyLabels[instrumentType] || { first: 'Grantor', second: 'Grantee' };
    }

    window.getCofoPartyLabels = getCofoPartyLabels;

    function clearCofoTransactionDetails() {
        const transactionFields = [
            'cofo-first-party',
            'cofo-second-party',
        ];

        transactionFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = '';
            }
        });
    }

    window.clearCofoTransactionDetails = clearCofoTransactionDetails;

    function clearRofoFields() {
        const rofoFields = [
            'rofo-date',
            'rofo-file-number',
            'rofo-grantee',
        ];

        rofoFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = '';
            }
        });

        // Restore default grantor (readonly field)
        const rofoGrantor = document.getElementById('rofo-grantor');
        if (rofoGrantor) {
            rofoGrantor.value = 'KANO STATE GOVERNMENT';
        }

        // Restore RoFO number to empty (will be re-populated on next toggle)
        const rofoNumber = document.getElementById('rofo-number');
        if (rofoNumber) {
            rofoNumber.value = '';
        }

        // Instrument type is locked to Right of Occupancy
        const rofoInstrumentType = document.getElementById('rofo-instrument-type');
        if (rofoInstrumentType) {
            rofoInstrumentType.value = 'Right of Occupancy';
        }

        const rofoLandUse = document.getElementById('rofo-land-use');
        if (rofoLandUse) {
            rofoLandUse.value = '';
        }
    }

    function clearOccupancyPermitFields() {
        const occupancyPermitFields = [
            'occupancy-permit-op-type',
            'occupancy-permit-op-category',
            'occupancy-permit-op-serial-number',
            'occupancy-permit-date',
            'occupancy-permit-file-number',
            'occupancy-permit-grantee',
            'occupancy-permit-serial-no',
            'occupancy-permit-page-no',
            'occupancy-permit-vol-no',
            'occupancy-permit-deeds-time',
            'occupancy-permit-deeds-date',
        ];

        occupancyPermitFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = '';
            }
        });

        // Restore default grantor (readonly field)
        const opGrantor = document.getElementById('occupancy-permit-grantor');
        if (opGrantor) {
            opGrantor.value = 'KANO STATE GOVERNMENT';
        }

        // Put the LGA picker back out of the way and unlock the registration particulars,
        // which clearing the OP Type alone would otherwise leave greyed out.
        const opLgaGrantorReset = document.getElementById('occupancy-permit-lga-grantor');
        if (opLgaGrantorReset) {
            opLgaGrantorReset.value = '';
        }
        if (typeof window.applyOccupancyPermitLgaRules === 'function') {
            window.applyOccupancyPermitLgaRules();
        }

        // Instrument type is locked to Occupancy Permit
        const opInstrumentType = document.getElementById('occupancy-permit-instrument-type');
        if (opInstrumentType) {
            opInstrumentType.value = 'Occupancy Permit';
        }

        const opLandUse = document.getElementById('occupancy-permit-land-use');
        if (opLandUse) {
            opLandUse.value = '';
        }
    }

    function clearCofoFields() {
        const cofoFields = [
            'cofo-date',
            'cofo-serial-no',
            'cofo-page-no',
            'cofo-vol-no',
            'cofo-deeds-time',
            'cofo-deeds-date',
            'cofo-type',
        ];

        cofoFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = '';
            }
        });

        const instrumentTypeSelect = document.getElementById('cofo-instrument-type');
        if (instrumentTypeSelect) {
            instrumentTypeSelect.value = 'Certificate of Occupancy';
        }

        const cofoFirstPartyInput = document.getElementById('cofo-first-party');
        if (cofoFirstPartyInput) {
            cofoFirstPartyInput.readOnly = false;
            cofoFirstPartyInput.classList.remove('bg-gray-100');
        }

        clearCofoTransactionDetails();

        const cofoTransactionDetails = document.getElementById('cofo-transaction-details');
        if (cofoTransactionDetails) {
            cofoTransactionDetails.classList.add('hidden');
        }

        applyAutofillLock([
            'cofo-instrument-type',
            'cofo-date',
            'cofo-serial-no',
            'cofo-page-no',
            'cofo-vol-no',
            'cofo-deeds-time',
            'cofo-deeds-date',
            'cofo-first-party',
            'cofo-second-party',
        ], false);

        autofillState.cofo = false;
    }

    window.clearCofoFields = clearCofoFields;

    function initializeFileIndexingForm() {
        const form = document.getElementById('new-file-form');
        const createBtn = document.getElementById('create-file-btn');
        const cancelBtn = document.getElementById('cancel-btn');
        const refreshBtn = document.getElementById('refresh-form-btn');
        const closeBtn = document.getElementById('close-dialog-btn');
        const overlay = document.getElementById('new-file-dialog-overlay');

        const propIdField = document.getElementById('prop-id-field');
        if (propIdField && window.editingRecord && window.editingRecord.prop_id) {
            propIdField.value = String(window.editingRecord.prop_id).trim();
        }

        updateCreateButtonState();
        initializePropertyAddressLinking();

        if (createBtn) {
            createBtn.addEventListener('click', function (e) {
                e.preventDefault();
                submitFileIndexingForm();
            });
        }

        // Always intercept native form submission. Without this, pressing Enter
        // in any field triggers a synchronous POST to the page URL, which both
        // bypasses the AJAX flow and leaves the browser on the POST request —
        // refreshing then shows the "Confirm Form Resubmission" prompt and can
        // insert a duplicate record. Routing every submit through the AJAX
        // handler (which is itself re-entrancy guarded) prevents that entirely.
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitFileIndexingForm();
            });
        }

        const awaitingField = document.getElementById('awaiting-file-no');
        if (awaitingField) {
            awaitingField.addEventListener('input', function () {
                if (awaitingField.dataset?.locked === 'true') {
                    awaitingField.value = awaitingField.dataset.lockedValue || awaitingField.value;
                    return;
                }

                resetGroupingState();
                updateCreateButtonState();
            });

            const triggerAwaitingLookup = () => {
                if (awaitingField.dataset?.locked === 'true') {
                    return;
                }

                const pendingValue = awaitingField.value?.trim();
                if (pendingValue && pendingValue.length >= 3) {
                    autoFillArchiveDetailsFromAPI(pendingValue);
                }
            };

            awaitingField.addEventListener('blur', triggerAwaitingLookup);
            awaitingField.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    triggerAwaitingLookup();
                }
            });
        }

        [cancelBtn, closeBtn].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', function () {
                    closeFileIndexingDialog();
                });
            }
        });

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Clear Form?',
                        text: 'This will reset all fields in the form. Continue?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Clear',
                        cancelButtonText: 'No, Keep',
                        confirmButtonColor: '#d97706',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            resetFileIndexingForm();
                            if (typeof showToast === 'function') {
                                showToast('Form Cleared', 'All fields have been reset', 'success');
                            }
                        }
                    });
                } else if (confirm('Are you sure you want to clear the form?')) {
                    resetFileIndexingForm();
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeFileIndexingDialog();
                }
            });
        }
    }

    async function submitFileIndexingForm() {
        const form = document.getElementById('new-file-form');
        if (!form) return;

        // Block re-entry while a save is already in progress so a second
        // click / Enter press cannot fire a duplicate request.
        if (isSubmittingFileIndex) return;

        // File Title required validation (covers file_title[] inputs and legacy #file-title)
        {
            const titleInputs = form.querySelectorAll('input[name="file_title[]"]');
            let firstEmptyTitle = null;
            titleInputs.forEach(input => {
                if (!firstEmptyTitle && !(input.value || '').trim()) {
                    firstEmptyTitle = input;
                }
            });
            if (!titleInputs.length) {
                const singleTitle = document.getElementById('file-title');
                if (singleTitle && !(singleTitle.value || '').trim()) {
                    firstEmptyTitle = singleTitle;
                }
            }
            if (firstEmptyTitle) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'File Title Required',
                        text: 'Please enter a File Title before saving.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#f59e0b'
                    }).then(() => {
                        firstEmptyTitle.focus();
                        try { firstEmptyTitle.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) { }
                    });
                } else {
                    alert('Please enter a File Title before saving.');
                    firstEmptyTitle.focus();
                }
                updateCreateButtonState();
                return;
            }
        }

        // Gender required validation (per-file selects in the Personal Details card)
        {
            const genderSelects = form.querySelectorAll('select.gender-select, select[name^="gender["]');
            let firstEmptyGender = null;
            genderSelects.forEach(sel => {
                if (!firstEmptyGender && !(sel.value || '').trim()) {
                    firstEmptyGender = sel;
                }
            });
            if (firstEmptyGender) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Gender Required',
                        text: 'Please select a Gender (Male, Female, Corporate or Joint) before saving.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#f59e0b'
                    }).then(() => {
                        firstEmptyGender.focus();
                        try { firstEmptyGender.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) { }
                    });
                } else {
                    alert('Please select a Gender before saving.');
                    firstEmptyGender.focus();
                }
                updateCreateButtonState();
                return;
            }
        }

        // Root of Title required validation (Holders card). The server enforces the
        // same rule; this is here so the officer is told before the round trip.
        {
            const rootInput = document.getElementById('root-of-title');
            if (rootInput && rootOfTitleApplies() && !(rootInput.value || '').trim()) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Root of Title Required',
                        text: 'Please enter the Root of Title before saving.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#f59e0b'
                    }).then(() => {
                        rootInput.focus();
                        try { rootInput.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) { }
                    });
                } else {
                    alert('Please enter the Root of Title before saving.');
                    rootInput.focus();
                }
                updateCreateButtonState();
                return;
            }
        }

        const tempSuffixPattern = /\(\s*T\s*\)\s*$/i;
        const hiddenFileNumberValue = (document.getElementById('fileno')?.value || '').trim();
        const displayFileNumberValue = (document.getElementById('file-number-display')?.value || '').trim();
        const awaitingFileNumberValue = (document.getElementById('awaiting-file-no')?.value || '').trim();
        const archiveFileNumberValue = (document.getElementById('archive-file-no')?.value || '').trim();

        let fileNumber = hiddenFileNumberValue || displayFileNumberValue || '';
        if (tempSuffixPattern.test(displayFileNumberValue) && !tempSuffixPattern.test(fileNumber)) {
            fileNumber = displayFileNumberValue;
        }

        const tempSource = [hiddenFileNumberValue, displayFileNumberValue, awaitingFileNumberValue, archiveFileNumberValue, fileNumber]
            .find((candidate) => candidate && tempSuffixPattern.test(candidate)) || '';

        const hasTempFile = tempSource !== '';
        const baseFileNumber = hasTempFile
            ? tempSource.replace(tempSuffixPattern, '').trim()
            : fileNumber.trim();
        let tempFileNo = hasTempFile ? `${baseFileNumber}(T)` : null;

        // Edit mode: this form has no temp-file input — it infers temp status purely from
        // a "(T)" suffix on the file number. A stored temp record keeps the BASE number in
        // file_number and the "(T)" only in temp_file_no, so nothing on screen carries it,
        // and update() unconditionally resets has_temp_file/temp_file_no when no "(T)"
        // reaches it. Inherit the stored value so editing never severs the link. The
        // backend re-derives has_temp_file and the base file number from this.
        if (!tempFileNo) {
            const inheritedTempFileNo = (editModeState.record?.temp_file_no
                || window.editingRecord?.temp_file_no
                || '').toString().trim();

            if (inheritedTempFileNo !== '') {
                tempFileNo = inheritedTempFileNo;
            }
        }

        // Check if any related file inputs have values (array inputs)
        const relatedFileInputs = document.querySelectorAll('input[name="related_fileno[]"]');
        const relatedFileValues = Array.from(relatedFileInputs)
            .map(input => input.value.trim())
            .filter(value => value !== '');
        const hasRelatedFiles = relatedFileValues.length > 0;

        let fileTitle = document.getElementById('file-title')?.value || '';
        if (!fileTitle) {
            const titleInputs = document.querySelectorAll('input[name="file_title[]"]');
            if (titleInputs.length > 0) {
                const titles = Array.from(titleInputs).map(input => input.value.trim()).filter(v => v !== '');
                if (titles.length > 0) {
                    fileTitle = titles;
                }
            }
        }

        const indexingType = document.getElementById('indexing-type')?.value || 'Regular';
        const currentHolders = Array.from(document.querySelectorAll('.current-holder-input'))
            .map(input => input.value.trim())
            .filter(v => v !== '');

        console.log('Form submission - File Number:', fileNumber);
        console.log('Form submission - Has Related Files:', hasRelatedFiles);
        console.log('Form submission - File Title:', fileTitle);

        // Strip (T) suffix before normalizing so temp file numbers match the awaiting file number.
        // Also strip an "AND EXTENSION" suffix for the awaiting-file comparison only — the full
        // base value (with the extension) is preserved in baseFileNumber and saved intact below.
        const EXTENSION_SUFFIX_RE = /\s*AND\s+EXTENSION\s*$/i;
        const normalizedFileNumber = normalizeFileno(baseFileNumber.replace(EXTENSION_SUFFIX_RE, '').trim());
        const shouldEnforcePrimaryMatch = !hasRelatedFiles;

        if (!editModeState.isEditing && !groupingState.record && !window.isNewKnMode) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Awaiting File Not Confirmed',
                    text: 'Please fetch the grouping record so the awaiting file number can be validated before saving.',
                    icon: 'warning',
                    confirmButtonText: 'OK',
                });
            } else {
                alert('Please load the grouping record to confirm the awaiting file number before saving.');
            }
            updateCreateButtonState();
            return;
        }

        if (
            !editModeState.isEditing &&
            !window.isNewKnMode &&
            shouldEnforcePrimaryMatch &&
            (!normalizedFileNumber || normalizedFileNumber !== groupingState.normalizedAwaiting)
        ) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'File Number Mismatch',
                    text: 'The selected file number does not match the awaiting file number from grouping. Please refresh the archive details.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                });
            } else {
                alert('Selected file number must match the awaiting file number from grouping.');
            }
            updateCreateButtonState();
            return;
        }

        // Prevent submission if duplicate is detected and not in edit mode.
        // Skip this block entirely when the user is creating a KANGIS physical variant —
        // the KANGIS confirmation dialog will handle it instead.
        if (indexedFeedbackState.isDuplicate && !editModeState.isEditing && !isKangisVariantMode('full')) {
            if (indexedFeedbackState.record) {
                await promptExistingIndexedRecord(indexedFeedbackState.record, { forcePrompt: true });
                updateCreateButtonState();
                return;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Duplicate File Number',
                    text: 'This file number has already been indexed. Duplicate file numbers are not allowed.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                });
            } else {
                alert('This file number has already been indexed. Duplicate file numbers are not allowed.');
            }
            updateCreateButtonState();
            return;
        }

        const selectionMethod = document.querySelector('input[name="selection_method"]:checked')?.value || 'batch';
        console.log('Selection Method:', selectionMethod);

        let lgaSelect = document.getElementById('lga-city');
        if (!lgaSelect) {
            lgaSelect = document.getElementById('lga-city-0');
        }
        if (!lgaSelect) {
            lgaSelect = document.querySelector('select[name^="lga"]');
        }
        const districtSelectEl = document.getElementById('district-select');
        const customDistrictInput = document.getElementById('custom-district-input');
        const districtName = getDistrictValue();
        const districtId = getSelectedOptionId(districtSelectEl);

        let lgaName = '';
        let lgaId = null;
        let lgaValue = lgaSelect ? lgaSelect.value : '';

        // Robust extraction of LGA Name and ID
        if (lgaSelect && lgaValue) {
            // First try selectedOptions (modern)
            let selectedOpt = lgaSelect.selectedOptions ? lgaSelect.selectedOptions[0] : null;

            // Fallback: iterate if selectedOptions is empty but value exists
            if (!selectedOpt) {
                for (let i = 0; i < lgaSelect.options.length; i++) {
                    if (lgaSelect.options[i].value === lgaValue) {
                        selectedOpt = lgaSelect.options[i];
                        break;
                    }
                }
            }

            if (selectedOpt) {
                lgaName = (selectedOpt.dataset?.name || selectedOpt.textContent || '').trim();
                // Check if value is numeric for ID
                if (!isNaN(Number(lgaValue)) && lgaValue !== '') {
                    lgaId = Number(lgaValue);
                } else {
                    // If ID is string (e.g. UUID), keep it as string in a separate way if needed, 
                    // or just rely on lgaValue implicitly. 
                    // For the payload 'lga_id' maps to numeric usually. 
                    // If your system uses UUIDs, uncomment the next line:
                    // lgaId = lgaValue; 
                }
            }
        }

        // Validation: Check if value is present. Value is the single source of truth for selection.
        if (!lgaValue && indexingType !== 'Block') {
            showSelectionError('Please select an LGA before creating the file index.', lgaSelect);
            updateCreateButtonState();
            return;
        }

        const resolvedIndexedBy = ensureIndexedByLocked();
        const resolvedIndexedDate = ensureIndexedDateValue();

        const indexedByInput = document.getElementById('indexed-by');
        if (indexedByInput) {
            indexedByInput.value = resolvedIndexedBy;
        }

        const indexedDateInput = document.getElementById('indexed-date');
        if (indexedDateInput) {
            indexedDateInput.value = resolvedIndexedDate;
        }

        const hasCofo = document.getElementById('has-cofo-toggle')?.checked
            || document.getElementById('has-cofo')?.checked
            || false;
        const testControlValue = document.querySelector('input[name="test_control"]')?.value || '';

        const collectArrayValues = (baseName) => {
            let elements = document.querySelectorAll(`[name^="${baseName}["]`);
            if (elements.length === 0) {
                elements = document.querySelectorAll(`[name="${baseName}[]"]`);
            }
            if (elements.length > 0) {
                return Array.from(elements).map(el => el.value);
            }
            return null;
        };

        const normalizeDistrictChoice = (districtValue, customDistrictValue) => {
            const district = (districtValue ?? '').toString().trim();
            const custom = (customDistrictValue ?? '').toString().trim();
            if (!district) {
                return '';
            }
            if (district.toLowerCase() === 'other') {
                return custom || district;
            }
            return district;
        };

        const originalHolders = collectArrayValues('original_holder');

        const districtValues = collectArrayValues('district');
        const customDistrictValues = collectArrayValues('custom_district');
        const resolvedDistrictValues = Array.isArray(districtValues)
            ? districtValues.map((value, index) => normalizeDistrictChoice(value, customDistrictValues ? customDistrictValues[index] : ''))
            : normalizeDistrictChoice(districtName || null, customDistrictInput?.value || '');

        const streetNameValues = collectArrayValues('street_name');
        const customStreetNameValues = collectArrayValues('custom_street_name');
        const resolvedStreetNameValues = Array.isArray(streetNameValues)
            ? streetNameValues.map((value, index) => normalizeDistrictChoice(value, customStreetNameValues ? customStreetNameValues[index] : ''))
            : null;

        const currentFileNumberForSource = hasTempFile ? tempFileNo : baseFileNumber;
        const resolvedSourceFileId = getSourceFileIdForSubmit(currentFileNumberForSource);

        const formData = {
            file_number: hasTempFile ? tempFileNo : baseFileNumber,
            override_year_block: yearOverrideChecked() ? 1 : 0,
            has_temp_file: hasTempFile,
            temp_file_no: tempFileNo,
            file_title: collectArrayValues('file_title') || fileTitle,
            indexing_type: indexingType,
            // Parcel Update classification flags (multi-select: 'Dummy File' / 'Temporary'),
            // stored comma-separated. Their comment lives in the shared Title Status
            // Remark / Comment box, which we persist when a classification flag is set.
            file_classification: (function () {
                const vals = Array.from(document.querySelectorAll('.ts-classification-cb:checked')).map(cb => cb.value);
                return vals.length ? vals.join(',') : null;
            })(),
            file_classification_remarks: (function () {
                if (!document.querySelector('.ts-classification-cb:checked')) return null;
                const ta = document.getElementById('ts-remark');
                return (ta?.value || '').trim() || null;
            })(),
            // Root of Title is one value per file however many holder names there are,
            // so it stays a plain string in both Single and Block indexing.
            root_of_title: (document.getElementById('root-of-title')?.value || '').trim(),
            current_holder: indexingType === 'Block' ? currentHolders : (currentHolders[0] || fileTitle),
            original_holder: indexingType === 'Block' ? (originalHolders && originalHolders.length > 0 ? originalHolders : [currentHolders[0] || fileTitle]) : (originalHolders ? originalHolders[0] : (currentHolders[0] || fileTitle)),
            land_use_type: collectArrayValues('land_use_type') || (document.getElementById('land-use-type')?.value || ''),
            plot_number: collectArrayValues('plot_number') || (document.getElementById('plot-number')?.value || ''),
            tp_no: collectArrayValues('tp_number') || collectArrayValues('tp_no') || (document.getElementById('tp-number')?.value || ''),
            lpkn_no: collectArrayValues('lpkn_no') || (document.getElementById('lpkn-no')?.value || ''),
            location: collectArrayValues('location') || document.getElementById('location')?.value || groupingState.record?.location || groupingState.record?.property_location || '',
            latitude: (collectArrayValues('latitude') || []).map(v => (v === '' || v == null) ? null : v),
            longitude: (collectArrayValues('longitude') || []).map(v => (v === '' || v == null) ? null : v),
            plot_size: collectArrayValues('plot_size') || (document.getElementById('plot-size')?.value || ''),
            street_name: resolvedStreetNameValues || null,
            custom_street_name: customStreetNameValues || null,
            district: resolvedDistrictValues || null,
            custom_district: customDistrictValues || null,
            district_id: districtId || null,
            registry: getRegistryValue(),
            lga: collectArrayValues('lga') || ((lgaName && lgaName !== 'Select LGA' && lgaName !== 'Select LGA Name') ? lgaName : null),
            lga_id: lgaId,
            has_cofo: hasCofo,
            has_transaction: document.getElementById('has-transaction')?.checked || false,
            is_problematic: document.getElementById('is-problematic')?.checked || false,
            is_co_owned_plot: document.getElementById('co-owned-plot')?.checked || false,
            is_merged: document.getElementById('merged-plot')?.checked || false,
            serial_no: document.getElementById('serial-no')?.value || '',
            batch_no: document.getElementById('batch-no')?.value || '',
            shelf_location: document.getElementById('shelf-location')?.value || '',
            shelf_label_id: document.getElementById('shelf_label_id')?.value || '',
            batch_id: document.getElementById('batch_id')?.value || '',
            tracking_id: document.getElementById('tracking-id')?.value || '',
            archive_file_no: document.getElementById('archive-file-no')?.value || '',
            awaiting_file_no: document.getElementById('awaiting-file-no')?.value || '',
            registry_batch_no: document.getElementById('registry-batch-no')?.value || '',
            group_no: document.getElementById('registry-batch-no')?.value || '',
            mdc_batch_no: document.getElementById('mdc-batch-no')?.value || '',
            physical_registry: document.getElementById('physical-registry')?.value || '',
            general_registry: document.getElementById('general-registry')?.value || '',
            sys_batch_no: document.getElementById('sys-batch-no')?.value || '',
            shelf_rack_no: document.getElementById('shelf-rack-no')?.value || '',
            sub_prefix: document.getElementById('sub_prefix')?.value || '',
            suffix: document.getElementById('suffix')?.value || '',
            indexed_by: resolvedIndexedBy,
            indexed_date: resolvedIndexedDate,
            archive_location: document.getElementById('archive-location')?.value || '',
            grouping_match_id: document.getElementById('grouping-id')?.value || groupingState.record?.id || null,
            cofo_no: document.getElementById('cofo-number')?.value || '',
            cofo_date: document.getElementById('cofo-date')?.value || '',
            cofo_serial_no: document.getElementById('cofo-serial-no')?.value || '',
            cofo_page_no: document.getElementById('cofo-page-no')?.value || '',
            cofo_vol_no: document.getElementById('cofo-vol-no')?.value || '',
            cofo_deeds_time: document.getElementById('cofo-deeds-time')?.value || '',
            cofo_deeds_date: document.getElementById('cofo-deeds-date')?.value || '',
            cofo_instrument_type: document.getElementById('cofo-instrument-type')?.value || '',
            cofo_first_party: document.getElementById('cofo-first-party')?.value || '',
            cofo_second_party: document.getElementById('cofo-second-party')?.value || '',
            cofo_land_use: document.getElementById('cofo-land-use')?.value || '',
            cofo_period: document.getElementById('cofo-period')?.value || '',
            cofo_period_unit: document.getElementById('cofo-period-unit')?.value || 'Years',
            cofo_status: document.getElementById('cofo-status')?.value || 'Active',
            cofo_type: document.getElementById('cofo-type')?.value || '',
            has_rofo: document.getElementById('has-rofo-toggle')?.checked || false,
            rofo_instrument_type: document.getElementById('rofo-instrument-type')?.value || '',
            rofo_date: document.getElementById('rofo-date')?.value || '',
            rofo_number: document.getElementById('rofo-number')?.value || '',
            rofo_file_number: document.getElementById('rofo-file-number')?.value || '',
            rofo_land_use: document.getElementById('rofo-land-use')?.value || '',
            rofo_grantor: document.getElementById('rofo-grantor')?.value || '',
            rofo_grantee: document.getElementById('rofo-grantee')?.value || '',
            rofo_status: document.getElementById('rofo-status')?.value || 'Active',
            has_occupancy_permit: document.getElementById('has-occupancy-permit-toggle')?.checked || false,
            occupancy_permit_instrument_type: document.getElementById('occupancy-permit-instrument-type')?.value || '',
            occupancy_permit_op_type: document.getElementById('occupancy-permit-op-type')?.value || '',
            occupancy_permit_op_category: document.getElementById('occupancy-permit-op-category')?.value || '',
            occupancy_permit_op_serial_number: document.getElementById('occupancy-permit-op-serial-number')?.value || '',
            occupancy_permit_date: document.getElementById('occupancy-permit-date')?.value || '',
            occupancy_permit_file_number: document.getElementById('occupancy-permit-file-number')?.value || '',
            occupancy_permit_land_use: document.getElementById('occupancy-permit-land-use')?.value || '',
            occupancy_permit_grantor: document.getElementById('occupancy-permit-grantor')?.value || '',
            occupancy_permit_grantee: document.getElementById('occupancy-permit-grantee')?.value || '',
            occupancy_permit_status: document.getElementById('occupancy-permit-status')?.value || 'Active',
            occupancy_permit_serial_no: document.getElementById('occupancy-permit-serial-no')?.value || '',
            occupancy_permit_page_no: document.getElementById('occupancy-permit-page-no')?.value || '',
            occupancy_permit_vol_no: document.getElementById('occupancy-permit-vol-no')?.value || '',
            occupancy_permit_deeds_time: document.getElementById('occupancy-permit-deeds-time')?.value || '',
            occupancy_permit_deeds_date: document.getElementById('occupancy-permit-deeds-date')?.value || '',
            main_application_id: document.getElementById('application_id')?.value || null,
            subapplication_id: document.getElementById('sub_application_id')?.value || null,
            source_file_id: resolvedSourceFileId,
            file_type: collectArrayValues('file_type') || (document.getElementById('file_type')?.value || ''),
            gender: (function () {
                // Gender is captured per-file (gender[index]) in the Personal Details card.
                // The store endpoint records a single value, so send the first selected one.
                const vals = collectArrayValues('gender');
                if (Array.isArray(vals)) {
                    return vals.find(v => v && v.trim()) || vals[0] || '';
                }
                return document.getElementById('gender-0')?.value || document.getElementById('gender')?.value || '';
            })(),
            dob: collectArrayValues('dob') || (document.getElementById('dob')?.value || null),
            nin: collectArrayValues('nin') || (document.getElementById('nin')?.value || ''),
            tin: collectArrayValues('tin') || (document.getElementById('tin')?.value || ''),
            rc_no: collectArrayValues('rc_no') || (document.getElementById('rc-no')?.value || ''),
            country_code: collectArrayValues('country_code') || (document.getElementById('country-code')?.value || '+234'),
            phone: collectArrayValues('phone') || (document.getElementById('phone')?.value || ''),
            residence_address: collectArrayValues('residence_address') || (document.getElementById('residence_address')?.value || ''),
            dciv_reason: document.getElementById('dciv-reason')?.value || '',
            skip_entity_customer_updates: editModeState.isEditing,
            kangis_fileno_placeholder: (() => {
                // Read directly from prefix + serial controls so the value is always
                // current at submit time, regardless of whether JS events fired.
                const prefix = (document.getElementById('kangis-fileno-prefix')?.value || '').trim().toUpperCase();
                const serial = (document.getElementById('kangis-fileno-serial')?.value || '').trim();
                return (prefix && serial) ? `${prefix} ${serial}` : null;
            })(),
            // New KANGIS fields
            kangis_file_type: document.getElementById('kangis_file_type')?.value || null,
            mls_file_no: document.getElementById('mls_file_no_hidden')?.value || null,
            kangis_file_no: document.getElementById('kangis_file_no_hidden')?.value || null,
            new_kangis_file_no: document.getElementById('new_kangis_file_no_hidden')?.value || null,
            has_new_kangis_fileno: document.getElementById('has-new-kangis-fileno')?.checked ? 1 : 0,
            // New KANGIS transactions → saved to the pra table (one shared prop_id per file)
            has_new_kangis_transaction: document.getElementById('has-new-kangis-transaction')?.checked ? 1 : 0,
            transactions: (typeof window.collectNewKangisTransactions === 'function')
                ? window.collectNewKangisTransactions()
                : [],
            // Bill Balance & Grant Rent (repeatable — arrays). Saved to file_indexings
            // (primary/first) + billing + file_indexing_bills tables server-side.
            bill_total_amount: collectArrayValues('bill_total_amount'),
            bill_from_year: collectArrayValues('bill_from_year'),
            bill_to_year: collectArrayValues('bill_to_year'),
            bill_receipt_no: collectArrayValues('bill_receipt_no'),
            bill_receipt_date: collectArrayValues('bill_receipt_date'),
            ground_rent_amount: collectArrayValues('ground_rent_amount'),
            ground_rent_from_year: collectArrayValues('ground_rent_from_year'),
            ground_rent_to_year: collectArrayValues('ground_rent_to_year'),
            ground_rent_receipt_no: collectArrayValues('ground_rent_receipt_no'),
            ground_rent_receipt_date: collectArrayValues('ground_rent_receipt_date'),
        };

        // Editing must be able to remove the LAST related file number. Sending an explicit
        // empty array is what distinguishes "the operator cleared them" from "this form had
        // no related-file section at all" — the server only deletes the link rows for the
        // former (see FileIndexingController::syncRelatedFileLinks).
        if (editModeState.isEditing) {
            formData.related_fileno = relatedFileValues;
        }

        // Add related file numbers and details to formData
        if (relatedFileValues.length > 0 || indexingType === 'Block') {
            if (relatedFileValues.length > 0) {
                formData.related_fileno = relatedFileValues;
            }

            // Collect related details from the modal/DOM
            const relatedDetails = [];
            const container = document.getElementById('related-file-details-container');
            if (container) {
                const detailSections = container.querySelectorAll('.bg-white.rounded-lg.border');
                detailSections.forEach((section, index) => {
                    // In Block mode, we use 'holder' as ID, in Regular mode we use 'file_number'
                    const fileNumber = section.querySelector(`input[name$="][file_number]"]`)?.value;
                    const holder = section.querySelector(`input[name$="][holder]"]`)?.value;
                    const title = section.querySelector(`input[name$="][file_title]"]`)?.value;

                    if (fileNumber || holder || title) {
                        relatedDetails.push({
                            file_number: fileNumber || '',
                            holder: holder || '',
                            file_title: title || '',
                            location: section.querySelector(`input[name$="][location]"]`)?.value || '',
                            plot_number: section.querySelector(`input[name$="][plot_number]"]`)?.value || '',
                            tp_no: section.querySelector(`input[name$="][tp_no]"]`)?.value || '',
                            lpkn_no: section.querySelector(`input[name$="][lpkn_no]"]`)?.value || '',
                        });
                    }
                });
            }

            if (relatedDetails.length > 0) {
                formData.related_details = relatedDetails;
            }
        }

        if (testControlValue) {
            formData.test_control = testControlValue;
        }

        const propIdRaw = document.getElementById('prop-id-field')?.value;
        if (propIdRaw && propIdRaw.trim() !== '') {
            formData.prop_id = propIdRaw.trim();
        }

        const entityDetails = collectEntityDetailsForSubmit();
        if (entityDetails) {
            formData.entity_details = entityDetails;
        }

        const customerDetails = collectCustomerDetailsForSubmit();
        if (customerDetails) {
            formData.customer_details = customerDetails;
        }

        if (!formData.file_number || !formData.file_title) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Validation Error',
                    text: 'File number and file title are required.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                });
            } else {
                alert('File number and file title are required.');
            }
            return;
        }

        // Land Use Type is required for all create/update flows.
        const landUseSelect = document.getElementById('land-use-type') || document.getElementById('land-use-type-0');
        const landUseRaw = formData.land_use_type;
        const landUseHasValue = Array.isArray(landUseRaw)
            ? landUseRaw.some(v => (v ?? '').toString().trim() !== '')
            : (landUseRaw ?? '').toString().trim() !== '';
        if (!landUseHasValue) {
            const focusLandUse = () => {
                if (landUseSelect) {
                    landUseSelect.focus();
                    landUseSelect.classList.add('error-border');
                    landUseSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const clearError = () => {
                        landUseSelect.classList.remove('error-border');
                        landUseSelect.removeEventListener('change', clearError);
                        landUseSelect.removeEventListener('input', clearError);
                    };
                    landUseSelect.addEventListener('change', clearError);
                    landUseSelect.addEventListener('input', clearError);
                }
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Land Use Type Required',
                    text: 'Please select a Land Use Type before submitting.',
                    icon: 'warning',
                    confirmButtonColor: '#f59e0b',
                }).then(focusLandUse);
            } else {
                alert('Please select a Land Use Type before submitting.');
                focusLandUse();
            }
            return;
        }

        // KANGIS placeholder is required only when KANGIS registry is selected
        // (and we're not in the new_kn KN-series mode where placeholder doesn't apply).
        const generalRegistryValue = (document.getElementById('general-registry')?.value || '').toString().trim();
        const isKangisRegistrySelected = generalRegistryValue.toUpperCase().includes('KANGIS');
        if (isKangisRegistrySelected && !window.isNewKnMode) {
            const placeholderPrefix = (document.getElementById('kangis-fileno-prefix')?.value || '').trim();
            const placeholderSerial = (document.getElementById('kangis-fileno-serial')?.value || '').trim();
            const assembledPlaceholder = (formData.kangis_fileno_placeholder || '').toString().trim();

            if (!placeholderPrefix || !placeholderSerial || !assembledPlaceholder) {
                const wrapper = document.getElementById('kangis-fileno-placeholder-wrapper');
                const focusPlaceholder = () => {
                    if (wrapper) {
                        wrapper.classList.remove('hidden');
                        wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    // Show inline error on both fields, not just the first empty one
                    if (typeof validateKangisPlaceholderFields === 'function') {
                        validateKangisPlaceholderFields(true);
                    } else {
                        const target = !placeholderPrefix
                            ? document.getElementById('kangis-fileno-prefix')
                            : document.getElementById('kangis-fileno-serial');
                        if (target) {
                            target.focus();
                            target.classList.add('error-border');
                            const clearError = () => {
                                target.classList.remove('error-border');
                                target.removeEventListener('change', clearError);
                                target.removeEventListener('input', clearError);
                            };
                            target.addEventListener('change', clearError);
                            target.addEventListener('input', clearError);
                        }
                    }
                    const focusTarget = !placeholderPrefix
                        ? document.getElementById('kangis-fileno-prefix')
                        : document.getElementById('kangis-fileno-serial');
                    if (focusTarget) focusTarget.focus();
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'KANGIS FileNo Placeholder Required',
                        text: 'KANGIS Registry is selected. Please enter the KANGIS FileNo Placeholder (prefix and serial) before submitting.',
                        icon: 'warning',
                        confirmButtonColor: '#f59e0b',
                    }).then(focusPlaceholder);
                } else {
                    alert('KANGIS Registry is selected. Please enter the KANGIS FileNo Placeholder (prefix and serial) before submitting.');
                    focusPlaceholder();
                }
                return;
            }
        }

        // Residential Address is required when SLTR Registry is selected. Every rendered
        // customer/entity address field must be filled before the file can be saved.
        const isSltrRegistrySelected = generalRegistryValue.toUpperCase().includes('SLTR');
        if (isSltrRegistrySelected) {
            const addressFields = Array.from(
                document.querySelectorAll('textarea[name^="residence_address["], textarea[name="residence_address[]"]')
            );
            const firstEmptyAddress = addressFields.find(el => !(el.value || '').trim());
            // Fall back to the legacy single field when no repeatable fields are rendered.
            const legacyAddress = document.getElementById('residence_address');
            const legacyEmpty = !addressFields.length && legacyAddress && !(legacyAddress.value || '').trim();
            const target = firstEmptyAddress || (legacyEmpty ? legacyAddress : null);

            if (target) {
                const focusAddress = () => {
                    target.focus();
                    target.classList.add('error-border');
                    try { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) { }
                    const clearError = () => {
                        target.classList.remove('error-border');
                        target.removeEventListener('input', clearError);
                        target.removeEventListener('change', clearError);
                    };
                    target.addEventListener('input', clearError);
                    target.addEventListener('change', clearError);
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Residential Address Required',
                        text: 'SLTR Registry is selected. Please enter the Residential Address before submitting.',
                        icon: 'warning',
                        confirmButtonColor: '#f59e0b',
                    }).then(focusAddress);
                } else {
                    alert('SLTR Registry is selected. Please enter the Residential Address before submitting.');
                    focusAddress();
                }
                return;
            }
        }

        // Same rule for the CofO: Serial No / Page No / Vol No are required when the
        // file has one. Page No mirrors Serial No (read-only), so point at Serial No.
        if (document.getElementById('has-cofo-toggle')?.checked || document.getElementById('has-cofo')?.checked) {
            const cofoParticulars = [
                ['cofo-serial-no', 'Serial No', 'cofo-serial-no'],
                ['cofo-page-no', 'Page No', 'cofo-serial-no'],
                ['cofo-vol-no', 'Vol No', 'cofo-vol-no'],
            ];
            for (const [id, label, focusId] of cofoParticulars) {
                if ((document.getElementById(id)?.value || '').trim() === '') {
                    alert(`${label} is required for the Certificate of Occupancy (CofO).`);
                    document.getElementById(focusId)?.focus();
                    return;
                }
            }
        }

        if (document.getElementById('has-occupancy-permit-toggle')?.checked) {
            const serialInput = document.getElementById('occupancy-permit-op-serial-number');
            if (!/^[1-9][0-9]*$/.test((serialInput?.value || '').trim())) {
                alert('OP Serial Number is required and must contain positive digits without leading zeros.');
                serialInput?.focus();
                return;
            }

            // Serial No / Page No / Vol No are the deeds registration particulars and
            // are required whenever the file has an OP. Page No mirrors Serial No and
            // is read-only, so a blank one means Serial No is blank: focus that instead.
            const particulars = [
                ['occupancy-permit-serial-no', 'Serial No', 'occupancy-permit-serial-no'],
                ['occupancy-permit-page-no', 'Page No', 'occupancy-permit-serial-no'],
                ['occupancy-permit-vol-no', 'Vol No', 'occupancy-permit-vol-no'],
            ];
            for (const [id, label, focusId] of particulars) {
                if ((document.getElementById(id)?.value || '').trim() === '') {
                    alert(`${label} is required for the Occupancy Permit.`);
                    document.getElementById(focusId)?.focus();
                    return;
                }
            }
        }

        // "Has New KANGIS FileNo" gate — if ticked, the KN-series number must be entered.
        if (typeof window.validateHasNewKangisFileno === 'function' && !window.validateHasNewKangisFileno()) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'New KANGIS FileNo Required',
                    text: 'You ticked "Has New KANGIS FileNo". Please enter the New KANGIS File No (KN Series) before submitting.',
                    icon: 'warning',
                    confirmButtonColor: '#9333ea',
                });
            } else {
                alert('Please enter the New KANGIS File No (KN Series) before submitting.');
            }
            return;
        }

        // Title Status is required — at least one checkbox must be selected. The checkboxes
        // span two cards (Parcel Update / Normal and Title Status Update), so query by class.
        // SLTR / KANGIS / DCIV registries hide both cards (handleRegistryFieldToggling), so
        // the requirement doesn't apply there — skip it whenever the card is hidden.
        const titleStatusGroup = document.getElementById('ts-title-type-group');
        const titleStatusHidden = !!document.getElementById('ts-title-status-block')?.classList.contains('hidden');
        const titleStatusBoxes = Array.from(document.querySelectorAll('.ts-title-type-cb'));
        const titleStatusChosen = titleStatusBoxes.some(cb => cb.checked);
        if (!titleStatusHidden && titleStatusBoxes.length && !titleStatusChosen) {
            const errorTarget = titleStatusGroup || titleStatusBoxes[0];
            const focusTitleStatus = () => {
                errorTarget.classList.add('error-border');
                errorTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                (titleStatusBoxes[0] || errorTarget).focus();
                const clearError = () => {
                    errorTarget.classList.remove('error-border');
                    document.removeEventListener('change', clearError);
                };
                document.addEventListener('change', clearError);
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Title Status Required',
                    text: 'Please select a Title Status before submitting.',
                    icon: 'warning',
                    confirmButtonColor: '#f59e0b',
                }).then(focusTitleStatus);
            } else {
                alert('Please select a Title Status before submitting.');
                focusTitleStatus();
            }
            return;
        }

        // Location pin required — coordinates may only be applied via "Apply & Pin on Map",
        // so every file must have both latitude and longitude captured before submitting.
        // TEMPORARILY DISABLED (Google Cloud billing account suspended, so the Maps/Geocoding
        // API rejects requests and the pin can't be captured). Flip back to true once billing
        // is restored — coordinates remain nullable server-side, so nothing else needs changing.
        const REQUIRE_LOCATION_PIN = false;
        const latPinInputs = REQUIRE_LOCATION_PIN
            ? Array.from(document.querySelectorAll('input[name^="latitude["]'))
            : [];
        let unpinnedIdx = null;
        for (const latEl of latPinInputs) {
            const idx = latEl.name.replace(/[^0-9]/g, '');
            const lngEl = document.querySelector(`input[name="longitude[${idx}]"]`);
            if (!String(latEl.value || '').trim() || !lngEl || !String(lngEl.value || '').trim()) {
                unpinnedIdx = idx;
                break;
            }
        }
        if (latPinInputs.length && unpinnedIdx !== null) {
            const multi = latPinInputs.length > 1;
            const msg = multi
                ? `Please click "Apply & Pin on Map" to capture the location for File ${Number(unpinnedIdx) + 1} before submitting.`
                : 'Please click "Apply & Pin on Map" to capture the property location before submitting.';
            const focusUnpinned = () => {
                const el = document.querySelector(`input[name="latitude[${unpinnedIdx}]"]`);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            };
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Location Pin Required',
                    text: msg,
                    icon: 'warning',
                    confirmButtonColor: '#f59e0b',
                }).then(focusUnpinned);
            } else {
                alert(msg);
                focusUnpinned();
            }
            return;
        }

        const isEditingExisting = editModeState.isEditing && editModeState.recordId;

        // Check if there is an existing associated temporary record before proceeding
        const isTempFileSuffix = /\(\s*T\s*\)\s*$/i.test(formData.file_number || '');
        if (!isEditingExisting && !isTempFileSuffix && !formData.kangis_fileno_placeholder) {
            try {
                const checkUrl = `/fileindexing/api/check-temp-association?file_number=${encodeURIComponent(formData.file_number || '')}`;
                const chkResponse = await fetch(checkUrl, {
                    headers: { Accept: 'application/json' },
                });
                if (chkResponse.ok) {
                    const checkData = await chkResponse.json();
                    if (checkData.has_associated_temp && typeof Swal !== 'undefined') {
                        const confirmTempMerge = await Swal.fire({
                            title: 'Temporary File Association Detected',
                            html: `An existing temporary file <strong>${checkData.temp_record.temp_file_no}</strong> was found for this file number in the database.<br><br>` +
                                `Saving will merge this main file number into the existing temporary indexing record.<br><br>` +
                                `Do you want to proceed?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, merge & proceed',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                        });

                        if (!confirmTempMerge.isConfirmed) {
                            return; // User cancelled
                        }
                    }
                }
            } catch (err) {
                console.error('Error checking temporary file association', err);
            }
        }

        // KANGIS update mode: a dedicated endpoint that pins file_number and mirrors the
        // placeholder into kangis_grouping. Unset on every other page, where this is a no-op.
        const kangisUpdate = window.kangisUpdateMode || null;

        const endpoint = kangisUpdate
            ? kangisUpdate.url
            : (isEditingExisting
                ? `/fileindexing/${encodeURIComponent(editModeState.recordId)}`
                : '/fileindexing/store');
        const httpMethod = (kangisUpdate || isEditingExisting) ? 'PUT' : 'POST';

        if (kangisUpdate) {
            // The row's _N identity was settled when it was created and never moves.
            formData.file_number = kangisUpdate.fileNumber || formData.file_number;
        }

        // KANGIS variant confirmation — if a placeholder is provided, ask the user
        // to confirm the automatic suffix assignment before submitting.
        if ((kangisUpdate || isEditingExisting) && formData.kangis_fileno_placeholder && typeof Swal !== 'undefined') {
            // Updating an existing physical file — no suffix is allocated, so the
            // create-path confirmation below would state the opposite of what happens.
            // The generic edit page (PUT /fileindexing/{id}) is an update too.
            const keptFileNumber = kangisUpdate ? kangisUpdate.fileNumber : (formData.file_number || '');
            const confirmUpdate = await Swal.fire({
                title: 'Confirm KANGIS Update',
                html: `Physical file <strong>${formData.kangis_fileno_placeholder}</strong> will be updated.<br><br>` +
                    `The file number stays <strong class="text-blue-700">${keptFileNumber}</strong> — no new variant is created.<br><br>` +
                    `Do you want to proceed?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, update',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb',
            });

            if (!confirmUpdate.isConfirmed) {
                return;
            }
        } else if (formData.kangis_fileno_placeholder && typeof Swal !== 'undefined') {
            const selectedFileNumber = groupingState.record?.awaiting_fileno || formData.file_number || '';
            // Fetch sibling info so we can show the exact file number that will be assigned
            let bareExists = false;
            let suffixedCount = 0;
            try {
                const chkResp = await fetch(`/api/kangis-placeholder/check?file_number=${encodeURIComponent(selectedFileNumber)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (chkResp.ok) {
                    const chkData = await chkResp.json();
                    bareExists = chkData.bare_exists || false;
                    suffixedCount = chkData.count || 0;
                }
            } catch (_) { /* ignore — still show dialog without count */ }

            // Only add a suffix when the bare file number already exists
            const assignedSuffix = bareExists
                ? `${selectedFileNumber}_${suffixedCount + 1}`
                : selectedFileNumber;
            const confirmResult = await Swal.fire({
                title: 'Confirm KANGIS Record',
                html: `Physical file <strong>${formData.kangis_fileno_placeholder}</strong> will be indexed for <strong>${selectedFileNumber}</strong>.<br><br>` +
                    `The file number will be saved as <strong class="text-blue-700">${assignedSuffix}</strong>.<br><br>` +
                    `Do you want to proceed?`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Yes, create variant',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb',
            });

            if (!confirmResult.isConfirmed) {
                return; // User cancelled — do not submit
            }
        }

        const createBtn = document.getElementById('create-file-btn');
        if (createBtn) {
            createBtn.disabled = true;
            const targetSpan = createBtn.querySelector(editModeState.isEditing ? '[data-state="edit"]' : '[data-state="create"]');
            if (targetSpan) {
                targetSpan.textContent = isEditingExisting ? 'Updating...' : 'Creating...';
            } else {
                createBtn.textContent = isEditingExisting ? 'Updating...' : 'Creating...';
            }
        }

        const loadingIndicator = document.getElementById('grouping-loading-indicator');

        isSubmittingFileIndex = true;

        fetch(endpoint, {
            method: httpMethod,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(formData),
        })
            .then(async response => {
                let payload = null;
                try {
                    payload = await response.json();
                } catch (parseError) {
                    payload = null;
                }

                if (!response.ok) {
                    const error = new Error(payload?.message || 'Failed to save file indexing. Please try again.');
                    error.status = response.status;
                    error.validationErrors = payload?.errors || null;
                    error.errorType = payload?.error_type || null;
                    error.existingRecord = payload?.existing_record || null;
                    error.duplicateAction = payload?.duplicate_action || null;
                    throw error;
                }

                return payload || {};
            })
            .then(data => {
                console.log('Server response:', data);

                if (data.success) {
                    // Record a Title Status application if one was selected on the form
                    // (reuses the standalone title-status backend; defined in the blade).
                    if (typeof window.tsAfterFileIndexCreated === 'function') {
                        try { window.tsAfterFileIndexCreated(data, formData); } catch (_) { }
                    }

                    const propertyModalPayload = buildPropertyTransactionPayload(data, formData);
                    if (Array.isArray(data.file_transactions)) {
                        propertyModalPayload.existing_records = data.file_transactions;
                    }

                    if (isEditingExisting) {
                        const launchTransactionPrompt = () => promptPropertyTransactionCapture(propertyModalPayload);

                        showIndexingSavedCard(data, { isUpdate: true })
                            .then(() => postSubmitCards(data))
                            .then(launchTransactionPrompt);
                    } else {
                        // The store response doesn't echo back tracking_id/file_title,
                        // so read them from the DOM (they were set in the form before submit).
                        const domTrackingId = (document.getElementById('tracking-id')?.value || '').trim();
                        const domFileTitle = (document.querySelector('input[name="file_title[]"], #file-title')?.value || '').trim();
                        const fileIndexingData = {
                            ...propertyModalPayload,
                            tracking_id: domTrackingId || propertyModalPayload.tracking_id || '',
                            file_title: domFileTitle || propertyModalPayload.file_title || '',
                        };

                        closeFileIndexingDialog();

                        if (typeof window.refreshAvailableBatches === 'function') {
                            window.refreshAvailableBatches();
                        }

                        if (typeof refreshFileList === 'function') {
                            refreshFileList();
                        }

                        const launchTransactionPrompt = () => {
                            console.log('Opening property transaction modal...');
                            console.log('Full server response:', JSON.stringify(data, null, 2));
                            console.log('Final fileIndexingData to pass to modal:', fileIndexingData);

                            if (!fileIndexingData.file_number) {
                                console.error('File number is missing!');
                                alert('Error: File number not found. Please try again.');
                                return;
                            }

                            // If we arrived here from the file tracker (return_to URL), redirect back
                            // when the user clicks "No" on the transaction prompt.
                            const transactionOptions = {};
                            if (window.returnToUrl) {
                                transactionOptions.onDecline = function () {
                                    const params = new URLSearchParams();
                                    params.set('file_number', fileIndexingData.file_number || '');
                                    if (fileIndexingData.tracking_id) params.set('tracking_id', fileIndexingData.tracking_id);
                                    if (fileIndexingData.file_title) params.set('file_title', fileIndexingData.file_title);
                                    const separator = window.returnToUrl.includes('?') ? '&' : '?';
                                    window.location.href = window.returnToUrl + separator + params.toString();
                                };
                            }

                            promptPropertyTransactionCapture(fileIndexingData, transactionOptions);
                        };

                        let extraText = '';
                        if (data.batch_assignment) {
                            const assignment = data.batch_assignment;
                            extraText = `\n\nAssigned to:\nBatch: ${assignment.batch_no}\nSerial: ${assignment.serial_no}\nShelf: ${assignment.shelf_location || 'Auto-assigned'}`;
                        }

                        showIndexingSavedCard(data, { extraText }).then(() => {
                            // Auto-print KANGIS tracking sheet when in new_kn mode
                            const newFileId = data.file_indexing_id || data.id;
                            if (window.isNewKnMode && newFileId) {
                                const sheetUrl = `/fileindexing/kangis-tracking-sheet?files=${encodeURIComponent(newFileId)}&autoprint=1`;
                                const printWin = window.open(sheetUrl, '_blank');
                                if (printWin) {
                                    printWin.addEventListener('load', function () {
                                        printWin.focus();
                                        printWin.print();
                                    });
                                }
                            }
                            // Submission Summary (every file this save touched, and where
                            // each landed) then File Snapshot, before the transaction prompt.
                            postSubmitCards(data).then(launchTransactionPrompt);
                        });
                    }
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            })
            .catch(async error => {
                console.error('Error creating file indexing:', error);

                if (error.errorType === 'duplicate' && error.existingRecord) {
                    await handleDuplicateSubmissionConflict(error.existingRecord, error.message);
                    return;
                }

                let fallbackMessage = error.message || 'Failed to create file indexing. Please try again.';

                if (error.validationErrors && typeof error.validationErrors === 'object') {
                    const flattened = Object.values(error.validationErrors || {})
                        .flat()
                        .find(item => typeof item === 'string' && item.trim() !== '');

                    if (flattened) {
                        fallbackMessage = flattened;
                    }
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error',
                        text: fallbackMessage,
                        icon: 'error',
                        confirmButtonText: 'OK',
                    });
                } else {
                    alert(fallbackMessage);
                }
            })
            .finally(() => {
                isSubmittingFileIndex = false;
                if (createBtn) {
                    if (loadingIndicator) {
                        loadingIndicator.classList.add('hidden');
                    }
                    createBtn.disabled = false;
                    refreshSubmitButtonLabel();
                }
                updateCreateButtonState();
            });
    }

    function getDistrictValue() {
        const districtSelect = document.getElementById('district-select');
        const customDistrictInput = document.getElementById('custom-district-input');

        if (!districtSelect) {
            return '';
        }

        if (districtSelect.value === 'other') {
            return customDistrictInput?.value?.trim() || '';
        }

        return getSelectedOptionName(districtSelect);
    }

    function getRegistryValue() {
        const registryField = document.getElementById('registry');

        if (!registryField) {
            return '';
        }

        return registryField.value;
    }

    function resetFileIndexingForm() {
        const form = document.getElementById('new-file-form');
        if (form) {
            form.reset();
        }

        const fileNumberDisplay = document.getElementById('file-number-display');
        const fileNumberInput = document.getElementById('fileno');
        if (fileNumberDisplay) {
            setAutoFilledValue(fileNumberDisplay, '', { lock: false });
        }
        if (fileNumberInput) {
            fileNumberInput.value = '';
        }

        hideIndexedFeedback();
        exitEditMode();
        refreshSubmitButtonLabel();

        const relatedDisplay = document.getElementById('related-file-number-display');
        const relatedInput = document.getElementById('related-fileno');
        if (relatedDisplay) {
            setAutoFilledValue(relatedDisplay, '', { lock: false });
        }
        if (relatedInput) {
            relatedInput.value = '';
        }

        if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
            const $batchSelect = $('#batch-no');
            if ($batchSelect.hasClass('select2-hidden-accessible')) {
                $batchSelect.val(null).trigger('change');
            }
        } else {
            const batchSelect = document.getElementById('batch-no');
            if (batchSelect) {
                batchSelect.value = '';
            }
        }

        const shelfInput = document.getElementById('shelf-location');
        if (shelfInput) {
            setAutoFilledValue(shelfInput, '', { lock: false });
            shelfInput.classList.remove('loading', 'success-border', 'error-border');
        }

        const customDistrictContainer = document.getElementById('custom-district-container');
        const customDistrictInput = document.getElementById('custom-district-input');
        if (customDistrictContainer && customDistrictInput) {
            customDistrictContainer.classList.add('hidden');
            customDistrictInput.value = '';
        }

        const districtSelect = document.getElementById('district-select');
        if (districtSelect) {
            if (referenceDataStore.isLoaded) {
                populateDistrictSelect(referenceDataStore.districts);
            }
            districtSelect.value = '';
        }

        const fileTitleInput = document.getElementById('file-title');
        if (fileTitleInput) {
            setAutoFilledValue(fileTitleInput, '', { lock: false });
        }

        const lgaSelect = document.getElementById('lga-city');
        if (lgaSelect) {
            if (referenceDataStore.isLoaded) {
                populateLgaSelect(referenceDataStore.lgas);
            }
            lgaSelect.value = '';
        }

        clearArchiveFields();
        resetGroupingState();
        updateCreateButtonState();

        const trackingIdInput = document.getElementById('tracking-id');
        if (trackingIdInput) {
            trackingIdInput.value = generateTrackingId();
        }

        const archiveFileNoInput = document.getElementById('archive-file-no');
        if (archiveFileNoInput) archiveFileNoInput.value = '';

        const hasCofoToggle = document.getElementById('has-cofo-toggle');
        const cofoDetailsContainer = document.getElementById('cofo-details-container');
        if (hasCofoToggle) {
            hasCofoToggle.checked = false;
        }
        if (cofoDetailsContainer) {
            cofoDetailsContainer.classList.add('hidden');
        }
        clearCofoFields();

        const hasRofoToggleReset = document.getElementById('has-rofo-toggle');
        const rofoDetailsContainerReset = document.getElementById('rofo-details-container');
        if (hasRofoToggleReset) {
            hasRofoToggleReset.checked = false;
        }
        if (rofoDetailsContainerReset) {
            rofoDetailsContainerReset.classList.add('hidden');
        }
        clearRofoFields();

        const hasOccupancyPermitToggleReset = document.getElementById('has-occupancy-permit-toggle');
        const occupancyPermitDetailsContainerReset = document.getElementById('occupancy-permit-details-container');
        if (hasOccupancyPermitToggleReset) {
            hasOccupancyPermitToggleReset.checked = false;
        }
        if (occupancyPermitDetailsContainerReset) {
            occupancyPermitDetailsContainerReset.classList.add('hidden');
        }
        clearOccupancyPermitFields();

        clearEntityCustomerFields({ silent: true });

        if (typeof resetSmartFileSelector === 'function') {
            resetSmartFileSelector();
        }

        if (typeof window.__updateLocationField === 'function') {
            window.__updateLocationField();
        }

        // Clear dynamic party rows if any
        const partyContainer = document.getElementById('party-rows-container');
        if (partyContainer) {
            const rows = partyContainer.querySelectorAll('.party-row');
            rows.forEach((row, idx) => {
                if (idx > 0) row.remove(); // Keep first row
            });
        }
    }

    function closeFileIndexingDialog() {
        const overlay = document.getElementById('new-file-dialog-overlay');
        if (overlay) {
            overlay.classList.add('hidden');
            resetFileIndexingForm();
        }
    }

    window.closeFileIndexingDialog = closeFileIndexingDialog;

    function openFileIndexingDialog() {
        const overlay = document.getElementById('new-file-dialog-overlay');
        if (overlay) {
            overlay.classList.remove('hidden');

            initializeEntityCustomerSection();
            clearEntityCustomerFields({ silent: true });
            hideIndexedFeedback();

            clearArchiveFields();
            resetGroupingState();
            updateCreateButtonState();

            loadReferenceData();

            console.log('Dialog opening - refreshing auto-assignment preview...');
            loadAutoAssignmentPreview();

            const trackingIdInput = document.getElementById('tracking-id');
            if (trackingIdInput) {
                const newTrackingId = generateTrackingId();
                setAutoFilledValue(trackingIdInput, newTrackingId);
            }

            const indexedByInput = document.getElementById('indexed-by');
            const indexedDateInput = document.getElementById('indexed-date');
            const form = document.getElementById('new-file-form');
            const defaultIndexer = form?.dataset?.defaultIndexer || 'Current User';

            if (indexedByInput) {
                setAutoFilledValue(indexedByInput, defaultIndexer);
                ensureIndexedByLocked();
            }

            if (indexedDateInput) {
                const today = new Date();
                const formattedDate = today.getFullYear() + '-'
                    + String(today.getMonth() + 1).padStart(2, '0') + '-'
                    + String(today.getDate()).padStart(2, '0');
                setAutoFilledValue(indexedDateInput, formattedDate);
                ensureIndexedDateValue();
            }

            setTimeout(() => {
                const firstInput = overlay.querySelector('input:not([readonly])');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);

            initializeLocationAutoUpdate();
        }
    }

    window.openFileIndexingDialog = openFileIndexingDialog;

    function initializeLocationAutoUpdate() {
        const plotNumberInput = document.getElementById('plot-number');
        const districtSelect = document.getElementById('district-select');
        const customDistrictInput = document.getElementById('custom-district-input');
        const lgaSelect = document.getElementById('lga-city');
        const locationInput = document.getElementById('location');

        function getDistrictValueLocal() {
            if (!districtSelect) {
                return '';
            }

            if (districtSelect.value === 'other') {
                return customDistrictInput?.value?.trim() || '';
            }

            return getSelectedOptionName(districtSelect);
        }

        function updateLocationField() {
            if (!locationInput) {
                return;
            }

            const parts = [];
            const plotNumber = plotNumberInput?.value?.trim();
            const districtName = getDistrictValueLocal();
            const lgaName = getSelectedOptionName(lgaSelect);

            if (plotNumber) {
                parts.push(plotNumber);
            }
            if (districtName) {
                parts.push(districtName);
            }
            if (lgaName) {
                parts.push(lgaName);
            }

            const locationValue = parts.join(', ');
            locationInput.value = locationValue;

            if (locationValue) {
                locationInput.style.backgroundColor = '#f0f9ff';
                setTimeout(() => {
                    locationInput.style.backgroundColor = '#f9fafb';
                }, 1000);
            }
        }

        if (plotNumberInput) {
            plotNumberInput.removeEventListener('input', updateLocationField);
            plotNumberInput.addEventListener('input', updateLocationField);
        }

        if (districtSelect) {
            districtSelect.removeEventListener('change', updateLocationField);
            districtSelect.addEventListener('change', updateLocationField);
        }

        if (customDistrictInput) {
            customDistrictInput.removeEventListener('input', updateLocationField);
            customDistrictInput.addEventListener('input', updateLocationField);
        }

        if (lgaSelect) {
            lgaSelect.removeEventListener('change', updateLocationField);
            lgaSelect.addEventListener('change', updateLocationField);
        }

        window.__updateLocationField = updateLocationField;
        updateLocationField();
    }

    window.initializeLocationAutoUpdate = initializeLocationAutoUpdate;

    function buildPropertyTransactionPayload(serverResponse, fallbackFormData = {}) {
        const payload = serverResponse?.data || {};
        const tempSuffixPattern = /\(\s*T\s*\)\s*$/i;

        let fileNumber = payload.file_number || fallbackFormData.file_number || '';
        const hasTempFile = payload.has_temp_file || fallbackFormData.has_temp_file || tempSuffixPattern.test(fileNumber);

        let tempFileNo = payload.temp_file_no || fallbackFormData.temp_file_no || null;
        if (hasTempFile && !tempFileNo) {
            tempFileNo = tempSuffixPattern.test(fileNumber) ? fileNumber : `${fileNumber}(T)`;
        }

        if (hasTempFile && fileNumber) {
            fileNumber = fileNumber.replace(tempSuffixPattern, '').trim();
        }

        const basePayload = {
            id: payload.id || null,
            file_number: fileNumber,
            has_temp_file: hasTempFile ? 1 : 0,
            temp_file_no: tempFileNo,
            file_title: payload.file_title || fallbackFormData.file_title || '',
            plot_number: payload.plot_number || fallbackFormData.plot_number || '',
            tp_no: payload.tp_no || fallbackFormData.tp_no || '',
            lpkn_no: payload.lpkn_no || fallbackFormData.lpkn_no || '',
            lga: payload.lga || fallbackFormData.lga || '',
            district: payload.district || fallbackFormData.district || '',
            location: payload.location || fallbackFormData.location || '',
            land_use_type: payload.land_use_type || fallbackFormData.land_use_type || '',
            test_control: payload.test_control || fallbackFormData.test_control || null,
        };

        const serverTransactions = Array.isArray(serverResponse?.file_transactions)
            ? serverResponse.file_transactions
            : [];

        if (serverTransactions.length > 0) {
            cacheTransactionsForFile(fileNumber, serverTransactions);
        }

        const cachedTransactions = getCachedTransactionsForFile(fileNumber);
        if (cachedTransactions.length > 0) {
            basePayload.existing_records = cachedTransactions;
        } else {
            basePayload.existing_records = [];
        }

        return basePayload;
    }

    function promptPropertyTransactionCapture(payload, options = {}) {
        // Skip the prompt if DCIV Registry is selected
        const generalRegistry = document.getElementById('general-registry')?.value || '';
        if (generalRegistry.toUpperCase().includes('DCIV')) {
            // Silently decline without showing popup
            const handleDecline = () => {
                if (typeof options.onDecline === 'function') {
                    options.onDecline();
                    return;
                }
                if (options.refreshOnDecline !== false) {
                    window.location.reload();
                }
            };
            handleDecline();
            return;
        }

        const {
            refreshOnDecline = true,
            question = 'Does this file have property transaction details to capture?',
            confirmText = 'Yes',
            denyText = 'No',
        } = options;

        const handleDecline = () => {
            if (typeof options.onDecline === 'function') {
                options.onDecline();
                return;
            }

            if (refreshOnDecline) {
                window.location.reload();
            }
        };

        const openModal = () => {
            if (!payload || !payload.file_number) {
                console.error('Cannot open property transaction modal: missing payload or file number.', payload);
                handleDecline();
                return;
            }

            if (typeof openPropertyTransactionModal === 'function') {
                openPropertyTransactionModal(payload);
            } else {
                console.error('openPropertyTransactionModal function not found');
                handleDecline();
            }
        };

        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                title: 'Property Transactions',
                text: question,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: denyText,
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) {
                    openModal();
                } else {
                    handleDecline();
                }
            });
        }

        if (window.confirm(question)) {
            openModal();
        } else {
            handleDecline();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshSubmitButtonLabel();
        initializeEntityCustomerSection();

        // Pre-fill file number when arriving from the tracker via ?file_number=KN...
        if (window.prefillFileNumber && !window.isEditMode) {
            const fileNumberDisplay = document.getElementById('file-number-display');
            const fileNumberInput = document.getElementById('fileno');
            const archiveFileNoInput = document.getElementById('archive-file-no');
            if (fileNumberDisplay) {
                setAutoFilledValue(fileNumberDisplay, window.prefillFileNumber);
            }
            if (fileNumberInput) {
                fileNumberInput.value = window.prefillFileNumber;
            }
            if (archiveFileNoInput) {
                setAutoFilledValue(archiveFileNoInput, window.prefillFileNumber, { lock: true });
            }
        }

        if (window.isEditMode && window.editingRecord?.id && window.editingRecord?.file_number) {
            fetchTransactionsForRecordId(window.editingRecord.id, window.editingRecord.file_number);
        }

        const checkScriptLoaded = () => {
            if (typeof GlobalFileNoModal !== 'undefined') {
                initializeFileNumberSelector();
            } else {
                setTimeout(checkScriptLoaded, 100);
            }
        };

        checkScriptLoaded();

        function initializeFileNumberSelector() {
            const fileNumberDisplay = document.getElementById('file-number-display');
            const fileNumberInput = document.getElementById('fileno');
            const selectFileNumberBtn = document.getElementById('select-file-number-btn');
            const clearFileNumberBtn = document.getElementById('clear-file-number-btn');
            const relatedDisplay = document.getElementById('related-file-number-display');
            const relatedInput = document.getElementById('related-fileno');
            const selectRelatedBtn = document.getElementById('select-related-file-number-btn');
            const clearRelatedBtn = document.getElementById('clear-related-file-number-btn');

            const clearPrimarySelection = () => {
                resetSourceFileSelection();

                if (fileNumberDisplay) {
                    setAutoFilledValue(fileNumberDisplay, '', { lock: false });
                    enableManualEditing(fileNumberDisplay);
                }
                if (fileNumberInput) {
                    fileNumberInput.value = '';
                }
                const archiveFileNoInput = document.getElementById('archive-file-no');
                if (archiveFileNoInput) {
                    setAutoFilledValue(archiveFileNoInput, '', { lock: false });
                    enableManualEditing(archiveFileNoInput);
                }
                const awaitingInput = document.getElementById('awaiting-file-no');
                if (awaitingInput && awaitingInput.dataset?.locked !== 'true') {
                    awaitingInput.value = '';
                }
                const groupingIdInput = document.getElementById('grouping-id');
                if (groupingIdInput) {
                    groupingIdInput.value = '';
                }
                const trackingIdInput = document.getElementById('tracking-id');
                if (trackingIdInput) {
                    setAutoFilledValue(trackingIdInput, '', { lock: false });
                }
                hideIndexedFeedback();
                exitEditMode();
                resetGroupingState();
                clearArchiveFields();
                clearEntityCustomerFields({ silent: true });
                loadFileHistoryForFile('');
                updateCreateButtonState();
            };

            const clearRelatedSelection = () => {
                if (relatedDisplay) {
                    setAutoFilledValue(relatedDisplay, '', { lock: false });
                }
                if (relatedInput) {
                    relatedInput.value = '';
                }
            };

            if (selectFileNumberBtn) {
                selectFileNumberBtn.addEventListener('click', function () {
                    GlobalFileNoModal.open({
                        // This IS the file being indexed, so the year restriction applies.
                        blockedYears: nonIndexableYears(),
                        callback: async function (fileData) {
                            if (!fileData || !fileData.fileNumber) {
                                return;
                            }

                            const isEditing = editModeState.isEditing;

                            // Record which tab the number came from, and mirror it into
                            // General Registry, BEFORE anything below looks the file up —
                            // isKangisVariantMode() gates the duplicate prompt and every
                            // backfill, and both run further down this callback.
                            fileSelectionState.system = (fileData.tab || '').toLowerCase() || null;
                            if (!isEditing && fileSelectionState.system === 'kangis') {
                                selectKangisGeneralRegistry();
                            }

                            if (!isEditing) {
                                clearArchiveFields();
                                clearEntityCustomerFields({ silent: true });
                                resetGroupingState();
                                updateCreateButtonState();
                            } else {
                                console.info('Edit mode active: skipping auto-clear when selecting a file number.');
                            }

                            if (fileNumberDisplay) {
                                if (isEditing) {
                                    setAutoFilledValue(fileNumberDisplay, fileData.fileNumber, { lock: false });
                                    enableManualEditing(fileNumberDisplay);
                                } else {
                                    setAutoFilledValue(fileNumberDisplay, fileData.fileNumber);
                                }
                            }

                            if (fileNumberInput) {
                                fileNumberInput.value = fileData.fileNumber;
                            }

                            const archiveFileNoInput = document.getElementById('archive-file-no');
                            if (archiveFileNoInput) {
                                setAutoFilledValue(archiveFileNoInput, fileData.fileNumber, { lock: !isEditing });
                                if (isEditing) {
                                    enableManualEditing(archiveFileNoInput);
                                }
                            }

                            console.log('File number selected from modal, checking for duplicates:', fileData.fileNumber);
                            await checkFileAlreadyIndexed(fileData.fileNumber, {
                                source: isEditing ? 'edit_modal_selection' : 'modal_selection',
                            });
                            await checkTemporaryFileAssociationInline(fileData.fileNumber);

                            if (!isEditing) {
                                autoFillArchiveDetailsFromAPI(fileData.fileNumber);
                                // In KANGIS variant mode the user is creating a new physical file —
                                // do NOT backfill entity/customer details from the existing record.
                                if (!isKangisVariantMode()) {
                                    autoFillEntityCustomerFromAPI(fileData.fileNumber, { source: 'modal-selection' });
                                }
                            }

                            loadFileHistoryForFile(fileData.fileNumber);

                            const record = fileData.record || null;
                            setSourceFileSelection(record, fileData.fileNumber);

                            const trackingIdInput = document.getElementById('tracking-id');
                            if (trackingIdInput) {
                                if (isDedicatedUpdatePage()) {
                                    // Changing the file number on an update screen re-points the
                                    // record, so the tracking ID must follow the new number.
                                    await refreshTrackingIdForFileNumber(
                                        fileData.fileNumber,
                                        record?.tracking_id || ''
                                    );
                                } else if (record?.tracking_id) {
                                    setAutoFilledValue(trackingIdInput, record.tracking_id, { lock: false });
                                } else if (!isEditing) {
                                    applyAutofillLock(['tracking-id'], false);
                                    ensureTrackingIdExists();
                                }
                            }

                            // In KANGIS variant mode the user is creating a new physical file —
                            // do NOT backfill content fields (file title, holder, location, etc.)
                            // from the existing record. Archive/grouping fields are still filled
                            // by autoFillArchiveDetailsFromAPI above.
                            if (!isEditing && !isKangisVariantMode()) {
                                const fileTitleInput = document.getElementById('file-title');
                                if (fileTitleInput) {
                                    setAutoFilledValue(fileTitleInput, record?.file_name ?? '', { lock: false });
                                }

                                // Auto-populate Current Holder from file name/title
                                const currentHolderInput = document.getElementById('current-holder');
                                if (currentHolderInput && (record?.file_name ?? '')) {
                                    currentHolderInput.value = record.file_name;
                                }

                                const plotNumberInput = document.getElementById('plot-number');
                                if (plotNumberInput) {
                                    setAutoFilledValue(plotNumberInput, record?.plot_no ?? '');
                                }

                                const tpNumberInput = document.getElementById('tp-number');
                                if (tpNumberInput) {
                                    setAutoFilledValue(tpNumberInput, record?.tp_no ?? '');
                                }

                                const lpknInput = document.getElementById('lpkn-no');
                                if (lpknInput) {
                                    setAutoFilledValue(lpknInput, record?.lpkn_no ?? '');
                                }

                                const locationInput = document.getElementById('location');
                                const shouldAutoUpdateLocation = !record?.location;
                                if (locationInput && record?.location) {
                                    setAutoFilledValue(locationInput, record.location);
                                    parseLocationAndPopulateFields(record.location);
                                } else if (locationInput) {
                                    applyAutofillLock(['location'], false);
                                }

                                if (shouldAutoUpdateLocation && typeof window.__updateLocationField === 'function') {
                                    window.__updateLocationField();
                                }

                                if (record?.land_use || record?.landuse) {
                                    const landUseSelect = document.getElementById('land-use-type');
                                    if (landUseSelect) {
                                        const landUse = record.land_use || record.landuse;
                                        populateLandUseFromRecord(landUse);
                                    }
                                }
                            }

                            console.log('File number selected:', fileData.fileNumber, record);
                        },
                    });
                });
            }

            if (clearFileNumberBtn) {
                clearFileNumberBtn.addEventListener('click', function () {
                    clearPrimarySelection();
                });
            }

            if (selectRelatedBtn) {
                selectRelatedBtn.addEventListener('click', function () {
                    GlobalFileNoModal.open({
                        autoPopulateGenericFields: false,
                        callback: function (fileData) {
                            if (!fileData || !fileData.fileNumber) {
                                return;
                            }

                            if (relatedDisplay) {
                                setAutoFilledValue(relatedDisplay, fileData.fileNumber);
                            }

                            if (relatedInput) {
                                relatedInput.value = fileData.fileNumber;
                            }
                        },
                    });
                });
            }

            if (clearRelatedBtn) {
                clearRelatedBtn.addEventListener('click', function () {
                    clearRelatedSelection();
                });
            }
        }
    });

    function normalizeGeoText(value) {
        if (!value && value !== 0) {
            return '';
        }

        return value
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase()
            .replace(/[^A-Z0-9 ]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function parseLocationAndPopulateFields(location) {
        if (!location) return;

        console.log('parseLocationAndPopulateFields: Processing location:', location);

        if (!referenceDataStore.isLoaded) {
            console.log('parseLocationAndPopulateFields: Reference data not loaded, waiting...');
            loadReferenceData()
                .then(() => {
                    if (referenceDataStore.isLoaded) {
                        parseLocationAndPopulateFields(location);
                    }
                })
                .catch((error) => {
                    console.warn('Unable to load reference data for location auto-fill:', error);
                });
            return;
        }

        const locationParts = location
            .split(',')
            .map(part => part.trim())
            .filter(Boolean);

        console.log('parseLocationAndPopulateFields: Location parts:', locationParts);

        if (locationParts.length >= 2) {
            const district = locationParts[locationParts.length - 2];
            const lga = locationParts[locationParts.length - 1];

            console.log('parseLocationAndPopulateFields: Extracted district:', district, 'LGA:', lga);

            const districtSelect = document.getElementById('district-select');
            if (districtSelect && district) {
                console.log('parseLocationAndPopulateFields: Processing district', district, 'with', referenceDataStore.districts.length, 'available districts');
                const customDistrictContainer = document.getElementById('custom-district-container');
                const customDistrictInput = document.getElementById('custom-district-input');
                const districtMatched = populateSelectFromText(districtSelect, district, referenceDataStore.districts, 'name');

                if (districtMatched) {
                    console.log('parseLocationAndPopulateFields: District matched, hiding custom input');
                    if (customDistrictContainer) {
                        customDistrictContainer.classList.add('hidden');
                    }
                    if (customDistrictInput) {
                        customDistrictInput.value = '';
                    }
                } else if (districtSelect.querySelector('option[value="other"]')) {
                    console.log('parseLocationAndPopulateFields: No district match, using Other option with custom input:', district);
                    districtSelect.value = 'other';
                    setAutoFilledValue(districtSelect, districtSelect.value);
                    if (customDistrictContainer && customDistrictInput) {
                        customDistrictContainer.classList.remove('hidden');
                        customDistrictInput.value = district;
                    }
                }

                if (typeof window.__updateLocationField === 'function') {
                    window.__updateLocationField();
                }
            }

            const lgaSelect = document.getElementById('lga-city');
            if (lgaSelect && lga) {
                const lgaMatched = populateSelectFromText(lgaSelect, lga, referenceDataStore.lgas, 'name');

                if (!lgaMatched) {
                    const normalizedLga = normalizeGeoText(lga);
                    let fallbackOption = Array.from(lgaSelect.options).find(option => option.dataset?.dynamic === 'true' && normalizeGeoText(option.textContent) === normalizedLga);
                    if (!fallbackOption) {
                        fallbackOption = document.createElement('option');
                        fallbackOption.value = lga;
                        fallbackOption.textContent = lga;
                        fallbackOption.dataset.dynamic = 'true';
                        lgaSelect.appendChild(fallbackOption);
                    }
                    lgaSelect.value = fallbackOption.value;
                    setAutoFilledValue(lgaSelect, lgaSelect.value);
                }

                if (typeof window.__updateLocationField === 'function') {
                    window.__updateLocationField();
                }
            }
        }
    }

    function populateSelectFromText(selectElement, text, dataArray, textField) {
        if (!selectElement || !text || !dataArray || !Array.isArray(dataArray)) {
            console.log('populateSelectFromText: Invalid parameters', { selectElement: !!selectElement, text, dataArrayLength: dataArray?.length, textField });
            return false;
        }

        const normalizedText = normalizeGeoText(text);
        if (!normalizedText) {
            console.log('populateSelectFromText: No normalized text for', text);
            return false;
        }

        console.log('populateSelectFromText: Looking for', normalizedText, 'in', dataArray.length, 'items');

        let match = null;

        // Records written while the id/name mix-up was live hold a bare id ("16") in
        // district/lga. Resolve it back to its reference row so the field shows the
        // real name and is rewritten correctly on the next save, rather than the
        // number being carried forward again.
        const rawText = text.toString().trim();
        if (/^\d+$/.test(rawText)) {
            match = dataArray.find(item => String(item.id) === rawText) || null;
        }

        if (!match) {
            match = dataArray.find(item => {
                const itemText = item[textField] || item.name || item.label || '';
                const normalizedItem = normalizeGeoText(itemText);
                return normalizedItem === normalizedText;
            });
        }

        if (!match) {
            match = dataArray.find(item => {
                const itemText = item[textField] || item.name || item.label || '';
                const normalizedItem = normalizeGeoText(itemText);
                if (!normalizedItem) return false;
                return normalizedItem.includes(normalizedText) || normalizedText.includes(normalizedItem);
            });
        }

        if (!match) {
            match = dataArray.find(item => {
                const itemText = item[textField] || item.name || item.label || '';
                const normalizedItem = normalizeGeoText(itemText);
                if (!normalizedItem) return false;

                const textWords = normalizedText.split(' ');
                const itemWords = normalizedItem.split(' ');

                return textWords.some(textWord =>
                    itemWords.some(itemWord =>
                        itemWord.startsWith(textWord) || textWord.startsWith(itemWord)
                    )
                );
            });
        }

        if (!match && normalizedText.length >= 3) {
            match = dataArray.find(item => {
                const itemText = item[textField] || item.name || item.label || '';
                const normalizedItem = normalizeGeoText(itemText);
                if (!normalizedItem || normalizedItem.length < 3) return false;

                const minLength = Math.min(normalizedText.length, normalizedItem.length);
                const prefixLength = Math.floor(minLength * 0.6);

                return normalizedText.substring(0, prefixLength) === normalizedItem.substring(0, prefixLength)
                    || normalizedText.substring(-prefixLength) === normalizedItem.substring(-prefixLength);
            });
        }

        if (match) {
            console.log('populateSelectFromText: Found match', match);

            const matchName = match.name || match.label || match.value || text;
            const idValue = match.id != null ? String(match.id) : '';

            // Two conventions are in play: the Blade-rendered per-file selects are
            // name-keyed (<option value="ABBASA">) while the legacy JS-built ones are
            // id-keyed (<option value="16">). Reusing an option that is ALREADY in this
            // select adopts whichever convention it follows, so the stored value stays
            // consistent with what a manual pick would have produced. Preferring the id
            // outright used to append <option value="16">GAYA</option> to name-keyed
            // selects — the dropdown read correctly but the id was what got saved.
            let option = Array.from(selectElement.options).find(opt => {
                if (opt.value === '' || opt.value === 'other') return false;
                if (idValue && opt.value === idValue) return true;
                return normalizeGeoText(opt.value) === normalizeGeoText(matchName)
                    || normalizeGeoText(opt.textContent) === normalizeGeoText(matchName);
            });

            if (!option) {
                // Genuinely absent — follow whatever the existing options use.
                const optionsUseIds = Array.from(selectElement.options).some(opt =>
                    opt.value !== '' && opt.value !== 'other' && /^\d+$/.test(opt.value)
                );
                option = document.createElement('option');
                option.value = (optionsUseIds && idValue) ? idValue : matchName;
                option.textContent = matchName;
                option.dataset.dynamic = 'true';
                selectElement.appendChild(option);
            }

            selectElement.value = option.value;

            if (selectElement.id) {
                setAutoFilledValue(selectElement, selectElement.value);
            }

            return true;
        }

        console.log('populateSelectFromText: No match found for', normalizedText);
        return false;
    }

    function populateLandUseFromRecord(landUse, index = 0) {
        if (!landUse) return;

        const landUseSelect = document.getElementById(`land-use-type-${index}`);
        if (!landUseSelect) return;

        const upperLandUse = landUse.toString().toUpperCase().trim();
        const options = Array.from(landUseSelect.options);

        let matchingOption = options.find(option => option.value.toUpperCase() === upperLandUse)
            || options.find(option => option.textContent.toUpperCase() === upperLandUse)
            || options.find(option => upperLandUse.includes(option.value.toUpperCase()))
            || options.find(option => option.value.toUpperCase().includes(upperLandUse));

        if (!matchingOption && upperLandUse.includes('MIXED')) {
            matchingOption = options.find(option => option.value.toUpperCase().includes('MIXED'));
        }

        if (!matchingOption && upperLandUse.includes('COMMERCIAL') && upperLandUse.includes('RESIDENTIAL')) {
            matchingOption = options.find(option => option.value.toUpperCase().includes('COMMERCIAL') && option.value.toUpperCase().includes('RESIDENTIAL'));
        }

        if (!matchingOption) {
            matchingOption = options.find(option => option.value.toUpperCase().includes(upperLandUse.replace(/[^A-Z]/g, '')));
        }

        if (!matchingOption) {
            const newOption = document.createElement('option');
            newOption.value = upperLandUse;
            newOption.textContent = landUse.toString().trim();
            newOption.dataset.dynamic = 'true';
            landUseSelect.appendChild(newOption);
            matchingOption = newOption;
        }

        if (matchingOption) {
            // lock:false — Land Use Type is required and officer-editable, so it must
            // never be left disabled. The autofill lock disables selects outright, and
            // Alpine's x-model can afterwards clear the value, which stranded edit mode
            // with an empty + disabled + required field that blocked saving.
            setAutoFilledValue(landUseSelect, matchingOption.value, { lock: false });
        }
    }

    window.populateLandUseFromRecord = populateLandUseFromRecord;

    // Initialize form for standalone page or modal context
    function initializeCreateIndexingForm() {
        console.log('Initializing Create Indexing Form');

        const form = document.getElementById('new-file-form');
        if (!form) {
            return;
        }

        // Set default indexer if field is empty
        initializeDefaultIndexer();

        // Initialize file number duplicate checking regardless of context (standalone or modal)
        initializeFileNumberDuplicateCheck();

        // Load reference data for selects
        loadReferenceData().then(() => {
            console.log('Reference data loaded for file indexing form');
        }).catch(error => {
            console.error('Error loading reference data:', error);
        });

        // Initialize location auto-update if checkbox exists
        initializeLocationAutoUpdate();

        // Initialize Select2 for batch selection if it exists
        const batchSelect = document.getElementById('batch-selection');
        if (batchSelect && typeof $ !== 'undefined' && $.fn.select2) {
            $(batchSelect).select2({
                placeholder: 'Select an auto-assignment batch...',
                allowClear: true,
                width: '100%'
            });
        }

        console.log('Create indexing form initialization complete');
    }

    // Initialize file number duplicate checking
    function initializeFileNumberDuplicateCheck() {
        const fileNumberInput = document.getElementById('fileno');
        const archiveFileNoInput = document.getElementById('archive-file-no');

        if (fileNumberInput) {
            // Add input event listener for real-time duplicate checking
            fileNumberInput.addEventListener('input', function (e) {
                // Typing over the number drops the modal's KANGIS marker — but only for
                // real keystrokes: setAutoFilledValue() dispatches a synthetic 'input'
                // on this same field while applying a selection.
                if (e.isTrusted) {
                    fileSelectionState.system = null;
                }
                const fileNumber = e.target.value.trim();
                if (fileNumber) {
                    console.log('Checking for duplicate file number:', fileNumber);
                    checkFileAlreadyIndexed(fileNumber, { source: 'user_input' });
                } else {
                    hideIndexedFeedback();
                    exitEditMode();
                }
            });

            // Also check on blur for safety
            fileNumberInput.addEventListener('blur', function (e) {
                const fileNumber = e.target.value.trim();
                if (fileNumber) {
                    checkFileAlreadyIndexed(fileNumber, { source: 'user_blur' });
                }
            });

            // Check initial value if present
            const initialValue = fileNumberInput.value.trim();
            if (initialValue) {
                console.log('Checking initial file number value:', initialValue);
                checkFileAlreadyIndexed(initialValue, { source: 'initial_load' });
            }
        }

        // Also set up listener for archive file number field
        if (archiveFileNoInput) {
            archiveFileNoInput.addEventListener('input', function (e) {
                if (e.isTrusted) {
                    fileSelectionState.system = null;
                }
                const fileNumber = e.target.value.trim();
                if (fileNumber) {
                    console.log('Checking archive file number for duplicates:', fileNumber);
                    checkFileAlreadyIndexed(fileNumber, { source: 'archive_input' });
                } else {
                    hideIndexedFeedback();
                    exitEditMode();
                }
            });
        }
    }

    // Initialize default indexer from form data
    function initializeDefaultIndexer() {
        const form = document.getElementById('new-file-form');
        const indexedByField = document.getElementById('indexed-by');

        if (form && indexedByField) {
            const defaultIndexer = form.dataset.defaultIndexer;

            // If the field is empty or has placeholder value, set it to current user
            if (!indexedByField.value || indexedByField.value.trim() === '' || indexedByField.value === 'Current User') {
                if (defaultIndexer) {
                    indexedByField.value = defaultIndexer;
                    console.log('Set default indexer:', defaultIndexer);
                }
            }
        }
    }

    // Expose function to be called when autofill data doesn't include indexer
    window.ensureDefaultIndexer = function () {
        initializeDefaultIndexer();
    };

    // Populate form from grouping record
    function populateFromGroupingRecord(record) {
        if (!record) return;

        // Show Entity & Customer Cards
        document.querySelectorAll('.entity-customer-cards').forEach(el => el.classList.remove('hidden'));

        // Populate Awaiting File Number if empty
        const awaitingInput = document.getElementById('awaiting_fileno');
        if (awaitingInput && !awaitingInput.value && record.awaiting_fileno) {
            awaitingInput.value = record.awaiting_fileno;
        }

        // Populate System Batch Amount/No
        const sysBatchInput = document.getElementById('sys_batch_no');
        if (sysBatchInput && !sysBatchInput.value && record.sys_batch_no) {
            sysBatchInput.value = record.sys_batch_no;
        }

        // Populate Shelf Rack
        const shelfInput = document.getElementById('shelf_location');
        if (shelfInput && !shelfInput.value && record.shelf_rack) {
            shelfInput.value = record.shelf_rack;
        }

        // Populate Tracking ID
        const trackingInput = document.getElementById('tracking_id');
        if (trackingInput && !trackingInput.value && record.tracking_id) {
            trackingInput.value = record.tracking_id;
        }

        // Populate Land Use
        const landUseInput = document.getElementById('land_use_type');
        if (landUseInput && !landUseInput.value && record.landuse) {
            landUseInput.value = record.landuse;
        }

        // Populate Year
        const yearInput = document.getElementById('year');
        if (yearInput && !yearInput.value && record.year) {
            yearInput.value = record.year;
        }

        // Show toast or notification
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            Toast.fire({
                icon: 'info',
                title: 'Data populated from Grouping records'
            });
        }
        // Trigger variant check if it's a KANGIS file
        if (typeof window.checkKangisPlaceholderSiblings === 'function') {
            window.checkKangisPlaceholderSiblings();
        }
    }
    window.populateFromGroupingRecord = populateFromGroupingRecord;

    /**
     * Does this file need a Root of Title?
     *
     * Only two registry combinations do (client rule 2026-08-27):
     *   - SLTR              — General Registry OR Physical Registry says SLTR
     *   - Land / Registry 3 — General Registry "Land Registry" AND Physical
     *                         Registry "Registry 3 - Land"
     * Every other registry (KANGIS, DCIV, and the other Land physical registries)
     * neither shows the field nor validates it. The server applies the same test,
     * so a hidden field can never block a save.
     */
    function rootOfTitleApplies() {
        const norm = (el) => (el?.value || '').toUpperCase().replace(/\s+/g, ' ').trim();
        const squash = (v) => v.replace(/[^A-Z0-9]/g, '');

        const general = norm(document.getElementById('general-registry'));
        const physical = norm(document.getElementById('physical-registry'));

        const isSltr = general.includes('SLTR') || physical.includes('SLTR');
        const isLandRegistry3 = general.includes('LAND REGISTRY')
            && squash(physical).includes('REGISTRY3');

        return isSltr || isLandRegistry3;
    }

    /**
     * Show/hide the Root of Title field and its `required` attribute to match.
     *
     * The value is deliberately NOT cleared when the field is hidden: a registry
     * change must never silently destroy something the officer already typed, and
     * an existing record being edited under a non-applicable registry keeps the
     * root it was captured with.
     */
    function syncRootOfTitleVisibility() {
        const group = document.getElementById('root-of-title-group');
        const input = document.getElementById('root-of-title');
        if (!group || !input) return;

        const applies = rootOfTitleApplies();
        group.classList.toggle('hidden', !applies);
        // A hidden `required` input makes the browser refuse a native submit with
        // "not focusable", so the attribute has to come off with the field.
        if (applies) {
            input.setAttribute('required', 'required');
        } else {
            input.removeAttribute('required');
        }
    }
    window.rootOfTitleApplies = rootOfTitleApplies;

    function handleRegistryFieldToggling() {
        const registrySelect = document.getElementById('general-registry');
        const physicalRegistrySelect = document.getElementById('physical-registry');
        const cofoSection = document.getElementById('cofo-section');
        const fileHistorySection = document.getElementById('file-history-section');
        const dcivReasonContainer = document.getElementById('dciv-reason-container');
        const dcivReasonInput = document.getElementById('dciv-reason');
        const fileTitleLabel = document.querySelector('label[for="file-title"]');
        const kangisPlaceholderWrapper = document.getElementById('kangis-fileno-placeholder-wrapper');
        const kangisPlaceholderInput = document.getElementById('kangis-fileno-placeholder'); // hidden field
        const kangisPlaceholderPrefix = document.getElementById('kangis-fileno-prefix');
        const kangisPlaceholderSerial = document.getElementById('kangis-fileno-serial');
        const kangisPlaceholderPreview = document.getElementById('kangis-placeholder-preview');
        const kangisPlaceholderPreviewValue = document.getElementById('kangis-placeholder-preview-value');
        const occupancyPermitSection = document.getElementById('occupancy-permit-section');
        const rofoSection = document.getElementById('rofo-section');
        const titleStatusBlock = document.getElementById('ts-title-status-block');
        const titleStatusUpdateBlock = document.getElementById('ts-title-update-group');

        /** Assemble the prefix + serial into the hidden <input> value and update preview */
        function assembleKangisPlaceholder() {
            const prefix = kangisPlaceholderPrefix ? kangisPlaceholderPrefix.value.trim() : '';
            const serial = kangisPlaceholderSerial ? kangisPlaceholderSerial.value.trim() : '';
            const isTemp = document.getElementById('kangis-fileno-is-temp')?.checked || false;
            let assembled = (prefix && serial) ? (prefix === 'KN' ? `${prefix}${serial}` : `${prefix} ${serial}`) : '';
            if (assembled && isTemp) assembled += '(T)';
            if (kangisPlaceholderInput) kangisPlaceholderInput.value = assembled;
            if (kangisPlaceholderPreview && kangisPlaceholderPreviewValue) {
                if (assembled) {
                    kangisPlaceholderPreviewValue.textContent = assembled;
                    kangisPlaceholderPreview.classList.remove('hidden');
                } else {
                    kangisPlaceholderPreview.classList.add('hidden');
                }
            }
            return assembled;
        }

        /** Show/hide inline validation error for KANGIS FileNo Placeholder fields.
         *  Returns true if valid (both prefix and serial are filled), false otherwise. */
        function validateKangisPlaceholderFields(showError) {
            const errorEl  = document.getElementById('kangis-placeholder-error');
            const errorTxt = document.getElementById('kangis-placeholder-error-text');
            const prefix   = (kangisPlaceholderPrefix?.value || '').trim();
            const serial   = (kangisPlaceholderSerial?.value || '').trim();

            const prefixMissing = !prefix;
            const serialMissing = !serial;

            if (kangisPlaceholderPrefix) {
                kangisPlaceholderPrefix.classList.toggle('error-border', prefixMissing);
            }
            if (kangisPlaceholderSerial) {
                kangisPlaceholderSerial.classList.toggle('error-border', serialMissing);
            }

            if (errorEl) {
                if (showError && (prefixMissing || serialMissing)) {
                    if (errorTxt) {
                        if (prefixMissing && serialMissing) {
                            errorTxt.textContent = 'Please select a prefix and enter the serial number.';
                        } else if (prefixMissing) {
                            errorTxt.textContent = 'Please select a prefix.';
                        } else {
                            errorTxt.textContent = 'Please enter the serial number.';
                        }
                    }
                    errorEl.classList.remove('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [errorEl] });
                } else {
                    errorEl.classList.add('hidden');
                }
            }

            return !prefixMissing && !serialMissing;
        }

        /** Clear all KANGIS placeholder inline errors (called when KANGIS registry is deselected). */
        function clearKangisPlaceholderErrors() {
            document.getElementById('kangis-placeholder-error')?.classList.add('hidden');
            kangisPlaceholderPrefix?.classList.remove('error-border');
            kangisPlaceholderSerial?.classList.remove('error-border');
        }

        function updateState() {
            let isDciv = false;
            let isKangis = false;
            let isSltr = false;

            // Check General Registry
            if (registrySelect && registrySelect.value) {
                const regVal = registrySelect.value.toUpperCase();
                if (regVal.includes('DCIV')) isDciv = true;
                if (regVal.includes('KANGIS')) isKangis = true;
                if (regVal.includes('SLTR')) isSltr = true;

                // Deliberately moving off KANGIS overrides the marker the file number
                // selection set, so the variant guards follow the operator's choice.
                if (!isKangis) {
                    fileSelectionState.system = null;
                }
            }
            // Check Physical Registry (User specific request)
            if (physicalRegistrySelect && physicalRegistrySelect.value === 'DCIV Registry') {
                isDciv = true;
            }

            // Occupancy Permit / RoFO details are only relevant to Lands-type registries —
            // KANGIS and SLTR files don't carry these instrument types, so hide both cards
            // entirely (and reset any values already entered) when either is selected.
            const hideOccupancyAndRofo = isKangis || isSltr;
            [occupancyPermitSection, rofoSection].forEach((section) => {
                if (!section) return;
                section.classList.toggle('hidden', hideOccupancyAndRofo);
            });
            if (hideOccupancyAndRofo) {
                const rofoToggle = document.getElementById('has-rofo-toggle');
                const rofoContainer = document.getElementById('rofo-details-container');
                if (rofoToggle && rofoToggle.checked) {
                    rofoToggle.checked = false;
                    rofoContainer?.classList.add('hidden');
                    if (typeof clearRofoFields === 'function') clearRofoFields();
                }
                const occupancyToggle = document.getElementById('has-occupancy-permit-toggle');
                const occupancyContainer = document.getElementById('occupancy-permit-details-container');
                if (occupancyToggle && occupancyToggle.checked) {
                    occupancyToggle.checked = false;
                    occupancyContainer?.classList.add('hidden');
                    if (typeof clearOccupancyPermitFields === 'function') clearOccupancyPermitFields();
                }
            }

            // Title Status / Title Status Update don't apply to SLTR, KANGIS or DCIV
            // files — hide both cards and drop any selection already made.
            const hideTitleStatus = isSltr || isKangis || isDciv;
            [titleStatusBlock, titleStatusUpdateBlock].forEach((block) => {
                if (block) block.classList.toggle('hidden', hideTitleStatus);
            });
            if (hideTitleStatus && typeof window.tsClearSelections === 'function') {
                window.tsClearSelections();
            }

            // Handle File Title Label
            if (fileTitleLabel) {
                if (isDciv) {
                    fileTitleLabel.textContent = 'File Title / Complainant';
                } else {
                    fileTitleLabel.textContent = 'File Title';
                }
            }

            if (cofoSection) {
                if (isDciv) {
                    cofoSection.classList.add('opacity-50', 'pointer-events-none');
                    cofoSection.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = true);
                } else {
                    cofoSection.classList.remove('opacity-50', 'pointer-events-none');
                    cofoSection.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = false);
                }
            }

            if (fileHistorySection) {
                if (isDciv) {
                    fileHistorySection.classList.add('hidden');
                } else {
                    fileHistorySection.classList.remove('hidden');
                }
            }

            // Toggle Reason Input
            if (dcivReasonContainer) {
                if (isDciv) {
                    dcivReasonContainer.classList.remove('hidden');
                    if (dcivReasonInput) dcivReasonInput.setAttribute('required', 'required');
                } else {
                    dcivReasonContainer.classList.add('hidden');
                    if (dcivReasonInput) {
                        dcivReasonInput.removeAttribute('required');
                        dcivReasonInput.value = ''; // Clear value when hidden
                    }
                }
            }

            // Toggle Kangis FileNo Placeholder field
            if (kangisPlaceholderWrapper) {
                // In new_kn mode: placeholder is never shown — KN files don't need it
                if (window.isNewKnMode) {
                    kangisPlaceholderWrapper.classList.add('hidden');
                    if (kangisPlaceholderPrefix) { kangisPlaceholderPrefix.removeAttribute('required'); kangisPlaceholderPrefix.value = ''; }
                    if (kangisPlaceholderSerial) { kangisPlaceholderSerial.removeAttribute('required'); kangisPlaceholderSerial.value = ''; }
                    if (kangisPlaceholderInput) kangisPlaceholderInput.value = '';
                } else if (isKangis) {
                    kangisPlaceholderWrapper.classList.remove('hidden');
                    // Mark the serial input as required (the hidden field is assembled by JS)
                    if (kangisPlaceholderPrefix) {
                        kangisPlaceholderPrefix.setAttribute('required', 'required');
                        if (!kangisPlaceholderPrefix._kangisBlurBound) {
                            kangisPlaceholderPrefix._kangisBlurBound = true;
                            kangisPlaceholderPrefix.addEventListener('blur', () => validateKangisPlaceholderFields(true));
                            kangisPlaceholderPrefix.addEventListener('change', () => validateKangisPlaceholderFields(true));
                        }
                    }
                    if (kangisPlaceholderSerial) {
                        kangisPlaceholderSerial.setAttribute('required', 'required');
                        if (!kangisPlaceholderSerial._kangisBlurBound) {
                            kangisPlaceholderSerial._kangisBlurBound = true;
                            kangisPlaceholderSerial.addEventListener('blur', () => validateKangisPlaceholderFields(true));
                            kangisPlaceholderSerial.addEventListener('input', () => {
                                if (kangisPlaceholderSerial.value.trim()) validateKangisPlaceholderFields(false);
                            });
                        }
                    }
                    checkKangisPlaceholderSiblings();
                } else {
                    kangisPlaceholderWrapper.classList.add('hidden');
                    clearKangisPlaceholderErrors();
                    if (kangisPlaceholderPrefix) {
                        kangisPlaceholderPrefix.removeAttribute('required');
                        kangisPlaceholderPrefix.value = '';
                    }
                    if (kangisPlaceholderSerial) {
                        kangisPlaceholderSerial.removeAttribute('required');
                        kangisPlaceholderSerial.value = '';
                    }
                    if (kangisPlaceholderInput) kangisPlaceholderInput.value = '';
                    if (kangisPlaceholderPreview) kangisPlaceholderPreview.classList.add('hidden');
                    hidekangisPlaceholderFeedback();

                    // Reset the "Has New KANGIS FileNo" gate when leaving KANGIS registry
                    const hasNewKangisChk = document.getElementById('has-new-kangis-fileno');
                    const hasNewKangisFields = document.getElementById('has-new-kangis-fields');
                    if (hasNewKangisChk && hasNewKangisChk.checked) {
                        hasNewKangisChk.checked = false;
                        hasNewKangisFields?.classList.add('hidden');
                        const knDisp = document.getElementById('new_kangis_file_no_display');
                        const knHid = document.getElementById('new_kangis_file_no_hidden');
                        if (knDisp) { knDisp.value = ''; knDisp.classList.remove('error-border'); }
                        if (knHid) knHid.value = '';
                        document.getElementById('has-new-kangis-error')?.classList.add('hidden');
                        const knStat = document.getElementById('kn-fileno-status');
                        if (knStat) knStat.textContent = '';
                    }

                    // Reset the "Has Transaction" card when leaving KANGIS registry
                    const nkTxnChk = document.getElementById('has-new-kangis-transaction');
                    if (nkTxnChk && nkTxnChk.checked) {
                        nkTxnChk.checked = false;
                        document.getElementById('nk-transaction-card')?.classList.add('hidden');
                        if (typeof window.resetNewKangisTransactions === 'function') {
                            window.resetNewKangisTransactions();
                        }
                    }
                }
            }

            // Root of Title only applies to SLTR and to Land Registry / Registry 3.
            syncRootOfTitleVisibility();
        }

        function hidekangisPlaceholderFeedback() {
            const fb = document.getElementById('kangis-placeholder-feedback');
            if (fb) fb.classList.add('hidden');
        }

        async function checkKangisPlaceholderSiblings() {
            const fb = document.getElementById('kangis-placeholder-feedback');
            const fbText = document.getElementById('kangis-placeholder-feedback-text');
            if (!fb || !fbText) return;

            // Use the SELECTED file number (awaiting_fileno from grouping) or the main fileno input
            const selectedFileNumber = groupingState.record?.awaiting_fileno || document.getElementById('fileno')?.value || '';

            // Only show feedback for KANGIS registry files
            const registrySelect = document.getElementById('general-registry');
            const isKangis = registrySelect && registrySelect.value && registrySelect.value.toUpperCase().includes('KANGIS');

            if (!isKangis || !selectedFileNumber.trim()) {
                hidekangisPlaceholderFeedback();
                return;
            }

            // Updating a record that already exists: the update path never adds an _N
            // suffix or renames a sibling (FileIndexingController::update and the KANGIS
            // update endpoint both keep file_number as stored). The collision check
            // below found the record itself and announced a rename that never happens.
            const editingId = window.kangisUpdateMode
                ? (window.editingRecord?.id || editModeState.recordId)
                : (editModeState.isEditing ? editModeState.recordId : null);
            if (editingId) {
                const keptNumber = window.kangisUpdateMode?.fileNumber
                    || editModeState.fileNumber
                    || selectedFileNumber.trim();
                fb.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-green-700 bg-green-50 border-green-200';
                fbText.innerHTML = `Updating the existing record — the file number stays <strong>${keptNumber}</strong>. No suffix is added and no other record is renamed.`;
                fb.classList.remove('hidden');
                if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [fb] });
                return;
            }

            try {
                const resp = await fetch(`/api/kangis-placeholder/check?file_number=${encodeURIComponent(selectedFileNumber.trim())}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!resp.ok) return;
                const data = await resp.json();

                const bareExists = data.bare_exists || false;
                const suffixedCount = data.count || 0;
                const firstCollision = data.first_collision || false;

                if (firstCollision) {
                    // Case B: bare exists, no suffixed yet → bare→_1, new→_2
                    fb.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-amber-700 bg-amber-50 border-amber-200';
                    fbText.innerHTML = `<strong>${selectedFileNumber}</strong> already exists — it will be renamed to <strong>${selectedFileNumber}_1</strong> and this record will be saved as <strong>${selectedFileNumber}_2</strong>.`;
                    fb.classList.remove('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [fb] });
                } else if (bareExists || suffixedCount > 0) {
                    // Case C/D: suffixed variants already exist
                    const nextSuffix = `${selectedFileNumber}_${suffixedCount + 1}`;
                    fb.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-blue-700 bg-blue-50 border-blue-200';
                    fbText.innerHTML = `<strong>${suffixedCount}</strong> variant(s) exist — this record will be saved as <strong>${nextSuffix}</strong>.`;
                    fb.classList.remove('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [fb] });
                } else {
                    // No collision: file number doesn't exist yet, no suffix needed
                    fb.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-green-700 bg-green-50 border-green-200';
                    fbText.innerHTML = `No existing record for <strong>${selectedFileNumber}</strong> — will be saved as <strong>${selectedFileNumber}</strong> (no suffix needed).`;
                    fb.classList.remove('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [fb] });
                }
            } catch (_) {
                hidekangisPlaceholderFeedback();
            }
        }
        window.checkKangisPlaceholderSiblings = checkKangisPlaceholderSiblings;
        window.validateKangisPlaceholderFields = validateKangisPlaceholderFields;

        // Track which file numbers have already shown the "first variant" Swal
        let kangisFirstVariantShown = new Set();

        // Wire prefix + serial inputs: assemble → check siblings
        let kangisDebounceTimer = null;
        function onKangisInputChange() {
            const assembled = assembleKangisPlaceholder();
            // Clear inline errors once both fields are filled
            if (assembled) validateKangisPlaceholderFields(false);
            clearTimeout(kangisDebounceTimer);
            // We don't hide immediately if we have a file selected, just let the debounce run
            kangisDebounceTimer = setTimeout(() => {
                checkKangisPlaceholderSiblings();
            }, 600);
        }
        function onKangisInputBlur() {
            const assembled = assembleKangisPlaceholder();
            clearTimeout(kangisDebounceTimer);
            // Show inline errors on blur if fields are incomplete
            validateKangisPlaceholderFields(true);
            checkKangisPlaceholderSiblings();
        }

        if (kangisPlaceholderPrefix) {
            kangisPlaceholderPrefix.addEventListener('change', onKangisInputChange);
        }
        if (kangisPlaceholderSerial) {
            kangisPlaceholderSerial.addEventListener('input', onKangisInputChange);
            kangisPlaceholderSerial.addEventListener('blur', onKangisInputBlur);
        }
        const kangisTempCheckbox = document.getElementById('kangis-fileno-is-temp');
        if (kangisTempCheckbox) {
            kangisTempCheckbox.addEventListener('change', onKangisInputChange);
        }

        if (registrySelect) {
            registrySelect.addEventListener('change', updateState);
        }
        if (physicalRegistrySelect) {
            physicalRegistrySelect.addEventListener('change', updateState);
        }

        updateState(); // Initial check
    }

    /** Manage the New KANGIS File section (KN Series) in the file identification card. */
    function initializeNewKangisFields() {
        const registrySelect = document.getElementById('general-registry');
        const newKangisWrapper = document.getElementById('new-kangis-wrapper');
        const isNewKangisChk = document.getElementById('is-new-kangis-file');
        const newKangisFields = document.getElementById('new-kangis-fields');
        const kangisFileTypeHid = document.getElementById('kangis_file_type');
        const knInput = document.getElementById('new_kangis_file_no_input');
        const knHidden = document.getElementById('new_kangis_file_no_hidden');
        const knList = document.getElementById('kn-autocomplete-list');
        const knStatus = document.getElementById('kn-fileno-status');

        if (!registrySelect || !newKangisWrapper) return;

        function isKangisRegistrySelected() {
            return registrySelect.value && registrySelect.value.toUpperCase().includes('KANGIS');
        }

        function setKangisFileType() {
            if (!kangisFileTypeHid) return;
            if (isNewKangisChk && isNewKangisChk.checked) {
                kangisFileTypeHid.value = 'new_kangis';
            } else if (isKangisRegistrySelected()) {
                kangisFileTypeHid.value = 'KANGIS';
            } else {
                kangisFileTypeHid.value = 'MLS';
            }
        }

        function updateNewKangisWrapperVisibility() {
            if (isKangisRegistrySelected()) {
                // KANGIS Registry selected — hide the "New KANGIS File" checkbox (already a KANGIS file)
                newKangisWrapper.classList.add('hidden');
                if (isNewKangisChk) isNewKangisChk.checked = false;
                if (newKangisFields) newKangisFields.classList.add('hidden');
            } else {
                newKangisWrapper.classList.remove('hidden');
            }
            setKangisFileType();
        }

        if (isNewKangisChk) {
            isNewKangisChk.addEventListener('change', function () {
                if (this.checked) {
                    if (newKangisFields) newKangisFields.classList.remove('hidden');
                } else {
                    if (newKangisFields) newKangisFields.classList.add('hidden');
                    // Clear hidden fields
                    ['kangis_file_type', 'mls_file_no_hidden', 'kangis_file_no_hidden', 'new_kangis_file_no_hidden', 'kangis_applicant_phone_hidden'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    });
                    if (knInput) knInput.value = '';
                    if (knList) { knList.innerHTML = ''; knList.classList.add('hidden'); }
                    if (knStatus) knStatus.textContent = '';
                }
                setKangisFileType();
            });
        }

        registrySelect.addEventListener('change', updateNewKangisWrapperVisibility);
        updateNewKangisWrapperVisibility(); // Initial state

        // KN autocomplete
        if (!knInput || !knList) return;

        let knDebounce = null;
        knInput.addEventListener('input', function () {
            const q = this.value.trim();
            if (knHidden) knHidden.value = '';
            if (knStatus) knStatus.textContent = '';
            clearTimeout(knDebounce);
            if (q.length < 1) {
                knList.innerHTML = '';
                knList.classList.add('hidden');
                return;
            }
            knDebounce = setTimeout(() => {
                fetch(`/api/kn-grouping/search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
                })
                    .then(r => r.ok ? r.json() : { data: [] })
                    .then(data => {
                        const items = data.data || data || [];
                        if (!items.length) {
                            knList.innerHTML = '<div class="px-4 py-2 text-xs text-gray-400">No matches found</div>';
                            knList.classList.remove('hidden');
                            return;
                        }
                        knList.innerHTML = items.map(item =>
                            `<div class="kn-autocomplete-item px-4 py-2 text-sm cursor-pointer hover:bg-green-50 font-mono font-semibold text-green-800 border-b border-gray-100" data-value="${item.kn_awaiting_fileno}">${item.kn_awaiting_fileno}</div>`
                        ).join('');
                        knList.classList.remove('hidden');
                        knList.querySelectorAll('.kn-autocomplete-item').forEach(el => {
                            el.addEventListener('click', function () {
                                const val = this.getAttribute('data-value');
                                knInput.value = val;
                                if (knHidden) knHidden.value = val;
                                knList.innerHTML = '';
                                knList.classList.add('hidden');
                                if (knStatus) {
                                    knStatus.textContent = `✓ Selected: ${val}`;
                                    knStatus.className = 'mt-1 text-xs text-green-700 font-semibold';
                                }
                            });
                        });
                    })
                    .catch(() => {
                        knList.innerHTML = '';
                        knList.classList.add('hidden');
                    });
            }, 350);
        });

        document.addEventListener('click', function (e) {
            if (!knList.contains(e.target) && e.target !== knInput) {
                knList.classList.add('hidden');
            }
        });
    }

    function initializeDynamicRelatedFiles() {
        const wrapper = document.getElementById('related-files-wrapper');
        const addBtn = document.getElementById('add-related-file-btn');
        const hasRelatedCheckbox = document.getElementById('has-related-file');

        if (!wrapper || !addBtn || !hasRelatedCheckbox) return;

        // Toggle visibility
        hasRelatedCheckbox.addEventListener('change', function () {
            if (this.checked) {
                wrapper.classList.remove('hidden');
                addBtn.classList.remove('hidden');
            } else {
                wrapper.classList.add('hidden');
                addBtn.classList.add('hidden');
                // Clear inputs if unchecked? Maybe better to keep them unless submitted?
                // For now, just hide. backend checks input.
            }
        });



        // Helper to add a new related file row
        window.addRelatedFileRow = function (initialValue = '') {
            const rows = wrapper.querySelectorAll('.related-file-row');
            const template = rows[0];
            if (!template) {
                // Nothing to clone (the wrapper was emptied): build the first row from
                // the same markup the section partial renders, rather than doing nothing.
                const first = document.createElement('div');
                first.className = 'related-file-row flex gap-2';
                first.innerHTML =
                    '<div class="flex-grow"><input type="text" name="related_fileno[]" readonly'
                    + ' class="related-file-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50"'
                    + ' placeholder="Select related file number"></div>'
                    + '<div class="flex gap-2">'
                    + '<button type="button" class="select-related-btn inline-flex items-center px-3 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50" title="Select File Number"><i data-lucide="search" class="h-4 w-4 text-indigo-600"></i></button>'
                    + '<button type="button" class="clear-related-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50" title="Remove/Clear"><i data-lucide="eraser" class="h-4 w-4 text-red-600"></i></button>'
                    + '</div>';
                first.querySelector('.related-file-input').value = initialValue || '';
                wrapper.appendChild(first);
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
                return;
            }

            const newRow = template.cloneNode(true);
            const input = newRow.querySelector('.related-file-input');
            const index = rows.length; // 0 for the very first row, 1 for the second, etc.

            if (input) {
                input.value = initialValue || '';
                input.readOnly = true;
                input.classList.add('bg-gray-50');
            }

            // Ensure icons have correct colors
            const searchIcon = newRow.querySelector('.select-related-btn i');
            if (searchIcon) searchIcon.classList.add('text-indigo-600');

            const actionIcon = newRow.querySelector('.clear-related-btn i');
            if (actionIcon) {
                actionIcon.setAttribute('data-lucide', 'trash-2');
                actionIcon.classList.add('text-red-600');
            }

            wrapper.appendChild(newRow);
            if (window.lucide) window.lucide.createIcons();

            // If we added a row, make sure the checkbox is checked and visible
            if (!hasRelatedCheckbox.checked) {
                hasRelatedCheckbox.checked = true;
                hasRelatedCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        // Add new row via button
        addBtn.addEventListener('click', function () {
            window.addRelatedFileRow();
        });
        // Event delegation for Select and Clear buttons
        wrapper.addEventListener('click', function (e) {
            const target = e.target.closest('button');
            if (!target) return;

            const row = target.closest('.related-file-row');
            const input = row.querySelector('.related-file-input');

            if (target.classList.contains('select-related-btn')) {
                // Open modal
                if (typeof GlobalFileNoModal !== 'undefined') {
                    GlobalFileNoModal.open({
                        autoPopulateGenericFields: false,
                        callback: function (data) {
                            if (data && data.fileNumber) {
                                input.value = data.fileNumber;
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }
                    });
                } else {
                    console.error('GlobalFileNoModal is not defined');
                }
            } else if (target.classList.contains('clear-related-btn')) {
                if (wrapper.querySelectorAll('.related-file-row').length > 1) {
                    row.remove();
                } else {
                    input.value = '';
                }
            }
        });
    }

    function initializeBlockIndexingUI() {
        const indexingTypeSelect = document.getElementById('indexing-type');
        const holdersWrapper = document.getElementById('current-holders-wrapper');
        const originalHoldersWrapper = document.getElementById('original-holders-wrapper');
        const fileTitleInput = document.getElementById('file-title');

        if (!indexingTypeSelect || (!holdersWrapper && !originalHoldersWrapper)) return;

        // Function to find the add button (it might be dynamic or hidden initially)
        const getAddBtn = () => document.getElementById('add-holder-btn');
        const getAddOriginalBtn = () => document.getElementById('add-original-holder-btn');

        // Function to update first holder from file title
        function syncFirstHolder() {
            if (indexingTypeSelect.value === 'Regular' && fileTitleInput) {
                const firstInput = holdersWrapper.querySelector('.current-holder-input');
                if (firstInput) {
                    firstInput.value = fileTitleInput.value;
                }
            }
        }

        // Function to sync Related Files count with Holders count
        function syncRelatedFilesToHolders() {
            const indexingType = indexingTypeSelect.value;
            if (indexingType !== 'Block') return;

            const currentHolders = holdersWrapper.querySelectorAll('.current-holder-row').length;
            const relatedWrapper = document.getElementById('related-files-wrapper');
            if (!relatedWrapper) return;

            const currentRelated = relatedWrapper.querySelectorAll('.related-file-row').length;
            const targetRelated = currentHolders;

            if (currentRelated < targetRelated) {
                // Add missing related files
                for (let i = currentRelated; i < targetRelated; i++) {
                    if (typeof window.addRelatedFileRow === 'function') {
                        window.addRelatedFileRow();
                    }
                }
            } else if (currentRelated > targetRelated) {
                // Remove extra related files (from the end)
                const rows = relatedWrapper.querySelectorAll('.related-file-row');
                for (let i = currentRelated - 1; i >= targetRelated; i--) {
                    if (i > 0) { // Keep at least the first one if we can
                        rows[i].remove();
                    } else {
                        // If we are down to index 0, just clear it if target is 0
                        if (targetRelated === 0) {
                            const input = rows[0].querySelector('.related-file-input');
                            if (input) input.value = '';

                            // Also uncheck the checkbox if we are back to 1 holder
                            const hasRelatedCheckbox = document.getElementById('has-related-file');
                            if (hasRelatedCheckbox && hasRelatedCheckbox.checked) {
                                hasRelatedCheckbox.checked = false;
                                hasRelatedCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    }
                }
            }
        }

        // Use Delegation for BOTH Add and Remove buttons to be super robust
        if (holdersWrapper) {
            holdersWrapper.addEventListener('click', function (e) {
                const addBtnHit = e.target.closest('#add-holder-btn');
                const removeBtnHit = e.target.closest('.remove-holder-btn');

                if (addBtnHit) {
                    const row = document.createElement('div');
                    row.className = 'current-holder-row flex gap-2';
                    row.innerHTML = `
                        <input type="text" name="current_holder[]" 
                            class="current-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <button type="button" class="remove-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50">
                            <i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>
                        </button>
                    `;
                    holdersWrapper.appendChild(row);
                    if (window.lucide) window.lucide.createIcons();

                    // Sync related files
                    syncRelatedFilesToHolders();

                    // Refresh Related Details button visibility
                    if (typeof updateRelatedButtonVisibility === 'function') {
                        updateRelatedButtonVisibility();
                    }
                } else if (removeBtnHit) {
                    removeBtnHit.closest('.current-holder-row').remove();

                    // Sync related files
                    syncRelatedFilesToHolders();

                    if (typeof updateRelatedButtonVisibility === 'function') {
                        updateRelatedButtonVisibility();
                    }
                }
            });

            // Delegate input for holders to update button visibility
            holdersWrapper.addEventListener('input', function (e) {
                if (e.target.classList.contains('current-holder-input')) {
                    if (typeof updateRelatedButtonVisibility === 'function') {
                        updateRelatedButtonVisibility();
                    }
                }
            });
        }

        if (originalHoldersWrapper) {
            originalHoldersWrapper.addEventListener('click', function (e) {
                const addBtnHit = e.target.closest('#add-original-holder-btn');
                const removeBtnHit = e.target.closest('.remove-original-holder-btn');

                if (addBtnHit) {
                    const row = document.createElement('div');
                    row.className = 'original-holder-row flex gap-2';
                    row.innerHTML = `
                        <input type="text" name="original_holder[]" 
                            class="original-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <button type="button" class="remove-original-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50">
                            <i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>
                        </button>
                    `;
                    originalHoldersWrapper.appendChild(row);
                    if (window.lucide) window.lucide.createIcons();
                } else if (removeBtnHit) {
                    removeBtnHit.closest('.original-holder-row').remove();
                }
            });
        }

        // Sync file title
        if (fileTitleInput) {
            fileTitleInput.addEventListener('input', syncFirstHolder);
        }

        // Indexing type change
        indexingTypeSelect.addEventListener('change', function () {
            const isBlock = this.value === 'Block';
            const addBtn = getAddBtn();
            const addOriginalBtn = getAddOriginalBtn();

            // Toggle add button
            if (addBtn) addBtn.style.display = isBlock ? 'inline-flex' : 'none';
            if (addOriginalBtn) addOriginalBtn.style.display = isBlock ? 'inline-flex' : 'none';

            if (!isBlock) {
                // Reset to single holder in Regular mode
                if (holdersWrapper) {
                    const rows = holdersWrapper.querySelectorAll('.current-holder-row');
                    rows.forEach((row, index) => {
                        if (index > 0) row.remove();
                    });
                }
                if (originalHoldersWrapper) {
                    const rows = originalHoldersWrapper.querySelectorAll('.original-holder-row');
                    rows.forEach((row, index) => {
                        if (index > 0) row.remove();
                    });
                }
                syncFirstHolder();
            }

            // Sync related files
            syncRelatedFilesToHolders();

            // Refresh Related Details button visibility
            if (typeof updateRelatedButtonVisibility === 'function') {
                updateRelatedButtonVisibility();
            }

            // Toggle Group Title Field
            const groupTitleContainer = document.getElementById('group-title-container');
            if (groupTitleContainer) {
                if (isBlock) {
                    groupTitleContainer.classList.remove('hidden');
                } else {
                    groupTitleContainer.classList.add('hidden');
                }
            }
        });

        // Set initial state
        const isBlockInitial = indexingTypeSelect.value === 'Block';
        const addBtnInitial = getAddBtn();
        const addOriginalBtnInitial = getAddOriginalBtn();
        if (addBtnInitial) addBtnInitial.style.display = isBlockInitial ? 'inline-flex' : 'none';
        if (addOriginalBtnInitial) addOriginalBtnInitial.style.display = isBlockInitial ? 'inline-flex' : 'none';

        if (!isBlockInitial) {
            syncFirstHolder();
        } else {
            syncRelatedFilesToHolders();
        }
    }

    function initializeRelatedFilesDetails() {
        const openBtn = document.getElementById('open-related-details-btn');
        const modal = document.getElementById('related-file-details-modal');
        const container = document.getElementById('related-file-details-container');
        const saveBtn = document.getElementById('save-related-details-btn');
        const cancelBtn = document.getElementById('cancel-related-details-btn');
        const wrapper = document.getElementById('related-files-wrapper');

        if (!openBtn || !modal || !container || !saveBtn || !cancelBtn) return;

        // Persistent storage for details entered in the modal
        // Key is either holder name (Block) or file number (Regular)
        let detailsCache = new Map();

        // Load existing related details if in edit mode
        if (window.isEditMode && window.editingRecord && Array.isArray(window.editingRecord.related_details)) {
            window.editingRecord.related_details.forEach(detail => {
                const indexingTypeSelect = document.getElementById('indexing-type');
                const isBlock = indexingTypeSelect && indexingTypeSelect.value === 'Block';
                // For Block indexing, key is usually the entity name (holder)
                // For Regular indexing, key is the related file number
                const key = isBlock ? (detail.entity_name || detail.customer_name) : detail.related_fileno;
                if (key) {
                    detailsCache.set(key, {
                        file_title: detail.file_title || '',
                        location: detail.location || '',
                        plot_number: detail.plot_number || '',
                        tp_no: detail.tp_no || '',
                        lpkn_no: detail.lpkn_no || '',
                        file_number: detail.file_number || ''
                    });
                }
            });
            console.log('Related details cache initialized from editing record:', detailsCache.size, 'items');
        }

        // Helper to collect current values from the modal and save them to cache
        function syncModalToCache() {
            const sections = container.querySelectorAll('.bg-white.rounded-lg.border');
            sections.forEach(section => {
                const indexingTypeSelect = document.getElementById('indexing-type');
                const isBlock = indexingTypeSelect && indexingTypeSelect.value === 'Block';

                const key = isBlock
                    ? section.querySelector('input[name$="][holder]"]')?.value
                    : section.querySelector('input[name$="][file_number]"]')?.value;

                if (!key) return;

                detailsCache.set(key, {
                    file_title: section.querySelector('input[name$="][file_title]"]')?.value || '',
                    location: section.querySelector('input[name$="][location]"]')?.value || '',
                    plot_number: section.querySelector('input[name$="][plot_number]"]')?.value || '',
                    tp_no: section.querySelector('input[name$="][tp_no]"]')?.value || '',
                    lpkn_no: section.querySelector('input[name$="][lpkn_no]"]')?.value || '',
                    dob: section.querySelector('input[name$="][dob]"]')?.value || '',
                    nin: section.querySelector('input[name$="][nin]"]')?.value || '',
                    tin: section.querySelector('input[name$="][tin]"]')?.value || '',
                    phone: section.querySelector('input[name$="][phone]"]')?.value || '',
                    email: section.querySelector('input[name$="][email]"]')?.value || '',
                    rc_no: section.querySelector('input[name$="][rc_no]"]')?.value || '',
                    country_code: section.querySelector('select[name$="][country_code]"]')?.value || '',
                    residence_address: section.querySelector('textarea[name$="][residence_address]"]')?.value || '',
                    // Task #22: backfilled indexing metadata (scoped to the related file, never the parent)
                    current_holder: section.querySelector('input[name$="][current_holder]"]')?.value || '',
                    original_holder: section.querySelector('input[name$="][original_holder]"]')?.value || '',
                    no_of_transactions: section.querySelector('input[name$="][no_of_transactions]"]')?.value || '',
                    prop_id: section.querySelector('input[name$="][prop_id]"]')?.value || '',
                    is_indexed: section.querySelector('input[name$="][is_indexed]"]')?.value || '',
                });
            });
        }

        // Toggle button visibility based on related file inputs OR holders (if Block indexing)
        function updateButtonVisibility() {
            const indexingTypeSelect = document.getElementById('indexing-type');
            const isBlock = indexingTypeSelect && indexingTypeSelect.value === 'Block';

            let hasValue = false;

            if (isBlock) {
                const holderInputs = document.querySelectorAll('.current-holder-input');
                holderInputs.forEach(input => {
                    if (input.value.trim() !== '') hasValue = true;
                });
            } else {
                const inputs = document.querySelectorAll('input[name="related_fileno[]"]');
                inputs.forEach(input => {
                    if (input.value.trim() !== '') hasValue = true;
                });
            }

            // Show button if there is something to detail
            if (hasValue) {
                openBtn.classList.remove('hidden');
            } else {
                openBtn.classList.add('hidden');
            }
        }

        // Expose to higher scope for holders adjustment
        window.updateRelatedButtonVisibility = updateButtonVisibility;

        // Monitoring input changes to update button visibility
        if (wrapper) {
            wrapper.addEventListener('input', updateButtonVisibility);
            wrapper.addEventListener('click', function (e) {
                // Slight delay to allow DOM updates
                setTimeout(updateButtonVisibility, 100);
            });
        }

        // Initial check
        updateButtonVisibility();

        // ----- Task #22: Related File No. backfill from the KANGIS index -----
        // Resolve the same endpoint the main form uses to check whether a file is already indexed.
        const relatedCheckIndexedUrl = (indexedFeedbackState.form
                || document.getElementById('new-file-form'))?.dataset?.checkIndexedUrl
            || '/fileindex/check-indexed';

        // Space-sensitive file-number comparison.
        // The SPACE is significant for KANGIS files: "KN 5217" (old MLS-KN) is a DIFFERENT
        // file from "KN5217" (new KANGIS format) and must not be treated as the same.
        // Dashes / slashes / a trailing (T) are NOT significant; land numbers have no spaces,
        // so they are unaffected by preserving spaces here.
        function fileNoMatchesStrict(a, b) {
            const norm = (s) => String(s || '')
                .toUpperCase().trim()
                .replace(/\(\s*T\s*\)\s*$/i, '')   // optional temporary suffix
                .replace(/[\-\/]/g, '')             // dashes & slashes not significant
                .replace(/\s+/g, ' ')               // collapse internal whitespace, but KEEP it
                .trim();
            return norm(a) !== '' && norm(a) === norm(b);
        }

        // Look up a related file in the index WITHOUT any of the side effects of
        // checkFileAlreadyIndexed (no edit-mode prompt, no main-form feedback, no redirect).
        async function lookupRelatedIndexed(fileNo) {
            const raw = (fileNo || '').trim();
            if (!raw) return null;
            const stripped = raw.replace(/\(\s*T\s*\)\s*$/i, '').trim();
            const registry = document.getElementById('general-registry')?.value || '';

            // Try the RAW value first (e.g. "RES-2026-2124(T)") so temporary files whose
            // (T) is stored as part of file_number still match; then the stripped base.
            // checkIndexed's buildFileNumberVariants also expands each into its variants.
            const candidates = stripped && stripped !== raw ? [raw, stripped] : [raw];
            for (const candidate of candidates) {
                const url = relatedCheckIndexedUrl.includes('?')
                    ? `${relatedCheckIndexedUrl}&fileno=${encodeURIComponent(candidate)}&registry=${encodeURIComponent(registry)}`
                    : `${relatedCheckIndexedUrl}?fileno=${encodeURIComponent(candidate)}&registry=${encodeURIComponent(registry)}`;
                try {
                    const payload = await fetchJson(url);
                    // Guard: checkIndexed collapses spaces, so "KN 5217" can wrongly return the
                    // "KN5217" record. Only accept it if the spacing actually matches.
                    if (payload && payload.exists && payload.record
                        && fileNoMatchesStrict(raw, payload.record.file_number)) {
                        return payload.record;
                    }
                } catch (e) {
                    console.error('Related file index lookup failed for', candidate, e);
                }
            }

            // Fallback: true temporary records are stored with file_number = NULL and the number
            // held in temp_file_no, so checkIndexed (which matches file_number) misses them.
            // check-temp-association matches by temp_file_no and returns the full record.
            try {
                const tmp = await fetchJson(
                    `/fileindexing/api/check-temp-association?file_number=${encodeURIComponent(raw)}`
                );
                if (tmp && tmp.has_associated_temp && tmp.temp_record
                    && fileNoMatchesStrict(raw, tmp.temp_record.temp_file_no || tmp.temp_record.file_number)) {
                    return tmp.temp_record;
                }
            } catch (e) {
                console.error('Related temp-file lookup failed for', raw, e);
            }
            return null;
        }

        // Pull transaction history for a related file (read-only; does NOT touch the parent prop-id-field).
        // Tries the raw value first, then the (T)-stripped base, since history is usually keyed
        // on the base MLS number rather than the temporary form.
        async function lookupRelatedHistory(fileNo) {
            const raw = (fileNo || '').trim();
            if (!raw) return [];
            const base = raw.replace(/\(\s*T\s*\)\s*$/i, '').trim();
            const candidates = base && base !== raw ? [raw, base] : [raw];
            for (const candidate of candidates) {
                try {
                    const resp = await fetchJson(`/api/file-history?mlsFNo=${encodeURIComponent(candidate)}`);
                    if (resp && resp.success === true && Array.isArray(resp.data) && resp.data.length) {
                        return resp.data;
                    }
                } catch (e) {
                    console.error('Related file history lookup failed for', candidate, e);
                }
            }
            return [];
        }

        // Original holder = the granting/disposing party on the earliest recorded transaction.
        function deriveOriginalHolderFromEntry(entry) {
            if (!entry || !entry.parties || typeof entry.parties !== 'object') return null;
            const normalize = (v) => {
                if (v === null || v === undefined) return null;
                const t = String(v).trim();
                return (t === '' || t.toLowerCase() === 'null') ? null : t;
            };
            const partyEntries = Object.entries(entry.parties)
                .map(([role, value]) => ({ role, value: normalize(value) }))
                .filter((i) => i.value !== null);
            if (!partyEntries.length) return null;
            const findByRegex = (re) => partyEntries.find((i) => typeof i.role === 'string' && re.test(i.role.trim()));
            const priority = [/grantor/i, /assignor/i, /transferor/i, /vendor/i, /donor/i, /lessor/i, /surrenderor/i, /mortgagor/i];
            for (const re of priority) {
                const match = findByRegex(re);
                if (match) return match.value;
            }
            return partyEntries[0].value;
        }

        // Set a per-section hidden field (scoped to related_details[index][...]).
        function setSectionHidden(section, suffix, value) {
            const el = section.querySelector(`input[name$="][${suffix}]"]`);
            if (el) el.value = value == null ? '' : String(value);
        }

        // Backfill a single related-file section from the index + transaction history.
        // - Fills File Title (Current Holder) + Original Holder + detail fields when empty
        //   (never overwrites user input).
        // - Renders the transaction timeline.
        // - Stores prop_id against THIS related file only — never the parent prop-id-field.
        async function backfillRelatedSection(section) {
            if (!section) return;
            const fileNo = (section.dataset.fileNo || '').trim();
            if (!fileNo) return;

            const statusEl = section.querySelector('.related-backfill-status');
            const timelineEl = section.querySelector('.related-file-timeline');

            if (statusEl) {
                statusEl.innerHTML = '<span class="inline-flex items-center gap-1 text-xs text-gray-500">'
                    + '<span class="loading-spinner"></span> Checking index…</span>';
            }

            let record = null;
            let entries = [];
            try {
                [record, entries] = await Promise.all([
                    lookupRelatedIndexed(fileNo),
                    lookupRelatedHistory(fileNo),
                ]);
            } catch (e) {
                console.error('Related file backfill failed for', fileNo, e);
            }

            const indexed = !!record;

            const fillIfEmpty = (suffix, value) => {
                const el = section.querySelector(`input[name$="][${suffix}]"]`);
                if (el && !el.value.trim() && value != null && String(value).trim() !== '') {
                    el.value = String(value).trim();
                }
            };

            // Derive holders + transaction count from history.
            const sortedEntries = sortFileHistoryEntries(entries);
            const txnCount = sortedEntries.length;
            const latest = txnCount ? sortedEntries[txnCount - 1] : null;
            const earliest = txnCount ? sortedEntries[0] : null;
            const currentHolderObj = latest ? deriveCurrentHolder(latest) : null;
            const currentHolder = (currentHolderObj && currentHolderObj.name)
                || (record && record.current_holder) || '';
            const originalHolder = (record && record.original_holder)
                || deriveOriginalHolderFromEntry(earliest) || '';

            // Backfill detail fields from the existing indexed record (blank if not indexed).
            // File Title doubles as Current Holder; only fill empty fields so user edits stay.
            if (record) {
                fillIfEmpty('file_title', record.file_title || currentHolder);
                fillIfEmpty('location', record.location);
                fillIfEmpty('plot_number', record.plot_number);
                fillIfEmpty('tp_no', record.tp_no);
                fillIfEmpty('lpkn_no', record.lpkn_no);
            }
            // Original Holder is an editable field — fill only when blank.
            fillIfEmpty('original_holder', originalHolder);

            // Persist read-only metadata against this related file only.
            setSectionHidden(section, 'current_holder', currentHolder);
            setSectionHidden(section, 'no_of_transactions', txnCount);
            setSectionHidden(section, 'is_indexed', indexed ? '1' : '0');
            const propIdEntry = sortedEntries.find((en) => en && en.prop_id != null && String(en.prop_id).trim() !== '');
            setSectionHidden(section, 'prop_id', propIdEntry ? String(propIdEntry.prop_id).trim() : '');

            // Status badge.
            if (statusEl) {
                statusEl.innerHTML = indexed
                    ? '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold px-2.5 py-1">'
                        + '<i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i> Indexed — auto-filled</span>'
                    : '<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-1">'
                        + '<i data-lucide="circle-dashed" class="h-3.5 w-3.5"></i> Not indexed</span>';
            }

            // Transaction timeline (always shown, with a count badge / "no transactions" state).
            if (timelineEl) {
                const body = timelineEl.querySelector('.related-timeline-body');
                const countEl = timelineEl.querySelector('.related-timeline-count');
                if (countEl) {
                    countEl.textContent = txnCount
                        ? `${txnCount} transaction${txnCount === 1 ? '' : 's'}`
                        : 'No transactions found';
                    countEl.classList.toggle('bg-blue-100', !!txnCount);
                    countEl.classList.toggle('text-blue-700', !!txnCount);
                    countEl.classList.toggle('bg-gray-100', !txnCount);
                    countEl.classList.toggle('text-gray-500', !txnCount);
                }
                if (body) {
                    body.innerHTML = txnCount
                        ? buildFileHistoryTimelineMarkup(sortedEntries)
                        : '<p class="text-sm text-gray-500">No transaction(s) found for this file.</p>';
                }
                timelineEl.classList.remove('hidden');
            }

            if (window.lucide) window.lucide.createIcons();
        }

        // Open Modal
        openBtn.addEventListener('click', function () {
            // Sync any currently open fields before regenerating
            syncModalToCache();

            const indexingTypeSelect = document.getElementById('indexing-type');
            const isBlock = indexingTypeSelect && indexingTypeSelect.value === 'Block';

            let items = [];

            if (isBlock) {
                const holderInputs = document.querySelectorAll('.current-holder-input');
                holderInputs.forEach(input => {
                    const val = input.value.trim();
                    if (val) items.push({ type: 'holder', name: val });
                });

                if (items.length === 0) {
                    alert('Please enter at least one Current Holder for Block Indexing.');
                    return;
                }
            } else {
                const fnoInputs = document.querySelectorAll('input[name="related_fileno[]"]');
                fnoInputs.forEach(input => {
                    const val = input.value.trim();
                    if (val) items.push({ type: 'file', fileNo: val });
                });

                if (items.length === 0) {
                    alert('Please enter at least one related file number first.');
                    return;
                }
            }

            // Generate Forms
            container.innerHTML = '';
            items.forEach((item, index) => {
                const key = isBlock ? item.name : item.fileNo;
                const cached = detailsCache.get(key) || {};

                const section = document.createElement('div');
                section.className = 'bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden mb-6 last:mb-0';
                section.dataset.relatedIndex = index;
                if (!isBlock) {
                    section.dataset.fileNo = item.fileNo;
                }

                const title = isBlock ? `Record for Holder: ${item.name}` : `Related File: ${item.fileNo}`;
                const icon = isBlock ? 'user' : 'file-text';

                // For block indexing, we don't need to re-enter the file number per holder
                const fileNoHtml = isBlock
                    ? `<input type="hidden" name="related_details[${index}][holder]" value="${item.name}">`
                    : `<input type="hidden" name="related_details[${index}][file_number]" value="${item.fileNo}">`;

                // Task #22: only Related File No. (Regular mode) entries can be backfilled from the index.
                const statusHtml = isBlock ? '' : `<div class="related-backfill-status text-xs"></div>`;
                // current_holder is captured implicitly by the File Title field; the rest stay hidden.
                const indexedHiddenHtml = isBlock ? '' : `
                            <input type="hidden" name="related_details[${index}][current_holder]" value="${escapeAttribute(cached.current_holder || '')}">
                            <input type="hidden" name="related_details[${index}][no_of_transactions]" value="${escapeAttribute(cached.no_of_transactions || '')}">
                            <input type="hidden" name="related_details[${index}][prop_id]" value="${escapeAttribute(cached.prop_id || '')}">
                            <input type="hidden" name="related_details[${index}][is_indexed]" value="${escapeAttribute(cached.is_indexed || '')}">`;
                // File Title doubles as the Current Holder label for Regular related files.
                const fileTitleLabel = isBlock ? 'File Title' : 'File Title <span class="text-gray-400 font-normal">(Current Holder)</span>';
                // Editable Original Holder field (Regular mode only).
                const originalHolderFieldHtml = isBlock ? '' : `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Original Holder</label>
                                <input type="text" name="related_details[${index}][original_holder]" value="${escapeAttribute(cached.original_holder || '')}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-white">
                            </div>`;
                const displayAndTimelineHtml = isBlock ? '' : `
                        <details class="related-file-timeline hidden mt-3 rounded-lg border border-blue-100 bg-white">
                            <summary class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer list-none select-none text-sm font-semibold text-blue-800">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="history" class="h-4 w-4 text-blue-600"></i> Transaction Timeline
                                    <span class="related-timeline-count inline-flex items-center rounded-full text-[11px] font-semibold px-2 py-0.5"></span>
                                </span>
                                <i data-lucide="chevron-down" class="h-4 w-4 text-blue-400"></i>
                            </summary>
                            <div class="related-timeline-body px-4 pb-4 pt-2 border-t border-blue-100 max-h-72 overflow-y-auto"></div>
                        </details>`;

                section.innerHTML = `
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                             <div class="bg-blue-100 p-1.5 rounded-full">
                                <i data-lucide="${icon}" class="w-4 h-4 text-blue-600"></i>
                             </div>
                             <h4 class="font-bold text-gray-800 text-sm">${title}</h4>
                        </div>
                        ${statusHtml}
                    </div>
                    <div class="p-4 bg-gray-50/50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            ${fileNoHtml}
                            ${indexedHiddenHtml}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">${fileTitleLabel} <span class="text-red-500">*</span></label>
                                <input type="text" name="related_details[${index}][file_title]" value="${cached.file_title || (isBlock ? item.name : '')}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-white" required>
                            </div>
                            ${originalHolderFieldHtml}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location <span class="text-red-500">*</span></label>
                                <input type="text" name="related_details[${index}][location]" value="${cached.location || ''}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-white" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Plot Number</label>
                                <input type="text" name="related_details[${index}][plot_number]" value="${cached.plot_number || ''}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">TP Number</label>
                                <input type="text" name="related_details[${index}][tp_no]" value="${cached.tp_no || ''}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">LPKN Number</label>
                                <input type="text" name="related_details[${index}][lpkn_no]" value="${cached.lpkn_no || ''}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-white">
                            </div>
                        </div>
                        ${displayAndTimelineHtml}
                    </div>
                `;
                container.appendChild(section);
            });

            if (window.lucide) window.lucide.createIcons();
            modal.classList.remove('hidden');

            // Task #22: backfill each indexed Related File No. (Regular mode) on open.
            if (!isBlock) {
                container.querySelectorAll('[data-file-no]').forEach((sec) => {
                    backfillRelatedSection(sec);
                });
            }
        });

        // Close Modal
        function closeModal() {
            syncModalToCache(); // Save state even on close
            modal.classList.add('hidden');
        }

        cancelBtn.addEventListener('click', closeModal);

        // Save Details
        saveBtn.addEventListener('click', async function () {
            // Task #22: backfill executes on save — re-check the index for every Related File No.
            // (catches files typed/changed since the modal opened) and auto-fill empty fields.
            const indexedSections = Array.from(container.querySelectorAll('[data-file-no]'));
            if (indexedSections.length) {
                const originalSaveText = saveBtn.textContent;
                saveBtn.disabled = true;
                saveBtn.textContent = 'Checking index…';
                try {
                    await Promise.all(indexedSections.map((sec) => backfillRelatedSection(sec)));
                } catch (e) {
                    console.error('Related file backfill on save failed:', e);
                }
                saveBtn.disabled = false;
                saveBtn.textContent = originalSaveText;
            }

            let isValid = true;
            container.querySelectorAll('input[required]').forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields.');
                return;
            }

            syncModalToCache();
            modal.classList.add('hidden');

            // feedback
            const originalHtml = openBtn.innerHTML;
            openBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4 mr-2"></i> Details Staged';
            openBtn.classList.add('bg-green-50', 'text-green-700', 'border-green-300');
            if (window.lucide) window.lucide.createIcons();

            setTimeout(() => {
                openBtn.innerHTML = originalHtml;
                openBtn.classList.remove('bg-green-50', 'text-green-700', 'border-green-300');
                if (window.lucide) window.lucide.createIcons();
            }, 2000);
        });
    }

    /** "Has New KANGIS FileNo" gate (normal mode only). A New KANGIS FileNo is the
     *  KN-series number — distinct from the legacy KANGIS FileNo Placeholder. When the
     *  checkbox is ticked the KN-series input is revealed and becomes required; its value
     *  feeds the existing `new_kangis_file_no` payload field. */
    function initializeHasNewKangisFileno() {
        const chk = document.getElementById('has-new-kangis-fileno');
        const fields = document.getElementById('has-new-kangis-fields');
        const knDisplay = document.getElementById('new_kangis_file_no_display');
        const knHidden = document.getElementById('new_kangis_file_no_hidden');
        const knStatus = document.getElementById('kn-fileno-status');
        const errorEl = document.getElementById('has-new-kangis-error');
        const selectBtn = document.getElementById('new_kangis_file_no_select_btn');
        const clearBtn = document.getElementById('new_kangis_file_no_clear_btn');

        // These only render in normal (non-new_kn) mode.
        if (!chk || !fields || !knDisplay || !knHidden) return;

        function clearKnFields() {
            knDisplay.value = '';
            knHidden.value = '';
            if (knStatus) knStatus.textContent = '';
            if (errorEl) errorEl.classList.add('hidden');
            knDisplay.classList.remove('error-border');
            if (typeof window.refreshTransactionFileNoLabels === 'function') {
                window.refreshTransactionFileNoLabels();
            }
        }

        chk.addEventListener('change', function () {
            if (this.checked) {
                fields.classList.remove('hidden');
                if (window.lucide) window.lucide.createIcons({ nodes: [fields] });
            } else {
                fields.classList.add('hidden');
                clearKnFields();
            }
        });

        // Pick a New KANGIS file via the global file number modal (New KANGIS tab).
        function openNewKangisPicker() {
            if (!window.GlobalFileNoModal) {
                console.warn('GlobalFileNoModal not available');
                return;
            }
            GlobalFileNoModal.open({
                initialTab: 'newkangis',
                // Do NOT overwrite the main #fileno / file_number inputs — this is a
                // separate New KANGIS reference attached to the current file.
                autoPopulateGenericFields: false,
                callback: function (data) {
                    if (!data || !data.fileNumber) return;
                    const val = String(data.fileNumber).trim();
                    knDisplay.value = val;
                    knHidden.value = val;
                    knDisplay.classList.remove('error-border');
                    if (errorEl) errorEl.classList.add('hidden');
                    if (knStatus) {
                        knStatus.textContent = `✓ Selected: ${val}`;
                        knStatus.className = 'mt-1 text-xs text-purple-700 font-semibold';
                    }
                    // Reflect the selected file in the transaction row headers
                    if (typeof window.refreshTransactionFileNoLabels === 'function') {
                        window.refreshTransactionFileNoLabels();
                    }
                }
            });
        }

        if (selectBtn) selectBtn.addEventListener('click', openNewKangisPicker);
        if (knDisplay) knDisplay.addEventListener('click', openNewKangisPicker);
        if (clearBtn) clearBtn.addEventListener('click', clearKnFields);
    }

    /** Validate the "Has New KANGIS FileNo" gate at submit time.
     *  Returns true when valid, false (and shows error) when checked but empty. */
    function validateHasNewKangisFileno() {
        const chk = document.getElementById('has-new-kangis-fileno');
        const fields = document.getElementById('has-new-kangis-fields');
        // Only enforce when the gate is ticked AND actually visible
        // (offsetParent is null when an ancestor — e.g. the KANGIS wrapper — is hidden).
        if (!chk || !chk.checked || chk.offsetParent === null) return true;
        if (!fields || fields.classList.contains('hidden')) return true;

        const knDisplay = document.getElementById('new_kangis_file_no_display');
        const knHidden = document.getElementById('new_kangis_file_no_hidden');
        const errorEl = document.getElementById('has-new-kangis-error');
        const value = (knHidden?.value || '').trim();

        if (!value) {
            if (errorEl) {
                errorEl.classList.remove('hidden');
                if (window.lucide) window.lucide.createIcons({ nodes: [errorEl] });
            }
            if (knDisplay) {
                knDisplay.classList.add('error-border');
                knDisplay.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }
        return true;
    }
    // Expose for the submit handler
    window.validateHasNewKangisFileno = validateHasNewKangisFileno;

    /** "Has Transaction" (New KANGIS) — toggles a repeatable transaction card; each row is
     *  collected into formData.transactions and saved to the pra table on submit. */
    function initializeNewKangisTransactions() {
        const chk = document.getElementById('has-new-kangis-transaction');
        const card = document.getElementById('nk-transaction-card');
        const rows = document.getElementById('nk-transaction-rows');
        const addBtn = document.getElementById('add-nk-transaction-btn');

        // Only present in normal (non-new_kn) mode.
        if (!chk || !card || !rows || !addBtn) return;

        const clearRow = (row) => {
            row.querySelectorAll('input').forEach(el => { el.value = ''; });
            row.querySelectorAll('select').forEach(el => { el.selectedIndex = 0; });
        };

        // Stamp the selected New KANGIS file number in front of each "Transaction X" header.
        const updateFileNoLabels = () => {
            const fileNo = (document.getElementById('new_kangis_file_no_hidden')?.value
                || document.getElementById('new_kangis_file_no_display')?.value || '').trim();
            rows.querySelectorAll('.nk-txn-fileno').forEach(el => {
                el.textContent = fileNo || '';
            });
        };
        window.refreshTransactionFileNoLabels = updateFileNoLabels;

        // Renumber rows and show/hide remove buttons (first row keeps no remove).
        const refreshRows = () => {
            const all = rows.querySelectorAll('.nk-transaction-row');
            all.forEach((row, i) => {
                const idx = row.querySelector('.nk-txn-index');
                if (idx) idx.textContent = String(i + 1);
                const removeBtn = row.querySelector('.nk-txn-remove');
                if (removeBtn) removeBtn.classList.toggle('hidden', all.length === 1);
            });
            updateFileNoLabels();
        };

        // Reset to a single empty row (used on uncheck / registry change).
        const resetRows = () => {
            const all = rows.querySelectorAll('.nk-transaction-row');
            all.forEach((row, i) => { if (i > 0) row.remove(); });
            const first = rows.querySelector('.nk-transaction-row');
            if (first) clearRow(first);
            refreshRows();
        };
        window.resetNewKangisTransactions = resetRows;

        chk.addEventListener('change', function () {
            if (this.checked) {
                card.classList.remove('hidden');
                updateFileNoLabels();
                if (window.lucide) window.lucide.createIcons({ nodes: [card] });
            } else {
                card.classList.add('hidden');
                resetRows();
            }
        });

        addBtn.addEventListener('click', function () {
            const template = rows.querySelector('.nk-transaction-row');
            if (!template) return;
            const clone = template.cloneNode(true);
            clearRow(clone);
            rows.appendChild(clone);
            refreshRows();
            if (window.lucide) window.lucide.createIcons({ nodes: [clone] });
        });

        rows.addEventListener('click', function (e) {
            const removeHit = e.target.closest('.nk-txn-remove');
            if (removeHit) {
                const row = removeHit.closest('.nk-transaction-row');
                if (row && rows.querySelectorAll('.nk-transaction-row').length > 1) {
                    row.remove();
                    refreshRows();
                }
            }
        });

        refreshRows();
    }

    /** Collect the New KANGIS transaction rows into an array of plain objects.
     *  Rows that are entirely empty are skipped. */
    function collectNewKangisTransactions() {
        const chk = document.getElementById('has-new-kangis-transaction');
        const card = document.getElementById('nk-transaction-card');
        if (!chk || !chk.checked || !card || card.classList.contains('hidden')) return [];

        const out = [];
        document.querySelectorAll('#nk-transaction-rows .nk-transaction-row').forEach(row => {
            const txn = {
                instrument_type:  (row.querySelector('.nk-txn-instrument')?.value || '').trim(),
                transaction_date: (row.querySelector('.nk-txn-date')?.value || '').trim(),
                serial_no:        (row.querySelector('.nk-txn-serial')?.value || '').trim(),
                page_no:          (row.querySelector('.nk-txn-page')?.value || '').trim(),
                vol_no:           (row.querySelector('.nk-txn-vol')?.value || '').trim(),
                reg_date:         (row.querySelector('.nk-txn-reg-date')?.value || '').trim(),
                reg_time:         (row.querySelector('.nk-txn-reg-time')?.value || '').trim(),
                grantor:          (row.querySelector('.nk-txn-grantor')?.value || '').trim(),
                grantee:          (row.querySelector('.nk-txn-grantee')?.value || '').trim(),
            };
            if (Object.values(txn).some(v => v !== '')) out.push(txn);
        });
        return out;
    }
    window.collectNewKangisTransactions = collectNewKangisTransactions;

    document.addEventListener('DOMContentLoaded', function () {
        const init = (fn, name) => {
            try {
                fn();
            } catch (e) {
                console.error(`Error initializing ${name}:`, e);
            }
        };

        init(initializeEntityCustomerSection, 'EntityCustomerSection');
        init(initializeCreateIndexingForm, 'CreateIndexingForm');
        init(handleRegistryFieldToggling, 'RegistryFieldToggling');
        init(initializeNewKangisFields, 'NewKangisFields');
        init(initializeHasNewKangisFileno, 'HasNewKangisFileno');
        init(initializeNewKangisTransactions, 'NewKangisTransactions');
        init(initializeDynamicRelatedFiles, 'DynamicRelatedFiles');
        init(initializeBlockIndexingUI, 'BlockIndexingUI');
        init(initializeRelatedFilesDetails, 'RelatedFilesDetails');

        // new_kn mode: extra setup
        if (window.isNewKnMode) {
            // 1. Force KANGIS Registry selection
            const regSelect = document.getElementById('general-registry');
            if (regSelect) {
                const kangisOpt = Array.from(regSelect.options).find(o => o.value.toUpperCase().includes('KANGIS'));
                if (kangisOpt) { regSelect.value = kangisOpt.value; regSelect.dispatchEvent(new Event('change')); }
            }

            // 2. Auto-check the New KANGIS checkbox and reveal fields
            const chk = document.getElementById('is-new-kangis-file');
            const fields = document.getElementById('new-kangis-fields');
            const wrapper = document.getElementById('new-kangis-wrapper');
            if (chk) chk.checked = true;
            if (fields) fields.classList.remove('hidden');
            if (wrapper) wrapper.classList.remove('hidden');

            // 3. Bidirectional sync: dedicated KN input <-> main File Number field
            const fileNoDisplay = document.getElementById('file-number-display');
            const fileNoHidden = document.getElementById('fileno');
            const knInput = document.getElementById('new_kangis_file_no_input');
            const knHidden = document.getElementById('new_kangis_file_no_hidden');

            const isKnFormat = (val) => /^KN[\s_-]*\d/i.test((val || '').replace(/\s+/g, ''));

            // Sync main → KN hidden + dedicated KN input (when main is a KN value)
            function syncFromMain() {
                const val = (fileNoHidden?.value || fileNoDisplay?.value || '').trim();
                if (!val) return;
                if (isKnFormat(val)) {
                    if (knHidden) knHidden.value = val;
                    if (knInput && document.activeElement !== knInput) knInput.value = val;
                } else {
                    // Non-KN value in main field (e.g. existing MLS loaded in edit mode):
                    // preserve it as the MLS reference if mls_file_no_input is still empty.
                    const mlsInput = document.getElementById('mls_file_no_input');
                    const mlsHidden = document.getElementById('mls_file_no_hidden');
                    if (mlsInput && !mlsInput.value) mlsInput.value = val;
                    if (mlsHidden && !mlsHidden.value) mlsHidden.value = val;
                }
            }
            if (fileNoDisplay) fileNoDisplay.addEventListener('change', syncFromMain);
            if (fileNoHidden) fileNoHidden.addEventListener('change', syncFromMain);
            if (fileNoDisplay) {
                new MutationObserver(syncFromMain).observe(fileNoDisplay, { attributes: true, attributeFilter: ['value'] });
            }

            // Sync dedicated KN input → main File Number field + KN hidden
            function syncFromKnInput() {
                const val = (knInput?.value || '').trim();
                if (knHidden) knHidden.value = val;
                if (val) {
                    if (fileNoHidden) fileNoHidden.value = val;
                    if (fileNoDisplay) {
                        fileNoDisplay.value = val;
                        fileNoDisplay.dispatchEvent(new Event('change'));
                    }
                }
            }
            if (knInput) {
                knInput.addEventListener('change', syncFromKnInput);
                knInput.addEventListener('blur', syncFromKnInput);
            }

            // 4. If prefilled value is an MLS (not KN), preserve it in mls_file_no_input/hidden
            //    and clear the main File Number field so the user picks the new KN.
            const prefilled = (window.prefillFileNumber || '').trim();
            if (prefilled && !isKnFormat(prefilled) && !window.isEditMode) {
                const mlsInput = document.getElementById('mls_file_no_input');
                const mlsHidden = document.getElementById('mls_file_no_hidden');
                if (mlsInput && !mlsInput.value) mlsInput.value = prefilled;
                if (mlsHidden && !mlsHidden.value) mlsHidden.value = prefilled;
            }
        }

        // KnModeFileSelector: opens GlobalFileNoModal for MLS/KANGIS fields in new_kn mode
        window.KnModeFileSelector = {
            _target: null,
            open: function (target) {
                this._target = target;
                if (typeof GlobalFileNoModal === 'undefined') { alert('File selector not available'); return; }
                GlobalFileNoModal.open({
                    autoPopulateGenericFields: false,
                    // These are the registry numbers of the file being indexed, not a
                    // reference to some other file, so the year restriction applies here too.
                    blockedYears: nonIndexableYears(),
                    callback: (data) => {
                        if (!data || !data.fileNumber) return;
                        if (this._target === 'mls') {
                            const inp = document.getElementById('mls_file_no_input');
                            const hid = document.getElementById('mls_file_no_hidden');
                            if (inp) inp.value = data.fileNumber;
                            if (hid) hid.value = data.fileNumber;
                        } else if (this._target === 'kangis') {
                            const inp = document.getElementById('kangis_file_no_input');
                            const hid = document.getElementById('kangis_file_no_hidden');
                            if (inp) inp.value = data.fileNumber;
                            if (hid) hid.value = data.fileNumber;
                        } else if (this._target === 'new_kangis') {
                            // Picking the new KN file number: update dedicated input,
                            // KN hidden, and the main File Number field/hidden.
                            const knInp = document.getElementById('new_kangis_file_no_input');
                            const knHid = document.getElementById('new_kangis_file_no_hidden');
                            const mainDisplay = document.getElementById('file-number-display');
                            const mainHidden = document.getElementById('fileno');
                            if (knInp) knInp.value = data.fileNumber;
                            if (knHid) knHid.value = data.fileNumber;
                            if (mainHidden) mainHidden.value = data.fileNumber;
                            if (mainDisplay) {
                                mainDisplay.value = data.fileNumber;
                                mainDisplay.dispatchEvent(new Event('change'));
                            }
                            const knStatus = document.getElementById('kn-fileno-status');
                            if (knStatus) {
                                knStatus.textContent = `✓ Selected: ${data.fileNumber}`;
                                knStatus.className = 'mt-1 text-xs text-green-700 font-semibold';
                            }
                        }
                    }
                });
            }
        };

        // Also initialize GlobalFileNoModal if available for modal context
        const checkInterval = setInterval(function () {
            if (typeof GlobalFileNoModal !== 'undefined') {
                clearInterval(checkInterval);
                console.log('GlobalFileNoModal is available, initializing');

                if (typeof GlobalFileNoModal.init === 'function') {
                    GlobalFileNoModal.init();
                }
            }
        }, 100);

        initializeSltrRegistryLookup();
    });

    function initializeSltrRegistryLookup() {
        const generalRegistry = document.getElementById('general-registry');
        const fileNoHidden = document.getElementById('fileno');
        const fileNoDisplay = document.getElementById('file-number-display');

        if (!generalRegistry) return;

        function triggerSltrLookup() {
            const registry = generalRegistry.value || '';
            const fileNo = (fileNoHidden?.value || fileNoDisplay?.value || '').trim();

            if (registry.toUpperCase().includes('SLTR') && fileNo) {
                fetchSltrGroupingDetails(fileNo);
            } else {
                const sltrSection = document.getElementById('sltr-grouping-details');
                if (sltrSection) sltrSection.classList.add('hidden');

                // Also clear the inputs if not SLTR
                const subPrefixInput = document.getElementById('sub_prefix');
                const suffixInput = document.getElementById('suffix');
                if (subPrefixInput && !window.isEditMode) subPrefixInput.value = '';
                if (suffixInput && !window.isEditMode) suffixInput.value = '';
            }
        }

        async function fetchSltrGroupingDetails(fileNumber) {
            const sltrSection = document.getElementById('sltr-grouping-details');
            const subPrefixInput = document.getElementById('sub_prefix');
            const suffixInput = document.getElementById('suffix');

            if (!sltrSection) return;

            try {
                const response = await fetch(`/fileindexing/api/sltr-grouping-details?file_number=${encodeURIComponent(fileNumber)}`);
                const data = await response.json();

                if (data.success && data.data) {
                    sltrSection.classList.remove('hidden');
                    if (subPrefixInput) subPrefixInput.value = data.data.sub_prefix || '';
                    if (suffixInput) suffixInput.value = data.data.suffix || '';

                    if (typeof Swal !== 'undefined' && !window.isEditMode) {
                        Swal.fire({
                            title: 'SLTR Details Found',
                            text: `Sub Prefix: ${data.data.sub_prefix}, Suffix: ${data.data.suffix}`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                        });
                    }
                } else {
                    // If no data found but it's SLTR registry, we still show the section but keep fields empty/editable?
                    // Actually requirement says "fetch sub_prefix and suffix", implying they come from lookup.
                    // We'll hide it if no match found.
                    sltrSection.classList.add('hidden');
                    if (subPrefixInput && !window.isEditMode) subPrefixInput.value = '';
                    if (suffixInput && !window.isEditMode) suffixInput.value = '';
                }
            } catch (error) {
                console.error('Error fetching SLTR grouping details:', error);
            }
        }

        generalRegistry.addEventListener('change', triggerSltrLookup);
        if (fileNoHidden) fileNoHidden.addEventListener('change', triggerSltrLookup);
        if (fileNoDisplay) fileNoDisplay.addEventListener('change', triggerSltrLookup);

        // Initial check
        if (generalRegistry.value) {
            triggerSltrLookup();
        }
    }
})();

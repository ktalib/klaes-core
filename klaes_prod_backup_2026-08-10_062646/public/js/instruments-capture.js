
// Initialize GlobalFileNoModal when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    if (typeof GlobalFileNoModal !== 'undefined' && GlobalFileNoModal.init) {
        GlobalFileNoModal.init();
        console.log('GlobalFileNoModal initialized successfully');
    } else {
        console.warn('GlobalFileNoModal not available for initialization');
    }

    // State management
    let currentInstrumentType = null;
    let tempFileCounter = 1;
    let opLookupMatchedRecord = null;
    let suppressNextOpSerialLookup = false;

    // Global Exports (Moved to top of DOMContentLoaded for robustness)
    window.openFileSelector = openFileSelector;
    window.applySelectedFile = applySelectedFile;
    window.closeRegistrationDialog = closeRegistrationDialog;
    window.openRegistrationDialog = openRegistrationDialog;
    window.checkDuplicate = checkDuplicate;
    window.handleSubmit = handleSubmit;
    window.populateForm = populateForm;
    const isInstrumentCaptureCreatePage = window.location.pathname.toLowerCase().includes('/instruments/create');
    // Serial-only OP lookup (fetch candidates, let the user pick one) is restricted
    // to the MLS File Number Generator page. Everywhere else keeps the 5-field
    // disambiguation flow — see the comment in lookupOpSerialNumberAndAutofill().
    const isMlsFileNumberGeneratorPage = window.location.pathname.toLowerCase().replace(/\/+$/, '') === '/file-numbers';

    // Wire up `#close-property-form` and cancel button for the property modal dialog on the instruments capture page
    const propDialogCloseBtn = document.getElementById('close-property-form');
    const propDialogCancelBtn = document.getElementById('cancel-property-form');
    const propFormDialog = document.getElementById('property-form-dialog');
    if (propDialogCloseBtn) {
        propDialogCloseBtn.addEventListener('click', function() {
            if (propFormDialog) {
                propFormDialog.classList.add('hidden');
                propFormDialog.style.display = 'none';
            }
        });
    }
    if (propDialogCancelBtn) {
        propDialogCancelBtn.addEventListener('click', function() {
            if (propFormDialog) {
                propFormDialog.classList.add('hidden');
                propFormDialog.style.display = 'none';
            }
        });
    }

    const instrumentTypes = {
        'power-of-attorney': {
            id: 'power-of-attorney',
            name: 'Power of Attorney',
            firstParty: 'Grantor',
            secondParty: 'Grantee',
            needsRootReg: true,
            description: 'A legal document granting authority to a person (the attorney) to act on behalf of another (the donor) in property-related matters.'
        },
        'irrevocable-power-of-attorney': {
            id: 'irrevocable-power-of-attorney',
            name: 'Irrevocable Power of Attorney',
            firstParty: 'Grantor',
            secondParty: 'Grantee',
            needsRootReg: true,
            description: 'A non-revocable legal instrument that permanently empowers the attorney to act on behalf of the donor in managing or transferring land/property rights.'
        },
        'deed-of-mortgage': {
            id: 'deed-of-mortgage',
            name: 'Deed of Mortgage',
            firstParty: 'Mortgagor',
            secondParty: 'Mortgagee',
            needsRootReg: true,
            description: 'A formal agreement used to secure a loan against landed property, with the lender holding interest until full repayment.',
            subtypes: ['deed-of-mortgage', 'tripartite-mortgage']
        },
        'tripartite-mortgage': {
            id: 'tripartite-mortgage',
            name: 'Tripartite Mortgage',
            firstParty: 'Mortgagor',
            secondParty: 'Mortgagee',
            needsRootReg: true,
            description: 'A three-party agreement involving the borrower, lender, and property owner, typically used where the borrower is not the titleholder.',
            subtypes: ['deed-of-mortgage', 'tripartite-mortgage']
        },
        'deed-of-assignment': {
            id: 'deed-of-assignment',
            name: 'Deed of Assignment',
            firstParty: 'Assignor',
            secondParty: 'Assignee',
            needsRootReg: true,
            description: 'A document that legally transfers ownership of an interest in land or property from one party (assignor) to another (assignee).',
            subtypes: ['deed-of-assignment', 'deed-of-gift']
        },
        'deed-of-lease': {
            id: 'deed-of-lease',
            name: 'Deed of Lease',
            firstParty: 'Lessor',
            secondParty: 'Lessee',
            needsRootReg: true,
            description: 'A contractual document that grants possession and use of land or property to a lessee for a specified period under agreed terms.'
        },
        'deed-of-sub-lease': {
            id: 'deed-of-sub-lease',
            name: 'Deed of Sub-Lease',
            firstParty: 'Sub-Lessor',
            secondParty: 'Sub-Lessee',
            needsRootReg: true,
            description: 'An agreement where a lessee (not the owner) leases part or all of the leased property to another party.'
        },
        'deed-of-sub-division': {
            id: 'deed-of-sub-division',
            name: 'Deed of Sub-Division',
            firstParty: 'Subdivider',
            secondParty: 'Beneficiary',
            needsRootReg: true,
            description: 'A legal instrument used to divide a single parcel of land into multiple plots, each with its own separate title or interest.'
        },
        'deed-of-merger': {
            id: 'deed-of-merger',
            name: 'Deed of Merger',
            firstParty: 'Transferor',
            secondParty: 'Transferee',
            needsRootReg: true,
            description: 'A document that combines multiple property interests or parcels into a single title or ownership.'
        },
        'deed-of-surrender-release': {
            id: 'deed-of-surrender-release',
            name: 'Deed of Surrender and Release',
            firstParty: 'Surrenderer/Releasor',
            secondParty: 'Surrenderee/Releasee',
            needsRootReg: true,
            description: 'A legal agreement in which a tenant voluntarily returns possession of property to the landlord, or a document that discharges a party from a previous claim or mortgage on a property.',
            subtypes: ['deed-of-surrender-release', 'power-of-attorney', 'irrevocable-power-of-attorney']
        },
        'devolution-order': {
            id: 'devolution-order',
            name: 'Devolution Order',
            firstParty: 'Deceased Owner',
            secondParty: 'Heir/Beneficiary',
            needsRootReg: true,
            description: 'A court-issued legal instrument used to transfer property ownership from a deceased person\'s estate to their rightful heirs or beneficiaries.'
        },
        'deed-of-gift': {
            id: 'deed-of-gift',
            name: 'Deed of Gift',
            firstParty: 'Donor',
            secondParty: 'Donee',
            needsRootReg: true,
            description: 'A legal document that transfers ownership of property from a donor (giver) to a donee (receiver) without monetary consideration, typically as a gift.'
        },
        'occupancy-permit': {
            id: 'occupancy-permit',
            name: 'Occupancy Permit (OP)',
            firstParty: 'Grantor',
            secondParty: 'Allottee',
            needsRootReg: true,
            autoSetGrantor: true,
            description: 'An official document issued by Kano State Government granting permission to occupy and use a specific property or land for designated purposes.'
        },
        'certificate-of-occupancy': {
            id: 'certificate-of-occupancy',
            name: 'Certificate of Occupancy',
            firstParty: 'Grantor', // Kano State Government
            secondParty: 'Grantee',
            needsRootReg: true,
            autoSetGrantor: true, // Will autofill Kano State Government
            description: 'A legal document issued by the government that proves ownership of land for a specified period (usually 99 years) in accordance with the Land Use Act.'
        }
    };

    // DOM Elements Cache
    const elements = {
        registrationDialog: document.getElementById('registration-dialog'),
        dialogTitle: document.getElementById('dialog-title'),
        registrationForm: document.getElementById('registration-form'),
        cancelBtn: document.getElementById('cancel-btn'),
        submitBtn: document.getElementById('submit-btn'),
        hardFreshFormBtn: document.getElementById('hard-refresh-registration-form-btn'),
        resetFormBtn: document.getElementById('reset-registration-form-btn'),

        subtypeContainer: document.getElementById('subtype-selector-container'),
        subtypeSelect: document.getElementById('instrument-subtype-select'),

        firstPartyTitle: document.getElementById('first-party-title'),
        firstPartyLabel: document.getElementById('firstPartyName-label'),
        secondPartyTitle: document.getElementById('second-party-title'),
        secondPartyLabel: document.getElementById('secondPartyName-label'),
        surveyInfo: document.getElementById('surveyInfo'),
        surveyInfoSection: document.getElementById('survey-info-section'),
        instrumentFields: document.getElementById('instrument-fields'),
        instrumentSidebar: document.getElementById('instrument-sidebar'),
        generateRootBtn: document.getElementById('generate-root-btn'),
        rootRegNoInput: document.getElementById('rootRegNo'),
        generateParticularsUrlInput: document.getElementById('generateParticularsUrl'),
        hiddenMlsFNo: document.getElementById('hidden_mlsFNo'),
        hiddenKangisFileNo: document.getElementById('hidden_kangisFileNo'),
        hiddenNewKANGISFileno: document.getElementById('hidden_NewKANGISFileno'),
        hiddenGenericFileno: document.getElementById('hidden_fileno'),
        displayFileno: document.getElementById('display_fileno'),
        fileSourceInfo: document.getElementById('file_source_info'),
        dialogCloseBtn: document.getElementById('dialog-close-btn'),
        selectFileBtn: document.getElementById('select-file-btn'),
        instrumentTypeInfo: document.getElementById('instrument-type-info-display'),
        includeSolicitor: document.getElementById('includeSolicitor'),
        solicitorContainer: document.getElementById('solicitor-container'),
        opSerialNumberContainer: document.getElementById('op_serial_number_container'),
        opSerialNumberInput: document.getElementById('op_serial_number'),
        opTypeContainer: document.getElementById('op-type-container'),
        cofoTypeContainer: document.getElementById('coo-type-container'),

        opRegistrationDetails: document.getElementById('op-registration-details'),
        regSerialInput: document.getElementById('serial_no'),
        regPageInput: document.getElementById('reg_page_no'),
        regPageDisplay: document.getElementById('reg_page_no_display'),
        regVolumeInput: document.getElementById('volume_no'),
        registrationNumberInput: document.getElementById('registration_number'),
        registrationDateInput: document.getElementById('registrationDate'),
        registrationTimeInput: document.getElementById('registrationTime'),

        landUseIdSelect: document.getElementById('land_use_id'),
        purposeIdSelect: document.getElementById('purpose_id'),
        landUseHidden: document.getElementById('land_use'),
        purposeHidden: document.getElementById('purpose'),

        // PRA History Elements
        praHistoryAlert: document.getElementById('pra-history-alert'),
        viewPraHistoryBtn: document.getElementById('btn-view-pra-history'),
        praHistoryModal: document.getElementById('pra-history-modal'),
        praHistoryBody: document.getElementById('pra-history-body'),
        praHistorySubtitle: document.getElementById('pra-history-subtitle'),
        praHistoryCloseBtns: [
            document.getElementById('pra-history-close-btn'),
            document.getElementById('pra-history-bottom-close-btn')
        ]
    };

    // Subtype Selector Listener
    if (elements.subtypeSelect) {
        elements.subtypeSelect.addEventListener('change', function () {
            const subtype = this.value;
            if (subtype) {
                const typeData = instrumentTypes[subtype];
                if (typeData) {
                    // Update current logic for the selected subtype
                    currentInstrumentType = subtype; // Update global state

                    elements.dialogTitle.textContent = `Capture ${typeData.name} `;
                    updatePartyLabels(subtype);
                    showInstrumentForm(subtype);
                    setHidden('instrument_type', typeData.name);

                    // Update Submit Button Label
                    const isAtomicType = true; // All types are now atomic in this context
                    applySubmitButtonLabel();

                    // Update Info Display
                    if (elements.instrumentTypeInfo) {
                        elements.instrumentTypeInfo.innerHTML = `
                            <div class="flex flex-col justify-center h-full">
                                <h4 class="text-sm font-bold text-blue-700 leading-tight">${typeData.name}</h4>
                                <p class="text-[11px] text-gray-500 leading-snug mt-1 text-justify">${typeData.description || ''}</p>
                            </div>
                        `;
                    }
                    if (typeof checkDuplicate === 'function') {
                        checkDuplicate();
                    }
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            }
        });
    }

    // Attach duplicate check listener to static Entry Date
    const entryDateInput = document.getElementById('entryDate');
    const transactionDateInput = document.getElementById('transactionDate');

    if (transactionDateInput) {
        transactionDateInput.addEventListener('change', function () {
            if (typeof checkDuplicate === 'function') {
                checkDuplicate();
            }
        });
    }

    if (entryDateInput && typeof checkDuplicate === 'function') {
        entryDateInput.addEventListener('change', checkDuplicate);
    }

    if (elements.regSerialInput) {
        elements.regSerialInput.addEventListener('input', updateRegistrationParticulars);
        elements.regSerialInput.addEventListener('input', _scheduleOpCandidateCheck);
        elements.regSerialInput.addEventListener('blur', _scheduleOpCandidateCheck);
    }
    if (elements.regVolumeInput) {
        elements.regVolumeInput.addEventListener('input', updateRegistrationParticulars);
        elements.regVolumeInput.addEventListener('input', _scheduleOpCandidateCheck);
        elements.regVolumeInput.addEventListener('blur', _scheduleOpCandidateCheck);
    }
    if (elements.regPageInput) {
        elements.regPageInput.addEventListener('input', _scheduleOpCandidateCheck);
        elements.regPageInput.addEventListener('blur', _scheduleOpCandidateCheck);
    }
    // Plot number and Allottee help disambiguate when serial+page+vol collide.
    ['plotNumber', 'secondPartyName', 'tp_no'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', _scheduleOpCandidateCheck);
            el.addEventListener('blur', _scheduleOpCandidateCheck);
        }
    });

    if (elements.opSerialNumberInput) {
        elements.opSerialNumberInput.addEventListener('blur', lookupOpSerialNumberAndAutofill);
        elements.opSerialNumberInput.addEventListener('change', lookupOpSerialNumberAndAutofill);

        // As-you-type search + Enter-to-search only on the MLS File Number Generator
        // page. Elsewhere the input/keydown listeners are skipped to avoid burning a
        // fresh TEMP- fileno on every keystroke (blur/change above still fire).
        if (isMlsFileNumberGeneratorPage) {
            let opLookupDebounceTimer = null;
            elements.opSerialNumberInput.addEventListener('input', function () {
                clearTimeout(opLookupDebounceTimer);
                opLookupDebounceTimer = setTimeout(() => {
                    lookupOpSerialNumberAndAutofill();
                }, 450);
            });
            elements.opSerialNumberInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    clearTimeout(opLookupDebounceTimer);
                    lookupOpSerialNumberAndAutofill();
                }
            });
        }
    }

    // NEW: Attach listener to file number input for manual entry trigger
    if (elements.displayFileno) {
        if (typeof checkDuplicate === 'function') {
            elements.displayFileno.addEventListener('change', checkDuplicate);
            elements.displayFileno.addEventListener('blur', checkDuplicate);
        }
        elements.displayFileno.addEventListener('input', updateOpSubmitAvailability);
        elements.displayFileno.addEventListener('change', updateOpSubmitAvailability);
    }

    // Attach listeners to instrument type buttons
    const typeButtons = document.querySelectorAll('.instrument-type-btn');
    typeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const type = this.getAttribute('data-type');
            if (typeof openRegistrationDialog === 'function') {
                openRegistrationDialog(type);
            } else {
                console.error('openRegistrationDialog is not defined');
            }
        });
    });

    // Attach submit listener
    if (elements.submitBtn) {
        elements.submitBtn.addEventListener('click', handleSubmit);
    }
    if (elements.cancelBtn) {
        elements.cancelBtn.addEventListener('click', closeRegistrationDialog);
    }
    if (elements.dialogCloseBtn) {
        elements.dialogCloseBtn.addEventListener('click', closeRegistrationDialog);
    }
    if (elements.selectFileBtn) {
        elements.selectFileBtn.addEventListener('click', openFileSelector);
    }
    if (elements.hardFreshFormBtn) {
        elements.hardFreshFormBtn.addEventListener('click', handleHardFreshForm);
    }
    if (elements.resetFormBtn) {
        elements.resetFormBtn.addEventListener('click', handleManualResetForm);
    }

    // State -> LGA listeners for parties
    ['firstParty', 'secondParty', 'solicitor', 'coMortgagor', 'thirdParty', 'party5'].forEach(prefix => {
        const stateEl = document.getElementById(`${prefix}State`);
        if (stateEl) {
            stateEl.addEventListener('change', function () {
                fetchLgas(this.value, `${prefix}Lga`);
                updatePartyAddressMode(prefix);
            });

            if (stateEl.value) {
                const lgaEl = document.getElementById(`${prefix}Lga`);
                fetchLgas(stateEl.value, `${prefix}Lga`, lgaEl?.value || null);
            }
        }
    });


    // Property Description Auto-update listeners
    ['house_no', 'plotNumber', 'desc_lga', 'desc_district', 'propertyState', 'manual_district', 'streetName', 'manual_street_name'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            const eventType = el.tagName === 'SELECT' ? 'change' : 'input';
            el.addEventListener(eventType, () => {
                // If it's a select, also update the custom UI/toggles
                if (el.tagName === 'SELECT') {
                    updateCustomSelect(id, el.value);
                }
                updatePropertyDescription();
            });
        }
    });

    const manualSelectConfig = {
        'desc_district': { containerId: 'manual_district_container', inputId: 'manual_district' },
        'district': { containerId: 'manual_survey_district_container', inputId: 'manual_survey_district' },
        'streetName': { containerId: 'manual_street_container', inputId: 'manual_street_name', onChange: updatePropertyDescription },
        'firstPartyStreet': { containerId: 'manual_firstPartyStreet_container', inputId: 'manual_firstPartyStreet' },
        'secondPartyStreet': { containerId: 'manual_secondPartyStreet_container', inputId: 'manual_secondPartyStreet' },
        'firstPartyDistrict': { containerId: 'manual_firstPartyDistrict_container', inputId: 'manual_firstPartyDistrict' },
        'secondPartyDistrict': { containerId: 'manual_secondPartyDistrict_container', inputId: 'manual_secondPartyDistrict' },
        'coMortgagorDistrict': { containerId: 'manual_coMortgagorDistrict_container', inputId: 'manual_coMortgagorDistrict' },
        'thirdPartyDistrict': { containerId: 'manual_thirdPartyDistrict_container', inputId: 'manual_thirdPartyDistrict' },
        'party5District': { containerId: 'manual_party5District_container', inputId: 'manual_party5District' }
    };

    function isOtherValue(value) {
        if (!value) return false;
        const normalized = value.toString().trim().toLowerCase();
        return normalized === 'other' || normalized === 'others';
    }

    function handleManualToggle(id, value) {
        const config = manualSelectConfig[id];
        if (!config) return;

        const container = config.containerId ? document.getElementById(config.containerId) : null;
        const manualInput = config.inputId ? document.getElementById(config.inputId) : null;
        const shouldShow = isOtherValue(value);

        if (container) container.classList.toggle('hidden', !shouldShow);
        if (!shouldShow && manualInput) {
            manualInput.value = '';
        }
    }

    function isKanoState(value) {
        const normalized = (value || '').toString().trim().toLowerCase();
        return normalized === '' || normalized === 'kano' || normalized === 'kano state';
    }

    function updatePartyAddressMode(prefix) {
        if (!['firstParty', 'secondParty'].includes(prefix)) return;

        const stateEl = document.getElementById(`${prefix}State`);
        const useManual = stateEl && !isKanoState(stateEl.value);

        [`${prefix}Street`, `${prefix}District`].forEach(id => {
            const selectEl = document.getElementById(id);
            const config = manualSelectConfig[id];
            if (!selectEl || !config) return;

            if (useManual) {
                selectEl.value = 'Others';
                selectEl.dataset.forcedManualForState = '1';
                handleManualToggle(id, 'Others');
            } else {
                if (selectEl.dataset.forcedManualForState === '1') {
                    selectEl.value = '';
                    delete selectEl.dataset.forcedManualForState;
                }
                handleManualToggle(id, selectEl.value);
            }

            // Keep Select2 display in sync when the value is set programmatically
            if (window.jQuery && jQuery.fn.select2 && jQuery(selectEl).hasClass('select2-hidden-accessible')) {
                jQuery(selectEl).trigger('change.select2');
            }
        });
    }

    function setupManualSelectListeners() {
        Object.keys(manualSelectConfig).forEach(id => {
            const selectEl = document.getElementById(id);
            if (!selectEl) return;
            // Avoid double-binding when re-run for dynamically inserted templates
            if (selectEl.dataset.manualToggleBound === '1') {
                handleManualToggle(id, selectEl.value);
                return;
            }
            selectEl.dataset.manualToggleBound = '1';
            selectEl.addEventListener('change', (event) => {
                handleManualToggle(id, event.target.value);
                const callback = manualSelectConfig[id]?.onChange;
                if (typeof callback === 'function') callback();
            });
            handleManualToggle(id, selectEl.value);
        });
    }

    setupManualSelectListeners();
    ['firstParty', 'secondParty'].forEach(updatePartyAddressMode);

    function getSelectOrManualValue(selectId, manualInputId) {
        const selectEl = document.getElementById(selectId);
        if (!selectEl) return '';
        let value = (selectEl.value || '').trim();
        const manualEl = manualInputId ? document.getElementById(manualInputId) : null;
        const manualContainer = manualEl?.closest('.mt-2');
        if (manualEl && manualContainer && !manualContainer.classList.contains('hidden') && manualEl.value.trim()) {
            return manualEl.value.trim();
        }
        if (isOtherValue(value) && manualInputId) {
            return manualEl?.value?.trim() || '';
        }
        return value;
    }

    function resetManualSelectFields() {
        Object.keys(manualSelectConfig).forEach(id => {
            const config = manualSelectConfig[id];
            const selectEl = document.getElementById(id);
            const container = config.containerId ? document.getElementById(config.containerId) : null;
            const manualInput = config.inputId ? document.getElementById(config.inputId) : null;

            if (manualInput) manualInput.value = '';

            if (selectEl) {
                handleManualToggle(id, selectEl.value);
            } else if (container) {
                container.classList.add('hidden');
            }
        });
    }

    function getCsrfToken() {
        return window.InstrumentCaptureConfig?.csrfToken
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '';
    }

    function getTpLookupUrls() {
        const urls = window.InstrumentCaptureConfig?.urls || {};
        return {
            search: urls.tpLookupSearch || '/instruments/tp-lookups/search',
            store: urls.tpLookupStore || '/instruments/tp-lookups',
        };
    }

    function getTpLookupValue() {
        const selectEl = document.getElementById('tp_no');
        if (!selectEl) return '';
        if (selectEl.value === '__other__') {
            return document.getElementById('manual_tp_no')?.value?.trim() || '';
        }
        return selectEl.value?.trim() || '';
    }

    function setTpLookupManualVisibility(show) {
        const container = document.getElementById('manual_tp_no_container');
        if (container) container.classList.toggle('hidden', !show);
        if (!show) {
            const manual = document.getElementById('manual_tp_no');
            if (manual) manual.value = '';
        }
    }

    async function persistManualTpLookup() {
        const selectEl = document.getElementById('tp_no');
        const manualEl = document.getElementById('manual_tp_no');
        const value = manualEl?.value?.trim().toUpperCase() || '';
        if (!selectEl || selectEl.value !== '__other__' || !value || value === 'OTHER' || value === 'OTHERS') {
            return value;
        }

        if (manualEl.dataset.savedValue === value) return value;

        const response = await fetch(getTpLookupUrls().store, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ tp_no: value }),
        });

        if (!response.ok) {
            throw new Error('Could not save TP number');
        }

        manualEl.dataset.savedValue = value;
        const option = new Option(value, value, true, true);
        selectEl.appendChild(option);
        selectEl.value = value;
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(selectEl).trigger('change.select2');
        }
        setTpLookupManualVisibility(false);
        return value;
    }

    function initTpLookupSelect() {
        const selectEl = document.getElementById('tp_no');
        if (!selectEl) return;

        const initialValue = selectEl.querySelector('option[selected]')?.value || selectEl.value;
        if (initialValue) {
            selectEl.value = initialValue;
        }

        if (window.jQuery && jQuery.fn.select2) {
            jQuery(selectEl).select2({
                width: '100%',
                placeholder: 'TP No',
                allowClear: true,
                minimumInputLength: 0,
                dropdownParent: jQuery('#registration-dialog').length ? jQuery('#registration-dialog') : jQuery(document.body),
                ajax: {
                    url: getTpLookupUrls().search,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term || '' }),
                    processResults: data => data,
                    cache: true,
                },
            });

            jQuery(selectEl).on('select2:select', function (event) {
                setTpLookupManualVisibility(event.params?.data?.id === '__other__');
            });
            jQuery(selectEl).on('select2:clear', function () {
                setTpLookupManualVisibility(false);
            });
        }

        selectEl.addEventListener('change', () => {
            setTpLookupManualVisibility(selectEl.value === '__other__');
        });

        const manualEl = document.getElementById('manual_tp_no');
        if (manualEl) {
            manualEl.addEventListener('input', () => {
                manualEl.value = manualEl.value.toUpperCase();
                updatePropertyDescription();
            });
            manualEl.addEventListener('blur', () => {
                persistManualTpLookup().catch(error => console.warn(error.message || error));
            });
        }

        setTpLookupManualVisibility(selectEl.value === '__other__');
    }

    initTpLookupSelect();

    // Enable searchable Select2 dropdowns for District selects.
    // Select2 fires native `change` events, so existing manual-toggle /
    // property-description listeners keep working unchanged.
    function initDistrictSelect2(root) {
        if (!(window.jQuery && jQuery.fn.select2)) return;

        const districtIds = [
            'firstPartyDistrict', 'secondPartyDistrict', 'desc_district', 'district',
            'coMortgagorDistrict', 'thirdPartyDistrict', 'party5District', 'solicitorDistrict'
        ];

        const scope = root && root.querySelectorAll ? root : document;

        districtIds.forEach(id => {
            const selectEl = scope.getElementById
                ? scope.getElementById(id)
                : scope.querySelector(`#${id}`);
            if (!selectEl) return;

            const $select = jQuery(selectEl);
            // Skip if Select2 is already initialised on this element
            if ($select.hasClass('select2-hidden-accessible')) return;

            $select.select2({
                width: '100%',
                placeholder: selectEl.querySelector('option[value=""]')?.textContent?.trim() || 'Select District',
                allowClear: false,
                containerCssClass: 'district-select2',
                dropdownParent: jQuery('#registration-dialog').length
                    ? jQuery('#registration-dialog')
                    : jQuery(document.body),
            });

            // Select2 fires its change event through jQuery, which does NOT reach handlers
            // registered with native addEventListener (the "Others → specify" toggle,
            // property-description updater, etc). Re-dispatch a native change event so those
            // listeners run. A guard flag prevents the re-dispatch from looping back in.
            $select.on('change', function () {
                if (selectEl.dataset.select2Syncing === '1') return;
                selectEl.dataset.select2Syncing = '1';
                selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                delete selectEl.dataset.select2Syncing;
            });

            // Hide the static chevron icon since Select2 renders its own arrow
            const chevron = selectEl.parentElement?.querySelector('[data-lucide="chevron-down"]');
            if (chevron) chevron.style.display = 'none';
        });
    }

    window.initDistrictSelect2 = initDistrictSelect2;
    initDistrictSelect2();

    // Attach checkbox listeners
    const surveyInfo = document.getElementById('surveyInfo');
    if (surveyInfo) {
        surveyInfo.addEventListener('change', handleSurveyInfoChange);
    }




    // Toggle Solicitor Section
    function getSolicitorAddressParts() {
        const districtSelect = document.getElementById('solicitorDistrict');
        const districtOther = document.getElementById('solicitorDistrictCustom')?.value?.trim()
            || document.getElementById('solicitorDistrictOther')?.value?.trim()
            || '';
        const district = districtSelect
            ? (districtSelect.value === 'Other' ? districtOther : districtSelect.value.trim())
            : '';
        const lga = document.getElementById('solicitorLga')?.value?.trim() || '';
        const city = document.getElementById('solicitorCity')?.value?.trim() || '';
        const state = document.getElementById('solicitorState')?.value?.trim() || '';

        return [district, lga, city, state].filter(Boolean);
    }

    function updateSolicitorOfficeAddress() {
        const addressField = document.getElementById('solicitorAddress');
        if (!addressField) return;

        const parts = getSolicitorAddressParts();
        addressField.value = parts.join(', ');
    }

    function handleSolicitorDistrictChange(select) {
        const wrapper = document.getElementById('solicitorDistrictOtherWrapper');
        const otherInput = document.getElementById('solicitorDistrictOther');
        const customInput = document.getElementById('solicitorDistrictCustom');

        if (select.value === 'Other') {
            if (wrapper) wrapper.classList.remove('hidden');
            if (otherInput) otherInput.focus();
        } else {
            if (wrapper) wrapper.classList.add('hidden');
            if (otherInput) otherInput.value = '';
            if (customInput) customInput.value = '';
        }

        updateSolicitorOfficeAddress();
    }

    // When the solicitor's state isn't Kano, its districts aren't in the list, so force
    // the District to "Other" and reveal the specify field. Revert when Kano is chosen again.
    function updateSolicitorDistrictMode() {
        const stateEl = document.getElementById('solicitorState');
        const districtEl = document.getElementById('solicitorDistrict');
        if (!stateEl || !districtEl) return;

        if (!isKanoState(stateEl.value)) {
            districtEl.value = 'Other';
            districtEl.dataset.forcedOtherForState = '1';
        } else if (districtEl.dataset.forcedOtherForState === '1') {
            districtEl.value = '';
            delete districtEl.dataset.forcedOtherForState;
        }

        // Keep the Select2 display in sync when the value is set programmatically
        if (window.jQuery && jQuery.fn.select2 && jQuery(districtEl).hasClass('select2-hidden-accessible')) {
            jQuery(districtEl).trigger('change.select2');
        }

        handleSolicitorDistrictChange(districtEl);
    }

    window.handleSolicitorDistrictChange = handleSolicitorDistrictChange;
    window.updateSolicitorOfficeAddress = updateSolicitorOfficeAddress;

    function attachSolicitorListeners() {
        const sidebarToggle = document.getElementById('includeSolicitorSidebar');
        const mainCheckbox = document.getElementById('includeSolicitor');
        const container = document.getElementById('solicitor-sidebar-container');
        const mainContainer = elements.solicitorContainer;

        if (!container && !mainContainer) return;

        const updateSolicitorUI = (checked) => {
            const targetContainer = container || mainContainer;
            if (checked) {
                if (targetContainer) targetContainer.classList.remove('hidden');
                if (mainContainer) mainContainer.classList.remove('hidden');
            } else {
                if (targetContainer) targetContainer.classList.add('hidden');
                if (mainContainer) mainContainer.classList.add('hidden');
                // Clear inputs
                const fields = (targetContainer || mainContainer).querySelectorAll('input, select, textarea');
                fields.forEach(input => input.value = '');
            }
            if (mainCheckbox) mainCheckbox.checked = checked;
            updateSolicitorOfficeAddress();
        };

        if (sidebarToggle) {
            sidebarToggle.addEventListener('change', function () {
                updateSolicitorUI(this.checked);
            });
            // Initial state
            if (mainCheckbox) sidebarToggle.checked = mainCheckbox.checked;
            updateSolicitorUI(sidebarToggle.checked);
        }

        // Re-attach LGA listener for sidebar state select if it exists
        const sidebarState = document.getElementById('solicitorState');
        if (sidebarState) {
            sidebarState.addEventListener('change', function () {
                updateSolicitorDistrictMode();
                fetchLgas(this.value, 'solicitorLga').then(() => updateSolicitorOfficeAddress());
            });
            // Sync on initial render (e.g. when editing a record with a non-Kano state)
            updateSolicitorDistrictMode();
        }

        const sidebarLga = document.getElementById('solicitorLga');
        if (sidebarLga) {
            sidebarLga.addEventListener('change', updateSolicitorOfficeAddress);
        }

        const sidebarCity = document.getElementById('solicitorCity');
        if (sidebarCity) {
            sidebarCity.addEventListener('input', updateSolicitorOfficeAddress);
        }

        const sidebarDistrict = document.getElementById('solicitorDistrict');
        if (sidebarDistrict) {
            sidebarDistrict.addEventListener('change', function () {
                handleSolicitorDistrictChange(this);
            });
        }

        const sidebarDistrictOther = document.getElementById('solicitorDistrictOther');
        if (sidebarDistrictOther) {
            sidebarDistrictOther.addEventListener('input', updateSolicitorOfficeAddress);
        }

        const sidebarDistrictCustom = document.getElementById('solicitorDistrictCustom');
        if (sidebarDistrictCustom) {
            sidebarDistrictCustom.addEventListener('input', updateSolicitorOfficeAddress);
        }

        updateSolicitorOfficeAddress();
    }

    function refreshSolicitorVisibility(instrumentType) {
        const supported = ['deed-of-assignment', 'deed-of-gift', 'power-of-attorney', 'irrevocable-power-of-attorney'];
        const toggleContainer = document.querySelector('#includeSolicitorSidebar')?.closest('.border-t');

        if (toggleContainer) {
            if (supported.includes(instrumentType)) {
                toggleContainer.classList.remove('hidden');
            } else {
                toggleContainer.classList.add('hidden');
                // Also ensure it's unchecked if not supported? 
                // Probably better to just hide it.
            }
        }
    }

    // --- File Selection Logic ---

    function openFileSelector() {
        if (typeof GlobalFileNoModal === 'undefined') {
            console.error('GlobalFileNoModal is not defined');
            Swal.fire({
                icon: 'error',
                title: 'Component Missing',
                text: 'File selector component is missing. Please reload the page.'
            });
            return;
        }

        GlobalFileNoModal.open({
            callback: applySelectedFile,
            debug: true
        });
    }

    function clearStaleFormInputs() {
        // Reset the method to POST and storeUrl to /instruments/store if they were in update mode
        const methodField = elements.registrationForm?.querySelector('input[name="_method"]');
        if (methodField) methodField.remove();

        const storeUrlInput = document.getElementById('storeUrl');
        if (storeUrlInput) storeUrlInput.value = '/instruments/store';

        // Clear all populated fields
        const fieldsToClear = [
            'firstPartyName', 'secondPartyName', 'firstPartyStreet', 'firstPartyPhone',
            'secondPartyStreet', 'secondPartyPhone', 'coMortgagorName', 'coMortgagorAddress',
            'coMortgagorDistrict', 'coMortgagorState', 'coMortgagorLga', 'coMortgagorPhone',
            'thirdPartyName', 'thirdPartyAddress', 'thirdPartyDistrict', 'thirdPartyState',
            'thirdPartyLga', 'thirdPartyPhone', 'party5Name', 'party5Address',
            'party5District', 'party5State', 'party5Lga', 'party5Phone', 'solicitorName',
            'solicitorAddress', 'solicitorDistrict', 'solicitorState', 'solicitorLga',
            'plotDescription', 'plotLocation', 'house_no', 'plotNumber', 'tp_no', 'plotSize',
            'surveyPlanNo', 'lga', 'district', 'manual_district', 'cofoTerm',
            'cofoDate', 'cofoRegParticulars', 'assignmentTerm', 'leaseTerm',
            'subLeaseAmount', 'firstPartyState', 'firstPartyDistrict', 'secondPartyState',
            'secondPartyDistrict', 'coMortgagorDistrict', 'solicitorState',
            'solicitorDistrict', 'propertyState', 'transactionDate', 'duration',
            'startDate', 'endDate', 'annualRent', 'considerationAmount', 'bankName',
            'rootRegNo', 'cofoType', 'op_serial_number', 'serial_no', 'volume_no',
            'registration_number', 'reg_page_no', 'registrationDate', 'registrationTime',
            'lpkn_no', 'propertyDescription'
        ];

        fieldsToClear.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.value = '';
                // Trigger events to update custom select components if any
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // Property State is always Kano (the registry only holds Kano State
        // lands). It's a readonly field, so restore its default after the clear
        // loop wipes it rather than leaving it blank.
        const propertyStateEl = document.getElementById('propertyState');
        if (propertyStateEl) {
            propertyStateEl.value = 'Kano';
            propertyStateEl.dispatchEvent(new Event('input', { bubbles: true }));
            propertyStateEl.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Clear prop_id hidden field
        const propIdInput = elements.registrationForm?.querySelector('[name="prop_id"]');
        if (propIdInput) {
            propIdInput.value = '';
        }

        // Reset toggles (checkboxes) if they are checked
        ['hasCoMortgagor', 'hasThirdParty', 'hasFourthParty', 'includeSolicitorSidebar', 'includeSolicitor', 'coo_recertification', 'coo_conversion'].forEach(id => {
            const cb = document.getElementById(id);
            if (cb && cb.checked) {
                cb.checked = false;
                cb.dispatchEvent(new Event('change'));
            }
        });

        // If the instrument type has autoSetGrantor, re-apply it
        if (currentInstrumentType) {
            const type = instrumentTypes[currentInstrumentType];
            if (type && type.autoSetGrantor) {
                const firstPartyNameField = document.getElementById('firstPartyName');
                if (firstPartyNameField) {
                    firstPartyNameField.value = 'Kano State Government';
                    firstPartyNameField.readOnly = true;
                    firstPartyNameField.style.backgroundColor = '#f3f4f6';
                    firstPartyNameField.style.cursor = 'not-allowed';
                }
                const addressFields = {
                    'firstPartyStreet': 'Ministry of Land and Physical Planning Kano State',
                    'firstPartyLga': 'Kano',
                    'firstPartyState': 'Kano',
                    'firstPartyPostalCode': '700102',
                    'firstPartyCountry': 'Nigeria'
                };
                Object.entries(addressFields).forEach(([fieldId, value]) => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.value = value;
                        field.readOnly = true;
                        field.style.backgroundColor = '#f3f4f6';
                    }
                });
                if (typeof toggleKanoFields === 'function') {
                    toggleKanoFields('Kano State Government');
                }
            }
        }

        // Reset manual custom selects
        resetManualSelectFields();

        // Reset duplicate state and capture lock
        duplicateCheckState.existingRecord = null;
        duplicateCheckState.propertyData = null;
        duplicateCheckState.praHistory = [];
        duplicateCheckState.isUpdateMode = false;
        duplicateCheckState.consentApp = null;
        duplicateCheckState.priorInstrument = null;
        alreadyCapturedLock = false;
        resetSubmitButton();
    }

    async function applySelectedFile(result) {
        if (!result) return;

        // Clear stale inputs first
        clearStaleFormInputs();

        const fileNo = result.fileNumber;
        const system = result.system.toLowerCase();

        elements.displayFileno.value = fileNo;
        elements.fileSourceInfo.textContent = `Selected from ${system.toUpperCase()}`;
        elements.fileSourceInfo.className = "mt-2 text-sm text-green-600 font-medium";

        // Clear hidden
        elements.hiddenMlsFNo.value = '';
        elements.hiddenKangisFileNo.value = '';
        elements.hiddenNewKANGISFileno.value = '';
        elements.hiddenGenericFileno.value = fileNo;

        if (system === 'mls') elements.hiddenMlsFNo.value = fileNo;
        else if (system === 'kangis') elements.hiddenKangisFileNo.value = fileNo;
        else if (system === 'newkangis' || system === 'new_kangis') elements.hiddenNewKANGISFileno.value = fileNo;

        // Base-layer backfill: populate the property/location (and grantee for
        // grant-type instruments) from the file's indexing record. Runs before
        // checkDuplicate so any richer consent / prior-instrument data can still
        // overwrite these defaults.
        await backfillPropertyFromFile(fileNo);

        // Trigger duplicate check
        if (typeof checkDuplicate === 'function') {
            checkDuplicate();
        }
    }

    /**
     * Backfill the property/location fields from the selected file's indexing
     * record (file_indexings) so a freshly selected file no longer starts from a
     * blank Property Description / Grantee. Sources the canonical property
     * attributes (plot, TP, LPKN, street, district, LGA, land use, description)
     * from the same endpoint the PRA form controller uses. For grant-type
     * instruments (CofO / OP — Kano State Government is the grantor) the file
     * owner is also prefilled as the Grantee.
     */
    async function backfillPropertyFromFile(fileNo) {
        if (!fileNo) return false;
        try {
            const res = await fetch(`/api/pra/v1/records/property-by-file/${encodeURIComponent(fileNo)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!res.ok) return false;

            const payload = await res.json();
            const d = payload && payload.data ? payload.data : null;
            if (!d) return false;

            // Suppress description auto-rebuild while the dropdowns are set so the
            // stored/indexed description isn't clobbered mid-update.
            window._suppressPropertyDescUpdate = true;

            if (d.plot_no) safeSetValue('plotNumber', d.plot_no);
            if (d.tp_no) safeSetValue('tp_no', d.tp_no);
            if (d.lpkn_no) safeSetValue('lpkn_no', d.lpkn_no);
            if (d.street_name) setSelectOrManual('streetName', d.street_name);
            if (d.district) {
                setSelectOrManual('desc_district', d.district);
                setSelectOrManual('district', d.district);
            }
            if (d.lga) {
                setSelectOrManual('desc_lga', d.lga);
                setSelectOrManual('lga', d.lga);
            }

            const desc = d.property_description || d.location;
            const propDescEl = document.getElementById('propertyDescription');
            if (propDescEl && desc) {
                propDescEl.value = String(desc).toUpperCase();
            }

            window._suppressPropertyDescUpdate = false;

            if (d.land_use) {
                prefillLandUseAndPurposeFromLookup(d.land_use, '');
            }

            // Grantee name only for grant-type instruments (Kano State Government
            // is the grantor, so the file owner is the grantee). Never assume the
            // owner is the grantee for assignments/mortgages/etc.
            const typeDef = instrumentTypes[currentInstrumentType];
            if (typeDef && typeDef.autoSetGrantor && d.owner_name) {
                const grantee = document.getElementById('secondPartyName');
                if (grantee && !grantee.value.trim()) {
                    grantee.value = String(d.owner_name).toUpperCase();
                }
            }

            return true;
        } catch (e) {
            window._suppressPropertyDescUpdate = false;
            console.warn('backfillPropertyFromFile failed', e);
            return false;
        }
    }

    // --- Duplicate Check Logic (Updated) ---

    const duplicateCheckState = {
        existingRecord: null,
        propertyData: null,
        praHistory: [],
        isUpdateMode: false,
        consentApp: null,
        priorInstrument: null
    };

    let suppressNextDuplicateCheck = false;
    let autoFilledSubmitMode = false;
    let alreadyCapturedLock = false;

    function setPraHistoryAlertVisibility(isVisible) {
        const alerts = document.querySelectorAll('#pra-history-alert');
        alerts.forEach((alert) => {
            if (!alert) return;
            if (isVisible) {
                alert.classList.remove('hidden');
                alert.removeAttribute('hidden');
            } else {
                alert.classList.add('hidden');
                alert.setAttribute('hidden', 'hidden');
            }
        });
    }

    /**
     * Set a <select> element to a value. If the value doesn't match any existing
     * option, select "Others" and populate the corresponding manual-input field.
     */
    function setSelectOrManual(selectId, value) {
        if (!value) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;

        const options = Array.from(sel.options);

        // Try exact match first
        const match = options.find(o => o.value.toLowerCase() === value.toLowerCase());
        if (match) {
            sel.value = match.value;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        // Try partial match (option value contained in consent address or vice-versa)
        const partial = options.find(o => {
            if (!o.value) return false;
            const ov = o.value.toLowerCase();
            const cv = value.toLowerCase();
            return cv.includes(ov) || ov.includes(cv);
        });
        if (partial) {
            sel.value = partial.value;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        // Try matching after stripping common location suffixes (TOWN, CITY, AREA, WARD, VILLAGE)
        const stripped = value.replace(/\b(TOWN|CITY|AREA|WARD|VILLAGE)\b/gi, '').trim();
        if (stripped && stripped.toLowerCase() !== value.toLowerCase()) {
            const strippedMatch = options.find(o => {
                if (!o.value) return false;
                const ov = o.value.toLowerCase();
                const sv = stripped.toLowerCase();
                return sv === ov || sv.includes(ov) || ov.includes(sv);
            });
            if (strippedMatch) {
                sel.value = strippedMatch.value;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
        }

        // Fall back to "Others" + manual input
        const othersOpt = options.find(o => o.value === 'Others');
        if (othersOpt) {
            sel.value = 'Others';
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            // Use manualSelectConfig mapping if available, otherwise fall back to 'manual_' + selectId
            const config = typeof manualSelectConfig !== 'undefined' ? manualSelectConfig[selectId] : null;
            const manualInputId = config ? config.inputId : ('manual_' + selectId);
            const manualInput = document.getElementById(manualInputId);
            if (manualInput) {
                manualInput.value = value.toUpperCase();
            }
            // Ensure the manual input container is visible
            if (config && config.containerId) {
                const container = document.getElementById(config.containerId);
                if (container) container.classList.remove('hidden');
            }
        }
    }

    /**
     * Collect all option values from a <select> element (excluding empty/placeholder).
     */
    function getSelectOptionValues(selectId) {
        const sel = document.getElementById(selectId);
        if (!sel) return [];
        return Array.from(sel.options)
            .map(o => o.value)
            .filter(v => v && v !== 'Others');
    }

    /**
     * Try to find a matching option value inside a text string.
     * Returns the matched option value or null.
     * Matches are case-insensitive and use word boundaries to avoid partial matches.
     */
    function extractOptionFromText(text, selectId) {
        if (!text) return null;
        const options = getSelectOptionValues(selectId);
        if (!options.length) return null;

        const normalized = text.toUpperCase();

        // Sort options longest-first to prefer more specific matches (e.g., "Dawakin Kudu" over "Kudu")
        const sorted = options.slice().sort((a, b) => b.length - a.length);

        for (const opt of sorted) {
            const upper = opt.toUpperCase();
            // Check if the option value appears in the text (word boundary aware)
            // Build a regex that escapes special chars and uses word boundaries
            const escaped = upper.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const re = new RegExp('(?:^|[\\s,./;:()\\-])' + escaped + '(?:$|[\\s,./;:()\\-])', 'i');
            if (re.test(normalized) || normalized.includes(upper)) {
                return opt;
            }
        }
        return null;
    }

    /**
     * Extract LGA from text by searching against ALL known LGA options across every
     * LGA <select> currently in the DOM (desc_lga has pre-loaded options from $lgas).
     * Falls back to the property description if the direct address didn't match.
     */
    function extractLgaFromText(text, fallbackText) {
        // desc_lga is pre-populated with all Kano LGAs from the server
        const match = extractOptionFromText(text, 'desc_lga');
        if (match) return match;
        if (fallbackText) return extractOptionFromText(fallbackText, 'desc_lga');
        return null;
    }

    /**
     * Extract District from text by checking against the district <select> options.
     * Accepts an optional `excludeValue` (e.g. already-matched LGA) so the same
     * token isn't used for both LGA and District.
     */
    function extractDistrictFromText(text, fallbackText, excludeValue) {
        const tryExtract = (src) => {
            if (!src) return null;
            // If an LGA was already matched, strip it from the search text first
            let cleaned = src;
            if (excludeValue) {
                const re = new RegExp(excludeValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
                cleaned = cleaned.replace(re, ' ');
            }
            // Remove state tokens so values like "Kano State" do not get picked as district.
            cleaned = cleaned.replace(/\b[A-Za-z\s]+\sSTATE\b/gi, ' ');
            return extractOptionFromText(cleaned, 'desc_district');
        };
        return tryExtract(text) || tryExtract(fallbackText);
    }

    function extractPlotNoFromText(text) {
        if (!text) return '';
        const raw = String(text);

        // Match common forms: "PLOT NO 12", "PLOT 12", "NO. 12".
        const patterns = [
            /\bPLOT\s*NO\.?\s*[:\-]?\s*([A-Z0-9\/\-]+)\b/i,
            /\bPLOT\s*[:\-]?\s*([A-Z0-9\/\-]+)\b/i,
            /\bNO\.?\s*[:\-]?\s*([A-Z0-9\/\-]+)\b/i
        ];

        for (const pattern of patterns) {
            const match = raw.match(pattern);
            if (match && match[1]) {
                return match[1].trim();
            }
        }

        return '';
    }

    function parseLocationPartsFromDescription(text) {
        if (!text) {
            return { plotNo: '', district: '', lga: '', state: '' };
        }

        const parts = String(text)
            .split(',')
            .map((part) => part.replace(/\.$/, '').trim())
            .filter(Boolean);

        if (!parts.length) {
            return { plotNo: '', district: '', lga: '', state: '' };
        }

        const statePart = parts.find((part) => /\bstate\b/i.test(part)) || '';
        const normalizedState = normalizeLocationLabel(statePart);

        // Exclude state token before assigning positional fields.
        const nonStateParts = parts.filter((part) => !/\bstate\b/i.test(part));

        // Check if the first segment looks like a plot identifier (has a number or "PLOT"/"NO" keyword).
        // Generic descriptions like "A PIECE OF LAND" should NOT be treated as plot numbers.
        const firstPart = (nonStateParts[0] || '').toUpperCase();
        const isPlotLike = /\d/.test(firstPart) || /\b(PLOT|NO|LOT|BLOCK)\b/i.test(firstPart);

        if (isPlotLike) {
            // First segment is a plot identifier
            return {
                plotNo: nonStateParts[0] || '',
                district: nonStateParts[1] || '',
                lga: nonStateParts[2] || '',
                state: normalizedState || ''
            };
        } else {
            // First segment is a generic description (e.g. "A PIECE OF LAND"), skip it
            return {
                plotNo: '',
                district: nonStateParts[1] || '',
                lga: nonStateParts[2] || '',
                state: normalizedState || ''
            };
        }
    }

    /**
     * Parse a comma-separated party address into district, LGA, and state.
     * e.g. "3, SABON BAKINZUWO, NASARAWA, KANO STATE" →
     *   { street: '3', district: 'SABON BAKINZUWO', lga: 'NASARAWA', state: 'Kano' }
     * Skips house-number-like first segments for district.
     */
    function parsePartyAddress(addressText) {
        const empty = { street: '', district: '', lga: '', state: '' };
        if (!addressText) return empty;
        const parts = String(addressText).split(',').map(p => p.trim()).filter(Boolean);
        if (!parts.length) return empty;

        // Find the segment containing explicit "STATE" keyword
        let stateIdx = parts.findIndex(p => /\bSTATE\b/i.test(p));
        let state = '';
        if (stateIdx >= 0) {
            state = parts[stateIdx].replace(/\bSTATE\b\.?/gi, '').trim();
            parts.splice(stateIdx, 1);
        }

        // After removing state segment, assign remaining positionally:
        // Last = LGA, middle = district, first (if starts with number) = street/house
        let street = '', district = '', lga = '';
        if (parts.length >= 3) {
            street = parts[0];
            lga = parts[parts.length - 1];
            district = parts.slice(1, -1).join(', ');
        } else if (parts.length === 2) {
            // Could be [district, lga] or [house#, area]
            if (/^\d+$/.test(parts[0].trim())) {
                street = parts[0];
                district = parts[1];
            } else {
                district = parts[0];
                lga = parts[1];
            }
        } else if (parts.length === 1) {
            district = parts[0];
        }

        return { street: street, district: district, lga: lga, state: state };
    }

    /**
     * Extract State from text by checking against a state <select>.
     * Prefers explicit "X STATE" pattern over generic name scanning.
     */
    function extractStateFromText(text, selectId) {
        if (!text) return null;
        const sel = document.getElementById(selectId);
        if (!sel) return null;
        const options = Array.from(sel.options).map(o => o.value).filter(Boolean);
        const normalized = text.toUpperCase();

        // Priority: match "X STATE" pattern first (most reliable signal)
        const stateMatch = normalized.match(/\b([A-Z][A-Z\s]*?)\s+STATE\b/);
        if (stateMatch) {
            const candidate = stateMatch[1].trim();
            const found = options.find(o => o.toUpperCase() === candidate);
            if (found) return found;
            // Partial match
            const partial = options.find(o =>
                candidate.includes(o.toUpperCase()) || o.toUpperCase().includes(candidate)
            );
            if (partial) return partial;
        }

        // Fallback: find any state name in text (longest-first)
        const sorted = options.slice().sort((a, b) => b.length - a.length);
        for (const opt of sorted) {
            if (normalized.includes(opt.toUpperCase())) return opt;
        }
        return null;
    }

    function pickConsentValue(consent, keys) {
        for (const key of keys) {
            const value = consent?.[key];
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                return String(value).trim();
            }
        }
        return '';
    }

    function normalizeLocationLabel(value) {
        if (!value) return '';
        return String(value)
            .replace(/\bLGA\b/gi, '')
            .replace(/\bSTATE\b/gi, '')
            .replace(/\s{2,}/g, ' ')
            .replace(/\s+,/g, ',')
            .trim();
    }

    /**
     * Auto-fill party and instrument fields from a consent application record.
     * Falls back to prior instrument_capture record for property & solicitor details.
     */
    function autoFillFromConsent(consent, priorInstrument) {
        if (!consent && !priorInstrument) return;

        const prior = priorInstrument || {};
        const cons = consent || {};

        console.log('[autoFillFromConsent] consent keys:', consent ? Object.keys(cons) : 'none');
        console.log('[autoFillFromConsent] prior instrument keys:', priorInstrument ? Object.keys(prior) : 'none');

        const propertyText = pickConsentValue(cons, [
            'property_description',
            'location_of_right_of_occupancy',
            'location',
            'property_location',
            'address'
        ]) || pickConsentValue(prior, [
            'property_description',
            'property_location',
            'location'
        ]);
        console.log('[autoFillFromConsent] propertyText resolved:', propertyText);
        const propDesc = (propertyText || '').toUpperCase();

        const explicitPropertyLga = normalizeLocationLabel(pickConsentValue(cons, [
            'property_lga',
            'lga',
            'location_lga'
        ])) || normalizeLocationLabel(prior.lga || '');
        const explicitPropertyDistrict = normalizeLocationLabel(pickConsentValue(cons, [
            'property_district',
            'district',
            'location_district'
        ])) || normalizeLocationLabel(prior.district || '');
        const explicitPropertyStreet = pickConsentValue(cons, [
            'property_street',
            'property_street_name',
            'street_name'
        ]);
        const explicitHouseNo = pickConsentValue(cons, [
            'property_house_no',
            'house_no'
        ]) || prior.house_no || '';
        const explicitPropertyState = normalizeLocationLabel(pickConsentValue(cons, [
            'property_state',
            'state',
            'location_state'
        ]));
        const explicitPlotNo = pickConsentValue(cons, [
            'plot_number',
            'plot_no'
        ]) || prior.plot_number || '';
        const parsedLocationParts = parseLocationPartsFromDescription(propertyText);

        console.log('[autoFillFromConsent] explicitPlotNo:', explicitPlotNo);
        console.log('[autoFillFromConsent] explicitPropertyLga:', explicitPropertyLga);
        console.log('[autoFillFromConsent] explicitPropertyDistrict:', explicitPropertyDistrict);
        console.log('[autoFillFromConsent] explicitPropertyState:', explicitPropertyState);
        console.log('[autoFillFromConsent] parsedLocationParts:', JSON.stringify(parsedLocationParts));

        // Suppress updatePropertyDescription during auto-fill so dropdown changes
        // don't overwrite the consent's original property description.
        window._suppressPropertyDescUpdate = true;

        // Party 1 (Applicant / Assignor / Mortgagor)
        const fName = document.getElementById('firstPartyName');
        const p1Name = cons.applicant_name || prior.party_1_name || '';
        if (fName && p1Name) {
            fName.value = p1Name.toUpperCase();
        }

        const p1Address = cons.applicant_address || prior.party_1_address || '';
        if (p1Address) {
            setSelectOrManual('firstPartyStreet', p1Address);

            // Use positional parser for comma-separated addresses, fallback to generic extractors
            const p1Parsed = parsePartyAddress(p1Address);
            const hasCommas = String(p1Address).includes(',');

            // State: prefer parsed "X STATE" result, then generic extractor, then prior
            const p1State = (hasCommas && p1Parsed.state)
                ? p1Parsed.state
                : (extractStateFromText(p1Address, 'firstPartyState') || prior.party_1_state || 'Kano');
            safeSetValue('firstPartyState', p1State);

            // District: prefer parsed district, then generic extractor, then prior
            const p1Lga = (hasCommas && p1Parsed.lga) ? p1Parsed.lga : extractLgaFromText(p1Address, propDesc);
            const p1District = (hasCommas && p1Parsed.district)
                ? p1Parsed.district
                : (extractDistrictFromText(p1Address, propDesc, p1Lga) || prior.party_1_district || '');
            if (p1District) {
                setSelectOrManual('firstPartyDistrict', p1District);
            }

            // LGA: fetch for the resolved state, then preselect
            if (p1Lga) {
                fetchLgas(p1State, 'firstPartyLga', p1Lga);
            } else if (prior.party_1_lga) {
                fetchLgas(p1State, 'firstPartyLga', prior.party_1_lga);
            } else {
                fetchLgas(p1State, 'firstPartyLga');
            }
        }

        // Party 2 (Party / Assignee / Mortgagee)
        const sName = document.getElementById('secondPartyName');
        const p2Name = cons.party_name || prior.party_2_name || '';
        if (sName && p2Name) {
            sName.value = p2Name.toUpperCase();
        }

        const p2Address = cons.party_address || prior.party_2_address || '';
        if (p2Address) {
            setSelectOrManual('secondPartyStreet', p2Address);

            // Use positional parser for comma-separated addresses, fallback to generic extractors
            const p2Parsed = parsePartyAddress(p2Address);
            const p2HasCommas = String(p2Address).includes(',');

            // State: prefer parsed "X STATE" result, then generic extractor, then prior
            const p2State = (p2HasCommas && p2Parsed.state)
                ? p2Parsed.state
                : (extractStateFromText(p2Address, 'secondPartyState') || prior.party_2_state || 'Kano');
            safeSetValue('secondPartyState', p2State);

            // District: prefer parsed district, then generic extractor, then prior
            const p2Lga = (p2HasCommas && p2Parsed.lga) ? p2Parsed.lga : extractLgaFromText(p2Address, propDesc);
            const p2District = (p2HasCommas && p2Parsed.district)
                ? p2Parsed.district
                : (extractDistrictFromText(p2Address, propDesc, p2Lga) || prior.party_2_district || '');
            if (p2District) {
                setSelectOrManual('secondPartyDistrict', p2District);
            }

            // LGA: fetch for the resolved state, then preselect
            if (p2Lga) {
                fetchLgas(p2State, 'secondPartyLga', p2Lga);
            } else if (prior.party_2_lga) {
                fetchLgas(p2State, 'secondPartyLga', prior.party_2_lga);
            } else {
                fetchLgas(p2State, 'secondPartyLga');
            }
        }

        // Consideration amount (try both possible field IDs)
        const consideration = cons.consideration || prior.consideration_amount || '';
        if (consideration) {
            const considerationEl = document.getElementById('considerationAmount') || document.getElementById('consideration');
            if (considerationEl) {
                considerationEl.value = consideration;
            }
        }

        const resolvedPlotNo = explicitPlotNo || extractPlotNoFromText(propertyText) || parsedLocationParts.plotNo;
        console.log('[autoFillFromConsent] resolvedPlotNo:', resolvedPlotNo, '| plotNumber el exists:', !!document.getElementById('plotNumber'));
        if (resolvedPlotNo) {
            safeSetValue('plotNumber', resolvedPlotNo);
        }
        if (explicitHouseNo) {
            safeSetValue('house_no', explicitHouseNo);
        }

        // Property description - set dropdowns first, then restore original text
        const propDescEl = document.getElementById('propertyDescription');
        if (explicitPropertyStreet) {
            setSelectOrManual('streetName', explicitPropertyStreet);
        }

        const descLga = explicitPropertyLga
            || parsedLocationParts.lga
            || extractLgaFromText(propertyText, cons.applicant_address || cons.party_address || prior.party_1_address || null);
        console.log('[autoFillFromConsent] descLga:', descLga, '| desc_lga el exists:', !!document.getElementById('desc_lga'));
        if (descLga) {
            setSelectOrManual('desc_lga', descLga);
            setSelectOrManual('lga', descLga);
        }

        const descDistrict = explicitPropertyDistrict
            || parsedLocationParts.district
            || extractDistrictFromText(propertyText, cons.applicant_address || cons.party_address || prior.party_1_address || null, descLga);
        console.log('[autoFillFromConsent] descDistrict:', descDistrict, '| desc_district el exists:', !!document.getElementById('desc_district'));
        if (descDistrict) {
            setSelectOrManual('desc_district', descDistrict);
            setSelectOrManual('district', descDistrict);
        }

        // Property State is always Kano — the registry only holds Kano State
        // lands, so never override this from consent/parsed data.
        safeSetValue('propertyState', 'Kano');

        if (propDescEl && propertyText) {
            // Restore original consent property text after select updates.
            propDescEl.value = propDesc;
        }

        // Re-enable updatePropertyDescription
        window._suppressPropertyDescUpdate = false;

        // Solicitor details from prior instrument record
        if (prior.solicitor_name) {
            const solToggle = document.getElementById('includeSolicitorSidebar');
            if (solToggle && !solToggle.checked) {
                solToggle.checked = true;
                solToggle.dispatchEvent(new Event('change'));
            }
            safeSetValue('solicitorName', prior.solicitor_name);
            safeSetValue('solicitorAddress', prior.solicitor_address);
            if (prior.solicitor_district) {
                setSelectOrManual('solicitorDistrict', prior.solicitor_district);
            }
            // solicitor_state/solicitor_lga columns don't exist - extract from address
            if (prior.solicitor_address) {
                const solState = extractStateFromText(prior.solicitor_address, 'solicitorState') || 'Kano';
                safeSetValue('solicitorState', solState);
                const solLga = extractLgaFromText(prior.solicitor_address, '');
                if (solLga) {
                    fetchLgas(solState, 'solicitorLga', solLga);
                } else {
                    fetchLgas(solState, 'solicitorLga');
                }
            }
            console.log('[autoFillFromConsent] Solicitor filled from prior instrument:', prior.solicitor_name);
        }

        // Property & survey fields from prior instrument
        if (prior.tp_no) safeSetValue('tp_no', prior.tp_no);
        if (prior.survey_plan_no) safeSetValue('surveyPlanNo', prior.survey_plan_no);
        if (prior.lpkn_no) safeSetValue('lpkn_no', prior.lpkn_no);
        if (prior.plot_size) safeSetValue('size', prior.plot_size);

        // Show survey info section if we have survey data (skip in OSS OP context)
        if ((prior.survey_plan_no || prior.lga || prior.district) && !window.ossOpContext) {
            const surveySection = document.getElementById('survey-info-section');
            if (surveySection) surveySection.classList.remove('hidden');
            const surveyCheckbox = document.getElementById('surveyInfo');
            if (surveyCheckbox && !surveyCheckbox.checked) {
                surveyCheckbox.checked = true;
            }
        }

        // Land use & purpose from prior instrument (use select-based prefill)
        if (prior.land_use || prior.purpose) {
            prefillLandUseAndPurposeFromLookup(prior.land_use || '', prior.purpose || '');
        }

        // Party phone numbers from prior instrument
        if (prior.party_1_phone) safeSetValue('firstPartyPhone', prior.party_1_phone);
        if (prior.party_2_phone) safeSetValue('secondPartyPhone', prior.party_2_phone);

        // Entry/Transaction date from prior instrument
        const priorDate = prior.entry_date || prior.instrument_date || '';
        if (priorDate) {
            safeSetDateValue('entryDate', priorDate);
            safeSetDateValue('transactionDate', priorDate);
        }

        const sources = [];
        if (consent) sources.push('consent application');
        if (priorInstrument) sources.push('prior instrument record');

        setAutoFilledSubmitMode(true);

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Data Auto-filled',
                text: `Details populated from ${sources.join(' & ')}.`,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    }

    /**
     * Check whether a consent application exists and has been printed for the
     * currently selected file number and instrument type (Assignment / Gift).
     *
     * @param {object} opts
     * @param {boolean} opts.silent - If true, only returns a result without showing Swal alerts.
     * @returns {Promise<boolean>} true = gate passed (can proceed), false = blocked.
     */
    async function runConsentGateCheck({ silent = false } = {}) {
        const consentGatedTypes = {
            'deed-of-assignment': 'Assignment',
            'deed-of-gift': 'Gift',
            'deed-of-mortgage': 'Mortgage',
            'tripartite-mortgage': 'Tripartite Mortgage'
        };
        if (!consentGatedTypes.hasOwnProperty(currentInstrumentType)) return true;
        if (duplicateCheckState.isUpdateMode) return true;

        const fileNo = (elements.displayFileno?.value || '').trim();
        if (!fileNo) return true; // no file yet — don't block

        const requiredConsentType = consentGatedTypes[currentInstrumentType];

        const disableSubmit = (reason) => {
            if (elements.submitBtn) {
                elements.submitBtn.disabled = true;
                elements.submitBtn.className = 'px-6 py-2.5 text-white font-medium bg-gray-400 rounded-lg cursor-not-allowed opacity-60 shadow-md flex items-center gap-2';
                elements.submitBtn.setAttribute('title', reason);
            }
        };

        try {
            const res = await fetch(
                `/api/consent-applications/check?file_number=${encodeURIComponent(fileNo)}&consent_type=${encodeURIComponent(requiredConsentType)}`
            );
            const data = await res.json();

            if (!data.has_consent) {
                const article = /^[aeiou]/i.test(requiredConsentType) ? 'An' : 'A';
                disableSubmit(`${article} ${requiredConsentType} Consent Application Required: A consent application must exist before registering this instrument.`);
                if (!silent) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Consent Application Required',
                        html: `${article} <strong>${requiredConsentType} Consent Application</strong> must be generated for file <strong>${fileNo}</strong> before registering this instrument.<br><br>Please go to <em>Applications for Consent</em> and generate the application first.`,
                        confirmButtonColor: '#dc2626'
                    });
                }
                return false;
            }

            if (!data.has_printed) {
                disableSubmit(`Consent Letter Not Printed: The ${requiredConsentType} Consent Letter must be printed before registering this instrument.`);
                if (!silent) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Consent Letter Not Printed',
                        html: `The <strong>${requiredConsentType} Consent Letter</strong> for file <strong>${fileNo}</strong> must be printed before registering this instrument.<br><br>Please print the consent letter from <em>Applications for Consent</em> first.`,
                        confirmButtonColor: '#dc2626'
                    });
                }
                return false;
            }

            return true;
        } catch (err) {
            console.error('runConsentGateCheck: fetch failed:', err);
            disableSubmit('Consent verification failed. Please check your connection and try again.');
            if (!silent) {
                Swal.fire({
                    icon: 'error',
                    title: 'Consent Verification Failed',
                    text: 'Could not verify consent status. Please check your connection and try again.',
                    confirmButtonColor: '#dc2626'
                });
            }
            return false;
        }
    }

    async function checkDuplicate() {
        if (suppressNextDuplicateCheck) {
            suppressNextDuplicateCheck = false;
            duplicateCheckState.praHistory = [];
            setPraHistoryAlertVisibility(false);
            return;
        }

        // Reset state first
        duplicateCheckState.existingRecord = null;
        duplicateCheckState.propertyData = null;
        duplicateCheckState.praHistory = [];
        duplicateCheckState.isUpdateMode = false;
        duplicateCheckState.consentApp = null;
        duplicateCheckState.priorInstrument = null;
        alreadyCapturedLock = false;
        resetSubmitButton();

        // Hide PRA alert by default
        setPraHistoryAlertVisibility(false);

        const fileNo = elements.displayFileno.value;
        const typeId = currentInstrumentType;
        const date = document.getElementById('transactionDate')?.value || document.getElementById('entryDate')?.value;

        console.log('checkDuplicate Triggered:', { fileNo, typeId, date });

        // FIX: If fileNo is empty (cleared), ensure we clean up UI
        if (!fileNo) {
            console.log('checkDuplicate: No fileNo, resetting.');
            resetSubmitButton();
            return;
        }

        if (!typeId) {
            console.log('checkDuplicate: No typeId, skipping.');
            return;
        }

        // Resolve Type Name (DB stores Name, not ID)
        const typeDef = instrumentTypes[typeId];
        const typeName = typeDef ? typeDef.name : typeId;
        console.log('checkDuplicate: Resolved typeName:', typeName);

        try {
            const extraParams = {
                fileno: fileNo,
                instrument_type: typeName,
                date: date,
                prop_id: document.querySelector('[name="prop_id"]')?.value,
                party_1: document.querySelector('[name="party_1_name"]')?.value,
                party_2: document.querySelector('[name="party_2_name"]')?.value,
                op_serial: document.querySelector('[name="op_serial_number"]')?.value,
                reg_no: document.querySelector('[name="reg_no"]')?.value
            };
            const params = new URLSearchParams(Object.fromEntries(
                Object.entries(extraParams).filter(([_, v]) => v !== null && v !== undefined && v !== '')
            ));
            const url = `/api/instruments/check-duplicate?${params.toString()}`;
            console.log('checkDuplicate: Fetching URL:', url);

            const res = await fetch(url);
            const data = await res.json();
            console.log('checkDuplicate: Received response:', data);

            // Handle PRA History Alert
            if (data.pra_history && data.pra_history.length > 0) {
                duplicateCheckState.praHistory = data.pra_history;
                setPraHistoryAlertVisibility(true);
            }

            // SURRENDER & RELEASE MORTGAGE RECOGNITION CONVENTION
            if (currentInstrumentType === 'deed-of-surrender-release') {
                const mortgageRecords = data.pra_history ? data.pra_history.filter(rec => {
                    const type = (rec.transaction_type || rec.instrument_type || '').toLowerCase();
                    return type.includes('mortgage');
                }) : [];

                if (mortgageRecords.length > 0) {
                    let htmlContent = `
                        <div class="text-left space-y-3 mt-2 max-h-[300px] overflow-y-auto pr-1">
                            <p class="text-xs text-slate-600 mb-3 font-semibold">Multiple mortgage records have been identified. Please select the one you wish to release & backfill:</p>
                    `;

                    mortgageRecords.forEach((mortgageRecord, idx) => {
                        const mortgagor = mortgageRecord.party_1 || mortgageRecord.Mortgagor || 'N/A';
                        const mortgagee = mortgageRecord.party_2 || mortgageRecord.Mortgagee || 'N/A';
                        
                        const serNo = mortgageRecord.serialNo || mortgageRecord.serial_no || '0';
                        const pgNo = mortgageRecord.pageNo || mortgageRecord.page_no || mortgageRecord.reg_page_no || '0';
                        const volNo = mortgageRecord.volumeNo || mortgageRecord.volume_no || '0';
                        const particulars = `${serNo}/${pgNo}/${volNo}`;
                        
                        const dateVal = mortgageRecord.deeds_date || mortgageRecord.transaction_date || mortgageRecord.assignment_date || mortgageRecord.regDate || mortgageRecord.created_at || '';
                        const formattedDate = dateVal ? new Date(dateVal).toLocaleDateString() : 'N/A';

                        const checked = idx === 0 ? 'checked' : '';

                        htmlContent += `
                            <label class="block cursor-pointer bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl p-3.5 relative transition-all duration-200 group">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="selected_mortgage" value="${idx}" ${checked} class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <div class="space-y-1 w-full">
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] uppercase font-black text-indigo-600 tracking-wider">Option ${idx + 1}</span>
                                            <span class="text-[10px] font-mono font-bold text-slate-400">Reg: ${particulars}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-y-1 text-xs pt-1 border-t border-slate-100 mt-1">
                                            <span class="font-semibold text-slate-500">Party 1:</span>
                                            <span class="text-slate-800 font-bold break-all">${mortgagor.toUpperCase()}</span>
                                            <span class="font-semibold text-slate-500">Party 2:</span>
                                            <span class="text-slate-800 font-bold break-all">${mortgagee.toUpperCase()}</span>
                                            <span class="font-semibold text-slate-500">Reg Date:</span>
                                            <span class="text-slate-800 font-medium">${formattedDate}</span>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        `;
                    });

                    htmlContent += `</div>`;

                    Swal.fire({
                        title: 'Active Mortgage Identified!',
                        html: htmlContent,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Map & Apply Details',
                        cancelButtonText: 'Manual Capture',
                        confirmButtonColor: '#4f46e5',
                        cancelButtonColor: '#94a3b8',
                        allowOutsideClick: false,
                        preConfirm: () => {
                            const selectedRadio = document.querySelector('input[name="selected_mortgage"]:checked');
                            if (!selectedRadio) {
                                Swal.showValidationMessage('Please select a mortgage to continue');
                                return false;
                            }
                            return selectedRadio.value;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const selectedIndex = parseInt(result.value);
                            const chosenRecord = mortgageRecords[selectedIndex];
                            autoFillReleaseFromMortgage(chosenRecord);
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Mortgage Data Mapped',
                                text: 'Property details and parties have been updated for release.',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                        }
                    });
                } else {
                    // Alert user to capture the existing mortgage first
                    showMissingMortgageAlert(fileNo);
                }
            }

            if (data.exists) {
                console.log('checkDuplicate: Duplicate found! Instrument ID:', data.instrument.id);
                duplicateCheckState.existingRecord = data.instrument;
                duplicateCheckState.propertyData = data.pra;
                duplicateCheckState.consentApp = data.consent_app || null;
                // If there is no separate prior record, reuse the duplicate record itself
                // so Create New can still auto-fill from instrument_capture data.
                duplicateCheckState.priorInstrument = data.prior_instrument || data.instrument || null;

                // Pre-fill immediately so data is already available even before user chooses
                // Create New or dismisses the duplicate modal.
                if (!duplicateCheckState.isUpdateMode) {
                    const hasConsNow = !!duplicateCheckState.consentApp;
                    const hasPriorNow = !!duplicateCheckState.priorInstrument;
                    if (hasConsNow || hasPriorNow) {
                        console.log('checkDuplicate: Immediate pre-fill on duplicate modal open.', {
                            consent: hasConsNow,
                            prior: hasPriorNow
                        });
                        autoFillFromConsent(duplicateCheckState.consentApp, duplicateCheckState.priorInstrument);
                    }
                }

                // Lock submit to prevent duplicate capture (only on /instruments/create)
                if (isInstrumentCaptureCreatePage) {
                    alreadyCapturedLock = true;
                    if (elements.submitBtn) {
                        elements.submitBtn.disabled = true;
                        elements.submitBtn.className = 'px-6 py-2.5 text-white font-medium bg-gray-400 rounded-lg cursor-not-allowed opacity-60 shadow-md flex items-center gap-2';
                        elements.submitBtn.setAttribute('title', 'This instrument has already been captured. Use Update Existing to modify the record.');
                    }
                }

                showDuplicateModal(data.instrument, data.pra, fileNo);
            } else {
                console.log('checkDuplicate: No duplicate exists.');

                // Auto-fill from Consent Application and/or prior instrument record
                if (!duplicateCheckState.isUpdateMode) {
                    const hasCons = !!data.consent_app;
                    const hasPrior = !!data.prior_instrument;
                    if (hasCons || hasPrior) {
                        console.log('checkDuplicate: Auto-filling.', { consent: hasCons, prior: hasPrior });
                        autoFillFromConsent(data.consent_app, data.prior_instrument);
                    }
                }
            }
        } catch (e) {
            console.error('checkDuplicate: Error during fetch:', e);
        }

        // ── Consent Gate: show early warning on file selection ───────────────────
        await runConsentGateCheck({ silent: false });
        // ─────────────────────────────────────────────────────────────────────────
    }

    function showMissingMortgageAlert(fileNo) {
        Swal.fire({
            title: 'No Existing Mortgage Found!',
            html: `
                <div class="text-left">
                    <p class="mb-3">A <strong>Deed of Surrender & Release</strong> requires an existing active mortgage to be released.</p>
                    <p class="mb-3 text-red-600 font-semibold">No mortgage record was found for File Number: ${fileNo}</p>
                    <p class="text-sm text-gray-600">You must capture the existing mortgage first before capturing a Surrender & Release.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Capture Existing Mortgage Now',
            cancelButtonText: 'Cancel Capture',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                openPraMortgageModal(fileNo);
            } else {
                warnCannotSubmitWithoutMortgage();
            }
        });
    }

    function openPraMortgageModal(fileNo) {
        const modal = document.getElementById('property-form-dialog');
        const modalTitle = document.querySelector('#property-form-dialog h2');

        if (modal) {
            if (modalTitle) {
                modalTitle.textContent = 'Capture Existing Mortgage';
                modalTitle.dataset.customTitle = 'Capture Existing Mortgage';
            }
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            modal.style.setProperty('z-index', '1000040', 'important');
            
            // Auto-select Deed of Mortgage
            if (window.PraFormController && window.PraFormController.controller) {
                window.PraFormController.controller.setState('transactionType', 'Deed of Mortgage');
            }

            // Fill File Number selection
            if (typeof window.applyPrimaryFileSelection === 'function') {
                window.applyPrimaryFileSelection({ fileno: fileNo, mlsFileno: fileNo }, 'instruments-page');
            }

            // Backfill the property details from any existing record on this file
            // so capturing the missing mortgage starts from the file's known
            // location/plot/land-use rather than a blank form.
            if (window.PraFormController && window.PraFormController.controller
                && typeof window.PraFormController.controller.autoBackfillPropertyDetailsFromFile === 'function') {
                window.PraFormController.controller.autoBackfillPropertyDetailsFromFile(fileNo);
            }
        } else {
            console.error('Property form dialog modal not found in DOM.');
        }
    }

    function warnCannotSubmitWithoutMortgage() {
        Swal.fire({
            title: 'Mortgage Capture Required',
            text: 'A Surrender & Release cannot be registered without an existing Mortgage record. Please capture the mortgage first.',
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6'
        });

        // Disable submit button on the main form
        if (elements.submitBtn) {
            elements.submitBtn.disabled = true;
            elements.submitBtn.className = 'px-6 py-2.5 text-white font-medium bg-gray-400 rounded-lg cursor-not-allowed opacity-60 shadow-md flex items-center gap-2';
            elements.submitBtn.setAttribute('title', 'A Deed of Surrender and Release requires an existing Mortgage record.');
        }
    }

    function showDuplicateModal(instrument, pra, fileNo) {
        const duplicateFileNo = (
            fileNo ||
            instrument?.mlsFNo ||
            instrument?.kangisFileNo ||
            instrument?.NewKANGISFileno ||
            instrument?.np_fileno ||
            instrument?.temp_fileno ||
            instrument?.fileno ||
            instrument?.file_number ||
            (typeof elements !== 'undefined' && elements.displayFileno?.value) ||
            'N/A'
        );
        console.log('showDuplicateModal: Starting with', { instrument, pra });
        const modal = document.getElementById('duplicate-modal');
        const detailsContainer = document.getElementById('duplicate-details');
        const closeBtn = document.getElementById('btn-close-duplicate');
        const closeFooterBtn = document.getElementById('btn-close-duplicate-footer');

        if (!modal) {
            console.error('ERROR: Cannot find element with ID "duplicate-modal". Please check create.blade.php.');
            return;
        }

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        modal.style.zIndex = '1000030';

        // Format details
        let html = `
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.166 2.357-1.166 3.03 0l6.28 10.875c.673 1.166-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.459-1.516-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        A potential duplicate record has been found. Please review the details below.
                    </p>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <span class="font-semibold">File Number:</span>
            <span class="break-all">${duplicateFileNo}</span>
            <span class="font-semibold">Instrument:</span>
            <span>${instrument.instrument_type || 'N/A'}</span>
            <span class="font-semibold">Reg No:</span>
            <span>${instrument.reg_no || instrument.registration_number || 'Pending'}</span>
            <span class="font-semibold">Date:</span>
            <span>${instrument.entry_date || instrument.created_at || 'N/A'}</span>
        </div>
        <div class="mt-2 pt-2 border-t border-gray-200">
            <span class="font-semibold block mb-1">Parties:</span>
            <span class="block truncate text-xs text-gray-600">${instrument.party_1_name || 'N/A'}</span>
            <span class="block truncate text-xs text-gray-600">${instrument.party_2_name || 'N/A'}</span>
        </div>
    `;

        if (pra) {
            const desc = (pra.property_description || pra.propertyDescription || '').trim();
            const loc = (pra.location || pra.property_location || '').trim();
            
            let displayString = desc;
            if (loc && loc.toLowerCase() !== desc.toLowerCase()) {
                if (displayString) {
                    displayString += `<br>${loc}`;
                } else {
                    displayString = loc;
                }
            }
            
            if (displayString) {
                html += `
                <div class="mt-2 pt-2 border-t border-gray-200 text-xs">
                    <span class="font-semibold text-green-700 block mb-1">Property Record Found:</span>
                    <p class="text-gray-600 leading-relaxed">${displayString}</p>
                </div>
                `;
            }
        }

        detailsContainer.innerHTML = html;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        const updateBtn = document.getElementById('btn-update-existing');
        const createBtn = document.getElementById('btn-create-new');
        
        if (updateBtn) {
            updateBtn.classList.remove('hidden');
            if (instrument) {
                const isRegistered = !!(instrument.registration_number || instrument.reg_no || instrument.is_deed_registered == 1);
                if (isRegistered) {
                    updateBtn.innerHTML = `<i class="fas fa-eye mr-2"></i> View / Load Existing Record`;
                    updateBtn.className = 'px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white text-base font-medium rounded-md w-full shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-300';
                } else {
                    updateBtn.innerHTML = `<i class="fas fa-edit mr-2"></i> Update Existing Record`;
                    updateBtn.className = 'px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-base font-medium rounded-md w-full shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300';
                }
            } else {
                updateBtn.innerHTML = `<i class="fas fa-edit mr-2"></i> Update Existing Record`;
                updateBtn.className = 'px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-base font-medium rounded-md w-full shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300';
            }
        }
        if (createBtn) {
            createBtn.classList.remove('hidden');
        }

        const closeBtnNow = document.getElementById('btn-close-duplicate');
        const closeFooterBtnNow = document.getElementById('btn-close-duplicate-footer');

        const closeDuplicateModal = () => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            if (modal._duplicateEscHandler) {
                document.removeEventListener('keydown', modal._duplicateEscHandler);
                delete modal._duplicateEscHandler;
            }
        };

        if (!updateBtn || !createBtn) {
            console.error('Duplicate modal action buttons were not found.');
            return;
        }

        // Clone nodes to remove old listeners
        const updateBtnClone = updateBtn.cloneNode(true);
        const createBtnClone = createBtn.cloneNode(true);
        const closeBtnClone = closeBtnNow ? closeBtnNow.cloneNode(true) : null;
        const closeFooterBtnClone = closeFooterBtnNow ? closeFooterBtnNow.cloneNode(true) : null;
        updateBtn.parentNode.replaceChild(updateBtnClone, updateBtn);
        createBtn.parentNode.replaceChild(createBtnClone, createBtn);
        if (closeBtnNow && closeBtnClone) {
            closeBtnNow.parentNode.replaceChild(closeBtnClone, closeBtnNow);
        }
        if (closeFooterBtnNow && closeFooterBtnClone) {
            closeFooterBtnNow.parentNode.replaceChild(closeFooterBtnClone, closeFooterBtnNow);
        }

        updateBtnClone.onclick = (event) => {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            handleUpdateExisting();
            closeDuplicateModal();
        };

        createBtnClone.onclick = (event) => {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            handleCreateNew();
            closeDuplicateModal();
        };
        if (closeBtnClone) {
            closeBtnClone.onclick = (event) => {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                closeDuplicateModal();
                handleCreateNew();
            };
        }
        if (closeFooterBtnClone) {
            closeFooterBtnClone.onclick = (event) => {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                closeDuplicateModal();
                handleCreateNew();
            };
        }

        modal.onclick = (event) => {
            if (event.target === modal) {
                closeDuplicateModal();
                handleCreateNew();
            }
        };

        if (modal._duplicateEscHandler) {
            document.removeEventListener('keydown', modal._duplicateEscHandler);
        }
        modal._duplicateEscHandler = (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeDuplicateModal();
                handleCreateNew();
            }
        };
        document.addEventListener('keydown', modal._duplicateEscHandler);
    }

    function handleUpdateExisting() {
        const record = duplicateCheckState.existingRecord;
        if (!record) return;

        duplicateCheckState.isUpdateMode = true;
        autoFilledSubmitMode = false;
        
        // Check if already registered
        const isRegistered = !!(record.registration_number || record.reg_no || record.is_deed_registered == 1);
        alreadyCapturedLock = isRegistered;

        if (isRegistered) {
            elements.submitBtn.textContent = 'Already Registered';
            elements.submitBtn.disabled = true;
            elements.submitBtn.className = 'px-6 py-2.5 text-white font-medium bg-gray-400 rounded-lg cursor-not-allowed opacity-60 shadow-md flex items-center gap-2';
            elements.submitBtn.setAttribute('title', 'This instrument has already been registered. Modification is restricted.');
            elements.submitBtn.innerHTML = `<i class="fas fa-lock mr-2"></i> Already Registered`;
        } else {
            alreadyCapturedLock = false;
            elements.submitBtn.textContent = 'Update Instrument';
            elements.submitBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            elements.submitBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            elements.submitBtn.classList.add('bg-amber-600', 'hover:bg-amber-700');
            elements.submitBtn.disabled = false;
            elements.submitBtn.innerHTML = `<i class="fas fa-save mr-2"></i> Update Instrument`;
            elements.submitBtn.removeAttribute('title');
        }

        setHidden('id', record.id);
        const storeUrlInput = document.getElementById('storeUrl');
        if (storeUrlInput) {
            storeUrlInput.value = `/instruments/${record.id}`;
            setHidden('_method', 'PUT');
        }

        populateForm(record);
        
        const swalTitle = isRegistered ? 'View Mode (Registered)' : 'Update Mode';
        const swalText = isRegistered 
            ? 'This record is already registered. Modification is disabled to ensure data integrity.' 
            : 'Form pre-filled with existing record data. You are now in UPDATE mode.';
        
        Swal.fire({
            icon: isRegistered ? 'warning' : 'info',
            title: swalTitle,
            text: swalText,
            timer: 3000,
            showConfirmButton: false
        });
    }

    function handleCreateNew() {
        duplicateCheckState.isUpdateMode = false;
        // Do NOT clear alreadyCapturedLock here — if a duplicate was found,
        // the lock persists to prevent re-submitting the same instrument.
        if (!alreadyCapturedLock) {
            resetSubmitButton();
        }

        const methodField = elements.registrationForm.querySelector('input[name="_method"]');
        if (methodField) methodField.remove();

        const storeUrlInput = document.getElementById('storeUrl');
        if (storeUrlInput) storeUrlInput.value = '/instruments/store';

        // Auto-fill from consent/prior instrument when user chooses to create new
        const hasCons = !!duplicateCheckState.consentApp;
        const hasPrior = !!duplicateCheckState.priorInstrument;
        if (hasCons || hasPrior) {
            console.log('handleCreateNew: Auto-filling from stored consent/prior.', { consent: hasCons, prior: hasPrior });
            try {
                autoFillFromConsent(duplicateCheckState.consentApp, duplicateCheckState.priorInstrument);
            } catch (error) {
                console.error('handleCreateNew: Auto-fill failed', error);
            }
        }
    }

    function getSubmitLabel() {
        const override = (window.ossOpSubmitLabel || '').toString().trim();
        if (override) return override;
        if (autoFilledSubmitMode) {
            return 'Register Instrument';
        }
        return 'Capture & Register Instrument';
    }

    function updateOpSubmitAvailability() {
        if (!elements.submitBtn) return;

        // Never re-enable if locked due to already-captured record
        if (alreadyCapturedLock) return;

        if (currentInstrumentType !== 'occupancy-permit') {
            elements.submitBtn.removeAttribute('title');
            return;
        }

        const systemFileNo = (elements.displayFileno?.value || '').toString().trim();
        const hasSystemFileNo = systemFileNo.length > 0;
        elements.submitBtn.disabled = !hasSystemFileNo;

        if (!hasSystemFileNo) {
            elements.submitBtn.setAttribute('title', 'System FileNo is required before submission.');
        } else {
            elements.submitBtn.removeAttribute('title');
        }
    }

    function applySubmitButtonLabel() {
        if (!elements.submitBtn) return;

        // Never re-enable if locked due to already-captured record
        if (alreadyCapturedLock) return;

        const label = getSubmitLabel();
        elements.submitBtn.dataset.submitting = '0';
        elements.submitBtn.disabled = false;
        elements.submitBtn.textContent = label;
        elements.submitBtn.innerHTML = `<i class="fas fa-save mr-2"></i> ${label}`;
        elements.submitBtn.className = 'px-6 py-2.5 text-white font-medium bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-md flex items-center gap-2';
        updateOpSubmitAvailability();
    }

    function resetSubmitButton() {
        applySubmitButtonLabel();
    }

    function setAutoFilledSubmitMode(isAutoFilled) {
        autoFilledSubmitMode = Boolean(isAutoFilled);
        applySubmitButtonLabel();
    }

    function setOssOpSubmitMode(isContinueMode) {
        if (window.ossOpContext !== true && window.ffrExistingManualRegistration !== true && isInstrumentCaptureCreatePage) return;
        window.ossOpSubmitLabel = isContinueMode
            ? 'Continue with File Commissioning'
            : 'Capture Existing OP';
        applySubmitButtonLabel();
    }

    function isFfrExistingManualRegistrationFlow() {
        return window.ffrExistingManualRegistration === true;
    }

    function restoreCommissionOpSelection(preferredType) {
        const modalContainer = document.querySelector('#generateModal [x-data="fileNumberGenerator()"]');
        const component = modalContainer && modalContainer._x_dataStack ? modalContainer._x_dataStack[0] : null;
        const normalizedType = String(preferredType || '').toLowerCase() === 'resettlement' ? 'resettlement' : 'direct';

        if (component) {
            component.applicationType = 'new';
            component.allocatedByFilter = '';
            component.defaultAllocationType = normalizedType;
            if (Object.prototype.hasOwnProperty.call(component, '_currentAllocationSourceType')) {
                component._currentAllocationSourceType = 'op';
            }
        }

        // Keep DOM radios in sync because parts of this section are event-driven.
        const appTypeRadio = document.querySelector('input[name="application_type"][value="new"]');
        if (appTypeRadio) {
            appTypeRadio.checked = true;
            appTypeRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const allocationSourceOpRadio = document.querySelector('input[name="allocated_by_filter"][value=""]');
        if (allocationSourceOpRadio) {
            allocationSourceOpRadio.checked = true;
            allocationSourceOpRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const defaultTypeRadio = document.querySelector(`input[name="default_allocation_type"][value="${normalizedType}"]`);
        if (defaultTypeRadio) {
            defaultTypeRadio.checked = true;
        }
    }

    async function continueWithFileCommissioningFromLookup(record) {
        const modalContainer = document.querySelector('#generateModal [x-data="fileNumberGenerator()"]');
        const component = modalContainer && modalContainer._x_dataStack ? modalContainer._x_dataStack[0] : null;

        const fileName = (record.party_2_name || record.party_1_name || '').toString().trim();
        const plotNo = (record.plot_number || '').toString().trim();
        const tpNo = (record.tp_no || '').toString().trim();
        const location = (record.property_location || record.property_description || '').toString().trim();
        const lga = (record.lga || '').toString().trim();
        const opType = String(record.op_type || '').toLowerCase();
        const defaultAllocationType = opType.includes('resettlement') ? 'resettlement' : 'direct';
        const sourceTable = String(record.source_table || 'instrument_capture').toLowerCase();
        const hasSourceCapture = sourceTable === 'instrument_capture' && !!(record.id && String(record.id).trim() !== '');
        const sourcePraId = record.source_pra_id || (sourceTable === 'pra' ? record.id : '');
        const hasExistingSource = hasSourceCapture || !!(sourcePraId && String(sourcePraId).trim() !== '');
        const hasPropId = !!(record.prop_id && String(record.prop_id).trim() !== '');
        const skipPraUpdatePrompt = record && record.skip_pra_update_prompt === true;

        // For existing OP records, first sync PRA with temp fileno before opening
        // commissioning; this preserves prop_id continuity for the follow-up update.
        const isOpCommissionFlow = window.ossOpContext === true || (!isInstrumentCaptureCreatePage && hasSourceCapture);
        if (isOpCommissionFlow && hasSourceCapture) {
            // Show Update & Continue confirmation for FEFR flows
            if (isFfrExistingManualRegistrationFlow()) {
                const confirmSync = await Swal.fire({
                    icon: 'question',
                    title: 'Update Existing Record?',
                    html: '<p class="text-sm text-gray-700">This will update the existing PRA record' + (record.prop_id ? ' <strong>(Prop ID: ' + record.prop_id + ')</strong>' : '') + ' with your changes before proceeding to file commissioning.</p>',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Update & Continue',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#6b7280',
                });
                if (!confirmSync.isConfirmed) {
                    if (elements.submitBtn) {
                        elements.submitBtn.disabled = false;
                        elements.submitBtn.dataset.submitting = '';
                        applySubmitButtonLabel();
                    }
                    return;
                }
            }
            const tempPraOk = await syncExistingOpToPraBeforeCommission(record);
            if (!tempPraOk) {
                Swal.fire({
                    icon: 'error',
                    title: 'PRA Sync Failed',
                    text: 'Could not sync the existing OP temp file to PRA. Please retry Capture Existing OP.'
                });
                return;
            }
        } else if (isOpCommissionFlow && !hasSourceCapture && hasPropId && !skipPraUpdatePrompt) {
            // PRA-sourced OP record in OSS flow: record already exists in PRA.
            // Update the existing record with any edits the user made (e.g. registration details).
            const formData = new FormData(elements.registrationForm);
            const formPayload = buildPraPayload(formData);
            // Ensure prop_id is carried over from the matched record
            formPayload.prop_id = record.prop_id;

            // FEFR-only confirmation. In OSS Commission flow we are about to ask
            // the user for Subdivision/Merger/Proceed in the next dialog anyway;
            // showing two prompts back-to-back is confusing and was the cause of
            // the "have to click twice to reach commissioning" bug.
            if (isFfrExistingManualRegistrationFlow()) {
                const confirmResult = await Swal.fire({
                    icon: 'question',
                    title: 'Update Existing Record?',
                    html: '<p class="text-sm text-gray-700">This will update the existing PRA record <strong>(Prop ID: ' + record.prop_id + ')</strong> with your changes before proceeding to file commissioning.</p>',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Update & Continue',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#6b7280',
                });

                if (!confirmResult.isConfirmed) {
                    if (elements.submitBtn) {
                        elements.submitBtn.disabled = false;
                        elements.submitBtn.dataset.submitting = '';
                        applySubmitButtonLabel();
                    }
                    return;
                }
            }

            const updatePayload = Object.assign({}, formPayload);
            delete updatePayload.prop_id;
            // Flag to update shared property details on ALL sibling rows (OP + Transfer of Title)
            updatePayload.update_siblings = true;
            const updateOk = await upsertPraByPropId(Object.assign(updatePayload, { prop_id: record.prop_id }));
            if (!updateOk) {
                Swal.fire({
                    icon: 'error',
                    title: 'PRA Update Failed',
                    text: 'Could not update the existing PRA record. Please retry.'
                });
                if (elements.submitBtn) {
                    elements.submitBtn.disabled = false;
                    elements.submitBtn.dataset.submitting = '';
                    applySubmitButtonLabel();
                }
                return;
            }

            const tempFileno = (formPayload.temp_fileno || formPayload.fileno || '').toString().trim();
            window.pendingExistingOpPraContext = {
                prop_id: record.prop_id,
                source_pra_id: sourcePraId || '',
                temp_fileno: tempFileno,
                source_op_party_2: (record.party_2_name || '').toString().trim() || null,
                base_payload: Object.assign({}, formPayload, { prop_id: record.prop_id })
            };
        } else if (isFfrExistingManualRegistrationFlow() && hasPropId) {
            // FEFR existing flow: UPDATE PRA record (not create) before commissioning.
            const confirmFfr = await Swal.fire({
                icon: 'question',
                title: 'Update Existing Record?',
                html: '<p class="text-sm text-gray-700">This will update the existing PRA record <strong>(Prop ID: ' + record.prop_id + ')</strong> with your changes before proceeding to file commissioning.</p>',
                showCancelButton: true,
                confirmButtonText: 'Yes, Update & Continue',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
            });
            if (!confirmFfr.isConfirmed) {
                if (elements.submitBtn) {
                    elements.submitBtn.disabled = false;
                    elements.submitBtn.dataset.submitting = '';
                    applySubmitButtonLabel();
                }
                return;
            }
            const payload = buildPraPayloadFromExistingOp(record);
            const updateOk = await upsertPraByPropId(payload);
            if (!updateOk) {
                Swal.fire({
                    icon: 'error',
                    title: 'PRA Update Failed',
                    text: 'Could not update the existing PRA record. Please retry.'
                });
                return;
            }
            const tempFileno = (payload.temp_fileno || payload.fileno || '').toString().trim();
            window.pendingExistingOpPraContext = {
                prop_id: record.prop_id,
                source_pra_id: sourcePraId || '',
                temp_fileno: tempFileno,
                source_op_party_2: (record.party_2_name || '').toString().trim() || null,
                base_payload: Object.assign({}, payload, { prop_id: record.prop_id })
            };
        }

        // Persist last selected source OP so commission submit can recover linkage
        // if duplicate modals/forms exist on the page.
        window.lastCommissionSourceOp = hasExistingSource ? {
            id: hasSourceCapture ? (record.id || '') : '',
            source_pra_id: sourcePraId || '',
            prop_id: record.prop_id || '',
            op_serial_number: record.op_serial_number || '',
            registration_number: record.registration_number || record.deeds_serial_no || '',
            serial_no: record.serial_no || '',
            page_no: record.page_no || record.serial_no || '',
            volume_no: record.volume_no || '',
            original_owner: record.party_2_name || ''
        } : null;

        // ── OP Saved! action dialog (FileNo Commissioning path only) ──────────
        // For the FileNo Commissioning button flow on the OSS OP page, after the
        // existing OP has been synced to PRA we ask the user whether to flag this
        // OP as Subdivision (multiple files), Merger (another OP coming), or just
        // Continue with File Commissioning. FEFR flow has its own equivalent
        // dialog wired via the `ffr-existing-op-captured` event, so we skip it
        // here when in FEFR mode.
        //
        // skip_op_saved_dialog: set when the caller (e.g. submitPraRecord for a
        // brand-new OP capture) already showed the OP Saved! dialog. Without
        // this guard the user sees the same dialog twice and has to click
        // "Continue with File Commissioning" two times before the commission
        // modal opens.
        if (window.ossOpContext === true && !isFfrExistingManualRegistrationFlow() && window._isOngoingMerger !== true && record && record.skip_op_saved_dialog !== true) {
            const opSavedAction = await showOpSavedActionsDialog({ propId: record.prop_id });

            if (opSavedAction === 'merger') {
                if (!window._mergerGroupId) {
                    window._mergerGroupId = generateMergerUuid();
                }
                window._isOngoingMerger = true;

                // Keep FEFR flow off while looping in Commission flow.
                window.ffrExistingManualRegistration = false;
                window.ffrDirectOpMode = false;

                // Prevent stale OP lookup match (and its prop_id leakage) from
                // hijacking the next merger OP submit. Without clearing
                // window.lastCommissionSourceOp, buildPraPayload's resolvedPropId
                // fallback would inherit the previous OP's prop_id, causing the
                // next save to UPSERT the previous row instead of creating a new
                // one — i.e. "two mergers but only one saved".
                opLookupMatchedRecord = null;
                window.lastCommissionSourceOp = null;
                alreadyCapturedLock = false;
                if (typeof setOssOpSubmitMode === 'function') {
                    setOssOpSubmitMode(false);
                }
                if (typeof clearOpLookupResultsCards === 'function') {
                    clearOpLookupResultsCards();
                }

                if (record.prop_id) {
                    await stampPraMergerGroup(record.prop_id, window._mergerGroupId);
                }

                resetOpFormForNextMergerCapture();
                if (elements.submitBtn) {
                    elements.submitBtn.dataset.submitting = '';
                    elements.submitBtn.disabled = false;
                    applySubmitButtonLabel();
                }

                // Keep OP modal active so user can capture the next seller's OP.
                window._opCaptureSubmitted = false;
                return;
            }
            // 'proceed' (or after Subdivision flagged) — fall through to commission.
        }

        if (component) {
            component.applicationType = 'new';
            component.allocatedByFilter = '';
            // Keep OP allocation source selected in the commission modal.
            if (Object.prototype.hasOwnProperty.call(component, '_currentAllocationSourceType')) {
                component._currentAllocationSourceType = 'op';
            }
            // The "op" radio and its "New Applications (Existing OP Change of Name)"
            // sub-option share name="allocation_source_type", so natively checking
            // the sub-option (which is how this flow was entered) unchecks "op" in
            // the browser's radio group — setting the JS property above does not
            // re-check the actual DOM radio. Sync it explicitly so "Occupancy
            // Permit (OP)" still shows as selected when the modal reappears.
            const opSourceRadioNow = document.querySelector('input[name="allocation_source_type"][value="op"]');
            if (opSourceRadioNow) {
                opSourceRadioNow.checked = true;
            }
            component.defaultAllocationType = defaultAllocationType;
            component.batchMode = false;
            component.plotNo = plotNo;
            component.tpNo = tpNo;
            component.location = location;
            component.lga = lga;
            component.sourceInstrumentCaptureId = hasSourceCapture ? (record.id || '') : '';
            component.sourcePropId = record.prop_id || '';
            component.sourcePraId = sourcePraId || '';
            component.sourceOpSerialNumber = record.op_serial_number || '';
            component.sourceRegistrationNumber = record.registration_number || record.deeds_serial_no || '';
            component.sourceSerialNo = record.serial_no || '';
            component.sourcePageNo = record.page_no || record.serial_no || '';
            component.sourceVolumeNo = record.volume_no || '';
            component.sourceOriginalOwner = record.party_2_name || '';
            // Existing OP linkage should be enforced; brand-new OP flow keeps this false.
            component.requireOpSource = hasExistingSource;

            if (!Array.isArray(component.locationEntries) || component.locationEntries.length === 0) {
                component.locationEntries = [{ plotNo: '', tpNo: '', location: '', lga: '', tracking_id: null }];
                component.currentEntryIndex = 0;
            }

            component.locationEntries[0].plotNo = plotNo;
            component.locationEntries[0].tpNo = tpNo;
            component.locationEntries[0].location = location;
            component.locationEntries[0].lga = lga;

            if (typeof component.updatePreview === 'function') {
                component.updatePreview();
            }

            if (typeof component.handleAllocationFilterChange === 'function') {
                component.handleAllocationFilterChange();
            }

            // Set fileName AFTER handleAllocationFilterChange() which resets it to ''.
            // For Change of Name, do NOT prefill allottee name as File Name. Same
            // reasoning applies on the MLS File Number Generator page: the OP is
            // selected only to inherit property/location details, and the new file's
            // owner must be entered fresh rather than defaulting to the OP's allottee.
            if (component.subSource !== 'OP Change of Name' && !isMlsFileNumberGeneratorPage) {
                component.fileName = fileName;
            }
        }





        // Hard-set hidden fields so submitForm() always receives source linkage values,
        // even if Alpine reactivity timing is delayed.


        const setHidden = (name, value) => {
            const els = document.querySelectorAll(`form#generateForm input[name="${name}"]`);
            els.forEach((el) => {
                el.value = value || '';
            });
        };
        setHidden('source_instrument_capture_id', hasSourceCapture ? (record.id || '') : '');
        setHidden('source_prop_id', record.prop_id || '');
        setHidden('source_pra_id', sourcePraId || '');
        setHidden('source_op_serial_number', record.op_serial_number || '');
        setHidden('source_registration_number', record.registration_number || record.deeds_serial_no || '');
        setHidden('source_serial_no', record.serial_no || '');
        setHidden('source_page_no', record.page_no || record.serial_no || '');
        setHidden('source_volume_no', record.volume_no || '');
        setHidden('source_original_owner', record.party_2_name || '');
        setHidden('require_op_source', hasExistingSource ? '1' : '0');

        if (typeof window.openCommissionFileNoModal === 'function') {
            window.openCommissionFileNoModal();
        } else {
            const commissionModal = document.getElementById('generateModal');
            if (commissionModal) {
                commissionModal.classList.remove('hidden');
            }
        }

        // Re-apply radio checks explicitly because this UI section is not fully x-model driven.
        setTimeout(() => {
            const appTypeRadio = document.querySelector('input[name="application_type"][value="new"]');
            if (appTypeRadio) {
                appTypeRadio.checked = true;
                appTypeRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Re-affirm the "op" radio here too — Alpine's re-render of the Row 2
            // sub-option (or any other native radio-group interaction) can steal
            // the checked state back between the immediate sync above and now.
            const opSourceRadio = document.querySelector('input[name="allocation_source_type"][value="op"]');
            if (opSourceRadio) {
                opSourceRadio.checked = true;
            }

            // Re-apply fileName after radio change events which reset it via handleAllocationFilterChange()
            // For Change of Name, do NOT prefill allottee name as File Name (same guard as the initial set).
            if (component && fileName && component.subSource !== 'OP Change of Name' && !isMlsFileNumberGeneratorPage) {
                component.fileName = fileName;
            }
        }, 50);

        closeRegistrationDialog();
    }

    function buildPraPayloadFromExistingOp(record) {
        const text = (value) => (value || '').toString().trim();
        const tempFileno = text(record.temp_fileno || record.fileno || record.display_fileno || '');
        const regNo = text(record.regNo || record.reg_no || record.registration_number || record.deeds_serial_no || '');
        const grantor = text(record.party_1_name || record.party_1 || record.Grantor || record.grantor || '');
        const grantee = text(record.party_2_name || record.party_2 || record.Grantee || record.grantee || '');

        // Capture the exact source row the user selected from the lookup list so
        // the backend links THAT row, even if multiple OPs share the same
        // op_serial_number. Without this the backend falls back to "latest IC
        // row with this serial" and links the wrong OP to the new TOT.
        const sourceTableRaw = String(record.source_table || '').toLowerCase();
        const sourceOpTable = (sourceTableRaw === 'instrument_capture' || sourceTableRaw === 'deed_registrations')
            ? sourceTableRaw
            : null;
        const sourceOpIdRaw = sourceOpTable ? text(record.id) : '';
        const sourceOpId = sourceOpIdRaw && /^\d+$/.test(sourceOpIdRaw) ? Number(sourceOpIdRaw) : null;

        const result = {
            prop_id: text(record.prop_id) || null,
            mlsFNo: text(record.mlsFNo || record.mlsfNo) || null,
            kangisFileNo: text(record.kangisFileNo) || null,
            NewKANGISFileno: text(record.NewKANGISFileno) || null,
            fileno: tempFileno || null,
            temp_fileno: tempFileno || null,
            source: 'Occupancy Permit (OP)',
            transaction_type: text(record.transaction_type) || 'Occupancy Permit (OP)',
            transaction_date: text(record.transaction_date || record.entry_date || record.created_at) || null,
            reg_date: text(record.reg_date || record.entry_date || record.created_at) || null,
            reg_time: text(record.reg_time) || null,
            deeds_date: text(record.deeds_date || record.reg_date || record.entry_date || record.created_at) || null,
            deeds_time: text(record.deeds_time || record.reg_time) || null,
            regNo: regNo || null,
            serialNo: text(record.serial_no || record.serialNo) || null,
            pageNo: text(record.page_no || record.pageNo || record.serial_no || record.serialNo) || null,
            volumeNo: text(record.volume_no || record.volumeNo) || null,
            op_serial_number: text(record.op_serial_number) || null,
            op_type: text(record.op_type) || null,
            purpose: text(record.purpose) || null,
            land_use: text(record.land_use) || null,
            plot_no: text(record.plot_number || record.plot_no) || null,
            tp_no: text(record.tp_no) || null,
            lpkn_no: text(record.lpkn_no || record.lpknNo) || null,
            plot_size: text(record.plot_size) || null,
            house_no: text(record.house_no) || null,
            lgsaOrCity: text(record.lga) || null,
            location: text(record.district || record.lga) || null,
            property_description: text(record.property_description || record.property_location) || null,
            instrument_type: 'Occupancy Permit (OP)',
            parties: {
                grantor: grantor || null,
                grantee: grantee || null
            },
            Grantor: grantor || null,
            Grantee: grantee || null,
            party_1: grantor || null,
            party_2: grantee || null,
            source_op_id: sourceOpId,
            source_op_table: sourceOpTable
        };

        // Tag with OP Change of Name system source when on the OSS page.
        const currentUrl = new URL(window.location.href);
        if (currentUrl.pathname.includes('/lands-one-stop-shop/') || currentUrl.searchParams.get('type') === 'change-of-name') {
            result.system_source = 'OSSOPCHANGEOFNAME';
        }

        return result;
    }

    async function upsertPraByPropId(payload) {
        const propId = (payload?.prop_id || '').toString().trim();
        if (!propId) return false;

        const csrfToken = document.querySelector('input[name="_token"]')?.value || '';
        const baseHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        };

        try {
            const checkResp = await fetch(`/api/pra/v1/records/${encodeURIComponent(propId)}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const updatePayload = Object.assign({}, payload);
            delete updatePayload.prop_id;

            if (checkResp.ok) {
                const resp = await fetch(`/api/pra/v1/records/${encodeURIComponent(propId)}`, {
                    method: 'PUT',
                    headers: baseHeaders,
                    body: JSON.stringify(updatePayload)
                });
                const data = await resp.json().catch(() => null);
                return !!(resp.ok && data?.success);
            }

            const resp = await fetch('/api/pra/v1/records', {
                method: 'POST',
                headers: baseHeaders,
                body: JSON.stringify(payload)
            });
            const data = await resp.json().catch(() => null);
            return !!(resp.ok && data?.success);
        } catch (err) {
            console.warn('PRA upsert error:', err);
            return false;
        }
    }

    async function syncExistingOpToPraBeforeCommission(record) {
        const payload = buildPraPayloadFromExistingOp(record);
        const tempFileno = (payload.temp_fileno || payload.fileno || '').toString().trim();
        if (!tempFileno) {
            return false;
        }

        const preSync = await createOrUpdatePraBeforeCommission(payload);
        if (!preSync.success) {
            return false;
        }

        window.pendingExistingOpPraContext = {
            prop_id: preSync.propId,
            source_pra_id: preSync.praId,
            temp_fileno: tempFileno,
            source_op_party_2: (record.party_2_name || '').toString().trim() || null,
            base_payload: Object.assign({}, payload, { prop_id: preSync.propId })
        };

        return true;
    }

    function updateRegistrationParticulars() {
        const serial = (elements.regSerialInput?.value || '').trim();
        const volume = (elements.regVolumeInput?.value || '').trim();
        const page = serial || '';

        if (elements.regPageInput) {
            elements.regPageInput.value = page;
        }
        if (elements.regPageDisplay) {
            elements.regPageDisplay.value = page;
        }

        if (elements.registrationNumberInput) {
            if (!serial && !volume) {
                elements.registrationNumberInput.value = '';
            } else {
                elements.registrationNumberInput.value = `${serial}/${page}/${volume}`;
            }
        }
    }

    function getCurrentTimeHHMM() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
    }

    function populateForm(data) {
        // Configure form for UPDATE
        let methodField = elements.registrationForm.querySelector('input[name="_method"]');
        if (!methodField) {
            methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            elements.registrationForm.appendChild(methodField);
        }
        methodField.value = 'PUT';

        const storeUrlInput = document.getElementById('storeUrl');
        if (storeUrlInput) {
            // Update URL to the update route: /instruments/{id}
            storeUrlInput.value = `/instruments/${data.id}`;
        }

        safeSetValue('firstPartyName', data.party_1_name);
        safeSetValue('secondPartyName', data.party_2_name);
        safeSetValue('firstPartyStreet', data.party_1_address);
        safeSetValue('firstPartyPhone', data.party_1_phone);
        safeSetValue('secondPartyStreet', data.party_2_address);
        safeSetValue('secondPartyPhone', data.party_2_phone);

        if (data.party_3_name) {
            const toggle = document.getElementById('hasCoMortgagor');
            if (toggle) {
                toggle.checked = true;
                toggle.dispatchEvent(new Event('change'));
                safeSetValue('coMortgagorName', data.party_3_name);
                safeSetValue('coMortgagorAddress', data.party_3_address);
                safeSetValue('coMortgagorDistrict', data.party_3_district);
                safeSetValue('coMortgagorState', data.party_3_state);
                safeSetValue('coMortgagorLga', data.party_3_lga);
                safeSetValue('coMortgagorPhone', data.party_3_phone);
            }
        }

        if (data.party_4_name) {
            const toggle = document.getElementById('hasThirdParty');
            if (toggle) {
                toggle.checked = true;
                toggle.dispatchEvent(new Event('change'));
                safeSetValue('thirdPartyName', data.party_4_name);
                safeSetValue('thirdPartyAddress', data.party_4_address);
                safeSetValue('thirdPartyDistrict', data.party_4_district);
                safeSetValue('thirdPartyState', data.party_4_state);
                safeSetValue('thirdPartyLga', data.party_4_lga);
                safeSetValue('thirdPartyPhone', data.party_4_phone);
            }
        }

        if (data.party_5_name) {
            const toggle = document.getElementById('hasFourthParty');
            if (toggle) {
                toggle.checked = true;
                toggle.dispatchEvent(new Event('change'));
                safeSetValue('party5Name', data.party_5_name);
                safeSetValue('party5Address', data.party_5_address);
                safeSetValue('party5District', data.party_5_district);
                safeSetValue('party5State', data.party_5_state);
                safeSetValue('party5Lga', data.party_5_lga);
                safeSetValue('party5Phone', data.party_5_phone);
            }
        }

        if (data.solicitor_name) {
            const solToggle = document.getElementById('includeSolicitorSidebar');
            if (solToggle) {
                solToggle.checked = true;
                // Trigger any change listeners:
                solToggle.dispatchEvent(new Event('change'));
            }
        }
        safeSetValue('solicitorName', data.solicitor_name);
        safeSetValue('solicitorAddress', data.solicitor_address);
        safeSetValue('plotDescription', data.property_description);
        safeSetValue('plotLocation', data.properties_location || data.location); // Fallback
        safeSetValue('house_no', data.house_no);
        safeSetValue('plotNumber', data.plot_number);
        safeSetValue('tp_no', data.tp_no);
        safeSetValue('plotSize', data.plot_size);
        safeSetValue('surveyPlanNo', data.survey_plan_no);
        safeSetValue('lga', data.lga);

        // Handle District Logic (Standard vs Custom)
        const districtSelect = document.getElementById('district');
        if (districtSelect && data.district) {
            // Check if district exists in options (standard list)
            // Note: Options might be objects or just strings in value. 
            // In the Blade component, value="name", label="name".
            let optionExists = false;
            for (let i = 0; i < districtSelect.options.length; i++) {
                if (districtSelect.options[i].value === data.district) {
                    optionExists = true;
                    break;
                }
            }

            if (optionExists || data.district === 'Select District') {
                safeSetValue('district', data.district);
            } else {
                // If value not in list, assume it's 'Others' + custom value
                // Or if saved as "Others" explicitly
                safeSetValue('district', 'Others');
                safeSetValue('manual_district', data.district);

                // Ensure UI is updated (though safeSetValue triggers updateCustomSelect, 
                // we want to be sure manual district is shown)
                updateCustomSelect('district', 'Others');
            }
        }

        // New Fields
        safeSetValue('cofoTerm', data.cofoTerm || data.cofo_term);
        safeSetDateValue('cofoDate', data.cofoDate || data.cofo_date);
        safeSetValue('cofoRegParticulars', data.cofoRegParticulars || data.cofo_reg_particulars);
        safeSetValue('assignmentTerm', data.assignmentTerm || data.assignment_term);
        safeSetValue('leaseTerm', data.leaseTerm || data.lease_term);
        safeSetValue('subLeaseAmount', data.subLeaseAmount || data.sub_lease_amount);
        // Populate State and then LGA
        if (data.party_1_state) {
            safeSetValue('firstPartyState', data.party_1_state);
            fetchLgas(data.party_1_state, 'firstPartyLga', data.party_1_lga || data.party_1_city);
        }
        if (data.party_1_district) {
            safeSetValue('firstPartyDistrict', data.party_1_district);
        }

        if (data.party_2_state) {
            safeSetValue('secondPartyState', data.party_2_state);
            fetchLgas(data.party_2_state, 'secondPartyLga', data.party_2_lga || data.party_2_city);
        }
        if (data.party_2_district) {
            safeSetValue('secondPartyDistrict', data.party_2_district);
        }

        if (data.party_3_district) {
            safeSetValue('coMortgagorDistrict', data.party_3_district);
        }

        if (data.solicitor_state) {
            safeSetValue('solicitorState', data.solicitor_state);
            fetchLgas(data.solicitor_state, 'solicitorLga', data.solicitor_lga || data.solicitor_city);
        }
        if (data.solicitor_district) {
            safeSetValue('solicitorDistrict', data.solicitor_district);
        }

        // Property State & LGA — always Kano (the registry only holds Kano State
        // lands and the field is readonly).
        safeSetValue('propertyState', 'Kano');
        fetchLgas('Kano', 'lga', data.lga);

        safeSetDateValue('transactionDate', data.transaction_date || data.instrument_date || data.entry_date || data.created_at);
        safeSetDateValue('entryDate', data.created_at || data.entry_date || data.instrument_date || data.transaction_date);
        safeSetValue('duration', data.duration);
        safeSetDateValue('startDate', data.start_date);
        safeSetDateValue('endDate', data.end_date);
        safeSetValue('annualRent', data.annual_rent);
        safeSetValue('considerationAmount', data.consideration_amount);
        safeSetValue('bankName', data.bank_name);

        if (data.root_reg_number) {
            safeSetValue('rootRegNo', data.root_reg_number);
        }


        // C of O Type Dropdown
        if (data.op_type) {
            safeSetValue('cofoType', data.op_type);
        } else if (data.coo_recertification) {
            safeSetValue('cofoType', 'Recertification');
        } else if (data.coo_conversion) {
            safeSetValue('cofoType', 'Conversion');
        } else {
            // Default fallback if relevant, or leave empty
            // safeSetValue('cofoType', 'Regular'); 
        }

        if (data.op_type) {
            // Keep this for op_type radio buttons if they still exist elsewhere (e.g. Occupancy Permit)
            // But for C of O we use dropdown now.
            const rb = document.querySelector(`input[name="op_type"][value="${data.op_type}"]`);
            if (rb && rb.type === 'radio') rb.checked = true;
        }

        if (data.op_serial_number) {
            safeSetValue('op_serial_number', data.op_serial_number);
        }

        if (data.serial_no || data.page_no || data.volume_no || data.registration_number || data.deeds_serial_no) {
            safeSetValue('serial_no', data.serial_no || data.serialNo);
            safeSetValue('volume_no', data.volume_no || data.volumeNo);
            safeSetValue('registration_number', data.registration_number || data.reg_number || data.deeds_serial_no);

            const pageValue = data.page_no || data.pageNo || data.serial_no || data.serialNo || '';
            safeSetValue('reg_page_no', pageValue);
            if (elements.regPageDisplay) {
                elements.regPageDisplay.value = pageValue;
            }
        }

        safeSetDateValue('registrationDate', data.deeds_date || data.reg_date || data.registrationDate || data.entry_date || data.instrument_date);
        safeSetValue('registrationTime', data.deeds_time || data.reg_time || data.registrationTime);

        // CoO Type Checkboxes
        if (data.coo_recertification) {
            const cb = document.getElementById('coo_recertification');
            if (cb) cb.checked = true;
        }
        if (data.coo_conversion) {
            const cb = document.getElementById('coo_conversion');
            if (cb) cb.checked = true;
        }

        // LPKN No
        if (data.lpkn_no) {
            safeSetValue('lpkn_no', data.lpkn_no);
        }

        // Refresh property description once all fields are set
        updatePropertyDescription();

        updateRegistrationParticulars();

        // Update Submit Button Text
        applySubmitButtonLabel();
    }

    function safeSetValue(id, value) {
        if (value == null) return;
        const el = document.getElementById(id);
        if (el) {
            if (id === 'tp_no' && el.tagName === 'SELECT' && value) {
                const hasOption = Array.from(el.options).some(option => option.value == value);
                if (!hasOption) {
                    el.appendChild(new Option(value, value, true, true));
                }
            }
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            if (window.jQuery && jQuery.fn.select2 && jQuery(el).hasClass('select2-hidden-accessible')) {
                jQuery(el).trigger('change.select2');
            }

            // Handle custom Select components universally
            // 1. Try to get display text from selected option if it's a select
            let displayValue = value;
            if (el.tagName === 'SELECT' && el.options.length > 0) {
                if (el.selectedIndex > -1) {
                    displayValue = el.options[el.selectedIndex].text;
                } else {
                    // Try to match value if selectedIndex didn't update yet (though assign updates it sync)
                    for (let i = 0; i < el.options.length; i++) {
                        if (el.options[i].value == value) { // loose equality for string/num
                            displayValue = el.options[i].text;
                            break;
                        }
                    }
                }
            }

            // 2. Update the custom UI (if it exists)
            updateCustomSelect(id, displayValue);
        }
    }

    function updateCustomSelect(id, value) {
        console.log('updateCustomSelect triggered:', { id, value });
        // Find the custom select wrapper for this input
        const input = document.getElementById(id); // This might be the hidden input or the select
        if (!input) {
            console.warn('updateCustomSelect: Input not found:', id);
            return;
        }

        // Handle Others for District - Move this UP so it runs for standard selects too
        handleManualToggle(id, value);

        // ... rest of the function ...
        const wrapper = input.closest('.relative');
        if (!wrapper) return;

        const btn = wrapper.querySelector('button');
        if (!btn) return;

        const span = btn.querySelector('span');
        if (span) {
            span.textContent = value || 'Select Option';
            span.classList.remove('text-gray-500');
            span.classList.add('text-gray-700');
        }
    }

    /**
     * Write a date value to a native date input, syncing flatpickr when present.
     *
     * The global header enhancer turns every <input type="date"> into a
     * flatpickr with a separate VISIBLE altInput (DD/MM/YYYY). Assigning
     * `el.value` only updates the hidden original, leaving the visible field
     * blank, so route through flatpickr's setDate() when the instance exists.
     * An empty value clears the field.
     */
    function setDateFieldValue(el, value) {
        if (!el) return;
        const ymd = value ? String(value).split('T')[0].split(' ')[0] : '';
        if (el._flatpickr) {
            el._flatpickr.setDate(ymd, false);
        } else {
            el.value = ymd;
        }
    }

    function safeSetDateValue(id, value) {
        if (!value) return;
        const el = document.getElementById(id);
        if (el) {
            setDateFieldValue(el, value);
        }
    }

    /**
     * Re-sync flatpickr-enhanced date inputs after a native form.reset().
     *
     * The global header enhancer turns every <input type="date"> into a flatpickr
     * with a separate VISIBLE altInput (DD/MM/YYYY). form.reset() restores the
     * ORIGINAL (now hidden) input but does NOT notify flatpickr, so the visible
     * altInput is left blank while the underlying value persists. That makes the
     * guarded "if (!input.value)" re-fill skip, and the date silently disappears
     * from the card. Push each current value back through flatpickr to repaint.
     */
    function resyncFlatpickrInputs(root) {
        (root || document).querySelectorAll('input[type="date"], input.flatpickr-input').forEach(function (el) {
            if (el._flatpickr) {
                el._flatpickr.setDate(el.value || '', false);
            }
        });
    }

    // --- Form & UI Logic ---

    async function generateRootParticulars() {
        try {
            const url = elements.generateParticularsUrlInput?.value;
            if (!url) return;
            if (elements.generateRootBtn) {
                elements.generateRootBtn.disabled = true;
                elements.generateRootBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data?.success && data.rootRegistrationNumber && elements.rootRegNoInput) {
                elements.rootRegNoInput.value = data.rootRegistrationNumber;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Data Error',
                    text: data?.message || 'Failed to generate registration particulars.'
                });
            }
        } catch (e) {
            console.error('Error generating particulars', e);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Error generating registration particulars.'
            });
        } finally {
            if (elements.generateRootBtn) {
                elements.generateRootBtn.disabled = false;
                elements.generateRootBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        }
    }

    function updatePartyLabels(instrumentType) {
        const type = instrumentTypes[instrumentType];
        if (!type) return;

        if (elements.firstPartyTitle) elements.firstPartyTitle.textContent = `${type.firstParty} Information`;
        if (elements.firstPartyLabel) elements.firstPartyLabel.textContent = `${type.firstParty} Name`;
        if (elements.secondPartyTitle) elements.secondPartyTitle.textContent = `${type.secondParty} Information`;
        if (elements.secondPartyLabel) elements.secondPartyLabel.textContent = `${type.secondParty} Name`;

        const firstPartyAddressTitle = document.getElementById('first-party-address-title');
        const secondPartyAddressTitle = document.getElementById('second-party-address-title');
        if (firstPartyAddressTitle) firstPartyAddressTitle.textContent = `${type.firstParty} Address`;
        if (secondPartyAddressTitle) secondPartyAddressTitle.textContent = `${type.secondParty} Address`;

        const p1Input = document.getElementById('firstPartyName');
        if (p1Input) p1Input.placeholder = `Enter ${type.firstParty.toLowerCase()}'s full name`;
        const p2Input = document.getElementById('secondPartyName');
        if (p2Input) p2Input.placeholder = `Enter ${type.secondParty.toLowerCase()}'s full name`;
    }

    function showInstrumentForm(instrumentType) {
        const fieldsContainer = elements.instrumentFields;
        fieldsContainer.innerHTML = '';
        const sidebarContainer = elements.instrumentSidebar;
        if (sidebarContainer) sidebarContainer.innerHTML = '';

        const templateId = `template-${instrumentType}`;
        const template = document.getElementById(templateId);

        if (template) {
            const clone = template.content.cloneNode(true);
            fieldsContainer.appendChild(clone);
        } else {
            fieldsContainer.innerHTML = '<div class="text-gray-500 italic p-4 text-center">Form not available for this instrument type.</div>';
        }

        const sidebarTemplateId = `template-${instrumentType}-sidebar`;
        const sidebarTemplate = document.getElementById(sidebarTemplateId);
        if (sidebarContainer && sidebarTemplate) {
            const clone = sidebarTemplate.content.cloneNode(true);
            sidebarContainer.appendChild(clone);
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();

        setTimeout(() => {
            // Setup toggles for extra parties (Co-Mortgagor, Third Party, Fourth Party)
            const extraParties = [
                { chk: 'hasCoMortgagor', cont: 'coMortgagorContainer' },
                { chk: 'hasThirdParty', cont: 'thirdPartyContainer' },
                { chk: 'hasFourthParty', cont: 'fourthPartyContainer' }
            ];

            extraParties.forEach(item => {
                const toggle = document.getElementById(item.chk);
                const container = document.getElementById(item.cont);
                if (toggle && container) {
                    const updateVisibility = () => {
                        if (toggle.checked) {
                            container.classList.remove('hidden');
                        } else {
                            container.classList.add('hidden');
                            // Only clear if not in edit mode or explicitly changed?
                            // Usually better to clear when hiding to avoid accidental submission of hidden data.
                            container.querySelectorAll('input, select, textarea').forEach(input => {
                                input.value = '';
                                // Keep any Select2 dropdown display in sync after clearing
                                if (input.tagName === 'SELECT' && window.jQuery && jQuery.fn.select2
                                    && jQuery(input).hasClass('select2-hidden-accessible')) {
                                    jQuery(input).trigger('change.select2');
                                }
                            });
                        }
                    };

                    toggle.addEventListener('change', updateVisibility);

                    // Prefix for dynamic fetching
                    const prefix = item.chk === 'hasCoMortgagor' ? 'coMortgagor' :
                        (item.chk === 'hasThirdParty' ? 'thirdParty' : 'party5');

                    const stateEl = document.getElementById(`${prefix}State`);
                    const lgaEl = document.getElementById(`${prefix}Lga`);

                    // Attach listener to State dropdown
                    if (stateEl && lgaEl) {
                        stateEl.addEventListener('change', function () {
                            fetchLgas(this.value, `${prefix}Lga`);
                        });
                    }

                    // Initial state check
                    if (toggle.checked) {
                        container.classList.remove('hidden');
                        // Trigger initial fetch if visible and state has value
                        if (stateEl && stateEl.value) {
                            fetchLgas(stateEl.value, `${prefix}Lga`);
                        }
                    } else {
                        container.classList.add('hidden');
                    }
                }
            });

            attachSolicitorListeners();
            refreshSolicitorVisibility(instrumentType);

            // Wire up "Others → specify" toggles for district selects added by the templates
            setupManualSelectListeners();

            // Initialise searchable District dropdowns rendered by the form/sidebar templates
            if (typeof initDistrictSelect2 === 'function') initDistrictSelect2();

            if (instrumentType === 'deed-of-assignment') {
                const sidebarToggle = document.getElementById('includeSolicitorSidebar');
                const mainCheckbox = document.getElementById('includeSolicitor');
                const solicitorContainer = document.getElementById('solicitor-sidebar-container');

                if (sidebarToggle) {
                    sidebarToggle.checked = true;
                    sidebarToggle.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (mainCheckbox) {
                    mainCheckbox.checked = true;
                }

                if (solicitorContainer) {
                    solicitorContainer.classList.remove('hidden');
                }
            }
        }, 0);
    }

    function updateThirdPartyLabels() {
        const header = document.getElementById('thirdPartyHeader');
        const nameLabel = document.getElementById('coMortgagorName-label');
        const addrLabel = document.getElementById('coMortgagorAddress-label');
        const distLabel = document.getElementById('coMortgagorDistrict-label');
        const nameInput = document.getElementById('coMortgagorName');
        const checkboxLabel = document.getElementById('thirdPartyCheckboxLabel');
        const toggle = document.getElementById('hasThirdParty');

        const isTripartiteSubtype = currentInstrumentType === 'tripartite-mortgage';
        const isChecked = toggle ? toggle.checked : false;

        // Logic for Tripartite Mortgage:
        // Unchecked = Co-Mortgagor
        // Checked = Third Party

        // Logic for Deed of Mortgage (Standard):
        // Checked = Co-Mortgagor (since that's the default extra party role)
        // (Note: Deed of Mortgage hides this section if unchecked)

        let useThirdPartyTerm = false;

        if (isTripartiteSubtype) {
            if (isChecked) useThirdPartyTerm = true;
        } else {
            // For standard deed, maybe just stick to Co-Mortgagor?
            // User didn't specify transforming Deed of Mortgage labels, but let's keep Co-Mortgagor standard.
            useThirdPartyTerm = false;
        }

        if (header) {
            header.textContent = useThirdPartyTerm ? 'Third Party Details' : 'Co-Mortgagor Details';
        }

        if (checkboxLabel) {
            if (isTripartiteSubtype) {
                checkboxLabel.textContent = 'Include Third Party (three-party agreement)';
            } else {
                checkboxLabel.textContent = 'Include Co-Mortgagor (three-party agreement)';
            }
        }

        if (nameLabel) {
            nameLabel.textContent = useThirdPartyTerm ? 'Third Party Name' : 'Co-Mortgagor Name';
        }

        if (nameInput) {
            nameInput.placeholder = useThirdPartyTerm ? 'Enter third party name' : 'Enter co-mortgagor full name';
        }

        if (addrLabel) {
            addrLabel.textContent = useThirdPartyTerm ? 'Third Party Address' : 'Co-Mortgagor Address';
        }

        if (distLabel) {
            distLabel.textContent = useThirdPartyTerm ? 'Third Party District' : 'Co-Mortgagor District';
        }
    }

    function normalizeInstrumentTypeKey(inputType) {
        const raw = String(inputType || '').trim();
        if (!raw) return '';

        const lowered = raw.toLowerCase();
        const compact = lowered.replace(/\s+/g, ' ').trim();

        if (compact === 'deed-of-assignment/gift' || compact === 'deed of assignment/gift') {
            return 'deed-of-assignment';
        }
        if (compact === 'assignment/gift' || compact === 'deed of assignment gift') {
            return 'deed-of-assignment';
        }

        return lowered.replace(/\s+/g, '-');
    }

    function normalizeAssignmentGiftBookletCode() {
        const shouldNormalize = currentInstrumentType === 'deed-of-assignment' || currentInstrumentType === 'deed-of-gift';
        if (!shouldNormalize) return;

        const displayFileno = document.getElementById('display_fileno');
        const hiddenFileno = document.getElementById('hidden_fileno');

        const currentValue = (displayFileno?.value || hiddenFileno?.value || '').toString().trim();
        if (!currentValue) return;

        // Business rule: transfer instruments should carry TR instead of legacy 02a booklet suffix.
        const normalizedValue = currentValue.replace(/-02a$/i, '-TR');
        if (normalizedValue === currentValue) return;

        if (displayFileno) {
            displayFileno.value = normalizedValue;
            displayFileno.dispatchEvent(new Event('input', { bubbles: true }));
            displayFileno.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (hiddenFileno) {
            hiddenFileno.value = normalizedValue;
            hiddenFileno.dispatchEvent(new Event('input', { bubbles: true }));
            hiddenFileno.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function openRegistrationDialog(instrumentType) {
        let typeKey = normalizeInstrumentTypeKey(instrumentType) || instrumentType;
        let type = instrumentTypes[typeKey] || instrumentTypes[instrumentType];

        // If not found by key, try search by Name
        if (!type) {
            // e.g. instrumentType = "Deed of Assignment" vs key "deed-of-assignment"
            const foundKey = Object.keys(instrumentTypes).find(k =>
                instrumentTypes[k].name.trim().toLowerCase() === String(instrumentType).trim().toLowerCase()
            );
            if (foundKey) {
                typeKey = foundKey;
                type = instrumentTypes[foundKey];
            }
        }

        if (!type) {
            console.error('Unknown instrument type:', instrumentType);
            return;
        }

        // If the dialog is already visible (user switching instrument types without
        // closing), reset the form so stale data from the previous type is cleared.
        const dialogAlreadyOpen = elements.registrationDialog && !elements.registrationDialog.classList.contains('hidden');
        if (dialogAlreadyOpen) {
            if (elements.registrationForm) {
                elements.registrationForm.reset();
                resyncFlatpickrInputs(elements.registrationForm);
            }
            resetManualSelectFields();
            elements.hiddenMlsFNo.value = '';
            elements.hiddenKangisFileNo.value = '';
            elements.hiddenNewKANGISFileno.value = '';
            elements.hiddenGenericFileno.value = '';
            elements.displayFileno.value = '';
            elements.fileSourceInfo.textContent = 'Please select a file to associate with this instrument.';
            elements.fileSourceInfo.className = 'mt-2 text-sm text-gray-500';
            setPraHistoryAlertVisibility(false);
            duplicateCheckState.existingRecord = null;
            duplicateCheckState.propertyData = null;
            duplicateCheckState.praHistory = [];
            duplicateCheckState.isUpdateMode = false;
            duplicateCheckState.consentApp = null;
            duplicateCheckState.priorInstrument = null;
            alreadyCapturedLock = false;
        }

        currentInstrumentType = typeKey;
        normalizeAssignmentGiftBookletCode();
        resetSubmitButton(); // Ensure button label is correct for the type
        updateOpSubmitAvailability();
        const isEditMode = document.getElementById('instrument_id') && document.getElementById('instrument_id').value !== '';

        if (typeKey === 'occupancy-permit' && window.ossOpContext === true) {
            opLookupMatchedRecord = null;
            alreadyCapturedLock = false;
            setOssOpSubmitMode(false);
            window._opCaptureSubmitted = false;
        }
        if (typeKey === 'occupancy-permit' && isFfrExistingManualRegistrationFlow()) {
            setOssOpSubmitMode(false);
            window._opCaptureSubmitted = false;
        }

        // Keep capture dialog above other stacked overlays (e.g., Commission modal).
        if (elements.registrationDialog) {
            elements.registrationDialog.style.setProperty('z-index', '1000020', 'important');
        }

        // Set official instrument type for submission
        setHidden('instrument_type', type.name);

        // Keep Entry Date synced only when Transaction Date is present.
        const entryDateInput = document.getElementById('entryDate');
        const transactionDateInput = document.getElementById('transactionDate');
        const transactionDateInputWrap = transactionDateInput ? transactionDateInput.closest('.relative.mb-4') : null;
        const transactionDateHeading = transactionDateInputWrap ? transactionDateInputWrap.previousElementSibling : null;

        // Entry Date should be editable on Instrument Capture create page.
        const todayYmd = new Date().toISOString().split('T')[0];
        if (entryDateInput) {
            if (isInstrumentCaptureCreatePage) {
                entryDateInput.readOnly = false;
                entryDateInput.removeAttribute('aria-disabled');
                entryDateInput.removeAttribute('tabindex');
                entryDateInput.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                entryDateInput.classList.add('bg-white');
                if (!entryDateInput.value) {
                    setDateFieldValue(entryDateInput, todayYmd);
                }
            } else {
                entryDateInput.readOnly = true;
                entryDateInput.setAttribute('aria-disabled', 'true');
                entryDateInput.setAttribute('tabindex', '-1');
                entryDateInput.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                entryDateInput.classList.remove('bg-white');
                if (!entryDateInput.value) {
                    setDateFieldValue(entryDateInput, todayYmd);
                }
            }
        }

        // On the Instrument Capture create page, transaction date is hidden and not required.
        if (isInstrumentCaptureCreatePage && transactionDateInput) {
            setDateFieldValue(transactionDateInput, '');
            if (transactionDateInputWrap) transactionDateInputWrap.classList.add('hidden');
            if (transactionDateHeading) transactionDateHeading.classList.add('hidden');
        } else {
            // Clear stale transaction date from previous modal sessions so
            // it does not auto-prefill into entry date.
            if (transactionDateInput) setDateFieldValue(transactionDateInput, '');
            if (transactionDateInputWrap) transactionDateInputWrap.classList.remove('hidden');
            if (transactionDateHeading) transactionDateHeading.classList.remove('hidden');
        }

        // Only sync entry date from transaction date when both already
        // have values (e.g. edit mode). Do NOT overwrite entry date with
        // an empty or stale transaction date on fresh dialog opens.
        if (entryDateInput && transactionDateInput && transactionDateInput.value && !entryDateInput.value) {
            setDateFieldValue(entryDateInput, transactionDateInput.value);
        }

        elements.dialogTitle.textContent = `Capture ${type.name} `;
        updatePartyLabels(typeKey);
        showInstrumentForm(typeKey);

        // Toggle special type containers
        if (elements.opTypeContainer) {
            if (typeKey === 'occupancy-permit') {
                elements.opTypeContainer.classList.remove('hidden');
            } else {
                elements.opTypeContainer.classList.add('hidden');
            }
        }

        if (elements.opRegistrationDetails) {
            if (typeKey === 'occupancy-permit' && (window.ossOpContext === true || isFfrExistingManualRegistrationFlow())) {
                elements.opRegistrationDetails.classList.remove('hidden');
            } else {
                elements.opRegistrationDetails.classList.add('hidden');
            }
        }

        if (elements.cofoTypeContainer) {
            if (typeKey === 'certificate-of-occupancy') {
                elements.cofoTypeContainer.classList.remove('hidden');
            } else {
                elements.cofoTypeContainer.classList.add('hidden');
            }
        }

        // Handle Subtypes
        if (elements.subtypeContainer && elements.subtypeSelect) {
            if (type.subtypes && type.subtypes.length > 0) {
                elements.subtypeContainer.classList.remove('hidden');
                elements.subtypeSelect.innerHTML = '';
                type.subtypes.forEach(stKey => {
                    const st = instrumentTypes[stKey];
                    const option = document.createElement('option');
                    option.value = stKey;
                    option.textContent = st.name;
                    if (stKey === instrumentType) option.selected = true;
                    elements.subtypeSelect.appendChild(option);
                });
            } else {
                elements.subtypeContainer.classList.add('hidden');
            }
        }

        // Display Instrument Info in Modal
        if (elements.instrumentTypeInfo) {
            elements.instrumentTypeInfo.innerHTML = `
            <div class="flex flex-col justify-center h-full">
                <h4 class="text-sm font-bold text-blue-700 leading-tight">${type.name}</h4>
                <p class="text-[11px] text-gray-500 leading-snug mt-1 text-justify">${type.description || ''}</p>
            </div>
        `;
        }

        if (type.autoSetGrantor) {
            const firstPartyNameField = document.getElementById('firstPartyName');
            if (firstPartyNameField && (!isEditMode || !firstPartyNameField.value)) {
                firstPartyNameField.value = 'Kano State Government';
                firstPartyNameField.readOnly = true;
                firstPartyNameField.style.backgroundColor = '#f3f4f6';
                firstPartyNameField.style.cursor = 'not-allowed';
            }
            // Set fixed address...
            let streetAddress = 'Ministry of Land and Physical Planning Kano State';
            if (instrumentType === 'certificate-of-occupancy') {
                streetAddress = 'Ministry of Land and Physical Planning Kano State';
            }

            const addressFields = {
                'firstPartyStreet': streetAddress,
                'firstPartyLga': 'Kano',
                'firstPartyState': 'Kano',
                'firstPartyPostalCode': '700102',
                'firstPartyCountry': 'Nigeria'
            };
            Object.entries(addressFields).forEach(([fieldId, value]) => {
                const field = document.getElementById(fieldId);
                if (field && (!isEditMode || !field.value)) {
                    field.value = value;
                    field.readOnly = true;
                    field.style.backgroundColor = '#f3f4f6';
                }
            });


            // Trigger field hiding logic
            toggleKanoFields('Kano State Government');

            const solicitorSection = document.getElementById('solicitor-section');
            if (solicitorSection) solicitorSection.style.display = 'none';

        } else {
            // Reset solicitor container visibility
            if (elements.solicitorContainer) elements.solicitorContainer.classList.add('hidden');
            if (elements.includeSolicitor) elements.includeSolicitor.checked = false;

            // Only clear party fields when the dialog is being opened fresh (not already visible).
            // Clearing while the dialog is open would wipe values the user has already typed.
            const isDialogFresh = elements.registrationDialog && elements.registrationDialog.classList.contains('hidden');

            const firstPartyNameField = document.getElementById('firstPartyName');
            if (firstPartyNameField && !isEditMode && isDialogFresh) {
                firstPartyNameField.value = '';
                firstPartyNameField.readOnly = false;
                firstPartyNameField.style.backgroundColor = '';
                firstPartyNameField.style.cursor = '';
            } else if (firstPartyNameField) {
                // Always restore editability in case previous type locked it
                firstPartyNameField.readOnly = false;
                firstPartyNameField.style.backgroundColor = '';
                firstPartyNameField.style.cursor = '';
            }

            ['firstPartyStreet', 'firstPartyLga', 'firstPartyState', 'firstPartyPostalCode', 'firstPartyCountry'].forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && !isEditMode && isDialogFresh) {
                    field.value = '';
                    field.readOnly = false;
                    field.style.backgroundColor = '';
                } else if (field && !isEditMode) {
                    field.readOnly = false;
                    field.style.backgroundColor = '';
                }
            });

            // Ensure extra fields are toggled back if name changed
            if (firstPartyNameField) toggleKanoFields(firstPartyNameField.value);

            const solicitorSection = document.getElementById('solicitor-section');
            if (solicitorSection) solicitorSection.style.display = 'block';
        }

        const registrationDetailsSection = document.getElementById('registration-details-section');
        if (type.needsRootReg) {
            if (registrationDetailsSection) registrationDetailsSection.classList.remove('hidden');
            if (elements.rootRegNoInput && !elements.rootRegNoInput.value) {
                generateRootParticulars();
            }
            const filenoLabel = document.getElementById('fileno-label');
            const selectFileBtn = document.getElementById('select-file-btn');
            const displayFileno = document.getElementById('display_fileno');

            if (instrumentType === 'occupancy-permit') {
                // Change label and styling
                if (filenoLabel) {
                    filenoLabel.textContent = 'System FileNo';
                    filenoLabel.classList.remove('text-gray-800');
                    filenoLabel.classList.add('text-red-600');
                }

                // Show Serial Number Input
                if (elements.opSerialNumberContainer) {
                    elements.opSerialNumberContainer.classList.remove('hidden');
                }

                // Hide select button
                if (selectFileBtn) selectFileBtn.classList.add('hidden');

                // Fetch next temp file number
                updateOpSubmitAvailability();
                fetch('/instruments/get-next-temp-fileno')
                    .then(res => res.json())
                    .then(data => {
                        if (data.temp_fileno) {
                            if (displayFileno) {
                                displayFileno.value = data.temp_fileno;
                                // Trigger any change listeners if needed
                                displayFileno.dispatchEvent(new Event('change'));
                            }
                            elements.hiddenGenericFileno.value = data.temp_fileno;
                            // Preserve for Direct OP mode (applyLookupRecordToForm may overwrite display_fileno)
                            window._generatedTempFileno = data.temp_fileno;
                            // For property records logic
                            elements.fileSourceInfo.textContent = 'Automatically generated system file number.';
                            elements.fileSourceInfo.className = "mt-2 text-sm text-red-500 font-medium";
                        }
                        updateOpSubmitAvailability();
                    })
                    .catch(err => {
                        console.error('Error fetching temp fileno:', err);
                        updateOpSubmitAvailability();
                    });

            } else {
                // Reset label and styling
                if (filenoLabel) {
                    filenoLabel.textContent = 'File Number';
                    filenoLabel.classList.remove('text-red-600');
                    filenoLabel.classList.add('text-gray-800');
                }
                // Show select button if not in view mode
                if (selectFileBtn && !isEditMode) selectFileBtn.classList.remove('hidden');

                // Hide Serial Number Input
                if (elements.opSerialNumberContainer) {
                    elements.opSerialNumberContainer.classList.add('hidden');
                    if (elements.opSerialNumberInput && !isEditMode) elements.opSerialNumberInput.value = '';
                }
            }

            if (typeof handleSurveyInfoChange === 'function') handleSurveyInfoChange();
            if (typeof updateLandUseDisplay === 'function') updateLandUseDisplay();
            elements.registrationDialog.classList.remove('hidden');
        } else {
            if (registrationDetailsSection) registrationDetailsSection.classList.add('hidden');
            if (typeof handleSurveyInfoChange === 'function') handleSurveyInfoChange();
            if (typeof updateLandUseDisplay === 'function') updateLandUseDisplay();
            elements.registrationDialog.classList.remove('hidden');
        }

        // OP Type Visibility
        const opContainer = document.getElementById('op-type-container');
        if (opContainer) {
            if (instrumentType === 'occupancy-permit') {
                opContainer.classList.remove('hidden');

                const prefillType = String(window.prefillOpTypeFromCommission || '').toLowerCase();
                const prefillTargetId = prefillType === 'resettlement' ? 'op_type_resettlement' :
                    (prefillType === 'direct' ? 'op_type_direct_allocation' : null);
                if (prefillTargetId) {
                    const prefillTarget = document.getElementById(prefillTargetId);
                    if (prefillTarget) {
                        prefillTarget.checked = true;
                        prefillTarget.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            } else {
                opContainer.classList.add('hidden');
                // Uncheck radios
                opContainer.querySelectorAll('input[type="radio"]').forEach(el => el.checked = false);
            }
        }

        // CoO Type Visibility
        const cooContainer = document.getElementById('coo-type-container');
        if (cooContainer) {
            if (instrumentType === 'certificate-of-occupancy') {
                cooContainer.classList.remove('hidden');
            } else {
                cooContainer.classList.add('hidden');
                // Reset select
                const cofoType = document.getElementById('cofoType');
                if (cofoType) cofoType.value = '';
            }
        }

        // Additional Details section is intentionally hidden for all types.
        const additionalDetailsSection = document.getElementById('additional-details-section');
        if (additionalDetailsSection) {
            additionalDetailsSection.classList.add('hidden');
        }

        // Ensure Grantor fields visibility is correct based on current name
        const p1NameField = document.getElementById('firstPartyName');
        if (p1NameField && typeof toggleKanoFields === 'function') {
            toggleKanoFields(p1NameField.value);
        }
    }

    function refreshInstrumentFileNumberUI(options = {}) {
        const { applyTempFileno = false } = options;
        const filenoLabel = document.getElementById('fileno-label');
        const selectFileBtn = document.getElementById('select-file-btn');
        const displayFileno = document.getElementById('display_fileno');
        const canShowSelect = typeof isEditMode === 'undefined' || !isEditMode;

        if (currentInstrumentType === 'occupancy-permit') {
            if (filenoLabel) {
                filenoLabel.textContent = 'System FileNo';
                filenoLabel.classList.remove('text-gray-800');
                filenoLabel.classList.add('text-red-600');
            }

            if (elements.opSerialNumberContainer) elements.opSerialNumberContainer.classList.remove('hidden');
            if (selectFileBtn) selectFileBtn.classList.add('hidden');

            if (applyTempFileno) {
                updateOpSubmitAvailability();
                fetch('/instruments/get-next-temp-fileno')
                    .then(res => res.json())
                    .then(data => {
                        if (data.temp_fileno) {
                            if (displayFileno) {
                                displayFileno.value = data.temp_fileno;
                                displayFileno.dispatchEvent(new Event('change'));
                            }
                            elements.hiddenGenericFileno.value = data.temp_fileno;
                            // Preserve for Direct OP mode
                            window._generatedTempFileno = data.temp_fileno;
                            elements.fileSourceInfo.textContent = 'Automatically generated system file number.';
                            elements.fileSourceInfo.className = "mt-2 text-sm text-red-500 font-medium";
                        }
                        updateOpSubmitAvailability();
                    })
                    .catch(err => {
                        console.error('Error fetching temp fileno:', err);
                        updateOpSubmitAvailability();
                    });
            }
        } else {
            if (filenoLabel) {
                filenoLabel.textContent = 'File Number';
                filenoLabel.classList.remove('text-red-600');
                filenoLabel.classList.add('text-gray-800');
            }

            if (selectFileBtn && canShowSelect) selectFileBtn.classList.remove('hidden');

            if (elements.opSerialNumberContainer) {
                elements.opSerialNumberContainer.classList.add('hidden');
                if (elements.opSerialNumberInput && canShowSelect) elements.opSerialNumberInput.value = '';
            }
        }

        updateOpSubmitAvailability();
    }

    function resetRegistrationState(options = {}) {
        const {
            hideDialog = false,
            resetInstrumentType = false,
            skipFileNumberRefresh = false,
            preserveGeneratedFileno = '',
            preserveDefaultGrantor = '',
            skipRegDateTimeFill = false
        } = options;

        if (hideDialog && elements.registrationDialog) {
            elements.registrationDialog.classList.add('hidden');
        } else if (elements.registrationDialog) {
            elements.registrationDialog.classList.remove('hidden');
        }

        if (resetInstrumentType) currentInstrumentType = null;
        if (elements.registrationForm) {
            elements.registrationForm.reset();
            resyncFlatpickrInputs(elements.registrationForm);
        }

        resetManualSelectFields();

        // Re-apply autoSetGrantor fields after form reset if instrument type is preserved
        if (!resetInstrumentType && currentInstrumentType) {
            const type = instrumentTypes[currentInstrumentType];
            if (type && type.autoSetGrantor) {
                const firstPartyNameField = document.getElementById('firstPartyName');
                if (firstPartyNameField) {
                    firstPartyNameField.value = 'Kano State Government';
                    firstPartyNameField.readOnly = true;
                    firstPartyNameField.style.backgroundColor = '#f3f4f6';
                    firstPartyNameField.style.cursor = 'not-allowed';
                }
                const addressFields = {
                    'firstPartyStreet': 'Ministry of Land and Physical Planning Kano State',
                    'firstPartyLga': 'Kano',
                    'firstPartyState': 'Kano',
                    'firstPartyPostalCode': '700102',
                    'firstPartyCountry': 'Nigeria'
                };
                Object.entries(addressFields).forEach(([fieldId, value]) => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.value = value;
                        field.readOnly = true;
                        field.style.backgroundColor = '#f3f4f6';
                    }
                });
                toggleKanoFields('Kano State Government');
            }

            if (currentInstrumentType === 'occupancy-permit' && !skipRegDateTimeFill) {
                if (elements.registrationDateInput && !elements.registrationDateInput.value) {
                    elements.registrationDateInput.value = new Date().toISOString().split('T')[0];
                }
                if (elements.registrationTimeInput && !elements.registrationTimeInput.value) {
                    elements.registrationTimeInput.value = getCurrentTimeHHMM();
                }
            }
        }

        const registrationDetailsSection = document.getElementById('registration-details-section');
        if (registrationDetailsSection) registrationDetailsSection.classList.remove('hidden');

        const solicitorSection = document.getElementById('solicitor-section');
        if (solicitorSection) solicitorSection.style.display = 'block';

        if (elements.solicitorContainer) elements.solicitorContainer.classList.add('hidden');
        if (elements.includeSolicitor) elements.includeSolicitor.checked = false;

        // Reset PRA alert
        setPraHistoryAlertVisibility(false);

        if (elements.subtypeContainer) elements.subtypeContainer.classList.add('hidden');
        if (elements.subtypeSelect) elements.subtypeSelect.innerHTML = '';

        elements.hiddenMlsFNo.value = '';
        elements.hiddenKangisFileNo.value = '';
        elements.hiddenNewKANGISFileno.value = '';
        elements.hiddenGenericFileno.value = '';
        elements.displayFileno.value = '';
        elements.fileSourceInfo.textContent = 'Please select a file to associate with this instrument.';
        elements.fileSourceInfo.className = "mt-2 text-sm text-gray-500";

        if (!skipFileNumberRefresh) {
            refreshInstrumentFileNumberUI({ applyTempFileno: !hideDialog });
        }

        if (preserveGeneratedFileno) {
            if (elements.displayFileno) {
                elements.displayFileno.value = preserveGeneratedFileno;
                elements.displayFileno.dispatchEvent(new Event('change'));
            }
            elements.hiddenGenericFileno.value = preserveGeneratedFileno;
            elements.fileSourceInfo.textContent = 'Automatically generated system file number.';
            elements.fileSourceInfo.className = 'mt-2 text-sm text-red-500 font-medium';
        }

        if (preserveDefaultGrantor) {
            const firstPartyNameField = document.getElementById('firstPartyName');
            if (firstPartyNameField) {
                firstPartyNameField.value = preserveDefaultGrantor;
                toggleKanoFields(preserveDefaultGrantor);
            }
        }

        updatePropertyDescription();

        autoFilledSubmitMode = false;
        alreadyCapturedLock = false;
        resetSubmitButton();
    }

    function isAutoGeneratedTempFilenoActive() {
        const displayedFileno = String(elements.displayFileno?.value || '').trim();
        if (!displayedFileno || !/^TEMP-/i.test(displayedFileno)) {
            return false;
        }

        const hasLinkedFile = Boolean(
            String(elements.hiddenMlsFNo?.value || '').trim() ||
            String(elements.hiddenKangisFileNo?.value || '').trim() ||
            String(elements.hiddenNewKANGISFileno?.value || '').trim()
        );

        const sourceInfoText = String(elements.fileSourceInfo?.textContent || '').toLowerCase();
        return sourceInfoText.includes('automatically generated system file number') || !hasLinkedFile;
    }

    function handleManualResetForm() {
        const firstPartyNameField = document.getElementById('firstPartyName');
        const currentGrantorName = String(firstPartyNameField?.value || '').trim();
        const preserveDefaultGrantor = currentGrantorName.toUpperCase() === 'KANO STATE GOVERNMENT' ? currentGrantorName : '';

        const preserveGeneratedFileno = isAutoGeneratedTempFilenoActive()
            ? String(elements.displayFileno?.value || '').trim()
            : '';

        suppressNextDuplicateCheck = true;
        duplicateCheckState.praHistory = [];
        setPraHistoryAlertVisibility(false);
        hidePraHistoryModal();

        // Reset OP lookup state so button reverts to "Capture Existing OP"
        opLookupMatchedRecord = null;
        alreadyCapturedLock = false;
        window.lastCommissionSourceOp = null;
        setOssOpSubmitMode(false);
        clearOpLookupResultsCards();

        resetRegistrationState({
            hideDialog: false,
            resetInstrumentType: false,
            skipFileNumberRefresh: Boolean(preserveGeneratedFileno),
            preserveGeneratedFileno,
            preserveDefaultGrantor,
            skipRegDateTimeFill: true
        });

        // Clear OP Serial Number on reset
        if (elements.opSerialNumberInput) {
            elements.opSerialNumberInput.value = '';
        }

        setOpSerialLookupFeedback('neutral', 'Form reset completed.');
    }

    function handleHardFreshForm() {
        window.location.reload();
    }

    function closeRegistrationDialog() {
        const selectedTypeBeforeClose = String(window.prefillOpTypeFromCommission || '').toLowerCase();
        const wasFromCommission = window.ossOpContext === true;
        const wasFromFfr = window.ffrExistingManualRegistration === true;
        const wasDirectOp = window.ffrDirectOpMode === true;
        const wasCaptureFlow = wasFromCommission || wasFromFfr;
        const wasSubmitted = window._opCaptureSubmitted === true;

        window.prefillOpTypeFromCommission = null;

        // ── Clear all user-entered data before hiding ──
        // Reset OP lookup state
        suppressNextDuplicateCheck = true;
        duplicateCheckState.praHistory = [];
        opLookupMatchedRecord = null;
        alreadyCapturedLock = false;
        window.lastCommissionSourceOp = null;
        setOssOpSubmitMode(false);
        clearOpLookupResultsCards();
        setPraHistoryAlertVisibility(false);
        hidePraHistoryModal();

        // Clear OP Serial Number
        if (elements.opSerialNumberInput) {
            elements.opSerialNumberInput.value = '';
        }
        setOpSerialLookupFeedback('neutral', '');

        resetRegistrationState({ hideDialog: true, resetInstrumentType: true });

        // If OP capture was opened from Commission or FEFR and closed WITHOUT
        // submitting, warn the user and close the parent modals to prevent
        // incomplete transactions (e.g. MLS file numbers without PRA records).
        if (wasCaptureFlow && !wasSubmitted) {
            // Clean up context flags
            window.ossOpContext = false;
            window.requireOpLookupForCommission = false;
            window._opCaptureSubmitted = false;
            window.ffrExistingManualRegistration = false;
            window.ffrDirectOpMode = false;
            window.ffrIndexedFileData = null;
            window._generatedTempFileno = null;

            // Close parent Commission modal
            if (wasFromCommission) {
                if (typeof window.closeCommissionFileNoModal === 'function') {
                    window.closeCommissionFileNoModal();
                } else if (typeof window.closeGenerateModal === 'function') {
                    window.closeGenerateModal();
                }
            }

            // Close parent FEFR modal and Capture Existing File modal
            if (wasFromFfr) {
                var captureModal = document.getElementById('captureModal');
                if (captureModal) captureModal.classList.add('hidden');
                var ffrModal = document.getElementById('ffrModal');
                if (ffrModal) ffrModal.classList.add('hidden');
            }

            Swal.fire({
                icon: 'warning',
                title: 'Capture Cancelled',
                text: 'The Occupancy Permit was not captured. The transaction has been cancelled to avoid incomplete records.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // Reset the flag after use
        window._opCaptureSubmitted = false;

        // When Capture OP is launched from Commission modal, keep OP source/type selected
        // so users do not lose Direct Allocation/Resettlement choice after closing.
        if (wasFromCommission) {
            setTimeout(() => {
                restoreCommissionOpSelection(selectedTypeBeforeClose);
            }, 0);
        }
    }

    function setOpSerialLookupFeedback(kind, message) {
        const container = document.getElementById('op_serial_number_container');
        if (!container) return;

        let feedback = document.getElementById('op-serial-lookup-feedback');
        if (!feedback) {
            feedback = document.createElement('p');
            feedback.id = 'op-serial-lookup-feedback';
            feedback.className = 'mt-2 text-xs font-medium';
            container.appendChild(feedback);
        }

        const classes = {
            neutral: 'text-gray-500',
            success: 'text-emerald-600',
            warning: 'text-amber-600',
            error: 'text-red-600'
        };

        feedback.className = `mt-2 text-xs font-medium ${classes[kind] || classes.neutral}`;
        feedback.innerHTML = message || '';
    }

    function normalizeLookupText(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    async function prefillLandUseAndPurposeFromLookup(landUseName, purposeName) {
        if (!elements.landUseIdSelect || !elements.purposeIdSelect) return;

        const hasLandUseOptions = Array.from(elements.landUseIdSelect.options || []).length > 1;
        if (!hasLandUseOptions) {
            await fetchLandUses();
        }

        if (landUseName) {
            const targetLandUse = normalizeLookupText(landUseName);
            const landUseOption = Array.from(elements.landUseIdSelect.options || []).find((opt) => {
                const optName = normalizeLookupText(opt.dataset?.name || opt.textContent || '');
                return optName === targetLandUse || optName.includes(targetLandUse) || targetLandUse.includes(optName);
            });

            if (landUseOption) {
                elements.landUseIdSelect.value = landUseOption.value;
                if (elements.landUseHidden) {
                    elements.landUseHidden.value = landUseOption.dataset?.name || landUseOption.textContent || '';
                }
                await fetchPurposes(landUseOption.value);
            }
        }

        if (purposeName) {
            const targetPurpose = normalizeLookupText(purposeName);
            const purposeOption = Array.from(elements.purposeIdSelect.options || []).find((opt) => {
                const optName = normalizeLookupText(opt.dataset?.name || opt.textContent || '');
                return optName === targetPurpose || optName.includes(targetPurpose) || targetPurpose.includes(optName);
            });

            if (purposeOption) {
                elements.purposeIdSelect.value = purposeOption.value;
                if (elements.purposeHidden) {
                    elements.purposeHidden.value = purposeOption.dataset?.name || purposeOption.textContent || '';
                }
            }
        }
    }

    function ensureOpLookupResultsContainer() {
        const host = document.getElementById('op_serial_number_container');
        if (!host) return null;

        let container = document.getElementById('op-lookup-results-cards');
        if (!container) {
            container = document.createElement('div');
            container.id = 'op-lookup-results-cards';
            container.className = 'mt-3 space-y-2 hidden';
            host.appendChild(container);

            // Clicking a result card blurs OP SerialNo input; suppress that one lookup rerun
            // so checkbox selection works on the first click.
            container.addEventListener('mousedown', function (event) {
                if (event.target.closest('.op-lookup-card-checkbox') || event.target.closest('label')) {
                    suppressNextOpSerialLookup = true;
                }
            });
        }

        return container;
    }

    function clearOpLookupResultsCards() {
        const container = document.getElementById('op-lookup-results-cards');
        if (!container) return;
        container.innerHTML = '';
        container.classList.add('hidden');
    }

    function resetOpLookupSelectionAndSystemFile(options = {}) {
        const { requestFreshTemp = false, infoMessage = '' } = options;

        opLookupMatchedRecord = null;
        alreadyCapturedLock = false;
        window.lastCommissionSourceOp = null;
        setOssOpSubmitMode(false);
        clearOpLookupResultsCards();

        safeSetValue('hidden_mlsFNo', '');
        safeSetValue('hidden_kangisFileNo', '');
        safeSetValue('hidden_NewKANGISFileno', '');
        safeSetValue('hidden_fileno', '');

        if (requestFreshTemp && currentInstrumentType === 'occupancy-permit') {
            refreshInstrumentFileNumberUI({ applyTempFileno: true });
        }

        if (elements.fileSourceInfo) {
            elements.fileSourceInfo.textContent = infoMessage || 'Automatically generated system file number.';
            elements.fileSourceInfo.className = 'mt-2 text-sm text-red-500 font-medium';
        }
    }

    /**
     * Reassign temp_fileno + prop_id on any sibling source rows that share the
     * same values as the row the user just clicked. Keeps the selected row's
     * values intact and frees the duplicates for clean source-of-truth lookups.
     * Mutates `selectedRow` in place if any reassignment happened so downstream
     * applyLookupRecordToForm uses the still-valid values.
     */
    async function maybeResolveOpDuplicates(selectedRow) {
        if (!selectedRow || !selectedRow.id) return;
        const rawTable = String(selectedRow.source_table || 'instrument_capture').toLowerCase();
        // Resolver only operates on real source rows (IC / DR), not PRA timeline rows.
        if (rawTable !== 'instrument_capture' && rawTable !== 'deed_registrations') return;

        const tempVal = String(selectedRow.temp_fileno || selectedRow.fileno || '').trim();
        if (!/^TEMP-/i.test(tempVal)) return;
        if (!selectedRow.prop_id) return;

        try {
            const tokenEl = document.querySelector('meta[name="csrf-token"]');
            const csrf = tokenEl ? tokenEl.getAttribute('content') : '';
            const response = await fetch('/instruments/resolve-op-duplicates', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    selected_source_op_id: selectedRow.id,
                    selected_source_op_table: rawTable
                })
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (!payload || !payload.success) return;
            const reassigned = Array.isArray(payload.reassigned) ? payload.reassigned : [];
            if (reassigned.length === 0) return;

            console.info('[OP duplicate resolver] reassigned siblings:', reassigned);
            if (typeof toastr !== 'undefined' && toastr.info) {
                toastr.info(
                    `Reassigned ${reassigned.length} duplicate OP record${reassigned.length === 1 ? '' : 's'} ` +
                    `to fresh temp file numbers and prop_ids.`,
                    'Duplicate Cleanup'
                );
            }
        } catch (err) {
            console.warn('[OP duplicate resolver] request failed', err);
        }
    }

    async function applyLookupRecordToForm(data) {
        if (!data) return;

        opLookupMatchedRecord = data;
        setOssOpSubmitMode(true);

        if (data.party_1_name) safeSetValue('firstPartyName', data.party_1_name);
        if (data.party_2_name) safeSetValue('secondPartyName', data.party_2_name);
        if (data.plot_number) safeSetValue('plotNumber', data.plot_number);
        if (data.tp_no) safeSetValue('tp_no', data.tp_no);
        if (data.survey_plan_no) safeSetValue('surveyPlanNo', data.survey_plan_no);

        // Property description - set with suppression so dropdowns don't overwrite it
        const propDesc = data.property_description || data.property_location || '';
        if (propDesc) {
            window._suppressPropertyDescUpdate = true;
            safeSetValue('propertyDescription', propDesc);
        }

        // Property dropdowns - use setSelectOrManual for fuzzy matching + Others fallback
        const district = data.district || extractDistrictFromText(propDesc, null, null);
        const lga = data.lga || extractLgaFromText(propDesc, null);
        const streetName = data.street_name || null;

        if (district) setSelectOrManual('desc_district', district);
        if (lga) setSelectOrManual('desc_lga', lga);
        if (streetName) setSelectOrManual('streetName', streetName);

        // Party 1 address fields
        if (data.party_1_address) setSelectOrManual('firstPartyStreet', data.party_1_address);
        if (data.party_1_district) {
            setSelectOrManual('firstPartyDistrict', data.party_1_district);
        } else if (district) {
            setSelectOrManual('firstPartyDistrict', district);
        }
        const p1State = data.party_1_state || 'Kano';
        safeSetValue('firstPartyState', p1State);
        if (data.party_1_lga) {
            fetchLgas(p1State, 'firstPartyLga', data.party_1_lga);
        } else if (lga) {
            fetchLgas(p1State, 'firstPartyLga', lga);
        }

        // Party 2 address fields
        if (data.party_2_address) setSelectOrManual('secondPartyStreet', data.party_2_address);
        if (data.party_2_district) {
            setSelectOrManual('secondPartyDistrict', data.party_2_district);
        } else if (district) {
            setSelectOrManual('secondPartyDistrict', district);
        }
        const p2State = data.party_2_state || 'Kano';
        safeSetValue('secondPartyState', p2State);
        if (data.party_2_lga) {
            fetchLgas(p2State, 'secondPartyLga', data.party_2_lga);
        } else if (lga) {
            fetchLgas(p2State, 'secondPartyLga', lga);
        }

        window._suppressPropertyDescUpdate = false;

        if (data.land_use && elements.landUseHidden) {
            elements.landUseHidden.value = data.land_use;
        }
        if (data.purpose && elements.purposeHidden) {
            elements.purposeHidden.value = data.purpose;
        }
        await prefillLandUseAndPurposeFromLookup(data.land_use, data.purpose);

        const lookupFileNo = data.temp_fileno || data.fileno || data.mlsFNo || data.kangisFileNo || data.NewKANGISFileno;
        if (lookupFileNo) {
            safeSetValue('display_fileno', lookupFileNo);
            safeSetValue('hidden_fileno', data.temp_fileno || data.fileno || '');
            safeSetValue('hidden_mlsFNo', data.mlsFNo || '');
            safeSetValue('hidden_kangisFileNo', data.kangisFileNo || '');
            safeSetValue('hidden_NewKANGISFileno', data.NewKANGISFileno || '');

            if (elements.fileSourceInfo) {
                const sourceInfo = String(data.source_table || 'instrument_capture').toLowerCase() === 'pra'
                    ? 'Loaded from existing PRA record.'
                    : 'Loaded from existing OP capture record.';
                elements.fileSourceInfo.textContent = sourceInfo;
                elements.fileSourceInfo.className = 'mt-2 text-sm text-emerald-600 font-medium';
            }
        }

        const serialNo = data.serial_no || data.serialNo || '';
        const pageNo = data.page_no || data.pageNo || serialNo || '';
        const volumeNo = data.volume_no || data.volumeNo || '';
        const regNo = data.registration_number || data.reg_number || data.deeds_serial_no || '';

        if (serialNo || pageNo || volumeNo || regNo) {
            safeSetValue('serial_no', serialNo);
            safeSetValue('reg_page_no', pageNo);
            safeSetValue('volume_no', volumeNo);
            safeSetValue('registration_number', regNo);
            if (elements.regPageDisplay) {
                elements.regPageDisplay.value = pageNo;
            }
            updateRegistrationParticulars();
        }

        safeSetDateValue('registrationDate', data.deeds_date || data.reg_date || data.registrationDate);
        safeSetValue('registrationTime', data.deeds_time || data.reg_time || data.registrationTime);

        // Backfill Transaction Date and Entry Date from the lookup record
        // For PRA records: use created_at for Entry Date as instructed.
        var isPraSource = String(data.source_table || '').toLowerCase() === 'pra';
        safeSetDateValue('transactionDate', data.transaction_date || data.instrument_date || data.entry_date || data.created_at);
        if (isPraSource) {
            safeSetDateValue('entryDate', data.created_at || data.entry_date || data.instrument_date || data.transaction_date);
        } else {
            safeSetDateValue('entryDate', data.entry_date || data.instrument_date || data.transaction_date || data.created_at);
        }

        if (!window.prefillOpTypeFromCommission && data.op_type) {
            const normalizedType = String(data.op_type).toLowerCase();
            const targetId = normalizedType.includes('resettlement')
                ? 'op_type_resettlement'
                : (normalizedType.includes('direct') ? 'op_type_direct_allocation' : null);
            if (targetId) {
                const target = document.getElementById(targetId);
                if (target) target.checked = true;
            }
        }

        // Only auto-generate property description if none was loaded from the record
        if (!data.property_description && !data.property_location) {
            updatePropertyDescription();
        }
        const sourceName = String(data.source_table || 'instrument_capture').toLowerCase() === 'pra'
            ? 'PRA'
            : 'instrument_capture';
        setAutoFilledSubmitMode(true);
        setOpSerialLookupFeedback('success', `Record selected (ID: ${data.id}). Details fetched from ${sourceName}.`);

        // If the record already exists in instrument_capture OR PRA, disable submit
        // to prevent duplicate capture on the standalone /instruments/create page.
        // OSS OP and other commission flows need the button enabled to copy data to PRA.
        if (isInstrumentCaptureCreatePage) {
            alreadyCapturedLock = true;
            if (elements.submitBtn) {
                elements.submitBtn.disabled = true;
                elements.submitBtn.className = 'px-6 py-2.5 text-white font-medium bg-gray-400 rounded-lg cursor-not-allowed opacity-60 shadow-md flex items-center gap-2';
                elements.submitBtn.setAttribute('title', 'This instrument has already been captured and registered. Duplicate submission is not allowed.');
            }
        }
    }

    function renderOpLookupResultsCards(records) {
        const container = ensureOpLookupResultsContainer();
        if (!container) return;

        container.innerHTML = '';
        container.classList.remove('hidden');

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[c]));

        // Count per source
        const sourceCounts = {};
        records.forEach(r => {
            const src = String(r.source_table || 'instrument_capture').toLowerCase() === 'pra' ? 'PRA' : 'Deeds Registration';
            sourceCounts[src] = (sourceCounts[src] || 0) + 1;
        });

        const sourceColors = {
            'Deeds Registration': { bg: '#dbeafe', text: '#1d4ed8', border: '#93c5fd' },
            'PRA': { bg: '#d1fae5', text: '#047857', border: '#6ee7b7' }
        };

        // Build source badges
        let badgesHtml = '';
        Object.entries(sourceCounts).forEach(([src, count]) => {
            const c = sourceColors[src] || { bg: '#f3f4f6', text: '#374151', border: '#d1d5db' };
            badgesHtml += `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700;background:${c.bg};color:${c.text};border:1px solid ${c.border};">${src} <span style="background:rgba(255,255,255,0.7);padding:1px 5px;border-radius:9999px;">${count}</span></span> `;
        });

        // Build summary bar with View button
        const summaryDiv = document.createElement('div');
        summaryDiv.className = 'flex flex-wrap items-center gap-2 p-3 rounded-lg border border-blue-200 bg-blue-50/60';
        summaryDiv.innerHTML = `
            <span style="font-size:12px;font-weight:700;color:#1e40af;">${records.length} Result${records.length !== 1 ? 's' : ''} Found</span>
            <span style="display:inline-flex;gap:4px;flex-wrap:wrap;">${badgesHtml}</span>
            <button type="button" id="opLookupViewBtn" style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:8px;font-size:12px;font-weight:700;color:#ffffff;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.15);transition:all 0.15s;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Results
            </button>
        `;
        container.appendChild(summaryDiv);

        // Store records for the modal
        const _storedRecords = records;

        // View button opens SweetAlert modal with result cards
        const viewBtn = summaryDiv.querySelector('#opLookupViewBtn');
        if (viewBtn) {
            viewBtn.addEventListener('mousedown', function () {
                suppressNextOpSerialLookup = true;
            });
            viewBtn.addEventListener('click', function () {
                _openOpLookupResultsModal(_storedRecords, esc);
            });
        }
    }

    function _openOpLookupResultsModal(records, esc) {
        const availableRecords = records.filter((row) => !row.already_used);
        const linkedRecords = records.filter((row) => !!row.already_used);

        const classifySource = (row) => String(row.source_table || 'instrument_capture').toLowerCase() === 'pra' ? 'pra' : 'deeds';

        const buildCardHtml = (row, idx) => {
            const rowSource = classifySource(row);
            const sourceName = rowSource === 'pra' ? 'PRA' : 'Deeds Registration';
            const style = cardStyles[idx % cardStyles.length];
            const isUsed = !!row.already_used;

            const cardBorder = isUsed ? '#d1d5db' : style.border;
            const cardBg = isUsed ? '#f9fafb' : style.bg;
            const cardCursor = isUsed ? 'not-allowed' : 'pointer';
            const cardOpacity = isUsed ? '0.72' : '1';

            return `
                <div class="op-modal-result-card" data-idx="${idx}" data-used="${isUsed ? '1' : '0'}"
                     style="border:1px solid ${cardBorder};background:${cardBg};border-radius:10px;padding:10px;cursor:${cardCursor};transition:all 0.15s;position:relative;opacity:${cardOpacity};">
                    ${isUsed ? `<div style="position:absolute;top:8px;right:8px;display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:10px;font-weight:700;letter-spacing:0.3px;">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        Linked to ${esc(row.linked_fileno || row.fileno || row.temp_fileno || 'a file')}
                    </div>` : (row.is_subdivided ? `<div style="position:absolute;top:8px;right:8px;display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-size:10px;font-weight:700;letter-spacing:0.3px;">
                        &#x2702; Subdivision &mdash; ${row.transfer_count || 0}/${row.op_count || '?'} used
                    </div>` : '')}
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:6px;background:${isUsed ? '#9ca3af' : style.accent};color:#fff;font-weight:800;font-size:10px;">${idx + 1}</span>
                        <span style="font-size:11px;font-weight:700;color:${isUsed ? '#9ca3af' : style.accent};text-transform:uppercase;letter-spacing:0.5px;">${esc(sourceName)}</span>
                        <span style="margin-left:auto;font-size:10px;color:#64748b;">${esc(row.instrument_type || '')}</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px 10px;font-size:11px;line-height:1.25;">
                        <div><span style="color:#94a3b8;font-size:10px;">Allottee</span><br><strong style="color:#1e293b;">${esc(row.party_2_name)}</strong></div>
                        <div><span style="color:#94a3b8;font-size:10px;">OP Serial No</span><br><strong style="color:#1e293b;">${esc(row.op_serial_number)}</strong></div>
                        <div><span style="color:#94a3b8;font-size:10px;">Reg No</span><br><strong style="color:#1e293b;">${esc(row.registration_number || row.deeds_serial_no)}</strong></div>
                        <div><span style="color:#94a3b8;font-size:10px;">Property</span><br><strong style="color:#1e293b;">${esc(row.property_description || row.property_location)}</strong></div>
                        <div><span style="color:#94a3b8;font-size:10px;">Land Use</span><br><strong style="color:#1e293b;">${esc(row.land_use)}</strong></div>
                        <div><span style="color:#94a3b8;font-size:10px;">OP Type</span><br><strong style="color:#1e293b;">${esc(row.op_type)}</strong></div>
                        <div><span style="color:#94a3b8;font-size:10px;">Plot No</span><br><strong style="color:#1e293b;">${esc(row.plot_number)}</strong></div>
                        <div><span style="color:#94a3b8;font-size:10px;">TP No</span><br><strong style="color:#1e293b;">${esc(row.tp_no)}</strong></div>
                    </div>
                </div>
            `;
        };

        const buildSubColumnHtml = (title, palette, subRecords, globalIndexes, emptyText) => {
            const cardsHtml = subRecords.length
                ? subRecords.map((row, i) => buildCardHtml(row, globalIndexes[i])).join('')
                : `<div style="border:1px dashed #d1d5db;background:#fff;border-radius:10px;padding:14px;text-align:center;font-size:11px;color:#94a3b8;">No records.</div>`;
            return `
                <div style="min-width:0;display:flex;flex-direction:column;gap:6px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;background:${palette.bg};color:${palette.text};border:1px solid ${palette.border};">${title}</span>
                        <span style="font-size:10px;font-weight:700;color:#64748b;">(${subRecords.length})</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;max-height:36vh;overflow-y:auto;padding-right:2px;">${cardsHtml}</div>
                </div>
            `;
        };

        const buildSectionHtml = (sectionTitle, tone, sectionRecords, globalStartIndex) => {
            const tones = {
                available: { label: '#1d4ed8', labelBg: '#eff6ff', labelBorder: '#bfdbfe', sectionBorder: '#bfdbfe', sectionBg: '#fafbff' },
                linked: { label: '#dc2626', labelBg: '#fef2f2', labelBorder: '#fecaca', sectionBorder: '#fecaca', sectionBg: '#fffbfb' }
            };
            const pal = tones[tone] || tones.available;

            const deedsRecords = [], deedsIndexes = [];
            const praRecords = [], praIndexes = [];
            sectionRecords.forEach((row, i) => {
                const gIdx = globalStartIndex + i;
                if (classifySource(row) === 'pra') { praRecords.push(row); praIndexes.push(gIdx); }
                else { deedsRecords.push(row); deedsIndexes.push(gIdx); }
            });

            const deedsPalette = { bg: '#dbeafe', text: '#1d4ed8', border: '#93c5fd' };
            const praPalette = { bg: '#d1fae5', text: '#047857', border: '#6ee7b7' };

            return `
                <div style="border:1px solid ${pal.sectionBorder};background:${pal.sectionBg};border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:8px;">
                    <div style="display:flex;align-items:center;gap:8px;border-bottom:1px solid ${pal.sectionBorder};padding-bottom:6px;">
                        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;font-size:11px;font-weight:800;background:${pal.labelBg};color:${pal.label};border:1px solid ${pal.labelBorder};">${sectionTitle}</span>
                    </div>
                    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px;align-items:start;">
                        ${buildSubColumnHtml('Deeds Registration', deedsPalette, deedsRecords, deedsIndexes, 'No Deeds Registration records.')}
                        ${buildSubColumnHtml('PRA', praPalette, praRecords, praIndexes, 'No PRA records.')}
                    </div>
                </div>
            `;
        };

        const cardStyles = [
            { border: '#bae6fd', bg: '#f0f9ff', accent: '#0369a1' },
            { border: '#a7f3d0', bg: '#ecfdf5', accent: '#047857' },
            { border: '#c4b5fd', bg: '#f5f3ff', accent: '#6d28d9' },
            { border: '#fcd34d', bg: '#fffbeb', accent: '#b45309' },
            { border: '#fda4af', bg: '#fff1f2', accent: '#be123c' },
        ];

        const modalHtml = `
            <div style="text-align:left;">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:8px;">
                    <span style="font-size:13px;font-weight:700;color:#1e293b;">${records.length} Result${records.length !== 1 ? 's' : ''}</span>
                </div>
                <p style="font-size:12px;color:#64748b;margin-bottom:10px;">Select from the unlinked pane for commissioning. Linked records stay visible for reference.</p>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    ${buildSectionHtml('Unlinked OPs', 'available', availableRecords, 0)}
                    ${buildSectionHtml('Linked OPs', 'linked', linkedRecords, availableRecords.length)}
                </div>
            </div>
        `;

        Swal.fire({
            title: 'OP Lookup Results',
            html: modalHtml,
            width: 900,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Close',
            cancelButtonColor: '#6b7280',
            didOpen: () => {
                const popup = Swal.getPopup();
                if (!popup) return;
                const cards = popup.querySelectorAll('.op-modal-result-card');
                cards.forEach((card) => {
                    const isUsed = card.getAttribute('data-used') === '1';

                    if (isUsed) {
                        // No hover effects or click for used/transferred cards
                        return;
                    }

                    card.addEventListener('mouseenter', function () {
                        card.style.boxShadow = '0 2px 8px rgba(0,0,0,0.12)';
                        card.style.transform = 'translateY(-1px)';
                    });
                    card.addEventListener('mouseleave', function () {
                        card.style.boxShadow = 'none';
                        card.style.transform = 'none';
                    });
                    card.addEventListener('click', async function () {
                        const idx = parseInt(card.getAttribute('data-idx'), 10);
                        const selectedRow = records[idx];
                        if (!selectedRow) return;

                        // Confirm selection
                        const confirmResult = await Swal.fire({
                            icon: 'warning',
                            title: 'Confirm Selection',
                            html: `<p class="text-sm text-gray-700 mb-2">OP Serial Numbers may not be unique. Please verify this is the correct record:</p>
                                <div class="text-left text-sm bg-gray-50 rounded p-3 space-y-1">
                                    <p><strong class="text-red-600">Allottee:</strong> ${esc(selectedRow?.party_2_name)}</p>
                                    <p><strong class="text-red-600">OP Serial:</strong> ${esc(selectedRow?.op_serial_number)}</p>
                                    <p><strong class="text-red-600">Reg No:</strong> ${esc(selectedRow?.registration_number || selectedRow?.deeds_serial_no)}</p>
                                    <p><strong class="text-red-600">Property:</strong> ${esc(selectedRow?.property_description || selectedRow?.property_location)}</p>
                                </div>`,
                            showCancelButton: true,
                            confirmButtonText: 'Yes, this is correct',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#2563eb',
                            cancelButtonColor: '#6b7280'
                        });

                        if (!confirmResult.isConfirmed) return;

                        await maybeResolveOpDuplicates(selectedRow);
                        await applyLookupRecordToForm(selectedRow);
                        // Show the selected record inline in the results container
                        _showSelectedRecordInline(selectedRow, esc);
                    });
                });
            }
        });
    }

    function _showSelectedRecordInline(row, esc) {
        const container = document.getElementById('op-lookup-results-cards');
        if (!container) return;

        const rowSource = String(row.source_table || 'instrument_capture').toLowerCase();
        const sourceName = rowSource === 'pra' ? 'PRA' : 'Deeds Registration';
        const sourceColor = rowSource === 'pra'
            ? { bg: '#d1fae5', text: '#047857', border: '#6ee7b7', accent: '#047857', cardBg: '#ecfdf5', cardBorder: '#a7f3d0' }
            : { bg: '#dbeafe', text: '#1d4ed8', border: '#93c5fd', accent: '#1d4ed8', cardBg: '#f0f9ff', cardBorder: '#bae6fd' };

        container.innerHTML = `
            <div style="border:2px solid ${sourceColor.cardBorder};background:${sourceColor.cardBg};border-radius:10px;padding:12px;position:relative;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;padding:3px 10px;border-radius:9999px;font-size:10px;font-weight:800;background:#dcfce7;color:#15803d;border:1px solid #86efac;">SELECTED</span>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700;background:${sourceColor.bg};color:${sourceColor.text};border:1px solid ${sourceColor.border};">${esc(sourceName)}</span>
                    <span style="margin-left:auto;font-size:10px;color:#64748b;">${esc(row.instrument_type || '')}</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 12px;font-size:11px;">
                    <div><span style="color:#94a3b8;font-size:10px;">Allottee</span><br><strong style="color:#1e293b;">${esc(row.party_2_name)}</strong></div>
                    <div><span style="color:#94a3b8;font-size:10px;">OP Serial No</span><br><strong style="color:#1e293b;">${esc(row.op_serial_number)}</strong></div>
                    <div><span style="color:#94a3b8;font-size:10px;">Reg No</span><br><strong style="color:#1e293b;">${esc(row.registration_number || row.deeds_serial_no)}</strong></div>
                    <div><span style="color:#94a3b8;font-size:10px;">Property</span><br><strong style="color:#1e293b;">${esc(row.property_description || row.property_location)}</strong></div>
                    <div><span style="color:#94a3b8;font-size:10px;">Land Use</span><br><strong style="color:#1e293b;">${esc(row.land_use)}</strong></div>
                    <div><span style="color:#94a3b8;font-size:10px;">OP Type</span><br><strong style="color:#1e293b;">${esc(row.op_type)}</strong></div>
                    <div><span style="color:#94a3b8;font-size:10px;">Plot No</span><br><strong style="color:#1e293b;">${esc(row.plot_number)}</strong></div>
                    <div><span style="color:#94a3b8;font-size:10px;">TP No</span><br><strong style="color:#1e293b;">${esc(row.tp_no)}</strong></div>
                </div>
            </div>
        `;
        container.classList.remove('hidden');
    }

    async function lookupOpSerialNumberAndAutofill() {
        // OP serial-only lookup is disabled everywhere except the MLS File Number
        // Generator page: serial numbers alone are not unique enough to disambiguate
        // candidate OPs, and auto-selection caused wrong OP→TOT lineage (source_op_id
        // pointing at the wrong OP). Elsewhere every OP is captured fresh; the TOT
        // inherits the just-captured OP's prop_id + temp_fileno through the normal
        // commissioning flow, and disambiguation happens via checkForExistingOpByFields
        // once Reg Particulars (serial+page+vol) are filled — multi-field match,
        // user-confirmed. The Generator page still wants serial-only search-and-select
        // (a specific record picked from a results list), so it keeps the old behavior.
        if (currentInstrumentType !== 'occupancy-permit') return;

        if (suppressNextOpSerialLookup) {
            setTimeout(() => { suppressNextOpSerialLookup = false; }, 100);
            return;
        }

        if (!isMlsFileNumberGeneratorPage) {
            resetOpLookupSelectionAndSystemFile({ requestFreshTemp: true });
            setOpSerialLookupFeedback('neutral', '');
            return;
        }

        try {
            const serial = (elements.opSerialNumberInput?.value || '').trim().toUpperCase();
            if (!serial) {
                resetOpLookupSelectionAndSystemFile({ requestFreshTemp: true });
                setOpSerialLookupFeedback('neutral', '');
                return;
            }

            setOpSerialLookupFeedback('neutral', `Checking OP SerialNo ${serial}...`);

            const url = `/api/instruments/op-serial-lookup?op_serial_number=${encodeURIComponent(serial)}`;
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const payload = await response.json();

            if (!response.ok || !payload?.success) {
                resetOpLookupSelectionAndSystemFile({ requestFreshTemp: true });
                setOpSerialLookupFeedback('error', 'Lookup failed. Please try again.');
                return;
            }

            if (!payload?.found || !payload?.data) {
                resetOpLookupSelectionAndSystemFile({
                    requestFreshTemp: true,
                    infoMessage: 'No OP serial match found. Fresh system file number generated.'
                });
                setOpSerialLookupFeedback('success', `OP Serial No <strong>${serial}</strong> is available — no existing records found.`);
                return;
            }

            const matches = Array.isArray(payload.records) && payload.records.length > 0
                ? payload.records
                : [payload.data];

            // Always show result cards - user must click to select
            resetOpLookupSelectionAndSystemFile({ requestFreshTemp: true });
            renderOpLookupResultsCards(matches);
        } catch (error) {
            console.error('OP Serial lookup failed:', error);
            resetOpLookupSelectionAndSystemFile({ requestFreshTemp: true });
            setOpSerialLookupFeedback('error', 'Lookup failed due to network/server error.');
        }
    }

    // Tracks the set of supplied values the user has already dismissed via
    // "No, create new" so we don't re-prompt for the same input. Cleared when
    // the user picks a candidate or resets the form.
    let _opCandidateDismissedKey = null;
    let _opCandidateCheckTimer = null;
    let _opCandidateCheckInFlight = false;

    function _buildOpCandidateKey(fields) {
        return [
            fields.op_serial_number, fields.serial_no, fields.page_no,
            fields.volume_no, fields.party_2_name
        ].map(v => String(v || '').trim().toUpperCase()).join('|');
    }

    function _readOpCandidateFields() {
        const get = (id) => (document.getElementById(id)?.value || '').toString().trim();
        return {
            op_serial_number: get('op_serial_number'),
            serial_no:        get('serial_no'),
            page_no:          get('reg_page_no') || get('reg_page_no_display'),
            volume_no:        get('volume_no'),
            party_2_name:     get('secondPartyName'),
        };
    }

    async function checkForExistingOpByFields() {
        if (currentInstrumentType !== 'occupancy-permit') return;
        if (opLookupMatchedRecord) return;
        if (_opCandidateCheckInFlight) return;

        const fields = _readOpCandidateFields();

        // Gate: only fire when ALL 5 disambiguation fields are filled.
        // Exact-match on the full set is what gives a single unambiguous hit.
        if (!fields.op_serial_number || !fields.serial_no || !fields.page_no
            || !fields.volume_no || !fields.party_2_name) return;

        const key = _buildOpCandidateKey(fields);
        if (key === _opCandidateDismissedKey) return;

        const params = new URLSearchParams();
        Object.entries(fields).forEach(([k, v]) => params.append(k, v));

        _opCandidateCheckInFlight = true;
        try {
            const resp = await fetch(`/api/instruments/op-candidates-check?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            const payload = await resp.json();
            if (!resp.ok || !payload?.success) return;

            if (!payload.count || !Array.isArray(payload.candidates) || payload.candidates.length === 0) {
                // No existing OP matches these 5 fields exactly — confirm to the
                // user that a fresh OP will be created. One-shot per field combo
                // (dismissal key suppresses re-prompts while still editing).
                await Swal.fire({
                    icon: 'info',
                    title: 'OP Does Not Exist',
                    html: `<p style="font-size:13px;color:#475569;">The system will create a <strong>new OP</strong> based on the details you provide when you submit.</p>`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#2563eb',
                });
                _opCandidateDismissedKey = key;
                return;
            }

            // Exact 5-field match should yield 1 result; if multiple, take the
            // top-ranked one (prop_id-bearing, most recent — already sorted by
            // the service).
            await showOpCandidateConfirmation(payload.candidates[0], key);
        } catch (err) {
            console.warn('OP candidate check failed:', err);
        } finally {
            _opCandidateCheckInFlight = false;
        }
    }

    function _scheduleOpCandidateCheck() {
        clearTimeout(_opCandidateCheckTimer);
        _opCandidateCheckTimer = setTimeout(checkForExistingOpByFields, 600);
    }

    async function showOpCandidateConfirmation(candidate, dismissalKey) {
        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));

        const sourceLabel = candidate.source_table === 'pra' ? 'PRA'
            : candidate.source_table === 'deed_registrations' ? 'Deeds Reg'
            : 'Instr. Capture';
        const regParts = [candidate.serial_no, candidate.page_no, candidate.volume_no].filter(Boolean).join('/');

        const result = await Swal.fire({
            icon: 'question',
            title: 'Existing OP Found',
            html: `
                <p style="font-size:13px;color:#475569;margin-bottom:12px;">
                    An OP in the system exactly matches the OP Serial, Reg Particulars, and Allottee you entered. Is this the same OP?
                </p>
                <div style="border:1px solid #cbd5e1;border-radius:8px;padding:12px;background:#f8fafc;text-align:left;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;background:#dbeafe;color:#1d4ed8;">${sourceLabel}</span>
                        ${candidate.prop_id ? `<span style="font-size:10px;color:#64748b;margin-left:auto;">prop_id ${esc(candidate.prop_id)}</span>` : ''}
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 12px;font-size:12px;">
                        <div><span style="color:#94a3b8;">Allottee:</span><br><strong>${esc(candidate.party_2_name || candidate.party_2 || '—')}</strong></div>
                        <div><span style="color:#94a3b8;">OP Serial:</span><br><strong>${esc(candidate.op_serial_number || '—')}</strong></div>
                        <div><span style="color:#94a3b8;">Reg Particulars:</span><br><strong>${esc(regParts || candidate.registration_number || '—')}</strong></div>
                        <div><span style="color:#94a3b8;">Plot:</span><br><strong>${esc(candidate.plot_number || candidate.plot_no || '—')}</strong></div>
                        <div style="grid-column:1/-1;"><span style="color:#94a3b8;">Property:</span><br><strong>${esc(candidate.property_description || candidate.property_location || '—')}</strong></div>
                    </div>
                </div>
            `,
            width: 560,
            showCancelButton: true,
            confirmButtonText: 'Yes, use this OP',
            cancelButtonText: 'No, create new',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
        });

        if (result.isConfirmed) {
            await applyLookupRecordToForm(candidate);
            // Switch submit button to indicate link-to-existing mode. buildPraPayload
            // will pass link_existing_op:true so backend links instead of creating dup.
            window.ossOpSubmitLabel = 'Continue with Existing OP';
            applySubmitButtonLabel();
        } else {
            // "No, create new" → remember dismissal so we don't re-prompt
            // for this exact combination unless the user changes something.
            _opCandidateDismissedKey = dismissalKey;
        }
    }



    function handleSurveyInfoChange() {
        const surveyCheckbox = document.getElementById('surveyInfo');
        const surveySection = document.getElementById('survey-info-section');
        if (surveyCheckbox && surveySection) {
            if (surveyCheckbox.checked) {
                surveySection.classList.remove('hidden');
            } else {
                surveySection.classList.add('hidden');
            }
        }
        updatePropertyDescription();
    }

    function updatePropertyDescription() {
        // Skip auto-update while consent auto-fill is populating dropdowns
        if (window._suppressPropertyDescUpdate) return;

        const house = document.getElementById('house_no')?.value?.trim() || '';
        const plot = document.getElementById('plotNumber')?.value?.trim() || '';
        const district = getSelectOrManualValue('desc_district', 'manual_district');
        const lga = document.getElementById('desc_lga')?.value?.trim() || '';
        const state = document.getElementById('propertyState')?.value?.trim() || 'Kano';
        const streetName = getSelectOrManualValue('streetName', 'manual_street_name');

        const parts = [];
        // If both house and plot exist, show only plot. Otherwise show whichever is present.
        if (plot) {
            parts.push(plot);
        } else if (house) {
            parts.push(`House No ${house}`);
        }
        if (streetName && streetName !== 'Select Street Name') parts.push(streetName);
        if (district && district !== 'Select District') parts.push(district);
        if (lga && lga !== 'Select LGA') parts.push(lga);
        if (state) parts.push(state);

        const descEl = document.getElementById('propertyDescription');
        if (descEl) {
            descEl.value = parts.join(', ');
        }
    }

    function setHidden(name, value) {
        let control = elements.registrationForm.querySelector(`[name = "${name}"]`);
        if (!control) {
            control = document.createElement('input');
            control.type = 'hidden';
            control.name = name;
            elements.registrationForm.appendChild(control);
        }
        control.value = value == null ? '' : value;
    }

    function buildAddress(prefix) {
        const street = getSelectOrManualValue(`${prefix}Street`, `manual_${prefix}Street`);
        const lga = document.getElementById(`${prefix}Lga`)?.value?.trim() || '';
        const state = document.getElementById(`${prefix}State`)?.value?.trim() || '';
        const postal = document.getElementById(`${prefix}PostalCode`)?.value?.trim() || '';
        const country = document.getElementById(`${prefix}Country`)?.value?.trim() || '';
        return [street, lga, state, postal, country].filter(Boolean).join(', ');
    }

    async function fetchLgas(stateId, lgaSelectId, selectedValue = null) {
        const lgaSelect = document.getElementById(lgaSelectId);
        if (!lgaSelect) return;

        // Clear existing options except first
        lgaSelect.innerHTML = '<option value="">Loading...</option>';

        if (!stateId) {
            lgaSelect.innerHTML = '<option value="">Select LGA</option>';
            return;
        }

        try {
            // We can fetch by ID or Name. The database table StatLGAs uses StateID.
            // Wait, the $states in blade has StateID as value? 
            // Let's check instrument-select.blade.php again.
            // Line 25: $optionValue = is_object($option) ? ($option->value ?? $option->StateName ?? '') : $option;
            // In InstrumentController, states is from `DB::table('States')->get()`.
            // So it has StateID and StateName. 
            // In instrument-select, it will use StateName as value if 'value' property is missing.
            // Wait, let's check State table again.
            // stdClass Object ( [StateID] => 1 [StateName] => Abia [Capital] => Umuahia )
            // So the value will be 'Abia' (StateName) because 'value' property is missing in the object.
            // BUT we need StateID to fetch LGAs from StatLGAs.

            // I should probably update the State dropdown to use StateID as value.
            // Or if I can't change that, I need to fetch by StateName? 
            // Better to change State dropdown to use StateID.

            const response = await fetch(`/instruments/get-lgas/${encodeURIComponent(stateId)}`);
            const lgas = await response.json();

            lgaSelect.innerHTML = '<option value="">Select LGA</option>';
            lgas.forEach(lga => {
                const option = document.createElement('option');
                option.value = lga.LGAName; // We store Name in address string
                option.textContent = lga.LGAName;
                if (selectedValue && (lga.LGAName === selectedValue || lga.LGAID == selectedValue)) {
                    option.selected = true;
                }
                lgaSelect.appendChild(option);
            });

            // Trigger UI update for the selected value
            if (selectedValue) {
                updateCustomSelect(lgaSelectId, selectedValue);
                lgaSelect.dispatchEvent(new Event('change'));
            }
        } catch (e) {
            console.error('Error fetching LGAs:', e);
            lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
        }
    }

    // Exported later at the bottom of DOMContentLoaded, but moved here for safety
    window.prefillLandUseAndPurposeFromLookup = prefillLandUseAndPurposeFromLookup;

    async function showAtomicRegistrationConfirmation() {
        const type = instrumentTypes[currentInstrumentType];
        if (!type) {
            console.error('Unknown currentInstrumentType:', currentInstrumentType);
            return false;
        }

        const instrumentName = type.name;
        let subType = '';

        if (currentInstrumentType === 'occupancy-permit') {
            const opTypeEl = document.querySelector('input[name="op_type"]:checked');
            subType = opTypeEl ? opTypeEl.value : '';
            if (!subType) {
                Swal.fire('Required', 'Please select an Occupancy Permit sub-type first.', 'info');
                return false;
            }
        } else if (currentInstrumentType === 'certificate-of-occupancy') {
            subType = document.getElementById('cofoType')?.value || '';
            if (!subType) {
                Swal.fire('Required', 'Please select a Certificate of Occupancy type first.', 'info');
                return false;
            }
        } else {
            subType = instrumentName;
        }

        const manualRegFlow = currentInstrumentType === 'occupancy-permit' && isFfrExistingManualRegistrationFlow();
        const serialNo = (document.getElementById('serial_no')?.value || '').toString().trim();
        const pageNo = (document.getElementById('reg_page_no')?.value || '').toString().trim();
        const volumeNo = (document.getElementById('volume_no')?.value || '').toString().trim();
        const registrationParticulars = (document.getElementById('registration_number')?.value || '').toString().trim();
        const regInfoLabel = manualRegFlow
            ? 'Manual Registration Details'
            : 'Registration Number';
        const regInfoText = manualRegFlow
            ? 'This registration uses the manual Serial / Page / Volume entered in the OP form.'
            : 'Will be assigned automatically upon submission';
        const regInfoHint = manualRegFlow
            ? ('Serial: ' + (serialNo || '-') + ' | Page: ' + (pageNo || '-') + ' | Vol: ' + (volumeNo || '-') + (registrationParticulars ? (' | Ref: ' + registrationParticulars) : ''))
            : 'The system will allocate the correct unique registration particulars (Serial / Page / Volume) when you confirm.';

        try {
            const result = await Swal.fire({
                title: 'Confirm Registration',
                html: `
                    <div class="text-left">
                        <p class="text-gray-600 mb-4">You are about to register <b>${instrumentName}</b> ${subType !== instrumentName ? `â€” <span class="text-blue-600 font-bold">${subType}</span>` : ''}.</p>
                        
                        <div class="bg-blue-50/50 rounded-2xl border border-blue-100 p-4 relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 opacity-5">
                                <i data-lucide="shield-check" class="h-24 w-24 text-blue-900"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="lock" class="h-5 w-5 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-800">${regInfoLabel}</p>
                                        <p class="text-xs text-gray-500">${regInfoText}</p>
                                    </div>
                                </div>
                                <p class="text-[10px] text-emerald-600 font-medium bg-emerald-50/50 p-2 rounded-lg border border-emerald-100 flex items-center gap-2">
                                    <i data-lucide="info" class="h-3 w-3"></i> ${regInfoHint}
                                </p>
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Confirm & Register',
                cancelButtonText: 'Review Form',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#94a3b8',
                customClass: {
                    popup: 'rounded-2xl border-none shadow-2xl p-6',
                    confirmButton: 'rounded-xl px-6 py-3 font-bold',
                    cancelButton: 'rounded-xl px-6 py-3 font-bold'
                },
                didRender: () => {
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            });

            return result.isConfirmed;

        } catch (err) {
            console.error(err);
            Swal.close();
            Swal.fire('Error', 'Failed to reach server', 'error');
            return false;
        }
    }

    async function handleSubmit(e) {
        if (e) e.preventDefault();

        // Prevent submission if the record is already captured (defense-in-depth)
        if (alreadyCapturedLock) {
            Swal.fire({
                icon: 'warning',
                title: 'Already Captured',
                text: 'This instrument has already been captured and registered. Duplicate submission is not allowed.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        normalizeAssignmentGiftBookletCode();

        const submitLabel = getSubmitLabel().toLowerCase();
        const isCaptureExistingOpSubmit = submitLabel === 'capture existing op';

        // In FileNo Commissioning OP capture, keep FEFR flags off so submit
        // cannot route to FEFR handoff/toast paths.
        if (currentInstrumentType === 'occupancy-permit' && window.ossOpContext === true) {
            window.ffrExistingManualRegistration = false;
            window.ffrDirectOpMode = false;
        }

        // Keep FEFR context sticky during submit. In some UI transitions the
        // flag can be stale, which wrongly routes submit to commissioning logic.
        if (currentInstrumentType === 'occupancy-permit' && isCaptureExistingOpSubmit && window.ossOpContext !== true) {
            window.ffrExistingManualRegistration = true;
        }

        if (isCaptureExistingOpSubmit && elements.submitBtn) {
            if (elements.submitBtn.dataset.submitting === '1') {
                return;
            }
            elements.submitBtn.dataset.submitting = '1';
            elements.submitBtn.disabled = true;
            elements.submitBtn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i> Saving...';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // Existing OP found: pure handoff to commissioning (no validation/submission here).
        if (currentInstrumentType === 'occupancy-permit' && window.ossOpContext === true && opLookupMatchedRecord && !isFfrExistingManualRegistrationFlow() && window._isOngoingMerger !== true) {
            window._opCaptureSubmitted = true;
            continueWithFileCommissioningFromLookup(opLookupMatchedRecord);
            return;
        }

        // Non-create page with instrument_capture lookup: copy data to PRA then commission.
        if (currentInstrumentType === 'occupancy-permit' && !isInstrumentCaptureCreatePage && opLookupMatchedRecord && !isFfrExistingManualRegistrationFlow() && window._isOngoingMerger !== true) {
            const lookupSource = String(opLookupMatchedRecord.source_table || 'instrument_capture').toLowerCase();
            if (lookupSource === 'instrument_capture') {
                window._opCaptureSubmitted = true;
                continueWithFileCommissioningFromLookup(opLookupMatchedRecord);
                return;
            }
        }

        // FEFR flow: existing OP selected from lookup - update PRA then commission.
        // Direct OP mode: create OP PRA row, then open Capture Existing for Transfer of Title.
        // Non-direct: hand off to commissioning.
        if (currentInstrumentType === 'occupancy-permit' && isFfrExistingManualRegistrationFlow() && opLookupMatchedRecord) {
            window._opCaptureSubmitted = true;

            // FEFR submit should always create a fresh OP row and hand off to
            // Transfer flow; never fall back to file-commissioning update prompts.
            window.ffrDirectOpMode = true;

            if (window.ffrDirectOpMode === true) {
                // Indexed file: create OP PRA row from matched record, then open Capture Existing
                const rec = opLookupMatchedRecord;
                const hasPropId = false; // ffrDirectOpMode always creates a new OP PRA row — never updates an existing one
                const formDataCurrent = new FormData(elements.registrationForm);
                const formPayloadCurrent = buildPraPayload(formDataCurrent);
                const typedGrantor = (formDataCurrent.get('firstPartyName') || '').toString().trim() || 'Kano State Government';
                const typedGrantee = (formDataCurrent.get('secondPartyName') || rec.party_2_name || rec.second_party_name || '').toString().trim() || null;
                const csrfTok = document.querySelector('input[name="_token"]')?.value ||
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const resolvedTemp2 = (window._generatedTempFileno || '').toString().trim();
                const sourceFileNo3 = (window.ffrExistingSourceFileNo || '').toString().trim();
                const systemTempFileNo3 = (elements.displayFileno?.value || '').toString().trim();
                const tempFileNo3 = systemTempFileNo3.toUpperCase().startsWith('TEMP-') ? systemTempFileNo3 : (resolvedTemp2 || '');
                const praFileNo3 = tempFileNo3 || sourceFileNo3;

                const praPayload3 = {
                    transaction_type: 'Occupancy Permit (OP)',
                    instrument_type: 'Occupancy Permit (OP)',
                    transaction_date: (rec.transaction_date || rec.entry_date || '').toString().trim() || null,
                    source: 'FFR Existing OP Capture',
                    mlsFNo: sourceFileNo3 || null,
                    fileno: praFileNo3,
                    temp_fileno: tempFileNo3 || null,
                    op_type: (rec.op_type || 'OP Resettlement').toString(),
                    op_serial_number: (rec.op_serial_number || '').toString().trim() || null,
                    serialNo: (rec.serial_no || '').toString().trim() || null,
                    pageNo: (rec.page_no || '').toString().trim() || null,
                    volumeNo: (rec.volume_no || '').toString().trim() || null,
                    regNo: (rec.registration_number || '').toString().trim() || null,
                    reg_date: (rec.reg_date || '').toString().trim() || null,
                    reg_time: (rec.reg_time || '').toString().trim() || null,
                    deeds_date: (rec.deeds_date || rec.reg_date || rec.entry_date || '').toString().trim() || null,
                    deeds_time: (rec.deeds_time || rec.reg_time || '').toString().trim() || null,
                    land_use: (rec.land_use || '').toString().trim() || null,
                    plot_no: (rec.plot_no || rec.plot_number || '').toString().trim() || null,
                    tp_no: (rec.tp_no || '').toString().trim() || null,
                    location: (rec.property_description || rec.location || '').toString().trim() || null,
                    property_description: (rec.property_description || rec.location || '').toString().trim() || null,
                    Grantor: typedGrantor,
                    Grantee: typedGrantee,
                    party_1: typedGrantor,
                    party_2: typedGrantee,
                    lga: (rec.lga || '').toString().trim() || null,
                    parties: {
                        grantor: typedGrantor,
                        grantee: typedGrantee,
                    },
                };

                if (elements.submitBtn) {
                    elements.submitBtn.disabled = true;
                    elements.submitBtn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i> Saving OP to PRA...';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }

                try {
                    let resolvedPropId = null;

                    if (hasPropId) {
                        const confirmFfrDirect = await Swal.fire({
                            icon: 'question',
                            title: 'Update Existing Record?',
                            html: '<p class="text-sm text-gray-700">This will update the existing PRA record <strong>(Prop ID: ' + rec.prop_id + ')</strong> with your changes before proceeding.</p>',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Update & Continue',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#2563eb',
                            cancelButtonColor: '#6b7280',
                        });

                        if (!confirmFfrDirect.isConfirmed) {
                            if (elements.submitBtn) {
                                elements.submitBtn.disabled = false;
                                elements.submitBtn.dataset.submitting = '';
                                applySubmitButtonLabel();
                            }
                            return;
                        }

                        const updatePayload = Object.assign({}, formPayloadCurrent, {
                            prop_id: String(rec.prop_id).trim(),
                            update_siblings: true,
                        });
                        const updateOk = await upsertPraByPropId(updatePayload);
                        if (!updateOk) {
                            Swal.fire({
                                icon: 'error',
                                title: 'PRA Update Failed',
                                text: 'Could not update the existing PRA record. Please retry.'
                            });
                            if (elements.submitBtn) {
                                elements.submitBtn.disabled = false;
                                elements.submitBtn.dataset.submitting = '';
                                applySubmitButtonLabel();
                            }
                            return;
                        }
                        await Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Existing PRA record updated',
                            showConfirmButton: false,
                            timer: 1400,
                            timerProgressBar: true,
                        });
                        resolvedPropId = String(rec.prop_id).trim();
                    } else {
                        const praResp3 = await fetch('/api/pra/v1/records', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfTok,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(praPayload3),
                        });
                        const praData3 = await praResp3.json().catch(() => null);
                        if (!praResp3.ok || !praData3?.success) {
                            console.warn('FFR Direct OP (lookup) PRA creation response:', praData3);
                        }
                        resolvedPropId = praData3?.data?.prop_id ? String(praData3.data.prop_id) : null;
                    }

                    const opDetail = {
                        op_type: (rec.op_type || 'OP Resettlement').toString(),
                        transaction_date: (rec.transaction_date || rec.entry_date || '').toString().trim(),
                        op_serial_number: (rec.op_serial_number || '').toString().trim(),
                        serial_no: (rec.serial_no || '').toString().trim(),
                        page_no: (rec.page_no || '').toString().trim(),
                        volume_no: (rec.volume_no || '').toString().trim(),
                        registration_number: (rec.registration_number || '').toString().trim(),
                        first_party_name: typedGrantor,
                        second_party_name: typedGrantee || '',
                        allottee: typedGrantee || '',
                        land_use: (rec.land_use || '').toString().trim(),
                        plot_no: (rec.plot_no || rec.plot_number || '').toString().trim(),
                        tp_no: (rec.tp_no || '').toString().trim(),
                        location: (rec.property_description || rec.location || '').toString().trim(),
                        lga: (rec.lga || '').toString().trim(),
                        ffr_temp_fileno: tempFileNo3 || null,
                        source_file_no: sourceFileNo3 || '',
                        was_ffrDirectOpMode: true,
                    };
                    if (resolvedPropId) {
                        opDetail.ffr_first_pra_prop_id = resolvedPropId;
                    }

                    window.dispatchEvent(new CustomEvent('ffr-existing-op-captured', {
                        detail: opDetail
                    }));
                } catch (praErr3) {
                    console.warn('FFR Direct OP (lookup) PRA creation failed:', praErr3);
                }

                if (elements.submitBtn) {
                    elements.submitBtn.disabled = false;
                    applySubmitButtonLabel();
                }

                closeRegistrationDialog();
                window.ffrExistingManualRegistration = false;
                window.ffrDirectOpMode = false;
                window.ffrIndexedFileData = null;
                window._generatedTempFileno = null;
                window.ossOpSubmitLabel = '';
                return;
            }
        }

        // ── Consent Gate: Assignment & Gift require a printed consent letter ──────
        const consentGatePassed = await runConsentGateCheck({ silent: false });
        if (!consentGatePassed) return;
        // ─────────────────────────────────────────────────────────────────────────

        // 1. Validation
        if (!validateForm()) {
            if (isCaptureExistingOpSubmit) {
                resetSubmitButton();
            }
            return;
        }

        // FFR Existing Manual flow: create PRA record (party1=Kano State Government),
        // then hand off OP form data to Capture Existing form (no instrument registration).
        if (currentInstrumentType === 'occupancy-permit' && isFfrExistingManualRegistrationFlow()) {
            const selectedOpType = document.querySelector('input[name="op_type"]:checked');
            const detail = {
                op_type: selectedOpType ? selectedOpType.value : '',
                transaction_date: (document.getElementById('transactionDate')?.value || document.getElementById('entryDate')?.value || '').toString().trim(),
                op_serial_number: (document.getElementById('op_serial_number')?.value || '').toString().trim(),
                serial_no: (document.getElementById('serial_no')?.value || '').toString().trim(),
                page_no: (document.getElementById('reg_page_no')?.value || document.getElementById('reg_page_no_display')?.value || '').toString().trim(),
                volume_no: (document.getElementById('volume_no')?.value || '').toString().trim(),
                registration_number: (document.getElementById('registration_number')?.value || '').toString().trim(),
                reg_date: (document.getElementById('registrationDate')?.value || document.getElementById('entryDate')?.value || '').toString().trim(),
                reg_time: (document.getElementById('registrationTime')?.value || '').toString().trim(),
                deeds_date: (document.getElementById('registrationDate')?.value || document.getElementById('entryDate')?.value || '').toString().trim(),
                deeds_time: (document.getElementById('registrationTime')?.value || '').toString().trim(),
                first_party_name: (document.getElementById('firstPartyName')?.value || '').toString().trim(),
                second_party_name: (document.getElementById('secondPartyName')?.value || '').toString().trim(),
                allottee: (document.getElementById('secondPartyName')?.value || '').toString().trim(),
                land_use: (document.getElementById('land_use')?.value || '').toString().trim(),
                purpose: (document.getElementById('purpose')?.value || '').toString().trim(),
                plot_no: (document.getElementById('plotNumber')?.value || '').toString().trim(),
                tp_no: (document.getElementById('tp_no')?.value || '').toString().trim(),
                location: (document.getElementById('propertyDescription')?.value || '').toString().trim(),
                lga: (document.getElementById('desc_lga')?.value || '').toString().trim(),
            };

            // Validate Transaction Date on the OP card before proceeding
            if (!detail.transaction_date) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Transaction Date Required',
                    text: 'Please enter the Transaction Date before submitting.',
                    confirmButtonColor: '#f59e0b',
                });
                if (elements.submitBtn) {
                    elements.submitBtn.disabled = false;
                    applySubmitButtonLabel();
                }
                return;
            }

            const sourceFileNo = (window.ffrExistingSourceFileNo || '').toString().trim();
            const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            // ── Direct OP mode (indexed files): create OP PRA row, then open Capture Existing for Transfer of Title ──
            if (window.ffrDirectOpMode === true) {
                const indexed = window.ffrIndexedFileData || {};
                const resolvedTemp = (window._generatedTempFileno || '').toString().trim();
                console.log('[FFR Direct OP manual] _generatedTempFileno:', resolvedTemp);

                const systemTempFileNo2 = (elements.displayFileno?.value || '').toString().trim();
                const tempFileNo2 = systemTempFileNo2.toUpperCase().startsWith('TEMP-') ? systemTempFileNo2 : (resolvedTemp || '');
                const praFileNo2 = tempFileNo2 || sourceFileNo;
                const praPayload2 = {
                    transaction_type: 'Occupancy Permit (OP)',
                    instrument_type: 'Occupancy Permit (OP)',
                    transaction_date: detail.transaction_date || null,
                    source: 'FFR Existing OP Capture',
                    mlsFNo: sourceFileNo || null,
                    fileno: praFileNo2,
                    temp_fileno: tempFileNo2 || null,
                    op_type: detail.op_type,
                    op_serial_number: detail.op_serial_number || null,
                    serialNo: detail.serial_no || null,
                    pageNo: detail.page_no || null,
                    volumeNo: detail.volume_no || null,
                    regNo: detail.registration_number || null,
                    reg_date: detail.reg_date || null,
                    reg_time: detail.reg_time || null,
                    deeds_date: detail.deeds_date || null,
                    deeds_time: detail.deeds_time || null,
                    land_use: detail.land_use || null,
                    purpose: detail.purpose || null,
                    plot_no: detail.plot_no || null,
                    tp_no: detail.tp_no || null,
                    location: detail.location || null,
                    property_description: detail.location || null,
                    Grantor: detail.first_party_name || 'Kano State Government',
                    Grantee: detail.second_party_name || null,
                    party_1: detail.first_party_name || 'Kano State Government',
                    party_2: detail.second_party_name || null,
                    parties: {
                        grantor: detail.first_party_name || 'Kano State Government',
                        grantee: detail.second_party_name || null,
                    },
                    merger_group_id: window._mergerGroupId || null,
                    is_merger_op: window._isOngoingMerger ? 1 : 0,
                };

                if (elements.submitBtn) {
                    elements.submitBtn.disabled = true;
                    elements.submitBtn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i> Saving OP to PRA...';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }

                try {
                    const praResp2 = await fetch('/api/pra/v1/records', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(praPayload2),
                    });
                    const praData2 = await praResp2.json().catch(() => null);
                    if (!praResp2.ok || !praData2?.success) {
                        console.warn('FFR Direct OP PRA creation response:', praData2);
                    }
                    if (praData2?.data?.prop_id) {
                        detail.ffr_first_pra_prop_id = praData2.data.prop_id;
                    }
                    detail.ffr_temp_fileno = tempFileNo2 || null;
                } catch (praErr2) {
                    console.warn('FFR Direct OP PRA creation failed:', praErr2);
                }

                if (elements.submitBtn) {
                    elements.submitBtn.disabled = false;
                    applySubmitButtonLabel();
                }

                window._opCaptureSubmitted = true;

                detail.source_file_no = sourceFileNo || '';
                detail.was_ffrDirectOpMode = true;  // flag for merger Swal

                window.dispatchEvent(new CustomEvent('ffr-existing-op-captured', {
                    detail: detail
                }));

                closeRegistrationDialog();
                window.ffrExistingManualRegistration = false;
                window.ffrDirectOpMode = false;
                window.ffrIndexedFileData = null;
                window._generatedTempFileno = null;
                window.ossOpSubmitLabel = '';
                return;
            }

            // ── Non-indexed files: original flow — create one PRA row, then dispatch to Capture Existing ──
            const systemTempFileNo = (elements.displayFileno?.value || '').toString().trim();
            const tempFileNo = systemTempFileNo.toUpperCase().startsWith('TEMP-') ? systemTempFileNo : (window.ffrExistingTempFileNo || '').toString().trim();
            const praFileNo = tempFileNo || sourceFileNo;
            const praPayload = {
                transaction_type: 'Occupancy Permit (OP)',
                instrument_type: 'Occupancy Permit (OP)',
                transaction_date: detail.transaction_date || null,
                source: 'FFR Existing OP Capture',
                mlsFNo: null,
                fileno: praFileNo,
                temp_fileno: tempFileNo || null,
                op_type: detail.op_type,
                op_serial_number: detail.op_serial_number || null,
                serialNo: detail.serial_no || null,
                pageNo: detail.page_no || null,
                volumeNo: detail.volume_no || null,
                regNo: detail.registration_number || null,
                reg_date: detail.reg_date || null,
                reg_time: detail.reg_time || null,
                deeds_date: detail.deeds_date || null,
                deeds_time: detail.deeds_time || null,
                land_use: detail.land_use || null,
                purpose: detail.purpose || null,
                plot_no: detail.plot_no || null,
                tp_no: detail.tp_no || null,
                location: detail.location || null,
                property_description: detail.location || null,
                Grantor: 'Kano State Government',
                Grantee: detail.second_party_name || null,
                party_1: 'Kano State Government',
                party_2: detail.second_party_name || null,
                parties: {
                    grantor: 'Kano State Government',
                    grantee: detail.second_party_name || null,
                },
            };

            // Show brief loading
            if (elements.submitBtn) {
                elements.submitBtn.disabled = true;
                elements.submitBtn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i> Saving to PRA...';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            try {
                const praResp = await fetch('/api/pra/v1/records', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(praPayload),
                });
                const praData = await praResp.json().catch(() => null);
                if (!praResp.ok || !praData?.success) {
                    console.warn('FFR Existing OP PRA creation response:', praData);
                }
                // Capture prop_id from first PRA to pass to the second record
                if (praData?.data?.prop_id) {
                    detail.ffr_first_pra_prop_id = praData.data.prop_id;
                }
                // Also store temp file number for the second record
                detail.ffr_temp_fileno = tempFileNo || null;
            } catch (praErr) {
                console.warn('FFR Existing OP PRA creation failed:', praErr);
            }

            // Reset button
            if (elements.submitBtn) {
                elements.submitBtn.disabled = false;
                applySubmitButtonLabel();
            }

            // Mark OP capture as submitted for the FEFR flow.
            window._opCaptureSubmitted = true;

            // Dispatch event to populate Capture Existing form
            window.dispatchEvent(new CustomEvent('ffr-existing-op-captured', {
                detail: detail
            }));

            closeRegistrationDialog();
            window.ffrExistingManualRegistration = false;
            window.ossOpSubmitLabel = '';
            return;
        }

        // Universal Atomic Confirmation Intercept (skip for OSS OP -> PRA flow)
        if (!(currentInstrumentType === 'occupancy-permit' && window.ossOpContext === true)) {
            const confirmed = await showAtomicRegistrationConfirmation();
            if (!confirmed) return;
        }

        // 2. Loading State
        const originalContent = elements.submitBtn.innerHTML;
        const isUpdate = duplicateCheckState.isUpdateMode;
        const processingText = isUpdate ? 'Updating...' : 'Processing...';

        elements.submitBtn.disabled = true;
        elements.submitBtn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i> ${processingText}`;
        if (typeof lucide !== 'undefined') lucide.createIcons();

        // 3. Prepare Data
        const formData = new FormData(elements.registrationForm);

        if (document.getElementById('tp_no')) {
            try {
                await persistManualTpLookup();
            } catch (error) {
                console.warn(error.message || error);
            }
            formData.set('tp_no', getTpLookupValue());
        }

        // Handle "Others" for District
        const districtSelect = document.getElementById('desc_district');
        const manualDistrictInput = document.getElementById('manual_district');

        // Override instrument_type for Atomic Types
        if (currentInstrumentType === 'occupancy-permit') {
            formData.set('instrument_type', 'Occupancy Permit (OP)');
        } else if (currentInstrumentType === 'certificate-of-occupancy') {
            formData.set('instrument_type', 'Certificate of Occupancy');
        }

        if (districtSelect && districtSelect.value === 'Others' && manualDistrictInput) {
            // Replace the "Others" value with the typed manual input
            formData.set('district', manualDistrictInput.value);
            // If the backend expects 'lga' and 'district' but maybe also 'desc_district' if coming from select
            // Ensure we update any other field names
            if (formData.has('desc_district')) {
                formData.set('desc_district', manualDistrictInput.value);
            }
        }

        const streetSelect = document.getElementById('streetName');
        if (streetSelect) {
            const resolvedStreet = getSelectOrManualValue('streetName', 'manual_street_name');
            formData.set('streetName', resolvedStreet);
            formData.set('property_street_name', resolvedStreet);
        }

        [
            { field: 'firstPartyStreet', manual: 'manual_firstPartyStreet' },
            { field: 'secondPartyStreet', manual: 'manual_secondPartyStreet' },
            { field: 'firstPartyDistrict', manual: 'manual_firstPartyDistrict' },
            { field: 'secondPartyDistrict', manual: 'manual_secondPartyDistrict' },
            { field: 'coMortgagorDistrict', manual: 'manual_coMortgagorDistrict' },
            { field: 'thirdPartyDistrict', manual: 'manual_thirdPartyDistrict' },
            { field: 'party5District', manual: 'manual_party5District' }
        ].forEach(({ field, manual }) => {
            if (!document.getElementById(field)) return;
            const resolved = getSelectOrManualValue(field, manual);
            if (resolved) {
                formData.set(field, resolved);
            } else {
                formData.delete(field);
            }
        });

        // Survey Info district ("Others" → typed value). Only override when a value is chosen
        // so it doesn't wipe the district resolved from the property-details section.
        const surveyDistrictSelect = document.getElementById('district');
        if (surveyDistrictSelect && surveyDistrictSelect.value) {
            const resolvedSurveyDistrict = getSelectOrManualValue('district', 'manual_survey_district');
            if (resolvedSurveyDistrict) formData.set('district', resolvedSurveyDistrict);
        }

        // Force _method=PUT if URL indicates update and it's not in formData
        const storeUrlInput = document.getElementById('storeUrl');
        if (storeUrlInput && storeUrlInput.value.includes('/instruments/') && !storeUrlInput.value.endsWith('/store')) {
            formData.set('_method', 'PUT');
        }

        const includeSolicitorSelected = document.getElementById('includeSolicitorSidebar')?.checked === true
            || document.getElementById('includeSolicitor')?.checked === true;
        formData.set('include_solicitor', includeSolicitorSelected ? '1' : '0');

        const url = storeUrlInput ? storeUrlInput.value : '/instruments/store';

        if (currentInstrumentType === 'occupancy-permit' && window.ossOpContext === true) {
            await submitPraRecord(formData, originalContent);
            return;
        }

        try {
            const response = await fetch(url, {
                method: 'POST', // Laravel uses POST + _method for PUT
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '',
                    'Accept': 'application/json'
                }
            });

            const responseText = await response.text();
            console.log('Submission raw response:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse response as JSON:', parseError);
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'Received an invalid response format from the server. Please check the logs.'
                });
                elements.submitBtn.disabled = false;
                elements.submitBtn.innerHTML = originalContent;
                return;
            }

            if (data.success) {
                // Store deed_registration_id for RDS/CoR actions
                const deedRegId = data.deed_registration_id || null;
                const instrumentCaptureId = data.id || null;

                // Reset the form immediately before showing success popup
                resetRegistrationState({ hideDialog: false, resetInstrumentType: false });

                // REDESIGNED: Premium COMPACT Light Theme Success Modal
                const isSyncActive = (data.instrument_type || currentInstrumentType || "").includes("Deed of Gift") ||
                    (data.instrument_type || currentInstrumentType || "").includes("Deed of Assignment") ||
                    (data.instrument_type || currentInstrumentType || "").includes("Power of Attorney");

                Swal.fire({
                    width: '720px',
                    html: `
                        <div class="text-left">
                            <!-- Success Header (Compact) -->
                            <div class="flex items-center gap-3 mb-5 bg-emerald-50/50 p-3 rounded-2xl border border-emerald-100">
                                <div class="bg-emerald-500 text-white p-2 rounded-xl shadow-sm">
                                    <i data-lucide="badge-check" class="h-6 w-6"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-800 tracking-tight leading-none">Registration Successful</h2>
                                     
                                </div>
                            </div>

                            <!-- Context Details (Compact) -->
                            <div class="grid grid-cols-2 gap-2 mb-6">
                                <div class="bg-gray-50/80 p-2 rounded-xl border border-gray-100">
                                    <span class="block text-[8px] text-gray-400 uppercase font-bold mb-0.5">Instrument Type</span>
                                    <span class="font-bold text-gray-700 text-xs truncate block">${data.instrument_type || currentInstrumentType}</span>
                                </div>
                                <div class="bg-gray-50/80 p-2 rounded-xl border border-gray-100">
                                    <span class="block text-[8px] text-gray-400 uppercase font-bold mb-0.5">FileNo</span>
                                    <span class="font-bold text-blue-600 text-xs block">${data.fileno || elements.displayFileno.value}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                ${isSyncActive ? `
                                <!-- Detailed Sync Notification -->
                                <div class="p-4 bg-blue-50/50 border border-blue-100 rounded-3xl flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center gap-2 mb-3">
                                            <div class="bg-blue-500 text-white p-1 rounded-lg">
                                                <i data-lucide="refresh-cw" class="h-3 w-3"></i>
                                            </div>
                                            <span class="text-[10px] font-black text-blue-700 uppercase tracking-tight">System Records Synchronized</span>
                                        </div>

                                        ${data.sync_result && data.sync_result.synced ? `
                                        <div class="mb-4 space-y-2">
                                            <div class="flex justify-between items-center bg-white/60 p-2 rounded-xl border border-blue-100/50 shadow-sm">
                                                <span class="text-[7px] text-blue-400 font-black uppercase tracking-wider">Old File Name</span>
                                                <span class="text-[9px] text-slate-500 font-bold truncate max-w-[150px] ml-2 text-right">${data.sync_result.old_name || 'N/A'}</span>
                                            </div>
                                            <div class="flex justify-between items-center bg-emerald-50/60 p-2 rounded-xl border border-emerald-200/50 shadow-sm">
                                                <span class="text-[7px] text-emerald-600 font-black uppercase tracking-wider">New File Name</span>
                                                <span class="text-[9px] text-emerald-900 font-black truncate max-w-[150px] ml-2 text-right">${data.sync_result.new_name || 'N/A'}</span>
                                            </div>
                                        </div>
                                        ` : ''}
                                    </div>

                                    <ul class="text-[9px] text-blue-600 space-y-1 font-bold pt-3 border-t border-blue-100/50 mt-auto">
                                        <li>. File Indexing: Updated File Title</li>
                                        <li>. Customer: Updated Customer Name</li>
                                        <li>. Entity: Updated Entity Name</li>
                                        <li>. FileNumber: Updated File Name</li>
                                    </ul>
                                </div>
                                ` : ''}

                                <!-- Permanent Registry Card (Compact Premium) -->
                                <div class="${isSyncActive ? '' : 'col-span-2'} bg-gradient-to-br from-indigo-50 to-blue-50/30 rounded-[2rem] border border-blue-100 p-5 shadow-inner-sm relative overflow-hidden group">
                                    <div class="relative z-10">
                                        <div class="mb-5 text-center">
                                            <span class="block text-[9px] text-blue-400/80 mb-1 font-bold tracking-tight">REGISTRATION PARTICULARS</span>
                                            <div class="text-3xl font-black font-mono tracking-tighter text-blue-900 bg-white inline-block px-4 py-1.5 rounded-xl shadow-sm border border-blue-50">
                                               ${data.registration_number || data.reg_number || 'N/A'}
                                            </div>
                                        </div>

                                        <!-- REORDERED: Serial, Page, Volume -->
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="p-2 rounded-xl bg-white/60 text-center border border-white/80 shadow-sm">
                                                <span class="block text-[8px] text-gray-400 uppercase font-bold mb-0.5">Serial</span>
                                                <span class="text-base font-black text-slate-700">${data.serial_no || '-'}</span>
                                            </div>
                                            <div class="p-2 rounded-xl bg-white/60 text-center border border-white/80 shadow-sm">
                                                <span class="block text-[8px] text-gray-400 uppercase font-bold mb-0.5">Page</span>
                                                <span class="text-base font-black text-slate-700">${data.page_no || '-'}</span>
                                            </div>
                                            <div class="p-2 rounded-xl bg-white/60 text-center border border-white/80 shadow-sm">
                                                <span class="block text-[8px] text-gray-400 uppercase font-bold mb-0.5">Vol</span>
                                                <span class="text-base font-black text-slate-700">${data.volume_no || '-'}</span>
                                            </div>
                                        </div>

                                        <!-- Integrated Date/Time -->
                                        <div class="mt-4 pt-3 border-t border-blue-100/50 space-y-1">
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-blue-400 font-medium">Deed Registration Time:</span>
                                                <span class="text-blue-900 font-bold">${data.deeds_time || '-'}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-blue-400 font-medium">Deed Registration Date:</span>
                                                <span class="text-blue-900 font-bold">${data.deeds_date || '-'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                            <!-- RDS & CoR Action Buttons -->
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <button onclick="window._icGenerateRDS('deed_reg_${deedRegId}')" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors ${deedRegId ? '' : 'opacity-40 cursor-not-allowed'}" ${deedRegId ? '' : 'disabled'}>
                                    <i data-lucide="file-plus-2" class="h-3.5 w-3.5"></i>
                                    Generate RDS
                                </button>
                                <button onclick="window._icPrintRDS('deed_reg_${deedRegId}')" id="ic-print-rds-btn" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 transition-colors opacity-40 cursor-not-allowed" disabled>
                                    <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                    Print RDS
                                </button>
                                <button onclick="window._icGenerateCoR('deed_reg_${deedRegId}')" id="ic-generate-cor-btn" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors opacity-40 cursor-not-allowed" disabled>
                                    <i data-lucide="award" class="h-3.5 w-3.5"></i>
                                    Generate CoR
                                </button>
                                <button onclick="window._icPrintCoR('deed_reg_${deedRegId}')" id="ic-print-cor-btn" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition-colors opacity-40 cursor-not-allowed" disabled>
                                    <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                    Print CoR
                                </button>
                            </div>
                        </div>
                    `,
                    showConfirmButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#10b981',
                    allowOutsideClick: false,
                    customClass: {
                        popup: 'rounded-[2.5rem] border-none shadow-2xl p-6',
                        confirmButton: 'rounded-xl px-6 py-2 font-bold'
                    },
                    didRender: () => {
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    },
                    willClose: () => {
                        // If a return URL is set (e.g. from the OP capture edit page), redirect there
                        if (window.ossOpCaptureReturnUrl) {
                            window.location.href = window.ossOpCaptureReturnUrl;
                            return;
                        }
                    }
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Failed',
                    text: data.message || 'Please check the form input.'
                });
                elements.submitBtn.disabled = false;
                elements.submitBtn.innerHTML = originalContent;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        } catch (error) {
            console.error('Submission error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Submission Error',
                text: 'An error occurred while processing your request.'
            });
            elements.submitBtn.disabled = false;
            elements.submitBtn.innerHTML = originalContent;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    async function submitPraRecord(formData, originalContent) {
        // Restore the submit button immediately â€” the commission modal takes over UI.
        if (elements.submitBtn) {
            elements.submitBtn.disabled = false;
            elements.submitBtn.innerHTML = originalContent;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // Capture Existing OP flow requirement:
        // persist to PRA first (using temp file number when available), then commission.
        const basePayload = buildPraPayload(formData);
        if (window._mergerGroupId) {
            basePayload.merger_group_id = window._mergerGroupId;
        }
        if (window._isOngoingMerger === true) {
            basePayload.is_merger_op = 1;
        }
        const preSync = await createOrUpdatePraBeforeCommission(basePayload);
        if (!preSync.success) {
            Swal.fire({
                icon: 'error',
                title: 'PRA Save Failed',
                text: preSync.message || 'Could not save record to PRA before commissioning.'
            });
            return;
        }

        const normalizePartyName = (s) => (s || '').toString().trim().toLowerCase().replace(/\s+/g, ' ');
        const formatPartyList = (names) => {
            const list = Array.isArray(names) ? names.filter((n) => (n || '').toString().trim() !== '') : [];
            if (list.length === 0) return '';
            if (list.length === 1) return list[0];
            if (list.length === 2) return list[0] + ' and ' + list[1];
            return list.slice(0, -1).join(', ') + ' and ' + list[list.length - 1];
        };

        // For Transfer of Title row, Party 1 should be all source sellers from OP rows.
        const latestTransferFrom = (formData.get('secondPartyName') || '').toString().trim();
        // Also track each OP's plot number so the ToT row gets a combined "x, x and x" value.
        const latestPlotNo = (formData.get('plotNumber') || '').toString().trim();
        if (window._isOngoingMerger !== true) {
            window._mergerTransferGrantors = [];
            window._mergerPlotNos = [];
        }
        if (latestTransferFrom) {
            window._mergerTransferGrantors = window._mergerTransferGrantors || [];
            const exists = window._mergerTransferGrantors.some((name) => normalizePartyName(name) === normalizePartyName(latestTransferFrom));
            if (!exists) {
                window._mergerTransferGrantors.push(latestTransferFrom);
            }
        }
        if (latestPlotNo) {
            window._mergerPlotNos = window._mergerPlotNos || [];
            if (!window._mergerPlotNos.includes(latestPlotNo)) {
                window._mergerPlotNos.push(latestPlotNo);
            }
        }
        const mergedTransferFrom = formatPartyList(window._mergerTransferGrantors || []);
        const mergedPlotNo = formatPartyList(window._mergerPlotNos || []);

        // FileNo Commissioning: after each OP save, show type-selection dialog.
        // Subdivision and Merger are independent flags — user can apply both.
        // Dialog never closes on its own; only Merger or Proceed buttons close it.
        if (currentInstrumentType === 'occupancy-permit' && window.ossOpContext === true) {
            const opSavedAction = await showOpSavedActionsDialog({ propId: preSync.propId });

            if (opSavedAction === 'merger') {
                if (!window._mergerGroupId) {
                    window._mergerGroupId = generateMergerUuid();
                }
                window._isOngoingMerger = true;

                // Explicitly keep FEFR flow off while looping in Commission flow.
                window.ffrExistingManualRegistration = false;
                window.ffrDirectOpMode = false;

                // Prevent stale OP lookup match (and its prop_id leakage) from
                // hijacking the next merger OP submit. Without clearing
                // window.lastCommissionSourceOp, buildPraPayload's resolvedPropId
                // fallback would inherit the previous OP's prop_id, causing the
                // next save to UPSERT the previous row instead of creating a new
                // one — i.e. "two mergers but only one saved".
                opLookupMatchedRecord = null;
                window.lastCommissionSourceOp = null;
                alreadyCapturedLock = false;
                setOssOpSubmitMode(false);
                clearOpLookupResultsCards();

                if (preSync.propId) {
                    await stampPraMergerGroup(preSync.propId, window._mergerGroupId);
                }

                resetOpFormForNextMergerCapture();
                if (elements.submitBtn) {
                    elements.submitBtn.dataset.submitting = '';
                    elements.submitBtn.disabled = false;
                    applySubmitButtonLabel();
                }

                // Keep OP modal active for the next seller capture.
                window._opCaptureSubmitted = false;
                return;
            }

            // 'proceed' (with or without subdivision already stamped): continue normally
        }

        window.pendingNewOpFormData = null;
        window.pendingExistingOpPraContext = {
            prop_id: preSync.propId,
            source_pra_id: preSync.praId,
            temp_fileno: basePayload.temp_fileno || basePayload.fileno || null,
            source_op_party_2: latestTransferFrom || null,
            merger_source_parties: mergedTransferFrom || null,
            merger_plot_nos: mergedPlotNo || null,
            commissioned_file_name: (formData.get('file_name') || '').toString().trim() || null,
            base_payload: Object.assign({}, basePayload, { prop_id: preSync.propId })
        };

        // This path did not come from an instrument_capture lookup record.
        // Clear stale source OP cache so commission submit does not enforce
        // source_instrument_capture_id from previous runs.
        window.lastCommissionSourceOp = null;

        // Build a minimal handoff record. id=null means sourceInstrumentCaptureId will
        // be empty in the commission form â†’ backend skips mirror inserts.
        const opType = document.querySelector('input[name="op_type"]:checked')?.value || '';
        const newOpRecord = {
            id: null,
            prop_id: preSync.propId || null,
            source_pra_id: preSync.praId || null,
            op_serial_number: formData.get('op_serial_number') || '',
            op_type: opType,
            party_1_name: formData.get('firstPartyName') || '',
            party_2_name: formData.get('secondPartyName') || '',
            serial_no: '',
            page_no: '',
            volume_no: '',
            registration_number: '',
            plot_number: formData.get('plotNumber') || '',
            tp_no: formData.get('tp_no') || '',
            property_location: formData.get('propertyDescription') || formData.get('plotLocation') || '',
            lga: formData.get('desc_lga') || '',
            // This OP row was just persisted in submitPraRecord(); avoid asking
            // to update it again during immediate commissioning handoff.
            skip_pra_update_prompt: true,
            // submitPraRecord already showed the OP Saved! action dialog above.
            // Prevent continueWithFileCommissioningFromLookup from showing the
            // same dialog a second time (which made the user click Continue
            // twice before the commission modal opened).
            skip_op_saved_dialog: true
        };

        // Mark OP capture as submitted so close handler knows the transaction completed.
        window._opCaptureSubmitted = true;

        // End merger session when user chooses to proceed with commissioning.
        window._mergerGroupId = null;
        window._isOngoingMerger = false;
        window._mergerTransferGrantors = [];
        window._mergerPlotNos = [];

        // Open commission modal. After file generation, submitPendingExistingOpPra
        // updates this same prop_id with the commissioned file number.
        continueWithFileCommissioningFromLookup(newOpRecord);
    }

    function generateMergerUuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            var v = c === 'x' ? r : ((r & 0x3) | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Show the unified "OP Saved!" action dialog used by the FileNo Commissioning
     * flow (both new OP captures and existing OP lookups). User picks Subdivision
     * (inline count, dialog stays open), Merger (close + loop OP capture), or
     * Continue with File Commissioning.
     *
     * @param {{ propId: ?(string|number) }} opts
     * @returns {Promise<'merger'|'proceed'>}
     */
    function showOpSavedActionsDialog(opts) {
        var propId = opts && opts.propId ? opts.propId : null;
        return new Promise(function (resolve) {
            // Guard against the same dialog being requested twice while one is open.
            if (typeof Swal !== 'undefined' && typeof Swal.isVisible === 'function' && Swal.isVisible()) {
                try { Swal.close(); } catch (e) { /* noop */ }
            }
            var settled = false;
            function finish(action) {
                if (settled) return;
                settled = true;
                try { Swal.close(); } catch (e) { /* noop */ }
                resolve(action);
            }
            Swal.fire({
                icon: 'question',
                title: 'OP Saved!',
                html: '<div style="display:grid;gap:12px;margin-top:4px;">'
                    + '<div style="border:1.5px solid #e2e8f0;border-radius:10px;padding:12px 12px 10px;background:#f8fafc;">'
                    + '<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin:0 0 10px;">Continue with OP Processing</p>'
                    + '<button id="swal-btn-subdivide" class="swal2-confirm swal2-styled" style="background:#ea580c;justify-content:center;width:100%;margin-bottom:8px;">&#x2702; Subdivision &mdash; this OP covers multiple files</button>'
                    + '<div id="swal-sub-count-row" style="display:none;gap:8px;align-items:center;margin-bottom:8px;">'
                    + '<input id="swal-op-count" type="number" min="2" step="1" placeholder="e.g. 3" '
                    + 'style="flex:1;padding:8px 12px;border:1.5px solid #ea580c;border-radius:8px;font-size:14px;text-align:center;" />'
                    + '<button id="swal-sub-confirm" style="background:#ea580c;color:white;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">Confirm</button>'
                    + '</div>'
                    + '<button id="swal-btn-merger" class="swal2-confirm swal2-styled" style="background:#7c3aed;justify-content:center;width:100%;margin-bottom:0;">+ Merger &mdash; another OP will be added</button>'
                    + '</div>'
                    + '<button id="swal-btn-proceed" class="swal2-cancel swal2-styled" style="background:#2563eb;justify-content:center;width:100%;">Continue with File Commissioning</button>'
                    + '</div>',
                showConfirmButton: false,
                showCancelButton: false,
                allowOutsideClick: false,
                didOpen: function () {
                    var subBtn = document.getElementById('swal-btn-subdivide');
                    var countRow = document.getElementById('swal-sub-count-row');
                    var countInput = document.getElementById('swal-op-count');
                    var subConfirm = document.getElementById('swal-sub-confirm');
                    var isSubdivided = false;

                    subBtn.onclick = function () {
                        if (isSubdivided) return;
                        subBtn.style.display = 'none';
                        countRow.style.display = 'flex';
                        countInput.focus();
                    };

                    subConfirm.onclick = function () {
                        var val = parseInt(countInput.value, 10);
                        if (!val || val < 2) {
                            countInput.style.borderColor = '#ef4444';
                            countInput.focus();
                            return;
                        }
                        isSubdivided = true;
                        var csrfToken = document.querySelector('meta[name="csrf-token"]')
                            ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
                        if (propId) {
                            fetch('/api/pra/v1/records/' + encodeURIComponent(propId), {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ is_subdivided: 1, op_count: val }),
                            }).catch(function (e) { console.warn('Subdivision stamp failed:', e); });
                        }
                        countRow.style.display = 'none';
                        subBtn.style.display = 'block';
                        subBtn.style.background = '#16a34a';
                        subBtn.innerHTML = '&#x2714; Subdivided &mdash; ' + val + ' file(s)';
                        subBtn.style.cursor = 'default';
                        subBtn.onclick = null;
                    };

                    document.getElementById('swal-btn-merger').onclick = function () {
                        finish('merger');
                    };

                    document.getElementById('swal-btn-proceed').onclick = function () {
                        finish('proceed');
                    };
                },
            });
        });
    }

    async function stampPraMergerGroup(propId, mergerGroupId) {
        if (!propId || !mergerGroupId) return;

        try {
            const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            await fetch(`/api/pra/v1/records/${encodeURIComponent(String(propId).trim())}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    merger_group_id: mergerGroupId,
                    is_merger_op: 1,
                }),
            });
        } catch (err) {
            console.warn('Merger stamp failed:', err);
        }
    }

    function resetOpFormForNextMergerCapture() {
        // Keep Grantor Name (Party 1), Party 2 (new owner), and file context.
        // Clear only OP registration-specific fields.
        ['op_serial_number', 'serial_no', 'reg_page_no', 'reg_page_no_display', 'volume_no', 'registration_number', 'registrationDate', 'registrationTime']
            .forEach((id) => {
                const el = document.getElementById(id);
                if (el) {
                    el.value = '';
                    el.dispatchEvent(new Event('input'));
                }
            });

        document.querySelectorAll('input[name="op_type"]').forEach((radio) => {
            radio.checked = false;
        });
    }

    async function createOrUpdatePraBeforeCommission(payload) {
        const csrfToken = document.querySelector('input[name="_token"]')?.value || '';

        let propId = (payload?.prop_id || '').toString().trim();
        const fileCandidate = (payload?.temp_fileno || payload?.fileno || '').toString().trim();

        const forceCreateForCommissionOp = currentInstrumentType === 'occupancy-permit' && window.ossOpContext === true;

        // Check if we loaded from an existing PRA record via OP serial lookup
        const isFromPra = !forceCreateForCommissionOp
            && opLookupMatchedRecord
            && String(opLookupMatchedRecord.source_table || '').toLowerCase() === 'pra'
            && opLookupMatchedRecord.prop_id;

        if (!propId && isFromPra) {
            propId = String(opLookupMatchedRecord.prop_id).trim();
        }

        if (!propId && fileCandidate) {
            try {
                const lookupResp = await fetch(`/api/pra/v1/records/by-file/${encodeURIComponent(fileCandidate)}`, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                const lookupData = await lookupResp.json().catch(() => null);
                if (lookupResp.ok && lookupData?.success && lookupData?.data?.prop_id) {
                    propId = String(lookupData.data.prop_id).trim();
                }
            } catch (err) {
                console.warn('PRA pre-lookup by file failed:', err);
            }
        }

        // If loaded from existing PRA, confirm and update the record with user edits before commissioning
        if (isFromPra && propId) {
            const confirmResult = await Swal.fire({
                icon: 'question',
                title: 'Update Existing Record?',
                html: '<p class="text-sm text-gray-700">This will update the existing PRA record <strong>(Prop ID: ' + propId + ')</strong> with any changes you made (registration details, dates, etc.) before proceeding to file commissioning.</p>',
                showCancelButton: true,
                confirmButtonText: 'Yes, Update & Continue',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
            });

            if (!confirmResult.isConfirmed) {
                return { success: false, propId: null, praId: null, message: 'Update cancelled by user.' };
            }

            const updatePayload = Object.assign({}, payload);
            delete updatePayload.prop_id;
            try {
                const updateResp = await fetch(`/api/pra/v1/records/${encodeURIComponent(propId)}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(updatePayload)
                });
                const updateData = await updateResp.json().catch(() => null);
                if (updateResp.ok && updateData?.success) {
                    console.log('Existing PRA record updated before commission:', propId);
                    return { success: true, propId: propId, praId: String(opLookupMatchedRecord.id || ''), message: '' };
                }
                console.warn('PRA update failed, falling through to create:', updateData?.message);
            } catch (err) {
                console.warn('PRA update before commission failed:', err);
            }
        }

        // POST a new record when no existing PRA to update.
        const createPayload = Object.assign({}, payload, {
            prop_id: propId || payload?.prop_id || null
        });

        try {
            const createResp = await fetch('/api/pra/v1/records', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(createPayload)
            });

            const createData = await createResp.json().catch(() => null);
            const createdPropId = (createData?.data?.prop_id || createData?.data?.propId || '').toString().trim();
            const createdPraId = (createData?.data?.id || '').toString().trim();

            const flattenErrors = (errorsObj) => {
                if (!errorsObj || typeof errorsObj !== 'object') return '';
                const parts = [];
                Object.keys(errorsObj).forEach((key) => {
                    const val = errorsObj[key];
                    if (Array.isArray(val)) {
                        val.forEach((m) => parts.push(String(m)));
                    } else if (val) {
                        parts.push(String(val));
                    }
                });
                return parts.join(' ');
            };

            if (!createResp.ok || !createData?.success || !createdPropId) {
                // Fallback for strict duplicate/validation paths: upsert existing prop id.
                if (propId) {
                    const upsertPayload = Object.assign({}, payload, { prop_id: propId });
                    const ok = await upsertPraByPropId(upsertPayload);
                    if (ok) {
                        return { success: true, propId: propId, praId: null, message: '' };
                    }
                }
                return {
                    success: false,
                    propId: null,
                    praId: null,
                    message: flattenErrors(createData?.errors) || createData?.message || 'PRA create did not return a valid prop_id.'
                };
            }

            return { success: true, propId: createdPropId, praId: createdPraId || null, message: '' };
        } catch (err) {
            console.warn('PRA create before commission failed:', err);
            return { success: false, propId: null, praId: null, message: 'Network error while creating PRA record.' };
        }
    }

    // Called by mls_js.blade.php after a file number is commissioned for a new (non-existing) OP.
    // Creates a single PRA record using the real file number â€” no temp file numbers.
    window.submitPendingNewOpPra = async function (fileNumber) {
        if (!window.pendingNewOpFormData || !fileNumber) return;
        const formData = window.pendingNewOpFormData;
        window.pendingNewOpFormData = null;

        const csrfToken = document.querySelector('input[name="_token"]')?.value || '';
        const payload = buildPraPayload(formData);
        // Override with the real commissioned file number; clear any temp reference.
        payload.mlsFNo = fileNumber;
        payload.fileno = fileNumber;
        payload.temp_fileno = null;
        // Tag OP Change of Name system source for traceability.
        if (!payload.system_source) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.pathname.includes('/lands-one-stop-shop/') || currentUrl.searchParams.get('type') === 'change-of-name') {
                payload.system_source = 'OSSOPCHANGEOFNAME';
            }
        }

        try {
            const resp = await fetch('/api/pra/v1/records', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await resp.json().catch(() => null);
            if (!resp.ok || !data?.success) {
                console.warn('New OP PRA record creation failed:', data?.message);
            }
        } catch (err) {
            console.warn('New OP PRA API error:', err);
        }
    };

    // Called after commissioning succeeds for an existing OP captured via lookup/manual form.
    // Always creates Row 2: a new PRA record identical to Row 1 but with mlsFNo filled in.
    // If the name changed, Row 2 becomes a Transfer of Title instead of an OP.
    window.submitPendingExistingOpPra = async function (fileNumber, commissionedFileNameArg = '', options = {}) {
        const normalizedFileNumber = (fileNumber || '').toString().trim();
        if (!window.pendingExistingOpPraContext || !normalizedFileNumber) return;

        const ctx = window.pendingExistingOpPraContext;
        const baseGrantor = (ctx.base_payload?.party_1 || ctx.base_payload?.Grantor || '').toString().trim();
        const baseGrantee = (ctx.base_payload?.party_2 || ctx.base_payload?.Grantee || '').toString().trim();
        const sourceOpParty2 = (ctx.source_op_party_2 || '').toString().trim();
        const mergedSourceParties = (ctx.merger_source_parties || '').toString().trim();
        const mergerPlotNos = (ctx.merger_plot_nos || '').toString().trim();
        const commissionedFileName = (
            commissionedFileNameArg ||
            ctx.commissioned_file_name ||
            ''
        ).toString().trim();

        const normalizePartyText = (s) => (s || '').toString().trim().toLowerCase().replace(/\s+/g, ' ');
        const formatPartyNames = (names) => {
            const list = Array.isArray(names) ? names.filter((n) => (n || '').toString().trim() !== '') : [];
            if (list.length === 0) return '';
            if (list.length === 1) return list[0];
            if (list.length === 2) return list[0] + ' and ' + list[1];
            return list.slice(0, -1).join(', ') + ' and ' + list[list.length - 1];
        };

        // Fallback: when merger_source_parties is not present, derive all source
        // sellers from persisted OP rows under the same prop_id.
        let resolvedMergedSourceParties = mergedSourceParties;
        if (!resolvedMergedSourceParties && ctx.prop_id) {
            try {
                const historyResp = await fetch(`/api/pra/v1/records/${encodeURIComponent(String(ctx.prop_id))}/history`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const historyData = await historyResp.json().catch(() => null);
                if (historyResp.ok && historyData?.success && Array.isArray(historyData.data)) {
                    const sellers = [];
                    historyData.data.forEach((row) => {
                        const tx = (row?.transaction_type || '').toString().toLowerCase();
                        const isTransfer = tx.includes('transfer of title');
                        const isOp = tx.includes('occupancy permit') || tx.includes('(op)');
                        if (!isTransfer && isOp) {
                            const grantee = (row?.parties?.grantee || '').toString().trim();
                            if (grantee && !sellers.some((n) => normalizePartyText(n) === normalizePartyText(grantee))) {
                                sellers.push(grantee);
                            }
                        }
                    });
                    const merged = formatPartyNames(sellers);
                    if (merged) {
                        resolvedMergedSourceParties = merged;
                    }
                }
            } catch (historyErr) {
                console.warn('submitPendingExistingOpPra: could not resolve merged sellers from history', historyErr);
            }
        }

        // Detect name change: compare original allottee vs commissioned file name.
        const normalize = (s) => (s || '').toString().trim().toLowerCase().replace(/\s+/g, ' ');
        const sourcePartyForTransfer = resolvedMergedSourceParties || sourceOpParty2;
        const hasNameChange = sourcePartyForTransfer !== ''
            && commissionedFileName !== ''
            && normalize(sourcePartyForTransfer) !== normalize(commissionedFileName);

        // When there is no name change the record would be an OP row — but OP
        // data already lives in instrument_capture / deed_registrations.  Skip
        // the PRA POST to avoid duplicating the OP.  Only Transfer of Title
        // rows (name changed) should be created in PRA.
        if (!hasNameChange) {
            console.info('submitPendingExistingOpPra: no name change — skipping PRA (OP source is IC/DR)');
            window.pendingExistingOpPraContext = null;
            return;
        }

        const csrfToken = document.querySelector('input[name="_token"]')?.value || '';
        const apiHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        };

        // Build Row 2: copy of the OP base payload with the commissioned file number.
        // No name change â†’ keep same OP transaction type.
        // Name changed â†’ switch to Transfer of Title with correct parties.
        const payload = Object.assign({}, ctx.base_payload || {}, {
            prop_id: ctx.prop_id,
            mlsFNo: normalizedFileNumber,
            fileno: normalizedFileNumber,
            temp_fileno: ctx.temp_fileno || null,
            Grantor: baseGrantor || null,
            Grantee: baseGrantee || sourcePartyForTransfer || commissionedFileName || null,
            party_1: baseGrantor || null,
            party_2: baseGrantee || sourcePartyForTransfer || commissionedFileName || null,
        });

        if (hasNameChange) {
            payload.transaction_type = 'Transfer of Title (OP)';
            payload.instrument_type = 'Transfer of Title (OP)';
            // Grantor = original allottee (transferring from), Grantee = new owner.
            const effectiveGrantor = sourcePartyForTransfer;
            if (effectiveGrantor) {
                payload.Grantor = effectiveGrantor;
                payload.party_1 = effectiveGrantor;
            }
            if (commissionedFileName) {
                payload.Grantee = commissionedFileName;
                payload.party_2 = commissionedFileName;
            }
            // For merger ToT rows, combine all source OP plot numbers.
            if (mergerPlotNos) {
                payload.plot_no = mergerPlotNos;
            }
        }

        // Tag OP Change of Name system source for traceability.
        if (!payload.system_source) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.pathname.includes('/lands-one-stop-shop/') || currentUrl.searchParams.get('type') === 'change-of-name') {
                payload.system_source = 'OSSOPCHANGEOFNAME';
            }
        }

        // Ensure parties sub-object carries the resolved party values so the
        // backend buildPartyAssignments() can map them even if flat fields are
        // stripped by validation.
        payload.parties = {
            grantor: payload.Grantor || null,
            grantee: payload.Grantee || null
        };

        let ok = false;
        try {
            const createResp = await fetch('/api/pra/v1/records', {
                method: 'POST',
                headers: apiHeaders,
                body: JSON.stringify(payload)
            });
            const createData = await createResp.json().catch(() => null);
            ok = !!(createResp.ok && createData?.success);
            if (!ok) {
                console.warn('Existing OP PRA Row 2 creation response:', createData);
            }
        } catch (e) {
            console.warn('Existing OP PRA Row 2 creation exception:', e);
            ok = false;
        }

        if (!ok) {
            console.warn('Existing OP PRA Row 2 creation failed for prop_id:', ctx.prop_id, 'hasNameChange:', hasNameChange, 'payload:', payload);
        }

        window.pendingExistingOpPraContext = null;
    };

    function buildPraPayload(formData) {
        const getVal = (name) => (formData.get(name) || '').toString().trim();
        const mlsFNo = getVal('mlsFNo');
        const kangisFileNo = getVal('kangisFileNo');
        const newKangisFileNo = getVal('NewKANGISFileno');
        const fileno = getVal('fileno');
        const displayFileno = elements.displayFileno?.value?.trim() || '';
        const tempFileno = displayFileno.toUpperCase().startsWith('TEMP-') ? displayFileno : '';

        const opType = document.querySelector('input[name="op_type"]:checked')?.value || '';
        const transactionType = opType ? `Occupancy Permit (OP) - ${opType}` : 'Occupancy Permit (OP)';

        const serialNo = getVal('serial_no');
        const pageNo = getVal('page_no') || serialNo;
        const volumeNo = getVal('volume_no');
        const regNo = getVal('registration_number') || getVal('regNo');
        const entryDate = getVal('entryDate');
        const transactionDate = getVal('transactionDate') || entryDate;
        const registrationDate = getVal('registrationDate') || entryDate;
        const registrationTime = getVal('registrationTime');
        const opSerialNumber = getVal('op_serial_number');
        const purpose = getVal('purpose');
        const resolvedPropId = getVal('prop_id') || (window.lastCommissionSourceOp?.prop_id || '') || (opLookupMatchedRecord?.prop_id || '');

        // If this OP came from a lookup selection, preserve the exact source row
        // identity so backend dedup/linking does not fall back to non-unique
        // op_serial_number matching.
        const matchedSourceTableRaw = String(opLookupMatchedRecord?.source_table || '').toLowerCase();
        const matchedSourceTable = (matchedSourceTableRaw === 'instrument_capture' || matchedSourceTableRaw === 'deed_registrations')
            ? matchedSourceTableRaw
            : null;
        const matchedSourceIdRaw = matchedSourceTable ? (opLookupMatchedRecord?.id || '') : '';
        const matchedSourceId = (matchedSourceIdRaw && /^\d+$/.test(String(matchedSourceIdRaw)))
            ? Number(matchedSourceIdRaw)
            : null;

        const payload = {
            prop_id: resolvedPropId || null,
            mlsFNo: mlsFNo || null,
            kangisFileNo: kangisFileNo || null,
            NewKANGISFileno: newKangisFileNo || null,
            fileno: fileno || null,
            temp_fileno: tempFileno || ((!mlsFNo && !kangisFileNo && !newKangisFileNo && !fileno && displayFileno) ? displayFileno : null),
            source: 'Occupancy Permit (OP)',
            transaction_type: transactionType,
            transaction_date: transactionDate || null,
            reg_date: registrationDate || null,
            reg_time: registrationTime || null,
            deeds_date: registrationDate || null,
            deeds_time: registrationTime || null,
            regNo: regNo || null,
            serialNo: serialNo || null,
            pageNo: pageNo || null,
            volumeNo: volumeNo || null,
            op_serial_number: opSerialNumber || null,
            op_type: opType || null,
            purpose: purpose || null,
            land_use: getVal('land_use') || null,
            plot_no: getVal('plotNumber') || null,
            tp_no: getVal('tp_no') || null,
            lpkn_no: getVal('lpkn_no') || null,
            plot_size: getVal('plotSize') || getVal('size') || null,
            house_no: getVal('house_no') || null,
            lgsaOrCity: getVal('desc_lga') || null,
            location: getVal('desc_district') || getVal('desc_lga') || null,
            property_description: getVal('propertyDescription') || null,
            instrument_type: 'Occupancy Permit (OP)',
            parties: {
                grantor: getVal('firstPartyName') || null,
                grantee: getVal('secondPartyName') || null
            },
            Grantor: getVal('firstPartyName') || null,
            Grantee: getVal('secondPartyName') || null,
            source_op_id: matchedSourceId,
            source_op_table: matchedSourceTable,
            // Opt-in flag for the backend dedup guard at PraRecordController::store.
            // Only set when the user explicitly accepted a candidate from the
            // existing-OP confirmation modal — never on fresh captures.
            link_existing_op: !!matchedSourceId
        };

        return payload;
    }

    function validateForm() {
        const fileNo = elements.displayFileno.value;
        const typeId = currentInstrumentType;
        const p1Name = document.getElementById('firstPartyName')?.value?.trim();
        const p2Name = document.getElementById('secondPartyName')?.value?.trim();

        const errors = [];
        if (!fileNo) errors.push('File selection is required.');
        if (!typeId) errors.push('Instrument type is not defined.');
        if (!p1Name) errors.push('First party name is required.');
        if (!p2Name) errors.push('Second party name is required.');

        // Party 2 Address Validation (LGA & State)
        const p2Lga = document.getElementById('secondPartyLga')?.value?.trim();
        const p2State = document.getElementById('secondPartyState')?.value?.trim();

        // Occupancy Permit Specific Validation
        if (typeId === 'occupancy-permit' || typeId === 'Occupancy Permit') {
            const opTypeSelected = document.querySelector('input[name="op_type"]:checked');
            const transactionDate = document.getElementById('transactionDate')?.value?.trim();
            const todayDate = new Date().toISOString().split('T')[0];
            if (!opTypeSelected) {
                errors.push('Occupancy Permit Type is required (Resettlement or Direct Allocation).');
            }
            if (!isInstrumentCaptureCreatePage) {
                if (!transactionDate) {
                    errors.push('Transaction Date is required.');
                } else if (transactionDate > todayDate) {
                    errors.push('Transaction Date cannot be in the future.');
                }
            }

            if (window.ossOpContext === true) {
                const opSerialNumber = document.getElementById('op_serial_number')?.value?.trim();
                const serialNo = document.getElementById('serial_no')?.value?.trim();
                const volumeNo = document.getElementById('volume_no')?.value?.trim();
                if (!opSerialNumber) errors.push('OP Serial Number is required.');
                else if (/[^0-9]/.test(opSerialNumber)) errors.push('OP Serial Number must contain only digits (no letters).');
                else if (/^0/.test(opSerialNumber)) errors.push('OP Serial Number must not start with zero.');
                if (!serialNo) errors.push('OP Registration Serial No is required.');
                if (!volumeNo) errors.push('OP Registration Volume No is required.');
            }
        }

        // Certificate of Occupancy Specific Validation
        if (typeId === 'certificate-of-occupancy' || typeId === 'Certificate of Occupancy') {
            const cofoType = document.getElementById('cofoType')?.value;
            if (!cofoType) {
                errors.push('Certificate of Occupancy Type is required.');
            }
        }

        // Deed of Assignment / Gift solicitor details validation
        if (typeId === 'deed-of-assignment' || typeId === 'deed-of-gift') {
            const includeSolicitor = document.getElementById('includeSolicitorSidebar')?.checked === true
                || document.getElementById('includeSolicitor')?.checked === true;
            const solicitorName = document.getElementById('solicitorName')?.value?.trim() || '';
            const solicitorAddress = document.getElementById('solicitorAddress')?.value?.trim() || '';
            const solicitorDistrict = document.getElementById('solicitorDistrict')?.value?.trim() || '';
            const solicitorState = document.getElementById('solicitorState')?.value?.trim() || '';
            const solicitorLga = document.getElementById('solicitorLga')?.value?.trim() || '';
            const hasAnySolicitorDetail = Boolean(
                solicitorName || solicitorAddress || solicitorDistrict || solicitorState || solicitorLga
            );

            if (includeSolicitor || hasAnySolicitorDetail) {
                if (!solicitorName) errors.push('Solicitor Name is required for Deed of Assignment/Gift.');
                if (!solicitorAddress) errors.push('Solicitor Address is required for Deed of Assignment/Gift.');
                if (!solicitorState) errors.push('Solicitor State is required for Deed of Assignment/Gift.');
                if (!solicitorLga) errors.push('Solicitor LGA is required for Deed of Assignment/Gift.');
            }
        }
        const district = document.getElementById('desc_district')?.value?.trim();
        const manualDistrict = document.getElementById('manual_district')?.value?.trim();
        const lga = document.getElementById('desc_lga')?.value?.trim();

        if (!lga) {
            errors.push('Property LGA is required.');
        }

        // Land Use and Purpose Validation
        const landUse = document.getElementById('land_use_id')?.value;
        const purpose = document.getElementById('purpose_id')?.value;

        if (!landUse) {
            errors.push('Land Use is required.');
        }
        if (!purpose) {
            errors.push('Purpose is required.');
        }

        if (errors.length > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Incomplete Form',
                html: '<ul style="text-align: left;">' + errors.map(e => `<li>- ${e}</li>`).join('') + '</ul>'
            });
            return false;
        }
        return true;
    }

    // --- PRA History Logic ---

    function showPraHistoryModal() {
        console.log('showPraHistoryModal: Starting execution');

        // Check if elements are available
        if (!elements.praHistoryModal) {
            console.error('showPraHistoryModal: elements.praHistoryModal NOT found in cache, re-fetching...');
            elements.praHistoryModal = document.getElementById('pra-history-modal');
        }
        if (!elements.praHistoryBody) {
            console.error('showPraHistoryModal: elements.praHistoryBody NOT found in cache, re-fetching...');
            elements.praHistoryBody = document.getElementById('pra-history-body');
        }

        if (!elements.praHistoryModal || !elements.praHistoryBody) {
            console.error('showPraHistoryModal: Required elements still missing. Aborting.', {
                modal: !!elements.praHistoryModal,
                body: !!elements.praHistoryBody
            });
            return;
        }

        let history = duplicateCheckState.praHistory;
        const fileNo = elements.displayFileno.value;

        // NEW: Filter for Surrender/Release - Only show Mortgages
        if (currentInstrumentType === 'deed-of-surrender-release') {
            history = history.filter(rec => {
                const type = (rec.transaction_type || rec.instrument_type || '').toLowerCase();
                return type.includes('mortgage');
            });
            console.log(`showPraHistoryModal: Filtered history for Surrender/Release. Showing ${history.length} mortgages.`);
        }

        // Sort history chronologically by transaction_date as requested
        if (history && history.length > 0) {
            history.sort((a, b) => {
                const dateA = new Date(a.transaction_date || a.deeds_date || a.assignment_date || a.regDate || a.created_at || 0);
                const dateB = new Date(b.transaction_date || b.deeds_date || b.assignment_date || b.regDate || b.created_at || 0);
                return dateA - dateB;
            });
            console.log('showPraHistoryModal: Sorted history chronologically.');
        }

        console.log(`showPraHistoryModal: Populating modal for file ${fileNo} with ${history.length} records`);

        if (elements.praHistorySubtitle) {
            elements.praHistorySubtitle.textContent = `Showing historical records for file: ${fileNo}`;
        }

        // Clear existing rows
        elements.praHistoryBody.innerHTML = '';

        if (!history || history.length === 0) {
            console.warn('showPraHistoryModal: No history records found in state.');
            elements.praHistoryBody.innerHTML = `
            <div class="flex flex-col items-center justify-center py-12 px-4 text-center bg-slate-50 rounded-2xl border-2 border-dashed border-slate-200">
                <div class="p-3 bg-white rounded-full shadow-sm mb-4">
                    <i data-lucide="info" class="h-6 w-6 text-slate-400"></i>
                </div>
                <h4 class="text-slate-900 font-bold">No Records Found</h4>
                <p class="text-sm text-slate-500 max-w-xs mt-1">We couldn't find any historical records matching this file number in the PRA database.</p>
            </div>
        `;
        } else {
            history.forEach((record, index) => {
                const card = document.createElement('div');
                card.className = "bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all duration-300 group hover:border-indigo-300 relative overflow-hidden";

                const partyFields = [
                    'Grantor', 'Grantee', 'Mortgagor', 'Mortgagee',
                    'Surrenderor', 'Surrenderee', 'Lessor', 'Lessee',
                    'Assignor', 'Assignee', 'Donor', 'Donee', 'Vendor', 'Purchaser'
                ];

                const foundParties = [];
                // Primary Party Columns: party_1, party_2, party_3
                const partyCols = [
                    { key: 'party_1', label: 'Party 1' },
                    { key: 'party_2', label: 'Party 2' },
                    { key: 'party_3', label: 'Party 3' }
                ];

                partyCols.forEach(col => {
                    if (record[col.key]) {
                        foundParties.push(`
                            <div class="flex items-start gap-2">
                                <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400 mt-1 min-w-[70px]">${col.label}:</span>
                                <span class="text-[11px] text-slate-700 font-semibold">${record[col.key]}</span>
                            </div>
                        `);
                    }
                });

                // Fallback to legacy party fields if primary columns are empty
                if (foundParties.length === 0) {
                    const legacyFields = ['Grantor', 'Grantee', 'Mortgagor', 'Mortgagee', 'Assignor', 'Assignee', 'Donor', 'Donee', 'Vendor', 'Purchaser'];
                    legacyFields.forEach(field => {
                        if (record[field]) {
                            foundParties.push(`
                                <div class="flex items-start gap-2">
                                    <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400 mt-1 min-w-[70px]">${field}:</span>
                                    <span class="text-[11px] text-slate-700 font-semibold">${record[field]}</span>
                                </div>
                            `);
                        }
                    });
                }

                const particulars = `${record.serialNo || '0'}/${record.pageNo || '0'}/${record.volumeNo || '0'}`;
                // Display deeds_date and deeds_time
                const dateVal = record.deeds_date || record.transaction_date || record.assignment_date || record.regDate || record.created_at || 'N/A';
                const formattedDate = dateVal !== 'N/A' ? new Date(dateVal).toLocaleDateString() : 'N/A';

                const transDateVal = record.transaction_date || 'N/A';
                const formattedTransDate = transDateVal !== 'N/A' ? new Date(transDateVal).toLocaleDateString() : 'N/A';

                // Helper to format time to AM/PM
                const formatTime = (t) => {
                    if (!t) return null;
                    try {
                        const parts = t.split(':');
                        if (parts.length < 2) return t;
                        let h = parseInt(parts[0]);
                        const m = parts[1].substring(0, 2);
                        const ampm = h >= 12 ? 'PM' : 'AM';
                        h = h % 12 || 12;
                        return `${h.toString().padStart(2, '0')}:${m} ${ampm}`;
                    } catch (e) { return t; }
                };

                const formattedTime = formatTime(record.deeds_time);

                const instType = record.transaction_type || record.instrument_type || record.instrumentType || 'N/A';

                card.innerHTML = `
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500 rounded-l-2xl group-hover:w-1.5 transition-all"></div>
                    <div class="space-y-4">
                        <!-- Header with Type & File -->
                        <div class="flex items-center justify-between gap-2 border-b border-slate-50 pb-2">
                             <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[9px] font-bold uppercase tracking-widest rounded-md border border-indigo-100">${instType}</span>
                             <span class="text-[10px] font-mono text-slate-400 font-bold">${record.mlsFNo || record.kangisFileNo || record.fileno || 'N/A'}</span>
                        </div>
                        
                        <!-- Parties List -->
                        <div class="space-y-1.5">
                            ${foundParties.slice(0, 5).join('')}
                            ${foundParties.length > 5 ? `<p class="text-[9px] text-indigo-500 font-bold ml-[78px] mt-1">+ ${foundParties.length - 5} more parties</p>` : ''}
                        </div>

                        <!-- Details Footer (Grid style for balance) -->
                        <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-50">
                            <div class="flex flex-col space-y-2">
                                <div class="flex flex-col">
                                    <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400">Reg Particulars</span>
                                    <span class="text-sm font-black text-slate-900 font-mono tracking-tight">${particulars}</span>
                                </div>
                                ${formattedTransDate !== 'N/A' ? `
                                <div class="flex flex-col border-t border-slate-100 pt-1">
                                    <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400">Trans. Date</span>
                                    <span class="text-[11px] font-bold text-slate-600">${formattedTransDate}</span>
                                </div>` : ''}
                            </div>
                            <div class="flex flex-col items-end text-right space-y-2">
                                ${formattedTime ? `
                                <div class="flex flex-col items-end">
                                    <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400">Reg Time</span>
                                    <span class="text-[11px] font-bold text-slate-600">${formattedTime}</span>
                                </div>` : ''}
                                <div class="flex flex-col items-end border-t border-slate-50 pt-1 w-full">
                                    <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400">Reg Date</span>
                                    <span class="text-[11px] font-bold text-slate-600">${formattedDate}</span>
                                </div>
                            </div>
                        </div>

                        ${currentInstrumentType === 'deed-of-surrender-release' && instType.toLowerCase().includes('mortgage') ? `
                        <!-- Action Button for Surrender/Release -->
                        <div class="pt-2">
                            <button type="button" class="btn-release-mortgage w-full py-2.5 bg-indigo-600 text-white rounded-xl text-[11px] font-bold hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 shadow-sm uppercase tracking-widest border border-indigo-500"
                                data-index="${index}">
                                <i data-lucide="check-circle" class="h-3.5 w-3.5"></i> Apply
                            </button>
                        </div>
                        ` : ''}
                    </div>
                `;

                // Add event listener for release button if created
                const releaseBtn = card.querySelector('.btn-release-mortgage');
                if (releaseBtn) {
                    releaseBtn.addEventListener('click', () => {
                        autoFillReleaseFromMortgage(record);
                        hidePraHistoryModal();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Mortgage Data Mapped',
                                text: 'Property details and parties have been updated for release.',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                        }
                    });
                }

                elements.praHistoryBody.appendChild(card);
            });
        }

        console.log('showPraHistoryModal: Showing modal now');
        elements.praHistoryModal.classList.remove('hidden');
        elements.praHistoryModal.style.display = 'flex'; // Use flex to match centering logic
        document.body.style.overflow = 'hidden';

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function autoFillReleaseFromMortgage(record) {
        console.log('autoFillReleaseFromMortgage: Mapping record:', record);

        // Map Parties (Surrenderor = Mortgagee, Surrenderee = Mortgagor)
        const mortgagor = record.party_1 || record.Mortgagor || '';
        const mortgagee = record.party_2 || record.Mortgagee || '';

        // Surrenderor/Releasor is the BANK (Party 2/Mortgagee)
        const firstPartyInput = document.getElementById('firstPartyName');
        if (firstPartyInput) firstPartyInput.value = mortgagee;

        // Surrenderee/Releasee is the OWNER (Party 1/Mortgagor)
        const secondPartyInput = document.getElementById('secondPartyName');
        if (secondPartyInput) secondPartyInput.value = mortgagor;

        // Map Particulars
        const serNo = record.serialNo || record.serial_no || '0';
        const pgNo = record.pageNo || record.page_no || record.reg_page_no || '0';
        const volNo = record.volumeNo || record.volume_no || '0';
        const particulars = `${serNo}/${pgNo}/${volNo}`;
        const originalInput = document.getElementById('originalInstrumentRegParticulars');
        if (originalInput) originalInput.value = particulars;

        // Map Bank Name
        const bankInput = document.getElementById('bankName');
        if (bankInput) bankInput.value = mortgagee;

        // Force a UI update for party labels/titles
        if (typeof updatePartyLabels === 'function') {
            updatePartyLabels(currentInstrumentType);
        }

        console.log('autoFillReleaseFromMortgage: Auto-fill complete.');
    }

    function hidePraHistoryModal() {
        console.log('hidePraHistoryModal: Closing modal');
        if (elements.praHistoryModal) {
            elements.praHistoryModal.classList.add('hidden');
            elements.praHistoryModal.style.display = 'none'; // Clear the inline block style
            document.body.style.overflow = ''; // Restore scrolling
        }
    }

    // Setup PRA History Event Listeners
    if (elements.viewPraHistoryBtn) {
        console.log('Attaching click listener to View History button');
        elements.viewPraHistoryBtn.addEventListener('click', function (e) {
            console.log('View History button clicked');
            showPraHistoryModal();
        });
    } else {
        console.error('View History button NOT found in elements cache');
    }

    if (elements.praHistoryCloseBtns) {
        elements.praHistoryCloseBtns.forEach(btn => {
            if (btn) btn.addEventListener('click', hidePraHistoryModal);
        });
    }

    // Close modal on clicking outside
    const praModal = document.getElementById('pra-history-modal');
    if (praModal) {
        praModal.addEventListener('click', (e) => {
            if (e.target === praModal) {
                hidePraHistoryModal();
            }
        });
    }

    // --- Land Use (Purpose) Logic ---

    const fileNoToLandUse = {
        // direct mappings
        'RES': 'Residential',
        'COM': 'Commercial',
        'IND': 'Industrial',
        'AG': 'Agriculture',
        // conversion mappings
        'CON-RES': 'Residential',
        'CON-COM': 'Commercial',
        'CON-IND': 'Industrial',
        'CON-AG': 'Agriculture',
        // Recertification mappings
        'RES-RC': 'Residential',
        'COM-RC': 'Commercial',
        'IND-RC': 'Industrial',
        'AG-RC': 'Agriculture',
        // CON-RECERTIFICATION mappings
        'CON-RES-RC': 'Residential',
        'CON-COM-RC': 'Commercial',
        'CON-IND-RC': 'Industrial',
        'CON-AG-RC': 'Agriculture',
        'CON-RES-RC': 'Residential',
        'CON-COM-RC': 'Commercial',
        'CON-IND-RC': 'Industrial',
        'CON-AG-RC': 'Agriculture'
    };

    async function fetchLandUses() {
        const select = elements.landUseIdSelect;
        if (!select) return;

        const initialId = document.getElementById('initial_land_use_id')?.value;

        try {
            const response = await fetch('/api/reference/land-uses');
            const result = await response.json();

            if (result.success) {
                select.innerHTML = '<option value="">Select Land Use</option>';
                result.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    option.dataset.name = item.name;
                    if (initialId && item.id == initialId) option.selected = true;
                    select.appendChild(option);
                });

                if (select.value) {
                    const initialPurposeId = document.getElementById('initial_purpose_id')?.value;
                    fetchPurposes(select.value, initialPurposeId);
                }
            }
        } catch (e) {
            console.error('Error fetching Land Uses:', e);
        }
    }

    async function fetchPurposes(landUseId, selectedPurposeId = null) {
        const select = elements.purposeIdSelect;
        if (!select) return;

        if (!landUseId) {
            select.innerHTML = '<option value="">Select Purpose</option>';
            select.disabled = true;
            return;
        }

        select.innerHTML = '<option value="">Loading...</option>';
        select.disabled = false;

        try {
            const response = await fetch(`/api/reference/purposes?landuseid=${landUseId}`);
            const result = await response.json();

            if (result.success) {
                select.innerHTML = '<option value="">Select Purpose</option>';
                result.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    option.dataset.name = item.name;
                    if (selectedPurposeId && (item.id == selectedPurposeId || item.name == selectedPurposeId)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                // If it was auto-selected and there's only one purpose or something? No, let user select.
                if (select.value) {
                    elements.purposeHidden.value = select.options[select.selectedIndex].dataset.name;
                }
            }
        } catch (e) {
            console.error('Error fetching Purposes:', e);
            select.innerHTML = '<option value="">Error loading</option>';
        }
    }

    function updateLandUseDisplay() {
        const landUseSelect = elements.landUseIdSelect;
        const landUseInput = elements.landUseHidden;
        const landUseDisplay = document.getElementById('land_use_display');

        if (!landUseSelect || !landUseInput) return;

        const fileNoRaw = elements.displayFileno?.value || '';
        const fileNo = fileNoRaw.trim();
        let detectedUse = 'Unknown';
        let colorClass = 'bg-gray-100 text-gray-800';

        if (fileNo) {
            const upperFileNo = fileNo.toUpperCase();
            console.log('Parsing Land Use for:', upperFileNo);

            const sortedKeys = Object.keys(fileNoToLandUse).sort((a, b) => b.length - a.length);

            let matchedKey = null;
            for (const key of sortedKeys) {
                if (upperFileNo.startsWith(key)) {
                    const nextChar = upperFileNo[key.length];
                    if (!nextChar || /[^A-Z0-9]/.test(nextChar)) {
                        matchedKey = key;
                        break;
                    }
                }
            }

            if (matchedKey) {
                detectedUse = fileNoToLandUse[matchedKey];

                // Auto-select in Land Use dropdown
                const options = Array.from(landUseSelect.options);
                const matchingOption = options.find(opt => opt.textContent.trim().toUpperCase() === detectedUse.toUpperCase());
                if (matchingOption && landUseSelect.value !== matchingOption.value) {
                    landUseSelect.value = matchingOption.value;
                    landUseSelect.dispatchEvent(new Event('change'));
                }

                if (detectedUse === 'Residential') colorClass = 'bg-green-100 text-green-800';
                else if (detectedUse === 'Commercial') colorClass = 'bg-blue-100 text-blue-800';
                else if (detectedUse === 'Industrial') colorClass = 'bg-purple-100 text-purple-800';
                else if (detectedUse === 'Agriculture') colorClass = 'bg-yellow-100 text-yellow-800';
            }
        }

        if (landUseDisplay) {
            landUseDisplay.textContent = detectedUse;
            landUseDisplay.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`;
            // If they want the span hidden now, we keep it hidden but update it
        }
    }

    // Attach listeners
    const displayFileno = document.getElementById('display_fileno');
    if (displayFileno) {
        const observer = new MutationObserver(() => {
            updateLandUseDisplay();
        });
        observer.observe(displayFileno, { attributes: true, attributeFilter: ['value'] });
        displayFileno.addEventListener('change', updateLandUseDisplay);
        displayFileno.addEventListener('input', updateLandUseDisplay);
    }

    // Listen for file selection from Global File Modal
    $(document).on('fileno-modal:applied', function (event, data) {
        console.log('File number applied from modal:', data);
        // Delay slightly to ensure DOM has updated
        setTimeout(updateLandUseDisplay, 100);
    });

    // Initial check (delay slightly to ensure values loaded)
    setTimeout(() => {
        fetchLandUses();
        updateLandUseDisplay();

        // Add listeners for new dropdowns
        if (elements.landUseIdSelect) {
            elements.landUseIdSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                elements.landUseHidden.value = selectedOption ? selectedOption.dataset.name : '';
                fetchPurposes(this.value);
            });
        }

        if (elements.purposeIdSelect) {
            elements.purposeIdSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                elements.purposeHidden.value = selectedOption ? selectedOption.dataset.name : '';
            });
        }
    }, 500);

    function toggleKanoFields(name) {
        const container = document.getElementById('kano-grantor-fields');
        const districtContainer = document.getElementById('district-container');
        const row1 = document.getElementById('first-party-row-1');

        if (!container) return;

        if (name && name.trim().toLowerCase() === 'kano state government') {
            container.classList.add('hidden');
            if (districtContainer) districtContainer.classList.add('hidden');
            if (row1) row1.classList.remove('grid-cols-2');
        } else {
            container.classList.remove('hidden');
            if (districtContainer) districtContainer.classList.remove('hidden');
            if (row1) row1.classList.add('grid-cols-2');
        }
    }

    // Attach listener for First Party Name
    const p1NameField = document.getElementById('firstPartyName');
    if (p1NameField) {
        p1NameField.addEventListener('input', function () {
            toggleKanoFields(this.value);
        });
        // Also listen for change and character keyups to be safe
        p1NameField.addEventListener('change', function () {
            toggleKanoFields(this.value);
        });
    }

});

// ─── RDS & CoR Action Handlers for Registration Success Modal ───
(function () {
    const cfg = () => window.InstrumentCaptureConfig || {};
    const urls = () => cfg().urls || {};
    const csrf = () => cfg().csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    function enableBtn(id) {
        const btn = document.getElementById(id);
        if (btn) { btn.disabled = false; btn.classList.remove('opacity-40', 'cursor-not-allowed'); }
    }
    function disableBtn(id) {
        const btn = document.getElementById(id);
        if (btn) { btn.disabled = true; btn.classList.add('opacity-40', 'cursor-not-allowed'); }
    }

    // Show a non-blocking toast inside the Swal popup without closing it
    function showInlineStatus(msg, type) {
        let el = document.getElementById('ic-action-status');
        if (!el) {
            const container = document.querySelector('.swal2-html-container');
            if (!container) return;
            el = document.createElement('div');
            el.id = 'ic-action-status';
            el.className = 'mt-3 text-center text-xs font-bold py-2 px-3 rounded-xl transition-all';
            container.appendChild(el);
        }
        const colors = { success: 'bg-emerald-50 text-emerald-700 border border-emerald-200', error: 'bg-red-50 text-red-700 border border-red-200', info: 'bg-blue-50 text-blue-700 border border-blue-200', loading: 'bg-gray-50 text-gray-500 border border-gray-200' };
        el.className = 'mt-3 text-center text-xs font-bold py-2 px-3 rounded-xl ' + (colors[type] || colors.info);
        el.textContent = msg;
        if (type !== 'loading') setTimeout(() => { if (el.textContent === msg) el.textContent = ''; el.className = 'mt-3'; }, 5000);
    }

    window._icGenerateRDS = function (deedRegId) {
        if (!deedRegId || deedRegId === 'deed_reg_null') return;
        const btn = event?.target?.closest('button');
        if (btn) { btn.disabled = true; btn.classList.add('opacity-40'); }
        showInlineStatus('Generating RDS…', 'loading');

        fetch(`${urls().generateRds}/${deedRegId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' }
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    showInlineStatus('✓ RDS generated successfully', 'success');
                    enableBtn('ic-print-rds-btn');
                    enableBtn('ic-generate-cor-btn');
                    if (btn) { btn.disabled = true; btn.classList.add('opacity-40', 'cursor-not-allowed'); }
                } else {
                    if (d.rds_id) { enableBtn('ic-print-rds-btn'); enableBtn('ic-generate-cor-btn'); }
                    showInlineStatus(d.error || 'RDS could not be generated.', d.rds_id ? 'info' : 'error');
                    if (btn && !d.rds_id) { btn.disabled = false; btn.classList.remove('opacity-40'); }
                    if (btn && d.rds_id) { btn.disabled = true; btn.classList.add('opacity-40', 'cursor-not-allowed'); }
                }
            })
            .catch(() => { showInlineStatus('Failed to generate RDS.', 'error'); if (btn) { btn.disabled = false; btn.classList.remove('opacity-40'); } });
    };

    window._icPrintRDS = function (deedRegId) {
        if (!deedRegId || deedRegId === 'deed_reg_null') return;
        window.open(`${urls().viewRds}/${deedRegId}`, '_blank');
    };

    window._icGenerateCoR = function (deedRegId) {
        if (!deedRegId || deedRegId === 'deed_reg_null') return;
        const btn = event?.target?.closest('button');
        if (btn) { btn.disabled = true; btn.classList.add('opacity-40'); }
        showInlineStatus('Generating CoR…', 'loading');

        fetch(`${urls().generateCor}/${deedRegId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' }
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    showInlineStatus('✓ CoR generated successfully', 'success');
                    enableBtn('ic-print-cor-btn');
                    if (btn) { btn.disabled = true; btn.classList.add('opacity-40', 'cursor-not-allowed'); }
                } else {
                    if (d.cor_exists) { enableBtn('ic-print-cor-btn'); }
                    showInlineStatus(d.error || 'CoR could not be generated.', d.cor_exists ? 'info' : 'error');
                    if (btn && !d.cor_exists) { btn.disabled = false; btn.classList.remove('opacity-40'); }
                    if (btn && d.cor_exists) { btn.disabled = true; btn.classList.add('opacity-40', 'cursor-not-allowed'); }
                }
            })
            .catch(() => { showInlineStatus('Failed to generate CoR.', 'error'); if (btn) { btn.disabled = false; btn.classList.remove('opacity-40'); } });
    };

    window._icPrintCoR = function (deedRegId) {
        if (!deedRegId || deedRegId === 'deed_reg_null') return;
        const viewUrl = `${urls().viewCor}?id=${deedRegId}`;
        window.open(viewUrl, '_blank');
    };
})();

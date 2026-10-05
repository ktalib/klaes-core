(function (window, document) {
    const INSTRUMENT_TYPE_OPTIONS = ['Assignment', 'Surrender', 'Subdivision', 'Merger', 'Withdrawn', 'Revoke'];

    let cachedInstrumentTypes = null;
    let instrumentTypeRequest = null;

    async function requestInstrumentTypes(url) {
        if (!url) {
            return [];
        }

        if (cachedInstrumentTypes) {
            return cachedInstrumentTypes;
        }

        if (!instrumentTypeRequest) {
            instrumentTypeRequest = fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Instrument type lookup failed (${response.status})`);
                    }
                    return response.json();
                })
                .then((payload) => {
                    const list = Array.isArray(payload)
                        ? payload
                        : Array.isArray(payload?.data)
                            ? payload.data
                            : [];
                    cachedInstrumentTypes = list;
                    return list;
                })
                .catch((error) => {
                    console.warn('Failed to retrieve instrument types', error);
                    throw error;
                })
                .finally(() => {
                    instrumentTypeRequest = null;
                });
        }

        return instrumentTypeRequest;
    }

    const INSTRUMENT_PARTY_MAP = {
        Assignment: { from: 'Assignor', to: 'Assignee' },
        Surrender: { from: 'Surrenderor', to: 'Surrenderee (Kano State Govt.)' },
        Subdivision: { from: 'Parent Title Holder', to: 'Subdivided Owner(s)' },
        Merger: { from: 'Merging Owner', to: 'Resultant Owner' },
        Withdrawn: { from: 'Kano State Govt.', to: 'Original Owner' },
        Revoke: { from: 'Kano State Govt.', to: 'Original Owner' }
    };

    const INSTRUMENT_COLOR_MAP = {
        Assignment: { border: 'border-blue-200', bg: 'bg-blue-50', tag: 'bg-blue-100 text-blue-900' },
        Surrender: { border: 'border-amber-200', bg: 'bg-amber-50', tag: 'bg-amber-100 text-amber-900' },
        Subdivision: { border: 'border-emerald-200', bg: 'bg-emerald-50', tag: 'bg-emerald-100 text-emerald-900' },
        Merger: { border: 'border-purple-200', bg: 'bg-purple-50', tag: 'bg-purple-100 text-purple-900' },
        Withdrawn: { border: 'border-rose-200', bg: 'bg-rose-50', tag: 'bg-rose-100 text-rose-900' },
        Revoke: { border: 'border-red-200', bg: 'bg-red-50', tag: 'bg-red-100 text-red-900' },
        default: { border: 'border-gray-200', bg: 'bg-gray-50', tag: 'bg-gray-100 text-gray-800' }
    };

    const TRANSACTION_PARTY_LABELS = {
        'Power of Attorney': { first: 'Grantor', second: 'Grantee' },
        'Deed of Assignment': { first: 'Assignor', second: 'Assignee' },
        'ST Assignment': { first: 'Assignor', second: 'Assignee' },
        'Deed of Mortgage': { first: 'Mortgagor', second: 'Mortgagee', third: 'Co-mortgagor', fourth: 'Fourth Party' },
        'Tripartite Mortgage': { first: 'Mortgagor', second: 'Mortgagee', third: 'Co-mortgagor', fourth: 'Fourth Party' },
        'Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'ST Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'SLTR Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Customary Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Deed of Transfer': { first: 'Transferor', second: 'Transferee' },
        'Deed of Gift': { first: 'Donor', second: 'Donee' },
        'Deed of Lease': { first: 'Lessor', second: 'Lessee' },
        'Deed of Sub Lease': { first: 'Lessor', second: 'Lessee' },
        'Deed of Sub Under Lease': { first: 'Lessor', second: 'Lessee' },
        'Indenture of Lease': { first: 'Lessor', second: 'Lessee' },
        'Quarry Lease': { first: 'Lessor', second: 'Lessee' },
        'Private Lease': { first: 'Lessor', second: 'Lessee' },
        'Building Lease': { first: 'Lessor', second: 'Lessee' },
        'Tenancy Agreement': { first: 'Landlord', second: 'Tenant' },
        'Deed of Surrender and Release': { first: 'Surrenderor', second: 'Surrenderee', third: 'Co-Surrenderor' },
        'Letter of Administration': { first: 'Administrator', second: 'Beneficiary' },
        'Certificate of Purchase': { first: 'Vendor', second: 'Purchaser' }
    };

    const FIRST_PARTY_FIELD_MAP = {
        'Deed of Assignment': 'Assignor',
        'ST Assignment': 'Assignor',
        'Deed of Mortgage': 'Mortgagor',
        'Tripartite Mortgage': 'Mortgagor',
        'Deed of Surrender and Release': 'Surrenderor',
        'Deed of Sub Lease': 'Lessor',
        'Deed of Sub Under Lease': 'Lessor',
        'Indenture of Lease': 'Lessor',
        'Quarry Lease': 'Lessor',
        'Private Lease': 'Lessor',
        'Building Lease': 'Lessor',
        'Tenancy Agreement': 'Landlord',
        'Deed of Transfer': 'Transferor',
        'Deed of Gift': 'Donor',
        'Letter of Administration': 'Administrator',
        'Certificate of Purchase': 'Vendor'
    };

    const SECOND_PARTY_FIELD_MAP = {
        'Deed of Assignment': 'Assignee',
        'ST Assignment': 'Assignee',
        'Deed of Mortgage': 'Mortgagee',
        'Tripartite Mortgage': 'Mortgagee',
        'Deed of Surrender and Release': 'Surrenderee',
        'Deed of Sub Lease': 'Lessee',
        'Deed of Sub Under Lease': 'Lessee',
        'Indenture of Lease': 'Lessee',
        'Quarry Lease': 'Lessee',
        'Private Lease': 'Lessee',
        'Building Lease': 'Lessee',
        'Tenancy Agreement': 'Tenant',
        'Deed of Transfer': 'Transferee',
        'Deed of Gift': 'Donee',
        'Letter of Administration': 'Beneficiary',
        'Certificate of Purchase': 'Purchaser'
    };

    const THIRD_PARTY_FIELD_MAP = {
        'Deed of Mortgage': 'Mortgagor_2',
        'Tripartite Mortgage': 'Mortgagor_2',
        'Deed of Surrender and Release': 'Surrenderor_2'
    };

    // Historical Deed of Purchase Register capture. The register names its sides
    // plainly, so the card asks for "Party 1" / "Party 2" rather than the
    // Grantor / Grantee wording every other instrument falls back to.
    const PURCHASE_PARTY_LABELS = { first: 'Party 1', second: 'Party 2' };

    /**
     * Matched on the substring, not the catalogue key, so the legacy spellings
     * in the lookup table ("Deed of Purchase", "Deed of Purchase Registration")
     * all reach the same rules -- the same way the Payment/Receipt fields match.
     */
    function isDeedOfPurchaseType(transactionType) {
        return String(transactionType || '').toLowerCase().includes('deed of purchase');
    }

    /**
     * Party labels for an instrument type, with the caller's own fallback for
     * types the table does not name.
     */
    function resolvePartyLabels(transactionType, fallback) {
        if (isDeedOfPurchaseType(transactionType)) {
            return Object.assign({}, PURCHASE_PARTY_LABELS);
        }

        return TRANSACTION_PARTY_LABELS[transactionType] || fallback;
    }

    /**
     * Rename a party label without destroying the rest of it -- the label
     * carries a required marker beside the name, and writing textContent on the
     * label itself takes that marker with it.
     */
    function setPartyLabelText(labelElement, text) {
        if (!labelElement || !text) return;

        const span = labelElement.querySelector('[data-role="label-text"]');
        if (span) {
            span.textContent = text;
        } else {
            labelElement.textContent = text;
        }
    }

    const GOVERNMENT_TRANSACTION_TYPES = [
        'Certificate of Occupancy',
        'ST Certificate of Occupancy',
        'SLTR Certificate of Occupancy',
        'Customary Right of Occupancy',
        'Right of Occupancy',
        'Occupation Permit',
        'Occupancy Permit',
        'Occupancy Permit (OP)',
        'OP'
    ];

    const ALL_COLOR_CLASSES = Array.from(
        new Set(
            Object.values(INSTRUMENT_COLOR_MAP).flatMap((colors) => {
                const tagClasses = typeof colors.tag === 'string' ? colors.tag.split(' ') : [];
                return [colors.border, colors.bg, ...tagClasses];
            })
        )
    );

    function normalizeText(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).trim();
    }

    /** Values in the existing-records table come straight from the register — escape them. */
    function escapeExistingHtml(value) {
        return normalizeText(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char]));
    }

    class PraFormController {
        constructor(container) {
            this.container = container;
            this.form = container.querySelector('#property-record-form');
            this.dialog = document.getElementById('property-form-dialog');
            this.modelElements = new Map();
            this.optionalBadges = Array.from(container.querySelectorAll('[data-visible-when="property"]'));
            this.indexModeElements = Array.from(container.querySelectorAll('[data-index-class]'));
            this.transactionDetailsSection = container.querySelector('[data-section="transaction-details"]');
            this.partyLabelFirst = container.querySelector('[data-role="party-label-first"]');
            this.partyLabelSecond = container.querySelector('[data-role="party-label-second"]');
            this.partyLabelThird = container.querySelector('[data-role="party-label-third"]');
            this.partyLabelFourth = container.querySelector('[data-role="party-label-fourth"]');
            // The "*" beside Party 1 and the note under it -- both answer to the
            // Deed of Purchase rule that Party 1 may be left blank.
            this.partyRequiredFirst = container.querySelector('[data-role="party-required-first"]');
            this.purchasePartyHint = container.querySelector('[data-role="purchase-party-hint"]');
            this.partyInputFirst = container.querySelector('[data-name-key="firstParty"]');
            this.partyInputSecond = container.querySelector('[data-name-key="secondParty"]');
            this.partyInputThird = container.querySelector('[data-name-key="thirdParty"]');
            this.partyInputFourth = container.querySelector('[data-name-key="fourthParty"]');
            this.partyThirdWrapper = container.querySelector('[data-role="party-third-wrapper"]');
            this.partyFourthWrapper = container.querySelector('[data-role="party-fourth-wrapper"]');
            this.tripartiteToggleWrapper = container.querySelector('[data-role="tripartite-toggle"]');
            this.fourthPartyToggleWrapper = container.querySelector('[data-role="fourth-party-toggle"]');
            this.tripartiteToggleLabelText = container.querySelector('[data-role="tripartite-toggle-label"]');
            this.instrumentEmptyMessage = container.querySelector('[data-role="instrument-empty"]');
            this.instrumentList = container.querySelector('[data-role="instrument-list"]');
            this.instrumentTemplate = container.querySelector('#pra-instrument-entry-template');
            this.fileNumberMessage = container.querySelector('[data-role="no-file-number-copy"]');
            this.matchingRecordsContainer = container.querySelector('[data-role="matching-records-container"]');
            this.matchingRecordsResults = container.querySelector('[data-role="matching-records-results"]');
            // "Existing Records for this File" table + duplicate flag.
            this.existingRecordsSection = container.querySelector('[data-role="existing-records-section"]');
            this.existingRecordsBody = container.querySelector('[data-role="existing-records-body"]');
            this.existingRecordsCount = container.querySelector('[data-role="existing-records-count"]');
            this.existingRecordsLoading = container.querySelector('[data-role="existing-records-loading"]');
            this.duplicateWarning = container.querySelector('[data-role="duplicate-warning"]');
            // Deed of Purchase only: the "Use as Party 1" column in the existing
            // records table, and the note that explains it.
            this.existingRecordsPickerNote = container.querySelector('[data-role="existing-records-picker-note"]');
            this.existingRecordsUrl = container.dataset.existingRecordsUrl || '';
            this.existingRecords = [];
            // Set once the operator confirms "Add anyway" on an exact duplicate;
            // cleared again as soon as the entry changes.
            this.duplicateOverride = false;
            this.smartWrapper = container.querySelector('[data-role="smart-wrapper"]');
            this.tempStatus = document.getElementById('modal-temp-status');
            this.tempValue = document.getElementById('modal-temp-value');
            this.tempError = document.getElementById('modal-temp-error');
            this.toggleTempCheckbox = document.getElementById('modal-no-file-number-toggle');
            this.refreshTempButton = document.getElementById('modal-refresh-temp');
            this.tempWrapper = document.getElementById('modal-temp-wrapper');
            this.modalMlsMethodToggle = document.getElementById('modal-mls-method-toggle');
            this.modalMlsInputSections = Array.from(document.querySelectorAll('.mls-input-section'));
            this.tempEndpoint = container.dataset.tempEndpoint || null;
            this.instrumentTypesUrl = container.dataset.instrumentTypesUrl || '';
            this.descriptionTextarea = container.querySelector('[data-model="propertyDescription"]');
            this.regPreviewBox = container.querySelector('[data-role="reg-preview"]');
            this.regPreviewValue = container.querySelector('[data-role="reg-preview-value"]');
            this.streetComponent = container.querySelector('[data-component="street-name"]');
            this.districtComponent = container.querySelector('[data-component="district-name"]');
            this.refreshDescriptionButton = container.querySelector('[data-action="refresh-description"]');
            this.addInstrumentButton = container.querySelector('[data-action="add-instrument"]');
            this.serialSynchronizers = Array.from(container.querySelectorAll('[data-sync]'));
            this.instrumentEntries = [];
            this.initialising = true;
            this.submitButton = container.querySelector('#property-submit-btn');
            this.submitButtonDefaultLabel = this.submitButton ? (this.submitButton.dataset.defaultLabel || this.submitButton.textContent.trim()) : 'Submit';
            this.updateBanner = container.querySelector('[data-role="update-banner"]');
            this.updateBannerLabel = container.querySelector('[data-role="update-label"]');
            this.formActionOriginal = this.form ? this.form.getAttribute('action') : '';
            this.updateBaseUrl = this.form ? (this.form.dataset.updateBase || '') : '';
            this.methodOverrideInput = this.form ? this.form.querySelector('input[name="_method"]') : null;
            this.propertyIdInput = this.form ? this.form.querySelector('#property_id') : null;
            this.formActionInput = this.form ? this.form.querySelector('#form_action') : null;
            this.csrfToken = this.resolveCsrfToken();
            this.isSubmitting = false;
            // Raised while this controller re-fires an element's own input/change
            // event, so its model binding does not treat the echo as a fresh edit.
            this.suppressElementEvents = false;
            this.pendingUpdatePrompt = false;
            this.instrumentTypeOptions = [];
            this.transactionTypeSelects = Array.from(container.querySelectorAll('select[data-role="transaction-type"]'));
            this.transactionTypeSelects.forEach((select) => {
                if (!select.dataset.placeholder) {
                    select.dataset.placeholder = select.dataset.placeholder || 'Select type';
                }
                if (!select.dataset.initialValue) {
                    select.dataset.initialValue = select.value || '';
                }
            });
            this.opFieldsWrapper = container.querySelector('[data-role="op-fields-wrapper"]');
            // Payment Amount + Receipt No, shown only for a Deed of Purchase.
            this.purchaseFieldsWrapper = container.querySelector('[data-role="purchase-fields-wrapper"]');
            this.cofoFieldsWrapper = container.querySelector('[data-role="cofo-fields-wrapper"]');
            // Grantor picker + hint shown only for an OP issued by a Local Government.
            this.opLgaGrantorSelect = container.querySelector('[data-role="op-lga-grantor"]');
            this.opLgaNote = container.querySelector('[data-role="op-lga-note"]');
            // OP Category (Old OP / New OP) and its note, shown only for the two
            // State-granted OP types.
            // The shared Type / Category pair, whose options come from the catalogue.
            this.instrumentTypeGroup = container.querySelector('[data-role="instrument-type-group"]');
            this.instrumentCategoryGroup = container.querySelector('[data-role="instrument-category-group"]');
            this.instrumentTypeSelect = container.querySelector('#instrument_type_value');
            this.instrumentCategorySelect = container.querySelector('#instrument_category');
            this.instrumentOldNote = container.querySelector('[data-role="instrument-old-note"]');
            // Shown only while Instrument Type is 'Other': the field that names the paper.
            this.otherTransactionTypeGroup = container.querySelector('[data-role="other-transaction-type-group"]');
            this.otherTransactionTypeInput = container.querySelector('#other_transaction_type');
            this.state = {
                formMode: 'property',
                transactionType: '',
                firstParty: '',
                secondParty: '',
                thirdParty: '',
                fourthParty: '',
                party1: '',
                party2: '',
                party3: '',
                party4: '',
                serialNo: '',
                pageNo: '',
                volumeNo: '',
                landUse: '',
                propertyDescription: '',
                houseNo: '',
                plotNo: '',
                tpNo: '',
                lpknNo: '',
                approvedPlanNo: '',
                plotSize: '',
                scale: '',
                dateRecommended: '',
                dateApproved: '',
                leaseBegins: '',
                leaseExpires: '',
                metricSheet: '',
                streetName: '',
                currentStreetName: '',
                district: '',
                currentDistrict: '',
                lga: '',
                state: '',
                fileNumber: '',
                isMapped: false,
                hasOfficialFileNumber: true,
                showManualEntry: false,
                transactionDate: '',
                regDate: '',
                regTime: '',
                deedsDate: '',
                deedsTime: '',
                opType: '',
                // The one visible Type answer; copied into opType / cofoType /
                // instrumentSubtype by applyInstrumentCatalogRules().
                typeValue: '',
                instrumentSubtype: '',
                instrumentCategory: '',
                opSerialNumber: '',
                considerationAmount: '',
                receiptNo: '',
                cofoType: '',
                tempFileNumber: '',
                tempFileNumberLoading: false,
                tempFileNumberError: '',
                period: '',
                periodUnit: 'Years',
                tripartiteHasThird: false,
                isUpdateMode: false,
                updateRecordId: null
            };
            this.publicApi = this.buildPublicApi();
            container._x_dataStack = [this.publicApi];
        }

        buildPublicApi() {
            const api = {};
            const exposedKeys = [
                'formMode',
                'transactionType',
                'firstParty',
                'secondParty',
                'thirdParty',
                'fourthParty',
                'serialNo',
                'pageNo',
                'volumeNo',
                'landUse',
                'propertyDescription',
                'houseNo',
                'plotNo',
                'tpNo',
                'lpknNo',
                'approvedPlanNo',
                'plotSize',
                'scale',
                'dateRecommended',
                'dateApproved',
                'leaseBegins',
                'leaseExpires',
                'metricSheet',
                'streetName',
                'currentStreetName',
                'district',
                'currentDistrict',
                'lga',
                'state',
                'fileNumber',
                'hasOfficialFileNumber',
                'showManualEntry',
                'transactionDate',
                'regDate',
                'regTime',
                'deedsDate',
                'deedsTime',
                'opType',
                'typeValue',
                'instrumentSubtype',
                'instrumentCategory',
                'opSerialNumber',
                'considerationAmount',
                'receiptNo',
                'cofoType',
                'tempFileNumber',
                'tempFileNumberLoading',
                'tempFileNumberError',
                'period',
                'periodUnit',
                'tripartiteHasThird',
                'isMapped',
                'isUpdateMode',
                'updateRecordId',
                'party1',
                'party2',
                'party3',
                'party4',
                'hasFourthParty'
            ];

            exposedKeys.forEach((key) => {
                Object.defineProperty(api, key, {
                    enumerable: true,
                    get: () => this.state[key],
                    set: (value) => this.setState(key, value)
                });
            });

            Object.defineProperty(api, 'instrumentEntries', {
                enumerable: true,
                get: () => this.instrumentEntries
            });

            api.$nextTick = (callback) => {
                if (typeof callback === 'function') {
                    window.requestAnimationFrame(() => callback());
                }
            };

            api.updatePropertyDescription = () => this.updatePropertyDescription();
            api.addInstrumentEntry = () => this.addInstrumentEntry();
            api.removeInstrumentEntry = (index) => this.removeInstrumentEntry(index);
            api.requestTempFileNumber = (force) => this.fetchTempFileNumber(Boolean(force));
            api.resetUpdateMode = () => this.exitUpdateMode();
            api.resetForm = () => this.resetForm();

            return api;
        }

        init() {
            this.bindModelElements();
            this.bindOpLgaGrantor();
            if (!this.state.formMode) {
                this.state.formMode = 'property';
            }
            this.bindRefreshButton();
            this.bindStreetComponent();
            this.bindDistrictComponent();
            this.bindFileNumberControls();
            this.bindPropertyDescriptionRefresh();
            this.bindInstrumentControls();
            this.bindRegNumberSynchronisers();
            this.bindSmartSelectorWatcher();
            this.bindUpdateModeHandlers();
            this.bindDuplicateWatchers();
            if (this.state.transactionType === 'Tripartite Mortgage' && normalizeText(this.state.thirdParty).length > 0) {
                this.state.tripartiteHasThird = true;
                const tripartiteCheckbox = this.modelElements.get('tripartiteHasThird');
                if (tripartiteCheckbox) {
                    tripartiteCheckbox.checked = true;
                }
                this.syncModelElement('tripartiteHasThird', true);
            }
            this.initialising = false;
            this.syncAllState();
            this.updateTransactionContext();
            this.updatePropertyDescription();
            this.updateRegPreview();
            this.syncFileNumberSections();
            this.updateTempStatus();
            this.loadInstrumentTypeOptions();
        }

        bindModelElements() {
            const elements = this.container.querySelectorAll('[data-model]');
            elements.forEach((element) => {
                const modelKey = element.dataset.model;
                if (!modelKey) {
                    return;
                }

                if (!element.dataset.defaultClass) {
                    element.dataset.defaultClass = element.className || '';
                }

                if (!element.dataset.placeholderDefault) {
                    element.dataset.placeholderDefault = element.getAttribute('placeholder') || '';
                }

                this.modelElements.set(modelKey, element);

                const initialValue = this.extractElementValue(element, modelKey);
                this.state[modelKey] = initialValue;

                const eventName = element.tagName === 'SELECT' ? 'change' : 'input';
                element.addEventListener(eventName, () => {
                    if (this.suppressElementEvents) return;
                    const nextValue = this.extractElementValue(element, modelKey);
                    this.setState(modelKey, nextValue, { source: 'element' });
                });
            });
        }

        /**
         * An Occupancy Permit issued by a Local Government rather than by the State.
         * Some conversion and direct-allocation files carry these; an LGA registers nothing
         * in the state deeds registry, so such a permit has no serial, page, volume, date or
         * time — only which Local Government granted it.
         */
        /**
         * ANY instrument whose chosen Type is LGA was issued by a Local Government, so
         * party 1 is which Local Government - a Plot Allocation Letter, a Certificate of
         * Occupancy and a Right of Occupancy just as much as an Occupancy Permit.
         *
         * Recognised by the catalogue LABEL, not the stored value: the instruments spell
         * it differently ('LGA', 'LGA CofO'), and an LGA option added to the catalogue
         * tomorrow is picked up here without a second edit.
         */
        isLgaType() {
            const value = normalizeText(this.state.typeValue);
            if (!value) return false;

            const types = (this.catalogEntry() || {}).types || [];

            return types.some(
                (o) => o.value === value && String(o.label).trim().toUpperCase() === 'LGA'
            );
        }

        /**
         * The narrower case: an Occupancy Permit granted by a Local Government. The 0/0/0
         * registration stamp is the PERMIT's rule, not a rule about being LGA-issued, so
         * it stays keyed to this rather than to isLgaType().
         */
        isLgaOpType() {
            return this.typeColumn() === 'opType' && this.isLgaType();
        }

        /** The 44 authority names, read off the picker so this file holds no second copy. */
        kanoLgaAuthorities() {
            if (!this.opLgaGrantorSelect) {
                return [];
            }
            return Array.from(this.opLgaGrantorSelect.options)
                .map((option) => option.value)
                .filter((value) => value !== '');
        }

        bindOpLgaGrantor() {
            if (!this.opLgaGrantorSelect) {
                return;
            }
            // The picker is a stand-in for the first-party input, so it writes the same state key.
            this.opLgaGrantorSelect.addEventListener('change', () => {
                this.setState('firstParty', this.opLgaGrantorSelect.value, { source: 'element' });
            });
        }

        /**
         * Swap the grantor control and fix (or release) the registration particulars to match
         * the current OP Type. Safe to call on every relevant change — it is idempotent.
         */
        applyLgaOpTypeRules() {
            // The grantor picker follows the BROAD rule: anything LGA-issued gets it.
            const isLgaIssued = this.isLgaType();
            // The 0/0/0 stamp follows the narrow one: only a permit is stamped.
            const isLga = this.isLgaOpType();
            const authorities = this.kanoLgaAuthorities();

            if (this.opLgaNote) {
                // The note names the 0/0/0 rule, so it belongs to the permit case only.
                this.opLgaNote.classList.toggle('hidden', !isLga);
            }
            if (this.opLgaGrantorSelect) {
                this.opLgaGrantorSelect.classList.toggle('hidden', !isLgaIssued);
                // Which Local Government issued it is the one particular such an instrument
                // must give, whatever kind of instrument it happens to be.
                this.opLgaGrantorSelect.required = isLgaIssued;
            }
            if (this.partyInputFirst) {
                this.partyInputFirst.classList.toggle('hidden', isLgaIssued);
                // Whether Party 1 blocks the form is decided in one place — a hidden
                // input must not hold the form back, but neither must a Deed of
                // Purchase, and this pass is not the only one with a say.
                this.applyFirstPartyRequirement();
            }

            // Party 1 is the council on EVERY LGA-issued instrument, so this runs on the
            // broad rule even when the permit-only branch below does not.
            if (isLgaIssued && this.opLgaGrantorSelect) {
                const chosen = normalizeText(this.state.firstParty);
                if (chosen && !authorities.includes(chosen)
                    && !Array.from(this.opLgaGrantorSelect.options).some((o) => o.value === chosen)) {
                    // A hand-typed grantor from an older row stays selectable.
                    const option = document.createElement('option');
                    option.value = chosen;
                    option.textContent = chosen;
                    this.opLgaGrantorSelect.appendChild(option);
                }
                if (chosen === 'KANO STATE GOVERNMENT') {
                    // The State is not one of the 44 - make the officer choose.
                    this.setState('firstParty', '');
                    this.opLgaGrantorSelect.value = '';
                } else {
                    this.opLgaGrantorSelect.value = chosen;
                }
            }

            const regFields = ['serialNo', 'pageNo', 'volumeNo'];
            const dateFields = ['deedsDate', 'deedsTime'];

            if (isLga) {
                // Registration number 0/0/0, no date, no time — nothing was ever registered.
                regFields.forEach((key) => {
                    this.setState(key, '0', { silent: true });
                    this.syncModelElement(key, '0');
                });
                dateFields.forEach((key) => {
                    this.setState(key, '', { silent: true });
                    this.syncModelElement(key, '');
                });
                this.updateRegPreview();

                this.setRegParticularsLocked(true);
                return;
            }

            // Not (or no longer) an LGA permit: release the fields and drop the 0/0/0 stamp.
            this.setRegParticularsLocked(false);
            const stamped = regFields.every((key) => normalizeText(this.state[key]) === '0');
            if (stamped) {
                regFields.forEach((key) => {
                    this.setState(key, '', { silent: true });
                    this.syncModelElement(key, '');
                });
                this.updateRegPreview();
            }
            if (!isLgaIssued && authorities.includes(normalizeText(this.state.firstParty))) {
                const isGovernment = GOVERNMENT_TRANSACTION_TYPES.includes(this.state.transactionType);
                this.setState('firstParty', isGovernment ? 'KANO STATE GOVERNMENT' : '');
            }
        }

        /**
         * The Type and Category a captured instrument can carry, as emitted once by
         * App\Services\InstrumentTypeCatalog into a <script type="application/json">
         * beside the fields. Read lazily and cached, so this file holds no second copy
         * of that table and cannot drift from the PHP one.
         */
        instrumentCatalog() {
            if (this._instrumentCatalog) return this._instrumentCatalog;

            const node = this.container.querySelector('[data-role="instrument-catalog"]');
            try {
                this._instrumentCatalog = node ? JSON.parse(node.textContent || '[]') : [];
            } catch (err) {
                console.warn('Instrument catalogue did not parse', err);
                this._instrumentCatalog = [];
            }

            return this._instrumentCatalog;
        }

        /** Matched the way the PHP side matches: normalised, key as a substring. */
        catalogEntry() {
            const needle = normalizeText(this.state.transactionType)
                .replace(/\s+/g, ' ').toLowerCase();
            if (!needle) return null;

            return this.instrumentCatalog().find((e) => needle.indexOf(e.key) !== -1) || null;
        }

        /** Which column the one Type field is stored in for the current instrument. */
        typeColumn() {
            const type = normalizeText(this.state.transactionType).toLowerCase();
            if (type.includes('occupancy permit')) return 'opType';
            if (type.includes('certificate of occupancy')) return 'cofoType';
            return 'instrumentSubtype';
        }

        /** Category 'Old' on a permit: the particulars predate the registry practice. */
        isOldCategory() {
            if (this.typeColumn() !== 'opType') return false;
            return normalizeText(this.state.instrumentCategory).toUpperCase() === 'OLD';
        }

        /** Fill a select from catalogue options, keeping the current value if still valid. */
        fillCatalogSelect(select, options, placeholder, current) {
            if (!select) return '';

            const keep = options.some((o) => o.value === current) ? current : '';

            select.innerHTML = '';
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = placeholder;
            select.appendChild(blank);

            options.forEach((o) => {
                const el = document.createElement('option');
                el.value = o.value;
                el.textContent = o.label;
                select.appendChild(el);
            });

            // A stored value the catalogue no longer lists stays selectable, so loading an
            // old record cannot silently blank it.
            if (current && keep === '') {
                const legacy = document.createElement('option');
                legacy.value = current;
                legacy.textContent = current + ' (legacy)';
                select.appendChild(legacy);
            }

            select.value = current || '';
            return select.value;
        }

        /**
         * Swap the Type and Category options to the current instrument's, hide either
         * field when that instrument has none, and copy the Type answer into the column
         * it is actually stored in so the OP and CofO rules keep working off the field
         * they have always read.
         */
        applyInstrumentCatalogRules() {
            const entry = this.catalogEntry();
            const types = (entry && entry.types) || [];
            const categories = (entry && entry.categories) || [];

            if (this.instrumentTypeGroup) {
                this.instrumentTypeGroup.classList.toggle('hidden', types.length === 0);
            }
            if (this.instrumentCategoryGroup) {
                this.instrumentCategoryGroup.classList.toggle('hidden', categories.length === 0);
            }

            const typeValue = this.fillCatalogSelect(
                this.instrumentTypeSelect, types, 'Select type', normalizeText(this.state.typeValue)
            );
            const categoryValue = this.fillCatalogSelect(
                this.instrumentCategorySelect, categories, 'Select category',
                normalizeText(this.state.instrumentCategory)
            );

            if (normalizeText(this.state.typeValue) !== typeValue) {
                this.state.typeValue = typeValue;
            }
            if (normalizeText(this.state.instrumentCategory) !== categoryValue) {
                this.state.instrumentCategory = categoryValue;
            }

            // The Type answer goes to the column this instrument stores it in, and the
            // other two are emptied so a stale one cannot ride along on the save.
            const column = this.typeColumn();
            ['opType', 'cofoType', 'instrumentSubtype'].forEach((key) => {
                const want = key === column ? typeValue : '';
                if (normalizeText(this.state[key]) !== want) {
                    this.state[key] = want;
                    this.syncModelElement(key, want);
                }
            });

            if (this.instrumentOldNote) {
                this.instrumentOldNote.classList.toggle('hidden', !this.isOldCategory());
            }

            // The extra registration-number rows carry their own Type, Category and OP
            // Serial Number, rendered by instrument_section.blade.php off this same
            // catalogue. A programmatic instrument change fires no 'change' event, so
            // this is the only way those rows hear about one.
            this.container.dispatchEvent(new CustomEvent('pra:instrument-fields-changed', {
                bubbles: true,
                detail: {
                    transactionType: this.state.transactionType || '',
                    types,
                    categories,
                    typeColumn: this.typeColumn(),
                },
            }));
        }



        /** Grey out and unlock the registration particulars together. */
        setRegParticularsLocked(locked) {
            ['serialNo', 'pageNo', 'volumeNo', 'deedsDate', 'deedsTime'].forEach((key) => {
                const element = this.modelElements.get(key);
                if (!element) {
                    return;
                }
                // pageNo is always mirrored from serialNo, so it stays read-only regardless.
                if (key !== 'pageNo') {
                    element.readOnly = locked;
                }
                element.required = false;
                element.classList.toggle('bg-gray-100', locked);
                element.classList.toggle('cursor-not-allowed', locked);
            });
        }

        extractElementValue(element, key) {
            if (!element) {
                return '';
            }

            if (element.type === 'checkbox') {
                return element.checked;
            }

            if (key === 'hasOfficialFileNumber') {
                return element.value === '1' || element.value === 'true';
            }

            return element.value || '';
        }

        setState(key, value, options = {}) {
            if (key === 'hasOfficialFileNumber') {
                value = Boolean(value);
            }

            if (this.state[key] === value) {
                return;
            }

            this.state[key] = value;

            if (this.initialising || options.silent) {
                return;
            }

            this.onStateChange(key, value, options);
        }

        onStateChange(key, value, options = {}) {
            switch (key) {
                case 'formMode':
                    this.applyFormMode(value);
                    break;
                case 'transactionType':
                    this.syncModelElement(key, value);
                    this.syncTransactionTypeSelects(value);
                    this.updateTransactionContext();
                    // When Surrender and Release is selected, re-check mortgages for current file number
                    if (String(value).toLowerCase() === 'deed of surrender and release') {
                        const fileno = normalizeText(this.state.fileNumber || this.state.fileno || '');
                        if (fileno.length > 0) {
                            this.checkFilenoForMortgages(fileno);
                        }
                    } else {
                        // clear mapping when not a surrender/release
                        this.setState('isMapped', false);
                    }
                    break;
                case 'fileNumber':
                    // Always check for mortgages/surrender records when file number changes
                    // pass true to enable auto-selection of transaction type
                    const fileno = normalizeText(value);
                    if (fileno.length > 0) {
                        this.fetchAndDisplayMatchingRecords(fileno);
                        // Wider view than the PRA-only card above: everything the
                        // file holds across PRA/FH/Deeds/CofO, so a duplicate is
                        // visible before it is saved.
                        this.loadExistingRecordsTable(fileno);
                        // Backfill the shared property details (location, plot,
                        // district, land use, etc.) from any existing record on
                        // this file so selecting a file number prefills the form
                        // here just like it does on the instrument-capture page.
                        // Skip in update mode so a loaded record is not clobbered,
                        // and skip the silent re-dispatch used while populating.
                        if (!this.state.isUpdateMode && !options.silent) {
                            this.autoBackfillPropertyDetailsFromFile(fileno);
                        }
                    } else {
                        if (this.matchingRecordsContainer) {
                            this.matchingRecordsContainer.classList.add('hidden');
                        }
                        this.clearExistingRecordsTable();
                    }
                    this.syncModelElement(key, value);
                    break;
                case 'tripartiteHasThird':
                    this.syncModelElement(key, value);
                    this.updateTripartiteControls();
                    break;
                case 'isMapped':
                    // Ensure the hidden input carries explicit 1/0 strings for server validation
                    this.syncModelElement(key, value ? '1' : '0');
                    break;
                case 'firstParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party1', value);
                    this.state.party1 = value;
                    break;
                case 'secondParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party2', value);
                    this.state.party2 = value;
                    break;
                case 'thirdParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party3', value);
                    this.state.party3 = value;
                    break;
                case 'fourthParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party4', value);
                    this.state.party4 = value;
                    break;
                case 'hasFourthParty':
                    this.syncModelElement(key, value);
                    this.updateTripartiteControls();
                    break;
                case 'opType':
                    this.syncModelElement(key, value);
                    this.applyLgaOpTypeRules();
                    break;
                case 'typeValue':
                case 'instrumentCategory':
                    this.syncModelElement(key, value);
                    this.applyInstrumentCatalogRules();
                    // The permit rules read opType, which the line above has just set.
                    this.applyLgaOpTypeRules();
                    break;
                case 'serialNo':
                case 'pageNo':
                case 'volumeNo':
                case 'landUse':
                case 'houseNo':
                case 'plotNo':
                case 'tpNo':
                case 'lpknNo':
                case 'approvedPlanNo':
                case 'plotSize':
                case 'scale':
                case 'dateRecommended':
                case 'dateApproved':
                case 'leaseBegins':
                case 'leaseExpires':
                case 'metricSheet':
                case 'streetName':
                case 'currentStreetName':
                case 'district':
                case 'currentDistrict':
                case 'lga':
                case 'state':
                case 'transactionDate':
                case 'regDate':
                case 'regTime':
                case 'deedsDate':
                case 'deedsTime':
                case 'opSerialNumber':
                case 'period':
                case 'periodUnit':
                    this.syncModelElement(key, value);
                    if (['houseNo', 'currentStreetName', 'currentDistrict', 'lga', 'state'].includes(key)) {
                        this.updatePropertyDescription();
                    }
                    if (['serialNo', 'pageNo', 'volumeNo'].includes(key)) {
                        this.updateRegPreview();
                    }
                    break;
                case 'propertyDescription':
                    this.syncModelElement(key, value);
                    break;
                case 'fileNumber':
                    this.syncModelElement(key, value);
                    break;
                case 'hasOfficialFileNumber':
                    this.syncHasOfficialField(value);
                    this.syncFileNumberSections();
                    break;
                case 'tempFileNumber':
                case 'tempFileNumberLoading':
                case 'tempFileNumberError':
                    this.updateTempStatus();
                    if (window.GlobalFileNoModal && typeof window.GlobalFileNoModal.updatePreview === 'function') {
                        window.GlobalFileNoModal.updatePreview();
                    }
                    break;
                default:
                    this.syncModelElement(key, value);
                    break;
            }
        }

        syncTransactionTypeSelects(value) {
            const normalized = value || '';
            this.transactionTypeSelects.forEach((select) => {
                if (select.value !== normalized) {
                    select.value = normalized;
                }
            });
        }

        async loadInstrumentTypeOptions() {
            if (!this.instrumentTypesUrl) {
                return;
            }

            try {
                const options = await requestInstrumentTypes(this.instrumentTypesUrl);
                if (!Array.isArray(options) || options.length === 0) {
                    return;
                }
                this.instrumentTypeOptions = options;
                this.populateTransactionTypeSelects();
            } catch (error) {
                const selects = this.transactionTypeSelects;
                if (selects && selects.length) {
                    selects.forEach((select) => {
                        if (!select.dataset.errorNotified) {
                            select.dataset.errorNotified = 'true';
                            select.title = 'Instrument types failed to load; please type manually if needed.';
                        }
                    });
                }
            }
        }

        populateTransactionTypeSelects() {
            if (!Array.isArray(this.instrumentTypeOptions) || this.instrumentTypeOptions.length === 0) {
                return;
            }

            const selects = this.transactionTypeSelects.length
                ? this.transactionTypeSelects
                : Array.from(this.container.querySelectorAll('select[data-role="transaction-type"]'));

            const currentValue = this.state.transactionType || '';

            selects.forEach((select) => {
                const placeholder = select.dataset.placeholder || 'Select type';
                const previousValue = currentValue || select.value || select.dataset.initialValue || '';

                while (select.firstChild) {
                    select.removeChild(select.firstChild);
                }

                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                select.appendChild(placeholderOption);

                const existingFallbacks = select.querySelectorAll('option[data-fallback="true"]');
                existingFallbacks.forEach((option) => option.remove());

                const normalizedOptions = this.instrumentTypeOptions
                    .map((item) => {
                        const name = (item && (item.name || item.InstrumentName)) ? String(item.name || item.InstrumentName).trim() : '';
                        return name ? { value: name, label: name } : null;
                    })
                    .filter(Boolean)
                    .sort((a, b) => a.label.localeCompare(b.label));

                // 'Other' is not an instrument in the catalogue, it is the way out of it:
                // the officer names the paper in the Specify field beside this select.
                // Appended after the sort so it stays at the foot of the list, which is
                // where the file-indexing modal (which appends it in PHP) puts it too.
                if (!normalizedOptions.some((item) => item.value.toLowerCase() === 'other')) {
                    normalizedOptions.push({ value: 'Other', label: 'Other' });
                }

                let hasMatch = false;

                normalizedOptions.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    select.appendChild(option);
                    if (!hasMatch && previousValue && item.value.toLowerCase() === previousValue.toLowerCase()) {
                        hasMatch = true;
                    }
                });

                if (previousValue && !hasMatch) {
                    const fallback = document.createElement('option');
                    fallback.value = previousValue;
                    fallback.textContent = previousValue;
                    fallback.dataset.fallback = 'true';
                    select.appendChild(fallback);
                }

                select.value = previousValue;
            });

            this.updateTransactionContext();
        }

        syncModelElement(key, value) {
            const element = this.modelElements.get(key);
            if (!element) {
                return;
            }

            if (element.type === 'checkbox') {
                element.checked = Boolean(value);
                return;
            }

            if (element.value !== value) {
                element.value = value == null ? '' : value;
            }
        }

        syncHasOfficialField(value) {
            const element = this.modelElements.get('hasOfficialFileNumber');
            if (element) {
                element.value = value ? '1' : '0';
            }
            if (this.toggleTempCheckbox) {
                this.toggleTempCheckbox.checked = !value;
            }
        }

        syncAllState() {
            Object.keys(this.state).forEach((key) => {
                this.onStateChange(key, this.state[key], { silent: true });
            });
        }

        resetForm() {
            // Invalidate any in-flight matching-records fetch so stale results
            // cannot re-populate the "Existing Records for this File" card.
            this.matchingRecordsToken = (this.matchingRecordsToken || 0) + 1;

            if (this.form) {
                this.form.reset();
            }

            this.exitUpdateMode();

            // Force-clear the file number & related file number inputs in case
            // they carry default values that form.reset() would otherwise restore.
            const clearKeys = ['fileNumber', 'relatedFileNumber', 'fileno', 'related_file_number'];
            clearKeys.forEach((key) => {
                const el = this.modelElements.get(key);
                if (el && 'value' in el) {
                    el.value = '';
                }
            });

            if (this.matchingRecordsResults) {
                this.matchingRecordsResults.innerHTML = '';
            }
            if (this.matchingRecordsContainer) {
                this.matchingRecordsContainer.classList.add('hidden');
                const countLabel = this.matchingRecordsContainer.querySelector('[data-role="matching-records-count"]');
                if (countLabel) countLabel.textContent = '';
            }

            this.instrumentEntries = [];

            this.modelElements.forEach((element, key) => {
                this.state[key] = this.extractElementValue(element, key);
            });

            // Explicitly blank out file number state so the fileNumber onStateChange
            // dispatch takes the "empty" branch and hides the matching-records card.
            this.state.fileNumber = '';
            this.state.tempFileNumber = '';
            this.state.tempFileNumberLoading = false;
            this.state.tempFileNumberError = '';
            this.state.isMapped = false;

            this.syncAllState();
            this.updateTransactionContext();
            this.updatePropertyDescription();
            this.updateRegPreview();
            this.syncFileNumberSections();
            this.updateTempStatus();
            this.renderInstruments();

            // Belt-and-suspenders: after all sync handlers ran, ensure the
            // "Existing Records for this File" card is empty and hidden.
            if (this.matchingRecordsResults) {
                this.matchingRecordsResults.innerHTML = '';
            }
            if (this.matchingRecordsContainer) {
                this.matchingRecordsContainer.classList.add('hidden');
            }
            this.clearExistingRecordsTable();

            // Also clear the CofO/PRA duplicate lock cards if the blade helpers exist.
            try {
                if (typeof window.unlockPropertyRecordForm === 'function' && this.form) {
                    window.unlockPropertyRecordForm(this.form, { silent: true });
                }
                if (typeof window.clearCofoDuplicateCard === 'function') {
                    window.clearCofoDuplicateCard();
                }
                if (typeof window.clearPraDuplicateCard === 'function') {
                    window.clearPraDuplicateCard();
                }
            } catch (e) {
                // non-fatal: blade helpers are optional
            }
        }

        applyFormMode(mode) {
            const normalizedMode = mode === 'index' ? 'index' : 'property';
            if (this.modelElements.has('formMode')) {
                const hidden = this.modelElements.get('formMode');
                hidden.value = normalizedMode;
            }

            this.optionalBadges.forEach((badge) => {
                badge.classList.toggle('hidden', normalizedMode !== 'property');
            });

            const isIndexMode = normalizedMode === 'index';
            this.indexModeElements.forEach((element) => {
                const indexClass = element.dataset.indexClass || '';
                const defaultClass = element.dataset.defaultClass || '';
                const indexPlaceholder = element.dataset.indexPlaceholder || '';
                const defaultPlaceholder = element.dataset.placeholderDefault || '';

                element.className = isIndexMode
                    ? `${defaultClass} ${indexClass}`.trim()
                    : defaultClass;

                const placeholder = isIndexMode && indexPlaceholder ? indexPlaceholder : defaultPlaceholder;
                if (placeholder) {
                    element.setAttribute('placeholder', placeholder);
                } else {
                    element.removeAttribute('placeholder');
                }
            });

            const titleElement = this.dialog ? this.dialog.querySelector('#form-title') : null;
            if (titleElement) {
                if (titleElement.dataset.customTitle) {
                    titleElement.textContent = titleElement.dataset.customTitle;
                } else {
                    titleElement.textContent = normalizedMode === 'index' ? 'Index Card' : 'Add New Property Record';
                }
            }
        }

        updateTransactionContext() {
            // Lock the Instrument Type dropdown to "Deed of Mortgage" on the instruments capture page
            if (window.location.pathname.includes('/instruments/create')) {
                const selectElement = document.getElementById('transactionType-record');
                if (selectElement) {
                    selectElement.style.pointerEvents = 'none';
                    selectElement.style.backgroundColor = '#f3f4f6';
                    selectElement.setAttribute('tabindex', '-1');
                    if (this.state.transactionType !== 'Deed of Mortgage') {
                        this.state.transactionType = 'Deed of Mortgage';
                        this.syncModelElement('transactionType', 'Deed of Mortgage');
                        this.syncTransactionTypeSelects('Deed of Mortgage');
                    }
                }
            }

            const transactionType = this.state.transactionType || '';
            const labels = resolvePartyLabels(transactionType, { first: 'Grantor', second: 'Grantee' });
            const normalizedTx = String(transactionType || '').toLowerCase();
            const isOpTransaction = normalizedTx.includes('occupancy permit')
                || normalizedTx.includes('occupation permit')
                || normalizedTx === 'op';
            const isCofOTransaction = normalizedTx.includes('certificate of occupancy');
            const isOtherTransaction = normalizedTx === 'other';

            if (this.otherTransactionTypeGroup) {
                this.otherTransactionTypeGroup.classList.toggle('hidden', !isOtherTransaction);
            }
            if (this.otherTransactionTypeInput) {
                // A hidden input must not hold the form back on its own `required`, and the
                // name it holds belongs to 'Other' alone -- left behind on a row that has
                // since become a real instrument, the server would save it in its place.
                this.otherTransactionTypeInput.required = isOtherTransaction;
                if (!isOtherTransaction && this.otherTransactionTypeInput.value !== '') {
                    this.otherTransactionTypeInput.value = '';
                }
            }

            const updateLabelText = setPartyLabelText;

            if (this.partyLabelFirst && labels.first) {
                updateLabelText(this.partyLabelFirst, labels.first);
            }
            if (this.partyLabelSecond && labels.second) {
                updateLabelText(this.partyLabelSecond, labels.second);
            }
            if (this.partyLabelThird) {
                const isSurrender = transactionType === 'Deed of Surrender and Release';
                const hasTripartite = Boolean(this.state.tripartiteHasThird);
                const thirdLabel = isSurrender && hasTripartite ? (labels.second || 'Surrenderee') : (labels.third || 'Third Party');
                updateLabelText(this.partyLabelThird, thirdLabel);
            }

            if (this.partyLabelFourth && labels.fourth) {
                updateLabelText(this.partyLabelFourth, labels.fourth);
            }

            if (this.partyInputFirst) {
                this.partyInputFirst.name = FIRST_PARTY_FIELD_MAP[transactionType] || 'Grantor';
                const labelLower = labels.first.toLowerCase();
                this.partyInputFirst.setAttribute('placeholder', `Enter ${labelLower} name`);
            }

            if (this.partyInputSecond) {
                this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Grantee';
                const labelLower = labels.second.toLowerCase();
                this.partyInputSecond.setAttribute('placeholder', `Enter ${labelLower} name`);
            }

            if (this.partyInputThird) {
                const thirdLabel = labels.third || 'Third Party';
                this.partyInputThird.setAttribute('placeholder', `Enter ${thirdLabel.toLowerCase()} name`);
            }

            if (this.partyInputFourth) {
                const fourthLabel = labels.fourth || 'Fourth Party';
                this.partyInputFourth.setAttribute('placeholder', `Enter ${fourthLabel.toLowerCase()} name`);
            }

            const isGovernment = GOVERNMENT_TRANSACTION_TYPES.includes(transactionType);
            if (this.partyInputFirst) {
                // An LGA-issued OP is a government transaction whose grantor is NOT the State,
                // so it keeps the Local Government already picked instead of being overwritten.
                if (isGovernment && !this.isLgaOpType()) {
                    this.partyInputFirst.value = 'KANO STATE GOVERNMENT';
                    this.partyInputFirst.readOnly = true;
                    this.partyInputFirst.classList.add('bg-gray-100', 'text-gray-800');
                    this.setState('firstParty', 'KANO STATE GOVERNMENT');
                } else if (!isGovernment) {
                    if (this.partyInputFirst.readOnly) {
                        this.setState('firstParty', '');
                    }
                    this.partyInputFirst.readOnly = false;
                    this.partyInputFirst.classList.remove('bg-gray-100', 'text-gray-800');
                }
            }

            if (this.opFieldsWrapper) {
                this.opFieldsWrapper.classList.toggle('hidden', !isOpTransaction);
            }

            // Deed of Purchase carries what was paid. Matched on "deed of
            // purchase" rather than the full configured name so the legacy
            // spellings in the lookup table ("Deed of Purchase", "Deed of
            // Purchase Registration") all hit it.
            const isPurchaseTransaction = normalizedTx.includes('deed of purchase');
            if (this.purchaseFieldsWrapper) {
                this.purchaseFieldsWrapper.classList.toggle('hidden', !isPurchaseTransaction);
            }

            if (!isPurchaseTransaction) {
                // Clearing on switch-away is the point: an amount left behind
                // from a purchase would otherwise be saved against whatever
                // instrument the user changed to.
                this.setState('considerationAmount', '', { silent: true });
                this.syncModelElement('considerationAmount', '');
                this.setState('receiptNo', '', { silent: true });
                this.syncModelElement('receiptNo', '');
            }

            // Party 1 is optional for a Deed of Purchase alone: the register is
            // being captured for files that are often not indexed yet, and the
            // seller's name is only readable off the physical file. Saved blank
            // now, filled in at indexing. Party 2 stays required.
            this.applyPurchasePartyRules(isPurchaseTransaction);

            const opSerialInput = this.form?.querySelector('[name="op_serial_number"]');
            if (opSerialInput) {
                opSerialInput.required = isOpTransaction;
                opSerialInput.pattern = '[1-9][0-9]*';
                opSerialInput.maxLength = 100;
            }
            if (!isOpTransaction) {
                this.setState('opType', '', { silent: true });
                this.syncModelElement('opType', '');
                this.setState('typeValue', '', { silent: true });
                this.syncModelElement('typeValue', '');
                this.setState('opSerialNumber', '', { silent: true });
                this.syncModelElement('opSerialNumber', '');
            }

            // Runs for both branches: applies the LGA rules when the row is an LGA permit and
            // undoes the 0/0/0 stamp when the type has just moved away from one.
            this.applyInstrumentCatalogRules();
            this.applyLgaOpTypeRules();

            if (this.cofoFieldsWrapper) {
                this.cofoFieldsWrapper.classList.toggle('hidden', !isCofOTransaction);
            }

            if (!isCofOTransaction) {
                this.setState('cofoType', '', { silent: true });
                this.syncModelElement('cofoType', '');
            }

            if (this.transactionDetailsSection) {
                this.transactionDetailsSection.classList.toggle('hidden', transactionType === '');
            }

            if (this.tripartiteToggleLabelText) {
                if (transactionType === 'Tripartite Mortgage' || transactionType === 'Deed of Mortgage') {
                    this.tripartiteToggleLabelText.textContent = 'Include Co-Mortgagor (three-party agreement)';
                } else if (transactionType === 'Deed of Surrender and Release') {
                    this.tripartiteToggleLabelText.textContent = 'Include Co-Surrenderor (three-party agreement)';
                }
            }

            this.updateTripartiteControls();
        }

        /** True while the card is capturing a historical Deed of Purchase. */
        isDeedOfPurchaseCapture() {
            return isDeedOfPurchaseType(this.state.transactionType);
        }

        /**
         * Party 1 rules for a Deed of Purchase.
         *
         * The Purchase Register is being captured off paper, for files that have
         * frequently not been indexed yet -- the seller is only known once the
         * physical file is read. So Party 1 drops its `required` and its "*",
         * and the note under it says why. Every other instrument is untouched.
         */
        applyPurchasePartyRules(isPurchase) {
            this.applyFirstPartyRequirement();

            if (this.partyRequiredFirst) {
                this.partyRequiredFirst.classList.toggle('hidden', Boolean(isPurchase));
            }

            if (this.purchasePartyHint) {
                this.purchasePartyHint.classList.toggle('hidden', !isPurchase);
            }

            // The picker in the existing-records table belongs to this mode too.
            this.updateExistingRecordsPartyPicker();
        }

        /**
         * Whether Party 1 blocks the form — decided here and nowhere else.
         *
         * Two independent rules switch it off and they used to fight over the
         * attribute. The LGA pass hides the text input behind a council picker and
         * clears `required` so a hidden field cannot hold the form back; a Deed of
         * Purchase leaves Party 1 optional whether or not it is shown. Both run on
         * every instrument-type change, so whichever ran last won — and the LGA
         * pass runs second, which is why it kept putting the requirement back on
         * every Deed of Purchase and left Submit disabled until Party 1 was typed.
         *
         * Both callers now ask this instead, so the answer is the same whichever
         * order they run in.
         */
        applyFirstPartyRequirement() {
            if (!this.partyInputFirst) return;

            const required = !this.isLgaType() && !this.isDeedOfPurchaseCapture();
            if (this.partyInputFirst.required === required) return;

            this.partyInputFirst.required = required;

            // The page's submit-button validator reads that attribute but only ever
            // runs off a field event, so it has to be told the answer changed.
            this.refreshSubmitButtonState();
        }

        /**
         * Re-run the page's submit-button validator after a change made from here.
         *
         * validatePropertyForm() in propertycard/js/javascript.blade.php enables or
         * disables Submit by scanning `input[required]`, and it only ever runs from
         * an input/change listener on a field. Nothing this controller does
         * programmatically — clearing Party 1's `required` for a Deed of Purchase,
         * filling Party 1 from the timeline, prefilling a record — fires those
         * events, so the button would sit on a stale verdict: disabled because
         * Party 1 was empty and required, long after it stopped being either.
         *
         * That validator lives inside its page's DOMContentLoaded closure and
         * cannot be called from here, so the field re-announces itself instead.
         */
        refreshSubmitButtonState() {
            const element = this.partyInputFirst;
            if (!element) return;

            this.suppressElementEvents = true;
            try {
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            } finally {
                this.suppressElementEvents = false;
            }
        }

        /**
         * Show or hide the "Use as Party 1" column on the existing-records table.
         *
         * The table itself is unconditional -- it is the duplicate check every
         * instrument gets. Only the column that carries a row's Party 1 into the
         * form is confined to Deed of Purchase capture.
         */
        updateExistingRecordsPartyPicker() {
            const isPurchase = this.isDeedOfPurchaseCapture();

            if (!this.existingRecordsSection) return;

            this.existingRecordsSection
                .querySelectorAll('[data-col="use-party1"]')
                .forEach((cell) => cell.classList.toggle('hidden', !isPurchase));

            if (this.existingRecordsPickerNote) {
                const hasRows = (this.existingRecords || []).length > 0;
                this.existingRecordsPickerNote.classList.toggle('hidden', !(isPurchase && hasRows));
            }
        }

        /**
         * Carry one existing transaction's Party 1 into the form's Party 1.
         *
         * Explicitly officer-driven: nothing is copied until a row is picked, and
         * the first row is never assumed. Only Party 1 moves -- the rest of the
         * Deed of Purchase is keyed from the document in hand.
         */
        applyExistingRecordPartyOne(record, rowIndex) {
            const party1 = normalizeText(record && record.party_1);

            // Rows without a Party 1 are never given a button, so this is a guard
            // rather than a case the operator can reach.
            if (!party1 || party1 === '-') return;

            this.setState('firstParty', party1);

            // Mark which row the name came from. Carried on the button rather
            // than the row's background, which evaluateDuplicate() repaints.
            this.markPickedPartyOneRow(rowIndex);

            // The field was filled without a keystroke, so nothing has told the
            // page validator that Party 1 is no longer empty.
            this.refreshSubmitButtonState();

            if (this.partyInputFirst) {
                this.partyInputFirst.scrollIntoView({ block: 'center', behavior: 'smooth' });
                this.partyInputFirst.focus();
            }
        }

        /** Leave the picked row's button reading as the chosen one, and reset the rest. */
        markPickedPartyOneRow(rowIndex) {
            if (!this.existingRecordsBody) return;

            const idleClasses = ['text-blue-700', 'bg-white', 'border-blue-300', 'hover:bg-blue-50'];
            const pickedClasses = ['text-emerald-800', 'bg-emerald-50', 'border-emerald-400', 'hover:bg-emerald-100'];

            this.existingRecordsBody.querySelectorAll('[data-action="use-party1"]').forEach((button) => {
                const row = button.closest('tr');
                const isPicked = row && row.getAttribute('data-existing-row') === String(rowIndex);

                button.classList.remove(...(isPicked ? idleClasses : pickedClasses));
                button.classList.add(...(isPicked ? pickedClasses : idleClasses));
                button.textContent = isPicked ? '✓ Party 1' : 'Use as Party 1';
            });
        }

        updateTripartiteControls() {
            const transactionType = this.state.transactionType || '';
            const isTripartiteDropdown = transactionType === 'Tripartite Mortgage';
            const isMortgage = transactionType === 'Deed of Mortgage';
            const isSurrender = transactionType === 'Deed of Surrender and Release';

            const checkboxElement = this.modelElements.get('tripartiteHasThird');
            const fourthPartyCheckbox = this.modelElements.get('hasFourthParty');
            // Empty fallback on purpose: the branch below only overwrites the
            // second-party label when the table actually names one.
            const labels = resolvePartyLabels(transactionType, {});

            if (this.tripartiteToggleWrapper) {
                this.tripartiteToggleWrapper.classList.toggle('hidden', !(isTripartiteDropdown || isSurrender || isMortgage));
            }

            let hasToggleChecked = Boolean(this.state.tripartiteHasThird);
            const fourthValue = normalizeText(this.state.fourthParty);

            // Auto-check if P4 has value in Mortgage mode
            if (isTripartiteDropdown && fourthValue && !hasToggleChecked) {
                hasToggleChecked = true;
                this.state.tripartiteHasThird = true;
                if (checkboxElement) {
                    checkboxElement.checked = true;
                }
                this.syncModelElement('tripartiteHasThird', true);
            }

            // Sync uncheck if moving away from Tripartite types
            if (!(isTripartiteDropdown || isSurrender || isMortgage) && hasToggleChecked) {
                hasToggleChecked = false;
                this.state.tripartiteHasThird = false;
                if (checkboxElement) {
                    checkboxElement.checked = false;
                }
                this.syncModelElement('tripartiteHasThird', false);
            }

            let hasFourthPartyChecked = Boolean(this.state.hasFourthParty);
            const fourthValueActual = normalizeText(this.state.fourthParty);

            // Auto-check if P4 has value in Mortgage mode
            if ((isTripartiteDropdown || isMortgage) && fourthValueActual && !hasFourthPartyChecked) {
                hasFourthPartyChecked = true;
                this.state.hasFourthParty = true;
                if (fourthPartyCheckbox) {
                    fourthPartyCheckbox.checked = true;
                }
                this.syncModelElement('hasFourthParty', true);
            }

            // Sync uncheck if moving away from Mortgage types
            if (!(isTripartiteDropdown || isMortgage) && hasFourthPartyChecked) {
                hasFourthPartyChecked = false;
                this.state.hasFourthParty = false;
                if (fourthPartyCheckbox) {
                    fourthPartyCheckbox.checked = false;
                }
                this.syncModelElement('hasFourthParty', false);
            }

            // Hide Fourth Party toggle by default
            if (this.fourthPartyToggleWrapper) {
                this.fourthPartyToggleWrapper.classList.add('hidden');
            }

            // Logic for showing 3rd/4th party fields:
            // - Tripartite/Deed of Mortgage: Base is P1, P2. 
            // - Toggle 1 (Co-mortgagor) -> Shows P3.
            // - Toggle 2 (Include Fourth Party) -> Shows P4.
            let shouldShowThird = false;
            let shouldShowFourth = false;

            if (isTripartiteDropdown) {
                shouldShowThird = true;
                if (this.fourthPartyToggleWrapper) {
                    this.fourthPartyToggleWrapper.classList.remove('hidden');
                }
                if (hasFourthPartyChecked) {
                    shouldShowFourth = true;
                }
            } else if (isMortgage) {
                if (hasToggleChecked) {
                    shouldShowThird = true;
                    if (this.fourthPartyToggleWrapper) {
                        this.fourthPartyToggleWrapper.classList.remove('hidden');
                    }
                    if (hasFourthPartyChecked) {
                        shouldShowFourth = true;
                    }
                }
            } else if (isSurrender) {
                if (hasToggleChecked) {
                    shouldShowThird = true;
                }
            }

            if (this.partyThirdWrapper) {
                this.partyThirdWrapper.classList.toggle('hidden', !shouldShowThird);
            }
            if (this.partyFourthWrapper) {
                this.partyFourthWrapper.classList.toggle('hidden', !shouldShowFourth);
            }

            // Handle P4 (Mortgagor 3)
            if (!shouldShowFourth) {
                if (this.partyInputFourth) {
                    this.partyInputFourth.value = '';
                    this.partyInputFourth.name = '';
                    this.syncModelElement('party4', '');
                    this.state.party4 = '';
                    this.state.fourthParty = '';
                }
            } else if (this.partyInputFourth) {
                this.partyInputFourth.name = 'Mortgagor_3';
            }

            if (shouldShowThird) {
                if (this.partyInputThird) {
                    if (isSurrender) {
                        // For Surrender: P1: Surrenderor, P2: Co-Surrenderor, P3: Surrenderee
                        // Mapping: UI partyInputThird (thirdParty) becomes Co-Surrenderor (Surrenderor_2), 
                        // partyInputSecond (secondParty) becomes Surrenderee.
                        this.partyInputThird.name = THIRD_PARTY_FIELD_MAP[transactionType] || 'Surrenderor_2';
                    } else {
                        // For Mortgage: P1: Mortgagor, P2: Mortgagee, P3: Co-mortgagor
                        // UI: partyInputThird (thirdParty) is Co-mortgagor
                        this.partyInputThird.name = THIRD_PARTY_FIELD_MAP[transactionType] || 'Mortgagor_2';
                    }
                }

                if (this.partyInputSecond) {
                    if (isSurrender) {
                        this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Surrenderee';
                    } else {
                        this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Mortgagee';
                    }
                }

                // Update Labels
                if (this.partyLabelSecond) {
                    setPartyLabelText(this.partyLabelSecond, isSurrender ? 'Surrenderee' : (labels.second || 'Second Party'));
                }
                if (this.partyLabelThird) {
                    setPartyLabelText(this.partyLabelThird, isSurrender ? 'Co-Surrenderor' : (labels.third || 'Co-mortgagor'));
                }

                // Update Placeholders
                if (this.partyInputSecond) {
                    const l2 = isSurrender ? 'surrenderee' : (labels.second || 'second party').toLowerCase();
                    this.partyInputSecond.setAttribute('placeholder', `Enter ${l2} name`);
                }
                if (this.partyInputThird) {
                    const l3 = isSurrender ? 'co-surrenderor' : (labels.third || 'co-mortgagor').toLowerCase();
                    this.partyInputThird.setAttribute('placeholder', `Enter ${l3} name`);
                }
            } else {
                // Restore default names and labels when not in tripartite-collapsed mode
                if (this.partyInputSecond) {
                    this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Grantee';
                }
                if (this.partyLabelSecond) {
                    setPartyLabelText(this.partyLabelSecond, labels.second || 'Second Party');
                }
                if (this.partyInputSecond) {
                    const labelLower = (labels.second || 'second party').toLowerCase();
                    this.partyInputSecond.setAttribute('placeholder', `Enter ${labelLower} name`);
                }

                // Clear Co-mortgagor/Additional party when showing Fourth Party instead or when Bipartite
                if (this.partyInputThird) {
                    this.partyInputThird.value = '';
                    this.partyInputThird.name = '';
                }
                const thirdValue = normalizeText(this.state.thirdParty);
                if (thirdValue) {
                    this.setState('thirdParty', '', { silent: true });
                    this.syncModelElement('thirdParty', '');
                }
            }
        }

        async checkFilenoForMortgages(fileno, autoSelect = false) {
            if (!fileno) return;
            const warningNode = this.container.querySelector('[data-role="mortgage-warning"]');
            // Try to fetch mortgages count and also detect surrender/release records
            try {
                const mortgageResp = await fetch(`/api/file-mortgages?fileno=${encodeURIComponent(fileno)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const mortgagePayload = mortgageResp.ok ? await mortgageResp.json() : null;
                const mortgageCount = Number(mortgagePayload?.count || 0);

                // Check PRA record for Surrender and Release entries
                const praResp = await fetch(`/api/pra/v1/records/by-file/${encodeURIComponent(fileno)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const praPayload = praResp.ok ? await praResp.json() : null;
                const existingType = (praPayload && praPayload.data && praPayload.data.transaction_type) ? String(praPayload.data.transaction_type).toLowerCase() : '';
                const hasSurrender = existingType === 'deed of surrender and release' || existingType === 'surrender' || existingType === 'deed of surrender';

                // Auto-select transaction type based on history
                if (autoSelect) {
                    if (mortgageCount > 0) {
                        const currentType = String(this.state.transactionType).toLowerCase();
                        if (currentType !== 'deed of surrender and release') {
                            this.setState('transactionType', 'Deed of Surrender and Release');
                        }
                    } else if (hasSurrender) {
                        const currentType = String(this.state.transactionType).toLowerCase();
                        if (currentType !== 'deed of mortgage') {
                            this.setState('transactionType', 'Deed of Mortgage');
                        }
                    }
                }

                // Update hidden state and warning node
                const anyRelated = mortgageCount > 0 || hasSurrender;
                this.setState('isMapped', anyRelated);
                if (warningNode) {
                    warningNode.classList.toggle('hidden', !anyRelated);
                }
            } catch (err) {
                console.warn('Related-deeds lookup failed', err);
            }
        }

        async fetchAndDisplayMatchingRecords(fileno) {
            if (!fileno || !this.matchingRecordsResults) return;

            // Snapshot the reset token so a Refresh during flight discards results.
            const requestToken = this.matchingRecordsToken || 0;

            try {
                // **PIC/PRA ROUTING FIX**: Add record_mode to query params
                const recordModeInput = this.container.querySelector('input[name="record_mode"]');
                const recordMode = recordModeInput ? recordModeInput.value : '';
                const queryParams = new URLSearchParams({ record_mode: recordMode });

                const response = await fetch(`/api/pra/v1/records/all-by-file/${encodeURIComponent(fileno)}?${queryParams.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });

                const payload = response.ok ? await response.json() : null;
                const records = payload?.data || [];

                // Refresh was clicked while the request was in flight — bail out.
                if ((this.matchingRecordsToken || 0) !== requestToken) {
                    if (this.matchingRecordsContainer) this.matchingRecordsContainer.classList.add('hidden');
                    if (this.matchingRecordsResults) this.matchingRecordsResults.innerHTML = '';
                    return;
                }

                if (records.length === 0) {
                    if (this.matchingRecordsContainer) this.matchingRecordsContainer.classList.add('hidden');
                    return;
                }

                this.renderMatchingRecords(records);
                if (this.matchingRecordsContainer) this.matchingRecordsContainer.classList.remove('hidden');

            } catch (err) {
                console.warn('Failed to fetch matching records', err);
            }
        }

        // ── Existing Records for this File ───────────────────────────────────
        // The PRA-only card above answers "what else is on this file in PRA?".
        // This table answers the wider question the operator actually needs before
        // capturing: what does the file already hold ANYWHERE — PRA, File History,
        // Deeds Registration or CofO — so the same instrument is not entered twice.

        /**
         * Load every record held against the file across all four sources and
         * render them as a Timeline-shaped table below the form.
         */
        async loadExistingRecordsTable(fileno) {
            if (!this.existingRecordsSection || !this.existingRecordsBody) return;
            if (!this.existingRecordsUrl) return;

            const cleaned = normalizeText(fileno);
            if (!cleaned) {
                this.clearExistingRecordsTable();
                return;
            }

            // Reuse the matching-records token so a Refresh mid-flight discards this too.
            const requestToken = this.matchingRecordsToken || 0;

            this.existingRecordsSection.classList.remove('hidden');
            if (this.existingRecordsLoading) this.existingRecordsLoading.classList.remove('hidden');

            try {
                // "timeline", not "all": the deduplicated, arranged set the Property
                // Timeline shows for this file, rather than the raw union that lists
                // the same deed once per source table and in no order.
                const url = `${this.existingRecordsUrl}?file_number=${encodeURIComponent(cleaned)}&source=timeline`;
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });

                const payload = response.ok ? await response.json() : null;

                if ((this.matchingRecordsToken || 0) !== requestToken) {
                    this.clearExistingRecordsTable();
                    return;
                }

                if (this.existingRecordsLoading) this.existingRecordsLoading.classList.add('hidden');

                this.existingRecords = (payload && payload.success && Array.isArray(payload.records))
                    ? payload.records
                    : [];

                this.renderExistingRecordsTable();
                this.evaluateDuplicate();
            } catch (err) {
                console.warn('Failed to load existing records for file', err);
                if (this.existingRecordsLoading) this.existingRecordsLoading.classList.add('hidden');
                this.clearExistingRecordsTable();
            }
        }

        clearExistingRecordsTable() {
            this.existingRecords = [];
            this.duplicateOverride = false;

            if (this.existingRecordsBody) this.existingRecordsBody.innerHTML = '';
            if (this.existingRecordsCount) this.existingRecordsCount.textContent = '';
            if (this.existingRecordsLoading) this.existingRecordsLoading.classList.add('hidden');
            if (this.existingRecordsSection) this.existingRecordsSection.classList.add('hidden');
            if (this.duplicateWarning) {
                this.duplicateWarning.classList.add('hidden');
                this.duplicateWarning.innerHTML = '';
            }
        }

        renderExistingRecordsTable() {
            if (!this.existingRecordsBody || !this.existingRecordsSection) return;

            const records = this.existingRecords || [];
            this.existingRecordsBody.innerHTML = '';

            if (this.existingRecordsCount) {
                this.existingRecordsCount.textContent = records.length ? String(records.length) : '0';
            }

            if (records.length === 0) {
                // A file with nothing on it is the ordinary "new record" case — say
                // so plainly rather than showing an empty grid.
                this.existingRecordsBody.innerHTML =
                    '<tr><td colspan="9" class="px-2 py-4 text-center text-gray-400">' +
                    'No records held against this file yet — this will be the first.' +
                    '</td></tr>';
                this.existingRecordsSection.classList.remove('hidden');
                this.updateExistingRecordsPartyPicker();
                return;
            }

            records.forEach((record, index) => {
                const tr = document.createElement('tr');
                tr.className = 'border-t border-gray-100';
                tr.setAttribute('data-existing-row', String(index));

                const source = record.source_table || '—';
                const fileNo = record.file_number || record.mlsFNo || record.fileno || '—';
                const type = record.transaction_type || record.instrument_type || '—';
                const party1 = record.party_1 || '—';
                const party2 = record.party_2 || '—';
                const reg = this.formatExistingRegParticulars(record);
                const date = record.transaction_date || record.instrument_date || '—';

                // "Use as Party 1" — only where there is a Party 1 to carry over.
                // Hidden outside Deed of Purchase capture by
                // updateExistingRecordsPartyPicker().
                const hasParty1 = party1 !== '—' && normalizeText(party1) !== '-';
                const pickCell = hasParty1
                    ? '<button type="button" data-action="use-party1"'
                        + ' class="px-2 py-0.5 text-[10px] font-semibold text-blue-700 bg-white border border-blue-300 rounded hover:bg-blue-50 whitespace-nowrap">'
                        + 'Use as Party 1</button>'
                    : '<span class="text-gray-300">—</span>';

                tr.innerHTML = `
                    <td class="px-2 py-1.5 text-gray-400">${index + 1}</td>
                    <td class="px-2 py-1.5"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">${escapeExistingHtml(source)}</span></td>
                    <td class="px-2 py-1.5 whitespace-nowrap text-gray-600">${escapeExistingHtml(fileNo)}</td>
                    <td class="px-2 py-1.5 font-medium text-gray-800" data-cell="type">${escapeExistingHtml(type)}</td>
                    <td class="px-2 py-1.5 text-gray-600">${escapeExistingHtml(party1)}</td>
                    <td class="px-2 py-1.5 text-gray-600">${escapeExistingHtml(party2)}</td>
                    <td class="px-2 py-1.5 text-gray-600">${escapeExistingHtml(reg)}</td>
                    <td class="px-2 py-1.5 whitespace-nowrap text-gray-600">${escapeExistingHtml(date)}</td>
                    <td class="px-2 py-1.5 text-right hidden" data-col="use-party1">${pickCell}</td>
                `;

                const pickButton = tr.querySelector('[data-action="use-party1"]');
                if (pickButton) {
                    pickButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        this.applyExistingRecordPartyOne(record, index);
                    });
                }

                this.existingRecordsBody.appendChild(tr);
            });

            this.existingRecordsSection.classList.remove('hidden');
            this.updateExistingRecordsPartyPicker();
        }

        formatExistingRegParticulars(record) {
            // The timeline source pre-joins the particulars and prints "0/0/0" where
            // nothing is on record. Show that as it stands, so the row reads the same
            // here as it does on the Property Timeline.
            const joined = normalizeText(record.reg_no ?? record.regNo ?? '');
            if (joined) return joined;

            const serial = normalizeText(record.serial_no ?? record.serialNo ?? '');
            const page = normalizeText(record.page_no ?? record.pageNo ?? '');
            const volume = normalizeText(record.volume_no ?? record.volumeNo ?? '');

            if (!serial && !page && !volume) return '—';

            return `${serial || '-'}/${page || '-'}/${volume || '-'}`;
        }

        /** What the form currently holds, in the shape the duplicate rules compare. */
        readDuplicateKeyFromForm() {
            const typeSelect = this.container.querySelector('[data-role="transaction-type"]');
            const dateInput = this.container.querySelector('#transactionDate');

            return {
                type: normalizeText(typeSelect ? typeSelect.value : '').toLowerCase(),
                serial: normalizeText(this.container.querySelector('#serialNo')?.value ?? ''),
                page: normalizeText(this.container.querySelector('#pageNo')?.value ?? ''),
                volume: normalizeText(this.container.querySelector('#volumeNo')?.value ?? ''),
                date: normalizeText(dateInput ? dateInput.value : '')
            };
        }

        /**
         * Compare the in-progress entry against what the file already holds.
         *
         * Two tiers, because certainty differs:
         *   exact  — same instrument type AND identical registration particulars
         *            (serial/page/volume all present and equal). Two instruments
         *            cannot share a registration entry, so this is a real duplicate.
         *   likely — same instrument type AND same transaction date, but the
         *            particulars do not both exist to compare. Worth a warning,
         *            not a claim.
         *
         * @return {{exact: number[], likely: number[]}} indexes into this.existingRecords
         */
        findDuplicateMatches() {
            const key = this.readDuplicateKeyFromForm();
            const exact = [];
            const likely = [];

            if (!key.type) return { exact, likely };

            const hasReg = key.serial !== '' && key.volume !== '';

            (this.existingRecords || []).forEach((record, index) => {
                const type = normalizeText(record.transaction_type || record.instrument_type || '').toLowerCase();
                if (!type || type !== key.type) return;

                const serial = normalizeText(record.serial_no ?? record.serialNo ?? '');
                const page = normalizeText(record.page_no ?? record.pageNo ?? '');
                const volume = normalizeText(record.volume_no ?? record.volumeNo ?? '');

                if (hasReg && serial !== '' && volume !== ''
                    && serial === key.serial && volume === key.volume
                    && (key.page === '' || page === '' || page === key.page)) {
                    exact.push(index);
                    return;
                }

                if (key.date !== '') {
                    const recordDate = normalizeText(record.transaction_date || record.instrument_date || '');
                    // Server dates render as "Nov 29, 2019"; the input is ISO. Compare
                    // on the parsed day so the two formats still meet.
                    const a = Date.parse(recordDate);
                    const b = Date.parse(key.date);
                    if (!Number.isNaN(a) && !Number.isNaN(b)
                        && new Date(a).toDateString() === new Date(b).toDateString()) {
                        likely.push(index);
                    }
                }
            });

            return { exact, likely };
        }

        /** Highlight matching rows and raise (or clear) the duplicate banner. */
        evaluateDuplicate() {
            if (!this.duplicateWarning || !this.existingRecordsBody) return { exact: [], likely: [] };

            const matches = this.findDuplicateMatches();

            // Repaint row highlights from scratch each time.
            this.existingRecordsBody.querySelectorAll('tr[data-existing-row]').forEach((tr) => {
                tr.classList.remove('bg-red-50', 'bg-amber-50');
                const typeCell = tr.querySelector('[data-cell="type"]');
                if (typeCell) typeCell.classList.remove('text-red-700', 'text-amber-800');
            });

            const paint = (indexes, rowClass, textClass) => {
                indexes.forEach((i) => {
                    const tr = this.existingRecordsBody.querySelector(`tr[data-existing-row="${i}"]`);
                    if (!tr) return;
                    tr.classList.add(rowClass);
                    const typeCell = tr.querySelector('[data-cell="type"]');
                    if (typeCell) typeCell.classList.add(textClass);
                });
            };

            paint(matches.likely, 'bg-amber-50', 'text-amber-800');
            paint(matches.exact, 'bg-red-50', 'text-red-700');

            if (matches.exact.length > 0) {
                this.duplicateWarning.className =
                    'mb-2 rounded-md border px-3 py-2 text-xs border-red-300 bg-red-50 text-red-800';
                this.duplicateWarning.innerHTML =
                    '<span class="font-semibold">This record already exists.</span> ' +
                    `${matches.exact.length} record(s) on this file share this instrument type and registration particulars ` +
                    '(highlighted below). Saving will create a duplicate.';
                this.duplicateWarning.classList.remove('hidden');
            } else if (matches.likely.length > 0) {
                this.duplicateWarning.className =
                    'mb-2 rounded-md border px-3 py-2 text-xs border-amber-300 bg-amber-50 text-amber-900';
                this.duplicateWarning.innerHTML =
                    '<span class="font-semibold">Possible duplicate.</span> ' +
                    `${matches.likely.length} record(s) on this file share this instrument type and transaction date ` +
                    '(highlighted below). Check the registration particulars before saving.';
                this.duplicateWarning.classList.remove('hidden');
            } else {
                this.duplicateWarning.classList.add('hidden');
                this.duplicateWarning.innerHTML = '';
            }

            // A changed entry invalidates any previous "add anyway" decision.
            this.duplicateOverride = false;

            return matches;
        }

        /** Confirm before saving over an exact duplicate. */
        async confirmDuplicateSubmit(count) {
            const message = `${count} record(s) on this file already have this instrument type and registration particulars. `
                + 'Saving will create a duplicate entry.';

            if (window.Swal && typeof window.Swal.fire === 'function') {
                const result = await window.Swal.fire({
                    title: 'This record already exists',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Add anyway',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626',
                    reverseButtons: true
                });
                return !!result.isConfirmed;
            }

            return window.confirm(`${message}\n\nAdd anyway?`);
        }

        /**
         * Auto-backfill property details from any existing record on the file.
         *
         * Used by the "Capture Existing Mortgage Now" flow (No Existing Mortgage
         * Found! prompt): once a file number is selected we pull the shared
         * property attributes (location, plot, district, land use, etc.) from the
         * file's existing records so the user does not have to click a matching
         * record first. Property details are identical across a file's
         * instruments, so the first record that actually carries property data
         * is sufficient.
         */
        async autoBackfillPropertyDetailsFromFile(fileno) {
            const normalized = normalizeText(fileno);
            if (!normalized) return false;

            // Primary source: the file's indexing record holds the canonical
            // property attributes (plot, TP, LPKN, district, LGA, land use, etc.).
            try {
                const response = await fetch(`/api/pra/v1/records/property-by-file/${encodeURIComponent(normalized)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });

                const payload = response.ok ? await response.json() : null;
                const details = payload?.data || null;
                if (details && Object.values(details).some((v) => normalizeText(v).length > 0)) {
                    return this.applyIndexedPropertyDetails(details);
                }
            } catch (err) {
                console.warn('Failed to backfill property details from file indexing', err);
            }

            // Fallback: derive property details from an existing PRA record on the
            // file when the file has no indexing row.
            try {
                const recordModeInput = this.container.querySelector('input[name="record_mode"]');
                const recordMode = recordModeInput ? recordModeInput.value : '';
                const queryParams = new URLSearchParams({ record_mode: recordMode });

                const response = await fetch(`/api/pra/v1/records/all-by-file/${encodeURIComponent(normalized)}?${queryParams.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });

                const payload = response.ok ? await response.json() : null;
                const records = payload?.data || [];
                if (records.length === 0) return false;

                const propertyKeys = ['location', 'plot_no', 'plotNo', 'house_no', 'houseNo', 'street_name', 'streetName', 'district', 'land_use', 'landUse', 'property_description', 'propertyDescription'];
                const hasPropertyData = (rec) => propertyKeys.some((k) => normalizeText(rec[k]).length > 0);
                const rec = records.find(hasPropertyData) || records[0];

                return this.applyIndexedPropertyDetails({
                    house_no: rec.house_no ?? rec.houseNo,
                    plot_no: rec.plot_no ?? rec.plotNo,
                    tp_no: rec.tp_no ?? rec.tpNo,
                    lpkn_no: rec.lpkn_no ?? rec.lpknNo,
                    street_name: rec.street_name ?? rec.streetName,
                    district: rec.district,
                    lga: rec.lga,
                    location: rec.location,
                    land_use: rec.land_use ?? rec.landUse,
                    property_description: rec.property_description ?? rec.propertyDescription,
                });
            } catch (err) {
                console.warn('Failed to auto-backfill property details for file', err);
                return false;
            }
        }

        /**
         * Apply backfilled property details to the form, driving the cascading
         * Street/District selects (with their "Other" free-text fallback) and the
         * LGA / Land Use selects — not just plain inputs. Indexed values often do
         * not exactly match a dropdown option (e.g. "Dan Dinshe Kofar Dawannau"
         * vs the listed "DAN DINSHE KOFAR DAWANAU"), so unmatched values route
         * through the component's "Other" input to stay visible and submittable.
         */
        applyIndexedPropertyDetails(details) {
            if (!details) return false;
            let applied = false;

            const setText = (stateKey, value) => {
                if (normalizeText(value).length > 0) {
                    this.setState(stateKey, value);
                    applied = true;
                }
            };

            setText('houseNo', details.house_no);
            setText('plotNo', details.plot_no);
            setText('tpNo', details.tp_no);
            setText('lpknNo', details.lpkn_no);

            if (normalizeText(details.street_name).length > 0) {
                this.applyComponentSelectValue(this.streetComponent, 'street-select', 'street-other', details.street_name);
                applied = true;
            }
            if (normalizeText(details.district).length > 0) {
                this.applyComponentSelectValue(this.districtComponent, 'district-select', 'district-other', details.district);
                applied = true;
            }
            if (normalizeText(details.lga).length > 0) {
                if (this.applyPlainSelectValue('lga', details.lga)) applied = true;
            }
            if (normalizeText(details.land_use).length > 0) {
                if (this.applyPlainSelectValue('landUse', details.land_use)) applied = true;
            }

            // Rebuild the description from the components just set; only fall back
            // to the stored/indexed value when nothing could be derived.
            this.updatePropertyDescription();
            const derived = normalizeText(this.state.propertyDescription);
            const stored = normalizeText(details.property_description) || normalizeText(details.location);
            if (derived.length === 0 && stored.length > 0) {
                this.setState('propertyDescription', stored);
                applied = true;
            }

            return applied;
        }

        /**
         * Select a value on a Street/District component: match an existing option
         * case-insensitively (by value or label); otherwise switch the select to
         * "Other" and place the value in the free-text input. Dispatches the
         * native events the component already listens to so state + property
         * description stay in sync.
         */
        applyComponentSelectValue(component, selectRole, otherRole, value) {
            if (!component) return;
            const select = component.querySelector(`[data-role="${selectRole}"]`);
            const otherInput = component.querySelector(`[data-role="${otherRole}"]`);
            if (!select) return;

            const target = normalizeText(value).toLowerCase();
            const match = Array.from(select.options).find((o) =>
                normalizeText(o.value).toLowerCase() === target ||
                normalizeText(o.textContent).toLowerCase() === target
            );

            if (match) {
                select.value = match.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (otherInput) {
                select.value = 'other';
                select.dispatchEvent(new Event('change', { bubbles: true }));
                otherInput.value = normalizeText(value);
                otherInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        /**
         * Select a value on a plain model-bound <select> (LGA, Land Use) by
         * case-insensitive match on option value or label. Returns whether a
         * matching option was found and applied.
         */
        applyPlainSelectValue(modelKey, value) {
            const select = this.modelElements.get(modelKey);
            if (!select || !select.options) return false;

            const target = normalizeText(value).toLowerCase();
            const match = Array.from(select.options).find((o) =>
                normalizeText(o.value).toLowerCase() === target ||
                normalizeText(o.textContent).toLowerCase() === target
            );

            if (!match) return false;

            select.value = match.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        renderMatchingRecords(records) {
            this.matchingRecordsResults.innerHTML = '';

            const countLabel = this.matchingRecordsContainer.querySelector('[data-role="matching-records-count"]');
            if (countLabel) {
                countLabel.textContent = `(${records.length})`;
            }

            records.forEach((record, index) => {
                const item = document.createElement('div');
                item.className = 'p-3 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-blue-200 transition-all duration-200';

                const type = record.transaction_type || record.instrument_type || 'Unknown Type';
                const regNo = record.regNo || record.Prop_id || record.prop_id || 'No Reg #';
                const parties = this.getExtendedPartiesSummary(record);
                const dateInfo = this.getDateInfoSummary(record);

                const isMortgage = type.toLowerCase().includes('mortgage');
                const isSurrender = type.toLowerCase().includes('surrender') || type.toLowerCase().includes('release');
                let badgeHtml = '';
                if (isMortgage) {
                    badgeHtml = '<span class="ml-1 px-1.5 py-0.5 text-[8px] font-bold bg-amber-100 text-amber-700 rounded border border-amber-200 uppercase">Mortgage</span>';
                } else if (isSurrender) {
                    badgeHtml = '<span class="ml-1 px-1.5 py-0.5 text-[8px] font-bold bg-purple-100 text-purple-700 rounded border border-purple-200 uppercase">Surrender</span>';
                }

                item.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1 min-w-0 pr-2">
                            <div class="flex items-center">
                                <span class="mr-1.5 text-red-600 font-bold text-[10px]">${index + 1}.</span>
                                <div class="text-[11px] font-bold text-gray-800 truncate">${type}</div>
                                ${badgeHtml}
                            </div>
                            <div class="text-[10px] text-gray-500 font-medium"><span class="font-bold text-gray-700">Registration Particulars:</span> <span class="text-blue-900 font-bold">${regNo}</span></div>
                            ${dateInfo ? `<div class="text-[10px] text-blue-600 font-semibold mt-0.5">${dateInfo}</div>` : ''}
                        </div>
                        <div class="flex space-x-1 shrink-0">
                            <button type="button" class="px-2 py-1 text-[9px] font-bold text-white bg-blue-500 rounded hover:bg-blue-600 transition-colors shadow-sm" data-action="update-record">Update</button>
                            <button type="button" class="px-2 py-1 text-[9px] font-bold text-white bg-emerald-500 rounded hover:bg-emerald-600 transition-colors shadow-sm" data-action="capture-new">Capture New</button>
                        </div>
                    </div>
                    ${parties ? `<div class="text-[9px] text-gray-600 border-t border-gray-50 pt-1.5 mt-1.5 leading-tight">${parties}</div>` : ''}
                `;

                item.querySelector('[data-action="update-record"]').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleMatchingRecordSelect(record, 'update');
                });

                item.querySelector('[data-action="capture-new"]').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleMatchingRecordSelect(record, 'capture');
                });

                this.matchingRecordsResults.appendChild(item);
            });
        }

        getExtendedPartiesSummary(record) {
            const parts = [];
            // Use parties from API if available
            if (record.parties) {
                const p = record.parties;
                if (p.party_1) parts.push(`<span class="font-semibold text-gray-700">P1:</span> ${p.party_1}`);
                if (p.party_2) parts.push(`<span class="font-semibold text-gray-700">P2:</span> ${p.party_2}`);
                if (p.party_3) parts.push(`<span class="font-semibold text-gray-700">P3:</span> ${p.party_3}`);
            }

            // Fallback to legacy fields if parties is empty
            if (parts.length === 0) {
                const primary = record.Assignor || record.Mortgagor || record.Grantor || record.Lessor || record.Surrenderor;
                const secondary = record.Assignee || record.Mortgagee || record.Grantee || record.Lessee || record.Surrenderee;
                if (primary) parts.push(`<span class="font-semibold text-gray-700">From:</span> ${primary}`);
                if (secondary) parts.push(`<span class="font-semibold text-gray-700">To:</span> ${secondary}`);
            }

            return parts.join(' <span class="text-gray-300 mx-1">|</span> ');
        }

        getPartiesSummary(record) {
            return this.getExtendedPartiesSummary(record);
        }

        getDateInfoSummary(record) {
            const parts = [];
            const date = record.deeds_date || record.deedsDate || record.transaction_date || record.transactionDate;
            const time = record.deeds_time || record.deedsTime;

            if (date) parts.push(`<span class="font-bold text-gray-700">Deed Date:</span> <span class="text-blue-700">${date}</span>`);
            if (time) parts.push(`<span class="font-bold text-gray-700">Deed Time:</span> <span class="text-blue-700">${time}</span>`);

            return parts.length > 0 ? parts.join(' <span class="text-gray-300 mx-1">|</span> ') : null;
        }

        handleMatchingRecordSelect(record, action) {
            // Hide the container
            if (this.matchingRecordsContainer) {
                this.matchingRecordsContainer.classList.add('hidden');
            }

            if (action === 'update') {
                const label = this.getRecordDisplayLabel(record);
                const recordId = this.resolveRecordId(record);
                this.enterUpdateMode(record, label, recordId);

                // Populate all fields from the record
                this.prefillFormFromRecord(record);
            } else {
                // Capture New: keep form in 'add' mode but populate property details
                this.exitUpdateMode();
                this.prefillPropertyDetailsFromRecord(record);
            }
        }

        async openForEditing(record) {
            if (!this.resolveRecordId(record)) {
                throw new Error('Missing property record identifier.');
            }

            await this.loadInstrumentTypeOptions();
            this.resetForm();
            this.setState('formMode', record.record_mode === 'index' ? 'index' : 'property');
            this.enterUpdateMode(record, this.getRecordDisplayLabel(record));

            const first = (...values) => values.find(value => value !== null && value !== undefined && String(value).trim() !== '') ?? '';
            const date = value => String(value || '').split(/[T ]/)[0];
            const thirdParty = first(record.Mortgagor_2, record.Surrenderor_2, record.party_3);
            const fourthParty = first(record.Mortgagor_3, record.party_4);
            const normalized = {
                ...record,
                transaction_type: first(record.transaction_type, record.transactionType, record.instrument_type),
                firstParty: first(record.party_1, record.Assignor, record.Mortgagor, record.Vendor, record.Donor, record.Surrenderor, record.Lessor, record.Grantor, record.firstParty),
                secondParty: first(record.party_2, record.Assignee, record.Mortgagee, record.Purchaser, record.Donee, record.Surrenderee, record.Lessee, record.Grantee, record.secondParty),
                thirdParty,
                fourthParty,
                tripartiteHasThird: Boolean(thirdParty || fourthParty),
                lga: first(record.lga, record.lgsaOrCity),
                periodUnit: first(record.period_unit, record.periodUnit, 'Years'),
                date_recommended: date(record.date_recommended),
                date_approved: date(record.date_approved),
                lease_begins: date(record.lease_begins),
                lease_expires: date(record.lease_expires),
                transaction_date: date(first(record.transaction_date, record.transactionDate)),
                deeds_date: date(first(record.deeds_date, record.deedsDate)),
                deeds_time: String(first(record.deeds_time, record.deedsTime)).slice(0, 8)
            };

            // Preserve historical instrument names that are absent from the current catalogue.
            this.transactionTypeSelects.forEach(select => {
                if (!Array.from(select.options).some(option => option.value === normalized.transaction_type)) {
                    select.add(new Option(normalized.transaction_type, normalized.transaction_type));
                }
            });
            this.prefillFormFromRecord(normalized);
            this.setState('typeValue', first(record.type_value, record.op_type, record.cofo_type, record.instrument_subtype));
            this.setState('instrumentCategory', record.instrument_category || '');
            // Instrument rules set default parties; the saved record takes precedence on edit.
            for (const key of ['firstParty', 'secondParty', 'thirdParty', 'fourthParty']) {
                this.setState(key, normalized[key]);
            }

            // Use the address components' manual entry fallback for unlisted values.
            this.applyComponentSelectValue(this.streetComponent, 'street-select', 'street-other', record.street_name || '');
            this.applyComponentSelectValue(this.districtComponent, 'district-select', 'district-other', record.district || '');
            for (const [key, value] of [['lga', normalized.lga], ['landUse', record.land_use || '']]) {
                const select = this.modelElements.get(key);
                if (value && select?.options && !this.applyPlainSelectValue(key, value)) {
                    select.add(new Option(value, value));
                    this.setState(key, value);
                    this.syncModelElement(key, value);
                }
            }
            this.setState('propertyDescription', record.property_description || record.location || '');

            const fileNumber = first(record.mlsFNo, record.kangisFileNo, record.NewKANGISFileno, record.temp_fileno);
            this.setState('fileNumber', fileNumber, { silent: true });
            for (const id of ['fileno', 'primary_selected_fileno', 'fileno-display']) {
                const input = this.container.querySelector('#' + id);
                if (input) input.value = fileNumber;
            }
            this.setState('tempFileNumber', record.temp_fileno || '');
            this.syncModelElement('tempFileNumber', record.temp_fileno || '');
            const relatedInput = this.form.querySelector('[name="related_file_number"]');
            if (relatedInput) relatedInput.value = first(record.related_file_number, record.related_fileno);

            // Retain stored fields that the shared Add form does not expose.
            for (const name of ['location', 'layout', 'schedule', 'instrumentType', 'party_5']) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.dataset.editPreserved = '1';
                input.value = name === 'instrumentType' ? (record.instrument_type || '') : (record[name] || '');
                this.form.appendChild(input);
            }

            this.dialog.classList.remove('hidden');
            this.dialog.style.removeProperty('display');
        }

        prefillFormFromRecord(record) {
            // First pass: basic flat mapping
            Object.entries(record).forEach(([key, value]) => {
                if (value !== null && value !== undefined) {
                    this.setState(key, value);
                }
            });

            // Second pass: explicit mappings for known differences
            const mapping = {
                'transaction_type': 'transactionType',
                'deeds_date': 'deedsDate',
                'deeds_time': 'deedsTime',
                'op_type': 'opType',
                'instrument_subtype': 'instrumentSubtype',
                'instrument_category': 'instrumentCategory',
                'op_serial_number': 'opSerialNumber',
                'consideration_amount': 'considerationAmount',
                'receipt_no': 'receiptNo',
                'cofo_type': 'cofoType',
                'transaction_date': 'transactionDate',
                'land_use': 'landUse',
                'property_description': 'propertyDescription',
                'house_no': 'houseNo',
                'plot_no': 'plotNo',
                'street_name': 'streetName',
                'tp_no': 'tpNo',
                'lpkn_no': 'lpknNo',
                'approved_plan_no': 'approvedPlanNo',
                'plot_size': 'plotSize',
                'date_recommended': 'dateRecommended',
                'date_approved': 'dateApproved',
                'lease_begins': 'leaseBegins',
                'lease_expires': 'leaseExpires',
                'metric_sheet': 'metricSheet'
            };

            Object.entries(mapping).forEach(([recordKey, stateKey]) => {
                if (record[recordKey] !== undefined && record[recordKey] !== null) {
                    this.setState(stateKey, record[recordKey]);
                }
            });

            // Handle parties
            if (record.parties) {
                const parties = record.parties;
                // Map logical parties to form fields
                if (parties.party_1) this.setState('party1', parties.party_1);
                if (parties.party_2) this.setState('party2', parties.party_2);
                if (parties.party_3) this.setState('party3', parties.party_3);
                if (parties.party_4) this.setState('party4', parties.party_4);

                const primary = parties.assignor || parties.mortgagor || parties.grantor || parties.lessor || parties.surrenderor || parties.party_1;
                const secondary = parties.assignee || parties.mortgagee || parties.grantee || parties.lessee || parties.surrenderee || parties.party_2;

                if (primary) this.setState('firstParty', primary);
                if (secondary) this.setState('secondParty', secondary);
            }
        }

        prefillPropertyDetailsFromRecord(record) {
            const propertyFieldsMapping = {
                'houseNo': 'houseNo',
                'house_no': 'houseNo',
                'plotNo': 'plotNo',
                'plot_no': 'plotNo',
                'tpNo': 'tpNo',
                'tp_no': 'tpNo',
                'lpknNo': 'lpknNo',
                'lpkn_no': 'lpknNo',
                'approvedPlanNo': 'approvedPlanNo',
                'approved_plan_no': 'approvedPlanNo',
                'plotSize': 'plotSize',
                'plot_size': 'plotSize',
                'propertyDescription': 'propertyDescription',
                'property_description': 'propertyDescription',
                'location': 'location',
                'landUse': 'landUse',
                'land_use': 'landUse',
                'district': 'district',
                'streetName': 'streetName',
                'street_name': 'streetName',
                'lga': 'lga',
                'state': 'state'
            };

            Object.entries(propertyFieldsMapping).forEach(([recordKey, stateKey]) => {
                if (record[recordKey] !== undefined && record[recordKey] !== null) {
                    this.setState(stateKey, record[recordKey]);
                }
            });
        }

        updatePropertyDescription() {
            const parts = [];
            const placeholders = ['Select Street Name', 'Select District Name', 'Select LGA'];

            const isPlaceholder = (val) => {
                if (!val) return true;
                return placeholders.some(p => val.includes(p));
            };

            const house = normalizeText(this.state.houseNo);
            if (house) {
                parts.push(house);
            }

            const street = normalizeText(this.state.currentStreetName || this.state.streetName);
            if (street && !isPlaceholder(street)) {
                parts.push(street);
            }

            const district = normalizeText(this.state.currentDistrict || this.state.district);
            if (district && !isPlaceholder(district)) {
                parts.push(district);
            }

            const lga = normalizeText(this.state.lga);
            if (lga && !isPlaceholder(lga)) {
                parts.push(lga);
            }

            const stateName = normalizeText(this.state.state);
            if (stateName) {
                parts.push(stateName);
            }

            const description = parts.join(', ');
            if (this.state.propertyDescription !== description) {
                this.state.propertyDescription = description;
                this.syncModelElement('propertyDescription', description);
            }
        }

        updateRegPreview() {
            const parts = [this.state.serialNo, this.state.pageNo, this.state.volumeNo]
                .map((value) => normalizeText(value))
                .filter((value) => value.length > 0);
            const preview = parts.join('/');

            if (this.regPreviewValue) {
                this.regPreviewValue.textContent = preview;
            }

            if (this.regPreviewBox) {
                this.regPreviewBox.classList.toggle('hidden', preview.length === 0);
            }
        }

        syncFileNumberSections() {
            const hasOfficial = !!this.state.hasOfficialFileNumber;
            if (this.smartWrapper) {
                this.smartWrapper.classList.toggle('hidden', !hasOfficial);
            }
            if (this.tempWrapper) {
                this.tempWrapper.classList.toggle('hidden', hasOfficial);
            }
            if (this.modalMlsMethodToggle) {
                this.modalMlsMethodToggle.classList.toggle('hidden', !hasOfficial);
            }

            if (!hasOfficial) {
                this.modalMlsInputSections.forEach(section => {
                    section.classList.add('hidden');
                });
            } else if (window.GlobalFileNoModal && typeof window.GlobalFileNoModal.initializeTabUI === 'function') {
                window.GlobalFileNoModal.initializeTabUI('mls');
            }

            if (this.fileNumberMessage) {
                this.fileNumberMessage.classList.toggle('hidden', hasOfficial);
            }
        }

        bindFileNumberControls() {
            if (this.toggleTempCheckbox) {
                this.toggleTempCheckbox.addEventListener('change', (event) => {
                    const checked = Boolean(event.target.checked);
                    this.setState('hasOfficialFileNumber', !checked);
                    if (checked && !this.state.tempFileNumber) {
                        this.fetchTempFileNumber(true);
                    }
                });
            }

            if (this.refreshTempButton) {
                this.refreshTempButton.addEventListener('click', () => {
                    this.fetchTempFileNumber(true);
                });
            }
            // Bind the main fileno hidden input (smart selector writes to #fileno)
            try {
                const filenoInput = this.container.querySelector('#fileno') || document.getElementById('fileno');
                if (filenoInput) {
                    const handler = (event) => {
                        const value = (event && event.target && event.target.value) ? String(event.target.value).trim() : String(filenoInput.value || '').trim();
                        // update controller state so onStateChange('fileNumber') triggers mortgage check when appropriate
                        this.setState('fileNumber', value);
                    };
                    filenoInput.addEventListener('input', handler);
                    filenoInput.addEventListener('change', handler);
                }
            } catch (err) {
                console.warn('Failed to bind fileno input for mortgage detection', err);
            }

        }

        updateTempStatus() {
            if (this.tempStatus) {
                this.tempStatus.classList.toggle('hidden', !this.state.tempFileNumberLoading);
            }

            if (this.tempValue) {
                if (this.state.tempFileNumberLoading) {
                    this.tempValue.textContent = 'Generating...';
                } else {
                    this.tempValue.textContent = this.state.tempFileNumber || 'Pending...';
                }
            }

            if (this.tempError) {
                const hasError = normalizeText(this.state.tempFileNumberError).length > 0;
                this.tempError.classList.toggle('hidden', !hasError);
                this.tempError.textContent = hasError ? this.state.tempFileNumberError : '';
            }
        }

        async fetchTempFileNumber(force = false) {
            if (!this.tempEndpoint || this.state.tempFileNumberLoading) {
                return;
            }

            this.setState('tempFileNumberLoading', true);
            this.setState('tempFileNumberError', '', { silent: true });

            try {
                const response = await fetch(this.tempEndpoint, {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const payload = await response.json();
                if (payload && payload.status === 'success' && payload.temp_fileno) {
                    this.setState('tempFileNumber', payload.temp_fileno);
                } else {
                    throw new Error(payload && payload.message ? payload.message : 'Unable to generate a temporary file number');
                }
            } catch (error) {
                this.setState('tempFileNumberError', error.message || 'Unable to generate a temporary file number');
            } finally {
                this.setState('tempFileNumberLoading', false);
            }
        }

        bindPropertyDescriptionRefresh() {
            if (this.refreshDescriptionButton) {
                this.refreshDescriptionButton.addEventListener('click', () => this.updatePropertyDescription());
            }
        }

        bindStreetComponent() {
            if (!this.streetComponent) {
                return;
            }

            const select = this.streetComponent.querySelector('[data-role="street-select"]');
            const otherInput = this.streetComponent.querySelector('[data-role="street-other"]');
            if (!select || !otherInput) {
                return;
            }

            const originalName = select.name;

            const applyStreetState = () => {
                const value = select.value;
                if (value === 'other') {
                    select.name = '';
                    otherInput.classList.remove('hidden');
                    otherInput.name = otherInput.dataset.nameTarget || originalName;
                    this.setState('streetName', 'other');
                    this.setState('currentStreetName', otherInput.value);
                } else {
                    select.name = originalName;
                    otherInput.classList.add('hidden');
                    otherInput.name = '';
                    otherInput.value = '';
                    const display = (value && select.options[select.selectedIndex]) ? select.options[select.selectedIndex].textContent : '';
                    this.setState('streetName', value);
                    this.setState('currentStreetName', display);
                }
                this.updatePropertyDescription();
            };

            select.addEventListener('change', () => applyStreetState());
            otherInput.addEventListener('input', () => {
                this.setState('currentStreetName', otherInput.value);
                this.updatePropertyDescription();
            });

            applyStreetState();
        }

        bindDistrictComponent() {
            if (!this.districtComponent) {
                return;
            }

            const select = this.districtComponent.querySelector('[data-role="district-select"]');
            const otherInput = this.districtComponent.querySelector('[data-role="district-other"]');
            if (!select || !otherInput) {
                return;
            }

            const originalName = select.name;

            const applyDistrictState = () => {
                const value = select.value;
                if (value === 'other') {
                    select.name = '';
                    otherInput.classList.remove('hidden');
                    otherInput.name = otherInput.dataset.nameTarget || originalName;
                    this.setState('district', 'other');
                    this.setState('currentDistrict', otherInput.value);
                } else {
                    select.name = originalName;
                    otherInput.classList.add('hidden');
                    otherInput.name = '';
                    otherInput.value = '';
                    const display = (value && select.options[select.selectedIndex]) ? select.options[select.selectedIndex].textContent : '';
                    this.setState('district', value);
                    this.setState('currentDistrict', display);
                }
                this.updatePropertyDescription();
            };

            select.addEventListener('change', () => applyDistrictState());
            otherInput.addEventListener('input', () => {
                this.setState('currentDistrict', otherInput.value);
                this.updatePropertyDescription();
            });

            applyDistrictState();
        }

        bindInstrumentControls() {
            if (this.addInstrumentButton) {
                this.addInstrumentButton.addEventListener('click', () => {
                    this.addInstrumentEntry();
                });
            }

            this.renderInstruments();
        }

        addInstrumentEntry() {
            const entry = {
                id: Date.now() + Math.random(),
                type: '',
                date: '',
                party_from: '',
                party_to: ''
            };
            this.instrumentEntries.push(entry);
            this.renderInstruments();
        }

        removeInstrumentEntry(index) {
            if (index < 0 || index >= this.instrumentEntries.length) {
                return;
            }
            this.instrumentEntries.splice(index, 1);
            this.renderInstruments();
        }

        renderInstruments() {
            if (!this.instrumentList) {
                return;
            }

            this.instrumentList.innerHTML = '';

            if (!this.instrumentEntries.length) {
                if (this.instrumentEmptyMessage) {
                    this.instrumentEmptyMessage.classList.remove('hidden');
                }
                return;
            }

            if (this.instrumentEmptyMessage) {
                this.instrumentEmptyMessage.classList.add('hidden');
            }

            this.instrumentEntries.forEach((entry, index) => {
                const fragment = this.instrumentTemplate ? document.importNode(this.instrumentTemplate.content, true) : document.createDocumentFragment();
                let wrapper = fragment.querySelector ? fragment.querySelector('[data-role="instrument-entry"]') : null;

                if (!wrapper) {
                    wrapper = document.createElement('div');
                    wrapper.className = 'mt-4 rounded-lg p-4 space-y-4 border border-gray-200 bg-white';
                    fragment.appendChild(wrapper);
                }

                wrapper.dataset.index = String(index);
                this.applyInstrumentColors(wrapper, entry.type);

                const indexSpan = fragment.querySelector('[data-role="instrument-index"]');
                if (indexSpan) {
                    indexSpan.textContent = String(index + 1);
                }

                const tag = fragment.querySelector('[data-role="instrument-tag"]');
                if (tag) {
                    if (entry.type) {
                        tag.textContent = entry.type;
                        tag.classList.remove('hidden');
                    } else {
                        tag.textContent = '';
                        tag.classList.add('hidden');
                    }
                    this.applyInstrumentTagColors(tag, entry.type);
                }

                const typeSelect = fragment.querySelector('[data-field="type"]');
                if (typeSelect) {
                    typeSelect.name = `instrument_entries[${index}][type]`;
                    typeSelect.innerHTML = '';
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select type';
                    typeSelect.appendChild(defaultOption);
                    INSTRUMENT_TYPE_OPTIONS.forEach((optionValue) => {
                        const option = document.createElement('option');
                        option.value = optionValue;
                        option.textContent = optionValue;
                        if (optionValue === entry.type) {
                            option.selected = true;
                        }
                        typeSelect.appendChild(option);
                    });
                    if (entry.type && !INSTRUMENT_TYPE_OPTIONS.includes(entry.type)) {
                        const custom = document.createElement('option');
                        custom.value = entry.type;
                        custom.textContent = entry.type;
                        custom.selected = true;
                        typeSelect.appendChild(custom);
                    }
                    typeSelect.addEventListener('change', (event) => {
                        entry.type = event.target.value;
                        this.renderInstruments();
                    });
                }

                const dateInput = fragment.querySelector('[data-field="date"]');
                if (dateInput) {
                    dateInput.name = `instrument_entries[${index}][date]`;
                    dateInput.value = entry.date;
                    dateInput.addEventListener('change', (event) => {
                        entry.date = event.target.value;
                    });
                }

                const partyFromInput = fragment.querySelector('[data-field="party_from"]');
                const partyToInput = fragment.querySelector('[data-field="party_to"]');
                const labelFrom = fragment.querySelector('[data-role="instrument-label-from"]');
                const labelTo = fragment.querySelector('[data-role="instrument-label-to"]');
                const hint = fragment.querySelector('[data-role="instrument-hint"]');

                const partyLabels = INSTRUMENT_PARTY_MAP[entry.type] || { from: 'Party A', to: 'Party B' };

                if (labelFrom) {
                    labelFrom.textContent = partyLabels.from;
                }

                if (labelTo) {
                    labelTo.textContent = partyLabels.to;
                }

                if (hint) {
                    hint.classList.toggle('hidden', entry.type !== 'Subdivision');
                }

                if (partyFromInput) {
                    partyFromInput.name = `instrument_entries[${index}][party_from]`;
                    partyFromInput.value = entry.party_from;
                    partyFromInput.setAttribute('placeholder', partyLabels.from);
                    partyFromInput.addEventListener('input', (event) => {
                        entry.party_from = event.target.value;
                    });
                }

                if (partyToInput) {
                    partyToInput.name = `instrument_entries[${index}][party_to]`;
                    partyToInput.value = entry.party_to;
                    partyToInput.setAttribute('placeholder', partyLabels.to);
                    partyToInput.addEventListener('input', (event) => {
                        entry.party_to = event.target.value;
                    });
                }

                const removeButton = fragment.querySelector('[data-action="remove-instrument"]');
                if (removeButton) {
                    removeButton.addEventListener('click', () => this.removeInstrumentEntry(index));
                }

                this.instrumentList.appendChild(fragment);
            });
        }

        applyInstrumentColors(wrapper, type) {
            if (!wrapper) {
                return;
            }

            const colors = INSTRUMENT_COLOR_MAP[type] || INSTRUMENT_COLOR_MAP.default;
            const borderClasses = ALL_COLOR_CLASSES.filter((cls) => cls.startsWith('border-'));
            const bgClasses = ALL_COLOR_CLASSES.filter((cls) => cls.startsWith('bg-'));

            wrapper.classList.remove(...borderClasses, ...bgClasses);
            wrapper.classList.remove('bg-white');
            wrapper.classList.add(colors.border, colors.bg);
        }

        applyInstrumentTagColors(tag, type) {
            if (!tag) {
                return;
            }
            const colors = INSTRUMENT_COLOR_MAP[type] || INSTRUMENT_COLOR_MAP.default;
            const tagClasses = ALL_COLOR_CLASSES.filter((cls) => cls.includes('text-') || cls.startsWith('bg-'));
            tag.classList.remove(...tagClasses);
            colors.tag.split(' ').forEach((cls) => tag.classList.add(cls));
        }

        bindRegNumberSynchronisers() {
            this.serialSynchronizers.forEach((element) => {
                const targetKey = element.dataset.sync;
                if (!targetKey) {
                    return;
                }
                element.addEventListener('input', () => {
                    const targetElement = this.modelElements.get(targetKey);
                    const value = element.value || '';
                    if (targetElement) {
                        if (!targetElement.value) {
                            targetElement.value = value;
                            this.setState(targetKey, value);
                        }
                    } else {
                        this.setState(targetKey, value);
                    }
                });
            });
        }

        bindRefreshButton() {
            const refreshButton = document.getElementById('refresh-property-form');
            if (!refreshButton) {
                return;
            }

            refreshButton.addEventListener('click', () => {
                this.resetForm();
            });
        }

        bindSmartSelectorWatcher() {
            const filenoInput = document.getElementById('fileno');
            if (!filenoInput) {
                return;
            }
            filenoInput.addEventListener('change', () => {
                const value = normalizeText(filenoInput.value);
                // update state and allow onStateChange handlers to run
                this.setState('fileNumber', value);
                if (value) {
                    this.setState('hasOfficialFileNumber', true);
                }

                // Auto-prefill disabled here to allow user selection from matching records list first
                /*
                try {
                    if (typeof window.queuePraPrefillForFileNumber === 'function') {
                        window.queuePraPrefillForFileNumber([value], 'smart-selector');
                    }
                } catch (err) {
                    console.warn('Failed to queue PRA prefill for fileno', err);
                }
                */
            });
        }

        /**
         * Re-run the duplicate check whenever a field the rules depend on changes,
         * so the flag appears while the record is being typed rather than at save.
         */
        bindDuplicateWatchers() {
            if (!this.container || !this.duplicateWarning) return;

            const watch = (selector, events) => {
                const element = this.container.querySelector(selector);
                if (!element) return;
                events.forEach((eventName) => {
                    element.addEventListener(eventName, () => this.evaluateDuplicate());
                });
            };

            watch('[data-role="transaction-type"]', ['change']);
            watch('#serialNo', ['input', 'change']);
            watch('#pageNo', ['input', 'change']);
            watch('#volumeNo', ['input', 'change']);
            watch('#transactionDate', ['input', 'change']);
        }

        bindUpdateModeHandlers() {
            if (this.form) {
                this.form.addEventListener('submit', (event) => this.handleFormSubmit(event));
            }

            if (this.container) {
                this.container.addEventListener('pra-record-applied', (event) => this.handlePraRecordApplied(event));
                this.container.addEventListener('form-mode-changed', () => this.exitUpdateMode());
            }

            const closeButton = document.getElementById('close-property-form');
            if (closeButton) {
                closeButton.addEventListener('click', () => this.exitUpdateMode());
            }

            const cancelButton = document.getElementById('cancel-property-form');
            if (cancelButton) {
                cancelButton.addEventListener('click', () => this.exitUpdateMode());
            }
        }

        handlePraRecordApplied(event) {
            // alert suppressed in favor of matching records list selection
            return;
        }

        enterUpdateMode(record, label, forcedRecordId) {
            if (!record) {
                return;
            }

            const recordId = forcedRecordId || this.resolveRecordId(record);
            if (!recordId) {
                return;
            }

            this.state.updateRecordId = recordId;
            this.state.isUpdateMode = true;

            const titleElement = this.dialog?.querySelector('#form-title');
            if (titleElement) {
                titleElement.dataset.customTitle = 'Edit Property Record';
                titleElement.textContent = 'Edit Property Record';
            }

            if (this.propertyIdInput) {
                this.propertyIdInput.value = recordId;
            }

            if (this.formActionInput) {
                this.formActionInput.value = 'update';
            }

            this.ensureMethodOverrideInput('PUT');

            if (this.form) {
                const nextAction = this.buildUpdateUrl(recordId);
                if (nextAction) {
                    this.form.setAttribute('action', nextAction);
                }
            }

            if (this.submitButton) {
                this.submitButton.textContent = 'Update';
            }

            if (this.updateBanner) {
                if (this.updateBannerLabel) {
                    this.updateBannerLabel.textContent = label || this.getRecordDisplayLabel(record) || `#${recordId}`;
                }
                this.updateBanner.classList.remove('hidden');
            }

            this.setSubmitting(false);
        }

        exitUpdateMode() {
            this.state.isUpdateMode = false;
            this.state.updateRecordId = null;
            this.pendingUpdatePrompt = false;

            const titleElement = this.dialog ? this.dialog.querySelector('#form-title') : null;
            if (titleElement) {
                delete titleElement.dataset.customTitle;
                titleElement.textContent = this.state.formMode === 'index' ? 'Index Card' : 'Add New Property Record';
            }

            this.form?.querySelectorAll('[data-edit-preserved]').forEach(input => input.remove());

            if (this.propertyIdInput) {
                this.propertyIdInput.value = '';
            }

            if (this.formActionInput) {
                this.formActionInput.value = 'add';
            }

            if (this.methodOverrideInput) {
                this.methodOverrideInput.remove();
                this.methodOverrideInput = null;
            }

            if (this.form && this.formActionOriginal) {
                this.form.setAttribute('action', this.formActionOriginal);
            }

            if (this.submitButton) {
                this.submitButton.textContent = this.submitButtonDefaultLabel;
                this.submitButton.disabled = false;
                this.submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            if (this.updateBanner) {
                this.updateBanner.classList.add('hidden');
                if (this.updateBannerLabel) {
                    this.updateBannerLabel.textContent = '';
                }
            }

            this.isSubmitting = false;
        }

        ensureMethodOverrideInput(value) {
            if (!this.form) {
                return;
            }
            if (!this.methodOverrideInput) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_method';
                this.form.appendChild(input);
                this.methodOverrideInput = input;
            }
            this.methodOverrideInput.value = value;
        }

        setSubmitting(isSubmitting, labelOverride) {
            this.isSubmitting = isSubmitting;
            if (!this.submitButton) {
                return;
            }

            if (isSubmitting) {
                this.submitButton.disabled = true;
                this.submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                if (labelOverride) {
                    this.submitButton.textContent = labelOverride;
                }
            } else {
                this.submitButton.disabled = false;
                this.submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                if (this.state.isUpdateMode) {
                    this.submitButton.textContent = 'Update';
                } else {
                    this.submitButton.textContent = this.submitButtonDefaultLabel;
                }
            }
        }

        async handleFormSubmit(event) {
            // Duplicate guard — runs ahead of every save path, including the plain
            // native POST below that returns early. Only an exact match (same
            // instrument type AND same registration particulars) stops the save;
            // the softer "possible duplicate" case stays advisory.
            if (!this.state.isUpdateMode && !this.duplicateOverride) {
                const matches = this.evaluateDuplicate();

                if (matches.exact.length > 0) {
                    // Stop propagation too, not just the default: Legal Search binds
                    // its own submit interceptor on document that AJAX-posts the
                    // form. Without this, cancelling the prompt would still save.
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();

                    const proceed = await this.confirmDuplicateSubmit(matches.exact.length);
                    if (!proceed) {
                        return;
                    }

                    // Re-enter the normal flow; the override lets it through once.
                    this.duplicateOverride = true;
                    if (typeof this.form.requestSubmit === 'function') {
                        this.form.requestSubmit();
                    } else {
                        this.form.submit();
                    }
                    return;
                }
            }

            this.duplicateOverride = false;

            const isInstrumentsPage = window.location.pathname.toLowerCase().includes('/instruments/create');
            
            if (!this.state.isUpdateMode && !isInstrumentsPage) {
                return;
            }

            event.preventDefault();

            if (this.state.isUpdateMode && !this.state.updateRecordId) {
                this.showAlert('Missing record identifier for update.', 'error');
                return;
            }

            if (this.isSubmitting) {
                return;
            }

            const submittingMsg = this.state.isUpdateMode ? 'Updating…' : 'Saving…';
            this.setSubmitting(true, submittingMsg);

            try {
                const formData = new FormData(this.form);
                let url = this.form.action;
                
                if (this.state.isUpdateMode) {
                    formData.append('_method', 'PUT');
                    url = this.buildUpdateUrl(this.state.updateRecordId);
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await this.parseJsonSafely(response);

                if (!response.ok || !data || data.status !== 'success') {
                    let message = data && data.message ? data.message : (this.state.isUpdateMode ? 'Failed to update property record' : 'Failed to save property record');
                    if (data && data.errors) {
                        const errorMessages = Object.values(data.errors)
                            .flat()
                            .map((value) => normalizeText(value))
                            .filter((value) => value.length > 0);
                        if (errorMessages.length) {
                            message = errorMessages.join(' ');
                        }
                    }
                    this.showAlert(message, 'error');
                    return;
                }

                const successMessage = data.message || (this.state.isUpdateMode ? 'Property record updated successfully' : 'Property record saved successfully');

                if (isInstrumentsPage && !this.state.isUpdateMode) {
                    // Close the mortgage modal
                    const modal = document.getElementById('property-form-dialog');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.style.display = 'none';
                    }

                    // Show success alert
                    if (typeof window.Swal !== 'undefined') {
                        await window.Swal.fire({
                            title: 'Mortgage Captured Successfully',
                            text: 'The existing mortgage has been successfully captured. The property location details will be passed to your Deed of Surrender and Release form.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        window.alert('Mortgage Captured Successfully!');
                    }

                    // Capture all values from the mortgage form BEFORE resetting it
                    const plotNoVal = document.querySelector('#property-form-dialog #plotNo') ? document.querySelector('#property-form-dialog #plotNo').value : '';
                    const tpNoVal = document.querySelector('#property-form-dialog #tp_no_prop') ? document.querySelector('#property-form-dialog #tp_no_prop').value : '';
                    const lpknNoVal = document.querySelector('#property-form-dialog #lpkn_no_prop') ? document.querySelector('#property-form-dialog #lpkn_no_prop').value : '';
                    const plotSizeVal = document.querySelector('#property-form-dialog #plot_size_prop') ? document.querySelector('#property-form-dialog #plot_size_prop').value : '';
                    
                    // Street
                    const streetSelect = document.querySelector('#property-form-dialog #streetName');
                    let streetVal = '';
                    let isManualStreet = false;
                    if (streetSelect) {
                        streetVal = streetSelect.value;
                        if (streetVal === 'Others') {
                            isManualStreet = true;
                            const manualStreet = document.querySelector('#property-form-dialog #manual_street_name');
                            if (manualStreet) streetVal = manualStreet.value;
                        }
                    }

                    // District
                    const districtSelect = document.querySelector('#property-form-dialog #desc_district');
                    let districtVal = '';
                    let isManualDistrict = false;
                    if (districtSelect) {
                        districtVal = districtSelect.value;
                        if (districtVal === 'Others') {
                            isManualDistrict = true;
                            const manualDistrict = document.querySelector('#property-form-dialog #manual_district');
                            if (manualDistrict) districtVal = manualDistrict.value;
                        }
                    }

                    // LGA
                    const lgaSelect = document.querySelector('#property-form-dialog #desc_lga');
                    const lgaVal = lgaSelect ? lgaSelect.value : '';

                    // Property Description
                    const propDescVal = document.querySelector('#property-form-dialog #property-description') ? document.querySelector('#property-form-dialog #property-description').value : '';

                    // Extract parties from Mortgage Form before resetting
                    const firstPartyInput = document.querySelector('#property-form-dialog [data-model="firstParty"]');
                    const secondPartyInput = document.querySelector('#property-form-dialog [data-model="secondParty"]');
                    const thirdPartyInput = document.querySelector('#property-form-dialog [data-model="thirdParty"]');
                    const hasTripartiteCheckbox = document.querySelector('#property-form-dialog [data-model="tripartiteHasThird"]');

                    const mortgagorName = firstPartyInput ? firstPartyInput.value : '';
                    const mortgageeName = secondPartyInput ? secondPartyInput.value : '';
                    const coMortgagorName = thirdPartyInput ? thirdPartyInput.value : '';
                    const hasCoMortgagor = hasTripartiteCheckbox ? hasTripartiteCheckbox.checked : false;

                    // Now safely reset form submit button and state so it can be used again
                    this.resetForm();

                    // Pass property location details to the Capture Deed of Surrender / Release form!
                    const mainPlot = document.querySelector('#registration-dialog #plotNumber');
                    if (mainPlot) mainPlot.value = plotNoVal;

                    const mainTp = document.querySelector('#registration-dialog #tp_no');
                    if (mainTp) mainTp.value = tpNoVal;

                    const mainLpkn = document.querySelector('#registration-dialog #lpkn_no');
                    if (mainLpkn) mainLpkn.value = lpknNoVal;

                    const mainSize = document.querySelector('#registration-dialog #size');
                    if (mainSize) mainSize.value = plotSizeVal;

                    const mainStreet = document.querySelector('#registration-dialog #streetName');
                    const mainManualStreet = document.querySelector('#registration-dialog #manual_street_name');
                    if (mainStreet) {
                        if (isManualStreet) {
                            mainStreet.value = 'Others';
                            mainStreet.dispatchEvent(new Event('change'));
                            if (mainManualStreet) {
                                mainManualStreet.value = streetVal;
                                mainManualStreet.dispatchEvent(new Event('input'));
                            }
                        } else {
                            mainStreet.value = streetVal;
                            mainStreet.dispatchEvent(new Event('change'));
                        }
                    }

                    const mainDistrict = document.querySelector('#registration-dialog #desc_district');
                    const mainManualDistrict = document.querySelector('#registration-dialog #manual_district');
                    const surveyDistrict = document.querySelector('#registration-dialog #district');
                    if (mainDistrict) {
                        if (isManualDistrict) {
                            mainDistrict.value = 'Others';
                            mainDistrict.dispatchEvent(new Event('change'));
                            if (mainManualDistrict) {
                                mainManualDistrict.value = districtVal;
                                mainManualDistrict.dispatchEvent(new Event('input'));
                            }
                        } else {
                            mainDistrict.value = districtVal;
                            mainDistrict.dispatchEvent(new Event('change'));
                        }
                    }
                    if (surveyDistrict) {
                        surveyDistrict.value = districtVal;
                        surveyDistrict.dispatchEvent(new Event('change'));
                    }

                    const mainLga = document.querySelector('#registration-dialog #desc_lga');
                    const surveyLga = document.querySelector('#registration-dialog #lga');
                    if (mainLga) {
                        mainLga.value = lgaVal;
                        mainLga.dispatchEvent(new Event('change'));
                    }
                    if (surveyLga) {
                        surveyLga.value = lgaVal;
                        surveyLga.dispatchEvent(new Event('change'));
                    }

                    // Activate Survey Info Checkbox if LGA or District has a value
                    const surveyInfoCheckbox = document.querySelector('#registration-dialog #surveyInfo');
                    if (surveyInfoCheckbox && (lgaVal || districtVal)) {
                        surveyInfoCheckbox.checked = true;
                        surveyInfoCheckbox.dispatchEvent(new Event('change'));
                        
                        // Also show survey section
                        const surveySection = document.getElementById('survey-info-section');
                        if (surveySection) {
                            surveySection.classList.remove('hidden');
                        }
                    }

                    const mainDesc = document.querySelector('#registration-dialog #propertyDescription');
                    if (mainDesc) {
                        mainDesc.value = propDescVal;
                    }

                    // BACKFILL & SWITCH PARTIES:
                    // Mortgagee (Second Party) -> Surrenderer/Releasor (firstPartyName in capture modal)
                    // Mortgagor (First Party) -> Surrenderee/Releasee (secondPartyName in capture modal)
                    const mainFirstParty = document.querySelector('#registration-dialog #firstPartyName');
                    if (mainFirstParty) {
                        mainFirstParty.value = mortgageeName;
                        mainFirstParty.dispatchEvent(new Event('input'));
                    }

                    const mainSecondParty = document.querySelector('#registration-dialog #secondPartyName');
                    if (mainSecondParty) {
                        mainSecondParty.value = mortgagorName;
                        mainSecondParty.dispatchEvent(new Event('input'));
                    }

                    // Co-Mortgagor -> Co-Surrenderor
                    const mainHasThirdParty = document.querySelector('#registration-dialog #hasThirdParty');
                    const mainCoSurrenderor = document.querySelector('#registration-dialog #coSurrenderorName');
                    const mainThirdPartyContainer = document.querySelector('#registration-dialog #thirdPartyContainer');
                    if (hasCoMortgagor && coMortgagorName) {
                        if (mainHasThirdParty) {
                            mainHasThirdParty.checked = true;
                            mainHasThirdParty.dispatchEvent(new Event('change'));
                        }
                        if (mainThirdPartyContainer) {
                            mainThirdPartyContainer.classList.remove('hidden');
                        }
                        if (mainCoSurrenderor) {
                            mainCoSurrenderor.value = coMortgagorName;
                            mainCoSurrenderor.dispatchEvent(new Event('input'));
                        }
                    }

                    // Check if we need to call checkDuplicate to re-evaluate history and recognize the mortgage
                    if (typeof window.checkDuplicate === 'function') {
                        window.checkDuplicate();
                    }

                } else {
                    if (typeof window.Swal !== 'undefined') {
                        await window.Swal.fire({
                            title: this.state.isUpdateMode ? 'Updated' : 'Saved',
                            text: successMessage,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        window.alert(successMessage);
                    }

                    this.exitUpdateMode();
                    window.location.reload();
                }
            } catch (error) {
                const message = error && error.message ? error.message : (this.state.isUpdateMode ? 'Failed to update property record' : 'Failed to save property record');
                this.showAlert(message, 'error');
            } finally {
                this.setSubmitting(false);
            }
        }

        buildUpdateUrl(recordId) {
            const targetId = encodeURIComponent(recordId);
            if (this.updateBaseUrl) {
                const trimmed = this.updateBaseUrl.replace(/\/$/, '');
                return `${trimmed}/${targetId}`;
            }
            if (this.formActionOriginal) {
                const trimmed = this.formActionOriginal.replace(/\/$/, '');
                return `${trimmed}/${targetId}`;
            }
            return '';
        }

        getRecordDisplayLabel(record) {
            const candidates = [
                record.mlsFNo,
                record.mlsfno,
                record.kangisFileNo,
                record.NewKANGISFileno,
                record.fileno,
                record.file_number,
                record.temp_fileno,
                record.prop_id,
                record.property_id,
                record.propertyId,
                record.id ? `#${record.id}` : ''
            ];

            for (const candidate of candidates) {
                const normalized = normalizeText(candidate);
                if (normalized) {
                    return normalized;
                }
            }
            return '';
        }

        resolveRecordId(record) {
            if (!record) {
                return null;
            }

            const candidates = [
                record.id,
                record.prop_id,
                record.property_id,
                record.propertyId,
                record.record_id,
                record.recordId
            ];

            for (const candidate of candidates) {
                if (candidate !== undefined && candidate !== null && `${candidate}`.length) {
                    return candidate;
                }
            }

            return null;
        }

        async parseJsonSafely(response) {
            try {
                return await response.clone().json();
            } catch (error) {
                return null;
            }
        }

        showAlert(message, type) {
            const text = message || 'An unexpected error occurred.';
            if (typeof window.Swal !== 'undefined') {
                window.Swal.fire({
                    title: type === 'error' ? 'Update Failed' : 'Notice',
                    text,
                    icon: type === 'error' ? 'error' : 'info'
                });
            } else {
                window.alert(text);
            }
        }

        resolveCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && meta.getAttribute('content')) {
                return meta.getAttribute('content');
            }
            const tokenInput = this.form ? this.form.querySelector('input[name="_token"]') : null;
            return tokenInput ? tokenInput.value : '';
        }
    }

    function initialisePraController() {
        const container = document.querySelector('.pra-property-form[data-pra-form]');
        if (!container) {
            return;
        }

        const controller = new PraFormController(container);
        controller.init();

        window.PraFormController = {
            container,
            controller,
            getPublicApi() {
                return controller.publicApi;
            },
            setFormMode(mode) {
                controller.setState('formMode', mode);
            },
            /**
             * Announce a file number that was written straight into the form inputs
             * by an outside opener (Legal Search prefills #fileno itself). Without
             * this the fileNumber state change never fires, so the file-driven
             * panels — the PRA matching-records card and the "Existing Records for
             * this File" table — stay empty on a prefilled dialog.
             */
            setFileNumber(value) {
                const fileno = normalizeText(value);
                const changed = controller.state.fileNumber !== fileno;

                controller.setState('fileNumber', fileno);

                // setState is a no-op when the value is unchanged — which is exactly
                // what happens when the dialog is reopened for the same file. Drive
                // the file-keyed panels directly in that case so they still load.
                if (!changed) {
                    if (fileno) {
                        controller.fetchAndDisplayMatchingRecords(fileno);
                        controller.loadExistingRecordsTable(fileno);
                    } else {
                        controller.clearExistingRecordsTable();
                    }
                }
            },
            /** Reload just the existing-records table + duplicate check. */
            refreshExistingRecords(value) {
                const fileno = normalizeText(value !== undefined ? value : controller.state.fileNumber);
                if (fileno) {
                    controller.loadExistingRecordsTable(fileno);
                } else {
                    controller.clearExistingRecordsTable();
                }
            },
            refreshDescription() {
                controller.updatePropertyDescription();
            },
            resetForm() {
                controller.resetForm();
            },
            openForEditing(record) {
                return controller.openForEditing(record);
            },
            resetUpdateMode() {
                controller.exitUpdateMode();
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialisePraController);
    } else {
        initialisePraController();
    }
})(window, document);

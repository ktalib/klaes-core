/**
 * Consent Applications JS Logic
 */

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('generate-modal');
    const openBtn = document.getElementById('open-generate-modal');
    const closeBtns = document.querySelectorAll('.close-modal');
    const overlay = document.getElementById('modal-overlay');
    const form = document.getElementById('consent-form');
    const consentTypeSelect = document.getElementById('consent_type');

    // UI Elements
    const partyRoleLabel = document.getElementById('party-role-label');
    const partyNameLabel = document.getElementById('party-name-label');
    const applicantNameLabel = document.getElementById('applicant-name-label');
    const APPLICANT_NAME_DEFAULT_HTML = applicantNameLabel ? applicantNameLabel.innerHTML : '';
    const financialTitle = document.getElementById('financial-title');
    const financialLabel = document.getElementById('financial-label');
    const financialWordsInput = document.querySelector('input[name="consideration_words"]');
    const financialInput = document.querySelector('input[name="consideration"]');

    const selectFilenoBtn = document.getElementById('select-fileno-btn');
    const filenoInput = document.getElementById('file_number');
    const selectionIndicator = document.getElementById('file-selection-indicator');
    const applicationIdInput = document.getElementById('application_id');
    const modalTitle = document.getElementById('modal-title');
    const submitBtn = document.querySelector('button[type="submit"][form="consent-form"]');
    const consentVariant = window.consentFormVariant || 'letter';
    const letterDateInput = document.getElementById('letter_date');
    const applicationDateInput = document.getElementById('application_submitted_date');
    const applicationTypeInput = document.getElementById('application_type');
    const rightOfOccupancyNumberHiddenInput = document.getElementById('right_of_occupancy_number');
    const rightOfOccupancyNumberDisplayInput = document.getElementById('right_of_occupancy_number_display');
    const rightOfOccupancyNumberWrapper = document.getElementById('right-of-occupancy-number-wrapper');
    const rightOfOccupancyLandUseSelect = document.getElementById('right_of_occupancy_landuse');
    const rightOfOccupancyPurposeSelect = document.getElementById('purpose_of_right_of_occupancy');
    const specialMortgageTermsWrapper = document.getElementById('special-mortgage-terms-wrapper');
    const specialMortgageTermsInput = document.getElementById('special_mortgage_terms');
    const dateOfGrantInput = document.getElementById('date_of_grant');
    const wizardSteps = [...document.querySelectorAll('.wizard-step')];
    const wizardIndicators = [...document.querySelectorAll('[data-step-indicator]')];
    const wizardPrevBtn = document.getElementById('wizard-prev-btn');
    const wizardNextBtn = document.getElementById('wizard-next-btn');
    let currentEditingAppType = null; // Track original application_type when editing
    let currentWizardStep = 1;
    let formIsInteractive = true; // false in view mode — keeps dynamically added rows read-only

    function applyVariantSettings() {
        const isApplication = consentVariant === 'application';
        document.querySelectorAll('.variant-application').forEach(el => {
            el.classList.toggle('hidden', !isApplication);
        });
        document.querySelectorAll('.variant-letter').forEach(el => {
            el.classList.toggle('hidden', isApplication);
        });

        const toggleSectionInputs = (selector, enabled) => {
            document.querySelectorAll(`${selector} input, ${selector} select, ${selector} textarea`).forEach(el => {
                // Skip applicant address fields — they stay permanently disabled (auto-filled from correspondence)
                if (el.classList.contains('address-component-applicant')) return;
                el.disabled = !enabled;
            });
        };
        toggleSectionInputs('.variant-application', isApplication);
        toggleSectionInputs('.variant-letter', !isApplication);

        if (letterDateInput) {
            letterDateInput.required = !isApplication;
            letterDateInput.disabled = isApplication;
            if (isApplication) letterDateInput.value = '';
        }
        if (applicationDateInput) {
            applicationDateInput.required = isApplication;
            applicationDateInput.disabled = !isApplication;
            if (!isApplication) applicationDateInput.value = '';
        }

        if (applicationTypeInput) {
            // Preserve application_type when editing - only set default for new records
            if (currentEditingAppType !== null) {
                applicationTypeInput.value = currentEditingAppType;
            } else {
                applicationTypeInput.value = isApplication ? 'application_for_censent' : '';
            }
        }
    }

    function getManualInputForSelect(selectEl) {
        if (!selectEl) return null;
        const ref = selectEl.dataset.manualInput;
        if (!ref) return null;
        if (ref === 'next') {
            let sibling = selectEl.nextElementSibling;
            while (sibling && sibling.tagName !== 'INPUT') {
                sibling = sibling.nextElementSibling;
            }
            return sibling || null;
        }
        return document.querySelector(ref);
    }

    function getSelectOrManualValue(selectEl) {
        if (!selectEl) return '';
        const value = selectEl.value ? selectEl.value.trim() : '';
        if (value === 'Other') {
            const manualInput = getManualInputForSelect(selectEl);
            return manualInput ? manualInput.value.trim() : '';
        }
        return value;
    }

    function handleManualStreetToggle(selectEl) {
        const manualInput = getManualInputForSelect(selectEl);
        if (!manualInput) return;
        const shouldShow = selectEl.value === 'Other';
        manualInput.classList.toggle('hidden', !shouldShow);
        // Street is optional; show manual input without forcing required validation.
        manualInput.required = false;
        if (!shouldShow) {
            manualInput.value = '';
        }
        const addressType = selectEl.dataset.addressType;
        if (addressType) updateBuiltAddress(addressType);
    }

    function setStreetSelectValue(selectEl, value) {
        if (!selectEl) return;
        const manualInput = getManualInputForSelect(selectEl);
        const trimmed = (value || '').trim();
        const hasOption = trimmed && [...selectEl.options].some(opt => opt.value === trimmed);

        if (trimmed && hasOption) {
            selectEl.value = trimmed;
            if (manualInput) {
                manualInput.classList.add('hidden');
                manualInput.required = false;
                manualInput.value = '';
            }
        } else if (trimmed) {
            selectEl.value = 'Other';
            if (manualInput) {
                manualInput.classList.remove('hidden');
                manualInput.required = false;
                manualInput.value = trimmed;
            }
        } else {
            selectEl.value = '';
            if (manualInput) {
                manualInput.classList.add('hidden');
                manualInput.required = false;
                manualInput.value = '';
            }
        }
    }

    // Helper to parse address string back into components
    function setDistrictValue(selectEl, otherEl, value) {
        if (!selectEl || !otherEl) return;
        if (value) {
            if ([...selectEl.options].some(o => o.value === value && value !== 'Other')) {
                selectEl.value = value;
                otherEl.classList.add('hidden');
                otherEl.value = '';
                otherEl.required = false;
            } else {
                selectEl.value = 'Other';
                otherEl.classList.remove('hidden');
                otherEl.value = value !== 'Other' ? value : '';
                otherEl.required = true;
            }
        } else {
            selectEl.value = '';
            otherEl.classList.add('hidden');
            otherEl.value = '';
            otherEl.required = false;
        }
    }

    function setInteractiveFormState(isInteractive) {
        formIsInteractive = isInteractive;

        document.querySelectorAll('#add-fileno-btn, #select-fileno-btn, .additional-fileno-search-btn, .remove-fileno-btn').forEach(btn => {
            btn.disabled = !isInteractive;
            btn.classList.toggle('opacity-50', !isInteractive);
            btn.classList.toggle('cursor-not-allowed', !isInteractive);
        });

        form.querySelectorAll('input, select, textarea').forEach(el => {
            // Skip applicant address fields — they stay permanently disabled (auto-filled from correspondence)
            if (el.classList.contains('address-component-applicant')) return;
            if (!isInteractive && el.id === 'file_number') {
                el.disabled = true;
                el.classList.add('bg-slate-200', 'cursor-not-allowed');
                return;
            }
            if (el.id === 'right_of_occupancy_number_display') {
                el.disabled = true;
                el.classList.add('bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
                return;
            }
            if (isInteractive) {
                el.disabled = false;
                el.classList.remove('bg-slate-200', 'cursor-not-allowed');
            } else {
                el.disabled = true;
                el.classList.add('bg-slate-200', 'cursor-not-allowed');
            }
        });
    }

    // Read-only: issued by the server on create and never reassigned.
    function setTrackingNo(value) {
        const el = document.getElementById('application_tracking_no_value');
        if (el) el.textContent = (value || '').trim() || 'Assigned on save';
    }

    function setRightOfOccupancyNumber(value) {
        const normalizedValue = (value || '').trim();
        if (rightOfOccupancyNumberHiddenInput) {
            rightOfOccupancyNumberHiddenInput.value = normalizedValue;
        }
        if (rightOfOccupancyNumberDisplayInput) {
            rightOfOccupancyNumberDisplayInput.value = normalizedValue;
            rightOfOccupancyNumberDisplayInput.disabled = true;
            rightOfOccupancyNumberDisplayInput.classList.add('bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
        }
    }

    function updateApplicantNamePreview() {
        const wrapper = document.getElementById('applicant-name-preview-wrapper');
        const preview = document.getElementById('applicant_name_preview');
        if (!wrapper || !preview) return;
        const applicantInput = document.querySelector('input[name="applicant_name"]');
        const name = applicantInput ? (applicantInput.value || '').trim() : '';
        const fileNo = rightOfOccupancyNumberHiddenInput ? (rightOfOccupancyNumberHiddenInput.value || '').trim() : '';
        preview.textContent = name;
        if (name && fileNo) {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
        }
    }

    function filterRightOfOccupancyPurposeOptions() {
        if (!rightOfOccupancyLandUseSelect || !rightOfOccupancyPurposeSelect) {
            return;
        }

        const selectedLandUseOption = rightOfOccupancyLandUseSelect.options[rightOfOccupancyLandUseSelect.selectedIndex];
        const selectedLandUseId = selectedLandUseOption ? selectedLandUseOption.dataset.landuseId : '';
        const currentPurposeValue = rightOfOccupancyPurposeSelect.value;
        let hasCurrentPurpose = false;

        [...rightOfOccupancyPurposeSelect.options].forEach((option, index) => {
            if (index === 0) return;
            const optionLandUseId = option.dataset.landuseId || '';
            const shouldShow = !selectedLandUseId || optionLandUseId === selectedLandUseId;
            option.hidden = !shouldShow;
            option.disabled = !shouldShow;
            if (shouldShow && option.value === currentPurposeValue) {
                hasCurrentPurpose = true;
            }
        });

        if (!hasCurrentPurpose) {
            rightOfOccupancyPurposeSelect.value = '';
        }
    }

    function toggleMortgageSpecificFields(type) {
        const isMortgage = type === 'Mortgage';

        if (rightOfOccupancyNumberWrapper) {
            // The additional rows live inside this wrapper, so they hide with it.
            rightOfOccupancyNumberWrapper.classList.toggle('hidden', isMortgage);
        }

        if (specialMortgageTermsWrapper) {
            specialMortgageTermsWrapper.classList.toggle('hidden', !isMortgage);
        }

        if (isMortgage) {
            setRightOfOccupancyNumber('');
        } else if (filenoInput && !rightOfOccupancyNumberHiddenInput?.value) {
            setRightOfOccupancyNumber(filenoInput.value || '');
        }

        if (specialMortgageTermsInput) {
            specialMortgageTermsInput.required = false;
            if (!isMortgage) {
                specialMortgageTermsInput.value = '';
            }
        }
    }

    function isElementVisible(el) {
        return !!(el && el.offsetParent !== null);
    }

    function validateWizardStep(stepNumber) {
        const stepEl = wizardSteps.find(step => Number(step.dataset.step) === stepNumber);
        if (!stepEl) return true;

        const inputs = [...stepEl.querySelectorAll('input, select, textarea')]
            .filter(el => !el.disabled && isElementVisible(el));

        for (const input of inputs) {
            if (typeof input.checkValidity === 'function' && !input.checkValidity()) {
                input.reportValidity();
                return false;
            }
        }
        return true;
    }

    function updateWizardControls() {
        const totalSteps = wizardSteps.length || 1;
        wizardSteps.forEach(step => {
            step.classList.toggle('hidden', Number(step.dataset.step) !== currentWizardStep);
        });

        // The file number is set from several places — the picker, a lookup of an
        // existing consent, or an edit — and a programmatic .value assignment
        // fires no event. Rather than hook each one, load the bill whenever the
        // Payments step is opened; loadConsentBill() skips a refetch when the
        // file has not changed.
        if (currentWizardStep === 4) {
            loadConsentBill(filenoInput ? filenoInput.value.trim() : '');
        }

        wizardIndicators.forEach(indicator => {
            const stepNumber = Number(indicator.dataset.stepIndicator);
            const isCompletedOrActive = stepNumber <= currentWizardStep;
            const circle = indicator.querySelector(`#wizard-circle-${stepNumber}`);
            const label = indicator.querySelector(`#wizard-label-${stepNumber}`);

            if (circle) {
                circle.classList.toggle('border-teal-500', isCompletedOrActive);
                circle.classList.toggle('bg-teal-500', isCompletedOrActive);
                circle.classList.toggle('text-white', isCompletedOrActive);
                circle.classList.toggle('border-slate-300', !isCompletedOrActive);
                circle.classList.toggle('bg-white', !isCompletedOrActive);
                circle.classList.toggle('text-slate-400', !isCompletedOrActive);
            }

            if (label) {
                label.classList.toggle('text-teal-600', isCompletedOrActive);
                label.classList.toggle('text-slate-400', !isCompletedOrActive);
            }
        });

        document.querySelectorAll('.wizard-connector').forEach(connector => {
            const beforeStep = Number(connector.dataset.beforeStep || 0);
            const bar = connector.querySelector('.connector-bar');
            if (!bar) return;
            const isActive = beforeStep <= currentWizardStep;
            bar.classList.toggle('bg-teal-500', isActive);
            bar.classList.toggle('bg-slate-200', !isActive);
        });

        if (wizardPrevBtn) wizardPrevBtn.classList.toggle('hidden', currentWizardStep === 1);
        if (wizardNextBtn) wizardNextBtn.classList.toggle('hidden', currentWizardStep === totalSteps);
        if (submitBtn) submitBtn.classList.toggle('hidden', currentWizardStep !== totalSteps);
    }

    function resetWizard() {
        currentWizardStep = 1;
        updateWizardControls();
    }

    function parseAndPopulateAddress(addressString, type) {
        if (!addressString) return;

        // Remove trailing period and split by comma, then clean each part
        addressString = addressString.trim();
        const parts = addressString.split(',').map(p => p.trim().replace(/\.+$/, ''));
        let houseNo = '', street = '', district = '', lga = '', state = '';

        // Address format: No. {houseNo}, {street}, {district}, {lga}, {state} State
        // Parse positionally after identifying house number and state

        let remainingParts = [];
        parts.forEach(part => {
            if (part.startsWith('No. ') || part.startsWith('House No ')) {
                houseNo = part.replace(/^(No\. |House No )/, '');
            } else if (part.endsWith(' State') || part.match(/\s+State$/i)) {
                state = part.replace(/\s*State$/i, '').trim();
            } else {
                remainingParts.push(part);
            }
        });

        // Remaining parts in order: street, district, lga
        if (remainingParts.length >= 1) street = remainingParts[0];
        if (remainingParts.length >= 2) district = remainingParts[1];
        if (remainingParts.length >= 3) lga = remainingParts[2];

        const prefix = type === 'applicant'
            ? 'applicant'
            : (type === 'party' ? 'party' : (type === 'property' ? 'property' : 'correspondence'));

        let houseEl = document.getElementById(`${prefix}_house_no`);
        let streetEl = document.getElementById(`${prefix}_street`);
        let distSelect = document.getElementById(`${prefix}_district`);
        let distOther = document.getElementById(`${prefix}_district_other`);
        let lgaSelect = document.getElementById(`${prefix}_lga`);
        let stateSelect = document.getElementById(`${prefix}_state`);

        if (houseEl) houseEl.value = houseNo;
        if (streetEl) setStreetSelectValue(streetEl, street);
        if (distSelect && distOther) setDistrictValue(distSelect, distOther, district);

        // Set state - add option if not exists
        if (stateSelect && state) {
            if ([...stateSelect.options].some(o => o.value === state)) {
                stateSelect.value = state;
            } else {
                const opt = document.createElement('option');
                opt.value = state;
                opt.textContent = state;
                opt.selected = true;
                stateSelect.appendChild(opt);
            }
        }

        // Set LGA. The list is normally loaded by the state select's change
        // event, which a programmatic populate never fires — so on edit the
        // dropdown stayed empty and the stored LGA could not be reselected.
        // Load it here instead, then apply the value.
        if (lgaSelect) {
            ensureLgaOptions(prefix, state, lga);
        }

        // Trigger preview update
        updateBuiltAddress(type);
    }

    function splitNationalityState(value) {
        if (!value || typeof value !== 'string') {
            return { nationality: '', stateOfOrigin: '' };
        }
        const separators = ['/', ',', '-'];
        for (const separator of separators) {
            if (value.includes(separator)) {
                const parts = value.split(separator).map(part => part.trim()).filter(Boolean);
                return {
                    nationality: parts[0] || '',
                    stateOfOrigin: parts.slice(1).join(' ') || ''
                };
            }
        }
        return { nationality: value.trim(), stateOfOrigin: '' };
    }

    async function fetchApplicationByFileNumber(fileNumber) {
        const response = await fetch(`/consent-applications/lookup?file_number=${encodeURIComponent(fileNumber)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Lookup failed');
        }
        return result.data;
    }

    /**
     * Backfill the Payments step from the file's valuation.
     *
     * Display only. The server recomputes the same figures on save from the
     * same valuation, so nothing here is posted or trusted — if the two ever
     * disagree, what gets stored and printed is the server's.
     */
    let lastBilledFileNumber = null;

    async function loadConsentBill(fileNumber) {
        // Entering the Payments step repeatedly must not re-hit the endpoint,
        // and must not re-run the consideration prefill over an edited figure.
        if (fileNumber && fileNumber === lastBilledFileNumber) {
            return;
        }
        lastBilledFileNumber = fileNumber || null;

        const statusEl = document.getElementById('bill-status');
        const amountEl = document.getElementById('bill_valuation_amount');
        const sourceEl = document.getElementById('bill_valuation_source');

        if (!statusEl || !amountEl) {
            return;
        }

        const money = (v) => '₦' + Number(v || 0).toLocaleString('en-NG', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });

        const setRows = (bill) => {
            // Clearing falls back to each row's configured default, not zero:
            // a fixed fee does not depend on a valuation, so showing ₦0.00 for
            // it when none is linked would misstate the charge.
            const byKey = {
                cgt_amount: 'bill_cgt',
                stamp_duty_amount: 'bill_stamp_duty',
                registration_fee: 'bill_registration_fee',
                assignment_fee: 'bill_assignment_fee',
                processing_fee: 'bill_processing_fee'
            };

            let fallbackTotal = 0;
            Object.keys(byKey).forEach((key) => {
                const el = document.getElementById(byKey[key]);
                if (!el) return;
                const row = el.closest('.bill-row');
                const fallback = row ? parseFloat(row.dataset.default || '0') || 0 : 0;
                const value = bill ? bill[key] : fallback;
                fallbackTotal += Number(value) || 0;
                el.textContent = money(value);
            });

            const totalEl = document.getElementById('bill_total');
            if (totalEl) {
                totalEl.textContent = money(bill ? bill.bill_total : fallbackTotal);
            }
        };

        const warnEl = document.getElementById('bill-no-valuation');
        const warnMsgEl = document.getElementById('bill-no-valuation-message');
        const showWarning = (message) => {
            if (warnEl) warnEl.classList.toggle('hidden', !message);
            if (warnMsgEl && message) warnMsgEl.textContent = message;
        };

        if (!fileNumber) {
            statusEl.textContent = 'Select a file to load the valuation';
            statusEl.className = 'text-xs font-medium text-slate-400';
            amountEl.value = '';
            if (sourceEl) sourceEl.textContent = '';
            showWarning(null);
            setRows(null);
            return;
        }

        statusEl.textContent = 'Loading valuation…';
        statusEl.className = 'text-xs font-medium text-slate-400';

        try {
            const response = await fetch(
                `/consent-applications/valuation-bill?file_number=${encodeURIComponent(fileNumber)}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const result = await response.json();

            // A missing or zero valuation shows the reason and leaves the rows
            // at zero: it must never read as a genuine ₦0.00 assessment.
            if (!result.success || !result.has_valuation) {
                const message = result.message || 'No valuation available for this file.';
                statusEl.textContent = 'No valuation';
                statusEl.className = 'text-xs font-medium text-amber-600';
                amountEl.value = '';
                if (sourceEl) sourceEl.textContent = '';
                showWarning(message);
                setRows(null);
                return;
            }

            const bill = result.data;
            amountEl.value = money(bill.valuation_amount);
            setRows(bill);
            statusEl.textContent = 'Valuation linked';
            statusEl.className = 'text-xs font-medium text-emerald-600';
            showWarning(null);
            prefillConsiderationFromValuation(bill.valuation_amount);
            if (sourceEl) {
                sourceEl.textContent = 'Valuation report #' + bill.valuation_report_id
                    + ' · ' + bill.valuation_reference + ' · ' + bill.valuation_status;
            }
        } catch (error) {
            console.error('Consent bill lookup failed:', error);
            statusEl.textContent = 'Could not load the valuation for this file.';
            statusEl.className = 'text-xs font-medium text-red-600';
            showWarning('The valuation service could not be reached. Try again before saving.');
            setRows(null);
        }
    }

    /**
     * Backfill the declared consideration from the file's valuation.
     *
     * Prefill only — the field stays editable, because the consideration is the
     * price the parties declared and it does not have to equal the Ministry's
     * assessed value. A figure the operator typed, or one loaded from a saved
     * record, is never overwritten; only a value this function put there is
     * replaced when a different file is selected.
     */
    function prefillConsiderationFromValuation(amount) {
        const figures = document.querySelector('input[name="consideration"]');

        if (!figures || !amount || Number(amount) <= 0) {
            return;
        }

        const isOurs = figures.dataset.autofilled === '1';
        if (figures.value.trim() !== '' && !isOurs) {
            return;
        }

        figures.value = Number(amount).toLocaleString('en-NG', { maximumFractionDigits: 0 });
        figures.dataset.autofilled = '1';

        // Reuse the existing handler so the read-only words field stays in step
        // instead of this having its own copy of the conversion.
        figures.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Typing clears the flag, so an edited figure is treated as the operator's
    // own and survives any later file reselect. A programmatic dispatch is not
    // trusted, so the prefill above does not clear its own flag.
    const considerationField = document.querySelector('input[name="consideration"]');
    if (considerationField) {
        considerationField.addEventListener('input', function (event) {
            if (event.isTrusted) {
                delete this.dataset.autofilled;
            }
        });
    }

    /**
     * Per-line manual overrides on the consent bill.
     *
     * The override inputs gain a name only while a row is actually open, so an
     * untouched row posts nothing at all and the server keeps its calculated
     * amount. Reverting strips the name again rather than merely blanking the
     * value, which would otherwise post an empty override on every save.
     */
    function initBillOverrides() {
        const section = document.getElementById('consent-bill-section');
        if (!section || section.dataset.overridesReady === '1') {
            return;
        }
        section.dataset.overridesReady = '1';

        const rowOf = (el) => el.closest('.bill-row');

        section.addEventListener('click', function (event) {
            const editBtn = event.target.closest('.bill-edit-btn');
            const revertBtn = event.target.closest('.bill-revert-btn');

            if (editBtn) {
                const row = rowOf(editBtn);
                const panel = row.querySelector('.bill-override');
                const amount = panel.querySelector('[data-override-amount]');
                const reason = panel.querySelector('[data-override-reason]');
                const key = row.dataset.fee;
                const opening = panel.classList.contains('hidden');

                panel.classList.toggle('hidden', !opening);

                if (opening) {
                    amount.name = `bill_override[${key}]`;
                    reason.name = `bill_override_reason[${key}]`;
                    // Seed with the calculated figure so an override is an edit
                    // of the real number, not a blank box.
                    if (!amount.value) {
                        const shown = row.querySelector('.font-mono');
                        amount.value = shown ? shown.textContent.replace(/[^0-9.]/g, '') : '';
                    }
                    amount.focus();
                }
                return;
            }

            if (revertBtn) {
                const row = rowOf(revertBtn);
                const panel = row.querySelector('.bill-override');
                const amount = panel.querySelector('[data-override-amount]');
                const reason = panel.querySelector('[data-override-reason]');

                panel.classList.add('hidden');
                amount.value = '';
                reason.value = '';
                amount.removeAttribute('name');
                reason.removeAttribute('name');
                row.querySelector('.bill-row-note').classList.remove('text-amber-600');
                updateBillTotalPreview();
            }
        });

        section.addEventListener('input', function (event) {
            if (event.target.matches('[data-override-amount]')) {
                const row = rowOf(event.target);
                row.querySelector('.bill-row-note').classList.add('text-amber-600');
                updateBillTotalPreview();
            }
        });
    }

    /**
     * Preview total. The server recomputes and stores its own, so this is only
     * to stop the displayed total contradicting an override on screen.
     */
    function updateBillTotalPreview() {
        const section = document.getElementById('consent-bill-section');
        const totalEl = document.getElementById('bill_total');
        if (!section || !totalEl) {
            return;
        }

        let total = 0;
        section.querySelectorAll('.bill-row').forEach((row) => {
            const override = row.querySelector('[data-override-amount]');
            const useOverride = override && override.name && override.value.trim() !== '';
            const raw = useOverride
                ? override.value
                : (row.querySelector('.font-mono') || {}).textContent || '0';
            total += parseFloat(String(raw).replace(/[^0-9.]/g, '')) || 0;
        });

        totalEl.textContent = '₦' + total.toLocaleString('en-NG', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    initBillOverrides();

    function populateFromApplicationRecord(appData) {
        if (!appData) {
            return;
        }

        applicationIdInput.value = appData.id || '';
        filenoInput.value = appData.file_number || filenoInput.value;
        loadConsentBill(filenoInput.value);

        const setInputValue = (name, value) => {
            const input = document.querySelector(`[name="${name}"]`);
            if (input) input.value = value || '';
        };

        setInputValue('applicant_name', appData.applicant_name);
        setInputValue('party_name', appData.party_name);
        setInputValue('consideration', appData.consideration);
        setInputValue('consideration_words', appData.consideration_words);
        setRightOfOccupancyNumber(appData.right_of_occupancy_number || appData.file_number || '');
        updateApplicantNamePreview();
        setInputValue('right_of_occupancy_landuse', appData.right_of_occupancy_landuse);
        filterRightOfOccupancyPurposeOptions();
        setInputValue('purpose_of_right_of_occupancy', appData.purpose_of_right_of_occupancy);
        setInputValue('original_holder_name', appData.original_holder_name);
        setInputValue('correspondence_address', appData.correspondence_address);
        setInputValue('postal_address_gsm', appData.postal_address_gsm);
        setInputValue('nationality_state_of_origin', appData.nationality_state_of_origin);
        if (appData.nationality || appData.state_of_origin) {
            setInputValue('nationality', appData.nationality);
            setInputValue('state_of_origin', appData.state_of_origin);
        } else if (appData.nationality_state_of_origin) {
            const parsed = splitNationalityState(appData.nationality_state_of_origin);
            setInputValue('nationality', parsed.nationality);
            setInputValue('state_of_origin', parsed.stateOfOrigin);
        }
        setInputValue('stage_of_development', appData.stage_of_development);
        setDistrictValue(
            document.getElementById('location_rofo_district'),
            document.getElementById('location_rofo_district_other'),
            appData.location_of_right_of_occupancy
        );
        if (dateOfGrantInput) {
            dateOfGrantInput.value = appData.date_of_grant ? String(appData.date_of_grant).split(' ')[0] : '';
        }
        setInputValue('special_mortgage_terms', appData.special_mortgage_terms);

        if (letterDateInput) {
            const letterDate = appData.application_date ? appData.application_date.split(' ')[0] : '';
            letterDateInput.value = letterDate;
        }
        if (applicationDateInput) {
            const applicationDate = appData.application_submitted_date ? appData.application_submitted_date.split(' ')[0] : '';
            applicationDateInput.value = applicationDate;
        }

        if (consentTypeSelect && appData.consent_type) {
            consentTypeSelect.value = appData.consent_type;
            consentTypeSelect.dispatchEvent(new Event('change'));
        }

        parseAndPopulateAddress(appData.applicant_address, 'applicant');
        parseAndPopulateAddress(appData.party_address, 'party');
        parseAndPopulateAddress(appData.property_description, 'property');
        parseAndPopulateAddress(appData.correspondence_address || appData.applicant_address, 'correspondence');
        syncApplicantWithCorrespondence();

        if (document.getElementById('applicant_address_hidden')) {
            document.getElementById('applicant_address_hidden').value = appData.applicant_address || '';
        }
        if (document.getElementById('party_address_hidden')) {
            document.getElementById('party_address_hidden').value = appData.party_address || '';
        }
        if (document.getElementById('property_address_hidden')) {
            document.getElementById('property_address_hidden').value = appData.property_description || '';
        }
        if (document.getElementById('correspondence_address_hidden')) {
            document.getElementById('correspondence_address_hidden').value = appData.correspondence_address || appData.applicant_address || '';
        }

        const applicantPreview = document.getElementById('applicant_address_preview');
        const partyPreview = document.getElementById('party_address_preview');
        const propertyPreview = document.getElementById('property_address_preview');
        const correspondencePreview = document.getElementById('correspondence_address_preview');
        if (applicantPreview) applicantPreview.textContent = appData.applicant_address || 'No address built yet...';
        if (partyPreview) partyPreview.textContent = appData.party_address || 'No address built yet...';
        if (propertyPreview) propertyPreview.textContent = appData.property_description || 'No description built yet...';
        if (correspondencePreview) correspondencePreview.textContent = document.getElementById('correspondence_address_hidden')?.value || 'No address built yet...';

        populateAdditionalProperties(appData.additional_properties);

        // These were previously restored only by the Edit button, so loading an
        // existing application by file number silently dropped them on save.
        setTrackingNo(appData.application_tracking_no);
        populateAdditionalParties(appData.additional_parties);
        if (typeof populateAdditionalApplicants === 'function') {
            populateAdditionalApplicants(appData.additional_applicants);
        }
        if (applicationTypeInput && appData.application_type) {
            applicationTypeInput.value = appData.application_type;
            currentEditingAppType = appData.application_type;
        }

        setInteractiveFormState(true);

        if (selectionIndicator) selectionIndicator.classList.remove('hidden');
    }

    // UI State Management
    function toggleModal(show, mode = 'create') {
        if (show) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            applyVariantSettings();

            setInteractiveFormState(true);
            resetWizard();

            if (mode === 'create') {
                if (consentVariant === 'application') {
                    modalTitle.textContent = 'Create Application';
                    submitBtn.querySelector('span').textContent = 'Create Application';
                } else {
                    modalTitle.textContent = 'Generate Letter';
                    submitBtn.querySelector('span').textContent = 'Generate Letter';
                }
                applicationIdInput.value = '';
                setTrackingNo('');
                // Default to Kano for new applications
                document.getElementById('applicant_state').value = 'Kano';
                document.getElementById('party_state').value = 'Kano';
                const correspondenceState = document.getElementById('correspondence_state');
                if (correspondenceState) correspondenceState.value = 'Kano';

                // Set default application date
                const today = new Date().toISOString().split('T')[0];
                if (letterDateInput) letterDateInput.value = today;
                if (applicationDateInput) applicationDateInput.value = today;
                setRightOfOccupancyNumber('');
                if (rightOfOccupancyLandUseSelect) rightOfOccupancyLandUseSelect.value = '';
                if (rightOfOccupancyPurposeSelect) rightOfOccupancyPurposeSelect.value = '';
                if (dateOfGrantInput) dateOfGrantInput.value = '';
                filterRightOfOccupancyPurposeOptions();

                updateBuiltAddress('correspondence');
                updateBuiltAddress('party');
                syncApplicantWithCorrespondence();
            } else if (mode === 'view') {
                // View mode - disable all inputs
                modalTitle.textContent = 'View Application';
                setInteractiveFormState(false);
            } else {
                if (consentVariant === 'application') {
                    modalTitle.textContent = 'Edit Application';
                    submitBtn.querySelector('span').textContent = 'Update Application';
                } else {
                    modalTitle.textContent = 'Edit Letter';
                    submitBtn.querySelector('span').textContent = 'Update Letter';
                }
            }

            // Re-apply variant settings to show/hide appropriate sections
            applyVariantSettings();

            if (window.lucide) window.lucide.createIcons();
        } else {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            form.reset();
            applicationIdInput.value = '';
            currentEditingAppType = null; // Reset edit mode tracking

            // form.reset() restores values but not data attributes. Left set,
            // the autofill flag would let the next open overwrite a saved
            // consideration with the valuation amount.
            const considerationEl = document.querySelector('input[name="consideration"]');
            if (considerationEl) delete considerationEl.dataset.autofilled;
            lastBilledFileNumber = null;

            // Clear address components manually
            document.querySelectorAll('.address-component-applicant').forEach(el => el.value = '');
            document.querySelectorAll('.address-component-party').forEach(el => el.value = '');
            document.querySelectorAll('.address-component-property').forEach(el => el.value = '');
            document.querySelectorAll('.address-component-correspondence').forEach(el => el.value = '');

            const applicantPreview = document.getElementById('applicant_address_preview');
            const partyPreview = document.getElementById('party_address_preview');
            const propertyPreview = document.getElementById('property_address_preview');
            const correspondencePreview = document.getElementById('correspondence_address_preview');
            if (applicantPreview) applicantPreview.textContent = 'No address built yet...';
            if (partyPreview) partyPreview.textContent = 'No address built yet...';
            if (propertyPreview) propertyPreview.textContent = 'No address built yet...';
            if (correspondencePreview) correspondencePreview.textContent = 'No address built yet...';

            document.querySelectorAll('.manual-street-input').forEach(input => {
                input.classList.add('hidden');
                input.required = false;
                input.value = '';
            });
            document.querySelectorAll('.street-dropdown').forEach(select => {
                select.value = '';
                handleManualStreetToggle(select);
            });

            if (selectionIndicator) selectionIndicator.classList.add('hidden');
            clearAdditionalParties();
            if (typeof clearAdditionalApplicants === 'function') clearAdditionalApplicants();
            clearAdditionalFileNumbers();
            resetWizard();
        }
    }

    if (openBtn) openBtn.onclick = () => toggleModal(true, 'create');
    closeBtns.forEach(btn => btn.onclick = () => toggleModal(false));
    if (overlay) overlay.onclick = null;

    if (wizardPrevBtn) {
        wizardPrevBtn.addEventListener('click', function () {
            if (currentWizardStep > 1) {
                currentWizardStep -= 1;
                updateWizardControls();
            }
        });
    }

    if (wizardNextBtn) {
        wizardNextBtn.addEventListener('click', function () {
            const totalSteps = wizardSteps.length || 1;
            if (!validateWizardStep(currentWizardStep)) {
                return;
            }
            if (currentWizardStep < totalSteps) {
                currentWizardStep += 1;
                updateWizardControls();
            }
        });
    }

    wizardIndicators.forEach((indicator) => {
        indicator.classList.add('cursor-pointer');
        indicator.addEventListener('click', function () {
            const targetStep = Number(indicator.dataset.stepIndicator || 0);
            if (!targetStep || targetStep === currentWizardStep) {
                return;
            }

            if (targetStep < currentWizardStep) {
                currentWizardStep = targetStep;
                updateWizardControls();
                return;
            }

            for (let step = currentWizardStep; step < targetStep; step += 1) {
                if (!validateWizardStep(step)) {
                    return;
                }
            }

            currentWizardStep = targetStep;
            updateWizardControls();
        });
    });

    applyVariantSettings();
    resetWizard();

    // Edit Button Click Handling
    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-consent-btn');
        const viewBtn = e.target.closest('.view-consent-btn');

        if (editBtn || viewBtn) {
            const btn = editBtn || viewBtn;
            const appId = btn.getAttribute('data-id');
            const appData = JSON.parse(btn.getAttribute('data-app'));
            const mode = viewBtn ? 'view' : 'edit';

            // Preserve original application_type before modal opens
            currentEditingAppType = appData.application_type || null;

            toggleModal(true, mode);

            // Populate basic fields
            applicationIdInput.value = appId;
            setTrackingNo(appData.application_tracking_no);
            filenoInput.value = appData.file_number;
            setRightOfOccupancyNumber(appData.right_of_occupancy_number || appData.file_number || '');
            loadConsentBill(appData.file_number);
            document.querySelector('input[name="applicant_name"]').value = appData.applicant_name;
            document.querySelector('input[name="party_name"]').value = appData.party_name;
            updateApplicantNamePreview();
            const letterDate = appData.application_date ? appData.application_date.split(' ')[0] : new Date().toISOString().split('T')[0];
            if (letterDateInput) letterDateInput.value = letterDate;
            const applicationDate = appData.application_submitted_date ? appData.application_submitted_date.split(' ')[0] : new Date().toISOString().split('T')[0];
            if (applicationDateInput) applicationDateInput.value = applicationDate;

            const setInputValue = (name, value) => {
                const input = document.querySelector(`[name="${name}"]`);
                if (input) input.value = value || '';
            };

            setInputValue('right_of_occupancy_landuse', appData.right_of_occupancy_landuse);
            filterRightOfOccupancyPurposeOptions();
            setInputValue('purpose_of_right_of_occupancy', appData.purpose_of_right_of_occupancy);
            setInputValue('original_holder_name', appData.original_holder_name);
            setInputValue('correspondence_address', appData.correspondence_address);
            setInputValue('postal_address_gsm', appData.postal_address_gsm);
            setInputValue('nationality_state_of_origin', appData.nationality_state_of_origin);
            if (appData.nationality || appData.state_of_origin) {
                setInputValue('nationality', appData.nationality);
                setInputValue('state_of_origin', appData.state_of_origin);
            } else if (appData.nationality_state_of_origin) {
                const parsed = splitNationalityState(appData.nationality_state_of_origin);
                setInputValue('nationality', parsed.nationality);
                setInputValue('state_of_origin', parsed.stateOfOrigin);
            }
            setInputValue('stage_of_development', appData.stage_of_development);
            setDistrictValue(
                document.getElementById('location_rofo_district'),
                document.getElementById('location_rofo_district_other'),
                appData.location_of_right_of_occupancy
            );
            if (dateOfGrantInput) {
                dateOfGrantInput.value = appData.date_of_grant ? String(appData.date_of_grant).split(' ')[0] : '';
            }
            setInputValue('special_mortgage_terms', appData.special_mortgage_terms);

            // Handle addresses (they are stored as full addresses, but we have components)
            // For now let's just populate the hidden address input if components are empty
            // or we could try to split if they were saved in a specific format.
            // But usually we just populate the components if they exist.
            // Since we save the components in individual fields in modern logic, 
            // but the migration only had applicant_address text field.
            // Wait, let's check the form fields in modal.blade.php again.

            // Parse and populate address components
            parseAndPopulateAddress(appData.applicant_address, 'applicant');
            parseAndPopulateAddress(appData.party_address, 'party');
            parseAndPopulateAddress(appData.property_description, 'property');
            parseAndPopulateAddress(appData.correspondence_address || appData.applicant_address, 'correspondence');
            syncApplicantWithCorrespondence();

            document.getElementById('applicant_address_hidden').value = appData.applicant_address || '';
            document.getElementById('party_address_hidden').value = appData.party_address || '';
            document.getElementById('property_address_hidden').value = appData.property_description || '';
            const correspondenceHidden = document.getElementById('correspondence_address_hidden');
            if (correspondenceHidden) correspondenceHidden.value = appData.correspondence_address || appData.applicant_address || '';

            // Populate previews
            const applicantPreview = document.getElementById('applicant_address_preview');
            const partyPreview = document.getElementById('party_address_preview');
            const propertyPreview = document.getElementById('property_address_preview');
            const correspondencePreview = document.getElementById('correspondence_address_preview');
            if (applicantPreview) applicantPreview.textContent = appData.applicant_address || 'No address built yet...';
            if (partyPreview) partyPreview.textContent = appData.party_address || 'No address built yet...';
            if (propertyPreview) propertyPreview.textContent = appData.property_description || 'No address built yet...';
            if (correspondencePreview) correspondencePreview.textContent = document.getElementById('correspondence_address_hidden')?.value || 'No address built yet...';

            // Populate financial fields
            financialInput.value = appData.consideration || '';
            financialWordsInput.value = appData.consideration_words || '';

            // Populate additional parties if any
            if (appData.additional_parties) {
                populateAdditionalParties(appData.additional_parties);
            }

            // Populate additional applicants if any
            if (appData.additional_applicants) {
                if (typeof populateAdditionalApplicants === 'function') {
                    populateAdditionalApplicants(appData.additional_applicants);
                }
            }

            // Populate additional file numbers and their property descriptions
            populateAdditionalProperties(appData.additional_properties);

            // Set select value
            if (consentTypeSelect) {
                consentTypeSelect.value = appData.consent_type;
                consentTypeSelect.dispatchEvent(new Event('change'));
            }

            // Explicitly set application_type from record (preserve original)
            if (applicationTypeInput && appData.application_type) {
                applicationTypeInput.value = appData.application_type;
            }

            if (selectionIndicator) selectionIndicator.classList.remove('hidden');
        }
    });

    // Global File Number Selector Integration
    if (selectFilenoBtn) {
        selectFilenoBtn.addEventListener('click', function () {
            if (typeof GlobalFileNoModal !== 'undefined') {
                GlobalFileNoModal.open({
                    callback: async function (result) {
                        console.log('File selected:', result);
                        if (result && result.fileNumber) {
                            filenoInput.value = result.fileNumber;
                            setRightOfOccupancyNumber(result.fileNumber);
                            loadConsentBill(result.fileNumber);
                            if (selectionIndicator) selectionIndicator.classList.remove('hidden');
                            // The primary file counts toward the property total.
                            updateMultiPropertyNotice();

                            // No longer populating C of O separately as per request

                            // Auto-populate applicant name if available in record
                            if (result.record) {
                                const record = result.record;

                                // Name
                                const applicantInput = document.querySelector('input[name="applicant_name"]');
                                if (applicantInput && !applicantInput.value) {
                                    applicantInput.value = record.applicant_name || record.owner_name || record.file_name || '';
                                }
                                updateApplicantNamePreview();

                                // Address Components
                                if (document.getElementById('property_house_no')) {
                                    document.getElementById('property_house_no').value = record.plot_no || record.house_no || '';
                                }
                                const propertyStreetSelect = document.getElementById('property_street');
                                if (propertyStreetSelect) {
                                    // Only use an actual street field, NOT record.location
                                    // because location is a composite string (e.g. "SHARADA JAEN YAMMA, GWALE")
                                    // that already contains district/LGA info set separately below.
                                    setStreetSelectValue(propertyStreetSelect, record.street || record.street_name || '');
                                }
                                if (document.getElementById('property_district')) {
                                    setDistrictValue(document.getElementById('property_district'), document.getElementById('property_district_other'), record.district || '');
                                }
                                if (document.getElementById('property_lga')) {
                                    const lgaSelect = document.getElementById('property_lga');
                                    const lgaValue = record.lga || (record.lga_id ? record.lga_name : ''); // Assuming lga_name might be returned
                                    if (lgaValue) {
                                        if (![...lgaSelect.options].some(o => o.value === lgaValue)) {
                                            const opt = document.createElement('option');
                                            opt.value = lgaValue;
                                            opt.textContent = lgaValue;
                                            lgaSelect.appendChild(opt);
                                        }
                                        lgaSelect.value = lgaValue;
                                    }
                                }
                                if (document.getElementById('property_state')) {
                                    document.getElementById('property_state').value = record.state || 'Kano';
                                }

                                // Trigger address builder
                                updateBuiltAddress('correspondence');
                                syncApplicantWithCorrespondence();
                                updateBuiltAddress('property');
                            }

                            try {
                                const existingApplication = await fetchApplicationByFileNumber(result.fileNumber);
                                if (existingApplication) {
                                    populateFromApplicationRecord(existingApplication);
                                } else {
                                    applicationIdInput.value = '';
                                }
                            } catch (error) {
                                console.error('Lookup error:', error);
                            }
                        }
                    }
                });
            } else {
                console.error('GlobalFileNoModal is not defined.');
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'File selector component missing.', 'error');
                }
            }
        });
    }

    // Additional File Numbers Management
    //
    // Each additional file number owns a Property Description block: the two
    // containers are added to and removed from together and paired by position.
    const addFilenoBtn = document.getElementById('add-fileno-btn');
    const additionalFilenosContainer = document.getElementById('additional-file-numbers-container');
    const filenoTemplate = document.getElementById('additional-fileno-template');
    const additionalPropertiesContainer = document.getElementById('additional-properties-container');
    const additionalPropertyTemplate = document.getElementById('additional-property-template');
    const additionalRofoContainer = document.getElementById('additional-rofo-container');
    const additionalRofoTemplate = document.getElementById('additional-rofo-template');

    function filenoRows() {
        return additionalFilenosContainer
            ? [...additionalFilenosContainer.querySelectorAll('.additional-fileno-block')]
            : [];
    }

    function propertyBlocks() {
        return additionalPropertiesContainer
            ? [...additionalPropertiesContainer.querySelectorAll('.additional-property-block')]
            : [];
    }

    function rofoRows() {
        return additionalRofoContainer
            ? [...additionalRofoContainer.querySelectorAll('.additional-rofo-block')]
            : [];
    }

    // Mirror a file number and its applicant into the paired Step 2 Right of Occupancy row.
    function setRofoRowValues(row, fileNumber, applicantName) {
        if (!row) return;

        const display = row.querySelector('.additional-rofo-display');
        if (display) display.value = fileNumber || '';

        const hidden = row.querySelector('.additional-rofo-applicant');
        if (hidden && applicantName !== undefined) hidden.value = applicantName || '';

        const name = hidden ? hidden.value : '';
        const nameLabel = row.querySelector('.additional-rofo-applicant-name');
        if (nameLabel) nameLabel.textContent = name;

        // Mirrors the primary badge: only shown once both file and applicant are known.
        const wrapper = row.querySelector('.additional-rofo-applicant-wrapper');
        if (wrapper) {
            const show = !!(fileNumber && name);
            wrapper.classList.toggle('hidden', !show);
            wrapper.classList.toggle('flex', show);
        }
    }

    function fieldValue(block, name) {
        const el = block.querySelector(`[name="${name}"]`);
        return el ? el.value.trim() : '';
    }

    function updateAdditionalPropertyAddress(block) {
        const districtSelect = block.querySelector('select[name="additional_property_district[]"]');
        const district = districtSelect && districtSelect.value === 'Other'
            ? fieldValue(block, 'additional_property_district_other[]')
            : fieldValue(block, 'additional_property_district[]');

        const fullAddress = composeAddress({
            houseNo: fieldValue(block, 'additional_property_house_no[]'),
            street: getSelectOrManualValue(block.querySelector('select[name="additional_property_street[]"]')),
            district: district,
            lga: fieldValue(block, 'additional_property_lga[]'),
            state: fieldValue(block, 'additional_property_state[]')
        });

        const hidden = block.querySelector('.additional-property-hidden');
        if (hidden) hidden.value = fullAddress;

        const preview = block.querySelector('.additional-property-preview');
        if (preview) {
            preview.textContent = fullAddress || 'No description built yet...';
            preview.classList.toggle('text-slate-400', !fullAddress);
            preview.classList.toggle('text-slate-700', !!fullAddress);
            preview.classList.toggle('not-italic', !!fullAddress);
        }
    }

    // Flag a consent that spans several plots, so it is not mistaken for a single-property one.
    function updateMultiPropertyNotice() {
        const propertyCount = getSelectedFileNumbers().length;
        const isMulti = propertyCount > 1;

        document.querySelectorAll('.multi-property-notice').forEach(notice => {
            notice.classList.toggle('hidden', !isMulti);
            notice.classList.toggle('flex', isMulti);
        });
        document.querySelectorAll('.multi-property-count').forEach(el => {
            el.textContent = propertyCount;
        });

        // Only number the main blocks once there is something to distinguish them from.
        const mainIndex = document.getElementById('main-property-index');
        if (mainIndex) mainIndex.textContent = isMulti ? ' 1' : '';
        const mainRofoIndex = document.getElementById('main-rofo-index');
        if (mainRofoIndex) mainRofoIndex.textContent = isMulti ? ' 1' : '';
    }

    // Keep each property block numbered and labelled with the file number it describes.
    // The main Property Description section is #1, so additional blocks start at #2.
    function syncPropertyBlockHeaders() {
        updateMultiPropertyNotice();
        const rows = filenoRows();

        rofoRows().forEach((row, index) => {
            const label = row.querySelector('.rofo-block-index');
            if (label) label.textContent = index + 2;

            const input = rows[index] ? rows[index].querySelector('.additional-fileno-input') : null;
            setRofoRowValues(row, input ? input.value.trim() : '');
        });

        propertyBlocks().forEach((block, index) => {
            const header = block.querySelector('.property-block-header');
            if (header) header.textContent = `Property Description ${index + 2}`;

            const filenoLabel = block.querySelector('.property-block-fileno');
            if (filenoLabel) {
                const input = rows[index] ? rows[index].querySelector('.additional-fileno-input') : null;
                const fileNumber = input ? input.value.trim() : '';
                filenoLabel.textContent = fileNumber || 'no file selected';
                filenoLabel.classList.toggle('text-indigo-600', !!fileNumber);
                filenoLabel.classList.toggle('text-slate-400', !fileNumber);
            }
        });
    }

    function warn(title, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire(title, message, 'warning');
        } else {
            alert(message);
        }
    }

    // All file numbers currently on the form (main + additional), upper-cased for comparison.
    function getSelectedFileNumbers() {
        const values = [];
        if (filenoInput && filenoInput.value.trim()) {
            values.push(filenoInput.value.trim().toUpperCase());
        }
        if (additionalFilenosContainer) {
            additionalFilenosContainer.querySelectorAll('.additional-fileno-input').forEach(input => {
                if (input.value.trim()) values.push(input.value.trim().toUpperCase());
            });
        }
        return values;
    }

    function clearAdditionalFileNumbers() {
        if (additionalFilenosContainer) additionalFilenosContainer.innerHTML = '';
        if (additionalPropertiesContainer) additionalPropertiesContainer.innerHTML = '';
        if (additionalRofoContainer) additionalRofoContainer.innerHTML = '';
        updateMultiPropertyNotice();
    }

    function addFileNumberRow(value) {
        if (!additionalFilenosContainer || !filenoTemplate) return null;

        additionalFilenosContainer.appendChild(filenoTemplate.content.cloneNode(true));
        const block = additionalFilenosContainer.lastElementChild;

        if (value) {
            block.querySelector('.additional-fileno-input').value = value;
            block.querySelector('.additional-fileno-indicator').classList.remove('hidden');
        }

        // New rows inherit the form's current interactive state (view mode stays read-only).
        if (!formIsInteractive) {
            block.querySelectorAll('button').forEach(btn => {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
        }

        addPropertyBlock();
        if (additionalRofoContainer && additionalRofoTemplate) {
            additionalRofoContainer.appendChild(additionalRofoTemplate.content.cloneNode(true));
        }
        syncPropertyBlockHeaders();

        if (window.lucide) window.lucide.createIcons();
        return block;
    }

    function addPropertyBlock(property) {
        if (!additionalPropertiesContainer || !additionalPropertyTemplate) return null;

        additionalPropertiesContainer.appendChild(additionalPropertyTemplate.content.cloneNode(true));
        const block = additionalPropertiesContainer.lastElementChild;

        if (property) {
            const setValue = (name, value) => {
                const el = block.querySelector(`[name="${name}"]`);
                if (el) el.value = value || '';
            };
            setValue('additional_property_house_no[]', property.house_no);
            setStreetSelectValue(block.querySelector('select[name="additional_property_street[]"]'), property.street || '');
            setDistrictValue(
                block.querySelector('select[name="additional_property_district[]"]'),
                block.querySelector('input[name="additional_property_district_other[]"]'),
                property.district || ''
            );
            setSelectValueWithFallback(block.querySelector('select[name="additional_property_lga[]"]'), property.lga);
            setSelectValueWithFallback(block.querySelector('select[name="additional_property_state[]"]'), property.state);
        }

        updateAdditionalPropertyAddress(block);
        return block;
    }

    function populateAdditionalProperties(properties) {
        clearAdditionalFileNumbers();
        if (!Array.isArray(properties)) return;

        properties.forEach(property => {
            if (!property) return;

            // addFileNumberRow appends an empty property block; replace it with the saved one.
            addFileNumberRow(property.file_number || '');
            const block = additionalPropertiesContainer && additionalPropertiesContainer.lastElementChild;
            if (block) block.remove();
            addPropertyBlock(property);

            const rows = rofoRows();
            setRofoRowValues(rows[rows.length - 1], property.file_number || '', property.applicant_name || '');
        });

        syncPropertyBlockHeaders();
        if (window.lucide) window.lucide.createIcons();
    }

    if (addFilenoBtn) {
        addFilenoBtn.addEventListener('click', function () {
            if (!filenoInput.value.trim()) {
                warn('Required', 'Please select the primary File Number first.');
                return;
            }
            addFileNumberRow('');
        });
    }

    if (additionalFilenosContainer) {
        additionalFilenosContainer.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.remove-fileno-btn');
            if (removeBtn) {
                const row = removeBtn.closest('.additional-fileno-block');
                const removedIndex = filenoRows().indexOf(row);
                const pairedProperty = propertyBlocks()[removedIndex];
                if (pairedProperty) pairedProperty.remove();
                const pairedRofo = rofoRows()[removedIndex];
                if (pairedRofo) pairedRofo.remove();
                row.remove();
                syncPropertyBlockHeaders();
                return;
            }

            const searchBtn = e.target.closest('.additional-fileno-search-btn');
            if (!searchBtn) return;

            if (typeof GlobalFileNoModal === 'undefined') {
                console.error('GlobalFileNoModal is not defined.');
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'File selector component missing.', 'error');
                }
                return;
            }

            const block = searchBtn.closest('.additional-fileno-block');
            const input = block.querySelector('.additional-fileno-input');

            GlobalFileNoModal.open({
                // Without this the picker writes every selection straight into
                // [name="file_number"] — the primary input — clobbering it.
                autoPopulateGenericFields: false,
                callback: function (result) {
                    if (!result || !result.fileNumber) return;

                    const picked = result.fileNumber.trim();
                    const taken = getSelectedFileNumbers();

                    // Re-picking into a row that already holds a value must not clash with itself.
                    const own = input.value.trim().toUpperCase();
                    const ownIndex = own ? taken.indexOf(own) : -1;
                    if (ownIndex > -1) taken.splice(ownIndex, 1);

                    if (taken.includes(picked.toUpperCase())) {
                        warn('Duplicate', `File number ${picked} is already on this application.`);
                        return;
                    }

                    input.value = picked;
                    block.querySelector('.additional-fileno-indicator').classList.remove('hidden');

                    const pairedIndex = filenoRows().indexOf(block);

                    // Backfill the paired Step 2 Right of Occupancy row's applicant.
                    // Same precedence the primary file number uses.
                    const record = result.record || {};
                    setRofoRowValues(
                        rofoRows()[pairedIndex],
                        picked,
                        record.applicant_name || record.owner_name || record.file_name || result.file_name || ''
                    );

                    // Seed the paired Property Description from the selected file's record.
                    const pairedProperty = propertyBlocks()[pairedIndex];
                    if (pairedProperty && result.record) {
                        const houseNo = pairedProperty.querySelector('input[name="additional_property_house_no[]"]');
                        if (houseNo) houseNo.value = record.plot_no || record.house_no || '';
                        setStreetSelectValue(
                            pairedProperty.querySelector('select[name="additional_property_street[]"]'),
                            record.street || record.street_name || ''
                        );
                        setDistrictValue(
                            pairedProperty.querySelector('select[name="additional_property_district[]"]'),
                            pairedProperty.querySelector('input[name="additional_property_district_other[]"]'),
                            record.district || ''
                        );
                        setSelectValueWithFallback(
                            pairedProperty.querySelector('select[name="additional_property_lga[]"]'),
                            record.lga || record.lga_name || ''
                        );
                        setSelectValueWithFallback(
                            pairedProperty.querySelector('select[name="additional_property_state[]"]'),
                            record.state || 'Kano'
                        );
                        updateAdditionalPropertyAddress(pairedProperty);
                    }

                    syncPropertyBlockHeaders();
                }
            });
        });
    }

    if (additionalPropertiesContainer) {
        const rebuildPropertyAddress = e => {
            const block = e.target.closest('.additional-property-block');
            if (block) updateAdditionalPropertyAddress(block);
        };
        additionalPropertiesContainer.addEventListener('input', rebuildPropertyAddress);
        additionalPropertiesContainer.addEventListener('change', rebuildPropertyAddress);
    }

    // Dynamic Form Labels based on Consent Type
    if (consentTypeSelect) {
        consentTypeSelect.addEventListener('change', function () {
            const type = this.value;
            console.log('Consent Type changed:', type);

            const financialSection = document.getElementById('financial-section');

            if (type === 'Assignment') {
                if (financialSection) financialSection.classList.remove('hidden');
                financialInput.required = true;
                financialWordsInput.required = true;
                partyRoleLabel.textContent = 'Assignee Details';
                partyNameLabel.textContent = 'Assignee Full Name';
                if (applicantNameLabel) applicantNameLabel.innerHTML = APPLICANT_NAME_DEFAULT_HTML;
                document.querySelectorAll('.assignment-only-field').forEach(el => el.classList.remove('hidden'));
                financialTitle.textContent = 'Consideration';
                financialLabel.textContent = 'Amount in Words';
                financialWordsInput.placeholder = 'e.g. Five Million Naira Only';
                financialInput.placeholder = 'e.g. 5,000,000';

                // Show Add Party container
                const addPartyContainer = document.getElementById('add-party-action-container');
                if (addPartyContainer) addPartyContainer.classList.remove('hidden');
                const addPartyBtnSpan = document.querySelector('#add-party-btn span');
                if (addPartyBtnSpan) addPartyBtnSpan.textContent = 'Add Another Assignee';

            } else if (type === 'Gift') {
                if (financialSection) financialSection.classList.remove('hidden');
                financialInput.required = true;
                financialWordsInput.required = true;
                partyRoleLabel.textContent = 'Assignee Details';
                partyNameLabel.textContent = 'Assignee Full Name';
                if (applicantNameLabel) applicantNameLabel.innerHTML = APPLICANT_NAME_DEFAULT_HTML;
                document.querySelectorAll('.assignment-only-field').forEach(el => el.classList.remove('hidden'));
                financialTitle.textContent = 'Consideration';
                financialLabel.textContent = 'Amount in Words';
                financialWordsInput.placeholder = 'e.g. Five Million Naira Only';
                financialInput.placeholder = 'e.g. 5,000,000';

                // Show Add Party container
                const addPartyContainer = document.getElementById('add-party-action-container');
                if (addPartyContainer) addPartyContainer.classList.remove('hidden');
                const addPartyBtnSpan = document.querySelector('#add-party-btn span');
                if (addPartyBtnSpan) addPartyBtnSpan.textContent = 'Add Another Assignee';

            } else if (type === 'Mortgage' || type === 'Tripartite Mortgage') {
                if (financialSection) financialSection.classList.remove('hidden');
                financialInput.required = true;
                financialWordsInput.required = true;
                partyRoleLabel.textContent = 'Mortgagee Details';
                partyNameLabel.textContent = 'Assignee Full Name (Mortgage Institutions)';
                if (applicantNameLabel) applicantNameLabel.innerHTML = 'Applicant Full Name (Mortgagor) <span class="text-red-500">*</span>';
                document.querySelectorAll('.assignment-only-field').forEach(el => el.classList.add('hidden'));
                financialTitle.textContent = 'Consideration';
                financialLabel.textContent = 'Amount in Words';
                financialWordsInput.placeholder = 'e.g. Ten Million Naira Only';
                financialInput.placeholder = 'e.g. 10,000,000';

                // Show Add Party container
                const addPartyContainer = document.getElementById('add-party-action-container');
                if (addPartyContainer) addPartyContainer.classList.remove('hidden');
                const addPartyBtnSpan = document.querySelector('#add-party-btn span');
                if (addPartyBtnSpan) addPartyBtnSpan.textContent = 'Add Another Mortgagee';
            }

            const multiplePropertiesContainer = document.getElementById('multiple-properties-prompt-container');
            const hasMultipleCheckbox = document.getElementById('has_multiple_properties');
            if (type === 'Mortgage' || type === 'Tripartite Mortgage') {
                if (multiplePropertiesContainer) multiplePropertiesContainer.classList.remove('hidden');
            } else {
                if (multiplePropertiesContainer) multiplePropertiesContainer.classList.add('hidden');
                if (hasMultipleCheckbox && hasMultipleCheckbox.checked) {
                    hasMultipleCheckbox.checked = false;
                    hasMultipleCheckbox.dispatchEvent(new Event('change'));
                }
            }

            toggleMortgageSpecificFields(type);

            if (typeof updateApplicantNamePreview === 'function') updateApplicantNamePreview();

            if (window.lucide) window.lucide.createIcons();
        });
        consentTypeSelect.dispatchEvent(new Event('change'));
    }

    const hasMultipleCheckbox = document.getElementById('has_multiple_properties');
    if (hasMultipleCheckbox) {
        hasMultipleCheckbox.addEventListener('change', function() {
            const addFilenoBtn = document.getElementById('add-fileno-btn');
            if (addFilenoBtn) {
                if (this.checked) {
                    addFilenoBtn.classList.remove('hidden');
                    addFilenoBtn.classList.add('flex');
                } else {
                    addFilenoBtn.classList.add('hidden');
                    addFilenoBtn.classList.remove('flex');
                    document.querySelectorAll('.remove-fileno-btn').forEach(btn => btn.click());
                }
            }
        });
    }

    // Keep applicant name badge in sync whenever the applicant name changes
    const applicantNameInputLive = document.querySelector('input[name="applicant_name"]');
    if (applicantNameInputLive) {
        applicantNameInputLive.addEventListener('input', updateApplicantNamePreview);
        applicantNameInputLive.addEventListener('change', updateApplicantNamePreview);
    }

    if (rightOfOccupancyLandUseSelect) {
        rightOfOccupancyLandUseSelect.addEventListener('change', function () {
            filterRightOfOccupancyPurposeOptions();
        });
        filterRightOfOccupancyPurposeOptions();
    }

    // Real-time Number to Words Conversion
    if (financialInput) {
        financialInput.addEventListener('input', function () {
            const val = this.value.replace(/,/g, '');
            if (val && !isNaN(val)) {
                financialWordsInput.value = convertNumberToWords(val);
            } else {
                financialWordsInput.value = '';
            }
        });
    }

    function convertNumberToWords(amount) {
        if (isNaN(amount) || amount === "" || amount === "0") return "";

        let num = parseInt(amount);
        if (isNaN(num)) return "";

        const words = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
        const tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
        const scales = ["", "Thousand", "Million", "Billion", "Trillion"];

        if (num === 0) return "Zero Naira Only";

        function convertChunk(n) {
            let str = "";
            if (n >= 100) {
                str += words[Math.floor(n / 100)] + " Hundred ";
                n %= 100;
            }
            if (n >= 20) {
                str += tens[Math.floor(n / 10)] + " ";
                n %= 10;
            }
            if (n > 0) {
                str += words[n] + " ";
            }
            return str.trim();
        }

        let result = "";
        let scaleIndex = 0;
        let tempNum = num;

        while (tempNum > 0) {
            let chunk = tempNum % 1000;
            if (chunk > 0) {
                let chunkStr = convertChunk(chunk);
                result = chunkStr + (scales[scaleIndex] ? " " + scales[scaleIndex] : "") + (result ? ", " + result : "");
            }
            tempNum = Math.floor(tempNum / 1000);
            scaleIndex++;
        }

        // Clean up formatting
        result = result.replace(/,$/, "").trim();
        if (result.startsWith(", ")) result = result.substring(2);

        return result + " Naira Only";
    }

    // Address Builder Logic
    const applicantComponents = document.querySelectorAll('.address-component-applicant');
    const partyComponents = document.querySelectorAll('.address-component-party');
    const propertyComponents = document.querySelectorAll('.address-component-property');
    const correspondenceComponents = document.querySelectorAll('.address-component-correspondence');
    const applicantAddressHidden = document.getElementById('applicant_address_hidden');
    const partyAddressHidden = document.getElementById('party_address_hidden');
    const propertyAddressHidden = document.getElementById('property_address_hidden');
    const correspondenceAddressHidden = document.getElementById('correspondence_address_hidden');

    function getStreetValueById(id) {
        const el = document.getElementById(id);
        return getSelectOrManualValue(el);
    }

    function setSelectValueWithFallback(selectEl, value) {
        if (!selectEl) return;
        const trimmed = (value || '').trim();
        if (!trimmed) {
            selectEl.value = '';
            return;
        }
        if (![...selectEl.options].some(option => option.value === trimmed)) {
            const option = document.createElement('option');
            option.value = trimmed;
            option.textContent = trimmed;
            selectEl.appendChild(option);
        }
        selectEl.value = trimmed;
    }

    function syncApplicantWithCorrespondence() {
        const correspondenceHouseNo = document.getElementById('correspondence_house_no');
        const correspondenceStreet = document.getElementById('correspondence_street');
        const correspondenceDistrict = document.getElementById('correspondence_district');
        const correspondenceDistrictOther = document.getElementById('correspondence_district_other');
        const correspondenceLga = document.getElementById('correspondence_lga');
        const correspondenceState = document.getElementById('correspondence_state');

        const applicantHouseNo = document.getElementById('applicant_house_no');
        const applicantStreet = document.getElementById('applicant_street');
        const applicantDistrict = document.getElementById('applicant_district');
        const applicantDistrictOther = document.getElementById('applicant_district_other');
        const applicantLga = document.getElementById('applicant_lga');
        const applicantState = document.getElementById('applicant_state');

        if (!correspondenceHouseNo || !applicantHouseNo) {
            return;
        }

        applicantHouseNo.value = correspondenceHouseNo.value || '';
        setStreetSelectValue(applicantStreet, getSelectOrManualValue(correspondenceStreet));

        const correspondenceDistrictValue = correspondenceDistrict && correspondenceDistrict.value === 'Other'
            ? (correspondenceDistrictOther ? correspondenceDistrictOther.value : '')
            : (correspondenceDistrict ? correspondenceDistrict.value : '');
        setDistrictValue(applicantDistrict, applicantDistrictOther, correspondenceDistrictValue);

        setSelectValueWithFallback(applicantState, correspondenceState ? correspondenceState.value : '');
        setSelectValueWithFallback(applicantLga, correspondenceLga ? correspondenceLga.value : '');

        updateBuiltAddress('applicant');
    }

    // Title-case helper: "12TH AV" → "12th Av", "BICHI" → "Bichi"
    function addrTitleCase(str) {
        if (!str) return str;
        return str.replace(/\S+/g, function (word) {
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        });
    }

    function composeAddress({ houseNo, street, district, lga, state }) {
        const parts = [];
        // Only one of house/plot no OR street joins the address:
        // if house/plot no has a value, use it; otherwise fall back to street.
        if (houseNo) {
            parts.push(addrTitleCase(houseNo));
        } else if (street) {
            parts.push(addrTitleCase(street));
        }
        if (district) parts.push(addrTitleCase(district));
        if (lga) parts.push(addrTitleCase(lga));
        if (state) parts.push(addrTitleCase(state) + ' State');

        return parts.join(', ') + (parts.length > 0 ? '.' : '');
    }

    function updateBuiltAddress(type) {
        let houseNo, street, district, lga, state, hiddenInput, previewSpan;

        const getVal = id => document.getElementById(id) ? document.getElementById(id).value.trim() : '';

        if (type === 'applicant') {
            houseNo = getVal('applicant_house_no');
            street = getStreetValueById('applicant_street');
            district = getVal('applicant_district') === 'Other' ? getVal('applicant_district_other') : getVal('applicant_district');
            lga = getVal('applicant_lga');
            state = getVal('applicant_state');
            hiddenInput = applicantAddressHidden;
            previewSpan = document.getElementById('applicant_address_preview');
        } else if (type === 'party') {
            houseNo = getVal('party_house_no');
            street = getStreetValueById('party_street');
            district = getVal('party_district') === 'Other' ? getVal('party_district_other') : getVal('party_district');
            lga = getVal('party_lga');
            state = getVal('party_state');
            hiddenInput = partyAddressHidden;
            previewSpan = document.getElementById('party_address_preview');
        } else if (type === 'property') {
            houseNo = getVal('property_house_no');
            street = getStreetValueById('property_street');
            district = getVal('property_district') === 'Other' ? getVal('property_district_other') : getVal('property_district');
            lga = getVal('property_lga');
            state = getVal('property_state');
            hiddenInput = propertyAddressHidden;
            previewSpan = document.getElementById('property_address_preview');
        } else if (type === 'correspondence') {
            houseNo = getVal('correspondence_house_no');
            street = getStreetValueById('correspondence_street');
            district = getVal('correspondence_district') === 'Other' ? getVal('correspondence_district_other') : getVal('correspondence_district');
            lga = getVal('correspondence_lga');
            state = getVal('correspondence_state');
            hiddenInput = correspondenceAddressHidden;
            previewSpan = document.getElementById('correspondence_address_preview');
        }

        const fullAddress = composeAddress({ houseNo, street, district, lga, state });
        if (hiddenInput) hiddenInput.value = fullAddress;
        if (previewSpan) {
            previewSpan.textContent = fullAddress || 'No address built yet...';
            previewSpan.classList.toggle('text-slate-400', !fullAddress);
            previewSpan.classList.toggle('text-slate-700', !!fullAddress);
            previewSpan.classList.toggle('not-italic', !!fullAddress);
        }
    }

    [...applicantComponents].forEach(c => {
        c.addEventListener('change', () => updateBuiltAddress('applicant'));
        c.addEventListener('input', () => updateBuiltAddress('applicant'));
    });

    [...partyComponents].forEach(c => {
        c.addEventListener('change', () => updateBuiltAddress('party'));
        c.addEventListener('input', () => updateBuiltAddress('party'));
    });

    [...propertyComponents].forEach(c => {
        c.addEventListener('change', () => updateBuiltAddress('property'));
        c.addEventListener('input', () => updateBuiltAddress('property'));
    });

    [...correspondenceComponents].forEach(c => {
        c.addEventListener('change', () => {
            updateBuiltAddress('correspondence');
            syncApplicantWithCorrespondence();
        });
        c.addEventListener('input', () => {
            updateBuiltAddress('correspondence');
            syncApplicantWithCorrespondence();
        });
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('street-dropdown')) {
            handleManualStreetToggle(e.target);
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('manual-street-input')) {
            const addressType = e.target.dataset.addressType;
            if (addressType) updateBuiltAddress(addressType);
        }
    });

    document.querySelectorAll('.street-dropdown').forEach(select => handleManualStreetToggle(select));

    // District Select Other Logic
    document.addEventListener('change', function (e) {
        if (e.target.matches('select[id$="_district"], select[name$="_district[]"], select.dist-select')) {
            const isOther = e.target.value === 'Other';
            let otherInput;
            if (e.target.id) {
                otherInput = document.getElementById(e.target.id + '_other');
            } else if (e.target.nextElementSibling && e.target.nextElementSibling.matches('input')) {
                otherInput = e.target.nextElementSibling;
            }

            if (otherInput) {
                if (isOther) {
                    otherInput.classList.remove('hidden');
                    otherInput.required = true;
                } else {
                    otherInput.classList.add('hidden');
                    otherInput.required = false;
                    otherInput.value = '';
                }
                otherInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });

    // Handle State change to fetch LGAs
    const applicantState = document.getElementById('applicant_state');
    const partyState = document.getElementById('party_state');
    const correspondenceState = document.getElementById('correspondence_state');

    /**
     * Make sure a prefix's LGA dropdown holds its state's LGAs, then select one.
     *
     * fetchLgas() only ever ran from a user's change event on the state select.
     * Populating the form from a saved record sets that select in code, which
     * fires nothing, so the LGA list stayed at "Select LGA" and the saved LGA
     * was silently dropped on the next save.
     */
    async function ensureLgaOptions(prefix, state, desiredLga) {
        const select = document.getElementById(`${prefix}_lga`);
        if (!select || !state) {
            return;
        }

        // Only the placeholder present means the list was never loaded.
        if (select.options.length <= 1) {
            await fetchLgas(state, `${prefix}_lga`, prefix);
        }

        if (desiredLga) {
            if (![...select.options].some(o => o.value === desiredLga)) {
                const opt = document.createElement('option');
                opt.value = desiredLga;
                opt.textContent = desiredLga;
                select.appendChild(opt);
            }
            select.value = desiredLga;
        }

        // fetchLgas() rebuilt the list and recomposed the address without the
        // LGA, so recompose once more now that it is selected.
        updateBuiltAddress(prefix);
    }

    async function fetchLgas(stateName, lgaSelectId, updateType) {
        try {
            const response = await fetch(`/instruments/get-lgas/${encodeURIComponent(stateName)}`);
            const lgas = await response.json();
            const select = document.getElementById(lgaSelectId);

            select.innerHTML = '<option value="">Select LGA</option>';
            lgas.forEach(lga => {
                const opt = document.createElement('option');
                opt.value = lga.LGAName;
                opt.textContent = lga.LGAName;
                select.appendChild(opt);
            });

            // Re-trigger address update after LGA list changes
            updateBuiltAddress(updateType);
        } catch (error) {
            console.error('Error fetching LGAs:', error);
        }
    }

    if (applicantState) {
        applicantState.addEventListener('change', function () {
            if (this.value) {
                fetchLgas(this.value, 'applicant_lga', 'applicant');
            }
        });
    }

    if (partyState) {
        partyState.addEventListener('change', function () {
            if (this.value) fetchLgas(this.value, 'party_lga', 'party');
        });
    }

    if (correspondenceState) {
        correspondenceState.addEventListener('change', function () {
            if (this.value) fetchLgas(this.value, 'correspondence_lga', 'correspondence');
            syncApplicantWithCorrespondence();
        });
    }

    // Initialize built addresses on load (Kano is default)
    updateBuiltAddress('correspondence');
    updateBuiltAddress('party');
    syncApplicantWithCorrespondence();
    updateBuiltAddress('applicant');

    // Dynamic Party Management
    const addPartyBtn = document.getElementById('add-party-btn');
    const additionalPartiesContainer = document.getElementById('additional-parties-container');
    const partyTemplate = document.getElementById('additional-party-template');

    function clearAdditionalParties() {
        if (additionalPartiesContainer) {
            additionalPartiesContainer.innerHTML = '';
        }
    }

    function updatePartyHeaders() {
        const blocks = additionalPartiesContainer.querySelectorAll('.additional-party-block');
        const typeSelect = document.getElementById('consent_type');
        let headerPrefix = 'Third';
        if (typeSelect) {
            if (typeSelect.value === 'Assignment') headerPrefix = 'Additional Assignee';
            else if (typeSelect.value === 'Gift') headerPrefix = 'Additional Assignee';
            else if (typeSelect.value === 'Mortgage') headerPrefix = 'Additional Mortgagee';
        }

        blocks.forEach((block, index) => {
            const header = block.querySelector('.party-header');
            if (header) {
                // If it's a fixed prefix (like 'Third Party'), we can change it to sequence or just keep the generic label.
                // The prompt says: "rename to 'Assignee / Donee / Mortgagee' based on type"
                // So let's just use the selected title and perhaps a number if desired.
                header.textContent = `${headerPrefix} ${index + 1}`;
            }
        });

        // Hide add button if we have 2 blocks (only if there was an arbitrary limit. 
        // User requested: "Allow adding multiple assignees without an arbitrary limit of 2, or increase if necessary"
        // Let's remove the limit of 2.)
        if (addPartyBtn && blocks.length >= 10) { // arbitrary higher limit mostly to prevent infinite DOM loops just in case
            addPartyBtn.parentElement.classList.add('hidden');
        } else if (addPartyBtn) {
            addPartyBtn.parentElement.classList.remove('hidden');
        }
    }

    if (addPartyBtn && additionalPartiesContainer && partyTemplate) {
        addPartyBtn.addEventListener('click', function () {
            const currentBlocks = additionalPartiesContainer.querySelectorAll('.additional-party-block');
            if (currentBlocks.length >= 2) return;

            const clone = partyTemplate.content.cloneNode(true);
            additionalPartiesContainer.appendChild(clone);

            // Re-index heads and check limit
            updatePartyHeaders();

            // Initialize Lucide icons for the new block
            if (window.lucide) window.lucide.createIcons();

            // Add remove event listener
            const lastBlock = additionalPartiesContainer.lastElementChild;
            const removeBtn = lastBlock.querySelector('.remove-party-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    lastBlock.remove();
                    updatePartyHeaders();
                });
            }
        });
    }

    // Function to populate additional parties (used during Edit)
    function populateAdditionalParties(parties) {
        clearAdditionalParties();
        if (!parties || !Array.isArray(parties)) return;

        parties.forEach(party => {
            const clone = partyTemplate.content.cloneNode(true);
            const block = document.createElement('div');
            block.appendChild(clone);
            const actualBlock = block.firstElementChild;

            actualBlock.querySelector('input[name="additional_party_name[]"]').value = party.name || '';
            actualBlock.querySelector('input[name="additional_party_house_no[]"]').value = party.house_no || '';
            const partyStreetSelect = actualBlock.querySelector('select[name="additional_party_street[]"]');
            setStreetSelectValue(partyStreetSelect, party.street || '');

            const distSelect = actualBlock.querySelector('select[name="additional_party_district[]"]');
            const distOther = actualBlock.querySelector('input[name="additional_party_district_other[]"]');
            setDistrictValue(distSelect, distOther, party.district || '');

            actualBlock.querySelector('select[name="additional_party_lga[]"]').value = party.lga || '';
            actualBlock.querySelector('select[name="additional_party_state[]"]').value = party.state || '';

            additionalPartiesContainer.appendChild(actualBlock);

            // Add remove event listener
            const removeBtn = actualBlock.querySelector('.remove-party-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    actualBlock.remove();
                    updatePartyHeaders();
                });
            }
        });

        updatePartyHeaders();
        if (window.lucide) window.lucide.createIcons();
    }

    // Dynamic Applicant Management
    const addApplicantBtn = document.getElementById('add-applicant-btn');
    const additionalApplicantsContainer = document.getElementById('additional-applicants-container');
    const applicantTemplate = document.getElementById('additional-applicant-template');

    function clearAdditionalApplicants() {
        if (additionalApplicantsContainer) {
            additionalApplicantsContainer.innerHTML = '';
        }
    }

    if (addApplicantBtn && additionalApplicantsContainer && applicantTemplate) {
        addApplicantBtn.addEventListener('click', function () {
            const clone = applicantTemplate.content.cloneNode(true);
            additionalApplicantsContainer.appendChild(clone);

            if (window.lucide) window.lucide.createIcons();

            // Add remove event listener
            const lastBlock = additionalApplicantsContainer.lastElementChild;
            const removeBtn = lastBlock.querySelector('.remove-applicant-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    lastBlock.remove();
                });
            }

            // Hide the 'Add Applicant' button if limit reached (example limit of 5, or unlimited)
            // User requested "allow adding multiple applicants", no arbitrary limit stated, so we don't hide it
        });
    }

    window.clearAdditionalApplicants = clearAdditionalApplicants;
    window.populateAdditionalApplicants = function (applicants) {
        clearAdditionalApplicants();
        if (!applicants || !Array.isArray(applicants)) return;

        applicants.forEach(applicant => {
            const clone = applicantTemplate.content.cloneNode(true);
            const block = document.createElement('div');
            block.appendChild(clone);
            const actualBlock = block.firstElementChild;

            actualBlock.querySelector('input[name="additional_applicant_name[]"]').value = applicant.name || '';
            actualBlock.querySelector('input[name="additional_applicant_house_no[]"]').value = applicant.house_no || '';
            const applicantStreetSelect = actualBlock.querySelector('select[name="additional_applicant_street[]"]');
            setStreetSelectValue(applicantStreetSelect, applicant.street || '');

            const distSelect = actualBlock.querySelector('select[name="additional_applicant_district[]"]');
            const distOther = actualBlock.querySelector('input[name="additional_applicant_district_other[]"]');
            setDistrictValue(distSelect, distOther, applicant.district || '');

            actualBlock.querySelector('select[name="additional_applicant_lga[]"]').value = applicant.lga || '';
            actualBlock.querySelector('select[name="additional_applicant_state[]"]').value = applicant.state || '';

            additionalApplicantsContainer.appendChild(actualBlock);

            // Add remove event listener
            const removeBtn = actualBlock.querySelector('.remove-applicant-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    actualBlock.remove();
                });
            }
        });

        if (window.lucide) window.lucide.createIcons();
    };

    function normalizeStreetFieldValues(formEl, formData) {
        const mappings = [
            { selector: 'select[name="additional_party_street[]"]', name: 'additional_party_street[]' },
            { selector: 'select[name="additional_applicant_street[]"]', name: 'additional_applicant_street[]' },
            { selector: 'select[name="additional_property_street[]"]', name: 'additional_property_street[]' }
        ];

        mappings.forEach(({ selector, name }) => {
            const selects = formEl.querySelectorAll(selector);
            if (!selects.length) return;
            formData.delete(name);
            selects.forEach(select => {
                formData.append(name, getSelectOrManualValue(select));
            });
        });

        formData.delete('additional_party_street_manual[]');
        formData.delete('additional_applicant_street_manual[]');
        formData.delete('additional_property_street_manual[]');
    }

    // Handle Form Submission
    if (form) {
        form.onsubmit = async function (e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submit-btn');
            const originalBtnContent = submitBtn.innerHTML;

            // Basic validation
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (!filenoInput.value) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Required', 'Please select a File Number first.', 'warning');
                } else {
                    alert('Please select a File Number first.');
                }
                return;
            }

            // Loading state
            const isEdit = !!applicationIdInput.value;
            submitBtn.disabled = true;
            const loadingLabel = isEdit
                ? 'Updating...'
                : (consentVariant === 'application' ? 'Creating...' : 'Generating...');
            submitBtn.innerHTML = `<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> ${loadingLabel}`;

            if (window.lucide) window.lucide.createIcons();

            try {
                const formData = new FormData(form);
                normalizeStreetFieldValues(form, formData);

                // Resolve location_of_right_of_occupancy "Other" to typed value
                const rofoSelect = document.getElementById('location_rofo_district');
                const rofoOther = document.getElementById('location_rofo_district_other');
                if (rofoSelect && rofoSelect.value === 'Other' && rofoOther) {
                    formData.set('location_of_right_of_occupancy', rofoOther.value);
                }
                const appId = applicationIdInput.value;
                const isEdit = !!appId;

                let url = '/consent-applications';
                let method = 'POST';

                if (isEdit) {
                    url = `/consent-applications/${appId}`;
                    formData.append('_method', 'PUT');
                }

                // Process additional parties into a format the backend expects if necessary
                // Laravel handles additional_party_name[] and additional_party_address[] as arrays automatically.
                // We'll let the controller combine them.

                const response = await fetch(url, {
                    method: 'POST', // Still POST but with _method override for PUT
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                });

                const result = await response.json();

                if (result.success) {
                    if (typeof Swal !== 'undefined') {
                        const isApplication = consentVariant === 'application';
                        Swal.fire({
                            title: 'Success!',
                            text: result.message,
                            icon: 'success',
                            confirmButtonText: 'Close'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert(result.message);
                        window.location.reload();
                    }
                } else {
                    throw new Error(result.message || 'Validation failed');
                }
            } catch (error) {
                console.error('Submission error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', error.message || 'Something went wrong.', 'error');
                } else {
                    alert('Error: ' + error.message);
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
                if (window.lucide) window.lucide.createIcons();
            }
        };
    }
});

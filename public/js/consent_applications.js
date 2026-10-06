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

    let lastBilledFileNumber = null;

    /**
     * Transaction types — config/consent_bill.php, rendered onto the select.
     *
     * The type fixes the registration rate, the processing fee, the stamp duty
     * rate and who stamp duty is paid to (KIRS / FIRS). The server recomputes
     * from the same table on save; these are for display only.
     */
    const transactionTypeSelect = document.getElementById('transaction_type');
    const readJson = (el, attr) => {
        try { return JSON.parse((el && el.getAttribute(attr)) || '{}') || {}; } catch (e) { return {}; }
    };
    const TRANSACTION_TYPES = readJson(transactionTypeSelect, 'data-types');
    const STAMP_DUTY_PAYEES = readJson(transactionTypeSelect, 'data-payees');
    // Consent types that settle the transaction type by themselves:
    // { Gift: 'gift', Mortgage: 'mortgage', ... } — config consent_type_transactions.
    const IMPLIED_TYPES = readJson(transactionTypeSelect, 'data-implied');
    const impliedValues = Object.values(IMPLIED_TYPES);

    /**
     * Lock or free the Transaction Type according to the Consent Type.
     *
     * A Gift or a Mortgage needs no further choice, so the field is set to the
     * implied type and locked (not disabled: a disabled field would not post).
     * An Assignment frees it and offers only the three party options; a value
     * left over from a Gift/Mortgage is cleared so the officer must choose.
     */
    function syncTransactionTypeWithConsentType() {
        if (!transactionTypeSelect || !consentTypeSelect) return;
        const implied = IMPLIED_TYPES[consentTypeSelect.value] || '';

        if (implied) {
            transactionTypeSelect.value = implied;
        } else if (impliedValues.includes(transactionTypeSelect.value)) {
            transactionTypeSelect.value = '';
        }

        const locked = !!implied;
        transactionTypeSelect.dataset.locked = locked ? '1' : '';
        transactionTypeSelect.setAttribute('aria-readonly', locked ? 'true' : 'false');
        transactionTypeSelect.tabIndex = locked ? -1 : 0;
        transactionTypeSelect.classList.toggle('bg-slate-100', locked);
        transactionTypeSelect.classList.toggle('cursor-not-allowed', locked);
        transactionTypeSelect.classList.toggle('pointer-events-none', locked);
        transactionTypeSelect.classList.toggle('bg-slate-50', !locked);
        transactionTypeSelect.classList.toggle('cursor-pointer', !locked);
    }

    // A locked field must not be changed from the keyboard either.
    if (transactionTypeSelect) {
        transactionTypeSelect.addEventListener('keydown', function (e) {
            if (this.dataset.locked === '1' && e.key !== 'Tab') e.preventDefault();
        });
    }

    function currentTransactionType() {
        const key = transactionTypeSelect ? transactionTypeSelect.value : '';
        return key && TRANSACTION_TYPES[key] ? Object.assign({ key: key }, TRANSACTION_TYPES[key]) : null;
    }

    // "5%", "1.5%", "0.375%" — the same shape ConsentBillCalculator::formatRate() prints.
    const formatRate = (rate) => String(parseFloat(Number(rate || 0).toFixed(4))) + '%';

    const formatMoney = (v) => '₦' + Number(v || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });

    function setFieldError(id, message) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('hidden', !message);
    }

    /**
     * Apply the chosen transaction type to the form.
     *
     * The Consent Type comes first in the logic: a Gift or Mortgage settles
     * the transaction type (syncTransactionTypeWithConsentType()); for an
     * Assignment the officer picks the party option. When the choice is the
     * officer's (fromUser) the percentages reset to the type's rates; when a
     * saved record is loaded, its own rates are kept. Also rewrites the payee
     * note and rebills the selected file. Safe to call repeatedly.
     */
    function applyTransactionType(fromUser) {
        syncTransactionTypeWithConsentType();
        const profile = currentTransactionType();
        const summary = document.getElementById('transaction-type-summary');
        const section = document.getElementById('consent-bill-section');
        fromUser = fromUser === true || (fromUser && fromUser.type === 'change');

        if (profile) setFieldError('transaction_type_error', '');

        const registrationRate = profile ? profile.registration_rate : (section ? section.dataset.registrationRate : 0);
        const stampRate = profile ? profile.stamp_duty_rate : (section ? section.dataset.stampDutyRate : 0);
        const processing = profile ? profile.processing_fee : (section ? section.dataset.processingFee : 0);
        const payeeName = profile ? (STAMP_DUTY_PAYEES[profile.payee] || profile.payee) : '';

        // The type's rates become the percentage fields' defaults. A user
        // choice resets the fields to them; a loaded record keeps its own.
        setRateDefault('registration', registrationRate, fromUser);
        setRateDefault('stamp_duty', stampRate, fromUser);

        // The processing fee's fallback follows the type (₦12,000 / ₦20,000).
        const processingEl = document.getElementById('bill_processing_fee');
        const processingRow = processingEl ? processingEl.closest('.bill-row') : null;
        if (processingRow) processingRow.dataset.default = Number(processing || 0).toFixed(2);

        const payeeNote = document.getElementById('stamp-duty-payee-note');
        if (payeeNote) {
            payeeNote.textContent = profile
                ? 'Not included in the total. Payable to the ' + payeeName + '.'
                : 'Select a transfer type to see who stamp duty is paid to.';
        }

        if (summary) {
            summary.classList.toggle('hidden', !profile);
            if (profile) {
                const isFederal = profile.payee === 'FIRS';
                summary.className = 'mt-3 p-3 rounded-xl border text-xs leading-relaxed '
                    + (isFederal ? 'border-indigo-200 bg-indigo-50 text-indigo-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900');
                const setByConsent = transactionTypeSelect.dataset.locked === '1'
                    ? ' <span class="font-normal opacity-75">(set by Consent Type)</span>' : '';
                summary.innerHTML = '<div class="font-bold mb-0.5">' + profile.label + setByConsent + '</div>'
                    + 'Registration ' + formatRate(profile.registration_rate)
                    + ' + Processing ' + formatMoney(profile.processing_fee) + ' to the Ministry.<br>'
                    + 'Stamp duty <strong>' + formatRate(profile.stamp_duty_rate) + '</strong> payable to the <strong>'
                    + payeeName + '</strong>.';
            }
        }

        // Rebill under the new rates. loadConsentBill() keys its cache on the
        // file AND the type, so this refetches only when something changed.
        const fileNumber = filenoInput ? filenoInput.value.trim() : '';
        if (fileNumber) {
            loadConsentBill(fileNumber);
        } else {
            updateBillTotalPreview();
        }
    }

    if (transactionTypeSelect) {
        transactionTypeSelect.addEventListener('change', applyTransactionType);
    }
    // Choosing Gift / Mortgage locks the Transaction Type; Assignment frees it.
    if (consentTypeSelect) {
        consentTypeSelect.addEventListener('change', applyTransactionType);
    }

    /**
     * Editable percentages (registration, stamp duty).
     *
     * Each field remembers the type's rate as its default. It gains the name
     * bill_rate[key] only while its value differs from that default, so an
     * untouched rate posts nothing and the server keeps the configured one; an
     * edited rate is recomputed and audited server-side.
     */
    const rateInput = (key) => document.querySelector(`[data-rate-input="${key}"]`);

    function syncRateName(input) {
        const value = parseFloat(String(input.value).replace(/[^0-9.]/g, ''));
        const fallback = parseFloat(input.dataset.default || '0');
        const edited = !isNaN(value) && Math.abs(value - fallback) > 0.00005;
        if (edited) {
            input.name = `bill_rate[${input.dataset.rateInput}]`;
        } else {
            input.removeAttribute('name');
        }
        input.classList.toggle('border-amber-300', edited);
        input.classList.toggle('bg-amber-50', edited);
        input.classList.toggle('bg-white', !edited);
    }

    function setRateDefault(key, rate, resetValue) {
        const input = rateInput(key);
        if (!input) return;
        const shown = String(parseFloat(Number(rate || 0).toFixed(4)));
        input.dataset.default = shown;
        if (resetValue || input.value.trim() === '' || !input.name) {
            input.value = shown;
        }
        syncRateName(input);
    }

    // Load a saved record's own percentages into the fields.
    function setRateValues(registrationRate, stampRate) {
        [['registration', registrationRate], ['stamp_duty', stampRate]].forEach(([key, rate]) => {
            const input = rateInput(key);
            if (!input || rate === null || rate === undefined || rate === '') return;
            input.value = String(parseFloat(Number(rate).toFixed(4)));
            syncRateName(input);
        });
    }

    const currentRate = (key, fallback) => {
        const input = rateInput(key);
        const value = input ? parseFloat(String(input.value).replace(/[^0-9.]/g, '')) : NaN;
        return isNaN(value) ? (parseFloat(fallback) || 0) : value;
    };

    // The amount the percentages are taken of: the overridden assessed amount
    // when the operator unlocked it, otherwise the valuation last loaded.
    let lastBillBase = 0;
    function currentBillBase() {
        const flag = document.getElementById('bill_basis_overridden');
        if (flag && flag.value === '1' && financialInput) {
            return parseFloat(financialInput.value.replace(/[^0-9.]/g, '')) || 0;
        }
        return lastBillBase;
    }

    document.addEventListener('input', function (event) {
        const input = event.target.closest && event.target.closest('[data-rate-input]');
        if (!input) return;
        syncRateName(input);
        rebasePercentageFees(currentBillBase());
    });

    /**
     * Paint the Deeds workflow strip for the chosen file, and stop the wizard
     * when the file has not been valued.
     *
     * The same rule runs server-side in ConsentApplicationController::store();
     * this is the early warning, so an officer is told at file selection rather
     * than after filling four steps. Both read their mode from the same config,
     * so the button state and the save result cannot disagree.
     */
    function mountConsentPipeline() {
        if (!window.DeedsPipeline) return;

        window.DeedsPipeline.watch('#consent-pipeline-strip', {
            fileSel: '[name="file_number"]',
            stage: 'consent',
            consentType: function () {
                const t = document.querySelector('[name="consent_type"]');
                return t ? (t.value || '').trim() : '';
            },
            onState: function (state) {
                const nextBtn = document.getElementById('wizard-next-btn');
                consentPipelineBlocked = state.blocked;

                // The override is offered only when the gate is actually in the
                // way — it is a way past a specific obstacle, not a standing
                // option to skip valuation. Absent entirely for officers without
                // the permission, since the block is not rendered for them.
                const box = document.getElementById('manual-valuation-box');
                if (box) {
                    box.classList.toggle('hidden', !state.blocked);
                    if (!state.blocked) {
                        const cb = document.getElementById('manual_valuation');
                        if (cb) cb.checked = false;
                        const fields = document.getElementById('manual-valuation-fields');
                        if (fields) fields.classList.add('hidden');
                    }
                }

                applyConsentGateToNextButton();
                if (!nextBtn) return;
                if (!state.blocked) nextBtn.removeAttribute('title');
                else nextBtn.setAttribute('title', state.warn || 'Workflow stage outstanding');
            }
        });
    }

    // Read by the submit handler as a last check, in case the wizard was
    // advanced before the lookup came back.
    let consentPipelineBlocked = false;

    /**
     * Whether a ticked Manual Valuation is complete enough to stand in for the
     * missing valuation. Mirrors manualValuation() in the controller. The
     * assessed amount lives on a later step, so it is left to the server to
     * enforce.
     */
    function manualValuationSatisfied() {
        const cb = document.getElementById('manual_valuation');
        return !!(cb && cb.checked);
    }

    function applyConsentGateToNextButton() {
        const nextBtn = document.getElementById('wizard-next-btn');
        if (!nextBtn) return;

        const stop = consentPipelineBlocked && !manualValuationSatisfied();
        nextBtn.disabled = stop;
        nextBtn.classList.toggle('opacity-50', stop);
        nextBtn.classList.toggle('cursor-not-allowed', stop);
    }

    // Ticking the box reveals the assessed-amount reminder and re-tests the gate.
    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'manual_valuation') {
            const fields = document.getElementById('manual-valuation-fields');
            if (fields) fields.classList.toggle('hidden', !event.target.checked);
            applyConsentGateToNextButton();
        }
    });

    async function loadConsentBill(fileNumber) {
        mountConsentPipeline(fileNumber);

        // Entering the Payments step repeatedly must not re-hit the endpoint,
        // and must not re-run the consideration prefill over an edited figure.
        // Keyed on the transaction type too: changing it changes every rate.
        const typeKey = (transactionTypeSelect && transactionTypeSelect.value) || '';
        const billKey = fileNumber ? fileNumber + '|' + typeKey : null;
        if (billKey && billKey === lastBilledFileNumber) {
            return;
        }
        lastBilledFileNumber = billKey;

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
                stamp_duty_amount: 'bill_stamp_duty',
                registration_fee: 'bill_registration_fee',
                processing_fee: 'bill_processing_fee'
            };

            // Under a transaction type stamp duty is paid to KIRS / FIRS and is
            // not part of the Ministry total; the server's bill_total agrees.
            const stampExcluded = !!currentTransactionType();
            let fallbackTotal = 0;
            Object.keys(byKey).forEach((key) => {
                const el = document.getElementById(byKey[key]);
                if (!el) return;
                const row = el.closest('.bill-row');
                const fallback = row ? parseFloat(row.dataset.default || '0') || 0 : 0;
                const value = bill ? bill[key] : fallback;
                if (!(stampExcluded && key === 'stamp_duty_amount')) {
                    fallbackTotal += Number(value) || 0;
                }
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
            statusEl.innerHTML = 'Select a file to load the valuation';
            statusEl.className = 'text-xs font-medium text-slate-400';
            amountEl.value = '';
            if (sourceEl) sourceEl.textContent = '';
            showWarning(null);
            setRows(null);
            return;
        }

        // Say so immediately: the lookup is a round trip, and without this the
        // rows sit at their defaults with nothing to say whether a file was
        // even registered.
        statusEl.innerHTML = '<span class="inline-flex items-center gap-1.5">'
            + '<span class="inline-block h-3 w-3 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></span>'
            + 'Loading valuation for ' + fileNumber + '…</span>';
        statusEl.className = 'text-xs font-medium text-blue-600';

        try {
            const response = await fetch(
                `/consent-applications/valuation-bill?file_number=${encodeURIComponent(fileNumber)}`
                    + `&transaction_type=${encodeURIComponent(typeKey)}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const result = await response.json();

            // A missing or zero valuation shows the reason and leaves the rows
            // at zero: it must never read as a genuine ₦0.00 assessment.
            if (!result.success || !result.has_valuation) {
                const message = result.message || 'No valuation available for this file.';
                // A Gift is not valued (no consideration changes hands, and the
                // deeds pipeline skips its valuation stage), so the missing
                // report is expected there, not a problem to warn about.
                const isGift = consentTypeSelect && consentTypeSelect.value === 'Gift';
                statusEl.innerHTML = isGift ? 'Valuation not required for a Gift' : 'No valuation';
                statusEl.className = 'text-xs font-medium ' + (isGift ? 'text-slate-500' : 'text-amber-600');
                amountEl.value = '';
                if (sourceEl) sourceEl.textContent = '';
                showWarning(isGift ? null : message);
                setRows(null);
                return;
            }

            const bill = result.data;
            amountEl.value = money(bill.valuation_amount);
            setRows(bill);
            lastBillBase = Number(bill.valuation_amount) || 0;
            // The server computed at the type's default rates. An overridden
            // assessed amount or an edited percentage keeps governing the
            // percentage lines, so recompute on screen with them.
            const basisFlag = document.getElementById('bill_basis_overridden');
            const ratesEdited = !!document.querySelector('[data-rate-input][name]');
            if ((basisFlag && basisFlag.value === '1') || ratesEdited) {
                rebasePercentageFees(currentBillBase());
            }
            statusEl.innerHTML = 'Valuation linked';
            statusEl.className = 'text-xs font-medium text-emerald-600';
            showWarning(null);
            prefillConsiderationFromValuation(bill.valuation_amount);
            prefillApplicantFromValuation(result.prefill);
            if (sourceEl) {
                sourceEl.textContent = 'Valuation report #' + bill.valuation_report_id
                    + ' · ' + bill.valuation_reference + ' · ' + bill.valuation_status;
            }
        } catch (error) {
            console.error('Consent bill lookup failed:', error);
            statusEl.innerHTML = 'Could not load the valuation for this file.';
            statusEl.className = 'text-xs font-medium text-red-600';
            showWarning('The valuation service could not be reached. Try again before saving.');
            setRows(null);
        }
    }

    /**
     * Backfill the applicant and property details the valuation already holds.
     *
     * The valuation is the first time the Ministry visits the property and
     * records who holds it and where it is, so re-keying it here is both wasted
     * work and a chance to disagree with the report the bill is raised against.
     *
     * Prefill only, on the same terms as the consideration above: an empty
     * field is filled, a field this function filled earlier is replaced when a
     * different file is chosen, and anything the operator typed is left alone.
     * The server sends only fields the valuation genuinely holds. That now
     * includes applicant_phone: the number is taken once, at valuation, and
     * Instrument Capture reads it back off the consent in turn. A report
     * captured before that field existed sends none, and the box stays empty.
     *
     * @param {Object<string,string>|undefined} prefill keyed by form field name
     */
    function prefillApplicantFromValuation(prefill) {
        if (!prefill || typeof prefill !== 'object') {
            return;
        }

        Object.keys(prefill).forEach(function (name) {
            const value = (prefill[name] || '').trim();
            if (!value) {
                return;
            }

            const field = document.querySelector('[name="' + name + '"]');
            if (!field) {
                return;
            }

            // Never fight the operator: only an empty field, or one this
            // function filled for a previously selected file, is written to.
            const isOurs = field.dataset.valuationFilled === '1';
            if (field.value.trim() !== '' && !isOurs) {
                return;
            }

            field.value = value;
            field.dataset.valuationFilled = '1';
            // Let any listener bound to the field (address composition, the
            // district/LGA selects) react as though it had been typed.
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    // Typing clears the flag, so an edited value is treated as the operator's
    // own and survives a later file reselect.
    document.addEventListener('input', function (event) {
        const field = event.target;
        if (field && field.dataset && field.dataset.valuationFilled === '1' && event.isTrusted) {
            delete field.dataset.valuationFilled;
        }
    }, true);

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
     * Unlocking and rebasing the assessed amount.
     *
     * The figure is locked to the valuation by default so it cannot be edited
     * by accident. The pencil unlocks it; once edited, Consideration and
     * Stamp Duty are recalculated from the new amount and a flag is posted so
     * the server rebases too — without it the save would silently revert to
     * the valuation-derived figures the operator had just replaced.
     */
    function initConsiderationOverride() {
        const field = document.getElementById('consideration_figures');
        const btn = document.getElementById('consideration-edit-btn');
        const flag = document.getElementById('bill_basis_overridden');
        const note = document.getElementById('consideration-override-note');

        if (!field || !btn || btn.dataset.ready === '1') {
            return;
        }
        btn.dataset.ready = '1';

        btn.addEventListener('click', function () {
            const unlocking = field.hasAttribute('readonly');

            if (unlocking) {
                field.removeAttribute('readonly');
                field.classList.remove('bg-slate-100', 'cursor-not-allowed');
                field.classList.add('bg-amber-50', 'border-amber-200');
                field.focus();
                field.select();
            } else {
                field.setAttribute('readonly', 'readonly');
                field.classList.add('bg-slate-100', 'cursor-not-allowed');
                field.classList.remove('bg-amber-50', 'border-amber-200');
            }
        });

        field.addEventListener('input', function (event) {
            // Only a real edit rebases; the valuation prefill dispatches an
            // untrusted event and must not mark the amount as overridden.
            if (!event.isTrusted || field.hasAttribute('readonly')) {
                return;
            }

            if (flag) flag.value = '1';
            if (note) note.classList.remove('hidden');
            rebasePercentageFees(parseFloat(this.value.replace(/[^0-9.]/g, '')) || 0);
        });
    }

    /**
     * Recalculate the percentage lines from a new base.
     *
     * Display only, and only the two percentage lines — the fixed fees do not
     * depend on the base. The server recomputes the same way on save.
     */
    function rebasePercentageFees(base) {
        const section = document.getElementById('consent-bill-section');
        if (!section) {
            return;
        }

        const money = (v) => '₦' + Number(v || 0).toLocaleString('en-NG', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });

        const profile = currentTransactionType();
        const pairs = [
            ['bill_stamp_duty', currentRate('stamp_duty', profile ? profile.stamp_duty_rate : section.dataset.stampDutyRate)],
            ['bill_registration_fee', currentRate('registration', profile ? profile.registration_rate : section.dataset.registrationRate)]
        ];

        pairs.forEach(([id, rate]) => {
            const el = document.getElementById(id);
            if (!el) return;
            // Round each line before it is summed, matching the server.
            el.textContent = money(Math.round(base * rate) / 100);
        });

        updateBillTotalPreview();
    }

    initConsiderationOverride();

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

        const stampExcluded = !!currentTransactionType();
        let total = 0;
        section.querySelectorAll('.bill-row').forEach((row) => {
            // Paid to KIRS / FIRS, not the Ministry — see applyTransactionType().
            if (stampExcluded && row.dataset.fee === 'stamp_duty_amount') return;
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


    const modalTitle = document.getElementById('modal-title');
    const submitBtn = document.querySelector('button[type="submit"][form="consent-form"]');
    const consentVariant = window.consentFormVariant || 'letter';
    const letterDateInput = document.getElementById('letter_date');
    const applicationDateInput = document.getElementById('application_submitted_date');

    // admin/header.blade.php enhances every input[type=date] with flatpickr (altInput),
    // so the original input is hidden and setting .value leaves the visible field stale.
    // Go through the flatpickr instance when there is one.
    const setDateInputValue = (input, value) => {
        if (!input) return;
        if (input._flatpickr) {
            if (value) {
                input._flatpickr.setDate(value, false);
            } else {
                input._flatpickr.clear(false);
            }
            return;
        }
        input.value = value || '';
    };
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
            if (isApplication) setDateInputValue(letterDateInput, '');
        }
        if (applicationDateInput) {
            applicationDateInput.required = isApplication;
            applicationDateInput.disabled = !isApplication;
            if (!isApplication) setDateInputValue(applicationDateInput, '');
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

    // The assignee must be a DIFFERENT person from both the applicant and the party
    // who currently holds the title. Either match means the transaction is already
    // done (or is a no-op), so flag it the moment the name is typed rather than
    // letting the user complete four wizard steps and fail at submit.
    //
    // setCustomValidity() is what does the blocking: it makes the native
    // checkValidity() used by both validateWizardStep() and the submit handler fail,
    // so Next and Create are both stopped without any extra wiring.
    function checkAssigneeNameConflict() {
        const partyInput = document.querySelector('input[name="party_name"]');
        if (!partyInput) return true;

        const warning = document.getElementById('party-name-duplicate-warning');
        const warningText = document.getElementById('party-name-duplicate-warning-text');
        const norm = (selector) => {
            const el = document.querySelector(selector);
            return el ? (el.value || '').trim().toUpperCase() : '';
        };

        const assignee = norm('input[name="party_name"]');
        const applicant = norm('input[name="applicant_name"]');
        const holder = norm('input[name="original_holder_name"]');
        const roleLabel = (document.getElementById('party-role-label')?.textContent || 'Assignee')
            .replace(/\s*Details\s*$/i, '').trim() || 'Assignee';

        let message = '';
        if (assignee && applicant && assignee === applicant) {
            message = `The ${roleLabel} cannot be the same person as the Applicant. A property cannot be transferred to its own holder.`;
        } else if (assignee && holder && assignee === holder) {
            message = `${partyInput.value.trim()} is already the registered holder of this file, so this transaction has already been registered.`;
        }

        partyInput.setCustomValidity(message);
        partyInput.classList.toggle('border-red-400', !!message);
        partyInput.classList.toggle('bg-red-50', !!message);
        partyInput.classList.toggle('border-slate-200', !message);

        if (warning && warningText) {
            warningText.textContent = message;
            warning.classList.toggle('hidden', !message);
            if (message && window.lucide) window.lucide.createIcons();
        }

        return !message;
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

        // Step 1's two required choices get inline messages. The file number is
        // readonly — filled only by the selector — and the browser skips
        // constraint validation on readonly inputs, so without this a missing
        // file let the wizard stall with nothing on screen to say why.
        if (stepNumber === 1) {
            let ok = true;
            if (transactionTypeSelect && !transactionTypeSelect.disabled && !transactionTypeSelect.value) {
                setFieldError('transaction_type_error', 'Select the transfer type before continuing.');
                ok = false;
            }
            if (filenoInput && !filenoInput.disabled && !filenoInput.value.trim()) {
                setFieldError('file_number_error', 'No file selected. Use the search button to pick the file this consent is for.');
                ok = false;
            } else {
                setFieldError('file_number_error', '');
            }
            if (!ok) {
                const firstError = stepEl.querySelector('[id$="_error"]:not(.hidden)');
                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        }

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

        // The file number is set from several places and a programmatic .value
        // assignment fires no event, so load the bill whenever the Payments
        // step opens. loadConsentBill() skips a refetch for the same file.
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

    // asEdit=false means the user picked a file that ALREADY has a consent while
    // capturing a NEW one (the next assignment, to a different owner). Only
    // file/property-level facts may carry over. Anything belonging to the previous
    // transaction — the parties, the money, the applicant's identity, the tracking
    // number, and above all the record id — must not, or the new consent silently
    // becomes an edit of the old one and 403s when that one has been printed.
    function populateFromApplicationRecord(appData, { asEdit = true } = {}) {
        if (!appData) {
            return;
        }

        if (asEdit) {
            applicationIdInput.value = appData.id || '';
        }
        filenoInput.value = appData.file_number || filenoInput.value;
        loadConsentBill(filenoInput.value);

        const setInputValue = (name, value) => {
            const input = document.querySelector(`[name="${name}"]`);
            if (input) input.value = value || '';
        };

        if (asEdit) {
            setInputValue('applicant_name', appData.applicant_name);
            setInputValue('party_name', appData.party_name);
            setInputValue('consideration', appData.consideration);
            setInputValue('consideration_words', appData.consideration_words);
        }
        setRightOfOccupancyNumber(appData.right_of_occupancy_number || appData.file_number || '');
        updateApplicantNamePreview();
        setInputValue('right_of_occupancy_landuse', appData.right_of_occupancy_landuse);
        filterRightOfOccupancyPurposeOptions();
        setInputValue('purpose_of_right_of_occupancy', appData.purpose_of_right_of_occupancy);
        if (asEdit) {
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
        if (asEdit) {
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
            // Blank on consents captured before transaction types existed; the
            // officer must choose one before the edit can be saved. The
            // record's own consent type and percentages are kept.
            if (transactionTypeSelect) {
                transactionTypeSelect.value = appData.transaction_type || '';
                applyTransactionType(false);
                if (appData.transaction_type) {
                    setRateValues(appData.registration_rate, appData.stamp_duty_rate);
                }
            }

            parseAndPopulateAddress(appData.applicant_address, 'applicant');
            parseAndPopulateAddress(appData.party_address, 'party');
        }
        parseAndPopulateAddress(appData.property_description, 'property');
        if (asEdit) {
            parseAndPopulateAddress(appData.correspondence_address || appData.applicant_address, 'correspondence');
            syncApplicantWithCorrespondence();
        }

        if (asEdit && document.getElementById('applicant_address_hidden')) {
            document.getElementById('applicant_address_hidden').value = appData.applicant_address || '';
        }
        if (asEdit && document.getElementById('party_address_hidden')) {
            document.getElementById('party_address_hidden').value = appData.party_address || '';
        }
        if (document.getElementById('property_address_hidden')) {
            document.getElementById('property_address_hidden').value = appData.property_description || '';
        }
        if (asEdit && document.getElementById('correspondence_address_hidden')) {
            document.getElementById('correspondence_address_hidden').value = appData.correspondence_address || appData.applicant_address || '';
        }

        const propertyPreview = document.getElementById('property_address_preview');
        if (propertyPreview) propertyPreview.textContent = appData.property_description || 'No description built yet...';
        if (asEdit) {
            const applicantPreview = document.getElementById('applicant_address_preview');
            const partyPreview = document.getElementById('party_address_preview');
            const correspondencePreview = document.getElementById('correspondence_address_preview');
            if (applicantPreview) applicantPreview.textContent = appData.applicant_address || 'No address built yet...';
            if (partyPreview) partyPreview.textContent = appData.party_address || 'No address built yet...';
            if (correspondencePreview) correspondencePreview.textContent = document.getElementById('correspondence_address_hidden')?.value || 'No address built yet...';
        }

        populateAdditionalProperties(appData.additional_properties);

        if (asEdit) {
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
        }

        setInteractiveFormState(true);
        checkAssigneeNameConflict();

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
                    modalTitle.textContent = 'Create Consent Application';
                    submitBtn.querySelector('span').textContent = 'Create Consent Application';
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

                // Letter Date defaults to today; Application Date is the date on the
                // applicant's own letter, so it is left blank for the user to enter.
                const today = new Date().toISOString().split('T')[0];
                if (letterDateInput) setDateInputValue(letterDateInput, today);
                if (applicationDateInput) setDateInputValue(applicationDateInput, '');
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

            // form.reset() restores values but not data attributes; a stale
            // autofill flag would let the next open overwrite a saved
            // consideration with the valuation amount.
            const considerationEl = document.querySelector('input[name="consideration"]');
            if (considerationEl) delete considerationEl.dataset.autofilled;
            lastBilledFileNumber = null;

            // form.reset() unticks the box but leaves the fields it disabled,
            // so unlock them explicitly or the next application opens with an
            // uneditable correspondence address.
            document.querySelectorAll('.address-component-correspondence').forEach((el) => {
                el.disabled = false;
                el.classList.remove('bg-slate-100', 'cursor-not-allowed');
                el.classList.add('bg-white');
            });
            applicationIdInput.value = '';
            currentEditingAppType = null; // Reset edit mode tracking
            // form.reset() restores values but not setCustomValidity(), so a stale
            // conflict would keep the next application unsubmittable.
            checkAssigneeNameConflict();

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
            setFieldError('transaction_type_error', '');
            setFieldError('file_number_error', '');
            lastBillBase = 0;
            applyTransactionType(true);
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
            loadConsentBill(filenoInput.value);
            setRightOfOccupancyNumber(appData.right_of_occupancy_number || appData.file_number || '');
            document.querySelector('input[name="applicant_name"]').value = appData.applicant_name;
            document.querySelector('input[name="party_name"]').value = appData.party_name;
            updateApplicantNamePreview();
            const letterDate = appData.application_date ? appData.application_date.split(' ')[0] : new Date().toISOString().split('T')[0];
            if (letterDateInput) setDateInputValue(letterDateInput, letterDate);
            // Blank when the record has none — never substitute today's date.
            const applicationDate = appData.application_submitted_date ? appData.application_submitted_date.split(' ')[0] : '';
            if (applicationDateInput) setDateInputValue(applicationDateInput, applicationDate);

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
            // Blank on consents captured before transaction types existed; the
            // officer must choose one before the edit can be saved. The
            // record's own consent type and percentages are kept.
            if (transactionTypeSelect) {
                transactionTypeSelect.value = appData.transaction_type || '';
                applyTransactionType(false);
                if (appData.transaction_type) {
                    setRateValues(appData.registration_rate, appData.stamp_duty_rate);
                }
            }

            // Explicitly set application_type from record (preserve original)
            if (applicationTypeInput && appData.application_type) {
                applicationTypeInput.value = appData.application_type;
            }

            checkAssigneeNameConflict();

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
                            setFieldError('file_number_error', '');
            loadConsentBill(filenoInput.value);
                            setRightOfOccupancyNumber(result.fileNumber);
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
                                // Each number goes to its own field. This used
                                // to read `record.plot_no || record.house_no`
                                // into the single combined box, which is the
                                // conflation the split was asked to undo.
                                if (document.getElementById('property_house_no')) {
                                    document.getElementById('property_house_no').value = record.house_no || '';
                                }
                                if (document.getElementById('property_plot_no')) {
                                    document.getElementById('property_plot_no').value = record.plot_no || '';
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
                                // A file can be assigned again and again over its life, so an
                                // existing consent on it is a PREVIOUS transaction, not this one.
                                // Carry over only its file/property facts and never its id —
                                // adopting the id turned every new consent into an edit of the
                                // last one. Use the Edit button in the list to edit deliberately.
                                const existingApplication = await fetchApplicationByFileNumber(result.fileNumber);
                                if (existingApplication) {
                                    populateFromApplicationRecord(existingApplication, { asEdit: false });
                                }
                                applicationIdInput.value = '';
                            } catch (error) {
                                console.error('Lookup error:', error);
                            }

                            // Original Holder Name belongs to the FILE (its registered title),
                            // not to any earlier application on it. Assert it AFTER the lookup
                            // above, which replays a previous application's whole saved form —
                            // on a brand-new consent that was carrying the old applicant's name
                            // onto a file whose title is someone else entirely.
                            const fileTitle = result.file_title || result.file_name
                                || (result.record || {}).file_name || (result.record || {}).file_title || '';
                            const originalHolderInput = document.querySelector('input[name="original_holder_name"]');
                            if (originalHolderInput && fileTitle) {
                                originalHolderInput.value = fileTitle;
                            }
                            // Assigning .value fires no input event, so re-run the conflict
                            // check by hand now that the holder name is known.
                            checkAssigneeNameConflict();
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
            plotNo: fieldValue(block, 'additional_property_plot_no[]'),
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
            setValue('additional_property_plot_no[]', property.plot_no);
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

    // Live assignee-conflict detection: any of the three names changing can create
    // or clear the conflict, so all three drive the same check.
    ['party_name', 'applicant_name', 'original_holder_name'].forEach(fieldName => {
        const field = document.querySelector(`input[name="${fieldName}"]`);
        if (!field) return;
        field.addEventListener('input', checkAssigneeNameConflict);
        field.addEventListener('change', checkAssigneeNameConflict);
    });

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

    /**
     * Copy the applicant address onto the correspondence fields.
     *
     * The two are the same address most of the time, so the checkbox saves
     * retyping it. While ticked the correspondence fields are locked and kept
     * in step as the applicant address is edited; unticking leaves the copied
     * values in place to be adjusted rather than clearing them.
     */
    function copyApplicantAddressToCorrespondence() {
        const get = (id) => document.getElementById(id);

        const applicantHouseNo = get('applicant_house_no');
        const correspondenceHouseNo = get('correspondence_house_no');

        if (!applicantHouseNo || !correspondenceHouseNo) {
            return;
        }

        correspondenceHouseNo.value = applicantHouseNo.value || '';
        setStreetSelectValue(get('correspondence_street'), getSelectOrManualValue(get('applicant_street')));

        const applicantDistrict = get('applicant_district');
        const applicantDistrictOther = get('applicant_district_other');
        const districtValue = applicantDistrict && applicantDistrict.value === 'Other'
            ? (applicantDistrictOther ? applicantDistrictOther.value : '')
            : (applicantDistrict ? applicantDistrict.value : '');
        setDistrictValue(get('correspondence_district'), get('correspondence_district_other'), districtValue);

        const applicantState = get('applicant_state');
        setSelectValueWithFallback(get('correspondence_state'), applicantState ? applicantState.value : '');

        // The LGA list is populated per state, so load it before selecting —
        // otherwise the option does not exist yet and the value is dropped.
        const applicantLga = get('applicant_lga');
        ensureLgaOptions('correspondence', applicantState ? applicantState.value : '',
            applicantLga ? applicantLga.value : '');

        updateBuiltAddress('correspondence');
    }

    function initCorrespondenceSameAsApplicant() {
        const checkbox = document.getElementById('correspondence_same_as_applicant');
        if (!checkbox || checkbox.dataset.ready === '1') {
            return;
        }
        checkbox.dataset.ready = '1';

        const fields = () => document.querySelectorAll('.address-component-correspondence');

        const setLocked = (locked) => {
            fields().forEach((el) => {
                // Disabled inputs still expose .value, so the composed address
                // and its hidden field keep working while locked.
                el.disabled = locked;
                el.classList.toggle('bg-slate-100', locked);
                el.classList.toggle('cursor-not-allowed', locked);
                el.classList.toggle('bg-white', !locked);
            });
        };

        checkbox.addEventListener('change', function () {
            setLocked(this.checked);
            if (this.checked) {
                copyApplicantAddressToCorrespondence();
            }
        });

        // Keep it in step while the applicant address is edited.
        document.querySelectorAll('.address-component-applicant').forEach((el) => {
            ['change', 'input'].forEach((evt) => el.addEventListener(evt, function () {
                if (checkbox.checked) {
                    copyApplicantAddressToCorrespondence();
                }
            }));
        });
    }

    initCorrespondenceSameAsApplicant();

    /**
     * Formerly copied the correspondence address onto the applicant fields.
     *
     * The applicant now has its own editable address section, so mirroring
     * would overwrite whatever the operator entered — and, on edit, would
     * overwrite the applicant address just loaded from the saved record. Kept
     * as a no-op rather than removed, so the several call sites still resolve.
     */
    function syncApplicantWithCorrespondence() {
        return;
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

    function composeAddress({ houseNo, plotNo, street, district, lga, state }) {
        const parts = [];
        // House No and Plot No are separate entries. The house number belongs in
        // front of the street it is on ("12 Zoo Road"); the plot number is a
        // cadastral reference that stands on its own and is labelled, because
        // "12 Zoo Road, 45, Nassarawa" reads as a second house number.
        //
        // This replaces a house-or-street choice that discarded the street
        // outright whenever a number had been entered.
        const streetLine = [houseNo, street].filter(Boolean).map(addrTitleCase).join(' ');
        if (streetLine) parts.push(streetLine);
        if (plotNo) {
            parts.push(/^plot\b/i.test(plotNo) ? addrTitleCase(plotNo) : 'Plot ' + addrTitleCase(plotNo));
        }
        if (district) parts.push(addrTitleCase(district));
        if (lga) parts.push(addrTitleCase(lga));
        if (state) parts.push(addrTitleCase(state) + ' State');

        return parts.join(', ') + (parts.length > 0 ? '.' : '');
    }

    function updateBuiltAddress(type) {
        let houseNo, plotNo, street, district, lga, state, hiddenInput, previewSpan;

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
            plotNo = getVal('property_plot_no');
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

        const fullAddress = composeAddress({ houseNo, plotNo, street, district, lga, state });
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

    /**
     * Re-render the consent table and the stat cards from the server without a
     * page reload. The page is fetched again and only those two regions are
     * swapped; Alpine picks up the new row menus on its own, and the row
     * buttons are handled by delegated listeners, so nothing needs rebinding.
     */
    window.refreshConsentTable = async function () {
        // The Deeds Applications page reuses this wizard, and its
        // #consent-table is a server-side DataTable: swapping its tbody would
        // empty it, so ask DataTables to redraw the current page instead.
        const $ = window.jQuery;
        if ($ && $.fn && $.fn.DataTable && $.fn.DataTable.isDataTable('#consent-table')) {
            $('#consent-table').DataTable().ajax.reload(null, false);
            return;
        }
        if (!document.querySelector('#consent-table')) {
            window.location.reload();
            return;
        }
        try {
            const response = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                cache: 'no-store'
            });
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');

            ['#consent-table tbody', '#consent-stats'].forEach((selector) => {
                const fresh = doc.querySelector(selector);
                const current = document.querySelector(selector);
                if (fresh && current) current.innerHTML = fresh.innerHTML;
            });

            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            // The save itself succeeded; a failed refresh only leaves the table
            // stale, so fall back to a reload rather than hiding the new row.
            console.error('Consent table refresh failed:', error);
            window.location.reload();
        }
    };

    /**
     * Print on a consent captured before transaction types existed.
     *
     * Those rows have no type, so their letter would print without the fee
     * schedule or the KIRS / FIRS payee. Clicking Print first asks for the
     * transaction type (and lets the consent type and percentages be adjusted),
     * saves the choice over AJAX — the server recomputes the bill — and then
     * opens the letter. Rows that already have a type print straight away.
     */
    const escapeHtml = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    document.addEventListener('click', async function (e) {
        const link = e.target.closest('.consent-print-needs-type');
        if (!link) return;
        if (typeof Swal === 'undefined') return; // fall through to a plain print

        e.preventDefault();
        // The Deeds Applications page shows its row menu as a floating panel
        // that would otherwise stay open behind the dialog.
        const floatingMenu = document.getElementById('deeds-global-dropdown');
        if (floatingMenu) floatingMenu.style.visibility = 'hidden';

        const id = link.getAttribute('data-id');
        const fileNumber = link.getAttribute('data-file') || '';
        const consentType = link.getAttribute('data-consent-type') || 'Assignment';
        const letterUrl = link.getAttribute('href');

        // Only the party options: Gift and Mortgage are settled by the consent type.
        const typeOptions = Object.keys(TRANSACTION_TYPES).filter((key) => !impliedValues.includes(key)).map((key) =>
            `<option value="${escapeHtml(key)}">${escapeHtml(TRANSACTION_TYPES[key].label)}</option>`).join('');
        const consentOptions = ['Assignment', 'Gift', 'Mortgage'].map((v) =>
            `<option value="${v}" ${v === consentType ? 'selected' : ''}>${v}</option>`).join('');
        const field = 'w-full px-3 py-2 rounded-lg border border-slate-200 text-sm';
        const label = 'block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1 text-left';

        const choice = await Swal.fire({
            title: 'Before printing',
            width: 560,
            html: `
                <p class="text-sm text-slate-600 mb-4 text-left">File <strong>${escapeHtml(fileNumber)}</strong> was captured
                    before transfer types. Choose one so the letter prints with the right fees and stamp duty payee.
                    The choice is saved on the consent.</p>
                <div class="grid grid-cols-2 gap-3 text-left">
                    <div class="col-span-2">
                        <label class="${label}">Consent Type</label>
                        <select id="pt-consent" class="${field}">${consentOptions}</select>
                    </div>
                    <div class="col-span-2" id="pt-type-wrap">
                        <label class="${label}">Transfer Type *</label>
                        <select id="pt-type" class="${field}"><option value="">Select transfer type…</option>${typeOptions}</select>
                    </div>
                    <div>
                        <label class="${label}">Registration (%)</label>
                        <input id="pt-reg" type="text" inputmode="decimal" class="${field} font-mono">
                    </div>
                    <div>
                        <label class="${label}">Stamp Duty (%)</label>
                        <input id="pt-sd" type="text" inputmode="decimal" class="${field} font-mono">
                    </div>
                    <div class="col-span-2 text-xs text-slate-500" id="pt-payee"></div>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'Save & Print',
            confirmButtonColor: '#2563eb',
            focusConfirm: false,
            showLoaderOnConfirm: true,
            didOpen: () => {
                const typeSel = document.getElementById('pt-type');
                const consentSel = document.getElementById('pt-consent');
                // The transaction type in force: implied by Gift / Mortgage,
                // otherwise the party option chosen.
                const effectiveType = () => IMPLIED_TYPES[consentSel.value] || typeSel.value;
                const refresh = () => {
                    const implied = IMPLIED_TYPES[consentSel.value];
                    document.getElementById('pt-type-wrap').classList.toggle('hidden', !!implied);
                    const profile = TRANSACTION_TYPES[effectiveType()];
                    if (!profile) {
                        document.getElementById('pt-payee').textContent = '';
                        return;
                    }
                    // Defaults follow the type; the percentages stay editable.
                    document.getElementById('pt-reg').value = String(profile.registration_rate);
                    document.getElementById('pt-sd').value = String(profile.stamp_duty_rate);
                    document.getElementById('pt-payee').textContent = profile.label + ': stamp duty payable to the '
                        + (STAMP_DUTY_PAYEES[profile.payee] || profile.payee)
                        + '. Processing fee ' + formatMoney(profile.processing_fee) + '.';
                };
                typeSel.addEventListener('change', refresh);
                consentSel.addEventListener('change', refresh);
                refresh();
            },
            preConfirm: async () => {
                const consentValue = document.getElementById('pt-consent').value;
                const type = IMPLIED_TYPES[consentValue] || document.getElementById('pt-type').value;
                if (!type) {
                    Swal.showValidationMessage('Select a transfer type.');
                    return false;
                }
                const profile = TRANSACTION_TYPES[type];
                const body = {
                    transaction_type: type,
                    consent_type: document.getElementById('pt-consent').value,
                    bill_rate: {}
                };
                // Only rates that differ from the type's are sent as edits.
                [['registration', 'pt-reg', profile.registration_rate], ['stamp_duty', 'pt-sd', profile.stamp_duty_rate]]
                    .forEach(([key, el, fallback]) => {
                        const v = parseFloat(document.getElementById(el).value);
                        if (!isNaN(v) && Math.abs(v - Number(fallback)) > 0.00005) body.bill_rate[key] = v;
                    });

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.content
                        || document.querySelector('input[name="_token"]')?.value;
                    const response = await fetch(`/consent-applications/${id}/transaction-type`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify(body)
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok || !result.success) {
                        const errors = result.errors ? Object.values(result.errors).flat() : [];
                        throw new Error(errors[0] || result.message || 'The update failed.');
                    }
                    return result;
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        });

        if (!choice.isConfirmed || !choice.value) return;

        // Still inside the click's activation window in most browsers; if the
        // pop-up is blocked anyway, offer a real link to click instead.
        const opened = window.open(letterUrl, '_blank');
        window.refreshConsentTable();
        if (!opened) {
            Swal.fire({
                icon: 'success',
                title: 'Saved',
                html: `${escapeHtml(choice.value.message)}<br><br>
                    <a href="${escapeHtml(letterUrl)}" target="_blank" class="inline-block px-4 py-2 rounded-lg bg-blue-600 text-white font-bold">Open Letter</a>`,
                showConfirmButton: false,
                showCloseButton: true
            });
        }
    });

    applyTransactionType(true);

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

            // Assignee may be neither the applicant nor the file's current holder.
            // checkAssigneeNameConflict() already flags this live and blocks via
            // setCustomValidity(); this is the backstop for values set programmatically
            // after the last check. Mirrored server-side in ConsentApplicationController.
            if (!checkAssigneeNameConflict()) {
                const msg = document.getElementById('party-name-duplicate-warning-text')?.textContent
                    || 'The Assignee conflicts with another party on this application.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Duplicate Party', text: msg, confirmButtonColor: '#dc2626' });
                } else {
                    alert(msg);
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
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                });

                const result = await response.json().catch(() => ({}));

                if (response.ok && result.success) {
                    // Saved over AJAX: close the wizard and refresh the table in
                    // place, rather than reloading the whole page.
                    toggleModal(false);
                    await window.refreshConsentTable();

                    const printUrl = result.id ? `/consent-applications/${result.id}` : null;
                    const notice = result.pipeline_warning ? '\n\n' + result.pipeline_warning : '';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: isEdit ? 'Updated' : 'Saved',
                            text: result.message + notice,
                            icon: result.pipeline_warning ? 'warning' : 'success',
                            showCancelButton: !!printUrl,
                            confirmButtonText: 'Close',
                            cancelButtonText: 'Open Letter',
                        }).then((choice) => {
                            if (printUrl && choice.dismiss === Swal.DismissReason.cancel) {
                                window.open(printUrl, '_blank');
                            }
                        });
                    } else {
                        alert(result.message + notice);
                    }
                } else {
                    // Laravel's 422 carries per-field messages; show them all
                    // instead of the generic "The given data was invalid."
                    const fieldErrors = result.errors
                        ? Object.values(result.errors).flat().filter(Boolean)
                        : [];
                    if (result.errors && result.errors.transaction_type) {
                        setFieldError('transaction_type_error', result.errors.transaction_type[0]);
                    }
                    throw new Error(fieldErrors.length ? fieldErrors.join('\n') : (result.message || 'Validation failed'));
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

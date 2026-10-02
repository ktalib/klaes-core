/**
 * "Fill demo data" — a testing aid for the Create File Index form.
 *
 * Filling that form by hand is forty-odd fields, and doing it for every test run
 * is most of the cost of testing indexing at all. This fetches one ready-made
 * payload from /fileindexing/demo-sample and puts it in the form.
 *
 * WHAT IT FILLS, AND WITH WHAT — the distinction is the whole safety story:
 *
 *   REAL      the file number and tracking ID come from an actual `grouping` row
 *             that is not yet in file_indexings. The file number is typed into the
 *             form and then the ORDINARY grouping lookup is triggered
 *             (autoFillArchiveDetailsFromAPI), so batch, shelf and tracking ID are
 *             filled by the same code path a real operator would use. Nothing here
 *             short-circuits that; a demo save exercises the real flow.
 *
 *   INVENTED  every human particular — holder, company, address, NIN, phone.
 *
 * This file is only ever loaded when config('fileindexing.demo_fill') is on AND
 * APP_ENV is not production, so on production it is not on the page at all.
 *
 * The form is half plain DOM inputs and half Alpine state (`indexingForm` ->
 * fileParams[0]). Writing only to .value would leave Alpine's copy stale and the
 * submitted payload would silently disagree with what is on screen, so both are
 * written — see fillAlpine().
 */
(function () {
    'use strict';

    /** File numbers already handed out on this page, so a second click differs. */
    const usedFileNumbers = [];

    function byId(id) {
        return document.getElementById(id);
    }

    /**
     * Set a plain input and let the rest of the page know.
     *
     * The form has change/blur listeners doing validation and dependent lookups;
     * assigning .value alone fires none of them, so a demo-filled field would
     * behave differently from a typed one.
     */
    function setInput(el, value) {
        if (!el || value === undefined || value === null) return false;

        // flatpickr replaces the visible input and ignores direct .value writes —
        // the calendar and the submitted value would drift apart. Always go
        // through its API when it has claimed the field.
        if (el._flatpickr && typeof el._flatpickr.setDate === 'function') {
            el._flatpickr.setDate(value, true);
            return true;
        }

        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    /**
     * Pick a <select> option by visible text or value, case-insensitively.
     * Returns false when nothing matched, so the caller can fall back.
     */
    function selectByText(el, value) {
        if (!el || !value) return false;

        const wanted = String(value).trim().toUpperCase();
        const match = Array.from(el.options || []).find(function (opt) {
            return String(opt.value).trim().toUpperCase() === wanted
                || String(opt.textContent).trim().toUpperCase() === wanted;
        });

        if (!match) return false;

        el.value = match.value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    /**
     * Push the sample into the Alpine `indexingForm` component.
     *
     * fileParams[0] is what the submit handler actually reads for the property and
     * party fields; the visible inputs are bound to it. Writing the Alpine state is
     * therefore the authoritative half, and the DOM updates itself from it.
     */
    function fillAlpine(form) {
        if (typeof Alpine === 'undefined') return false;

        const root = document.querySelector('[x-data="indexingForm"], [x-data*="indexingForm"]');
        if (!root) return false;

        let data;
        try {
            data = Alpine.$data(root);
        } catch (error) {
            console.warn('Demo fill: Alpine state not reachable', error);
            return false;
        }

        if (!data || !Array.isArray(data.fileParams) || !data.fileParams.length) return false;

        const param = data.fileParams[0];

        // Identity / property
        param.title = form.file_title || param.title;
        param.applicant_name = form.applicant_name || param.applicant_name;
        param.gender = form.gender || param.gender;
        param.plot_number = form.plot_number || param.plot_number;
        param.plot_size = form.plot_size || param.plot_size;
        param.land_use_type = form.land_use_type || param.land_use_type;
        param.tp_number = form.tp_no || param.tp_number;
        param.lpkn_no = form.lpkn_no || param.lpkn_no;
        param.location = form.location || param.location;

        // Personal identifiers. These are Alpine-bound too — the #nin / #phone
        // inputs that exist elsewhere in the tree belong to legacy addon partials
        // this form does not include, so writing to them would fill nothing.
        param.nin = form.nin || param.nin;
        param.tin = form.tin || param.tin;
        param.rc_no = form.rc_no || param.rc_no;
        param.phone = form.phone || param.phone;
        param.dob = form.dob || param.dob;
        param.residence_address = form.residence_address || param.residence_address;

        // Entity / customer
        param.entity_type = form.entity_type || param.entity_type;
        param.entity_name = form.entity_name || param.entity_name;
        param.entity_physical_address = form.residence_address || param.entity_physical_address;
        param.customer_type = form.customer_type || param.customer_type;
        param.customer_name = form.customer_name || param.customer_name;
        param.property_address = form.location || param.property_address;

        // district / street carry an "Other + custom" pair. The demo values are
        // real Kano districts but the select is seeded per-LGA, so the option may
        // not be present; setting the plain value and clearing the custom half is
        // correct either way — resolveDistrictChoice() server-side takes the
        // custom value only when the select says "Other".
        param.district = form.district || param.district;
        param.custom_district = '';
        param.street_name = form.street_name || param.street_name;
        param.custom_street_name = '';
        param.lga = form.lga || param.lga;

        return true;
    }

    /**
     * The genuinely plain inputs — only the few that exist in the partials this
     * form actually includes (file_identification + the form shell). Everything
     * property-, party- and contact-shaped is Alpine; see fillAlpine().
     */
    function fillPlainInputs(form) {
        selectByText(byId('general-registry'), form.general_registry);
        selectByText(byId('indexing-type'), form.indexing_type);

        // Root of Title is a plain input in the Holders card. It only exists for the
        // SLTR and Land/Registry-3 combinations, so fill it only when the registries
        // just selected above actually ask for it — otherwise the demo would store a
        // root on a file the rule says has none.
        const rootOfTitle = byId('root-of-title');
        if (rootOfTitle && form.root_of_title && window.rootOfTitleApplies?.()) {
            rootOfTitle.value = form.root_of_title;
            rootOfTitle.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /**
     * Re-seat flatpickr on any date input Alpine just wrote to.
     *
     * A global enhancer wraps every date input on this app (admin/header), and
     * flatpickr keeps its own display element — an x-model write updates the
     * underlying value but leaves the visible box showing the old date. Pushing
     * the value back through setDate() puts the two back in agreement.
     */
    function syncFlatpickrInputs() {
        document.querySelectorAll('#new-file-form input').forEach(function (el) {
            if (el._flatpickr && el.value) {
                try {
                    el._flatpickr.setDate(el.value, false);
                } catch (error) {
                    /* a date the picker will not accept is not worth failing the fill over */
                }
            }
        });
    }

    function setBusy(button, busy, label) {
        const text = byId('demo-fill-btn-label');
        if (button) button.disabled = busy;
        if (text) text.textContent = label;
    }

    async function fillDemoData(button) {
        const url = button.getAttribute('data-demo-url');
        setBusy(button, true, 'Loading…');

        try {
            const params = new URLSearchParams();
            usedFileNumbers.forEach(function (fn) { params.append('exclude[]', fn); });

            const response = await fetch(url + (params.toString() ? '?' + params : ''), {
                headers: { Accept: 'application/json' },
            });

            const payload = await response.json().catch(function () { return null; });

            if (!response.ok || !payload || !payload.success) {
                const message = (payload && payload.message)
                    || 'Demo data is not available (the endpoint is off, or no unindexed file is left).';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'info', title: 'No demo data', text: message });
                } else {
                    alert(message);
                }
                return;
            }

            const fileNumber = payload.source.file_number;
            usedFileNumbers.push(fileNumber);

            // Held until the Property Transaction dialog is opened — it does not exist
            // on the page yet, and its Alpine component is created with it.
            stashTransactions(payload.transactions);

            // 1. Human particulars first, so they are already in place when the
            //    grouping lookup below repaints the archive fields.
            fillAlpine(payload.form);
            fillPlainInputs(payload.form);
            syncFlatpickrInputs();

            // 2. The real file number, then the ORDINARY grouping lookup — this is
            //    what fills tracking ID, batch and shelf. Deliberately not faked:
            //    a demo record that skipped this path would not test it.
            //
            //    The number lives in TWO inputs: #fileno is a hidden field (the
            //    value actually submitted) and #file-number-display is the visible
            //    one. Setting only the hidden field would leave the operator looking
            //    at a blank File Number box while the form submits a real number.
            setInput(byId('fileno'), fileNumber);
            setInput(byId('file-number-display'), fileNumber);

            if (typeof window.autoFillArchiveDetailsFromAPI === 'function') {
                try {
                    await window.autoFillArchiveDetailsFromAPI(fileNumber);
                } catch (error) {
                    console.warn('Demo fill: grouping lookup failed', error);
                }
            }

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Demo data filled',
                    html: '<div style="text-align:left;font-size:13px;">'
                        + '<div><b>File number</b> ' + fileNumber + ' <i>(real, not yet indexed)</i></div>'
                        + '<div><b>Tracking ID</b> ' + (payload.source.tracking_id || '—') + '</div>'
                        + (Array.isArray(payload.transactions) && payload.transactions.length
                            ? '<div style="margin-top:6px;"><b>' + payload.transactions.length
                              + ' transactions</b> are waiting — they fill in when you open '
                              + '<i>Add Property Transaction Details</i>.</div>'
                            : '')
                        + '<div style="margin-top:6px;color:#92400e;">'
                        + 'Holder, address and identifiers are invented sample data.</div>'
                        + '</div>',
                    timer: 3500,
                    timerProgressBar: true,
                });
            }
        } catch (error) {
            console.error('Demo fill failed', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Demo fill failed', text: error.message });
            }
        } finally {
            setBusy(button, false, 'Fill demo data');
        }
    }

    /* ------------------------------------------------------------------------
     * The Property Transaction card
     *
     * That card is a separate Alpine component in a dialog that does not exist yet
     * when "Fill demo data" is pressed, so the chain is STASHED here and applied the
     * first time the dialog opens. Filling it by hand is three transactions x eight
     * fields, which is most of the cost of testing the ownership-gap check.
     * ---------------------------------------------------------------------- */

    /** The chain waiting to be written into the card, or null once it has been. */
    let pendingTransactions = null;

    function applyPendingTransactions() {
        if (!pendingTransactions || typeof Alpine === 'undefined') return false;

        const host = document.querySelector('#property-transaction-dialog [x-data]');
        if (!host) return false;

        let card;
        try {
            card = Alpine.$data(host);
        } catch (error) {
            return false;
        }

        if (!card || typeof card.addTransaction !== 'function') return false;

        const chain = pendingTransactions;
        pendingTransactions = null;

        // Replace whatever blank blocks the card opened with, then add one per row.
        card.transactions = [];
        chain.forEach(function (row) {
            card.addTransaction();
            const block = card.transactions[card.transactions.length - 1];

            Object.keys(row).forEach(function (key) {
                if (row[key] !== undefined && row[key] !== null) block[key] = row[key];
            });

            // The dropdown is built from InstrumentTypes; a value that is not in the
            // list renders as a blank select, so register it the way a real pick does.
            if (typeof card.registerTransactionType === 'function') {
                card.registerTransactionType(block.transactionType);
            }
        });

        return true;
    }

    /**
     * Wrap the dialog's own open function, once it exists.
     *
     * Wrapping rather than polling the DOM: the card is populated by
     * openPropertyTransactionModal(), so the only safe moment to write into it is
     * after that has run and Alpine has settled.
     */
    function hookTransactionModal() {
        if (typeof window.openPropertyTransactionModal !== 'function'
            || window.openPropertyTransactionModal.__demoWrapped) {
            return typeof window.openPropertyTransactionModal === 'function';
        }

        const original = window.openPropertyTransactionModal;

        const wrapped = function () {
            const result = original.apply(this, arguments);

            if (pendingTransactions) {
                // The dialog sets its own state on a timeout; land after it, and retry a
                // few times rather than assuming a single delay is always enough.
                let attempts = 0;
                const timer = setInterval(function () {
                    attempts++;
                    if (applyPendingTransactions() || attempts > 12) clearInterval(timer);
                }, 250);
            }

            return result;
        };

        wrapped.__demoWrapped = true;
        window.openPropertyTransactionModal = wrapped;

        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const button = byId('demo-fill-btn');
        if (!button) return;

        // The modal partial may define its opener after this file runs, so keep
        // looking for a short while rather than giving up on the first miss.
        if (!hookTransactionModal()) {
            let tries = 0;
            const timer = setInterval(function () {
                tries++;
                if (hookTransactionModal() || tries > 40) clearInterval(timer);
            }, 250);
        }

        button.addEventListener('click', function () {
            fillDemoData(button);
        });
    });

    /** Called by fillDemoData() once the payload is in. */
    function stashTransactions(rows) {
        pendingTransactions = Array.isArray(rows) && rows.length ? rows : null;
    }
})();

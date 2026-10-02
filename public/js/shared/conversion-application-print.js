/**
 * "Application for Conversion" printing — shared by the MLS File Commissioning
 * table and the ST File Commissioning table.
 *
 * Both screens ask the same question (how was the property acquired?) and open the
 * same server-rendered sheet (FileNumberController::generateConversionApplication),
 * so the prompt lives here rather than being duplicated per page.
 *
 * The file number is preferred over the numeric id: the backend resolves it through
 * fileNumber.mlsfNo and can fall back to plot_extensions, and it cannot collide with
 * a different row that happens to share the id.
 */
(function (window) {
    'use strict';

    /**
     * The answer given the first time this sheet was printed, or null if it has
     * never been answered. Saved server-side on conversion_applications, so a
     * reprint doesn't have to ask again.
     *
     * Any failure resolves to null — the prompt then simply appears, which is the
     * old behaviour. Printing must never be blocked by this lookup.
     */
    function fetchSavedAcquisitionMethod(convKey) {
        return fetch(`/file-numbers/conversion-application/${convKey}/acquisition-method`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => (response.ok ? response.json() : null))
            .then(payload => {
                if (!payload) {
                    return null;
                }
                return {
                    method: payload.method ? String(payload.method) : '',
                    other: (payload.other || ''),
                    // Pre-fills the Allocation Source card: the answer stored for
                    // this sheet, else the file's own allocation info, else its LGA.
                    allocationSource: (payload.allocation_source || ''),
                    allocationEntity: (payload.allocation_entity || ''),
                    // Where a state entity is written to — an LGA needs none.
                    allocationAddress: (payload.allocation_address || ''),
                    // The source was entered at commissioning: confirm, don't rewrite.
                    allocationLocked: Boolean(payload.allocation_locked),
                    // SuA only: the institution it was allocated by, the officer
                    // the last copy was addressed to, and the parcel it names.
                    institutionCategory: (payload.institution_category || ''),
                    institutionName: (payload.institution_name || ''),
                    addressedTo: (payload.addressed_to || '')
                };
            })
            .catch(() => null);
    }

    /** Entities a State Government allocation can come from. Mirrors the SuA
     *  commissioning form (js/commission_new_st/sua_commission.js). */
    const STATE_ENTITIES = ['KSIP', 'HOUSING', 'KUNPDA'];

    /** The sentinel every lookup-driven dropdown appends. Never stored as a name. */
    const OTHERS_SPECIFY = 'OTHERS (SPECIFY)';

    /** The institution whose allocating body is a named council. */
    const LOCAL_GOVERNMENT = 'LOCAL GOVERNMENT';

    /**
     * The four Allocation Source lists, fetched once per page. Same promise for
     * every caller, so reopening the card costs nothing.
     */
    let lookupsPromise = null;

    function loadAllocationLookups() {
        if (!lookupsPromise) {
            lookupsPromise = fetch('/api/reference/allocation-source-lookups', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => (response.ok ? response.json() : null))
                .then(payload => (payload && payload.data) || {})
                // A failed lookup leaves "Others (Specify)", which still answers the card.
                .catch(() => ({}));
        }

        return lookupsPromise;
    }

    /** Fill a select with plain names plus the "Others (Specify)" sentinel. */
    function fillNameSelect(select, names, selectedName, placeholder) {
        const options = (names || []).slice();
        options.push(OTHERS_SPECIFY);

        select.innerHTML = `<option value="">${placeholder}</option>` + options
            .map(name => `<option value="${escapeAttr(name)}">${escapeHtml(name)}</option>`)
            .join('');

        if (selectedName) {
            // A stored name the list no longer offers is restored through the
            // sentinel, with the name itself shown in the "specify" box.
            select.value = options.indexOf(selectedName) !== -1 ? selectedName : OTHERS_SPECIFY;
        }
    }

    /**
     * Fill a select with both institution lists, grouped, exactly as the SuA
     * commissioning form offers them. Only a file commissioned before that form
     * asked the question gets this — every newer file already has an answer, and
     * shows it back as text instead.
     *
     * Each option carries the list it came from, which is what decides the
     * addressee names offered beside it.
     */
    function fillInstitutionGroups(select, lookups, selectedName) {
        const groups = [
            ['Government', 'GOVERNMENT', lookups.institution_government || []],
            ['Other Institutions', 'OTHER', lookups.institution_other || []]
        ];

        select.innerHTML = '<option value="">Select Institution</option>';

        groups.forEach(([label, category, names]) => {
            const group = document.createElement('optgroup');
            group.label = label;

            names.concat(OTHERS_SPECIFY).forEach((name) => {
                const option = new Option(name, name);
                option.dataset.category = category;
                group.appendChild(option);
            });

            select.appendChild(group);
        });

        if (selectedName) select.value = selectedName;
    }

    /** The list the selected institution came from: 'GOVERNMENT' or 'OTHER'. */
    function selectedInstitutionCategory(select) {
        const option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;

        return (option && option.dataset.category) === 'OTHER' ? 'OTHER' : 'GOVERNMENT';
    }

    /**
     * The legacy source/entity pair an institution maps onto — the JS twin of
     * App\Services\AllocationSourceResolver::toLegacy(). The server settles it
     * again on a locked sheet; this keeps an unlocked one consistent.
     */
    function legacyAllocationFor(institution, council) {
        if (String(institution || '').toUpperCase() === LOCAL_GOVERNMENT) {
            return { source: 'Local Government', entity: council || '' };
        }

        return { source: 'State Government', entity: institution || '' };
    }

    /** Every sheet is a Kano State document — the state line is not a choice. */
    const SHEET_STATE = 'Kano';

    /**
     * The address is built from parts (plot no, district, LGA, state) and stored
     * as the composed line. These two keep that round-trip: compose() writes the
     * canonical form, parse() reads it back so a reprint opens with the same parts
     * in the same boxes.
     */
    /**
     * Reference names come out of the lookups shouting (AFFA, GAYA); the sheet is
     * a letter, so they are title-cased. A plot number is left as typed, upper-cased
     * because it is a code (12A), not a word.
     */
    function titleCase(value) {
        return String(value)
            .toLowerCase()
            .replace(/(^|[\s\-\/])([a-z])/g, (match, sep, letter) => sep + letter.toUpperCase());
    }

    function composeAddress(parts) {
        // Names only — no "Plot", "LGA" or "State" labels; the sheet reads as an
        // address, not a form.
        const lines = [];
        if (parts.plotNo) lines.push(String(parts.plotNo).toUpperCase());
        if (parts.district) lines.push(titleCase(parts.district));
        if (parts.lga) lines.push(titleCase(parts.lga));
        lines.push(SHEET_STATE);
        return lines.join(', ');
    }

    function parseAddress(address) {
        const parts = { plotNo: '', district: '', lga: '' };
        if (!address) return parts;

        // Tolerates the older labelled form ("Plot 5A, Affa, Gaya LGA, Kano State")
        // as well as the plain one written now.
        const tokens = String(address)
            .split(/[\r\n,]+/)
            .map(t => t.trim())
            .filter(Boolean)
            .map(t => t.replace(/^plot\s+/i, '').replace(/\s+(lga|local government)$/i, '').trim())
            .filter(t => t && !/^kano(\s+state)?$/i.test(t));

        if (!tokens.length) return parts;

        // Last name is always the LGA; what precedes it is the plot code (it has a
        // digit) and/or the district.
        parts.lga = tokens.pop();
        tokens.forEach((token) => {
            if (/\d/.test(token) && !parts.plotNo) {
                parts.plotNo = token;
            } else if (!parts.district) {
                parts.district = token;
            }
        });

        return parts;
    }

    /** Reference lookups, fetched once per page. */
    function fetchJson(url) {
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => (r.ok ? r.json() : null))
            .then(payload => ((payload && payload.data) || []))
            .catch(() => []);
    }

    /**
     * Turn a select into a searchable one, when the page carries select2. Inside a
     * SweetAlert popup the dropdown must be parented to the popup, or it renders
     * behind it. A page without select2 keeps the plain select.
     */
    function enhanceWithSelect2(select, placeholder) {
        const jq = window.jQuery;
        if (!jq || !jq.fn || !jq.fn.select2) return;

        try {
            jq(select).select2({
                placeholder: placeholder,
                width: '100%',
                dropdownParent: jq(Swal.getPopup())
            });
            // select2 fires its own event; keep the native listeners working.
            jq(select).on('select2:select select2:clear', () => {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        } catch (e) {
            /* plain select is a fine fallback */
        }
    }

    function fillSelect(select, items, selectedName, placeholder) {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.name;
            option.textContent = item.name;
            option.dataset.id = item.id;
            if (selectedName && item.name === selectedName) option.selected = true;
            select.appendChild(option);
        });

        // A stored value the list no longer offers still belongs on the sheet.
        if (selectedName && !select.value) {
            select.appendChild(new Option(selectedName, selectedName, true, true));
        }
    }

    const escapeAttr = (value) => String(value).replace(/"/g, '&quot;');
    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    /**
     * Populate and wire the SuA card.
     *
     * Allocation Source is not asked here — it was answered on the SuA File
     * Number Commissioning form and is shown back as plain text. What it decides
     * is which of the two lists the Institution / Agency and the Addressee are
     * picked from, and that is settled before this runs.
     *
     * Both remaining lists can still be answered with a typed name under
     * "Others (Specify)", which is remembered for next time.
     */
    function wireSuaAllocationCard(state) {
        const institutionSelect = document.getElementById('sua_cs_institution');
        const institutionOther = document.getElementById('sua_cs_institution_other');
        const addresseeSelect = document.getElementById('sua_cs_addressee');
        const addresseeOther = document.getElementById('sua_cs_addressee_other');
        if (!addresseeSelect) return;

        const toggleOther = (select, input) => {
            const isOther = select.value === OTHERS_SPECIFY;
            input.classList.toggle('hidden', !isOther);
            if (!isOther) input.value = '';
        };

        // On a file that already has an institution there is no select to read a
        // category from — the file's own is the answer.
        const currentCategory = () => (institutionSelect
            ? selectedInstitutionCategory(institutionSelect)
            : state.category);

        const fillAddressees = (lookups, selectedName) => {
            fillNameSelect(
                addresseeSelect,
                lookups[currentCategory() === 'OTHER' ? 'addressed_to_other' : 'addressed_to_government'],
                selectedName,
                'Select Addressee'
            );

            // A name stored before it was a lookup option comes back through the
            // sentinel — put the name itself in the box beside it.
            if (addresseeSelect.value === OTHERS_SPECIFY) addresseeOther.value = selectedName;
            toggleOther(addresseeSelect, addresseeOther);
        };

        loadAllocationLookups().then((lookups) => {
            if (institutionSelect) {
                fillInstitutionGroups(institutionSelect, lookups, state.institution);
                if (institutionSelect.value === OTHERS_SPECIFY) institutionOther.value = state.institution;
                toggleOther(institutionSelect, institutionOther);

                // Answering the institution decides which addressee names apply, so
                // the list beside it is replaced rather than left contradicting it.
                institutionSelect.addEventListener('change', () => {
                    toggleOther(institutionSelect, institutionOther);
                    fillAddressees(lookups, '');
                });
            }

            fillAddressees(lookups, state.addressedTo);
        });

        addresseeSelect.addEventListener('change', () => toggleOther(addresseeSelect, addresseeOther));
    }

    /**
     * Grey out every answer on the card, once they are all filled in.
     *
     * A sheet that has been issued already carries its answers on
     * conversion_applications: a reprint shows what was issued, it does not
     * re-ask. Leaving the controls in place (disabled) rather than swapping them
     * for text keeps preConfirm reading exactly the same fields — a disabled
     * input still reports its value and still matches :checked.
     */
    const FROZEN_CLASSES = ['bg-gray-100', 'text-gray-600', 'cursor-not-allowed'];

    /** Exactly what freezeAnsweredCard() disabled, so Edit can undo just that. */
    let frozenControls = [];

    /** Tell select2 its underlying select changed state. */
    function refreshSelect2(popup) {
        const jq = window.jQuery;
        if (!jq || !jq.fn || !jq.fn.select2) return;

        try {
            jq(popup).find('select').trigger('change.select2');
        } catch (e) {
            /* the native disabled attribute already holds */
        }
    }

    function freezeAnsweredCard() {
        const popup = Swal.getPopup();
        if (!popup) return;

        frozenControls = Array.prototype.slice.call(popup.querySelectorAll('input, select, textarea'));
        frozenControls.forEach((el) => {
            el.disabled = true;
            el.classList.add(...FROZEN_CLASSES);
        });

        refreshSelect2(popup);
    }

    /**
     * Hand the card back to the user after they confirmed they meant to edit.
     * Only the controls freezeAnsweredCard() touched are restored — the fields
     * that are read-only for their own reasons stay that way.
     */
    function unfreezeAnsweredCard() {
        frozenControls.forEach((el) => {
            el.disabled = false;
            el.classList.remove(...FROZEN_CLASSES);
        });
        frozenControls = [];

        const popup = Swal.getPopup();
        if (popup) refreshSelect2(popup);
    }

    /** The value a lookup select answers with: the name, or the typed one. */
    function chosenName(selectId, otherId) {
        const select = document.getElementById(selectId);
        if (!select) return '';

        return select.value === OTHERS_SPECIFY
            ? (document.getElementById(otherId).value || '').trim()
            : select.value;
    }

    /**
     * @param {string} fileNo     shown in the prompt
     * @param {object} [saved]    { method, other, allocationSource, allocationEntity }
     *                            to pre-select, when re-answering
     * @param {object} [options]  { variant } — 'sua' asks the Institution /
     *                            Addressee questions instead of the binary
     *                            State/Local Government one. Anything else keeps
     *                            the LGA/Conversion card exactly as it was.
     */
    function promptAcquisitionMethod(fileNo, saved, options) {
        const isSua = Boolean(options && options.variant === 'sua');
        const savedCategory = (saved && saved.institutionCategory === 'OTHER') ? 'OTHER' : 'GOVERNMENT';
        const savedInstitution = (saved && saved.institutionName) ? String(saved.institutionName) : '';
        const savedAddressedTo = (saved && saved.addressedTo) ? String(saved.addressedTo) : '';
        const savedMethod = (saved && saved.method) ? String(saved.method) : 'a';
        const savedOther = (saved && saved.other) ? String(saved.other) : '';
        const checkedAttr = (letter) => (savedMethod === letter ? ' checked' : '');

        // Allocation Source is confirmed beside the acquisition method because it
        // decides who the sheet is addressed to — a Local Government source its
        // Chairman, a State Government source the allocating entity.
        const savedSource = (saved && saved.allocationSource) ? String(saved.allocationSource) : 'Local Government';
        const savedEntity = (saved && saved.allocationEntity) ? String(saved.allocationEntity) : '';
        const isState = savedSource === 'State Government';
        const stateEntity = isState && STATE_ENTITIES.indexOf(savedEntity) !== -1 ? savedEntity : '';
        const stateOther = isState && savedEntity && !stateEntity ? savedEntity : '';
        const lgaEntity = isState ? '' : savedEntity;
        const savedAddress = (saved && saved.allocationAddress) ? String(saved.allocationAddress) : '';

        // Answered on the SuA File Number Commissioning form. It is shown back
        // here, never re-asked: it is the file's own Allocation Source, and it is
        // what decides which Institution / Agency and Addressee lists are offered.
        // A council allocation reads as the council, which is who the letter goes to.
        const suaAllocationSource = ((savedInstitution.toUpperCase() === LOCAL_GOVERNMENT && savedEntity)
            ? `${savedEntity.replace(/\s+$/, '')} LOCAL GOVERNMENT`
            : savedInstitution).toUpperCase();

        // A file commissioned before Allocation Source named an institution has
        // nothing to back-fill, and is the only case where the card asks for one.
        const suaInstitutionKnown = Boolean(savedInstitution);

        // The acquisition method is null until this sheet has been answered once,
        // so it is what tells a reprint from a first print.
        const suaReprint = isSua && Boolean(saved && saved.method);
        const addressParts = parseAddress(savedAddress);
        const sourceLocked = Boolean(saved && saved.allocationLocked);

        return Swal.fire({
            title: 'Confirmation Sheet',
            width: 900,
            html: `
                <div class="flex items-start justify-between gap-3 px-2 mb-3">
                    <p id="cs_intro" class="text-sm text-gray-600 text-left">
                        ${suaReprint
                            ? `The answers <strong>${fileNo}</strong> was issued with. Choose Edit to change them.`
                            : `Confirm both answers for <strong>${fileNo}</strong> before the Confirmation Sheet is generated.`}
                    </p>
                    ${suaReprint ? `
                    <button type="button" id="cs_edit_btn"
                            class="shrink-0 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 transition-all">
                        Edit
                    </button>
                    ` : ''}
                </div>
                ${suaReprint ? `
                <div id="cs_edit_confirm" class="hidden mx-2 mb-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded-md text-left">
                    <p class="text-xs text-amber-900 mb-2">
                        Edit the answers this sheet was already issued with?
                    </p>
                    <div class="flex gap-2">
                        <button type="button" id="cs_edit_yes"
                                class="px-3 py-1 text-xs font-medium text-white bg-amber-600 rounded-md hover:bg-amber-700 transition-all">
                            Yes
                        </button>
                        <button type="button" id="cs_edit_no"
                                class="px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-all">
                            No
                        </button>
                    </div>
                </div>
                ` : ''}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-2 text-left">

                <div class="border border-gray-200 rounded-lg p-3">
                    <h4 class="text-sm font-semibold text-gray-900 mb-1">Allocation Source</h4>
                    <p class="text-xs text-gray-500 mb-3">Decides who the sheet is addressed to.</p>
                    ${isSua ? `
                        <div class="space-y-3 mb-3">
                            <p class="text-sm font-bold text-gray-900">
                                ${suaAllocationSource ? escapeHtml(suaAllocationSource) : 'Not captured at commissioning'}
                            </p>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Institution / Agency</label>
                                ${suaInstitutionKnown ? `
                                <p class="text-sm text-gray-800">${escapeHtml(savedInstitution)}</p>
                                ` : `
                                <select id="sua_cs_institution"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                    <option value="">Loading…</option>
                                </select>
                                <input type="text" id="sua_cs_institution_other" value=""
                                       class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all hidden"
                                       placeholder="Specify the institution...">
                                <p class="text-xs text-gray-500 mt-1">
                                    This file was commissioned before Allocation Source named an institution — answer it once here.
                                </p>
                                `}
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Addressee</label>
                                <select id="sua_cs_addressee"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                    <option value="">Loading…</option>
                                </select>
                                <input type="text" id="sua_cs_addressee_other" value=""
                                       class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all hidden"
                                       placeholder="Specify the addressee...">
                            </div>

                        </div>
                    ` : sourceLocked ? `
                        <div class="bg-gray-50 border border-gray-200 rounded-md p-3 mb-3">
                            <div class="text-sm font-semibold text-gray-800">${savedSource}</div>
                            <div class="text-sm text-gray-600">${savedEntity || '—'}</div>
                            <p class="text-xs text-gray-500 mt-2">Set when the file was commissioned — not editable here.</p>
                        </div>
                        <input type="hidden" id="locked_allocation_source" value="${escapeAttr(savedSource)}">
                        <input type="hidden" id="locked_allocation_entity" value="${escapeAttr(savedEntity)}">
                    ` : `
                        <div class="space-y-2 mb-3">
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                                <input type="radio" name="allocation_source" value="Local Government" class="w-4 h-4 text-blue-600"${isState ? '' : ' checked'}>
                                <span class="text-sm font-medium text-gray-700">Local Government (LGA)</span>
                            </label>
                            <div id="lga_entity_container" class="${isState ? 'hidden' : ''} pl-2">
                                <input type="text" id="lga_entity_input" value="${escapeAttr(lgaEntity)}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                       placeholder="Local Government">
                            </div>

                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                                <input type="radio" name="allocation_source" value="State Government" class="w-4 h-4 text-blue-600"${isState ? ' checked' : ''}>
                                <span class="text-sm font-medium text-gray-700">State Government</span>
                            </label>
                            <div id="state_entity_container" class="${isState ? '' : 'hidden'} pl-2 space-y-2">
                                <select id="state_entity_select"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                    <option value="">Select Entity</option>
                                    ${STATE_ENTITIES.map(name => `<option value="${name}"${stateEntity === name ? ' selected' : ''}>${name}</option>`).join('')}
                                    <option value="Other"${stateOther ? ' selected' : ''}>Other</option>
                                </select>
                                <input type="text" id="state_entity_other_input" value="${escapeAttr(stateOther)}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all ${stateOther ? '' : 'hidden'}"
                                       placeholder="Specify the entity...">
                            </div>
                        </div>
                    `}

                    <div id="state_address_container" class="${(isSua || isState) ? '' : 'hidden'} border-t border-gray-200 pt-3 space-y-2">
                        <p class="text-xs font-medium text-gray-700">Address printed on the sheet</p>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" id="address_plot_no" value="${escapeAttr(addressParts.plotNo)}"
                                   class="w-full px-2 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                   placeholder="Plot No">
                            <select id="address_district"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                <option value="">Loading districts…</option>
                            </select>
                            <select id="address_lga"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                <option value="">Select LGA</option>
                            </select>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                            <div class="text-xs text-gray-500">Full address</div>
                            <div id="address_preview" class="text-sm text-gray-800"></div>
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-lg p-3">
                    <h4 class="text-sm font-semibold text-gray-900 mb-1">Property Acquisition Method</h4>
                    <p class="text-xs text-gray-500 mb-3">How the property was acquired.</p>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="a" class="w-4 h-4 text-blue-600"${checkedAttr('a')}>
                            <span class="text-sm font-medium text-gray-700">a. By Purchase</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="b" class="w-4 h-4 text-blue-600"${checkedAttr('b')}>
                            <span class="text-sm font-medium text-gray-700">b. By Inheritance</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="c" class="w-4 h-4 text-blue-600"${checkedAttr('c')}>
                            <span class="text-sm font-medium text-gray-700">c. By Gift</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="d" class="w-4 h-4 text-blue-600"${checkedAttr('d')}>
                            <span class="text-sm font-medium text-gray-700">d. Direct Local Government Allocation</span>
                        </label>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-md cursor-pointer border border-transparent hover:border-blue-200 transition-all">
                            <input type="radio" name="acquisition_method" value="e" class="w-4 h-4 text-blue-600" id="method_other_radio"${checkedAttr('e')}>
                            <span class="text-sm font-medium text-gray-700">e. Any other (Specify)</span>
                        </label>
                    </div>
                    <div id="other_specify_container" class="mt-3 ${savedMethod === 'e' ? '' : 'hidden'} animate-fadeIn">
                        <input type="text" id="other_specify_input" value="${escapeAttr(savedOther)}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                               placeholder="Please specify other method...">
                    </div>
                </div>

                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generate Document',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const addressContainer = document.getElementById('state_address_container');

                if (isSua) {
                    wireSuaAllocationCard({
                        category: savedCategory,
                        institution: savedInstitution,
                        addressedTo: savedAddressedTo
                    });
                }

                if (!isSua && !sourceLocked) {
                    const sourceRadios = document.querySelectorAll('input[name="allocation_source"]');
                    const lgaContainer = document.getElementById('lga_entity_container');
                    const stateContainer = document.getElementById('state_entity_container');
                    const stateSelect = document.getElementById('state_entity_select');
                    const stateOtherInput = document.getElementById('state_entity_other_input');

                    sourceRadios.forEach(radio => {
                        radio.addEventListener('change', (e) => {
                            const state = e.target.value === 'State Government';
                            stateContainer.classList.toggle('hidden', !state);
                            lgaContainer.classList.toggle('hidden', state);
                            // Only a state entity needs an address built for it.
                            addressContainer.classList.toggle('hidden', !state);
                        });
                    });

                    stateSelect.addEventListener('change', (e) => {
                        stateOtherInput.classList.toggle('hidden', e.target.value !== 'Other');
                        if (e.target.value === 'Other') stateOtherInput.focus();
                    });
                }

                // Address parts. The districts table carries no LGA column, so the two
                // lists are independent — a district cannot be narrowed by the LGA here.
                const lgaSelect = document.getElementById('address_lga');
                const districtSelect = document.getElementById('address_district');

                const preview = document.getElementById('address_preview');
                const plotInput = document.getElementById('address_plot_no');

                const refreshPreview = () => {
                    preview.textContent = composeAddress({
                        plotNo: plotInput.value.trim(),
                        district: districtSelect.value.trim(),
                        lga: lgaSelect.value.trim()
                    });
                };

                const lgasReady = fetchJson('/api/reference/lgas').then((lgas) => {
                    fillSelect(lgaSelect, lgas, addressParts.lga, 'Select LGA');
                    refreshPreview();
                });

                const districtsReady = fetchJson('/api/reference/districts').then((districts) => {
                    fillSelect(districtSelect, districts, addressParts.district, 'Select District');
                    // 1,800-odd districts need a search box, not a scroll.
                    enhanceWithSelect2(districtSelect, 'Select District');
                    refreshPreview();
                });

                // Only once every list has landed — greying a select before it is
                // filled would freeze it on "Loading…".
                if (suaReprint) {
                    Promise.all([loadAllocationLookups(), lgasReady, districtsReady])
                        .then(freezeAnsweredCard);

                    // Editing an issued sheet is deliberate, so Edit asks before it
                    // unlocks. The buttons are never frozen: freezeAnsweredCard()
                    // only disables inputs.
                    const editButton = document.getElementById('cs_edit_btn');
                    const editConfirm = document.getElementById('cs_edit_confirm');
                    const intro = document.getElementById('cs_intro');

                    editButton.addEventListener('click', () => {
                        editConfirm.classList.remove('hidden');
                        editButton.disabled = true;
                        editButton.classList.add('opacity-50', 'cursor-not-allowed');
                    });

                    document.getElementById('cs_edit_no').addEventListener('click', () => {
                        editConfirm.classList.add('hidden');
                        editButton.disabled = false;
                        editButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    });

                    document.getElementById('cs_edit_yes').addEventListener('click', () => {
                        unfreezeAnsweredCard();
                        editConfirm.classList.add('hidden');
                        editButton.classList.add('hidden');
                        intro.innerHTML = `Editing the answers for <strong>${fileNo}</strong>. `
                            + 'Generate Document saves what you change here.';
                    });
                }

                plotInput.addEventListener('input', refreshPreview);
                [lgaSelect, districtSelect].forEach(el => el.addEventListener('change', refreshPreview));
                refreshPreview();

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

                if (isSua) {
                    // Back-filled from the file, unless it predates the institution
                    // lists — then the card asked, and the answer carries its own list.
                    const institutionSelect = document.getElementById('sua_cs_institution');
                    const category = suaInstitutionKnown
                        ? savedCategory
                        : selectedInstitutionCategory(institutionSelect);
                    const institution = suaInstitutionKnown
                        ? savedInstitution
                        : chosenName('sua_cs_institution', 'sua_cs_institution_other');
                    const addressee = chosenName('sua_cs_addressee', 'sua_cs_addressee_other');

                    if (!institution) {
                        Swal.showValidationMessage('Please choose (or specify) the Institution / Agency');
                        return false;
                    }

                    if (!addressee) {
                        Swal.showValidationMessage('Please choose (or specify) the Addressee');
                        return false;
                    }

                    const suaLga = document.getElementById('address_lga').value.trim();
                    if (!suaLga) {
                        Swal.showValidationMessage('Please pick the LGA for the address the sheet is sent to');
                        return false;
                    }

                    // The council named at commissioning still belongs to a LOCAL
                    // GOVERNMENT allocation; the address LGA is only a fallback.
                    const legacy = legacyAllocationFor(institution, savedEntity || suaLga);

                    return {
                        method: selectedMethod,
                        other: otherValue,
                        allocationSource: legacy.source,
                        allocationEntity: legacy.entity,
                        allocationAddress: composeAddress({
                            plotNo: document.getElementById('address_plot_no').value.trim(),
                            district: document.getElementById('address_district').value.trim(),
                            lga: suaLga
                        }),
                        institutionCategory: category,
                        institutionName: institution,
                        addressedTo: addressee
                    };
                }

                const selectedSource = sourceLocked
                    ? document.getElementById('locked_allocation_source').value
                    : document.querySelector('input[name="allocation_source"]:checked').value;

                let entity;
                let address = '';
                if (selectedSource === 'State Government') {
                    if (sourceLocked) {
                        entity = document.getElementById('locked_allocation_entity').value.trim();
                    } else {
                        const picked = document.getElementById('state_entity_select').value;
                        entity = picked === 'Other'
                            ? document.getElementById('state_entity_other_input').value.trim()
                            : picked;
                    }

                    if (!entity) {
                        Swal.showValidationMessage('Please choose (or specify) the State Government entity');
                        return false;
                    }

                    const lgaValue = document.getElementById('address_lga').value.trim();
                    const districtValue = document.getElementById('address_district').value.trim();

                    if (!lgaValue) {
                        Swal.showValidationMessage('Please pick the LGA for the address the sheet is sent to');
                        return false;
                    }

                    address = composeAddress({
                        plotNo: document.getElementById('address_plot_no').value.trim(),
                        district: districtValue,
                        lga: lgaValue
                    });
                } else {
                    entity = sourceLocked
                        ? document.getElementById('locked_allocation_entity').value.trim()
                        : document.getElementById('lga_entity_input').value.trim();

                    if (!entity) {
                        Swal.showValidationMessage('Please enter the Local Government the sheet is addressed to');
                        return false;
                    }
                }

                return {
                    method: selectedMethod,
                    other: otherValue,
                    allocationSource: selectedSource,
                    allocationEntity: entity,
                    allocationAddress: address
                };
            }
        });
    }

    function generateConversionApplication(id, fileNo, options) {
        const convKey = (fileNo && fileNo !== '--' && fileNo !== 'N/A')
            ? encodeURIComponent(fileNo)
            : id;

        // Ask with whatever this sheet already knows filled in — the stored answers
        // on a reprint, otherwise the file's own allocation info or its LGA.
        return fetchSavedAcquisitionMethod(convKey)
            .then((saved) => promptAcquisitionMethod(fileNo, saved, options))
            .then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const {
                    method, other, allocationSource, allocationEntity, allocationAddress,
                    institutionCategory, institutionName, addressedTo
                } = result.value;

                let url = `/file-numbers/conversion-application/${convKey}?method=${method}`;
                if (method === 'e' && other) {
                    url += `&other=${encodeURIComponent(other)}`;
                }
                if (allocationSource) {
                    url += `&allocation_source=${encodeURIComponent(allocationSource)}`;
                }
                if (allocationEntity) {
                    url += `&allocation_entity=${encodeURIComponent(allocationEntity)}`;
                }
                if (allocationAddress) {
                    url += `&allocation_address=${encodeURIComponent(allocationAddress)}`;
                }
                // SuA only — the LGA/Conversion card never returns these.
                if (institutionCategory) {
                    url += `&institution_category=${encodeURIComponent(institutionCategory)}`;
                }
                if (institutionName) {
                    url += `&institution_name=${encodeURIComponent(institutionName)}`;
                }
                if (addressedTo) {
                    url += `&addressed_to=${encodeURIComponent(addressedTo)}`;
                }

                window.open(url, '_blank');
            });
    }

    window.promptConversionAcquisitionMethod = promptAcquisitionMethod;
    window.generateConversionApplication = generateConversionApplication;
})(window);

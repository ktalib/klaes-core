{{-- Duplex Parcel Update — wizard, register actions and the stage runner. --}}
<script>
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /** The site-plan endpoint for the duplex currently open. Same URL for POST and DELETE. */
    const SITE_PLAN_URL = () => '{{ url('duplex-parcel-update') }}/' + state.duplex.id + '/site-plan';
    const TYPES = @json($types);

    // The same ceiling the controller validates against, for every update type.
    const MAX_PLOTS = {{ \App\Models\DuplexParcelUpdate::MAX_PLOTS }};
    const maxPlotsFor = () => MAX_PLOTS;

    const LGAS = @json(collect($lgas)->pluck('name')->values());
    const DISTRICTS = @json(collect($districts)->pluck('name')->values());

    // Icon + one-line description per update type. Lives here because the list is
    // rendered from the plan now rather than pre-rendered in the blade.
    const TYPE_META = {
        merger:            ['combine', 'Several files become one parcel'],
        subdivision:       ['split-square-horizontal', 'One parcel becomes several plots'],
        change_of_purpose: ['repeat', 'Same parcel, new land use'],
        extension:         ['expand', 'Boundary adjusted, one for one'],
        separation:        ['scissors', 'Plots separated out of one file'],
    };


    // ---------------------------------------------------------------- state
    // `plan` is the ordered list the officer built by ticking. Its order IS the
    // execution order — never re-sort it by type anywhere downstream.
    const state = {
        step: 1,
        sources: [],       // {fileNumber, title}
        plan: [],          // [{type, rank, count}] in tick order
        duplex: null,      // {id, duplex_id} once created
        stages: [],        // server stage rows
        stageIndex: 0,
        carry: [],         // holding numbers produced by the stage just saved
        // One record per SOURCE FILE: its title, its applicant, its location. Files on
        // one duplex are not required to share any of them.
        entries: [],       // [{file_title, applicant, plot_no, district, lga}]
        entryIndex: 0,
        // The recommended site plan attached on step 4: {path, url, name} or null.
        sitePlan: null,
    };

    /** The fields one entry carries, and the element each is edited in. */
    const ENTRY_FIELDS = {
        file_title: 'dx-file-title',
        plot_no:    'dx-plot-no-main',
        district:   'dx-district',
        lga:        'dx-lga',
    };

    const blankEntry = () => ({ file_title: '', plot_no: '', district: '', lga: '' });

    // ---------------------------------------------------------------- helpers
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(body || {}),
        }).then(r => r.json());
    }

    function toast(icon, title, text) {
        if (window.Swal) return Swal.fire({ icon, title, text, timer: icon === 'success' ? 2200 : undefined });
        alert(title + (text ? '\n' + text : ''));
    }

    function icons() { if (window.lucide) window.lucide.createIcons(); }

    // ---------------------------------------------------------------- step 1
    window.openDuplexWizard = function () {
        state.step = 1;
        state.sources = [];
        state.plan = [];
        state.duplex = null;
        state.stages = [];
        state.stageIndex = 0;
        state.carry = [];
        state.mergerDismissed = false;

        state.entries = [];
        state.entryIndex = 0;
        state.sitePlan = null;

        document.getElementById('dx-applicant').value = '';
        document.getElementById('dx-file-title').value = '';
        document.getElementById('dx-plot-no-main').value = '';
        document.getElementById('dx-district').value = '';
        document.getElementById('dx-lga').value = '';
        updateAddressPreview();
        document.getElementById('dx-duplex-badge').classList.add('hidden');

        renderRanks();
        renderSources();
        renderEntry();
        updateAddressPreview();

        const modal = document.getElementById('duplex-wizard');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        showStep(1);
        icons();
    };

    function hideDuplexWizard() {
        const modal = document.getElementById('duplex-wizard');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    /**
     * Closing discards whatever has not been saved, so it asks first — but only when
     * there is something to lose. `force` is for the paths that close it deliberately,
     * such as after a successful submit.
     */
    window.closeDuplexWizard = function (force) {
        if (force === true) return hideDuplexWizard();

        const applicant = (document.getElementById('dx-applicant')?.value || '').trim();
        const started = state.sources.length > 0 || state.plan.length > 0 || applicant !== '';

        if (!started || !window.Swal) return hideDuplexWizard();

        Swal.fire({
            icon: 'warning',
            title: 'Close without saving?',
            text: state.duplex
                ? 'The duplex has been created, but anything captured on this screen and not yet saved will be lost.'
                : 'Nothing has been saved yet. The files, the plan and any sizes entered will be lost.',
            showCancelButton: true,
            confirmButtonText: 'Close and discard',
            cancelButtonText: 'Keep working',
            reverseButtons: true,
            allowOutsideClick: false,
            confirmButtonColor: '#dc2626',
        }).then(r => { if (r.isConfirmed) hideDuplexWizard(); });
    };

    window.pickSourceFile = function () {
        // The plan is fixed once the duplex row exists; adding a file here would not
        // reach the server.
        if (state.duplex) {
            return toast('info', 'Plan locked', 'The duplex has been created, so its source files cannot change.');
        }
        if (!window.GlobalFileNoModal) return toast('error', 'File selector unavailable');

        window.GlobalFileNoModal.open({
            callback: function (picked) {
                const fileNumber = (picked.fileNumber || '').trim();
                if (!fileNumber) return;
                if (state.sources.some(s => s.fileNumber === fileNumber)) {
                    return toast('info', 'Already added', fileNumber + ' is already on this duplex.');
                }
                state.sources.push({ fileNumber, title: picked.file_title || '' });

                syncEntriesToSources();

                // The register's own title for THIS file, on THIS file's card. The field
                // is read-only: it states what the register holds rather than inviting a
                // second answer to the same question.
                const mine = state.entries[state.sources.length - 1];
                if (mine && !mine.file_title.trim() && picked.file_title) {
                    mine.file_title = picked.file_title;
                }

                // The applicant is usually the holder, so it STARTS from the title rather
                // than from nothing — it was being typed out by hand on every duplex. Only
                // when the box is still empty, so a name already entered is never
                // overwritten, and it stays editable for the cases where the applicant is
                // someone else.
                const applicantEl = document.getElementById('dx-applicant');
                if (applicantEl && !applicantEl.value.trim() && picked.file_title) {
                    applicantEl.value = picked.file_title;
                }

                // Land on the file just added — it is the one that needs filling in.
                state.entryIndex = state.sources.length - 1;

                fillLocationFromFile(picked.record);

                renderSources();
                syncMergerFromSources();
            }
        });
    };

    /**
     * District and LGA, taken from the file the officer just picked.
     *
     * The parcel's location is already on its registry record, so asking for it again
     * is a chance to disagree with the register. Only BLANK fields are filled: the
     * first file answers, later ones do not overrule it, and anything typed by hand
     * stands — the files on one duplex are neighbours, not necessarily identical.
     *
     * The lookup API and the smart selector name these differently (LGA / lga / fi_lga
     * / ma_lga), hence the list rather than one key.
     */
    function fillLocationFromFile(record) {
        if (!record) return;

        const pick = keys => {
            for (const k of keys) {
                const v = String(record[k] ?? '').trim();
                if (v && v !== '—') return v;
            }
            return '';
        };

        const district = pick(['district', 'District', 'fi_district', 'ma_district', 'layout_district']);
        const lga      = pick(['lga', 'LGA', 'fi_lga', 'ma_lga', 'lga_name']);
        const where    = pick(['location', 'Location', 'ma_location', 'address']);

        // Plenty of older records carry no district or LGA column at all — only a
        // location line such as "10, BARGERY ROAD, NASARAWA". Reading the name out of
        // it is a guess, so it is only allowed when exactly ONE district (or LGA) in
        // the list appears there; two candidates means the officer decides.
        const entry = state.entries[state.entries.length - 1];
        if (!entry) return;

        if (!entry.district.trim()) entry.district = district || soleMatchIn('dx-district', where);
        if (!entry.lga.trim())      entry.lga      = lga || soleMatchIn('dx-lga', where);

        renderEntry();
    }

    /** The one option named in a free-text location line, or nothing. */
    function soleMatchIn(selectId, text) {
        const el = document.getElementById(selectId);
        if (!el || !text) return '';

        const haystack = ' ' + text.toUpperCase().replace(/[^A-Z0-9]+/g, ' ') + ' ';

        const hits = [...el.options]
            .map(o => o.value.trim())
            .filter(v => v.length >= 4
                && haystack.includes(' ' + v.toUpperCase().replace(/[^A-Z0-9]+/g, ' ') + ' '));

        return hits.length === 1 ? hits[0] : '';
    }

    /**
     * Set a field only if it is empty. Both of these are <select>s, so the value has to
     * match an option — a district spelled differently in the register would otherwise
     * blank the box instead of leaving what was there.
     */
    function setIfBlank(id, value) {
        const el = document.getElementById(id);
        if (!el || !value || String(el.value || '').trim()) return;

        if (el.tagName === 'SELECT') {
            const want = value.toLowerCase();
            const hit = [...el.options].find(o =>
                o.value.trim().toLowerCase() === want || o.text.trim().toLowerCase() === want);
            if (!hit) return;
            el.value = hit.value;
        } else {
            el.value = value;
        }
    }

    /**
     * Read the visible inputs back into the entry being edited.
     *
     * Called on every keystroke so a value is never lost to a click on the arrows —
     * the alternative, reading only when stepping, drops whatever was typed if the
     * officer moves on by any other route.
     */
    window.dxEntryTouched = function () {
        const entry = state.entries[state.entryIndex];
        if (!entry) return;

        Object.keys(ENTRY_FIELDS).forEach(field => {
            const el = document.getElementById(ENTRY_FIELDS[field]);
            if (el) entry[field] = el.value;
        });
    };

    /** Paint the entry at state.entryIndex into the shared inputs. */
    function renderEntry() {
        const card  = document.getElementById('dx-entry-card');
        const total = state.entries.length;

        card.classList.toggle('hidden', total === 0);
        if (!total) return;

        if (state.entryIndex >= total) state.entryIndex = total - 1;
        if (state.entryIndex < 0) state.entryIndex = 0;

        const entry  = state.entries[state.entryIndex];
        const source = state.sources[state.entryIndex];

        Object.keys(ENTRY_FIELDS).forEach(field => {
            const el = document.getElementById(ENTRY_FIELDS[field]);
            if (!el) return;

            // A select can only hold a value it has an option for; anything else would
            // silently blank the box.
            if (el.tagName === 'SELECT' && entry[field]
                && ![...el.options].some(o => o.value === entry[field])) {
                el.value = '';
                return;
            }

            el.value = entry[field] || '';
        });

        document.getElementById('dx-entry-file').textContent =
            source ? source.fileNumber : '—';
        document.getElementById('dx-entry-count').textContent =
            (state.entryIndex + 1) + ' of ' + total;

        updateAddressPreview();
        icons();
    }

    /** Move between files. Wraps, so N+1 is 1 — the list is a ring, not a dead end. */
    window.dxEntryStep = function (by) {
        const total = state.entries.length;
        if (total < 2) return;

        dxEntryTouched();
        state.entryIndex = (state.entryIndex + by + total) % total;
        renderEntry();
    };

    /**
     * Keep one entry per source file, in the same order.
     *
     * Entries are matched to files by NUMBER, not position, so removing the first file
     * does not shift everyone else's details up onto the wrong file.
     */
    function syncEntriesToSources() {
        const byFile = {};
        state.sources.forEach((src, i) => {
            byFile[src.fileNumber] = state.entries[i] || blankEntry();
        });

        state.entries = state.sources.map(src => byFile[src.fileNumber] || blankEntry());

        if (state.entryIndex >= state.entries.length) {
            state.entryIndex = Math.max(0, state.entries.length - 1);
        }

        renderEntry();
    }

    /** The values of one field across every file, blanks dropped. */
    const entryValues = field => state.entries
        .map(e => String(e[field] || '').trim())
        .filter(Boolean);

    /** Kano is the only state this registry serves, so it is a constant, not a field. */
    const STATE_NAME = 'KANO';

    /**
     * One location, assembled the same way every time: district, LGA, state.
     *
     * The plot number is NOT part of it. A subdivision gives each child its own plot
     * number, so folding the parcel's number into the location would stamp the mother's
     * plot onto every child.
     */
    window.updateAddressPreview = function () {
        const parts = [
            document.getElementById('dx-district').value.trim(),
            document.getElementById('dx-lga').value.trim(),
            STATE_NAME,
        ];

        const location = parts.filter(Boolean).join(', ').toUpperCase();
        document.getElementById('dx-address').value = location;

        const box = document.getElementById('dx-address-preview');
        box.innerHTML = (parts[0] || parts[1])
            ? location
            : '<span class="text-slate-400 font-normal italic">Fills in from the fields above.</span>';
    };

    function renderSources() {
        const box = document.getElementById('dx-sources');

        // Empty state is itself the picker button — an empty dashed box with no
        // affordance was the least useful thing on the card.
        if (!state.sources.length) {
            box.innerHTML = `
                <button type="button" onclick="pickSourceFile()"
                        class="w-full flex flex-col items-center justify-center gap-1.5 py-2 text-slate-400 hover:text-blue-600 transition">
                    <i data-lucide="file-search" class="w-5 h-5"></i>
                    <span class="text-xs font-semibold">Choose a file from the registry</span>
                </button>`;
            if (window.lucide) lucide.createIcons();
            return;
        }

        box.innerHTML = state.sources.map((s, i) => `
            <span class="inline-flex items-center gap-2.5 pl-3 pr-2 py-2 rounded-xl bg-white border border-slate-200 shadow-sm">
                <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center text-[10px] font-black">${i + 1}</span>
                <span class="min-w-0">
                    <span class="block holding-no text-xs font-black text-slate-700 leading-tight">${s.fileNumber}</span>
                    ${s.title ? `<span class="block text-[10px] text-slate-400 truncate max-w-[180px]">${s.title}</span>` : ''}
                </span>
                <button type="button" onclick="removeSource(${i})" title="Remove"
                        class="w-6 h-6 rounded-lg flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </span>`).join('');

        if (window.lucide) lucide.createIcons();
    }

    window.removeSource = function (i) {
        if (state.duplex) return;
        state.sources.splice(i, 1);
        state.entries.splice(i, 1);
        renderSources();
        syncEntriesToSources();
        syncMergerFromSources();
    };

    /**
     * More than one source file can only mean one thing: they are being merged. So the
     * Merger is added automatically as the first leg, locked, and its quantity set to
     * the number of files picked.
     *
     * Nothing else can sensibly consume several files at once — a Subdivision or a
     * Change of Purpose starts from one parcel — so leaving the officer to remember it
     * only invites a duplex that cannot run.
     */
    function syncMergerFromSources() {
        if (state.duplex) return;               // the plan is fixed once it exists

        const n = state.sources.length;
        const at = state.plan.findIndex(p => p.type === 'merger');

        if (n > 1) {
            if (autoMergerDismissed()) {
                // The officer took it out deliberately. Keep it out, but keep the
                // count on it if they add a Merger back by hand later.
                if (at !== -1) state.plan[at].count = n;
                renderRanks();
                return;
            }
            if (at === -1) {
                state.plan.unshift({ type: 'merger', rank: 0, count: n, auto: true });
            } else {
                const entry = state.plan.splice(at, 1)[0];
                entry.count = n;
                entry.auto = true;
                state.plan.unshift(entry);      // a merger is always the first leg
            }
        } else if (at !== -1 && state.plan[at].auto) {
            // Down to a single file: the merger it added is no longer meaningful.
            state.plan.splice(at, 1);
        }

        renderRanks();
    }

    /**
     * The auto-added Merger is a suggestion, not a rule.
     *
     * Several files usually do have to be merged first — but not always. An applicant
     * may need the purposes brought into line BEFORE the merger, because parcels of
     * different land uses cannot sensibly be merged, and then the first leg is a
     * Change of Purpose over all the files. Locking the merger made that plan
     * impossible to express.
     *
     * Once dismissed it stays dismissed for this duplex, so it does not reappear the
     * next time the source list changes.
     */
    function autoMergerDismissed() { return state.mergerDismissed === true; }

    /**
     * Selection order = execution order. Each pick from the dropdown APPENDS to the
     * plan; removing one shuffles the rest up, so the ranks always read 1..N.
     */
    window.addTypeFromPicker = function (select) {
        const type = select.value;
        select.value = '';
        if (!type) return;

        // Change of Purpose is the one update that may run more than once. A parcel can
        // need its purpose changed BEFORE a merger (to bring several files into line) and
        // again AFTER a subdivision (when only some of the new plots take a different
        // use), and those are two different instructions on two different sets of files.
        // Every other type still collapses to one occurrence.
        if (type !== 'change_of_purpose' && state.plan.some(p => p.type === type)) return;

        state.plan.push({ type, rank: 0, count: null });
        renderRanks();

        if (type !== 'change_of_purpose') return;

        // Whether the entry JUST ADDED is the first leg — not whether the plan happens
        // to start with a Change of Purpose. Those are different questions once a
        // second one exists, and confusing them re-ran the first leg's prompt and
        // cleared the table the officer had already filled in.
        const isFirstLeg = state.plan.length === 1;

        // A first-leg Change of Purpose over SEVERAL source files has to say which of
        // them it covers, and what each one becomes — the answer decides how many
        // holding numbers it mints, and the officer is looking at those files now.
        if (isFirstLeg && state.sources.length > 1) {
            return askChangeOfPurposeScope();
        }

        // A LATER one works on plots that do not exist yet — they are the subdivision's,
        // and even the subdivision's quantity has not been entered at this point. So it
        // cannot list files; it asks how many take a new purpose, and which purpose.
        if (!isFirstLeg) {
            askLaterChangeOfPurposeCount();
        }
    };

    /** Does anything before this stage produce more than one parcel? */
    function splitsBefore(index) {
        return state.plan.slice(0, index)
            .some(p => p.type === 'subdivision' || p.type === 'separation');
    }

    /**
     * Everything a LATER Change of Purpose needs, asked in the order the answers
     * actually become knowable.
     *
     *   1. How many plots does the split before it produce?   (39)
     *   2. All of them, or only some?
     *   3. How many, and what do they become?                 (3 -> COM)
     *
     * The count cannot be asked first: "3 of them" means nothing until there are 39 to
     * take 3 from, and at step 1 the subdivision has not been sized either. Card 1
     * therefore sizes the SPLIT, writing to that stage's own plan entry — which is the
     * same property the Quantities step reads, so the figure appears there too.
     */
    function askLaterChangeOfPurposeCount() {
        const index = state.plan.length - 1;
        const entry = state.plan[index];
        if (!entry) return;

        // The split this Change of Purpose draws its plots from.
        let splitIndex = -1;
        for (let i = index - 1; i >= 0; i--) {
            if (state.plan[i].type === 'subdivision' || state.plan[i].type === 'separation') {
                splitIndex = i;
                break;
            }
        }

        // Nothing upstream splits the parcel, so exactly one file reaches this stage.
        if (splitIndex === -1) {
            entry.count = 1;
            return askLaterCopPurpose(index, 1, true);
        }

        if (!window.Swal) {
            entry.count = entry.count || 1;
            return renderRanks();
        }

        const split = state.plan[splitIndex];

        // ---- card 1: size the split ----
        Swal.fire({
            icon: 'question',
            title: TYPES[split.type],
            html: 'Before this Change of Purpose can say how many plots it touches, the '
                + '<b>' + TYPES[split.type] + '</b> that feeds it has to be sized.'
                + '<br><br>How many plots will it produce?',
            input: 'number',
            inputValue: split.count || '',
            inputAttributes: { min: 1, max: maxPlotsFor(split.type), step: 1 },
            showCancelButton: true,
            confirmButtonText: 'Set',
            cancelButtonText: 'Remove this update',
            reverseButtons: true,
            allowOutsideClick: false,
            confirmButtonColor: '#2563eb',
            inputValidator: v => {
                const n = parseInt(v, 10);
                if (!n || n < 1) return 'Enter how many plots the ' + TYPES[split.type] + ' produces.';
                if (n > maxPlotsFor(split.type)) return 'A ' + TYPES[split.type] + ' in a duplex can produce at most ' + maxPlotsFor(split.type) + ' plots.';
                return null;
            },
        }).then(r => {
            if (!r.isConfirmed) return dropLaterCop(index);

            // Written onto the SPLIT's entry — the Quantities step reads the same
            // property, so it arrives there already filled in.
            split.count = parseInt(r.value, 10) || 1;
            renderRanks();

            askLaterCopScope(index, split.count);
        });
    }

    /** Taking the update back out when a card is cancelled. */
    function dropLaterCop(index) {
        state.plan.splice(index, 1);
        renderRanks();
    }

    // ---- card 2: all of them, or only some? ----
    function askLaterCopScope(index, total) {
        Swal.fire({
            icon: 'question',
            title: 'Change of Purpose',
            html: 'That gives <b>' + total + ' plots</b>. Do you want to change the purpose of '
                + '<b>all ' + total + '</b>, or only some of them?',
            showCancelButton: true,
            confirmButtonText: 'Yes — all ' + total,
            cancelButtonText: 'No — only some',
            reverseButtons: true,
            allowOutsideClick: false,
            confirmButtonColor: '#2563eb',
        }).then(r => {
            // Dismissing by any other route is not an answer, so the update comes out
            // rather than being captured on a guess.
            if (r.dismiss === Swal.DismissReason.close
                || r.dismiss === Swal.DismissReason.esc) {
                return dropLaterCop(index);
            }
            askLaterCopPurpose(index, total, r.isConfirmed);
        });
    }

    /**
     * Card 3 — the Change of Purpose card itself.
     *
     * "All" fixes the count at the total and asks only what they become; "only some"
     * asks for both. What the plots ARRIVE as is stated rather than asked: they come
     * out of the previous stage, and the first leg exists precisely to bring everything
     * to one land use.
     */
    function askLaterCopPurpose(index, total, all) {
        const entry = state.plan[index];
        if (!entry) return;

        const arriving = laterCopCurrentUse(index);
        const arrivingLabel = arriving
            ? (LAND_USE_LABELS[arriving] ? LAND_USE_LABELS[arriving] + ' (' + arriving + ')' : arriving)
            : 'their current purpose';

        const options = LAND_USE_OPTIONS
            .map(c => '<option value="' + c + '"' + (c === entry.new_land_use ? ' selected' : '') + '>'
                + (LAND_USE_LABELS[c] || c) + ' (' + c + ')</option>').join('');

        const countField = all
            ? '<p style="margin:0 0 12px">All <b>' + total + '</b> plots change purpose.</p>'
            : '<label style="display:block;font-weight:700;margin-bottom:4px">'
              + 'How many of the ' + total + ' change purpose?</label>'
              + '<input id="dx-later-count" type="number" min="1" max="' + total + '" step="1"'
              + ' class="swal2-input" style="margin:0 0 12px;width:100%" value="'
              + (entry.count && entry.count <= total ? entry.count : '') + '">';

        Swal.fire({
            icon: 'question',
            title: 'Change of Purpose',
            html: 'You pick <i>which</i> plots on the Stages step — they have no numbers yet.'
                + '<div style="margin-top:16px;text-align:left;font-size:13px">'
                + countField
                + '<label style="display:block;font-weight:700;margin-bottom:4px">'
                + 'They arrive as <span style="font-weight:400">' + arrivingLabel + '</span>. '
                + 'What do they become?</label>'
                + '<select id="dx-later-use" class="swal2-select" style="margin:0;width:100%">'
                + '<option value="">— New purpose —</option>' + options + '</select>'
                + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Set',
            cancelButtonText: 'Remove this update',
            reverseButtons: true,
            allowOutsideClick: false,
            confirmButtonColor: '#2563eb',
            focusConfirm: false,
            preConfirm: () => {
                const n = all
                    ? total
                    : parseInt(document.getElementById('dx-later-count')?.value, 10);
                const use = document.getElementById('dx-later-use')?.value || '';

                if (!n || n < 1) return Swal.showValidationMessage('Enter how many plots change purpose.');
                if (n > total) return Swal.showValidationMessage('Only ' + total + ' plots will exist.');
                if (!use) return Swal.showValidationMessage('Pick the purpose those plots change to.');

                return { count: n, use };
            },
        }).then(r => {
            if (!r.isConfirmed) return dropLaterCop(index);

            entry.count = r.value.count;
            entry.new_land_use = r.value.use;
            entry.current_land_use = arriving || null;
            renderRanks();
        });
    }

    /**
     * What a later Change of Purpose's plots arrive as.
     *
     * They come out of whatever ran before it, and by then the duplex has usually been
     * brought to one land use — the first leg exists precisely to do that. So this
     * reads the most recent answer upstream rather than asking again: an earlier CoP's
     * new purpose, or failing that the duplex's own land use.
     */
    function laterCopCurrentUse(index) {
        for (let i = index - 1; i >= 0; i--) {
            const p = state.plan[i];
            if (p.type === 'change_of_purpose') {
                if (p.new_land_use) return p.new_land_use;
                const rows = Array.isArray(p.cop_rows) ? p.cop_rows.filter(r => r.new_land_use) : [];
                if (rows.length) return rows[0].new_land_use;
            }
        }

        const first = state.sources[0]?.fileNumber;
        return first ? landUseCode(first) : '';
    }

    /**
     * True when this stage's input is the real source files rather than the previous
     * stage's holding numbers — i.e. it is the first leg.
     */
    function operatesOnSourceFiles(type) {
        return state.plan.length > 0 && state.plan[0].type === type;
    }

    // ------------------------------------------------- change of purpose, step 1
    /**
     * "You have selected 3 files. Do you want to change the purpose of all 3, or are
     * some of the files for merger?"
     *
     * Yes  -> every source file changes; the table opens with a row each.
     * No   -> the officer picks which, one row at a time, adding more as needed.
     */
    window.askChangeOfPurposeScope = function () {
        const n = state.sources.length;

        if (!window.Swal) return openCopTable(state.sources.map(s => s.fileNumber));

        Swal.fire({
            icon: 'question',
            title: 'Change of Purpose',
            html: `You have selected <b>${n} files</b>. Do you want to change the purpose of
                   <b>all ${n}</b> selected files, or are some of the files for merger?`,
            showCancelButton: true,
            confirmButtonText: `Yes — all ${n}`,
            cancelButtonText: 'No — only some',
            allowOutsideClick: false,
            reverseButtons: true,
            confirmButtonColor: '#2563eb',
        }).then(r => {
            // Dismissing the dialog by any other route leaves the stage unanswered
            // rather than guessing, and the table opens empty.
            if (r.isConfirmed) return openCopTable(state.sources.map(s => s.fileNumber));
            openCopTable([]);
        });
    };

    /** The rows currently captured for the first-leg Change of Purpose. */
    function copEntry() {
        // Only a FIRST-leg Change of Purpose is answered on step 1, because only it
        // works on the real source files. A later one runs on plots that do not exist
        // yet, so it is captured on its own panel at step 3.
        const first = state.plan[0];
        return (first && first.type === 'change_of_purpose') ? first : null;
    }

    function copRows() {
        const entry = copEntry();
        return (entry && Array.isArray(entry.cop_rows)) ? entry.cop_rows : [];
    }

    /**
     * Opens the picker table. `preselected` seeds one row per file; an empty list
     * opens with a single blank row for the officer to fill in.
     */
    function openCopTable(preselected) {
        const entry = copEntry();
        if (!entry) return;

        entry.cop_rows = (preselected && preselected.length)
            ? preselected.map(f => ({ file_no: f, current_land_use: landUseCode(f), new_land_use: '' }))
            : [{ file_no: '', current_land_use: '', new_land_use: '' }];

        renderRanks();
        document.getElementById('dx-cop-card')?.classList.remove('hidden');
    }

    window.addCopRow = function () {
        const entry = copEntry();
        if (!entry) return;

        entry.cop_rows = copRows().concat([{ file_no: '', current_land_use: '', new_land_use: '' }]);
        renderRanks();
    };

    window.removeCopRow = function (i) {
        const entry = copEntry();
        if (!entry) return;

        const rows = copRows().slice();
        rows.splice(i, 1);
        entry.cop_rows = rows.length ? rows : [{ file_no: '', current_land_use: '', new_land_use: '' }];
        renderRanks();
    };

    /** The current purpose is never typed — it is read off the file number. */
    window.onCopFileChange = function (i, value) {
        const entry = copEntry();
        if (!entry) return;

        const rows = copRows().slice();
        rows[i] = Object.assign({}, rows[i], {
            file_no: value,
            current_land_use: landUseCode(value),
        });
        entry.cop_rows = rows;
        renderRanks();
    };

    window.onCopPurposeChange = function (i, value) {
        const entry = copEntry();
        if (!entry) return;

        const rows = copRows().slice();
        rows[i] = Object.assign({}, rows[i], { new_land_use: value });
        entry.cop_rows = rows;
        renderRanks();
    };

    function renderCopTable() {
        const host = document.getElementById('dx-cop-rows');
        if (!host) return;

        const rows  = copRows();
        const taken = rows.map(r => r.file_no).filter(Boolean);

        host.innerHTML = rows.map((row, i) => {
            // A file already named on another row drops out, so one file cannot be
            // given two different new purposes.
            const options = state.sources
                .filter(s => s.fileNumber === row.file_no || !taken.includes(s.fileNumber))
                .map(s => `<option value="${s.fileNumber}" ${s.fileNumber === row.file_no ? 'selected' : ''}>
                               ${s.fileNumber}</option>`).join('');

            // A KANGIS number carries no land use of its own, so say so rather than
            // showing an empty box the officer cannot act on.
            const current = row.file_no
                ? (row.current_land_use
                    ? (LAND_USE_LABELS[row.current_land_use]
                        ? LAND_USE_LABELS[row.current_land_use] + ' (' + row.current_land_use + ')'
                        : row.current_land_use)
                    : 'Not carried by this number')
                : '';

            const unknown = row.file_no && !row.current_land_use;

            return `
                <div class="grid grid-cols-12 gap-2 items-center">
                    <div class="col-span-5">
                        <select onchange="onCopFileChange(${i}, this.value)"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                            <option value="">— Select file —</option>
                            ${options}
                        </select>
                    </div>
                    <div class="col-span-3">
                        <input type="text" readonly value="${current}"
                            title="${unknown ? 'Read from the file number. A KANGIS number does not carry one.' : ''}"
                            class="w-full px-3 py-2 rounded-xl border text-xs font-semibold
                                   ${unknown ? 'border-amber-200 bg-amber-50 text-amber-700 italic'
                                             : 'border-slate-200 bg-slate-50 text-slate-600'}">
                    </div>
                    <div class="col-span-3">
                        <select onchange="onCopPurposeChange(${i}, this.value)"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                            <option value="">— New purpose —</option>
                            ${LAND_USE_OPTIONS
                                .map(c => `<option value="${c}" ${c === row.new_land_use ? 'selected' : ''}>
                                               ${LAND_USE_LABELS[c] || c} (${c})</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-span-1 flex justify-end">
                        <button type="button" onclick="removeCopRow(${i})" title="Remove this row"
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>`;
        }).join('');

        // What happens to the files NOT in the table — named, because "the rest pass
        // through" means nothing next to a list of actual file numbers.
        const untouched = state.sources
            .map(s => s.fileNumber)
            .filter(f => !taken.includes(f));

        const note = document.getElementById('dx-cop-untouched');
        if (note) {
            note.innerHTML = untouched.length
                ? `<b>${untouched.join(', ')}</b> ${untouched.length === 1 ? 'keeps its' : 'keep their'}
                   current purpose and file number, and ${untouched.length === 1 ? 'carries' : 'carry'}
                   on to the next update.`
                : 'Every selected file is changing purpose.';
        }

        if (window.lucide) lucide.createIcons();
    }

    /**
     * Removing by POSITION, not by type: Change of Purpose can appear twice, and
     * filtering the plan by type would take both legs out at once.
     */
    window.removeTypeFromPlan = function (index) {
        if (state.duplex) return;          // the plan is fixed once the duplex exists

        index = Number(index);
        const entry = state.plan[index];
        if (!entry) return;

        // Taking out the merger the system suggested is a real decision — it is the
        // usual first leg — so it is confirmed rather than silently dropped.
        if (entry && entry.auto && window.Swal) {
            return Swal.fire({
                icon: 'question',
                title: 'Remove the Merger?',
                html: 'Several files are selected, and they are normally merged first.'
                    + '<br><br>Remove it only if something has to happen to the files '
                    + '<b>before</b> they are merged — bringing them to a common land use, '
                    + 'for instance. You can add a Merger back at any point in the order.',
                showCancelButton: true,
                confirmButtonText: 'Yes, remove it',
                cancelButtonText: 'Keep it',
                allowOutsideClick: false,
                confirmButtonColor: '#dc2626',
            }).then(r => {
                if (!r.isConfirmed) return;
                state.mergerDismissed = true;
                state.plan.splice(index, 1);
                renderRanks();
            });
        }

        if (entry && entry.auto) state.mergerDismissed = true;

        state.plan.splice(index, 1);
        renderRanks();
    };

    /**
     * The chosen updates, in the order they were chosen. Rank 1 is the first row, so
     * the list reads top to bottom as the sequence it will actually run in.
     */
    function renderSelectedTypes() {
        const list = document.getElementById('dx-type-list');
        if (!list) return;

        if (!state.plan.length) {
            list.innerHTML = `
                <div class="flex flex-col items-center justify-center gap-2 py-8 rounded-2xl border border-dashed border-slate-200 text-slate-400">
                    <i data-lucide="list-plus" class="w-5 h-5"></i>
                    <p class="text-xs font-semibold">No updates added yet</p>
                    <p class="text-[11px]">Choose one from the dropdown above to start.</p>
                </div>`;
            if (window.lucide) lucide.createIcons();
            return;
        }

        const locked = !!state.duplex;

        list.innerHTML = state.plan.map((entry, index) => {
            const meta = TYPE_META[entry.type] || ['circle', ''];
            return `
                <div class="type-row picked flex items-center gap-3.5 px-4 py-3.5 rounded-2xl border border-blue-300 bg-blue-50/40"
                     data-type="${entry.type}">
                    <span class="rank-badge rank-${Math.min(entry.rank, 5)} w-8 h-8 rounded-xl border flex items-center justify-center text-xs font-black shrink-0">
                        ${entry.rank}</span>

                    <span class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                        <i data-lucide="${meta[0]}" class="w-4 h-4"></i>
                    </span>

                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-bold text-slate-700 leading-tight">${copSeriesLabel(entry, index)}</span>
                        <span class="block text-[11px] text-slate-500 mt-0.5">
                            Runs ${ordinal(entry.rank)} &middot; ${entry.auto
                                ? `<b class="text-blue-700">${state.sources.length} source files</b> merged into one parcel`
                                : (entry.type === 'change_of_purpose' && index > 0 && entry.count
                                    ? `<b class="text-blue-700">${entry.count} plot${entry.count === 1 ? '' : 's'}</b> take a new purpose`
                                    : meta[1])}</span>
                    </span>

                    ${locked
                        ? `<span class="text-slate-400" title="The plan is locked"><i data-lucide="lock" class="w-3.5 h-3.5"></i></span>`
                        : `<button type="button" onclick="removeTypeFromPlan(${index})"
                                  title="${entry.auto
                                      ? 'Added automatically — remove it if something must happen before the merger'
                                      : 'Remove'}"
                                  class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition shrink-0">
                              <i data-lucide="x" class="w-3.5 h-3.5"></i>
                          </button>`}
                </div>`;
        }).join('');

        if (window.lucide) lucide.createIcons();
    }

    /** Only what has NOT been chosen yet — a picked update leaves the dropdown. */
    function renderTypePicker() {
        const select = document.getElementById('dx-type-picker');
        if (!select) return;

        const taken = state.plan.map(p => p.type);

        // A picked update leaves the list — except Change of Purpose, which stays so it
        // can be added again at a later rank.
        const left = Object.keys(TYPES)
            .filter(t => t === 'change_of_purpose' || !taken.includes(t));

        const label = t => TYPES[t]
            + (t === 'change_of_purpose' && taken.includes(t) ? ' (2nd Series)' : '');

        select.innerHTML = left.length
            ? '<option value="">— Select an update to add —</option>'
              + left.map(t => '<option value="' + t + '">' + label(t) + '</option>').join('')
            : '<option value="">All updates added</option>';

        select.disabled = !left.length || !!state.duplex;
    }

    const ordinal = n => ['', 'first', 'second', 'third', 'fourth', 'fifth'][n] || (n + 'th');

    /**
     * "Change of Purpose (2nd Series)" for the later leg.
     *
     * Two rows both reading "Change of Purpose" is the single most confusing thing on
     * this screen once the plan carries both, so the later one is numbered wherever it
     * is named: the plan list, the order rail, the stage track and the stage header.
     */
    function copSeriesLabel(entry, index) {
        if (!entry || entry.type !== 'change_of_purpose') return TYPES[entry?.type] || '';

        const series = state.plan
            .slice(0, index + 1)
            .filter(p => p.type === 'change_of_purpose').length;

        return series > 1 ? TYPES.change_of_purpose + ' (' + ordinalSeries(series) + ' Series)'
                          : TYPES.change_of_purpose;
    }

    const ordinalSeries = n => n + (['th', 'st', 'nd', 'rd'][(n % 100 - 20) % 10] || ['th', 'st', 'nd', 'rd'][n % 100] || 'th');

    /** The same label, addressed by a stage row rather than a plan entry. */
    function stageSeriesLabel(stage) {
        if (!stage || stage.type !== 'change_of_purpose') return TYPES[stage?.type] || '';

        const series = state.stages
            .filter(s => s.type === 'change_of_purpose' && Number(s.rank) <= Number(stage.rank))
            .length;

        return series > 1 ? TYPES.change_of_purpose + ' (' + ordinalSeries(series) + ' Series)'
                          : TYPES.change_of_purpose;
    }

    function renderRanks() {
        state.plan.forEach((p, i) => { p.rank = i + 1; });


        renderTypePicker();
        renderSelectedTypes();

        document.getElementById('dx-single-note').classList.toggle('hidden', state.plan.length !== 1);
        document.getElementById('dx-picked-count').textContent = state.plan.length + ' selected';

        syncStartProcess();
        syncCopCard();
        renderOrderRail();
    }

    /**
     * A duplex needs TWO or more updates, so Start Process is disabled until there are.
     *
     * One update carried through this page produces a duplex that combines nothing: the
     * same holding number, approval and memo the single-workflow page already gives it,
     * only harder to find afterwards. The rule is enforced in store() as well - this is
     * the half that stops the officer filling in a plan the server will refuse.
     */
    const MIN_UPDATES = 2;

    function syncStartProcess() {
        const btn = document.getElementById('dx-next');
        if (!btn || state.step !== 1) return;

        // Only the plan gates the button. The other step-1 rules (a source file, an
        // applicant, a legal first leg) are checked on the way out, where they can say
        // which one failed - a disabled button with no message explains nothing.
        const enough = state.plan.length >= MIN_UPDATES;

        btn.disabled = !enough;
        btn.classList.toggle('opacity-50', !enough);
        btn.classList.toggle('cursor-not-allowed', !enough);
        btn.title = enough ? '' : 'A duplex needs two or more updates.';
    }

    /**
     * The per-file table belongs to a FIRST-leg Change of Purpose over several source
     * files, and only to that. A CoP further down the order works from the previous
     * stage's holding numbers, which do not exist yet at step 1, so it is answered on
     * its own panel at step 3 as before.
     */
    function syncCopCard() {
        const card = document.getElementById('dx-cop-card');
        if (!card) return;

        const entry   = copEntry();
        const applies = !!entry
            && !state.duplex
            && operatesOnSourceFiles('change_of_purpose')
            && state.sources.length > 1;

        card.classList.toggle('hidden', !applies);

        if (!applies) {
            // Dropped out of first place, or down to one file: the answer no longer
            // describes the plan, so it is discarded rather than left to be submitted.
            if (entry) delete entry.cop_rows;
            return;
        }

        // Source files can change after the table was filled in. Drop rows naming a
        // file that is no longer selected, and keep the count in step.
        if (Array.isArray(entry.cop_rows)) {
            const live = state.sources.map(s => s.fileNumber);
            const kept = entry.cop_rows.filter(r => !r.file_no || live.includes(r.file_no));
            entry.cop_rows = kept.length ? kept : [{ file_no: '', current_land_use: '', new_land_use: '' }];
        }

        entry.count = copCompleteRows().length || null;

        // With two Change of Purpose legs in the plan, "Which files change purpose?" on
        // its own does not say which one it means. Name the leg.
        const heading = document.getElementById("dx-cop-heading");
        if (heading) {
            const twoLegs = state.plan.filter(p => p.type === "change_of_purpose").length > 1;
            heading.textContent = twoLegs
                ? "Which files change purpose? — the FIRST one, runs 1st"
                : "Which files change purpose?";
        }

        renderCopTable();
    }

    /** Rows that actually say something: a file AND the purpose it changes to. */
    function copCompleteRows() {
        return copRows().filter(r => r.file_no && r.new_land_use);
    }

    /**
     * Live read-back of the plan in execution order. The order IS the instruction —
     * it drives the memo and the commit — so it gets shown as a list rather than
     * being left for the officer to reconstruct from scattered badges.
     */
    function renderOrderRail() {
        const rail = document.getElementById('dx-order-rail');
        if (!rail) return;

        if (!state.plan.length) {
            rail.innerHTML = '<p class="text-xs text-slate-400 italic">Nothing ticked yet.</p>';
            return;
        }

        rail.innerHTML = state.plan.map(p => `
            <div class="flex items-center gap-2.5">
                <span class="rank-badge rank-${Math.min(p.rank, 5)} w-6 h-6 rounded-lg border flex items-center justify-center text-[11px] font-black shrink-0">${p.rank}</span>
                <span class="text-xs font-bold text-slate-700">${TYPES[p.type]}</span>
            </div>`).join('');
    }

    // ---------------------------------------------------------------- step 2
    /**
     * How many parcels a stage records a size for.
     *
     * A merger sizes what goes IN (each source file has its own area); everything else
     * sizes what comes OUT. A Change of Purpose sizes only the files it renames.
     */
    function sizeSlots(p, incoming) {
        // A Change of Purpose renames a parcel and leaves its area alone, so it records
        // no size. Asking for one invited a figure that contradicted the parcel the plot
        // actually came from.
        if (p.type === 'change_of_purpose') return 0;
        if (p.type === 'merger') return incoming;
        // One set of dimension boxes per stripe — see stripeCount(). Still one file.
        if (p.type === 'extension') return stripeCount(p);
        return p.count || 2;
    }

    /**
     * One parcel's Length x Width pair and its derived area, as markup.
     *
     * Shared by the first render and by the grow-with-the-count path below, which used
     * to build its own copy of the old single-size cell - and built it with `data-type`
     * where the handler reads `data-idx`, so a size typed into a box added by raising
     * the quantity was silently thrown away. One definition, one set of data
     * attributes, and that cannot happen again.
     */
    function dimCell(p, index, i) {
        const terms = normaliseTerms((p.dims || [])[i]);

        const box = (t, value) => `
            <div class="relative">
                <input type="number" step="any" min="0" placeholder="${t + 1}"
                       value="${value ?? ''}" aria-label="Side ${t + 1} in metres"
                       data-idx="${index}" data-slot="${i}" data-term="${t}"
                       oninput="onDimInput(this)"
                       class="dx-dim w-16 px-1.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center">
                ${terms.length > 1 ? `
                    <button type="button" onclick="removeDimTerm(${index}, ${i}, ${t})"
                        title="Remove this side"
                        class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-white border border-slate-200
                               text-slate-400 hover:text-rose-600 hover:border-rose-200 text-[9px] font-black
                               leading-none flex items-center justify-center">&times;</button>` : ''}
            </div>`;

        return `
            <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1.5">
                ${p.type === 'merger' ? 'File' : (p.type === 'extension' ? 'Stripe' : 'Plot')} ${i + 1}</label>
            <div class="flex flex-wrap items-center gap-1.5">
                ${terms.map((v, t) => box(t, v)).join('<span class="text-slate-400 text-xs font-black shrink-0">×</span>')}
                <button type="button" onclick="addDimTerm(${index}, ${i})"
                    title="Add another side"
                    class="w-7 h-9 rounded-lg border border-dashed border-slate-300 text-slate-400
                           hover:border-blue-400 hover:text-blue-600 transition text-sm font-black shrink-0">+</button>
            </div>
            <p class="dx-slot-area text-[10px] font-bold text-slate-500 text-center mt-1"
               data-idx="${index}" data-slot="${i}"></p>`;
    }

    /** Always at least one box to type in. */
    function normaliseTerms(value) {
        // Captured as {l, w} before dimensions became a chain; read forward so a stage
        // saved then still opens with what it holds.
        if (value && !Array.isArray(value)) {
            return [value.l ?? '', value.w ?? ''];
        }

        const terms = Array.isArray(value) ? value.slice() : [];

        return terms.length ? terms : ['', ''];
    }

    /**
     * The dimension inputs under one stage row, preserving anything already typed.
     *
     * The officer reads Length x Width off the survey plan, so that is what the card
     * asks for; the AREA is derived from it and never typed. Both are kept: `dims`
     * holds what was entered, `sizes` holds the m² the rest of the duplex works in, so
     * the stage payload, the running totals and the memo are unchanged by this.
     *
     * A stage captured before dimensions existed has a size and no dims. Its boxes open
     * blank and its area is left alone until a dimension is actually typed - recomputing
     * it from two empty boxes would silently zero a figure the officer had entered.
     */
    function sizeGrid(p, slots, index) {
        p.sizes = p.sizes || [];
        p.dims  = p.dims  || [];

        // Nothing to size — see sizeSlots().
        if (slots < 1) return '';

        const cells = Array.from({ length: slots })
            .map((_, i) => '<div>' + dimCell(p, index, i) + '</div>')
            .join('');

        {{-- Where the running-area line sits depends on which way the arithmetic runs.
             A subdivision ALLOCATES out of a parcel whose size is already known, so the
             figure has to be above the boxes being filled in. A merger DERIVES its total
             from the sizes typed in, so its line can only follow them. --}}
        const allocating = p.type === 'subdivision' || p.type === 'separation';
        const noteEl = '<p class="dx-size-note text-xs"></p>';

        // Thirty-nine plots of the same size is the normal case, and typing the same
        // number thirty-nine times is what officers actually complained about. Same
        // gesture as the batch commissioning screen: fill the first, copy it down.
        //
        // Never on a MERGER: its boxes are the existing areas of different files, which
        // are almost never equal, so copying one across them would be wrong data in a
        // single click rather than a shortcut.
        const applyAll = (slots > 1 && p.type !== 'merger')
            ? `<button type="button" onclick="applySizeToAll(${index})"
                   class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-600
                          hover:text-blue-700 px-2 py-1 rounded-lg hover:bg-blue-50 transition">
                   <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                   Apply Plot 1 to all ${slots}
               </button>`
            : '';

        return `
            <div class="dx-size-grid mt-3 pt-3 border-t border-slate-100">
                ${allocating ? '<div class="mb-2.5">' + noteEl + '</div>' : ''}
                <div class="flex items-center justify-between gap-3 mb-2">
                    <p class="text-[11px] font-black uppercase tracking-wider text-slate-500">
                        Plot dimension${slots === 1 ? '' : 's'} (m) &middot;
                        <span class="text-slate-400">one box per side &mdash; use + for a parcel with more than two</span></p>
                    ${applyAll}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">${cells}</div>
                ${allocating ? '' : '<div class="mt-2.5">' + noteEl + '</div>'}
            </div>`;
    }

    /**
     * Copy the first size box down the rest of the stage's boxes.
     *
     * Writes straight to the inputs rather than re-rendering the row, so the officer
     * does not lose focus or the quantity they are part-way through typing.
     */
    window.applySizeToAll = function (index) {
        const row = document.querySelectorAll('#dx-quantities > div')[Number(index)];
        const entry = state.plan[Number(index)];
        if (!row || !entry) return;

        const slots = row.querySelectorAll('.dx-size-grid .grid > div').length;
        const first = normaliseTerms((entry.dims || [])[0]);

        if (!dimsText(first)) {
            return toast('info', 'Nothing to copy',
                'Enter the first plot\'s dimensions, then apply them to the rest.');
        }

        // The whole chain, and the area with it: a parcel measured by its perimeter has
        // an area that cannot be re-derived from the sides, so copying the sides alone
        // would leave every other plot showing an area its own boxes cannot account for.
        const size = (entry.sizes || [])[0];

        entry.dims  = entry.dims  || [];
        entry.sizes = entry.sizes || [];

        for (let i = 0; i < slots; i++) {
            entry.dims[i]  = first.slice();
            entry.sizes[i] = size;
        }

        // Every cell changed shape (a different number of boxes), so this one repaints
        // the grid rather than writing values into the inputs that are there.
        renderQuantities();
    };

    /**
     * A dimension changed: recompute that parcel's area and repaint every total.
     *
     * The area is only overwritten once a dimension is actually present. Clearing both
     * boxes leaves the last computed figure alone rather than replacing it with 0 —
     * an officer tabbing through a stage they are not editing must not lose its sizes.
     */
    /** One more side on this parcel. */
    window.addDimTerm = function (index, slot) {
        const entry = state.plan[Number(index)];
        if (!entry) return;

        entry.dims = entry.dims || [];
        entry.dims[slot] = normaliseTerms(entry.dims[slot]);
        entry.dims[slot].push('');

        redrawDimCell(index, slot);
    };

    window.removeDimTerm = function (index, slot, term) {
        const entry = state.plan[Number(index)];
        if (!entry) return;

        entry.dims[slot] = normaliseTerms(entry.dims[slot]);

        // Never down to nothing: a parcel with no box has no way back.
        if (entry.dims[slot].length <= 1) return;

        entry.dims[slot].splice(Number(term), 1);
        recomputeSlot(entry, slot);
        redrawDimCell(index, slot);
    };

    /**
     * Repaint one parcel's cell in place.
     *
     * Only that cell: re-rendering the whole step would take the focus out of a box
     * the officer is still typing in two rows down.
     */
    function redrawDimCell(index, slot) {
        const row = document.querySelectorAll('#dx-quantities > div')[Number(index)];
        const entry = state.plan[Number(index)];
        if (!row || !entry) return;

        const cell = row.querySelectorAll('.dx-size-grid .grid > div')[Number(slot)];
        if (!cell) return;

        cell.innerHTML = dimCell(entry, index, slot);
        refreshSizeNotes();
        icons();
    }

    /** The area this parcel's sides imply, where they imply one. */
    function recomputeSlot(entry, slot) {
        const derived = areaFromTerms(entry.dims[slot]);

        entry.sizes = entry.sizes || [];

        if (derived !== null) {
            entry.sizes[slot] = derived;
        }
    }

    window.onDimInput = function (input) {
        const entry = state.plan[Number(input.dataset.idx)];
        if (!entry) return;

        const slot = Number(input.dataset.slot);
        const term = Number(input.dataset.term);

        entry.dims  = entry.dims  || [];
        entry.sizes = entry.sizes || [];
        entry.dims[slot] = normaliseTerms(entry.dims[slot]);
        entry.dims[slot][term] = input.value;

        // Only where the sides give an answer. A parcel measured by its perimeter keeps
        // whatever area was typed for it - editing a side must not wipe that.
        recomputeSlot(entry, slot);
        scheduleQuantityRefresh('notes');
    };

    /**
     * The per-parcel readout under each pair of boxes: "2,842.35 m² · 0.2842 Ha".
     *
     * Blank where nothing has been entered — a row of "0 m² · 0.0000 Ha" reads as a
     * measured answer rather than an empty box.
     */
    /**
     * Coalesce the whole-grid refreshes onto one animation frame.
     *
     * A stage can carry two hundred plots, and both refreshes walk every cell. Run
     * straight from `oninput` that work repeats per CHARACTER — typing "82" rebuilds
     * the grid at 8 plots and then at 82 — which is what froze the modal once the
     * counts grew, and left Continue Process looking dead while the main thread was
     * busy. Scheduled, a burst of typing costs one pass, and the field being typed in
     * stays responsive because the work lands between frames.
     *
     * Only the typing paths go through here. Click-driven refreshes stay immediate:
     * they happen once, and the officer is waiting to see the result.
     */
    let dxRefreshFrame = null;
    let dxRefreshWanted = { flows: false, notes: false };

    function scheduleQuantityRefresh(what) {
        dxRefreshWanted[what] = true;
        if (dxRefreshFrame !== null) return;

        dxRefreshFrame = requestAnimationFrame(() => {
            dxRefreshFrame = null;
            const wanted = dxRefreshWanted;
            dxRefreshWanted = { flows: false, notes: false };

            // refreshQuantityFlows() ends in refreshSizeNotes(), so the notes pass is
            // not repeated when both were asked for.
            if (wanted.flows) refreshQuantityFlows();
            else if (wanted.notes) refreshSizeNotes();
        });
    }

    function refreshSlotAreas() {
        document.querySelectorAll('#dx-quantities .dx-slot-area').forEach(el => {
            const entry = state.plan[Number(el.dataset.idx)];
            const slot  = Number(el.dataset.slot);
            const size  = entry ? parseFloat((entry.sizes || [])[slot]) : NaN;

            if (!entry || !isFinite(size) || size <= 0) {
                if (el.textContent !== '') el.textContent = '';
                return;
            }

            // Assigning innerHTML re-parses the markup even when the text is identical.
            // Across two hundred readouts, on every keystroke, that is the cost worth
            // skipping — nearly all of them are unchanged by one character typed in one
            // box, so compare before writing.
            const html = area(size) + ' &middot; <span class="text-blue-700">' + hectares(size) + '</span>';
            if (el.innerHTML !== html) el.innerHTML = html;
        });
    }

    const sumSizes = p => (p.sizes || []).reduce((t, v) => t + (parseFloat(v) || 0), 0);
    // Areas are RECORDED in square metres - that is what the stage payload holds, what
    // the memo prints and what every downstream reader expects - and READ OUT in
    // hectares beside them, because that is the unit the officer checks a parcel in.
    // Both, never one: 1,410 m2 and 0.141 Ha are the same parcel said two ways, and a
    // screen that shows only the hectare figure cannot be checked against the memo.
    const AREA_UNIT = 'm²';
    const M2_PER_HECTARE = 10000;

    /**
     * A parcel's dimensions are a CHAIN OF SIDES, not a length and a width.
     *
     * The Ministry's own memo prints them that way — "A: 60.00m x 21.00m x 46.00m x
     * 21.00m x 42.71m" — because the parcels are surveyed polygons, not rectangles.
     * Two boxes could only ever describe the rectangular minority, and forced everyone
     * else to round their plot into a shape it is not.
     *
     * So dimensions are a list of any length, and the AREA is only derived where the
     * arithmetic is honest: two sides multiply, and nothing else does. Side lengths
     * alone do not determine the area of a polygon — three or more sides can enclose
     * many different areas — so a parcel with more than two sides carries its
     * dimensions and no computed area, rather than one invented from the perimeter.
     */
    const DERIVABLE_TERMS = 2;

    /**
     * An extension may come in SEVERAL STRIPES.
     *
     * The land added to a parcel is not always one piece — a boundary adjustment can
     * pick up two or three separate stripes along different sides, each with its own
     * dimensions on the survey plan. The stage asked for one and one only, so the rest
     * had nowhere to be recorded.
     *
     * The count is therefore how many stripes to MEASURE, and nothing else. An
     * extension still issues exactly ONE holding number and ONE file number, whatever
     * the count says: the stripes are added to a single parcel, and that parcel keeps
     * being one file. DuplexParcelUpdateStage::outputCount() already returns 1 for an
     * extension regardless of plot_count, so the server is the guarantee and this is
     * only the screen agreeing with it.
     */
    const stripeCount = p => Math.max(1, Number(p.count) || 1);

    /** What a stage HANDS ON, which for a merger and an extension is always one file. */
    const outputCountOf = p => (p.type === 'merger' || p.type === 'extension')
        ? 1
        : (p.count || 2);

    /** The terms as the memo prints them: "60 x 21 x 46". Blanks dropped. */
    const dimsText = terms => (terms || [])
        .map(v => String(v ?? '').trim())
        .filter(v => v !== '')
        .join(' x ');

    /** m2 from the sides, or null where it cannot honestly be derived. */
    const areaFromTerms = terms => {
        const nums = (terms || [])
            .map(v => parseFloat(v))
            .filter(v => isFinite(v) && v > 0);

        return nums.length === DERIVABLE_TERMS ? nums[0] * nums[1] : null;
    };

    /** 2842.3474 -> "0.2842 Ha". Four places: a plot is a fraction of a hectare. */
    const hectares = m2 => {
        const ha = (Number(m2) || 0) / M2_PER_HECTARE;
        return ha.toLocaleString('en-NG', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ' Ha';
    };
    const area = n => {
        const v = Number(n) || 0;
        // Whole metres print clean; fractions keep up to two places, so 1410 reads
        // "1,410" and 1410.3 reads "1,410.3" rather than "1,410.30".
        return (v === Math.round(v)
            ? v.toLocaleString('en-NG')
            : v.toLocaleString('en-NG', { minimumFractionDigits: 1, maximumFractionDigits: 2 }))
            + ' ' + AREA_UNIT;
    };

    /** "2,842.35 m² · 0.2842 Ha" — the pair the running-total lines report. */
    const areaWithHectares = n => area(n) + ' · ' + hectares(n);

    /**
     * The parcel area walked through the chain.
     *
     * A merger's four files add up to the parcel the subdivision then divides — that
     * total was nowhere on screen, so there was no way to tell whether the plot sizes
     * entered below actually accounted for the land. Each stage now states what it
     * receives and what it passes on, and a subdivision says when the two disagree.
     */
    function refreshSizeNotes() {
        refreshSlotAreas();

        let carried = null;   // area of the parcel entering the next stage, or null if unknown

        document.querySelectorAll('#dx-quantities > div').forEach((row, i) => {
            const p = state.plan[i];
            const note = row.querySelector('.dx-size-note');
            if (!p || !note) return;

            const entered = sumSizes(p);

            // The line carries a state, so it is coloured as one rather than sitting grey
            // with a coloured word buried in it: blue while it is simply reporting an
            // area, green when a subdivision accounts for its parent, amber when it does
            // not. `strong` keeps the figures a shade darker than the sentence.
            let text = '', tone = 'text-slate-400', strong = 'text-slate-700';

            const b = v => '<b class="' + strong + '">' + v + '</b>';

            if (p.type === 'merger') {
                carried = entered || null;
                if (entered) {
                    tone = 'text-blue-600';
                    strong = 'text-blue-800';
                    text = 'Merged parcel: ' + b(areaWithHectares(entered));
                }
            } else if (p.type === 'subdivision' || p.type === 'separation') {
                // Tolerance in SQUARE METRES: half a metre either way, which is the
                // rounding a survey plan carries, not the 90 m2 the old 0.009 Ha allowed.
                const balanced = carried && entered
                    && Math.abs(carried - entered) < 0.01;

                if (carried && entered) {
                    tone = balanced ? 'text-emerald-600' : 'text-amber-600';
                    strong = balanced ? 'text-emerald-800' : 'text-amber-800';
                } else if (carried || entered) {
                    tone = 'text-blue-600';
                    strong = 'text-blue-800';
                }

                const parts = [];
                if (carried) parts.push('Parcel being divided: ' + b(areaWithHectares(carried)));
                if (entered) parts.push('plots entered: ' + b(areaWithHectares(entered)));

                if (carried && entered) {
                    const diff = carried - entered;
                    if (balanced) {
                        parts.push(b('accounted for'));
                    } else {
                        parts.push(b(diff > 0 ? areaWithHectares(diff) + ' remaining' : areaWithHectares(-diff) + ' over'));
                    }
                }

                text = parts.join(' &middot; ');
                carried = entered || carried;
            } else if (p.type === 'extension') {
                if (carried || entered) { tone = 'text-blue-600'; strong = 'text-blue-800'; }
                if (carried) text = 'Parcel before extension: ' + b(areaWithHectares(carried));
                if (entered) {
                    text += (text ? ' &middot; ' : '') + 'after: ' + b(areaWithHectares(entered));
                    carried = entered;
                }
            } else if (p.type === 'change_of_purpose') {
                // A rename does not change any area; it just renames some of the parcels.
                if (entered) {
                    tone = 'text-blue-600';
                    strong = 'text-blue-800';
                    text = 'Parcels changing purpose: ' + b(areaWithHectares(entered));
                }
            }

            note.className = 'dx-size-note text-xs font-semibold ' + tone;
            note.innerHTML = text;
        });
    }

    function renderQuantities() {
        const box = document.getElementById('dx-quantities');

        // What each stage receives, walked forward through the plan. A Merger shows "1"
        // because it produces one parcel — but on its own that reads as "one file", when
        // the point of a merger is how MANY files it swallows. So the input count is
        // stated beside it.
        let incoming = state.sources.length;

        box.innerHTML = state.plan.map((p, idx) => {
            /**
             * A merger has no count to ask for: it swallows whatever it is given.
             *
             * An extension DOES — how many stripes of land are being added — but nearly
             * every extension is one stripe, so the box stays locked and a small button
             * opens it. An always-editable field on a stage that hands on ONE file
             * invites a number that reads like a file count; asking for the click makes
             * the several-stripe case deliberate, which is what it is.
             */
            // A count already above one is its own unlock: a resumed duplex rebuilds the
            // plan from the stage rows and loses the flag, and a locked box showing "3"
            // would be a number nobody could correct.
            const fixed = (p.type === 'merger')
                || (p.type === 'extension' && !p.stripesUnlocked && stripeCount(p) <= 1);
            const outputs = outputCountOf(p);

            // The number in the BOX is what the officer answers, which is not always
            // what the stage hands on: a merger shows how many files go in, an
            // extension how many stripes it covers, everything else its own output.
            const shown = p.type === 'merger' ? incoming
                : (p.type === 'extension' ? stripeCount(p) : outputs);

            // A Change of Purpose does NOT shrink the file count — it renames some and
            // passes the rest on — so its badge reads "39 -> 3" and needs saying out
            // loud, or it reads as thirty-nine plots becoming three.
            const hint = p.type === 'merger'
                ? `<b class="text-slate-600">${incoming}</b> file${incoming === 1 ? '' : 's'} merged into one parcel`
                : (p.type === 'extension'
                    ? 'How many stripes'
                    : (p.type === 'change_of_purpose'
                        ? `How many of the <b class="text-slate-600">${incoming}</b> take a new purpose &middot; the rest keep theirs and carry on`
                        : 'How many plots'));

            // A visible many-to-one (or one-to-many) badge, so the arithmetic of the
            // stage is readable without opening it.
            const flow = `
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-50 border border-slate-200 text-[11px] font-black text-slate-500">
                    <span class="dx-flow-in">${incoming}</span>
                    <i data-lucide="arrow-right" class="w-3 h-3 text-slate-300"></i>
                    <span class="dx-flow-out ${outputs === incoming ? 'text-slate-500' : 'text-blue-600'}">${outputs}</span>
                </span>`;

            const slots = sizeSlots(p, incoming);

            const row = `
                <div class="px-4 py-3 rounded-xl border border-slate-200">
                  <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="rank-badge rank-${Math.min(p.rank, 5)} text-center px-2 py-0.5 rounded-lg border text-xs font-black">${p.rank}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-700">${TYPES[p.type]}</p>
                            <p class="dx-qty-hint text-[11px] text-slate-400">${hint}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        ${flow}
                        ${p.type === 'extension' && !p.stripesUnlocked && stripeCount(p) <= 1 ? `
                            <button type="button" onclick="unlockStripes(${idx})"
                                title="More than one stripe of land is being added"
                                class="px-2.5 py-2 rounded-xl border border-dashed border-slate-300 text-[11px]
                                       font-bold text-slate-500 hover:border-blue-400 hover:text-blue-600
                                       transition inline-flex items-center gap-1 shrink-0">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Stripes
                            </button>` : ''}
                        <input type="number" min="1" max="${maxPlotsFor(p.type)}" ${fixed ? 'disabled' : ''}
                            value="${shown}"
                            data-idx="${idx}"
                            oninput="renderQuantities()"
                            class="dx-qty w-24 px-3 py-2 rounded-xl border border-slate-200 text-sm text-center ${fixed ? 'bg-slate-50 text-slate-400' : ''}">
                    </div>
                  </div>
                  ${sizeGrid(p, slots, idx)}
                </div>`;

            // A Change of Purpose renames some of what it receives and passes the rest
            // through, so the count going on to the next stage does not shrink.
            incoming = (p.type === 'change_of_purpose') ? incoming : outputs;

            return row;
        }).join('');

        refreshSizeNotes();
        if (window.lucide) lucide.createIcons();
    }

    /**
     * Carry revised quantities from the plan onto the stage rows already on screen, so
     * a corrected count re-renders the right number of plot cards. The new value is
     * persisted when that stage is saved — saveStage takes plot_count with the payload.
     */
    function applyPlanCountsToStages() {
        state.stages.forEach(stage => {
            // By RANK, not type: two Change of Purpose legs share a type but are
            // different stages with different counts. sqlsrv hands rank back as a
            // string, so both sides are cast.
            const entry = state.plan.find(p => Number(p.rank) === Number(stage.rank));
            if (!entry || !entry.count) return;

            if (Number(stage.plot_count) !== Number(entry.count)) {
                stage.plot_count = entry.count;

                // The stage's saved plots no longer match the new count; drop the stale
                // ones so the panel rebuilds from the sizes just entered.
                if (stage.payload && stage.payload.plots) {
                    stage.payload.plots = stage.payload.plots.slice(0, entry.count);
                }
            }
        });
    }

    function collectQuantities() {
        document.querySelectorAll('.dx-qty').forEach(input => {
            const entry = state.plan[Number(input.dataset.idx)];
            if (!entry) return;

            // The max attribute is not enforced on typing, and the grid builds one cell
            // per plot — clamp for real before 5000 cells are built.
            const max = maxPlotsFor(entry.type);
            if (Number(input.value) > max) input.value = max;

            entry.count = parseInt(input.value || '1', 10) || 1;
        });
    }

    // Re-rendering on every keystroke would steal focus, so the counts are read back
    // into the plan first and only the flow badges are refreshed.
    /**
     * Open an extension's stripe count for editing.
     *
     * The flag lives on the plan entry, not the DOM, so the box stays open through the
     * re-renders that follow every keystroke in it. Nothing else changes: the stage
     * still hands on one file however high the count goes.
     */
    window.unlockStripes = function (index) {
        const entry = state.plan[Number(index)];
        if (!entry) return;

        entry.stripesUnlocked = true;
        renderQuantities();

        const row = document.querySelectorAll('#dx-quantities > div')[Number(index)];
        const qty = row?.querySelector('.dx-qty');

        if (qty) { qty.focus(); qty.select(); }
    };

    window.renderQuantities = function () {
        // The counts are read back immediately — the plan must be right the instant
        // Continue is pressed — but repainting the grid waits for the frame.
        collectQuantities();
        scheduleQuantityRefresh('flows');
    };

    /** Update just the "N -> M" badges in place. */
    function refreshQuantityFlows() {
        let incoming = state.sources.length;

        document.querySelectorAll('#dx-quantities > div').forEach((row, i) => {
            const p = state.plan[i];
            if (!p) return;

            const outputs = outputCountOf(p);
            const badge = row.querySelector('.dx-flow-in');
            const outEl = row.querySelector('.dx-flow-out');
            const qty = row.querySelector('.dx-qty');

            if (qty && p.type === 'merger') qty.value = incoming;

            if (badge) badge.textContent = incoming;
            if (outEl) {
                outEl.textContent = outputs;
                outEl.classList.toggle('text-blue-600', outputs !== incoming);
                outEl.classList.toggle('text-slate-500', outputs === incoming);
            }

            // Grow or shrink the size boxes with the count, without disturbing the
            // quantity field the officer is typing in.
            const grid = row.querySelector('.dx-size-grid .grid');
            if (grid) {
                const want = sizeSlots(p, incoming);
                p.sizes = p.sizes || [];
                p.dims  = p.dims  || [];

                while (grid.children.length > want) grid.lastElementChild.remove();

                // Built off-document and attached once. Appending a hundred cells to a
                // live grid one at a time makes the browser reckon the layout a hundred
                // times, which is most of the pause when a count jumps from 1 to 82.
                if (grid.children.length < want) {
                    const batch = document.createDocumentFragment();
                    for (let j = grid.children.length; j < want; j++) {
                        const cell = document.createElement('div');
                        cell.innerHTML = dimCell(p, i, j);
                        batch.appendChild(cell);
                    }
                    grid.appendChild(batch);
                }

                const label = row.querySelector('.dx-size-grid p');
                if (label) {
                    label.innerHTML = 'Plot dimension' + (want === 1 ? '' : 's') + ' (m) &middot; '
                        + '<span class="text-slate-400">one box per side &mdash; use + for a parcel with more than two</span>';
                }
            }

            const hint = row.querySelector('.dx-qty-hint');
            if (hint && p.type === 'merger') {
                hint.innerHTML = '<b class="text-slate-600">' + incoming + '</b> file'
                    + (incoming === 1 ? '' : 's') + ' merged into one parcel';
            }

            incoming = (p.type === 'change_of_purpose') ? incoming : outputs;
        });

        refreshSizeNotes();
    }

    // ---------------------------------------------------------------- step 3
    /**
     * What a stage consumes, derived from what is SAVED rather than from the last
     * save in this session. `state.carry` only knows about the stage just submitted,
     * so on resume - or on jumping back to an earlier stage - it is empty, which left
     * the input line blank and the Change of Purpose "Apply to" list with nothing in
     * it. The stage rows carry their holding numbers, so read them from there.
     */
    function carryFor(index) {
        if (index <= 0) return state.sources.map(s => s.fileNumber);

        const previous = state.stages[index - 1];
        const holdings = (previous?.files || [])
            .slice()
            .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
            .map(f => f.holding_no)
            .filter(Boolean);

        // Fall back to the in-session value for a stage saved a moment ago whose rows
        // have not been re-read yet.
        return holdings.length ? holdings : state.carry;
    }

    /** Re-read the duplex so stage rows (and their holding numbers) are current. */
    async function refreshStages() {
        if (!state.duplex) return;
        const res = await fetch('{{ url('duplex-parcel-update') }}/' + state.duplex.id, {
            headers: { 'Accept': 'application/json' }
        }).then(r => r.json());
        if (res.success) {
            state.stages = (res.data?.stage_rows || []).slice().sort((a, b) => a.rank - b.rank);
            syncPlanSizesFromStages();
        }
    }

    /**
     * Read the saved stage payloads back onto the step-2 plan.
     *
     * The plan and the stage rows hold the same measurements, and step 2 owns the copy
     * the officer edits. Without this the two drift the moment either side is written:
     * a RESUMED duplex opened step 2 with empty boxes even though every stage carried
     * its sizes, and a size corrected on a stage panel was overwritten by the stale
     * plan the next time step 2 was rendered.
     *
     * Called after every read of the stage rows, so "what step 2 holds" is always the
     * most recently saved answer unless the officer has typed over it since.
     */
    function syncPlanSizesFromStages() {
        state.stages.forEach(stage => {
            // By RANK, not type - two Change of Purpose legs share a type.
            const entry = state.plan.find(p => Number(p.rank) === Number(stage.rank));
            const plots = (stage.payload && stage.payload.plots) || [];

            if (!entry || !plots.length) return;

            entry.sizes = entry.sizes || [];
            entry.dims  = entry.dims  || [];

            // FILLS BLANKS ONLY, field by field. The plan is the live copy - both step 2
            // and the step-3 cards write to it - so anything already in it is newer than
            // the payload this is reading. Overwriting wholesale did two things wrong at
            // once: a stage saved with a row of nulls (the usual state of one the officer
            // clicked past) wiped measurements typed on step 2, and saving ANY stage
            // rolled every other stage's unsaved edits back to what was last stored.
            plots.forEach((pl, i) => {
                const blank = v => (v ?? '') === '';

                if (blank(entry.sizes[i]) && !blank(pl.size)) entry.sizes[i] = pl.size;

                if (!dimsText(entry.dims[i])) {
                    const saved = pl.dimensions
                        ?? ((pl.length != null || pl.width != null) ? [pl.length, pl.width] : null);

                    if (dimsText(saved)) entry.dims[i] = normaliseTerms(saved);
                }
            });
        });
    }

    /**
     * Jump straight to a stage from the track. Any stage already captured is
     * reachable, plus the one being worked on; stages further ahead stay locked
     * because their input does not exist yet.
     */
    window.goToStage = function (index) {
        if (index > state.stageIndex && state.stages[index]?.status !== 'done') return;
        state.stageIndex = index;
        renderStageTrack();
        renderStagePanel();
        showStep(3);
    };

    function renderStageTrack() {
        const track = document.getElementById('dx-stage-track');

        track.innerHTML = state.stages.map((s, i) => {
            const cls = i < state.stageIndex ? 'done' : (i === state.stageIndex ? 'current' : 'locked');
            const arrow = i < state.stages.length - 1
                ? '<i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>' : '';
            const tick = (i < state.stageIndex || s.status === 'done')
                ? '<i data-lucide="check" class="w-3.5 h-3.5"></i>'
                : `<span class="text-[10px] font-black">${s.rank}</span>`;

            // Reachable = already captured, or the stage in hand. Anything beyond that
            // has no input yet, so it stays inert rather than pretending to be a link.
            const reachable = i <= state.stageIndex || s.status === 'done';
            const tag = reachable ? 'button' : 'span';
            const attrs = reachable
                ? `type="button" onclick="goToStage(${i})" title="Go to ${stageSeriesLabel(s)}" class="stage-pill ${cls} clickable`
                : `class="stage-pill ${cls}`;

            return `
                <${tag} ${attrs} inline-flex items-center gap-2 pl-2 pr-3.5 py-2 rounded-xl text-xs font-bold">
                    <span class="w-5 h-5 rounded-lg bg-white/70 flex items-center justify-center shrink-0">${tick}</span>
                    ${stageSeriesLabel(s)}
                </${tag}>${arrow}`;
        }).join('');

        icons();
    }

    function renderStagePanel() {
        const stage = state.stages[state.stageIndex];
        const panel = document.getElementById('dx-stage-panel');

        if (!stage) { panel.innerHTML = ''; return; }

        const incoming = carryFor(state.stageIndex);
        const inputLine = incoming.join(', ');

        const inputLabel = state.stageIndex === 0 ? 'source file(s)' : 'holding, from the previous stage';
        const count = stage.plot_count || 1;

        // Whatever this stage was saved with, so going back to edit shows the real
        // entries instead of an empty form.
        const saved = stage.payload || {};

        // Sizes captured on step 2 seed the stage panel, so the officer is not asked
        // for the same number twice. Anything saved on the stage itself wins.
        /**
         * What each plot card opens with.
         *
         * Merged FIELD BY FIELD, not array by array. Taking the stage's saved plots
         * whole as soon as it had any meant a stage saved with empty measurements - the
         * usual state of a stage the officer clicked past - permanently beat the
         * dimensions they had just typed on step 2, and the card opened blank.
         *
         * Step 2 leads because it is the copy the officer edits, and it is kept current
         * from the saved stages by syncPlanSizesFromStages(); the stage's own payload
         * fills anything step 2 has nothing to say about - the holder, the plot number,
         * and every field of a stage captured before step 2 was visited.
         */
        const plan = state.plan.find(p => Number(p.rank) === Number(stage.rank)) || {};
        const planned = plan.sizes || [];
        const plannedDims = plan.dims || [];

        const filled = v => v !== null && v !== undefined && v !== '';
        const slotCount = Math.max((saved.plots || []).length, planned.length, plannedDims.length);

        const savedPlots = Array.from({ length: slotCount }).map((_, i) => {
            const kept = (saved.plots || [])[i] || {};
            const dim  = plannedDims[i];

            const plannedTerms = dimsText(dim) ? normaliseTerms(dim) : null;
            const keptTerms = kept.dimensions
                ?? ((kept.length != null || kept.width != null) ? [kept.length, kept.width] : null);

            return {
                ...kept,
                size:       filled(planned[i]) ? planned[i] : (kept.size ?? null),
                dimensions: plannedTerms ?? keptTerms,
            };
        });
        const savedApplies = saved.applies_to || [];
        const esc = v => (v === null || v === undefined) ? '' : String(v).replace(/"/g, '&quot;');

        // A file's registered holder, taken from the record picked on step 1. Falls back
        // to the applicant for a holding number, which has no registry row of its own.
        const applicantName = document.getElementById('dx-applicant')?.value || '';
        const holderOf = fileNo => {
            const src = state.sources.find(x => x.fileNumber === fileNo);
            return (src && src.title) ? src.title : applicantName;
        };


        /**
         * The Length x Width pair on a step-3 plot card, and the area it derives.
         *
         * The dimensions are what the officer reads off the survey plan; the m² figure
         * is carried in a hidden input because that is what collectStagePayload sends
         * and what every downstream reader - the stage payload, the memo, the running
         * totals - works in. A stage captured before dimensions existed opens with its
         * area in that hidden field and both boxes blank, and keeps that area until a
         * dimension is typed.
         */
        const plotSizeBlock = (i, plot) => {
            const terms = normaliseTerms(plot?.dimensions
                ?? (plot && (plot.length != null || plot.width != null)
                    ? [plot.length, plot.width]
                    : null));

            const box = (t, value) => `
                <div class="relative">
                    <input type="number" step="any" min="0" placeholder="${t + 1}" aria-label="Side ${t + 1} in metres"
                           value="${esc(value)}" data-slot="${i}" data-term="${t}"
                           oninput="onPlotDimInput(this)"
                           class="dx-plot-dim w-14 px-1.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center">
                    ${terms.length > 1 ? `
                        <button type="button" onclick="removePlotDimTerm(${i}, ${t})" title="Remove this side"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-white border border-slate-200
                                   text-slate-400 hover:text-rose-600 hover:border-rose-200 text-[9px] font-black
                                   leading-none flex items-center justify-center">&times;</button>` : ''}
                </div>`;

            return `
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 mt-3">
                Dimensions (m)</label>
            <div class="dx-plot-dims flex flex-wrap items-center gap-1.5" data-slot="${i}">
                ${terms.map((v, t) => box(t, v)).join('<span class="text-slate-400 text-xs font-black shrink-0">×</span>')}
                <button type="button" onclick="addPlotDimTerm(${i})" title="Add another side"
                    class="w-7 h-9 rounded-lg border border-dashed border-slate-300 text-slate-400
                           hover:border-blue-400 hover:text-blue-600 transition text-sm font-black shrink-0">+</button>
            </div>
            <input type="hidden" class="dx-plot-size" data-slot="${i}" value="${esc(plot?.size)}">
            <p class="dx-plot-area text-[10px] font-bold text-slate-500 text-center mt-1" data-slot="${i}"></p>`;
        };

        // How many holding numbers this stage will mint. Needed before the template,
        // because it decides whether the Tracking ID is optional.
        const outputs = (stage.type === 'merger' || stage.type === 'extension') ? 1 : count;

        let body = '';

        /**
         * "Apply the first card to all" for the stage panel, where a 39-plot
         * subdivision renders 39 size+holder cards. Rendered only when there is more
         * than one card to fill.
         */
        const plotApplyAll = (n, label) => n > 1
            ? `<div class="flex items-center gap-2 mb-3">
                   <button type="button" onclick="applyPlotFieldToAll('size')"
                       class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-600
                              hover:text-blue-700 px-2.5 py-1.5 rounded-lg hover:bg-blue-50 transition">
                       <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                       Apply ${label} 1 size to all ${n}
                   </button>
                   <button type="button" onclick="applyPlotFieldToAll('holder')"
                       class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-600
                              hover:text-blue-700 px-2.5 py-1.5 rounded-lg hover:bg-blue-50 transition">
                       <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                       Apply ${label} 1 holder to all ${n}
                   </button>
               </div>`
            : '';

        // Rows captured on step 1 for a first-leg Change of Purpose. Their presence is
        // what tells this panel the question has already been answered per file.
        const seededCop = Array.isArray(saved.cop_rows) ? saved.cop_rows : [];

        // The new numbers this stage has minted, if it has been saved once already,
        // keyed by the file each belongs to.
        const seededFiles = (Array.isArray(saved.cop_rows) ? saved.cop_rows : []).map(r => r.file_no);
        const heldFor = holdingByFile(stage, incoming, seededFiles);

        if (stage.type === 'change_of_purpose' && seededCop.length) {
            // Answered on step 1, against the real source files. The panel reads it
            // back rather than asking again — the officer may still correct a purpose,
            // but WHICH files change was settled when the plan was built, because the
            // holding numbers were minted from that answer.
            body = `
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                        <p class="text-xs font-bold text-slate-600">
                            Captured when the plan was built — ${seededCop.length}
                            ${seededCop.length === 1 ? 'file changes' : 'files change'} purpose.
                        </p>
                    </div>

                    <div class="grid grid-cols-12 gap-2 mb-1.5 px-1">
                        <span class="col-span-4 text-[10px] font-black uppercase tracking-wider text-slate-400">File Number</span>
                        <span class="col-span-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Current</span>
                        <span class="col-span-3 text-[10px] font-black uppercase tracking-wider text-slate-400">New Purpose</span>
                        <span class="col-span-3 text-[10px] font-black uppercase tracking-wider text-slate-400">Holding No.</span>
                    </div>

                    <div class="space-y-2">
                        ${seededCop.map(row => {
                            const code = row.current_land_use || landUseCode(row.file_no);
                            const current = code
                                ? (LAND_USE_LABELS[code] ? LAND_USE_LABELS[code] + ' (' + code + ')' : code)
                                : 'Not carried by this number';
                            // The holding number this file will carry until the Land
                            // step. Minted when the stage is saved, so before that it
                            // says so rather than showing a blank box.
                            const held = heldFor[row.file_no];

                            return `
                            <div class="dx-stage-cop-row grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-4">
                                    <input type="text" readonly value="${esc(row.file_no)}"
                                        class="dx-cop-file w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-black holding-no">
                                </div>
                                <div class="col-span-2">
                                    <input type="text" readonly value="${esc(current)}"
                                        class="dx-cop-current w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-600">
                                </div>
                                <div class="col-span-3">
                                    <select class="dx-cop-new w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold">
                                        <option value="">— New purpose —</option>
                                        ${LAND_USE_OPTIONS.map(c => `
                                            <option value="${c}" ${c === row.new_land_use ? 'selected' : ''}>
                                                ${LAND_USE_LABELS[c] || c} (${c})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <input type="text" readonly value="${esc(held || '')}"
                                        placeholder="reserving…"
                                        data-file="${esc(row.file_no)}" ${held ? 'data-issued="1"' : ''}
                                        class="dx-holding-preview w-full px-3 py-2 rounded-xl border text-xs font-black holding-no
                                               ${held ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                                                      : 'border-slate-200 bg-slate-50 text-slate-400'}">
                                </div>
                            </div>`;
                        }).join('')}
                    </div>

                    ${(() => {
                        const changing  = seededCop.map(r => r.file_no);
                        const untouched = incoming.filter(f => !changing.includes(f));
                        return untouched.length
                            ? `<p class="text-[11px] text-slate-500 mt-3 leading-relaxed">
                                   <b class="holding-no">${untouched.join(', ')}</b>
                                   ${untouched.length === 1 ? 'keeps its' : 'keep their'} current purpose and
                                   file number, and ${untouched.length === 1 ? 'travels' : 'travel'} on to the
                                   next update untouched.</p>`
                            : `<p class="text-[11px] text-slate-500 mt-3">Every file in this duplex is changing purpose.</p>`;
                    })()}

                    {{-- Size and holder for the files being changed. Every other stage
                         records these, and a renamed parcel has a size like any other. --}}
                    <div id="dx-cop-apply" class="mt-5"></div>
                    <div id="dx-cop-plots" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>`;
        } else if (stage.type === 'change_of_purpose') {
            const options = incoming;

            // The purpose being changed FROM is whatever the earlier legs left these
            // parcels on — see landUseEnteringStage(). A second-series CoP sitting
            // behind "COM -> RES, Subdivision" receives RES plots, not the COM the
            // duplex started from.
            const currentLandUse = landUseLabelEnteringStage(state.stageIndex);
            body = `
                <div class="mb-5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Apply to</label>
                    <div class="flex flex-wrap gap-2">
                        ${options.map((o, i) => `
                            <label class="applies-chip inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-semibold cursor-pointer">
                                <input type="checkbox" class="dx-applies" value="${o}" onchange="onAppliesChange()"
                                       ${savedApplies.length ? (savedApplies.includes(o) ? 'checked' : '') : (i < count ? 'checked' : '')}>
                                <span class="holding-no">${o}</span>
                            </label>`).join('')}
                    </div>

                    {{-- Says what will happen to THESE files, by name. The generic version
                         ("files you leave unticked pass through untouched") meant nothing to
                         anyone who had not written it. --}}
                    <p id="dx-applies-explain" class="text-[11px] text-slate-500 mt-2 leading-relaxed"></p>

                    {{-- Size and holder for the files being changed. Every other stage
                         records these, and a renamed parcel has a size like any other. --}}
                    <div id="dx-cop-apply" class="mt-4"></div>
                    <div id="dx-cop-plots" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Current land use</label>
                        <input type="text" id="dx-current-landuse" readonly value="${currentLandUse}"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">New land use *</label>
                        {{-- Pre-selected from what was answered on step 1, and from the
                             stage's own payload when coming back to it. Without this the
                             box reset to "Select" every time the panel was reopened. --}}
                        <select id="dx-new-landuse" onchange="onAppliesChange()"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                            <option value="">— Select —</option>
                            ${LAND_USE_OPTIONS.map(c => `
                                <option value="${c}" ${c === (saved.new_land_use || '') ? 'selected' : ''}>
                                    ${LAND_USE_LABELS[c] || c} (${c})</option>`).join('')}
                        </select>
                    </div>
                </div>`;
        } else if (stage.type === 'merger') {
            body = `
                <p class="text-sm text-slate-600 mb-4">
                    These files merge into one parcel. Give each its size as it stands today.
                </p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    ${incoming.map((f, i) => `
                        <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50">
                            <p class="text-[11px] font-black text-slate-600 holding-no mb-3 truncate" title="${f}">${f}</p>
                            ${plotSizeBlock(i, savedPlots[i])}
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 mt-3">Holder</label>
                            <input type="text" class="dx-plot-holder w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                   placeholder="Name" value="${esc(savedPlots[i]?.holder ?? holderOf(f))}">
                        </div>`).join('')}
                </div>`;
        } else {
            // A stripe is a piece of land being ADDED, not a parcel being created —
            // several of them still make one extended parcel, and one file.
            const label = stage.type === 'extension'
                ? (count > 1 ? 'Stripe' : 'Adjusted parcel')
                : 'Plot';

            body = `
                <p class="text-sm text-slate-600 mb-4">
                    ${stage.type === 'extension'
                        ? (count > 1
                            ? 'Measure each stripe of land being added. They all go onto the one extended '
                              + 'parcel, which replaces the incoming file — <b>one file number, not ' + count + '</b>.'
                            : 'The extended parcel replaces the incoming file.')
                        : 'Size each plot, and name its holder. The holder defaults to the applicant — change it where a plot goes to someone else.'}
                </p>
                ${plotApplyAll(count, label)}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    ${Array.from({ length: count }).map((_, i) => `
                        <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50">
                            <p class="text-[11px] font-black text-slate-600 mb-3">${label} ${i + 1}</p>
                            ${plotSizeBlock(i, savedPlots[i])}
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 mt-3">Holder</label>
                            <input type="text" class="dx-plot-holder w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                   placeholder="Name" value="${esc(savedPlots[i]?.holder ?? document.getElementById('dx-applicant').value ?? '')}">
                        </div>`).join('')}
                </div>`;
        }

        panel.innerHTML = `
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="rank-badge rank-${Math.min(stage.rank, 5)} w-8 h-8 rounded-xl border flex items-center justify-center text-xs font-black shrink-0">${stage.rank}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-slate-800 leading-tight">${stageSeriesLabel(stage)}</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                Stage ${state.stageIndex + 1} of ${state.stages.length}
                            </p>
                        </div>
                    </div>
                    <div class="text-right min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Input · ${inputLabel}</p>
                        <p class="holding-no text-xs font-black text-slate-700 truncate max-w-xs">${inputLine || '—'}</p>
                    </div>
                </div>

                <div class="p-6">${body}</div>

                <div class="px-6 py-3 border-t border-slate-100 bg-slate-50/60 flex items-center gap-2 text-[11px] text-slate-500">
                    <i data-lucide="corner-down-right" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span id="dx-stage-produces">Produces <strong class="text-slate-700">${outputs}</strong> holding number${outputs === 1 ? '' : 's'} for the next stage.${
                        stage.type === 'extension' && count > 1
                            ? ` The ${count} stripes are measurements on that one parcel, not files of their own.`
                            : ''
                    }</span>
                </div>
            </div>`;

        if (stage.type === 'change_of_purpose') {
            // Two shapes of CoP panel: the per-file table seeded from step 1, and the
            // chip picker used when the CoP runs on a later stage's holding numbers.
            document.querySelector('#dx-stage-panel .dx-stage-cop-row')
                ? onSeededCopChange()
                : onAppliesChange();
        }

        icons();
    }

    /**
     * The holding numbers a stage MINTED, in order — the new number each changed file
     * receives. Carried files are excluded: they keep the number they arrived with, so
     * they were never issued one.
     *
     * Populated once the stage is saved (saveStage mints them and refreshStages re-reads
     * the rows), and empty before that.
     */
    function mintedHoldings(stage) {
        return (stage?.files || [])
            .filter(f => f.role !== 'carried' && f.holding_no)
            .slice()
            .sort((a, b) => Number(a.sequence) - Number(b.sequence))
            .map(f => f.holding_no);
    }

    /**
     * Fill in the holding numbers a stage has not issued yet.
     *
     * The officer asked to see them while filling the stage in, not after saving. They
     * are computed by the server through the SAME method that issues them
     * (previewHoldingNumbers / allocateHoldingNumbers share one implementation), so the
     * preview cannot drift from what is actually minted.
     *
     * Only ever writes into boxes that are still empty, so a real number already on
     * screen is never overwritten by a projection.
     */
    async function fillHoldingPreview(changingFiles) {
        if (!state.duplex) return;

        const stage = state.stages[state.stageIndex];
        if (!stage) return;

        const files = (changingFiles || []).filter(Boolean);
        const targets = [...document.querySelectorAll('#dx-stage-panel .dx-holding-preview')]
            .filter(el => !el.dataset.issued);

        if (!files.length || !targets.length) return;

        let numbers = [];
        try {
            const url = '{{ url('duplex-parcel-update') }}/' + state.duplex.id
                + '/stages/' + stage.id + '/holding-preview?count=' + files.length;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } }).then(r => r.json());
            numbers = res.numbers || [];
        } catch (e) {
            return;   // a failed preview just leaves the placeholder text
        }

        if (!numbers.length) return;

        // Same rule the server uses when it issues them: walk the INPUT list and hand
        // the next number to each file that is changing.
        const incomingList = state.stageIndex === 0
            ? state.sources.map(x => x.fileNumber)
            : (state.carry || []);
        const changing = new Set(files);
        const map = {};
        let i = 0;
        incomingList.forEach(f => { if (changing.has(f)) map[f] = numbers[i++]; });

        // A file not in the input list (a later stage working off holdings) still gets
        // one, in the order it was listed.
        files.forEach(f => { if (!map[f]) map[f] = numbers[i++]; });

        targets.forEach(el => {
            const n = map[el.dataset.file];
            if (!n) return;
            el.value = n;
            el.classList.remove('border-slate-200', 'bg-slate-50', 'text-slate-400');
            el.classList.add('border-indigo-200', 'bg-indigo-50', 'text-indigo-600', 'italic');
            el.title = 'Reserved for this file — issued when the stage is saved';
        });

        document.querySelectorAll('#dx-stage-panel .dx-holding-line').forEach(el => {
            if (el.dataset.issued) return;
            const n = map[el.dataset.file];
            if (!n) return;
            el.innerHTML = '&rarr; ' + n;
            el.className = 'dx-holding-line text-[10px] mb-3 truncate text-indigo-600 italic holding-no';
        });
    }

    /**
     * Which holding number each changed file received, keyed by file number.
     *
     * saveStage() walks the INPUT list in order and hands the next holding number to
     * each file that is changing — so the numbers follow input order, not the order the
     * officer happened to list the rows in. Indexing them by row would mislabel the
     * column the moment those two differ.
     */
    function holdingByFile(stage, incomingList, changingFiles) {
        const minted = mintedHoldings(stage);
        const changing = new Set(changingFiles || []);
        const map = {};
        let i = 0;

        (incomingList || []).forEach(f => {
            if (changing.has(f)) map[f] = minted[i++];
        });

        return map;
    }

    /**
     * The step-1 table's equivalent of onAppliesChange: which files change is already
     * settled, so this only keeps the size/holder cards and the "produces" line in
     * step with the rows on screen.
     */
    window.onSeededCopChange = function () {
        const panel = document.getElementById('dx-stage-panel');
        if (!panel) return;

        const rows  = [...panel.querySelectorAll('.dx-stage-cop-row')];
        const files = rows.map(r => r.querySelector('.dx-cop-file')?.value).filter(Boolean);
        if (!files.length) return;

        renderCopPlots(files);
        fillHoldingPreview(files);

        // A Change of Purpose hands on EVERY file it received, not just the ones it
        // changed — the untouched files still have to reach the Land step.
        const stage    = state.stages[state.stageIndex];
        const incoming = (state.stageIndex === 0 ? state.sources.map(s => s.fileNumber) : state.carry) || [];
        const passing  = Math.max(incoming.length, files.length);
        const kept     = passing - files.length;

        const produces = document.getElementById('dx-stage-produces');
        if (produces) {
            produces.innerHTML = 'Mints <strong class="text-slate-700">' + files.length + '</strong> new file number'
                + (files.length === 1 ? '' : 's')
                + (kept > 0
                    ? '. The other <strong class="text-slate-700">' + kept + '</strong> keep theirs, so '
                      + '<strong class="text-slate-700">' + passing + '</strong> files go on to the next step.'
                    : '.');
        }

        return stage;
    };

    /** Land-use codes as they appear in file numbers, mapped to what officers call them. */
    const LAND_USE_LABELS = {
        RES: 'Residential', COM: 'Commercial', IND: 'Industrial',
        AGR: 'Agricultural', AG: 'Agricultural', AGRIC: 'Agricultural',
        MIX: 'Mixed Use', MIXED: 'Mixed Use',
    };

    // Segments that sit BEFORE the land use in a file number rather than being one.
    const FILE_NO_PREFIXES = ['CON', 'ST', 'SLTR', 'KN'];

    // The land uses a parcel can be changed TO. One list, used by the step-1 table
    // and the step-3 panel, so the two can never drift apart.
    const LAND_USE_OPTIONS = ['RES', 'COM', 'IND', 'AGR', 'MIX'];

    // KANGIS registry prefixes. Mirrors App\Support\FileNumberLandUse.
    const KANGIS_PREFIXES = ['MLKNGP', 'KNGP', 'KNML', 'MLKN'];

    /**
     * A KANGIS number is an identity, not a purpose, and carries no land use. It has
     * no "-" to split on either, so without this the parser below hands the whole
     * number back as the land-use code and "MLKN 3235" is shown as a purpose.
     */
    function isKangisNumber(fileNo) {
        const first = String(fileNo || '').split(',')[0].trim().toUpperCase();
        if (!first) return false;

        return KANGIS_PREFIXES.some(p => {
            if (!first.startsWith(p)) return false;
            const rest = first.slice(p.length).trim();
            return rest !== '' && /^\d+$/.test(rest);
        });
    }

    /**
     * The land-use code carried by a file number.
     *
     *   CON-AG-1995-15    -> AG      (CON is a prefix, not a land use)
     *   ST-RES-2025-0001  -> RES
     *   RES-1994-762      -> RES
     *
     * Returns '' for anything that is not a registry file number - a holding number
     * like DPX-2026-0006-H01 has no land use, and reading "DPX" off it was exactly the
     * bug this replaces.
     */
    function landUseCode(fileNo) {
        const first = String(fileNo || '').split(',')[0].trim().toUpperCase();
        if (!first || first.startsWith('DPX-') || isKangisNumber(first)) return '';

        const parts = first.split('-').filter(Boolean);
        if (!parts.length) return '';

        if (FILE_NO_PREFIXES.includes(parts[0]) && parts[1] && isNaN(parts[1])) {
            return parts[1];
        }

        return isNaN(parts[0]) ? parts[0] : '';
    }

    /** "Agricultural (AG)" - the code alone means nothing to most readers. */
    function landUseLabel(fileNo) {
        const code = landUseCode(fileNo);
        if (!code) return '';
        const name = LAND_USE_LABELS[code];
        return name ? name + ' (' + code + ')' : code;
    }

    /**
     * The land use the parcels ARRIVING at a stage actually carry.
     *
     * Not the source file's — that is only true of the first stage. Take the plan
     * "Change of Purpose (COM → RES) → Subdivision → Change of Purpose (2nd series)":
     * the first leg makes the parcel Residential, and a Subdivision hands that purpose
     * down to every plot it cuts, so the second leg receives RES files. Reading the
     * purpose off the source file showed "Commercial (COM)" on that second leg — the
     * one thing those plots are not — and the explanation under it then read "H85, H86
     * and H87 become Commercial", describing a change from a purpose they had already
     * left.
     *
     * So the earlier stages are walked in rank order and the last purpose actually set
     * wins. Only a Change of Purpose sets one; Subdivision, Merger, Extension and
     * Separation all carry it forward untouched. Where an earlier leg sent its files
     * to DIFFERENT purposes there is no single answer, so none is given rather than
     * one that is wrong for most of them.
     */
    function landUseEnteringStage(index) {
        let code = landUseCode(state.sources[0]?.fileNumber || '');

        for (let i = 0; i < index; i++) {
            const earlier = state.stages[i];
            if (!earlier || earlier.type !== 'change_of_purpose') continue;

            // What that leg was saved with, or — before it has been saved — what step 1
            // planned for it. Matched by RANK: two CoP legs share a type.
            const payload = earlier.payload || {};
            const planned = state.plan.find(p => Number(p.rank) === Number(earlier.rank)) || {};

            const rows = Array.isArray(payload.cop_rows) ? payload.cop_rows
                : (Array.isArray(planned.cop_rows) ? planned.cop_rows : []);

            const purposes = rows.length
                ? rows.map(r => r.new_land_use)
                : [payload.new_land_use || planned.new_land_use];

            const distinct = [...new Set(
                purposes.map(v => String(v || '').trim().toUpperCase()).filter(Boolean)
            )];

            if (distinct.length === 1) code = distinct[0];
            else if (distinct.length > 1) code = '';
        }

        return code;
    }

    /** The same, ready to print: "Residential (RES)". */
    function landUseLabelEnteringStage(index) {
        const code = landUseEnteringStage(index);
        if (!code) return '';
        return (LAND_USE_LABELS[code] ? LAND_USE_LABELS[code] + ' (' + code + ')' : code);
    }

    /**
     * Restyle the chips and rewrite the explanation whenever the selection or the new
     * land use changes. Naming the actual files is the whole point: "H01 and H02 become
     * Commercial, H03 stays Industrial" is understood immediately, and no amount of
     * rewording the abstract version got close.
     */
    window.onAppliesChange = function () {
        const panel = document.getElementById('dx-stage-panel');
        if (!panel) return;

        const boxes = [...panel.querySelectorAll('.dx-applies')];
        if (!boxes.length) return;

        // Unticked chips read as inactive, so the selection is legible at a glance.
        boxes.forEach(box => {
            const chip = box.closest('.applies-chip');
            if (chip) chip.classList.toggle('is-off', !box.checked);
        });

        const on  = boxes.filter(b => b.checked).map(b => b.value);
        const off = boxes.filter(b => !b.checked).map(b => b.value);

        const shortName = v => String(v).split('-').pop();          // ...-H01 -> H01
        const list = arr => arr.length <= 1
            ? (arr[0] || '')
            : arr.slice(0, -1).join(', ') + ' and ' + arr[arr.length - 1];

        const select  = document.getElementById('dx-new-landuse');
        const newUse  = select && select.value
            ? select.options[select.selectedIndex].textContent.trim()
            : 'the new land use';

        const currentUse = document.getElementById('dx-current-landuse')?.value.trim() || 'their current land use';

        // The stage that produced these files, so the sentence can say where the file
        // numbers they keep came from.
        const previous = state.stages[state.stageIndex - 1];
        const from = previous ? 'the ' + TYPES[previous.type] : 'the previous stage';

        const out = document.getElementById('dx-applies-explain');
        if (!out) return;

        if (!on.length) {
            out.innerHTML = '<span class="text-amber-600 font-bold">Nothing ticked.</span> '
                + 'Tick the files whose land use is changing — at least one is needed.';
            return;
        }

        let text = '<strong>' + list(on.map(shortName)) + '</strong> become ' + newUse + '.';

        text += ' Their current numbers are decommissioned and replaced.';

        if (off.length) {
            text += ' <strong>' + list(off.map(shortName)) + '</strong> stay ' + currentUse
                 + ' and keep the file numbers ' + from + ' gave them.';
        }

        text += ' All ' + boxes.length + ' still get real file numbers at the Land step.';
        out.innerHTML = text;

        renderCopPlots(on);
        fillHoldingPreview(on);

        // A Change of Purpose hands on EVERY file it received, not just the ones it
        // changed - the untouched files still have to reach the Land step. Saying
        // "produces 2" when 5 leave the stage is what made the Done step unreadable.
        const produces = document.getElementById('dx-stage-produces');
        if (produces) {
            produces.innerHTML = 'Mints <strong class="text-slate-700">' + on.length + '</strong> new file number'
                + (on.length === 1 ? '' : 's') + '. The other <strong class="text-slate-700">' + off.length
                + '</strong> keep theirs, so <strong class="text-slate-700">' + boxes.length
                + '</strong> file' + (boxes.length === 1 ? '' : 's') + ' go on to the next step.';
        }
    };

    /**
     * Size and holder for each file a Change of Purpose renames.
     *
     * Rebuilt from the ticked chips so it always matches the selection, and existing
     * values are carried across a re-tick rather than being wiped.
     */
    function renderCopPlots(selected) {
        const box = document.getElementById('dx-cop-plots');
        if (!box) return;

        // Keep whatever has already been typed, keyed by the file it belongs to.
        const kept = {};
        box.querySelectorAll('[data-holding]').forEach(card => {
            kept[card.dataset.holding] = {
                holder: card.querySelector('.dx-plot-holder')?.value || '',
            };
        });

        const stage = state.stages[state.stageIndex];
        const saved = (stage?.payload?.plots) || [];
        const applicant = document.getElementById('dx-applicant')?.value || '';

        // Input order for this stage: the source files at rank 1, the previous stage's
        // holdings after that — the same list saveStage() walks.
        const incomingList = state.stageIndex === 0
            ? state.sources.map(x => x.fileNumber)
            : (state.carry || []);
        const heldFor = holdingByFile(stage, incomingList, selected);

        box.innerHTML = selected.map((h, i) => {
            const prior = kept[h] || {};
            const holder = prior.holder || saved[i]?.holder || applicant;

            // What this file becomes. The card title is what it arrives as; a renamed
            // file also gets a NEW holding number, and the officer needs to see it.
            const becomes = heldFor[h];

            return `
                <div data-holding="${h}" class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50">
                    <p class="text-[11px] font-black text-slate-600 holding-no mb-1 truncate" title="${h}">${h}</p>
                    <p class="dx-holding-line text-[10px] mb-3 truncate ${becomes ? 'text-indigo-700 font-black holding-no' : 'text-slate-400 italic'}"
                       data-file="${h}" ${becomes ? 'data-issued="1"' : ''} title="${becomes || ''}">
                        ${becomes ? '&rarr; ' + becomes : '&rarr; reserving…'}</p>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Holder</label>
                    <input type="text" class="dx-plot-holder w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                           placeholder="Name" value="${String(holder).replace(/"/g, '&quot;')}">
                </div>`;
        }).join('');

        // Same copy-down gesture as the other stage panels, sized to the selection.
        const apply = document.getElementById('dx-cop-apply');
        if (apply) {
            apply.innerHTML = selected.length > 1
                ? `<div class="flex items-center gap-2 mb-3">
                       <button type="button" onclick="applyPlotFieldToAll('holder')"
                           class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-600
                                  hover:text-blue-700 px-2.5 py-1.5 rounded-lg hover:bg-blue-50 transition">
                           <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                           Apply first holder to all ${selected.length}
                       </button>
                   </div>`
                : '';
        }

        // Areas for the cards that opened with a size already on them.
        //
        // Scoped to this grid. It read `panel`, which is never declared in here — the
        // CoP panel's container is `box` (#dx-cop-plots) — so the line threw a
        // ReferenceError every time a Change of Purpose stage was painted. Reopening a
        // saved duplex goes straight to step 3, which paints every stage, so editing
        // one that carried a CoP died on the Continue button with nothing said.
        box.querySelectorAll('.dx-plot-dim').forEach(syncPlotArea);

        if (window.lucide) lucide.createIcons();
    }

    /**
     * Copy the first plot card's size or holder down the rest of the stage panel.
     * `field` is 'size' or 'holder'.
     */
    window.applyPlotFieldToAll = function (field) {
        const panel = document.getElementById('dx-stage-panel');
        if (!panel) return;

        // 'size' copies the whole DIMENSION CHAIN and the area with it. Copying the
        // sides alone would leave every other card showing an area its own boxes cannot
        // account for, which is exactly the case for a parcel measured off the plan.
        if (field === 'size') {
            const slots = panel.querySelectorAll('.dx-plot-dims').length;
            if (slots < 2) return;

            const first = plotTermsOf(panel, 0)
                .map(v => String(v ?? '').trim())
                .filter(v => v !== '');

            if (!first.length) {
                return toast('info', 'Nothing to copy',
                    'Enter the first plot\'s dimensions, then apply them to the rest.');
            }

            const size = panel.querySelector('.dx-plot-size[data-slot="0"]')?.value ?? '';
            const stage = state.stages[state.stageIndex];
            const entry = stage && state.plan.find(p => Number(p.rank) === Number(stage.rank));

            if (entry) {
                entry.dims  = entry.dims  || [];
                entry.sizes = entry.sizes || [];

                for (let i = 0; i < slots; i++) {
                    entry.dims[i]  = first.slice();
                    entry.sizes[i] = size === '' ? '' : parseFloat(size);
                }
            }

            // Every card changes shape (a different number of boxes), so the panel is
            // repainted rather than having values written into the inputs on screen.
            renderStagePanel();
            return toast('success', 'Applied to all ' + slots);
        }

        const inputs = [...panel.querySelectorAll('.dx-plot-holder')];
        if (inputs.length < 2) return;

        const first = (inputs[0]?.value ?? '').trim();
        if (first === '') {
            return toast('info', 'Nothing to copy',
                'Fill the first holder, then apply it to the rest.');
        }

        inputs.forEach(input => { input.value = first; });
        toast('success', 'Applied to all ' + inputs.length);
    };

    /** The sides typed on one step-3 card, in order. */
    function plotTermsOf(panel, slot) {
        return [...panel.querySelectorAll('.dx-plot-dims[data-slot="' + slot + '"] .dx-plot-dim')]
            .map(el => el.value);
    }

    /**
     * Recompute one step-3 card's area from its boxes, repaint its readout, and write
     * the answer back into the plan.
     *
     * The plan is the live copy - step 2 renders it and the next stage panel is seeded
     * from it - so a measurement corrected here has to reach it, or stepping back to
     * Quantities would show the figure it replaced and the stale one would win.
     */
    function syncPlotArea(input) {
        const panel = document.getElementById('dx-stage-panel');
        if (!panel) return;

        const slot   = input.dataset.slot;
        const terms  = plotTermsOf(panel, slot);
        const hidden = panel.querySelector('.dx-plot-size[data-slot="' + slot + '"]');
        const out    = panel.querySelector('.dx-plot-area[data-slot="' + slot + '"]');

        // Two sides multiply and give the area; any other count is a surveyed polygon,
        // whose area its sides do not determine. There is no area box to fill in, so
        // the parcel simply carries its dimensions and no figure — rather than one
        // invented from the perimeter.
        const derived = areaFromTerms(terms);

        if (hidden && derived !== null) {
            hidden.value = derived;
        }

        const stage = state.stages[state.stageIndex];
        const entry = stage && state.plan.find(p => Number(p.rank) === Number(stage.rank));

        if (entry) {
            const i = Number(slot);
            entry.sizes = entry.sizes || [];
            entry.dims  = entry.dims  || [];
            entry.dims[i]  = terms.slice();
            entry.sizes[i] = hidden && hidden.value !== '' ? parseFloat(hidden.value) : '';
        }

        if (out) {
            const size = parseFloat(hidden?.value);
            out.innerHTML = (isFinite(size) && size > 0)
                ? area(size) + ' &middot; <span class="text-blue-700">' + hectares(size) + '</span>'
                : '';
        }
    }

    window.onPlotDimInput = function (input) { syncPlotArea(input); };

    /** One more side on a step-3 card, added in place so the panel keeps its state. */
    window.addPlotDimTerm = function (slot) {
        const panel = document.getElementById('dx-stage-panel');
        const wrap = panel?.querySelector('.dx-plot-dims[data-slot="' + slot + '"]');
        if (!wrap) return;

        const term = wrap.querySelectorAll('.dx-plot-dim').length;
        const plus = wrap.querySelector('button[onclick^="addPlotDimTerm"]');

        const sep = document.createElement('span');
        sep.className = 'text-slate-400 text-xs font-black shrink-0';
        sep.textContent = '×';

        const cell = document.createElement('div');
        cell.className = 'relative';
        cell.innerHTML =
            '<input type="number" step="any" min="0" placeholder="' + (term + 1) + '"'
            + ' aria-label="Side ' + (term + 1) + ' in metres" data-slot="' + slot + '" data-term="' + term + '"'
            + ' oninput="onPlotDimInput(this)"'
            + ' class="dx-plot-dim w-14 px-1.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center">'
            + '<button type="button" onclick="removePlotDimTerm(' + slot + ', ' + term + ')" title="Remove this side"'
            + ' class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-white border border-slate-200'
            + ' text-slate-400 hover:text-rose-600 hover:border-rose-200 text-[9px] font-black'
            + ' leading-none flex items-center justify-center">&times;</button>';

        wrap.insertBefore(sep, plus);
        wrap.insertBefore(cell, plus);

        cell.querySelector('input').focus();
        syncPlotArea(cell.querySelector('input'));
    };

    window.removePlotDimTerm = function (slot, term) {
        const panel = document.getElementById('dx-stage-panel');
        const wrap = panel?.querySelector('.dx-plot-dims[data-slot="' + slot + '"]');
        if (!wrap) return;

        const boxes = [...wrap.querySelectorAll('.dx-plot-dim')];
        if (boxes.length <= 1) return;   // never down to nothing

        const values = boxes.map(b => b.value);
        values.splice(Number(term), 1);

        // Rebuilt rather than spliced out of the DOM, so every remaining box carries
        // the right data-term and the × buttons stay addressed correctly.
        const stage = state.stages[state.stageIndex];
        const entry = stage && state.plan.find(p => Number(p.rank) === Number(stage.rank));
        if (entry) {
            entry.dims = entry.dims || [];
            entry.dims[Number(slot)] = values;
        }

        renderStagePanel();
    };

    function collectStagePayload() {
        const stage = state.stages[state.stageIndex];
        const panel = document.getElementById('dx-stage-panel');

        const sizes = [...panel.querySelectorAll('.dx-plot-size')].map(e => e.value);
        const holders = [...panel.querySelectorAll('.dx-plot-holder')].map(e => e.value);

        // size stays the authority - it is what the memo and the summary read - and the
        // dimensions ride alongside it so the stage can be reopened and re-measured.
        //
        // Counted off whichever list is longer, not off `sizes`. A Change of Purpose
        // card carries a Holder and no size box (see renderCopPlots), so mapping over
        // `sizes` produced an empty plots array on every CoP stage and threw away every
        // holder the officer had typed — the values were read on the line above and
        // then had nothing to be written onto.
        const plots = Array.from({ length: Math.max(sizes.length, holders.length) }, (_, i) => {
            const size = sizes[i];
            const terms = plotTermsOf(panel, i)
                .map(v => String(v ?? '').trim())
                .filter(v => v !== '')
                .map(Number);

            return {
                size: (size == null || size === '') ? null : parseFloat(size),
                // The chain, in survey order. length/width are still written for a
                // two-sided parcel so anything reading them keeps working.
                dimensions: terms,
                length: terms.length === 2 ? terms[0] : null,
                width:  terms.length === 2 ? terms[1] : null,
                holder: holders[i] || null,
            };
        });

        const payload = {
            plot_count: stage.plot_count || plots.length || 1,
            plots: plots,
        };

        if (stage.type === 'change_of_purpose' && panel.querySelector('.dx-stage-cop-row')) {
            // Per-file purposes, as answered on step 1. Each file names its own.
            const rows = [...panel.querySelectorAll('.dx-stage-cop-row')].map(r => ({
                file_no:          r.querySelector('.dx-cop-file')?.value || '',
                current_land_use: landUseCode(r.querySelector('.dx-cop-file')?.value || ''),
                new_land_use:     r.querySelector('.dx-cop-new')?.value || '',
            }));

            const missing = rows.filter(r => !r.new_land_use).map(r => r.file_no);
            if (missing.length) {
                toast('warning', 'New purpose missing',
                    'Say what ' + missing.join(', ') + ' changes to.');
                return null;
            }

            payload.cop_rows   = rows;
            payload.applies_to = rows.map(r => r.file_no);
            payload.plot_count = rows.length;

            return payload;
        }

        if (stage.type === 'change_of_purpose') {
            payload.new_land_use = document.getElementById('dx-new-landuse')?.value || '';
            // The purpose being left behind. Stored so the stage records the change it
            // made rather than only where it ended up — and it is the purpose the
            // EARLIER legs left these parcels on, not the source file's.
            const from = landUseEnteringStage(state.stageIndex);
            if (from) payload.current_land_use = from;
            payload.applies_to = [...panel.querySelectorAll('.dx-applies:checked')].map(e => e.value);
            if (!payload.new_land_use) {
                toast('warning', 'New land use required', 'Pick the land use this file is changing to.');
                return null;
            }
            if (!payload.applies_to.length) {
                toast('warning', 'Nothing selected', 'Tick at least one file for the Change of Purpose.');
                return null;
            }
        }

        return payload;
    }

    // ---------------------------------------------------------------- nav
    /**
     * Which wizard steps the officer may click to.
     *
     * Steps 1 and 2 stay reachable for reference once the duplex exists, but the plan
     * behind them is fixed by then - the stage rows and holding numbers were built
     * from it - so they open read-only rather than pretending an edit would stick.
     *
     * Step 4 (the site plan) opens as soon as the duplex row exists - a DRAFT included.
     * The drawing usually arrives from Survey while the stages are still being captured,
     * and there is nothing about attaching it that depends on the stages being finished;
     * making the officer complete every stage first only meant the plan sat on a desk.
     *
     * Step 5 (the summary) still waits for every stage: it reports what was captured,
     * and there is nothing to report until there is.
     */
    function canGoToStep(step) {
        if (step === state.step) return true;
        if (step <= 2) return true;
        if (step === 3) return !!state.duplex;
        if (step === 4) return !!state.duplex;
        if (step === 5) return !!state.duplex && state.stages.length > 0
            && state.stages.every(s => s.status === 'done');
        return false;
    }

    window.goToWizardStep = function (step) {
        if (!canGoToStep(step)) {
            const why = step === 4
                ? 'Start the duplex first — the plan attaches to it.'
                : (step === 5
                    ? 'Every stage has to be captured before the summary.'
                    : 'Finish selecting the updates first.');
            return toast('info', 'Not there yet', why);
        }

        if (step === 3) { renderStageTrack(); renderStagePanel(); }
        if (step === 5) renderDone();
        showStep(step);
    };

    function showStep(step) {
        state.step = step;

        // Step 2 is reached from the stepper and from Back as well as from step 1 — a
        // resumed draft opens at step 3 — so the quantities are painted here rather
        // than at the one call site that used to do it. Without this, opening a draft
        // and clicking Quantities showed an empty card.
        if (step === 2) renderQuantities();

        // Step 1 owns the "two or more updates" gate; every other step re-enables the
        // button, or a plan corrected on the way back would leave it stuck.
        if (step === 1) {
            syncStartProcess();
        } else {
            const nextBtn = document.getElementById('dx-next');
            if (nextBtn) {
                nextBtn.disabled = false;
                nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                nextBtn.title = '';
            }
        }

        // Painted here rather than at the call sites: the step is reached from Next,
        // from the stepper, from Back off the summary and from an upload finishing,
        // and one of those forgetting to render is a blank panel.
        if (step === 4) renderSitePlan();

        document.querySelectorAll('.wizard-step').forEach(el => {
            el.classList.toggle('active', Number(el.dataset.step) === step);
        });

        // The button label lives in its own span so the trailing arrow icon survives.
        const next = document.getElementById('dx-next-text');
        const back = document.getElementById('dx-back');
        const label = document.getElementById('dx-step-label');
        const subtitle = document.getElementById('wizard-subtitle');

        back.style.visibility = step === 1 ? 'hidden' : 'visible';

        // Stepper: everything before the current step reads as done, and a tab you
        // cannot reach yet must not look or behave like a link.
        document.querySelectorAll('.dx-step-tab').forEach(tab => {
            const n = Number(tab.dataset.tab);
            tab.classList.toggle('is-active', n === step);
            tab.classList.toggle('is-done', n < step);

            const reachable = canGoToStep(n);
            tab.classList.toggle('is-locked', !reachable);
            const btn = tab.querySelector('button');
            if (btn) {
                btn.disabled = !reachable;
                btn.title = reachable ? 'Go to this step' : 'Not available yet';
            }
        });

        // Steps 1 and 2 are read-only once the duplex row exists.
        const locked = !!state.duplex;
        document.getElementById('dx-plan-locked').classList.toggle('hidden', !locked);
        document.getElementById('dx-applicant').disabled = locked;
        document.getElementById('dx-file-title').disabled = locked;
        // The arrows still work when locked — reading the other files is not editing.
        ['dx-plot-no-main', 'dx-district', 'dx-lga'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = locked;
        });
        // Quantities and plot sizes stay editable. What is fixed once the duplex row
        // exists is the PLAN — which updates, in what order, on which files — because
        // the stage rows and holding numbers were built from it. How many plots a stage
        // makes, and how big they are, is ordinary data the officer must be able to
        // correct without deleting the duplex and starting again.
        ['dx-plot-no-main', 'dx-district', 'dx-lga'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = locked;
        });
        document.querySelectorAll('[onclick^="pickSourceFile"], [onclick^="removeSource"]')
            .forEach(el => { el.disabled = locked; el.classList.toggle('hidden', locked); });

        if (step === 1) {
            next.textContent = 'Start Process';
            label.textContent = 'Step 1 of 4';
            subtitle.textContent = 'Pick the file, then add the updates in the order you want them carried out.';
        } else if (step === 2) {
            next.textContent = 'Continue Process';
            label.textContent = 'Step 2 of 4';
            subtitle.textContent = 'How many of each.';
        } else if (step === 3) {
            const last = state.stageIndex === state.stages.length - 1;
            next.textContent = last ? 'Finish Stages' : 'Save & Next Stage';
            label.textContent = 'Step 3 of 4';
            subtitle.textContent = 'Each stage runs on holding numbers — nothing is commissioned yet.';
        } else if (step === 4) {
            next.textContent = 'Continue';
            label.textContent = 'Step 4 of 4';
            subtitle.textContent = 'The drawing the recommendation is read against.';
        } else {
            next.textContent = 'Submit';
            label.textContent = '';
            subtitle.textContent = 'Captured.';
        }

        icons();
    }

    window.wizardBack = function () {
        if (state.step === 3 && state.stageIndex > 0) {
            // Stages already saved keep their holding numbers; stepping back is just
            // a view change, and re-saving a stage replaces its numbers cleanly.
            state.stageIndex -= 1;
            renderStageTrack();
            renderStagePanel();
            showStep(3);
            return;
        }
        // Back off the site plan lands on the last stage, which has to be repainted:
        // the panel was last drawn before the stage was saved.
        if (state.step === 4) {
            renderStageTrack();
            renderStagePanel();
            return showStep(3);
        }

        if (state.step > 1) showStep(state.step - 1);
    };

    /**
     * The step button, while the step it started is being carried out.
     *
     * Step 2 creates the duplex and then re-reads it — two round trips with nothing on
     * screen to show for them — so the button looked dead and officers pressed it
     * again, which posted the same duplex twice. It is held disabled for the whole
     * await and released by whichever step comes next.
     */
    function setWizardBusy(busy) {
        const btn     = document.getElementById('dx-next');
        const back    = document.getElementById('dx-back');
        const icon    = document.getElementById('dx-next-icon');
        const spinner = document.getElementById('dx-next-spinner');

        if (btn) {
            btn.disabled = busy;
            btn.classList.toggle('opacity-60', busy);
            btn.classList.toggle('cursor-not-allowed', busy);
            btn.classList.toggle('hover:bg-blue-700', !busy);
        }
        // Going back mid-save would leave the officer on a step the server is still
        // answering for, so it waits with the forward button.
        if (back) {
            back.disabled = busy;
            back.classList.toggle('opacity-60', busy);
            back.classList.toggle('cursor-not-allowed', busy);
        }
        if (icon) icon.classList.toggle('hidden', busy);
        if (spinner) spinner.classList.toggle('hidden', !busy);
    }

    /**
     * One step at a time.
     *
     * The guard matters as much as the disabled attribute: a click queued before the
     * button was disabled, or a keyboard Enter, still reaches this.
     */
    let dxAdvancing = false;

    window.wizardNext = async function () {
        if (dxAdvancing) return;

        dxAdvancing = true;
        setWizardBusy(true);
        try {
            return await wizardAdvance();
        } catch (err) {
            // post() has no error arm of its own, so a dropped connection or an HTML
            // error page used to reject here with nothing said and the button left
            // looking broken. Say what happened and let them try again.
            console.error('Duplex wizard step failed:', err);
            return toast('error', 'That step could not be completed',
                'The server could not be reached, or it answered with an error. Nothing was lost — try again.');
        } finally {
            dxAdvancing = false;
            setWizardBusy(false);
        }
    };

    async function wizardAdvance() {
        // The duplex already exists, so step 1/2 must not create it again — but an edit
        // made on the way back through still has to reach the stages. Without this the
        // corrected quantity was silently thrown away.
        if (state.duplex && state.step < 3) {
            if (state.step === 2) {
                collectQuantities();
                applyPlanCountsToStages();
            }
            return goToWizardStep(3);
        }

        if (state.step === 1) {
            // Checked in the order the fields now appear on the card.
            if (!state.sources.length) return toast('warning', 'Pick at least one source file');

            dxEntryTouched();

            const applicant = document.getElementById('dx-applicant').value.trim();
            if (!applicant) return toast('warning', 'Applicant required');
            if (state.plan.length < MIN_UPDATES) {
                return toast('warning',
                    state.plan.length ? 'A duplex needs two or more updates' : 'Add the updates',
                    'A duplex carries several parcel updates as one instruction. For a single '
                    + 'update, use its own page under Parcel Update — New.');
            }

            const merger = state.plan.find(p => p.type === 'merger');
            if (merger && merger.rank === 1 && state.sources.length < 2) {
                return toast('warning', 'A Merger needs two or more source files');
            }

            // Only a Merger can swallow several files at once. Any other first leg
            // over several sources works on them file by file — which a Change of
            // Purpose does, and is exactly why it may lead.
            const first = state.plan[0];
            if (state.sources.length > 1 && first
                && first.type !== 'merger' && first.type !== 'change_of_purpose') {
                return toast('warning', 'That update cannot come first',
                    TYPES[first.type] + ' starts from one parcel. With several source files '
                    + 'the first update has to be a Merger, or a Change of Purpose that says '
                    + 'which file each change applies to.');
            }

            // A first-leg CoP over several files must have been answered, or the
            // stage has nothing to mint holding numbers from.
            if (state.sources.length > 1 && operatesOnSourceFiles('change_of_purpose')) {
                const rows = copCompleteRows();
                if (!rows.length) {
                    askChangeOfPurposeScope();
                    return toast('warning', 'Change of Purpose not answered yet',
                        'Say which files are changing purpose, and what each one is changing to.');
                }

                const incomplete = copRows().filter(r => (r.file_no && !r.new_land_use)
                                                      || (!r.file_no && r.new_land_use));
                if (incomplete.length) {
                    return toast('warning', 'A row is incomplete',
                        'Every row needs both a file number and the purpose it is changing to. '
                        + 'Remove any row you do not need.');
                }
            }

            renderQuantities();
            return showStep(2);
        }

        if (state.step === 2) {
            collectQuantities();

            const res = await post('{{ route('duplex-parcel-update.store') }}', {
                // One field feeds both columns - they are the same thing here.
                // One value per file, in file order. The server stores a JSON array
                // when there is more than one and a plain string when there is only one,
                // so a single-file duplex reads exactly as it always did.
                applicant_name: document.getElementById('dx-applicant').value.trim(),
                file_title: entryValues('file_title'),
                plot_no: entryValues('plot_no'),
                district: entryValues('district'),
                lga: entryValues('lga'),
                // Kept alongside so each source row can be stamped with its own details.
                source_entries: state.sources.map((src, i) => ({
                    file_no:    src.fileNumber,
                    file_title: state.entries[i]?.file_title || '',
                    plot_no:    state.entries[i]?.plot_no || '',
                    district:   state.entries[i]?.district || '',
                    lga:        state.entries[i]?.lga || '',
                })),
                state: STATE_NAME,
                address: document.getElementById('dx-address').value || null,
                source_file_nos: state.sources.map(s => s.fileNumber),
                stages: state.plan.map(p => {
                    const stage = { type: p.type, rank: p.rank, count: p.count };
                    // Only a first-leg CoP carries rows; every other stage is captured
                    // on its own panel at step 3.
                    if (p.type === 'change_of_purpose' && Array.isArray(p.cop_rows)) {
                        const rows = p.cop_rows.filter(r => r.file_no && r.new_land_use);
                        if (rows.length) {
                            stage.cop_rows = rows;
                            stage.count = rows.length;
                        }
                    }
                    // A later Change of Purpose has no files to name yet, but it does
                    // know what its plots become — carried so step 3 opens with it set.
                    if (p.type === 'change_of_purpose' && p.new_land_use) {
                        stage.new_land_use = p.new_land_use;
                        if (p.current_land_use) stage.current_land_use = p.current_land_use;
                    }
                    return stage;
                }),
            });

            if (!res.success) {
                return toast('error', 'Could not create duplex', res.message || 'Check the form and try again.');
            }

            state.duplex = { id: res.id, duplex_id: res.duplex_id };

            // From here on the officer is working inside a numbered duplex, so the
            // reference belongs in the header where it stays visible.
            const badge = document.getElementById('dx-duplex-badge');
            badge.textContent = res.duplex_id;
            badge.classList.remove('hidden');

            const detail = await fetch('{{ url('duplex-parcel-update') }}/' + res.id, {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json());

            // stage_rows, not stages: `stages` on the payload is the JSON plan column
            // (type + rank + count). The rows are what carry the ids we post back to.
            state.stages = (detail.data?.stage_rows || []).sort((a, b) => a.rank - b.rank);
            state.stageIndex = 0;
            state.carry = [];

            renderStageTrack();
            renderStagePanel();
            return showStep(3);
        }

        if (state.step === 3) {
            const payload = collectStagePayload();
            if (!payload) return;

            const stage = state.stages[state.stageIndex];
            const res = await post(
                '{{ url('duplex-parcel-update') }}/' + state.duplex.id + '/stages/' + stage.id,
                payload
            );

            if (!res.success) {
                return toast('error', 'Stage not saved', res.message || 'Check the entries and try again.');
            }

            state.carry = res.holding_numbers || [];

            // Re-read the rows so the stage just saved carries its holding numbers and
            // its payload. Everything downstream - the next stage's input line, the
            // Apply-to list, and the values shown when the officer clicks back to this
            // stage - is read from those rows, not from this response.
            await refreshStages();

            // A Change of Purpose is the stage officers ask about by name, because its
            // holding numbers are what the rest of the plan hangs off. Say which ones
            // it just issued rather than leaving them to be found on the way back.
            if (stage.type === 'change_of_purpose') {
                const held = mintedHoldings(state.stages[state.stageIndex]);
                if (held.length) {
                    const shown = held.slice(0, 6).join(', ')
                        + (held.length > 6 ? ' and ' + (held.length - 6) + ' more' : '');
                    toast('success', held.length === 1 ? 'Holding number assigned' : 'Holding numbers assigned', shown);
                }
            }

            if (state.stageIndex < state.stages.length - 1) {
                state.stageIndex += 1;
                renderStageTrack();
                renderStagePanel();
                return showStep(3);
            }

            return showStep(4);
        }

        // The site plan is REQUIRED to finish. It is the drawing the recommendation is
        // read against, so a duplex submitted without one cannot be recommended and
        // simply comes back. The step itself is reachable from the moment the duplex
        // exists, so this asks for nothing the officer could not already have done.
        if (state.step === 4) {
            if (!state.sitePlan) {
                document.getElementById('dx-siteplan-drop')?.classList.add(
                    'border-rose-400', 'bg-rose-50/40');

                return toast('warning', 'Site plan required',
                    'Attach the recommended site plan before finishing. It is the drawing '
                    + 'the recommendation is read against.');
            }

            renderDone();
            return showStep(5);
        }

        // Submitting from the Done step: hand the officer the summary sheet rather than
        // dropping them back on the register with nothing to check. The reload waits
        // until the sheet is dismissed.
        closeDuplexWizard(true);

        if (state.duplex && typeof window.showDuplexSummary === 'function') {
            await window.showDuplexSummary('{{ url('duplex-parcel-update') }}', state.duplex.id);
        }

        window.location.reload();
    }

    // ------------------------------------------------------------ site plan
    /**
     * The recommended site plan: one drawing for the whole duplex.
     *
     * Uploaded the moment it is chosen rather than held until Submit. A duplex row
     * already exists by this step, so there is somewhere to put it - and an officer
     * who closes the wizard here finds the plan still attached when they resume.
     */
    const SITE_PLAN_MAX_BYTES = 5 * 1024 * 1024;

    /** Paint the step: the drop card, or the plan that is already attached. */
    function renderSitePlan() {
        const drop = document.getElementById('dx-siteplan-drop');
        const box  = document.getElementById('dx-siteplan-current');
        if (!drop || !box) return;

        if (!state.sitePlan) {
            drop.classList.remove('hidden');
            box.classList.add('hidden');
            box.innerHTML = '';
            icons();
            return;
        }

        // Clears the red the "site plan required" warning painted on the drop card.
        drop.classList.remove('border-rose-400', 'bg-rose-50/40');

        // The drop card is hidden rather than left below: two upload targets on one
        // screen reads as "attach a second plan", and a duplex has exactly one.
        drop.classList.add('hidden');
        box.classList.remove('hidden');

        const name = state.sitePlan.name || String(state.sitePlan.path || '').split('/').pop();
        const pdf  = /\.pdf$/i.test(name);

        /**
         * The drawing itself, not just its filename.
         *
         * A site plan is the one attachment whose CONTENT has to be checked — the wrong
         * sheet uploaded under a plausible name is exactly the mistake this step exists
         * to catch, and "duplex_10059_site_plan_1787896368.png" tells the officer
         * nothing. An image renders inline; a PDF gets a native browser preview, which
         * needs no library and no conversion.
         *
         * Both are click-through to the full-size view, and the frame is fixed height
         * with the image contained inside it so a portrait survey sheet cannot push the
         * Replace and Remove buttons off the panel.
         */
        const preview = pdf
            ? `<div class="mt-4 rounded-xl overflow-hidden border border-emerald-200 bg-white">
                   <object data="${state.sitePlan.url}#toolbar=0&navpanes=0&view=FitH"
                           type="application/pdf" class="w-full h-64 block">
                       {{-- Shown when the browser has no inline PDF viewer. --}}
                       <div class="h-64 flex flex-col items-center justify-center gap-2 text-slate-400">
                           <i data-lucide="file-text" class="w-8 h-8"></i>
                           <p class="text-xs font-bold">Preview not available here — use View</p>
                       </div>
                   </object>
               </div>`
            : `<a href="${state.sitePlan.url}" target="_blank" rel="noopener"
                  class="block mt-4 rounded-xl overflow-hidden border border-emerald-200 bg-white group">
                   <img src="${state.sitePlan.url}" alt="Site plan for ${state.duplex ? state.duplex.duplex_id : 'this duplex'}"
                        class="w-full h-64 object-contain bg-slate-50 group-hover:opacity-95 transition"
                        onerror="this.closest('a').classList.add('hidden')">
               </a>`;

        box.innerHTML = `
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-white border border-emerald-200 shrink-0">
                        <i data-lucide="${pdf ? 'file-text' : 'image'}" class="w-5 h-5 text-emerald-600"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Site plan attached</p>
                        <p class="text-sm font-bold text-slate-700 truncate" title="${name}">${name}</p>
                    </div>
                    <a href="${state.sitePlan.url}" target="_blank" rel="noopener"
                       class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 transition inline-flex items-center gap-1.5 shrink-0">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                    </a>
                </div>

                ${preview}

                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-emerald-200/70">
                    <button type="button" onclick="document.getElementById('dx-siteplan-input').click()"
                        class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 transition inline-flex items-center gap-1.5">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Replace
                    </button>
                    <button type="button" onclick="removeDuplexSitePlan()"
                        class="px-3 py-2 rounded-xl border border-rose-200 bg-white text-xs font-bold text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-1.5">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Remove
                    </button>
                </div>
            </div>`;

        icons();
    }

    window.uploadDuplexSitePlan = async function (file) {
        if (!file) return;

        if (!state.duplex) {
            return toast('warning', 'No duplex yet', 'Capture the stages first - the plan attaches to the duplex.');
        }

        // Checked here as well as on the server: a 5 MB scan that fails validation only
        // after it has crossed the wire wastes the officer's time on what is usually
        // the slow part of the day.
        if (file.size > SITE_PLAN_MAX_BYTES) {
            return toast('error', 'File too large', 'The site plan must be 5 MB or smaller.');
        }
        if (!/\.(pdf|png|jpe?g)$/i.test(file.name)) {
            return toast('error', 'Unsupported file', 'The site plan must be a PDF or an image (PNG or JPG).');
        }

        const body = new FormData();
        body.append('site_plan', file);

        if (window.Swal) {
            Swal.fire({ title: 'Uploading site plan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        }

        const res = await fetch(SITE_PLAN_URL(), {
            // No Content-Type header: the browser sets the multipart boundary, and
            // naming it by hand is the classic way to get an empty $request->file().
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body,
        }).then(r => r.json()).catch(e => ({ success: false, message: e.message }));

        if (window.Swal) Swal.close();

        // Clear the input either way, or choosing the same file again fires no change
        // event and the retry looks like nothing happened.
        const input = document.getElementById('dx-siteplan-input');
        if (input) input.value = '';

        if (!res.success) {
            const detail = res.errors && res.errors.site_plan ? res.errors.site_plan[0] : res.message;
            return toast('error', 'Site plan not attached', detail || 'Try again.');
        }

        state.sitePlan = { path: res.site_plan, url: res.url, name: res.name };
        showStep(4);
        toast('success', 'Site plan attached', res.name || '');
    };

    window.removeDuplexSitePlan = async function () {
        if (!state.duplex || !state.sitePlan) return;

        if (window.Swal) {
            const ok = await Swal.fire({
                icon: 'warning',
                title: 'Remove the site plan?',
                text: 'The uploaded file is deleted. You can attach another one.',
                showCancelButton: true,
                confirmButtonText: 'Remove',
                confirmButtonColor: '#e11d48',
            });
            if (!ok.isConfirmed) return;
        }

        const res = await fetch(SITE_PLAN_URL(), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        }).then(r => r.json()).catch(e => ({ success: false, message: e.message }));

        if (!res.success) return toast('error', 'Not removed', res.message || 'Try again.');

        state.sitePlan = null;
        showStep(4);
    };

    // Drag and drop onto the card. Every dragover must be cancelled or the browser
    // navigates away to the dropped file instead of handing it over.
    document.addEventListener('DOMContentLoaded', function () {
        const drop = document.getElementById('dx-siteplan-drop');
        if (!drop) return;

        ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => {
            e.preventDefault();
            drop.classList.add('border-blue-500', 'bg-blue-50/50');
        }));

        ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => {
            e.preventDefault();
            drop.classList.remove('border-blue-500', 'bg-blue-50/50');
        }));

        drop.addEventListener('drop', e => {
            const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) window.uploadDuplexSitePlan(file);
        });
    });

    function renderDone() {
        const one = state.stages.length === 1;
        document.getElementById('dx-done-text').textContent = one
            ? state.duplex.duplex_id + ' captured with a single update.'
            : state.duplex.duplex_id + ' captured with ' + state.stages.length + ' updates, in the order you added them.';

        document.getElementById('dx-done-chain').innerHTML = state.stages.map(s => {
            const holdings = (s.files || [])
                .slice()
                .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
                .map(f => f.holding_no)
                .filter(Boolean);

            // A carried file kept the number the previous stage gave it - no new one was
            // minted for it, because no new FILE number will be minted either. That is
            // what makes a 5-plot subdivision plus a 2-file CoP use 7 numbers, not 10.
            const rows = (s.files || []).slice().sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
            const isCarried = i => (rows[i]?.role === 'carried');
            const changedCount = rows.filter(r => r.role !== 'carried').length;

            const chip = (h, carried) => `
                <span class="holding-no text-[10px] font-black px-2 py-1 rounded-lg border ${carried
                    ? 'bg-slate-50 text-slate-400 border-slate-200'
                    : 'bg-slate-100 text-slate-600 border-slate-200'}">${h}</span>`;

            const chips = holdings.map((h, i) => chip(h, isCarried(i))).join('');

            const carried = holdings.length - changedCount;
            const summary = carried > 0
                ? changedCount + ' new · ' + carried + ' unchanged'
                : holdings.length + (holdings.length === 1 ? ' file' : ' files');

            const note = carried > 0
                ? `<p class="text-[10px] text-slate-400 mt-1.5">The greyed numbers keep the file numbers the previous stage gave them — this stage does not renumber them.</p>`
                : '';

            return `
                <div class="flex items-start gap-3 px-4 py-3 rounded-2xl border border-slate-200 bg-white">
                    <span class="rank-badge rank-${Math.min(s.rank, 5)} w-7 h-7 rounded-lg border flex items-center justify-center text-xs font-black shrink-0">${s.rank}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-bold text-slate-700 leading-tight">${stageSeriesLabel(s)}</p>
                            ${holdings.length ? `<span class="text-[11px] font-bold text-slate-400 shrink-0">${summary}</span>` : ''}
                        </div>
                        ${chips ? `<div class="flex flex-wrap gap-1.5 mt-2">${chips}</div>` : ''}
                        ${note}
                    </div>
                </div>`;
        }).join('');
    }

    // ---------------------------------------------------------------- register
    /**
     * The row menu is moved to <body> and positioned in viewport coordinates.
     *
     * Left inside the table it is clipped: the table sits in an `overflow-x-auto`
     * wrapper, and any ancestor with a non-visible overflow crops an absolutely
     * positioned child — which is why the menu appeared to drop *inside* the table.
     * Escaping to <body> with `position: fixed` is the only way round that without
     * removing the horizontal scroll the table needs.
     */
    window.toggleRowMenu = function (id, trigger) {
        const menu = document.getElementById('row-menu-' + id);
        const isOpen = !menu.classList.contains('hidden');

        closeRowMenus();
        if (isOpen) return;

        if (menu.parentElement !== document.body) document.body.appendChild(menu);

        menu.classList.remove('hidden');

        // Measure the menu at its natural height: a cap left over from a previous
        // opening near the foot of the page would otherwise be read back as the
        // menu's real height and never lifted again.
        menu.style.maxHeight = '';
        menu.style.overflowY = '';

        const btn = trigger.getBoundingClientRect();
        const box = menu.getBoundingClientRect();
        const gap = 6;
        const margin = 8;

        // The whole menu must be readable — the last item is Delete, and a menu cut
        // off by the foot of the window hides actions people cannot know are there.
        const roomBelow = window.innerHeight - btn.bottom - gap - margin;
        const roomAbove = btn.top - gap - margin;
        const roomTotal = window.innerHeight - margin * 2;

        let height = box.height;
        if (height > roomTotal) {
            // Taller than the window itself (a short window, or a row with every
            // action live): fill the window and let the menu scroll.
            height = roomTotal;
            menu.style.maxHeight = height + 'px';
            menu.style.overflowY = 'auto';
        }

        let top;
        if (height <= roomBelow) {
            top = btn.bottom + gap;              // the usual place, under the trigger
        } else if (height <= roomAbove) {
            top = btn.top - height - gap;        // flipped above it
        } else {
            // Neither side has room for the full menu, so drop the anchoring rather
            // than the items: pin the menu inside the window, next to its row.
            top = Math.min(btn.bottom + gap, window.innerHeight - margin - height);
        }

        menu.style.top = Math.max(margin, top) + 'px';
        menu.style.left = Math.max(margin, Math.min(btn.right - box.width, window.innerWidth - box.width - margin)) + 'px';
    };

    function closeRowMenus() {
        document.querySelectorAll('[id^="row-menu-"]').forEach(m => m.classList.add('hidden'));
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[id^="row-menu-"]') && !e.target.closest('button[onclick^="toggleRowMenu"]')) {
            closeRowMenus();
        }
    });

    // A fixed menu does not travel with the page, so close it rather than let it
    // hang detached from its row. Scrolling *inside* the menu — which a menu capped
    // to the window height does — is not the page moving, so it must not close it.
    window.addEventListener('scroll', function (e) {
        if (e.target instanceof Element && e.target.closest('[id^="row-menu-"]')) return;
        closeRowMenus();
    }, true);
    window.addEventListener('resize', closeRowMenus);

    /**
     * Reopen an unfinished duplex at the first stage still to be captured.
     *
     * Without this a draft is a dead end: the wizard only ever opened on a brand-new
     * duplex, so an officer who closed it mid-capture could not get back in.
     */
    window.resumeDuplex = async function (id) {
        closeRowMenus();

        const res = await fetch('{{ url('duplex-parcel-update') }}/' + id, {
            headers: { 'Accept': 'application/json' }
        }).then(r => r.json());

        if (!res.success) return toast('error', 'Could not open this duplex');

        const d = res.data;
        openDuplexWizard();

        state.duplex = { id: d.id, duplex_id: d.duplex_id };
        state.sources = (d.source_file_nos || []).map(fn => ({ fileNumber: fn, title: d.file_title || '' }));
        state.plan = (d.stages || []).map(p => ({ type: p.type, rank: p.rank, count: p.count ?? null }));
        state.stages = (d.stage_rows || []).slice().sort((a, b) => a.rank - b.rank);

        // Land on the first stage not yet done — a rejected stage counts as not done,
        // which is what makes "re-run that one stage" reachable.
        // Before anything renders: step 2's boxes are filled from the stages that were
        // already captured, so a resumed duplex shows what it holds rather than blanks.
        syncPlanSizesFromStages();

        const next = state.stages.findIndex(s => s.status !== 'done');
        state.stageIndex = next === -1 ? state.stages.length - 1 : next;
        state.carry = [];

        // A plan attached on an earlier visit comes back with the row, so the site-plan
        // step opens showing what is already there rather than an empty drop card that
        // invites a second upload.
        state.sitePlan = d.site_plan
            ? {
                path: d.site_plan,
                url: '{{ asset('storage') }}/' + d.site_plan,
                name: String(d.site_plan).split('/').pop(),
            }
            : null;

        // Stored as a JSON array when the duplex has several files, as a plain string
        // when it has one. Either way it comes back as one value per file.
        const asList = v => {
            if (Array.isArray(v)) return v;
            const raw = String(v ?? '').trim();
            if (raw.startsWith('[')) {
                try { const parsed = JSON.parse(raw); if (Array.isArray(parsed)) return parsed; }
                catch (e) { /* not JSON after all — treat it as one value */ }
            }
            return raw === '' ? [] : [raw];
        };

        const titles     = asList(d.file_title);
        const plots      = asList(d.plot_no);
        const districts  = asList(d.district);
        const lgas       = asList(d.lga);

        // A duplex captured before per-file details has ONE value for every file; it is
        // read onto each entry rather than left on the first.
        const at = (list, i) => list[i] ?? (list.length === 1 ? list[0] : '') ?? '';

        document.getElementById('dx-applicant').value = d.applicant_name || '';

        state.entries = state.sources.map((src, i) => ({
            file_title: at(titles, i),
            plot_no:    at(plots, i),
            district:   at(districts, i),
            lga:        at(lgas, i),
        }));
        state.entryIndex = 0;
        renderEntry();

        const badge = document.getElementById('dx-duplex-badge');
        badge.textContent = d.duplex_id;
        badge.classList.remove('hidden');

        renderSources();
        renderRanks();
        renderStageTrack();
        renderStagePanel();
        showStep(3);

        toast('info', 'Resuming ' + d.duplex_id,
            'Picking up at stage ' + state.stages[state.stageIndex].rank + '.');
    };

    /**
     * The whole account of a duplex - sources, every stage, what was issued and what
     * was retired - in the same card the File Indexing and MLS commissioning screens
     * use. Available at any status, not just after commissioning.
     */
    window.openDuplexSummary = function (id) {
        closeRowMenus();

        if (typeof window.showDuplexSummary !== 'function') {
            return toast('error', 'Summary unavailable', 'The summary card script did not load.');
        }

        return window.showDuplexSummary('{{ url('duplex-parcel-update') }}', id);
    };

    window.viewDuplex = async function (id) {
        const res = await fetch('{{ url('duplex-parcel-update') }}/' + id, {
            headers: { 'Accept': 'application/json' }
        }).then(r => r.json());

        if (!res.success) return toast('error', 'Could not load duplex');

        const d = res.data;
        document.getElementById('dx-view-title').textContent = d.duplex_id;
        // applicant_display is the tidied form the server sends for read-only use;
        // applicant_name stays raw for the wizard, which writes it back.
        document.getElementById('dx-view-sub').textContent =
            (d.applicant_display || d.applicant_name || '') + ' · ' + (d.status || '').replace('_', ' ');

        // The site plan, first: it is the drawing the whole instruction is read
        // against, and an attachment nothing links to might as well not be there.
        const sitePlan = d.site_plan
            ? `<a href="{{ asset('storage') }}/${d.site_plan}" target="_blank" rel="noopener"
                  class="flex items-center gap-3 border border-emerald-200 bg-emerald-50/40 rounded-2xl px-4 py-3 hover:bg-emerald-50 transition">
                   <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white border border-emerald-200 shrink-0">
                       <i data-lucide="map" class="w-4 h-4 text-emerald-600"></i>
                   </span>
                   <div class="min-w-0">
                       <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Site plan</p>
                       <p class="text-xs font-bold text-slate-600 truncate">${String(d.site_plan).split('/').pop()}</p>
                   </div>
                   <i data-lucide="external-link" class="w-4 h-4 text-slate-300 ml-auto shrink-0"></i>
               </a>`
            : `<div class="flex items-center gap-3 border border-slate-200 border-dashed rounded-2xl px-4 py-3">
                   <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-50 border border-slate-200 shrink-0">
                       <i data-lucide="map" class="w-4 h-4 text-slate-300"></i>
                   </span>
                   <p class="text-xs font-bold text-slate-400">No site plan attached</p>
               </div>`;

        const stages = (d.stages || []).sort((a, b) => a.rank - b.rank);
        document.getElementById('dx-view-body').innerHTML = sitePlan + stages.map(s => {
            const files = (s.files || []);
            return `
                <div class="border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="rank-badge rank-${Math.min(s.rank, 5)} text-center px-2 py-0.5 rounded-lg border text-xs font-black">${s.rank}</span>
                        <p class="text-sm font-black text-slate-700">${TYPES[s.type] || s.type}</p>
                        <span class="ml-auto text-[11px] font-bold uppercase text-slate-400">${s.status}</span>
                    </div>
                    ${files.length ? `<div class="space-y-1">${files.map(f => `
                        <div class="flex items-center gap-2 text-xs">
                            <span class="holding-no text-slate-500">${f.holding_no || f.source_file_no || ''}</span>
                            ${f.final_file_no ? `<i data-lucide="arrow-right" class="w-3 h-3 text-slate-300"></i>
                                <span class="holding-no font-bold text-emerald-700">${f.final_file_no}</span>` : ''}
                        </div>`).join('')}</div>`
                        : '<p class="text-xs text-slate-400">No holding numbers yet.</p>'}
                </div>`;
        }).join('');

        const modal = document.getElementById('dx-view-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        icons();
    };

    window.closeViewModal = function () {
        const modal = document.getElementById('dx-view-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    /**
     * openKnupda / closeKnupda / saveKnupda used to live here, driving the
     * four-field KAMMA modal that stood in for the site inspection. The
     * inspection now exists as the Master JSI, captured and approved on the
     * Physical Planning register, so this page only links to it.
     *
     * Sending the JSI to Deeds is not driven from here either — that is Physical
     * Planning acting on their own record, and it lives on the Master JSI
     * register's action menu.
     *
     * The endpoint (duplex-parcel-update.knupda) and the knupda_* columns remain:
     * they are history, and MasterJsiGate reads knupda_status as a legacy pass so
     * duplexes cleared before the cutover keep moving.
     */

    // KAMMA / Physical Planning is the first approval on a duplex record.
    window.openKnupda = async function (id) {
        const res = await fetch('{{ url('duplex-parcel-update') }}/' + id, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        if (!json.success) return toast('error', 'Could not load', json.message);
        const d = json.data;
        document.getElementById('knupda-duplex-id').value = d.id;
        document.getElementById('knupda-land-value').value = d.land_value || '';
        document.getElementById('knupda-fee').value = d.knupda_fee || '';
        document.getElementById('knupda-status').value = d.knupda_status || 'Pending';
        document.getElementById('knupda-remarks').value = d.knupda_remarks || '';
        const modal = document.getElementById('dx-knupda-modal');
        modal.classList.remove('hidden'); modal.classList.add('flex');
    };
    window.closeKnupda = function () {
        const modal = document.getElementById('dx-knupda-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    };
    window.saveKnupda = async function () {
        const id = document.getElementById('knupda-duplex-id').value;
        const result = await post('{{ url('duplex-parcel-update') }}/' + id + '/knupda', {
            land_value: document.getElementById('knupda-land-value').value,
            knupda_fee: document.getElementById('knupda-fee').value,
            knupda_status: document.getElementById('knupda-status').value,
            knupda_remarks: document.getElementById('knupda-remarks').value,
        });
        if (!result.success) return toast('error', 'Not saved', result.message);
        closeKnupda(); toast('success', result.message); setTimeout(() => window.location.reload(), 700);
    };

    window.generateDoc = async function (id, kind) {
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/generate-' + kind, {});
        if (!res.success) return toast('error', 'Not generated', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    /**
     * The approval decision: approve or reject, asked once, answered in a card.
     *
     * They are two answers to the same question, so the menu carries one item and
     * this opens with both. What the split into two menu items was protecting
     * against — a live-looking approval on a duplex that cannot be approved — is
     * handled inside the card instead: Approve is offered only when the register
     * says it is available, and otherwise sits greyed with the first missing step
     * spelled out. The rules themselves are the register's, and the server
     * enforces them again on the way in.
     *
     * `opts` is what the row knows: { label, canApprove, canReject, blocker }.
     * Called without it — nothing does today — both answers are offered and the
     * server has the last word.
     */
    window.openApprovalDecision = async function (id, opts) {
        const o = opts || {};
        const canApprove = o.canApprove !== false;
        const canReject  = o.canReject  !== false;
        const label      = o.label ? String(o.label) : '';

        // No SweetAlert2 (it has failed to load before): fall back to the two
        // actions' own confirmations rather than leaving the item dead.
        if (!window.Swal) {
            if (!canApprove) return rejectDuplex(id);
            return approveDuplex(id);
        }

        const esc = s => String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        const option = (key, title, note, enabled) =>
            '<button type="button" id="dx-decide-' + key + '" class="dx-decision dx-decision-' + key + '"'
            + (enabled ? '' : ' disabled') + '>'
            + '<span class="dx-decision-title">' + title + '</span>'
            + '<span class="dx-decision-note">' + note + '</span>'
            + '</button>';

        // Which duplex, by name. The menu is detached from its row and floats over
        // the register, so a card that only said "Approval decision" would leave the
        // officer to remember which line they opened it from.
        const html = '<div style="text-align:left">'
            + (label
                ? '<p style="margin:0 0 .35rem;font-size:11px;font-weight:800;letter-spacing:.06em;'
                  + 'text-transform:uppercase;color:#94a3b8">' + esc(label) + '</p>'
                : '')
            + option('approve', 'Approve',
                canApprove
                    ? 'Closes the KAMMA/Physical Planning answer and the recommendation as they '
                      + 'stand, opens the conveyance, and lists the duplex for commissioning.'
                    : esc(o.blocker || 'Not available yet'),
                canApprove)
            + option('reject', 'Reject',
                'Closes the duplex: every action except viewing is disabled. You will be asked '
                + 'for a reason.',
                canReject)
            + '</div>';

        // The answer is read off the card and acted on AFTER it has closed:
        // Swal.fire() settles when the popup is gone, and Reject opens a second
        // popup straight away — firing that while the first is still animating
        // out loses it.
        let choice = null;

        await Swal.fire({
            title: 'Approval decision',
            html: html,
            showConfirmButton: false,
            showCloseButton: true,
            focusConfirm: false,
            allowOutsideClick: true,
            didOpen: () => {
                const popup = Swal.getPopup();
                const pick = (key) => {
                    const el = popup.querySelector('#dx-decide-' + key);
                    if (el) el.addEventListener('click', () => { choice = key; Swal.close(); });
                };
                pick('approve');
                pick('reject');
            },
        });

        // Approve acts on the card's own click: the card is the decision point and
        // states what approving does, so a second "are you sure" would only be a
        // second click on the same answer. Reject still asks — it takes a reason.
        if (choice === 'approve') return approveDuplex(id, true);
        if (choice === 'reject')  return rejectDuplex(id);
    };

    /** `decided` skips the confirmation — the approval modal has already asked. */
    window.approveDuplex = async function (id, decided) {
        if (!decided && window.Swal) {
            const r = await Swal.fire({
                icon: 'question',
                title: 'Approve this duplex?',
                html: 'Approving closes the KAMMA answer and the recommendation, opens the '
                    + 'conveyance, and lists the duplex for commissioning.',
                showCancelButton: true,
                confirmButtonText: 'Yes, approve',
                confirmButtonColor: '#059669',
                allowOutsideClick: false,
            });
            if (!r.isConfirmed) return;
        }

        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/approve', {});
        if (!res.success) return toast('error', 'Not approved', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.rejectDuplex = async function (id) {
        let reason = '';
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'warning',
                title: 'Reject this duplex?',
                html: 'A rejected duplex is closed: every action except viewing is disabled.',
                input: 'text',
                inputLabel: 'Reason',
                showCancelButton: true,
                confirmButtonText: 'Yes, reject',
                confirmButtonColor: '#dc2626',
                allowOutsideClick: false,
            });
            if (!r.isConfirmed) return;
            reason = r.value || '';
        }
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/reject', { reason });
        if (!res.success) return toast('error', 'Not rejected', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.sendToLand = async function (id) {
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/send-to-land', {});
        if (!res.success) return toast('error', 'Not sent', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.deleteDuplex = async function (id) {
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'warning', title: 'Delete this duplex?',
                showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626',
            });
            if (!r.isConfirmed) return;
        }
        const res = await fetch('{{ url('duplex-parcel-update') }}/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        }).then(r => r.json());

        if (!res.success) return toast('error', 'Not deleted', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    document.addEventListener('DOMContentLoaded', icons);
})();
</script>

/**
 * OP → File Property ID Matching.
 *
 * The FILE is the control record. The officer loads a confirmed file on the left and
 * its Property ID becomes the target; on the right they search the Occupancy Permits —
 * by serial number, because that is what is printed on the permit in their hand — tick
 * the ones that belong to the file, and Batch Match moves every ticked permit onto the
 * file's Property ID.
 *
 * TWO STAGES, TWO SETS
 * A file's permits rarely share one serial: the officer searches 989, ticks two, ADDS
 * them to the batch, searches 990, adds another. So there are two collections, both
 * keyed source_table:op_id:
 *
 *   selected   ticked on the CURRENT search. Cleared by Add, by Clear, by a new file.
 *   staged     the batch. Survives every search until it is matched or emptied.
 *
 * Selection used to survive searches silently, which meant the officer pressed Batch
 * Match on a set they could no longer see — the count said "3" while the screen showed
 * one. Staging makes the batch a thing on the page: card 3 lists every record in it and
 * each can be removed there. A row already in the batch is drawn ticked and locked in
 * the results, so it cannot be added twice.
 *
 * WHY THE TARGET FILE CLEARS BOTH
 * Ticks and batch alike mean "these belong to THAT file". Changing the file changes what
 * they mean, so both are dropped rather than carried onto a target the officer never
 * checked them against.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('op-propid-match');
        if (!root) return;

        var urls = {
            file: root.dataset.fileUrl,
            ops: root.dataset.opsUrl,
            batch: root.dataset.batchUrl,
            unmatch: root.dataset.unmatchUrl,
            batches: root.dataset.batchesUrl,
            undo: root.dataset.undoUrl
        };
        var csrf = root.dataset.csrf || '';

        // ------------------------------------------------------------------ state
        var target = null;          // the chosen file: {file_number, prop_id, ...}
        var results = [];           // the OP rows currently on screen
        var selected = new Map();   // ticks on the CURRENT search only
        var staged = new Map();     // the batch itself — added across many searches
        var resultsAreAssociated = false;  // card 2 is showing the file's own permits, not a search
        var busy = false;
        var confirming = false;    // a confirmation dialog is open; ignore repeat clicks

        // ------------------------------------------------------------------ elements
        var el = {
            banner: document.getElementById('opm-banner'),
            reset: document.getElementById('opm-reset'),

            fileDisplay: document.getElementById('opm-file-display'),
            filePickBtn: document.getElementById('opm-file-pick-btn'),
            fileSelected: document.getElementById('opm-file-selected'),
            fileExisting: document.getElementById('opm-file-existing'),

            advancedToggle: document.getElementById('opm-advanced-toggle'),
            advancedLabel: document.getElementById('opm-advanced-label'),
            advanced: document.getElementById('opm-advanced'),
            serial: document.getElementById('opm-serial'),
            serialMode: document.getElementById('opm-serial-mode'),
            opFile: document.getElementById('opm-op-file'),
            party: document.getElementById('opm-party'),
            propId: document.getElementById('opm-prop-id'),
            plotNo: document.getElementById('opm-plot-no'),
            hideMatched: document.getElementById('opm-hide-matched'),
            opSearchBtn: document.getElementById('opm-op-search-btn'),
            opClear: document.getElementById('opm-op-clear'),
            opCount: document.getElementById('opm-op-count'),
            opResults: document.getElementById('opm-op-results'),
            addBtn: document.getElementById('opm-add-btn'),
            addLabel: document.getElementById('opm-add-label'),

            stagedList: document.getElementById('opm-staged'),
            stagedClear: document.getElementById('opm-staged-clear'),
            summary: document.getElementById('opm-summary'),
            companions: document.getElementById('opm-move-companions'),
            batchBtn: document.getElementById('opm-batch-btn'),
            batchLabel: document.getElementById('opm-batch-label'),

            batches: document.getElementById('opm-batches'),
            batchesRefresh: document.getElementById('opm-batches-refresh')
        };

        // ------------------------------------------------------------------ helpers
        function esc(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function dash(value) {
            var text = String(value === null || value === undefined ? '' : value).trim();
            return text === '' ? '—' : esc(text);
        }

        function icons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
        }

        function key(row) {
            return row.source_table + ':' + row.op_id;
        }

        function shortDate(value) {
            if (!value) return '—';
            var parsed = new Date(String(value).replace(' ', 'T'));
            if (isNaN(parsed.getTime())) return esc(String(value).slice(0, 10));
            return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function banner(kind, message) {
            var palette = {
                ok: 'bg-emerald-50 border-emerald-200 text-emerald-800',
                warn: 'bg-amber-50 border-amber-200 text-amber-800',
                error: 'bg-rose-50 border-rose-200 text-rose-800',
                info: 'bg-sky-50 border-sky-200 text-sky-800'
            };
            var glyph = { ok: 'check-circle-2', warn: 'alert-triangle', error: 'alert-octagon', info: 'info' };

            el.banner.className = 'mb-5 rounded-xl border px-4 py-3 text-sm flex items-start gap-2.5 ' + (palette[kind] || palette.info);
            el.banner.innerHTML =
                '<i data-lucide="' + (glyph[kind] || 'info') + '" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>' +
                '<div class="flex-1">' + esc(message) + '</div>' +
                '<button type="button" class="opm-banner-close text-current/60 hover:text-current">' +
                '<i data-lucide="x" class="h-4 w-4"></i></button>';
            el.banner.classList.remove('hidden');
            icons();
            el.banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function clearBanner() {
            el.banner.classList.add('hidden');
            el.banner.innerHTML = '';
        }

        /** GET a JSON endpoint. Errors come back as a rejected promise carrying the server's message. */
        function get(url, params) {
            var query = new URLSearchParams(params || {}).toString();
            return fetch(url + (query ? '?' + query : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(readJson);
        }

        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            }).then(readJson);
        }

        function readJson(response) {
            return response.json().catch(function () {
                throw new Error('The server returned something that was not a response (HTTP ' + response.status + ').');
            }).then(function (payload) {
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || firstValidationError(payload) || 'That request failed (HTTP ' + response.status + ').');
                }
                return payload;
            });
        }

        function firstValidationError(payload) {
            if (!payload || !payload.errors) return null;
            var keys = Object.keys(payload.errors);
            return keys.length ? payload.errors[keys[0]][0] : null;
        }

        // ------------------------------------------------------------------ left: file
        /**
         * Open the shared Global File Number Selector.
         *
         * The picker owns the searching, the registry tabs and the recent-selections
         * list; this page only takes the number it returns and loads it as the target.
         * Its callback hands back { fileNumber, tab, file_title, record } — only the
         * number is trusted here, because fileTarget() re-reads the file from the
         * registries anyway and its answer is the one the match is made against.
         */
        function openFilePicker() {
            if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
                banner('error', 'The File Number Selector did not load on this page. Refresh, and if it keeps happening report it — the file cannot be chosen without it.');
                return;
            }

            window.GlobalFileNoModal.open({
                // The picker fills any input it recognises as a file-number field by name.
                // This page has none to fill — the display box is set from the callback,
                // after the server has confirmed the file — so that is turned off rather
                // than left to guess.
                autoPopulateGenericFields: false,
                callback: function (picked) {
                    var fileNumber = ((picked && picked.fileNumber) || '').toString().trim().replace(/-+$/, '');
                    if (!fileNumber) return;
                    pickFile(fileNumber);
                }
            });
        }

        /**
         * Load a file as the target.
         *
         * Read-only. A file with no registered Property ID comes back with the one it
         * would be given, flagged as proposed; nothing is reserved until the match runs.
         */
        function pickFile(fileNumber) {
            el.fileSelected.classList.remove('hidden');
            el.fileSelected.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-6 text-center text-sm text-slate-400">Loading the file…</div>';

            get(urls.file, { file_no: fileNumber }).then(function (payload) {
                target = payload.data;
                el.fileDisplay.value = target.file_number;

                // A change of target invalidates the ticks AND the batch: both meant
                // "these belong to the file I was looking at", and that file is no longer
                // this one. Carrying them over would match a set against a target the
                // officer never checked them against.
                selected.clear();
                staged.clear();
                renderStaged();

                renderTarget();
                renderExisting(target.existing || []);
                updateSummary();

                // What is already on this parcel, listed without being asked for.
                loadAssociatedOps();

                // No banner for an unregistered file. The target card already shows the
                // id it will be given and marks it as new; a warning on top of that turns
                // a routine case into something that looks like a fault.
                clearBanner();
            }).catch(function (error) {
                target = null;
                el.fileDisplay.value = '';
                el.fileSelected.classList.add('hidden');
                el.fileExisting.classList.add('hidden');
                updateSummary();
                banner('error', error.message);
            });
        }

        function renderTarget() {
            if (!target) {
                el.fileSelected.classList.add('hidden');
                el.fileSelected.innerHTML = '';
                return;
            }

            var badges = [];
            if (target.commissioned) badges.push('<span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-semibold">Commissioned</span>');
            if (target.in_mls) badges.push('<span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">MLPP File Numbers</span>');
            if (target.indexed) badges.push('<span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">Indexed</span>');

            el.fileSelected.classList.remove('hidden');
            el.fileSelected.innerHTML = '' +
                '<div class="rounded-xl border-2 border-violet-200 bg-violet-50/50 p-4">' +
                  '<div class="flex items-start justify-between gap-4">' +
                    '<div class="min-w-0">' +
                      '<div class="text-[11px] font-semibold uppercase tracking-wide text-violet-600">Target file</div>' +
                      '<div class="text-lg font-bold text-slate-900 mt-0.5">' + dash(target.file_number) + '</div>' +
                      // The picker's KANGIS tabs return a KANGIS number; the permits being
                      // moved will be carrying the land file number behind it, so show it.
                      (target.also_known_as
                        ? '<div class="text-xs text-violet-700 font-medium">also indexed as ' + esc(target.also_known_as) + '</div>'
                        : '') +
                      '<div class="text-sm text-slate-600">' + dash(target.file_title) + '</div>' +
                      '<div class="flex flex-wrap gap-1.5 mt-2">' + badges.join('') + '</div>' +
                    '</div>' +
                    '<div class="flex-shrink-0 text-right">' +
                      '<div class="text-[11px] font-semibold uppercase tracking-wide text-violet-600">Target Property ID</div>' +
                      '<div class="text-3xl font-black text-violet-700 tabular-nums leading-tight">' + esc(target.prop_id) + '</div>' +
                      // A previewed id is marked, not hidden: it is the real target, but
                      // it becomes this file's only when the match is written.
                      (target.prop_id_proposed
                        ? '<div class="text-[10px] font-semibold text-violet-500 uppercase tracking-wide mt-0.5">new · reserved when you match</div>'
                        : '') +
                    '</div>' +
                  '</div>' +
                  '<dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-3 border-t border-violet-200/70 text-xs">' +
                    field('Plot', target.plot_no) + field('Land use', target.land_use) +
                    field('LGA', target.lga) + field('Location', target.location) +
                  '</dl>' +
                  renderRelated() +
                '</div>';

            icons();
        }

        /**
         * The file's other numbers, and what each one's parcel id is.
         *
         * Always shown when there are any, not only on the unregistered path: a land file,
         * its Old KANGIS file and its New KANGIS file are three files over one parcel and
         * carry three different prop_ids on purpose. Seeing that the number in hand points
         * somewhere else is the difference between consolidating a file and splitting it.
         */
        function renderRelated() {
            var related = (target && target.related) || [];
            if (!related.length) return '';

            var rows = related.map(function (item) {
                return '<div class="flex items-center justify-between gap-2 py-1">' +
                    '<span class="font-medium text-slate-700">' + dash(item.file_number) + '</span>' +
                    (item.prop_id
                      ? '<button type="button" class="opm-use-related px-2 py-0.5 rounded bg-white border border-violet-200 text-violet-700 text-[11px] font-semibold hover:bg-violet-50 transition" ' +
                        'data-file="' + esc(item.file_number) + '">use PROP_ID ' + esc(item.prop_id) + '</button>'
                      : '<span class="text-slate-400 text-[11px]">not registered</span>') +
                  '</div>';
            }).join('');

            return '' +
                '<div class="mt-3 pt-3 border-t border-violet-200/70">' +
                  '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">' +
                    'Other numbers on this file' +
                  '</div>' +
                  (rows || '<div class="text-xs text-slate-400 py-1">None recorded.</div>') +
                '</div>';
        }

        function field(label, value) {
            return '<div><dt class="text-slate-400 font-medium">' + esc(label) + '</dt>' +
                   '<dd class="text-slate-800 font-semibold truncate">' + dash(value) + '</dd></div>';
        }

        function renderExisting(rows) {
            if (!rows.length) {
                el.fileExisting.classList.remove('hidden');
                el.fileExisting.innerHTML =
                    '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-xs text-slate-500">' +
                    'Nothing is recorded on this Property ID yet.</div>';
                return;
            }

            el.fileExisting.classList.remove('hidden');
            el.fileExisting.innerHTML = '' +
                '<details class="rounded-lg border border-slate-200 overflow-hidden" open>' +
                  '<summary class="px-4 py-2.5 bg-slate-50 cursor-pointer text-xs font-semibold text-slate-600 select-none">' +
                    'Already on Property ID ' + esc(target ? target.prop_id : '') + ' — ' + rows.length + ' record(s)' +
                  '</summary>' +
                  '<div class="max-h-56 overflow-auto divide-y divide-slate-100">' +
                    rows.map(function (row) {
                        return '<div class="px-4 py-2 text-xs">' +
                            '<div class="flex items-center justify-between gap-2">' +
                              '<span class="font-semibold text-slate-800">' + dash(row.instrument_type) + '</span>' +
                              '<span class="text-slate-400 tabular-nums">' + esc(row.source_table) + ' #' + esc(row.id) + '</span>' +
                            '</div>' +
                            '<div class="text-slate-500 mt-0.5">' +
                              dash(row.file_no) +
                              (row.op_serial_number ? ' · serial ' + esc(row.op_serial_number) : '') +
                              ' · ' + dash(row.party_2 || row.party_1) +
                            '</div>' +
                          '</div>';
                    }).join('') +
                  '</div>' +
                '</details>';
        }

        // ------------------------------------------------------------------ right: OPs
        /**
         * Show the permits already sitting on the chosen file, without being asked.
         *
         * Selecting a file answers "what is on this parcel already?", and making the
         * officer search for permits that the page could simply list is how a permit
         * already on the wrong file goes unnoticed. Runs on every file selection.
         *
         * Uses the ordinary OP search with nothing but prop_id, so these rows come back
         * fully shaped — is_matched and companion counts included — and the batch treats
         * them exactly like searched ones. exclude_prop_id is deliberately NOT sent: it
         * is the target's own parcel id, and sending it would filter out every row this
         * is trying to show.
         */
        function loadAssociatedOps() {
            if (!target) {
                resultsAreAssociated = false;
                results = [];
                renderResults();
                return;
            }

            resultsAreAssociated = true;
            el.opResults.classList.remove('hidden');
            el.opResults.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-8 text-center text-sm text-slate-400">Loading the permits on this file…</div>';
            el.opCount.textContent = '';

            // Both ways a permit can belong to this file, in one request. The file
            // number matters as much as the parcel id — a permit captured against the
            // file but never matched carries the number and its OWN parcel id, and it is
            // the one most in need of attention. Aliases go too, so a KANGIS-numbered
            // permit on the same file is found.
            // EVERY number this file is known by, not just the one picked. The link to a
            // file's permits is often an alias — a KANGIS number in related_fileno — and
            // sending only the picked number leaves those permits unfound.
            var numbers = [target.file_number];
            if (target.also_known_as) numbers.push(target.also_known_as);
            (target.related || []).forEach(function (item) {
                if (item && item.file_number) numbers.push(item.file_number);
            });

            get(urls.ops, {
                associated: 1,
                file_no: numbers.join(','),
                // A previewed id belongs to nothing yet, so it would only match noise.
                prop_id: target.prop_id_proposed ? '' : (target.prop_id || '')
            }).then(function (payload) {
                results = payload.data || [];
                renderResults();
            }).catch(function () {
                // A convenience, not the job. A failure here leaves the officer to search,
                // which is what they would have done anyway.
                resultsAreAssociated = false;
                results = [];
                el.opResults.classList.add('hidden');
                el.opCount.textContent = '';
            });
        }

        function searchOps() {
            resultsAreAssociated = false;

            var params = {
                serial: el.serial.value.trim(),
                serial_mode: el.serialMode.value,
                file_no: el.opFile.value.trim(),
                party: el.party.value.trim(),
                prop_id: el.propId.value.trim(),
                plot_no: el.plotNo.value.trim()
            };

            if (el.hideMatched.checked && target && target.prop_id) params.exclude_prop_id = target.prop_id;

            el.opResults.classList.remove('hidden');
            el.opResults.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-8 text-center text-sm text-slate-400">Searching…</div>';
            el.opCount.textContent = '';

            get(urls.ops, params).then(function (payload) {
                results = payload.data || [];
                renderResults();

                if (payload.truncated) {
                    banner('warn', 'More permits match than can be shown. Narrow the search — add the file number, ' +
                        'the holder name, or fewer serials — so you can see everything you are about to move.');
                } else {
                    clearBanner();
                }
            }).catch(function (error) {
                results = [];
                el.opResults.classList.add('hidden');
                el.opCount.textContent = '';
                banner('error', error.message);
            });
        }

        function renderResults() {
            if (!results.length) {
                el.opResults.classList.remove('hidden');
                el.opResults.innerHTML =
                    '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">' +
                    (resultsAreAssociated
                        ? 'No Occupancy Permits are on this file yet. Search by serial to find the ones that belong to it.'
                        : 'No Occupancy Permit matches that search.') +
                    '</div>';
                el.opCount.textContent = '';
                updateSummary();
                return;
            }

            var onPageSelected = results.filter(function (row) { return selected.has(key(row)); }).length;
            var onPageStaged = results.filter(function (row) { return staged.has(key(row)); }).length;
            // Rows already in the batch are locked, so "all" means all the rows that can
            // still be ticked — otherwise the box could never show as checked.
            var tickable = results.length - onPageStaged;

            el.opCount.textContent =
                (resultsAreAssociated ? results.length + ' on this file' : results.length + ' found') +
                ' · ' + onPageSelected + ' ticked here' +
                (onPageStaged ? ' · ' + onPageStaged + ' already in batch' : '');
            el.opResults.classList.remove('hidden');
            el.opResults.innerHTML = '' +
                '<div class="rounded-lg border border-slate-200 overflow-hidden">' +
                  (resultsAreAssociated
                    ? '<div class="px-4 py-2 bg-amber-50 border-b border-amber-200 text-xs font-semibold text-amber-800">' +
                        'Associated with ' + esc(target.file_number) +
                        ' — on its Property ID, or carrying its file number' +
                      '</div>'
                    : '') +
                  '<div class="px-4 py-2 bg-slate-50 border-b border-slate-200 flex items-center gap-3">' +
                    '<label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">' +
                      '<input type="checkbox" id="opm-select-all" ' + (tickable > 0 && onPageSelected === tickable ? 'checked' : '') + ' ' +
                        'class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"> Select all on screen' +
                    '</label>' +
                  '</div>' +
                  '<div class="max-h-[28rem] overflow-auto divide-y divide-slate-100">' +
                    results.map(renderRow).join('') +
                  '</div>' +
                '</div>';

            icons();
            updateSummary();
        }

        function renderRow(row) {
            var rowKey = key(row);
            var isSelected = selected.has(rowKey);
            // Already in the batch: shown ticked and locked, so it cannot be added twice.
            // It is removed from card 3, where the whole batch is visible, not from here.
            var isStaged = staged.has(rowKey);
            var alreadyOnTarget = target && target.prop_id && String(row.prop_id) === String(target.prop_id);
            var companions = row.companions;

            // A permit already on this file is SELECTABLE, not locked. It used to be
            // barred on the reasoning that there was nothing to do with it — but this
            // page unmatches too now, and the permits on the wrong file are exactly the
            // ones an officer opens a file to take off it. Only a row already in the
            // batch is locked, and that is to stop it being added twice.
            var locked = isStaged;

            return '' +
                '<label class="flex items-start gap-3 px-4 py-3 transition ' +
                  (locked ? 'cursor-default ' : 'cursor-pointer ') +
                  (isStaged ? 'bg-violet-50/70' : (isSelected ? 'bg-emerald-50/70' : 'hover:bg-slate-50')) + '">' +
                  '<input type="checkbox" class="opm-op-tick mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" ' +
                    'data-key="' + esc(rowKey) + '" ' + (isSelected || isStaged ? 'checked' : '') + (locked ? ' disabled' : '') + '>' +
                  '<div class="min-w-0 flex-1">' +
                    '<div class="flex items-center flex-wrap gap-2">' +
                      '<span class="px-2 py-0.5 rounded bg-slate-900 text-white text-[11px] font-bold tabular-nums">Serial ' + dash(row.op_serial_number) + '</span>' +
                      '<span class="font-semibold text-sm text-slate-900">' + dash(row.file_no) + '</span>' +
                      (row.temp_file_no && row.temp_file_no !== row.file_no
                        ? '<span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold">' + esc(row.temp_file_no) + '</span>'
                        : '') +
                      (row.op_type ? '<span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">' + esc(row.op_type) + '</span>' : '') +
                    '</div>' +
                    '<div class="text-xs text-slate-600 mt-1">' +
                      '<span class="text-slate-400">Holder:</span> <span class="font-medium">' + dash(row.holder) + '</span>' +
                      ' <span class="text-slate-300">|</span> <span class="text-slate-400">Granted:</span> ' + shortDate(row.transaction_date) +
                      ' <span class="text-slate-300">|</span> <span class="text-slate-400">Reg:</span> ' + dash(row.reg_particulars) +
                    '</div>' +
                    '<div class="text-[11px] text-slate-400 mt-0.5">' +
                      esc(row.source_table) + ' #' + esc(row.op_id) +
                      (row.plot_no ? ' · Plot ' + esc(row.plot_no) : '') +
                      (row.lga ? ' · ' + esc(row.lga) : '') +
                      (row.location ? ' · ' + esc(row.location) : '') +
                    '</div>' +
                  '</div>' +
                  '<div class="flex-shrink-0 text-right">' +
                    (alreadyOnTarget
                      ? '<span class="px-2 py-1 rounded-md bg-emerald-100 text-emerald-700 text-[11px] font-semibold">on this file</span>'
                      : isStaged
                        ? '<span class="px-2 py-1 rounded-md bg-violet-600 text-white text-[11px] font-semibold">in batch</span>'
                        : '<span class="px-2 py-1 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold tabular-nums">PROP_ID ' + dash(row.prop_id) + '</span>') +
                    // Matched permits are listed now, so the row has to say so — the batch
                    // below offers Unmatch on them, and an officer must know which they
                    // picked before they get there.
                    (row.is_matched
                      ? '<div class="text-[10px] font-semibold text-amber-600 mt-1">already matched</div>'
                      : '') +
                    (companions
                      ? '<div class="text-[10px] text-amber-600 mt-1">+' + companions + ' linked row(s)</div>'
                      : '') +
                  '</div>' +
                '</label>';
        }

        function findRow(rowKey) {
            for (var i = 0; i < results.length; i++) {
                if (key(results[i]) === rowKey) return results[i];
            }
            return null;
        }

        // ------------------------------------------------------------------ staging
        /**
         * Move what is ticked on screen into the batch.
         *
         * The ticks are cleared afterwards: they belong to the search that produced them,
         * and leaving them set would make the next "Add" re-submit permits already in the
         * batch. The batch itself is what survives — it is drawn on card 3 where the
         * officer can see and remove any part of it.
         */
        function addSelectedToBatch() {
            if (selected.size === 0) return;

            var added = 0;
            selected.forEach(function (row, rowKey) {
                if (!staged.has(rowKey)) added++;
                staged.set(rowKey, row);
            });

            selected.clear();
            renderResults();
            renderStaged();
            updateSummary();

            banner('ok', added + ' OP record(s) added to the batch. ' +
                'Search another serial to add more, or press Batch Match when the batch is complete.');
        }

        function removeFromBatch(rowKey) {
            staged.delete(rowKey);
            renderResults();
            renderStaged();
            updateSummary();
        }

        /**
         * What kind of batch is this?
         *
         *   'match'    nothing in it is matched yet — one Batch Match moves them all
         *   'unmatch'  everything in it is already matched — one Batch Unmatch frees them
         *   'mixed'    both kinds together. There is no single right action for the batch,
         *              so none is offered: each row gets its own button instead.
         *
         * A permit whose state could not be read (is_matched null) makes the batch mixed
         * rather than joining either side, so a batch action is never taken on a guess.
         */
        function batchKind() {
            var matched = 0;
            var unmatched = 0;

            staged.forEach(function (row) {
                if (row.is_matched === true) matched++;
                else if (row.is_matched === false) unmatched++;
            });

            if (matched > 0 && unmatched === 0 && matched === staged.size) return 'unmatch';
            if (unmatched > 0 && matched === 0 && unmatched === staged.size) return 'match';
            return 'mixed';
        }

        function renderStaged() {
            el.stagedClear.classList.toggle('hidden', staged.size === 0);

            if (staged.size === 0) {
                el.stagedList.classList.add('hidden');
                el.stagedList.innerHTML = '';
                return;
            }

            // Per-row buttons appear only when the batch is mixed. With a batch that is
            // all one kind the single button below says the same thing once, and a button
            // on every row would just be the same action repeated.
            var mixed = batchKind() === 'mixed';

            var rows = [];
            staged.forEach(function (row, rowKey) {
                rows.push('' +
                    '<div class="flex items-center gap-3 px-4 py-2.5">' +
                      '<span class="px-2 py-0.5 rounded bg-slate-900 text-white text-[11px] font-bold tabular-nums flex-shrink-0">Serial ' +
                        dash(row.op_serial_number) + '</span>' +
                      '<div class="min-w-0 flex-1">' +
                        '<div class="text-sm font-semibold text-slate-900 truncate">' + dash(row.file_no) + '</div>' +
                        '<div class="text-[11px] text-slate-500 truncate">' +
                          dash(row.holder) + ' · ' + esc(row.source_table) + ' #' + esc(row.op_id) +
                        '</div>' +
                      '</div>' +
                      '<span class="flex-shrink-0 px-2 py-1 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold tabular-nums">PROP_ID ' +
                        dash(row.prop_id) + '</span>' +
                      (row.companions
                        ? '<span class="flex-shrink-0 text-[10px] text-amber-600">+' + row.companions + ' linked</span>'
                        : '') +
                      (mixed ? rowAction(row, rowKey) : '') +
                      '<button type="button" class="opm-staged-remove flex-shrink-0 p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" ' +
                        'title="Remove from batch" data-key="' + esc(rowKey) + '">' +
                        '<i data-lucide="x" class="h-4 w-4"></i></button>' +
                    '</div>');
            });

            var kind = batchKind();
            var heading = kind === 'unmatch'
                ? 'In this batch — ' + staged.size + ' matched OP record(s)'
                : kind === 'match'
                  ? 'In this batch — ' + staged.size + ' OP record(s)'
                  : 'In this batch — ' + staged.size + ' OP record(s), matched and unmatched together';

            el.stagedList.classList.remove('hidden');
            el.stagedList.innerHTML = '' +
                '<div class="rounded-lg border border-violet-200 bg-violet-50/40 overflow-hidden">' +
                  '<div class="px-4 py-2 bg-violet-100/60 border-b border-violet-200 text-xs font-semibold text-violet-800">' +
                    esc(heading) +
                  '</div>' +
                  '<div class="max-h-64 overflow-auto divide-y divide-violet-100 bg-white">' + rows.join('') + '</div>' +
                '</div>';

            icons();
        }

        /** The per-row Match / Unmatch button, shown only on a mixed batch. */
        function rowAction(row, rowKey) {
            if (row.is_matched === true) {
                return '<button type="button" class="opm-row-unmatch flex-shrink-0 px-2.5 py-1 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-semibold transition" ' +
                    'data-key="' + esc(rowKey) + '">Unmatch</button>';
            }

            if (row.is_matched === false) {
                return '<button type="button" class="opm-row-match flex-shrink-0 px-2.5 py-1 rounded-md bg-violet-600 hover:bg-violet-700 text-white text-[11px] font-semibold transition" ' +
                    'data-key="' + esc(rowKey) + '">Match</button>';
            }

            // State unknown — offer nothing rather than the wrong thing.
            return '<span class="flex-shrink-0 text-[10px] text-slate-400">state unknown</span>';
        }

        // ------------------------------------------------------------------ batch
        function updateSummary() {
            var count = staged.size;
            var ticked = selected.size;

            var companionTotal = 0;
            staged.forEach(function (row) { companionTotal += (row.companions || 0); });

            // Card 2's Add button tracks the ticks; card 3's button tracks the batch.
            el.addBtn.disabled = ticked === 0 || busy;
            el.addLabel.textContent = ticked > 0 ? 'Add ' + ticked + ' to Batch' : 'Add to Batch';

            var kind = count > 0 ? batchKind() : 'match';

            // ---- Unmatch: the batch is entirely already-matched permits. A target file
            // is irrelevant here — unmatching takes them off whatever they are on.
            if (kind === 'unmatch') {
                el.summary.innerHTML =
                    '<span class="font-semibold text-slate-800">' + count + ' already-matched OP record(s)</span>' +
                    ' will be taken off the parcel they are on and given new Property IDs.' +
                    (count > 1 ? ' You will choose whether individually or as one Group OP.' : '') +
                    '<div class="text-xs text-slate-500 mt-1">Reassigning them to a file afterwards is optional — ' +
                    'they stay in this batch, and you can pick a file and match, or stop here.</div>';

                el.batchBtn.disabled = busy;
                el.batchLabel.textContent = 'Batch Unmatch ' + count + ' OP(s)';
                setBatchTone('unmatch');
                return;
            }

            // ---- Mixed: no single action is right for the batch, so none is offered.
            if (kind === 'mixed') {
                el.summary.innerHTML =
                    '<span class="text-amber-600 font-medium">This batch mixes matched and unmatched permits.</span> ' +
                    'There is no one action for both, so use the <strong>Match</strong> and <strong>Unmatch</strong> ' +
                    'buttons on each row above.' +
                    (!target || !target.prop_id
                        ? '<div class="text-xs text-slate-500 mt-1">Matching a row needs a confirmed file selected on the left.</div>'
                        : '');

                el.batchBtn.disabled = true;
                el.batchLabel.textContent = 'Use the per-row buttons';
                setBatchTone('mixed');
                return;
            }

            // ---- Match: the ordinary path, unchanged.
            setBatchTone('match');

            if (!target && count === 0) {
                el.summary.innerHTML = 'Select a confirmed file on the left, then search OPs and add them to this batch.';
            } else if (!target) {
                el.summary.innerHTML = '<span class="text-amber-600 font-medium">' + count +
                    ' OP(s) in the batch — now select the confirmed file on the left.</span>';
            } else if (!target.prop_id) {
                // A target with no parcel id is not a target. There is nothing to move the
                // permits ONTO, so the button stays shut until the officer resolves it.
                el.summary.innerHTML = '<span class="text-amber-600 font-medium">' +
                    esc(target.file_number) + ' has no Property ID and none could be worked out.</span> ' +
                    "Try again, or pick one of the file's other numbers.";
            } else if (count === 0) {
                el.summary.innerHTML = 'Target is <span class="font-semibold text-slate-800">' + esc(target.file_number) +
                    '</span> (Property ID <span class="font-semibold text-violet-700">' + esc(target.prop_id) +
                    '</span>). Now search the OPs that belong to it, tick them, and press Add to Batch.' +
                    (ticked > 0
                        ? '<div class="text-xs text-amber-600 mt-1">' + ticked +
                          ' ticked on the right but not yet added — press Add to Batch.</div>'
                        : '');
            } else {
                el.summary.innerHTML =
                    '<span class="font-semibold text-slate-800">' + count + ' OP record(s)</span>' +
                    (companionTotal && el.companions.checked
                        ? ' <span class="text-slate-500">plus ' + companionTotal + ' linked Transfer of Title row(s)</span>'
                        : '') +
                    ' will be moved to Property ID <span class="font-semibold text-violet-700">' + esc(target.prop_id) +
                    '</span> — file <span class="font-semibold text-slate-800">' + esc(target.file_number) + '</span>.' +
                    (ticked > 0
                        ? '<div class="text-xs text-amber-600 mt-1">' + ticked +
                          ' more ticked on the right but not yet added — press Add to Batch to include them.</div>'
                        : '');
            }

            var ready = !!target && !!target.prop_id && count > 0 && !busy;
            el.batchBtn.disabled = !ready;
            el.batchLabel.textContent = count > 0 ? 'Batch Match ' + count + ' OP(s)' : 'Batch Match';
        }

        /** The button carries the colour of what it does: violet moves on, rose takes off. */
        function setBatchTone(kind) {
            var base = 'inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white ' +
                'disabled:bg-slate-300 disabled:cursor-not-allowed rounded-lg transition shadow-sm ';
            el.batchBtn.className = base + (kind === 'unmatch'
                ? 'bg-rose-600 hover:bg-rose-700'
                : 'bg-slate-900 hover:bg-slate-800');
        }

        /**
         * Match ONE permit from a mixed batch, leaving the rest of the batch alone.
         *
         * The same endpoint and the same rules as a full Batch Match — it is one row
         * rather than all of them, and the row leaves the batch once it is on the file.
         */
        function runSingleMatch(row) {
            if (busy || confirming) return;

            confirming = true;
            confirmAction({
                title: 'Match this permit to ' + target.file_number + '?',
                icon: 'question',
                confirmText: 'Yes, match it',
                confirmColor: '#7c3aed',
                html: 'Serial <strong>' + esc(row.op_serial_number || '—') + '</strong> moves onto ' +
                      '<strong>Property ID ' + esc(String(target.prop_id)) + '</strong>.' +
                      '<br><br><span style="font-size:13px;color:#64748b;">This can be undone from Recent matches.</span>'
            }).then(function (ok) {
                confirming = false;
                if (!ok) return;

                busy = true;
                updateSummary();

                post(urls.batch, {
                    file_no: target.file_number,
                    prop_id: target.prop_id,
                    prop_id_proposed: !!target.prop_id_proposed,
                    ops: [{ source_table: row.source_table, op_id: row.op_id }],
                    move_companions: el.companions.checked
                }).then(function (payload) {
                    banner(payload.errors && payload.errors.length ? 'warn' : 'ok', payload.message);

                    // Matched: it belongs to the file now, so it leaves the batch. Anything
                    // still unresolved stays, which is what makes the batch shrink toward
                    // one kind and the single button come back.
                    staged.delete(key(row));
                    selected.delete(key(row));

                    if (payload.prop_id) {
                        target.prop_id = payload.prop_id;
                        target.prop_id_proposed = false;
                        renderTarget();
                    }

                    renderStaged();
                    renderExisting(payload.existing || []);
                    if (results.length) searchOps();
                    loadBatches();
                }).catch(function (error) {
                    banner('error', error.message);
                }).finally(function () {
                    busy = false;
                    updateSummary();
                });
            });
        }

        /**
         * Ask how the permits should be separated, then unmatch them.
         *
         * Group is offered only for two or more: a group of one is not a group, and
         * offering it would let an officer believe they had made something shared when
         * they had not. The server refuses it too.
         */
        function runUnmatch(ops) {
            if (ops.length === 1) {
                confirming = true;
                confirmAction({
                    title: 'Unmatch Associated OP',
                    icon: 'warning',
                    confirmText: 'Unmatch and issue its own ID',
                    confirmColor: '#e11d48',
                    html: 'It comes off the parcel it is on and is given a Property ID of its own.' +
                          '<br><br><span style="font-size:13px;color:#64748b;">Reassigning it to a file afterwards is optional. ' +
                          'This can be undone from Recent matches.</span>'
                }).then(function (ok) {
                    confirming = false;
                    if (ok) sendUnmatch(ops, 'individual');
                });
                return;
            }

            chooseUnmatchMode(ops.length).then(function (mode) {
                if (mode) sendUnmatch(ops, mode);
            });
        }

        /** The two-way choice, for two or more permits. */
        function chooseUnmatchMode(count) {
            var individual = 'Issue Individual IDs';
            var group = 'Create as Group OPs';

            if (window.Swal && typeof window.Swal.fire === 'function') {
                confirming = true;
                return window.Swal.fire({
                    title: 'How should these ' + count + ' permits be separated?',
                    html:
                        '<div style="text-align:left;font-size:13px;color:#475569;line-height:1.6">' +
                          '<p style="margin:0 0 10px"><strong>Unmatch and Issue Individual IDs</strong> — each permit becomes ' +
                          'its own parcel with its own Property ID. Use this when the permits have nothing to do with each other.</p>' +
                          '<p style="margin:0"><strong>Unmatch / Create as Group OPs</strong> — all ' + count + ' permits share ' +
                          'one new Property ID. Use this when they belong together, but not to the file they are on.</p>' +
                        '</div>',
                    icon: 'question',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: individual,
                    denyButtonText: group,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#0284c7',
                    denyButtonColor: '#7c3aed',
                    reverseButtons: true
                }).then(function (r) {
                    confirming = false;
                    if (r.isConfirmed) return 'individual';
                    if (r.isDenied) return 'group';
                    return null;
                });
            }

            var wantsGroup = window.confirm(
                'How should these ' + count + ' permits be separated?' +
                '\n\nOK = ' + group + ' (they share one new Property ID)' +
                '\nCancel = ' + individual + ' (each gets its own)'
            );
            return Promise.resolve(wantsGroup ? 'group' : 'individual');
        }

        function sendUnmatch(ops, mode) {
            busy = true;
            el.batchBtn.disabled = true;
            el.batchLabel.textContent = 'Unmatching…';

            post(urls.unmatch, {
                ops: ops,
                mode: mode,
                move_companions: el.companions.checked
            }).then(function (payload) {
                banner(payload.errors && payload.errors.length ? 'warn' : 'ok', payload.message);

                // The permits STAY in the batch. Unmatching does not end the job — the
                // officer may now pick a file and match them, or stop. Their new parcel
                // ids and cleared matched-state are written straight in, so the batch
                // redraws as a plain Match batch without a round trip.
                (payload.assignments || []).forEach(function (a) {
                    var rowKey = a.source_table + ':' + a.op_id;
                    var row = staged.get(rowKey);
                    if (!row) return;
                    row.prop_id = String(a.prop_id);
                    row.is_matched = false;
                    row.companions = 0;
                    staged.set(rowKey, row);
                });

                renderStaged();
                updateSummary();

                // The search list still shows their old state; re-run it so it agrees.
                if (results.length) searchOps();
                loadBatches();
            }).catch(function (error) {
                banner('error', error.message);
            }).finally(function () {
                busy = false;
                updateSummary();
            });
        }

        function runBatch() {
            if (staged.size === 0 || busy || confirming) return;

            var ops = [];
            staged.forEach(function (row) {
                ops.push({ source_table: row.source_table, op_id: row.op_id });
            });

            var kind = batchKind();

            // An all-matched batch unmatches. No target file is needed or used: the
            // permits come off whatever they are on.
            if (kind === 'unmatch') {
                runUnmatch(ops);
                return;
            }

            // Mixed batches have no single action; the per-row buttons handle them.
            if (kind === 'mixed') return;

            if (!target || !target.prop_id) return;

            var pending = confirmAction({
                title: 'Match ' + ops.length + ' Occupancy Permit record(s)?',
                icon: 'warning',
                confirmText: 'Yes, match them',
                confirmColor: '#059669',
                html:
                    'They will move onto <strong>Property ID ' + esc(String(target.prop_id)) + '</strong>' +
                    ' (file <strong>' + esc(target.file_number) + '</strong>).' +
                    '<br><br><span style="font-size:13px;color:#64748b;">Their current Property IDs will be replaced. ' +
                    'This can be undone from Recent matches.</span>'
            });

            confirming = true;
            pending.then(function (confirmed) {
                confirming = false;
                if (!confirmed) return;
                runBatchConfirmed(ops);
            });
        }

        /** The batch itself, once the officer has confirmed it. */
        function runBatchConfirmed(ops) {
            busy = true;
            el.batchBtn.disabled = true;
            el.batchLabel.textContent = 'Matching…';

            post(urls.batch, {
                file_no: target.file_number,
                prop_id: target.prop_id,
                ops: ops,
                move_companions: el.companions.checked,
                // Tells the server this target was a preview, so it re-checks and
                // registers the id (or a fresh one) before writing anything.
                prop_id_proposed: !!target.prop_id_proposed
            }).then(function (payload) {
                banner(payload.errors && payload.errors.length ? 'warn' : 'ok', payload.message);

                // Done means done: the batch is spent, and leaving it would invite the
                // same records being matched twice against the next file the officer opens.
                staged.clear();
                selected.clear();
                renderStaged();

                // The server settles the id: a previewed one is now registered, and may
                // not be the number that was on screen if it was claimed meanwhile. Adopt
                // what actually got written, so the card and any further match agree with
                // the database.
                if (payload.prop_id) {
                    target.prop_id = payload.prop_id;
                    target.prop_id_proposed = false;
                    renderTarget();
                }

                renderExisting(payload.existing || []);

                // Re-run the search so the moved permits redraw carrying their new id,
                // rather than showing the officer a stale screen they might act on again.
                if (results.length) searchOps();
                else renderResults();

                updateSummary();
                loadBatches();
            }).catch(function (error) {
                banner('error', error.message);
            }).finally(function () {
                busy = false;
                updateSummary();
            });
        }

        /**
         * Confirm a destructive action. Returns a promise resolving true/false.
         *
         * Wrapped rather than calling Swal.fire at each site so the three
         * confirmations share one look, and so a page where the SweetAlert CDN
         * did not load still guards the action instead of silently proceeding.
         */
        function confirmAction(opts) {
            if (typeof Swal === 'undefined') {
                return Promise.resolve(window.confirm(
                    opts.title + '\n\n' + (opts.text || '')
                ));
            }

            return Swal.fire({
                title: opts.title,
                html: opts.html || undefined,
                text: opts.html ? undefined : (opts.text || ''),
                icon: opts.icon || 'question',
                showCancelButton: true,
                confirmButtonText: opts.confirmText || 'Continue',
                cancelButtonText: 'Cancel',
                confirmButtonColor: opts.confirmColor || '#7c3aed',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                focusCancel: true
            }).then(function (result) {
                return !!result.isConfirmed;
            });
        }

        // ------------------------------------------------------------------ history
        function loadBatches() {
            el.batches.innerHTML = '<span class="text-slate-400">Loading…</span>';

            get(urls.batches).then(function (payload) {
                var rows = payload.data || [];
                if (!rows.length) {
                    el.batches.innerHTML = '<span class="text-slate-400">No matches have been made yet.</span>';
                    return;
                }

                el.batches.innerHTML = '<div class="divide-y divide-slate-100">' + rows.map(function (batch) {
                    return '<div class="flex items-center justify-between gap-3 py-2.5 px-1">' +
                        '<div class="min-w-0">' +
                          '<div class="text-sm font-medium text-slate-800">' +
                            dash(batch.file_number) + ' <span class="text-slate-400">→ PROP_ID ' + esc(batch.prop_id) + '</span>' +
                          '</div>' +
                          '<div class="text-[11px] text-slate-400">' +
                            esc(batch.batch_ref) + ' · ' + batch.records + ' record(s) · ' + dash(batch.created_at) +
                          '</div>' +
                        '</div>' +
                        (batch.undone
                          ? '<span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-500 text-[11px] font-semibold">Undone</span>'
                          : '<button type="button" class="opm-undo px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold transition" ' +
                            'data-batch="' + esc(batch.batch_ref) + '">Undo</button>') +
                      '</div>';
                }).join('') + '</div>';
            }).catch(function (error) {
                el.batches.innerHTML = '<span class="text-rose-600">' + esc(error.message) + '</span>';
            });
        }

        function undoBatch(batchRef) {
            confirmAction({
                title: 'Undo batch ' + batchRef + '?',
                icon: 'warning',
                confirmText: 'Yes, put it back',
                confirmColor: '#e11d48',
                text: 'Records that have been moved again since will be left alone.'
            }).then(function (confirmed) {
                if (!confirmed) return;

                post(urls.undo, { batch_ref: batchRef }).then(function (payload) {
                    banner('ok', payload.message);
                    loadBatches();
                    if (target) pickFile(target.file_number);
                }).catch(function (error) {
                    banner('error', error.message);
                });
            });
        }

        // ------------------------------------------------------------------ wiring
        el.filePickBtn.addEventListener('click', openFilePicker);
        el.fileDisplay.addEventListener('click', openFilePicker);

        el.fileSelected.addEventListener('click', function (event) {
            // Switch the target to a related number that already has a parcel id.
            var use = event.target.closest('.opm-use-related');
            if (use) pickFile(use.dataset.file);
        });

        el.advancedToggle.addEventListener('click', function () {
            var hidden = el.advanced.classList.toggle('hidden');
            el.advancedLabel.textContent = hidden ? 'Advanced search' : 'Hide advanced';
        });

        el.opSearchBtn.addEventListener('click', searchOps);
        [el.serial, el.opFile, el.party, el.propId].forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); searchOps(); }
            });
        });

        el.opClear.addEventListener('click', function () {
            el.serial.value = '';
            el.opFile.value = '';
            el.party.value = '';
            el.propId.value = '';
            el.plotNo.value = '';
            results = [];
            // The batch is deliberately NOT cleared here: Clear resets the search so the
            // next serial can be looked up, and carrying the batch across searches is the
            // entire reason it exists. Remove all on card 3 empties the batch.
            selected.clear();
            el.opResults.classList.add('hidden');
            el.opResults.innerHTML = '';
            el.opCount.textContent = '';
            updateSummary();
        });

        el.opResults.addEventListener('change', function (event) {
            if (event.target.id === 'opm-select-all') {
                var on = event.target.checked;
                results.forEach(function (row) {
                    // A row already in the batch is locked; select-all must not re-tick it.
                    if (staged.has(key(row))) return;
                    if (on) selected.set(key(row), row);
                    else selected.delete(key(row));
                });
                renderResults();
                return;
            }

            if (event.target.classList.contains('opm-op-tick')) {
                var rowKey = event.target.dataset.key;
                var row = findRow(rowKey);
                if (!row) return;

                if (event.target.checked) selected.set(rowKey, row);
                else selected.delete(rowKey);

                event.target.closest('label').classList.toggle('bg-emerald-50/70', event.target.checked);
                renderResults();
            }
        });

        el.addBtn.addEventListener('click', addSelectedToBatch);

        el.stagedList.addEventListener('click', function (event) {
            var remove = event.target.closest('.opm-staged-remove');
            if (remove) { removeFromBatch(remove.dataset.key); return; }

            // Per-row actions, on a mixed batch only.
            var unmatchOne = event.target.closest('.opm-row-unmatch');
            if (unmatchOne) {
                var u = staged.get(unmatchOne.dataset.key);
                if (u) runUnmatch([{ source_table: u.source_table, op_id: u.op_id }]);
                return;
            }

            var matchOne = event.target.closest('.opm-row-match');
            if (matchOne) {
                var m = staged.get(matchOne.dataset.key);
                if (!m) return;
                if (!target || !target.prop_id) {
                    banner('warn', 'Select the confirmed file on the left before matching a permit to it.');
                    return;
                }
                runSingleMatch(m);
            }
        });

        el.stagedClear.addEventListener('click', function () {
            if (staged.size === 0) return;
            staged.clear();
            renderResults();
            renderStaged();
            updateSummary();
        });

        el.companions.addEventListener('change', updateSummary);

        el.batchBtn.addEventListener('click', runBatch);

        el.batchesRefresh.addEventListener('click', loadBatches);
        el.batches.addEventListener('click', function (event) {
            var button = event.target.closest('.opm-undo');
            if (button) undoBatch(button.dataset.batch);
        });

        el.banner.addEventListener('click', function (event) {
            if (event.target.closest('.opm-banner-close')) clearBanner();
        });

        el.reset.addEventListener('click', function () {
            target = null;
            results = [];
            selected.clear();
            staged.clear();
            renderStaged();
            el.fileDisplay.value = '';
            el.fileSelected.classList.add('hidden');
            el.fileExisting.classList.add('hidden');
            el.opClear.click();
            clearBanner();
            updateSummary();
        });

        updateSummary();
        loadBatches();
        icons();
    });
})();

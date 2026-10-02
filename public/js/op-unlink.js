/**
 * OP Unlink / Reassignment.
 *
 * Five sections down the page:
 *
 *   1  the file the permits are on now
 *   2  Associated OPs — everything on that file's Property ID
 *   3  Unmatch — detach the selected permits, individually or as a Group OP
 *   4  Unmatched OPs — the pool: what was just detached, plus anything added by hand
 *   5  Select New File Number — OPTIONAL, then Match
 *
 * REASSIGNMENT IS OPTIONAL, and the script enforces that by never coupling the two:
 * the unmatch in section 3 does not check section 5, does not scroll to it, and does
 * not warn about leaving it empty. An officer who unmatches and walks away has
 * finished a job, not abandoned a form.
 *
 * THREE SETS, and keeping them apart is most of the work here:
 *
 *   associatedSel   ticked in section 2 — what is about to be detached
 *   pool            section 4's contents — detached permits and manual additions
 *   poolSel         ticked within the pool — what a reassignment would move
 *
 * A permit leaves section 2 and appears in section 4 when it is unmatched, because it
 * is no longer on that file. Section 2 is then re-read from the server rather than
 * patched, so what is on screen is what is in the database.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('op-unlink');
        if (!root) return;

        var urls = {
            file: root.dataset.fileUrl,
            ops: root.dataset.opsUrl,
            match: root.dataset.matchUrl,
            associated: root.dataset.associatedUrl,
            unlink: root.dataset.unlinkUrl,
            batches: root.dataset.batchesUrl,
            undo: root.dataset.undoUrl
        };
        var csrf = root.dataset.csrf || '';

        // ------------------------------------------------------------------ state
        var currentFile = null;      // section 1: {file_number, prop_id, ...}
        var newFile = null;          // section 5: the optional destination
        var associated = [];         // section 2 rows
        var associatedSel = new Map();
        var pool = new Map();        // section 4, keyed source_table:op_id
        var poolSel = new Map();
        var poolResults = [];        // section 4's manual search results
        var busy = false;
        var confirming = false;

        var el = {
            banner: document.getElementById('opu-banner'),
            reset: document.getElementById('opu-reset'),

            fileDisplay: document.getElementById('opu-file-display'),
            filePickBtn: document.getElementById('opu-file-pick-btn'),
            fileSelected: document.getElementById('opu-file-selected'),

            associated: document.getElementById('opu-associated'),
            associatedCount: document.getElementById('opu-associated-count'),

            unmatchSummary: document.getElementById('opu-unmatch-summary'),
            moveCompanions: document.getElementById('opu-move-companions'),
            unmatchBtn: document.getElementById('opu-unmatch-btn'),
            unmatchLabel: document.getElementById('opu-unmatch-label'),

            poolSearchToggle: document.getElementById('opu-pool-search-toggle'),
            poolSearchLabel: document.getElementById('opu-pool-search-label'),
            poolSearch: document.getElementById('opu-pool-search'),
            serial: document.getElementById('opu-serial'),
            opFile: document.getElementById('opu-op-file'),
            poolSearchBtn: document.getElementById('opu-pool-search-btn'),
            poolSearchCount: document.getElementById('opu-pool-search-count'),
            poolResults: document.getElementById('opu-pool-results'),
            pool: document.getElementById('opu-pool'),

            newFileDisplay: document.getElementById('opu-new-file-display'),
            newFilePickBtn: document.getElementById('opu-new-file-pick-btn'),
            newFileClear: document.getElementById('opu-new-file-clear'),
            newFileSelected: document.getElementById('opu-new-file-selected'),
            matchSummary: document.getElementById('opu-match-summary'),
            matchBtn: document.getElementById('opu-match-btn'),
            matchLabel: document.getElementById('opu-match-label'),

            batches: document.getElementById('opu-batches'),
            batchesRefresh: document.getElementById('opu-batches-refresh')
        };

        // ------------------------------------------------------------------ helpers
        function esc(v) {
            return String(v === null || v === undefined ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function dash(v) {
            var t = String(v === null || v === undefined ? '' : v).trim();
            return t === '' ? '—' : esc(t);
        }

        function icons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
        }

        function key(row) { return row.source_table + ':' + row.op_id; }

        function shortDate(v) {
            if (!v) return '—';
            var d = new Date(String(v).replace(' ', 'T'));
            if (isNaN(d.getTime())) return esc(String(v).slice(0, 10));
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
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
                '<button type="button" class="opu-banner-close text-current/60 hover:text-current">' +
                '<i data-lucide="x" class="h-4 w-4"></i></button>';
            el.banner.classList.remove('hidden');
            icons();
            el.banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function clearBanner() {
            el.banner.classList.add('hidden');
            el.banner.innerHTML = '';
        }

        function get(url, params) {
            var q = new URLSearchParams(params || {}).toString();
            return fetch(url + (q ? '?' + q : ''), {
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
            for (var field in payload.errors) {
                if (Object.prototype.hasOwnProperty.call(payload.errors, field)) {
                    var list = payload.errors[field];
                    if (list && list.length) return list[0];
                }
            }
            return null;
        }

        /** SweetAlert where it exists, the browser's own dialog where it does not. */
        function confirmAction(opts) {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    title: opts.title,
                    html: opts.html,
                    icon: opts.icon || 'question',
                    showCancelButton: true,
                    confirmButtonText: opts.confirmText || 'Continue',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: opts.confirmColor || '#e11d48',
                    reverseButtons: true
                }).then(function (r) { return !!r.isConfirmed; });
            }
            var text = String(opts.html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            return Promise.resolve(window.confirm(opts.title + '\n\n' + text));
        }

        function openPicker(onPicked) {
            if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
                banner('error', 'The File Number Selector did not load on this page. Refresh and try again.');
                return;
            }
            window.GlobalFileNoModal.open({
                autoPopulateGenericFields: false,
                callback: function (picked) {
                    var n = ((picked && picked.fileNumber) || '').toString().trim().replace(/-+$/, '');
                    if (n) onPicked(n);
                }
            });
        }

        // ------------------------------------------------------- 1: the current file
        function pickCurrentFile(fileNumber) {
            el.fileSelected.classList.remove('hidden');
            el.fileSelected.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-6 text-center text-sm text-slate-400">Loading the file…</div>';

            get(urls.file, { file_no: fileNumber }).then(function (payload) {
                currentFile = payload.data;
                el.fileDisplay.value = currentFile.file_number;

                // A change of file invalidates section 2's ticks — they meant "take these
                // off THAT file". The pool is left alone on purpose: permits already
                // detached are detached, and they stay available to reassign.
                associatedSel.clear();

                renderCurrentFile();
                clearBanner();
                loadAssociated();
            }).catch(function (error) {
                currentFile = null;
                associated = [];
                associatedSel.clear();
                el.fileDisplay.value = '';
                el.fileSelected.classList.add('hidden');
                renderAssociated();
                updateUnmatch();
                banner('error', error.message);
            });
        }

        function renderCurrentFile() {
            if (!currentFile) { el.fileSelected.classList.add('hidden'); return; }

            el.fileSelected.classList.remove('hidden');
            el.fileSelected.innerHTML = '' +
                '<div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">' +
                  '<div class="flex items-start justify-between gap-4">' +
                    '<div class="min-w-0">' +
                      '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Current file</div>' +
                      '<div class="text-lg font-bold text-slate-900">' + dash(currentFile.file_number) + '</div>' +
                      '<div class="text-sm text-slate-600">' + dash(currentFile.file_title) + '</div>' +
                    '</div>' +
                    '<div class="flex-shrink-0 text-right">' +
                      '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Property ID</div>' +
                      '<div class="text-2xl font-black text-slate-800 tabular-nums">' + dash(currentFile.prop_id) + '</div>' +
                      (currentFile.prop_id_proposed
                        ? '<div class="text-[10px] font-semibold text-amber-600 uppercase">not registered — nothing to unlink</div>'
                        : '') +
                    '</div>' +
                  '</div>' +
                '</div>';
        }

        // ------------------------------------------------------- 2: associated OPs
        function loadAssociated() {
            if (!currentFile || !currentFile.prop_id || currentFile.prop_id_proposed) {
                associated = [];
                renderAssociated();
                updateUnmatch();
                return;
            }

            el.associated.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-8 text-center text-sm text-slate-400">Loading associated records…</div>';

            get(urls.associated, { prop_id: currentFile.prop_id }).then(function (payload) {
                associated = payload.permits || [];
                renderAssociated(payload.others || []);
                updateUnmatch();
            }).catch(function (error) {
                associated = [];
                renderAssociated();
                updateUnmatch();
                banner('error', error.message);
            });
        }

        function renderAssociated(others) {
            others = others || [];

            if (!currentFile) {
                el.associatedCount.textContent = '';
                el.associated.innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">' +
                    'Select a file number above to see the permits associated with it.</div>';
                return;
            }

            if (!associated.length) {
                el.associatedCount.textContent = others.length ? others.length + ' other record(s)' : '';
                el.associated.innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">' +
                    'No Occupancy Permits are associated with this file.</div>' + renderOthers(others);
                icons();
                return;
            }

            var ticked = associated.filter(function (r) { return associatedSel.has(key(r)); }).length;
            el.associatedCount.textContent = associated.length + ' permit(s) · ' + ticked + ' selected';

            el.associated.innerHTML = '' +
                '<div class="rounded-lg border border-slate-200 overflow-hidden">' +
                  '<div class="px-4 py-2 bg-slate-50 border-b border-slate-200">' +
                    '<label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">' +
                      '<input type="checkbox" id="opu-assoc-all" ' + (ticked === associated.length ? 'checked' : '') + ' ' +
                        'class="h-3.5 w-3.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500"> Select all' +
                    '</label>' +
                  '</div>' +
                  '<div class="max-h-[26rem] overflow-auto divide-y divide-slate-100">' +
                    associated.map(function (r) { return renderPermitRow(r, associatedSel, 'opu-assoc-tick'); }).join('') +
                  '</div>' +
                '</div>' + renderOthers(others);

            icons();
        }

        /**
         * The non-permit records on the same parcel — transfers, mostly.
         *
         * Shown but not selectable. The officer should see everything on the parcel
         * before taking anything off it, but a transfer is not a permit: detaching one
         * on its own would leave a grant with no transfer, and a transfer with no grant.
         */
        function renderOthers(others) {
            if (!others.length) return '';

            return '' +
                '<div class="mt-3 rounded-lg border border-slate-200 bg-slate-50/60 overflow-hidden">' +
                  '<div class="px-4 py-2 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wide text-slate-500">' +
                    'Also on this Property ID — not selectable' +
                  '</div>' +
                  '<div class="divide-y divide-slate-100">' +
                    others.map(function (r) {
                      return '<div class="px-4 py-2 flex items-center gap-3 text-xs">' +
                          '<span class="px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 font-semibold">' + dash(r.instrument_type) + '</span>' +
                          '<span class="font-medium text-slate-700">' + dash(r.file_no) + '</span>' +
                          '<span class="text-slate-500 truncate">' + dash(r.holder) + '</span>' +
                          '<span class="ml-auto text-slate-400">' + esc(r.source_table) + ' #' + esc(r.op_id) + '</span>' +
                        '</div>';
                    }).join('') +
                  '</div>' +
                '</div>';
        }

        /** One permit row. Used by section 2, section 4's search results and the pool. */
        function renderPermitRow(row, selection, tickClass) {
            var isSel = selection.has(key(row));

            return '' +
                '<label class="flex items-start gap-3 px-4 py-3 cursor-pointer transition ' +
                  (isSel ? 'bg-emerald-50/70' : 'hover:bg-slate-50') + '">' +
                  '<input type="checkbox" class="' + tickClass + ' mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" ' +
                    'data-key="' + esc(key(row)) + '" ' + (isSel ? 'checked' : '') + '>' +
                  '<div class="min-w-0 flex-1">' +
                    '<div class="flex items-center flex-wrap gap-2">' +
                      '<span class="px-2 py-0.5 rounded bg-slate-900 text-white text-[11px] font-bold tabular-nums">Serial ' + dash(row.op_serial_number) + '</span>' +
                      '<span class="font-semibold text-sm text-slate-900">' + dash(row.file_no) + '</span>' +
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
                    '</div>' +
                  '</div>' +
                  '<span class="flex-shrink-0 px-2 py-1 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold tabular-nums">PROP_ID ' +
                    dash(row.prop_id) + '</span>' +
                '</label>';
        }

        function findIn(list, rowKey) {
            for (var i = 0; i < list.length; i++) {
                if (key(list[i]) === rowKey) return list[i];
            }
            return null;
        }

        // ------------------------------------------------------- 3: the unmatch
        function updateUnmatch() {
            var n = associatedSel.size;
            var ready = n > 0 && !busy;

            el.unmatchBtn.disabled = !ready;
            el.unmatchLabel.textContent = n > 0 ? 'Unmatch ' + n + ' Selected' : 'Unmatch Selected';

            if (!currentFile) {
                el.unmatchSummary.innerHTML = 'Select a file above, then choose the permits to take off it.';
            } else if (n === 0) {
                el.unmatchSummary.innerHTML = 'Select one or more permits above.';
            } else {
                el.unmatchSummary.innerHTML =
                    '<span class="font-semibold text-slate-800">' + n + ' permit(s)</span> will be taken off ' +
                    '<span class="font-semibold text-slate-800">' + esc(currentFile.file_number) + '</span> ' +
                    '(Property ID <span class="font-semibold text-slate-800">' + esc(currentFile.prop_id) + '</span>)' +
                    (n === 1
                      ? ' and given a Property ID of its own.'
                      : ' and given new Property IDs — you will choose whether individually or as one Group OP.');
            }
        }

        /**
         * Ask how to separate, then do it.
         *
         * The Group option is offered ONLY for two or more permits. A group of one is
         * not a group, and offering it would let an officer believe they had created
         * something shared when they had not. The server refuses it too.
         */
        function askAndUnmatch() {
            if (associatedSel.size === 0 || busy || confirming) return;

            var ops = [];
            associatedSel.forEach(function (row) {
                ops.push({ source_table: row.source_table, op_id: row.op_id });
            });

            if (ops.length === 1) {
                confirming = true;
                confirmAction({
                    title: 'Unmatch Associated OP',
                    icon: 'warning',
                    confirmText: 'Unmatch and issue its own ID',
                    html: 'It will come off <strong>' + esc(currentFile.file_number) + '</strong> and be given a Property ID of its own.' +
                          '<br><br><span style="font-size:13px;color:#64748b;">Reassigning it to another file afterwards is optional.</span>'
                }).then(function (ok) {
                    confirming = false;
                    if (ok) runUnmatch(ops, 'individual');
                });
                return;
            }

            chooseMode(ops.length).then(function (mode) {
                if (mode) runUnmatch(ops, mode);
            });
        }

        /** The two-way choice, for two or more permits. */
        function chooseMode(count) {
            var individual = 'Unmatch and Issue Individual IDs';
            var group = 'Unmatch / Create as Group OPs';

            if (window.Swal && typeof window.Swal.fire === 'function') {
                confirming = true;
                return window.Swal.fire({
                    title: 'How should these ' + count + ' permits be separated?',
                    html:
                        '<div style="text-align:left;font-size:13px;color:#475569;line-height:1.6">' +
                          '<p style="margin:0 0 10px"><strong>Issue Individual IDs</strong> — each permit becomes its own parcel, with its own Property ID. ' +
                          'Use this when the permits have nothing to do with each other.</p>' +
                          '<p style="margin:0"><strong>Create as Group OPs</strong> — all ' + count + ' permits share one new Property ID. ' +
                          'Use this when they belong together, but not to the file they are on.</p>' +
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

            // No SweetAlert: ask the group question plainly rather than guessing.
            var wantsGroup = window.confirm(
                'How should these ' + count + ' permits be separated?\n\n' +
                'OK = ' + group + ' (they share one new Property ID)\n' +
                'Cancel = ' + individual + ' (each gets its own)'
            );
            return Promise.resolve(wantsGroup ? 'group' : 'individual');
        }

        function runUnmatch(ops, mode) {
            busy = true;
            el.unmatchBtn.disabled = true;
            el.unmatchLabel.textContent = 'Unmatching…';

            post(urls.unlink, {
                ops: ops,
                mode: mode,
                move_companions: el.moveCompanions.checked
            }).then(function (payload) {
                banner(payload.errors && payload.errors.length ? 'warn' : 'ok', payload.message);

                // Straight into the pool, ready to reassign — or to be left alone.
                (payload.released || []).forEach(function (row) { pool.set(key(row), row); });

                associatedSel.clear();
                renderPool();
                updateMatch();

                // Re-read section 2 rather than patch it: the permits have moved, and the
                // server is the only thing that knows what is left on the parcel.
                loadAssociated();
                loadBatches();
            }).catch(function (error) {
                banner('error', error.message);
            }).finally(function () {
                busy = false;
                updateUnmatch();
            });
        }

        // ------------------------------------------------------- 4: the pool
        function renderPool() {
            if (pool.size === 0) {
                el.pool.innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">' +
                    'Nothing here yet. Unmatch permits above, or add other permits by searching.</div>';
                return;
            }

            var rows = [];
            var ticked = 0;
            pool.forEach(function (row) {
                if (poolSel.has(key(row))) ticked++;
                rows.push(renderPoolRow(row));
            });

            el.pool.innerHTML = '' +
                '<div class="rounded-lg border border-emerald-200 overflow-hidden">' +
                  '<div class="px-4 py-2 bg-emerald-50 border-b border-emerald-200 flex items-center gap-3">' +
                    '<label class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-800 cursor-pointer">' +
                      '<input type="checkbox" id="opu-pool-all" ' + (ticked === pool.size ? 'checked' : '') + ' ' +
                        'class="h-3.5 w-3.5 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500"> Select all' +
                    '</label>' +
                    '<span class="text-xs text-emerald-700">' + pool.size + ' permit(s) · ' + ticked + ' selected</span>' +
                    '<button type="button" id="opu-pool-clear" class="ml-auto text-xs font-medium text-slate-500 hover:text-rose-600">Remove all</button>' +
                  '</div>' +
                  '<div class="max-h-[26rem] overflow-auto divide-y divide-slate-100 bg-white">' + rows.join('') + '</div>' +
                '</div>';

            icons();
        }

        function renderPoolRow(row) {
            var base = renderPermitRow(row, poolSel, 'opu-pool-tick');

            // A per-row way out of the pool. Adding by hand is easy, so removing has to be.
            return base.replace('</label>',
                '<button type="button" class="opu-pool-remove flex-shrink-0 p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" ' +
                'title="Remove from this list" data-key="' + esc(key(row)) + '">' +
                '<i data-lucide="x" class="h-4 w-4"></i></button></label>');
        }

        function searchPool() {
            var serial = el.serial.value.trim();
            var fileNo = el.opFile.value.trim();

            if (!serial && !fileNo) {
                banner('warn', 'Give an OP serial number or a file number to search for.');
                return;
            }

            el.poolResults.classList.remove('hidden');
            el.poolResults.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-6 text-center text-sm text-slate-400">Searching…</div>';

            // include_matched: this page exists to deal with permits that ARE matched, so
            // the Match page's "hide anything already matched" rule must not apply here.
            get(urls.ops, { serial: serial, file_no: fileNo, include_matched: 1 }).then(function (payload) {
                poolResults = payload.data || [];
                renderPoolResults();
            }).catch(function (error) {
                poolResults = [];
                el.poolResults.classList.add('hidden');
                banner('error', error.message);
            });
        }

        function renderPoolResults() {
            el.poolSearchCount.textContent = poolResults.length + ' found';

            if (!poolResults.length) {
                el.poolResults.innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-400">' +
                    'No Occupancy Permit matches that search.</div>';
                return;
            }

            el.poolResults.innerHTML = '' +
                '<div class="rounded-lg border border-slate-200 overflow-hidden">' +
                  '<div class="max-h-64 overflow-auto divide-y divide-slate-100">' +
                    poolResults.map(function (r) {
                      var already = pool.has(key(r));
                      return '<div class="flex items-center gap-3 px-4 py-2.5 ' + (already ? 'bg-emerald-50/60' : '') + '">' +
                          '<div class="min-w-0 flex-1">' +
                            '<div class="text-sm font-semibold text-slate-900">' +
                              'Serial ' + dash(r.op_serial_number) + ' · ' + dash(r.file_no) +
                            '</div>' +
                            '<div class="text-[11px] text-slate-500 truncate">' + dash(r.holder) +
                              ' · ' + esc(r.source_table) + ' #' + esc(r.op_id) + '</div>' +
                          '</div>' +
                          '<span class="text-[11px] font-semibold text-slate-500 tabular-nums">PROP_ID ' + dash(r.prop_id) + '</span>' +
                          (already
                            ? '<span class="px-2 py-1 rounded bg-emerald-600 text-white text-[11px] font-semibold">added</span>'
                            : '<button type="button" class="opu-pool-add px-2.5 py-1 rounded-md bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-semibold transition" ' +
                              'data-key="' + esc(key(r)) + '">Add</button>') +
                        '</div>';
                    }).join('') +
                  '</div>' +
                '</div>';
        }

        // ------------------------------------------------------- 5: optional reassign
        function pickNewFile(fileNumber) {
            el.newFileSelected.classList.remove('hidden');
            el.newFileSelected.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-6 text-center text-sm text-slate-400">Loading the file…</div>';

            get(urls.file, { file_no: fileNumber }).then(function (payload) {
                newFile = payload.data;
                el.newFileDisplay.value = newFile.file_number;
                el.newFileClear.classList.remove('hidden');
                renderNewFile();
                updateMatch();
            }).catch(function (error) {
                clearNewFile();
                banner('error', error.message);
            });
        }

        function renderNewFile() {
            if (!newFile) { el.newFileSelected.classList.add('hidden'); return; }

            el.newFileSelected.classList.remove('hidden');
            el.newFileSelected.innerHTML = '' +
                '<div class="rounded-xl border border-violet-200 bg-violet-50/60 p-4 flex items-start justify-between gap-4">' +
                  '<div class="min-w-0">' +
                    '<div class="text-[11px] font-semibold uppercase tracking-wide text-violet-600">Destination file</div>' +
                    '<div class="text-lg font-bold text-slate-900">' + dash(newFile.file_number) + '</div>' +
                    '<div class="text-sm text-slate-600">' + dash(newFile.file_title) + '</div>' +
                  '</div>' +
                  '<div class="flex-shrink-0 text-right">' +
                    '<div class="text-[11px] font-semibold uppercase tracking-wide text-violet-600">Property ID</div>' +
                    '<div class="text-2xl font-black text-violet-700 tabular-nums">' + dash(newFile.prop_id) + '</div>' +
                    (newFile.prop_id_proposed
                      ? '<div class="text-[10px] font-semibold text-violet-500 uppercase">new · reserved when you match</div>'
                      : '') +
                  '</div>' +
                '</div>';
        }

        function clearNewFile() {
            newFile = null;
            el.newFileDisplay.value = '';
            el.newFileSelected.classList.add('hidden');
            el.newFileSelected.innerHTML = '';
            el.newFileClear.classList.add('hidden');
            updateMatch();
        }

        function updateMatch() {
            var n = poolSel.size;
            var ready = !!newFile && !!newFile.prop_id && n > 0 && !busy;

            el.matchBtn.disabled = !ready;
            el.matchLabel.textContent = n > 0 ? 'Match ' + n + ' OP(s) to New File' : 'Match to New File';

            if (!newFile && n === 0) {
                el.matchSummary.innerHTML = 'Choose a destination file only if these permits should be reassigned.';
            } else if (!newFile) {
                el.matchSummary.innerHTML = '<span class="text-slate-600">' + n + ' permit(s) selected above.</span> ' +
                    'Choose a destination file to reassign them — or leave this section alone and you are done.';
            } else if (n === 0) {
                el.matchSummary.innerHTML = 'Destination is <span class="font-semibold text-slate-800">' + esc(newFile.file_number) +
                    '</span>. Now select the permits to move in section 4.';
            } else {
                el.matchSummary.innerHTML =
                    '<span class="font-semibold text-slate-800">' + n + ' permit(s)</span> will move to Property ID ' +
                    '<span class="font-semibold text-violet-700">' + esc(newFile.prop_id) + '</span> — file ' +
                    '<span class="font-semibold text-slate-800">' + esc(newFile.file_number) + '</span>.';
            }
        }

        function runMatch() {
            if (!newFile || !newFile.prop_id || poolSel.size === 0 || busy || confirming) return;

            var ops = [];
            poolSel.forEach(function (row) {
                ops.push({ source_table: row.source_table, op_id: row.op_id });
            });

            confirming = true;
            confirmAction({
                title: 'Match ' + ops.length + ' permit(s) to ' + newFile.file_number + '?',
                icon: 'question',
                confirmText: 'Yes, match them',
                confirmColor: '#7c3aed',
                html: 'They will move onto <strong>Property ID ' + esc(String(newFile.prop_id)) + '</strong>.' +
                      '<br><br><span style="font-size:13px;color:#64748b;">This can be undone from the Match OP page.</span>'
            }).then(function (ok) {
                confirming = false;
                if (!ok) return;

                busy = true;
                el.matchBtn.disabled = true;
                el.matchLabel.textContent = 'Matching…';

                post(urls.match, {
                    file_no: newFile.file_number,
                    prop_id: newFile.prop_id,
                    prop_id_proposed: !!newFile.prop_id_proposed,
                    ops: ops,
                    move_companions: true
                }).then(function (payload) {
                    banner(payload.errors && payload.errors.length ? 'warn' : 'ok', payload.message);

                    // Matched permits leave the pool: they are on a file now, and leaving
                    // them here invites the same batch being sent to a second file.
                    poolSel.forEach(function (row, k) { pool.delete(k); });
                    poolSel.clear();

                    if (payload.prop_id) {
                        newFile.prop_id = payload.prop_id;
                        newFile.prop_id_proposed = false;
                        renderNewFile();
                    }

                    renderPool();
                    if (currentFile) loadAssociated();
                }).catch(function (error) {
                    banner('error', error.message);
                }).finally(function () {
                    busy = false;
                    updateMatch();
                });
            });
        }

        // ------------------------------------------------------- recent unlinks
        function loadBatches() {
            el.batches.innerHTML = '<div class="px-6 py-5 text-sm text-slate-400">Loading…</div>';

            get(urls.batches).then(function (payload) {
                var rows = payload.data || [];
                if (!rows.length) {
                    el.batches.innerHTML = '<div class="px-6 py-5 text-sm text-slate-400">No unlinks have been made yet.</div>';
                    return;
                }

                el.batches.innerHTML = '<div class="divide-y divide-slate-100">' + rows.map(function (b) {
                    return '<div class="px-6 py-3 flex items-center justify-between gap-4">' +
                        '<div class="min-w-0">' +
                          '<div class="text-sm font-medium text-slate-800">' +
                            b.records + ' record(s) ' +
                            (b.grouped
                              ? '<span class="text-violet-600">grouped on PROP_ID ' + esc(b.prop_id) + '</span>'
                              : '<span class="text-slate-500">given individual Property IDs</span>') +
                          '</div>' +
                          '<div class="text-[11px] text-slate-400 font-mono">' + esc(b.batch_ref) + ' · ' + dash(b.created_at) + '</div>' +
                        '</div>' +
                        (b.undone
                          ? '<span class="text-xs font-semibold text-slate-400">undone</span>'
                          : '<button type="button" class="opu-undo px-3 py-1.5 rounded-md text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 transition" ' +
                            'data-batch="' + esc(b.batch_ref) + '">Undo</button>') +
                      '</div>';
                }).join('') + '</div>';
            }).catch(function () {
                el.batches.innerHTML = '<div class="px-6 py-5 text-sm text-rose-500">The recent unlinks could not be loaded.</div>';
            });
        }

        function undoBatch(batchRef) {
            if (busy || confirming) return;

            confirming = true;
            confirmAction({
                title: 'Undo this unlink?',
                icon: 'warning',
                confirmText: 'Yes, put them back',
                html: 'Every record in <strong>' + esc(batchRef) + '</strong> goes back to the Property ID it had before.' +
                      '<br><br><span style="font-size:13px;color:#64748b;">Anything moved again since is left alone.</span>'
            }).then(function (ok) {
                confirming = false;
                if (!ok) return;

                post(urls.undo, { batch_ref: batchRef }).then(function (payload) {
                    banner('ok', payload.message);
                    loadBatches();
                    if (currentFile) loadAssociated();
                }).catch(function (error) {
                    banner('error', error.message);
                });
            });
        }

        // ------------------------------------------------------------------ wiring
        el.filePickBtn.addEventListener('click', function () { openPicker(pickCurrentFile); });
        el.fileDisplay.addEventListener('click', function () { openPicker(pickCurrentFile); });

        el.newFilePickBtn.addEventListener('click', function () { openPicker(pickNewFile); });
        el.newFileDisplay.addEventListener('click', function () { openPicker(pickNewFile); });
        el.newFileClear.addEventListener('click', clearNewFile);

        el.associated.addEventListener('change', function (event) {
            if (event.target.id === 'opu-assoc-all') {
                var on = event.target.checked;
                associated.forEach(function (r) {
                    if (on) associatedSel.set(key(r), r);
                    else associatedSel.delete(key(r));
                });
                renderAssociated();
                updateUnmatch();
                return;
            }

            if (event.target.classList.contains('opu-assoc-tick')) {
                var row = findIn(associated, event.target.dataset.key);
                if (!row) return;
                if (event.target.checked) associatedSel.set(key(row), row);
                else associatedSel.delete(key(row));
                renderAssociated();
                updateUnmatch();
            }
        });

        el.unmatchBtn.addEventListener('click', askAndUnmatch);

        el.poolSearchToggle.addEventListener('click', function () {
            var hidden = el.poolSearch.classList.toggle('hidden');
            el.poolSearchLabel.textContent = hidden ? 'Add other permits' : 'Hide search';
        });

        el.poolSearchBtn.addEventListener('click', searchPool);
        [el.serial, el.opFile].forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); searchPool(); }
            });
        });

        el.poolResults.addEventListener('click', function (event) {
            var add = event.target.closest('.opu-pool-add');
            if (!add) return;
            var row = findIn(poolResults, add.dataset.key);
            if (!row) return;
            pool.set(key(row), row);
            renderPool();
            renderPoolResults();
            updateMatch();
        });

        el.pool.addEventListener('click', function (event) {
            var remove = event.target.closest('.opu-pool-remove');
            if (remove) {
                event.preventDefault();
                pool.delete(remove.dataset.key);
                poolSel.delete(remove.dataset.key);
                renderPool();
                renderPoolResults();
                updateMatch();
                return;
            }

            if (event.target.id === 'opu-pool-clear') {
                pool.clear();
                poolSel.clear();
                renderPool();
                renderPoolResults();
                updateMatch();
            }
        });

        el.pool.addEventListener('change', function (event) {
            if (event.target.id === 'opu-pool-all') {
                var on = event.target.checked;
                pool.forEach(function (row, k) {
                    if (on) poolSel.set(k, row);
                    else poolSel.delete(k);
                });
                renderPool();
                updateMatch();
                return;
            }

            if (event.target.classList.contains('opu-pool-tick')) {
                var k = event.target.dataset.key;
                var row = pool.get(k);
                if (!row) return;
                if (event.target.checked) poolSel.set(k, row);
                else poolSel.delete(k);
                renderPool();
                updateMatch();
            }
        });

        el.matchBtn.addEventListener('click', runMatch);

        el.batches.addEventListener('click', function (event) {
            var undo = event.target.closest('.opu-undo');
            if (undo) undoBatch(undo.dataset.batch);
        });

        el.batchesRefresh.addEventListener('click', loadBatches);

        el.banner.addEventListener('click', function (event) {
            if (event.target.closest('.opu-banner-close')) clearBanner();
        });

        el.reset.addEventListener('click', function () {
            currentFile = null;
            associated = [];
            associatedSel.clear();
            pool.clear();
            poolSel.clear();
            poolResults = [];
            el.fileDisplay.value = '';
            el.fileSelected.classList.add('hidden');
            el.serial.value = '';
            el.opFile.value = '';
            el.poolResults.classList.add('hidden');
            el.poolSearchCount.textContent = '';
            clearNewFile();
            renderAssociated();
            renderPool();
            updateUnmatch();
            clearBanner();
        });

        renderAssociated();
        renderPool();
        updateUnmatch();
        updateMatch();
        loadBatches();
        icons();
    });
})();

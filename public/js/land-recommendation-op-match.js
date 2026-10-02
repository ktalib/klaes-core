/**
 * OP-holder Match on the Recommendation capture form.
 *
 * When the officer picks a file whose Occupancy Permit was granted to one person
 * while File Indexing holds another — and no transfer on the file explains the
 * change — this draws the file's chain and offers Match. Match writes the missing
 * Transfer of Title server-side; the officer is never asked to key it, because it is
 * one row with two names already on the file and hand-keying it is how the wrong
 * parties, the wrong file, or nothing at all gets recorded.
 *
 * After a Match the capture continues in "existing recommendation" mode: this file
 * has been through recommendation before, that letter is approved and will not go
 * for approval again, so no new one is generated. The record is still saved — the
 * RofO is generated and printed from it — and the approved letter is uploaded on the
 * register, which is what unlocks Approve.
 *
 * Batch mode is out of scope: a batch is a subdivision mother's children, which do
 * not come from OSS.
 *
 * The card is fail-open. A file that cannot be checked (network, a 500, a file with
 * no indexing row) captures exactly as it always did — this must never be the reason
 * an ordinary recommendation cannot be saved.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('land-recommendation-form');
        var fileNoInput = document.getElementById('file_number');
        var host = document.getElementById('op-match-card');

        if (!form || !fileNoInput || !host) return;

        var checkUrl = form.dataset.opmatchCheckUrl || '';
        var matchUrl = form.dataset.opmatchUrl || '';
        var csrf = form.querySelector('input[name="_token"]');

        // The Match OP page: the same card with nothing else on it, so a write is the
        // end of a job rather than a step in a capture. See match_op.blade.php.
        var standalone = form.dataset.opmatchStandalone === '1';

        var flagInput = document.getElementById('is_existing_recommendation');
        var totInput = document.getElementById('op_match_tot_pra_id');
        var batchToggle = document.getElementById('batch-mode-toggle');

        var lastChecked = '';
        var inFlight = null;

        function esc(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function batchOn() {
            return !!(batchToggle && batchToggle.checked);
        }

        /** Clear the card and every trace of the mode it may have switched on. */
        function reset() {
            host.innerHTML = '';
            host.classList.add('hidden');
            setExistingMode(false, null);
            setSubmitBlocked(false);
        }

        /**
         * Hold the save button shut while the file still needs its transfer.
         *
         * The submit handler below refuses these saves anyway, and the server refuses
         * them again — but a button that looks ready and then throws a dialog reads as
         * a fault. Disabled with a title explaining why is the honest state.
         *
         * Only ever ON for a file the card is actively offering Match for; reset()
         * turns it off, so no other capture can inherit a disabled button.
         */
        function setSubmitBlocked(on) {
            form.querySelectorAll('[type="submit"]').forEach(function (btn) {
                btn.disabled = !!on;
                btn.classList.toggle('opacity-40', !!on);
                btn.classList.toggle('cursor-not-allowed', !!on);
                if (on) {
                    btn.setAttribute('title', 'Press Match first — the Occupancy Permit has a different name and no dealing on this file explains the change.');
                } else {
                    btn.removeAttribute('title');
                }
            });
        }

        /**
         * "This file keeps its existing approved recommendation."
         *
         * Carried on hidden inputs so the server records the decision rather than
         * re-deriving it: Match writes the very row whose absence made the file
         * qualify, so the condition is gone by the time the form is submitted.
         */
        function setExistingMode(on, totId) {
            if (flagInput) flagInput.value = on ? '1' : '0';
            if (totInput) totInput.value = on && totId ? String(totId) : '';

            // The submit button is deliberately left alone. Saving is saving, whatever
            // mode the file is in, and uploading the approved letter is a separate act
            // that lives in the record's action menu on the register — not something
            // one button both promises and cannot actually do.
        }

        /**
         * Shown from the moment a file number is picked until its answer arrives.
         *
         * The chain is read through the Legal Search report engine — four registers,
         *3-5 seconds on a cold file — and the card simply appearing some seconds
         * later reads as a page that did nothing. It also stands in for the idle
         * notice on the Match OP page, which keys off this host being visible.
         */
        function showChecking(fileNo) {
            host.innerHTML = ''
                + '<div class="rounded-xl border border-slate-200 bg-white p-5">'
                +   '<div class="flex items-center gap-3">'
                +     '<i data-lucide="loader" class="h-5 w-5 animate-spin text-blue-600"></i>'
                +     '<div>'
                +       '<h3 class="text-sm font-bold text-slate-800">Checking ' + esc(fileNo) + '…</h3>'
                +       '<p class="mt-0.5 text-xs text-slate-500">Reading the file history from all four registers. This can take a few seconds.</p>'
                +     '</div>'
                +   '</div>'
                +   '<div class="mt-4 space-y-2">'
                +     '<div class="h-3 w-1/3 rounded bg-slate-100 animate-pulse"></div>'
                +     '<div class="h-3 w-2/3 rounded bg-slate-100 animate-pulse"></div>'
                +     '<div class="h-3 w-1/2 rounded bg-slate-100 animate-pulse"></div>'
                +   '</div>'
                + '</div>';

            host.classList.remove('hidden');
            if (window.lucide) window.lucide.createIcons();
        }

        function timelineRows(rows, state) {
            if (!rows || !rows.length) {
                return '<p class="text-xs text-slate-500 italic">No transactions are recorded on this file.</p>';
            }

            var needsTot = !!(state && state.applies);
            var isMatched = !!(state && state.matched);

            return rows.map(function (r) {
                // The OP row carries the verdict, because the OP is what the verdict is
                // about: red while the transfer out of it is missing, green once it is
                // on file. Every other row keeps its ordinary tone.
                var tone;
                if (r.is_op && needsTot) {
                    tone = 'border-rose-300 bg-rose-50';
                } else if (r.is_op && isMatched) {
                    tone = 'border-emerald-300 bg-emerald-50';
                } else {
                    tone = r.is_op ? 'border-amber-300 bg-amber-50'
                        : (r.is_tot ? 'border-blue-500 bg-blue-100 ring-1 ring-blue-200' : 'border-slate-200 bg-white');
                }

                // Which register the row came from — the same four sources the
                // Property Timeline labels, so a reader can tell at a glance that
                // this is the file's whole history and not just its deeds.
                var source = r.source
                    ? '<span class="ml-2 align-middle rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide bg-slate-200/80 text-slate-600">'
                        + esc(String(r.source).replace(/_staging$/, '').replace(/_/g, ' ')) + '</span>'
                    : '';

                // The row the report engine names as the root of title, badged the way
                // the Property Timeline badges it — the card argues about the OP, so
                // which row IS the root should not have to be inferred from the colour.
                // "-RoT" rather than a chip repeating the instrument name: the row already
                // says "Occupancy Permit (Op)", so "RoT: Occupancy Permit (OP)" beside it
                // said it twice. Same marker the Legal Search timeline table uses.
                // Only on the transfer this flow wrote — an officer-captured transfer
                // between the same two people is a real deed and must not be labelled
                // as something the system invented.
                var sysgen = r.system_generated
                    ? '<span class="ml-2 align-middle rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide bg-blue-600 text-white">New (System Generated)</span>'
                    : '';

                var rot = r.root_of_title
                    ? '<span class="ml-1.5 align-middle text-[10px] font-bold italic text-violet-700">-RoT</span>'
                    : '';

                // The parcel the permit was granted over. On a merger this is the only
                // thing that tells two otherwise identical OP rows apart — both read
                // "Occupancy Permit (OP)" from "Kano State Government", and only the
                // plot shows that two separate properties are being combined.

                return ''
                    + '<li class="relative pl-6 pb-3 last:pb-0">'
                    +   '<span class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full ' + ((r.is_op && needsTot) ? 'bg-rose-500' : ((r.is_op && isMatched) ? 'bg-emerald-500' : (r.is_op ? 'bg-amber-500' : (r.is_tot ? 'bg-blue-500' : 'bg-slate-300')))) + '"></span>'
                    +   '<div class="rounded-lg border ' + tone + ' px-3 py-2">'
                    +     '<div class="flex items-center justify-between gap-3">'
                    +       '<span class="text-xs font-bold text-slate-800">' + esc(r.type) + rot + source + sysgen + '</span>'
                    +       '<span class="text-[10px] text-slate-500 whitespace-nowrap">' + esc(r.date || '—') + '</span>'
                    +     '</div>'
                    +     '<div class="mt-1 text-[11px] text-slate-600">'
                    +       '<span class="font-medium">' + esc(r.party_1 || '—') + '</span>'
                    +       ' <span class="text-slate-400">&rarr;</span> '
                    +       '<span class="font-medium">' + esc(r.party_2 || '—') + '</span>'
                    +     '</div>'
                    +   '</div>'
                    + '</li>';
            }).join('');
        }

        /**
         * "Checked — there is nothing to match on this file."
         *
         * Deliberately plain and grey: this is not a warning and not a success, it is
         * a file the officer can cross off. The reason is the server's own sentence,
         * so the page never invents an explanation of its own.
         */
        function renderNoAction(state) {
            host.innerHTML = ''
                + '<div class="rounded-xl border border-slate-200 bg-white p-5">'
                +   '<div class="flex items-start gap-3">'
                +     '<span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">'
                +       '<i data-lucide="circle-slash" class="h-4 w-4"></i></span>'
                +     '<div class="min-w-0">'
                +       '<h3 class="text-sm font-bold text-slate-800">Nothing to match on '
                +         '<span class="font-mono">' + esc(state.file_number || 'this file') + '</span></h3>'
                +       '<p class="mt-0.5 text-xs text-slate-600">' + esc(state.reason || 'This file is not in the state Match applies to.') + '</p>'
                +       '<p class="mt-2 text-[11px] text-slate-400">Pick the next file number to carry on.</p>'
                +     '</div>'
                +   '</div>'
                + '</div>';

            host.classList.remove('hidden');
            if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
        }

        /** The card: what the file says, why it is a problem, and the one action. */
        function render(state) {
            var applies = !!state.applies;

            // The verdict leads the card rather than sitting on the OP row: it is the
            // answer to the question the officer opened this card with, so it should be
            // the first thing read, not something found by scanning the history. The
            // row keeps its red or green tone, which is what ties the badge to it.
            var merger = state.merger || null;
            var matchInfo = state.match || null;

            // The file was matched EARLIER, on the Match OP page, and is only now being
            // captured — the two-officer split that page exists for. The check comes
            // back "matched" with nothing left to press, so without this the capture
            // records an ordinary recommendation and the whole consequence of matching
            // (no new letter, upload the extant one, approval waits for it) is lost.
            //
            // Derived from the transfer THIS FLOW wrote, never from "matched" alone: a
            // file bridged by a deed an officer keyed off paper has had no recommendation
            // yet, and forcing it into existing mode would demand a letter that does not
            // exist. system_generated is the server's answer to "did Match write this",
            // matched on instrument AND both parties.
            var writtenHere = !!(matchInfo && matchInfo.kind === 'dealing' && matchInfo.system_generated);
            if (writtenHere && !standalone && !batchOn()) {
                setExistingMode(true, matchInfo.pra_id || null);
            }

            var verdict = '';
            if (applies) {
                verdict = '<div class="mb-2 flex flex-wrap items-center gap-2"><span class="inline-flex items-center gap-1.5 rounded px-2 py-1 text-[10px] font-bold tracking-wide bg-rose-600 text-white">'
                    + '<i data-lucide="alert-triangle" class="h-3 w-3"></i> ToT Detected (Unmatched OP)</span>'
                    // A second badge rather than different wording in the first: the file
                    // is in the ordinary unmatched state AND it is a merger, and the
                    // officer needs to read both facts, not one instead of the other.
                    + (merger
                        ? '<span class="inline-flex items-center gap-1.5 rounded px-2 py-1 text-[10px] font-bold tracking-wide bg-violet-600 text-white">'
                            + '<i data-lucide="git-merge" class="h-3 w-3"></i> OP Merger &mdash; ' + merger.op_count + ' OPs &rarr; 1 ToT</span>'
                        : '')
                    + '</div>';
            } else if (state.matched) {
                verdict = '<div class="mb-2"><span class="inline-flex items-center gap-1.5 rounded px-2 py-1 text-[10px] font-bold tracking-wide bg-emerald-600 text-white">'
                    + '<i data-lucide="check" class="h-3 w-3"></i> Matched</span></div>';
            }

            var head = applies
                ? '<div class="flex items-start gap-3">'
                +   '<span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700"><i data-lucide="alert-triangle" class="h-4 w-4"></i></span>'
                +   '<div>'
                +     '<h3 class="text-sm font-bold text-amber-900">' + (merger
                            ? 'Several Occupancy Permits, one merged file'
                            : 'The Occupancy Permit has a different name') + '</h3>'
                +     '<p class="mt-0.5 text-xs text-amber-800">' + esc(state.reason) + '</p>'
                +   '</div>'
                + '</div>'
                : (state.name_spelling_only
                    ? '<div class="flex items-start gap-3">'
                    +   '<span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700"><i data-lucide="spell-check" class="h-4 w-4"></i></span>'
                    +   '<div>'
                    +     '<h3 class="text-sm font-bold text-sky-900">Same holder, spelt two ways</h3>'
                    +     '<p class="mt-0.5 text-xs text-sky-800">' + esc(state.reason) + '</p>'
                    +   '</div>'
                    + '</div>'
                    : '<div class="flex items-start gap-3">'
                    +   '<span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">'
                    +     '<i data-lucide="' + (matchInfo && matchInfo.kind === 'merger_sibling' ? 'file-symlink' : 'check') + '" class="h-4 w-4"></i></span>'
                    +   '<div>'
                    // "Matched" is the state the officer is looking for — but on its own it
                    // does not say WHY, and the three ways a file can be matched call for
                    // different things afterwards. The heading names the case; the sentence
                    // below says what, if anything, is left to do.
                    +     '<h3 class="text-sm font-bold text-emerald-900">'
                    +       esc(matchInfo ? matchInfo.title : (state.matched ? 'Occupancy Permit (OP) Matched' : 'File history')) + '</h3>'
                    +     '<p class="mt-0.5 text-xs text-emerald-800">'
                    +       esc(matchInfo ? matchInfo.detail : state.reason) + '</p>'
                    +   '</div>'
                    + '</div>');

            // The two names are the whole point of the card, so they carry a colour
            // each and keep it in every state: amber is always the holder the chain
            // records (the OP), indigo is always the name File Indexing holds. The
            // arrow between them is the direction a Match would transfer in.
            //
            // Same two colours on a file that needs no action — a reader who has seen
            // one card can read the next without re-learning what the tints mean.
            // On a merger the left side is not one root of title but several, so it
            // lists them: each permit, its holder and its plot, stacked. That is the
            // whole claim the card is making — two separate properties, granted to two
            // different people, becoming one file — and it should be readable without
            // opening the history below.
            // Every permit the file is rooted in. Before a Match that list comes off the
            // merger block; after one there is no merger block any more — the file is
            // still rooted in both permits, so the box must not shrink to the first name
            // just because the transfer combining them now exists. state.roots carries
            // them in every state, from the same pra rows the merger is built from.
            var rootList = merger ? merger.grants : (state.roots || []);
            var rootNames = rootList.map(function (g) { return g.holder || '—'; });
            var rootNamesText = rootNames.length > 1
                ? rootNames.slice(0, -1).join(', ') + ' and ' + rootNames[rootNames.length - 1]
                : (rootNames[0] || '—');

            var rootBox = (rootList.length > 1)
                ? '<div class="flex-1 rounded-lg border border-amber-300 bg-amber-100/70 px-3 py-2">'
                +   '<div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Roots of title (' + rootList.length + ' Occupancy Permits)</div>'
                +   '<div class="mt-1 text-xs font-bold leading-relaxed text-amber-950">' + esc(rootNamesText)
                +   '</div>'
                + '</div>'
                : '<div class="flex-1 rounded-lg border border-amber-300 bg-amber-100/70 px-3 py-2">'
                +   '<div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Root of title</div>'
                +   '<div class="text-xs font-bold text-amber-950">' + esc(state.root_of_title || '—') + '</div>'
                + '</div>';

            var names = ''
                + '<div class="mt-3 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">'
                +   rootBox
                +   '<div class="shrink-0 self-center text-slate-400">'
                +     '<i data-lucide="arrow-right" class="h-4 w-4 hidden sm:block"></i>'
                +     '<i data-lucide="arrow-down" class="h-4 w-4 sm:hidden"></i>'
                +   '</div>'
                +   '<div class="flex-1 rounded-lg border border-indigo-300 bg-indigo-100/70 px-3 py-2">'
                +     '<div class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">File Indexing name</div>'
                +     '<div class="text-xs font-bold text-indigo-950">' + esc(state.indexing_name || '—') + '</div>'
                +   '</div>'
                + '</div>';

            // The record that accounts for the file, pulled out of the history and shown
            // on its own. On a matched file the history below can be a dozen rows, and
            // "which one of these is the answer?" is the question the officer is actually
            // asking — leaving them to find it is what made "Matched" feel like a dead end.
            var accounted = '';
            if (matchInfo) {
                var sysTag = matchInfo.system_generated
                    ? '<span class="ml-2 align-middle rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide bg-blue-600 text-white">System Generated</span>'
                    : '';
                var elsewhere = (matchInfo.kind === 'merger_sibling');

                accounted = ''
                    + '<div class="mt-3 rounded-lg border ' + (elsewhere ? 'border-amber-300 bg-amber-50' : 'border-emerald-300 bg-emerald-50/80') + ' px-3 py-2.5">'
                    +   '<div class="text-[10px] font-bold uppercase tracking-wider ' + (elsewhere ? 'text-amber-700' : 'text-emerald-700') + '">'
                    +     (elsewhere ? 'Recorded under ' + esc(matchInfo.file_no) : 'What accounts for this file')
                    +   '</div>'
                    +   '<div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">'
                    +     '<span class="text-xs font-bold text-slate-800">' + esc(matchInfo.type || '—') + '</span>'
                    +     (matchInfo.source
                            ? '<span class="rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide bg-slate-200/80 text-slate-600">'
                                + esc(String(matchInfo.source).replace(/_staging$/, '').replace(/_/g, ' ')) + '</span>'
                            : '')
                    +     sysTag
                    +     (matchInfo.date ? '<span class="text-[10px] text-slate-500">' + esc(matchInfo.date) + '</span>' : '')
                    +   '</div>'
                    +   '<div class="mt-1 text-[11px] text-slate-700">'
                    +     '<span class="font-medium">' + esc(matchInfo.party_1 || '—') + '</span>'
                    +     ' <span class="text-slate-400">&rarr;</span> '
                    +     '<span class="font-medium">' + esc(matchInfo.party_2 || '—') + '</span>'
                    +   '</div>'
                    // Only the merger-sibling case leaves something undone, so only it gets
                    // a follow-up line. The other two are genuinely finished, and telling an
                    // officer to "check" a file that is fine wastes the trip.
                    +   (elsewhere
                            ? '<p class="mt-2 flex items-start gap-1.5 text-[11px] text-amber-900 border-t border-amber-200 pt-2">'
                                + '<i data-lucide="corner-down-right" class="h-3.5 w-3.5 shrink-0 mt-px"></i>'
                                + '<span>Search <b>' + esc(matchInfo.file_no) + '</b> in Legal Search to see the transfer itself. '
                                + 'Moving it onto this file number is a records correction, not something Match can do.</span></p>'
                            : '<p class="mt-2 flex items-center gap-1.5 text-[11px] font-semibold ' + (elsewhere ? 'text-amber-800' : 'text-emerald-800') + ' border-t border-emerald-200 pt-2">'
                                + '<i data-lucide="check-circle-2" class="h-3.5 w-3.5 shrink-0"></i>'
                                + '<span>' + (writtenHere && !standalone
                                        ? 'Nothing further on the chain — carry on with the capture below.'
                                        : 'No action needed on this file.') + '</span></p>')
                    + '</div>';
            }

            // On the Match OP page the officer works through files one after another, so a
            // finished file should hand them straight to the next one rather than leaving
            // them to clear the box by hand.
            var nextFile = (matchInfo && standalone)
                ? '<div class="mt-3">'
                +   '<button type="button" id="op-match-next" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-900 transition">'
                +     '<i data-lucide="arrow-right" class="h-4 w-4"></i> Check another file'
                +   '</button>'
                + '</div>'
                : '';

            var action = applies
                ? '<div class="mt-3 flex flex-wrap items-center gap-3">'
                +   '<button type="button" id="op-match-btn" class="inline-flex items-center gap-2.5 px-8 py-3.5 bg-amber-600 text-white text-base font-bold rounded-xl hover:bg-amber-700 transition shadow-lg shadow-amber-200">'
                +     '<i data-lucide="git-merge" class="h-5 w-5"></i> ' + (merger ? 'Match OP Merger' : 'Match')
                +   '</button>'
                +   (merger
                        // Says "one" explicitly. The officer is looking at two permits and
                        // the reasonable fear is that pressing this writes two transfers;
                        // the sentence answers that before the confirmation repeats it.
                        ? '<span class="text-xs text-slate-600 max-w-md">Records <b>one</b> Transfer of Title from '
                            + '<b class="text-amber-800">' + esc(merger.party_1) + '</b>'
                            + ' to <b class="text-indigo-800">' + esc(state.indexing_name) + '</b>, combining all '
                            + merger.op_count + ' permits'
                            + (merger.plots && merger.plots.length
                                ? ' (' + merger.plots.map(function (p) { return 'Plot ' + esc(p); }).join(' + ') + ')'
                                : '')
                            + ' into this file, then continues the capture.</span>'
                        : '<span class="text-xs text-slate-600 max-w-md">Records the transfer from '
                            + '<b class="text-amber-800">' + esc(state.op ? state.op.holder : '') + '</b>'
                            + ' to <b class="text-indigo-800">' + esc(state.indexing_name) + '</b>, then continues the capture.</span>')
                + '</div>'
                : '';

            // Once the file is in "existing recommendation" mode the fact belongs to
            // this card, not to a panel of its own — the save button already says it
            // too, and a full-width box for one sentence pushed the form down.
            var mode = (flagInput && flagInput.value === '1')
                ? '<p class="mt-3 flex items-start gap-2 text-[11px] text-violet-800 border-t border-violet-200/70 pt-3">'
                +   '<i data-lucide="file-check-2" class="h-3.5 w-3.5 shrink-0 mt-px"></i>'
                +   '<span><b>No new recommendation is generated for this file.</b> Its letter was already approved — save the record, then upload that letter from the action menu on the register. The approval waits for it.</span>'
                + '</p>'
                : '';

            host.innerHTML = ''
                + '<div class="rounded-xl border ' + (applies ? 'border-amber-200 bg-amber-50/60'
                        : (state.name_spelling_only ? 'border-sky-200 bg-sky-50/60' : 'border-emerald-200 bg-emerald-50/50')) + ' p-5">'
                +   verdict
                +   head
                +   names
                +   accounted
                +   '<details class="mt-3" ' + (applies ? 'open' : '') + '>'
                +     '<summary class="cursor-pointer text-[11px] font-bold uppercase tracking-wider text-slate-500 hover:text-slate-700">File history (' + (state.timeline || []).length + ')</summary>'
                +     '<ul class="mt-2 border-l border-slate-200 ml-1">' + timelineRows(state.timeline, state) + '</ul>'
                +   '</details>'
                +   action
                +   nextFile
                +   mode
                + '</div>';

            host.classList.remove('hidden');
            setSubmitBlocked(applies);
            if (window.lucide) window.lucide.createIcons();

            var next = document.getElementById('op-match-next');
            if (next) {
                next.addEventListener('click', function () { clearForNextFile(); });
            }

            var btn = document.getElementById('op-match-btn');
            if (btn) {
                btn.addEventListener('click', function () {
                    // Locked on the first click, before the confirmation opens. The
                    // dialog blocks the page while it is up, but the gap between the
                    // click and it appearing is enough for a second click to queue a
                    // second write — and this writes a dealing on somebody's title.
                    if (btn.disabled) return;
                    lockMatchButton(btn, 'Opening…');

                    confirmMatch(
                        state,
                        function () { runMatch(btn); },
                        function () { unlockMatchButton(btn); }   // cancelled
                    );
                });
            }
        }

        /**
         * Ask before writing.
         *
         * Match puts a real Transfer of Title into the deeds register — a dealing on
         * somebody's title — and it is one click away from a button the officer may
         * have opened the card by accident. The dialog states the two names, the file
         * and the direction, so what is about to be recorded is read once before it
         * exists rather than discovered afterwards.
         */
        /** Disabled, dimmed and spinning, so a second click has nothing to hit. */
        function lockMatchButton(btn, label) {
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            btn.innerHTML = '<i data-lucide="loader" class="h-5 w-5 animate-spin"></i> ' + label;
            if (window.lucide) window.lucide.createIcons();
        }

        function unlockMatchButton(btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-70', 'cursor-not-allowed');
            btn.innerHTML = '<i data-lucide="git-merge" class="h-5 w-5"></i> Match';
            if (window.lucide) window.lucide.createIcons();
        }

        function confirmMatch(state, onConfirm, onCancel) {
            var fileNo = fileNoInput.value.trim();
            var merger = (state && state.merger) ? state.merger : null;
            var from = merger ? merger.party_1 : ((state && state.op) ? state.op.holder : '');
            var to = (state && state.indexing_name) ? state.indexing_name : '';

            if (typeof Swal === 'undefined') {
                if (window.confirm('Record a Transfer of Title on ' + fileNo + ' from ' + from + ' to ' + to + '?')) {
                    onConfirm();
                } else if (typeof onCancel === 'function') {
                    onCancel();
                }
                return;
            }

            Swal.fire({
                icon: 'question',
                title: merger ? 'Record this merger?' : 'Record this transfer?',
                html: ''
                    + (merger
                        ? '<p class="text-sm text-slate-600"><b>One</b> Transfer of Title will be recorded on <b>' + esc(fileNo)
                            + '</b>, combining ' + merger.op_count + ' Occupancy Permits'
                            + (merger.plots && merger.plots.length
                                ? ' over ' + merger.plots.map(function (p) { return 'Plot ' + esc(p); }).join(' and ')
                                : '')
                            + '. No separate transfer is written for each permit.</p>'
                        : '<p class="text-sm text-slate-600">A Transfer of Title will be recorded on <b>' + esc(fileNo) + '</b>:</p>')
                    + '<div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left">'
                    +   '<div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">From ('
                    +     (merger ? merger.op_count + ' Occupancy Permit holders' : 'Occupancy Permit holder') + ')</div>'
                    +   '<div class="text-sm font-bold text-slate-900">' + esc(from) + '</div>'
                    +   '<div class="mt-2 text-[10px] font-bold uppercase tracking-wider text-indigo-700">To (File Indexing name)</div>'
                    +   '<div class="text-sm font-bold text-slate-900">' + esc(to) + '</div>'
                    + '</div>'
                    + '<p class="mt-3 text-xs text-slate-500">It is recorded with no registration particulars (0/0/0), as a reconstructed transfer that was never presented to the registry.</p>',
                showCancelButton: true,
                confirmButtonText: 'Yes, record it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    onConfirm();
                } else if (typeof onCancel === 'function') {
                    onCancel();
                }
            });
        }

        function runMatch(btn) {
            var fileNo = fileNoInput.value.trim();
            if (!fileNo || !matchUrl) return;

            lockMatchButton(btn, 'Matching…');

            var body = new FormData();
            body.append('file_number', fileNo);
            if (csrf) body.append('_token', csrf.value);

            fetch(matchUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (res) {
                    if (!res.ok || !res.data.success) {
                        throw new Error(res.data.message || 'The transfer could not be recorded.');
                    }

                    // The write is what puts this file into "existing recommendation"
                    // mode — the recommendation it already has was approved on paper.
                    setExistingMode(true, res.data.pra_id);
                    render(res.data.data || {});
                    // render() re-reads the fresh state, which no longer applies — but be
                    // explicit rather than relying on that to unlock the save.
                    setSubmitBlocked(false);

                    // What the page around the card does about a write is the page's
                    // business, not this script's: the Match OP page adds the new row to
                    // its table, and the capture form ignores it. Announced rather than
                    // called, so neither page has to be known here.
                    document.dispatchEvent(new CustomEvent('opmatch:recorded', {
                        detail: {
                            file_number: fileNo,
                            pra_id: res.data.pra_id,
                            message: res.data.message,
                            state: res.data.data || {}
                        }
                    }));

                    if (standalone) {
                        afterStandaloneMatch(res.data.message);
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Transfer recorded',
                            html: '<p class="text-sm text-slate-600">' + esc(res.data.message) + '</p>'
                                + '<p class="text-xs text-slate-500 mt-2">This file already has an approved recommendation, so a new one will not be generated. '
                                + 'The Extant Recommendation enables the Approval.</p>',
                            confirmButtonColor: '#d97706'
                        });
                    }
                })
                .catch(function (err) {
                    unlockMatchButton(btn);

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Not matched', text: err.message, confirmButtonColor: '#dc2626' });
                    }
                });
        }

        /**
         * On the Match OP page the officer is working a list, so the question after a
         * write is simply whether there is another one. Yes clears the file number and
         * leaves them on a ready page; No reloads, which is the same thing but from a
         * clean slate — and is what they want when the run is finished.
         */
        function afterStandaloneMatch(message) {
            if (typeof Swal === 'undefined') {
                if (window.confirm(message + '\n\nMatch another file?')) {
                    clearForNextFile();
                } else {
                    window.location.reload();
                }
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Transfer recorded',
                html: '<p class="text-sm text-slate-600">' + esc(message) + '</p>'
                    + '<p class="text-sm font-semibold text-slate-700 mt-3">Do you want to match another file?</p>',
                showCancelButton: true,
                confirmButtonText: 'Yes, match another',
                cancelButtonText: 'No, I am done',
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    clearForNextFile();
                } else {
                    window.location.reload();
                }
            });
        }

        /** Ready for the next file: no file number, no card, nothing carried over. */
        function clearForNextFile() {
            fileNoInput.value = '';
            lastChecked = '';
            reset();

            if (window.jQuery) window.jQuery(fileNoInput).trigger('change');
            else fileNoInput.dispatchEvent(new Event('change'));

            var pick = document.getElementById('select-fileno-btn');
            if (pick) pick.focus();
        }

        function check() {
            var fileNo = fileNoInput.value.trim();

            if (batchOn() || !fileNo || !checkUrl) {
                reset();
                lastChecked = '';
                return;
            }

            if (fileNo === lastChecked) return;
            lastChecked = fileNo;

            showChecking(fileNo);

            // A second selection while the first is still in the air must not paint
            // the earlier file's answer over the later one.
            if (inFlight) inFlight.abort();
            var controller = ('AbortController' in window) ? new AbortController() : null;
            inFlight = controller;

            fetch(checkUrl + '?file_number=' + encodeURIComponent(fileNo), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (payload) {
                    if (!payload || !payload.success) { reset(); return; }

                    var state = payload.data || {};

                    // The card is worth the space whenever the file has an Occupancy
                    // Permit at its root — which is every state below. An action to
                    // take; a transfer already on file whose name does not match, which
                    // is the cue that a correction rather than a new transfer is needed;
                    // or a file that is already accounted for, which is an answer too.
                    //
                    // `matched` used to fall through to the empty "select a file" panel,
                    // so re-selecting a file somebody had just matched looked as though
                    // nothing had been checked at all. A file that IS matched is exactly
                    // the file an officer wants confirmation about.
                    if (state.applies || state.matched || state.has_working_transfer || state.name_spelling_only) {
                        setExistingMode(false, null);
                        render(state);
                    } else {
                        reset();

                        // The file WAS checked and the answer is "nothing to match here"
                        // — a file with no Occupancy Permit at its root, or no indexing
                        // row to compare one against. On the capture form that is
                        // rightly silent: an ordinary capture just carries on. On the
                        // Match OP page, where checking files IS the job, falling back
                        // to "Select a file number to check it" reads as though the
                        // check never ran, and the officer has no way to tell a file
                        // they have cleared from one they have not looked at.
                        if (standalone) renderNoAction(state);
                    }
                })
                .catch(function (err) {
                    // Fail open — an ordinary capture is unaffected — but never leave
                    // the spinner turning, which would read as a check still running.
                    if (err && err.name === 'AbortError') return;   // superseded by a newer pick
                    reset();
                });
        }

        // Saving without pressing Match would write a recommendation on top of a
        // chain that still does not explain how the title reached the applicant. The
        // server refuses this too; this only saves the round trip and keeps the
        // officer on the card that has the button.
        form.addEventListener('submit', function (event) {
            if (standalone) return;   // nothing is submitted from the Match OP page

            var pending = document.getElementById('op-match-btn');
            if (!pending || (flagInput && flagInput.value === '1')) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Match this file first',
                    text: 'The Occupancy Permit has a different name and no transfer on the file explains the change. '
                        + 'Press Match to record it, then save.',
                    confirmButtonColor: '#d97706'
                }).then(function () {
                    host.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            } else {
                host.scrollIntoView();
            }
        // Capture phase: this has to run before the form's own submit handlers, or
        // the save is already on its way by the time it is refused.
        }, true);

        // The file-number selector writes the field with jQuery's .val().trigger('change'),
        // which never reaches a native listener — so bind through jQuery as well.
        fileNoInput.addEventListener('change', check);
        if (window.jQuery) window.jQuery(fileNoInput).on('change', check);
        if (batchToggle) batchToggle.addEventListener('change', check);

        // An edit page opens with a file number already in the field.
        if (fileNoInput.value.trim() !== '') check();
    });
})();

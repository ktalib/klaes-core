/**
 * Related File (Physical File is Sighted) — File Indexing (user, 2026-10-28).
 *
 * Adds a Yes / No answer under every Related File Number row. No marks the related
 * file NPF (No Physical File): Quick Search then stops treating the pair as kept
 * inside each other. Yes may carry "Cover missing – contents sighted". Neither is
 * preselected; a related file with no answer cannot be saved (the server refuses too).
 *
 * Rows are built, cloned and refilled from several places (create-indexing-dialog.js,
 * the Blade forms), often without an input event, so the answers are attached to
 * whatever rows exist and refreshed on DOM changes and a light poll. The answers go
 * to the server as JSON in #related-file-sightings (App\Services\FileIndexing\RelatedFileSightings).
 */
(function () {
    'use strict';
    if (window.RelatedFileSighting) return;

    const ROW_INPUT = 'input[name="related_fileno[]"]';
    let hidden = null;
    let stored = {};          // key -> {sighted, cover_missing} from the saved record
    let uidSeq = 0;
    let scheduled = false;

    const key = v => String(v || '').toUpperCase().replace(/\s+/g, '');
    const requiredMessage = () => hidden?.dataset.requiredMessage
        || 'You must indicate whether the Related File physical file has been sighted.';
    const coverText = () => hidden?.dataset.coverText || 'Cover missing – contents sighted';

    function loadStored() {
        hidden = document.getElementById('related-file-sightings');
        stored = {};
        if (!hidden) return;
        try {
            const items = JSON.parse(hidden.value || '[]');
            (Array.isArray(items) ? items : []).forEach(item => {
                const k = key(item?.file_number);
                if (k && typeof item.sighted === 'boolean') {
                    stored[k] = { sighted: item.sighted, cover_missing: item.cover_missing || null };
                }
            });
        } catch (_) { /* unreadable: start unanswered */ }
    }

    function buildBlock() {
        const block = document.createElement('div');
        block.className = 'related-sighting w-full rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm';
        block.dataset.uid = String(++uidSeq);
        block.dataset.sighted = '';
        block.innerHTML = ''
            + '<div class="flex flex-wrap items-center gap-x-3 gap-y-1">'
            + '  <span class="font-medium text-gray-700">Related File (Physical File is Sighted) <span class="text-red-600">*</span></span>'
            + '  <div class="inline-flex rounded-md shadow-sm" role="radiogroup" aria-label="Related File (Physical File is Sighted)">'
            + '    <button type="button" role="radio" aria-checked="false" data-value="1" class="rf-choice px-3 py-1 text-xs font-semibold border border-gray-300 bg-white text-gray-700 rounded-l-md">Yes</button>'
            + '    <button type="button" role="radio" aria-checked="false" data-value="0" class="rf-choice px-3 py-1 text-xs font-semibold border border-l-0 border-gray-300 bg-white text-gray-700 rounded-r-md">No</button>'
            + '  </div>'
            + '  <span class="rf-npf-badge hidden inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700" title="No Physical File">NPF</span>'
            + '</div>'
            + '<div class="rf-cover hidden mt-2 flex flex-wrap items-center gap-2">'
            + '  <label class="inline-flex items-center gap-2 text-xs text-gray-700">'
            + '    <input type="checkbox" class="rf-cover-cb h-4 w-4 rounded border-gray-300 text-indigo-600">'
            + '    <span class="rf-cover-label"></span>'
            + '  </label>'
            + '  <input type="text" maxlength="500" class="rf-cover-note hidden flex-1 min-w-0 px-2 py-1 border border-gray-300 rounded-md text-xs" placeholder="Note (optional)" aria-label="Cover missing note">'
            + '</div>'
            + '<p class="rf-error hidden mt-1 text-xs font-medium text-red-600"></p>';
        block.querySelector('.rf-cover-label').textContent = coverText();
        return block;
    }

    function setAnswer(block, sighted, coverMissing) {
        block.dataset.sighted = sighted === true ? '1' : (sighted === false ? '0' : '');
        block.querySelectorAll('.rf-choice').forEach(btn => {
            const on = btn.dataset.value === block.dataset.sighted;
            btn.setAttribute('aria-checked', on ? 'true' : 'false');
            btn.classList.toggle('bg-white', !on);
            btn.classList.toggle('text-gray-700', !on);
            btn.classList.toggle('bg-green-600', on && btn.dataset.value === '1');
            btn.classList.toggle('bg-red-600', on && btn.dataset.value === '0');
            btn.classList.toggle('text-white', on);
        });
        block.querySelector('.rf-npf-badge').classList.toggle('hidden', sighted !== false);

        const cover = block.querySelector('.rf-cover');
        const cb = block.querySelector('.rf-cover-cb');
        const note = block.querySelector('.rf-cover-note');
        cover.classList.toggle('hidden', sighted !== true);
        if (sighted !== true) {
            cb.checked = false;
            note.value = '';
        } else if (coverMissing !== undefined) {
            cb.checked = !!coverMissing;
            note.value = (coverMissing && coverMissing !== coverText()) ? coverMissing : '';
        }
        note.classList.toggle('hidden', !cb.checked);
        if (sighted !== null) block.querySelector('.rf-error').classList.add('hidden');
    }

    // An indexed related file was physically handled, so it is never NPF (user,
    // 2026-10-29): its answer is Yes and locked. The server enforces the same.
    const indexedCache = {};   // key -> Promise<boolean>
    const INDEXED_URL = '/fileindexing/api/related-file-indexed';

    function setLocked(block, locked) {
        block.dataset.locked = locked ? '1' : '';
        block.querySelectorAll('.rf-choice').forEach(btn => {
            btn.disabled = locked;
            btn.classList.toggle('cursor-not-allowed', locked);
            btn.classList.toggle('opacity-75', locked);
        });
        let note = block.querySelector('.rf-indexed-note');
        if (locked && !note) {
            note = document.createElement('span');
            note.className = 'rf-indexed-note text-xs font-medium text-green-700';
            note.textContent = 'Indexed file: physical file seen';
            block.querySelector('[role="radiogroup"]').insertAdjacentElement('afterend', note);
        }
        note?.classList.toggle('hidden', !locked);
    }

    function checkIndexed(block, k, fileNumber) {
        if (!indexedCache[k]) {
            indexedCache[k] = fetch(`${INDEXED_URL}?file_number=${encodeURIComponent(fileNumber)}`, { headers: { Accept: 'application/json' } })
                .then(r => r.ok ? r.json() : null)
                .then(d => !!(d && d.success && d.indexed))
                .catch(() => { delete indexedCache[k]; return false; });
        }
        indexedCache[k].then(indexed => {
            if (!indexed || block.dataset.for !== k) return; // changed while we were asking
            setAnswer(block, true, undefined);
            setLocked(block, true);
            write();
        });
    }

    function rowsWithInput() {
        return Array.from(document.querySelectorAll(ROW_INPUT))
            .map(input => ({ input, row: input.closest('.related-file-row') || input.parentElement }))
            .filter(x => x.row);
    }

    function refresh() {
        scheduled = false;
        if (!hidden || !document.body.contains(hidden)) loadStored();
        if (!hidden) return;

        const seenUids = new Set();
        rowsWithInput().forEach(({ input, row }) => {
            let block = row.querySelector(':scope > .related-sighting');
            // A row cloned from another carries its answer: start it fresh.
            if (block && seenUids.has(block.dataset.uid)) {
                block.remove();
                block = null;
            }
            if (!block) {
                row.classList.add('flex-wrap');
                block = buildBlock();
                row.appendChild(block);
            }
            seenUids.add(block.dataset.uid);

            const k = key(input.value);
            block.classList.toggle('hidden', k === '');
            if (block.dataset.for !== k) {
                // Another file picked in this row: the old answer does not carry over.
                block.dataset.for = k;
                setLocked(block, false);
                const saved = stored[k];
                setAnswer(block, saved ? saved.sighted : null, saved ? saved.cover_missing : null);
                if (k) checkIndexed(block, k, input.value.trim());
            }
        });

        write();
    }

    function schedule() {
        if (scheduled) return;
        scheduled = true;
        (window.requestAnimationFrame || setTimeout)(refresh);
    }

    function answers() {
        const out = [];
        const seen = new Set();
        rowsWithInput().forEach(({ input, row }) => {
            const fileNumber = input.value.trim();
            const k = key(fileNumber);
            if (!k || seen.has(k)) return;
            seen.add(k);
            const block = row.querySelector(':scope > .related-sighting');
            const state = block?.dataset.for === k ? block.dataset.sighted : '';
            const sighted = state === '1' ? true : (state === '0' ? false : null);
            let cover = null;
            if (sighted === true && block.querySelector('.rf-cover-cb')?.checked) {
                cover = (block.querySelector('.rf-cover-note')?.value || '').trim() || coverText();
            }
            out.push({ file_number: fileNumber, sighted, cover_missing: cover });
        });
        return out;
    }

    function write() {
        if (hidden) hidden.value = JSON.stringify(answers());
    }

    /** JSON for the save payload (refreshed first, so a just-filled row is included). */
    function collect() {
        refresh();
        return hidden ? hidden.value : '[]';
    }

    /** False (and the rows flagged) when a related file is still unanswered. */
    function validate() {
        refresh();
        if (!hidden) return true;
        const missing = [];
        let first = null;
        rowsWithInput().forEach(({ input, row }) => {
            if (!input.value.trim()) return;
            const block = row.querySelector(':scope > .related-sighting');
            if (block && block.dataset.sighted !== '') return;
            missing.push(input.value.trim());
            if (block) {
                const err = block.querySelector('.rf-error');
                err.textContent = requiredMessage();
                err.classList.remove('hidden');
                first = first || block;
            }
        });
        if (!missing.length) return true;

        // "Has Related File?" unticked only hides the rows (their numbers still save): reopen them.
        const wrapper = document.getElementById('related-files-wrapper');
        const hasRelated = document.getElementById('has-related-file');
        if (wrapper?.classList.contains('hidden') && hasRelated && !hasRelated.checked) {
            hasRelated.checked = true;
            hasRelated.dispatchEvent(new Event('change', { bubbles: true }));
        }
        first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const text = requiredMessage() + ' (' + missing.join(', ') + ')';
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: 'Related File not answered', text, confirmButtonText: 'OK' });
        } else {
            alert(text);
        }
        return false;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest?.('.related-sighting .rf-choice');
        if (!btn) return;
        const block = btn.closest('.related-sighting');
        setAnswer(block, btn.dataset.value === '1', undefined);
        write();
    });

    document.addEventListener('change', function (e) {
        if (!e.target.classList?.contains('rf-cover-cb')) return;
        const block = e.target.closest('.related-sighting');
        const note = block.querySelector('.rf-cover-note');
        note.classList.toggle('hidden', !e.target.checked);
        if (!e.target.checked) note.value = '';
        write();
    });

    document.addEventListener('input', function (e) {
        if (e.target.matches?.(ROW_INPUT) || e.target.classList?.contains('rf-cover-note')) schedule();
    });

    function start() {
        loadStored();
        refresh();
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
        // File numbers are set by script without events; a light poll catches those.
        setInterval(refresh, 800);
    }

    /** Swap in another record's saved answers (the form switched records) and re-apply them. */
    function reload(items) {
        if (!hidden) loadStored();
        if (!hidden) return;
        hidden.value = JSON.stringify(Array.isArray(items) ? items : []);
        loadStored();
        document.querySelectorAll('.related-sighting').forEach(b => { delete b.dataset.for; });
        refresh();
    }

    window.RelatedFileSighting = { collect, validate, refresh, reload };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();

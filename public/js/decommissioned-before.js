/*
 * "File Already Decommissioned Before" — shared by the SPU, APU and Legacy Parcel
 * Update forms.
 *
 * The records are checked first. A file with no decommissioning record shows nothing
 * (the workflow writes a new record as usual). A file that already has one shows the
 * question answered YES and locked, with that record's date and reason — its new
 * files are added to that record instead of a second one being created.
 *
 * Usage:
 *   KlaesDecommBefore.check(['CON-AG-2010-110'], containerEl, hiddenInputEl);
 * containerEl gets the panel; hiddenInputEl (name="previously_decommissioned") gets
 * "Yes" / "No" for one file, or "FILE=Yes; FILE=No" for several.
 * The page sets window.KLAES_DECOMM_BEFORE_URL to the route
 * parcel-update.decommissioned-before.
 */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function clear(container, hidden) {
        if (container) { container.innerHTML = ''; container.classList.add('hidden'); }
        if (hidden) hidden.value = '';
    }

    function render(files, container, hidden) {
        var names = Object.keys(files);
        var yes = names.filter(function (f) { return files[f].answer === 'Yes'; });

        if (hidden) {
            hidden.value = names.length === 1
                ? files[names[0]].answer
                : names.map(function (f) { return f + '=' + files[f].answer; }).join('; ');
        }
        if (!container) return;
        if (!yes.length) { container.innerHTML = ''; container.classList.add('hidden'); return; }

        var html = '<div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">'
            + '<div class="flex items-center gap-2 font-bold uppercase tracking-wide text-[11px]">'
            + 'File Already Decommissioned Before'
            + '<span class="inline-flex items-center gap-1 rounded-full bg-amber-600 px-2 py-0.5 text-[10px] text-white" title="Read from the decommissioning records">'
            + 'YES <span aria-hidden="true">&#128274;</span></span></div>'
            + '<ul class="mt-1.5 space-y-1">';
        yes.forEach(function (f) {
            var r = files[f];
            html += '<li><span class="font-mono font-bold">' + esc(f) + '</span> — record #' + esc(r.record_id)
                + (r.date ? ', ' + esc(r.date) : '')
                + (r.reason ? ': ' + esc(r.reason.length > 90 ? r.reason.slice(0, 90) + '…' : r.reason) : '')
                + '</li>';
        });
        html += '</ul><p class="mt-1.5 text-[11px] text-amber-800">The new file numbers will be added to this existing decommissioning record; no second record is created.</p></div>';
        container.innerHTML = html;
        container.classList.remove('hidden');
    }

    function check(fileNos, container, hidden) {
        var list = (Array.isArray(fileNos) ? fileNos : [fileNos])
            .map(function (f) { return String(f || '').trim(); })
            .filter(function (f) { return f !== ''; });
        var owner = container || {};
        var ticket = owner.__dbSeq = (owner.__dbSeq || 0) + 1;
        if (!list.length || !global.KLAES_DECOMM_BEFORE_URL) { clear(container, hidden); return; }

        var qs = list.map(function (f) { return 'file_no[]=' + encodeURIComponent(f); }).join('&');
        fetch(global.KLAES_DECOMM_BEFORE_URL + '?' + qs, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (ticket !== owner.__dbSeq) return;
                render((data && data.files) || {}, container, hidden);
            })
            .catch(function () { if (ticket === owner.__dbSeq) clear(container, hidden); });
    }

    /*
     * Follow a form's source file field(s) and keep the panel in step. `source` is a
     * CSS selector (every match's value counts — several for merger/duplex/legacy) or
     * a function returning the file numbers. The global file-number selector writes
     * those fields from script without firing events, so they are polled. The panel
     * goes after `anchor` (default: the first matching field's parent), and a hidden
     * name="previously_decommissioned" input is added to the field's form.
     */
    function watch(source, anchor) {
        var read = typeof source === 'function'
            ? source
            : function () {
                return Array.prototype.map.call(document.querySelectorAll(source), function (el) { return el.value; });
            };
        var last = null, panel = null, hidden = null;

        function ensure() {
            if (panel) return true;
            var first = typeof source === 'string' ? document.querySelector(source) : null;
            var at = typeof anchor === 'string' ? document.querySelector(anchor) : (anchor || (first && first.parentElement));
            if (!at) return false;
            panel = document.createElement('div');
            panel.className = 'hidden mt-2';
            panel.setAttribute('data-decomm-before', '');
            at.insertAdjacentElement('afterend', panel);
            var form = (first && first.form) || at.closest('form');
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'previously_decommissioned';
            (form || panel).appendChild(hidden);
            return true;
        }

        function tick() {
            var files = (read() || []).map(function (f) { return String(f || '').trim(); }).filter(Boolean);
            var key = files.join('|');
            if (key === last || !ensure()) return;
            last = key;
            check(files, panel, hidden);
        }

        tick();
        setInterval(tick, 800);
    }

    global.KlaesDecommBefore = { check: check, clear: clear, watch: watch };
})(window);

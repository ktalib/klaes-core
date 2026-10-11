/**
 * SPAS workflow indicator —
 *   Record → Field Data → Contravention → 2nd Serve → Response → Payment →
 *   Memo → Decision → Certificate
 *
 * Same look as the deeds pipeline (deeds-pipeline.js). Wherever a page marks
 * a spot for a record it is painted from ONE batched lookup per page:
 *
 *     <span data-spa-pipeline="91"></span>              dots, for table rows
 *     SpasPipeline.mount(el, 91, { current: 'memo' })   full strip, for forms
 *
 * DataTables rows are repainted after every draw automatically.
 * Read-only: it shows where a record stands and never blocks anything.
 */
(function () {
    'use strict';

    var ENDPOINT = '/special-assignment/pipeline/status';

    var STAGES = [
        { key: 'field',         letter: 'F', label: 'Field Data' },
        { key: 'contravention', letter: 'X', label: 'Contravention' },
        { key: 'response',      letter: 'A', label: 'Response' },
        { key: 'second',        letter: '2', label: '2nd Serve' },
        { key: 'payment',       letter: 'P', label: 'Payment' },
        { key: 'memo',          letter: 'M', label: 'Memo' },
        { key: 'decision',      letter: 'D', label: 'Decision' },
        { key: 'certificate',   letter: 'C', label: 'Certificate' }
    ];

    // One hue per stage while it is outstanding; done is always emerald,
    // out of order always amber, rejected always red — read across the strip.
    var HUE = {
        record: '#64748b', field: '#0891b2', contravention: '#dc2626', second: '#ea580c',
        response: '#2563eb', payment: '#16a34a', memo: '#7c3aed', decision: '#9333ea',
        certificate: '#db2777'
    };

    var ICONS = {
        record: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/>',
        field: '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        contravention: '<path d="M12 3l9 16H3L12 3z"/><path d="M12 10v4M12 17h.01"/>',
        second: '<path d="M4 4h16v12H5.5L4 17.5V4z"/><path d="M9 9h6"/>',
        response: '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        payment: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        memo: '<path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/>',
        decision: '<path d="M14 4l6 6-8 8H6v-6l8-8z"/><path d="M4 21h16"/>',
        certificate: '<circle cx="12" cy="9" r="5"/><path d="M9 13.5L8 21l4-2 4 2-1-7.5"/>'
    };

    var cache = {};   // id -> stages, so a redraw of the same rows costs nothing

    /** done | progress | pending | na | rejected | skipped (pending, but a later stage is done). */
    function stateOf(stages, i) {
        var s = stages[STAGES[i].key] || {};
        if (s.state !== 'pending') { return s.state || 'pending'; }
        for (var j = i + 1; j < STAGES.length; j++) {
            // The 2nd Serve is what follows NO response, so it never makes the
            // Response look skipped; a reply can still come after it.
            if (STAGES[i].key === 'response' && STAGES[j].key === 'second') { continue; }
            var later = stages[STAGES[j].key];
            if (later && later.state === 'done') { return 'skipped'; }
        }
        return 'pending';
    }

    function tip(meta, s, state) {
        return meta.label + ': ' + (s.message || state)
            + (state === 'skipped' ? ' (later steps done first)' : '');
    }

    // ── dots ────────────────────────────────────────────────────────────
    function paint(el, stages) {
        if (!stages) { el.innerHTML = '<span style="color:#cbd5e1;font-size:11px">—</span>'; return; }

        var html = '<span style="display:inline-flex;align-items:center;gap:2px;white-space:nowrap">';
        STAGES.forEach(function (meta, i) {
            var state = stateOf(stages, i);
            var s = stages[meta.key] || {};
            var hue = HUE[meta.key];
            var tone = {
                done: 'background:#10b981;border-color:#10b981;color:#fff',
                skipped: 'background:#f59e0b;border-color:#f59e0b;color:#fff',
                rejected: 'background:#ef4444;border-color:#ef4444;color:#fff',
                progress: 'background:' + hue + '26;border-color:' + hue + ';color:' + hue,
                na: 'background:#f8fafc;border-color:#e2e8f0;color:#cbd5e1',
                pending: 'background:#fff;border-color:' + hue + '59;color:' + hue
            }[state];
            html += '<span title="' + esc(tip(meta, s, state)) + '" style="display:inline-flex;align-items:center;'
                + 'justify-content:center;width:17px;height:17px;border-radius:9999px;border:1.5px solid;'
                + 'font-size:9.5px;font-weight:700;line-height:1;cursor:default;' + tone + '">' + meta.letter + '</span>';
        });
        el.innerHTML = html + '</span>';
    }

    // ── strip ───────────────────────────────────────────────────────────
    function stripHtml(stages, current) {
        // Wraps onto a second row in a narrow form rather than scrolling the
        // later steps (Memo, Decision, Certificate) out of sight.
        var html = '<div><div style="display:flex;flex-wrap:wrap;align-items:flex-start;row-gap:12px">';

        STAGES.forEach(function (meta, i) {
            var state = stateOf(stages, i);
            var s = stages[meta.key] || {};
            var hue = HUE[meta.key];
            var isCurrent = meta.key === current && state !== 'done';
            var colour = state === 'na' ? '#cbd5e1'
                : state === 'done' ? '#059669'
                : state === 'skipped' ? '#d97706'
                : state === 'rejected' ? '#dc2626'
                : hue;
            var solid = state === 'done' || state === 'skipped' || state === 'rejected';
            var fill = solid ? colour : (isCurrent ? '#fff' : state === 'progress' ? hue + '22' : '#f1f5f9');
            var glyph = state === 'na' ? '<span style="font-size:14px;font-weight:700">&ndash;</span>'
                : state === 'done' ? '<span style="font-size:12px;font-weight:700">&#10003;</span>'
                : state === 'skipped' ? '<span style="font-size:13px;font-weight:700">!</span>'
                : state === 'rejected' ? '<span style="font-size:12px;font-weight:700">&#10005;</span>'
                : '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="' + colour
                    + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS[meta.key] + '</svg>';

            html += '<div style="flex:1 1 0;min-width:104px" title="' + esc(tip(meta, s, state)) + '">'
                + '<div style="display:flex;align-items:center">'
                + '<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;'
                + 'border-radius:9999px;border:2px solid ' + colour + ';background:' + fill + ';color:'
                + (solid ? '#fff' : colour) + ';flex:0 0 auto'
                + (isCurrent ? ';box-shadow:0 0 0 4px ' + hue + '26' : '') + '">' + glyph + '</span>'
                + (i < STAGES.length - 1
                    ? '<span style="flex:1;height:3px;margin:0 5px;border-radius:2px;background:'
                      + (state === 'done' ? '#059669' : '#e2e8f0') + '"></span>'
                    : '')
                + '</div>'
                + '<div style="margin-top:5px;padding-right:6px">'
                + '<div style="font-size:12px;font-weight:700;color:'
                + (state === 'na' ? '#94a3b8' : solid ? colour : hue) + '">' + meta.label + '</div>'
                + '<div style="font-size:10.5px;color:' + (state === 'na' ? '#94a3b8' : '#475569')
                + ';line-height:1.3;margin-top:1px">' + esc(s.message || '—') + '</div>'
                + '</div></div>';
        });

        return html + '</div></div>';
    }

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fetchStages(ids, fresh) {
        var want = ids.filter(function (id, i) { return ids.indexOf(id) === i && (fresh || !(id in cache)); });
        if (!want.length) { return Promise.resolve(); }
        var chunks = [];
        for (var i = 0; i < want.length; i += 200) { chunks.push(want.slice(i, i + 200)); }
        return Promise.all(chunks.map(function (chunk) {
            var qs = chunk.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
            return fetch(ENDPOINT + '?' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    chunk.forEach(function (id) { cache[id] = (res && res.data && res.data[id]) || null; });
                });
        }));
    }

    /** Paint every unpainted marker on the page (or inside root). */
    function refresh(root) {
        var nodes = [].slice.call((root || document).querySelectorAll('[data-spa-pipeline]'))
            .filter(function (el) { return el.dataset.spaPainted !== el.dataset.spaPipeline; });
        if (!nodes.length) { return Promise.resolve(); }
        var ids = nodes.map(function (el) { return el.dataset.spaPipeline; });
        // Always fresh: rows are redrawn after a save, and must show it.
        return fetchStages(ids, true).then(function () {
            nodes.forEach(function (el) {
                paint(el, cache[el.dataset.spaPipeline]);
                el.dataset.spaPainted = el.dataset.spaPipeline;
            });
        }).catch(function (err) {
            console.error('spas-pipeline: lookup failed', err);
            nodes.forEach(function (el) { el.textContent = ''; });
        });
    }

    /**
     * Full strip for one record.
     * @param {Element} el
     * @param {number|string} id   SPAS application id (empty clears the strip)
     * @param {Object} [opts]      current: the stage this form works on; force: skip the cache
     */
    function mount(el, id, opts) {
        if (!el) { return; }
        opts = opts || {};
        if (!id) { el.innerHTML = ''; el.classList.add('hidden'); return; }
        if (opts.force) { delete cache[id]; }
        el.classList.remove('hidden');
        el.innerHTML = '<div style="font-size:12px;color:#64748b">Loading workflow…</div>';
        fetchStages([String(id)]).then(function () {
            var stages = cache[id];
            el.innerHTML = stages ? stripHtml(stages, opts.current) : '';
            if (!stages) { el.classList.add('hidden'); }
        }).catch(function () {
            el.innerHTML = '<div style="font-size:12px;color:#b45309">Could not load the workflow for this record.</div>';
        });
    }

    /** Forget cached stages (all, or one id) — call after a save changes a record. */
    function invalidate(id) {
        if (id) { delete cache[id]; } else { cache = {}; }
        [].slice.call(document.querySelectorAll('[data-spa-pipeline]')).forEach(function (el) {
            if (!id || el.dataset.spaPipeline === String(id)) { delete el.dataset.spaPainted; }
        });
    }

    /**
     * Keep a strip in step with a record picker (a <select> of applications).
     * Polled, like DeedsPipeline.watch: pickers are set by code as often as by
     * hand, and a programmatic .value change fires no event.
     */
    var watched = {};
    function watch(containerSel, pickerSel, opts) {
        if (watched[containerSel]) { return; }
        watched[containerSel] = true;
        var last = null;
        function tick() {
            var el = document.querySelector(containerSel);
            var picker = document.querySelector(pickerSel);
            if (!el || !picker) { return; }
            var id = picker.value || '';
            if (id === last) { return; }
            last = id;
            mount(el, id, opts);
        }
        tick();
        setInterval(tick, 600);
    }

    window.SpasPipeline = { refresh: refresh, mount: mount, watch: watch, invalidate: invalidate, stages: STAGES };

    function start() {
        refresh();
        // Server-side DataTables replace their rows on every draw (page, search,
        // reload after a save). Watching the DOM catches every one of them —
        // DataTables events do not reliably bubble to the document.
        var queued = false;
        new MutationObserver(function () {
            if (queued) { return; }
            queued = true;
            setTimeout(function () { queued = false; refresh(); }, 120);
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();

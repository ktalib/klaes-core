/*
 * Scanned-page viewer (user, 2026-11-01). Opens the pages of one scanned folder — Blind Scan
 * or the raw scans on the records server — so an indexer or legal searcher can confirm a file
 * really has been scanned. Pages come from /scan-check/pages?t=<token> (ScanCheckController).
 *
 *   window.KlaesScanViewer.open({ token, title, subtitle })
 */
(function () {
    if (window.KlaesScanViewer) return;

    var css = ''
        + '.ksv-backdrop{position:fixed;inset:0;z-index:2100;background:rgba(15,23,42,.72);display:flex;align-items:center;justify-content:center;padding:18px}'
        + '.ksv-box{background:#fff;border-radius:14px;width:min(1180px,100%);height:min(92vh,900px);display:flex;flex-direction:column;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.35)}'
        + '.ksv-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;background:linear-gradient(120deg,#1e3a8a,#1d4ed8);color:#fff}'
        + '.ksv-title{font-weight:700;font-size:1rem;line-height:1.2}.ksv-sub{font-size:.75rem;opacity:.85}'
        + '.ksv-btn{background:rgba(255,255,255,.15);color:#fff;border:0;border-radius:8px;padding:6px 12px;font-size:.8rem;font-weight:600;cursor:pointer}.ksv-btn:hover{background:rgba(255,255,255,.28)}'
        + '.ksv-body{flex:1;display:flex;min-height:0}'
        + '.ksv-thumbs{width:170px;flex-shrink:0;overflow-y:auto;background:#f1f5f9;border-right:1px solid #e2e8f0;padding:8px}'
        + '.ksv-thumb{display:block;width:100%;margin-bottom:8px;border:2px solid transparent;border-radius:8px;background:#fff;cursor:pointer;padding:4px;text-align:center}'
        + '.ksv-thumb.on{border-color:#2563eb}.ksv-thumb img{width:100%;height:110px;object-fit:contain;background:#f8fafc}'
        + '.ksv-thumb .n{font-size:.68rem;color:#475569;margin-top:3px;word-break:break-all}'
        + '.ksv-thumb .doc{height:110px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#b91c1c;background:#fef2f2;border-radius:6px}'
        + '.ksv-main{flex:1;display:flex;flex-direction:column;min-width:0;background:#0f172a}'
        + '.ksv-stage{flex:1;display:flex;align-items:center;justify-content:center;overflow:auto;padding:10px}'
        + '.ksv-stage img{max-width:100%;max-height:100%;object-fit:contain;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.4)}'
        + '.ksv-stage iframe{width:100%;height:100%;border:0;background:#fff}'
        + '.ksv-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 14px;background:#111827;color:#e5e7eb;font-size:.8rem}'
        + '.ksv-msg{color:#cbd5e1;font-size:.9rem;text-align:center;padding:30px}'
        + '@media (max-width:700px){.ksv-thumbs{display:none}}';

    function el(tag, attrs, text) {
        var e = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
        if (text != null) e.textContent = text;
        return e;
    }

    var state = { pages: [], index: 0, root: null };

    function close() {
        if (state.root) state.root.remove();
        state.root = null;
        document.removeEventListener('keydown', onKey);
    }

    function onKey(e) {
        if (!state.root) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowRight') show(state.index + 1);
        if (e.key === 'ArrowLeft') show(state.index - 1);
    }

    function show(i) {
        if (!state.pages.length) return;
        state.index = Math.max(0, Math.min(state.pages.length - 1, i));
        var p = state.pages[state.index];
        var stage = state.root.querySelector('.ksv-stage');
        stage.innerHTML = '';
        if (p.type === 'image') {
            stage.appendChild(el('img', { src: p.url, alt: p.name }));
        } else if (p.type === 'pdf') {
            stage.appendChild(el('iframe', { src: p.url, title: p.name }));
        } else {
            var m = el('div', { class: 'ksv-msg' }, 'This page (' + p.name + ') cannot be shown in the browser. ');
            var a = el('a', { href: p.url, target: '_blank', style: 'color:#93c5fd;text-decoration:underline' }, 'Download it');
            m.appendChild(a);
            stage.appendChild(m);
        }
        state.root.querySelector('.ksv-count').textContent = 'Page ' + (state.index + 1) + ' of ' + state.pages.length;
        state.root.querySelector('.ksv-name').textContent = p.name;
        state.root.querySelectorAll('.ksv-thumb').forEach(function (t, n) {
            t.classList.toggle('on', n === state.index);
            if (n === state.index) t.scrollIntoView({ block: 'nearest' });
        });
    }

    function open(opts) {
        close();
        if (!document.getElementById('ksv-style')) {
            var s = el('style', { id: 'ksv-style' }); s.textContent = css; document.head.appendChild(s);
        }
        var root = el('div', { class: 'ksv-backdrop', role: 'dialog', 'aria-modal': 'true' });
        root.addEventListener('click', function (e) { if (e.target === root) close(); });
        var box = el('div', { class: 'ksv-box' });
        var head = el('div', { class: 'ksv-head' });
        var titles = el('div');
        titles.appendChild(el('div', { class: 'ksv-title' }, opts.title || 'Scanned pages'));
        titles.appendChild(el('div', { class: 'ksv-sub' }, opts.subtitle || ''));
        var actions = el('div', { style: 'display:flex;gap:8px' });
        var prev = el('button', { class: 'ksv-btn', type: 'button' }, '‹ Prev'); prev.onclick = function () { show(state.index - 1); };
        var next = el('button', { class: 'ksv-btn', type: 'button' }, 'Next ›'); next.onclick = function () { show(state.index + 1); };
        var x = el('button', { class: 'ksv-btn', type: 'button', 'aria-label': 'Close' }, '✕'); x.onclick = close;
        actions.appendChild(prev); actions.appendChild(next); actions.appendChild(x);
        head.appendChild(titles); head.appendChild(actions);
        var body = el('div', { class: 'ksv-body' });
        var thumbs = el('div', { class: 'ksv-thumbs' });
        var main = el('div', { class: 'ksv-main' });
        var stage = el('div', { class: 'ksv-stage' });
        stage.appendChild(el('div', { class: 'ksv-msg' }, 'Loading pages…'));
        var foot = el('div', { class: 'ksv-foot' });
        foot.appendChild(el('span', { class: 'ksv-count' }, ''));
        foot.appendChild(el('span', { class: 'ksv-name', style: 'opacity:.8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap' }, ''));
        main.appendChild(stage); main.appendChild(foot);
        body.appendChild(thumbs); body.appendChild(main);
        box.appendChild(head); box.appendChild(body); root.appendChild(box);
        document.body.appendChild(root);
        state.root = root;
        document.addEventListener('keydown', onKey);

        fetch((window.KLAES_SCAN_PAGES_URL || '/scan-check/pages') + '?t=' + encodeURIComponent(opts.token), {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!state.root) return;
                if (!res.ok || !res.d.success) throw new Error(res.d.message || 'Could not load the pages.');
                state.pages = res.d.pages || [];
                if (!opts.subtitle) titles.lastChild.textContent = res.d.label + ' · ' + res.d.folder + ' · ' + res.d.count + ' page(s)';
                if (!state.pages.length) {
                    stage.innerHTML = '';
                    stage.appendChild(el('div', { class: 'ksv-msg' }, 'This folder has no pages in it.'));
                    return;
                }
                state.pages.forEach(function (p, n) {
                    var t = el('button', { class: 'ksv-thumb', type: 'button', title: p.name });
                    if (p.type === 'image') t.appendChild(el('img', { src: p.url, alt: '', loading: 'lazy' }));
                    else t.appendChild(el('div', { class: 'doc' }, (p.name.split('.').pop() || 'FILE').toUpperCase()));
                    t.appendChild(el('div', { class: 'n' }, (n + 1) + '. ' + p.name.split('/').pop()));
                    t.onclick = function () { show(n); };
                    thumbs.appendChild(t);
                });
                show(0);
            })
            .catch(function (e) {
                if (!state.root) return;
                stage.innerHTML = '';
                stage.appendChild(el('div', { class: 'ksv-msg' }, e.message || 'Could not load the pages.'));
            });
    }

    window.KlaesScanViewer = { open: open, close: close };
})();

/*
 * Create File Index: "Has this file been scanned?" (user, 2026-11-01).
 * Shown under the File Number as soon as one is entered; a new index is refused by the
 * server until the file's pages exist (Digital Archive, Blind Scan, or the raw scans on the
 * records server as a fallback). From here the indexer can open the pages to confirm. Create
 * File Index stays disabled until the scan is found (re-checked every 30 seconds).
 *
 *   window.klaesScanCheck(fileNumber)          called by checkFileAlreadyIndexed()
 *   window.klaesScanBlocked(scanCheck, message) called when the server refuses a save
 */
(function () {
    var panel = document.getElementById('scan-check-panel');
    if (!panel) return;

    var urls = {
        check: panel.getAttribute('data-check-url'),
    };
    var current = null;   // file number being shown
    var lastResult = null;
    var seq = 0;

    var css = ''
        + '.scp{margin-top:12px;border-radius:12px;border:1px solid;padding:12px 14px;font-size:.85rem}'
        + '.scp-ok{background:#f0fdf4;border-color:#bbf7d0;color:#14532d}'
        + '.scp-no{background:#fef2f2;border-color:#fecaca;color:#7f1d1d}'
        + '.scp-wait{background:#f8fafc;border-color:#e2e8f0;color:#475569}'
        + '.scp-head{display:flex;align-items:center;gap:8px;font-weight:700}'
        + '.scp-dot{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;flex-shrink:0}'
        + '.scp-ok .scp-dot{background:#16a34a}.scp-no .scp-dot{background:#dc2626}.scp-wait .scp-dot{background:#94a3b8}'
        + '.scp-list{margin:8px 0 0 30px;display:flex;flex-direction:column;gap:6px}'
        + '.scp-row{display:flex;flex-wrap:wrap;align-items:center;gap:8px}'
        + '.scp-chip{font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:999px;background:#fff;border:1px solid currentColor}'
        + '.scp-btn{font-size:.78rem;font-weight:600;padding:5px 10px;border-radius:8px;border:1px solid;cursor:pointer;background:#fff}'
        + '.scp-ok .scp-btn{color:#166534;border-color:#86efac}.scp-ok .scp-btn:hover{background:#dcfce7}'
        + '.scp-no .scp-btn{color:#991b1b;border-color:#fca5a5}.scp-no .scp-btn:hover{background:#fee2e2}'
        + '.scp-note{margin:6px 0 0 30px;font-size:.78rem;opacity:.9}';
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    // Create File Index stays off until the file's pages exist (user, 2026-11-01). While a
    // check is running the button is off too; if the check itself fails the server decides.
    window.klaesScanAllowsCreate = function () {
        if (!current) return true;
        if (!lastResult) return false;
        if (lastResult.error) return true;
        if (lastResult.block_create === false) return true;
        return !!lastResult.scanned;
    };

    function refreshButton() {
        if (typeof window.klaesUpdateCreateButton === 'function') {
            try { window.klaesUpdateCreateButton(); } catch (e) { /* the form owns the button */ }
        }
    }

    // While a file is not scanned, look again every 30 seconds so the button turns on by
    // itself once the scan is uploaded in Blind Scan.
    var poll = null;
    function schedulePoll() {
        clearTimeout(poll);
        if (!current || !lastResult || lastResult.error || lastResult.scanned) return;
        var file = current;
        poll = setTimeout(function () {
            if (file !== current) return;
            if (document.hidden) { schedulePoll(); return; }
            var before = lastResult;
            run(file, true, true).then(function (d) {
                if (d && d.scanned && before && !before.scanned && typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', timer: 5000, showConfirmButton: false,
                        title: file + ' has been scanned — you can now create the index.' });
                }
            });
        }, 30000);
    }

    function render(r) {
        lastResult = r;
        refreshButton();
        schedulePoll();
        panel.classList.remove('hidden');
        if (!r) {
            panel.innerHTML = '<div class="scp scp-wait"><div class="scp-head"><span class="scp-dot">…</span> Checking whether this file has been scanned…</div></div>';
            return;
        }
        if (r.error) {
            panel.innerHTML = '<div class="scp scp-wait"><div class="scp-head"><span class="scp-dot">!</span> ' + esc(r.error) + '</div></div>';
            return;
        }
        var html;
        if (r.scanned) {
            html = '<div class="scp scp-ok"><div class="scp-head"><span class="scp-dot">✓</span> Scanned — ' + r.pages + ' page' + (r.pages === 1 ? '' : 's') + ' found</div><div class="scp-list">';
            r.sources.forEach(function (s, i) {
                html += '<div class="scp-row"><span class="scp-chip">' + esc(s.label) + '</span><span>' + esc(s.folder) + ' · ' + s.pages + ' page' + (s.pages === 1 ? '' : 's') + '</span>';
                if (s.token) html += '<button type="button" class="scp-btn" data-view="' + i + '">View pages</button>';
                else if (s.view_url) html += '<a class="scp-btn" href="' + esc(s.view_url) + '" target="_blank" rel="noopener">Open in Digital Archive</a>';
                html += '</div>';
            });
            html += '</div>';
            if (r.empty_folders && r.empty_folders.length) {
                html += '<div class="scp-note">Also found ' + r.empty_folders.length + ' empty folder(s): ' + esc(r.empty_folders.map(function (e) { return e.folder; }).join(', ')) + '.</div>';
            }
            html += '</div>';
        } else {
            var why = r.empty_folders && r.empty_folders.length
                ? 'A folder exists (' + esc(r.empty_folders.map(function (e) { return e.label + ': ' + e.folder; }).join(', ')) + ') but it has no pages in it.'
                : 'No pages were found in the Digital Archive, the Blind Scan folder' + (r.records_server_checked ? ' or the raw scans on the records server' : '') + '.';
            html = '<div class="scp scp-no"><div class="scp-head"><span class="scp-dot">✕</span> Not scanned</div>'
                + '<div class="scp-note" style="margin-top:4px">' + why
                + (r.records_server_unavailable ? ' The records server could not be reached.' : '')
                + (r.block_create ? ' <b>Create File Index stays disabled until the scan is uploaded in Blind Scan.</b> This is checked again every 30 seconds.' : ' You can still create the index; remember to scan the file in Blind Scan.') + '</div>'
                + '<div class="scp-list"><div class="scp-row">'
                // "Add to Needs Scanning" and "Needs Scanning list" removed (user, 2026-11-01).
                + '<button type="button" class="scp-btn" data-recheck="1">Check again</button>'
                + '</div></div></div>';
        }
        panel.innerHTML = html;
    }

    panel.addEventListener('click', function (e) {
        var v = e.target.closest('[data-view]');
        if (v && lastResult) {
            var s = lastResult.sources[Number(v.getAttribute('data-view'))];
            if (s && window.KlaesScanViewer) window.KlaesScanViewer.open({ token: s.token, title: lastResult.file_number + ' — ' + s.label, subtitle: s.folder + ' · ' + s.pages + ' page(s)' });
            return;
        }
        if (e.target.closest('[data-recheck]')) { run(current, true); }
    });

    function run(fileNumber, fresh, quiet) {
        var mine = ++seq;
        current = (fileNumber || '').trim() || null;
        if (!current) {
            clearTimeout(poll);
            panel.innerHTML = ''; panel.classList.add('hidden'); lastResult = null;
            refreshButton();
            return Promise.resolve(null);
        }
        // A background re-check keeps the current answer on screen until the new one arrives.
        if (!quiet) render(null);
        return fetch(urls.check + '?file_number=' + encodeURIComponent(current) + (fresh ? '&fresh=1' : ''), {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
            .then(function (d) {
                if (mine !== seq) return null;
                if (!d || !d.success) {
                    // A failed background re-check keeps the last answer (and the button off).
                    if (quiet && lastResult) { schedulePoll(); return null; }
                    render({ error: (d && d.message) || 'Could not check whether this file has been scanned.' });
                    return null;
                }
                render(d);
                return d;
            })
            .catch(function () {
                if (mine !== seq) return null;
                if (quiet && lastResult) { schedulePoll(); return null; }
                render({ error: 'Could not check whether this file has been scanned. Try again.' });
                return null;
            });
    }

    // Debounced: the File Number fires on every keystroke.
    var timer = null;
    window.klaesScanCheck = function (fileNumber) {
        clearTimeout(timer);
        var value = (fileNumber || '').trim();
        if (!value) { run('', false); return; }
        if (value === current && lastResult && !lastResult.error) return;
        // A new number: the button goes off at once, not after the pause.
        if (value !== current) { current = value; render(null); }
        timer = setTimeout(function () { run(value, false); }, 400);
    };

    window.klaesScanBlocked = function (scanCheck, message) {
        if (scanCheck) { current = scanCheck.file_number || current; render(scanCheck); }
        panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof Swal === 'undefined') { alert(message || 'This file has not been scanned.'); return Promise.resolve(); }
        return Swal.fire({
            icon: 'warning',
            title: 'This file has not been scanned',
            html: esc((scanCheck && scanCheck.message) || message || '')
                + '<br><br>A file can only be indexed once its pages have been uploaded in Blind Scan.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#2563eb',
        });
    };
})();

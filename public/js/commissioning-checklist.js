/**
 * Commissioning checklist — did commissioning write every table it should?
 *
 *   openCommissioningChecklist(fileNo, batchNo, batchCount)  action-menu card
 *   autoCommissioningChecklist(fileNumbers)                  run after a commissioning;
 *       a small notice confirms a clean run, and a gap stays on screen until viewed.
 *
 * Built outside SweetAlert on purpose: the automatic check lands while the
 * commissioning success popup is still open, and Swal shows one dialog at a time.
 * Endpoint: window.COMMISSIONING_CHECKLIST_URL (POST, see CommissioningChecklistController).
 */
(function () {
    'use strict';

    const STATUS = {
        ok:      { icon: '✓', badge: 'background:#dcfce7;color:#15803d;border-color:#bbf7d0', label: 'Done' },
        missing: { icon: '✗', badge: 'background:#fee2e2;color:#b91c1c;border-color:#fecaca', label: 'Missing' },
        warn:    { icon: '!', badge: 'background:#fef3c7;color:#b45309;border-color:#fde68a', label: 'Check' },
        na:      { icon: '–', badge: 'background:#f1f5f9;color:#94a3b8;border-color:#e2e8f0', label: 'N/A' },
    };

    const esc = (v) => String(v == null ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    function csrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    async function fetchChecklist(payload) {
        const url = window.COMMISSIONING_CHECKLIST_URL;
        if (!url) throw new Error('Checklist endpoint is not configured on this page.');
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(payload),
        });
        let data = null;
        try { data = await res.json(); } catch (e) { /* fall through */ }
        if (!res.ok || !data || !data.success) {
            throw new Error((data && data.message) || ('Checklist request failed (' + res.status + ')'));
        }
        return data;
    }

    // ---- card --------------------------------------------------------------

    function ensureCard() {
        let overlay = document.getElementById('commChecklistOverlay');
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.id = 'commChecklistOverlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:2100;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;padding:16px';
        overlay.innerHTML = `
            <div style="background:#fff;border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);width:100%;max-width:640px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc">
                    <div>
                        <div style="font-size:16px;font-weight:800;color:#1e293b">Commissioning Checklist</div>
                        <div id="commChecklistSubtitle" style="font-size:12px;color:#64748b;margin-top:2px"></div>
                    </div>
                    <button type="button" data-close style="font-size:22px;line-height:1;color:#94a3b8;background:none;border:0;cursor:pointer" title="Close">&times;</button>
                </div>
                <div id="commChecklistBody" style="padding:16px 20px;overflow-y:auto"></div>
                <div id="commChecklistFooter" style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;background:#f8fafc"></div>
            </div>`;
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('[data-close]')) closeCard();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.style.display === 'flex') closeCard();
        });
        document.body.appendChild(overlay);
        return overlay;
    }

    function closeCard() {
        const o = document.getElementById('commChecklistOverlay');
        if (o) o.style.display = 'none';
    }

    function showCard(subtitle, bodyHtml, footerHtml) {
        const o = ensureCard();
        o.querySelector('#commChecklistSubtitle').innerHTML = subtitle;
        o.querySelector('#commChecklistBody').innerHTML = bodyHtml;
        o.querySelector('#commChecklistFooter').innerHTML = footerHtml
            + '<button type="button" data-close style="padding:8px 16px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;font-size:13px;font-weight:600;color:#334155;cursor:pointer">Close</button>';
        o.style.display = 'flex';
    }

    function badge(status) {
        const s = STATUS[status] || STATUS.na;
        return `<span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;border:1px solid;font-weight:800;font-size:14px;flex-shrink:0;${s.badge}" title="${s.label}">${s.icon}</span>`;
    }

    function verdict(result) {
        const gaps = result.total - result.complete;
        return gaps === 0
            ? `<div style="padding:10px 12px;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;font-size:13px;font-weight:700;margin-bottom:12px">All checks passed${result.total > 1 ? ` for ${result.total} files` : ''}.</div>`
            : `<div style="padding:10px 12px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:13px;font-weight:700;margin-bottom:12px">${result.total > 1 ? `${gaps} of ${result.total} files have` : 'This file has'} something missing.</div>`;
    }

    function renderSingle(file, result) {
        const labels = Object.fromEntries((result.summary || []).map(s => [s.key, s.label]));
        const rows = Object.entries(file.items).map(([key, item]) => `
            <div style="display:flex;gap:12px;align-items:flex-start;padding:9px 0;border-bottom:1px solid #f1f5f9">
                ${badge(item.status)}
                <div style="min-width:0">
                    <div style="font-size:13px;font-weight:700;color:#1e293b">${esc(labels[key] || key)}</div>
                    <div style="font-size:12px;color:${item.status === 'missing' ? '#b91c1c' : '#64748b'};word-break:break-word">${esc(item.detail || (STATUS[item.status] || STATUS.na).label)}</div>
                </div>
            </div>`).join('');
        return verdict(result) + rows;
    }

    function renderBatch(result) {
        const rows = (result.summary || []).map(s => {
            const c = s.counts || {};
            const parts = [];
            if (c.ok) parts.push(`<span style="color:#15803d">${c.ok} done</span>`);
            if (c.missing) parts.push(`<span style="color:#b91c1c;font-weight:700">${c.missing} missing</span>`);
            if (c.warn) parts.push(`<span style="color:#b45309">${c.warn} to check</span>`);
            if (c.na) parts.push(`<span style="color:#94a3b8">${c.na} n/a</span>`);
            const list = (s.files || []).length
                ? `<div style="margin-top:4px;font-family:ui-monospace,monospace;font-size:11px;color:#475569;word-break:break-word">${s.files.map(f =>
                    `<a href="#" data-file="${esc(f)}" style="color:#2563eb;text-decoration:underline">${esc(f)}</a>`).join(', ')}${s.more ? ` … +${s.more} more` : ''}</div>`
                : '';
            return `
                <div style="display:flex;gap:12px;align-items:flex-start;padding:9px 0;border-bottom:1px solid #f1f5f9">
                    ${badge(s.status)}
                    <div style="min-width:0;flex:1">
                        <div style="font-size:13px;font-weight:700;color:#1e293b">${esc(s.label)}</div>
                        <div style="font-size:12px">${parts.join(' · ')}</div>
                        ${list}
                    </div>
                </div>`;
        }).join('');
        return verdict(result) + rows
            + '<div style="font-size:11px;color:#94a3b8;margin-top:8px">Click a file number to see its own checklist.</div>';
    }

    function bindFileLinks(back) {
        const body = document.getElementById('commChecklistBody');
        if (!body) return;
        body.querySelectorAll('a[data-file]').forEach(a => a.addEventListener('click', (e) => {
            e.preventDefault();
            openCommissioningChecklist(a.dataset.file, '', 0, back);
        }));
    }

    function loading(subtitle) {
        showCard(subtitle, '<div style="padding:24px;text-align:center;color:#64748b;font-size:13px">Checking…</div>', '');
    }

    function failed(subtitle, err) {
        showCard(subtitle, `<div style="padding:12px;border-radius:10px;background:#fef2f2;color:#b91c1c;font-size:13px">${esc(err.message || err)}</div>`, '');
    }

    /**
     * @param {string} fileNo
     * @param {string} batchNo     shown as a "whole batch" option when batchCount > 1
     * @param {number} batchCount
     * @param {Function} [back]    re-opens the batch view (when drilled in from one)
     */
    async function openCommissioningChecklist(fileNo, batchNo, batchCount, back) {
        fileNo = String(fileNo || '').trim();
        const subtitle = `<span style="font-family:ui-monospace,monospace;font-weight:700;color:#334155">${esc(fileNo)}</span>`;
        loading(subtitle);
        try {
            const result = await fetchChecklist({ file_number: fileNo });
            const file = (result.files || [])[0];
            if (!file) throw new Error('Nothing to check.');
            let footer = '';
            if (typeof back === 'function') {
                footer += btn('commChecklistBack', '← Back to batch', '#475569', '#fff');
            }
            if (batchNo && Number(batchCount) > 1) {
                footer += btn('commChecklistBatch', `Check whole batch (${Number(batchCount)} files)`, '#fff', '#4f46e5');
            }
            showCard(subtitle + (file.source ? ` · ${esc(file.source)}` : ''), renderSingle(file, result), footer);
            const b = document.getElementById('commChecklistBatch');
            if (b) b.addEventListener('click', () => openBatchChecklist({ batch_no: batchNo }, `Batch ${esc(batchNo)}`));
            const bk = document.getElementById('commChecklistBack');
            if (bk) bk.addEventListener('click', back);
        } catch (err) {
            failed(subtitle, err);
        }
    }

    async function openBatchChecklist(payload, subtitle, preloaded) {
        loading(subtitle);
        try {
            const result = preloaded || await fetchChecklist(payload);
            if ((result.files || []).length === 1) {
                showCard(subtitle, renderSingle(result.files[0], result), '');
                return;
            }
            showCard(`${subtitle} · ${result.total} files`, renderBatch(result), '');
            bindFileLinks(() => openBatchChecklist(payload, subtitle, result));
        } catch (err) {
            failed(subtitle, err);
        }
    }

    function btn(id, text, color, bg) {
        return `<button type="button" id="${id}" style="padding:8px 14px;border-radius:10px;border:1px solid ${bg === '#fff' ? '#cbd5e1' : bg};background:${bg};color:${color};font-size:13px;font-weight:700;cursor:pointer">${text}</button>`;
    }

    // ---- automatic check after commissioning -------------------------------

    function notice(html, tone, persistent) {
        let el = document.getElementById('commChecklistNotice');
        if (!el) {
            el = document.createElement('div');
            el.id = 'commChecklistNotice';
            document.body.appendChild(el);
        }
        const base = 'position:fixed;right:16px;bottom:16px;z-index:2090;max-width:min(360px,calc(100vw - 32px));border-radius:14px;box-shadow:0 10px 25px -5px rgba(0,0,0,.25);padding:12px 14px;font-size:13px;border:1px solid;';
        const tones = {
            ok:   'background:#f0fdf4;border-color:#bbf7d0;color:#15803d',
            bad:  'background:#fef2f2;border-color:#fecaca;color:#991b1b',
            info: 'background:#fff;border-color:#e2e8f0;color:#475569',
        };
        el.style.cssText = base + tones[tone];
        el.innerHTML = html;
        el.style.display = 'block';
        clearTimeout(el._timer);
        if (!persistent) el._timer = setTimeout(() => { el.style.display = 'none'; }, 8000);
        return el;
    }

    /**
     * Run the checklist over files just commissioned. Never throws: a failed check
     * must not disturb the commissioning success flow it rides on.
     */
    async function autoCommissioningChecklist(fileNumbers) {
        const files = (Array.isArray(fileNumbers) ? fileNumbers : [fileNumbers])
            .map(f => (typeof f === 'string' ? f : (f && (f.file_number || f.full_file_number)) || ''))
            .map(f => String(f).trim())
            .filter(f => f && f !== 'N/A');
        if (!files.length) return;

        const label = files.length === 1 ? esc(files[0]) : `${files.length} files`;
        notice(`<b>Commissioning checklist</b><br>Checking ${label}…`, 'info', true);

        try {
            const result = await fetchChecklist({ file_numbers: files.slice(0, 3000) });
            const gaps = result.total - result.complete;
            if (gaps === 0) {
                notice(`<b>✓ Commissioning checklist passed</b><br>${label}: every record is in place.`, 'ok', false);
                return;
            }
            const missingItems = (result.summary || []).filter(s => s.status === 'missing').map(s => esc(s.label));
            const el = notice(`
                <div style="display:flex;justify-content:space-between;gap:8px">
                    <b>✗ Commissioning checklist: ${files.length === 1 ? 'gaps found' : `${gaps} of ${result.total} files incomplete`}</b>
                    <button type="button" data-dismiss style="background:none;border:0;font-size:18px;line-height:1;color:inherit;cursor:pointer">&times;</button>
                </div>
                <div style="margin-top:4px">${label} — missing: ${missingItems.join(', ') || 'see details'}</div>
                <button type="button" data-view style="margin-top:8px;padding:6px 12px;border-radius:8px;border:0;background:#b91c1c;color:#fff;font-weight:700;font-size:12px;cursor:pointer">View checklist</button>`, 'bad', true);
            el.querySelector('[data-dismiss]').addEventListener('click', () => { el.style.display = 'none'; });
            el.querySelector('[data-view]').addEventListener('click', () => {
                el.style.display = 'none';
                if (window.Swal && typeof window.Swal.close === 'function') window.Swal.close();
                openBatchChecklist({ file_numbers: files }, files.length === 1 ? `<span style="font-family:ui-monospace,monospace;font-weight:700;color:#334155">${esc(files[0])}</span>` : 'Just commissioned', result);
            });
        } catch (err) {
            notice(`<b>Commissioning checklist</b><br>Could not run the check: ${esc(err.message || err)}`, 'info', false);
        }
    }

    window.openCommissioningChecklist = openCommissioningChecklist;
    window.autoCommissioningChecklist = autoCommissioningChecklist;
})();

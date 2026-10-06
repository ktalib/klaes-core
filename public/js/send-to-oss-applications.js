/**
 * "Send to OSS Applications" — shared by the MLPP File Commissioning and OSS FC / FEFR
 * action menus. The endpoint decides the list (No Change vs Change of Ownership);
 * this only confirms, posts and reports.
 *
 *   sendToOssApplications(url, payload, { label, list })
 */
(function () {
    'use strict';

    const esc = (v) => String(v == null ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    async function sendToOssApplications(url, payload, opts) {
        opts = opts || {};
        const label = opts.label || '';
        const list = opts.list || 'OSS Applications';
        const hasSwal = window.Swal && typeof window.Swal.fire === 'function';

        if (hasSwal) {
            const ok = await window.Swal.fire({
                icon: 'question',
                title: opts.title || 'Send to OSS Applications?',
                html: `<p class="text-sm text-gray-600"><b class="font-mono">${esc(label)}</b> will be placed on<br><b>${esc(list)}</b>.</p>`
                    + '<p class="text-xs text-gray-400 mt-2">Original creator and date are kept. Nothing happens if it is already listed.</p>',
                showCancelButton: true,
                confirmButtonText: 'Send',
            });
            if (!ok.isConfirmed) return;
            window.Swal.fire({ title: 'Sending…', allowOutsideClick: false, didOpen: () => window.Swal.showLoading() });
        } else if (!window.confirm(`Send ${label} to ${list}?`)) {
            return;
        }

        let data = null;
        try {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': meta ? meta.getAttribute('content') : '',
                },
                body: JSON.stringify(payload),
            });
            try { data = await res.json(); } catch (e) { data = null; }
            if (!data) data = { success: false, message: 'Request failed (' + res.status + ')' };
            if (!res.ok && data.success !== false) data.success = false;
            if (res.status === 422 && data.errors && !data.message) {
                data.message = Object.values(data.errors).flat().join(' ');
            }
        } catch (err) {
            data = { success: false, message: err.message || String(err) };
        }

        const text = esc(data.message || '') + (data.note ? `<br><span class="text-xs text-amber-600">${esc(data.note)}</span>` : '');
        if (hasSwal) {
            window.Swal.fire({
                icon: data.success ? (data.action === 'already' ? 'info' : 'success') : 'error',
                title: data.success ? (data.action === 'already' ? 'Already listed' : 'Sent') : 'Not sent',
                html: `<p class="text-sm">${text}</p>`,
            });
        } else {
            window.alert((data.message || '') + (data.note ? '\n' + data.note : ''));
        }
    }

    window.sendToOssApplications = sendToOssApplications;
})();

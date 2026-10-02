<script>
(function () {
    'use strict';

    /* ──────────────────────────────────────────────────────────────
       CONFIG / STATE
    ────────────────────────────────────────────────────────────── */
    const BASE_URL   = (document.querySelector('meta[name="app-base-url"]')?.content || '').replace(/\/$/, '');
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const CURRENT_MODULE = window.currentModule || '';

    const state = {
        records : [],
        filter  : 'all',
        search  : '',
        canApprove: false,   // set from server-injected permission
    };

    /* ──────────────────────────────────────────────────────────────
       PERMISSION — temporarily allow all authenticated users
    ────────────────────────────────────────────────────────────── */
    state.canApprove = true;

    /* ──────────────────────────────────────────────────────────────
       DOM HANDLES
    ────────────────────────────────────────────────────────────── */
    const $  = id => document.getElementById(id);
    const qs = sel => document.querySelector(sel);

    // Tab switching — piggy-back on the existing main-tab logic
    function getTabCheckout() { return $('tab-checkout'); }
    function getContentCheckout() { return $('content-checkout'); }

    // Section controls
    const btnRefresh    = $('co-refresh-btn');

    // Stats
    const statPending   = $('co-stat-pending');
    const statApproved  = $('co-stat-approved');
    const statRejected  = $('co-stat-rejected');
    const pendingBadge  = $('checkout-pending-count');
    const resultCount   = $('co-result-count');

    // Table
    const elLoading    = $('co-loading');
    const elEmpty      = $('co-empty');
    const elTableWrap  = $('co-table-wrap');
    const tbody        = $('co-tbody');

    // Filter buttons
    const filterBtns = document.querySelectorAll('.co-filter-btn');

    // Search
    const searchInput = $('co-search');

    /* ──────────────────────────────────────────────────────────────
       HELPERS
    ────────────────────────────────────────────────────────────── */
    function escHtml(str) {
        if (!str && str !== 0) return '—';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDate(val) {
        if (!val) return '—';
        try { return new Date(val).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }); }
        catch { return String(val); }
    }

    function formatDateTime(val) {
        if (!val) return '—';
        try {
            return new Date(val).toLocaleString('en-GB', {
                day:'2-digit', month:'short', year:'numeric',
                hour:'2-digit', minute:'2-digit'
            });
        } catch { return String(val); }
    }

    function statusBadge(status) {
        const map = {
            pending  : 'bg-amber-100 text-amber-800 border-amber-200',
            approved : 'bg-green-100 text-green-800 border-green-200',
            rejected : 'bg-red-100 text-red-800 border-red-200',
        };
        const cls = map[status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
        return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${cls}">${escHtml(status)}</span>`;
    }

    function showToast(msg, type = 'success') {
        if (typeof window.showPageToast === 'function') {
            window.showPageToast(msg, type);
            return;
        }
        // Fallback
        const wrap = document.getElementById('page-toast-container');
        if (!wrap) { alert(msg); return; }
        const div = document.createElement('div');
        const cls = type === 'success'
            ? 'bg-green-600 text-white'
            : 'bg-red-600 text-white';
        div.className = `${cls} px-4 py-2 rounded-lg shadow-lg text-sm pointer-events-auto max-w-sm`;
        div.textContent = msg;
        wrap.appendChild(div);
        setTimeout(() => div.remove(), 4000);
    }

    /* ──────────────────────────────────────────────────────────────
       FETCH LIST
    ────────────────────────────────────────────────────────────── */
    async function loadList() {
        if (!elLoading || !tbody) return;

        elLoading.classList.remove('hidden');
        elEmpty.classList.add('hidden');
        elTableWrap.classList.add('hidden');

        try {
            const params = new URLSearchParams({ status: state.filter, search: state.search, module: CURRENT_MODULE });
            const res = await fetch(`${BASE_URL}/create-file-tracker/kangis-checkout?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message ?? 'Failed to load');

            state.records = json.data ?? [];
        } catch (err) {
            showToast('Could not load checkout requests: ' + err.message, 'error');
            state.records = [];
        } finally {
            elLoading.classList.add('hidden');
        }

        renderTable();
        updateStats();
    }

    /* ──────────────────────────────────────────────────────────────
       RENDER TABLE
    ────────────────────────────────────────────────────────────── */
    function renderTable() {
        tbody.innerHTML = '';

        if (state.records.length === 0) {
            elEmpty.classList.remove('hidden');
            elTableWrap.classList.add('hidden');
            resultCount.textContent = '0 records';
            return;
        }

        elEmpty.classList.add('hidden');
        elTableWrap.classList.remove('hidden');
        resultCount.textContent = `${state.records.length} record${state.records.length !== 1 ? 's' : ''}`;

        state.records.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            tr.dataset.id = row.id;

            let actionsHtml = '';
            if (row.status === 'pending') {
                if (state.canApprove) {
                    actionsHtml += `
                        <button class="co-approve-btn inline-flex items-center px-2.5 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 transition-colors" data-id="${row.id}">
                            <i data-lucide="check" class="h-3 w-3 mr-1"></i>Approve
                        </button>
                        <button class="co-reject-btn inline-flex items-center px-2.5 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors ml-1" data-id="${row.id}">
                            <i data-lucide="x" class="h-3 w-3 mr-1"></i>Reject
                        </button>`;
                } else {
                    actionsHtml = `<span class="text-xs text-gray-400 italic">Awaiting approval</span>`;
                }
            } else if (row.status === 'approved') {
                actionsHtml = `
                    <button class="co-print-ack-btn inline-flex items-center px-2.5 py-1 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 transition-colors" data-id="${row.id}">
                        <i data-lucide="printer" class="h-3 w-3 mr-1"></i>Print Ack.
                    </button>`;
            } else {
                actionsHtml = `<span class="text-xs text-gray-400">${escHtml(row.rejected_by_name ?? 'Registry')}</span>`;
            }

            tr.innerHTML = `
                <td class="px-4 py-3 font-mono text-xs text-gray-800">${escHtml(row.request_no)}</td>
                <td class="px-4 py-3 text-sm text-gray-700">${escHtml(row.requested_office_name || '—')}</td>
                <td class="px-4 py-3 text-sm text-gray-700">${escHtml(row.requested_by_name || '—')}</td>
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">${formatDateTime(row.created_at)}</td>
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">${row.approved_at ? formatDateTime(row.approved_at) : '—'}</td>
                <td class="px-4 py-3">${statusBadge(row.status)}</td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-[200px] truncate" title="${escHtml(row.purpose)}">${escHtml(row.purpose || '—')}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-1 flex-wrap">${actionsHtml}</div>
                </td>`;
            tbody.appendChild(tr);
        });

        // Re-init lucide icons for newly rendered buttons
        if (window.lucide) window.lucide.createIcons();
    }

    /* ──────────────────────────────────────────────────────────────
       UPDATE STATS + BADGE
    ────────────────────────────────────────────────────────────── */
    function updateStats() {
        // Always fetch all records counts from current data when filter is 'all';
        // otherwise we need the full set — handled by re-computing from state.records
        // when filter is 'all'.  For other filters, reflect what's loaded.
        const pending  = state.records.filter(r => r.status === 'pending').length;
        const approved = state.records.filter(r => r.status === 'approved').length;
        const rejected = state.records.filter(r => r.status === 'rejected').length;

        if (statPending)  statPending.textContent  = pending;
        if (statApproved) statApproved.textContent = approved;
        if (statRejected) statRejected.textContent = rejected;

        if (pendingBadge) {
            if (pending > 0) {
                pendingBadge.textContent = pending;
                pendingBadge.classList.remove('hidden');
            } else {
                pendingBadge.classList.add('hidden');
            }
        }
    }

    /* ──────────────────────────────────────────────────────────────
       APPROVE (click delegation on tbody)
    ────────────────────────────────────────────────────────────── */
    tbody?.addEventListener('click', async e => {
        const approveBtn = e.target.closest('.co-approve-btn');
        const rejectBtn  = e.target.closest('.co-reject-btn');
        const printBtn   = e.target.closest('.co-print-ack-btn');

        if (approveBtn) {
            const id = approveBtn.dataset.id;
            if (!confirm('Approve this checkout request?')) return;

            approveBtn.disabled = true;
            approveBtn.textContent = 'Approving…';

            try {
                const res = await fetch(`${BASE_URL}/create-file-tracker/kangis-checkout/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept'       : 'application/json',
                        'Content-Type' : 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.message ?? 'Approval failed');
                showToast(json.message, 'success');
                // Auto-open ack sheet
                renderAckSheet(json.data);
                loadList();
            } catch (err) {
                showToast('Approval failed: ' + err.message, 'error');
                approveBtn.disabled = false;
                approveBtn.innerHTML = '<i data-lucide="check" class="h-3 w-3 mr-1 inline"></i>Approve';
                if (window.lucide) window.lucide.createIcons();
            }
        }

        if (rejectBtn) {
            const id = rejectBtn.dataset.id;
            openRejectModal(id);
        }

        if (printBtn) {
            const id = parseInt(printBtn.dataset.id, 10);
            const record = state.records.find(r => r.id == id);
            if (record) renderAckSheet(record);
        }
    });

    /* ──────────────────────────────────────────────────────────────
       REJECT MODAL
    ────────────────────────────────────────────────────────────── */
    const rejectModal     = $('co-reject-modal');
    const rejectCloseBtn  = $('co-reject-close');
    const rejectCancelBtn = $('co-reject-cancel');
    const rejectConfirm   = $('co-reject-confirm');
    const rejectIdInput   = $('co-reject-id');
    const rejectReason    = $('co-reject-reason');
    const rejectError     = $('co-reject-error');

    function openRejectModal(id) {
        if (!rejectModal) return;
        rejectIdInput.value  = id;
        rejectReason.value   = '';
        rejectError.classList.add('hidden');
        rejectModal.classList.remove('hidden');
        rejectModal.classList.add('flex');
        rejectReason.focus();
    }

    function closeRejectModal() {
        rejectModal?.classList.add('hidden');
        rejectModal?.classList.remove('flex');
    }

    rejectCloseBtn?.addEventListener('click',  closeRejectModal);
    rejectCancelBtn?.addEventListener('click', closeRejectModal);
    rejectModal?.addEventListener('click', e => { if (e.target === rejectModal) closeRejectModal(); });

    rejectConfirm?.addEventListener('click', async () => {
        const id     = rejectIdInput?.value;
        const reason = rejectReason?.value.trim() ?? '';

        if (!reason) {
            rejectError.textContent = 'Rejection reason is required.';
            rejectError.classList.remove('hidden');
            rejectReason?.focus();
            return;
        }

        rejectConfirm.disabled = true;
        rejectConfirm.innerHTML = '<i data-lucide="loader" class="h-4 w-4 mr-2 animate-spin inline"></i>Rejecting…';

        try {
            const res = await fetch(`${BASE_URL}/create-file-tracker/kangis-checkout/${id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept'      : 'application/json',
                },
                body: JSON.stringify({ rejection_reason: reason }),
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message ?? 'Rejection failed');
            showToast('Checkout request rejected.', 'success');
            closeRejectModal();
            loadList();
        } catch (err) {
            rejectError.textContent = err.message;
            rejectError.classList.remove('hidden');
        } finally {
            rejectConfirm.disabled = false;
            rejectConfirm.innerHTML = '<i data-lucide="x-circle" class="h-4 w-4 mr-2 inline"></i>Reject';
            if (window.lucide) window.lucide.createIcons();
        }
    });

    /* ──────────────────────────────────────────────────────────────
       ACKNOWLEDGEMENT SHEET
    ────────────────────────────────────────────────────────────── */
    const ackModal     = $('co-ack-modal');
    const ackCloseBtn  = $('co-ack-close');
    const ackPrintBtn  = $('co-ack-print');
    const ackContent   = $('co-ack-content');

    function renderAckSheet(record) {
        if (!ackModal || !ackContent) return;

        const now = new Date().toLocaleString('en-GB', {
            day:'2-digit', month:'long', year:'numeric',
            hour:'2-digit', minute:'2-digit'
        });

        const mod = CURRENT_MODULE || 'kangis';
        const registryLabel = { kangis: 'KANGIS Registry', sltr: 'SLTR Registry', st: 'ST Registry' }[mod] || 'Registry';
        const registryHeaderLine = mod === 'kangis' ? 'KANGIS REGISTRY' : `${registryLabel.toUpperCase()}`;
        const acknowledgementHeaderLine = mod === 'kangis'
            ? 'KANGIS FILE REGISTRY CHECKOUT ACKNOWLEDGEMENT'
            : `${registryLabel.toUpperCase()} FILE REGISTRY CHECKOUT ACKNOWLEDGEMENT`;
        const trackingId = record.request_no || record.acknowledgement_no || '';
        const qrUrl = `/qr/svg?size=120&data=${encodeURIComponent(trackingId)}`;
        const baseUrl = (document.querySelector('meta[name="app-base-url"]')?.content || '').replace(/\/$/, '');
        const approverDesignation = record.approved_by_designation
            || record.approved_by_role
            || record.approved_by_type
            || 'Registry Officer';

        ackContent.innerHTML = `
        <div id="co-ack-printable" class="font-sans text-gray-800 text-sm" style="position:relative;width:210mm;min-height:297mm;padding:16mm 14mm;background:#fff;margin:0 auto;box-shadow:0 0 10px rgba(0,0,0,0.16);font-family:'Roboto', Arial, sans-serif;">

            <!-- Watermark -->
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:0;pointer-events:none;opacity:0.06;">
                <img src="${baseUrl}/assets/logo/court_of arms.jpeg" alt="" style="width:280px;height:280px;object-fit:contain;">
            </div>

            <div style="position:relative;z-index:1;">

            <!-- Official Header -->
            <div style="display:flex;align-items:flex-start;justify-content:space-between;border-bottom:3px solid #111;padding-bottom:12px;margin-bottom:20px;">
                <div style="flex-shrink:0;width:78px;height:78px;display:flex;align-items:center;justify-content:center;">
                    <img src="${baseUrl}/assets/logo/logo1.jpg" alt="Logo" style="width:100%;height:100%;object-fit:contain;" onerror="this.style.display='none'">
                </div>
                <div style="flex:1;text-align:center;padding:0 16px;">
                    <p style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 2px;">Kano State Ministry of Land and Physical Planning</p>
                    <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin:0 0 4px;color:#444;">${escHtml(registryHeaderLine)}</p>
                    <p style="font-size:15px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin:0;color:#111;">${escHtml(acknowledgementHeaderLine)}</p>
                </div>
                <div style="flex-shrink:0;display:flex;align-items:flex-start;gap:8px;">
                    <div style="width:72px;height:72px;">
                        <img src="${qrUrl}" alt="QR" style="width:100%;height:100%;object-fit:contain;">
                    </div>
                    <div style="width:72px;height:72px;display:flex;align-items:center;justify-content:center;">
                        <img src="${baseUrl}/assets/logo/kangis.jpg" alt="Seal" style="width:100%;height:100%;object-fit:contain;" onerror="this.style.display='none'">
                    </div>
                </div>
            </div>

            <!-- Reference Numbers -->
            <div style="display:flex;gap:12px;margin-bottom:20px;">
                <div style="flex:1;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:12px;">
                    <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#92400e;margin:0 0 4px;">Request Number</p>
                    <p style="font-family:monospace;font-size:16px;font-weight:800;color:#78350f;margin:0;">${escHtml(record.request_no)}</p>
                </div>
                <div style="flex:1;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px;">
                    <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#166534;margin:0 0 4px;">Acknowledgement No.</p>
                    <p style="font-family:monospace;font-size:16px;font-weight:800;color:#14532d;margin:0;">${escHtml(record.acknowledgement_no)}</p>
                </div>
            </div>

            <!-- File Details Section -->
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;border:1px solid #d1d5db;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th colspan="2" style="text-align:left;padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#374151;border-bottom:1px solid #d1d5db;">File Details</th>
                    </tr>
                </thead>
                <tbody>
                    ${trow('File Number', record.file_number || '—')}
                    ${trow('File Title', record.file_title || '—')}
                    ${trow('Registry', registryLabel)}
                    ${trow('Purpose', record.purpose || '—')}
                </tbody>
            </table>

            <!-- Requester Details Section -->
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;border:1px solid #d1d5db;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th colspan="2" style="text-align:left;padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#374151;border-bottom:1px solid #d1d5db;">Requested By</th>
                    </tr>
                </thead>
                <tbody>
                    ${trow('Name', record.requested_by_name || '—')}
                    ${trow('Office', record.requested_office_name || '—')}
                    ${trow('Request Date', formatDate(record.created_at))}
                </tbody>
            </table>

            <!-- Approval Details Section -->
            <table style="width:100%;border-collapse:collapse;margin-bottom:20px;border:1px solid #d1d5db;">
                <thead>
                    <tr style="background:#dcfce7;">
                        <th colspan="2" style="text-align:left;padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#166534;border-bottom:1px solid #d1d5db;">Approved By (Registry)</th>
                    </tr>
                </thead>
                <tbody>
                    ${trow('Name', record.approved_by_name || '—')}
                    ${trow('Designation', approverDesignation)}
                    ${trow('Approval Date', formatDateTime(record.approved_at))}
                </tbody>
            </table>

            <!-- Signature Block -->
            <div style="display:flex;gap:60px;margin-top:32px;">
                <div style="flex:1;text-align:center;">
                    <div style="border-bottom:1.5px solid #555;height:40px;"></div>
                    <p style="font-size:11px;color:#555;margin:4px 0 2px;">Requestor's Signature</p>
                    <p style="font-size:10px;color:#888;margin:0;">${escHtml(record.requested_by_name || '')}</p>
                </div>
                <div style="flex:1;text-align:center;">
                    <div style="border-bottom:1.5px solid #555;height:40px;"></div>
                    <p style="font-size:11px;color:#555;margin:4px 0 2px;">Registry Officer's Signature &amp; Stamp</p>
                    <p style="font-size:10px;color:#888;margin:0;">${escHtml(record.approved_by_name || '')}</p>
                </div>
            </div>

            <!-- Footer -->
            <div style="margin-top:28px;border-top:2px solid #e5e7eb;padding-top:12px;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <img src="${baseUrl}/assets/logo/klaes.png" alt="KLAES" style="width:36px;height:36px;object-fit:contain;" onerror="this.style.display='none'">
                    <span style="font-size:11px;font-weight:700;color:#333;">KLAES</span>
                </div>
                <div style="text-align:center;flex:1;padding:0 12px;">
                    <p style="font-size:9px;color:#9ca3af;margin:0;">Kano State Ministry of Land and Physical Planning &bull; ${escHtml(registryLabel)}</p>
                    <p style="font-size:9px;color:#9ca3af;margin:2px 0 0;">Generated: ${now} &bull; This document is valid only with the Registry Officer's official stamp.</p>
                </div>
                <div>
                    <img src="${baseUrl}/assets/logo/las.jpeg" alt="LAS" style="width:36px;height:36px;object-fit:contain;" onerror="this.style.display='none'">
                </div>
            </div>

            </div><!-- /z-index wrapper -->
        </div>`;

        ackModal.classList.remove('hidden');
        ackModal.classList.add('flex');
        if (window.lucide) window.lucide.createIcons();
    }

    function trow(label, value) {
        return `<tr style="border-bottom:1px solid #e5e7eb;">
            <td style="padding:8px 12px;font-size:11px;color:#6b7280;font-weight:600;width:160px;vertical-align:top;">${escHtml(label)}</td>
            <td style="padding:8px 12px;font-size:12px;color:#1f2937;font-weight:600;">${escHtml(value)}</td>
        </tr>`;
    }

    function row(label, value) {
        return `<div class="px-4 py-2.5 flex justify-between gap-4">
            <span class="text-xs text-gray-500 font-medium w-40 shrink-0">${escHtml(label)}</span>
            <span class="text-sm text-gray-800 font-semibold text-right">${escHtml(value)}</span>
        </div>`;
    }

    ackCloseBtn?.addEventListener('click', () => {
        ackModal?.classList.add('hidden');
        ackModal?.classList.remove('flex');
    });

    ackModal?.addEventListener('click', e => {
        if (e.target === ackModal) {
            ackModal.classList.add('hidden');
            ackModal.classList.remove('flex');
        }
    });

    ackPrintBtn?.addEventListener('click', () => {
        const printable = document.getElementById('co-ack-printable');
        if (!printable) return;

        const mod = CURRENT_MODULE || 'kangis';
        const registryLabel = { kangis: 'KANGIS Registry', sltr: 'SLTR Registry', st: 'ST Registry' }[mod] || 'Registry';

        const win = window.open('', '_blank', 'width=800,height=700');
        win.document.write(`<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8">
<title>${registryLabel} – Checkout Acknowledgement</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    @page { size: A4; margin: 0; }
    @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
  * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Roboto', Arial, sans-serif; font-size: 12px; color: #1f2937; background: #525659; padding: 20px; }
  img { max-width: 100%; }
  table { width: 100%; border-collapse: collapse; }
    #co-ack-printable { box-shadow: 0 0 10px rgba(0,0,0,0.45) !important; }
  @media print {
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: none; padding: 0; }
        #co-ack-printable { box-shadow: none !important; margin: 0 !important; }
  }
</style>
</head><body>
${printable.outerHTML}
<script>window.onload=function(){window.print();}<\/script>
</body></html>`);
        win.document.close();
    });

    /* ──────────────────────────────────────────────────────────────
       FILTER BUTTONS
    ────────────────────────────────────────────────────────────── */
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            state.filter = btn.dataset.filter ?? 'all';
            filterBtns.forEach(b => {
                b.classList.remove('active', 'ring-2', 'ring-amber-400', 'ring-offset-1', 'bg-amber-100', 'text-amber-800');
                b.classList.add('bg-gray-100', 'text-gray-700');
            });
            btn.classList.add('active', 'ring-2', 'ring-amber-400', 'ring-offset-1', 'bg-amber-100', 'text-amber-800');
            btn.classList.remove('bg-gray-100', 'text-gray-700');
            loadList();
        });
    });

    /* ──────────────────────────────────────────────────────────────
       SEARCH
    ────────────────────────────────────────────────────────────── */
    let searchTimer;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.search = searchInput.value.trim();
            loadList();
        }, 350);
    });

    /* ──────────────────────────────────────────────────────────────
       REFRESH BUTTON
    ────────────────────────────────────────────────────────────── */
    btnRefresh?.addEventListener('click', () => loadList());

    /* ──────────────────────────────────────────────────────────────
       TAB SWITCH — hook into the existing tab system
    ────────────────────────────────────────────────────────────── */
    document.addEventListener('click', e => {
        const mainTab = e.target.closest('.main-tab');
        if (mainTab && mainTab.dataset.tab === 'checkout') {
            // Deactivate all tabs
            document.querySelectorAll('.main-tab').forEach(t => {
                t.classList.remove('border-blue-500', 'text-blue-600', 'border-amber-400', 'text-amber-700');
                t.classList.add('border-transparent', 'text-gray-500');
            });
            // Deactivate all contents
            document.querySelectorAll('.main-tab-content').forEach(c => {
                c.classList.add('hidden');
                c.classList.remove('active');
            });
            // Activate checkout tab
            mainTab.classList.remove('border-transparent', 'text-gray-500');
            mainTab.classList.add('border-amber-400', 'text-amber-700');

            const content = document.getElementById('content-checkout');
            if (content) {
                content.classList.remove('hidden');
                content.classList.add('active');
            }

            // Load records once per tab open
            loadList();
        }
    });

    /* ──────────────────────────────────────────────────────────────
       INITIAL LOAD (on page load, do a background stats-only fetch)
    ────────────────────────────────────────────────────────────── */
    (async function initStats() {
        try {
            const res = await fetch(`${BASE_URL}/create-file-tracker/kangis-checkout?status=all&module=${encodeURIComponent(CURRENT_MODULE)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (json.success) {
                const records = json.data ?? [];
                const pending = records.filter(r => r.status === 'pending').length;
                if (pendingBadge && pending > 0) {
                    pendingBadge.textContent = pending;
                    pendingBadge.classList.remove('hidden');
                }
            }
        } catch (_) { /* silent */ }
    })();

    // Auto-switch to checkout tab when page is loaded as kangis module
    if (window.isKangisModule) {
        const checkoutTab = document.getElementById('tab-checkout');
        if (checkoutTab) checkoutTab.click();
    }

})();
</script>

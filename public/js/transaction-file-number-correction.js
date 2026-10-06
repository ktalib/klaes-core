/**
 * transaction-file-number-correction.js
 *
 * "Correct File No. (Main ↔ Temp)" dialog for the PRA (/propertycard), File History
 * (/file-index-view) and CofO (/propertycard/cofo) transaction tables.
 *
 * Lists every PRA / File History / CofO transaction recorded under a file's main number or its
 * temporary "(T)" number, and lets the user move one transaction to the other number. prop_id is
 * never changed (TransactionFileNumberCorrectionService).
 *
 * Config comes from resources/views/components/transaction-file-number-correction.blade.php:
 *   window.TxnFileNoCorrection = { canCorrect, candidatesUrl, updateUrl }
 *
 * Usage:
 *   openTransactionFileNumberCorrection({ table: 'pra', id: 123, onChanged: () => table.ajax.reload(null, false) })
 *   txnFileNoCorrectionMenuItem('pra', 123, 'css classes')  // '' when the user may not correct
 */
(function () {
    'use strict';

    const cfg = () => window.TxnFileNoCorrection || {};

    const esc = (v) => String(v == null ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    const dash = (v) => (v == null || String(v).trim() === '' ? '—' : esc(v));

    const csrf = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    const notify = (icon, title, text) => {
        if (window.Swal) {
            return Swal.fire({ icon, title, text });
        }
        alert(title + (text ? '\n\n' + text : ''));
        return Promise.resolve();
    };

    /** Menu item HTML for a row's action menu; empty when the user may not correct. */
    window.txnFileNoCorrectionMenuItem = function (table, id, classes) {
        if (!cfg().canCorrect || !table || !id) return '';
        const cls = classes || 'block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900';
        return `<a href="javascript:void(0)" class="txn-fileno-correct-btn ${esc(cls)}" role="menuitem"
                   data-txn-table="${esc(table)}" data-txn-id="${esc(id)}">Correct File No. (Main &harr; Temp)</a>`;
    };

    const renderTable = (data) => {
        const rows = (data.transactions || []).map((t) => {
            const selected = data.selected && data.selected.table === t.table && Number(data.selected.id) === Number(t.id);
            const target = t.is_temp ? 'main' : 'temp';
            const targetNo = t.is_temp ? data.main : data.temp;
            const badge = t.is_temp
                ? `<span style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:9999px;padding:1px 8px;font-weight:600;white-space:nowrap;display:inline-block;">${esc(t.file_number)}</span>`
                : `<span style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;border-radius:9999px;padding:1px 8px;font-weight:600;white-space:nowrap;display:inline-block;">${esc(t.file_number)}</span>`;
            const action = `<button type="button" class="txn-fileno-move" data-table="${esc(t.table)}" data-id="${esc(t.id)}"
                       data-target="${target}" data-from="${esc(t.file_number)}" data-to="${esc(targetNo)}"
                       data-new-temp="${target === 'temp' && !data.temp_registered ? '1' : ''}"
                       style="background:#4f46e5;color:#fff;border:none;border-radius:4px;padding:3px 8px;font-size:11px;white-space:nowrap;cursor:pointer;">
                       Update to ${esc(targetNo)}</button>`;
            return `<tr style="${selected ? 'background:#eef2ff;' : ''}border-top:1px solid #e5e7eb;">
                <td style="padding:6px;white-space:nowrap;">${esc(t.source)} #${esc(t.id)}</td>
                <td style="padding:6px;white-space:nowrap;">${badge}</td>
                <td style="padding:6px;">${dash(t.transaction_type)}</td>
                <td style="padding:6px;white-space:nowrap;">${dash(t.transaction_date)}</td>
                <td style="padding:6px;">${dash(t.party_1)} &rarr; ${dash(t.party_2)}</td>
                <td style="padding:6px;white-space:nowrap;">${dash(t.registration)}</td>
                <td style="padding:6px;text-align:right;">${action}</td>
            </tr>`;
        }).join('');

        const tempNote = data.temp_registered
            ? ''
            : `<p style="margin:0 0 8px;color:#1e40af;font-size:12px;">${esc(data.temp)} has no transactions yet. Moving a transaction to it starts the temporary number.</p>`;

        return `<div style="text-align:left;font-size:12px;">
            <p style="margin:0 0 6px;">Main: <b>${esc(data.main)}</b> &nbsp;&middot;&nbsp; Temporary: <b>${esc(data.temp)}</b></p>
            <p style="margin:0 0 8px;color:#6b7280;">Moving a transaction changes only the file number it is recorded under. Its prop_id stays the same, and Legal Search returns it from either number.</p>
            ${tempNote}
            <div style="max-height:55vh;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead style="background:#f9fafb;position:sticky;top:0;">
                        <tr>
                            <th style="padding:6px;text-align:left;">Source</th>
                            <th style="padding:6px;text-align:left;">File No</th>
                            <th style="padding:6px;text-align:left;">Transaction</th>
                            <th style="padding:6px;text-align:left;">Date</th>
                            <th style="padding:6px;text-align:left;">Parties</th>
                            <th style="padding:6px;text-align:left;">Reg.</th>
                            <th style="padding:6px;"></th>
                        </tr>
                    </thead>
                    <tbody>${rows || '<tr><td colspan="7" style="padding:12px;text-align:center;color:#9ca3af;">No transactions found.</td></tr>'}</tbody>
                </table>
            </div>
        </div>`;
    };

    const fetchCandidates = (table, id) => {
        const url = cfg().candidatesUrl + '?' + new URLSearchParams({ table, id }).toString();
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok || !body.success) throw new Error(body.message || 'Could not load the transactions.');
                return body.data;
            });
    };

    const postCorrection = (payload) => fetch(cfg().updateUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(payload),
    }).then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
          if (!ok || !body.success) {
              const firstError = body.errors ? Object.values(body.errors)[0] : null;
              throw new Error((firstError && firstError[0]) || body.message || 'The correction could not be saved.');
          }
          return body;
      });

    window.openTransactionFileNumberCorrection = function (opts) {
        const table = opts && opts.table;
        const id = opts && opts.id;
        const onChanged = (opts && typeof opts.onChanged === 'function') ? opts.onChanged : null;

        if (!cfg().canCorrect) {
            notify('error', 'Not permitted', 'You do not have permission to correct file numbers.');
            return;
        }
        if (!window.Swal) {
            alert('The correction dialog needs SweetAlert2, which is not loaded on this page.');
            return;
        }

        let changed = false;

        const show = () => {
            Swal.fire({ title: 'Loading transactions…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetchCandidates(table, id).then((data) => {
                Swal.fire({
                    title: 'Correct File No. (Main ↔ Temp)',
                    html: renderTable(data),
                    width: '64rem',
                    showConfirmButton: false,
                    showCloseButton: true,
                    didOpen: (popup) => {
                        popup.querySelectorAll('.txn-fileno-move').forEach((btn) => {
                            btn.addEventListener('click', () => confirmMove(btn.dataset));
                        });
                    },
                    willClose: () => {
                        if (changed && onChanged) onChanged();
                        changed = false;
                    },
                });
            }).catch((err) => notify('error', 'Could not load transactions', err.message));
        };

        const confirmMove = (d) => {
            Swal.fire({
                title: 'Move transaction?',
                html: `<div style="text-align:left;font-size:13px;">
                        <p>Move this transaction from <b>${esc(d.from)}</b> to <b>${esc(d.to)}</b>.</p>
                        ${d.newTemp ? `<p style="color:#1e40af;margin-top:6px;">This is the first transaction on <b>${esc(d.to)}</b>.</p>` : ''}
                        <p style="color:#6b7280;margin-top:6px;">The prop_id is not changed. The correction is recorded in the audit log.</p>
                       </div>`,
                showCancelButton: true,
                confirmButtonText: 'Move',
                confirmButtonColor: '#4f46e5',
                showLoaderOnConfirm: true,
                allowOutsideClick: () => !Swal.isLoading(),
                // Reason input hidden for now; the server accepts an empty reason.
                preConfirm: () => {
                    return postCorrection({ table: d.table, id: Number(d.id), target: d.target, reason: '' })
                        .catch((err) => {
                            Swal.showValidationMessage(err.message);
                            return false;
                        });
                },
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    changed = true;
                    Swal.fire({ icon: 'success', title: 'File number corrected', text: result.value.message, timer: 2200, showConfirmButton: false })
                        .then(() => {
                            // Reopen the list so the next transaction can be corrected.
                            if (onChanged) onChanged();
                            changed = false;
                            show();
                        });
                } else {
                    show();
                }
            });
        };

        show();
    };

    // Delegated handler for menu items rendered by txnFileNoCorrectionMenuItem(). The page can
    // register a reload hook with window.TxnFileNoCorrection.onChanged. Capture phase, so the
    // click never reaches row handlers underneath (the PRA table loads the row on click).
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('.txn-fileno-correct-btn');
        if (!btn) return;
        event.preventDefault();
        event.stopPropagation();
        document.querySelectorAll('.action-menu, .txn-fileno-menu').forEach((m) => m.classList.add('hidden'));
        window.openTransactionFileNumberCorrection({
            table: btn.getAttribute('data-txn-table'),
            id: btn.getAttribute('data-txn-id'),
            onChanged: cfg().onChanged,
        });
    }, true);
})();

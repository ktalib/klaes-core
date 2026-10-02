/**
 * MasterDelete — the confirmation flow shared by every Recommendation and RofO
 * master delete (Land, SLTR and ST).
 *
 * Two steps on purpose, matching the ST file-number master delete this is modelled
 * on. The first step says what will be removed; the second makes the operator type
 * the document's own number back. These entries sit in a row menu next to Print,
 * on a screen that is a list of near-identical rows, and nothing behind them can
 * be undone — one misplaced click must not be enough.
 *
 * The server re-checks the typed confirmation and the Supper Admin role. This is
 * the courtesy, not the gate.
 *
 * Usage:
 *   MasterDelete.confirm({
 *     url: '/land-rofos/12/master-destroy',
 *     reference: 'KN/LKJ/2024/001',   // what the operator must type
 *     title: 'Master Delete RofO',
 *     lead: 'This permanently deletes the RofO for <b>…</b>.',
 *     targets: ['RofO issuance', 'PRA transaction'],
 *     keeps: 'The recommendation itself is kept.',   // optional, green line
 *     body: { scope: 'unit', id: 12 },               // optional extra payload
 *     onDone: () => location.reload()
 *   });
 */
window.MasterDelete = (function () {
    'use strict';

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function confirm(options) {
        const reference = String(options.reference || '').trim();

        if (!reference) {
            Swal.fire({
                icon: 'error',
                title: 'Nothing to confirm against',
                text: 'This record has no file number, so a master delete cannot be confirmed. Report it rather than forcing it through.'
            });
            return;
        }

        const targets = (options.targets || [])
            .map(function (t) { return '<li>' + t + '</li>'; })
            .join('');

        Swal.fire({
            icon: 'warning',
            title: options.title || 'Destructive action',
            html:
                '<div style="text-align:left;font-size:13px">' +
                    '<p style="margin-bottom:8px">' + (options.lead || '') + '</p>' +
                    (targets
                        ? '<p style="margin-bottom:4px;font-weight:600">What will be removed:</p>' +
                          '<ul style="margin:0 0 10px 18px;list-style:disc">' + targets + '</ul>'
                        : '') +
                    (options.keeps
                        ? '<p style="color:#047857;font-weight:600;margin:0">' + options.keeps + '</p>'
                        : '') +
                '</div>',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Continue',
            cancelButtonText: 'Cancel',
            focusCancel: true
        }).then(function (step) {
            if (!step.isConfirmed) return;

            Swal.fire({
                icon: 'error',
                title: 'Type the number to confirm',
                input: 'text',
                inputPlaceholder: reference,
                html: '<p style="font-size:13px">Type <b>' + escapeHtml(reference) + '</b> exactly to delete it.</p>',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Delete permanently',
                cancelButtonText: 'Cancel',
                focusCancel: true,
                preConfirm: function (value) {
                    if (String(value || '').trim().toUpperCase() !== reference.toUpperCase()) {
                        Swal.showValidationMessage('That does not match.');
                        return false;
                    }
                    return String(value).trim();
                }
            }).then(function (confirmStep) {
                if (!confirmStep.isConfirmed) return;

                Swal.fire({
                    title: 'Deleting…',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetch(options.url, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(Object.assign({ confirm: confirmStep.value }, options.body || {}))
                })
                    .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Server returned an unreadable response.' }; }); })
                    .then(function (result) {
                        if (!result.success) {
                            throw new Error(result.message || 'Delete failed.');
                        }

                        return Swal.fire({
                            icon: 'success',
                            title: 'Deleted',
                            text: result.message || 'The record was deleted.'
                        }).then(function () {
                            if (typeof options.onDone === 'function') {
                                options.onDone(result);
                            } else {
                                window.location.reload();
                            }
                        });
                    })
                    .catch(function (err) {
                        Swal.fire({ icon: 'error', title: 'Delete failed', text: err.message });
                    });
            });
        });
    }

    return { confirm: confirm };
})();

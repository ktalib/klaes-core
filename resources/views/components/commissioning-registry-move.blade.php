{{--
    Shared "move this file to the other commissioning registry" action.

    Included by both the MLS File Number Generator list and the OSS list so the two
    directions cannot drift apart: one confirm dialog, one endpoint, one reload rule.
    The caller passes the target, so the same function serves "send to OSS" and
    "send back to Land".
--}}
<script>
(function () {
    if (window.moveCommissioningRegistry) return; // included on a page twice

    var LABELS = {
        OSS: 'OSS File Commissioning',
        MLS: 'Land (MLS File Commissioning)'
    };

    /**
     * @param {Event}  evt
     * @param {string} fileNumber
     * @param {string} target 'OSS' or 'MLS'
     */
    window.moveCommissioningRegistry = function (evt, fileNumber, target) {
        if (evt && evt.stopPropagation) evt.stopPropagation();

        fileNumber = String(fileNumber || '').trim();
        target = String(target || '').toUpperCase();

        if (!fileNumber) {
            Swal.fire('Missing file number', 'This row has no file number to move.', 'warning');
            return;
        }
        if (target !== 'OSS' && target !== 'MLS') {
            Swal.fire('Unknown destination', 'The move target must be OSS or Land.', 'error');
            return;
        }

        var to = LABELS[target];
        var from = LABELS[target === 'OSS' ? 'MLS' : 'OSS'];

        Swal.fire({
            icon: 'question',
            title: 'Move to ' + to + '?',
            html:
                '<p style="font-size:13px;color:#475569;margin-bottom:10px;">'
                + '<strong>' + fileNumber + '</strong> will be re-filed from ' + from + ' to ' + to + '.'
                + '</p>'
                + '<p style="font-size:12px;color:#64748b;text-align:left;">'
                + 'This changes only where the file is listed: its commissioning stamp and its '
                + 'OSS application row. The file number, its indexing and any registered '
                + 'instruments are left untouched, and the move can be reversed from the other list.'
                + '</p>',
            width: 520,
            showCancelButton: true,
            confirmButtonText: 'Yes, move it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            showLoaderOnConfirm: true,
            allowOutsideClick: function () { return !Swal.isLoading(); },
            preConfirm: function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return fetch('{{ route('commissioning-registry.move') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': meta ? meta.getAttribute('content') : ''
                    },
                    body: JSON.stringify({ file_number: fileNumber, target: target })
                })
                .then(function (r) {
                    return r.json().catch(function () { return null; }).then(function (body) {
                        // A non-2xx carries the reason in the same envelope; surface that
                        // rather than a generic failure the officer cannot act on.
                        if (!r.ok || !body || body.success !== true) {
                            throw new Error((body && body.message) || 'The move failed (HTTP ' + r.status + ').');
                        }
                        return body;
                    });
                })
                .catch(function (e) {
                    Swal.showValidationMessage(e.message || 'The move failed.');
                });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            Swal.fire({
                icon: 'success',
                title: 'Moved',
                text: result.value.message || (fileNumber + ' moved to ' + to + '.'),
                timer: 2200,
                showConfirmButton: false
            }).then(function () {
                // The row no longer belongs on this list, so the list has to be re-read.
                if (typeof window.reloadAfterRegistryMove === 'function') {
                    window.reloadAfterRegistryMove();
                } else {
                    window.location.reload();
                }
            });
        });
    };
})();
</script>

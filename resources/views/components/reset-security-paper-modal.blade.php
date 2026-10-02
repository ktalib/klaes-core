{{--
  Global "Reset Security Paper Code" modal.
  Exposes: window.openResetSecurityPaperModal(id, fileNumber, currentSerial, postEndpoint, options)
    options (optional object):
      assignEndpoint – POST url for assigning a new code. When given, a
                       successful reset opens the assign picker instead of
                       reloading, so the operator can key in the correct code
                       in one go. The page reloads once that step finishes or
                       is cancelled.
      codesApiUrl    – passed through to the assign modal, and used to drop its
                       cached code list so the freed code shows up immediately
      fieldName      – passed through to the assign modal
      onSuccess      – callback that replaces both the chaining and the reload

  The reason matters: a mutilated / mis-printed sheet is physically destroyed, so
  its code is retired and never returns to the pool. The other two reasons put the
  code straight back. Kept in one component so Land RofO, SLTR and ST behave alike.
--}}
<script>
(function () {
    var REASONS = [
        {
            value: 'mistake_output',
            label: 'Mistake on Output / Mutilated Paper',
            hint:  'The printed sheet is spoiled. This code is retired permanently and will NOT return to the pool.',
            icon:  '&#9888;',
            color: '#b45309',
            bg:    '#fffbeb',
            border:'#fcd34d'
        },
        {
            value: 'spc_mismatch',
            label: 'SPC Mismatch',
            hint:  'The code keyed in does not match the sheet. The paper is untouched, so the code returns to the pool.',
            icon:  '&#8646;',
            color: '#1d4ed8',
            bg:    '#eff6ff',
            border:'#93c5fd'
        },
        {
            value: 'drop_spc',
            label: 'Drop SPC',
            hint:  'Detach this code from the record. The code returns to the pool for reuse.',
            icon:  '&#10005;',
            color: '#475569',
            bg:    '#f8fafc',
            border:'#cbd5e1'
        }
    ];

    window.openResetSecurityPaperModal = function (id, fileNumber, currentSerial, postEndpoint, options) {
        var opts = options || {};

        var optionsHtml = REASONS.map(function (r, i) {
            return '' +
            '<label for="spcr-' + r.value + '" style="display:block;cursor:pointer;margin-bottom:8px;">' +
                '<input type="radio" name="spcr-reason" id="spcr-' + r.value + '" value="' + r.value + '" ' +
                       'style="position:absolute;opacity:0;pointer-events:none;">' +
                '<div class="spcr-card" data-value="' + r.value + '" ' +
                     'style="border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 13px;transition:all .12s;background:#fff;">' +
                    '<div style="display:flex;align-items:center;gap:9px;">' +
                        '<span style="font-size:15px;line-height:1;color:' + r.color + ';">' + r.icon + '</span>' +
                        '<span style="font-weight:700;font-size:13.5px;color:#1e293b;">' + r.label + '</span>' +
                    '</div>' +
                    '<div style="font-size:11.5px;color:#64748b;margin-top:5px;line-height:1.45;">' + r.hint + '</div>' +
                '</div>' +
            '</label>';
        }).join('');

        var html = '' +
            '<div style="text-align:left;">' +
                '<p style="font-size:13px;color:#475569;margin-bottom:4px;">' +
                    'Resetting the security paper code for <strong style="color:#2563eb;">' + fileNumber + '</strong>' +
                '</p>' +
                (currentSerial
                    ? '<p style="font-size:12px;color:#64748b;margin-bottom:12px;">Current code: <strong style="font-family:monospace;color:#0f172a;">' + currentSerial + '</strong></p>'
                    : '<div style="margin-bottom:12px;"></div>') +
                '<p style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;">Why is this being reset?</p>' +
                optionsHtml +
                '<p id="spcr-warn" style="display:none;margin-top:10px;padding:9px 11px;border-radius:8px;' +
                   'background:#fef2f2;border:1.5px solid #fecaca;color:#b91c1c;font-size:11.5px;font-weight:600;line-height:1.45;"></p>' +
            '</div>';

        Swal.fire({
            title: 'Reset Security Paper Code',
            html: html,
            width: 520,
            showCancelButton: true,
            confirmButtonText: 'Reset Code',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            didOpen: function () {
                var cards = Swal.getPopup().querySelectorAll('.spcr-card');
                var warn  = document.getElementById('spcr-warn');

                function paint() {
                    var picked = Swal.getPopup().querySelector('input[name="spcr-reason"]:checked');
                    cards.forEach(function (card) {
                        var r  = REASONS.filter(function (x) { return x.value === card.dataset.value; })[0];
                        var on = picked && picked.value === card.dataset.value;
                        card.style.borderColor = on ? r.border : '#e2e8f0';
                        card.style.background  = on ? r.bg : '#fff';
                        card.style.boxShadow   = on ? '0 0 0 3px ' + r.bg : 'none';
                    });

                    // Only the retiring reason gets the hard warning.
                    if (picked && picked.value === 'mistake_output') {
                        warn.innerHTML = 'Code <strong>' + (currentSerial || '') + '</strong> will be voided permanently. ' +
                                         'It cannot be assigned to any file again, on any screen.';
                        warn.style.display = 'block';
                    } else {
                        warn.style.display = 'none';
                    }
                }

                Swal.getPopup().querySelectorAll('input[name="spcr-reason"]').forEach(function (input) {
                    input.addEventListener('change', paint);
                });
                cards.forEach(function (card) {
                    card.addEventListener('mouseenter', function () {
                        if (card.style.background === 'rgb(255, 255, 255)' || card.style.background === '#fff') {
                            card.style.borderColor = '#cbd5e1';
                        }
                    });
                });
                paint();
            },
            preConfirm: function () {
                var picked = Swal.getPopup().querySelector('input[name="spcr-reason"]:checked');
                if (!picked) {
                    Swal.showValidationMessage('Please choose a reason for the reset');
                    return false;
                }
                return { reason: picked.value };
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Resetting...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            fetch(postEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: result.value.reason })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to reset code.' });
                    return;
                }

                var msg = data.returned_to_pool
                    ? 'Code ' + (data.paper_code || '') + ' is back in the pool.'
                    : 'Code ' + (data.paper_code || '') + ' has been voided and will not be reissued.';

                // The freed code has to appear in the picker straight away.
                if (typeof window.invalidateSecurityPaperCodes === 'function') {
                    window.invalidateSecurityPaperCodes(opts.codesApiUrl);
                }

                Swal.fire({ icon: 'success', title: 'Code Reset', text: msg, timer: 1600, showConfirmButton: false })
                    .then(function () {
                        if (typeof opts.onSuccess === 'function') {
                            opts.onSuccess(data);
                            return;
                        }

                        // The point of a reset is almost always to key in the
                        // right code, so go straight to the picker and only
                        // reload once that step is done or abandoned.
                        if (opts.assignEndpoint && typeof window.openAssignSecurityPaperModal === 'function') {
                            var assignOpts = {};
                            if (opts.codesApiUrl) { assignOpts.codesApiUrl = opts.codesApiUrl; }
                            if (opts.fieldName)   { assignOpts.fieldName   = opts.fieldName; }
                            assignOpts.onDismiss = function () { location.reload(); };

                            // Pass no current serial — it was just cleared, and
                            // prefilling it would invite reassigning the code
                            // that was reset.
                            openAssignSecurityPaperModal(id, fileNumber, '', opts.assignEndpoint, assignOpts);
                            return;
                        }

                        location.reload();
                    });
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' });
            });
        });
    };
})();
</script>

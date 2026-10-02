{{--
  Global "Assign Security Paper Code" modal.
  Exposes: window.openAssignSecurityPaperModal(id, fileNumber, currentSerial, postEndpoint, options)
    options (optional object):
      codesApiUrl  – GET endpoint returning {codes:[...]}  (default: /security-paper-codes/available)
      fieldName    – body field sent to postEndpoint       (default: 'paper_code')
--}}
<script>
(function () {
    // Cache: null = not yet fetched, [] = fetched but empty, [...] = has codes
    var _cache = {};

    async function fetchCodes(apiUrl) {
        if (apiUrl in _cache) return _cache[apiUrl];
        try {
            var res = await fetch(apiUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json();
            _cache[apiUrl] = Array.isArray(data.codes) ? data.codes : [];
        } catch (e) {
            // Don't cache on failure so the next open retries
            return { error: e.message || 'Failed to load codes' };
        }
        return _cache[apiUrl];
    }

    // Codes are cached per endpoint, so anything that changes the pool from
    // outside this modal (a reset returning a code) must drop the cache first —
    // otherwise the freed code is missing from the list until a page reload.
    window.invalidateSecurityPaperCodes = function (apiUrl) {
        if (apiUrl) { delete _cache[apiUrl]; } else { _cache = {}; }
    };

    window.openAssignSecurityPaperModal = function (id, fileNumber, currentSerial, postEndpoint, options) {
        var opts      = options || {};
        var codesApi  = opts.codesApiUrl || '{{ url("security-paper-codes/available") }}';
        var fieldName = opts.fieldName   || 'paper_code';

        setTimeout(async function () {
            var result = await fetchCodes(codesApi);

            // Handle fetch error
            if (result && result.error) {
                Swal.fire({ icon: 'error', title: 'Could not load codes', text: result.error });
                return;
            }

            var codes = result; // array of strings

            var poolNote = codes.length === 0
                ? '<p style="font-size:11px;color:#ef4444;margin-top:6px;">No codes available in this pool. Contact the system administrator.</p>'
                : '<p style="font-size:11px;color:#64748b;margin-top:6px;">' + codes.length + ' code(s) available — type to search</p>';

            var inputHtml = `
                <p style="font-size:13px;color:#475569;margin-bottom:10px;text-align:left;">
                    Security paper code for file <strong style="color:#2563eb;">${fileNumber}</strong>
                    ${currentSerial ? '<br><span style="font-size:11px;color:#64748b;">Current: ' + currentSerial + '</span>' : ''}
                </p>
                <div style="position:relative;text-align:left;">
                    <input id="spc-value" type="text" placeholder="Type to search a code..."
                        autocomplete="off"
                        ${codes.length === 0 ? 'disabled' : ''}
                        style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;transition:border-color 0.15s;box-sizing:border-box;"
                        onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
                    <div id="spc-suggestions"
                        style="position:absolute;left:0;right:0;top:100%;max-height:200px;overflow-y:auto;border:1.5px solid #3b82f6;border-top:none;border-radius:0 0 8px 8px;background:#fff;z-index:99999;display:none;box-shadow:0 6px 16px rgba(0,0,0,0.12);"></div>
                </div>
                ${poolNote}
            `;

            Swal.fire({
                title: 'Enter Security Paper Code',
                html: inputHtml,
                showCancelButton: true,
                confirmButtonText: 'Assign Code',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                focusConfirm: false,
                didOpen: function () {
                    // Allow the absolute-positioned suggestions to overflow the popup
                    var popup = Swal.getPopup();
                    if (popup) { popup.style.overflow = 'visible'; popup.style.overflowX = 'visible'; }
                    var htmlCont = Swal.getHtmlContainer();
                    if (htmlCont) { htmlCont.style.overflow = 'visible'; }

                    var input = document.getElementById('spc-value');
                    var sugg  = document.getElementById('spc-suggestions');
                    if (!input || !sugg || codes.length === 0) return;

                    function showSuggestions(val) {
                        var q = (val || '').trim().toLowerCase();
                        if (!q) { sugg.style.display = 'none'; return; }
                        var matches = codes.filter(function (c) { return String(c).toLowerCase().includes(q); }).slice(0, 15);
                        if (!matches.length) {
                            sugg.innerHTML = '<div style="padding:10px 14px;color:#94a3b8;font-size:13px;font-style:italic;">No matches for "' + q + '"</div>';
                            sugg.style.display = 'block';
                            return;
                        }
                        sugg.innerHTML = matches.map(function (c) {
                            var safe = String(c).replace(/'/g, "\\'");
                            return '<div style="padding:9px 14px;cursor:pointer;font-size:13px;font-family:monospace;border-bottom:1px solid #f1f5f9;transition:background 0.1s;" ' +
                                   'onmouseover="this.style.background=\'#eff6ff\'" onmouseout="this.style.background=\'\'" ' +
                                   'onmousedown="(function(){document.getElementById(\'spc-value\').value=\'' + safe + '\';document.getElementById(\'spc-suggestions\').style.display=\'none\';})();return false;">' +
                                   String(c) + '</div>';
                        }).join('');
                        sugg.style.display = 'block';
                    }

                    input.addEventListener('input', function () { showSuggestions(this.value); });
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') sugg.style.display = 'none';
                    });
                    // Hide suggestions when focus leaves the input (but not when clicking a suggestion)
                    input.addEventListener('blur', function () {
                        setTimeout(function () { sugg.style.display = 'none'; }, 200);
                    });

                    if (currentSerial) input.value = currentSerial;
                    input.focus();
                },
                preConfirm: function () {
                    var paperCode = (document.getElementById('spc-value').value || '').trim();
                    if (!paperCode) {
                        Swal.showValidationMessage('Please enter or select a security paper code');
                        return false;
                    }
                    return paperCode;
                }
            }).then(function (swalResult) {
                if (!swalResult.isConfirmed) {
                    // Callers that opened this as a follow-up step (e.g. after a
                    // reset) still need the page refreshed on cancel.
                    if (typeof opts.onDismiss === 'function') { opts.onDismiss(); }
                    return;
                }

                Swal.fire({ title: 'Assigning...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                var body = {};
                body[fieldName] = swalResult.value;

                fetch(postEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(body)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        delete _cache[codesApi]; // invalidate so next open re-fetches
                        Swal.fire({ icon: 'success', title: 'Code Assigned', timer: 1800, showConfirmButton: false })
                            .then(function () {
                                if (typeof opts.onSuccess === 'function') { opts.onSuccess(data); }
                                else { location.reload(); }
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to assign code.' });
                    }
                })
                .catch(function () {
                    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' });
                });
            });
        }, 120);
    };
})();
</script>

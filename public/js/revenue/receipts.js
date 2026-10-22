/*
 * KLAES REV-M → Receipting → Awaiting Receipt (resources/views/revenue/receipts/awaiting.blade.php).
 * Generate receipt: confirm, POST, then open the official receipt (three copies) to print.
 */
(function () {
    var cfg = window.RevReceipts || {};

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function message(ok, text) {
        if (window.Swal) {
            return window.Swal.fire({ icon: ok ? 'success' : 'error', title: ok ? 'Receipt generated' : 'Not generated', text: text, confirmButtonColor: '#047857' });
        }
        window.alert(text);
        return Promise.resolve();
    }

    function confirmIssue(ref, amount) {
        var text = 'Generate the official receipt for ' + amount + ' paid on ' + ref + '? It prints as Original, Duplicate and Triplicate.';
        if (window.Swal) {
            return window.Swal.fire({ icon: 'question', title: 'Generate receipt', text: text, showCancelButton: true,
                confirmButtonText: 'Generate', confirmButtonColor: '#047857' }).then(function (r) { return r.isConfirmed; });
        }
        return Promise.resolve(window.confirm(text));
    }

    cfg.issue = function (paymentId, ref, amount) {
        // Opened straight after the confirm click (before the request), so the browser
        // does not block it as a pop-up; it is pointed at the receipt once issued.
        var printWindow = null;

        confirmIssue(ref, amount).then(function (yes) {
            if (!yes) { return; }
            if (cfg.canPrint) { printWindow = window.open('', '_blank'); }

            return fetch(cfg.issueUrl.replace('__ID__', paymentId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (json) {
                    if (!res.ok || json.success === false) { throw new Error(json.message || 'Something went wrong.'); }
                    return json;
                });
            }).then(function (json) {
                var no = (json.message || '').match(/RCT-\d{4}-\d+/);
                if (printWindow && no) {
                    printWindow.location.href = cfg.printUrl.replace('__NO__', no[0]);
                } else if (printWindow) {
                    printWindow.close();
                }
                return message(true, json.message).then(function () { window.location.reload(); });
            }).catch(function (e) {
                if (printWindow) { printWindow.close(); }
                message(false, e.message);
            });
        });
    };

    window.RevReceipts = cfg;
})();

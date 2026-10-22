/*
 * "This OP has no serial number" -- shared by the PRA card, the File Indexing OP section
 * and the Property Transaction card (user, 2026-10-16).
 *
 *   KlaesOpSerialNone.confirm()       Yes / No question asked before the box is ticked.
 *   KlaesOpSerialNone.next()          the serial the next save would most likely get
 *                                     (read-only preview; the real one is set on save).
 *   KlaesOpSerialNone.label(serial)   the preview line shown under the greyed-out box.
 *
 * Loaded by each screen with a plain <script> tag; safe to load more than once.
 */
(function () {
    if (window.KlaesOpSerialNone) return;

    var PREVIEW_URL = '/op-serial/next-preview';

    function confirmNoSerial() {
        var title = 'No OP serial number?';
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                icon: 'question',
                title: title,
                showCancelButton: true,
                confirmButtonText: 'Yes, generate one',
                cancelButtonText: 'No',
                reverseButtons: true,
                focusCancel: true
            }).then(function (r) { return !!(r && r.isConfirmed); });
        }
        return Promise.resolve(window.confirm(title));
    }

    function nextSerial() {
        return fetch(PREVIEW_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : {}; })
            .then(function (d) { return String((d && d.next) || ''); })
            .catch(function () { return ''; });
    }

    // "001240" + 2 -> "001242": the preview for the third ticked OP on the same form.
    function offset(serial, by) {
        var n = parseInt(serial, 10);
        if (!serial || isNaN(n)) return '';
        return String(n + (by || 0)).padStart(6, '0');
    }

    function label(serial) {
        return serial
            ? 'Preview: ' + serial + ' (confirmed when you save)'
            : 'The system will assign the serial when you save.';
    }

    window.KlaesOpSerialNone = { confirm: confirmNoSerial, next: nextSerial, offset: offset, label: label };
})();

{{--
    Survey entry forms as modals (2026-10-26, user: "the form displaying above the tables should be proper modals").

    Any entry panel marked data-sv-modal="Title" is lifted into a dialog: title bar, close button,
    backdrop, Esc, scrolling body and a fixed action bar. The page keeps its own open/close code,
    which toggles the panel's "open" class, so a panel the server renders open (edit mode, or a
    validation error) opens as a modal too.

        data-sv-modal="Log Complaint"      dialog title
        data-sv-modal-size="sm|lg"         narrower / wider dialog (default md)
        data-sv-modal-close="<url>"        where closing goes instead of just hiding (edit mode)
        <button data-sv-again>             a submit button that reopens this modal after the save,
                                           for entering one row after another

    Field grouping inside a form-grid: <div class="sv-group full"><span>Heading</span></div> starts a
    group; .form-grid.cols-3 gives three columns on wide dialogs.
--}}
<style>
    .survey-proto .sv-modal { display: none; position: fixed; inset: 0; z-index: 1040; align-items: flex-start; justify-content: center; padding: 4vh 16px; }
    .survey-proto .sv-modal.open { display: flex; }
    .survey-proto .sv-modal-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .55); }
    .survey-proto .sv-modal-dialog { position: relative; background: #fff; border-radius: 12px; box-shadow: 0 24px 60px rgba(0, 0, 0, .28);
        width: min(860px, 100%); max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; }
    .survey-proto .sv-modal-dialog.sm { width: min(600px, 100%); }
    .survey-proto .sv-modal-dialog.lg { width: min(1080px, 100%); }
    .survey-proto .sv-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 22px;
        border-bottom: 1px solid var(--gray-200, #e5e7eb); flex-shrink: 0; }
    .survey-proto .sv-modal-head h4 { margin: 0; font-size: 16.5px; font-weight: 600; color: var(--gray-900, #111827); }
    .survey-proto .sv-modal-x { background: none; border: 0; font-size: 26px; line-height: 1; color: var(--gray-500, #6b7280); cursor: pointer; padding: 0 4px; }
    .survey-proto .sv-modal-x:hover { color: var(--gray-900, #111827); }
    .survey-proto .sv-modal-body { overflow-y: auto; flex: 1; min-height: 0; }

    /* The panel inside the dialog: no dashed box; its action bar sticks to the bottom. */
    .survey-proto .sv-modal [data-sv-modal],
    .survey-proto .sv-modal [data-sv-modal].open { display: block; background: #fff; border: 0; border-radius: 0; margin: 0; padding: 20px 22px 0; box-shadow: none; }
    .survey-proto .sv-modal .form-container { box-shadow: none; border: 0; margin: 0 !important; }
    .survey-proto .sv-modal .form-body { padding: 0; }
    .survey-proto .sv-modal .form-actions { position: sticky; bottom: 0; z-index: 30; background: var(--gray-50, #f9fafb);
        margin: 22px -22px 0; padding: 12px 22px; border-top: 1px solid var(--gray-200, #e5e7eb); }

    .survey-proto .sv-modal .helper-text { font-size: 12.5px; line-height: 1.5; color: var(--gray-500, #6b7280); }
    .survey-proto .sv-modal .helper-text a { color: var(--primary, #2563eb); text-decoration: underline; }
    /* The file-number selector takes the whole row, so the picked number is readable. */
    .survey-proto .sv-modal .form-grid > [data-lookup] { grid-column: 1 / -1; }
    /* Grouping and grids */
    .survey-proto .form-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .survey-proto .form-grid + .form-grid { margin-top: 22px; }
    .survey-proto .sv-group { grid-column: 1 / -1; display: flex; align-items: center; gap: 10px; margin: 6px 0 -6px;
        font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--primary, #2563eb); }
    .survey-proto .sv-group::after { content: ''; flex: 1; height: 1px; background: var(--gray-200, #e5e7eb); }
    .survey-proto .sv-group:first-child { margin-top: 0; }
    /* The address builder sits flat in the dialog like any other group; its heading matches the group headings. */
    .survey-proto .sv-modal .address-builder { background: none; border: 0; box-shadow: none; padding: 0; margin: 22px 0 0; }
    .survey-proto .sv-modal .address-builder .ab-legend { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; letter-spacing: .06em;
        text-transform: uppercase; color: var(--primary, #2563eb); background: none; padding: 0; margin: 22px 0 12px; border: 0; }
    .survey-proto .sv-modal .address-builder .ab-legend::after { content: ''; flex: 1; height: 1px; background: var(--gray-200, #e5e7eb); }
    .survey-proto .sv-group i { font-size: 13px; }
    .survey-proto .form-grid .span-2 { grid-column: span 2; }
    @media (max-width: 900px) { .survey-proto .form-grid.cols-3 { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 640px) {
        .survey-proto .form-grid.cols-3 { grid-template-columns: 1fr; }
        .survey-proto .form-grid .span-2 { grid-column: 1 / -1; }
        .survey-proto .sv-modal { padding: 0; }
        .survey-proto .sv-modal-dialog { max-height: 100vh; height: 100%; border-radius: 0; }
    }
    body.sv-modal-lock { overflow: hidden; }
</style>
<script>
(function () {
    function lift(panel) {
        if (panel.dataset.svModalReady) return;
        panel.dataset.svModalReady = '1';
        var root = panel.closest('.survey-proto') || document.body;
        var overlay = document.createElement('div');
        overlay.className = 'sv-modal';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        var titleId = 'svm-' + Math.random().toString(36).slice(2, 8);
        overlay.setAttribute('aria-labelledby', titleId);
        overlay.innerHTML = '<div class="sv-modal-backdrop"></div><div class="sv-modal-dialog ' + (panel.dataset.svModalSize || '') + '">' +
            '<div class="sv-modal-head"><h4 id="' + titleId + '"></h4><button type="button" class="sv-modal-x" aria-label="Close">&times;</button></div>' +
            '<div class="sv-modal-body"></div></div>';
        overlay.querySelector('h4').textContent = panel.dataset.svModal || 'Entry';
        overlay.querySelector('.sv-modal-body').appendChild(panel);
        root.appendChild(overlay);

        function close() {
            if (panel.dataset.svModalClose) { location.href = panel.dataset.svModalClose; return; }
            panel.classList.remove('open');
        }
        overlay.querySelector('.sv-modal-x').addEventListener('click', close);
        overlay.querySelector('.sv-modal-backdrop').addEventListener('click', close);
        overlay.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !document.querySelector('.select2-container--open')) close(); });
        // Buttons inside the form that only hide the panel ("Close") keep working: they remove .open.

        // Errors from the last save are shown at the top of the page, behind the dialog: copy them in.
        var flashed = false, loading = true;
        function showErrors() {
            if (flashed || !loading) return;
            flashed = true;
            var box = root.querySelector('[data-sv-flash="error"]');
            if (!box || panel.contains(box)) return;
            var copy = box.cloneNode(true);
            copy.style.marginBottom = '16px';
            panel.insertBefore(copy, panel.firstChild);
        }

        function sync() {
            var open = panel.classList.contains('open');
            if (open === overlay.classList.contains('open')) return;
            overlay.classList.toggle('open', open);
            document.body.classList.toggle('sv-modal-lock', !!document.querySelector('.survey-proto .sv-modal.open'));
            if (open) {
                showErrors();
                overlay.querySelector('.sv-modal-body').scrollTop = 0;
                setTimeout(function () {
                    var first = panel.querySelector('input:not([type=hidden]):not([readonly]):not([disabled]), select:not([disabled]), textarea');
                    if (first && !panel.querySelector('.is-invalid, .error')) try { first.focus({ preventScroll: true }); } catch (e) {}
                }, 30);
            }
        }
        new MutationObserver(sync).observe(panel, { attributes: true, attributeFilter: ['class'] });

        // "Save & add next": remember the panel across the save's redirect, then reopen it.
        var key = 'svModalAgain:' + location.pathname;
        panel.querySelectorAll('[data-sv-again]').forEach(function (b) {
            b.addEventListener('click', function () { try { sessionStorage.setItem(key, panel.id); } catch (e) {} });
        });
        try {
            if (panel.id && sessionStorage.getItem(key) === panel.id) {
                sessionStorage.removeItem(key);
                panel.classList.add('open');
            }
        } catch (e) {}
        sync();
        loading = false;
    }
    function init() { document.querySelectorAll('.survey-proto [data-sv-modal]').forEach(lift); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.svModal = { init: init, open: function (id) { var p = document.getElementById(id); if (p) p.classList.add('open'); } };
})();
</script>

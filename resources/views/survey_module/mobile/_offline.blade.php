{{-- SCF offline layer (2026-10-25): service worker, the on-phone queue and its status pill.
     Included once, from the mobile footer, on every signed-in page. --}}
<link rel="manifest" href="{{ route('survey-module.mobile.manifest') }}">
<style>
    .scf-pill { position: fixed; left: 50%; transform: translateX(-50%); bottom: calc(76px + env(safe-area-inset-bottom)); z-index: 60;
                background: #1e1b2e; color: #fff; font: 600 12.5px/1.2 'Outfit', system-ui, sans-serif; padding: 8px 14px; border-radius: 999px;
                box-shadow: 0 8px 24px -8px rgba(0,0,0,.45); max-width: calc(100% - 32px); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .scf-pill.off { background: #52514e; }
    .scf-pill.warn { background: #b45309; }
    .scf-note { max-width: var(--page-w, 720px); margin: 12px auto 0; padding: 10px 14px; border-radius: 12px; font-size: 14px; }
    .scf-note.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .scf-note.wait { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
</style>
<div class="scf-pill" id="scfPill" hidden role="status" aria-live="polite"></div>
<script>
    window.SCF_OFFLINE = {
        sw: @json(route('survey-module.mobile.sw')),
        scope: @json(url('/survey-module/mobile') . '/'),
        session: @json(route('survey-module.mobile.session')),
        dashboard: @json(route('survey-module.mobile.index'))
    };
    // A note after a save made through the offline layer (?scf=saved|queued|photos-waiting).
    (function () {
        var q = new URLSearchParams(location.search), kind = q.get('scf');
        if (!kind) return;
        var ref = q.get('ref'), text, cls = 'ok';
        if (kind === 'saved') text = 'Case ' + (ref || '') + ' saved.';
        else if (kind === 'photos-waiting') { text = 'Case ' + (ref || '') + ' saved. Some photos are still waiting to upload; they will go when the signal is better.'; cls = 'wait'; }
        else if (kind === 'queued') { text = 'No signal: the case is saved on this phone and will sync when you are back online.'; cls = 'wait'; }
        else return;
        document.addEventListener('DOMContentLoaded', function () {
            var n = document.createElement('div');
            n.className = 'scf-note ' + cls; n.setAttribute('role', 'status'); n.textContent = text;
            var main = document.querySelector('main') || document.body;
            main.insertBefore(n, main.firstChild);
            history.replaceState(null, '', location.pathname);
        });
    })();
</script>
<script src="{{ asset('js/survey-mobile-offline.js') }}?v={{ \App\Http\Controllers\Survey\MobileOfflineController::SW_VERSION }}"></script>

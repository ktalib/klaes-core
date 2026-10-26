{{-- Account menu, opened by the user button in the app bar (ui.menu()). --}}
<div class="menu" id="menu">
    <div class="who"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->email }}</small></div>
    <a href="{{ route('survey-module.mobile.index') }}"><i class="fas fa-house"></i> Dashboard</a>
    <a href="{{ route('survey-module.mobile.register') }}"><i class="fas fa-plus"></i> Register new case</a>
    <a href="{{ route('survey-module.mobile.observations') }}"><i class="fas fa-compass-drafting"></i> Field observations</a>
    <!-- <a href="{{ route('survey-module.compensation.cases') }}"><i class="fas fa-desktop"></i> Open desktop module</a> -->
    {{-- Signing out also clears the pages kept on the phone for offline use (they hold case details). --}}
    <form method="POST" action="{{ route('survey-module.mobile.logout') }}"
          onsubmit="return scfSignOut(this);">
        @csrf
        <button type="submit"><i class="fas fa-right-from-bracket"></i> Sign out</button>
    </form>
</div>
<script>
    window.ui = window.ui || {};
    // Clear the offline copies first, then sign out; a sign-out never waits more than 2 s on it.
    window.scfSignOut = function (form) {
        if (!window.caches || form.dataset.cleared) return true;
        var go = function () { form.dataset.cleared = '1'; form.submit(); };
        var timer = setTimeout(go, 2000);
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (n) { return n.indexOf('scf-') === 0; }).map(function (n) { return caches.delete(n); }));
        }).then(function () { clearTimeout(timer); go(); }, function () { clearTimeout(timer); go(); });
        return false;
    };
    window.ui.menu = function () { document.getElementById('menu').classList.toggle('open'); };
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest('#menu') && !ev.target.closest('[aria-label="Account menu"]')) {
            document.getElementById('menu').classList.remove('open');
        }
    });
</script>

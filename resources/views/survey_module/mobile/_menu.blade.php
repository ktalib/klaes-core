{{-- Account menu, opened by the user button in the app bar (ui.menu()). --}}
<div class="menu" id="menu">
    <div class="who"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->email }}</small></div>
    <a href="{{ route('survey-module.mobile.index') }}"><i class="fas fa-house"></i> Dashboard</a>
    <a href="{{ route('survey-module.mobile.register') }}"><i class="fas fa-plus"></i> Register new case</a>
    <!-- <a href="{{ route('survey-module.compensation.cases') }}"><i class="fas fa-desktop"></i> Open desktop module</a> -->
    <form method="POST" action="{{ route('survey-module.mobile.logout') }}">
        @csrf
        <button type="submit"><i class="fas fa-right-from-bracket"></i> Sign out</button>
    </form>
</div>
<script>
    window.ui = window.ui || {};
    window.ui.menu = function () { document.getElementById('menu').classList.toggle('open'); };
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest('#menu') && !ev.target.closest('[aria-label="Account menu"]')) {
            document.getElementById('menu').classList.remove('open');
        }
    });
</script>

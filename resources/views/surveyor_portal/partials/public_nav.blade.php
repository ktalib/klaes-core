{{-- Top bar of the public pages: the seal is the logo, the wordmark is kept for the banner (user, 2026-11-05). --}}
@php $signedIn = auth('surveyor')->check(); @endphp
<header class="ks-topbar">
    <div class="mx-auto px-4 flex items-center justify-between gap-3" style="max-width:1120px;height:68px;">
        <a href="{{ route('surveyor-portal.home') }}" class="flex items-center gap-3 min-w-0" style="text-decoration:none;color:inherit;">
            <span class="flex items-center gap-2 shrink-0" aria-label="{{ $portalSettings['brand_name'] }} and Ministry seals">
                <img src="{{ asset('assets/logo/ministry2.png') }}" alt="Kano State Ministry of Land and Physical Planning seal" width="44" height="44" style="width:44px;height:44px;border-radius:50%;object-fit:contain;box-shadow:0 1px 3px rgba(0,0,0,.18);">
                <img src="{{ asset($portalSettings['logo_seal']) }}" alt="{{ $portalSettings['brand_name'] }} seal" width="44" height="44" style="width:44px;height:44px;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.25);">
            </span>
            <span class="min-w-0">
                <span class="block font-extrabold tracking-wide" style="color:var(--ks-blue);font-size:17px;line-height:1.1;">{{ $portalSettings['brand_name'] }}</span>
                <span class="block ks-help truncate" style="font-size:12px;">{{ $portalSettings['office_name'] }}</span>
            </span>
        </a>
        <nav class="flex items-center gap-2 shrink-0">
            @if ($signedIn)
                <a class="ks-btn ks-btn-primary" style="padding:8px 14px;" href="{{ route('surveyor-portal.dashboard') }}"><i data-lucide="layout-dashboard" class="w-4 h-4"></i><span class="hidden sm:inline">My dashboard</span></a>
            @else
                <a class="ks-btn ks-btn-light" style="padding:8px 10px;" href="{{ route('surveyor-portal.map-requests.create') }}">
                    <i data-lucide="map" class="w-4 h-4"></i><span class="hidden sm:inline">Request a map</span><span class="sr-only sm:hidden">Request a map</span>
                </a>
                <a class="ks-btn ks-btn-light" style="padding:8px 12px;" href="{{ route('surveyor-portal.login') }}" data-signin-dialog-trigger>Sign in</a>
                <a class="ks-btn ks-btn-primary" style="padding:8px 12px;" href="{{ route('surveyor-portal.register') }}" data-register-dialog-trigger>Register</a>
            @endif
        </nav>
    </div>
</header>
@unless ($signedIn)
    <dialog id="ksCompanyRegistrationDialog" aria-labelledby="ksCompanyRegistrationTitle"
            style="width:min(92vw,480px);padding:0;border:0;border-radius:14px;box-shadow:0 24px 70px rgba(8,43,94,.28);">
        <div style="padding:26px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;">
                <div>
                    <h2 id="ksCompanyRegistrationTitle" class="text-lg font-bold">Is your surveying company registered?</h2>
                    <p class="ks-help" style="margin-top:7px;">Choose whether your company is already registered and approved.</p>
                </div>
                <button type="button" class="ks-btn ks-btn-light" style="padding:6px 10px;" aria-label="Close" data-register-dialog-close>&times;</button>
            </div>
            <div class="grid sm:grid-cols-2 gap-3" style="margin-top:22px;">
                <a class="ks-btn ks-btn-primary" href="{{ route('surveyor-portal.register', ['company' => 'yes']) }}">Yes, register</a>
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.register', ['company' => 'no']) }}">No, register a company</a>
            </div>
        </div>
    </dialog>
    {{-- Sign in asks first (user, 2026-11-11): company or surveyor, each with its own sign-in URL. --}}
    <dialog id="ksSignInDialog" aria-labelledby="ksSignInTitle"
            style="width:min(92vw,480px);padding:0;border:0;border-radius:14px;box-shadow:0 24px 70px rgba(8,43,94,.28);">
        <div style="padding:26px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;">
                <div>
                    <h2 id="ksSignInTitle" class="text-lg font-bold">Sign in as</h2>
                    <p class="ks-help" style="margin-top:7px;">Are you signing in as a company or as a surveyor?</p>
                </div>
                <button type="button" class="ks-btn ks-btn-light" style="padding:6px 10px;" aria-label="Close" data-signin-dialog-close>&times;</button>
            </div>
            <div class="grid sm:grid-cols-2 gap-3" style="margin-top:22px;">
                <a class="ks-btn ks-btn-primary" href="{{ route('surveyor-portal.login.as', 'surveyor') }}"><i data-lucide="user-round" class="w-4 h-4"></i> Surveyor</a>
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.login.as', 'company') }}"><i data-lucide="building-2" class="w-4 h-4"></i> Company</a>
            </div>
        </div>
    </dialog>
    <style>
        #ksCompanyRegistrationDialog::backdrop, #ksSignInDialog::backdrop { background:rgba(8,23,43,.56); backdrop-filter:blur(3px); }
        #ksCompanyRegistrationDialog[open], #ksSignInDialog[open] { animation:ks-dialog-in .16s ease-out; }
        @keyframes ks-dialog-in { from { opacity:0; transform:translateY(8px) scale(.98); } to { opacity:1; transform:translateY(0) scale(1); } }
        @media (prefers-reduced-motion:reduce) { #ksCompanyRegistrationDialog[open], #ksSignInDialog[open] { animation:none; } }
    </style>
    <script>
        (function () {
            var dialog = document.getElementById('ksSignInDialog');
            if (!dialog || typeof dialog.showModal !== 'function') return;   // no <dialog>: the link opens /login, which asks the same

            document.addEventListener('click', function (event) {
                var trigger = event.target instanceof Element ? event.target.closest('[data-signin-dialog-trigger]') : null;
                if (!trigger) return;
                event.preventDefault();
                dialog.showModal();
            });
            dialog.querySelector('[data-signin-dialog-close]').addEventListener('click', function () { dialog.close(); });
            dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
        })();
    </script>
    <script>
        (function () {
            var dialog = document.getElementById('ksCompanyRegistrationDialog');
            if (!dialog || typeof dialog.showModal !== 'function') return;

            document.addEventListener('click', function (event) {
                var trigger = event.target instanceof Element
                    ? event.target.closest('[data-register-dialog-trigger]')
                    : null;
                if (!trigger) return;
                event.preventDefault();
                dialog.showModal();
            });
            dialog.querySelector('[data-register-dialog-close]').addEventListener('click', function () {
                dialog.close();
            });
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) dialog.close();
            });
        })();
    </script>
@endunless

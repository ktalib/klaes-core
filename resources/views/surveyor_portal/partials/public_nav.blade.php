{{-- Top bar of the public pages: the seal is the logo, the wordmark is kept for the banner (user, 2026-11-05). --}}
@php $signedIn = auth('surveyor')->check(); @endphp
<header class="ks-topbar">
    <div class="mx-auto px-4 flex items-center justify-between gap-3" style="max-width:1120px;height:68px;">
        <a href="{{ route('surveyor-portal.home') }}" class="flex items-center gap-3 min-w-0" style="text-decoration:none;color:inherit;">
            <img src="{{ asset('assets/logo/kssgof-logo.png') }}" alt="" width="44" height="44" style="width:44px;height:44px;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.25);">
            <span class="min-w-0">
                <span class="block font-extrabold tracking-wide" style="color:var(--ks-blue);font-size:17px;line-height:1.1;">KSSGOF</span>
                <span class="block ks-help truncate" style="font-size:12px;">Surveyor General's Office Portal</span>
            </span>
        </a>
        <nav class="flex items-center gap-2 shrink-0">
            @if ($signedIn)
                <a class="ks-btn ks-btn-primary" style="padding:8px 14px;" href="{{ route('surveyor-portal.dashboard') }}"><i data-lucide="layout-dashboard" class="w-4 h-4"></i><span class="hidden sm:inline">My dashboard</span></a>
            @else
                <a class="ks-btn ks-btn-light" style="padding:8px 12px;" href="{{ route('surveyor-portal.login') }}">Sign in</a>
                <a class="ks-btn ks-btn-primary" style="padding:8px 12px;" href="{{ route('surveyor-portal.register') }}">Register</a>
            @endif
        </nav>
    </div>
</header>

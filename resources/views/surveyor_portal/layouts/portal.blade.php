@extends('surveyor_portal.layouts.app')

{{-- Signed-in shell: a sidebar on every screen size; on phones it slides in from the menu button (user, 2026-11-08). --}}
@section('body')
@php
    $me = auth('surveyor')->user();
    $unread = \App\Models\Survey\SurveyorPortalNotification::unreadFor($me)->count();   // a company counts its surveyors' too
    $nameWords = preg_split('/\s+/', trim((string) $me->name)) ?: [];
    // The security questions card: forced until answers are set, or opened from the sidebar to change them.
    $sqcService = app(\App\Services\Survey\SurveyorSecurityQuestions::class);
    $sqcForced = $sqcService->owesAnswers(request(), $me);
    $sqcOpen = $sqcForced || (session('open_security_questions_card') && $sqcService->enabled());
    $initials = mb_strtoupper(mb_substr($nameWords[0] ?? '', 0, 1) . mb_substr(count($nameWords) > 1 ? end($nameWords) : '', 0, 1)) ?: '?';
    // A company's own login (user, 2026-11-10) has its own sidebar.
    $isCompany = $me->isCompany();
    $meRef = $isCompany ? 'Company account · ' . $me->username : $me->surveyor_id_no;
@endphp
@push('styles')
    <style>
        .ks-shell { display:flex; min-height:100vh; }
        .ks-portal-sidebar { position:sticky; top:0; z-index:40; display:flex; flex-direction:column; flex:0 0 268px; width:268px; height:100vh; overflow-y:auto; color:#fff;
            background:linear-gradient(180deg,var(--ks-blue-dark) 0%,var(--ks-blue) 100%); box-shadow:1px 0 0 rgba(0,0,0,.06); }
        .ks-portal-sidebar::after { position:absolute; left:0; right:0; bottom:0; height:4px; background:linear-gradient(90deg,var(--ks-blue) 0 33.3%,var(--ks-green) 33.3% 66.6%,var(--ks-gold) 66.6% 100%); content:""; }
        .ks-side-brand { display:flex; align-items:center; gap:11px; padding:20px 18px 18px; color:#fff; text-decoration:none; }
        /* Side by side, not overlapped: the overlap hid half of the Ministry seal (user, 2026-11-09). */
        .ks-side-seals { display:flex; flex:0 0 auto; gap:6px; }
        .ks-side-brand img { flex:0 0 42px; width:42px; height:42px; border-radius:50%; background:#fff; object-fit:contain; padding:1px; box-shadow:0 0 0 2px rgba(255,255,255,.25); }
        .ks-side-brand-name { display:block; font-size:16px; font-weight:800; letter-spacing:.04em; line-height:1.1; }
        .ks-side-brand-office { display:block; margin-top:3px; color:rgba(255,255,255,.72); font-size:11px; line-height:1.3; }
        .ks-side-close { display:none; margin-left:auto; padding:6px; border:0; border-radius:8px; background:rgba(255,255,255,.1); color:#fff; cursor:pointer; }
        .ks-sidebar-profile { display:flex; align-items:center; gap:11px; margin:0 14px 18px; padding:12px; border:1px solid rgba(255,255,255,.14); border-radius:12px; background:rgba(255,255,255,.07); }
        .ks-sidebar-avatar { display:grid; place-items:center; flex:0 0 40px; width:40px; height:40px; border-radius:11px; background:var(--ks-gold); color:#1B1405; font-size:15px; font-weight:800; letter-spacing:.02em; }
        .ks-sidebar-profile-company { overflow:hidden; color:#fff; font-size:13px; font-weight:750; text-overflow:ellipsis; white-space:nowrap; }
        .ks-sidebar-profile-name { overflow:hidden; color:rgba(255,255,255,.86); font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
        .ks-sidebar-profile-id { margin-top:2px; color:rgba(255,255,255,.6); font-size:11px; font-variant-numeric:tabular-nums; }
        .ks-sidebar-label { margin:0 0 8px 26px; color:rgba(255,255,255,.55); font-size:10px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .ks-sidebar-nav { display:flex; flex-direction:column; gap:3px; padding:0 12px; }
        .ks-sidebar-nav a { position:relative; display:flex; align-items:center; gap:12px; min-height:44px; padding:10px 14px; border-radius:10px; color:rgba(255,255,255,.8); font-size:14px; font-weight:600; text-decoration:none; transition:background .15s ease,color .15s ease; }
        .ks-sidebar-nav a svg { flex:0 0 auto; color:rgba(255,255,255,.65); }
        .ks-sidebar-nav a:hover { background:rgba(255,255,255,.08); color:#fff; }
        .ks-sidebar-nav a:focus-visible { outline:2px solid var(--ks-gold); outline-offset:1px; }
        .ks-sidebar-nav a.on { background:#fff; color:var(--ks-blue); box-shadow:0 6px 16px rgba(0,0,0,.14); }
        .ks-sidebar-nav a.on svg { color:var(--ks-blue); }
        .ks-sidebar-nav a.on::before { position:absolute; top:10px; bottom:10px; left:0; width:3px; border-radius:0 3px 3px 0; background:var(--ks-gold); content:""; }
        .ks-sidebar-nav .ks-badge { margin-left:auto; padding:1px 8px; background:var(--ks-gold); color:#1B1405; }
        .ks-side-group { display:flex; align-items:center; gap:12px; min-height:40px; padding:10px 14px 4px; color:rgba(255,255,255,.8); font-size:14px; font-weight:600; }
        .ks-side-group svg { color:rgba(255,255,255,.65); }
        .ks-side-group.in { color:#fff; }
        .ks-side-group.in svg { color:var(--ks-gold); }
        .ks-side-sub { display:flex; flex-direction:column; gap:2px; margin:0 0 4px 22px; padding-left:10px; border-left:1px solid rgba(255,255,255,.18); }
        .ks-sidebar-nav .ks-side-sub a { min-height:38px; padding:8px 12px; font-size:13px; }
        .ks-side-foot { margin-top:auto; padding:16px 14px 22px; }
        .ks-side-signout { width:100%; justify-content:flex-start; padding:10px 14px; border:1px solid rgba(255,255,255,.18); background:transparent; color:#fff; }
        .ks-side-signout:hover { background:rgba(255,255,255,.08); }
        .ks-shell-main { display:flex; flex-direction:column; flex:1 1 auto; min-width:0; }
        .ks-shell-top { position:sticky; top:0; z-index:30; display:flex; align-items:center; gap:12px; min-height:64px; padding:10px 28px; border-bottom:1px solid var(--ks-line); background:rgba(255,255,255,.94); backdrop-filter:blur(6px); }
        .ks-shell-menu { display:none; padding:8px; border:1px solid var(--ks-line); border-radius:9px; background:#fff; color:var(--ks-ink); cursor:pointer; }
        .ks-shell-title { min-width:0; margin:0; overflow:hidden; color:var(--ks-ink); font-size:19px; font-weight:800; text-overflow:ellipsis; white-space:nowrap; }
        .ks-shell-user { display:flex; align-items:center; gap:10px; margin-left:auto; }
        .ks-shell-user-avatar { display:grid; place-items:center; width:36px; height:36px; border-radius:50%; background:#E8EDF5; color:var(--ks-blue); font-size:13px; font-weight:800; }
        .ks-shell-user-name { font-size:13px; font-weight:700; line-height:1.2; }
        .ks-shell-user-id { color:var(--ks-soft); font-size:11px; }
        .ks-portal-content { width:100%; min-width:0; padding:24px 28px 40px; }
        .ks-shell-scrim { position:fixed; inset:0; z-index:35; background:rgba(8,23,43,.5); backdrop-filter:blur(2px); }
        @media (max-width:1023px) {
            .ks-portal-sidebar { position:fixed; top:0; left:0; bottom:0; width:min(84vw,290px); transform:translateX(-100%); transition:transform .22s ease; box-shadow:none; }
            .ks-shell.nav-open .ks-portal-sidebar { transform:translateX(0); box-shadow:0 20px 60px rgba(8,23,43,.4); }
            .ks-side-close, .ks-shell-menu { display:inline-flex; }
            .ks-shell-top { padding:10px 16px; }
            .ks-portal-content { padding:18px 16px 32px; }
        }
        @media (max-width:640px) {
            .ks-shell-user-text { display:none; }
            .ks-shell-title { font-size:17px; }
        }
        @media (prefers-reduced-motion:reduce) { .ks-portal-sidebar { transition:none; } }
    </style>
@endpush
<div class="ks-shell" id="ksShell">
    <div class="ks-shell-scrim" data-ks-nav-close hidden></div>
    <aside class="ks-portal-sidebar" id="ksPortalSidebar" aria-label="Portal navigation">
        <div class="flex items-center">
            <a href="{{ route('surveyor-portal.dashboard') }}" class="ks-side-brand min-w-0">
                <span class="ks-side-seals" aria-label="{{ $portalSettings['brand_name'] }} and Ministry seals">
                    <img src="{{ asset('assets/logo/ministry2.png') }}" alt="Kano State Ministry of Land and Physical Planning seal" width="42" height="42">
                    <img src="{{ asset($portalSettings['logo_seal']) }}" alt="{{ $portalSettings['brand_name'] }} seal" width="42" height="42">
                </span>
                <span class="min-w-0">
                    <span class="ks-side-brand-name">{{ $portalSettings['brand_name'] }}</span>
                    <span class="ks-side-brand-office">{{ $portalSettings['office_name'] }}</span>
                </span>
            </a>
            <button type="button" class="ks-side-close" style="margin-right:14px;" aria-label="Close menu" data-ks-nav-close><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="ks-sidebar-profile">
            <div class="ks-sidebar-avatar" aria-hidden="true">{{ $initials }}</div>
            <div class="min-w-0">
                <div class="ks-sidebar-profile-company">{{ $me->company_name ?: '—' }}</div>
                @if ($isCompany)
                    <div class="ks-sidebar-profile-name">Company account</div>
                    <div class="ks-sidebar-profile-id">{{ $me->username }}</div>
                @else
                    <div class="ks-sidebar-profile-name">Surveyor {{ $me->name }}</div>
                    <div class="ks-sidebar-profile-id">{{ $me->surveyor_id_no }}</div>
                @endif
            </div>
        </div>
        <div class="ks-sidebar-label">Workspace</div>
        <nav class="ks-sidebar-nav">
            <a class="{{ request()->routeIs('surveyor-portal.dashboard') ? 'on' : '' }}" href="{{ route('surveyor-portal.dashboard') }}" @if (request()->routeIs('surveyor-portal.dashboard')) aria-current="page" @endif><i data-lucide="layout-dashboard" class="w-[18px] h-[18px]"></i> Dashboard</a>
            @if ($isCompany)
            {{-- The company's sidebar (user, 2026-11-10/12): Dashboard, Our Surveyors, Our Beacons (Issued Beacons, Used Beacons, Surveyors Report), Uploaded Survey Plans (Original, Signed), Notifications. --}}
            <a class="{{ request()->routeIs('surveyor-portal.company.surveyors') ? 'on' : '' }}" href="{{ route('surveyor-portal.company.surveyors') }}" @if (request()->routeIs('surveyor-portal.company.surveyors')) aria-current="page" @endif><i data-lucide="users" class="w-[18px] h-[18px]"></i> Our Surveyors</a>
            @php
                $ourBeaconPages = [
                    [request()->routeIs('surveyor-portal.company.beacons') && request()->route('which') === 'issued', route('surveyor-portal.company.beacons', 'issued'), 'radio-tower', 'Issued Beacons'],
                    [request()->routeIs('surveyor-portal.company.beacons') && request()->route('which') === 'used', route('surveyor-portal.company.beacons', 'used'), 'list-checks', 'Used Beacons'],
                    [request()->routeIs('surveyor-portal.company.reports*'), route('surveyor-portal.company.reports'), 'clipboard-list', 'Surveyors Report'],
                ];
                $inOurBeacons = request()->routeIs('surveyor-portal.company.beacons', 'surveyor-portal.company.reports*');
            @endphp
            <div class="ks-side-group {{ $inOurBeacons ? 'in' : '' }}"><i data-lucide="map-pin" class="w-[18px] h-[18px]"></i> Our Beacons</div>
            <div class="ks-side-sub">
                @foreach ($ourBeaconPages as [$here, $url, $icon, $label])
                    <a class="{{ $here ? 'on' : '' }}" href="{{ $url }}" @if ($here) aria-current="page" @endif><i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}</a>
                @endforeach
            </div>
            {{-- Uploaded Survey Plans > Original / Signed (user, 2026-11-12). --}}
            @php
                $coPlanPages = [
                    [request()->routeIs('surveyor-portal.company.plans') && request()->route('which') === 'original', route('surveyor-portal.company.plans', 'original'), 'file-text', 'Original'],
                    [request()->routeIs('surveyor-portal.company.plans') && request()->route('which') === 'signed', route('surveyor-portal.company.plans', 'signed'), 'file-check-2', 'Signed'],
                ];
            @endphp
            <div class="ks-side-group {{ request()->routeIs('surveyor-portal.company.plans') ? 'in' : '' }}"><i data-lucide="upload" class="w-[18px] h-[18px]"></i> Uploaded Survey Plans</div>
            <div class="ks-side-sub">
                @foreach ($coPlanPages as [$here, $url, $icon, $label])
                    <a class="{{ $here ? 'on' : '' }}" href="{{ $url }}" @if ($here) aria-current="page" @endif><i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}</a>
                @endforeach
            </div>
            @else
            {{-- Requested Beacons and its three pages as sidebar items, not tabs (user, 2026-11-09). --}}
            @php
                $beaconPages = [
                    ['surveyor-portal.requests.*', route('surveyor-portal.requests.create'), 'file-plus-2', 'Beacon Request'],
                    ['surveyor-portal.beacon-register', route('surveyor-portal.beacon-register'), 'list-checks', 'Beacon Register'],
                    ['surveyor-portal.reports.*', route('surveyor-portal.reports.index'), 'clipboard-list', "Surveyor's Report"],
                ];
                $inBeacons = request()->routeIs('surveyor-portal.requests.*', 'surveyor-portal.beacon-register', 'surveyor-portal.reports.*');
            @endphp
            <div class="ks-side-group {{ $inBeacons ? 'in' : '' }}"><i data-lucide="radio-tower" class="w-[18px] h-[18px]"></i> Requested Beacons</div>
            <div class="ks-side-sub">
                @foreach ($beaconPages as [$pattern, $url, $icon, $label])
                    @php $here = request()->routeIs($pattern); @endphp
                    <a class="{{ $here ? 'on' : '' }}" href="{{ $url }}" @if ($here) aria-current="page" @endif><i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}</a>
                @endforeach
            </div>
            {{-- Survey Plan Upload (Survey Update 5g, 2026-11-10) > Original (the upload form and every upload) / Signed (user, 2026-11-12). --}}
            @php
                $onSigned = request()->routeIs('surveyor-portal.survey-plans.signed');
                $onOriginal = request()->routeIs('surveyor-portal.survey-plans.*') && ! $onSigned;
            @endphp
            <div class="ks-side-group {{ $onSigned || $onOriginal ? 'in' : '' }}"><i data-lucide="upload" class="w-[18px] h-[18px]"></i> Survey Plan Upload</div>
            <div class="ks-side-sub">
                <a class="{{ $onOriginal ? 'on' : '' }}" href="{{ route('surveyor-portal.survey-plans.index') }}" @if ($onOriginal) aria-current="page" @endif><i data-lucide="file-text" class="w-4 h-4"></i> Original</a>
                <a class="{{ $onSigned ? 'on' : '' }}" href="{{ route('surveyor-portal.survey-plans.signed') }}" @if ($onSigned) aria-current="page" @endif><i data-lucide="file-check-2" class="w-4 h-4"></i> Signed</a>
            </div>
            @endif
            <a class="{{ request()->routeIs('surveyor-portal.notifications') ? 'on' : '' }}" href="{{ route('surveyor-portal.notifications') }}" @if (request()->routeIs('surveyor-portal.notifications')) aria-current="page" @endif><i data-lucide="bell" class="w-[18px] h-[18px]"></i><span>Notifications</span> @if ($unread)<span class="ks-badge issued">{{ $unread }}</span>@endif</a>
        </nav>
        <div class="ks-side-foot">
            <a href="{{ route('surveyor-portal.password.edit') }}" class="ks-btn ks-side-signout" style="margin-bottom:8px;"><i data-lucide="key-round" class="w-4 h-4"></i> Change Password</a>
            {{-- Change the sign-in fallback answers (user, 2026-11-10); the card asks for the password first. --}}
            @if ($sqcService->enabled())
                <a href="{{ route('surveyor-portal.security-questions.edit') }}" class="ks-btn ks-side-signout" style="margin-bottom:8px;"><i data-lucide="shield-question" class="w-4 h-4"></i> Security Questions</a>
            @endif
            <form method="POST" action="{{ route('surveyor-portal.logout') }}">
                @csrf
                <button class="ks-btn ks-side-signout"><i data-lucide="log-out" class="w-4 h-4"></i> Sign out</button>
            </form>
        </div>
    </aside>

    <div class="ks-shell-main">
        <header class="ks-shell-top">
            <button type="button" class="ks-shell-menu" id="ksNavToggle" aria-label="Open menu" aria-controls="ksPortalSidebar" aria-expanded="false"><i data-lucide="menu" class="w-5 h-5"></i></button>
            <h1 class="ks-shell-title">@yield('heading')</h1>
            <div class="ks-shell-user">
                <div class="ks-shell-user-avatar" aria-hidden="true">{{ $initials }}</div>
                <div class="ks-shell-user-text">
                    <div class="ks-shell-user-name">{{ $me->holder_name }}</div>
                    <div class="ks-shell-user-id">{{ $meRef }}@if ($me->holder_name !== $me->name) · {{ $me->name }}@endif</div>
                </div>
            </div>
        </header>
        {{-- NIS / SURCON flash banners (CR 11112026-2, item 11). --}}
        @include('surveyor_portal.partials.flash_banners')
        <main class="ks-portal-content">
            @if (session('status'))<div class="ks-flash ok">{{ session('status') }}</div>@endif
            @if ($errors->any())
                <div class="ks-flash bad">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
            @yield('main')
            @if ($sqcOpen)
                @include('surveyor_portal.partials.security_questions_card', ['sqcForced' => $sqcForced])
            @endif
            <footer class="ks-help" style="margin-top:28px;padding-top:14px;border-top:1px solid var(--ks-line);text-align:center;font-size:12px;">
                {{ $portalSettings['portal_name'] }} · {{ $portalSettings['ministry_line'] }}<br>{{ $portalSettings['ministry_address'] }}
                @include('partials.vmal_credit', ['dark' => false])
            </footer>
        </main>
    </div>
</div>
<script>
    (function () {
        var shell = document.getElementById('ksShell'), toggle = document.getElementById('ksNavToggle'),
            scrim = shell.querySelector('.ks-shell-scrim'), sidebar = document.getElementById('ksPortalSidebar');
        var set = function (open) {
            shell.classList.toggle('nav-open', open);
            scrim.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open ? 'hidden' : '';
            if (open) { var first = sidebar.querySelector('.ks-sidebar-nav a'); if (first) first.focus(); } else { toggle.focus(); }
        };
        toggle.addEventListener('click', function () { set(!shell.classList.contains('nav-open')); });
        shell.querySelectorAll('[data-ks-nav-close]').forEach(function (el) { el.addEventListener('click', function () { set(false); }); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && shell.classList.contains('nav-open')) set(false); });
        // Back to a wide screen: the drawer state no longer applies.
        window.matchMedia('(min-width:1024px)').addEventListener('change', function (mq) {
            if (mq.matches && shell.classList.contains('nav-open')) { shell.classList.remove('nav-open'); scrim.hidden = true; document.body.style.overflow = ''; toggle.setAttribute('aria-expanded', 'false'); }
        });
    })();
</script>
@endsection

@extends('surveyor_portal.layouts.portal')

@section('title', 'Dashboard — KNSSGOF Portal')
@section('heading', 'Dashboard')

@push('styles')
    <style>
        .ks-dash-hero { position:relative; overflow:hidden; padding:28px; border-radius:16px; color:#fff; background:linear-gradient(125deg,var(--ks-blue-dark),var(--ks-blue) 70%,#2c6e9a); box-shadow:0 12px 30px rgba(18,47,83,.16); }
        .ks-dash-hero::after { position:absolute; top:-86px; right:-48px; width:260px; height:260px; border:1px solid rgba(255,255,255,.13); border-radius:50%; box-shadow:0 0 0 28px rgba(255,255,255,.035),0 0 0 58px rgba(255,255,255,.025); content:""; }
        .ks-dash-hero-copy { position:relative; z-index:1; max-width:620px; }
        .ks-dash-eyebrow { color:rgba(255,255,255,.75); font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
        .ks-dash-hero h2 { margin:7px 0 8px; color:#fff; font-size:clamp(23px,3vw,32px); font-weight:800; line-height:1.15; }
        .ks-dash-hero p { margin:0; color:rgba(255,255,255,.84); font-size:14px; line-height:1.6; }
        .ks-dash-hero-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:20px; }
        .ks-dash-hero .ks-btn-light { border-color:rgba(255,255,255,.55); }
        .ks-dash-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin:18px 0 24px; }
        .ks-dash-stat { display:flex; align-items:center; gap:14px; min-height:104px; padding:17px; box-shadow:0 5px 18px rgba(20,23,26,.035); }
        .ks-dash-stat-icon { display:grid; flex:0 0 44px; width:44px; height:44px; place-items:center; border-radius:13px; }
        .ks-dash-stat-icon.waiting { background:#FFF5D9; color:#8A5A00; }
        .ks-dash-stat-icon.ready { background:#E7F0FF; color:#1D4F91; }
        .ks-dash-stat-icon.accepted { background:#E4F6EC; color:#167044; }
        .ks-dash-stat-value { color:var(--ks-ink); font-size:27px; font-weight:800; line-height:1.1; }
        .ks-dash-stat-label { margin-top:5px; color:var(--ks-soft); font-size:12px; font-weight:650; }
        .ks-dash-section-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0 0 12px; }
        .ks-dash-section-heading h2 { margin:0; font-size:17px; font-weight:800; }
        .ks-dash-shortcuts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:24px; }
        .ks-dash-shortcut { display:flex; align-items:center; gap:14px; padding:17px; color:inherit; text-decoration:none; transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease; }
        .ks-dash-shortcut:hover { transform:translateY(-2px); border-color:#B8C6D9; box-shadow:0 9px 22px rgba(20,23,26,.07); }
        .ks-dash-shortcut-icon { display:grid; flex:0 0 42px; width:42px; height:42px; place-items:center; border-radius:12px; background:#EEF2F8; color:var(--ks-blue); }
        .ks-dash-shortcut-title { font-size:14px; font-weight:750; }
        .ks-dash-shortcut-copy { margin-top:3px; color:var(--ks-soft); font-size:12px; line-height:1.45; }
        .ks-dash-shortcut-arrow { margin-left:auto; color:#7A838C; }
        .ks-dash-latest { overflow:hidden; }
        @media (max-width:640px) {
            .ks-dash-hero { padding:21px; }
            .ks-dash-stats { grid-template-columns:1fr; gap:9px; margin:13px 0 20px; }
            .ks-dash-stat { min-height:78px; padding:13px; }
            .ks-dash-shortcuts { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('main')
    @php
        $me = auth('surveyor')->user();
        $firstName = preg_split('/\s+/', trim((string) $me->name))[0] ?? $me->name;
    @endphp
    <section class="ks-dash-hero" aria-labelledby="dashboardWelcome">
        <div class="ks-dash-hero-copy">
            <div class="ks-dash-eyebrow">Surveyor workspace</div>
            <h2 id="dashboardWelcome">Welcome back, {{ $firstName }}</h2>
            <p>Manage your beacon requests, keep track of issued numbers, and review updates from the Surveyor General’s office.</p>
            <div class="ks-dash-hero-actions">
                <a class="ks-btn" href="{{ route('surveyor-portal.requests.create', ['tab' => 'beacons']) }}" style="background:var(--ks-gold);color:#1B1405;">
                    <i data-lucide="radio-tower" class="w-4 h-4"></i> View my Beacons
                </a>
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.requests.create', ['tab' => 'request', 'request_type_dialog' => 1]) }}">
                    <i data-lucide="plus" class="w-4 h-4"></i> Beacon Request
                </a>
            </div>
        </div>
    </section>

    <section class="ks-dash-stats" aria-label="Beacon request summary">
        <article class="ks-card ks-dash-stat">
            <span class="ks-dash-stat-icon waiting"><i data-lucide="clock-3" class="w-5 h-5"></i></span>
            <div><div class="ks-dash-stat-value">{{ number_format($stats['submitted']) }}</div><div class="ks-dash-stat-label">Waiting for the SG</div></div>
        </article>
        <article class="ks-card ks-dash-stat">
            <span class="ks-dash-stat-icon ready"><i data-lucide="package-check" class="w-5 h-5"></i></span>
            <div><div class="ks-dash-stat-value">{{ number_format($stats['ready']) }}</div><div class="ks-dash-stat-label">Ready to Accept</div></div>
        </article>
        <article class="ks-card ks-dash-stat">
            <span class="ks-dash-stat-icon accepted"><i data-lucide="badge-check" class="w-5 h-5"></i></span>
            <div><div class="ks-dash-stat-value">{{ number_format($stats['accepted']) }}</div><div class="ks-dash-stat-label">Accepted Requests</div></div>
        </article>
    </section>

    <section aria-labelledby="dashboardShortcuts">
        <div class="ks-dash-section-heading"><h2 id="dashboardShortcuts">Quick access</h2></div>
        <div class="ks-dash-shortcuts">
            <a class="ks-card ks-dash-shortcut" href="{{ route('surveyor-portal.requests.create', ['tab' => 'beacons']) }}">
                <span class="ks-dash-shortcut-icon"><i data-lucide="radio-tower" class="w-5 h-5"></i></span>
                <span><span class="ks-dash-shortcut-title">My Beacons</span><span class="ks-dash-shortcut-copy block">View issued beacon numbers and update their usage details.</span></span>
                <i data-lucide="arrow-up-right" class="ks-dash-shortcut-arrow w-4 h-4"></i>
            </a>
            <a class="ks-card ks-dash-shortcut" href="{{ route('surveyor-portal.requests.create', ['tab' => 'request', 'request_type_dialog' => 1]) }}">
                <span class="ks-dash-shortcut-icon"><i data-lucide="file-plus-2" class="w-5 h-5"></i></span>
                <span><span class="ks-dash-shortcut-title">Beacon Request</span><span class="ks-dash-shortcut-copy block">Start a Single or Bulk request and attach SURCON payment evidence.</span></span>
                <i data-lucide="arrow-up-right" class="ks-dash-shortcut-arrow w-4 h-4"></i>
            </a>
        </div>
    </section>

    <section aria-labelledby="latestRequestsHeading">
        <div class="ks-dash-section-heading">
            <div><h2 id="latestRequestsHeading">Latest requests</h2><p class="ks-help" style="margin:4px 0 0;">Your five most recent beacon requests.</p></div>
            <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.requests.index') }}">View all requests <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
        </div>
        <div class="ks-dash-latest">
            @include('surveyor_portal.requests._table', ['rows' => $latest, 'first' => 1])
        </div>
    </section>
@endsection

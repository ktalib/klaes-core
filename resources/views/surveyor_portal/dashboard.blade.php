@extends('surveyor_portal.layouts.portal')

@section('title', 'Dashboard — MLPP-SG Portal')
@section('heading', 'Dashboard')

@push('styles')
    <style>
        .ks-dash-hero { position:relative; display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:20px; overflow:hidden; padding:30px; border-radius:18px; color:#fff;
            background:linear-gradient(125deg,var(--ks-blue-dark),var(--ks-blue) 68%,#2c6e9a); box-shadow:0 16px 34px -14px rgba(18,47,83,.45); }
        /* A faint survey grid, fading out to the left. */
        .ks-dash-hero::before { position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.07) 1px,transparent 1px);
            background-size:32px 32px; -webkit-mask-image:linear-gradient(90deg,transparent 35%,#000); mask-image:linear-gradient(90deg,transparent 35%,#000); pointer-events:none; content:""; }
        .ks-dash-hero::after { position:absolute; left:0; right:0; bottom:0; height:4px; background:linear-gradient(90deg,var(--ks-blue) 0 33.3%,var(--ks-green) 33.3% 66.6%,var(--ks-gold) 66.6% 100%); content:""; }
        .ks-dash-hero-copy { position:relative; z-index:1; max-width:620px; }
        .ks-dash-eyebrow { color:rgba(255,255,255,.75); font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
        .ks-dash-hero h2 { margin:8px 0 8px; color:#fff; font-size:clamp(24px,3vw,32px); font-weight:800; letter-spacing:-.01em; line-height:1.15; }
        .ks-dash-hero p { margin:0; color:rgba(255,255,255,.85); font-size:14px; line-height:1.6; }
        .ks-dash-hero-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:22px; }
        .ks-dash-hero-actions .ks-btn { min-height:44px; }
        .ks-dash-hero .ks-btn-light { border-color:rgba(255,255,255,.55); }
        .ks-dash-id { position:relative; z-index:1; display:grid; gap:8px; min-width:220px; max-width:100%; padding:14px 16px; border:1px solid rgba(255,255,255,.2); border-radius:14px; background:rgba(255,255,255,.1); backdrop-filter:blur(4px); }
        .ks-dash-id div { display:flex; align-items:center; gap:9px; min-width:0; font-size:13px; }
        .ks-dash-id svg { flex:0 0 auto; color:var(--ks-gold); }
        .ks-dash-id span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ks-dash-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; margin:20px 0 26px; }
        .ks-dash-stat { position:relative; display:flex; align-items:center; gap:16px; min-height:108px; padding:18px 20px; overflow:hidden; box-shadow:0 5px 18px rgba(20,23,26,.04); }
        .ks-dash-stat::after { position:absolute; left:0; top:0; bottom:0; width:4px; content:""; }
        .ks-dash-stat.waiting::after { background:#E0A100; }
        .ks-dash-stat.ready::after { background:#2F6FD0; }
        .ks-dash-stat.accepted::after { background:#1F8A55; }
        .ks-dash-stat-icon { display:grid; flex:0 0 48px; width:48px; height:48px; place-items:center; border-radius:14px; }
        .ks-dash-stat.waiting .ks-dash-stat-icon { background:#FFF5D9; color:#8A5A00; }
        .ks-dash-stat.ready .ks-dash-stat-icon { background:#E7F0FF; color:#1D4F91; }
        .ks-dash-stat.accepted .ks-dash-stat-icon { background:#E4F6EC; color:#167044; }
        .ks-dash-stat-value { color:var(--ks-ink); font-size:30px; font-weight:800; line-height:1.05; font-variant-numeric:tabular-nums; }
        .ks-dash-stat-label { margin-top:6px; color:var(--ks-soft); font-size:13px; font-weight:650; }
        .ks-dash-section-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin:0 0 12px; }
        .ks-dash-section-heading h2 { margin:0; font-size:17px; font-weight:800; }
        .ks-dash-shortcuts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:26px; }
        .ks-dash-shortcut { display:flex; align-items:center; gap:14px; padding:18px; color:inherit; text-decoration:none; transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease; }
        .ks-dash-shortcut:hover { transform:translateY(-2px); border-color:#B8C6D9; box-shadow:0 10px 24px rgba(20,23,26,.08); }
        .ks-dash-shortcut:focus-visible { outline:2px solid var(--ks-gold); outline-offset:2px; }
        .ks-dash-shortcut-icon { display:grid; flex:0 0 46px; width:46px; height:46px; place-items:center; border-radius:13px; background:#EEF2F8; color:var(--ks-blue); transition:background .15s ease,color .15s ease; }
        .ks-dash-shortcut:hover .ks-dash-shortcut-icon { background:var(--ks-blue); color:#fff; }
        .ks-dash-shortcut-title { font-size:15px; font-weight:750; }
        .ks-dash-shortcut-copy { margin-top:3px; color:var(--ks-soft); font-size:12.5px; line-height:1.45; }
        .ks-dash-shortcut-arrow { flex:0 0 auto; margin-left:auto; color:#7A838C; }
        .ks-dash-latest { overflow:hidden; }
        .ks-dash-latest-head { margin:0; padding:16px 18px; border-bottom:1px solid var(--ks-line); }
        /* The shared table brings its own card; inside this one it sits flush. */
        .ks-dash-latest .ks-card { border:0; border-radius:0; }
        .ks-dash-latest .ks-table tbody tr:hover { background:#F8FAFC; }
        @media (max-width:900px) { .ks-dash-stats { grid-template-columns:1fr; gap:10px; } .ks-dash-stat { min-height:84px; padding:14px 16px; } }
        @media (max-width:640px) {
            .ks-dash-hero { padding:22px; }
            .ks-dash-id { min-width:0; width:100%; }
            .ks-dash-hero-actions .ks-btn { flex:1 1 100%; }
            .ks-dash-shortcuts { grid-template-columns:1fr; }
            .ks-dash-latest-head .ks-btn { width:100%; }
        }
        @media (prefers-reduced-motion:reduce) { .ks-dash-shortcut, .ks-dash-shortcut-icon { transition:none; } .ks-dash-shortcut:hover { transform:none; } }
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
            <h2 id="dashboardWelcome">Welcome back, Surveyor {{ $firstName }}</h2>
            <p>Manage your beacon requests, keep track of issued numbers, and review updates from the Surveyor General’s office.</p>
            <div class="ks-dash-hero-actions">
                <a class="ks-btn" href="{{ route('surveyor-portal.beacon-register') }}" style="background:var(--ks-gold);color:#1B1405;">
                    <i data-lucide="radio-tower" class="w-4 h-4"></i> View my Beacons
                </a>
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.requests.create', ['request_type_dialog' => 1]) }}">
                    <i data-lucide="plus" class="w-4 h-4"></i> Beacon Request
                </a>
            </div>
        </div>
        <div class="ks-dash-id" aria-label="Your account">
            <div><i data-lucide="badge-check" class="w-4 h-4"></i><span>{{ $me->surveyor_id_no }}</span></div>
            @if ($me->company_name)<div><i data-lucide="building-2" class="w-4 h-4"></i><span>{{ $me->company_name }}</span></div>@endif
            <div><i data-lucide="user-round" class="w-4 h-4"></i><span>{{ $me->name }}</span></div>
        </div>
    </section>

    <section class="ks-dash-stats" aria-label="Beacon request summary">
        <article class="ks-card ks-dash-stat waiting">
            <span class="ks-dash-stat-icon"><i data-lucide="clock-3" class="w-6 h-6"></i></span>
            <div><div class="ks-dash-stat-value">{{ number_format($stats['submitted']) }}</div><div class="ks-dash-stat-label">Waiting for the SG</div></div>
        </article>
        <article class="ks-card ks-dash-stat ready">
            <span class="ks-dash-stat-icon"><i data-lucide="package-check" class="w-6 h-6"></i></span>
            <div><div class="ks-dash-stat-value">{{ number_format($stats['ready']) }}</div><div class="ks-dash-stat-label">Ready to Accept</div></div>
        </article>
        <article class="ks-card ks-dash-stat accepted">
            <span class="ks-dash-stat-icon"><i data-lucide="badge-check" class="w-6 h-6"></i></span>
            <div><div class="ks-dash-stat-value">{{ number_format($stats['accepted']) }}</div><div class="ks-dash-stat-label">Accepted Requests</div></div>
        </article>
    </section>

    <section aria-labelledby="dashboardShortcuts">
        <div class="ks-dash-section-heading"><h2 id="dashboardShortcuts">Quick access</h2></div>
        <div class="ks-dash-shortcuts">
            <a class="ks-card ks-dash-shortcut" href="{{ route('surveyor-portal.beacon-register') }}">
                <span class="ks-dash-shortcut-icon"><i data-lucide="radio-tower" class="w-5 h-5"></i></span>
                <span><span class="ks-dash-shortcut-title">My Beacons</span><span class="ks-dash-shortcut-copy block">The beacons you have used, with their coordinates and report.</span></span>
                <i data-lucide="arrow-up-right" class="ks-dash-shortcut-arrow w-4 h-4"></i>
            </a>
            <a class="ks-card ks-dash-shortcut" href="{{ route('surveyor-portal.requests.create', ['request_type_dialog' => 1]) }}">
                <span class="ks-dash-shortcut-icon"><i data-lucide="file-plus-2" class="w-5 h-5"></i></span>
                <span><span class="ks-dash-shortcut-title">Beacon Request</span><span class="ks-dash-shortcut-copy block">Start a Single or Bulk request and attach SURCON payment evidence.</span></span>
                <i data-lucide="arrow-up-right" class="ks-dash-shortcut-arrow w-4 h-4"></i>
            </a>
        </div>
    </section>

    <section class="ks-card ks-dash-latest" aria-labelledby="latestRequestsHeading">
        <div class="ks-dash-section-heading ks-dash-latest-head">
            <div><h2 id="latestRequestsHeading">Latest requests</h2><p class="ks-help" style="margin:4px 0 0;">Your five most recent beacon requests.</p></div>
            <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.requests.index') }}">View all requests <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
        </div>
        @include('surveyor_portal.requests._table', ['rows' => $latest, 'first' => 1])
    </section>
@endsection

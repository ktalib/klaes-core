@extends('surveyor_portal.layouts.portal')

@section('title', 'Dashboard — MLPP-SG Portal')
@section('heading', 'Dashboard')

{{-- A company's dashboard (user, 2026-11-10): its surveyors, its beacons and its surveyors' reports, read only. --}}
@push('styles')
    <style>
        .ks-dash-hero { position:relative; overflow:hidden; padding:28px 30px; border-radius:18px; color:#fff;
            background:linear-gradient(125deg,var(--ks-blue-dark),var(--ks-blue) 68%,#2c6e9a); box-shadow:0 16px 34px -14px rgba(18,47,83,.45); }
        .ks-dash-hero::after { position:absolute; left:0; right:0; bottom:0; height:4px; background:linear-gradient(90deg,var(--ks-blue) 0 33.3%,var(--ks-green) 33.3% 66.6%,var(--ks-gold) 66.6% 100%); content:""; }
        .ks-dash-eyebrow { color:rgba(255,255,255,.75); font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
        .ks-dash-hero h2 { margin:8px 0; color:#fff; font-size:clamp(22px,3vw,30px); font-weight:800; line-height:1.15; }
        .ks-dash-hero p { margin:0; max-width:640px; color:rgba(255,255,255,.85); font-size:14px; line-height:1.6; }
        .ks-co-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin:20px 0 26px; }
        .ks-co-stat { display:flex; align-items:center; gap:14px; min-height:104px; padding:18px; color:inherit; text-decoration:none; transition:border-color .15s ease,box-shadow .15s ease; }
        .ks-co-stat:hover { border-color:#B8C6D9; box-shadow:0 10px 24px rgba(20,23,26,.08); }
        .ks-co-stat-icon { display:grid; flex:0 0 46px; width:46px; height:46px; place-items:center; border-radius:13px; background:#EEF2F8; color:var(--ks-blue); }
        .ks-co-stat-value { font-size:28px; font-weight:800; line-height:1.05; font-variant-numeric:tabular-nums; }
        .ks-co-stat-label { margin-top:5px; color:var(--ks-soft); font-size:13px; font-weight:650; }
        .ks-co-stat-sub { margin-top:2px; color:var(--ks-soft); font-size:12px; }
        .ks-co-grid { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:16px; }
        .ks-co-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin:0; padding:14px 18px; border-bottom:1px solid var(--ks-line); }
        .ks-co-head h2 { margin:0; font-size:16px; font-weight:800; }
        .ks-co-grid .ks-card .ks-card { border:0; border-radius:0; }
        .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
        @media (max-width:1100px) { .ks-co-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } .ks-co-grid { grid-template-columns:1fr; } }
        @media (max-width:560px) { .ks-co-stats { grid-template-columns:1fr; } .ks-dash-hero { padding:22px; } }
    </style>
@endpush

@section('main')
    @php $me = auth('surveyor')->user(); @endphp
    <section class="ks-dash-hero" aria-labelledby="coWelcome">
        <div class="ks-dash-eyebrow">Company workspace</div>
        <h2 id="coWelcome">{{ $me->company_name }}</h2>
        <p>See your company's surveyors, the beacons issued to your company and where they were used, and your surveyors' reports.</p>
    </section>

    <section class="ks-co-stats" aria-label="Company summary">
        <a class="ks-card ks-co-stat" href="{{ route('surveyor-portal.company.surveyors') }}">
            <span class="ks-co-stat-icon"><i data-lucide="users" class="w-5 h-5"></i></span>
            <div><div class="ks-co-stat-value">{{ number_format($stats['surveyors']) }}</div><div class="ks-co-stat-label">Our Surveyors</div><div class="ks-co-stat-sub">{{ number_format($stats['active']) }} active</div></div>
        </a>
        <a class="ks-card ks-co-stat" href="{{ route('surveyor-portal.company.beacons', 'issued') }}">
            <span class="ks-co-stat-icon"><i data-lucide="radio-tower" class="w-5 h-5"></i></span>
            <div><div class="ks-co-stat-value">{{ number_format($stats['issued']) }}</div><div class="ks-co-stat-label">Issued Beacons</div><div class="ks-co-stat-sub">{{ number_format($stats['left']) }} not used yet</div></div>
        </a>
        <a class="ks-card ks-co-stat" href="{{ route('surveyor-portal.company.beacons', 'used') }}">
            <span class="ks-co-stat-icon"><i data-lucide="list-checks" class="w-5 h-5"></i></span>
            <div><div class="ks-co-stat-value">{{ number_format($stats['used']) }}</div><div class="ks-co-stat-label">Used Beacons</div><div class="ks-co-stat-sub">On a Surveyor's Report</div></div>
        </a>
        <a class="ks-card ks-co-stat" href="{{ route('surveyor-portal.company.reports') }}">
            <span class="ks-co-stat-icon"><i data-lucide="clipboard-list" class="w-5 h-5"></i></span>
            <div><div class="ks-co-stat-value">{{ number_format($stats['reports']) }}</div><div class="ks-co-stat-label">Surveyors Reports</div><div class="ks-co-stat-sub">{{ number_format($stats['submitted']) }} submitted</div></div>
        </a>
    </section>

    <div class="ks-co-grid">
        <section class="ks-card" aria-labelledby="coLatest" style="overflow:hidden;">
            <div class="ks-co-head">
                <h2 id="coLatest">Latest issued beacons</h2>
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.company.beacons', 'issued') }}">View all <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
            </div>
            <div class="overflow-x-auto">
                <table class="ks-table">
                    <thead><tr><th>S/N</th><th>Beacon No.</th><th>Issued</th><th>Surveyor</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($latest as $b)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="font-semibold ks-num">{{ \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no) }}</td>
                                <td class="ks-num">{{ $b->register?->issue_date ? \Illuminate\Support\Carbon::parse($b->register->issue_date)->format('d/m/Y') : '—' }}</td>
                                <td>{{ $b->register?->surveyor_name ?: '—' }}</td>
                                <td><span class="ks-badge {{ $b->used ? 'accepted' : 'issued' }}">{{ $b->used ? 'Used' : 'Not used' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ks-help" style="text-align:center;padding:24px;">No beacons have been issued to your company yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ks-card" aria-labelledby="coNotes" style="overflow:hidden;">
            <div class="ks-co-head">
                <h2 id="coNotes">Notifications</h2>
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.notifications') }}">All <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
            </div>
            @forelse ($notes as $n)
                @php $from = $n->surveyorFor($me); @endphp
                <div style="padding:12px 18px;border-bottom:1px solid #ECEEF0;{{ $n->isReadBy($me) ? '' : 'background:#F3F6FB;' }}">
                    @if ($from)<div class="ks-help" style="font-size:12px;font-weight:600;">Surveyor {{ $from }}</div>@endif
                    <div class="font-semibold" style="font-size:14px;">{{ $n->title }}</div>
                    <div class="text-sm">{{ $n->body }}</div>
                    <div class="ks-help">{{ optional($n->created_at)->format('d/m/Y H:i') }}</div>
                </div>
            @empty
                <p class="ks-help" style="padding:20px;text-align:center;margin:0;">No notifications yet.</p>
            @endforelse
        </section>
    </div>
@endsection

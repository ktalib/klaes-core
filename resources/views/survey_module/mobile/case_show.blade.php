@php
    $statusLabel = fn ($s) => $s === 'Review' ? 'Pending Review' : ($s === 'Pending' ? 'Draft' : $s);
    $statusClass = fn ($s) => match ($s) {
        'Review' => 'st-review', 'Active' => 'st-active', 'Completed' => 'st-done', 'Rejected' => 'st-rejected', default => 'st-draft',
    };
    $isLand = $case->scheme_type === 'land';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b2e">
    <title>{{ $case->case_ref }} — Survey Mobile</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('survey_module.mobile._styles')
    <style>
        :root { --page-w: 880px; }
        .appbar { padding-bottom: 18px; }
        .case-hero { max-width: var(--page-w); margin: 14px auto 0; }
        .case-hero h2 { font-size: clamp(24px, 7vw, 32px); font-weight: 800; }
        .case-hero p { opacity: .85; font-size: 14px; margin-top: 2px; }
        .case-hero .pill { font-size: 13px; padding: 4px 12px; }
        .stack { display: grid; gap: 16px; }
        .list { display: grid; gap: 10px; }
        .li { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--line); border-radius: 14px; }
        .li .main-t { font-weight: 700; color: var(--ink); overflow: hidden; text-overflow: ellipsis; }
        .li small { display: block; color: var(--muted); font-size: 13px; }
        .li .amt { font-weight: 700; color: var(--ink); white-space: nowrap; }
    </style>
</head>
<body>

<header class="appbar">
    <div class="appbar-row">
        <a href="{{ route('survey-module.mobile.index') }}" class="icon-btn" aria-label="Back to dashboard"><i class="fas fa-arrow-left"></i></a>
        <div class="appbar-title">
            <h1>Case details</h1>
            <p>{{ $case->project?->project_code }} · {{ $case->project?->name }}</p>
        </div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
    <div class="case-hero">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h2>{{ $case->case_ref }}</h2>
            <span class="pill {{ $statusClass($case->status) }}">{{ $statusLabel($case->status) }}</span>
        </div>
        <p>{{ $isLand ? 'Land-for-Land (50:50)' : 'Monetary (Cash for Trees)' }} · registered {{ optional($case->created_at)->format('d M Y, H:i') }}</p>
    </div>
</header>

@include('survey_module.mobile._menu')

<div class="wrap stack" style="padding-bottom:{{ $canSubmit ? '0' : '24px' }};">
    @if (session('success'))
        <div class="flash ok" role="status"><i class="fas fa-circle-check"></i><div>{{ session('success') }}</div></div>
    @endif
    @if (session('error'))
        <div class="flash bad" role="alert"><i class="fas fa-triangle-exclamation"></i><div>{{ session('error') }}</div></div>
    @endif

    {{-- Compensation headline --}}
    <div class="stat">
        @if ($isLand)
            <div class="lbl"><i class="fas fa-map-location-dot"></i> Land-for-Land</div>
            <div class="big">{{ $split['total'] }} plots</div>
            <div class="split">
                <div><b>{{ $split['farmer'] }}</b><small>Farmer</small></div>
                <span style="font-size:24px;color:#d1d5db;">:</span>
                <div><b>{{ $split['govt'] }}</b><small>Government</small></div>
            </div>
        @else
            <div class="lbl"><i class="fas fa-money-bill-wave"></i> Cash compensation</div>
            <div class="big">₦{{ number_format($cash, 2) }}</div>
            <div class="hint">{{ $case->trees->count() }} tree line(s) · {{ number_format($case->trees->sum('quantity')) }} tree(s)</div>
        @endif
    </div>

    <div class="card card-pad">
        <div class="section-title" style="margin-top:0;"><i class="fas fa-circle-info"></i> General</div>
        <div class="review">
            <div><span>Project</span><span>{{ $case->project?->project_code }} · {{ $case->project?->name }}</span></div>
            <div><span>Purpose</span><span>{{ $case->purpose ?: '—' }}</span></div>
            <div><span>Survey officer</span><span>{{ $case->survey_officer ?: '—' }}</span></div>
            <div><span>Date</span><span>{{ optional($case->case_date)->format('d M Y') ?? '—' }}</span></div>
            <div><span>Area</span><span>{{ $case->area_ha !== null ? rtrim(rtrim((string) $case->area_ha, '0'), '.') . ' Ha' : '—' }}</span></div>
            <div><span>Location</span><span>{{ $location ?: '—' }}</span></div>
            <div><span>Coordinates</span><span>{{ $case->coordinates ?: '—' }}</span></div>
            <div><span>GPS reading</span><span>{{ $case->gps_reading ?: '—' }}</span></div>
            @if ($case->prop_plot)
                <div><span>Plot No.</span><span>{{ $case->prop_plot }}</span></div>
            @endif
            <div><span>Boundary file</span><span>{{ $case->boundary_file ?: '—' }}</span></div>
            <div class="wide"><span>Description</span><span>{{ $case->description ?: '—' }}</span></div>
        </div>
    </div>

    <div class="card card-pad">
        <div class="section-title" style="margin-top:0;"><i class="fas fa-users"></i> Beneficiaries ({{ $case->beneficiaries->count() }})</div>
        @if ($case->beneficiaries->isEmpty())
            <div class="empty-state" style="margin-top:0;"><i class="fas fa-user-group"></i>No farmers on this case yet.</div>
        @else
            <div class="list">
                @foreach ($case->beneficiaries as $b)
                    <div class="li">
                        <div style="min-width:0;">
                            <div class="main-t">{{ $b->full_name }}</div>
                            <small>{{ $b->phone ?: 'No phone' }}{{ $b->nin ? ' · NIN ' . $b->nin : '' }}</small>
                        </div>
                        <span class="pill {{ $b->status === 'Verified' ? 'st-done' : ($b->status === 'Review' ? 'st-review' : 'st-draft') }}">{{ $b->status }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @unless ($isLand)
        <div class="card card-pad">
            <div class="section-title" style="margin-top:0;"><i class="fas fa-tree"></i> Economic trees</div>
            @if ($case->trees->isEmpty())
                <div class="empty-state" style="margin-top:0;"><i class="fas fa-tree"></i>No tree lines yet.</div>
            @else
                <div class="list">
                    @foreach ($case->trees as $t)
                        <div class="li">
                            <div style="min-width:0;">
                                <div class="main-t">{{ $t->tree_type }}</div>
                                <small>{{ number_format($t->quantity) }} × ₦{{ number_format((float) $t->unit_price, 2) }}</small>
                            </div>
                            <span class="amt">₦{{ number_format((float) $t->line_total, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endunless

    <p class="hint" style="text-align:center;">To change this case, open it in the desktop module.</p>
</div>

@if ($canSubmit)
    <nav class="actionbar">
        <form class="actionbar-inner" method="POST" action="{{ route('survey-module.mobile.cases.submit', $case) }}"
              onsubmit="return confirm('Submit {{ $case->case_ref }} for review?');">
            @csrf
            <a class="btn btn-ghost" href="{{ route('survey-module.mobile.index') }}" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
            <span class="spacer"></span>
            <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Submit for review</button>
        </form>
    </nav>
@endif
</body>
</html>

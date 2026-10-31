@php
    $opKey = \App\Services\Survey\OpLookup::key($check->op_source, $check->op_source_id);
    $cls = ['registered' => 'ok', 'other_surveyor' => 'warn', 'unregistered' => 'bad'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('survey_module.mobile.plots._head', ['title' => $check->check_ref])
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <a class="icon-btn" href="{{ route('survey-module.mobile.plots.index') }}" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
        <div class="appbar-title"><h1>{{ $check->check_ref }}</h1><p>{{ $check->target_label }}</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    @if (session('success'))<div class="scf-note ok">{{ session('success') }}</div>@endif

    <div class="card card-pad" style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <strong>Ground check</strong>
            <span class="st {{ $check->result === 'Confirmed' ? 'ok' : 'bad' }}">{{ $check->result }}</span>
        </div>
        @if ($check->discrepancy_label)<div style="margin-top:6px;font-weight:600;color:#b91c1c;">{{ $check->discrepancy_label }}</div>@endif
        {{-- Plot No., District, LGA, Location --}}
        <dl class="kv">
            @if ($check->op_serial)<dt>OP</dt><dd>@if ($opKey)<a href="{{ route('survey-module.mobile.plots.op', $opKey) }}">{{ $check->op_serial }}</a>@else{{ $check->op_serial }}@endif</dd>@endif
            @if ($check->holder_name)<dt>Allottee</dt><dd>{{ $check->holder_name }}</dd>@endif
            <dt>Plot No.</dt><dd>{{ $check->plot_no ?: '—' }}</dd>
            <dt>District</dt><dd>{{ $check->district ?: '—' }}</dd>
            <dt>LGA</dt><dd>{{ $check->lga ?: '—' }}</dd>
            <dt>Location</dt><dd>{{ $check->location ?: '—' }}</dd>
            @if ($check->layout_no)<dt>Layout</dt><dd>{{ $check->layout_no }}</dd>@endif
            <dt>Checked</dt><dd>{{ $check->checked_on?->format('d M Y') }} {{ $check->checked_at }}</dd>
            <dt>Surveyor</dt><dd>{{ $check->surveyor_name }}</dd>
            @if ($check->latitude !== null)<dt>GPS</dt><dd>{{ number_format($check->latitude, 6) }}, {{ number_format($check->longitude, 6) }}{{ $check->accuracy_m ? ' (±' . round($check->accuracy_m) . ' m)' : '' }}</dd>@endif
        </dl>
        @if ($check->notes)<div class="sub" style="margin-top:8px;">{{ $check->notes }}</div>@endif
    </div>

    <div class="card card-pad" style="margin-bottom:12px;">
        <strong>Beacons against the Beacon Register</strong>
        @forelse ((array) $check->beacon_check as $b)
            <div class="item">
                <div><strong>{{ $b['beacon'] }}</strong>
                    @if ($b['register_no'])<div class="sub">{{ $b['register_no'] }}{{ $b['surveyor'] ? ' · ' . $b['surveyor'] : '' }}{{ $b['issue_date'] ? ' · ' . $b['issue_date'] : '' }}</div>@endif</div>
                <div><span class="st {{ $cls[$b['status']] ?? 'warn' }}">{{ $b['label'] }}</span></div>
            </div>
        @empty
            <div class="sub" style="padding:8px 0;">No beacon numbers were recorded.</div>
        @endforelse
    </div>

    <div class="card card-pad">
        <strong>Photographs</strong>
        @if ($check->photos->isNotEmpty())
            <div class="thumbs">@foreach ($check->photos as $ph)<a href="{{ route('survey-module.mobile.plots.checks.photos.show', $ph) }}"><img src="{{ route('survey-module.mobile.plots.checks.photos.show', $ph) }}" alt="Ground check photo" loading="lazy"></a>@endforeach</div>
        @else
            <div class="sub" style="padding:8px 0;">None{{ request('photos') === 'waiting' ? ' yet: they are waiting to upload' : '' }}.</div>
        @endif
    </div>
</main>

@include('survey_module.mobile._footer', ['activeTab' => 'checks'])
</body>
</html>

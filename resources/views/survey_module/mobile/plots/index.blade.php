<!DOCTYPE html>
<html lang="en">
<head>
    @include('survey_module.mobile.plots._head', ['title' => 'Home'])
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <div class="appbar-logo"><img src="{{ asset('assets/logo/SCF.png') }}" alt="" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'fas fa-map-location-dot'}))"></div>
        <div class="appbar-title"><h1>Plot Confirmation</h1><p>{{ auth()->user()->name }}</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    <div class="grid2" style="margin-bottom:12px;">
        <a class="btn btn-primary" href="{{ route('survey-module.mobile.plots.ops') }}" style="justify-content:center;"><i class="fas fa-people-arrows"></i> Show OP plot</a>
        <a class="btn btn-success" href="{{ route('survey-module.mobile.plots.checks.create') }}" style="justify-content:center;"><i class="fas fa-map-location-dot"></i> Ground check</a>
    </div>

    <div class="stats">
        <div class="card"><b>{{ $stats['showings_today'] }}</b><span>Showings today · {{ $stats['showings_month'] }} this month</span></div>
        <div class="card"><b>{{ $stats['checks_today'] }}</b><span>Ground checks today · {{ $stats['checks_month'] }} this month{{ $stats['discrepancies'] ? ', ' . $stats['discrepancies'] . ' with a discrepancy' : '' }}</span></div>
    </div>

    <div class="card card-pad" style="margin-bottom:12px;">
        <strong>My latest showings</strong>
        @forelse ($showings as $s)
            <a class="item" href="{{ route('survey-module.mobile.plots.op', $s->op_key) }}">
                <div><strong>OP {{ $s->op_serial ?: '—' }} · {{ $s->label }}</strong>
                    <div class="sub">{{ $s->allottee_name }}</div>
                    {{-- Plot No., District, LGA --}}
                    <div class="sub">{{ implode(' · ', array_filter([$s->plot_no ? 'Plot ' . $s->plot_no : null, $s->district, $s->lga])) }}</div></div>
                <div class="sub" style="text-align:right;">{{ $s->shown_on?->format('d M') }}<br>{{ $s->photos_count }} photo{{ $s->photos_count === 1 ? '' : 's' }}</div>
            </a>
        @empty
            <div class="sub" style="padding:8px 0;">None yet. Find an OP to record its showing.</div>
        @endforelse
    </div>

    <div class="card card-pad">
        <strong>My latest ground checks</strong>
        @forelse ($checks as $c)
            <a class="item" href="{{ route('survey-module.mobile.plots.checks.show', $c) }}">
                <div><strong>{{ $c->check_ref }} · {{ $c->target_label }}</strong>
                    <div class="sub">{{ implode(' · ', array_filter([$c->district, $c->lga])) ?: ($c->holder_name ?: '') }}</div></div>
                <div style="text-align:right;"><span class="st {{ $c->result === 'Confirmed' ? 'ok' : 'bad' }}">{{ $c->result }}</span>
                    <div class="sub">{{ $c->checked_on?->format('d M') }}</div></div>
            </a>
        @empty
            <div class="sub" style="padding:8px 0;">None yet.</div>
        @endforelse
    </div>
</main>

@include('survey_module.mobile._footer', ['activeTab' => 'plots'])
</body>
</html>

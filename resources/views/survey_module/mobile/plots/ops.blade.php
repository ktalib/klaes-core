<!DOCTYPE html>
<html lang="en">
<head>
    @include('survey_module.mobile.plots._head', ['title' => 'Find OP'])
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <a class="icon-btn" href="{{ $for === 'check' ? route('survey-module.mobile.plots.checks.create') : route('survey-module.mobile.plots.index') }}" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
        <div class="appbar-title"><h1>{{ $for === 'check' ? 'OP to ground-check' : 'Show OP plot' }}</h1><p>Find the Occupancy Permit</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    <form class="card card-pad" method="GET" action="{{ route('survey-module.mobile.plots.ops') }}" role="search" style="margin-bottom:12px;">
        @if ($for === 'check')<input type="hidden" name="for" value="check">@endif
        <div class="field"><label for="q">OP serial, allottee, plot no. or file no.</label>
            <input class="input" id="q" name="q" value="{{ $q }}" minlength="2" maxlength="100" autocomplete="off" required autofocus></div>
        <button class="btn btn-primary" type="submit" style="width:100%;margin-top:10px;"><i class="fas fa-magnifying-glass"></i> Search</button>
        <div class="sub" style="margin-top:6px;">Searching needs a signal. OPs you have opened before can be reopened offline from Home.</div>
    </form>

    @if ($q !== '')
        <div class="card card-pad">
            <strong>{{ count($results) }} result{{ count($results) === 1 ? '' : 's' }}{{ count($results) >= 30 ? ' (first 30: type more to narrow)' : '' }}</strong>
            @forelse ($results as $op)
                @php $n = $counts[$op['key']] ?? 0; @endphp
                <a class="item" href="{{ $for === 'check' ? route('survey-module.mobile.plots.checks.create', ['op' => $op['key']]) : route('survey-module.mobile.plots.op', $op['key']) }}">
                    <div><strong>OP {{ $op['serial'] ?: '—' }}</strong>{{ $op['type'] ? ' · ' . $op['type'] : '' }}
                        <div class="sub">{{ $op['allottee'] ?: 'No allottee recorded' }}</div>
                        {{-- Plot No., District, LGA --}}
                        <div class="sub">{{ implode(' · ', array_filter([$op['plot_no'] ? 'Plot ' . $op['plot_no'] : null, $op['district'], $op['lga']])) ?: ($op['location'] ?: 'No plot recorded') }}</div>
                        @if ($op['file_number'])<div class="sub">{{ $op['file_number'] }}</div>@endif
                    </div>
                    <div style="text-align:right;">@if ($n)<span class="st ok">Shown {{ $n }}×</span>@endif</div>
                </a>
            @empty
                <div class="sub" style="padding:8px 0;">No OP matches "{{ $q }}".</div>
            @endforelse
        </div>
    @endif
</main>

@include('survey_module.mobile._footer', ['activeTab' => $for === 'check' ? 'checks' : 'showings'])
</body>
</html>

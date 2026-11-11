<!DOCTYPE html>
<html lang="en">
<head>
    @include('survey_module.mobile.plots._head', ['title' => 'Find OP'])
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <a class="icon-btn" href="{{ $for === 'check' ? route('survey-module.mobile.plots.checks.create') : route('survey-module.mobile.plots.index') }}" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
        <div class="appbar-title"><h1>{{ $for === 'check' ? 'OP to ground-check' : 'Plot Showing' }}</h1><p>Find the Occupancy Permit</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    {{-- As the web Record Plot Showing (user, 2026-11-09): the layout first, then the plot in it (CR 03112026 item 3). --}}
    <form class="card card-pad" method="GET" action="{{ route('survey-module.mobile.plots.ops') }}" role="search" style="margin-bottom:12px;">
        @if ($for === 'check')<input type="hidden" name="for" value="check">@endif
        {{-- One searchable dropdown (user, 2026-11-09): tap it, type to search, pick. --}}
        @php $tpPicked = $tpGroups->firstWhere('id', $group); @endphp
        <div class="field" style="position:relative;"><label for="tpSearch">TP No.</label>
            <input type="hidden" name="group" id="tpGroup" value="{{ $tpPicked ? $tpPicked->id : '' }}">
            <input class="input" id="tpSearch" autocomplete="off" autocapitalize="characters" role="combobox" aria-expanded="false" aria-controls="tpList"
                   placeholder="— Select TP No. —" value="{{ $tpPicked ? $tpPicked->label : '' }}" style="padding-right:34px;">
            <i class="fas fa-chevron-down" aria-hidden="true" style="position:absolute;right:12px;top:38px;color:#6b7280;pointer-events:none;"></i>
            <div id="tpList" role="listbox" hidden
                 style="position:absolute;z-index:40;left:0;right:0;max-height:280px;overflow:auto;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.15);margin-top:4px;">
                @foreach ($tpGroups as $g)
                    <div class="tp-opt" role="option" data-id="{{ $g->id }}" data-label="{{ $g->label }}" style="padding:10px 12px;border-bottom:1px solid #f3f4f6;cursor:pointer;">
                        <strong>{{ $g->label }}</strong> <span class="sub">({{ number_format($g->op_count) }} OPs)</span></div>
                @endforeach
                <div id="tpNone" class="sub" hidden style="padding:10px 12px;">No TP No. matches.</div>
            </div>
            <div class="sub" style="margin-top:4px;">{{ number_format($tpGroups->count()) }} layouts, grouped from the TP numbers on the OPs.</div>
        </div>
        <div class="field" style="margin-top:10px;"><label for="plot">Plot No.</label>
            <input class="input" id="plot" name="plot" value="{{ $plot }}" maxlength="100" autocomplete="off" autocapitalize="characters" placeholder="Leave blank to list the layout's plots"></div>
        <button class="btn btn-primary" type="submit" style="width:100%;margin-top:10px;"><i class="fas fa-magnifying-glass"></i> Find plot</button>
    </form>

    {{-- For an OP with no TP number on it: by its serial number only, as on the web. --}}
    <form class="card card-pad" method="GET" action="{{ route('survey-module.mobile.plots.ops') }}" role="search" style="margin-bottom:12px;">
        @if ($for === 'check')<input type="hidden" name="for" value="check">@endif
        <div class="field"><label for="q">No TP No. on the OP? OP serial number</label>
            <input class="input" id="q" name="q" value="{{ $group ? '' : $q }}" minlength="2" maxlength="100" inputmode="numeric" autocomplete="off" required></div>
        <button class="btn btn-outline" type="submit" style="width:100%;margin-top:10px;"><i class="fas fa-hashtag"></i> Find by serial</button>
        <div class="sub" style="margin-top:6px;">Searching needs a signal. OPs you have opened before can be reopened offline from Home.</div>
    </form>

    @if ($searched)
        <div class="card card-pad">
            <strong>{{ count($results) }} plot{{ count($results) === 1 ? '' : 's' }}{{ count($results) >= ($group ? 100 : 30) ? ' (first ' . ($group ? 100 : 30) . ': type the plot no. to narrow)' : '' }}</strong>
            @forelse ($results as $op)
                @php $n = $counts[$op['key']] ?? 0; @endphp
                <a class="item" href="{{ $for === 'check' ? route('survey-module.mobile.plots.checks.create', ['op' => $op['key']]) : route('survey-module.mobile.plots.op', $op['key']) }}">
                    <div>
                        {{-- Plot No., District, LGA first: the plot is what is being found. --}}
                        <strong>{{ $op['plot_no'] ? 'Plot ' . $op['plot_no'] : 'No plot recorded' }}</strong>
                        <div class="sub">{{ implode(' · ', array_filter([$op['district'], $op['lga']])) ?: ($op['location'] ?: '') }}</div>
                        <div class="sub">{{ $op['allottee'] ?: 'No allottee recorded' }}</div>
                        <div class="sub">OP {{ $op['serial'] ?: '—' }}{{ $op['type'] ? ' · ' . $op['type'] : '' }}{{ $op['file_number'] ? ' · ' . $op['file_number'] : '' }}</div>
                    </div>
                    <div style="text-align:right;">@if ($n)<span class="st ok">Shown {{ $n }}×</span>@endif</div>
                </a>
            @empty
                <div class="sub" style="padding:8px 0;">{{ $group ? 'No OP in this layout matches that plot number.' : 'No OP has a serial number like "' . $q . '".' }}</div>
            @endforelse
        </div>
    @endif
</main>

<script>
(function () {
    // TP No.: a searchable dropdown. The box shows the picked layout; typing filters the list under it.
    var input = document.getElementById('tpSearch'), hidden = document.getElementById('tpGroup'), list = document.getElementById('tpList'), none = document.getElementById('tpNone');
    if (!input || !hidden || !list) return;
    var opts = Array.prototype.slice.call(list.querySelectorAll('.tp-opt'));
    var norm = function (s) { return String(s || '').toUpperCase().replace(/\s+/g, ''); };
    var picked = input.value;
    function filter(term) {
        var t = norm(term), shown = 0;
        opts.forEach(function (o) { var hit = !t || norm(o.dataset.label).indexOf(t) !== -1; o.hidden = !hit; if (hit) shown++; });
        none.hidden = shown > 0;
    }
    function open() { list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); }
    input.addEventListener('focus', function () { input.select(); filter(''); open(); });
    input.addEventListener('input', function () { hidden.value = ''; filter(input.value); open(); });
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep the box focused while picking
    list.addEventListener('click', function (e) {
        var o = e.target.closest('.tp-opt'); if (!o) return;
        hidden.value = o.dataset.id; input.value = picked = o.dataset.label; close();
        document.getElementById('plot').focus();
    });
    input.addEventListener('blur', function () { setTimeout(function () { close(); if (!hidden.value) input.value = ''; else input.value = picked; }, 150); });
    input.closest('form').addEventListener('submit', function (e) {
        if (!hidden.value) { e.preventDefault(); input.focus(); input.setCustomValidity('Choose the TP No. from the list.'); input.reportValidity(); setTimeout(function () { input.setCustomValidity(''); }, 2000); }
    });
})();
</script>

@include('survey_module.mobile._footer', ['activeTab' => $for === 'check' ? 'checks' : 'showings'])
</body>
</html>

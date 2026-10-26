@php
    use App\Support\SurveyGeometry as G;
    $p = $anchor['prefill'];
    $last = $readings->last();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="{{ asset('assets/logo/SCF.png') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b2e">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $p['its_number'] }} — Field Observations</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('survey_module.mobile._styles')
    <style>
        body { padding-bottom: calc(84px + env(safe-area-inset-bottom)); }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .dms { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }
        .reading { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #efedf3; font-size: 14px; }
        .reading:last-child { border-bottom: 0; }
        .reading .mono { font-family: ui-monospace, monospace; }
        .pending { color: #92400e; font-size: 12px; }
        .err-box { color: #b91c1c; font-size: 13.5px; margin-top: 8px; }
    </style>
</head>
<body class="has-mobile-footer">
<header class="appbar">
    <div class="appbar-row">
        <a class="icon-btn" href="{{ route('survey-module.mobile.observations') }}" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
        <div class="appbar-title"><h1>{{ $p['its_number'] }}</h1><p>{{ $p['file_number'] }}{{ $p['grantee_name'] ? ' · ' . $p['grantee_name'] : '' }}</p></div>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>
</header>
@include('survey_module.mobile._menu')

<main class="wrap">
    @if (session('success'))<div class="scf-note ok">{{ session('success') }}</div>@endif

    @if ($anchor['locked'])
        <div class="card card-pad" style="margin-bottom:14px;">This job is closed; no more readings can be added.</div>
    @else
        <form class="card card-pad" id="readingForm" method="POST" action="{{ route('survey-module.mobile.observations.store', $anchor['key']) }}" style="margin-bottom:14px;" novalidate>
            @csrf
            <datalist id="stations">@foreach ($stations as $s)<option value="{{ $s }}">@endforeach</datalist>
            <div class="grid2">
                <div class="field"><label for="from">From station</label>
                    <input class="input" id="from" name="from_station" list="stations" maxlength="50" value="{{ $last?->to_station }}" autocapitalize="characters" required></div>
                <div class="field"><label for="to">To station</label>
                    <input class="input" id="to" name="to_station" list="stations" maxlength="50" autocapitalize="characters" required></div>
            </div>
            <div class="field" style="margin-top:10px;"><label>Bearing (° ′ ″)</label>
                <div class="dms">
                    <input class="input" name="bearing_d" type="number" inputmode="numeric" min="0" max="359" placeholder="deg" required>
                    <input class="input" name="bearing_m" type="number" inputmode="numeric" min="0" max="59" placeholder="min">
                    <input class="input" name="bearing_s" type="number" inputmode="decimal" min="0" max="59.9" step="0.1" placeholder="sec">
                </div></div>
            <div class="grid2" style="margin-top:10px;">
                <div class="field"><label for="dist">Distance (m)</label>
                    <input class="input" id="dist" name="observed_distance" type="number" inputmode="decimal" min="0.001" step="0.001" required></div>
                <div class="field"><label for="on">Date</label>
                    <input class="input" id="on" name="observed_on" type="date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"></div>
            </div>
            <div class="field" style="margin-top:10px;"><label for="rem">Remarks</label>
                <input class="input" id="rem" name="remarks" maxlength="500"></div>
            <div class="err-box" id="readingErr" hidden></div>
            <button type="submit" class="btn btn-success" id="readingSave" style="margin-top:12px;width:100%;"><i class="fas fa-plus"></i> Save reading</button>
        </form>
    @endif

    <div class="card card-pad">
        <strong>Readings</strong>
        <div id="readingList">
            @forelse ($readings as $r)
                <div class="reading">
                    <div><strong>{{ $r->from_station }} → {{ $r->to_station }}</strong><div class="mono">{{ G::dms($r->observed_bearing) }} · {{ number_format($r->observed_distance, 3) }} m</div></div>
                    <div style="font-size:12px;opacity:.7;text-align:right;">{{ optional($r->observed_on)->format('d M') }}<br>{{ $r->source === 'mobile' ? 'Phone' : 'Desktop' }}</div>
                </div>
            @empty
                <div class="reading" id="noReadings" style="opacity:.7;">No readings yet.</div>
            @endforelse
        </div>
    </div>
</main>

@include('survey_module.mobile._footer', ['activeTab' => 'readings'])
<script>
(function () {
    var form = document.getElementById('readingForm');
    if (!form) return;
    var err = document.getElementById('readingErr'), btn = document.getElementById('readingSave'), list = document.getElementById('readingList');
    function show(msg) { err.textContent = msg; err.hidden = !msg; }
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        show('');
        if (!form.from_station.value.trim() || !form.to_station.value.trim() || form.bearing_d.value === '' || !form.observed_distance.value) {
            show('Fill in both stations, the bearing (degrees at least) and the distance.'); return;
        }
        var fields = [];
        new FormData(form).forEach(function (v, k) { if (k !== '_token') fields.push([k, v]); });
        var label = form.from_station.value.toUpperCase() + ' → ' + form.to_station.value.toUpperCase();
        btn.disabled = true;
        (window.SCF ? SCF.submitReading(form.action, fields, @json($p['its_number']) + ' · ' + label)
                    : Promise.resolve({ fallback: true })).then(function (res) {
            btn.disabled = false;
            if (res.fallback) { form.submit(); return; }
            if (res.errors) { var k = Object.keys(res.errors)[0]; show(res.errors[k][0]); return; }
            // Show it straight away; a queued one is marked until it syncs.
            var none = document.getElementById('noReadings'); if (none) none.remove();
            var d = form.bearing_d.value, m = form.bearing_m.value || '0', s = form.bearing_s.value || '0';
            var row = document.createElement('div');
            row.className = 'reading';
            row.innerHTML = '<div><strong></strong><div class="mono"></div></div><div style="font-size:12px;text-align:right;"></div>';
            row.querySelector('strong').textContent = label;
            row.querySelector('.mono').textContent = d + '°' + String(m).padStart(2, '0') + "'" + s + '" · ' + Number(form.observed_distance.value).toFixed(3) + ' m';
            var tag = row.lastChild; tag.textContent = res.queued ? 'Saved on phone · will sync' : 'Saved'; if (res.queued) tag.className = 'pending';
            list.appendChild(row);
            // The next reading starts where this one ended.
            form.from_station.value = form.to_station.value.toUpperCase();
            ['to_station', 'bearing_d', 'bearing_m', 'bearing_s', 'observed_distance', 'remarks'].forEach(function (n) { form[n].value = ''; });
            form.to_station.focus();
        });
    });
})();
</script>
</body>
</html>

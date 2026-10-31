@php
    use App\Support\SurveyGeometry as G;
    use App\Services\Survey\BeaconNumberCheck as BNC;
    $p = $anchor['prefill'];
    $last = $readings->last();
    $isPrivate = $anchor['source'] === 'private';
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
        .mono { font-family: ui-monospace, monospace; font-size: 13px; }
        .pending { color: #92400e; font-size: 12px; }
        .err-box { color: #b91c1c; font-size: 13.5px; margin-top: 8px; }
        .tabs { display: flex; gap: 6px; overflow-x: auto; margin: 0 0 12px; scrollbar-width: none; }
        .tabs::-webkit-scrollbar { display: none; }
        .tabs button { flex: 0 0 auto; border: 1px solid var(--line); background: #fff; color: var(--text); border-radius: 999px; padding: 8px 14px; font: 600 13.5px 'Outfit', sans-serif; }
        .tabs button.on { background: var(--ink); color: #fff; border-color: var(--ink); }
        .tabs .n { opacity: .7; font-weight: 500; margin-left: 4px; }
        .pane[hidden] { display: none; }
        .kv { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: 14px; }
        .kv dt { color: var(--muted); }
        .kv dd { margin: 0; font-weight: 600; text-align: right; }
        .st { display: inline-block; font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 2px 9px; }
        .st.ok { background: #ecfdf5; color: #047857; }
        .st.warn { background: #fffbeb; color: #92400e; }
        .st.bad { background: #fef2f2; color: #b91c1c; }
        .gps { font-size: 12.5px; color: var(--muted); }
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
    @if ($errors->any())<div class="scf-note wait">{{ $errors->first() }}</div>@endif

    {{-- Plot No., District, LGA, Location --}}
    <div class="card card-pad" style="margin-bottom:12px;font-size:13.5px;">
        <strong>{{ $isPrivate ? "Surveyor's Report (Private)" : ($anchor['source'] === 'cadastral' ? 'Cadastral I to S' : 'Survey I to S') }}</strong>
        <div style="opacity:.8;margin-top:3px;">{{ implode(' · ', array_filter([$p['plot_no'] ? 'Plot ' . $p['plot_no'] : null, $p['district'], $p['lga'], $p['location'] !== implode(', ', array_filter([$p['district'], $p['lga']])) ? $p['location'] : null])) ?: 'No location recorded' }}</div>
        @if ($p['surveyor_name'])<div style="opacity:.8;">{{ $p['surveyor_name'] }}</div>@endif
        @if ($anchor['locked'])<div style="margin-top:6px;" class="st warn">Closed: view only</div>@endif
    </div>

    <div class="tabs" role="tablist">
        <button type="button" data-tab="readings">Readings<span class="n">{{ $readings->count() }}</span></button>
        <button type="button" data-tab="coords">Coordinates<span class="n">{{ $coords->count() }}</span></button>
        <button type="button" data-tab="beacons">Beacon Nos</button>
        <button type="button" data-tab="compute">Computation</button>
        <button type="button" data-tab="sketch">Sketch Plan</button>
    </div>

    {{-- ============================== Readings ============================== --}}
    <section class="pane" data-pane="readings">
        @unless ($anchor['locked'])
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
        @endunless

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
    </section>

    {{-- ============================ Coordinates ============================= --}}
    <section class="pane" data-pane="coords" hidden>
        @unless ($anchor['locked'])
            <form class="card card-pad" id="coordForm" method="POST" action="{{ route('survey-module.mobile.observations.coords.store', $anchor['key']) }}" style="margin-bottom:14px;" novalidate>
                @csrf
                <div class="field"><label for="bid">Beacon ID</label>
                    <input class="input" id="bid" name="beacon_id" maxlength="50" autocapitalize="characters" required></div>
                <div class="grid2" style="margin-top:10px;">
                    <div class="field"><label for="nn">Northing (m)</label>
                        <input class="input" id="nn" name="northing" type="number" inputmode="decimal" step="0.001" required></div>
                    <div class="field"><label for="ee">Easting (m)</label>
                        <input class="input" id="ee" name="easting" type="number" inputmode="decimal" step="0.001" required></div>
                </div>
                <div class="grid2" style="margin-top:10px;">
                    <div class="field"><label for="el">Elevation (m)</label>
                        <input class="input" id="el" name="elevation" type="number" inputmode="decimal" step="0.001"></div>
                    <div class="field"><label for="crem">Remarks</label>
                        <input class="input" id="crem" name="remarks" maxlength="500"></div>
                </div>
                <input type="hidden" name="gps_latitude"><input type="hidden" name="gps_longitude"><input type="hidden" name="gps_accuracy_m">
                <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-ghost btn-sm" id="gpsBtn"><i class="fas fa-location-crosshairs"></i> Record GPS here</button>
                    <span class="gps" id="gpsOut">The phone's position is kept as a field check; it does not replace the northing and easting.</span>
                </div>
                <div class="err-box" id="coordErr" hidden></div>
                <button type="submit" class="btn btn-success" id="coordSave" style="margin-top:12px;width:100%;"><i class="fas fa-plus"></i> Save beacon</button>
            </form>
        @endunless

        <div class="card card-pad">
            <strong>List of Coordinates</strong>
            <div id="coordList">
                @forelse ($coords as $c)
                    <div class="reading">
                        <div><strong>{{ $c->beacon_id }}</strong><div class="mono">N {{ number_format((float) $c->northing, 3) }} · E {{ number_format((float) $c->easting, 3) }}</div>
                            @if ($c->gps_latitude !== null)<div class="gps"><i class="fas fa-location-dot"></i> {{ number_format((float) $c->gps_latitude, 6) }}, {{ number_format((float) $c->gps_longitude, 6) }}{{ $c->gps_accuracy_m ? ' (±' . round($c->gps_accuracy_m) . ' m)' : '' }}</div>@endif
                        </div>
                        <div style="font-size:12px;opacity:.7;text-align:right;">{{ $c->source === 'mobile' ? 'Phone' : 'Desktop' }}</div>
                    </div>
                @empty
                    <div class="reading" id="noCoords" style="opacity:.7;">No beacons yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============================= Beacon Nos ============================= --}}
    <section class="pane" data-pane="beacons" hidden>
        <div class="card card-pad">
            <strong>Beacon Nos against the Beacon Register</strong>
            <div class="hint" style="margin-bottom:6px;">{{ $beaconSum['registered'] }} registered · {{ $beaconSum['other_surveyor'] }} issued to another surveyor · {{ $beaconSum['unregistered'] }} not in the register{{ $beaconSum['reused'] ? ' · ' . $beaconSum['reused'] . ' used on another survey' : '' }}. Refreshed when you open this page online.</div>
            @forelse ($beacons as $b)
                @php $cls = ['registered' => 'ok', 'other_surveyor' => 'warn', 'unregistered' => 'bad'][$b->status]; @endphp
                <div class="reading">
                    <div><strong>{{ $b->beacon_id }}</strong>
                        @if ($b->issue)<div class="gps">{{ $b->issue->register_no }}{{ $b->issue->surveyor_name ? ' · ' . $b->issue->surveyor_name : '' }}</div>@endif
                        @if ($b->elsewhere->isNotEmpty())<div class="gps" style="color:#92400e;">Also on {{ $b->elsewhere->implode(', ') }}</div>@endif
                    </div>
                    <div><span class="st {{ $cls }}">{{ BNC::LABELS[$b->status] }}</span></div>
                </div>
            @empty
                <div class="reading" style="opacity:.7;">Add beacons under Coordinates to check them.</div>
            @endforelse
        </div>
    </section>

    {{-- ============================ Computation ============================= --}}
    <section class="pane" data-pane="compute" hidden>
        <div class="card card-pad" style="margin-bottom:12px;">
            <strong>Traverse closure (readings)</strong>
            @if ($misclosure)
                <dl class="kv" style="margin-top:8px;">
                    <dt>Closed loop</dt><dd>{{ $closed ? 'Yes, back to ' . $readings->first()->from_station : 'Not yet' }}</dd>
                    <dt>Total distance</dt><dd>{{ number_format($misclosure['length'], 3) }} m</dd>
                    <dt>ΔN / ΔE</dt><dd class="mono">{{ number_format($misclosure['dn'], 3) }} / {{ number_format($misclosure['de'], 3) }}</dd>
                    <dt>Linear misclosure</dt><dd>{{ number_format($misclosure['linear'], 3) }} m</dd>
                    <dt>Precision</dt><dd>{{ $misclosure['precision'] ? '1 : ' . number_format($misclosure['precision']) : 'Closes exactly' }}</dd>
                </dl>
                @unless ($closed)<div class="hint">Closure is meaningful once the last reading returns to the first station.</div>@endunless
            @else
                <div class="hint">No readings yet.</div>
            @endif
        </div>

        <div class="card card-pad" style="margin-bottom:12px;">
            <strong>Area (List of Coordinates)</strong>
            @if ($shape['area'] !== null)
                <dl class="kv" style="margin-top:8px;">
                    <dt>Beacons</dt><dd>{{ $coords->count() }}</dd>
                    <dt>Area</dt><dd>{{ number_format($shape['area'], 2) }} m² · {{ number_format($shape['area'] / 10000, 4) }} ha</dd>
                    <dt>Perimeter</dt><dd>{{ number_format($shape['perimeter'], 3) }} m</dd>
                </dl>
            @else
                <div class="hint">Three or more beacons are needed for an area.</div>
            @endif
        </div>

        <div class="card card-pad">
            <strong>Readings against the coordinates</strong>
            @forelse ($readings->filter(fn ($o) => isset($checks[$o->id])) as $o)
                @php $c = $checks[$o->id]; $bad = abs($c['distance_diff'] ?? 0) > 0.05 || abs($c['bearing_diff'] ?? 0) > 60; @endphp
                <div class="reading">
                    <div><strong>{{ $o->from_station }} → {{ $o->to_station }}</strong>
                        <div class="mono">obs {{ G::dms($o->observed_bearing) }} · {{ number_format($o->observed_distance, 3) }} m</div>
                        <div class="mono">calc {{ G::dms($c['bearing']) }} · {{ number_format((float) $c['distance'], 3) }} m</div></div>
                    <div style="text-align:right;"><span class="st {{ $bad ? 'warn' : 'ok' }}">{{ sprintf('%+.0f″', $c['bearing_diff'] ?? 0) }} · {{ sprintf('%+.3f m', $c['distance_diff'] ?? 0) }}</span></div>
                </div>
            @empty
                <div class="hint">Shown where both stations of a reading are on the List of Coordinates.</div>
            @endforelse
        </div>
    </section>

    {{-- ============================ Sketch Plan ============================= --}}
    <section class="pane" data-pane="sketch" hidden>
        @php $current = $sketches->firstWhere('is_superseded', 0) ?? $sketches->first(); @endphp
        <div class="card card-pad" style="margin-bottom:12px;">
            <strong>Sketch Plan</strong>
            @if ($current)
                <div style="margin-top:6px;font-size:14px;">{{ $current->original_name }} · {{ \Illuminate\Support\Carbon::parse($current->created_at)->format('d M Y') }}{{ $current->uploaded_by_name ? ' · ' . $current->uploaded_by_name : '' }}</div>
                <div class="hint">Filed in {{ $current->file_number }}'s EDMS folder (Survey Registry).</div>
            @else
                <div class="hint">No Sketch Plan yet.</div>
            @endif
        </div>
        @unless ($anchor['locked'])
            <form class="card card-pad" method="POST" enctype="multipart/form-data" id="sketchForm"
                  action="{{ route('survey-module.mobile.observations.sketch.store', $anchor['key']) }}">
                @csrf
                <div class="field"><label for="sk">{{ $current ? 'Replace the Sketch Plan' : 'Upload the Sketch Plan' }}</label>
                    <input class="input" id="sk" type="file" name="sketch_plan" accept="image/jpeg,image/png,application/pdf" required></div>
                <div class="hint">Take a photo of the sketch or choose a PDF, JPG or PNG. It needs a signal: it is filed straight into EDMS{{ $p['file_number'] ? ' for ' . $p['file_number'] : '' }}.</div>
                <div class="err-box" id="sketchErr" hidden>No signal: upload the Sketch Plan when you are back online.</div>
                <button type="submit" class="btn btn-primary" style="margin-top:12px;width:100%;"><i class="fas fa-upload"></i> Upload Sketch Plan</button>
            </form>
        @endunless
    </section>
</main>

@include('survey_module.mobile._footer', ['activeTab' => 'readings'])
<script>
(function () {
    // Tabs: one page (so one copy kept offline), the tab in the URL hash.
    var buttons = document.querySelectorAll('.tabs button'), panes = document.querySelectorAll('.pane');
    function open(name) {
        if (!document.querySelector('[data-pane="' + name + '"]')) name = 'readings';
        buttons.forEach(function (b) { b.classList.toggle('on', b.dataset.tab === name); b.setAttribute('aria-selected', b.dataset.tab === name); });
        panes.forEach(function (p) { p.hidden = p.dataset.pane !== name; });
    }
    buttons.forEach(function (b) { b.addEventListener('click', function () { history.replaceState(null, '', location.pathname + '#' + b.dataset.tab); open(b.dataset.tab); }); });
    open((location.hash || '#readings').slice(1));

    var sketch = document.getElementById('sketchForm');
    if (sketch) sketch.addEventListener('submit', function (ev) {
        if (!navigator.onLine) { ev.preventDefault(); document.getElementById('sketchErr').hidden = false; }
    });

    function row(list, emptyId, title, line, queued) {
        var none = document.getElementById(emptyId); if (none) none.remove();
        var el = document.createElement('div');
        el.className = 'reading';
        el.innerHTML = '<div><strong></strong><div class="mono"></div></div><div style="font-size:12px;text-align:right;"></div>';
        el.querySelector('strong').textContent = title;
        el.querySelector('.mono').textContent = line;
        var tag = el.lastChild; tag.textContent = queued ? 'Saved on phone · will sync' : 'Saved'; if (queued) tag.className = 'pending';
        list.appendChild(el);
    }

    // Readings.
    var form = document.getElementById('readingForm');
    if (form) {
        var err = document.getElementById('readingErr'), btn = document.getElementById('readingSave'), list = document.getElementById('readingList');
        var show = function (msg) { err.textContent = msg; err.hidden = !msg; };
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
                var d = form.bearing_d.value, m = form.bearing_m.value || '0', s = form.bearing_s.value || '0';
                row(list, 'noReadings', label, d + '°' + String(m).padStart(2, '0') + "'" + s + '" · ' + Number(form.observed_distance.value).toFixed(3) + ' m', res.queued);
                // The next reading starts where this one ended.
                form.from_station.value = form.to_station.value.toUpperCase();
                ['to_station', 'bearing_d', 'bearing_m', 'bearing_s', 'observed_distance', 'remarks'].forEach(function (n) { form[n].value = ''; });
                form.to_station.focus();
            });
        });
    }

    // Coordinates, with an optional GPS fix.
    var cform = document.getElementById('coordForm');
    if (cform) {
        var cerr = document.getElementById('coordErr'), cbtn = document.getElementById('coordSave'), clist = document.getElementById('coordList'), gpsOut = document.getElementById('gpsOut');
        var cshow = function (msg) { cerr.textContent = msg; cerr.hidden = !msg; };
        document.getElementById('gpsBtn').addEventListener('click', function () {
            if (!navigator.geolocation) { gpsOut.textContent = 'This phone cannot give a position.'; return; }
            gpsOut.textContent = 'Getting the position…';
            navigator.geolocation.getCurrentPosition(function (pos) {
                var c = pos.coords;
                cform.gps_latitude.value = c.latitude.toFixed(7); cform.gps_longitude.value = c.longitude.toFixed(7); cform.gps_accuracy_m.value = Math.round(c.accuracy);
                gpsOut.textContent = c.latitude.toFixed(6) + ', ' + c.longitude.toFixed(6) + ' (±' + Math.round(c.accuracy) + ' m)';
            }, function () { gpsOut.textContent = 'No position: allow location for this app and try again.'; },
               { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
        });
        cform.addEventListener('submit', function (ev) {
            ev.preventDefault();
            cshow('');
            var bid = cform.beacon_id.value.trim().toUpperCase().replace(/\s+/g, ' ');
            if (!bid || cform.northing.value === '' || cform.easting.value === '') { cshow('Give the beacon ID, northing and easting.'); return; }
            var fields = [];
            new FormData(cform).forEach(function (v, k) { if (k !== '_token') fields.push([k, v]); });
            cbtn.disabled = true;
            (window.SCF ? SCF.submit(cform.action, fields, @json($p['its_number']) + ' · beacon ' + bid)
                        : Promise.resolve({ fallback: true })).then(function (res) {
                cbtn.disabled = false;
                if (res.fallback) { cform.submit(); return; }
                if (res.errors) { var k = Object.keys(res.errors)[0]; cshow(res.errors[k][0]); return; }
                row(clist, 'noCoords', bid, 'N ' + Number(cform.northing.value).toFixed(3) + ' · E ' + Number(cform.easting.value).toFixed(3), res.queued);
                ['beacon_id', 'northing', 'easting', 'elevation', 'remarks', 'gps_latitude', 'gps_longitude', 'gps_accuracy_m'].forEach(function (n) { cform[n].value = ''; });
                gpsOut.textContent = 'Recorded. Record GPS again at the next beacon.';
                cform.beacon_id.focus();
            });
        });
    }
})();
</script>
</body>
</html>

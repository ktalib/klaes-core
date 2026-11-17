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
        /* Phone look (user, 2026-11-15): a job header and an icon tab bar, not web pills. */
        .fo-hero { margin: 0 -16px 0; padding: 16px 16px 18px; color: #fff; background: linear-gradient(160deg, #1e1b2e 0%, #3b1a3a 55%, #9d174d 100%); border-radius: 0 0 26px 26px; }
        .fo-hero .kind { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; background: rgba(255,255,255,.14); border-radius: 999px; padding: 4px 10px; }
        .fo-hero .where { margin-top: 10px; font-size: 15px; font-weight: 600; }
        .fo-hero .who { font-size: 13px; opacity: .75; margin-top: 2px; }
        .fo-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 14px; }
        .fo-kpis div { background: rgba(255,255,255,.1); border-radius: 14px; padding: 9px 10px; }
        .fo-kpis b { display: block; font-size: 19px; line-height: 1.1; } .fo-kpis span { font-size: 11.5px; opacity: .75; }
        .tabs { position: sticky; top: calc(env(safe-area-inset-top) + 64px); z-index: 30; display: grid; grid-template-columns: repeat(5, 1fr); gap: 2px; margin: 12px -16px 14px; padding: 6px 8px;
                background: rgba(246,244,249,.96); backdrop-filter: blur(8px); border-bottom: 1px solid #ebe7f1; }
        .tabs button { border: 0; background: transparent; border-radius: 12px; padding: 7px 2px 6px; font: 600 11.5px 'Outfit', sans-serif; color: #6b6480; display: flex; flex-direction: column; align-items: center; gap: 3px; position: relative; }
        .tabs button i { font-size: 18px; }
        .tabs button.on { color: var(--primary-dark); background: #fff; box-shadow: 0 2px 8px rgba(30,27,46,.08); }
        .tabs .n { position: absolute; top: 2px; right: 10%; min-width: 17px; height: 17px; border-radius: 9px; background: var(--primary); color: #fff; font-size: 10.5px; font-weight: 700; display: grid; place-items: center; padding: 0 4px; }
        .tabs .n:empty, .tabs .n[data-z] { display: none; }
        .pane[hidden] { display: none; }
        .kv { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: 14px; }
        .kv dt { color: var(--muted); }
        .kv dd { margin: 0; font-weight: 600; text-align: right; }
        .st { display: inline-block; font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 2px 9px; }
        .st.ok { background: #ecfdf5; color: #047857; }
        .st.warn { background: #fffbeb; color: #92400e; }
        .st.bad { background: #fef2f2; color: #b91c1c; }
        .gps { font-size: 12.5px; color: var(--muted); }
        .bn-pick { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; text-align: left; cursor: pointer; font-weight: 700; }
        .bn-pick .ph { color: #9ca3af; font-weight: 400; }
        .bn-list { max-height: 52dvh; overflow: auto; margin-top: 10px; }
        .bn-opt:disabled { opacity: .5; }
        .bn-src { font-size: 11px; font-weight: 700; border-radius: 999px; padding: 2px 8px; background: #eef2ff; color: #3730a3; margin-left: auto; flex: none; }
        .bn-src.extant { background: #f0fdf4; color: #166534; } .bn-src.other { background: #fdf2f8; color: #9d174d; }
    </style>

    {{-- Confirmations are SweetAlert, never the browser's own pop-up (user, 2026-11-15). --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    <div class="fo-hero">
        <span class="kind"><i class="fas fa-compass-drafting"></i>{{ $isPrivate ? "Surveyor's Report (Private)" : ($anchor['source'] === 'cadastral' ? 'Cadastral I to S' : 'Survey I to S') }}</span>
        @if ($anchor['locked'])<span class="kind" style="background:#fbbf24;color:#451a03;margin-left:4px;">Closed: view only</span>@endif
        <div class="where">{{ implode(' · ', array_filter([$p['plot_no'] ? 'Plot ' . $p['plot_no'] : null, $p['district'], $p['lga'], $p['location'] !== implode(', ', array_filter([$p['district'], $p['lga']])) ? $p['location'] : null])) ?: 'No location recorded' }}</div>
        @if ($p['surveyor_name'])<div class="who"><i class="fas fa-user-gear"></i> {{ $p['surveyor_name'] }}</div>@endif
        <div class="fo-kpis">
            <div><b>{{ $readings->count() }}</b><span>readings</span></div>
            <div><b>{{ $coords->count() }}</b><span>beacons listed</span></div>
            <div><b>{{ $shape['area'] !== null ? number_format($shape['area'], 0) : '—' }}</b><span>{{ $shape['area'] !== null ? 'm² area' : 'area' }}</span></div>
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" data-tab="plot"><i class="fas fa-map-location-dot"></i>Plot</button>
        <button type="button" data-tab="readings"><i class="fas fa-ruler-combined"></i>Readings<span class="n" @if (! $readings->count()) data-z @endif>{{ $readings->count() }}</span></button>
        <button type="button" data-tab="coords"><i class="fas fa-location-dot"></i>Beacons<span class="n" @if (! $coords->count()) data-z @endif>{{ $coords->count() }}</span></button>
        <button type="button" data-tab="compute"><i class="fas fa-square-check"></i>Checks</button>
        <button type="button" data-tab="sketch"><i class="fas fa-file-pen"></i>Sketch</button>
    </div>

    {{-- ================================ Plot ================================ --}}
    <section class="pane" data-pane="plot">
        @include('survey_module.mobile._fo_plot')
    </section>

    {{-- ============================== Readings ============================== --}}
    <section class="pane" data-pane="readings">
        @unless ($anchor['locked'])
            <details style="margin-bottom:14px;"><summary style="font-weight:600;cursor:pointer;padding:6px 0;">Add a single reading (works offline)</summary>
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
            </details>
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
                {{-- Beacon Number (user, 2026-11-15): a dropdown with search, and one Other (specify) for a number not listed. --}}
                <div class="field"><label for="bnPick">Beacon Number <span style="color:var(--err)">*</span></label>
                    <button type="button" class="input bn-pick" id="bnPick" aria-haspopup="dialog">
                        <span id="bnPickTxt" class="ph">Choose the beacon number</span><i class="fas fa-chevron-down"></i></button>
                    <input type="hidden" name="beacon_id" id="bid">
                    <input class="input" id="bnOther" maxlength="50" autocapitalize="characters" placeholder="Specify the beacon number" style="margin-top:8px;text-transform:uppercase;" hidden>
                    <div class="gps" id="bnNote" hidden></div></div>
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
    <section class="pane" data-pane="compute" hidden>
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
        if (!document.querySelector('[data-pane="' + name + '"]')) name = 'plot';
        buttons.forEach(function (b) { b.classList.toggle('on', b.dataset.tab === name); b.setAttribute('aria-selected', b.dataset.tab === name); });
        panes.forEach(function (p) { p.hidden = p.dataset.pane !== name; });
    }
    buttons.forEach(function (b) { b.addEventListener('click', function () { history.replaceState(null, '', location.pathname + '#' + b.dataset.tab); open(b.dataset.tab); }); });
    open((location.hash || '#plot').slice(1));

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
        // ---- Beacon Number dropdown (2026-11-15): search, then one Other (specify) at the foot ----
        var bn = { other: false, filled: false };
        var bnTxt = document.getElementById('bnPickTxt'), bnOther = document.getElementById('bnOther'), bnNote = document.getElementById('bnNote');
        var onList = @json($coords->map(fn ($c) => mb_strtoupper(trim($c->beacon_id)))->values());
        var SEARCH = @json(route('survey-module.mobile.beacon-search')), localReg = null;
        var bnKey = function (s) { return String(s || '').trim().toUpperCase().replace(/\s+/g, ' '); };
        function bnSet(no, other, x, y) {
            // A new pick clears the X / Y the last pick filled in.
            if (bn.filled) { cform.easting.value = ''; cform.northing.value = ''; bn.filled = false; }
            bn.other = !!other;
            cform.beacon_id.value = other ? bnKey(bnOther.value) : bnKey(no);
            bnTxt.textContent = other ? 'Other (specify)' : (no || 'Choose the beacon number');
            bnTxt.className = no || other ? '' : 'ph';
            bnOther.hidden = !other; bnOther.required = !!other; if (other) setTimeout(function () { bnOther.focus(); }, 200); else bnOther.value = '';
            bnNote.hidden = true;
            if (!other && x && y && cform.easting.value === '' && cform.northing.value === '') {
                cform.easting.value = Number(x).toFixed(3); cform.northing.value = Number(y).toFixed(3); bn.filled = true;
                bnNote.hidden = false; bnNote.textContent = 'Easting / Northing filled from the Beacon Register. Check them against the plan.';
            }
        }
        bnOther.addEventListener('input', function () { bnOther.value = bnOther.value.toUpperCase(); cform.beacon_id.value = bnKey(bnOther.value); });
        function localSearch(q) {
            var go = function () {
                var k = bnKey(q).replace(/ /g, '');
                return (localReg || []).filter(function (r) { return String(r[0]).toUpperCase().replace(/ /g, '').indexOf(k) === 0; }).slice(0, 30)
                    .map(function (r) { return { beacon_no: String(r[0]), x: r[1], y: r[2], source: 'register' }; });
            };
            if (localReg) return Promise.resolve(go());
            return fetch('/maps/data/beacons.json').then(function (r) { return r.json(); }).then(function (j) { localReg = j.rows; return go(); }).catch(function () { return []; });
        }
        function bnSheet() {
            var bg = document.getElementById('bnBg'), sh = document.getElementById('bnSheet');
            if (!sh) {
                bg = document.createElement('div'); bg.id = 'bnBg'; bg.className = 'fo-sheet-bg';
                sh = document.createElement('div'); sh.id = 'bnSheet'; sh.className = 'fo-sheet'; sh.setAttribute('role', 'dialog');
                sh.innerHTML = '<div class="grab"></div><button type="button" class="fo-x" aria-label="Close"><i class="fas fa-xmark"></i></button>'
                    + '<h3>Beacon Number</h3><div class="sub">Search the issued beacon numbers, the Extant Beacons list and the Beacon Register.</div>'
                    + '<input class="fo-txt" id="bnQ" placeholder="Type to search, e.g. A0876A" autocomplete="off"><div class="bn-list" id="bnList"></div>';
                document.body.appendChild(bg); document.body.appendChild(sh);
                var close = function () { sh.classList.remove('open'); bg.classList.remove('open'); };
                bg.onclick = close; sh.querySelector('.fo-x').onclick = close; sh._close = close;
                var q = sh.querySelector('#bnQ'), list = sh.querySelector('#bnList'), seq = 0, t = null;
                var srcLabel = { captured: 'Issued', extant: 'Extant list', register: 'Register' };
                var render = function (rows, offline) {
                    list.innerHTML = '';
                    if (offline) { var n = document.createElement('div'); n.className = 'fo-note'; n.textContent = 'No signal: showing the Beacon Register kept on the phone.'; list.appendChild(n); }
                    if (!rows.length) { var e = document.createElement('div'); e.className = 'fo-note'; e.textContent = 'No beacon number matches. Use Other (specify) below.'; list.appendChild(e); }
                    rows.forEach(function (b) {
                        var taken = onList.indexOf(bnKey(b.beacon_no)) !== -1;
                        var o = document.createElement('button'); o.type = 'button'; o.className = 'fo-opt bn-opt'; o.disabled = taken;
                        o.innerHTML = '<i class="fas fa-map-pin"></i><span><b></b><small></small></span><span class="bn-src"></span>';
                        o.querySelector('b').textContent = b.beacon_no;
                        o.querySelector('small').textContent = taken ? 'Already on this job\'s list' : (b.x && b.y ? 'E ' + Number(b.x).toFixed(2) + ' · N ' + Number(b.y).toFixed(2) : 'No X / Y on record');
                        var s = o.querySelector('.bn-src'); s.textContent = srcLabel[b.source] || 'Register'; if (b.source === 'extant') s.classList.add('extant');
                        o.onclick = function () { bnSet(b.beacon_no, false, b.x, b.y); close(); };
                        list.appendChild(o);
                    });
                    var oth = document.createElement('button'); oth.type = 'button'; oth.className = 'fo-opt';
                    oth.innerHTML = '<i class="fas fa-pen"></i><span><b>Other (specify)</b><small>A beacon number not listed: type it</small></span><span class="bn-src other">Other</span>';
                    oth.onclick = function () { if (q.value.trim()) bnOther.value = bnKey(q.value); bnSet('', true); cform.beacon_id.value = bnKey(bnOther.value); close(); };
                    list.appendChild(oth);
                };
                var run = function () {
                    var mine = ++seq, term = q.value;
                    var online = navigator.onLine ? fetch(SEARCH + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { if (!r.ok) throw 0; return r.json(); }).then(function (j) { return { rows: j.data || [], offline: false }; }) : Promise.reject(0);
                    online.catch(function () { return localSearch(term).then(function (rows) { return { rows: rows, offline: true }; }); })
                        .then(function (res) { if (mine === seq) render(res.rows, res.offline); });
                };
                q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 250); });
                sh._run = run; sh._q = q;
            }
            sh._q.value = ''; sh._run();
            sh.classList.add('open'); bg.classList.add('open');
            setTimeout(function () { sh._q.focus(); }, 250);
        }
        document.getElementById('bnPick').addEventListener('click', bnSheet);

        cform.addEventListener('submit', function (ev) {
            ev.preventDefault();
            cshow('');
            if (bn.other) cform.beacon_id.value = bnKey(bnOther.value);
            var bid = cform.beacon_id.value.trim().toUpperCase().replace(/\s+/g, ' ');
            if (bn.other && !bid) { cshow('Specify the beacon number.'); bnOther.focus(); return; }
            if (!bid || cform.northing.value === '' || cform.easting.value === '') { cshow('Give the beacon number, northing and easting.'); return; }
            if (onList.indexOf(bid) !== -1) { cshow('Beacon ' + bid + ' is already on this list.'); return; }
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
                onList.push(bid); bn.filled = false; bnSet('', false);
                gpsOut.textContent = 'Recorded. Record GPS again at the next beacon.';
            });
        });
    }
})();
</script>
@php
    $foCfg = [
        'its' => $anchor['key'], 'its_no' => $p['its_number'], 'locked' => (bool) $anchor['locked'],
        'today' => now()->toDateString(), 'csrf' => csrf_token(),
        'saveUrl' => route('survey-module.mobile.observations.traverse.store', $anchor['key']),
        'after' => route('survey-module.mobile.observations.show', ['its' => $anchor['key'], 'app' => \App\Support\SurveyMobileApps::current()]) . '#coords',
        'coords' => $coords->map(fn ($c) => ['b' => $c->beacon_id, 'e' => (float) $c->easting, 'n' => (float) $c->northing])->values(),
    ];
@endphp
<script type="application/json" id="foCfg">@json($foCfg)</script>
<link rel="stylesheet" href="{{ asset('maps/vendor/maplibre-gl.css') }}">
<script src="{{ asset('maps/vendor/maplibre-gl.js') }}"></script>
<script src="{{ asset('maps/vendor/pmtiles.js') }}"></script>
<script src="{{ asset('maps/vendor/proj4.js') }}"></script>
<script src="{{ asset('js/fo-field.js') }}?v={{ filemtime(public_path('js/fo-field.js')) }}"></script>
</body>
</html>

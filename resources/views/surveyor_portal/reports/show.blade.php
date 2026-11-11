@extends('surveyor_portal.layouts.portal')

@section('title', $report->report_ref . " — Surveyor's Report — MLPP-SG Portal")
@section('heading', "Surveyor's Report " . $report->report_ref)

@include('surveyor_portal.reports._assets')

@push('styles')
<style>
    .ks-rep-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
    .ks-rep-meta { display:flex; flex-wrap:wrap; gap:8px; align-items:center; font-size:13px; color:var(--ks-soft); }
    .ks-rep-band { display:flex; align-items:center; gap:10px; margin:22px 0 10px; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
    .ks-rep-band.b1 { background:#EAF1FB; color:#1D4F91; }
    .ks-rep-band.b2 { background:#EAF6EE; color:#167044; }
    .ks-rep-band.b3 { background:#FBF4E2; color:#8A5A00; }
    .ks-rep-band small { margin-left:auto; font-weight:600; letter-spacing:0; text-transform:none; }
    .ks-lines td { vertical-align:middle; }
    .ks-lines .ks-input { min-width:90px; padding:8px 10px; }
    .ks-sum { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:10px; margin-bottom:12px; }
    .ks-sum div { padding:12px 14px; border:1px solid var(--ks-line); border-radius:10px; background:#fff; }
    .ks-sum strong { display:block; font-size:20px; font-variant-numeric:tabular-nums; }
    .ks-sum span { color:var(--ks-soft); font-size:12px; }
    .ks-menu { position:relative; display:inline-block; }
    .ks-menu-btn { display:grid; place-items:center; width:32px; height:32px; border:1px solid var(--ks-line); border-radius:8px; background:#fff; cursor:pointer; }
    .ks-menu-list { position:absolute; right:0; top:36px; z-index:20; min-width:170px; padding:6px; border:1px solid var(--ks-line); border-radius:10px; background:#fff; box-shadow:0 12px 28px rgba(20,23,26,.14); }
    .ks-menu-list button, .ks-menu-list a { display:flex; width:100%; align-items:center; gap:8px; padding:8px 10px; border:0; border-radius:7px; background:none; color:var(--ks-ink); font-size:13px; text-align:left; text-decoration:none; cursor:pointer; }
    .ks-menu-list button:hover, .ks-menu-list a:hover { background:#F3F6FA; }
    .ks-menu-list .danger { color:var(--ks-danger); }
</style>
@endpush

@section('main')
    @php
        $statusLabel = $report->isIssued() ? 'Issued' : ($report->submitted_at ? 'Submitted' : 'Draft');
        $badge = ['Draft' => 'submitted', 'Submitted' => 'issued', 'Issued' => 'accepted'][$statusLabel];
        $D = fn ($n, $p = 3) => $n === null ? '—' : number_format((float) $n, $p);
    @endphp

    <div class="ks-rep-head">
        <div class="ks-rep-meta">
            <span class="ks-badge {{ $badge }}">{{ $statusLabel }}</span>
            @if ($report->its_number)<span>I to S {{ $report->its_number }}</span>@endif
            @if ($report->portal_beacons)<span>Beacons: {{ $report->portal_beacons }}</span>@endif
            @if ($report->submitted_at)<span>Submitted {{ $report->submitted_at->format('d/m/Y H:i') }}</span>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.reports.print', $report->id) }}" target="_blank" rel="noopener"><i data-lucide="printer" class="w-4 h-4"></i> Print</a>
            @if ($editable)
                <form method="POST" action="{{ route('surveyor-portal.reports.submit', $report->id) }}" onsubmit="return confirm('Submit {{ $report->report_ref }} to the Office of the Surveyor General? You cannot change it afterwards.');">
                    @csrf
                    <button class="ks-btn ks-btn-green"><i data-lucide="send" class="w-4 h-4"></i> Submit report</button>
                </form>
            @endif
        </div>
    </div>
    @error('submit')<div class="ks-flash bad" role="alert">{{ $message }}</div>@enderror
    @error('report')<div class="ks-flash bad" role="alert">{{ $message }}</div>@enderror
    @if ($pending && $editable)
        <div class="ks-flash" style="background:#FFF7E0;color:#6B4A00;">Enter the coordinates of {{ implode(', ', $pending) }} in the List of Coordinates below.</div>
    @endif

    <details @if (! $coords->count() || $errors->hasAny(['district', 'lga', 'completed_on', 'layout_plan_no', 'encroachment_details', 'developed_with', 'beacons_reason', 'district_other'])) open @endif style="margin-bottom:6px;">
        <summary class="ks-btn ks-btn-light" style="list-style:none;margin-bottom:12px;"><i data-lucide="file-text" class="w-4 h-4"></i> Report details</summary>
        <form method="POST" action="{{ route('surveyor-portal.reports.update', $report->id) }}">
            @csrf
            @method('PUT')
            @include('surveyor_portal.reports._fields', ['lock' => ! $editable, 'fileLocked' => (bool) $report->its_key])
            @if ($editable)
                <div class="flex justify-end"><button class="ks-btn ks-btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save details</button></div>
            @endif
        </form>
    </details>

    {{-- 1. Beacon Nos --}}
    @php $beaconCoords = $coords->filter(fn ($c) => in_array(strtoupper(preg_replace('/\s+/', '', (string) $c->beacon_id)), $issuedKeys, true))->values(); @endphp
    <div class="ks-rep-band b1"><i data-lucide="radio-tower" class="w-4 h-4"></i> Beacon Nos <small>{{ $beaconCoords->count() }} on the list</small></div>
    <div class="ks-card overflow-x-auto">
        <table class="ks-table">
            <thead><tr><th>S/N</th><th>Beacon No.</th><th>Issued to you</th><th>Easting (X)</th><th>Northing (Y)</th></tr></thead>
            <tbody>
                @forelse ($beaconCoords as $c)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="font-semibold ks-num">{{ \App\Models\Survey\SurveyBeaconRegister::display($c->beacon_id) }}</td>
                        <td><span class="ks-badge accepted">Yes · Used</span></td>
                        <td class="ks-num">{{ $D($c->easting) }}</td>
                        <td class="ks-num">{{ $D($c->northing) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ks-help" style="text-align:center;padding:18px;">No beacon on the List of Coordinates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 2. List of Coordinates --}}
    <div class="ks-rep-band b2"><i data-lucide="map-pin" class="w-4 h-4"></i> List of Coordinates <small>{{ $coords->count() }} points</small></div>
    <div class="ks-card overflow-x-auto">
        <table class="ks-table">
            <thead><tr><th>S/N</th><th>Beacon / Point</th><th>Northing (Y)</th><th>Easting (X)</th><th>Height (Z)</th><th>Remarks</th>@if ($editable)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($coords as $c)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="font-semibold ks-num">{{ \App\Models\Survey\SurveyBeaconRegister::display($c->beacon_id) }}</td>
                        <td class="ks-num">{{ $D($c->northing) }}</td>
                        <td class="ks-num">{{ $D($c->easting) }}</td>
                        <td class="ks-num">{{ $D($c->elevation) }}</td>
                        <td>{{ $c->remarks ?: '—' }}</td>
                        @if ($editable)
                            <td style="text-align:right;">
                                <div class="ks-menu">
                                    <button type="button" class="ks-menu-btn" aria-label="Actions" aria-haspopup="menu" aria-expanded="false"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                                    <div class="ks-menu-list" role="menu" hidden>
                                        <form method="POST" action="{{ route('surveyor-portal.reports.coordinates.destroy', [$report->id, $c->id]) }}" onsubmit="return confirm('Remove {{ $c->beacon_id }} from the list? A beacon goes back to unused.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="danger" role="menuitem"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="ks-help" style="text-align:center;padding:18px;">No coordinates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($editable)
        <form method="POST" action="{{ route('surveyor-portal.reports.coordinates.store', $report->id) }}" class="ks-card p-4 mt-3" id="coordForm">
            <input type="hidden" name="_grid" value="coord">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <strong style="font-size:14px;">Add coordinates</strong>
                <button type="button" class="ks-btn ks-btn-light" data-add-line="coord" style="padding:7px 12px;"><i data-lucide="plus" class="w-4 h-4"></i> Add line</button>
            </div>
            @if (old('_grid') === 'coord' && $errors->any())
                <div class="ks-flash bad" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
            <div class="overflow-x-auto">
                <table class="ks-table ks-lines" style="min-width:860px;">
                    <thead><tr><th>Beacon / Point <span class="ks-req">*</span></th><th>Northing (Y) <span class="ks-req">*</span></th><th>Easting (X) <span class="ks-req">*</span></th><th>Height (Z)</th><th>Remarks</th><th></th></tr></thead>
                    <tbody data-lines="coord"></tbody>
                </table>
            </div>
            <template id="coordLine">
                <tr>
                    <td>
                        <select class="ks-input" data-name="beacon" required>
                            <option value="">Select…</option>
                            @foreach ($beacons as $b)<option value="{{ $b->beacon_no }}">{{ \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no) }}</option>@endforeach
                            <option value="other">Other point (specify)</option>
                        </select>
                        <input class="ks-input" data-name="beacon_other" maxlength="50" placeholder="Specify point *" style="margin-top:6px;" hidden disabled>
                    </td>
                    <td><input class="ks-input" type="number" step="any" data-name="northing" required></td>
                    <td><input class="ks-input" type="number" step="any" data-name="easting" required></td>
                    <td><input class="ks-input" type="number" step="any" data-name="elevation"></td>
                    <td><input class="ks-input" data-name="remarks" maxlength="500"></td>
                    <td><button type="button" class="ks-btn ks-btn-light" data-remove-line aria-label="Remove line" style="padding:6px 8px;"><i data-lucide="x" class="w-4 h-4"></i></button></td>
                </tr>
            </template>
            <p class="ks-help" style="margin-top:6px;">A beacon saved here becomes Used and moves to your Beacon Register. Other points (control stations and the like) are listed but not counted as beacons.</p>
            <div class="flex justify-end mt-2"><button class="ks-btn ks-btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save coordinates</button></div>
        </form>
    @endif

    {{-- 3. Computation & Field Observations --}}
    <div class="ks-rep-band b3"><i data-lucide="calculator" class="w-4 h-4"></i> Computation &amp; Field Observations <small>{{ $rec['readings'] }} readings</small></div>
    <div class="ks-sum">
        <div><strong>{{ $rec['area'] !== null ? number_format($rec['area'], 2) : '—' }}</strong><span>Area (m²)</span></div>
        <div><strong>{{ $rec['perimeter'] !== null ? number_format($rec['perimeter'], 2) : '—' }}</strong><span>Perimeter (m)</span></div>
        <div><strong>{{ $rec['readings'] }}</strong><span>Readings{{ $rec['closed'] ? ' · closed' : '' }}</span></div>
        <div><strong>{{ $rec['misclosure'] !== null ? number_format($rec['misclosure'], 3) : '—' }}</strong><span>Misclosure (m)</span></div>
    </div>
    <div class="ks-card overflow-x-auto">
        <table class="ks-table">
            <thead><tr><th>S/N</th><th>From</th><th>To</th><th>Bearing</th><th>Distance (m)</th><th>Date observed</th><th>Observer</th><th>Remarks</th>@if ($editable)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($observations as $o)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="ks-num">{{ $o->from_station }}</td>
                        <td class="ks-num">{{ $o->to_station }}</td>
                        <td class="ks-num">{{ \App\Support\SurveyGeometry::dms($o->observed_bearing) }}</td>
                        <td class="ks-num">{{ $D($o->observed_distance) }}</td>
                        <td>{{ $o->observed_on ? $o->observed_on->format('d/m/Y') : '—' }}</td>
                        <td>{{ $o->observer ?: '—' }}</td>
                        <td>{{ $o->remarks ?: '—' }}</td>
                        @if ($editable)
                            <td style="text-align:right;">
                                <div class="ks-menu">
                                    <button type="button" class="ks-menu-btn" aria-label="Actions" aria-haspopup="menu" aria-expanded="false"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                                    <div class="ks-menu-list" role="menu" hidden>
                                        <form method="POST" action="{{ route('surveyor-portal.reports.observations.destroy', [$report->id, $o->id]) }}" onsubmit="return confirm('Remove this reading?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="danger" role="menuitem"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9" class="ks-help" style="text-align:center;padding:18px;">No field observations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($editable)
        <form method="POST" action="{{ route('surveyor-portal.reports.observations.store', $report->id) }}" class="ks-card p-4 mt-3" id="obsForm">
            <input type="hidden" name="_grid" value="obs">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <strong style="font-size:14px;">Add field observations</strong>
                <button type="button" class="ks-btn ks-btn-light" data-add-line="obs" style="padding:7px 12px;"><i data-lucide="plus" class="w-4 h-4"></i> Add line</button>
            </div>
            @if (old('_grid') === 'obs' && $errors->any())
                <div class="ks-flash bad" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
            <div class="grid sm:grid-cols-2 gap-3 mb-2" style="max-width:520px;">
                <div><label class="ks-label" for="obsDate">Date observed</label><input class="ks-input" id="obsDate" type="date" name="observed_on" max="{{ now()->format('Y-m-d') }}"></div>
                <div><label class="ks-label" for="obsBy">Observer</label><input class="ks-input" id="obsBy" name="observer" maxlength="255" value="{{ $report->surveyor_name }}"></div>
            </div>
            <div class="overflow-x-auto">
                <table class="ks-table ks-lines" style="min-width:860px;">
                    <thead><tr><th>From <span class="ks-req">*</span></th><th>To <span class="ks-req">*</span></th><th>Bearing ° <span class="ks-req">*</span></th><th>′</th><th>″</th><th>Distance (m) <span class="ks-req">*</span></th><th>Remarks</th><th></th></tr></thead>
                    <tbody data-lines="obs"></tbody>
                </table>
            </div>
            <template id="obsLine">
                <tr>
                    <td><input class="ks-input" data-name="from_station" maxlength="50" required></td>
                    <td><input class="ks-input" data-name="to_station" maxlength="50" required></td>
                    <td><input class="ks-input" type="number" min="0" max="359" data-name="bearing_d" required style="min-width:70px;"></td>
                    <td><input class="ks-input" type="number" min="0" max="59" data-name="bearing_m" style="min-width:60px;"></td>
                    <td><input class="ks-input" type="number" min="0" max="59.999" step="any" data-name="bearing_s" style="min-width:60px;"></td>
                    <td><input class="ks-input" type="number" step="any" min="0" data-name="observed_distance" required></td>
                    <td><input class="ks-input" data-name="remarks" maxlength="500"></td>
                    <td><button type="button" class="ks-btn ks-btn-light" data-remove-line aria-label="Remove line" style="padding:6px 8px;"><i data-lucide="x" class="w-4 h-4"></i></button></td>
                </tr>
            </template>
            <div class="flex justify-end mt-2"><button class="ks-btn ks-btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save readings</button></div>
        </form>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    // Line grids: each line's inputs are named lines[n][field] when added.
    var counters = { coord: 0, obs: 0 };
    function addLine(kind, beacon, values) {
        var tpl = document.getElementById(kind === 'coord' ? 'coordLine' : 'obsLine');
        var body = document.querySelector('[data-lines="' + kind + '"]');
        if (!tpl || !body) return;
        var row = tpl.content.firstElementChild.cloneNode(true), n = counters[kind]++;
        row.querySelectorAll('[data-name]').forEach(function (el) { el.name = 'lines[' + n + '][' + el.dataset.name + ']'; });
        var sel = row.querySelector('[data-name="beacon"]'), other = row.querySelector('[data-name="beacon_other"]');
        if (values) row.querySelectorAll('[data-name]').forEach(function (el) {
            if (values[el.dataset.name] !== undefined && values[el.dataset.name] !== null) el.value = values[el.dataset.name];
        });
        if (sel) {
            if (beacon) sel.value = beacon;
            var syncOther = function () {
                var on = sel.value === 'other';
                other.hidden = !on; other.disabled = !on; other.required = on;
            };
            sel.addEventListener('change', syncOther);
            syncOther();
        }
        row.querySelector('[data-remove-line]').addEventListener('click', function () {
            if (body.children.length > 1) row.remove();
        });
        body.appendChild(row);
        if (window.lucide) lucide.createIcons();
    }
    document.querySelectorAll('[data-add-line]').forEach(function (b) {
        b.addEventListener('click', function () { addLine(b.dataset.addLine); });
    });
    // Picked beacons with no coordinate yet open as lines to fill.
    // After a refused save, the lines come back as typed.
    var pending = @json($editable ? $pending : []);
    var oldGrid = @json(old('_grid')), oldLines = @json(array_values((array) old('lines', [])));
    if (document.querySelector('[data-lines="coord"]')) {
        if (oldGrid === 'coord' && oldLines.length) oldLines.forEach(function (l) { addLine('coord', null, l); });
        else if (pending.length) pending.forEach(function (b) { addLine('coord', b); });
        else addLine('coord');
    }
    if (document.querySelector('[data-lines="obs"]')) {
        if (oldGrid === 'obs' && oldLines.length) oldLines.forEach(function (l) { addLine('obs', null, l); });
        else addLine('obs');
    }

    // Row action menus: open on the three dots, close on outside click or Escape.
    function closeAll(except) {
        document.querySelectorAll('.ks-menu-list').forEach(function (m) {
            if (m === except) return;
            m.hidden = true;
            var btn = m.parentElement.querySelector('.ks-menu-btn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ks-menu-btn');
        if (btn) {
            e.stopPropagation();
            var list = btn.parentElement.querySelector('.ks-menu-list');
            var open = list.hidden;
            closeAll(list);
            list.hidden = !open;
            if (open) {
                // Placed against the window so a scrolling table never clips it.
                var rc = btn.getBoundingClientRect();
                list.style.position = "fixed";
                list.style.top = (rc.bottom + 4) + "px";
                list.style.left = Math.max(8, rc.right - list.offsetWidth) + "px";
                list.style.right = "auto";
            }
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        if (!e.target.closest('.ks-menu-list')) closeAll();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
    window.addEventListener('scroll', function () { closeAll(); }, true);
})();
</script>
@endpush

@extends('survey_module.layouts.klaes')

@section('page-title', 'Plot Showing Register — KLAES')

@php
  use App\Models\Survey\SurveyOpShowing as Showing;
  use App\Models\Survey\SurveyPlotCheck as Check;

  // Opened from Records > Plot Showing Register (user, 2026-11-02).
  $PageTitle = 'Plot Showing Register';
  $PageDescription = 'OP plots shown to their allottees (Showing History) and ground checks of plots against their layout and the Beacon Register: recorded here or with the Plot Confirmation app.';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $tabUrl = fn ($t) => route('survey-module.compensation.plot-confirmations', ['tab' => $t]);
  $beaconCls = ['registered' => 'completed', 'other_surveyor' => 'pending', 'unregistered' => 'rejected'];
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

{{-- Record Plot Showing (user, 2026-11-02): the same record the Plot Confirmation app makes. --}}
@php $pcOpen = $errors->any() && old('_form') === 'showing'; @endphp
<form method="POST" action="{{ route('survey-module.compensation.plot-confirmations.showings.store') }}" enctype="multipart/form-data"
      id="pcShowForm" class="farmer-entry-form {{ $pcOpen ? 'open' : '' }}" data-sv-modal="Record Plot Showing" data-sv-modal-size="lg" data-sv-modal-static
      data-search="{{ route('survey-module.compensation.plot-confirmations.op-search') }}">
    @csrf
    <input type="hidden" name="_form" value="showing">
    <input type="hidden" name="op" id="pcOp" value="{{ old('op') }}">
    @if ($pcOpen)
        <div class="comp-type-note" style="margin-bottom:14px;border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
        </div>
    @endif

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-file-signature"></i><span>OP</span></div>
        <div class="form-group span-2" style="position:relative;">
            <label>Find the OP <span class="required">*</span></label>
            <input type="text" id="pcOpSearch" autocomplete="off" placeholder="OP serial, allottee, plot no. or file no." value="{{ old('op_label') }}">
            <input type="hidden" name="op_label" id="pcOpLabel" value="{{ old('op_label') }}">
            <div id="pcOpResults" style="display:none;position:absolute;z-index:60;left:0;right:0;top:100%;max-height:260px;overflow:auto;background:#fff;border:1px solid var(--gray-200);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);"></div>
            <p class="helper-text">OPs from Deeds. Type at least 2 characters.</p>
        </div>
        <div class="form-group">
            <label>Showing</label>
            <input type="text" id="pcNext" readonly tabindex="-1" style="background:var(--gray-100);font-weight:700;" value="{{ old('op') ? '' : '—' }}">
        </div>
        {{-- Property order: Plot No., District, LGA, Location (backfilled from the OP). --}}
        <div class="form-group"><label>Allottee</label><input type="text" id="pcAllottee" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>Plot No.</label><input type="text" id="pcPlot" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>District</label><input type="text" id="pcDistrict" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>LGA</label><input type="text" id="pcLga" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group span-2"><label>Location</label><input type="text" id="pcLocation" readonly tabindex="-1" style="background:var(--gray-100);"></div>
    </div>

    {{-- Who asked for the showing (user, 2026-11-02). Ticked: the name is the allottee's. The address
         is the full address from the address builder (req_*), stored composed in requester_address. --}}
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-user"></i><span>Requester</span></div>
        <div class="form-group full" style="margin-bottom:0;">
            <input type="hidden" name="requester_is_allottee" value="0">
            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:500;cursor:pointer;">
                <input type="checkbox" name="requester_is_allottee" value="1" id="pcReqSame" style="width:auto;" @checked(old('requester_is_allottee') === '1')>
                Requester is the allottee
            </label>
        </div>
        <div class="form-group span-2">
            <label>Requester's Name <span class="required">*</span></label>
            <input type="text" name="requester_name" id="pcReqName" maxlength="255" value="{{ old('requester_name') }}" required>
            <p class="helper-text" id="pcReqHint"></p>
        </div>
    </div>
    @include('survey_module.partials._address_builder', [
        'prefix' => 'req_',
        'mode'   => 'person',
        'model'  => null,
        'legend' => "Requester's Address",
    ])

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-street-view"></i><span>Showing</span></div>
        <div class="form-group">
            <label>Date <span class="required">*</span></label>
            <input type="date" name="shown_on" value="{{ old('shown_on', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
        </div>
        <div class="form-group">
            <label>Time <span class="required">*</span></label>
            <input type="time" name="shown_at" value="{{ old('shown_at', now()->format('H:i')) }}" required>
        </div>
        <div class="form-group">
            <label>Was the allottee there? <span class="required">*</span></label>
            <select name="allottee_present" required>
                <option value="">— Select —</option>
                <option value="1" @selected(old('allottee_present') === '1')>Yes</option>
                <option value="0" @selected(old('allottee_present') === '0')>No</option>
            </select>
        </div>
        <div class="form-group" id="pcReasonBox" style="display:none;">
            <label>Reason <span class="required">*</span></label>
            <select name="reason" id="pcReason">
                <option value="">— Why is it shown again? —</option>
                @foreach (Showing::REASONS as $x)<option value="{{ $x }}" @selected(old('reason') === $x)>{{ $x }}</option>@endforeach
            </select>
            <p class="helper-text">Needed from the 2nd showing on.</p>
        </div>
        <div class="form-group">
            <label>Surveyor <span class="required">*</span></label>
            <select name="surveyor" id="pcSurveyor" required>
                <option value="">— Select surveyor —</option>
                @foreach ($surveyors as $s)
                    <option value="{{ $s->id }}" @selected(old('surveyor') === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>
                @endforeach
                <option value="other" @selected(old('surveyor') === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="surveyor_other" id="pcSurveyorOther" maxlength="255" placeholder="Specify the surveyor's name *"
                   value="{{ old('surveyor_other') }}" style="margin-top:6px;{{ old('surveyor') === 'other' ? '' : 'display:none;' }}">
        </div>
        <div class="form-group">
            <label>Photographs</label>
            <input type="file" name="photos[]" accept="image/jpeg,image/png" multiple>
            <p class="helper-text">Up to 10, JPG or PNG.</p>
        </div>
        <div class="form-group full">
            <label>Notes</label>
            <textarea name="notes" maxlength="2000" rows="2">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="form-actions">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('pcShowForm').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
        <div class="right"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Save Showing</button></div>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('pcShowForm');
    if (!form) return;
    var box = document.getElementById('pcOpSearch'), list = document.getElementById('pcOpResults'), timer = null;
    var set = function (id, v) { var el = document.getElementById(id); if (el) el.value = v || ''; };
    // Requester: ticked fills the name (locked) from the OP's allottee; it is cleared again when
    // unticked or another OP is picked. The allottee's address on record (one line in Deeds) is
    // shown as a guide for the address fields.
    var reqSame = document.getElementById('pcReqSame'), reqName = document.getElementById('pcReqName'),
        reqHint = document.getElementById('pcReqHint'), current = null, filled = {};
    function syncRequester() {
        if (filled.name !== undefined && reqName.value === filled.name) reqName.value = '';
        filled = {};
        reqName.readOnly = reqSame.checked;
        reqName.style.background = reqSame.checked ? 'var(--gray-100)' : '';
        reqHint.textContent = '';
        if (!reqSame.checked) return;
        if (!current) { reqHint.textContent = 'Find the OP first: the name is taken from its allottee.'; return; }
        if (current.allottee) { reqName.value = filled.name = current.allottee; }
        else { reqName.readOnly = false; reqName.style.background = ''; reqHint.textContent = 'This OP has no allottee name on record: type it.'; }
        if (current.address) reqHint.textContent = (reqHint.textContent ? reqHint.textContent + ' ' : '') + "Allottee's address on record: " + current.address + '.';
    }
    reqSame.addEventListener('change', syncRequester);
    function pick(op) {
        current = op;
        set('pcOp', op.key); set('pcOpLabel', 'OP ' + (op.serial || '—') + ' · ' + (op.allottee || ''));
        box.value = document.getElementById('pcOpLabel').value;
        set('pcNext', op.next_label); set('pcAllottee', op.allottee); set('pcPlot', op.plot_no);
        set('pcDistrict', op.district); set('pcLga', op.lga); set('pcLocation', op.location);
        var again = op.next > 1;
        document.getElementById('pcReasonBox').style.display = again ? '' : 'none';
        document.getElementById('pcReason').required = again;
        if (!again) document.getElementById('pcReason').value = '';
        list.style.display = 'none';
        syncRequester();
    }
    function clearPick() {
        current = null;
        ['pcOp', 'pcOpLabel', 'pcAllottee', 'pcPlot', 'pcDistrict', 'pcLga', 'pcLocation'].forEach(function (id) { set(id, ''); });
        set('pcNext', '—');
        document.getElementById('pcReasonBox').style.display = 'none';
        document.getElementById('pcReason').required = false;
        syncRequester();
    }
    box.addEventListener('input', function () {
        clearTimeout(timer);
        if (document.getElementById('pcOp').value) clearPick();   // a new search drops the previous OP and its details
        var q = box.value.trim();
        if (q.length < 2) { list.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(form.dataset.search + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    list.innerHTML = '';
                    if (!rows.length) { list.innerHTML = '<div style="padding:10px 12px;color:var(--gray-500);font-size:13px;">No OP found.</div>'; }
                    rows.forEach(function (op) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 12px;border:0;border-bottom:1px solid var(--gray-100);background:#fff;cursor:pointer;font-size:13px;';
                        var t = document.createElement('strong'); t.textContent = 'OP ' + (op.serial || '—') + ' · ' + (op.allottee || '—');
                        var s = document.createElement('div'); s.style.color = 'var(--gray-500)';
                        s.textContent = [op.plot_no ? 'Plot ' + op.plot_no : null, op.district, op.lga, op.file_number].filter(Boolean).join(' · ') + ' — next: ' + op.next_label;
                        b.appendChild(t); b.appendChild(s);
                        b.addEventListener('click', function () { pick(op); });
                        list.appendChild(b);
                    });
                    list.style.display = '';
                }).catch(function () { list.style.display = 'none'; });
        }, 300);
    });
    document.addEventListener('click', function (e) { if (!list.contains(e.target) && e.target !== box) list.style.display = 'none'; });
    var sv = document.getElementById('pcSurveyor'), other = document.getElementById('pcSurveyorOther');
    function syncOther() { var on = sv.value === 'other'; other.style.display = on ? '' : 'none'; other.required = on; if (!on) other.value = ''; }
    if (window.jQuery) window.jQuery(sv).on('change', syncOther); else sv.addEventListener('change', syncOther);
    form.addEventListener('submit', function (e) {
        if (!document.getElementById('pcOp').value) { e.preventDefault(); box.focus(); box.style.borderColor = '#dc2626'; alert('Find and pick the OP that was shown.'); }
    });
    // Back from a failed save with an OP chosen: look it up again so its details and the reason show.
    var keep = document.getElementById('pcOp').value;
    if (keep) {
        fetch(form.dataset.search + '?q=' + encodeURIComponent((document.getElementById('pcOpLabel').value.match(/OP (\S+)/) || [])[1] || ''), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); }).then(function (rows) { var op = rows.find(function (o) { return o.key === keep; }); if (op) pick(op); });
    }
})();
</script>

<style>
    .survey-proto .pc-tabs { display:flex; gap:6px; margin-bottom:12px; }
    .survey-proto .pc-tabs a { padding:8px 14px; border-radius:8px; border:1.5px solid var(--gray-300,#d1d5db); font-weight:600; font-size:14px; color:var(--gray-700,#374151); text-decoration:none; }
    .survey-proto .pc-tabs a.on { background:var(--primary); border-color:var(--primary); color:#fff; }
    .survey-proto .pc-sub { font-size:12px; color:var(--gray-500); }
    .survey-proto .pc-photos { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .survey-proto .pc-photos img { width:120px; height:90px; object-fit:cover; border-radius:8px; border:1px solid var(--gray-200,#e5e7eb); }
    .survey-proto .pc-kv { display:grid; grid-template-columns: 140px 1fr; gap:6px 12px; font-size:14px; }
    .survey-proto .pc-kv dt { color:var(--gray-500); }
    .survey-proto .pc-kv dd { margin:0; font-weight:600; }
</style>

<div x-data="{ view: null }">
<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Showings</div><div class="kpi-value">{{ number_format($totals['showings']) }}</div><div class="pc-sub">{{ number_format($totals['ops']) }} OPs shown</div></div>
    <div class="kpi-card"><div class="kpi-label">Ground checks</div><div class="kpi-value">{{ number_format($totals['checks']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Discrepancies</div><div class="kpi-value" style="color:#b91c1c;">{{ number_format($totals['discrepancies']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Recorded with</div><div style="font-size:15px;font-weight:600;">Record Plot Showing</div><div class="pc-sub">Here, or with the Plot Confirmation app in the field.</div></div>
</div>

<div class="page-header" style="margin-bottom:10px;">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('pcShowForm').classList.add('open')"><i class="fas fa-plus"></i> Record Plot Showing</button>
</div>

<div class="pc-tabs">
    <a href="{{ $tabUrl('showings') }}" class="{{ $tab === 'showings' ? 'on' : '' }}">OP Showing</a>
    <a href="{{ $tabUrl('checks') }}" class="{{ $tab === 'checks' ? 'on' : '' }}">Ground Check</a>
</div>

<div class="table-container">
    <div class="table-header">
        <h3>{{ $tab === 'checks' ? 'Ground Check' : 'OP Showing' }}</h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ $tab === 'checks' ? 'Ref, OP, plot, layout, beacon…' : 'OP, allottee, plot, surveyor…' }}" style="{{ $field }}width:220px;">
            @if ($tab === 'checks')
                <select name="result" style="{{ $field }}">
                    <option value="">All results</option>
                    @foreach (Check::RESULTS as $x)<option value="{{ $x }}" @selected(request('result') === $x)>{{ $x }}</option>@endforeach
                </select>
            @else
                <select name="reason" style="{{ $field }}">
                    <option value="">All showings</option>
                    <option value="first" @selected(request('reason') === 'first')>1st showings</option>
                    @foreach (Showing::REASONS as $x)<option value="{{ $x }}" @selected(request('reason') === $x)>{{ $x }}</option>@endforeach
                </select>
            @endif
            <input type="date" name="from" value="{{ request('from') }}" style="{{ $field }}" title="From">
            <input type="date" name="to" value="{{ request('to') }}" style="{{ $field }}" title="To">
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if (request()->hasAny(['q', 'result', 'reason', 'from', 'to']))<a class="btn btn-secondary btn-sm" href="{{ $tabUrl($tab) }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table>
            @if ($tab === 'checks')
                <thead><tr><th>S/N</th><th>Ref</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>OP / Layout</th><th>Checked</th><th>Surveyor</th><th>Beacons</th><th>Result</th><th></th></tr></thead>
            @else
                <thead><tr><th>S/N</th><th>OP</th><th>Allottee</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Showing</th><th>Shown</th><th>Surveyor</th><th>Allottee there</th><th>Requester</th><th></th></tr></thead>
            @endif
            <tbody>
                @forelse ($rows as $x)
                    @php
                        $photos = $x->photos->map(fn ($p) => route($tab === 'checks' ? 'survey-module.mobile.plots.checks.photos.show' : 'survey-module.mobile.plots.showings.photos.show', $p))->values();
                        $map = $x->latitude !== null ? 'https://www.google.com/maps?q=' . $x->latitude . ',' . $x->longitude : null;
                    @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        @if ($tab === 'checks')
                            <td><strong>{{ $x->check_ref }}</strong></td>
                            <td>{{ $x->plot_no ?: '—' }}</td>
                            <td>{{ $x->district ?: '—' }}</td>
                            <td>{{ $x->lga ?: '—' }}</td>
                            <td>{{ $x->location ?: '—' }}</td>
                            <td>{{ $x->op_serial ? 'OP ' . $x->op_serial : '' }}{{ $x->op_serial && $x->layout_no ? ' · ' : '' }}{{ $x->layout_no }}<div class="pc-sub">{{ $x->holder_name }}</div></td>
                            <td>{{ $x->checked_on?->format('d M Y') }} <span class="pc-sub">{{ $x->checked_at }}</span></td>
                            <td>{{ $x->surveyor_name }}</td>
                            <td>@foreach ((array) $x->beacon_check as $b)<span class="status-badge {{ $beaconCls[$b['status']] ?? 'pending' }}" title="{{ $b['label'] }}" style="margin:0 3px 3px 0;">{{ $b['beacon'] }}</span>@endforeach{{ empty($x->beacon_check) ? '—' : '' }}</td>
                            <td><span class="status-badge {{ $x->result === Check::CONFIRMED ? 'completed' : 'rejected' }}">{{ $x->result }}</span>
                                @if ($x->discrepancy_label)<div class="pc-sub">{{ $x->discrepancy_label }}</div>@endif</td>
                        @else
                            <td><strong>{{ $x->op_serial ?: '—' }}</strong></td>
                            <td>{{ $x->allottee_name ?: '—' }}</td>
                            <td>{{ $x->plot_no ?: '—' }}</td>
                            <td>{{ $x->district ?: '—' }}</td>
                            <td>{{ $x->lga ?: '—' }}</td>
                            <td>{{ $x->location ?: '—' }}</td>
                            <td>{{ $x->label }}<div class="pc-sub">{{ $x->reason }}</div></td>
                            <td>{{ $x->shown_on?->format('d M Y') }} <span class="pc-sub">{{ $x->shown_at }}</span></td>
                            <td>{{ $x->surveyor_name }}</td>
                            <td>{{ $x->allottee_present === null ? '—' : ($x->allottee_present ? 'Yes' : 'No') }}</td>
                            <td>{{ $x->requester_name ?: '—' }}@if ($x->requester_is_allottee)<div class="pc-sub">The allottee</div>@endif</td>
                        @endif
                        <td style="width:44px;">
                            <div x-data="{ open: false, top: 0, left: 0 }" class="relative">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <button type="button" class="{{ $svItem }}"
                                            @click.stop="open = false; view = @js([
                                                'title'  => $tab === 'checks' ? 'Ground check ' . $x->check_ref : 'OP ' . ($x->op_serial ?: '—') . ' · ' . $x->label,
                                                'rows'   => array_filter($tab === 'checks' ? [
                                                    'Plot' => $x->target_label, 'Allottee' => $x->holder_name, 'Result' => $x->result . ($x->discrepancy_label ? ' — ' . $x->discrepancy_label : ''),
                                                    'Beacons' => collect((array) $x->beacon_check)->map(fn ($b) => $b['beacon'] . ': ' . $b['label'])->implode('; '),
                                                    'Checked' => trim($x->checked_on?->format('d M Y') . ' ' . $x->checked_at), 'Surveyor' => $x->surveyor_name, 'Notes' => $x->notes,
                                                    'GPS' => $x->latitude !== null ? round($x->latitude, 6) . ', ' . round($x->longitude, 6) . ($x->accuracy_m ? ' (±' . round($x->accuracy_m) . ' m)' : '') : null,
                                                ] : [
                                                    'Allottee' => $x->allottee_name, 'Showing' => $x->label . ($x->reason ? ' — ' . $x->reason : ''),
                                                    'Shown' => trim($x->shown_on?->format('d M Y') . ' ' . $x->shown_at), 'Surveyor' => $x->surveyor_name,
                                                    'Allottee there' => $x->allottee_present === null ? null : ($x->allottee_present ? 'Yes' : 'No'),
                                                    'Requester' => $x->requester_name ? $x->requester_name . ($x->requester_is_allottee ? ' (the allottee)' : '') : null,
                                                    'Requester address' => $x->requester_address, 'Notes' => $x->notes,
                                                    'GPS' => $x->latitude !== null ? round($x->latitude, 6) . ', ' . round($x->longitude, 6) . ($x->accuracy_m ? ' (±' . round($x->accuracy_m) . ' m)' : '') : null,
                                                ]),
                                                'photos' => $photos,
                                            ])">
                                        <i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View details
                                    </button>
                                    @if ($map)
                                        <a href="{{ $map }}" target="_blank" rel="noopener" class="{{ $svItem }}" @click="open = false"><i data-lucide="map-pin" class="w-4 h-4 text-gray-500"></i> Open in Maps</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $tab === 'checks' ? 'No ground checks' : 'No showings' }} {{ request()->hasAny(['q', 'result', 'reason', 'from', 'to']) ? 'match the filter' : 'recorded yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($rows->total()) }} {{ $tab === 'checks' ? 'ground checks' : 'showings' }}</span><div class="pagination">{{ $rows->links() }}</div></div>
</div>

{{-- Details --}}
<template x-if="view">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.45);">
        <div class="bg-white rounded-xl shadow-xl w-full" style="max-width:640px;max-height:90vh;overflow:auto;padding:20px;" @click.outside="view = null" @keydown.escape.window="view = null">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;" x-text="view.title"></h3>
                <button type="button" class="btn btn-secondary btn-sm" @click="view = null" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <dl class="pc-kv">
                <template x-for="(v, k) in view.rows" :key="k"><div style="display:contents;"><dt x-text="k"></dt><dd x-text="v"></dd></div></template>
            </dl>
            <div class="pc-photos" x-show="view.photos.length">
                <template x-for="src in view.photos" :key="src"><a :href="src" target="_blank" rel="noopener"><img :src="src" alt="Photograph"></a></template>
            </div>
            <div class="pc-sub" x-show="!view.photos.length" style="margin-top:10px;">No photographs.</div>
        </div>
    </div>
</template>
</div>
@endsection

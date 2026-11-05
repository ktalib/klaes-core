@extends('survey_module.layouts.klaes')

@section('page-title', ($isGkn ? 'Plot Showing Register' : 'Plot Showing Register - Resettlement') . ' — KLAES')

@php
  use App\Models\Survey\SurveyOpShowing as Showing;
  use App\Models\Survey\SurveyPlotCheck as Check;

  // Opened from Records > Plot Showing Register (user, 2026-11-02).
  $PageTitle = $isGkn ? 'Plot Showing Register' : 'Plot Showing Register - Resettlement';
  $PageDescription = $isGkn
      ? 'OP plots shown to their allottees (Showing History) and ground checks of plots against their layout and the Beacon Register: recorded here or with the Plot Confirmation app. Confirmation Forms are issued and captured on their own tab.'
      : 'Resettlement OP plots shown to their allottees, and their ground checks. View only: they are recorded under GKN > Plot Showing Register.';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $tabUrl = fn ($t) => route($register, $t === 'showings' ? [] : ['tab' => $t]);
  $beaconCls = ['registered' => 'completed', 'other_surveyor' => 'pending', 'unregistered' => 'rejected'];
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

{{-- Plot Showing Register (Old) (user, 2026-11-03): an entry from the old register book. The book has no OP
     numbers, so nothing here is tied to an OP; "C of O No." may really be the OP number and is kept as written. --}}
@if ($isGkn)
@php
    $oldOpen = ($errors->any() && old('_form') === 'old') || $editOld;
    $ov = fn ($k, $col = null) => old($k, $editOld ? ($k === 'shown_on' ? optional($editOld->shown_on)->format('Y-m-d') : $editOld->{$col ?? $k}) : null);
    // Pointed Out By is a surveyor from the list, or Other (user, 2026-11-03). An entry saved with a typed
    // name opens on that surveyor if the name is on the list, otherwise on Other with the name filled.
    $oldBy = ''; $oldByOther = '';
    if ($editOld) {
        $byName = trim((string) $editOld->surveyor_name);
        $byMatch = $editOld->surveyors_list_id
            ? $surveyors->firstWhere('id', $editOld->surveyors_list_id)
            : $surveyors->first(fn ($s) => $byName !== '' && mb_strtolower($s->full_name) === mb_strtolower($byName));
        if ($byMatch) $oldBy = (string) $byMatch->id;
        elseif ($byName !== '') { $oldBy = 'other'; $oldByOther = $byName; }
    }
    $oldBy = (string) old('pointed_by', $oldBy);
    $oldByOther = old('pointed_by_other', $oldByOther);
    // TP No. (user, 2026-11-03), as on Record Plot Showing (New): an entry from the register, or Other.
    $oldTp = (string) old('tp', $editOld ? ($editOld->survey_tp_number_id ?: ($editOld->tp_no ? 'other' : '')) : '');
    $oldTpOther = old('tp_other', $editOld && ! $editOld->survey_tp_number_id ? $editOld->tp_no : '');
@endphp
<form method="POST" id="pcOldForm" class="farmer-entry-form {{ $oldOpen ? 'open' : '' }}"
      action="{{ $editOld ? route('survey-module.compensation.plot-confirmations.old.update', $editOld) : route('survey-module.compensation.plot-confirmations.old.store') }}"
      data-sv-modal="{{ $editOld ? 'Edit Old Register Entry' : 'Record Plot Showing' }}">
    @csrf
    @if ($editOld) @method('PUT') @endif
    <input type="hidden" name="_form" value="old">
    @if ($errors->any() && old('_form') === 'old')
        <div class="comp-type-note" style="margin-bottom:14px;border-color:#fecaca;background:#fef2f2;color:#991b1b;">{{ $errors->first() }}</div>
    @endif
    @unless ($editOld)
        {{-- One form, one attribute (recording 2026-11-05): New or Old Register. --}}
        <div class="form-group" style="margin-bottom:10px;">
            <label>Entry</label>
            <div style="display:flex;gap:18px;">
                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:500;"><input type="radio" name="pc_entry_old" value="new" style="width:auto;" onchange="pcEntry('new')"> New</label>
                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:500;"><input type="radio" name="pc_entry_old" value="old" style="width:auto;" checked onchange="pcEntry('old')"> Old Register</label>
            </div>
        </div>
    @endunless
    <p class="helper-text" style="margin:0 0 10px;">Copy the line as written in the old register. Give at least the plot no., the allottee or the number.</p>
    <div class="form-grid">
        <div class="form-group"><label>Date</label><input type="date" name="shown_on" value="{{ $ov('shown_on') }}" max="{{ now()->format('Y-m-d') }}"></div>
        <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" value="{{ $ov('plot_no') }}" maxlength="100"></div>
        <div class="form-group"><label>Allottee</label><input type="text" name="allottee_name" value="{{ $ov('allottee_name') }}" maxlength="255"></div>
        <div class="form-group"><label>C of O / OP No.</label><input type="text" name="op_serial" value="{{ $ov('op_serial') }}" maxlength="50"><p class="helper-text">As written in the book.</p></div>
        <div class="form-group">
            <label>OP Type</label>
            <select name="op_type">
                <option value="">— Select —</option>
                @foreach ([Showing::RESETTLEMENT, Showing::DIRECT] as $opt)
                    <option value="{{ $opt }}" @selected($ov('op_type') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            <p class="helper-text">Resettlement lines also show under Compensation.</p>
        </div>
        <div class="form-group">
            <label>Pointed Out By</label>
            <select name="pointed_by" id="pcOldBy" class="sv-searchable">
                <option value="">— Select surveyor —</option>
                @foreach ($surveyors as $s)
                    <option value="{{ $s->id }}" @selected($oldBy === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>
                @endforeach
                <option value="other" @selected($oldBy === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="pointed_by_other" id="pcOldByOther" maxlength="255" placeholder="Specify the surveyor's name *"
                   value="{{ $oldByOther }}" style="margin-top:6px;{{ $oldBy === 'other' ? '' : 'display:none;' }}" @if ($oldBy === 'other') required @endif>
        </div>
        <div class="form-group">
            <label>TP No. (Layout)</label>
            <select name="tp" id="pcOldTp" class="sv-searchable">
                <option value="">— Select TP No. —</option>
                @foreach ($tpNumbers as $t)
                    <option value="{{ $t->id }}" @selected($oldTp === (string) $t->id)>{{ $t->tp_no }}</option>
                @endforeach
                <option value="other" @selected($oldTp === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="tp_other" id="pcOldTpOther" maxlength="100" placeholder="Specify the TP No. *"
                   value="{{ $oldTpOther }}" style="margin-top:6px;{{ $oldTp === 'other' ? '' : 'display:none;' }}" @if ($oldTp === 'other') required @endif>
            <p class="helper-text">From Public Layout Plans, if the book gives it.</p>
        </div>
        {{-- Who asked for the showing, as on Record Plot Showing (New) (user, 2026-11-03). Optional: the book may not say. --}}
        <div class="form-group full" style="margin-bottom:0;">
            <input type="hidden" name="requester_is_allottee" value="0">
            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:500;cursor:pointer;">
                <input type="checkbox" name="requester_is_allottee" value="1" id="pcOldReqSame" style="width:auto;"
                       @checked((string) old('requester_is_allottee', $editOld && $editOld->requester_is_allottee ? '1' : '0') === '1')>
                Requester is the allottee
            </label>
        </div>
        <div class="form-group">
            <label>Requester's Name</label>
            <input type="text" name="requester_name" id="pcOldReqName" maxlength="255" value="{{ $ov('requester_name') }}">
        </div>
        <div class="form-group full"><label>Remark</label><textarea name="notes" maxlength="2000" rows="2">{{ $ov('notes') }}</textarea></div>
    </div>
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route($register, ['source' => 'old']) }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $editOld ? 'Save Changes' : 'Save' }}</button></div>
    </div>
</form>

{{-- Record Plot Showing (user, 2026-11-02): the same record the Plot Confirmation app makes. --}}
@php $pcOpen = $errors->any() && old('_form') === 'showing'; @endphp
<form method="POST" action="{{ route('survey-module.compensation.plot-confirmations.showings.store') }}" enctype="multipart/form-data"
      id="pcShowForm" class="farmer-entry-form {{ $pcOpen ? 'open' : '' }}" data-sv-modal="Record Plot Showing" data-sv-modal-size="lg" data-sv-modal-static
      data-search="{{ route('survey-module.compensation.plot-confirmations.op-search') }}"
      data-plots="{{ route('survey-module.compensation.plot-confirmations.tp-plots') }}">
    @csrf
    <input type="hidden" name="_form" value="showing">
    <input type="hidden" name="op" id="pcOp" value="{{ old('op') }}">
    @if ($pcOpen)
        <div class="comp-type-note" style="margin-bottom:14px;border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
        </div>
    @endif

    <div class="form-group" style="margin-bottom:10px;">
        <label>Entry</label>
        <div style="display:flex;gap:18px;">
            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:500;"><input type="radio" name="pc_entry_new" value="new" style="width:auto;" checked onchange="pcEntry('new')"> New</label>
            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:500;"><input type="radio" name="pc_entry_new" value="old" style="width:auto;" onchange="pcEntry('old')"> Old Register</label>
        </div>
    </div>

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-file-signature"></i><span>OP</span></div>
        {{-- The layout first, then the plot in it (CR 03112026 item 3). --}}
        <div class="form-group span-2">
            <label>TP No. <span class="required">*</span></label>
            <select id="pcTpGroup" class="sv-searchable">
                <option value="">— Select TP No. —</option>
                @foreach ($tpGroups as $g)<option value="{{ $g->id }}">{{ $g->label }} ({{ number_format($g->op_count) }} OPs)</option>@endforeach
            </select>
            <p class="helper-text">{{ number_format($tpGroups->count()) }} layouts, grouped from the TP numbers on the OPs.</p>
        </div>
        <div class="form-group">
            <label>Showing</label>
            <input type="text" id="pcNext" readonly tabindex="-1" style="background:var(--gray-100);font-weight:700;" value="{{ old('op') ? '' : '—' }}">
        </div>
        <div class="form-group span-2" style="position:relative;">
            <label>Plot No. <span class="required">*</span></label>
            <input type="text" id="pcPlotSearch" autocomplete="off" placeholder="Choose the TP No. first" disabled>
            <div id="pcPlotResults" style="display:none;position:absolute;z-index:60;left:0;right:0;top:100%;max-height:260px;overflow:auto;background:#fff;border:1px solid var(--gray-200);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);"></div>
            <p class="helper-text" id="pcPlotHint">Plots in the layout, numbers first, then the C, IND and SS series.</p>
        </div>
        {{-- For an OP with no TP number on it: about 12,500 have none. --}}
        <div class="form-group" style="position:relative;">
            <label>No TP No. on the OP?</label>
            <input type="text" id="pcOpSearch" autocomplete="off" placeholder="OP serial number" value="{{ old('op_label') }}">
            <input type="hidden" name="op_label" id="pcOpLabel" value="{{ old('op_label') }}">
            <div id="pcOpResults" style="display:none;position:absolute;z-index:60;left:0;right:0;top:100%;max-height:260px;overflow:auto;background:#fff;border:1px solid var(--gray-200);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);"></div>
            <p class="helper-text">Find it by serial number.</p>
        </div>
        {{-- Property order: Plot No., District, LGA, Location (backfilled from the OP). --}}
        <div class="form-group"><label>Allottee</label><input type="text" id="pcAllottee" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>Plot No.</label><input type="text" id="pcPlot" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>District</label><input type="text" id="pcDistrict" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>LGA</label><input type="text" id="pcLga" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group span-2"><label>Location</label><input type="text" id="pcLocation" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        {{-- TP No. (user, 2026-11-03): from the Public Layout Plans register, or Other with the number typed.
             Picked for you when the OP's layout number is in the register. --}}
        <div class="form-group span-2">
            <label>TP No. (Layout)</label>
            <select name="tp" id="pcTp" class="sv-searchable">
                <option value="">— Select TP No. —</option>
                @foreach ($tpNumbers as $t)
                    <option value="{{ $t->id }}" data-tp="{{ $t->tp_no }}" @selected(old('tp') === (string) $t->id)>{{ $t->tp_no }}</option>
                @endforeach
                <option value="other" @selected(old('tp') === 'other')>Other (specify)</option>
            </select>
            <p class="helper-text" id="pcTpHint">Public Layout Plans ({{ number_format($tpNumbers->count()) }} drawings).</p>
        </div>
        <div class="form-group" id="pcTpOtherBox" style="{{ old('tp') === 'other' ? '' : 'display:none;' }}">
            <label>Specify TP No. <span class="required">*</span></label>
            <input type="text" name="tp_other" id="pcTpOther" maxlength="100" placeholder="e.g. TP/KNUPDA/358E" value="{{ old('tp_other') }}" @if (old('tp') === 'other') required @else disabled @endif>
        </div>
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
// New or Old Register: one Record Plot Showing, the entry attribute swaps the fields it asks for.
function pcEntry(kind) {
    var show = document.getElementById('pcShowForm'), old = document.getElementById('pcOldForm');
    if (!show || !old) return;
    show.classList.toggle('open', kind === 'new');
    old.classList.toggle('open', kind === 'old');
    document.querySelectorAll('input[name="pc_entry_new"], input[name="pc_entry_old"]').forEach(function (r) { r.checked = r.value === kind; });
}
// Old register: "Requester is the allottee" copies the Allottee into Requester's Name and locks it.
(function () {
    var same = document.getElementById('pcOldReqSame'), name = document.getElementById('pcOldReqName');
    var allottee = document.querySelector('#pcOldForm [name="allottee_name"]');
    if (!same || !name || !allottee) return;
    function sync() {
        name.readOnly = same.checked; name.style.background = same.checked ? 'var(--gray-100)' : '';
        if (same.checked) name.value = allottee.value.trim();
    }
    same.addEventListener('change', function () { if (!same.checked) name.value = ''; sync(); });
    allottee.addEventListener('input', function () { if (same.checked) sync(); });
    sync();
})();
// Old register: Other under Pointed Out By, and under TP No., reveals the required box.
[['pcOldBy', 'pcOldByOther'], ['pcOldTp', 'pcOldTpOther']].forEach(function (ids) {
    var by = document.getElementById(ids[0]), other = document.getElementById(ids[1]);
    if (!by || !other) return;
    by.addEventListener('change', function () {
        var on = by.value === 'other';
        other.style.display = on ? '' : 'none'; other.required = on;
        if (on) other.focus(); else other.value = '';
    });
});
// TP No.: Other reveals a required specify field. Picking an OP selects its layout's TP No. when the register has it
// (the previous OP's pick is cleared; a TP No. chosen by hand is left alone).
var pcTpAuto = null;
function pcTpSet(value) {
    var sel = document.getElementById('pcTp'); if (!sel) return;
    sel.value = value;
    if (window.jQuery) window.jQuery(sel).trigger('change.select2');
    pcTpOtherSync();
}
function pcTpOtherSync() {
    var other = document.getElementById('pcTp').value === 'other', box = document.getElementById('pcTpOtherBox'), input = document.getElementById('pcTpOther');
    box.style.display = other ? '' : 'none'; input.disabled = !other; input.required = other;
}
function pcTpFromOp(layout, tpNumberId) {
    var sel = document.getElementById('pcTp'), hint = document.getElementById('pcTpHint'); if (!sel) return;
    var norm = function (v) { return String(v || '').toUpperCase().replace(/\s+/g, ''); };
    if (pcTpAuto !== null && sel.value === pcTpAuto) pcTpSet('');
    pcTpAuto = null;
    hint.textContent = hint.dataset.base || (hint.dataset.base = hint.textContent);
    if (!layout || sel.value) return;
    var hit = (tpNumberId && sel.querySelector('option[value="' + tpNumberId + '"]'))
        || Array.prototype.find.call(sel.options, function (o) { return o.dataset.tp && norm(o.dataset.tp) === norm(layout); });
    if (hit) { pcTpSet(hit.value); pcTpAuto = hit.value; hint.textContent = "From the OP's layout (" + layout + ').'; }
    else hint.textContent = "The OP's layout " + layout + ' is not in Public Layout Plans: pick it, or Other.';
}
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('pcTp'); if (!sel) return;
    if (window.jQuery) window.jQuery(sel).on('change', function () { pcTpAuto = null; pcTpOtherSync(); }); else sel.addEventListener('change', function () { pcTpAuto = null; pcTpOtherSync(); });
});

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
        pcTpFromOp(op.layout, op.tp_number_id);
        var again = op.next > 1;
        document.getElementById('pcReasonBox').style.display = again ? '' : 'none';
        document.getElementById('pcReason').required = again;
        if (!again) document.getElementById('pcReason').value = '';
        list.style.display = 'none';
        plotList.style.display = 'none';
        syncRequester();
    }

    // The layout, then the plot in it (CR 03112026 item 3). Picking a plot picks its OP; several OPs on one plot are all listed.
    var grp = document.getElementById('pcTpGroup'), plotBox = document.getElementById('pcPlotSearch'),
        plotList = document.getElementById('pcPlotResults'), plotHint = document.getElementById('pcPlotHint'), plotTimer = null, plotSeq = 0;
    function plotRow(op) {
        var b = document.createElement('button');
        b.type = 'button';
        b.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 12px;border:0;border-bottom:1px solid var(--gray-100);background:#fff;cursor:pointer;font-size:13px;';
        var t = document.createElement('strong'); t.textContent = 'Plot ' + (op.plot_no || '—') + ' · ' + (op.allottee || '—');
        var s = document.createElement('div'); s.style.color = 'var(--gray-500)';
        s.textContent = ['OP ' + (op.serial || '—'), op.district, op.lga, op.file_number].filter(Boolean).join(' · ') + ' — next: ' + op.next_label;
        b.appendChild(t); b.appendChild(s);
        b.addEventListener('click', function () { box.value = ''; pick(op); box.value = ''; plotBox.value = op.plot_no || ''; });
        return b;
    }
    function loadPlots() {
        if (!grp.value) { plotList.style.display = 'none'; return; }
        var mine = ++plotSeq;
        fetch(form.dataset.plots + '?group=' + encodeURIComponent(grp.value) + '&plot=' + encodeURIComponent(plotBox.value.trim()), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                if (mine !== plotSeq) return;
                plotList.innerHTML = '';
                if (!rows.length) { plotList.innerHTML = '<div style="padding:10px 12px;color:var(--gray-500);font-size:13px;">No plot found in this layout.</div>'; }
                rows.forEach(function (op) { plotList.appendChild(plotRow(op)); });
                plotList.style.display = '';
                plotHint.textContent = rows.length >= 100 ? 'Showing the first 100: type the plot number to narrow it.' : rows.length + ' plot record' + (rows.length === 1 ? '' : 's') + ' in the list.';
            }).catch(function () { plotList.style.display = 'none'; });
    }
    function groupChanged() {
        if (document.getElementById('pcOp').value) clearPick();
        plotBox.value = '';
        plotBox.disabled = !grp.value;
        plotBox.placeholder = grp.value ? 'Type or choose the plot number' : 'Choose the TP No. first';
        loadPlots();
    }
    if (window.jQuery) window.jQuery(grp).on('change', groupChanged); else grp.addEventListener('change', groupChanged);
    plotBox.addEventListener('focus', loadPlots);
    plotBox.addEventListener('input', function () {
        if (document.getElementById('pcOp').value) clearPick();
        clearTimeout(plotTimer);
        plotTimer = setTimeout(loadPlots, 250);
    });
    function clearPick() {
        current = null;
        ['pcOp', 'pcOpLabel', 'pcAllottee', 'pcPlot', 'pcDistrict', 'pcLga', 'pcLocation'].forEach(function (id) { set(id, ''); });
        pcTpFromOp(null);
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
    document.addEventListener('click', function (e) {
        if (!list.contains(e.target) && e.target !== box) list.style.display = 'none';
        if (!plotList.contains(e.target) && e.target !== plotBox) plotList.style.display = 'none';
    });
    var sv = document.getElementById('pcSurveyor'), other = document.getElementById('pcSurveyorOther');
    function syncOther() { var on = sv.value === 'other'; other.style.display = on ? '' : 'none'; other.required = on; if (!on) other.value = ''; }
    if (window.jQuery) window.jQuery(sv).on('change', syncOther); else sv.addEventListener('change', syncOther);
    form.addEventListener('submit', function (e) {
        if (!document.getElementById('pcOp').value) { e.preventDefault(); plotBox.style.borderColor = '#dc2626'; box.style.borderColor = '#dc2626'; alert('Choose the TP No. and the plot that was shown.'); }
    });
    // Back from a failed save with an OP chosen: look it up again so its details and the reason show.
    var keep = document.getElementById('pcOp').value;
    if (keep) {
        fetch(form.dataset.search + '?q=' + encodeURIComponent((document.getElementById('pcOpLabel').value.match(/OP (\S+)/) || [])[1] || ''), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); }).then(function (rows) { var op = rows.find(function (o) { return o.key === keep; }); if (op) pick(op); });
    }
})();
</script>
@endif

<style>
    .survey-proto .pc-tabs { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
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
{{-- Tabs first, so each tab brings its own counts and buttons. Confirmation Forms (user, 2026-11-03) opens here
     without a page load; so does going back to the tab the page was opened on. --}}
<div class="pc-tabs" id="pcTabs" data-server-tab="{{ $tab }}">
    <a href="{{ $tabUrl('showings') }}" data-tab="showings" class="{{ $tab === 'showings' ? 'on' : '' }}">Plot Showing</a>
    <a href="{{ $tabUrl('checks') }}" data-tab="checks" class="{{ $tab === 'checks' ? 'on' : '' }}">Ground Check</a>
    @if ($isGkn)
        <a href="{{ $tabUrl('confirmation') }}" data-tab="confirmation" class="{{ $tab === 'confirmation' ? 'on' : '' }}">Confirmation Forms</a>
    @endif
</div>

<div id="pcMain" @if ($tab === 'confirmation') hidden @endif>
<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Showings</div><div class="kpi-value">{{ number_format($totals['showings']) }}</div><div class="pc-sub">{{ number_format($totals['ops']) }} OPs shown</div></div>
    <div class="kpi-card"><div class="kpi-label">Old register</div><div class="kpi-value">{{ number_format($totals['old']) }}</div><div class="pc-sub">from the old book</div></div>
    <div class="kpi-card"><div class="kpi-label">Ground checks</div><div class="kpi-value">{{ number_format($totals['checks']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Discrepancies</div><div class="kpi-value" style="color:#b91c1c;">{{ number_format($totals['discrepancies']) }}</div></div>
    {{-- Which register this is, and how it is recorded (user, 2026-11-03). --}}
    @php
      [$regName, $regIcon, $regHow] = match ($tab) {
          'checks' => ['Ground Check', 'fa-map-marked-alt', 'Checked in the field with the Plot Confirmation app.'],
          default  => ['Plot Showing', 'fa-street-view', $isGkn ? 'Record Plot Showing, or the Plot Confirmation app in the field.' : 'View only: recorded under GKN.'],
      };
    @endphp
    <div class="kpi-card" style="border-left:4px solid var(--primary, #2563eb);"><div class="kpi-label">Register</div>
        <div style="font-size:16px;font-weight:700;color:var(--primary, #2563eb);"><i class="fas {{ $regIcon }}" style="font-size:14px;margin-right:6px;"></i>{{ $regName }}</div>
        <div class="pc-sub">{{ $regHow }}</div></div>
</div>

{{-- One button; New or Old Register is an attribute on the form (recording 2026-11-05). --}}
@if ($isGkn)
<div class="page-header" style="margin-bottom:10px;">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="pcEntry('new')"><i class="fas fa-plus"></i> Record Plot Showing</button>
</div>
@endif

<div class="table-container">
    <div class="table-header">
        <h3>{{ $tab === 'checks' ? 'Ground Check' : 'Plot Showing' }}</h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ $tab === 'checks' ? 'Ref, OP, plot, layout, beacon…' : 'OP, allottee, plot, surveyor, remark…' }}" style="{{ $field }}width:220px;">
            @if ($tab === 'checks')
                <select name="result" style="{{ $field }}">
                    <option value="">All results</option>
                    @foreach (Check::RESULTS as $x)<option value="{{ $x }}" @selected(request('result') === $x)>{{ $x }}</option>@endforeach
                </select>
            @else
                <select name="source" style="{{ $field }}">
                    <option value="">All sources</option>
                    <option value="new" @selected($source === 'new')>New</option>
                    <option value="old" @selected($source === 'old')>Old Register</option>
                </select>
                @if ($isGkn)
                    <select name="type" style="{{ $field }}">
                        <option value="">All OP types</option>
                        @foreach ([Showing::RESETTLEMENT, Showing::DIRECT] as $opt)<option value="{{ $opt }}" @selected(request('type') === $opt)>{{ $opt }}</option>@endforeach
                    </select>
                @endif
                <select name="reason" style="{{ $field }}">
                    <option value="">All showings</option>
                    <option value="first" @selected(request('reason') === 'first')>1st showings</option>
                    @foreach (Showing::REASONS as $x)<option value="{{ $x }}" @selected(request('reason') === $x)>{{ $x }}</option>@endforeach
                </select>
            @endif
            <input type="date" name="from" value="{{ request('from') }}" style="{{ $field }}" title="From">
            <input type="date" name="to" value="{{ request('to') }}" style="{{ $field }}" title="To">
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if (request()->hasAny(['q', 'result', 'reason', 'source', 'type', 'from', 'to']))<a class="btn btn-secondary btn-sm" href="{{ $tabUrl($tab) }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table>
            @if ($tab === 'checks')
                <thead><tr><th>S/N</th><th>Ref</th><th>TP No. / Layout</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>OP</th><th>Checked</th><th>Surveyor</th><th>Beacons</th><th>Result</th><th></th></tr></thead>
            @else
                <thead><tr><th>S/N</th><th>OP</th><th>Allottee</th><th>TP No.</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Source</th><th>OP Type</th><th>Showing</th><th>Shown</th><th>Surveyor / Pointed Out By</th><th>Allottee there</th><th>Requester</th><th>Ground Check</th><th></th></tr></thead>
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
                            {{-- TP No. before Plot No. (user, 2026-11-03): the layout number the check was made against. --}}
                            <td style="white-space:nowrap;">{{ $x->layout_no ?: '—' }}</td>
                            <td>{{ $x->plot_no ?: '—' }}</td>
                            <td>{{ $x->district ?: '—' }}</td>
                            <td>{{ $x->lga ?: '—' }}</td>
                            <td>{{ $x->location ?: '—' }}</td>
                            <td>{{ $x->op_serial ? 'OP ' . $x->op_serial : '—' }}<div class="pc-sub">{{ $x->holder_name }}</div>
                                {{-- The showing this check answers (user, 2026-11-03). --}}
                                @if ($x->showing)<div class="pc-sub">Against {{ $x->showing->label }} · {{ $x->showing->shown_on?->format('d M Y') }}</div>@endif</td>
                            <td>{{ $x->checked_on?->format('d M Y') }} <span class="pc-sub">{{ $x->checked_at }}</span></td>
                            <td>{{ $x->surveyor_name }}</td>
                            <td>@foreach ((array) $x->beacon_check as $b)<span class="status-badge {{ $beaconCls[$b['status']] ?? 'pending' }}" title="{{ $b['label'] }}" style="margin:0 3px 3px 0;">{{ $b['beacon'] }}</span>@endforeach{{ empty($x->beacon_check) ? '—' : '' }}</td>
                            <td><span class="status-badge {{ $x->result === Check::CONFIRMED ? 'completed' : 'rejected' }}">{{ $x->result }}</span>
                                @if ($x->discrepancy_label)<div class="pc-sub">{{ $x->discrepancy_label }}</div>@endif</td>
                        @else
                            <td><strong>{{ $x->op_serial ?: '—' }}</strong></td>
                            <td>{{ $x->allottee_name ?: '—' }}</td>
                            <td style="white-space:nowrap;">{{ $x->tp_no ?: '—' }}</td>
                            <td>{{ $x->plot_no ?: '—' }}</td>
                            <td>{{ $x->district ?: '—' }}</td>
                            <td>{{ $x->lga ?: '—' }}</td>
                            <td>{{ $x->location ?: '—' }}</td>
                            @php $isOld = $x->op_source === Showing::SOURCE_OLD; @endphp
                            <td style="white-space:nowrap;">@if ($isOld)<span class="status-badge pending">Old Register</span>@else New @endif</td>
                            <td style="white-space:nowrap;">{{ $x->op_type ?: ($isOld ? '—' : Showing::DIRECT) }}</td>
                            <td>{{ $isOld ? '—' : $x->label }}<div class="pc-sub">{{ $x->reason }}</div></td>
                            <td>{{ $x->shown_on?->format('d M Y') ?? '—' }} <span class="pc-sub">{{ $x->shown_at }}</span></td>
                            <td>{{ $x->surveyor_name ?: '—' }}</td>
                            <td>{{ $x->allottee_present === null ? '—' : ($x->allottee_present ? 'Yes' : 'No') }}</td>
                            <td>{{ $x->requester_name ?: '—' }}@if ($x->requester_is_allottee)<div class="pc-sub">The allottee</div>@endif</td>
                            {{-- Step 2, the field's response to this showing (user, 2026-11-03). --}}
                            <td>
                                @forelse ($x->groundChecks as $gc)
                                    <span class="status-badge {{ $gc->result === Check::CONFIRMED ? 'completed' : 'rejected' }}" title="{{ $gc->check_ref }}" style="margin:0 3px 3px 0;">{{ $gc->result }}</span>
                                @empty
                                    <span class="pc-sub">{{ $x->op_source === Showing::SOURCE_OLD ? '—' : 'Awaiting' }}</span>
                                @endforelse
                            </td>
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
                                                    'Allottee' => $x->allottee_name, 'TP No.' => $x->tp_no, 'Showing' => $x->label . ($x->reason ? ' — ' . $x->reason : ''),
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
                                    @if ($isGkn && $tab !== 'checks' && $x->op_source === Showing::SOURCE_OLD)
                                        <a href="{{ route($register, ['edit' => $x->id] + request()->only(['q', 'source', 'type', 'from', 'to', 'page'])) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                        <form method="POST" action="{{ route('survey-module.compensation.plot-confirmations.old.destroy', $x) }}" onsubmit="return confirm('Remove this old register entry?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="17" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $tab === 'checks' ? 'No ground checks' : 'No showings' }} {{ request()->hasAny(['q', 'result', 'reason', 'source', 'type', 'from', 'to']) ? 'match the filter' : 'recorded yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($rows->total()) }} {{ $tab === 'checks' ? 'ground checks' : 'showings' }}</span><div class="pagination">{{ $rows->links() }}</div></div>
</div>
</div>{{-- #pcMain --}}

@if ($isGkn)
<div id="pcConfirm" @unless ($tab === 'confirmation') hidden @endunless>
    @include('survey_module.compensation.partials._confirmation_forms_panel')
</div>
@endif

<script>
// Confirmation Forms opens without a page load, and so does going back to the tab the page was opened on;
// the address follows so a refresh, or a save that comes back here, lands on the same tab.
(function () {
    var bar = document.getElementById('pcTabs');
    if (!bar) return;
    var server = bar.dataset.serverTab, main = document.getElementById('pcMain'), confirm = document.getElementById('pcConfirm');
    var serverUrl = server === 'confirmation' ? null : location.href;
    bar.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-tab]');
        if (!a) return;
        var t = a.dataset.tab;
        if (t !== 'confirmation' && (t !== server || !serverUrl)) return;   // another list: load it
        e.preventDefault();
        var on = t === 'confirmation';
        main.hidden = on; confirm.hidden = !on;
        bar.querySelectorAll('a[data-tab]').forEach(function (x) { x.classList.toggle('on', x === a); });
        try { history.replaceState(null, '', on ? a.href : serverUrl); } catch (err) {}
        if (window.lucide && lucide.createIcons) lucide.createIcons();
    });
})();
</script>

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

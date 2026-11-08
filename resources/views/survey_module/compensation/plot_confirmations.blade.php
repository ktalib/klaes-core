@extends('survey_module.layouts.klaes')

@section('page-title', ($isGkn ? 'Plot Showing Register' : 'Plot Showing Register - Resettlement') . ' — KLAES')

@php
  use App\Models\Survey\SurveyOpShowing as Showing;
  use App\Models\Survey\SurveyPlotCheck as Check;

  // Opened from Records > Plot Showing Register (user, 2026-11-02).
  $PageTitle = $isGkn ? 'Plot Showing Register' : 'Plot Showing Register - Resettlement';
  $PageDescription = $isGkn
      ? 'OP plots shown to their allottees (Showing History) and ground checks of plots against their layout and the Beacon Register: recorded here or with the Plot Confirmation app. Ground Check is available below Record Plot Showing.'
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
      data-sv-modal="{{ $editOld ? 'Edit Old Register Entry' : 'Record Plot Showing (Old Register)' }}">
    @csrf
    @if ($editOld) @method('PUT') @endif
    <input type="hidden" name="_form" value="old">
    @if ($errors->any() && old('_form') === 'old')
        <div class="comp-type-note" style="margin-bottom:14px;border-color:#fecaca;background:#fef2f2;color:#991b1b;">{{ $errors->first() }}</div>
    @endif
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

{{-- Record Plot Showing (user, 2026-11-02): the same record the Plot Confirmation app makes. Plot Showing and Ground
     Check are one form, each opened by its own button (user, 2026-11-06): the OP block is shared and the choice swaps
     what is asked below it. Only the chosen part's fields are sent or validated; the other's fieldset is disabled. --}}
@php
    $pcOpen = $errors->any() && in_array(old('_form'), ['showing', 'check'], true);
    $pcPane = $pcOpen && old('_form') === 'check' ? 'check' : 'show';
    $pcSaveShowing = route('survey-module.compensation.plot-confirmations.showings.store');
    $pcSaveCheck = route('survey-module.compensation.plot-confirmations.checks.store');
@endphp
<form method="POST" action="{{ $pcPane === 'check' ? $pcSaveCheck : $pcSaveShowing }}" enctype="multipart/form-data"
      id="pcShowForm" class="farmer-entry-form {{ $pcOpen ? 'open' : '' }}" data-sv-modal="{{ $pcPane === 'check' ? 'Ground Check' : 'Record Plot Showing' }}" data-sv-modal-size="lg" data-sv-modal-static
      data-search="{{ route('survey-module.compensation.plot-confirmations.op-search') }}"
      data-plots="{{ route('survey-module.compensation.plot-confirmations.tp-plots') }}"
      data-save-showing="{{ $pcSaveShowing }}" data-save-check="{{ $pcSaveCheck }}">
    @csrf
    <input type="hidden" name="_form" id="pcFormKind" value="{{ $pcPane === 'check' ? 'check' : 'showing' }}">
    <input type="hidden" name="op" id="pcOp" value="{{ old('op') }}">
    @if ($pcOpen)
        <div class="comp-type-note" style="margin-bottom:14px;border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
        </div>
    @endif

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-file-signature"></i><span>OP</span></div>
        {{-- The layout first, then the plot in it (CR 03112026 item 3). --}}
        <div class="form-group span-2">
            <label>TP No. <span class="required">*</span></label>
            <select id="pcTpGroup" class="sv-searchable">
                <option value="">— Select TP No. —</option>
                @foreach ($tpGroups as $g)<option value="{{ $g->id }}" data-tp="{{ $g->label }}">{{ $g->label }} ({{ number_format($g->op_count) }} OPs)</option>@endforeach
            </select>
            <p class="helper-text">{{ number_format($tpGroups->count()) }} layouts, grouped from the TP numbers on the OPs.</p>
        </div>
        <div class="form-group" data-pc-on="show" style="{{ $pcPane === 'show' ? '' : 'display:none;' }}">
            <label>Showing</label>
            <input type="text" id="pcNext" readonly tabindex="-1" style="background:var(--gray-100);font-weight:700;" value="{{ old('op') ? '' : '—' }}">
        </div>
        {{-- The showing this check answers: the latest unless another is picked (user, 2026-11-03). --}}
        <div class="form-group" data-pc-on="check" style="{{ $pcPane === 'check' ? '' : 'display:none;' }}">
            <label>Against Showing</label>
            <select name="showing" id="pcAgainst" data-no-search data-old="{{ old('showing') }}" @disabled($pcPane !== 'check')><option value="">—</option></select>
            <p class="helper-text" id="pcAgainstHint"></p>
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
            <label style="display:flex;align-items:center;gap:7px;margin-top:8px;font-weight:500;">
                <input type="checkbox" id="pcNoOp" style="width:auto;">
                OP is not captured — capture it now
            </label>
        </div>
        {{-- Property order: Plot No., District, LGA, Location (backfilled from the OP). --}}
        <div class="form-group"><label>Allottee</label><input type="text" id="pcAllottee" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>Plot No.</label><input type="text" id="pcPlot" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>District</label><input type="text" id="pcDistrict" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group"><label>LGA</label><input type="text" id="pcLga" readonly tabindex="-1" style="background:var(--gray-100);"></div>
        <div class="form-group span-2"><label>Location</label><input type="text" id="pcLocation" readonly tabindex="-1" style="background:var(--gray-100);"></div>
    </div>

    {{-- Plot Showing tab --}}
    <fieldset class="pc-pane" id="pcPaneShow" {{ $pcPane === 'show' ? '' : 'disabled hidden' }}>
    <div class="form-grid cols-3">
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
    </fieldset>

    {{-- Ground Check tab: the app's check of an OP's plot (a layout plot with no OP stays on the app). --}}
    <fieldset class="pc-pane" id="pcPaneCheck" {{ $pcPane === 'check' ? '' : 'disabled hidden' }}>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-map-marked-alt"></i><span>Ground Check</span></div>
        <div class="form-group">
            <label>Date <span class="required">*</span></label>
            <input type="date" name="checked_on" value="{{ old('checked_on', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
        </div>
        <div class="form-group">
            <label>Time</label>
            <input type="time" name="checked_at" value="{{ old('checked_at', now()->format('H:i')) }}">
        </div>
        <div class="form-group">
            <label>Surveyor <span class="required">*</span></label>
            <select name="surveyor" id="pcCheckSurveyor" required>
                <option value="">— Select surveyor —</option>
                @foreach ($surveyors as $s)
                    <option value="{{ $s->id }}" @selected(old('surveyor') === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>
                @endforeach
                <option value="other" @selected(old('surveyor') === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="surveyor_other" id="pcCheckSurveyorOther" maxlength="255" placeholder="Specify the surveyor's name *"
                   value="{{ old('surveyor_other') }}" style="margin-top:6px;{{ old('surveyor') === 'other' ? '' : 'display:none;' }}" @if (old('surveyor') === 'other') required @endif>
        </div>
        <div class="form-group full">
            <label>Beacon numbers found on the plot</label>
            <input type="text" name="beacons_found" maxlength="1000" value="{{ old('beacons_found') }}" placeholder="e.g. KN4010, KN4011, KN4012">
            <p class="helper-text">Each is checked against the Beacon Register when saved.</p>
        </div>
        <div class="form-group">
            <label>Result <span class="required">*</span></label>
            <select name="result" id="pcResult" required>
                <option value="">— Select —</option>
                @foreach (Check::RESULTS as $x)<option value="{{ $x }}" @selected(old('result') === $x)>{{ $x }}</option>@endforeach
            </select>
        </div>
        <div class="form-group" id="pcDiscBox" style="{{ old('result') === Check::DISCREPANCY ? '' : 'display:none;' }}">
            <label>What is wrong? <span class="required">*</span></label>
            <select name="discrepancy" id="pcDisc" @disabled(old('result') !== Check::DISCREPANCY) @if (old('result') === Check::DISCREPANCY) required @endif>
                <option value="">— Select —</option>
                @foreach (Check::DISCREPANCIES as $x)<option value="{{ $x }}" @selected(old('discrepancy') === $x)>{{ $x === Check::OTHER ? 'Other (specify)' : $x }}</option>@endforeach
            </select>
            <input type="text" name="discrepancy_other" id="pcDiscOther" maxlength="255" placeholder="Describe it *" value="{{ old('discrepancy_other') }}"
                   style="margin-top:6px;{{ old('result') === Check::DISCREPANCY && old('discrepancy') === Check::OTHER ? '' : 'display:none;' }}"
                   @disabled(old('discrepancy') !== Check::OTHER) @if (old('result') === Check::DISCREPANCY && old('discrepancy') === Check::OTHER) required @endif>
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
    </fieldset>

    <div class="form-actions">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('pcShowForm').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
        <div class="right"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <span id="pcSaveLabel">{{ $pcPane === 'check' ? 'Save Ground Check' : 'Save Showing' }}</span></button></div>
    </div>
</form>

<div id="pcOpCaptureModal" class="pc-op-modal" role="dialog" aria-modal="true" aria-labelledby="pcOpCaptureTitle" style="display:none;">
    <div class="pc-op-modal-card">
        <div class="pc-op-modal-heading">
            <div>
                <h3 id="pcOpCaptureTitle">Capture Occupancy Permit</h3>
                <p>Save the OP to PRA. The saved OP and property details will be added to Plot Showing.</p>
            </div>
            <button type="button" id="pcOpCaptureClose" class="pc-op-modal-close" aria-label="Close">&times;</button>
        </div>
        <form id="pcOpCaptureForm" data-store="{{ route('survey-module.compensation.plot-confirmations.ops.store') }}"
              data-file-search="{{ route('survey-module.compensation.plot-confirmations.file-search') }}"
              data-file-property="{{ url('/api/pra/v1/records/property-by-file') }}">
            @csrf
            <div id="pcOpCaptureErrors" class="comp-type-note" style="display:none;margin-bottom:12px;border-color:#fecaca;background:#fef2f2;color:#991b1b;"></div>
            <div class="pc-op-modal-grid">
                <div class="form-group pc-op-modal-full" style="position:relative;">
                    <label for="pcOpFileSearch">File Number <span class="pc-op-optional">(optional)</span></label>
                    <input type="search" id="pcOpFileSearch" autocomplete="off" placeholder="Search and select a file number">
                    <input type="hidden" name="file_number" id="pcOpFileNumber">
                    <div id="pcOpFileResults" class="pc-op-file-results" style="display:none;"></div>
                    <div id="pcOpFileSelected" class="pc-op-file-selected" style="display:none;"></div>
                    <p class="helper-text">If you leave this empty, a temporary file number will be generated when you save.</p>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureType">OP Type</label>
                    <select id="pcOpCaptureType" name="op_type">
                        <option value="">— Select type —</option>
                        <option value="Resettlement">Resettlement</option>
                        <option value="Direct Allocation">Direct Allocation</option>
                        <option value="Other">Other (specify)</option>
                    </select>
                </div>
                <div class="form-group" id="pcOpCaptureTypeOtherBox" style="display:none;">
                    <label for="pcOpCaptureTypeOther">Specify OP Type <span class="required">*</span></label>
                    <input type="text" id="pcOpCaptureTypeOther" name="op_type_other" maxlength="100" disabled>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureSerial">OP Serial Number <span class="required">*</span></label>
                    <input type="text" id="pcOpCaptureSerial" name="op_serial_number" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureDate">Transaction / Commencement Date</label>
                    <input type="date" id="pcOpCaptureDate" name="transaction_date">
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureLandUse">Land Use</label>
                    <select id="pcOpCaptureLandUse" name="land_use">
                        <option value="">— Select land use —</option>
                        @foreach ($opCaptureLandUses as $landUse)
                            <option value="{{ $landUse }}">{{ $landUse }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureGrantor">Grantor <span class="required">*</span></label>
                    <input type="text" id="pcOpCaptureGrantor" name="grantor" value="KANO STATE GOVERNMENT" maxlength="255" readonly required>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureGrantee">Grantee / Allottee <span class="required">*</span></label>
                    <input type="text" id="pcOpCaptureGrantee" name="grantee" maxlength="255" required>
                </div>
                <div class="pc-op-registration-row">
                    <div class="form-group">
                        <label for="pcOpCaptureRegSerial">Serial No.</label>
                        <input type="text" id="pcOpCaptureRegSerial" name="serialNo" inputmode="numeric" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="pcOpCapturePageDisplay">Page No.</label>
                        <input type="text" id="pcOpCapturePageDisplay" disabled aria-describedby="pcOpCapturePageHelp">
                        <input type="hidden" id="pcOpCapturePage" name="pageNo">
                        <span id="pcOpCapturePageHelp" class="pc-op-optional">Same as Serial No.</span>
                    </div>
                    <div class="form-group">
                        <label for="pcOpCaptureVolume">Volume No.</label>
                        <input type="text" id="pcOpCaptureVolume" name="volumeNo" inputmode="numeric" maxlength="100">
                    </div>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureDeedsDate">Deeds Date</label>
                    <input type="date" id="pcOpCaptureDeedsDate" name="deeds_date">
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureDeedsTime">Deeds Time</label>
                    <input type="time" id="pcOpCaptureDeedsTime" name="deeds_time">
                </div>
                <div class="form-group">
                    <label for="pcOpCapturePlotSize">Plot Size</label>
                    <input type="text" id="pcOpCapturePlotSize" name="plot_size" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="pcOpCapturePlot">Plot No. <span class="required">*</span></label>
                    <input type="text" id="pcOpCapturePlot" name="plot_no" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureTp">TP No. / Layout</label>
                    <input type="text" id="pcOpCaptureTp" name="tp_no" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureDistrict">District</label>
                    <select id="pcOpCaptureDistrict" name="district" class="sv-searchable" data-placeholder="Select or search district…">
                        <option value="">— Select district —</option>
                        @foreach ($opCaptureDistricts as $district)
                            <option value="{{ $district }}">{{ $district }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="pcOpCaptureLga">LGA</label>
                    <select id="pcOpCaptureLga" name="lga" class="sv-searchable" data-placeholder="Select LGA…">
                        <option value="">— Select LGA —</option>
                        @foreach ($opCaptureLgas as $lga)
                            <option value="{{ $lga }}">{{ $lga }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group pc-op-modal-full">
                    <label for="pcOpCaptureLocation">Location (auto)</label>
                    <input type="text" id="pcOpCaptureLocation" readonly style="background:var(--gray-100);font-weight:600;color:var(--primary);" placeholder="District, LGA, Kano State">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" id="pcOpCaptureCancel" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-success" id="pcOpCaptureSave"><i class="fas fa-check"></i> Save OP and Continue</button>
            </div>
        </form>
    </div>
</div>

<script>
// The buttons (user, 2026-11-06): the new form as Plot Showing or Ground Check, or the Old Register form.
function pcEntry(kind, pane) {
    var show = document.getElementById('pcShowForm'), old = document.getElementById('pcOldForm');
    if (!show || !old) return;
    if (kind === 'new') pcFormTab(pane || 'show');
    show.classList.toggle('open', kind === 'new');
    old.classList.toggle('open', kind === 'old');
}
// Plot Showing / Ground Check on the form: one OP block, the choice sets the title, what is asked, what is sent and where it saves.
function pcFormTab(pane) {
    var form = document.getElementById('pcShowForm'), check = pane === 'check';
    if (!form) return;
    form.setAttribute('action', check ? form.dataset.saveCheck : form.dataset.saveShowing);
    document.getElementById('pcFormKind').value = check ? 'check' : 'showing';
    [['pcPaneShow', !check], ['pcPaneCheck', check]].forEach(function (p) {
        var el = document.getElementById(p[0]);
        el.disabled = !p[1]; el.hidden = !p[1];
    });
    document.getElementById('pcAgainst').disabled = !check;
    form.querySelectorAll('[data-pc-on]').forEach(function (el) { el.style.display = el.dataset.pcOn === pane ? '' : 'none'; });
    var head = form.closest('.sv-modal') && form.closest('.sv-modal').querySelector('h4');
    if (head) head.textContent = check ? 'Ground Check' : 'Record Plot Showing';
    document.getElementById('pcSaveLabel').textContent = check ? 'Save Ground Check' : 'Save Showing';
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
    // Against Showing: the OP's showings, latest first. An OP never shown cannot be checked yet.
    var against = document.getElementById('pcAgainst'), againstHint = document.getElementById('pcAgainstHint');
    function fillAgainst(op) {
        var rows = op ? (op.showings || []) : [], keep = against.dataset.old;
        against.innerHTML = '';
        if (!rows.length) { var none = document.createElement('option'); none.value = ''; none.textContent = '—'; against.appendChild(none); }
        rows.forEach(function (s) { var o = document.createElement('option'); o.value = s.id; o.textContent = s.label; against.appendChild(o); });
        if (keep && rows.some(function (s) { return String(s.id) === keep; })) against.value = keep;
        against.dataset.old = '';
        againstHint.textContent = op && !rows.length ? 'This OP has not been shown yet: record the showing first.' : '';
    }
    function pick(op) {
        current = op;
        document.getElementById('pcNoOp').checked = false;
        fillAgainst(op);
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

    var noOp = document.getElementById('pcNoOp'), captureModal = document.getElementById('pcOpCaptureModal'),
        captureForm = document.getElementById('pcOpCaptureForm'), fileSearch = document.getElementById('pcOpFileSearch'),
        fileResults = document.getElementById('pcOpFileResults'), selectedFileBox = document.getElementById('pcOpFileSelected'),
        fileNumberInput = document.getElementById('pcOpFileNumber'), captureErrors = document.getElementById('pcOpCaptureErrors'),
        fileTimer = null, selectedFile = null, fileSearchPage = 1, fileSearchToken = 0;
    function syncCaptureLocation() {
        var district = document.getElementById('pcOpCaptureDistrict').value.trim(),
            lga = document.getElementById('pcOpCaptureLga').value.trim(),
            parts = [district, lga].filter(Boolean);
        document.getElementById('pcOpCaptureLocation').value = parts.length ? parts.concat('Kano State').join(', ') : '';
    }
    function normalizeLandUse(value) {
        var normalized = String(value || '').trim().toUpperCase().replace(/^CON[\s_-]+/, '').replace(/[\s_-]+RC$/, '');
        var choices = {
            AGRICULTURAL: 'AGRICULTURAL', AGRICULTURE: 'AGRICULTURAL', AG: 'AGRICULTURAL',
            COMMERCIAL: 'COMMERCIAL', COMM: 'COMMERCIAL', COM: 'COMMERCIAL',
            INDUSTRIAL: 'INDUSTRIAL', INDUSTRY: 'INDUSTRIAL', IND: 'INDUSTRIAL',
            RESIDENTIAL: 'RESIDENTIAL', RESIDENCIAL: 'RESIDENTIAL', RES: 'RESIDENTIAL'
        };
        return choices[normalized] || normalized;
    }
    function captureField(id, value) {
        var field = document.getElementById(id);
        if (!field) return;
        if (field.tagName === 'SELECT') {
            var wanted = id === 'pcOpCaptureLandUse' ? normalizeLandUse(value) : String(value || '').trim().toUpperCase();
            var option = Array.prototype.find.call(field.options, function (candidate) {
                var candidateValue = id === 'pcOpCaptureLandUse' ? normalizeLandUse(candidate.value) : candidate.value.trim().toUpperCase();
                return candidateValue === wanted;
            });
            field.value = option ? option.value : '';
            if (window.jQuery && jQuery.fn && jQuery.fn.select2 && jQuery(field).data('select2')) {
                jQuery(field).trigger('change.select2');
            }
        } else {
            field.value = value || '';
        }
        if (id === 'pcOpCaptureDistrict' || id === 'pcOpCaptureLga') syncCaptureLocation();
    }
    document.getElementById('pcOpCaptureDistrict').addEventListener('change', syncCaptureLocation);
    document.getElementById('pcOpCaptureLga').addEventListener('change', syncCaptureLocation);
    function openCaptureModal() {
        captureErrors.style.display = 'none';
        captureErrors.textContent = '';
        captureField('pcOpCapturePlot', document.getElementById('pcPlotSearch').value.trim() || document.getElementById('pcPlot').value);
        var groupSelect = document.getElementById('pcTpGroup');
        var groupOption = groupSelect.options[groupSelect.selectedIndex];
        captureField('pcOpCaptureTp', groupOption ? groupOption.dataset.tp : '');
        captureField('pcOpCaptureDistrict', document.getElementById('pcDistrict').value);
        captureField('pcOpCaptureLga', document.getElementById('pcLga').value);
        captureField('pcOpCaptureGrantee', document.getElementById('pcAllottee').value);
        captureModal.style.display = 'flex';
        document.getElementById('pcOpCapturePlot').focus();
    }
    function closeCaptureModal(resetChoice) {
        captureModal.style.display = 'none';
        if (resetChoice) noOp.checked = false;
    }
    noOp.addEventListener('change', function () {
        if (noOp.checked) {
            openCaptureModal();
            clearPick();
        } else {
            closeCaptureModal(false);
        }
    });
    document.getElementById('pcOpCaptureClose').addEventListener('click', function () { closeCaptureModal(true); });
    document.getElementById('pcOpCaptureCancel').addEventListener('click', function () { closeCaptureModal(true); });
    captureModal.addEventListener('click', function (event) {
        if (event.target === captureModal) closeCaptureModal(true);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && captureModal.style.display !== 'none') closeCaptureModal(true);
    });

    var captureType = document.getElementById('pcOpCaptureType'),
        captureTypeOther = document.getElementById('pcOpCaptureTypeOther'),
        captureTypeOtherBox = document.getElementById('pcOpCaptureTypeOtherBox');
    captureType.addEventListener('change', function () {
        var otherType = captureType.value === 'Other';
        captureTypeOtherBox.style.display = otherType ? '' : 'none';
        captureTypeOther.disabled = !otherType;
        captureTypeOther.required = otherType;
        if (!otherType) captureTypeOther.value = '';
    });
    var regSerialInput = document.getElementById('pcOpCaptureRegSerial'),
        pageDisplay = document.getElementById('pcOpCapturePageDisplay'),
        pageValue = document.getElementById('pcOpCapturePage');
    function syncRegistrationPage() {
        pageDisplay.value = regSerialInput.value;
        pageValue.value = regSerialInput.value;
    }
    regSerialInput.addEventListener('input', syncRegistrationPage);

    function selectCaptureFile(file) {
        selectedFile = file;
        fileNumberInput.value = file.file_number;
        fileSearch.value = '';
        fileSearch.style.display = 'none';
        fileResults.style.display = 'none';
        selectedFileBox.textContent = '';
        var text = document.createElement('span');
        text.textContent = file.file_number + (file.applicant_name ? ' · ' + file.applicant_name : '');
        selectedFileBox.appendChild(text);
        var clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'btn btn-secondary btn-sm';
        clear.textContent = 'Clear';
        clear.addEventListener('click', clearCaptureFile);
        selectedFileBox.appendChild(clear);
        selectedFileBox.style.display = 'flex';
        if (file.land_use) captureField('pcOpCaptureLandUse', file.land_use);
        if (file.applicant_name && !document.getElementById('pcOpCaptureGrantee').value) {
            captureField('pcOpCaptureGrantee', file.applicant_name);
        }
        fetch(captureForm.dataset.fileProperty + '/' + encodeURIComponent(file.file_number), { headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('Could not load property details for the selected file.');
                return response.json();
            })
            .then(function (payload) {
                var property = payload.data;
                if (!property) return;
                [
                    ['pcOpCapturePlot', property.plot_no],
                    ['pcOpCaptureTp', property.tp_no],
                    ['pcOpCaptureDistrict', property.district],
                    ['pcOpCaptureLga', property.lga],
                    ['pcOpCaptureLandUse', property.land_use],
                    ['pcOpCaptureGrantee', property.owner_name],
                    ['pcOpCapturePlotSize', property.plot_size],
                ].forEach(function (pair) {
                    if (!document.getElementById(pair[0]).value && pair[1]) captureField(pair[0], pair[1]);
                });
            }).catch(function (error) {
                captureErrors.textContent = error.message;
                captureErrors.style.display = '';
            });
    }
    function clearCaptureFile() {
        selectedFile = null;
        fileNumberInput.value = '';
        selectedFileBox.style.display = 'none';
        selectedFileBox.textContent = '';
        fileSearch.style.display = '';
        fileSearch.value = '';
    }
    fileSearch.addEventListener('input', function () {
        clearTimeout(fileTimer);
        var term = fileSearch.value.trim();
        fileSearchToken++;
        fileSearchPage = 1;
        if (term.length < 2) { fileResults.style.display = 'none'; return; }
        fileTimer = setTimeout(function () {
            searchCaptureFiles(term, 1, false, fileSearchToken);
        }, 250);
    });
    function searchCaptureFiles(term, page, append, token) {
        fetch(captureForm.dataset.fileSearch + '?search=' + encodeURIComponent(term) + '&limit=20&page=' + page, { headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('File-number search failed.');
                return response.json();
            })
            .then(function (payload) {
                if (token !== fileSearchToken || term !== fileSearch.value.trim()) return;
                var previousLoadMore = fileResults.querySelector('.pc-op-file-load-more');
                if (previousLoadMore) previousLoadMore.remove();
                if (!append) fileResults.textContent = '';
                (payload.files || []).forEach(function (file) {
                    var option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'pc-op-file-result';
                    option.textContent = file.file_number;
                    option.addEventListener('click', function () { selectCaptureFile(file); });
                    fileResults.appendChild(option);
                });
                if (!append && (!payload.files || !payload.files.length)) {
                    var empty = document.createElement('div');
                    empty.className = 'pc-op-file-empty';
                    empty.textContent = 'No indexed file numbers found.';
                    fileResults.appendChild(empty);
                }
                if (payload.has_more) {
                    var loadMore = document.createElement('button');
                    loadMore.type = 'button';
                    loadMore.className = 'pc-op-file-result pc-op-file-load-more';
                    loadMore.textContent = 'Load more file numbers…';
                    loadMore.addEventListener('click', function () {
                        fileSearchPage = page + 1;
                        searchCaptureFiles(term, fileSearchPage, true, token);
                    });
                    fileResults.appendChild(loadMore);
                }
                fileResults.style.display = '';
            })
            .catch(function () {
                if (token !== fileSearchToken) return;
                if (!append) fileResults.textContent = '';
                var error = document.createElement('div');
                error.className = 'pc-op-file-empty';
                error.textContent = 'File-number search failed. Try again.';
                fileResults.appendChild(error);
                fileResults.style.display = '';
            });
    }
    document.addEventListener('click', function (event) {
        if (!fileResults.contains(event.target) && event.target !== fileSearch) fileResults.style.display = 'none';
    });
    captureForm.addEventListener('submit', function (event) {
        event.preventDefault();
        captureErrors.style.display = 'none';
        captureErrors.textContent = '';
        if (fileSearch.value.trim() && !fileNumberInput.value) {
            captureErrors.textContent = 'Choose a file number from the search results, or clear the search to generate a temporary file number.';
            captureErrors.style.display = '';
            return;
        }
        var save = document.getElementById('pcOpCaptureSave');
        save.disabled = true;
        fetch(captureForm.dataset.store, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': captureForm.querySelector('[name="_token"]').value,
            },
            body: new FormData(captureForm),
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    var errors = payload.errors || {};
                    var message = Object.keys(errors).map(function (key) { return errors[key][0]; }).filter(Boolean)[0]
                        || payload.message || 'The Occupancy Permit could not be saved.';
                    throw new Error(message);
                }
                return payload;
            });
        }).then(function (payload) {
            if (!payload.success || !payload.op) throw new Error('The saved OP could not be loaded into Plot Showing.');
            closeCaptureModal(true);
            pick(payload.op);
            document.getElementById('pcPlotSearch').value = payload.op.plot_no || '';
            if (selectedFile) {
                captureErrors.textContent = 'OP saved with file number ' + (payload.op.file_number || selectedFile.file_number) + '.';
            }
            captureForm.reset();
            clearCaptureFile();
            captureType.dispatchEvent(new Event('change'));
            syncRegistrationPage();
        }).catch(function (error) {
            captureErrors.textContent = error.message;
            captureErrors.style.display = '';
        }).finally(function () {
            save.disabled = false;
        });
    });

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
        fillAgainst(null);
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
    // Ground Check: the same Other, and a Discrepancy asks what is wrong (exactly one Other, with a required specify).
    var csv = document.getElementById('pcCheckSurveyor'), cOther = document.getElementById('pcCheckSurveyorOther'),
        res = document.getElementById('pcResult'), discBox = document.getElementById('pcDiscBox'),
        disc = document.getElementById('pcDisc'), discOther = document.getElementById('pcDiscOther');
    function syncCheckOther() { var on = csv.value === 'other'; cOther.style.display = on ? '' : 'none'; cOther.required = on; if (!on) cOther.value = ''; }
    function syncDisc() {
        var bad = res.value === 'Discrepancy', other = bad && disc.value === 'Other';
        discBox.style.display = bad ? '' : 'none'; disc.disabled = !bad; disc.required = bad;
        if (!bad) disc.value = '';
        discOther.style.display = other ? '' : 'none'; discOther.disabled = !other; discOther.required = other;
        if (!other) discOther.value = '';
    }
    if (window.jQuery) window.jQuery(csv).on('change', syncCheckOther); else csv.addEventListener('change', syncCheckOther);
    res.addEventListener('change', syncDisc);
    disc.addEventListener('change', syncDisc);
    form.addEventListener('submit', function (e) {
        var checking = document.getElementById('pcFormKind').value === 'check';
        if (!document.getElementById('pcOp').value) { e.preventDefault(); plotBox.style.borderColor = '#dc2626'; box.style.borderColor = '#dc2626'; alert(checking ? 'Choose the TP No. and the plot that was checked.' : 'Choose the TP No. and the plot that was shown.'); return; }
        if (checking && !against.value) { e.preventDefault(); alert('Record the OP showing first: the ground check is the response to a showing.'); }
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
    .pc-op-modal { position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.62); }
    .pc-op-modal-card { width:min(940px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:12px;box-shadow:0 24px 70px rgba(0,0,0,.3);padding:22px; }
    .pc-op-modal-heading { display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--gray-200,#e5e7eb); }
    .pc-op-modal-heading h3 { margin:0;color:var(--gray-900,#111827);font-size:20px;font-weight:700; }
    .pc-op-modal-heading p { margin:5px 0 0;color:var(--gray-500,#6b7280);font-size:13px; }
    .pc-op-modal-close { border:0;background:transparent;color:var(--gray-500,#6b7280);font-size:28px;line-height:1;cursor:pointer; }
    .pc-op-modal-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 14px; }
    .pc-op-modal-full { grid-column:1/-1; }
    .pc-op-registration-row { grid-column:1/-1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 14px; }
    .pc-op-modal .form-group input,.pc-op-modal .form-group select { width:100%; }
    .pc-op-modal .form-actions { display:flex;justify-content:flex-end;gap:8px;margin-top:8px; }
    .pc-op-optional { color:var(--gray-500,#6b7280);font-size:12px;font-weight:400; }
    .pc-op-inline-check { display:flex;align-items:center;gap:7px;margin-top:6px;color:var(--gray-600,#4b5563);font-size:12px;font-weight:400; }
    .pc-op-inline-check input { width:auto!important; }
    .pc-op-file-results { position:absolute;z-index:20;top:100%;left:0;right:0;max-height:220px;overflow:auto;background:#fff;border:1px solid var(--gray-200,#e5e7eb);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12); }
    .pc-op-file-result { display:block;width:100%;padding:9px 11px;text-align:left;background:#fff;border:0;border-bottom:1px solid var(--gray-100,#f3f4f6);cursor:pointer; }
    .pc-op-file-result:hover { background:var(--gray-50,#f9fafb); }
    .pc-op-file-empty { padding:10px 12px;color:var(--gray-500,#6b7280);font-size:13px; }
    .pc-op-file-selected { justify-content:space-between;align-items:center;gap:10px;margin-top:7px;padding:8px 10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;color:#166534;font-size:13px; }
    @media(max-width:640px) {
        .pc-op-modal { padding:8px;align-items:flex-start; }
        .pc-op-modal-card { max-height:96vh;padding:15px; }
        .pc-op-modal-grid { grid-template-columns:1fr; }
        .pc-op-modal-full { grid-column:auto; }
        .pc-op-registration-row { grid-template-columns:1fr; }
    }
    .survey-proto .pc-tabs { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
    .survey-proto .pc-tabs a, .survey-proto .pc-tabs button { padding:8px 14px; border-radius:8px; border:1.5px solid var(--gray-300,#d1d5db); background:#fff; cursor:pointer; font-weight:600; font-size:14px; color:var(--gray-700,#374151); text-decoration:none; }
    .survey-proto .pc-tabs a.on, .survey-proto .pc-tabs button.on { background:var(--primary); border-color:var(--primary); color:#fff; }
    .survey-proto .pc-pane { border:0; margin:0; padding:0; min-width:0; }
    .survey-proto .pc-sub { font-size:12px; color:var(--gray-500); }
    .survey-proto .pc-photos { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .survey-proto .pc-photos img { width:120px; height:90px; object-fit:cover; border-radius:8px; border:1px solid var(--gray-200,#e5e7eb); }
    .survey-proto .pc-kv { display:grid; grid-template-columns: 140px 1fr; gap:6px 12px; font-size:14px; }
    .survey-proto .pc-kv dt { color:var(--gray-500); }
    .survey-proto .pc-kv dd { margin:0; font-weight:600; }
</style>

<div x-data="{ view: null }">
<div class="pc-tabs">
    <a href="{{ $tabUrl('showings') }}" data-tab="showings" class="{{ $tab === 'showings' ? 'on' : '' }}">Plot Showing</a>
</div>

<div id="pcMain">
<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Showings</div><div class="kpi-value">{{ number_format($totals['showings']) }}</div><div class="pc-sub">{{ number_format($totals['ops']) }} OPs shown</div></div>
    <div class="kpi-card"><div class="kpi-label">Old register</div><div class="kpi-value">{{ number_format($totals['old']) }}</div><div class="pc-sub">from the old book</div></div>
    <div class="kpi-card"><div class="kpi-label">Ground checks</div><div class="kpi-value">{{ number_format($totals['checks']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Discrepancies</div><div class="kpi-value" style="color:#b91c1c;">{{ number_format($totals['discrepancies']) }}</div></div>
    {{-- Which register this is, and how it is recorded (user, 2026-11-03). --}}
    @php
      [$regName, $regIcon, $regHow] = match ($tab) {
          'checks' => ['Ground Check', 'fa-map-marked-alt', $isGkn ? 'The Ground Check button, or the Plot Confirmation app in the field.' : 'View only: recorded under GKN.'],
          default  => ['Plot Showing', 'fa-street-view', $isGkn ? 'Record Plot Showing, or the Plot Confirmation app in the field.' : 'View only: recorded under GKN.'],
      };
    @endphp
    <div class="kpi-card" style="border-left:4px solid var(--primary, #2563eb);"><div class="kpi-label">Register</div>
        <div style="font-size:16px;font-weight:700;color:var(--primary, #2563eb);"><i class="fas {{ $regIcon }}" style="font-size:14px;margin-right:6px;"></i>{{ $regName }}</div>
        <div class="pc-sub">{{ $regHow }}</div></div>
</div>

{{-- Below the cards. Record Plot Showing keeps its two choices, New and Old Register; Ground Check is separate. --}}
@if ($isGkn)
<div class="page-header" style="margin-bottom:10px;align-items:flex-start;">
    {{-- Export Records on the left (user, 2026-11-06). --}}
    <div>@include('survey_module.partials._records_export', ['endpoint' => route($isGkn ? 'survey-module.gkn.plot-showing.export' : 'survey-module.compensation.plot-confirmations.export'), 'title' => 'Plot Showing Register', 'dateLabel' => $tab === 'checks' ? 'Date Checked' : 'Date Shown', 'onclick' => "pcExport('" . request('tab', 'showings') . "', 'Plot Showing Register')"])</div>
    <div style="display:flex;flex-direction:column;align-items:stretch;gap:8px;">
        <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
            <button type="button" class="btn btn-primary" style="width:100%;justify-content:center;" aria-haspopup="true" :aria-expanded="open" @click.stop="open = !open">
                <i class="fas fa-plus"></i> Record Plot Showing <i class="fas fa-chevron-down" style="font-size:11px;margin-left:4px;"></i>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" role="menu" class="absolute right-0 z-50 w-48 mt-1 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                <button type="button" role="menuitem" class="{{ $svItem }}" @click.stop="open = false; pcEntry('new', 'show')"><i class="fas fa-plus" style="width:16px;color:var(--gray-500);"></i> New</button>
                <button type="button" role="menuitem" class="{{ $svItem }}" @click.stop="open = false; pcEntry('old')"><i class="fas fa-book" style="width:16px;color:var(--gray-500);"></i> Old Register</button>
            </div>
        </div>
        <button type="button" class="btn" style="justify-content:center;background:#b45309;border-color:#b45309;color:#fff;" onclick="pcEntry('new', 'check')"><i class="fas fa-map-marked-alt"></i> Ground Check</button>
    </div>
</div>
@endif

@unless ($isGkn)
<div class="page-header" style="margin-bottom:10px;"><div>@include('survey_module.partials._records_export', ['endpoint' => route($isGkn ? 'survey-module.gkn.plot-showing.export' : 'survey-module.compensation.plot-confirmations.export'), 'title' => 'Plot Showing Register', 'dateLabel' => $tab === 'checks' ? 'Date Checked' : 'Date Shown', 'onclick' => "pcExport('" . request('tab', 'showings') . "', 'Plot Showing Register')"])</div><div></div></div>
@endunless

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

@push('scripts')
<script>
    /* One export modal for the page: the button says which list it opens on. */
    function pcExport(tab, title) {
        window.recordsExportConfig.params.tab = tab;
        window.recordsExportConfig.reportTitle = title;
        window.recordsExportConfig.filename = 'Survey_' + title.replace(/\s+/g, '_');
        document.getElementById('records-export-title').textContent = 'Export ' + title;
        openRecordsExportModal();
    }
</script>
@endpush

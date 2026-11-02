@php
    $months   = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $quarters = [1 => '1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];

    // The form is the create form, or the edit form when ?edit=<id> (or a failed edit) brought us here.
    $e        = $editing;
    $formOpen = $e || $errors->any() || request()->boolean('new');
    $val      = fn ($field, $default = null) => old($field, $e ? $e->{$field} : $default);
    $dateVal  = fn ($field, $default = null) => old($field, $e && $e->{$field} ? $e->{$field}->format('Y-m-d') : $default);
    $keep     = request()->only(['q', 'layout', 'year', 'quarter', 'month', 'status', 'page']);
    $layoutTypes = \App\Models\Survey\SurveyBeaconRegister::LAYOUT_TYPES;

    // Module role 'Survey - Beacon Register' (user, 2026-10-23): show only what the user may do.
    $brCan = collect(['create', 'edit', 'approve', 'delete'])
        ->mapWithKeys(fn ($a) => [$a => (bool) optional(auth()->user())->canDo('Survey - Beacon Register', $a)])->all();
    $brItem   = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $userName = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : null;

    // Which dropdown entry the form starts on. Entries saved without a source are shown as Other.
    $surveyorChoice = old('surveyor_choice', $e ? match ($e->surveyor_source) {
        'staff'  => 'staff:' . $e->surveyors_list_id,
        'surcon' => 'surcon:' . $e->cadastral_surveyor_id,
        default  => 'other',
    } : '');
@endphp

<style>
    .survey-proto .br-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 20px; }
    .survey-proto .br-section { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; padding: 12px 16px 16px; margin: 0 0 16px; }
    .survey-proto .br-section > legend { padding: 0 6px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--primary, #0f766e); }
    .survey-proto .br-section-address { padding-top: 14px; }
    .survey-proto .br-section-address .address-builder { margin: 0; padding: 0; border: 0; background: none; }
    .survey-proto .br-section-address .ab-legend { font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--primary, #0f766e); margin-bottom: 10px; }
    .survey-proto .br-grid .span-2 { grid-column: span 2; }
    .survey-proto .br-grid label { display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; }
    .survey-proto .br-grid input, .survey-proto .br-grid select, .survey-proto .br-grid textarea {
        width: 100%; padding: 8px 12px; border: 1.5px solid var(--gray-300); border-radius: var(--radius-sm);
        font-family: var(--font); font-size: 14px; background: #fff;
    }
    .survey-proto .br-grid .is-invalid { border-color: var(--danger); }
    .survey-proto .br-sub { font-size: 12px; color: var(--gray-500); }
    .survey-proto .br-fileno { margin-bottom: 14px; }
    .survey-proto .br-fileno label { display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; }
    .survey-proto .br-file-list { display: flex; flex-wrap: wrap; gap: 6px; min-height: 42px; padding: 6px 8px; border: 1.5px solid var(--gray-300); border-radius: var(--radius-sm); background: var(--gray-50, #f9fafb); align-items: center; }
    .survey-proto .br-file-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 10px; border-radius: 999px; background: var(--primary-50, #f0fdfa); border: 1px solid var(--primary-100, #ccfbf1); color: var(--primary); font-size: 13px; font-weight: 600; }
    .survey-proto .br-file-x { background: none; border: 0; padding: 0 4px; font-size: 16px; line-height: 1; color: var(--primary); cursor: pointer; }
    .survey-proto .br-file-x:hover { color: var(--danger, #b91c1c); }
    .survey-proto .br-file-empty { font-size: 13px; color: var(--gray-400, #9ca3af); }
    /* District, LGA and State are optional here (user, 2026-10-23); the shared builder still marks them. */
    .survey-proto .br-section-address .form-group:not(.ab-other) label .required { display: none; }
    .survey-proto .br-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; border-radius: 8px; }
    .survey-proto .br-layout.is-invalid { outline: 1.5px solid var(--danger); outline-offset: 4px; }
    .survey-proto .br-layout-opt { position: relative; cursor: pointer; margin: 0; }
    .survey-proto .br-layout-opt input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .survey-proto .br-layout-opt span { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1.5px solid var(--gray-300); border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--gray-700); background: #fff; }
    .survey-proto .br-layout-opt span i { color: var(--gray-400); }
    .survey-proto .br-layout-opt input:checked + span { border-color: var(--primary); background: var(--primary-50, #f0fdfa); color: var(--primary); font-weight: 600; }
    .survey-proto .br-layout-opt input:checked + span i { color: var(--primary); }
    .survey-proto .br-layout-opt input:focus-visible + span { box-shadow: 0 0 0 3px var(--primary-100, #ccfbf1); }
    @media (max-width: 640px) { .survey-proto .br-layout { grid-template-columns: 1fr; } }
    .survey-proto .br-preview { margin-top: 12px; padding: 10px 12px; background: var(--gray-50, #f9fafb); border: 1px dashed var(--gray-300); border-radius: 6px; }
    .survey-proto .br-preview-head { font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 6px; }
    .survey-proto .br-chips { display: flex; flex-wrap: wrap; gap: 4px; max-height: 140px; overflow-y: auto; }
    .survey-proto .br-chip { font: 600 12px/1 var(--font); padding: 4px 7px; border-radius: 4px; background: #fff; border: 1px solid var(--gray-300); color: var(--gray-800); }
    .survey-proto .br-chip-gap { border: 0; background: none; }
    .survey-proto .br-expand { display: block; margin-top: 2px; background: none; border: 0; padding: 0; font-size: 12px; color: var(--primary); cursor: pointer; }
    .survey-proto tr.br-num-row td { background: var(--gray-50, #f9fafb); font-size: 13px; color: var(--gray-700); padding-top: 6px; padding-bottom: 6px; }
    .survey-proto tr.br-num-row .br-num-sn { padding-left: 22px; color: var(--gray-500); }
    .survey-proto tr.br-num-row .br-num-no { padding-left: 14px; border-left: 2px solid var(--primary-100, #ccfbf1); font-weight: 600; color: var(--gray-900); }
    /* LGA names show in capitals (display only; the stored value matches the LGA list). */
    .survey-proto .br-section-address .ab-lga + .select2 .select2-selection__choice { text-transform: uppercase; }
    .survey-proto .br-tabs { display: flex; gap: 4px; margin-bottom: 10px; border-bottom: 1px solid var(--gray-200, #e5e7eb); }
    .survey-proto .br-tabs a { padding: 8px 14px; font-size: 14px; color: var(--gray-600, #4b5563); border-bottom: 2px solid transparent; margin-bottom: -1px; text-decoration: none; }
    .survey-proto .br-tabs a.on { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
    .survey-proto .br-head { display:flex; flex-wrap:wrap; gap:8px 24px; font-size:13px; color:var(--gray-700); margin-bottom:12px; }
    .survey-proto .br-head strong { color: var(--gray-900); }
    .br-modal { display: none; position: fixed; inset: 0; z-index: 1000; align-items: flex-start; justify-content: center; padding: 4vh 16px; }
    .br-modal.open { display: flex; }
    .br-modal-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .5); }
    .br-modal-dialog { position: relative; background: #fff; border-radius: 10px; box-shadow: 0 20px 50px rgba(0,0,0,.25);
                       width: 100%; max-width: 980px; max-height: 92vh; display: flex; flex-direction: column; }
    .br-modal-dialog form { display: flex; flex-direction: column; min-height: 0; flex: 1; }
    .br-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid var(--gray-200, #e5e7eb); }
    .br-modal-header h4 { margin: 0; font-size: 16px; font-weight: 600; }
    .br-modal-x { background: none; border: 0; font-size: 26px; line-height: 1; color: var(--gray-500, #6b7280); cursor: pointer; padding: 0 4px; }
    .br-modal-x:hover { color: var(--gray-900, #111827); }
    .br-modal-body { padding: 18px 20px; overflow-y: auto; }
    .br-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--gray-200, #e5e7eb); background: var(--gray-50, #f9fafb); border-radius: 0 0 10px 10px; }
    @media (max-width: 1024px) { .survey-proto .br-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px)  { .survey-proto .br-grid { grid-template-columns: 1fr; } .survey-proto .br-grid .span-2 { grid-column: auto; } }
</style>

@include('survey_module.partials._flash')

{{-- The three buttons together on the right, each its own colour (user, 2026-11-02). --}}
<style>
    .br-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; }
    .br-actions .btn { color: #fff; border: 0; }
    .br-actions .btn:hover { filter: brightness(.92); color: #fff; }
    .br-actions .br-btn-print { background: #475569; }
    .br-actions .br-btn-issue { background: #d97706; }
    .br-actions .br-btn-new { background: #0f766e; }
</style>
<div class="page-header">
    <div></div>
    <div class="br-actions">
        {{-- The register as the printed book, for the current filters (2026-10-25). --}}
        <a href="{{ route('survey-module.workflow.beacon-register.print', request()->only(['q', 'layout', 'year', 'quarter', 'month', 'status'])) }}"
           target="_blank" class="btn btn-sm br-btn-print"><i class="fas fa-print"></i> Print Register</a>
        @if ($brCan['create'])
            {{-- Issue Beacon No (WhatsApp, user 2026-10-31; groups 2026-11-02). --}}
            @unless ($e)
                <button type="button" class="btn btn-sm br-btn-issue" onclick="brIssueOpen()">
                    <i class="fas fa-hashtag"></i> Issue Beacon No
                </button>
            @endunless
            <button type="button" class="btn btn-sm br-btn-new" onclick="brOpenNew()">
                <i class="fas fa-plus"></i> New Entry
            </button>
        @endif
    </div>
</div>

@if ($brCan['create'] && ! $e)
@php
    $brPrefixes = \App\Services\Survey\SurveySettings::beaconPrefixes();
@endphp
<style>
    #brIssue .bi-sec { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; padding: 10px 14px 4px; margin: 0 0 12px; }
    #brIssue .bi-sec > legend { padding: 0 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--primary, #0f766e); }
    #brIssue .bi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0 16px; }
    #brIssue .bi-grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 0 16px; align-items: start; }
    @media (max-width: 900px) { #brIssue .bi-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 600px) { #brIssue .bi-grid, #brIssue .bi-grid-2 { grid-template-columns: 1fr; } }
    #brIssue .br-layout.is-invalid { outline: 1.5px solid var(--danger, #b91c1c); outline-offset: 4px; }
    #brIssue .bi-table { width: 100%; font-size: 13px; border-collapse: collapse; }
    #brIssue .bi-table th { position: sticky; top: 0; z-index: 1; background: var(--gray-50, #f9fafb); text-align: left; padding: 7px 10px; font-size: 11.5px; font-weight: 600; text-transform: uppercase; color: var(--gray-600, #4b5563); border-bottom: 1px solid var(--gray-200, #e5e7eb); white-space: nowrap; }
    #brIssue .bi-table td { padding: 6px 10px; border-bottom: 1px solid var(--gray-100, #f3f4f6); vertical-align: top; }
    #brIssue .bi-table tr.bi-first td { border-top: 2px solid var(--gray-300, #d1d5db); }
    #brIssue .bi-table td.bi-no { font-weight: 600; white-space: nowrap; }
    #brIssue .bi-menu { position: fixed; z-index: 1100; width: 200px; background: #fff; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,.12); padding: 4px 0; }
    #brIssue .bi-menu button { display: flex; align-items: center; gap: 8px; width: 100%; padding: 8px 12px; background: none; border: 0; text-align: left; font-size: 13px; color: var(--gray-700, #374151); cursor: pointer; }
    #brIssue .bi-menu button:hover { background: var(--gray-50, #f9fafb); }
    #brIssueAdd:disabled, #brIssueSave:disabled { opacity: .5; cursor: not-allowed; }
</style>
<div id="brIssue" class="br-modal" role="dialog" aria-modal="true" aria-labelledby="brIssueTitle">
  {{-- Clicking outside does not close it (user, 2026-11-02): Cancel or the x only. --}}
  <div class="br-modal-backdrop"></div>
  <div class="br-modal-dialog" style="max-width:1200px;">
    <div class="br-modal-header">
        <h4 id="brIssueTitle">Issue Beacon No</h4>
        <button type="button" class="br-modal-x" aria-label="Close" onclick="brIssueClose()">&times;</button>
    </div>
    <div style="padding:16px 20px;overflow-y:auto;flex:1;min-height:0;">
        @if ($brPrefixes->isEmpty())
            <p class="helper-text">No beacon prefix is set yet. Add them in Configurable Entities &gt; Survey &gt; Beacon Prefixes.</p>
        @else
        {{-- Several groups in one go (user, 2026-11-02): each run of numbers with its own type, surveyor and
             location is added to the table at the bottom; Save records every group as its own register entry. --}}
        <fieldset class="bi-sec">
            <legend>Beacon Type &amp; Date</legend>
            <div class="bi-grid-2">
                <div class="form-group">
                    <label>Beacon Type <span class="required">*</span></label>
                    <div class="br-layout" id="brIssueType" role="radiogroup" aria-label="Beacon type">
                        @foreach ($layoutTypes as $key => $label)
                            <label class="br-layout-opt">
                                <input type="radio" name="br_issue_type" value="{{ $key }}" onchange="document.getElementById('brIssueType').classList.remove('is-invalid')" />
                                <span><i class="fas {{ $key === 'Government' ? 'fa-landmark' : 'fa-user' }}"></i> {{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="form-group">
                    <label>Issue Date <span class="required">*</span></label>
                    <input type="date" id="brIssueOn" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" />
                    <p class="helper-text">The same date for every group.</p>
                </div>
            </div>
        </fieldset>

        <fieldset class="bi-sec">
            <legend>Beacon Numbers</legend>
            <div class="bi-grid">
                <div class="form-group">
                    <label>Beacon Prefix <span class="required">*</span></label>
                    <select id="brIssuePrefix" onchange="brIssueNext()">
                        <option value="">Select prefix…</option>
                        @foreach ($brPrefixes as $p)<option value="{{ $p->prefix }}">{{ $p->prefix }}{{ $p->description ? ' · ' . $p->description : '' }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Number of Beacons <span class="required">*</span></label>
                    <input type="number" id="brIssueCount" min="1" max="{{ \App\Models\Survey\SurveyBeaconRegister::MAX_RANGE }}" value="4" oninput="brIssuePreview()" />
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                        <input type="checkbox" id="brIssueAuto" checked style="width:auto;" onchange="brIssueMode()" /> Automatic serial
                    </label>
                    <input type="number" id="brIssueStart" min="1" placeholder="Start serial" disabled oninput="brIssuePreview()" />
                    <p class="helper-text" id="brIssueNextNote">Choose a prefix to get the next serial.</p>
                </div>
                <div class="form-group">
                    <label>Alphabet after the serial</label>
                    <input type="text" id="brIssueSuffix" maxlength="3" placeholder="Optional, e.g. B" style="text-transform:uppercase;" oninput="this.value = this.value.replace(/[^a-z]/gi, ''); brIssueNext()" />
                </div>
            </div>
            {{-- Duplicate check: against the register and against the groups already in the table. --}}
            <p id="brIssuePreview" style="margin:0 0 2px;font-weight:700;"></p>
            <p id="brIssueDupNote" class="helper-text" style="margin:0 0 8px;"></p>
        </fieldset>

        <fieldset class="bi-sec">
            <legend>Surveyor</legend>
            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label>Surveyor</label>
                    <select id="brIssueSurveyor" onchange="document.getElementById('brIssueSurveyorOtherWrap').style.display = this.value === 'other' ? '' : 'none'">
                        <option value="">Select surveyor…</option>
                        @if (count($surveyors['staff']))
                            <optgroup label="Survey Department staff">
                                @foreach ($surveyors['staff'] as $key => $s)<option value="{{ $key }}" data-name="{{ $s['name'] }}">{{ $s['name'] }}{{ $s['hint'] ? ' — ' . $s['hint'] : '' }}</option>@endforeach
                            </optgroup>
                        @endif
                        @if (count($surveyors['surcon']))
                            <optgroup label="Registered surveyors (SURCON)">
                                @foreach ($surveyors['surcon'] as $key => $s)<option value="{{ $key }}" data-name="{{ $s['name'] }}">{{ $s['name'] }}{{ $s['hint'] ? ' — ' . $s['hint'] : '' }}</option>@endforeach
                            </optgroup>
                        @endif
                        <option value="other">Other (specify)</option>
                    </select>
                </div>
                <div class="form-group" id="brIssueSurveyorOtherWrap" style="display:none;">
                    <label>Specify surveyor's name <span class="required">*</span></label>
                    <input type="text" id="brIssueSurveyorOther" maxlength="255" />
                </div>
            </div>
        </fieldset>

        <div class="br-section-address bi-sec" style="padding-top:12px;">
            @include('survey_module.partials._address_builder', [
                'prefix' => 'issue_',
                'mode'   => 'property',
                'model'  => null,
                'legend' => 'Beacon Location',
            ])
        </div>

        <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;margin:0 0 14px;flex-wrap:wrap;">
            <span id="brIssueAddMsg" class="helper-text" style="color:var(--danger, #b91c1c);margin:0;"></span>
            <button type="button" class="btn btn-primary" id="brIssueAdd" onclick="brIssueAdd()"><i class="fas fa-plus"></i> Add to table</button>
        </div>

        {{-- The table is last (user, 2026-11-02): one row per beacon number, grouped by type, surveyor and location. --}}
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
            <strong style="font-size:14px;">Beacons to issue</strong>
            <span id="brIssueSummary" class="helper-text" style="margin:0;"></span>
        </div>
        <div id="brIssueTableWrap" data-column-picker="off" style="max-height:340px;overflow:auto;border:1px solid var(--gray-200,#e5e7eb);border-radius:8px;">
            <table class="bi-table">
                <thead>
                    <tr><th style="width:48px;">S/N</th><th>Beacon No.</th><th>Type</th><th>Surveyor</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th style="width:44px;"></th></tr>
                </thead>
                <tbody id="brIssueRows"></tbody>
            </table>
        </div>
        @endif
    </div>
    <div class="form-actions" style="padding:12px 20px;border-top:1px solid var(--gray-200,#e5e7eb);margin:0;">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="brIssueClose()">Cancel</button></div>
        <div class="right" style="display:flex;align-items:center;gap:12px;">
            <span id="brIssueSaveMsg" class="helper-text" style="color:var(--danger, #b91c1c);margin:0;"></span>
            @unless ($brPrefixes->isEmpty())<button type="button" class="btn btn-success" id="brIssueSave" onclick="brIssueSave()" disabled><i class="fas fa-save"></i> <span>Save</span></button>@endunless
        </div>
    </div>
  </div>
</div>
@endif

{{-- Entry modal: create, or edit when ?edit=<id>. Reopens after a validation error. --}}
<div id="brModal" class="br-modal {{ $formOpen ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="brModalTitle">
  <div class="br-modal-backdrop" onclick="brClose()"></div>
  <div class="br-modal-dialog">
    <div class="br-modal-header">
        <h4 id="brModalTitle">{{ $e ? 'Edit entry ' . $e->issued_range : 'New beacon issue' }}</h4>
        <button type="button" class="br-modal-x" aria-label="Close" onclick="brClose()">&times;</button>
    </div>
<form method="POST" id="brForm" autocomplete="off"
      action="{{ $e ? route('survey-module.workflow.beacon-register.update', $e) : route('survey-module.workflow.beacon-register.store') }}">
    @csrf
    @if ($e)
        @method('PUT')
        @foreach ($keep as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}" />
        @endforeach
    @endif

    <div class="br-modal-body">
    @if ($errors->any())
        <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;margin-bottom:14px;display:block;">
            <ul style="margin:0 0 0 18px;">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 0. Layout type: the first check (user, 2026-10-23) --}}
    <fieldset class="br-section">
        <legend>Type <span class="required">*</span></legend>
        <div class="br-layout @error('layout_type') is-invalid @enderror" role="radiogroup" aria-label="Type">
            @foreach ($layoutTypes as $key => $label)
                <label class="br-layout-opt">
                    <input type="radio" name="layout_type" value="{{ $key }}" required @checked($val('layout_type') === $key) />
                    <span><i class="fas {{ $key === 'Government' ? 'fa-landmark' : 'fa-user' }}"></i> {{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    {{-- 1. Issue details --}}
    <fieldset class="br-section">
        <legend>Issue Details</legend>
        {{-- File numbers: several per line, each picked with the global selector, never typed; optional. --}}
        @php
            $brFiles = old('file_numbers', $e ? $e->fileNumberList() : []);
            $brFiles = array_values(array_filter(array_map('trim', (array) $brFiles), 'strlen'));
        @endphp
        <div class="br-fileno" id="brFilePicker" data-lookup="{{ route('survey-module.gkn.lookup') }}">
            <label>File Numbers</label>
            <div class="br-file-list" id="brFileList" aria-live="polite">
                @foreach ($brFiles as $fno)
                    <span class="br-file-chip" data-no="{{ $fno }}">
                        <input type="hidden" name="file_numbers[]" value="{{ $fno }}" />
                        <span>{{ $fno }}</span>
                        <button type="button" class="br-file-x" aria-label="Remove {{ $fno }}" title="Remove">&times;</button>
                    </span>
                @endforeach
                <span class="br-file-empty" id="brFileEmpty" @if (count($brFiles)) hidden @endif>No file number added.</span>
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="brFilePick" style="margin-top:8px;"><i class="fas fa-plus"></i> Add file number</button>
            <div class="br-sub" id="brFileStatus">Optional. Add as many as apply. The first file picked fills the plot, district and LGA from its records.</div>
        </div>
        <div class="br-grid">
            <div>
                <label>Date <span class="required">*</span></label>
                <input type="date" name="issue_date" id="brIssueDate" value="{{ $dateVal('issue_date', now()->format('Y-m-d')) }}"
                       max="{{ now()->format('Y-m-d') }}" required class="@error('issue_date') is-invalid @enderror" />
                <div class="br-sub" id="brPeriod"></div>
            </div>
            <div>
                <label>Register No.</label>
                <input type="text" name="register_no" value="{{ $val('register_no') }}" placeholder="e.g. 201827" maxlength="30" />
                <div class="br-sub">The printed page number</div>
            </div>
            <div>
                <label>Plan No.</label>
                <input type="text" name="plan_no" value="{{ $val('plan_no') }}" maxlength="100" />
            </div>
        </div>
    </fieldset>

    {{-- 2. Beacons issued --}}
    <fieldset class="br-section">
        <legend>Beacons Issued</legend>
        <div class="br-grid">
            <div>
                <label>From <span class="required">*</span></label>
                <input type="text" name="beacon_from" id="brFrom" value="{{ $val('beacon_from') }}" placeholder="e.g. C9302B" maxlength="30"
                       required class="@error('beacon_from') is-invalid @enderror" oninput="brCount()" style="text-transform:uppercase;" />
            </div>
            <div>
                <label>To</label>
                <input type="text" name="beacon_to" id="brTo" value="{{ $val('beacon_to') }}" placeholder="e.g. C9306B" maxlength="30"
                       class="@error('beacon_to') is-invalid @enderror" oninput="brCount()" style="text-transform:uppercase;" />
                <div class="br-sub">Leave blank for a single beacon</div>
            </div>
            <div>
                <label>Total</label>
                <input type="text" id="brTotal" value="{{ $val('total_issued') }}" readonly tabindex="-1"
                       style="background:var(--gray-100);font-weight:600;" />
                <div class="br-sub" id="brTotalHint">Counted from the range</div>
            </div>
        </div>
        {{-- Every number the range expands to; each is saved as its own record against this surveyor. --}}
        <div class="br-preview" id="brPreview" hidden>
            <div class="br-preview-head" id="brPreviewHead"></div>
            <div class="br-chips" id="brPreviewChips"></div>
        </div>
    </fieldset>

    {{-- 3. Surveyor --}}
    <fieldset class="br-section">
        <legend>Surveyor</legend>
        <div class="br-grid">
            <div>
                <label>Surveyor's Name</label>
                <select name="surveyor_choice" id="brSurveyor" onchange="brSurveyorChanged()"
                        class="@error('surveyor_choice') is-invalid @enderror">
                    <option value="">Select surveyor…</option>
                    @if (count($surveyors['staff']))
                        <optgroup label="Survey Department staff">
                            @foreach ($surveyors['staff'] as $key => $s)
                                <option value="{{ $key }}" data-reg="" @selected($surveyorChoice === $key)>{{ $s['name'] }}{{ $s['hint'] ? ' — ' . $s['hint'] : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if (count($surveyors['surcon']))
                        <optgroup label="Registered surveyors (SURCON)">
                            @foreach ($surveyors['surcon'] as $key => $s)
                                <option value="{{ $key }}" data-reg="{{ $s['reg'] }}" @selected($surveyorChoice === $key)>{{ $s['name'] }}{{ $s['hint'] ? ' — ' . $s['hint'] : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    <option value="other" @selected($surveyorChoice === 'other')>Other</option>
                </select>
            </div>
            <div id="brSurveyorOtherWrap">
                <label>Specify surveyor's name</label>
                <input type="text" name="surveyor_name_other" id="brSurveyorOther" maxlength="255"
                       value="{{ old('surveyor_name_other', $surveyorChoice === 'other' && $e ? $e->surveyor_name : null) }}"
                       class="@error('surveyor_name_other') is-invalid @enderror" />
            </div>
            <div>
                <label>Registration</label>
                <input type="text" name="surveyor_registration" id="brReg" value="{{ $val('surveyor_registration') }}" maxlength="50"
                       placeholder="SURCON no." />
            </div>
        </div>
    </fieldset>

    {{-- 4. Location: the standard address builder (prop_* columns) --}}
    <fieldset class="br-section br-section-address">
        @include('survey_module.partials._address_builder', [
            'prefix' => 'prop_',
            'mode'   => 'property',
            'model'  => $e,
            'legend' => 'Beacon Location',
        ])
    </fieldset>

    {{-- 4b. Coordinates --}}
    <fieldset class="br-section">
        <legend>Coordinates</legend>
        <div class="br-grid">
            <div>
                <label>X (Easting)</label>
                <input type="number" name="coord_x" value="{{ $val('coord_x') !== null ? (float) $val('coord_x') : '' }}" step="any" inputmode="decimal"
                       placeholder="e.g. 452345.120" class="@error('coord_x') is-invalid @enderror" />
            </div>
            <div>
                <label>Y (Northing)</label>
                <input type="number" name="coord_y" value="{{ $val('coord_y') !== null ? (float) $val('coord_y') : '' }}" step="any" inputmode="decimal"
                       placeholder="e.g. 1325678.450" class="@error('coord_y') is-invalid @enderror" />
            </div>
        </div>
    </fieldset>

    {{-- 5. Payment --}}
    <fieldset class="br-section">
        <legend>Payment</legend>
        <div class="br-grid">
            <div>
                <label>Teller No.</label>
                <div style="display:flex;gap:6px;">
                    <input type="text" name="teller_no" id="brTeller" value="{{ $val('teller_no') }}" maxlength="100" style="flex:1;" />
                    {{-- Rev-M lookup (2026-10-25): fills Amount, Date Received and the file from the payment. --}}
                    <button type="button" class="btn btn-outline btn-sm" id="brRevm" data-url="{{ route('survey-module.workflow.beacon-register.revm-lookup') }}"
                            title="Fill from the Rev-M payment" style="white-space:nowrap;"><i class="fas fa-receipt"></i> Fill from Rev-M</button>
                </div>
                <div class="br-sub" id="brRevmStatus">Teller, transaction, receipt or bill number.</div>
            </div>
            <div>
                <label>Amount (₦)</label>
                <input type="number" name="amount" value="{{ $val('amount') }}" step="0.01" min="0" />
            </div>
            <div>
                <label>Date Received</label>
                <input type="date" name="date_received" value="{{ $dateVal('date_received') }}"
                       class="@error('date_received') is-invalid @enderror" />
            </div>
        </div>
    </fieldset>

    </div>{{-- .br-modal-body --}}

    <div class="br-modal-footer">
        <button class="btn btn-secondary btn-sm" type="button" onclick="brClose()">
            <i class="fas fa-times"></i> Cancel
        </button>
        <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save changes' : 'Save entry' }}</button>
    </div>
</form>
  </div>
</div>

{{-- Each figure split Government / Private (user, 2026-10-26); a type line filters the register to it. --}}
@php
    $brCards = [
        'entries'    => ['Entries', fn ($n) => number_format($n)],
        'beacons'    => ['Beacons Issued', fn ($n) => number_format($n)],
        'this_month' => ['Issued This Month', fn ($n) => number_format($n)],
        'amount'     => ['Amount Received', fn ($n) => '₦' . number_format($n, 2)],
    ];
    $brTypeLink = fn ($type) => route('survey-module.workflow.beacon-register.index',
        \Illuminate\Support\Arr::except(request()->query(), ['layout', 'page', 'edit', 'new']) + ['layout' => $type]);
@endphp
<style>
    .survey-proto .br-kpi-split { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--gray-200, #e5e7eb); display: grid; gap: 4px; }
    .survey-proto .br-kpi-split a { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 3px 6px; margin: 0 -6px;
        border-radius: 6px; font-size: 13px; color: var(--gray-700, #374151); text-decoration: none; }
    .survey-proto .br-kpi-split a:hover { background: var(--gray-50, #f9fafb); }
    .survey-proto .br-kpi-split a.on { background: var(--primary-50, #f0fdfa); color: var(--primary); font-weight: 600; }
    .survey-proto .br-kpi-split a span { display: inline-flex; align-items: center; gap: 6px; }
    .survey-proto .br-kpi-split a i { width: 14px; text-align: center; color: var(--gray-400, #9ca3af); }
    .survey-proto .br-kpi-split a.on i { color: var(--primary); }
    .survey-proto .br-kpi-split strong { color: var(--gray-900, #111827); font-weight: 600; }
</style>
<div class="kpi-grid">
    @foreach ($brCards as $key => [$label, $fmt])
        <div class="kpi-card">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ $fmt($stats[$key]) }}</div>
            <div class="br-kpi-split">
                @foreach ($layoutTypes as $type => $typeLabel)
                    <a href="{{ $brTypeLink($type) }}" class="{{ request('layout') === $type ? 'on' : '' }}" title="Show {{ $typeLabel }} entries">
                        <span><i class="fas {{ $type === 'Government' ? 'fa-landmark' : 'fa-user' }}"></i> {{ $typeLabel }}</span>
                        <strong>{{ $fmt($stats['by_type'][$type][$key] ?? 0) }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<div class="br-tabs" role="tablist">
    <a href="{{ route('survey-module.workflow.beacon-register.index', \Illuminate\Support\Arr::except(request()->query(), ['view', 'page', 'edit', 'new'])) }}"
       class="{{ $view === 'issues' ? 'on' : '' }}" role="tab" aria-selected="{{ $view === 'issues' ? 'true' : 'false' }}">
        <i class="fas fa-list"></i> By issue
    </a>
    <a href="{{ route('survey-module.workflow.beacon-register.index', \Illuminate\Support\Arr::except(request()->query(), ['page', 'edit', 'new']) + ['view' => 'numbers']) }}"
       class="{{ $view === 'numbers' ? 'on' : '' }}" role="tab" aria-selected="{{ $view === 'numbers' ? 'true' : 'false' }}">
        <i class="fas fa-th"></i> By beacon number
    </a>
</div>

<form method="GET" class="table-toolbar">
    @if ($view === 'numbers')
        <input type="hidden" name="view" value="numbers" />
    @endif
    <div class="left" style="flex-wrap:wrap;gap:8px;">
        @php $fs = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;'; @endphp
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Beacon no., surveyor, plan, teller…" style="{{ $fs }}width:240px;" />
        <select name="layout" style="{{ $fs }}">
            <option value="">All Types</option>
            @foreach ($layoutTypes as $key => $label)
                <option value="{{ $key }}" @selected(request('layout') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="year" style="{{ $fs }}">
            <option value="">All Years</option>
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected((string) request('year') === (string) $y)>{{ $y }}</option>
            @endforeach
        </select>
        <select name="quarter" style="{{ $fs }}">
            <option value="">All Quarters</option>
            @foreach ($quarters as $n => $label)
                <option value="{{ $n }}" @selected((string) request('quarter') === (string) $n)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="month" style="{{ $fs }}">
            <option value="">All Months</option>
            @foreach ($months as $n => $label)
                <option value="{{ $n }}" @selected((string) request('month') === (string) $n)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" style="{{ $fs }}">
            <option value="">All Status</option>
            <option value="pending" @selected(request('status') === 'pending')>Awaiting review</option>
            <option value="reviewed" @selected(request('status') === 'reviewed')>Reviewed</option>
            <option value="audited" @selected(request('status') === 'audited')>Audited</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'layout', 'year', 'quarter', 'month', 'status']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.workflow.beacon-register.index', $view === 'numbers' ? ['view' => 'numbers'] : []) }}">Clear</a>
        @endif
    </div>
</form>

<div class="br-head">
    <span>Quarter: <strong>{{ request('quarter') ? $quarters[(int) request('quarter')] ?? '—' : 'All' }}</strong></span>
    <span>Month: <strong>{{ request('month') ? $months[(int) request('month')] ?? '—' : 'All' }}</strong></span>
    <span>Total Number Issued Per Page: <strong>{{ number_format($pageBeacons) }}</strong></span>
    <span>Total for this selection: <strong>{{ number_format($filteredBeacons) }}</strong> beacons · <strong>₦{{ number_format($filteredAmount, 2) }}</strong></span>
</div>

@if ($view === 'issues')
<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Type</th>
                    <th>File No.</th>
                    <th>Register No.</th>
                    <th>Issued Number</th>
                    <th>Total</th>
                    <th>Plan No.</th>
                    <th>Surveyor's Name</th>
                    <th>Registration</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>X</th>
                    <th>Y</th>
                    <th>Teller No.</th>
                    <th>Amount (₦)</th>
                    <th>Date Received</th>
                    <th>Date Created</th>
                    <th>Status</th>
                    <th style="width:48px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $row)
                    <tr @if ($e && $e->id === $row->id) style="background:#fff8e1;" @endif>
                        <td>{{ $entries->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;">{{ $row->layout_type_label ?: '—' }}</td>
                        <td style="white-space:nowrap;">@forelse ($row->fileNumberList() as $fno)<div>{{ $fno }}</div>@empty — @endforelse</td>
                        <td>{{ $row->register_no ?: '—' }}</td>
                        <td style="white-space:nowrap;">
                            <strong>{{ $row->issued_range }}</strong>
                            @if ($row->numbers->count() > 1)
                                <button type="button" class="br-expand" onclick="brToggleNumbers({{ $row->id }}, this)" aria-expanded="false">
                                    <i class="fas fa-chevron-down"></i> {{ $row->numbers->count() }} numbers
                                </button>
                            @endif
                        </td>
                        <td>{{ number_format($row->total_issued) }}</td>
                        <td>{{ $row->plan_no ?: '—' }}</td>
                        <td>{{ $row->surveyor_name ?: '—' }}</td>
                        <td>{{ $row->surveyor_registration ?: '—' }}</td>
                        <td>{{ $row->prop_plot ?: '—' }}</td>
                        <td>{{ $row->resolvedDistrict('prop_') ?: '—' }}</td>
                        <td>{{ mb_strtoupper((string) \App\Support\AddressBuilder::resolve($row->prop_lga)) ?: '—' }}</td>
                        <td>{{ $row->resolvedStreet('prop_') ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $row->coord_x !== null ? rtrim(rtrim(number_format((float) $row->coord_x, 4, '.', ''), '0'), '.') : '—' }}</td>
                        <td style="white-space:nowrap;">{{ $row->coord_y !== null ? rtrim(rtrim(number_format((float) $row->coord_y, 4, '.', ''), '0'), '.') : '—' }}</td>
                        <td>
                            {{ $row->teller_no ?: '—' }}
                        </td>
                        <td style="text-align:right;">{{ $row->amount !== null ? number_format((float) $row->amount, 2) : '—' }}</td>
                        <td style="white-space:nowrap;">{{ optional($row->date_received)->format('d/m/Y') ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $row->issue_date->format('d/m/Y') }}</td>
                        <td style="white-space:nowrap;">
                            @if ($row->audited_at)
                                <span class="status-badge completed" title="Audited by {{ $userName($row->auditor) }} on {{ $row->audited_at->format('d/m/Y H:i') }}"><span class="dot"></span>Audited</span>
                            @elseif ($row->reviewed_at)
                                <span class="status-badge review" title="Reviewed by {{ $userName($row->reviewer) }} on {{ $row->reviewed_at->format('d/m/Y H:i') }}"><span class="dot"></span>Reviewed</span>
                            @else
                                <span class="status-badge pending"><span class="dot"></span>Entered</span>
                            @endif
                            <div class="br-sub">by {{ $userName($row->creator) ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @if (! $row->audited_at && $brCan['edit'])
                                        <a href="{{ route('survey-module.workflow.beacon-register.index', $keep + ['edit' => $row->id]) }}" class="{{ $brItem }}">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                        </a>
                                    @endif
                                    @if (! $row->reviewed_at && $brCan['approve'])
                                        <form method="POST" action="{{ route('survey-module.workflow.beacon-register.review', $row) }}">
                                            @csrf
                                            <button type="submit" class="{{ $brItem }}"><i data-lucide="check" class="w-4 h-4 text-gray-500"></i> Mark reviewed</button>
                                        </form>
                                    @elseif ($row->reviewed_at && ! $row->audited_at && $brCan['approve'])
                                        <form method="POST" action="{{ route('survey-module.workflow.beacon-register.audit', $row) }}">
                                            @csrf
                                            <button type="submit" class="{{ $brItem }}"><i data-lucide="check-check" class="w-4 h-4 text-gray-500"></i> Mark audited</button>
                                        </form>
                                    @endif
                                    @if ($row->audited_at)
                                        <div class="px-3 py-2 text-xs text-gray-500">Audited entries are locked.</div>
                                    @elseif ($brCan['delete'])
                                        <form method="POST" action="{{ route('survey-module.workflow.beacon-register.destroy', $row) }}"
                                              onsubmit="return confirm('Delete the entry {{ $row->issued_range }} for {{ addslashes($row->surveyor_name) }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $brItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i> Delete</button>
                                        </form>
                                    @endif
                                    @if (! $row->audited_at && ! $brCan['edit'] && ! $brCan['approve'] && ! $brCan['delete'])
                                        <div class="px-3 py-2 text-xs text-gray-500">No actions available to you.</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @if ($row->numbers->count() > 1)
                        {{-- One row per beacon record, in the issue row's columns. The amount
                             covers the whole line, so it is not repeated on each beacon. --}}
                        @php
                            $rowSn       = $entries->firstItem() + $loop->index;
                            $rowDistrict = $row->resolvedDistrict('prop_') ?: '—';
                            $rowLga      = mb_strtoupper((string) \App\Support\AddressBuilder::resolve($row->prop_lga)) ?: '—';
                            $rowStreet   = $row->resolvedStreet('prop_') ?: '—';
                        @endphp
                        @foreach ($row->numbers as $n)
                            <tr class="br-num-row" data-parent="{{ $row->id }}" hidden>
                                <td class="br-num-sn">{{ $rowSn }}.{{ $loop->iteration }}</td>
                                <td style="white-space:nowrap;">{{ $row->layout_type_label ?: '—' }}</td>
                                <td style="white-space:nowrap;">@forelse ($row->fileNumberList() as $fno)<div>{{ $fno }}</div>@empty — @endforelse</td>
                                <td>{{ $row->register_no ?: '—' }}</td>
                                <td style="white-space:nowrap;"><span class="br-num-no">{{ $n->beacon_no }}</span></td>
                                <td>1</td>
                                <td>{{ $row->plan_no ?: '—' }}</td>
                                <td>{{ $row->surveyor_name ?: '—' }}</td>
                                <td>{{ $row->surveyor_registration ?: '—' }}</td>
                                <td>{{ $row->prop_plot ?: '—' }}</td>
                                <td>{{ $rowDistrict }}</td>
                                <td>{{ $rowLga }}</td>
                                <td>{{ $rowStreet }}</td>
                                <td style="white-space:nowrap;">{{ $row->coord_x !== null ? rtrim(rtrim(number_format((float) $row->coord_x, 4, '.', ''), '0'), '.') : '—' }}</td>
                                <td style="white-space:nowrap;">{{ $row->coord_y !== null ? rtrim(rtrim(number_format((float) $row->coord_y, 4, '.', ''), '0'), '.') : '—' }}</td>
                                <td>{{ $row->teller_no ?: '—' }}</td>
                                <td style="text-align:right;color:var(--gray-400);">—</td>
                                <td style="white-space:nowrap;">{{ optional($row->date_received)->format('d/m/Y') ?: '—' }}</td>
                                <td style="white-space:nowrap;">{{ $row->issue_date->format('d/m/Y') }}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="21" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-map-pin" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No beacon issues recorded{{ request()->hasAny(['q', 'layout', 'year', 'quarter', 'month', 'status']) ? ' for this selection' : ' yet' }}.
                            @if ($brCan['create'])<a href="#" onclick="brOpenNew();return false;">Record one</a>.@endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $entries->firstItem() ?? 0 }}–{{ $entries->lastItem() ?? 0 }} of {{ number_format($entries->total()) }} entries ·
            {{ number_format($pageBeacons) }} beacons on this page
        </span>
        <div class="pagination">{{ $entries->links() }}</div>
    </div>
</div>
@else
{{-- By beacon number: one row per expanded number; the details are its issue line's. --}}
<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Beacon No.</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>File No.</th>
                    <th>Surveyor's Name</th>
                    <th>Registration</th>
                    <th>Plan No.</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>X</th>
                    <th>Y</th>
                    <th>Teller No.</th>
                    <th>Issue Entry</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($numbers as $n)
                    @php $line = $n->register; @endphp
                    <tr>
                        <td>{{ $numbers->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;"><strong>{{ $n->beacon_no }}</strong></td>
                        <td style="white-space:nowrap;">{{ optional($line?->issue_date)->format('d/m/Y') ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $line?->layout_type_label ?: '—' }}</td>
                        <td style="white-space:nowrap;">@forelse (($line ? $line->fileNumberList() : []) as $fno)<div>{{ $fno }}</div>@empty — @endforelse</td>
                        <td>{{ $line?->surveyor_name ?: '—' }}</td>
                        <td>{{ $line?->surveyor_registration ?: '—' }}</td>
                        <td>{{ $line?->plan_no ?: '—' }}</td>
                        <td>{{ $line?->prop_plot ?: '—' }}</td>
                        <td>{{ $line ? ($line->resolvedDistrict('prop_') ?: '—') : '—' }}</td>
                        <td>{{ $line ? (mb_strtoupper((string) \App\Support\AddressBuilder::resolve($line->prop_lga)) ?: '—') : '—' }}</td>
                        <td>{{ $line ? ($line->resolvedStreet('prop_') ?: '—') : '—' }}</td>
                        <td style="white-space:nowrap;">{{ $line?->coord_x !== null ? rtrim(rtrim(number_format((float) $line?->coord_x, 4, '.', ''), '0'), '.') : '—' }}</td>
                        <td style="white-space:nowrap;">{{ $line?->coord_y !== null ? rtrim(rtrim(number_format((float) $line?->coord_y, 4, '.', ''), '0'), '.') : '—' }}</td>
                        <td>{{ $line?->teller_no ?: '—' }}</td>
                        <td style="white-space:nowrap;">
                            @if ($line)
                                <a href="{{ route('survey-module.workflow.beacon-register.index', ['q' => $line->beacon_from]) }}">{{ $line->issued_range }}</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" style="text-align:center;padding:28px;color:var(--gray-500);">
                            No beacon numbers{{ request()->hasAny(['q', 'layout', 'year', 'quarter', 'month', 'status']) ? ' for this selection' : ' issued yet' }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $numbers->firstItem() ?? 0 }}–{{ $numbers->lastItem() ?? 0 }} of {{ number_format($numbers->total()) }} beacon numbers</span>
        <div class="pagination">{{ $numbers->links() }}</div>
    </div>
</div>
@endif

<script>
    // Own names (br*): _scripts.blade.php is pushed after this and must not collide.
    // The edit form is server-rendered, so leaving an edit (or starting a new entry
    // from one) reloads the page without ?edit; a new entry opens in place.
    var brCleanUrl = @json(route('survey-module.workflow.beacon-register.index', $keep));

    function brOpenNew() {
        @if ($e)
            window.location = brCleanUrl + (brCleanUrl.indexOf('?') === -1 ? '?' : '&') + 'new=1';
        @else
            brShow();
        @endif
    }

    function brShow() {
        document.getElementById('brModal').classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(function () {
            var first = document.querySelector('#brForm input[name="layout_type"]:checked') || document.querySelector('#brForm input[name="layout_type"]');
            if (first) first.focus();
        }, 50);
    }

    function brClose() {
        @if ($e)
            window.location = brCleanUrl;
        @else
            document.getElementById('brModal').classList.remove('open');
            document.body.style.overflow = '';
        @endif
    }

    // File numbers: several per line, each from the global selector. The first
    // file's records fill plot / district / LGA where they are still blank;
    // removing a file clears only what that file filled. Typed values stay.
    (function () {
        var root = document.getElementById('brFilePicker');
        if (!root) return;
        var form = document.getElementById('brForm');
        var list = document.getElementById('brFileList');
        var empty = document.getElementById('brFileEmpty');
        var status = document.getElementById('brFileStatus');
        var filledBy = {};   // upper-cased file number => fields it filled
        var $j = function () { return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null; };
        var field = function (n) { return form.querySelector('[name="' + n + '"]'); };
        var say = function (t, err) { status.textContent = t; status.style.color = err ? '#b91c1c' : ''; };
        var chips = function () { return Array.prototype.slice.call(list.querySelectorAll('.br-file-chip')); };
        var has = function (no) { return chips().some(function (c) { return c.dataset.no.toUpperCase() === no.toUpperCase(); }); };
        var refreshEmpty = function () { empty.hidden = chips().length > 0; };

        function setSelect(el, value) {
            if (!el || !value) return false;
            if (Array.prototype.some.call(el.options, function (o) { return o.selected; })) return false;
            var match = Array.prototype.find.call(el.options, function (o) { return o.value.toLowerCase() === String(value).toLowerCase(); });
            if (!match) { match = new Option(value, value); el.add(match); }
            match.selected = true;
            var $ = $j();
            if ($ && $(el).data('select2')) $(el).trigger('change'); else el.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }
        function setText(el, value) {
            if (!el || !value || el.value.trim() !== '') return false;
            el.value = value;
            return true;
        }
        function unfill(fields) {
            (fields || []).forEach(function (f) {
                if (f.tagName === 'SELECT') {
                    Array.prototype.forEach.call(f.options, function (o) { o.selected = false; });
                    var $ = $j();
                    if ($ && $(f).data('select2')) $(f).trigger('change');
                } else {
                    f.value = '';
                }
            });
        }

        function addChip(no) {
            var chip = document.createElement('span');
            chip.className = 'br-file-chip';
            chip.dataset.no = no;
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = 'file_numbers[]'; input.value = no;
            var label = document.createElement('span');
            label.textContent = no;
            var x = document.createElement('button');
            x.type = 'button'; x.className = 'br-file-x'; x.title = 'Remove';
            x.setAttribute('aria-label', 'Remove ' + no);
            x.innerHTML = '&times;';
            chip.append(input, label, x);
            list.insertBefore(chip, empty);
            refreshEmpty();
        }

        function add(no) {
            if (has(no)) { say(no + ' is already on this entry.', true); return; }
            addChip(no);
            var key = no.toUpperCase();
            filledBy[key] = [];
            say('Looking up ' + no + '…');
            fetch(root.dataset.lookup + '?file_number=' + encodeURIComponent(no), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!filledBy[key]) return;   // removed while loading
                    var p = field('prop_plot');
                    if (setText(p, d.plot)) filledBy[key].push(p);
                    var di = field('prop_district[]');
                    if (setSelect(di, d.district)) filledBy[key].push(di);
                    var lg = field('prop_lga[]');
                    if (setSelect(lg, d.lga)) filledBy[key].push(lg);
                    say(no + ' added.' + (filledBy[key].length ? ' Location filled from its records; check it below.' : ''));
                })
                .catch(function () { say(no + ' added. Its details could not be loaded.'); });
        }

        list.addEventListener('click', function (ev) {
            var x = ev.target.closest('.br-file-x');
            if (!x) return;
            var chip = x.closest('.br-file-chip');
            var key = chip.dataset.no.toUpperCase();
            unfill(filledBy[key]);
            delete filledBy[key];
            chip.remove();
            refreshEmpty();
            say(chip.dataset.no + ' removed.');
        });

        window.brAddFile = function (no) { if (no && !has(no)) add(no); };

        document.getElementById('brFilePick').addEventListener('click', function () {
            if (!window.GlobalFileNoModal) { say('The file number selector did not load. Refresh the page.', true); return; }
            window.GlobalFileNoModal.open({
                callback: function (data) {
                    var no = data && data.fileNumber ? String(data.fileNumber).trim() : '';
                    if (no) add(no);
                }
            });
        });
    })();

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && document.getElementById('brModal').classList.contains('open')) brClose();
    });

    // Rev-M lookup: a successful payment fills Amount (the bill's Beacons line), Date Received and
    // the payer's file where those are still blank. Nothing is saved until the form is.
    (function () {
        var btn = document.getElementById('brRevm');
        if (!btn) return;
        var form = document.getElementById('brForm');
        var status = document.getElementById('brRevmStatus');
        var say = function (t, bad) { status.textContent = t; status.style.color = bad ? '#b91c1c' : ''; };
        btn.addEventListener('click', function () {
            var ref = (document.getElementById('brTeller').value || '').trim();
            if (!ref) { say('Enter the teller, transaction, receipt or bill number first.', true); return; }
            say('Looking up ' + ref + ' in Rev-M…');
            fetch(btn.dataset.url + '?ref=' + encodeURIComponent(ref), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.found) { say(d.message || 'No Rev-M payment found.', true); return; }
                    var amount = form.querySelector('[name="amount"]'), received = form.querySelector('[name="date_received"]');
                    if (amount && d.amount) amount.value = Number(d.amount).toFixed(2);
                    if (received && !received.value && d.paid_on) received.value = d.paid_on;
                    if (d.file_number && window.brAddFile) window.brAddFile(d.file_number);
                    say('Bill ' + d.bill_ref + (d.paid ? ' (paid' + (d.paid_on ? ' ' + d.paid_on : '') + ')' : ' (not paid yet)') +
                        ' · ₦' + Number(d.amount).toLocaleString() + (d.is_beacon ? ' for beacons' : '') +
                        (d.payer ? ' · ' + d.payer : '') + (d.file_number ? ' · ' + d.file_number : ''), !d.paid);
                })
                .catch(function () { say('Rev-M could not be reached. Enter the amount by hand.', true); });
        });
    })();

    // Mirrors SurveyBeaconRegister::parseBeacon / parseRange / expand. The server re-checks on save.
    var BR_MAX = {{ \App\Models\Survey\SurveyBeaconRegister::MAX_RANGE }};
    function brParse(code) {
        var m = String(code || '').replace(/\s+/g, '').toUpperCase().match(/^([A-Z\/\-]*)(\d+)([A-Z]*)$/);
        return m ? { series: m[1] + '|' + m[3], n: parseInt(m[2], 10), prefix: m[1], suffix: m[3], width: m[2].length } : null;
    }
    function brFormat(a, n) {
        var d = String(n);
        while (d.length < a.width) d = '0' + d;
        return a.prefix + d + a.suffix;
    }

    // Issue Beacon No (user, 2026-10-31): PREFIX + serial + optional letter, a run of N numbers.
    // Groups (user, 2026-11-02): each run, with its type, surveyor and location, is added to the table;
    // Save records every group as its own register entry in one go. Nothing is saved before that.
    var brIssueServerNext = null, brIssueGroups = [], brIssueGid = 0, brIssueState = 'none', brIssueEditAt = null;
    var BR_ISSUE_URL = {
        next: @json(route('survey-module.workflow.beacon-register.next-serial')),
        check: @json(route('survey-module.workflow.beacon-register.check-range')),
        save: @json(route('survey-module.workflow.beacon-register.issue'))
    };
    function brIssueEl(id) { return document.getElementById(id); }
    function brIssueOpen() { brIssueEl('brIssue').classList.add('open'); document.body.style.overflow = 'hidden'; brIssueRender(); }
    function brIssueClose() {
        if (brIssueGroups.length && !confirm('Close and drop the beacons in the table? They have not been saved.')) return;
        brIssueGroups = []; brIssueEditAt = null; brIssueRender(); brIssueMenuClose();
        brIssueEl('brIssue').classList.remove('open'); document.body.style.overflow = '';
        brIssueMode();
    }
    function brIssueSeries() { return brIssueEl('brIssuePrefix').value + '|' + brIssueEl('brIssueSuffix').value.toUpperCase(); }
    // The highest serial of this prefix + letter already in the table (0 if none).
    function brIssuePendingMax(series) {
        return brIssueGroups.reduce(function (m, g) { return g.series === series ? Math.max(m, g.start + g.count - 1) : m; }, 0);
    }
    function brIssueMode() {
        var auto = brIssueEl('brIssueAuto').checked, start = brIssueEl('brIssueStart');
        start.disabled = auto;
        if (auto) {
            var pend = brIssuePendingMax(brIssueSeries());
            start.value = brIssueServerNext ? Math.max(brIssueServerNext, pend + 1) : '';
        }
        brIssuePreview();
    }
    function brIssueNext() {
        var prefix = brIssueEl('brIssuePrefix').value, suffix = brIssueEl('brIssueSuffix').value.toUpperCase();
        var note = brIssueEl('brIssueNextNote');
        brIssueServerNext = null;
        if (!prefix) { note.textContent = 'Choose a prefix to get the next serial.'; brIssueMode(); return; }
        note.textContent = 'Checking the last serial issued…';
        fetch(BR_ISSUE_URL.next + '?prefix=' + encodeURIComponent(prefix) + '&suffix=' + encodeURIComponent(suffix), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                brIssueServerNext = d.next;
                var pend = brIssuePendingMax(prefix + '|' + suffix);
                note.textContent = (d.last ? 'Last issued: ' + prefix + d.last + suffix + '.' : 'None issued yet under ' + prefix + (suffix ? ' … ' + suffix : '') + '.')
                    + (pend >= d.next ? ' In the table up to ' + prefix + pend + suffix + '.' : '')
                    + ' Next: ' + Math.max(d.next, pend + 1) + '.';
                brIssueMode();
            })
            .catch(function () { note.textContent = 'The last serial could not be checked; untick Automatic and enter it.'; });
    }
    function brIssueRange() {
        var prefix = brIssueEl('brIssuePrefix').value, suffix = brIssueEl('brIssueSuffix').value.toUpperCase();
        var count = parseInt(brIssueEl('brIssueCount').value, 10), start = parseInt(brIssueEl('brIssueStart').value, 10);
        if (!prefix || !(count > 0) || !(start > 0)) return null;
        return { prefix: prefix, suffix: suffix, series: prefix + '|' + suffix, start: start, count: count,
                 from: prefix + start + suffix, to: prefix + (start + count - 1) + suffix };
    }
    // Duplicate check (user, 2026-11-02): against the register (server) and the groups in the table.
    var brIssueTimer = null, brIssueSeq = 0;
    function brIssuePreview() {
        var r = brIssueRange(), p = brIssueEl('brIssuePreview'), note = brIssueEl('brIssueDupNote');
        p.textContent = r ? (r.count === 1 ? r.from : r.from + ' to ' + r.to) + ' (' + r.count + ' beacon' + (r.count === 1 ? '' : 's') + ')' : '';
        note.textContent = ''; note.style.color = '';
        clearTimeout(brIssueTimer);
        var mine = ++brIssueSeq;
        if (!r) { brIssueState = 'none'; return; }
        if (r.count > BR_MAX) { brIssueState = 'clash'; note.style.color = 'var(--danger, #b91c1c)'; note.textContent = 'At most ' + BR_MAX.toLocaleString() + ' beacons in one group.'; return; }

        var inTable = [];
        brIssueGroups.forEach(function (g, gi) {
            if (g.series !== r.series) return;
            var lo = Math.max(g.start, r.start), hi = Math.min(g.start + g.count - 1, r.start + r.count - 1);
            if (lo <= hi) inTable.push((lo === hi ? r.prefix + lo + r.suffix : r.prefix + lo + r.suffix + ' to ' + r.prefix + hi + r.suffix) + ' (group ' + (gi + 1) + ')');
        });
        brIssueState = 'checking';
        note.textContent = 'Checking for duplicates…';
        brIssueTimer = setTimeout(function () {
            var url = BR_ISSUE_URL.check + '?prefix=' + encodeURIComponent(r.prefix) + '&suffix=' + encodeURIComponent(r.suffix) + '&start=' + r.start + '&count=' + r.count;
            fetch(url, { headers: { Accept: 'application/json' } }).then(function (res) { return res.json(); }).then(function (d) {
                if (mine !== brIssueSeq) return;   // a newer run was asked for meanwhile
                var taken = d.taken || [], msgs = [];
                if (taken.length) {
                    var t = taken[0];
                    msgs.push(taken.length + ' already issued: ' + taken.slice(0, 6).map(function (x) { return x.no; }).join(', ') + (taken.length > 6 ? ', …' : '')
                        + ' (' + (t.surveyor ? 'to ' + t.surveyor + ' ' : '') + (t.date ? 'on ' + t.date : '') + (t.entry ? ', entry ' + t.entry : '') + ').');
                }
                if (inTable.length) msgs.push('Already in the table: ' + inTable.join('; ') + '.');
                brIssueState = msgs.length ? 'clash' : 'ok';
                note.style.color = msgs.length ? 'var(--danger, #b91c1c)' : '#15803d';
                note.textContent = msgs.length ? msgs.join(' ') + ' Change the start serial or the number of beacons.' : 'No duplicates: none of these numbers has been issued or added.';
            }).catch(function () {
                if (mine !== brIssueSeq) return;
                brIssueState = inTable.length ? 'clash' : 'unchecked';
                note.style.color = inTable.length ? 'var(--danger, #b91c1c)' : '';
                note.textContent = inTable.length ? 'Already in the table: ' + inTable.join('; ') + '.' : 'Could not check the register now; Save checks again.';
            });
        }, 300);
    }

    // The Beacon Location box (issue_ address builder): read, show and clear.
    function brIssueAddrRoot() { return document.querySelector('.address-builder[data-prefix="issue_"]'); }
    function brIssueAddr() {
        var root = brIssueAddrRoot();
        var multi = function (k) { var s = root.querySelector('select[name="issue_' + k + '[]"]'); return s ? Array.prototype.filter.call(s.options, function (o) { return o.selected && o.value; }).map(function (o) { return o.value; }) : []; };
        var txt = function (k) { var el = root.querySelector('[name="issue_' + k + '"]'); return el ? el.value.trim() : ''; };
        return { house: txt('house'), plot: txt('plot'), street: multi('street'), street_other: txt('street_other'),
                 district: multi('district'), district_other: txt('district_other'), lga: multi('lga'), state: txt('state') };
    }
    function brIssueShow(list, other) { return list.map(function (v) { return v === 'Other' ? (other || 'Other') : v; }).join(' & '); }
    function brIssueAddrClear() {
        var root = brIssueAddrRoot();
        ['street', 'district', 'lga'].forEach(function (k) {
            var s = root.querySelector('select[name="issue_' + k + '[]"]');
            if (!s) return;
            Array.prototype.forEach.call(s.options, function (o) { o.selected = false; });
            if (window.jQuery && jQuery(s).data('select2')) jQuery(s).trigger('change'); else s.dispatchEvent(new Event('change', { bubbles: true }));
        });
        ['house', 'plot', 'street_other', 'district_other'].forEach(function (k) {
            var el = root.querySelector('[name="issue_' + k + '"]');
            if (el) { el.value = ''; el.dispatchEvent(new Event('input', { bubbles: true })); }
        });
    }

    function brIssueAdd() {
        var msg = brIssueEl('brIssueAddMsg'), fail = function (t, el) { msg.textContent = t; if (el && el.focus) el.focus(); };
        msg.textContent = ''; brIssueEl('brIssueSaveMsg').textContent = '';
        var typeEl = document.querySelector('input[name="br_issue_type"]:checked');
        if (!typeEl) { brIssueEl('brIssueType').classList.add('is-invalid'); return fail('Choose the beacon type: Government or Private.'); }
        var r = brIssueRange();
        if (!r) return fail('Choose the prefix, the number of beacons and the start serial.', brIssueEl('brIssuePrefix'));
        if (brIssueState === 'checking') return fail('Still checking for duplicates; try again in a moment.');
        if (brIssueState === 'clash') return fail('Some of these numbers are taken; change the start serial or the number of beacons.', brIssueEl('brIssueCount'));
        var svSel = brIssueEl('brIssueSurveyor'), sv = svSel.value, svOther = brIssueEl('brIssueSurveyorOther').value.trim().replace(/\s+/g, ' ');
        if (sv === 'other' && !svOther) return fail("Type the surveyor's name.", brIssueEl('brIssueSurveyorOther'));
        var a = brIssueAddr();
        if (a.street.indexOf('Other') >= 0 && !a.street_other) return fail('Specify the street.');
        if (a.district.indexOf('Other') >= 0 && !a.district_other) return fail('Specify the district.');
        var opt = svSel.options[svSel.selectedIndex];

        // A group taken back with Edit returns to its place in the table.
        var at = brIssueEditAt === null ? brIssueGroups.length : Math.min(brIssueEditAt, brIssueGroups.length);
        brIssueEditAt = null;
        brIssueGroups.splice(at, 0, {
            id: ++brIssueGid, type: typeEl.value, series: r.series, prefix: r.prefix, suffix: r.suffix, start: r.start, count: r.count, from: r.from, to: r.to,
            auto: brIssueEl('brIssueAuto').checked,
            sv: sv, svOther: sv === 'other' ? svOther : '', svLabel: sv === 'other' ? svOther : (sv && opt ? (opt.dataset.name || opt.text) : ''),
            addr: a,
            show: { plot: a.plot || (a.house ? 'House ' + a.house : ''), district: brIssueShow(a.district, a.district_other),
                    lga: a.lga.join(' & ').toUpperCase(),
                    // Location = District, LGA, State (user, 2026-11-02), as the entry's stored location.
                    location: [brIssueShow(a.district, a.district_other), a.lga.join(' & ').toUpperCase(), a.state].filter(Boolean).join(', ') }
        });
        // Ready for the next group: same type, prefix and surveyor; the next serial; a fresh location.
        brIssueAddrClear();
        brIssueRender();
        brIssueMode();
        brIssueNext();
    }

    function brIssueTotal() { return brIssueGroups.reduce(function (n, g) { return n + g.count; }, 0); }
    function brIssueRender() {
        var body = brIssueEl('brIssueRows');
        if (!body) return;
        body.textContent = '';
        var sn = 0;
        brIssueGroups.forEach(function (g) {
            for (var i = 0; i < g.count; i++) {
                var tr = document.createElement('tr');
                if (i === 0) tr.className = 'bi-first';
                var cells = [++sn, g.prefix + (g.start + i) + g.suffix];
                cells.forEach(function (v, ci) { var td = document.createElement('td'); td.textContent = v; if (ci === 1) td.className = 'bi-no'; tr.appendChild(td); });
                if (i === 0) {
                    [g.type, g.svLabel || '—', g.show.plot || '—', g.show.district || '—', g.show.lga || '—', g.show.location || '—'].forEach(function (v) {
                        var td = document.createElement('td'); td.rowSpan = g.count; td.textContent = v; tr.appendChild(td);
                    });
                    var act = document.createElement('td'); act.rowSpan = g.count;
                    act.innerHTML = '<button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100" title="Actions" aria-label="Actions"><i data-lucide="more-vertical" class="w-5 h-5"></i></button>';
                    act.firstChild.addEventListener('click', function (ev) { ev.stopPropagation(); brIssueMenu(ev.currentTarget, g.id); });
                    tr.appendChild(act);
                }
                body.appendChild(tr);
            }
        });
        if (!sn) {
            body.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--gray-500);padding:18px 10px;">No beacons added yet. Fill in the type, numbers, surveyor and location above, then Add to table.</td></tr>';
        }
        var total = brIssueTotal(), groups = brIssueGroups.length;
        brIssueEl('brIssueSummary').textContent = groups ? total + ' beacon' + (total === 1 ? '' : 's') + ' in ' + groups + ' group' + (groups === 1 ? '' : 's') : '';
        var save = brIssueEl('brIssueSave');
        if (save) { save.disabled = !groups; save.querySelector('span').textContent = groups ? 'Save ' + total + ' beacon' + (total === 1 ? '' : 's') : 'Save'; }
        if (window.lucide && lucide.createIcons) lucide.createIcons();
    }

    // Row menu: Edit group (back into the form) / Remove group.
    function brIssueMenuClose() { var m = document.getElementById('brIssueMenu'); if (m) m.remove(); }
    function brIssueMenu(btn, gid) {
        var had = document.getElementById('brIssueMenu');
        brIssueMenuClose();
        if (had && had.dataset.gid == gid) return;
        var m = document.createElement('div');
        m.id = 'brIssueMenu'; m.className = 'bi-menu'; m.dataset.gid = gid; m.dataset.t = Date.now();
        m.innerHTML = '<button type="button" data-a="edit"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit group</button>'
            + '<button type="button" data-a="remove" style="color:#dc2626;"><i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i> Remove group</button>';
        brIssueEl('brIssue').appendChild(m);
        var rc = btn.getBoundingClientRect();
        m.style.top = Math.min(rc.bottom + 4, window.innerHeight - m.offsetHeight - 8) + 'px';
        m.style.left = Math.max(8, rc.right - 200) + 'px';
        m.addEventListener('click', function (ev) {
            var b = ev.target.closest('button');
            if (!b) return;
            ev.stopPropagation(); brIssueMenuClose();
            if (b.dataset.a === 'edit') brIssueEditGroup(gid); else brIssueRemoveGroup(gid);
        });
        if (window.lucide && lucide.createIcons) lucide.createIcons();
    }
    document.addEventListener('click', function (ev) { if (!ev.target.closest || !ev.target.closest('#brIssueMenu')) brIssueMenuClose(); });
    // A scroll closes it, but not the one that brought the button into view as it opened.
    document.addEventListener('scroll', function () { var m = document.getElementById('brIssueMenu'); if (m && Date.now() - m.dataset.t > 250) brIssueMenuClose(); }, true);
    function brIssueRemoveGroup(gid) {
        brIssueGroups = brIssueGroups.filter(function (g) { return g.id !== gid; });
        brIssueRender(); brIssueMode(); brIssueNext();
    }
    function brIssueSetSelect(sel, v) {
        sel.value = v;
        if (window.jQuery && jQuery(sel).data('select2')) jQuery(sel).trigger('change.select2');
    }
    function brIssueEditGroup(gid) {
        var g = brIssueGroups.filter(function (x) { return x.id === gid; })[0];
        if (!g) return;
        brIssueEditAt = brIssueGroups.indexOf(g);
        brIssueGroups = brIssueGroups.filter(function (x) { return x.id !== gid; });
        var radio = document.querySelector('input[name="br_issue_type"][value="' + g.type + '"]');
        if (radio) radio.checked = true;
        brIssueEl('brIssueSuffix').value = g.suffix;
        brIssueEl('brIssueCount').value = g.count;
        brIssueEl('brIssueAuto').checked = false;
        brIssueEl('brIssueStart').disabled = false;
        brIssueEl('brIssueStart').value = g.start;
        brIssueSetSelect(brIssueEl('brIssuePrefix'), g.prefix);
        var svSel = brIssueEl('brIssueSurveyor');
        brIssueSetSelect(svSel, g.sv);
        svSel.dispatchEvent(new Event('change'));
        brIssueEl('brIssueSurveyorOther').value = g.svOther;
        brIssueAddrClear();
        var root = brIssueAddrRoot();
        ['house', 'plot'].forEach(function (k) { var el = root.querySelector('[name="issue_' + k + '"]'); if (el) el.value = g.addr[k]; });
        if (window.fillAddressBuilder) window.fillAddressBuilder('issue_', g.addr);
        brIssueRender();
        brIssueNext();
        brIssueEl('brIssueAddMsg').textContent = 'Group ' + g.from + ' is back in the form: change it, then Add to table again.';
    }

    function brIssueSave() {
        var msg = brIssueEl('brIssueSaveMsg'), btn = brIssueEl('brIssueSave');
        msg.textContent = '';
        if (!brIssueGroups.length) { msg.textContent = 'Add at least one group to the table.'; return; }
        var on = brIssueEl('brIssueOn').value;
        if (!on) { msg.textContent = 'Enter the issue date.'; brIssueEl('brIssueOn').focus(); return; }
        var payload = {
            issue_date: on,
            groups: brIssueGroups.map(function (g) {
                return {
                    layout_type: g.type, beacon_from: g.from, beacon_to: g.count === 1 ? '' : g.to,
                    surveyor_choice: g.sv, surveyor_name_other: g.svOther,
                    prop_house: g.addr.house, prop_plot: g.addr.plot, prop_street: g.addr.street, prop_street_other: g.addr.street_other,
                    prop_district: g.addr.district, prop_district_other: g.addr.district_other, prop_lga: g.addr.lga, prop_state: g.addr.state
                };
            })
        };
        btn.disabled = true;
        fetch(BR_ISSUE_URL.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (d) { return { ok: res.ok, d: d }; });
        }).then(function (x) {
            if (x.ok && x.d.redirect) { brIssueGroups = []; window.location = x.d.redirect; return; }
            var errs = x.d.errors ? Object.keys(x.d.errors).map(function (k) { return x.d.errors[k][0]; }) : [];
            msg.textContent = errs[0] || x.d.message || 'Could not save. Nothing was recorded.';
            btn.disabled = false;
        }).catch(function () { msg.textContent = 'Could not reach the server. Nothing was recorded.'; btn.disabled = false; });
    }

    function brCount() {
        var from = document.getElementById('brFrom').value.trim(), to = document.getElementById('brTo').value.trim();
        var total = document.getElementById('brTotal'), hint = document.getElementById('brTotalHint');
        var box = document.getElementById('brPreview'), head = document.getElementById('brPreviewHead'), chips = document.getElementById('brPreviewChips');
        var bad = function (msg) {
            total.value = '';
            hint.textContent = msg; hint.style.color = msg ? 'var(--danger)' : '';
            box.hidden = true;
        };
        if (from === '') { bad(''); hint.textContent = 'Counted from the range'; return; }

        var a = brParse(from), b = to === '' ? a : brParse(to);
        if (!a) return bad('First number must be letters then digits, e.g. C9302B');
        if (!b) return bad('Last number must be letters then digits, e.g. C9306B');
        if (a.series !== b.series) return bad('Both numbers need the same letters, e.g. C9302B to C9306B');
        if (b.n < a.n) return bad('The last number is lower than the first');

        var count = b.n - a.n + 1;
        total.value = count;
        if (count > BR_MAX) return bad('Too many: one entry can hold at most ' + BR_MAX.toLocaleString());
        hint.textContent = 'Counted from the range'; hint.style.color = '';

        // Show every number up to 60; beyond that the first and last 25.
        var list = [];
        if (count <= 60) {
            for (var n = a.n; n <= b.n; n++) list.push(brFormat(a, n));
        } else {
            for (var x = a.n; x < a.n + 25; x++) list.push(brFormat(a, x));
            list.push('…');
            for (var y = b.n - 24; y <= b.n; y++) list.push(brFormat(a, y));
        }
        head.textContent = count === 1
            ? 'Will create 1 beacon record:'
            : 'Will create ' + count.toLocaleString() + ' beacon records, all against the surveyor below:';
        chips.innerHTML = '';
        list.forEach(function (t) {
            var c = document.createElement('span');
            c.className = t === '…' ? 'br-chip br-chip-gap' : 'br-chip';
            c.textContent = t;
            chips.appendChild(c);
        });
        box.hidden = false;
    }

    function brToggleNumbers(id, btn) {
        var rows = document.querySelectorAll('tr.br-num-row[data-parent="' + id + '"]');
        if (!rows.length) return;
        var show = rows[0].hidden;
        rows.forEach(function (r) { r.hidden = !show; });
        btn.setAttribute('aria-expanded', show ? 'true' : 'false');
        btn.querySelector('i').className = show ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    }

    // "Other" reveals the required name box. A SURCON surveyor fills their registration,
    // and changing the pick clears a registration that was filled for the previous one.
    var brAutoReg = null;
    function brSurveyorChanged(initial) {
        var sel = document.getElementById('brSurveyor');
        var other = sel.value === 'other';
        var wrap = document.getElementById('brSurveyorOtherWrap'), input = document.getElementById('brSurveyorOther');
        wrap.style.visibility = other ? 'visible' : 'hidden';
        input.disabled = !other;
        if (other && !initial) input.focus();

        if (initial) return;
        var reg = document.getElementById('brReg');
        var opt = sel.options[sel.selectedIndex];
        var newReg = opt && opt.dataset ? (opt.dataset.reg || '') : '';
        if (reg.value === '' || reg.value === brAutoReg) {
            reg.value = newReg;
            brAutoReg = newReg || null;
        }
    }

    function brPeriod() {
        var v = document.getElementById('brIssueDate').value, out = document.getElementById('brPeriod');
        if (!v) { out.textContent = ''; return; }
        var d = new Date(v + 'T00:00:00');
        out.textContent = d.toLocaleString('en-GB', { month: 'long', year: 'numeric' }) + ' · Q' + (Math.floor(d.getMonth() / 3) + 1);
    }

    document.addEventListener('DOMContentLoaded', function () {
        brCount(); brPeriod(); brSurveyorChanged(true);
        document.getElementById('brIssueDate').addEventListener('change', brPeriod);
        @if ($formOpen)
            brShow();
        @endif
    });
</script>

@once
@push('scripts')
    {{-- The shared file number selector, outside .survey-proto so the prototype CSS cannot reach it. --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endpush
@endonce

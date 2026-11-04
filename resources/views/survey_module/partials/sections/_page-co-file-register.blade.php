@php
    use App\Models\Survey\SurveyCoFile;

    $editing = $coFile->exists;
    $capture = $coFile->source === SurveyCoFile::SOURCE_CAPTURED;
    $readonlyStyle = 'background:var(--gray-100);color:var(--gray-700);';

    // CO File No = Prefix CO + Serial No + Year (user, 2026-10-30), shown as three boxes.
    $shownNo = $editing || $capture ? (string) $coFile->co_file_no : (string) $nextNo;
    preg_match('/^CO-(\d+)-(\d{4})(\(T\))?$/', $shownNo, $noParts);
    $noSerial = old('co_serial', $noParts[1] ?? '');
    $noYear   = old('co_year', $noParts[2] ?? '');
    $noTemp   = (string) old('co_temporary', ! empty($noParts[3]) ? '1' : '0') === '1';
    // Old CO Register (user, 2026-11-03): a collapsible section of Capture CO File, open when the file has ledger details.
    $oldReg = $capture && (string) old('old_register', (request('register') === 'old'
        || collect(SurveyCoFile::OLD_REGISTER_FIELDS)->contains(fn ($k) => $coFile->{$k} !== null && $coFile->{$k} !== '')) ? '1' : '0') === '1';
@endphp

@include('survey_module.partials._flash')

{{-- Two modes (user, 2026-10-30): Commission a new CO file, or Capture an existing paper one. Not shown while editing:
     its only use there was the Old CO Register switch, now a collapsible section (user, 2026-11-03). --}}
@if (! $editing)
    <style>
        .survey-proto .co-modes { display:inline-flex; gap:4px; padding:4px; background:var(--gray-100,#f3f4f6); border-radius:10px; margin-bottom:14px; }
        .survey-proto .co-modes a { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; font-weight:600; font-size:14px; color:var(--gray-600,#4b5563); text-decoration:none; }
        .survey-proto .co-modes a.on { background:#fff; color:var(--primary); box-shadow:0 1px 3px rgba(0,0,0,.12); }
        .survey-proto .co-modes button { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border:0; border-radius:8px; background:none; font-weight:600; font-size:14px; color:var(--gray-600,#4b5563); cursor:pointer; }
        .survey-proto .co-modes button.on { background:#fff; color:var(--primary); box-shadow:0 1px 3px rgba(0,0,0,.12); }
    </style>
    {{-- Each form shows only its own mode (user, 2026-10-30): the two lists in the sidebar each open their own form. --}}
    <nav class="co-modes" aria-label="CO file mode">
        @if ($capture)
            {{-- The Old CO Register is a collapsible section inside this form (user, 2026-11-03), no longer a switch here. --}}
            <a href="{{ route('survey-module.compensation.co-files.create', ['mode' => SurveyCoFile::SOURCE_CAPTURED]) }}" class="on" aria-current="page">
                <i class="fas fa-keyboard"></i> Capture CO File
            </a>
        @else
            <a href="{{ route('survey-module.compensation.co-files.create') }}" class="on" aria-current="page">
                <i class="fas fa-plus"></i> Commission CO File
            </a>
        @endif
    </nav>
@endif

<form method="POST"
      action="{{ $editing
                  ? route('survey-module.compensation.co-files.update', $coFile)
                  : route('survey-module.compensation.co-files.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    @unless ($editing)
        <input type="hidden" name="source" value="{{ $coFile->source }}" />
    @endunless
    @if ($capture)<input type="hidden" name="old_register" id="coOldRegisterOn" value="{{ $oldReg ? '1' : '0' }}" />@endif

    <div class="form-container">
        <div class="form-body">
            <style>
                .survey-proto .co-no { display: grid; grid-template-columns: 72px 1fr 110px; gap: 6px; }
                .survey-proto .co-no input { text-align: center; font-weight: 600; }
                .survey-proto .co-no .lbl { font-size: 11px; color: var(--gray-500); text-align: center; margin-top: 2px; }
                .survey-proto .co-idx { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
                .survey-proto .co-idx.yes { background: #dcfce7; color: #166534; }
                .survey-proto .co-idx.no { background: #fef3c7; color: #92400e; }
                .survey-proto .co-top-row { display:grid; grid-template-columns:minmax(300px,1.2fr) minmax(200px,1fr) minmax(200px,1fr); gap:16px; }
                /* Related files (user, 2026-11-03: "correct the related file layout"): a full-width row under the top row. */
                .survey-proto .co-related-box { border:1px solid var(--gray-200); border-radius:8px; background:var(--gray-50,#f9fafb); padding:12px 14px; margin:4px 0 16px; }
                .survey-proto .co-related-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px 16px; }
                .survey-proto .co-related-grid .co-rel label { font-size:12px; }
                .survey-proto .co-rel-row { display:flex; gap:6px; align-items:stretch; }
                .survey-proto .co-rel-row .co-rel-no { flex:1; min-width:0; font-weight:700; background:var(--gray-100); cursor:not-allowed; }
                .survey-proto .co-rel-row .btn { white-space:nowrap; padding-left:10px; padding-right:10px; }
                @media (max-width: 1200px) { .survey-proto .co-related-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
                .survey-proto .co-rel-add { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:10px; }
                .survey-proto .co-rel-add select { width:auto; min-width:220px; padding:6px 10px; }
                .survey-proto .co-sit-toggle { display:flex; align-items:center; gap:8px; min-height:38px; }
                .survey-proto .co-sit-toggle input { width:auto; margin:0; }
                .survey-proto .co-sit-toggle span { margin:0; }
                .survey-proto .co-location-map { height:320px; border:1px solid var(--gray-300); border-radius:8px; overflow:hidden; background:var(--gray-100); }
                .survey-proto .co-location-map img { max-width:none !important; max-height:none !important; }
                @media (max-width: 900px) { .survey-proto .co-top-row { grid-template-columns:1fr 1fr; } .survey-proto .co-no-cell { grid-column:1 / -1; } }
                @media (max-width: 680px) { .survey-proto .co-top-row, .survey-proto .co-related-grid { grid-template-columns:1fr; } }
            </style>

            <div class="co-top-row">
                <div class="form-group co-no-cell">
                    <label>CO File No. @if ($capture)<span class="required">*</span>@endif</label>
                    <div class="co-no">
                        <div>
                            <input type="text" value="CO" readonly style="{{ $readonlyStyle }}" aria-label="Prefix" />
                            <div class="lbl">Prefix</div>
                        </div>
                        <div>
                            @if ($capture)
                                <input type="text" name="co_serial" value="{{ $noSerial }}" inputmode="numeric" maxlength="7"
                                       placeholder="e.g. 9" aria-label="Serial No" required />
                            @else
                                <input type="text" value="{{ $noSerial }}" readonly style="{{ $readonlyStyle }}" aria-label="Serial No" />
                            @endif
                            <div class="lbl">Serial No.</div>
                        </div>
                        <div>
                            @if ($capture)
                                <input type="number" name="co_year" value="{{ $noYear }}" min="{{ SurveyCoFile::FIRST_YEAR }}" max="{{ date('Y') }}"
                                       placeholder="e.g. 2018" aria-label="Year" required />
                            @else
                                <input type="text" value="{{ $noYear }}" readonly style="{{ $readonlyStyle }}" aria-label="Year" />
                            @endif
                            <div class="lbl">Year</div>
                        </div>
                    </div>
                    @if ($capture)
                        {{-- A temporary file is saved CO-serial-year(T), e.g. CO-33-2025(T) (user, 2026-11-02). --}}
                        <label style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-weight:500;cursor:pointer;">
                            <input type="checkbox" name="co_temporary" value="1" style="width:auto;margin:0;" @checked($noTemp) /> Temporary (T)
                        </label>
                    @elseif (! empty($noParts[3]))
                        <div class="helper-text" style="margin-top:4px;">Temporary file (T)</div>
                    @endif
                    @foreach (['co_file_no', 'co_serial', 'co_year'] as $k)
                        @error($k)<p class="helper-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
                    @endforeach
                    <p class="helper-text">
                        @if ($capture)
                            The paper file's serial and year; saved as <strong>CO-serial-year</strong>, e.g. CO-9-2018 (no leading zeros), or CO-33-2025(T) when Temporary is ticked.
                        @else
                            {{ $editing ? 'Generated when commissioned; cannot be changed.' : 'Next number for this year (' . $shownNo . '); confirmed when you save.' }}
                        @endif
                    </p>
                </div>

                <div class="form-group">
                    <label>Commission Date @unless ($capture)<span class="required">*</span>@endunless</label>
                    <input type="date" name="commission_date"
                           value="{{ old('commission_date', optional($coFile->commission_date)->format('Y-m-d')) }}"
                           @unless ($capture) required @endunless />
                </div>

                @php
                    $hasSitFile = (string) old('has_sit_file', ($coFile->related_file_no || $coFile->related_sit_file_no || $coFile->related_gkn_file_no || $coFile->related_misckn_file_no || $coFile->related_rdkn_file_no || $coFile->related_mlpp_file_no) ? '1' : '0') === '1';
                @endphp
                <div class="form-group">
                    <label for="coHasSitFile">Has Related File <span class="required">*</span></label>
                    <div class="co-sit-toggle">
                        <input type="checkbox" name="has_sit_file" id="coHasSitFile" value="1" @checked($hasSitFile) />
                        <span id="coHasSitFileAnswer">{{ $hasSitFile ? 'Yes' : 'No' }}</span>
                    </div>
                </div>
            </div>

            {{-- Related files, one field each for SIT, Misc KN, GKN, RDKN and MLPP (user, 2026-11-02, RDKN and MLPP 2026-11-03; CR 30102026
                 item 1): picked from the global file number selector, indexed or not. With Has Related File = Yes at least one is required.
                 Only SIT shows at first; Add more offers the other types in a dropdown (user, 2026-11-03), and a type that has a number shows. --}}
            <div id="coRelatedFields" @class(['co-related-box', 'hidden' => ! $hasSitFile])>
                <div id="coRelated" data-lookup="{{ route('survey-module.compensation.co-files.related-lookup') }}">
                    <p class="helper-text" style="margin:0 0 10px;"><strong>Related files</strong>: select at least one from the file number selector, indexed or not. An indexed file fills the plot, district and LGA below.</p>
                    <div class="co-related-grid">
                    @foreach ([
                        'related_sit_file_no'    => ['SIT', 'sit', 'SIT-'],
                        'related_misckn_file_no' => ['Misc KN', 'gkn', 'MISC KN '],
                        'related_gkn_file_no'    => ['GKN', 'gkn', 'GKN-'],
                        'related_rdkn_file_no'   => ['RDKN', 'gkn', 'RDKN-'],
                        'related_mlpp_file_no'   => ['MLPP', 'mls', ''],
                    ] as $relCol => [$relLabel, $relTab, $relPrefix])
                        @php
                            $relFirst = $relCol === 'related_sit_file_no';
                            $relShown = $relFirst || trim((string) old($relCol, $coFile->{$relCol})) !== '' || $errors->has($relCol);
                        @endphp
                        <div class="co-rel" data-col="{{ $relCol }}" data-tab="{{ $relTab }}" data-prefix="{{ $relPrefix }}" data-label="{{ $relLabel }}"
                             data-optional="{{ $relFirst ? '0' : '1' }}" @unless ($relShown) style="display:none;" @endunless>
                            <label>{{ $relLabel }} File No.</label>
                            <div class="co-rel-row">
                                <input type="text" name="{{ $relCol }}" class="co-rel-no" readonly
                                       value="{{ old($relCol, $coFile->{$relCol}) }}"
                                       placeholder="No {{ $relLabel }} file" />
                                <button type="button" class="btn btn-primary btn-sm co-rel-pick" title="Select {{ $relLabel }} file" aria-label="Select {{ $relLabel }} file">
                                    <i class="fas fa-folder-open"></i> Select
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm co-rel-clear" title="{{ $relFirst ? 'Clear' : 'Remove' }}" aria-label="{{ $relFirst ? 'Clear' : 'Remove' }} {{ $relLabel }} file">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            @error($relCol)<p class="helper-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
                            <p class="helper-text co-rel-status" style="margin:2px 0 0;"></p>
                        </div>
                    @endforeach
                    </div>
                    <div class="co-rel-add" id="coRelAdd">
                        <button type="button" class="btn btn-secondary btn-sm" id="coRelAddBtn"><i class="fas fa-plus"></i> Add more</button>
                        <select id="coRelAddKind" data-no-search style="display:none;" aria-label="Related file type to add">
                            <option value="">Select the file type…</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- File / Job Title and Status share a row (user, 2026-10-26) --}}
            <div class="form-grid cols-3">
                <div class="form-group span-2">
                    <label>File / Job Title <span class="required">*</span></label>
                    <input type="text" name="title" id="coTitle" value="{{ old('title', $coFile->title) }}" maxlength="255"
                           placeholder="e.g. Tundun Wada Government Acquisition" required />
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status">
                        @foreach (SurveyCoFile::statusesFor($coFile->source) as $s)
                            <option value="{{ $s }}" @selected(old('status', $coFile->status) === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Old CO Register (user, 2026-11-03): a collapsible section of Capture CO File, not a tab. The ledger's Job No. is the CO
                 File No. above, its Description the File / Job Title and its Remarks the Remarks below; its other columns
                 are here, all optional, copied as written. --}}
            @if ($capture)
                @php
                    $ov = fn ($k) => old($k, $coFile->{$k} instanceof \Carbon\Carbon ? $coFile->{$k}->format('Y-m-d') : $coFile->{$k});
                    $svName = trim((string) $coFile->surveyor_name);
                    $svPick = $coFile->surveyors_list_id ? (string) $coFile->surveyors_list_id
                        : ($svName !== '' ? (string) ($surveyors->first(fn ($s) => mb_strtolower($s->full_name) === mb_strtolower($svName))?->id ?? 'other') : '');
                    $svPick = (string) old('surveyor', $svPick);
                    $svOther = old('surveyor_other', $svPick === 'other' ? $svName : '');
                @endphp
                <style>
                    /* Spaced off the Location card and tinted amber like an old ledger (user, 2026-11-03). */
                    .survey-proto .co-old-wrap { margin:22px 0 24px; }
                    .survey-proto .co-old-toggle { display:flex; align-items:center; gap:10px; width:100%; padding:12px 14px; border:1px solid #fcd34d;
                        border-left:4px solid #d97706; border-radius:10px; background:#fffbeb; cursor:pointer; text-align:left; font:inherit; }
                    .survey-proto .co-old-toggle:hover { background:#fef3c7; }
                    .survey-proto .co-old-toggle .t { font-weight:700; font-size:13px; letter-spacing:.3px; text-transform:uppercase; color:#92400e; }
                    .survey-proto .co-old-toggle .h { font-size:12.5px; color:#a16207; }
                    .survey-proto .co-old-toggle .chev { margin-left:auto; color:#b45309; transition:transform .15s; }
                    .survey-proto .co-old-toggle[aria-expanded="true"] { border-bottom-left-radius:0; border-bottom-right-radius:0; }
                    .survey-proto .co-old-toggle[aria-expanded="true"] .chev { transform:rotate(180deg); }
                    .survey-proto #coOldRegister { border:1px solid #fcd34d; border-left:4px solid #d97706; border-top:0; border-radius:0 0 10px 10px;
                        padding:4px 14px 14px; background:#fffdf5; }
                </style>
                <div class="co-old-wrap">
                <button type="button" class="co-old-toggle" id="coOldToggle" aria-controls="coOldRegister" aria-expanded="{{ $oldReg ? 'true' : 'false' }}">
                    <i class="fas fa-book" style="color:#b45309;"></i>
                    <span><span class="t">Old CO Register</span><br><span class="h">For a file from the old register books. Open to fill its ledger details.</span></span>
                    <i class="fas fa-chevron-down chev"></i>
                </button>
                <div id="coOldRegister" @unless ($oldReg) hidden @endunless>
                <div class="form-grid cols-3" style="margin-top:14px;">
                    <p class="helper-text full" style="grid-column:1 / -1;margin:-4px 0 0;font-size:12.5px;color:var(--gray-500);">
                        For a file from the old register books: the Job No. goes in CO File No. (CO/94/75 is serial 94, year 1975), the Description in File / Job Title.
                        Fill what the book gives; everything here is optional.
                    </p>
                    <div class="form-group">
                        <label>File Ref.</label>
                        <input type="text" name="old_file_ref" value="{{ $ov('old_file_ref') }}" maxlength="150" placeholder="e.g. SUR/G23/Vol III/564" />
                        <p class="helper-text">As written in the book.</p>
                    </div>
                    <div class="form-group">
                        <label>Approx. Area (ha)</label>
                        <input type="number" name="approx_area_ha" step="any" min="0" value="{{ $ov('approx_area_ha') !== null ? (float) $ov('approx_area_ha') : '' }}" placeholder="e.g. 0.52" />
                    </div>
                    <div class="form-group">
                        <label>No. of Farms</label>
                        <input type="number" name="farms_count" step="1" min="0" value="{{ $ov('farms_count') }}" placeholder="e.g. 2" />
                    </div>
                    <div class="form-group">
                        <label>Date Received</label>
                        <input type="date" name="date_received" value="{{ $ov('date_received') }}" />
                    </div>
                    <div class="form-group span-2">
                        <label>Surveyor</label>
                        <select name="surveyor" id="coOldSurveyor" class="sv-searchable">
                            <option value="">— Select surveyor —</option>
                            @foreach ($surveyors as $s)
                                <option value="{{ $s->id }}" @selected($svPick === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>
                            @endforeach
                            <option value="other" @selected($svPick === 'other')>Other (specify)</option>
                        </select>
                        <input type="text" name="surveyor_other" id="coOldSurveyorOther" maxlength="255" placeholder="Specify the surveyor's name *"
                               value="{{ $svOther }}" style="margin-top:6px;{{ $svPick === 'other' ? '' : 'display:none;' }}" @if ($svPick === 'other') required @endif />
                    </div>
                    <div class="form-group">
                        <label>Field Work From</label>
                        <input type="date" name="fieldwork_from" value="{{ $ov('fieldwork_from') }}" />
                    </div>
                    <div class="form-group">
                        <label>Field Work To</label>
                        <input type="date" name="fieldwork_to" value="{{ $ov('fieldwork_to') }}" />
                    </div>
                    <div class="form-group">
                        <label>Plan Completed</label>
                        <input type="date" name="plan_completed_on" value="{{ $ov('plan_completed_on') }}" />
                    </div>
                    <div class="form-group">
                        <label>Total Amount (₦)</label>
                        <input type="number" name="total_amount" step="0.01" min="0" value="{{ $ov('total_amount') !== null ? (float) $ov('total_amount') : '' }}" placeholder="e.g. 41.60" />
                    </div>
                    <div class="form-group">
                        <label>Financial File Reference</label>
                        <input type="text" name="financial_file_ref" value="{{ $ov('financial_file_ref') }}" maxlength="100" placeholder="e.g. SUR/311/59/75-76" />
                    </div>
                    <div class="form-group">
                        <label>Date Despatched</label>
                        <input type="date" name="despatched_on" value="{{ $ov('despatched_on') }}" />
                    </div>
                    <div class="form-group">
                        <label>Date Paid</label>
                        <input type="date" name="paid_on" value="{{ $ov('paid_on') }}" />
                    </div>
                </div>
                </div>
                </div>
                <script>
                    // Opening the Old CO Register section turns it on; while it is closed its fields are disabled, so they
                    // are not sent and a file's ledger details are left as they are.
                    function coOldRegisterSwitch(on) {
                        var box = document.getElementById('coOldRegister');
                        box.hidden = !on;
                        document.getElementById('coOldRegisterOn').value = on ? '1' : '0';
                        box.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = !on; });
                        if (on) { var o = document.getElementById('coOldSurveyorOther'); if (o) o.disabled = document.getElementById('coOldSurveyor').value !== 'other'; }
                        document.getElementById('coOldToggle').setAttribute('aria-expanded', on ? 'true' : 'false');
                        coOldRegisterRequired(on);
                    }
                    // Old CO Register on: Has Related File, File / Job Title and District are not required (user, 2026-11-03).
                    function coOldRegisterRequired(on) {
                        var title = document.getElementById('coTitle'), sit = document.getElementById('coHasSitFile'), dist = document.querySelector('.ab-district');
                        if (title) title.required = !on;
                        [title, sit, dist].forEach(function (el) {
                            var star = el && el.closest('.form-group') && el.closest('.form-group').querySelector('label .required');
                            if (star) star.style.display = on ? 'none' : '';
                        });
                    }
                    document.addEventListener('DOMContentLoaded', function () {
                        var toggle = document.getElementById('coOldToggle');
                        if (toggle) toggle.addEventListener('click', function () { coOldRegisterSwitch(toggle.getAttribute('aria-expanded') !== 'true'); });
                        var box = document.getElementById('coOldRegister');
                        if (box && box.hidden) box.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = true; });
                        if (box) coOldRegisterRequired(!box.hidden);
                    });
                    // Surveyor: Other reveals the required name box.
                    document.addEventListener('DOMContentLoaded', function () {
                        var sel = document.getElementById('coOldSurveyor'), other = document.getElementById('coOldSurveyorOther');
                        if (!sel || !other) return;
                        var sync = function () { var on = sel.value === 'other'; other.style.display = on ? '' : 'none'; other.required = on; other.disabled = !on; if (!on) other.value = ''; };
                        if (window.jQuery) window.jQuery(sel).on('change', sync); else sel.addEventListener('change', sync);
                    });
                </script>
            @endif

            {{-- Location: Plot No., District & LGA, State; the full location reads District, LGA, State (user, 2026-10-30). --}}
            @include('survey_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $coFile,
                'legend' => 'Location',
                'fields' => ['plot', 'district', 'lga', 'state'],
            ])

            <div class="form-grid">
                <div class="form-group full">
                    <label>Location Map</label>
                    <div id="co-location-map" class="co-location-map" role="img" aria-label="Map preview based on selected district and LGA"></div>
                    <p class="helper-text" id="co-location-map-status">Select a district, LGA, or both to preview the area on the map.</p>
                </div>
            </div>

            {{-- Remarks sit after Property Location (auto) (user, 2026-10-28) --}}
            <div class="form-grid">
                <div class="form-group full">
                    <label>Remarks</label>
                    <textarea name="remarks" maxlength="4000" placeholder="Optional notes… (the old register's Remarks column goes here too)">{{ old('remarks', $coFile->remarks) }}</textarea>
                </div>
            </div>

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.co-files') }}">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-check"></i>
                        {{ $editing ? 'Save Changes' : ($capture ? 'Capture CO File' : 'Commission CO File') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@once
@push('scripts')
    {{-- The shared selector, outside the .survey-proto wrapper so the prototype CSS cannot reach it. --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endpush
@endonce
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush
@push('scripts')
@once
    @include('partials.maps_scripts')
@endonce
<script>
(function () {
    'use strict';
    var el = document.getElementById('co-location-map');
    var form = el && el.closest('form');
    var status = document.getElementById('co-location-map-status');
    if (!el || !form || !window.L || !window.google || !google.maps || !google.maps.Geocoder) return;

    var map = L.map(el, { scrollWheelZoom: false }).setView([12.0022, 8.5920], 9);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics'
    }).addTo(map);
    var marker = null;
    var geocoder = new google.maps.Geocoder();
    var timer = null;
    var requestNo = 0;

    function selectedValues(selector) {
        var select = form.querySelector(selector);
        if (!select) return { values: [], hasOther: false };
        var hasOther = false;
        var values = Array.prototype.map.call(select.selectedOptions, function (option) {
            var value = (option.value || '').trim();
            if (value.toLowerCase() === 'other') { hasOther = true; return ''; }
            return value;
        }).filter(Boolean);
        return { values: values, hasOther: hasOther };
    }

    function locationParts() {
        var districtSelection = selectedValues('[name="prop_district[]"]');
        var lgaSelection = selectedValues('[name="prop_lga[]"]');
        var districts = districtSelection.values;
        var lgas = lgaSelection.values;
        if (districtSelection.hasOther) {
            var otherDistrict = form.querySelector('[name="prop_district_other"]');
            if (otherDistrict && otherDistrict.value.trim()) districts.push(otherDistrict.value.trim());
        }
        return { districts: districts, lgas: lgas };
    }

    function geocodeNext(queries, index, zoom, token) {
        if (token !== requestNo) return;
        if (index >= queries.length) {
            status.textContent = 'No map location was found for the selected district or LGA.';
            return;
        }
        geocoder.geocode({ address: queries[index] }, function (results, resultStatus) {
            if (token !== requestNo) return;
            if (resultStatus === 'OK' && results && results[0]) {
                var point = results[0].geometry.location;
                var latlng = [point.lat(), point.lng()];
                if (!marker) marker = L.marker(latlng).addTo(map);
                else marker.setLatLng(latlng);
                map.setView(latlng, zoom);
                status.textContent = 'Map preview based on ' + queries[index].replace(/, Kano State, Nigeria$/, '') + '.';
                return;
            }
            geocodeNext(queries, index + 1, zoom, token);
        });
    }

    function updateMap() {
        var area = locationParts();
        var district = area.districts.join(', ');
        var lga = area.lgas.join(', ');
        var queries = [];
        if (district && lga) queries.push(district + ', ' + lga + ', Kano State, Nigeria');
        if (district) queries.push(district + ', Kano State, Nigeria');
        if (lga) queries.push(lga + ', Kano State, Nigeria');
        queries = queries.filter(function (query, index) { return queries.indexOf(query) === index; });
        var token = ++requestNo;
        if (!queries.length) {
            status.textContent = 'Select a district, LGA, or both to preview the area on the map.';
            if (marker) { map.removeLayer(marker); marker = null; }
            map.setView([12.0022, 8.5920], 9);
            return;
        }
        status.textContent = 'Finding the selected area on the map…';
        geocodeNext(queries, 0, district && lga ? 12 : (district ? 13 : 10), token);
    }

    var WATCH = '[name="prop_district[]"], [name="prop_lga[]"], [name="prop_district_other"]';
    function schedule() { window.clearTimeout(timer); timer = window.setTimeout(updateMap, 400); }
    form.addEventListener('change', function (event) {
        if (event.target.matches(WATCH)) schedule();
    });
    // The District and LGA boxes are select2, which reports a pick as a jQuery event only, so the
    // listener above never heard it and the map did not pin the LGA (user, 2026-11-03).
    if (window.jQuery) {
        jQuery(form).on('change', WATCH, function (e) { if (!e.originalEvent) schedule(); });
    }
    window.setTimeout(function () { map.invalidateSize(); updateMap(); }, 250);
})();
</script>
<script>
(function () {
    'use strict';
    var root = document.getElementById('coRelated');
    if (!root) return;
    var form = root.closest('form');
    var hasSitFile = document.getElementById('coHasSitFile');
    var relatedFields = document.getElementById('coRelatedFields');
    var hasSitFileAnswer = document.getElementById('coHasSitFileAnswer');
    var blocks = Array.prototype.slice.call(root.querySelectorAll('.co-rel'));
    // The box's heading says how to pick; an empty field needs no line of its own (layout, 2026-11-03).
    var HINT = '';

    function $j() { return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null; }
    function field(name) { return form.querySelector('[name="' + name + '"]'); }
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function isBlank(el) {
        if (!el) return false;
        if (el.tagName === 'SELECT') return !Array.prototype.some.call(el.options, function (o) { return o.selected && o.value; });
        return !el.value.trim();
    }
    function setSelect(el, value) {
        if (!el || !value) return false;
        var match = Array.prototype.find.call(el.options, function (o) { return o.value.toLowerCase() === String(value).toLowerCase(); });
        if (!match) { match = new Option(value, value); el.add(match); }
        Array.prototype.forEach.call(el.options, function (o) { o.selected = (o === match); });
        var $ = $j();
        if ($ && $(el).data('select2')) $(el).trigger('change'); else el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }
    function setText(el, value) {
        if (!el || !value) return false;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    function badge(d) {
        return d.indexed ? '<span class="co-idx yes">Indexed</span>' : '<span class="co-idx no">Not indexed</span>';
    }

    // One picker per kind. Each remembers the fields its pick filled, and undoes only those.
    blocks.forEach(function (block) {
        var number = block.querySelector('.co-rel-no'), status = block.querySelector('.co-rel-status');
        var label = block.dataset.label, prefix = block.dataset.prefix, filled = [];
        function say(html, isError) { status.innerHTML = html; status.style.color = isError ? '#b91c1c' : ''; }
        function clearFilled() {
            filled.forEach(function (f) {
                if (f.tagName === 'SELECT') {
                    Array.prototype.forEach.call(f.options, function (o) { o.selected = false; });
                    var $ = $j();
                    if ($ && $(f).data('select2')) $(f).trigger('change'); else f.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    f.value = ''; f.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            filled = [];
        }
        function lookup(fileNumber, fill) {
            say(esc(fileNumber) + ': checking the records…');
            return fetch(root.dataset.lookup + '?file_number=' + encodeURIComponent(fileNumber), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (fill && d.known) {
                        // Fill gaps only: what is already on the form wins.
                        var t = document.getElementById('coTitle');
                        if (isBlank(t) && setText(t, d.title)) filled.push(t);
                        var p = field('prop_plot');
                        if (isBlank(p) && setText(p, d.plot)) filled.push(p);
                        [['prop_district[]', d.district], ['prop_lga[]', d.lga]].forEach(function (x) {
                            var el = field(x[0]);
                            if (isBlank(el) && setSelect(el, x[1])) filled.push(el);
                        });
                    }
                    var what = d.indexed
                        ? (d.title ? ' · ' + esc(d.title) : '')
                        : (d.known ? ' · on the file number register, not yet indexed' : ' · not in the records yet');
                    say(badge(d) + ' ' + what.replace(/^ · /, '') + (fill && filled.length ? ' — details filled below; check them.' : ''));
                })
                .catch(function () { say('Its index status could not be checked.', true); });
        }
        block.querySelector('.co-rel-pick').addEventListener('click', function () {
            if (!window.GlobalFileNoModal) { say('The file number selector did not load. Refresh the page.', true); return; }
            window.GlobalFileNoModal.open({
                initialTab: block.dataset.tab,
                allowedTabs: [block.dataset.tab],
                callback: function (data) {
                    var no = data && data.fileNumber ? String(data.fileNumber).trim() : '';
                    if (!no) return;
                    // Each field takes its own kind: a GKN number does not go in the Misc KN field.
                    if (no.toUpperCase().indexOf(prefix.toUpperCase()) !== 0) {
                        say(esc(no) + ' is not a ' + esc(label) + ' file. Pick a ' + esc(label) + ' file number here.', true);
                        return;
                    }
                    clearFilled();
                    number.value = no;
                    lookup(no, true);
                }
            });
        });
        block.querySelector('.co-rel-clear').addEventListener('click', function () {
            clearFilled();
            number.value = '';
            say(HINT);
            // Misc KN, GKN, RDKN and MLPP are added with Add more, so × takes the field away again; SIT stays.
            if (block.dataset.optional === '1') { block.style.display = 'none'; refreshAdd(); }
        });
        block.reset = function () { clearFilled(); number.value = ''; say(HINT); };
        if (number.value.trim()) lookup(number.value.trim(), false); else say(HINT);
    });

    function updateSitFileVisibility(clearWhenOff) {
        var enabled = hasSitFile.checked;
        relatedFields.classList.toggle('hidden', !enabled);
        hasSitFileAnswer.textContent = enabled ? 'Yes' : 'No';
        if (!enabled && clearWhenOff) blocks.forEach(function (b) { b.reset(); });
    }
    hasSitFile.addEventListener('change', function () { updateSitFileVisibility(true); });
    updateSitFileVisibility(false);

    // Add more (user, 2026-11-03): a dropdown of the file types not yet on the form; picking one shows its field and opens the selector.
    var addBtn = document.getElementById('coRelAddBtn'), addKind = document.getElementById('coRelAddKind'), addBox = document.getElementById('coRelAdd');
    function hiddenBlocks() { return blocks.filter(function (b) { return b.style.display === 'none'; }); }
    function refreshAdd() {
        var rest = hiddenBlocks();
        addKind.innerHTML = '<option value="">Select the file type…</option>';
        rest.forEach(function (b) { var o = document.createElement('option'); o.value = b.dataset.col; o.textContent = b.dataset.label + ' File No.'; addKind.appendChild(o); });
        addBox.style.display = rest.length ? '' : 'none';
        addKind.style.display = 'none';
        addBtn.style.display = '';
    }
    addBtn.addEventListener('click', function () { addBtn.style.display = 'none'; addKind.style.display = ''; addKind.focus(); });
    addKind.addEventListener('change', function () {
        var b = blocks.find(function (x) { return x.dataset.col === addKind.value; });
        if (!b) return;
        b.style.display = '';
        refreshAdd();
        b.querySelector('.co-rel-pick').click();
    });
    refreshAdd();

    // Has Related File = Yes needs at least one of the four.
    form.addEventListener('submit', function (e) {
        if (!hasSitFile.checked) return;
        var any = blocks.some(function (b) { return b.querySelector('.co-rel-no').value.trim() !== ''; });
        if (!any) {
            e.preventDefault();
            var st = blocks[0].querySelector('.co-rel-status');
            st.innerHTML = 'Has Related File is Yes: select at least one related file (SIT, Misc KN, GKN, RDKN or MLPP), or turn it off.';
            st.style.color = '#b91c1c';
            blocks[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();
</script>
@endpush

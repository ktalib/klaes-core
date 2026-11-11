{{--
    The standard Surveyor's Report fields, a portal copy of the staff Surveyor's Report (Private) form
    (survey_module.partials.sections._page-gkn-report-form). Same names, same checks (ReportController::validated).
    Needs $report, $districts, $lgas, $transport, $fieldRecords, $lock (true = read only), $fileLocked.
--}}
@php
    $val = fn (string $f) => old($f, $f === 'grantee_name' ? $report->file_title : (in_array($f, ['boundaries_pointed_on', 'commenced_on', 'completed_on', 'report_date'], true) && $report->{$f} ? \Illuminate\Support\Carbon::parse($report->{$f})->format('Y-m-d') : $report->{$f}));
    $yn = function (string $f) use ($report) {
        $v = old($f, $report->{$f} === null ? null : ((bool) $report->{$f} ? '1' : '0'));
        return $v === null ? null : (string) $v;
    };
    $district = old('district', $report->district);
    $districtIsListed = $district === null || $district === '' || in_array($district, $districts, true) || $district === 'Other';
    $districtChoice = $districtIsListed ? $district : 'Other';
    $districtOther = old('district_other', $districtIsListed ? '' : $district);
    $checked = (array) old('field_jacket_checklist', $report->field_jacket_checklist ?? []);
    $dis = $lock ? 'disabled' : '';
@endphp

<fieldset class="ks-rep-section" {{ $dis }}>
    <legend><i data-lucide="map-pinned" class="w-4 h-4"></i> Property</legend>
    <div class="ks-rep-grid">
        {{-- File Number left off the portal form for now (user, 2026-11-09). A report from an I to S still keeps the I to S's file. --}}
        <div>
            <label class="ks-label" for="repGrantee">Name of Grantee</label>
            <input class="ks-input" id="repGrantee" name="grantee_name" maxlength="500" value="{{ $val('grantee_name') }}">
        </div>
        <div>
            <label class="ks-label" for="repPlot">Plot No.</label>
            <input class="ks-input" id="repPlot" name="plot_no" maxlength="100" value="{{ $val('plot_no') }}">
        </div>
        <div>
            <label class="ks-label" for="repDistrict">District <span class="ks-req">*</span></label>
            <select class="ks-input ks-searchable" id="repDistrict" name="district" required data-placeholder="Search district…">
                <option value="">Select district…</option>
                @foreach ($districts as $d)<option value="{{ $d }}" @selected($districtChoice === $d)>{{ $d }}</option>@endforeach
                <option value="Other" @selected($districtChoice === 'Other')>Other (specify)</option>
            </select>
            @error('district')<p class="ks-error">{{ $message }}</p>@enderror
            <div id="repDistrictOtherBox" style="margin-top:8px;{{ $districtChoice === 'Other' ? '' : 'display:none;' }}">
                <label class="ks-label" for="repDistrictOther">Specify District <span class="ks-req">*</span></label>
                <input class="ks-input" id="repDistrictOther" name="district_other" maxlength="255" value="{{ $districtOther }}" @disabled($districtChoice !== 'Other')>
                @error('district_other')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="ks-label" for="repLga">LGA <span class="ks-req">*</span></label>
            <select class="ks-input ks-searchable" id="repLga" name="lga" required data-placeholder="Search LGA…">
                <option value="">Select LGA…</option>
                @foreach ($lgas as $l)<option value="{{ $l }}" @selected(strcasecmp((string) $val('lga'), $l) === 0)>{{ $l }}</option>@endforeach
            </select>
            @error('lga')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="ks-label">Location</span>
            <div class="ks-input" style="background:#F1F3F5;min-height:44px;">{{ $report->location ?: 'District and LGA, filled when saved' }}</div>
        </div>
    </div>
</fieldset>

<fieldset class="ks-rep-section" {{ $dis }}>
    <legend><i data-lucide="compass" class="w-4 h-4"></i> The Survey</legend>
    <div class="ks-rep-grid">
        <div><label class="ks-label" for="repGrid">Origin of the local grid <span class="ks-req">*</span></label>
            <input class="ks-input" id="repGrid" name="grid_origin" maxlength="255" value="{{ $val('grid_origin') }}"></div>
        <div><label class="ks-label" for="repCps">The three control points (in situ) <span class="ks-req">*</span></label>
            <input class="ks-input" id="repCps" name="control_points" maxlength="500" value="{{ $val('control_points') }}" placeholder="e.g. CP 101, CP 102, CP 103"></div>
        <div><label class="ks-label" for="repPointedBy">Boundaries pointed out by</label>
            <input class="ks-input" id="repPointedBy" name="boundaries_pointed_by" maxlength="255" value="{{ $val('boundaries_pointed_by') }}"></div>
        <div><label class="ks-label" for="repPointedOn">… on date</label>
            <input class="ks-input" id="repPointedOn" type="date" name="boundaries_pointed_on" value="{{ $val('boundaries_pointed_on') }}"></div>
        <div><label class="ks-label" for="repStart">Survey commenced on <span class="ks-req">*</span></label>
            <input class="ks-input" id="repStart" type="date" name="commenced_on" value="{{ $val('commenced_on') }}"></div>
        <div><label class="ks-label" for="repEnd">… and completed on <span class="ks-req">*</span></label>
            <input class="ks-input" id="repEnd" type="date" name="completed_on" value="{{ $val('completed_on') }}">
            @error('completed_on')<p class="ks-error">{{ $message }}</p>@enderror</div>
        <div><label class="ks-label" for="repKm">Distance travelled (km)</label>
            <input class="ks-input" id="repKm" type="number" step="0.01" min="0" name="distance_km" value="{{ $val('distance_km') !== null && $val('distance_km') !== '' ? (float) $val('distance_km') : '' }}"></div>
        <div><label class="ks-label" for="repComputed">Computations were done on</label>
            <input class="ks-input" id="repComputed" name="computed_on" maxlength="255" value="{{ $val('computed_on') }}" placeholder="Date, or where / how"></div>
        <div class="ks-rep-full"><label class="ks-label" for="repDelay">Delay (if any) in completion of survey is due to</label>
            <textarea class="ks-input" id="repDelay" name="delay_reason" rows="2" maxlength="2000">{{ $val('delay_reason') }}</textarea></div>
    </div>
</fieldset>

@php
    $ask = function (string $name, string $question, string $yes, string $no, bool $req = false) use ($yn) {
        $cur = $yn($name);
        return '<div><span class="ks-label">' . e($question) . ($req ? ' <span class="ks-req">*</span>' : '') . '</span><div class="ks-rep-yn">'
            . '<label><input type="radio" name="' . $name . '" value="1"' . ($cur === '1' ? ' checked' : '') . '> ' . e($yes) . '</label>'
            . '<label><input type="radio" name="' . $name . '" value="0"' . ($cur === '0' ? ' checked' : '') . '> ' . e($no) . '</label></div></div>';
    };
@endphp
<fieldset class="ks-rep-section" {{ $dis }}>
    <legend><i data-lucide="map" class="w-4 h-4"></i> Layout and Boundaries</legend>
    <div class="ks-rep-grid">
        {!! $ask('on_layout_plan', 'The plot is / is not on an approved layout plan', 'Is', 'Is not', true) !!}
        {{-- A TP No. from Public Layout Plans, searchable (user, 2026-11-09: "the same as tp no"). --}}
        @php
            $repTp = trim((string) $val('layout_plan_no'));
            $repTpList = collect($tpNumbers ?? []);
            if ($repTp !== '' && ! $repTpList->contains(fn ($t) => $t['no'] === $repTp)) $repTpList->prepend(['no' => $repTp, 'name' => 'Previously saved']);
        @endphp
        <div><label class="ks-label" for="repLayoutNo">Layout plan No.</label>
            <select class="ks-input ks-searchable" id="repLayoutNo" name="layout_plan_no" data-placeholder="Search TP No.…">
                <option value=""></option>
                @foreach ($repTpList as $tp)
                    <option value="{{ $tp['no'] }}" @selected($repTp === $tp['no'])>{{ $tp['no'] }}{{ $tp['name'] ? ' — ' . \Illuminate\Support\Str::limit($tp['name'], 60) : '' }}</option>
                @endforeach
            </select>
            <p class="ks-help">Required when the plot is on an approved layout plan.</p>
            @error('layout_plan_no')<p class="ks-error">{{ $message }}</p>@enderror</div>
        {!! $ask('corners_demarcated', 'The corners were / were not demarcated on the ground before', 'Were', 'Were not', true) !!}
        <div><label class="ks-label" for="repHardBy">Hard by</label>
            <input class="ks-input" id="repHardBy" name="hard_by" maxlength="500" value="{{ $val('hard_by') }}"></div>
    </div>
    <p class="ks-help" style="margin:10px 0 0;"><em>The plot is surveyed strictly as on the approved site plan in file.</em></p>
</fieldset>

<fieldset class="ks-rep-section" {{ $dis }}>
    <legend><i data-lucide="home" class="w-4 h-4"></i> Site Conditions</legend>
    <div class="ks-rep-grid">
        {!! $ask('encroachment', 'Encroachment noticed?', 'Yes', 'No (none noticed)') !!}
        <div><label class="ks-label" for="repEnc">Encroachment details</label>
            <input class="ks-input" id="repEnc" name="encroachment_details" maxlength="2000" value="{{ $val('encroachment_details') }}" placeholder="Required when Yes">
            @error('encroachment_details')<p class="ks-error">{{ $message }}</p>@enderror</div>
        {!! $ask('developed', 'The plot is / is not developed', 'Is developed', 'Is not') !!}
        <div><label class="ks-label" for="repDev">Developed with</label>
            <input class="ks-input" id="repDev" name="developed_with" maxlength="500" value="{{ $val('developed_with') }}" placeholder="Required when developed">
            @error('developed_with')<p class="ks-error">{{ $message }}</p>@enderror</div>
        <div><label class="ks-label" for="repNotEmplaced">Survey beacons not emplaced on ground</label>
            <input class="ks-input" id="repNotEmplaced" name="beacons_not_emplaced" maxlength="500" value="{{ $val('beacons_not_emplaced') }}" placeholder="Beacon numbers, if any"></div>
        <div><label class="ks-label" for="repNotEmplacedWhy">… due to</label>
            <input class="ks-input" id="repNotEmplacedWhy" name="beacons_reason" maxlength="1000" value="{{ $val('beacons_reason') }}">
            @error('beacons_reason')<p class="ks-error">{{ $message }}</p>@enderror</div>
        <div><label class="ks-label" for="repKn">Previous KN</label>
            <input class="ks-input" id="repKn" name="previous_kn" maxlength="100" value="{{ $val('previous_kn') }}"></div>
        {!! $ask('previous_kn_coincides', 'Its boundaries coincide with the present survey', 'Coincide', 'Do not coincide') !!}
    </div>
    <p class="ks-help" style="margin:10px 0 0;"><i data-lucide="route" class="w-4 h-4" style="display:inline;vertical-align:-3px;"></i> {{ \App\Models\Survey\SurveySurveyorReport::ROAD_RESERVATIONS }}</p>
</fieldset>

<fieldset class="ks-rep-section" {{ $dis }}>
    <legend><i data-lucide="clipboard-check" class="w-4 h-4"></i> Completion and Field Records</legend>
    <div class="ks-rep-grid">
        <div><label class="ks-label" for="repTransport">Transport was provided by <span class="ks-req">*</span></label>
            <select class="ks-input" id="repTransport" name="transport_by">
                <option value="">Select…</option>
                @foreach ($transport as $k => $label)<option value="{{ $k }}" @selected($val('transport_by') === $k)>{{ $label }}</option>@endforeach
            </select></div>
        <div class="ks-rep-full"><label class="ks-label" for="repRemarks">Any other remarks</label>
            <textarea class="ks-input" id="repRemarks" name="remarks" rows="2" maxlength="4000">{{ $val('remarks') }}</textarea></div>
        <div class="ks-rep-full">
            <span class="ks-label">In the field jacket, the following field records are numbered and filed in this order</span>
            <div class="ks-rep-checks">
                @foreach ($fieldRecords as $k => $label)
                    <label><input type="checkbox" name="field_jacket_checklist[]" value="{{ $k }}" @checked(in_array($k, $checked, true))> {{ $label }}</label>
                @endforeach
            </div>
        </div>
        <div><label class="ks-label" for="repSurveyor">Surveyor's name <span class="ks-req">*</span></label>
            <input class="ks-input" id="repSurveyor" name="surveyor_name" maxlength="255" value="{{ $val('surveyor_name') }}"></div>
        <div><label class="ks-label" for="repDate">Date</label>
            <input class="ks-input" id="repDate" type="date" name="report_date" value="{{ $val('report_date') }}"></div>
    </div>
</fieldset>

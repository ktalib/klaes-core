@php
    use App\Models\Survey\SurveySurveyorReport as SR;
    use App\Http\Controllers\Survey\LookupController;

    $private = $privateReport ?? false;
    $editing = $report->exists;
    $locked  = $private ? $report->isIssued() : $report->isSubmitted();
    $ro = 'background:var(--gray-100);color:var(--gray-700);';
    $val = function ($f) use ($private, $report) {
        $stored = $private && $f === 'grantee_name' ? $report->file_title : $report->{$f};
        return old($f, $stored instanceof \Carbon\Carbon ? $stored->format('Y-m-d') : $stored);
    };
    $currentDistrict = trim((string) $val('district'));
    $currentLga = trim((string) $val('lga'));
    $districtChoice = old('district', LookupController::canonicalOption('districts', $currentDistrict) ?: ($currentDistrict === '' ? '' : 'Other'));
    $districtOther = old('district_other', $districtChoice === 'Other' && $currentDistrict !== 'Other' ? $currentDistrict : '');
    $lgaChoice = old('lga', LookupController::canonicalOption('lgas', $currentLga) ?: '');
    // A yes/no field's current answer as '1', '0' or ''.
    $yn = function ($f) use ($report) {
        $o = old($f);
        if ($o !== null) return (string) $o;
        return $report->{$f} === null ? '' : ($report->{$f} ? '1' : '0');
    };
    $checked = old('field_jacket_checklist', $report->field_jacket_checklist ?? []);
@endphp

@once
@push('styles')
<style>
    .sr-checklist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 9px;
        margin-top: 8px;
    }
    .sr-checklist-item {
        display: grid;
        grid-template-columns: 20px 30px minmax(0, 1fr);
        align-items: center;
        gap: 10px;
        min-height: 54px;
        padding: 9px 12px;
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: 9px;
        background: #fff;
        color: var(--gray-800, #1f2937);
        cursor: pointer;
        font-weight: 500;
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }
    .sr-checklist-item:hover {
        border-color: var(--primary, #2563eb);
        background: #f8fbff;
    }
    .sr-checklist-item:focus-within {
        outline: 3px solid rgba(37, 99, 235, .2);
        outline-offset: 1px;
    }
    .sr-checklist-item.is-checked {
        border-color: var(--primary, #2563eb);
        background: #eff6ff;
        box-shadow: inset 0 0 0 1px var(--primary, #2563eb);
    }
    .sr-checklist-item input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--primary, #2563eb);
    }
    .sr-checklist-number {
        display: inline-flex;
        width: 28px;
        height: 28px;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--gray-100, #f3f4f6);
        color: var(--gray-600, #4b5563);
        font-size: 12px;
        font-weight: 700;
    }
    .sr-checklist-item.is-checked .sr-checklist-number {
        background: #dbeafe;
        color: #1d4ed8;
    }
    fieldset:disabled .sr-checklist-item {
        cursor: not-allowed;
        opacity: .8;
    }
</style>
@endpush
@endonce

@include('survey_module.partials._flash')

@if ($locked)
    <div class="comp-type-note" style="margin-bottom:16px;">
        <i class="fas fa-lock"></i>
        {{ $private ? 'Issued ' . optional($report->issued_at)->format('d M Y H:i') : 'Submitted ' . optional($report->submitted_at)->format('d M Y H:i') }};
        the report can no longer be edited.
        <a href="{{ $private ? route('survey-module.workflow.change-reports.print', $report) : route('survey-module.gkn.reports.print', $report) }}" target="_blank">Print it</a>.
    </div>
@endif

<form method="POST" action="{{ $private
    ? ($editing ? route('survey-module.workflow.change-reports.update', $report) : route('survey-module.workflow.change-reports.store'))
    : ($editing ? route('survey-module.gkn.reports.update', $report) : route('survey-module.gkn.reports.store')) }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    @if ($private)
        @if ($fromLine && ! $editing)<input type="hidden" name="survey_register_file_id" value="{{ $line->id }}" />@endif
        @if (! empty($beaconLine))<input type="hidden" name="survey_beacon_register_id" value="{{ $beaconLine }}" />@endif
    @else
        <input type="hidden" name="its_key" id="srItsKey" value="{{ $key }}" />
    @endif

    {{-- New report: "Is there an I to S?" first, as on the portal (user, 2026-11-09). Yes shows the I to S list;
         on the Private report No goes on without one; a GKN report always answers an I to S. --}}
    @unless ($editing)
        {{-- The I to S is the default (user, 2026-11-10: "no need for this question again"): Yes unless changed, so the
             question no longer opens by itself; Change still offers it (Private: No starts from the file). --}}
        @php $srItsAnswer = old('its_choice', 'yes'); @endphp
        <input type="hidden" name="its_choice" id="srItsAnswer" value="{{ $srItsAnswer }}">
        <dialog id="srItsDialog" aria-labelledby="srItsDialogTitle"
                style="margin:auto;border:0;border-radius:14px;padding:0;width:min(460px,calc(100vw - 32px));max-height:calc(100vh - 32px);overflow:auto;box-shadow:0 24px 60px rgba(15,23,42,.3);">{{-- margin:auto centres a modal dialog; the page's CSS reset sets it to 0 (top-left, 2026-11-09) --}}
            <div style="padding:20px 22px;" data-its-step="ask">
                <h3 id="srItsDialogTitle" style="margin:0 0 4px;font-size:17px;font-weight:700;">Is there an Instruction to Survey?</h3>
                <p class="helper-text" style="margin:0 0 16px;">{{ $private ? 'Yes: pick the I to S and its details fill in. No: pick the file instead.' : 'A GKN Surveyor\'s Report answers an issued I to S.' }}</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <button type="button" class="btn btn-primary" data-its-answer="yes" style="justify-content:center;"><i class="fas fa-check"></i> Yes</button>
                    <button type="button" class="btn btn-outline" data-its-answer="no" style="justify-content:center;"><i class="fas fa-times"></i> No</button>
                </div>
                <div style="text-align:right;margin-top:12px;"><a href="{{ $private ? route('survey-module.workflow.change-reports.index') : route('survey-module.gkn.reports.index') }}" class="helper-text">Back to the list</a></div>
            </div>
            @unless ($private)
                <div style="padding:20px 22px;" data-its-step="none" hidden>
                    <h3 style="margin:0 0 4px;font-size:17px;font-weight:700;">An I to S is needed</h3>
                    <p class="helper-text" style="margin:0 0 16px;">A GKN Surveyor's Report is the answer to an issued Instruction to Survey. Create the I to S first, then come back to write its report.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;">
                        <button type="button" class="btn btn-outline" data-its-back>Back</button>
                        <a href="{{ route('survey-module.gkn.its.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Create an I to S</a>
                    </div>
                </div>
            @endunless
        </dialog>
        <div class="form-container" id="srItsStarted" style="margin-bottom:12px;" @if ($srItsAnswer === '') hidden @endif>
            <div class="form-body" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:10px;padding-bottom:10px;">
                <span>Instruction to Survey: <strong id="srItsAnswerLabel">{{ $srItsAnswer === 'no' ? 'No, starting from the file' : 'Yes' }}</strong></span>
                <button type="button" class="btn btn-outline btn-sm" id="srItsChange"><i class="fas fa-repeat"></i> Change</button>
            </div>
        </div>
    @endunless

    @unless ($private || $editing)
        <div class="form-container" id="srItsBlock" style="margin-bottom:16px;" @if ($srItsAnswer !== 'yes') hidden @endif>
            <div class="form-body">
                <div class="form-group full" style="margin:0;">
                    <label for="srItsChoice">Instruction to Survey <span class="required">*</span></label>
                    <select id="srItsChoice" required>
                    <option value="">— Choose an issued I to S without a report —</option>
                    @foreach ($choices as $k => $choice)
                        <option value="{{ $k }}"
                            data-file-number="{{ $choice['file_number'] ?? '' }}"
                            data-grantee="{{ $choice['grantee_name'] ?? '' }}"
                            data-plot="{{ $choice['plot_no'] ?? '' }}"
                            data-district="{{ $choice['district'] ?? '' }}"
                            data-lga="{{ $choice['lga'] ?? '' }}"
                            data-location="{{ $choice['location'] ?? '' }}"
                            data-surveyor="{{ $choice['surveyor_name'] ?? '' }}"
                            @selected($key === $k)>{{ $choice['label'] }}</option>
                    @endforeach
                    </select>
                    @if (! $choices)
                        <p class="helper-text">Every issued I to S already has a report. <a href="{{ route('survey-module.gkn.its.create') }}">Create an I to S</a> first.</p>
                    @else
                        <p class="helper-text">The full report form is ready below. Choosing an I to S fills its file and property details.</p>
                    @endif
                </div>
            </div>
        </div>
    @endunless

    {{-- Private: the Instruction to Survey the report answers (user, 2026-11-09), full width as on the GKN report.
         Required on a new report; a draft saved before it was asked for may still choose one; once chosen it is shown. --}}
    @if ($private && $report->its_number)
        <div class="form-container" style="margin-bottom:16px;">
            <div class="form-body">
                <div class="form-group full" style="margin:0;">
                    <label>Instruction to Survey</label>
                    <input type="text" value="{{ $report->its_number }}" readonly style="{{ $ro }}" />
                </div>
            </div>
        </div>
    @elseif ($private && ! $locked)
        @php $srPrivFills = ! ($editing || $fromLine || ! empty($fromBeacons)); @endphp
        <div class="form-container" id="srItsBlock" style="margin-bottom:16px;" @if (! $editing && ($srItsAnswer ?? '') !== 'yes') hidden @endif>
            <div class="form-body">
                <div class="form-group full" style="margin:0;">
                    <label for="srItsChoice">Instruction to Survey @unless ($editing)<span class="required">*</span>@endunless</label>
                    <select id="srItsChoice" name="its_key" @unless ($editing) required @if (($srItsAnswer ?? '') !== 'yes') disabled @endif @endunless data-private-fill="{{ $srPrivFills ? '1' : '0' }}">
                    <option value="">— Choose an issued I to S without a report —</option>
                    @foreach ($choices as $k => $choice)
                        <option value="{{ $k }}"
                            data-file-number="{{ $choice['file_number'] ?? '' }}"
                            data-grantee="{{ $choice['grantee_name'] ?? '' }}"
                            data-plot="{{ $choice['plot_no'] ?? '' }}"
                            data-district="{{ $choice['district'] ?? '' }}"
                            data-lga="{{ $choice['lga'] ?? '' }}"
                            data-location="{{ $choice['location'] ?? '' }}"
                            data-surveyor="{{ $choice['surveyor_name'] ?? '' }}"
                            @selected($key === $k)>{{ $choice['label'] }}</option>
                    @endforeach
                    </select>
                    @error('its_key')<p class="helper-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
                    @if (! $choices)
                        <p class="helper-text">No issued I to S is waiting for a report. <a href="{{ route('survey-module.gkn.its.create') }}">Create an I to S</a> first.</p>
                    @elseif ($srPrivFills)
                        <p class="helper-text">Choosing an I to S fills its file and property details below.</p>
                    @else
                        <p class="helper-text">The I to S must be for {{ $report->file_number ?: 'this file' }}.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <fieldset @disabled($locked) style="border:0;padding:0;margin:0;">
    <div class="form-container">
        <div class="form-body">
            <h3 style="margin:0 0 12px;font-size:15px;font-weight:700;text-align:center;letter-spacing:.05em;">SURVEYOR'S REPORT</h3>

            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-folder-open"></i><span>File and Property</span></div>
                @if ($private)
                    @if ($editing || $fromLine || ! empty($fromBeacons))
                        <div class="form-group">
                            <label>File Number <span class="required">*</span></label>
                            <input type="text" name="file_number" value="{{ old('file_number', $report->file_number) }}" readonly style="{{ $ro }}" />
                        </div>
                    @else
                        @include('survey_module.partials._file_picker', [
                            'id' => 'srPrivate', 'name' => 'file_number', 'value' => $report->file_number, 'label' => 'File Number', 'required' => true,
                            'fill' => [
                                'title' => 'srGrantee', 'plot' => 'srPlot',
                                'district' => ['id' => 'srDistrict', 'reportLocation' => true, 'allowOther' => true, 'other' => 'srDistrictOther'],
                                'lga' => ['id' => 'srLga', 'reportLocation' => true],
                            ],
                        ])
                    @endif
                @else
                    <div class="form-group">
                        <label>File Number</label>
                        <input type="text" id="srFileNumber" value="{{ $report->file_number ?: 'Select an I to S above' }}" readonly style="{{ $ro }}" />
                        <p class="helper-text" id="srItsHint">{{ $report->its_number ? 'From ' . $report->its_number . '.' : 'Select an issued Instruction to Survey above.' }}</p>
                    </div>
                @endif
                <div class="form-group">
                    <label>Name of Grantee</label>
                    <input type="text" @if ($private) name="grantee_name" @endif id="srGrantee" maxlength="500" value="{{ $val('grantee_name') }}" @unless ($private) readonly style="{{ $ro }}" @endunless />
                </div>
                <div class="form-group"><label>Plot No.</label><input type="text" @if ($private) name="plot_no" @endif id="srPlot" maxlength="100" value="{{ $val('plot_no') }}" @unless ($private) readonly style="{{ $ro }}" @endunless /></div>
                <div class="form-group">
                    <label for="srDistrict">District <span class="required">*</span></label>
                    <select id="srDistrict" name="district" class="sr-location-select" data-url="{{ route('survey-module.lookup.districts') }}" data-placeholder="Search district…" required>
                        <option value=""></option>
                        @if ($districtChoice !== '')
                            @if ($districtChoice !== 'Other')<option value="{{ $districtChoice }}" selected>{{ $districtChoice }}</option>@endif
                        @endif
                        <option value="Other" @selected($districtChoice === 'Other')>Other (specify)</option>
                    </select>
                </div>
                <div class="form-group" id="srDistrictOtherWrap" @style(['display:none' => $districtChoice !== 'Other'])>
                    <label for="srDistrictOther">Specify District <span class="required">*</span></label>
                    <input type="text" id="srDistrictOther" name="district_other" maxlength="255" value="{{ $districtOther }}"
                           placeholder="Enter the district name" @if ($districtChoice === 'Other') required @endif />
                </div>
                <div class="form-group">
                    <label for="srLga">LGA <span class="required">*</span></label>
                    <select id="srLga" name="lga" class="sr-location-select" data-url="{{ route('survey-module.lookup.lgas') }}" data-placeholder="Search LGA…" required>
                        <option value=""></option>
                        @if ($lgaChoice !== '')<option value="{{ $lgaChoice }}" selected>{{ $lgaChoice }}</option>@endif
                    </select>
                </div>
                <div class="form-group">
                    <label for="srLocation">Location</label>
                    <input type="text" id="srLocation" value="{{ $val('location') }}" readonly style="{{ $ro }}" />
                    <p class="helper-text">Automatically formed from District, LGA and Kano State.</p>
                </div>
            </div>

            <p class="helper-text" style="margin:6px 0 14px;"><em>The survey is done in accordance with Technical Instruction.</em></p>

            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-ruler-combined"></i><span>Survey Details</span></div>
                <div class="form-group"><label>Origin of the local grid <span class="required">*</span></label>
                    <input type="text" name="grid_origin" maxlength="255" value="{{ $val('grid_origin') }}" /></div>
                <div class="form-group"><label>The three control points (in situ) <span class="required">*</span></label>
                    <input type="text" name="control_points" maxlength="500" value="{{ $val('control_points') }}" placeholder="e.g. CP 101, CP 102, CP 103" /></div>
                <div class="form-group"><label>Boundaries pointed out by</label>
                    <input type="text" name="boundaries_pointed_by" maxlength="255" value="{{ $val('boundaries_pointed_by') }}" /></div>
                <div class="form-group"><label>… on date</label>
                    <input type="date" name="boundaries_pointed_on" value="{{ $val('boundaries_pointed_on') }}" /></div>
                <div class="form-group"><label>Survey commenced on <span class="required">*</span></label>
                    <input type="date" name="commenced_on" value="{{ $val('commenced_on') }}" /></div>
                <div class="form-group"><label>… and completed on <span class="required">*</span></label>
                    <input type="date" name="completed_on" value="{{ $val('completed_on') }}" /></div>
                <div class="form-group"><label>Distance travelled (km)</label>
                    <input type="number" step="0.01" min="0" name="distance_km" value="{{ $val('distance_km') !== null ? (float) $val('distance_km') : '' }}" /></div>
                <div class="form-group"><label>Computations were done on</label>
                    <input type="text" name="computed_on" maxlength="255" value="{{ $val('computed_on') }}" placeholder="Date, or where / how" /></div>
                <div class="form-group full"><label>Delay (if any) in completion of survey is due to</label>
                    <textarea name="delay_reason" maxlength="2000">{{ $val('delay_reason') }}</textarea></div>
            </div>

            @php
                $ynField = function (string $name, string $question, string $yes, string $no, bool $req = false) use ($yn) {
                    $cur = $yn($name);
                    $h  = '<div class="form-group"><label>' . e($question) . ($req ? ' <span class="required">*</span>' : '') . '</label><div style="display:flex;gap:16px;flex-wrap:wrap;">';
                    $h .= '<label style="display:inline-flex;gap:6px;align-items:center;font-weight:400;"><input type="radio" name="' . $name . '" value="1"' . ($cur === '1' ? ' checked' : '') . '> ' . e($yes) . '</label>';
                    $h .= '<label style="display:inline-flex;gap:6px;align-items:center;font-weight:400;"><input type="radio" name="' . $name . '" value="0"' . ($cur === '0' ? ' checked' : '') . '> ' . e($no) . '</label>';
                    return $h . '</div></div>';
                };
            @endphp
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-map-marked-alt"></i><span>Layout and Boundaries</span></div>
                {!! $ynField('on_layout_plan', 'The plot is / is not on an approved layout plan', 'Is', 'Is not', true) !!}
                <div class="form-group"><label>Layout plan No.</label>
                    <input type="text" name="layout_plan_no" maxlength="100" value="{{ $val('layout_plan_no') }}" placeholder="Required when it is" /></div>
                {!! $ynField('corners_demarcated', 'The corners were / were not demarcated on the ground before', 'Were', 'Were not', true) !!}
                <div class="form-group"><label>Hard by</label>
                    <input type="text" name="hard_by" maxlength="500" value="{{ $val('hard_by') }}" /></div>
            </div>

            <p class="helper-text" style="margin:6px 0 14px;"><em>The plot is surveyed strictly as on the approved site plan in file.</em></p>

            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-home"></i><span>Site Conditions</span></div>
                {!! $ynField('encroachment', 'Encroachment noticed?', 'Yes', 'No (none noticed)') !!}
                <div class="form-group"><label>Encroachment details</label>
                    <input type="text" name="encroachment_details" maxlength="2000" value="{{ $val('encroachment_details') }}" placeholder="Required when Yes" /></div>
                {!! $ynField('developed', 'The plot is / is not developed', 'Is developed', 'Is not') !!}
                <div class="form-group"><label>Developed with</label>
                    <input type="text" name="developed_with" maxlength="500" value="{{ $val('developed_with') }}" placeholder="Required when developed" /></div>
                <div class="form-group"><label>Survey beacons not emplaced on ground</label>
                    <input type="text" name="beacons_not_emplaced" maxlength="500" value="{{ $val('beacons_not_emplaced') }}" placeholder="Beacon numbers, if any" /></div>
                <div class="form-group"><label>… due to</label>
                    <input type="text" name="beacons_reason" maxlength="1000" value="{{ $val('beacons_reason') }}" /></div>
                <div class="form-group"><label>Previous KN</label>
                    <input type="text" name="previous_kn" maxlength="100" value="{{ $val('previous_kn') }}" /></div>
                {!! $ynField('previous_kn_coincides', 'Its boundaries coincide with the present survey', 'Coincide', 'Do not coincide') !!}
            </div>

            <div class="comp-type-note" style="margin:6px 0 14px;"><i class="fas fa-road"></i> {{ SR::ROAD_RESERVATIONS }}</div>

            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-clipboard-check"></i><span>Completion and Field Records</span></div>
                <div class="form-group"><label>Transport was provided by <span class="required">*</span></label>
                    <select name="transport_by">
                        <option value="">— Select —</option>
                        @foreach (SR::TRANSPORT as $k => $label)
                            <option value="{{ $k }}" @selected($val('transport_by') === $k)>{{ $label }}</option>
                        @endforeach
                    </select></div>
                <div class="form-group full"><label>Any other remarks</label>
                    <textarea name="remarks" maxlength="4000">{{ $val('remarks') }}</textarea></div>

                <div class="form-group full">
                    <label>In the field jacket, the following field records are numbered and filed in this order</label>
                    <p class="helper-text" style="margin:2px 0 8px;">Select the records included in the field jacket. The numbers match the printed report.</p>
                    <div class="sr-checklist-grid" role="group" aria-label="Field jacket records">
                        @foreach (SR::FIELD_RECORDS as $k => $label)
                            @php $recordChecked = in_array($k, (array) $checked, true); @endphp
                            <label class="sr-checklist-item {{ $recordChecked ? 'is-checked' : '' }}">
                                <input type="checkbox" name="field_jacket_checklist[]" value="{{ $k }}" @checked($recordChecked)>
                                <span class="sr-checklist-number" aria-hidden="true">{{ $loop->iteration }}</span>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-group"><label>Surveyor's name <span class="required">*</span></label>
                    <input type="text" name="surveyor_name" maxlength="255" value="{{ $val('surveyor_name') }}" /></div>
                <div class="form-group"><label>Date</label>
                    <input type="date" name="report_date" value="{{ $val('report_date') }}" /></div>
            </div>

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ $private ? route('survey-module.workflow.change-reports.index') : route('survey-module.gkn.reports.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <div class="right">
                    @unless ($locked)
                        <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> {{ $private ? 'Save' : 'Save Draft' }}</button>
                    @endunless
                </div>
            </div>
        </div>
    </div>
    </fieldset>
</form>

@if ($editing && ! $locked)
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
        <form method="POST" action="{{ $private ? route('survey-module.workflow.change-reports.destroy', $report) : route('survey-module.gkn.reports.destroy', $report) }}" onsubmit="return confirm('Delete report {{ $report->report_ref }}?');">
            @csrf @method('DELETE')
            <button class="btn btn-outline btn-sm" type="submit"><i class="fas fa-trash"></i> Delete Report</button>
        </form>
        <form method="POST" action="{{ $private ? route('survey-module.workflow.change-reports.issue', $report) : route('survey-module.gkn.reports.submit', $report) }}" onsubmit="return confirm('{{ $private ? 'Issue' : 'Submit' }} {{ $report->report_ref }}? It is locked afterwards. Save any changes first.');">
            @csrf
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-paper-plane"></i> {{ $private ? 'Issue & Print' : 'Submit Report' }}</button>
        </form>
    </div>
@endif

@if ($private && $editing && isset($records))
    @php
        $BNC = \App\Services\Survey\BeaconNumberCheck::class;
        $rk = $records['key'];
        $bs = $records['summary'];
        $card = 'display:block;background:#fff;border:1px solid var(--gray-200,#e5e7eb);border-radius:12px;padding:14px 16px;text-decoration:none;color:inherit;';
    @endphp
    <div style="margin-top:18px;">
        <div class="sv-group" style="margin-bottom:10px;"><i class="fas fa-layer-group"></i><span>Survey Records</span></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            <a href="{{ route('survey-module.workflow.private.coordinates.show', $rk) }}" style="{{ $card }}">
                <div style="font-size:12px;color:var(--gray-500);"><i class="fas fa-map-pin"></i> List of Coordinates</div>
                <div style="font-size:20px;font-weight:700;">{{ $records['coords']->count() }} <span style="font-size:13px;font-weight:400;">beacon{{ $records['coords']->count() === 1 ? '' : 's' }}</span></div>
                <div style="font-size:12px;color:var(--primary);">{{ $locked ? 'View' : ($records['coords']->isEmpty() ? 'Add coordinates' : 'Open') }} →</div>
            </a>
            <a href="{{ route('survey-module.workflow.private.beacons.show', $rk) }}" style="{{ $card }}">
                <div style="font-size:12px;color:var(--gray-500);"><i class="fas fa-map-marker-alt"></i> Beacon Nos</div>
                <div style="font-size:20px;font-weight:700;">{{ $bs[$BNC::REGISTERED] }} / {{ $bs['total'] }} <span style="font-size:13px;font-weight:400;">in register</span></div>
                <div style="font-size:12px;{{ $bs[$BNC::UNREGISTERED] || $bs[$BNC::OTHER_SURVEYOR] ? 'color:#b91c1c;' : 'color:var(--gray-500);' }}">
                    {{ $bs[$BNC::UNREGISTERED] }} not in register · {{ $bs[$BNC::OTHER_SURVEYOR] }} another surveyor's
                </div>
            </a>
            <a href="{{ route('survey-module.workflow.private.observations.show', $rk) }}" style="{{ $card }}">
                <div style="font-size:12px;color:var(--gray-500);"><i class="fas fa-calculator"></i> Computation Field Observations</div>
                <div style="font-size:20px;font-weight:700;">{{ $records['readings'] }} <span style="font-size:13px;font-weight:400;">reading{{ $records['readings'] === 1 ? '' : 's' }}</span></div>
                <div style="font-size:12px;color:var(--primary);">{{ $locked ? 'View' : 'Open' }} →</div>
            </a>
            <a href="{{ route('survey-module.workflow.private.observations.show', $rk) }}" style="{{ $card }}">
                <div style="font-size:12px;color:var(--gray-500);"><i class="fas fa-drafting-compass"></i> Sketch Plan</div>
                <div style="font-size:15px;font-weight:600;margin-top:4px;">{{ $records['sketch'] ? $records['sketch']->original_name : 'Not uploaded' }}</div>
                <div style="font-size:12px;color:var(--primary);">{{ $records['sketch'] ? 'View / replace' : ($locked ? '' : 'Upload on Field Observations') }} →</div>
            </a>
        </div>
        @unless ($locked)
            <p style="margin-top:8px;font-size:12px;color:var(--gray-500);">These lock with the report when it is issued.</p>
        @endunless
    </div>
@endif

<script>
    (function () {
        var $ = window.jQuery;
        var district = document.getElementById('srDistrict');
        var lga = document.getElementById('srLga');
        var districtOther = document.getElementById('srDistrictOther');
        var otherWrap = document.getElementById('srDistrictOtherWrap');
        var location = document.getElementById('srLocation');

        function normalized(value) { return String(value || '').trim().toLocaleLowerCase(); }
        function dispatchChange(el) {
            if ($ && $(el).data('select2')) $(el).trigger('change');
            else el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function syncOther() {
            var useOther = district && district.value === 'Other';
            if (otherWrap) otherWrap.style.display = useOther ? '' : 'none';
            if (districtOther) {
                districtOther.required = !!useOther;
                if (!useOther) districtOther.value = '';
            }
            updateLocation();
        }
        function updateLocation() {
            if (!location) return;
            var districtValue = district && district.value === 'Other'
                ? (districtOther ? districtOther.value.trim() : '')
                : (district ? district.value.trim() : '');
            var lgaValue = lga ? lga.value.trim() : '';
            location.value = [districtValue, lgaValue, 'Kano'].filter(Boolean).join(', ');
        }
        function setChoice(el, value, allowOther, other) {
            if (!el) return;
            var wanted = String(value || '').trim();
            var request = el._surveyReportRequest = (el._surveyReportRequest || 0) + 1;
            function apply(valueToUse, isOther) {
                if (request !== el._surveyReportRequest) return;
                var existing = Array.prototype.find.call(el.options, function (option) {
                    return option.value !== '' && option.value !== 'Other' && normalized(option.value) === normalized(valueToUse);
                });
                if (isOther && Array.prototype.some.call(el.options, function (option) { return option.value === 'Other'; })) {
                    el.value = 'Other';
                    if (other) other.value = valueToUse === 'Other' ? '' : valueToUse;
                } else if (existing) {
                    el.value = existing.value;
                    if (other) other.value = '';
                } else {
                    el.value = '';
                    if (other) other.value = '';
                }
                dispatchChange(el);
            }
            var match = Array.prototype.find.call(el.options, function (option) {
                return option.value !== '' && option.value !== 'Other' && normalized(option.value) === normalized(wanted);
            });
            if (!wanted) return apply('', false);
            if (wanted === 'Other') return apply(wanted, true);
            if (match) return apply(match.value, false);

            fetch(el.dataset.url + '?q=' + encodeURIComponent(wanted), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var exact = data.results.find(function (item) { return item.id !== 'Other' && normalized(item.text) === normalized(wanted); });
                    if (exact && !Array.prototype.some.call(el.options, function (option) { return normalized(option.value) === normalized(exact.id); })) {
                        el.add(new Option(exact.text, exact.id));
                    }
                    apply(exact ? exact.id : wanted, !exact && allowOther);
                })
                .catch(function () { apply(wanted, allowOther); });
        }
        window.surveyReportSetLocationChoice = setChoice;

        if ($ && $.fn && $.fn.select2) {
            document.querySelectorAll('.sr-location-select').forEach(function (el) {
                var $el = $(el);
                $el.select2({
                    width: '100%',
                    placeholder: el.dataset.placeholder,
                    allowClear: true,
                    ajax: {
                        url: el.dataset.url,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                        processResults: function (data) {
                            if (el === lga) data.results = data.results.filter(function (item) { return item.id !== 'Other'; });
                            return data;
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                });
                $el.on('change.surveyReportLocation', function () {
                    if (el === district) syncOther();
                    else updateLocation();
                });
            });
        }

        if (districtOther) districtOther.addEventListener('input', updateLocation);
        syncOther();

        var instructionSelect = document.getElementById('srItsChoice');
        var key = document.getElementById('srItsKey');
        if (instructionSelect && key) {
            instructionSelect.addEventListener('change', function () {
                var option = instructionSelect.options[instructionSelect.selectedIndex];
                key.value = option.value || '';
                document.getElementById('srFileNumber').value = option.value ? (option.dataset.fileNumber || '') : 'Select an I to S above';
                document.getElementById('srGrantee').value = option.value ? (option.dataset.grantee || '') : '';
                document.getElementById('srPlot').value = option.value ? (option.dataset.plot || '') : '';
                setChoice(district, option.value ? option.dataset.district : '', true, districtOther);
                setChoice(lga, option.value ? option.dataset.lga : '', false, null);
                var surveyor = document.querySelector('[name="surveyor_name"]');
                if (surveyor) surveyor.value = option.value ? (option.dataset.surveyor || '') : '';
                var hint = document.getElementById('srItsHint');
                if (hint) hint.textContent = option.value ? 'From ' + option.text.split(' · ')[0] + '.' : 'Select an issued Instruction to Survey above.';
                updateLocation();
            });
        }

        // Private: the chosen I to S fills the file and property details, unless the file is already fixed
        // (from a Register File line, a Beacon Register line, or a saved report).
        if (instructionSelect && !key && instructionSelect.dataset.privateFill === '1') {
            instructionSelect.addEventListener('change', function () {
                var option = instructionSelect.options[instructionSelect.selectedIndex];
                if (!option.value) return;
                var set = function (id, value) { var el = document.getElementById(id); if (el && value) el.value = value; };
                set('srPrivateFileNumber', option.dataset.fileNumber);
                var status = document.getElementById('srPrivatePickStatus');
                if (status && option.dataset.fileNumber) { status.textContent = option.dataset.fileNumber + ' from ' + option.text.split(' · ')[0] + '.'; status.style.color = ''; }
                set('srGrantee', option.dataset.grantee);
                set('srPlot', option.dataset.plot);
                if (option.dataset.district) setChoice(district, option.dataset.district, true, districtOther);
                if (option.dataset.lga) setChoice(lga, option.dataset.lga, false, null);
                var surveyor = document.querySelector('[name="surveyor_name"]');
                if (surveyor && option.dataset.surveyor) surveyor.value = option.dataset.surveyor;
                updateLocation();
            });
        }

        // "Is there an I to S?" (user, 2026-11-09, as on the portal): asked first on a new report.
        var itsDialog = document.getElementById('srItsDialog');
        if (itsDialog) {
            var answer = document.getElementById('srItsAnswer'), block = document.getElementById('srItsBlock'),
                started = document.getElementById('srItsStarted'), answerLabel = document.getElementById('srItsAnswerLabel'),
                isPrivate = {{ $private ? 'true' : 'false' }}, listUrl = @json($private ? route('survey-module.workflow.change-reports.index') : route('survey-module.gkn.reports.index'));
            var step = function (name) { itsDialog.querySelectorAll('[data-its-step]').forEach(function (s) { s.hidden = s.dataset.itsStep !== name; }); };
            var open = function () { step('ask'); if (typeof itsDialog.showModal === 'function') itsDialog.showModal(); else itsDialog.setAttribute('open', ''); };
            var apply = function (value) {
                answer.value = value;
                var yes = value === 'yes';
                if (block) block.hidden = !yes;
                if (instructionSelect) {
                    // Private, No: the list is off, so it is neither required nor sent.
                    if (isPrivate) { instructionSelect.disabled = !yes; if (!yes) instructionSelect.value = ''; }
                    if (yes) setTimeout(function () { instructionSelect.focus(); }, 50);
                }
                answerLabel.textContent = yes ? 'Yes' : 'No, starting from the file';
                started.hidden = false;
                if (itsDialog.open) itsDialog.close();
            };
            itsDialog.querySelectorAll('[data-its-answer]').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (b.dataset.itsAnswer === 'no' && !isPrivate) { step('none'); return; }   // GKN: an I to S is needed
                    apply(b.dataset.itsAnswer);
                });
            });
            var back = itsDialog.querySelector('[data-its-back]');
            if (back) back.addEventListener('click', function () { step('ask'); });
            document.getElementById('srItsChange').addEventListener('click', open);
            // Esc with nothing answered goes back to the list: the report needs the answer.
            itsDialog.addEventListener('cancel', function (e) { if (!answer.value) { e.preventDefault(); window.location = listUrl; } });
            if (!answer.value) open();
        }
    })();
</script>

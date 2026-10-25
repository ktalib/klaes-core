@php
    use App\Models\Survey\SurveySurveyorReport as SR;

    $editing = $report->exists;
    $locked  = $report->isSubmitted();
    $ro = 'background:var(--gray-100);color:var(--gray-700);';
    $val = fn ($f) => old($f, $report->{$f} instanceof \Carbon\Carbon ? $report->{$f}->format('Y-m-d') : $report->{$f});
    // A yes/no field's current answer as '1', '0' or ''.
    $yn = function ($f) use ($report) {
        $o = old($f);
        if ($o !== null) return (string) $o;
        return $report->{$f} === null ? '' : ($report->{$f} ? '1' : '0');
    };
    $checked = old('field_jacket_checklist', $report->field_jacket_checklist ?? []);
@endphp

@include('survey_module.partials._flash')

@if ($locked)
    <div class="comp-type-note" style="margin-bottom:16px;">
        <i class="fas fa-lock"></i>
        Submitted {{ optional($report->submitted_at)->format('d M Y H:i') }}; the report can no longer be edited.
        <a href="{{ route('survey-module.gkn.reports.print', $report) }}" target="_blank">Print it</a>.
    </div>
@endif

{{-- New report: choose the I to S first; the page reloads with its details. --}}
@unless ($editing)
    <form method="GET" action="{{ route('survey-module.gkn.reports.create') }}" class="form-container" style="margin-bottom:16px;">
        <div class="form-body">
            <div class="form-group full" style="margin:0;">
                <label>Instruction to Survey <span class="required">*</span></label>
                <select name="its" onchange="this.form.submit()">
                    <option value="">— Choose an issued I to S without a report —</option>
                    @foreach ($choices as $k => $label)
                        <option value="{{ $k }}" @selected($key === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                @if (! $choices)
                    <p class="helper-text">Every issued I to S already has a report. <a href="{{ route('survey-module.gkn.its.create') }}">Create an I to S</a> first.</p>
                @endif
            </div>
        </div>
    </form>
@endunless

@if ($editing || $key)
<form method="POST" action="{{ $editing ? route('survey-module.gkn.reports.update', $report) : route('survey-module.gkn.reports.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    <input type="hidden" name="its_key" value="{{ $key }}" />

    <fieldset @disabled($locked) style="border:0;padding:0;margin:0;">
    <div class="form-container">
        <div class="form-body">
            <h3 style="margin:0 0 12px;font-size:15px;font-weight:700;text-align:center;letter-spacing:.05em;">SURVEYOR'S REPORT</h3>

            <div class="form-grid">
                <div class="form-group">
                    <label>File Number</label>
                    <input type="text" value="{{ $report->file_number }}" readonly style="{{ $ro }}" />
                    <p class="helper-text">From {{ $report->its_number }}.</p>
                </div>
                <div class="form-group">
                    <label>Name of Grantee</label>
                    <input type="text" name="grantee_name" maxlength="500" value="{{ $val('grantee_name') }}" />
                </div>
                {{-- Plot No., District, LGA, Location: from the I to S. --}}
                <div class="form-group"><label>Plot No.</label><input type="text" value="{{ $report->plot_no ?: '—' }}" readonly style="{{ $ro }}" /></div>
                <div class="form-group"><label>District</label><input type="text" value="{{ $report->district ?: '—' }}" readonly style="{{ $ro }}" /></div>
                <div class="form-group"><label>LGA</label><input type="text" value="{{ $report->lga ?: '—' }}" readonly style="{{ $ro }}" /></div>
                <div class="form-group"><label>Location</label><input type="text" value="{{ $report->location ?: '—' }}" readonly style="{{ $ro }}" /></div>
            </div>

            <p class="helper-text" style="margin:6px 0 14px;"><em>The survey is done in accordance with Technical Instruction.</em></p>

            <div class="form-grid">
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
                {!! $ynField('on_layout_plan', 'The plot is / is not on an approved layout plan', 'Is', 'Is not', true) !!}
                <div class="form-group"><label>Layout plan No.</label>
                    <input type="text" name="layout_plan_no" maxlength="100" value="{{ $val('layout_plan_no') }}" placeholder="Required when it is" /></div>
                {!! $ynField('corners_demarcated', 'The corners were / were not demarcated on the ground before', 'Were', 'Were not', true) !!}
                <div class="form-group"><label>Hard by</label>
                    <input type="text" name="hard_by" maxlength="500" value="{{ $val('hard_by') }}" /></div>
            </div>

            <p class="helper-text" style="margin:6px 0 14px;"><em>The plot is surveyed strictly as on the approved site plan in file.</em></p>

            <div class="form-grid">
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
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:6px;">
                        @foreach (SR::FIELD_RECORDS as $k => $label)
                            <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;">
                                <input type="checkbox" name="field_jacket_checklist[]" value="{{ $k }}" @checked(in_array($k, (array) $checked, true))>
                                {{ $loop->iteration }}. {{ $label }}
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
                    <a class="btn btn-secondary" href="{{ route('survey-module.gkn.reports.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <div class="right">
                    @unless ($locked)
                        <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> Save Draft</button>
                    @endunless
                </div>
            </div>
        </div>
    </div>
    </fieldset>
</form>

@if ($editing && ! $locked)
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
        <form method="POST" action="{{ route('survey-module.gkn.reports.destroy', $report) }}" onsubmit="return confirm('Delete draft {{ $report->report_ref }}?');">
            @csrf @method('DELETE')
            <button class="btn btn-outline btn-sm" type="submit"><i class="fas fa-trash"></i> Delete Draft</button>
        </form>
        <form method="POST" action="{{ route('survey-module.gkn.reports.submit', $report) }}" onsubmit="return confirm('Submit {{ $report->report_ref }}? It is locked afterwards. Save any changes first.');">
            @csrf
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-paper-plane"></i> Submit Report</button>
        </form>
    </div>
@endif
@endif

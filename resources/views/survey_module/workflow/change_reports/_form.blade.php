@php
    $editing = $report->exists;
    $locked = $report->isIssued();
    $fromLine = (bool) ($line ?? null);
    $ro = 'background:var(--gray-100);color:var(--gray-700);';
@endphp

@include('survey_module.partials._flash')

@if ($locked)
    <div class="comp-type-note" style="margin-bottom:16px;">
        <i class="fas fa-lock"></i> Issued {{ optional($report->issued_at)->format('d M Y H:i') }}; it can no longer be edited.
        <a href="{{ route('survey-module.workflow.change-reports.print', $report) }}" target="_blank">Print it</a>.
    </div>
@endif

<form method="POST" action="{{ $editing ? route('survey-module.workflow.change-reports.update', $report) : route('survey-module.workflow.change-reports.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    @if ($fromLine && ! $editing)<input type="hidden" name="survey_register_file_id" value="{{ $line->id }}" />@endif

    <fieldset @disabled($locked) style="border:0;padding:0;margin:0;">
    <div class="form-container">
        <div class="form-body">
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-folder-open"></i><span>File</span></div>
                @if ($fromLine || $editing)
                    <div class="form-group"><label>File Number</label><input type="text" name="file_number" value="{{ $report->file_number }}" readonly style="{{ $ro }}" /></div>
                @else
                    @include('survey_module.partials._file_picker', [
                        'id' => 'cr', 'name' => 'file_number', 'value' => $report->file_number, 'label' => 'File Number', 'required' => true,
                        'fill' => ['title' => 'crTitle', 'plot' => 'crPlot', 'district' => 'crDistrict', 'lga' => 'crLga'],
                    ])
                @endif
                <div class="form-group full"><label>File Title</label>
                    <input type="text" name="file_title" id="crTitle" maxlength="500" value="{{ old('file_title', $report->file_title) }}" /></div>
            </div>
            <div class="form-grid cols-3">
                <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Property</span></div>
                <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" id="crPlot" maxlength="100" value="{{ old('plot_no', $report->plot_no) }}" /></div>
                <div class="form-group"><label>District</label><input type="text" name="district" id="crDistrict" maxlength="255" value="{{ old('district', $report->district) }}" /></div>
                <div class="form-group"><label>LGA</label><input type="text" name="lga" id="crLga" maxlength="255" value="{{ old('lga', $report->lga) }}" /></div>
            </div>
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-drafting-compass"></i><span>Plan</span></div>
                <div class="form-group"><label>Plan No.</label><input type="text" name="plan_no" maxlength="100" value="{{ old('plan_no', $report->plan_no) }}" /></div>
                <div class="form-group"><label>Plan prepared by (surveyor)</label><input type="text" name="surveyor_name" maxlength="255" value="{{ old('surveyor_name', $report->surveyor_name) }}" /></div>
            </div>
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-search"></i><span>Findings</span></div>
                <div class="form-group full"><label>Discrepancies found <span class="required">*</span></label>
                    <textarea name="discrepancies" rows="5" maxlength="4000" required placeholder="What in the submitted plan does not match the existing records…">{{ old('discrepancies', $report->discrepancies) }}</textarea></div>
                <div class="form-group full"><label>Changes required <span class="required">*</span></label>
                    <textarea name="required_changes" rows="5" maxlength="4000" required placeholder="One change per line…">{{ old('required_changes', $report->required_changes) }}</textarea>
                    <p class="helper-text">Each line prints as a numbered item.</p></div>
            </div>
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-user-check"></i><span>Examination</span></div>
                <div class="form-group"><label>Examined by</label><input type="text" name="examined_by" maxlength="255" value="{{ old('examined_by', $report->examined_by) }}" /></div>
                <div class="form-group"><label>Date</label><input type="date" name="report_date" value="{{ old('report_date', optional($report->report_date)->format('Y-m-d')) }}" /></div>
            </div>
            <div class="form-actions">
                <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.workflow.change-reports.index') }}"><i class="fas fa-arrow-left"></i> Back</a></div>
                <div class="right">@unless ($locked)<button class="btn btn-success" type="submit"><i class="fas fa-save"></i> Save Draft</button>@endunless</div>
            </div>
        </div>
    </div>
    </fieldset>
</form>

@if ($editing && isset($records))
    @php
        $BNC = \App\Services\Survey\BeaconNumberCheck::class;
        $rk = $records['key'];
        $bs = $records['summary'];
        $card = 'display:block;background:#fff;border:1px solid var(--gray-200,#e5e7eb);border-radius:12px;padding:14px 16px;text-decoration:none;color:inherit;';
    @endphp
    {{-- Survey records on the private report (change list p.2): the same core records as the GKN report. --}}
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
            <p style="margin-top:8px;font-size:12px;color:var(--gray-500);">These lock with the report when it is issued, and print with it.</p>
        @endunless
    </div>
@endif

@if ($editing && ! $locked)
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
        <form method="POST" action="{{ route('survey-module.workflow.change-reports.destroy', $report) }}" onsubmit="return confirm('Delete draft {{ $report->report_ref }}?');">
            @csrf @method('DELETE')
            <button class="btn btn-outline btn-sm" type="submit"><i class="fas fa-trash"></i> Delete Draft</button>
        </form>
        <form method="POST" action="{{ route('survey-module.workflow.change-reports.issue', $report) }}" onsubmit="return confirm('Issue {{ $report->report_ref }}? It is locked and opened for printing. Save any changes first.');">
            @csrf
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-print"></i> Issue &amp; Print</button>
        </form>
    </div>
@endif

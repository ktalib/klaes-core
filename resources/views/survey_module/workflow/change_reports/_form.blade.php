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

@include('cadastral_module.partials._flash')

@php
    $isEdit = (bool) ($report->exists ?? false);
    $action = $isEdit
        ? route('cadastral-module.reports.update', $report)
        : route('cadastral-module.reports.store');
@endphp

<div class="unit-tag"><i class="fas fa-file-lines"></i> 4.2 · Cadastral Report</div>

<div class="page-header">
    <div>@if ($isEdit) <strong>{{ $report->report_ref }}</strong> @endif</div>
    <a href="{{ route('cadastral-module.reports.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to reports
    </a>
</div>

@unless ($isEdit)
    <div class="caveat">
        <i class="fas fa-circle-info"></i>
        <div>
            <strong>The type decides the chain, and the chain is fixed once the report is opened.</strong>
            Verification runs eight steps including a field inspection; customary and statutory run
            the same seven without it. The chain is copied onto the report at creation, so later
            changes to the configured stages never rewrite a report already in flight.
        </div>
    </div>
@endunless

<form method="POST" action="{{ $action }}" class="form-container">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-body">
        <div class="form-grid">
            <div class="form-group">
                <label>Report Type <span class="required">*</span></label>
                <select name="report_type" required @disabled($isEdit)>
                    @foreach (\App\Models\Cadastral\CadastralReport::TYPES as $k => $label)
                        <option value="{{ $k }}" @selected(old('report_type', $report->report_type)===$k)>
                            {{ $label }} ({{ count(config("cadastral_module.stage_chains.$k", [])) }} steps)
                        </option>
                    @endforeach
                </select>
                @if ($isEdit)
                    <div class="helper-text">The chain is already seeded, so the type can no longer change.</div>
                @endif
            </div>

            <div class="form-group">
                <label>File Number <span class="required">*</span></label>
                <input type="text" name="file_number" value="{{ old('file_number', $report->file_number) }}" required />
            </div>

            <div class="form-group">
                <label>File Title</label>
                <input type="text" name="file_title" value="{{ old('file_title', $report->file_title) }}" />
            </div>

            <div class="form-group">
                <label>Plot Number</label>
                <input type="text" name="plot_no" value="{{ old('plot_no', $report->plot_no) }}" />
            </div>

            <div class="form-group">
                <label>Block Number</label>
                <input type="text" name="block_no" value="{{ old('block_no', $report->block_no) }}" />
            </div>

            <div class="form-group">
                <label>Layout</label>
                <input type="text" name="layout_name" value="{{ old('layout_name', $report->layout_name) }}" />
            </div>

            <div class="form-group">
                <label>Chart ID</label>
                <input type="number" name="cadastral_chart_id" value="{{ old('cadastral_chart_id', $report->cadastral_chart_id) }}" />
                <div class="helper-text">Optional — the chart may be created part-way through the chain.</div>
            </div>

            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date', optional($report->due_date)->format('Y-m-d')) }}" />
            </div>

            <div class="form-group">
                <label>Survey Necessary</label>
                <select name="survey_necessary">
                    <option value="">—</option>
                    <option value="Yes" @selected(old('survey_necessary', $report->survey_necessary)==='Yes')>Yes</option>
                    <option value="No" @selected(old('survey_necessary', $report->survey_necessary)==='No')>No</option>
                </select>
                <div class="helper-text">Carried to the Land 12 when the report is approved.</div>
            </div>

            <div class="form-group">
                <label>Beacon Numbers</label>
                <input type="text" name="beacon_numbers" value="{{ old('beacon_numbers', $report->beacon_numbers) }}" />
            </div>

            <div class="form-group">
                <label>Dispatch To</label>
                <input type="text" name="dispatched_to" value="{{ old('dispatched_to', $report->dispatched_to) }}" />
            </div>

            <div class="form-group" style="grid-column:1/-1;">
                <label>Plot Description</label>
                <textarea name="plot_description" rows="3">{{ old('plot_description', $report->plot_description) }}</textarea>
            </div>

            <div class="form-group" style="grid-column:1/-1;">
                <label>Observation — Ground Status</label>
                <textarea name="ground_status" rows="3"
                          placeholder="What was found on the ground">{{ old('ground_status', $report->ground_status) }}</textarea>
            </div>

            <div class="form-group" style="grid-column:1/-1;">
                <label>Observation — Chart Status</label>
                <textarea name="chart_status" rows="3"
                          placeholder="What the chart shows">{{ old('chart_status', $report->chart_status) }}</textarea>
            </div>
        </div>

        @include('cadastral_module.partials._address_builder', [
            'prefix' => 'prop_',
            'mode'   => 'property',
            'model'  => $report,
            'legend' => 'Property Location',
            'plotField' => 'plot_no',
        ])
    </div>

    <div class="form-actions">
        <a href="{{ route('cadastral-module.reports.index') }}" class="btn btn-secondary">Cancel</a>
        @canDo('Cad - Records', $isEdit ? 'edit' : 'create')
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ $isEdit ? 'Save Report' : 'Open the Report' }}
            </button>
        @endcanDo
    </div>
</form>

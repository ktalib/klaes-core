@include('cadastral_module.partials._flash')

@php
    $isEdit = (bool) ($report->exists ?? false);
    $action = $isEdit
        ? route('cadastral-module.reports.update', $report)
        : route('cadastral-module.reports.store');

    // Values the receipt supplies go on the in-memory model so the fields and
    // the builder render them before the picker locks them; nothing saves.
    $picked = $picked ?? null;
    if ($picked && $picked['status'] === 'ok') {
        foreach (\App\Services\Cadastral\CadastralRegistryLookup::lockedInput($picked['values'], ['file_title', 'plot_no', 'prop_house', 'prop_street', 'prop_district', 'prop_lga', 'prop_state']) as $col => $value) {
            $report->{$col} = $value;
        }
    }

    // The file's current chart, offered as the report's chart.
    $chartOptions = collect();
    if ($report->cadastral_chart_id && ($own = \App\Models\Cadastral\CadastralChart::find($report->cadastral_chart_id))) {
        $chartOptions->put($own->id, "{$own->chart_ref} (v{$own->version}, {$own->status})");
    }
    if ($c = $picked['records']['chart'] ?? null) {
        $chartOptions->put($c['id'], "{$c['ref']} ({$c['status']})");
    }
@endphp

<div class="page-header">
    <div>@if ($isEdit) <strong>{{ $report->report_ref }}</strong> · {{ $report->file_number }} @endif</div>
    <a href="{{ route('cadastral-module.reports.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to reports
    </a>
</div>

@include('cadastral_module.partials._wizard')

<form method="POST" action="{{ $action }}" class="form-container" data-wizard
      data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate>
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-stepper" data-wizard-header></div>

    <div class="form-body">

        {{-- 1. The file: a registered intake file, with what its receipt says. --}}
        <section class="form-step" data-step data-title="File" data-icon="folder-search"
                 data-subtitle="{{ $isEdit ? 'The file is fixed once the report is opened.' : 'Pick a file that has been received and registered at intake. Greyed fields come from its receipt; complete any it leaves blank.' }}">
            <div class="form-grid">
                @include('cadastral_module.partials._file_picker', [
                    'scope'   => 'receipt',
                    'hidden'  => $isEdit ? [] : ['cadastral_file_receipt_id'],
                    'initial' => $picked,
                    'fixed'   => $isEdit,
                    'number'  => $report->file_number,
                    'help'    => $isEdit ? null : 'Only a file registered at intake can have a report opened on it.',
                ])

                <div class="form-group">
                    <label>File Title</label>
                    <input type="text" name="file_title" value="{{ old('file_title', $report->file_title) }}" maxlength="500" />
                </div>

                <div class="form-group">
                    <label>Plot Number</label>
                    <input type="text" name="plot_no" value="{{ old('plot_no', $report->plot_no) }}" maxlength="50" />
                </div>

                <div class="form-group">
                    <label>Block Number</label>
                    <input type="text" name="block_no" value="{{ old('block_no', $report->block_no) }}" maxlength="50" />
                </div>

                <div class="form-group">
                    <label>Layout</label>
                    <input type="text" name="layout_name" value="{{ old('layout_name', $report->layout_name) }}" maxlength="255" />
                </div>
            </div>

            @include('cadastral_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $report,
                'legend' => 'Property Location',
                'plotField' => 'plot_no',
            ])
        </section>

        {{-- 2. What kind of report, and what the officer already knows. --}}
        <section class="form-step" data-step data-title="Report Type & Details" data-icon="file-pen-line"
                 data-subtitle="The type decides the stage chain, and the chain is fixed once the report is opened.">
            @unless ($isEdit)
                <div class="caveat">
                    <i class="fas fa-circle-info"></i>
                    <div>
                        Verification runs eight steps including a field inspection; customary and statutory run
                        the same seven without it. The chain is copied onto the report at creation, so later
                        changes to the configured stages never rewrite a report already in flight.
                    </div>
                </div>
            @endunless

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
                    <label>Chart</label>
                    <select name="cadastral_chart_id" id="report-chart">
                        <option value="">— None yet —</option>
                        @foreach ($chartOptions as $id => $text)
                            <option value="{{ $id }}" @selected((string) old('cadastral_chart_id', $report->cadastral_chart_id) === (string) $id)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <div class="helper-text">The file's own chart. Optional — the chart may be created part-way through the chain.</div>
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
                    <input type="text" name="beacon_numbers" value="{{ old('beacon_numbers', $report->beacon_numbers) }}" maxlength="500" />
                </div>

                <div class="form-group">
                    <label>Dispatch To</label>
                    <input type="text" name="dispatched_to" value="{{ old('dispatched_to', $report->dispatched_to) }}" maxlength="255" />
                </div>

                <div class="form-group" style="grid-column:1/-1;">
                    <label>Plot Description</label>
                    <textarea name="plot_description" rows="3" maxlength="8000">{{ old('plot_description', $report->plot_description) }}</textarea>
                </div>

                <div class="form-group" style="grid-column:1/-1;">
                    <label>Observation — Ground Status</label>
                    <textarea name="ground_status" rows="3" maxlength="8000"
                              placeholder="What was found on the ground">{{ old('ground_status', $report->ground_status) }}</textarea>
                </div>

                <div class="form-group" style="grid-column:1/-1;">
                    <label>Observation — Chart Status</label>
                    <textarea name="chart_status" rows="3" maxlength="8000"
                              placeholder="What the chart shows">{{ old('chart_status', $report->chart_status) }}</textarea>
                </div>
            </div>
        </section>

        <section class="form-step" data-step data-review data-title="Review" data-icon="clipboard-check"
                 data-subtitle="{{ $isEdit ? 'Check the changes, then save.' : 'Check everything, then open the report. Its stage chain is seeded the moment it opens.' }}">
            <div data-wizard-summary></div>
        </section>
    </div>

    <div class="form-actions" data-wizard-nav>
        <a href="{{ route('cadastral-module.reports.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="button" class="btn btn-outline" data-wizard-back><i data-lucide="arrow-left"></i> Back</button>
        <button type="button" class="btn btn-primary" data-wizard-next>Next <i data-lucide="arrow-right"></i></button>
        @canDo('Cad - Records', $isEdit ? 'edit' : 'create')
            <button type="submit" class="btn btn-primary" data-wizard-submit>
                <i data-lucide="{{ $isEdit ? 'save' : 'file-plus-2' }}"></i> {{ $isEdit ? 'Save Report' : 'Open the Report' }}
            </button>
        @endcanDo
    </div>
</form>

{{-- The chart list follows the picked file: only that file's chart is offered
     (ReportController::validated refuses any other). --}}
<script>
document.addEventListener('cadastral:file-picked', function (e) {
    var select = document.getElementById('report-chart');
    if (!select || select.closest('form') !== e.target || (e.detail && e.detail.initial)) return;
    var chart = e.detail && e.detail.status === 'ok' && e.detail.records ? e.detail.records.chart : null;
    while (select.options.length > 1) select.remove(1);
    if (chart) select.add(new Option(chart.ref + ' (' + chart.status + ')', chart.id, false, false));
});
</script>

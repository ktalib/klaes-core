@php
    use App\Models\Survey\SurveyChangeReport as CR;
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    // Surveyor's Report (Private) records (sidebar, user 2026-10-30): ?records= opens the list to pick a report for that record.
    $recordPages = [
        'coordinates'  => ['List of Coordinates', 'survey-module.workflow.private.coordinates.show', 'map-pin'],
        'beacons'      => ['Beacon Nos', 'survey-module.workflow.private.beacons.show', 'map-pinned'],
        'observations' => ['Computation Field Observations', 'survey-module.workflow.private.observations.show', 'calculator'],
        'sketch'       => ['Sketch Plan', 'survey-module.workflow.private.observations.show', 'pen-tool'],
    ];
    $recordMode = $recordPages[request('records')] ?? null;
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
@endphp

@include('survey_module.partials._flash')

<style>
    /* One table in three sections, tinted like the Batch Recommendation banks. */
    .survey-proto th.rs-band { text-align: center; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; }
    .survey-proto th.rs-beacons { background: #fffbeb; color: #92400e; }
    .survey-proto th.rs-coords { background: #f0f9ff; color: #075985; }
    .survey-proto th.rs-obs { background: #f5f3ff; color: #5b21b6; }
    .survey-proto th.rs-split, .survey-proto td.rs-split { border-left: 2px solid var(--gray-300, #d1d5db); }
</style>

<div class="page-header">
    <div>@include('survey_module.partials._records_export', ['endpoint' => route('survey-module.workflow.change-reports.export'), 'title' => "Surveyor's Report (Private)", 'dateLabel' => 'Report Date', 'statuses' => [CR::DRAFT, CR::ISSUED]])</div>
    {{-- "Capture Observation" on the Computation Field Observations view (user, 2026-11-03). --}}
    <a href="{{ route('survey-module.workflow.change-reports.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ request('records') === 'observations' ? 'Capture Observation' : 'Create Report' }}</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Reports</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Draft</div><div class="kpi-value">{{ number_format($stats['draft']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Issued</div><div class="kpi-value">{{ number_format($stats['issued']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Awaiting a report</div><div class="kpi-value">{{ number_format($waiting->count()) }}</div></div>
</div>

{{-- No "Choose a report below" hint on the record views (user, 2026-11-03). --}}

@if ($waiting->isNotEmpty())
    <div class="comp-type-note" style="margin-bottom:16px;display:block;">
        <i class="fas fa-info-circle"></i> Examination files cleared with <strong>Changes required</strong> and no report yet:
        @foreach ($waiting as $w)
            <a href="{{ route('survey-module.workflow.change-reports.create', ['line' => $w->id]) }}" style="margin-left:6px;white-space:nowrap;">{{ $w->register_ref }} · {{ $w->file_number }}</a>{{ $loop->last ? '' : ',' }}
        @endforeach
    </div>
@endif

<form method="GET" class="table-toolbar">
    @if ($recordMode)<input type="hidden" name="records" value="{{ request('records') }}" />@endif
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search report, file, plan, surveyor…" style="{{ $inputStyle }}width:260px;" />
        <select name="status" style="{{ $inputStyle }}"><option value="">All Status</option>
            @foreach ([CR::DRAFT, CR::ISSUED] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status']))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.workflow.change-reports.index', $recordMode ? ['records' => request('records')] : []) }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th colspan="11" class="rs-band">Surveyor's Report (Private)</th>
                    @include('survey_module.partials.sections._report_sections', ['part' => 'bands', 'a' => null])
                    <th></th>
                </tr>
                <tr><th>S/N</th><th>Report No</th><th>Register File</th><th>File No.</th><th>Plan No.</th><th>Surveyor</th>
                    <th>Plot No.</th><th>District</th><th>LGA</th><th>Date</th><th>Status</th>
                    @include('survey_module.partials.sections._report_sections', ['part' => 'heads', 'a' => null])
                    <th></th></tr>
            </thead>
            <tbody>
                @forelse ($reports as $r)
                    <tr>
                        <td>{{ $reports->firstItem() + $loop->index }}</td>
                        <td>@if ($recordMode)<a href="{{ route($recordMode[1], 'private-' . $r->id) }}" title="Open {{ $recordMode[0] }}"><strong>{{ $r->report_ref }}</strong></a>@else<strong>{{ $r->report_ref }}</strong>@endif</td>
                        <td>{{ $r->registerFile?->register_ref ?: '—' }}</td>
                        <td style="font-family:monospace;">{{ $r->file_number ?: '—' }}</td>
                        <td>{{ $r->plan_no ?: '—' }}</td>
                        <td>{{ $r->surveyor_name ?: '—' }}</td>
                        <td>{{ $r->plot_no ?: '—' }}</td>
                        <td>{{ $r->district ?: '—' }}</td>
                        <td>{{ $r->lga ?: '—' }}</td>
                        <td>{{ optional($r->report_date)->format('d M Y') ?: '—' }}</td>
                        <td>{!! $chip($r->status, $r->isIssued() ? 'green' : 'gray') !!}</td>
                        @include('survey_module.partials.sections._report_sections', ['part' => 'cells', 'rec' => $rowRecords[$r->id]['rec'] ?? null, 'a' => $rowRecords[$r->id]['anchor'] ?? null])
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const rr = $refs.btn.getBoundingClientRect(); top = rr.bottom + 4; left = Math.max(8, rr.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.workflow.change-reports.edit', $r) }}" class="{{ $svItem }}"><i data-lucide="{{ $r->isIssued() ? 'eye' : 'pencil' }}" class="w-4 h-4 text-gray-500"></i> {{ $r->isIssued() ? 'View' : 'Edit / Issue' }}</a>
                                    <a href="{{ route('survey-module.workflow.change-reports.print', $r) }}" target="_blank" class="{{ $svItem }}"><i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print</a>
                                    @foreach (array_slice($recordPages, 0, 3, true) as [$label, $route, $icon])
                                        <a href="{{ route($route, 'private-' . $r->id) }}" class="{{ $svItem }}"><i data-lucide="{{ $icon }}" class="w-4 h-4 text-gray-500"></i> {{ $label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="22" style="text-align:center;padding:28px;color:var(--gray-500);">
                        {{ request()->hasAny(['q', 'status']) ? 'No reports match these filters.' : 'No reports yet.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $reports->firstItem() ?? 0 }}–{{ $reports->lastItem() ?? 0 }} of {{ number_format($reports->total()) }} reports</span>
        <div class="pagination">{{ $reports->links() }}</div>
    </div>
</div>

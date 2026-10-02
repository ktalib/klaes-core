{{--
    The report detail page (rebuild plan Phase 4).

    Report information, the shared stage tracker with the current step's
    actions (Advance, Return, Approve/Reject, Dispatch), Assign, and the Report
    on Application questionnaire filled on the Report step. Every action is
    enforced against the step's required post in ReportWorkflow; the screen
    shows the same reason the server would refuse with.

    Manual chart: the scan is uploaded into the file's EDMS folder as a page
    (CadastralDocuments, Phase 5), and the typed scan path on the chart is kept
    beside it as the fallback. Both are shown.
--}}
@include('cadastral_module.partials._flash')

@php
    $typeRoute = 'cadastral-module.reports.' . $report->report_type;
    $backUrl = \Illuminate\Support\Facades\Route::has($typeRoute) ? route($typeRoute) : route('cadastral-module.reports.index');

    $assigneeName = $report->assigned_user_id ? ($userNames[$report->assigned_user_id] ?? 'User #' . $report->assigned_user_id) : null;
    $latestNote = $progress['steps']->filter(fn ($s) => filled($s->note))
        ->sortByDesc(fn ($s) => optional($s->updated_at)->timestamp ?? 0)->first();
    $location = \App\Services\Cadastral\CadastralAddress::propertyLocation($report);

    $assignBlocked = $current ? $workflow->blockReason($report, $current) : 'There is no open step.';
@endphp

<div class="unit-tag"><i class="fas fa-file-lines"></i> 4.2 · Cadastral Report · {{ $report->type_label }}</div>

<div class="page-header">
    <div>
        <strong>{{ $report->report_ref }}</strong> · {{ $report->type_label }} · {{ $report->file_number }}
        <span class="status-badge {{ $report->status_badge }}"><span class="dot"></span>{{ $report->status }}</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @canDo('Cad - Records', 'print')
            <a href="{{ route('cadastral-module.reports.application-print', $report) }}" target="_blank" class="btn btn-outline btn-sm">
                <i class="fas fa-file-signature"></i> Report on Application
            </a>
            <a href="{{ route('cadastral-module.reports.print', $report) }}" target="_blank" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Internal Print
            </a>
        @endcanDo
        @unless ($report->isFinished())
            @canDo('Cad - Records', 'edit')
                <a href="{{ route('cadastral-module.reports.edit', $report) }}" class="btn btn-outline btn-sm">
                    <i class="fas fa-edit"></i> Edit
                </a>
            @endcanDo
        @endunless
        <a href="{{ $backUrl }}" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> {{ $report->type_label }} Reports
        </a>
    </div>
</div>

<div class="section-cards" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.35fr);gap:16px;align-items:start;">

    {{-- Report information --}}
    <div>
        <div class="dash-card">
            <div class="card-header"><strong>Report Information</strong></div>
            <div class="list-item"><span>Report ID</span><strong>{{ $report->report_ref }}</strong></div>
            <div class="list-item"><span>Type</span><strong>{{ $report->type_label }}</strong></div>
            <div class="list-item"><span>File No</span><strong>{{ $report->file_number }}</strong></div>
            <div class="list-item"><span>Owner</span><strong>{{ $report->file_title ?: '—' }}</strong></div>
            {{-- District, LGA, State; the plot is its own row. --}}
            <div class="list-item"><span>Location</span><strong>{{ $location ?: '—' }}</strong></div>
            <div class="list-item"><span>Plot / Block</span><strong>{{ $report->plot_no ?: '—' }}{{ $report->block_no ? ' / ' . $report->block_no : '' }}</strong></div>
            <div class="list-item"><span>Layout</span><strong>{{ $report->layout_name ?: '—' }}</strong></div>
            <div class="list-item">
                <span>Assignee</span>
                <strong>
                    @if ($report->isFinished())
                        —
                    @elseif ($assigneeName)
                        {{ $assigneeName }}
                    @else
                        <span style="font-weight:500;color:var(--gray-500);">Unassigned · {{ $workflow->postLabel($report->assigned_post) ?? 'any officer' }} desk</span>
                    @endif
                </strong>
            </div>
            <div class="list-item"><span>Due</span><strong>{{ optional($report->due_date)->format('d M Y') ?: '—' }}</strong></div>
            @if ($report->dispatched_at)
                <div class="list-item"><span>Dispatched</span><strong>{{ $report->dispatched_at->format('d M Y H:i') }}{{ $report->dispatched_to ? ' → ' . $report->dispatched_to : '' }}</strong></div>
            @endif
            <div class="list-item" style="align-items:flex-start;">
                <span>Notes</span>
                <strong style="font-weight:500;text-align:right;white-space:pre-wrap;">@if ($latestNote){{ $latestNote->note }} <span style="color:var(--gray-500);">({{ $latestNote->step_name }})</span>@else—@endif</strong>
            </div>
            @if ($report->receipt)
                <div class="list-item">
                    <span>From Receipt</span>
                    <strong>
                        <a href="{{ route('cadastral-module.registry.receipts.edit', $report->receipt) }}">
                            {{ $report->receipt->receipt_ref }}
                        </a>
                        <span style="font-weight:500;color:var(--gray-500);">· {{ $report->receipt->source_registry }}</span>
                    </strong>
                </div>
            @endif
            @if ($report->chart)
                <div class="list-item">
                    <span>Chart</span>
                    <strong>
                        <a href="{{ route('cadastral-module.charting.edit', $report->chart) }}">
                            {{ $report->chart->chart_ref }} (v{{ $report->chart->version }})
                        </a>
                    </strong>
                </div>
            @endif
        </div>

        {{-- Assign the current step --}}
        @if ($current && ! $report->isFinished())
            <div class="dash-card" style="margin-top:16px;">
                <div class="card-header">
                    <strong>Assign</strong>
                    <span class="helper-text" style="margin:0;">{{ $current->step_name }} · {{ $workflow->postLabel($current->required_post) ?? 'any officer' }}</span>
                </div>
                @canDo('Cad - Records', 'edit')
                    @if ($assignable)
                        <form method="POST" action="{{ route('cadastral-module.reports.assign', $report) }}" style="display:flex;gap:6px;flex-wrap:wrap;">
                            @csrf
                            <select name="assigned_user_id" required @disabled($assignBlocked)
                                    style="flex:1;min-width:180px;padding:7px 10px;border:1px solid var(--gray-300);border-radius:4px;font-size:13px;background:#fff;">
                                <option value="">Choose an officer…</option>
                                @foreach ($assignable as $uid => $uname)
                                    <option value="{{ $uid }}" @selected((int) $report->assigned_user_id === (int) $uid)>{{ $uname }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm" @disabled($assignBlocked)><i class="fas fa-user-check"></i> Assign</button>
                        </form>
                        @if ($assignBlocked)
                            <div class="helper-text" style="margin-top:6px;"><i class="fas fa-lock"></i> {{ $assignBlocked }}</div>
                        @endif
                    @else
                        <div class="helper-text" style="margin:0;">
                            Nobody with a KLAES login holds the {{ $workflow->postLabel($current->required_post) ?? 'Cadastral officer' }} post yet
                            (cadastral_officers.user_id / post_code), so there is no one to assign.
                        </div>
                    @endif
                @endcanDo
            </div>
        @endif
    </div>

    {{-- Stage tracker --}}
    <div class="dash-card">
        <div class="card-header">
            <strong>Stages</strong>
            <span>{{ $progress['done'] }} of {{ $progress['total'] }} done ({{ $progress['percent'] }}%)</span>
        </div>
        <div style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin:4px 0 16px;">
            <div style="height:100%;width:{{ $progress['percent'] }}%;background:#22c55e;"></div>
        </div>
        @if ($myPosts)
            <div class="helper-text" style="margin:-6px 0 12px;">
                Your post{{ count($myPosts) > 1 ? 's' : '' }}: {{ implode(', ', array_map(fn ($p) => $workflow->postLabel($p), $myPosts)) }}
            </div>
        @endif

        @include('cadastral_module.partials._stage_tracker', [
            'report'      => $report,
            'steps'       => $progress['steps'],
            'workflow'    => $workflow,
            'interactive' => true,
            'userNames'   => $userNames,
        ])
    </div>
</div>

@include('cadastral_module.partials._report_application_form', ['report' => $report, 'application' => $application])

<div class="dash-card" style="margin-top:18px;">
    <div class="card-header"><strong>Observations</strong></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;padding:8px 0;">
        <div>
            <div class="kpi-label">Ground Status</div>
            <div style="white-space:pre-wrap;">{{ $report->ground_status ?: '—' }}</div>
        </div>
        <div>
            <div class="kpi-label">Chart Status</div>
            <div style="white-space:pre-wrap;">{{ $report->chart_status ?: '—' }}</div>
        </div>
        <div>
            <div class="kpi-label">Plot Description</div>
            <div style="white-space:pre-wrap;">{{ $report->plot_description ?: '—' }}</div>
        </div>
    </div>
</div>

{{-- Manual chart --}}
@php
    $manualDocs = app(\App\Services\Cadastral\CadastralDocuments::class)->for(
        \App\Services\Cadastral\CadastralDocuments::OWNER_REPORT,
        $report->id,
        \App\Services\Cadastral\CadastralDocuments::KIND_MANUAL_CHART
    );
    $canUploadChart = \App\Services\Cadastral\CadastralDocuments::installed();
@endphp
<div class="form-container" style="margin-top:18px;">
    <div class="card-header">
        <strong>Manual Chart</strong>
        <span class="helper-text" style="margin:0;">The scanned trimsheet or topsheet, and the chart it belongs to.</span>
    </div>

    <div class="form-body" style="padding-bottom:0;">
        <div class="form-grid">
            <div class="form-group">
                <label>Uploaded Scan</label>
                @include('cadastral_module.partials._documents', ['docs' => $manualDocs])
                <div class="helper-text">Filed into the file's EDMS folder; it appears in Page Typing like any scanned page. A new upload supersedes the old, which is kept.</div>
            </div>
            <div class="form-group">
                <label>Typed Scan Path</label>
                <div>{{ $report->chart?->manual_chart_path ?: '—' }}</div>
                <div class="helper-text">The fallback: a scan already on disk, recorded on the chart.</div>
            </div>
        </div>
    </div>

    @canDo('Cad - Records', 'create')
        <form method="POST" action="{{ route('cadastral-module.reports.manual-chart.store', $report) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Chart ID</label>
                        <input type="number" name="cadastral_chart_id" value="{{ $report->cadastral_chart_id }}" />
                        <div class="helper-text">Checked against this report's file number before it is attached.</div>
                    </div>
                    <div class="form-group">
                        <label>Upload the Scan</label>
                        @if ($canUploadChart)
                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" />
                            <div class="helper-text">PDF, JPG or PNG, up to 10 MB.</div>
                        @else
                            <div class="helper-text">Upload is pending installation (the Phase 5 migration). Type the scan path for now.</div>
                        @endif
                    </div>
                    <div class="form-group">
                        <label>…or Type the Scan Path</label>
                        <input type="text" name="manual_chart_path"
                               value="{{ $report->chart?->manual_chart_path }}" />
                        <div class="helper-text">Required only when no scan is uploaded.</div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paperclip"></i> Record the Chart</button>
            </div>
        </form>
    @endcanDo
</div>

{{-- Field inspection: verification only --}}
@if ($report->report_type === \App\Models\Cadastral\CadastralReport::TYPE_VERIFICATION)
    <div class="form-container" style="margin-top:18px;">
        <div class="card-header">
            <strong>Field Inspection</strong>
            <span class="helper-text" style="margin:0;">
                Only verification reports have this step. GPS is a single point — KLAES has no
                geometry type, so the fix locates the visit, it does not describe the parcel.
            </span>
        </div>

        @if ($report->inspections->isNotEmpty())
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Date</th><th>Officer</th><th>GPS</th><th>Development</th><th>Occupancy</th><th>Encroachment</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report->inspections as $inspection)
                            <tr>
                                <td>{{ optional($inspection->inspected_on)->format('d M Y') ?: '—' }}</td>
                                <td>{{ $inspection->field_officer_name ?: '—' }}</td>
                                <td>
                                    @if ($inspection->hasFix())
                                        {{ number_format($inspection->gps_latitude, 6) }}, {{ number_format($inspection->gps_longitude, 6) }}
                                    @else — @endif
                                </td>
                                <td>{{ $inspection->development_status ?: '—' }}</td>
                                <td>{{ $inspection->occupancy_status ?: '—' }}</td>
                                <td>
                                    @if ($inspection->encroachment)
                                        <span class="status-badge rejected"><span class="dot"></span>Yes</span>
                                    @else
                                        <span class="status-badge active"><span class="dot"></span>No</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @canDo('Cad - Records', 'create')
            <form method="POST" action="{{ route('cadastral-module.reports.inspections.store', $report) }}">
                @csrf
                <div class="form-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Inspected On</label>
                            <input type="date" name="inspected_on" value="{{ now()->format('Y-m-d') }}" />
                        </div>
                        <div class="form-group">
                            <label>Field Officer</label>
                            <input type="text" name="field_officer_name" value="{{ auth()->user()->name }}" />
                        </div>
                        <div class="form-group">
                            <label>GPS Latitude</label>
                            <input type="number" step="0.0000001" name="gps_latitude" />
                        </div>
                        <div class="form-group">
                            <label>GPS Longitude</label>
                            <input type="number" step="0.0000001" name="gps_longitude" />
                        </div>
                        <div class="form-group">
                            <label>Accuracy (m)</label>
                            <input type="number" step="0.01" name="gps_accuracy_m" />
                        </div>
                        <div class="form-group">
                            <label>Development Status</label>
                            <input type="text" name="development_status" placeholder="Undeveloped, foundation, completed…" />
                        </div>
                        <div class="form-group">
                            <label>Occupancy</label>
                            <input type="text" name="occupancy_status" placeholder="Vacant, owner-occupied, tenanted…" />
                        </div>
                        <div class="form-group">
                            <label>Access Road</label>
                            <input type="text" name="access_road" />
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;">
                                <input type="checkbox" name="encroachment" value="1" /> Encroachment found
                            </label>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Encroachment Note</label>
                            <input type="text" name="encroachment_note" />
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Ground Findings</label>
                            <textarea name="ground_findings" rows="3"></textarea>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Chart Findings</label>
                            <textarea name="chart_findings" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-location-dot"></i> Record the Inspection</button>
                </div>
            </form>
        @endcanDo
    </div>
@endif

@if ($land12 && in_array($report->status, ['Approved', 'Dispatched'], true))
    <div class="form-container" style="margin-top:18px;">
        <div class="form-body">
            <div class="helper-text">
                <a href="{{ $land12 }}" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-export"></i> Open a Land 12 with these details
                </a>
                The Land 12 register is owned by its own screen; this pre-fills it rather than
                writing into it.
            </div>
        </div>
    </div>
@endif

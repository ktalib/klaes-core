{{--
    The report list (rebuild plan Phase 4): All Reports, and the Verification,
    Customary and Statutory pages, which are this list with the type fixed
    ($scopeType), a header naming that stream's chain, and Start Report for it.

    Expects $reports (steps loaded), $stats, $scopeType, $typeFilter,
    $userNames (users.id => name) and $workflow.
--}}
@include('cadastral_module.partials._flash')

@php
    $types = \App\Models\Cadastral\CadastralReport::TYPES;
    $typeRoutes = [
        'verification' => 'cadastral-module.reports.verification',
        'customary'    => 'cadastral-module.reports.customary',
        'statutory'    => 'cadastral-module.reports.statutory',
    ];
    $listUrl = $scopeType ? route($typeRoutes[$scopeType]) : route('cadastral-module.reports.index');
    $chain = $scopeType ? $workflow->chainFor($scopeType) : [];
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
@endphp


<div class="page-header">
    <div></div>
    <div style="display:flex;gap:8px;">
        @canDo('Cad - Records', 'export')
            <a href="{{ route('cadastral-module.reports.export', array_filter(['report_type' => $typeFilter, 'status' => request('status')])) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-file-csv"></i> Export
            </a>
        @endcanDo
        <a href="{{ route('cadastral-module.reports.dashboard') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-table-columns"></i> Desk Dashboard
        </a>
        @canDo('Cad - Records', 'create')
            <a href="{{ route('cadastral-module.reports.create', $scopeType ? ['type' => $scopeType] : []) }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> {{ $scopeType ? 'Start ' . $types[$scopeType] . ' Report' : 'Start Report' }}
            </a>
        @endcanDo
    </div>
</div>

@if ($scopeType)
    @php
        // One icon per stage key; unknown keys fall back to a plain circle.
        $stageIcons = [
            'registration'     => 'fa-inbox',
            'field_inspection' => 'fa-map-location-dot',
            'manual_chart'     => 'fa-file-arrow-up',
            'digital_chart'    => 'fa-draw-polygon',
            'report'           => 'fa-file-pen',
            'checking'         => 'fa-list-check',
            'approval'         => 'fa-stamp',
            'dispatch'         => 'fa-paper-plane',
        ];
        // One colour per stage: [accent (-700), tint (-50)], walking the wheel so
        // neighbours never look alike. Raw hex: Tailwind here is the v2 CDN.
        $stageColors = [
            'registration'     => ['#1d4ed8', '#eff6ff'],  // blue
            'field_inspection' => ['#0f766e', '#f0fdfa'],  // teal
            'manual_chart'     => ['#6d28d9', '#f5f3ff'],  // violet
            'digital_chart'    => ['#4338ca', '#eef2ff'],  // indigo
            'report'           => ['#b45309', '#fffbeb'],  // amber
            'checking'         => ['#0e7490', '#ecfeff'],  // cyan
            'approval'         => ['#15803d', '#f0fdf4'],  // green
            'dispatch'         => ['#be123c', '#fff1f2'],  // rose
        ];
        $stageCounts = $stageCounts ?? [];
        $stageFilter = $stageFilter ?? '';
        $openTotal = array_sum($stageCounts);
    @endphp
    <div class="dash-card cad-stages" style="margin-bottom:18px;">
        <div class="card-header" style="align-items:flex-start;">
            <div>
                <strong style="font-size:15px;">{{ $types[$scopeType] }} workflow</strong>
                <div class="helper-text" style="margin:2px 0 0;">
                    Every {{ strtolower($types[$scopeType]) }} report moves through these {{ count($chain) }} stages in order.
                    The chain is set when a report is started, so later changes to the workflow never alter a report already in progress.
                </div>
            </div>
            <div style="text-align:right;white-space:nowrap;">
                <div style="font-size:22px;font-weight:700;color:#0f172a;line-height:1;">{{ number_format($openTotal) }}</div>
                <div class="helper-text" style="margin:0;">open {{ \Illuminate\Support\Str::plural('report', $openTotal) }}</div>
            </div>
        </div>

        <div class="cad-stage-track">
            @foreach ($chain as $i => $stage)
                @php
                    $n = $stageCounts[$stage['key']] ?? 0;
                    $isActive = $stageFilter === $stage['key'];
                    $href = $isActive ? $listUrl : $listUrl . '?stage=' . urlencode($stage['key']);
                    [$accent, $tint] = $stageColors[$stage['key']] ?? ['#475569', '#f8fafc'];
                @endphp
                <a href="{{ $href }}" class="cad-stage{{ $isActive ? ' is-active' : '' }}{{ $n ? ' has-work' : '' }}"
                   style="--stage-accent:{{ $accent }};--stage-tint:{{ $tint }};"
                   title="{{ $isActive ? 'Show all stages' : 'Show reports at ' . $stage['name'] }}">
                    <span class="cad-stage-icon"><i class="fas {{ $stageIcons[$stage['key']] ?? 'fa-circle' }}"></i></span>
                    <span class="cad-stage-no">Stage {{ $i + 1 }}</span>
                    <span class="cad-stage-name">{{ $stage['name'] }}</span>
                    <span class="cad-stage-desk">
                        <i class="fas fa-user-tie"></i>
                        {{ $stage['post'] ? $workflow->postLabel($stage['post']) : 'Any officer' }}
                    </span>
                    <span class="cad-stage-count">{{ $n }} {{ $n === 1 ? 'report' : 'reports' }} here</span>
                </a>
            @endforeach
        </div>

        @if ($stageFilter !== '')
            <div class="helper-text" style="margin:10px 0 0;">
                <i class="fas fa-filter"></i> Showing open reports at one stage only.
                <a href="{{ $listUrl }}">Show all stages</a>
            </div>
        @endif
    </div>

    <style>
        .cadastral-proto .cad-stage-track {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            counter-reset: none;
        }
        .cadastral-proto .cad-stage {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 12px 10px;
            border: 1.5px solid #e2e8f0;
            border-top: 4px solid var(--stage-accent);
            border-radius: 10px;
            background: #fff;
            color: #334155;
            text-decoration: none;
            transition: border-color .15s, box-shadow .15s, transform .15s, background .15s;
        }
        .cadastral-proto .cad-stage:hover {
            background: var(--stage-tint);
            box-shadow: 0 2px 8px rgba(15, 23, 42, .08);
            transform: translateY(-1px);
        }
        /* Selected stage (the list is filtered to it): outlined in its own colour. */
        .cadastral-proto .cad-stage.is-active {
            background: var(--stage-tint);
            border-color: var(--stage-accent);
            box-shadow: 0 0 0 2px var(--stage-accent);
        }
        .cadastral-proto .cad-stage-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--stage-tint); color: var(--stage-accent); font-size: 14px;
        }
        .cadastral-proto .cad-stage.is-active .cad-stage-icon { background: var(--stage-accent); color: #fff; }
        .cadastral-proto .cad-stage-no { font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--stage-accent); }
        .cadastral-proto .cad-stage-name { font-size: 13.5px; font-weight: 700; color: #0f172a; line-height: 1.25; }
        .cadastral-proto .cad-stage-desk { font-size: 11.5px; color: #64748b; line-height: 1.3; }
        .cadastral-proto .cad-stage-desk i { font-size: 10px; margin-right: 3px; }
        .cadastral-proto .cad-stage-count {
            align-self: flex-start; margin-top: 6px; padding: 2px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 600; color: #64748b; background: #f1f5f9;
        }
        /* Work waiting: the count becomes a solid pill in the stage's colour. */
        .cadastral-proto .cad-stage.has-work .cad-stage-count { background: var(--stage-accent); color: #fff; }
    </style>
@endif

<style>
    /* Progress bar in the Current Stage column: one segment per stage. */
    .cadastral-proto .cad-progress { display: flex; gap: 2px; margin-bottom: 4px; max-width: 160px; }
    .cadastral-proto .cad-progress span { flex: 1; height: 5px; min-width: 8px; border-radius: 3px; background: #e2e8f0; }
    .cadastral-proto .cad-progress span.done { background: #16a34a; }
    .cadastral-proto .cad-progress span.current { background: #f59e0b; }
    .cadastral-proto .cad-progress span.returned { background: #dc2626; }
</style>

<div class="kpi-grid">
    <a class="kpi-card" href="{{ route('cadastral-module.reports.index') }}" style="text-decoration:none;color:inherit;">
        <div class="kpi-label">All Reports</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </a>
    @foreach ($typeRoutes as $k => $routeName)
        <a class="kpi-card" href="{{ route($routeName) }}" style="text-decoration:none;color:inherit;{{ $scopeType === $k ? 'box-shadow:0 0 0 2px #fda4af;' : '' }}">
            <div class="kpi-label">{{ $types[$k] }}</div>
            <div class="kpi-value">{{ number_format($stats[$k]) }}</div>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ $listUrl }}" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Report ID or file number…"
               style="{{ $inputStyle }}width:260px;" />

        @unless ($scopeType)
            <select name="report_type" style="{{ $inputStyle }}">
                <option value="">All Types</option>
                @foreach ($types as $k => $label)
                    <option value="{{ $k }}" @selected($typeFilter === $k)>{{ $label }}</option>
                @endforeach
            </select>
        @endunless

        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach (\App\Models\Cadastral\CadastralReport::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>

        <select name="assigned_post" style="{{ $inputStyle }}">
            <option value="">All Desks</option>
            @foreach (config('cadastral_module.posts') as $code => $label)
                <option value="{{ $code }}" @selected(request('assigned_post')===$code)>{{ $label }}</option>
            @endforeach
        </select>

        @if (($stageFilter ?? '') !== '')
            <input type="hidden" name="stage" value="{{ $stageFilter }}" />
        @endif

        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','report_type','status','assigned_post','stage']))
            <a class="btn btn-outline btn-sm" href="{{ $listUrl }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>File No</th>
                    <th>Type</th>
                    <th>Owner</th>
                    <th>Location</th>
                    <th>Assigned Officer</th>
                    <th>Current Stage</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reports as $report)
                    @php
                        $currentStep = $report->steps->firstWhere('step_no', $report->current_step);
                        $assignee = $report->assigned_user_id && isset($userNames[$report->assigned_user_id])
                            ? $userNames[$report->assigned_user_id]
                            : null;
                    @endphp
                    <tr>
                        <td><a href="{{ route('cadastral-module.reports.show', $report) }}"><strong>{{ $report->report_ref }}</strong></a></td>
                        <td>{{ $report->file_number }}</td>
                        <td>{{ $report->type_label }}</td>
                        <td>{{ $report->file_title ?: '—' }}</td>
                        {{-- District, LGA, State; never the plot. --}}
                        <td>{{ \App\Services\Cadastral\CadastralAddress::propertyLocation($report) ?: '—' }}</td>
                        <td>
                            @if ($report->isFinished())
                                —
                            @elseif ($assignee)
                                {{ $assignee }}
                            @else
                                <span style="color:var(--gray-500);">{{ $workflow->postLabel($report->assigned_post) ?? 'Any officer' }} desk</span>
                            @endif
                        </td>
                        <td>
                            @if ($report->isFinished())
                                <span style="color:var(--gray-500);">{{ $report->status === 'Dispatched' ? 'Completed' : 'Closed' }}</span>
                            @else
                                <div class="cad-progress" aria-hidden="true">
                                    @foreach ($report->steps->sortBy('step_no') as $s)
                                        <span class="{{ in_array($s->status, ['done', 'skipped'], true) ? 'done' : ($s->status === 'returned' ? 'returned' : ($s->step_no === $report->current_step ? 'current' : '')) }}"
                                              title="{{ $s->step_no }}. {{ $s->step_name }} — {{ ucfirst($s->status) }}"></span>
                                    @endforeach
                                </div>
                                <span style="font-size:12.5px;">
                                    <strong>{{ $currentStep?->step_name ?? Str::headline((string) $report->current_step_key) }}</strong>
                                    <span style="color:var(--gray-500);">· stage {{ $report->current_step }} of {{ $report->steps->count() }}</span>
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="status-badge {{ $report->status_badge }}">
                                <span class="dot"></span>{{ $report->status }}
                            </span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('cadastral-module.reports.show', $report) }}" title="Open"><i class="fas fa-eye"></i></a>
                                @canDo('Cad - Records', 'print')
                                    <a href="{{ route('cadastral-module.reports.application-print', $report) }}" target="_blank" title="Report on Application (official form)"><i class="fas fa-file-signature"></i></a>
                                    <a href="{{ route('cadastral-module.reports.print', $report) }}" target="_blank" title="Internal report print"><i class="fas fa-print"></i></a>
                                @endcanDo
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-file-lines" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No {{ $scopeType ? strtolower($types[$scopeType]) . ' ' : '' }}reports{{ request()->hasAny(['q','report_type','status','assigned_post']) ? ' match this filter' : ' yet' }}.
                            @canDo('Cad - Records', 'create')
                                <a href="{{ route('cadastral-module.reports.create', $scopeType ? ['type' => $scopeType] : []) }}">Start one</a>.
                            @endcanDo
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $reports->firstItem() ?? 0 }}–{{ $reports->lastItem() ?? 0 }} of {{ number_format($reports->total()) }} reports</span>
        <div class="pagination">{{ $reports->links() }}</div>
    </div>
</div>

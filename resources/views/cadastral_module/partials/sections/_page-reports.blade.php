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

<div class="unit-tag"><i class="fas fa-file-lines"></i> 4.2 · Cadastral Report{{ $scopeType ? ' · ' . $types[$scopeType] : '' }}</div>

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
    <div class="dash-card" style="margin-bottom:18px;">
        <div class="card-header">
            <strong>{{ $types[$scopeType] }} reports</strong>
            <span class="helper-text" style="margin:0;">{{ count($chain) }} stages, fixed on each report when it is opened</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:12.5px;">
            @foreach ($chain as $i => $stage)
                <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:#f1f5f9;color:#334155;">
                    <strong>{{ $i + 1 }}</strong> {{ $stage['name'] }}
                    @if ($stage['post'])
                        <span style="color:#64748b;">· {{ $workflow->postLabel($stage['post']) }}</span>
                    @endif
                </span>
                @if (! $loop->last)<i class="fas fa-chevron-right" style="color:#cbd5e1;font-size:10px;"></i>@endif
            @endforeach
        </div>
    </div>
@endif

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

        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','report_type','status','assigned_post']))
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
                                {{ $report->current_step }}/{{ $report->steps->count() }} ·
                                {{ $currentStep?->step_name ?? Str::headline((string) $report->current_step_key) }}
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

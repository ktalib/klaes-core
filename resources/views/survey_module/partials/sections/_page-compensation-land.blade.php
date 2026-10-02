@php
    /**
     * Land-for-Land cases only. Monetary cases allocate no plots, so the
     * controller filters them out entirely rather than listing them with zeros.
     * Every farmer/government figure comes from SurveyCompCase::plotSplit().
     */
    $badgeClass = fn ($s) => match ($s) {
        'Active'    => 'active',
        'Completed' => 'completed',
        'Review'    => 'review',
        'Rejected'  => 'rejected',
        default     => 'pending',
    };
    $statusLabel = fn ($s) => $s === 'Review' ? 'Pending Review' : $s;
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a class="btn btn-primary" href="{{ route('survey-module.compensation.cases.register') }}">
        <i class="fas fa-plus"></i> New Land Case
    </a>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Land-for-Land Cases</div>
        <div class="kpi-value">{{ number_format($totals['cases']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Plots</div>
        <div class="kpi-value">{{ number_format($totals['plots']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Farmer Share</div>
        <div class="kpi-value">{{ number_format($totals['farmer']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Government Share</div>
        <div class="kpi-value">{{ number_format($totals['govt']) }}</div>
    </div>
</div>

<div class="comp-type-note">
    <i class="fas fa-info-circle"></i>
    Plots split 50:50 between farmer and government. When the total is odd the extra plot goes to
    <strong>Government</strong>. No cash is paid for economic trees under this scheme.
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search case or project…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
        <select name="project" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Land Projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>
                    {{ $p->project_code }} · {{ $p->name }}
                </option>
            @endforeach
        </select>
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (App\Http\Controllers\Survey\CaseController::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $statusLabel($s) }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'project']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.land') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Case Ref</th>
                    <th>Project</th>
                    <th>Location</th>
                    <th>Total Plots</th>
                    <th>Farmer</th>
                    <th>Govt</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cases as $c)
                    @php $s = $splits[$c->id] ?? ['total' => 0, 'farmer' => 0, 'govt' => 0]; @endphp
                    <tr>
                        <td><strong>{{ $c->case_ref }}</strong></td>
                        <td>{{ $c->project?->name ?? '—' }}</td>
                        <td>{{ $c->property_location ?: '—' }}</td>
                        <td>
                            {{ number_format($s['total']) }}
                            <div style="font-size:11px;color:var(--gray-500);">
                                {{ $c->plots_count ? 'from plot rows' : 'declared on case' }}
                            </div>
                        </td>
                        <td><strong style="color:var(--secondary-dark);">{{ number_format($s['farmer']) }}</strong></td>
                        <td><strong style="color:var(--info);">{{ number_format($s['govt']) }}</strong></td>
                        <td>
                            <span class="status-badge {{ $badgeClass($c->status) }}">
                                <span class="dot"></span>{{ $statusLabel($c->status) }}
                            </span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('survey-module.tools.plot-allocation', ['case' => $c->id]) }}" title="Plot rows"><i class="fas fa-th"></i></a>
                                <a href="{{ route('survey-module.compensation.cases.show', $c) }}" title="View case"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('survey-module.compensation.cases.edit', $c) }}" title="Edit case"><i class="fas fa-edit"></i></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-map-marked-alt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'project']))
                                No land-for-land cases match these filters.
                                <a href="{{ route('survey-module.compensation.land') }}">Clear them</a>.
                            @else
                                No land-for-land cases yet. They appear here once a case is registered under a
                                <a href="{{ route('survey-module.compensation.projects') }}">Land-for-Land project</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $cases->firstItem() ?? 0 }}–{{ $cases->lastItem() ?? 0 }}
            of {{ number_format($cases->total()) }} land-for-land cases · monetary cases are not listed here
        </span>
        <div class="pagination">{{ $cases->links() }}</div>
    </div>
</div>

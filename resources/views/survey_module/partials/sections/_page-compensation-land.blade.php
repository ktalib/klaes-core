@php
    /**
     * Land to Land cases only. Monetary Compensation cases allocate no plots, so the
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
@include('survey_module.partials._searchable_selects')

{{-- New Land Case hidden "for now" (user, 2026-10-28); set $showNewLandCase to true to bring it back. --}}
@php $showNewLandCase = false; @endphp
@if ($showNewLandCase)
    <div class="page-header">
        <div></div>
        <a class="btn btn-primary" href="{{ route('survey-module.compensation.cases.register') }}">
            <i class="fas fa-plus"></i> New Land Case
        </a>
    </div>
@endif

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Land to Land Cases</div>
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
        <select name="project" class="sv-searchable" data-width="300px" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
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
        @include('survey_module.partials._origin_filter')
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'project', 'origin']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.land') }}">Clear</a>
        @endif
    </div>
</form>

@include('survey_module.partials._table_ui')

<div class="table-wrapper">
    <div class="table-scroll">
        <table class="sv-dt">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Case Ref</th>
                    <th>Project</th>
                    <th>Location</th>
                    <th class="num">Total Plots</th>
                    <th class="num">Farmer</th>
                    <th class="num">Govt</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                    <th class="col-actions"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cases as $c)
                    @php $s = $splits[$c->id] ?? ['total' => 0, 'farmer' => 0, 'govt' => 0]; @endphp
                    <tr>
                        <td class="muted">{{ $cases->firstItem() + $loop->index }}</td>
                        <td class="code">{{ $c->case_ref }}</td>
                        <td class="wide strong">{{ $c->project?->display_name ?? '—' }}@if ($c->project)<span class="sub">{{ $c->project->project_code }}{{ $c->project->plan_no ? ' · ' . $c->project->plan_no : '' }}</span>@endif</td>
                        <td class="mid">{{ $c->property_location ?: '—' }}</td>
                        <td class="num">
                            {{ number_format($s['total']) }}
                            <span class="sub">{{ $c->plots_count ? 'from plot rows' : 'declared on case' }}</span>
                        </td>
                        <td class="num"><strong style="color:var(--secondary-dark);">{{ number_format($s['farmer']) }}</strong></td>
                        <td class="num"><strong style="color:var(--info);">{{ number_format($s['govt']) }}</strong></td>
                        <td>
                            <span class="status-badge {{ $badgeClass($c->status) }}">
                                <span class="dot"></span>{{ $statusLabel($c->status) }}
                            </span>
                        </td>
                        @include('survey_module.partials._created_cells', ['row' => $c])
                        <td class="col-actions">
                            @php $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50'; @endphp
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.tools.plot-allocation', ['case' => $c->id]) }}" class="{{ $svItem }}"><i data-lucide="grid-3x3" class="w-4 h-4 text-gray-500"></i> Plot Rows</a>
                                    <a href="{{ route('survey-module.compensation.cases.show', $c) }}" class="{{ $svItem }}"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View Case</a>
                                    <a href="{{ route('survey-module.compensation.cases.edit', $c) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit Case</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-map-marked-alt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'project', 'origin']))
                                No land-to-land cases match these filters.
                                <a href="{{ route('survey-module.compensation.land') }}">Clear them</a>.
                            @else
                                No land-to-land cases yet. They appear here once a case is registered under a
                                <a href="{{ route('survey-module.compensation.projects') }}">Land to Land project</a>.
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
            of {{ number_format($cases->total()) }} land-to-land cases · monetary cases are not listed here
        </span>
        <div class="pagination">{{ $cases->links() }}</div>
    </div>
</div>

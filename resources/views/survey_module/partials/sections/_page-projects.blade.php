@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a href="{{ route('survey-module.compensation.projects.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create Project
    </a>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Projects</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Monetary Schemes</div>
        <div class="kpi-value">{{ number_format($stats['monetary']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Land-for-Land Schemes</div>
        <div class="kpi-value">{{ number_format($stats['land']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Active</div>
        <div class="kpi-value">{{ number_format($stats['active']) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or code…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
        <select name="scheme" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Schemes</option>
            <option value="monetary" @selected(request('scheme')==='monetary')>Monetary</option>
            <option value="land" @selected(request('scheme')==='land')>Land-for-Land</option>
        </select>
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (['Active','Draft','Closed'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','scheme','status']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.projects') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Project ID</th>
                    <th>Project Name</th>
                    <th>Location</th>
                    <th>Scheme Type</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Cases</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $p)
                    <tr>
                        <td><strong>{{ $p->project_code }}</strong></td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->property_location ?: '—' }}</td>
                        <td>
                            @if ($p->isMonetary())
                                <span class="status-badge active"><span class="dot"></span>Monetary</span>
                            @else
                                <span class="status-badge completed"><span class="dot"></span>Land-for-Land</span>
                            @endif
                        </td>
                        <td>{{ $p->purpose ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $p->status === 'Active' ? 'active' : ($p->status === 'Closed' ? 'rejected' : 'pending') }}">
                                <span class="dot"></span>{{ $p->status }}
                            </span>
                        </td>
                        <td>{{ $p->cases_count }}</td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('survey-module.compensation.cases.register', ['project' => $p->id]) }}"
                                   title="Register a case under this project"><i class="fas fa-plus-circle"></i></a>
                                <a href="{{ route('survey-module.compensation.projects.edit', $p) }}"
                                   title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('survey-module.compensation.projects.destroy', $p) }}"
                                      style="display:inline" onsubmit="return confirm('Delete {{ $p->project_code }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No projects yet.
                            <a href="{{ route('survey-module.compensation.projects.create') }}">Create the first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }}
            of {{ number_format($projects->total()) }} projects · scheme type is set at project level
        </span>
        <div class="pagination">{{ $projects->links() }}</div>
    </div>
</div>

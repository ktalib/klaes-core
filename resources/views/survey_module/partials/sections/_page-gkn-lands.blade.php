@php use App\Http\Controllers\Survey\GknController; @endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a href="{{ route('survey-module.gkn.register') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Register GKN
    </a>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Parcels on Register</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Active</div>
        <div class="kpi-value">{{ number_format($stats['active']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Under Survey</div>
        <div class="kpi-value">{{ number_format($stats['survey']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Area (Ha)</div>
        <div class="kpi-value">{{ number_format($stats['area'], 2) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search GKN, title, officer or district…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:280px;" />
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (GknController::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="land_use" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Land Use</option>
            @foreach (GknController::LAND_USES as $lu)
                <option value="{{ $lu }}" @selected(request('land_use')===$lu)>{{ $lu }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','status','land_use']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.lands') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>GKN No.</th>
                    <th>Title / Description</th>
                    <th>Location</th>
                    <th>Area (Ha)</th>
                    <th>Land Use</th>
                    <th>Survey Officer</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $g)
                    <tr>
                        <td><strong>{{ $g->gkn_number }}</strong></td>
                        <td>{{ $g->title ?: '—' }}</td>
                        <td>{{ $g->property_location ?: '—' }}</td>
                        <td>{{ $g->area_ha !== null ? number_format((float) $g->area_ha, 2) : '—' }}</td>
                        <td>{{ $g->land_use ?: '—' }}</td>
                        <td>{{ $g->survey_officer ?: '—' }}</td>
                        <td>{{ optional($g->record_date)->format('Y-m-d') ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $g->status === 'Active' ? 'active' : ($g->status === 'Deeded' ? 'completed' : ($g->status === 'Closed' ? 'rejected' : 'pending')) }}">
                                <span class="dot"></span>{{ $g->status }}
                            </span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('survey-module.gkn.edit', $g) }}" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="{{ route('survey-module.gkn.tracking', ['q' => $g->gkn_number]) }}"
                                   title="File movements"><i class="fas fa-exchange-alt"></i></a>
                                <form method="POST" action="{{ route('survey-module.gkn.destroy', $g) }}"
                                      style="display:inline" onsubmit="return confirm('Delete {{ $g->gkn_number }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-map-marked-alt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No government land parcels on the register.
                            <a href="{{ route('survey-module.gkn.register') }}">Register the first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }}
            of {{ number_format($records->total()) }} parcels
        </span>
        <div class="pagination">{{ $records->links() }}</div>
    </div>
</div>

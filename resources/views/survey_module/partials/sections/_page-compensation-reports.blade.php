@include('survey_module.partials._flash')

<form method="GET" class="report-filters">
    <div class="form-group">
        <label>Scheme Type</label>
        <select name="scheme">
            <option value="">All</option>
            <option value="monetary" @selected(request('scheme')==='monetary')>Monetary</option>
            <option value="land" @selected(request('scheme')==='land')>Land-for-Land</option>
        </select>
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <option value="">All</option>
            @foreach (['Pending','Active','Review','Completed','Rejected'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label>From</label>
        <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}" />
    </div>
    <div class="form-group">
        <label>To</label>
        <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" />
    </div>
    <div class="form-group">
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit"><i class="fas fa-chart-bar"></i> Run Report</button>
    </div>
</form>

<div class="section-cards">
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-money-bill-wave"></i></div>
        <h4>Monetary Disbursed</h4>
        <div class="stat">&#8358;{{ number_format($totals['monetary_cash'], 2) }}</div>
        <p>Cash for trees &middot; {{ number_format($byScheme['monetary'] ?? 0) }} case(s)</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-map"></i></div>
        <h4>Plots Allocated</h4>
        <div class="stat">{{ number_format($totals['plots']) }}</div>
        <p>{{ number_format($totals['plots_farmer']) }} farmer :
           {{ number_format($totals['plots_govt']) }} government
           &middot; {{ number_format($byScheme['land'] ?? 0) }} case(s)</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-users"></i></div>
        <h4>Beneficiaries</h4>
        <div class="stat">{{ number_format($totals['beneficiaries']) }}</div>
        <p>Across {{ number_format($totals['cases']) }} case(s)</p>
    </div>
</div>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>Case</th><th>Project</th><th>Scheme</th><th>Location</th>
                    <th>Area (Ha)</th><th>Status</th><th>Registered</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $c)
                    <tr>
                        <td><strong>{{ $c->case_ref }}</strong></td>
                        <td>{{ $c->project->name ?? '—' }}</td>
                        <td>
                            <span class="status-badge {{ $c->isMonetary() ? 'active' : 'completed' }}">
                                <span class="dot"></span>{{ $c->isMonetary() ? 'Monetary' : 'Land-for-Land' }}
                            </span>
                        </td>
                        <td>{{ $c->property_location ?: '—' }}</td>
                        <td>{{ $c->area_ha !== null ? number_format((float) $c->area_ha, 2) : '—' }}</td>
                        <td>{{ $c->status }}</td>
                        <td>{{ optional($c->created_at)->format('Y-m-d') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--gray-500);">
                        No cases match these filters.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>{{ number_format($rows->total()) }} case(s) &middot;
              {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>

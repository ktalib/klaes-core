@include('survey_module.partials._flash')

<form method="GET" class="report-filters">
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <option value="">All</option>
            @foreach (['Pending','Review','Approved','Rejected'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label>Land Use</label>
        <select name="land_use">
            <option value="">All</option>
            @foreach (['Residential','Commercial','Agricultural','Institutional','Industrial','Mixed'] as $u)
                <option value="{{ $u }}" @selected(request('land_use')===$u)>{{ $u }}</option>
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
        <div class="section-icon"><i class="fas fa-landmark"></i></div>
        <h4>Parcels</h4>
        <div class="stat">{{ number_format($totals['records']) }}</div>
        <p>In the selected window</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-vector-square"></i></div>
        <h4>Total Area</h4>
        <div class="stat">{{ number_format($totals['area'], 2) }}</div>
        <p>Hectares</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-map-pin"></i></div>
        <h4>With Coordinates</h4>
        <div class="stat">{{ number_format($totals['mapped']) }}</div>
        <p>of {{ number_format($totals['records']) }} parcels</p>
    </div>
</div>

@if ($byLandUse->isNotEmpty())
    <div class="dash-card" style="margin-bottom:20px;">
        <div class="card-header"><h3>By Land Use</h3></div>
        <table>
            <thead><tr><th>Land Use</th><th>Parcels</th><th>Area (Ha)</th></tr></thead>
            <tbody>
                @foreach ($byLandUse as $row)
                    <tr>
                        <td>{{ $row->land_use }}</td>
                        <td>{{ number_format($row->total) }}</td>
                        <td>{{ number_format((float) $row->area, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>GKN No.</th><th>Title</th><th>Location</th><th>Land Use</th>
                    <th>Area (Ha)</th><th>Status</th><th>Registered</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $g)
                    <tr>
                        <td><strong>{{ $g->gkn_number }}</strong></td>
                        <td>{{ $g->title ?: '—' }}</td>
                        <td>{{ $g->property_location ?: '—' }}</td>
                        <td>{{ $g->land_use ?: '—' }}</td>
                        <td>{{ $g->area_ha !== null ? number_format((float) $g->area_ha, 2) : '—' }}</td>
                        <td>{{ $g->status }}</td>
                        <td>{{ optional($g->created_at)->format('Y-m-d') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--gray-500);">
                        No GKN records match these filters.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>{{ number_format($rows->total()) }} parcel(s) &middot;
              {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>

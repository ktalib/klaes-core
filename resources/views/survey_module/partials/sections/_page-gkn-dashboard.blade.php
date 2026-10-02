@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a class="btn btn-primary" href="{{ route('survey-module.gkn.register') }}">
        <i class="fas fa-plus"></i> Register GKN
    </a>
</div>

<div class="kpi-grid">
    @foreach ([
        ['Total Indexed',   $kpis['total']],
        ['Pending Survey',  $kpis['pending']],
        ['Under Review',    $kpis['review']],
        ['Files in Transit',$kpis['in_transit']],
    ] as [$label, $value])
        <div class="kpi-card">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ number_format($value) }}</div>
        </div>
    @endforeach
</div>

<div class="dash-grid">
    <div class="dash-card">
        <div class="card-header">
            <h3>Recent GKN Activity</h3>
            <a href="{{ route('survey-module.gkn.lands') }}">View All &rarr;</a>
        </div>
        <div class="activity-feed">
            @forelse ($recent as $g)
                <div class="activity-item">
                    <div class="activity-icon"><i class="fas fa-landmark"></i></div>
                    <div class="activity-content">
                        <div class="text"><strong>{{ $g->gkn_number }}</strong> — {{ $g->title ?: 'Untitled parcel' }}</div>
                        <div class="time">{{ $g->property_location ?: '—' }}
                            @if ($g->created_at) &middot; {{ $g->created_at->diffForHumans() }} @endif
                        </div>
                    </div>
                </div>
            @empty
                <div style="text-align:center;padding:28px;color:var(--gray-500);">
                    No government land records yet.
                    <a href="{{ route('survey-module.gkn.register') }}">Register the first one</a>.
                </div>
            @endforelse
        </div>
    </div>

    <div class="dash-card">
        <div class="card-header"><h3>By Land Use</h3></div>
        @php $maxUse = max(1, (int) ($byLandUse->max('total') ?? 1)); @endphp
        @forelse ($byLandUse as $row)
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span>{{ $row->land_use }}</span>
                    <strong>{{ number_format($row->total) }}</strong>
                </div>
                <div style="height:8px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                    <div style="height:100%;width:{{ round(($row->total / $maxUse) * 100) }}%;background:var(--primary);"></div>
                </div>
            </div>
        @empty
            <div style="text-align:center;padding:28px;color:var(--gray-500);">Nothing to chart yet.</div>
        @endforelse
    </div>
</div>

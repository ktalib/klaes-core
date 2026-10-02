@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a class="btn btn-primary" href="{{ route('survey-module.compensation.cases.register') }}">
        <i class="fas fa-plus"></i> New Compensation
    </a>
</div>

<div class="kpi-grid">
    @foreach ([
        ['fa-folder-open',    'Total Cases',    $kpis['total']],
        ['fa-hourglass-half', 'Pending Review', $kpis['pending']],
        ['fa-check-circle',   'Approved',       $kpis['approved']],
        ['fa-file-signature', 'OPs Generated',  $kpis['ops']],
    ] as [$icon, $label, $value])
        <div class="kpi-card">
            <i class="fas {{ $icon }} kpi-icon"></i>
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ number_format($value) }}</div>
        </div>
    @endforeach
</div>

@php $peak = max(1, collect($chart)->max('count')); @endphp

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div class="dash-card">
        <div class="card-header"><h3>Cases Registered — last 6 months</h3></div>
        @if (collect($chart)->sum('count') === 0)
            <div style="text-align:center;padding:40px;color:var(--gray-500);">No cases registered yet.</div>
        @else
            <div style="height:200px;display:flex;align-items:flex-end;gap:12px;padding:10px 0;">
                @foreach ($chart as $m)
                    <div style="flex:1;text-align:center;">
                        <div title="{{ $m['count'] }} case(s)"
                             style="height:{{ max(4, (int) round(($m['count'] / $peak) * 170)) }}px;background:var(--primary);border-radius:4px 4px 0 0;width:100%;max-width:40px;margin:0 auto;"></div>
                        <span style="font-size:11px;color:var(--gray-500);">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="dash-card">
        <div class="card-header"><h3>Compensation Summary</h3></div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--gray-50);border-radius:var(--radius-sm);">
                <span>Monetary Schemes (Cash for Trees)</span>
                <span style="font-weight:700;color:var(--primary);">&#8358;{{ number_format($summary['monetary_cash'], 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--gray-50);border-radius:var(--radius-sm);">
                <span>Land-for-Land Schemes (Plots)</span>
                <span style="font-weight:700;color:var(--primary);">{{ number_format($summary['land_plots']) }} plots</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--gray-50);border-radius:var(--radius-sm);">
                <span>Beneficiaries Served</span>
                <span style="font-weight:700;color:var(--primary);">{{ number_format($summary['beneficiaries']) }}</span>
            </div>
            <div style="padding:10px 14px;background:var(--gray-50);border-radius:var(--radius-sm);">
                <span style="font-size:12px;color:var(--gray-500);">
                    Scheme type is set at project level — cases inherit it and are never mixed,
                    so cash and plot totals come from different cases.
                </span>
            </div>
        </div>
    </div>
</div>

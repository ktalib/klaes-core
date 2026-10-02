{{--
    Reports / Analytics (rebuild plan, Phase 9 card set).

    Four headline cards and the brief's metrics table for a date range, each
    metric shown for the period and all-time so a quiet period is not mistaken
    for an empty module. Figures come from DashboardMetrics::analytics(); the
    definition column says exactly what each number counts.
--}}
@include('cadastral_module.partials._flash')

<style>
    .cadastral-proto .cad-an-def { font-size: 12px; color: var(--gray-500); }
    .cadastral-proto .cad-an-num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
    .cadastral-proto .cad-an-num.is-zero { color: var(--gray-400); font-weight: 400; }
    .cadastral-proto .cad-an-range { font-size: 12.5px; color: var(--gray-500); margin: -12px 0 16px; }
    .cadastral-proto .cad-an-presets { display: flex; gap: 6px; flex-wrap: wrap; }
</style>

@php
    $c = $a['cards'];

    $cards = [
        ['label' => 'Reports Total',      'value' => number_format($c['reports']),    'icon' => 'fa-file-lines',
         'hint'  => 'Opened in the period'],
        ['label' => 'Dispatched',         'value' => number_format($c['dispatched']), 'icon' => 'fa-paper-plane',
         'hint'  => 'Dispatched in the period'],
        ['label' => 'Duplicates Blocked', 'value' => number_format($c['duplicates']), 'icon' => 'fa-ban',
         'hint'  => 'Flagged at reception in the period'],
        ['label' => 'Bills Raised',       'value' => number_format($c['bills']),      'icon' => 'fa-file-invoice',
         'hint'  => '₦' . number_format($c['billsValue'], 2) . ' issued in the period'],
    ];

    $presets = [
        'Year to date'  => [now()->startOfYear(), now()],
        'Last 30 days'  => [now()->subDays(29), now()],
        'This month'    => [now()->startOfMonth(), now()],
        'Last year'     => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
    ];
@endphp

<form method="GET" action="{{ route('cadastral-module.analytics') }}" class="report-filters">
    <div class="form-group">
        <label for="an-from">From</label>
        <input type="date" id="an-from" name="from" value="{{ $from->toDateString() }}">
    </div>
    <div class="form-group">
        <label for="an-to">To</label>
        <input type="date" id="an-to" name="to" value="{{ $to->toDateString() }}">
    </div>
    <div class="form-group" style="flex:2;">
        <label>Quick range</label>
        <div class="cad-an-presets">
            @foreach ($presets as $name => [$pFrom, $pTo])
                <a class="btn btn-outline btn-xs"
                   href="{{ route('cadastral-module.analytics', ['from' => $pFrom->toDateString(), 'to' => $pTo->toDateString()]) }}">{{ $name }}</a>
            @endforeach
        </div>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
        <a href="{{ route('cadastral-module.analytics') }}" class="btn btn-outline btn-sm"><i class="fas fa-rotate"></i> Reset</a>
    </div>
</form>

<p class="cad-an-range">
    Showing {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}.
</p>

<div class="kpi-grid">
    @foreach ($cards as $card)
        <div class="kpi-card">
            <i class="fas {{ $card['icon'] }} kpi-icon" style="color:#fda4af;opacity:.8;"></i>
            <div class="kpi-label">{{ $card['label'] }}</div>
            <div class="kpi-value">{{ $card['value'] }}</div>
            <div style="font-size:12px;color:var(--gray-500);margin-top:4px;">{{ $card['hint'] }}</div>
        </div>
    @endforeach
</div>

<div class="table-wrapper">
    <div class="table-toolbar">
        <strong style="font-size:14px;">Metrics</strong>
        <a href="{{ route('cadastral-module.dashboard') }}" class="btn btn-outline btn-xs">
            <i class="fas fa-gauge"></i> Dashboard
        </a>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Metric</th>
                    <th style="text-align:right;">In period</th>
                    <th style="text-align:right;">All time</th>
                    <th>What it counts</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($a['rows'] as $row)
                    <tr>
                        <td><strong>{{ $row['label'] }}</strong></td>
                        <td class="cad-an-num {{ $row['period'] === 0 ? 'is-zero' : '' }}">{{ number_format($row['period']) }}</td>
                        <td class="cad-an-num {{ $row['total'] === 0 ? 'is-zero' : '' }}">{{ number_format($row['total']) }}</td>
                        <td class="cad-an-def">{{ $row['definition'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (collect($a['rows'])->sum('total') === 0)
        <div class="table-footer">
            <span class="cad-an-def">
                No cadastral records exist yet. Figures fill in as files are logged at
                <a href="{{ route('cadastral-module.registry.receipts') }}">the intake queue</a>.
            </span>
        </div>
    @endif
</div>

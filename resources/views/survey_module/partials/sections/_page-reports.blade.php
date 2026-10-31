@php
    // Survey Reports (user, 2026-10-25): graphs, charts and tables. Chart.js 3.9 is loaded by layouts.app.
    $k = $kpis;
    $nf = fn ($v) => number_format((float) $v);
    $naira = fn ($v) => '₦' . number_format((float) $v, 2);
    $sumOf = fn (array $a) => array_sum($a);
    $hasIssuance = $sumOf($issuance['series']['beacons']) + $sumOf($issuance['series']['control_points']) > 0;
    $hasRegister = $sumOf($register['series']['verification']) + $sumOf($register['series']['examination']) > 0;
    $hasIts = array_sum($its['values']) > 0;
@endphp

@include('survey_module.partials._flash')

{{-- Chart roles (docs: dataviz reference palette, validated for these two slots on this surface). --}}
<style>
    .sv-viz { --surface-1:#fcfcfb; --ink-1:#0b0b0b; --ink-2:#52514e; --muted:#898781; --grid:#e1e0d9; --base:#c3c2b7;
              --series-1:#2a78d6; --series-2:#eb6834; }
    .sv-viz .viz-card { background:var(--surface-1); border:1px solid rgba(11,11,11,.10); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .sv-viz .viz-card h3 { margin:0; font-size:15px; font-weight:700; color:var(--ink-1); }
    .sv-viz .viz-card .sub { margin:2px 0 12px; font-size:12.5px; color:var(--ink-2); }
    .sv-viz .viz-box { position:relative; height:280px; }
    .sv-viz .viz-empty { height:120px; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:13px;
                         border:1px dashed var(--base); border-radius:8px; }
    .sv-viz details.viz-table { margin-top:10px; font-size:13px; }
    .sv-viz details.viz-table summary { cursor:pointer; color:var(--ink-2); }
    .sv-viz details.viz-table table { margin-top:8px; width:100%; border-collapse:collapse; font-variant-numeric:tabular-nums; }
    .sv-viz details.viz-table th, .sv-viz details.viz-table td { padding:5px 8px; border-bottom:1px solid var(--grid); text-align:right; }
    .sv-viz details.viz-table th:first-child, .sv-viz details.viz-table td:first-child { text-align:left; }
    .sv-viz .viz-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(420px, 1fr)); gap:18px; }
    .sv-viz .viz-grid .viz-card { margin-bottom:0; }
    .sv-viz .kpi-section { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-2); margin:6px 0 8px; }
    @media (max-width: 560px) { .sv-viz .viz-grid { grid-template-columns:1fr; } }
</style>

<form method="GET" class="report-filters">
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

<p class="helper-text" style="margin-bottom:16px;">
    Covering {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}. Counts marked "open" or "pending" are as of today.
</p>

<div class="sv-viz">
    <div class="kpi-section">Compensation</div>
    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-label">CO Files</div><div class="kpi-value">{{ $nf($k['compensation']['co_files']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Projects</div><div class="kpi-value">{{ $nf($k['compensation']['projects']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Cases registered</div><div class="kpi-value">{{ $nf($k['compensation']['cases']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Trees valued</div><div class="kpi-value">{{ $naira($k['compensation']['cash']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Complaints open</div><div class="kpi-value">{{ $nf($k['compensation']['complaints_open']) }}</div></div>
    </div>

    <div class="kpi-section">GKN</div>
    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-label">Application files</div><div class="kpi-value">{{ $nf($k['gkn']['applications']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">I to S issued</div><div class="kpi-value">{{ $nf($k['gkn']['its_issued']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Surveyors Reports</div><div class="kpi-value">{{ $nf($k['gkn']['reports']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Beacons on coordinate lists</div><div class="kpi-value">{{ $nf($k['gkn']['coordinates']) }}</div></div>
    </div>

    <div class="kpi-section">Examination / Verification</div>
    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-label">Files received</div><div class="kpi-value">{{ $nf($k['examination']['received']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Files open</div><div class="kpi-value">{{ $nf($k['examination']['open']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Beacons issued</div><div class="kpi-value">{{ $nf($k['examination']['beacons']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Control points issued</div><div class="kpi-value">{{ $nf($k['examination']['control_points']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Disputes open</div><div class="kpi-value">{{ $nf($k['examination']['disputes_open']) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Old surveys pending</div><div class="kpi-value">{{ $nf($k['examination']['old_pending']) }}</div></div>
    </div>

    {{-- 1. Trend over time, two series of the same unit: one axis. --}}
    <div class="viz-card">
        <h3>Beacons and control points issued</h3>
        <p class="sub">Numbers issued per month, by issue date</p>
        @if ($hasIssuance)
            <div class="viz-box"><canvas id="svIssuance" role="img" aria-label="Beacons and control points issued per month"></canvas></div>
        @else
            <div class="viz-empty">Nothing issued in this range.</div>
        @endif
        <details class="viz-table"><summary>Show table</summary>
            <table><thead><tr><th>Month</th><th>Beacons</th><th>Control points</th></tr></thead><tbody>
                @foreach ($issuance['labels'] as $i => $m)
                    <tr><td>{{ $m }}</td><td>{{ $nf($issuance['series']['beacons'][$i]) }}</td><td>{{ $nf($issuance['series']['control_points'][$i]) }}</td></tr>
                @endforeach
            </tbody></table>
        </details>
    </div>

    <div class="viz-grid">
        {{-- 2. Part-to-whole per month: stacked columns. --}}
        <div class="viz-card">
            <h3>Register File: files received</h3>
            <p class="sub">Per month, verification and examination</p>
            @if ($hasRegister)
                <div class="viz-box"><canvas id="svRegister" role="img" aria-label="Register File lines received per month"></canvas></div>
            @else
                <div class="viz-empty">No files received in this range.</div>
            @endif
            <details class="viz-table"><summary>Show table</summary>
                <table><thead><tr><th>Month</th><th>Verification</th><th>Examination</th></tr></thead><tbody>
                    @foreach ($register['labels'] as $i => $m)
                        <tr><td>{{ $m }}</td><td>{{ $nf($register['series']['verification'][$i]) }}</td><td>{{ $nf($register['series']['examination'][$i]) }}</td></tr>
                    @endforeach
                </tbody></table>
            </details>
        </div>

        {{-- 5. Magnitude across ordered statuses: one hue. --}}
        <div class="viz-card">
            <h3>Instruction to Survey by status</h3>
            <p class="sub">Survey's own jobs · {{ $nf($its['cadastral']) }} more issued by Cadastral</p>
            @if ($hasIts)
                <div class="viz-box"><canvas id="svIts" role="img" aria-label="Survey instructions by status"></canvas></div>
            @else
                <div class="viz-empty">No survey jobs yet.</div>
            @endif
            <details class="viz-table"><summary>Show table</summary>
                <table><thead><tr><th>Status</th><th>Jobs</th></tr></thead><tbody>
                    @foreach ($its['labels'] as $i => $s)<tr><td>{{ $s }}</td><td>{{ $nf($its['values'][$i]) }}</td></tr>@endforeach
                </tbody></table>
            </details>
        </div>

        {{-- 3. Part-to-whole per project: stacked horizontal bars. --}}
        <div class="viz-card">
            <h3>Land to Land: plots by project</h3>
            <p class="sub">Owner and Government plots, split 50:50 per beneficiary (odd plot to Government)</p>
            @if ($plots)
                <div class="viz-box" style="height:{{ max(160, 56 * count($plots) + 70) }}px;"><canvas id="svPlots" role="img" aria-label="Owner and Government plots by project"></canvas></div>
            @else
                <div class="viz-empty">No Land to Land cases with plots yet.</div>
            @endif
            <details class="viz-table"><summary>Show table</summary>
                <table><thead><tr><th>Project</th><th>Owner</th><th>Government</th><th>Total</th></tr></thead><tbody>
                    @forelse ($plots as $p)<tr><td>{{ $p['label'] }} · {{ $p['name'] }}</td><td>{{ $nf($p['owner']) }}</td><td>{{ $nf($p['govt']) }}</td><td>{{ $nf($p['owner'] + $p['govt']) }}</td></tr>
                    @empty<tr><td colspan="4">—</td></tr>@endforelse
                </tbody></table>
            </details>
        </div>

        {{-- 4. Magnitude per project, one series: one hue, no legend. --}}
        <div class="viz-card">
            <h3>Monetary Compensation: valuation by project</h3>
            <p class="sub">Trees valued on the project's cases (rejected cases left out)</p>
            @if ($cash)
                <div class="viz-box" style="height:{{ max(160, 44 * count($cash) + 70) }}px;"><canvas id="svCash" role="img" aria-label="Cash valued by project"></canvas></div>
            @else
                <div class="viz-empty">No trees valued yet.</div>
            @endif
            <details class="viz-table"><summary>Show table</summary>
                <table><thead><tr><th>Project</th><th>Amount</th></tr></thead><tbody>
                    @forelse ($cash as $c)<tr><td>{{ $c['label'] }} · {{ $c['name'] }}</td><td>{{ $naira($c['amount']) }}</td></tr>
                    @empty<tr><td colspan="2">—</td></tr>@endforelse
                </tbody></table>
            </details>
        </div>
    </div>

    <div class="comp-type-note" style="margin-top:20px;">
        <i class="fas fa-info-circle"></i>
        Detailed breakdowns: the <a href="{{ route('survey-module.compensation.reports') }}">Compensation</a> and
        <a href="{{ route('survey-module.gkn.reports') }}">GKN</a> reports, and the
        <a href="{{ route('survey-module.compensation.calculator') }}">Compensation Calculator</a> per project.
    </div>
</div>

@push('scripts')
<script>
(function () {
    if (!window.Chart) return;
    var css = getComputedStyle(document.querySelector('.sv-viz'));
    var v = function (n) { return css.getPropertyValue(n).trim(); };
    var C = { s1: v('--series-1'), s2: v('--series-2'), surface: v('--surface-1'), ink2: v('--ink-2'), muted: v('--muted'), grid: v('--grid'), base: v('--base') };
    var nf = new Intl.NumberFormat('en-NG');
    var naira = function (n) { return '₦' + new Intl.NumberFormat('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n); };

    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = C.muted;

    // Recessive axes: hairline grid, muted ticks. Value axes get clean thousands; category
    // axes keep their labels (Chart.js passes them an index, not a value).
    function axis(extra) {
        return Object.assign({ grid: { color: C.grid, lineWidth: 1, drawBorder: false, borderDash: [] }, ticks: { color: C.muted } }, extra || {});
    }
    function valueAxis(extra) {
        return axis(Object.assign({ beginAtZero: true, ticks: { color: C.muted, precision: 0, callback: function (val) { return nf.format(val); } } }, extra || {}));
    }
    var legend = { position: 'top', align: 'start', labels: { color: C.ink2, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10 } };
    // Bars: thin, 4px rounded data end, square at the baseline, 2px surface gap between touching segments.
    function bar(label, data, color, extra) {
        return Object.assign({ label: label, data: data, backgroundColor: color, hoverBackgroundColor: color, maxBarThickness: 24,
                               borderRadius: 4, borderSkipped: 'start', borderColor: C.surface, borderWidth: 0 }, extra || {});
    }
    function draw(id, cfg) { var el = document.getElementById(id); if (el) new Chart(el, cfg); }

    var issuance = @json($issuance);
    draw('svIssuance', {
        type: 'line',
        data: { labels: issuance.labels, datasets: [
            { label: 'Beacons', data: issuance.series.beacons, borderColor: C.s1, backgroundColor: C.s1, borderWidth: 2, tension: 0,
              pointRadius: 4, pointHoverRadius: 6, pointBorderColor: C.surface, pointBorderWidth: 2, borderJoinStyle: 'round', borderCapStyle: 'round' },
            { label: 'Control points', data: issuance.series.control_points, borderColor: C.s2, backgroundColor: C.s2, borderWidth: 2, tension: 0,
              pointRadius: 4, pointHoverRadius: 6, pointBorderColor: C.surface, pointBorderWidth: 2, borderJoinStyle: 'round', borderCapStyle: 'round' }
        ] },
        options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                   plugins: { legend: legend, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + nf.format(c.parsed.y); } } } },
                   scales: { x: axis({ grid: { display: false }, ticks: { color: C.muted, maxRotation: 0, autoSkip: true, autoSkipPadding: 12 } }), y: valueAxis() } }
    });

    var reg = @json($register);
    draw('svRegister', {
        type: 'bar',
        data: { labels: reg.labels, datasets: [
            bar('Verification', reg.series.verification, C.s1, { borderSkipped: false, borderWidth: { top: 2 }, borderRadius: 0 }),
            bar('Examination', reg.series.examination, C.s2, { borderWidth: { bottom: 2 } })
        ] },
        options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                   plugins: { legend: legend }, scales: { x: axis({ stacked: true, grid: { display: false }, ticks: { color: C.muted, maxRotation: 0, autoSkip: true, autoSkipPadding: 12 } }), y: valueAxis({ stacked: true }) } }
    });

    var its = @json($its);
    draw('svIts', {
        type: 'bar',
        data: { labels: its.labels, datasets: [ bar('Jobs', its.values, C.s1) ] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
                   scales: { x: axis({ grid: { display: false } }), y: valueAxis() } }
    });

    var plots = @json($plots);
    draw('svPlots', {
        type: 'bar',
        data: { labels: plots.map(function (p) { return p.label; }), datasets: [
            bar('Owner', plots.map(function (p) { return p.owner; }), C.s1, { borderSkipped: false, borderWidth: { right: 2 }, borderRadius: 0 }),
            bar('Government', plots.map(function (p) { return p.govt; }), C.s2, { borderWidth: { left: 2 } })
        ] },
        options: { indexAxis: 'y', maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                   plugins: { legend: legend, tooltip: { callbacks: { title: function (it) { var p = plots[it[0].dataIndex]; return p.label + ' · ' + p.name; } } } },
                   scales: { x: valueAxis({ stacked: true }), y: axis({ stacked: true, grid: { display: false } }) } }
    });

    var cash = @json($cash);
    draw('svCash', {
        type: 'bar',
        data: { labels: cash.map(function (c) { return c.label; }), datasets: [ bar('Valued', cash.map(function (c) { return c.amount; }), C.s1) ] },
        options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false },
                   tooltip: { callbacks: { title: function (it) { var c = cash[it[0].dataIndex]; return c.label + ' · ' + c.name; }, label: function (c) { return naira(c.parsed.x); } } } },
                   scales: { x: valueAxis({ ticks: { color: C.muted, maxRotation: 0, maxTicksLimit: 5, callback: function (v) { return '₦' + nf.format(v); } } }), y: axis({ grid: { display: false } }) } }
    });
})();
</script>
@endpush

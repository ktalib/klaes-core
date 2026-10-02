{{--
    The Cadastral Module dashboard (rebuild plan, Phase 1).

    One page for the whole module, replacing the four per-unit dashboards in the
    sidebar: six tiles, the latest open report as a vertical stage tracker, a
    merged activity feed, and quick links. Every figure is from
    DashboardMetrics::overview(). The tables are empty until intake starts, so
    each panel carries its own empty state.

    Stage colours follow the brief: done green, active amber, waiting slate.
    Returned is red and skipped is muted so neither is mistaken for progress.
--}}
@include('cadastral_module.partials._flash')

<style>
    .cadastral-proto .cad-kpi-link { display: block; color: inherit; text-decoration: none; }
    .cadastral-proto .cad-kpi-link .kpi-icon { color: #fda4af; opacity: .8; }
    .cadastral-proto .cad-kpi-hint { font-size: 12px; color: var(--gray-500); margin-top: 4px; }

    .cadastral-proto .cad-tracker { list-style: none; margin: 0; padding: 0; }
    .cadastral-proto .cad-tracker li { position: relative; display: flex; gap: 12px; padding: 0 0 18px; }
    .cadastral-proto .cad-tracker li:last-child { padding-bottom: 0; }
    .cadastral-proto .cad-tracker li:not(:last-child)::before {
        content: ''; position: absolute; left: 12px; top: 26px; bottom: 2px; width: 2px; background: #e2e8f0;
    }
    .cadastral-proto .cad-tracker li.is-done:not(:last-child)::before { background: #86efac; }
    .cadastral-proto .cad-tracker .dot {
        flex: 0 0 26px; height: 26px; border-radius: 50%; display: grid; place-items: center;
        font-size: 11px; font-weight: 700; background: #f1f5f9; color: #64748b; border: 2px solid #cbd5e1;
    }
    .cadastral-proto .cad-tracker .is-done .dot     { background: #dcfce7; color: #166534; border-color: #22c55e; }
    .cadastral-proto .cad-tracker .is-active .dot   { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
    .cadastral-proto .cad-tracker .is-returned .dot { background: #fee2e2; color: #991b1b; border-color: #ef4444; }
    .cadastral-proto .cad-tracker .is-skipped .dot  { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; }
    .cadastral-proto .cad-tracker .name { font-size: 13.5px; font-weight: 600; color: var(--gray-800); }
    .cadastral-proto .cad-tracker .is-waiting .name,
    .cadastral-proto .cad-tracker .is-skipped .name { color: #64748b; font-weight: 500; }
    .cadastral-proto .cad-tracker .meta { font-size: 12px; color: var(--gray-500); }
    .cadastral-proto .cad-tracker .state { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .cadastral-proto .cad-tracker .is-done .state     { color: #15803d; }
    .cadastral-proto .cad-tracker .is-active .state   { color: #b45309; }
    .cadastral-proto .cad-tracker .is-returned .state { color: #b91c1c; }
    .cadastral-proto .cad-tracker .is-waiting .state,
    .cadastral-proto .cad-tracker .is-skipped .state  { color: #94a3b8; }

    .cadastral-proto .cad-progress { height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin: 6px 0 16px; }
    .cadastral-proto .cad-progress span { display: block; height: 100%; background: #22c55e; }

    .cadastral-proto .cad-dash-empty {
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
        min-height: 150px; padding: 16px; text-align: center; font-size: 13px; color: var(--gray-500);
        border: 1px dashed var(--gray-300); border-radius: 8px;
    }
    .cadastral-proto .cad-dash-empty i { font-size: 22px; opacity: .45; }

    .cadastral-proto .cad-quick { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    .cadastral-proto .cad-quick a {
        display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 8px;
        border: 1px solid var(--gray-200); background: #fff; color: var(--gray-800); text-decoration: none;
        font-size: 13.5px; font-weight: 600;
    }
    .cadastral-proto .cad-quick a:hover { border-color: #fda4af; background: #fff1f2; }
    .cadastral-proto .cad-quick a i { color: #e11d48; width: 18px; text-align: center; }
    .cadastral-proto .cad-quick small { display: block; font-weight: 400; color: var(--gray-500); font-size: 11.5px; }

    .cadastral-proto .activity-item .activity-icon.is-duplicate { background: #fee2e2; color: #b91c1c; }
    .cadastral-proto .activity-item .activity-icon.is-card      { background: #dcfce7; color: #166534; }
    .cadastral-proto .activity-item .activity-icon.is-job       { background: #fef3c7; color: #92400e; }
    .cadastral-proto .activity-item .activity-content a { color: inherit; text-decoration: none; }
    .cadastral-proto .activity-item .activity-content a:hover strong { text-decoration: underline; }
</style>

@php
    $t = $m['tiles'];

    $tiles = [
        ['label' => 'Intake Queue',  'value' => number_format($t['intake']),  'icon' => 'fa-inbox',
         'hint'  => 'Logged, awaiting registration', 'href' => route('cadastral-module.registry.receipts')],
        ['label' => 'Open Reports',  'value' => number_format($t['reports']), 'icon' => 'fa-file-lines',
         'hint'  => 'Not yet dispatched or rejected', 'href' => route('cadastral-module.reports.index')],
        ['label' => 'Survey Jobs',   'value' => number_format($t['jobs']),    'icon' => 'fa-helmet-safety',
         'hint'  => 'Issued, in field or submitted', 'href' => route('cadastral-module.survey-jobs.index')],
        ['label' => 'Index Cards',   'value' => number_format($t['cards']),   'icon' => 'fa-id-card',
         'hint'  => 'Commissioned', 'href' => route('cadastral-module.index-cards.index')],
        ['label' => 'Fees YTD',      'value' => '₦' . number_format($t['feesYtd'], 2), 'icon' => 'fa-naira-sign',
         'hint'  => 'Bills issued since 1 January', 'href' => route('cadastral-module.plan-description.index', ['view' => 'fees'])],
        ['label' => 'Duplicates',    'value' => number_format($t['duplicates']), 'icon' => 'fa-clone',
         'hint'  => 'Flagged on arrival', 'href' => route('cadastral-module.registry.duplicates')],
    ];

    $activityKinds = [
        'receipt'   => ['icon' => 'fa-inbox',         'verb' => 'File received'],
        'card'      => ['icon' => 'fa-id-card',       'verb' => 'Index card commissioned'],
        'job'       => ['icon' => 'fa-helmet-safety', 'verb' => 'Survey job issued'],
        'duplicate' => ['icon' => 'fa-clone',         'verb' => 'Duplicate flagged'],
    ];

    $activityHref = function (array $e) {
        return match ($e['kind']) {
            'receipt'   => route('cadastral-module.registry.receipts.edit', $e['id']),
            'card'      => route('cadastral-module.index-cards.show', $e['id']),
            'job'       => route('cadastral-module.survey-jobs.edit', $e['id']),
            'duplicate' => route('cadastral-module.registry.duplicates', ['file_number' => $e['title']]),
            default     => null,
        };
    };

    $latest = $m['latestReport'];
@endphp

@if ($jobFormatUnconfirmed)
    <div class="caveat">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>Survey job numbers are being issued in an unconfirmed format.</strong>
            <code>{{ app(\App\Services\Cadastral\CadastralSettings::class)->jobNumberFormat()['format'] }}</code> is a KLAES-local
            placeholder — nobody here knows the pattern SURCON mandates. Confirm it with the
            Surveyor-General's office before issuing in bulk.
        </div>
    </div>
@endif

<div class="kpi-grid">
    @foreach ($tiles as $tile)
        <a class="kpi-card cad-kpi-link" href="{{ $tile['href'] }}">
            <i class="fas {{ $tile['icon'] }} kpi-icon"></i>
            <div class="kpi-label">{{ $tile['label'] }}</div>
            <div class="kpi-value">{{ $tile['value'] }}</div>
            <div class="cad-kpi-hint">{{ $tile['hint'] }}</div>
        </a>
    @endforeach
</div>

<div class="dash-grid">
    {{-- Latest open report --}}
    <div class="dash-card">
        <div class="card-header">
            <h3>Latest Open Report</h3>
            <a href="{{ route('cadastral-module.reports.index') }}">All reports</a>
        </div>

        @if ($latest)
            @php $report = $latest['report']; @endphp
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <a href="{{ route('cadastral-module.reports.show', $report) }}" style="font-weight:700;color:var(--gray-900);text-decoration:none;">
                        {{ $report->report_ref ?: 'Report #' . $report->id }}
                    </a>
                    <div style="font-size:12.5px;color:var(--gray-500);">
                        {{ $report->type_label }} · {{ $report->file_number ?: 'no file number' }}
                        @if ($report->file_title) · {{ \Illuminate\Support\Str::limit($report->file_title, 50) }} @endif
                    </div>
                </div>
                <span class="status-badge {{ $report->status_badge }}"><span class="dot"></span>{{ $report->status }}</span>
            </div>

            <div style="font-size:12px;color:var(--gray-500);margin-top:12px;">
                {{ $latest['done'] }} of {{ $latest['total'] }} stages done
            </div>
            <div class="cad-progress">
                <span style="width:{{ $latest['total'] > 0 ? round($latest['done'] / $latest['total'] * 100) : 0 }}%;"></span>
            </div>

            <ol class="cad-tracker">
                @foreach ($latest['stages'] as $s)
                    <li class="is-{{ $s['state'] }}">
                        <span class="dot">
                            @if ($s['state'] === 'done')
                                <i class="fas fa-check"></i>
                            @elseif ($s['state'] === 'returned')
                                <i class="fas fa-rotate-left"></i>
                            @else
                                {{ $s['no'] }}
                            @endif
                        </span>
                        <div style="flex:1;min-width:0;">
                            <div class="name">{{ $s['name'] }}</div>
                            <div class="meta">
                                <span class="state">{{ ucfirst($s['state']) }}</span>
                                @if ($s['actor']) · {{ $s['actor'] }} @endif
                                @if ($s['at']) · {{ $s['at']->format('j M Y, H:i') }} @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <div class="cad-dash-empty">
                <i class="fas fa-route"></i>
                <span>No report is in progress.</span>
                <span style="font-size:12px;">Reports are opened from a registered file; each one follows the Verification, Customary or Statutory stage chain.</span>
                <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.reports.index') }}" style="margin-top:6px;">Open the report register</a>
            </div>
        @endif
    </div>

    {{-- Recent activity --}}
    <div class="dash-card">
        <div class="card-header">
            <h3>Recent Activity</h3>
            <a href="{{ route('cadastral-module.registry.receipts') }}">Intake queue</a>
        </div>

        @if (empty($m['activity']))
            <div class="cad-dash-empty">
                <i class="fas fa-clock-rotate-left"></i>
                <span>Nothing has happened in the module yet.</span>
                <span style="font-size:12px;">File receipts, index-card commissions, survey jobs and duplicate flags will appear here.</span>
            </div>
        @else
            <div class="activity-feed">
                @foreach ($m['activity'] as $e)
                    @php $kind = $activityKinds[$e['kind']] ?? ['icon' => 'fa-circle', 'verb' => '']; @endphp
                    <div class="activity-item">
                        <div class="activity-icon is-{{ $e['kind'] }}"><i class="fas {{ $kind['icon'] }}"></i></div>
                        <div class="activity-content">
                            <div class="text">
                                <a href="{{ $activityHref($e) }}">{{ $kind['verb'] }} · <strong>{{ $e['title'] ?: '—' }}</strong></a>
                            </div>
                            @if ($e['detail'])
                                <div class="time">{{ \Illuminate\Support\Str::limit($e['detail'], 70) }}</div>
                            @endif
                            <div class="time" title="{{ $e['at']->format('j M Y, H:i') }}">{{ $e['at']->diffForHumans() }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="dash-card">
    <div class="card-header">
        <h3>Quick Links</h3>
        <a href="{{ route('cadastral-module.analytics') }}">Analytics</a>
    </div>
    <div class="cad-quick">
        <a href="{{ route('cadastral-module.registry.receipts') }}">
            <i class="fas fa-inbox"></i><span>Registry<small>Intake queue and file receipts</small></span>
        </a>
        <a href="{{ route('cadastral-module.reports.index', ['report_type' => 'verification']) }}">
            <i class="fas fa-magnifying-glass-location"></i><span>Verification<small>Verification reports</small></span>
        </a>
        <a href="{{ route('cadastral-module.index-cards.index') }}">
            <i class="fas fa-id-card"></i><span>Index Cards<small>Commission and track cards</small></span>
        </a>
        <a href="{{ route('cadastral-module.plan-description.index', ['view' => 'fees']) }}#fee-calculator">
            <i class="fas fa-calculator"></i><span>Fee Calculator<small>Cadastral fees and area</small></span>
        </a>
    </div>
</div>

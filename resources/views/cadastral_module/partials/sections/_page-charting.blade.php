@include('cadastral_module.partials._flash')


<div class="page-header">
    <div></div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('cadastral-module.charting.conflicts') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-triangle-exclamation"></i> Conflicts
        </a>
        @canDo('Cad - Records', 'create')
            <a href="{{ route('cadastral-module.charting.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Chart
            </a>
        @endcanDo
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Current Charts</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Charted or Better</div>
        <div class="kpi-value">{{ number_format($stats['charted']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Charting Queue</div>
        <div class="kpi-value">{{ number_format($stats['queue']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Charting Not Required</div>
        <div class="kpi-value">{{ number_format($stats['not_required']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Flagged Conflicts</div>
        <div class="kpi-value">{{ number_format($stats['conflicts']) }}</div>
    </div>
</div>

@if ($stats['clusters'] > 0)
    <div class="caveat" style="border-left-color:var(--danger);background:#fdecea;color:#7f1d1d;">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>{{ $stats['clusters'] }} plot/block/layout combination(s) are charted under more than one file.</strong>
            <a href="{{ route('cadastral-module.charting.conflicts') }}">Review them on the Conflicts screen</a>.
        </div>
    </div>
@endif

{{-- The queue: registered direct files with no current chart. Conversion
     files never appear here; they are counted as not requiring charting. --}}
<div class="table-wrapper" style="margin-bottom:18px;">
    <div class="table-toolbar">
        <div class="left">
            <strong>Charting queue</strong>
            <span class="helper-text" style="margin:0 0 0 8px;">
                Registered intake files (direct RES, COM, IND …) with no current chart.
                {{ number_format($stats['not_required']) }} conversion file(s) are excluded: charting not required.
            </span>
        </div>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>File Number</th><th>Owner</th><th>Type</th><th>Location</th><th>Registered</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($queue as $receipt)
                    <tr>
                        <td><strong>{{ $receipt->file_number }}</strong></td>
                        <td>{{ Str::limit($receipt->file_title, 30) ?: '—' }}</td>
                        <td>{{ \App\Services\Cadastral\CadastralRegistryLookup::typeLabel($receipt->file_number, $receipt->source_registry, $receipt->file_class) }}</td>
                        {{-- District, LGA, State — never the plot number. --}}
                        <td>{{ $receipt->property_location ?: '—' }}</td>
                        <td>{{ optional($receipt->registered_at)->format('d M Y') ?: '—' }}</td>
                        <td>
                            @canDo('Cad - Records', 'create')
                                <a class="btn btn-primary btn-xs" href="{{ route('cadastral-module.charting.create', ['file_number' => $receipt->file_number]) }}">
                                    <i class="fas fa-plus"></i> Chart it
                                </a>
                            @endcanDo
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:20px;color:var(--gray-500);">
                            Nothing is waiting: every registered direct file has a current chart.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($queue->hasPages())
        <div class="table-footer"><span></span><div class="pagination">{{ $queue->links() }}</div></div>
    @endif
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File no., plot, layout or plan no.…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />

        <select name="chart_category" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">Direct & Conversion</option>
            <option value="direct" @selected(request('chart_category')==='direct')>Direct</option>
            <option value="conversion" @selected(request('chart_category')==='conversion')>Conversion</option>
        </select>

        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (\App\Models\Cadastral\CadastralChart::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>

        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
            <input type="checkbox" name="current" value="0" @checked(request('current')==='0') />
            Include superseded
        </label>

        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','chart_category','status','current']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.charting.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Chart</th>
                    <th>Ver.</th>
                    <th>File Number</th>
                    <th>Plot / Block</th>
                    <th>Layout</th>
                    <th>Location</th>
                    <th>Area (sqm)</th>
                    <th>Beacons</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charts as $chart)
                    <tr @if (! $chart->is_current) style="opacity:.6;" @endif>
                        <td>
                            <strong>{{ $chart->chart_ref }}</strong>
                            @if ($chart->conflict_status === 'confirmed')
                                <i class="fas fa-triangle-exclamation" style="color:var(--danger);" title="{{ $chart->conflict_note }}"></i>
                            @endif
                            @if (($conflictCounts[$chart->id] ?? 0) > 0)
                                <div>
                                    <a href="{{ route('cadastral-module.charting.edit', $chart) }}" class="status-badge rejected"
                                       title="Same plot/block/layout or approved plan charted under another file">
                                        <span class="dot"></span>{{ $conflictCounts[$chart->id] }} conflict(s)
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td>v{{ $chart->version }}</td>
                        <td>
                            {{ $chart->file_number }}
                            @unless ($chart->charting_required)
                                <span class="status-badge review" title="Conversion files are not charted">
                                    <span class="dot"></span>Charting not required
                                </span>
                            @endunless
                        </td>
                        <td>{{ $chart->plot_no ?: '—' }}{{ $chart->block_no ? ' / ' . $chart->block_no : '' }}</td>
                        <td>{{ Str::limit($chart->layout_name, 24) ?: '—' }}</td>
                        {{-- District, LGA, State — never the plot number. --}}
                        <td>{{ $chart->property_location ?: '—' }}</td>
                        <td class="money">
                            {{ $chart->area_sqm !== null ? number_format($chart->area_sqm, 2) : '—' }}
                            @if ($chart->area_source)
                                <div class="helper-text" style="margin:0;font-size:10px;">{{ $chart->area_source }}</div>
                            @endif
                        </td>
                        <td>{{ $chart->coordinates_count }}</td>
                        <td>
                            <span class="status-badge {{ $chart->status_badge }}">
                                <span class="dot"></span>{{ $chart->status }}
                            </span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('cadastral-module.charting.edit', $chart) }}" title="Open">
                                    <i class="fas fa-edit"></i>
                                </a>

                                @if ($gis = $chart->gisLink())
                                    <a href="{{ $gis[1] }}" target="_blank" rel="noopener" title="Open {{ $gis[0] }}">
                                        <i class="fas fa-map-location-dot"></i>
                                    </a>
                                @endif

                                @if ($chart->is_current)
                                    <form method="POST" action="{{ route('cadastral-module.charting.new-version', $chart) }}"
                                          style="display:inline"
                                          onsubmit="return confirm('Create version {{ $chart->version + 1 }}? {{ $chart->chart_ref }} will be marked superseded and kept.');">
                                        @csrf
                                        <button type="submit" title="New version"><i class="fas fa-code-branch"></i></button>
                                    </form>
                                @endif

                                @canDo('Cad - Records', 'create')
                                    <a href="{{ route('cadastral-module.plan-description.create', ['chart' => $chart->id]) }}"
                                       title="Plan & description">
                                        <i class="fas fa-ruler-combined"></i>
                                    </a>
                                @endcanDo
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-pen-ruler" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No charts yet.
                            @canDo('Cad - Records', 'create')
                                <a href="{{ route('cadastral-module.charting.create') }}">Create the first one</a>
                            @endcanDo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $charts->firstItem() ?? 0 }}–{{ $charts->lastItem() ?? 0 }}
            of {{ number_format($charts->total()) }} · superseded versions are kept, never deleted
        </span>
        <div class="pagination">{{ $charts->links() }}</div>
    </div>
</div>

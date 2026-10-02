@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-triangle-exclamation"></i> 4.3 · Cadastral Information</div>

<div class="caveat">
    <i class="fas fa-circle-info"></i>
    <div>
        <strong>What this can and cannot detect.</strong>
        It finds <em>identity</em> conflicts — two live charts sharing a plot, block and layout, or
        sharing an approved plan number. That works on data officers already key, and it is what
        catches a double allocation in practice.
        It also attempts a <em>geometric</em> overlap between two beacon rings, but that only covers
        parcels whose coordinates have been keyed. KLAES holds no parcel geometry — no polygons, no
        shapefile or CAD import — so geometric coverage grows only as the rings are entered.
    </div>
</div>

<div class="page-header">
    <div></div>
    <a href="{{ route('cadastral-module.charting.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to the register
    </a>
</div>

<div class="table-wrapper" style="margin-bottom:18px;">
    <div class="table-toolbar">
        <div class="left"><strong>Plots charted under more than one file</strong></div>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>Plot</th><th>Block</th><th>Layout</th><th>Charts</th><th>Distinct Files</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($clusters as $cluster)
                    <tr>
                        <td><strong>{{ $cluster->plot_no }}</strong></td>
                        <td>{{ $cluster->block_no ?: '—' }}</td>
                        <td>{{ $cluster->layout_name ?: '—' }}</td>
                        <td>{{ $cluster->chart_count }}</td>
                        <td>
                            <span class="status-badge rejected">
                                <span class="dot"></span>{{ $cluster->file_count }} files
                            </span>
                        </td>
                        <td>
                            @canDo('Cad - Records', 'edit')
                                <a class="btn btn-outline btn-xs"
                                   href="{{ route('cadastral-module.charting.index', ['q' => $cluster->plot_no]) }}">
                                    Open the charts
                                </a>
                            @endcanDo
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-circle-check" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No plot is charted under more than one file number.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="table-wrapper">
    <div class="table-toolbar">
        <div class="left"><strong>Charts flagged by an officer</strong></div>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>Chart</th><th>File Number</th><th>Plot / Block</th><th>Layout</th><th>Flag</th><th>Note</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($flagged as $chart)
                    <tr>
                        <td><strong>{{ $chart->chart_ref }}</strong></td>
                        <td>{{ $chart->file_number }}</td>
                        <td>{{ $chart->plot_no ?: '—' }}{{ $chart->block_no ? ' / ' . $chart->block_no : '' }}</td>
                        <td>{{ Str::limit($chart->layout_name, 24) ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $chart->conflict_status === 'confirmed' ? 'rejected' : 'review' }}">
                                <span class="dot"></span>{{ ucfirst($chart->conflict_status) }}
                            </span>
                        </td>
                        <td>{{ Str::limit($chart->conflict_note, 60) ?: '—' }}</td>
                        <td>
                            @canDo('Cad - Records', 'edit')
                                <a class="btn btn-outline btn-xs" href="{{ route('cadastral-module.charting.edit', $chart) }}">
                                    Open
                                </a>
                            @endcanDo
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-circle-check" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No chart is currently flagged.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $flagged->firstItem() ?? 0 }}–{{ $flagged->lastItem() ?? 0 }} of {{ number_format($flagged->total()) }}</span>
        <div class="pagination">{{ $flagged->links() }}</div>
    </div>
</div>

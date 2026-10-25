@php
    use App\Http\Controllers\Survey\CoordinateRegisterController as CR;

    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $colours = ['its' => 'pink', 'beacon' => 'blue', 'control' => 'purple', 'lpkn' => 'green'];
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
    $n = fn ($v) => $v === null ? '—' : number_format((float) $v, 3);
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a href="{{ route('survey-module.workflow.coordinate-register.export', request()->only(['q', 'source'])) }}" class="btn btn-secondary">
        <i class="fas fa-file-csv"></i> Export CSV
    </a>
</div>

<div class="kpi-grid">
    @foreach (CR::SOURCES as $k => $label)
        <a class="kpi-card" href="{{ route('survey-module.workflow.coordinate-register.index', ['source' => $k]) }}" style="text-decoration:none;color:inherit;">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ number_format((int) ($counts[$k] ?? 0)) }}</div>
        </a>
    @endforeach
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search point, file no., reference…" style="{{ $inputStyle }}width:260px;" />
        <select name="source" style="{{ $inputStyle }}">
            <option value="">All Sources</option>
            @foreach (CR::SOURCES as $k => $label)
                <option value="{{ $k }}" @selected(request('source') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'source']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.workflow.coordinate-register.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Source</th>
                    <th>Point</th>
                    <th style="text-align:right;">Northing (Y)</th>
                    <th style="text-align:right;">Easting (X)</th>
                    <th style="text-align:right;">Elevation</th>
                    <th>File No.</th>
                    <th>Reference</th>
                    <th>Recorded</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($points as $p)
                    @php
                        $open = match ($p->source) {
                            'its'     => route('survey-module.gkn.coordinates.show', $p->link),
                            'beacon'  => route('survey-module.workflow.beacon-register.index', ['q' => $p->point_id]),
                            'control' => route('survey-module.workflow.control-points.index', ['q' => $p->point_id]),
                            'lpkn'    => route('survey-module.records.lpkn.coordinates', ['layout' => $p->link]),
                            default   => null,
                        };
                    @endphp
                    <tr>
                        <td>{{ $points->firstItem() + $loop->index }}</td>
                        <td>{!! $chip(CR::SOURCES[$p->source] ?? $p->source, $colours[$p->source] ?? 'gray') !!}</td>
                        <td><strong>{{ $p->point_id }}</strong></td>
                        <td style="text-align:right;font-family:monospace;">{{ $n($p->northing) }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ $n($p->easting) }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ $n($p->elevation) }}</td>
                        <td style="font-family:monospace;">{{ $p->file_number ?: '—' }}</td>
                        <td>{{ $p->reference ?: '—' }}</td>
                        <td>{{ $p->recorded_at ? \Illuminate\Support\Carbon::parse($p->recorded_at)->format('d M Y') : '—' }}</td>
                        <td>
                            @if ($open)
                                <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                    <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                            @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                        <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                    </button>
                                    <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                         :style="'top:' + top + 'px; left:' + left + 'px'"
                                         class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                        <a href="{{ $open }}" class="{{ $svItem }}"><i data-lucide="external-link" class="w-4 h-4 text-gray-500"></i> Open in {{ CR::SOURCES[$p->source] }}</a>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="text-align:center;padding:28px;color:var(--gray-500);">
                        {{ request()->hasAny(['q', 'source']) ? 'No coordinates match this search.' : 'No coordinates recorded yet.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $points->firstItem() ?? 0 }}–{{ $points->lastItem() ?? 0 }} of {{ number_format($points->total()) }} points · read-only; edit them where they were recorded</span>
        <div class="pagination">{{ $points->links() }}</div>
    </div>
</div>

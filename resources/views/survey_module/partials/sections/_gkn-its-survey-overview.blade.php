{{-- Overview shared by List of Coordinates and Computation Field Observations: every issued I to S. $page: coordinates | observations --}}
@php
    $isCoords = $page === 'coordinates';
    $route = $isCoords ? 'survey-module.gkn.coordinates' : 'survey-module.gkn.observations';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
@endphp

@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Issued I to S</div><div class="kpi-value">{{ number_format($totals['its']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Beacons recorded</div><div class="kpi-value">{{ number_format($totals['coords']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Field readings</div><div class="kpi-value">{{ number_format($totals['obs']) }}</div></div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search I to S, file, surveyor, grantee…" style="{{ $inputStyle }}width:280px;" />
        <select name="has" style="{{ $inputStyle }}">
            <option value="">All</option>
            <option value="with" @selected(request('has') === 'with')>{{ $isCoords ? 'With coordinates' : 'With readings' }}</option>
            <option value="without" @selected(request('has') === 'without')>{{ $isCoords ? 'Without coordinates' : 'Without readings' }}</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'has']))
            <a class="btn btn-outline btn-sm" href="{{ route($route . '.index') }}">Clear</a>
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
                    <th>I to S No.</th>
                    <th>File No.</th>
                    <th>Grantee</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Surveyor</th>
                    <th style="text-align:right;">Beacons</th>
                    <th style="text-align:right;">Readings</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $a)
                    @php $p = $a['prefill']; @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td>{!! $a['source'] === 'survey' ? $chip('Survey', 'pink') : $chip('Cadastral', 'indigo') !!}@if ($a['locked']) {!! $chip('Closed', 'gray') !!}@endif</td>
                        <td><strong style="font-family:monospace;">{{ $p['its_number'] }}</strong></td>
                        <td style="font-family:monospace;">{{ $p['file_number'] ?: '—' }}</td>
                        <td>{{ $p['grantee_name'] ?: '—' }}</td>
                        <td>{{ $p['plot_no'] ?: '—' }}</td>
                        <td>{{ $p['district'] ?: '—' }}</td>
                        <td>{{ $p['lga'] ?: '—' }}</td>
                        <td>{{ $p['surveyor_name'] ?: '—' }}</td>
                        <td style="text-align:right;">{{ number_format($a['coords']) }}</td>
                        <td style="text-align:right;">{{ number_format($a['obs']) }}</td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.gkn.coordinates.show', $a['key']) }}" class="{{ $svItem }}">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-gray-500"></i> {{ $a['coords'] || $a['locked'] ? 'Open' : 'Create' }} Coordinates
                                    </a>
                                    <a href="{{ route('survey-module.gkn.observations.show', $a['key']) }}" class="{{ $svItem }}">
                                        <i data-lucide="calculator" class="w-4 h-4 text-gray-500"></i> Field Observations
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                            @if (request()->hasAny(['q', 'has']))
                                Nothing matches these filters.
                            @else
                                No issued Instructions to Survey yet. <a href="{{ route('survey-module.gkn.its.create') }}">Create one</a> first.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} instructions</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>

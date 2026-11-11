{{-- A block's farms on the Imported tab (user, 2026-11-09): one row per farmer, placed under the block's row in the
     table's columns. The farmer's lines from the list (matched by name in the block) are added up; display only. --}}
@php
    $num = fn ($v, $d = 4) => $v === null ? '<span class="muted">—</span>' : number_format((float) $v, $d);
    $sum = function ($rows, $col) {
        $vals = $rows->pluck($col)->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v));
        return $vals->isEmpty() ? null : $vals->sum();
    };
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
@endphp
@foreach ($farms as $f)
    @php
        $mine = $lines[$key($f->full_name)] ?? collect();
        $parts = preg_split('/\s+/u', trim((string) $f->full_name)) ?: [];
        $firstName = $parts[0] ?? null;
        $lastName = count($parts) > 1 ? end($parts) : null;
        $middleName = count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null;
        $fNo = $mine->pluck('f_no')->filter()->unique()->implode(', ');
        $plots = $mine->pluck('plot_no')->filter()->map(fn ($p) => trim((string) $p))->filter()->values();
        $remarks = $mine->pluck('remarks')->filter()->unique()->implode('; ');
    @endphp
    <tr class="imp-farm" data-farm-of="{{ $case->id }}">
        <td class="muted nowrap">{{ $sn !== '' ? $sn . '.' : '' }}{{ $first + $loop->index }}</td>
        <td class="wide">{{ $f->full_name ?: '—' }}</td>
        <td class="muted">—</td>
        <td class="muted">—</td>
        <td class="muted">—</td>
        <td class="muted">—</td>
        <td class="nowrap" style="font-family:monospace;">{{ $f->farm_id_no ?: ($fNo !== '' ? 'F ' . $fNo : '—') }}</td>
        <td>{{ $firstName ?: '—' }}</td>
        <td>{{ $middleName ?: '—' }}</td>
        <td>{{ $lastName ?: '—' }}</td>
        <td class="muted">—</td>
        <td class="num muted">—</td>
        <td class="num">{!! $num($sum($mine, 'acreage')) !!}</td>
        <td class="num">{!! $num($sum($mine, 'creetable'), 2) !!}</td>
        <td class="num">{!! $num($sum($mine, 'govt_50'), 2) !!}</td>
        <td class="num">{!! $num($sum($mine, 'farmer_50'), 2) !!}</td>
        <td class="mid" title="{{ $plots->implode(', ') }}">
            @if ($plots->isEmpty())<span class="muted">—</span>@else{{ $plots->count() }} · {{ $plots->take(6)->implode(', ') }}{{ $plots->count() > 6 ? ', …' : '' }}@endif
        </td>
        <td class="mid">{{ $remarks !== '' ? \Illuminate\Support\Str::limit($remarks, 80) : '—' }}</td>
        <td class="ctr muted">—</td>
        <td>@if ($f->status)<span class="status-badge {{ strtolower($f->status) === 'verified' ? 'completed' : 'pending' }}"><span class="dot"></span>{{ $f->status }}</span>@else<span class="muted">—</span>@endif</td>
        <td class="muted">—</td>
        <td class="muted">—</td>
        <td class="col-actions">
            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                     :style="'top:' + top + 'px; left:' + left + 'px'"
                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                    <a href="{{ route('survey-module.compensation.cases.farm-sheet', [$case, $f->id]) }}" target="_blank" class="{{ $svItem }}"><i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> Farm Sheet</a>
                    <a href="{{ route('survey-module.compensation.cases.show', $case) }}" class="{{ $svItem }}"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View block</a>
                </div>
            </div>
        </td>
    </tr>
@endforeach
@if ($farms->isEmpty() && $first === 1)
    <tr class="imp-farm" data-farm-of="{{ $case->id }}"><td colspan="23" class="muted" style="padding:10px 14px;">No farmers recorded on this block.</td></tr>
@endif
@if ($nextPage)
    <tr class="imp-farm" data-farm-of="{{ $case->id }}" data-farm-more="{{ $nextPage }}"><td colspan="23"><button type="button" class="btn btn-outline btn-sm" data-farm-more-btn>Show more farms ({{ number_format($left) }} left)</button></td></tr>
@endif

{{-- A staged sheet's rows, placed under its row in the Imported table, in that table's columns. As written; long decimals
     show to 3 places, the full figure on hover. --}}
@php
    $cell = function ($v) {
        if ($v === null || $v === '') return '<td class="num muted">—</td>';
        $short = preg_match('/^-?\d+\.\d{4,}$/', $v) ? number_format((float) $v, 3, '.', '') : $v;

        return '<td class="num" title="' . e($v) . '">' . e($short) . '</td>';
    };
    $none = '<td class="muted">—</td>';
@endphp
@foreach ($rows as $x)
    <tr class="imp-staged imp-line {{ $x->kind !== 'line' ? 'imp-line-total' : '' }}" data-of="{{ $sheet }}">
        <td class="nowrap">{{ $x->kind === 'line' ? ($group ? $group . '.' : '') . ($first + $loop->index) : '' }}</td>
        <td class="wide">Computation Sheet</td>
        {!! $none !!}
        {!! $none !!}
        {!! $none !!}
        {!! $none !!}
        <td class="nowrap" style="font-family:monospace;">{{ $x->farm_id ?: '—' }}</td>
        <td>{{ $x->first_name ?: '—' }}</td>
        <td>{{ $x->middle_name ?: '—' }}</td>
        <td>{{ $x->last_name ?: '—' }}</td>
        <td class="nowrap">{{ $x->sheet_no ?: '—' }}</td>
        {!! $cell($x->area_m2) !!}
        {!! $cell($x->area_ha) !!}
        {!! $cell($x->creatable) !!}
        {!! $cell($x->govt_50) !!}
        {!! $cell($x->farm_50) !!}
        <td class="mid">{{ $x->plots ?: '—' }}</td>
        <td class="mid">{{ trim(($x->tag_no ? 'Tag No ' . $x->tag_no . ' ' : '') . $x->remarks) ?: '—' }}</td>
        <td class="ctr muted">—</td>
        {!! $none !!}
        {!! $none !!}
        {!! $none !!}
        <td class="col-actions"></td>
    </tr>
@endforeach
@if ($nextPage)
    <tr class="imp-staged" data-of="{{ $sheet }}" data-more="{{ $nextPage }}"><td colspan="23"><button type="button" class="btn btn-outline btn-sm" data-cs-more>Show more ({{ number_format($left) }} left)</button></td></tr>
@endif

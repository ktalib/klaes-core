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
        {{-- The farmer's passport photo from the KMC Access export (user, 2026-11-11), on its own row or on the sheet's
             row it matched; it opens full size. --}}
        @php $srcLabel = ($x->source_file ?? '') === \App\Services\Survey\KmcAccessImport::SOURCE ? 'KMC Access' : 'Computation Sheet'; @endphp
        <td class="wide">
            @if (! empty($x->photo_path))
                <a href="{{ route('survey-module.compensation.computation-sheet.photo', $x->id) }}" target="_blank" rel="noopener" title="{{ $x->photo_name }}" style="display:inline-flex;align-items:center;gap:8px;">
                    <img src="{{ route('survey-module.compensation.computation-sheet.photo', $x->id) }}" alt="Passport photo" loading="lazy" style="width:34px;height:34px;object-fit:cover;border-radius:6px;border:1px solid var(--gray-200);">
                    {{ $srcLabel }}
                </a>
            @else
                {{ $srcLabel }}
            @endif
        </td>
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
        @php
            // Remarks, with the Access farm record's own fields (District, Staff ID, Supervisor, Camera) as written.
            $extra = collect(['District' => $x->district ?? null, 'Staff ID' => $x->staff_id ?? null, 'Supervisor' => $x->supervisor ?? null, 'Camera' => $x->camera ?? null])
                ->filter()->map(fn ($v, $k) => "$k $v")->implode(' · ');
        @endphp
        <td class="mid">{{ trim(implode(' · ', array_filter([$x->tag_no ? 'Tag No ' . $x->tag_no : null, $x->remarks, $extra]))) ?: '—' }}</td>
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

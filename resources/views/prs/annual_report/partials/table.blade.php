{{--
    Generic report table. Driven entirely by the section DTO:
      columns: [ ['label'=>..., 'align'=>'left'|null, 'suspect'=>bool], ... ]
      rows:    [ [cell, cell, ...], ... ]
      total:   [cell, cell, ...]

    A `suspect` column is rendered de-emphasised (greyed) where the source data is
    known to be contaminated — e.g. Deed of Release land use is Certificate of
    Occupancy data. The on-screen explanation of *why* now lives in
    docs/prs-2025/19-ui-caveat-log.md; only the visual de-emphasis remains here.

    The label column is sticky on horizontal scroll, so the month/name stays
    readable on the eight-column monthly tables at narrow widths.
--}}
<div class="prs-table-scroll overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="bg-slate-50">
                @foreach($table['columns'] as $col)
                    @php $isLabel = ($col['align'] ?? '') === 'left'; @endphp
                    <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap border-b border-slate-200
                               {{ $isLabel ? 'text-left prs-sticky-col' : 'text-right' }}
                               {{ ($col['suspect'] ?? false) ? 'text-slate-400' : 'text-slate-500' }}">
                        {{ $col['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($table['rows'] as $row)
                <tr class="hover:bg-slate-50 transition-colors">
                    @foreach($row as $i => $cell)
                        @php
                            $col     = $table['columns'][$i] ?? [];
                            $isLabel = ($col['align'] ?? '') === 'left';
                            $suspect = $col['suspect'] ?? false;
                        @endphp
                        <td class="px-4 py-3 whitespace-nowrap
                                   {{ $isLabel ? 'text-left font-bold text-slate-800 prs-sticky-col' : 'text-right font-medium tabular-nums' }}
                                   {{ $suspect ? 'text-slate-300' : ($isLabel ? '' : 'text-slate-600') }}">
                            {{ is_numeric($cell) ? number_format($cell) : $cell }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        @if(!empty($table['total']))
            <tfoot>
                <tr class="bg-slate-50/80 font-bold border-t-2 border-slate-200">
                    @foreach($table['total'] as $i => $cell)
                        @php
                            $col     = $table['columns'][$i] ?? [];
                            $isLabel = ($col['align'] ?? '') === 'left';
                            $suspect = $col['suspect'] ?? false;
                        @endphp
                        <td class="px-4 py-3.5 whitespace-nowrap
                                   {{ $isLabel ? 'text-left text-slate-800 text-[11px] font-extrabold uppercase tracking-wider prs-sticky-col' : 'text-right font-bold text-slate-900 tabular-nums' }}
                                   {{ $suspect ? 'text-slate-300' : 'text-slate-900' }}">
                            {{ is_numeric($cell) ? number_format($cell) : ($cell ?? '—') }}
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</div>

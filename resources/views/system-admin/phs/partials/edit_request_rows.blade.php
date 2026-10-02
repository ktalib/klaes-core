{{--
  The records on this file that can actually be corrected.

  Sourced from LegalSearchService::search(), NOT from the printed report: report
  rows are rendered output and carry no record id, and several of them are
  synthetic (File Commissioning, Temporary File) with no stored row behind them.
  Selecting one of those to "edit" could never do anything, so they are listed
  separately as context rather than offered as targets.

  data-record-id / data-source-table are what legalsearch.update / .remove /
  .drop key on.
--}}
@php
    $records = $records ?? [];
    $printed = $report['rows'] ?? [];

    $chipFor = fn (string $t) => [
        'file_history_staging' => ['File History', 'bg-blue-50 text-blue-700 ring-blue-100'],
        'CofO_staging'         => ['CofO',         'bg-emerald-50 text-emerald-700 ring-emerald-100'],
        'pra'                  => ['PRA',          'bg-amber-50 text-amber-700 ring-amber-100'],
        'deed_registrations'   => ['Deed Reg.',    'bg-violet-50 text-violet-700 ring-violet-100'],
    ][$t] ?? [$t, 'bg-slate-100 text-slate-600 ring-slate-200'];
@endphp

@if (empty($records))
    <p class="py-8 text-center text-xs text-slate-400">
        No correctable records on this file.
        @if (!empty($printed))
            The printed result contains only derived rows.
        @endif
    </p>
@else
    <p class="mb-2 text-[11px] text-slate-400">
        Click a record to select it, then use the buttons above.
    </p>
    <ul class="space-y-2">
        @foreach ($records as $rec)
            @php [$chipLabel, $chipClass] = $chipFor($rec['table']); @endphp
            <li data-pc-row
                data-record-id="{{ $rec['id'] }}"
                data-source-table="{{ $rec['table'] }}"
                data-label="{{ trim($rec['instrument'] . ' ' . ($rec['reg_no'] ?? '')) }}"
                class="group flex items-start gap-3 rounded-md border border-slate-200 px-3 py-2 cursor-pointer transition hover:border-indigo-300 hover:bg-indigo-50/40">

                {{-- Explicit affordance: without this nothing on the row says it is clickable. --}}
                <span class="pc-dot mt-1 grid h-4 w-4 shrink-0 place-items-center rounded-full border-2 border-slate-300 group-hover:border-indigo-400">
                    <span class="pc-dot-fill h-1.5 w-1.5 rounded-full bg-transparent"></span>
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 {{ $chipClass }}">
                            {{ $chipLabel }}
                        </span>
                        <span class="text-xs font-semibold text-slate-800">{{ $rec['instrument'] }}</span>
                        @if (!empty($rec['reg_no']) && $rec['reg_no'] !== '0/0/0' && $rec['reg_no'] !== '-')
                            <span class="text-[10px] text-slate-400">Reg: {{ $rec['reg_no'] }}</span>
                        @endif
                        <span class="text-[10px] text-slate-300">#{{ $rec['id'] }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[11px] text-slate-600 truncate">
                            {{ $rec['party_1'] ?: '—' }} &rarr; {{ $rec['party_2'] ?: '—' }}
                        </span>
                        <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ $rec['date'] }}</span>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif

{{-- Derived rows: shown so the admin sees the full printed result, but they are
     generated at print time and there is nothing stored to edit. --}}
@php
    $derived = collect($printed)->filter(function ($row) {
        return in_array($row['source_table'] ?? '', ['File Commissioning', 'Temporary File'], true)
            || stripos($row['instrument_type'] ?? '', 'commissioning') !== false
            || stripos($row['instrument_type'] ?? '', 'decommissioning') !== false;
    });
@endphp

@if ($derived->isNotEmpty())
    <div class="mt-4 border-t border-slate-100 pt-3">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            Also on the printed result &mdash; derived, nothing to edit
        </p>
        <ul class="space-y-1.5">
            @foreach ($derived as $row)
                <li class="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 opacity-70">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">
                            derived
                        </span>
                        <span class="text-xs font-semibold text-slate-700">{{ $row['instrument_type'] ?? '—' }}</span>
                        <span class="ml-auto text-[10px] text-slate-400">
                            {{ ($row['transaction_date'] ?? '-') !== '-' ? $row['transaction_date'] : ($row['reg_date'] ?? '') }}
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-500 truncate">
                        {{ $row['grantor'] ?? '—' }} &rarr; {{ $row['grantee'] ?? '—' }}
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

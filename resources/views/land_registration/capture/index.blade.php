@extends('layouts.app')
@section('page-title')
    {{ __('Land Registry') }}
@endsection

@section('content')
    {{--
        The Land Registry: historical Deeds of Purchase backfilled through PRA.

        Same table shape as the New Registration register, so the two read
        alike — but the rows come from `pra`, not deed_registrations. A record
        here was digitised from the paper register; a record there was
        registered through this system and holds a number from the vault.
    --}}
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6 space-y-4">

            @if(session('success'))
                <div class="flex items-start gap-2 bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
                    <i data-lucide="check-circle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
                    <i data-lucide="alert-circle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold">{{ config('land_registration.authority.registry') }}</h1>
                    <p class="text-gray-600 text-sm">
                        {{ config('land_registration.instrument_type') }} records captured through PRA
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                @foreach([
                    ['label' => 'Captured Today', 'value' => $capturedTodayCount, 'icon' => 'calendar-check', 'tone' => 'green'],
                    ['label' => 'Total', 'value' => $totalCount, 'icon' => 'files', 'tone' => 'slate'],
                ] as $stat)
                    <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-center gap-3">
                        <div class="h-9 w-9 rounded-lg bg-{{ $stat['tone'] }}-50 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="{{ $stat['icon'] }}" class="h-4 w-4 text-{{ $stat['tone'] }}-600"></i>
                        </div>
                        <div>
                            <div class="text-xl font-bold leading-tight">{{ number_format($stat['value']) }}</div>
                            <div class="text-xs text-gray-500">{{ $stat['label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="land-registry-table" class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="text-left font-medium px-4 py-3">S/N</th>
                                <th class="text-left font-medium px-4 py-3">PropID</th>
                                <th class="text-left font-medium px-4 py-3">Timeline</th>
                                <th class="text-left font-medium px-4 py-3">File No</th>
                                <th class="text-left font-medium px-4 py-3">Reg Particulars</th>
                                <th class="text-left font-medium px-4 py-3">{{ config('land_registration.parties.first') }}</th>
                                <th class="text-left font-medium px-4 py-3">{{ config('land_registration.parties.second') }}</th>
                                <th class="text-right font-medium px-4 py-3">Payment Amount</th>
                                <th class="text-left font-medium px-4 py-3">Receipt No</th>
                                <th class="text-left font-medium px-4 py-3">Transaction Date</th>
                                <th class="text-left font-medium px-4 py-3">Reg Time</th>
                                <th class="text-left font-medium px-4 py-3">Reg Date</th>
                                <th class="text-left font-medium px-4 py-3">Captured Time</th>
                                <th class="text-left font-medium px-4 py-3">Captured Date</th>
                                <th class="text-left font-medium px-4 py-3">Plot Number</th>
                                <th class="text-left font-medium px-4 py-3">Plot Size</th>
                                <th class="text-left font-medium px-4 py-3">District</th>
                                <th class="text-left font-medium px-4 py-3">LGA</th>
                                <th class="text-left font-medium px-4 py-3">Captured By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($records as $i => $row)
                                @php
                                    $capturedAt = $row->captured_at ? \Carbon\Carbon::parse($row->captured_at) : null;
                                    $regDate = $row->deeds_date ? \Carbon\Carbon::parse($row->deeds_date) : null;
                                    // Free text and sentinel dates reach this column on
                                    // digitised rows, so an unparseable value shows as
                                    // blank rather than taking the page down.
                                    try {
                                        $transactionDate = $row->transaction_date
                                            ? \Carbon\Carbon::parse($row->transaction_date)
                                            : null;
                                    } catch (\Throwable $e) {
                                        $transactionDate = null;
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50" data-row-id="{{ $row->id }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-gray-600">{{ $row->prop_id ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{-- Same cross-table timeline the PRA screen opens: either
                                             identifier will do, so a record with no prop_id still
                                             resolves on its file number. The count is the weighted
                                             (deduped) record count the sibling screens badge. --}}
                                        @php $timelineCount = $timelineCounts[$row->id] ?? 0; @endphp
                                        @if($row->prop_id || $row->fileno)
                                            <button type="button"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium border transition {{ $timelineCount > 0 ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100' : 'bg-gray-50 text-gray-400 border-gray-200 hover:bg-gray-100' }}"
                                                onclick="openPropertyTimeline('{{ $row->prop_id }}', '{{ $row->fileno }}')"
                                                title="View the full property timeline">
                                                <i data-lucide="history" class="h-3 w-3"></i> Timeline ({{ $timelineCount }})
                                            </button>
                                        @else
                                            <span class="text-gray-300 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->fileno ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($row->registration_number)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                                {{ $row->registration_number }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->vendor ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->purchaser ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{-- Formatted only when it really is a number:
                                             legacy rows can hold free text like "N1.5M". --}}
                                        @if(is_numeric($row->amount))
                                            &#8358;{{ number_format((float) $row->amount, 2) }}
                                        @else
                                            {{ $row->amount ?: '—' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->receipt_no ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                        {{ $transactionDate ? $transactionDate->format('d M Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $row->deeds_time ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                        {{ $regDate ? $regDate->format('d M Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                        {{ $capturedAt ? $capturedAt->format('g:i A') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                        {{ $capturedAt ? $capturedAt->format('d M Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->plot_number ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->size ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->district ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->lga ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $row->captured_by_name ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="19" class="px-4 py-12 text-center text-gray-500">
                                        <i data-lucide="inbox" class="h-8 w-8 mx-auto mb-2 text-gray-300"></i>
                                        <p>No Deed of Purchase has been captured through PRA yet.</p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            Historical deeds are backfilled from the Property Records Assistant.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>

    <style>
        /* DataTables' own chrome, brought in line with the rest of the page. */
        #land-registry-table_wrapper .dataTables_filter,
        #land-registry-table_wrapper .dataTables_length {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        #land-registry-table_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.375rem 0.625rem;
            margin-left: 0.5rem;
        }

        #land-registry-table_wrapper .dataTables_info,
        #land-registry-table_wrapper .dataTables_paginate {
            padding: 0.75rem 1rem;
            font-size: 0.8125rem;
        }
    </style>

    {{-- openPropertyTimeline(). The modal shell it renders into
         (tailwind-modal.js) is already global in layouts.app. --}}
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // jQuery and DataTables are loaded globally by layouts.app. Wrapped
            // because anything registered after this would be lost if it threw.
            try {
                const table = document.getElementById('land-registry-table');
                const hasRows = table && table.querySelectorAll('tbody tr[data-row-id]').length > 0;

                if (hasRows && window.jQuery && jQuery.fn.DataTable) {
                    jQuery(table).DataTable({
                        order: [],
                        pageLength: 25,
                        lengthMenu: [10, 25, 50, 100],
                        columnDefs: [{ targets: [0, 2], orderable: false }],  // S/N and Timeline
                        language: {
                            search: 'Search:',
                            searchPlaceholder: 'file no, party 1, party 2, district…',
                            emptyTable: 'No records.',
                            zeroRecords: 'No records match that search.',
                            info: 'Showing _START_ to _END_ of _TOTAL_ records',
                            infoEmpty: 'No records',
                            infoFiltered: '(filtered from _MAX_)',
                        },
                        drawCallback: function () {
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        },
                    });
                }
            } catch (err) {
                console.error('Land Registry: could not initialise the table', err);
            }
        });
    </script>
@endsection

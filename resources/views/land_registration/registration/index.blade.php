@extends('layouts.app')
@section('page-title')
    {{ __('Land Registration — Register') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6 space-y-4">

            @if(session('error'))
                <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
                    <i data-lucide="alert-circle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold">{{ config('land_registration.authority.registry') }} — Register</h1>
                    <p class="text-gray-600 text-sm">
                        {{ config('land_registration.instrument_type') }}, in registry order
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    {{-- The next registration particulars are deliberately not
                         shown here; the number is only settled when a record is
                         actually registered. --}}

                    {{-- The vault manager. This is where the serial, page and
                         volume for this register are amended, so it is reachable
                         from the register itself rather than only from the Deeds
                         screen. Gated exactly as it is there — same audience, no
                         wider access. --}}
                    @if(Auth::user()->assign_role == 'Supper Admin')
                        {{-- The x-data wrapper is not decoration: Alpine only
                             evaluates @click inside a component scope, so a bare
                             button would silently do nothing when clicked. --}}
                        <div x-data="{}">
                            <button type="button" @click="$dispatch('open-instrument-types-modal')"
                                class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg border border-gray-300 transition-colors">
                                <i data-lucide="list" class="h-4 w-4"></i>
                                <span>Instrument Types</span>
                            </button>
                        </div>
                    @endif
                                        @canDo('Land Registration', 'create')
<a href="{{ route('land-registration.capture.create') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span>New Registration</span>
                    </a>
                    @endcanDo
                </div>
            </div>

            @if(Auth::user()->assign_role == 'Supper Admin')
                {{-- instrument-types-manager.js builds every request as
                     `${window.baseUrl}/instrument-types`. Without this the base
                     is undefined, the URL resolves against the current path, and
                     the manager opens showing "No instrument types found" —
                     looking like empty data rather than a 404. --}}
                <script>
                    window.baseUrl = "{{ url('') }}";

                    // This register owns one instrument, so the manager lists
                    // only that one — its officers amend this register's
                    // numbering, not the Deeds vaults.
                    window.instrumentTypesFilter = @json([config('land_registration.instrument_type')]);
                </script>
                @include('instrument_registration.modals.instrument_types_manager')
                <script src="{{ asset('js/instrument-types-manager.js') }}"></script>
            @endif

            <div class="grid grid-cols-2 gap-3">
                @foreach([
                    ['label' => 'Registered Today', 'value' => $registeredTodayCount, 'icon' => 'calendar-check', 'tone' => 'green'],
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
                    <table id="land-register-table" class="min-w-full text-sm">
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
                                <th class="text-left font-medium px-4 py-3">Registered By</th>
                                <th class="text-right font-medium px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($instruments as $i => $row)
                                @php
                                    // Registration and capture timestamps are kept
                                    // apart: a record held back as a temporary
                                    // registration is captured on one day and
                                    // numbered on another.
                                    $capturedAt = $row->captured_at ? \Carbon\Carbon::parse($row->captured_at) : null;
                                    $regDate = $row->deeds_date ? \Carbon\Carbon::parse($row->deeds_date) : null;
                                    // The date on the deed itself. Free text and
                                    // sentinel values reach this column on older
                                    // rows, so an unparseable one shows as blank
                                    // rather than taking the register down.
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
                                            {{-- Badged in the registry's own accent, matching the
                                                 pending pill beside it. Orange rather than green:
                                                 this is the row's identity, not a status. --}}
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                                {{ $row->registration_number }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                                <i data-lucide="clock" class="h-3 w-3"></i> pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->vendor }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->purchaser }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{-- Formatted only when it really is a
                                             number: legacy rows can hold free text
                                             such as "N1.5M". --}}
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
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                        {{ $row->deeds_time ?: '—' }}
                                    </td>
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
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                        {{ trim($row->reg_creator_name ?? '') ?: '—' }}
                                    </td>
                                    {{-- No Documents column: RDS and CoR are on the
                                         Action menu, and having them in both places
                                         only widened an already wide table. --}}
                                    {{-- The menu is positioned fixed from JS rather
                                         than absolutely inside the cell: the table
                                         scrolls horizontally, and an absolutely
                                         positioned menu would be clipped by that
                                         scroll container. --}}
                                    <td class="px-4 py-3 text-right">
                                        <div class="relative inline-block">
                                            <button type="button"
                                                class="js-row-menu p-1.5 rounded hover:bg-gray-100 text-gray-500"
                                                aria-haspopup="true" aria-expanded="false" title="Actions">
                                                <i data-lucide="more-vertical" class="h-4 w-4"></i>
                                            </button>
                                            <div class="js-row-menu-panel hidden fixed z-50 w-44 bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-left">
                                                @if($row->status === 'registered')
                                                    <a href="{{ route('land-registration.registration.view', $row->registered_instrument_id) }}"
                                                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                        <i data-lucide="eye" class="h-4 w-4 text-gray-400"></i> View
                                                    </a>
                                                    @if($row->capture_id)
                                                                                                                @canDo('Land Registration', 'edit')
<a href="{{ route('land-registration.capture.edit', $row->capture_id) }}"
                                                            class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                            <i data-lucide="pencil" class="h-4 w-4 text-gray-400"></i> Edit
                                                        </a>
                                                        @endcanDo
                                                    @endif
                                                                                                        @canDo('Land Registration', 'print')
<a href="{{ url('land-registration/print-rds/' . $row->registered_instrument_id) }}" target="_blank"
                                                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                        <i data-lucide="file-text" class="h-4 w-4 text-gray-400"></i> Print RDS
                                                    </a>
                                                    @endcanDo
                                                    <a href="{{ route('land-registration.cor.index', ['id' => $row->registered_instrument_id]) }}" target="_blank"
                                                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                        <i data-lucide="award" class="h-4 w-4 text-gray-400"></i> Print CoR
                                                    </a>
                                                @else
                                                    <button type="button"
                                                        class="js-register-single w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                                        data-id="{{ $row->id }}" data-fileno="{{ $row->fileno }}">
                                                        <i data-lucide="stamp" class="h-4 w-4 text-gray-400"></i> Register
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="20" class="px-4 py-12 text-center text-gray-500">
                                        <i data-lucide="book" class="h-8 w-8 mx-auto mb-2 text-gray-300"></i>
                                        <p>The register is empty.</p>
                                                                                @canDo('Land Registration', 'create')
<a href="{{ route('land-registration.capture.create') }}"
                                            class="text-orange-600 hover:underline text-sm">Capture the first Deed of Purchase</a>
                                        @endcanDo
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
        #land-register-table_wrapper .dataTables_filter,
        #land-register-table_wrapper .dataTables_length {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        #land-register-table_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.375rem 0.625rem;
            margin-left: 0.5rem;
        }

        #land-register-table_wrapper .dataTables_info,
        #land-register-table_wrapper .dataTables_paginate {
            padding: 0.75rem 1rem;
            font-size: 0.8125rem;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- openPropertyTimeline(). The modal shell it renders into
         (tailwind-modal.js) is already global in layouts.app. --}}
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            /* ------------------------------------------------------------------
             | Search / sort / paging.
             |
             | jQuery and DataTables are already loaded globally by layouts.app,
             | so nothing extra is pulled in here. The table is skipped when it
             | holds only the "register is empty" placeholder row, which has a
             | single cell and would otherwise trip DataTables' column count.
             ------------------------------------------------------------------ */
            const table = document.getElementById('land-register-table');
            const hasRows = table && table.querySelectorAll('tbody tr[data-row-id]').length > 0;

            // Wrapped: everything below this point -- the row action menus and the
            // Register button -- is registered afterwards, and an exception here
            // would abort the rest of this handler and leave the page looking
            // fine but completely inert.
            try {
            if (hasRows && window.jQuery && jQuery.fn.DataTable) {
                jQuery(table).DataTable({
                    order: [],                       // keep the registry order the controller built
                    pageLength: 25,
                    lengthMenu: [10, 25, 50, 100],
                    columnDefs: [
                        // S/N, Timeline and Action are not meaningful to sort on.
                        // Action is the last column, so these indices move whenever
                        // a column is added above.
                        { targets: [0, 2, 19], orderable: false },
                        { targets: [19], searchable: false },
                    ],
                    language: {
                        search: 'Search:',
                        searchPlaceholder: 'file no, party 1, party 2, district…',
                        emptyTable: 'The register is empty.',
                        zeroRecords: 'No registrations match that search.',
                        info: 'Showing _START_ to _END_ of _TOTAL_ registrations',
                        infoEmpty: 'No registrations',
                        infoFiltered: '(filtered from _MAX_)',
                    },
                    drawCallback: function () {
                        // Rows are re-rendered on every draw, so the icons in them
                        // have to be re-created or they come back as bare markup.
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    },
                });
            }
            } catch (err) {
                // The register still renders as a plain table; only search,
                // sorting and paging are lost.
                console.error('Land register: could not initialise the table', err);
            }

            /* ------------------------------------------------------------------
             | Row action menu.
             |
             | Positioned with position:fixed against the button's own rectangle.
             | The table scrolls horizontally, so a menu positioned inside the cell
             | would be clipped by that scroll container instead of overlaying it.
             ------------------------------------------------------------------ */
            const closeAllMenus = () => {
                document.querySelectorAll('.js-row-menu-panel').forEach(p => p.classList.add('hidden'));
                document.querySelectorAll('.js-row-menu').forEach(b => b.setAttribute('aria-expanded', 'false'));
            };

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('.js-row-menu');

                if (!trigger) {
                    // A click inside an open menu should not close it before the
                    // link or button in it has been followed.
                    if (!event.target.closest('.js-row-menu-panel')) closeAllMenus();
                    return;
                }

                const panel = trigger.parentElement.querySelector('.js-row-menu-panel');
                const wasOpen = !panel.classList.contains('hidden');
                closeAllMenus();
                if (wasOpen) return;

                const rect = trigger.getBoundingClientRect();
                panel.classList.remove('hidden');

                // Flip upwards when there is not enough room below.
                const height = panel.offsetHeight;
                const below = window.innerHeight - rect.bottom;
                panel.style.top = (below < height + 8 ? rect.top - height - 4 : rect.bottom + 4) + 'px';
                panel.style.left = Math.max(8, rect.right - panel.offsetWidth) + 'px';

                trigger.setAttribute('aria-expanded', 'true');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });

            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllMenus(); });
            window.addEventListener('resize', closeAllMenus);
            window.addEventListener('scroll', closeAllMenus, true);

            /* ------------------------------------------------------------------
             | Register a held-back capture.
             |
             | Delegated rather than bound per button: DataTables detaches the rows
             | that are not on the current page, so buttons bound at load would go
             | dead as soon as anyone paged or searched.
             ------------------------------------------------------------------ */
            document.addEventListener('click', async function (event) {
                const btn = event.target.closest('.js-register-single');
                if (!btn) return;

                const id = btn.dataset.id;
                const fileNo = btn.dataset.fileno;
                closeAllMenus();

                // Confirmed before it runs: registering burns the next number
                // out of the vault, and a burnt number is never reissued.
                const confirmed = await Swal.fire({
                    title: 'Register this deed?',
                    html: 'File <strong>' + fileNo + '</strong> will take the next number in the series. '
                        + 'That number cannot be returned once issued.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Register',
                    confirmButtonColor: '#ea580c',
                }).then(r => r.isConfirmed);

                if (!confirmed) return;

                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'Registering…';

                try {
                    const res = await fetch("{{ route('land-registration.registration.register-single') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ id: id }),
                    });
                    const data = await res.json();

                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Registered',
                            text: data.message,
                            confirmButtonColor: '#ea580c',
                        });
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'Registration failed.');
                    }
                } catch (err) {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    Swal.fire({ icon: 'error', title: 'Not registered', text: err.message });
                }
            });
        });
    </script>
@endsection

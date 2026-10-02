@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.0.7/datatables.min.css">
    <style>
        .file-number-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .timeline-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .timeline-badge .timeline-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 9999px;
            background: currentColor;
            display: inline-flex;
        }

        .timeline-badge-muted {
            background: #f3f4f6;
            color: #6b7280;
            border-color: #e5e7eb;
        }

        .timeline-badge-info {
            background: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
        }

        .timeline-badge-warn {
            background: #fef3c7;
            color: #b45309;
            border-color: #fde68a;
        }

        .timeline-badge-strong {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fecaca;
        }
    </style>
@endpush

@section('page-title', $PageTitle ?? 'File History View')

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="max-w-7xl mx-auto px-6 py-12 space-y-8">
            <header class="space-y-2">
                <h1 class="text-2xl font-semibold text-gray-900">{{ $PageTitle ?? 'File History View' }}</h1>
                <p class="text-gray-600">Snapshot of file history pulled directly from the indexing archive.</p>
            </header>

            <div class="rounded-md border overflow-x-auto">
                <table id="indexed-files-table" class="min-w-full text-sm text-left">
                    <thead class="bg-gray-50">
                        <tr class="border-b">
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Prop ID</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">File Number</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Party 1</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Party 2</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Party 3</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Instrument Type</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Land Use</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Registration
                                Particulars</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Transaction Date</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Deeds Time</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Deeds Date</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Date Created</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">CreatedBy</th>
                            <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="15" class="p-6 text-center text-sm text-gray-500">Loading file history...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.footer')

        {{-- Property Transaction Modal --}}
        @include('fileindexing.partial.property_transaction_modal')
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableEl = document.getElementById('indexed-files-table');
            if (!tableEl || !window.jQuery) {
                return;
            }

            const generateMenuId = (row) => {
                if (row.id) {
                    return `action-menu-${row.id}`;
                }
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return `action-menu-${window.crypto.randomUUID()}`;
                }
                return `action-menu-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
            };

            const timelineBaseUrl = "{{ url('/file-index-view') }}";

            const actionMenuTemplate = (row) => {
                const menuId = generateMenuId(row);
                const timelineUrl = row.id ? `${timelineBaseUrl}/${row.id}/timeline` : null;
                const linkClasses = timelineUrl
                    ? 'block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900'
                    : 'block px-4 py-2 text-sm text-gray-400 cursor-not-allowed';
                const linkAttrs = timelineUrl ? `href="${timelineUrl}"` : 'href="#" aria-disabled="true"';
                const dupEnabled = !!row.has_duplicate;
                const dupClasses = dupEnabled
                    ? 'block px-4 py-2 text-sm text-rose-700 hover:bg-rose-50 hover:text-rose-900'
                    : 'block px-4 py-2 text-sm text-gray-400 cursor-not-allowed';
                const dupAttrs = dupEnabled
                    ? `href="/duplicate-callup?file_number=${encodeURIComponent(row.file_number || '')}" target="_blank" rel="noopener"`
                    : 'href="#" aria-disabled="true"';
                return `
                                    <div class="relative inline-block text-left">
                                        <button
                                            type="button"
                                            class="action-menu-toggle inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-2 py-1 text-gray-600 hover:bg-gray-50 focus:outline-none"
                                            data-dropdown-target="${menuId}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                            </svg>
                                        </button>
                                        <div
                                            id="${menuId}"
                                            class="action-menu hidden absolute right-0 z-10 mt-2 w-36 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                            role="menu"
                                        >
                                            <div class="py-1">
                                                <a ${linkAttrs}
                                                   class="${linkClasses}"
                                                   role="menuitem">
                                                    View Record
                                                </a>
                                                <a href="javascript:void(0)"
                                                   class="edit-transaction-btn block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                                                   data-id="${row.id}"
                                                   data-file-number="${row.file_number || ''}"
                                                   data-temp-file-no="${row.temp_file_no || row.temp_fileno || ''}"
                                                   data-file-title="${row.file_title || ''}"
                                                   data-lga="${row.lga || ''}"
                                                   data-district="${row.district || ''}"
                                                   data-land-use="${row.land_use || ''}"
                                                   data-plot-no="${row.plot_no || row.plot_number || ''}"
                                                   data-tp-no="${row.tp_no || ''}"
                                                   data-lpkn-no="${row.lpkn_no || ''}"
                                                   role="menuitem">
                                                    Edit Transaction
                                                </a>
                                                <a ${dupAttrs}
                                                   class="${dupClasses}"
                                                   role="menuitem">
                                                    Duplicate Call-up Sheet
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                `;
            };

            const table = $('#indexed-files-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('file-index-view.data') }}",
                    data: function (d) {
                        @php($urlParam = request()->query('url'))
                        @if(!empty($urlParam))
                            d.url = @json($urlParam);
                        @endif
                    },
                },
                pageLength: 25,
                lengthChange: false,
                order: [[11, 'desc']],
                columns: [
                    {
                        data: 'prop_id',
                        render: function (data) {
                            const value = data ?? 'N/A';
                            return `<span class="file-number-badge">${value}</span>`;
                        }
                    },
                    {
                        data: 'file_number',
                        render: function (data, type, row) {
                            const value = data ?? 'N/A';
                            const timelineCount = Number.isFinite(row.timeline_count) ? row.timeline_count : 0;

                            let toneClass = 'timeline-badge-info';
                            if (timelineCount === 0) {
                                toneClass = 'timeline-badge-muted';
                            } else if (timelineCount >= 6 && timelineCount <= 15) {
                                toneClass = 'timeline-badge-warn';
                            } else if (timelineCount > 15) {
                                toneClass = 'timeline-badge-strong';
                            }

                            const propId = (row.prop_id ?? '').toString().trim();
                            const fileNoForTimeline = (row.file_number ?? '').toString().trim();
                            const canOpenTimeline = Boolean(propId || fileNoForTimeline);
                            const timelineParams = [];
                            if (propId) timelineParams.push(`prop_id=${encodeURIComponent(propId)}`);
                            if (fileNoForTimeline) timelineParams.push(`file_number=${encodeURIComponent(fileNoForTimeline)}`);
                            const timelineHref = `/property-search/timeline?${timelineParams.join('&')}`;

                            const timelineHtml = canOpenTimeline
                                ? `<div class="mt-1.5">
                                    <a href="${timelineHref}"
                                       class="timeline-open-link inline-flex hover:opacity-80 transition-opacity"
                                       data-prop-id="${propId}"
                                       data-file-number="${fileNoForTimeline}">
                                        <span class="timeline-badge ${toneClass}">
                                            <span class="timeline-dot"></span>
                                            Timeline (${timelineCount})
                                        </span>
                                    </a>
                                </div>`
                                : `<div class="mt-1.5">
                                    <span class="timeline-badge ${toneClass}">
                                        <span class="timeline-dot"></span>
                                        Timeline (${timelineCount})
                                    </span>
                                </div>`;

                            return `
                                        <div class="space-y-1">
                                            <span class="file-number-badge">${value}</span>
                                            ${timelineHtml}
                                        </div>
                                    `;
                        }
                    },
                    { data: 'party_1' },
                    { data: 'party_2' },
                    { data: 'party_3' },
                    { data: 'instrument_type' },
                    {
                        data: 'land_use',
                        render: function (data) {
                            if (!data || data === 'N/A' || data === '-') {
                                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-gray-100 text-gray-500 border border-gray-200">N/A</span>';
                            }

                            const raw = data.toString().trim().toUpperCase();
                            const normMap = {
                                'COM': 'COMMERCIAL',
                                'RES': 'RESIDENTIAL',
                                'IND': 'INDUSTRIAL',
                                'MIX': 'MIXED USE',
                                'PUB': 'PUBLIC',
                                'AGR': 'AGRICULTURAL',
                                'EDU': 'EDUCATIONAL',
                                'REL': 'RELIGIOUS'
                            };

                            const displayLabel = normMap[raw] || raw;

                            const palette = {
                                'COMMERCIAL': 'bg-amber-50 text-amber-700 border-amber-200',
                                'RESIDENTIAL': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'INDUSTRIAL': 'bg-slate-50 text-slate-700 border-slate-200',
                                'MIXED USE': 'bg-purple-50 text-purple-700 border-purple-200',
                                'PUBLIC': 'bg-blue-50 text-blue-700 border-blue-200',
                                'AGRICULTURAL': 'bg-lime-50 text-lime-700 border-lime-200',
                                'EDUCATIONAL': 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                'RELIGIOUS': 'bg-rose-50 text-rose-700 border-rose-200'
                            };

                            const className = palette[displayLabel] || 'bg-gray-50 text-gray-600 border-gray-200';

                            return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase border ${className}">${displayLabel}</span>`;
                        }
                    },
                    { data: 'registration_particulars' },
                    { data: 'transaction_date' },
                    { 
                        data: 'deeds_time',
                        render: function(data) {
                            return data ? data : '-';
                        }
                    },
                    { data: 'deeds_date' },
                    { data: 'date_created' },
                    { data: 'created_by' },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (_data, _type, row) {
                            return actionMenuTemplate(row);
                        }
                    }
                ],
                language: {
                    search: 'Search records:',
                    paginate: {
                        next: 'Next',
                        previous: 'Previous'
                    },
                    processing: 'Loading records...',
                    zeroRecords: 'No matching entries found.',
                },
                drawCallback: function () {
                    document.querySelectorAll('.action-menu').forEach(menu => menu.classList.add('hidden'));
                }
            });

            const hideAllMenus = () => {
                document.querySelectorAll('.action-menu').forEach(menu => menu.classList.add('hidden'));
            };

            document.addEventListener('click', (event) => {
                const timelineLink = event.target.closest('.timeline-open-link');
                if (timelineLink) {
                    event.preventDefault();
                    const propId = timelineLink.getAttribute('data-prop-id') || '';
                    const fileNumber = timelineLink.getAttribute('data-file-number') || '';

                    if (typeof window.openPropertyTimeline === 'function') {
                        window.openPropertyTimeline(propId, fileNumber);
                    } else {
                        const href = timelineLink.getAttribute('href');
                        if (href) {
                            window.location.href = href;
                        }
                    }
                    return;
                }

                const toggle = event.target.closest('.action-menu-toggle');
                if (toggle) {
                    event.preventDefault();
                    const targetId = toggle.getAttribute('data-dropdown-target');
                    const menu = document.getElementById(targetId);
                    if (menu) {
                        const willOpen = menu.classList.contains('hidden');
                        hideAllMenus();
                        menu.classList.toggle('hidden', !willOpen);
                    }
                    return;
                }

                if (!event.target.closest('.action-menu')) {
                    hideAllMenus();
                }

                // Handle Edit Transaction button click
                const editBtn = event.target.closest('.edit-transaction-btn');
                if (editBtn) {
                    event.preventDefault();
                    hideAllMenus();
                    
                    const dataset = editBtn.dataset;
                    const fileNumber = dataset.fileNumber || dataset.tempFileNo || '';

                    if (!fileNumber) {
                        alert('File number not found for this record.');
                        return;
                    }

                    // Show loading state
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Loading...',
                            text: 'Fetching transaction details',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    }

                    // Prepare data for modal
                    const fileIndexingData = {
                        id: dataset.id,
                        file_number: fileNumber,
                        temp_file_no: dataset.tempFileNo || '',
                        file_title: dataset.fileTitle,
                        lga: dataset.lga,
                        district: dataset.district,
                        land_use_type: dataset.landUse,
                        plot_no: dataset.plotNo,
                        tp_no: dataset.tpNo,
                        lpkn_no: dataset.lpknNo,
                        property_description: [dataset.district, dataset.lga].filter(Boolean).join(', '),
                        existing_records: []
                    };

                    // Fetch existing records
                    fetch(`/api/property-records/check/${encodeURIComponent(fileNumber)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (typeof Swal !== 'undefined') {
                                Swal.close();
                            }
                            
                            if (data.success && data.records) {
                                fileIndexingData.existing_records = data.records;
                            }
                            
                            // Open the modal
                            if (typeof window.openPropertyTransactionModal === 'function') {
                                window.openPropertyTransactionModal(fileIndexingData);
                            } else {
                                alert('Transaction modal functionality is not loaded yet. Please try again or refresh the page.');
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching transactions:', error);
                            if (typeof Swal !== 'undefined') {
                                Swal.close();
                            }
                            alert('Failed to fetch transaction details. Defaulting to empty form.');
                            if (typeof window.openPropertyTransactionModal === 'function') {
                                window.openPropertyTransactionModal(fileIndexingData);
                            }
                        });
                }
            });

            // Callback function required by the property transaction modal
            window.checkExistingPropertyRecords = function() {
                console.log('Update successful, reloading table...');
                if (table) {
                    table.ajax.reload(null, false); // Reload without resetting pagination
                }
            };
        });



        //  get.file indexing(
        //     if fileno = $input.text.get the
        //  )
    </script>
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
@endsection
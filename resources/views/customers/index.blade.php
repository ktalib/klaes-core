@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Customers') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @php
            $statusColors = [
                'Active' => 'bg-green-100 text-green-800 border-green-200',
                'Pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                'Suspended' => 'bg-rose-100 text-rose-800 border-rose-200',
                'Retired' => 'bg-gray-200 text-gray-800 border-gray-300',
            ];

            $typeColors = [
                'Individual' => 'bg-blue-50 text-blue-700 border-blue-200',
                'Corporate' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'Multiple' => 'bg-purple-50 text-purple-700 border-purple-200',
            ];

            $activeFilters = collect([
                ['label' => 'Search', 'value' => request('search')],
                ['label' => 'Type', 'value' => request('customer_type')],
                ['label' => 'Status', 'value' => request('status')],
                ['label' => 'Entity', 'value' => $selectedEntityLabel],
            ])->filter(fn($filter) => filled($filter['value']));

            $typeBreakdown = collect($metrics['type_breakdown'] ?? []);
            $lastUpdatedHuman = isset($metrics['last_updated_at']) && $metrics['last_updated_at']
                ? \Carbon\Carbon::parse($metrics['last_updated_at'])->diffForHumans()
                : '—';
        @endphp

        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <p class="text-sm uppercase tracking-wide text-gray-500">Customers</p>
                    <h1 class="text-2xl font-semibold text-gray-900">Customer Management</h1>
                    <p class="mt-1 text-sm text-gray-600">Search, monitor, and manage customer records with fast insight
                        into entity linkages.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                        <i data-lucide="clock" class="h-3 w-3"></i>
                        <span>Last update {{ $lastUpdatedHuman }}</span>
                    </div>
                    <a href="{{ route('entities.index') }}"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-50">
                        <i data-lucide="square-stack" class="h-4 w-4"></i>
                        <span>View All Entities</span>
                    </a>
                    <a href="{{ route('customers.create') }}"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span>New Customer</span>
                    </a>
                </div>
            </div>

            <section id="customer-filters" class="mt-6 rounded-lg border border-gray-200 bg-white px-5 py-5 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Filter customers</h2>
                        <p class="text-sm text-gray-600">Combine search, type, and status filters for faster lookups.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('entities.index') }}"
                            class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">
                            <i data-lucide="square-stack" class="h-3 w-3"></i>
                            View entities
                        </a>
                        <a href="{{ route('customers.create') }}"
                            class="inline-flex items-center gap-2 rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                            <i data-lucide="plus" class="h-3 w-3"></i>
                            Add customer
                        </a>
                    </div>
                </div>

                <form method="GET" action="{{ route('customers.index') }}" class="mt-4 space-y-4" data-customer-filter-form>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Search</label>
                            <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-white">
                                <span class="px-3 text-gray-400">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                </span>
                                <input type="text" name="search" value="{{ request('search') }}"
                                    placeholder="Name, email, phone, file no."
                                    class="w-full rounded-r-md border-0 py-2 pr-3 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-0"
                                    data-customer-search-input>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Customer type</label>
                            <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-white">
                                <span class="px-3 text-gray-400">
                                    <i data-lucide="layers" class="h-4 w-4"></i>
                                </span>
                                <select name="customer_type"
                                    class="w-full rounded-r-md border-0 py-2 pr-3 text-sm text-gray-700 focus:outline-none focus:ring-0"
                                    data-customer-type-filter>
                                    <option value="">All</option>
                                    @foreach (['Individual' => 'Individual', 'Corporate' => 'Corporate', 'Multiple' => 'Multiple Owners'] as $type => $label)
                                        <option value="{{ $type }}" @selected(request('customer_type') === $type)>{{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-white">
                                <span class="px-3 text-gray-400">
                                    <i data-lucide="badge-check" class="h-4 w-4"></i>
                                </span>
                                <select name="status"
                                    class="w-full rounded-r-md border-0 py-2 pr-3 text-sm text-gray-700 focus:outline-none focus:ring-0"
                                    data-customer-status-filter>
                                    <option value="">All</option>
                                    @foreach (['Active', 'Pending', 'Suspended', 'Retired'] as $status)
                                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Entity ID</label>
                            <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-white">
                                <span class="px-3 text-gray-400">
                                    <i data-lucide="hash" class="h-4 w-4"></i>
                                </span>
                                <input type="number" name="entity_id" value="{{ request('entity_id') }}"
                                    placeholder="e.g. 12045"
                                    class="w-full rounded-r-md border-0 py-2 pr-3 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-0"
                                    data-customer-entity-filter>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-4 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($activeFilters->isNotEmpty())
                                <p class="text-xs uppercase tracking-wide text-gray-500">Active filters</p>
                                @foreach ($activeFilters as $filter)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                        <i data-lucide="filter" class="h-3 w-3"></i>
                                        {{ $filter['label'] }}:
                                        <span class="font-semibold text-gray-900">{{ $filter['value'] }}</span>
                                    </span>
                                @endforeach
                            @else
                                <p class="text-sm text-gray-500">No filters applied</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 hover:text-gray-800"
                                data-customer-reset>Reset</a>
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                                <i data-lucide="filter" class="h-4 w-4"></i>
                                Apply
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="users" class="h-4 w-4 text-gray-500"></i>
                            <p class="text-xs uppercase text-gray-500">All Customers</p>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span
                                class="text-3xl font-semibold text-gray-900">{{ number_format($metrics['total_customers'] ?? 0) }}</span>
                            <span class="text-xs text-gray-500">records</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        <span
                            class="font-semibold text-gray-700">{{ number_format($metrics['active_customers'] ?? 0) }}</span>
                        active today
                    </p>
                </div>

                <div class="flex flex-col justify-between rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm">
                    <div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="shield-check" class="h-4 w-4 text-green-700"></i>
                            <p class="text-xs uppercase text-green-700">Active</p>
                        </div>
                        <div class="mt-2 text-3xl font-semibold text-green-800">
                            {{ number_format($metrics['active_customers'] ?? 0) }}
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-green-700">
                        {{ number_format($metrics['pending_customers'] ?? 0) }} pending onboarding
                    </p>
                </div>

                <div class="flex flex-col justify-between rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="alert-circle" class="h-4 w-4 text-amber-700"></i>
                            <p class="text-xs uppercase text-amber-700">Needs Attention</p>
                        </div>
                        <div class="mt-2 text-3xl font-semibold text-amber-800">
                            {{ number_format(($metrics['pending_customers'] ?? 0) + ($metrics['suspended_customers'] ?? 0)) }}
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-amber-700">
                        <span class="font-semibold">{{ number_format($metrics['suspended_customers'] ?? 0) }}</span>
                        suspended
                    </p>
                </div>

                <div class="flex flex-col justify-between rounded-lg border border-slate-200 bg-slate-50 p-4 shadow-sm">
                    <div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="pie-chart" class="h-4 w-4 text-slate-700"></i>
                            <p class="text-xs uppercase text-slate-700">Type Distribution</p>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (['Individual', 'Corporate', 'Multiple'] as $type)
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium {{ $typeColors[$type] ?? 'bg-white border-slate-200 text-slate-700' }}">
                                    {{ $type }} · <span
                                        class="font-semibold ml-1">{{ number_format($typeBreakdown->get($type, 0)) }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .dataTables_wrapper .dataTables_length,
                .dataTables_wrapper .dataTables_filter,
                .dataTables_wrapper .dataTables_info,
                .dataTables_wrapper .dataTables_paginate {
                    margin: 0.5rem 0;
                }

                .dataTables_wrapper .dataTables_length select,
                .dataTables_wrapper .dataTables_filter input {
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    padding: 0.4rem 0.75rem;
                    font-size: 0.875rem;
                }

                .dataTables_wrapper .dataTables_paginate .paginate_button {
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    padding: 0.35rem 0.65rem;
                    margin: 0 0.125rem;
                    font-size: 0.875rem;
                    color: #374151 !important;
                }

                .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                    background-color: #2563eb !important;
                    border-color: #2563eb !important;
                    color: #fff !important;
                }

                .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
                    background-color: #f3f4f6 !important;
                    border-color: #9ca3af !important;
                }

                .dt-buttons {
                    margin-bottom: 1rem;
                }

                .dt-button {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.5rem 0.9rem;
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    background-color: #ffffff;
                    color: #374151;
                    font-size: 0.875rem;
                    font-weight: 500;
                    margin-right: 0.5rem;
                    transition: background-color 0.2s ease;
                }

                .dt-button:hover {
                    background-color: #f9fafb;
                }
            </style>

            <section class="mt-6 rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="Customer results">
                <div class="relative overflow-x-auto">
                    <div id="customers-search-indicator"
                        class="pointer-events-none absolute inset-x-0 top-4 z-10 hidden items-center justify-center">
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-blue-700 shadow-sm">
                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                            Searching...
                        </span>
                    </div>
                    <table id="customers-table" data-source="{{ route('customers.datatable') }}"
                        data-create-url="{{ route('customers.create') }}"
                        class="min-w-full divide-y divide-gray-200 opacity-100 transition-opacity duration-200"
                        data-loading="true">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Entity ID</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    File Number</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Customer Name</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Customer Type</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Account No.</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Retired By</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Reason Retired</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Email</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Phone</th>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Property Address</th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white"></tbody>
                    </table>
                    <noscript>
                        <div class="px-4 py-4 text-center text-sm text-gray-500">
                            JavaScript is required to load customer records.
                        </div>
                    </noscript>
                </div>
            </section>

            @include('admin.footer')
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableElement = document.getElementById('customers-table');
            const indicator = document.getElementById('customers-search-indicator');
            const filterForm = document.querySelector('[data-customer-filter-form]');
            const searchInput = filterForm ? filterForm.querySelector('[data-customer-search-input]') : null;
            const typeSelect = filterForm ? filterForm.querySelector('[data-customer-type-filter]') : null;
            const statusSelect = filterForm ? filterForm.querySelector('[data-customer-status-filter]') : null;
            const entityInput = filterForm ? filterForm.querySelector('[data-customer-entity-filter]') : null;
            const resetLink = filterForm ? filterForm.querySelector('[data-customer-reset]') : null;
            const dataSource = tableElement ? tableElement.getAttribute('data-source') : null;
            const createUrl = tableElement ? tableElement.getAttribute('data-create-url') : null;

            const revealTable = () => {
                if (!tableElement) {
                    return;
                }
                tableElement.removeAttribute('data-loading');
                tableElement.classList.remove('opacity-0');
                tableElement.classList.add('opacity-100');
            };

            const toggleIndicator = (isVisible) => {
                if (!indicator) {
                    return;
                }
                indicator.classList.toggle('hidden', !isVisible);
            };

            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) {
                console.warn('DataTables not available');
                revealTable();
                return;
            }

            if (!tableElement || !dataSource) {
                revealTable();
                return;
            }

            const table = window.jQuery(tableElement).DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                ordering: true,
                info: true,
                autoWidth: false,
                responsive: false,
                stateSave: true,
                scrollX: true,
                lengthChange: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[2, 'asc']],
                ajax: {
                    url: dataSource,
                    data: function (params) {
                        params.customer_type = typeSelect ? typeSelect.value : '';
                        params.status = statusSelect ? statusSelect.value : '';
                        params.entity_id = entityInput ? entityInput.value : '';
                        params.filter_search = searchInput ? searchInput.value : '';
                    },
                    beforeSend: function () {
                        toggleIndicator(true);
                    },
                    complete: function () {
                        toggleIndicator(false);
                    },
                    error: function () {
                        toggleIndicator(false);
                    }
                },
                columns: [
                    { data: 'entity_id', orderable: true },
                    { data: 'file_number', orderable: true },
                    { data: 'customer_name', orderable: true },
                    { data: 'customer_type', orderable: true },
                    { data: 'account_no', orderable: true },
                    { data: 'retired_by', orderable: true },
                    { data: 'reason_retired', orderable: false },
                    { data: 'email', orderable: false },
                    { data: 'phone', orderable: false },
                    { data: 'property_address', orderable: false },
                    { data: 'actions', orderable: false }
                ],
                columnDefs: [
                    { targets: 0, className: 'px-4 py-4 text-sm font-mono text-gray-900 whitespace-nowrap' },
                    { targets: 1, className: 'px-4 py-4 text-sm font-mono text-gray-900 whitespace-nowrap' },
                    { targets: 2, className: 'px-4 py-4 text-sm text-gray-900' },
                    { targets: 3, className: 'px-4 py-4 text-sm' },
                    { targets: 4, className: 'px-4 py-4 text-sm font-mono text-gray-900 whitespace-nowrap' },
                    { targets: 5, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 6, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 7, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 8, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 9, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 10, className: 'px-4 py-4 text-right text-sm' },
                    {
                        targets: '_all',
                        render: function (data, type, row, meta) {
                            if (meta.col === 3 || meta.col === 10) return data;
                            return data || '<span class=\"text-xs text-gray-400\">&mdash;</span>';
                        }
                    }
                ],
                buttons: [
                    {
                        extend: 'csv',
                        text: '<i class=\"fas fa-file-csv\"></i> CSV',
                        className: 'dt-button',
                        title: 'Customer Management Export',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                        }
                    },
                    {
                        extend: 'excel',
                        text: '<i class=\"fas fa-file-excel\"></i> Excel',
                        className: 'dt-button',
                        title: 'Customer Management Export',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class=\"fas fa-file-pdf\"></i> PDF',
                        className: 'dt-button',
                        title: 'Customer Management Export',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                        },
                        customize: function (doc) {
                            doc.content[1].table.widths = ['8%', '10%', '14%', '8%', '10%', '8%', '12%', '10%', '8%', '12%'];
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class=\"fas fa-print\"></i> Print',
                        className: 'dt-button',
                        title: 'Customer Management',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                        }
                    }
                ],
                language: {
                    lengthMenu: 'Show _MENU_ customers per page',
                    info: 'Showing _START_ to _END_ of _TOTAL_ customers',
                    infoEmpty: 'Showing 0 to 0 of 0 customers',
                    infoFiltered: '(filtered from _MAX_ total customers)',
                    zeroRecords: 'No matching customers found',
                    emptyTable: createUrl
                        ? 'No customers match the current filters. <a href=\"' + createUrl + '\" class=\"text-blue-600 underline\">Add customer</a>.'
                        : 'No customer data available',
                    processing: 'Searching...',
                    paginate: {
                        first: 'First',
                        last: 'Last',
                        next: 'Next',
                        previous: 'Previous'
                    }
                },
                dom: '<\"row\"<\"col-sm-12 col-md-6\"l><\"col-sm-12 col-md-6\"B>>' +
                    '<\"row\"<\"col-sm-12\"tr>>' +
                    '<\"row\"<\"col-sm-12 col-md-5\"i><\"col-sm-12 col-md-7\"p>>',
                initComplete: function () {
                    revealTable();
                    if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                        lucide.createIcons();
                    }
                },
                drawCallback: function () {
                    if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                        lucide.createIcons();
                    }
                }
            });

            const reloadTable = function (resetPaging) {
                table.ajax.reload(null, resetPaging === true);
            };

            if (filterForm) {
                filterForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    reloadTable(true);
                });
            }

            if (searchInput) {
                let debounceHandle = null;
                searchInput.addEventListener('input', function () {
                    if (debounceHandle) {
                        clearTimeout(debounceHandle);
                    }
                    debounceHandle = setTimeout(function () {
                        reloadTable(true);
                    }, 400);
                });
            }

            if (typeSelect) {
                typeSelect.addEventListener('change', function () {
                    reloadTable(true);
                });
            }

            if (statusSelect) {
                statusSelect.addEventListener('change', function () {
                    reloadTable(true);
                });
            }

            if (entityInput) {
                entityInput.addEventListener('change', function () {
                    reloadTable(true);
                });
            }

            if (resetLink) {
                resetLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    if (typeSelect) {
                        typeSelect.value = '';
                    }
                    if (statusSelect) {
                        statusSelect.value = '';
                    }
                    if (entityInput) {
                        entityInput.value = '';
                    }
                    reloadTable(true);
                });
            }
        });
    </script>
@endsection
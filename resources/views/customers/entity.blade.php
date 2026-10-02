@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? (__('Customers for') . ' ' . $entity->entity_name) }}
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

        $typeBreakdown = collect($metrics['type_breakdown'] ?? []);
        $lastUpdatedHuman = isset($metrics['last_updated_at']) && $metrics['last_updated_at']
            ? \Carbon\Carbon::parse($metrics['last_updated_at'])->diffForHumans()
            : '—';
    @endphp

    <style>
        /* Custom DataTables styling to match app theme */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin: 0.5rem 0;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            @apply border border-gray-300 rounded-md px-3 py-1 text-sm;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            @apply px-3 py-1 mx-1 border border-gray-300 rounded text-sm;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            @apply bg-blue-600 text-white border-blue-600;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            @apply bg-gray-100;
        }

        .dt-buttons {
            @apply mb-4;
        }

        .dt-button {
            @apply inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50 mr-2;
        }

        .dt-button:hover {
            @apply bg-gray-50;
        }
    </style>

    <div class="mx-auto max-w-7xl px-6 py-8">
        <!-- Header with Entity Info -->
        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
                        @if($entity->entity_type === 'Individual')
                            <i data-lucide="user" class="h-6 w-6"></i>
                        @elseif($entity->entity_type === 'Corporate')
                            <i data-lucide="building-2" class="h-6 w-6"></i>
                        @else
                            <i data-lucide="users" class="h-6 w-6"></i>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-wide text-gray-500">
                            {{ $entity->entity_type === 'Multiple' ? 'Multiple Owners' : $entity->entity_type }} Entity
                        </p>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ $entity->entity_name }}</h1>
                        <p class="mt-1 text-sm text-gray-600">Customer records linked to this entity</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('entities.index') }}"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-50">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        <span>Back to Entities</span>
                    </a>
                    <a href="{{ route('entities.show', $entity) }}"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-50">
                        <i data-lucide="eye" class="h-4 w-4"></i>
                        <span>View Entity Details</span>
                    </a>
                    <a href="{{ route('customers.create') }}"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span>New Customer</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="users" class="h-4 w-4 text-gray-500"></i>
                        <p class="text-xs uppercase text-gray-500">Total Customers</p>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-3xl font-semibold text-gray-900">{{ number_format($metrics['total_customers']) }}</span>
                        <span class="text-xs text-gray-500">linked</span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ $entity->entity_name }}</p>
            </div>
            <div class="flex flex-col justify-between rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-check" class="h-4 w-4 text-green-700"></i>
                        <p class="text-xs uppercase text-green-700">Active</p>
                    </div>
                    <div class="mt-2 text-3xl font-semibold text-green-800">
                        {{ number_format($metrics['active_customers']) }}
                    </div>
                </div>
                <p class="mt-2 text-xs text-green-700">{{ number_format($metrics['pending_customers']) }} pending</p>
            </div>
            <div class="flex flex-col justify-between rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-alert" class="h-4 w-4 text-amber-700"></i>
                        <p class="text-xs uppercase text-amber-700">Under Review</p>
                    </div>
                    <div class="mt-2 text-3xl font-semibold text-amber-800">
                        {{ number_format(($metrics['pending_customers'] ?? 0) + ($metrics['suspended_customers'] ?? 0)) }}
                    </div>
                </div>
                <p class="mt-2 text-xs text-amber-700">{{ number_format($metrics['suspended_customers'] ?? 0) }}
                    suspended</p>
            </div>
            <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="pie-chart" class="h-4 w-4 text-gray-500"></i>
                        <p class="text-xs uppercase text-gray-500">Type Breakdown</p>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['Individual' => 'Individual', 'Corporate' => 'Corporate', 'Multiple' => 'Multiple Owners'] as $type => $label)
                            @if($typeBreakdown->get($type, 0) > 0)
                                <span
                                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                    {{ $label }} · {{ number_format($typeBreakdown->get($type, 0)) }}
                                </span>
                            @endif
                        @endforeach
                        @if($typeBreakdown->sum() == 0)
                            <span class="text-xs text-gray-400">No customers yet</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Customers Table -->
        <section class="mt-6 rounded-lg border border-gray-200 bg-white shadow-sm"
            aria-label="Customer results for {{ $entity->entity_name }}">
            <div class="overflow-x-auto">
                <table id="entity-customers-table" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                File Number</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Account No.</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Customer</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Status</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Contact</th>
                            <th scope="col"
                                class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($customers as $customer)
                        <tr>
                            <td class="px-4 py-4 text-sm font-mono text-gray-900"
                                data-order="{{ $customer->file_number ?? 'zzz' }}">
                                @if($customer->file_number)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                        <i data-lucide="file-text" class="h-3 w-3"></i>
                                        {{ $customer->file_number }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm font-mono text-gray-900"
                                data-order="{{ $customer->account_no ?? 'zzz' }}">
                                @if($customer->account_no)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                        <i data-lucide="hash" class="h-3 w-3"></i>
                                        {{ $customer->account_no }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
                                        @if($customer->customer_type === 'Individual')
                                            <i data-lucide="user" class="h-4 w-4"></i>
                                        @elseif($customer->customer_type === 'Corporate')
                                            <i data-lucide="building-2" class="h-4 w-4"></i>
                                        @else
                                            <i data-lucide="users" class="h-4 w-4"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $customer->customer_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $customer->customer_type === 'Multiple' ? 'Multiple Owners' : $customer->customer_type }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm" data-order="{{ $customer->status }}">
                                @php($badge = $statusColors[$customer->status] ?? 'bg-gray-100 text-gray-800 border-gray-200')
                                <span
                                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $badge }}">{{ $customer->status }}</span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700">
                                <div class="font-medium text-gray-900">{{ $customer->email ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $customer->phone ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-4 text-right text-sm">
                                <div class="inline-flex items-center gap-3">
                                    <a href="{{ route('customers.show', $customer) }}"
                                        class="text-blue-600 hover:text-blue-800">View</a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('customers.edit', $customer) }}"
                                        class="text-indigo-600 hover:text-indigo-800">Edit</a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <i data-lucide="users-x" class="h-8 w-8 text-gray-400"></i>
                                    <p class="font-medium text-gray-700">No customers linked</p>
                                    <p class="text-xs text-gray-500">This entity has no associated customer records yet.
                                    </p>
                                    <a href="{{ route('customers.create') }}"
                                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                        <i data-lucide="plus" class="h-3 w-3"></i>
                                        Add Customer
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @include('admin.footer')
    </div>
</div>

<!-- DataTables Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<script>
    $(document).ready(function () {
        $('#entity-customers-table').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'dt-button',
                    title: '{{ $entity->entity_name }} - Customer Export',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    }
                },
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'dt-button',
                    title: '{{ $entity->entity_name }} - Customer Export',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'dt-button',
                    title: '{{ $entity->entity_name }} - Customer Export',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    },
                    customize: function (doc) {
                        doc.content[1].table.widths = ['15%', '15%', '30%', '15%', '25%'];
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'dt-button',
                    title: '{{ $entity->entity_name }} - Customer List',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    }
                }
            ],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            responsive: true,
            order: [[0, 'asc']],
            columnDefs: [
                {
                    targets: [5],
                    orderable: false,
                    searchable: false
                }
            ],
            language: {
                search: "Search customers:",
                lengthMenu: "Show _MENU_ customers per page",
                info: "Showing _START_ to _END_ of _TOTAL_ customers",
                infoEmpty: "Showing 0 to 0 of 0 customers",
                infoFiltered: "(filtered from _MAX_ total customers)",
                zeroRecords: "No matching customers found",
                emptyTable: "No customer data available for this entity"
            },
            initComplete: function () {
                // Re-initialize Lucide icons after DataTables renders
                lucide.createIcons();
            },
            drawCallback: function () {
                // Re-initialize Lucide icons after each redraw
                lucide.createIcons();
            }
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
    });
</script>
@endsection
@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Entities') }}
@endsection

@section('content')
    <style>
        /* DataTables custom styling */
        .dataTables_wrapper {
            padding: 1rem;
        }
        
        .dataTables_filter {
            margin-bottom: 1rem;
        }
        
        .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 0.375rem 0.75rem;
            margin-right: 0.25rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .dt-button:hover {
            background-color: #e5e7eb;
            border-color: #9ca3af;
        }
        
        .dataTables_info {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .dataTables_paginate .paginate_button {
            border: 1px solid #d1d5db;
            color: #374151 !important;
            padding: 0.375rem 0.75rem;
            margin: 0 0.125rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background-color: #f3f4f6 !important;
            border-color: #9ca3af !important;
        }
        
        .dataTables_paginate .paginate_button.current {
            background-color: #3b82f6 !important;
            border-color: #3b82f6 !important;
            color: #ffffff !important;
        }
        
        .dataTables_paginate .paginate_button.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Responsive table styling */
        @media (max-width: 768px) {
            .dataTables_wrapper .row > div {
                margin-bottom: 0.5rem;
            }
        }
    </style>
 


<div class="flex-1 overflow-auto">
    @include('admin.header')

    <div class="mx-auto max-w-7xl px-6 py-8 space-y-8">
        @include('entities.partials.hero', ['stats' => $stats ?? []])
        @include('entities.partials.stats-overview', ['stats' => $stats ?? []])
        @include('entities.partials.filter-panel')
        @include('entities.partials.table', ['entities' => $entities])
    </div>

    @include('admin.footer')
</div>


 
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableElement = document.getElementById('entities-table');
            const indicator = document.getElementById('entities-search-indicator');
            const filterForm = document.querySelector('[data-entity-filter-form]');
            const searchInput = filterForm ? filterForm.querySelector('[data-entity-search-input]') : null;
            const typeSelect = filterForm ? filterForm.querySelector('[data-entity-type-filter]') : null;
            const resetLink = filterForm ? filterForm.querySelector('[data-entity-reset]') : null;
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
                scrollX: true,
                stateSave: true,
                lengthChange: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[0, 'asc']],
                ajax: {
                    url: dataSource,
                    data: function (params) {
                        params.entity_type = typeSelect ? typeSelect.value : '';
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
                    { data: 'file_number', orderable: true },
                    { data: 'media', orderable: false },
                    { data: 'entity', orderable: true },
                    { data: 'type', orderable: true },
                    { data: 'linked_customers', orderable: true },
                    { data: 'date_captured', orderable: true },
                    { data: 'actions', orderable: false }
                ],
                columnDefs: [
                    { targets: 0, className: 'whitespace-nowrap px-4 py-4 text-sm font-mono text-gray-900' },
                    { targets: 1, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 2, className: 'px-4 py-4 text-sm text-gray-900' },
                    { targets: 3, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 4, className: 'px-4 py-4 text-sm text-gray-700' },
                    { targets: 5, className: 'whitespace-nowrap px-4 py-4 text-sm text-gray-600' },
                    { targets: 6, className: 'px-4 py-4 text-right text-sm' },
                    {
                        targets: '_all',
                        render: function (data) {
                            return data || '';
                        }
                    }
                ],
                buttons: [
                    {
                        extend: 'copy',
                        text: '<i class="fa fa-copy"></i> Copy',
                        className: 'btn btn-sm btn-secondary',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fa fa-file-csv"></i> CSV',
                        className: 'btn btn-sm btn-secondary',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        },
                        filename: 'entities_' + new Date().toISOString().split('T')[0]
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel"></i> Excel',
                        className: 'btn btn-sm btn-secondary',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        },
                        filename: 'entities_' + new Date().toISOString().split('T')[0]
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fa fa-file-pdf"></i> PDF',
                        className: 'btn btn-sm btn-secondary',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        },
                        filename: 'entities_' + new Date().toISOString().split('T')[0],
                        orientation: 'landscape',
                        pageSize: 'A4'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print"></i> Print',
                        className: 'btn btn-sm btn-secondary',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        }
                    }
                ],
                language: {
                    lengthMenu: 'Show _MENU_ entities',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entities',
                    infoEmpty: 'No entities to display',
                    infoFiltered: '(filtered from _MAX_ total entities)',
                    emptyTable: createUrl
                        ? 'No entities match the current filters. <a href="' + createUrl + '" class="text-blue-600 underline">Add entity</a>.'
                        : 'No entity records available',
                    zeroRecords: 'No matching entities found',
                    processing: 'Searching...',
                    paginate: {
                        first: 'First',
                        last: 'Last',
                        next: 'Next',
                        previous: 'Previous'
                    }
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"B>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
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

            if (resetLink) {
                resetLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    if (typeSelect) {
                        typeSelect.value = '';
                    }
                    reloadTable(true);
                });
            }
        });
    </script>
 
@endsection

@extends('layouts.app')

@section('page-title')
    Grouping Analytics (New)
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-100 min-h-screen">
        @include($headerPartial ?? 'admin.header')

        <div class="p-6 space-y-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-2xl font-semibold text-slate-900">Grouping Analytics</h1>
                <p class="text-sm text-slate-500 mt-2">
                    This dashboard is in a fresh state. Use the controls below to introduce new queries and visualisations.
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Data Preview</h2>
                        <span class="text-xs uppercase tracking-wide text-slate-400">Live from SQL Server</span>
                    </div>
                    <p class="text-sm text-slate-500">
                        Results load directly from the `grouping` table with server-side pagination. Use the filters below to narrow the dataset before exporting or drilling deeper.
                    </p>
                    <div class="mt-3">
                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="flex flex-col text-xs font-medium uppercase text-slate-500 gap-1">
                                <span>MDC Batch No.</span>
                                <input type="text" id="filter-mdc-batch" placeholder="e.g. 1" class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                            </label>
                            <label class="flex flex-col text-xs font-medium uppercase text-slate-500 gap-1">
                                <span>SYS Batch No.</span>
                                <input type="text" id="filter-sys-batch" placeholder="e.g. 12" class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                            </label>
                            <div class="flex items-end gap-2">
                                <button type="button" id="filter-apply" class="inline-flex items-center justify-center rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                                    Apply Filters
                                </button>
                                <button type="button" id="filter-reset" class="inline-flex items-center justify-center rounded border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-1">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table id="grouping-table" class="display min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left hidden">ID</th>
                                <th scope="col" class="px-4 py-3 text-left">Tracking ID</th>
                                <th scope="col" class="px-4 py-3 text-left">Awaiting File No</th>
                                <th scope="col" class="px-4 py-3 text-left">MLPP File No</th>
                                <th scope="col" class="px-4 py-3 text-left">FileNo Mapping</th>
                                  <th scope="col" class="px-4 py-3 text-left">Index Mapping</th>
                                <th scope="col" class="px-4 py-3 text-left">Group No</th>
                                <th scope="col" class="px-4 py-3 text-left">SYS Batch</th>
                               
                                <th scope="col" class="px-4 py-3 text-left">MDC Batch</th>
                                <th scope="col" class="px-4 py-3 text-left">Registry</th>
                                <th scope="col" class="px-4 py-3 text-left">Shelf Rack</th>
                                <th scope="col" class="px-4 py-3 text-left">Indexed By</th>
                                <th scope="col" class="px-4 py-3 text-left">Land Use</th>
                                <th scope="col" class="px-4 py-3 text-left">Year</th>
                                <th scope="col" class="px-4 py-3 text-left">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>

        @include($footerPartial ?? 'admin.footer')
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableElement = $('#grouping-table');

            if (!tableElement.length || !$.fn.DataTable) {
                return;
            }

            const batchFilters = {
                mdc: $('#filter-mdc-batch'),
                sys: $('#filter-sys-batch')
            };

            const dataTable = tableElement.DataTable({
                processing: true,
                serverSide: true,
                serverMethod: 'GET',
                deferRender: true,
                searchDelay: 400,
                pageLength: 50,
                order: [[0, 'asc']],
                columnDefs: [
                    {
                        targets: '_all',
                        className: 'text-left align-middle whitespace-nowrap'
                    }
                ],
                ajax: {
                    url: '{{ route('grouping-analytics.data') }}',
                    data: function (params) {
                        params.mdc_batch_no = batchFilters.mdc.val();
                        params.sys_batch_no = batchFilters.sys.val();
                    }
                },
                columns: [
                    {
                        data: 'id',
                        name: 'id',
                        visible: false,
                        searchable: false,
                        defaultContent: ''
                    },
                    {
                        data: 'tracking_id',
                        name: 'tracking_id',
                        defaultContent: '',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data;
                            }

                            if (!data) {
                                return '<span class="text-slate-400">—</span>';
                            }

                            return `<span class="font-mono text-xs inline-flex items-center px-2 py-0.5 rounded bg-red-100 text-red-700 border border-red-200">${data}</span>`;
                        }
                    },
                    {
                        data: 'awaiting_fileno',
                        name: 'awaiting_fileno',
                        defaultContent: '',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data;
                            }

                            if (!data) {
                                return '<span class="text-slate-400">—</span>';
                            }

                            return `<span class="font-medium text-slate-700">${data}</span>`;
                        }
                    },
                    {
                        data: 'mls_fileno',
                        name: 'mls_fileno',
                        defaultContent: ''
                    },
                    {
                        data: 'mapping',
                        name: 'mapping',
                        defaultContent: '',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data;
                            }

                            const isMapped = String(data) === '1';
                            const badgeClass = isMapped
                                ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                                : 'bg-amber-100 text-amber-700 border border-amber-200';
                            const label = isMapped ? 'Mapped' : 'Pending';

                            return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">${label}</span>`;
                        }
                    },
                    {
                        data: 'indexing_mapping',
                        name: 'indexing_mapping',
                        defaultContent: '',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data;
                            }

                            const isMapped = String(data) === '1';
                            const badgeClass = isMapped
                                ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                                : 'bg-amber-100 text-amber-700 border border-amber-200';
                            const label = isMapped ? 'Mapped' : 'Pending';

                            return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">${label}</span>`;
                        }
                    },
                    { data: 'group_no', name: 'group', defaultContent: '', orderable: false, searchable: false },
                    { data: 'sys_batch_no', name: 'sys_batch_no', defaultContent: '' },
                    { data: 'mdc_batch_no', name: 'mdc_batch_no', defaultContent: '' },
                    { data: 'registry', name: 'registry', defaultContent: '' },
                    {
                        data: 'shelf_rack',
                        name: 'shelf_rack',
                        defaultContent: '',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data;
                            }

                            if (!data) {
                                return '<span class="text-slate-400">—</span>';
                            }

                            return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 border border-sky-200">${data}</span>`;
                        }
                    },
                    { data: 'indexed_by', name: 'indexed_by', defaultContent: '' },
                    { data: 'landuse', name: 'landuse', defaultContent: '' },
                    { data: 'year', name: 'year', defaultContent: '' },
                    { data: 'created_at', name: 'created_at', defaultContent: '' }
                ],
                language: {
                    emptyTable: 'No records match the current filters.'
                }
            });

            const triggerReload = function () {
                dataTable.ajax.reload(null, false);
            };

            batchFilters.mdc.on('keyup change', triggerReload);
            batchFilters.sys.on('keyup change', triggerReload);

            $('#filter-apply').on('click', triggerReload);

            $('#filter-reset').on('click', function () {
                batchFilters.mdc.val('');
                batchFilters.sys.val('');
                triggerReload();
            });
        });
    </script>
@endsection

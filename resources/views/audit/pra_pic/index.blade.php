@extends('layouts.app')

@section('page-title')
    PRA & PIC Audit Dashboard
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>[x-cloak]{display:none !important;}</style>
@endpush

@section('content')
    <div class="flex-1 overflow-auto bg-slate-100 min-h-screen">
        @include($headerPartial ?? 'admin.header')

        <div x-data="{ activeTab: 'pra' }" class="p-6 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center gap-3 mb-4">
                    <button type="button" class="px-4 py-2 text-sm font-semibold rounded" :class="activeTab === 'pra' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'" @click="activeTab = 'pra'; window.dispatchEvent(new CustomEvent('pra-pic-tab-change', { detail: 'pra' }));">PRA</button>
                    <button type="button" class="px-4 py-2 text-sm font-semibold rounded" :class="activeTab === 'pic' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'" @click="activeTab = 'pic'; window.dispatchEvent(new CustomEvent('pra-pic-tab-change', { detail: 'pic' }));">PIC</button>
                </div>
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">PRA & PIC Audit Dashboard</h1>
                        <p class="text-sm text-slate-500 mt-1">Investigate audit trails with user-linked records and legacy string issuances side by side.</p>
                    </div>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-4">
                    <label class="flex flex-col text-xs font-semibold uppercase text-slate-500 gap-1">
                        <span>User Filter</span>
                        <select id="audit-user-filter" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="">All Users</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->display_name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col text-xs font-semibold uppercase text-slate-500 gap-1">
                        <span>Start Date</span>
                        <input type="text" id="audit-start-date" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" placeholder="YYYY-MM-DD" value="{{ $defaultStart }}">
                    </label>
                    <label class="flex flex-col text-xs font-semibold uppercase text-slate-500 gap-1">
                        <span>End Date</span>
                        <input type="text" id="audit-end-date" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" placeholder="YYYY-MM-DD" value="{{ $defaultEnd }}">
                    </label>
                    <div class="flex items-end gap-2">
                        <button type="button" id="audit-apply-filter" class="inline-flex items-center justify-center rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">Apply</button>
                        <button type="button" id="audit-reset-filter" class="inline-flex items-center justify-center rounded border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-1">Reset</button>
                    </div>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">PRA User Entries</h3>
                        <div class="mt-3 flex items-end justify-between">
                            <div>
                                <p class="text-2xl font-semibold text-slate-900" id="stat-pra-user-total">—</p>
                                <p class="text-xs text-slate-500 mt-1">Total Records</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-medium text-blue-600" id="stat-pra-user-distinct">—</p>
                                <p class="text-xs text-slate-500 mt-1">Distinct Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">PIC User Entries</h3>
                        <div class="mt-3 flex items-end justify-between">
                            <div>
                                <p class="text-2xl font-semibold text-slate-900" id="stat-pic-user-total">—</p>
                                <p class="text-xs text-slate-500 mt-1">Total Records</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-medium text-blue-600" id="stat-pic-user-distinct">—</p>
                                <p class="text-xs text-slate-500 mt-1">Distinct Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">PRA String Labels</h3>
                        <div class="mt-3 flex items-end justify-between">
                            <div>
                                <p class="text-2xl font-semibold text-slate-900" id="stat-pra-string-total">—</p>
                                <p class="text-xs text-slate-500 mt-1">Total Records</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-medium text-emerald-600" id="stat-pra-string-labels">—</p>
                                <p class="text-xs text-slate-500 mt-1">Distinct Labels</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">PIC String Labels</h3>
                        <div class="mt-3 flex items-end justify-between">
                            <div>
                                <p class="text-2xl font-semibold text-slate-900" id="stat-pic-string-total">—</p>
                                <p class="text-xs text-slate-500 mt-1">Total Records</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-medium text-emerald-600" id="stat-pic-string-labels">—</p>
                                <p class="text-xs text-slate-500 mt-1">Distinct Labels</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-6 space-y-10">
                    <div x-show="activeTab === 'pra'" x-cloak class="space-y-8">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 mb-3">User-linked PRA Records</h2>
                            <table id="pra-user-records-table" class="display min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Created At</th>
                                        <th class="px-4 py-3 text-left">User</th>
                                        <th class="px-4 py-3 text-left">Count</th>
                                        <th class="px-4 py-3 text-left">Transaction</th>
                                        <th class="px-4 py-3 text-left">MLPP File No</th>
                                        <th class="px-4 py-3 text-left">Serial</th>
                                        <th class="px-4 py-3 text-left">Page</th>
                                        <th class="px-4 py-3 text-left">Volume</th>
                                        <th class="px-4 py-3 text-left">Grantor</th>
                                        <th class="px-4 py-3 text-left">Grantee</th>
                                        <th class="px-4 py-3 text-left">Transaction Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 mb-3">String-based PRA Entries</h2>
                            <table id="pra-string-records-table" class="display min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Label</th>
                                        <th class="px-4 py-3 text-left">Count</th>
                                        <th class="px-4 py-3 text-left">Created At</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="activeTab === 'pic'" x-cloak class="space-y-8">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 mb-3">User-linked PIC Records</h2>
                            <table id="pic-user-records-table" class="display min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Created At</th>
                                        <th class="px-4 py-3 text-left">User</th>
                                        <th class="px-4 py-3 text-left">Count</th>
                                        <th class="px-4 py-3 text-left">Transaction</th>
                                        <th class="px-4 py-3 text-left">MLPP File No</th>
                                        <th class="px-4 py-3 text-left">KANGIS File No</th>
                                        <th class="px-4 py-3 text-left">Serial</th>
                                        <th class="px-4 py-3 text-left">Page</th>
                                        <th class="px-4 py-3 text-left">Volume</th>
                                        <th class="px-4 py-3 text-left">Grantor</th>
                                        <th class="px-4 py-3 text-left">Grantee</th>
                                        <th class="px-4 py-3 text-left">Transaction Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 mb-3">String-based PIC Entries</h2>
                            <table id="pic-string-records-table" class="display min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-xs tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Label</th>
                                        <th class="px-4 py-3 text-left">Count</th>
                                        <th class="px-4 py-3 text-left">Created At</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include($footerPartial ?? 'admin.footer')
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterControls = {
                user: document.getElementById('audit-user-filter'),
                start: document.getElementById('audit-start-date'),
                end: document.getElementById('audit-end-date'),
                apply: document.getElementById('audit-apply-filter'),
                reset: document.getElementById('audit-reset-filter'),
            };

            const statElements = {
                praUserTotal: document.getElementById('stat-pra-user-total'),
                praUserDistinct: document.getElementById('stat-pra-user-distinct'),
                picUserTotal: document.getElementById('stat-pic-user-total'),
                picUserDistinct: document.getElementById('stat-pic-user-distinct'),
                praStringTotal: document.getElementById('stat-pra-string-total'),
                praStringLabels: document.getElementById('stat-pra-string-labels'),
                picStringTotal: document.getElementById('stat-pic-string-total'),
                picStringLabels: document.getElementById('stat-pic-string-labels'),
            };

            const statsUrl = '{{ route('pra-pic-audit.stats') }}';

            const filterState = () => ({
                user_id: filterControls.user.value,
                start_date: filterControls.start.value,
                end_date: filterControls.end.value,
            });

            const formatNumber = (value) => {
                const numeric = Number(value);
                if (!isFinite(numeric)) {
                    return '—';
                }

                return numeric.toLocaleString();
            };

            const setStatLoading = () => {
                for (const key in statElements) {
                    if (Object.prototype.hasOwnProperty.call(statElements, key) && statElements[key]) {
                        statElements[key].textContent = '…';
                    }
                }
            };

            const setStatFallback = () => {
                for (const key in statElements) {
                    if (Object.prototype.hasOwnProperty.call(statElements, key) && statElements[key]) {
                        statElements[key].textContent = '—';
                    }
                }
            };

            const applyStats = (payload) => {
                if (!payload || typeof payload !== 'object') {
                    setStatFallback();
                    return;
                }

                const praUser = payload.pra_user || {};
                const picUser = payload.pic_user || {};
                const praStrings = payload.pra_strings || {};
                const picStrings = payload.pic_strings || {};

                if (statElements.praUserTotal) {
                    statElements.praUserTotal.textContent = formatNumber(praUser.total_records);
                }

                if (statElements.praUserDistinct) {
                    statElements.praUserDistinct.textContent = formatNumber(praUser.distinct_users);
                }

                if (statElements.picUserTotal) {
                    statElements.picUserTotal.textContent = formatNumber(picUser.total_records);
                }

                if (statElements.picUserDistinct) {
                    statElements.picUserDistinct.textContent = formatNumber(picUser.distinct_users);
                }

                if (statElements.praStringTotal) {
                    statElements.praStringTotal.textContent = formatNumber(praStrings.total_records);
                }

                if (statElements.praStringLabels) {
                    statElements.praStringLabels.textContent = formatNumber(praStrings.labels);
                }

                if (statElements.picStringTotal) {
                    statElements.picStringTotal.textContent = formatNumber(picStrings.total_records);
                }

                if (statElements.picStringLabels) {
                    statElements.picStringLabels.textContent = formatNumber(picStrings.labels);
                }
            };

            const fetchStats = () => {
                if (!statsUrl) {
                    setStatFallback();
                    return;
                }

                setStatLoading();

                const params = filterState();
                const searchParams = new URLSearchParams();

                if (params.user_id) {
                    searchParams.append('user_id', params.user_id);
                }

                if (params.start_date) {
                    searchParams.append('start_date', params.start_date);
                }

                if (params.end_date) {
                    searchParams.append('end_date', params.end_date);
                }

                const url = searchParams.toString() ? statsUrl + '?' + searchParams.toString() : statsUrl;

                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Failed to load stats');
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        applyStats(data);
                    })
                    .catch(function () {
                        setStatFallback();
                    });
            };

            const tables = {
                praUser: $('#pra-user-records-table').DataTable({
                    processing: true,
                    serverSide: true,
                    serverMethod: 'GET',
                    searchDelay: 400,
                    deferRender: true,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    dom: 'Bfrtip',
                    buttons: ['copy', 'excel', 'csv', 'pdf', 'print'],
                    ajax: {
                        url: '{{ route('pra-pic-audit.pra.user-records') }}',
                        data: function (params) {
                            Object.assign(params, filterState());
                        },
                    },
                    columns: [
                        { data: 'created_at', name: 'p.created_at', defaultContent: '' },
                        { data: 'user_full_name', name: 'user_full_name', defaultContent: '' },
                        { data: 'entry_count', name: 'entry_count', defaultContent: '', orderable: false, searchable: false },
                        { data: 'transaction_type', name: 'p.transaction_type', defaultContent: '' },
                        { data: 'mlsFNo', name: 'p.mlsFNo', defaultContent: '' },
                        { data: 'serialNo', name: 'p.serialNo', defaultContent: '0' },
                        { data: 'pageNo', name: 'p.pageNo', defaultContent: '0' },
                        { data: 'volumeNo', name: 'p.volumeNo', defaultContent: '0' },
                        { data: 'grantor', name: 'p.grantor', defaultContent: '' },
                        { data: 'grantee', name: 'p.grantee', defaultContent: '' },
                        { data: 'transaction_date', name: 'p.transaction_date', defaultContent: '' },
                    ],
                }),
                praString: $('#pra-string-records-table').DataTable({
                    processing: true,
                    serverSide: true,
                    serverMethod: 'GET',
                    searchDelay: 400,
                    deferRender: true,
                    pageLength: 25,
                    order: [[1, 'desc']],
                    dom: 'Bfrtip',
                    buttons: ['copy', 'excel', 'csv', 'pdf', 'print'],
                    ajax: {
                        url: '{{ route('pra-pic-audit.pra.string-records') }}',
                        data: function (params) {
                            Object.assign(params, filterState());
                        },
                    },
                    columns: [
                        { data: 'created_by_label', name: 'created_by_label', defaultContent: '' },
                        { data: 'total_records', name: 'total_records', defaultContent: '' },
                        { data: 'first_created_at', name: 'first_created_at', defaultContent: '' },
                    ],
                }),
                picUser: $('#pic-user-records-table').DataTable({
                    processing: true,
                    serverSide: true,
                    serverMethod: 'GET',
                    searchDelay: 400,
                    deferRender: true,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    dom: 'Bfrtip',
                    buttons: ['copy', 'excel', 'csv', 'pdf', 'print'],
                    ajax: {
                        url: '{{ route('pra-pic-audit.pic.user-records') }}',
                        data: function (params) {
                            Object.assign(params, filterState());
                        },
                    },
                    columns: [
                        { data: 'created_at', name: 'p.created_at', defaultContent: '' },
                        { data: 'user_full_name', name: 'user_full_name', defaultContent: '' },
                        { data: 'entry_count', name: 'entry_count', defaultContent: '', orderable: false, searchable: false },
                        { data: 'transaction_type', name: 'p.transaction_type', defaultContent: '' },
                        { data: 'mlsFNo', name: 'p.mlsFNo', defaultContent: '' },
                        { data: 'kangisFileNo', name: 'p.kangisFileNo', defaultContent: '' },
                        { data: 'serialNo', name: 'p.serialNo', defaultContent: '0' },
                        { data: 'pageNo', name: 'p.pageNo', defaultContent: '0' },
                        { data: 'volumeNo', name: 'p.volumeNo', defaultContent: '0' },
                        { data: 'grantor', name: 'p.grantor', defaultContent: '' },
                        { data: 'grantee', name: 'p.grantee', defaultContent: '' },
                        { data: 'transaction_date', name: 'p.transaction_date', defaultContent: '' },
                    ],
                }),
                picString: $('#pic-string-records-table').DataTable({
                    processing: true,
                    serverSide: true,
                    serverMethod: 'GET',
                    searchDelay: 400,
                    deferRender: true,
                    pageLength: 25,
                    order: [[1, 'desc']],
                    dom: 'Bfrtip',
                    buttons: ['copy', 'excel', 'csv', 'pdf', 'print'],
                    ajax: {
                        url: '{{ route('pra-pic-audit.pic.string-records') }}',
                        data: function (params) {
                            Object.assign(params, filterState());
                        },
                    },
                    columns: [
                        { data: 'created_by_label', name: 'created_by_label', defaultContent: '' },
                        { data: 'total_records', name: 'total_records', defaultContent: '' },
                        { data: 'first_created_at', name: 'first_created_at', defaultContent: '' },
                    ],
                }),
            };

            const reloadTables = () => {
                Object.values(tables).forEach((table) => {
                    table.ajax.reload(null, false);
                });
            };

            filterControls.apply.addEventListener('click', function () {
                reloadTables();
                fetchStats();
            });
            filterControls.reset.addEventListener('click', () => {
                filterControls.user.value = '';
                if (filterControls.start && filterControls.start._flatpickr) {
                    filterControls.start._flatpickr.clear();
                }
                if (filterControls.end && filterControls.end._flatpickr) {
                    filterControls.end._flatpickr.clear();
                }
                filterControls.start.value = '';
                filterControls.end.value = '';
                reloadTables();
                fetchStats();
            });

            window.addEventListener('pra-pic-tab-change', (event) => {
                const detail = event.detail;
                if (detail === 'pra') {
                    tables.praUser.columns.adjust();
                    tables.praString.columns.adjust();
                } else {
                    tables.picUser.columns.adjust();
                    tables.picString.columns.adjust();
                }
            });

            if (window.flatpickr) {
                flatpickr(filterControls.start, {
                    dateFormat: 'Y-m-d',
                    defaultDate: filterControls.start.value || null,
                });

                flatpickr(filterControls.end, {
                    dateFormat: 'Y-m-d',
                    defaultDate: filterControls.end.value || null,
                });
            }

            fetchStats();
        });
    </script>
@endsection

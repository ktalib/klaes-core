@extends('layouts.app')
@section('page-title')
    {{ __('Legal Search Reports') }}
@endsection

@section('content')
<style>
    /* ── Layout ─────────────────────────────────────── */
    .cards {
        background-color: #fff;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }

    /* ── Buttons ─────────────────────────────────────── */
    .btn {
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 0.375rem; font-weight: 500; font-size: 0.8125rem;
        line-height: 1.25rem; padding: 0.45rem 1rem;
        transition: all .18s; cursor: pointer; border: none; gap: .35rem;
    }
    .btn-primary  { background: #1d4ed8; color: #fff; }
    .btn-primary:hover { background: #1e40af; }
    .btn-outline  { background: transparent; border: 1px solid #d1d5db; color: #374151; }
    .btn-outline:hover { background: #f3f4f6; }
    .btn-sm       { padding: .25rem .6rem; font-size: .75rem; }
    .btn-icon     { padding: .45rem; width: 2.25rem; height: 2.25rem; }
    .btn:disabled { opacity: .5; cursor: not-allowed; }

    /* ── Inputs / Selects ────────────────────────────── */
    .input, .select {
        display: block; width: 100%; border-radius: .375rem;
        border: 1px solid #d1d5db; padding: .45rem .75rem;
        font-size: .8125rem; background: #fff;
    }
    .input:focus, .select:focus {
        outline: none; border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59,130,246,.25);
    }
    .select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right .5rem center; background-repeat: no-repeat;
        background-size: 1.4em 1.4em; padding-right: 2.25rem;
    }

    /* ── Badges ──────────────────────────────────────── */
    .badge {
        display: inline-flex; align-items: center; border-radius: 9999px;
        font-size: .7rem; font-weight: 600; line-height: 1;
        padding: .28rem .65rem; white-space: nowrap; letter-spacing: .02em;
    }
    .badge-found       { background: #d1fae5; color: #065f46; }
    .badge-not-found   { background: #fee2e2; color: #991b1b; }
    .badge-printed     { background: #dbeafe; color: #1e40af; }
    .badge-not-printed { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
    .badge-outline     { background: transparent; border: 1px solid #d1d5db; color: #374151; }
    .badge-default     { background: #1d4ed8; color: #fff; }
    .badge-secondary   { background: #f3f4f6; color: #374151; }
    .badge-destructive { background: #ef4444; color: #fff; }

    /* ── Tabs ────────────────────────────────────────── */
    .tabs-list {
        display: grid; grid-template-columns: repeat(3,1fr);
        background: #f1f5f9; border-radius: .375rem; padding: .2rem;
    }
    .tab-trigger {
        display: flex; align-items: center; justify-content: center; gap: .4rem;
        padding: .45rem 1rem; border-radius: .25rem; font-size: .8125rem;
        font-weight: 500; cursor: pointer; transition: all .18s;
        background: transparent; color: #6b7280; border: none;
    }
    .tab-trigger.active { background: #fff; color: #1d4ed8; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
    .tab-contents { display: none; }
    .tab-contents.active { display: block; }

    /* ── Popover ─────────────────────────────────────── */
    .popover         { position: relative; display: inline-block; }
    .popover-content {
        position: absolute; top: calc(100% + 4px); left: 0; z-index: 50;
        background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,.12); padding: 1rem;
        width: 22rem; display: none;
    }
    .popover-content.show { display: block; }

    /* ── Chart container ─────────────────────────────── */
    .chart-container { position: relative; height: 300px; width: 100%; }
    @media (min-width: 768px)  { .chart-container { height: 350px; } }
    @media (min-width: 1024px) { .chart-container { height: 400px; } }

    /* ── DataTable custom theme ──────────────────────── */
    #legal-search-logs-table_wrapper .dataTables_length,
    #legal-search-logs-table_wrapper .dataTables_filter {
        margin-bottom: .5rem;
    }
    #legal-search-logs-table_wrapper .dataTables_length select {
        border: 1px solid #d1d5db; border-radius: .375rem;
        padding: .3rem .5rem; font-size: .8125rem; margin: 0 .25rem;
    }
    #legal-search-logs-table_wrapper .dataTables_filter input {
        border: 1px solid #d1d5db; border-radius: .375rem;
        padding: .35rem .65rem; font-size: .8125rem; outline: none;
        transition: border-color .15s;
    }
    #legal-search-logs-table_wrapper .dataTables_filter input:focus {
        border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.2);
    }
    #legal-search-logs-table_wrapper .dataTables_info,
    #legal-search-logs-table_wrapper .dataTables_paginate {
        margin-top: .75rem; font-size: .8rem;
    }
    #legal-search-logs-table_wrapper .paginate_button {
        border-radius: .25rem !important; font-size: .8rem !important;
        padding: .25rem .6rem !important;
    }
    #legal-search-logs-table_wrapper .paginate_button.current {
        background: #1d4ed8 !important; border-color: #1d4ed8 !important;
        color: #fff !important;
    }

    /* ── Table cell styling ──────────────────────────── */
    #legal-search-logs-table { width: 100% !important; border-collapse: collapse; }
    #legal-search-logs-table thead th {
        background: #f8fafc; color: #374151; font-weight: 600;
        font-size: .78rem; text-transform: uppercase; letter-spacing: .04em;
        border-bottom: 2px solid #e5e7eb; padding: .75rem .85rem;
        white-space: nowrap; vertical-align: middle;
    }
    #legal-search-logs-table thead th::before,
    #legal-search-logs-table thead th::after { display: none !important; }

    #legal-search-logs-table tbody td {
        padding: .7rem .85rem; border-bottom: 1px solid #f1f5f9;
        vertical-align: middle; font-size: .8125rem; color: #1f2937;
    }
    #legal-search-logs-table tbody tr:hover td { background: #f8fafc; }
    #legal-search-logs-table tbody tr:last-child td { border-bottom: none; }

    /* Two-line cell helpers */
    .cell-primary   { font-weight: 600; color: #111827; font-size: .8125rem; line-height: 1.4; }
    .cell-secondary { font-size: .72rem; color: #6b7280; margin-top: .1rem; line-height: 1.3; }
    .cell-mono      { font-family: ui-monospace, monospace; font-size: .78rem; }

    /* SN column */
    #legal-search-logs-table tbody td:first-child {
        color: #6b7280; font-weight: 600; font-size: .78rem; text-align: center;
        width: 46px;
    }
    #legal-search-logs-table thead th:first-child { text-align: center; }

    /* Export buttons */
    .dt-buttons { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .5rem; }
    .dt-button {
        display: inline-flex; align-items: center; gap: .3rem;
        border: 1px solid #d1d5db; border-radius: .375rem;
        padding: .32rem .75rem; font-size: .78rem; font-weight: 500;
        background: #fff; color: #374151; cursor: pointer;
        transition: all .15s; line-height: 1.4;
    }
    .dt-button:hover { background: #f3f4f6; border-color: #9ca3af; }
    .dt-button.btn-copy   { background: #f0fdf4; border-color: #86efac; color: #166534; }
    .dt-button.btn-excel  { background: #f0fdf4; border-color: #4ade80; color: #15803d; }
    .dt-button.btn-csv    { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
    .dt-button.btn-pdf    { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
    .dt-button.btn-print  { background: #fafafa; border-color: #d1d5db; color: #374151; }
    .dt-button:hover.btn-copy  { background: #dcfce7; }
    .dt-button:hover.btn-excel { background: #dcfce7; }
    .dt-button:hover.btn-csv   { background: #dbeafe; }
    .dt-button:hover.btn-pdf   { background: #fee2e2; }

    /* dt-header layout */
    .dt-toolbar {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: .5rem; margin-bottom: .75rem;
    }
    .dt-toolbar-left  { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
    .dt-toolbar-right { display: flex; align-items: center; gap: .35rem; }

    /* Action button */
    .btn-action {
        display: inline-flex; align-items: center; gap: .25rem;
        font-size: .74rem; font-weight: 500; padding: .28rem .65rem;
        border-radius: .3rem; border: 1px solid #e5e7eb;
        background: #fff; color: #374151; cursor: pointer;
        transition: all .15s; white-space: nowrap;
    }
    .btn-action:hover { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }

    /* Responsive */
    @media (min-width: 768px)  {
        .md\:flex-row { flex-direction: row; }
        .md\:items-center { align-items: center; }
        .md\:grid-cols-2 { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .md\:grid-cols-3 { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .md\:gap-6       { gap: 1.5rem; }
        .md\:col-span-2  { grid-column: span 2/span 2; }
    }
    @media (min-width: 1024px) {
        .lg\:grid-cols-2 { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .lg\:grid-cols-4 { grid-template-columns: repeat(4,minmax(0,1fr)); }
        .lg\:col-span-2  { grid-column: span 2/span 2; }
    }

    @media print {
        .dt-buttons, .dataTables_filter, .dataTables_length,
        .dataTables_paginate, .dataTables_info { display: none !important; }
        #legal-search-logs-table { font-size: 10px; }
    }
</style>

<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">

        {{-- CDN Assets --}}
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Legal Search Reports</h2>
                <p class="text-sm text-gray-500 mt-0.5">Search logs and analytics for the selected period</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- Filters Popover --}}
                <div class="popover">
                    <button id="filters-btn" class="btn btn-outline">
                        <i data-lucide="filter" class="h-4 w-4"></i>
                        Filters
                        <i data-lucide="chevron-down" class="h-4 w-4"></i>
                    </button>
                    <div id="filters-popover" class="popover-content">
                        <div class="space-y-4">
                            <h3 class="font-semibold text-sm text-gray-800">Filter Reports</h3>
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-gray-600">Date Range</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input id="date-from" type="date" class="input" value="2023-01-01">
                                    <input id="date-to"   type="date" class="input" value="2023-12-31">
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-gray-600">Report Period</label>
                                <select id="filter-period" class="select">
                                    <option value="day">Daily</option>
                                    <option value="week">Weekly</option>
                                    <option value="month" selected>Monthly</option>
                                    <option value="quarter">Quarterly</option>
                                    <option value="year">Yearly</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-gray-600">Payment Status</label>
                                <select id="filter-payment" class="select">
                                    <option value="all" selected>All Statuses</option>
                                    <option value="paid">Paid</option>
                                    <option value="pending">Pending</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-gray-600">Search Type</label>
                                <select id="filter-search-type" class="select">
                                    <option value="all" selected>All Types</option>
                                    <option value="commercial">Commercial</option>
                                    <option value="official">Official</option>
                                    <option value="online">Online</option>
                                </select>
                            </div>
                            <button id="apply-filters-btn" class="btn btn-primary w-full">Apply Filters</button>
                        </div>
                    </div>
                </div>

                <select id="report-period" class="select" style="width:170px;">
                    <option value="day">Daily</option>
                    <option value="week">Weekly</option>
                    <option value="month" selected>Monthly</option>
                    <option value="quarter">Quarterly</option>
                    <option value="year">Yearly</option>
                    <option value="custom">Custom Range</option>
                </select>

                <div class="flex items-center gap-1">
                    <button id="print-btn" class="btn btn-outline btn-icon" title="Print">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- Stats cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 md:gap-4 mb-5">
            <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm ring-1 ring-emerald-100 bg-emerald-50/10">
                <p class="text-[10px] md:text-xs font-semibold text-emerald-600 uppercase tracking-wider">Searches Today</p>
                <p class="text-2xl md:text-3xl font-bold text-emerald-700 mt-1.5">{{ $totalToday ?? 0 }}</p>
            </div>

            <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-[10px] md:text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Searches</p>
                <p class="text-2xl md:text-3xl font-bold text-slate-900 mt-1.5">{{ $total ?? 0 }}</p>
            </div>
            
            <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-[10px] md:text-xs font-semibold text-blue-600 uppercase tracking-wider">Found Results</p>
                <p class="text-2xl md:text-3xl font-bold text-blue-700 mt-1.5">{{ $foundCount ?? 0 }}</p>
            </div>

            <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-[10px] md:text-xs font-semibold text-rose-600 uppercase tracking-wider">Not Found</p>
                <p class="text-2xl md:text-3xl font-bold text-rose-700 mt-1.5">{{ $notFoundCount ?? 0 }}</p>
            </div>

            <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-[10px] md:text-xs font-semibold text-purple-600 uppercase tracking-wider">Official Prints</p>
                <p class="text-2xl md:text-3xl font-bold text-purple-700 mt-1.5">{{ $officialCount ?? 0 }}</p>
            </div>

            <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-[10px] md:text-xs font-semibold text-amber-600 uppercase tracking-wider">On-Premise Prints</p>
                <p class="text-2xl md:text-3xl font-bold text-amber-700 mt-1.5">{{ $onPremiseCount ?? 0 }}</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="w-full">
            <div class="tabs-list mb-5">
                <button class="tab-trigger active" data-tab="table">
                    <i data-lucide="table" class="h-4 w-4"></i>
                    Detailed View
                </button>
                <button class="tab-trigger" data-tab="summary">
                    <i data-lucide="file-text" class="h-4 w-4"></i>
                    Summary View
                </button>
                <button class="tab-trigger" data-tab="charts">
                    <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                    Charts View
                </button>
            </div>

            {{-- ── TABLE TAB ── --}}
            <div id="table-tab" class="tab-contents active w-full">
                <div class="cards w-full">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Legal Search Transactions</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Detailed view of all legal search transactions for the selected period.</p>
                        </div>
                        {{-- Export buttons rendered by DataTables go into #dt-export-btns --}}
                        <div id="dt-export-btns" class="dt-buttons"></div>
                    </div>
                    <div class="p-5 w-full overflow-x-auto">
                        <table id="legal-search-logs-table" class="min-w-full" style="width:100%">
                            <thead>
                                <tr>
                                    <th>S/N</th>
                                    <th>Date</th>
                                    <th>Search Parameter</th>
                                    <th>Source</th>
                                    <th>Search Value</th>
                                    <th>Result</th>
                                    <th>LGA</th>
                                    <th>Receipt No.</th>
                                    <th>Staff</th>
                                    <th>Printed</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="transactions-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ── SUMMARY TAB ── --}}
            <div id="summary-tab" class="tab-contents mt-1 w-full overflow-x-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5 mb-5">
                    <div class="cards">
                        <div class="px-5 pt-5 pb-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Searches Conducted</p>
                        </div>
                        <div class="px-5 pb-5">
                            <div id="total-searches" class="text-3xl font-bold text-gray-900">0</div>
                            <p class="text-xs text-gray-500 mt-1">Total search requests processed</p>
                        </div>
                    </div>
                    <div class="cards">
                        <div class="px-5 pt-5 pb-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Search Revenue</p>
                        </div>
                        <div class="px-5 pb-5">
                            <div id="total-revenue" class="text-3xl font-bold text-gray-900">₦0</div>
                            <p class="text-xs text-gray-500 mt-1">From completed searches</p>
                        </div>
                    </div>
                    <div class="cards">
                        <div class="px-5 pt-5 pb-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Status Distribution</p>
                        </div>
                        <div class="px-5 pb-5">
                            <div id="status-distribution" class="flex flex-wrap items-center gap-3 mt-1"></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5 mb-5 w-full">
                    <div class="cards md:col-span-2 lg:col-span-2">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Search Results</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Distribution of search results</p>
                        </div>
                        <div class="px-5 py-5">
                            <div id="results-distribution" class="flex flex-wrap items-center justify-center gap-8 py-2"></div>
                        </div>
                    </div>
                    <div class="cards md:col-span-2 lg:col-span-2">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Search Efficiency</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Key performance metrics</p>
                        </div>
                        <div class="px-5 py-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center">
                                    <div id="avg-revenue" class="text-3xl font-bold text-gray-900 mb-1">₦0</div>
                                    <div class="text-sm font-medium text-gray-700">Average Revenue</div>
                                    <div class="text-xs text-gray-400 mt-0.5">Per search</div>
                                </div>
                                <div class="text-center">
                                    <div id="success-rate" class="text-3xl font-bold text-gray-900 mb-1">0%</div>
                                    <div class="text-sm font-medium text-gray-700">Success Rate</div>
                                    <div class="text-xs text-gray-400 mt-0.5">Records found</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-5">
                    <div class="cards w-full">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Search Parameters</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Distribution of search parameter types</p>
                        </div>
                        <div class="px-5 py-4 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead><tr class="border-b border-gray-100">
                                    <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">Parameter</th>
                                    <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">Count</th>
                                    <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">%</th>
                                </tr></thead>
                                <tbody id="parameters-table-body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="cards w-full">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Searches by LGA</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Distribution by Local Government Area</p>
                        </div>
                        <div class="px-5 py-4 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead><tr class="border-b border-gray-100">
                                    <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">LGA</th>
                                    <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">Count</th>
                                    <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">%</th>
                                </tr></thead>
                                <tbody id="lga-table-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── CHARTS TAB ── --}}
            <div id="charts-tab" class="tab-contents mt-1 w-full">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-5">
                    <div class="cards">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Searches by LGA</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Distribution of searches by Local Government Area</p>
                        </div>
                        <div class="p-5"><div class="chart-container"><canvas id="lga-chart"></canvas></div></div>
                    </div>
                    <div class="cards">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Search Parameters Distribution</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Pie chart of search parameter types</p>
                        </div>
                        <div class="p-5"><div class="chart-container"><canvas id="parameters-chart"></canvas></div></div>
                    </div>
                    <div class="cards lg:col-span-2">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Monthly Trends</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Monthly search volume and revenue trends</p>
                        </div>
                        <div class="p-5"><div class="chart-container"><canvas id="trends-chart"></canvas></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>

<script>
lucide.createIcons();

/* ── Sample data (used by Summary & Charts) ── */
const sampleReportData = [
    { id:"1", date:"2023-01-15", searchType:"Commercial", searchParameter:"File Number", searchValue:"CON-RES-2018-242", result:"Found",     amount:"5000", paymentStatus:"Paid",    paymentMethod:"Bank Transfer", receiptNumber:"RCP001", staff:"Musa Ali",     lga:"Kano Municipal" },
    { id:"2", date:"2023-01-16", searchType:"Official",   searchParameter:"Plot Number", searchValue:"GP No. 1067/1",      result:"Found",     amount:"3000", paymentStatus:"Paid",    paymentMethod:"Cash",          receiptNumber:"RCP002", staff:"Jane Smith",   lga:"Fagge"          },
    { id:"3", date:"2023-01-17", searchType:"Online",     searchParameter:"Owner Name",  searchValue:"Alhaji Musa Ibrahim", result:"Not Found", amount:"2500", paymentStatus:"Pending", paymentMethod:"Online",        receiptNumber:"RCP003", staff:"Mike Johnson", lga:"Gwale"          },
    { id:"4", date:"2023-01-18", searchType:"Commercial", searchParameter:"KANGIS Number",searchValue:"KNML 08146",         result:"Found",     amount:"4500", paymentStatus:"Paid",    paymentMethod:"POS",           receiptNumber:"RCP004", staff:"Sarah Wilson", lga:"Tarauni"        },
    { id:"5", date:"2023-01-19", searchType:"Official",   searchParameter:"Plan Number", searchValue:"LKN/RES/2021/3006",  result:"Found",     amount:"3500", paymentStatus:"Refunded",paymentMethod:"Bank Transfer", receiptNumber:"RCP005", staff:"David Brown",  lga:"Nassarawa"      }
];
const sampleMonthlyData = [
    { month:"Jan", searches:45, revenue:225000 }, { month:"Feb", searches:52, revenue:260000 },
    { month:"Mar", searches:38, revenue:190000 }, { month:"Apr", searches:61, revenue:305000 },
    { month:"May", searches:49, revenue:245000 }, { month:"Jun", searches:55, revenue:275000 },
    { month:"Jul", searches:43, revenue:215000 }, { month:"Aug", searches:58, revenue:290000 },
    { month:"Sep", searches:47, revenue:235000 }, { month:"Oct", searches:53, revenue:265000 },
    { month:"Nov", searches:41, revenue:205000 }, { month:"Dec", searches:48, revenue:240000 }
];

let currentTab = 'table';
let reportData = sampleReportData;
let monthlyData = sampleMonthlyData;
let charts = {};
let dtTable = null;

/* ── Render date as two lines (date + time) ── */
function renderDateCell(raw) {
    if (!raw) return '<span class="cell-secondary">—</span>';
    // Expected: "24 May, 2026 01:45 PM"
    // Split on the time pattern (digits:digits space AM/PM)
    const m = raw.match(/^(.+?)\s+(\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?)$/i);
    if (m) {
        return `<div class="cell-primary">${m[1].trim()}</div><div class="cell-secondary">${m[2].trim()}</div>`;
    }
    return `<div class="cell-primary">${raw}</div>`;
}

/* ── Render search value as two lines (type label + value) ── */
function renderSearchValueCell(value, param) {
    if (!value) return '<span class="cell-secondary">—</span>';
    const label = param ? `<div class="cell-secondary">${param}</div>` : '';
    return `<div class="cell-primary cell-mono">${value}</div>${label}`;
}

/* ── Render staff as two lines ── */
function renderStaffCell(staff) {
    if (!staff) return '<span class="cell-secondary">—</span>';
    // Split on last space to get first/last name on separate lines if multi-word
    const parts = staff.trim().split(' ');
    if (parts.length >= 2) {
        const first = parts.slice(0, -1).join(' ');
        const last  = parts[parts.length - 1];
        return `<div class="cell-primary">${first}</div><div class="cell-secondary">${last}</div>`;
    }
    return `<div class="cell-primary">${staff}</div>`;
}

/* ── DataTable init ── */
function initDataTable() {
    if (dtTable) { dtTable.destroy(); dtTable = null; }

    dtTable = $('#legal-search-logs-table').DataTable({
        processing: true,
        serverSide: true,
        ordering: false,
        ajax: {
            url: '{{ route("legalsearchreports.data") }}',
            data: function(d) {
                d.order = [{ column: 1, dir: 'desc' }];
                if (d.columns && d.columns[1]) {
                    d.columns[1].orderable = true;
                }
            }
        },
        columns: [
            {
                data: 'DT_RowIndex', name: 'DT_RowIndex',
                orderable: false, searchable: false,
                className: 'text-center'
            },
            {
                data: 'date', name: 'created_at',
                render: function(data) { return renderDateCell(data); }
            },
            {
                data: 'search_parameter', name: 'search_parameter',
                render: function(data) {
                    return data
                        ? `<span class="badge badge-outline">${data}</span>`
                        : '<span class="cell-secondary">—</span>';
                }
            },
            {
                data: 'source_display', name: 'search_source',
                orderable: false, searchable: false,
                render: function(data) { return data || '—'; }
            },
            {
                data: 'search_value', name: 'search_value',
                render: function(data, type, row) {
                    if (type !== 'display') return data;
                    return `<div class="cell-primary cell-mono">${data || '—'}</div>`;
                }
            },
            {
                data: 'result_display', name: 'result_status',
                render: function(data) { return data || '—'; }
            },
            {
                data: 'lga', name: 'lga',
                render: function(data) {
                    return data ? `<div class="cell-primary">${data}</div>` : '<span class="cell-secondary">—</span>';
                }
            },
            {
                data: 'receipt_no', name: 'receipt_no',
                render: function(data) {
                    return data
                        ? `<div class="cell-primary cell-mono">${data}</div>`
                        : '<span class="cell-secondary">—</span>';
                }
            },
            {
                data: 'staff', name: 'user.first_name',
                render: function(data) { return renderStaffCell(data); }
            },
            {
                data: 'printed_status', name: 'printed',
                render: function(data) { return data || '—'; }
            },
            {
                data: 'action', name: 'action',
                orderable: false, searchable: false
            }
        ],
        order: [[1, 'desc']],
        pageLength: 25,
        responsive: true,
        dom: 'rt<"dt-footer flex items-center justify-between flex-wrap gap-2 mt-3"ip>',
        buttons: [
            {
                extend: 'copyHtml5',
                text: '<svg xmlns="http://www.w3.org/2000/svg" class="inline-block mr-1" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copy',
                className: 'btn-copy',
                exportOptions: { columns: ':not(:last-child)' }
            },
            {
                extend: 'excelHtml5',
                text: '<svg xmlns="http://www.w3.org/2000/svg" class="inline-block mr-1" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Excel',
                className: 'btn-excel',
                title: 'Legal Search Reports',
                exportOptions: { columns: ':not(:last-child)', stripHtml: true }
            },
            {
                extend: 'csvHtml5',
                text: '<svg xmlns="http://www.w3.org/2000/svg" class="inline-block mr-1" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>CSV',
                className: 'btn-csv',
                title: 'Legal Search Reports',
                exportOptions: { columns: ':not(:last-child)', stripHtml: true }
            },
            {
                extend: 'pdfHtml5',
                text: '<svg xmlns="http://www.w3.org/2000/svg" class="inline-block mr-1" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>PDF',
                className: 'btn-pdf',
                title: 'Legal Search Reports',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':not(:last-child)', stripHtml: true }
            },
            {
                extend: 'print',
                text: '<svg xmlns="http://www.w3.org/2000/svg" class="inline-block mr-1" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Print',
                className: 'btn-print',
                title: 'Legal Search Reports',
                exportOptions: { columns: ':not(:last-child)', stripHtml: true }
            }
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search records…',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_–_END_ of _TOTAL_ records',
            infoEmpty: 'No records found',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: '<div class="text-sm text-gray-500 py-4">Loading…</div>'
        },
        initComplete: function() {
            // Move buttons into the card header slot
            const btns = this.api().buttons().container();
            $('#dt-export-btns').append(btns);

            // Build custom toolbar above the table
            const wrapper = $('#legal-search-logs-table_wrapper');
            const lengthEl  = wrapper.find('.dataTables_length').detach();
            const filterEl  = wrapper.find('.dataTables_filter').detach();

            const toolbar = $(`
                <div class="dt-toolbar">
                    <div class="dt-toolbar-left" id="dt-length-slot"></div>
                    <div class="dt-toolbar-right" id="dt-filter-slot"></div>
                </div>
            `);
            wrapper.find('table').before(toolbar);
            $('#dt-length-slot').append(lengthEl);
            $('#dt-filter-slot').append(filterEl);
        }
    });
}

/* ── Summary helpers ── */
function calculateStatistics(data) {
    const totalSearches = data.length;
    const totalRevenue  = data.reduce((s,i) => i.paymentStatus === 'Paid' ? s + parseInt(i.amount) : s, 0);
    const countBy = (key) => data.reduce((c,i) => { c[i[key]] = (c[i[key]] || 0) + 1; return c; }, {});
    return {
        totalSearches,
        totalRevenue,
        paymentStatusCounts:    countBy('paymentStatus'),
        searchParameterCounts:  countBy('searchParameter'),
        lgaCounts:              countBy('lga'),
        resultCounts:           countBy('result')
    };
}

function renderSummaryView(stats) {
    document.getElementById('total-searches').textContent = stats.totalSearches;
    document.getElementById('total-revenue').textContent  = `₦${stats.totalRevenue.toLocaleString()}`;

    // Status distribution
    const sd = document.getElementById('status-distribution');
    sd.innerHTML = '';
    Object.entries(stats.paymentStatusCounts).forEach(([s, c]) => {
        sd.insertAdjacentHTML('beforeend', `
            <div class="flex items-center gap-1.5">
                <span class="inline-block w-2 h-2 rounded-full ${s==='Paid'?'bg-blue-600':s==='Pending'?'bg-yellow-500':'bg-red-500'}"></span>
                <span class="text-sm text-gray-700">${s}: <strong>${c}</strong></span>
            </div>`);
    });

    // Results distribution
    const rd = document.getElementById('results-distribution');
    rd.innerHTML = '';
    Object.entries(stats.resultCounts).forEach(([r, c]) => {
        rd.insertAdjacentHTML('beforeend', `
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-900 mb-1">${c}</div>
                <span class="badge ${r==='Found'?'badge-found':'badge-not-found'}">${r}</span>
                <div class="text-xs text-gray-400 mt-1">${((c/stats.totalSearches)*100).toFixed(1)}%</div>
            </div>`);
    });

    const avgRev = stats.totalRevenue / stats.totalSearches;
    const succRate = ((stats.resultCounts['Found'] || 0) / stats.totalSearches) * 100;
    document.getElementById('avg-revenue').textContent  = `₦${Math.round(avgRev).toLocaleString()}`;
    document.getElementById('success-rate').textContent = `${succRate.toFixed(1)}%`;

    // Parameters table
    const ptb = document.getElementById('parameters-table-body');
    ptb.innerHTML = '';
    Object.entries(stats.searchParameterCounts).forEach(([p, c]) => {
        ptb.insertAdjacentHTML('beforeend', `
            <tr class="border-b border-gray-50 hover:bg-gray-50">
                <td class="py-2 text-sm text-gray-800">${p}</td>
                <td class="py-2 text-sm font-semibold">${c}</td>
                <td class="py-2 text-xs text-gray-500">${((c/stats.totalSearches)*100).toFixed(1)}%</td>
            </tr>`);
    });

    // LGA table
    const ltb = document.getElementById('lga-table-body');
    ltb.innerHTML = '';
    Object.entries(stats.lgaCounts).forEach(([l, c]) => {
        ltb.insertAdjacentHTML('beforeend', `
            <tr class="border-b border-gray-50 hover:bg-gray-50">
                <td class="py-2 text-sm text-gray-800">${l}</td>
                <td class="py-2 text-sm font-semibold">${c}</td>
                <td class="py-2 text-xs text-gray-500">${((c/stats.totalSearches)*100).toFixed(1)}%</td>
            </tr>`);
    });
}

function createCharts(stats) {
    Object.values(charts).forEach(ch => ch && ch.destroy());
    charts = {};

    charts.lga = new Chart(document.getElementById('lga-chart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: Object.keys(stats.lgaCounts),
            datasets: [{ label: 'Searches', data: Object.values(stats.lgaCounts),
                backgroundColor: 'rgba(29,78,216,.75)', borderColor: 'rgba(29,78,216,1)', borderWidth: 1 }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });

    charts.params = new Chart(document.getElementById('parameters-chart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: Object.keys(stats.searchParameterCounts),
            datasets: [{ data: Object.values(stats.searchParameterCounts),
                backgroundColor: ['rgba(29,78,216,.8)','rgba(16,185,129,.8)','rgba(245,158,11,.8)','rgba(239,68,68,.8)','rgba(139,92,246,.8)'],
                borderWidth: 1 }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    charts.trends = new Chart(document.getElementById('trends-chart').getContext('2d'), {
        type: 'line',
        data: {
            labels: monthlyData.map(d => d.month),
            datasets: [
                { label: 'Searches', data: monthlyData.map(d => d.searches),
                  borderColor: 'rgba(29,78,216,1)', backgroundColor: 'rgba(29,78,216,.1)', tension: .3, yAxisID: 'y' },
                { label: "Revenue (₦'000)", data: monthlyData.map(d => d.revenue/1000),
                  borderColor: 'rgba(239,68,68,1)', backgroundColor: 'rgba(239,68,68,.1)', tension: .3, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y:  { type:'linear', position:'left',  title:{ display:true, text:'Searches' } },
                y1: { type:'linear', position:'right', title:{ display:true, text:"Revenue (₦'000)" }, grid:{ drawOnChartArea:false } }
            }
        }
    });
}

/* ── Tab switching ── */
function switchTab(name) {
    document.querySelectorAll('.tab-trigger').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === name);
    });
    document.querySelectorAll('.tab-contents').forEach(c => {
        c.classList.toggle('active', c.id === `${name}-tab`);
    });
    currentTab = name;
    if (name === 'charts') setTimeout(() => createCharts(calculateStatistics(reportData)), 120);
}

/* ── Event listeners ── */
document.getElementById('filters-btn').addEventListener('click', () =>
    document.getElementById('filters-popover').classList.toggle('show'));
document.addEventListener('click', e => {
    if (!e.target.closest('#filters-btn') && !e.target.closest('#filters-popover'))
        document.getElementById('filters-popover').classList.remove('show');
});
document.getElementById('print-btn').addEventListener('click', () => window.print());
document.querySelectorAll('.tab-trigger').forEach(t =>
    t.addEventListener('click', () => switchTab(t.dataset.tab)));

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function () {
    initDataTable();
    renderSummaryView(calculateStatistics(reportData));
    lucide.createIcons();
});
</script>

@endsection

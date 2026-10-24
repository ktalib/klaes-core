@extends('layouts.app')

@section('page-title')
    {{ __('Moved Transactions') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header')

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-xs font-semibold text-red-600 uppercase tracking-widest">File Indexing</p>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mt-1">Moved Transactions</h1>
                        <p class="text-sm text-slate-500 mt-2 max-w-3xl">
                            Transactions taken off an indexed file because they are not in that physical file or were
                            entered wrongly. Each was given a system temp file no and deleted from its PRA, File History
                            or CofO table &mdash; the stored snapshot is the only remaining copy.
                        </p>
                    </div>
                    <a href="{{ route('indexed-files.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-600 hover:text-blue-600 hover:border-blue-200 transition-all shadow-sm">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Indexed Files
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-wrap items-center gap-3 justify-between">
                    <div class="relative flex-1 min-w-[16rem]">
                        <i data-lucide="search" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="mt-search" placeholder="Search temp file no, file number, transaction, parties, reason, who moved it…"
                               class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-300">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rows</label>
                        <select id="mt-per-page"
                                class="text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:outline-none focus:ring-4 focus:ring-blue-500/10">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="mt-table">
                        <thead class="bg-slate-50/80 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide whitespace-nowrap">S/N</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="temp_file_no">Temp File No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="file_number">Moved From</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="source_table">Source</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="transaction_type">Transaction</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide whitespace-nowrap">Parties</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="transaction_date">Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide whitespace-nowrap">Reg. No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="moved_by">Moved By</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="moved_at">Moved At</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 text-xs uppercase tracking-wide whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="mt-tbody" class="divide-y divide-slate-50"></tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500" id="mt-summary">&nbsp;</p>
                    <div class="flex items-center gap-2">
                        <button type="button" id="mt-prev"
                                class="px-3 py-2 text-sm rounded-xl border border-slate-200 text-slate-600 hover:border-blue-200 hover:text-blue-600 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                            Previous
                        </button>
                        <span class="text-xs text-slate-500" id="mt-page">Page 1</span>
                        <button type="button" id="mt-next"
                                class="px-3 py-2 text-sm rounded-xl border border-slate-200 text-slate-600 hover:border-blue-200 hover:text-blue-600 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Snapshot details -->
    <div id="mt-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[88vh] flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold text-red-500 uppercase tracking-widest">Moved Transaction Snapshot</p>
                    <h3 class="text-xl font-bold text-slate-900 mt-1" id="mt-modal-title">—</h3>
                    <p class="text-xs text-slate-500 mt-1" id="mt-modal-subtitle"></p>
                </div>
                <button type="button" id="mt-modal-close"
                        class="p-2 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 transition-all">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="p-5 overflow-y-auto space-y-5" id="mt-modal-body"></div>
        </div>
    </div>

    <script>
        window.movedTransactionsConfig = {
            listUrl: @json(route('moved-transactions.api.list')),
            showUrlTemplate: @json(route('moved-transactions.api.show', ['id' => '__ID__'])),
        };
    </script>
    <script src="{{ asset('js/moved-transactions/index.js') }}?v={{ filemtime(public_path('js/moved-transactions/index.js')) }}"></script>
@endsection

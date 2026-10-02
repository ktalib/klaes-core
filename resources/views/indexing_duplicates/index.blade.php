@extends('layouts.app')

@section('page-title')
    {{ __('Indexing Duplicates') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header')

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-xs font-semibold text-rose-600 uppercase tracking-widest">File Indexing</p>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mt-1">Indexing Duplicates</h1>
                        <p class="text-sm text-slate-500 mt-2 max-w-3xl">
                            Indexed files found to be duplicates and removed from the live tables. These records no
                            longer exist in the indexing, file number, customer or entity tables &mdash; this page and
                            its stored snapshot are the only remaining record of them.
                        </p>
                    </div>
                    <a href="{{ route('indexed-files.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-600 hover:text-blue-600 hover:border-blue-200 transition-all shadow-sm">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Indexed Files
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Total Moved</p>
                    <h3 class="text-3xl font-bold text-slate-900 mt-2" id="stat-total">--</h3>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Moved Today</p>
                    <h3 class="text-3xl font-bold text-rose-600 mt-2" id="stat-today">--</h3>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Registries</p>
                    <h3 class="text-3xl font-bold text-slate-900 mt-2" id="stat-registries">--</h3>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest"
                       title="A commissioning row was found in mls_file_no and deliberately left in place">
                        Commissioning Kept
                    </p>
                    <h3 class="text-3xl font-bold text-amber-600 mt-2" id="stat-mls">--</h3>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-wrap items-center gap-3 justify-between">
                    <div class="relative flex-1 min-w-[16rem]">
                        <i data-lucide="search" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="dup-search" placeholder="Search file number, title, duplicate of, reason, who moved it…"
                               class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-300">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rows</label>
                        <select id="dup-per-page"
                                class="text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:outline-none focus:ring-4 focus:ring-blue-500/10">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="dup-table">
                        <thead class="bg-slate-50/80 border-b border-slate-100">
                            {{--
                                Dropped columns (Kangis/Newkangis/Mls FileNo, TP, LPKN, District, LGA,
                                Duplicate Of, Reason, Removed From, Moved At) are still stored on the
                                record — they show in the Snapshot modal.
                            --}}
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide whitespace-nowrap">S/N</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="file_indexing_id">Indexing ID</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="file_number">File No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="file_title">File Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="land_use_type">Land Use</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="plot_number">Plot No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="indexed_by">Indexed By</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="indexed_at">Indexed Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="moved_by">Moved By</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 text-xs uppercase tracking-wide whitespace-nowrap">Details</th>
                            </tr>
                        </thead>
                        <tbody id="dup-tbody" class="divide-y divide-slate-50"></tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500" id="dup-summary">&nbsp;</p>
                    <div class="flex items-center gap-2">
                        <button type="button" id="dup-prev"
                                class="px-3 py-2 text-sm rounded-xl border border-slate-200 text-slate-600 hover:border-blue-200 hover:text-blue-600 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                            Previous
                        </button>
                        <span class="text-xs text-slate-500" id="dup-page">Page 1</span>
                        <button type="button" id="dup-next"
                                class="px-3 py-2 text-sm rounded-xl border border-slate-200 text-slate-600 hover:border-blue-200 hover:text-blue-600 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Snapshot details -->
    <div id="dup-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[88vh] flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold text-rose-500 uppercase tracking-widest">Deleted Record Snapshot</p>
                    <h3 class="text-xl font-bold text-slate-900 mt-1" id="dup-modal-title">—</h3>
                    <p class="text-xs text-slate-500 mt-1" id="dup-modal-subtitle"></p>
                </div>
                <button type="button" id="dup-modal-close"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="p-5 overflow-y-auto space-y-5" id="dup-modal-body"></div>
        </div>
    </div>

    <script>
        window.indexingDuplicatesConfig = {
            listUrl: @json(route('indexing-duplicates.api.list')),
            statsUrl: @json(route('indexing-duplicates.api.stats')),
            showUrlTemplate: @json(route('indexing-duplicates.api.show', ['id' => '__ID__'])),
        };
    </script>
    <script src="{{ asset('js/indexing-duplicates/index.js') }}?v={{ filemtime(public_path('js/indexing-duplicates/index.js')) }}"></script>
@endsection

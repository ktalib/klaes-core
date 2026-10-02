@extends('layouts.app')

@section('page-title')
    {{ __('Indexed Files') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header')

        @php
            $user = auth()->user();

            /*
             | isSuperAdmin() replaces the hand-rolled pair of checks that used to live here:
             | an exact in_array('Supper Admin', ...) against the raw assign_role CSV, plus a
             | separate lowercase test on users.type. Between them they missed the
             | 'Super Admin' spelling entirely, so some administrator accounts silently lost
             | the destructive menu items. The model predicate covers every form.
             */
            $hasSuperRole = $user && $user->isSuperAdmin();
            $canIndexFiles = $hasSuperRole || ($user && $user->canDo('Indexing', 'create'));
        @endphp

        <div class="p-6 space-y-8 max-w-7xl mx-auto">
            <!-- Hero -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 lg:p-8 space-y-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div>
                        <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest">Indexed Files</p>
                        <h1 class="text-3xl md:text-4xl font-bold text-slate-900 mt-1">Indexed Files Catalog</h1>
                        <p class="text-base text-slate-500 mt-2 max-w-2xl">
                            40,000+ records with lightning-fast server-side pagination. Search, audit and manage indexed
                            files without leaving this workspace.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" onclick="window.location.reload()"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            Refresh view
                        </button>


                        @canDo('Indexing', 'create')
                        <a href="{{ route('fileindexing.create') }}"
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow hover:bg-blue-700 transition"
                            title="Index a new file">
                            <i data-lucide="folder-plus" class="h-5 w-5"></i>
                            Index New File
                        </a>
                        @endcanDo


                    </div>
                </div>

 
            </div>

            <!-- Stats Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="indexed-stats-cards">
                <div
                    class="p-6 rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50/80 via-white to-slate-50 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide">Total Indexed</p>
                            <p class="text-4xl font-bold text-blue-700 mt-2" id="stats-total-indexed">--</p>
                            <p class="text-xs text-blue-500 mt-2">All-time records</p>
                        </div>
                        <div class="p-4 bg-white/60 rounded-2xl border border-blue-100">
                            <i data-lucide="database" class="h-7 w-7 text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div
                    class="p-6 rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50/70 via-white to-slate-50 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Today's Activity</p>
                            <p class="text-4xl font-bold text-emerald-700 mt-2" id="stats-indexed-today">--</p>
                            <p class="text-xs text-emerald-500 mt-2">Last 24 hours</p>
                        </div>
                        <div class="p-4 bg-white/60 rounded-2xl border border-emerald-100">
                            <i data-lucide="zap" class="h-7 w-7 text-emerald-600"></i>
                        </div>
                    </div>
                </div>

                <div
                    class="p-6 rounded-2xl border border-purple-100 bg-gradient-to-br from-purple-50/70 via-white to-slate-50 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-purple-600 uppercase tracking-wide">Active Registries</p>
                            <p class="text-4xl font-bold text-purple-700 mt-2" id="stats-registries">--</p>
                            <p class="text-xs text-purple-500 mt-2">With indexed activity</p>
                        </div>
                        <div class="p-4 bg-white/60 rounded-2xl border border-purple-100">
                            <i data-lucide="layers" class="h-7 w-7 text-purple-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workspace Tabs , -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 pt-6">
                    <div class="flex flex-wrap gap-3 text-sm font-semibold text-slate-500">
                        <button type="button" data-indexed-tab="unindexed"
                            class="indexed-tab-btn indexed-tab-active px-4 py-2 rounded-2xl border border-blue-500 bg-blue-50 text-blue-700">Unindexed
                            Files</button>
                        <button type="button" data-indexed-tab="digital"
                            class="indexed-tab-btn px-4 py-2 rounded-2xl border border-transparent bg-slate-50 text-slate-600 opacity-50 cursor-not-allowed pointer-events-none"
                            disabled aria-disabled="true">Digital Index (AI)</button>
                        <button type="button" data-indexed-tab="indexed"
                            class="indexed-tab-btn px-4 py-2 rounded-2xl border border-transparent bg-slate-50 text-slate-600 hover:text-slate-900">Indexed
                            Files</button>
                        <button type="button" data-indexed-tab="view-indexing"
                            class="indexed-tab-btn px-4 py-2 rounded-2xl border border-transparent bg-slate-50 text-slate-600 hover:text-slate-900">
                            File Indexing View</button>
                    </div>
                </div>

                <div class="mt-4 border-t border-slate-100">
                    <div data-indexed-tab-panel="unindexed" class="px-2 pb-6">
                        @include('fileindexing.partials.unindexed_pending_panel')
                    </div>

                    <div data-indexed-tab-panel="digital" class="hidden px-2 pb-6">
                        @include('fileindexing.partials.digital_index_content')
                    </div>

                    <div data-indexed-tab-panel="indexed" class="hidden px-2 pb-6">
                        <!-- Search and Per-Page Controls -->
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] mb-4">
                            <div class="relative">
                                <label for="indexed-files-search"
                                    class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Global
                                    search</label>
                                <div class="mt-2">
                                    <i data-lucide="search"
                                        class="absolute left-4 top-[58%] transform -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none"></i>
                                    <input type="search" id="indexed-files-search"
                                        placeholder="Search file number, registry, land use..."
                                        class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 bg-slate-50/60 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                                        autocomplete="off" spellcheck="false">
                                </div>
                                <p class="text-xs text-slate-400 mt-1">Instant filtering across every column. Press <kbd
                                        class="px-1.5 py-0.5 bg-slate-100 rounded">Enter</kbd> to apply.</p>
                            </div>
                            <div
                                class="bg-slate-50/80 border border-slate-100 rounded-2xl px-4 py-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Records per page
                                    </p>
                                    <p class="text-sm text-slate-500">Choose how many results to load at once</p>
                                </div>
                                <select id="indexed-files-per-page"
                                    class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                    <option value="20">20</option>
                                    <option value="40">40</option>
                                    <option value="60">60</option>
                                    <option value="80">80</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>


                        {{-- Advanced Search: per-column narrowing on top of the global box above.
                             Every control carries data-adv-param, which is the exact query
                             string key the list endpoint reads, so adding a field here needs
                             no matching change in the JS. --}}
                        <div class="mb-4" id="advanced-search">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" id="adv-search-toggle"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:border-blue-300 transition"
                                    aria-expanded="false" aria-controls="adv-search-panel">
                                    <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                                    Advanced search
                                    <span id="adv-active-count"
                                        class="hidden inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-blue-600 text-white text-[11px] font-bold">0</span>
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400" id="adv-search-chevron"></i>
                                </button>
                                <div id="adv-active-chips" class="flex flex-wrap items-center gap-2"></div>
                            </div>

                            <div id="adv-search-panel"
                                class="hidden mt-3 rounded-3xl border border-slate-200 bg-white shadow-sm p-5 space-y-6">

                                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Filter by column</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Fields are combined with AND. Leave a
                                            field empty to ignore it.</p>
                                    </div>
                                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                        Text match
                                        <select data-adv-param="adv_match_mode"
                                            class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 normal-case tracking-normal focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                            <option value="contains">Contains</option>
                                            <option value="starts">Starts with</option>
                                            <option value="exact">Exact match</option>
                                        </select>
                                    </label>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">File No</span>
                                        <input type="text" data-adv-param="adv_file_number" placeholder="e.g. KNML 642"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/60 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                                            autocomplete="off">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">File Name</span>
                                        <input type="text" data-adv-param="adv_file_title"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/60 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                                            autocomplete="off">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">Plot No</span>
                                        <input type="text" data-adv-param="adv_plot_number"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/60 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                                            autocomplete="off">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">Gen. Registry</span>
                                        <select data-adv-param="adv_general_registry" data-adv-options="general_registry"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                            <option value="">Any</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">Land Use</span>
                                        <select data-adv-param="adv_land_use_type" data-adv-options="land_use_type"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                            <option value="">Any</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        {{-- district holds ~8,800 distinct values, so it stays a text box. --}}
                                        <span class="text-xs font-semibold text-slate-600">District</span>
                                        <input type="text" data-adv-param="adv_district"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/60 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                                            autocomplete="off">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">LGA</span>
                                        <select data-adv-param="adv_lga" data-adv-options="lga"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                            <option value="">Any</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">Indexed By</span>
                                        <select data-adv-param="adv_created_by" data-adv-options="created_by"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                            <option value="">Anyone</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">Indexed From</span>
                                        <input type="date" data-adv-param="adv_indexed_from"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/60 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-semibold text-slate-600">Indexed To</span>
                                        <input type="date" data-adv-param="adv_indexed_to"
                                            class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/60 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                    </label>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-100">
                                    <p class="text-xs text-slate-400" id="adv-search-hint">Press
                                        <kbd class="px-1.5 py-0.5 bg-slate-100 rounded">Enter</kbd> in any box to apply.
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <button type="button" id="adv-search-reset"
                                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
                                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                                            Clear all
                                        </button>
                                        <button type="button" id="adv-search-apply"
                                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow hover:bg-blue-700 transition">
                                            <i data-lucide="search" class="h-4 w-4"></i>
                                            Apply filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="px-4 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-b border-slate-100 bg-slate-50/60 rounded-3xl">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Files</h2>
                                <p class="text-sm text-slate-500">Search across 40,000+ indexed records</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-slate-100 text-slate-600 font-semibold">
                                    <i data-lucide="activity" class="h-3.5 w-3.5"></i>
                                    Live data
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 font-semibold">
                                    <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                                    Audit ready
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 overflow-hidden rounded-3xl border border-slate-100">
                            @include('components.gender-legend')
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm" id="indexed-files-table">
                                    <thead class="bg-gray-100 border-b border-gray-200 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="shelf_location">Shelf/Rack</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="general_registry">Gen. Registry</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="registry">Registry</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="file_number">File No</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">Correspondence FileNo</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">Shadow FileNo</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Related FileNo</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Temp FileNo</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                DCIV FileNo</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="file_title">File Name</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="plot_number">Plot No</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="created_at">Indexed Date</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="created_at">Indexed By</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="tp_no">TP No</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="lpkn_no">LPKN No</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="land_use_type">Land Use</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="district">District</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="lga">LGA</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="registry_batch_no">Registry Batch No</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                DCIV Reason</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Lon</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Lat</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Map</th>
                                            <th
                                                class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Status</th>
                                            <th
                                                class="px-4 py-3 text-center font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100" id="indexed-files-tbody">
                                        <tr class="text-center py-8">
                                            <td colspan="25" class="text-gray-500 py-8">
                                                <i data-lucide="loader" class="h-5 w-5 animate-spin inline-block"></i>
                                                Loading...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 py-4 px-4 border-t bg-gradient-to-r from-gray-50 to-white"
                                id="indexed-files-pagination">
                                <p class="text-sm text-gray-700 font-medium" id="indexed-files-summary">Showing <span
                                        class="text-blue-600 font-semibold">--</span> of <span
                                        class="text-blue-600 font-semibold">--</span> results</p>
                                <div class="flex items-center gap-3">
                                    <button class="btn btn-outline btn-sm hover:bg-gray-100 transition disabled:opacity-50"
                                        id="indexed-files-prev" disabled>

                                        <span class="hidden sm:inline">Prev</span>
                                    </button>
                                    <div class="flex items-center gap-2">

                                        <span
                                            class="text-sm text-gray-900 font-bold bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-200 min-w-12 text-center"
                                            id="indexed-files-page">--</span>
                                    </div>
                                    <button class="btn btn-outline btn-sm hover:bg-gray-100 transition disabled:opacity-50"
                                        id="indexed-files-next" disabled>
                                        <span class="hidden sm:inline">Next</span>

                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-indexed-tab-panel="view-indexing" class="hidden px-2 pb-6">
                        <!-- Search and Per-Page Controls -->
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] mb-4">
                            <div class="relative">
                                <label for="view-indexing-search"
                                    class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Global
                                    search</label>
                                <div class="mt-2">
                                    <i data-lucide="search"
                                        class="absolute left-4 top-[58%] transform -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none"></i>
                                    <input type="search" id="view-indexing-search"
                                        placeholder="Search file number, registry, title..."
                                        class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 bg-slate-50/60 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                                        autocomplete="off" spellcheck="false">
                                </div>
                            </div>
                            <div
                                class="bg-slate-50/80 border border-slate-100 rounded-2xl px-4 py-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Records per
                                        page</p>
                                    <p class="text-sm text-slate-500">Choose how many results to load at once</p>
                                </div>
                                <select id="view-indexing-per-page"
                                    class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                                    <option value="20">20</option>
                                    <option value="40">40</option>
                                    <option value="60">60</option>
                                    <option value="80">80</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 overflow-hidden rounded-3xl border border-slate-100">
                            @include('components.gender-legend')
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm" id="view-indexing-table">
                                    <thead class="bg-gray-100 border-b border-gray-200 sticky top-0">
                                        <tr>  
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="file_number">File Number</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Related FileNo</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="file_title">File Title</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="current_holder">Current Holder</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="original_holder">Original Holder</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="plot_number">Plot Number</th>
                                            <th
                                                class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                                Land Use</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="district">District</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="lga">LGA</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide"
                                                data-sort="state">State</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100" id="view-indexing-tbody">
                                        <tr class="text-center py-8">
                                            <td colspan="10" class="text-gray-500 py-8">
                                                <i data-lucide="loader" class="h-5 w-5 animate-spin inline-block"></i>
                                                Loading...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 py-4 px-4 border-t bg-gradient-to-r from-gray-50 to-white"
                                id="view-indexing-pagination">
                                <p class="text-sm text-gray-700 font-medium" id="view-indexing-summary">Showing <span
                                        class="text-blue-600 font-semibold">--</span> of <span
                                        class="text-blue-600 font-semibold">--</span> results</p>
                                <div class="flex items-center gap-3">
                                    <button class="btn btn-outline btn-sm hover:bg-gray-100 transition disabled:opacity-50"
                                        id="view-indexing-prev" disabled>
                                        <span class="hidden sm:inline">Prev</span>
                                    </button>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm text-gray-900 font-bold bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-200 min-w-12 text-center"
                                            id="view-indexing-page">--</span>
                                    </div>
                                    <button class="btn btn-outline btn-sm hover:bg-gray-100 transition disabled:opacity-50"
                                        id="view-indexing-next" disabled>
                                        <span class="hidden sm:inline">Next</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('fileindexing.partials.unindexed_assets')

    {{-- Property Transaction Modal --}}
    @include('fileindexing.partial.property_transaction_modal')

    @include('components.global-fileno-modal')

    <div id="location-map-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
        <div class="relative mx-auto my-8 w-full max-w-5xl px-4">
            <div class="overflow-hidden rounded-[32px] bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-8 py-5">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Map Coordinates</p>
                        <h2 id="location-map-modal-title" class="text-lg font-bold text-slate-900">Update Lat / Lon</h2>
                    </div>
                    <button type="button" class="location-map-close-btn inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
                <div class="space-y-6 p-8">
                    <div class="grid gap-6 sm:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">File Number</label>
                            <p id="location-map-file-number" class="mt-2 text-sm font-semibold text-slate-900"></p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</label>
                            <p id="location-map-file-title" class="mt-2 text-sm text-slate-700"></p>
                        </div>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label for="location-map-latitude" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Latitude</label>
                            <input id="location-map-latitude" type="text" inputmode="decimal" step="any"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 font-mono" />
                        </div>
                        <div>
                            <label for="location-map-longitude" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Longitude</label>
                            <input id="location-map-longitude" type="text" inputmode="decimal" step="any"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 font-mono" />
                        </div>
                    </div>
                    <div class="rounded-3xl border border-slate-200 overflow-hidden bg-slate-50">
                        <div class="flex items-center justify-between p-4 border-b border-slate-200 bg-white">
                            <p class="text-sm font-semibold text-slate-700">📍 Map Preview</p>
                            <span class="text-[10px] text-slate-400 font-medium">Drag the marker to adjust coordinates</span>
                        </div>
                        <div id="location-map-preview" class="min-h-[28rem] bg-slate-100 relative z-10"></div>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p id="location-map-helper" class="text-sm text-slate-500">Drag the marker or type coordinates to pinpoint the location.</p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="location-map-close-btn inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                Close
                            </button>
                            <button id="location-map-save-btn" type="button" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                Save Coordinates
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    @endpush

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    {{-- Shared cross-module Property Timeline card, opened by the action menu's
         "View File Transactions" item. --}}
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
    {{-- Two-step typed confirmation shared with the Recommendation / RofO master
         deletes, used by the action menu's "Master Delete" item. --}}
    <script src="{{ asset('js/master-delete.js') }}"></script>
    {{-- The File Snapshot card, reused by the action menu's "View Details" item so
         it shows the same card the indexing form shows after a save. --}}
    <script src="{{ asset('js/fileindexing/file-snapshot-card.js') }}?v={{ @filemtime(public_path('js/fileindexing/file-snapshot-card.js')) }}"></script>

    <script>
        window.indexedFilesConfig = {
            tableVariant: 'main',
            listUrl: @json(route('indexed-files.api.list')),
            viewListUrl: @json(route('indexed-files.api.view-list')),
            filterOptionsUrl: @json(route('indexed-files.api.filter-options')),
            statsUrl: @json(route('indexed-files.api.stats')),
            updateCoordinatesUrlTemplate: @json(route('indexed-files.api.update-coordinates', ['id' => '__ID__'])),
            showUrlTemplate: @json(route('fileindex.show', ['fileindex' => '__ID__'])),
            editUrlTemplate: @json(url('fileindexing/__ID__/edit')),
            deleteUrlTemplate: @json(route('fileindex.destroy', ['fileindex' => '__ID__'])),
            trackingUrlTemplate: @json(route('fileindexing.batch-tracking-sheet') . '?files=__ID__'),
            canManageActions: @json($canIndexFiles),
            // The action menu is built in JavaScript, so the module permissions the route
            // middleware enforces travel with the config rather than through a blade guard.
            canEdit: @json($user && $user->canDo('Indexing', 'edit')),
            canDelete: @json($user && $user->canDo('Indexing', 'delete')),
            canPrint: @json($user && $user->canDo('Indexing', 'print')),
            // "Move to Indexing Duplicates" is hidden unless assign_role is Supper Admin.
            canMoveToIndexingDuplicates: @json($hasSuperRole),
            // Master Delete: same gate, same reason — it erases the record from the
            // live tables with no archive behind it. IndexedFileTableController
            // re-checks the role; hiding the entry here is only a courtesy.
            canMasterDelete: @json($hasSuperRole),
            // Match OP, offered on conversion (CON-) rows only. Same endpoints as the
            // Match OP page and the recommendation capture card, so all three ask one
            // service the same question and get the same answer.
            opMatchCheckUrl: @json(route('land-recommendations.op-match.check')),
            opMatchUrl: @json(route('land-recommendations.op-match.store')),
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('[data-indexed-tab]');
            const tabPanels = document.querySelectorAll('[data-indexed-tab-panel]');
            const beginIndexingBtn = document.getElementById('begin-indexing-btn');
            const digitalTabBtn = document.querySelector('[data-indexed-tab="digital"]');
            let digitalTabEnabled = false;

            function setDigitalTabState(enabled) {
                if (!digitalTabBtn) {
                    return;
                }

                digitalTabEnabled = enabled;
                digitalTabBtn.disabled = !enabled;
                digitalTabBtn.setAttribute('aria-disabled', (!enabled).toString());
                const disableClasses = ['opacity-50', 'cursor-not-allowed', 'pointer-events-none'];
                disableClasses.forEach((className) => {
                    digitalTabBtn.classList.toggle(className, !enabled);
                });
            }

            // Initial state
            setDigitalTabState(false);


            function activateTab(targetTag) {
                if (targetTag === 'digital' && !digitalTabEnabled) {
                    return;
                }

                // Toggle buttons
                tabButtons.forEach((btn) => {
                    const isActive = btn.getAttribute('data-indexed-tab') === targetTag;

                    // Active state classes
                    if (isActive) {
                        btn.classList.add('indexed-tab-active', 'border-blue-500', 'bg-blue-50', 'text-blue-700');
                        btn.classList.remove('border-transparent', 'bg-slate-50', 'text-slate-600');
                    } else {
                        btn.classList.remove('indexed-tab-active', 'border-blue-500', 'bg-blue-50', 'text-blue-700');
                        btn.classList.add('border-transparent', 'bg-slate-50', 'text-slate-600');
                    }
                });

                // Toggle panels
                tabPanels.forEach((panel) => {
                    const panelTag = panel.getAttribute('data-indexed-tab-panel');
                    if (panelTag === targetTag) {
                        panel.classList.remove('hidden');
                    } else {
                        panel.classList.add('hidden');
                    }
                });
            }

            // Bind click events
            tabButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    activateTab(btn.getAttribute('data-indexed-tab'));
                });
            });

            if (beginIndexingBtn) {
                beginIndexingBtn.addEventListener('click', () => {
                    setDigitalTabState(true);
                    activateTab('digital');
                });
            }

            // Default tab
            // If default is unindexed, we can stick with that
            activateTab('unindexed');
        });
    </script>

    <!-- Related Files Modal -->
    <div id="related-files-modal" class="fixed inset-0 z-[100] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-200">
                <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-600 p-2 rounded-lg shadow-sm">
                            <i data-lucide="link" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Related File Numbers</h3>
                            <div class="flex items-center gap-2">
                                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Associated File Records</p>
                                <span id="parent-file-number-container" class="hidden">
                                    <span class="text-[10px] text-slate-300 font-bold px-1">|</span>
                                    <span class="text-[10px] text-blue-600 font-bold uppercase tracking-tight">Main: </span>
                                    <span id="parent-file-number-badge" class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100">---</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="close-related-modal-btn" class="text-gray-400 hover:text-gray-500 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <div class="bg-white px-6 py-6">
                    <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200 border-collapse">
                            <thead>
                                <tr class="bg-slate-50 font-semibold text-gray-700">
                                    <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">#</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">File Number</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">File Title</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">Location</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">Plot/TP/LPKN</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs uppercase tracking-wider border-b border-gray-200">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="related-files-table-body" class="bg-white divide-y divide-gray-100 italic text-gray-500">
                                <!-- Dynamic content -->
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm">Loading related files...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-gray-100">
                    <button type="button" id="close-related-modal-footer-btn" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Edit Related File Modal -->
    <div id="edit-related-file-modal" class="fixed inset-0 z-[110] hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="edit-related-backdrop"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100">
                <form id="edit-related-file-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" id="edit-related-id">
                    
                    <div class="bg-indigo-600 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                                <i data-lucide="edit-3" class="w-5 h-5 text-white"></i>
                            </div>
                            <h3 class="text-lg font-bold text-white">Edit Related File Details</h3>
                        </div>
                        <button type="button" id="close-edit-related-modal" class="text-white/70 hover:text-white transition-colors">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>

                    <div class="px-8 py-6 space-y-5">
                        <div class="grid grid-cols-2 gap-5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File Number</label>
                                <input type="text" name="file_number" id="edit-related-file-number" 
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File Title</label>
                                <input type="text" name="file_title" id="edit-related-file-title" 
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Property Location</label>
                            <textarea name="location" id="edit-related-location" rows="2"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700 resize-none"></textarea>
                        </div>

                        <div class="grid grid-cols-3 gap-5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Plot Number</label>
                                <input type="text" name="plot_number" id="edit-related-plot-number" 
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">TP Number</label>
                                <input type="text" name="tp_no" id="edit-related-tp-no" 
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">LPKN Number</label>
                                <input type="text" name="lpkn_no" id="edit-related-lpkn-no" 
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-8 py-5 flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100">
                        <button type="button" id="cancel-edit-related" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/25 transition-all flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Temporary File Number Modal -->
    <div id="temp-file-modal" class="fixed inset-0 z-[120] hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="temp-file-backdrop"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100">
                <input type="hidden" id="temp-file-id">

                <div class="bg-teal-600 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                            <i data-lucide="file-key" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Has Temporary File</h3>
                            <p class="text-xs text-teal-100 font-medium">Enter the SFS Temporary File Number</p>
                        </div>
                    </div>
                    <button type="button" id="close-temp-file-modal" class="text-white/70 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="px-8 py-6 space-y-5">
                    <div class="grid grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File No</label>
                            <input type="text" id="temp-file-file-number" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File Title</label>
                            <input type="text" id="temp-file-file-title" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Plot No</label>
                            <input type="text" id="temp-file-plot-number" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">District</label>
                            <input type="text" id="temp-file-district" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">LGA</label>
                            <input type="text" id="temp-file-lga" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Location</label>
                        <input type="text" id="temp-file-location" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                    </div>

                    <div class="space-y-1.5 pt-2 border-t border-gray-100">
                        <label class="text-xs font-bold text-teal-600 uppercase tracking-wider block">Temporary File Number (SFS) <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-3">
                            <input type="text" id="temp-file-no-input" readonly class="flex-1 px-4 py-3 rounded-xl border-2 border-teal-200 bg-gray-50 text-gray-800 font-semibold text-lg cursor-pointer" placeholder="Click to select file number...">
                            <button type="button" id="open-fileno-selector-btn" class="px-4 py-3 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">
                                <i data-lucide="file-search" class="w-4 h-4"></i>
                                Select
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Use the file number selector to choose the SFS temporary file number.</p>
                    </div>
                </div>

                <div class="bg-gray-50 px-8 py-5 flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100">
                    <button type="button" id="cancel-temp-file" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">Cancel</button>
                    <button type="button" id="submit-temp-file" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 shadow-lg shadow-teal-500/25 transition-all flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MCC FileNo: Match Cadastral Correspondence FileNo Modal -->
    <div id="mcc-file-modal" class="fixed inset-0 z-[120] hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="mcc-file-backdrop"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100">
                <input type="hidden" id="mcc-file-id">

                <!-- Header -->
                <div class="bg-gradient-to-r from-[#5c0d0d] via-[#7c3a1d] to-[#9c6b32] px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                            <i data-lucide="git-compare" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Match Cadastral Correspondence FileNo</h3>
                            <p class="text-xs text-white/80 font-medium">Link a Land file with its Cadastral Correspondence file</p>
                        </div>
                    </div>
                    <button type="button" id="close-mcc-file-modal" class="text-white/70 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- Body: two columns -->
                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 border border-gray-200 rounded-2xl overflow-hidden">
                        <!-- LAND side -->
                        <div class="p-5 border-b md:border-b-0 md:border-r border-gray-200 bg-[#fbe9e9]">
                            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-gray-100">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-[#fdeaea] text-[#7a1212]"><i data-lucide="file-text" class="w-4 h-4"></i></span>
                                <h4 class="text-sm font-bold text-[#7a1212] uppercase tracking-wide">Land Department</h4>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3" id="mcc-land-details"></dl>
                        </div>

                        <!-- CADASTRAL side -->
                        <div class="p-5 bg-[#faf3ea]/60">
                            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-gray-100">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-[#f3e6d3] text-[#8a5a2b]"><i data-lucide="compass" class="w-4 h-4"></i></span>
                                <h4 class="text-sm font-bold text-[#8a5a2b] uppercase tracking-wide">Cadastral Department</h4>
                            </div>

                            <!-- Selector (unmatched only) -->
                            <div id="mcc-cadastral-selector" class="mb-4 hidden">
                                <label class="text-xs font-bold text-[#8a5a2b] uppercase tracking-wider block mb-1.5">Cadastral File Number <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="text" id="mcc-cadastral-input" readonly class="flex-1 px-4 py-2.5 rounded-xl border-2 border-[#e3cba5] bg-white text-gray-800 font-semibold cursor-pointer" placeholder="Click to select cadastral file...">
                                    <button type="button" id="mcc-open-fileno-selector-btn" class="px-4 py-2.5 rounded-xl bg-[#8a5a2b] text-white font-bold hover:bg-[#73491f] transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">
                                        <i data-lucide="file-search" class="w-4 h-4"></i>
                                        Select
                                    </button>
                                </div>
                            </div>

                            <!-- Awaiting placeholder -->
                            <div id="mcc-cadastral-awaiting" class="flex flex-col items-center justify-center py-10 text-center">
                                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#f3e6d3] text-[#8a5a2b] mb-3"><i data-lucide="clock" class="w-6 h-6"></i></span>
                                <p class="text-sm font-semibold text-[#8a5a2b]">Awaiting Match</p>
                                <p class="text-xs text-gray-400 mt-1">Select a cadastral file number to view its details.</p>
                            </div>

                            <!-- Loading -->
                            <div id="mcc-cadastral-loading" class="hidden py-10 text-center text-sm text-gray-400">
                                <i data-lucide="loader" class="w-5 h-5 animate-spin inline-block"></i> Loading details...
                            </div>

                            <!-- Details -->
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 hidden" id="mcc-cadastral-details"></dl>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-5 flex items-center justify-between gap-3 rounded-b-2xl border-t border-gray-100">
                    <div id="mcc-status-badge"></div>
                    <div class="flex items-center gap-3">
                        <button type="button" id="cancel-mcc-file" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">Cancel</button>
                        <button type="button" id="mcc-unmatch-btn" class="hidden px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-[#7a1212] hover:bg-[#5c0d0d] shadow-lg shadow-[#7a1212]/25 transition-all inline-flex items-center gap-2">
                            <i data-lucide="unlink" class="w-4 h-4"></i>
                            Unmatch
                        </button>
                        <button type="button" id="mcc-match-btn" class="hidden px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-[#8a5a2b] hover:bg-[#73491f] shadow-lg shadow-[#8a5a2b]/25 transition-all inline-flex items-center gap-2">
                            <i data-lucide="link" class="w-4 h-4"></i>
                            Match
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MPP FileNo: Match  Shadow Files (Shadow Files) Modal -->
    <div id="mpp-file-modal" class="fixed inset-0 z-[120] hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="mpp-file-backdrop"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100">
                <input type="hidden" id="mpp-file-id">

                <!-- Header -->
                <div class="bg-gradient-to-r from-[#0d3b5c] via-[#1d5a7c] to-[#32869c] px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                            <i data-lucide="git-compare" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Match Shadow File</h3>
                            <p class="text-xs text-white/80 font-medium">Link a Land file with its Physical Planning shadow file</p>
                        </div>
                    </div>
                    <button type="button" id="close-mpp-file-modal" class="text-white/70 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- Body: two columns -->
                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 border border-gray-200 rounded-2xl overflow-hidden">
                        <!-- LAND side -->
                        <div class="p-5 border-b md:border-b-0 md:border-r border-gray-200 bg-[#fbe9e9]">
                            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-gray-100">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-[#fdeaea] text-[#7a1212]"><i data-lucide="file-text" class="w-4 h-4"></i></span>
                                <h4 class="text-sm font-bold text-[#7a1212] uppercase tracking-wide">Land Department</h4>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3" id="mpp-land-details"></dl>
                        </div>

                        <!-- PHYSICAL PLANNING side -->
                        <div class="p-5 bg-[#eaf6f0]/60">
                            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-gray-100">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-[#d3f3e0] text-[#2b8a5a]"><i data-lucide="map" class="w-4 h-4"></i></span>
                                <h4 class="text-sm font-bold text-[#2b8a5a] uppercase tracking-wide">Physical Planning Department</h4>
                            </div>

                            <!-- Selector (unmatched only) -->
                            <div id="mpp-pp-selector" class="mb-4 hidden">
                                <label class="text-xs font-bold text-[#2b8a5a] uppercase tracking-wider block mb-1.5">Physical Planning File Number <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="text" id="mpp-pp-input" readonly class="flex-1 px-4 py-2.5 rounded-xl border-2 border-[#a5e3c0] bg-white text-gray-800 font-semibold cursor-pointer" placeholder="Click to select physical planning file...">
                                    <button type="button" id="mpp-open-fileno-selector-btn" class="px-4 py-2.5 rounded-xl bg-[#2b8a5a] text-white font-bold hover:bg-[#1f7349] transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">
                                        <i data-lucide="file-search" class="w-4 h-4"></i>
                                        Select
                                    </button>
                                </div>
                            </div>

                            <!-- Awaiting placeholder -->
                            <div id="mpp-pp-awaiting" class="flex flex-col items-center justify-center py-10 text-center">
                                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#d3f3e0] text-[#2b8a5a] mb-3"><i data-lucide="clock" class="w-6 h-6"></i></span>
                                <p class="text-sm font-semibold text-[#2b8a5a]">Awaiting Match</p>
                                <p class="text-xs text-gray-400 mt-1">Select a physical planning file number to view its details.</p>
                            </div>

                            <!-- Loading -->
                            <div id="mpp-pp-loading" class="hidden py-10 text-center text-sm text-gray-400">
                                <i data-lucide="loader" class="w-5 h-5 animate-spin inline-block"></i> Loading details...
                            </div>

                            <!-- Details -->
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 hidden" id="mpp-pp-details"></dl>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-5 flex items-center justify-between gap-3 rounded-b-2xl border-t border-gray-100">
                    <div id="mpp-status-badge"></div>
                    <div class="flex items-center gap-3">
                        <button type="button" id="cancel-mpp-file" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">Cancel</button>
                        <button type="button" id="mpp-unmatch-btn" class="hidden px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-[#12407a] hover:bg-[#0d3b5c] shadow-lg shadow-[#12407a]/25 transition-all inline-flex items-center gap-2">
                            <i data-lucide="unlink" class="w-4 h-4"></i>
                            Unmatch
                        </button>
                        <button type="button" id="mpp-match-btn" class="hidden px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-[#2b8a5a] hover:bg-[#1f7349] shadow-lg shadow-[#2b8a5a]/25 transition-all inline-flex items-center gap-2">
                            <i data-lucide="link" class="w-4 h-4"></i>
                            Match
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script type="module" src="{{ asset('js/indexed-files/index.js') }}?v={{ filemtime(public_path('js/indexed-files/index.js')) }}"></script>
    <script type="module" src="{{ asset('js/indexed-files/view-indexing.js') }}?v={{ filemtime(public_path('js/indexed-files/view-indexing.js')) }}"></script>
@endsection
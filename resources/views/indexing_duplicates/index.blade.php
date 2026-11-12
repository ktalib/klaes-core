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
                            Indexed files found to be duplicates and removed from the live tables. These records, and
                            their PRA, CofO, file history and file tracking rows, no longer exist in the live tables
                            &mdash; this page and its stored snapshot are the only remaining record of them.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                    @if (auth()->user()?->isSuperAdmin())
                        <button type="button" id="dup-manual-open"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-600 text-sm font-semibold text-white hover:bg-rose-700 transition-all shadow-sm">
                            <i data-lucide="file-plus" class="h-4 w-4"></i>
                            Manual Entry
                        </button>
                    @endif
                    <a href="{{ route('indexed-files.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-600 hover:text-blue-600 hover:border-blue-200 transition-all shadow-sm">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Indexed Files
                    </a>
                    </div>
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
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="temp_file_no">Temp File No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="file_title">File Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="land_use_type">Land Use</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="plot_number">Plot No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="indexed_by">Indexed By</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="indexed_at">Indexed Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide cursor-pointer whitespace-nowrap" data-sort="moved_by">Moved By</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 text-xs uppercase tracking-wide whitespace-nowrap">Actions</th>
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

    <!-- Manual entry: a duplicate file that was never indexed (CR 11112026-2) -->
    <div id="dup-manual-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold text-rose-500 uppercase tracking-widest">Manual Entry</p>
                    <h3 class="text-xl font-bold text-slate-900 mt-1">Capture a duplicate file</h3>
                    <p class="text-xs text-slate-500 mt-1">For a duplicate physical file that was never indexed. Enter what is written on THIS file: nothing is copied from the indexed main file.</p>
                </div>
                <button type="button" id="dup-manual-close" aria-label="Close"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <form id="dup-manual-form" class="p-5 overflow-y-auto space-y-4" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">File No. <span class="text-rose-500">*</span></label>
                        <div class="flex">
                            <input type="text" id="dup-manual-fileno" name="file_number" readonly placeholder="Click to select file number"
                                   class="w-full rounded-l-xl border border-slate-200 px-3 py-2 text-sm cursor-pointer bg-white">
                            <button type="button" id="dup-manual-fileno-btn" aria-label="Select file number"
                                    class="px-3 border border-l-0 border-slate-200 rounded-r-xl text-slate-500 hover:text-blue-600 hover:bg-blue-50">
                                <i data-lucide="search" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Duplicate Of (main file)</label>
                        <input type="text" id="dup-manual-dupof" name="duplicate_of" readonly placeholder="Same as File No."
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">File Title (on this file) <span class="text-rose-500">*</span></label>
                    <input type="text" name="file_title" maxlength="4000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Plot No.</label>
                        <input type="text" name="plot_number" maxlength="100" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">District</label>
                        <select id="dup-manual-district" name="district" class="dup-manual-place w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Select district</option>
                            @foreach (($districtOptions ?? []) as $districtName)
                                <option value="{{ $districtName }}">{{ $districtName }}</option>
                            @endforeach
                            <option value="__other">Other</option>
                        </select>
                        <input type="text" id="dup-manual-district-other" maxlength="100" placeholder="Specify district"
                               class="hidden mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" disabled>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">LGA</label>
                        <select id="dup-manual-lga" name="lga" class="dup-manual-place w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Select LGA</option>
                            @foreach (($lgaOptions ?? []) as $lgaName)
                                <option value="{{ $lgaName }}">{{ $lgaName }}</option>
                            @endforeach
                            <option value="__other">Other</option>
                        </select>
                        <input type="text" id="dup-manual-lga-other" maxlength="100" placeholder="Specify LGA"
                               class="hidden mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" disabled>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Location <span class="font-normal text-slate-400">(from District and LGA)</span></label>
                    <input type="text" id="dup-manual-location" name="location" maxlength="255" readonly placeholder="Select a district and LGA"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        {{-- Land use comes from the file number, in full (RES = Residential); typed only when the number carries none. --}}
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Land Use <span class="font-normal text-slate-400">(from File No.)</span></label>
                        <input type="text" id="dup-manual-landuse" name="land_use_type" maxlength="100" readonly placeholder="Select a file number"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50">
                    </div>
                    <div>
                        {{-- The same Physical Registry list as File Indexing's create form, plus one "Other". --}}
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Physical Registry</label>
                        <select id="dup-manual-registry" name="registry" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Select Registry</option>
                            @foreach ($physicalRegistries as $registryName)
                                <option value="{{ $registryName }}">{{ $registryName }}</option>
                            @endforeach
                            <option value="__other">Other</option>
                        </select>
                        <input type="text" id="dup-manual-registry-other" maxlength="50" placeholder="Specify registry" disabled
                               class="hidden mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reason <span class="text-rose-500">*</span></label>
                    <textarea name="reason" maxlength="500" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                              placeholder="e.g. Main file is indexed; this physical file carries the same number"></textarea>
                </div>
                <div id="dup-manual-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" id="dup-manual-cancel" class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" id="dup-manual-save" class="px-4 py-2 rounded-xl bg-rose-600 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50">Save Entry</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.indexingDuplicatesConfig = {
            listUrl: @json(route('indexing-duplicates.api.list')),
            statsUrl: @json(route('indexing-duplicates.api.stats')),
            showUrlTemplate: @json(route('indexing-duplicates.api.show', ['id' => '__ID__'])),
            reversePreviewUrlTemplate: @json(route('indexing-duplicates.api.reverse-preview', ['id' => '__ID__'])),
            reverseUrlTemplate: @json(route('indexing-duplicates.api.reverse', ['id' => '__ID__'])),
            // Reverse Duplicate is Super Admin only, like the move; the controller re-checks.
            canReverse: @json((bool) auth()->user()?->isSuperAdmin()),
            manualUrl: @json(route('indexing-duplicates.api.manual')),
            landUseLabels: @json(\App\Support\FileNumberLandUse::LABELS),
            tempFileUrlTemplate: @json(route('indexing-duplicates.api.temp-file', ['id' => '__ID__'])),
            csrfToken: @json(csrf_token()),
        };
    </script>
    @if (auth()->user()?->isSuperAdmin())
        @include('components.global-fileno-modal')
        {{-- Searchable District / LGA lists on Manual Entry (1,800+ districts). --}}
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
        <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    @endif
    <script src="{{ asset('js/indexing-duplicates/index.js') }}?v={{ filemtime(public_path('js/indexing-duplicates/index.js')) }}"></script>
@endsection

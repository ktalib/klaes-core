@extends('layouts.app')

@section('page-title')
    {{ __($pageTitle ?? 'Duplicate File Search') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50 text-slate-900">
        @include('admin.header')

        <div class="relative isolate">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.25),_transparent_55%),_radial-gradient(circle_at_bottom,_rgba(34,197,94,0.3),_transparent_60%)]"></div>

            <div class="max-w-7xl mx-auto px-6 lg:px-10 pt-10 pb-16 space-y-10">
                <div class="bg-gradient-to-br from-white/95 via-emerald-50/90 to-emerald-100/60 border border-emerald-100 rounded-3xl p-8 shadow-2xl">
                    <p class="text-xs uppercase tracking-[0.3em] text-emerald-600">Registry</p>
                    <div class="mt-4 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
                        <div class="space-y-3 max-w-2xl">
                            <h1 class="text-4xl lg:text-5xl font-semibold text-emerald-900 leading-tight">Problem Files Search Database</h1>
                            <p class="text-base text-emerald-900/70">Audit historical registry ledgers, surface conflicting allocations and triage duplicate records without touching production tables.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-center sm:text-left">
                            <div class="bg-white/80 border border-emerald-100 rounded-2xl px-4 py-3">
                                <p class="text-[0.65rem] uppercase tracking-widest text-emerald-500">Modules linked</p>
                                <p class="text-2xl font-semibold">2</p>
                            </div>
                            <div class="bg-white/80 border border-emerald-100 rounded-2xl px-4 py-3">
                                <p class="text-[0.65rem] uppercase tracking-widest text-emerald-500">Columns tracked</p>
                                <p class="text-2xl font-semibold">7</p>
                            </div>
                            <div class="bg-white/80 border border-emerald-100 rounded-2xl px-4 py-3 col-span-2">
                                <p class="text-[0.65rem] uppercase tracking-widest text-emerald-500">Data contract</p>
                                <p class="text-sm">SN · Registry · File Number · Title · Plot · Location · Comment</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-6 text-slate-900">
                    <div class="flex flex-col gap-4">
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)]">
                            <label class="flex flex-col text-sm font-semibold text-emerald-900/80">
                                <span>Keyword</span>
                                <div class="relative mt-2">
                                    <input
                                        id="file-search-keyword"
                                        type="search"
                                        inputmode="search"
                                        autocomplete="off"
                                        placeholder="Search file number, title or location"
                                        class="w-full rounded-2xl border border-emerald-100 bg-white px-4 py-3 pr-12 text-base text-slate-900 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                    >
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">⌘K</span>
                                </div>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="text-sm font-semibold text-emerald-900/80">
                                    <span>Registry</span>
                                    <select id="file-search-registry" class="mt-2 w-full rounded-2xl border border-emerald-100 px-4 py-3 text-base text-slate-900 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                        <option value="">All Registries</option>
                                        @foreach(($registryOptions ?? collect()) as $registry)
                                            <option value="{{ $registry }}">{{ $registry }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-sm font-semibold text-emerald-900/80">
                                    <span>Per Page</span>
                                    <select id="file-search-per-page" class="mt-2 w-full rounded-2xl border border-emerald-100 px-4 py-3 text-base text-slate-900 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="75">75</option>
                                        <option value="100">100</option>
                                    </select>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="text-sm font-semibold text-emerald-900/80">
                                    <span>Start Date</span>
                                    <input id="file-search-start-date" type="date" class="mt-2 w-full rounded-2xl border border-emerald-100 px-4 py-3 text-base text-slate-900 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                </label>
                                <label class="text-sm font-semibold text-emerald-900/80">
                                    <span>End Date</span>
                                    <input id="file-search-end-date" type="date" class="mt-2 w-full rounded-2xl border border-emerald-100 px-4 py-3 text-base text-slate-900 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                </label>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500">
                            <button id="file-search-run" type="button" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-2 text-white font-semibold hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-emerald-500">
                                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Run Search
                            </button>
                            <button id="file-search-reset" type="button" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-100 px-5 py-2 font-semibold text-emerald-700 hover:border-emerald-200">
                                Reset Filters
                            </button>
                            <button id="file-search-export" type="button" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-700 px-5 py-2 text-white font-semibold hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-emerald-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 12v9m0-9l3 3m-3-3l-3 3M12 5l3 3m-3-3L9 8m3-3V3"/></svg>
                                Export Results
                            </button>
                            <button id="file-search-import" type="button" class="inline-flex items-center gap-2 rounded-2xl bg-amber-500 px-5 py-2 text-white font-semibold hover:bg-amber-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-amber-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 12v9m0-9l3 3m-3-3l-3 3M12 5l3 3m-3-3L9 8m3-3V3"></path></svg>
                                Import CSV
                            </button>
                            <button id="file-search-add" type="button" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-2 text-white font-semibold hover:bg-blue-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Add Record
                            </button>
                        </div>
                    </div>
                    <!-- Tab Navigation -->
                    <div class="flex border-b border-emerald-100 bg-emerald-50 rounded-t-2xl">
                        @foreach(($recordTypeLabels ?? []) as $typeKey => $typeLabel)
                            <button 
                                type="button" 
                                class="tab-button px-6 py-4 text-sm font-semibold transition-all duration-200 rounded-t-2xl {{ $loop->first ? 'active bg-white text-emerald-900 border-b-2 border-emerald-500' : 'text-emerald-700 hover:text-emerald-900 hover:bg-emerald-100' }}" 
                                data-tab="{{ $typeKey }}">
                                {{ $typeLabel }}
                                <span class="tab-count ml-2 inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full {{ $loop->first ? 'bg-emerald-100 text-emerald-700' : 'bg-emerald-200 text-emerald-600' }}" id="count-{{ $typeKey }}">0</span>
                            </button>
                        @endforeach
                    </div>

                    <!-- Tab Content -->
                    <div class="space-y-0" id="file-search-sections">
                        @foreach(($recordTypeLabels ?? []) as $typeKey => $typeLabel)
                            @php($sectionId = 'file-search-' . $typeKey)
                            <section class="tab-content rounded-b-2xl border border-t-0 border-slate-200 {{ $loop->first ? 'active' : 'hidden' }}" data-record-type="{{ $typeKey }}" data-tab-content="{{ $typeKey }}">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-emerald-100 text-sm" id="{{ $sectionId }}-table">
                                        <thead class="bg-emerald-50 text-[0.65rem] uppercase tracking-[0.3em] text-emerald-700">
                                            <tr>
                                                <th class="px-4 py-3 text-left">SN</th>
                                                <th class="px-4 py-3 text-left">Registry</th>
                                                <th class="px-4 py-3 text-left">File Number</th>
                                                <th class="px-4 py-3 text-left">File Title</th>
                                                <th class="px-4 py-3 text-left">Plot Number</th>
                                                <th class="px-4 py-3 text-left">Location</th>
                                                <th class="px-4 py-3 text-left">Comment</th>
                                                <th class="px-4 py-3 text-left">Created</th>
                                                <th class="px-4 py-3 text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="{{ $sectionId }}-tbody" class="divide-y divide-slate-100 text-slate-700">
                                            <tr>
                                                <td colspan="9" class="px-4 py-10 text-center text-slate-500" id="{{ $sectionId }}-empty">
                                                    <div class="inline-flex items-center gap-3 text-base">
                                                        <svg class="h-5 w-5 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
                                                        Loading {{ strtolower($typeLabel) }}...
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex flex-col gap-3 border-t border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 md:flex-row md:items-center md:justify-between">
                                    <div class="flex items-center gap-4">
                                        <p id="{{ $sectionId }}-summary" class="font-medium">Awaiting results</p>
                                        <span id="{{ $sectionId }}-status" class="hidden text-xs font-semibold uppercase tracking-widest text-emerald-600"></span>
                                    </div>
                                    <div class="inline-flex items-center gap-2">
                                        <button id="{{ $sectionId }}-export" type="button" class="rounded-xl bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-emerald-500">
                                            Export
                                        </button>
                                        <button id="{{ $sectionId }}-prev" type="button" class="rounded-xl border border-emerald-100 px-4 py-2 font-semibold text-emerald-700 disabled:opacity-40">Prev</button>
                                        <span id="{{ $sectionId }}-page" class="rounded-xl bg-white px-4 py-2 font-semibold text-emerald-900">1</span>
                                        <button id="{{ $sectionId }}-next" type="button" class="rounded-xl border border-emerald-100 px-4 py-2 font-semibold text-emerald-700 disabled:opacity-40">Next</button>
                                    </div>
                                </div>
                                <div id="{{ $sectionId }}-error" class="hidden border-t border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
                            </section>
                        @endforeach
                    </div>

                    <div id="file-search-error" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>

    <!-- Add/Edit Record Modal -->
    <div id="record-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" id="modal-backdrop"></div>
            {{-- Wider than it was: the card now captures a row of fields per file rather
                 than a single file, and four fields do not fit legibly in max-w-lg. --}}
            <div class="relative w-full max-w-3xl transform rounded-3xl bg-white p-6 shadow-2xl transition-all max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 id="modal-title" class="text-xl font-semibold text-slate-900">Add Record</h3>
                    <button type="button" id="modal-close" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form id="record-form" class="space-y-4">
                    <input type="hidden" id="record-id" name="id" value="">
                    <input type="hidden" id="record-original-type" value="">
                    <input type="hidden" id="record-type" name="record_type" value="{{ $defaultRecordType ?? 'duplicate' }}">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="record-registry" class="block text-sm font-medium text-slate-700 mb-1">Registry <span class="text-red-500">*</span></label>
                            <select id="record-registry" name="registry" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                <option value="">Select Registry</option>
                                @foreach(($registrySelectOptions ?? collect()) as $registry)
                                    <option value="{{ $registry }}">{{ $registry }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="record-comment" class="block text-sm font-medium text-slate-700 mb-1">Category <span class="text-red-500">*</span></label>
                            <select id="record-comment" name="comment_label" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                <option value="duplicate">Duplicate Files</option>
                                <option value="temporary">Temp Files</option>
                                <option value="wrc">W/C/R Files</option>
                            </select>
                        </div>
                    </div>

                    {{-- SHARED. The file number is the one thing every entry has in common:
                         a duplicate is the SAME number appearing more than once, which is
                         what this register records. It belongs above the list, not repeated
                         identically inside every row.

                         Picked from the global file-number list, never typed. --}}
                    <div id="shared-file-number-field" class="hidden">
                        <label for="record-file-number" class="block text-sm font-medium text-slate-700 mb-1">File Number <span class="text-red-500">*</span></label>
                        <div class="relative flex">
                            <input type="text" id="record-file-number" name="file_number" readonly title="Select from existing file numbers" class="w-full rounded-l-xl border border-slate-200 px-4 py-2.5 text-sm cursor-pointer focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="Click to select file number">
                            <button type="button" id="fileno-selector-btn" class="flex items-center px-3 border border-l-0 border-slate-200 bg-slate-50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition-colors rounded-r-xl" title="Select from existing file numbers">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </button>
                        </div>
                    </div>

                    {{-- EDIT MODE. Editing touches exactly one existing row, so the card
                         keeps the single set of fields it always had. Hidden while adding. --}}
                    <div id="single-file-fields" class="hidden space-y-4">
                        <div>
                            <label for="record-file-title-single" class="block text-sm font-medium text-slate-700 mb-1">File Title</label>
                            <input type="text" id="record-file-title-single" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="Enter file title">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="record-plot-number" class="block text-sm font-medium text-slate-700 mb-1">Plot Number</label>
                                <input type="text" id="record-plot-number" name="plot_number" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="e.g., Plot 123">
                            </div>
                            <div>
                                <label for="record-location" class="block text-sm font-medium text-slate-700 mb-1">Location</label>
                                <input type="text" id="record-location" name="location" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="Enter location">
                            </div>
                        </div>
                    </div>

                    {{-- Duplicate titles share one selected file number; other categories
                         retain their individual file-number rows. --}}
                    <div id="multi-file-fields">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <label id="record-file-list-label" class="block text-sm font-medium text-slate-700">Files</label>
                                <p id="record-file-list-help" class="text-xs text-slate-500">Each file below is saved as its own record, under the registry and category above.</p>
                            </div>
                            <button type="button" id="record-file-add" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span id="record-file-add-label">Add File</span>
                            </button>
                        </div>
                        <div id="record-file-list" class="space-y-3"></div>
                    </div>

                    <input type="hidden" id="record-file-title" name="file_title" value="">
                    <input type="hidden" id="record-comment-note" name="comment" value="">
                    <div id="form-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" id="modal-cancel" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" id="modal-submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50">
                            <span id="submit-text">Save Record</span>
                            <span id="submit-loading" class="hidden">
                                <svg class="inline h-4 w-4 animate-spin mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="import-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" id="import-backdrop"></div>
            <div class="relative w-full max-w-xl transform rounded-3xl bg-white p-6 shadow-2xl transition-all">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-slate-900">Import CSV Records</h3>
                    <button type="button" id="import-close" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form id="import-form" class="space-y-4" enctype="multipart/form-data">
                    <div class="flex flex-col gap-2 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 sm:flex-row sm:items-center sm:justify-between">
                        <span>Use the CSV template to align columns before uploading new records.</span>
                        <a
                            id="import-template-link"
                            href="{{ route('file-search-db.import-template') }}"
                            class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-3 py-2 font-semibold text-emerald-700 hover:border-emerald-300 hover:text-emerald-900"
                            target="_blank"
                            rel="noopener"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l3-3m-3 3l-3-3m9 6H6"/></svg>
                            Download Template
                        </a>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-semibold text-slate-700">
                            <span>Record Type</span>
                            <select id="import-type" name="type" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                @foreach(($recordTypeLabels ?? []) as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-700">
                            <span>Import Mode</span>
                            <select id="import-mode" name="mode" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                <option value="append">Append to existing</option>
                                <option value="replace">Replace existing uploads</option>
                            </select>
                            <span class="mt-1 block text-xs text-slate-500">Replacing removes prior CSV/import uploads for the chosen category only.</span>
                        </label>
                    </div>
                    <div>
                        <label for="import-file" class="block text-sm font-semibold text-slate-700 mb-1">CSV File <span class="text-red-500">*</span></label>
                        <input type="file" id="import-file" name="file" accept=".csv,text/csv,text/plain" required class="w-full rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                        <p class="mt-1 text-xs text-slate-500">Max 20&nbsp;MB CSV. Expected columns: registry, file_number, file_title, plot_number, location, comment.</p>
                    </div>
                    <div id="import-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" id="import-cancel" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" id="import-submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50">
                            <span id="import-submit-text">Start Import</span>
                            <span id="import-submit-loading" class="hidden">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
                                Uploading…
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" id="delete-backdrop"></div>
            <div class="relative w-full max-w-sm transform rounded-3xl bg-white p-6 shadow-2xl transition-all">
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100">
                        <svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Delete Record</h3>
                    <p class="text-sm text-slate-600 mb-6">Are you sure you want to delete this file search record? This action cannot be undone.</p>
                    <input type="hidden" id="delete-record-id" value="">
                    <input type="hidden" id="delete-record-type" value="">
                    <div class="flex justify-center gap-3">
                        <button type="button" id="delete-cancel" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="button" id="delete-confirm" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-500">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Global File Number Modal Component --}}
    @include('components.global-fileno-modal')
    <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>

    <style>
        .tab-button {
            position: relative;
            border-bottom: 2px solid transparent;
        }
        .tab-button.active {
            z-index: 10;
        }
        .tab-content {
            display: block;
        }
        .tab-content.hidden {
            display: none;
        }
    </style>

    @php($defaultRecordType = is_array($recordTypeLabels ?? null) ? array_key_first($recordTypeLabels) : 'duplicate')
    <script>
        window.fileSearchConfig = {
            listUrl: @json(route('file-search-db.records')),
            importUrl: @json(route('file-search-db.import')),
            templateUrl: @json(route('file-search-db.import-template')),
            exportUrl: @json(route('file-search-db.export')),
            storeUrl: @json(route('file-search-db.store')),
            showUrl: @json(route('file-search-db.show', ['id' => '__ID__'])),
            updateUrl: @json(route('file-search-db.update', ['id' => '__ID__'])),
            deleteUrl: @json(route('file-search-db.destroy', ['id' => '__ID__'])),
            defaultPerPage: 25,
            csrfToken: @json(csrf_token()),
            typeLabels: @json($recordTypeLabels ?? []),
            typeOrder: @json(array_keys($recordTypeLabels ?? [])),
            defaultType: @json($defaultRecordType ?? 'duplicate'),
        };
    </script>
    <script type="module" src="{{ asset('js/file_search_db.js') }}"></script>
@endsection

@extends('layouts.app')

@section('page-title')
    {{ __('Missing Files') }}
@endsection

@section('content')

<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')

    <!-- Page banner -->
    <div class="bg-gradient-to-r from-rose-700 via-red-600 to-rose-800 px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="file-warning" class="h-5 w-5 text-white shrink-0"></i>
        <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">Registry</span>
            <span class="text-rose-100 text-sm">·</span>
            <span class="text-white text-sm font-medium">Missing Files</span>
            <span class="text-rose-100 text-sm">·</span>
            <span class="text-rose-100 text-sm">Capture &amp; Track</span>
        </div>
    </div>

    <!-- Page header -->
    <header class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Missing Files</h1>
                <p class="text-gray-600 mt-1">Record files that cannot be located and track their recovery.</p>
            </div>
            <button type="button" id="mf-reset-btn"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                Reset Form
            </button>
        </div>
    </header>

    <div class="p-6">
        <div class="container mx-auto space-y-6">

            <div id="mf-toast-container" class="fixed inset-x-0 top-24 z-[100000] flex flex-col items-center gap-2 pointer-events-none"></div>

            {{-- ============ TABS ============ --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex gap-8 px-6" aria-label="Tabs">
                        <button type="button" data-mf-tab="capture"
                            class="mf-tab active group inline-flex items-center gap-2 border-b-2 border-rose-500 text-rose-600 py-4 px-1 text-sm font-medium">
                            <i data-lucide="clipboard-plus" class="h-4 w-4"></i>
                            Capture Missing File
                        </button>
                        <button type="button" data-mf-tab="list"
                            class="mf-tab group inline-flex items-center gap-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                            <i data-lucide="list-x" class="h-4 w-4"></i>
                            Missing Files
                            <span id="mf-tab-count" class="ml-1 bg-rose-100 text-rose-700 py-0.5 px-2 rounded-full text-xs">0</span>
                        </button>
                    </nav>
                </div>
            </div>

            {{-- ============ CAPTURE PANEL ============ --}}
            <div id="mf-panel-capture" class="mf-tab-panel">
            {{-- ============ CAPTURE CARD ============ --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-rose-50 to-white">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i data-lucide="clipboard-plus" class="h-5 w-5 text-rose-600"></i>
                        Capture a Missing File
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Select the file, then record where it should be shelved.</p>
                </div>

                <div class="p-6">

                    {{-- All fields, 3 per row --}}
                    <div>
                        {{-- Tracking ID (hidden, auto-resolved from File Indexing) --}}
                        <input type="hidden" id="mf-tracking-id" name="tracking_id">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {{-- File No (File Selector) --}}
                                <div class="flex flex-col">
                                    <label for="mf-file-no" class="text-sm font-medium text-gray-700">File No <span class="text-red-500">*</span></label>
                                    <div class="relative mt-1">
                                        <input type="text" id="mf-file-no" name="file_number" placeholder="e.g. RES-2015-4859"
                                            class="block w-full px-3 py-2 pr-12 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500 font-semibold" disabled>
                                        <button type="button" id="mf-fileno-selector-btn"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 border-l border-gray-300 text-gray-500 hover:text-rose-600 hover:bg-rose-50 transition-colors rounded-r-md"
                                            title="Select from existing file numbers">
                                            <i data-lucide="search" class="h-4 w-4"></i>
                                        </button>
                                    </div>
                                    <p id="mf-file-no-hint" class="mt-1 text-xs text-gray-500">Use the smart file selector
                                        <i data-lucide="search" class="h-3 w-3 inline"></i> to search and pick a file number.</p>
                                    {{-- Duplicate warning (UQ_missing_files_file_number) --}}
                                    <p id="mf-file-no-error" class="mt-1 hidden items-start gap-1.5 text-xs font-medium text-red-600">
                                        <i data-lucide="alert-circle" class="h-3.5 w-3.5 shrink-0 mt-px"></i>
                                        <span id="mf-file-no-error-text">This file has already been recorded as missing.</span>
                                    </p>
                                    {{-- In-transit notice (file is logged out in the File Tracker, not truly lost) --}}
                                    <div id="mf-transit-notice" class="mt-2 hidden items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                        <i data-lucide="truck" class="h-4 w-4 shrink-0 mt-px text-amber-600"></i>
                                        <span><span class="font-semibold">Currently in-transit.</span> <span id="mf-transit-notice-text">This file is logged out in the File Tracker.</span></span>
                                    </div>
                                </div>
                                {{-- Archive / Registry --}}
                                <div class="flex flex-col">
                                    <label for="mf-archive-registry" class="text-sm font-medium text-gray-700">Archive / Registry</label>
                                    <select id="mf-archive-registry" name="archive_registry"
                                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500">
                                        <option value="Archive">Archive</option>
                                        <option value="Registry 1" selected>Registry 1</option>
                                        <option value="Registry 2">Registry 2</option>
                                        <option value="Registry 3">Registry 3</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">Auto-detected from the file number &mdash; adjust if needed.</p>
                                </div>
                                {{-- Rack 1 --}}
                                <div class="flex flex-col">
                                    <label for="mf-rack-primary" class="text-sm font-medium text-gray-700">Rack 1</label>
                                    <select id="mf-rack-primary" name="rack_primary"
                                        class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200">
                                        @foreach(range('A', 'Z') as $letter)
                                            <option value="{{ $letter }}">{{ $letter }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- Rack 2 (Backup) --}}
                                <div class="flex flex-col">
                                    <label for="mf-rack-secondary" class="text-sm font-medium text-gray-700">Rack 2 (Backup)</label>
                                    <select id="mf-rack-secondary" name="rack_secondary"
                                        class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200">
                                        <option value="">None</option>
                                        @foreach(range('A', 'Z') as $letter)
                                            <option value="{{ $letter }}">{{ $letter }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- Shelf --}}
                                <div class="flex flex-col">
                                    <label for="mf-shelf-number" class="text-sm font-medium text-gray-700">Shelf</label>
                                    <select id="mf-shelf-number" name="shelf_number"
                                        class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200">
                                        @for($i = 1; $i <= 100; $i++)
                                            <option value="{{ $i }}">{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                {{-- Full Label --}}
                                <div class="flex flex-col">
                                    <label for="mf-full-label" class="text-sm font-medium text-gray-700">Full Label</label>
                                    <input type="text" id="mf-full-label" name="full_label" value="A1" readonly
                                        class="mt-1 w-full rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-700">
                                    <p class="mt-1 text-xs text-slate-500">Auto-generated from rack &amp; shelf.</p>
                                </div>
                            </div>
                        </div>

                    {{-- ---- Submit ---- --}}
                    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="button" id="mf-submit-btn"
                            class="inline-flex items-center px-5 py-2.5 rounded-md text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-sm transition-colors">
                            <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                            Save Missing File
                        </button>
                    </div>
                </div>
            </div>
            </div>{{-- end capture panel --}}

            {{-- ============ LIST PANEL ============ --}}
            <div id="mf-panel-list" class="mf-tab-panel hidden">
            {{-- ============ MISSING FILES TABLE ============ --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="list-x" class="h-5 w-5 text-rose-600"></i>
                            Missing Files
                        </h3>
                        <p class="text-sm text-gray-600 mt-1"><span id="mf-count">0</span> record(s)</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"></i>
                            <input type="search" id="mf-search" placeholder="Search files…"
                                class="w-56 pl-8 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500">
                        </div>
                        <button type="button" id="mf-refresh-btn"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            title="Refresh">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">File No</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Archive / Registry</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Rack</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Shelf</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Label</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Reported By</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Status</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 uppercase tracking-wide text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="mf-table-body" class="divide-y divide-gray-100">
                            <tr id="mf-empty-row">
                                <td colspan="9" class="px-4 py-10 text-center text-gray-400">
                                    <i data-lucide="inbox" class="h-8 w-8 mx-auto mb-2 text-gray-300"></i>
                                    No missing files recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-6 py-3 border-t border-gray-200 flex items-center justify-between flex-wrap gap-3">
                    <p class="text-sm text-gray-600">
                        Showing <span id="mf-page-from">0</span>–<span id="mf-page-to">0</span> of <span id="mf-page-total">0</span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" id="mf-prev-btn"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i data-lucide="chevron-left" class="h-4 w-4"></i>
                        </button>
                        <span class="text-sm text-gray-600">Page <span id="mf-page-current">1</span> of <span id="mf-page-last">1</span></span>
                        <button type="button" id="mf-next-btn"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>
            </div>{{-- end list panel --}}

        </div>
    </div>

    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Global File Number Modal -->
@include('components.global-fileno-modal')

<script>
    window.MissingFilesConfig = {
        routes: {
            data:    "{{ route('missing-files.data') }}",
            check:   "{{ route('missing-files.check') }}",
            store:   "{{ route('missing-files.store') }}",
            found:   "{{ url('missing-files') }}",   // + /{id}/found
            destroy: "{{ url('missing-files') }}",   // + /{id}
        },
        csrf: "{{ csrf_token() }}",
        initial: @json($missingFiles),
        initialPagination: @json($pagination),
    };
</script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/missing-files.js') }}"></script>

@endsection

@extends('layouts.app')
@section('page-title')
    {{ __('Quick Search & File Location') }}
@endsection
{{-- ikkdt --}}
@section('content')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush
{{-- --}}

    <div class="flex-1 overflow-y-auto overflow-x-hidden">
        @include('admin.header')

        @php
            $urlCtx = request('url');
            $isStContext = $urlCtx === 'st';
            $isSltrContext = $urlCtx === 'sltr';
            $isSurveyContext = $urlCtx === 'survey';
            $isKangisContext = $urlCtx === 'kangis';
            $isCadastralContext = $urlCtx === 'cadastral';
            $isDcivContext = $urlCtx === 'dciv';
            if ($isStContext) {
                $headerGradient = 'bg-gradient-to-r from-blue-700 via-blue-600 to-blue-800';
                $headerStyle = 'background-image: linear-gradient(to right, #1d4ed8, #2563eb, #1e40af);';
                $dotClass = 'text-blue-100';
            } elseif ($isSltrContext) {
                $headerGradient = 'bg-gradient-to-r from-lime-600 via-green-500 to-lime-600';
                $headerStyle = 'background-image: linear-gradient(to right, #65a30d, #22c55e, #65a30d);';
                $dotClass = 'text-lime-100';
            } elseif ($isSurveyContext) {
                $headerGradient = 'bg-gradient-to-r from-pink-600 via-pink-500 to-pink-700';
                $headerStyle = 'background-image: linear-gradient(to right, #db2777, #ec4899, #be185d);';
                $dotClass = 'text-pink-100';
            } elseif ($isKangisContext) {
                $headerGradient = 'bg-gradient-to-r from-orange-600 via-orange-500 to-orange-600';
                $headerStyle = 'background-image: linear-gradient(to right, #ea580c, #f97316, #ea580c);';
                $dotClass = 'text-orange-100';
            } elseif ($isCadastralContext) {
                $headerGradient = 'bg-gradient-to-r from-rose-700 via-rose-600 to-rose-800';
                $headerStyle = 'background-image: linear-gradient(to right, #be123c, #e11d48, #9f1239);';
                $dotClass = 'text-rose-100';
            } elseif ($isDcivContext) {
                $headerGradient = 'bg-gradient-to-r from-green-900 via-emerald-800 to-green-900';
                $headerStyle = 'background-image: linear-gradient(to right, #0b3d2e, #065f46, #0b3d2e);';
                $dotClass = 'text-emerald-100';
            } else {
                $headerGradient = 'bg-gradient-to-r from-red-700 via-red-600 to-red-800';
                $headerStyle = '';
                $dotClass = 'text-red-100';
            }
        @endphp
        <div class="px-6 py-3 flex items-center gap-3 shadow-sm {{ $headerGradient }}"
            @if($headerStyle) style="{{ $headerStyle }}" @endif>
            <i data-lucide="search" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Quick Search</span>
                <span class="{{ $dotClass }} text-sm">·</span>
                <span class="text-white text-sm font-medium">File Location &amp; Status</span>
            </div>
            {{-- Hidden for now: Send File Search Request --}}
            <button type="button" class="js-fr-open ml-auto hidden items-center gap-1.5 rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-xs font-semibold text-white border border-white/30 transition-all"
                title="Send a File Search Request to SCB Monitors">
                <i data-lucide="send" class="h-3.5 w-3.5"></i>
                Send File Search Request
            </button>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8">
            <!-- Reporting summary -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i data-lucide="bar-chart-3" class="h-4 w-4 text-indigo-600"></i> File Search Report
                    </h3>
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i data-lucide="calendar" class="h-3.5 w-3.5 text-gray-400"></i>
                            <input id="qs-rep-from" type="date" class="rounded-lg border border-gray-300 px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500" title="From date">
                            <span class="text-gray-400">–</span>
                            <input id="qs-rep-to" type="date" class="rounded-lg border border-gray-300 px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500" title="To date">
                            <button type="button" id="qs-rep-clear" class="hidden inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-50" title="Clear date range">
                                <i data-lucide="x" class="h-3 w-3"></i> Clear
                            </button>
                        </div>
                        <span class="hidden sm:inline text-gray-200">|</span>
                        <button type="button" id="qs-export-csv" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100" title="Export current view to CSV">
                            <i data-lucide="file-spreadsheet" class="h-3.5 w-3.5"></i> CSV
                        </button>
                        <button type="button" id="qs-export-pdf" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100" title="Export current view to PDF">
                            <i data-lucide="file-text" class="h-3.5 w-3.5"></i> PDF
                        </button>
                        <span id="qs-rep-date" class="text-xs font-medium text-gray-400"></span>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="group relative overflow-hidden rounded-xl border border-indigo-300 bg-indigo-500 p-4 shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5">
                        <span class="pointer-events-none absolute -right-3 -top-3 text-indigo-300"><i data-lucide="inbox" class="h-16 w-16"></i></span>
                        <div class="relative flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-indigo-100">Requests Today</div>
                                <div id="qs-rep-today" class="mt-1 text-3xl font-extrabold leading-none text-white">—</div>
                            </div>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-700 text-white shadow-md shadow-indigo-900">
                                <i data-lucide="inbox" class="h-5 w-5"></i>
                            </span>
                        </div>
                        <div class="relative mt-3 space-y-1 border-t border-indigo-400 pt-2 text-[10px] font-medium">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 text-indigo-100"><span class="h-1.5 w-1.5 rounded-full bg-white"></span>Found</span>
                                <span id="qs-rep-today-found" class="font-bold text-white">—</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 text-indigo-100"><span class="h-1.5 w-1.5 rounded-full bg-white"></span>Not Found</span>
                                <span id="qs-rep-today-notfound" class="font-bold text-white">—</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 text-indigo-100"><span class="h-1.5 w-1.5 rounded-full bg-white"></span>Awaiting</span>
                                <span id="qs-rep-today-awaiting" class="font-bold text-white">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-xl border border-sky-300 bg-sky-500 p-4 shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5">
                        <span class="pointer-events-none absolute -right-3 -top-3 text-sky-300"><i data-lucide="search" class="h-16 w-16"></i></span>
                        <div class="relative flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-100">Total Blind / Open</div>
                                <div id="qs-rep-blind" class="mt-1 text-3xl font-extrabold leading-none text-white">—</div>
                            </div>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-700 text-white shadow-md shadow-sky-900">
                                <i data-lucide="search" class="h-5 w-5"></i>
                            </span>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-xl border border-emerald-300 bg-emerald-500 p-4 shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5">
                        <span class="pointer-events-none absolute -right-3 -top-3 text-emerald-300"><i data-lucide="check-circle" class="h-16 w-16"></i></span>
                        <div class="relative flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-100">Total Found</div>
                                <div id="qs-rep-found" class="mt-1 text-3xl font-extrabold leading-none text-white">—</div>
                            </div>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-700 text-white shadow-md shadow-emerald-900">
                                <i data-lucide="check-circle" class="h-5 w-5"></i>
                            </span>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-xl border border-amber-300 bg-amber-500 p-4 shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5">
                        <span class="pointer-events-none absolute -right-3 -top-3 text-amber-300"><i data-lucide="x-circle" class="h-16 w-16"></i></span>
                        <div class="relative flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-100">Total Not Found</div>
                                <div id="qs-rep-notfound" class="mt-1 text-3xl font-extrabold leading-none text-white">—</div>
                            </div>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-700 text-white shadow-md shadow-amber-900">
                                <i data-lucide="x-circle" class="h-5 w-5"></i>
                            </span>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-xl border border-red-300 bg-red-500 p-4 shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5">
                        <span class="pointer-events-none absolute -right-3 -top-3 text-red-300"><i data-lucide="alert-triangle" class="h-16 w-16"></i></span>
                        <div class="relative flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-red-100">Total Missing</div>
                                <div id="qs-rep-missing" class="mt-1 text-3xl font-extrabold leading-none text-white">—</div>
                            </div>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-700 text-white shadow-md shadow-red-900">
                                <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                            </span>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-xl border border-slate-300 bg-slate-500 p-4 shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5">
                        <span class="pointer-events-none absolute -right-3 -top-3 text-slate-300"><i data-lucide="clock" class="h-16 w-16"></i></span>
                        <div class="relative flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-100">Total Awaiting</div>
                                <div id="qs-rep-awaiting" class="mt-1 text-3xl font-extrabold leading-none text-white">—</div>
                            </div>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-700 text-white shadow-md shadow-slate-900">
                                <i data-lucide="clock" class="h-5 w-5"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <!-- Left: Search + Result -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Enter File Number</label>
                        <div class="flex gap-2">
                            <input id="qs-input" type="text" readonly autocomplete="off"
                                placeholder="Click “Select” to choose a file number"
                                class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm uppercase cursor-pointer focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <button type="button" id="qs-pick"
                                class="inline-flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-900 whitespace-nowrap">
                                <i data-lucide="folder-search" class="h-4 w-4"></i><span>Select</span>
                            </button>
                            <button type="button" id="qs-btn"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                <i data-lucide="search" class="h-4 w-4"></i><span>Search</span>
                            </button>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                            <p class="text-xs text-gray-500">Every search returns a clear outcome and next action.</p>
                            <label for="qs-temp-check" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 cursor-pointer"
                                title="Searches the temporary “(T)” copy of the selected file number — resolved as a standalone request, never against the parent file">
                                <input type="checkbox" id="qs-temp-check" class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                                <span class="text-xs font-semibold text-violet-700">Is Temporary File</span>
                                <span class="hidden sm:inline text-[11px] font-medium text-violet-400">appends “(T)” to the file number</span>
                            </label>
                        </div>
                    </div>

                    <div id="qs-result" class="hidden"></div>

                    <!-- SCB Feedback table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                <i data-lucide="message-square-reply" class="h-4 w-4 text-indigo-600"></i> SCB Feedback
                            </h3>
                            <button type="button" id="qs-tbl-refresh" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
                                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Refresh
                            </button>
                        </div>
                        <div class="px-6 py-2.5 border-b border-gray-100">
                            <div class="relative">
                                <i data-lucide="search" class="h-3.5 w-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                <input id="qs-tbl-search" type="text" placeholder="Search file no, title, requester…" class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div id="qs-feedback-table" class="overflow-x-auto"></div>
                    </div>
                </div>

                <!-- Right: File Request Log panel -->
                <aside class="lg:col-span-1 lg:sticky lg:top-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                    <i data-lucide="clipboard-list" class="h-4 w-4 text-indigo-600"></i> File Search History <span id="qs-log-total" class="text-gray-400 font-semibold"></span>
                                </h3>
                                <button type="button" id="qs-fb-refresh" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
                                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Refresh
                                </button>
                            </div>
                            <div id="qs-log-filters" class="mt-3 flex flex-wrap items-center gap-1.5"></div>
                            <div class="mt-2.5 relative">
                                <i data-lucide="search" class="h-3.5 w-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                <input id="qs-log-search" type="text" placeholder="Search file no, title, requester…" class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div id="qs-feedback" class="max-h-[70vh] overflow-y-auto"></div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    @include('components.global-fileno-modal')
    @include('create_file_tracker_page.partials.fr-modal')
    @php
        $reqOffices = ($offices ?? collect())->map(fn ($o) => [
            'code'       => $o->office_code,
            'name'       => $o->office_name,
            'department' => $o->department,
        ])->values();
        $reqOfficers = ($receivingOfficers ?? collect())->map(fn ($o) => [
            'id'            => $o->id,
            'name'          => trim(($o->first_name ?? '') . ' ' . ($o->last_name ?? '')),
            'department_id' => $o->department_id,
        ])->filter(fn ($o) => $o['name'] !== '')->values();
        $reqDeptNameToId = ($departmentIds ?? collect())->mapWithKeys(fn ($d) => [$d->name => $d->id]);
    @endphp
    <script>
    (function () {
        const RESOLVE_URL = "{{ route('create-file-tracker.quick-search.resolve') }}";
        const SLIP_URL    = "{{ route('create-file-tracker.slip') }}";
        const FR_URL      = "{{ route('create-file-tracker.file-request') }}";
        const UPDATE_URL  = "{{ route('create-file-tracker.quick-search.update-status') }}";
        const REDIRECT_LAND_URL = "{{ route('create-file-tracker.quick-search.redirect-director-land') }}";
        const REDIRECT_DCIV_URL = "{{ route('create-file-tracker.quick-search.redirect-dciv-director') }}";
        const FEEDBACK_URL= "{{ route('create-file-tracker.quick-search.scb-feedback') }}";
        // Movement Timeline — same File Tracker "track" endpoint the mobile File
        // Search view uses, so the desktop Quick Search result shows the identical
        // movement/approval history for a file.
        const TRACK_URL   = "{{ url('/api/file-trackers/track') }}";
        // Module context (?url=kangis|sltr|cadastral|st|…) scopes the report, SCB
        // Feedback queue and File Search History to that registry's files only.
        const URL_CTX     = @json(request('url'));
        const CSRF        = "{{ csrf_token() }}";
        const IS_SUPER_ADMIN = @json(auth()->user()->isSuperAdmin());
        // SCB Monitors receive File Search Requests — they don't raise them, so the
        // "Send (Blind) Request to SCB Monitor" buttons are hidden for them. Everyone
        // else (incl. OFS ranked officers) sees them. OFS overrides the SCB hide —
        // an OFS user (even one who is also SCB) sees all.
        const IS_SCB_MONITOR = @json(auth()->user()->isScbMonitor());
        const IS_OFS         = @json(auth()->user()->isOfs());
        // Show the "Send Request" buttons unless the user is an SCB-only monitor.
        const CAN_SEND_FR    = IS_OFS || !IS_SCB_MONITOR;
        // ── Requester cascade data (mirrors Create File Tracker) ──
        const REQ_DEPARTMENTS = @json(($departments ?? collect())->values());
        const REQ_OFFICES     = @json($reqOffices);
        const REQ_OFFICERS    = @json($reqOfficers);
        const REQ_DEPT_NAME_TO_ID = @json($reqDeptNameToId);
        const REGISTRIES      = @json($registries ?? []);
        // Request Purpose lookup (id, name, turnaround_days) — used to build the
        // Request Purpose / Expected Return Date prompt shown before "Log File".
        const REQUEST_PURPOSES = @json($requestPurposes ?? []);
        const REQ_DIRECTORS   = @json($requesterDirectors ?? []);
        const ADD_OFFICER_URL = "{{ route('create-file-tracker.receiving-officers.store') }}";

        const STATUS_OPTIONS = [
            ['IN_TRANSIT', 'In Transit'],
            ['IN_ARCHIVE', 'In Archive'],
            ['IN_POOL_OFFICE', 'In Pool Office'],
            ['FILE_NOT_FOUND', 'File Not Found'],
            ['REFER_TO_ORIGINAL_REGISTRY', 'Refer to Original Registry'],
            ['MISSING_FILE', 'Missing File'],
        ];
        const STATUS_META = {
            IN_TRANSIT:                 { label: 'In Transit',                 cls: 'bg-amber-100 text-amber-800 border-amber-300',  icon: 'truck' },
            IN_ARCHIVE:                 { label: 'In Archive',                 cls: 'bg-green-100 text-green-800 border-green-300',  icon: 'archive' },
            IN_ARCHIVE_FOUND:           { label: 'In Archive — Found',         cls: 'bg-green-100 text-green-800 border-green-300',  icon: 'check-circle' },
            IN_ARCHIVE_NOT_FOUND:       { label: 'Not Found In Archive',       cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
            IN_POOL_OFFICE:             { label: 'In Pool Office',             cls: 'bg-blue-100 text-blue-800 border-blue-300',    icon: 'folder-search' },
            IN_POOL_OFFICE_FOUND:       { label: 'In Pool Office — Found',     cls: 'bg-green-100 text-green-800 border-green-300',  icon: 'check-circle' },
            IN_POOL_OFFICE_NOT_FOUND:   { label: 'In Pool Office — Not Found', cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
            PENDING_FILE:               { label: 'Pending (Not Indexed)',      cls: 'bg-gray-100 text-gray-800 border-gray-300',    icon: 'help-circle' },
            BLIND_REQUEST_SENT:         { label: 'Blind Request Sent',         cls: 'bg-indigo-100 text-indigo-800 border-indigo-300', icon: 'send' },
            FILE_NOT_FOUND:             { label: 'File Not Found',             cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
            MISSING_FILE:               { label: 'Missing File',               cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
            REFER_TO_ORIGINAL_REGISTRY: { label: 'Refer to Original Registry', cls: 'bg-gray-100 text-gray-800 border-gray-300',    icon: 'corner-up-right' },
        };

        const input  = document.getElementById('qs-input');
        const btn    = document.getElementById('qs-btn');
        const pickBtn= document.getElementById('qs-pick');
        const result = document.getElementById('qs-result');
        const tempChk= document.getElementById('qs-temp-check');

        // Temporary "(T)" suffix helpers — a temp file is searched as a standalone
        // record, so the checkbox appends "(T)" to the selected number (and unticking
        // strips it back to the parent number).
        const hasTemp   = v => /\(\s*T\s*\)\s*$/i.test(v);
        const stripTemp = v => v.replace(/\s*\(\s*T\s*\)\s*$/i, '');
        const withTemp  = v => hasTemp(v) ? v : v + '(T)';
        if (tempChk) tempChk.addEventListener('change', () => {
            const v = input.value.trim();
            if (!v) return;
            input.value = tempChk.checked ? withTemp(v) : stripTemp(v);
            search();
        });

        const esc = v => (v == null ? '' : String(v)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        /**
         * The applicant's passport photograph for a file, as a thumbnail.
         *
         * Most files have none — a corporate file never carries one, and neither does a
         * file commissioned before the photograph was captured — so the empty state falls
         * back to the file title's initials instead of a broken frame.
         */
        const filePassportThumb = (url, fileTitle, size = 'h-14 w-14') => {
            const name = String(fileTitle || '').trim();
            const initials = name
                ? name.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase()
                : '—';

            if (!url) {
                return `<div class="${size} flex-shrink-0 rounded-md border border-dashed border-gray-300 bg-gray-50 text-gray-400 flex items-center justify-center text-xs font-semibold"
                             title="No passport photograph on record for this file">${esc(initials)}</div>`;
            }

            return `<img src="${esc(url)}" alt="Passport photograph of ${esc(name || 'the applicant')}"
                         class="${size} flex-shrink-0 rounded-md border border-gray-200 object-cover bg-gray-50"
                         loading="lazy" onerror="this.style.visibility='hidden';">`;
        };

        const row = (l, v) => v ? `<div class="flex justify-between gap-4 py-2 border-b border-gray-100 last:border-0">
            <span class="text-xs font-medium text-gray-500">${esc(l)}</span>
            <span class="text-sm text-gray-800 text-right">${esc(v)}</span></div>` : '';
        // Same shape as row(), but the value is a person: show their profile picture
        // beside the name. Falls back to initials when they have not uploaded one.
        // Clicking the officer opens the shared profile card (photo, username, phone) —
        // the same one the creator cells across the app use, already mounted by the layout.
        const personRow = (l, name, photo) => name ? `<div class="flex justify-between gap-4 py-2 border-b border-gray-100 last:border-0">
            <span class="text-xs font-medium text-gray-500">${esc(l)}</span>
            <span class="text-sm text-gray-800 text-right upc-trigger" data-user-card data-user-name="${esc(name)}" title="Click to view profile">
                ${window.UserAvatar ? window.UserAvatar.withName(photo, name, 44) : esc(name)}</span></div>` : '';
        const money = (l, v) => (v == null || v === '') ? '' : row(l, '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        // A duplicate_fileno flag only diverts the file to the Director Land when the
        // category carries a duplication to resolve (Duplicate / CofO / W-C-R). Temporary
        // files are flagged for the badge only and still use the normal SCB workflow.
        const directsToLand = d => !!(d.duplicate_flag && d.duplicate_flag.directs_to_land);
        // A file under DCIV investigation (own registry = DCIV Registry, or flagged
        // dciv_status=1) is re-directed to the DCIV Director rather than sent to the
        // SCB — mirrors directsToLand(). Derived from the server-computed next_action
        // label (see FileLocationResolver::actionMetaFor()) instead of re-deriving the
        // DCIV rule client-side.
        const isDcivFile = d => /DCIV Director/.test(d.next_action || '');
        // The front desk can override that routing with the checkbox on the result card
        // and send a normal File Search Request to the SCB Monitor pool instead — e.g. the
        // DCIV Director is away, or the investigation is closed but the flag has not been
        // cleared yet. The override lives on the result object so actionButtons() and the
        // requester form both read the same switch. Nothing is overridden server-side:
        // sendFileRequest() has no DCIV guard, so an overridden request is an ordinary FR.
        const isDciv = d => isDcivFile(d) && !d.dciv_override;
        // The searched number is registered in BOTH file_indexings and duplicate_fileno,
        // so it no longer identifies one physical file. The user must pick the exact
        // record before anything can be sent — and the request must NOT fall through to
        // the SCB just because one of the matches happens to be indexed.
        const hasCandidates = d => Array.isArray(d.duplicate_candidates) && d.duplicate_candidates.length > 1;
        const hasIndexedCandidate = d => Array.isArray(d.duplicate_candidates)
            && d.duplicate_candidates.some(c => c.source === 'file_indexings');
        const duplicateFlagHtml = d => d.duplicate_flag ? `
            <div class="mb-3 rounded-lg px-4 py-3" style="background:${d.duplicate_flag.color}14;border:1px solid ${d.duplicate_flag.color}55;">
                <div class="flex items-center gap-2">
                    <i data-lucide="copy" class="h-4 w-4 shrink-0" style="color:${d.duplicate_flag.color};"></i>
                    <span class="text-sm font-bold" style="color:${d.duplicate_flag.color};">${esc(d.duplicate_flag.label)}</span>
                    ${d.duplicate_flag.comment ? `<span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap" style="color:${d.duplicate_flag.color};background:${d.duplicate_flag.color}1a;">${esc(d.duplicate_flag.comment)}</span>` : ''}
                </div>
                ${(d.duplicate_flag.entries && d.duplicate_flag.entries.length > 1) ? `
                <div class="mt-2 pl-6 space-y-1">
                    ${d.duplicate_flag.entries.map(e => `
                    <div class="flex items-baseline gap-2 text-xs">
                        <span class="font-semibold" style="color:${d.duplicate_flag.color};">${esc(e.file_number)}</span>
                        <span class="text-gray-600">${esc(e.file_title || '—')}</span>
                    </div>`).join('')}
                </div>` : (d.duplicate_flag.file_title ? `
                <div class="mt-1 pl-6 text-xs text-gray-600">${esc(d.duplicate_flag.file_title)}</div>` : '')}
            </div>` : '';
        // The record the user picked, as {record_id, source}. Null until they choose.
        let selectedCandidate = null;

        function pickFileNumber() {
            if (typeof GlobalFileNoModal === 'undefined') { search(); return; }
            GlobalFileNoModal.open({
                // When "Is Temporary File" is checked, restrict the MLS smart selector
                // to temporary "(T)" file numbers only.
                tempOnly: !!(tempChk && tempChk.checked),
                callback: function (data) {
                    let fileNumber = (data.fileNumber || '').toString().trim();
                    if (!fileNumber) return;
                    if (tempChk && tempChk.checked) {
                        fileNumber = withTemp(fileNumber);
                    } else if (tempChk && hasTemp(fileNumber)) {
                        // A "(T)" number picked straight from the selector — keep the
                        // checkbox in sync so unticking it strips back to the parent.
                        tempChk.checked = true;
                    }
                    input.value = fileNumber;
                    search();
                }
            });
        }

        function actionButtons(d) {
            const out = [];
            const fno = encodeURIComponent(d.file_number);
            if (d.status === 'IN_TRANSIT' && d.file_tracker_id) {
                // Re-direct the request straight to the office currently holding the file
                // (its last receiving officer) instead of routing it to the SCB Monitor.
                const showRedirect = d.can_redirect && CAN_SEND_FR;
                if (showRedirect) {
                    const office = d.receiving_officer_name || d.current_location || 'Current Office';
                    out.push(`<button type="button" data-redirect
                        class="inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">
                        <i data-lucide="user-check" class="h-4 w-4"></i> Re-direct Request to ${esc(office)}</button>`);
                }
                // The slip can only be printed once the re-direct request has been sent —
                // disabled/greyed until then (only gated when a redirect button is shown).
                /*
                const slipDisabled = showRedirect;
                out.push(`<a href="/create-file-tracker/${d.file_tracker_id}/request-sheet" target="_blank" data-print-slip
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white ${slipDisabled ? 'bg-gray-300 cursor-not-allowed pointer-events-none opacity-60' : 'bg-amber-600 hover:bg-amber-700'}"
                    ${slipDisabled ? 'aria-disabled="true" tabindex="-1"' : ''}>
                    <i data-lucide="printer" class="h-4 w-4"></i> Print Tracking Confirmation Slip</a>`);
                */
            }
            // SCB confirmed Found -> log the file (redirect to create-file-tracker, prefilled).
            if (d.can_log) {
                out.push(`<button type="button" data-log class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    <i data-lucide="file-plus" class="h-4 w-4"></i> Log File</button>`);
                // Temporary file toggle — carried to Log a File, where the TMP code is
                // appended to the Tracking ID so the temporary file is tracked standalone.
                out.push(`<label class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 cursor-pointer"
                    title="Appends the TMP code to the Tracking ID">
                    <input type="checkbox" data-temp-file class="h-3.5 w-3.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    <span class="text-xs font-semibold text-amber-700">Is Temporary File</span></label>`);
            }
            // For In-Transit files the dedicated "Print Tracking Confirmation Slip" button
            // (above) already covers printing, so suppress the generic "Print Slip" button.
            if (d.slip_variant && d.status !== 'IN_TRANSIT') {
                const labels = { tracking_sheet:'Print Tracking Sheet', missing:'Print Missing File Slip', refer_registry:'Print Refer-to-Original-Registry Slip', tracking_confirmation:'Print Slip' };
                out.push(`<a href="${SLIP_URL}?file_number=${fno}&variant=${d.slip_variant}" target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    <i data-lucide="printer" class="h-4 w-4"></i> ${labels[d.slip_variant] || 'Print Slip'}</a>`);
            }
            if (d.can_send_fr && CAN_SEND_FR) {
                if (directsToLand(d)) {
                    // Duplicate / CofO / W-C-R files must NOT be blind-searched by the SCB —
                    // they are re-directed to the Director Land (Land Department) to resolve
                    // the duplication. Temporary files are excluded and fall through to the
                    // normal SCB workflow below.
                    // With a selection list on screen the button stays the same action
                    // but is inert until a record is chosen: the request carries that
                    // record's id, and the shared file number cannot stand in for it.
                    const needsPick = hasCandidates(d);
                    out.push(`<button type="button" data-redirect-land ${needsPick ? 'disabled' : ''}
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white ${needsPick ? 'bg-purple-300 cursor-not-allowed' : 'bg-purple-600 hover:bg-purple-700'}">
                        <i data-lucide="user-check" class="h-4 w-4"></i> Send Request to Director Land</button>`);
                } else if (isDcivFile(d)) {
                    // A file under DCIV investigation is not blind-searched by the SCB —
                    // it is re-directed to the DCIV Director to resolve. Both buttons are
                    // rendered up front and the override checkbox swaps which one is
                    // visible, so toggling it costs no re-render (and no re-fetch of the
                    // digital library / movement timeline).
                    const ov = !!d.dciv_override;
                    const scbLabel = d.is_blind ? 'Send Blind Request to SCB Monitor' : 'Send File Search Request to SCB Monitor';
                    out.push(`<button type="button" data-redirect-dciv style="background: linear-gradient(to right, #0b3d2e, #065f46, #0b3d2e);${ov ? 'display:none;' : ''}" class="inline-flex items-center gap-2 rounded-lg hover:opacity-90 px-4 py-2 text-sm font-semibold text-white">
                        <i data-lucide="shield-alert" class="h-4 w-4"></i> Re-direct To DCIV Director</button>`);
                    out.push(`<button type="button" data-fr ${ov ? '' : 'style="display:none;"'} class="inline-flex items-center gap-2 rounded-lg ${d.is_blind ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'} px-4 py-2 text-sm font-semibold text-white">
                        <i data-lucide="send" class="h-4 w-4"></i> ${scbLabel}</button>`);
                } else if (d.is_missing_file) {
                    // File exists in missing_files — SCB already searched and could not find it.
                    // Change button to "Send Blind Request to the Original Registry" instead of
                    // routing through SCB. The backend saves the request without notifying SCB.
                    const label = d.is_blind ? 'Send Blind Request to the Original Registry' : 'Send File Search Request to the Original Registry';
                    const frCls = d.is_blind ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700';
                    out.push(`<button type="button" data-fr data-missing-file="1" class="inline-flex items-center gap-2 rounded-lg ${frCls} px-4 py-2 text-sm font-semibold text-white">
                        <i data-lucide="send" class="h-4 w-4"></i> ${label}</button>`);
                } else {
                    const label = d.next_action || (d.is_blind ? 'Send Blind Request to SCB Monitor' : 'Send File Search Request to SCB Monitor');
                    const frCls = d.is_blind ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700';
                    out.push(`<button type="button" data-fr class="inline-flex items-center gap-2 rounded-lg ${frCls} px-4 py-2 text-sm font-semibold text-white">
                        <i data-lucide="send" class="h-4 w-4"></i> ${label}</button>`);
                }
            }
            return out.join('');
        }

        // In-transit files: a one-line note under the actions naming the Receiving Officer
        // (and their department) currently holding the file — mirrors the mobile redirect note.
        function redirectSubline(d) {
            if (d.status !== 'IN_TRANSIT' || !d.can_redirect) return '';
            const officer = (d.receiving_officer_name || '').trim();
            let dept = (d.receiving_department || '').trim();
            if (dept && !/department$/i.test(dept)) dept = dept + ' Department';
            if (officer) {
                const deptPart = (dept && dept.toLowerCase() !== officer.toLowerCase()) ? ` (${esc(dept)})` : '';
                return `<p class="px-6 pb-4 -mt-1 text-xs text-gray-500">Receiving Officer: <strong class="text-gray-700">${esc(officer)}</strong>${deptPart} · currently holding the file.</p>`;
            }
            const office = d.current_location;
            if (office) {
                return `<p class="px-6 pb-4 -mt-1 text-xs text-gray-500">This request will be sent to <strong class="text-gray-700">${esc(office)}</strong>, which currently holds the file.</p>`;
            }
            return '';
        }

        // Map a registry name to its Create File Tracker module (?url=…) so the Log
        // File button lands on the registry-appropriate page (KANGIS → ?url=kangis,
        // Cadastral → ?url=cadastral, …). Land/Deeds registries use the base page.
        function registryToModule(registry) {
            const r = String(registry || '').toLowerCase();
            if (r.includes('kangis'))  return 'kangis';
            if (r.includes('sltr'))    return 'sltr';
            if (r.includes('cadastr')) return 'cadastral';
            if (r.includes('dciv'))    return 'dciv';
            if (r.includes('survey'))  return 'survey';
            if (r.includes('sit') || r === 'st registry') return 'st';
            return ''; // Land / Deeds / other → base Create File Tracker page
        }

        function registryBadgeClass(registry) {
            const r = String(registry || '').toLowerCase();
            if (r.includes('kangis')) {
                return 'bg-orange-50 text-orange-800 border-orange-200';
            }
            if (r.includes('sltr')) {
                return 'bg-lime-50 text-lime-800 border-lime-200';
            }
            if (r.includes('cadastr')) {
                return 'bg-rose-50 text-rose-800 border-rose-200';
            }
            if (r.includes('dciv')) {
                return 'bg-emerald-50 text-emerald-800 border-emerald-200';
            }
            if (r.includes('survey')) {
                return 'bg-pink-50 text-pink-800 border-pink-200';
            }
            if (r.includes('sit') || r === 'st registry') {
                return 'bg-sky-50 text-sky-800 border-sky-200';
            }
            return 'bg-gray-50 text-gray-800 border-gray-200';
        }

        function logFile(d) {
            const params = new URLSearchParams({ file_number: d.file_number || '' });
            if (d.file_title) params.set('file_title', d.file_title);
            // Land the user on the page that matches the file's registry.
            const mod = registryToModule(d.registry);
            if (mod) params.set('url', mod);
            // Backfill ALL the requester/office details captured on the file request
            // into the Create File Tracker form. The details are carried on the FOUND
            // File Search Request (d.fr_found); fall back to any top-level fields.
            const src = d.fr_found || {};
            const reqOfficer    = src.receiving_officer    || d.receiving_officer;
            const reqOffice     = src.requester_office     || d.requester_office;
            const reqOfficeCode  = src.requester_office_code || d.requester_office_code;
            const reqDepartment = src.requester_department || d.requester_department;
            // Origin registry captured on the request (falls back to the file's registry).
            const reqRegistry   = src.registry || d.registry;
            if (reqOfficer)    params.set('req_officer', reqOfficer);
            if (reqOffice)     params.set('req_office', reqOffice);
            if (reqOfficeCode) params.set('req_office_code', reqOfficeCode);
            if (reqDepartment) params.set('req_department', reqDepartment);
            if (reqRegistry)   params.set('req_registry', reqRegistry);
            // Signal the Log-a-File page to grey out (lock) the auto-filled office details.
            params.set('lock_office', '1');
            // Temporary file — Log a File pre-checks its "Is Temporary File" toggle and
            // appends the TMP code to the Tracking ID (standalone tracking only).
            const tempCb = result.querySelector('[data-temp-file]');
            if (tempCb && tempCb.checked) params.set('is_temp', '1');
            // Request Purpose only — backfilled into #request-purpose, which then derives
            // the Expected Return Date from its turnaround on the Log a File page. The
            // date is deliberately NOT carried from request time: the return clock starts
            // when the file is logged out.
            if (d.request_purpose_id) params.set('req_purpose_id', d.request_purpose_id);
            window.location = '/create-file-tracker?' + params.toString();
        }

        // Ask for the Request Purpose before handing off to logFile(). Shared by both
        // "Log File" entry points (the main search result and each SCB Feedback row) so
        // the purpose carries over to the Create File Tracker form instead of being
        // re-entered there. The Expected Return Date is set on that form, not here.
        function promptForRequestPurposeAndDeadline(d) {
            const purposeOptions = REQUEST_PURPOSES.map(p =>
                `<option value="${p.id}" data-turnaround-days="${p.turnaround_days}">${esc(p.name)}</option>`
            ).join('');

            Swal.fire({
                title: 'Request Purpose & Timeline',
                html: `
                    <div class="text-left space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Request Purpose *</label>
                            <select id="qs-log-purpose" class="swal2-select" style="display:block;width:100%;margin:0;">
                                <option value="">Select the reason this file is being requested</option>
                                ${purposeOptions}
                            </select>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Continue to Log File',
                confirmButtonColor: '#059669',
                focusConfirm: false,
                preConfirm: () => {
                    const purposeSel = document.getElementById('qs-log-purpose');
                    const purposeId = purposeSel.value;
                    const purposeName = purposeSel.selectedOptions[0]?.text || '';
                    if (!purposeId) {
                        Swal.showValidationMessage('Please select a Request Purpose.');
                        return false;
                    }
                    return { purposeId, purposeName };
                }
            }).then(result => {
                if (!result.isConfirmed) return;
                d.request_purpose_id = result.value.purposeId;
                d.request_purpose_name = result.value.purposeName;
                logFile(d);
            });
        }

        // Entry point for both "Log File" buttons. The Request Purpose is normally
        // already captured on the Requester form when the File Search Request was
        // raised — in that case skip straight to logFile(). Only prompt as a fallback
        // (e.g. legacy/blind requests raised before this existed, or a file logged
        // with no prior FR). The Expected Return Date is always set on Log a File.
        function handleLogFileClick(d, purpose) {
            const src = purpose || {};
            if (src.request_purpose_id) {
                d.request_purpose_id = src.request_purpose_id;
                d.request_purpose_name = src.request_purpose_name || '';
                logFile(d);
                return;
            }
            promptForRequestPurposeAndDeadline(d);
        }

        // ── Movement Timeline (ported from mobile File Search's "View Movement
        // timeline" panel) — fetches the same /api/file-trackers/track/{file} data
        // and renders it with the desktop's Tailwind styling instead of mobile's
        // inline CSS-variable theme.
        async function toggleMovementTimeline(fileNumber, detailsEl, originRegistry, rackShelf) {
            const container = document.getElementById('qs-movement-timeline');
            if (!container || !detailsEl || !detailsEl.open) return;
            if (container.dataset.loaded === '1') return;

            container.innerHTML = `<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">Loading movement history for <strong>${esc(fileNumber)}</strong>…</div>`;

            // A file commissioned through KLAES has not reached the archive yet — its
            // DIIT "File Commissioning" line opens the timeline instead of this row.
            // Set from the response below (is_commissioned).
            let isCommissionedFile = false;

            const archiveHomeRowHtml = `
                <div class="relative pl-6 pb-3">
                    <span class="absolute left-[6px] top-0 bottom-0 w-0.5 bg-gray-200"></span>
                    <span class="absolute left-0 top-px h-[15px] w-[15px] rounded-full bg-emerald-500 border-2 border-gray-200 flex items-center justify-center">
                        <i data-lucide="archive" class="h-2 w-2 text-white"></i>
                    </span>
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Archive / Registry</div>
                            <div class="text-[13px] font-bold text-gray-900">${esc(originRegistry || 'Registry / Archive')}${rackShelf ? ` — Shelf/Rack ${esc(rackShelf)}` : ''}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap">In Archive</span>
                    </div>
                </div>`;

            // A file indexed since FileRangeTrackingService shipped already carries a
            // REAL opening row (_range_home), placed by its registry range — it may say
            // "In Pool Office" where this hard-coded row can only say "In Archive".
            // Set from the merged history below; drawing both gives two home lines.
            let hasRangeHomeRow = false;

            const homeRow = () => ((isCommissionedFile || hasRangeHomeRow) ? '' : archiveHomeRowHtml);

            try {
                const res  = await fetch(`${TRACK_URL}/${encodeURIComponent(fileNumber)}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json().catch(() => null);
                isCommissionedFile = Boolean(json && json.data && json.data.is_commissioned);
                // A 404 just means the file has never been tracked — that is an empty
                // history, not a failure. Only a real error (500 / unparseable body)
                // should surface the red "could not load" state.
                const neverTracked = res.status === 404 || (json && json.success === false && /not found/i.test(String(json.message || '')));
                if (neverTracked) {
                    container.innerHTML = `${homeRow()}<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">No movement history available for this file.</div>`;
                } else if (!json || !json.success || !json.data) {
                    container.innerHTML = `${homeRow()}<div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-600">Could not load movement history.</div>`;
                } else {
                    const currentLogs = Array.isArray(json.data.movement_history) ? json.data.movement_history : [];
                    const priorLogs   = Array.isArray(json.data.prior_movements) ? json.data.prior_movements : [];
                    const allLogs     = [...priorLogs, ...currentLogs].sort(compareMovementEntries);
                    hasRangeHomeRow   = allLogs.some(e => e && e._range_home);
                    if (!allLogs.length) {
                        container.innerHTML = `${homeRow()}<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">No movement history available for this file.</div>`;
                    } else {
                        const approvalPurposes = ['recommendation', 'approval'];
                        const movementLogs = allLogs.filter(e => !approvalPurposes.includes(String(e.purpose || '').toLowerCase()));
                        const approvalLogs = allLogs.filter(e => approvalPurposes.includes(String(e.purpose || '').toLowerCase()));
                        // Request Purpose / Timeline / Expected Return Date belong to the
                        // current tracker (one per tracking cycle), applied to every row —
                        // mirrors the desktop File Log Table / mobile behaviour.
                        const trackerMeta = {
                            // id -> photo URL for every officer named on the log, resolved
                            // server-side in one primed query (see FileTrackerApiController::track).
                            officerPhotos: json.data.officer_photos || {},
                            requestPurposeName: json.data.request_purpose_name || '',
                            timelineStatus: json.data.timeline_status || null,
                            daysUntilDeadline: (json.data.days_until_deadline === null || json.data.days_until_deadline === undefined) ? null : Number(json.data.days_until_deadline),
                            expectedReturnDate: json.data.deadline || null,
                        };
                        container.innerHTML = `
                            ${priorLogs.length ? `<div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[11px] text-gray-500">Tracking cycles for this file.</div>` : ''}
                            <div>
                                ${homeRow()}
                                ${movementLogs.length ? movementLogs.map(e => renderMovementRow(e, trackerMeta)).join('') : `<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">No physical movement entries found. Approval steps may still be present below.</div>`}
                            </div>
                            ${approvalLogs.length ? `
                            <div class="mt-4 mb-2 text-[10px] font-extrabold uppercase tracking-wider text-gray-500">Workflow Approvals</div>
                            <div class="space-y-2">${approvalLogs.map(e => renderApprovalRow(e)).join('')}</div>` : ''}`;
                        container.dataset.loaded = '1';
                    }
                }
            } catch (e) {
                container.innerHTML = `<div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-600">Error loading movement history: ${esc(e.message)}</div>`;
            }
            if (window.lucide) window.lucide.createIcons();
        }

        function compareMovementEntries(a, b) {
            return movementEntryTimestamp(a) - movementEntryTimestamp(b);
        }

        function movementEntryTimestamp(entry) {
            const parseDateTime = (date, time) => {
                const d = (date || '').toString().trim();
                if (!d) return null;
                const t = (time || '').toString().trim() || '00:00';
                const parsed = Date.parse(`${d} ${t}`);
                return Number.isNaN(parsed) ? null : parsed;
            };
            const inTs = parseDateTime(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
            if (inTs !== null) return inTs;
            const outTs = parseDateTime(entry.log_out_date || entry.logOutDate, entry.log_out_time || entry.logOutTime);
            if (outTs !== null) return outTs;
            const createdTs = Date.parse(entry.created_at || entry.createdAt || '');
            return Number.isNaN(createdTs) ? Number.POSITIVE_INFINITY : createdTs;
        }

        function mtToAmPm(timeStr) {
            if (!timeStr) return '';
            const parts = timeStr.toString().trim().split(':');
            if (parts.length < 2) return timeStr;
            let h = parseInt(parts[0], 10);
            const m = parts[1].padStart(2, '0');
            if (isNaN(h)) return timeStr;
            const period = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${period}`;
        }

        function formatMovementDate(date, time) {
            const d = (date || '').toString().trim();
            if (!d) return '—';
            if (!time) return esc(d);
            return `${esc(d)} ${esc(mtToAmPm(time))}`;
        }

        function resolveMovementStatus(entry) {
            const rawOverride = (entry.status_label || entry.statusLabel || entry.new_status || entry.newStatus || '').toString().trim();
            const rawStatus = (entry.status || '').toString().trim().toLowerCase();
            const normalize = (value) => value.toLowerCase().replace(/_/g, ' ');

            if (rawOverride) {
                switch (normalize(rawOverride)) {
                    case 'log-in':
                    case 'log in':
                        return { label: 'Log-in', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
                    case 'log-out':
                    case 'log out':
                        return { label: 'Log-out', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
                    case 'pending acceptance':
                    case 'in-transit':
                    case 'in transit':
                        return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
                    case 'rejected':
                        return { label: 'Rejected', style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
                    case 'cancelled':
                    case 'canceled':
                        return { label: 'Cancelled', style: 'background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;' };
                    default:
                        return { label: rawOverride, style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
                }
            }
            switch (rawStatus) {
                case 'pending_acceptance':
                    return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
                case 'active':
                    return { label: 'Log-out', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
                case 'completed':
                    return { label: 'Log-in', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
                case 'rejected':
                    return { label: 'Rejected', style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
                default:
                    return { label: String(entry.status || 'Completed').replace(/_/g, ' '), style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
            }
        }

        // Green/Amber/Red timeline label + colour for a tracker — mirrors
        // FileTracker::getTimelineStatusAttribute() / the mobile day-count badge.
        function formatTimelineMeta(meta) {
            const byStatus = {
                green:   { color: '#166534', bg: '#d1fae5', border: '#a7f3d0' },
                amber:   { color: '#78350f', bg: '#fef9c3', border: '#fde68a' },
                red:     { color: '#b91c1c', bg: '#fee2e2', border: '#fecaca' },
                // Clock not started — the file has not been logged out yet.
                pending: { color: '#475569', bg: '#e2e8f0', border: '#cbd5e1' },
            };
            if (!meta || !meta.timelineStatus || !byStatus[meta.timelineStatus]) return null;
            if (meta.timelineStatus === 'pending') {
                return { label: 'Pending', icon: 'fa-clock', ...byStatus.pending };
            }
            const days = meta.daysUntilDeadline;
            let label;
            if (days === null || days === undefined) {
                label = { green: 'On Track', amber: 'Due Soon', red: 'Overdue' }[meta.timelineStatus];
            } else if (days > 0) {
                label = `${days} day${days === 1 ? '' : 's'} left`;
            } else if (days === 0) {
                label = 'Due today';
            } else {
                const abs = Math.abs(days);
                label = `${abs} day${abs === 1 ? '' : 's'} overdue`;
            }
            return { label, ...byStatus[meta.timelineStatus] };
        }

        // Timeline cell for an SCB feedback row. A file search request never counts
        // down: the return clock starts when the file is logged OUT, so a request that
        // is still being searched for (or found but not yet logged) shows a neutral
        // "Pending" clock rather than a number of days. Mirrors
        // FileSearchRequest::getTimelineStatusAttribute().
        function fbTimelineBadge(r) {
            const meta = formatTimelineMeta({ timelineStatus: 'pending', daysUntilDeadline: null });
            return `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold" style="background:${meta.bg};color:${meta.color};border:1px solid ${meta.border};" title="The return clock starts when the file is logged out">`
                 + `<i data-lucide="clock" class="h-3 w-3"></i> ${esc(meta.label)}</span>`;
        }

        function formatExpectedReturnDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return esc(String(value).slice(0, 10));
            const dd = String(d.getDate()).padStart(2, '0');
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            return `${dd}/${mm}/${d.getFullYear()}`;
        }

        function renderMovementRow(entry, trackerMeta) {
            const office = esc(entry.office_name || entry.office || entry.receiving_office_name || 'Unknown');
            const officerNameRaw = entry.receiving_officer_name || entry.receivingOfficerName || entry.accepted_by_name || '-';
            const officer = esc(officerNameRaw);
            const officerPhoto = (trackerMeta && trackerMeta.officerPhotos)
                ? (trackerMeta.officerPhotos[String(entry.receiving_officer_id ?? entry.receivingOfficerId ?? '')] || null)
                : null;
            const officerHtml = window.UserAvatar
                ? window.UserAvatar.withName(officerPhoto, officerNameRaw, 24)
                : officer;
            const status = resolveMovementStatus(entry);
            const hasLogIn = status.label === 'Log-in' || status.label === 'Completed';
            const inDate = hasLogIn
                ? formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime)
                : '-';
            const outDateRaw = entry.log_out_date || entry.logOutDate;
            const outTimeRaw = entry.log_out_time || entry.logOutTime;
            const outDate = outDateRaw
                ? formatMovementDate(outDateRaw, outTimeRaw)
                : ['active', 'pending_acceptance', 'in-transit', 'in transit'].includes((entry.status || '').toString().trim().toLowerCase())
                    ? 'In transit' : '-';
            const notes = entry.notes ? `<div class="mt-2 text-xs text-gray-500">${esc(entry.notes)}</div>` : '';
            const timelineMeta = formatTimelineMeta(trackerMeta);
            const requestPurposeName = trackerMeta && trackerMeta.requestPurposeName ? esc(trackerMeta.requestPurposeName) : '—';
            const expectedReturnDate = formatExpectedReturnDate(trackerMeta && trackerMeta.expectedReturnDate);
            const delayReason = entry.delay_reason ? esc(entry.delay_reason) : '—';

            return `
                <div class="relative pl-6 pb-4">
                    <span class="absolute left-[6px] top-0 bottom-0 w-0.5 bg-gray-200"></span>
                    <span class="absolute left-0 top-px h-[15px] w-[15px] rounded-full bg-indigo-600 border-2 border-gray-200 flex items-center justify-center">
                        <i data-lucide="truck" class="h-2 w-2 text-white"></i>
                    </span>
                    <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5">
                        <div class="flex items-start justify-between gap-2 flex-wrap">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Office</div>
                                <div class="text-[13px] font-bold text-gray-900">${office}</div>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap" style="${status.style}">${esc(status.label)}</span>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                            <!-- Officer photo per log row. Reuses the same UserAvatar the
                                 "Receiving Officer (holder)" line below the timeline uses, so
                                 one face renders identically in both places; it falls back to
                                 initials for rows that name a place ("Archive") rather than a
                                 person, and for officers with no photo on file. -->
                            <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Receiving Officer</div><div class="text-gray-800 font-semibold">${officerHtml}</div></div>
                            <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Timeline</div><div>${timelineMeta ? `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold" style="background:${timelineMeta.bg};color:${timelineMeta.color};border:1px solid ${timelineMeta.border};">${timelineMeta.icon ? `<i class="fas ${timelineMeta.icon}"></i>` : ''}${esc(timelineMeta.label)}</span>` : '<span class="text-gray-400">—</span>'}</div></div>
                            <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Log In</div><div class="text-gray-800 font-semibold">${inDate}</div></div>
                            <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Log Out</div><div class="text-gray-800 font-semibold">${outDate}</div></div>
                            <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Request Purpose</div><div class="text-gray-800 font-semibold">${requestPurposeName}</div></div>
                            <div><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Expected Return</div><div class="text-gray-800 font-semibold">${expectedReturnDate}</div></div>
                            <div class="col-span-2"><div class="text-[10px] font-bold uppercase text-gray-400 mb-0.5">Delay Reason</div><div class="text-gray-800 font-semibold">${delayReason}</div></div>
                        </div>
                        ${notes}
                    </div>
                </div>`;
        }

        function renderApprovalRow(entry) {
            const office = esc(entry.office_name || entry.office || entry.receiving_office_name || 'Unknown');
            const officer = esc(entry.receiving_officer_name || entry.accepted_by_name || '—');
            const eventDate = formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
            const purposeLabel = String(entry.purpose || '').toLowerCase() === 'recommendation' ? 'Recommendation' : 'Approval';
            const notes = entry.notes ? `<div class="mt-2 text-xs text-gray-500">${esc(entry.notes)}</div>` : '';
            return `
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div>
                            <div class="text-xs font-bold text-gray-800">${purposeLabel}</div>
                            <div class="mt-1 text-xs text-gray-500">${eventDate}</div>
                        </div>
                        <div class="text-right text-xs">
                            <div class="font-semibold text-gray-800">${office}</div>
                            <div class="text-gray-500">${officer}</div>
                        </div>
                    </div>
                    ${notes}
                </div>`;
        }

        // File Request Type of the open movement cycle (In-Transit / Requested),
        // resolved server-side by FileLocationResolver::requestTypeFor(). A request
        // type, not a log status, so it is labelled as such. Nothing when null.
        const REQUEST_TYPE_CLS = {
            IN_TRANSIT: 'bg-amber-50 text-amber-800 border-amber-300',
            REQUESTED:  'bg-violet-50 text-violet-800 border-violet-300',
        };
        function requestTypeTagHtml(rt) {
            if (!rt || !rt.label) return '';
            const cls = REQUEST_TYPE_CLS[rt.key] || 'bg-gray-50 text-gray-700 border-gray-300';
            return `<span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${cls}" title="File Request Type">
                <span class="font-medium opacity-75">Request Type:</span> ${esc(rt.label)}</span>`;
        }

        function render(d) {
            const meta = STATUS_META[d.status] || { label:d.status, cls:'bg-gray-100 text-gray-800 border-gray-300', icon:'file' };
            const showsRegistry = (d.status === 'REFER_TO_ORIGINAL_REGISTRY' && d.registry);
            const metaLabel = showsRegistry ? d.registry : meta.label;
            // When the badge shows a registry name, colour it by registry
            // (KANGIS → orange, SLTR → lime, …) instead of the neutral status grey.
            const metaCls = showsRegistry ? registryBadgeClass(d.registry) : meta.cls;
            // SCB has confirmed the file is physically present (…_FOUND, but not …NOT_FOUND).
            const isFound = /_FOUND$/.test(d.status || '') && !/NOT_FOUND/.test(d.status || '');
            // SCB has reported the file as Not Found (Missing / Pending).
            const isNotFound = /NOT_FOUND/.test(d.status || '');

            // Root of Title / Original Holder / Current Holder — three DIFFERENT
            // concepts (client spec 2026-08-20 §12), resolved server-side by
            // TitleHolderResolver. This block is authoritative; the chronological
            // chain below it is context only, which is why its nodes no longer
            // label anybody "Original Holder".
            //
            // Root of Title is legitimately blank on a file where nothing predates
            // the Ministry grant — render the dash rather than inventing a name
            // from the earliest party, which is precisely the assumption the spec
            // removes.
            const titleHoldersHtml = (() => {
                const th = d.title_holders;
                // The three lines must read as three different things, so each
                // carries its own colour from the resolver's `tone`.
                const TONES = {
                    amber:   { label: 'text-amber-700',   value: 'text-amber-800' },
                    emerald: { label: 'text-emerald-700', value: 'text-emerald-800' },
                    indigo:  { label: 'text-indigo-700',  value: 'text-indigo-800' },
                    gray:    { label: 'text-gray-500',    value: 'text-gray-800' },
                };
                const forceRow = (l, v, tone) => {
                    const t = TONES[tone] || TONES.gray;
                    return `<div class="flex justify-between gap-4 py-2 border-b border-gray-100 last:border-0">
                    <span class="text-xs font-semibold ${t.label}">${esc(l)}</span>
                    <span class="text-sm font-medium text-right ${v ? t.value : 'text-gray-400'}">${v ? esc(v) : '—'}</span></div>`;
                };
                if (!th) {
                    // Not indexed — no chain to interpret; keep the legacy two rows.
                    const origVal = d.original_holder || d.current_holder;
                    const currVal = d.current_holder || d.original_holder;
                    return forceRow('Original Holder', origVal, 'emerald')
                         + forceRow('Current Holder', currVal, 'indigo');
                }
                // `lines` carries the label, value and colour tone for each line,
                // resolved server-side so every interface renders them alike.
                const lines = Array.isArray(th.lines) ? th.lines : [];
                return `
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-gray-500 mb-1">Title</div>
                    ${lines.map(l => forceRow(l.label, l.value, l.tone)).join('')}`;
            })();

            // Ownership timeline — chronological holder chain from the cross-table
            // property timeline. Context for the block above; hidden entirely when
            // the file has no transaction history (the three rows already covered
            // that case from the indexing columns).
            const holderHistoryHtml = (() => {
                let hist = Array.isArray(d.holder_history) ? d.holder_history : [];
                // Hide Mortgage and Surrender And Release nodes from the holders list.
                hist = hist.filter((h) => {
                    const t = String(h.transaction_type || '').toLowerCase();
                    return !t.includes('mortgage') && !t.includes('surrender');
                });
                if (!hist.length) {
                    return '';
                }
                // Shorten a transaction type to its instrument label (R of O, C of O,
                // Assignment, Mortgage, …) for the compact ownership list.
                const abbrevType = (t) => {
                    if (!t) return '';
                    const s = String(t).toLowerCase();
                    if (s.includes('right of occupancy')) return 'R of O';
                    if (s.includes('certificate of occupancy')) return 'C of O';
                    return String(t).replace(/^deed of\s+/i, '').trim();
                };
                // Render a single holder node: recipient name, the instrument in
                // brackets and the dealing's date. Nodes are NOT labelled Original
                // or Current Holder any more — position in the chain does not
                // decide either role (spec §12), the resolver does, and the Title
                // block above carries the answer.
                const buildNode = (h, isFirst, isLast) => {
                    const dot = isFirst ? 'bg-emerald-600' : (isLast ? 'bg-indigo-600' : 'bg-gray-300');
                    const dotIcon = (isFirst || isLast) ? 'text-white' : 'text-gray-500';
                    const line = !isLast ? `<span class="absolute left-[6px] top-[18px] -bottom-0.5 w-0.5 bg-gray-200"></span>` : '';
                    const name = h.to || h.holder;
                    const roleLabel = h.date || '';
                    const roleColor = 'text-gray-500';
                    const type = abbrevType(h.transaction_type);
                    return `
                        <div class="relative pl-6 ${isLast ? '' : 'pb-2'}">
                            ${line}
                            <span class="absolute left-0 top-px h-[15px] w-[15px] rounded-full ${dot} border-2 border-gray-200 flex items-center justify-center">
                                <i data-lucide="user" class="h-2 w-2 ${dotIcon}"></i>
                            </span>
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="text-[13px] font-bold text-gray-900 leading-tight">${esc(name)}</span>
                                <span class="text-[11px] font-semibold ${roleColor}">${esc(roleLabel)}${type ? ` <span class="text-gray-400 font-medium">(${esc(type)})</span>` : ''}</span>
                            </div>
                        </div>`;
                };
                // One node per dealing. A single-transaction file used to be drawn
                // twice (once as Original, once as Current) so both roles appeared;
                // the Title block above now states both, so the duplicate node would
                // only read as a phantom second dealing.
                const nodes = hist.map((h, i) => buildNode(h, i === 0, i === hist.length - 1)).join('');
                return `
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-gray-500 mt-3 mb-2.5">Dealings</div>
                    <div class="pb-0.5">${nodes}</div>`;
            })();

            // Movement Timeline — mirrors the mobile File Search "View Movement
            // timeline" panel (same /track endpoint), shown for files that are
            // physically moving (In Transit) or resting In Archive.
            const movementDetailsHtml = (() => {
                if (!/^IN_TRANSIT|^IN_ARCHIVE/.test(d.status || '')) return '';
                return `
                    <details class="mb-3 rounded-lg border border-gray-200" data-movement-timeline>
                        <summary class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 flex items-center gap-2">
                            <i data-lucide="route" class="h-3.5 w-3.5"></i> Movement Timeline
                        </summary>
                        <div class="px-4 pb-3 pt-1">
                            <div id="qs-movement-timeline"></div>
                        </div>
                    </details>`;
            })();

            result.innerHTML = `
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            ${filePassportThumb(d.passport_url, d.file_title, 'h-14 w-14')}
                            <div>
                            <div class="text-lg font-bold text-gray-900">${esc(d.file_number)}</div>
                            ${Array.isArray(d.related_files) && d.related_files.length ? `
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                <span class="font-semibold text-violet-700">Related file${d.related_files.length === 1 ? '' : 's'}:</span>
                                ${d.related_files.map(f => `<span class="inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-violet-800"><span class="font-semibold">${esc(f.file_number)}</span>${f.file_title ? `<span class="text-violet-700">— ${esc(f.file_title)}</span>` : ''}</span>`).join('')}
                            </div>` : ''}
                            <div class="text-sm text-gray-500">${esc(d.file_title || '—')}</div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold ${metaCls}">
                            <i data-lucide="${meta.icon}" class="h-3.5 w-3.5"></i> ${esc(metaLabel)}
</span>
                        ${requestTypeTagHtml(d.request_type)}
                        </div>
                    </div>
                    <div class="px-6 py-3">
                        ${duplicateFlagHtml(d)}
                        ${(d.status === 'MISSING_FILE' && d.is_indexed) ? `
                        <div class="mb-3 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="info" class="h-4 w-4 text-blue-600 shrink-0"></i>
                                <span class="text-sm font-semibold text-blue-800">This file was indexed and returned to the Original Registry before archiving.</span>
                            </div>
                            ${(d.registry || d.rack_shelf) ? `
                            <div class="mt-1.5 pl-6 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-blue-700">
                                ${d.registry ? `<span><span class="font-semibold">Registry:</span> ${esc(d.registry)}</span>` : ''}
                                ${d.rack_shelf ? `<span><span class="font-semibold">Shelf/Rack:</span> ${esc(d.rack_shelf)}</span>` : ''}
                            </div>` : ''}
                        </div>` : ''}
                        ${hasCandidates(d) ? `
                        <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3" data-candidates>
                            <div class="flex items-center gap-2">
                                <i data-lucide="layers" class="h-4 w-4 shrink-0 text-amber-600"></i>
                                <span class="text-sm font-bold text-amber-800">${d.duplicate_candidates.length} files are registered under this number</span>
                            </div>
                            <div class="mt-1 pl-6 text-xs text-amber-700">Select the exact file you are looking for. Indexed files go to the SCB for physical confirmation; duplicate files go to Director Land.</div>
                            <div class="mt-2.5 space-y-2">
                                ${d.duplicate_candidates.map((c, i) => `
                                <label class="flex gap-3 rounded-lg border border-amber-200 bg-white px-3 py-2.5 cursor-pointer hover:border-amber-400 transition-colors" data-candidate>
                                    <input type="radio" name="dup-candidate" class="mt-1 h-4 w-4 shrink-0 text-purple-600 focus:ring-purple-500"
                                        data-candidate-id="${esc(c.record_id)}" data-candidate-source="${esc(c.source)}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-2 flex-wrap">
                                            <span class="text-sm font-bold text-gray-900">${esc(c.file_number)}</span>
                                            <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full ${c.source === 'duplicate_fileno' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'}">${esc(c.source_label)}</span>
                                        </div>
                                        <div class="mt-0.5 text-sm text-gray-700">${esc(c.holder || '—')}</div>
                                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
                                            ${c.registry ? `<span><span class="font-semibold">Registry:</span> ${esc(c.registry)}</span>` : ''}
                                            ${c.rack_shelf ? `<span><span class="font-semibold">Shelf/Rack:</span> ${esc(c.rack_shelf)}</span>` : ''}
                                            ${c.current_location ? `<span><span class="font-semibold">Location:</span> ${esc(c.current_location)}</span>` : ''}
                                            <span><span class="font-semibold">Status:</span> ${esc(c.status_label)}</span>
                                        </div>
                                    </div>
                                </label>`).join('')}
                            </div>
                        </div>` : ''}
                        ${d.is_temp_file && !(d.duplicate_flag && /temp/i.test(d.duplicate_flag.label || '')) ? `
                        <div class="mb-3 rounded-lg px-4 py-3 flex items-center gap-2" style="background:#7c3aed14;border:1px solid #7c3aed55;">
                            <i data-lucide="file-clock" class="h-4 w-4 shrink-0" style="color:#7c3aed;"></i>
                            <span class="text-sm font-bold" style="color:#7c3aed;">Temporary File</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap" style="color:#7c3aed;background:#7c3aed1a;"></span>
                        </div>` : ''}
                        ${Number(d.dciv_status) === 1 ? `
                        <div class="mb-3 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                                <span class="text-sm font-bold text-rose-800">Under Investigation</span>
                                ${d.dciv_fileno ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 border border-rose-200 whitespace-nowrap">${esc(d.dciv_fileno)}</span>` : ''}
                            </div>
                            ${d.dciv_reason ? `<div class="text-xs text-rose-700 mt-1.5">${esc(d.dciv_reason)}</div>` : ''}
                        </div>` : ''}
                        ${(() => {
                            const rf = d.dciv_related_files || [];
                            if (!rf.length) return '';
                            const item = f => `
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200 whitespace-nowrap">${esc(f.related_file_number)}</span>
                                    ${f.related_file_title ? `<span class="text-emerald-700">— ${esc(f.related_file_title)}</span>` : ''}
                                </div>`;
                            const list = `<div class="mt-2 space-y-1">${rf.map(item).join('')}</div>`;
                            return rf.length > 1 ? `
                            <details class="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                                <summary class="cursor-pointer select-none flex items-center gap-2 text-sm font-bold text-emerald-800">
                                    <i data-lucide="link" class="h-4 w-4 text-emerald-700 shrink-0"></i> Linked Related Files (${rf.length})
                                </summary>
                                ${list}
                            </details>` : `
                            <div class="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="link" class="h-4 w-4 text-emerald-700 shrink-0"></i>
                                    <span class="text-sm font-bold text-emerald-800">Linked Related Files (${rf.length})</span>
                                </div>
                                ${list}
                            </div>`;
                        })()}
                        <details class="mb-3 rounded-lg border border-gray-200">
                            <summary class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 flex items-center gap-2">
                                <i data-lucide="users" class="h-3.5 w-3.5"></i> Holder &amp; Bill Details
                            </summary>
                            <div class="px-4 pb-3 pt-1">
                                ${titleHoldersHtml}
                                ${holderHistoryHtml}
                                ${d.bill_balance ? `
                                <div class="mt-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                                    <div class="mb-1">
                                        <span class="text-xs font-bold text-gray-700">Bill Ref ID: ${d.bill_balance.reference ? `<span class="text-red-600">${esc(d.bill_balance.reference)}</span>` : '<span class="text-gray-400">—</span>'}</span>
                                    </div>
                                    ${money('Rent / Annum', d.bill_balance.amount)}
                                    ${row('Rent Period', [d.bill_balance.rent_from_year, d.bill_balance.rent_to_year].filter(Boolean).join(' – '))}
                                    ${row('Expiry', d.bill_balance.expiry)}
                                    ${row('Receipt', d.bill_balance.receipt)}
                                    ${Object.entries(d.bill_balance.fees || {}).map(([k, v]) => money(k, v)).join('')}
                                    ${money('Total', d.bill_balance.fees_total)}
                                </div>` : `
                                <div class="mt-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                                    <span class="text-xs font-bold text-gray-700">Bill Ref ID: <span class="text-gray-400">—</span></span>
                                </div>`}
                                ${(d.indexing_bills && (d.indexing_bills.bill_balance != null || d.indexing_bills.grant_rent != null)) ? `
                                <div class="mt-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2">
                                    ${money('Bill Balance', d.indexing_bills.bill_balance)}
                                    ${money('Grant Rent', d.indexing_bills.grant_rent)}
                                </div>` : ''}
                            </div>
                        </details>
                        ${movementDetailsHtml}
                        ${row('Registry', d.registry)}
                        ${row('Shelf/Rack', d.rack_shelf || '—')}
                        ${(() => {
                            // In-transit files are physically held by a Receiving Officer in
                            // their department — show the holding Department as the current
                            // location and label the holder explicitly. Other statuses keep
                            // the expected archive/pool location.
                            if (d.status === 'IN_TRANSIT') {
                                let dept = (d.receiving_department || '').trim();
                                if (dept && !/department$/i.test(dept)) dept = dept + ' Department';
                                return personRow('Receiving Officer (holder)', d.receiving_officer_name, d.receiving_officer_photo)
                                     + row('Department', dept || d.current_location);
                            }
                            return row('Current Location (Expected)', d.current_location)
                                 + personRow('Receiving Officer', d.receiving_officer_name, d.receiving_officer_photo);
                        })()}
                        ${row('Logged Out', d.logged_out_at)}
                        ${row('Duration with holder', d.duration_with_holder)}
                        ${row('Request Sent', d.fr_sent_at ? (d.fr_sent_at + (d.fr_request_no ? ' · ' + d.fr_request_no : '')) : '')}
                        ${isFound ? `
                        <div class="mt-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle" class="h-4 w-4 text-green-600 shrink-0"></i>
                                <span class="text-sm font-bold text-green-800">File Found</span>
                                ${d.fr_found && d.fr_found.request_no ? `<span class="ml-auto text-[11px] font-semibold text-green-700">${esc(d.fr_found.request_no)}</span>` : ''}
                            </div>
                            ${d.fr_found ? `
                            <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                                ${d.fr_found.responded_at ? `<div class="flex items-center gap-1.5 text-green-900"><i data-lucide="calendar-check" class="h-3.5 w-3.5 text-green-600 shrink-0"></i><span class="font-semibold">Found:</span> ${esc(d.fr_found.responded_at)}</div>` : ''}
                                ${d.fr_found.responder ? `<div class="flex items-center gap-1.5 text-green-900"><i data-lucide="user-check" class="h-3.5 w-3.5 text-green-600 shrink-0"></i><span class="font-semibold">Responded by (SCB):</span> ${esc(d.fr_found.responder)}</div>` : ''}
                                ${d.fr_found.requested_at ? `<div class="flex items-center gap-1.5 text-green-900"><i data-lucide="send" class="h-3.5 w-3.5 text-green-600 shrink-0"></i><span class="font-semibold">Request sent:</span> ${esc(d.fr_found.requested_at)}</div>` : ''}
                                ${d.fr_found.receiving_officer ? `<div class="flex items-center gap-1.5 text-green-900"><i data-lucide="user" class="h-3.5 w-3.5 text-green-600 shrink-0"></i><span class="font-semibold">Requester:</span> ${esc(d.fr_found.receiving_officer)}</div>` : ''}
                                ${(d.fr_found.requester_office || d.fr_found.requester_department) ? `<div class="flex items-center gap-1.5 text-green-900"><i data-lucide="building-2" class="h-3.5 w-3.5 text-green-600 shrink-0"></i><span class="font-semibold">Office:</span> ${esc([d.fr_found.requester_department, d.fr_found.requester_office].filter(Boolean).join(' · '))}</div>` : ''}
                                ${d.fr_found.registry ? `<div class="flex items-center gap-1.5 text-green-900"><i data-lucide="library" class="h-3.5 w-3.5 text-green-600 shrink-0"></i><span class="font-semibold">Registry:</span> ${esc(d.fr_found.registry)}</div>` : ''}
                            </dl>` : ''}
                        </div>` : ''}
                        ${isNotFound ? (() => {
                            // Missing → red, Pending → amber; mirror the SCB Feedback colour coding.
                            const nft = d.fr_not_found && d.fr_not_found.not_found_type
                                ? (String(d.fr_not_found.not_found_type).toUpperCase() === 'MISSING' ? 'Missing' : 'Pending') : '';
                            const c = nft === 'Pending'
                                ? { bg:'bg-amber-50', bd:'border-amber-200', ic:'text-amber-600', tx:'text-amber-800', body:'text-amber-900', lbl:'text-amber-700', icon:'hourglass' }
                                : { bg:'bg-red-50',   bd:'border-red-200',   ic:'text-red-600',   tx:'text-red-800',   body:'text-red-900',   lbl:'text-red-700',   icon:'alert-triangle' };
                            const f = d.fr_not_found;
                            return `
                        <div class="mt-3 rounded-lg ${c.bg} border ${c.bd} px-4 py-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="${c.icon}" class="h-4 w-4 ${c.ic} shrink-0"></i>
                                <span class="text-sm font-bold ${c.tx}">File Not Found${nft ? ` (${nft})` : ''}</span>
                                ${f && f.request_no ? `<span class="ml-auto text-[11px] font-semibold ${c.lbl}">${esc(f.request_no)}</span>` : ''}
                            </div>
                            ${f ? `
                            <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                                ${f.responded_at ? `<div class="flex items-center gap-1.5 ${c.body}"><i data-lucide="calendar-x" class="h-3.5 w-3.5 ${c.ic} shrink-0"></i><span class="font-semibold">Responded:</span> ${esc(f.responded_at)}</div>` : ''}
                                ${f.responder ? `<div class="flex items-center gap-1.5 ${c.body}"><i data-lucide="user-check" class="h-3.5 w-3.5 ${c.ic} shrink-0"></i><span class="font-semibold">Responded by (SCB):</span> ${esc(f.responder)}</div>` : ''}
                                ${f.requested_at ? `<div class="flex items-center gap-1.5 ${c.body}"><i data-lucide="send" class="h-3.5 w-3.5 ${c.ic} shrink-0"></i><span class="font-semibold">Request sent:</span> ${esc(f.requested_at)}</div>` : ''}
                                ${f.receiving_officer ? `<div class="flex items-center gap-1.5 ${c.body}"><i data-lucide="user" class="h-3.5 w-3.5 ${c.ic} shrink-0"></i><span class="font-semibold">Requester:</span> ${esc(f.receiving_officer)}</div>` : ''}
                                ${(f.requester_office || f.requester_department) ? `<div class="flex items-center gap-1.5 ${c.body}"><i data-lucide="building-2" class="h-3.5 w-3.5 ${c.ic} shrink-0"></i><span class="font-semibold">Office:</span> ${esc([f.requester_department, f.requester_office].filter(Boolean).join(' · '))}</div>` : ''}
                                ${f.registry ? `<div class="flex items-center gap-1.5 ${c.body}"><i data-lucide="library" class="h-3.5 w-3.5 ${c.ic} shrink-0"></i><span class="font-semibold">Registry:</span> ${esc(f.registry)}</div>` : ''}
                                ${f.note ? `<div class="sm:col-span-2 flex items-start gap-1.5 ${c.body}"><i data-lucide="message-square" class="h-3.5 w-3.5 ${c.ic} shrink-0 mt-px"></i><span class="font-semibold">Note:</span> ${esc(f.note)}</div>` : ''}
                            </dl>` : ''}
                        </div>`;
                        })() : ''}
                        <div class="mt-3 rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
                            <div class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Next Action</div>
                            <div data-next-action class="text-sm text-indigo-900 mt-0.5">${esc(hasCandidates(d)
                                ? 'Select the exact file above. Indexed files go to the SCB Monitor; duplicate files go to Director Land.'
                                : d.next_action)}</div>
                        </div>

                        <details class="mt-3 rounded-lg border border-gray-200">
                            <summary class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 flex items-center gap-2">
                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Update Location Status
                            </summary>
                            <div class="px-4 pb-4 pt-1 space-y-2">
                                <select data-us-status class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    ${STATUS_OPTIONS.map(o => `<option value="${o[0]}" ${o[0]===d.status?'selected':''}>${o[1]}</option>`).join('')}
                                </select>
                                <input data-us-loc type="text" placeholder="Location / note (optional)" value="${esc(d.current_location || '')}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <button type="button" data-us-save class="inline-flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">
                                    <i data-lucide="save" class="h-4 w-4"></i> Save Status
                                </button>
                                <div data-us-msg class="text-xs"></div>
                            </div>
                        </details>
                    </div>
                    ${(d.can_send_fr && CAN_SEND_FR && (!directsToLand(d) || hasIndexedCandidate(d))) ? `
                    <div data-scb-request-form class="px-6 pt-4 border-t border-gray-100" style="${((directsToLand(d) && hasCandidates(d)) || isDciv(d)) ? 'display:none;' : ''}">
                        <div class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Requester</div>
                        <div class="mb-3">
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Registry (Origin) <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <select data-fr-registry class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">— Select Registry (Origin) —</option>
                                    ${REGISTRIES.map(rg => `<option value="${esc(rg.name)}" data-code="${esc(rg.registry_code || '')}">${esc(rg.name)}</option>`).join('')}
                                </select>
                                <span data-fr-registry-code class="inline-flex items-center justify-center min-w-[56px] rounded-lg bg-indigo-50 border border-indigo-100 px-2 py-2 text-xs font-bold text-indigo-700">—</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Requester Department <span class="text-red-500">*</span></label>
                                <select data-fr-dept class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">— Select Department —</option>
                                    ${REQ_DEPARTMENTS.map(dn => `<option value="${esc(dn)}">${esc(dn)}</option>`).join('')}
                                    <option value="${DEPT_OTHER}">Other…</option>
                                </select>
                                <input data-fr-dept-other type="text" placeholder="Specify department *" class="hidden mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Requester Director</label>
                                <select data-fr-director class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" disabled>
                                    <option value="">— Select Director —</option>
                                </select>
                                <div data-fr-director-other class="hidden mt-2 p-2 bg-gray-50 border border-gray-200 rounded-md">
                                    <p class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Add New Director</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" data-fr-director-first-name placeholder="First Name *" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <input type="text" data-fr-director-last-name placeholder="Last Name *" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Requester Office <span class="text-red-500">*</span></label>
                                <select data-fr-office class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" disabled>
                                    <option value="">— Select Office —</option>
                                </select>
                                <input data-fr-office-other type="text" placeholder="Specify office *" class="hidden mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">File Requester <span class="text-red-500">*</span></label>
                                <select data-fr-officer class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" disabled>
                                    <option value="">— Select Officer —</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Request Purpose <span class="text-red-500">*</span></label>
                                <select data-fr-purpose class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">— Select the reason this file is being requested —</option>
                                    ${REQUEST_PURPOSES.map(p => `<option value="${p.id}" data-turnaround-days="${p.turnaround_days}">${esc(p.name)}</option>`).join('')}
                                    <option value="in_transit">In-Transit</option>
                                    <option value="other">Other</option>
                                </select>
                                <input data-fr-purpose-other type="text" placeholder="Specify the reason this file is being requested *" class="hidden mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div data-fr-timeline-days-wrap>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Timeline (Days)</label>
                                <input data-fr-timeline-days type="number" min="0" max="365" placeholder="e.g. 5" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            {{-- No Expected Return Date here: the return clock starts when the
                                 file is logged out on Create File Tracker, not when the request
                                 is raised, so that date is captured only on the Log a File page. --}}
                        </div>

                        <!-- Add Receiving Officer card (shown when the office/officer is not listed) -->
                        <div data-fr-addcard class="hidden mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                            <div class="flex items-center gap-2 text-amber-800 mb-2">
                                <i data-lucide="user-plus" class="h-4 w-4"></i>
                                <span class="text-xs font-bold">Add Receiving Officer</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <input data-ao-first type="text" placeholder="First name *" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <input data-ao-last type="text" placeholder="Last name *" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button" data-ao-save class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                    <i data-lucide="user-plus" class="h-3.5 w-3.5"></i> Add Officer</button>
                                <button type="button" data-ao-cancel class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50">Cancel</button>
                                <span data-ao-msg class="text-[11px]"></span>
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] text-gray-400">The file is logged back to whoever is honored first by seniority.</p>
                        <div data-fr-registry-preview class="mt-3"></div>
                    </div>` : ''}
                    <div id="qs-fsDigital" class="px-6 pt-4 border-t border-gray-100"></div>
                    ${(isDcivFile(d) && d.can_send_fr && CAN_SEND_FR) ? `
                    <div class="px-6 pt-4">
                        <label class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 cursor-pointer">
                            <input type="checkbox" data-dciv-override ${d.dciv_override ? 'checked' : ''}
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-xs text-amber-900"><span class="font-bold">Override DCIV routing</span> —
                                send this to the SCB Monitor as a normal File Search Request instead of re-directing it to the DCIV Director.</span>
                        </label>
                    </div>` : ''}
                    <div class="px-6 py-4 flex flex-wrap gap-2">${actionButtons(d)}</div>
                    ${'' /* redirectSubline(d) — "Receiving Officer … currently holding the file" note hidden for now */}
                </div>`;
            result.classList.remove('hidden');

            // DCIV files get the cascade initialised too, even though their requester form
            // starts hidden — the override checkbox only un-hides it, it does not re-render.
            if (d.can_send_fr && (!directsToLand(d) || hasIndexedCandidate(d))) initRequesterCascade(d);

            const frBtn = result.querySelector('[data-fr]');
            if (frBtn) frBtn.addEventListener('click', () => sendFR(d, frBtn));
            const redirectBtn = result.querySelector('[data-redirect]');
            if (redirectBtn) redirectBtn.addEventListener('click', () => sendRedirect(d, redirectBtn));
            const redirectLandBtn = result.querySelector('[data-redirect-land]');
            if (redirectLandBtn) redirectLandBtn.addEventListener('click', () => {
                // An indexed physical record is routed through the normal SCB
                // request flow; duplicate-fileno records remain Director Land work.
                if (selectedCandidate && selectedCandidate.source === 'file_indexings') {
                    sendFR(d, redirectLandBtn);
                } else {
                    sendRedirectToDirectorLand(d, redirectLandBtn);
                }
            });
            // Duplicate selection: the Director Land button stays inert until one of
            // the matching records is chosen, because the request is saved against
            // that record's id and source table — the shared file number cannot
            // identify which of the two physical files was asked for.
            selectedCandidate = null;
            result.querySelectorAll('[data-candidate-id]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    selectedCandidate = {
                        record_id: Number(radio.dataset.candidateId),
                        source:    radio.dataset.candidateSource,
                    };
                    result.querySelectorAll('[data-candidate]').forEach(l =>
                        l.classList.toggle('border-purple-500', l.contains(radio)));
                    if (redirectLandBtn) {
                        redirectLandBtn.disabled = false;
                        if (radio.dataset.candidateSource === 'file_indexings') {
                            // Indexed file: reveal the requester form and turn the
                            // same action button into an SCB request.
                            const scbForm = result.querySelector('[data-scb-request-form]');
                            if (scbForm) scbForm.style.display = '';
                            redirectLandBtn.className = 'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700';
                            redirectLandBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Send File Search Request to SCB Monitor';
                        } else {
                            // Duplicate file: hide the optional SCB form and keep
                            // the Director Land action.
                            const scbForm = result.querySelector('[data-scb-request-form]');
                            if (scbForm) scbForm.style.display = 'none';
                            redirectLandBtn.className = 'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white bg-purple-600 hover:bg-purple-700';
                            redirectLandBtn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Send Request to Director Land';
                        }
                        if (window.lucide) window.lucide.createIcons();
                    }
                });
            });
            const redirectDcivBtn = result.querySelector('[data-redirect-dciv]');
            if (redirectDcivBtn) redirectDcivBtn.addEventListener('click', () => sendRedirectToDcivDirector(d, redirectDcivBtn));
            // Override the DCIV routing: swap the re-direct button for the SCB request
            // button and reveal the requester form. Both already exist in the DOM.
            const dcivOverrideCb = result.querySelector('[data-dciv-override]');
            if (dcivOverrideCb) dcivOverrideCb.addEventListener('change', () => {
                d.dciv_override = dcivOverrideCb.checked;
                const scbForm = result.querySelector('[data-scb-request-form]');
                if (scbForm)          scbForm.style.display          = d.dciv_override ? '' : 'none';
                if (redirectDcivBtn)  redirectDcivBtn.style.display  = d.dciv_override ? 'none' : '';
                if (frBtn)            frBtn.style.display            = d.dciv_override ? '' : 'none';
                const nextActionEl = result.querySelector('[data-next-action]');
                if (nextActionEl) nextActionEl.textContent = d.dciv_override
                    ? (d.is_blind ? 'Send Blind Request to SCB Monitor' : 'Send File Search Request to SCB Monitor')
                    : d.next_action;
            });
            const logBtn = result.querySelector('[data-log]');
            if (logBtn) logBtn.addEventListener('click', () => handleLogFileClick(d, d.fr_found));
            const saveBtn = result.querySelector('[data-us-save]');
            if (saveBtn) saveBtn.addEventListener('click', () => updateStatus(d, result.querySelector('[data-us-status]').value, result.querySelector('[data-us-loc]').value, saveBtn));
            const movementDetails = result.querySelector('[data-movement-timeline]');
            if (movementDetails) movementDetails.addEventListener('toggle', () => toggleMovementTimeline(d.file_number, movementDetails, d.registry, d.rack_shelf));
            if (window.lucide) window.lucide.createIcons();

            // Load the File Digital Library (same FileIndexing source as the DFR / mobile
            // File Search preview) inline beneath the location details.
            loadFsDigital(d.file_number);

            // File already has an open request → surface the duplicate prompt immediately
            // (instead of waiting for the user to fill the form and click Send).
            // A DCIV file's SCB button starts hidden behind the override checkbox, so the
            // prompt would be attached to something invisible. Those fall back to the
            // server-side duplicate guard in sendFileRequest() when Send is clicked.
            if (frBtn && d.existing_request && !isDcivFile(d)) {
                showFrDuplicate(d, frBtn, d.existing_request);
            }
        }

        function renderError(msg) {
            result.innerHTML = `<div class="rounded-xl border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700">${esc(msg)}</div>`;
            result.classList.remove('hidden');
        }

        async function search() {
            const q = input.value.trim();
            if (!q) { input.focus(); return; }
            btn.disabled = true; result.classList.add('hidden');
            try {
                const res  = await fetch(`${RESOLVE_URL}?query=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                json.success ? render(json.data) : renderError(json.message || 'Search failed.');
            } catch (e) { renderError('Network error — please try again.'); }
            finally { btn.disabled = false; }
        }

        async function updateStatus(d, status, location, saveBtn) {
            const msg = result.querySelector('[data-us-msg]');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Saving…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(UPDATE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number: d.file_number, status, current_location: location }),
                });
                const json = await res.json();
                if (json.success) { render(json.data); }
                else if (msg) { msg.className = 'text-xs text-red-600'; msg.textContent = json.message || 'Could not update.'; saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Save Status'; if (window.lucide) window.lucide.createIcons(); }
            } catch (e) {
                if (msg) { msg.className = 'text-xs text-red-600'; msg.textContent = 'Network error — please try again.'; }
                saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Save Status';
                if (window.lucide) window.lucide.createIcons();
            }
        }

        // Inline duplicate-request warning shown next to the Send button.
        const DEPT_OTHER     = '__DEPT_OTHER__';
        const DIRECTOR_OTHER = '__DIRECTOR_OTHER__';
        const OFFICE_OTHER   = '__OFFICE_OTHER__';
        const OFFICER_ADD    = '__OFFICER_ADD__';

        // ── Smart Registry (Origin) detection ──────────────────────────────────
        // Auto-selects the Requester section's Registry (Origin) from the file
        // number. Config-driven so new registries/patterns are one-line changes.
        // Land (MLS) numbers resolve Registry 1/2/3 by CON prefix and year — see
        // docs/guides/Registry Summary.md ("Registry Assignment Rules").
        const LAND_REGISTRY_RULES = {
            conRegistry: 'Registry 3 - Land',      // Priority 1: any CON-* file, all years
            yearRegistries: [
                { from: 1981, to: 1991, registry: 'Registry 1 - Land' }, // Priority 2
                { from: 1992, to: 2025, registry: 'Registry 2 - Land' }, // Priority 3
            ],
            fallback: 'Registry 2 - Land',
        };
        const REGISTRY_DETECT_RULES = [
            { pattern: /^ST[-\/ ]/,                       registry: 'ST Registry' },
            { pattern: /^DCIV[-\/ ]/,                     registry: 'DCIV Registry' },
            { pattern: /^SLTR[-\/ ]/,                     registry: 'SLTR Registry' },
            { pattern: /^SIT[-\/ ]/,                      registry: 'SIT Registry' },
            { pattern: /^(GKN|LPKN)[-\/ ]/,               registry: 'Survey Registry' },
            { pattern: /^(KNML|MLKN|MNKL|KNGP|KN\s*\d)/,  registry: 'KANGIS Registry' },
            // Land registry (MLS): RES/COM/IND/AG/MISC with optional CON- and -RC parts.
            { pattern: /^(CON-)?(RES|COM|IND|AG|MISC)(-RC)?[-\/ ]/, registry: detectLandRegistry },
        ];

        function detectLandRegistry(fno) {
            if (/^CON-/.test(fno)) return LAND_REGISTRY_RULES.conRegistry;
            const m = fno.match(/-((?:19|20)\d{2})(?:-|$)/);
            const year = m ? parseInt(m[1], 10) : null;
            if (year) {
                const hit = LAND_REGISTRY_RULES.yearRegistries.find(r => year >= r.from && year <= r.to);
                if (hit) return hit.registry;
            }
            return LAND_REGISTRY_RULES.fallback;
        }

        function detectRegistryName(fileNumber) {
            // Normalise: uppercase, trim, strip a temporary-file "(T)" marker.
            const fno = String(fileNumber || '').toUpperCase().trim().replace(/\s*\(\s*T\s*\)\s*$/, '');
            if (!fno) return null;
            for (const rule of REGISTRY_DETECT_RULES) {
                if (rule.pattern.test(fno)) {
                    return typeof rule.registry === 'function' ? rule.registry(fno) : rule.registry;
                }
            }
            return null;
        }

        // Match a registry label to a dropdown <option>. Exact match first, then
        // "Registry 2" → "Registry 2 - Land", then prefix (so resolver labels
        // like "Registry 1" still land on the right physical registry).
        function findRegistryOption(sel, label) {
            const want = String(label || '').toLowerCase().trim();
            if (!want) return null;
            const opts = Array.from(sel.options).filter(o => o.value);
            return opts.find(o => o.value.toLowerCase().trim() === want)
                || opts.find(o => o.value.toLowerCase().trim() === want + ' - land')
                || opts.find(o => o.value.toLowerCase().trim().startsWith(want))
                || null;
        }

        // Wire the Requester cascade: Department → Office → Officer (mirrors Create File Tracker).
        function initRequesterCascade(fileData = {}) {
            const deptSel    = result.querySelector('[data-fr-dept]');
            const directorSel= result.querySelector('[data-fr-director]');
            const officeSel  = result.querySelector('[data-fr-office]');
            const officerSel = result.querySelector('[data-fr-officer]');
            const addCard    = result.querySelector('[data-fr-addcard]');
            if (!deptSel || !officeSel || !officerSel) return;

            const deptOther   = result.querySelector('[data-fr-dept-other]');
            const directorOther = result.querySelector('[data-fr-director-other]');
            const officeOther = result.querySelector('[data-fr-office-other]');

            // Requester Officer is searchable (select2) — the officer list can run long,
            // so typing narrows it instead of scrolling a native <select>. Re-initialised
            // (destroy + init) every time the option list is rebuilt below, since select2
            // doesn't pick up options/disabled state changed via raw innerHTML/.disabled.
            function refreshOfficerSelect2() {
                const $officerSel = $(officerSel);
                if ($officerSel.data('select2')) $officerSel.select2('destroy');
                $officerSel.select2({
                    width: '100%',
                    placeholder: '— Select Officer —',
                });
            }
            refreshOfficerSelect2();

            // Request Purpose → Timeline (Days): pre-fill the days from the selected
            // purpose's default turnaround. No Expected Return Date is derived here —
            // the return clock starts at log-out (Create File Tracker), not at request
            // time, so the date is captured only on the Log a File page.
            const purposeSel      = result.querySelector('[data-fr-purpose]');
            const purposeOther    = result.querySelector('[data-fr-purpose-other]');
            const timelineDaysInput = result.querySelector('[data-fr-timeline-days]');
            if (purposeSel) {
                const timelineDaysWrap = result.querySelector('[data-fr-timeline-days-wrap]');

                purposeSel.addEventListener('change', () => {
                    const isOther = purposeSel.value === 'other';
                    if (purposeOther) {
                        purposeOther.classList.toggle('hidden', !isOther);
                        if (!isOther) purposeOther.value = '';
                    }

                    // "In-Transit" is a purely internal movement (no loan/return
                    // expected) — hide Timeline (Days) and clear any stale value
                    // instead of just skipping validation.
                    const isInTransit = purposeSel.value === 'in_transit';
                    if (timelineDaysWrap) timelineDaysWrap.classList.toggle('hidden', isInTransit);
                    if (isInTransit) {
                        if (timelineDaysInput) timelineDaysInput.value = '';
                        return;
                    }

                    // No turnaround pre-fill — Timeline (Days) is entered by hand.
                });
            }

            deptSel.addEventListener('change', () => {
                const dept = deptSel.value;
                // "Other" department → reveal a free-text specify input.
                if (deptOther) {
                    deptOther.classList.toggle('hidden', dept !== DEPT_OTHER);
                    if (dept !== DEPT_OTHER) deptOther.value = '';
                }
                
                if (directorSel) {
                    directorSel.innerHTML = '<option value="">— Select Director —</option>' +
                        REQ_DIRECTORS.filter(d => d.department === dept || dept === DEPT_OTHER)
                            .map(d => `<option value="${d.id}">${esc(d.first_name)} ${esc(d.last_name)}</option>`).join('') +
                        `<option value="${DIRECTOR_OTHER}">Other…</option>`;
                    directorSel.disabled = false;
                    if (directorOther) { directorOther.classList.add('hidden'); }
                }

                officeSel.innerHTML = '<option value="">— Select Office —</option>' +
                    REQ_OFFICES.filter(o => o.department === dept)
                        .map(o => `<option value="${esc(o.code)}" data-name="${esc(o.name)}">${esc(o.name)}</option>`).join('') +
                    `<option value="${OFFICE_OTHER}">Other…</option>`;
                officeSel.disabled = false;
                if (officeOther) { officeOther.classList.add('hidden'); officeOther.value = ''; }
                officerSel.innerHTML = '<option value="">— Select Officer —</option>';
                officerSel.disabled = true;
                refreshOfficerSelect2();
                hideAddCard();
            });

            if (directorSel) {
                directorSel.addEventListener('change', () => {
                    if (directorOther) {
                        const isOther = directorSel.value === DIRECTOR_OTHER;
                        directorOther.classList.toggle('hidden', !isOther);
                        if (!isOther) {
                            const f = result.querySelector('[data-fr-director-first-name]');
                            const l = result.querySelector('[data-fr-director-last-name]');
                            if (f) f.value = '';
                            if (l) l.value = '';
                        }
                    }
                });
            }

            officeSel.addEventListener('change', () => {
                // "Other" office → reveal a free-text specify input (officer still picked below).
                if (officeOther) {
                    officeOther.classList.toggle('hidden', officeSel.value !== OFFICE_OTHER);
                    if (officeSel.value !== OFFICE_OTHER) officeOther.value = '';
                }
                hideAddCard();
                const dept   = deptSel.value;
                const deptId = REQ_DEPT_NAME_TO_ID[dept];
                const matches = REQ_OFFICERS.filter(o => deptId == null || String(o.department_id) === String(deptId));
                officerSel.innerHTML = '<option value="">— Select Officer —</option>' +
                    matches.map(o => `<option value="${esc(o.name)}">${esc(o.name)}</option>`).join('') +
                    `<option value="${OFFICER_ADD}" class="font-semibold">+ Add Receiving Officer…</option>`;
                officerSel.disabled = false;
                refreshOfficerSelect2();
            });

            // select2 doesn't dispatch a real native 'change' event when an option is
            // picked (jQuery has no native .change() method to proxy through, unlike
            // .click()/.focus()), so a plain addEventListener('change', …) here never
            // fires. Bind via jQuery instead — mirrors receivingOfficerSelect.on('change', …)
            // in create_file_tracker_page/partials/js.blade.php.
            $(officerSel).on('change', () => {
                if (officerSel.value === OFFICER_ADD) {
                    // Reset back to the placeholder so the box doesn't stay stuck showing
                    // "+ Add Receiving Officer…" (which would make re-picking it a no-op).
                    $(officerSel).val('').trigger('change.select2');
                    showAddCard();
                } else {
                    hideAddCard();
                }
            });

            // Registry (Origin): reflect the selected registry's short code live and
            // load the digital copy from that registry's folder (if one exists).
            const registrySel     = result.querySelector('[data-fr-registry]');
            const registryCode    = result.querySelector('[data-fr-registry-code]');
            const registryPreview = result.querySelector('[data-fr-registry-preview]');
            if (registrySel && registryCode) {
                registrySel.addEventListener('change', () => {
                    const opt = registrySel.selectedOptions[0];
                    registryCode.textContent = (opt && opt.dataset.code) ? opt.dataset.code : '—';
                    loadRegistryPreview(fileData.file_number, registrySel.value, registryPreview);
                });

                // When opened from a specific registry module (?url=kangis|cadastral|…),
                // the Registry (Origin) is fixed to that module's registry — preselect it
                // and lock the dropdown so it cannot be changed.
                const ctxKeyword = {
                    kangis:    'kangis',
                    cadastral: 'cadastr',
                    sltr:      'sltr',
                    survey:    'survey',
                    st:        'st registry',
                    dciv:      'dciv',
                }[URL_CTX];
                if (ctxKeyword) {
                    const match = Array.from(registrySel.options).find(
                        o => o.value && o.value.toLowerCase().includes(ctxKeyword)
                    );
                    if (match) {
                        registrySel.value = match.value;
                        registrySel.dispatchEvent(new Event('change'));
                        registrySel.disabled = true;
                        registrySel.classList.add('bg-gray-100', 'cursor-not-allowed');
                    }
                }

                // Smart auto-detect (skipped when a module context locked the select):
                // prefer the registry resolved by the server (file_ranges / indexing),
                // else detect it from the file number pattern. Stays user-changeable.
                if (!registrySel.disabled && !registrySel.value) {
                    const detected = findRegistryOption(registrySel, fileData.registry)
                        || findRegistryOption(registrySel, detectRegistryName(fileData.file_number));
                    if (detected) {
                        registrySel.value = detected.value;
                        registrySel.dispatchEvent(new Event('change'));
                        const holder = registrySel.closest('div');
                        if (holder && !holder.parentElement.querySelector('[data-fr-registry-auto]')) {
                            holder.insertAdjacentHTML('afterend',
                                `<p data-fr-registry-auto class="mt-1 flex items-center gap-1 text-[11px] font-medium text-emerald-600">
                                    <i data-lucide="sparkles" class="h-3 w-3"></i>
                                    Auto-detected from the file number.</p>`);
                            if (window.lucide) window.lucide.createIcons();
                        }
                    }
                }
            }

            function showAddCard() { if (addCard) { addCard.classList.remove('hidden'); if (window.lucide) window.lucide.createIcons(); } }
            function hideAddCard() { if (addCard) addCard.classList.add('hidden'); }

            // Add Receiving Officer (reuses the Create File Tracker endpoint).
            const saveBtn = addCard?.querySelector('[data-ao-save]');
            const cancel  = addCard?.querySelector('[data-ao-cancel]');
            if (cancel) cancel.addEventListener('click', () => { addCard.classList.add('hidden'); officeSel.value = ''; $(officerSel).val('').trigger('change'); });
            if (saveBtn) saveBtn.addEventListener('click', async () => {
                const first = addCard.querySelector('[data-ao-first]').value.trim();
                const last  = addCard.querySelector('[data-ao-last]').value.trim();
                const msg   = addCard.querySelector('[data-ao-msg]');
                if (!first || !last) { msg.className = 'text-[11px] text-red-600'; msg.textContent = 'First and last name are required.'; return; }
                saveBtn.disabled = true; saveBtn.innerHTML = '<i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Saving…';
                if (window.lucide) window.lucide.createIcons();
                try {
                    const res = await fetch(ADD_OFFICER_URL, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                        body: JSON.stringify({ first_name:first, last_name:last }),
                    });
                    const json = await res.json();
                    if (json.success) {
                        const name = (first + ' ' + last).trim();
                        const opt = document.createElement('option');
                        opt.value = name; opt.textContent = name;
                        officerSel.insertBefore(opt, officerSel.querySelector(`option[value="${OFFICER_ADD}"]`));
                        officerSel.disabled = false;
                        refreshOfficerSelect2();
                        $(officerSel).val(name).trigger('change');
                        addCard.classList.add('hidden');
                        addCard.querySelectorAll('input').forEach(i => i.value = '');
                        msg.textContent = '';
                    } else {
                        msg.className = 'text-[11px] text-red-600'; msg.textContent = json.message || 'Could not add officer.';
                    }
                } catch (e) {
                    msg.className = 'text-[11px] text-red-600'; msg.textContent = 'Network error — please try again.';
                } finally {
                    saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="user-plus" class="h-3.5 w-3.5"></i> Add Officer';
                    if (window.lucide) window.lucide.createIcons();
                }
            });
        }

        function showFrDuplicate(d, frBtn, ex) {
            const wrap = document.createElement('div');
            wrap.className = 'mt-2 w-full rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm';
            wrap.innerHTML = `
                <div class="flex items-start gap-2 text-amber-800">
                    <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5 shrink-0"></i>
                    <div>
                        <div class="font-semibold">This file has already been requested.</div>
                        <div class="text-xs mt-0.5">Requested by <span class="font-semibold">${esc(ex.requester_name || '—')}</span>${ex.requester_office ? ' · ' + esc(ex.requester_office) : ''}${ex.requester_department ? ' (' + esc(ex.requester_department) + ')' : ''}
                            ${ex.request_no ? '· ' + esc(ex.request_no) : ''} · ${esc(ex.status || '')}${ex.requested_at ? ' · ' + esc(ex.requested_at) : ''}.
                            It has already been sent to SCB. You can update the requester details on the existing request instead of sending a duplicate.</div>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" data-fr-update class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Update Requester Details</button>
                    <button type="button" data-fr-cancel class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50">
                        Cancel</button>
                </div>`;
            // The file already has an open request — block a duplicate by disabling the
            // SCB button. Use "Update Requester Details" to amend the existing request, or
            // Cancel to re-enable the button.
            frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> ' + esc(d.next_action || 'Send File Search Request to SCB Monitor');
            const disableFr = () => { frBtn.disabled = true; frBtn.classList.add('opacity-50', 'cursor-not-allowed'); };
            const enableFr  = () => { frBtn.disabled = false; frBtn.classList.remove('opacity-50', 'cursor-not-allowed'); };
            disableFr();
            frBtn.insertAdjacentElement('afterend', wrap);
            wrap.querySelector('[data-fr-update]').addEventListener('click', () => { wrap.remove(); enableFr(); sendFR(d, frBtn, false, ex.id); });
            wrap.querySelector('[data-fr-cancel]').addEventListener('click', () => { wrap.remove(); enableFr(); });
            if (window.lucide) window.lucide.createIcons();
        }

        async function sendFR(d, frBtn, force = false, updateId = null) {
            const deptSel    = result.querySelector('[data-fr-dept]');
            const officeSel  = result.querySelector('[data-fr-office]');
            const officerSel = result.querySelector('[data-fr-officer]');
            const deptOther   = result.querySelector('[data-fr-dept-other]');
            const officeOther = result.querySelector('[data-fr-office-other]');

            // Department: "Other" → use the typed value.
            const deptIsOther = deptSel && deptSel.value === DEPT_OTHER;
            const requesterDept = deptIsOther
                ? (deptOther ? deptOther.value.trim() : '')
                : (deptSel ? deptSel.value.trim() : '');

            // Office: "Other" → use the typed value (no code); otherwise the listed office name + code.
            const officeOpt     = officeSel ? officeSel.selectedOptions[0] : null;
            const officeIsOther = officeOpt && officeOpt.value === OFFICE_OTHER;
            const requesterOffice = officeIsOther
                ? (officeOther ? officeOther.value.trim() : '')
                : ((officeOpt && officeOpt.value) ? (officeOpt.dataset.name || officeOpt.textContent.trim()) : '');
            const requesterOfficeCode = officeIsOther ? '' : ((officeOpt && officeOpt.value) ? officeOpt.value : '');

            let receivingOfficer  = officerSel ? officerSel.value.trim() : '';
            if (receivingOfficer === OFFICER_ADD) receivingOfficer = '';

            // Origin Registry (required) + its short code.
            const registrySel  = result.querySelector('[data-fr-registry]');
            const registry     = registrySel ? registrySel.value.trim() : '';
            const registryCode = registrySel && registrySel.selectedOptions[0] ? (registrySel.selectedOptions[0].dataset.code || '') : '';

            // Request Purpose — captured once here so it carries through automatically
            // to Create File Tracker's "Log File" step. The Expected Return Date is NOT
            // captured at request time; it is set when the file is logged out.
            const purposeSel    = result.querySelector('[data-fr-purpose]');
            const purposeOther  = result.querySelector('[data-fr-purpose-other]');
            const rawPurposeValue  = purposeSel ? purposeSel.value : '';
            const purposeIsOther   = rawPurposeValue === 'other';
            const purposeIsInTransit = rawPurposeValue === 'in_transit';
            const requestPurposeId = (purposeIsOther || purposeIsInTransit) ? '' : rawPurposeValue;
            const requestPurposeOther = purposeIsOther ? (purposeOther ? purposeOther.value.trim() : '') : (purposeIsInTransit ? 'In-Transit' : '');

            const directorSel = result.querySelector('[data-fr-director]');
            const directorOther = result.querySelector('[data-fr-director-other]');
            const directorId = directorSel ? directorSel.value : '';
            const directorIsOther = directorId === DIRECTOR_OTHER;
            const directorFirstName = directorIsOther && directorOther ? result.querySelector('[data-fr-director-first-name]').value.trim() : '';
            const directorLastName = directorIsOther && directorOther ? result.querySelector('[data-fr-director-last-name]').value.trim() : '';

            const flag = (el) => { if (el) { el.classList.add('ring-2','ring-red-400','border-red-400'); } };
            const unflag = (el) => { if (el) el.classList.remove('ring-2','ring-red-400','border-red-400'); };
            // select2 hides the native <select> behind its own rendered box, so flagging
            // officerSel directly (via classList) is invisible — style the rendered box instead.
            const officerSelect2Box = () => $(officerSel).next('.select2-container').find('.select2-selection');
            const flagOfficerSelect2 = () => officerSelect2Box().css({ boxShadow: '0 0 0 2px #f87171', borderColor: '#f87171' });
            const unflagOfficerSelect2 = () => officerSelect2Box().css({ boxShadow: '', borderColor: '' });
            [deptSel, officeSel, officerSel, deptOther, officeOther, registrySel, purposeSel, purposeOther, directorSel].forEach(unflag);
            unflagOfficerSelect2();
            if (deptIsOther && !requesterDept) { flag(deptOther); deptOther.focus(); return; }
            if (directorIsOther && (!directorFirstName || !directorLastName)) { 
                flag(directorOther.querySelector('input')); 
                return; 
            }
            if (officeIsOther && !requesterOffice) { flag(officeOther); officeOther.focus(); return; }
            if (officerSel && !receivingOfficer) { flag(officerSel); flagOfficerSelect2(); $(officerSel).select2('open'); return; }
            if (registrySel && !registry) { flag(registrySel); registrySel.focus(); return; }
            if (purposeSel && !rawPurposeValue) { flag(purposeSel); purposeSel.focus(); return; }
            if (purposeIsOther && !requestPurposeOther) { flag(purposeOther); purposeOther.focus(); return; }
            // "In-Transit" is a purely internal movement (no loan/return expected),
            // so Timeline (Days) is not required or validated.
            const timelineDaysInput = result.querySelector('[data-fr-timeline-days]');
            if (!purposeIsInTransit && timelineDaysInput && timelineDaysInput.value.trim() !== '') {
                const timelineDaysNum = Number(timelineDaysInput.value);
                if (!Number.isInteger(timelineDaysNum) || timelineDaysNum < 0 || timelineDaysNum > 365) {
                    flag(timelineDaysInput);
                    timelineDaysInput.focus();
                    Swal.fire({ icon: 'warning', title: 'Invalid Timeline', text: 'Timeline (Days) must be a whole number between 0 and 365.' });
                    return;
                }
            }

            frBtn.disabled = true;
            frBtn.innerHTML = updateId
                ? '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Updating…'
                : '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(FR_URL, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number:d.file_number, file_title:d.file_title, current_location:d.current_location, resolved_status:d.status, receiving_officer: receivingOfficer, requester_department: requesterDept, requester_office: requesterOffice, requester_office_code: requesterOfficeCode, registry: registry || null, registry_code: registryCode || null, force: force ? 1 : 0, update_existing_id: updateId || null, request_purpose_id: requestPurposeId || null, request_purpose_other: requestPurposeOther || null, requester_director_id: directorIsOther ? null : directorId, requester_director_first_name: directorFirstName, requester_director_last_name: directorLastName }),
                });
                const json = await res.json();
                if (json.success) {
                    const reqNo = esc(json.data?.request_no || '');
                    const isMissing = frBtn.getAttribute('data-missing-file') === '1';
                    frBtn.outerHTML = json.updated
                        ? `<span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                        <i data-lucide="check" class="h-4 w-4"></i> Requester details updated on ${reqNo}</span>`
                        : isMissing
                        ? `<span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                        <i data-lucide="check" class="h-4 w-4"></i> Request ${reqNo} saved — file is in the missing list. Refer to Original Registry.</span>`
                        : `<span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                        <i data-lucide="check" class="h-4 w-4"></i> File Request ${reqNo} sent to SCB Monitors</span>`;
                    loadLog();
                } else if (json.duplicate) {
                    showFrDuplicate(d, frBtn, json.existing || {});
                } else {
                    frBtn.disabled = false; frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Retry Send';
                    alert(json.message || 'Could not send the request.');
                }
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                frBtn.disabled = false; frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Retry Send';
                alert('Network error — please try again.');
            }
        }

        // ── Registry digital preview ────────────────────────────────────────────
        // When a Registry (Origin) is selected, look up the file folder in that
        // registry's on-disk source (SLTR / Cadastral / KANGIS / Physical Planning)
        // and show the scanned image(s)/document(s) if the folder exists.
        const REGISTRY_FILES_URL = "{{ route('digital-request.registry-files') }}";
        const REG_IMG_EXT = ['jpg','jpeg','png','gif','webp','bmp','tif','tiff'];

        async function loadRegistryPreview(fileNo, registry, box) {
            if (!box) return;
            box.innerHTML = '';
            if (!fileNo || !registry) return;
            box.innerHTML = `<div class="flex items-center gap-2 text-[11px] text-gray-400"><i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Checking ${esc(registry)} digital copy…</div>`;
            if (window.lucide) window.lucide.createIcons();
            try {
                const res  = await fetch(REGISTRY_FILES_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_no: fileNo, registry }),
                });
                const json = await res.json();
                const files = (json && json.available && Array.isArray(json.files)) ? json.files : [];
                if (!files.length) {
                    box.innerHTML = `<div class="flex items-center gap-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-[11px] text-gray-500"><i data-lucide="folder-x" class="h-3.5 w-3.5"></i> No digital copy found in ${esc(registry)} for this file.</div>`;
                    if (window.lucide) window.lucide.createIcons();
                    return;
                }
                const thumbs = files.map((f, i) => {
                    const isImg = qsIsImg(f);
                    const isPdf = (f.ext || '').toLowerCase() === 'pdf';
                    const inner = isImg
                        ? `<img src="${esc(f.url)}" alt="${esc(f.name)}" loading="lazy" class="h-16 w-16 object-cover rounded-md border border-gray-200 group-hover:ring-2 group-hover:ring-indigo-400">`
                        : `<span class="flex h-16 w-16 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-gray-500 group-hover:ring-2 group-hover:ring-indigo-400"><i data-lucide="${isPdf ? 'file-text' : 'file'}" class="h-6 w-6"></i></span>`;
                    return `<button type="button" data-reg-i="${i}" class="group relative cursor-pointer" title="${esc(f.name)}${f.category ? ' · ' + esc(f.category) : ''}">${inner}</button>`;
                }).join('');
                box.innerHTML = `
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 px-3 py-2.5">
                        <div class="flex items-center gap-1.5 text-[11px] font-bold text-indigo-700 mb-2">
                            <i data-lucide="images" class="h-3.5 w-3.5"></i> ${esc(registry)} digital copy · ${files.length} page${files.length > 1 ? 's' : ''}
                        </div>
                        <div class="flex flex-wrap gap-2">${thumbs}</div>
                    </div>`;
                // Open the registry pages in the same in-page lightbox (images + PDFs)
                // rather than a new browser tab.
                box.querySelectorAll('[data-reg-i]').forEach(el =>
                    el.addEventListener('click', () => qsOpenDig(files, parseInt(el.dataset.regI, 10))));
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                box.innerHTML = `<div class="text-[11px] text-red-600">Could not load the registry digital copy.</div>`;
            }
        }

        // ── File Digital Library (cover card + lightbox; FileIndexing source) ─────
        // Mirrors the mobile File Search / DFR preview: shows the scanned pages held
        // for this file number in the digital library, with a full-screen viewer.
        const DIGITAL_FILES_URL = "{{ route('digital-request.digital-files') }}";
        let qsDigFiles = [], qsDigIndex = 0, qsDigStripBuilt = false;
        // The list currently shown in the lightbox. May be the File Digital Library
        // (qsDigFiles) or a registry digital copy — both share the same viewer so PDFs
        // and images open inline in the modal instead of a new browser tab.
        let qsViewFiles = [];
        let qsDigRotation = 0, qsPdfToken = 0;
        const qsIsImg = f => REG_IMG_EXT.includes((f.ext || '').toLowerCase());

        async function loadFsDigital(fileNo) {
            const box = document.getElementById('qs-fsDigital');
            if (!box) return;
            box.innerHTML = `<div class="flex items-center gap-2 text-[11px] text-gray-400"><i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Checking the digital library…</div>`;
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(DIGITAL_FILES_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_no: fileNo }),
                });
                const json = await res.json();
                let files = (json && json.available && Array.isArray(json.files)) ? json.files : [];
                if (files.length) files = await qsPruneMissing(files);
                qsDigFiles = files;
                renderFsDigital();
            } catch (e) {
                qsDigFiles = [];
                box.innerHTML = `<div class="flex items-center gap-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-[11px] text-gray-500"><i data-lucide="folder-x" class="h-3.5 w-3.5"></i> Could not load the digital file.</div>`;
                if (window.lucide) window.lucide.createIcons();
            }
        }

        // Drop pages whose soft copy is missing — only on a definitive 404 (fail open).
        async function qsPruneMissing(files) {
            const present = new Array(files.length).fill(true);
            let i = 0;
            const worker = async () => {
                while (i < files.length) {
                    const idx = i++;
                    try { const r = await fetch(files[idx].url, { method: 'HEAD' }); if (r.status === 404) present[idx] = false; }
                    catch (e) {}
                }
            };
            await Promise.all(Array.from({ length: Math.min(8, files.length) }, worker));
            return files.filter((_, idx) => present[idx]);
        }

        function renderFsDigital() {
            qsDigStripBuilt = false;
            const box = document.getElementById('qs-fsDigital');
            if (!box) return;
            if (!qsDigFiles.length) {
                // No library copy → render nothing. The registry-based preview below the
                // Registry (Origin) dropdown already covers the "not found" message.
                box.innerHTML = '';
                box.classList.add('hidden');
                return;
            }
            box.classList.remove('hidden');
            const cover = qsDigFiles[0];
            const n = qsDigFiles.length;
            const coverInner = qsIsImg(cover)
                ? `<img src="${esc(cover.url)}" alt="cover" loading="lazy" class="h-full w-full object-cover">`
                : `<i data-lucide="file-text" class="h-7 w-7 text-gray-400"></i>`;
            box.innerHTML = `
                <button type="button" data-dig-open class="group w-full flex items-center gap-3 rounded-xl border border-indigo-100 bg-indigo-50/40 px-3 py-2.5 text-left hover:bg-indigo-50 transition">
                    <span class="h-14 w-14 shrink-0 rounded-lg overflow-hidden border border-indigo-200 bg-white flex items-center justify-center">${coverInner}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5 text-[12px] font-bold text-indigo-700"><i data-lucide="images" class="h-3.5 w-3.5"></i> File Digital Library</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">${n} page${n > 1 ? 's' : ''} in the digital library</span>
                        <span class="block text-[11px] font-semibold text-indigo-600 mt-0.5"><i data-lucide="expand" class="inline h-3 w-3 mr-1 align-text-bottom"></i>Click to open gallery</span>
                    </span>
                    <i data-lucide="chevron-right" class="h-4 w-4 text-indigo-400 group-hover:translate-x-0.5 transition"></i>
                </button>`;
            const openBtn = box.querySelector('[data-dig-open]');
            if (openBtn) openBtn.addEventListener('click', () => qsOpenDig(qsDigFiles, 0));
            if (window.lucide) window.lucide.createIcons();
        }

        // Lazily build the full-screen viewer once and reuse it across searches.
        function qsEnsureDigViewer() {
            if (document.getElementById('qsDigViewer')) return;
            const el = document.createElement('div');
            el.id = 'qsDigViewer';
            el.className = 'fixed inset-0 z-[60] hidden flex-col bg-black/90';
            el.innerHTML = `
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-white">
                    <div class="min-w-0">
                        <div id="qsDigVName" class="text-sm font-semibold truncate"></div>
                        <div id="qsDigVCount" class="text-[11px] text-white/60"></div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" id="qsDigRotateLeft" title="Rotate left" class="hidden p-2 rounded-lg hover:bg-white/10 text-white"><i data-lucide="rotate-ccw" class="h-5 w-5"></i></button>
                        <button type="button" id="qsDigRotateRight" title="Rotate right" class="hidden p-2 rounded-lg hover:bg-white/10 text-white"><i data-lucide="rotate-cw" class="h-5 w-5"></i></button>
                        <button type="button" data-dig-close class="p-2 rounded-lg hover:bg-white/10 text-white"><i data-lucide="x" class="h-5 w-5"></i></button>
                    </div>
                </div>
                <div id="qsDigStage" class="relative flex-1 flex items-center justify-center overflow-hidden px-2">
                    <button type="button" id="qsDigPrev" class="absolute left-3 top-1/2 -translate-y-1/2 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white disabled:opacity-30"><i data-lucide="chevron-left" class="h-6 w-6"></i></button>
                    <div id="qsDigStageInner" class="flex items-center justify-center"></div>
                    <button type="button" id="qsDigNext" class="absolute right-3 top-1/2 -translate-y-1/2 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white disabled:opacity-30"><i data-lucide="chevron-right" class="h-6 w-6"></i></button>
                </div>
                <div id="qsDigStrip" class="flex gap-2 overflow-x-auto px-4 py-3 bg-black/40"></div>`;
            document.body.appendChild(el);
            el.querySelector('[data-dig-close]').addEventListener('click', qsCloseDig);
            el.addEventListener('click', (e) => { if (e.target === el) qsCloseDig(); });
            document.getElementById('qsDigPrev').addEventListener('click', qsDigPrev);
            document.getElementById('qsDigNext').addEventListener('click', qsDigNext);
            document.getElementById('qsDigRotateLeft').addEventListener('click', () => qsDigRotate(-1));
            document.getElementById('qsDigRotateRight').addEventListener('click', () => qsDigRotate(1));
        }

        function qsOpenDig(files, i) {
            // Back-compat: qsOpenDig(index) defaults to the File Digital Library list.
            if (typeof files === 'number') { i = files; files = qsDigFiles; }
            qsViewFiles = Array.isArray(files) ? files : [];
            if (!qsViewFiles.length) return;
            qsEnsureDigViewer();
            qsDigStripBuilt = false;
            qsDigIndex = Math.max(0, Math.min(i, qsViewFiles.length - 1));
            const v = document.getElementById('qsDigViewer');
            v.classList.remove('hidden'); v.classList.add('flex');
            document.body.style.overflow = 'hidden';
            qsDigBuildStrip();
            qsDigRenderViewer();
            if (window.lucide) window.lucide.createIcons();
        }
        function qsCloseDig() {
            const v = document.getElementById('qsDigViewer');
            if (!v) return;
            qsPdfToken++; // cancel any in-flight PDF render
            v.classList.add('hidden'); v.classList.remove('flex');
            const inner = document.getElementById('qsDigStageInner');
            if (inner) inner.innerHTML = '';
            document.body.style.overflow = '';
        }
        function qsDigNext() { if (qsDigIndex < qsViewFiles.length - 1) { qsDigIndex++; qsDigRenderViewer(); } }
        function qsDigPrev() { if (qsDigIndex > 0) { qsDigIndex--; qsDigRenderViewer(); } }
        function qsDigGoto(i) { qsDigIndex = Math.max(0, Math.min(i, qsViewFiles.length - 1)); qsDigRenderViewer(); }

        function qsDigRenderViewer() {
            const f = qsViewFiles[qsDigIndex];
            if (!f) return;
            qsPdfToken++;        // cancel any in-flight PDF render
            qsDigRotation = 0;   // each page starts upright
            const stage = document.getElementById('qsDigStageInner');
            const isImg = qsIsImg(f);
            const isPdf = (f.ext || '').toLowerCase() === 'pdf';
            // Rotate tools apply to images only.
            document.getElementById('qsDigRotateLeft').classList.toggle('hidden', !isImg);
            document.getElementById('qsDigRotateRight').classList.toggle('hidden', !isImg);
            if (isImg) {
                stage.innerHTML = `<i data-lucide="loader" class="h-8 w-8 text-white animate-spin"></i>`;
                if (window.lucide) window.lucide.createIcons();
                const img = new Image();
                img.alt = f.name;
                img.style.objectFit = 'contain'; img.style.transition = 'transform .2s ease';
                img.onload = () => { stage.innerHTML = ''; stage.appendChild(img); qsApplyImgRotation(); };
                img.onerror = () => { stage.innerHTML = `<div style="color:#fff;font-size:13px;">Could not load this page.</div>`; };
                img.src = f.url;
            } else if (isPdf) {
                // Browsers/webviews that won't embed a PDF in an <iframe> get a blank
                // frame, so paint the pages to <canvas> with PDF.js instead.
                qsRenderPdf(f);
            } else {
                // Other doc types embed in a frame, with a fallback link in case the
                // browser can't display the format inline.
                stage.innerHTML = `<div style="display:flex;flex-direction:column;gap:8px;align-items:center;">
                    <iframe src="${esc(f.url)}" title="${esc(f.name)}" style="width:92vw;height:78vh;background:#fff;border:0;border-radius:6px;"></iframe>
                    <a href="${esc(f.url)}" target="_blank" rel="noopener" style="color:#fff;font-size:12px;text-decoration:underline;">Open in new tab</a>
                </div>`;
            }
            document.getElementById('qsDigVName').textContent = f.name;
            document.getElementById('qsDigVCount').textContent = `Page ${qsDigIndex + 1} of ${qsViewFiles.length}`;
            document.getElementById('qsDigPrev').disabled = qsDigIndex === 0;
            document.getElementById('qsDigNext').disabled = qsDigIndex === qsViewFiles.length - 1;
            [qsDigIndex - 1, qsDigIndex + 1].forEach(j => { const nf = qsViewFiles[j]; if (nf && qsIsImg(nf)) { const im = new Image(); im.src = nf.url; } });
            const strip = document.getElementById('qsDigStrip');
            strip.querySelectorAll('[data-strip-item]').forEach((el, j) => {
                el.classList.toggle('ring-2', j === qsDigIndex);
                el.classList.toggle('ring-indigo-400', j === qsDigIndex);
                if (j === qsDigIndex) el.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
            });
        }

        function qsDigBuildStrip() {
            if (qsDigStripBuilt) return;
            const strip = document.getElementById('qsDigStrip');
            strip.innerHTML = qsViewFiles.map((f, i) => {
                const thumb = qsIsImg(f)
                    ? `<img src="${esc(f.url)}" alt="" loading="lazy" class="h-full w-full object-cover">`
                    : `<i data-lucide="file-text" class="h-4 w-4 text-white/60"></i>`;
                return `<button type="button" data-strip-item data-dig-i="${i}" class="relative h-14 w-12 shrink-0 rounded-md overflow-hidden border border-white/20 bg-white/5 flex items-center justify-center ring-indigo-400">${thumb}<span class="absolute bottom-0 right-0 px-1 text-[9px] font-bold text-white bg-black/50">${i + 1}</span></button>`;
            }).join('');
            strip.querySelectorAll('[data-strip-item]').forEach(el =>
                el.addEventListener('click', () => qsDigGoto(parseInt(el.dataset.digI, 10))));
            qsDigStripBuilt = true;
            if (window.lucide) window.lucide.createIcons();
        }

        // ── Image rotate tool ───────────────────────────────────────────────
        // Rotate the current image in 90° steps. When sideways (90°/270°) the
        // max width/height caps are swapped so the rotated image still fits.
        function qsDigRotate(dir) {
            qsDigRotation = (((qsDigRotation + (dir < 0 ? -90 : 90)) % 360) + 360) % 360;
            qsApplyImgRotation();
        }
        function qsApplyImgRotation() {
            const img = document.querySelector('#qsDigStageInner img');
            if (!img) return;
            img.style.transformOrigin = 'center center';
            img.style.transform = `rotate(${qsDigRotation}deg)`;
            if (qsDigRotation % 180 === 0) {
                img.style.maxWidth = '92vw'; img.style.maxHeight = '80vh';
            } else {
                img.style.maxWidth = '80vh'; img.style.maxHeight = '92vw';
            }
        }

        // ── PDF rendering (PDF.js) ──────────────────────────────────────────
        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
        function qsPdfFallback(f) {
            return `<div style="display:flex;flex-direction:column;gap:8px;align-items:center;">
                <iframe src="${esc(f.url)}" title="${esc(f.name)}" style="width:92vw;height:78vh;background:#fff;border:0;border-radius:6px;"></iframe>
                <a href="${esc(f.url)}" target="_blank" rel="noopener" style="color:#fff;font-size:12px;text-decoration:underline;">Open PDF in new tab</a>
            </div>`;
        }
        async function qsRenderPdf(f) {
            const token = ++qsPdfToken;
            const stage = document.getElementById('qsDigStageInner');
            stage.innerHTML = `<i data-lucide="loader" class="h-8 w-8 text-white animate-spin"></i>`;
            if (window.lucide) window.lucide.createIcons();
            if (!window.pdfjsLib) { stage.innerHTML = qsPdfFallback(f); return; }
            try {
                const pdf = await pdfjsLib.getDocument({ url: f.url }).promise;
                if (token !== qsPdfToken) return; // a newer page took over
                const wrap = document.createElement('div');
                wrap.style.cssText = 'width:92vw;height:80vh;display:flex;flex-direction:column;';
                const pages = document.createElement('div');
                pages.style.cssText = 'flex:1;overflow-y:auto;display:flex;flex-direction:column;align-items:center;gap:10px;padding:10px;';
                const link = document.createElement('a');
                link.href = f.url; link.target = '_blank'; link.rel = 'noopener';
                link.textContent = 'Open PDF in new tab';
                link.style.cssText = 'flex-shrink:0;text-align:center;padding:8px;color:#fff;font-size:12px;text-decoration:underline;';
                wrap.appendChild(pages); wrap.appendChild(link);
                stage.innerHTML = ''; stage.appendChild(wrap);
                const scale = Math.min(2, (window.devicePixelRatio || 1) * 1.4);
                for (let p = 1; p <= pdf.numPages; p++) {
                    if (token !== qsPdfToken) return;
                    const page = await pdf.getPage(p);
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width; canvas.height = viewport.height;
                    canvas.style.cssText = 'max-width:100%;height:auto;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.4);';
                    pages.appendChild(canvas);
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                }
            } catch (e) {
                if (token !== qsPdfToken) return;
                stage.innerHTML = qsPdfFallback(f);
            }
        }

        document.addEventListener('keydown', (e) => {
            const v = document.getElementById('qsDigViewer');
            if (!v || v.classList.contains('hidden')) return;
            if (e.key === 'Escape') qsCloseDig();
            else if (e.key === 'ArrowRight') qsDigNext();
            else if (e.key === 'ArrowLeft') qsDigPrev();
            else if (e.key === 'r' || e.key === 'R') qsDigRotate(e.shiftKey ? -1 : 1);
        });

        // Re-direct an in-transit file's request straight to the office currently holding
        // it (its last receiving officer) instead of routing it to the SCB Monitor.
        // Mirrors the Digital File Request "Send Request to {office}" redirect.
        const REDIRECT_URL = "{{ route('digital-request.store') }}";
        async function sendRedirect(d, btn) {
            const office = d.receiving_officer_name || d.current_location || 'Current Office';
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(REDIRECT_URL, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({
                        file_no: d.file_number,
                        file_title: d.file_title,
                        request_type: 'Physical',
                        is_redirected: true,
                        receiving_officer: d.receiving_officer_name || null,
                        current_file_location: d.current_location || null,
                        current_file_holder: d.receiving_officer_name || null,
                    }),
                });
                const json = await res.json();
                if (json.success) {
                    // Grey out / disable the re-direct button now that the request is sent,
                    // keeping its original label. Show the confirmation as feedback below.
                    btn.disabled = true;
                    btn.className = 'inline-flex items-center gap-2 rounded-lg bg-gray-300 px-4 py-2 text-sm font-semibold text-white cursor-not-allowed opacity-60';
                    btn.innerHTML = `<i data-lucide="user-check" class="h-4 w-4"></i> Re-direct Request to ${esc(office)}`;
                    Swal.fire('Request re-directed to ' + office, '', 'success');
                    // Enable the Print Tracking Confirmation Slip now that the request is sent.
                    const slip = result.querySelector('[data-print-slip]');
                    if (slip) {
                        slip.classList.remove('bg-gray-300','cursor-not-allowed','pointer-events-none','opacity-60');
                        slip.classList.add('bg-amber-600','hover:bg-amber-700');
                        slip.removeAttribute('aria-disabled');
                        slip.removeAttribute('tabindex');
                    }
                } else if (json.duplicate) {
                    btn.disabled = false; btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Re-direct Request to ' + esc(office);
                    alert(json.message || 'This file already has an open request.');
                } else {
                    btn.disabled = false; btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Retry Re-direct';
                    alert(json.message || 'Could not re-direct the request.');
                }
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                btn.disabled = false; btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Retry Re-direct';
                alert('Network error — please try again.');
            }
        }

        // Re-direct a duplicate file (in the duplicate_fileno table) to the Director Land
        // (Land Department) instead of raising a blind File Search Request to the SCB.
        async function sendRedirectToDirectorLand(d, btn) {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Re-directing…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(REDIRECT_LAND_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({
                        file_number: d.file_number,
                        file_title: d.file_title,
                        // Null on a file with no duplicate — the normal workflow.
                        selected_record_id:     selectedCandidate ? selectedCandidate.record_id : null,
                        selected_record_source: selectedCandidate ? selectedCandidate.source : null,
                    }),
                });
                const json = await res.json();
                if (json.success) {
                    btn.disabled = true;
                    btn.className = 'inline-flex items-center gap-2 rounded-lg bg-gray-300 px-4 py-2 text-sm font-semibold text-white cursor-not-allowed opacity-60';
                    btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i> Request Sent to Director Land';
                    if (window.Swal) Swal.fire(json.message || 'Re-directed to Director Land (Land Department).', '', 'success');
                    loadLog();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Send Request to Director Land';
                    alert(json.message || 'Could not re-direct to Director Land.');
                }
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Send Request to Director Land';
                alert('Network error — please try again.');
            }
        }

        // Re-direct a file under DCIV investigation to the DCIV Director instead of
        // raising a File Search Request to the SCB. Mirrors sendRedirectToDirectorLand().
        async function sendRedirectToDcivDirector(d, btn) {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Re-directing…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(REDIRECT_DCIV_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number: d.file_number, file_title: d.file_title }),
                });
                const json = await res.json();
                if (json.success) {
                    btn.disabled = true;
                    btn.className = 'inline-flex items-center gap-2 rounded-lg bg-gray-300 px-4 py-2 text-sm font-semibold text-white cursor-not-allowed opacity-60';
                    btn.removeAttribute('style');
                    btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i> Re-directed To DCIV Director';
                    if (window.Swal) Swal.fire(json.message || 'Re-directed to DCIV Director.', '', 'success');
                    loadLog();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="shield-alert" class="h-4 w-4"></i> Re-direct To DCIV Director';
                    alert(json.message || 'Could not re-direct to DCIV Director.');
                }
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="shield-alert" class="h-4 w-4"></i> Re-direct To DCIV Director';
                alert('Network error — please try again.');
            }
        }

        // ── File Request Log + SCB Feedback ──
        const LOG_URL    = "{{ route('create-file-tracker.quick-search.file-request-log') }}";
        const ACTED_BASE = "{{ url('create-file-tracker/quick-search/file-request') }}";
        const fbBox      = document.getElementById('qs-feedback');
        const tblBox     = document.getElementById('qs-feedback-table');
        const logFilters = document.getElementById('qs-log-filters');
        let   logStatus  = 'FOUND';   // default active tab
        let   logFrom    = '';        // date-range filter (YYYY-MM-DD)
        let   logTo      = '';
        let   lastReport = {};        // last report summary (for exports)

        // Loaded rows kept client-side so the search boxes can filter without a refetch.
        let   scbRows = [], logRows = [];
        let   scbQuery = '', logQuery = '';
        let   scbPage = 1;
        const SCB_PAGE_SIZE = 20;

        // Render the SCB Feedback table from the current filter + page.
        function renderScb() {
            renderTable(scbRows.filter(r => rowMatches(r, scbQuery)));
        }

        // Case-insensitive match across the searchable fields of a request row.
        function rowMatches(r, q) {
            if (!q) return true;
            q = q.toLowerCase();
            return [r.file_number, r.request_no, r.file_title, r.receiving_officer,
                    r.requested_by, r.requester_office, r.requester_department,
                    r.current_location, r.location_type, r.scb_response]
                .some(v => v && String(v).toLowerCase().includes(q));
        }

        const LOG_BADGE = {
            'Awaiting':  'bg-amber-100 text-amber-800',
            'Found':     'bg-green-100 text-green-800',
            'Found (Indexed)': 'bg-green-100 text-green-800',
            'Found for Indexing': 'bg-amber-100 text-amber-800',
            'Not Found': 'bg-red-100 text-red-800',
        };

        // Front Desk acts on an SCB-responded request (Log File / Refer), then it
        // drops out of the SCB Feedback queue and lives in the File Request Log.
        async function frontDeskAct(btn) {
            const id = btn.dataset.id, kind = btn.dataset.fbAct;
            const fno = btn.dataset.fno, title = btn.dataset.title || '', officer = btn.dataset.officer || '';
            const office = btn.dataset.office || '', dept = btn.dataset.dept || '';
            const registry = btn.dataset.registry || '';
            const purposeId = btn.dataset.purposeId || '', purposeName = btn.dataset.purposeName || '', deadline = btn.dataset.deadline || '';
            btn.disabled = true;
            try {
                await fetch(`${ACTED_BASE}/${id}/front-desk-acted`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
            } catch (e) { /* non-fatal — still let the Front Desk proceed */ }

            if (kind === 'log') {
                handleLogFileClick(
                    { file_number: fno, file_title: title, receiving_officer: officer, requester_office: office, requester_department: dept, registry: registry },   // navigates to Create File Tracker (?url=… by registry)
                    { request_purpose_id: purposeId, request_purpose_name: purposeName, expected_return_date: deadline }
                );
            } else {
                window.open(`${SLIP_URL}?file_number=${encodeURIComponent(fno)}&variant=refer_registry`, '_blank');
                loadScbFeedback();
                loadLog();
            }
        }

        // Reporting summary tiles.
        function renderReport(report) {
            report = report || {};
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = (v ?? 0); };
            const dateEl = document.getElementById('qs-rep-date');
            if (dateEl) dateEl.textContent = report.range_label
                ? `Range · ${report.range_label}`
                : (report.date ? `Today · ${report.date}` : '');
            set('qs-rep-today',    report.submitted_today);
            set('qs-rep-today-found',    report.today_found);
            set('qs-rep-today-notfound', report.today_not_found);
            set('qs-rep-today-awaiting', report.today_awaiting);
            set('qs-rep-blind',    report.blind_open);
            set('qs-rep-found',    report.found);
            set('qs-rep-notfound', report.not_found);
            set('qs-rep-missing',  report.missing);
            set('qs-rep-awaiting', report.awaiting);
            lastReport = report;
        }

        function renderLogFilters(counts) {
            counts = counts || {};
            const totalEl = document.getElementById('qs-log-total');
            if (totalEl) totalEl.textContent = `${counts.all ?? 0}`;
            const chips = [
                ['BLIND',     'Blind/Open', counts.blind_open],
                ['FOUND',     'Found',      counts.found],
                ['NOT_FOUND', 'Not Found',  counts.not_found],
                ['MISSING',   'Missing',    counts.missing],
                ['PENDING',   'Awaiting',   counts.pending],
                // ['', 'FSR History', counts.all],  // hidden for now
            ];
            logFilters.innerHTML = chips.map(([val, label, n]) => {
                const on = logStatus === val;
                return `<button type="button" data-log-filter="${val}"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border transition-colors ${
                        on ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'}">
                    ${label}<span class="inline-flex items-center justify-center min-w-[1.1rem] h-4 px-1 rounded-full text-[10px] font-bold ${
                        on ? 'bg-white/25 text-white' : 'bg-gray-100 text-gray-500'}">${n ?? 0}</span></button>`;
            }).join('');
            logFilters.querySelectorAll('[data-log-filter]').forEach(b =>
                b.addEventListener('click', () => { logStatus = b.dataset.logFilter; loadLog(); }));
        }

        // SCB Feedback queue — SCB has responded, Front Desk hasn't acted yet.
        function renderTable(rows) {
            if (!rows.length) {
                tblBox.innerHTML = '<div class="px-4 py-10 text-center text-xs text-gray-400 flex flex-col items-center gap-2"><i data-lucide="inbox" class="h-6 w-6 text-gray-300"></i> Nothing awaiting your action.</div>';
                if (window.lucide) window.lucide.createIcons();
                return;
            }
            const dash = '<span class="text-gray-300">—</span>';
            const splitDT = (s) => { if (!s) return null; const i = s.indexOf(' '); return i < 0 ? { d:s, t:'' } : { d:s.slice(0, i), t:s.slice(i + 1) }; };

            // Paginate — 20 rows per page.
            const total = rows.length;
            const pages = Math.max(1, Math.ceil(total / SCB_PAGE_SIZE));
            if (scbPage > pages) scbPage = pages;
            if (scbPage < 1) scbPage = 1;
            const start  = (scbPage - 1) * SCB_PAGE_SIZE;
            const pageRows = rows.slice(start, start + SCB_PAGE_SIZE);

            tblBox.innerHTML = `
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400 bg-gray-50 border-b border-gray-100">
                            <th class="px-3 py-2.5 w-10">S/N</th>
                            <th class="px-3 py-2.5">File No</th>
                            <th class="px-3 py-2.5">Title</th>
                            <th class="px-3 py-2.5">Requester</th>
                            <th class="px-3 py-2.5">Location</th>
                            <th class="px-3 py-2.5">SCB Response</th>
                            <th class="px-3 py-2.5 whitespace-nowrap">Timeline</th>
                            <th class="px-3 py-2.5 whitespace-nowrap">Sent</th>
                            <th class="px-3 py-2.5 whitespace-nowrap">Responded</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${pageRows.map((r, i) => {
                            // For a Not Found outcome, spell out the kind (Missing / Pending)
                            // alongside the response, e.g. "Not Found (Pending)", and give each
                            // kind its own colour — Missing red, Pending amber.
                            const nft       = (r.not_found && r.not_found_type)
                                ? (String(r.not_found_type).toUpperCase() === 'MISSING' ? 'Missing' : 'Pending') : '';
                            const respLabel = nft ? `${r.scb_response} (${nft})` : r.scb_response;
                            const isReadyIndexing = !!r.ready_indexing;
                            const badge  = isReadyIndexing ? 'bg-amber-100 text-amber-800'
                                : nft === 'Missing' ? 'bg-red-100 text-red-800'
                                : nft === 'Pending' ? 'bg-amber-100 text-amber-800'
                                : (LOG_BADGE[r.scb_response] || 'bg-gray-100 text-gray-700');
                            const icon   = isReadyIndexing
                                ? 'hourglass'
                                : (r.found ? 'check' : (r.not_found ? (nft === 'Pending' ? 'hourglass' : 'x') : 'clock'));
                            const sn     = start + i + 1;
                            const sentDt = splitDT(r.requested_at);
                            const dt     = splitDT(r.responded_at);
                            const action = (r.found && r.can_log)
                                ? `<button type="button" data-fb-act="log" data-id="${r.id}" data-fno="${esc(r.file_number)}" data-title="${esc(r.file_title || '')}" data-officer="${esc(r.receiving_officer || '')}" data-office="${esc(r.requester_office || '')}" data-dept="${esc(r.requester_department || '')}" data-registry="${esc(r.registry || '')}" data-purpose-id="${esc(r.request_purpose_id || '')}" data-purpose-name="${esc(r.request_purpose_name || '')}" data-deadline="${esc(r.expected_return_date || '')}" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"><i data-lucide="file-plus" class="h-3.5 w-3.5"></i> Log File</button>`
                                : (r.found
                                    ? `<button type="button" disabled class="inline-flex items-center gap-1 rounded-lg bg-amber-300 px-3 py-1.5 text-xs font-semibold text-white cursor-not-allowed opacity-80" title="File has been found but is pending indexing"><i data-lucide="hourglass" class="h-3.5 w-3.5"></i>Pending Indexing</button>`
                                : (r.not_found
                                    ? `<button type="button" data-fb-act="refer" data-id="${r.id}" data-fno="${esc(r.file_number)}" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"><i data-lucide="printer" class="h-3.5 w-3.5"></i> Print</button>`
                                    : `<span class="text-[11px] text-gray-400 italic">Awaiting SCB…</span>`));
                            const delBtn = IS_SUPER_ADMIN
                                ? `<button type="button" data-fb-del data-id="${r.id}" title="Delete request" class="inline-flex items-center gap-1 rounded-lg bg-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>`
                                : '';
                            // Revert an SCB Found/Not-Found response (e.g. tapped by mistake),
                            // sending the request back to the open queue. Super Admins only.
                            const revertBtn = (IS_SUPER_ADMIN && (r.found || r.not_found))
                                ? `<button type="button" data-fb-revert data-id="${r.id}" title="Revert SCB response" class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Revert response</button>`
                                : '';
                            // OFS (Office Priority Search) requests get a dedicated amber/gold
                            // treatment so they stand out from regular front-desk requests.
                            const ofsBadge = r.is_ofs
                                ? `<span class="mt-1 ml-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 ring-1 ring-amber-300" title="Office Priority Search${r.ofs_rank ? ' · ' + esc(r.ofs_rank) : ''}"><i data-lucide="crown" class="h-2.5 w-2.5"></i> OFS${r.ofs_rank ? ' · ' + esc(r.ofs_rank) : ''}</span>`
                                : '';
                            // Tint the whole row by the SCB outcome — green for Found, red for
                            // Not Found — so the queue reads at a glance. Rows still awaiting a
                            // response keep the OFS amber highlight (if any).
                            const rowTint = r.found
                                ? 'bg-emerald-50/70 border-l-4 border-l-emerald-400 hover:bg-emerald-50'
                                : (r.not_found
                                    ? (nft === 'Pending'
                                        ? 'bg-amber-50/70 border-l-4 border-l-amber-400 hover:bg-amber-50'
                                        : 'bg-red-50/70 border-l-4 border-l-red-400 hover:bg-red-50')
                                    : (r.is_ofs ? 'bg-amber-50/60 border-l-4 border-l-amber-400 hover:bg-gray-50/60' : 'hover:bg-gray-50/60'));
                            return `
                            <tr class="border-b border-gray-200 align-top ${rowTint}">
                                <td class="px-3 py-3 whitespace-nowrap text-gray-400 font-medium">${sn}</td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-gray-800">${esc(r.file_number)}</div>
                                    <div class="text-[11px] text-gray-400">${esc(r.request_no)}</div>
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${r.is_dfr ? 'bg-gray-200 text-gray-600' : (r.is_blind ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700')}">
                                        <i data-lucide="${r.is_dfr ? 'file-text' : (r.is_blind ? 'eye-off' : 'folder-search')}" class="h-2.5 w-2.5"></i> ${esc(r.request_type)}</span>${ofsBadge}
                                </td>
                                <td class="px-3 py-3 text-gray-600 max-w-[180px] truncate" title="${esc(r.file_title || '')}">${r.file_title ? esc(r.file_title) : dash}</td>
                                <td class="px-3 py-3 text-gray-600 max-w-[170px] align-top">
                                    <div class="truncate font-medium text-gray-800" title="${esc(r.receiving_officer || '')}">${r.receiving_officer ? `<span class="upc-trigger" data-user-card data-user-name="${esc(r.receiving_officer)}" title="View profile">${esc(r.receiving_officer)}</span>` : dash}</div>
                                    ${(() => {
                                        const chips = [];
                                        if (r.requester_department) chips.push(`<span class="inline-flex items-center gap-1 rounded-full bg-violet-50 border border-violet-100 px-1.5 py-px text-[10px] font-semibold text-violet-700" title="Department"><i data-lucide="building-2" class="h-2.5 w-2.5"></i>${esc(r.requester_department)}</span>`);
                                        if (r.requester_office)     chips.push(`<span class="inline-flex items-center gap-1 rounded-full bg-sky-50 border border-sky-100 px-1.5 py-px text-[10px] font-semibold text-sky-700" title="Office"><i data-lucide="map-pin" class="h-2.5 w-2.5"></i>${esc(r.requester_office)}</span>`);
                                        return chips.length ? `<div class="mt-1 flex flex-wrap gap-1">${chips.join('')}</div>` : '';
                                    })()}
                                    ${r.registry ? `<div class="mt-0.5"><span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-100 px-1.5 py-px text-[10px] font-semibold text-indigo-700" title="Origin Registry"><i data-lucide="library" class="h-2.5 w-2.5"></i>${esc(r.registry)}${r.registry_code ? ` · ${esc(r.registry_code)}` : ''}</span></div>` : ''}
                                </td>
                                <td class="px-3 py-3 text-gray-500 max-w-[150px] truncate" title="${esc(r.current_location || r.location_type || '')}">${(r.current_location || r.location_type) ? esc(r.current_location || r.location_type) : dash}</td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ${badge}">
                                        <i data-lucide="${icon}" class="h-3 w-3"></i> ${esc(respLabel)}</span>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">${fbTimelineBadge(r)}</td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs">
                                    ${sentDt ? `<div class="text-gray-700 font-medium">${esc(sentDt.d)}</div><div class="text-gray-400">${esc(sentDt.t)}</div>` : dash}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs">
                                    ${dt ? `<div class="text-gray-700 font-medium">${esc(dt.d)}</div><div class="text-gray-400">${esc(dt.t)}</div>` : dash}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">${action}${revertBtn}${delBtn}</div>
                                </td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
                ${pages > 1 ? `
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-gray-100 text-xs text-gray-500">
                    <span>Showing <span class="font-semibold text-gray-700">${start + 1}–${Math.min(start + SCB_PAGE_SIZE, total)}</span> of <span class="font-semibold text-gray-700">${total}</span></span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-scb-page="prev" ${scbPage <= 1 ? 'disabled' : ''} class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 font-semibold ${scbPage <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'}"><i data-lucide="chevron-left" class="h-3.5 w-3.5"></i> Prev</button>
                        <span class="px-1">Page ${scbPage} / ${pages}</span>
                        <button type="button" data-scb-page="next" ${scbPage >= pages ? 'disabled' : ''} class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 font-semibold ${scbPage >= pages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'}">Next <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></button>
                    </div>
                </div>` : ''}`;
            tblBox.querySelectorAll('[data-fb-act]').forEach(b =>
                b.addEventListener('click', () => frontDeskAct(b)));
            tblBox.querySelectorAll('[data-fb-del]').forEach(b =>
                b.addEventListener('click', () => deleteFileRequest(b)));
            tblBox.querySelectorAll('[data-fb-revert]').forEach(b =>
                b.addEventListener('click', () => revertFileRequest(b)));
            tblBox.querySelectorAll('[data-scb-page]').forEach(b =>
                b.addEventListener('click', () => {
                    if (b.disabled) return;
                    scbPage += (b.dataset.scbPage === 'next' ? 1 : -1);
                    renderScb();
                    tblBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }));
            if (window.lucide) window.lucide.createIcons();
        }

        // Revert an SCB Found/Not-Found response (tapped by mistake) — sends the request
        // back to the open queue so the SCB can respond afresh.
        async function revertFileRequest(btn) {
            if (!confirm('Revert this SCB response and send the request back to the open queue?')) return;
            const id = btn.dataset.id;
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Reverting…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(`${ACTED_BASE}/${id}/revert`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (json.success) {
                    reloadPanels();
                } else {
                    btn.disabled = false; btn.innerHTML = original;
                    alert(json.message || 'Could not revert this response.');
                    if (window.lucide) window.lucide.createIcons();
                }
            } catch (e) {
                btn.disabled = false; btn.innerHTML = original;
                alert('Network error — please try again.');
                if (window.lucide) window.lucide.createIcons();
            }
        }

        // Delete a File Search Request (Super Admins only).
        async function deleteFileRequest(btn) {
            if (!confirm('Delete this file request? This cannot be undone.')) return;
            const id = btn.dataset.id;
            btn.disabled = true;
            try {
                const res = await fetch(`${ACTED_BASE}/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (json.success) {
                    reloadPanels();
                } else {
                    btn.disabled = false;
                    alert(json.message || 'Could not delete the request.');
                }
            } catch (e) {
                btn.disabled = false;
                alert('Network error — please try again.');
            }
        }

        async function loadScbFeedback() {
            tblBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-gray-400">Loading…</div>';
            try {
                const fbUrl = FEEDBACK_URL + (URL_CTX ? ('?url=' + encodeURIComponent(URL_CTX)) : '');
                const res  = await fetch(fbUrl, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                scbRows = (json && json.data) || [];
                // Most recent on top — sort by SCB response time, then by sent time.
                scbRows.sort((a, b) =>
                    ((b.responded_ts || b.requested_ts || 0) - (a.responded_ts || a.requested_ts || 0)));
                scbPage = 1;
                renderScb();
            } catch (e) {
                tblBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-red-500">Could not load the SCB feedback.</div>';
            }
        }

        // File Request Log — read-only history of every request the user raised.
        async function loadLog() {
            fbBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-gray-400">Loading…</div>';
            try {
                const params = new URLSearchParams();
                if (logStatus) params.set('status', logStatus);
                if (logFrom)   params.set('from', logFrom);
                if (logTo)     params.set('to', logTo);
                if (logQuery)  params.set('q', logQuery);
                if (URL_CTX)   params.set('url', URL_CTX);
                const qs   = params.toString();
                const url  = LOG_URL + (qs ? ('?' + qs) : '');
                const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                renderReport(json.report);
                renderLogFilters(json.counts);
                logRows = (json && json.data) || [];
                renderLog(logRows.filter(r => rowMatches(r, logQuery)));
            } catch (e) {
                fbBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-red-500">Could not load the file request log.</div>';
            }
        }

        function renderLog(rows) {
            if (!rows.length) {
                fbBox.innerHTML = '<div class="px-4 py-10 text-center text-xs text-gray-400 flex flex-col items-center gap-2"><i data-lucide="inbox" class="h-6 w-6 text-gray-300"></i> No file requests in this view.</div>';
                if (window.lucide) window.lucide.createIcons();
                return;
            }
            fbBox.innerHTML = `
                    <ul class="divide-y divide-gray-100">
                        ${rows.map(r => {
                            // A blind / not-indexed file reported Not Found is surfaced as "Missing".
                            const isMissing = r.not_found && r.is_blind;
                            // When the SCB picked a Not Found kind, spell it out, e.g. "Not Found (Pending)",
                            // and colour each kind distinctly — Missing red, Pending amber.
                            const nft = (r.not_found && r.not_found_type)
                                ? (String(r.not_found_type).toUpperCase() === 'MISSING' ? 'Missing' : 'Pending') : '';
                            const respLabel = nft ? `${r.scb_response} (${nft})` : (isMissing ? 'Missing' : r.scb_response);
                            const badge = nft === 'Pending' ? 'bg-amber-100 text-amber-800'
                                : (nft === 'Missing' || isMissing) ? 'bg-red-100 text-red-800'
                                : (LOG_BADGE[r.scb_response] || 'bg-gray-100 text-gray-700');
                            const icon  = r.found ? 'check' : (r.not_found ? (nft === 'Pending' ? 'hourglass' : (isMissing ? 'help-circle' : 'x')) : 'clock');
                            const done  = r.front_desk_acted
                                ? `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold bg-gray-200 text-gray-600"><i data-lucide="check-check" class="h-2.5 w-2.5"></i> Completed</span>`
                                : '';
                            return `
                            <li class="px-5 py-4 hover:bg-gray-50/60">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-800 truncate">${esc(r.file_number)}</div>
                                        <div class="text-[11px] text-gray-400">${esc(r.request_no)}</div>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ${badge}">
                                        <i data-lucide="${icon}" class="h-3 w-3"></i> ${esc(respLabel)}</span>
                                </div>
                                <div class="mt-1.5 text-xs text-gray-600 truncate" title="${esc(r.file_title || '')}">${esc(r.file_title || '—')}</div>
                                ${r.receiving_officer ? `<div class="mt-0.5 text-[11px] text-gray-400 truncate">for ${esc(r.receiving_officer)}</div>` : ''}
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500">
                                    ${r.requested_by ? `<span class="upc-trigger inline-flex items-center gap-1 rounded-full bg-slate-50 border border-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-700" data-user-card data-user-name="${esc(r.requested_by)}" title="View profile"><i data-lucide="user" class="h-3 w-3"></i> <span class="uppercase tracking-wide">Sent by</span>: ${esc(r.requested_by)}</span>` : ''}
                                    <span class="inline-flex items-center gap-1"><i data-lucide="map-pin" class="h-3 w-3 text-gray-400"></i> ${esc(r.current_location || r.location_type || '—')}</span>
                                    ${r.requested_at ? `<span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700"><i data-lucide="clock" class="h-3 w-3"></i> <span class="uppercase tracking-wide">Requested</span>: ${esc(r.requested_at)}</span>` : ''}
                                    ${r.responded_at ? `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"><i data-lucide="calendar-check" class="h-3 w-3"></i> <span class="uppercase tracking-wide">Responded</span>: ${esc(r.responded_at)}</span>` : ''}
                                    ${r.responder ? `<span class="upc-trigger inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700" data-user-card data-user-name="${esc(r.responder)}" title="View profile"><i data-lucide="user-check" class="h-3 w-3"></i> <span class="uppercase tracking-wide">Responded by</span>: ${esc(r.responder)}</span>` : ''}
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${r.is_dfr ? 'bg-gray-200 text-gray-600' : (r.is_blind ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700')}">
                                        <i data-lucide="${r.is_dfr ? 'file-text' : (r.is_blind ? 'eye-off' : 'folder-search')}" class="h-2.5 w-2.5"></i> ${esc(r.request_type)}</span>
                                    ${done}
                                </div>
                            </li>`;
                        }).join('')}
                    </ul>`;
            if (window.lucide) window.lucide.createIcons();
        }

        function reloadPanels() { loadScbFeedback(); loadLog(); }

        // ── Export (CSV / PDF) — Instrument-Capture-style consolidated report ──
        // Exports exactly what's on screen: the current status chip + date range +
        // search box, applied to the loaded File Search History rows.
        const EXPORT_COLS = [
            { key: 'sn',                label: 'S/N',           pdfWidth: 10 },
            { key: 'request_no',        label: 'Request No',    pdfWidth: 26 },
            { key: 'file_number',       label: 'File No',       pdfWidth: 28 },
            { key: 'file_title',        label: 'Title',         pdfWidth: 'auto' },
            { key: 'request_type',      label: 'Type',          pdfWidth: 22 },
            { key: 'scb_response',      label: 'Response',      pdfWidth: 20 },
            { key: 'requested_by',      label: 'Sent By',       pdfWidth: 28 },
            { key: 'receiving_officer', label: 'Requester',     pdfWidth: 28 },
            { key: 'current_location',  label: 'File Location',  pdfWidth: 28 },
            { key: 'requested_at',      label: 'Requested',     pdfWidth: 26 },
            { key: 'responded_at',      label: 'Responded',     pdfWidth: 26 },
        ];

        // Build flat export rows from the currently filtered history.
        function exportRows() {
            return logRows.filter(r => rowMatches(r, logQuery)).map((r, i) => {
                const isMissing = r.not_found && r.is_blind;
                return {
                    sn:                i + 1,
                    request_no:        r.request_no || '',
                    file_number:       r.file_number || '',
                    file_title:        r.file_title || '',
                    request_type:      r.request_type || '',
                    scb_response:      isMissing ? 'Missing' : (r.scb_response || ''),
                    requested_by:      r.requested_by || '',
                    receiving_officer: r.receiving_officer || '',
                    current_location:  r.current_location || r.location_type || '',
                    requested_at:      r.requested_at || '',
                    responded_at:      r.responded_at || '',
                };
            });
        }

        // Human label for the active filter, used in filenames + the PDF subtitle.
        function exportScopeLabel() {
            const map = { FOUND:'Found', NOT_FOUND:'Not Found', MISSING:'Missing', BLIND:'Blind-Open', PENDING:'Awaiting' };
            return map[logStatus] || 'All';
        }
        function exportRangeLabel() {
            if (logFrom && logTo) return `${logFrom} to ${logTo}`;
            if (logFrom) return `From ${logFrom}`;
            if (logTo)   return `To ${logTo}`;
            return '';
        }
        function csvCell(v) { return `"${String(v ?? '').replace(/"/g, '""')}"`; }

        function exportHistoryCsv() {
            const rows = exportRows();
            if (!rows.length) { Swal.fire('No Data', 'There is nothing to export in the current view.', 'warning'); return; }
            const lines = [
                EXPORT_COLS.map(c => c.label).join(','),
                ...rows.map(r => EXPORT_COLS.map(c => csvCell(r[c.key])).join(',')),
            ];
            const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `File_Search_Report_${exportScopeLabel()}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
        }

        function loadImg(url) {
            return fetch(url).then(r => r.ok ? r.blob() : Promise.reject())
                .then(b => new Promise(res => { const fr = new FileReader(); fr.onloadend = () => res(fr.result); fr.onerror = () => res(null); fr.readAsDataURL(b); }))
                .catch(() => null);
        }

        function exportHistoryPdf() {
            const rows = exportRows();
            if (!rows.length) { Swal.fire('No Data', 'There is nothing to export in the current view.', 'warning'); return; }
            if (!window.jspdf || !window.jspdf.jsPDF) { Swal.fire('Error', 'PDF engine not loaded. Please refresh and try again.', 'error'); return; }

            Promise.all([
                loadImg('/assets/logo/ministry1.jpg'),
                loadImg('/assets/logo/ministry2.jpeg'),
                loadImg('/assets/logo/Nigerian-Coat-of-Arms.png'),
            ]).then(([leftLogo, rightLogo, watermark]) => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
                const pageW = doc.internal.pageSize.getWidth();
                const pageH = doc.internal.pageSize.getHeight();
                const center = pageW / 2;
                const logo = 22;

                function header() {
                    if (leftLogo)  doc.addImage(leftLogo, 'JPEG', 10, 8, logo, logo);
                    if (rightLogo) doc.addImage(rightLogo, 'JPEG', pageW - 10 - logo, 8, logo, logo);
                    doc.setFont('helvetica', 'bold'); doc.setTextColor(0, 0, 0);
                    doc.setFontSize(16); doc.text('KANO STATE GOVERNMENT', center, 14, { align: 'center' });
                    doc.setFontSize(12); doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', center, 20, { align: 'center' });
                    doc.setFontSize(11); doc.text('FILE SEARCH REPORT', center, 26, { align: 'center' });
                    doc.setLineWidth(0.5); doc.line(10, 32, pageW - 10, 32);
                }
                function watermarkDraw() {
                    if (!watermark) return;
                    try {
                        const s = 120, x = (pageW - s) / 2, y = (pageH - s) / 2;
                        if (typeof doc.GState === 'function') { doc.setGState(new doc.GState({ opacity: 0.08 })); doc.addImage(watermark, 'PNG', x, y, s, s); doc.setGState(new doc.GState({ opacity: 1 })); }
                        else doc.addImage(watermark, 'PNG', x, y, s, s);
                    } catch (e) {}
                }

                const scope = exportScopeLabel();
                const range = exportRangeLabel();

                const columnStyles = {};
                EXPORT_COLS.forEach((c, i) => { if (c.pdfWidth) columnStyles[i] = { cellWidth: c.pdfWidth }; });

                doc.autoTable({
                    head: [EXPORT_COLS.map(c => c.label)],
                    body: rows.map(r => EXPORT_COLS.map(c => r[c.key] ?? '')),
                    startY: 52,
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 1.5, overflow: 'linebreak', valign: 'middle' },
                    headStyles: { fillColor: [79, 70, 229], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                    columnStyles,
                    margin: { top: 52, left: 10, right: 10 },
                    didDrawPage: function () {
                        header();
                        doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(0, 0, 0);
                        doc.text(`File Search History — ${scope}${range ? ' (' + range + ')' : ''}`, 14, 39);
                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(8); doc.setTextColor(120, 120, 120);
                        doc.text('Generated on: ' + new Date().toLocaleString(), pageW - 10, 45, { align: 'right' });
                        watermarkDraw();
                    },
                });

                const total = doc.internal.getNumberOfPages();
                for (let p = 1; p <= total; p++) {
                    doc.setPage(p);
                    doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.setTextColor(120, 120, 120);
                    doc.text(`Page ${p} of ${total}`, pageW - 10, pageH - 5, { align: 'right' });
                }

                doc.save(`File_Search_Report_${scope}_${new Date().toISOString().split('T')[0]}.pdf`);
            }).catch(e => Swal.fire('Error', 'Failed to generate PDF: ' + (e && e.message ? e.message : e), 'error'));
        }

        btn.addEventListener('click', search);
        if (pickBtn) pickBtn.addEventListener('click', pickFileNumber);
        input.addEventListener('click', pickFileNumber);   // read-only: open picker instead of typing
        document.getElementById('qs-fb-refresh').addEventListener('click', loadLog);

        // Date-range filter for the report + history.
        const repFrom  = document.getElementById('qs-rep-from');
        const repTo    = document.getElementById('qs-rep-to');
        const repClear = document.getElementById('qs-rep-clear');
        function syncDateRange() {
            logFrom = repFrom.value || '';
            logTo   = repTo.value || '';
            repClear.classList.toggle('hidden', !(logFrom || logTo));
            loadLog();
        }
        repFrom.addEventListener('change', syncDateRange);
        repTo.addEventListener('change', syncDateRange);
        repClear.addEventListener('click', () => {
            repFrom.value = ''; repTo.value = '';
            syncDateRange();
        });

        document.getElementById('qs-export-csv').addEventListener('click', exportHistoryCsv);
        document.getElementById('qs-export-pdf').addEventListener('click', exportHistoryPdf);
        document.getElementById('qs-tbl-refresh').addEventListener('click', loadScbFeedback);

        // Live, client-side filtering of the already-loaded rows.
        document.getElementById('qs-tbl-search').addEventListener('input', (e) => {
            scbQuery = e.target.value.trim();
            scbPage = 1;
            renderScb();
        });
        // Instant client-side filter over the already-loaded rows, plus a debounced
        // server refetch so matches beyond the 100 most-recent rows are also found.
        let logSearchTimer = null;
        document.getElementById('qs-log-search').addEventListener('input', (e) => {
            logQuery = e.target.value.trim();
            renderLog(logRows.filter(r => rowMatches(r, logQuery)));
            clearTimeout(logSearchTimer);
            logSearchTimer = setTimeout(loadLog, 350);
        });
        reloadPanels();
    })();
    </script>


@endpush
@endsection

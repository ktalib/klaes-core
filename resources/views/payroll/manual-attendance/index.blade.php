@extends('layouts.app')

@section('page-title')
    Manual Attendance Capture
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manual-attendance.css') }}">
    @if(!empty($isEmbedded))
        <style>
            .sidebar { display: none !important; }
            body { overflow: auto; }
        </style>
    @endif
@endpush

@push('scripts')
    <script src="{{ asset('js/manual-attendance.js') }}" defer></script>
@endpush

@section('content')
    @php $embeddedMode = !empty($isEmbedded); @endphp
    <div class="flex-1 overflow-auto" id="manual-attendance-app"
         data-employees-url="{{ route('manual-attendance.employees') }}"
            data-datatable-url="{{ route('manual-attendance.datatable') }}"
         data-store-url="{{ route('manual-attendance.entries.store') }}"
         data-fallback-avatar="{{ asset('storage/upload/profile/avatar.png') }}"
         data-embedded="{{ $embeddedMode ? 'true' : 'false' }}">
        @unless($embeddedMode)
            @include('admin.header')
        @endunless

        <div class="container mx-auto py-6 space-y-6">
            <div class="bg-white border card-shadow rounded-lg p-6 space-y-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Manual Attendance Capture</h1>
                        <p class="text-sm text-slate-600">Mark staff as present or absent. Check-in time is auto-captured when marked present.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 justify-end">
                        @if($embeddedMode)
                            <a href="{{ route('manual-attendance.index') }}" class="inline-flex items-center rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                                <i class="fas fa-arrow-left mr-2"></i> Back to main view
                            </a>
                        @else
                            <a href="{{ route('manual-attendance.index', ['url' => 'frontdesks']) }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                <i class="fas fa-external-link-alt mr-2"></i> Launch front desk mode
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700" for="attendance-date">Attendance Date</label>
                        <input type="date" id="attendance-date" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ $defaultDate }}">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700" for="attendance-shift">Assigned Shift</label>
                        <div class="relative">
                            <i class="fas fa-business-time absolute left-3 top-3 text-slate-400"></i>
                            <select id="attendance-shift" class="w-full rounded-md border border-slate-300 px-3 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">All shifts</option>
                                @foreach($shiftOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700" for="attendance-work-station">Work Station</label>
                        <div class="relative">
                            <i class="fas fa-warehouse absolute left-3 top-3 text-slate-400"></i>
                            <select id="attendance-work-station" class="w-full rounded-md border border-slate-300 px-3 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">All work stations</option>
                                @foreach($workStations as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700" for="attendance-search">Search Employee</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-3 text-slate-400"></i>
                                <input type="text" id="attendance-search" class="w-full rounded-md border border-slate-300 px-3 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Search by name or email">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border card-shadow rounded-lg">
                <div class="border-b px-6 py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Staff Attendance</h2>
                        <p class="text-sm text-slate-600">Choose list or grid view to capture attendance efficiently.</p>
                    </div>
                    @if($embeddedMode)
                        <div class="flex flex-wrap items-center gap-3 justify-end">
                            <span class="text-sm text-slate-500" id="manual-attendance-count">0 staff</span>
                            <button class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" id="manual-attendance-refresh">
                                <i class="fas fa-sync mr-2"></i> Refresh
                            </button>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-3 justify-end">
                            <div class="inline-flex rounded-md border border-slate-200 overflow-hidden" role="group">
                                <button type="button" class="manual-attendance-view-btn px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 active" data-view="list">List view</button>
                                <button type="button" class="manual-attendance-view-btn px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50" data-view="grid">Card view</button>
                            </div>
                            <span class="text-sm text-slate-500" id="manual-attendance-count">0 staff</span>
                            <span class="text-sm font-medium text-emerald-600" id="manual-attendance-selected-count">0 selected</span>
                            <button class="inline-flex items-center rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" id="manual-attendance-select-all" disabled>
                                <i class="fas fa-check-double mr-2"></i> Select All
                            </button>
                            <button class="inline-flex items-center rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" id="manual-attendance-clear-selection" disabled>
                                <i class="fas fa-times mr-2"></i> Clear Selection
                            </button>
                            <button class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300" id="manual-attendance-mark-selected" disabled>
                                <i class="fas fa-user-check mr-2"></i> Mark Selected Present
                            </button>
                            <button class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" id="manual-attendance-refresh">
                                <i class="fas fa-sync mr-2"></i> Refresh
                            </button>
                        </div>
                    @endif
                </div>
                <div class="p-6 space-y-6">
                    <div id="manual-attendance-list" class="{{ $embeddedMode ? 'hidden' : 'block' }}">
                        @include('payroll.manual-attendance.partials.list-view', ['embedded' => $embeddedMode])
                    </div>
                    <div id="manual-attendance-grid" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 {{ $embeddedMode ? '' : 'hidden' }}">
                        <div class="text-center py-12 text-slate-500 col-span-full">
                            <i class="fas fa-hourglass-half mr-2"></i> Select filters to load staff.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="file" id="manual-attendance-upload-input" accept=".xlsx,.xls,.csv" class="hidden">

        @include('admin.footer')
    </div>
@endsection

@extends('layouts.app')
@section('page-title')
    {{ __('Create File Tracker') }}
@endsection
@section('content')
 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="{{ asset('css/create-file-tracker.css') }}">
 
     <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
          
          <div class="container mx-auto py-6 space-y-6">

 

        <div id="page-toast-container" class="fixed inset-x-0 top-24 z-50 flex flex-col items-center gap-2 pointer-events-none"></div>
        
        <!-- Page Header -->
        <header class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                {{-- <div>
                    <h1 class="text-2xl font-bold text-gray-900">Create File Tracker</h1>
                    <p class="text-gray-600 mt-1">Register a new file for tracking with office logging system</p>
                </div> --}}
                <div class="flex items-center gap-2">
                    <button id="resetBtn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                        Reset Form
                    </button>
                    <button id="previewBtn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i data-lucide="eye" class="h-4 w-4 mr-2"></i>
                        Preview
                    </button>
                </div>
            </div>
        </header>

        <div class="flex flex-1 p-6">
            <!-- Two-Tab System for Create File Tracker -->
            <div class="w-full">
                <!-- Tab Navigation -->
                <div class="bg-white rounded-t-lg border border-gray-200">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                            <button id="tab-create" class="main-tab active border-b-2 border-blue-500 py-4 px-1 text-sm font-medium text-blue-600" data-tab="create">
                                <i data-lucide="file-plus" class="h-4 w-4 inline mr-2"></i>
                                Create File Tracker
                            </button>
                            <button id="tab-logs" class="main-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="logs">
                                <i data-lucide="database" class="h-4 w-4 inline mr-2"></i>
                                File Tracker Log
                                <span id="log-count" class="ml-2 bg-blue-100 text-blue-800 py-1 px-2 rounded-full text-xs">0</span>
                            </button>
                        </nav>
                    </div>
                </div>
                
                <!-- Tab Content -->
                <div class="bg-white rounded-b-lg border-l border-r border-b border-gray-200 shadow-sm">
                    <!-- Create File Tracker Tab -->
                    <div id="content-create" class="main-tab-content active p-6">
                        <div class="grid grid-cols-1 xl:grid-cols-[1fr,320px] gap-6">
                            <!-- Main Form -->
                            <div class="space-y-6">
                                <!-- File Details Card -->
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                    <div class="px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                            <i data-lucide="file-text" class="h-5 w-5"></i>
                                            File Details
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">Enter the basic file information</p>
                                    </div>
                                    <div class="p-6 space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <label for="file-no" class="block text-sm font-medium text-gray-700">File No *</label>
                                                <div class="relative">
                                                    <input type="text" id="file-no" name="file_number" placeholder="e.g. RES-2015-4859" class="block w-full px-3 py-2 pr-12 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <button type="button" id="fileno-selector-btn" class="absolute inset-y-0 right-0 flex items-center px-3 border-l border-gray-300 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-colors rounded-r-md" title="Select from existing file numbers">
                                                        <i data-lucide="search" class="h-4 w-4"></i>
                                                    </button>
                                                </div>
                                                <p class="text-xs text-gray-500">Enter the unique file number or click <i data-lucide="search" class="h-3 w-3 inline"></i> to search</p>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="tracking-id" class="block text-sm font-medium text-gray-700">File Tracking ID</label>
                                                <input type="text" id="tracking-id" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm">
                                                <p class="text-xs text-gray-500">Auto-fetched from File Indexing after selecting a file number</p>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="file-name" class="block text-sm font-medium text-gray-700">File Name *</label>
                                            <input type="text" id="file-name" placeholder="e.g. Alhaji Ibrahim Dantata" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <p class="text-xs text-gray-500">Auto-filled from File Indexing; reset the form to unlock if a change is required</p>
                                        </div>
                                        <!-- Priority Selection -->
                                        <div class="space-y-2">
                                            <label for="file-priority" class="block text-sm font-medium text-gray-700">File Priority</label>
                                            <select id="file-priority" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                <option value="LOW">Low Priority</option>
                                                <option value="MEDIUM" selected>Medium Priority</option>
                                                <option value="HIGH">High Priority</option>
                                            </select>
                                            <p class="text-xs text-gray-500">Set the priority level for this file</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Office Details Card -->
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                    <div class="px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                            <i data-lucide="building" class="h-5 w-5"></i>
                                            Office Details
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">Select the current office location</p>
                                    </div>
                                    <div class="p-6 space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <label for="origin-office" class="block text-sm font-medium text-gray-700">Registry (Origin)</label>
                                                <select id="origin-office" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Select Registry (Origin)</option>
                                                </select>
                                                <p class="text-xs text-gray-500">Defaults to the Registry where files are dispatched from</p>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="current-office" class="block text-sm font-medium text-gray-700">Destination Office (Login) *</label>
                                                <select id="current-office" name="receiving_office_name" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Select destination</option>
                                                </select>
                                                <p class="text-xs text-gray-500">Select the office that is receiving the file</p>
                                            </div>
                                            <div id="custom-destination-office-wrapper" class="space-y-2 hidden">
                                                <label for="custom-destination-office-name" class="block text-sm font-medium text-gray-700">Enter Destination Office Name *</label>
                                                <input type="text" id="custom-destination-office-name" class="block w-full px-3 py-2 border border-blue-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. Special Projects Desk" disabled>
                                                <p class="text-xs text-gray-500">This value is saved to the same backend column as the dropdown.</p>
                                            </div>
                                            <div class="space-y-2 hidden" aria-hidden="true">
                                                <label for="origin-office-id" class="sr-only">Origin Office ID</label>
                                                <input type="text" id="origin-office-id" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm">
                                                <p class="text-xs text-gray-500">Auto-populated from the selected registry</p>
                                            </div>
                                            <div class="space-y-2 hidden" aria-hidden="true">
                                                <label for="current-office-id" class="sr-only">Destination Office ID</label>
                                                <input type="text" id="current-office-id" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm">
                                                <p class="text-xs text-gray-500">Auto-populated from destination selection</p>
                                            </div>
                                            <div class="space-y-2 col-span-2">
                                                <label for="receiving-officer" class="block text-sm font-medium text-gray-700">Receiving Officer *</label>
                                                <select id="receiving-officer" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Select receiving officer</option>
                                                </select>
                                                <p id="receiving-officer-hint" class="text-xs text-gray-500">Directory loading...</p>
                                            </div>
                                        </div>
                                        <!-- Notes/Remarks Field -->
                                        <div class="space-y-2">
                                            <label for="office-notes" class="block text-sm font-medium text-gray-700">Notes/Remarks</label>
                                            <textarea id="office-notes" rows="3" placeholder="Reason why the file is in this office..." class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                                            <p class="text-xs text-gray-500">Enter the reason for the file being in this office</p>
                                        </div>
                                        <div id="office-info" class="hidden p-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span id="office-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"></span>
                                                <span id="office-code-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"></span>
                                            </div>
                                            <p id="office-department" class="text-sm text-gray-600"></p>
                                        </div>
                                        <div id="origin-office-info" class="hidden p-3 mt-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span id="origin-office-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"></span>
                                                <span id="origin-office-code-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"></span>
                                            </div>
                                                <p id="origin-office-department" class="text-sm text-gray-600"></p>
                                        </div>
                                        <div id="receiving-officer-info" class="hidden p-3 mt-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span id="receiving-officer-name" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"></span>
                                                <span id="receiving-officer-level" class="hidden inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"></span>
                                            </div>
                                            <p id="receiving-officer-department" class="text-sm text-gray-600"></p>
                                            <p id="receiving-officer-roles" class="text-xs text-gray-500 mt-1"></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Log Session Card -->
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                    <div class="px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                            <i data-lucide="hash" class="h-5 w-5"></i>
                                            File Log Session
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">Initial log entry details (auto-populated)</p>
                                    </div>
                                    <div class="p-6 space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <label for="log-id" class="block text-sm font-medium text-gray-700">Log ID</label>
                                                <input type="text" id="log-id" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm">
                                                <p class="text-xs text-gray-500">Auto-generated log identifier</p>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="log-in-time" class="block text-sm font-medium text-gray-700">Log In Time</label>
                                                <input type="text" id="log-in-time" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                                <p class="text-xs text-gray-500">Current time (auto-updated)</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <label for="log-in-date" class="block text-sm font-medium text-gray-700">Log In Date</label>
                                                <input type="text" id="log-in-date" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                                <p class="text-xs text-gray-500">Current date (auto-updated)</p>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="log-out-time" class="block text-sm font-medium text-gray-700">Log Out Time</label>
                                                <input type="text" id="log-out-time" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                                <p class="text-xs text-gray-500">Will be set when file is logged out</p>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="log-out-date" class="block text-sm font-medium text-gray-700">Log Out Date</label>
                                            <input type="text" id="log-out-date" readonly class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                            <p class="text-xs text-gray-500">Will be set when file is logged out</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex justify-end gap-2">
                                    <button id="resetFormBtn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Reset
                                    </button>
                                    <button id="saveFileLogBtn" class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 min-w-[150px]">
                                        <i data-lucide="save" class="h-4 w-4 mr-2"></i>
                                        Save File Log
                                    </button>
                                </div>
                            </div>

                            <!-- Sidebar -->
                            <div class="w-80 space-y-6">
                                <!-- Current Date & Time Card -->
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                    <div class="px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                            <i data-lucide="clock" class="h-5 w-5"></i>
                                            Current Date & Time
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">Live timestamp for log entries</p>
                                    </div>
                                    <div class="p-6 space-y-4">
                                        <div class="text-center">
                                            <div id="current-time" class="text-2xl font-mono font-bold">00:00:00</div>
                                            <div id="current-date" class="text-lg text-gray-600">0000-00-00</div>
                                        </div>
                                        <hr class="border-gray-200">
                                        <div class="space-y-3">
                                            <div class="flex flex-col space-y-2">
                                                <span class="text-sm font-medium text-gray-600">File No:</span>
                                                <span id="sidebar-file-no" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 font-mono w-fit">-</span>
                                            </div>
                                            <div class="flex flex-col space-y-2">
                                                <span class="text-sm font-medium text-gray-600">Tracking ID:</span>
                                                <span id="sidebar-tracking-id" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 font-mono w-fit"></span>
                                            </div>
                                            <div class="flex flex-col space-y-2">
                                                <span class="text-sm font-medium text-gray-600">Log ID:</span>
                                                <span id="sidebar-log-id" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 font-mono w-fit"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @include('create_file_tracker_page.partials.quick-actions')
                            </div>
                        </div>
                    </div>
                    <!-- File Tracker Log Tab -->
                    <div id="content-logs" class="main-tab-content hidden p-6 space-y-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="space-y-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="database" class="h-5 w-5 text-blue-600"></i>
                                        <span class="text-sm font-semibold uppercase tracking-wide text-blue-600">Log Intelligence</span>
                                    </div>
                                    <h4 class="mt-1 text-xl font-semibold text-gray-900">All File Tracker Logs</h4>
                                    <p class="text-sm text-gray-600">Search live trackers and legacy archives from a single place.</p>
                                </div>
                                <div class="rounded-lg border border-blue-100 bg-blue-50/70 p-4 text-sm text-blue-800">
                                    <div class="flex items-start gap-3">
                                        <i data-lucide="scan" class="mt-0.5 h-4 w-4"></i>
                                        <div class="space-y-1">
                                            <p class="font-medium">Scanning a tracking ID?</p>
                                            <p>Paste or scan the code into the search box. If no active tracker exists we will look in the archive (`fileNumber`) table and let you prefill a new tracker automatically.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="tracker-card-legend mt-3">
                                    <div class="tracker-card-legend__item">
                                        <span class="tracker-card-legend__dot tracker-card-legend__dot--owned"></span>
                                        <span>Green cards show files awaiting your acceptance</span>
                                    </div>
                                    <div class="tracker-card-legend__item">
                                        <span class="tracker-card-legend__dot tracker-card-legend__dot--other"></span>
                                        <span>Red cards belong to another handler</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div class="relative w-full sm:w-64">
                                    <i data-lucide="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" id="search-logs" placeholder="Search by tracking ID, file number, name, or notes" class="w-full rounded-md border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                                </div>
                                <div class="flex items-center justify-end gap-2 sm:justify-start">
                                    <button id="refresh-logs" class="inline-flex items-center rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition-colors hover:border-gray-300 hover:bg-gray-50">
                                        <i data-lucide="refresh-cw" class="mr-2 h-4 w-4"></i>
                                        Refresh
                                    </button>
                                    <button id="export-logs" class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">
                                        <i data-lucide="download" class="mr-2 h-4 w-4"></i>
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-lg border border-blue-100 bg-blue-50/70 p-4 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-wide text-blue-600">Total Trackers</div>
                                <div id="total-trackers" class="mt-2 text-2xl font-semibold text-blue-700">0</div>
                                <p class="text-xs text-blue-700/80">All trackers captured across the system</p>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50/70 p-4 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-wide text-amber-600">Active Movements</div>
                                <div id="active-trackers" class="mt-2 text-2xl font-semibold text-amber-700">0</div>
                                <p class="text-xs text-amber-700/80">Files currently logged into an office</p>
                            </div>
                            <div class="rounded-lg border border-red-100 bg-red-50/70 p-4 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-wide text-red-600">High Priority</div>
                                <div id="high-priority" class="mt-2 text-2xl font-semibold text-red-700">0</div>
                                <p class="text-xs text-red-700/80">Trackers flagged as mission critical</p>
                            </div>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50/70 p-4 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Total Movements</div>
                                <div id="total-movements" class="mt-2 text-2xl font-semibold text-emerald-700">0</div>
                                <p class="text-xs text-emerald-700/80">Total office handovers recorded</p>
                            </div>
                        </div>

                        <div id="trackers-container" class="space-y-6">
                            <!-- File trackers will be dynamically injected here -->
                        </div>
                    </div>
    </div>
</div>

    <!-- Preview Dialog -->
    <div id="preview-dialog" class="dialog-overlay">
        <div class="dialog-content max-w-2xl">
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">File Tracker Preview</h2>
                    <p class="text-sm text-gray-600 mt-1">Review all details before saving</p>
                </div>
                <button id="close-preview" class="text-gray-400 hover:text-gray-600 p-1">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
            <div id="preview-content" class="py-6">
                <!-- Preview content will be dynamically generated -->
            </div>
            <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                <button id="close-preview-btn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button id="save-tracker-btn" class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                    <i data-lucide="save" class="h-4 w-4 mr-2"></i>
                    Save File Tracker
                </button>
            </div>
        </div>
    </div>

    <!-- Notes Dialog for Adding New Log Entries -->
    <div id="notes-dialog" class="dialog-overlay">
        <div class="dialog-content max-w-md">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Update Movement</h2>
                    <p class="text-sm text-gray-600 mt-1">Confirm the next office and receiving officer before handing over the file.</p>
                </div>
                <button id="close-notes" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <p class="font-semibold text-blue-900">Destination</p>
                    <p id="movement-destination-label" class="mt-1 text-blue-900">Select a destination office to continue.</p>
                    <p class="mt-2 text-xs text-blue-700">Transfers only unlock after you have accepted the file. We'll block further transfers once it leaves your office.</p>
                </div>
                <div class="space-y-2">
                    <label for="movement-receiving-officer" class="block text-sm font-medium text-gray-700">New receiving officer *</label>
                    <select id="movement-receiving-officer" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select receiving officer</option>
                    </select>
                    <p class="text-xs text-gray-500">This officer will approve the file before it can move again.</p>
                </div>
                <div class="space-y-2">
                    <label for="new-log-notes" class="block text-sm font-medium text-gray-700">Transfer notes</label>
                    <textarea id="new-log-notes" rows="4" placeholder="Reason the file is heading to this office..." class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    <p class="text-xs text-gray-500">These notes are shared with the next officer.</p>
                </div>
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
                    <label class="flex items-start gap-2 text-sm text-amber-800">
                        <input type="checkbox" id="movement-override-toggle" class="mt-1 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                        <span>
                            Receiver is beside me — mark this file as accepted immediately.
                            <span id="movement-override-help" class="block text-xs text-amber-700 mt-1">Only use this when the receiving officer physically confirms the handover in person.</span>
                        </span>
                    </label>
                </div>
                <div id="movement-error" class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button id="cancel-notes" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button id="confirm-notes" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    Log movement
                </button>
            </div>
        </div>
    </div>

    <!-- Update Log Dialog -->
    <div id="update-log-dialog" class="dialog-overlay">
        <div class="dialog-content max-w-xl">
            <div class="flex items-start justify-between pb-4 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Correct Log Entry</h2>
                    <p class="text-sm text-gray-600 mt-1">Fix the receiving officer or office for this movement.</p>
                </div>
                <button id="update-log-close" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <div class="space-y-4 py-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    <p class="font-semibold text-gray-800">
                        File <span id="update-log-file-label" class="font-mono text-blue-600"></span>
                    </p>
                    <p class="mt-1">
                        Log Entry <span id="update-log-entry-label" class="font-mono text-gray-700"></span>
                    </p>
                </div>

                <div class="space-y-2">
                    <label for="update-log-office" class="block text-sm font-medium text-gray-700">Current Office *</label>
                    <select id="update-log-office" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                        <option value="">Select office</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="update-log-receiving-officer" class="block text-sm font-medium text-gray-700">Receiving Officer *</label>
                    <select id="update-log-receiving-officer" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                        <option value="">Select receiving officer</option>
                    </select>
                </div>

                <div id="update-log-error" class="hidden rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"></div>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
                <button id="update-log-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button id="update-log-save" class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md shadow-sm hover:bg-blue-700">
                    <i data-lucide="save" class="mr-2 h-4 w-4"></i>
                    Update Log
                </button>
            </div>
        </div>
    </div>

    <!-- Create Receiving Officer Dialog -->
    <div id="new-officer-dialog" class="dialog-overlay">
        <div class="dialog-content max-w-md">
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Add Receiving Officer</h2>
                    <p class="text-sm text-gray-600 mt-1">Create the officer record without leaving this page.</p>
                </div>
                <button id="new-officer-close" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
            <form id="new-officer-form" class="space-y-4 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label for="new-officer-first-name" class="block text-sm font-medium text-gray-700">First Name *</label>
                        <input type="text" id="new-officer-first-name" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40" placeholder="e.g. Aisha">
                    </div>
                    <div class="space-y-2">
                        <label for="new-officer-last-name" class="block text-sm font-medium text-gray-700">Last Name *</label>
                        <input type="text" id="new-officer-last-name" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40" placeholder="e.g. Musa">
                    </div>
                </div>
                <div class="space-y-2">
                    <label for="new-officer-username" class="block text-sm font-medium text-gray-700">Username (auto)</label>
                    <input type="text" id="new-officer-username" class="block w-full rounded-md border border-gray-300 px-3 py-2 bg-gray-50 font-mono text-sm" readonly>
                    <p class="text-xs text-gray-500">Password defaults to <span class="font-semibold text-gray-700">password</span>. Share this with the new officer.</p>
                </div>
                <div id="new-officer-error" class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-200 pt-4">
                    <button type="button" id="new-officer-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="new-officer-submit" class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md shadow-sm hover:bg-blue-700">
                        <i data-lucide="user-plus" class="mr-2 h-4 w-4"></i>
                        Create Officer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assignment Workflow Dialog -->
    <div id="assignment-dialog" class="dialog-overlay">
        <div class="dialog-content max-w-3xl">
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Complete Logout &amp; Assign</h2>
                    <p class="text-sm text-gray-600 mt-1">Finalize the handover and notify the next handler immediately.</p>
                </div>
                <button id="assignment-close" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <div id="assignment-status" class="hidden mt-4 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700"></div>

            <div id="assignment-loading" class="py-12 text-center text-gray-600">
                <div class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600"></div>
                <p class="text-sm font-medium">Loading assignment directory...</p>
            </div>

            <div id="assignment-form" class="hidden space-y-6">
                <div id="assignment-summary" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700"></div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="assignment-office" class="text-sm font-medium text-gray-700">Forward to Office *</label>
                        <select id="assignment-office" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                            <option value="">Select office</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label for="assignment-user" class="text-sm font-medium text-gray-700">Assign to Officer *</label>
                        <select id="assignment-user" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                            <option value="">Select officer</option>
                        </select>
                        <p id="assignment-user-hint" class="text-xs text-gray-500">Type to search by name or department.</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="assignment-note" class="text-sm font-medium text-gray-700">Instructions for Recipient</label>
                    <textarea id="assignment-note" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40" placeholder="Highlight next steps or special requirements..."></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" id="assignment-email" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Send email notification
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" id="assignment-sms" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Send SMS notification
                    </label>
                </div>

                <div id="assignment-alert" class="hidden rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
                <button id="assignment-cancel" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button id="assignment-skip" type="button" class="px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Complete without assignment
                </button>
                <button id="assignment-submit" type="button" class="inline-flex items-center px-6 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <i data-lucide="send" class="mr-2 h-4 w-4"></i>
                    Complete &amp; Assign
                </button>
            </div>
        </div>
    </div>

    <!-- View Details Dialog -->
    <div id="details-dialog" class="dialog-overlay">
        <div class="dialog-content details-dialog">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">File Tracker Details</h2>
                <button id="close-details" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
            <div id="details-content">
                <!-- Details content will be dynamically generated -->
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button id="close-details-btn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Close
                </button>
                <button id="print-details-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                    Print Details
                </button>
            </div>
        </div>
    </div> 
  <style>
/* Tab display fixes */
.main-tab-content {
    display: none !important;
}

.main-tab-content.active {
    display: block !important;
}

.main-tab-content.hidden {
    display: none !important;
}

/* Preview Dialog Styling */
.dialog-overlay {
    background: rgba(0, 0, 0, 0.5) !important;
}

.dialog-content {
    background: white !important;
    border-radius: 12px !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
}

/* Priority Badge Styling */
.priority-LOW {
    background-color: #dcfce7 !important;
    color: #166534 !important;
    border: 1px solid #bbf7d0 !important;
}

.priority-MEDIUM {
    background-color: #fef3c7 !important;
    color: #92400e !important;
    border: 1px solid #fde68a !important;
}

.priority-HIGH {
    background-color: #fee2e2 !important;
    color: #991b1b !important;
    border: 1px solid #fecaca !important;
}

/* Custom Select2 styling for batch selection */
.select2-container--default .select2-selection--single {
    height: 42px !important;
    border: 1px solid #d1d5db !important;
    border-radius: 0.375rem !important;
    padding: 0 12px !important;
    display: flex !important;
    align-items: center !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #374151 !important;
    line-height: 42px !important;
    padding-left: 0 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
    right: 8px !important;
}

.select2-dropdown {
    border: 1px solid #d1d5db !important;
    border-radius: 0.375rem !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #3b82f6 !important;
    color: white !important;
}

.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
}

.tracker-highlight {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2) !important;
    animation: trackerHighlightPulse 1.4s ease-in-out 2;
}

@keyframes trackerHighlightPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.45);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(37, 99, 235, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
    }
}

.cofo-status-content {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.cofo-status-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    font-size: 0.75rem;
}

.loading-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(37, 99, 235, 0.3);
    border-top-color: rgba(37, 99, 235, 0.9);
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

.autofill-locked {
    background-color: #fdf2f8 !important;
    color: #b91c1c !important;
    border-color: #f87171 !important;
    cursor: not-allowed !important;
}

.autofill-locked::placeholder {
    color: #fca5a5 !important;
}

select.autofill-locked {
    pointer-events: none !important;
}

input.autofill-locked:focus,
select.autofill-locked:focus,
textarea.autofill-locked:focus {
    outline: none !important;
    box-shadow: none !important;
}

/* Shelf location input styling */
#shelf-location {
    transition: all 0.2s ease-in-out;
}

#shelf-location:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Loading state for shelf input */
#shelf-location.loading {
    background-image: url("data:image/svg+xml,%3csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M10 3V6M10 14V17M17 10H14M6 10H3M15.364 4.636L13.536 6.464M6.464 13.536L4.636 15.364M15.364 15.364L13.536 13.536M6.464 6.464L4.636 4.636' stroke='%236b7280' stroke-width='2' stroke-linecap='round'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 12px center;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Success state styling */
.success-border {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}

/* Error state styling */
.error-border {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

/* Select2 loading and result states */
.select2-results__message {
    color: #6b7280 !important;
    font-style: italic !important;
    padding: 8px 12px !important;
}

.select2-results__option.loading-results {
    color: #6b7280 !important;
    font-style: italic !important;
}

/* Batch selection feedback */
.batch-selection-feedback {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #6b7280;
}

.batch-selection-feedback.success {
    color: #10b981;
}

.batch-selection-feedback.error {
    color: #ef4444;
}
</style>

 
@include('components.global-fileno-modal')
 
    <!-- Core JS dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/fileindexing/create-indexing-dialog.js') }}"></script>
    
        <!-- Footer -->
        @include('admin.footer')
    
  
    <!-- Add Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    @php
        $authUser = auth()->user();
        $canAssignFiles = $authUser && (
            $authUser->type === 'super admin'
            || $authUser->can('manage file tracker')
            || $authUser->can('assign file tracker')
        );

        $currentUserPayload = [
            'id' => $authUser?->id,
            'name' => $authUser?->name,
            'type' => $authUser?->type,
            'department_id' => $authUser?->department_id,
        ];

        $assignmentPermissionsPayload = [
            'canAssign' => $canAssignFiles,
        ];
    @endphp
    <script>
        window.currentUser = @json($currentUserPayload);
        window.assignmentPermissions = @json($assignmentPermissionsPayload);
    </script>
    
    @include('create_file_tracker_page.partials.quick-actions-js')
    @include('create_file_tracker_page.partials.js')
    @include('create_file_tracker_page.partials.assignment-workflow-js')
    </div>
@endsection

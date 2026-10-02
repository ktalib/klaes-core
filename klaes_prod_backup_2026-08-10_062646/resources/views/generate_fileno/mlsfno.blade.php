@extends('layouts.app')
@section('page-title')
    {{ __('MLS File Number Generator') }}
@endsection
 
@section('content') 
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <style>
            .select2-container .select2-selection--single {
                height: 42px !important;
                display: flex;
                align-items: center;  
                border-color: #d1d5db !important;
            }
            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 40px !important;
            }
        </style>
    @endpush
    <style>
        /* Action Dropdown Styles */
        .action-dropdown {
            position: relative;
            display: inline-block;
        }


       
        /* Menu is opened by the click handler in mls_js.blade.php, which promotes it
           to position:fixed and writes inline left/top. Never open it on :hover --
           that rule outranks .hidden and re-opens closed menus at stale coordinates
           (and fires on tap on touch devices, fighting the click handler). */
        .action-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 9999;
            padding: 0.5rem 0;
            margin-top: 2px;
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            text-align: left;

            /* Responsive sizing: never wider than the viewport, never taller than the
               space available -- scroll internally instead of overflowing off-screen. */
            width: max-content;
            min-width: 14rem;
            max-width: calc(100vw - 20px);
            max-height: calc(100vh - 20px);
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .action-dropdown-menu.show {
            display: block;
        }

        .action-dropdown-menu.hidden {
            display: none;
        }

        /* Menu items must stay on one line and keep a comfortable tap target */
        .action-dropdown-menu button {
            white-space: nowrap;
            min-height: 44px;
        }

        /* Ensure table doesn't clip dropdown */
        #mlsfTable, #mlsfTable_wrapper {
            overflow: visible !important;
        }

        .dataTables_scrollBody {
            overflow: visible !important;
        }
    </style>
            <!-- Main Content -->
            <div class="flex-1 overflow-auto">
                <!-- Header -->
                @include('admin.header', [
                    'PageTitle' => 'MLS File Number Generator',
                    'PageDescription' => 'Generate and manage MLS file numbers'])

                <!-- Dashboard Content -->
                <div class="p-6">
                    <div class="container mx-auto py-6 space-y-6">

                        <!-- Tab Navigation -->
                        <div class="border-b-2 border-gray-200 mb-6">
                            <nav class="flex space-x-1" aria-label="Tabs">
                                <button 
                                    id="tab-generator" 
                                    onclick="switchTab('generator')"
                                    class="tab-button group relative py-3 px-6 text-sm font-semibold rounded-t-lg transition-all duration-200">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                        <span>MLS File Number Generator</span>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-blue-600 rounded-t-sm transition-all duration-200"></div>
                                </button>
                                @if(Auth::user()->assign_role == 'Supper Admin')
                                <button 
                                    id="tab-serial-init" 
                                    onclick="switchTab('serial-init')"
                                    class="tab-button group relative py-3 px-6 text-sm font-semibold rounded-t-lg transition-all duration-200">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="settings" class="w-5 h-5"></i>
                                        <span>Serial Initialization</span>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-transparent rounded-t-sm transition-all duration-200"></div>
                                </button>
                                @endif
                                <button 
                                    id="tab-consolidation" 
                                    onclick="switchTab('consolidation')"
                                    class="tab-button group relative py-3 px-6 text-sm font-semibold rounded-t-lg transition-all duration-200">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="file-bar-chart" class="w-5 h-5"></i>
                                        <span>Consolidated Report</span>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-transparent rounded-t-sm transition-all duration-200"></div>
                                </button>
                            </nav>
                        </div>

                        <!-- Tab Content: File Generator -->
                        <div id="content-generator" class="tab-content">

                            <!-- Action Buttons -->
                        <div class="flex justify-between items-center mb-6">
                            <div class="flex space-x-4">
                                <button 
                                    onclick="openGeneratorModalMain()"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    <span>Generate New  File Number</span>
                                </button>

                                <button
                                    onclick="openBatchPrintModal()"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                    <span>Print Batch Commissioning Sheet</span>
                                </button>

                                 <!-- <button
                                    onclick="openMigrationModal()"
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                                    <i data-lucide="upload" class="w-4 h-4"></i>
                                    <span>Migrate Data</span>
                                </button>   -->
                                <!-- <button 
                                    onclick="testDatabaseConnection()"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                                    <i data-lucide="database" class="w-4 h-4"></i>
                                    <span>Test Database</span>
                                </button> -->
                                <!-- <button 
                                    onclick="debugTableData()"
                                    class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                                    <i data-lucide="bug" class="w-4 h-4"></i>
                                    <span>Debug Data</span>
                                </button> -->
                            </div>

                            <!-- Reservation status indicators -->
                            <div id="reservationIndicator" class="hidden mb-4"></div>
                            <div id="reservationWarning" class="hidden mb-4"></div>
                            <!-- Statistics Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 ">
                                <!-- Total Generated -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center space-x-4">
                                    <div class="p-3 bg-blue-100 rounded-lg text-blue-600">
                                        <i data-lucide="file-check" class="w-8 h-8"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Total Commissioned</p>
                                        <h3 class="text-2xl font-bold text-gray-900" id="stat-total">{{ number_format($totalCount ?? 0) }}</h3>
                                        <p class="text-xs text-green-600 flex items-center mt-1">
                                            <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                            excludes legacy
                                        </p>
                                    </div>
                                </div>

                                <!-- Generated Today -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center space-x-4">
                                    <div class="p-3 bg-green-100 rounded-lg text-green-600">
                                        <i data-lucide="calendar" class="w-8 h-8"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Commissioned Today</p>
                                        <h3 class="text-2xl font-bold text-gray-900" id="stat-today">{{ number_format($todayCount ?? 0) }}</h3>
                                        <p class="text-xs text-gray-400 mt-1">
                                            {{ date('M j, Y') }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Generated This Month -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center space-x-4">
                                    <div class="p-3 bg-purple-100 rounded-lg text-purple-600">
                                        <i data-lucide="bar-chart-2" class="w-8 h-8"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Commissioned This Month</p>
                                        <h3 class="text-2xl font-bold text-gray-900" id="stat-month">{{ number_format($monthCount ?? 0) }}</h3>
                                        <p class="text-xs text-gray-400 mt-1">
                                            {{ date('F Y') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(Auth::user() && Auth::user()->assign_role === 'Supper Admin')
                        <!-- Bulk Actions Bar (visible only when rows are selected) -->
                        <div id="mlsfBulkActionsBar"
                             class="hidden items-center justify-between bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-3 shadow-sm">
                            <div class="flex items-center space-x-2 text-sm text-red-800">
                                <i data-lucide="check-square" class="w-4 h-4"></i>
                                <span><strong id="mlsfSelectedCount">0</strong> record(s) selected</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button"
                                        onclick="clearMlsfSelection()"
                                        class="text-sm text-gray-600 hover:text-gray-800 px-3 py-1.5 rounded-md hover:bg-white transition-colors">
                                    Clear
                                </button>
                                <button id="mlsfBulkDeleteBtn"
                                        type="button"
                                        onclick="bulkDeleteSelectedRecords()"
                                        class="inline-flex items-center space-x-2 bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-md text-sm font-semibold transition-colors shadow-sm">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    <span>Global Delete</span>
                                </button>
                            </div>
                        </div>
                        @endif

                        <!-- DataTable -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table id="mlsfTable" class="w-full table-auto">
                                        <thead>
                                            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
                                                @if(Auth::user() && Auth::user()->assign_role === 'Supper Admin')
                                                <th class="px-3 py-3 text-center whitespace-nowrap" style="width:36px">
                                                    <input type="checkbox" id="mlsfSelectAll" title="Select all on this page"
                                                           class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500 cursor-pointer">
                                                </th>
                                                @endif
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">S/N</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Customer Type</th>

                                                 <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Source</th>

                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">MLS File No</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">File Title</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Land Use</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">TP No</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Plot No</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">LGA</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Location</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Commissioned By</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Time Commissioned</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Date Commissioned</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <!-- Data will be loaded via DataTables AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End of File Generator Tab -->

                    <!-- Tab Content: Serial Initialization -->
                    <div id="content-serial-init" class="tab-content hidden">

                        <!-- Warning Alert -->
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i data-lucide="alert-triangle" class="h-5 w-5 text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <strong class="font-medium">Important:</strong> 
                                        Serial initialization is a <strong>one-time</strong> operation. Once initialized and locked, 
                                        values cannot be modified through the UI to prevent system errors and duplicate file numbers.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Serial Control Table -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Serial Number Control</h3>
                                    <button 
                                        onclick="refreshSerialTable()" 
                                        class="text-blue-600 hover:text-blue-700 flex items-center space-x-1 text-sm">
                                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                        <span>Refresh</span>
                                    </button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full" id="serialControlTable">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Land Use</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Serial</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Initialized By</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Initialized At</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200" id="serialTableBody">
                                            <!-- Will be populated by JavaScript just  the  -->
                                            <tr>
                                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                                    <i data-lucide="loader" class="w-6 h-6 inline-block animate-spin"></i>
                                                    <p class="mt-2">Loading serial control data...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- End of Serial Initialization Tab -->

                    <!-- Tab Content: Consolidation Report -->
                    <div id="content-consolidation" class="tab-content hidden">

                        <!-- Header Section -->
                        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl p-5 mb-6">
                            <div class="flex items-start space-x-4">
                                <div class="bg-emerald-100 p-3 rounded-lg">
                                    <i data-lucide="file-bar-chart" class="w-6 h-6 text-emerald-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-emerald-900">MLS File Number Consolidated Report</h3>
                                    <p class="text-sm text-emerald-700 mt-1">
                                        Generate and export consolidated reports from the <strong>mls_file_no</strong> source table.
                                        Filter by date range, file number year, or land use prefix, then export to PDF or Excel.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Controls -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                            <div class="px-6 py-4 border-b border-gray-100">
                                <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider flex items-center space-x-2">
                                    <i data-lucide="filter" class="w-4 h-4 text-gray-400"></i>
                                    <span>Report Filters</span>
                                </h4>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                                    <!-- Date From -->
                                    <div>
                                        <label for="crDateFrom" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Date From</label>
                                        <input type="date" id="crDateFrom"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all">
                                    </div>
                                    <!-- Date To -->
                                    <div>
                                        <label for="crDateTo" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Date To</label>
                                        <input type="date" id="crDateTo"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all">
                                    </div>
                                    <!-- File Year -->
                                    <div>
                                        <label for="crFileYear" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">File Number Year</label>
                                        <select id="crFileYear"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all">
                                            <option value="">All Years</option>
                                            @for ($y = date('Y'); $y >= 2020; $y--)
                                                <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <!-- Land Use Prefix -->
                                    <div>
                                        <label for="crPrefix" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Land Use Prefix</label>
                                        <select id="crPrefix"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all">
                                            <option value="">All Prefixes</option>
                                            @foreach($allPrefixes as $pfx)
                                                <option value="{{ $pfx->prefix }}">{{ $pfx->prefix }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Action Row -->
                                <div class="flex flex-wrap items-center justify-between mt-6 pt-4 border-t border-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <button onclick="fetchConsolidationReport()"
                                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg flex items-center space-x-2 text-sm font-semibold transition-all shadow-sm hover:shadow-md">
                                            <i data-lucide="search" class="w-4 h-4"></i>
                                            <span>Generate Report</span>
                                        </button>
                                        <button onclick="resetConsolidationFilters()"
                                            class="bg-white hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg flex items-center space-x-2 text-sm font-medium border border-gray-300 transition-all">
                                            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                            <span>Reset</span>
                                        </button>
                                    </div>

                                    <!-- Export Buttons (only visible after data is fetched) -->
                                    <div id="crExportBtns" class="hidden flex items-center space-x-3 mt-3 lg:mt-0">
                                        <span class="text-sm text-gray-500 font-medium" id="crRecordCount">0 records</span>
                                        <button onclick="exportConsolidationPDF()"
                                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg flex items-center space-x-2 text-sm font-semibold transition-all shadow-sm hover:shadow-md">
                                            <i data-lucide="file-text" class="w-4 h-4"></i>
                                            <span>Export PDF</span>
                                        </button>
                                        <button onclick="exportConsolidationExcel()"
                                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg flex items-center space-x-2 text-sm font-semibold transition-all shadow-sm hover:shadow-md">
                                            <i data-lucide="sheet" class="w-4 h-4"></i>
                                            <span>Export Excel</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Table -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider flex items-center space-x-2">
                                    <i data-lucide="table" class="w-4 h-4 text-gray-400"></i>
                                    <span>Report Preview</span>
                                </h4>
                                <div id="crFilterSummary" class="hidden flex items-center gap-3 text-xs">
                                    <span id="crSummaryBadge" class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold border border-emerald-200"></span>
                                </div>
                            </div>
                            <div class="overflow-x-auto max-h-[550px]">
                                <table class="min-w-full divide-y divide-gray-200" id="crPreviewTable">
                                    <thead class="bg-gray-50 sticky top-0 z-10">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">SN</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">File Number</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">File Name</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Land Use</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Location</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">LGA</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="crPreviewBody">
                                        <tr>
                                            <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                                                <div class="flex flex-col items-center gap-3">
                                                    <i data-lucide="file-bar-chart" class="w-10 h-10 text-gray-300"></i>
                                                    <span class="text-sm">Apply filters and click <strong>"Generate Report"</strong> to preview data</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                    <!-- End of Consolidation Report Tab -->

                </div>


                <!-- Footer -->
                @include('admin.footer')
            </div>

            <!-- Generate Modal with Alpine.js -->
            <div id="generateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                <div class="relative top-5 mx-auto p-4 md:p-6 border w-full max-w-4xl md:w-[800px] shadow-xl rounded-lg bg-white" 
                     x-data="fileNumberGenerator()" x-init="console.log('X-INIT DIRECT LOG'); init()">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                            <div>
                                <h3 id="modalTitle" class="text-xl font-semibold text-gray-900">Commission  New File Number</h3>
                                <p class="text-sm text-gray-500 mt-1">Fill in the details to generate a new MLS file number</p>
                            </div>
                            <button onclick="closeGenerateModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i data-lucide="x" class="w-6 h-6"></i>
                            </button>
                        </div>

                        <!-- Modal Form -->
                        <form id="generateForm" onsubmit="submitForm(event)" class="space-y-6">
                            @csrf

                            <!-- Hidden field for the generated file number that backend expects -->
                            <input type="hidden" name="generated_file_number" x-model="preview" id="generatedFileNumber">
                            <input type="hidden" name="tracking_id" id="trackingIdInput" value="">
                            <input type="hidden" name="extension_type" x-model="extensionType">
                            <input type="hidden" name="related_fileno" x-model="relatedFileNo">
                            <input type="hidden" name="related_file_title" x-model="relatedFileTitle">
                            <input type="hidden" name="related_file_indexing_id" x-model="relatedFileIndexingId">
                            <input type="hidden" name="change_of_purpose_app_id" x-model="changeOfPurposeAppId">
                            <input type="hidden" name="original_file_no" x-model="originalFileNo">
                            <input type="hidden" name="new_purpose" x-model="newPurpose">
                            <input type="hidden" name="subdivision_app_id" x-model="subdivisionAppId">
                            <input type="hidden" name="merger_app_id" x-model="mergerAppId">
                            <input type="hidden" name="separation_app_id" x-model="separationAppId">

                            <!-- Application Type Selection -->
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                                <label class="block text-sm font-semibold text-gray-700 mb-4">
                                    <i data-lucide="info" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                                    Application Type
                                </label>
                                <div class="flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-8">
                                    <div class="flex items-center gap-8 shrink-0">
                                        <label class="flex items-center group cursor-pointer">
                                            <div class="relative flex items-center justify-center">
                                                <input type="radio" name="application_type" value="new" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                                       x-model="appTypeRadio" @change="updateApplicationType()" checked required>
                                            </div>
                                            <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Direct Allocation</span>
                                        </label>
                                        <label class="flex items-center group cursor-pointer">
                                            <div class="relative flex items-center justify-center">
                                                <input type="radio" name="application_type" value="conversion" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                                       x-model="appTypeRadio" @change="updateApplicationType()" required>
                                            </div>
                                            <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Conversion</span>
                                        </label>
                                        <label class="hidden items-center group cursor-pointer">
                                            <div class="relative flex items-center justify-center">
                                                <input type="radio" name="application_type" value="change_of_purpose" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                                       x-model="appTypeRadio" @change="updateApplicationType()" required>
                                            </div>
                                            <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">
                                                <i data-lucide="repeat" class="w-4 h-4 inline mr-1"></i>Change of Purpose
                                            </span>
                                        </label>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">File Type</span>
                                        </div>
                                        {{-- UI-only control: it drives the Alpine `fileOption` state, which the
                                             File Options > File Type select below owns via x-model and submits.
                                             It must NOT carry id="fileOption" or name="file_option" — duplicating
                                             either makes FormData post file_option twice (PHP keeps the last) and
                                             makes getElementById('fileOption') resolve to this control instead of
                                             the bound one.

                                             x-model is required, not cosmetic: resetForm() clears fileOption, and
                                             without a binding this select kept showing the previous choice. Picking
                                             the value it already displays fires no change event, so the handler
                                             below never ran and the stale label silently disagreed with the state
                                             that gets submitted. --}}
                                        <select id="fileTypeWorkflow" x-model="fileTypeWorkflow"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white text-sm font-medium text-gray-700"
                                                @change="applyFileTypeWorkflow($event.target.value)">
                                                 <option value="">Select File Type</option>
                                            <option value="temporary">Temporary</option>
                                            <option value="extension">Extension</option>
                                            <option value="miscellaneous">Miscellaneous</option>
                                            <option value="change_of_purpose">Change of Purpose</option>
                                            <option value="resettlement">Resettlement</option>
                                            <option value="regrant">Re-grant</option>
                                            <option value="reissuance">Re-Issuance of FileNo</option>
                                            <option value="subdivision">Subdivision</option>
                                            <option value="merger">Merger</option>
                                            <option value="separation">Separation</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Allocation Source (Direct Allocation only) -->
                                <div x-show="applicationType === 'new'" class="mt-4 pt-4 border-t border-gray-200" x-transition>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-3">
                                        <i data-lucide="filter" class="w-3 h-3 inline mr-1 text-blue-500"></i>
                                        Direct allocation type
                                    </label>
                                    
                                    <!-- Allocation Options - Row 1 -->
                                    <div class="grid grid-cols-3 gap-2 mb-2">
                                        <!-- Default Radio -->
                                        <label class="flex items-center justify-center p-2 rounded-md border border-gray-200 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors">
                                            <input type="radio" name="allocation_source_type" value="default"
                                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" checked
                                                   @change="_currentAllocationSourceType = 'default'; hasCustomFileName = false; allocatedByFilter = ''; defaultAllocationType = ''; requireOpSource = false; subSource = ''">
                                            <span class="ml-2 text-xs font-medium text-gray-700 whitespace-nowrap">Default</span>
                                        </label>

                                        <!-- Occupancy Permit (OP) -->
                                        <label class="flex items-center justify-center p-2 rounded-md border border-blue-200 bg-blue-50/60 cursor-pointer hover:bg-blue-50 transition-colors">
                                            <input type="radio" name="allocation_source_type" value="op"
                                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                                   @change="_currentAllocationSourceType = 'op'; allocatedByFilter = ''; handleAllocationFilterChange()">
                                            <span class="ml-2 text-xs font-semibold text-gray-700 whitespace-nowrap">Occupancy Permit (OP)</span>
                                        </label>

                                        <!-- Allocation List -->
                                        <label class="flex items-center justify-center p-2 rounded-md border border-gray-200 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                            <input type="radio" name="allocation_source_type" value="allocation_list"
                                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                                   @change="_currentAllocationSourceType = 'allocation_list'; hasCustomFileName = false; allocatedByFilter = 'Allocation List'; handleAllocationFilterChange(); defaultAllocationType = ''">
                                            <span class="ml-2 text-xs font-semibold text-gray-700 whitespace-nowrap">Allocation List</span>
                                        </label>
                                    </div>

                                    <!-- Allocation Options - Row 2 (Shown only when OP is selected) -->
                                    <div class="grid grid-cols-1 gap-2" x-show="_currentAllocationSourceType === 'op'" x-transition>
                                        <!-- New Applications (Existing OP) -->
                                        <label class="flex items-center justify-center p-3 rounded-md border border-blue-200 bg-blue-50/60 cursor-pointer hover:bg-blue-100 transition-colors">
                                            <input type="radio" name="allocation_source_type" value="direct"
                                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                                   @change="_currentAllocationSourceType = 'direct'; allocatedByFilter = ''; defaultAllocationType = 'direct'; openCommissionOpCaptureModal('direct')">
                                            <span class="ml-2 text-sm font-semibold text-gray-700 whitespace-nowrap">New Applications (Existing OP)</span>
                                        </label>
                                    </div>

                                    <input x-show="allocatedByFilter === 'Allocation List'" type="hidden" name="is_resettlement" value="0">
                                    <input type="hidden" name="is_resettlement" :value="defaultAllocationType === 'resettlement' ? 1 : 0" x-show="allocatedByFilter === ''">
                                    </div>
                                </div>

                            <!-- Sections hidden when OP source is selected but not yet captured -->
                            <div x-show="!isOpFormHidden" x-transition.opacity.duration.200ms class="space-y-6">

                            <!-- Batch / Multi-Mode Section -->
                            <div class="bg-gradient-to-r p-4 rounded-lg border-2 transition-colors duration-300"
                                 :class="applicationType === 'change_of_purpose' ? 'from-purple-50 to-pink-50 border-purple-200' : 'from-blue-50 to-indigo-50 border-blue-200'">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="layers" class="w-5 h-5" :class="applicationType === 'change_of_purpose' ? 'text-purple-600' : 'text-blue-600'"></i>
                                        <label class="text-sm font-semibold text-gray-700" x-text="applicationType === 'change_of_purpose' ? 'Change of Purpose Mode' : 'Batch Mode'"></label>
                                        <span class="text-xs text-gray-500" x-text="applicationType === 'change_of_purpose' ? '(Generate file using selected CoP application)' : '(Generate multiple files at once)'"></span>
                                    </div>
                                    
                                    <!-- Hide default batch switch when in Change of purpose mode -->
                                    <label class="relative inline-flex items-center cursor-pointer" x-show="applicationType !== 'change_of_purpose'">
                                        <input type="checkbox" x-model="batchMode" @change="toggleBatchMode()" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <!-- Uncommissioned OP batch awaiting commissioning: offer a way back
                                     to the OP Batch card to correct a mistake before generating. -->
                                <div x-show="pendingOpBatchId" x-transition
                                     class="mt-3 flex flex-wrap items-center justify-between gap-3 px-3 py-2 bg-violet-50 border border-violet-200 rounded-lg">
                                    <div class="text-xs text-violet-800">
                                        Commissioning OP batch
                                        <span class="font-mono font-semibold" x-text="pendingOpBatchId"></span>
                                        <span class="text-violet-500">— not yet commissioned, so it can still be edited.</span>
                                    </div>
                                    <button type="button" onclick="copBackToBatchCard()"
                                            class="px-3 py-1.5 bg-white border border-violet-300 rounded-lg text-xs font-semibold text-violet-700 hover:bg-violet-100 transition">
                                        &lt; Back to OP Batch
                                    </button>
                                </div>

                                <!-- Batch Quantity Input (shown when batch mode is active) -->
                                <div x-show="batchMode" x-transition class="mt-3">
                                    <label for="batchQuantity" class="block text-xs font-medium text-gray-600 mb-1">
                                        Number of Files to Generate
                                    </label>
                                    <div class="flex items-center space-x-3">
                                        <input type="number" id="batchQuantity" x-model="batchQuantity" @input="updateBatchPreview()"
                                               min="2" max="100" 
                                               class="w-32 px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <span class="text-sm text-gray-600">files (Max: 100)</span>
                                        <div class="flex-1"></div>
                                        <div class="text-xs text-blue-600 font-medium" x-show="batchQuantity > 1">
                                            Serial Range: <span x-text="serialRangePreview"></span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2 font-bold">
                                        <i data-lucide="info" class="w-3 h-3 inline"></i>
                                       All files will share the same File Name (File Title) and Prefix. You can only add unique location details for each file (if need be)
                                    </p>
                                </div>
                            </div>

                            <!-- Main Form Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Left Column -->
                                <div class="space-y-4">
                                    <!-- File Name -->


                                      <!-- File Options -->

                                    <!-- Middle Prefix Section -->
                                    <div id="middlePrefixSection" class="hidden">
                                        <label for="middlePrefix" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i data-lucide="tag" class="w-4 h-4 inline mr-1"></i>
                                            Middle Prefix
                                        </label>
                                        <input type="text" id="middlePrefix" name="middle_prefix" x-model="middlePrefix"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            placeholder="e.g., KN" value="KN">
                                    </div>

                                    <!-- This section has been moved to the right column with toggle functionality -->

                                    <!-- File Options Section - 2x2 Grid -->
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="flex items-center space-x-2">
                                                <i data-lucide="settings" class="w-4 h-4 inline mr-1"></i>
                                                <label class="block text-sm font-semibold text-gray-700">File Options</label>
                                                <!-- Entry Counter (shown in batch mode) -->
                                                <span x-show="batchMode" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                                                    Applicant <span x-text="currentEntryIndex + 1"></span> of <span x-text="batchQuantity"></span>
                                                </span>
                                            </div>
                                            <!-- Applicant Navigation (shown in batch mode) -->
                                            <div x-show="batchMode" class="flex items-center space-x-2">
                                                <button type="button" @click="previousEntry()" 
                                                        :disabled="currentEntryIndex === 0"
                                                        :class="currentEntryIndex === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                                        class="p-1 rounded-md text-blue-600 transition-colors">
                                                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                                                </button>
                                                <button type="button" @click="nextEntry()" 
                                                        :disabled="currentEntryIndex >= batchQuantity - 1"
                                                        :class="currentEntryIndex >= batchQuantity - 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                                        class="p-1 rounded-md text-blue-600 transition-colors">
                                                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Party 1 for this applicant: the paired OP's Party 2 (allottee), matched by
                                             batch sequence. Only present after a Batch Capture OP hand-off. --}}
                                        <div x-show="batchMode && opBatchAllottees.length > 0" x-cloak
                                             class="mb-3 pb-3 border-b border-gray-200">
                                            <div class="text-xs font-medium text-gray-500">
                                                Party 1 — allottee from OP <span x-text="currentEntryIndex + 1"></span>
                                            </div>
                                            <div class="text-sm font-semibold text-violet-700"
                                                 x-text="opBatchAllottees[currentEntryIndex] || '—'"></div>
                                        </div>

                                        <!-- 2x2 Grid Layout -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Row 1, Col 1: File Type -->
                                            <div>
                                                <label for="fileOption" class="block text-xs font-medium text-gray-600 mb-1">
                                                    File Type
                                                </label>
                                                <select id="fileOption" name="file_option" x-model="fileOption" @change="updateFileOption()"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                                    <option value="normal" selected>Normal File</option>
                                                    <option value="temporary" hidden>Temporary File</option>
                                                    <option value="extension" hidden>Extension</option>
                                                    <option value="miscellaneous" hidden>Miscellaneous</option>
                                                    <option value="regrant" hidden>Re-grant</option>
                                                    <option value="reissuance" hidden>Re-Issuance of FileNo</option>
                                                    <option value="resettlement" hidden>Resettlement</option>
                                                    <option value="old_mls">Old MLS</option>
                                                    <option value="sit" x-show="applicationType === 'new'">SIT</option>
                                                </select>
                                            </div>

                                            <!-- Row 1, Col 2: File NameSelection -->
                                            <div>
                                                <label for="fileName" class="block text-xs font-medium text-gray-600 mb-1">
                                                    File Name
                                                </label>
                                                
                                                <!-- Standard Text Input (Always shown when hasCustomFileName is checked) -->
                                                <div x-show="hasCustomFileName || applicationType !== 'new' || allocatedByFilter === ''" x-cloak>
                                                    <input type="text" id="fileName" name="file_name" x-model="fileName"
                                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                           :class="{ '': isInherited }"
                                                           placeholder="Enter file name">
                                                </div>

                                                <!-- Select2 Dropdown (Shown only when NOT using custom file name AND Direct Allocation + Allocation List) -->
                                                <div x-show="!hasCustomFileName && applicationType === 'new' && allocatedByFilter === 'Allocation List'" wire:ignore x-cloak>
                                                    <select id="allocationSelect" 
                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                            style="width: 100%">
                                                        <option value="">Search and select an allottee</option>
                                                        @foreach($unallocatedEntries as $entry)
                                                            <option value="{{ $entry->id }}" 
                                                                    data-full-name="{{ $entry->first_name }} {{ $entry->middle_name ? $entry->middle_name . ' ' : '' }}{{ $entry->last_name }}"
                                                                    data-plot="{{ $entry->plot_number }}"
                                                                    data-district="{{ $entry->district }}"
                                                                    data-lga="{{ $entry->lga }}"
                                                                    data-state="{{ $entry->state }}"
                                                                    data-allocated-by="{{ $entry->allocated_by }}"
                                                                    data-allottee-address="{{ $entry->allottee_address }}">
                                                                {{ $entry->first_name }} {{ $entry->middle_name ? $entry->middle_name . ' ' : '' }}{{ $entry->last_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <!-- Hidden input for the allocation ID -->
                                                    <input type="hidden" name="allocation_id" id="allocation_id" x-model="allocationId">
                                                    <!-- Hidden input to submit the file_name when using select2 -->
                                                    <input type="hidden" name="file_name" :value="fileName" :disabled="applicationType !== 'new'">
                                                </div>
                                            </div>

                                            <!-- Row 2, Col 1: Prefix (Now First) -->
                                            <div x-show="fileOption !== 'sit'" x-transition>
                                                <label for="prefix" class="block text-xs font-medium text-gray-600 mb-1">
                                                    Select Prefix
                                                </label>
                                                <!-- Hidden input to ensure Prefix is submitted if select is disabled -->

                                                <select id="prefix" name="prefix" x-model="prefix" @change="handlePrefixChange($event)"
                                                        class="w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                                                        :class="{ '': isInherited }">
                                                    <option value="">Select Prefix</option>
                                                    <template x-for="px in filteredPrefixes" :key="px.id">
                                                        <option :value="px.prefix" :data-limit="px.land_use_id" x-text="px.prefix"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Row 2, Col 2: Land Use (Read-Only/Disabled) -->
                                            <div x-show="fileOption !== 'sit'" x-transition>
                                                <label for="landUse" class="block text-xs font-medium text-gray-600 mb-1">
                                                    Select Land Use
                                                </label>
                                                <!-- Hidden input to ensure value is submitted if select is disabled -->
                                                <input type="hidden" name="land_use" x-model="landUse">
                                                <select id="landUse" x-model="landUse"
                                                        @change="landUseId = $event.target.options[$event.target.selectedIndex].getAttribute('data-id'); updatePreview();"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                                    <option value="">(Auto-selected)</option>
                                                    @foreach($landUses as $lu)
                                                        @php
                                                            $name = strtoupper($lu->landuse);
                                                            $code = '';
                                                            if (str_contains($name, 'RESIDENTIAL')) $code = 'RES';
                                                            elseif (str_contains($name, 'COMMERCIAL')) $code = 'COM';
                                                            elseif (str_contains($name, 'INDUSTRIAL')) $code = 'IND';
                                                            elseif (str_contains($name, 'AGRICULTURAL')) $code = 'AG';
                                                            else $code = substr($name, 0, 3);
                                                        @endphp
                                                        <option value="{{ $code }}" data-id="{{ $lu->id }}">{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <!-- SIT info banner (shown when SIT file type is selected) -->
                                            <div x-show="fileOption === 'sit'" x-transition class="col-span-2">
                                                <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-md text-blue-700 text-sm">
                                                    <i data-lucide="info" class="w-4 h-4 flex-shrink-0"></i>
                                                    <span>SIT files do not have Land Use or Prefix. Serial is auto-generated.</span>
                                                </div>
                                            </div>

                                            <!-- SIT File Reason (shown when SIT file type is selected) -->
                                            <div x-show="fileOption === 'sit'" x-transition class="col-span-2">
                                                <label for="sitReason" class="block text-xs font-medium text-gray-600 mb-1">
                                                    <i data-lucide="clipboard-list" class="w-3.5 h-3.5 inline mr-1"></i>
                                                    SIT File Reason
                                                </label>
                                                <textarea id="sitReason" name="sit_reason" x-model="sitReason" rows="2"
                                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                          placeholder="Enter the reason for this SIT file"></textarea>
                                            </div>

                                            <!-- Row 3, Col 1: Purpose (hidden for SIT) -->
                                            <div x-show="fileOption !== 'sit'" x-transition>
                                                <label for="purpose" class="block text-xs font-medium text-gray-600 mb-1">
                                                    Purpose
                                                </label>
                                                 <select id="purpose" name="purpose_id" x-model="purpose" @change="updatePreview()"
                                                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                                                         :disabled="fileOption === 'sit'" required>
                                                    <option value="">Select Purpose</option>
                                                    <template x-for="p in purposes" :key="p.id">
                                                        <option :value="p.id" x-text="p.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                         
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                                   Customer Type
                                                </label>
                                                <div class="flex items-center h-[42px]">
                                                     <select id="customerType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                                                         :class="{ '': fileOption === 'sit' }"
                                                         name="customer_type" x-model="customerType" :disabled="fileOption === 'sit'" required>
                                                        <option value="">Select Customer Type</option>
                                                        <option value="Individual">Individual</option>
                                                        <option value="Corporate">Corporate</option>
                                                        <option value="Multiple">Multiple</option>
                                                        <option value="Government">Government</option>
                                                    </select>
                                                    <!-- Hidden input to ensure Government is submitted when select is disabled -->
                                                    <input type="hidden" x-show="fileOption === 'sit'" name="customer_type" :value="customerType" :disabled="fileOption !== 'sit'">
                                                </div>
                                            </div>

                                            <!-- Gender -->
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                                   Gender
                                                </label>
                                                <div class="flex items-center h-[42px]">
                                                    <select id="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                                                        name="gender" x-model="gender" required>
                                                        <option value="">Select Gender</option>
                                                        <option value="Male">Male</option>
                                                        <option value="Female">Female</option>
                                                        <option value="Corporate">Corporate</option>
                                                        <option value="Joint">Joint</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Row 4, Col 1: Phone Number -->
                                            <div>
                                                <label for="generatePhoneNo" class="block text-xs font-medium text-gray-600 mb-1">
                                                    Phone No of Applicant
                                                </label>
                                                <input type="text" id="generatePhoneNo" name="phone_no" x-model="phone_no"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                       :class="{ '': isInherited }"
                                                       placeholder="Enter Phone No">
                                            </div>

                                            <!-- Row 4, Col 2: Address -->
                                            <div>
                                                <label for="generateAddress" class="block text-xs font-medium text-gray-600 mb-1">
                                                    Address of Applicant
                                                </label>
                                                <input type="text" id="generateAddress" name="address" x-model="address"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                                       :class="{ '': isInherited }"
                                                       placeholder="Enter Address">
                                            </div>
                                        </div>

                                        <!-- Local Rep Details (Shown only for Conversion) -->
                                        <div x-show="applicationType === 'conversion'" class="mt-4 p-4 border border-blue-200 bg-blue-50 rounded-lg">
                                            <h4 class="text-sm font-semibold text-blue-800 mb-3 border-b border-blue-200 pb-2">Details of Local Rep</h4>
                                            
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label for="generateRepPhoneNo" class="block text-xs font-medium text-gray-700 mb-1">
                                                        Phone No
                                                    </label>
                                                    <input type="text" id="generateRepPhoneNo" name="rep_phone_no" x-model="rep_phone_no"
                                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                                           :class="{ '': isInherited }"
                                                           placeholder="Rep Phone No">
                                                </div>

                                                <div>
                                                    <label for="generateRepAddress" class="block text-xs font-medium text-gray-700 mb-1">
                                                        Address
                                                    </label>
                                                    <input type="text" id="generateRepAddress" name="rep_address" x-model="rep_address"
                                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm uppercase"
                                                           :class="{ '': isInherited }"
                                                           placeholder="Rep Address">
                                                </div>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Right Column -->
                                    <div class="space-y-4">
                                        <!-- Extension File Selection -->
                                        <div x-show="fileOption === 'extension'">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="link" class="w-4 h-4 inline mr-1"></i>
                                                <span x-text="extensionType === 'plot' ? 'Select Existing File (Plot Extension) — original number is retained' : 'Select Existing MLS File Number to Extend'"></span>
                                            </label>
                                            <div x-show="extensionType === 'plot'" class="mb-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                                <i data-lucide="git-branch" class="w-3 h-3 mr-1"></i>
                                                Plot Extension
                                            </div>

                                            <div class="flex space-x-2">
                                                <div class="relative flex-grow">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <i data-lucide="file-text" class="h-4 w-4 text-gray-400"></i>
                                                    </div>
                                                    <input type="text" 
                                                           id="displayExtensionFileNo"
                                                           x-model="existingFileNo" 
                                                           readonly
                                                           placeholder="No file selected"
                                                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 text-gray-500 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                                <button type="button" 
                                                        onclick="openExtensionFileSelector()"
                                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                                    Select File
                                                </button>
                                            </div>
                                            
                                            <!-- Hidden input to store the actual value for form submission -->
                                            <input type="hidden" id="extensionFileNo" name="existing_file_no" x-model="existingFileNo">

                                            <p class="mt-2 text-xs text-gray-500">
                                                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                                <span x-text="extensionType === 'plot' ? 'Search and select the existing file. Its original file number will be kept; details auto-fill if indexed.' : 'Search and select the file you wish to extend.'"></span>
                                            </p>
                                        </div>

                                        <!-- Temporary File Selection -->
                                        <div x-show="fileOption === 'temporary'">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="clock" class="w-4 h-4 inline mr-1"></i>
                                                Select Existing File for Temporary Version
                                            </label>

                                            <div class="flex items-center space-x-3">
                                                <div class="flex-grow">
                                                    <input type="text" id="displayTemporaryFileNo" x-model="existingFileNo" readonly
                                                           placeholder="No file selected"
                                                           class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md focus:outline-none cursor-not-allowed">
                                                    <input type="hidden" id="temporaryFileNo" name="existing_file_no" x-model="existingFileNo">
                                                </div>
                                                <button type="button" onclick="openTemporaryFileSelector()"
                                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center gap-2 text-sm font-medium whitespace-nowrap">
                                                    <i data-lucide="search" class="w-4 h-4"></i>
                                                    Select File
                                                </button>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">Select the main file number to fetch tracking ID and other details</p>
                                        </div>

                                        <!-- Change of Purpose Selection -->
                                        <!-- Only shown when the File Type is Change of Purpose. -->
                                        <div id="change-of-purpose-section" x-show="applicationType === 'change_of_purpose'"
                                             class="mb-4 border border-blue-200 rounded-lg p-4 bg-blue-50/30 shadow-sm" x-transition x-cloak>
                                            
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="repeat" class="w-4 h-4 inline mr-1 text-blue-600"></i>
                                                Select Approved Change of Purpose Application
                                            </label>
                                            
                                            <div class="flex items-center space-x-3">
                                                <div class="flex-grow">
                                                    <input type="text" id="displayCopFileNo" readonly
                                                           placeholder="No application selected"
                                                           :value="originalFileNo ? originalFileNo + ' - ' + copApplicantName : ''"
                                                           class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md focus:outline-none cursor-not-allowed">
                                                </div>
                                                <button type="button" @click="openCopFileSelector()"
                                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center gap-2 text-sm font-medium whitespace-nowrap">
                                                    <i data-lucide="search" class="w-4 h-4"></i>
                                                    Select FileNo
                                                </button>
                                            </div>

                                            <!-- Selected Application Details -->
                                            <div id="selectedCopDetails" x-show="changeOfPurposeAppId" 
                                                 class="p-4 rounded-lg bg-white border border-blue-100 shadow-sm space-y-2 relative mt-4">
                                                <div class="text-sm font-semibold text-gray-700">Selected Details:</div>
                                                
                                                <div class="grid grid-cols-2 gap-4 mt-2">
                                                    <div>
                                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Current File Number</div>
                                                        <div class="font-mono font-bold text-gray-900" x-text="originalFileNo || '—'"></div>
                                                    </div>
                                                    <div>
                                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Applicant</div>
                                                        <div class="font-semibold text-gray-900" x-text="copApplicantName || '—'"></div>
                                                    </div>
                                                    <div>
                                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Current Land Use</div>
                                                        <div class="text-sm text-gray-700" x-text="copCurrentLandUse || '—'"></div>
                                                    </div>
                                                    <div>
                                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">New Purpose</div>
                                                        <div class="font-semibold text-emerald-700" x-text="copNewPurpose || '—'"></div>
                                                    </div>
                                                    <div x-show="relatedFileNo">
                                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Related File Number</div>
                                                        <div class="font-bold text-blue-700" x-text="relatedFileNo"></div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-4 pt-3 border-t border-gray-100 flex justify-end">
                                                    <button type="button" @click="clearChangeOfPurposeSelection()"
                                                            class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-md hover:bg-red-100 transition">
                                                        <i data-lucide="trash-2" class="w-3 h-3"></i>Clear App
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Old FileNo (Duplicate) — Re-Issuance of FileNo.
                                             The file being re-issued already exists under a duplicated
                                             number; it is picked exactly like a Related File, but stored
                                             on its own as the old file number. -->
                                        <div x-show="fileOption === 'reissuance'" x-transition class="mb-4">
                                            <div class="bg-rose-50 p-4 rounded-lg border border-rose-200 shadow-sm">
                                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                                    <i data-lucide="copy" class="w-4 h-4 inline mr-1 text-rose-600"></i>
                                                    Old File (Duplicate FileNo)
                                                    <span class="text-red-500 ml-1">*</span>
                                                </label>
                                                <div class="grid grid-cols-1 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                                            Old FileNo (Duplicate) <span class="text-red-500">*</span>
                                                        </label>
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" readonly
                                                                x-model="oldFileNo"
                                                                class="flex-1 px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-mono cursor-not-allowed"
                                                                placeholder="No old file selected">
                                                            <button type="button" @click="openOldFileModal()"
                                                                    class="px-3 py-2 bg-rose-600 text-white text-xs font-semibold rounded-md hover:bg-rose-700 transition-colors flex items-center gap-1.5 whitespace-nowrap">
                                                                <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                                                Select
                                                            </button>
                                                            <button type="button" x-show="oldFileNo" @click="clearOldFile()"
                                                                    class="px-3 py-2 bg-white border border-rose-300 text-rose-700 text-xs font-semibold rounded-md hover:bg-rose-100 transition-colors">
                                                                Clear
                                                            </button>
                                                        </div>
                                                        <input type="hidden" name="old_fileno" x-model="oldFileNo">
                                                    </div>
                                                    <div x-show="oldFileNo">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Old File Title</label>
                                                        <input type="text" x-model="oldFileTitle" readonly
                                                            class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700"
                                                            placeholder="Auto-filled from the selected file">
                                                    </div>
                                                </div>
                                                <p class="text-[11px] text-gray-500 mt-3">
                                                    <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                                    The new file number is generated normally; the duplicated number selected here is kept on the record as the old file number.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Related File Section. Hidden for the workflows that carry their own
                                             dedicated related/source selectors (Merger, Subdivision, Separation,
                                             Re-Issuance), for Temporary (inherits its source file), and for
                                             Change of Purpose (has its own application selector above). -->
                                        <div x-show="!['subdivision', 'merger', 'separation', 'temporary', 'reissuance'].includes(fileOption) && applicationType !== 'change_of_purpose'"
                                             class="mb-4">
                                            <!-- Checkbox to enable Related File (hidden for RC prefix since it's mandatory) -->
                                            <div x-show="!isRecertificationPrefix" class="mb-3">
                                                <label class="flex items-center cursor-pointer">
                                                    <input type="checkbox" 
                                                           x-model="hasRelatedFile" 
                                                           @change="if(!hasRelatedFile) { relatedFileNo = ''; relatedFileTitle = ''; relatedFileIndexingId = ''; }"
                                                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                    <span class="ml-2 text-sm font-medium text-gray-700">
                                                        <i data-lucide="link-2" class="w-4 h-4 inline mr-1 text-gray-500"></i>
                                                        This file has a Related File Number
                                                    </span>
                                                </label>
                                                <p class="text-xs text-gray-500 mt-1 ml-6">Check this if this file is related to an existing file (e.g., conversion, extension, recertification)</p>
                                            </div>

                                            <!-- Related File Input Section (shown when checked OR when RC prefix) -->
                                            <div x-show="hasRelatedFile || isRecertificationPrefix" x-transition class="bg-amber-50 p-4 rounded-lg border border-amber-200 shadow-sm">
                                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                                    <i data-lucide="link-2" class="w-4 h-4 inline mr-1 text-amber-600"></i>
                                                    <span x-text="isRecertificationPrefix ? 'Related File (Original File for Recertification)' : 'Related File'"></span>
                                                    <span x-show="isRecertificationPrefix" class="text-red-500 ml-1">*</span>
                                                </label>
                                                <div class="grid grid-cols-1 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                                            Related File Number
                                                            <span x-show="isRecertificationPrefix" class="text-red-500">*</span>
                                                        </label>
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" readonly
                                                                x-model="relatedFileNo"
                                                                class="flex-1 px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-mono cursor-not-allowed"
                                                                placeholder="No file selected"
                                                                :required="isRecertificationPrefix">
                                                            <button type="button" @click="openRelatedFileModal()"
                                                                    class="px-3 py-2 bg-amber-600 text-white text-xs font-semibold rounded-md hover:bg-amber-700 transition-colors flex items-center gap-1.5 whitespace-nowrap">
                                                                <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                                                Select
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div x-show="relatedFileNo">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                                            Related File Title
                                                            <span x-show="isRecertificationPrefix" class="text-red-500">*</span>
                                                        </label>
                                                        <input type="text"
                                                            x-model="relatedFileTitle"
                                                            class="w-full px-3 py-2 border border-gray-200 rounded-md text-gray-700"
                                                            placeholder="Enter the original file title"
                                                            :required="isRecertificationPrefix && relatedFileNo">
                                                        <p class="text-[11px] text-gray-500 mt-1">Auto-filled from the selected file. If empty (e.g. legacy files), please type the original file title.</p>
                                                    </div>
                                                    <div x-show="relatedFileNo">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Related File Type</label>
                                                        <select x-model="relatedFileType"
                                                                class="w-full px-3 py-2 border border-gray-200 rounded-md text-gray-700 bg-white">
                                                            <option value="">-- Select type --</option>
                                                            <template x-for="t in relatedFileTypes" :key="t">
                                                                <option :value="t" x-text="t"></option>
                                                            </template>
                                                        </select>
                                                        <div x-show="relatedFileType === 'Other'" x-transition class="mt-2">
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                                                Please specify <span class="text-red-500">*</span>
                                                            </label>
                                                            <input type="text" x-model="relatedFileTypeOther" maxlength="60"
                                                                class="w-full px-3 py-2 border border-gray-200 rounded-md text-gray-700"
                                                                placeholder="Describe the relationship (e.g. Court Order)"
                                                                :required="relatedFileType === 'Other'">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Additional related files: a file can relate to more than one -->
                                                <template x-for="(row, idx) in extraRelatedFiles" :key="idx">
                                                    <div class="mt-4 pt-4 border-t border-amber-200">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <span class="text-xs font-semibold text-gray-600">
                                                                Related File #<span x-text="idx + 2"></span>
                                                            </span>
                                                            <button type="button" @click="removeRelatedFile(idx)"
                                                                    class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 transition">
                                                                <i data-lucide="trash-2" class="w-3 h-3"></i>Remove
                                                            </button>
                                                        </div>
                                                        <div class="grid grid-cols-1 gap-3">
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Related File Number</label>
                                                                <div class="flex items-center gap-2">
                                                                    <input type="text" readonly x-model="row.file_no"
                                                                        class="flex-1 px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-mono cursor-not-allowed"
                                                                        placeholder="No file selected">
                                                                    <button type="button" @click="openRelatedFileModal(idx)"
                                                                            class="px-3 py-2 bg-amber-600 text-white text-xs font-semibold rounded-md hover:bg-amber-700 transition-colors flex items-center gap-1.5 whitespace-nowrap">
                                                                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                                                        Select
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div x-show="row.file_no">
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Related File Title</label>
                                                                <input type="text" x-model="row.title"
                                                                    class="w-full px-3 py-2 border border-gray-200 rounded-md text-gray-700"
                                                                    placeholder="Enter the related file title">
                                                            </div>
                                                            <div x-show="row.file_no">
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Related File Type</label>
                                                                <select x-model="row.type"
                                                                        class="w-full px-3 py-2 border border-gray-200 rounded-md text-gray-700 bg-white">
                                                                    <option value="">-- Select type --</option>
                                                                    <template x-for="t in relatedFileTypes" :key="t">
                                                                        <option :value="t" x-text="t"></option>
                                                                    </template>
                                                                </select>
                                                                <div x-show="row.type === 'Other'" x-transition class="mt-2">
                                                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                                                        Please specify <span class="text-red-500">*</span>
                                                                    </label>
                                                                    <input type="text" x-model="row.type_other" maxlength="60"
                                                                        class="w-full px-3 py-2 border border-gray-200 rounded-md text-gray-700"
                                                                        placeholder="Describe the relationship (e.g. Court Order)"
                                                                        :required="row.type === 'Other'">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                {{-- Conversion links to exactly one existing file, so it never
                                                     offers additional related files. --}}
                                                <div x-show="applicationType !== 'conversion'" class="mt-4 pt-3 border-t border-amber-200">
                                                    <button type="button" @click="addRelatedFile()"
                                                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-amber-300 text-amber-700 text-xs font-semibold rounded-md hover:bg-amber-100 transition-colors">
                                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                                        Add Another Related File
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Subdivision Related Section -->
                                        <div x-show="fileOption === 'subdivision'" x-transition class="mb-4">
                                            <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200 shadow-sm">
                                                <div class="flex items-center justify-between mb-3">
                                                    <label class="block text-sm font-semibold text-gray-700">
                                                        <i data-lucide="split" class="w-4 h-4 inline mr-1 text-indigo-600"></i>
                                                        Subdivision Selection
                                                    </label>
                                                    <button type="button" @click="openSubdivisionFileModal()"
                                                            class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-700 transition-colors flex items-center gap-1.5">
                                                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                                        Select Subdivision File
                                                    </button>
                                                </div>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Source File Number</label>
                                                        <input type="text" readonly x-model="subdivisionFileNo"
                                                            class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-mono"
                                                            placeholder="No file selected">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">No. of Plots (Batch Size)</label>
                                                        <input type="text" readonly :value="subdivisionAppId ? batchQuantity : 'N/A'"
                                                            class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-bold"
                                                            placeholder="--">
                                                    </div>
                                                    <div x-show="relatedFileNo" class="col-span-2">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Related File Number</label>
                                                        <div class="px-3 py-2 border border-gray-200 rounded-md bg-white text-blue-700 font-bold" x-text="relatedFileNo"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Merger Related Section -->
                                        <div x-show="fileOption === 'merger'" x-transition class="mb-4">
                                            <div class="bg-orange-50 p-4 rounded-lg border border-orange-200 shadow-sm">
                                                <div class="flex items-center justify-between mb-3">
                                                    <label class="block text-sm font-semibold text-gray-700">
                                                        <i data-lucide="merge" class="w-4 h-4 inline mr-1 text-orange-600"></i>
                                                        Merger Selection
                                                    </label>
                                                    <button type="button" @click="openMergerFileModal()"
                                                            class="px-3 py-1.5 bg-orange-600 text-white text-xs font-semibold rounded-md hover:bg-orange-700 transition-colors flex items-center gap-1.5">
                                                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                                        Select Merger File
                                                    </button>
                                                </div>
                                                <div class="grid grid-cols-1 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Source File Number</label>
                                                        <input type="text" readonly x-model="mergerFileNo"
                                                            class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-mono"
                                                            placeholder="No file selected">
                                                    </div>
                                                    <div x-show="relatedFileNo">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Related File Number</label>
                                                        <div class="px-3 py-2 border border-gray-200 rounded-md bg-white text-blue-700 font-bold" x-text="relatedFileNo"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Separation Related Section -->
                                        <div x-show="fileOption === 'separation'" x-transition class="mb-4">
                                            <div class="bg-violet-50 p-4 rounded-lg border border-violet-200 shadow-sm">
                                                <div class="flex items-center justify-between mb-3">
                                                    <label class="block text-sm font-semibold text-gray-700">
                                                        <i data-lucide="split" class="w-4 h-4 inline mr-1 text-violet-600"></i>
                                                        Separation Selection
                                                    </label>
                                                    <button type="button" @click="openSeparationFileModal()"
                                                            class="px-3 py-1.5 bg-violet-600 text-white text-xs font-semibold rounded-md hover:bg-violet-700 transition-colors flex items-center gap-1.5">
                                                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                                        Select Separation File
                                                    </button>
                                                </div>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Source File Number</label>
                                                        <input type="text" readonly x-model="separationFileNo"
                                                            class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-mono"
                                                            placeholder="No file selected">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">No. of Plots (Batch Size)</label>
                                                        <input type="text" readonly :value="separationAppId ? batchQuantity : 'N/A'"
                                                            class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 font-bold"
                                                            placeholder="--">
                                                    </div>
                                                    <div x-show="relatedFileNo" class="col-span-2">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Related File Number</label>
                                                        <div class="px-3 py-2 border border-gray-200 rounded-md bg-white text-blue-700 font-bold" x-text="relatedFileNo"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    <!-- Year and Serial Number Grid -->
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <!-- Year -->
                                        <div x-show="showYearSection" x-cloak>
                                            <label for="year" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                                                Year
                                            </label>
                                            <input type="number" id="year" name="year" x-model="year" @input="updatePreview()"
                                                   :class="yearFieldClass"
                                                   min="2020" max="2050" :readonly="!isYearEditable">
                                            <p class="text-xs text-gray-500 mt-1" x-text="yearDescription"></p>
                                        </div>

                                        <!-- Serial Number -->
                                        <div>
                                            <label for="serialNo" class="block text-sm font-medium text-gray-700 mb-2 flex justify-between items-center">
                                                <span>
                                                    <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                                                    Serial No.
                                                </span>
                                                <template x-if="['normal', 'reissuance', 'subdivision', 'merger', 'separation', 'temporary'].includes(fileOption)">
                                                    <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100" 
                                                          x-text="'Next: ' + getNextSerialForLandUse(prefix || landUse)">
                                                    </span>
                                                </template>
                                            </label>
                                            <input :type="serialFieldType" id="serialNo" name="serial_no" 
                                                   x-model="serialNo" 
                                                   x-on:input="serialFieldType === 'text' ? updatePreviewOnly() : updatePreview()"
                                                   :class="serialFieldClass"
                                                   :placeholder="serialPlaceholder" 
                                                   :readonly="isSerialReadonly" 
                                                   :disabled="isSerialDisabled"
                                                   x-bind:min="serialFieldType === 'number' ? '1' : false"
                                                   x-bind:max="serialFieldType === 'number' ? '9999' : false"
                                                   :inputmode="serialFieldType === 'text' ? 'text' : 'numeric'"
                                                   autocomplete="off"
                                                   >
                                            <p class="text-xs mt-1" :class="serialDescriptionClass" x-text="serialDescription"></p>
                                        </div>
                                    </div>

                                        <!-- Full File Number Preview -->
                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i data-lucide="eye" class="w-4 h-4 inline mr-1"></i>
                                            Generated File Number Preview
                                        </label>
                                        <div id="mlsfPreview" class="w-full px-4 py-3 bg-white border border-blue-300 rounded-md text-lg font-mono text-center font-bold shadow-sm"
                                             :class="previewClass" x-text="preview">
                                        </div>
                                    </div>
                                    
                                         <div class="bg-gray-100 px-4 py-2 rounded-md flex justify-between items-center max-w-xs mt-4">
                                        <div class="text-gray-700 font-mono text-sm font-bold whitespace-nowrap">
                                            <i data-lucide="file-search" class="inline h-4 w-4 mr-1"></i> 
                                            Tracking ID: <span id="trackingIdDisplay" class="text-red-600 font-bold">--</span>
                                        </div> 
                                       </div>
                                  <!-- Row 2, Col 2: Land Use Category Preview (hidden for SIT) -->
                                        <div x-show="fileOption !== 'sit'" x-transition>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">
                                                    Land Use (Purpose)
                                                </label>
                                                <div class="flex items-center h-[42px]">
                                                    <span id="landUsePreview"
                                                          class="px-3 py-2 rounded-lg text-sm font-bold inline-flex items-center shadow-sm border transition-all duration-300 ease-in-out transform uppercase"
                                                          :class="{
                                                              'bg-blue-50 text-blue-700 border-blue-200 scale-105 shadow-blue-100': landUse && landUse.toUpperCase().includes('RES'),
                                                              'bg-green-50 text-green-700 border-green-200 scale-105 shadow-green-100': landUse && landUse.toUpperCase().includes('COM'),
                                                              'bg-amber-50 text-amber-700 border-amber-200 scale-105 shadow-amber-100': landUse && landUse.toUpperCase().includes('IND'),
                                                              'bg-purple-50 text-purple-700 border-purple-200 scale-105 shadow-purple-100': landUse && landUse.toUpperCase().includes('AG'),
                                                              'bg-gray-50 text-gray-400 border-gray-200': !landUse
                                                          }"
                                                          x-text="landUseFullText || 'Select prefix'">
                                                    </span>
                                                </div>
                                        </div>
                                            </div>


                                </div>

                                <!-- Right Column -->
                                <div class="space-y-4">


                                    <!-- Middle Prefix (for miscellaneous files) -->
                                    <div x-show="fileOption === 'miscellaneous'">
                                        <label for="middlePrefix" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i data-lucide="tag" class="w-4 h-4 inline mr-1"></i>
                                            Middle Prefix
                                        </label>
                                        <input type="text" id="middlePrefix" name="middle_prefix" x-model="middlePrefix" @input="updatePreview()"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="e.g., KN" value="KN">
                                    </div>


                                </div><!-- end right column -->
                            </div><!-- end main form grid -->


                                    <!-- Location Details + Commissioning side-by-side -->
                                    <div class="grid grid-cols-2 gap-4 mt-4">

                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="flex items-center space-x-2">
                                                <i data-lucide="map" class="w-4 h-4 inline mr-1"></i>
                                                <label class="block text-sm font-semibold text-gray-700">Location Details</label>
                                                <!-- Entry Counter (shown in batch mode) -->
                                                <span x-show="batchMode" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                                                    Entry <span x-text="currentEntryIndex + 1"></span> of <span x-text="batchQuantity"></span>
                                                </span>
                                            </div>
                                            <!-- Add/Remove Controls (shown in batch mode) -->
                                            <div x-show="batchMode" class="flex items-center space-x-2">
                                                <button type="button" @click="previousEntry()" 
                                                        :disabled="currentEntryIndex === 0"
                                                        :class="currentEntryIndex === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                                        class="p-1 rounded-md text-blue-600 transition-colors">
                                                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                                                </button>
                                                <button type="button" @click="nextEntry()" 
                                                        :disabled="currentEntryIndex >= batchQuantity - 1"
                                                        :class="currentEntryIndex >= batchQuantity - 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                                        class="p-1 rounded-md text-blue-600 transition-colors">
                                                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Batch Location Sync Toggle (shown only in batch mode) -->
                                        <div x-show="batchMode" class="flex items-center space-x-2 mb-3 pb-3 border-b border-gray-200">
                                            <input type="checkbox" 
                                                   id="applyToAllBatch" 
                                                   x-model="applyLocationToAll" 
                                                   class="rounded text-blue-600 focus:ring-blue-500">
                                            <label for="applyToAllBatch" class="text-xs font-medium text-gray-600 cursor-pointer">
                                                Apply Location to All Files in Batch
                                            </label>
                                        </div>

                                        <!-- Apply to Batch Button (shown when toggle is enabled) -->
                                        <div x-show="batchMode && applyLocationToAll" class="mb-3">
                                            <button type="button" 
                                                    @click="applyLocationToBatch()" 
                                                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors flex items-center justify-center space-x-2">
                                                <i data-lucide="copy" class="w-4 h-4"></i>
                                                <span>Apply Current Location to All <span x-text="batchQuantity"></span> Files</span>
                                            </button>
                                        </div>

                                        <!-- Location Details Fields -->
                                        <div class="grid grid-cols-2 gap-3">

                                            <!-- TP Number: custom search -->
                                            <div class="relative">
                                                <label class="block text-xs font-medium text-gray-600 mb-1">TP Number</label>
                                                <div class="relative">
                                                    <input type="text"
                                                           id="tp_search_input"
                                                           :value="tpSearchQuery"
                                                           @input="tpSearchQuery = $event.target.value; debounceTpSearch()"
                                                           @keydown.escape="tpSearchOpen = false"
                                                           @keydown.arrow-down.prevent="tpFocusNext()"
                                                           @keydown.arrow-up.prevent="tpFocusPrev()"
                                                           @keydown.enter.prevent="tpSelectFocused()"
                                                           @blur="setTimeout(() => tpSearchOpen = false, 150)"
                                                           autocomplete="off"
                                                           placeholder="Type to search TP no..."
                                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-7">
                                                    <button type="button" x-show="tpNo"
                                                            @click="clearTpNo()"
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">&#215;</button>
                                                </div>
                                                <input type="hidden" name="tp_no" id="generator_tp_no_val" :value="tpNo">
                                                <!-- Dropdown results -->
                                                <div x-show="tpSearchOpen"
                                                     x-cloak
                                                     class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                                    <div x-show="tpSearchLoading" class="px-3 py-2 text-xs text-gray-500">Searching...</div>
                                                    <div x-show="!tpSearchLoading && tpSearchResults.length === 0" class="px-3 py-2 text-xs text-gray-400">No results</div>
                                                    <template x-for="(result, index) in tpSearchResults" :key="result.id">
                                                        <div @mousedown.prevent="selectTpResult(result)"
                                                             :class="tpFocusIndex === index ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50'"
                                                             class="px-3 py-2 text-sm cursor-pointer"
                                                             x-text="result.text">
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- Plot Number: plain text input -->
                                            <div>
                                                <label for="plotNo" class="block text-xs font-medium text-gray-600 mb-1">
                                                    Plot Number
                                                </label>
                                                <input type="text" id="plotNo" name="plot_no"
                                                       :value="batchMode ? locationEntries[currentEntryIndex]?.plotNo : plotNo"
                                                       @input="batchMode ? updateLocationEntry('plotNo', $event.target.value) : plotNo = $event.target.value; buildLocation()"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                       placeholder="Enter plot number">
                                            </div>

                                            <!-- District dropdown + "Other" specify -->
                                            <div id="generatorDistrictWrap" class="relative">
                                                <label for="generator_district" class="block text-xs font-medium text-gray-600 mb-1">
                                                    District
                                                </label>
                                                <select id="generator_district"
                                                        @change="onDistrictChange($event.target.value)"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                                    <option value="">Select District</option>
                                                    @foreach($districts as $dist)
                                                        <option value="{{ $dist->name }}">{{ $dist->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" id="generator_district_other"
                                                       x-show="districtIsOther"
                                                       x-cloak
                                                       @input="onDistrictOtherInput($event.target.value)"
                                                       placeholder="Please specify district..."
                                                       class="w-full mt-2 px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <input type="hidden" name="district" :value="district">
                                            </div>

                                            <!-- LGA -->
                                            <div>
                                                <label for="generator_lga" class="block text-xs font-medium text-gray-600 mb-1">
                                                    LGA
                                                </label>
                                                <select id="generator_lga" name="lga"
                                                        @change="onLgaChange($event.target.value)"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                                    <option value="">Select LGA</option>
                                                    @foreach($lgas as $lga)
                                                        <option value="{{ $lga->name }}">{{ $lga->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <!-- Location: auto-built from plot + district + lga (readonly) -->
                                            <div class="col-span-2">
                                                <label for="generator_location" class="block text-xs font-medium text-gray-600 mb-1">
                                                    Location <span class="text-gray-400 font-normal">(auto-filled)</span>
                                                </label>
                                                <input type="text" id="generator_location" name="location"
                                                       :value="batchMode ? locationEntries[currentEntryIndex]?.location : location"
                                                       readonly
                                                       class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-600 cursor-default"
                                                       placeholder="Filled from plot number, district & LGA">
                                            </div>

                                        </div>

                                        <!-- Entry Progress Indicator (shown in batch mode) -->
                                        <div x-show="batchMode" class="mt-3 pt-3 border-t border-gray-300 hidden">
                                            <div class="flex items-center justify-between text-xs text-gray-600">
                                                <span>Progress: <span x-text="filledEntriesCount"></span> of <span x-text="batchQuantity"></span> entries filled</span>
                                                <div class="flex items-center space-x-1">
                                                    <template x-for="i in parseInt(batchQuantity)" :key="i">
                                                        <div class="w-2 h-2 rounded-full" 
                                                             :class="isEntryFilled(i-1) ? 'bg-green-500' : 'bg-gray-300'"></div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Commissioning Metadata Grid -->
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="space-y-4">
                                            <!-- Top Row: Time and Date -->
                                            <div class="grid grid-cols-2 gap-4">
                                                <!-- Commission Time -->
                                                <div>
                                                    <label for="commissionTime" class="block text-xs font-semibold text-gray-600 mb-1">
                                                        <i data-lucide="clock" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                                        Commissioning Time
                                                    </label>
                                                     <input type="time" id="commissionTime" name="commission_time"
                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent bg-white sync-time text-sm"
                                                            placeholder="Auto-filled" value="{{ date('H:i') }}">
                                                </div>

                                                <!-- Commission Date -->
                                                <div>
                                                    <label for="commissionDate" class="block text-xs font-semibold text-gray-600 mb-1">
                                                        <i data-lucide="calendar-check" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                                        Commissioning Date
                                                    </label>
                                                     <input type="date" id="commissionDate" name="commission_date"                 
                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent bg-white sync-time text-sm"
                                                            placeholder="Auto-filled" value="{{ date('Y-m-d') }}">
                                                </div>
                                            </div>

                                            <!-- Bottom Row: Commissioned By -->
                                            <div>
                                                <label for="commissionedBy" class="block text-xs font-semibold text-gray-600 mb-1">
                                                    <i data-lucide="user-check" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                                    Commissioned By
                                                </label>
                                                 <input type="text" id="commissionedBy" name="commissioned_by"  
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent bg-white text-sm"
                                                        placeholder="Auto-filled" value="{{ Auth::user()->name }}">
                                            </div>
                                        </div>
                                    </div>

                                    </div><!-- end location+commissioning grid -->
                                    
                                    <!-- Hidden Related File Data -->
                                    <input type="hidden" name="related_fileno" x-model="relatedFileNo">
                                    <input type="hidden" name="related_file_indexing_id" x-model="relatedFileIndexingId">
                                    <input type="hidden" name="related_file_title" x-model="relatedFileTitle">

                            <!-- Form Actions -->
                            <div class="flex justify-between border-t border-gray-200 mt-4">
                                <button type="button" onclick="showOverrideModal()" 
                                        id="overrideButton"
                                        disabled
                                        class="px-4 py-2 bg-orange-600 text-white rounded-md transition-colors flex items-center space-x-2 opacity-50 cursor-not-allowed disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                    <span>Override</span>
                                </button>  
                                <button type="button" onclick="closeGenerateModal()" 
                                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                
                                <button type="submit" 
                                        id="generateButton"
                                        disabled
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md transition-colors flex items-center space-x-2 opacity-50 cursor-not-allowed disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    <span>Generate</span>
                                </button>
                            </div>

                            </div><!-- end x-show !isOpFormHidden wrapper -->

                            <!-- OP capture pending message (shown when OP is selected but not yet captured) -->
                            <div x-show="isOpFormHidden" x-cloak x-transition.opacity.duration.200ms class="flex flex-col items-center justify-center p-8 text-center bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 min-h-[250px] overflow-hidden">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                                    <i data-lucide="info" class="w-8 h-8 text-blue-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-2">Capture Occupancy Permit First</h4>
                                <p class="text-gray-500 max-w-md mx-auto text-sm">Please select <strong>New Applications (Existing OP)</strong> above, then capture/select an Occupancy Permit (OP) record to continue with the commissioning process.</p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Batch Summary Modal -->
            <div id="batchSummaryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-[60]">
                <div class="relative top-10 mx-auto p-6 border w-[700px] max-w-3xl shadow-2xl rounded-lg bg-white">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 flex items-center space-x-2">
                                    <i data-lucide="layers" class="w-6 h-6 text-blue-600"></i>
                                    <span>Batch Generation Summary</span>
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">Review the details before generating files</p>
                            </div>
                            <button onclick="closeBatchSummaryModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i data-lucide="x" class="w-6 h-6"></i>
                            </button>
                        </div>

                        <!-- Summary Content -->
                        <div class="space-y-4">
                            <!-- Batch Overview -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1">Batch Size</p>
                                        <p class="text-lg font-semibold text-gray-900" id="summaryBatchSize">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1">Serial Number Range</p>
                                        <p class="text-lg font-semibold text-blue-600" id="summarySerialRange">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1 uppe">Land Use</p>
                                        <p class="text-lg font-semibold text-gray-900" id="summaryLandUse">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1">File Name</p>
                                        <p class="text-lg font-semibold text-gray-900" id="summaryFileName">-</p>
                                    </div>
                                </div>
                            </div>

                            <!-- File Number Preview -->
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-600 mb-2">File Numbers to be Generated</p>
                                <p class="text-sm font-mono text-green-600" id="summaryFileNumbers">-</p>
                            </div>

                            <!-- Location Details List -->
                            <div class="bg-white border border-gray-200 rounded-lg">
                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg">
                                    <h4 class="text-sm font-semibold text-gray-700">Location Details</h4>
                                </div>
                                <div class="max-h-64 overflow-y-auto p-4" id="summaryLocationList">
                                    <!-- Location entries will be populated here -->
                                </div>
                            </div>

                            @include('generate_fileno.partials.summary_destination')

                            <!-- Warning Message -->
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            This action will generate <strong id="summaryTotalFiles">0</strong> file numbers. 
                                            Please review all details carefully before confirming.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button type="button" onclick="closeBatchSummaryModal()" 
                                    class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="button" onclick="confirmBatchGeneration()" 
                                    id="confirmBatchButton"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center space-x-2">
                                <i data-lucide="check" class="w-4 h-4"></i>
                                <span>Confirm & Generate</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Override Modal -->
            <div id="overrideModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Override File Number</h3>
                            <button onclick="closeOverrideModal()" class="text-gray-400 hover:text-gray-600">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <!-- Override Form -->
                        <form id="overrideForm" onsubmit="submitOverrideForm(event)">
                            @csrf

                            <!-- Manual Year -->
                            <div class="mb-4">
                                <label for="overrideYear" class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                                <input type="number" id="overrideYear" name="override_year" 
                                       value="{{ date('Y') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       >
                            </div>

                            <!-- Manual Serial Number -->
                            <div class="mb-4">
                                <label for="overrideSerialNo" class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                                <input type="number" id="overrideSerialNo" name="override_serial_no" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       min="1" max="9999">
                            </div>

                            <!-- Extension Option -->
                            <div class="mb-4" style="display:none;">
                                <label class="flex items-center">
                                    <input type="checkbox" id="overrideExtension" name="override_extension" class="mr-2">
                                    <span>File Extension</span>
                                </label>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeOverrideModal()" 
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                                    Apply Override
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Migration Modal -->
            <div id="migrationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Migrate Data from Excel</h3>
                            <button onclick="closeMigrationModal()" class="text-gray-400 hover:text-gray-600">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <!-- Migration Form -->
                        <form id="migrationForm" onsubmit="submitMigrationForm(event)" enctype="multipart/form-data">
                            @csrf

                            <!-- File Upload -->
                            <div class="mb-4">
                                <label for="excelFile" class="block text-sm font-medium text-gray-700 mb-2">CSV File</label>
                                <input type="file" id="excelFile" name="excel_file" 
                                       accept=".csv,.txt"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Upload CSV file with columns: mlsfNo, kangisFile, NewKANGISFileNo, FileName (ignore SN column)</p>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeMigrationModal()" 
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    Migrate Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-[80]">
                <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Edit</h3>
                            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <!-- Edit Form -->
                        <form id="editForm" onsubmit="submitEditForm(event)">
                            @csrf
                            @method('PUT')
                            <input type="hidden" id="editId" name="id">
                            <input type="hidden" id="editEntity" name="entity">

                            <!-- MLSF Number (Read-only) -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">MLSF Number</label>
                                <input type="text" id="editMlsfNo" 
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md"
                                       readonly>
                            </div>


                             <!-- Customer Type -->
                              <div class="mb-4">
                                 <label class="block text-sm font-medium text-gray-700 mb-2">Customer Type</label>
                                 <select id="editCustomerType" name="customer_type" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase">
                                     <option value="">Select Customer Type</option>
                                     <option value="Individual">Individual</option>
                                     <option value="Corporate">Corporate</option>
                                     <option value="Multiple">Multiple</option>
                                 </select> 
                              </div>

                              <!-- Purpose -->
                              <div class="mb-4">
                                  <label for="editPurpose" class="block text-sm font-medium text-gray-700 mb-2">Purpose</label>
                                  <select id="editPurpose" name="purpose_id" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase">
                                      <option value="">Select Purpose</option>
                                      @foreach(\App\Models\Purpose::all() as $p)
                                          <option value="{{ $p->id }}">{{ $p->name }}</option>
                                      @endforeach
                                  </select>
                              </div>

                            <!-- File Name (Editable) -->
                            <div class="mb-4">
                                <label for="editFileName" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="file-text" class="w-4 h-4 inline mr-1"></i>
                                    File Name
                                </label>
                                <input type="text" id="editFileName" name="file_name" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                       placeholder="Enter file name" required>
                            </div>

                            <!-- Plot Number -->
                            <div class="mb-4">
                                <label for="editPlotNo" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="map-pin" class="w-4 h-4 inline mr-1"></i>
                                    Plot Number
                                </label>
                                <input type="text" id="editPlotNo" name="plot_no" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                       placeholder="Enter plot number">
                            </div>

                            <!-- TP Number -->
                            <div class="mb-4">
                                <label for="editTpNo" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                                    TP Number
                                </label>
                                <input type="text" id="editTpNo" name="tp_no" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                       placeholder="Enter TP number">
                            </div>

                            <!-- Location -->
                            <div class="mb-4">
                                <label for="editLocation" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="map" class="w-4 h-4 inline mr-1"></i>
                                    Location
                                </label>
                                <input type="text" id="editLocation" name="location" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                       placeholder="Enter location" >
                            </div>

                            <!-- Location Details Grid (LGA & District) 
  --> 
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <!-- LGA Dropdown -->
                                <div>

                            
                                    <label for="editLga" class="block text-sm font-medium text-gray-700 mb-2">
                                        LGA
                                    </label>
                                    <select id="editLga" name="lga" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase">
                                        <option value="">Select LGA</option>
                                        @foreach($lgas as $lga)
                                            <option value="{{ $lga->name }}">{{ $lga->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- District Dropdown -->
                                <div id="editDistrictWrap" class="relative">
                                    <label for="editDistrict" class="block text-sm font-medium text-gray-700 mb-2">
                                        District
                                    </label>
                                    <select id="editDistrict" onchange="onEditDistrictChange(this.value)"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase">
                                        <option value="">Select District</option>
                                        @foreach($districts as $district)
                                            <option value="{{ $district->name }}">{{ $district->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" id="editDistrictOther" style="display:none;"
                                           oninput="onEditDistrictOtherInput(this.value)"
                                           placeholder="Please specify district..."
                                           class="w-full mt-2 px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase">
                                    <input type="hidden" id="editDistrictValue" name="district">
                                </div>
                            </div>

                            <!-- Phone Number of Applicant -->
                            <div class="mb-4">
                                <label for="editPhoneNo" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="phone" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                                    Phone No of Applicant
                                </label>
                                <input type="text" id="editPhoneNo" name="phone_no" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter Applicant Phone No">
                            </div>

                            <!-- Address of Applicant -->
                            <div class="mb-4">
                                <label for="editAddress" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="home" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                                    Address of Applicant
                                </label>
                                <input type="text" id="editAddress" name="address" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                       placeholder="Enter Applicant Address">
                            </div>

                            <!-- Details of local Rep (section) -->
                            <div id="editLocalRepSection" class="mb-4 p-4 border border-blue-200 bg-blue-50 rounded-lg hidden">
                                <h4 class="text-sm font-semibold text-blue-800 mb-3 border-b border-blue-200 pb-2">Details of Local Rep</h4>
                                
                                <div class="space-y-3">
                                    <!-- Rep Phone -->
                                    <div>
                                        <label for="editRepPhoneNo" class="block text-xs font-medium text-gray-700 mb-1">
                                            <i data-lucide="phone" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                            Phone No
                                        </label>
                                        <input type="text" id="editRepPhoneNo" name="rep_phone_no" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                               placeholder="Enter Local Rep Phone No">
                                    </div>

                                    <!-- Rep Address -->
                                    <div>
                                        <label for="editRepAddress" class="block text-xs font-medium text-gray-700 mb-1">
                                            <i data-lucide="home" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                            Address
                                        </label>
                                        <input type="text" id="editRepAddress" name="rep_address" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm uppercase"
                                               placeholder="Enter Local Rep Address">
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeEditModal()" 
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Direct Allocation Modal -->
            <div id="directAllocationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-[80]">
                <div class="relative top-20 mx-auto p-5 border w-[500px] shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Direct Allocation</h3>
                            <button onclick="closeDirectAllocationModal()" class="text-gray-400 hover:text-gray-600">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <!-- Form -->
                        <form id="directAllocationForm" onsubmit="submitDirectAllocationForm(event)">
                            @csrf
                            @method('PUT')
                            <input type="hidden" id="daEditId" name="id">

                            <!-- Plot Number -->
                            <div class="mb-4">
                                <label for="daPlotNo" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="map-pin" class="w-4 h-4 inline mr-1"></i>
                                    Plot Number
                                </label>
                                <input type="text" id="daPlotNo" name="plot_no" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent uppercase"
                                       placeholder="Enter plot number">
                            </div>


                            <!-- Location Details Grid (LGA & District) -->
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <!-- LGA Dropdown -->
                                <div>
                                    <label for="daLga" class="block text-sm font-medium text-gray-700 mb-2">
                                        LGA
                                    </label>
                                    <select id="daLga" name="lga" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 uppercase">
                                        <option value="">Select LGA</option>
                                        @foreach($lgas as $lga)
                                            <option value="{{ $lga->name }}">{{ $lga->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- District Dropdown -->
                                <div id="daDistrictWrap" class="relative">
                                    <label for="daDistrict" class="block text-sm font-medium text-gray-700 mb-2">
                                        District
                                    </label>
                                    <select id="daDistrict" onchange="onDaDistrictChange(this.value)"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 uppercase">
                                        <option value="">Select District</option>
                                        @foreach($districts as $district)
                                            <option value="{{ $district->name }}">{{ $district->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" id="daDistrictOther" style="display:none;"
                                           oninput="onDaDistrictOtherInput(this.value)"
                                           placeholder="Please specify district..."
                                           class="w-full mt-2 px-3 py-2 border border-green-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 uppercase">
                                    <input type="hidden" id="daDistrictValue" name="district">
                                </div>
                            </div>


                            
                            <!-- Location -->
                            <div class="mb-4">
                                <label for="daLocation" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="map" class="w-4 h-4 inline mr-1"></i>
                                    Location
                                </label>
                                <input type="text" id="daLocation" name="location" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent uppercase"
                                       placeholder="Enter location">
                            </div>

                        

                            <!-- District / Location after LGA -->
                            <!-- <div class="mb-4">
                                <label for="daDistrict" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="map-pin" class="w-4 h-4 inline mr-1"></i>
                                    District
                                </label>
                                <input type="text" id="daDistrict" name="district"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent uppercase"
                                       placeholder="Enter district">
                            </div> -->

                            <!-- Phone Number -->
                            <div class="mb-4">
                                <label for="daPhoneNo" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="phone" class="w-4 h-4 inline mr-1"></i>
                                    Phone Number
                                </label>
                                <input type="text" id="daPhoneNo" name="phone_no" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       placeholder="Enter phone number">
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="button" onclick="closeDirectAllocationModal()" 
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    Update Allocation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- File Commissioning Sheet Modal -->
            <div id="commissioningSheetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-semibold text-gray-900">File Number Commissioning Sheet</h3>
                            <button onclick="closeCommissioningSheetModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i data-lucide="x" class="w-6 h-6"></i>
                            </button>
                        </div>

                        <!-- Form Container -->
                        <form id="commissioningSheetForm" onsubmit="submitCommissioningSheet(event)">
                            @csrf

                            <!-- Ministry Header -->
                            <div class="text-center mb-6 border-b pb-4">
                                <h2 class="text-lg font-bold text-gray-800">Ministry of Land & Physical Planning</h2>
                                <h3 class="text-base font-medium text-gray-700">DEPARTMENT OF LAND</h3>
                                <h4 class="text-base font-medium text-gray-600 mt-2">File Commissioning Sheet</h4>
                            </div>

                            <!-- Data Load Status -->
                            <div id="dataLoadStatus" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md hidden">
                                <div class="flex items-center">
                                    <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2"></i>
                                    <span class="text-sm text-blue-800">Data loaded from selected file number record</span>
                                </div>
                            </div>

                            <!-- Form Fields Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <!-- File Number -->
                                <div class="md:col-span-1">
                                    <label for="cs_file_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        File No:
                                    </label>
                                    <input type="text" id="cs_file_number" name="file_number"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Enter file number" oninput="updateCommissioningFileType()">
                                    <!-- Actual record source (mls_file_no.source) shown after the File No on the printed sheet -->
                                    <input type="hidden" id="cs_source" name="source">
                                    <span id="cs_file_type_badge" class="hidden mt-1 inline-block px-2 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-800"></span>
                                </div>

                                <!-- File Name -->
                                <div class="md:col-span-1">
                                    <label for="cs_file_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        File Name:
                                    </label>
                                    <input type="text" id="cs_file_name" name="file_name" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Enter file name"
                                           oninput="document.getElementById('cs_name_allottee').value = this.value" required>
                                </div>

                                <!-- Allottee -->
                                <div class="md:col-span-2">
                                    <label for="cs_name_allottee" class="block text-sm font-medium text-gray-700 mb-2">
                                        Allottee:
                                    </label>
                                    <input type="text" id="cs_name_allottee" name="name_or_allottee" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50"
                                           placeholder="Auto-filled from file name" readonly>
                                </div>

                                <!-- Plot Number -->
                                <div class="md:col-span-1">
                                    <label for="cs_plot_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        Plot No:
                                    </label>
                                    <input type="text" id="cs_plot_number" name="plot_number" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Enter plot number">
                                </div>

                                <!-- TP Number -->
                                <div class="md:col-span-1">
                                    <label for="cs_tp_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        TP No:
                                    </label>
                                    <input type="text" id="cs_tp_number" name="tp_number" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Enter TP number">
                                </div>



                                <!-- Location -->
                                <div class="md:col-span-2">
                                    <label for="cs_location" class="block text-sm font-medium text-gray-700 mb-2">
                                        Location:
                                    </label>
                                    <textarea id="cs_location" name="location" rows="2"
                                             class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                             placeholder="Enter location" ></textarea>
                                </div>




                                <!-- LGA input text -->
                                <div class="md:col-span-2">
                                    <label for="cs_lga" class="block text-sm font-medium text-gray-700 mb-2">
                                        LGA:
                                    </label>
                                    <input type="text" id="cs_lga" name="lga" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Enter LGA" >
                                </div>
         <!-- Time Commissioned -->
                                <div class="md:col-span-1">
                                    <label for="cs_time_created" class="block text-sm font-medium text-gray-700 mb-2">
                                        Time Commissioned:
                                    </label>
                                    <input type="time" id="cs_time_created" name="time_created" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 sync-time"
                                           value="{{ date('H:i') }}">
                                </div>

                                <!-- Date Commissioned -->
                                <div class="md:col-span-1">
                                    <label for="cs_date_created" class="block text-sm font-medium text-gray-700 mb-2">
                                        Date Commissioned:
                                    </label>
                                    <input type="date" id="cs_date_created" name="date_created" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 sync-time"
                                           value="{{ date('Y-m-d') }}">
                                </div>



                                <!-- Created By -->
                                <div class="md:col-span-1">
                                    <label for="cs_created_by" class="block text-sm font-medium text-gray-700 mb-2">
                                        Commissioned by:
                                    </label>
                                    <input type="text" id="cs_created_by" name="created_by" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Enter creator name"
                                           value="{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}">
                                </div>

                                <!-- Tracking ID (Hidden) -->
                                <input type="hidden" id="cs_tracking_id" name="tracking_id">
                            </div>

                            <!-- Signatures Section -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 border-t pt-6">
                                <div class="text-center">
                                    <label class="block text-sm font-medium text-gray-700 mb-4">
                                        Created by Signature
                                    </label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 h-24 flex items-center justify-center">
                                        <span class="text-gray-500 text-sm">Signature area</span>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <label class="block text-sm font-medium text-gray-700 mb-4">
                                        Approved by Signature
                                    </label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 h-24 flex items-center justify-center">
                                        <span class="text-gray-500 text-sm">Signature area</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-3 border-t pt-6">
                                <button type="button" onclick="closeCommissioningSheetModal()" 
                                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i data-lucide="x" class="w-4 h-4 mr-2"></i>
                                    Cancel
                                </button>
                                <button type="button" onclick="generateAndPrintCommissioningSheet()" 
                                        class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                    <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                                    Generate & Print
                                </button>
                                <!-- <button type="submit" 
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                    <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                    Save Draft
                                </button> -->
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Merger Selection Modal -->
            <div id="mergerFileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-[100]">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-4 border-b pb-3">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <i data-lucide="merge" class="w-6 h-6 text-orange-600"></i>
                                Select Approved Merger File
                            </h3>
                            <button onclick="closeMergerFileModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i data-lucide="x" class="w-6 h-6"></i>
                            </button>
                        </div>

                        <div class="mb-4">
                            <div class="relative">
                                <input type="text" id="mergerSearchInput" 
                                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                       placeholder="Search by Temp File No, Global File No or Applicant Name..."
                                       onkeyup="searchMergerFiles(this.value)">
                                <div class="absolute left-3 top-3 text-gray-400">
                                    <i data-lucide="search" class="w-5 h-5"></i>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Temp File No</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">File No</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Applicant / Title</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="mergerResultsTable" class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                            <div class="flex flex-col items-center gap-2">
                                                <i data-lucide="loader" class="w-8 h-8 animate-spin text-orange-500"></i>
                                                <span>Loading approved applications...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
            
                </div>
            </div>

            <!-- Change of Purpose Selection Modal -->
            <div id="copFileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-[101]">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-4 border-b pb-3">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <i data-lucide="refresh-cw" class="w-6 h-6 text-blue-600"></i>
                                Select Approved Change of Purpose
                            </h3>
                            <button onclick="closeCopFileModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i data-lucide="x" class="w-6 h-6"></i>
                            </button>
                        </div>

                        <div class="mb-4">
                            <div class="relative">
                                <input type="text" id="copSearchInput" 
                                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       placeholder="Search by File No, Applicant Name or Purpose..."
                                       onkeyup="searchCopFiles(this.value)">
                                <div class="absolute left-3 top-3 text-gray-400">
                                    <i data-lucide="search" class="w-5 h-5"></i>
                                </div>
                            </div>
                        </div>

                        <div id="copSearchResults" class="max-h-[400px] overflow-y-auto border border-gray-200 rounded-lg p-2 bg-gray-50">
                            <div class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <i data-lucide="search" class="w-8 h-8 text-gray-300"></i>
                                    <span>Search for approved applications...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <style>
            /* Tab Styling */
            .tab-button {
                background-color: transparent;
                position: relative;
                border: none;
                outline: none;
            }

            .tab-button.active {
                background-color: white;
                color: #2563eb;
                box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
            }

            .tab-button.active .absolute {
                background-color: #2563eb !important;
            }

            .tab-button:not(.active) {
                color: #6b7280;
                background-color: #f9fafb;
            }

            .tab-button:not(.active):hover {
                color: #374151;
                background-color: #f3f4f6;
            }

            .tab-button:not(.active) .absolute {
                background-color: transparent !important;
            }

            .tab-content {
                animation: fadeIn 0.3s ease-in;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Table Cell Styling */
            #mlsfTable td {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 200px;
            }

            #mlsfTable td:hover {
                overflow: visible;
                white-space: normal;
            }

            /* File Number Badge Styles */
            .file-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.375rem 0.75rem;
                border-radius: 0.5rem;
                font-size: 0.813rem;
                font-weight: 600;
                font-family: 'Monaco', 'Courier New', monospace;
                letter-spacing: -0.5px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                transition: all 0.2s;
            }

            .file-badge:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .badge-res {
                background-color: #dbeafe;
                color: #1e40af;
                border: 1px solid #93c5fd;
            }

            .badge-com {
                background-color: #d1fae5;
                color: #065f46;
                border: 1px solid #6ee7b7;
            }

            .badge-ind {
                background-color: #fef3c7;
                color: #92400e;
                border: 1px solid #fcd34d;
            }

            .badge-ag {
                background-color: #fce7f3;
                color: #831843;
                border: 1px solid #f9a8d4;
            }

            .badge-default {
                background-color: #e5e7eb;
                color: #374151;
                border: 1px solid #d1d5db;
            }


        </style>



    <script>
        async function syncInputsWithNetworkTime() {
            try {
                // Fetch current time based on User's IP address
                const response = await fetch('http://worldtimeapi.org/api/timezone/Africa/Lagos');
                const data = await response.json();

                // networkDate is now independent of the local PC clock
                const networkDate = new Date(data.datetime);

                // Format components (ensure leading zeros for HTML compliance)
                const yyyy = networkDate.getFullYear();
                const mm = String(networkDate.getMonth() + 1).padStart(2, '0');
                const dd = String(networkDate.getDate()).padStart(2, '0');
                const hh = String(networkDate.getHours()).padStart(2, '0');
                const min = String(networkDate.getMinutes()).padStart(2, '0');

                const dateStr = `${yyyy}-${mm}-${dd}`; // YYYY-MM-DD
                const timeStr = `${hh}:${min}`;        // HH:MM
                const fullStr = `${dateStr}T${timeStr}`; // YYYY-MM-DDTHH:MM

                // Find all inputs and apply the correct values
                document.querySelectorAll('input.sync-time').forEach(input => {
                    if (input.type === 'date') input.value = dateStr;
                    if (input.type === 'time') input.value = timeStr;
                    if (input.type === 'datetime-local') input.value = fullStr;
                });

                console.log("Inputs synchronized with network time.");
            } catch (error) {
                console.error("Network time fetch failed. Using local fallback.", error);
            }
        }

        // Run sync on page load
        window.addEventListener('load', syncInputsWithNetworkTime);
    </script>

    {{-- Reuse the same OP capture modal used in Lands One Stop Shop Applications page --}}
    @include('instruments.partials.register_modal')
    @include('components.global-fileno-modal')
    <template id="template-occupancy-permit">@include('instruments.partials.types.occupancy-permit')</template>

    {{-- Batch Capture OP — shared with the OSS Applications page. Opened by
         openCommissionOpCaptureModal (mls_js) when the OP type is captured as a batch;
         the single-record TOT-link mode is unused here (that flow is OSS-only). --}}
    @include('lands_one_stop_shop.partials.capture-op-card')

    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/instruments-capture.js') }}?v={{ time() }}"></script>

    <!-- Extension Type Selection Modal (File Extension vs Plot Extension) -->
    <div id="extensionTypeModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden overflow-y-auto h-full w-full z-[70]">
        <div class="relative top-32 mx-auto p-6 border w-full max-w-md shadow-xl rounded-lg bg-white">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i data-lucide="git-branch" class="w-5 h-5 inline mr-1 text-blue-600"></i>
                    Select Extension Type
                </h3>
                <button type="button" onclick="cancelExtensionTypeSelection()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-4">Choose how this extension should be processed.</p>
            <div class="space-y-3">
                <button type="button" onclick="selectExtensionType('file')"
                        class="w-full text-left p-4 rounded-lg border-2 border-gray-200 hover:border-blue-500 hover:bg-blue-50 transition-colors group">
                    <div class="flex items-start gap-3">
                        <i data-lucide="file-plus" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                        <div>
                            <div class="text-sm font-semibold text-gray-900 group-hover:text-blue-700">File Extension</div>
                            <div class="text-xs text-gray-500 mt-0.5">Generates a new file number (<span class="font-mono">… AND EXTENSION</span>). Existing workflow.</div>
                        </div>
                    </div>
                </button>
                <button type="button" onclick="selectExtensionType('plot')"
                        class="w-full text-left p-4 rounded-lg border-2 border-gray-200 hover:border-rose-500 hover:bg-rose-50 transition-colors group">
                    <div class="flex items-start gap-3">
                        <i data-lucide="map-pin" class="w-5 h-5 text-rose-600 mt-0.5"></i>
                        <div>
                            <div class="text-sm font-semibold text-gray-900 group-hover:text-rose-700">Plot Extension</div>
                            <div class="text-xs text-gray-500 mt-0.5">Keeps the original file number. Uses an existing file and saves a separate Plot Extension transaction.</div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- Printer Manager Modal (Moved to Root-ish Level) -->
    <div id="printerManagerModal" class="fixed inset-0 bg-gray-900 bg-opacity-80 hidden overflow-y-auto h-full w-full z-50">
        <!-- Modal Panel -->
        <div id="pmModalCard" class="relative top-10 mx-auto p-6 border w-full max-w-md shadow-2xl rounded-xl bg-white mb-20 text-left border-gray-200">
            
            <!-- Close Button -->
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button type="button" onclick="closePrinterManager()" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-lg hover:bg-gray-100">
                    <span class="sr-only">Close</span>
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="text-center">
                <div class="flex items-center justify-center w-16 h-16 mx-auto bg-blue-100 rounded-full mb-4 border-4 border-white shadow-sm">
                    <i data-lucide="printer" class="w-8 h-8 text-blue-600"></i>
                </div>
                <h3 class="text-2xl font-black text-gray-900 tracking-tight" id="modal-title" style="margin: 0;">Printer Manager</h3>
                <div class="mt-3 px-4 py-2 bg-blue-50 rounded-xl inline-flex flex-col items-center">
                    <p class="text-sm text-blue-800 m-0 font-medium">File No: <span id="pmRefDisplay" class="font-black text-blue-600">--</span></p>
                    <p class="text-[10px] uppercase font-bold tracking-widest text-blue-500 mt-1" id="pmBatchContainer">Batch: <span id="pmBatchDisplay">--</span></p>
                </div>
            </div>

            <div class="mt-8 space-y-6">
                
                <!-- Print Mode Selection -->
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 mb-3 ml-1">
                        Select Printing Scope
                    </label>
                    <div class="flex p-1.5 bg-gray-100 rounded-2xl" role="group">
                        <button type="button" id="pmModeIndividual" onclick="setPrintMode('Individual')"
                            class="flex-1 px-4 py-3 text-sm font-black rounded-xl transition-all duration-200">
                            <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i> Single
                        </button>
                        <button type="button" id="pmModeBatch" onclick="setPrintMode('Batch')"
                            class="flex-1 px-4 py-3 text-sm font-black rounded-xl transition-all duration-200 ml-1">
                            <i data-lucide="files" class="w-4 h-4 inline mr-2"></i> Whole Batch
                        </button>
                    </div>
                </div>

                <div id="pmBatchSelectorContainer" class="hidden">
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 mb-3 ml-1">
                        Batch Section ID
                    </label>
                    <div class="relative">
                        <select id="pmBatchSelect" onchange="onBatchSelectionChange()"
                            class="block w-full py-4 pl-5 pr-12 text-sm font-bold border-gray-100 rounded-2xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white shadow-sm appearance-none cursor-pointer">
                            <option value="">Select an unprinted batch...</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2">Printed batches are automatically removed from this list.</p>
                </div>

                <!-- Document Type Selection -->
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 mb-3 ml-1">
                        Document to Generate
                    </label>
                    <div class="relative">
                        <select id="pmDocType" onchange="checkPrintStatus()" 
                            class="block w-full py-4 pl-5 pr-12 text-sm font-bold border-gray-100 rounded-2xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white shadow-sm appearance-none cursor-pointer">
                            <option value="Commissioning Sheet">📄 Commissioning Sheet</option>
                            <option value="Application for Conversion">🔄 Application for Conversion</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </div>
                </div>

                <!-- Status Display -->
                <div class="p-5 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200 hidden" id="pmStatusContainer">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-tighter">Usage</span>
                        <span class="text-sm font-black px-3 py-1 bg-white border border-gray-100 rounded-lg text-gray-900 shadow-sm" id="pmCountDisplay">--/2</span>
                    </div>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-tighter">Availability</span>
                        <span id="pmStatusBadge" class="inline-flex items-center px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm">
                            Checking...
                        </span>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="mt-4 pt-2">
                    <button type="button" id="pmExecuteBtn" onclick="executePrintProtocol()" disabled
                        class="w-full py-5 px-6 bg-blue-600 text-white font-black text-base rounded-2xl hover:bg-blue-700 disabled:bg-gray-100 disabled:text-gray-300 transition-all shadow-xl shadow-blue-100 flex items-center justify-center gap-3 active:scale-95 border-b-4 border-blue-800">
                        <i data-lucide="send" class="w-6 h-6"></i>
                        <span id="pmBtnText">Verifying Protocol...</span>
                    </button>
                    <!--<div class="mt-6 flex items-center justify-center space-x-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                        <span class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400">Secure Server Link Active</span>
                    </div> -->
                </div>
            </div>
        </div>
    </div>
    @include('components.global-fileno-modal')

    <!-- ===== Batch Print Modal ===== -->
    <div id="batchPrintModal" class="fixed inset-0 bg-gray-900 bg-opacity-80 hidden overflow-y-auto h-full w-full z-[60]">
        <div class="relative top-16 mx-auto p-6 border w-full max-w-lg shadow-2xl rounded-xl bg-white mb-20 text-left border-gray-200">

            <!-- Close -->
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button type="button" onclick="closeBatchPrintModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Header -->
            <div class="flex items-center space-x-3 mb-6">
                <div class="flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-full border-4 border-white shadow-sm">
                    <i data-lucide="printer" class="w-6 h-6 text-emerald-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Batch Print — Commissioning Sheets</h3>
                    <p class="text-sm text-gray-500">Select a date to generate a multi-page PDF of sheets</p>
                </div>
            </div>

            <!-- Date Selector -->
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Commissioning Date</label>
                    <div class="flex space-x-2">
                        <input type="date" id="bpDateSelect" onchange="onBatchPrintDateChange()"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <!-- Count info boxes -->
                <div id="bpCountInfo" class="hidden space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-center">
                            <span class="block text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-1">Total</span>
                            <span id="bpTotalCount" class="text-2xl font-black text-blue-700">0</span>
                        </div>
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-center">
                            <span class="block text-[10px] font-bold text-amber-500 uppercase tracking-wider mb-1">Printed</span>
                            <span id="bpPrintedCount" class="text-2xl font-black text-amber-700">0</span>
                        </div>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center ring-2 ring-emerald-500/10">
                            <span class="block text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Unprinted</span>
                            <span id="bpUnprintedCount" class="text-2xl font-black text-emerald-700">0</span>
                        </div>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2.5 flex items-center space-x-2">
                        <i data-lucide="info" class="w-4 h-4 text-emerald-600 flex-shrink-0"></i>
                        <span class="text-xs text-emerald-800 font-medium">
                            Note: The <strong id="bpRemainingText" class="text-emerald-900">0</strong> unprinted sheet(s) will be generated for this date.
                        </span>
                    </div>
                    <div id="bpConversionNote" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2.5 flex items-center space-x-2">
                        <i data-lucide="file-text" class="w-4 h-4 text-indigo-600 flex-shrink-0"></i>
                        <span class="text-xs text-indigo-800 font-medium">
                            Conversion (CON-) files: <strong id="bpConvTotalText" class="text-indigo-900">0</strong> total,
                            <strong id="bpConvPrintedText" class="text-indigo-900">0</strong> printed,
                            <strong id="bpConvUnprintedText" class="text-indigo-900">0</strong> awaiting an Application for Conversion.
                        </span>
                    </div>
                </div>

                <!-- No batches notice -->
                <div id="bpNoBatchesNotice" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 flex items-center space-x-2">
                    <i data-lucide="info" class="w-4 h-4 text-amber-600 flex-shrink-0"></i>
                    <span class="text-sm text-amber-800">No commissioning sheets found for the selected date.</span>
                </div>

                <!-- Documents to generate -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Documents to generate</label>
                    <div class="space-y-2">
                        <label class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" id="bpDocCommissioning" checked
                                class="mt-0.5 w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Commissioning Sheets</span>
                                <span class="block text-xs text-gray-500">The standard batch commissioning sheet for every file on this date.</span>
                            </span>
                        </label>
                        <label class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" id="bpDocConversion"
                                class="mt-0.5 w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Application for Conversion</span>
                                <span class="block text-xs text-gray-500">For Conversion (CON-) files only. You will review the recipient LGA before it generates.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-100">
                <button onclick="closeBatchPrintModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button id="bpPrintBtn" onclick="executeBatchPrint()" disabled
                    class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed flex items-center space-x-2 transition-colors">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Generate Selected Documents</span>
                </button>
            </div>
        </div>
    </div>
    <!-- ===== /Batch Print Modal ===== -->

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        {{-- Shared "where did this record go" renderer, also used after file indexing
             and ST commissioning. Must load before mls_js, whose Commission Summary
             card calls renderRecordSummaryGroups(). --}}
        <script src="{{ asset('js/shared/record-summary-card.js') }}?v={{ @filemtime(public_path('js/shared/record-summary-card.js')) }}"></script>
        @include('generate_fileno.mls_js')
        @include('generate_fileno.view_batches')
        <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
        <script>
            $(document).ready(function() {
                if (typeof GlobalFileNoModal !== 'undefined') {
                    GlobalFileNoModal.init();
                } else {
                    console.error('GlobalFileNoModal still undefined after script load attempt');
                }
            });
        </script>
    @endpush
@endsection


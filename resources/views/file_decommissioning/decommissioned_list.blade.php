@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Decommissioned Files') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include($headerPartial ?? 'admin.header')

        <!-- Main Content -->
        <div class="p-6">
            <div class="bg-white rounded-lg shadow-sm" >
                <!-- Page Header -->
                {{-- <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Decommissioned Files</h1>
                            <p class="text-sm text-gray-600 mt-1">View and manage all decommissioned files in the LANDS module</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('file-decommissioning.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                                Back to Decommissioning
                            </a>
                        </div>
                    </div>
                </div> --}}

                <!-- Quick Decommission Toggle -->
                <div class="px-6 pt-6">
                    <button id="toggleQuickDecommission" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i>
                        Show Quick Decommission
                    </button>
                </div>

                <!-- Quick Decommission Section -->
                <div id="quickDecommissionSection" class="px-6 py-6 border-b border-gray-200 hidden">
                    <div class="bg-gradient-to-r from-red-50 to-pink-50 rounded-lg p-6 border border-red-200">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Quick File Decommissioning</h3>
                                <p class="text-sm text-gray-600">Search and decommission files quickly</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 mr-1"></i>
                                    Decommission Action
                                </span>
                            </div>
                        </div>

                        <form id="quickDecommissionForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="fileSearch" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="search" class="w-4 h-4 inline mr-1"></i>
                                        Search and Select File <span class="text-red-600">*</span>
                                    </label>
                                    <select id="fileSearch" name="file_id" class="w-full" required>
                                        <option value="">Type to search for files...</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Search by MLPP File No, Kangis File No, or File Name</p>
                                </div>
                                <div>
                                    <label for="commissioningDate" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                                        Commissioning Date and Time
                                    </label>
                                    <input type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" id="commissioningDate" name="commissioning_date">
                                    <p class="text-xs text-gray-500 mt-1">Optional - When the file was commissioned</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="decommissioningDate" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="calendar-x" class="w-4 h-4 inline mr-1"></i>
                                        Decommissioning Date and Time <span class="text-red-600">*</span>
                                    </label>
                                    <input type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" id="decommissioningDate" name="decommissioning_date" required>
                                    <p class="text-xs text-gray-500 mt-1">Required - When the file is being decommissioned</p>
                                </div>
                                <div>
                                    <label for="decommissioningReason" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="message-square" class="w-4 h-4 inline mr-1"></i>
                                        Reason for Decommissioning <span class="text-red-600">*</span>
                                    </label>
                                    <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" id="decommissioningReason" name="decommissioning_reason" rows="3" required placeholder="Enter the reason for decommissioning this file..."></textarea>
                                </div>
                            </div>
                            <div>
                                <label for="decommissionedBy" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="user" class="w-4 h-4 inline mr-1"></i>
                                    Decommissioned By
                                </label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" id="decommissionedBy" name="decommissioned_by" value="{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}" readonly>
                            </div>
                            <div class="flex items-center space-x-3">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <i data-lucide="archive" class="w-4 h-4 mr-2"></i>
                                    Decommission File
                                </button>
                                <button type="reset" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                    <i data-lucide="x" class="w-4 h-4 mr-2"></i>
                                    Clear Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Total Decommissioned Files Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="archive" class="w-4 h-4 text-red-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Decommissioned</p>
                                    <p class="text-2xl font-bold text-gray-900" id="totalDecommissionedCount">{{ number_format($totalDecommissionedFiles) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Decommissioned Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="clock" class="w-4 h-4 text-yellow-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Recent (30 days)</p>
                                    <p class="text-2xl font-bold text-gray-900" id="recentDecommissionedCount">{{ number_format($recentDecommissioned) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- This Month Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="calendar" class="w-4 h-4 text-green-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">This Month</p>
                                    <p class="text-2xl font-bold text-gray-900" id="thisMonthCount">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Section -->
                <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="max-w-md mx-auto">
                        <label for="searchDecommissioned" class="block text-sm font-medium text-gray-700 mb-3 text-center">
                            <i data-lucide="search" class="w-4 h-4 inline mr-2"></i>
                            Search Decommissioned Files
                        </label>
                        <div class="relative">
                            <input type="text" id="searchDecommissioned" class="w-full px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Search by file number, name, or reason...">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 text-center">Search by File No, File Name, or Decommissioning Reason</p>
                    </div>
                </div>

                <!-- Decommissioned Files Content -->
                <div class="px-6 py-6">
                    <!-- Default State - No Files -->
                    <div id="no-files" class="text-center py-12 hidden">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i data-lucide="archive" class="w-12 h-12 text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Decommissioned Files Found</h3>
                        <p class="text-gray-500 max-w-md mx-auto">There are no decommissioned files matching your search criteria.</p>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="flex space-x-6" aria-label="Tabs">
                            <button type="button" data-decom-tab="real"
                                class="decom-tab whitespace-nowrap border-b-2 border-blue-600 py-3 px-1 text-sm font-semibold text-blue-600 flex items-center gap-2">
                                <i data-lucide="archive" class="w-4 h-4"></i>
                                Decommissioned Files
                            </button>
                            <button type="button" data-decom-tab="false"
                                class="decom-tab whitespace-nowrap border-b-2 border-transparent py-3 px-1 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:border-gray-300 flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                False Decommissioning Records
                            </button>
                        </nav>
                    </div>

                    <!-- Tab: Decommissioned Files -->
                    <div id="files-container" class="decom-pane" data-decom-pane="real">
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Decommissioned Files List</h3>
                            <p class="text-sm text-gray-600">Click the view button next to any file to see detailed information</p>
                        </div>

                        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300" id="decommissionedFilesTable">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PropID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Temporary File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Related File</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commissioning Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decommissioning Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decommissioned By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Data will be loaded via DataTables -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab: False Decommissioning -->
                    <div id="false-files-container" class="decom-pane hidden" data-decom-pane="false">
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Title Status updates raised from File Indexing.  
                        </div>

                        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300" id="falseDecommissionedFilesTable">
                                <thead class="bg-amber-50 sticky top-0">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MLPP File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kangis File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Temporary File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Data will be loaded via DataTables -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Page Footer -->
            @include($footerPartial ?? 'admin.footer')
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Decommissioned File Details</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDetailsModal()">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="mt-4">
                    <!-- File Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-blue-600 mb-3">File Information</h4>
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p><strong>MLPP File No:</strong> <span id="detailMlsfNo" class="text-gray-700">-</span></p>
                                    <p><strong>Kangis File No:</strong> <span id="detailKangisFileNo" class="text-gray-700">-</span></p>
                                    <p><strong>New Kangis File No:</strong> <span id="detailNewKangisFileNo" class="text-gray-700">-</span></p>
                                </div>
                                <div>
                                    <p><strong>File Name:</strong> <span id="detailFileName" class="text-gray-700">-</span></p>
                                    <p><strong>File Number ID:</strong> <span id="detailFileNumberId" class="text-gray-700">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decommissioning Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-red-600 mb-3">Decommissioning Information</h4>
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p><strong>Commissioning Date:</strong> <span id="detailCommissioningDate" class="text-gray-700">-</span></p>
                                    <p><strong>Decommissioning Date:</strong> <span id="detailDecommissioningDate" class="text-gray-700">-</span></p>
                                </div>
                                <div>
                                    <p><strong>Decommissioned By:</strong> <span id="detailDecommissionedBy" class="text-gray-700">-</span></p>
                                    <p><strong>Record Created:</strong> <span id="detailCreatedAt" class="text-gray-700">-</span></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p><strong>Decommissioning Reason:</strong></p>
                                <div class="mt-2 p-3 bg-white rounded border border-red-200">
                                    <p id="detailDecommissioningReason" class="text-gray-700 text-sm">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500" onclick="closeDetailsModal()">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Select2 CSS and JS (Quick Decommission file picker) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Include DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
    
    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Select2 Custom Styling */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px;
            color: #374151;
            font-size: 0.875rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .select2-dropdown {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6;
        }

        /* DataTables Custom Styling */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin: 1rem 0;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 0.75rem;
            margin: 0 0.125rem;
            border-radius: 0.375rem;
        }

        /* Animation for fade in */
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Custom badge styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-decommissioned {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Reason column: truncated cells reveal the full text on hover */
        .reason-truncated {
            cursor: help;
            border-bottom: 1px dotted #9ca3af;
        }

        #reasonTooltip {
            position: absolute;
            z-index: 10000;
            max-width: 28rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            background-color: #111827;
            color: #f9fafb;
            font-size: 0.75rem;
            line-height: 1.15rem;
            white-space: pre-wrap;
            word-break: break-word;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.25);
            pointer-events: none;
            display: none;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Initialize Lucide icons
            lucide.createIcons();

            // Quick Decommission: show/hide
            $('#toggleQuickDecommission').on('click', function() {
                const section = document.getElementById('quickDecommissionSection');
                section.classList.toggle('hidden');
                this.innerHTML = section.classList.contains('hidden')
                    ? '<i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i> Show Quick Decommission'
                    : '<i data-lucide="minus-circle" class="w-4 h-4 mr-2"></i> Hide Quick Decommission';
                lucide.createIcons();
            });

            // Default the decommissioning date to now (flatpickr may have taken over the input)
            const nowValue = new Date().toISOString().slice(0, 16);
            setDateField('decommissioningDate', nowValue);

            function setDateField(id, value) {
                const field = document.getElementById(id);
                if (!field) return;
                if (field._flatpickr) {
                    field._flatpickr.setDate(value, false);
                } else {
                    field.value = value;
                }
            }

            // Quick Decommission: file picker
            $('#fileSearch').select2({
                placeholder: 'Type to search for files...',
                allowClear: true,
                minimumInputLength: 2,
                width: '100%',
                ajax: {
                    url: '{{ route("file-decommissioning.search") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { search: params.term };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    cache: true
                },
                templateResult: function(option) {
                    if (!option.id) return option.text;

                    return $(
                        '<div class="flex items-center justify-between p-2">' +
                            '<div>' +
                                '<div class="font-medium text-gray-900">' + option.text + '</div>' +
                                '<div class="text-sm text-gray-500">' + (option.mlsfNo || 'N/A') + '</div>' +
                            '</div>' +
                        '</div>'
                    );
                }
            });

            // Initialize DataTable for decommissioned files
            const decommissionedFilesTable = $('#decommissionedFilesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("file-decommissioning.decommissioned-files-data") }}',
                    type: 'GET'
                },
                columns: [
                    { data: null, name: 'sn', orderable: false, searchable: false, render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    } },
                    { data: 'prop_id', name: 'prop_id', orderable: false, searchable: false },
                    { data: 'file_no', name: 'file_no' },
                    { data: 'temp_file_no', name: 'temp_file_no', orderable: false, searchable: false },
                    { data: 'related_file', name: 'related_file', orderable: false, searchable: false },
                    { data: 'file_name', name: 'file_name' },
                    { data: 'commissioning_date', name: 'commissioning_date' },
                    { data: 'decommissioning_date', name: 'decommissioning_date' },
                    { data: 'decommissioned_by', name: 'decommissioned_by' },
                    { data: 'decommissioning_reason', name: 'decommissioning_reason' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ],
                order: [[7, 'desc']], // Order by decommissioning_date desc (index shifted by the Temporary File No column)
                pageLength: 25,
                responsive: true,
                drawCallback: function() {
                    lucide.createIcons();
                }
            });

            // Initialize DataTable for FALSE decommissioned files (Title Status from File Indexing)
            const falseDecommissionedFilesTable = $('#falseDecommissionedFilesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("file-decommissioning.false-decommissioned-files-data") }}',
                    type: 'GET'
                },
                columns: [
                    { data: null, name: 'sn', orderable: false, searchable: false, render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    } },
                    { data: 'mls_file_no', name: 'mls_file_no' },
                    { data: 'kangis_file_no', name: 'kangis_file_no' },
                    { data: 'temp_file_no', name: 'temp_file_no', orderable: false, searchable: false },
                    { data: 'file_name', name: 'file_name' },
                    { data: 'decommissioning_date', name: 'decommissioning_date' },
                    { data: 'decommissioned_by', name: 'decommissioned_by' },
                    { data: 'decommissioning_reason', name: 'decommissioning_reason' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ],
                order: [[5, 'desc']], // decommissioning_date, shifted by the Temporary File No column
                pageLength: 10,
                responsive: true,
                drawCallback: function() {
                    lucide.createIcons();
                }
            });

            // Reason cell: the server sends a 50-char preview plus the full text in
            // data-reason — show the rest in a floating tooltip on hover so the column
            // keeps its width. Tooltip lives on <body> so table overflow can't clip it.
            const $reasonTooltip = $('<div id="reasonTooltip"></div>').appendTo('body');

            function positionReasonTooltip(event) {
                const margin = 12;
                const width = $reasonTooltip.outerWidth();
                const height = $reasonTooltip.outerHeight();

                let left = event.pageX + 16;
                let top = event.pageY + 16;

                const maxLeft = $(window).scrollLeft() + $(window).width() - width - margin;
                if (left > maxLeft) {
                    left = Math.max($(window).scrollLeft() + margin, maxLeft);
                }

                const maxTop = $(window).scrollTop() + $(window).height() - height - margin;
                if (top > maxTop) {
                    top = Math.max($(window).scrollTop() + margin, event.pageY - height - 16);
                }

                $reasonTooltip.css({ left: left + 'px', top: top + 'px' });
            }

            $(document)
                .on('mouseenter', '.reason-truncated', function (e) {
                    const full = $(this).attr('data-reason');
                    if (!full) return;
                    $reasonTooltip.text(full).show();
                    positionReasonTooltip(e);
                })
                .on('mousemove', '.reason-truncated', function (e) {
                    if ($reasonTooltip.is(':visible')) positionReasonTooltip(e);
                })
                .on('mouseleave', '.reason-truncated', function () {
                    $reasonTooltip.hide();
                });

            // Related File cell: reveal the remaining file numbers when the "+N" pill is clicked
            $('#decommissionedFilesTable tbody').on('click', '.rel-more', function() {
                $(this).addClass('hidden');
                $(this).siblings('.rel-rest').removeClass('hidden');
            });

            // Custom search functionality (drives both tables)
            $('#searchDecommissioned').on('keyup', function() {
                decommissionedFilesTable.search(this.value).draw();
                falseDecommissionedFilesTable.search(this.value).draw();
            });

            // Tab switching between Decommissioned and False Decommissioning tables
            $('.decom-tab').on('click', function () {
                const tab = $(this).data('decom-tab');

                // Toggle active styling
                $('.decom-tab')
                    .removeClass('border-blue-600 text-blue-600')
                    .addClass('border-transparent text-gray-500');
                $(this)
                    .removeClass('border-transparent text-gray-500')
                    .addClass('border-blue-600 text-blue-600');

                // Toggle panes
                $('.decom-pane').addClass('hidden');
                $(`.decom-pane[data-decom-pane="${tab}"]`).removeClass('hidden');

                // DataTables initialised while hidden need their columns recalculated
                if (tab === 'false') {
                    falseDecommissionedFilesTable.columns.adjust();
                } else {
                    decommissionedFilesTable.columns.adjust();
                }
                lucide.createIcons();
            });

            // Quick Decommission: submit
            $('#quickDecommissionForm').on('submit', function(e) {
                e.preventDefault();

                const formData = {
                    file_id: $('#fileSearch').val(),
                    commissioning_date: $('#commissioningDate').val(),
                    decommissioning_date: $('#decommissioningDate').val(),
                    decommissioning_reason: $('#decommissioningReason').val()
                };

                if (!formData.file_id) {
                    Swal.fire({ icon: 'warning', title: 'Select a file', text: 'Please select a file to decommission.', confirmButtonColor: '#f59e0b' });
                    return;
                }

                if (!formData.decommissioning_date) {
                    Swal.fire({ icon: 'warning', title: 'Date required', text: 'Please enter the decommissioning date and time.', confirmButtonColor: '#f59e0b' });
                    return;
                }

                if (!formData.decommissioning_reason.trim()) {
                    Swal.fire({ icon: 'warning', title: 'Reason required', text: 'Please enter the reason for decommissioning.', confirmButtonColor: '#f59e0b' });
                    return;
                }

                Swal.fire({
                    title: 'Processing...',
                    text: 'Decommissioning file, please wait...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: '{{ route("file-decommissioning.decommission") }}',
                    type: 'POST',
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'File decommissioned successfully!',
                                confirmButtonColor: '#10b981'
                            });

                            $('#quickDecommissionForm')[0].reset();
                            $('#fileSearch').val(null).trigger('change');
                            setDateField('decommissioningDate', new Date().toISOString().slice(0, 16));

                            decommissionedFilesTable.ajax.reload();
                            loadAdditionalStatistics();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message || 'Error decommissioning file',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    },
                    error: function(xhr) {
                        let message = 'Error decommissioning file';

                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }
                            if (xhr.responseJSON.errors) {
                                message = Object.values(xhr.responseJSON.errors).flat().join(', ');
                            }
                        }

                        Swal.fire({ icon: 'error', title: 'Error!', text: message, confirmButtonColor: '#ef4444' });
                    }
                });
            });

            // Load additional statistics
            loadAdditionalStatistics();

            // Function to load additional statistics
            function loadAdditionalStatistics() {
                $.ajax({
                    url: '{{ route("file-decommissioning.statistics") }}',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            $('#totalDecommissionedCount').text(response.data.decommissioned_files.toLocaleString());
                            $('#recentDecommissionedCount').text(response.data.recent_decommissioned.toLocaleString());
                            
                            // Calculate this month's count (you might want to add this to the backend)
                            // For now, we'll use the recent count as an approximation
                            $('#thisMonthCount').text(Math.floor(response.data.recent_decommissioned * 0.3).toLocaleString());
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading statistics:', xhr);
                    }
                });
            }
        });

        // Global function to view decommissioned file details
        function viewDecommissionedFile(fileId) {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                text: 'Fetching file details...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Load file details
            $.ajax({
                url: `/file-decommissioning/decommissioned-details/${fileId}`,
                type: 'GET',
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        const file = response.data;
                        
                        // Populate modal with file details
                        $('#detailMlsfNo').text(file.mls_file_no || 'N/A');
                        $('#detailKangisFileNo').text(file.kangis_file_no || 'N/A');
                        $('#detailNewKangisFileNo').text(file.new_kangis_file_no || 'N/A');
                        $('#detailFileName').text(file.file_name || 'N/A');
                        $('#detailFileNumberId').text(file.file_number_id || 'N/A');
                        $('#detailCommissioningDate').text(file.commissioning_date || 'N/A');
                        $('#detailDecommissioningDate').text(file.decommissioning_date || 'N/A');
                        $('#detailDecommissionedBy').text(file.decommissioned_by || 'N/A');
                        $('#detailCreatedAt').text(file.created_at || 'N/A');
                        $('#detailDecommissioningReason').text(file.decommissioning_reason || 'N/A');
                        
                        // Show modal
                        showDetailsModal();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Error loading file details: ' + response.message,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error loading file details',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }

        // Global function to view FALSE decommissioned file details (Title Status from File Indexing)
        function viewFalseDecommissionedFile(fileId) {
            Swal.fire({
                title: 'Loading...',
                text: 'Fetching record details...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: `/file-decommissioning/false-decommissioned-details/${fileId}`,
                type: 'GET',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        const file = response.data;
                        $('#detailMlsfNo').text(file.mls_file_no || 'N/A');
                        $('#detailKangisFileNo').text(file.kangis_file_no || 'N/A');
                        $('#detailNewKangisFileNo').text(file.new_kangis_file_no || 'N/A');
                        $('#detailFileName').text(file.file_name || 'N/A');
                        $('#detailFileNumberId').text(file.file_number_id || 'N/A');
                        $('#detailCommissioningDate').text(file.commissioning_date || 'N/A');
                        $('#detailDecommissioningDate').text(file.decommissioning_date || 'N/A');
                        $('#detailDecommissionedBy').text(file.decommissioned_by || 'N/A');
                        $('#detailCreatedAt').text(file.created_at || 'N/A');
                        $('#detailDecommissioningReason').text(file.decommissioning_reason || 'N/A');
                        showDetailsModal();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Error loading record details: ' + response.message, confirmButtonColor: '#ef4444' });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Error loading record details', confirmButtonColor: '#ef4444' });
                }
            });
        }

        // Modal functions
        function showDetailsModal() {
            document.getElementById('viewDetailsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailsModal() {
            document.getElementById('viewDetailsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('viewDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });
    </script>
@endsection
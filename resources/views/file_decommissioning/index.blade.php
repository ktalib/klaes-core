@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('File Decommissioning') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include($headerPartial ?? 'admin.header')

        <!-- Main Content -->
        <div class="p-6">
            <div class="bg-white rounded-lg shadow-sm"  >
                <!-- Page Header -->
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">File Decommissioning</h1>
                            <p class="text-sm text-gray-600 mt-1">Manage and track file decommissioning in the LANDS module</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('file-decommissioning.decommissioned') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i data-lucide="archive" class="w-4 h-4 mr-2"></i>
                                View Decommissioned Files
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Active Files Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Files</p>
                                    <p class="text-2xl font-bold text-gray-900" id="activeFilesCount">{{ number_format($totalActiveFiles) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Decommissioned Files Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="archive" class="w-4 h-4 text-red-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Decommissioned Files</p>
                                    <p class="text-2xl font-bold text-gray-900" id="decommissionedFilesCount">{{ number_format($totalDecommissionedFiles) }}</p>
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
                                    <p class="text-2xl font-bold text-gray-900" id="recentDecommissionedCount">0</p>
                                </div>
                            </div>
                        </div>

                        <!-- Total Files Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="folder" class="w-4 h-4 text-green-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Files</p>
                                    <p class="text-2xl font-bold text-gray-900" id="totalFilesCount">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
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
                        <div>
                                <button id="toggleQuickDecommission" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i>
                                    Show Quick Decommission
                                </button>
                        </div>
                                <script>
                                    document.getElementById('toggleQuickDecommission').addEventListener('click', function() {
                                        const section = document.getElementById('quickDecommissionSection');
                                        const button = document.getElementById('toggleQuickDecommission');
                                        section.classList.toggle('hidden');
                                        button.innerHTML = section.classList.contains('hidden') 
                                            ? '<i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i> Show Quick Decommission' 
                                            : '<i data-lucide="minus-circle" class="w-4 h-4 mr-2"></i> Hide Quick Decommission';
                                    });
                                </script>


                <!-- Active Files Table -->
                <div class="px-6 py-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Decommissioned Files</h3>
                        <p class="text-sm text-gray-600">View and manage decommissioned files from the LANDS module</p>
                    </div>

                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-300" id="decommissionedFilesTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MLPP File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kangis File No</th>
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
                </div>
            </div>
            
            <!-- Page Footer -->
            @include($footerPartial ?? 'admin.footer')
        </div>
    </div>

    <!-- Decommission Modal -->
    <div id="decommissionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Decommission File</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal()">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <form id="decommissionForm" class="mt-4">
                    <input type="hidden" id="modalFileId" name="file_id">
                    
                    <!-- File Details -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-blue-600 mb-3">File Details</h4>
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p><strong>MLPP File No:</strong> <span id="modalMlsfNo" class="text-gray-700">-</span></p>
                                    <p><strong>Kangis File No:</strong> <span id="modalKangisFileNo" class="text-gray-700">-</span></p>
                                    <p><strong>New Kangis File No:</strong> <span id="modalNewKangisFileNo" class="text-gray-700">-</span></p>
                                </div>
                                <div>
                                    <p><strong>File Name:</strong> <span id="modalFileName" class="text-gray-700">-</span></p>
                                    <p><strong>Type:</strong> <span id="modalFileType" class="text-gray-700">-</span></p>
                                    <p><strong>Created:</strong> <span id="modalCreatedAt" class="text-gray-700">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decommissioning Form -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="modalCommissioningDate" class="block text-sm font-medium text-gray-700 mb-2">Commissioning Date and Time</label>
                            <input type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="modalCommissioningDate" name="commissioning_date">
                            <p class="text-xs text-gray-500 mt-1">Optional - When the file was commissioned</p>
                        </div>
                        <div>
                            <label for="modalDecommissioningDate" class="block text-sm font-medium text-gray-700 mb-2">Decommissioning Date and Time <span class="text-red-600">*</span></label>
                            <input type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="modalDecommissioningDate" name="decommissioning_date" required>
                            <p class="text-xs text-gray-500 mt-1">Required - When the file is being decommissioned</p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <label for="modalDecommissioningReason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Decommissioning <span class="text-red-600">*</span></label>
                        <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="modalDecommissioningReason" name="decommissioning_reason" rows="4" required placeholder="Enter the reason for decommissioning this file..."></textarea>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500" onclick="closeModal()">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i data-lucide="archive" class="w-4 h-4 mr-2"></i>
                            Decommission File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Select2 CSS and JS -->
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
    </style>

    <script>
        $(document).ready(function() {
            // Initialize Lucide icons
            lucide.createIcons();

            // Set default decommissioning date to current date/time
            const now = new Date();
            const currentDateTime = now.toISOString().slice(0, 16);
            $('#decommissioningDate, #modalDecommissioningDate').val(currentDateTime);

            // Initialize file search select2
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
                        return {
                            search: params.term
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                templateResult: function(option) {
                    if (!option.id) return option.text;
                    
                    var $option = $(
                        '<div class="flex items-center justify-between p-2">' +
                            '<div>' +
                                '<div class="font-medium text-gray-900">' + option.text + '</div>' +
                                '<div class="text-sm text-gray-500">' + (option.mlsfNo || 'N/A') + '</div>' +
                            '</div>' +
                        '</div>'
                    );
                    return $option;
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
                    { data: 'mls_file_no', name: 'mls_file_no' },
                    { data: 'kangis_file_no', name: 'kangis_file_no' },
                    { data: 'file_name', name: 'file_name' },
                    { data: 'commissioning_date', name: 'commissioning_date' },
                    { data: 'decommissioning_date', name: 'decommissioning_date' },
                    { data: 'decommissioned_by', name: 'decommissioned_by' },
                    { data: 'decommissioning_reason', name: 'decommissioning_reason' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ],
                order: [[4, 'desc']], // Order by decommissioning_date desc
                pageLength: 25,
                responsive: true,
                drawCallback: function() {
                    lucide.createIcons();
                }
            });

            // Load statistics
            loadStatistics();

            // Quick decommission form submission
            $('#quickDecommissionForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    file_id: $('#fileSearch').val(),
                    commissioning_date: $('#commissioningDate').val(),
                    decommissioning_date: $('#decommissioningDate').val(),
                    decommissioning_reason: $('#decommissioningReason').val()
                };

                if (!formData.file_id) {
                    showAlert('Please select a file to decommission.', 'warning');
                    return;
                }

                if (!formData.decommissioning_date) {
                    showAlert('Please enter the decommissioning date and time.', 'warning');
                    return;
                }

                if (!formData.decommissioning_reason.trim()) {
                    showAlert('Please enter the reason for decommissioning.', 'warning');
                    return;
                }

                decommissionFileAction(formData);
            });

            // Modal decommission form submission
            $('#decommissionForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    file_id: $('#modalFileId').val(),
                    commissioning_date: $('#modalCommissioningDate').val(),
                    decommissioning_date: $('#modalDecommissioningDate').val(),
                    decommissioning_reason: $('#modalDecommissioningReason').val()
                };

                decommissionFileAction(formData);
            });

            // Function to decommission file
            function decommissionFileAction(formData) {
                // Show loading with SweetAlert
                Swal.fire({
                    title: 'Processing...',
                    text: 'Decommissioning file, please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
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
                            
                            // Reset forms
                            $('#quickDecommissionForm')[0].reset();
                            $('#decommissionForm')[0].reset();
                            $('#fileSearch').val(null).trigger('change');
                            
                            // Close modal if open
                            closeModal();
                            
                            // Refresh table and statistics
                            decommissionedFilesTable.ajax.reload();
                            loadStatistics();
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
                        let detailsHtml = '';
                        
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }
                            
                            if (xhr.responseJSON.errors) {
                                const errors = Object.values(xhr.responseJSON.errors).flat();
                                message = errors.join(', ');
                                
                                // Create detailed error display
                                detailsHtml = '<div class="text-left mt-3"><strong>Validation Errors:</strong><ul class="list-disc list-inside">';
                                Object.keys(xhr.responseJSON.errors).forEach(field => {
                                    xhr.responseJSON.errors[field].forEach(error => {
                                        detailsHtml += `<li><strong>${field}:</strong> ${error}</li>`;
                                    });
                                });
                                detailsHtml += '</ul></div>';
                                
                                // Show request data if available
                                if (xhr.responseJSON.request_data) {
                                    detailsHtml += '<div class="mt-3"><strong>Request Data:</strong><pre class="text-xs bg-gray-100 p-2 rounded">' + JSON.stringify(xhr.responseJSON.request_data, null, 2) + '</pre></div>';
                                }
                            }
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error!',
                            html: message + detailsHtml,
                            confirmButtonColor: '#ef4444',
                            width: '600px'
                        });
                        
                        // Also log to console for debugging
                        console.error('Decommissioning error:', xhr.responseJSON);
                    }
                });
            }

            // Function to load statistics
            function loadStatistics() {
                $.ajax({
                    url: '{{ route("file-decommissioning.statistics") }}',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            $('#totalFilesCount').text(response.data.total_files.toLocaleString());
                            $('#activeFilesCount').text(response.data.active_files.toLocaleString());
                            $('#decommissionedFilesCount').text(response.data.decommissioned_files.toLocaleString());
                            $('#recentDecommissionedCount').text(response.data.recent_decommissioned.toLocaleString());
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading statistics:', xhr);
                    }
                });
            }

            // Function to show alerts
            function showAlert(message, type) {
                const icon = type === 'success' ? 'success' : 
                            type === 'warning' ? 'warning' : 'error';
                
                Swal.fire({
                    icon: icon,
                    title: type.charAt(0).toUpperCase() + type.slice(1),
                    text: message,
                    confirmButtonColor: type === 'success' ? '#10b981' : 
                                      type === 'warning' ? '#f59e0b' : '#ef4444'
                });
            }
        });

        // Global function to decommission file from table
        function decommissionFile(fileId) {
            // Load file details
            $.ajax({
                url: `/file-decommissioning/file-details/${fileId}`,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const file = response.data;
                        
                        // Populate modal with file details
                        $('#modalFileId').val(file.id);
                        $('#modalMlsfNo').text(file.mlsfNo || 'N/A');
                        $('#modalKangisFileNo').text(file.kangisFileNo || 'N/A');
                        $('#modalNewKangisFileNo').text(file.NewKANGISFileNo || 'N/A');
                        $('#modalFileName').text(file.FileName || 'N/A');
                        $('#modalFileType').text(file.type || 'N/A');
                        $('#modalCreatedAt').text(file.created_at || 'N/A');
                        
                        // Set commissioning date if available
                        if (file.commissioning_date) {
                            $('#modalCommissioningDate').val(file.commissioning_date);
                        }
                        
                        // Set default decommissioning date to current date/time
                        const now = new Date();
                        const currentDateTime = now.toISOString().slice(0, 16);
                        $('#modalDecommissioningDate').val(currentDateTime);
                        
                        // Clear reason field
                        $('#modalDecommissioningReason').val('');
                        
                        // Show modal
                        showModal();
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
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error loading file details',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }

        // Global function to view decommissioned file details
        function viewDecommissionedFile(fileId) {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                text: 'Loading file details...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: `/file-decommissioning/decommissioned-details/${fileId}`,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const file = response.data;
                        
                        // Create detailed view HTML
                        const detailsHtml = `
                            <div class="text-left space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <strong class="text-blue-600">MLPP File No:</strong><br>
                                        <span class="text-gray-700">${file.mls_file_no}</span>
                                    </div>
                                    <div>
                                        <strong class="text-blue-600">Kangis File No:</strong><br>
                                        <span class="text-gray-700">${file.kangis_file_no}</span>
                                    </div>
                                    <div>
                                        <strong class="text-blue-600">New Kangis File No:</strong><br>
                                        <span class="text-gray-700">${file.new_kangis_file_no}</span>
                                    </div>
                                    <div>
                                        <strong class="text-blue-600">File Name:</strong><br>
                                        <span class="text-gray-700">${file.file_name}</span>
                                    </div>
                                    <div>
                                        <strong class="text-blue-600">Commissioning Date:</strong><br>
                                        <span class="text-gray-700">${file.commissioning_date}</span>
                                    </div>
                                    <div>
                                        <strong class="text-blue-600">Decommissioning Date:</strong><br>
                                        <span class="text-gray-700">${file.decommissioning_date}</span>
                                    </div>
                                    <div>
                                        <strong class="text-blue-600">Decommissioned By:</strong><br>
                                        <span class="text-gray-700">${file.decommissioned_by}</span>
                                    </div>
                                    <div>
                                        <strong class="text-blue-600">Record Created:</strong><br>
                                        <span class="text-gray-700">${file.created_at}</span>
                                    </div>
                                </div>
                                <div>
                                    <strong class="text-blue-600">Decommissioning Reason:</strong><br>
                                    <div class="bg-gray-50 p-3 rounded border mt-2">
                                        <span class="text-gray-700">${file.decommissioning_reason}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        Swal.fire({
                            title: 'Decommissioned File Details',
                            html: detailsHtml,
                            width: '800px',
                            confirmButtonText: 'Close',
                            confirmButtonColor: '#3b82f6'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message || 'Error loading file details',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error loading file details',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }

        // Modal functions
        function showModal() {
            document.getElementById('decommissionModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('decommissionModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('decommissionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
@endsection
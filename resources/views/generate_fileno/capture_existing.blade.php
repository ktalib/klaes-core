@extends('layouts.app')
@section('page-title')
    {{ __('Capture Existing File Numbers') }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
    <style>
        #captureTable_wrapper .dataTables_filter input {
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            padding: 0.5rem 1rem;
            background-color: #f8fafc;
            color: #1f2937;
        }

        #captureTable_wrapper .dataTables_filter input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            border-color: #10b981;
            background-color: #ffffff;
        }

        #captureTable_wrapper .dataTables_length select {
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            padding: 0.25rem 0.75rem;
            background-color: #ffffff;
        }

        #captureTable thead th,
        #captureTable tbody td {
            white-space: nowrap !important;
        }

        #captureTable tbody tr:nth-child(odd) {
            background-color: rgba(248, 250, 252, 0.6);
        }

        #captureTable tbody tr:hover {
            background-color: rgba(209, 250, 229, 0.55) !important;
        }
    </style>
@endpush

@section('content') 
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header', [
            'PageTitle' => 'Global File Registry',
            'PageDescription' => 'View and manage all file numbers across all sources'
        ])
        
        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="container mx-auto py-6 space-y-6">
                
                <!-- Action Buttons -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex space-x-4">
                        <button 
                            onclick="openCaptureModal()"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span>Capture Existing File</span>
                        </button>
                        <button 
                            onclick="openMigrationModal()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                            <i data-lucide="upload" class="w-4 h-4"></i>
                            <span>Migrate Data</span>
                        </button>  
                    </div>
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4 shadow-sm">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <div class="text-sm text-gray-700 font-medium">
                                Total Captured: 
                                <span id="totalCount" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-bold bg-green-100 text-green-800 ml-1">
                                    {{ $totalCount ?? 0 }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    $stats = [
                        [
                            'label' => 'Total File Numbers',
                            'value' => number_format($totalCount ?? 0),
                            'icon' => 'database',
                            'accent' => 'text-green-600',
                            'chip' => 'bg-green-100 text-green-700'
                        ],
                        [
                            'label' => 'MLPP File No',
                            'value' => number_format($mlsfCount ?? 0),
                            'icon' => 'square-stack',
                            'accent' => 'text-sky-600',
                            'chip' => 'bg-sky-100 text-sky-700'
                        ],
                        [
                            'label' => 'KANGIS File No',
                            'value' => number_format($kangisCount ?? 0),
                            'icon' => 'map',
                            'accent' => 'text-amber-600',
                            'chip' => 'bg-amber-100 text-amber-700'
                        ],
                        [
                            'label' => 'New KANGIS File No',
                            'value' => number_format($newKangisCount ?? 0),
                            'icon' => 'sparkles',
                            'accent' => 'text-fuchsia-600',
                            'chip' => 'bg-fuchsia-100 text-fuchsia-700'
                        ],
                        [
                            'label' => 'ST File No',
                            'value' => number_format($stCount ?? 0),
                            'icon' => 'file-badge',
                            'accent' => 'text-emerald-600',
                            'chip' => 'bg-emerald-100 text-emerald-700'
                        ],
                    ];
                @endphp

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5 mb-6">
                    @foreach ($stats as $stat)
                        <div class="bg-white/90 backdrop-blur border border-slate-100 rounded-xl shadow-sm p-5 flex flex-col gap-4">
                            <div class="flex items-center justify-between">
                                <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 uppercase tracking-wide">
                                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-slate-100">
                                        <i data-lucide="{{ $stat['icon'] }}" class="w-4 h-4 {{ $stat['accent'] }}"></i>
                                    </span>
                                    {{ $stat['label'] }}
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $stat['chip'] }}">
                                    Live
                                </span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold text-slate-800">{{ $stat['value'] }}</span>
                                <span class="text-xs font-semibold text-slate-400">records captured</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- DataTable -->
                <div class="bg-white/90 backdrop-blur-md rounded-2xl border border-slate-100 shadow-xl">
                    <div class="px-6 pt-6 pb-3 space-y-5">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 text-slate-800 text-base font-semibold">
                                    <i data-lucide="grid" class="w-4 h-4 text-emerald-500"></i>
                                    <span>Global File Registry</span>
                                </div>
                            
                            </div>
                            
                        </div>
                    </div>
                    <div class="px-6 pb-6">
                        <div class="overflow-x-auto">
                            <table id="captureTable" class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead>
                                    <tr class="bg-slate-50/60">
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Primary FileNo</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Related FileNo</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Source</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">File Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">TP No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Plot No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">LGA</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Location</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Captured By</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Capture Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100 text-slate-600">
                                    <!-- Data will be loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        @include('admin.footer')
    </div>

    <!-- Capture Modal -->
    @include('components.partials.capture-existing-fileno-modal-html')

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
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-[500px] shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit File Record</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Edit Form -->
                <form id="editForm" onsubmit="submitEditForm(event)">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editId" name="id">
                    
                    <!-- MLSF Number (Read-only) -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">MLSF Number</label>
                        <input type="text" id="editMlsfNo" 
                               class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md"
                               readonly>
                    </div>

                    <!-- KANGIS File Number (Editable) -->
                    <div class="mb-4">
                        <label for="editKangisFileNo" class="block text-sm font-medium text-gray-700 mb-2">
                            <i data-lucide="file" class="w-4 h-4 inline mr-1"></i>
                            KANGIS File No
                        </label>
                        <input type="text" id="editKangisFileNo" name="kangis_file_no" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="Enter KANGIS file number">
                    </div>

                    <!-- New KANGIS File Number (Editable) -->
                    <div class="mb-4">
                        <label for="editNewKangisFileNo" class="block text-sm font-medium text-gray-700 mb-2">
                            <i data-lucide="file-plus" class="w-4 h-4 inline mr-1"></i>
                            New KANGIS File No
                        </label>
                        <input type="text" id="editNewKangisFileNo" name="new_kangis_file_no" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="Enter new KANGIS file number">
                    </div>

                    <!-- File Name (Editable) -->
                    <div class="mb-4">
                        <label for="editFileName" class="block text-sm font-medium text-gray-700 mb-2">
                            <i data-lucide="file-text" class="w-4 h-4 inline mr-1"></i>
                            File Name
                        </label>
                        <input type="text" id="editFileName" name="file_name" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="Enter file name" required>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Update Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Route config for external JS -->
    <script>
        window.captureExistingConfig = {
            dataUrl:     '{{ route("existing-file-numbers.data") }}',
            storeUrl:    '{{ route("existing-file-numbers.store") }}',
            migrateUrl:  '{{ route("file-numbers.migrate") }}',
            showUrl:     '{{ route("file-numbers.show", ":id") }}',
            updateUrl:   '{{ route("file-numbers.update", ":id") }}',
            destroyUrl:  '{{ route("file-numbers.destroy", ":id") }}',
            existingUrl: '{{ route("file-numbers.existing") }}',
            countUrl:    '{{ route("file-numbers.count") }}'
        };
    </script>
    <script src="{{ asset('js/capture-existing-fileno.js') }}"></script>
@endsection
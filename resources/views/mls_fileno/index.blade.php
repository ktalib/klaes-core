@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? 'File Number Management' }}
@endsection

@section('content')
{{-- Include CSS Assets --}}
@include('sectionaltitling.partials.assets.css')
<style>
    .stat-card {
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    /* Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border: 1px solid transparent;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 0.5rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        outline: none;
        transition: all 0.15s ease-in-out;
    }
    
    .btn:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
    }
    
    .btn-primary {
        color: white;
        background-color: #2563eb;
        border-color: transparent;
    }
    
    .btn-primary:hover {
        background-color: #1d4ed8;
    }
    
    .btn-success {
        color: white;
        background-color: #16a34a;
        border-color: transparent;
    }
    
    .btn-success:hover {
        background-color: #15803d;
    }
    
    .btn-secondary {
        color: #374151;
        background-color: white;
        border-color: #d1d5db;
    }
    
    .btn-secondary:hover {
        background-color: #f9fafb;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 9999px;
        border-width: 1px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease-in-out;
    }
    
    .status-generated {
        background-color: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }
    
    .status-captured {
        background-color: #fef3c7;
        color: #854d0e;
        border-color: #fde047;
    }
    
    .status-active {
        background-color: #dbeafe;
        color: #1e40af;
        border-color: #93c5fd;
    }
    
    /* Badge hover effects */
    .status-badge:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transform: scale(1.05);
    }
    
    /* Table hover effects */
    #mlsTable tbody tr {
        transition: all 0.2s ease;
    }
    
    #mlsTable tbody tr:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    #mlsTable th,
    #mlsTable td {
        vertical-align: middle;
    }

    #mlsTable td:not(.file-name-cell) {
        white-space: nowrap;
    }
    
    /* File Number Badge */
    .file-number-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.375rem 0.875rem;
        font-size: 0.875rem;
        font-weight: 600;
        border-radius: 0.375rem;
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #93c5fd;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease-in-out;
    }
    
    .file-number-badge:hover {
        background-color: #bfdbfe;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transform: scale(1.02);
    }

    .file-number-badge.badge-default {
        background-color: #dbeafe;
        color: #1e40af;
        border-color: #93c5fd;
    }

    .file-number-badge.badge-com {
        background-color: #dbeafe;
        color: #1e3a8a;
        border-color: #93c5fd;
    }

    .file-number-badge.badge-res {
        background-color: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }

    .file-number-badge.badge-con {
        background-color: #f3e8ff;
        color: #6b21a8;
        border-color: #d8b4fe;
    }

    .file-number-badge.badge-ind {
        background-color: #ffedd5;
        color: #9a3412;
        border-color: #fdba74;
    }

    .file-number-badge.badge-agr {
        background-color: #fef9c3;
        color: #854d0e;
        border-color: #fde047;
    }

    .file-number-badge.badge-mix {
        background-color: #fce7f3;
        color: #9d174d;
        border-color: #f9a8d4;
    }

    .file-number-badge.badge-spe {
        background-color: #ede9fe;
        color: #5b21b6;
        border-color: #c4b5fd;
    }

    .file-number-badge.badge-rec {
        background-color: #ccfbf1;
        color: #0f766e;
        border-color: #99f6e4;
    }

    .file-number-badge.badge-edu {
        background-color: #cffafe;
        color: #0e7490;
        border-color: #a5f3fc;
    }

    .file-number-badge.badge-rel {
        background-color: #fef3c7;
        color: #92400e;
        border-color: #fcd34d;
    }

    .file-number-badge.badge-knm,
    .file-number-badge.badge-mlk,
    .file-number-badge.badge-mls,
    .file-number-badge.badge-st {
        background-color: #e0f2fe;
        color: #1e3a8a;
        border-color: #93c5fd;
    }

    .other-number-badge {
        margin: 0;
    }

    .other-number-group {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    /* Edit Modal Styles */
    .mls-edit-modal .swal2-html-container {
        margin: 0;
        padding: 1.5rem 0;
    }
    
    .mls-edit-modal input[type="text"],
    .mls-edit-modal input[type="date"] {
        font-size: 0.875rem;
    }
    
    .mls-edit-modal input:focus {
        outline: none;
        border-color: #16a34a;
        ring: 2px;
        ring-color: rgba(22, 163, 74, 0.2);
    }
    
    .mls-edit-modal label {
        margin-bottom: 0.25rem;
    }
</style>

{{-- External Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Dashboard Content -->
    <div class="p-6">
        <div class="container-fluid px-4 py-6">
    <!-- Page Header -->
    <div class="mb-8">
        <!-- Breadcrumb -->
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/dashboard" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <i data-lucide="home" class="h-4 w-4 mr-2"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400"></i>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">FileNo</span>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400"></i>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">MLPP Management</span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Page Header with Gradient Background -->
        <div class="bg-gradient-to-r from-green-600 via-green-700 to-emerald-800 rounded-xl shadow-lg p-8 relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" width="100" height="100" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
                            <circle cx="10" cy="10" r="1" fill="white"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>

            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center">
                            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-lg mr-4">
                                <i data-lucide="database" class="h-10 w-10 text-white"></i>
                            </div>
                            <div>
                                <h1 class="text-4xl font-bold text-white mb-2">
                                    File Number Management
                                </h1>
                                <p class="text-green-100 text-lg">View, search, and manage all file numbers including MLPP, ST, and KANGIS numbers in the system</p>
                            </div>
                        </div>

                        <!-- Quick Stats in Header -->
                        <div class="flex items-center gap-6 mt-6">
                            <div class="flex items-center text-white/80">
                                <i data-lucide="clock" class="h-4 w-4 mr-2"></i>
                                <span class="text-sm">Last updated: <span class="font-medium text-white">{{ now()->format('g:i A') }}</span></span>
                            </div>
                            <div class="flex items-center text-white/80">
                                <i data-lucide="user" class="h-4 w-4 mr-2"></i>
                                <span class="text-sm">Welcome, <span class="font-medium text-white">{{ Auth::user()->name ?? 'User' }}</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col gap-3">
                        <div class="flex gap-3">
                            <button onclick="exportTableToCSV()" class="flex-1 flex items-center justify-center px-6 py-3 bg-white text-green-700 font-semibold rounded-lg hover:bg-green-50 transition-all duration-200 shadow-lg">
                                <i data-lucide="download" class="h-5 w-5 mr-2"></i>
                                Export
                            </button>
                            <button onclick="refreshData()" class="flex-1 flex items-center justify-center px-6 py-3 bg-white/20 backdrop-blur-sm text-white font-medium rounded-lg hover:bg-white/30 transition-all duration-200 border border-white/30 group">
                                <i data-lucide="refresh-cw" class="h-5 w-5 mr-2 group-hover:rotate-180 transition-transform duration-500"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access Toolbar -->
        <div class="mt-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-gray-600">Quick Actions:</span>
                    <div class="flex gap-2">
                        <button onclick="filterByToday()" class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                            <i data-lucide="calendar" class="h-3 w-3 inline mr-1"></i>
                            Today's Files
                        </button>
                        <button onclick="filterByGenerated()" class="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                            <i data-lucide="plus-circle" class="h-3 w-3 inline mr-1"></i>
                            Generated
                        </button>
                        <button onclick="filterByCaptured()" class="px-3 py-1.5 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-md hover:bg-yellow-200 transition-colors">
                            <i data-lucide="import" class="h-3 w-3 inline mr-1"></i>
                            Captured
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="toggleViewMode()" class="text-sm text-gray-600 hover:text-gray-900 transition-colors" title="Toggle View Mode">
                        <i data-lucide="layout-grid" class="h-5 w-5"></i>
                    </button>
                    <button onclick="openSettings()" class="text-sm text-gray-600 hover:text-gray-900 transition-colors" title="Settings">
                        <i data-lucide="settings" class="h-5 w-5"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Quick filter functions
        function filterByToday() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('searchInput').value = today;
            applyFilters();
        }

        function filterByGenerated() {
            document.getElementById('statusFilter').value = 'generated';
            applyFilters();
        }

        function filterByCaptured() {
            document.getElementById('statusFilter').value = 'captured';
            applyFilters();
        }

        function toggleViewMode() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Grid view coming soon!',
                showConfirmButton: false,
                timer: 2000
            });
        }

        function openSettings() {
            Swal.fire({
                icon: 'info',
                title: 'Display Settings',
                text: 'Table customization coming soon!',
                confirmButtonColor: '#16a34a'
            });
        }
    </script>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6" id="statsCards">
        <!-- Stat cards will be populated here -->
        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total File Numbers</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalCount ?? 0 }}</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i data-lucide="database" class="h-8 w-8 text-blue-600"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Generated</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2" id="generatedCount">-</p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i data-lucide="plus-circle" class="h-8 w-8 text-green-600"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Captured</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2" id="capturedCount">-</p>
                </div>
                <div class="p-3 bg-orange-100 rounded-lg">
                    <i data-lucide="import" class="h-8 w-8 text-orange-600"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Recent (30 days)</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2" id="recentCount">-</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i data-lucide="calendar" class="h-8 w-8 text-purple-600"></i>
                </div>
            </div>
        </div>
    </div>

    @if(isset($error))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <div class="flex items-center">
            <i data-lucide="alert-circle" class="h-5 w-5 text-red-500 mr-2"></i>
            <div>
                <h3 class="text-sm font-medium text-red-800">Database Connection Error</h3>
                <p class="text-sm text-red-700 mt-1">{{ $error }}</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters and Search -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">
                <i data-lucide="filter" class="inline-block h-5 w-5 mr-2"></i>
                Filters & Search
            </h2>
            <button onclick="clearAllFilters()" class="text-sm text-blue-600 hover:text-blue-800">
                <i data-lucide="x" class="inline-block h-4 w-4 mr-1"></i>
                Clear All
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <!-- Search Input -->
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i data-lucide="search" class="inline-block h-4 w-4 mr-1"></i>
                    Search
                </label>
                <input type="text" 
                       id="searchInput" 
                       placeholder="Search MLPP file numbers..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                       onkeyup="handleSearchInput()">
            </div>

            <!-- Year Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                <select id="yearFilter" onchange="applyFilters()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">All Years</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="statusFilter" onchange="applyFilters()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">All Status</option>
                    <option value="generated">Generated</option>
                    <option value="captured">Captured</option>
                    <option value="active">Active</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Table Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white">
                    <i data-lucide="table" class="inline-block h-5 w-5 mr-2"></i>
                    MLPP FileNo Table
                </h2>
                <div class="text-white text-sm">
                    <span id="recordCount">0 records</span>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="tableLoading" class="text-center py-12" style="display: none;">
            <i data-lucide="loader-2" class="h-12 w-12 text-green-600 animate-spin mx-auto"></i>
            <p class="text-gray-600 mt-4">Loading MLPP FileNo...</p>
        </div>

        <!-- Table Content -->
        <div id="tableContainer">
            <!-- DataTables integration -->
            <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="mlsTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Primary File No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Other file numbers</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Commissioned</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commissioned By</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
            <script>
                var mlsTable = null;
                $(document).ready(function() {
                    mlsTable = $('#mlsTable').DataTable({
                        processing: true,
                        serverSide: true,
                        deferRender: true,
                        searchDelay: 400,
                        stateSave: true,
                        autoWidth: false,
                        scrollX: true,
                        lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
                        ajax: {
                            url: '/mls-fileno/datatable',
                            type: 'GET',
                            data: function(d) {
                                // Send custom filters
                                d.year = $('#yearFilter').val();
                                d.status = $('#statusFilter').val();
                            }
                        },
                        columns: [
                            { data: 'mlsfNo', name: 'mlsfNo', className: 'text-left' },
                            { data: 'OtherNumbers', name: 'OtherNumbers', orderable: false, searchable: false, defaultContent: '-', className: 'text-left' },
                            { data: 'FileName', name: 'FileName', className: 'file-name-cell text-left' },
                            { data: 'SOURCE', name: 'SOURCE', className: 'text-left' },
                            { data: 'commissioning_date', name: 'commissioning_date', className: 'text-left' },
                            { data: 'created_by', name: 'created_by', className: 'text-left' },
                            { data: 'actions', name: 'actions', orderable: false, searchable: false, defaultContent: '', className: 'text-left' }
                        ],
                        order: [[4, 'desc']],
                        pageLength: 20,
                        language: {
                            processing: '<span class="text-sm text-gray-600">Loading MLPP FileNo...</span>',
                            lengthMenu: 'Show _MENU_ rows',
                            search: '',
                            searchPlaceholder: 'Quick search...',
                            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                            infoEmpty: 'No records found',
                            zeroRecords: 'No matching records found'
                        },
                        drawCallback: function(settings) {
                            // update counts/stats
                            updateRecordCount();
                            try { lucide.createIcons(); } catch(e){}
                        }
                    });

                    // Update stats once table has loaded
                    mlsTable.on('draw', function() {
                        // could request /mls-fileno/stats endpoint for detailed stats if needed
                        // lightweight: update counts using API info
                        var info = mlsTable.page.info();
                        document.getElementById('recordCount').textContent = info.recordsDisplay + ' records';
                    });
                });
            </script>
        </div>
    </div>
    <!-- Pagination Controls -->
    <!-- DataTables handles pagination -->
</div>

<script>
    // Lightweight client-side utilities that work with server-side DataTables
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize icons and fetch lightweight stats
        try { lucide.createIcons(); } catch(e){}
        fetchStats();
        updateRecordCount();
    });

    function fetchStats() {
        // Fetch small summary stats from server instead of computing from DOM
        fetch('/mls-fileno/stats')
            .then(res => res.json())
            .then(payload => {
                if (payload.success && payload.data) {
                    const totalsBySource = {};
                    if (Array.isArray(payload.data.by_source)) {
                        payload.data.by_source.forEach(item => {
                            const key = (item.SOURCE || item.source || '').toLowerCase();
                            if (key) {
                                totalsBySource[key] = item.count;
                            }
                        });
                    }

                    document.getElementById('generatedCount').textContent = (totalsBySource.generated ?? '-') ;
                    document.getElementById('capturedCount').textContent = (totalsBySource.captured ?? '-');
                    document.getElementById('recentCount').textContent = payload.data.recent ?? '-';
                }
            })
            .catch(err => console.warn('Could not fetch stats', err));
    }

    // Update record count using DataTables API (fast)
    function updateRecordCount() {
        if (typeof mlsTable !== 'undefined' && mlsTable) {
            var info = mlsTable.page.info();
            document.getElementById('recordCount').textContent = info.recordsDisplay + ' records';
        }
    }

    // Filter functions — trigger server-side reloads instead of DOM scans
    function applyFilters() {
        if (typeof mlsTable !== 'undefined' && mlsTable) {
            mlsTable.ajax.reload();
        }
    }

    var searchTimeout = null;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (typeof mlsTable !== 'undefined' && mlsTable) {
                mlsTable.search(document.getElementById('searchInput').value).draw();
            } else {
                applyFilters();
            }
        }, 300);
    }

    function clearAllFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('yearFilter').value = '';
        document.getElementById('statusFilter').value = '';
        if (typeof mlsTable !== 'undefined' && mlsTable) {
            mlsTable.search('').columns().search('').draw();
        } else {
            applyFilters();
        }

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Filters cleared',
            showConfirmButton: false,
            timer: 1500
        });
    }

    // Keep existing action helpers (they still work with server-side rows)
    function viewDetails(fileNumber) {
        Swal.fire({
            title: 'MLPP File Details',
            html: `<div class="text-left"><strong>File Number:</strong> ${fileNumber}</div>`,
            confirmButtonColor: '#16a34a'
        });
    }

    // Note: edit flow uses AJAX fetch to server; no change required here
</script>

        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? 'ST FileNo Management' }}
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
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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

        .btn-info {
            color: white;
            background-color: #0891b2;
            border-color: transparent;
        }

        .btn-info:hover {
            background-color: #0e7490;
        }

        .btn-warning {
            color: white;
            background-color: #ca8a04;
            border-color: transparent;
        }

        .btn-warning:hover {
            background-color: #a16207;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        /* Status Badges - Enhanced with borders and shadows */
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

        .status-reserved {
            background-color: #fef3c7;
            color: #854d0e;
            border-color: #fde047;
        }

        .status-expired {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .status-active {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        .status-cancelled {
            background-color: #f3f4f6;
            color: #1f2937;
            border-color: #d1d5db;
        }

        .status-assigned {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        /* File Type Badges - Enhanced with vibrant colors */
        .type-badge {
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

        .type-primary {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        .type-sua {
            background-color: #f3e8ff;
            color: #6b21a8;
            border-color: #d8b4fe;
        }

        .type-pua {
            background-color: #e0e7ff;
            color: #3730a3;
            border-color: #a5b4fc;
        }

        /* Land Use Badges - Enhanced with distinct colors */
        .land-use-badge {
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

        .land-use-residential {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #6ee7b7;
        }

        .land-use-commercial {
            background-color: #e0f2fe;
            color: #075985;
            border-color: #7dd3fc;
        }

        .land-use-industry {
            background-color: #ffedd5;
            color: #9a3412;
            border-color: #fed7aa;
        }

        .land-use-mixed {
            background-color: #5c0a75;
            color: #ffffff;
            border-color: #f9a8d4;
        }

        /* File Number Badge - Blue */
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: scale(1.02);
        }

        /* TRA Badge - Red */
        .tra-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease-in-out;
        }

        .tra-badge:hover {
            background-color: #fee2e2;
            box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
            transform: scale(1.02);
        }

        /* Badge hover effects */
        .status-badge:hover,
        .type-badge:hover,
        .land-use-badge:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: scale(1.05);
        }
    </style>

    {{-- External Libraries --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>


    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        @include('exports.consolidated_report_action', ['reportKey' => 'st-commissioning'])

        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="container-fluid px-4 py-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <!-- Breadcrumb -->
                    <nav class="flex mb-4" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="/dashboard"
                                    class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
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
                                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">ST Management</span>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <!-- Page Header with Gradient Background -->
                    <div
                        class="bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-800 rounded-xl shadow-lg p-8 relative overflow-hidden">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 opacity-10">
                            <svg class="w-full h-full" width="100" height="100" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
                                        <circle cx="10" cy="10" r="1" fill="white" />
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
                                                ST FileNo Management
                                            </h1>
                                            <p class="text-blue-100 text-lg">View, search, and manage all ST FileNo in the
                                                system</p>
                                        </div>
                                    </div>

                                    <!-- Quick Stats in Header -->
                                    <div class="flex items-center gap-6 mt-6">
                                        <div class="flex items-center text-white/80">
                                            <i data-lucide="clock" class="h-4 w-4 mr-2"></i>
                                            <span class="text-sm">Last updated: <span
                                                    class="font-medium text-white">{{ now()->format('g:i A') }}</span></span>
                                        </div>
                                        <div class="flex items-center text-white/80">
                                            <i data-lucide="user" class="h-4 w-4 mr-2"></i>
                                            <span class="text-sm">Welcome, <span
                                                    class="font-medium text-white">{{ Auth::user()->name ?? 'User' }}</span></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col gap-3">
                                    <a href="/commission-new-st"
                                        class="group flex items-center justify-center px-6 py-3 bg-white text-blue-700 font-semibold rounded-lg hover:bg-blue-50 transform hover:scale-105 transition-all duration-200 shadow-lg">
                                        <i data-lucide="plus-circle"
                                            class="h-5 w-5 mr-2 group-hover:rotate-90 transition-transform duration-300"></i>
                                        Commission New File
                                    </a>
                                    <div class="flex gap-3">
                                        <button onclick="exportTableToCSV()"
                                            class="flex-1 flex items-center justify-center px-4 py-2.5 bg-white/20 backdrop-blur-sm text-white font-medium rounded-lg hover:bg-white/30 transition-all duration-200 border border-white/30">
                                            <i data-lucide="download" class="h-4 w-4 mr-2"></i>
                                            Export
                                        </button>
                                        <button onclick="refreshData()"
                                            class="flex-1 flex items-center justify-center px-4 py-2.5 bg-white/20 backdrop-blur-sm text-white font-medium rounded-lg hover:bg-white/30 transition-all duration-200 border border-white/30 group">
                                            <i data-lucide="refresh-cw"
                                                class="h-4 w-4 mr-2 group-hover:rotate-180 transition-transform duration-500"></i>
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
                                    <button onclick="filterByToday()"
                                        class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                                        <i data-lucide="calendar" class="h-3 w-3 inline mr-1"></i>
                                        Today's Files
                                    </button>
                                    <button onclick="filterByPending()"
                                        class="px-3 py-1.5 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-md hover:bg-yellow-200 transition-colors">
                                        <i data-lucide="alert-circle" class="h-3 w-3 inline mr-1"></i>
                                        Pending Review
                                    </button>
                                    <button onclick="filterByPrimary()"
                                        class="px-3 py-1.5 text-xs font-medium text-purple-700 bg-purple-100 rounded-md hover:bg-purple-200 transition-colors">
                                        <i data-lucide="file-text" class="h-3 w-3 inline mr-1"></i>
                                        Primary Only
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button onclick="toggleViewMode()"
                                    class="text-sm text-gray-600 hover:text-gray-900 transition-colors"
                                    title="Toggle View Mode">
                                    <i data-lucide="layout-grid" class="h-5 w-5"></i>
                                </button>
                                <button onclick="openSettings()"
                                    class="text-sm text-gray-600 hover:text-gray-900 transition-colors" title="Settings">
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

                    function filterByPending() {
                        document.getElementById('statusFilter').value = 'reserved';
                        applyFilters();
                    }

                    function filterByPrimary() {
                        document.getElementById('fileTypeFilter').value = 'PRIMARY';
                        applyFilters();
                    }

                    function toggleViewMode() {
                        // Placeholder for view mode toggle
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
                        // Placeholder for settings
                        Swal.fire({
                            icon: 'info',
                            title: 'Display Settings',
                            text: 'Table customization coming soon!',
                            confirmButtonColor: '#3b82f6'
                        });
                    }
                </script>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6" id="statsCards">
                    <!-- Loading State -->
                    <div class="col-span-4 text-center py-8">
                        <i data-lucide="loader-2" class="h-8 w-8 text-blue-600 animate-spin mx-auto"></i>
                        <p class="text-gray-600 mt-2">Loading statistics...</p>
                    </div>
                </div>

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

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                        <!-- Search Input -->
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="search" class="inline-block h-4 w-4 mr-1"></i>
                                Search
                            </label>
                            <input type="text" id="searchInput" placeholder="Search FileNo, names, etc."
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                onkeyup="handleSearchInput()">
                        </div>

                        <!-- Land Use Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Land Use</label>
                            <select id="landUseFilter" onchange="applyFilters()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Land Uses</option>
                                <option value="RESIDENTIAL">Residential</option>
                                <option value="COMMERCIAL">Commercial</option>
                                <option value="INDUSTRY">Industry</option>
                                <option value="MIXED-USE">Mixed-Use</option>
                            </select>
                        </div>

                        <!-- File Type Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File Type</label>
                            <select id="fileTypeFilter" onchange="applyFilters()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Types</option>
                                <option value="PRIMARY">Primary</option>
                                <option value="SUA">SuA</option>
                                <option value="PUA">PuA</option>
                            </select>
                        </div>

                        <!-- Year Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                            <select id="yearFilter" onchange="applyFilters()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Years</option>
                                <option value="2025">2025</option>
                                <option value="2024">2024</option>
                                <option value="2023">2023</option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="statusFilter" onchange="applyFilters()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Status</option>
                                <option value="generated">Generated</option>
                                <option value="reserved">Reserved</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- Table Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-white">
                                <i data-lucide="table" class="inline-block h-5 w-5 mr-2"></i>
                                FileNo Table
                            </h2>
                            <div class="text-white text-sm">
                                <span id="recordCount">0 records</span>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="tableLoading" class="text-center py-12">
                        <i data-lucide="loader-2" class="h-12 w-12 text-blue-600 animate-spin mx-auto"></i>
                        <p class="text-gray-600 mt-4">Loading FileNo...</p>
                    </div>

                    <!-- Table Content -->
                    <div id="tableContainer" style="display: none;">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="fileNumbersTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('sn')">
                                            S/N <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>

                                        {{-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('tra')">
                                            TRA <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th> --}}
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('np_fileno')">
                                            ST File No <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('mls_fileno')">
                                            MLPP File No <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Applicant
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('file_no_type')">
                                            Type <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('land_use')">
                                            Land Use <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Units
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('year')">
                                            Year <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('created_at')">
                                            Commissioning Date <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('created_by')">
                                            Commissioned By <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            onclick="sortTable('status')">
                                            Status <i data-lucide="chevrons-up-down" class="inline h-3 w-3"></i>
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                    <!-- Data will be populated here -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span
                                        id="totalRecords">0</span> results
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="previousPage()" id="prevBtn"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <i data-lucide="chevron-left" class="inline h-4 w-4"></i> Previous
                                    </button>
                                    <div id="pageNumbers" class="flex gap-2">
                                        <!-- Page numbers will be populated here -->
                                    </div>
                                    <button onclick="nextPage()" id="nextBtn"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next <i data-lucide="chevron-right" class="inline h-4 w-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div id="emptyState" style="display: none;" class="text-center py-12">
                        <i data-lucide="inbox" class="h-16 w-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No FileNo found</h3>
                        <p class="text-gray-600 mb-6">Try adjusting your filters or search criteria</p>
                        <button onclick="clearAllFilters()" class="btn btn-primary">
                            <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- File Details Modal -->
            <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50"
                onclick="closeDetailsModal(event)">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto"
                    onclick="event.stopPropagation()">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-white">File Number Details</h3>
                        <button onclick="closeDetailsModal()" class="text-white hover:text-gray-200">
                            <i data-lucide="x" class="h-6 w-6"></i>
                        </button>
                    </div>
                    <div class="p-6" id="modalContent">
                        <!-- Content will be populated here -->
                    </div>
                </div>
            </div>

            <!-- Units Modal -->
            <div id="unitsModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden items-center justify-center z-[60]"
                onclick="closeUnitsModal(event)">
                <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full mx-4 max-h-[85vh] flex flex-col overflow-hidden"
                    onclick="event.stopPropagation()">
                    <div class="bg-gradient-to-r from-indigo-700 to-purple-800 px-8 py-5 flex items-center justify-between">
                        <div>
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i data-lucide="layers" class="h-7 w-7 mr-3"></i>
                                Linked Units / Sub-Applications
                            </h3>
                            <p class="text-indigo-100 text-sm mt-1" id="unitsModalSubtitle">Associated units for this primary file</p>
                        </div>
                        <button onclick="closeUnitsModal()" class="text-white hover:bg-white/20 p-2 rounded-full transition-colors">
                            <i data-lucide="x" class="h-7 w-7"></i>
                        </button>
                    </div>
                    <div class="p-0 overflow-auto flex-1">
                        <div id="unitsModalContent">
                            <!-- Table will be populated here -->
                        </div>
                    </div>
                    <div class="px-8 py-4 bg-gray-50 border-t flex justify-end">
                        <button onclick="closeUnitsModal()" class="px-6 py-2.5 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>



            <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            {{-- window.generateConversionApplication — shared with the MLS File Commissioning table. --}}
            <script src="{{ asset('js/shared/conversion-application-print.js') }}?v={{ @filemtime(public_path('js/shared/conversion-application-print.js')) }}"></script>
            <script>
                // Allocation Type (DAP / CON-P) only became a real choice on this date.
                // Anything commissioned earlier stored whatever the old form defaulted to,
                // so its Direct/Conversion label is not evidence of anything and is hidden.
                const ALLOCATION_TYPE_KNOWN_FROM = Date.parse('2026-08-09T00:00:00');

                /** The CON mother file behind a row, '' when there is none. */
                function resolveConversionMother(file) {
                    return [file.mls_fileno, file.parent_mls_fileno, file.np_fileno]
                        .map(v => String(v || '').trim())
                        .find(v => /^CON-/i.test(v)) || '';
                }

                /**
                 * 'Conversion' | 'Direct' | '' — the one rule used by both the table and
                 * the dashboard so they can never disagree.
                 */
                function resolveAllocationLabel(file) {
                    if (resolveConversionMother(file) !== '') {
                        return 'Conversion';
                    }

                    const commissionedAt = Date.parse(file.date_commissioned || file.created_at || '');
                    if (isNaN(commissionedAt) || commissionedAt < ALLOCATION_TYPE_KNOWN_FROM) {
                        return '';
                    }

                    const applicationType = String(file.application_type || '').trim().toLowerCase();
                    if (applicationType === 'conversion') {
                        return 'Conversion';
                    }
                    if (applicationType === 'direct allocation' && file.file_no_type === 'PRIMARY') {
                        return 'Direct';
                    }

                    return '';
                }

                // Global variables
                let allFileNumbers = [];
                let filteredFileNumbers = [];
                let unitsByMotherId = {};
                let currentPage = 1;
                let itemsPerPage = 1000;
                let sortColumn = 'created_at';
                let sortDirection = 'desc';
                let searchTimeout = null;

                // Initialize page
                document.addEventListener('DOMContentLoaded', function () {
                    lucide.createIcons();
                    loadStatistics();
                    loadFileNumbers();
                });

                // Load statistics
                async function loadStatistics() {
                    try {
                        const response = await fetch('/api/file-numbers/st-stats');
                        const data = await response.json();

                        if (data.status === 'success') {
                            displayStatistics(data.data);
                        }
                    } catch (error) {
                        console.error('Error loading statistics:', error);
                    }
                }

                /**
                 * Break the Primary Applications count down by allocation type.
                 *
                 * Counted from the loaded file list with the same rule the table uses, so
                 * the card and the rows always agree. Files commissioned before the
                 * DAP / CON-P split are counted as "Unspecified" rather than guessed.
                 */
                function updatePrimaryAllocationSplit() {
                    const target = document.getElementById('primaryAllocationSplit');
                    if (!target) return;

                    const primaries = (allFileNumbers || []).filter(f => f.file_no_type === 'PRIMARY');
                    if (primaries.length === 0) {
                        target.innerHTML = '';
                        return;
                    }

                    let direct = 0, conversion = 0, unspecified = 0;
                    primaries.forEach(f => {
                        const label = resolveAllocationLabel(f);
                        if (label === 'Conversion') conversion++;
                        else if (label === 'Direct') direct++;
                        else unspecified++;
                    });

                    const pill = (count, text, classes) => count > 0
                        ? `<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${classes}">${count} ${text}</span>`
                        : '';

                    target.innerHTML = [
                        pill(direct, 'Direct', 'border-emerald-200 bg-emerald-50 text-emerald-700'),
                        pill(conversion, 'Conversion', 'border-amber-200 bg-amber-50 text-amber-700'),
                        pill(unspecified, 'Unspecified', 'border-gray-200 bg-gray-50 text-gray-500'),
                    ].join('');
                }

                // Display statistics
                function displayStatistics(stats) {
                    const statsHtml = `
                        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Total FileNo</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2">${stats.total_records || 0}</p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <i data-lucide="database" class="h-8 w-8 text-blue-600"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Primary Applications</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2">${stats.primary_count || 0}</p>
                                    {{-- Direct / Conversion split, filled once the file list loads --}}
                                    <div id="primaryAllocationSplit" class="mt-2 flex flex-wrap gap-1"></div>
                                </div>
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <i data-lucide="file-text" class="h-8 w-8 text-green-600"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">SuA Applications</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2">${stats.sua_count || 0}</p>
                                </div>
                                <div class="p-3 bg-purple-100 rounded-lg">
                                    <i data-lucide="layers" class="h-8 w-8 text-purple-600"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-indigo-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">PuA Applications</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2">${stats.pua_count || 0}</p>
                                </div>
                                <div class="p-3 bg-indigo-100 rounded-lg">
                                    <i data-lucide="map" class="h-8 w-8 text-indigo-600"></i>
                                </div>
                            </div>
                        </div>
                    `;

                    document.getElementById('statsCards').innerHTML = statsHtml;
                    lucide.createIcons();

                    // The cards can render before or after the file list arrives.
                    updatePrimaryAllocationSplit();
                }

                // Load FileNo
                async function loadFileNumbers() {
                    try {
                        document.getElementById('tableLoading').style.display = 'block';
                        document.getElementById('tableContainer').style.display = 'none';
                        document.getElementById('emptyState').style.display = 'none';

                        const response = await fetch('/api/file-numbers/st-all');
                        const data = await response.json();

                        if (data.status === 'success') {
                            allFileNumbers = data.data;
                            filteredFileNumbers = [...allFileNumbers];
                            applyFilters();
                            updatePrimaryAllocationSplit();

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: `Loaded ${data.count} FileNo`,
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    } catch (error) {
                        console.error('Error loading FileNo:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to load FileNo',
                            confirmButtonColor: '#ef4444'
                        });
                    } finally {
                        document.getElementById('tableLoading').style.display = 'none';
                    }
                }

                // Apply filters
                function applyFilters() {
                    const search = document.getElementById('searchInput').value.toLowerCase();
                    const landUse = document.getElementById('landUseFilter').value;
                    const fileType = document.getElementById('fileTypeFilter').value;
                    const year = document.getElementById('yearFilter').value;
                    const status = document.getElementById('statusFilter').value;

                    // Clear units map
                    unitsByMotherId = {};

                    // Build units map and filter records
                    const mainTableRecords = [];

                    allFileNumbers.forEach(file => {
                        // Only show PRIMARY and SUA in the main table
                        if (file.file_no_type === 'PRIMARY' || file.file_no_type === 'SUA') {
                            mainTableRecords.push(file);
                        }

                        // Collect PUA units for their respective PRIMARY parents
                        // Use np_fileno as the common link (Mother's ST File No)
                        if (file.file_no_type === 'PUA') {
                            const motherFileno = file.np_fileno;
                            const motherId = file.mother_application_id;
                            
                            if (motherFileno && motherFileno !== 'N/A') {
                                if (!unitsByMotherId['fn_' + motherFileno]) unitsByMotherId['fn_' + motherFileno] = [];
                                unitsByMotherId['fn_' + motherFileno].push(file);
                            }
                            
                            if (motherId) {
                                if (!unitsByMotherId['id_' + motherId]) unitsByMotherId['id_' + motherId] = [];
                                unitsByMotherId['id_' + motherId].push(file);
                            }
                        }
                    });

                    filteredFileNumbers = mainTableRecords.filter(file => {
                        // Search filter
                        if (search) {
                            const searchableText = [
                                file.full_file_number,
                                file.np_fileno,
                                file.fileno,
                                file.mls_fileno,
                                file.first_name,
                                file.middle_name,
                                file.surname,
                                file.corporate_name
                            ].join(' ').toLowerCase();

                            if (!searchableText.includes(search)) return false;
                        }

                        // Land use filter
                        if (landUse && file.land_use) {
                            // Case-insensitive comparison and handle variations
                            const fileLandUse = file.land_use.toUpperCase().trim();
                            const selectedLandUse = landUse.toUpperCase().trim();
                            if (fileLandUse !== selectedLandUse) return false;
                        } else if (landUse && !file.land_use) {
                            return false;
                        }

                        // File type filter - usually redundant here as we show primaries, 
                        // but kept for UI consistency if user changes filter
                        if (fileType && file.file_no_type !== fileType) return false;

                        // Year filter
                        if (year && file.year != year) return false;

                        // Status filter
                        if (status && file.status !== status) return false;

                        return true;
                    });

                    currentPage = 1;
                    sortAndDisplayTable();
                }

                // Handle search input with debounce
                function handleSearchInput() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        applyFilters();
                    }, 300);
                }

                // Sort and display table
                function sortAndDisplayTable() {
                    // Sort data
                    filteredFileNumbers.sort((a, b) => {
                        let aVal = a[sortColumn];
                        let bVal = b[sortColumn];

                        if (sortColumn === 'created_at') {
                            aVal = new Date(aVal);
                            bVal = new Date(bVal);
                        }

                        if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
                        if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
                        return 0;
                    });

                    displayTable();
                }

                // Sort table
                function sortTable(column) {
                    if (sortColumn === column) {
                        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortColumn = column;
                        sortDirection = 'asc';
                    }

                    sortAndDisplayTable();
                }

                // Helper function to get status display text
                function getStatusDisplay(status) {
                    if (!status) return 'N/A';

                    const statusUpper = status.toUpperCase();
                    switch (statusUpper) {
                        case 'USED':
                            return 'Assigned';
                        case 'ACTIVE':
                            return 'Commissioned';
                        default:
                            return 'Decommissioned';
                    }
                }

                // Helper function to get status CSS class
                /**
                 * "Commissioned By" cell. st_file_numbers.created_by is a real user id and the
                 * API also returns the resolved name, so the shared profile card
                 * (js/user-profile-card.js) is opened by id with the name as a fallback.
                 */
                function renderCommissionedBy(file) {
                    const name = String(file.created_by_name || '').trim();

                    if (name === '' || name === 'System') {
                        return 'System';
                    }

                    const safe = name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    const idAttr = file.created_by ? ` data-user-id="${String(file.created_by).replace(/"/g, '')}"` : '';

                    return `<span class="upc-trigger" data-user-card${idAttr} data-user-name="${safe}" title="View profile">${safe}</span>`;
                }

                function getStatusClass(status) {
                    if (!status) return 'generated';

                    const statusUpper = status.toUpperCase();
                    switch (statusUpper) {
                        case 'USED':
                            return 'active'; // Use blue styling for assigned
                        case 'ACTIVE':
                            return 'generated'; // Use green styling for commissioned
                        default:
                            return 'cancelled'; // Use gray styling for decommissioned
                    }
                }

                // Display table
                function displayTable() {
                    const totalRecords = filteredFileNumbers.length;
                    const totalPages = Math.ceil(totalRecords / itemsPerPage);
                    const start = (currentPage - 1) * itemsPerPage;
                    const end = Math.min(start + itemsPerPage, totalRecords);
                    const pageData = filteredFileNumbers.slice(start, end);
                    const baseIndex = start; // for S/N numbering

                    if (totalRecords === 0) {
                        document.getElementById('tableContainer').style.display = 'none';
                        document.getElementById('emptyState').style.display = 'block';
                        return;
                    }

                    document.getElementById('tableContainer').style.display = 'block';
                    document.getElementById('emptyState').style.display = 'none';

                    // Update record count
                    document.getElementById('recordCount').textContent = `${totalRecords} records`;
                    document.getElementById('showingStart').textContent = start + 1;
                    document.getElementById('showingEnd').textContent = end;
                    document.getElementById('totalRecords').textContent = totalRecords;

                    // Generate table rows
                    const tbody = document.getElementById('tableBody');
                    tbody.innerHTML = pageData.map((file, idx) => {
                        const applicantName = file.corporate_name ||
                            `${file.first_name || ''} ${file.middle_name || ''} ${file.surname || ''}`.trim() ||
                            'N/A';

                        // Determine ST File No and MLS File No based on file typecorr
                        let stFileNo = 'N/A';
                        let mlsFileNo = 'N/A';

                        if (file.file_no_type === 'PRIMARY') {
                            stFileNo = file.np_fileno || 'N/A';
                            // A conversion carries its CON mother file number in
                            // mls_fileno; for every other primary the two are the same.
                            mlsFileNo = file.mls_fileno || file.fileno || 'N/A';
                        } else if (file.file_no_type === 'PUA') {
                            stFileNo = file.fileno || 'N/A';
                            mlsFileNo = file.np_fileno || 'N/A';
                        } else if (file.file_no_type === 'SUA') {
                            stFileNo = file.fileno || 'N/A';
                            mlsFileNo = file.np_fileno || 'N/A';
                        }

                        // The CON mother file: a primary carries it in mls_fileno, a unit
                        // (PuA/SuA) inherits its primary's via parent_mls_fileno. Only a
                        // conversion has one, and only it has a Confirmation Sheet.
                        // resolveConversionMother() reads the row's own three fields;
                        // mlsFileNo is the number shown in the table, which for a unit
                        // is its np_fileno and so is already covered.
                        const conversionMotherNo = /^CON-/i.test(String(mlsFileNo).trim())
                            ? mlsFileNo
                            : resolveConversionMother(file);
                        // Sub-label under the type badge. Files commissioned before the
                        // DAP / CON-P split carry an application_type that was only ever a
                        // form default, so they stay unlabelled — see resolveAllocationLabel().
                        const allocationLabel = resolveAllocationLabel(file);

                        // The Confirmation Sheet belongs to a SuA (always) and to a
                        // conversion primary; a PuA unit never gets one.
                        const isSua = file.file_no_type === 'SUA';
                        const canPrintLcs = file.file_no_type !== 'PUA'
                            && (isSua || conversionMotherNo !== '');

                        // Printed off the CON mother when there is one, otherwise off the
                        // file's own number — a SuA is filed under st_file_no.
                        const lcsFileNo = conversionMotherNo
                            || String(file.fileno || file.mls_fileno || file.np_fileno || '').trim();

                        // The SuA card only applies when the sheet is printed off the
                        // SuA's own file number. A SuA under a converted scheme prints
                        // its mother's sheet, which is the LGA/Conversion one.
                        const csVariant = (isSua && !conversionMotherNo) ? 'sua' : 'default';

                        return `
                            <tr class="hover:bg-gray-50 transition-colors">

                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="sn">${baseIndex + idx + 1}</span>
                                </td>


                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="file-number-badge">${stFileNo}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="file-number-badge">${mlsFileNo}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 font-medium">${applicantName}</div>
                                    <div class="text-xs text-gray-500">${file.applicant_type || ''}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="type-badge type-${(file.file_no_type || 'primary').toLowerCase()}">${file.file_no_type || 'N/A'}</span>
                                    ${allocationLabel ? `
                                    <div class="mt-1">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${allocationLabel === 'Conversion'
                                            ? 'border-amber-200 bg-amber-50 text-amber-700'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700'}">${allocationLabel}</span>
                                    </div>` : ''}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="land-use-badge land-use-${(file.land_use || '').toLowerCase().replace('-', '')}">${file.land_use || 'N/A'}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    ${(() => {
                                        if (file.file_no_type !== 'PRIMARY') {
                                            return `<span class="text-gray-400 text-xs italic">N/A</span>`;
                                        }
                                        
                                        const motherFileno = file.np_fileno;
                                        const motherId = file.mother_application_id;
                                        
                                        // Try to find units by Fileno first, then by ID
                                        let units = [];
                                        if (motherFileno && unitsByMotherId['fn_' + motherFileno]) {
                                            units = unitsByMotherId['fn_' + motherFileno];
                                        } else if (motherId && unitsByMotherId['id_' + motherId]) {
                                            units = unitsByMotherId['id_' + motherId];
                                        }

                                        if (units.length > 0) {
                                            const triggerId = motherFileno || motherId;
                                            return `
                                                <button onclick="showUnitsModal('${triggerId}', '${stFileNo}')" 
                                                    class="inline-flex items-center px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold hover:bg-indigo-200 transition-colors shadow-sm border border-indigo-200">
                                                    <i data-lucide="layers" class="h-3 w-3 mr-1.5"></i>
                                                    ${units.length} ${units.length === 1 ? 'Unit' : 'Units'}
                                                </button>
                                            `;
                                        } else {
                                            return `<span class="text-gray-400 text-xs italic">0 Units</span>`;
                                        }
                                    })()}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ${file.year || 'N/A'}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${file.created_at ? new Date(file.created_at).toLocaleDateString() : 'N/A'}
                                    <div class="text-xs">${file.created_at ? new Date(file.created_at).toLocaleTimeString() : ''}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 font-medium">${renderCommissionedBy(file)}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge status-${getStatusClass(file.status)}">${getStatusDisplay(file.status)}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="relative inline-block text-left">
                                        <button type="button" onclick="toggleStActionMenu(event, 'st-actions-${file.id}')"
                                                class="p-1.5 rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors" title="Actions">
                                            <i data-lucide="more-vertical" class="h-4 w-4"></i>
                                        </button>
                                        <div id="st-actions-${file.id}"
                                             class="st-action-menu hidden absolute right-0 z-50 mt-1 w-64 rounded-lg border border-gray-200 bg-white py-1 shadow-lg text-left">
                                            <button type="button" onclick='viewDetails(${JSON.stringify(file).replace(/'/g, "&apos;")})'
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <i data-lucide="eye" class="h-4 w-4 mr-2 text-gray-500"></i>
                                                View/Edit Details
                                            </button>
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <button type="button" onclick='printStCommissioningSheet(${JSON.stringify(file).replace(/'/g, "&apos;")})'
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <i data-lucide="file-text" class="h-4 w-4 mr-2 text-blue-600"></i>
                                                ST Commissioning Sheet (FCS)
                                            </button>
                                            ${canPrintLcs ? `
                                            <button type="button" onclick="generateConversionApplication(${file.id}, '${lcsFileNo}', { variant: '${csVariant}' })"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <i data-lucide="refresh-cw" class="h-4 w-4 mr-2 text-indigo-600"></i>
                                                Confirmation Sheet (CS)
                                            </button>` : `
                                            <span class="flex w-full items-center px-4 py-2 text-sm text-gray-300 cursor-not-allowed"
                                                  title="${file.file_no_type === 'PUA' ? 'A PuA unit has no Confirmation Sheet' : 'Only conversion files have a Confirmation Sheet'}">
                                                <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                                                Confirmation Sheet (CS)
                                            </span>`}
                                            @if(Auth::check() && Auth::user()->assign_role === 'Supper Admin')
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <button type="button" onclick='masterDeleteStFile(${JSON.stringify(file).replace(/'/g, "&apos;")})'
                                                    class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i data-lucide="trash-2" class="h-4 w-4 mr-2"></i>
                                                Master Delete
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    // Update pagination
                    updatePagination(totalPages);

                    lucide.createIcons();
                }

                // Update pagination
                function updatePagination(totalPages) {
                    const pageNumbers = document.getElementById('pageNumbers');
                    let paginationHtml = '';

                    const maxVisible = 5;
                    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisible - 1);

                    if (endPage - startPage < maxVisible - 1) {
                        startPage = Math.max(1, endPage - maxVisible + 1);
                    }

                    for (let i = startPage; i <= endPage; i++) {
                        paginationHtml += `
                            <button onclick="goToPage(${i})" class="px-4 py-2 text-sm font-medium ${i === currentPage ? 'text-white bg-blue-600' : 'text-gray-700 bg-white'} border border-gray-300 rounded-lg hover:bg-gray-50">
                                ${i}
                            </button>
                        `;
                    }

                    pageNumbers.innerHTML = paginationHtml;

                    // Update prev/next buttons
                    document.getElementById('prevBtn').disabled = currentPage === 1;
                    document.getElementById('nextBtn').disabled = currentPage === totalPages || totalPages === 0;
                }

                // Pagination functions
                function previousPage() {
                    if (currentPage > 1) {
                        currentPage--;
                        displayTable();
                    }
                }

                function nextPage() {
                    const totalPages = Math.ceil(filteredFileNumbers.length / itemsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        displayTable();
                    }
                }

                function goToPage(page) {
                    currentPage = page;
                    displayTable();
                }

                // View details
                function viewDetails(file) {
                    const applicantName = file.corporate_name ||
                        `${file.first_name || ''} ${file.middle_name || ''} ${file.surname || ''}`.trim() ||
                        'N/A';

                    const modalContent = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">File Number Information</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Full File Number</label>
                                        <p class="text-gray-900 font-medium">${file.full_file_number || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">NP File No</label>
                                        <p class="text-gray-900">${file.np_fileno || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">File No</label>
                                        <p class="text-gray-900">${file.fileno || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">MLPP File No</label>
                                        <p class="text-gray-900">${file.mls_fileno || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Tracking ID</label>
                                        <p class="text-gray-900">${file.tra || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Application Details</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Land Use</label>
                                        <p class="text-gray-900">${file.land_use || 'N/A'} (${file.land_use_code || ''})</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">File Type</label>
                                        <p class="text-gray-900">${file.file_no_type || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Year</label>
                                        <p class="text-gray-900">${file.year || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Serial Number</label>
                                        <p class="text-gray-900">${file.serial_no || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Status</label>
                                        <p><span class="status-badge status-${getStatusClass(file.status)}">${getStatusDisplay(file.status)}</span></p>
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Applicant Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Applicant Type</label>
                                        <p class="text-gray-900">${file.applicant_type || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Full Name</label>
                                        <p class="text-gray-900">${applicantName}</p>
                                    </div>
                                    ${file.corporate_name ? `
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">RC Number</label>
                                            <p class="text-gray-900">${file.rc_number || 'N/A'}</p>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">System Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Created By</label>
                                        <p class="text-gray-900 font-medium">${file.created_by_name || 'System'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Created Date</label>
                                        <p class="text-gray-900">${file.created_at ? new Date(file.created_at).toLocaleString() : 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Last Updated</label>
                                        <p class="text-gray-900">${file.updated_at ? new Date(file.updated_at).toLocaleString() : 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button onclick="closeDetailsModal()" class="btn btn-secondary">Close</button>
                            <button onclick="editStRecord(${file.id})" class="btn btn-primary" style="background-color:#f59e0b;border-color:#f59e0b">
                                <i data-lucide="pencil" class="h-4 w-4 mr-2"></i>
                                Edit
                            </button>
                            <button onclick='printStCommissioningSheet(${JSON.stringify(file).replace(/'/g, "&apos;")})' class="btn btn-primary">
                                <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                                ST Commissioning Sheet (FCS)
                            </button>
                        </div>
                    `;

                    document.getElementById('modalContent').innerHTML = modalContent;
                    document.getElementById('detailsModal').classList.remove('hidden');
                    document.getElementById('detailsModal').classList.add('flex');
                    lucide.createIcons();
                }

                // Close modal
                function closeDetailsModal(event) {
                    if (!event || event.target.id === 'detailsModal') {
                        document.getElementById('detailsModal').classList.add('hidden');
                        document.getElementById('detailsModal').classList.remove('flex');
                    }
                }

                /**
                 * Show modal with units linked to a mother ID or Fileno
                 */
                function showUnitsModal(identifier, motherFileNo) {
                    const units = unitsByMotherId['fn_' + identifier] || unitsByMotherId['id_' + identifier] || [];
                    const modal = document.getElementById('unitsModal');
                    const content = document.getElementById('unitsModalContent');
                    const subtitle = document.getElementById('unitsModalSubtitle');

                    subtitle.textContent = `Displaying ${units.length} sub-application(s) linked to Primary File: ${motherFileNo}`;

                    if (units.length === 0) {
                        content.innerHTML = `
                            <div class="py-20 text-center">
                                <i data-lucide="inbox" class="h-16 w-16 text-gray-300 mx-auto mb-4"></i>
                                <p class="text-gray-500 font-medium">No units found for this primary application.</p>
                            </div>
                        `;
                    } else {
                        content.innerHTML = `
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Unit File No</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Applicant Name</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Commissioned On</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    ${units.map(unit => {
                                        const name = unit.corporate_name || `${unit.first_name || ''} ${unit.middle_name || ''} ${unit.surname || ''}`.trim();
                                        const stNo = unit.fileno || 'N/A';
                                        return `
                                            <tr class="hover:bg-indigo-50/50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="file-number-badge">${stNo}</span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-semibold text-gray-900">${name}</div>
                                                    <div class="text-xs text-gray-500">${unit.rc_number || ''}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="type-badge type-${(unit.file_no_type || '').toLowerCase()}">${unit.file_no_type}</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="status-badge status-${getStatusClass(unit.status)}">${getStatusDisplay(unit.status)}</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    ${unit.created_at ? new Date(unit.created_at).toLocaleDateString() : 'N/A'}
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        `;
                    }

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    lucide.createIcons();
                }

                function closeUnitsModal(event) {
                    if (!event || event.target.id === 'unitsModal' || event.target.closest('button')) {
                        document.getElementById('unitsModal').classList.add('hidden');
                        document.getElementById('unitsModal').classList.remove('flex');
                    }
                }

                /**
                 * Open the ST File Commissioning page in edit mode for this record.
                 * The page reuses the commissioning form with the issued file
                 * numbers locked — only applicant, gender and location are editable.
                 */
                function editStRecord(id) {
                    if (!id) {
                        Swal.fire({ icon: 'error', title: 'Cannot edit', text: 'This record has no id.' });
                        return;
                    }
                    window.location.href = '/commission-new-st?edit=' + encodeURIComponent(id);
                }

                // Edit file (row shortcut) — same destination as the card's Edit button.
                function editFile(id) {
                    editStRecord(id);
                }

                // Row actions menu — only one open at a time.
                function toggleStActionMenu(event, menuId) {
                    event.stopPropagation();
                    const menu = document.getElementById(menuId);
                    if (!menu) return;

                    document.querySelectorAll('.st-action-menu').forEach(m => {
                        if (m !== menu) m.classList.add('hidden');
                    });
                    menu.classList.toggle('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }

                document.addEventListener('click', function () {
                    document.querySelectorAll('.st-action-menu').forEach(m => m.classList.add('hidden'));
                });

                /**
                 * MASTER DELETE — wipes the ST file number from every table.
                 *
                 * Irreversible, so it asks twice: a red warning listing exactly what
                 * goes, then the file number typed out by hand.
                 */
                function masterDeleteStFile(file) {
                    const stFileNo = file.file_no_type === 'PRIMARY'
                        ? (file.np_fileno || file.fileno)
                        : (file.fileno || file.np_fileno);
                    const mlsFileNo = file.mls_fileno || '';
                    const isConversion = /^CON-/i.test(String(mlsFileNo).trim());
                    const isPrimary = file.file_no_type === 'PRIMARY';

                    const targets = [
                        'ST file number record' + (isPrimary ? ' <b>and all its unit files</b>' : ''),
                        'File indexing record',
                        'Legacy file number (fileNumber) link',
                        'Customer &amp; entity staging records',
                        'Related file links and decommissioning log',
                        'Saved commissioning sheets'
                    ];
                    if (isConversion) {
                        targets.push('The CON mother file <b>' + mlsFileNo + '</b> — only if this commissioning created it');
                    }

                    Swal.fire({
                        icon: 'warning',
                        title: 'Destructive action',
                        html: `
                            <div style="text-align:left;font-size:13px">
                                <p style="margin-bottom:8px">This permanently deletes <b>${stFileNo}</b> from <b>every table</b>. It cannot be undone.</p>
                                <p style="margin-bottom:4px;font-weight:600">What will be removed:</p>
                                <ul style="margin:0 0 10px 18px;list-style:disc">${targets.map(t => `<li>${t}</li>`).join('')}</ul>
                                <p style="color:#b91c1c;font-weight:600;margin:0">Scanned documents are never deleted — the EDMS folder is kept if it holds any.</p>
                            </div>`,
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        confirmButtonText: 'Continue',
                        cancelButtonText: 'Cancel',
                        focusCancel: true
                    }).then(step => {
                        if (!step.isConfirmed) return;

                        Swal.fire({
                            icon: 'error',
                            title: 'Type the file number to confirm',
                            input: 'text',
                            inputPlaceholder: stFileNo,
                            html: `<p style="font-size:13px">Type <b>${stFileNo}</b> exactly to delete it everywhere.</p>`,
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            confirmButtonText: 'Delete permanently',
                            focusCancel: true,
                            preConfirm: (value) => {
                                if ((value || '').trim() !== stFileNo) {
                                    Swal.showValidationMessage('The file number does not match.');
                                    return false;
                                }
                                return value.trim();
                            }
                        }).then(confirmStep => {
                            if (!confirmStep.isConfirmed) return;

                            fetch('{{ route('st-file-numbers.master-destroy') }}', {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ file_number: stFileNo, confirm: confirmStep.value })
                            })
                            .then(r => r.json())
                            .then(result => {
                                if (!result.success) {
                                    throw new Error(result.message || 'Delete failed');
                                }
                                const d = result.data.deleted || {};
                                const lines = Object.keys(d).filter(k => d[k]).map(k => `${k}: ${d[k]}`);
                                if (result.data.mother_deleted) {
                                    lines.push(`mother file ${result.data.mother_file} removed`);
                                }
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted',
                                    html: `<div style="text-align:left;font-size:13px"><p>${stFileNo} removed.</p>
                                           <ul style="margin:6px 0 0 18px;list-style:disc">${lines.map(l => `<li>${l}</li>`).join('')}</ul></div>`,
                                    confirmButtonColor: '#3b82f6'
                                }).then(() => {
                                    loadFileNumbers();
                                    loadStatistics();
                                });
                            })
                            .catch(error => {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Delete failed',
                                    text: error.message || 'Could not delete the file number',
                                    confirmButtonColor: '#3b82f6'
                                });
                            });
                        });
                    });
                }

                /**
                 * File Commissioning Sheet for an ST file.
                 *
                 * Same two steps as the OSS and MLS screens: save the sheet, then open
                 * the printable view. A conversion is filed under its CON mother number,
                 * which is also what lets the controller resolve the sheet's related-file
                 * line ("Conversion") from mls_file_no.source.
                 */
                function printStCommissioningSheet(file) {
                    const stFileNo = file.file_no_type === 'PRIMARY'
                        ? (file.np_fileno || file.fileno)
                        : (file.fileno || file.np_fileno);
                    // A unit carries no CON number of its own — its scheme's lives on
                    // the primary. Resolving across all three fields is what makes the
                    // parent/old file number print alongside the new one.
                    const conversionMotherNo = resolveConversionMother(file);
                    const sheetFileNo = conversionMotherNo || stFileNo;

                    const applicantName = file.corporate_name ||
                        `${file.first_name || ''} ${file.middle_name || ''} ${file.surname || ''}`.trim() ||
                        '';
                    const commissionedAt = file.date_commissioned || file.created_at || null;

                    const payload = {
                        file_number: sheetFileNo,
                        file_name: applicantName,
                        name_or_allottee: applicantName,
                        plot_number: file.property_plot_no || '',
                        location: file.property_street_name || file.property_district || '',
                        lga: file.property_lga || '',
                        date_created: commissionedAt ? new Date(commissionedAt).toISOString() : '',
                        created_by: file.created_by_name || '',
                        commissioning_time: commissionedAt ? new Date(commissionedAt).toLocaleTimeString() : '',
                        related_file_number: sheetFileNo === stFileNo ? '' : stFileNo
                    };

                    fetch('{{ route('commissioning-sheet.generate-print') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (!result.success) {
                            throw new Error(result.message || 'Could not save the commissioning sheet');
                        }
                        // source=st puts "DEPARTMENT OF SECTIONAL TITLING" on the sheet —
                        // a conversion is filed under its CON number, so the number alone
                        // cannot say which department commissioned it.
                        const params = new URLSearchParams({ source: 'st' });
                        if (payload.commissioning_time) {
                            params.set('commissioning_time', payload.commissioning_time);
                        }
                        window.open('{{ url('commissioning-sheet/print') }}/' + result.data.id + '?' + params.toString(), '_blank');
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Commissioning Sheet',
                            text: error.message || 'Could not generate the commissioning sheet',
                            confirmButtonColor: '#3b82f6'
                        });
                    });
                }

                // Clear filters
                function clearAllFilters() {
                    document.getElementById('searchInput').value = '';
                    document.getElementById('landUseFilter').value = '';
                    document.getElementById('fileTypeFilter').value = '';
                    document.getElementById('yearFilter').value = '';
                    document.getElementById('statusFilter').value = '';
                    applyFilters();

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Filters cleared',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }

                // Refresh data
                function refreshData() {
                    loadStatistics();
                    loadFileNumbers();
                }

                // Export to CSV
                function exportTableToCSV() {
                    if (filteredFileNumbers.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No Data',
                            text: 'No data available to export',
                            confirmButtonColor: '#f59e0b'
                        });
                        return;
                    }

                    const headers = ['TRA', 'ST File No', 'MLPP File No', 'Applicant', 'Type', 'Land Use', 'Year', 'Commissioning Date', 'Commissioned By', 'Status'];
                    let csvContent = headers.join(',') + '\n';

                    filteredFileNumbers.forEach(file => {
                        const applicantName = file.corporate_name ||
                            `${file.first_name || ''} ${file.middle_name || ''} ${file.surname || ''}`.trim() ||
                            'N/A';

                        const row = [
                            `"${file.tra || 'N/A'}"`,
                            `"${file.np_fileno || 'N/A'}"`,
                            `"${file.mls_fileno || 'N/A'}"`,
                            `"${applicantName}"`,
                            `"${file.file_no_type || 'N/A'}"`,
                            `"${file.land_use || 'N/A'}"`,
                            `"${file.year || 'N/A'}"`,
                            `"${file.created_at ? new Date(file.created_at).toLocaleString() : 'N/A'}"`,
                            `"${file.created_by_name || 'N/A'}"`,
                            `"${getStatusDisplay(file.status)}"`
                        ];
                        csvContent += row.join(',') + '\n';
                    });

                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);

                    link.setAttribute('href', url);
                    link.setAttribute('download', `st_file_numbers_${new Date().toISOString().split('T')[0]}.csv`);
                    link.style.visibility = 'hidden';

                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'CSV exported successfully',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }




                //debug mode    /*



            </script>



        </div>
    </div>
    </div>
@endsection

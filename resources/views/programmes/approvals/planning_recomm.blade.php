@extends('layouts.app')

@section('page-title')
    @php
        $pageTitleFromRequest = request()->query('url');
        $resolvedPageTitle = $PageTitle ?? __('KLAES');

        if (is_string($pageTitleFromRequest) && strtolower($pageTitleFromRequest) === 'approval') {
            $resolvedPageTitle = 'Planning Recommendation Approval';
        }
    @endphp
    {{ $resolvedPageTitle }}
@endsection

@section('styles')
    {{--
    <li>
        <a href="{{ route('actions.buyers_list', ['id' => $application->id]) }}?url=buyers"
            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
            <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
            <span>View/Edit Buyers List</span>
        </a>
    </li> --}}

@endsection

@section('content')
    <style>
        /* Enhanced Badge Styles */
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .badge-approved {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #047857;
            border: 1px solid #10b981;
        }


        .badge-approved2 {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #04397e;
            border: 1px solid #04397e;
        }

        .badge-pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #b45309;
            border: 1px solid #f59e0b;
        }

        .badge-declined {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #b91c1c;
            border: 1px solid #ef4444;
        }

        .badge-neutral {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #b91c1c;
            border: 1px solid #ef4444;
        }

        /* Primary Application Styling */
        .primary-theme {
            --primary-color: #2563eb;
            --primary-light: #dbeafe;
            --primary-dark: #1d4ed8;
            --accent-color: #3b82f6;
        }

        .primary-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #2563eb;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1), 0 2px 4px -1px rgba(37, 99, 235, 0.06);
        }

        .primary-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0;
            border-bottom: 3px solid #1e40af;
        }

        .primary-table-header {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            font-weight: 600;
            color: #1e40af;
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #3b82f6;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Unit Application Styling */

        /* Enhanced Table Styling */
        .table-cell {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }

        .table-row:hover .table-cell {
            background-color: #f8fafc;
        }

        /* Enhanced Tab Styling */
        .tab-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: 2px solid #1e40af;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }

        .tab-primary:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
            box-shadow: 0 6px 8px -1px rgba(37, 99, 235, 0.4);
        }

        .tab-unit {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: 2px solid #065f46;
            box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.3);
            transform: translateY(-2px);
        }

        .tab-unit:hover {
            background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
            box-shadow: 0 6px 8px -1px rgba(5, 150, 105, 0.4);
        }

        .tab-inactive {
            background: white;
            color: #6b7280;
            border: 2px solid #d1d5db;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .tab-inactive:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Enhanced Stats Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .primary-stat-card {
            border-left: 4px solid #2563eb;
        }

        .unit-stat-card {
            border-left: 4px solid #059669;
        }

        /* Enhanced Action Buttons */
        .action-button {
            transition: all 0.2s ease;
            border-radius: 8px;
            font-weight: 500;
        }

        .action-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Disabled States */
        .disabled-link {
            color: #9ca3af !important;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }

        .disabled-icon {
            color: #9ca3af !important;
        }

        /* Application Type Indicators */
        .app-type-indicator {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
            color: white;
        }

        .primary-indicator {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        .unit-indicator {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        /* Enhanced Filter Styling */
        .filter-select {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
    </style>

    <!-- Add the script at the beginning of the content section to ensure it's loaded before the buttons -->
    <script>
        function showTab(tabId) {
            document.getElementById('primary-survey').classList.add('hidden');
            document.getElementById('unit-survey').classList.add('hidden');

            const primaryTab = document.getElementById('primary-survey-tab');
            const unitTab = document.getElementById('unit-survey-tab');

            primaryTab.classList.remove('tab-primary', 'tab-unit');
            unitTab.classList.remove('tab-primary', 'tab-unit');

            primaryTab.classList.add('tab-inactive');
            unitTab.classList.add('tab-inactive');

            const primaryBadge = primaryTab.querySelector('div:last-child');
            const unitBadge = unitTab.querySelector('div:last-child');

            primaryBadge.className = 'ml-2 bg-gray-200 px-2 py-1 rounded-full text-xs text-gray-600';
            unitBadge.className = 'ml-2 bg-gray-200 px-2 py-1 rounded-full text-xs text-gray-600';

            document.getElementById(tabId).classList.remove('hidden');

            const activeTab = document.getElementById(tabId + '-tab');
            activeTab.classList.remove('tab-inactive');

            if (tabId === 'primary-survey') {
                activeTab.classList.add('tab-primary');
                primaryBadge.className = 'ml-2 bg-white bg-opacity-20 px-2 py-1 rounded-full text-xs';
            } else if (tabId === 'unit-survey') {
                activeTab.classList.add('tab-unit');
                unitBadge.className = 'ml-2 bg-white bg-opacity-20 px-2 py-1 rounded-full text-xs';
            }
        }

        function customToggleDropdown(button, event) {
            event.preventDefault();
            event.stopPropagation();

            document.querySelectorAll('.action-menu').forEach(menu => {
                if (menu !== button.nextElementSibling) {
                    menu.classList.add('hidden');
                }
            });

            const menu = button.nextElementSibling;
            menu.classList.toggle('hidden');

            if (!menu.classList.contains('hidden')) {
                const rect = button.getBoundingClientRect();
                const menuHeight = menu.offsetHeight;
                const menuWidth = menu.offsetWidth;
                const viewportHeight = window.innerHeight;
                const viewportWidth = window.innerWidth;
                const margin = 16;

                let top = rect.bottom + 8;
                let left = rect.left + rect.width - menuWidth;

                if (top + menuHeight + margin > viewportHeight) {
                    top = rect.top - menuHeight - 8;
                }

                if (top < margin) {
                    top = margin;
                }

                if (left + menuWidth + margin > viewportWidth) {
                    left = viewportWidth - menuWidth - margin;
                }

                if (left < margin) {
                    left = margin;
                }

                const availableHeight = viewportHeight - (margin * 2);
                if (menuHeight > availableHeight) {
                    menu.style.maxHeight = availableHeight + 'px';
                    menu.style.overflowY = 'auto';
                } else {
                    menu.style.maxHeight = '';
                    menu.style.overflowY = '';
                }

                menu.style.top = top + 'px';
                menu.style.left = left + 'px';
            }
        }

        document.addEventListener('click', function (event) {
            document.querySelectorAll('.action-menu').forEach(menu => {
                if (!menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });
        });

        function filterTable(tableId, status) {
            const table = document.getElementById(tableId);
            if (!table) {
                return;
            }

            const headerCells = table.querySelectorAll('thead th');
            const targetHeader = 'Planning Recommendation Status';
            let statusColumnIndex = -1;

            headerCells.forEach((cell, index) => {
                if (cell.textContent.trim().toLowerCase() === targetHeader.toLowerCase()) {
                    statusColumnIndex = index;
                }
            });

            if (statusColumnIndex === -1) {
                statusColumnIndex = tableId === 'primaryApplicationTable' ? 5 : 5;
            }

            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const statusCell = rows[i].getElementsByTagName('td')[statusColumnIndex];
                if (!statusCell) {
                    continue;
                }

                const statusText = statusCell.textContent.trim();
                if (status === 'All...' || statusText.includes(status)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Tab persistence logic
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('unit_page') || urlParams.has('unit_status') || urlParams.has('unit_search')) {
                showTab('unit-survey');
            } else if (urlParams.has('primary_page') || urlParams.has('primary_status') || urlParams.has('primary_search')) {
                showTab('primary-survey');
            }

            const primaryFilter = document.getElementById('primaryStatusFilter');
            const unitFilter = document.getElementById('unitStatusFilter');

            if (primaryFilter) {
                primaryFilter.addEventListener('change', function () {
                    const url = new URL(window.location.href);
                    url.searchParams.set('primary_status', this.value);
                    url.searchParams.set('primary_page', 1); // Reset to page 1
                    window.location.href = url.toString();
                });
            }

            if (unitFilter) {
                unitFilter.addEventListener('change', function () {
                    const url = new URL(window.location.href);
                    url.searchParams.set('unit_status', this.value);
                    url.searchParams.set('unit_page', 1); // Reset to page 1
                    window.location.href = url.toString();
                });
            }

            // Search inputs handling
            const primarySearchInput = document.getElementById('primarySearchInput');
            if (primarySearchInput) {
                primarySearchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        const url = new URL(window.location.href);
                        url.searchParams.set('primary_search', this.value);
                        url.searchParams.set('primary_page', 1);
                        window.location.href = url.toString();
                    }
                });
            }

            const unitSearchInput = document.getElementById('unitSearchInput');
            if (unitSearchInput) {
                unitSearchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        const url = new URL(window.location.href);
                        url.searchParams.set('unit_search', this.value);
                        url.searchParams.set('unit_page', 1);
                        window.location.href = url.toString();
                    }
                });
            }
        });
    </script>

    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include($headerPartial ?? 'admin.header')

        <!-- Main Content -->
        <div class="p-6">
            <!-- Payments Overview -->

            <div class="grid grid-cols-3 gap-6 mb-8">
                <!-- Total Statistics Card -->
                <div class="stat-card relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-green-500"></div>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-gray-700 font-semibold text-lg">Total Planning Recommendations</h3>
                            <p class="text-gray-500 text-sm mt-1">Combined Primary & Unit Applications</p>
                        </div>
                        <div class="bg-gradient-to-br from-blue-100 to-green-100 p-3 rounded-full">
                            <i data-lucide="file-text" class="text-blue-600 w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="text-4xl font-bold text-gray-800 mb-3">
                        {{ $totalPrimaryApplications + $totalUnitApplications }}
                    </div>
                    <div class="flex items-center text-sm">
                        <div class="flex items-center bg-blue-50 px-2 py-1 rounded-full mr-2">
                            <i data-lucide="info" class="text-blue-600 w-4 h-4 mr-1"></i>
                            <span class="text-blue-600 font-medium">System Overview</span>
                        </div>
                    </div>
                </div>

                <!-- Primary Applications Card -->
                <div class="stat-card primary-stat-card relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-blue-600"></div>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-gray-700 font-semibold text-lg">Primary Applications</h3>
                            <p class="text-blue-600 text-sm mt-1 font-medium">Main Property Applications</p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full relative">
                            <i data-lucide="home" class="text-blue-600 w-6 h-6"></i>
                            <div class="primary-indicator">P</div>
                        </div>
                    </div>
                    <div class="text-4xl font-bold text-blue-700 mb-3">{{ $totalPrimaryApplications }}</div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="flex items-center bg-green-50 px-2 py-1 rounded">
                            <i data-lucide="check-circle" class="text-green-600 w-3 h-3 mr-1"></i>
                            <span class="text-green-700 font-semibold">{{ $approvedPrimaryApplications }}</span>
                        </div>
                        <div class="flex items-center bg-red-50 px-2 py-1 rounded">
                            <i data-lucide="x-circle" class="text-red-600 w-3 h-3 mr-1"></i>
                            <span class="text-red-700 font-semibold">{{ $rejectedPrimaryApplications }}</span>
                        </div>
                        <div class="flex items-center bg-amber-50 px-2 py-1 rounded">
                            <i data-lucide="clock" class="text-amber-600 w-3 h-3 mr-1"></i>
                            <span
                                class="text-amber-700 font-semibold">{{ $pendingPrimaryApplications ?? ($totalPrimaryApplications - $approvedPrimaryApplications - $rejectedPrimaryApplications) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Unit Applications Card -->
                <div class="stat-card unit-stat-card relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-green-500 to-green-600"></div>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-gray-700 font-semibold text-lg">Unit Applications</h3>
                            <p class="text-green-600 text-sm mt-1 font-medium">Subdivision Unit Applications</p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full relative">
                            <i data-lucide="layers" class="text-green-600 w-6 h-6"></i>
                            <div class="unit-indicator">U</div>
                        </div>
                    </div>
                    <div class="text-4xl font-bold text-green-700 mb-3">{{ $totalUnitApplications }}</div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="flex items-center bg-green-50 px-2 py-1 rounded">
                            <i data-lucide="check-circle" class="text-green-600 w-3 h-3 mr-1"></i>
                            <span class="text-green-700 font-semibold">{{ $approvedUnitApplications }}</span>
                        </div>
                        <div class="flex items-center bg-red-50 px-2 py-1 rounded">
                            <i data-lucide="x-circle" class="text-red-600 w-3 h-3 mr-1"></i>
                            <span class="text-red-700 font-semibold">{{ $rejectedUnitApplications }}</span>
                        </div>
                        <div class="flex items-center bg-amber-50 px-2 py-1 rounded">
                            <i data-lucide="clock" class="text-amber-600 w-3 h-3 mr-1"></i>
                            <span
                                class="text-amber-700 font-semibold">{{ $pendingUnitApplications ?? ($totalUnitApplications - $approvedUnitApplications - $rejectedUnitApplications) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Tab Navigation -->
            <div class="flex space-x-4 mb-8">
                <button onclick="showTab('primary-survey')" id="primary-survey-tab"
                    class="flex items-center px-6 py-3 text-sm font-semibold rounded-xl transition-all duration-300 ease-in-out focus:outline-none focus:ring-4 focus:ring-blue-200 tab-primary">
                    <i data-lucide="home" class="w-5 h-5 mr-2"></i>
                    <span>Primary Applications</span>
                    <div class="ml-2 bg-white bg-opacity-20 px-2 py-1 rounded-full text-xs">{{ $totalPrimaryApplications }}
                    </div>
                </button>
                <button onclick="showTab('unit-survey')" id="unit-survey-tab"
                    class="flex items-center px-6 py-3 text-sm font-semibold rounded-xl transition-all duration-300 ease-in-out focus:outline-none focus:ring-4 focus:ring-green-200 tab-inactive">
                    <i data-lucide="layers" class="w-5 h-5 mr-2"></i>
                    <span>Unit Applications</span>
                    <div class="ml-2 bg-gray-200 px-2 py-1 rounded-full text-xs text-gray-600">{{ $totalUnitApplications }}
                    </div>
                </button>
            </div>

            <!-- Primary Application  -->
            <div id="primary-survey">
                {{-- Planning Recommendations Status Breakdown hidden per request --}}
                {{-- @include('programmes.partials.planning_report') --}}
                <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold">Planning Recommendation</h2>
                            <p class="text-sm text-gray-600 mt-1">Primary Application</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                                <input type="text" id="primarySearchInput" placeholder="Search primary..."
                                    value="{{ request()->query('primary_search') }}"
                                    class="pl-10 pr-4 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div class="relative">
                                <select id="primaryStatusFilter"
                                    class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                                    <option value="All..." {{ request()->query('primary_status') == 'All...' ? 'selected' : '' }}>All Status</option>
                                    <option value="Approved" {{ request()->query('primary_status') == 'Approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="Pending" {{ request()->query('primary_status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="Declined" {{ request()->query('primary_status') == 'Declined' ? 'selected' : '' }}>Declined</option>
                                </select>
                                <i data-lucide="chevron-down"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                            </div>

                            <button class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                                <i data-lucide="upload" class="w-4 h-4 text-gray-600"></i>
                                <span>Import</span>
                            </button>

                            <button class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                                <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="primaryApplicationTable" class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-xs">
                                    <th class="table-header">ST FileNo/MLPP FileNo</th>
                                    <th class="table-header">Owner</th>
                                    <th class="table-header">
                                        <div class="flex items-center space-x-2">
                                            <i data-lucide="building-2" class="w-4 h-4"></i>
                                            <span>Land Use</span>
                                        </div>
                                    </th>
                                    <th class="table-header">Prerequisites</th>
                                    <th class="table-header">JSI Capture Status</th>
                                    <th class="table-header">JSI Approval Status</th>
                                    <th class="table-header">Planning Recommendation Status</th>
                                    <th class="table-header">Created By</th>
                                    <th class="table-header">Date Captured</th>
                                    <th class="table-header">Comment</th>
                                    <th class="table-header">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($applications as $application)
                                    <tr class="text-xs">
                                        @php
                                            $primaryJsiReport = DB::connection('sqlsrv')
                                                ->table('joint_site_inspection_reports')
                                                ->where('application_id', $application->id)
                                                ->whereNull('sub_application_id')
                                                ->select('is_approved', 'is_submitted', 'is_generated', 'inspection_date', 'approved_at')
                                                ->first();
                                        @endphp
                                        <td class="table-cell">
                                            @if($application->np_fileno)
                                                <span
                                                    class="badge badge-approved2 whitespace-nowrap">{{ $application->np_fileno }}</span>
                                            @else
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                            @if($application->fileno)
                                                <span
                                                    class="badge badge-approved2 whitespace-nowrap">{{ $application->fileno }}</span>
                                            @endif
                                        </td>
                                        <td class="table-cell">{{ $application->owner_name ?? '-' }}</td>
                                        <td class="table-cell">
                                            @if($application->land_use)
                                                @php
                                                    $landUseBadgeClass = match (strtolower($application->land_use)) {
                                                        'residential' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                        'commercial' => 'bg-green-100 text-green-800 border-green-200',
                                                        'industrial' => 'bg-red-100 text-red-800 border-red-200',
                                                        'mixed use' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                        'mixed' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                        default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                    };
                                                @endphp
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $landUseBadgeClass }} whitespace-nowrap">
                                                    {{ strtoupper($application->land_use) }}
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                                                    N/A
                                                </span>
                                            @endif
                                        </td>
                                        <td class="table-cell">
                                            @php
                                                $cofoRecord = DB::connection('sqlsrv')
                                                    ->table('Cofo')
                                                    ->where('mlsFNo', $application->fileno)
                                                    ->orWhere('kangisFileNo', $application->fileno)
                                                    ->orWhere('NewKANGISFileno', $application->fileno)
                                                    ->select('regNo')
                                                    ->first();
                                            @endphp
                                            @if(!$cofoRecord)
                                                <span class="badge badge-declined">
                                                    <i data-lucide="x-circle" class="w-4 h-4 mr-1 text-red-600"></i>
                                                    Cofo not captured
                                                </span>
                                            @elseif($cofoRecord->regNo == '0/0/0')
                                                <span class="badge badge-pending">
                                                    <i data-lucide="alert-triangle" class="w-4 h-4 mr-1 text-yellow-600"></i>
                                                    NO CofO
                                                </span>
                                            @else
                                                <span class="badge badge-approved">
                                                    <i data-lucide="check-circle" class="w-4 h-4 mr-1 text-green-600"></i>
                                                    CoFO
                                                </span>
                                            @endif
                                        </td>

                                        <td class="table-cell">
                                            @php
                                                $primaryJsiCaptureStatus = 'Not Captured';
                                                $primaryJsiCaptureBadgeClass = 'badge badge-neutral';
                                                $primaryJsiCaptureDate = '-';

                                                if ($primaryJsiReport) {
                                                    $primaryJsiCaptureStatus = 'Captured';
                                                    $primaryJsiCaptureBadgeClass = 'badge badge-approved';
                                                    $primaryJsiCaptureDate = $primaryJsiReport->inspection_date
                                                        ? \Carbon\Carbon::parse($primaryJsiReport->inspection_date)->format('d/m/Y')
                                                        : '-';
                                                }
                                            @endphp

                                            <span
                                                class="{{ $primaryJsiCaptureBadgeClass }}">{{ $primaryJsiCaptureStatus }}</span>
                                            {{ $primaryJsiCaptureDate }}
                                        </td>

                                        <td class="table-cell">
                                            @php
                                                $primaryJsiApprovalStatus = 'Pending';
                                                $primaryJsiApprovalBadgeClass = 'badge badge-neutral';
                                                $primaryJsiApprovalDate = '-';
                                                $primaryJsiApproved = false;

                                                if ($primaryJsiReport) {
                                                    if ((int) ($primaryJsiReport->is_approved ?? 0) === 1) {
                                                        $primaryJsiApprovalStatus = 'Approved';
                                                        $primaryJsiApprovalBadgeClass = 'badge badge-approved';
                                                        $primaryJsiApproved = true;
                                                        $primaryJsiApprovalDate = $primaryJsiReport->approved_at
                                                            ? \Carbon\Carbon::parse($primaryJsiReport->approved_at)->format('d/m/Y h:i A')
                                                            : '-';
                                                    } else {
                                                        $primaryJsiApprovalStatus = 'Pending';
                                                        $primaryJsiApprovalBadgeClass = 'badge badge-pending';
                                                    }
                                                }
                                            @endphp

                                            <span
                                                class="{{ $primaryJsiApprovalBadgeClass }}">{{ $primaryJsiApprovalStatus }}</span>
                                            {{ $primaryJsiApprovalDate }}
                                        </td>

                                        <td class="table-cell">
                                            @php
                                                $primaryPlanningStatus = strtolower($application->planning_recommendation_status ?? '');
                                                $primaryPlanningLabel = 'Pending';
                                                $primaryPlanningBadgeClass = 'badge badge-pending';
                                                $primaryPlanningDate = '-';

                                                if ($primaryPlanningStatus === 'approved') {
                                                    $primaryPlanningLabel = 'Approved';
                                                    $primaryPlanningBadgeClass = 'badge badge-approved';
                                                    $primaryPlanningDate = $application->planning_approval_date
                                                        ? \Carbon\Carbon::parse($application->planning_approval_date)->format('d/m/Y h:i A')
                                                        : '-';
                                                } elseif ($primaryPlanningStatus === 'declined') {
                                                    $primaryPlanningLabel = 'Declined';
                                                    $primaryPlanningBadgeClass = 'badge badge-declined';
                                                    $primaryPlanningDate = $application->planning_approval_date
                                                        ? \Carbon\Carbon::parse($application->planning_approval_date)->format('d/m/Y h:i A')
                                                        : '-';
                                                } elseif (!$primaryJsiApproved) {
                                                    $primaryPlanningLabel = 'Pending JSI';
                                                }
                                            @endphp

                                            <span class="{{ $primaryPlanningBadgeClass }}">{{ $primaryPlanningLabel }}</span>
                                            {{ $primaryPlanningDate }}
                                        </td>
                                        <td class="table-cell">{{ $application->created_by_name ?? '—' }}</td>
                                        <td class="table-cell">
                                            @if($application->created_at)
                                                {{ \Carbon\Carbon::parse($application->created_at)->format('d/m/Y h:i A') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="table-cell">{{ $application->comments ?? '-' }}</td>


                                        <td class="table-cell relative">
                                            @include('programmes.approvals.action_menu.pp_action_menu', ['jsiReport' => $primaryJsiReport])
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="text-xs">
                                        <td colspan="11" class="table-cell text-center py-4 text-gray-500">No primary survey
                                            records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 px-4 py-3 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                        {{ $applications->appends([
        'unit_page' => request()->query('unit_page'),
        'url' => request()->query('url'),
        'primary_status' => request()->query('primary_status'),
        'primary_search' => request()->query('primary_search'),
        'unit_status' => request()->query('unit_status'),
        'unit_search' => request()->query('unit_search')
    ])->links() }}
                    </div>
                </div>
            </div>


            <!-- Unit Application  -->
            <div id="unit-survey" class="hidden">
                {{-- Planning Recommendations Status Breakdown hidden per request --}}
                {{-- @include('programmes.partials.unit_planning_report') --}}
                <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold">Planning Recommendation</h2>
                            <p class="text-sm text-gray-600 mt-1">Unit Application</p>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                                <input type="text" id="unitSearchInput" placeholder="Search units..."
                                    value="{{ request()->query('unit_search') }}"
                                    class="pl-10 pr-4 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>

                            <div class="relative">
                                <select id="unitStatusFilter"
                                    class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 appearance-none">
                                    <option value="All..." {{ request()->query('unit_status') == 'All...' ? 'selected' : '' }}>All Status</option>
                                    <option value="Approved" {{ request()->query('unit_status') == 'Approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="Pending" {{ request()->query('unit_status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="Declined" {{ request()->query('unit_status') == 'Declined' ? 'selected' : '' }}>Declined</option>
                                </select>
                                <i data-lucide="chevron-down"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                            </div>


                            <button class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                                <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="unitApplicationTable" class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-xs">
                                    <th class="table-header">ST FileNo/MLPP FileNo</th>
                                    <th class="table-header">Owner</th>
                                    <th class="table-header">
                                        <div class="flex items-center space-x-2">
                                            <i data-lucide="building-2" class="w-4 h-4"></i>
                                            <span>Land Use</span>
                                        </div>
                                    </th>
                                    <th class="table-header">Unit Type</th>

                                    <th class="table-header">JSI Capture Status</th>
                                    <th class="table-header">JSI Approval Status</th>
                                    <th class="table-header">Planning Recommendation Status</th>
                                    <th class="table-header">Created By</th>
                                    <th class="table-header">Date Captured</th>
                                    <th class="table-header">Comment</th>
                                    <th class="table-header">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($unitApplications as $unitApplication)
                                    <tr class="text-xs">
                                        @php
                                            $unitJsiReport = DB::connection('sqlsrv')
                                                ->table('joint_site_inspection_reports')
                                                ->where('sub_application_id', $unitApplication->id)
                                                ->whereNull('application_id')
                                                ->select('is_approved', 'is_submitted', 'is_generated', 'inspection_date', 'approved_at')
                                                ->first();
                                        @endphp
                                        <td class="table-cell">
                                            @php
                                                $unitDisplayNp = $unitApplication->np_fileno ?? $unitApplication->mother_np_fileno;
                                            @endphp
                                            @if($unitDisplayNp)
                                                <span class="badge badge-approved2 whitespace-nowrap">{{ $unitDisplayNp }}</span>
                                            @endif
                                            @if($unitApplication->fileno)
                                                <span
                                                    class="badge badge-approved2 whitespace-nowrap">{{ $unitApplication->fileno }}</span>
                                            @elseif(!$unitDisplayNp)
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                            @if($unitApplication->mls_fileno)
                                                <span
                                                    class="badge badge-approved2 whitespace-nowrap">{{ $unitApplication->mls_fileno }}</span>
                                            @endif
                                        </td>
                                        <td class="table-cell">{{ $unitApplication->owner_name ?? '-' }}</td>
                                        <td class="table-cell">
                                            @php
                                                $unitLandUse = $unitApplication->land_use ?? null;
                                            @endphp
                                            @if($unitLandUse)
                                                @php
                                                    $unitLandUseBadgeClass = match (strtolower($unitLandUse)) {
                                                        'residential' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                        'commercial' => 'bg-green-100 text-green-800 border-green-200',
                                                        'industrial' => 'bg-red-100 text-red-800 border-red-200',
                                                        'mixed use' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                        'mixed-use' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                        default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                    };
                                                @endphp
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $unitLandUseBadgeClass }} whitespace-nowrap">
                                                    {{ strtoupper($unitLandUse) }}
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                                                    N/A
                                                </span>
                                            @endif
                                        </td>
                                        <td class="table-cell">
                                            @php
                                                $displayUnitType = $unitApplication->unit_type;
                                                if (empty($displayUnitType)) {
                                                    $displayUnitType = ((int) ($unitApplication->is_sua_unit ?? 0) === 1) ? 'SUA' : 'PUA';
                                                }
                                            @endphp
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-indigo-50 text-indigo-700 border-indigo-100 uppercase">
                                                {{ $displayUnitType }}
                                            </span>
                                        </td>


                                        <td class="table-cell">
                                            @php
                                                $unitJsiCaptureStatus = 'Not Captured';
                                                $unitJsiCaptureBadgeClass = 'badge badge-neutral';
                                                $unitJsiCaptureDate = '-';

                                                if ($unitJsiReport) {
                                                    $unitJsiCaptureStatus = 'Captured';
                                                    $unitJsiCaptureBadgeClass = 'badge badge-approved';
                                                    $unitJsiCaptureDate = $unitJsiReport->inspection_date
                                                        ? \Carbon\Carbon::parse($unitJsiReport->inspection_date)->format('d/m/Y')
                                                        : '-';
                                                }
                                            @endphp

                                            <span class="{{ $unitJsiCaptureBadgeClass }}">{{ $unitJsiCaptureStatus }}</span>
                                            {{ $unitJsiCaptureDate }}
                                        </td>

                                        <td class="table-cell">
                                            @php
                                                $unitJsiApprovalStatus = 'Pending';
                                                $unitJsiApprovalBadgeClass = 'badge badge-neutral';
                                                $unitJsiApprovalDate = '-';
                                                $unitJsiApproved = false;

                                                if ($unitJsiReport) {
                                                    if ((int) ($unitJsiReport->is_approved ?? 0) === 1) {
                                                        $unitJsiApprovalStatus = 'Approved';
                                                        $unitJsiApprovalBadgeClass = 'badge badge-approved';
                                                        $unitJsiApproved = true;
                                                        $unitJsiApprovalDate = $unitJsiReport->approved_at
                                                            ? \Carbon\Carbon::parse($unitJsiReport->approved_at)->format('d/m/Y h:i A')
                                                            : '-';
                                                    } else {
                                                        $unitJsiApprovalStatus = 'Pending';
                                                        $unitJsiApprovalBadgeClass = 'badge badge-pending';
                                                    }
                                                }
                                            @endphp

                                            <span class="{{ $unitJsiApprovalBadgeClass }}">{{ $unitJsiApprovalStatus }}</span>
                                            {{ $unitJsiApprovalDate }}
                                        </td>

                                        <td class="table-cell">
                                            @php
                                                $unitPlanningStatus = strtolower($unitApplication->planning_recommendation_status ?? '');
                                                $unitPlanningLabel = 'Pending';
                                                $unitPlanningBadgeClass = 'badge badge-pending';
                                                $unitPlanningDate = '-';

                                                $isSua_local = ($unitApplication->is_sua_unit ?? 0) == 1 || (strtoupper($unitApplication->unit_type ?? '') === 'SUA');
                                                $motherApproved = strtolower($unitApplication->mother_planning_status ?? '') === 'approved';

                                                if ($unitPlanningStatus === 'approved') {
                                                    $unitPlanningLabel = 'Approved';
                                                    $unitPlanningBadgeClass = 'badge badge-approved';
                                                    $unitPlanningDate = $unitApplication->planning_approval_date
                                                        ? \Carbon\Carbon::parse($unitApplication->planning_approval_date)->format('d/m/Y h:i A')
                                                        : '-';
                                                } elseif ($unitPlanningStatus === 'declined') {
                                                    $unitPlanningLabel = 'Declined';
                                                    $unitPlanningBadgeClass = 'badge badge-declined';
                                                    $unitPlanningDate = $unitApplication->planning_approval_date
                                                        ? \Carbon\Carbon::parse($unitApplication->planning_approval_date)->format('d/m/Y h:i A')
                                                        : '-';
                                                } elseif (!$isSua_local && $motherApproved) {
                                                    $unitPlanningLabel = 'Approved (Primary)';
                                                    $unitPlanningBadgeClass = 'badge badge-approved';
                                                } elseif (!$unitJsiApproved) {
                                                    $unitPlanningLabel = 'Pending JSI';
                                                }
                                            @endphp

                                            <span class="{{ $unitPlanningBadgeClass }}">{{ $unitPlanningLabel }}</span>
                                            {{ $unitPlanningDate }}
                                        </td>

                                        <td class="table-cell">{{ $unitApplication->created_by_name ?? '—' }}</td>
                                        <td class="table-cell">
                                            @if($unitApplication->created_at)
                                                {{ \Carbon\Carbon::parse($unitApplication->created_at)->format('d/m/Y h:i A') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="table-cell">{{ $unitApplication->comments ?? '-' }}</td>
                                        <td class="table-cell relative">
                                            <!-- Dropdown Toggle Button -->
                                            <button type="button" class="p-2 hover:bg-gray-100 focus:outline-none rounded-full"
                                                onclick="customToggleDropdown(this, event)">
                                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                            </button>

                                            <!-- Dropdown Menu Unit Application Surveys -->
                                            <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">
                                                @php
                                                    $parentApplicationId = $unitApplication->main_application_id
                                                        ?? ($unitApplication->mother_application_id ?? null);

                                                    if (!$parentApplicationId && !empty($unitApplication->mls_fileno)) {
                                                        $parentApplicationId = DB::connection('sqlsrv')
                                                            ->table('mother_applications')
                                                            ->where('fileno', $unitApplication->mls_fileno)
                                                            ->value('id');
                                                    }

                                                    if (!isset($unitJsiReport) || $unitJsiReport === null) {
                                                        $unitJsiReport = DB::connection('sqlsrv')
                                                            ->table('joint_site_inspection_reports')
                                                            ->where('sub_application_id', $unitApplication->id)
                                                            ->whereNull('application_id')
                                                            ->orderByDesc('id')
                                                            ->first();
                                                    }
                                                    $unitReportSubmitted = $unitJsiReport && (bool) $unitJsiReport->is_submitted;
                                                    $unitIsGenerated = $unitJsiReport && (bool) $unitJsiReport->is_generated;
                                                    $unitIsApproved = $unitJsiReport && (int) ($unitJsiReport->is_approved ?? 0) === 1;
                                                    $unitHasJsiReport = (bool) $unitJsiReport;

                                                    $currentUrlMode = request()->query('url');
                                                    $unitInspectionRouteParams = ['subApplication' => $unitApplication->id];
                                                    if ($currentUrlMode) {
                                                        $unitInspectionRouteParams['url'] = $currentUrlMode;
                                                    }
                                                    $unitInspectionRouteParams['return'] = request()->fullUrl();
                                                    $unitInspectionDetailsLink = route('sub-actions.planning-recommendation.joint-inspection.details', $unitInspectionRouteParams);
                                                    $unitInspectionEditLink = route('sub-actions.planning-recommendation.joint-inspection.edit', $unitInspectionRouteParams);
                                                    $unitCanEditInspection = $unitHasJsiReport && !$unitIsApproved;
                                                    $unitInspectionLink = $unitCanEditInspection ? $unitInspectionEditLink : $unitInspectionDetailsLink;
                                                    $unitInspectionLabel = $unitCanEditInspection ? 'View/Edit Inspection Details' : 'View Inspection Details';
                                                    $unitInspectionIcon = $unitCanEditInspection ? 'file-edit' : 'layout-dashboard';

                                                    $unitPlanningStatusNormalized = strtolower(trim($unitApplication->planning_recommendation_status ?? ''));
                                                    $unitPlanningLocked = in_array($unitPlanningStatusNormalized, ['approved', 'declined'], true);

                                                    $isSua_local = ($unitApplication->is_sua_unit ?? 0) == 1 || (strtoupper($unitApplication->unit_type ?? '') === 'SUA');
                                                    $motherApproved = strtolower($unitApplication->mother_planning_status ?? '') === 'approved';

                                                    $unitCanApproveDecline = ($unitIsApproved || (!$isSua_local && $motherApproved)) && !$unitPlanningLocked;
                                                    $unitApproveDeclineTooltip = $unitIsApproved || (!$isSua_local && $motherApproved)
                                                        ? 'Planning recommendation already resolved'
                                                        : 'Joint site inspection must be approved first';
                                                @endphp

                                                @if($currentUrlMode === 'view')
                                                    <li>
                                                        <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}"
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                            <i data-lucide="eye" class="w-4 h-4 text-sky-600"></i>
                                                            <span>View Unit Application</span>
                                                        </a>
                                                    </li>

                                                    <li>
                                                        @if($unitHasJsiReport)
                                                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link"
                                                                title="Inspection details already captured">
                                                                <i data-lucide="clipboard-list" class="w-4 h-4 disabled-icon"></i>
                                                                <span>Enter Inspection Details</span>
                                                            </div>
                                                        @else
                                                            <a href="#"
                                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 joint-inspection-trigger"
                                                                data-application-id="{{ $parentApplicationId ?? '' }}"
                                                                data-sub-application-id="{{ $unitApplication->id }}">
                                                                <i data-lucide="clipboard-list" class="w-4 h-4 text-purple-600"></i>
                                                                <span>Enter Inspection Details</span>
                                                            </a>
                                                        @endif
                                                    </li>

                                                    <li>
                                                        @if(!$unitHasJsiReport)
                                                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link"
                                                                title="No inspection record yet">
                                                                <i data-lucide="layout-dashboard" class="w-4 h-4 disabled-icon"></i>
                                                                <span>View/Edit Inspection Details</span>
                                                            </div>
                                                        @else
                                                            <a href="{{ $unitInspectionLink }}"
                                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                                <i data-lucide="{{ $unitInspectionIcon }}"
                                                                    class="w-4 h-4 text-indigo-600"></i>
                                                                <span>{{ $unitInspectionLabel }}</span>
                                                            </a>
                                                        @endif
                                                    </li>

                                                    <li>
                                                        <a href="{{ route('programmes.planning.unit.complete-survey', $unitApplication->id) }}"
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                            <i data-lucide="clipboard-list" class="w-4 h-4 text-teal-600"></i>
                                                            <span>Complete Survey Details</span>
                                                        </a>
                                                    </li>

                                                    <li>
                                                        @if($unitApplication->planning_recommendation_status == 'Approved' || (!$isSua_local && $motherApproved))
                                                            <a href="{{ route('sub-actions.recommendation', ['id' => $unitApplication->id]) }}"
                                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                                                <span>View Planning Recommendation </span>
                                                            </a>
                                                        @else
                                                            <div
                                                                class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link">
                                                                <i data-lucide="check-circle" class="w-4 h-4 disabled-icon"></i>
                                                                <span>View Planning Recommendation </span>
                                                            </div>
                                                        @endif
                                                    </li>
                                                @elseif($currentUrlMode === 'approval')
                                                    <li>
                                                        <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}"
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                            <i data-lucide="eye" class="w-4 h-4 text-sky-600"></i>
                                                            <span>View Unit Application</span>
                                                        </a>
                                                    </li>

                                                    {{-- <li>
                                                        @if(!$unitHasJsiReport)
                                                        <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link"
                                                            title="No inspection record yet">
                                                            <i data-lucide="layout-dashboard" class="w-4 h-4 disabled-icon"></i>
                                                            <span>View Inspection Details Tab</span>
                                                        </div>
                                                        @else
                                                        <a href="{{ $unitInspectionDetailsLink }}"
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                            <i data-lucide="layout-dashboard" class="w-4 h-4 text-indigo-600"></i>
                                                            <span>View Inspection Details Tab</span>
                                                        </a>
                                                        @endif
                                                    </li> --}}
                                                    <li>
                                                        @if(($unitHasJsiReport || (!$isSua_local && $motherApproved)) && !($unitIsApproved && $unitApplication->planning_recommendation_status == 'Approved'))
                                                            <a href="{{ route('sub-actions.recommendation', ['id' => $unitApplication->id]) }}?url=recommendation"
                                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                                <i data-lucide="check-circle" class="w-4 h-4 text-amber-600"></i>
                                                                <span>Approve/Decline</span>
                                                            </a>
                                                        @else
                                                            @php
                                                                $disableTooltip = (!$unitHasJsiReport && ($isSua_local || !$motherApproved))
                                                                    ? 'Joint site inspection must be captured first'
                                                                    : 'Planning recommendation already resolved';
                                                            @endphp
                                                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link"
                                                                title="{{ $disableTooltip }}">
                                                                <i data-lucide="check-circle" class="w-4 h-4 disabled-icon"></i>
                                                                <span>Approve/Decline</span>
                                                            </div>
                                                        @endif
                                                    </li>

                                                    <li>
                                                        @if($unitApplication->planning_recommendation_status == 'Approved' || (!$isSua_local && $motherApproved))
                                                            <a href="{{ route('sub-actions.recommendation', ['id' => $unitApplication->id]) }}?url=recommendation"
                                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                                                <span>View Planning Recommendation </span>
                                                            </a>
                                                        @else
                                                            <div
                                                                class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link">
                                                                <i data-lucide="check-circle" class="w-4 h-4 disabled-icon"></i>
                                                                <span>View Planning Recommendation </span>
                                                            </div>
                                                        @endif
                                                    </li>
                                                @else
                                                    <li>
                                                        <div
                                                            class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link">
                                                            <i data-lucide="shield-alert" class="w-4 h-4 disabled-icon"></i>
                                                            <span>No access</span>
                                                        </div>
                                                    </li>
                                                @endif

                                                {{-- ST keeps no recommendation record — the decision is three
                                                     columns on the application — so Master Delete here unmakes
                                                     the decision rather than deleting a row. The application,
                                                     its memo and its RofO all survive. Supper Admin only. --}}
                                                @if(auth()->user()?->assign_role === 'Supper Admin' && !empty($unitApplication->planning_approval_date))
                                                    <li>
                                                        <button type="button"
                                                            onclick="masterDeleteStRecommendation('unit', {{ (int) $unitApplication->id }}, @js($unitApplication->fileno))"
                                                            class="w-full text-left px-4 py-2 flex items-center space-x-2 text-red-700 hover:bg-red-50 font-semibold">
                                                            <i data-lucide="shield-alert" class="w-4 h-4"></i>
                                                            <span>Master Delete</span>
                                                        </button>
                                                    </li>
                                                @endif
                                            </ul>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="text-xs">
                                        <td colspan="11" class="table-cell text-center py-4 text-gray-500">No unit applications
                                            found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 px-4 py-3 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                        {{ $unitApplications->appends([
        'primary_page' => request()->query('primary_page'),
        'url' => request()->query('url'),
        'primary_status' => request()->query('primary_status'),
        'primary_search' => request()->query('primary_search'),
        'unit_status' => request()->query('unit_status'),
        'unit_search' => request()->query('unit_search')
    ])->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Footer -->
        @include($footerPartial ?? 'admin.footer')
    </div>

    @php
        // Get shared utilities and unit data for JSI modal
        $sharedUtilitiesOptions = [];
        $unitDataOptions = [];

        $applicationIds = isset($applications) && $applications->isNotEmpty()
            ? $applications->pluck('id')->filter()->values()->toArray()
            : [];

        $unitApplicationIds = isset($unitApplications) && !empty($unitApplications)
            ? collect($unitApplications)->pluck('id')->filter()->values()->toArray()
            : [];

        if (!empty($applicationIds) || !empty($unitApplicationIds)) {
            try {
                $sharedUtilitiesQuery = DB::connection('sqlsrv')
                    ->table('shared_utilities');

                if (!empty($unitApplicationIds)) {
                    $sharedUtilitiesQuery->whereIn('sub_application_id', $unitApplicationIds);
                }

                if (!empty($applicationIds)) {
                    if (!empty($unitApplicationIds)) {
                        $sharedUtilitiesQuery->orWhereIn('application_id', $applicationIds);
                    } else {
                        $sharedUtilitiesQuery->whereIn('application_id', $applicationIds);
                    }
                }

                $sharedUtilitiesOptions = $sharedUtilitiesQuery
                    ->pluck('utility_type')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($unitApplicationIds)) {
                    $unitDataOptions = DB::connection('sqlsrv')
                        ->table('subapplications')
                        ->whereIn('id', $unitApplicationIds)
                        ->select('id', 'main_application_id', 'unit_number', 'block_number', 'first_name', 'surname', 'applicant_title')
                        ->get()
                        ->mapWithKeys(function ($unit) {
                            return [
                                $unit->id => [
                                    'id' => $unit->id,
                                    'main_application_id' => $unit->main_application_id,
                                    'unit_number' => $unit->unit_number,
                                    'block_number' => $unit->block_number,
                                    'first_name' => $unit->first_name,
                                    'surname' => $unit->surname,
                                    'applicant_title' => $unit->applicant_title,
                                    'buyer_name' => trim(($unit->first_name ?? '') . ' ' . ($unit->surname ?? '')),
                                ],
                            ];
                        })
                        ->toArray();
                }

                // Debug the query results
                \Log::info('JSI Data Query', [
                    'applicationIds' => $applicationIds,
                    'unitApplicationIds' => $unitApplicationIds,
                    'foundUtilities' => $sharedUtilitiesOptions,
                    'foundUnits' => count($unitDataOptions)
                ]);
            } catch (\Exception $e) {
                \Log::error('JSI Data Query Error', ['error' => $e->getMessage()]);
                $sharedUtilitiesOptions = [];
                $unitDataOptions = [];
            }
        } else {
            \Log::info('JSI Data - No applications available');
        }
    @endphp

    <!-- Include Joint Site Inspection Modal -->
    @include('programmes.partials.joint_site_inspection_modal')

    <script src="{{ asset('js/master-delete.js') }}"></script>
    <script>
        /**
         * Master Delete for an ST planning recommendation.
         *
         * ST stores no recommendation record — the decision is three columns on the
         * application — so this unmakes the decision rather than deleting a row. It
         * is named and gated as a Master Delete because from the operator's side it
         * is the same act as the Land and SLTR deletes: an approval put on a file in
         * error, taken back off it.
         */
        function masterDeleteStRecommendation(scope, id, fileNo) {
            MasterDelete.confirm({
                url: '{{ route('programmes.approvals.planning_recomm.master-destroy') }}',
                body: { scope: scope, id: id },
                reference: fileNo,
                title: 'Master Delete Planning Recommendation',
                lead: 'This permanently clears the planning recommendation recorded against <b>' + fileNo + '</b>. It cannot be undone.',
                targets: [
                    'The recommendation decision (Approved / Declined)',
                    'Its approval date',
                    'Its recommendation comment'
                ],
                keeps: 'The application, its units, its ST memo, its RofO and its file number are all untouched — the file simply goes back to Pending.'
            });
        }
    </script>

    <script>
        // Make shared utilities and unit data available to the modal
        window.sharedUtilitiesOptions = @json($sharedUtilitiesOptions);
        window.unitDataOptions = @json($unitDataOptions);

        console.log('DEBUG - Shared utilities loaded:', window.sharedUtilitiesOptions);
        console.log('DEBUG - Shared utilities count:', window.sharedUtilitiesOptions ? window.sharedUtilitiesOptions.length : 0);
        console.log('DEBUG - Unit data loaded:', window.unitDataOptions);
        console.log('DEBUG - Unit data count:', window.unitDataOptions ? Object.keys(window.unitDataOptions).length : 0);

        // Also make it available as a global variable for the modal
        if (typeof sharedUtilitiesOptions === 'undefined') {
            window.sharedUtilitiesOptions = @json($sharedUtilitiesOptions);
        }
        if (typeof unitDataOptions === 'undefined') {
            window.unitDataOptions = @json($unitDataOptions);
        }
    </script>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.GlobalFileNoModal) {
                window.GlobalFileNoModal.init();
            }

            @if(session('swal_success') && session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: {!! json_encode(session('success')) !!},
                    timer: 2000,
                    showConfirmButton: false
                });
            @endif

            @if(session('swal_error') && session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: {!! json_encode(session('error')) !!}
                });
            @endif
                                                    });
    </script>

    <!-- Include Joint Site Inspection JavaScript -->
    @include('programmes.partials.joint_site_inspection_js')
@endsection
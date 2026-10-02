@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('styles')
 
@endsection

@section('content')
<style>
    .badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .badge-approved {
      background-color: #d1fae5;
      color: #059669;
    }
    .badge-pending {
      background-color: #fef3c7;
      color: #d97706;
    }
    .badge-declined {
      background-color: #fee2e2;
      color: #dc2626;
    }
    .table-header {
      background-color: #f9fafb;
      font-weight: 500;
      color: rgb(13, 136, 13);
      text-align: left;
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #e5e7eb;
    }
    .table-cell {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #e5e7eb;
    }
    .tab-content {
      display: block;
    }
    .tab-content.hidden {
      display: none;
    }
    .status-completed {
      background-color: #d1fae5;
      color: #059669;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
    }
    .status-in-progress {
      background-color: #fef3c7;
      color: #d97706;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
    }
    .status-not-started {
      background-color: #fee2e2;
      color: #dc2626;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
    }
    .btn-disabled {
      background-color: #9ca3af !important;
      cursor: not-allowed !important;
      opacity: 0.6;
    }
    .btn-disabled:hover {
      background-color: #9ca3af !important;
      transform: none !important;
    }
    .file-count-badge {
      background-color: #3b82f6;
      color: white;
      font-size: 0.625rem;
      padding: 0.125rem 0.375rem;
      border-radius: 9999px;
      margin-left: 0.5rem;
    }
    .file-count-badge-purple {
      background-color: #8b5cf6;
      color: white;
      font-size: 0.625rem;
      padding: 0.125rem 0.375rem;
      border-radius: 9999px;
      margin-left: 0.5rem;
    }
</style>

<!-- Tab switching script -->
<script>
  function showTab(tabId) {
    // Hide all tab contents
    document.getElementById('primary-applications').classList.add('hidden');
    document.getElementById('unit-applications').classList.add('hidden');
    
    // Reset all tab buttons
    document.getElementById('primary-applications-tab').classList.remove('bg-blue-600', 'text-white');
    document.getElementById('primary-applications-tab').classList.add('bg-white', 'text-gray-700', 'border', 'border-gray-200');
    document.getElementById('unit-applications-tab').classList.remove('bg-purple-600', 'text-white');
    document.getElementById('unit-applications-tab').classList.add('bg-white', 'text-gray-700', 'border', 'border-gray-200');
    
    // Show selected tab content
    document.getElementById(tabId).classList.remove('hidden');
    
    // Highlight active tab button
    if (tabId === 'primary-applications') {
      document.getElementById('primary-applications-tab').classList.remove('bg-white', 'text-gray-700', 'border', 'border-gray-200');
      document.getElementById('primary-applications-tab').classList.add('bg-blue-600', 'text-white');
    } else {
      document.getElementById('unit-applications-tab').classList.remove('bg-white', 'text-gray-700', 'border', 'border-gray-200');
      document.getElementById('unit-applications-tab').classList.add('bg-purple-600', 'text-white');
    }
  }
</script>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <!-- Tab Navigation --<div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold">eRegistry</h2>
                    <p class="text-sm text-gray-600 mt-1">Electronic Registry Management System</p>
                </div>
                
                <!-- Summary Cards -->
                <div class="flex gap-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-center min-w-[120px]">
                        <div class="text-2xl font-bold text-blue-600">
                            {{ isset($primaryApplications) ? count($primaryApplications) : 0 }}
                        </div>
                        <div class="text-xs text-blue-700 font-medium">Primary Apps</div>
                        <div class="text-xs text-blue-600">
                            @if(isset($primaryApplications))
                                @php
                                    $completedPrimary = collect($primaryApplications)->where('processing_status', 'Completed')->count();
                                    $inProgressPrimary = collect($primaryApplications)->where('processing_status', 'In Progress')->count();
                                @endphp
                                {{ $completedPrimary }} completed, {{ $inProgressPrimary }} in progress
                            @else
                                0 completed, 0 in progress
                            @endif
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 border border-purple-200 rounded-lg px-4 py-3 text-center min-w-[120px]">
                        <div class="text-2xl font-bold text-purple-600">
                            {{ isset($unitApplications) ? count($unitApplications) : 0 }}
                        </div>
                        <div class="text-xs text-purple-700 font-medium">Unit Apps</div>
                        <div class="text-xs text-purple-600">
                            @if(isset($unitApplications))
                                @php
                                    $completedUnit = collect($unitApplications)->where('processing_status', 'Completed')->count();
                                    $inProgressUnit = collect($unitApplications)->where('processing_status', 'In Progress')->count();
                                @endphp
                                {{ $completedUnit }} completed, {{ $inProgressUnit }} in progress
                            @else
                                0 completed, 0 in progress
                            @endif
                        </div>
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-center min-w-[120px]">
                        <div class="text-2xl font-bold text-green-600">
                            @php
                                $totalPrimary = isset($primaryApplications) ? count($primaryApplications) : 0;
                                $totalUnit = isset($unitApplications) ? count($unitApplications) : 0;
                                $totalApps = $totalPrimary + $totalUnit;
                            @endphp
                            {{ $totalApps }}
                        </div>
                        <div class="text-xs text-green-700 font-medium">Total Apps</div>
                        <div class="text-xs text-green-600">
                            @php
                                $totalCompleted = 0;
                                $totalInProgress = 0;
                                if(isset($primaryApplications)) {
                                    $totalCompleted += collect($primaryApplications)->where('processing_status', 'Completed')->count();
                                    $totalInProgress += collect($primaryApplications)->where('processing_status', 'In Progress')->count();
                                }
                                if(isset($unitApplications)) {
                                    $totalCompleted += collect($unitApplications)->where('processing_status', 'Completed')->count();
                                    $totalInProgress += collect($unitApplications)->where('processing_status', 'In Progress')->count();
                                }
                            @endphp
                            {{ $totalCompleted }} completed, {{ $totalInProgress }} in progress
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Buttons -->
            <div class="flex space-x-1 mb-6">
                <button id="primary-applications-tab" onclick="showTab('primary-applications')" 
                        class="px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-blue-600 text-white">
                    <i data-lucide="building" class="w-4 h-4 mr-2 inline"></i>
                    Primary Applications
                </button>
                <button id="unit-applications-tab" onclick="showTab('unit-applications')" 
                        class="px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-white text-gray-700 border border-gray-200 hover:bg-gray-50">
                    <i data-lucide="home" class="w-4 h-4 mr-2 inline"></i>
                    Unit Applications
                </button>
            </div>
            
            <!-- Primary Applications Tab Content -->
            <div id="primary-applications" class="tab-content">
                <!-- Search Filters -->
                <div class="mb-4">
                    <button id="toggle-search-filters-btn" class="flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span id="toggle-search-filters-text">Show Filters</span>
                    </button>
                </div>

                <!-- Filter Section (Initially Hidden) -->
                <div id="search-filters-section" class="mb-6 bg-white p-5 rounded-lg border border-gray-200 shadow-sm hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Smart Search - Primary Applications
                        </h3>
                        <div id="results-counter" class="text-sm text-gray-500 hidden">
                            <span id="count">0</span> results found
                        </div>
                    </div>
                    
                    <!-- Main search bar -->
                    <div class="relative mb-4">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" id="global-search" class="w-full pl-10 pr-10 py-3 text-base rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 transition" placeholder="Search across all fields...">
                        <button type="button" id="clear-search" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Advanced filters toggle -->
                    <div class="mb-3">
                        <button id="toggle-filters" class="text-sm text-blue-600 hover:text-blue-800 focus:outline-none flex items-center">
                            <svg id="chevron-down" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                            <svg id="chevron-up" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                            Advanced Filters
                        </button>
                    </div>
                    
                    <!-- Advanced filters section -->
                    <div id="advanced-filters" class="hidden transition-all duration-300">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-4">
                            <div class="relative">
                                <label for="date-filter" class="block text-sm font-medium text-gray-700 mb-1">Commissioning Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <input type="date" id="date-filter" class="w-full pl-10 pr-10 py-2 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 transition">
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label for="office-filter" class="block text-sm font-medium text-gray-700 mb-1">Current Office</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <input type="text" id="office-filter" class="w-full pl-10 pr-10 py-2 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 transition" placeholder="Search by office">
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label for="return-date-filter" class="block text-sm font-medium text-gray-700 mb-1">Expected Return Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <input type="date" id="return-date-filter" class="w-full pl-10 pr-10 py-2 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 transition">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active filters -->
                    <div id="active-filters" class="flex flex-wrap gap-2 mt-3 hidden"></div>
                    
                    <!-- Action buttons -->
                    <div class="mt-4 flex justify-end space-x-3">
                        <button id="reset-filters" class="flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </button>
                        <button id="apply-filters" class="flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Apply Filters
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="primary-eRegistry-table" class="min-w-full divide-y divide-gray-200"<thead>
                            <tr class="text-xs">
                                <th class="table-header">#</th>
                                <th class="table-header">Ministry FileNo</th>
                                <th class="table-header">ST Fileno</th>
                                <th class="table-header">Applicant Name</th>
                                <th class="table-header">Status</th>
                                <th class="table-header">Commissioning Date</th>
                                <th class="table-header">Decommissioning Date</th>
                                <th class="table-header">Expected Return Date</th>
                                <th class="table-header">Current Office</th>
                                <th class="table-header">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @if(isset($primaryApplications) && count($primaryApplications) > 0)
                                @foreach($primaryApplications as $application)
                                    <tr class="text-xs hover:bg-gray-50">
                                        
                                        <td class="table-cell">{{ $application->fileno ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->np_fileno ?? 'N/A' }}</td>
                                        <td class="table-cell">
                                            @if(!empty($application->corporate_name))
                                                {{ $application->corporate_name }}
                                            @elseif(!empty($application->multiple_owners_names))
                                                @php
                                                    $owners = json_decode($application->multiple_owners_names, true);
                                                    echo is_array($owners) ? implode(', ', array_slice($owners, 0, 2)) . (count($owners) > 2 ? '...' : '') : 'Multiple Owners';
                                                @endphp
                                            @else
                                                {{ trim(($application->first_name ?? '') . ' ' . ($application->surname ?? '')) }}
                                            @endif
                                            @if($application->land_use)
                                                - {{ $application->land_use }}
                                            @endif
                                        </td>
                                        <td class="table-cell">
                                            @if($application->processing_status == 'Completed')
                                                <span class="status-completed">
                                                    <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                                    Completed
                                                    @if($application->file_count > 0)
                                                        <span class="file-count-badge">{{ $application->file_count }} files</span>
                                                    @endif
                                                </span>
                                            @elseif($application->processing_status == 'In Progress')
                                                <span class="status-in-progress">
                                                    <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                                    In Progress
                                                    @if($application->file_count > 0)
                                                        <span class="file-count-badge">{{ $application->file_count }} files</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="status-not-started">
                                                    <i data-lucide="x-circle" class="w-3 h-3 mr-1"></i>
                                                    Not Started
                                                </span>
                                            @endif
                                        </td>
                                        <td class="table-cell">{{ $application->Commissioning_Date ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->Decommissioning_Date ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->Expected_Return_Date ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->Current_Office ?? 'N/A' }}</td>
                                        <td class="table-cell">
                                            @if($application->processing_status == 'Completed')
                                                <a href="{{ route('file-viewer.primary', $application->application_id) }}" 
                                                   class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                                    <i data-lucide="folder-open" class="w-3 h-3 mr-1"></i>
                                                    View Files
                                                </a>
                                            @else
                                                <button disabled 
                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-white btn-disabled transition-colors"
                                                        title="Files not available - Processing {{ strtolower($application->processing_status) }}">
                                                    <i data-lucide="folder-x" class="w-3 h-3 mr-1"></i>
                                                    @if($application->processing_status == 'In Progress')
                                                        Processing...
                                                    @else
                                                        No Files
                                                    @endif
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr class="text-xs">
                                    <td colspan="10" class="table-cell text-center py-4">No primary applications found</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Unit Applications Tab Content -->
            <div id="unit-applications" class="tab-content hidden">
                <!-- Search Filters for Unit Applications -->
                <div class="mb-4">
                    <button id="toggle-unit-search-filters-btn" class="flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span id="toggle-unit-search-filters-text">Show Filters</span>
                    </button>
                </div>

                <!-- Unit Filter Section (Initially Hidden) -->
                <div id="unit-search-filters-section" class="mb-6 bg-white p-5 rounded-lg border border-gray-200 shadow-sm hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Smart Search - Unit Applications
                        </h3>
                        <div id="unit-results-counter" class="text-sm text-gray-500 hidden">
                            <span id="unit-count">0</span> results found
                        </div>
                    </div>
                    
                    <!-- Main search bar for units -->
                    <div class="relative mb-4">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" id="unit-global-search" class="w-full pl-10 pr-10 py-3 text-base rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-200 focus:ring-opacity-50 transition" placeholder="Search across all unit application fields...">
                        <button type="button" id="unit-clear-search" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Advanced filters for units -->
                    <div class="mb-3">
                        <button id="toggle-unit-filters" class="text-sm text-purple-600 hover:text-purple-800 focus:outline-none flex items-center">
                            <svg id="unit-chevron-down" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                            <svg id="unit-chevron-up" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                            Advanced Filters
                        </button>
                    </div>
                    
                    <div id="unit-advanced-filters" class="hidden transition-all duration-300">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-4">
                            <div class="relative">
                                <label for="unit-date-filter" class="block text-sm font-medium text-gray-700 mb-1">Commissioning Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <input type="date" id="unit-date-filter" class="w-full pl-10 pr-10 py-2 rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-200 focus:ring-opacity-50 transition">
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label for="unit-office-filter" class="block text-sm font-medium text-gray-700 mb-1">Current Office</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <input type="text" id="unit-office-filter" class="w-full pl-10 pr-10 py-2 rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-200 focus:ring-opacity-50 transition" placeholder="Search by office">
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label for="unit-return-date-filter" class="block text-sm font-medium text-gray-700 mb-1">Expected Return Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <input type="date" id="unit-return-date-filter" class="w-full pl-10 pr-10 py-2 rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-200 focus:ring-opacity-50 transition">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active filters for units -->
                    <div id="unit-active-filters" class="flex flex-wrap gap-2 mt-3 hidden"></div>
                    
                    <!-- Action buttons for units -->
                    <div class="mt-4 flex justify-end space-x-3">
                        <button id="unit-reset-filters" class="flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </button>
                        <button id="unit-apply-filters" class="flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Apply Filters
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="unit-eRegistry-table" class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-xs">
                                
                                <th class="table-header">Parent Fileno</th>
                                <th class="table-header">ST Fileno</th>
                                <th class="table-header">File Name</th>
                                <th class="table-header">Status</th>
                                
                                <th class="table-header">Commissioning Date</th>
                                <th class="table-header">Decommissioning Date</th>
                                <th class="table-header">Expected Return Date</th>
                                <th class="table-header">Current Office</th>
                                <th class="table-header">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @if(isset($unitApplications) && count($unitApplications) > 0)
                                @foreach($unitApplications as $application)
                                    <tr class="text-xs hover:bg-gray-50">
                                        
                                        <td class="table-cell">{{ $application->parent_fileno ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->fileno ?? 'N/A' }}</td>
                                        <td class="table-cell">
                                            @if(!empty($application->corporate_name))
                                                {{ $application->corporate_name }}
                                            @elseif(!empty($application->multiple_owners_names))
                                                @php
                                                    $owners = json_decode($application->multiple_owners_names, true);
                                                    echo is_array($owners) ? implode(', ', array_slice($owners, 0, 2)) . (count($owners) > 2 ? '...' : '') : 'Multiple Owners';
                                                @endphp
                                            @else
                                                {{ trim(($application->first_name ?? '') . ' ' . ($application->surname ?? '')) }}
                                            @endif
                                            @if($application->unit_number)
                                                - Unit {{ $application->unit_number }}
                                            @endif
                                        </td>
                                        <td class="table-cell">
                                            @if($application->processing_status == 'Completed')
                                                <span class="status-completed">
                                                    <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                                    Completed
                                                    @if($application->file_count > 0)
                                                        <span class="file-count-badge-purple">{{ $application->file_count }} files</span>
                                                    @endif
                                                </span>
                                            @elseif($application->processing_status == 'In Progress')
                                                <span class="status-in-progress">
                                                    <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                                    In Progress
                                                    @if($application->file_count > 0)
                                                        <span class="file-count-badge-purple">{{ $application->file_count }} files</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="status-not-started">
                                                    <i data-lucide="x-circle" class="w-3 h-3 mr-1"></i>
                                                    Not Started
                                                </span>
                                            @endif
                                        </td>
                                        <td class="table-cell">{{ $application->Commissioning_Date ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->Decommissioning_Date ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->Expected_Return_Date ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $application->Current_Office ?? 'N/A' }}</td>
                                        <td class="table-cell">
                                            @if($application->processing_status == 'Completed')
                                                <a href="{{ route('file-viewer.unit', $application->sub_application_id) }}" 
                                                   class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors">
                                                    <i data-lucide="folder-open" class="w-3 h-3 mr-1"></i>
                                                    View Files
                                                </a>
                                            @else
                                                <button disabled 
                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-white btn-disabled transition-colors"
                                                        title="Files not available - Processing {{ strtolower($application->processing_status) }}">
                                                    <i data-lucide="folder-x" class="w-3 h-3 mr-1"></i>
                                                    @if($application->processing_status == 'In Progress')
                                                        Processing...
                                                    @else
                                                        No Files
                                                    @endif
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr class="text-xs">
                                    <td colspan="10" class="table-cell text-center py-4">No unit applications found</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>

 
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Primary Applications Filter Functionality
        const globalSearch = document.getElementById('global-search');
        const clearSearch = document.getElementById('clear-search');
        const toggleFilters = document.getElementById('toggle-filters');
        const advancedFilters = document.getElementById('advanced-filters');
        const chevronDown = document.getElementById('chevron-down');
        const chevronUp = document.getElementById('chevron-up');
        const dateFilter = document.getElementById('date-filter');
        const officeFilter = document.getElementById('office-filter');
        const returnDateFilter = document.getElementById('return-date-filter');
        const resetBtn = document.getElementById('reset-filters');
        const applyBtn = document.getElementById('apply-filters');
        const activeFilters = document.getElementById('active-filters');
        const resultsCounter = document.getElementById('results-counter');
        const countDisplay = document.getElementById('count');
        const table = document.getElementById('primary-eRegistry-table');
        const toggleSearchFiltersBtn = document.getElementById('toggle-search-filters-btn');
        const toggleSearchFiltersText = document.getElementById('toggle-search-filters-text');
        const searchFiltersSection = document.getElementById('search-filters-section');

        // Unit Applications Filter Functionality
        const unitGlobalSearch = document.getElementById('unit-global-search');
        const unitClearSearch = document.getElementById('unit-clear-search');
        const toggleUnitFilters = document.getElementById('toggle-unit-filters');
        const unitAdvancedFilters = document.getElementById('unit-advanced-filters');
        const unitChevronDown = document.getElementById('unit-chevron-down');
        const unitChevronUp = document.getElementById('unit-chevron-up');
        const unitDateFilter = document.getElementById('unit-date-filter');
        const unitOfficeFilter = document.getElementById('unit-office-filter');
        const unitReturnDateFilter = document.getElementById('unit-return-date-filter');
        const unitResetBtn = document.getElementById('unit-reset-filters');
        const unitApplyBtn = document.getElementById('unit-apply-filters');
        const unitActiveFilters = document.getElementById('unit-active-filters');
        const unitResultsCounter = document.getElementById('unit-results-counter');
        const unitCountDisplay = document.getElementById('unit-count');
        const unitTable = document.getElementById('unit-eRegistry-table');
        const toggleUnitSearchFiltersBtn = document.getElementById('toggle-unit-search-filters-btn');
        const toggleUnitSearchFiltersText = document.getElementById('toggle-unit-search-filters-text');
        const unitSearchFiltersSection = document.getElementById('unit-search-filters-section');

        // Primary Applications Filter Functions
        if (toggleSearchFiltersBtn) {
            toggleSearchFiltersBtn.addEventListener('click', function() {
                const isHidden = searchFiltersSection.classList.toggle('hidden');
                toggleSearchFiltersText.textContent = isHidden ? 'Show Filters' : 'Hide Filters';
            });
        }

        if (toggleFilters) {
            toggleFilters.addEventListener('click', function() {
                advancedFilters.classList.toggle('hidden');
                chevronDown.classList.toggle('hidden');
                chevronUp.classList.toggle('hidden');
            });
        }

        if (globalSearch) {
            globalSearch.addEventListener('input', function() {
                if (this.value) {
                    clearSearch.classList.remove('hidden');
                } else {
                    clearSearch.classList.add('hidden');
                }
                filterPrimaryTable();
            });
        }

        if (clearSearch) {
            clearSearch.addEventListener('click', function() {
                globalSearch.value = '';
                this.classList.add('hidden');
                filterPrimaryTable();
            });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', filterPrimaryTable);
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                globalSearch.value = '';
                dateFilter.value = '';
                officeFilter.value = '';
                returnDateFilter.value = '';
                clearSearch.classList.add('hidden');
                activeFilters.innerHTML = '';
                activeFilters.classList.add('hidden');
                resultsCounter.classList.add('hidden');
                filterPrimaryTable();
            });
        }

        [dateFilter, officeFilter, returnDateFilter].forEach(input => {
            if (input) {
                input.addEventListener('change', filterPrimaryTable);
                input.addEventListener('input', filterPrimaryTable);
            }
        });

        // Unit Applications Filter Functions
        if (toggleUnitSearchFiltersBtn) {
            toggleUnitSearchFiltersBtn.addEventListener('click', function() {
                const isHidden = unitSearchFiltersSection.classList.toggle('hidden');
                toggleUnitSearchFiltersText.textContent = isHidden ? 'Show Filters' : 'Hide Filters';
            });
        }

        if (toggleUnitFilters) {
            toggleUnitFilters.addEventListener('click', function() {
                unitAdvancedFilters.classList.toggle('hidden');
                unitChevronDown.classList.toggle('hidden');
                unitChevronUp.classList.toggle('hidden');
            });
        }

        if (unitGlobalSearch) {
            unitGlobalSearch.addEventListener('input', function() {
                if (this.value) {
                    unitClearSearch.classList.remove('hidden');
                } else {
                    unitClearSearch.classList.add('hidden');
                }
                filterUnitTable();
            });
        }

        if (unitClearSearch) {
            unitClearSearch.addEventListener('click', function() {
                unitGlobalSearch.value = '';
                this.classList.add('hidden');
                filterUnitTable();
            });
        }

        if (unitApplyBtn) {
            unitApplyBtn.addEventListener('click', filterUnitTable);
        }

        if (unitResetBtn) {
            unitResetBtn.addEventListener('click', function() {
                unitGlobalSearch.value = '';
                unitDateFilter.value = '';
                unitOfficeFilter.value = '';
                unitReturnDateFilter.value = '';
                unitClearSearch.classList.add('hidden');
                unitActiveFilters.innerHTML = '';
                unitActiveFilters.classList.add('hidden');
                unitResultsCounter.classList.add('hidden');
                filterUnitTable();
            });
        }

        [unitDateFilter, unitOfficeFilter, unitReturnDateFilter].forEach(input => {
            if (input) {
                input.addEventListener('change', filterUnitTable);
                input.addEventListener('input', filterUnitTable);
            }
        });

        // Filter table function for primary applications
        function filterPrimaryTable() {
            if (!table) return;
            
            const searchTerm = globalSearch ? globalSearch.value.toLowerCase() : '';
            const date = dateFilter ? dateFilter.value : '';
            const office = officeFilter ? officeFilter.value.toLowerCase() : '';
            const returnDate = returnDateFilter ? returnDateFilter.value : '';
            
            const rows = table.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            updateActiveFilters();
            
            rows.forEach(row => {
                const allText = Array.from(row.cells).map(cell => 
                    cell.textContent.toLowerCase()).join(' ');
                const commissionDate = formatDate(row.cells[4] ? row.cells[4].textContent : '');
                const officeCell = row.cells[7] ? row.cells[7].textContent.toLowerCase() : '';
                const expectedReturnDate = formatDate(row.cells[6] ? row.cells[6].textContent : '');
                
                const matchesSearch = !searchTerm || allText.includes(searchTerm);
                const matchesDate = !date || commissionDate === date;
                const matchesOffice = !office || officeCell.includes(office);
                const matchesReturn = !returnDate || expectedReturnDate === returnDate;
                
                if (matchesSearch && matchesDate && matchesOffice && matchesReturn) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (resultsCounter && countDisplay) {
                if (searchTerm || date || office || returnDate) {
                    countDisplay.textContent = visibleCount;
                    resultsCounter.classList.remove('hidden');
                } else {
                    resultsCounter.classList.add('hidden');
                }
            }
        }

        // Filter table function for unit applications
        function filterUnitTable() {
            if (!unitTable) return;
            
            const searchTerm = unitGlobalSearch ? unitGlobalSearch.value.toLowerCase() : '';
            const date = unitDateFilter ? unitDateFilter.value : '';
            const office = unitOfficeFilter ? unitOfficeFilter.value.toLowerCase() : '';
            const returnDate = unitReturnDateFilter ? unitReturnDateFilter.value : '';
            
            const rows = unitTable.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            updateUnitActiveFilters();
            
            rows.forEach(row => {
                const allText = Array.from(row.cells).map(cell => 
                    cell.textContent.toLowerCase()).join(' ');
                const commissionDate = formatDate(row.cells[4] ? row.cells[4].textContent : '');
                const officeCell = row.cells[7] ? row.cells[7].textContent.toLowerCase() : '';
                const expectedReturnDate = formatDate(row.cells[6] ? row.cells[6].textContent : '');
                
                const matchesSearch = !searchTerm || allText.includes(searchTerm);
                const matchesDate = !date || commissionDate === date;
                const matchesOffice = !office || officeCell.includes(office);
                const matchesReturn = !returnDate || expectedReturnDate === returnDate;
                
                if (matchesSearch && matchesDate && matchesOffice && matchesReturn) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (unitResultsCounter && unitCountDisplay) {
                if (searchTerm || date || office || returnDate) {
                    unitCountDisplay.textContent = visibleCount;
                    unitResultsCounter.classList.remove('hidden');
                } else {
                    unitResultsCounter.classList.add('hidden');
                }
            }
        }

        // Update active filters display for primary applications
        function updateActiveFilters() {
            if (!activeFilters) return;
            
            activeFilters.innerHTML = '';
            let hasFilters = false;
            
            if (globalSearch && globalSearch.value) addFilter('Search: ' + globalSearch.value);
            if (dateFilter && dateFilter.value) addFilter('Date: ' + formatDisplayDate(dateFilter.value));
            if (officeFilter && officeFilter.value) addFilter('Office: ' + officeFilter.value);
            if (returnDateFilter && returnDateFilter.value) addFilter('Return: ' + formatDisplayDate(returnDateFilter.value));
            
            function addFilter(text) {
                hasFilters = true;
                const chip = document.createElement('div');
                chip.className = 'bg-blue-100 text-blue-800 text-xs rounded-full px-3 py-1 flex items-center';
                chip.innerHTML = `<span>${text}</span>`;
                activeFilters.appendChild(chip);
            }
            
            activeFilters.classList.toggle('hidden', !hasFilters);
        }

        // Update active filters display for unit applications
        function updateUnitActiveFilters() {
            if (!unitActiveFilters) return;
            
            unitActiveFilters.innerHTML = '';
            let hasFilters = false;
            
            if (unitGlobalSearch && unitGlobalSearch.value) addUnitFilter('Search: ' + unitGlobalSearch.value);
            if (unitDateFilter && unitDateFilter.value) addUnitFilter('Date: ' + formatDisplayDate(unitDateFilter.value));
            if (unitOfficeFilter && unitOfficeFilter.value) addUnitFilter('Office: ' + unitOfficeFilter.value);
            if (unitReturnDateFilter && unitReturnDateFilter.value) addUnitFilter('Return: ' + formatDisplayDate(unitReturnDateFilter.value));
            
            function addUnitFilter(text) {
                hasFilters = true;
                const chip = document.createElement('div');
                chip.className = 'bg-purple-100 text-purple-800 text-xs rounded-full px-3 py-1 flex items-center';
                chip.innerHTML = `<span>${text}</span>`;
                unitActiveFilters.appendChild(chip);
            }
            
            unitActiveFilters.classList.toggle('hidden', !hasFilters);
        }

        // Helper: Format date from text to YYYY-MM-DD
        function formatDate(dateText) {
            if (dateText === 'N/A' || dateText === 'n/a') return '';
            try {
                const date = new Date(dateText);
                return date.toISOString().split('T')[0];
            } catch {
                return '';
            }
        }
        
        // Helper: Format date for display
        function formatDisplayDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        }
    });
</script>
@endsection



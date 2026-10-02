@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<style>
    /* Required for proper z-index stacking and overflow */
    .dropdown-wrapper { 
        position: static; 
    }
    .dropdown-menu { 
        position: fixed;
        z-index: 9999;
        min-width: 12rem;
        margin-top: 0.25rem;
    }
    /* Ensure table doesn't expand due to dropdown */
    .overflow-x-auto { 
        overflow-x: auto;
        overflow-y: visible;
    }
    /* Prevent table cell from expanding */
    .action-cell {
        width: 60px;
        min-width: 60px;
        max-width: 60px;
        position: relative;
    }
    
    /* Counter animations */
    .counter-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: all 0.3s ease;
    }
    .counter-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .counter-card.primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    .counter-card.secondary {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    .counter-card.uploaded {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }
    .counter-card.pending {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    /* Table improvements */
    .table-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.75rem;
        padding: 1rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .table-row {
        transition: all 0.2s ease;
    }
    .table-row:hover {
        background-color: #f8fafc;
        transform: scale(1.001);
    }
    
    /* Search and filter styling */
    .search-container {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
    }
    
    /* Status badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .badge-approved {
        background-color: #dcfce7;
        color: #166534;
    }
    .badge-pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    .badge-declined {
        background-color: #fee2e2;
        color: #991b1b;
    }
    .badge-uploaded {
        background-color: #dcfce7;
        color: #166534;
    }
    .badge-not-uploaded {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    /* Enhanced button styling */
    .btn-primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
    }
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    
    .btn-secondary {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
    }
    .btn-secondary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(107, 114, 128, 0.4);
    }
</style>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
        <!-- Main Content Container -->
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <!-- Header with actions -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">Physical Planning Memo</h2>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <!-- Smart Counters Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Applications Counter -->
                <div class="counter-card primary rounded-xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">Total Applications</p>
                            <p class="text-3xl font-bold mt-2" id="total-counter">{{ count($PrimaryApplications) }}</p>
                            <p class="text-white/70 text-xs mt-1">All applications</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="file-text" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>

                <!-- Approved Applications Counter -->
                <div class="counter-card secondary rounded-xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">Approved</p>
                            <p class="text-3xl font-bold mt-2" id="approved-counter">
                                {{ 
                                    collect($PrimaryApplications)->filter(function($app) {
                                        return strtolower($app->application_status) === 'approved';
                                    })->count()
                                }}
                            </p>
                            <p class="text-white/70 text-xs mt-1">Applications</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="check-circle" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>

                <!-- Site Plans Uploaded Counter -->
                <div class="counter-card uploaded rounded-xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">Site Plans Uploaded</p>
                            <p class="text-3xl font-bold mt-2" id="uploaded-counter">
                                {{ 
                                    collect($PrimaryApplications)->filter(function($app) {
                                        return $app->site_plan_status == 'Uploaded';
                                    })->count()
                                }}
                            </p>
                            <p class="text-white/70 text-xs mt-1">Regular site plans</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="upload" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>

                <!-- Recommended Site Plans Uploaded Counter -->
                <div class="counter-card uploaded rounded-xl p-6 text-white shadow-lg" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">Recommended Site Plans</p>
                            <p class="text-3xl font-bold mt-2" id="recommended-uploaded-counter">
                                {{ 
                                    collect($PrimaryApplications)->filter(function($app) {
                                        return isset($app->recommended_site_plan_status) && $app->recommended_site_plan_status == 'Uploaded';
                                    })->count()
                                }}
                            </p>
                            <p class="text-white/70 text-xs mt-1">Recommended sketches</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="file-plus" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Site Plans Counter -->
                <div class="counter-card pending rounded-xl p-6 text-white shadow-lg hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">Pending Site Plans</p>
                            <p class="text-3xl font-bold mt-2" id="pending-counter">
                                {{ 
                                    collect($PrimaryApplications)->filter(function($app) {
                                        return $app->site_plan_status != 'Uploaded';
                                    })->count()
                                }}
                            </p>
                            <p class="text-white/70 text-xs mt-1">Awaiting upload</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="clock" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-container">
                <div class="flex flex-col md:flex-row gap-4 items-center">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="smart-search" placeholder="Search applications by file no, property, owner..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                            </div>
                            <button id="clear-search"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <div id="search-info" class="mt-1 text-xs text-gray-500 hidden">
                            Found <span id="search-count">0</span> results
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <select id="status-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="all">All Status</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="declined">Declined</option>
                        </select>
                        <select id="siteplan-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="all">All Site Plans</option>
                            <option value="uploaded">Uploaded</option>
                            <option value="not-uploaded">Not Uploaded</option>
                        </select>
                        <button id="export-btn" class="btn-secondary flex items-center space-x-2">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Primary Applications Table -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <h2 class="text-xl font-bold">Primary Applications</h2>
                        {{-- <button type="button" onclick="showTableInfo()" class="ml-2 text-blue-500 hover:text-blue-700 focus:outline-none">
                            <i data-lucide="info" class="h-5 w-5"></i>
                        </button> --}}
                    </div>

                    <!-- Smart Search Input -->
                    <div class="relative flex-grow mx-4">
                        <div class="flex items-center space-x-2">
                            <!-- Search Icon Button -->
                            <button id="show-search-btn" type="button"
                                class="flex items-center justify-center w-10 h-10 rounded-full border border-gray-300 bg-white hover:bg-gray-100 focus:outline-none">
                                <i data-lucide="search" class="h-5 w-5 text-gray-500"></i>
                            </button>
                            <!-- Search Input (hidden by default) -->
                            <div id="search-input-container" class="relative hidden flex-grow">
                                <input type="text" id="smart-search" placeholder="Search applications..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                                </div>
                                <button id="clear-search"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                </button>
                                <div id="search-info" class="absolute mt-1 text-xs text-gray-500 hidden">
                                    Found <span id="search-count">0</span> results
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const showSearchBtn = document.getElementById('show-search-btn');
                            const searchInputContainer = document.getElementById('search-input-container');
                            const smartSearch = document.getElementById('smart-search');
                            // Show search input when icon is clicked
                            showSearchBtn.addEventListener('click', function() {
                                searchInputContainer.classList.remove('hidden');
                                smartSearch.focus();
                                showSearchBtn.classList.add('hidden');
                            });
                            // Hide search input when input loses focus and is empty
                            smartSearch.addEventListener('blur', function() {
                                setTimeout(function() {
                                    if (smartSearch.value.trim() === '') {
                                        searchInputContainer.classList.add('hidden');
                                        showSearchBtn.classList.remove('hidden');
                                    }
                                }, 150);
                            });
                        });
                    </script>

                    <div class="flex items-center space-x-4">

                        <div class="relative">
                            <select
                                class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                                <option>All...</option>
                                <option>Approved</option>
                                <option>Pending</option>
                                <option>Declined</option>
                            </select>
                            <i data-lucide="chevron-down"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                        </div>

                        <style>
                            button:hover {
                                background-color: #fed7aa;
                            }
                        </style>

                        <button class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                            <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
                            <span>Export</span>
                        </button>


                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="applications-table" class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-xs"> 
 

 
                                <th class="table-header text-green-500">ST File No</th>
                                  <th class="table-header text-green-500">MLPP File No</th>
                                <th class="table-header text-green-500">Property</th>
                                <th class="table-header text-green-500">Type</th>
                                <th class="table-header text-green-500">Land Use</th>
                                <th class="table-header text-green-500">Owner</th>
                                <th class="table-header text-green-500">Units</th>
                                
                               
                                <th class="table-header text-green-500">Planning Recommendation</th> 
                                 <th class="table-header text-green-500">ST Director's Approval</th>
                                 <th class="table-header text-green-500">Site Plan</th>
                                <th class="table-header text-green-500">Physical Planning Memo</th>
                                {{-- <th class="table-header text-green-500">Planning Recommendation</th> --}}
                                <th class="table-header text-green-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($PrimaryApplications as $PrimaryApplication)
                                @php
                                    // $sitePlanDimensionExists = DB::connection('sqlsrv')
                                    //     ->table('site_plan_dimensions')
                                    //     ->where('application_id', $PrimaryApplication->id)
                                    //     ->exists();

                                    $memoStatus = DB::connection('sqlsrv')
                                        ->table('memos')
                                        ->where('application_id', $PrimaryApplication->id)
                                        ->where('memo_status', 'GENERATED')
                                        ->where('memo_type', 'physical_planning')
                                        ->exists();

                                    $approvalStatus = strtolower($PrimaryApplication->planning_recommendation_status) === 'approved';
                                    $sitePlanUploaded = $PrimaryApplication->site_plan_status == 'Uploaded';
                                    $stMemoGenerated = $memoStatus;
                                @endphp
                                <tr class="text-xs application-row"
                                    data-status="{{ $PrimaryApplication->site_plan_status == 'Uploaded' ? 'uploaded' : 'not-uploaded' }}">
                                    
                                    <td class="table-cell">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 whitespace-nowrap">
                                            
                                             {{ $PrimaryApplication->np_fileno }}
                                        </span>
                                    </td>
                                    <td class="table-cell">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 whitespace-nowrap">
                                           {{ $PrimaryApplication->fileno }}
                                        </span>
                                    </td>
                                    <td class="table-cell">
                                        <div class="truncate max-w-[150px]"
                                            title="{{ $PrimaryApplication->property_plot_no }} {{ $PrimaryApplication->property_street_name }}, {{ $PrimaryApplication->property_lga }}">
                                            {{ $PrimaryApplication->property_plot_no }}
                                            {{ $PrimaryApplication->property_street_name }},
                                            {{ $PrimaryApplication->property_lga }}
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        @if ($PrimaryApplication->commercial_type)
                                            {{ $PrimaryApplication->commercial_type }}
                                        @elseif ($PrimaryApplication->industrial_type)
                                            {{ $PrimaryApplication->industrial_type }}
                                        @elseif ($PrimaryApplication->mixed_type)
                                            {{ $PrimaryApplication->mixed_type }}
                                        @else
                                           -
                                        @endif
                                    </td>
                                    <td class="table-cell">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $PrimaryApplication->land_use }}
                                        </span>
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <div
                                                class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                                                @if ($PrimaryApplication->passport)
                                                    <img src="{{ asset('storage/app/public/' . $PrimaryApplication->passport) }}"
                                                        alt="Passport"
                                                        class="w-full h-full rounded-full object-cover cursor-pointer"
                                                        onclick="showPassportPreview('{{ asset('storage/app/public/' . $PrimaryApplication->passport) }}', 'Owner Passport')">
                                                @elseif ($PrimaryApplication->multiple_owners_passport)
                                                    @php
                                                        $passports = json_decode(
                                                            $PrimaryApplication->multiple_owners_passport,
                                                            true,
                                                        );
                                                        $firstPassport = $passports[0] ?? null;
                                                    @endphp
                                                    @if ($firstPassport)
                                                        <img src="{{ asset('storage/app/public/' . $firstPassport) }}"
                                                            alt="Passport"
                                                            class="w-full h-full rounded-full object-cover cursor-pointer"
                                                            onclick="showMultipleOwners({{ $PrimaryApplication->multiple_owners_names }}, {{ $PrimaryApplication->multiple_owners_passport }})">
                                                    @endif
                                                @endif
                                            </div>
                                            <span class="truncate max-w-[120px]">
                                                @if ($PrimaryApplication->corporate_name)
                                                    {{ $PrimaryApplication->corporate_name }}
                                                @elseif($PrimaryApplication->multiple_owners_names)
                                                    @php
                                                        $ownerNames = json_decode(
                                                            $PrimaryApplication->multiple_owners_names,
                                                            true,
                                                        );
                                                        $firstOwner = $ownerNames[0] ?? 'Unknown Owner';
                                                    @endphp
                                                    {{ $firstOwner }}
                                                    <span class="ml-1 cursor-pointer text-blue-500"
                                                        onclick="showMultipleOwners({{ $PrimaryApplication->multiple_owners_names }}, {{ $PrimaryApplication->multiple_owners_passport }})">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </span>
                                                @elseif($PrimaryApplication->first_name || $PrimaryApplication->surname)
                                                    {{ $PrimaryApplication->first_name }}
                                                    {{ $PrimaryApplication->surname }}
                                                @else
                                                    Unknown Owner
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="table-cell">{{ $PrimaryApplication->NoOfUnits }}</td>
                              
                               
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <span class="badge badge-{{ strtolower($PrimaryApplication->planning_recommendation_status) }}">
                                                {{ $PrimaryApplication->planning_recommendation_status }}
                                            </span>
                                            @if($PrimaryApplication->planning_recommendation_status == 'Declined')
                                                <i data-lucide="info" class="w-4 h-4 ml-1 text-blue-500 cursor-pointer" 
                                                   onclick="showDeclinedInfo(event, 'Planning Recommendation', {{ json_encode($PrimaryApplication->recomm_comments) }}, {{ json_encode($PrimaryApplication->director_comments) }})"></i>
                                            @endif
                                        </div>
                                    </td>


                                     <td class="table-cell">
                                        <div class="flex items-center">
                                            <span class="badge badge-{{ strtolower($PrimaryApplication->application_status) }}">
                                                {{ $PrimaryApplication->application_status }}
                                            </span>
                                            @if($PrimaryApplication->application_status == 'Declined')
                                                <i data-lucide="info" class="w-4 h-4 ml-1 text-blue-500 cursor-pointer" 
                                                   onclick="showDeclinedInfo(event, 'Comment', {{ json_encode($PrimaryApplication->recomm_comments) }}, {{ json_encode($PrimaryApplication->director_comments) }})"></i>
                                            @endif
                                        </div>
                                    </td>
                                         <td class="table-cell">
                                        @if ($PrimaryApplication->site_plan_status == 'Uploaded')
                                            <span
                                                class="inline-block px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded">Uploaded</span>
                                        @else
                                            <span
                                                class="inline-block px-2 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded">Not
                                                Uploaded</span>
                                        @endif
                                    </td>
                                    {{-- <td class="table-cell">
                                        @if($sitePlanDimensionExists)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                               Approved
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <svg class="mr-1.5 h-2 w-2 text-gray-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                               Pending
                                            </span>
                                        @endif
                                    </td> --}}

                                          <td class="table-cell">
                                        @if($memoStatus)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                                Generated
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <svg class="mr-1.5 h-2 w-2 text-gray-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                                Not Generated
                                            </span>
                                        @endif
                                    </td>
                                    <td class="table-cell overflow-visible relative">
                                        @include('stmemo.action_menu')
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- No Results Message -->
                <div id="no-results-message" class="hidden py-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i data-lucide="search-x" class="h-8 w-8 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No matching applications found</h3>
                    <p class="text-gray-500">Try adjusting your search or filter criteria</p>
                </div>

                <div class="flex justify-between items-center mt-6 text-sm">
                    
                    <div class="flex items-center space-x-2">
                        <button class="px-3 py-1 border border-gray-200 rounded-md flex items-center">
                            <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i>
                            <span>Previous</span>
                        </button>
                        <button class="px-3 py-1 border border-gray-200 rounded-md flex items-center">
                            <span>Next</span>
                            <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

    @include('sectionaltitling.action_modals.eRegistry_modal')

    <script>
        // Add the showTableInfo function to display a helpful popup
        function showTableInfo() {
            Swal.fire({
                title: 'Using the Applications Table',
                html: `
                    <div class="text-left p-2">
                        <h3 class="font-bold text-lg mb-2">Quick Tips:</h3>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><span class="font-semibold">Action Menu:</span> Click the three dots (...) to access actions for each application.</li>
                            <li><span class="font-semibold">Status Indicators:</span> 
                                <ul class="list-circle pl-5 mt-1">
                                    <li>Green badges indicate completed items</li>
                                    <li>Gray badges indicate pending items</li>
                                    <li>Red badges indicate declined items</li>
                                </ul>
                            </li>
                            <li><span class="font-semibold">Search:</span> Use the search icon to find applications by ID, file number, property details, etc.</li>
                            <li><span class="font-semibold">Workflow Steps:</span> Follow these steps in order:
                                <ol class="list-decimal pl-5 mt-1">
                                    <li>Generate ST Memo</li>
                                    <li>Upload Site Plan (via action menu)</li>
                                    <li>Approve Application</li>
                                </ol>
                            </li>
                            <li><span class="font-semibold">Info Icons:</span> Click on <i data-lucide="info" class="h-4 w-4 inline text-blue-500"></i> icons for more details about declined items.</li>
                        </ul>
                    </div>
                `,
                width: '600px',
                showCloseButton: true,
                showConfirmButton: false,
                focusConfirm: false,
                didOpen: () => {
                    // Initialize any Lucide icons in the modal
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        }

      
        function showPassportPreview(imageSrc, title) {
            Swal.fire({
                title: title,
                html: `<img src="${imageSrc}" class="img-fluid" style="max-height: 400px;">`,
                width: 'auto',
                showCloseButton: true,
                showConfirmButton: false
            });
        }

        function showMultipleOwners(owners, passports) {
            if (Array.isArray(owners) && owners.length > 0) {
                let htmlContent = '<div class="grid grid-cols-3 gap-4" style="max-width: 600px;">';

                owners.forEach((name, index) => {
                    const passport = Array.isArray(passports) && passports[index] ?
                        `<img src="{{ asset('storage/app/public/') }}/${passports[index]}" 
                                      class="w-24 h-32 object-cover mx-auto border-2 border-gray-300" 
                                      style="object-position: center top;">` :
                        '<div class="w-24 h-32 bg-gray-300 mx-auto flex items-center justify-center"><span>No Image</span></div>';

                    htmlContent += `
                                <div class="flex flex-col items-center">
                                     <div class="passport-container bg-blue-50 p-2 rounded">
                                          ${passport}
                                          <p class="text-center text-sm font-medium mt-1">${name}</p>
                                     </div>
                                </div>
                          `;
                });

                htmlContent += '</div>';

                Swal.fire({
                    title: 'Multiple Owners',
                    html: htmlContent,
                    width: 'auto',
                    showCloseButton: true,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    title: 'Multiple Owners',
                    text: 'No owners available',
                    icon: 'info',
                    confirmButtonText: 'Close'
                });
            }
        }

        function showDeclinedInfo(event, title, recommComments, directorComments) {
            event.stopPropagation();

            let htmlContent = '<div class="text-left">';
            if (recommComments) {
                htmlContent += `
                          <div class="mb-3">
                                <h3 class="font-bold text-gray-700">Recommendation Comments:</h3>
                                <p class="text-gray-600 mt-1 p-2 bg-gray-100 rounded">${recommComments}</p>
                          </div>
                     `;
            }

            if (directorComments) {
                htmlContent += `
                          <div>
                                <h3 class="font-bold text-gray-700">Director Comments:</h3>
                                <p class="text-gray-600 mt-1 p-2 bg-gray-100 rounded">${directorComments}</p>
                          </div>
                     `;
            }

            if (!recommComments && !directorComments) {
                htmlContent += '<p>No comments available.</p>';
            }

            htmlContent += '</div>';

            Swal.fire({
                title: `Declined: ${title}`,
                html: htmlContent,
                icon: 'info',
                width: 'auto',
                showCloseButton: true,
                showConfirmButton: true,
                confirmButtonText: 'Close'
            });
        }

        // New function to handle "Generate ST Memo" action
        function generateSTMemo(applicationId) {
            Swal.fire({
                title: 'Generate Physical Planning Memo',
                text: 'Are you sure you want to physical planning memo for this application?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, generate it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ url('/stmemo/generate') }}/" + applicationId;
                }
            });
        }

        // Enhanced Search and Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Counter animation function
            function animateCounter(element, target, duration = 1000) {
                const start = 0;
                const increment = target / (duration / 16);
                let current = start;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(current);
                }, 16);
            }
            
            // Animate counters on page load
            setTimeout(() => {
                const totalCounter = document.getElementById('total-counter');
                const approvedCounter = document.getElementById('approved-counter');
                const uploadedCounter = document.getElementById('uploaded-counter');
                const pendingCounter = document.getElementById('pending-counter');
                
                if (totalCounter) animateCounter(totalCounter, parseInt(totalCounter.textContent));
                if (approvedCounter) animateCounter(approvedCounter, parseInt(approvedCounter.textContent));
                if (uploadedCounter) animateCounter(uploadedCounter, parseInt(uploadedCounter.textContent));
                if (pendingCounter) animateCounter(pendingCounter, parseInt(pendingCounter.textContent));
            }, 300);

            const applicationRows = document.querySelectorAll('.application-row');
            const searchInput = document.getElementById('smart-search');
            const clearSearchBtn = document.getElementById('clear-search');
            const searchInfo = document.getElementById('search-info');
            const searchCount = document.getElementById('search-count');
            const noResultsMessage = document.getElementById('no-results-message');
            const table = document.getElementById('applications-table');
            const statusFilter = document.getElementById('status-filter');
            const siteplanFilter = document.getElementById('siteplan-filter');
            const exportBtn = document.getElementById('export-btn');

            // Store original counter values
            const originalCounters = {
                total: {{ count($PrimaryApplications) }},
                approved: {{ collect($PrimaryApplications)->filter(function($app) { return strtolower($app->application_status) === 'approved'; })->count() }},
                uploaded: {{ collect($PrimaryApplications)->filter(function($app) { return $app->site_plan_status == 'Uploaded'; })->count() }},
                pending: {{ collect($PrimaryApplications)->filter(function($app) { return $app->site_plan_status != 'Uploaded'; })->count() }}
            };

            // Enhanced filter function
            function filterRows() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const statusValue = statusFilter.value;
                const siteplanValue = siteplanFilter.value;
                
                let visibleCount = 0;
                let approvedCount = 0;
                let uploadedCount = 0;
                let pendingCount = 0;

                applicationRows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    const statusCell = row.querySelector('td:nth-child(7) .badge'); // Planning recommendation status
                    const directorStatusCell = row.querySelector('td:nth-child(8) .badge'); // Director's approval
                    const siteplanCell = row.querySelector('td:nth-child(9)'); // Site plan status
                    
                    // Check search match
                    const matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
                    
                    // Check status filter
                    let matchesStatus = statusValue === 'all';
                    if (statusValue === 'approved' && directorStatusCell) {
                        matchesStatus = directorStatusCell.textContent.toLowerCase().includes('approved');
                    } else if (statusValue === 'pending' && directorStatusCell) {
                        matchesStatus = directorStatusCell.textContent.toLowerCase().includes('pending');
                    } else if (statusValue === 'declined' && directorStatusCell) {
                        matchesStatus = directorStatusCell.textContent.toLowerCase().includes('declined');
                    }
                    
                    // Check site plan filter
                    let matchesSiteplan = siteplanValue === 'all';
                    if (siteplanValue === 'uploaded' && siteplanCell) {
                        matchesSiteplan = siteplanCell.textContent.toLowerCase().includes('uploaded');
                    } else if (siteplanValue === 'not-uploaded' && siteplanCell) {
                        matchesSiteplan = siteplanCell.textContent.toLowerCase().includes('not uploaded');
                    }

                    // Show/hide row based on all filters
                    if (matchesSearch && matchesStatus && matchesSiteplan) {
                        row.style.display = '';
                        visibleCount++;
                        
                        // Count for dynamic counters
                        if (directorStatusCell && directorStatusCell.textContent.toLowerCase().includes('approved')) {
                            approvedCount++;
                        }
                        if (siteplanCell && siteplanCell.textContent.toLowerCase().includes('uploaded')) {
                            uploadedCount++;
                        } else {
                            pendingCount++;
                        }
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update search info
                if (searchTerm === '' && statusValue === 'all' && siteplanValue === 'all') {
                    searchInfo.classList.add('hidden');
                    clearSearchBtn.classList.add('hidden');
                    // Reset to original values
                    document.getElementById('total-counter').textContent = originalCounters.total;
                    document.getElementById('approved-counter').textContent = originalCounters.approved;
                    document.getElementById('uploaded-counter').textContent = originalCounters.uploaded;
                    document.getElementById('pending-counter').textContent = originalCounters.pending;
                } else {
                    searchCount.textContent = visibleCount;
                    searchInfo.classList.remove('hidden');
                    clearSearchBtn.classList.remove('hidden');
                    
                    // Update counters with filtered values
                    document.getElementById('total-counter').textContent = visibleCount;
                    document.getElementById('approved-counter').textContent = approvedCount;
                    document.getElementById('uploaded-counter').textContent = uploadedCount;
                    document.getElementById('pending-counter').textContent = pendingCount;
                }

                // Show/hide no results message
                if (visibleCount === 0) {
                    table.classList.add('hidden');
                    noResultsMessage.classList.remove('hidden');
                } else {
                    table.classList.remove('hidden');
                    noResultsMessage.classList.add('hidden');
                }
            }

            // Add event listeners
            searchInput.addEventListener('input', filterRows);
            statusFilter.addEventListener('change', filterRows);
            siteplanFilter.addEventListener('change', filterRows);

            // Clear search functionality
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                statusFilter.value = 'all';
                siteplanFilter.value = 'all';
                filterRows();
                searchInput.focus();
            });

            // Export functionality
            exportBtn.addEventListener('click', function() {
                const visibleRows = Array.from(table.querySelectorAll('tr')).filter(row => 
                    row.style.display !== 'none'
                );
                
                if (visibleRows.length === 0) return;
                
                let csv = '';
                visibleRows.forEach(row => {
                    const cells = row.querySelectorAll('th, td');
                    const rowData = Array.from(cells).slice(0, -1).map(cell => 
                        `"${cell.textContent.trim().replace(/"/g, '""')}"`
                    ).join(',');
                    csv += rowData + '\n';
                });
                
                // Download CSV
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `site-plan-applications-${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                }
                
                // Escape to clear search
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    statusFilter.value = 'all';
                    siteplanFilter.value = 'all';
                    filterRows();
                    searchInput.blur();
                }
            });

            // Initialize
            filterRows();
        });

        // Recommended Site Plan Sketch Modal Functions
        function openRecommendedSitePlanModal(applicationId) {
            document.getElementById('recommendedSitePlanModal').style.display = 'flex';
            document.getElementById('recommended_application_id').value = applicationId;
            
            // Fetch existing recommended site plan if any
            fetch(`/stmemo/get-recommended-siteplan/${applicationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.recommendedSitePlan) {
                        const container = document.getElementById('existing-recommended-file');
                        container.innerHTML = `
                            <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded">
                                <p class="text-sm text-blue-700 mb-2">
                                    <i data-lucide="file-check" class="w-4 h-4 inline mr-1"></i>
                                    Current recommended site plan sketch uploaded
                                </p>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-blue-600">Uploaded: ${data.recommendedSitePlan.created_at}</span>
                                    <div class="flex gap-2">
                                        <a href="/storage/${data.recommendedSitePlan.recommended_file}" target="_blank" 
                                           class="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">
                                            View
                                        </a>
                                        <button onclick="deleteRecommendedSitePlan(${applicationId})" 
                                                class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        lucide.createIcons();
                    } else {
                        document.getElementById('existing-recommended-file').innerHTML = '';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function closeRecommendedSitePlanModal() {
            document.getElementById('recommendedSitePlanModal').style.display = 'none';
            document.getElementById('recommendedSitePlanForm').reset();
            document.getElementById('existing-recommended-file').innerHTML = '';
        }

        function uploadRecommendedSitePlan() {
            const form = document.getElementById('recommendedSitePlanForm');
            const formData = new FormData(form);
            const submitBtn = document.getElementById('uploadRecommendedBtn');
            
            // Get the file input to check if a file is selected
            const fileInput = document.getElementById('recommended_file');
            const applicationId = document.getElementById('recommended_application_id').value;
            
            // Validate required fields
            if (!applicationId || !fileInput.files.length) {
                Swal.fire('Error', 'Please select a file to upload', 'error');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Uploading...';
            
            fetch('/stmemo/save-recommended-siteplan', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.message, 'success');
                    closeRecommendedSitePlanModal();
                    location.reload(); // Refresh the page to update status
                } else {
                    Swal.fire('Error', data.message || 'Failed to upload file', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An error occurred while uploading the file', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="upload" class="w-4 h-4 mr-2"></i>Upload';
                lucide.createIcons();
            });
        }

        function deleteRecommendedSitePlan(applicationId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This action will permanently delete the recommended site plan sketch.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/stmemo/delete-recommended-siteplan/${applicationId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted!', data.message, 'success');
                            closeRecommendedSitePlanModal();
                            location.reload();
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete file', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'An error occurred while deleting the file', 'error');
                    });
                }
            });
        }

        function viewRecommendedSitePlan(applicationId) {
            // Fetch the recommended site plan data
            fetch(`/stmemo/get-recommended-siteplan/${applicationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.recommendedSitePlan && data.recommendedSitePlan.recommended_file) {
                        // Open the file in a new tab
                        const fileUrl = `/storage/${data.recommendedSitePlan.recommended_file}`;
                        window.open(fileUrl, '_blank');
                    } else {
                        Swal.fire({
                            title: 'No File Found',
                            text: 'No recommended site plan sketch has been uploaded for this application yet.',
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: 'Upload Now',
                            cancelButtonText: 'Close'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                openRecommendedSitePlanModal(applicationId);
                            }
                        });
                    }
                } else {
                    if (data.table_missing) {
                        Swal.fire('Database Error', 'The recommended site plans table has not been created yet. Please contact the system administrator.', 'error');
                    } else {
                        Swal.fire('Error', data.message || 'Failed to retrieve recommended site plan data', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An error occurred while retrieving the recommended site plan', 'error');
            });
        }
    </script>

    <!-- Recommended Site Plan Sketch Modal -->
    <div id="recommendedSitePlanModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        <i data-lucide="file-plus" class="w-5 h-5 inline mr-2"></i>
                        Upload Recommended Site Plan Sketch
                    </h3>
                    <button onclick="closeRecommendedSitePlanModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Existing file display -->
                <div id="existing-recommended-file"></div>

                <!-- Upload Form -->
                <form id="recommendedSitePlanForm" enctype="multipart/form-data">
                    <input type="hidden" id="recommended_application_id" name="application_id">
                    
                    <div class="mb-4">
                        <label for="recommended_file" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Recommended Site Plan Sketch
                        </label>
                        <input type="file" 
                               id="recommended_file" 
                               name="recommended_file" 
                               accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                        <p class="text-xs text-gray-500 mt-1">
                            Supported formats: PDF, JPG, JPEG, PNG (Max size: 10MB)
                        </p>
                    </div>

                    <div class="mb-4">
                        <label for="recommended_description" class="block text-sm font-medium text-gray-700 mb-2">
                            Description (Optional)
                        </label>
                        <textarea id="recommended_description" 
                                  name="description" 
                                  rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Enter description for the recommended site plan sketch..."></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" 
                                onclick="closeRecommendedSitePlanModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" 
                                id="uploadRecommendedBtn"
                                onclick="uploadRecommendedSitePlan()"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                            <i data-lucide="upload" class="w-4 h-4 mr-2"></i>
                            Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </script>

<!-- Include View Recommended Site Plan Modal -->
@include('stmemo.partials.view-recommended-site-plan-modal')

<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

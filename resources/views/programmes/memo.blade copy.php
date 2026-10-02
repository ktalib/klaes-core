 @extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('styles')
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
@endsection

@section('content')
<style>
    /* Custom DataTables styling */
    .dataTables_wrapper {
        font-family: inherit;
    }
    
    .dataTables_length select,
    .dataTables_filter input {
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .dataTables_length select:focus,
    .dataTables_filter input:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .dataTables_info {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .dataTables_paginate .paginate_button {
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        margin: 0 0.125rem;
        background: white;
        color: #374151;
        text-decoration: none;
    }
    
    .dataTables_paginate .paginate_button:hover {
        background-color: #f3f4f6;
        border-color: #9ca3af;
        color: #374151;
    }
    
    .dataTables_paginate .paginate_button.current {
        background-color: #10b981;
        border-color: #10b981;
        color: white;
    }
    
    .dataTables_paginate .paginate_button.disabled {
        color: #9ca3af;
        cursor: not-allowed;
    }

    /* Badge styles */
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        gap: 0.25rem;
    }
    
    .badge-approved {
        background-color: #d1fae5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    
    .badge-pending {
        background-color: #fef3c7;
        color: #d97706;
        border: 1px solid #fde68a;
    }
    
    .badge-declined {
        background-color: #fee2e2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    
    .badge-partial {
        background-color: #e0e7ff;
        color: #6366f1;
        border: 1px solid #c7d2fe;
    }

    /* Prerequisites combined badge */
    .prerequisites-badge {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        align-items: flex-start;
    }
    
    .prerequisite-item {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
    }

    /* Action dropdown styles */
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .action-toggle {
        transition: all 0.15s ease;
        padding: 0.5rem;
        border: none;
        background: none;
        cursor: pointer;
        border-radius: 50%;
    }
    
    .action-toggle:hover {
        background-color: #f3f4f6;
        transform: scale(1.05);
    }
    
    .action-menu {
        position: absolute;
        top: 100%;
        right: 0;
        z-index: 1000;
        min-width: 200px;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border: 1px solid #e5e7eb;
        padding: 0.5rem 0;
        margin-top: 0.25rem;
        display: none;
    }
    
    .action-menu.show {
        display: block;
    }
    
    .action-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 1rem;
        color: #374151;
        text-decoration: none;
        transition: background-color 0.15s ease;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
        gap: 0.5rem;
    }
    
    .action-item:hover {
        background-color: #f9fafb;
    }
    
    .action-item.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .action-item.disabled:hover {
        background-color: transparent;
    }

    /* Info icon for multiple owners */
    .info-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 16px;
        width: 16px;
        background-color: #e5e7eb;
        color: #4b5563;
        border-radius: 50%;
        font-size: 10px;
        margin-left: 4px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    .info-icon:hover {
        background-color: #d1d5db;
        transform: scale(1.1);
    }

    /* Improved card styling */
    .memo-card {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        border: 1px solid #e5e7eb;
        overflow: hidden;
    }
    
    .memo-card-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .memo-card-body {
        padding: 1.5rem;
    }

    /* Tab styling improvements */
    .tab-button {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        cursor: pointer;
    }
    
    .tab-button.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
    }
    
    .tab-button:not(.active) {
        background: white;
        color: #6b7280;
        border-color: #e5e7eb;
    }
    
    .tab-button:not(.active):hover {
        background: #f9fafb;
        color: #10b981;
        border-color: #10b981;
    }

    /* Main tab styling */
    .main-tab {
        padding: 1rem 1.5rem;
        border-bottom: 2px solid transparent;
        color: #6b7280;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .main-tab.active {
        color: #10b981;
        border-bottom-color: #10b981;
        background: rgba(16, 185, 129, 0.05);
    }
    
    .main-tab:hover {
        color: #10b981;
        background: rgba(16, 185, 129, 0.05);
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .memo-card-header {
            padding: 1rem;
        }
        
        .memo-card-body {
            padding: 1rem;
        }
        
        .tab-button {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .action-menu {
            position: fixed;
            left: 50%;
            transform: translateX(-50%);
            top: auto;
            bottom: 20px;
            right: auto;
            width: 90%;
        }
    }
</style>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="p-6 bg-gray-50 min-h-screen">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Sectional Titling Memo Management</h1>
            <p class="text-gray-600">Manage and generate sectional titling memos for primary and unit applications</p>
        </div>

        <!-- Main Navigation Tabs -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <nav class="flex" aria-label="Tabs">
                <button 
                    onclick="showMainTab('primary-applications')"
                    id="primary-applications-tab"
                    class="main-tab active flex items-center gap-2"
                >
                    <i data-lucide="file-text" class="w-5 h-5"></i>
                    <span class="font-semibold">Primary Applications</span>
                </button>
                <button 
                    onclick="showMainTab('unit-applications')"
                    id="unit-applications-tab"
                    class="main-tab flex items-center gap-2"
                >
                    <i data-lucide="layout-grid" class="w-5 h-5"></i>
                    <span class="font-semibold">Unit Applications</span>
                </button>
            </nav>
        </div>

        <!-- Primary Applications Tab Content -->
        <div id="primary-applications">
            <!-- Sub-tabs for Primary Applications -->
            <div class="flex gap-4 mb-6">
                <button 
                    onclick="showSubTab('primary', 'not-generated')"
                    id="primary-not-generated-tab"
                    class="tab-button active"
                >
                    <i data-lucide="clipboard-plus" class="w-4 h-4"></i>
                    <span>Not Generated</span>
                </button>
                <button 
                    onclick="showSubTab('primary', 'generated')"
                    id="primary-generated-tab"
                    class="tab-button"
                >
                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                    <span>Generated Memos</span>
                </button>
            </div>
            
            <!-- Not Generated Primary Memos -->
            <div id="primary-not-generated" class="memo-card">
                <div class="memo-card-header">
                    <h2 class="text-xl font-bold">Primary Applications - Memo Not Generated</h2>
                    <p class="text-green-100 mt-1">Applications pending memo generation</p>
                </div>
                <div class="memo-card-body">
                    <table id="primaryNotGeneratedTable" class="min-w-full">
                        <thead>
                            <tr>
                                <th>File No</th>
                                <th>Owner</th>
                                <th>LGA</th>
                                <th>Land Use</th>
                                <th>Prerequisites</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasNotGenerated = false; @endphp
                            @foreach($motherApplications as $application)
                                @php
                                    // Check if memo already exists
                                    $memoData = DB::connection('sqlsrv')->table('memos')
                                        ->where('application_id', $application->id)
                                        ->where('memo_type', 'primary')
                                        ->first();
                                        
                                    if ($memoData) continue;
                                    $hasNotGenerated = true;
                                    
                                    // Check prerequisites
                                    $planningStatus = $application->planning_recommendation_status ?? 'pending';
                                    $directorStatus = $application->application_status ?? 'pending';
                                    $planningApproved = strtolower($planningStatus) === 'approved';
                                    $directorApproved = strtolower($directorStatus) === 'approved';
                                    $canGenerateMemo = $planningApproved && $directorApproved;
                                @endphp
                                
                                <tr>
                                    <td>{{ $application->fileno ?? 'N/A' }}</td>
                                    <td>
                                        @if(!empty($application->multiple_owners_names) && json_decode($application->multiple_owners_names))
                                            @php
                                                $owners = json_decode($application->multiple_owners_names);
                                                $firstOwner = isset($owners[0]) ? $owners[0] : 'N/A';
                                                $allOwners = json_encode($owners);
                                            @endphp
                                            {{ $firstOwner }}
                                            <span class="info-icon" onclick="showOwners({{ $allOwners }})">i</span>
                                        @else
                                            {{ $application->owner_name ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>{{ $application->property_lga ?? 'N/A' }}</td>
                                    <td>{{ $application->land_use ?? 'N/A' }}</td>
                                    <td>
                                        <div class="prerequisites-badge">
                                            <div class="prerequisite-item">
                                                @if($planningApproved)
                                                    <i data-lucide="check-circle" class="w-3 h-3 text-green-600"></i>
                                                    <span class="text-green-600">Planning: Approved</span>
                                                @else
                                                    <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i>
                                                    <span class="text-amber-600">Planning: {{ ucfirst($planningStatus) }}</span>
                                                @endif
                                            </div>
                                            <div class="prerequisite-item">
                                                @if($directorApproved)
                                                    <i data-lucide="check-circle" class="w-3 h-3 text-green-600"></i>
                                                    <span class="text-green-600">ST Director: Approved</span>
                                                @else
                                                    <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i>
                                                    <span class="text-amber-600">Director: {{ ucfirst($directorStatus) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $application->created_at ? date('d M Y', strtotime($application->created_at)) : 'N/A' }}</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button type="button" class="action-toggle" onclick="toggleActionMenu(this)">
                                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                            </button>
                                            <div class="action-menu">
                                                <a href="{{ route('sectionaltitling.viewrecorddetail')}}?id={{$application->id}}" class="action-item">
                                                    <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
                                                    <span>View Record</span>
                                                </a>
                                                @if($canGenerateMemo)
                                                    <a href="{{ route('programmes.generate_memo', $application->id) }}" class="action-item">
                                                        <i data-lucide="file-plus" class="w-4 h-4 text-green-600"></i>
                                                        <span>Generate Memo</span>
                                                    </a>
                                                @else
                                                    <span class="action-item disabled" title="Prerequisites required: Planning Recommendation and ST Director's Approval">
                                                        <i data-lucide="file-plus" class="w-4 h-4 text-gray-400"></i>
                                                        <span>Generate Memo</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            
                            @if(!$hasNotGenerated)
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-gray-500">
                                        <i data-lucide="clipboard-check" class="w-12 h-12 mx-auto mb-2 text-gray-300"></i>
                                        <p>All primary applications have generated memos</p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Generated Primary Memos -->
            <div id="primary-generated" class="memo-card hidden">
                <div class="memo-card-header">
                    <h2 class="text-xl font-bold">Primary Applications - Generated Memos</h2>
                    <p class="text-green-100 mt-1">Applications with completed memos</p>
                </div>
                <div class="memo-card-body">
                    <div class="overflow-x-auto">
                        <table id="primaryGeneratedTable" class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Memo No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CofO No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LGA</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Land Use</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commencement Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Residual Term</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @php $hasGenerated = false; @endphp
                                @foreach($motherApplications as $application)
                                    @php
                                        // Check if memo exists
                                        $memoData = DB::connection('sqlsrv')->table('memos')
                                            ->where('application_id', $application->id)
                                            ->where('memo_type', 'primary')
                                            ->first();
                                            
                                        if (!$memoData) continue;
                                        $hasGenerated = true;
                                        
                                        // Calculate terms
                                        $startDate = \Carbon\Carbon::parse($application->approval_date ?? now());
                                        $totalYears = $memoData->term_years ?? 40;
                                        $currentYear = now()->year;
                                        $elapsedYears = $currentYear - $startDate->year;
                                        $residualYears = $memoData->residual_years ?? max(0, $totalYears - $elapsedYears);
                                        $commencementDate = $memoData->commencement_date ?? $application->approval_date ?? now();
                                        $formattedCommencementDate = date('d M Y', strtotime($commencementDate));
                                    @endphp
                                    
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $memoData->memo_no ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $application->fileno ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $memoData->certificate_number ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if(!empty($application->multiple_owners_names) && json_decode($application->multiple_owners_names))
                                                @php
                                                    $owners = json_decode($application->multiple_owners_names);
                                                    $firstOwner = isset($owners[0]) ? $owners[0] : 'N/A';
                                                    $allOwners = json_encode($owners);
                                                @endphp
                                                {{ $firstOwner }}
                                                <span class="info-icon" onclick="showOwners({{ $allOwners }})">i</span>
                                            @else
                                                {{ $application->owner_name ?? 'N/A' }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $application->property_lga ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $application->land_use ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $totalYears }} Years</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $formattedCommencementDate }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $residualYears }} Years</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="action-dropdown">
                                                <button type="button" class="action-toggle" onclick="toggleActionMenu(this)">
                                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                </button>
                                                <div class="action-menu">
                                                    <a href="{{ route('sectionaltitling.viewrecorddetail')}}?id={{$application->id}}" class="action-item">
                                                        <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
                                                        <span>View Record</span>
                                                    </a>
                                                    <a href="{{ route('programmes.view_memo_primary', $application->id) }}" class="action-item">
                                                        <i data-lucide="clipboard" class="w-4 h-4 text-amber-600"></i>
                                                        <span>View Memo</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                
                                @if(!$hasGenerated)
                                    <tr>
                                        <td colspan="10" class="text-center py-8 text-gray-500">
                                            <i data-lucide="clipboard-x" class="w-12 h-12 mx-auto mb-2 text-gray-300"></i>
                                            <p>No generated memos found</p>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Unit Applications Tab Content -->
        <div id="unit-applications" class="hidden">
            <!-- Sub-tabs for Unit Applications -->
            <div class="flex gap-4 mb-6">
                <button 
                    onclick="showSubTab('unit', 'not-generated')"
                    id="unit-not-generated-tab"
                    class="tab-button active"
                >
                    <i data-lucide="clipboard-plus" class="w-4 h-4"></i>
                    <span>Not Generated</span>
                </button>
                <button 
                    onclick="showSubTab('unit', 'generated')"
                    id="unit-generated-tab"
                    class="tab-button"
                >
                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                    <span>Generated Memos</span>
                </button>
            </div>
            
            <!-- Not Generated Unit Memos -->
            <div id="unit-not-generated" class="memo-card">
                <div class="memo-card-header">
                    <h2 class="text-xl font-bold">Unit Applications - Memo Not Generated</h2>
                    <p class="text-green-100 mt-1">Unit applications pending memo generation</p>
                </div>
                <div class="memo-card-body">
                    <table id="unitNotGeneratedTable" class="min-w-full">
                        <thead>
                            <tr>
                                <th>ST FileNo</th>
                                <th>Scheme No</th>
                                <th>Unit Owner</th>
                                <th>LGA</th>
                                <th>Unit</th>
                                <th>Land Use</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasNotGeneratedUnits = false; @endphp
                            @foreach($subapplications as $unitApplication)
                                @php
                                    // Check if parent application has memo
                                    $primaryMemoData = DB::connection('sqlsrv')->table('memos')
                                        ->where('application_id', $unitApplication->main_application_id)
                                        ->where('memo_type', 'primary')
                                        ->first();
                                        
                                    if ($primaryMemoData) continue;
                                    $hasNotGeneratedUnits = true;
                                @endphp
                                
                                <tr>
                                    <td>{{ $unitApplication->fileno ?? 'N/A' }}</td>
                                    <td>{{ $unitApplication->scheme_no ?? 'N/A' }}</td>
                                    <td>
                                        @if(!empty($unitApplication->multiple_owners_names) && json_decode($unitApplication->multiple_owners_names))
                                            @php
                                                $owners = json_decode($unitApplication->multiple_owners_names);
                                                $firstOwner = isset($owners[0]) ? $owners[0] : 'N/A';
                                                $allOwners = json_encode($owners);
                                            @endphp
                                            {{ $firstOwner }}
                                            <span class="info-icon" onclick="showOwners({{ $allOwners }})">i</span>
                                        @else
                                            {{ $unitApplication->owner_name ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>{{ $unitApplication->property_lga ?? 'N/A' }}</td>
                                    <td>{{ $unitApplication->unit_number ?? '' }}</td>
                                    <td>{{ $unitApplication->land_use ?? 'N/A' }}</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button type="button" class="action-toggle" onclick="toggleActionMenu(this)">
                                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                            </button>
                                            <div class="action-menu">
                                                <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}" class="action-item">
                                                    <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
                                                    <span>View Record</span>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            
                            @if(!$hasNotGeneratedUnits)
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-gray-500">
                                        <i data-lucide="clipboard-check" class="w-12 h-12 mx-auto mb-2 text-gray-300"></i>
                                        <p>All unit applications have generated memos</p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Generated Unit Memos -->
            <div id="unit-generated" class="memo-card ">
                <div class="memo-card-header">
                    <h2 class="text-xl font-bold">Unit Applications - Generated Memos</h2>
                    <p class="text-green-100 mt-1">Unit applications with completed memos</p>
                </div>
                <div class="memo-card-body">
                    <table id="unitGeneratedTable" class="min-w-full">
                        <thead>
                            <tr>
                                <th>Memo No</th>
                                <th>ST FileNo</th>
                                <th>Scheme No</th>
                                <th>Unit Owner</th>
                                <th>LGA</th>
                                <th>Unit</th>
                                <th>Land Use</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasGeneratedUnits = false; @endphp
                            @foreach($subapplications as $unitApplication)
                                @php
                                    // Check if parent application has memo
                                    $primaryMemoData = DB::connection('sqlsrv')->table('memos')
                                        ->where('application_id', $unitApplication->main_application_id)
                                        ->where('memo_type', 'primary')
                                        ->first();
                                        
                                    if (!$primaryMemoData) continue;
                                    $hasGeneratedUnits = true;
                                @endphp
                                
                                <tr>
                                    <td>{{ $primaryMemoData->memo_no ?? 'N/A' }}</td>
                                    <td>{{ $unitApplication->fileno ?? 'N/A' }}</td>
                                    <td>{{ $unitApplication->scheme_no ?? 'N/A' }}</td>
                                    <td>
                                        @if(!empty($unitApplication->multiple_owners_names) && json_decode($unitApplication->multiple_owners_names))
                                            @php
                                                $owners = json_decode($unitApplication->multiple_owners_names);
                                                $firstOwner = isset($owners[0]) ? $owners[0] : 'N/A';
                                                $allOwners = json_encode($owners);
                                            @endphp
                                            {{ $firstOwner }}
                                            <span class="info-icon" onclick="showOwners({{ $allOwners }})">i</span>
                                        @else
                                            {{ $unitApplication->owner_name ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>{{ $unitApplication->property_lga ?? 'N/A' }}</td>
                                    <td>{{ $unitApplication->unit_number ?? '' }}</td>
                                    <td>{{ $unitApplication->land_use ?? 'N/A' }}</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button type="button" class="action-toggle" onclick="toggleActionMenu(this)">
                                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                            </button>
                                            <div class="action-menu">
                                                <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}" class="action-item">
                                                    <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
                                                    <span>View Record</span>
                                                </a>
                                                <a href="{{ route('programmes.view_memo_primary', $unitApplication->main_application_id) }}?url=unit={{ $unitApplication->unit_number ?? '' }}&unit_id={{$unitApplication->id ?? ''}}" class="action-item">
                                                    <i data-lucide="clipboard" class="w-4 h-4 text-amber-600"></i>
                                                    <span>View Memo</span>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            
                            @if(!$hasGeneratedUnits)
                                <tr>
                                    <td colspan="8" class="text-center py-8 text-gray-500">
                                        <i data-lucide="clipboard-x" class="w-12 h-12 mx-auto mb-2 text-gray-300"></i>
                                        <p>No generated unit memos found</p>
                                    </td>
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

<!-- Include SweetAlert2 for notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
// Disable DataTables error alerts
$.fn.dataTable.ext.errMode = 'none';

// Global variables for DataTables
let primaryNotGeneratedTable, primaryGeneratedTable, unitNotGeneratedTable, unitGeneratedTable;

// Show owners popup
function showOwners(owners) {
    let ownersList = '';
    owners.forEach(owner => {
        ownersList += `<li class="py-1">${owner}</li>`;
    });
    
    Swal.fire({
        title: 'All Owners',
        html: `<ul class="text-left list-disc list-inside space-y-1">${ownersList}</ul>`,
        icon: 'info',
        confirmButtonText: 'Close',
        confirmButtonColor: '#10B981',
        customClass: {
            popup: 'rounded-lg'
        }
    });
}

// Toggle action menu
function toggleActionMenu(button) {
    // Close all other menus
    document.querySelectorAll('.action-menu').forEach(menu => {
        if (menu !== button.nextElementSibling) {
            menu.classList.remove('show');
        }
    });
    
    // Toggle current menu
    const menu = button.nextElementSibling;
    menu.classList.toggle('show');
}

// Close menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// Main tab switching
function showMainTab(tabId) {
    // Hide all main tab contents
    document.getElementById('primary-applications').classList.add('hidden');
    document.getElementById('unit-applications').classList.add('hidden');
    
    // Reset all main tab buttons
    document.getElementById('primary-applications-tab').classList.remove('active');
    document.getElementById('unit-applications-tab').classList.remove('active');
    
    // Show selected tab content
    document.getElementById(tabId).classList.remove('hidden');
    
    // Highlight active tab button
    document.getElementById(tabId + '-tab').classList.add('active');
    
    // Initialize DataTables if needed
    if (tabId === 'primary-applications') {
        initializePrimaryTables();
    } else if (tabId === 'unit-applications') {
        initializeUnitTables();
    }
}

// Sub tab switching
function showSubTab(type, subTab) {
    if (type === 'primary') {
        // Hide all primary sub-tabs
        document.getElementById('primary-not-generated').classList.add('hidden');
        document.getElementById('primary-generated').classList.add('hidden');
        
        // Reset all primary sub-tab buttons
        document.getElementById('primary-not-generated-tab').classList.remove('active');
        document.getElementById('primary-generated-tab').classList.remove('active');
        
        // Show selected sub-tab
        document.getElementById('primary-' + subTab).classList.remove('hidden');
        document.getElementById('primary-' + subTab + '-tab').classList.add('active');
        
        // Initialize DataTable
        if (subTab === 'not-generated' && !primaryNotGeneratedTable) {
            initializePrimaryNotGeneratedTable();
        } else if (subTab === 'generated' && !primaryGeneratedTable) {
            initializePrimaryGeneratedTable();
        }
    } else if (type === 'unit') {
        // Hide all unit sub-tabs
        document.getElementById('unit-not-generated').classList.add('hidden');
        document.getElementById('unit-generated').classList.add('hidden');
        
        // Reset all unit sub-tab buttons
        document.getElementById('unit-not-generated-tab').classList.remove('active');
        document.getElementById('unit-generated-tab').classList.remove('active');
        
        // Show selected sub-tab
        document.getElementById('unit-' + subTab).classList.remove('hidden');
        document.getElementById('unit-' + subTab + '-tab').classList.add('active');
        
        // Initialize DataTable
        if (subTab === 'not-generated' && !unitNotGeneratedTable) {
            initializeUnitNotGeneratedTable();
        } else if (subTab === 'generated' && !unitGeneratedTable) {
            initializeUnitGeneratedTable();
        }
    }
}

// Initialize Primary Tables
function initializePrimaryTables() {
    if (!primaryNotGeneratedTable) {
        initializePrimaryNotGeneratedTable();
    }
}

// Initialize Unit Tables
function initializeUnitTables() {
    if (!unitNotGeneratedTable) {
        initializeUnitNotGeneratedTable();
    }
}

// Initialize Primary Not Generated Table
function initializePrimaryNotGeneratedTable() {
    if ($.fn.DataTable.isDataTable('#primaryNotGeneratedTable')) {
        $('#primaryNotGeneratedTable').DataTable().destroy();
    }
    
    // Check if table has data rows
    const tableRows = $('#primaryNotGeneratedTable tbody tr').length;
    const hasData = tableRows > 0 && !$('#primaryNotGeneratedTable tbody tr').first().find('td[colspan]').length;
    
    primaryNotGeneratedTable = $('#primaryNotGeneratedTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: 'Bfrtip',
        buttons: hasData ? [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel mr-1"></i> Export Excel',
                className: 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf mr-1"></i> Export PDF',
                className: 'bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm ml-2'
            }
        ] : [],
        columnDefs: [
            { orderable: false, targets: -1 },
            { className: 'text-center', targets: -1 },
            { targets: '_all', defaultContent: '' }
        ],
        language: {
            search: "Search records:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            emptyTable: "No primary applications pending memo generation",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
}

// Initialize Primary Generated Table
function initializePrimaryGeneratedTable() {
    if ($.fn.DataTable.isDataTable('#primaryGeneratedTable')) {
        $('#primaryGeneratedTable').DataTable().destroy();
    }
    
    // Check if table has data rows
    const tableRows = $('#primaryGeneratedTable tbody tr').length;
    const hasData = tableRows > 0 && !$('#primaryGeneratedTable tbody tr').first().find('td[colspan]').length;
    
    primaryGeneratedTable = $('#primaryGeneratedTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: 'Bfrtip',
        buttons: hasData ? [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel mr-1"></i> Export Excel',
                className: 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf mr-1"></i> Export PDF',
                className: 'bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm ml-2'
            }
        ] : [],
        columnDefs: [
            { orderable: false, targets: -1 },
            { className: 'text-center', targets: -1 },
            { targets: '_all', defaultContent: '' }
        ],
        language: {
            search: "Search records:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            emptyTable: "No generated primary memos found",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
}

// Initialize Unit Not Generated Table
function initializeUnitNotGeneratedTable() {
    if ($.fn.DataTable.isDataTable('#unitNotGeneratedTable')) {
        $('#unitNotGeneratedTable').DataTable().destroy();
    }
    
    // Check if table has data rows
    const tableRows = $('#unitNotGeneratedTable tbody tr').length;
    const hasData = tableRows > 0 && !$('#unitNotGeneratedTable tbody tr').first().find('td[colspan]').length;
    
    unitNotGeneratedTable = $('#unitNotGeneratedTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: 'Bfrtip',
        buttons: hasData ? [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel mr-1"></i> Export Excel',
                className: 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf mr-1"></i> Export PDF',
                className: 'bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm ml-2'
            }
        ] : [],
        columnDefs: [
            { orderable: false, targets: -1 },
            { className: 'text-center', targets: -1 },
            { targets: '_all', defaultContent: '' }
        ],
        columns: [
            { data: 0, defaultContent: '' },
            { data: 1, defaultContent: '' },
            { data: 2, defaultContent: '' },
            { data: 3, defaultContent: '' },
            { data: 4, defaultContent: '' },
            { data: 5, defaultContent: '' },
            { data: 6, defaultContent: '', orderable: false }
        ],
        language: {
            search: "Search records:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            emptyTable: "No unit applications pending memo generation",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
}

// Initialize Unit Generated Table
function initializeUnitGeneratedTable() {
    if ($.fn.DataTable.isDataTable('#unitGeneratedTable')) {
        $('#unitGeneratedTable').DataTable().destroy();
    }
    
    unitGeneratedTable = $('#unitGeneratedTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel mr-1"></i> Export Excel',
                className: 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf mr-1"></i> Export PDF',
                className: 'bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm ml-2'
            }
        ],
        columnDefs: [
            { orderable: false, targets: -1 },
            { className: 'text-center', targets: -1 }
        ],
        language: {
            search: "Search records:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
}

// Initialize on document ready
$(document).ready(function() {
    // Initialize the first tab
    showMainTab('primary-applications');
    showSubTab('primary', 'not-generated');
});
</script>
@endsection
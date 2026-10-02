@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('styles')
<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">
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
        min-width: 36px;
        min-height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
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
        max-width: 280px;
        width: max-content;
        max-height: 90vh;
        overflow-y: auto;
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
        padding: 0.75rem 1rem;
        color: #374151;
        text-decoration: none;
        transition: background-color 0.15s ease;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
        gap: 0.5rem;
        white-space: nowrap;
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

    .action-item span {
        overflow: hidden;
        text-overflow: ellipsis;
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

    /* Upload Modal Styles */
    .upload-modal-backdrop {
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }
    
    .drag-highlight {
        border-color: #10b981 !important;
        background-color: #f0fdf4 !important;
    }
    
    /* File Upload Animation */
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
    
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Table responsive container */
    .table-responsive {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        max-width: 100%;
        position: relative;
    }

    /* Table styling */
    .memo-table {
        min-width: 900px; /* Ensure minimum width for all columns */
        width: 100%;
        border-collapse: collapse;
    }

    .memo-table th,
    .memo-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .memo-table th {
        background-color: #f9fafb;
        font-weight: 600;
        color: #374151;
        font-size: 0.875rem;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .memo-table tbody tr:hover {
        background-color: #f9fafb;
    }

    /* Action column should be sticky on mobile */
    .action-column {
        position: sticky;
        right: 0;
        background-color: white;
        z-index: 5;
        box-shadow: -2px 0 4px rgba(0, 0, 0, 0.1);
        min-width: 80px;
    }

    .action-column th {
        background-color: #f9fafb;
        box-shadow: -2px 0 4px rgba(0, 0, 0, 0.1);
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .memo-card-header {
            padding: 1rem;
        }
        
        .memo-card-body {
            padding: 0; /* Remove padding for full-width scrolling */
        }
        
        .tab-button {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Table responsive adjustments */
        .table-responsive {
            margin: 0 -1rem; /* Extend to card edges */
            padding: 0 1rem; /* Add back content padding */
        }

        .memo-table {
            min-width: 1100px; /* Wider on mobile to accommodate content */
        }

        .memo-table th,
        .memo-table td {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        /* Enhanced mobile action menu */
        .action-toggle {
            min-width: 44px;
            min-height: 44px;
            padding: 0.625rem;
        }
        
        .action-menu {
            position: fixed !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            top: auto !important;
            bottom: 80px !important;
            right: auto !important;
            width: 90% !important;
            max-width: calc(100vw - 32px) !important;
            min-width: 280px !important;
            font-size: 0.875rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 9999; /* Higher z-index to appear above scrollable content */
        }
        
        .action-item {
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            gap: 0.75rem;
        }
        
        /* Scroll indicator */
        .table-responsive::after {
            content: "← Scroll to see more →";
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: #6b7280;
            pointer-events: none;
        }
        
        #uploadMemoModal .relative {
            width: 95%;
            margin: 1rem auto;
        }
    }

    /* Tablet responsive adjustments */
    @media (min-width: 769px) and (max-width: 1024px) {
        .action-menu {
            min-width: 220px;
            max-width: 300px;
        }
        
        .action-toggle {
            min-width: 40px;
            min-height: 40px;
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Primary Applications - ST Memo Management</h1>
            <p class="text-gray-600">Manage and generate sectional titling memos for primary applications</p>
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
                    <div class="table-responsive">
                        <table id="primaryNotGeneratedTable" class="memo-table">
                            <thead>
                                <tr>
                                    <th class="min-w-[120px]">File No</th>
                                    <th class="min-w-[150px]">Owner</th>
                                    <th class="min-w-[100px]">LGA</th>
                                    <th class="min-w-[120px]">Land Use</th>
                                    <th class="min-w-[200px]">Prerequisites</th>
                                    <th class="min-w-[120px]">Date Created</th>
                                    <th class="action-column">Actions</th>
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
                                    <td class="action-column">
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
            </div>
            
            <!-- Generated Primary Memos -->
            <div id="primary-generated" class="memo-card hidden">
                <div class="memo-card-header">
                    <h2 class="text-xl font-bold">Primary Applications - Generated Memos</h2>
                    <p class="text-green-100 mt-1">Applications with completed memos</p>
                </div>
                <div class="memo-card-body">
                    <div class="table-responsive">
                        <table id="primaryGeneratedTable" class="memo-table">
                            <thead>
                                <tr>
                                    <th class="min-w-[100px]">Memo No</th>
                                    <th class="min-w-[120px]">File No</th>
                                    <th class="min-w-[120px]">CofO No</th>
                                    <th class="min-w-[150px]">Owner</th>
                                    <th class="min-w-[100px]">LGA</th>
                                    <th class="min-w-[120px]">Land Use</th>
                                    <th class="min-w-[80px]">Term</th>
                                    <th class="min-w-[130px]">Commencement Date</th>
                                    <th class="min-w-[110px]">Residual Term</th>
                                    <th class="action-column">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                        
                                        // Get uploaded memo files
                                        $latestUploadedMemo = DB::connection('sqlsrv')->table('memo_uploads')
                                            ->where('application_id', $application->id)
                                            ->where('memo_type', 'primary')
                                            ->where('status', 'active')
                                            ->orderBy('uploaded_at', 'desc')
                                            ->first();
                                    @endphp
                                    
                                    <tr>
                                        <td>{{ $memoData->memo_no ?? 'N/A' }}</td>
                                        <td>{{ $application->fileno ?? 'N/A' }}</td>
                                        <td>{{ $memoData->certificate_number ?? 'N/A' }}</td>
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
                                        <td>{{ $totalYears }} Years</td>
                                        <td>{{ $formattedCommencementDate }}</td>
                                        <td>{{ $residualYears }} Years</td>
                                        <td class="action-column">
                                            <div class="action-dropdown">
                                                <button type="button" class="action-toggle" onclick="toggleActionMenu(this)">
                                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                </button>
                                                <div class="action-menu">
                                                    <a href="{{ route('sectionaltitling.viewrecorddetail')}}?id={{$application->id}}" class="action-item">
                                                        <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
                                                        <span>View Record</span>
                                                    </a>
                                                    @if($latestUploadedMemo)
                                                        <a href="{{ route('programmes.view_uploaded_memo', $latestUploadedMemo->id) }}" target="_blank" class="action-item">
                                                            <i data-lucide="file-text" class="w-4 h-4 text-amber-600"></i>
                                                            <span>View Memo (Edited)</span>
                                                        </a>
                                                    @else
                                                        <a href="{{ route('programmes.view_memo_new', $application->id) }}" target="_blank" class="action-item">
                                                            <i data-lucide="clipboard" class="w-4 h-4 text-amber-600"></i>
                                                            <span>View Memo</span>
                                                        </a>
                                                    @endif
                                                    @if($latestUploadedMemo)
                                                        <button type="button" class="action-item disabled" disabled title="An edited memo has already been uploaded">
                                                            <i data-lucide="upload" class="w-4 h-4 text-gray-400"></i>
                                                            <span>Upload Edited ST Memo</span>
                                                        </button>
                                                    @else
                                                        <button type="button" onclick="openUploadModal({{ $application->id }}, '{{ $application->fileno ?? 'N/A' }}', 'primary')" class="action-item">
                                                            <i data-lucide="upload" class="w-4 h-4 text-green-600"></i>
                                                            <span>Upload Edited ST Memo</span>
                                                        </button>
                                                    @endif
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
    </div>
    
    <!-- Upload Memo Modal -->
    <div id="uploadMemoModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3">
                    <h3 class="text-lg font-medium text-gray-900">Upload Memo Document</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeUploadModal()">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <form id="uploadMemoForm" enctype="multipart/form-data">
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Select PDF Document
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors">
                            <div class="space-y-1 text-center">
                                <i data-lucide="file-text" class="mx-auto h-12 w-12 text-gray-400"></i>
                                <div class="flex text-sm text-gray-600">
                                    <label for="memo-file" class="relative cursor-pointer bg-white rounded-md font-medium text-green-600 hover:text-green-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-green-500">
                                        <span>Upload a file</span>
                                        <input id="memo-file" name="memo_file" type="file" class="sr-only" accept=".pdf" onchange="handleFileSelect(this)">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF files only, up to 10MB</p>
                            </div>
                        </div>
                        
                        <!-- File Preview -->
                        <div id="filePreview" class="hidden mt-3 p-3 bg-gray-50 rounded-md">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i data-lucide="file-text" class="w-5 h-5 text-red-600 mr-2"></i>
                                    <span id="fileName" class="text-sm text-gray-700"></span>
                                </div>
                                <button type="button" onclick="removeFile()" class="text-red-600 hover:text-red-800">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <span id="fileSize" class="text-xs text-gray-500"></span>
                            </div>
                        </div>
                        
                        <!-- Error Message -->
                        <div id="uploadError" class="hidden mt-3 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                            <div class="flex items-center">
                                <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i>
                                <span id="errorMessage"></span>
                            </div>
                        </div>
                        
                        <!-- Additional Fields -->
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Document Description (Optional)
                            </label>
                            <textarea 
                                id="memo-description" 
                                name="description" 
                                rows="3" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                placeholder="Add any additional notes about this memo document..."
                            ></textarea>
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end pt-4 border-t border-gray-200 space-x-3">
                        <button type="button" onclick="closeUploadModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="uploadBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <span id="uploadBtnText">Upload Edited ST Memo</span>
                            <i id="uploadSpinner" class="hidden ml-2 w-4 h-4 animate-spin" data-lucide="loader-2"></i>
                        </button>
                    </div>
                </form>
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

// Toggle action menu with responsive positioning
function toggleActionMenu(button) {
    const menu = button.nextElementSibling;
    const isMobile = window.innerWidth <= 768;
    
    // Close all other menus first
    document.querySelectorAll('.action-menu').forEach(otherMenu => {
        if (otherMenu !== menu) {
            otherMenu.classList.remove('show');
        }
    });
    
    // Toggle current menu
    if (menu.classList.contains('show')) {
        menu.classList.remove('show');
    } else {
        menu.classList.add('show');
        
        // Enhanced positioning for desktop
        if (!isMobile) {
            positionActionMenu(button, menu);
        } else {
            // On mobile, ensure menu is above scrollable content
            menu.style.position = 'fixed';
            menu.style.zIndex = '9999';
        }
    }
}

// Smart positioning for desktop action menus
function positionActionMenu(button, menu) {
    const buttonRect = button.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Reset position
    menu.style.left = '';
    menu.style.right = '';
    menu.style.top = '';
    menu.style.bottom = '';
    
    // Calculate ideal position (bottom-right of button)
    let left = buttonRect.right - menuRect.width;
    let top = buttonRect.bottom + 4;
    
    // Adjust if going off right edge
    if (left < 8) {
        left = 8;
    }
    
    // Adjust if going off left edge  
    if (left + menuRect.width > viewportWidth - 8) {
        left = viewportWidth - menuRect.width - 8;
    }
    
    // Adjust if going off bottom edge
    if (top + menuRect.height > viewportHeight - 8) {
        top = buttonRect.top - menuRect.height - 4;
    }
    
    // Apply calculated position
    menu.style.position = 'fixed';
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
    menu.style.right = 'auto';
    menu.style.bottom = 'auto';
}

// Close all action menus
function closeAllActionMenus() {
    document.querySelectorAll('.action-menu').forEach(menu => {
        menu.classList.remove('show');
    });
}

// Close menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        closeAllActionMenus();
    }
});

// Close menu on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAllActionMenus();
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    // Close all menus on resize to prevent positioning issues
    closeAllActionMenus();
});

// Sub tab switching for primary applications
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
    }
}

// Initialize Primary Tables
function initializePrimaryTables() {
    if (!primaryNotGeneratedTable) {
        initializePrimaryNotGeneratedTable();
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
        responsive: false,
        scrollX: true,
        autoWidth: false,
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

    primaryNotGeneratedTable.columns.adjust();
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
        responsive: false,
        scrollX: true,
        autoWidth: false,
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

    primaryGeneratedTable.columns.adjust();
}



// Upload Modal Functions
let currentApplicationId = null;
let currentFileNo = null;
let currentMemoType = 'primary';

function openUploadModal(applicationId, fileNo, memoType = 'primary') {
    if (!applicationId) {
        showUploadError('Invalid application reference.');
        return;
    }

    currentApplicationId = applicationId;
    currentFileNo = fileNo;
    currentMemoType = memoType || 'primary';
    
    // Reset form
    document.getElementById('uploadMemoForm').reset();
    document.getElementById('filePreview').classList.add('hidden');
    document.getElementById('uploadError').classList.add('hidden');
    document.getElementById('uploadBtn').disabled = true;
    
    // Show modal
    document.getElementById('uploadMemoModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeUploadModal() {
    document.getElementById('uploadMemoModal').classList.add('hidden');
    document.body.style.overflow = 'auto'; // Restore scrolling
    currentApplicationId = null;
    currentFileNo = null;
    currentMemoType = 'primary';
}

function handleFileSelect(input) {
    const file = input.files[0];
    const errorDiv = document.getElementById('uploadError');
    const previewDiv = document.getElementById('filePreview');
    const uploadBtn = document.getElementById('uploadBtn');
    
    // Hide error initially
    errorDiv.classList.add('hidden');
    
    if (!file) {
        previewDiv.classList.add('hidden');
        uploadBtn.disabled = true;
        return;
    }
    
    // Validate file type
    if (file.type !== 'application/pdf') {
        showUploadError('Please select a PDF file only.');
        input.value = '';
        previewDiv.classList.add('hidden');
        uploadBtn.disabled = true;
        return;
    }
    
    // Validate file size (10MB max)
    const maxSize = 10 * 1024 * 1024; // 10MB in bytes
    if (file.size > maxSize) {
        showUploadError('File size must be less than 10MB.');
        input.value = '';
        previewDiv.classList.add('hidden');
        uploadBtn.disabled = true;
        return;
    }
    
    // Show file preview
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    previewDiv.classList.remove('hidden');
    uploadBtn.disabled = false;
}

function removeFile() {
    document.getElementById('memo-file').value = '';
    document.getElementById('filePreview').classList.add('hidden');
    document.getElementById('uploadError').classList.add('hidden');
    document.getElementById('uploadBtn').disabled = true;
}

function showUploadError(message) {
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('uploadError').classList.remove('hidden');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Handle form submission
document.getElementById('uploadMemoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('memo-file');
    const descriptionInput = document.getElementById('memo-description');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadBtnText = document.getElementById('uploadBtnText');
    const uploadSpinner = document.getElementById('uploadSpinner');
    
    if (!fileInput.files[0]) {
        showUploadError('Please select a file to upload.');
        return;
    }
    
    if (!currentApplicationId) {
        showUploadError('Invalid application ID.');
        return;
    }
    
    // Show loading state
    uploadBtn.disabled = true;
    uploadBtnText.textContent = 'Uploading...';
    uploadSpinner.classList.remove('hidden');
    
    // Create FormData
    const formData = new FormData();
    formData.append('memo_file', fileInput.files[0]);
    formData.append('description', descriptionInput.value);
    formData.append('application_id', currentApplicationId);
    formData.append('file_no', currentFileNo);
    formData.append('memo_type', currentMemoType);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    
    // Submit via AJAX
    fetch('/programmes/upload-memo', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            Swal.fire({
                title: 'Success!',
                text: data.message || 'Memo uploaded successfully.',
                icon: 'success',
                confirmButtonColor: '#10B981',
                customClass: {
                    popup: 'rounded-lg'
                }
            }).then(() => {
                closeUploadModal();
                // Optionally reload the page or update the table
                location.reload();
            });
        } else {
            throw new Error(data.message || 'Upload failed.');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showUploadError(error.message || 'An error occurred during upload. Please try again.');
    })
    .finally(() => {
        // Reset loading state
        uploadBtn.disabled = false;
    uploadBtnText.textContent = 'Upload Edited ST Memo';
        uploadSpinner.classList.add('hidden');
    });
});

// Drag and drop functionality
const dropZone = document.querySelector('#uploadMemoModal .border-dashed');
if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    dropZone.addEventListener('drop', handleDrop, false);
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    e.currentTarget.classList.add('border-green-400', 'bg-green-50');
}

function unhighlight(e) {
    e.currentTarget.classList.remove('border-green-400', 'bg-green-50');
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        const fileInput = document.getElementById('memo-file');
        fileInput.files = files;
        handleFileSelect(fileInput);
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUploadModal();
    }
});

// Initialize on document ready
$(document).ready(function() {
    // Initialize the primary applications sub-tabs
    showSubTab('primary', 'not-generated');
});
</script>
@endsection
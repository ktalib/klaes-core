@extends('layouts.app')
@section('page-title')
    {{ __('Directors Approval') }}
@endsection

<style>
    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .tab-button {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        color: #6b7280;
    }
    
    /* Individual tab colors */
    .tab-button[data-tab="summary"] {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #3b82f6;
    }
    .tab-button[data-tab="summary"].active {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .tab-button[data-tab="detterment"] {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        border-color: #10b981;
    }
    .tab-button[data-tab="detterment"].active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .tab-button[data-tab="edms"] {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-color: #f59e0b;
    }
    .tab-button[data-tab="edms"].active {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .tab-button[data-tab="initial"] {
        background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
        border-color: #8b5cf6;
    }
    .tab-button[data-tab="initial"].active {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }
    
    .tab-button:hover:not(.active) {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Document File Card Styles */
    .document-file-card {
      position: relative;
      transform: translateY(0);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .document-file-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .document-preview {
      position: relative;
      overflow: hidden;
    }
    
    .document-preview::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .document-file-card:hover .document-preview::before {
      opacity: 1;
    }
    
    /* File type specific colors */
    .document-file-card[data-file-type="pdf"] .document-preview {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    }
    
    .document-file-card[data-file-type="image"] .document-preview {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    }
    
    .document-file-card[data-file-type="doc"] .document-preview {
      background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    }
    
    /* Status badge animations */
    .document-file-card .status-badge {
      transition: all 0.2s ease;
    }
    
    .document-file-card:hover .status-badge {
      transform: scale(1.05);
    }
    
    /* Action buttons hover effects */
    .document-file-card .action-btn {
      transition: all 0.2s ease;
      position: relative;
      overflow: hidden;
    }
    
    .document-file-card .action-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }
    
    .document-file-card .action-btn:hover::before {
      left: 100%;
    }
    
    /* Document icon animations */
    .document-icon {
      transition: transform 0.3s ease;
    }
    
    .document-file-card:hover .document-icon {
      transform: scale(1.1) rotate(5deg);
    }
    
    /* Truncate text with ellipsis */
    .truncate-text {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    /* Loading animation for document previews */
    .document-loading {
      position: relative;
    }
    
    .document-loading::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 20px;
      height: 20px;
      margin: -10px 0 0 -10px;
      border: 2px solid #f3f3f3;
      border-top: 2px solid #3498db;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .document-file-card {
        margin-bottom: 1rem;
      }
      
      .document-preview {
        height: 120px;
      }
    }
</style>
@include('sectionaltitling.partials.assets.css')
@section('content')
    @php
        $statusClass = match (strtolower($application->application_status ?? '')) {
            'approve' => 'bg-green-100 text-green-800',
            'approved' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'decline' => 'bg-red-100 text-red-800',
            'declined' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };

        $statusIcon = match (strtolower($application->application_status ?? '')) {
            'approve' => 'check-circle',
            'approved' => 'check-circle',
            'pending' => 'clock',
            'decline' => 'x-circle',
            'declined' => 'x-circle',
            default => 'help-circle'
        };
    @endphp
    @php
        $isSua_local = ($application->is_sua_unit ?? 0) == 1 || (strtoupper($application->unit_type ?? '') === 'SUA');
        $isPlanningApproved = (strtolower($application->planning_recommendation_status ?? '') === 'approved') ||
            (!$isSua_local && strtolower($application->mother_planning_status ?? '') === 'approved');

        // Approval is disabled if planning recommendation is not approved or already approved
        $approvalDisabled = !$isPlanningApproved || (strtolower($application->application_status ?? '') === 'approved');
    @endphp
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')

        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                <div class="modal-content p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h2 class="text-lg font-medium">
                                {{ $PageTitle }}
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                                    <i data-lucide="{{ $statusIcon }}" class="w-3 h-3 mr-1"></i>
                                    {{ $application->application_status }}
                                </span>
                            </h2>
                            <p class="text-xs text-gray-500 mt-1">
                                Approval Date:
                                {{ $application->approval_date ? \Carbon\Carbon::parse($application->approval_date)->format('Y-m-d') : '' }}
                            </p>
                        </div>
                        <button onclick="window.history.back()" class="text-gray-500 hover:text-gray-700">
                            <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div class="py-2">
                        @if(strtoupper(trim($application->unit_type ?? '')) !== 'SUA')
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                                <!-- Primary Application Info (First, as requested) - Hidden for SUA -->
                                <div class="flex items-center mb-3">
                                    <div class="bg-blue-100 text-blue-800 rounded-full p-1 mr-2">
                                        <i data-lucide="file-check" class="w-4 h-4"></i>
                                    </div>
                                    <div> 
                                        <h3 class="text-sm font-medium text-blue-800">Original Owner</h3>
                                        <p class="text-xs text-gray-700">
                                            @if ($application->primary_applicant_type == 'individual')
                                                {{ $application->applicant_title }} {{ $application->primary_first_name }}
                                                {{ $application->primary_surname }}
                                            @elseif($application->primary_applicant_type == 'corporate')
                                                {{ $application->primary_rc_number ?? '-' }}
                                                {{ $application->primary_corporate_name }}
                                            @elseif($application->primary_applicant_type == 'multiple')
                                                @php
                                                    $names = @json_decode(
                                                        $application->primary_multiple_owners_names,
                                                        true,
                                                    );
                                                    if (is_array($names) && count($names) > 0) {
                                                        echo implode(', ', $names);
                                                    } else {
                                                        echo $application->primary_multiple_owners_names;
                                                    }
                                                @endphp
                                            @endif

                                            <span class="inline-flex items-center px-2 py-0.5 ml-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                                <i data-lucide="link" class="w-3 h-3 mr-1"></i>Ministry FileNo:
                                                {{ $application->primary_fileno ?? 'N/A' }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 ml-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                                <i data-lucide="link" class="w-3 h-3 mr-1"></i>Sectional Titling FileNo:
                                                {{ $application->np_fileno ?? $application->primary_fileno ?? 'N/A' }}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Current Application Info -->
                                <div class="flex justify-between items-center border-t border-gray-200 pt-3">
                                    <div>
                                        <h3 class="text-sm font-medium">{{ $application->land_use ?? 'Property' }}</h3>
                                        <p class="text-xs text-gray-600 mt-1">
                                            File No: <span class="font-medium">{{ $application->fileno ?? 'N/A' }}</span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <h3 class="text-sm font-medium">
                                            @if ($application->applicant_type == 'individual')
                                                {{ $application->applicant_title }} {{ $application->first_name }}
                                                {{ $application->surname }}
                                            @elseif($application->applicant_type == 'corporate')
                                                {{ $application->rc_number }} {{ $application->corporate_name }}
                                            @elseif($application->applicant_type == 'multiple')
                                                @php
                                                    $names = @json_decode($application->multiple_owners_names, true);
                                                    if (is_array($names) && count($names) > 0) {
                                                        echo implode(', ', $names);
                                                    } else {
                                                        echo $application->multiple_owners_names;
                                                    }
                                                @endphp
                                            @endif
                                        </h3>
                                        <p class="text-xs text-gray-600 mt-1">Applicant</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(request('url') !== 'view')
                                        <!-- Tabs Navigation -->
                                        <div class="grid grid-cols-5 gap-2 mb-4">
                                            <button class="tab-button active" data-tab="summary">
                                                <i data-lucide="user" class="w-3.5 h-3.5 mr-1.5"></i>
                                                SUMMARY
                                            </button>

                                            <button class="tab-button" data-tab="detterment">
                                                <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5"></i>
                                                DOCUMENTS
                                            </button>
                                            <button class="tab-button" data-tab="edms">
                                                <i data-lucide="folder" class="w-3.5 h-3.5 mr-1.5"></i>
                                                EDMS
                                            </button>
                                            <button class="tab-button" data-tab="bills">
                                                <i data-lucide="receipt" class="w-3.5 h-3.5 mr-1.5"></i>
                                                BILLS
                                            </button>

                                            @if (request()->query('url') !== 'recommendation')
                                                <button 
                                                    class="tab-button{{ $approvalDisabled ? ' cursor-not-allowed bg-gray-200 text-gray-400' : '' }}" 
                                                    data-tab="initial"
                                                    {{ $approvalDisabled ? 'disabled' : '' }}
                                                    style="{{ $approvalDisabled ? 'pointer-events: none; opacity: 0.6;' : '' }}"
                                                >
                                                    <i data-lucide="banknote" class="w-3.5 h-3.5 mr-1.5"></i>
                                                    APPROVAL
                                                </button>
                                            @endif
                                        </div>

                                        <!-- Summary Tab -->
                                        <div id="summary-tab" class="tab-content active">
                                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                                <div class="p-4 border-b">
                                                    <h3 class="text-sm font-medium">Application Summary</h3>
                                                    <p class="text-xs text-gray-500">Unit application overview and details</p>
                                                </div>
                                                <div class="p-4 space-y-4">
                                                    <!-- Original Owner Information - Hidden for SUA -->
                                                    @if(strtoupper(trim($application->unit_type ?? '')) !== 'SUA')
                                                        <div class="mb-6">
                                                            <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b">Original Owner Information</h4>
                                                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                                                <div class="flex items-center">
                                                                    <div class="mr-4">
                                                                        @if(isset($application->primary_passport) && !empty($application->primary_passport))
                                                                            <img src="{{ asset('storage/app/public/' . $application->primary_passport) }}"
                                                                                alt="Primary Owner" class="w-16 h-16 object-cover rounded-full border border-gray-300">
                                                                        @else
                                                                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                                                                <i data-lucide="user" class="w-8 h-8 text-blue-500"></i>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                    <div>
                                                                        <p class="text-gray-900 font-medium">
                                                                            @if($application->primary_applicant_type == 'individual')
                                                                                {{ $application->primary_applicant_title ?? '' }} 
                                                                                {{ $application->primary_first_name ?? '' }} 
                                                                                {{ $application->primary_middle_name ?? '' }} 
                                                                                {{ $application->primary_surname ?? '' }}
                                                                            @elseif($application->primary_applicant_type == 'corporate')
                                                                                {{ $application->primary_corporate_name ?? 'N/A' }}
                                                                                <span class="text-sm text-gray-600">(RC: {{ $application->primary_rc_number ?? '-' }})</span>
                                                                            @elseif($application->primary_applicant_type == 'multiple')
                                                                                @php
                                                                                    $names = is_array($application->primary_multiple_owners_names)
                                                                                        ? $application->primary_multiple_owners_names
                                                                                        : (is_string($application->primary_multiple_owners_names)
                                                                                            ? json_decode($application->primary_multiple_owners_names, true)
                                                                                            : []);

                                                                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                                                                        $names = [];
                                                                                    }
                                                                                @endphp
                                                                                Multiple Owners
                                                                            @endif
                                                                        </p>
                                                                        <p class="text-gray-600 text-sm">
                                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                                Original Owner
                                                                            </span>
                                                                            <span class="ml-2">File No: {{ $application->primary_fileno ?? 'N/A' }}</span>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- Unit Owner Information -->
                                                    <div class="mb-6">
                                                        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b">Unit Owner Information</h4>

                                                        @if($application->applicant_type == 'individual')
                                                            <!-- Individual Applicant -->
                                                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                                                <div class="flex items-center">
                                                                    <div class="mr-4">
                                                                        @if(isset($application->passport) && !empty($application->passport))
                                                                            <img src="{{ asset('storage/app/public/' . $application->passport) }}"
                                                                                alt="Applicant" class="w-16 h-16 object-cover rounded-full border border-gray-300">
                                                                        @else
                                                                            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                                                                                <i data-lucide="user" class="w-8 h-8 text-gray-400"></i>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                    <div>
                                                                        <p class="text-gray-900 font-medium">
                                                                            {{ $application->applicant_title ?? '' }}
                                                                            {{ $application->first_name ?? '' }}
                                                                            {{ $application->middle_name ?? '' }}
                                                                            {{ $application->surname ?? '' }}
                                                                        </p>
                                                                        <p class="text-gray-600 text-sm">{{ $application->email ?? 'N/A' }}</p>
                                                                        <p class="text-gray-600 text-sm">{{ $application->phone_number ?? 'N/A' }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @elseif($application->applicant_type == 'corporate')
                                                            <!-- Corporate Applicant -->
                                                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                                                <div class="flex items-center">
                                                                    <div class="mr-4">
                                                                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                                                            <i data-lucide="building-2" class="w-8 h-8 text-blue-500"></i>
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <p class="text-gray-900 font-medium">{{ $application->corporate_name ?? 'N/A' }}</p>
                                                                        <p class="text-gray-600 text-sm">RC Number: {{ $application->rc_number ?? 'N/A' }}</p>
                                                                        <p class="text-gray-600 text-sm">{{ $application->email ?? 'N/A' }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @elseif($application->applicant_type == 'multiple')
                                                            <!-- Multiple Owners -->
                                                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                                                <h5 class="text-sm font-medium mb-3">Multiple Owners</h5>
                                                                <div class="space-y-2">
                                                                    @php
                                                                        $ownerNames = is_array($application->multiple_owners_names)
                                                                            ? $application->multiple_owners_names
                                                                            : (is_string($application->multiple_owners_names)
                                                                                ? json_decode($application->multiple_owners_names, true)
                                                                                : []);

                                                                        if (json_last_error() !== JSON_ERROR_NONE) {
                                                                            $ownerNames = [];
                                                                        }
                                                                    @endphp

                                                                    @foreach(array_slice($ownerNames, 0, 3) as $index => $ownerName)
                                                                        <div class="flex items-center">
                                                                            <div class="mr-2 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-700 text-sm font-medium">
                                                                                {{ $index + 1 }}
                                                                            </div>
                                                                            <span>{{ $ownerName }}</span>
                                                                        </div>
                                                                    @endforeach

                                                                    @if(count($ownerNames) > 3)
                                                                        <div class="pl-10 text-blue-600 text-sm">
                                                                            + {{ count($ownerNames) - 3 }} more owners
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Address:</p>
                                                                <p class="text-sm">
                                                                    @php
                                                                        // Build address from individual components for consistency
                                                                        $addressParts = [];

                                                                        if (!empty($application->property_house_no)) {
                                                                            $addressParts[] = ucwords(strtolower($application->property_house_no));
                                                                        }

                                                                        if (!empty($application->property_street_name)) {
                                                                            $addressParts[] = ucwords(strtolower($application->property_street_name));
                                                                        }

                                                                        if (!empty($application->property_lga)) {
                                                                            $addressParts[] = ucwords(strtolower($application->property_lga));
                                                                        }

                                                                        if (!empty($application->property_state)) {
                                                                            $addressParts[] = ucwords(strtolower($application->property_state));
                                                                        }

                                                                        // If no individual components, try the full address field
                                                                        if (empty($addressParts) && !empty($application->address)) {
                                                                            $formattedAddress = ucwords(strtolower(trim($application->address)));
                                                                        } else {
                                                                            $formattedAddress = implode(', ', $addressParts);
                                                                        }

                                                                        echo !empty($formattedAddress) ? $formattedAddress : 'N/A';
                                                                    @endphp
                                                                </p>
                                                            </div>
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Contact:</p>
                                                                <p class="text-sm">
                                                                    {{ $application->phone_number ?? 'N/A' }}
                                                                    @if(isset($application->email) && !empty($application->email))
                                                                        <br>{{ $application->email }}
                                                                    @endif
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Unit Information -->
                                                    <div class="mb-6">
                                                        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b">Unit Information</h4>
                                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">File Number:</p>
                                                                <p class="text-sm font-medium">{{ $application->fileno ?? 'N/A' }}</p>
                                                            </div>
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Land Use:</p>
                                                                <p class="text-sm">{{ ucfirst($application->land_use ?? 'N/A') }}</p>
                                                            </div>
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Scheme Number:</p>
                                                                <p class="text-sm">{{ $application->scheme_no ?? 'N/A' }}</p>
                                                            </div>
                                                        </div>

                                                        <!-- Unit Specific Details -->
                                                        <div class="p-3 bg-blue-50 border-l-4 border-blue-400 rounded-lg">
                                                            <h5 class="text-sm font-medium text-blue-800 mb-2">Unit Details</h5>
                                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                                <div>
                                                                    <p class="text-xs text-gray-600 font-medium">Block Number:</p>
                                                                    <p class="text-sm">{{ $application->block_number ?? 'N/A' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs text-gray-600 font-medium">Section Number:</p>
                                                                    <p class="text-sm">{{ $application->floor_number ?? 'N/A' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs text-gray-600 font-medium">Unit Number:</p>
                                                                    <p class="text-sm">{{ $application->unit_number ?? 'N/A' }}</p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Shared Areas Section -->
                                                        @if(isset($application->shared_areas) && !empty($application->shared_areas))
                                                            <div class="mt-3">
                                                                <p class="text-xs text-gray-600 font-medium mb-1">Shared Areas:</p>
                                                                <div class="flex flex-wrap gap-1">
                                                                    @php
                                                                        $sharedAreas = is_string($application->shared_areas)
                                                                            ? json_decode($application->shared_areas, true)
                                                                            : (is_array($application->shared_areas) ? $application->shared_areas : []);

                                                                        if (json_last_error() !== JSON_ERROR_NONE) {
                                                                            $sharedAreas = [];
                                                                        }
                                                                    @endphp

                                                                    @foreach($sharedAreas as $area)
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                            {{ ucfirst($area) }}
                                                                        </span>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Application Status -->
                                                    <div class="mb-6">
                                                        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b">Application Status</h4>
                                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Application Status:</p>
                                                                <p class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                                    {{ $statusClass }}">
                                                                    <i data-lucide="{{ $statusIcon }}" class="w-3 h-3 mr-1"></i>
                                                                    {{ $application->application_status ?? 'Pending' }}
                                                                </p>
                                                            </div>

                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Planning Recommendation:</p>
                                                                @php
                                                                    $isSua_local = ($application->is_sua_unit ?? 0) == 1 || (strtoupper($application->unit_type ?? '') === 'SUA');
                                                                    $effectivePlanningStatus = (strtolower($application->planning_recommendation_status ?? '') === 'approved')
                                                                        ? 'Approved'
                                                                        : ((!$isSua_local && strtolower($application->mother_planning_status ?? '') === 'approved') ? 'Approved (Mother)' : ($application->planning_recommendation_status ?? 'Pending'));

                                                                    $badgeClass = match (strtolower($effectivePlanningStatus)) {
                                                                        'approved', 'approved (mother)' => 'bg-green-100 text-green-800',
                                                                        'declined', 'rejected' => 'bg-red-100 text-red-800',
                                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                                        default => 'bg-yellow-100 text-yellow-800'
                                                                    };
                                                                @endphp
                                                                <p class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                                                    {{ $effectivePlanningStatus }}
                                                                </p>
                                                            </div>

                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Approval Date:</p>
                                                                <p class="text-sm">{{ $application->approval_date ? \Carbon\Carbon::parse($application->approval_date)->format('Y-m-d') : 'Pending' }}</p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Financial Information -->
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b">Financial Information</h4>
                                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Application Fee:</p>
                                                                <p class="text-sm">₦{{ number_format((float) ($application->application_fee ?? 0), 2) }}</p>
                                                            </div>
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Processing Fee:</p>
                                                                <p class="text-sm">₦{{ number_format((float) ($application->processing_fee ?? 0), 2) }}</p>
                                                            </div>
                                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                                <p class="text-xs text-gray-600 font-medium">Survey Fee:</p>
                                                                <p class="text-sm">₦{{ number_format((float) ($application->site_plan_fee ?? 0), 2) }}</p>
                                                            </div>
                                                        </div>

                                                        <div class="mt-3 p-3 bg-green-50 rounded-lg">
                                                            <div class="flex justify-between items-center">
                                                                <p class="text-sm font-medium text-gray-600">Total Initial Bill:</p>
                                                                <p class="text-lg font-bold text-green-700">
                                                                    ₦{{ number_format((float) ($application->application_fee ?? 0) + (float) ($application->processing_fee ?? 0) + (float) ($application->site_plan_fee ?? 0), 2) }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Action Buttons -->
                                                    <div class="flex justify-between items-center pt-4 mt-6 border-t border-gray-200">
                                                        <button type="button" onclick="window.history.back()" class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
                                                            <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                                            Back
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Detterment Bill Tab -->
                                        <div id="detterment-tab" class="tab-content">
                                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                                <div class="p-4 border-b">
                                                    <h3 class="text-sm font-medium">Documents</h3>
                                                    <p class="text-xs text-gray-500"> </p>
                                                </div>
                                                <input type="hidden" id="application_id" value="{{ $application->id }}">
                                                <input type="hidden" name="fileno" value="{{ $application->fileno }}">
                                                <div class="p-4 space-y-4">
                                                    <div class="grid grid-cols-2 gap-4">
                                                        @php
                                                            // Ensure documents is decoded from JSON if needed
                                                            $documents = is_string($application->documents)
                                                                ? json_decode($application->documents, true)
                                                                : $application->documents;
                                                        @endphp

                                                        <!-- Application Letter -->
                                                        @if (isset($documents['application_letter']))
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                                                <div class="h-48 bg-gray-100 relative">
                                                                    <img src="{{ asset('storage/app/public/' . $documents['application_letter']['path']) }}"
                                                                        alt="Application Letter" class="w-full h-full object-cover">
                                                                    <div class="absolute top-2 right-2">
                                                                        <button
                                                                            class="p-1 bg-white rounded-full shadow-sm hover:bg-gray-100"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['application_letter']['path']) }}', 'Application Letter')">
                                                                            <i data-lucide="maximize-2"
                                                                                class="w-4 h-4 text-gray-700"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="p-3">
                                                                    <h5 class="text-sm font-medium">Application Letter</h5>
                                                                    <p class="text-xs text-gray-500 mt-1">Uploaded on:
                                                                        {{ isset($documents['application_letter']['uploaded_at']) ? \Carbon\Carbon::parse($documents['application_letter']['uploaded_at'])->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}
                                                                    </p>
                                                                    <div class="flex mt-2 gap-2">
                                                                        <a href="{{ asset('storage/app/public/' . $documents['application_letter']['path']) }}"
                                                                            download
                                                                            class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-md flex items-center">
                                                                            <i data-lucide="download" class="w-3 h-3 mr-1"></i>
                                                                            Download
                                                                        </a>
                                                                        <button
                                                                            class="text-xs px-2 py-1 bg-gray-50 text-gray-600 rounded-md flex items-center"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['application_letter']['path']) }}', 'Application Letter')">
                                                                            <i data-lucide="eye" class="w-3 h-3 mr-1"></i> View
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-4 flex flex-col items-center justify-center">
                                                                <div class="text-gray-400 mb-2">
                                                                    <i data-lucide="file-question" class="w-10 h-10"></i>
                                                                </div>
                                                                <p class="text-sm text-gray-500">No application letter uploaded yet
                                                                </p>
                                                            </div>
                                                        @endif

                                                        <!-- Building Plan -->
                                                        @if (isset($documents['building_plan']))
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                                                <div class="h-48 bg-gray-100 relative">
                                                                    <img src="{{ asset('storage/app/public/' . $documents['building_plan']['path']) }}"
                                                                        alt="Building Plan" class="w-full h-full object-cover">
                                                                    <div class="absolute top-2 right-2">
                                                                        <button
                                                                            class="p-1 bg-white rounded-full shadow-sm hover:bg-gray-100"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['building_plan']['path']) }}', 'Building Plan')">
                                                                            <i data-lucide="maximize-2"
                                                                                class="w-4 h-4 text-gray-700"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="p-3">
                                                                    <h5 class="text-sm font-medium">Building Plan</h5>
                                                                    <p class="text-xs text-gray-500 mt-1">Uploaded on:
                                                                        {{ isset($documents['building_plan']['uploaded_at']) ? \Carbon\Carbon::parse($documents['building_plan']['uploaded_at'])->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}
                                                                    </p>
                                                                    <div class="flex mt-2 gap-2">
                                                                        <a href="{{ asset('storage/app/public/' . $documents['building_plan']['path']) }}"
                                                                            download
                                                                            class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-md flex items-center">
                                                                            <i data-lucide="download" class="w-3 h-3 mr-1"></i>
                                                                            Download
                                                                        </a>
                                                                        <button
                                                                            class="text-xs px-2 py-1 bg-gray-50 text-gray-600 rounded-md flex items-center"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['building_plan']['path']) }}', 'Building Plan')">
                                                                            <i data-lucide="eye" class="w-3 h-3 mr-1"></i> View
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-4 flex flex-col items-center justify-center">
                                                                <div class="text-gray-400 mb-2">
                                                                    <i data-lucide="file-question" class="w-10 h-10"></i>
                                                                </div>
                                                                <p class="text-sm text-gray-500">No building plan uploaded yet</p>
                                                            </div>
                                                        @endif

                                                        <!-- Architectural Design -->
                                                        @if (isset($documents['architectural_design']))
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                                                <div class="h-48 bg-gray-100 relative">
                                                                    <img src="{{ asset('storage/app/public/' . $documents['architectural_design']['path']) }}"
                                                                        alt="Architectural Design"
                                                                        class="w-full h-full object-cover">
                                                                    <div class="absolute top-2 right-2">
                                                                        <button
                                                                            class="p-1 bg-white rounded-full shadow-sm hover:bg-gray-100"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['architectural_design']['path']) }}', 'Architectural Design')">
                                                                            <i data-lucide="maximize-2"
                                                                                class="w-4 h-4 text-gray-700"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="p-3">
                                                                    <h5 class="text-sm font-medium">Architectural Design</h5>
                                                                    <p class="text-xs text-gray-500 mt-1">Uploaded on:
                                                                        {{ isset($documents['architectural_design']['uploaded_at']) ? \Carbon\Carbon::parse($documents['architectural_design']['uploaded_at'])->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}
                                                                    </p>
                                                                    <div class="flex mt-2 gap-2">
                                                                        <a href="{{ asset('storage/app/public/' . $documents['architectural_design']['path']) }}"
                                                                            download
                                                                            class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-md flex items-center">
                                                                            <i data-lucide="download" class="w-3 h-3 mr-1"></i>
                                                                            Download
                                                                        </a>
                                                                        <button
                                                                            class="text-xs px-2 py-1 bg-gray-50 text-gray-600 rounded-md flex items-center"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['architectural_design']['path']) }}', 'Architectural Design')">
                                                                            <i data-lucide="eye" class="w-3 h-3 mr-1"></i> View
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-4 flex flex-col items-center justify-center">
                                                                <div class="text-gray-400 mb-2">
                                                                    <i data-lucide="file-question" class="w-10 h-10"></i>
                                                                </div>
                                                                <p class="text-sm text-gray-500">No architectural design uploaded
                                                                    yet</p>
                                                            </div>
                                                        @endif

                                                        <!-- Ownership Document -->
                                                        @if (isset($documents['ownership_document']))
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                                                <div class="h-48 bg-gray-100 relative">
                                                                    <img src="{{ asset('storage/app/public/' . $documents['ownership_document']['path']) }}"
                                                                        alt="Ownership Document"
                                                                        class="w-full h-full object-cover">
                                                                    <div class="absolute top-2 right-2">
                                                                        <button
                                                                            class="p-1 bg-white rounded-full shadow-sm hover:bg-gray-100"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['ownership_document']['path']) }}', 'Ownership Document')">
                                                                            <i data-lucide="maximize-2"
                                                                                class="w-4 h-4 text-gray-700"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="p-3">
                                                                    <h5 class="text-sm font-medium">Ownership Document</h5>
                                                                    <p class="text-xs text-gray-500 mt-1">Uploaded on:
                                                                        {{ isset($documents['ownership_document']['uploaded_at']) ? \Carbon\Carbon::parse($documents['ownership_document']['uploaded_at'])->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}
                                                                    </p>
                                                                    <div class="flex mt-2 gap-2">
                                                                        <a href="{{ asset('storage/app/public/' . $documents['ownership_document']['path']) }}"
                                                                            download
                                                                            class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-md flex items-center">
                                                                            <i data-lucide="download" class="w-3 h-3 mr-1"></i>
                                                                            Download
                                                                        </a>
                                                                        <button
                                                                            class="text-xs px-2 py-1 bg-gray-50 text-gray-600 rounded-md flex items-center"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $documents['ownership_document']['path']) }}', 'Ownership Document')">
                                                                            <i data-lucide="eye" class="w-3 h-3 mr-1"></i> View
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div
                                                                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-4 flex flex-col items-center justify-center">
                                                                <div class="text-gray-400 mb-2">
                                                                    <i data-lucide="file-question" class="w-10 h-10"></i>
                                                                </div>
                                                                <p class="text-sm text-gray-500">No ownership document uploaded yet
                                                                </p>
                                                            </div>
                                                        @endif

                                                        <!-- Site Plan (Survey) -->
                                                        @php
                                                            // Check for site plan data from surveyCadastralRecord or application documents
                                                            $sitePlanDocument = null;
                                                            if (isset($documents['site_plan'])) {
                                                                $sitePlanDocument = $documents['site_plan'];
                                                            } else {
                                                                // Try to get from surveyCadastralRecord table using main application ID
                                                                try {
                                                                    $surveyData = DB::connection('sqlsrv')->table('surveyCadastralRecord')
                                                                        ->where('applicationID', $application->main_application_id ?? $application->id)
                                                                        ->first();
                                                                    if ($surveyData && $surveyData->site_plan_document) {
                                                                        $sitePlanDocument = [
                                                                            'path' => $surveyData->site_plan_document,
                                                                            'uploaded_at' => $surveyData->created_at ?? now(),
                                                                            'survey_plan_no' => $surveyData->survey_plan_no ?? 'N/A'
                                                                        ];
                                                                    }
                                                                } catch (Exception $e) {
                                                                    // Handle error silently
                                                                }
                                                            }
                                                        @endphp

                                                        @if ($sitePlanDocument)
                                                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                                                <div class="h-48 bg-gray-100 relative">
                                                                    <img src="{{ asset('storage/app/public/' . $sitePlanDocument['path']) }}"
                                                                        alt="Site Plan (Survey)" class="w-full h-full object-cover">
                                                                    <div class="absolute top-2 right-2">
                                                                        <button class="p-1 bg-white rounded-full shadow-sm hover:bg-gray-100"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $sitePlanDocument['path']) }}', 'Site Plan (Survey)')">
                                                                            <i data-lucide="maximize-2" class="w-4 h-4 text-gray-700"></i>
                                                                        </button>
                                                                    </div>
                                                                    <!-- Survey Badge -->
                                                                    <div class="absolute top-2 left-2">
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                                                            <i data-lucide="map" class="w-3 h-3 mr-1"></i>
                                                                            Survey
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="p-3">
                                                                    <h5 class="text-sm font-medium">Site Plan (Survey)</h5>
                                                                    <p class="text-xs text-gray-500 mt-1">
                                                                        @if(isset($sitePlanDocument['survey_plan_no']))
                                                                            Plan No: {{ $sitePlanDocument['survey_plan_no'] }} | 
                                                                        @endif
                                                                        Uploaded on: {{ isset($sitePlanDocument['uploaded_at']) ? \Carbon\Carbon::parse($sitePlanDocument['uploaded_at'])->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}
                                                                    </p>
                                                                    <div class="flex mt-2 gap-2">
                                                                        <a href="{{ asset('storage/app/public/' . $sitePlanDocument['path']) }}"
                                                                            download
                                                                            class="text-xs px-2 py-1 bg-green-50 text-green-600 rounded-md flex items-center">
                                                                            <i data-lucide="download" class="w-3 h-3 mr-1"></i>
                                                                            Download
                                                                        </a>
                                                                        <button
                                                                            class="text-xs px-2 py-1 bg-gray-50 text-gray-600 rounded-md flex items-center"
                                                                            onclick="previewDocument('{{ asset('storage/app/public/' . $sitePlanDocument['path']) }}', 'Site Plan (Survey)')">
                                                                            <i data-lucide="eye" class="w-3 h-3 mr-1"></i> View
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-4 flex flex-col items-center justify-center">
                                                                <div class="text-gray-400 mb-2">
                                                                    <i data-lucide="map" class="w-10 h-10"></i>
                                                                </div>
                                                                <p class="text-sm text-gray-500">No site plan (survey) uploaded yet</p>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <hr class="my-4">

                                                    <div class="flex justify-between items-center">
                                                        <div class="flex gap-2">
                                                            <button type="button" onclick="window.history.back();"
                                                                class="flex items-center px-3 py-1 text-xs bg-white text-black p-2 border border-gray-500 rounded-md hover:bg-gray-800">
                                                                <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                Back
                                                            </button>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bills Tab -->
                                        <div id="bills-tab" class="tab-content">
                                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                                <div class="p-4 border-b bg-gradient-to-r from-pink-50 to-rose-50">
                                                    <div class="flex items-center">
                                                        <i data-lucide="receipt" class="w-5 h-5 text-pink-600 mr-2"></i>
                                                        <div>
                                                            <h3 class="text-sm font-medium text-gray-900">Bills Information</h3>
                                                            <p class="text-xs text-gray-500">Unit application billing details</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-6 space-y-6">

                                                    @php
                                                        // Get bill data for unit applications
                                                        $initialBillData = null;
                                                        $billBalanceData = null;
                                                        $finalBillData = null;

                                                        try {
                                                            // Get Initial Bill from current application
                                                            $initialBillData = [
                                                                'receipt_number' => $application->receipt_number ?? 'N/A',
                                                                'amount' => ((float) ($application->application_fee ?? 0)) + ((float) ($application->processing_fee ?? 0)) + ((float) ($application->site_plan_fee ?? 0)),
                                                                'payment_date' => $application->payment_date ?? 'N/A',
                                                                'status' => $application->Payment_Status ?? 'Paid'
                                                            ];

                                                            // Get Bill Balance from billing table for unit applications
                                                            $billBalanceBill = DB::connection('sqlsrv')->table('billing')
                                                                ->where('application_id', $application->id)
                                                                ->first();

                                                            if ($billBalanceBill) {
                                                                $billBalanceData = [
                                                                    'reference_id' => 'BB-' . $application->fileno . '-' . date('Y'),
                                                                    'amount' => ((float) ($billBalanceBill->bill_balance ?? 0)) + ((float) ($billBalanceBill->dev_charges ?? 0)) + ((float) ($billBalanceBill->Penalty_Fees ?? 0)),
                                                                    'receipt_number' => $billBalanceBill->Bill_Balance_receipt ?? 'N/A',
                                                                    'receipt_date' => $billBalanceBill->Bill_Balance_receipt_date ?? 'N/A',
                                                                    'status' => $billBalanceBill->Payment_Status ?? 'Pending'
                                                                ];
                                                            }

                                                            // Get Final Bill from final_bills table
                                                            $finalBill = DB::connection('sqlsrv')->table('final_bills')
                                                                ->where('application_id', $application->id)
                                                                ->first();

                                                            if ($finalBill) {
                                                                $finalBillData = [
                                                                    'receipt_number' => $finalBill->receipt_number ?? 'N/A',
                                                                    'amount' => ((float) ($finalBill->recertification_fee ?? 0)) + ((float) ($finalBill->assignment_fee ?? 0)),
                                                                    'payment_date' => $finalBill->payment_date ?? 'N/A',
                                                                    'status' => $finalBill->Payment_Status ?? 'Pending'
                                                                ];
                                                            }
                                                        } catch (Exception $e) {
                                                            // Handle error silently
                                                        }
                                                    @endphp

                                                    <!-- 1. Initial Bill Receipt -->
                                                    <div class="bg-white border-2 border-blue-100 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                                                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 rounded-t-xl">
                                                            <div class="flex items-center">
                                                                <i data-lucide="receipt" class="w-5 h-5 text-white mr-3"></i>
                                                                <h4 class="text-sm font-semibold text-white">Initial Bill Receipt</h4>
                                                            </div>
                                                        </div>
                                                        <div class="p-6">
                                                            @if($initialBillData)
                                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                                    <div class="space-y-4">
                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Receipt Number:</span>
                                                                            <span class="text-sm font-semibold text-gray-900 font-mono">{{ $initialBillData['receipt_number'] }}</span>
                                                                        </div>
                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Payment Date:</span>
                                                                            <span class="text-sm font-semibold text-gray-900">{{ $initialBillData['payment_date'] }}</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="space-y-4">

                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Status:</span>
                                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                                                {{ $initialBillData['status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                                                <i data-lucide="{{ $initialBillData['status'] === 'Paid' ? 'check-circle' : 'clock' }}" class="w-4 h-4 mr-1"></i>
                                                                                {{ $initialBillData['status'] }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="text-center py-8">
                                                                    <i data-lucide="file-x" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                                                    <p class="text-sm text-gray-500">No initial bill data available</p>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- 2. Bill Balance Reference ID -->
                                                    <div class="bg-white border-2 border-orange-100 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                                                        <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4 rounded-t-xl">
                                                            <div class="flex items-center">
                                                                <i data-lucide="hash" class="w-5 h-5 text-white mr-3"></i>
                                                                <h4 class="text-sm font-semibold text-white">Bill Balance Reference ID</h4>
                                                            </div>
                                                        </div>
                                                        <div class="p-6">
                                                            @if($billBalanceData)
                                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                                    <div class="flex items-center justify-between p-4 bg-orange-50 rounded-lg border border-orange-200">
                                                                        <span class="text-sm font-medium text-orange-600">Reference ID:</span>
                                                                        <span class="text-sm font-bold text-orange-900 font-mono">{{ $billBalanceData['reference_id'] }}</span>
                                                                    </div>
                                                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                                                        <span class="text-sm font-medium text-gray-600">Amount:</span>
                                                                        <span class="text-lg font-bold text-gray-900">₦{{ number_format($billBalanceData['amount'], 2) }}</span>
                                                                    </div>
                                                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                                                        <span class="text-sm font-medium text-gray-600">Status:</span>
                                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                                            {{ $billBalanceData['status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                                            <i data-lucide="{{ $billBalanceData['status'] === 'Paid' ? 'check-circle' : 'clock' }}" class="w-4 h-4 mr-1"></i>
                                                                            {{ $billBalanceData['status'] }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="text-center py-8">
                                                                    <i data-lucide="file-x" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                                                    <p class="text-sm text-gray-500">No bill balance data available</p>
                                                                    <div class="mt-2">
                                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                                                            <i data-lucide="x-circle" class="w-4 h-4 mr-1"></i>
                                                                            Unpaid
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- 3. Bill Balance Receipt -->
                                                    <div class="bg-white border-2 border-indigo-100 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                                                        <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 px-6 py-4 rounded-t-xl">
                                                            <div class="flex items-center">
                                                                <i data-lucide="file-check" class="w-5 h-5 text-white mr-3"></i>
                                                                <h4 class="text-sm font-semibold text-white">Bill Balance Receipt</h4>
                                                            </div>
                                                        </div>
                                                        <div class="p-6">
                                                            @if($billBalanceData && $billBalanceData['receipt_number'] !== 'N/A')
                                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                                    <div class="space-y-4">
                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Receipt Number:</span>
                                                                            <span class="text-sm font-semibold text-gray-900 font-mono">{{ $billBalanceData['receipt_number'] }}</span>
                                                                        </div>
                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Receipt Date:</span>
                                                                            <span class="text-sm font-semibold text-gray-900">{{ $billBalanceData['receipt_date'] }}</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="space-y-4">

                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Status:</span>
                                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                                                {{ $billBalanceData['status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                                                <i data-lucide="{{ $billBalanceData['status'] === 'Paid' ? 'check-circle' : 'clock' }}" class="w-4 h-4 mr-1"></i>
                                                                                {{ $billBalanceData['status'] }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="text-center py-8">
                                                                    <i data-lucide="file-x" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                                                    <p class="text-sm text-gray-500">No bill balance receipt data available</p>
                                                                    <div class="mt-2">
                                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                                                            <i data-lucide="x-circle" class="w-4 h-4 mr-1"></i>
                                                                            Unpaid
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- 4. Final Bill (if exists) -->
                                                    @if($finalBillData)
                                                        <div class="bg-white border-2 border-green-100 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                                                            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 rounded-t-xl">
                                                                <div class="flex items-center">
                                                                    <i data-lucide="check-square" class="w-5 h-5 text-white mr-3"></i>
                                                                    <h4 class="text-sm font-semibold text-white">Final Bill Receipt</h4>
                                                                </div>
                                                            </div>
                                                            <div class="p-6">
                                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                                    <div class="space-y-4">
                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Receipt Number:</span>
                                                                            <span class="text-sm font-semibold text-gray-900 font-mono">{{ $finalBillData['receipt_number'] }}</span>
                                                                        </div>
                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Payment Date:</span>
                                                                            <span class="text-sm font-semibold text-gray-900">{{ $finalBillData['payment_date'] }}</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="space-y-4">

                                                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                                            <span class="text-sm font-medium text-gray-600">Status:</span>
                                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                                                {{ $finalBillData['status'] === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                                                <i data-lucide="{{ $finalBillData['status'] === 'Paid' ? 'check-circle' : 'clock' }}" class="w-4 h-4 mr-1"></i>
                                                                                {{ $finalBillData['status'] }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <hr class="my-6 border-gray-200">

                                                    <div class="flex justify-between items-center bg-gray-50 rounded-lg p-4">
                                                        <div class="flex gap-3">
                                                            <button type="button" onclick="window.history.back()" class="flex items-center px-4 py-2 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-50 transition-colors duration-200">
                                                                <i data-lucide="undo-2" class="w-4 h-4 mr-2"></i>
                                                                Back
                                                            </button>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            Last updated: {{ now()->format('M d, Y H:i') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- EDMS Tab -->
                                        <div id="edms-tab" class="tab-content">
                                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                                <div class="p-4 border-b">
                                                    <h3 class="text-sm font-medium">EDMS Files & Information</h3>
                                                    <p class="text-xs text-gray-500">Electronic Document Management System scanned files and details</p>
                                                </div>
                                                <div class="p-4 space-y-4">
                                                    @php
                                                        // Get EDMS data for this unit application using SQL Server connection
                                                        $edmsData = null;
                                                        $scannedFiles = collect();
                                                        $pageTypings = collect();

                                                        try {
                                                            // For unit applications, check both subapplication_id and main_application_id
                                                            $edmsData = DB::connection('sqlsrv')->table('file_indexings')
                                                                ->where(function ($query) use ($application) {
                                                                    $query->where('subapplication_id', $application->id);
                                                                })
                                                                ->first();

                                                            if ($edmsData) {
                                                                // Get scanned files
                                                                $scannedFiles = DB::connection('sqlsrv')->table('scannings')
                                                                    ->where('file_indexing_id', $edmsData->id)
                                                                    ->orderBy('created_at', 'desc')
                                                                    ->get();

                                                                // Get page typings
                                                                $pageTypings = DB::connection('sqlsrv')->table('pagetypings')
                                                                    ->where('file_indexing_id', $edmsData->id)
                                                                    ->orderBy('created_at', 'desc')
                                                                    ->get();
                                                            }
                                                        } catch (Exception $e) {
                                                            // Handle error silently
                                                        }
                                                    @endphp

                                                    @if($edmsData)
                                                        <!-- File Indexing Information -->
                                                        <div class="bg-white border border-blue-200 rounded-2xl p-6 shadow-sm mb-6">
                                                            <div class="flex items-center justify-between mb-4">
                                                                <div class="flex items-center">
                                                                    <i data-lucide="folder" class="w-5 h-5 text-blue-600 mr-2"></i>
                                                                    <h4 class="text-base font-semibold text-blue-800">File Indexing Information</h4>
                                                                    @if($edmsData->subapplication_id)
                                                                        <span class="ml-3 bg-purple-100 text-purple-800 text-xs px-2.5 py-0.5 rounded-full">
                                                                            Unit Application
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-sm">
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">Unit FileNo:</p>
                                                                    <p class="font-mono text-gray-800">{{ $application->fileno ?? 'N/A' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">Ministry FileNo:</p>
                                                                    <p class="font-mono text-gray-800">{{ $application->primary_fileno ?? 'N/A' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">Sectional Titling FileNo:</p>
                                                                    <p class="font-mono text-gray-800">{{ $application->np_fileno ?? $application->primary_fileno ?? 'N/A' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">File Title:</p>
                                                                    <p class="text-gray-800">{{ $edmsData->file_title ?? 'N/A' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">Land Use Type:</p>
                                                                    <p class="text-gray-800">{{ $edmsData->land_use_type ?? 'N/A' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">Property Description:</p>
                                                                    <p class="uppercase text-gray-800">
                                                                        @if(isset($application->property_house_no) || isset($application->property_street_name))
                                                                            {{ $application->property_house_no ?? '' }}
                                                                            {{ $application->property_street_name ? ', ' . $application->property_street_name : '' }}
                                                                            {{ $application->property_lga ? ', ' . $application->property_lga : '' }}
                                                                            {{ $application->property_state ? ', ' . $application->property_state : '' }}
                                                                        @else
                                                                            N/A
                                                                        @endif
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <!-- File Properties -->
                                                            <div class="mt-6">
                                                                <p class="text-xs text-gray-600 font-medium mb-3">File Properties:</p>
                                                                <div class="flex flex-wrap gap-2">
                                                                    @if($edmsData->has_cofo)
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                            <i data-lucide="check" class="w-3 h-3 mr-1"></i> Has C of O
                                                                        </span>
                                                                    @endif
                                                                    @if($edmsData->is_merged)
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                            <i data-lucide="merge" class="w-3 h-3 mr-1"></i> Merged Plot
                                                                        </span>
                                                                    @endif
                                                                    @if($edmsData->has_transaction)
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                                            <i data-lucide="repeat" class="w-3 h-3 mr-1"></i> Has Transactions
                                                                        </span>
                                                                    @endif
                                                                    @if($edmsData->is_problematic)
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                            <i data-lucide="alert-triangle" class="w-3 h-3 mr-1"></i> Problematic
                                                                        </span>
                                                                    @endif
                                                                    @if($edmsData->is_co_owned_plot)
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                                            <i data-lucide="users" class="w-3 h-3 mr-1"></i> Co-Owned
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Scanned Files Section -->
                                                        <div class="mb-6">
                                                            <div class="flex items-center mb-3">
                                                                <i data-lucide="scan" class="w-5 h-5 text-green-600 mr-2"></i>
                                                                <h4 class="text-sm font-medium text-gray-800">Scanned Documents</h4>
                                                                <span class="ml-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">{{ $scannedFiles->count() }} files</span>
                                                            </div>

                                                            @if($scannedFiles->count() > 0)
                                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                                    @foreach($scannedFiles as $scan)
                                                                                                                                                                                                                                                <div class="document-file-card bg-white border-2 border-gray-200 rounded-xl overflow-hidden hover:shadow-lg hover:border-blue-300 transition-all duration-300 cursor-pointer group"
                                                                                                                                                                                                                                                    onclick="previewScannedDocument('{{ asset('storage/' . $scan->document_path) }}', '{{ $scan->original_filename ?? 'Document' }}')">

                                                                                                                                                                                                                                                    <!-- Document Preview/Icon -->
                                                                                                                                                                                                                                                    <div class="document-preview relative bg-gradient-to-br from-blue-50 to-indigo-100 h-32 flex items-center justify-center">
                                                                                                                                                                                                                                                        @php
                                                                                                                                                                                                                                                            $fileExtension = pathinfo($scan->document_path ?? '', PATHINFO_EXTENSION);
                                                                                                                                                                                                                                                            $isPdf = strtolower($fileExtension) === 'pdf';
                                                                                                                                                                                                                                                            $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                                                                                                                                                                                                                                        @endphp

                                                                                                                                                                                                                                                        @if($scan->document_path && $isImage)
                                                                                                                                                                                                                                                            <!-- Image Preview -->
                                                                                                                                                                                                                                                            <img src="{{ asset('storage/' . $scan->document_path) }}" 
                                                                                                                                                                                                                                                                alt="Document Preview" 
                                                                                                                                                                                                                                                                class="w-full h-full object-cover">
                                                                                                                                                                                                                                                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                                                                                                                                                                                                                                                                <i data-lucide="zoom-in" class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300"></i>
                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                        @elseif($isPdf)
                                                                                                                                                                                                                                                            <!-- PDF Icon -->
                                                                                                                                                                                                                                                            <div class="text-center">
                                                                                                                                                                                                                                                                <div class="w-16 h-20 mx-auto mb-2 relative">
                                                                                                                                                                                                                                                                    <div class="w-full h-full bg-red-500 rounded-lg shadow-lg flex items-center justify-center">
                                                                                                                                                                                                                                                                        <i data-lucide="file-text" class="w-8 h-8 text-white"></i>
                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                    <div class="absolute -bottom-1 -right-1 bg-red-600 text-white text-xs px-1 py-0.5 rounded text-center font-bold">
                                                                                                                                                                                                                                                                        PDF
                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                        @else
                                                                                                                                                                                                                                                            <!-- Generic Document Icon -->
                                                                                                                                                                                                                                                            <div class="text-center">
                                                                                                                                                                                                                                                                <div class="w-16 h-20 mx-auto mb-2 relative">
                                                                                                                                                                                                                                                                    <div class="w-full h-full bg-blue-500 rounded-lg shadow-lg flex items-center justify-center">
                                                                                                                                                                                                                                                                        <i data-lucide="file" class="w-8 h-8 text-white"></i>
                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                    <div class="absolute -bottom-1 -right-1 bg-blue-600 text-white text-xs px-1 py-0.5 rounded text-center font-bold">
                                                                                                                                                                                                                                                                        {{ strtoupper($fileExtension ?: 'DOC') }}
                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                        @endif

                                                                                                                                                                                                                                                        <!-- Status Badge -->
                                                                                                                                                                                                                                                        <div class="absolute top-2 right-2">
                                                                                                                                                                                                                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium shadow-sm
                                                                                                                                                                                                                                                                {{ $scan->status === 'completed' ? 'bg-green-100 text-green-800 border border-green-200' :
                                                                        ($scan->status === 'processing' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : 'bg-gray-100 text-gray-800 border border-gray-200') }}">
                                                                                                                                                                                                                                                                @if($scan->status === 'completed')
                                                                                                                                                                                                                                                                    <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                                                                                                                                                                                                                                                @elseif($scan->status === 'processing')
                                                                                                                                                                                                                                                                    <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                                                                                                                                                                                                                                                @else
                                                                                                                                                                                                                                                                    <i data-lucide="file" class="w-3 h-3 mr-1"></i>
                                                                                                                                                                                                                                                                @endif
                                                                                                                                                                                                                                                                {{ ucfirst($scan->status ?? 'pending') }}
                                                                                                                                                                                                                                                            </span>
                                                                                                                                                                                                                                                        </div>
                                                                                                                                                                                                                                                    </div>

                                                                                                                                                                                                                                                    <!-- Document Info -->
                                                                                                                                                                                                                                                    <div class="p-3">
                                                                                                                                                                                                                                                        <div class="mb-2">
                                                                                                                                                                                                                                                            <h5 class="text-sm font-semibold text-gray-900 truncate" title="{{ $scan->document_type ?? 'Document' }}">
                                                                                                                                                                                                                                                                {{ $scan->document_type ?? 'Document' }}
                                                                                                                                                                                                                                                            </h5>
                                                                                                                                                                                                                                                            <p class="text-xs text-gray-500 truncate" title="{{ $scan->original_filename ?? 'N/A' }}">
                                                                                                                                                                                                                                                                {{ $scan->original_filename ?? 'N/A' }}
                                                                                                                                                                                                                                                            </p>
                                                                                                                                                                                                                                                        </div>

                                                                                                                                                                                                                                                        <div class="space-y-1 text-xs text-gray-600">
                                                                                                                                                                                                                                                            <div class="flex justify-between">
                                                                                                                                                                                                                                                                <span class="font-medium">Size:</span>
                                                                                                                                                                                                                                                                <span>{{ $scan->paper_size ?? 'N/A' }}</span>
                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                            <div class="flex justify-between">
                                                                                                                                                                                                                                                                <span class="font-medium">By:</span>
                                                                                                                                                                                                                                                                <span class="truncate ml-1" title="{{ $scan->uploaded_by ?? 'N/A' }}">{{ $scan->uploaded_by ?? 'N/A' }}</span>
                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                            <div class="flex justify-between">
                                                                                                                                                                                                                                                                <span class="font-medium">Date:</span>
                                                                                                                                                                                                                                                                <span>{{ $scan->created_at ? \Carbon\Carbon::parse($scan->created_at)->format('M d, Y') : 'N/A' }}</span>
                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                        </div>

                                                                                                                                                                                                                                                        @if($scan->notes)
                                                                                                                                                                                                                                                            <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs">
                                                                                                                                                                                                                                                                <p class="text-yellow-800"><span class="font-medium">Note:</span> {{ Str::limit($scan->notes, 50) }}</p>
                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                        @endif
                                                                                                                                                                                                                                                    </div>

                                                                                                                                                                                                                                                    <!-- Action Buttons -->
                                                                                                                                                                                                                                                    @if($scan->document_path)
                                                                                                                                                                                                                                                        <div class="px-3 pb-3 flex gap-2">
                                                                                                                                                                                                                                                            <button onclick="event.stopPropagation(); previewScannedDocument('{{ asset('storage/' . $scan->document_path) }}', '{{ $scan->original_filename ?? 'Document' }}')"
                                                                                                                                                                                                                                                                    class="flex-1 text-xs px-2 py-1.5 bg-blue-50 text-blue-600 rounded-md flex items-center justify-center hover:bg-blue-100 transition-colors">
                                                                                                                                                                                                                                                                <i data-lucide="eye" class="w-3 h-3 mr-1"></i>
                                                                                                                                                                                                                                                                View
                                                                                                                                                                                                                                                            </button>
                                                                                                                                                                                                                                                            <a href="{{ asset('storage/' . $scan->document_path) }}" download
                                                                                                                                                                                                                                                                onclick="event.stopPropagation()"
                                                                                                                                                                                                                                                                class="flex-1 text-xs px-2 py-1.5 bg-gray-50 text-gray-600 rounded-md flex items-center justify-center hover:bg-gray-100 transition-colors">
                                                                                                                                                                                                                                                                <i data-lucide="download" class="w-3 h-3 mr-1"></i>
                                                                                                                                                                                                                                                                Download
                                                                                                                                                                                                                                                            </a>
                                                                                                                                                                                                                                                        </div>
                                                                                                                                                                                                                                                    @endif
                                                                                                                                                                                                                                                </div>
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                                                    <i data-lucide="scan" class="w-12 h-12 text-gray-400 mx-auto mb-2"></i>
                                                                    <p class="text-sm text-gray-600">No scanned documents found</p>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <!-- Page Typings Section -->
                                                        <div class="mb-6">
                                                            <div class="flex items-center mb-3">
                                                                <i data-lucide="type" class="w-5 h-5 text-purple-600 mr-2"></i>
                                                                <h4 class="text-sm font-medium text-gray-800">Page Typings</h4>
                                                                <span class="ml-2 bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">{{ $pageTypings->count() }} pages</span>
                                                            </div>

                                                            @if($pageTypings->count() > 0)
                                                                <div class="space-y-3">
                                                                    @foreach($pageTypings as $page)
                                                                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                                                                            <div class="flex items-start justify-between mb-2">
                                                                                <div class="flex items-center">
                                                                                    <i data-lucide="file-text" class="w-4 h-4 text-purple-500 mr-2"></i>
                                                                                    <span class="text-sm font-medium text-gray-900">{{ $page->page_type ?? 'Page' }}</span>
                                                                                    @if($page->page_subtype)
                                                                                        <span class="ml-2 text-xs text-gray-500">({{ $page->page_subtype }})</span>
                                                                                    @endif
                                                                                </div>
                                                                                <span class="text-xs text-gray-500">{{ $page->created_at ? \Carbon\Carbon::parse($page->created_at)->format('M d, Y H:i') : 'N/A' }}</span>
                                                                            </div>

                                                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-gray-600">
                                                                                <div>
                                                                                    <span class="font-medium">Serial Number:</span> {{ $page->serial_number ?? 'N/A' }}
                                                                                </div>
                                                                                <div>
                                                                                    <span class="font-medium">Page Code:</span> {{ $page->page_code ?? 'N/A' }}
                                                                                </div>
                                                                                <div>
                                                                                    <span class="font-medium">Typed By:</span> {{ $page->typed_by ?? 'N/A' }}
                                                                                </div>
                                                                            </div>

                                                                            @if($page->file_path)
                                                                                <div class="mt-3">
                                                                                    <a href="{{ asset('storage/' . $page->file_path) }}" target="_blank"
                                                                                        class="text-xs px-2 py-1 bg-purple-50 text-purple-600 rounded-md flex items-center hover:bg-purple-100 w-fit">
                                                                                        <i data-lucide="external-link" class="w-3 h-3 mr-1"></i>
                                                                                        View Typed Page
                                                                                    </a>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                                                    <i data-lucide="type" class="w-12 h-12 text-gray-400 mx-auto mb-2"></i>
                                                                    <p class="text-sm text-gray-600">No page typings found</p>
                                                                </div>
                                                            @endif
                                                        </div>

                                                    @else
                                                        <!-- No EDMS Data Found -->
                                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                                                            <i data-lucide="folder-x" class="w-12 h-12 text-yellow-600 mx-auto mb-3"></i>
                                                            <h4 class="text-sm font-medium text-yellow-800 mb-2">No EDMS Record Found</h4>
                                                            <p class="text-xs text-yellow-700 mb-4">This unit application has not been processed through the Electronic Document Management System yet.</p>

                                                            <div class="bg-white rounded-lg p-4 mb-4">
                                                                <h5 class="text-sm font-medium text-gray-800 mb-2">Unit Application Information</h5>
                                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                                                    <div>
                                                                        <span class="font-medium text-gray-600">File Number:</span>
                                                                        <span class="text-gray-900">{{ $application->fileno ?? 'N/A' }}</span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-600">Land Use:</span>
                                                                        <span class="text-gray-900">{{ $application->land_use ?? 'N/A' }}</span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-600">Unit Number:</span>
                                                                        <span class="text-gray-900">{{ $application->unit_number ?? 'N/A' }}</span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="font-medium text-gray-600">Status:</span>
                                                                        <span class="text-gray-900">{{ $application->application_status ?? 'N/A' }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <hr class="my-4">

                                                    <div class="flex justify-between items-center">
                                                        <div class="flex gap-2">
                                                            <button type="button" onclick="window.history.back();" class="flex items-center px-3 py-1 text-xs bg-white text-black p-2 border border-gray-500 rounded-md hover:bg-gray-800">
                                                                <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                Back
                                                            </button>
                                                            {{-- @if($edmsData)
                                                                @if($edmsData->subapplication_id)
                                                                    <a href="{{ route('edms.sub', $edmsData->main_application_id) }}" 
                                                                        class="inline-flex items-center px-3 py-1 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                                                        <i data-lucide="external-link" class="w-3 h-3 mr-1"></i>
                                                                        View Unit EDMS
                                                                    </a>
                                                                @else
                                                                    <a href="{{ route('edms.index', ['applicationId' => $application->main_application_id ?? $application->id]) }}" 
                                                                        class="inline-flex items-center px-3 py-1 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                                                        <i data-lucide="external-link" class="w-3 h-3 mr-1"></i>
                                                                        View Full EDMS
                                                                    </a>
                                                                @endif
                                                            @endif --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="initial-tab" class="tab-content">
                                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                                <div class="p-4 border-b bg-gradient-to-r from-purple-50 to-indigo-50">
                                                    <div class="flex items-center">
                                                        <i data-lucide="banknote" class="w-5 h-5 text-purple-600 mr-2"></i>
                                                        <div>
                                                            <h3 class="text-sm font-medium text-gray-900">Director's Approval</h3>
                                                            <p class="text-xs text-gray-500">Review and approve/decline application</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <form id="directorApprovalForm">
                                                    <input type="hidden" name="application_id" id="directorApprovalApplicationId" value="{{ $application->id }}">
                                                    <!-- CSRF token for Laravel -->
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <div class="p-4 space-y-4">
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700">Decision</label>
                                                            @php
                                                                $isApproved = strtolower($application->application_status ?? '') === 'approved';
                                                                $isSua_local = ($application->is_sua_unit ?? 0) == 1 || (strtoupper($application->unit_type ?? '') === 'SUA');
                                                                $isPlanningApproved = (strtolower($application->planning_recommendation_status ?? '') === 'approved') ||
                                                                    (!$isSua_local && strtolower($application->mother_planning_status ?? '') === 'approved');
                                                                $currentStatus = strtolower($application->application_status ?? 'pending');
                                                            @endphp
                                                            <div class="mt-2 flex items-center space-x-4">
                                                                <label class="inline-flex items-center {{ $isApproved ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                                    <input type="radio" name="decision" value="approve" class="form-radio"
                                                                        {{ $isApproved ? 'disabled' : '' }}
                                                                        {{ $currentStatus === 'approve' || $currentStatus === 'approved' ? 'checked' : '' }}>
                                                                    <span class="ml-2">Approve</span>
                                                                </label>
                                                                <label class="inline-flex items-center {{ $isApproved ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                                    <input type="radio" name="decision" value="decline" class="form-radio"
                                                                        {{ $isApproved ? 'disabled' : '' }}
                                                                        {{ $currentStatus === 'decline' || $currentStatus === 'declined' ? 'checked' : '' }}>
                                                                    <span class="ml-2">Decline</span>
                                                                </label>
                                                                <span class="ml-4 text-xs text-gray-600">
                                                                    Current Status: <span class="font-semibold">{{ ucfirst($currentStatus) }}</span>
                                                                </span>
                                                            </div>
                                                            @if($isApproved)
                                                                <span class="text-xs text-gray-400 ml-2">Already approved. Decision cannot be changed.</span>
                                                            @endif
                                                        </div>
                                                        @if(!$isPlanningApproved)
                                                            <div class="mb-3 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                                                                Approval action is disabled until Planning Recommendation is approved. You can review details here.
                                                            </div>
                                                        @endif
                                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                                            <div class="col-span-2">
                                                                <label for="approval_date" class="block text-gray-700 mb-2">Approval/Decline Date</label>
                                                                <div class="flex items-center space-x-2">
                                                                    <input id="approval_date"
                                                                        name="approval_date" type="datetime-local"  
                                                                        value="{{ old('approval_date') ?? now()->format('Y-m-d\TH:i') }}"
                                                                        class="w-full p-2 border border-gray-300 rounded-md text-sm"
                                                                        max="{{ now()->format('Y-m-d\TH:i') }}"
                                                                    >
                                                                    <button type="button" onclick="document.getElementById('approval_date').value = '{{ now()->format('Y-m-d\TH:i') }}';"
                                                                        class="px-2 py-1 text-xs bg-gray-200 rounded hover:bg-gray-300">
                                                                        Use Current Date/Time
                                                                    </button>
                                                                </div>
                                                                <span class="text-xs text-gray-500">You cannot select a future date.</span>
                                                            </div>
                                                        </div>
                                                        <div id="reasonForDeclineContainer" class="mb-4 hidden">
                                                            <label for="reasonForDecline" class="block text-sm font-medium text-gray-700">Reason For Decline</label>
                                                            <textarea id="reasonForDecline" name="comments" rows="3"
                                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50"></textarea>
                                                        </div>

                                                        <hr class="my-4">
                                                        <div class="flex justify-between items-center">
                                                            <div class="flex gap-2">
                                                                <button type="button" onclick="window.history.back();"
                                                                    class="flex items-center px-3 py-1 text-xs bg-white text-black p-2 border border-gray-500 rounded-md hover:bg-gray-800">
                                                                    <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                    Back
                                                                </button>
                                                                <button 
                                                                    type="submit" 
                                                                    class="flex items-center px-3 py-1 text-xs rounded-md 
                                                                    {{ ($isApproved || !$isPlanningApproved) ? 'bg-gray-300 text-gray-500 cursor-not-allowed opacity-60' : 'bg-green-700 text-white hover:bg-gray-800' }}"
                                                                    {{ ($isApproved || !$isPlanningApproved) ? 'disabled' : '' }}>
                                                                    <i data-lucide="send-horizontal" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                    Submit
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        @endif

            <!-- Footer -->
            @include('admin.footer')
        </div>

        <script>
            // Safe icon initializer to avoid breaking page if lucide is not loaded
            function safeCreateIcons() {
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    try { window.lucide.createIcons(); } catch (e) {}
                }
            }

            // Initialize Lucide icons safely
            safeCreateIcons();

            // Tab switching functionality and decision radio handling
            document.addEventListener('DOMContentLoaded', function() {
                // Tab functionality
                const tabButtons = document.querySelectorAll('.tab-button');
                const tabContents = document.querySelectorAll('.tab-content');

                tabButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const tabId = this.getAttribute('data-tab');

                        // Deactivate all tabs
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        tabContents.forEach(content => content.classList.remove('active'));

                        // Activate selected tab
                        this.classList.add('active');
                        const targetTab = document.getElementById(`${tabId}-tab`);
                        if (targetTab) {
                            targetTab.classList.add('active');
                        }
                    });
                });

                // Decision radio button handling
                const decisionRadios = document.querySelectorAll('input[name="decision"]');
                const reasonForDeclineContainer = document.getElementById('reasonForDeclineContainer');

                if (decisionRadios.length > 0 && reasonForDeclineContainer) {
                    decisionRadios.forEach(radio => {
                        radio.addEventListener('change', function() {
                            if (this.value === 'decline') {
                                reasonForDeclineContainer.classList.remove('hidden');
                            } else {
                                reasonForDeclineContainer.classList.add('hidden');
                            }
                        });
                    });

                    // Initialize visibility based on current selection
                    const checkedDecision = document.querySelector('input[name="decision"]:checked');
                    if (checkedDecision && checkedDecision.value === 'decline') {
                        reasonForDeclineContainer.classList.remove('hidden');
                    }
                }
            });

            // Enhanced document preview function
            function previewDocument(fileUrl, documentTitle) {
                // Create modal overlay
                const previewModal = document.createElement('div');
                previewModal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
                previewModal.style.backdropFilter = 'blur(5px)';

                // Create content container
                const contentContainer = document.createElement('div');
                contentContainer.className = 'bg-white rounded-lg w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 max-h-[90vh] flex flex-col';

                // Modal header
                const modalHeader = document.createElement('div');
                modalHeader.className = 'flex justify-between items-center p-4 border-b';

                const modalTitle = document.createElement('h3');
                modalTitle.className = 'text-lg font-medium';
                modalTitle.textContent = documentTitle || 'Document Preview';

                const closeButton = document.createElement('button');
                closeButton.className = 'p-1 rounded-full hover:bg-gray-100 transition-colors';
                closeButton.innerHTML = '<i data-lucide="x" class="w-5 h-5"></i>';
                closeButton.onclick = () => previewModal.remove();

                modalHeader.appendChild(modalTitle);
                modalHeader.appendChild(closeButton);

                // Modal body - Document viewer
                const modalBody = document.createElement('div');
                modalBody.className = 'flex-1 overflow-hidden relative';

                const documentViewer = document.createElement('div');
                documentViewer.className = 'w-full h-full flex items-center justify-center bg-gray-100 overflow-auto';
                documentViewer.style.minHeight = '50vh';

                // Check file type
                const fileExtension = fileUrl.split('.').pop().toLowerCase();
                const isPdf = fileExtension === 'pdf';

                if (isPdf) {
                    const embedElement = document.createElement('embed');
                    embedElement.src = fileUrl;
                    embedElement.type = 'application/pdf';
                    embedElement.className = 'w-full h-full';
                    documentViewer.appendChild(embedElement);
                } else {
                    // Assume it's an image
                    const imageElement = document.createElement('img');
                    imageElement.src = fileUrl;
                    imageElement.className = 'max-w-full max-h-full object-contain';
                    imageElement.style.maxHeight = '70vh';
                    documentViewer.appendChild(imageElement);
                }

                modalBody.appendChild(documentViewer);

                // Modal footer
                const modalFooter = document.createElement('div');
                modalFooter.className = 'p-4 border-t flex justify-between';

                const downloadButton = document.createElement('a');
                downloadButton.href = fileUrl;
                downloadButton.download = documentTitle || 'document';
                downloadButton.className = 'px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center';
                downloadButton.innerHTML = '<i data-lucide="download" class="w-4 h-4 mr-2"></i> Download';

                const closeTextButton = document.createElement('button');
                closeTextButton.className = 'px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-100 flex items-center';
                closeTextButton.innerHTML = '<i data-lucide="x" class="w-4 h-4 mr-2"></i> Close';
                closeTextButton.onclick = () => previewModal.remove();

                modalFooter.appendChild(downloadButton);
                modalFooter.appendChild(closeTextButton);

                // Assemble modal
                contentContainer.appendChild(modalHeader);
                contentContainer.appendChild(modalBody);
                contentContainer.appendChild(modalFooter);
                previewModal.appendChild(contentContainer);

                // Add to document and initialize Lucide icons
                document.body.appendChild(previewModal);
                safeCreateIcons();

                // Add keyboard event to close on Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && document.body.contains(previewModal)) {
                        previewModal.remove();
                    }
                });
            }

            // Add form submission via AJAX
            const form = document.getElementById('directorApprovalForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Use the hidden input inside the form (avoids duplicate id issues)
                    const applicationId = document.getElementById('directorApprovalApplicationId').value;
                    const decision = document.querySelector('input[name="decision"]:checked').value;
                    const approvalDate = document.getElementById('approval_date').value;
                    let comments = '';

                    if (decision === 'decline') {
                        comments = document.getElementById('reasonForDecline').value;
                    }

                    // Show preloader with SweetAlert
                    Swal.fire({
                        title: 'Processing...',
                        html: 'Submitting director\'s approval',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // AJAX request
                    fetch('{{ route('sub-actions.update-director-approval') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            application_id: applicationId,
                            status: decision === 'approve' ? 'Approved' : 'Declined',
                            approval_date: approvalDate,
                            comments: comments
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Director\'s approval updated successfully!'
                            }).then(() => {
                                // Simply reload the page
                                location.reload();
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to update approval'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while updating director\'s approval.'
                        });
                    });
                });
            }

            // Function to preview scanned documents
            function previewScannedDocument(fileUrl, documentTitle) {
                previewDocument(fileUrl, documentTitle);
            }
        </script>
@endsection





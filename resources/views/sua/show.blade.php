@extends('layouts.app')

@section('page-title')
    {{ __('SUA Details - ') . ($application->fileno ?? 'N/A') }}
@endsection

@include('sectionaltitling.partials.assets.css')

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    
    <div class="p-6">
        <div class="bg-white rounded-lg shadow-md border border-gray-200">
            <div class="w-full max-w-7xl mx-auto">
                <!-- Main Section -->
                <div class="bg-white rounded-lg overflow-hidden">
                    <!-- Header -->
                    <div class="bg-blue-600 text-white p-6 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold">SUA FileNo: {{ $application->fileno ?? 'N/A' }}</h1>
                                <p class="text-blue-100 text-sm mt-1">Standalone Unit Application Details</p>
                                @if($application->np_fileno && $application->np_fileno !== $application->fileno)
                                    <p class="text-blue-200 text-sm">NP FileNo: {{ $application->np_fileno }}</p>
                                @endif
                            </div>
                            {{-- <div class="flex items-center space-x-3">
                                <span class="bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-medium">
                                    <i data-lucide="building" class="w-4 h-4 inline mr-1"></i>SUA
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    {{ $application->application_status === 'Approved' ? 'bg-green-100 text-green-800' : 
                                       ($application->application_status === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ $application->application_status ?? 'Pending' }}
                                </span>
                            </div> --}}
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-8">
                        <!-- SUA Information Notice -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5 mr-3"></i>
                                <div>
                                    <h3 class="text-blue-800 font-medium mb-1">Standalone Unit Application</h3>
                                    <p class="text-blue-700 text-sm">
                                        This is an independent unit application that does not require a mother application. 
                                        It follows a simplified workflow for standalone property development.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Application Information -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="file-text" class="w-5 h-5 mr-2 text-gray-600"></i>
                                    Application Information
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Scheme Number</label>
                                        <p class="text-gray-900 font-medium">{{ $application->scheme_no ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Land Use</label>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium 
                                            {{ $application->land_use === 'Residential' ? 'bg-green-100 text-green-800' : 
                                               ($application->land_use === 'Commercial' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ $application->land_use ?? 'N/A' }}
                                        </span>
                                    </div>
                                    {{-- <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Unit Type</label>
                                        <p class="text-gray-900">{{ $application->unit_type ?? 'N/A' }}</p>
                                    </div> --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Unit Number</label>
                                        <p class="text-gray-900">{{ $application->unit_number ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Unit Size</label>
                                        <p class="text-gray-900">{{ $application->unit_size ? number_format($application->unit_size, 2) . ' sqm' : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Property Location</label>
                                        <p class="text-gray-900">{{ $application->property_location ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Allocation Information -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="map-pin" class="w-5 h-5 mr-2 text-gray-600"></i>
                                    Original Owner
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Allocation Source</label>
                                        <p class="text-gray-900 font-medium">{{ $application->allocation_source ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Allocation Entity</label>
                                        <p class="text-gray-900">{{ $application->allocation_entity ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Applicant Information -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="user" class="w-5 h-5 mr-2 text-gray-600"></i>
                                    Applicant Information
                                </h3>
                            </div>
                            <div class="p-6">
                                @if($application->applicant_type === 'individual')
                                    <!-- Individual Applicant -->
                                    <div class="flex items-start space-x-6">
                                        <div class="flex-shrink-0">
                                            @if($application->passport)
                                                <img src="{{ asset('storage/app/public/passports/' . $application->passport) }}" 
                                                     alt="Applicant Photo" 
                                                     class="w-24 h-24 object-cover rounded-lg border border-gray-200">
                                            @else
                                                <div class="w-24 h-24 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center">
                                                    <i data-lucide="user" class="w-8 h-8 text-gray-400"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-600 mb-1">Full Name</label>
                                                    <p class="text-gray-900 font-medium">
                                                        {{ trim(($application->applicant_title ?? '') . ' ' . ($application->first_name ?? '') . ' ' . ($application->middle_name ?? '') . ' ' . ($application->surname ?? '')) }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
                                                    <p class="text-gray-900">{{ $application->email ?? 'N/A' }}</p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-600 mb-1">Phone Number</label>
                                                    <p class="text-gray-900">{{ $application->phone_number ?? 'N/A' }}</p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-600 mb-1">Address</label>
                                                    <p class="text-gray-900">{{ $application->address ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($application->applicant_type === 'corporate')
                                    <!-- Corporate Applicant -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-1">Corporate Name</label>
                                            <p class="text-gray-900 font-medium">{{ $application->corporate_name ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-1">RC Number</label>
                                            <p class="text-gray-900">{{ $application->rc_number ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
                                            <p class="text-gray-900">{{ $application->email ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-1">Phone Number</label>
                                            <p class="text-gray-900">{{ $application->phone_number ?? 'N/A' }}</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-600 mb-1">Address</label>
                                            <p class="text-gray-900">{{ $application->address ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                @elseif($application->applicant_type === 'multiple')
                                    <!-- Multiple Applicants -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-3">Multiple Owners</label>
                                        @php
                                            $names = !empty($application->multiple_owners_names) ? json_decode($application->multiple_owners_names, true) : [];
                                            if (json_last_error() !== JSON_ERROR_NONE) {
                                                $names = array_map('trim', explode(',', $application->multiple_owners_names ?? ''));
                                            }
                                        @endphp
                                        @if(!empty($names))
                                            <div class="space-y-2">
                                                @foreach($names as $name)
                                                    <div class="flex items-center p-2 bg-gray-50 rounded">
                                                        <i data-lucide="user" class="w-4 h-4 text-gray-400 mr-2"></i>
                                                        <span class="text-gray-900">{{ $name }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-gray-500 italic">No multiple owners listed</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Application Status and Tracking -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="activity" class="w-5 h-5 mr-2 text-gray-600"></i>
                                    Application Status 
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">ST Director's Approval</label>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                            {{ $application->application_status === 'Approved' ? 'bg-green-100 text-green-800' : 
                                               ($application->application_status === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ $application->application_status ?? 'Pending' }}
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Planning Recommendation</label>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                            {{ $application->planning_recommendation_status === 'Approved' ? 'bg-green-100 text-green-800' : 
                                               ($application->planning_recommendation_status === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ $application->planning_recommendation_status ?? 'Pending' }}
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Planning Approval Date</label>
                                        <p class="text-gray-900">{{ $application->planning_approval_date ? \Carbon\Carbon::parse($application->planning_approval_date)->format('F j, Y') : 'N/A' }}</p>
                                    </div>
                                    


 
                                     <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">ST Directors Approval Date</label>
                                        <p class="text-gray-900">{{ $application->approval_date ? \Carbon\Carbon::parse($application->approval_date)->format('F j, Y') : 'N/A' }}</p>
                                    </div>


                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Application Date</label>
                                        <p class="text-gray-900">{{ $application->created_at ? \Carbon\Carbon::parse($application->created_at)->format('F j, Y') : 'N/A' }}</p>
                                    </div>
                                </div>
                                
                                @if($application->planning_recomm_comments || $application->director_comments)
                                    <div class="mt-6 pt-6 border-t border-gray-200">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            @if($application->planning_recomm_comments)
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-600 mb-2">Planning Comments</label>
                                                    <div class="bg-gray-50 p-3 rounded-lg">
                                                        <p class="text-gray-900 text-sm">{{ $application->planning_recomm_comments }}</p>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($application->director_comments)
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-600 mb-2">Director's Comments</label>
                                                    <div class="bg-gray-50 p-3 rounded-lg">
                                                        <p class="text-gray-900 text-sm">{{ $application->director_comments }}</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- SUA Initial Payments -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="credit-card" class="w-5 h-5 mr-2 text-gray-600"></i>
                                    SUA Initial Payments
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Application Fee</label>
                                        <p class="text-gray-900 font-medium">{{ $application->application_fee ? '₦' . number_format($application->application_fee, 2) : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Processing Fee</label>
                                        <p class="text-gray-900 font-medium">{{ $application->processing_fee ? '₦' . number_format($application->processing_fee, 2) : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Site Plan Fee</label>
                                        <p class="text-gray-900 font-medium">{{ $application->site_plan_fee ? '₦' . number_format($application->site_plan_fee, 2) : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Payment Status</label>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
                                            {{ $application->Payment_Status === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $application->Payment_Status ?? 'Unpaid' }}
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Payment Date</label>
                                        <p class="text-gray-900">{{ $application->payment_date ? \Carbon\Carbon::parse($application->payment_date)->format('F j, Y') : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Receipt Number</label>
                                        <p class="text-gray-900 font-mono">{{ $application->receipt_number ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                
                                @php
                                    $totalFees = ($application->application_fee ?? 0) + ($application->processing_fee ?? 0) + ($application->site_plan_fee ?? 0);
                                @endphp
                                
                                @if($totalFees > 0)
                                    <div class="mt-6 pt-6 border-t border-gray-200">
                                        <div class="flex justify-between items-center">
                                            <span class="text-lg font-medium text-gray-900">Total Initial Payment:</span>
                                            <span class="text-xl font-bold text-gray-900">₦{{ number_format($totalFees, 2) }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Shared Areas -->
                        @if($application->shared_areas)
                            @php
                                $sharedAreas = is_string($application->shared_areas) ? json_decode($application->shared_areas, true) : $application->shared_areas;
                            @endphp
                            @if($sharedAreas && count($sharedAreas) > 0)
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i data-lucide="home" class="w-5 h-5 mr-2 text-gray-600"></i>
                                            Shared Areas
                                        </h3>
                                    </div>
                                    <div class="p-6">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            @foreach($sharedAreas as $area)
                                                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                                    @switch($area)
                                                        @case('hallways')
                                                            <i data-lucide="navigation" class="w-5 h-5 text-blue-600"></i>
                                                            <span class="text-gray-900 font-medium">Hallways</span>
                                                            @break
                                                        @case('parking_lots')
                                                            <i data-lucide="car" class="w-5 h-5 text-green-600"></i>
                                                            <span class="text-gray-900 font-medium">Parking Lots</span>
                                                            @break
                                                        @case('swimming_pool')
                                                            <i data-lucide="waves" class="w-5 h-5 text-blue-500"></i>
                                                            <span class="text-gray-900 font-medium">Swimming Pool</span>
                                                            @break
                                                        @case('rooftop')
                                                            <i data-lucide="building" class="w-5 h-5 text-gray-600"></i>
                                                            <span class="text-gray-900 font-medium">Rooftop</span>
                                                            @break
                                                        @case('storage')
                                                            <i data-lucide="package" class="w-5 h-5 text-orange-600"></i>
                                                            <span class="text-gray-900 font-medium">Storage</span>
                                                            @break
                                                        @default
                                                            <i data-lucide="square" class="w-5 h-5 text-gray-400"></i>
                                                            <span class="text-gray-900 font-medium">{{ ucfirst(str_replace('_', ' ', $area)) }}</span>
                                                    @endswitch
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <!-- Documents -->
                        @if($application->documents)
                            @php
                                $documents = is_string($application->documents) ? json_decode($application->documents, true) : $application->documents;
                            @endphp
                            @if($documents && count($documents) > 0)
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i data-lucide="file-text" class="w-5 h-5 mr-2 text-gray-600"></i>
                                            Supporting Documents
                                        </h3>
                                    </div>
                                    <div class="p-6">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            @foreach($documents as $docType => $docData)
                                                @if($docData && isset($docData['path']))
                                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                                                        <div class="flex items-center space-x-3">
                                                            @switch($docType)
                                                                @case('application_letter')
                                                                    <i data-lucide="mail" class="w-6 h-6 text-blue-600"></i>
                                                                    <div>
                                                                        <h4 class="font-medium text-gray-900">Application Letter</h4>
                                                                        <p class="text-sm text-gray-600">{{ $docData['original_name'] ?? 'Document' }}</p>
                                                                    </div>
                                                                    @break
                                                                @case('building_plan')
                                                                    <i data-lucide="building" class="w-6 h-6 text-green-600"></i>
                                                                    <div>
                                                                        <h4 class="font-medium text-gray-900">Building Plan</h4>
                                                                        <p class="text-sm text-gray-600">{{ $docData['original_name'] ?? 'Document' }}</p>
                                                                    </div>
                                                                    @break
                                                                @case('architectural_design')
                                                                    <i data-lucide="drafting-compass" class="w-6 h-6 text-purple-600"></i>
                                                                    <div>
                                                                        <h4 class="font-medium text-gray-900">Architectural Design</h4>
                                                                        <p class="text-sm text-gray-600">{{ $docData['original_name'] ?? 'Document' }}</p>
                                                                    </div>
                                                                    @break
                                                                @case('surveyor_report')
                                                                    <i data-lucide="map" class="w-6 h-6 text-orange-600"></i>
                                                                    <div>
                                                                        <h4 class="font-medium text-gray-900">Site Plan (Survey)</h4>
                                                                        <p class="text-sm text-gray-600">{{ $docData['original_name'] ?? 'Document' }}</p>
                                                                    </div>
                                                                    @break
                                                                @case('ownership_document')
                                                                    <i data-lucide="shield-check" class="w-6 h-6 text-red-600"></i>
                                                                    <div>
                                                                        <h4 class="font-medium text-gray-900">Ownership Document</h4>
                                                                        <p class="text-sm text-gray-600">{{ $docData['original_name'] ?? 'Document' }}</p>
                                                                    </div>
                                                                    @break
                                                                @case('deed_of_assignment')
                                                                    <i data-lucide="file-signature" class="w-6 h-6 text-indigo-600"></i>
                                                                    <div>
                                                                        <h4 class="font-medium text-gray-900">Deed of Assignment</h4>
                                                                        <p class="text-sm text-gray-600">{{ $docData['original_name'] ?? 'Document' }}</p>
                                                                    </div>
                                                                    @break
                                                                @default
                                                                    <i data-lucide="file" class="w-6 h-6 text-gray-600"></i>
                                                                    <div>
                                                                        <h4 class="font-medium text-gray-900">Site Plan (Survey)</h4>
                                                                        <p class="text-sm text-gray-600">{{ $docData['original_name'] ?? 'Document' }}</p>
                                                                    </div>
                                                            @endswitch
                                                        </div>
                                                        <div class="flex items-center space-x-2">
                                                            @if(isset($docData['uploaded_at']))
                                                                <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($docData['uploaded_at'])->format('M j, Y') }}</span>
                                                            @endif
                                                            <a href="{{ Storage::url($docData['path']) }}" 
                                                               target="_blank"
                                                               class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                                                <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
                                                                View
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <!-- Action Buttons -->
                        <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                            <a href="{{ route('sua.index') }}" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                                Back to SUA List
                            </a>
                            
                            <div class="flex space-x-3">
                                <a href="{{ url('/sectionaltitling/edit_sub/' . $application->id) }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                    <i data-lucide="edit" class="w-4 h-4 mr-2"></i>
                                    Edit Application
                                </a>
                                
                                <button onclick="window.print()" 
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                                    Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

@endsection
@extends('layouts.app')
@section('page-title')
{{ __('SECTIONAL TITLING  MODULE') }}
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
      font-size: 0.875rem;
      padding: 0.75rem 1.5rem;
      border-radius: 0.375rem;
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid #e5e7eb;
      background-color: #f9fafb;
      color: #6b7280;
    }
    .tab-button.active {
      background-color: #3b82f6;
      color: white;
      border-color: #3b82f6;
      font-weight: 500;
    }
    .tab-button:hover:not(.active) {
      background-color: #f3f4f6;
      border-color: #d1d5db;
    }
</style>

@include('sectionaltitling.partials.assets.css')
@section('content')
<!-- Main Content -->
<div class="flex-1 overflow-auto">
<!-- Header -->
@include('admin.header')
<!-- Dashboard Content --> 
<div class="p-6">
<!-- Primary Applications Table -->
<div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
<div class="container mx-auto mt-4 p-4">
<div class="card shadow-lg border-0">
<div class="card-header bg-gray-50 border-b border-gray-200 py-3 px-4">
<div class="flex justify-between items-center">
    <h2 class="text-xl font-bold text-gray-800">Application Details</h2>
    <div class="flex items-center gap-3">
        <!-- Status Badges -->
        <div class="flex items-center gap-2">
            <span
                class="bg-{{ $application->planning_recommendation_status == 'Approved' ? 'green' : ($application->planning_recommendation_status == 'Rejected' ? 'red' : 'yellow') }}-100 
                    text-{{ $application->planning_recommendation_status == 'Approved' ? 'green' : ($application->planning_recommendation_status == 'Rejected' ? 'red' : 'yellow') }}-800 
                    text-xs font-medium px-2.5 py-0.5 rounded-full">
                Planning Recommendation: {{ $application->planning_recommendation_status ?? 'Pending' }}
            </span>
            <span class="bg-{{ $application->application_status == 'Approved' ? 'green' : ($application->application_status == 'Rejected' ? 'red' : 'yellow') }}-100 text-{{ $application->application_status == 'Approved' ? 'green' : ($application->application_status == 'Rejected' ? 'red' : 'yellow') }}-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                ST Director's Approval: {{ $application->application_status ?? 'Pending' }}
            </span>
           
        </div>
        
        <!-- Action Buttons -->
        @php
            $isApproved = ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved');
            $isViewMode = request()->get('url') === '';
        @endphp
        
        @if(!$isViewMode)
        <div class="flex items-center gap-3">
            <!-- Edit Button -->
            <button type="button"
                onclick="editApplication()"
                class="flex items-center px-4 py-2 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ $isApproved ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700 text-white' }}"
                {{ $isApproved ? 'disabled' : '' }}
                title="{{ $isApproved ? 'Cannot edit - Both Application Status and Planning Recommendation have been approved' : 'Edit Application' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </button>
            
            <!-- Delete Button -->
            {{-- <button type="button"
                onclick="deleteApplication()"
                class="flex items-center px-4 py-2 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ $isApproved ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700 text-white' }}"
                {{ $isApproved ? 'disabled' : '' }}
                title="{{ $isApproved ? 'Cannot delete - Both Application Status and Planning Recommendation have been approved' : 'Delete Application' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Delete
            </button> --}}
        </div>
        @endif
        
        
    </div>
</div>
</div>

@php
    $allocatedUnits = 0;
    if (!empty($application->id)) {
        // Count from buyer_list where application_id matches
        // Use DISTINCT to avoid duplicate counts when joined with measurements
        $buyerListCount = DB::connection('sqlsrv')
            ->table('buyer_list as bl')
            ->leftJoin('st_unit_measurements as sum', function($join) {
                $join->on('bl.id', '=', 'sum.buyer_id')
                     ->on('bl.application_id', '=', 'sum.application_id');
            })
            ->where('bl.application_id', $application->id)
            ->distinct()
            ->count('bl.id');

        // Count from subapplications where main_application_id matches
        $subAppCount = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('main_application_id', $application->id)
            ->count();

        $allocatedUnits = $buyerListCount;
    }

    $totalUnits = $application->NoOfUnits ?? 0;
    $remainingUnits = max(0, ($totalUnits - $allocatedUnits));
    $progressPercentage = $totalUnits > 0 ? min(100, max(0, ($allocatedUnits / $totalUnits) * 100)) : 0;
@endphp

<!-- Units Countdown UI -->
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
            <div class="bg-blue-500 rounded-full p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4M9 7h6m-6 4h6m-6 4h6" />
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Units Allocation Progress</h3>
                <p class="text-sm text-gray-600">Track the allocation of units for this application</p>
            </div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-blue-600">{{ $remainingUnits }}</div>
            <div class="text-sm text-gray-500">Units Remaining</div>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="mb-4">
        <div class="flex justify-between text-sm text-gray-600 mb-2">
            <span>Progress</span>
            <span>{{ number_format($progressPercentage, 0) }}% Complete</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-3 rounded-full transition-all duration-500 ease-out" 
                 style="width: {{ $progressPercentage }}%"></div>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-3 gap-4">
        <!-- Total Units Card - Purple Theme -->
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 text-center border border-purple-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="mb-2">
                <div class="inline-flex items-center justify-center w-8 h-8 bg-purple-500 rounded-full mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-purple-700">{{ $totalUnits }}</div>
            <div class="text-sm text-purple-600 font-medium">Total Units</div>
        </div>
        
        <!-- Allocated Units Card - Green Theme -->
        <div class="bg-gradient-to-br from-green-50 to-emerald-100 rounded-lg p-4 text-center border border-green-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="mb-2">
                <div class="inline-flex items-center justify-center w-8 h-8 bg-green-500 rounded-full mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-green-700">{{ $allocatedUnits }}</div>
            <div class="text-sm text-green-600 font-medium">Allocated</div>
        </div>
        
        <!-- Remaining Units Card - Dynamic Theme -->
        <div class="bg-gradient-to-br {{ $remainingUnits > 0 ? 'from-orange-50 to-amber-100 border-orange-200' : 'from-green-50 to-emerald-100 border-green-200' }} rounded-lg p-4 text-center border shadow-sm hover:shadow-md transition-shadow">
            <div class="mb-2">
                <div class="inline-flex items-center justify-center w-8 h-8 {{ $remainingUnits > 0 ? 'bg-orange-500' : 'bg-green-500' }} rounded-full mb-2">
                    @if($remainingUnits > 0)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </div>
            </div>
            <div class="text-2xl font-bold {{ $remainingUnits > 0 ? 'text-orange-700' : 'text-green-700' }}">{{ $remainingUnits }}</div>
            <div class="text-sm {{ $remainingUnits > 0 ? 'text-orange-600' : 'text-green-600' }} font-medium">Remaining</div>
        </div>
    </div>
    
    @if($remainingUnits <= 0)
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span class="font-medium">All units have been allocated!</span>
        </div>
    @elseif($remainingUnits <= 3)
        <div class="mt-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span class="font-medium">Only {{ $remainingUnits }} units remaining!</span>
        </div>
    @endif
</div>

<!-- Tabs Navigation -->
<div class="border-b border-gray-200 bg-white px-4 py-3">
    <div class="flex space-x-2">
        <button class="tab-button active" data-tab="details">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            Record Details
        </button>
        <button class="tab-button" data-tab="documents">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            View Documents
        </button>
        <button class="tab-button" data-tab="buyers">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            View Buyer List
        </button>
    </div>
</div>

<div class="card-body p-0">

@if(session('success'))
    <div class="mx-6 mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="mx-6 mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
        {{ session('error') }}
    </div>
@endif

<!-- Record Details Tab -->
<div id="details-tab" class="tab-content active">
<div class="bg-white p-6">
    <!-- File Info and Status -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">NP File Number:</strong>
                    @if($application->np_fileno)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            {{ $application->np_fileno }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-500 border border-gray-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414A1 1 0 009 21H7a2 2 0 01-2-2v-5m16 0H4" />
                            </svg>
                            N/A
                        </span>
                    @endif
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">File Number:</strong>
                    @if($application->fileno)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                            </svg>
                            {{ $application->fileno }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-500 border border-gray-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414A1 1 0 009 21H7a2 2 0 01-2-2v-5m16 0H4" />
                            </svg>
                            N/A
                        </span>
                    @endif
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Application
                        Type:</strong>
                    <span
                        class="text-gray-900 text-lg">{{ ucfirst($application->applicant_type ?? 'N/A') }}</span>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Application
                        Date:</strong>
                    <span
                        class="text-gray-900">{{ $application->sys_date ? date('d M Y', strtotime($application->sys_date)) : 'N/A' }}</span>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Approval Date:</strong>
                    <span
                        class="text-gray-900">{{ $application->approval_date ? date('d M Y', strtotime($application->approval_date)) : 'Pending' }}</span>
                </div>
            </div>
        </div>
        <div class="flex justify-center items-center">
            <!-- Passport Photo Section -->
            <div class="text-center">
                <div
                    class="mb-2 border border-gray-300 rounded-lg overflow-hidden inline-block">
                    @if (isset($application->passport) && !empty($application->passport))
                        <img src="{{ asset('storage/' . ltrim($application->passport, '/')) }}"
                            alt="Applicant Photo" class="w-36 h-36 object-cover">
                    @else
                        <div class="w-36 h-36 bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-500 text-sm">No Photo Available</span>
                        </div>
                    @endif
                </div>
                <p class="text-sm text-gray-600">Applicant Photo</p>
            </div>
        </div>
    </div>

    <!-- Applicant Information -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">Applicant Information
        </h3>

        @if ($application->applicant_type == 'individual')
            <!-- Individual Applicant(s) -->
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <strong class="block font-medium text-gray-700 mb-2">Primary Applicant:</strong>
                <div class="flex items-center">
                    <div class="mr-4">
                        @if (isset($application->passport) && !empty($application->passport))
                            <img src="{{ asset('storage/' . ltrim($application->passport, '/')) }}"
                                alt="Primary Applicant"
                                class="w-16 h-16 object-cover rounded-full border border-gray-300">
                        @else
                            <div
                                class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                                <span class="text-gray-500 text-xs">No Photo</span>
                            </div>
                        @endif
                    </div>
                    <div>
                        <p class="text-gray-900 font-medium uppercase">
                            {{ strtoupper($application->applicant_title ?? '') }}
                            {{ strtoupper($application->first_name ?? '') }}
                            {{ strtoupper($application->middle_name ?? '') }}
                            {{ strtoupper($application->surname ?? '') }}
                        </p>
                        <p class="text-gray-600 text-sm uppercase">{{ strtoupper($application->email ?? 'N/A') }}</p>
                    </div>
                </div>
            </div>

            <!-- Co-Applicants (if any) -->
            @if (isset($application->co_applicants) && !empty($application->co_applicants))
                <strong class="block font-medium text-gray-700 mb-2">Co-Applicants:</strong>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($application->co_applicants as $co_applicant)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <div class="mr-4">
                                    @if (isset($co_applicant->passport_photo) && !empty($co_applicant->passport_photo))
                                        <img src="{{ asset('storage/' . ltrim($co_applicant->passport_photo, '/')) }}"
                                            alt="Co-Applicant"
                                            class="w-12 h-12 object-cover rounded-full border border-gray-300">
                                    @else
                                        <div
                                            class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
                                            <span class="text-gray-500 text-xs">No Photo</span>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-gray-900">{{ $co_applicant->title ?? '' }}
                                        {{ $co_applicant->name ?? 'N/A' }}</p>
                                    <p class="text-gray-600 text-sm">
                                        {{ $co_applicant->email ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @elseif($application->applicant_type == 'corporate')
            <!-- Corporate Applicant -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Corporate
                        Name:</strong>
                    <span
                        class="text-gray-900">{{ $application->corporate_name ?? 'N/A' }}</span>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">RC Number:</strong>
                    <span class="text-gray-900">{{ $application->rc_number ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Corporate Representatives (if any) -->
            @if (isset($application->representatives) && !empty($application->representatives))
                <div class="mt-4">
                    <strong class="block font-medium text-gray-700 mb-2">Corporate
                        Representatives:</strong>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($application->representatives as $rep)
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="mr-4">
                                        @if (isset($rep->passport_photo) && !empty($rep->passport_photo))
                                            <img src="{{ asset('storage/' . ltrim($rep->passport_photo, '/')) }}"
                                                alt="Representative"
                                                class="w-12 h-12 object-cover rounded-full border border-gray-300">
                                        @else
                                            <div
                                                class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
                                                <span class="text-gray-500 text-xs">No
                                                    Photo</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-gray-900">{{ $rep->name ?? 'N/A' }}</p>
                                        <p class="text-gray-600 text-sm">
                                            {{ $rep->position ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        <div class="mt-4 bg-gray-50 p-4 rounded-lg">
            <strong class="block font-medium text-gray-700 mb-1">Contact Information:</strong>
            <div class="text-gray-900">
                <div>
                    @php
                        $addressParts = [];
                        if (!empty($application->address_house_no)) $addressParts[] = strtoupper($application->address_house_no);
                        if (!empty($application->address_street_name)) $addressParts[] = strtoupper($application->address_street_name);
                        if (!empty($application->address_district)) $addressParts[] = strtoupper($application->address_district);
                        if (!empty($application->address_lga)) $addressParts[] = strtoupper($application->address_lga);
                        if (!empty($application->address_state)) $addressParts[] = strtoupper($application->address_state);
                        $fullAddress = implode(', ', $addressParts);
                    @endphp
                    {{ $fullAddress ?: 'N/A' }}
                </div>
                <div>
                    Phone:
                    @if (isset($application->phone_number))
                        @php
                            $phoneNumbers = explode(',', $application->phone_number);
                        @endphp
                        @if (count($phoneNumbers) > 1)
                            @foreach ($phoneNumbers as $phoneNumber)
                                {{ trim($phoneNumber) }}@if (!$loop->last)
                                    ,
                                @endif
                            @endforeach
                        @else
                            {{ $application->phone_number ?? 'N/A' }}
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div>Email: {{ $application->email ?? 'N/A' }}</div>
            </div>
        </div>

        <!-- Multiple Owners Section -->
        @if (isset($application->multiple_owners_names) && !empty($application->multiple_owners_names))
            <div class="mt-4">
                <strong class="block font-medium text-gray-700 mb-2">Multiple Owners:</strong>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @php
                        $ownerNames = is_array($application->multiple_owners_names)
                            ? $application->multiple_owners_names
                            : json_decode($application->multiple_owners_names, true);

                        $ownerPassports = is_array($application->multiple_owners_passport)
                            ? $application->multiple_owners_passport
                            : json_decode($application->multiple_owners_passport, true);
                    @endphp

                    @foreach ($ownerNames as $key => $ownerName)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <div class="mr-4">
                                    @if (isset($ownerPassports[$key]) && !empty($ownerPassports[$key]))
                                        <img src="{{ asset('storage/' . ltrim($ownerPassports[$key], '/')) }}"
                                            alt="Owner Photo"
                                            class="w-16 h-16 object-cover rounded-full border border-gray-300">
                                    @else
                                        <div
                                            class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                                            <span class="text-gray-500 text-xs">No Photo</span>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-gray-900 font-medium">{{ $ownerName }}
                                    </p>
                                    <p class="text-gray-600 text-sm">Owner {{ $key + 1 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Property Information -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">Property Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg">
                <strong class="block font-medium text-gray-700 mb-1">Land Use:</strong>
                <span
                    class="text-gray-900">{{ ucfirst($application->land_use ?? 'N/A') }}</span>
            </div>

            @if (!empty($application->residential_type))
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Residential
                        Type:</strong>
                    <span
                        class="text-gray-900">{{ ucfirst($application->residential_type) }}</span>
                </div>
            @endif

            @if (!empty($application->industrial_type))
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Industrial
                        Type:</strong>
                    <span
                        class="text-gray-900">{{ ucfirst($application->industrial_type) }}</span>
                </div>
            @endif

            @if (!empty($application->commercial_type))
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Commercial
                        Type:</strong>
                    <span
                        class="text-gray-900">{{ ucfirst($application->commercial_type) }}</span>
                </div>
            @endif

            <div class="bg-gray-50 p-4 rounded-lg">
                <strong class="block font-medium text-gray-700 mb-1">Plot Size:</strong>
                <span class="text-gray-900">{{ $application->plot_size ?? 'N/A' }} sqm</span>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <strong class="block font-medium text-gray-700 mb-1">Number of Units:</strong>
                <span class="text-gray-900">{{ $application->NoOfUnits ?? 'N/A' }}</span>
            </div>
                    </div>

        <div class="mt-4 bg-gray-50 p-4 rounded-lg">
            <strong class="block font-medium text-gray-700 mb-1">Property Location:</strong>
            <span class="block text-gray-900">
            Plot No: 
            {{ strtoupper($application->property_plot_no ?? '') }}
            </span>
            <span class="block text-gray-900">
            Street Name: {{ strtoupper($application->property_street_name ?? '') }}
            </span>
            <span class="block text-gray-900">
            District: {{ strtoupper($application->property_district ?? '') }}
            </span>
            <span class="block text-gray-900">
            LGA: {{ strtoupper($application->property_lga ?? '') }}
            </span>
            <span class="block text-gray-900">
            State: {{ strtoupper($application->property_state ?? '') }}
            </span>
        </div>
    </div>

    <!-- Financial Information -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">Initial Bill</h3>
        @php
            // Sanitize possible currency strings like "10,000.00" into floats
            $toFloat = function ($value) {
                if (is_null($value)) return 0.0;
                if (is_numeric($value)) return (float)$value;
                return (float) preg_replace('/[^\d\.\-]/', '', (string) $value);
            };
            $appFee = $toFloat($application->application_fee ?? 0);
            $procFee = $toFloat($application->processing_fee ?? 0);
            $siteFee = $toFloat($application->site_plan_fee ?? 0);
            $totalFees = $appFee + $procFee + $siteFee;
            $formatDate = function ($value) {
                if (empty($value)) {
                    return 'N/A';
                }
                try {
                    return \Carbon\Carbon::parse($value)->format('d M Y');
                } catch (\Throwable $th) {
                    return $value;
                }
            };

            $feeMeta = [
                [
                    'label' => 'Application Fee',
                    'amount' => $appFee,
                    'date' => $application->application_fee_payment_date ?? null,
                    'receipt' => $application->application_fee_receipt_number ?? null,
                ],
                [
                    'label' => 'Processing Fee',
                    'amount' => $procFee,
                    'date' => $application->processing_fee_payment_date ?? null,
                    'receipt' => $application->processing_fee_receipt_number ?? null,
                ],
                [
                    'label' => 'Site Plan Fee',
                    'amount' => $siteFee,
                    'date' => $application->site_plan_fee_payment_date ?? null,
                    'receipt' => $application->site_plan_fee_receipt_number ?? null,
                ],
            ];
        @endphp
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($feeMeta as $fee)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <strong class="block font-medium text-gray-700 mb-1">{{ $fee['label'] }}:</strong>
                        <span class="text-gray-900 block mb-2">₦{{ number_format($fee['amount'], 2) }}</span>
                        <div class="text-sm text-gray-600">
                            <div class="flex justify-between">
                                <span>Payment Date</span>
                                <span class="font-medium text-gray-800">{{ $formatDate($fee['date']) }}</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span>Receipt No.</span>
                                <span class="font-medium text-gray-800">{{ $fee['receipt'] ?: 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Legacy Receipt Number:</strong>
                    <span class="text-gray-900">{{ $application->receipt_number ?? 'N/A' }}</span>
                    <p class="text-xs text-gray-500 mt-1">Used before per-fee tracking was introduced.</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <strong class="block font-medium text-gray-700 mb-1">Legacy Payment Date:</strong>
                    <span class="text-gray-900">{{ $formatDate($application->payment_date ?? null) }}</span>
                    <p class="text-xs text-gray-500 mt-1">Displays the older single payment date if still populated.</p>
                </div>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <strong class="block font-medium text-gray-700 mb-1">Total Fees:</strong>
                <span class="text-gray-900 font-bold text-xl">₦{{ number_format($totalFees, 2) }}</span>
            </div>
        </div>
    </div>

    @if ($application->comments)
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">Comments</h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-700">
                    {{ $application->comments }}
                </p>
            </div>
        </div>
    @endif

    <div class="mt-6 flex gap-3">
        <button type="button"
            onclick="window.history.back();"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md transition duration-150 ease-in-out flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20"
            fill="currentColor">
            <path fill-rule="evenodd"
                d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z"
                clip-rule="evenodd" />
            </svg>
            Back to List
        </button>
    </div>
</div>

</div>

<!-- View Documents Tab -->
<div id="documents-tab" class="tab-content">
    <div class="bg-white p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">Application Documents</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @php
                $documents = !empty($application->documents) 
                    ? (is_string($application->documents) ? json_decode($application->documents, true) : $application->documents) 
                    : [];
                
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($documents)) {
                    $documents = [];
                }
            @endphp
            
            @foreach($documents as $key => $document)
                @if(is_array($document) && isset($document['path']))
                    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-16 h-16 bg-gray-100 rounded flex items-center justify-center">
                                @php
                                    $type = $document['type'] ?? 'unknown';
                                    $icon = 'document-text';
                                    
                                    if (in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        $icon = 'photograph';
                                    } elseif (in_array($type, ['pdf'])) {
                                        $icon = 'document';
                                    } elseif (in_array($type, ['doc', 'docx'])) {
                                        $icon = 'document-text';
                                    }
                                @endphp
                                
                                @if(in_array($type, ['jpg', 'jpeg', 'png', 'gif']))
                                    <img src="{{ asset('storage/' . ltrim($document['path'], '/')) }}" 
                                        alt="Document" 
                                        class="w-14 h-14 object-cover rounded">
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 capitalize">
                                    {{ str_replace('_', ' ', $key) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    Uploaded: {{ isset($document['uploaded_at']) ? \Carbon\Carbon::parse($document['uploaded_at'])->format('M d, Y') : 'N/A' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button onclick="viewDocument('{{ asset('storage/' . ltrim($document['path'], '/')) }}', '{{ $type }}')" 
                               class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1 rounded">
                                View Document
                            </button>
                        </div>
                    </div>
                @endif
            @endforeach
            
            @if(count($documents) == 0)
                <div class="col-span-2 text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-gray-500">No documents available</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Include Buyer List Tab Partial --}}
@include('sectionaltitling.partials.buyers-list-tab', [
    'application' => $application,
    'titles' => $titles,
    'isApproved' => ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved')
])

<!-- Document Viewer Modal -->
<div id="documentViewerModal" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden flex items-center justify-center">
    <div class="max-w-5xl w-full h-[80vh] bg-white rounded-lg shadow-xl overflow-hidden flex flex-col" style="max-height: 90vh;">
        <div class="bg-gray-100 p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold">Document Viewer</h3>
            <button onclick="closeDocumentViewer()" class="text-gray-600 hover:text-gray-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="flex-1 p-4 overflow-auto bg-gray-800 flex items-center justify-center" id="documentContent">
            <!-- Document will be loaded here -->
        </div>
    </div>
</div>

{{-- PapaParse library for CSV parsing --}}
<script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>

{{-- SweetAlert2 for alerts --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- Buyer List Management JS --}}
<script src="{{ asset('js/buyer-list-management.js') }}"></script>

<script>
    // Set global flag for application approval status
    window.isApplicationApproved = {{ ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved') ? 'true' : 'false' }};
    
    // Titles lookup data
    const titles = @json($titles ?? []);

    // Edit Application Function
    function editApplication() {
        const applicationId = {{ $application->id ?? 'null' }};
        
        Swal.fire({
            title: 'Edit Application',
            text: 'Are you sure you want to edit this application?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Edit',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect to edit page
                window.location.href = `{{ route('sectionaltitling.edit', '') }}/${applicationId}`;
            }
        });
    }
    
    // Delete Application Function
    function deleteApplication() {
        const applicationId = {{ $application->id ?? 'null' }};
        
        Swal.fire({
            title: 'Delete Application',
            text: 'Are you sure you want to delete this application? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Deleting...',
                    html: 'Please wait while we delete the application',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send delete request
                fetch(`{{ route('sectionaltitling.delete', '') }}/${applicationId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Application has been deleted successfully.',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Redirect to applications list
                            window.location.href = '{{ route("sectionaltitling.index") }}';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to delete application'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred while deleting the application.'
                    });
                });
            }
        });
    }

    // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {
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
                document.getElementById(`${tabId}-tab`).classList.add('active');
                
                // Load buyers list when buyers tab is clicked
                if (tabId === 'buyers') {
                    loadBuyersList();
                }
            });
        });
        
        // Handle form submission for adding buyers
        const buyersForm = document.getElementById('add-buyers-form');
        if (buyersForm) {
            buyersForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Client-side validation for required fields
                let valid = true;
                let errorMessages = [];
                const buyerBlocks = buyersForm.querySelectorAll('[x-for]');
                // Use new field names: firstName, surname, unit_no
                const buyerTitleInputs = buyersForm.querySelectorAll('select[name*="[buyerTitle]"]');
                const firstNameInputs = buyersForm.querySelectorAll('input[name*="[firstName]"]');
                const surnameInputs = buyersForm.querySelectorAll('input[name*="[surname]"]');
                const unitNoInputs = buyersForm.querySelectorAll('input[name*="[unit_no]"]');

                for (let i = 0; i < firstNameInputs.length; i++) {
                    const title = buyerTitleInputs[i]?.value?.trim();
                    const firstName = firstNameInputs[i]?.value?.trim();
                    const surname = surnameInputs[i]?.value?.trim();
                    const unit = unitNoInputs[i]?.value?.trim();

                    if (!title) {
                        valid = false;
                        errorMessages.push(`Buyer ${i + 1}: Title is required.`);
                    }
                    if (!firstName) {
                        valid = false;
                        errorMessages.push(`Buyer ${i + 1}: First Name is required.`);
                    }
                    if (!surname) {
                        valid = false;
                        errorMessages.push(`Buyer ${i + 1}: Surname is required.`);
                    }
                    if (!unit) {
                        valid = false;
                        errorMessages.push(`Buyer ${i + 1}: Unit No is required.`);
                    }
                }

                if (!valid) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        html: errorMessages.join('<br>'),
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Check if application is approved
                const isApproved = {{ ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved') ? 'true' : 'false' }};
                
                if (isApproved) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cannot Add Buyers',
                        text: 'Both Application Status and Planning Recommendation have been approved. No further modifications are allowed.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                // Validate application_id
                const applicationId = this.querySelector('input[name="application_id"]').value;
                if (!applicationId || applicationId.trim() === '') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'The application id field is required.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                // Validate that at least one buyer is added and required fields are filled
                const firstNames = this.querySelectorAll('input[name*="[firstName]"]');
                const surnames = this.querySelectorAll('input[name*="[surname]"]');
                const unitNos = this.querySelectorAll('input[name*="[unit_no]"]');
                
                let hasValidBuyer = false;
                let errors = [];
                
                for (let i = 0; i < firstNames.length; i++) {
                    const firstName = firstNames[i].value.trim();
                    const surname = surnames[i]?.value.trim() || '';
                    const unitNo = unitNos[i].value.trim();
                    
                    if (firstName || surname || unitNo) {
                        if (!firstName) {
                            errors.push(`Buyer ${i + 1}: First Name is required`);
                        }
                        if (!surname) {
                            errors.push(`Buyer ${i + 1}: Surname is required`);
                        }
                        if (!unitNo) {
                            errors.push(`Buyer ${i + 1}: Unit No is required`);
                        }
                        if (firstName && surname && unitNo) {
                            hasValidBuyer = true;
                        }
                    }
                }
                
                if (!hasValidBuyer) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please add at least one buyer with Title, First Name, Surname and Unit No filled.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                if (errors.length > 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Errors',
                        html: errors.join('<br>'),
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                // Show loading state
                Swal.fire({
                    title: 'Saving...',
                    html: 'Please wait while we process your request',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Get the form data
                const formData = new FormData(this);
                
                // Submit the form using fetch
                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => Promise.reject(err));
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Reset the form and refresh buyers list
                            buyersForm.reset();
                            // Restore application_id after reset
                            buyersForm.querySelector('input[name="application_id"]').value = "{{ $application->id }}";
                            // Reset Alpine.js buyers array
                            const buyersTab = document.getElementById('buyers-tab');
                            if (buyersTab && buyersTab._x_dataStack && buyersTab._x_dataStack[0]) {
                                buyersTab._x_dataStack[0].resetBuyers();
                            }
                            loadBuyersList();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to save buyers information'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Handle validation errors
                    if (error.errors) {
                        let errorMessages = [];
                        Object.keys(error.errors).forEach(key => {
                            errorMessages.push(...error.errors[key]);
                        });
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Errors',
                            html: errorMessages.join('<br>'),
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'An unexpected error occurred. Please try again later.'
                        });
                    }
                });
            });
        }

    });
    
    function viewDocument(url, type) {
        const viewer = document.getElementById('documentViewerModal');
        const content = document.getElementById('documentContent');
        
        // Clear previous content
        content.innerHTML = '';
        
        // Add appropriate content based on document type
        if (['jpg', 'jpeg', 'png', 'gif'].includes(type)) {
            const img = document.createElement('img');
            img.src = url;
            img.className = 'max-w-full max-h-full object-contain';
            content.appendChild(img);
        } else if (type === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.className = 'w-full h-full';
            content.appendChild(iframe);
        } else {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.className = 'w-full h-full';
            content.appendChild(iframe);
        }
        
        // Show the viewer modal
        viewer.classList.remove('hidden');
    }
    
    function closeDocumentViewer() {
        document.getElementById('documentViewerModal').classList.add('hidden');
    }
</script>

<!-- Footer -->
@include('admin.footer')
@endsection

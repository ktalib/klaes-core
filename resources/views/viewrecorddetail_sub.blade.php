@extends('layouts.app')
@section('page-title')
    @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
        {{ __('Standalone Unit Application (SUA) Details') }}
    @else
        {{ __('Unit Application Details') }}
    @endif
@endsection


@include('sectionaltitling.partials.assets.css')
@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
            <!-- Stats Cards -->


            <!-- Tabs -->

            <!-- SecondaryApplications Overview  -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="w-full max-w-7xl mx-auto mt-3">
                    <!-- Main Section -->
                    <div class="bg-white rounded-lg overflow-hidden">
                        <!-- Header -->
                        @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                            <div class="bg-blue-600 text-white p-4 rounded-t-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-xl font-bold">SUA FileNo: {{ $application->fileno ?? 'N/A' }}</h2>
                                        <p class="text-blue-100 text-sm">Standalone Unit Application</p>
                                    </div>
                                    <div class="bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-building mr-2"></i>SUA
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-emerald-600 text-white p-4 rounded-t-lg">
                                <h2 class="text-xl font-bold">STFileNo: {{ $application->fileno ?? 'N/A' }}</h2>
                            </div>
                        @endif

                        <div class="p-6">
                            @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                                <!-- SUA Application Info -->
                                <div class="mb-8 bg-white rounded-lg shadow-sm border border-blue-200 overflow-hidden">
                                    <div class="bg-blue-50 p-4 border-b border-blue-200">
                                        <h3 class="text-lg font-semibold flex items-center text-blue-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            Standalone Unit Application Details
                                        </h3>
                                    </div>
                                    <div class="p-5">
                                        <!-- SUA specific information -->
                                        <div class="bg-blue-50 p-4 rounded-lg mb-6">
                                            <div class="flex items-center mb-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3 text-blue-600"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="text-blue-800 font-medium">This is a Standalone Unit
                                                    Application</span>
                                            </div>
                                            <p class="text-blue-700 text-sm">
                                                SUA applications are independent unit applications that do not require a mother
                                                application.
                                                They follow a simplified workflow for standalone property development.
                                            </p>
                                        </div>

                                        <!-- File information -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Application Type:</p>
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                    Standalone Unit Application
                                                </span>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Land Use:</p>
                                                <p class="font-semibold text-gray-900">
                                                    {{ ucfirst($application->land_use ?? 'N/A') }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Property Location:</p>
                                                <p class="text-gray-700">{{ $application->property_location ?? 'N/A' }}</p>
                                            </div>
                                        </div>

                                        <!-- Additional SUA-specific fields -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Allocation Source:</p>
                                                <p>{{ ucfirst($application->allocation_source ?? 'N/A') }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Allocation Entity:</p>
                                                <p>{{ $application->allocation_entity ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Created Date:</p>
                                                <p>{{ $application->created_at ? date('F j, Y', strtotime($application->created_at)) : 'N/A' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- Original Owner Details (for regular unit applications) -->
                                <div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                    <div class="bg-gray-100 p-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            Original Owner Details
                                        </h3>
                                    </div>
                                    <div class="p-5">
                                        <!-- File information -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Form ID:</p>
                                                <p>STM-2025-000{{ $application->main_application_id ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Owner Type:</p>
                                                <p>{{ ucfirst($application->mother_applicant_type ?? 'N/A') }}</p>
                                            </div>
                                        </div>
                            @endif

                                    @if(!isset($application->is_sua_unit) || $application->is_sua_unit != 1)
                                                <!-- Owner identity -->
                                                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                                    <p class="text-sm text-gray-700 font-medium mb-3">Original Owner Identity:</p>

                                                    <div class="flex flex-col md:flex-row md:items-start">
                                                        <!-- Owner photo(s) section -->
                                                        <div class="md:w-1/3 mb-4 md:mb-0 md:mr-6">
                                                            @if ($application->mother_applicant_type == 'multiple')
                                                                @php
                                                                    $motherPassports = !empty($application->mother_multiple_owners_passport)
                                                                        ? json_decode($application->mother_multiple_owners_passport, true)
                                                                        : [];

                                                                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($motherPassports)) {
                                                                        $motherPassports = [];
                                                                    }
                                                                @endphp

                                                                @if(!empty($motherPassports))
                                                                    <div class="grid grid-cols-2 gap-2">
                                                                        @foreach($motherPassports as $index => $passport)
                                                                            @if($loop->index < 4) <!-- Show only first 4 photos -->
                                                                                <div class="bg-white rounded border border-gray-200 p-2">
                                                                                    @if(is_string($passport) && !empty($passport))
                                                                                        <img src="{{ asset('storage/' . $passport) }}"
                                                                                            alt="Owner {{ $index + 1 }}"
                                                                                            class="w-full h-24 object-cover rounded">
                                                                                    @elseif(is_array($passport) && !empty($passport['photo']))
                                                                                        <img src="{{ asset('storage/' . $passport['photo']) }}"
                                                                                            alt="{{ $passport['name'] ?? 'Owner ' . ($index + 1) }}"
                                                                                            class="w-full h-24 object-cover rounded">
                                                                                    @else
                                                                                        <div
                                                                                            class="w-full h-24 flex items-center justify-center bg-gray-100 rounded">
                                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                                class="h-12 w-12 text-gray-400" fill="none"
                                                                                                viewBox="0 0 24 24" stroke="currentColor">
                                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                                    stroke-width="2"
                                                                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                                            </svg>
                                                                                        </div>
                                                                                    @endif
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    </div>
                                                                    @if(count($motherPassports) > 4)
                                                                        <p class="text-xs text-gray-500 mt-2 text-center">
                                                                            +{{ count($motherPassports) - 4 }} more photos</p>
                                                                    @endif
                                                                @else
                                                                    <div class="bg-white p-4 rounded border border-gray-200 text-center">
                                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-16 w-16 mx-auto text-gray-400" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                        </svg>
                                                                        <p class="text-sm text-gray-500 mt-2">No photos</p>
                                                                    </div>
                                                                @endif
                                                            @elseif(!empty($application->mother_passport))
                                                                <div class="bg-white p-3 rounded border border-gray-200 text-center">
                                                                    <img src="{{ asset('storage/' . $application->mother_passport) }}"
                                                                        alt="Original Owner"
                                                                        class="w-32 h-32 object-cover rounded-md mx-auto">
                                                                </div>
                                                            @else
                                                                <div class="bg-white p-4 rounded border border-gray-200 text-center">
                                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                                        class="h-16 w-16 mx-auto text-gray-400" fill="none"
                                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                    </svg>
                                                                    <p class="text-sm text-gray-500 mt-2">No photo</p>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <!-- Owner details section -->
                                                        <div class="md:w-2/3">
                                                            @if ($application->mother_applicant_type == 'corporate')
                                                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                                    <div class="flex items-center">
                                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-6 w-6 mr-3 text-blue-500" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                                        </svg>
                                                                        <div>
                                                                            <p class="font-medium text-blue-800">
                                                                                {{ $application->mother_corporate_name ?? 'N/A' }}</p>
                                                                            <p class="text-sm text-gray-600">RC:
                                                                                {{ $application->mother_rc_number ?? 'N/A' }}</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @elseif ($application->mother_applicant_type == 'multiple' && !empty($application->mother_multiple_owners_names_array))
                                                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                                    <div class="flex items-center mb-2">
                                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-6 w-6 mr-3 text-blue-500" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                                        </svg>
                                                                        <h4 class="font-medium text-blue-800">Multiple Owners</h4>
                                                                    </div>
                                                                    <ul class="space-y-1 pl-9">
                                                                        @foreach($application->mother_multiple_owners_names_array as $index => $ownerName)
                                                                            @if($loop->index < 3)
                                                                                <li class="text-gray-700">{{ $ownerName }}</li>
                                                                            @elseif($loop->index == 3)
                                                                                <li class="text-blue-600 cursor-pointer hover:underline"
                                                                                    onclick="showFullNames({{ json_encode($application->mother_multiple_owners_names_array) }})">
                                                                                    +
                                                                                    {{ count($application->mother_multiple_owners_names_array) - 3 }}
                                                                                    more owners...
                                                                                </li>
                                                                                @break
                                                                            @endif
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            @else
                                                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                                    <div class="flex items-center">
                                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-6 w-6 mr-3 text-blue-500" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                        </svg>
                                                                        <div>
                                                                            <p class="font-medium text-blue-800">
                                                                                {{ $application->mother_applicant_title ?? '' }}
                                                                                {{ $application->mother_first_name ?? '' }}
                                                                                {{ $application->mother_middle_name ?? '' }}
                                                                                {{ $application->mother_surname ?? '' }}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <br>
                                                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                                <div class="flex items-center">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                                        class="lucide lucide-id-card-icon lucide-id-card h-6 w-6 mr-3 text-blue-500">
                                                                        <path d="M16 10h2" />
                                                                        <path d="M16 14h2" />
                                                                        <path d="M6.17 15a3 3 0 0 1 5.66 0" />
                                                                        <circle cx="9" cy="11" r="2" />
                                                                        <rect x="2" y="5" width="20" height="14" rx="2" />
                                                                    </svg>
                                                                    <div>
                                                                        <p class="font-medium text-blue-800">
                                                                            FileNo: {{ $application->mother_fileno ?? 'N/A' }}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>








                                                <!-- Property details -->
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                                    <div>
                                                        <p class="text-sm text-gray-600 font-medium">Land Use:</p>
                                                        <p>{{ ucfirst($application->mother_land_use ?? 'N/A') }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm text-gray-600 font-medium">Plot Size:</p>
                                                        <p>{{ $application->mother_plot_size ?? 'N/A' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm text-gray-600 font-medium">Phone:</p>
                                                        <p>{{ $application->mother_phone_number ?? 'N/A' }}</p>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                                    <div>
                                                        <p class="text-sm text-gray-600 font-medium">Total Blocks:</p>
                                                        <p class="font-medium">{{ $application->mother_NoOfBlocks ?? 'N/A' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm text-gray-600 font-medium">Total Sections:</p>
                                                        <p class="font-medium">{{ $application->mother_NoOfSections ?? 'N/A' }}</p>
                                                    </div>
                                                </div>

                                                @if($application->mother_property_plot_no || $application->mother_property_house_no || $application->mother_property_street_name)
                                                    <div class="mb-4">
                                                        <p class="text-sm text-gray-600 font-medium">Property Address:</p>
                                                        <div class="p-3 bg-blue-50 rounded-md mt-1 text-gray-700 border-l-4 border-blue-400">
                                                            {{ $application->mother_property_house_no ?? '' }} {{ $application->mother_property_plot_no ?? '' }} 
                                                            {{ $application->mother_property_street_name ?? '' }} 
                                                            @if($application->mother_property_district || $application->mother_property_lga)
                                                                , {{ $application->mother_property_district ?? '' }} {{ $application->mother_property_lga ?? '' }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                            <!-- Unit Owner Details -->
                            <div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                <div class="bg-gray-100 p-4 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                                            SUA Applicant Details
                                        @else
                                            Unit Owner Details
                                        @endif

                                        <!-- Action Buttons -->
                                        @php
                                            $isApproved = ($application->application_status == 'Approved' && $application->planning_recommendation_status == 'Approved');
                                        @endphp

                                        <div class="ml-auto flex items-center gap-3">
                                            <!-- Edit Button -->
                                            <button type="button" onclick="editUnitApplication()"
                                                class="flex items-center px-3 py-1 text-sm font-medium rounded-md transition duration-150 ease-in-out {{ $isApproved ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700 text-white' }}"
                                                {{ $isApproved ? 'disabled' : '' }}
                                                title="{{ $isApproved ? 'Cannot edit - Both Application Status and Planning Recommendation have been approved' : 'Edit Unit Application' }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </button>

                                            <!-- View Documents Button -->
                                            <button onclick="openDocumentsModal()"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 text-sm rounded-md flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                View Documents
                                            </button>
                                        </div>
                                    </h3>
                                </div>

                                <!-- Documents Modal -->
                                <div id="documentsModal"
                                    class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
                                    <div
                                        class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                                        <div
                                            class="bg-gray-100 p-4 border-b border-gray-200 flex justify-between items-center">
                                            <h3 class="text-lg font-semibold">Application Documents</h3>
                                            <button onclick="closeDocumentsModal()"
                                                class="text-gray-600 hover:text-gray-800">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
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
                                                                <div
                                                                    class="flex-shrink-0 w-16 h-16 bg-gray-100 rounded flex items-center justify-center">
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
                                                                        <img src="{{ asset('storage/' . $document['path']) }}"
                                                                            alt="Document" class="w-14 h-14 object-cover rounded">
                                                                    @else
                                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-10 w-10 text-gray-400" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                        </svg>
                                                                    @endif
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-sm font-medium text-gray-900 capitalize">
                                                                        {{ str_replace('_', ' ', $key) }}
                                                                    </p>
                                                                    <p class="text-xs text-gray-500">
                                                                        Uploaded:
                                                                        {{ isset($document['uploaded_at']) ? \Carbon\Carbon::parse($document['uploaded_at'])->format('M d, Y') : 'N/A' }}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="mt-3 flex justify-end">
                                                                <button
                                                                    onclick="viewDocument('{{ asset('storage/' . $document['path']) }}', '{{ $type }}')"
                                                                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1 rounded">
                                                                    View Document
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach

                                                @if(count($documents) == 0)
                                                    <div class="col-span-2 text-center py-8">
                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                            class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        <p class="text-gray-500">No documents available</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Document Viewer Modal -->
                                <div id="documentViewerModal"
                                    class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden flex items-center justify-center">
                                    <div class="max-w-5xl w-full h-[34vh] bg-white rounded-lg shadow-xl overflow-hidden flex flex-col"
                                        style="max-height: 90vh;">
                                        <div
                                            class="bg-gray-100 p-4 border-b border-gray-200 flex justify-between items-center">
                                            <h3 class="text-lg font-semibold">Document Viewer</h3>
                                            <button onclick="closeDocumentViewer()"
                                                class="text-gray-600 hover:text-gray-800">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="flex-1 p-4 overflow-auto bg-gray-800 flex items-center justify-center"
                                            id="documentContent">
                                            <!-- Document will be loaded here -->
                                        </div>
                                    </div>
                                </div>

                                <script>
                                    function openDocumentsModal() {
                                        document.getElementById('documentsModal').classList.remove('hidden');
                                        document.body.style.overflow = 'hidden';
                                    }

                                    function closeDocumentsModal() {
                                        document.getElementById('documentsModal').classList.add('hidden');
                                        document.body.style.overflow = '';
                                    }

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

                                <div class="p-5">
                                    <!-- Unit Identification Details -->


                                    <!-- Unit Owner Identity -->
                                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                        <p class="text-sm text-gray-700 font-medium mb-3">Unit Owner Identity:</p>

                                        <div class="flex flex-col md:flex-row md:items-start">
                                            <!-- Owner photo section -->
                                            <div class="md:w-1/3 mb-4 md:mb-0 md:mr-6">
                                                @if ($application->applicant_type == 'multiple' && !empty($application->multiple_owners_data_array))
                                                    <div class="grid grid-cols-2 gap-2">
                                                        @foreach($application->multiple_owners_data_array as $index => $ownerData)
                                                            @if($loop->index < 4) <!-- Show only first 4 photos -->
                                                                <div class="bg-white rounded border border-gray-200 p-2">
                                                                    @if(!empty($ownerData['photo']))
                                                                        <img src="{{ asset('storage/' . $ownerData['photo']) }}"
                                                                            alt="{{ $ownerData['name'] ?? 'Owner ' . ($index + 1) }}"
                                                                            class="w-full h-24 object-cover rounded">
                                                                    @else
                                                                        <div
                                                                            class="w-full h-24 flex items-center justify-center bg-gray-100 rounded">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                class="h-12 w-12 text-gray-400" fill="none"
                                                                                viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                    stroke-width="2"
                                                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                            </svg>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                    @if(count($application->multiple_owners_data_array) > 4)
                                                        <p class="text-xs text-gray-500 mt-2 text-center">
                                                            +{{ count($application->multiple_owners_data_array) - 4 }} more owners
                                                        </p>
                                                    @endif
                                                @elseif(!empty($application->passport))
                                                    <div class="bg-white p-3 rounded border border-gray-200 text-center">
                                                        <img src="{{ asset('storage/' . $application->passport) }}"
                                                            alt="Unit Owner" class="w-32 h-32 object-cover rounded-md mx-auto">
                                                    </div>
                                                @else
                                                    <div class="bg-white p-4 rounded border border-gray-200 text-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                            class="h-16 w-16 mx-auto text-gray-400" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        <p class="text-sm text-gray-500 mt-2">No photo</p>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Owner details section -->
                                            <div class="md:w-2/3">
                                                @if ($application->applicant_type == 'corporate')
                                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                        <div class="flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                class="h-6 w-6 mr-3 text-blue-500" fill="none"
                                                                viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                            </svg>
                                                            <div>
                                                                @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                                                                    <p class="font-medium text-blue-800">
                                                                        {{ $application->corporate_name ?? 'Corporate Entity' }}</p>
                                                                    <p class="text-sm text-gray-600">RC:
                                                                        {{ $application->rc_number ?? 'Not Provided' }}</p>
                                                                @else
                                                                    <p class="font-medium text-blue-800">
                                                                        {{ $application->corporate_name ?? 'N/A' }}</p>
                                                                    <p class="text-sm text-gray-600">RC:
                                                                        {{ $application->rc_number ?? 'N/A' }}</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @elseif ($application->applicant_type == 'multiple' && !empty($application->multiple_owners_names_array))
                                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                        <div class="flex items-center mb-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                class="h-6 w-6 mr-3 text-blue-500" fill="none"
                                                                viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                            </svg>
                                                            <h4 class="font-medium text-blue-800">Multiple Owners</h4>
                                                        </div>
                                                        <ul class="space-y-1 pl-9">
                                                            @foreach($application->multiple_owners_names_array as $index => $ownerName)
                                                                @if($loop->index < 3)
                                                                    <li class="text-gray-700">{{ $ownerName }}</li>
                                                                @elseif($loop->index == 3)
                                                                    <li class="text-blue-600 cursor-pointer hover:underline"
                                                                        onclick="showFullNames({{ json_encode($application->multiple_owners_names_array) }})">
                                                                        + {{ count($application->multiple_owners_names_array) - 3 }}
                                                                        more owners...
                                                                    </li>
                                                                    @break
                                                                @endif
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @else
                                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                        <div class="flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                class="h-6 w-6 mr-3 text-blue-500" fill="none"
                                                                viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                            </svg>
                                                            <div>
                                                                @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                                                                    @php
                                                                        $fullName = trim(($application->applicant_title ?? '') . ' ' .
                                                                            ($application->first_name ?? '') . ' ' .
                                                                            ($application->middle_name ?? '') . ' ' .
                                                                            ($application->surname ?? ''));
                                                                        $fullName = $fullName !== '' ? $fullName : 'SUA Applicant';
                                                                    @endphp
                                                                    <p class="font-medium text-blue-800">{{ $fullName }}</p>
                                                                @else
                                                                    <p class="font-medium text-blue-800">
                                                                        {{ $application->applicant_title ?? '' }}
                                                                        {{ $application->first_name ?? '' }}
                                                                        {{ $application->middle_name ?? '' }}
                                                                        {{ $application->surname ?? '' }}
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <br>
                                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                    <div class="flex items-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                            class="lucide lucide-id-card-icon lucide-id-card h-6 w-6 mr-3 text-blue-500">
                                                            <path d="M16 10h2" />
                                                            <path d="M16 14h2" />
                                                            <path d="M6.17 15a3 3 0 0 1 5.66 0" />
                                                            <circle cx="9" cy="11" r="2" />
                                                            <rect x="2" y="5" width="20" height="14" rx="2" />
                                                        </svg>
                                                        <div>
                                                            <p class="font-medium text-blue-800">
                                                                ST FIleNo: {{ $application->fileno ?? '' }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <br>
                                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                                    <div class="flex items-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                            class="lucide lucide-grid2x2-check-icon lucide-grid-2x2-check h-6 w-6 mr-3 text-blue-500">
                                                            <path
                                                                d="M12 3v17a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1H3" />
                                                            <path d="m16 19 2 2 4-4" />
                                                        </svg>
                                                        <div>
                                                            <p class="font-medium text-blue-800">
                                                                Scheme No: {{ $application->scheme_no ?? '' }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>


                                        </div>
                                    </div>

                                    <!-- Contact Information -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <p class="text-sm text-gray-600 font-medium">Phone Number:</p>
                                            <p>{{ $application->phone_number ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600 font-medium">Email:</p>
                                            <p>{{ $application->email ?? 'N/A' }}</p>
                                        </div>
                                        <div> 
                                            <p class="text-sm text-gray-600 font-medium">Address:</p>
                                            <p>{{ $application->address ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Application Status -->
                            <!-- Application Status -->
                            <div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                <div class="bg-gray-100 p-4 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        Application Status
                                    </h3>
                                </div>
                                <div class="p-5">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <p class="text-sm text-gray-600 font-medium">Planning Recommendation:</p>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $application->planning_recommendation_status === 'Approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }} mt-1">
                                                {{ $application->planning_recommendation_status ?? 'Pending' }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600 font-medium">Application Status:</p>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $application->application_status === 'Approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }} mt-1">
                                                {{ $application->application_status ?? 'Processing' }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600 font-medium">Approval Date:</p>
                                            <p>{{ $application->approval_date ? date('F j, Y', strtotime($application->approval_date)) : 'N/A' }}
                                            </p>
                                        </div>
                                    </div>

                                </div>
                            </div>


                            <!-- Property Details -->
                            <div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                <div class="bg-gray-100 p-4 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                        </svg>
                                        @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                                            SUA Property Details
                                        @else
                                            Property Details
                                        @endif
                                    </h3>
                                </div>
                                <div class="p-5">
                                    @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                                        <!-- SUA Property Details -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Unit Type:</p>
                                                <p class="text-blue-600 font-semibold">{{ $application->unit_type ?? 'N/A' }}
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Unit Size:</p>
                                                <p>{{ $application->unit_size ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Unit Number:</p>
                                                <p>{{ $application->unit_number ?? 'N/A' }}</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Land Use:</p>
                                                <p class="text-blue-600 font-semibold">
                                                    {{ ucfirst($application->land_use ?? 'N/A') }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Scheme Number:</p>
                                                <p>{{ $application->scheme_no ?? 'N/A' }}</p>
                                            </div>
                                        </div>

                                        @if($application->property_location)
                                            <div class="mb-6">
                                                <p class="text-sm text-gray-600 font-medium">Property Location:</p>
                                                <div
                                                    class="p-3 bg-blue-50 rounded-md mt-1 text-gray-700 border-l-4 border-blue-400">
                                                    {{ $application->property_location }}
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <!-- Regular Unit Application Property Details -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                            <!-- <div>
                                                <p class="text-sm text-gray-600 font-medium">Total Blocks (Mother):</p>
                                                <p class="text-blue-600 font-semibold">{{ $application->mother_NoOfBlocks ?? 'N/A' }}</p>
                                            </div> -->
                                            <!-- <div>
                                                <p class="text-sm text-gray-600 font-medium">Total Sections (Mother):</p>
                                                <p class="text-blue-600 font-semibold">{{ $application->mother_NoOfSections ?? 'N/A' }}</p>
                                            </div> -->
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Unit Block Number:</p>
                                                <p class="text-blue-600 font-semibold">{{ $application->block_number ?? 'N/A' }}
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Unit Section (Floor):</p>
                                                <p>{{ $application->floor_number ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Unit Number:</p>
                                                <p>{{ $application->unit_number ?? 'N/A' }}</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">House Number:</p>
                                                <p>{{ $application->mother_property_house_no ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Plot Number:</p>
                                                <p>{{ $application->mother_property_plot_no ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Street Name:</p>
                                                <p>{{ $application->mother_property_street_name ?? 'N/A' }}</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">District:</p>
                                                <p>{{ $application->mother_property_district ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Local Govt Area:</p>
                                                <p>{{ $application->mother_property_lga ?? 'N/A' }}</p>
                                            </div>
                                        </div>

                                        @if($application->mother_property_plot_no || $application->mother_property_house_no || $application->mother_property_street_name)
                                            <div class="mb-6">
                                                <p class="text-sm text-gray-600 font-medium">Mother Property Location:</p>
                                                <div class="p-3 bg-emerald-50 rounded-md mt-1 text-gray-700 border-l-4 border-emerald-400">
                                                    {{ $application->mother_property_house_no ?? '' }} {{ $application->mother_property_plot_no ?? '' }} 
                                                    {{ $application->mother_property_street_name ?? '' }}
                                                    @if($application->mother_property_district || $application->mother_property_lga)
                                                        , {{ $application->mother_property_district ?? '' }} {{ $application->mother_property_lga ?? '' }}
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Land Use:</p>
                                                <p>{{ ucfirst($application->land_use ?? 'N/A') }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="mt-6">
                                        <p class="text-sm text-gray-600 font-medium">Comments:</p>
                                        <div class="p-3 bg-gray-50 rounded-md mt-1 text-gray-700">
                                            {{ $application->comments ?? 'No comments' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if(!isset($application->is_sua_unit) || $application->is_sua_unit != 1)
                                <!-- Shared Areas -->
                                <div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                    <div class="bg-gray-100 p-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            Shared Areas
                                        </h3>
                                    </div>
                                    <div class="p-5">
                                        @php
                                            $sharedAreas = [];

                                            // Try multiple possible field names for shared areas
                                            if (isset($application->shared_areas) && !empty($application->shared_areas)) {
                                                if (is_string($application->shared_areas)) {
                                                    $sharedAreas = json_decode($application->shared_areas, true);
                                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                                        $sharedAreas = [$application->shared_areas]; // Treat as single area
                                                    }
                                                } elseif (is_array($application->shared_areas)) {
                                                    $sharedAreas = $application->shared_areas;
                                                }
                                            }

                                            // Fallback to mother application shared areas if available
                                            if (empty($sharedAreas) && isset($application->mother_shared_areas) && !empty($application->mother_shared_areas)) {
                                                if (is_string($application->mother_shared_areas)) {
                                                    $sharedAreas = json_decode($application->mother_shared_areas, true);
                                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                                        $sharedAreas = [$application->mother_shared_areas];
                                                    }
                                                } elseif (is_array($application->mother_shared_areas)) {
                                                    $sharedAreas = $application->mother_shared_areas;
                                                }
                                            }

                                            // Filter out empty values
                                            $sharedAreas = array_filter($sharedAreas ?? [], function ($area) {
                                                return !empty(trim($area));
                                            });
                                        @endphp

                                        @if(count($sharedAreas) > 0)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($sharedAreas as $area)
                                                    <span
                                                        class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        {{ ucfirst(trim($area)) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-center py-4 text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                <p>No shared areas defined for this property</p>
                                            </div>
                                        @endif

                                        <div class="mt-4 text-sm text-gray-600">
                                            <p class="font-medium mb-2">All residents have access to these common areas as part
                                                of the sectional title scheme.</p>
                                            <p>Maintenance of shared areas is covered by the body corporate fees.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(isset($application->is_sua_unit) && $application->is_sua_unit == 1)
                                <!-- SUA Payment Details -->
                                <div class="mb-8 bg-white rounded-lg shadow-sm border border-blue-200 overflow-hidden">
                                    <div class="bg-blue-50 p-4 border-b border-blue-200">
                                        <h3 class="text-lg font-semibold flex items-center text-blue-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                            </svg>
                                            SUA Payment Details
                                        </h3>
                                    </div>
                                    <div class="p-5">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Payment Date:</p>
                                                <p class="font-medium">
                                                    {{ $application->payment_date ? date('F j, Y', strtotime($application->payment_date)) : 'Not Paid' }}
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Receipt Number:</p>
                                                <p class="font-medium">{{ $application->receipt_number ?? 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Application Fee:</p>
                                                <p class="font-medium">
                                                    ₦{{ number_format((float) ($application->application_fee ?? 0), 2) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!-- Initial Bill Details (for regular unit applications only) -->
                            @if(!(isset($application->is_sua_unit) && $application->is_sua_unit == 1))
                                <div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                    <div class="bg-gray-100 p-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                            </svg>
                                            Initial Bill Details
                                        </h3>
                                    </div>
                                    <div class="p-5">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Application Fee:</p>
                                                <p class="font-medium">
                                                    ₦{{ number_format((float) ($application->application_fee ?? 0), 2) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Processing Fee:</p>
                                                <p class="font-medium">
                                                    ₦{{ number_format((float) ($application->processing_fee ?? 0), 2) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600 font-medium">Site Plan Fee:</p>
                                                <p class="font-medium">
                                                    ₦{{ number_format((float) ($application->site_plan_fee ?? 0), 2) }}</p>
                                            </div>


                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                                <div>
                                                    <p class="text-sm text-gray-600 font-medium">Payment Date:</p>
                                                    <p>{{ $application->payment_date ? date('F j, Y', strtotime($application->payment_date)) : 'N/A' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-gray-600 font-medium">Receipt Number:</p>
                                                    <p>{{ $application->receipt_number ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Total Bill Calculation -->
                                        @php
                                            $applicationFee = (float) ($application->application_fee ?? 0);
                                            $processingFee = (float) ($application->processing_fee ?? 0);
                                            $sitePlanFee = (float) ($application->site_plan_fee ?? 0);
                                            $totalBill = $applicationFee + $processingFee + $sitePlanFee;
                                        @endphp

                                        <div class="mt-6 border-t pt-4">
                                            <div class="flex justify-end">
                                                <p class="text-sm text-gray-600 font-medium mr-4">Total Initial Bill:</p>
                                                <p class="font-bold text-green-700">₦{{ number_format($totalBill, 2) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Action Buttons -->
                            <div class="flex justify-center space-x-4 mt-8">
                                <button type="button" onclick="window.history.back();"
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
                </div>


                <!-- Footer -->
                @include('admin.footer')
            </div>

@endsection

        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            window.showFullNames = function (owners) {
                if (!Array.isArray(owners)) {
                    owners = [];
                }
                if (owners.length > 0) {
                    Swal.fire({
                        title: 'Full Names of Multiple Owners',
                        html: '<ul>' + owners.map(name => `<li>${name}</li>`).join('') + '</ul>',
                        icon: 'info',
                        confirmButtonText: 'Close'
                    });
                } else {
                    Swal.fire({
                        title: 'Full Names of Multiple Owners',
                        text: 'No owners available',
                        icon: 'info',
                        confirmButtonText: 'Close'
                    });
                }
            }

            // Edit Unit Application Function
            function editUnitApplication() {
                const applicationId = {{ $application->id ?? 'null' }};

                Swal.fire({
                    title: 'Edit Unit Application',
                    text: 'Are you sure you want to edit this unit application?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, Edit',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect to edit page for sub applications
                        window.location.href = `{{ route('pua.edit', '') }}/${applicationId}`;
                    }
                });
            }
        </script>
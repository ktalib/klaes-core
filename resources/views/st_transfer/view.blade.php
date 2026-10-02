@extends('layouts.app')

@section('page-title')
    View Transfer of Title
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="container mx-auto py-6 space-y-6 px-4">
        <!-- Page Header with Action Buttons -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Transfer of Title Details</h1>
                <p class="text-sm text-gray-500">View the details of this sectional title transfer</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('st_transfer.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
               
                <button onclick="printPage()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Main Content Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <!-- Status Badge -->
            <div class="w-full flex justify-end p-4">
                <span class="badge badge-{{ $application->reg_status ?? 'pending' }}">
                    {{ ucfirst($application->reg_status ?? 'pending') }}
                </span>
            </div>

            <!-- Content Sections -->
            <div class="p-6 space-y-8">
                <!-- Application Information -->
                <div>
                    <h2 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Application Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">File Number</h3>
                            <p class="mt-1">{{ $application->fileno ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">STM No</h3>
                            <p class="mt-1">{{ $application->STM_Ref ?? 'N/A' }}</p>
                        </div>
                       
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Application Date</h3>
                            <p class="mt-1">{{ $application->created_at ? date('F j, Y', strtotime($application->created_at)) : 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                    <!-- Registration Details -->
                <div>
                    <h2 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Registration Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Registration Number</h3>
                            <span class="inline-block px-3 py-1 rounded-full bg-blue-100 text-blue-800 font-semibold text-sm mt-1">
                                {{ $application->Deeds_Serial_No ?? 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Deeds Date</h3>
                            <span class="inline-block px-3 py-1 rounded-full bg-green-100 text-green-800 font-semibold text-sm mt-1">
                                {{ $application->deeds_date ? date('F j, Y', strtotime($application->deeds_date)) : 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Deeds Time</h3>
                            <span class="inline-block px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 font-semibold text-sm mt-1">
                                {{ $application->deeds_time ?? 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Tenure Period</h3>
                            <span class="inline-block px-3 py-1 rounded-full bg-purple-100 text-purple-800 font-semibold text-sm mt-1">
                                {{ $application->Tenure_Period ? $application->Tenure_Period . ' years' : 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Serial Details</h3>
                            <span class="inline-block px-3 py-1 rounded-full bg-indigo-100 text-indigo-800 font-semibold text-sm mt-1">
                                  SerialNo: {{ $application->serial_no ?? 'N/A' }}
                                  PageNo: {{ $application->reg_page_no ?? 'N/A' }},
                                  VolumeNo: {{ $application->volume_no ?? 'N/A' }},
                              
                                
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Registered By</h3>
                            <span class="inline-block px-3 py-1 rounded-full bg-pink-100 text-pink-800 font-semibold text-sm mt-1">
                                {{ $application->reg_creator_name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Property Details -->
                <div>
                    <h2 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Property Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Number of Units</h3>
                            <p class="mt-1">{{ $application->NoOfUnits ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Number of Blocks</h3>
                            <p class="mt-1">{{ $application->NoOfBlocks ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Number of Sections</h3>
                            <p class="mt-1">{{ $application->NoOfSections ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Location</h3>
                            <p class="mt-1">
                                {{ $application->property_street_name ?? '' }}
                                {{ !empty($application->property_district) ? ', ' . $application->property_district : '' }}
                                {{ !empty($application->property_lga) ? ', ' . $application->property_lga : '' }}
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Land Use</h3>
                            <p class="mt-1">{{ $application->land_use ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Property Type</h3>
                            <p class="mt-1">
                                @if(!empty($application->commercial_type))
                                    Commercial ({{ $application->commercial_type }})
                                @elseif(!empty($application->industrial_type))
                                    Industrial ({{ $application->industrial_type }})
                                @elseif(!empty($application->residential_type))
                                    Residential ({{ $application->residential_type }})
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Owner Information -->
                <div>
                    <h2 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Owner Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {{-- <div>
                            <h3 class="text-sm font-medium text-gray-500">Current Owner</h3>
                            <p class="mt-1">{{ $application->Current_Owner ?? 'N/A' }}</p>
                        </div> --}}
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Applicant Name</h3>
                            <p class="mt-1">{{ $application->Applicant_Name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Occupation</h3>
                            <p class="mt-1">{{ $application->Occupation ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
                
            
                
                <!-- Status History -->
                <div>
                    <h2 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Status History</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 rounded-full bg-{{ $application->reg_status === 'registered' ? 'green' : ($application->reg_status === 'rejected' ? 'red' : 'blue') }}-100 flex items-center justify-center text-{{ $application->reg_status === 'registered' ? 'green' : ($application->reg_status === 'rejected' ? 'red' : 'blue') }}-500">
                                <i class="fas fa-{{ $application->reg_status === 'registered' ? 'check' : ($application->reg_status === 'rejected' ? 'times' : 'clock') }}"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium">{{ ucfirst($application->reg_status ?? 'Pending') }}</h3>
                                <p class="text-xs text-gray-500">{{ $application->updated_at ? date('F j, Y g:i A', strtotime($application->updated_at)) : 'N/A' }}</p>
                            </div>
                        </div>
                        
                        @if($application->reg_status === 'registered')
                        <div class="border-l-2 border-green-500 pl-4 ml-5 mt-2">
                    <p class="text-sm text-gray-600">Sectional transfer of title  successfully registered.</p>
                        </div>
                        @elseif($application->reg_status === 'rejected')
                        <div class="border-l-2 border-red-500 pl-4 ml-5 mt-2">
                            <p class="text-sm text-gray-600">This registration was declined.</p>
                        </div>
                        @else
                        <div class="border-l-2 border-blue-500 pl-4 ml-5 mt-2">
                            <p class="text-sm text-gray-600">This application is pending registration.</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>

@if(session('success'))
<script>
    Swal.fire({
        title: 'Success!',
        text: "{{ session('success') }}",
        icon: 'success',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
</script>
@endif

<script>
function printPage() {
    window.print();
}
</script>

<style>
@media print {
    nav, header, footer, .no-print {
        display: none !important;
    }
    
    body {
        background-color: white;
    }
    
    .container {
        max-width: 100% !important;
        padding: 0 !important;
    }
    
    .bg-white {
        box-shadow: none !important;
    }
}
</style>
@endsection

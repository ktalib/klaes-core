@extends('layouts.app')

@section('page-title')
    Generate Sectional CofO
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="container mx-auto py-6 space-y-6 px-4">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h1 class="text-2xl font-bold text-gray-900">Generate Certificate of Occupancy</h1>
            <a href="{{ route('st_transfer.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-600 hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i> Back to List
            </a>
        </div>
    
        <!-- Main Content Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6">
                <div class="border-b pb-4 mb-4">
                    <h2 class="text-lg font-medium text-gray-900">Property Details</h2>
                    <p class="text-sm text-gray-500">Registration details for Unit {{ $application->unit_number }}, {{ $application->fileno }}</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Unit Information</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dl class="grid grid-cols-1 gap-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">File Number:</dt>
                                    <dd class="text-sm text-gray-900">{{ $application->fileno }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Unit Number:</dt>
                                    <dd class="text-sm text-gray-900">{{ $application->unit_number }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Block Number:</dt>
                                    <dd class="text-sm text-gray-900">{{ $application->block_number }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Floor Number:</dt>
                                    <dd class="text-sm text-gray-900">{{ $application->floor_number }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Land Use:</dt>
                                    <dd class="text-sm text-gray-900">{{ $application->land_use }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Owner Information</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dl class="grid grid-cols-1 gap-y-2">
                                @if($application->corporate_name)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Corporate Name:</dt>
                                    <dd class="text-sm text-gray-900">{{ $application->corporate_name }}</dd>
                                </div>
                                @else
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Name:</dt>
                                    <dd class="text-sm text-gray-900">{{ $application->applicant_title }} {{ $application->first_name }} {{ $application->surname }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
                
                <form action="{{ route('st_transfer.save') }}" method="POST" class="space-y-6">
                    @csrf
                    <input type="hidden" name="sub_application_id" value="{{ $application->id }}">
                    
                    <div class="border-t border-b py-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Certificate of Occupancy Details</h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="certificate_number" class="block text-sm font-medium text-gray-700 mb-1">Certificate Number</label>
                                <input type="text" name="certificate_number" id="certificate_number" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="{{ $application->certificate_number ?? ('COFO-ST-' . date('Y') . '-' . $application->unit_number) }}" required>
                            </div>
                            
                            <div>
                                <label for="registration_date" class="block text-sm font-medium text-gray-700 mb-1">Registration Date</label>
                                <input type="date" name="registration_date" id="registration_date" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="{{ $application->issued_date ? date('Y-m-d', strtotime($application->issued_date)) : date('Y-m-d') }}" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="signed_by" class="block text-sm font-medium text-gray-700 mb-1">Signed By</label>
                                <input type="text" name="signed_by" id="signed_by" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="{{ $application->signed_by ?? '' }}" required>
                            </div>
                            
                            <div>
                                <label for="signed_title" class="block text-sm font-medium text-gray-700 mb-1">Signatory Title</label>
                                <input type="text" name="signed_title" id="signed_title" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="{{ $application->signed_title ?? 'Director' }}" required>
                            </div>
                        </div>
                        
                        <div>
                            <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                            <textarea name="remarks" id="remarks" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <a href="{{ route('st_transfer.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                            Register Certificate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>
@endsection

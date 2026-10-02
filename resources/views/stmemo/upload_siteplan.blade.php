@extends('layouts.app')

@section('page-title')
    {{$PageTitle}}
@endsection

@include('sectionaltitling.partials.assets.css')

@section('content')
<div class="flex-1 overflow-auto bg-gray-100 min-h-screen">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Main Content -->
    <div class="container mx-auto p-6">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow p-8">
            <div class="flex justify-between items-center mb-6 pb-4 border-b">
                <h1 class="text-2xl font-bold text-gray-800">Site Plan Details</h1>
                <a href="{{ route('stmemo.siteplan') }}" class="flex items-center text-blue-600 hover:text-blue-800">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
                    Back to Site Plans
                </a>
            </div>
            
            <div class="mb-8">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i data-lucide="info" class="w-5 h-5 text-blue-500"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Application Information</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>
                                    <span class="font-semibold">File No:</span> 
                                    {{ $isPrimary ? $application->fileno : $application->primary_fileno }} 
                                    {{ !$isPrimary ? ' / ' . $application->fileno : '' }}
                                </p>
                                <p>
                                    <span class="font-semibold">Applicant:</span> 
                                    @if($isPrimary)
                                        @if(!empty($application->corporate_name))
                                            {{ $application->corporate_name }}
                                        @else
                                            {{ $application->applicant_title }} {{ $application->first_name }} {{ $application->surname }}
                                        @endif
                                    @else
                                        @if(!empty($application->corporate_name))
                                            {{ $application->corporate_name }}
                                        @else
                                            {{ $application->applicant_title }} {{ $application->first_name }} {{ $application->surname }}
                                        @endif
                                    @endif
                                </p>
                                <p>
                                    <span class="font-semibold">Location:</span>
                                    {{ $application->property_plot_no }} {{ $application->property_street_name }},
                                    {{ $application->property_lga }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                @if(isset($existingSitePlan))
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800">Site Plan Available</h3>
                            <div class="mt-2 text-sm text-green-700">
                                <p>Site plan is available for this application.</p>
                                <p class="mt-1">
                                    <strong>File:</strong> {{ $existingSitePlan->site_file }}<br>
                                    <strong>Uploaded:</strong> {{ date('d M Y, g:i A', strtotime($existingSitePlan->created_at)) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                @else
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i data-lucide="upload-cloud" class="w-5 h-5 text-amber-500"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">No Site Plan Uploaded</h3>
                            <div class="mt-2 text-sm text-amber-700">
                                <p>No site plan has been uploaded for this application yet.</p>
                                <p class="mt-1">Use the action menu in the Site Plans page to upload a site plan.</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
                @if(isset($existingSitePlan))
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Location</label>
                        <div class="p-3 bg-gray-50 border rounded-md">
                            {{ $existingSitePlan->property_location }}
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Site Plan File</label>
                        @php
                            $fileUrl = asset('storage/site_plans/' . $existingSitePlan->site_file);
                            $extension = strtolower(pathinfo($existingSitePlan->site_file, PATHINFO_EXTENSION));
                        @endphp
                        
                        <div class="border rounded-lg p-4 bg-white">
                            @if(in_array($extension, ['jpg','jpeg','png']))
                                <div class="text-center">
                                    <img src="{{ $fileUrl }}" alt="Site Plan" class="max-w-full h-auto rounded border border-gray-300 shadow-sm">
                                    <div class="mt-3 flex justify-center space-x-3">
                                        <a href="{{ $fileUrl }}" target="_blank" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <i data-lucide="external-link" class="w-4 h-4 mr-2"></i>
                                            Open Full Size
                                        </a>
                                        <a href="{{ $fileUrl }}" download class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            @elseif($extension === 'pdf')
                                <div class="text-center py-8">
                                    <i data-lucide="file-text" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">PDF Site Plan</h3>
                                    <p class="text-sm text-gray-500 mb-4">{{ $existingSitePlan->site_file }}</p>
                                    <div class="flex justify-center space-x-3">
                                        <a href="{{ $fileUrl }}" target="_blank" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            <i data-lucide="eye" class="w-4 h-4 mr-2"></i>
                                            View PDF
                                        </a>
                                        <a href="{{ $fileUrl }}" download class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <i data-lucide="file" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Site Plan File</h3>
                                    <p class="text-sm text-gray-500 mb-4">{{ $existingSitePlan->site_file }}</p>
                                    <a href="{{ $fileUrl }}" download class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                        Download File
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center py-12">
                    <i data-lucide="file-x" class="w-24 h-24 mx-auto text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Site Plan Available</h3>
                    <p class="text-sm text-gray-500 mb-6">This application does not have a site plan uploaded yet.</p>
                    <a href="{{ route('stmemo.siteplan') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                        Back to Site Plans
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>

 
@endsection
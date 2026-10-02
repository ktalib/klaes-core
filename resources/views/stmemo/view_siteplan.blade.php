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
        <div class="max-w-5xl mx-auto bg-white rounded-lg shadow p-8">
            <div class="flex justify-between items-center mb-6 pb-4 border-b">
                <h1 class="text-2xl font-bold text-gray-800">Site Plan Details</h1>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('stmemo.siteplan') }}" class="flex items-center text-blue-600 hover:text-blue-800">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
                        Back to Site Plans
                    </a>
                    <a href="{{ route('stmemo.uploadSitePlan', $application->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center">
                        <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
                        View Site Plan Details
                    </a>
                </div>
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
                                    {{ $sitePlan->property_location }}
                                </p>
                                <p>
                                    <span class="font-semibold">Uploaded:</span>
                                    {{ \Carbon\Carbon::parse($sitePlan->created_at)->format('d M, Y H:i') }}
                                </p>
                                @if($sitePlan->updated_at && $sitePlan->updated_at != $sitePlan->created_at)
                                <p>
                                    <span class="font-semibold">Last Updated:</span>
                                    {{ \Carbon\Carbon::parse($sitePlan->updated_at)->format('d M, Y H:i') }}
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="border rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Site Plan Document</h3>
                        <div class="flex space-x-2">
                            <a href="{{ asset('storage/app/public/' . $sitePlan->site_file) }}" download class="text-blue-600 hover:text-blue-800">
                                <i data-lucide="download" class="w-5 h-5"></i>
                            </a>
                            <a href="{{ asset('storage/app/public/' . $sitePlan->site_file) }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                <i data-lucide="external-link" class="w-5 h-5"></i>
                            </a>
                            <button type="button" onclick="confirmDelete()" class="text-red-600 hover:text-red-800">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-gray-100 flex justify-center" style="min-height: 400px;">
                        @php
                            $extension = pathinfo($sitePlan->site_file, PATHINFO_EXTENSION);
                        @endphp
                        
                        @if(in_array(strtolower($extension), ['jpg', 'jpeg', 'png']))
                            <img src="{{ asset('storage/app/public/' . $sitePlan->site_file) }}" alt="Site Plan" class="max-w-full max-h-[70vh] object-contain">
                        @elseif(strtolower($extension) == 'pdf')
                            <iframe src="{{ asset('storage/app/public/' . $sitePlan->site_file) }}" width="100%" height="600px" class="border-0"></iframe>
                        @else
                            <div class="flex flex-col items-center justify-center text-gray-500">
                                <i data-lucide="file" class="w-16 h-16 mb-2"></i>
                                <p>Preview not available for this file type</p>
                                <a href="{{ asset('storage/app/public/' . $sitePlan->site_file) }}" target="_blank" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Open File
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
                
                <form id="delete-form" action="{{ route('stmemo.deleteSitePlan', $application->id) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>
@endsection

@push('scripts')
<script>
    function confirmDelete() {
        Swal.fire({
            title: 'Delete Site Plan?',
            text: "This action cannot be undone. Are you sure you want to delete this site plan?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form').submit();
            }
        });
    }
</script>
@endpush

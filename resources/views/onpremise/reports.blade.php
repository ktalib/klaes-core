@extends('layouts.app')
@section('page-title')
    {{ __('On-Premise Search Reports') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <div class="p-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6">
                <h2 class="text-2xl font-bold mb-4">On-Premise Search Reports</h2>
                <p class="text-gray-600 mb-6">View and manage your on-premise search reports.</p>
                
                <div class="text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Reports Available</h3>
                    <p class="text-gray-500 mb-4">You haven't generated any search reports yet.</p>
                    <a href="{{ route('onpremise.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Start New Search
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>
@endsection
@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Generate Certificate') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <div class="bg-white rounded-md shadow-sm p-6">
            <h2 class="text-xl font-bold mb-6">Generate Certificate of Occupancy</h2>

            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="info" class="w-5 h-5 text-blue-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            Please fill in all required fields to generate the Certificate of Occupancy.
                            Some fields are pre-populated based on the application data.
                        </p>
                    </div>
                </div>
            </div>

            @include('programmes.partials.cofo_form', [
                'isModal' => false,
                'application' => $application,
                'existingCofO' => $existingCofO ?? null,
                'certificateNumber' => $certificateNumber ?? null,
                'nextAvailableCertificateNumber' => $nextAvailableCertificateNumber ?? null,
                'startDate' => $startDate ?? null,
                'totalYears' => $totalYears ?? null,
            ])
        </div>
    </div>
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>

@endsection

@extends('layouts.app')
@section('page-title')
    {{ __('View GIS Data') }}
@endsection

@include('sectionaltitling.partials.assets.css')

@section('content')

<style>
    @media print {
        /* Complete reset for print layout */
        body * {
            visibility: hidden;
        }
        
        /* Only show the content we want to print */
        .space-y-6,
        .space-y-6 * {
            visibility: visible;
        }
        
        /* Hide specific elements even within our content area */
        .space-y-6 .no-print,
        .space-y-6 a[onclick],
        .space-y-6 button,
        .space-y-6 [x-show="showModal"],
        .modal,
        .fixed {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Reposition the main content to the top left of the page */
        .space-y-6 {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* Hide site structure elements by ID and class */
        #sidebar,
        #app-header,
        header,
        .header,
        nav,
        footer,
        .footer,
        .sidebar,
        [role="navigation"],
        .flex.items-center.space-x-3,
        .bg-white.rounded-md.shadow-sm.border.border-gray-200.p-6 > div:first-child {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Layout container modifications */
        .p-6,
        .flex-1,
        .overflow-auto,
        .bg-white.rounded-md.shadow-sm.border.border-gray-200.p-6 {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            box-shadow: none !important;
            overflow: visible !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        /* Add a title at the top of the printed page */
        .space-y-6::before {
            content: "GIS Data Report";
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            display: block;
            visibility: visible !important;
        }
        
        /* Style for the content sections */
        .space-y-6 > div {
            page-break-inside: avoid;
            margin-bottom: 15px;
            border: 1px solid #ddd !important;
            border-radius: 0 !important;
            background-color: white !important;
            padding: 15px !important;
        }
        
        /* Adjust document attachment display for printing */
        .grid.grid-cols-1.md\:grid-cols-3 {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 10px !important;
        }
        
        /* Ensure text is black for better printing */
        p, h1, h2, h3, h4, h5, h6 {
            color: black !important;
        }
        
        /* Remove backgrounds for better printing */
        .bg-gray-50 {
            background-color: white !important;
        }

        /* Page setup */
        @page {
            size: A4;
            margin: 1.5cm;
        }
    }
</style>
 
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
        <!-- Main Content Container -->
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <!-- Header with actions -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">View GIS Data</h2>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('gis_record.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 flex items-center no-print">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to List
                    </a>
                    <a href="javascript:void(0)" onclick="printGISData()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center no-print">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 no-print" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            <!-- Content in card sections -->
            <div class="space-y-6">
                <!-- File Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">File Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">MLSF Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->mlsfNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">KANGIS File Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->kangisFileNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">New KANGIS File Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->NewKANGISFileno ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Plot Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Plot Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Plot Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->plotNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Block Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->blockNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Approved Plan Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->approvedPlanNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">TP Plan Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->tpPlanNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Area (in Hectares)</p>
                            <p class="text-sm font-semibold">{{ $gisData->areaInHectares ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Land Use</p>
                            <p class="text-sm font-semibold">{{ $gisData->landUse ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Specific Use</p>
                            <p class="text-sm font-semibold">{{ $gisData->specifically ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Location Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Location Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Layout Name</p>
                            <p class="text-sm font-semibold">{{ $gisData->layoutName ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">District Name</p>
                            <p class="text-sm font-semibold">{{ $gisData->districtName ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">LGA Name</p>
                            <p class="text-sm font-semibold">{{ $gisData->lgaName ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">State Name</p>
                            <p class="text-sm font-semibold">{{ $gisData->StateName ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Street Name</p>
                            <p class="text-sm font-semibold">{{ $gisData->streetName ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">House Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->houseNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">House Type</p>
                            <p class="text-sm font-semibold">{{ $gisData->houseType ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Tenancy</p>
                            <p class="text-sm font-semibold">{{ $gisData->tenancy ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Survey Information Section -->
                {{-- <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Survey Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Surveyed By</p>
                            <p class="text-sm font-semibold">{{ $gisData->surveyedBy ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Drawn By</p>
                            <p class="text-sm font-semibold">{{ $gisData->drawnBy ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Checked By</p>
                            <p class="text-sm font-semibold">{{ $gisData->checkedBy ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Passed By</p>
                            <p class="text-sm font-semibold">{{ $gisData->passedBy ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Beacon Control Name</p>
                            <p class="text-sm font-semibold">{{ $gisData->beaconControlName ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Beacon Control X</p>
                            <p class="text-sm font-semibold">{{ $gisData->beaconControlX ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Beacon Control Y</p>
                            <p class="text-sm font-semibold">{{ $gisData->beaconControlY ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Metric Sheet Index</p>
                            <p class="text-sm font-semibold">{{ $gisData->metricSheetIndex ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Metric Sheet Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->metricSheetNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Imperial Sheet</p>
                            <p class="text-sm font-semibold">{{ $gisData->imperialSheet ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Imperial Sheet Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->imperialSheetNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Surveyor General Signature Date</p>
                            <p class="text-sm font-semibold">{{ $gisData->SurveyorGeneralSignatureDate ? date('d M, Y', strtotime($gisData->SurveyorGeneralSignatureDate)) : 'N/A' }}</p>
                        </div>
                    </div>
                </div> --}}

                <!-- Title Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Title Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Old Title Serial No</p>
                            <p class="text-sm font-semibold">{{ $gisData->oldTitleSerialNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Old Title Page No</p>
                            <p class="text-sm font-semibold">{{ $gisData->oldTitlePageNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Old Title Volume No</p>
                            <p class="text-sm font-semibold">{{ $gisData->oldTitleVolumeNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Deeds Date</p>
                            <p class="text-sm font-semibold">{{ $gisData->deedsDate ? date('d M, Y', strtotime($gisData->deedsDate)) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Deeds Time</p>
                            <p class="text-sm font-semibold">{{ $gisData->deedsTime ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Certificate Date</p>
                            <p class="text-sm font-semibold">{{ $gisData->certificateDate ? date('d M, Y', strtotime($gisData->certificateDate)) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">CofO Serial No</p>
                            <p class="text-sm font-semibold">{{ $gisData->CofOSerialNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Title Issued Year</p>
                            <p class="text-sm font-semibold">{{ $gisData->titleIssuedYear ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Owner Information Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Owner Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Original Allottee</p>
                            <p class="text-sm font-semibold">{{ $gisData->originalAllottee ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Address of Original Allottee</p>
                            <p class="text-sm font-semibold">{{ $gisData->addressOfOriginalAllottee ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Change of Ownership</p>
                            <p class="text-sm font-semibold">{{ $gisData->changeOfOwnership ?? 'No' }}</p>
                        </div>
                        
                        @if($gisData->changeOfOwnership == 'Yes')
                        <div>
                            <p class="text-sm font-medium text-gray-500">Reason for Change</p>
                            <p class="text-sm font-semibold">{{ $gisData->reasonForChange ?? 'N/A' }}</p>
                        </div>
                        @endif
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500">Current Allottee</p>
                            <p class="text-sm font-semibold">{{ $gisData->currentAllottee ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Address of Current Allottee</p>
                            <p class="text-sm font-semibold">{{ $gisData->addressOfCurrentAllottee ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Title of Current Allottee</p>
                            <p class="text-sm font-semibold">{{ $gisData->titleOfCurrentAllottee ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Phone Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->phoneNo ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Email Address</p>
                            <p class="text-sm font-semibold">{{ $gisData->emailAddress ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Occupation</p>
                            <p class="text-sm font-semibold">{{ $gisData->occupation ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Nationality</p>
                            <p class="text-sm font-semibold">{{ $gisData->nationality ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Company RC Number</p>
                            <p class="text-sm font-semibold">{{ $gisData->CompanyRCNo ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Document Attachments Section -->
                <div class="bg-gray-50 p-4 rounded-lg" x-data="{ showModal: false, activeImage: '' }">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Document Attachments</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @php
                            $fileFields = [
                                'transactionDocument' => 'Transaction Document',
                                'passportPhoto' => 'Passport Photo',
                                'nationalId' => 'National ID',
                                'internationalPassport' => 'International Passport',
                                'businessRegCert' => 'Business Registration Certificate',
                                'formCO7AndCO4' => 'Form CO7 & CO4',
                                'certOfIncorporation' => 'Certificate of Incorporation',
                                'memorandumAndArticle' => 'Memorandum & Article',
                                'letterOfAdmin' => 'Letter of Administration',
                                'courtAffidavit' => 'Court Affidavit',
                                'policeReport' => 'Police Report',
                                'newspaperAdvert' => 'Newspaper Advertisement',
                                'picture' => 'Picture',
                                'SurveyPlan' => 'Survey Plan'
                            ];
                            $hasDocuments = false;
                        @endphp
                        
                        @foreach($fileFields as $field => $label)
                            @if(isset($gisData->$field) && $gisData->$field)
                                @php 
                                    $hasDocuments = true; 
                                    $filePath = $gisData->$field;
                                    $publicPath = str_replace('public/', '', $filePath);
                                    $fullUrl = asset('storage/app/public/'.$publicPath);
                                    
                                    // Determine if it's an image
                                    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                                    $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                                @endphp
                                
                                <div class="bg-white rounded-lg shadow p-3 flex flex-col">
                                    <p class="text-sm font-medium text-gray-500 mb-2">{{ $label }}</p>
                                    
                                    @if($isImage)
                                        <div class="relative h-40 bg-gray-100 rounded-md overflow-hidden mb-2">
                                            <img src="{{ $fullUrl }}" alt="{{ $label }}" 
                                                class="w-full h-full object-cover hover:opacity-90 cursor-pointer transition-opacity" 
                                                @click="showModal = true; activeImage = '{{ $fullUrl }}'">
                                        </div>
                                        <div class="mt-auto">
                                            <a href="{{ $fullUrl }}" target="_blank" 
                                                class="text-blue-600 hover:underline flex items-center text-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                View Full Size
                                            </a>
                                        </div>
                                    @else
                                        <div class="flex items-center bg-gray-50 p-3 rounded-md mb-2">
                                            <div class="mr-3 bg-blue-50 p-2 rounded-md">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div class="overflow-hidden text-sm">
                                                <p class="truncate">{{ basename($filePath) }}</p>
                                            </div>
                                        </div>
                                        <div class="mt-auto">
                                            <a href="{{ $fullUrl }}" target="_blank" 
                                                class="text-blue-600 hover:underline flex items-center text-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Download Document
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                        
                        @if(!$hasDocuments)
                            <div class="col-span-3">
                                <p class="text-sm text-gray-500">No documents attached.</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Image Modal/Lightbox -->
                    <div x-show="showModal" 
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-75 flex items-center justify-center p-4"
                        @click.self="showModal = false">
                        
                        <div class="relative bg-white rounded-lg overflow-hidden max-w-3xl max-h-screen"
                            @click.away="showModal = false">
                            
                            <!-- Close button -->
                            <button @click="showModal = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 z-10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            
                            <!-- Image container -->
                            <div class="bg-gray-800 flex items-center justify-center">
                                <img :src="activeImage" class="max-h-[80vh] max-w-full object-contain" alt="Document Preview">
                            </div>
                            
                            <!-- Controls -->
                            <div class="p-3 bg-white border-t flex justify-between">
                                <span class="text-sm text-gray-600">Click outside or press ESC to close</span>
                                <a :href="activeImage" target="_blank" class="text-blue-600 hover:underline text-sm flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    Open in New Tab
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
</div>
 
<script>
    // Add event listener for the Escape key to close the modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.querySelector('[x-data]').__x) {
            const xData = document.querySelector('[x-data]').__x.getUnobservedData();
            if (xData.showModal) {
                xData.showModal = false;
            }
        }
    });
    
    // Custom print function to control what gets printed
    function printGISData() {
        // You could add additional logic here if needed
        window.print();
    }
</script>
@endsection

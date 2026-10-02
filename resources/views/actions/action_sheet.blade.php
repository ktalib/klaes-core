@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Director\'s Action Sheet') }}
@endsection
 @section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
    
    body {
        font-family: 'Source Sans Pro', sans-serif;
        background-color: #f8f9fa;
        font-size: 18px;
        line-height: 1.5;
    }
    
    .document-container {
        width: 21cm;
        background: white;
        margin: 0 auto;
        padding: 1cm;
        position: relative;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        min-height: 29.7cm;
    }
    
    .header-line {
        height: 2px;
        background: linear-gradient(90deg, #1a56db, #1e3a8a);
        margin: 8px 0 12px 0;
    }
    
    .signature-line {
        border-bottom: 1px solid #cbd5e1;
        display: inline-block;
        width: 180px;
        margin: 0 5px;
    }
    
    .short-line {
        width: 100px;
    }
    
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 11px;
        margin: 10px 0;
    }
    
    th, td {
        border: 1px solid #cbd5e1;
        padding: 5px 6px;
        text-align: left;
    }
    
    th {
        background-color: #f1f5f9;
        font-weight: 600;
    }
    
    .signature-section {
        margin: 15px 0;
    }
    
    .compact-section {
        margin-bottom: 12px;
    }
    
    .status-passed {
        background-color: #dcfce7;
        color: #166534;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: 600;
        font-size: 11px;
    }
    
    .status-declined {
        background-color: #fee2e2;
        color: #991b1b;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: 600;
        font-size: 11px;
    }
    
    .status-paid {
        background-color: #dbeafe;
        color: #1e40af;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: 600;
        font-size: 11px;
    }
    
    .status-not-paid {
        background-color: #fef3c7;
        color: #92400e;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: 600;
        font-size: 11px;
    }

    .header-branding {
        margin-bottom: 12px;
    }

    .header-logos-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .header-logo {
        width: 60px;
        height: 60px;
        object-fit: contain;
    }

    .header-title {
        flex: 1;
        text-align: center;
        font-size: 20px;
        font-weight: 700;
        color: #1e3a8a;
        margin: 0;
    }

    .page-index-wrapper {
        display: flex;
        justify-content: flex-end;
        margin-top: 8px;
    }

    .page-index-badge {
        background-color: #dbeafe;
        color: #1e40af;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }
    
    /* Hide print button when printing */
    @media print {
        body {
            background: white;
            padding: 0;
            margin: 0;
            width: 21cm;
            height: 29.7cm;
        }
        
        .document-container {
            width: 100%;
            height: 100%;
            padding: 1cm;
            margin: 0;
            box-shadow: none;
        }
        
        .no-print {
            display: none !important;
        }
        
        @page {
            size: A4 portrait;
            margin: 0;
        }
    }
</style>
 


<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <div class="no-print flex justify-center mb-4">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg text-sm flex items-center">
                <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                Print Action Sheet
            </button>
        </div>

        <!-- Document Container -->
        <div class="document-container">
            <!-- Header -->
            <div class="compact-section header-branding">
                <div class="header-logos-row">
                    <img src="{{ asset('images/ministry-logo-left.jpg') }}" alt="Ministry Logo" class="header-logo"  >
                    <h1 class="header-title">SECTIONAL TITLE DEPARTMENT</h1>
                    <img src="{{ asset('images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" class="header-logo"  >
                </div>
                <div class="page-index-wrapper">
                    <span class="page-index-badge">PAGE INDEX: 1</span>
                </div>
            </div>
            
            <div class="header-line"></div>
            
            <h2 class="text-lg font-bold text-center my-4 text-gray-800">DIRECTOR'S APPROVAL ACTION SHEET</h2>

            <!-- Application Details -->
            <div class="text-gray-700 compact-section">
                <p class="mb-3">
                    The fragmentation of property with scheme No: <span class="font-semibold">{{ $application->scheme_no ?? '______' }}</span>, 
                    ST application no <span class="font-semibold">{{ $application->np_fileno ?? $application->fileno ?? '______' }}</span>, located at 
                    <span class="font-semibold">{{ trim(($application->property_house_no ?? '') . ' ' . ($application->property_street_name ?? '') . ', ' . ($application->property_lga ?? '')) ?? '______' }}</span> under the name of 
                    <span class="font-semibold">{{ trim(($application->applicant_title ?? '') . ' ' . ($application->first_name ?? '') . ' ' . ($application->surname ?? '')) ?? '______' }}</span> with extant C-of-O no and plot no: 
                    <span class="font-semibold">{{ $application->cofo_number ?? '______' }}</span> (or Piece of land(domain)) is hereby 
                    @if($application->application_status == 'Approved')
                        <span class="font-bold text-green-600">APPROVED</span>
                    @else
                        <span class="font-bold text-red-600">DECLINED</span>
                    @endif
                    based on the under listed requirements:
                </p>
            </div>

            <!-- Requirements Table -->
            <table class="compact-section">
                <thead>
                    <tr>
                        <th>REQUIREMENT</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>a) Application Requirements</td>
                        <td>
                            @if(!empty($application->application_requirements_status))
                                @if($application->application_requirements_status == 'Approved')
                                    <span class="status-passed">PASSED</span>
                                @else
                                    <span class="status-declined">DECLINED</span>
                                @endif
                            @else
                                <span class="status-passed">PASSED</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>b) Site Plan</td>
                        <td>
                            @if(!empty($application->site_plan_status))
                                @if($application->site_plan_status == 'Approved')
                                    <span class="status-passed">PASSED</span>
                                @else
                                    <span class="status-declined">DECLINED</span>
                                @endif
                            @else
                                <span class="status-passed">PASSED</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>c) OSS Inspection Report</td>
                        <td>
                            @if(!empty($application->oss_inspection_status))
                                @if($application->oss_inspection_status == 'Approved')
                                    <span class="status-passed">PASSED</span>
                                @else
                                    <span class="status-declined">DECLINED</span>
                                @endif
                            @else
                                <span class="status-declined">PENDING</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>d) Planning Advice</td>
                        <td>
                            @if($application->planning_recommendation_status == 'Approved')
                                <span class="status-passed">PASSED</span>
                            @else
                                <span class="status-declined">DECLINED</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>e) Application and processing Fees</td>
                        <td>
                            @if(!empty($application->application_fee_status))
                                @if($application->application_fee_status == 'Paid')
                                    <span class="status-paid">PAID</span>
                                @else
                                    <span class="status-not-paid">NOT PAID</span>
                                @endif
                            @else
                                <span class="status-paid">PAID</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>f) Outstanding land use charges</td>
                        <td>
                            @if(!empty($application->land_use_charges_status))
                                @if($application->land_use_charges_status == 'Paid')
                                    <span class="status-paid">PAID</span>
                                @else
                                    <span class="status-not-paid">NOT PAID</span>
                                @endif
                            @else
                                <span class="status-not-paid">NOT PAID</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Director's Remark -->
            <div class="signature-section">
                <div class="font-bold text-sm mb-1">DIRECTOR'S REMARK:</div>
                <div class="@if($application->application_status == 'Approved') bg-green-50 border-l-4 border-green-500 @else bg-red-50 border-l-4 border-red-500 @endif p-3">
                    <p class="text-gray-700 text-sm">
                        @if(!empty($application->comments))
                            {{ $application->comments }}
                        @else
                            @if($application->application_status == 'Approved')
                                The application has been approved and meets all the requirements for sectional title development.
                            @else
                                The application was declined due to missing or inadequate requirements for the proposed fragmentation.
                            @endif
                        @endif
                    </p>
                </div>
            </div>

            <!-- Signature Section -->
            <div class="flex justify-between mt-8">
                <div>
                    <p class="text-sm">Sign: <span class="signature-line"></span></p>
                </div>
                <div>
                    <p class="text-sm">Date: <span class="signature-line short-line">{{ $application->approval_date ?? now()->format('d/m/Y') }}</span></p>
                </div>
            </div>

            <!-- Footer -->
            <div class="absolute bottom-4 left-0 right-0 text-center text-xs text-gray-500">
                <p>Official Document - Generated on: {{ now()->format('d/m/Y H:i') }} | 
                   Action Sheet Generated: {{ $application->action_sheet_generated_at ? \Carbon\Carbon::parse($application->action_sheet_generated_at)->format('d/m/Y H:i') : 'N/A' }}</p>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>
@endsection

@section('scripts')
<script>
    // Additional JavaScript for print functionality if needed
    document.addEventListener('DOMContentLoaded', function() {
        // Any initialization code can go here
    });
</script>
@endsection
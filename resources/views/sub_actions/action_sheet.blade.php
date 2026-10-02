@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Director\'s Action Sheet - Unit Application') }}
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
        background: linear-gradient(90deg, #8b5cf6, #7c3aed);
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
        background-color: #f3f4f6;
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
            <button onclick="window.print()" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-6 rounded-lg text-sm flex items-center">
                <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                Print Unit Action Sheet
            </button>
        </div>

        <!-- Document Container -->
        <div class="document-container">
            <!-- Header -->
            <div class="flex justify-between items-center compact-section">
                <h1 class="text-xl font-bold text-purple-900">SECTIONAL TITLE DEPARTMENT</h1>
                <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-semibold">UNIT APPLICATION</span>
            </div>
            
            <div class="header-line"></div>
            
            <h2 class="text-lg font-bold text-center my-4 text-gray-800">DIRECTOR'S APPROVAL ACTION SHEET - UNIT APPLICATION</h2>

            <!-- Application Details -->
            <div class="text-gray-700 compact-section hidden">
                <p class="mb-3">
                    The sectional title unit application with File No: <span class="font-semibold">{{ $application->mls_fileno ?? $application->fileno ?? '______' }}</span>, 
                    Unit Type: <span class="font-semibold">{{ $application->unit_type ?? 'Parented Unit' }}</span>, located at 
                    <span class="font-semibold">{{ trim(($application->property_house_no ?? '') . ' ' . ($application->property_street_name ?? '') . ', ' . ($application->property_lga ?? '')) ?? '______' }}</span> under the name of 
                    <span class="font-semibold">{{ trim(($application->applicant_title ?? '') . ' ' . ($application->first_name ?? '') . ' ' . ($application->surname ?? '')) ?? '______' }}</span> 
                    linked to Primary Application: <span class="font-semibold">{{ $application->primary_fileno ?? $application->np_fileno ?? '______' }}</span> is hereby 
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
                        <td>a) Unit Application Requirements</td>
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
                        <td>b) Primary Application Approval</td>
                        <td>
                            @if($application->primary_application_status == 'Approved')
                                <span class="status-passed">PASSED</span>
                            @else
                                <span class="status-declined">PENDING</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>c) Unit Plan Approval</td>
                        <td>
                            @if(!empty($application->unit_plan_status))
                                @if($application->unit_plan_status == 'Approved')
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
                        <td>e) Unit Application Fees</td>
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
                        <td>f) Certificate of Occupancy</td>
                        <td>
                            @php
                            $cofoRecord = DB::connection('sqlsrv')
                                ->table('Cofo')
                                ->where('mlsFNo', $application->fileno)
                                ->orWhere('kangisFileNo', $application->fileno)
                                ->orWhere('NewKANGISFileno', $application->fileno)
                                ->select('regNo')
                                ->first();
                            @endphp
                            @if(!$cofoRecord)
                                <span class="status-declined">NOT CAPTURED</span>
                            @elseif($cofoRecord->regNo == '0/0/0')
                                <span class="status-not-paid">NO CofO</span>
                            @else
                                <span class="status-passed">CofO AVAILABLE</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Director's Remark -->
            <div class="signature-section">
                <div class="font-bold text-sm mb-1">DIRECTOR'S REMARK:</div>
                <div class="p-3 @if($application->application_status == 'Approved') bg-green-50 border-l-4 border-green-500 @else bg-red-50 border-l-4 border-red-500 @endif">
                    <p class="text-gray-700 text-sm">
                        @if(!empty($application->comments))
                            {{ $application->comments }}
                        @else
                            @if($application->application_status == 'Approved')
                                The unit application has been approved and meets all the requirements for sectional title unit development.
                            @else
                                The unit application was declined due to missing or inadequate requirements for the proposed unit development.
                            @endif
                        @endif
                    </p>
                </div>
            </div>

            <!-- Unit Details Section -->
            <div class="compact-section">
                <div class="font-bold text-sm mb-2">UNIT DETAILS:</div>
                <table>
                    <tr>
                        <td><strong>Unit Type:</strong></td>
                        <td>{{ $application->unit_type ?? 'Parented Unit' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Primary Land Use:</strong></td>
                        <td>{{ $application->primary_land_use ?? $application->land_use ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Primary Application File:</strong></td>
                        <td>{{ $application->primary_fileno ?? $application->np_fileno ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Unit File Number:</strong></td>
                        <td>{{ $application->mls_fileno ?? $application->fileno ?? 'N/A' }}</td>
                    </tr>
                </table>
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
                <p>Official Unit Application Document - Generated on: {{ now()->format('d/m/Y H:i') }} | 
                   Action Sheet Generated: {{ $application->action_sheet_generated_at ? \Carbon\Carbon::parse($application->action_sheet_generated_at)->format('d/m/Y H:i') : 'N/A' }}</p>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>
 
<script>
    // Additional JavaScript for print functionality if needed
    document.addEventListener('DOMContentLoaded', function() {
        // Any initialization code can go here
    });
</script>
@endsection
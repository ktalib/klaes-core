@extends('layouts.app')
@section('page-title')
    {{ __('Planning Recommendation Declination Memo') }}
@endsection
@section('content')
<style>
  @media print {
            .no-print {
                display: none !important;
            }
            body, html {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                background: white !important;
                font-size: 11pt !important; /* Reduce font size slightly */
            }
            /* Hide all headers, navigation, and footer elements */
            header, nav, footer, .header, .footer, .sidebar, #admin-header, #admin-footer {
                display: none !important;
            }
            /* Force A4 size with proper dimensions */
            .print-container {
                width: 210mm !important;
                max-height: 297mm !important; /* Changed from min-height to max-height */
                height: auto !important; /* Let it size naturally */
                margin: 0 auto !important;
                padding: 0 !important;
                background: white !important;
                box-shadow: none !important;
                border: none !important;
                overflow: visible !important; /* Ensure no overflow creates extra page */
            }
            /* Prevent page breaks inside important sections */
            .no-break {
                page-break-inside: avoid !important;
            }
            /* Set proper margins for A4 - reduce margin to fit more content */
            @page {
                size: A4 portrait;
                margin: 1cm;
            }
            /* Reduce spacing */
            .p-8 {
                padding: 1.5rem !important;
            }
            .mb-8, .mb-6 {
                margin-bottom: 0.75rem !important;
            }
            .mt-16 {
                margin-top: 0.75rem !important;
            }
            /* Compact criteria items */
            .mb-6 > p, .mb-6 > div {
                margin-bottom: 0.25rem !important;
            }
            /* Make signature section more compact */
            .signature-section {
                margin-top: 0.5rem !important;
                margin-bottom: 0 !important;
            }
            /* Remove bottom space */
            .p-8:last-child {
                padding-bottom: 0 !important;
            }
            /* Reset any lingering margins */
            body::after, html::after, 
            .print-container::after {
                content: none !important;
            }
        }
</style>

    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                @php
                    // Get application details from the database
                    if (!isset($application) && request()->has('id')) {
                        $application = DB::connection('sqlsrv')
                            ->table('mother_applications')
                            ->where('id', request()->get('id'))
                            ->first();
                    }

                    if (!isset($surveyRecord) && isset($application)) {
                        $surveyRecord = DB::connection('sqlsrv')
                            ->table('surveyCadastralRecord')
                            ->where('sub_application_id', $application->id)
                            ->first();
                    }
                    
                    // Get decline reasons if available
                    $declineReasons = null;
                    if (isset($application)) {
                        $declineReasons = DB::connection('sqlsrv')
                            ->table('planning_decline_reasons')
                            ->where('sub_application_id', $application->id)
                            ->orderBy('created_at', 'desc')
                            ->first();
                    }
                @endphp

                <div class="max-w-4xl mx-auto bg-white shadow-md my-8 print-container">
                    <!-- Print Button with enhanced functionality -->
                    <div class="bg-gray-800 p-4 flex justify-end no-print">
                        <button onclick="printMemo()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Declination Memo
                        </button>
                    </div>

                    <!-- Page 1 -->
                    <div class="p-8">
                        <!-- Header -->
                        <div class="text-center mb-6">
                            <h1 class="text-xl font-bold uppercase">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                            <h2 class="text-lg font-semibold uppercase">PHYSICAL PLANNING DEPARTMENT</h2>
                        </div>

                        <!-- Memo Details --> 
                        <div class="mb-6">
                            <p class="font-semibold">FROM: DIRECTOR, PHYSICAL PLANNING</p>
                            <p class="mt-4">To: Permanent Secretary</p>
                            <p class="mt-2">Re: Declination of Planning Recommendation for Sectional Titling in respect of</p>
                            <p>Property Name: 
                                <strong>
                                    {{ $application->commercial_type ?? $application->industrial_type ?? $application->ownership_type ?? $application->residence_type ?? 'N/A' }}
                                </strong>
                            </p>
                            <p>Applicant Name: 
                                <strong>
                                @if ($application->applicant_type == 'individual')
                                    {{ $application->applicant_title ?? '' }} {{ $application->first_name ?? '' }} {{ $application->surname ?? '' }}
                                @elseif($application->applicant_type == 'corporate')
                                    {{ $application->corporate_name ?? '' }}
                                @elseif($application->applicant_type == 'multiple')
                                    @php
                                        $names = @json_decode($application->multiple_owners_names, true);
                                        echo is_array($names) ? implode(', ', $names) : ($application->multiple_owners_names ?? 'Multiple Owners');
                                    @endphp
                                @endif
                                </strong>
                            </p>
                        </div>

                        <!-- Introduction -->
                        <div class="mb-6">
                            <p>Dear Sir/Madam,</p>
                            <p class="mt-2 text-justify">
                                The above subject refers. Following a thorough review and physical site inspection conducted by our team, 
                                this memorandum provides a detailed assessment of the property/site located at:
                            </p>
                            <p class="mt-3">Property Address: 
                                <strong>
                                {{ $motherApplication->property_house_no ?? '' }} 
                                {{ $motherApplication->property_plot_no ?? '' }} 
                                {{ $motherApplication->property_street_name ?? '' }}, 
                                {{ $motherApplication->property_district ?? '' }}
                                </strong>
                            </p>
                            <p>Plot Number: <strong>{{ $motherApplication->property_plot_no ?? 'N/A' }}</strong></p>
                            <p>Block Number: <strong>{{ $application->block_number ?? '' }}</strong></p>
                            <p>LGA: <strong>{{ $motherApplication->property_lga ?? 'N/A' }}</strong></p>
                            <p>Unit No: <strong>{{ $application->unit_number ?? 'N/A' }}</strong></p>
                            
                            <p class="mt-4 text-justify">
                                Based on the evaluation against the prescribed planning criteria for Sectional Titling, 
                                we regret to inform you that the property/site does not meet the necessary conditions for approval. 
                                Below are the findings from our assessment:
                            </p>
                        </div>

                        <!-- Decline Reasons Section -->
                        <div class="mb-8">
                            @if (isset($declineReasons) && $declineReasons)
                                @php $reasonCount = 1; @endphp
                                
                                @if ($declineReasons->accessibility_selected)
                                <div class="mb-6">
                                    <p class="font-bold">{{ $reasonCount++ }}. Accessibility</p>
                                    <p class="ml-4">• Condition: The property/site must have adequate accessibility to ensure ease of movement and compliance with urban planning standards.</p>
                                    <p class="ml-4">• Findings:</p>
                                    <div class="ml-8">
                                        @if (!empty($declineReasons->access_road_details))
                                        <p>• {{ $declineReasons->access_road_details }}</p>
                                        @endif
                                        
                                        @if (!empty($declineReasons->pedestrian_details))
                                        <p>• Obstructions/barriers: {{ $declineReasons->pedestrian_details }}</p>
                                        @endif
                                        
                                        @if (empty($declineReasons->access_road_details) && empty($declineReasons->pedestrian_details))
                                        <p>• The property lacks adequate accessibility features required by planning standards.</p>
                                        @endif
                                    </div>
                                    <p class="ml-4">• Conclusion: The property/site does not satisfy the accessibility requirement.</p>
                                </div>
                                @endif
                                
                                @if ($declineReasons->land_use_selected)
                                <div class="mb-6">
                                    <p class="font-bold">{{ $reasonCount++ }}. Conformity with Existing Land Use of the Area</p>
                                    <p class="ml-4">• Condition: The property/site must conform to the existing land use designation of the area as per the Kano State Physical Development Plan.</p>
                                    <p class="ml-4">• Findings:</p>
                                    <div class="ml-8">
                                        @if (!empty($declineReasons->zoning_details))
                                        <p>• {{ $declineReasons->zoning_details }}</p>
                                        @endif
                                        
                                        @if (!empty($declineReasons->density_details))
                                        <p>• {{ $declineReasons->density_details }}</p>
                                        @endif
                                        
                                        @if (empty($declineReasons->zoning_details) && empty($declineReasons->density_details))
                                        <p>• The property does not conform to the approved land use plan for the area.</p>
                                        @endif
                                    </div>
                                    <p class="ml-4">• Conclusion: The property/site does not conform to the existing land use regulations.</p>
                                </div>
                                @endif
                                
                                @if ($declineReasons->utility_selected)
                                <div class="mb-6">
                                    <p class="font-bold">{{ $reasonCount++ }}. No Transverse of Utility Lines</p>
                                    <p class="ml-4">• Condition: The property/site must not transverse or interfere with existing utility lines (e.g., electricity, water, sewage).</p>
                                    <p class="ml-4">• Findings:</p>
                                    <div class="ml-8">
                                        @if (!empty($declineReasons->overhead_details))
                                        <p>• {{ $declineReasons->overhead_details }}</p>
                                        @endif
                                        
                                        @if (!empty($declineReasons->underground_details))
                                        <p>• {{ $declineReasons->underground_details }}</p>
                                        @endif
                                        
                                        @if (empty($declineReasons->overhead_details) && empty($declineReasons->underground_details))
                                        <p>• The property interferes with existing utility infrastructure in the area.</p>
                                        @endif
                                    </div>
                                    <p class="ml-4">• Conclusion: The property/site violates the no-transverse utility line condition.</p>
                                </div>
                                @endif
                                
                                @if ($declineReasons->road_reservation_selected)
                                <div class="mb-6">
                                    <p class="font-bold">{{ $reasonCount++ }}. Adequate Access Road/Road Reservation</p>
                                    <p class="ml-4">• Condition: The property/site must have an adequate access road or comply with the minimum road reservation standards as stipulated in the Kano Urban Planning and Development Authority (KAMMA) guidelines.</p>
                                    <p class="ml-4">• Findings:</p>
                                    <div class="ml-8">
                                        @if (!empty($declineReasons->right_of_way_details))
                                        <p>• {{ $declineReasons->right_of_way_details }}</p>
                                        @endif
                                        
                                        @if (!empty($declineReasons->road_width_details))
                                        <p>• Measurements: {{ $declineReasons->road_width_details }}</p>
                                        @endif
                                        
                                        @if (empty($declineReasons->right_of_way_details) && empty($declineReasons->road_width_details))
                                        <p>• The property lacks an adequate access road network as required by planning standards.</p>
                                        @endif
                                    </div>
                                    <p class="ml-4">• Conclusion: The property/site does not meet the requirements for adequate access road/road reservation.</p>
                                </div>
                                @endif
                                
                            @else
                                <!-- Display generic reason if no detailed reasons are available -->
                                <div class="p-4 bg-gray-50 rounded-md">
                                    <p class="whitespace-pre-line">{{ $application->recomm_comments ?? 'The application does not meet the necessary requirements for planning recommendation approval.' }}</p>
                                </div>
                            @endif
                        </div>

                        <!-- Conclusion -->
                        <div class="mb-6">
                            <p class="text-justify">
                                In view of these deficiencies, the application for Sectional Titling cannot be recommended for approval at this time. 
                                We advise the applicant to address the identified issues and resubmit the application for reconsideration.
                            </p>
                            <p class="mt-4">If you have no objection, please.</p>
                        </div>

                        <!-- Signature Section (integrated with main content) -->
                        <div class="signature-section no-break" style="padding: 0.5rem 0;">
                            <div class="flex justify-between mt-4">
                                <div class="w-1/2">
                                    <div class="border-t border-black w-3/4 mb-1"></div>
                                    <p class="font-semibold">DIRECTOR, PHYSICAL PLANNING</p>
                                    <p class="text-sm">Date: <strong>{{ date('d/m/Y') }}</strong></p>
                                </div>
                                <div class="w-1/2 text-right">
                                    <div class="border-t border-black w-3/4 ml-auto mb-1"></div>
                                    <p class="font-semibold">PERMANENT SECRETARY</p>
                                    <p class="text-sm">Date: ________________________</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            @include('admin.footer')
        </div>
    </div>

    <script>
        // Function to handle printing just the memo content
        function printMemo() {
            // Create a clone of the print container
            const printContent = document.querySelector('.print-container').cloneNode(true);
            
            // Remove any no-print elements from the clone
            const noPrintElements = printContent.querySelectorAll('.no-print');
            noPrintElements.forEach(element => element.remove());
            
            // Create a new window and document
            const printWindow = window.open('', '_blank', 'height=800,width=800');
            printWindow.document.write('<html><head><title>Planning Recommendation Declination Memo</title>');
            
            // Copy all styles from the current page
            document.querySelectorAll('style, link[rel="stylesheet"]').forEach(style => {
                printWindow.document.write(style.outerHTML);
            });
            
            // Add specific print styles
            printWindow.document.write(`
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        padding: 0;
                        margin: 0;
                        background: white;
                    }
                    .print-container {
                        width: 210mm;
                        height: auto;
                        max-height: 297mm;
                        padding: 15mm 15mm 10mm 15mm; /* Reduced padding */
                        margin: 0 auto;
                        background: white;
                        overflow: hidden;
                    }
                    @media print {
                        body {
                            padding: 0;
                            margin: 0;
                        }
                        @page {
                            size: A4 portrait;
                            margin: 1cm;
                        }
                        /* Prevent any extra blank pages */
                        html, body {
                            height: auto;
                            overflow: visible;
                        }
                    }
                </style>
            `);
            
            printWindow.document.write('</head><body>');
            printWindow.document.write('<div class="print-container">');
            printWindow.document.write(printContent.innerHTML);
            printWindow.document.write('</div></body></html>');
            
            printWindow.document.close();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.focus();
                    // Remove any extra spaces or elements that might cause a second page
                    const container = printWindow.document.querySelector('.print-container');
                    // Set explicit height to content
                    container.style.height = container.scrollHeight + 'px';
                    printWindow.print();
                }, 500);
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Add any JavaScript functionality needed
        });
    </script>
@endsection

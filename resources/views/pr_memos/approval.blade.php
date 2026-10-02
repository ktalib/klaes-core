@extends('layouts.app')
@section('page-title')
    {{ __('Planning Recommendation Approval Memo') }}
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
            /* Force A4 size with reduced padding to fit content */
            .print-container {
                width: 210mm !important;
                max-height: 297mm !important; /* Changed from min-height to max-height */
                height: auto !important; /* Let it size naturally */
                overflow: visible !important; /* Ensure no overflow creates extra page */
                margin: 0 auto !important;
                padding: 0 !important;
                background: white !important;
                box-shadow: none !important;
                border: none !important;
            }
            /* Prevent page breaks inside sections */
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
            .mb-8 {
                margin-bottom: 1rem !important;
            }
            .mt-16 {
                margin-top: 0.75rem !important;
            }
            .space-y-6 {
                margin-top: 0.5rem !important;
            }
            /* Compact criteria items */
            .space-y-6 > div {
                margin-bottom: 0.5rem !important;
            }
            /* Ensure content is scaled to fit */
            .print-container * {
                box-sizing: border-box !important;
            }
            /* Make signature section more compact */
            .signature-section {
                margin-top: 0.5rem !important;
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
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
                            ->where('application_id', $application->id)
                            ->first();
                    }
                @endphp

                <div class="max-w-4xl mx-auto bg-white shadow-md my-8 print-container">
                    <!-- Print Button -->
                    <div class="bg-gray-800 p-4 flex justify-end no-print">
                        <button onclick="printMemo()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Memo
                        </button>
                    </div>

                    <!-- Page 1 -->
                    <div class="p-8">
                        <!-- Header -->
                        <div class="text-center mb-8">
                            <h1 class="text-xl font-bold uppercase">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                            <h2 class="text-lg font-semibold uppercase">PHYSICAL PLANNING DEPARTMENT</h2>
                        </div>

                        <!-- Memo Details -->
                        <div class="mb-8 space-y-2">
                            <p class="font-semibold">FROM: DIRECTOR, PHYSICAL PLANNING</p>
                            <p class="font-semibold">TO: PERMANENT SECRETARY</p>
                            <p class="font-semibold">RE: APPLICATION FOR PLANNING RECOMMENDATION APPROVAL IN RESPECT OF PLOT NO. <strong>{{ $application->property_plot_no ?? 'N/A' }}</strong></p>
                        </div>

                        <!-- Property Details -->
                        <div class="mb-8 border-b pb-4">
                            <p class="mb-1"><span class="font-semibold">Property Address:</span> 
                                <strong>
                                {{ $application->property_house_no ?? '' }} 
                                {{ $application->property_plot_no ?? '' }} 
                                {{ $application->property_street_name ?? '' }}, 
                                {{ $application->property_district ?? '' }}
                                </strong>
                            </p>
                            <p class="mb-1"><span class="font-semibold">Plot Number:</span> <strong>{{ $application->property_plot_no ?? 'N/A' }}</strong></p>
                            <p class="mb-1"><span class="font-semibold">Block Number:</span> <strong>{{ $surveyRecord->block_no ?? 'N/A' }}</strong></p>
                            <p class="mb-1"><span class="font-semibold">LGA:</span> <strong>{{ $application->property_lga ?? 'N/A' }}</strong></p>
                        </div>

                        <!-- Introduction -->
                        <div class="mb-6">
                            <p class="text-justify">
                                The above subject matter refers. Following the submission of an application for Planning
                                Recommendation Approval by 
                                <strong>
                                @if ($application->applicant_type == 'individual')
                                    {{ $application->applicant_title ?? '' }} {{ $application->first_name ?? '' }} {{ $application->surname ?? '' }}
                                @elseif($application->applicant_type == 'corporate')
                                    {{ $application->corporate_name ?? '' }} (RC: {{ $application->rc_number ?? '' }})
                                @elseif($application->applicant_type == 'multiple')
                                    @php
                                        $names = @json_decode($application->multiple_owners_names, true);
                                        echo is_array($names) ? implode(', ', $names) : ($application->multiple_owners_names ?? 'Multiple Owners');
                                    @endphp
                                @endif
                                </strong>, 
                                a detailed physical site inspection and analysis were conducted to assess compliance with statutory 
                                requirements and planning standards. Below is a comprehensive report detailing the findings based 
                                on the key criteria for evaluation:
                            </p>
                        </div>

                        <!-- Evaluation Criteria -->
                        <div class="space-y-6 mb-8">
                            <!-- Criterion 1 -->
                            <div>
                                <h3 class="font-bold">1. ACCESSIBILITY /ROAD RESERVATION</h3>
                                <p class="ml-4">
                                    Recommendation: The property satisfies the accessibility requirement and adequate road
                                    reservation for early visibility.
                                </p>
                            </div>

                            <!-- Criterion 2 -->
                            <div>
                                <h3 class="font-bold">2. CONFORMITY WITH EXISTING LAND USE OF THE AREA</h3>
                                <p class="ml-4">
                                    Recommendation: The property conforms to the existing land-use <strong>{{ $application->land_use ?? '' }}</strong> designation
                                    of the area and is recommended for approval.
                                </p>
                            </div>

                            <!-- Criterion 3 -->
                            <div>
                                <h3 class="font-bold">3. NOT TRANSVERSING ANY UTILITY LINE</h3>
                                <ul class="list-disc ml-8">
                                    <li class="mb-2">
                                        A thorough inspection of the property revealed no encroachment or transversal of utility
                                        lines such as electricity, water supply, drainage, or telecommunication infrastructure.
                                    </li>
                                    <li>
                                        Utility clearance certificates have been obtained from relevant agencies, confirming the
                                        absence of conflicts with existing utilities.
                                    </li>
                                </ul>
                                <p class="ml-4 mt-2">
                                    Recommendation: The property does not interfere with any utility lines and is safe for
                                    development.
                                </p>
                            </div>

                            <!-- Criterion 4 -->
                            <div>
                                <h3 class="font-bold">4. ADEQUATE ACCESS ROAD/ROAD RESERVATION</h3>
                                <p class="ml-4">
                                    The property has adequate access road and satisfies all road reservation requirements
                                    as stipulated in the planning guidelines for <strong>{{ $application->land_use ?? '' }}</strong> development.
                                </p>
                            </div>

                            <!-- Additional Observations -->
                            <div>
                                <h3 class="font-bold">ADDITIONAL OBSERVATIONS (IF APPLICABLE):  :</h3>
                                <ul class="list-disc ml-8">
                                    <li>
                                    {{ $additionalObservations ?? 'No additional observations provided.' }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Page 2 - Signature Section -->
                    <div class="p-8 border-t no-break">
                        <!-- Signature Block - Updated to match official format -->
                        <div class="mt-8 mb-4 signature-section">
                            <div class="flex justify-between">
                                <div class="w-1/2">
                                    <div class="border-t border-black w-3/4 mt-8 mb-1"></div>
                                    <p class="font-semibold">DIRECTOR, PHYSICAL PLANNING</p>
                                    <p class="text-sm">Date: <strong>{{ date('d/m/Y') }}</strong></p>
                                </div>
                                <div class="w-1/2 text-right">
                                    <div class="border-t border-black w-3/4 ml-auto mt-8 mb-1"></div>
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
            printWindow.document.write('<html><head><title>Planning Recommendation Approval Memo</title>');
            
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
            // Handle additional observations
            const additionalObservations = document.getElementById('additionalObservations');
            const printedObservations = document.getElementById('printedObservations');
            const saveObservationsBtn = document.getElementById('saveObservations');
            
            if (saveObservationsBtn && additionalObservations) {
                saveObservationsBtn.addEventListener('click', function() {
                    const applicationId = {{ $application->id ?? 'null' }};
                    if (!applicationId) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Application ID not found'
                        });
                        return;
                    }
                    
                    // Show loading indicator
                    saveObservationsBtn.disabled = true;
                    saveObservationsBtn.textContent = 'Saving...';
                    
                    // Save the observations
                    fetch('{{ route("pr_memos.save-observations") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            application_id: applicationId,
                            additional_observations: additionalObservations.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        saveObservationsBtn.disabled = false;
                        saveObservationsBtn.textContent = 'Save Observations';
                        
                        if (data.success) {
                            // Update the printed observations
                            printedObservations.textContent = additionalObservations.value;
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: 'Additional observations saved successfully',
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to save observations'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error saving observations:', error);
                        saveObservationsBtn.disabled = false;
                        saveObservationsBtn.textContent = 'Save Observations';
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while saving observations'
                        });
                    });
                });
            }
            
            // Ensure printed observations show the current value
            if (additionalObservations && printedObservations) {
                // Update printed text when observation changes
                additionalObservations.addEventListener('input', function() {
                    printedObservations.textContent = this.value;
                });
            }
        });
    </script>
@endsection

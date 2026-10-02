<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo - {{ $memo->memo_no ?? 'MEMO/2025/01' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                width: 21cm;
                height: 29.7cm;
            }
            
            .memo-container {
                width: 100%;
                height: 100%;
                padding: 1.5cm;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            @page {
                size: A4 portrait;
                margin: 0;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            /* Ensure logos print correctly */
            img {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        .memo-container {
            max-width: 21cm;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            min-height: 29.7cm;
            font-family: 'Times New Roman', serif;
        }
        
        .memo-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .memo-no {
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .header-line {
            border-bottom: 2px solid #000;
            margin: 1rem 0;
        }

        .data-value {
            font-weight: 600;
        }
    </style>
</head>
<body class="py-4 bg-gray-50">

    <div class="no-print flex justify-center mb-4">
        <a href="javascript:history.back()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-4 inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
        </a>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md transition duration-200 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print Document
        </button>
    </div>

    <!-- Memo Container -->
    <div class="memo-container">
        <!-- Header with Logos -->
        <div class="memo-header">
            <div class="flex justify-between items-center mb-4">
                <!-- Left Ministry Logo -->
                <div class="w-20 h-20">
                    <img src="{{ asset('images/ministry-logo-left.jpg') }}" alt="Ministry Logo" class="w-full h-full object-contain" onerror="this.style.display='none'">
                </div>
                
                <!-- Center Content -->
                <div class="text-center flex-1">
                    <div class="memo-no">{{ $memo->memo_no ?? 'MEMO/2025/01' }}</div>
                </div>
                
                <!-- PERMANENT SECRETARY moved to right side -->
                <div class="text-left">
                    <div class="font-bold">PERMANENT SECRETARY</div>
                </div>
                
                <!-- Right Ministry Logo -->
                <div class="w-20 h-20">
                    <img src="{{ asset('images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" class="w-full h-full object-contain" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
        
         
        
        @php
            $applicationPageDisplay = '-';
            if (isset($memo->page_no) && trim((string) $memo->page_no) !== '') {
                $applicationPageDisplay = trim((string) $memo->page_no);
            } elseif (isset($pageNo) && trim((string) $pageNo) !== '') {
                $applicationPageDisplay = trim((string) $pageNo);
            } elseif (isset($landAdmin) && isset($landAdmin->page_no) && trim((string) $landAdmin->page_no) !== '') {
                $applicationPageDisplay = trim((string) $landAdmin->page_no);
            }
            if ($applicationPageDisplay === '-') {
                $applicationPageDisplay = '45';
            }

            $sitePlanPageDisplay = '-';
            if (isset($memo->site_plan_no) && trim((string) $memo->site_plan_no) !== '') {
                $sitePlanPageDisplay = trim((string) $memo->site_plan_no);
            } elseif (isset($landAdmin) && isset($landAdmin->site_plan_page_no) && trim((string) $landAdmin->site_plan_page_no) !== '') {
                $sitePlanPageDisplay = trim((string) $landAdmin->site_plan_page_no);
            } elseif (isset($landAdmin) && isset($landAdmin->page_no) && trim((string) $landAdmin->page_no) !== '') {
                $sitePlanPageDisplay = trim((string) $landAdmin->page_no);
            }
            if ($sitePlanPageDisplay === '-') {
                $sitePlanPageDisplay = $applicationPageDisplay;
            }

            $propertyTypeRaw = $memo->commercial_type ?? $memo->residence_type ?? $memo->property_type ?? $memo->land_use ?? null;
            if (is_string($propertyTypeRaw) && trim($propertyTypeRaw) !== '') {
                $propertyTypeDisplay = ucwords(strtolower(trim($propertyTypeRaw)));
            } else {
                $propertyTypeDisplay = 'Commercial / Residential';
            }

            $certificateNumberDisplay = '-';
            if (isset($memo->certificate_number) && trim($memo->certificate_number) !== '') {
                $certificateNumberDisplay = strtoupper(trim($memo->certificate_number));
            } elseif (isset($memo->fileno) && trim($memo->fileno) !== '') {
                $certificateNumberDisplay = strtoupper(trim($memo->fileno));
            }
            if ($certificateNumberDisplay === '-') {
                $certificateNumberDisplay = 'DD';
            }

            $locationCandidates = [];
            if (isset($memo->property_location) && trim($memo->property_location) !== '') {
                $locationCandidates[] = $memo->property_location;
            }

            $composedLocationParts = array_filter([
                isset($memo->property_house_no) && trim($memo->property_house_no) !== '' ? trim($memo->property_house_no) : null,
                isset($memo->property_plot_no) && trim($memo->property_plot_no) !== '' ? trim($memo->property_plot_no) : null,
                isset($memo->property_street_name) && trim($memo->property_street_name) !== '' ? trim($memo->property_street_name) : null,
                isset($memo->property_district) && trim($memo->property_district) !== '' ? trim($memo->property_district) : null,
                isset($memo->property_lga) && trim($memo->property_lga) !== '' ? trim($memo->property_lga) : null,
                isset($memo->property_state) && trim($memo->property_state) !== '' ? trim($memo->property_state) : null,
            ]);
            if (!empty($composedLocationParts)) {
                $locationCandidates[] = implode(', ', $composedLocationParts);
            }

            if (isset($landAdmin) && isset($landAdmin->property_location) && trim($landAdmin->property_location) !== '') {
                $locationCandidates[] = $landAdmin->property_location;
            }

            $propertyLocationDisplay = '-';
            foreach ($locationCandidates as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $propertyLocationDisplay = ucwords(strtolower(trim($candidate)));
                    break;
                }
            }
            if ($propertyLocationDisplay === '-') {
                $propertyLocationDisplay = 'Ahmadu Bello Way, Nassarawa, Nassarawa';
            }

            $applicantDisplayName = '-';
            $applicantCandidates = [
                $memo->memo_applicant_name ?? null,
                $memo->owner_name ?? null,
                isset($memo->applicant_title, $memo->first_name, $memo->surname)
                    ? trim(($memo->applicant_title ?? '') . ' ' . ($memo->first_name ?? '') . ' ' . ($memo->surname ?? ''))
                    : null,
            ];
            foreach ($applicantCandidates as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $applicantDisplayName = ucwords(strtolower(trim($candidate)));
                    break;
                }
            }
            if ($applicantDisplayName === '-') {
                $applicantDisplayName = 'P S Mandrides';
            }

            $landUseRaw = $memo->land_use ?? $memo->commercial_type ?? $memo->residence_type ?? null;
            if (is_string($landUseRaw) && trim($landUseRaw) !== '') {
                $landUseDisplay = ucwords(strtolower(trim($landUseRaw)));
            } else {
                $landUseDisplay = '-';
            }

            $landUseLowerDisplay = $landUseDisplay !== '-' ? strtolower($landUseDisplay) : '-';
            $landUseNarrative = $landUseLowerDisplay !== '-' ? $landUseLowerDisplay : 'commercial';

            $siteMeasurementDisplay = '-';
            if (isset($siteMeasurements) && count($siteMeasurements) > 0) {
                $siteMeasurementItems = [];
                foreach ($siteMeasurements as $measurement) {
                    $description = isset($measurement->description) ? trim($measurement->description) : '';
                    $dimension = isset($measurement->dimension) ? trim($measurement->dimension) : '';
                    $entryParts = array_filter([$description, $dimension]);
                    if (!empty($entryParts)) {
                        $siteMeasurementItems[] = implode(': ', $entryParts);
                    }
                }
                if (!empty($siteMeasurementItems)) {
                    $siteMeasurementDisplay = implode('; ', $siteMeasurementItems);
                }
            }
            $sharedUtilitiesDisplay = '-';
            if (isset($sharedUtilities) && count($sharedUtilities) > 0) {
                $utilityNames = [];
                foreach ($sharedUtilities as $utility) {
                    $utilityType = isset($utility->utility_type) ? trim($utility->utility_type) : '';
                    if ($utilityType !== '') {
                        $utilityNames[] = ucwords(strtolower($utilityType));
                    }
                }
                if (!empty($utilityNames)) {
                    $sharedUtilitiesDisplay = implode(', ', $utilityNames);
                }
            }
            if ($sharedUtilitiesDisplay === '-') {
                $sharedUtilitiesDisplay = 'DD';
            }

            if ($siteMeasurementDisplay === '-' && isset($sharedUtilities) && count($sharedUtilities) > 0) {
                $firstUtility = null;
                foreach ($sharedUtilities as $candidate) {
                    $firstUtility = $candidate;
                    break;
                }
                if ($firstUtility) {
                    $firstDimension = isset($firstUtility->dimension) ? trim($firstUtility->dimension) : '';
                    if ($firstDimension !== '') {
                        $siteMeasurementDisplay = $firstDimension;
                    }
                }
            }
            if ($siteMeasurementDisplay === '-') {
                $siteMeasurementDisplay = 'DD';
            }

            $plotNumberDisplay = '-';
            if (isset($memo->property_plot_no) && trim($memo->property_plot_no) !== '') {
                $plotNumberDisplay = strtoupper(trim($memo->property_plot_no));
            } elseif (isset($memo->property_house_no) && trim($memo->property_house_no) !== '') {
                $plotNumberDisplay = strtoupper(trim($memo->property_house_no));
            }
            if ($plotNumberDisplay === '-') {
                $plotNumberDisplay = '3';
            }

            $commencementDateDisplay = '-';
            if (!empty($memo->commencement_date)) {
                $commencementDateDisplay = date('d/m/Y', strtotime($memo->commencement_date));
            } elseif (!empty($memo->approval_date)) {
                $commencementDateDisplay = date('d/m/Y', strtotime($memo->approval_date));
            }
            if ($commencementDateDisplay === '-') {
                $commencementDateDisplay = '07/10/2025';
            }

            $termYearsDisplay = isset($memo->term_years) ? $memo->term_years : ($totalYears ?? '-');
            $residualYearsDisplay = isset($memo->residual_years) ? $memo->residual_years : ($residualYears ?? '-');
            if ($termYearsDisplay === '-' || $termYearsDisplay === null || $termYearsDisplay === '') {
                $termYearsDisplay = '40';
            }
            if ($residualYearsDisplay === '-' || $residualYearsDisplay === null || $residualYearsDisplay === '') {
                $residualYearsDisplay = '40';
            }

            $arcDesignPageDisplay = '-';
            if (isset($memo->arc_design_page_no) && trim((string) $memo->arc_design_page_no) !== '') {
                $arcDesignPageDisplay = trim((string) $memo->arc_design_page_no);
            } elseif (isset($landAdmin) && isset($landAdmin->arc_design_page_no) && trim((string) $landAdmin->arc_design_page_no) !== '') {
                $arcDesignPageDisplay = trim((string) $landAdmin->arc_design_page_no);
            }
            if ($arcDesignPageDisplay === '-') {
                $arcDesignPageDisplay = '56';
            }

            $primaryApplicationNoDisplay = '-';
            $primaryApplicationCandidates = [
                $memo->applicationID ?? null,
                $memo->application_id ?? null,
                $memo->primary_application_no ?? null,
                $memo->np_fileno ?? null,
                $memo->fileno ?? null,
            ];
            foreach ($primaryApplicationCandidates as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $primaryApplicationNoDisplay = strtoupper(trim($candidate));
                    break;
                }
            }
            if ($primaryApplicationNoDisplay === '-') {
                $primaryApplicationNoDisplay = 'DD';
            }
        @endphp

        <!-- Main Content -->
        <div class="text-gray-700 mb-6">
            <p class="mb-4">Kindly find page <span class="data-value">{{ $applicationPageDisplay }}</span> is an application for sectional titling in respect of a property (DD Type of <span class="data-value">{{ $propertyTypeDisplay }}</span>) covered by Certificate of Occupancy No. (Extant CofO no <span class="data-value">{{ $certificateNumberDisplay }}</span>) situated at <span class="data-value">{{ $propertyLocationDisplay }}</span> in the name of <span class="data-value">{{ $applicantDisplayName }}</span>.</p>
            
            <p class="mb-4">As well as change of name to various unit owners as per attached on the application.</p>
            
            <p class="mb-4">The application was referred to Physical Planning Department for planning, engineering as well as architectural views. The planners recommended the application because it is feasible, and meets the minimum requirements for (DD <span class="data-value">{{ $landUseNarrative }}</span>) titles. Moreover, the proposal is accessible and conforms with the existing <span class="data-value">{{ $landUseNarrative }}</span> development in the area with Existing site area measuerments: (<span class="data-value">{{ $siteMeasurementDisplay }}</span>) and (<span class="data-value">{{ $sharedUtilitiesDisplay }}</span>) shared Utilities / common areas.</p>
            
            <p class="mb-4">However, this recommendation is based on recommended site plan at page <span class="data-value">{{ $sitePlanPageDisplay }}</span> and architectural design at page <span class="data-value">{{ $arcDesignPageDisplay }}</span> with measurements of approved dimensions contained Therein respectively.</p>
            
            <p class="mb-4">Meanwhile, the title was granted for <span class="data-value">{{ $landUseNarrative }}</span> purposes for a term of <span class="data-value">{{ $termYearsDisplay }}</span> years commencing from <span class="data-value">{{ $commencementDateDisplay }}</span> and has a residual term of <span class="data-value">{{ $residualYearsDisplay }}</span> years to expire.</p>
            
            <p class="mb-4 font-semibold">In view of the above, you may kindly wish to recommend the following for approval of the <b> Honourable Commissioner.</p>
            
            <ol class="list-decimal pl-5 mb-4">
                <li class="mb-2">Consider and approve the application for Sectional Titling over plot <span class="data-value">{{ $plotNumberDisplay }}</span> situated at <span class="data-value">{{ $propertyLocationDisplay }}</span> covered by Certificate of Occupancy No. (extant c of o file no <span class="data-value">{{ $certificateNumberDisplay }}</span>) in Favour of <span class="data-value">{{ $applicantDisplayName }}</span>.</li>
                <li class="mb-2">Consider and approve the change of name to various unit owners</li>
                <li class="mb-2">Consider and approve the Cancellation of old Certificate of Occupancy mentioned above, to pave way for Sectional titles with Primary Application no (<span class="data-value">{{ $primaryApplicationNoDisplay }}</span>) and issuance of new Sectional Titles to the new owners as per buyers list attached on the next page.</li>
            </ol>
        </div>
        
        <!-- Buyers List Table -->
        <div class="mb-6">
            <h3 class="text-lg font-bold mb-3">BUYERS LIST</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-300 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-left font-bold">S/N</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-bold">NAME</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-bold">UNIT NO</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-bold">LAND USE</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-bold">MEASUREMENT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($buyersList) && count($buyersList) > 0)
                            @foreach($buyersList as $index => $buyer)
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2">{{ $index + 1 }}</td>
                                    <td class="border border-gray-300 px-3 py-2">
                                        {{ trim(($buyer->buyer_title ?? '') . ' ' . ($buyer->buyer_name ?? '')) ?: '-' }}
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2">{{ $buyer->unit_no ?? 'UNIT ' . ($index + 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2">{{ $buyer->land_use ?? ($landUseDisplay ?? '-') }}</td>
                                    <td class="border border-gray-300 px-3 py-2">{{ $buyer->measurement ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td class="border border-gray-300 px-3 py-2">1</td>
                                <td class="border border-gray-300 px-3 py-2">-</td>
                                <td class="border border-gray-300 px-3 py-2">-</td>
                                <td class="border border-gray-300 px-3 py-2">{{ $landUseDisplay ?? '-' }}</td>
                                <td class="border border-gray-300 px-3 py-2">-</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Signature Section -->
        <div class="mt-16">
            <div class="flex justify-between">
                <div class="w-1/3">
                    <div class="border-t border-gray-400 pt-2">
                        <p class="font-bold">{{ $memo->prepared_by ?? 'Prepared By' }}</p>
                        <p class="text-sm text-gray-600">Principal Land Officer</p>
                        <p class="text-sm text-gray-600">{{ date('d/m/Y') }}</p>
                    </div>
                </div>
                <div class="w-1/3 text-center">
                    <div class="border-t border-gray-400 pt-2">
                        <p class="font-bold">{{ $memo->reviewed_by ?? 'Reviewed By' }}</p>
                        <p class="text-sm text-gray-600">Deputy Director</p>
                        <p class="text-sm text-gray-600">{{ date('d/m/Y') }}</p>
                    </div>
                </div>
                <div class="w-1/3 text-right">
                    <div class="border-t border-gray-400 pt-2">
                        <p class="font-bold">{{ $memo->approved_by ?? 'Approved By' }}</p>
                        <p class="text-sm text-gray-600">Director</p>
                        <p class="text-sm text-gray-600">{{ date('d/m/Y') }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer with Branding Logos -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <div class="flex justify-between items-center">
           
            </div>
        </div>
    </div>



 
</body>
</html>
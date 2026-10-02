@php
    $authUser = auth()->user();

    $generatedBy = trim(collect([
        optional($authUser)->first_name,
        optional($authUser)->last_name,
    ])->filter()->implode(' '));

    if ($generatedBy === '') {
        $generatedBy = optional($authUser)->name ?? 'System';
    }

    if ($generatedBy === '') {
        $generatedBy = 'System';
    }

    $generatedTimestamp = \Illuminate\Support\Carbon::now();
    $generatedAtDisplay = $generatedTimestamp->format('F j, Y \\a\\t g:i A');
    $generatedDateShort = $generatedTimestamp->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="en" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sectional Titling Memo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- DOCX Libraries -->
    <script src="https://unpkg.com/docx@8.2.2/build/index.umd.js"></script>
    <script src="https://unpkg.com/file-saver@2.0.5/dist/FileSaver.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 16px;
            line-height: 1.35;
        }
        
        .memo-container {
            width: 21cm;
            min-height: 29.7cm;
            background: white;
            margin: 0 auto;
            padding: 1.5cm;
            position: relative;
            box-sizing: border-box;
        }

        .logo-section {
            position: relative;
            margin-bottom: 60px;
        }

        .memo-header-wrap {
            position: relative;
            margin-bottom: 20px;
        }
        
        .header-line {
            height: 2px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
            margin-bottom: 15px;
        }
        
        .memo-header {
            text-align: right;
            margin-bottom: 16px;
        }
        
        .signature-line {
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            width: 160px;
            margin: 0 5px;
        }
        
        .short-line {
            width: 90px;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 11.5px;
            margin: 12px 0;
            page-break-inside: avoid;
        }
        
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 9px;
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        
        .signature-section {
            margin: 2px 0;
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 2px;
        }
        
        .signature-item {
            margin-bottom: 1px;
        }
        
        .approval-section {
            margin: 18px 0;
        }

        .approval-section.permanent-secretary-section {
            margin-top: 10px;
        }
        
        .title {
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .permanent-secretary-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 4px;
            gap: 4px;
        }

        .data-value {
            font-weight: 600;
            color: #111827;
        }
        
        /* Hide print button when printing */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                width: 21cm;
                height: 29.7cm;
                font-size: 12px;
                line-height: 1.25;
            }
            
           
            
            .no-print {
                display: none !important;
            }

            .memo-container {
                width: 100%;
                min-height: auto;
                height: auto;
                margin: 0;
                padding: 1cm;
                box-shadow: none;
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            
            @page {
                size: A4 portrait;
                margin: 0;
              
            }
            
            /* Ensure logos print correctly */
            img {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .memo-container p {
                margin-bottom: 6px;
            }

            .memo-container .header-line {
                margin-bottom: 10px;
            }

            .logo-section {
                margin-bottom: 25px;
            }

            .memo-header-wrap {
                margin-bottom: 10px;
            }

            .memo-container .approval-section {
                margin: 10px 0;
            }

            .memo-container .signature-grid {
                gap: 4px;
                margin-bottom: 10px;
            }

            .memo-container table {
                margin: 8px 0;
                font-size: 10px;
            }

            .memo-container table th,
            .memo-container table td {
                padding: 4px 5px;
            }

            .memo-container ol {
                margin-bottom: 6px;
                padding-left: 18px;
            }

            .memo-container ol li {
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body class="py-4" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
    <!-- Buttons: Print & Download DOCX -->
    <div class="no-print flex justify-center space-x-4 mb-4">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md transition duration-200 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print Document
        </button>

    <button type="button" onclick="exportToWord()" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md transition duration-200 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Download .docx
        </button>
    </div>

    <!-- Memo Container -->
    <div class="memo-container">
        <!-- Header with Logos -->
    <div class="logo-section">
            <!-- Left Ministry Logo -->
            {{-- <img src="{{ asset('images/ministry-logo-left.jpg') }}" alt="Ministry Logo" 
                 style="position: absolute; left: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'"> --}}
            
            <!-- Right Ministry Logo -->
            {{-- <img src="{{ asset('images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" 
                 style="position: absolute; right: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'">
             --}}




                     <img src="{{ asset('public/images/ministry-logo-left.jpg') }}" alt="Ministry Logo" 
                 style="position: absolute; left: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'">
            
            <!-- Right Ministry Logo -->
            <img src="{{ asset('public/images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" 
                 style="position: absolute; right: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'">  
            
           
        </div>
        

             <div class="memo-header-wrap">
          
            
            <!-- Header Content (centered) -->
            <div class="memo-header">
                <p class="font-bold text-right"><span class="data-value">{{ $memo->memo_no ?? '-' }}</span></p>
                <p class="font-bold text-left">PERMANENT SECRETARY,</p>
          
              
                 
            </div>

        </div>
        
      

        <!-- Main Content -->
        @php
            $propertyLocationText = null;

            $subapplicationLocationParts = [];

            if (!empty($suaApplication->block_number)) {
                $subapplicationLocationParts[] = 'Block ' . trim($suaApplication->block_number);
            }

            if (!empty($suaApplication->floor_number)) {
                $subapplicationLocationParts[] = 'Floor ' . trim($suaApplication->floor_number);
            }

            if (!empty($suaApplication->unit_number)) {
                $subapplicationLocationParts[] = 'Unit ' . trim($suaApplication->unit_number);
            }

            foreach (['unit_street_name', 'unit_estate', 'unit_district', 'unit_lga', 'unit_state'] as $subapplicationField) {
                if (!empty($suaApplication->{$subapplicationField})) {
                    $subapplicationLocationParts[] = trim($suaApplication->{$subapplicationField});
                }
            }

            $subapplicationLocationParts = array_values(array_unique(array_filter(array_map(static function ($value) {
                return trim(preg_replace('/\s+/', ' ', (string) $value));
            }, $subapplicationLocationParts), static function ($value) {
                return $value !== '';
            })));

            if (!empty($subapplicationLocationParts)) {
                $propertyLocationText = implode(', ', $subapplicationLocationParts);
            }

            if (empty($propertyLocationText) && !empty($memo->property_location)) {
                $propertyLocationText = trim($memo->property_location);
            } elseif (empty($propertyLocationText) && (!empty($memo->property_street_name) || !empty($memo->property_district) || !empty($memo->property_lga))) {
                $propertyLocationText = trim(preg_replace('/\s+/', ' ', trim(($memo->property_street_name ?? '') . ', ' . ($memo->property_district ?? '') . ', ' . ($memo->property_lga ?? ''), ', ')));
            }

            if (empty($propertyLocationText)) {
                $propertyLocationText = $memo->property_lga ?? ($suaApplication->unit_lga ?? $suaApplication->mother_lga ?? '-');
            }

            $ownerNamesList = isset($ownerNamesList) && is_array($ownerNamesList)
                ? array_values(array_filter(array_map('trim', $ownerNamesList)))
                : [];

            $applicantDisplayName = trim($memo->memo_applicant_name ?? $memo->owner_name ?? '');
            if ($applicantDisplayName === '' && count($ownerNamesList) > 0) {
                $applicantDisplayName = $ownerNamesList[0];
            }
            if ($applicantDisplayName === '') {
                $applicantDisplayName = '-';
            }

            $landUseRaw = $suaApplication->land_use
                ?? $memo->land_use
                ?? $suaApplication->unit_land_use
                ?? $suaApplication->land_use_type
                ?? null;

            if (is_string($landUseRaw)) {
                $landUseDisplay = ucwords(strtolower(trim($landUseRaw)));
            } else {
                $landUseDisplay = '-';
            }

            $allocationEntityRaw = $suaApplication->allocation_entity
                ?? $memo->allocation_entity
                ?? $suaApplication->allocation_source
                ?? null;

            if (is_string($allocationEntityRaw) && trim($allocationEntityRaw) !== '') {
                $allocationEntityDisplay = trim($allocationEntityRaw);
            } else {
                $allocationEntityDisplay = '-';
            }

            $allocationRefNoDisplay = '-';
            if (isset($memo->allocation_ref_no) && is_string($memo->allocation_ref_no) && trim($memo->allocation_ref_no) !== '') {
                $allocationRefNoDisplay = strtoupper(trim($memo->allocation_ref_no));
            } elseif (isset($suaApplication->allocation_ref_no) && is_string($suaApplication->allocation_ref_no) && trim($suaApplication->allocation_ref_no) !== '') {
                $allocationRefNoDisplay = strtoupper(trim($suaApplication->allocation_ref_no));
            }

            $sharedUtilityRecord = $sharedUtility ?? null;

            if (!$sharedUtilityRecord && isset($suaApplication->id)) {
                $sharedUtilityRecord = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->select('utility_type', 'dimension')
                    ->where('sub_application_id', $suaApplication->id)
                    ->first();
            }

            $sharedUtilityDimension = null;
            $sharedUtilityType = null;

            if ($sharedUtilityRecord) {
                if (!empty($sharedUtilityRecord->dimension)) {
                    $sharedUtilityDimension = trim($sharedUtilityRecord->dimension);
                }

                if (!empty($sharedUtilityRecord->utility_type)) {
                    $sharedUtilityType = trim($sharedUtilityRecord->utility_type);
                }
            }

            $sharedUtilityDimensionDisplay = $sharedUtilityDimension ?? ($suaApplication->unit_size ?? '-');
            $sharedUtilityTypeDisplay = $sharedUtilityType ?? '-';
            // Get prevailing_land_use from joint_site_inspection_reports table using sub_application_id (unit_id)
            $prevailingLandUse = null;
            
            // Try to get unit_id from various sources
            $unitId = $suaApplication->id ?? $suaApplication->sub_application_id ?? $suaApplication->unit_id ?? null;
            
            if ($unitId) {
                try {
                    $jointSiteInspectionData = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                        ->table('joint_site_inspection_reports')
                        ->where('sub_application_id', $unitId)
                        ->select('prevailing_land_use')
                        ->first();
                    
                    if ($jointSiteInspectionData && !empty($jointSiteInspectionData->prevailing_land_use)) {
                        $prevailingLandUse = trim($jointSiteInspectionData->prevailing_land_use);
                    }
                } catch (\Exception $e) {
                    // Log error but continue - fallback to existing data
                    \Log::warning('Failed to fetch prevailing_land_use from joint_site_inspection_reports for unit: ' . $e->getMessage());
                }
            }

            // Use the fetched prevailing_land_use or fallback to existing memo data
            $memo->prevailing_land_use = $prevailingLandUse ?? ($memo->prevailing_land_use ?? null);

            $jointSiteInspection = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('joint_site_inspection_reports')
                ->select('shared_utilities', 'existing_site_measurement_summary')
                ->where('sub_application_id', $suaApplication->id ?? null)
                ->orderByDesc('id')
                ->first();

            $existingSiteMeasurementSummary = '-';
            if ($jointSiteInspection && isset($jointSiteInspection->existing_site_measurement_summary)) {
                $summaryCandidate = trim((string) $jointSiteInspection->existing_site_measurement_summary);
                if ($summaryCandidate !== '') {
                    $existingSiteMeasurementSummary = $summaryCandidate;
                }
            }

            $sharedUtilitiesDisplay = '-';
            if ($jointSiteInspection && isset($jointSiteInspection->shared_utilities)) {
                $rawSharedUtilities = $jointSiteInspection->shared_utilities;
                $sharedUtilitiesArray = [];

                if (is_string($rawSharedUtilities)) {
                    $decoded = json_decode($rawSharedUtilities, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $sharedUtilitiesArray = $decoded;
                    } else {
                        $sharedUtilitiesArray = preg_split('/[\r\n,;]+/', $rawSharedUtilities, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    }
                } elseif (is_array($rawSharedUtilities)) {
                    $sharedUtilitiesArray = $rawSharedUtilities;
                } elseif (is_object($rawSharedUtilities) && $rawSharedUtilities instanceof \Traversable) {
                    $sharedUtilitiesArray = iterator_to_array($rawSharedUtilities);
                }

                if (!empty($sharedUtilitiesArray)) {
                    $sharedUtilitiesClean = collect($sharedUtilitiesArray)
                        ->map(function ($value) {
                            if (is_string($value) || is_numeric($value)) {
                                $formatted = trim((string) $value);
                                if ($formatted !== '') {
                                    return ucfirst(strtolower($formatted));
                                }
                            }
                            return null;
                        })
                        ->filter()
                        ->unique()
                        ->values();

                    if ($sharedUtilitiesClean->count() > 0) {
                        $sharedUtilitiesDisplay = $sharedUtilitiesClean->implode(', ');
                    }
                }
            }

            if ($existingSiteMeasurementSummary === '-' && isset($sharedUtilityDimensionDisplay) && $sharedUtilityDimensionDisplay !== '-') {
                $existingSiteMeasurementSummary = $sharedUtilityDimensionDisplay;
            }

            if ($sharedUtilitiesDisplay === '-' && isset($sharedUtilityTypeDisplay) && $sharedUtilityTypeDisplay !== '-') {
                $sharedUtilitiesDisplay = $sharedUtilityTypeDisplay;
            }
        @endphp

        <div class="text-gray-700 mb-6">
            <p class="mb-4">At page <span class="data-value">{{ $memo->page_no ?? ($landAdmin->page_no ?? '-') }}</span> is an application for <span class="data-value">{{ strtoupper($landUseDisplay ?? '-') }}</span> sectional title in respect of a property,  covered by <span class="data-value">{{ $allocationEntityDisplay }}</span> reference No <span class="data-value">{{ $allocationRefNoDisplay }}</span> situated at <span class="data-value">{{ ucwords(strtolower($propertyLocationText)) }}</span> in the name of <span class="data-value">{{ ucwords(strtolower($applicantDisplayName)) }}</span>.</p>
            
            <p class="mb-4">As well as change of name for the unit owner as per attached on the application.</p>
            
            <p class="mb-4">The application was referred to One stop shop for planning, engineering as well as architectural views. The planners recommended the application because it is feasible, and the unit meets the minimum requirements for <span class="data-value">{{ strtoupper ($landUseDisplay ?? '-') }}</span> title with Unit area measurement: <span class="data-value">{{ $existingSiteMeasurementSummary }}</span> and <span class="data-value">{{ $sharedUtilitiesDisplay }}</span> as shared utilities/common areas.</p>
            
            <p class="mb-4">However, this recommendation is based on recommended site plan at page <span class="data-value">{{ $memo->site_plan_no ?? ($landAdmin->site_plan_page_no ?? '-') }}</span> and architectural design at page <span class="data-value">{{ $memo->arc_design_page_no ?? ($landAdmin->arc_design_page_no ?? '-') }}</span> with measurements of approved dimensions contained Therein respectively.</p>

            <p class="mb-4">Meanwhile, the title was granted for <span class="data-value">{{ strtoupper(trim($memo->prevailing_land_use ?? '')) ?: '-' }}</span> purposes for a term of <span class="data-value">{{ $memo->term_years ?? ($totalYears ?? '-') }}</span> years commencing from <span class="data-value">{{ $memo->commencement_date ? date('d/m/Y', strtotime($memo->commencement_date)) : ($memo->approval_date ? date('d/m/Y', strtotime($memo->approval_date)) : '18/10/2025') }}</span> and has a residual term of <span class="data-value">{{ $memo->residual_years ?? ($residualYears ?? '-') }}</span> years to expire.</p>

            <p class="mb-4 font-semibold">In view of the above, you may kindly wish to recommend the following for approval of the Honourable Commissioner.</p>
            
            <ol class="list-decimal pl-5 mb-4">
                <li class="mb-2">Consider and approve the application for Sectional Titling over a standalone <span class="data-value">{{ strtoupper($suaApplication->land_use ?? $landUseDisplay) }}</span> unit situated at <span class="data-value">{{ ucwords(strtolower($propertyLocationText)) }}</span> covered by ownership document from <span class="data-value">{{ $allocationEntityDisplay }}</span> with reference No. <span class="data-value">{{ $allocationRefNoDisplay }}</span> in Favour of <span class="data-value">{{ ucwords(strtolower($applicantDisplayName)) }}</span>.</li>

                <li class="mb-2">Consider and approve the change of name to the new owner <span class="data-value">{{ ucwords(strtolower($applicantDisplayName)) }}</span> of the stand-alone unit.</li>

                <li class="mb-2">Consider And Approve the Cancellation of the Allocation Slip From <span class="data-value">{{ $allocationEntityDisplay }}</span> To Pave Way For A New Unit(S) of Stand-Alone Sectional Title With File No <span class="data-value">{{ $suaApplication->fileno ??  '-' }}</span>.</li>
            </ol>

            <div class="overflow-x-auto mb-6">
                <table>
                    <thead>
                        <tr>
                            <th>SN</th>
                            <th>UNIT OWNER</th>
                            <th>UNIT NUMBER</th>
                            <th>MEASUREMENT (M²)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($buyersList) && count($buyersList) > 0)
                            @foreach($buyersList as $index => $buyer)
                                @php
                                    $buyerTitle = data_get($buyer, 'buyer_title');
                                    if (!empty($buyerTitle)) {
                                        $buyerTitle = strtoupper($buyerTitle);
                                    }
                                    $buyerName = data_get($buyer, 'buyer_name');
                                    $ownerFallback = data_get($buyer, 'owner_name');

                                    $unitOwnerName = trim(implode(' ', array_filter([
                                        $buyerTitle,
                                        $buyerName,
                                    ])));

                                    if ($unitOwnerName === '' && !empty($ownerFallback)) {
                                        $unitOwnerName = trim($ownerFallback);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->memo_applicant_name)) {
                                        $unitOwnerName = trim($memo->memo_applicant_name);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->owner_name)) {
                                        $unitOwnerName = trim($memo->owner_name);
                                    }

                                    if ($unitOwnerName === '' && isset($ownerNamesList[$index])) {
                                        $unitOwnerName = $ownerNamesList[$index];
                                    }

                                    if ($unitOwnerName === '' && count($ownerNamesList) > 0) {
                                        $unitOwnerName = $ownerNamesList[0];
                                    }

                                    if ($unitOwnerName === '') {
                                        $unitOwnerName = 'Applicant Name Not Provided';
                                    }
                                @endphp
                                @php
                                    $unitNumber = data_get($buyer, 'unit_no') ?? data_get($buyer, 'unit_number') ?? '-';
                                    $measurement = data_get($buyer, 'measurement') ?? data_get($buyer, 'plot_size') ?? '-';
                                @endphp
                                <tr>
                                    <td class="data-value">{{ $index + 1 }}.</td>
                                    <td class="data-value">{{ $unitOwnerName }}</td>
                                    <td class="data-value">{{ $unitNumber }}</td>
                                    <td class="data-value">{{ $measurement }}</td>
                                </tr>
                            @endforeach
                        @elseif(isset($memo->buyers) && is_array($memo->buyers) && count($memo->buyers) > 0)
                            @foreach($memo->buyers as $index => $buyer)
                                @php
                                    $buyerTitle = data_get($buyer, 'buyer_title');
                                    if (!empty($buyerTitle)) {
                                        $buyerTitle = strtoupper($buyerTitle);
                                    }
                                    $buyerName = data_get($buyer, 'buyer_name');
                                    $unitOwnerField = data_get($buyer, 'unit_owner');

                                    $unitOwnerName = trim(implode(' ', array_filter([
                                        $buyerTitle,
                                        $buyerName,
                                    ])));

                                    if ($unitOwnerName === '' && !empty($unitOwnerField)) {
                                        $unitOwnerName = trim($unitOwnerField);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->memo_applicant_name)) {
                                        $unitOwnerName = trim($memo->memo_applicant_name);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->owner_name)) {
                                        $unitOwnerName = trim($memo->owner_name);
                                    }

                                    if ($unitOwnerName === '' && isset($ownerNamesList[$index])) {
                                        $unitOwnerName = $ownerNamesList[$index];
                                    }

                                    if ($unitOwnerName === '' && count($ownerNamesList) > 0) {
                                        $unitOwnerName = $ownerNamesList[0];
                                    }

                                    if ($unitOwnerName === '') {
                                        $unitOwnerName = 'Applicant Name Not Provided';
                                    }
                                @endphp
                                @php
                                    $unitNumber = data_get($buyer, 'unit_number') ?? '-';
                                    $measurement = data_get($buyer, 'measurement') ?? data_get($buyer, 'plot_size') ?? '-';
                                @endphp
                                <tr>
                                    <td class="data-value">{{ $index + 1 }}.</td>
                                    <td class="data-value">{{ $unitOwnerName }}</td>
                                    <td class="data-value">{{ $unitNumber }}</td>
                                    <td class="data-value">{{ $measurement }}</td>
                                </tr>
                            @endforeach
                        @elseif(count($ownerNamesList) > 0)
                            @foreach($ownerNamesList as $index => $owner)
                                <tr>
                                    <td class="data-value">{{ $index + 1 }}.</td>
                                    <td class="data-value">{{ $owner }}</td>
                                    <td class="data-value">{{ $suaApplication->unit_number ?? '-' }}</td>
                                    <td class="data-value">{{ $suaApplication->unit_size ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td class="data-value">1.</td>
                                <td class="data-value">{{ $applicantDisplayName }}</td>
                                <td class="data-value">{{ $suaApplication->unit_number ?? '-' }}</td>
                                <td class="data-value">{{ $suaApplication->unit_size ?? '-' }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Corrected Signatures Section -->
        <div class="signature-grid">
            <div>
                <div class="signature-item">
                    <p>Name: - <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p>Rank: <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p>Sign: <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p>Date: <span class="signature-line short-line"></span></p>
                </div>
            </div>
            <div>
                <div class="signature-item">
                    <p>Counter Sign: - <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p><span class="data-value">{{ $memo->director_rank ?? 'Director Sectional Titling' }}</span></p>
                </div>
                <div class="signature-item">
                    <p>Date: <span class="signature-line short-line"></span></p>
                </div>
            </div>
        </div>

    <!-- Permanent Secretary Section - Corrected -->
    <div class="approval-section permanent-secretary-section">
            <div class="title">PERMANENT SECRETARY</div>
            <div class="permanent-secretary-line">
                <p>The application is hereby recommended for your kind approval, please. </p>
                <span class="signature-line"></span>
            </div>
            <div class="permanent-secretary-line">
                <p>Date: <span class="signature-line short-line"></span> 2025.</p>

                <span class="font-semibold">Permanent Secretary</span>
            </div>
        </div>
        
        <!-- Honourable Commissioner Section  -->
        <div class="approval-section">
            <div class="title">HONOURABLE COMMISSIONER</div>
            <div class="permanent-secretary-line">
                <p>The application is hereby APPROVED/NOT APPROVED. </p>
                <span class="signature-line"></span>
            </div>
            <div class="permanent-secretary-line">
                <p>Date: <span class="signature-line short-line"></span> 2025.</p>

                <span class="font-semibold">Honourable Commissioner</span>
            </div>
        </div>
    <div class="absolute inset-x-0 text-center text-xs text-gray-500" style="bottom: 1cm; padding: 0 1cm; z-index: 15;">
                        <p>Generated on: {{ $generatedDateShort }} by {{ $generatedBy }}</p>
        </div>
    </div>

    @php
        $exportBuyers = [];

        if (isset($buyersList) && count($buyersList) > 0) {
            foreach ($buyersList as $index => $buyer) {
                $buyerTitle = data_get($buyer, 'buyer_title');
                if (!empty($buyerTitle)) {
                    $buyerTitle = strtoupper($buyerTitle);
                }
                $buyerName = data_get($buyer, 'buyer_name');
                $ownerFallback = data_get($buyer, 'owner_name');

                $unitOwnerName = trim(implode(' ', array_filter([
                    $buyerTitle,
                    $buyerName,
                ])));

                if ($unitOwnerName === '' && !empty($ownerFallback)) {
                    $unitOwnerName = trim($ownerFallback);
                }

                if ($unitOwnerName === '' && !empty($memo->memo_applicant_name)) {
                    $unitOwnerName = trim($memo->memo_applicant_name);
                }

                if ($unitOwnerName === '' && !empty($memo->owner_name)) {
                    $unitOwnerName = trim($memo->owner_name);
                }

                if ($unitOwnerName === '' && isset($ownerNamesList[$index])) {
                    $unitOwnerName = $ownerNamesList[$index];
                }

                if ($unitOwnerName === '' && count($ownerNamesList) > 0) {
                    $unitOwnerName = $ownerNamesList[0];
                }

                if ($unitOwnerName === '') {
                    $unitOwnerName = 'Applicant Name Not Provided';
                }

                $unitNumberValue = data_get($buyer, 'unit_no') ?? data_get($buyer, 'unit_number') ?? '-';
                $measurementValue = data_get($buyer, 'measurement') ?? data_get($buyer, 'plot_size') ?? '-';

                $exportBuyers[] = [
                    'sn' => (string) ($index + 1),
                    'name' => $unitOwnerName,
                    'unitNo' => $unitNumberValue,
                    'measurement' => $measurementValue,
                ];
            }
        } elseif (isset($memo->buyers) && is_array($memo->buyers) && count($memo->buyers) > 0) {
            foreach ($memo->buyers as $index => $buyer) {
                $buyerTitle = data_get($buyer, 'buyer_title');
                if (!empty($buyerTitle)) {
                    $buyerTitle = strtoupper($buyerTitle);
                }
                $buyerName = data_get($buyer, 'buyer_name');
                $unitOwnerField = data_get($buyer, 'unit_owner');

                $unitOwnerName = trim(implode(' ', array_filter([
                    $buyerTitle,
                    $buyerName,
                ])));

                if ($unitOwnerName === '' && !empty($unitOwnerField)) {
                    $unitOwnerName = trim($unitOwnerField);
                }

                if ($unitOwnerName === '' && !empty($memo->memo_applicant_name)) {
                    $unitOwnerName = trim($memo->memo_applicant_name);
                }

                if ($unitOwnerName === '' && !empty($memo->owner_name)) {
                    $unitOwnerName = trim($memo->owner_name);
                }

                if ($unitOwnerName === '' && isset($ownerNamesList[$index])) {
                    $unitOwnerName = $ownerNamesList[$index];
                }

                if ($unitOwnerName === '' && count($ownerNamesList) > 0) {
                    $unitOwnerName = $ownerNamesList[0];
                }

                if ($unitOwnerName === '') {
                    $unitOwnerName = 'Applicant Name Not Provided';
                }

                $exportBuyers[] = [
                    'sn' => (string) ($index + 1),
                    'name' => $unitOwnerName,
                    'unitNo' => data_get($buyer, 'unit_number') ?? '-',
                    'measurement' => data_get($buyer, 'measurement') ?? data_get($buyer, 'plot_size') ?? '-',
                ];
            }
        } elseif (count($ownerNamesList) > 0) {
            foreach ($ownerNamesList as $index => $ownerName) {
                $exportBuyers[] = [
                    'sn' => (string) ($index + 1),
                    'name' => $ownerName,
                    'unitNo' => $suaApplication->unit_number ?? '-',
                    'measurement' => $suaApplication->unit_size ?? '-',
                ];
            }
        } else {
            $exportBuyers[] = [
                'sn' => '1',
                'name' => $applicantDisplayName,
                'unitNo' => $suaApplication->unit_number ?? '-',
                'measurement' => $suaApplication->unit_size ?? '-',
            ];
        }
    @endphp

    <script>
        (function syncPrintMeta() {
            const meta = {
                date: @json($generatedAtDisplay),
                user: @json($generatedBy),
            };

            [document.documentElement, document.body, ...document.querySelectorAll('.memo-container')]
                .forEach((el) => {
                    if (!el) {
                        return;
                    }

                    el.setAttribute('data-date', meta.date);
                    el.setAttribute('data-user', meta.user);
                });
        })();

        const leftLogoUrl = @json(asset('public/images/ministry-logo-left.jpg'));
        const rightLogoUrl = @json(asset('public/images/ministry-logo-right.jpeg'));

        async function fetchLogoArrayBuffer(url) {
            if (!url) {
                return null;
            }

            try {
                const response = await fetch(url, { cache: 'force-cache' });
                if (!response.ok) {
                    throw new Error(`Failed to fetch logo: ${response.status}`);
                }
                return await response.arrayBuffer();
            } catch (error) {
                console.warn('Logo load failed for', url, error);
                return null;
            }
        }

        async function exportToWord() {
            try {
                if (!window.docx) {
                    throw new Error('docx library is unavailable.');
                }

                const {
                    Document,
                    Packer,
                    Paragraph,
                    TextRun,
                    Table,
                    TableRow,
                    TableCell,
                    AlignmentType,
                    WidthType,
                    Footer,
                    ImageRun,
                    BorderStyle,
                } = docx;

                const tableBordersNone = {
                    top: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    bottom: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    left: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    right: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    insideHorizontal: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    insideVertical: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                };

                const tableCellMargins = { top: 80, bottom: 80, left: 80, right: 80 };

                const fontSizes = {
                    memoNumber: 24,
                    heading: 24,
                    body: 18,
                    bullet: 22,
                    tableHeader: 24,
                    tableBody: 22,
                };

                const [leftLogoData, rightLogoData] = await Promise.all([
                    fetchLogoArrayBuffer(leftLogoUrl),
                    fetchLogoArrayBuffer(rightLogoUrl),
                ]);

                const memoData = {
                    memoNo: '{{ $memo->memo_no ?? '-' }}',
                    pageNo: '{{ $memo->page_no ?? ($landAdmin->page_no ?? '-') }}',
                    landUse: '{{ strtoupper($landUseDisplay ?? '-') }}',
                    propertyLocation: '{{ ucwords(strtolower($propertyLocationText)) }}',
                    allocationEntity: '{{ $allocationEntityDisplay }}',
                    allocationRefNo: '{{ $allocationRefNoDisplay }}',
                    applicantName: '{{ ucwords(strtolower($applicantDisplayName)) }}',
                    sitePlanNo: '{{ $memo->site_plan_no ?? ($landAdmin->site_plan_page_no ?? '-') }}',
                    arcDesignPageNo: '{{ $memo->arc_design_page_no ?? ($landAdmin->arc_design_page_no ?? '-') }}',
                    existingMeasurement: '{{ $existingSiteMeasurementSummary }}',
                    sharedUtilities: '{{ $sharedUtilitiesDisplay }}',
                    prevailingLandUse: '{{ strtoupper(trim($memo->prevailing_land_use ?? '')) ?: '-' }}',
                    termYears: '{{ $memo->term_years ?? ($totalYears ?? '-') }}',
                    commencementDate: '{{ $memo->commencement_date ? date('d/m/Y', strtotime($memo->commencement_date)) : ($memo->approval_date ? date('d/m/Y', strtotime($memo->approval_date)) : '18/10/2025') }}',
                    residualYears: '{{ $memo->residual_years ?? ($residualYears ?? '-') }}',
                    directorRank: '{{ $memo->director_rank ?? 'Director Sectional Titling' }}',
                    suaFileNumber: '{{ $suaApplication->fileno ?? '-' }}',
                    generatedBy: '{{ $generatedBy }}',
                    generatedDate: '{{ $generatedAtDisplay }}',
                };

                const buyersData = @json($exportBuyers);
                const signatureTable = new Table({
                    width: { size: 100, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    rows: [
                        new TableRow({
                            children: [
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Name: - ____________________________', size: fontSizes.body })],
                                            spacing: { after: 50 },
                                        }),
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Rank: ____________________________', size: fontSizes.body })],
                                            spacing: { after: 50 },
                                        }),
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Sign: ____________________________', size: fontSizes.body })],
                                            spacing: { after: 50 },
                                        }),
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Date: _______________', size: fontSizes.body })],
                                        }),
                                    ],
                                }),
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Counter Sign: - ____________________________', size: fontSizes.body })],
                                            spacing: { after: 50 },
                                        }),
                                        new Paragraph({
                                            children: [new TextRun({ text: memoData.directorRank, size: fontSizes.body })],
                                            spacing: { after: 50 },
                                        }),
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Date: _______________', size: fontSizes.body })],
                                        }),
                                    ],
                                }),
                            ],
                        }),
                    ],
                });

                const permanentSecretaryTable = new Table({
                    width: { size: 100, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    rows: [
                        new TableRow({
                            children: [
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [
                                                new TextRun({
                                                    text: 'The application is hereby recommended for your kind approval, please.',
                                                    size: fontSizes.body,
                                                }),
                                            ],
                                        }),
                                    ],
                                }),
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [new TextRun({ text: '__________________________', size: fontSizes.body })],
                                            alignment: AlignmentType.RIGHT,
                                            spacing: { after: 50 },
                                        }),
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Permanent Secretary', size: fontSizes.body, bold: true })],
                                            alignment: AlignmentType.RIGHT,
                                        }),
                                    ],
                                }),
                            ],
                        }),
                        new TableRow({
                            children: [
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Date: ____________ 2025.', size: fontSizes.body })],
                                        }),
                                    ],
                                }),
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [new Paragraph({ children: [new TextRun({ text: '', size: fontSizes.body })] })],
                                }),
                            ],
                        }),
                    ],
                });

                const honourableCommissionerTable = new Table({
                    width: { size: 100, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    rows: [
                        new TableRow({
                            children: [
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [
                                                new TextRun({
                                                    text: 'The application is hereby APPROVED/NOT APPROVED.',
                                                    size: fontSizes.body,
                                                }),
                                            ],
                                        }),
                                    ],
                                }),
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [new TextRun({ text: '__________________________', size: fontSizes.body })],
                                            alignment: AlignmentType.RIGHT,
                                            spacing: { after: 50 },
                                        }),
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Honourable Commissioner', size: fontSizes.body, bold: true })],
                                            alignment: AlignmentType.RIGHT,
                                        }),
                                    ],
                                }),
                            ],
                        }),
                        new TableRow({
                            children: [
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [
                                        new Paragraph({
                                            children: [new TextRun({ text: 'Date: ____________ 2025.', size: fontSizes.body })],
                                        }),
                                    ],
                                }),
                                new TableCell({
                                    borders: tableBordersNone,
                                    margins: tableCellMargins,
                                    children: [new Paragraph({ children: [new TextRun({ text: '', size: fontSizes.body })] })],
                                }),
                            ],
                        }),
                    ],
                });

                const sectionChildren = [
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: memoData.memoNo,
                                bold: true,
                                size: fontSizes.memoNumber,
                            }),
                        ],
                        alignment: AlignmentType.RIGHT,
                        spacing: { after: 160 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: 'PERMANENT SECRETARY,',
                                bold: true,
                                size: fontSizes.heading,
                            }),
                        ],
                        spacing: { after: 240 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `At page ${memoData.pageNo} is an application for ${memoData.landUse} sectional title in respect of a property, covered by ${memoData.allocationEntity} reference No ${memoData.allocationRefNo} situated at ${memoData.propertyLocation} in the name of ${memoData.applicantName}.`,
                                size: fontSizes.body,
                            }),
                        ],
                        spacing: { after: 220 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: 'As well as change of name for the unit owner as per attached on the application.',
                                size: fontSizes.body,
                            }),
                        ],
                        spacing: { after: 220 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `The application was referred to One stop shop for planning, engineering as well as architectural views. The planners recommended the application because it is feasible, and the unit meets the minimum requirements for ${memoData.landUse} title with Unit area measurement: ${memoData.existingMeasurement} and ${memoData.sharedUtilities} as shared utilities/common areas.`,
                                size: fontSizes.body,
                            }),
                        ],
                        spacing: { after: 220 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `However, this recommendation is based on recommended site plan at page ${memoData.sitePlanNo} and architectural design at page ${memoData.arcDesignPageNo} with measurements of approved dimensions contained therein respectively.`,
                                size: fontSizes.body,
                            }),
                        ],
                        spacing: { after: 220 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Meanwhile, the title was granted for ${memoData.prevailingLandUse} purposes for a term of ${memoData.termYears} years commencing from ${memoData.commencementDate} and has a residual term of ${memoData.residualYears} years to expire.`,
                                size: fontSizes.body,
                            }),
                        ],
                        spacing: { after: 220 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: 'In view of the above, you may kindly wish to recommend the following for approval of the Honourable Commissioner.',
                                bold: true,
                                size: fontSizes.heading,
                            }),
                        ],
                        spacing: { after: 260 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `1. Consider and approve the application for Sectional Titling over a standalone ${memoData.landUse} unit situated at ${memoData.propertyLocation} covered by ownership document from ${memoData.allocationEntity} with reference No. ${memoData.allocationRefNo} in favour of ${memoData.applicantName}.`,
                                size: fontSizes.bullet,
                            }),
                        ],
                        spacing: { after: 160 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `2. Consider and approve the change of name to the new owner ${memoData.applicantName} of the stand-alone unit.`,
                                size: fontSizes.bullet,
                            }),
                        ],
                        spacing: { after: 160 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `3. Consider and approve the cancellation of the allocation slip from ${memoData.allocationEntity} to pave way for a new unit(s) of stand-alone sectional title with file no ${memoData.suaFileNumber}.`,
                                size: fontSizes.bullet,
                            }),
                        ],
                        spacing: { after: 240 },
                    }),
                ];

                if (leftLogoData || rightLogoData) {
                    sectionChildren.unshift(
                        new Table({
                            width: { size: 100, type: WidthType.PERCENTAGE },
                            borders: tableBordersNone,
                            rows: [
                                new TableRow({
                                    children: [
                                        new TableCell({
                                            borders: tableBordersNone,
                                            width: { size: 50, type: WidthType.PERCENTAGE },
                                            children: [
                                                leftLogoData
                                                    ? new Paragraph({
                                                          alignment: AlignmentType.LEFT,
                                                          children: [
                                                              new ImageRun({
                                                                  data: leftLogoData,
                                                                  transformation: { width: 70, height: 70 },
                                                              }),
                                                          ],
                                                      })
                                                    : new Paragraph(''),
                                            ],
                                        }),
                                        new TableCell({
                                            borders: tableBordersNone,
                                            width: { size: 50, type: WidthType.PERCENTAGE },
                                            children: [
                                                rightLogoData
                                                    ? new Paragraph({
                                                          alignment: AlignmentType.RIGHT,
                                                          children: [
                                                              new ImageRun({
                                                                  data: rightLogoData,
                                                                  transformation: { width: 70, height: 70 },
                                                              }),
                                                          ],
                                                      })
                                                    : new Paragraph(''),
                                            ],
                                        }),
                                    ],
                                }),
                            ],
                        })
                    );
                }

                sectionChildren.push(
                    new Paragraph({
                        children: [
                            new TextRun({ text: 'UNIT OWNERS', bold: true, size: fontSizes.heading }),
                        ],
                        alignment: AlignmentType.CENTER,
                        spacing: { after: 160 },
                    })
                );

                if (buyersData.length > 0) {
                    const tableRows = [
                        new TableRow({
                            children: [
                                new TableCell({
                                    children: [
                                        new Paragraph({
                                            children: [new TextRun({ text: 'SN', bold: true, size: fontSizes.tableHeader })],
                                            alignment: AlignmentType.CENTER,
                                        }),
                                    ],
                                    width: { size: 10, type: WidthType.PERCENTAGE },
                                }),
                                new TableCell({
                                    children: [new Paragraph({ children: [new TextRun({ text: 'UNIT OWNER', bold: true, size: fontSizes.tableHeader })] })],
                                    width: { size: 40, type: WidthType.PERCENTAGE },
                                }),
                                new TableCell({
                                    children: [new Paragraph({ children: [new TextRun({ text: 'UNIT NUMBER', bold: true, size: fontSizes.tableHeader })] })],
                                    width: { size: 25, type: WidthType.PERCENTAGE },
                                }),
                                new TableCell({
                                    children: [new Paragraph({ children: [new TextRun({ text: 'MEASUREMENT (M²)', bold: true, size: fontSizes.tableHeader })] })],
                                    width: { size: 25, type: WidthType.PERCENTAGE },
                                }),
                            ],
                        }),
                    ];

                    buyersData.forEach((buyer) => {
                        tableRows.push(
                            new TableRow({
                                children: [
                                    new TableCell({
                                        children: [
                                            new Paragraph({
                                                children: [
                                                    new TextRun({
                                                        text: buyer.sn ? `${buyer.sn}.` : '',
                                                        size: fontSizes.tableBody,
                                                    }),
                                                ],
                                                alignment: AlignmentType.CENTER,
                                            }),
                                        ],
                                    }),
                                    new TableCell({
                                        children: [new Paragraph({ children: [new TextRun({ text: buyer.name, size: fontSizes.tableBody })] })],
                                    }),
                                    new TableCell({
                                        children: [new Paragraph({ children: [new TextRun({ text: buyer.unitNo, size: fontSizes.tableBody })] })],
                                    }),
                                    new TableCell({
                                        children: [new Paragraph({ children: [new TextRun({ text: buyer.measurement, size: fontSizes.tableBody })] })],
                                    }),
                                ],
                            })
                        );
                    });

                    sectionChildren.push(
                        new Table({
                            rows: tableRows,
                            width: { size: 100, type: WidthType.PERCENTAGE },
                        })
                    );
                } else {
                    sectionChildren.push(
                        new Paragraph({
                            children: [new TextRun({ text: 'No unit owner data available.', size: fontSizes.body })],
                            alignment: AlignmentType.CENTER,
                        })
                    );
                }

                sectionChildren.push(
                    signatureTable,
                    new Paragraph({
                        children: [new TextRun({ text: 'PERMANENT SECRETARY', bold: true, size: fontSizes.heading })],
                        spacing: { after: 160 },
                    }),
                    permanentSecretaryTable,
                    new Paragraph({
                        children: [new TextRun({ text: 'HONOURABLE COMMISSIONER', bold: true, size: fontSizes.heading })],
                        spacing: { after: 160 },
                    }),
                    honourableCommissionerTable,
                );

                const doc = new Document({
                    creator: 'KLAES GIS EDMS',
                    title: 'Standalone Sectional Titling Memo',
                    description: 'Official Standalone Unit Sectional Titling Memo Document',
                    sections: [
                        {
                            properties: {
                                page: {
                                    margin: {
                                        top: 720,
                                        right: 720,
                                        bottom: 720,
                                        left: 720,
                                    },
                                },
                            },
                            footers: {
                                default: new Footer({
                                    children: [
                                        new Paragraph({
                                            children: [
                                                new TextRun({
                                                    text: `Generated on: ${memoData.generatedDate} by ${memoData.generatedBy}`,
                                                    size: 20,
                                                    color: '666666',
                                                }),
                                            ],
                                            alignment: AlignmentType.CENTER,
                                        }),
                                    ],
                                }),
                            },
                            children: sectionChildren,
                        },
                    ],
                });

                const buffer = await Packer.toBlob(doc);
                const fileName = `Standalone_Unit_Sectional_Titling_Memo_${memoData.memoNo.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().split('T')[0]}.docx`;

                saveAs(buffer, fileName);
                alert('Word document exported successfully!');
            } catch (error) {
                console.error('Error generating Word document:', error);
                alert('Error generating Word document. Please try again.');
            }
        }
    </script>
</body>
</html>
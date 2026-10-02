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
@endphp
<!DOCTYPE html>
<html lang="en" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sectional Titling Memo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .memo-container {
            width: 21cm;
            height: 29.7cm;
            background: white;
            margin: 0 auto;
            padding: 1.5cm;
            position: relative;
        }
        
        .header-line {
            height: 2px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
            margin-bottom: 15px;
        }
        
        .memo-header {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .signature-line {
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            width: 200px;
            margin: 0 5px;
        }
        
        .short-line {
            width: 120px;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 12px;
            margin: 15px 0;
        }
        
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        
        .signature-section {
            margin: 1 0;
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 2px;
        }
        
        .signature-item {
            margin-bottom: 1px;
        }
        

         .approval-section1 {
            margin: 7px 0;
        }

        .approval-section {
            margin: 7px 0;
        }
        
        .title {
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .permanent-secretary-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 6px;
            gap: 5px;
        }

        .data-value {
            font-weight: 700;
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
            
            .memo-container {
                width: 100%;
                height: 100%;
                padding: 1.5cm;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* @page {
                size: A4 portrait;
                margin: 0;
            } */


                 @page {
                size: A4 portrait;
                    margin: 5%;
                    margin-right: 5%;
                    margin-left: 5%;
                @bottom-center {
                    content: "Generated on: " attr(data-date) " by " attr(data-user);
                    font-size: 10px;
                    color: #666;
                }
            }

            
            /* Ensure logos print correctly */
            img {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="py-4" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
    @php
        use Illuminate\Support\Str;

        $formatTitle = function ($value) {
            if ($value === null) {
                return null;
            }

            $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

            if ($normalized === '') {
                return null;
            }

            return Str::title(Str::lower($normalized));
        };

        $composeAddress = function (array $parts) {
            $filtered = array_filter($parts, function ($part) {
                return !is_null($part) && trim($part) !== '';
            });

            return $filtered ? implode(', ', $filtered) : null;
        };

        $formattedPropertyLocation = $formatTitle($memo->property_location ?? null);
        $formattedFallbackLocation = $formatTitle($composeAddress([
            $memo->property_street_name ?? null,
            $memo->property_district ?? null,
            $memo->property_lga ?? null,
        ]));
        $applicantName = $memo->memo_applicant_name ?? $memo->owner_name ?? null;

        $recordsForLookup = array_filter([
            $memo ?? null,
            $landAdmin ?? null,
        ]);

        $getFirstNonEmpty = function (array $records, array $fields) {
            foreach ($records as $record) {
                foreach ($fields as $field) {
                    if (!is_object($record) && !is_array($record)) {
                        continue;
                    }

                    $value = null;
                    if (is_object($record) && isset($record->{$field})) {
                        $value = $record->{$field};
                    } elseif (is_array($record) && array_key_exists($field, $record)) {
                        $value = $record[$field];
                    }

                    if (is_string($value) || is_numeric($value)) {
                        $candidate = trim((string) $value);
                        if ($candidate !== '') {
                            return $candidate;
                        }
                    }
                }
            }

            return null;
        };

        $selectedLandUseSubtype = $getFirstNonEmpty($recordsForLookup, [
            'commercial_type', 'commercialType', 'commercial_subtype', 'commercialSubtype',
            'residence_type', 'residenceType', 'residential_type', 'residentialType',
        ]);

        $propertyTypeDisplay = $formatTitle($selectedLandUseSubtype) ?? 'Mixed';

        $selectedLandUse = $getFirstNonEmpty($recordsForLookup, [
            'land_use', 'landUse', 'property_type', 'propertyType',
            'commercial_type', 'commercialType',
            'residence_type', 'residenceType',
        ]);

        $landUseNarrative = $selectedLandUse ? Str::lower(trim($selectedLandUse)) : 'mixed';

        $sharedUtilitiesCollection = isset($sharedUtilities)
            ? ($sharedUtilities instanceof \Illuminate\Support\Collection ? $sharedUtilities : collect($sharedUtilities))
            : collect();

        $sharedUtilityDimensions = $sharedUtilitiesCollection
            ->pluck('dimension')
            ->filter(function ($dimension) {
                return is_string($dimension) && trim($dimension) !== '';
            })
            ->map(function ($dimension) {
                return trim($dimension);
            })
            ->unique()
            ->values();

        $sharedUtilityTypes = $sharedUtilitiesCollection
            ->pluck('utility_type')
            ->filter(function ($type) {
                return is_string($type) && trim($type) !== '';
            })
            ->map(function ($type) use ($formatTitle) {
                $title = $formatTitle($type);
                return $title ?? null;
            })
            ->filter()
            ->unique()
            ->values();

        $sharedUtilityDimensionDisplay = $sharedUtilityDimensions->count() > 0
            ? $sharedUtilityDimensions->implode('; ')
            : '-';

        $sharedUtilityTypeDisplay = $sharedUtilityTypes->count() > 0
            ? $sharedUtilityTypes->implode(', ')
            : '-';

        if ($sharedUtilityDimensionDisplay === '-' && isset($siteMeasurements)) {
            $siteMeasurementsCollection = $siteMeasurements instanceof \Illuminate\Support\Collection
                ? $siteMeasurements
                : collect($siteMeasurements);

            $siteMeasurementFallback = $siteMeasurementsCollection
                ->map(function ($item) {
                    $description = isset($item->description) ? trim($item->description) : '';
                    $dimension = isset($item->dimension) ? trim($item->dimension) : '';
                    return trim(implode(': ', array_filter([$description, $dimension])));
                })
                ->filter()
                ->unique()
                ->values();

            if ($siteMeasurementFallback->count() > 0) {
                $sharedUtilityDimensionDisplay = $siteMeasurementFallback->implode('; ');
            }
        }

        $jointSiteInspection = isset($jointSiteInspectionReport) ? $jointSiteInspectionReport : null;

        // Get prevailing_land_use from joint_site_inspection_reports table
        $prevailingLandUse = null;
        
        // Try to get application_id from various sources
        $applicationId = $memo->id ?? $memo->application_id ?? $memo->applicationId ?? null;
        
        if ($applicationId) {
            try {
                $jointSiteInspectionData = DB::connection('sqlsrv')
                    ->table('joint_site_inspection_reports')
                    ->where('application_id', $applicationId)
                    ->select('prevailing_land_use')
                    ->first();
                
                if ($jointSiteInspectionData && !empty($jointSiteInspectionData->prevailing_land_use)) {
                    $prevailingLandUse = trim($jointSiteInspectionData->prevailing_land_use);
                }
            } catch (\Exception $e) {
                // Log error but continue - fallback to existing data
                \Log::warning('Failed to fetch prevailing_land_use from joint_site_inspection_reports: ' . $e->getMessage());
            }
        }

        // Use the fetched prevailing_land_use or fallback to existing memo data
        $memo->prevailing_land_use = $prevailingLandUse ?? ($memo->prevailing_land_use ?? null);

        $existingSiteMeasurementSummary = '-';
        $reportSharedUtilitiesDisplay = '-';

        if ($jointSiteInspection) {
            $summaryCandidate = $jointSiteInspection->existing_site_measurement_summary ?? null;
            if (is_string($summaryCandidate) || is_numeric($summaryCandidate)) {
                $trimmed = trim((string) $summaryCandidate);
                if ($trimmed !== '') {
                    $existingSiteMeasurementSummary = $trimmed;
                }
            }

            $sharedUtilitiesRaw = $jointSiteInspection->shared_utilities ?? null;
            $sharedUtilitiesArray = [];

            if (is_string($sharedUtilitiesRaw)) {
                $decoded = json_decode($sharedUtilitiesRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $sharedUtilitiesArray = $decoded;
                } else {
                    $sharedUtilitiesArray = preg_split('/[\r\n,;]+/', $sharedUtilitiesRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                }
            } elseif (is_array($sharedUtilitiesRaw)) {
                $sharedUtilitiesArray = $sharedUtilitiesRaw;
            } elseif (is_object($sharedUtilitiesRaw) && $sharedUtilitiesRaw instanceof \Traversable) {
                $sharedUtilitiesArray = iterator_to_array($sharedUtilitiesRaw);
            }

            if (!empty($sharedUtilitiesArray)) {
                $reportSharedUtilities = collect($sharedUtilitiesArray)
                    ->map(function ($item) use ($formatTitle) {
                        if (is_string($item) || is_numeric($item)) {
                            $value = trim((string) $item);
                            if ($value !== '') {
                                return $formatTitle($value);
                            }
                        }
                        return null;
                    })
                    ->filter()
                    ->unique()
                    ->values();

                if ($reportSharedUtilities->count() > 0) {
                    $reportSharedUtilitiesDisplay = $reportSharedUtilities->implode(', ');
                }
            }
        }

        $siteMeasurementSummaryDisplay = $existingSiteMeasurementSummary !== '-' ? $existingSiteMeasurementSummary : $sharedUtilityDimensionDisplay;
        $sharedUtilitiesSummaryDisplay = $reportSharedUtilitiesDisplay !== '-' ? $reportSharedUtilitiesDisplay : $sharedUtilityTypeDisplay;

        $primaryApplicationNumberCandidates = [
            $memo->np_fileno ?? null,
            $memo->new_primary_file_number ?? null,
            $memo->fileno ?? null,
            $memo->primary_application_no ?? null,
            $memo->primaryApplicationNo ?? null,
            $memo->applicationID ?? null,
            $memo->application_id ?? null,
        ];

        $primaryApplicationNumberDisplay = '-';
        foreach ($primaryApplicationNumberCandidates as $candidate) {
            if (is_string($candidate) || is_numeric($candidate)) {
                $trimmed = trim((string) $candidate);
                if ($trimmed !== '') {
                    $primaryApplicationNumberDisplay = $trimmed;
                    break;
                }
            }
        }

        $hasCofoRecord = $hasCofoRecord ?? false;

        $cofoNumberDisplay = isset($cofoNumberDisplay)
            ? trim((string) $cofoNumberDisplay)
            : null;
        if ($cofoNumberDisplay === '') {
            $cofoNumberDisplay = null;
        }

        $primaryFileNumberDisplay = isset($primaryFileNumberDisplay)
            ? trim((string) $primaryFileNumberDisplay)
            : null;
        if ($primaryFileNumberDisplay === null || $primaryFileNumberDisplay === '') {
            $primaryFileNumberDisplay = $primaryApplicationNumberDisplay;
        }
        if ($primaryFileNumberDisplay === null || $primaryFileNumberDisplay === '') {
            $primaryFileNumberDisplay = '-';
        }

        $legacyReferenceLabel = $hasCofoRecord ? 'Old Cofo File No.' : 'Old Statutory File No.';
        $legacyReferenceValue = $hasCofoRecord
            ? ($cofoNumberDisplay ?? '-')
            : ($primaryFileNumberDisplay ?? '-');

        // Page references are displayed directly from the memo record in the content section below.
    @endphp
    <div class="no-print flex justify-center mb-4">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md transition duration-200 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print Documcent
        </button>
    </div>

    <!-- Memo Container -->
    <div class="memo-container" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
        <!-- Header with Logos -->
        <div style="position: relative; margin-bottom: 60px;">
            <!-- Left Ministry Logo -->
            <img src="{{ asset('public/images/ministry-logo-left.jpg') }}" alt="Ministry Logo" 
                 style="position: absolute; left: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'">
            
            <!-- Right Ministry Logo -->
            <img src="{{ asset('public/images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" 
                 style="position: absolute; right: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'">
            
           
        </div>
        

             <div style="position: relative; margin-bottom: 20px;">
          
            
            <!-- Header Content (centered) -->
            <!-- Header Content (centered) -->
            <div class="memo-header">
                <br>
                <p class="font-bold text-right"><span class="data-value">{{ $memo->memo_no ?? '-' }}</span></p>
                <p class="font-bold text-left">PERMANENT SECRETARY,</p>
            </div>
        </div>
        



        <!-- Main Content -->
        @php
            $normalizePageValue = static function ($value, $fallback = null) {
                if (is_string($value) || is_numeric($value)) {
                    $trimmed = trim((string) $value);
                    if ($trimmed !== '') {
                        return $trimmed;
                    }
                }

                if (is_string($fallback) || is_numeric($fallback)) {
                    $trimmedFallback = trim((string) $fallback);
                    if ($trimmedFallback !== '') {
                        return $trimmedFallback;
                    }
                }

                return null;
            };

            $pageNoNormalized = $normalizePageValue($memo->page_no ?? null, optional($landAdmin)->page_no);
            $memo->page_no = $pageNoNormalized !== null ? $pageNoNormalized : null;

            $sitePlanNormalized = $normalizePageValue($memo->site_plan_no ?? null, optional($landAdmin)->site_plan_page_no);
            $memo->site_plan_no = $sitePlanNormalized !== null ? $sitePlanNormalized : null;

            $arcDesignNormalized = $normalizePageValue($memo->arc_design_page_no ?? null, optional($landAdmin)->arc_design_page_no);
            $memo->arc_design_page_no = $arcDesignNormalized !== null ? $arcDesignNormalized : null;
        @endphp
        <div class="text-gray-700 mb-6">
            <p class="mb-4">
                Kindly find page <span class="data-value">{{ $memo->page_no ?? '-' }}</span> is an application for <span class="data-value">{{ strtoupper(trim($memo->land_use ?? '')) ?: '-' }} </span> sectional titles in respect of fragmentation of the property,  covered by {{ $legacyReferenceLabel }} <span class="data-value">{{ $legacyReferenceValue }}</span> situated at
                @if(!empty($formattedPropertyLocation))
                    <span class="data-value">{{ $formattedPropertyLocation }}</span>
                @elseif(!empty($formattedFallbackLocation))
                    <span class="data-value">{{ $formattedFallbackLocation }}</span>
                @else
                    <span class="data-value">-</span>
                @endif
                in the name of <span class="data-value">{{ $formatTitle($applicantName) ?? '-' }}</span>.
            </p>
            
            <p class="mb-4">As well as change of name to various unit owners as per attached on the application.</p>

            <p class="mb-4">The application was referred to Physical Planning Department for planning, engineering as well as architectural views. The planners recommended the application because it is feasible, and meets the minimum requirements for <span class="data-value">{{ strtoupper(trim($memo->land_use ?? '')) ?: '-' }}</span> titles. Moreover, the proposal is accessible and conforms with the existing <span class="data-value">{{ strtoupper(trim($memo->prevailing_land_use ?? '')) ?: '-' }}</span> development in the area with Existing site area measuerments: <span class="data-value">{{ $siteMeasurementSummaryDisplay }}</span> and <span class="data-value">{{ $sharedUtilitiesSummaryDisplay }}</span> as shared Utilities / common areas.</p>

            <p class="mb-4">However, this recommendation is based on recommended site plan at page <span class="data-value">{{ $memo->site_plan_no ?? '-' }}</span> and architectural design at <span class="data-value">{{ $memo->arc_design_page_no ?? '-' }}</span> with measurements of approved dimensions contained Therein respectively.</p>
            <p class="mb-4">Meanwhile, the title was granted for <span class="data-value">{{ strtoupper(trim($memo->prevailing_land_use ?? '')) ?: '-' }}</span> purposes for a term of <span class="data-value">{{ $memo->term_years ?? ($totalYears ?? '-') }}</span> years commencing from <span class="data-value">{{ $memo->commencement_date ? date('d/m/Y', strtotime($memo->commencement_date)) : ($memo->approval_date ? date('d/m/Y', strtotime($memo->approval_date)) : '-') }}</span> and has a residual term of <span class="data-value">{{ $memo->residual_years ?? ($residualYears ?? '-') }}</span> years to expire.</p>
            
            <p class="mb-4 font-semibold whitespace-nowrap">In view of the above, you may kindly wish to recommend the following for approval of the Honourable Commissioner.</p>
            
            <ol class="list-decimal pl-5 mb-4">
                <li class="mb-2">Consider and approve the application for Sectional Titling over plot <span class="data-value">{{ $memo->property_plot_no ?? '-' }}</span> situated at
                @php
                    $listAddress = $composeAddress([
                        $memo->property_location ?? null,
                    ]) ?? $composeAddress([
                        $memo->property_street_name ?? null,
                        $memo->property_district ?? null,
                        $memo->property_lga ?? null,
                    ]);
                    $formattedListAddress = $formatTitle($listAddress);
                @endphp
                @if(!empty($formattedListAddress))
                    <span class="data-value">{{ $formattedListAddress }}</span>
                @else
                    <span class="data-value">-</span>
                @endif
                 covered by {{ $legacyReferenceLabel }} <span class="data-value">{{ $legacyReferenceValue }}</span> in Favour of <span class="data-value">{{ $formatTitle($applicantName) ?? '-' }}</span>.</li>
                <li class="mb-2">Consider and approve the change of name to various unit owners</li>
                <li class="mb-2">Consider and approve the Cancellation of Old Statutory File No mentioned above, to pave way for Sectional titles with Primary Application File No <span class="data-value">{{ $primaryApplicationNumberDisplay }}</span> and issuance of new Sectional Titles to the new owners as per buyers list attached on the next page.</li>
            </ol>
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
                    <p>Sign: - <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p>{{ $memo->director_rank ?? 'Director Sectional Titling' }}</p>
                </div>
                <div class="signature-item">
                    <p>Date: <span class="signature-line short-line"></span></p>
                </div>
            </div>
        </div>

        <!-- Permanent Secretary Section  -->
        <div class="approval-section2">
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

       
    </div>

    <!-- Page 2: Buyers List -->
    <div class="memo-container" style="page-break-before: always;" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
        <!-- Buyer List Table -->
        <div class="mt-8">
            <h3 class="font-bold text-lg mb-4">BUYERS LIST</h3>
            <table>
                <thead>
                    <tr>
                        <th class="text-center">SN</th>
                        <th class="text-center">BUYER NAME</th>
                        <th class="text-center">UNIT NUMBER</th>
                        <th class="text-center">MEASUREMENT (SQM)</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($buyersList) && count($buyersList) > 0)
                        @foreach($buyersList as $index => $buyer)
                            <tr>
                                <td><span class="data-value">{{ $index + 1 }}.</span></td>
                                <td><span class="data-value">{{ trim(($buyer->buyer_title ?? '') . ' ' . ($buyer->buyer_name ?? '')) ?: '-' }}</span></td>
                                <td><span class="data-value">{{ $buyer->unit_no ?? '-' }}</span></td>
                                <td><span class="data-value">{{ $buyer->measurement ?? '-' }}</span></td>
                            </tr>
                        @endforeach
                    @elseif(isset($memo->buyers) && is_array($memo->buyers) && count($memo->buyers) > 0)
                        @foreach($memo->buyers as $index => $buyer)
                            <tr>
                                <td><span class="data-value">{{ $index + 1 }}.</span></td>
                                <td><span class="data-value">{{ $buyer['buyer_name'] ?? '-' }}</span></td>
                                <td><span class="data-value">{{ $buyer['unit_number'] ?? '-' }}</span></td>
                                <td><span class="data-value">{{ $buyer['measurement'] ?? $buyer['plot_size'] ?? '-' }}</span></td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="text-center">No buyers data available.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
 
    </div>
   

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
    </script>
</body>
</html>
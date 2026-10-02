@php
    $forwardData = array_merge(
        \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']),
        ['forEmbed' => $forEmbed ?? true]
    );
@endphp

@include('actions.JOINT-SITE-INSPECTION-REPORT', $forwardData)
@php return; @endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joint Site Inspection Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 12px;
            line-height: 1.2;
        }
        
        .heading {
            font-family: 'Libre Baskerville', serif;
        }
        
        .report-container {
            width: 21cm;
            min-height: 29.7cm;
            background: white;
            margin: 0 auto;
            padding: 1cm;
            position: relative;
            overflow: visible;
        }
        
        .header-line {
            height: 2px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
        }
        
        .underline {
            text-decoration: underline;
            text-decoration-thickness: 1px;
        }
        
        /* Corner logo positioning */
        .corner-logo {
            width: 80px;
            height: auto;
            object-fit: contain;
            z-index: 15;
        }

        .logo-top-left {
            position: absolute;
            top: 0.5cm;
            left: 0.5cm;
        }

        .logo-top-right {
            position: absolute;
            top: 0.5cm;
            right: 0.5cm;
        }

        .logo-bottom-left {
            opacity: 0.9;
        }

        .logo-bottom-right {
            opacity: 0.9;
        }

        .bottom-logos-wrapper {
            position: absolute;
            bottom: 90cm;
            left: 0.5cm;
            right: 0.5cm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            display: none;
        }

        body.pdf-export .bottom-logos-wrapper {
            position: absolute;
            bottom: 1cm;
            left: 0.5cm;
            right: 0.5cm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
            display: none;
        }

        @media print {
            .bottom-logos-wrapper {
                position: absolute;
                bottom: 1cm;
                left: 0.5cm;
                right: 0.5cm;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 0;
                padding: 0;
                z-index: 20;
            }
        }

        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 11px;
            margin: 8px 0;
        }
        
        th, td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        
        .section-text strong {
            color: #111827;
        }
        
        body.pdf-export {
            background: white;
            padding: 0;
            margin: 0;
            width: 21cm;
        }

        body.pdf-export .report-container {
            width: 100%;
            min-height: auto;
            height: auto;
            padding: 1cm;
            margin: 0;
            overflow: visible;
        }

        body.pdf-export .no-print {
            display: none !important;
        }

        body.pdf-export .corner-logo {
            width: 70px !important;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                width: 21cm;
            }
            
            .report-container {
                width: 100%;
                min-height: auto;
                height: auto;
                padding: 1cm;
                margin: 0;
                page-break-after: avoid;
                overflow: visible;
            }
            
            .no-print {
                display: none !important;
            }
            
            .corner-logo {
                width: 70px !important;
            }

            @page {
                size: A4 portrait;
                margin: 0;
            }

            .page-break-after {
                display: block;
                height: 0;
                page-break-after: always;
                break-after: page;
            }

            .page-break-before {
                display: block;
                height: 0;
                page-break-before: always;
                break-before: page;
            }
        }
    </style>
</head>

<body class="py-2">
    @if(!isset($printMode) || !$printMode)
        <div class="no-print flex justify-center mb-2 space-x-2">
            <button id="printReportBtn" type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1 px-4 rounded shadow transition duration-200 flex items-center text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V4a1 1 0 011-1h10a1 1 0 011 1v5" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18h12v-5a2 2 0 00-2-2H8a2 2 0 00-2 2v5z" />
                </svg>
                Print
            </button>
            @php
                $isUnitReportForPrint = !empty($report->sub_application_id);
                $printUrl = $isUnitReportForPrint 
                    ? route('sub-actions.planning-recommendation.joint-inspection.show', $application->id) . '?print=true'
                    : route('planning-recommendation.joint-inspection.show', $application->id) . '?print=true';
            @endphp
            <a href="{{ $printUrl }}" target="_blank" class="bg-gray-700 hover:bg-gray-800 text-white font-semibold py-1 px-4 rounded shadow transition duration-200 flex items-center text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                Open Print View
            </a>
        </div>
    @endif

    @php
        use Carbon\Carbon;

        // Handle case where $report might be null or undefined
        $report = $report ?? null;
        
        $inspectionDate = $report && $report->inspection_date ? Carbon::parse($report->inspection_date)->format('j F Y') : null;
        $lknNumber = ($report->lkn_number ?? null) ?? ($application->lkn_number ?? $application->applicationID ?? null);

        $locationParts = array_filter([
            $application->property_street_name ?? null,
            $application->property_lga ?? null,
        ]);
        $locationFallback = !empty($locationParts) ? implode(', ', $locationParts) : ($application->property_location ?? null);
        $locationDisplay = ($report->location ?? null) ?? $locationFallback;

        $plotNumber = ($report->plot_number ?? null) ?? ($application->property_plot_no ?? null);
        $schemeNumber = ($report->scheme_number ?? null) ?? ($application->scheme_no ?? null);

        $applicantDisplay = $report ? ($report->applicant_name ?? null) : null;
        if (!$applicantDisplay) {
            $type = strtolower($application->applicant_type ?? '');
            if ($type === 'individual') {
                $applicantDisplay = trim(implode(' ', array_filter([
                    $application->applicant_title ?? null,
                    $application->first_name ?? null,
                    $application->surname ?? null,
                ])));
            } elseif ($type === 'corporate') {
                $applicantDisplay = trim(implode(' ', array_filter([
                    $application->rc_number ?? null,
                    $application->corporate_name ?? null,
                ])));
            } elseif ($type === 'multiple') {
                $names = $application->multiple_owners_names ?? null;
                if (is_string($names)) {
                    $decodedNames = json_decode($names, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedNames)) {
                        $applicantDisplay = implode(', ', array_filter($decodedNames));
                    } else {
                        $applicantDisplay = $names;
                    }
                } elseif (is_array($names)) {
                    $applicantDisplay = implode(', ', array_filter($names));
                }
            }
        }
        $applicantDisplay = $applicantDisplay ?: ($application->applicant_name ?? '');

        $sharedAreasFromReport = $report ? ($report->shared_utilities ?? []) : [];
        if (is_string($sharedAreasFromReport)) {
            $decoded = json_decode($sharedAreasFromReport, true);
            $sharedAreasFromReport = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        // Handle undefined utilities variable
        $utilities = $utilities ?? [];
        
        $sharedUtilitiesFromDb = collect($utilities ?? [])
            ->pluck('utility_type')
            ->filter()
            ->map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $sharedAreasCombinedRaw = array_values(array_filter(array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, array_merge(
            is_array($sharedAreasFromReport) ? $sharedAreasFromReport : [],
            $sharedAreasList ?? [],
            $sharedUtilitiesFromDb
        ))));

        $sharedUtilitiesDisplay = array_values(array_filter(array_unique(array_map(function ($value) {
            if (!is_string($value)) {
                return null;
            }
            $normalized = trim($value);
            if ($normalized === '') {
                return null;
            }
            return ucwords(str_replace(['_', '-'], ' ', $normalized));
        }, $sharedAreasCombinedRaw))));

        // Build Table A strictly from persisted measurement entries
        $dimensionsCollection = collect($report->existing_site_measurement_entries ?? [])
            ->filter(function ($entry) {
                return is_array($entry) || is_object($entry);
            })
            ->map(function ($entry, $index) {
                $descriptionRaw = data_get($entry, 'description');
                $measurementDimensionRaw = data_get($entry, 'measurement_dimension')
                    ?? data_get($entry, 'measurement_dimension_display')
                    ?? data_get($entry, 'measurementDimension');
                $dimensionRaw = data_get($entry, 'dimension');
                $countRaw = data_get($entry, 'count');

                $description = is_string($descriptionRaw) ? trim($descriptionRaw) : ($descriptionRaw ?? '');
                $measurementDimension = is_string($measurementDimensionRaw) ? trim($measurementDimensionRaw) : ($measurementDimensionRaw ?? '');
                $dimension = is_string($dimensionRaw) ? trim($dimensionRaw) : ($dimensionRaw ?? '');
                $count = is_string($countRaw) ? trim($countRaw) : ($countRaw !== null ? (string) $countRaw : '');
                $count = $count === '' ? '1' : $count;

                if ($description === '' && $measurementDimension === '' && $dimension === '' && $count === '1') {
                    return null;
                }

                $sn = data_get($entry, 'sn');
                $sn = is_numeric($sn) ? (int) $sn : ($index + 1);

                return (object) [
                    'sn' => $sn,
                    'description' => $description === '' ? null : $description,
                    'measurement_dimension' => $measurementDimension === '' ? null : $measurementDimension,
                    'dimension' => $dimension === '' ? null : $dimension,
                    'count' => $count,
                ];
            })
            ->filter()
            ->sortBy('sn')
            ->values();
        $unitMeasurementsCollection = collect($unitMeasurements ?? [])->map(function ($item) {
            $unitNo = $item->unit_no ?? $item->unit_number ?? null;
            $measurement = $item->measurement ?? $item->unit_size ?? null;
            $sn = $item->sn ?? null;
            $buyerName = $item->buyer_name ?? null;
            $buyerTitle = $item->buyer_title ?? null;

            $unitNo = is_null($unitNo) ? null : trim((string) $unitNo);

            $measurementString = null;
            $shouldAppendUnit = false;
            if (!is_null($measurement)) {
                if (is_numeric($measurement)) {
                    $measurementString = rtrim(rtrim(number_format((float) $measurement, 2, '.', ''), '0'), '.');
                    $shouldAppendUnit = true;
                } else {
                    $measurementTrimmed = trim((string) $measurement);
                    if ($measurementTrimmed !== '') {
                        $measurementString = $measurementTrimmed;
                        $shouldAppendUnit = !preg_match('/(?:m\s*(?:²|2)|sq\s*\.?m)/i', $measurementTrimmed);
                    }
                }
            }

            if (($unitNo === '' || is_null($unitNo)) && is_null($measurementString)) {
                return null;
            }

            return (object) [
                'sn' => $sn,
                'unit_no' => $unitNo === '' ? null : $unitNo,
                'measurement' => $measurementString,
                'unit_size' => $measurementString,
                'buyer_name' => $buyerName,
                'buyer_title' => $buyerTitle,
                'append_unit' => $shouldAppendUnit,
            ];
        })->filter()->values();

        if ($unitMeasurementsCollection->isEmpty() && ($application->unit_number ?? null)) {
            $fallbackMeasurement = $application->unit_size ?? null;
            $formattedMeasurement = null;

            if (!is_null($fallbackMeasurement)) {
                if (is_numeric($fallbackMeasurement)) {
                    $formattedMeasurement = rtrim(rtrim(number_format((float) $fallbackMeasurement, 2, '.', ''), '0'), '.');
                } else {
                    $formattedMeasurement = trim((string) $fallbackMeasurement);
                }
            }

            $unitMeasurementsCollection = collect([(object) [
                'sn' => 1,
                'unit_no' => trim((string) $application->unit_number),
                'measurement' => $formattedMeasurement,
                'unit_size' => $formattedMeasurement,
                'buyer_name' => null,
                'buyer_title' => null,
                'append_unit' => is_numeric($fallbackMeasurement),
            ]]);
        }

        $unitDimensionRaw = $report->unit_dimension ?? null;
        $unitDimensionFormatted = null;
        $unitDimensionAppendsUnit = false;

        if ($unitDimensionRaw !== null) {
            if (is_numeric($unitDimensionRaw)) {
                $formatted = rtrim(rtrim(number_format((float) $unitDimensionRaw, 2, '.', ''), '0'), '.');
                $unitDimensionFormatted = $formatted === '' ? '0' : $formatted;
                $unitDimensionAppendsUnit = true;
            } else {
                $trimmed = trim((string) $unitDimensionRaw);
                if ($trimmed !== '') {
                    $unitDimensionFormatted = $trimmed;
                    $unitDimensionAppendsUnit = !preg_match('/(?:m\s*(?:²|2)|sq\s*\.?m)/i', $trimmed);
                }
            }
        }

        if ($unitDimensionFormatted !== null) {
            if ($unitMeasurementsCollection->isEmpty()) {
                $unitNumberCandidates = [
                    $report->unit_number ?? null,
                    $application->unit_number ?? null,
                ];

                $unitNumber = collect($unitNumberCandidates)
                    ->map(function ($candidate) {
                        return is_null($candidate) ? null : trim((string) $candidate);
                    })
                    ->first(function ($candidate) {
                        return !is_null($candidate) && $candidate !== '';
                    });

                if ($unitNumber === null) {
                    $unitNumber = 'Unit 1';
                }

                $unitMeasurementsCollection = collect([(object) [
                    'sn' => 1,
                    'unit_no' => $unitNumber,
                    'measurement' => $unitDimensionFormatted,
                    'unit_size' => $unitDimensionFormatted,
                    'buyer_name' => null,
                    'buyer_title' => null,
                    'append_unit' => $unitDimensionAppendsUnit,
                ]]);
            } else {
                $unitMeasurementsCollection = $unitMeasurementsCollection->map(function ($entry, $index) use ($unitDimensionFormatted, $unitDimensionAppendsUnit) {
                    if ($index === 0) {
                        $entry->measurement = $unitDimensionFormatted;
                        $entry->unit_size = $unitDimensionFormatted;
                        $entry->append_unit = $unitDimensionAppendsUnit;
                    }
                    return $entry;
                })->values();
            }
        }

    $complianceStatusLabel = $report && $report->compliance_status ? strtoupper(str_replace('_', ' ', $report->compliance_status)) : 'OBTAINABLE';
    $availabilityText = $report && $report->available_on_ground ? 'available on the ground' : 'not available on the ground';
    $additionalObservationFlag = $report && $report->has_additional_observations ? 'Yes' : 'No';
    $additionalObservationText = trim((string) ($report && $report->additional_observations ? $report->additional_observations : ''));
    $shouldDisplayAdditionalObservations = $report && $report->has_additional_observations && $additionalObservationText !== '';

        $defaultBoundaryTemplate = "Boundary demarcation:\n- North: \n- South: \n- East: \n- West: ";
        $boundaryDescriptionRaw = trim((string) ($report && $report->boundary_description ? $report->boundary_description : ''));
        $boundaryDescriptionDisplay = $boundaryDescriptionRaw !== '' ? $boundaryDescriptionRaw : $defaultBoundaryTemplate;

        $defaultMeasurementSummary = 'No recorded dimensions were submitted for this inspection.';
        $measurementSummaryText = trim((string) ($report && $report->existing_site_measurement_summary ? $report->existing_site_measurement_summary : ''));
        if ($measurementSummaryText === '') {
            $measurementSummaryText = $defaultMeasurementSummary;
        }

        $isUnitReport = $report && !empty($report->sub_application_id);
        $unitNumberRaw = null;
        if ($isUnitReport) {
            $unitNumberCandidates = [
                $report->unit_number ?? null,
                $application->unit_number ?? null,
            ];

            foreach ($unitNumberCandidates as $candidate) {
                if (is_null($candidate)) {
                    continue;
                }

                $candidateTrimmed = trim((string) $candidate);
                if ($candidateTrimmed !== '') {
                    $unitNumberRaw = $candidateTrimmed;
                    break;
                }
            }
        }
        $unitNumberDisplay = ($unitNumberRaw === null || $unitNumberRaw === '') ? '________________' : $unitNumberRaw;
        $sectionsCountDisplay = (!$isUnitReport && $report && $report->sections_count !== null && $report->sections_count !== '')
            ? $report->sections_count
            : '________________';
    @endphp

    <div class="report-container" id="jointInspectionReport">
        <img class="corner-logo logo-top-left" src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Ministry Logo" onerror="this.style.display='none'">
        <img class="corner-logo logo-top-right" src="{{ asset('assets/logo/ministry2.png') }}" alt="Ministry Logo" onerror="this.style.display='none'">

        <div class="header-line rounded-t-lg mb-2"></div>
        
        <div class="text-center mb-3">
            <h1 class="heading text-base font-bold text-gray-800 mb-1 underline">SECTIONAL TITLING ONE STOP SHOP</h1>
            <h2 class="heading text-sm font-bold text-gray-800 mt-1 underline">JOINT SITE INSPECTION REPORT</h2>
            <p class="text-sm font-semibold text-gray-700 mt-2">THE COORDINATOR OSS,</p>
        </div>

        <div class="mb-3 text-gray-700 section-text">
            <p class="mb-2">
                Below is a joint site inspection report conducted on
                <strong>{{ $inspectionDate ?? '________________' }}</strong>
                of an application made for fragmentation of property with LKN no
                <strong>{{ $lknNumber ?? '________________' }}</strong>
                in the name of
                <strong>{{ $applicantDisplay ?: '________________' }}</strong>
                located at
                <strong>{{ $locationDisplay ?? '________________' }}</strong>
                with plot no
                <strong>{{ $plotNumber ?? '________________' }}</strong>
                and Scheme no
                <strong>{{ $schemeNumber ?? '________________' }}</strong>.
            </p>
        </div>

        <div class="mb-3 text-gray-700 section-text">
            <p class="mb-2">
                The site has been inspected and found to be <strong>{{ $availabilityText }}</strong> and bounded as follows:
                <span class="font-semibold">{!! nl2br(e($boundaryDescriptionDisplay)) !!}</span>.
                @if($isUnitReport)
                    The property unit number is
                    <strong>{{ $unitNumberDisplay }}</strong>. The unit is obtainable,
                    accessible and conforms with the existing land use as described below.
                @else
                    The property is fragmented into
                    <strong>{{ $sectionsCountDisplay }}</strong> sections. All sections are obtainable,
                    accessible and conform with the existing land use as described below.
                @endif
            </p>
        </div>

        <div class="mb-3">
            <p class="font-semibold text-gray-700 mb-1 text-xs">Existing site measurement and Area:</p>
            <p class="text-xs text-gray-600 mb-2">{{ $measurementSummaryText }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs border border-gray-300">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 px-2 py-1 w-12 text-left">S/N</th>
                            <th class="border border-gray-300 px-2 py-1 text-left">Utility</th>
                            <th class="border border-gray-300 px-2 py-1 text-left">Measurement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dimensionsCollection as $index => $dimension)
                            <tr>
                                <td class="border border-gray-300 px-2 py-1 align-top">{{ $index + 1 }}</td>
                                <td class="border border-gray-300 px-2 py-1 align-top">
                                    @php
                                        $descriptionRaw = $dimension->description ?? '';
                                        $descriptionNormalized = trim(str_replace(['_', '-'], ' ', $descriptionRaw));
                                        $descriptionFormatted = $descriptionNormalized === ''
                                            ? 'Segment '.($index + 1)
                                            : ucwords($descriptionNormalized);
                                    @endphp
                                    {{ $descriptionFormatted }}
                                </td>
                                @php
                                    $dimensionRaw = data_get($dimension, 'dimension');
                                    $measurementDimensionRaw = data_get($dimension, 'measurement_dimension') ?? data_get($dimension, 'measurement_dimension_display');
                                    $dimensionDisplay = is_string($dimensionRaw) ? trim($dimensionRaw) : $dimensionRaw;
                                    $measurementDimensionDisplay = is_string($measurementDimensionRaw) ? trim($measurementDimensionRaw) : $measurementDimensionRaw;
                                    $finalDimension = $dimensionDisplay !== null && $dimensionDisplay !== ''
                                        ? $dimensionDisplay
                                        : $measurementDimensionDisplay;
                                @endphp
                                <td class="border border-gray-300 px-2 py-1 align-top">{{ $finalDimension !== null && $finalDimension !== '' ? $finalDimension : 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="border border-gray-300 px-2 py-2 text-center" colspan="3">
                                    No recorded dimensions were submitted for this inspection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-3 border border-gray-200 rounded p-2 bg-gray-50 text-xs space-y-2">
            <div class="flex"><span class="font-semibold mr-2">Existing Road reservation:</span> <span>{{ $report && $report->road_reservation ? $report->road_reservation : 'Not specified' }}</span></div>
            <div class="flex"><span class="font-semibold mr-2">Prevailing Land use:</span> <span>{{ $report && $report->prevailing_land_use ? $report->prevailing_land_use : 'Not specified' }}</span></div>
            <div class="flex"><span class="font-semibold mr-2">Applied land use:</span> <span>{{ $report && $report->applied_land_use ? $report->applied_land_use : ($application->land_use ?? 'Not specified') }}</span></div>
            <div class="flex"><span class="font-semibold mr-2">Shared utilities:</span>
                <span>{{ !empty($sharedUtilitiesDisplay) ? implode(', ', $sharedUtilitiesDisplay) : 'No shared utilities recorded' }}</span>
            </div>
        </div>

        <div class="mb-3 text-gray-700 section-text">
            <p class="mb-2">
                Based on the analysis conducted to assess compliance with statutory requirements and planning standards,
                the proposed scheme is
                <strong>{{ $complianceStatusLabel }}</strong>
                based on the existing dimensions listed above. The scheme consists of shared facilities and is in conformity
                with existing land use in the area. See dimensions of fragmented units as described by the recommended site plan in the table below.
            </p>
        </div>

        @if($shouldDisplayAdditionalObservations)
            <div class="mb-4 text-xs ">
                <p class="font-semibold text-gray-700 mb-1">ADDITIONAL OBSERVATION (IF ANY)</p>
                <div class="border border-dotted border-gray-400 min-h-[70px] p-2 rounded">
                    {!! nl2br(e($additionalObservationText)) !!}
                </div>
            </div>
        @endif

        <div class="mb-4 text-center">
            <div class="border-b border-gray-300 inline-block w-48"></div>
            <p class="text-xs text-gray-600 mt-1">{{ $report && $report->inspection_officer ? $report->inspection_officer : 'INSPECTION OFFICER / RANK' }}</p>
        </div>

        <div class="page-break-before">
            <table>
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>UNIT NO</th>
                        <th>Dimension in M²</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unitMeasurementsCollection as $index => $unit)
                        <tr>
                            <td>{{ $unit->sn ?? ($index + 1) }}</td>
                            <td>{{ $unit->unit_no ?? 'Unit '.($index + 1) }}</td>
                            <td>
                                @php
                                    $unitMeasurementValue = $unit->unit_size ?? $unit->measurement ?? null;
                                    $unitMeasurementDisplayable = !is_null($unitMeasurementValue) && trim((string) $unitMeasurementValue) !== '';
                                @endphp
                                @if($unitMeasurementDisplayable)
                                    {{ $unitMeasurementValue }}@if(!empty($unit->append_unit)) m²@endif
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">No unit dimensions recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="text-center text-xs text-gray-500 mt-4 mb-2">
                <p>Generated on: <span id="current-date"></span> by {{ auth()->user()->name ?? 'System' }}</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('current-date').textContent = new Date().toLocaleDateString();
    </script>

    @if(!isset($printMode) || !$printMode)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-YcsIP0Y4+GWXrXyhlHLLJoPLD114F8CbnMD4HzyBbs6k8ZZrVSu2CevulaHYodNs/WWEDuJeCec2bH4C0PizPQ==" crossorigin="anonymous" referreferrer="no-referrer"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const printBtn = document.getElementById('printReportBtn');
                const report = document.getElementById('jointInspectionReport');

                if (printBtn) {
                    printBtn.addEventListener('click', (event) => {
                        event.preventDefault();
                        window.print();
                    });
                }
            });
        </script>
    @endif
</body>
</html>

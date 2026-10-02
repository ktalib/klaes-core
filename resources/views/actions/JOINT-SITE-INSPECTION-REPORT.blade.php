@php
    $forEmbed = $forEmbed ?? false;
    $isStandalone = !$forEmbed;

    $jsiReportStyles = <<<'CSS'
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body,
        .jsi-embedded-root {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #ffffff;
            font-size: 12px;
            line-height: 1.35;
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
            padding-bottom: 2.5cm;
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
            text-align: center;
        }
        
        .section-text strong {
            color: #111827;
        }
        
        body.pdf-export,
        .jsi-embedded-root.pdf-export {
            background: white;
            padding: 0;
            margin: 0;
            width: 21cm;
        }

        body.pdf-export .report-container,
        .jsi-embedded-root.pdf-export .report-container {
            width: 100%;
            min-height: auto;
            height: auto;
            padding: 1cm;
            padding-bottom: 2.5cm;
            margin: 0;
            overflow: visible;
        }

        body.pdf-export .no-print,
        .jsi-embedded-root.pdf-export .no-print {
            display: none !important;
        }

        body.pdf-export .corner-logo,
        .jsi-embedded-root.pdf-export .corner-logo {
            width: 70px !important;
        }


            .report-container {
                width: 100%;
                min-height: auto;
                height: auto;
                padding: 1cm;
                padding-bottom: 2.5cm;
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
                margin: 0.1cm 0 2cm 0;
                @bottom-center {
                    content: "Generated on: " attr(data-date) " by " attr(data-user);
                    font-size: 10px;
                    color: #666;
                }
            }

            @page :first {
                margin: 1.2cm 0.4cm 2cm 0.4cm;
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

        @media print {
            /* Set standard print margins for all pages */
            @page {
                size: A4 portrait;
                margin: 0.1cm 0 2cm 0;
                @bottom-center {
                    content: "Generated on: " attr(data-date) " by " attr(data-user);
                    font-size: 10px;
                    color: #666;
                }
            }

            /* First page with slightly adjusted top margin */
            @page :first {
                margin-top: 0.75cm;
                margin-right: 0.75cm;
                margin-left: 0.75cm;
                margin-bottom: 2cm;
            }

            * {
                margin: 0;
                padding: 0;
            }

            body,
            .jsi-embedded-root {
                width: 100%;
                margin: 0;
                padding: 0;
                background: white;
            }

            .report-container {
                width: 100%;
                height: auto;
                min-height: auto;
                margin: 0;
                padding: 0.75cm;
                page-break-after: avoid;
                page-break-inside: avoid;
            }

            table {
                page-break-inside: avoid;
                margin: 8px 0;
            }

            tr {
                page-break-inside: avoid;
            }

            .no-print {
                display: none !important;
            }

            .corner-logo {
                width: 60px !important;
                height: auto !important;
            }
                
        }

        CSS;

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

    $generatedTimestamp = \Carbon\Carbon::now();
    $generatedAtDisplay = $generatedTimestamp->format('F j, Y \\a\\t g:i A');
    $generatedAtAttr = $generatedTimestamp->format('d/m/Y H:i');
@endphp

@if($isStandalone)
<!DOCTYPE html>
<html lang="en" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joint Site Inspection Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>{!! $jsiReportStyles !!}</style>

    <script>
        // Configure print settings and auto-print when print parameter is present
        function configurePrintSettings() {
            // Set print margins via CSS - these will apply when user prints
            const printStyle = document.createElement('style');
            printStyle.media = 'print';
            printStyle.textContent = `
                @page {
                    size: A4 portrait;
                    margin: 0.75cm 0.75cm 0.75cm 0.75cm;
                }
                body {
                    margin: 0;
                    padding: 0;
                }
                .report-container {
                    margin: 0;
                    padding: 0.75cm;
                }
            `;
            document.head.appendChild(printStyle);
        }

        document.addEventListener('DOMContentLoaded', function() {
            configurePrintSettings();
            
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') && window.top === window.self) {
                setTimeout(function() {
                    window.print();
                }, 800); // Slightly more delay to ensure everything is rendered
            }
        });
    </script>
</head>

<body class="py-2" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
@else
    <script src="https://cdn.tailwindcss.com"></script>
    <style>{!! $jsiReportStyles !!}</style>
    <div class="jsi-embedded-root py-2" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
@endif
  

    @php
        use Carbon\Carbon;

        $inspectionDate = $report->inspection_date ? Carbon::parse($report->inspection_date)->format('j F Y') : null;
        $lknNumber = $report->lkn_number ?? $application->lkn_number ?? $application->applicationID ?? null;

        $locationParts = array_filter([
            $application->property_street_name ?? null,
            $application->property_lga ?? null,
        ]);
        $locationFallback = !empty($locationParts) ? implode(', ', $locationParts) : ($application->property_location ?? null);
        $locationDisplay = $report->location ?? $locationFallback;

        $plotNumber = $report->plot_number ?? $application->property_plot_no ?? null;
        $schemeNumber = $report->scheme_number ?? $application->scheme_no ?? null;

        $applicantDisplay = $report->applicant_name;
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

        $sourceNormalized = strtolower(trim((string) ($report->source ?? '')));
        $fileNumberNormalized = strtoupper(trim((string) ($report->file_number ?? '')));
        
        // Robust detection: if it has a sub_application_id, it is definitely ST.
        // Otherwise, rely on the persisted source. Note: a "CON-" file number
        // prefix means "Consent" in this system's numbering scheme (see
        // FILE_NUMBERS_EXAMPLES_AND_REGISTRIES.md), NOT "Conversion" — so it
        // must never be used to infer a Conversion report (e.g. ST OSS files
        // like CON-RES-1985-114 would otherwise be misclassified).
        $hasSubApplication = !empty($report->sub_application_id);
        $isConversionReport = !$hasSubApplication && $sourceNormalized === 'conversion applications';

        $sharedAreasFromReport = $report->shared_utilities ?? [];
        if (is_string($sharedAreasFromReport)) {
            $decoded = json_decode($sharedAreasFromReport, true);
            $sharedAreasFromReport = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

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

        $dimensionsCollection = collect($report->existing_site_measurement_entries ?? [])
            ->filter(function ($item) {
                return is_array($item) || is_object($item);
            })
            ->map(function ($item, $index) {
                if (is_array($item)) {
                    $item = (object) $item;
                }

                $description = $item->description ?? ($item->shared_utility ?? ($item->sharedUtility ?? null));
                $dimensionValue = $item->dimension ?? ($item->measurement ?? null);
                $measurementDimensionValue = null;
                foreach ([
                    'measurement_dimension',
                    'measurementDimension',
                    'measurement_dimension_display',
                    'dimension_display',
                    'dimension_label',
                    'dimensionLabel',
                ] as $property) {
                    if (property_exists($item, $property) && $item->{$property} !== null) {
                        $candidate = trim((string) $item->{$property});
                        if ($candidate !== '') {
                            $measurementDimensionValue = $candidate;
                            break;
                        }
                    }
                }
                $countValue = $item->count ?? null;
                $snValue = $item->sn ?? null;

                if ($snValue === null || $snValue === '') {
                    $snValue = $index + 1;
                }

                $normalized = new \stdClass();
                $normalized->sn = $snValue;
                $normalized->description = $description;
                $normalized->dimension = $dimensionValue;
                $normalized->measurement_dimension = $measurementDimensionValue;
                $normalized->count = $countValue;
                $normalized->order = $index;

                return $normalized;
            })
            ->filter(function ($item) {
                $desc = strtolower(trim((string) ($item->description ?? '')));
                return $desc !== '' && !str_starts_with($desc, '__');
            })
            // A utility name is not unique within a scheme. For example, two
            // parking spaces can have different dimensions. Only collapse rows
            // when their complete printed measurement is identical.
            ->groupBy(function ($item) {
                $descriptionNormalized = preg_replace('/\s+/', ' ', strtolower(trim(str_replace(['_', '-'], ' ', (string) ($item->description ?? '')))));
                $measurementDimensionNormalized = preg_replace('/\s+/', ' ', strtolower(trim((string) ($item->measurement_dimension ?? ''))));
                $dimensionNormalized = preg_replace('/\s+/', ' ', strtolower(trim((string) ($item->dimension ?? ''))));
                $countNormalized = trim((string) ($item->count ?? ''));

                return implode('|', [
                    'utility',
                    $descriptionNormalized,
                    $measurementDimensionNormalized,
                    $dimensionNormalized,
                    $countNormalized,
                ]);
            })
            ->map(function ($group) {
                $representative = clone $group->first();

                $descriptionCandidate = $group->first(function ($item) {
                    return !is_null($item->description) && trim((string) $item->description) !== '';
                });
                if ($descriptionCandidate) {
                    $representative->description = $descriptionCandidate->description;
                }

                $dimensionCandidate = $group->first(function ($item) {
                    return !is_null($item->dimension) && trim((string) $item->dimension) !== '';
                });
                $representative->dimension = $dimensionCandidate ? $dimensionCandidate->dimension : null;

                $measurementDimensionCandidate = $group->first(function ($item) {
                    return !is_null($item->measurement_dimension) && trim((string) $item->measurement_dimension) !== '';
                });
                $representative->measurement_dimension = $measurementDimensionCandidate ? $measurementDimensionCandidate->measurement_dimension : null;

                $countCandidate = $group->first(function ($item) {
                    return !is_null($item->count) && trim((string) $item->count) !== '';
                });
                $representative->count = $countCandidate ? $countCandidate->count : null;

                $numericSn = $group->pluck('sn')
                    ->filter(function ($sn) {
                        return is_numeric($sn);
                    })
                    ->map(function ($sn) {
                        return (int) $sn;
                    })
                    ->min();

                if (!is_null($numericSn)) {
                    $representative->sn = $numericSn;
                }

                $orderValue = $group->pluck('order')
                    ->filter(function ($order) {
                        return is_numeric($order);
                    })
                    ->min();

                if (!is_null($orderValue)) {
                    $representative->order = (int) $orderValue;
                }

                return $representative;
            })
            ->sortBy(function ($item) {
                $sn = $item->sn ?? null;
                if (is_numeric($sn)) {
                    return (int) $sn;
                }

                $order = $item->order ?? null;
                if (is_numeric($order)) {
                    return (int) $order;
                }

                return PHP_INT_MAX;
            })
            ->values()
            ->map(function ($item) {
                if (property_exists($item, 'order')) {
                    unset($item->order);
                }

                return $item;
            });

        $unitMeasurementsCollection = collect($unitMeasurements ?? [])
            ->map(function ($item) {
                $unitNo = $item->unit_no ?? null;
                $measurement = $item->measurement ?? null;

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
                    'unit_no' => $unitNo === '' ? null : $unitNo,
                    'measurement' => $measurementString,
                    'append_unit' => $shouldAppendUnit,
                ];
            })
            ->filter()
            ->values()
            ->sortBy(function ($item) {
                $unitNo = $item->unit_no;
                if (is_null($unitNo) || $unitNo === '') {
                    return 'ZZZZZ';
                }

                preg_match('/^([A-Za-z]*)(\d+)(.*)$/', $unitNo, $matches);

                if (count($matches) >= 3) {
                    $letters = strtoupper($matches[1]);
                    $number = intval($matches[2]);
                    $suffix = $matches[3] ?? '';

                    return sprintf('%05s%010d%s', $letters, $number, $suffix);
                }

                $numbers = preg_replace('/[^0-9]/', '', $unitNo);
                if ($numbers !== '') {
                    $letters = preg_replace('/[0-9]/', '', $unitNo);
                    return sprintf('%05s%010d', strtoupper($letters), intval($numbers));
                }

                return strtoupper($unitNo);
            })
            ->values();

        $unitDimensionRaw = $report->unit_dimension ?? null;

        $needForEiaValue = null;
        if ($isConversionReport) {
            $eiaEntry = collect($report->existing_site_measurement_entries ?? [])->first(function($item) {
                $desc = strtolower(trim((string)data_get($item, 'description')));
                return $desc === '__need_for_eia__';
            });
            if ($eiaEntry) {
                $needForEiaValue = ((int)data_get($eiaEntry, 'count')) === 1 ? 'Yes' : 'No';
            }
        }

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
        }

        $complianceStatusLabel = $report->compliance_status ? strtoupper(str_replace('_', ' ', $report->compliance_status)) : 'OBTAINABLE';
        $availabilityText = $report->available_on_ground ? 'available on the ground' : 'not available on the ground';
        $additionalObservationFlag = $report->has_additional_observations ? 'Yes' : 'No';
        $additionalObservationText = trim((string) ($report->additional_observations ?? ''));
        $shouldDisplayAdditionalObservations = $report->has_additional_observations && $additionalObservationText !== '';

        $defaultBoundaryTemplate = "Boundary demarcation:\n- North: \n- South: \n- East: \n- West: ";
        $boundaryDescriptionRaw = trim((string) ($report->boundary_description ?? ''));
        $boundaryDescriptionDisplay = $boundaryDescriptionRaw !== '' ? $boundaryDescriptionRaw : $defaultBoundaryTemplate;

        $defaultMeasurementSummary = 'No recorded dimensions were submitted for this inspection.';
        $measurementSummaryText = trim((string) ($report->existing_site_measurement_summary ?? ''));
        if ($measurementSummaryText === '') {
            $measurementSummaryText = $defaultMeasurementSummary;
        }

        $isUnitReport = !empty($report->sub_application_id);
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
        $sectionsCountDisplay = (!$isUnitReport && $report->sections_count !== null && $report->sections_count !== '')
            ? $report->sections_count
            : '________________';
    @endphp

    <div class="report-container" id="jointInspectionReport"
        data-date="{{ $generatedAtDisplay }}"
        data-user="{{ $generatedBy }}">
    <img class="corner-logo logo-top-left" src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Ministry Logo" onerror="this.style.display='none'">
    <img class="corner-logo logo-top-right" src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="Ministry Logo" onerror="this.style.display='none'">

    
        <div class="text-center mb-3">
            @if($isConversionReport)
                <h1 class="heading text-base font-bold text-gray-800 mb-1 underline uppercase">CONVERSION - PHYSICAL PLANNING DEPARTMENT</h1>
            @else
                <h1 class="heading text-base font-bold text-gray-800 mb-1 underline uppercase">SECTIONAL TITLING ONE STOP SHOP</h1>
            @endif
            <h2 class="heading text-sm font-bold text-gray-800 mt-1 underline">JOINT SITE INSPECTION REPORT</h2>
        </div>
        <br>
        <div class="text-left mb-3">
            @if($isConversionReport)
                <p class="text-sm font-semibold text-gray-700">THE DIRECTOR,</p>
            @else
                <p class="text-sm font-semibold text-gray-700">THE DIRECTOR,</p>
            @endif
        </div>

        
        <div class="mb-3 text-gray-700 section-text">
            <p class="mb-2">
            Below Is A Joint Site Inspection Report Conducted On
            <strong class="whitespace-nowrap">{{ $inspectionDate ?? '________________' }}</strong>
            Of An Application Made For Fragmentation Of Property With LPKN No
            <strong class="whitespace-nowrap">{{ $lknNumber ?? '________________' }}</strong>
            In The Name Of
            <strong class="whitespace-nowrap">{{ ucwords(strtolower($applicantDisplay ?: '________________')) }}</strong>
            Located At
            <strong class="whitespace-nowrap">{{ ucwords(strtolower($locationDisplay ?? '________________')) }}</strong>
            With Plot No
            <strong class="whitespace-nowrap">{{ $plotNumber ?? '________________' }}</strong>
            And Scheme No
            <strong class="whitespace-nowrap">{{ $schemeNumber ?? '________________' }}</strong>.
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

        @if(!$isConversionReport)
            <div class="mb-3">
                <p class="font-semibold text-gray-700 mb-1 text-xs">Existing Site Area Measurements: {{ $measurementSummaryText }}</p>
                <p class="text-xs text-gray-600 mb-2">The following existing measurements were observed during the joint site inspection on table A and B.</p>
                <p class="font-semibold text-gray-700 mb-1 text-xs">Table A:</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border border-gray-300 px-2 py-1 w-12 text-center">SN</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">SHARED UTILITIES</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">COUNT</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">DIMENSION</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">MEASUREMENT  (M<sup>2</sup>)</th>
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
                                    <td class="border border-gray-300 px-2 py-1 align-top">
                                        @php
                                            $countValue = $dimension->count ?? null;
                                            $countDisplay = (is_string($countValue) && trim($countValue) !== '') || is_numeric($countValue)
                                                ? trim((string) $countValue)
                                                : null;
                                        @endphp
                                        {{ $countDisplay ?? 'N/A' }}
                                    </td>
                                    <td class="border border-gray-300 px-2 py-1 align-top">
                                        @php
                                            $measurementDimensionRaw = data_get($dimension, 'measurement_dimension') ?? data_get($dimension, 'measurement_dimension_display');
                                            $measurementDimensionDisplay = (is_string($measurementDimensionRaw) && trim($measurementDimensionRaw) !== '')
                                                ? trim((string) $measurementDimensionRaw)
                                                : ($measurementDimensionRaw !== null && $measurementDimensionRaw !== '' ? $measurementDimensionRaw : null);
                                        @endphp
                                        {{ $measurementDimensionDisplay ?? 'N/A' }}
                                    </td>
                                    @php
                                        $dimensionRaw = data_get($dimension, 'dimension');
                                        $dimensionDisplay = is_string($dimensionRaw) ? trim($dimensionRaw) : $dimensionRaw;
                                        $finalMeasurement = $dimensionDisplay !== null && $dimensionDisplay !== ''
                                            ? $dimensionDisplay
                                            : $measurementDimensionDisplay;
                                    @endphp
                                    <td class="border border-gray-300 px-2 py-1 align-top">{{ $finalMeasurement !== null && $finalMeasurement !== '' ? $finalMeasurement : 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="border border-gray-300 px-2 py-2 text-center" colspan="5">
                                        No recorded dimensions were submitted for this inspection.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-3">
                <p class="font-semibold text-gray-700 text-xs mb-1">Table B:</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border border-gray-300 px-2 py-1 w-12 text-center">SN</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">UNIT NO</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">DIMENSION (M²)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($unitMeasurementsCollection as $index => $unit)
                                <tr>
                                    <td class="border border-gray-300 px-2 py-1 align-top">{{ $index + 1 }}</td>
                                    <td class="border border-gray-300 px-2 py-1 align-top">{{ $unit->unit_no ?? 'Unit '.($index + 1) }}</td>
                                    <td class="border border-gray-300 px-2 py-1 align-top">
                                        @if(!empty($unit->measurement))
                                            {{ $unit->measurement }}@if($unit->append_unit) m²@endif
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center border border-gray-300 px-2 py-2">No unit dimensions recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
 

                          <div class="mb-3 border border-gray-200 rounded p-2 bg-gray-50 text-xs space-y-2">
            <div class="flex"><span class="font-semibold mr-2">Existing Road reservation:</span> <span>{{ $report->road_reservation ?? 'Not specified' }}</span></div>
            @if($isConversionReport && $needForEiaValue)
                <div class="flex"><span class="font-semibold mr-2">Need for EIA Report:</span> <span class="{{ $needForEiaValue === 'Yes' ? 'text-red-600 font-bold' : '' }}">{{ $needForEiaValue }}</span></div>
            @endif
            <div class="flex"><span class="font-semibold mr-2">Prevailing Land use:</span> <span>{{ $report->prevailing_land_use ?? 'Not specified' }}</span></div>
            <div class="flex"><span class="font-semibold mr-2">Applied land use:</span> <span>{{ $report->applied_land_use ?? ($application->land_use ?? 'Not specified') }}</span></div>
        </div>

        <div class="mb-3 text-gray-700 section-text">
            <p class="mb-2">
                Based on the analysis conducted to assess compliance with statutory requirements and planning standards,
                the proposed scheme is
                <strong>{{ $complianceStatusLabel }}</strong>
                based on the existing dimensions listed above. The scheme consists of shared facilities and is in conformity
                with existing land use in the area. See dimensions of fragmented units as described by the recommended site plan in Table B above.
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
  <br>  <br>
    
        <div class="mb-4">
                 <div class="border-b border-black w-48"></div>

         
            <p class="font-semibold text-gray-700 text-xs mb-2">{{ $report && $report->inspection_officer ? $report->inspection_officer : 'INSPECTION OFFICER / RANK' }}</p>
           
        
        </div>

 
      
    </div>

    <script>
        const generatedMeta = {
            date: @json($generatedAtDisplay),
            attrDate: @json($generatedAtAttr),
            user: @json($generatedBy),
        };

        [document.documentElement, document.body, document.getElementById('jointInspectionReport')].forEach(el => {
            if (!el) {
                return;
            }

            el.setAttribute('data-date', generatedMeta.date);
            el.setAttribute('data-user', generatedMeta.user);
        });

        document.querySelectorAll('.js-current-date').forEach(el => {
            el.textContent = new Date().toLocaleDateString();
        });
    </script>

    @if(!isset($printMode) || !$printMode)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-YcsIP0Y4+GWXrXyhlHLLJoPLD114F8CbnMD4HzyBbs6k8ZZrVSu2CevulaHYodNs/WWEDuJeCec2bH4C0PizPQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const printBtn = document.getElementById('printReportBtn');
                const downloadBtn = document.getElementById('downloadPdfBtn');
                const report = document.getElementById('jointInspectionReport');
                const rootElement = document.querySelector('.jsi-embedded-root') || document.body;
                let isGeneratingPdf = false;

                if (printBtn) {
                    printBtn.addEventListener('click', (event) => {
                        event.preventDefault();
                        window.print();
                    });
                }

                if (downloadBtn && report) {
                    const ensureHtml2Pdf = (callback) => {
                        if (typeof html2pdf === 'function') {
                            callback();
                            return;
                        }

                        const existingLoader = document.querySelector('script[data-html2pdf-loader="true"]');
                        if (existingLoader) {
                            existingLoader.addEventListener('load', callback, { once: true });
                            existingLoader.addEventListener('error', () => {
                                isGeneratingPdf = false;
                                alert('Unable to load PDF generator. Please use the "Open Print View" button instead.');
                            }, { once: true });
                            return;
                        }

                        const loader = document.createElement('script');
                        loader.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
                        loader.dataset.html2pdfLoader = 'true';
                        loader.onload = callback;
                        loader.onerror = () => {
                            isGeneratingPdf = false;
                            alert('Unable to load PDF generator. Please use the "Open Print View" button instead.');
                        };
                        document.body.appendChild(loader);
                    };

                    downloadBtn.addEventListener('click', (event) => {
                        event.preventDefault();

                        if (isGeneratingPdf) {
                            return;
                        }
                        isGeneratingPdf = true;

                        const generatePdf = () => {
                            const cleanup = () => {
                                rootElement.classList.remove('pdf-export');
                                downloadBtn.disabled = false;
                                downloadBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                                isGeneratingPdf = false;
                            };

                            const options = {
                                margin: [0.5, 0.5, 0.5, 0.5],
                                filename: `joint-site-inspection-report-{{ $application->fileno ?? $application->id }}.pdf`,
                                image: { type: 'jpeg', quality: 0.98 },
                                html2canvas: { scale: 2, useCORS: true, allowTaint: false },
                                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                            };

                            try {
                                rootElement.classList.add('pdf-export');
                                downloadBtn.disabled = true;
                                downloadBtn.classList.add('opacity-60', 'cursor-not-allowed');

                                const worker = html2pdf()
                                    .set(options)
                                    .from(report)
                                    .save();

                                if (worker && typeof worker.then === 'function') {
                                    worker
                                        .then(() => {})
                                        .catch((error) => {
                                            console.error('Failed generating PDF:', error);
                                            alert('Unable to generate PDF in this browser. Please use the "Open Print View" button to print or save as PDF.');
                                        })
                                        .finally(() => {
                                            cleanup();
                                        });
                                } else {
                                    cleanup();
                                }
                            } catch (error) {
                                console.error('Failed generating PDF:', error);
                                cleanup();
                                alert('Unable to generate PDF in this browser. Please use the "Open Print View" button to print or save as PDF.');
                            }
                        };

                        ensureHtml2Pdf(generatePdf);
                    });
                }
            });
        </script>
    @endif

@if($isStandalone)
</body>
</html>
@else
</div>
@endif

@php
    // Initialize critical variables early to prevent undefined warnings when included as a partial
    if (!isset($generatedBy)) {
        $generatedBy = '';
    }
    if (!isset($application)) {
        $application = null;
    }
    if (!isset($printMode)) {
        $printMode = false;
    }
    if (!isset($dimensionsData)) {
        $dimensionsData = [];
    }
    if (!isset($utilitiesData)) {
        $utilitiesData = [];
    }

    $authUser = auth()->user();

    $computedGeneratedBy = trim(collect([
        optional($authUser)->first_name,
        optional($authUser)->last_name,
    ])->filter()->implode(' '));

    if ($computedGeneratedBy === '') {
        $computedGeneratedBy = optional($authUser)->name ?? 'System';
    }

    if (!isset($generatedBy) || trim((string) $generatedBy) === '') {
        $generatedBy = $computedGeneratedBy ?: 'System';
    }

    $generatedTimestamp = \Illuminate\Support\Carbon::now();
    $generatedAtDisplay = $generatedTimestamp->format('F j, Y \\a\\t g:i A');
    $generatedAtAttr = $generatedTimestamp->format('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="en" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Recommendation Memo to DST</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @include('actions.parts.pp_css')
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function planningRecommendationState() {
            return {
                applicationId: '{{ $application->id ?? '' }}',
                isPrintMode: {{ (isset($printMode) && $printMode) ? 'true' : 'false' }},
                dimensions: @json($dimensionsData ?? []),
                utilities: @json($utilitiesData ?? []),
                showDimensionModal: false,
                showUtilityModal: false,
                currentDimension: { id: '', description: '', dimension: '', order: '' },
                currentUtility: { id: '', utility_type: '', dimension: '', dimension_display: '', count: '', order: '' },
                editMode: false,

                loadDimensions() {
                    if (this.isPrintMode || !this.applicationId) {
                        return;
                    }

                    axios.get(`{{ url('planning-tables/dimensions') }}/${this.applicationId}`)
                        .then(response => {
                            // No de-duplication here. Table A lists one row per
                            // fragmented unit, and the endpoint already returns
                            // exactly that (one row per buyer). Collapsing on
                            // description + dimension dropped every unit that
                            // repeats in another section - 70 of 97 rows on
                            // MLKN 3003 - because ST unit numbers restart at U1
                            // in each section, so the printed and on-screen
                            // copies disagreed with the Buyers List.
                            this.dimensions = response.data.map(dim => {
                                const sectionValue = dim.section
                                    ?? dim.section_no
                                    ?? dim.section_number
                                    ?? null;

                                return {
                                    ...dim,
                                    section: sectionValue,
                                    section_number: sectionValue,
                                    dimension_display: dim.dimension_display
                                        ?? dim.dimension
                                        ?? dim.measurement
                                        ?? '',
                                };
                            });
                        })
                        .catch(error => {
                            console.error('Error loading dimensions:', error);
                        });
                },

                loadUtilities() {
                    if (this.isPrintMode || !this.applicationId) {
                        return;
                    }

                    axios.get(`{{ url('planning-tables/utilities') }}/${this.applicationId}`)
                        .then(response => {
                            this.utilities = [...response.data].map((utility, index) => {
                                const dimensionDisplay = utility.dimension_display
                                    ?? utility.dimension
                                    ?? utility.size
                                    ?? '';

                                return {
                                    ...utility,
                                    dimension_display: dimensionDisplay,
                                };
                            });
                        })
                        .catch(error => {
                            console.error('Error loading utilities:', error);
                        });
                },

                formatUtilityLabel(value) {
                    if (value === undefined || value === null) {
                        return '';
                    }

                    const normalized = String(value)
                        .replace(/[_\-]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();

                    if (!normalized) {
                        return '';
                    }

                    return normalized
                        .split(' ')
                        .map(segment => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase())
                        .join(' ');
                },

                formatDimensionValue(displayValue, numericValue, decimals = null) {
                    if (displayValue !== undefined && displayValue !== null) {
                        const stringValue = String(displayValue).trim();
                        if (stringValue !== '') {
                            return stringValue;
                        }
                    }

                    if (numericValue === undefined || numericValue === null || numericValue === '') {
                        return '';
                    }

                    const numeric = Number(numericValue);

                    if (!Number.isNaN(numeric)) {
                        if (decimals !== null && Number.isFinite(decimals)) {
                            return numeric.toFixed(decimals);
                        }

                        return numeric.toString();
                    }

                    return String(numericValue);
                }
            };
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap');

        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 15px;
            line-height: 1.5;
        }

        .heading {
            font-family: 'Libre Baskerville', serif;
        }

        .memo-container {
            width: 21cm;
            min-height: 25.5cm;
            background: white;
            margin: 0 auto;
            padding: 1.2cm;
            position: relative;
            overflow: visible;
            display: flex;
            flex-direction: column;
        }

        .memo-body {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
        }

        .memo-footer {
            margin-top: auto;
            padding-top: 1rem;
            text-align: center;
            font-size: 10px;
            color: #64748b;
            break-inside: avoid;
            position: relative;
            border-top: 1px solid #e2e8f0;
        }

        @media print {
            .memo-container {
                overflow: visible;
                page-break-after: auto;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }

            .table-expanded {
                width: calc(100% + 0.6cm);
                max-width: calc(100% + 0.6cm);
                margin-left: -0.3cm;
                margin-right: -0.3cm;
            }

            .paragraph-expanded {
                width: calc(100% + 0.6cm) !important;
                max-width: calc(100% + 0.6cm) !important;
                margin-left: -0.3cm !important;
                margin-right: -0.3cm !important;
                padding-left: 0.3cm !important;
                padding-right: 0.3cm !important;
            }
        }

        .header-line {
            height: 3px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
        }

        .underline {
            text-decoration: underline;
            text-decoration-thickness: 1px;
        }

        .corner-logo {
            position: absolute;
            width: 80px;
            height: auto;
            z-index: 10;
        }

        .logo-top-left { top: 0.5cm; left: 0.5cm; }
        .logo-top-right { top: 0.5cm; right: 0.5cm; }
        .logo-bottom-left { bottom: 0.5cm; left: 0.5cm; }
        .logo-bottom-right { bottom: 0.5cm; right: 0.5cm; }

        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 10px;
            margin: 8px 0;
        }

        .table-expanded {
            width: calc(100% + 0.6cm);
            max-width: calc(100% + 0.6cm);
            margin-left: -0.3cm;
            margin-right: -0.3cm;
        }

        .paragraph-expanded {
            width: calc(100% + 0.6cm);
            max-width: calc(100% + 0.6cm);
            margin-left: -0.3cm;
            margin-right: -0.3cm;
            padding-left: 0.3cm;
            padding-right: 0.3cm;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: left;
        }

        th {
            background-color: #f1f5f9;
            font-weight: 600;
            font-size: 10px;
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            width: 200px;
            margin: 15px 0 5px 0;
        }

        .signature-section {
            break-inside: avoid;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                width: 21cm;
            }

            .memo-container {
                width: 100%;
                min-height: 25.5cm;
                padding: 0.4cm;
                margin: 0;
                overflow: visible;
                display: flex;
                flex-direction: column;
            }

            .memo-body {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
            }

            .no-print {
                display: none !important;
            }

            .corner-logo {
                width: 70px !important;
            }

            table {
                font-size: 9px;
            }

            th, td {
                padding: 3px 5px;
            }

            .table-expanded {
                width: calc(100% + 0.6cm);
                max-width: calc(100% + 0.6cm);
                margin-left: -0.3cm;
                margin-right: -0.3cm;
            }

            .paragraph-expanded {
                width: calc(100% + 0.6cm) !important;
                max-width: calc(100% + 0.6cm) !important;
                margin-left: -0.3cm !important;
                margin-right: -0.3cm !important;
                padding-left: 0.3cm !important;
                padding-right: 0.3cm !important;
            }

            @page {
                size: A4 portrait;
                margin: 0.1cm 0 1cm 0;
                @bottom-center {
                    content: "Generated on: " attr(data-date) " by " attr(data-user);
                    font-size: 10px;
                    color: #666;
                }
            }

            @page :first {
                margin: 1.2cm 0.4cm 2cm 0.4cm;
            }
        }

        .serial-box {
            position: absolute;
            top: 2.8cm;
            right: 0.5cm;
            border: 1px solid #1e3a8a;
            padding: 3px 6px;
            min-width: 100px;
            font-size: 10px;
            background: white;
            z-index: 20;
        }
        .serial-box .serial-label {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #1e3a8a;
            padding-bottom: 2px;
            margin-bottom: 3px;
            font-size: 10px;
            color: #1e3a8a;
        }
        .serial-code-wrap {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .serial-fraction {
            line-height: 1;
            color: #1e293b;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            font-weight: 900;
        }
        .serial-fraction .frac-top {
            border-bottom: 1.5px solid #1e293b;
            padding-bottom: 1px;
            font-size: 8px;
        }
        .serial-fraction .frac-bot {
            padding-top: 1px;
            font-size: 8px;
        }
        .serial-digits {
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.1em;
            color: #1e293b;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
@php
    $rawDimensionEntries = collect($dimensionsData ?? []);

    $dimensionIdentifier = function ($entry) {
        if (is_array($entry)) {
            return $entry['description'] ?? $entry['unit_no'] ?? '';
        }

        if (is_object($entry)) {
            return $entry->description ?? $entry->unit_no ?? '';
        }

        return '';
    };

    $shouldSortDimensions = $rawDimensionEntries->count() > 0
        && $rawDimensionEntries->every(function ($entry) use ($dimensionIdentifier) {
            $candidate = $dimensionIdentifier($entry);
            $normalized = is_string($candidate) ? trim($candidate) : '';

            if ($normalized === '') {
                return false;
            }

            return preg_match('/^\d+$/', $normalized) === 1;
        });

    $orderedDimensionEntries = $shouldSortDimensions
        ? $rawDimensionEntries->sortBy(function ($entry) use ($dimensionIdentifier) {
            $candidate = $dimensionIdentifier($entry);
            $normalized = is_string($candidate) ? trim($candidate) : '';
            return str_pad($normalized, 10, '0', STR_PAD_LEFT);
        })->values()
        : $rawDimensionEntries->values();

    $dimensionsData = $orderedDimensionEntries->map(function ($item, $index) use ($shouldSortDimensions) {
        if (is_object($item)) {
            $item = (array) $item;
        }

        $description = $item['description'] ?? ($item['unit_no'] ?? null);
        $dimension = $item['dimension_numeric'] ?? ($item['dimension'] ?? ($item['measurement'] ?? null));
        $dimensionDisplay = $item['dimension_display']
            ?? $item['dimension_raw']
            ?? ($item['dimension'] ?? ($item['measurement'] ?? null));

        if ($description === '' || $description === null) {
            $description = null;
        }

        if ($dimension === '' || $dimension === null) {
            $dimension = null;
        }
 
        $sectionValue = $item['section']
            ?? $item['section_no']
            ?? $item['section_number']
            ?? null;

        return [
            'sn' => $shouldSortDimensions
                ? ($index + 1)
                : (isset($item['sn']) && is_numeric($item['sn']) ? (int) $item['sn'] : ($index + 1)),
            'description' => $description,
            'dimension' => $dimension,
            'dimension_display' => $dimensionDisplay,
            'count' => $item['count'] ?? 1,
            'section' => $sectionValue,
            'section_number' => $sectionValue,
        ];
    })->filter(function ($item) {
        return !empty($item['description']) || !empty($item['dimension']);
    })->values()->all();

    if (isset($printMode) && $printMode) {
        $applicationId = $application->id ?? null;

        if (!empty($applicationId)) {
            $jsiReport = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('joint_site_inspection_reports')
                ->where('application_id', $applicationId)
                ->first();

            if ($jsiReport && !empty($jsiReport->existing_site_measurement_entries)) {
                $rawEntries = $jsiReport->existing_site_measurement_entries;

                if (is_string($rawEntries)) {
                    $decodedEntries = json_decode($rawEntries, true);
                    $rawEntries = json_last_error() === JSON_ERROR_NONE ? $decodedEntries : [];
                } elseif ($rawEntries instanceof \Traversable) {
                    $rawEntries = iterator_to_array($rawEntries);
                } elseif (!is_array($rawEntries)) {
                    $rawEntries = [];
                }

                $utilitiesData = collect($rawEntries)
                    ->map(function ($entry, $index) {
                        if (is_object($entry)) {
                            $entry = (array) $entry;
                        }

                        if (!is_array($entry)) {
                            return null;
                        }

                        $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
                        $dimensionRaw = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
                        $dimensionNumeric = $dimensionRaw !== '' && is_numeric($dimensionRaw)
                            ? (float) $dimensionRaw
                            : null;

                        $countRaw = $entry['count'] ?? 1;
                        $countValue = 1;

                        if (is_numeric($countRaw)) {
                            $countValue = (int) $countRaw;
                        } elseif (is_string($countRaw) && trim($countRaw) !== '') {
                            $countValue = trim($countRaw);
                        }

                        if ($description === '' && $dimensionRaw === '' && empty($entry['utility_type'])) {
                            return null;
                        }

                        return [
                            'sn' => isset($entry['sn']) && is_numeric($entry['sn']) ? (int) $entry['sn'] : ($index + 1),
                            'utility_type' => $entry['utility_type']
                                ?? $description
                                ?? ($entry['label'] ?? null),
                            'dimension' => $dimensionNumeric ?? ($dimensionRaw === '' ? null : $dimensionRaw),
                            'dimension_numeric' => $dimensionNumeric,
                            'dimension_display' => $dimensionRaw === '' && $dimensionNumeric !== null
                                ? (string) $dimensionNumeric
                                : $dimensionRaw,
                            'dimension_raw' => $dimensionRaw === '' ? null : $dimensionRaw,
                            'count' => $countValue,
                            'block' => $entry['block'] ?? null,
                            'section' => $entry['section'] ?? null,
                        ];
                    })
                    ->filter(function ($item) {
                        if (!is_array($item)) {
                            return false;
                        }

                        $utilityType = trim((string) ($item['utility_type'] ?? ''));

                        // __existing_structure__ is a JSI-card internal marker,
                        // not an approved shared utility for Table B.
                        return $utilityType !== '' && !str_starts_with($utilityType, '__');
                    })
                    ->values()
                    ->all();
            }
        }
    }

    $utilitiesData = collect($utilitiesData ?? [])->map(function ($item, $index) {
        if (is_object($item)) {
            $item = (array) $item;
        }

        $utilityType = $item['utility_type'] ?? ($item['name'] ?? $item['label'] ?? null);

        if ($utilityType === '' || $utilityType === null) {
            return null;
        }

        $dimensionValue = $item['dimension_numeric']
            ?? ($item['dimension'] ?? ($item['size'] ?? null));

        $dimensionDisplay = $item['dimension_display']
            ?? $item['dimension_raw']
            ?? ($item['dimension'] ?? ($item['size'] ?? null));

        return [
            'sn' => $index + 1,
            'utility_type' => $utilityType,
            'dimension' => $dimensionValue,
            'dimension_display' => $dimensionDisplay,
            'count' => $item['count'] ?? 1,
            'block' => $item['block'] ?? null,
            'section' => $item['section'] ?? null,
        ];
    })->filter(function ($item) {
        $utilityType = trim((string) ($item['utility_type'] ?? ''));

        // Keep this second guard for non-print and legacy data paths too.
        return $utilityType !== '' && !str_starts_with($utilityType, '__');
    })->values()->all();

    $formatDimensionDisplay = function ($displayValue, $numericValue = null, $decimals = null) {
        $stringDisplay = null;

        if ($displayValue !== null && $displayValue !== '') {
            $stringDisplay = is_string($displayValue)
                ? trim($displayValue)
                : trim((string) $displayValue);
        }

        if ($stringDisplay !== null && $stringDisplay !== '') {
            return $stringDisplay;
        }

        if ($numericValue === null || $numericValue === '') {
            return '';
        }

        if (is_numeric($numericValue)) {
            if ($decimals !== null) {
                return number_format((float) $numericValue, (int) $decimals);
            }

            $normalized = (string) $numericValue;

            if (strpos($normalized, '.') !== false) {
                $normalized = rtrim(rtrim($normalized, '0'), '.');
            }

            return $normalized === '' ? '0' : $normalized;
        }

        $numericString = is_string($numericValue) ? trim($numericValue) : (string) $numericValue;

        if ($numericString === '') {
            return '';
        }

        if (is_numeric($numericString)) {
            if ($decimals !== null) {
                return number_format((float) $numericString, (int) $decimals);
            }

            if (strpos($numericString, '.') !== false) {
                $numericString = rtrim(rtrim($numericString, '0'), '.');
            }

            return $numericString === '' ? '0' : $numericString;
        }

        return $numericString;
    };

    if (!isset($generatedBy) || trim((string) $generatedBy) === '') {
        $generatedByParts = array_filter([
            optional(auth()->user())->first_name ?? null,
            optional(auth()->user())->last_name ?? null,
        ]);

        $generatedBy = trim(implode(' ', $generatedByParts));

        if ($generatedBy === '') {
            $generatedBy = optional(auth()->user())->name ?? 'System';
        }

        if (!$generatedBy || $generatedBy === '') {
            $generatedBy = 'System';
        }
    }

    if (!isset($generatedAtDisplay) || trim((string) $generatedAtDisplay) === '') {
        $fallbackTimestamp = \Illuminate\Support\Carbon::now();
        $generatedAtDisplay = $fallbackTimestamp->format('F j, Y \\a\\t g:i A');
    }

    if (!isset($generatedAtAttr) || trim((string) $generatedAtAttr) === '') {
        $generatedAtAttr = \Illuminate\Support\Carbon::now()->format('d/m/Y H:i');
    }

    $displayFileNumber = optional($application)->fileno
        ?? optional($application)->np_fileno
        ?? optional($application)->new_primary_file_number
        ?? 'N/A';

    $primaryDateCandidates = [
        $application->planning_approval_date ?? null,
        $application->approval_date ?? null,
        $application->application_date ?? null,
        $application->subapplication_date ?? null,
        $application->created_at ?? null,
    ];

    $applicationDateRaw = collect($primaryDateCandidates)->first(function ($date) {
        return !empty($date) && !in_array($date, ['0000-00-00', '0000-00-00 00:00:00']);
    });

    $formattedApplicationDate = null;

    if ($applicationDateRaw) {
        try {
            $formattedApplicationDate = \Illuminate\Support\Carbon::parse($applicationDateRaw)->format('d F Y');
        } catch (\Exception $exception) {
            $formattedApplicationDate = null;
        }
    }

    $securityCode = app(\App\Services\SecurityCodeService::class)->getOrGenerateForDocument(
        (string) $displayFileNumber,
        (int) ($application->id ?? 0),
        'ST Planning Recomm'
    );
    $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);
@endphp
<body class="py-2" data-planning-content data-application-id="{{ $application->id ?? '' }}"
    data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}"
  x-data="planningRecommendationState()"
  x-init="() => {
    if (!isPrintMode) {
        loadDimensions();
        loadUtilities();
    }
  }"
>
    @if(!isset($printMode) || !$printMode)
    <div class="no-print flex justify-center mb-2">
    <button onclick="window.open('{{ route('planning-recommendation.print', $application->id) }}?url=print', '_blank')" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1 px-4 rounded shadow transition duration-200 flex items-center text-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print Document
        </button>
    </div>
    @endif

    <div class="memo-container">
        <img class="corner-logo logo-top-left" src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Ministry Left Logo" onerror="this.style.display='none'">
        <img class="corner-logo logo-top-right" src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="Ministry Right Logo" onerror="this.style.display='none'">

        <div class="serial-box">
            <div class="serial-label">Serial No:</div>
            <div class="serial-code-wrap">
                <span class="serial-fraction">
                    <span class="frac-top">{{ $sc['alphabet'] }}</span>
                    <span class="frac-bot">{{ $sc['digits_start'] }}</span>
                </span>
                <span class="serial-digits">{{ $sc['digits_end'] }}</span>
            </div>
        </div>

        <div class="memo-body">
    

            <div class="text-center mb-4">
                <br>
                <h1 class="heading text-2xl font-bold text-gray-800 mb-1 underline">SECTIONAL TITLING ONE STOP SHOP</h1>
                <br><br>
                <p class="text-lg font-semibold text-gray-700 mt-3 text-left">DIRECTOR SECTIONAL TITLING,</p>
            </div>
            <div class="mb-4">
                <p class="text-lg font-semibold text-gray-800 underline text-center">
                    RE: APPLICATION FOR SECTIONAL TITLING PLANNING RECOMMENDATION APPROVAL<br>
                    IN RESPECT OF FILE NO <span class="font-bold">{{ $displayFileNumber }}</span>
                </p>
            </div>
            <div style="width: calc(100% + 0.6cm); max-width: calc(100% + 0.6cm); margin-left: -0.3cm; margin-right: -0.3cm; padding-left: 0.3cm; padding-right: 0.3cm;">
                <p class="mb-2 text-base leading-relaxed">
                    Kindly refer to application dated <span class="font-bold">{{ $formattedApplicationDate ?? 'N/A' }}</span> in relation to the above subject. I am directed to convey the approval for fragmentation of file no <strong>{{ $displayFileNumber }}</strong> with plot no <strong>{{ $application->property_plot_no ?? 'N/A' }}</strong> and ST scheme no <strong>{{ $application->scheme_no ?? 'N/A' }}</strong> located at <span class="font-bold">
                @if(!empty($application->property_street_name) || !empty($application->property_lga))
                    {{ ucwords(strtolower($application->property_street_name ?? '')) }} {{ ucwords(strtolower($application->property_lga ?? '')) }}
                @else
                    {{ $application->property_location ?? 'N/A' }}
                @endif
                </span> into multiple units/ sections as described in table A. All sections / units have met physical planning requirement for statutory right of occupancy with approved shared utilities and compounds as described in table B.
                </p>

                <p class="mb-2">In view of the above and section seven (7) of kano state sectional and systematic land titling registration law 2024 (1446 AH) scheme and its fragmentation (based on approved Site plan dimensions) are suitable for approval.</p>
            </div>

            <div class="mb-4">
                <h3 class="font-bold text-gray-800 mb-1 text-sm text-left">1. TABLE A: DIMENSIONS OF FRAGMENTED UNITS</h3>
                <table class="table-expanded">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>UNIT </th>
                        <th>SECTION NO</th>
                        <th>DIMENSIONS  (M²)</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($printMode) && $printMode)
                        @php
                            // Ensure we do not display buyer-list records in the dimensions table.
                            $displayDimensions = collect($dimensionsData ?? [])->filter(function ($d) {
                                if (is_object($d)) {
                                    $d = (array) $d;
                                }

                                // Exclude records that look like buyer-list entries
                                if (isset($d['buyer_name']) || isset($d['buyer_title']) || isset($d['buyer_fullname'])) {
                                    return false;
                                }

                                return true;
                            })->values()->all();
                        @endphp

                        @if(!empty($displayDimensions))
                            @foreach($displayDimensions as $index => $dimension)
                                <tr>
                                    <td><strong>{{ $dimension['sn'] ?? ($index + 1) }}</strong></td>
                                    <td><strong>{{ $dimension['description'] ?? ($dimension['unit_no'] ?? '') }}</strong></td>
                                    <td><strong>{{ $dimension['section_number'] ?? $dimension['section'] ?? 'N/A' }}</strong></td>
                                    <td><strong>{{ $formatDimensionDisplay($dimension['dimension_display'] ?? null, $dimension['dimension'] ?? null, 2) }}</strong></td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="4"><em>No recorded unit dimensions available for this application.</em></td>
                            </tr>
                        @endif
                    @else
                        <template x-if="dimensions.length > 0">
                            <template x-for="(dimension, index) in dimensions" :key="`${dimension.description || 'unit'}-${dimension.dimension || index}-${index}`">
                                <tr>
                                    <td><strong x-text="dimension.sn ?? (index + 1)"></strong></td>
                                    <td><strong x-text="dimension.description || ''"></strong></td>
                                    <td><strong x-text="dimension.section_number || dimension.section || 'N/A'"></strong></td>
                                    <td><strong x-text="formatDimensionValue(dimension.dimension_display, dimension.dimension, 2)"></strong></td>
                                </tr>
                            </template>
                        </template>
                        <template x-if="dimensions.length === 0">
                            <template>
                                <tr>
                                    <td colspan="4"><em>No recorded unit dimensions available for this application.</em></td>
                                </tr>
                            </template>
                        </template>
                    @endif
                </tbody>
            </table>
        </div>

        <div>
            <h3 class="font-bold text-gray-800 mb-1 text-sm">2. TABLE B: UTILITY AND SHARED COMPOUND DESCRIPTION</h3>
            <table class="table-expanded">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>SHARED UTILITY TYPE</th>
                        <th>DIMENSION (M²)</th>
                        <th>COUNT WITHIN SCHEME</th>
                        <th>BLOCK</th>
                        <th>SECTION</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($printMode) && $printMode)
                        @php
                            $formatUtilityLabel = function ($value) {
                                if ($value === null || $value === '') {
                                    return '';
                                }

                                $normalized = preg_replace('/[_\-]+/', ' ', trim((string) $value));
                                $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');

                                if ($normalized === '') {
                                    return '';
                                }

                                return collect(explode(' ', $normalized))
                                    ->filter()
                                    ->map(function ($segment) {
                                        return ucfirst(strtolower($segment));
                                    })
                                    ->implode(' ');
                            };
                        @endphp
                        @if(!empty($utilitiesData))
                            @foreach($utilitiesData as $index => $utility)
                                <tr>
                                    <td><strong>{{ $utility['sn'] ?? ($index + 1) }}</strong></td>
                                    <td><strong>{{ $formatUtilityLabel($utility['utility_type'] ?? null) ?: 'Utility ' . ($index + 1) }}</strong></td>
                                    <td><strong>{{ $formatDimensionDisplay($utility['dimension_display'] ?? null, $utility['dimension'] ?? null) }}</strong></td>
                                    <td><strong>{{ $utility['count'] ?? 1 }}</strong></td>
                                    <td><strong>{{ $utility['block'] ?? '' }}</strong></td>
                                    <td><strong>{{ $utility['section'] ?? '' }}</strong></td>
                                </tr>
                            @endforeach
                        @else
                         <p> no utilities data </p>
                        @endif
                    @else
                        <template x-if="utilities.length > 0">
                            <template x-for="(utility, index) in utilities" :key="utility.id || `utility-${index}`">
                                <tr>
                                    <td><strong x-text="index + 1"></strong></td>
                                    <td><strong x-text="formatUtilityLabel(utility.utility_type) || `Utility ${index + 1}`"></strong></td>
                                    <td><strong x-text="formatDimensionValue(utility.dimension_display, utility.dimension)"></strong></td>
                                    <td><strong x-text="utility.count || 1"></strong></td>
                                    <td><strong x-text="utility.block || ''"></strong></td>
                                    <td><strong x-text="utility.section || ''"></strong></td>
                                </tr>
                            </template>
                        </template>
                        <template x-if="utilities.length === 0">
                            <template>
                                <tr>
                                    <td colspan="6"><em>No recorded shared utilities available for this application.</em></td>
                                </tr>
                            </template>
                    @endif
                </tbody>
            </table>
           
            
            <div class="signature-section mb-6 mt-6">
            <div class="flex justify-between">
                <div class="text-center w-1/2 pr-4">
                    <div class="signature-line"></div>
                        <p class="text-xs text-gray-600 mt-1">Coordinator <strong>OSS</strong></p>
                </div>
                <div class="text-center w-1/2 pl-4">
                    <div class="signature-line"></div>
                    <p class="text-xs text-gray-600 mt-1">Chairman OSS</p>
                </div>
            </div>
        </div>
        </div>

        <!-- Footer with generated date -->
        <div class="memo-footer" data-date="{{ $generatedAtDisplay }}" data-user="{{ $generatedBy }}">
       
        </div>
    </div>

    @include('actions.parts.pp_js')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateElement = document.getElementById('current-date');
            if (dateElement) {
                dateElement.textContent = new Date().toLocaleDateString();
            }

            @if(isset($printMode) && $printMode)
                setTimeout(() => window.print(), 800);
            @endif
        });
    </script>
</body>
</html>

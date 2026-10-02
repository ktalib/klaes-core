<!DOCTYPE html>
<html lang="en">
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
    @php
        $isPrintMode = isset($printMode) && $printMode;
        $blockNumberDefault = $application->block_number ?? '0';
        $dimensionsData = collect($dimensionsData ?? [])->map(function ($item, $index) use ($blockNumberDefault) {
            if (is_object($item)) {
                $item = (array) $item;
            }

            $description = $item['description']
                ?? $item['unit_no']
                ?? $item['unit_number']
                ?? null;

            $dimensionValue = $item['dimension']
                ?? $item['measurement']
                ?? $item['unit_size']
                ?? null;

            $sectionValue = $item['section']
                ?? $item['section_no']
                ?? $item['block_number']
                ?? $item['block']
                ?? $blockNumberDefault;

            if ($description === '' || $description === null) {
                $description = null;
            }

            if ($dimensionValue === '' || $dimensionValue === null) {
                $dimensionValue = null;
            }

            return [
                'sn' => isset($item['sn']) && is_numeric($item['sn']) ? (int) $item['sn'] : ($index + 1),
                'description' => $description,
                'dimension' => $dimensionValue,
                'count' => $item['count'] ?? 1,
                'section' => $sectionValue,
            ];
        })->filter(function ($item) {
            return !empty($item['description']) || !empty($item['dimension']);
        })->values()->all();
    @endphp
    @if(!$isPrintMode)
    <script>
        function subPlanningRecommendationState() {
            const defaultSection = '{{ $application->block_number ?? '0' }}';
            return {
                applicationId: '{{ $application->id ?? '' }}',
                showDimensionModal: false,
                showUtilityModal: false,
                dimensions: @json($dimensionsData),
                utilities: @json($utilitiesData ?? []),
                currentDimension: { id: '', description: '', dimension: '', section: '', order: '' },
                currentUtility: { id: '', utility_type: '', dimension: '', count: '', order: '' },
                editMode: false,

                loadDimensions() {
                    if (!this.applicationId) return;

                    axios.get(`{{ url('planning-tables/sub/dimensions') }}/${this.applicationId}`)
                        .then(response => {
                            const uniqueDimensions = [];
                            const seenKeys = new Set();

                            response.data.forEach(dim => {
                                const normalizedDim = Object.assign({}, dim);
                                normalizedDim.section = normalizedDim.section
                                    ?? normalizedDim.section_no
                                    ?? normalizedDim.block_number
                                    ?? normalizedDim.block
                                    ?? defaultSection;

                                const key = `${normalizedDim.description || ''}-${normalizedDim.dimension || ''}-${normalizedDim.section}`;
                                if (!seenKeys.has(key)) {
                                    uniqueDimensions.push(normalizedDim);
                                    seenKeys.add(key);
                                }
                            });

                            this.dimensions = uniqueDimensions;
                        })
                        .catch(error => {
                            console.error('Error loading dimensions:', error);
                        });
                },

                loadUtilities() {
                    if (!this.applicationId) return;

                    const url = `{{ url('planning-tables/sub/utilities') }}/${this.applicationId}`;

                    axios.get(url)
                        .then(response => {
                            this.utilities = [...response.data];
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
                }
            };
        }
    </script>
    @endif
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: ;
            line-height: 1.5;
        }
        
        .heading {
            font-family: 'Libre Baskerville', serif;
        }
        
        .memo-container {
            width: 21cm;
            min-height: 29.7cm;
            background: white;
            margin: 0 auto;
            padding: 1.2cm;
            position: relative;
            overflow: visible;
        }
        
        /* Allow page breaks for long tables */
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
        }
        
        .header-line {
            height: 3px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
        }
        
        .underline {
            text-decoration: underline;
            text-decoration-thickness: 1px;
        }
        
        /* Logo positioning - ONLY LOGO CSS */
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
        
        th, td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            font-size: 10px;
        }
        
        .dotted-line {
            border-bottom: 1px dotted #94a3b8;
            display: inline-block;
            min-width: 100px;
            height: 14px;
            vertical-align: bottom;
            margin: 0 4px;
        }
        
        .signature-line {
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            width: 200px;
            margin: 15px 0 5px 0;
        }
        
        /* Hide print button when printing */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                width: 21cm;
            }
            
            .memo-container {
                width: 100%;
                min-height: 29.7cm;
                padding: 1.2cm;
                margin: 0;
                overflow: visible;
            }
            
            .no-print {
                display: none !important;
            }
            
            .corner-logo {
                width: 70px !important;
            }
            
            /* Make tables more compact in print */
            table {
                font-size: 9px;
            }
            
            th, td {
                padding: 3px 5px;
            }
            
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    </style>
</head>
<body class="py-2" data-planning-content data-application-id="{{ $application->id ?? '' }}"
    @if(!$isPrintMode)
        x-data="subPlanningRecommendationState()"
        x-init="() => {
            if (applicationId) {
                loadDimensions();
                loadUtilities();
            }
        }"
    @endif
>
    @php
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
    @endphp

 
    <div class="no-print flex justify-center mb-2">
        <button onclick="window.open('{{ route('sub_pr_memos.print', $application->id) }}', '_blank')" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1 px-4 rounded shadow transition duration-200 flex items-center text-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print Document
        </button>
    </div>
    

    <!-- Memo Container -->
    <div class="memo-container">
        <!-- Corner Logos -->
        <img class="corner-logo logo-top-left" src="{{ asset('public/assets/logo/ministry1.jpg') }}" alt="Ministry Left Logo" onerror="this.style.display='none'">
        <img class="corner-logo logo-top-right" src="{{ asset('public/assets/logo/ministry2.jpeg') }}" alt="Ministry Right Logo" onerror="this.style.display='none'">
        {{-- <img class="corner-logo logo-bottom-left" src="{{ asset('assets/logo/klaes.png') }}" alt="Brand Left Logo" onerror="this.style.display='none'">
        <img class="corner-logo logo-bottom-right" src="{{ asset('assets/logo/las.jpeg') }}" alt="Brand Right Logo" onerror="this.style.display='none'"> --}}
        
      
        
        <!-- Header Section -->
        <div class="text-center mb-4">
             <br>
                <h1 class="heading text-2xl font-bold text-gray-800 mb-1 underline">SECTIONAL TITLING ONE STOP SHOP</h1>
                <br><br>
            <p class="text-md font-semibold text-gray-700 mt-3 text-left">DIRECTOR SECTIONAL TITLING,</p>
        </div>

        <!-- Reference Section -->
        <div class="mb-4">
            <p class="font-semibold text-gray-800 underline text-center">
                RE: APPLICATION FOR SECTIONAL TITLING PLANNING RECOMMENDATION APPROVAL<br>
                IN RESPECT OF FILE NO <span class="font-bold">{{ $application->fileno ?? 'N/A' }}</span>
            </p>
        </div>

        <!-- Main Content -->
        <div class="mb-3 text-gray-700">
            <p class="mb-2">
               Kindly refer to application dated <span class="font-bold">{{ $formattedApplicationDate ?? 'N/A' }}</span> in relation to the above subject. I am directed to convey the approval for fragmentation of <strong>FILE NO {{ $application->fileno ?? 'N/A' }}</strong> with plot no <strong>{{ $application->property_plot_no ?? 'N/A' }}</strong> and ST scheme no <strong>{{ $application->scheme_no ?? 'N/A' }}</strong> located at <span class="font-bold">
                @if(!empty($application->property_street_name) || !empty($application->property_lga))
                    {{ ucwords(strtolower($application->property_street_name ?? '')) }} {{ ucwords(strtolower($application->property_lga ?? ''))     }}
                @else
                    {{ $application->property_location ?? 'N/A' }}
                @endif
                </span> into multiple units/ sections as described in table A. All sections / units have met physical planning requirement for statutory right of occupancy with approved shared utilities and compounds as described in table B .
            </p>
            
            <p class="mb-2">In view of the above and section seven (7) of kano state sectional and systematic land titling registration law 2024 (1446 AH) scheme and its fragmentation (based on approved Site plan dimensions) are suitable for approval if you have no objection.</p>
        </div>

        <!-- Table A -->
        <div class="mb-4">
            <h3 class="font-bold text-gray-800 mb-1 text-sm text-left">1. TABLE A: DIMENSIONS OF FRAGMENTED UNITS</h3>
            <table>
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>UNIT </th>
                        <th>SECTION NO</th>
                        <th>DIMENSIONS  (M²)</th>
                    </tr>
                </thead>
                <tbody>
                    @if($isPrintMode)
                        @php
                            $blockNumberDefault = $application->block_number ?? '0';
                            $printDimensions = collect($dimensionsData ?? [])->map(function ($item) use ($blockNumberDefault) {
                                if (is_array($item)) {
                                    $item = (object) $item;
                                }

                                return (object) [
                                    'description' => $item->description
                                        ?? $item->unit_number
                                        ?? $item->unit_no
                                        ?? null,
                                    'dimension' => $item->dimension
                                        ?? $item->measurement
                                        ?? $item->unit_size
                                        ?? null,
                                    'section' => $item->section
                                        ?? $item->section_no
                                        ?? $item->block_number
                                        ?? $item->block
                                        ?? $blockNumberDefault,
                                ];
                            })->filter(function ($dimension) {
                                return !empty($dimension->description) || !empty($dimension->dimension);
                            })->values();

                            if ($printDimensions->isEmpty()) {
                                $unitNumber = $application->unit_number ?? null;
                                $unitSize = $application->unit_size ?? null;

                                if (!empty($unitNumber) || !empty($unitSize)) {
                                    $printDimensions = collect([
                                        (object) [
                                            'description' => $unitNumber ?: 'Unit 1',
                                            'dimension' => $unitSize,
                                            'section' => $application->block_number ?? '0',
                                        ],
                                    ]);
                                }

                                if ($printDimensions->isEmpty() && isset($application->main_application_id)) {
                                    $printDimensions = DB::connection('sqlsrv')
                                        ->table('st_unit_measurements')
                                        ->select('unit_no as description', 'measurement as dimension')
                                        ->where('application_id', $application->main_application_id)
                                        ->orderBy('unit_no')
                                        ->get();

                                    $printDimensions = collect($printDimensions)->map(function ($dimension) use ($blockNumberDefault) {
                                        return (object) [
                                            'description' => $dimension->description ?? $dimension->unit_no ?? null,
                                            'dimension' => $dimension->dimension ?? $dimension->measurement ?? null,
                                            'section' => $dimension->section
                                                ?? $dimension->section_no
                                                ?? $dimension->block_number
                                                ?? $dimension->block
                                                ?? $blockNumberDefault,
                                        ];
                                    })->filter(function ($dimension) {
                                        return !empty($dimension->description) || !empty($dimension->dimension);
                                    })->values();
                                }
                            }
                        @endphp

                        @if($printDimensions->count() > 0)
                            @foreach($printDimensions as $index => $dimension)
                            <tr>
                                <td><strong>{{ $index + 1 }}</strong></td>
                                <td><strong>{{ $dimension->description ?? '' }}</strong></td>
                                <td><strong>{{ $dimension->section ?? ($application->block_number ?? '0') }}</strong></td>
                                <td><strong>{{ number_format($dimension->dimension ?? 0, 2) }}</strong></td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td><strong>1</strong></td>
                                <td><strong>230</strong></td>
                                <td><strong>{{ $application->block_number ?? '0' }}</strong></td>
                                <td><strong>6</strong></td>
                            </tr>
                            <tr>
                                <td><strong>2</strong></td>
                                <td><strong>231</strong></td>
                                <td><strong>{{ $application->block_number ?? '0' }}</strong></td>
                                <td><strong>5.8</strong></td>
                            </tr>
                            <tr>
                                <td><strong>3</strong></td>
                                <td><strong>232</strong></td>
                                <td><strong>{{ $application->block_number ?? '0' }}</strong></td>
                                <td><strong>15</strong></td>
                            </tr>
                        @endif
                    @else
                        <!-- Dynamic dimensions data -->
                        <template x-if="dimensions.length > 0">
                            <template x-for="(dimension, index) in dimensions" :key="dimension.description + '-' + dimension.dimension">
                                <tr>
                                    <td><strong x-text="index + 1"></strong></td>
                                    <td><strong x-text="dimension.description || 'Unit ' + (index + 1)"></strong></td>
                                    <td><strong x-text="dimension.section ?? dimension.block ?? dimension.block_number ?? '{{ $application->block_number ?? '0' }}'"></strong></td>
                                    <td><strong x-text="parseFloat(dimension.dimension || 0).toFixed(2)"></strong></td>
                                </tr>
                            </template>
                        </template>

                        <!-- Fallback static data when no dynamic data -->
                        <template x-if="dimensions.length === 0">
                            <template>
                                <tr>
                                    <td><strong>1</strong></td>
                                    <td><strong>230</strong></td>
                                    <td><strong>{{ $application->block_number ?? '0' }}</strong></td>
                                    <td><strong>6</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>2</strong></td>
                                    <td><strong>231</strong></td>
                                    <td><strong>{{ $application->block_number ?? '0' }}</strong></td>
                                    <td><strong>5.8</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>3</strong></td>
                                    <td><strong>232</strong></td>
                                    <td><strong>{{ $application->block_number ?? '0' }}</strong></td>
                                    <td><strong>15</strong></td>
                                </tr>
                            </template>
                        </template>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Table B -->
        <div>
            <h3 class="font-bold text-gray-800 mb-1 text-sm">2. TABLE B: UTILITY AND SHARED COMPOUND DESCRIPTION</h3>
            <table>
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
                    @if($isPrintMode)
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

                            $printUtilities = collect($utilitiesData ?? [])->map(function ($item) {
                                if (is_array($item)) {
                                    $item = (object) $item;
                                }

                                return (object) [
                                    'utility_type' => $item->utility_type ?? null,
                                    'dimension' => $item->dimension ?? 0,
                                    'count' => $item->count ?? 1,
                                    'block' => $item->block ?? null,
                                    'section' => $item->section ?? null,
                                ];
                            })->filter(function ($utility) {
                                return !empty($utility->utility_type);
                            })->values();

                            if ($printUtilities->isEmpty() && isset($application->id)) {
                                $fallbackUtilities = DB::connection('sqlsrv')
                                    ->table('shared_utilities')
                                    ->where('sub_application_id', $application->id)
                                    ->orderBy('order')
                                    ->get();

                                if ($fallbackUtilities->isEmpty() && isset($application->main_application_id)) {
                                    $fallbackUtilities = DB::connection('sqlsrv')
                                        ->table('shared_utilities')
                                        ->where('application_id', $application->main_application_id)
                                        ->orderBy('order')
                                        ->get();
                                }

                                $printUtilities = collect($fallbackUtilities)->map(function ($utility) {
                                    return (object) [
                                        'utility_type' => $utility->utility_type ?? null,
                                        'dimension' => $utility->dimension ?? 0,
                                        'count' => $utility->count ?? 1,
                                        'block' => $utility->block ?? null,
                                        'section' => $utility->section ?? null,
                                    ];
                                })->filter(function ($utility) {
                                    return !empty($utility->utility_type);
                                })->values();
                            }
                        @endphp

                        @if($printUtilities->count() > 0)
                            @foreach($printUtilities as $index => $utility)
                            <tr>
                                <td><strong>{{ $index + 1 }}</strong></td>
                                <td><strong>{{ $formatUtilityLabel($utility->utility_type) ?: 'Utility ' . ($index + 1) }}</strong></td>
                                <td><strong>{{ number_format($utility->dimension ?? 0, 1) }}</strong></td>
                                <td><strong>{{ $utility->count ?? 1 }}</strong></td>
                                <td><strong>{{ $utility->block ?? '' }}</strong></td>
                                <td><strong>{{ $utility->section ?? '' }}</strong></td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td><strong>1</strong></td>
                                <td><strong>TOILET</strong></td>
                                <td><strong>2</strong></td>
                                <td><strong>3</strong></td>
                                <td><strong>2</strong></td>
                                <td><strong>1</strong></td>
                            </tr>
                            <tr>
                                <td><strong>2</strong></td>
                                <td><strong>TOILET</strong></td>
                                <td><strong>1.5</strong></td>
                                <td><strong>1</strong></td>
                                <td><strong>2</strong></td>
                                <td><strong>2</strong></td>
                            </tr>
                            <tr>
                                <td><strong>3</strong></td>
                                <td><strong>Garden</strong></td>
                                <td><strong>40</strong></td>
                                <td><strong>1</strong></td>
                                <td><strong>SCA</strong></td>
                                <td><strong>SCA</strong></td>
                            </tr>
                            <tr>
                                <td><strong>4</strong></td>
                                <td><strong>Laundry</strong></td>
                                <td><strong>3</strong></td>
                                <td><strong>1</strong></td>
                                <td><strong>2</strong></td>
                                <td><strong>1</strong></td>
                            </tr>
                        @endif
                    @else
                        <!-- Dynamic utilities data -->
                        <template x-if="utilities.length > 0">
                            <template x-for="(utility, index) in utilities" :key="utility.id || index">
                                <tr>
                                    <td><strong x-text="index + 1"></strong></td>
                                    <td><strong x-text="formatUtilityLabel(utility.utility_type) || ('Utility ' + (index + 1))"></strong></td>
                                    <td><strong x-text="parseFloat(utility.dimension || 0).toFixed(1)"></strong></td>
                                    <td><strong x-text="utility.count || 1"></strong></td>
                                    <td><strong x-text="utility.block || ''"></strong></td>
                                    <td><strong x-text="utility.section || ''"></strong></td>
                                </tr>
                            </template>
                        </template>

                        <!-- Fallback static data when no dynamic data -->
                        <template x-if="utilities.length === 0">
                            <template>
                                <tr>
                                    <td><strong>1</strong></td>
                                    <td><strong>TOILET</strong></td>
                                    <td><strong>2</strong></td>
                                    <td><strong>3</strong></td>
                                    <td><strong>2</strong></td>
                                    <td><strong>1</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>2</strong></td>
                                    <td><strong>TOILET</strong></td>
                                    <td><strong>1.5</strong></td>
                                    <td><strong>1</strong></td>
                                    <td><strong>2</strong></td>
                                    <td><strong>2</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>3</strong></td>
                                    <td><strong>Garden</strong></td>
                                    <td><strong>40</strong></td>
                                    <td><strong>1</strong></td>
                                    <td><strong>SCA</strong></td>
                                    <td><strong>SCA</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>4</strong></td>
                                    <td><strong>Laundry</strong></td>
                                    <td><strong>3</strong></td>
                                    <td><strong>1</strong></td>
                                    <td><strong>2</strong></td>
                                    <td><strong>1</strong></td>
                                </tr>
                            </template>
                        </template>
                    @endif
                </tbody>
            </table>
        </div>
<br>
<br>
<br>
<br>

<br>
 
 
 
        <div class="mb-4 mt-4">
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

        <!-- Footer -->
        <div class="absolute bottom-3 left-0 right-0 text-center text-xs text-gray-500">
            <p>Generated on: <span id="current-date"></span> by {{ Auth::user()->name ?? 'system' }}</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateElement = document.getElementById('current-date');
            if (dateElement) {
                dateElement.textContent = new Date().toLocaleDateString();
            }

            @if(isset($printMode) && $printMode)
            let checkDataInterval = setInterval(function() {
                const alpineEl = document.querySelector('[x-data]');
                if (alpineEl && alpineEl._x_dataStack) {
                    const alpineData = alpineEl._x_dataStack[0];

                    if (alpineData.dimensions.length > 0 || alpineData.utilities.length > 0) {
                        clearInterval(checkDataInterval);
                        setTimeout(() => window.print(), 300);
                    }
                }
            }, 200);

            setTimeout(function() {
                clearInterval(checkDataInterval);
                window.print();
            }, 1500);
            @endif
        });
    </script>

    @include('actions.parts.pp_js')
</body>
</html>
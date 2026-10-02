<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Recommendation Memo to DST</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Add SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @include('actions.parts.pp_css')
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .heading {
            font-family: 'Libre Baskerville', serif;
        }
        
        .memo-container {
            width: 21cm;
            min-height: 29.7cm;
            background: white;
            margin: 0 auto;
            padding: 1cm;
            position: relative;
            box-sizing: border-box;
        }
        
        .header-line {
            height: 3px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
            margin-bottom: 15px;
        }
        
        .underline {
            text-decoration: underline;
            text-decoration-thickness: 1px;
        }
        
        /* Logo positioning */
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
        
        /* Table styling */
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 11px;
            margin: 8px 0;
            page-break-inside: avoid;
        }
        
        th, td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        
        .signature-line {
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            width: 180px;
            margin: 10px 0 5px 0;
        }
        
        .signature-section {
            page-break-inside: avoid;
            margin-top: 15px;
        }
        
        /* Responsive logo sizing */
        @media screen and (max-width: 768px) {
            .corner-logo {
                width: 60px;
            }
            .memo-container {
                padding: 0.5cm;
                font-size: 13px;
            }
        }
        
        /* Print optimizations */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            
            html, body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 210mm;
                height: 297mm;
                font-size: 11px !important;
            }
            
            .memo-container {
                width: 100% !important;
                height: 100% !important;
                padding: 12mm !important;
                margin: 0 !important;
                transform: scale(0.95) !important;
                transform-origin: top left !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* Prevent page breaks */
            table, .signature-section, .main-content {
                page-break-inside: avoid !important;
            }
            
            /* Tighter spacing for print */
            .mb-6 { margin-bottom: 8px !important; }
            .mb-4 { margin-bottom: 6px !important; }
            .mb-3 { margin-bottom: 4px !important; }
            
            table {
                font-size: 10px !important;
                margin: 6px 0 !important;
            }
            
            th, td {
                padding: 2px 4px !important;
            }
            
            .corner-logo {
                width: 70px !important;
            }
        }
    </style>
</head>
<body class="py-2" data-planning-content data-application-id="{{ $application->id ?? '' }}" {{ request()->query('url') == 'print' ? 'data-print-page="true"' : '' }}
  x-data="{
    applicationId: '{{ $application->id ?? '' }}',
    showDimensionModal: false,
    showUtilityModal: false,
    dimensions: [],
    utilities: [],
    currentDimension: { id: '', description: '', dimension: '', order: '' },
    currentUtility: { id: '', utility_type: '', dimension: '', count: '', order: '' },
    editMode: false,

    loadDimensions() {
        this.dimensions = []; // Clear existing dimensions
        console.log('Loading dimensions for application:', this.applicationId);
        
        if (!this.applicationId) return;
        
        axios.get(`{{ url('planning-tables/dimensions') }}/${this.applicationId}`)
            .then(response => {
                const uniqueDimensions = [];
                const seenKeys = new Set();

                response.data.forEach(dim => {
                    const key = `${dim.description}-${dim.dimension}`;
                    if (!seenKeys.has(key)) {
                        uniqueDimensions.push(dim);
                        seenKeys.add(key);
                    }
                });

                this.dimensions = uniqueDimensions;
                console.log('Final unique dimensions:', this.dimensions);
            })
            .catch(error => {
                console.error('Error loading dimensions:', error);
            });
    },
    
    loadUtilities() {
      if (!this.applicationId) return;
      
      const url = `{{ url('planning-tables/utilities') }}/${this.applicationId}`;
      console.log('Loading utilities from:', url);
      
      axios.get(url)
        .then(response => {
          console.log('Received utilities data:', response.data);
          this.utilities = [...response.data];
          console.log('Utilities array after update:', this.utilities);
        })
        .catch(error => {
          console.error('Error loading utilities:', error);
        });
    }
  }"
  x-init="() => {
    console.log('Alpine init starting');
    if (applicationId) {
        loadDimensions();
        loadUtilities();
    }
  }"
>
    <div class="no-print flex justify-center mb-2">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1 px-4 rounded shadow transition duration-200 flex items-center text-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print Document
        </button>
    </div>

    <!-- Memo Container -->
    <div class="memo-container">
        <!-- Header and Footer Corner Logos -->
        <img class="corner-logo logo-top-left" src="{{ asset('assets/logo/ministry-left.png') }}" alt="Ministry Left Logo" onerror="this.style.display='none'">
        <img class="corner-logo logo-top-right" src="{{ asset('assets/logo/ministry-right.png') }}" alt="Ministry Right Logo" onerror="this.style.display='none'">
        <img class="corner-logo logo-bottom-left" src="{{ asset('assets/logo/brand-left.png') }}" alt="Brand Left Logo" onerror="this.style.display='none'">
        <img class="corner-logo logo-bottom-right" src="{{ asset('assets/logo/brand-right.png') }}" alt="Brand Right Logo" onerror="this.style.display='none'">
        
        <!-- Header Line -->
        <div class="header-line rounded-t-lg"></div>
        
        <!-- Header Section -->
        <div class="text-center mb-4">
            <h1 class="heading text-lg font-bold text-gray-800 mb-1 underline">SECTIONAL TITLING ONE STOP SHOP</h1>
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
        <div class="mb-4 text-gray-700 main-content">
            <p class="mb-3">
                Kindly refer to application dated <span class="font-bold">{{ $application->planning_approval_date ?? 'N/A' }}</span> in relation to the above subject. I am directed to convey the approval for fragmentation of (FILE NO {{ $application->fileno ?? 'N/A' }}) with plot no (<strong>{{ $application->property_plot_no ?? 'N/A' }}</strong>) and ST scheme no ({{ $application->scheme_no ?? 'N/A' }}) located at <span class="font-bold">{{ $application->property_street_name ?? '' }} {{ $application->property_lga ?? 'N/A' }}</span> into multiple units/ sections as described in table A. All sections / units have met physical planning requirement for statutory right of occupancy with approved shared utilities and compounds as described in table B next page.
            </p>
            
            <p class="mb-3">
                In view of the above and section seven (7) of kano state sectional and systematic land titling registration law 2024 (1446 AH) scheme and its fragmentation (based on approved Site plan dimensions) are suitable for approval if you have no objection.
            </p>
        </div>

        <!-- Signature Lines - Fixed Layout -->
        <div class="mb-6 signature-section">
            <div class="flex justify-between">
                <div class="text-center w-1/2 pr-4">
                    <div class="signature-line"></div>
                    <p class="text-xs text-gray-600 mt-1">Counter sign: Coordinator <strong>OSS</strong></p>
                </div>
                <div class="text-center w-1/2 pl-4">
                    <div class="signature-line"></div>
                    <p class="text-xs text-gray-600 mt-1">Chairman OSS</p>
                </div>
            </div>
        </div>

        <!-- Table A -->
        <div class="mb-6">
            <h3 class="font-bold text-gray-800 mb-2 text-md text-left">1. TABLE A DIMENSIONS OF FRAGMENTED UNITS</h3>
            <table>
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>UNIT / SECTION NO</th>
                        <th>DIMENSIONS IN M²</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic dimensions data -->
                    <template x-if="dimensions.length > 0">
                        <template x-for="(dimension, index) in dimensions" :key="dimension.description + '-' + dimension.dimension">
                            <tr>
                                <td><strong x-text="index + 1"></strong></td>
                                <td><strong x-text="dimension.description || 'Unit ' + (index + 1)"></strong></td>
                                <td><strong x-text="parseFloat(dimension.dimension || 0).toFixed(2)"></strong></td>
                            </tr>
                        </template>
                    </template>
                    
                    <!-- Fallback static data when no dynamic data -->
                    <template x-if="dimensions.length === 0">
                        <template>
                            @php
                            // Try to get dimensions from PHP data if available
                            $phpDimensions = [];
                            try {
                                if (isset($application->id)) {
                                    $phpDimensions = DB::connection('sqlsrv')
                                        ->table('site_plan_dimensions')
                                        ->where('application_id', $application->id)
                                        ->orderBy('order')
                                        ->get();
                                }
                            } catch (\Exception $e) {
                                $phpDimensions = [];
                            }
                            @endphp
                            
                            @if(count($phpDimensions) > 0)
                                @foreach($phpDimensions as $index => $dimension)
                                <tr>
                                    <td><strong>{{ $index + 1 }}</strong></td>
                                    <td><strong>{{ $dimension->description ?? 'Unit ' . ($index + 1) }}</strong></td>
                                    <td><strong>{{ number_format($dimension->dimension ?? 0, 2) }}</strong></td>
                                </tr>
                                @endforeach
                            @else
                                <!-- Default empty state -->
                                <tr>
                                    <td><strong>1</strong></td>
                                    <td><strong>Unit 1</strong></td>
                                    <td><strong>-</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>2</strong></td>
                                    <td><strong>Unit 2</strong></td>
                                    <td><strong>-</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>3</strong></td>
                                    <td><strong>Unit 3</strong></td>
                                    <td><strong>-</strong></td>
                                </tr>
                            @endif
                        </template>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Table B -->
        <div>
            <h3 class="font-bold text-gray-800 mb-2 text-md">2. Table B UTILITY AND SHARED COMPOUND DESCRIPTION</h3>
            <table>
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>SHARED UTILITY TYPE</th>
                        <th>DIMENSION IN M²</th>
                        <th>COUNT WITHIN SCHEME</th>
                        <th>BLOCK</th>
                        <th>SECTION</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic utilities data -->
                    <template x-if="utilities.length > 0">
                        <template x-for="(utility, index) in utilities" :key="utility.id || index">
                            <tr>
                                <td><strong x-text="index + 1"></strong></td>
                                <td><strong x-text="(utility.utility_type || 'Utility ' + (index + 1)).toUpperCase()"></strong></td>
                                <td><strong x-text="parseFloat(utility.dimension || 0).toFixed(1)"></strong></td>
                                <td><strong x-text="utility.count || 1"></strong></td>
                                <td><strong x-text="utility.block || '1'"></strong></td>
                                <td><strong x-text="utility.section || '1'"></strong></td>
                            </tr>
                        </template>
                    </template>
                    
                    <!-- Fallback static data when no dynamic data -->
                    <template x-if="utilities.length === 0">
                        <template>
                            @php
                            // Try to get utilities from PHP data if available
                            $phpUtilities = [];
                            try {
                                if (isset($application->id)) {
                                    $phpUtilities = DB::connection('sqlsrv')
                                        ->table('shared_utilities')
                                        ->where('application_id', $application->id)
                                        ->orderBy('order')
                                        ->get();
                                }
                            } catch (\Exception $e) {
                                $phpUtilities = [];
                            }
                            @endphp
                            
                            @if(count($phpUtilities) > 0)
                                @foreach($phpUtilities as $index => $utility)
                                <tr>
                                    <td><strong>{{ $index + 1 }}</strong></td>
                                    <td><strong>{{ strtoupper($utility->utility_type ?? 'UTILITY ' . ($index + 1)) }}</strong></td>
                                    <td><strong>{{ number_format($utility->dimension ?? 0, 1) }}</strong></td>
                                    <td><strong>{{ $utility->count ?? 1 }}</strong></td>
                                    <td><strong>{{ $utility->block ?? '1' }}</strong></td>
                                    <td><strong>{{ $utility->section ?? '1' }}</strong></td>
                                </tr>
                                @endforeach
                            @else
                                <!-- Default empty state -->
                                <tr>
                                    <td><strong>1</strong></td>
                                    <td><strong>TOILET</strong></td>
                                    <td><strong>-</strong></td>
                                    <td><strong>-</strong></td>
                                    <td><strong>1</strong></td>
                                    <td><strong>1</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>2</strong></td>
                                    <td><strong>SHARED AREA</strong></td>
                                    <td><strong>-</strong></td>
                                    <td><strong>-</strong></td>
                                    <td><strong>SCA</strong></td>
                                    <td><strong>SCA</strong></td>
                                </tr>
                            @endif
                        </template>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="absolute bottom-3 left-0 right-0 text-center text-xs text-gray-500">
            <p>Generated on: <span id="current-date"></span></p>
        </div>
    </div>

    @include('actions.parts.pp_js')
    
    <script>
        // Add current date to the document
        document.addEventListener('DOMContentLoaded', function() {
            const dateElement = document.getElementById('current-date');
            if (dateElement) {
                dateElement.textContent = new Date().toLocaleDateString();
            }
        });
    </script>
</body>
</html>
@extends('layouts.app')
@section('page-title')
    {{ __('Planning Recommendation') }}
@endsection

@section('content')
@include('sectionaltitling.partials.assets.css')
@include('actions.parts.recomm_css')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">

                {{-- All Variables and PHP Logic (moved to main file to avoid scope issues) --}}
                @php
                    // Status Variables
                    $statusClass = match (strtolower($application->planning_recommendation_status ?? '')) {
                        'approve' => 'bg-green-100 text-green-800',
                        'approved' => 'bg-green-100 text-green-800',
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'decline' => 'bg-red-100 text-red-800',
                        'declined' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800',
                    };

                    $statusIcon = match (strtolower($application->planning_recommendation_status ?? '')) {
                        'approve' => 'check-circle',
                        'approved' => 'check-circle',
                        'pending' => 'clock',
                        'decline' => 'x-circle',
                        'declined' => 'x-circle',
                        default => 'help-circle',
                    };

                    // Survey Record
                    $surveyRecord = DB::connection('sqlsrv')
                        ->table('surveyCadastralRecord')
                        ->where('application_id', $application->id)
                        ->first();

                    // Shared Utilities Options
                    $sharedUtilitiesOptions = [];
                    try {
                        $sharedUtilitiesOptions = DB::connection('sqlsrv')
                            ->table('shared_utilities')
                            ->where('application_id', $application->id)
                            ->pluck('utility_type')
                            ->filter()
                            ->unique()
                            ->values()
                            ->toArray();
                    } catch (\Exception $e) {
                        $sharedUtilitiesOptions = [];
                    }

                    $sharedAreasDecoded = [];
                    $sharedAreasRaw = $application->shared_areas ?? null;
                    if (!empty($sharedAreasRaw)) {
                        if (is_string($sharedAreasRaw)) {
                            $decoded = json_decode($sharedAreasRaw, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $sharedAreasDecoded = $decoded;
                            } elseif (str_contains($sharedAreasRaw, ',')) {
                                $sharedAreasDecoded = array_map('trim', explode(',', $sharedAreasRaw));
                            }
                        } elseif (is_array($sharedAreasRaw)) {
                            $sharedAreasDecoded = $sharedAreasRaw;
                        }
                    }

                    $sharedUtilitiesOptions = array_values(array_unique(array_filter(array_merge(
                        $sharedUtilitiesOptions,
                        $sharedAreasDecoded
                    ))));

                    $selectedSharedUtilities = [];
                    if (!empty($jointInspectionReport?->shared_utilities)) {
                        if (is_array($jointInspectionReport->shared_utilities)) {
                            $selectedSharedUtilities = $jointInspectionReport->shared_utilities;
                        } else {
                            $decodedUtilities = json_decode($jointInspectionReport->shared_utilities, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedUtilities)) {
                                $selectedSharedUtilities = $decodedUtilities;
                            }
                        }
                    } else {
                        $selectedSharedUtilities = $sharedUtilitiesOptions;
                    }

                    // Joint Inspection Applicant
                    $jointInspectionApplicant = $jointInspectionReport->applicant_name ?? '';
                    if (empty($jointInspectionApplicant)) {
                        $applicantType = strtolower($application->applicant_type ?? '');
                        if ($applicantType === 'individual') {
                            $jointInspectionApplicant = trim(implode(' ', array_filter([
                                $application->applicant_title ?? null,
                                $application->first_name ?? null,
                                $application->surname ?? null,
                            ])));
                        } elseif ($applicantType === 'corporate') {
                            $jointInspectionApplicant = trim(implode(' ', array_filter([
                                $application->rc_number ?? null,
                                $application->corporate_name ?? null,
                            ])));
                        } elseif ($applicantType === 'multiple') {
                            $names = $application->multiple_owners_names ?? null;
                            if (is_string($names)) {
                                $decodedNames = json_decode($names, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedNames)) {
                                    $jointInspectionApplicant = implode(', ', array_filter($decodedNames));
                                } else {
                                    $jointInspectionApplicant = $names;
                                }
                            } elseif (is_array($names)) {
                                $jointInspectionApplicant = implode(', ', array_filter($names));
                            }
                        }
                    }

                    // Location
                    $locationCandidates = array_filter([
                        $jointInspectionReport->location ?? null,
                        (($application->property_street_name ?? null) ? trim($application->property_street_name) : null),
                        $application->property_lga ?? null,
                        $application->property_location ?? null,
                    ]);
                    $jointInspectionLocation = $locationCandidates ? array_values($locationCandidates)[0] : '';

                    // Measurement Entries
                    $measurementEntries = [];
                    if ($jointInspectionReport && is_array($jointInspectionReport->existing_site_measurement_entries)) {
                        $measurementEntries = array_values(array_map(function ($entry) {
                            $description = isset($entry['description']) ? (string) $entry['description'] : '';
                            $dimension = isset($entry['dimension']) ? (string) $entry['dimension'] : '';
                            $measurementDimension = isset($entry['measurement_dimension']) ? (string) $entry['measurement_dimension'] : '';
                            if ($measurementDimension === '' && isset($entry['measurementDimension'])) {
                                $measurementDimension = (string) $entry['measurementDimension'];
                            }
                            if ($measurementDimension === '' && isset($entry['measurement_dimension_display'])) {
                                $measurementDimension = (string) $entry['measurement_dimension_display'];
                            }
                            $countRaw = isset($entry['count']) ? (string) $entry['count'] : '';
                            $count = trim($countRaw) === '' ? '1' : $countRaw;

                            return [
                                'description' => $description,
                                'measurement_dimension' => $measurementDimension,
                                'dimension' => $dimension,
                                'count' => $count,
                            ];
                        }, array_filter($jointInspectionReport->existing_site_measurement_entries ?? [], function ($entry) {
                            return is_array($entry);
                        })));
                    }

                    if (empty($measurementEntries)) {
                        $dimensionRecords = DB::connection('sqlsrv')
                            ->table('site_plan_dimensions')
                            ->where('application_id', $application->id)
                            ->orderBy('order')
                            ->get();

                        if ($dimensionRecords->count() > 0) {
                            $measurementEntries = $dimensionRecords->map(function ($dimension) {
                                $countValue = property_exists($dimension, 'count') ? (string) $dimension->count : '1';
                                $countNormalized = trim($countValue) === '' ? '1' : $countValue;

                                $measurementDimensionValue = '';
                                if (property_exists($dimension, 'measurement_dimension')) {
                                    $measurementDimensionValue = (string) $dimension->measurement_dimension;
                                } elseif (property_exists($dimension, 'measurement_dimension_display')) {
                                    $measurementDimensionValue = (string) $dimension->measurement_dimension_display;
                                } elseif (property_exists($dimension, 'measurementDimension')) {
                                    $measurementDimensionValue = (string) $dimension->measurementDimension;
                                }

                                return [
                                    'description' => $dimension->description ?? '',
                                    'measurement_dimension' => $measurementDimensionValue,
                                    'dimension' => isset($dimension->dimension) ? (string) $dimension->dimension : '',
                                    'count' => $countNormalized,
                                ];
                            })->values()->toArray();
                        }
                    }

                    if (empty($measurementEntries)) {
                        $measurementEntries = [[
                            'description' => '',
                            'measurement_dimension' => '',
                            'dimension' => '',
                            'count' => '1',
                        ]];
                    }

                    // Default Values
                    $defaultMeasurementSummaryMessage = 'No shared utility measurements were submitted for this inspection.';
                    $defaultBoundaryDescriptionTemplate = "Boundary demarcation:\n- North: \n- East: \n- South: \n- West: ";

                    // Joint Inspection Defaults
                    $jointInspectionDefaults = [
                        'inspection_date' => $jointInspectionReport && $jointInspectionReport->inspection_date ? \Carbon\Carbon::parse($jointInspectionReport->inspection_date)->format('Y-m-d') : now()->toDateString(),
                        'lkn_number' => $jointInspectionReport->lkn_number ?? ($surveyRecord->tp_plan_no ?? $application->lkn_number ?? ''),
                        'applicant_name' => $jointInspectionApplicant,
                        'location' => $jointInspectionReport->location ?? $jointInspectionLocation,
                        'plot_number' => $jointInspectionReport->plot_number ?? $application->property_plot_no ?? '',
                        'unit_number' => $jointInspectionReport->unit_number ?? ($application->unit_number ?? ''),
                        'scheme_number' => $jointInspectionReport->scheme_number ?? $application->scheme_no ?? '',
                        'boundary_description' => $jointInspectionReport->boundary_description ?? $defaultBoundaryDescriptionTemplate,
                        'available_on_ground' => $jointInspectionReport ? (bool) $jointInspectionReport->available_on_ground : false,
                        'sections_count' => $jointInspectionReport->sections_count ?? '',
                        'unit_dimension' => $jointInspectionReport->unit_dimension ?? ($application->unit_size ?? ''),
                        'road_reservation' => $jointInspectionReport->road_reservation ?? '',
                        'prevailing_land_use' => $jointInspectionReport->prevailing_land_use ?? '',
                        'applied_land_use' => $jointInspectionReport->applied_land_use ?? ($application->land_use ?? ''),
                        'compliance_status' => $jointInspectionReport->compliance_status ?? 'obtainable',
                        'has_additional_observations' => $jointInspectionReport ? (bool) $jointInspectionReport->has_additional_observations : false,
                        'additional_observations' => $jointInspectionReport->additional_observations ?? '',
                        'inspection_officer' => $jointInspectionReport->inspection_officer ?? (optional(auth()->user())->name ?? ''),
                        'shared_utilities' => $selectedSharedUtilities,
                        'existing_site_measurement_entries' => $measurementEntries,
                        'existing_site_measurement_summary' => $jointInspectionReport->existing_site_measurement_summary ?? $defaultMeasurementSummaryMessage,
                    ];

                    // Boundary Segments Processing
                    $boundarySegmentsDefaults = [
                        'north' => '',
                        'east' => '',
                        'south' => '',
                        'west' => '',
                    ];

                    $extractBoundarySegments = function ($description) use ($boundarySegmentsDefaults) {
                        $segments = $boundarySegmentsDefaults;
                        if (!is_string($description)) {
                            return $segments;
                        }

                        $normalized = preg_replace("/\r\n?/", "\n", trim($description));
                        if ($normalized === '') {
                            return $segments;
                        }

                        $matches = [];
                        preg_match_all('/-\s*(North|East|South|West)\s*:\s*(.*)/i', $normalized, $matches, PREG_SET_ORDER);
                        if (!empty($matches)) {
                            foreach ($matches as $match) {
                                $directionKey = strtolower($match[1]);
                                if (array_key_exists($directionKey, $segments)) {
                                    $segments[$directionKey] = trim($match[2]);
                                }
                            }
                        } else {
                            $segments['north'] = $normalized;
                        }

                        return $segments;
                    };

                    $buildBoundaryDescription = function (array $segments) use ($boundarySegmentsDefaults) {
                        $segments = array_merge($boundarySegmentsDefaults, array_intersect_key($segments, $boundarySegmentsDefaults));
                        $lines = ['Boundary demarcation:'];
                        $lines[] = '- North: ' . ($segments['north'] ?? '');
                        $lines[] = '- East: ' . ($segments['east'] ?? '');
                        $lines[] = '- South: ' . ($segments['south'] ?? '');
                        $lines[] = '- West: ' . ($segments['west'] ?? '');
                        return implode("\n", $lines);
                    };

                    $boundarySegments = $extractBoundarySegments($jointInspectionDefaults['boundary_description'] ?? $defaultBoundaryDescriptionTemplate);
                    $jointInspectionDefaults['boundary_segments'] = $boundarySegments;
                    $jointInspectionDefaults['boundary_description'] = $buildBoundaryDescription($boundarySegments);

                    // Land Use Options
                    $jointInspectionLandUseOptions = [
                        'Residential',
                        'Commercial',
                        'Industrial',
                        'Agricultural',
                        'Institutional',
                        'Mixed Use',
                        'Public Use',
                        'Recreational',
                    ];
                @endphp

                @php
                    $buyersTabAvailable = request()->get('url') === 'recommendation';
                    $jsiTabEnabled = !empty($jointInspectionReport);
                    $inspectionTabEnabled = $jsiTabEnabled;
                    $planningRecommTabEnabled = $jsiTabEnabled && (bool) ($jointInspectionReport->is_approved ?? false);
                    $finalTabEnabled = in_array(strtolower($application->planning_recommendation_status ?? ''), ['approve', 'approved'], true);

                    $requestedTab = request()->get('tab');
                    $availableTabs = [
                        'documents',
                        'jsi-report',
                        'inspection-details',
                        'buyers-list',
                        'initial',
                        'final',
                    ];

                    $resolvedActiveTab = in_array($requestedTab, $availableTabs, true)
                        ? $requestedTab
                        : 'documents';

                    if (in_array($resolvedActiveTab, ['jsi-report', 'inspection-details'], true) && !$jsiTabEnabled) {
                        $resolvedActiveTab = 'documents';
                    }

                    if ($resolvedActiveTab === 'buyers-list' && !$buyersTabAvailable) {
                        $resolvedActiveTab = 'documents';
                    }

                    if ($resolvedActiveTab === 'initial' && (!$buyersTabAvailable || !$planningRecommTabEnabled)) {
                        $resolvedActiveTab = 'documents';
                    }

                    if ($resolvedActiveTab === 'final' && !$finalTabEnabled) {
                        $resolvedActiveTab = 'documents';
                    }

                    $activeTab = $resolvedActiveTab;
                @endphp

                {{-- Include Page Header --}}
                @include('actions.recommendation._header')

                {{-- Include Tab Navigation --}}
                @include('actions.recommendation._navigation', ['resolvedActiveTab' => $activeTab])


                {{-- Include View Documents Tab --}}
                @include('actions.partials.view_documents')

                {{-- Include Application Data Tab --}}
                @include('actions.recommendation._application-data-tab')
                 {{-- Include JSI Report Tab --}}
                @include('actions.recommendation._jsi-report-tab')
                {{-- Include Inspection Details Tab --}}
                @include('actions.recommendation._inspection-details-tab')
                {{-- Include Buyers List Tab --}}
                @include('actions.recommendation._buyers-list-tab')
                {{-- Include Planning Recommendation Approval Tab --}}
                @include('actions.recommendation._approval-tab')

                {{-- Include Final Report Tab --}}
                @include('actions.recommendation._final-report-tab')

            
            </div>
 
    <!-- Footer -->
    @include('admin.footer')
</div>
 


{{-- Include JSI Modal --}}
@include('actions.recommendation._jsi-modal')

{{-- Include Decline Reason Modal --}}
@include('actions.recommendation._decline-reason-modal')

{{-- Include JavaScript and Scripts --}}
@include('actions.recommendation._scripts')

@endsection
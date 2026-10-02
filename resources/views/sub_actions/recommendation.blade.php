@extends('layouts.app')
@section('page-title')
    {{ __('Planning Recommendation') }}
@endsection

<style>
/* Custom tab styles */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-button {
    position: relative;
    display: inline-flex;
    flex: 1 1 calc(50% - 0.5rem);
    justify-content: center;
    align-items: stretch;
    border: none;
    background: transparent;
    padding: 0;
    cursor: pointer;
    min-width: 0;
    transition: transform 0.2s ease;
}

.tab-button:focus-visible .tab-button__inner {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.35);
}

.tab-button:active .tab-button__inner {
    transform: translateY(1px);
}

.tab-button__inner {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    min-height: 2.75rem;
    padding: 0.65rem 1rem;
    border-radius: 0.9rem;
    border: 1px solid transparent;
    background: rgba(248, 250, 252, 0.8);
    color: #475569;
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.25rem;
    transition: all 0.25s ease;
    box-shadow: inset 0 -1px 0 rgba(148, 163, 184, 0.08);
}

.tab-button__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 999px;
    background: rgba(191, 219, 254, 0.45);
    color: #1e3a8a;
    transition: all 0.25s ease;
}

.tab-button__label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.tab-button__badge {
    margin-left: 0.5rem;
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 600;
    background: rgba(187, 247, 208, 0.9);
    color: #166534;
    box-shadow: 0 2px 4px rgba(22, 101, 52, 0.15);
}

.tab-button__indicator {
    position: absolute;
    left: 16%;
    right: 16%;
    bottom: 6px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, #60a5fa, #2563eb);
    transform: scaleX(0);
    transform-origin: center;
    opacity: 0;
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.tab-button:hover .tab-button__inner {
    background: rgba(226, 232, 240, 0.85);
    color: #1e3a8a;
    border-color: rgba(148, 163, 184, 0.45);
}

.tab-button:hover .tab-button__icon {
    background: rgba(191, 219, 254, 0.7);
    color: #1d4ed8;
}

.tab-button:hover .tab-button__indicator {
    transform: scaleX(0.55);
    opacity: 0.6;
    background: linear-gradient(90deg, rgba(191, 219, 254, 0.9), rgba(147, 197, 253, 0.9));
}

.tab-button.active {
    flex: 1 1 100%;
}

.tab-button.active .tab-button__inner {
    background: #ffffff;
    color: #1d4ed8;
    border-color: rgba(59, 130, 246, 0.35);
    box-shadow:
        0 10px 30px rgba(59, 130, 246, 0.12),
        inset 0 0 0 1px rgba(59, 130, 246, 0.2);
}

.tab-button.active .tab-button__icon {
    background: rgba(59, 130, 246, 0.15);
    color: #1d4ed8;
    box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.15);
}

.tab-button.active .tab-button__indicator {
    transform: scaleX(1);
    opacity: 1;
}

@media (min-width: 768px) {
    .tab-button {
        flex: 0 0 auto;
    }

    .tab-button.active {
        flex: 0 0 auto;
    }
}

@media (max-width: 640px) {
    .tab-button {
        flex: 1 1 calc(50% - 0.5rem);
    }

    .tab-button__inner {
        justify-content: flex-start;
    }
}

.tab-button--disabled,
.tab-button[disabled] {
    cursor: not-allowed;
}

.tab-button--disabled .tab-button__inner,
.tab-button[disabled] .tab-button__inner {
    background: rgba(241, 245, 249, 0.7);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.25);
    box-shadow: inset 0 -1px 0 rgba(148, 163, 184, 0.05);
}

.tab-button--disabled .tab-button__icon,
.tab-button[disabled] .tab-button__icon {
    background: rgba(226, 232, 240, 0.6);
    color: #94a3b8;
}

.tab-button--disabled .tab-button__indicator,
.tab-button[disabled] .tab-button__indicator {
    display: none;
}

.tab-button--disabled:hover .tab-button__inner,
.tab-button[disabled]:hover .tab-button__inner {
    background: rgba(241, 245, 249, 0.7);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.25);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

@media print {
    body * {
        visibility: hidden;
    }

    #final-tab,
    #final-tab * {
        visibility: visible;
    }

    #final-tab {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }

    .no-print,
    button,
    .tab-button,
    footer,
    nav {
        display: none !important;
    }
}
</style>
@include('sectionaltitling.partials.assets.css')
@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">

                @php
                    $surveyRecord = DB::connection('sqlsrv')
                        ->table('surveyCadastralRecord')
                        ->where('sub_application_id', $application->id)
                        ->orWhere(function ($query) use ($application) {
                            $query->whereNull('sub_application_id')
                                ->where('application_id', $application->main_application_id ?? $application->id);
                        })
                        ->first();

                    $isSua_local = ($application->is_sua_unit ?? 0) == 1 || (strtoupper($application->unit_type ?? '') === 'SUA');
                    $effectivePlanningStatus = (strtolower($application->planning_recommendation_status ?? '') === 'approved') 
                        ? 'Approved' 
                        : ((!$isSua_local && strtolower($application->mother_planning_status ?? '') === 'approved') ? 'Approved' : ($application->planning_recommendation_status ?? 'Pending'));

                    $statusClass = match (strtolower($effectivePlanningStatus)) {
                        'approve' => 'bg-green-100 text-green-800',
                        'approved' => 'bg-green-100 text-green-800',
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'decline' => 'bg-red-100 text-red-800',
                        'declined' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800',
                    };

                    $statusIcon = match (strtolower($effectivePlanningStatus)) {
                        'approve' => 'check-circle',
                        'approved' => 'check-circle',
                        'pending' => 'clock',
                        'decline' => 'x-circle',
                        'declined' => 'x-circle',
                        default => 'help-circle',
                    };

                    $sharedUtilitiesOptions = [];
                    try {
                        $sharedUtilitiesOptions = DB::connection('sqlsrv')
                            ->table('shared_utilities')
                            ->where('sub_application_id', $application->id)
                            ->orWhere(function ($query) use ($application) {
                                $query->whereNull('sub_application_id')
                                    ->where('application_id', $application->main_application_id ?? 0);
                            })
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

                    $jointInspectionReport = \App\Models\JointSiteInspectionReport::where('sub_application_id', $application->id)
                        ->first();

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

                    $jointInspectionApplicant = $jointInspectionReport->applicant_name ?? '';
                    if (empty($jointInspectionApplicant)) {
                        $applicantType = strtolower($application->applicant_type ?? $application->primary_applicant_type ?? '');
                        if ($applicantType === 'individual') {
                            $jointInspectionApplicant = trim(implode(' ', array_filter([
                                $application->applicant_title ?? $application->primary_applicant_title ?? null,
                                $application->first_name ?? $application->primary_first_name ?? null,
                                $application->surname ?? $application->primary_surname ?? null,
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

                    $locationCandidates = array_filter([
                        $jointInspectionReport->location ?? null,
                        $application->property_street_name ?? null,
                        $application->property_lga ?? null,
                        $application->property_location ?? null,
                    ]);
                    $jointInspectionLocation = $locationCandidates ? array_values($locationCandidates)[0] : '';

                    $parentLandUse = $application->land_use ?? $application->primary_land_use ?? '';

                    $defaultMeasurementSummaryMessage = 'No shared utility measurements were submitted for this inspection.';

                    $measurementEntries = [];
                    if ($jointInspectionReport && is_array($jointInspectionReport->existing_site_measurement_entries)) {
                        $measurementEntries = array_values(array_filter(array_map(function ($entry) {
                            if (!is_array($entry)) {
                                return null;
                            }

                            $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
                            $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
                            $measurementDimension = isset($entry['measurement_dimension']) ? trim((string) $entry['measurement_dimension']) : '';
                            if ($measurementDimension === '' && isset($entry['measurementDimension'])) {
                                $measurementDimension = trim((string) $entry['measurementDimension']);
                            }
                            if ($measurementDimension === '' && isset($entry['measurement_dimension_display'])) {
                                $measurementDimension = trim((string) $entry['measurement_dimension_display']);
                            }
                            if ($measurementDimension === '' && isset($entry['measurementDimension'])) {
                                $measurementDimension = trim((string) $entry['measurementDimension']);
                            }
                            $countRaw = isset($entry['count']) ? trim((string) $entry['count']) : '';
                            $count = $countRaw === '' ? '1' : $countRaw;

                            if ($description === '' && $dimension === '' && $measurementDimension === '' && $count === '1') {
                                return null;
                            }

                            return [
                                'description' => $description,
                                'measurement_dimension' => $measurementDimension,
                                'dimension' => $dimension,
                                'count' => $count,
                            ];
                        }, $jointInspectionReport->existing_site_measurement_entries)));
                    }

                    if (empty($measurementEntries)) {
                        try {
                            $dimensionRecords = DB::connection('sqlsrv')
                                ->table('sub_site_plan_dimensions')
                                ->where('sub_application_id', $application->id)
                                ->orderBy('order')
                                ->get();
                        } catch (\Throwable $e) {
                            $dimensionRecords = collect([]);
                        }

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
                        try {
                            $dimensionRecords = DB::connection('sqlsrv')
                                ->table('site_plan_dimensions')
                                ->where('application_id', $application->main_application_id ?? $application->id)
                                ->orderBy('order')
                                ->get();
                        } catch (\Throwable $e) {
                            $dimensionRecords = collect([]);
                        }

                        if ($dimensionRecords->count() > 0) {
                            $measurementEntries = $dimensionRecords->map(function ($dimension) {
                                $countValue = property_exists($dimension, 'count') ? (string) $dimension->count : '1';
                                $countNormalized = trim($countValue) === '' ? '1' : $countValue;

                                return [
                                    'description' => $dimension->description ?? '',
                                    'measurement_dimension' => property_exists($dimension, 'measurement_dimension')
                                        ? (string) $dimension->measurement_dimension
                                        : (property_exists($dimension, 'measurementDimension') ? (string) $dimension->measurementDimension : ''),
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

                    $defaultBoundaryDescriptionTemplate = "Boundary demarcation:\n- North: \n- East: \n- South: \n- West: ";

                    $jointInspectionDefaults = [
                        'inspection_date' => $jointInspectionReport && $jointInspectionReport->inspection_date ? \Carbon\Carbon::parse($jointInspectionReport->inspection_date)->format('Y-m-d') : now()->toDateString(),
                        'lkn_number' => $jointInspectionReport->lkn_number ?? ($surveyRecord->tp_plan_no ?? $application->tp_plan_number ?? $application->lkn_number ?? ''),
                        'applicant_name' => $jointInspectionApplicant,
                        'location' => $jointInspectionReport->location ?? $jointInspectionLocation,
                        'plot_number' => $jointInspectionReport->plot_number ?? $application->property_plot_no ?? '',
                        'scheme_number' => $jointInspectionReport->scheme_number ?? $application->scheme_no ?? '',
                        'boundary_description' => $jointInspectionReport->boundary_description ?? $defaultBoundaryDescriptionTemplate,
                        'available_on_ground' => $jointInspectionReport ? (bool) $jointInspectionReport->available_on_ground : false,
                        'sections_count' => $jointInspectionReport->sections_count ?? '',
                        'unit_number' => $jointInspectionReport->unit_number ?? ($application->unit_number ?? ''),
                        'unit_dimension' => $jointInspectionReport->unit_dimension ?? ($application->unit_size ?? ''),
                        'road_reservation' => $jointInspectionReport->road_reservation ?? '',
                        'prevailing_land_use' => $jointInspectionReport->prevailing_land_use ?? $parentLandUse,
                        'applied_land_use' => $jointInspectionReport->applied_land_use ?? $parentLandUse,
                        'compliance_status' => $jointInspectionReport->compliance_status ?? 'obtainable',
                        'has_additional_observations' => $jointInspectionReport ? (bool) $jointInspectionReport->has_additional_observations : false,
                        'additional_observations' => $jointInspectionReport->additional_observations ?? '',
                        'inspection_officer' => $jointInspectionReport->inspection_officer ?? (optional(auth()->user())->name ?? ''),
                        'shared_utilities' => $selectedSharedUtilities,
                        'existing_site_measurement_entries' => $measurementEntries,
                        'existing_site_measurement_summary' => $jointInspectionReport->existing_site_measurement_summary ?? $defaultMeasurementSummaryMessage,
                    ];
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

                    $jointInspectionLandUseOptions = [
                        'Residential',
                        'Commercial',
                        'Industrial',
                        'Agricultural',
                        'Institutional',
                        'Mixed',
                        'Mixed Use',
                        'Public Use',
                        'Recreational',
                    ];
                @endphp


                <div class="modal-content8 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-medium">Planning Recommendation <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                                <i data-lucide="{{ $statusIcon }}" class="w-3 h-3 mr-1"></i>
                                {{ $effectivePlanningStatus }}
                            </span></h2>
                        <button onclick="window.history.back()" class="text-gray-500 hover:text-gray-700">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div class="py-2">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-medium">{{ $application->land_use }} Property</h3>
                                <p class="text-xs text-gray-500">
                                    Application ID: {{ $application->applicationID }} | File No: {{ $application->fileno }}
                                </p>

                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                                    <i data-lucide="{{ $statusIcon }}" class="w-3 h-3 mr-1"></i>
                                    {{ $effectivePlanningStatus }}
                                </span>
                            </div>
                            <div class="text-right">
                                <h3 class="text-sm font-medium">
                                    @if ($application->applicant_type == 'individual')
                                        {{ $application->applicant_title }} {{ $application->first_name }}
                                        {{ $application->surname }}
                                    @elseif($application->applicant_type == 'corporate')
                                        {{ $application->rc_number }} {{ $application->corporate_name }}
                                    @elseif($application->applicant_type == 'multiple')
                                        @php
                                            $names = @json_decode($application->multiple_owners_names, true);
                                            if (is_array($names) && count($names) > 0) {
                                                echo implode(', ', $names);
                                            } else {
                                                echo $application->multiple_owners_names;
                                            }
                                        @endphp
                                    @endif


                                </h3>
                                <p class="text-xs text-gray-500">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        {{ $application->land_use }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Tabs Navigation -->

                        @php
                            // Check if JSI is captured and approved
                            $jsiCaptured = !empty($jointInspectionReport);
                            $jsiApproved = $jointInspectionReport && 
                                          (bool) ($jointInspectionReport->is_approved ?? false);
                            
                            // JSI tab is disabled if not captured
                            $jsiTabEnabled = $jsiCaptured;
                            $jsiTabDisabledReason = $jsiTabEnabled 
                                ? null 
                                : 'Joint Site Inspection is not captured.';
                            
                            // Planning Recommendation tab is enabled if JSI has not been approved
                            $isSua_local = ($application->is_sua_unit ?? 0) == 1 || (strtoupper($application->unit_type ?? '') === 'SUA');
                            $planningRecommTabEnabled = $jsiApproved || (!$isSua_local && strtolower($application->mother_planning_status ?? '') === 'approved');
                            $planningRecommTabDisabledReason = $planningRecommTabEnabled 
                                ? null 
                                : 'Planning Recommendation is disabled until JSI has been approved.';
                        @endphp

                        <div class="mb-4">
                            <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-200 rounded-2xl shadow-sm">
                                <nav class="flex flex-wrap md:flex-nowrap items-stretch gap-2 md:gap-3 p-3 overflow-x-auto" aria-label="Sub Recommendation Tabs">
                                    <button type="button" class="tab-button active group" data-tab="documents" role="tab" aria-selected="true">
                                        <span class="tab-button__inner">
                                            <span class="tab-button__icon">
                                                <i data-lucide="folder-open" class="w-4 h-4"></i>
                                            </span>
                                            <span class="tab-button__label">
                                                <span class="">View Documents</span>
                                            </span>
                                        </span>
                                        <span class="tab-button__indicator" aria-hidden="true"></span>
                                    </button>

                                    <button
                                        type="button"
                                        class="tab-button group {{ $jsiTabEnabled ? '' : 'tab-button--disabled' }}"
                                        data-tab="jsi-report"
                                        role="tab"
                                        aria-selected="false"
                                        @unless($jsiTabEnabled)
                                            disabled
                                            aria-disabled="true"
                                            @if($jsiTabDisabledReason)
                                                title="{{ $jsiTabDisabledReason }}"
                                                data-disabled-message="{{ $jsiTabDisabledReason }}"
                                            @endif
                                        @endunless
                                    >
                                        <span class="tab-button__inner">
                                            <span class="tab-button__icon">
                                                <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                                            </span>
                                            <span class="tab-button__label">
                                                Joint Site Inspection
                                            </span>
                                        </span> 
                                        <span class="tab-button__indicator" aria-hidden="true"></span>
                                    </button>

                                    @if (request()->query('url') == 'recommendation')
                                        <button 
                                            type="button" 
                                            class="tab-button group {{ $planningRecommTabEnabled ? '' : 'tab-button--disabled' }}" 
                                            data-tab="initial" 
                                            role="tab" 
                                            aria-selected="false"
                                            @unless($planningRecommTabEnabled)
                                                disabled
                                                aria-disabled="true"
                                                @if($planningRecommTabDisabledReason)
                                                    title="{{ $planningRecommTabDisabledReason }}"
                                                    data-disabled-message="{{ $planningRecommTabDisabledReason }}"
                                                @endif
                                            @endunless
                                        >
                                            <span class="tab-button__inner">
                                                <span class="tab-button__icon">
                                                    <i data-lucide="banknote" class="w-4 h-4"></i>
                                                </span>
                                                <span class="tab-button__label">
                                                    Planning Recommendation
                                                </span>
                                            </span>
                                            <span class="tab-button__indicator" aria-hidden="true"></span>
                                        </button>
                                    @endif

                                    @if(in_array(strtolower($application->planning_recommendation_status ?? ''), ['approved','approve']))
                                        <button type="button" class="tab-button group" data-tab="final" role="tab" aria-selected="false">
                                            <span class="tab-button__inner">
                                                <span class="tab-button__icon">
                                                    <i data-lucide="file-check" class="w-4 h-4"></i>
                                                </span>
                                                <span class="tab-button__label">
                                                    Recommendation Report
                                                    <span class="tab-button__badge">Approved</span>
                                                </span>
                                            </span>
                                            <span class="tab-button__indicator" aria-hidden="true"></span>
                                        </button>
                                    @endif
                                </nav>
                            </div>
                        </div>
                        @include('sub_actions.partials.view_documents')

                        <!-- Complete survey data Tab -->
                        <div id="planning-form-tab" class="tab-content">
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="p-4 border-b bg-blue-50">
                                    <h3 class="text-lg font-medium text-blue-800">📋 Complete survey data</h3>
                                    <p class="text-sm text-blue-600 mt-1">Fill in all required information before generating the Physical Planning Report. Fields marked with <span class="text-red-500">*</span> are required: LKN Number, TP Plan Number, and Approved Plan Number.</p>
                                </div>
                                
                                <form id="applicationDataForm" class="p-6 space-y-6">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="application_id" value="{{ $application->id }}">
                                    
                                    <!-- Application Information Section -->
                                    <div class="bg-gray-50 rounded-lg p-4 border">
                                        <h4 class="font-semibold text-gray-800 mb-4 flex items-center">
                                            <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                                            Application Information
                                        </h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <label for="lkn_number" class="text-xs font-medium block">
                                                    LPKN Number <span class="text-red-500">*</span>
                                                </label>
                                                <div class="flex space-x-2">
                                                    <input type="text" id="lkn_number" name="lkn_number" 
                                                           value="{{ old('lkn_number', $application->lkn_number ?? 'Piece of Land') }}"
                                                           class="flex-1 p-2 border border-gray-300 rounded-md text-sm"
                                                           placeholder="Enter LPKN Number">
                                                    <button type="button" onclick="resetField('lkn_number')" 
                                                            class="px-3 py-2 bg-gray-500 text-white text-xs rounded-md hover:bg-gray-600">
                                                        Reset
                                                    </button>
                                                </div>
                                                <span id="lknStatus" class="text-xs text-gray-500">❌</span>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="tp_plan_number" class="text-xs font-medium block">
                                                    TP Plan Number <span class="text-red-500">*</span>
                                                </label>
                                                <div class="flex space-x-2">
                                                    <input type="text" id="tp_plan_number" name="tp_plan_number" 
                                                           value="{{ old('tp_plan_number', $application->tp_plan_number ?? 'Piece of Land') }}"
                                                           class="flex-1 p-2 border border-gray-300 rounded-md text-sm"
                                                           placeholder="Enter TP Plan Number">
                                                    <button type="button" onclick="resetField('tp_plan_number')" 
                                                            class="px-3 py-2 bg-gray-500 text-white text-xs rounded-md hover:bg-gray-600">
                                                        Reset
                                                    </button>
                                                </div>
                                                <span id="tpStatus" class="text-xs text-gray-500">❌</span>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="approved_plan_number" class="text-xs font-medium block">
                                                    Approved Plan Number <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" id="approved_plan_number" name="approved_plan_number" 
                                                       value="{{ old('approved_plan_number', $application->approved_plan_number ?? '') }}"
                                                       class="w-full p-2 border border-gray-300 rounded-md text-sm"
                                                       placeholder="Enter Approved Plan Number">
                                                <span id="approvedStatus" class="text-xs text-gray-500">❌</span>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="scheme_plan_number" class="text-xs font-medium block">
                                                    Scheme Plan No <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" id="scheme_plan_number" name="scheme_plan_number" 
                                                       value="{{ old('scheme_plan_number', $application->scheme_plan_number ?? '') }}"
                                                       class="w-full p-2 border border-gray-300 rounded-md text-sm"
                                                       placeholder="Enter Scheme Plan Number">
                                                <span id="schemeStatus" class="text-xs text-gray-500">❌</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Validation Status -->
                                    <div id="completionMessage" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                        <p class="text-sm text-red-700">
                                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                                            Complete all required fields above to unlock the Planning Recommendation Report tab.
                                        </p>
                                    </div>

                                    <div class="flex gap-3">
                                        <button type="button" onclick="window.history.back()" 
                                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 flex items-center">
                                            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
                                            Back
                                        </button>
                                        <button type="submit" id="saveApplicationDataBtn" 
                                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                                            <i data-lucide="save" class="w-4 h-4 mr-1"></i>
                                            Save Application Data
                                        </button>
                                    </div>
                                    <div class="text-sm text-gray-600 mt-2">
                                        <span class="text-red-500">*</span> Required fields: LKPN Number, TP Plan Number, Approved Plan Number
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="initial-tab" class="tab-content">
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="p-4 border-b">
                                    <h3 class="text-sm font-medium">Planning Recommendation Approval</h3>
                                </div>
                                <form id="planningRecommendationForm" method="post" action="javascript:void(0);"
                                    onsubmit="handlePlanningRecommendation(event)">
                                    <!-- CSRF token for Laravel -->
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <div class="p-4 space-y-4">
                                        <input type="hidden" id="application_id" value="{{ $application->id }}">
                                        <input type="hidden" name="fileno" value="{{ $application->fileno }}">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <label class="text-xs font-medium block">
                                                    Decision
                                                </label>
                                                <div class="flex items-center space-x-4">
                                                <label class="inline-flex items-center">
                                                    <input 
                                                        type="radio" 
                                                        name="decision" 
                                                        value="Approved"
                                                        class="form-radio"
                                                        onchange="toggleObservationsAndReasonContainers(this)"
                                                        {{ strtolower($application->planning_recommendation_status) === 'approved' ? 'checked disabled' : '' }}
                                                    >
                                                    <span class="ml-2 text-sm {{ strtolower($application->planning_recommendation_status) === 'approved' ? 'text-gray-400' : '' }}">Approve</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input 
                                                        type="radio" 
                                                        name="decision" 
                                                        value="Declined"
                                                        class="form-radio"
                                                        onchange="toggleObservationsAndReasonContainers(this)"
                                                        {{ strtolower($application->planning_recommendation_status) === 'declined' ? 'checked disabled' : (strtolower($application->planning_recommendation_status) === 'approved' ? 'disabled' : '') }}
                                                    >
                                                    <span class="ml-2 text-sm {{ strtolower($application->planning_recommendation_status) === 'approved' ? 'text-gray-400' : '' }}">Decline</span>
                                                </label>

                                                    <script>
                                                        function toggleObservationsAndReasonContainers(radio) {
                                                            const reasonContainer = document.getElementById('reasonContainer');
                                                            const observationsContainer = document.getElementById('observationsContainer');
                                                            
                                                            // Only show reason container when declining
                                                            reasonContainer.style.display = (radio.value === 'Declined') ? 'block' : 'none';
                                                            
                                                            // Only show observations container when approving
                                                            if (observationsContainer) {
                                                                observationsContainer.style.display = (radio.value === 'Approved') ? 'block' : 'none';
                                                            }
                                                        }
                                                    </script>
                                                </div>
                                            </div>
                                           <div class="space-y-2">
                                                <label for="approval-date" class="text-xs font-medium block">
                                                    Approval/Decline Date
                                                </label>
                                                <div class="flex items-center space-x-2">
                                                    <input id="approval-date" type="datetime-local" name="planning_approval_date"
                                                        value="{{ old('planning_approval_date') ?? now()->format('Y-m-d\TH:i') }}"
                                                        class="w-full p-2 border border-gray-300 rounded-md text-sm"
                                                        max="{{ now()->format('Y-m-d\TH:i') }}"
                                                    >
                                                    <button type="button" onclick="document.getElementById('approval-date').value = '{{ now()->format('Y-m-d\TH:i') }}';"
                                                        class="px-2 py-1 text-xs bg-gray-200 rounded hover:bg-gray-300">
                                                        Use Current Date/Time
                                                    </button>
                                                </div>
                                                <span class="text-xs text-gray-500">You cannot select a future date.</span>
                                            </div>
                                        </div>
                                     <div id="observationsContainer" class="grid grid-cols-1 gap-4" style="display: none;">
                                            <div class="space-y-2">
                                                <label for="additionalObservations" class="text-xs font-medium block">
                                                    Additional Observations (If applicable)
                                                </label>
                                                <div class="border border-gray-300 rounded-md p-2">
                                                    <textarea id="additionalObservations" name="additionalObservations" rows="4" 
                                                        class="w-full p-2 border-none focus:outline-none focus:ring-0"
                                                        placeholder="Enter any additional observations or special considerations here...">{{ $additionalObservations ?? '' }}</textarea>
                                                    <div class="flex justify-end mt-2">
                                                        <button type="button" id="saveObservations" 
                                                            class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                                            Save Observations
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                       <div id="reasonContainer" class="space-y-2" style="display: none;">
                                            <label for="comments" class="text-xs font-medium block">
                                                Reason <span class="text-red-500">*</span>
                                            </label>
                                            <button type="button" id="openDeclineReasonModal" 
                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-white text-left text-gray-500 hover:bg-gray-50"
                                                onclick="toggleModalEnhanced(true)">
                                                Click to specify decline reasons...
                                            </button>
                                            <input type="hidden" id="comments" name="comments">
                                            <p class="text-xs text-red-500 mt-1">Please provide detailed reasons for declining this application</p>
                                        </div>


                                        <hr class="my-4">

                                        <div class="flex justify-between items-center">
                                            <div class="flex gap-2">
                                                <a href="{{ route('sectionaltitling.primary') }}"
                                                    class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
                                                    <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                                    Back
                                                </a>
                                                <button id="planningRecommendationSubmitBtn" type="submit"
                                                    class="flex items-center px-3 py-1 text-xs bg-green-700 text-white rounded-md hover:bg-gray-800">
                                                    <i data-lucide="send-horizontal" class="w-3.5 h-3.5 mr-1.5"></i>
                                                    Submit
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
 
                        </div>
                    </div>






                    <!-- Final Bill Tab -->
                    <div id="final-tab" class="tab-content">
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="p-4 border-b">
                                <h3 class="text-sm font-medium">Planning Recommendation Report</h3>
                                <p class="text-xs text-gray-500">Review the compiled recommendation summary for this sub-application.</p>
                                @php
                                    $showPrintButton = request()->query('url') === 'recommendation';
                                @endphp
                                @if($showPrintButton)
                                    @php
                                        $isSubApplication = isset($application->main_application_id) && !empty($application->main_application_id);
                                        $printRouteName = $isSubApplication ? 'sub_pr_memos.print' : 'planning-recommendation.print';
                                    @endphp
                                    <div class="mt-3">
                                        <a href="{{ route($printRouteName, $application->id) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center px-3 py-1 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                            <i data-lucide="printer" class="w-3.5 h-3.5 mr-1.5"></i>
                                            Print Document
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <input type="hidden" id="application_id" value="{{ $application->id }}">
                            <input type="hidden" name="fileno" value="{{ $application->fileno }}">
                            <div class="p-4 space-y-4">
                                @include('sub_actions.planning_recomm', [
                                    'dimensionsData' => $dimensionsData ?? [],
                                    'utilitiesData' => $utilitiesData ?? [],
                                ])

                                <hr class="my-4">

                                <div class="flex justify-between items-center">
                                    <div class="flex gap-2">
                                        <button onclick="window.history.back()"
                                            class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
                                            <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                            Back
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Joint Site Inspection Report Tab -->
                    <div id="jsi-report-tab" class="tab-content">
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="p-4 border-b bg-green-50">
                                <h3 class="text-lg font-medium text-green-800 flex items-center">
                                    <i data-lucide="clipboard-check" class="w-5 h-5 mr-2"></i>
                                    Joint Site Inspection Report
                                </h3>
                                <p class="text-sm text-green-600 mt-1">View and print the comprehensive joint site inspection report for this sub-application.</p>
                            </div>
                            
                            <div class="p-6">
                                @if($jointInspectionReport)
                                    <div class="bg-gray-50 rounded-lg p-4 border mb-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="font-semibold text-gray-800 flex items-center">
                                                    <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                                                    Inspection Report Available
                                                </h4>
                                                <p class="text-sm text-gray-600 mt-1">
                                                    <strong>Inspection Date:</strong> {{ $jointInspectionReport->inspection_date ? \Carbon\Carbon::parse($jointInspectionReport->inspection_date)->format('j F Y') : 'Not specified' }}<br>
                                                    <strong>Status:</strong> {{ ucfirst($jointInspectionReport->status ?? 'completed') }}<br>
                                                    <strong>Inspector:</strong> {{ $jointInspectionReport->inspection_officer ?? 'Not specified' }}<br>
                                                    <strong>Approval Status:</strong> 
                                                    @if($jointInspectionReport->is_approved ?? false)
                                                        <span class="jsi-status inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                            <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                                            Approved
                                                        </span>
                                                        @if($jointInspectionReport->approved_by)
                                                            <span class="jsi-approved-by text-xs text-gray-500">by {{ $jointInspectionReport->approved_by }}</span>
                                                        @endif
                                                    @else
                                                        <span class="jsi-status inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                                            Pending Approval
                                                        </span>
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="flex gap-2">
                                                @if(request()->query('url') == 'recommendation')
                                                    @if($jointInspectionReport->is_approved ?? false)
                                                        <!-- Approved State - All buttons enabled -->
                                                        <a href="{{ route('sub-actions.planning-recommendation.joint-inspection.show', $application->id) }}" 
                                                           target="_blank"
                                                           class="jsi-view-btn px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                                                            <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                                            View Report
                                                        </a>
                                                        <a href="{{ route('sub-actions.planning-recommendation.joint-inspection.show', $application->id) }}?print=true" 
                                                           target="_blank"
                                                           class="jsi-print-btn px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
                                                            <i data-lucide="printer" class="w-4 h-4 mr-1"></i>
                                                            Print
                                                        </a>
                                                    @else
                                                        <!-- Pending Approval State - Buttons disabled, show approve button -->
                                                        <div class="jsi-approval-container flex gap-2">
                                                           <button type="button" 
                                                                   data-jsi-approve
                                                                   data-sub-application-id="{{ $application->id }}"
                                   data-confirm-message="Are you sure you want to approve this Joint Site Inspection report for sub-application {{ $application->application_no ?? $application->id }}?"
                                                                   class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 flex items-center">
                                                                <i data-lucide="check-circle" class="w-4 h-4 mr-1"></i>
                                                                Approve JSI
                                                            </button>
                                                            <button disabled
                                                                   class="jsi-print-btn disabled cursor-not-allowed px-4 py-2 bg-gray-400 text-gray-200 rounded-lg flex items-center">
                                                                <i data-lucide="printer" class="w-4 h-4 mr-1"></i>
                                                                Print
                                                            </button>
                                                           
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Embedded Report Preview -->
                                    <div class="bg-white rounded-lg border">
                                        <div class="p-4 border-b">
                                            <h5 class="font-medium text-gray-800 mb-2">Report Preview</h5>
                                            <p class="text-sm text-gray-600">This is a preview of the joint site inspection report. Use the buttons above to view the full report or print.</p>
                                        </div>
                                        <div class="max-h-96 overflow-y-auto">
                                            @php
                                                // Prepare dimensions data for JSI report (sub-application)
                                                $jsiDimensions = collect();
                                                $jsiUnitMeasurements = collect();
                                                
                                                try {
                                                    // Get shared utilities for sub-application (as objects, not strings)
                                                    $subSharedUtilities = DB::connection('sqlsrv')
                                                        ->table('shared_utilities')
                                                        ->where('sub_application_id', $application->id)
                                                        ->orderBy('order')
                                                        ->get();

                                                    // Fall back to parent application's shared utilities when none found directly
                                                    if ($subSharedUtilities->isEmpty() && ($application->main_application_id ?? null)) {
                                                        $subSharedUtilities = DB::connection('sqlsrv')
                                                            ->table('shared_utilities')
                                                            ->where('application_id', $application->main_application_id)
                                                            ->orderBy('order')
                                                            ->get();
                                                    }

                                                    // Fall back to utilities embedded within the inspection report
                                                    if ($subSharedUtilities->isEmpty() && $jointInspectionReport && !empty($jointInspectionReport->shared_utilities)) {
                                                        $utilitiesFromReport = $jointInspectionReport->shared_utilities;
                                                        if (is_string($utilitiesFromReport)) {
                                                            $decoded = json_decode($utilitiesFromReport, true);
                                                            $utilitiesFromReport = json_last_error() === JSON_ERROR_NONE ? $decoded : [$utilitiesFromReport];
                                                        }

                                                        if (is_array($utilitiesFromReport)) {
                                                            $subSharedUtilities = collect($utilitiesFromReport)
                                                                ->filter(function ($value) {
                                                                    if (is_string($value)) {
                                                                        return trim($value) !== '';
                                                                    }
                                                                    return !empty($value);
                                                                })
                                                                ->values()
                                                                ->map(function ($value, $index) {
                                                                    if (is_object($value)) {
                                                                        $value = (array) $value;
                                                                    }

                                                                    $label = is_array($value)
                                                                        ? ($value['utility_type'] ?? $value['label'] ?? '')
                                                                        : (is_string($value) ? trim($value) : '');

                                                                    if ($label === '') {
                                                                        $label = 'Shared Utility';
                                                                    }

                                                                    $count = 1;
                                                                    $dimension = null;

                                                                    if (is_array($value)) {
                                                                        $count = $value['count'] ?? $value['quantity'] ?? 1;
                                                                        $dimension = $value['dimension'] ?? $value['measurement'] ?? null;
                                                                    }

                                                                    return (object) [
                                                                        'utility_type' => is_string($label) ? trim($label) : $label,
                                                                        'count' => $count,
                                                                        'dimension' => $dimension,
                                                                        'order' => $index + 1,
                                                                    ];
                                                                });
                                                        }
                                                    }

                                                    // Get dimensions from site_plan_dimensions for sub-application
                                                    $subAppDimensions = DB::connection('sqlsrv')
                                                        ->table('site_plan_dimensions')
                                                        ->where('sub_application_id', $application->id)
                                                        ->orWhere('application_id', $application->main_application_id ?? 0)
                                                        ->get();
                                                    
                                                    // Process dimensions (if any)
                                                    if ($subAppDimensions->count() > 0) {
                                                        $jsiDimensions = $subAppDimensions->map(function($dim) {
                                                            $countValue = $dim->count ?? $dim->quantity ?? '1';
                                                            $countNormalized = trim((string) $countValue);
                                                            if ($countNormalized === '') {
                                                                $countNormalized = '1';
                                                            }

                                                            return (object) [
                                                                'description' => $dim->description ?? '',
                                                                'dimension' => $dim->dimension ?? '',
                                                                'count' => $countNormalized,
                                                            ];
                                                        });
                                                    }

                                                    // If no explicit dimensions, derive them from shared utilities
                                                    if ($jsiDimensions->count() === 0 && isset($subSharedUtilities) && $subSharedUtilities->count() > 0) {
                                                        $jsiDimensions = $subSharedUtilities->map(function ($utility, $index) {
                                                            $countValue = $utility->count ?? $utility->quantity ?? '1';
                                                            $countNormalized = trim((string) $countValue);
                                                            if ($countNormalized === '') {
                                                                $countNormalized = '1';
                                                            }

                                                            $description = $utility->utility_type ?? $utility->description ?? '';

                                                            return (object) [
                                                                'description' => is_string($description) ? trim($description) : (string) ($description ?? ''),
                                                                'dimension' => is_string($utility->dimension ?? '') ? trim((string) $utility->dimension) : (string) ($utility->dimension ?? ''),
                                                                'count' => $countNormalized,
                                                                'sn' => ($utility->order ?? $index + 1),
                                                            ];
                                                        })->filter(function ($entry) {
                                                            return ($entry->description !== '' || $entry->dimension !== '');
                                                        })->values();
                                                    }

                                                    // Final fallback: ensure at least one row when unit data exists
                                                    if ($jsiDimensions->count() === 0) {
                                                        $fallbackDescription = trim((string) ($application->unit_number ?? ''));
                                                        $fallbackDimension = $application->unit_size ?? null;

                                                        if ($fallbackDescription !== '' || !is_null($fallbackDimension)) {
                                                            $jsiDimensions = collect([(object) [
                                                                'description' => $fallbackDescription !== '' ? $fallbackDescription : 'Recorded Measurement',
                                                                'dimension' => $fallbackDimension !== null ? trim((string) $fallbackDimension) : '',
                                                                'count' => '1',
                                                            ]]);
                                                        }
                                                    }
                                                    
                                                    // ALWAYS process unit measurements (regardless of dimensions)
                                                    $mainAppId = $application->main_application_id ?? 0;
                                                    if ($mainAppId > 0) {
                                                        $buyerListData = DB::connection('sqlsrv')
                                                            ->table('buyer_list as bl')
                                                            ->leftJoin('st_unit_measurements as sum', function($join) {
                                                                // Join on buyer FK, not unit_no (unit numbers repeat and fan out).
                                                                $join->on('bl.id', '=', 'sum.buyer_id')
                                                                     ->on('bl.application_id', '=', 'sum.application_id');
                                                            })
                                                            ->where('bl.application_id', $mainAppId)
                                                            ->where('bl.unit_no', $application->unit_number)
                                                            ->select(
                                                                'bl.unit_no',
                                                                'bl.buyer_name',
                                                                'bl.buyer_title', 
                                                                'sum.measurement as unit_size'
                                                            )
                                                            ->get();
                                                            
                                                        if ($buyerListData->count() > 0) {
                                                            $jsiUnitMeasurements = $buyerListData->map(function($unit, $index) {
                                                                return (object) [
                                                                    'sn' => $index + 1,
                                                                    'unit_no' => $unit->unit_no,
                                                                    'unit_size' => $unit->unit_size,
                                                                    'buyer_name' => $unit->buyer_name,
                                                                    'buyer_title' => $unit->buyer_title
                                                                ];
                                                            });
                                                        } else {
                                                            // Fallback: Use sub-application data when no buyer list match
                                                            $jsiUnitMeasurements = collect([(object) [
                                                                'sn' => 1,
                                                                'unit_no' => $application->unit_number ?? 'N/A',
                                                                'unit_size' => $application->unit_size ?? null,
                                                                'buyer_name' => null,
                                                                'buyer_title' => null
                                                            ]]);
                                                        }
                                                    } else {
                                                        // No main application: Use sub-application data
                                                        $jsiUnitMeasurements = collect([(object) [
                                                            'sn' => 1,
                                                            'unit_no' => $application->unit_number ?? 'N/A',
                                                            'unit_size' => $application->unit_size ?? null,
                                                            'buyer_name' => null,
                                                            'buyer_title' => null
                                                        ]]);
                                                    }
                                                    
                                                } catch (Exception $e) {
                                                    $jsiDimensions = collect();
                                                    $jsiUnitMeasurements = collect();
                                                    $subSharedUtilities = collect();
                                                }
                                            @endphp
                                            @php
                                                $subSharedUtilitiesArray = $subSharedUtilities instanceof \Illuminate\Support\Collection
                                                    ? $subSharedUtilities->toArray()
                                                    : (is_array($subSharedUtilities) ? $subSharedUtilities : []);
                                            @endphp
                                            @include('actions.JOINT-SITE-INSPECTION-REPORT', [
                                                'application' => $application,
                                                'parentApplication' => $parentApplication ?? null,
                                                'report' => $jointInspectionReport,
                                                'dimensions' => $jsiDimensions,
                                                'unitMeasurements' => $jsiUnitMeasurements,
                                                'utilities' => $subSharedUtilities,
                                                'sharedAreasList' => $subSharedUtilitiesArray,
                                                'printMode' => false,
                                                'forEmbed' => true
                                            ])
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <div class="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i data-lucide="file-x" class="w-6 h-6 text-gray-400"></i>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-800 mb-2">No Joint Site Inspection Report Available</h4>
                                        <p class="text-gray-600 mb-4">A joint site inspection report has not been generated for this sub-application yet.</p>
                                        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center mx-auto" 
                                                onclick="alert('Joint site inspection functionality will be available soon.')">
                                            <i data-lucide="plus" class="w-4 h-4 mr-1"></i>
                                            Schedule Inspection
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Joint Site Inspection Modal -->
            {{-- <div id="jointInspectionModal" class="fixed inset-0 z-50 hidden items-center justify-center">
                <div class="absolute inset-0 bg-gray-900 bg-opacity-50" data-joint-inspection-dismiss></div>
                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-5 py-3 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Joint Site Inspection Report Details</h3>
                        <button type="button" class="text-gray-500 hover:text-gray-700" data-joint-inspection-dismiss>
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <form id="jointInspectionForm" class="p-6 space-y-6">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="application_id" value="{{ $application->main_application_id ?? $application->primary_id ?? $application->id }}">
                        <input type="hidden" name="sub_application_id" value="{{ $application->id }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Inspection Date <span class="text-red-500">*</span></label>
                                <input type="date" name="inspection_date" id="jointInspectionDate" max="{{ now()->toDateString() }}" value="{{ $jointInspectionDefaults['inspection_date'] ?? now()->toDateString() }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">LKN Number</label>
                                <input type="text" name="lkn_number" id="jointInspectionLkn" value="{{ $jointInspectionDefaults['lkn_number'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter LKN number">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Applicant / Scheme Name</label>
                                <input type="text" name="applicant_name" id="jointInspectionApplicant" value="{{ $jointInspectionDefaults['applicant_name'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Name of applicant or scheme">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Location</label>
                                <input type="text" name="location" id="jointInspectionLocation" value="{{ $jointInspectionDefaults['location'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter location">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Plot Number</label>
                                <input type="text" name="plot_number" id="jointInspectionPlot" value="{{ $jointInspectionDefaults['plot_number'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Plot number">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Scheme Number</label>
                                <input type="text" name="scheme_number" id="jointInspectionScheme" value="{{ $jointInspectionDefaults['scheme_number'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Scheme number">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Unit Number</label>
                                <input type="number" min="0" name="unit_number" id="jointInspectionUnitNumber" value="{{ $jointInspectionDefaults['unit_number'] ?? ($jointInspectionDefaults['sections_count'] ?? '') }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter unit number">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Unit Dimension (M²)</label>
                                <input type="text" name="unit_dimension" id="jointInspectionUnitDimension" value="{{ $jointInspectionDefaults['unit_dimension'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter unit dimension">
                                <p class="text-[11px] text-gray-500 mt-1">When supplied, this value fills Table B for the unit inspection report.</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Road Reservation</label>
                                <input type="text" name="road_reservation" id="jointInspectionRoadReservation" value="{{ $jointInspectionDefaults['road_reservation'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="e.g. 9m">
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-3">
                                <label class="block text-xs font-medium text-gray-700">Boundary Description</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">North</label>
                                        <textarea data-boundary-direction="north" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the northern boundary">{{ $jointInspectionDefaults['boundary_segments']['north'] ?? '' }}</textarea>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">East</label>
                                        <textarea data-boundary-direction="east" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the eastern boundary">{{ $jointInspectionDefaults['boundary_segments']['east'] ?? '' }}</textarea>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">South</label>
                                        <textarea data-boundary-direction="south" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the southern boundary">{{ $jointInspectionDefaults['boundary_segments']['south'] ?? '' }}</textarea>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">West</label>
                                        <textarea data-boundary-direction="west" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the western boundary">{{ $jointInspectionDefaults['boundary_segments']['west'] ?? '' }}</textarea>
                                    </div>
                                </div>
                                <textarea name="boundary_description" id="jointInspectionBoundary" rows="3" class="hidden" aria-hidden="true">{{ $jointInspectionDefaults['boundary_description'] ?? '' }}</textarea>
                                <p class="text-xs text-gray-500">Enter a note for each direction. We'll draft the combined boundary report automatically.</p>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-medium text-gray-700">Shared Utilities &amp; Measurements</label>
                                    <button type="button" id="addMeasurementEntry" class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 border border-green-200 rounded-md hover:bg-green-50">
                                        <span class="mr-1">+</span>
                                        Add Entry
                                    </button>
                                </div>
                                <div id="measurementEntriesContainer" class="space-y-2"></div>
                                <p class="text-xs text-gray-500">Each selected shared utility appears below. Capture the measurement for every utility, and add extra rows if you need to record more details.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Utilities Measurement Summary</label>
                                <textarea name="existing_site_measurement_summary" id="jointInspectionMeasurementSummary" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Provide a short summary for the measurements section">{{ $jointInspectionDefaults['existing_site_measurement_summary'] ?? $defaultMeasurementSummaryMessage }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">This note appears before the site measurement list in the generated report.</p>
                            </div>

                            <div class="flex items-center space-x-3">
                                <label class="inline-flex items-center text-sm text-gray-700">
                                    <input type="checkbox" name="available_on_ground" value="1" class="rounded border-gray-300 text-green-600 focus:ring-green-500" @checked($jointInspectionDefaults['available_on_ground'] ?? false)>
                                    <span class="ml-2">Site is available on the ground</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Prevailing Land Use</label>
                                    <select name="prevailing_land_use" id="jointInspectionPrevailingLandUse" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                        <option value="">Select prevailing land use</option>
                                        @foreach($jointInspectionLandUseOptions as $option)
                                            <option value="{{ $option }}" @selected(($jointInspectionDefaults['prevailing_land_use'] ?? '') === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Applied Land Use</label>
                                    <select name="applied_land_use" id="jointInspectionAppliedLandUse" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                        <option value="">Select applied land use</option>
                                        @foreach($jointInspectionLandUseOptions as $option)
                                            <option value="{{ $option }}" @selected(($jointInspectionDefaults['applied_land_use'] ?? '') === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-gray-700 mb-2">Shared Utilities</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @forelse($sharedUtilitiesOptions as $utility)
                                        <label class="flex items-center space-x-2">
                                            <input type="checkbox" name="shared_utilities[]" value="{{ $utility }}" class="rounded border-gray-300 text-green-600 focus:ring-green-500" @checked(in_array($utility, $jointInspectionDefaults['shared_utilities'] ?? []))>
                                            <span class="text-sm text-gray-700">{{ ucwords(str_replace(['_', '-'], ' ', $utility)) }}</span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-gray-500">No shared utilities recorded for this sub-application.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Compliance Status</label>
                                    <select name="compliance_status" id="jointInspectionCompliance" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                        <option value="obtainable" @selected(($jointInspectionDefaults['compliance_status'] ?? 'obtainable') === 'obtainable')>Obtainable</option>
                                        <option value="not_obtainable" @selected(($jointInspectionDefaults['compliance_status'] ?? '') === 'not_obtainable')>Not Obtainable</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Inspection Officer / Rank</label>
                                    <input type="text" name="inspection_officer" id="jointInspectionOfficer" value="{{ $jointInspectionDefaults['inspection_officer'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Officer name and rank">
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-gray-700 mb-2">Additional Observations?</p>
                                <div class="flex items-center space-x-4">
                                    <label class="inline-flex items-center text-sm text-gray-700">
                                        <input type="radio" name="has_additional_observations" value="1" class="text-green-600 focus:ring-green-500" @checked($jointInspectionDefaults['has_additional_observations'] ?? false)>
                                        <span class="ml-2">Yes</span>
                                    </label>
                                    <label class="inline-flex items-center text-sm text-gray-700">
                                        <input type="radio" name="has_additional_observations" value="0" class="text-green-600 focus:ring-green-500" @checked(!($jointInspectionDefaults['has_additional_observations'] ?? false))>
                                        <span class="ml-2">No</span>
                                    </label>
                                </div>
                            </div>

                            <div id="jointInspectionObservationsWrapper" class="{{ ($jointInspectionDefaults['has_additional_observations'] ?? false) ? '' : 'hidden' }}">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Additional Observation Details</label>
                                <textarea name="additional_observations" id="jointInspectionObservations" rows="4" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Document notable observations">{{ $jointInspectionDefaults['additional_observations'] ?? '' }}</textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t pt-4">
                            <div class="text-xs text-gray-500">
                                <span id="jsi-workflow-status">
                                    @if($jointInspectionReport)
                                        @if($jointInspectionReport->is_submitted)
                                            ✅ Submitted
                                        @elseif($jointInspectionReport->is_generated)
                                            📋 Generated
                                        @else
                                            💾 Saved
                                        @endif
                                    @else
                                        📝 Draft
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50" data-joint-inspection-dismiss>Cancel</button>
                                <button type="submit" id="jsi-save-btn" class="px-4 py-2 text-sm font-semibold bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Save</button>
                                <button type="button" id="jsi-generate-btn" class="px-4 py-2 text-sm font-semibold bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed" 
                                    @if(!$jointInspectionReport) disabled @endif>Generate</button>
                                <button type="button" id="jsi-submit-btn" class="px-4 py-2 text-sm font-semibold bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed" 
                                    @if(!$jointInspectionReport || !$jointInspectionReport->is_generated) disabled @endif>Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div> --}}

            <script>
                window.jointInspectionDefaults = @json($jointInspectionDefaults);
                window.jointInspectionBoundarySegments = @json($jointInspectionDefaults['boundary_segments'] ?? []);
                window.jointInspectionExistingReportUrl = @json($jointInspectionReport ? route('sub-actions.planning-recommendation.joint-inspection.show', $application->id) : null);
                window.jointInspectionSavedReport = @json($jointInspectionReport);
                
                // Debug: Log the data being passed
                console.log('DEBUG - jointInspectionDefaults:', window.jointInspectionDefaults);
                console.log('DEBUG - jointInspectionSavedReport:', window.jointInspectionSavedReport);
            </script>


            <!-- Decline Reason Modal -->
<div id="declineReasonModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50 hidden" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-4 border-b flex justify-between items-center bg-red-50">
            <h3 class="text-lg font-medium text-red-800">Specify Decline Reasons</h3>
            <button id="closeDeclineModal" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-6">
            <div class="text-sm text-gray-600 mb-4 bg-yellow-50 p-4 rounded-md border border-yellow-200">
                <p class="font-medium text-yellow-800">Instructions:</p>
                <p>Please select applicable reasons for declining this application and provide specific details for each selected reason.</p>
            </div>
            
             <!-- 1. Accessibility Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="accessibilityCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'accessibilityDetails')">
                    <div class="ml-3">
                        <label for="accessibilityCheck" class="font-medium text-gray-800 text-base">1. Accessibility Issues</label>
                        <p class="text-sm text-gray-600">The property/site must have adequate accessibility to ensure ease of movement and compliance with urban planning standards.</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="accessibilityDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="accessibilitySpecificDetails" class="block text-sm font-medium text-gray-700 mb-1">Specific details about accessibility issues:</label>
                        <textarea id="accessibilitySpecificDetails" rows="3" placeholder="E.g., The property lacks direct access to an approved road network..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="accessibilityObstructions" class="block text-sm font-medium text-gray-700 mb-1">Obstructions or barriers to access (if any):</label>
                        <textarea id="accessibilityObstructions" rows="2" placeholder="Describe any physical barriers or obstructions..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- 2. Land Use Conformity Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="conformityCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'conformityDetails')">
                    <div class="ml-3">
                        <label for="conformityCheck" class="font-medium text-gray-800 text-base">2. Land Use Conformity Issues</label>
                        <p class="text-sm text-gray-600">The property/site must conform to the existing land use designation of the area as per the Kano State Physical Development Plan.</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="conformityDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="landUseDetails" class="block text-sm font-medium text-gray-700 mb-1">Specific details about non-conformity:</label>
                        <textarea id="landUseDetails" rows="3" placeholder="E.g., The proposed use of the property conflicts with the designated residential zoning of the area..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="landUseDeviations" class="block text-sm font-medium text-gray-700 mb-1">Deviations from the approved land use plan:</label>
                        <textarea id="landUseDeviations" rows="2" placeholder="Describe any specific deviations from zoning or land use plans..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- 3. Utility Lines Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="utilityCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'utilityDetails')">
                    <div class="ml-3">
                        <label for="utilityCheck" class="font-medium text-gray-800 text-base">3. Utility Line Interference</label>
                        <p class="text-sm text-gray-600">The property/site must not transverse or interfere with existing utility lines (e.g., electricity, water, sewage).</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="utilityDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="utilityIssueDetails" class="block text-sm font-medium text-gray-700 mb-1">Specific details about utility line issues:</label>
                        <textarea id="utilityIssueDetails" rows="3" placeholder="E.g., The property boundary overlaps with an existing high-voltage power line corridor..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="utilityTypeDetails" class="block text-sm font-medium text-gray-700 mb-1">Type of utility line affected and implications:</label>
                        <textarea id="utilityTypeDetails" rows="2" placeholder="Specify the utility type (electricity, water, sewage) and safety/access implications..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- 4. Road Reservation Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="roadReservationCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'roadReservationDetails')">
                    <div class="ml-3">
                        <label for="roadReservationCheck" class="font-medium text-gray-800 text-base">4. Road Reservation Issues</label>
                        <p class="text-sm text-gray-600">The property/site must have an adequate access road or comply with minimum road reservation standards as stipulated in KAMMA guidelines.</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="roadReservationDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="roadReservationIssues" class="block text-sm font-medium text-gray-700 mb-1">Specific details about road/reservation issues:</label>
                        <textarea id="roadReservationIssues" rows="3" placeholder="E.g., The property lacks a defined access road, and the surrounding road network is below the required width..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="roadMeasurements" class="block text-sm font-medium text-gray-700 mb-1">Measurements or observations related to deficiencies:</label>
                        <textarea id="roadMeasurements" rows="2" placeholder="Provide relevant measurements (required vs. actual) and observations..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-t flex justify-end bg-gray-50">
            <button type="button" id="cancelDeclineReasons" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 mr-2" onclick="toggleModal(false)">
                Cancel
            </button>
            <button type="button" id="saveDeclineReasons" class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700">
                Save Reasons
            </button>
            <button type="button" id="saveAndViewDeclineReasons" class="ml-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700">
                Save & View Memo
            </button>
        </div>
    </div>
            <!-- Footer -->
            @include('admin.footer')
        </div>
    </div>
    </div>

<!-- Application Data Form JavaScript with Validation -->
<script>
// Application Data Form JavaScript with Validation
document.addEventListener('DOMContentLoaded', function() {
    // Get approval status from PHP
    const isApproved = '{{ strtolower($application->planning_recommendation_status ?? '') }}' === 'approved' || '{{ strtolower($application->planning_recommendation_status ?? '') }}' === 'approve';
    
    // Validation function
    function validateRequiredFields() {
        const lknNumber = document.getElementById('lkn_number').value.trim();
        const tpNumber = document.getElementById('tp_plan_number').value.trim();
        const approvedNumber = document.getElementById('approved_plan_number').value.trim();
        const schemeNumber = document.getElementById('scheme_plan_number').value.trim();

        // Update status indicators
        document.getElementById('lknStatus').textContent = (lknNumber && lknNumber !== 'Piece of Land') ? '✅' : '❌';
        document.getElementById('tpStatus').textContent = (tpNumber && tpNumber !== 'Piece of Land') ? '✅' : '❌';
        document.getElementById('approvedStatus').textContent = approvedNumber ? '✅' : '❌';
        document.getElementById('schemeStatus').textContent = schemeNumber ? '✅' : '❌';

        const allComplete = lknNumber && tpNumber && approvedNumber && schemeNumber && 
                           lknNumber !== 'Piece of Land' && tpNumber !== 'Piece of Land';
        
        // Enable Planning Recommendation Report tab if approved OR all fields complete
        const shouldEnableReportTab = isApproved || allComplete;
        
        // Update completion message
        const completionMessage = document.getElementById('completionMessage');
        if (shouldEnableReportTab) {
            if (isApproved) {
                completionMessage.className = 'mt-4 p-3 bg-green-50 border border-green-200 rounded-lg';
                completionMessage.innerHTML = `
                    <p class="text-sm text-green-700">
                        <i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i>
                        Application is approved! You can now access the Planning Recommendation Report tab.
                    </p>
                `;
            } else {
                completionMessage.className = 'mt-4 p-3 bg-green-50 border border-green-200 rounded-lg';
                completionMessage.innerHTML = `
                    <p class="text-sm text-green-700">
                        <i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i>
                        All required fields completed! You can now access the Planning Recommendation Report tab.
                    </p>
                `;
            }
        } else {
            completionMessage.className = 'mt-4 p-3 bg-red-50 border border-red-200 rounded-lg';
            completionMessage.innerHTML = `
                <p class="text-sm text-red-700">
                    <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                    Complete all required fields (LPKN Number, TP Plan Number, Approved Plan Number, and Scheme Plan No) above to unlock the Planning Recommendation Report tab.
                </p>
            `;
        }

        // Enable/disable Planning Recommendation Report tab
        const planningTab = document.getElementById('final-tab');
        if (planningTab) {
            const tabButton = document.querySelector('[data-tab="final"]');
            if (tabButton) {
                if (shouldEnableReportTab) {
                    tabButton.disabled = false;
                    tabButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    tabButton.classList.add('cursor-pointer');
                } else {
                    tabButton.disabled = true;
                    tabButton.classList.add('opacity-50', 'cursor-not-allowed');
                    tabButton.classList.remove('cursor-pointer');
                }
            }
        }

        return allComplete;
    }

    // Add event listeners to required fields
    const requiredFields = ['lkn_number', 'tp_plan_number', 'approved_plan_number', 'scheme_plan_number'];
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', validateRequiredFields);
            field.addEventListener('blur', validateRequiredFields);
        }
    });

    // Reset field function
    window.resetField = function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
            field.focus();
            validateRequiredFields();
        }
    };

    // Initial validation
    validateRequiredFields();

    // Prevent clicking on disabled Planning Recommendation Report tab
    document.addEventListener('click', function(e) {
        const finalTabButton = document.querySelector('[data-tab="final"]');
        if (finalTabButton && finalTabButton.disabled && finalTabButton.contains(e.target)) {
            e.preventDefault();
            e.stopPropagation();
            
            // Show appropriate alert message
            if (isApproved) {
                alert('The Planning Recommendation Report tab should be enabled for approved applications. Please refresh the page.');
            } else {
                alert('Please complete all required fields (LPKN Number, TP Plan Number, Approved Plan Number, and Scheme Plan No) in the "Complete survey data" tab before accessing the Planning Recommendation Report.');
            }
            
            return false;
        }
    });

    // Handle form submission
    const applicationDataForm = document.getElementById('applicationDataForm');
    if (applicationDataForm) {
        applicationDataForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // If application is approved, allow saving even with incomplete fields
            if (!isApproved && !validateRequiredFields()) {
                alert('Please fill in all required fields (LPKN Number, TP Plan Number, Approved Plan Number, and Scheme Plan No) before saving.');
                return;
            }
            
            // Collect form data
            const formData = new FormData(this);
            
            // Show loading state
            const submitBtn = document.getElementById('saveApplicationDataBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-1 animate-spin"></i>Saving...';
            submitBtn.disabled = true;
            
            // Send data to backend
            fetch('{{ route("sectionaltitling.saveApplicationData") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Application data saved successfully!');
                    validateRequiredFields();
                } else {
                    alert('Error saving data: ' + (data.error || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving data: ' + error.message);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
</script>

@include('sub_actions.parts.sub_recomm_js')

<!-- JSI Approval JavaScript -->
<script src="{{ asset('js/jsi-approval.js') }}"></script>

@endsection

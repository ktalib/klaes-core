{{-- Variables and PHP Logic --}}
@php
    $surveyRecord = DB::connection('sqlsrv')
        ->table('surveyCadastralRecord')
        ->where('application_id', $application->id)
        ->first();

    // Conversion-only meta sentinels (__need_for_eia__, __conversion_reservation_meta__,
    // ...) are stored alongside real utilities but must never be shown on an ST
    // One Stop Shop recommendation.
    $isMetaSentinel = function ($value) {
        return preg_match('/^__.+__$/', trim((string) $value)) === 1;
    };

    $sharedUtilitiesOptions = [];
    try {
        $sharedUtilitiesOptions = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $application->id)
            ->pluck('utility_type')
            ->filter()
            ->reject($isMetaSentinel)
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

    // One guard after the branch, so the sentinels are stripped whether the list
    // came from the JSI report (array or JSON) or from the shared_utilities table.
    $selectedSharedUtilities = collect($selectedSharedUtilities)
        ->reject(function ($utility) use ($isMetaSentinel) {
            $label = is_array($utility) ? ($utility['utility_type'] ?? reset($utility)) : $utility;
            return $isMetaSentinel($label);
        })
        ->values()
        ->all();

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

    $locationCandidates = array_filter([
        $jointInspectionReport->location ?? null,
        (($application->property_street_name ?? null) ? trim($application->property_street_name) : null),
        $application->property_lga ?? null,
        $application->property_location ?? null,
    ]);
    $jointInspectionLocation = $locationCandidates ? array_values($locationCandidates)[0] : '';

    $measurementEntries = [];
    if ($jointInspectionReport && is_array($jointInspectionReport->existing_site_measurement_entries)) {
        $measurementEntries = array_values(array_filter(array_map(function ($entry) {
            if (!is_array($entry)) {
                return null;
            }

            $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
            $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
            $measurementDimension = isset($entry['measurement_dimension'])
                ? trim((string) $entry['measurement_dimension'])
                : '';
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
        }, $jointInspectionReport->existing_site_measurement_entries ?? [])));
    }

    if (empty($measurementEntries)) {
        $dimensionRecords = DB::connection('sqlsrv')
            ->table('site_plan_dimensions')
            ->where('application_id', $application->id)
            ->orderBy('order')
            ->get();

        if ($dimensionRecords->count() > 0) {
            $measurementEntries = $dimensionRecords->map(function ($dimension) {
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
                    'count' => isset($dimension->count) ? (string) $dimension->count : '1',
                ];
            })->values()->toArray();
        }
    }

    if (empty($measurementEntries)) {
        $measurementEntries = [
            ['description' => '', 'measurement_dimension' => '', 'dimension' => '', 'count' => '1'],
        ];
    }

    $defaultMeasurementSummaryMessage = 'No shared utility measurements were submitted for this inspection.';
    $defaultBoundaryDescriptionTemplate = "Boundary demarcation:\n- North: \n- East: \n- South: \n- West: ";

    $jointInspectionDefaults = [
        'inspection_date' => $jointInspectionReport && $jointInspectionReport->inspection_date ? \Carbon\Carbon::parse($jointInspectionReport->inspection_date)->format('Y-m-d') : now()->toDateString(),
        'lkn_number' => $jointInspectionReport->lkn_number ?? ($surveyRecord->tp_plan_no ?? $application->lkn_number ?? ''),
        'applicant_name' => $jointInspectionApplicant,
        'file_number' => $jointInspectionReport->file_number ?? ($application->fileno ?? $application->np_fileno ?? ''),
        'location' => $jointInspectionReport->location ?? $jointInspectionLocation,
        'plot_number' => $jointInspectionReport->plot_number ?? $application->property_plot_no ?? '',
        'unit_number' => $jointInspectionReport->unit_number ?? ($application->unit_number ?? ''),
        'scheme_number' => $jointInspectionReport->scheme_number ?? $application->scheme_no ?? '',
        'boundary_description' => $jointInspectionReport->boundary_description ?? $defaultBoundaryDescriptionTemplate,
        'available_on_ground' => $jointInspectionReport ? (bool) $jointInspectionReport->available_on_ground : false,
        'sections_count' => $jointInspectionReport->sections_count ?? '',
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
        'Mixed Use',
        'Public Use',
        'Recreational',
    ];
@endphp

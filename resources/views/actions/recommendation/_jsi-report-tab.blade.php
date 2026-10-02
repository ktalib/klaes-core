{{-- Joint Site Inspection Report Tab --}}
@php
    $jsiReportTabActive = ($activeTab ?? null) === 'jsi-report';
@endphp
<div id="jsi-report-tab" class="tab-content {{ $jsiReportTabActive ? 'active' : '' }}">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b bg-green-50">
            <h3 class="text-lg font-medium text-green-800 flex items-center">
                <i data-lucide="clipboard-check" class="w-5 h-5 mr-2"></i>
                Joint Site Inspection Report
            </h3>
            <p class="text-sm text-green-600 mt-1">View and print the comprehensive joint site inspection report for this application.</p>
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
                            @if(request()->get('url') === 'recommendation')
                                @if($jointInspectionReport->is_approved ?? false)
                                    <!-- Approved State - All buttons enabled -->
                                    <a href="{{ route('planning-recommendation.joint-inspection.show', $application->id) }}" 
                                       target="_blank"
                                       class="jsi-view-btn px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                                        <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                        View Report
                                    </a>
                                    <a href="{{ route('planning-recommendation.joint-inspection.show', $application->id) }}?print=true" 
                                       target="_blank"
                                       class="jsi-print-btn px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
                                        <i data-lucide="printer" class="w-4 h-4 mr-1"></i>
                                        Print
                                    </a>
                                @else
                                    <!-- Pending Approval State - Buttons disabled, show approve button -->
                                    <div class="jsi-approval-container flex gap-2">
                                        <button disabled
                                               class="jsi-view-btn disabled cursor-not-allowed px-4 py-2 bg-gray-400 text-gray-200 rounded-lg flex items-center">
                                            <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                            View Report
                                        </button>
                                        <button disabled
                                               class="jsi-print-btn disabled cursor-not-allowed px-4 py-2 bg-gray-400 text-gray-200 rounded-lg flex items-center">
                                            <i data-lucide="printer" class="w-4 h-4 mr-1"></i>
                                            Print
                                        </button>
                                        <button type="button" 
                                               data-jsi-approve
                                               @if(isset($application->main_application_id) && !empty($application->main_application_id))
                                                   data-sub-application-id="{{ $application->id }}"
                                               @else
                                                   data-application-id="{{ $application->id }}"
                                               @endif
                                               data-reload-on-success="true"
                                               data-confirm-message="Are you sure you want to approve this Joint Site Inspection report for application {{ $application->application_no ?? $application->id }}?"
                                               class="jsi-approve-btn px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 flex items-center">
                                            <i data-lucide="check-circle" class="w-4 h-4 mr-1"></i>
                                            Approve JSI
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
                            // Prepare dimensions data for JSI report with fallback to st_unit_measurements table
                            $rawDimensions = collect($measurementEntries ?? []);

                            if ($rawDimensions->isEmpty()) {
                                try {
                                    $rawDimensions = DB::connection('sqlsrv')
                                        ->table('st_unit_measurements')
                                        ->where('application_id', $application->id)
                                        ->orderBy('unit_no')
                                        ->get();
                                } catch (Exception $e) {
                                    $rawDimensions = collect();
                                }
                            }

                            $dimensionIdentifier = function($entry) {
                                if (is_array($entry)) {
                                    return $entry['description'] ?? $entry['unit_no'] ?? '';
                                }

                                return $entry->description ?? $entry->unit_no ?? '';
                            };

                            $shouldSortDimensions = $rawDimensions->count() > 0 && $rawDimensions->every(function($entry) use ($dimensionIdentifier) {
                                $candidate = $dimensionIdentifier($entry);
                                $normalized = is_string($candidate) ? trim($candidate) : '';

                                if ($normalized === '') {
                                    return false;
                                }

                                return preg_match('/^\d+$/', $normalized) === 1;
                            });

                            $orderedDimensions = $shouldSortDimensions
                                ? $rawDimensions->sortBy(function($entry, $index) use ($dimensionIdentifier) {
                                    $candidate = $dimensionIdentifier($entry);
                                    $normalized = is_string($candidate) ? trim($candidate) : '';
                                    return str_pad($normalized, 10, '0', STR_PAD_LEFT);
                                })->values()
                                : $rawDimensions->values();

                            $jsiDimensions = $orderedDimensions->map(function($entry, $index) {
                                if (is_array($entry)) {
                                    $description = $entry['description'] ?? $entry['unit_no'] ?? '';
                                    $dimension = $entry['dimension'] ?? $entry['measurement'] ?? '';
                                    $countValue = $entry['count'] ?? $entry['quantity'] ?? '1';
                                } else {
                                    $description = $entry->description ?? $entry->unit_no ?? '';
                                    $dimension = $entry->dimension ?? $entry->measurement ?? '';
                                    $countValue = $entry->count ?? $entry->quantity ?? '1';
                                }

                                $countNormalized = trim((string) $countValue) === '' ? '1' : trim((string) $countValue);

                                return (object) [
                                    'sn' => $index + 1,
                                    'description' => $description,
                                    'dimension' => $dimension,
                                    'count' => $countNormalized,
                                ];
                            });

                            // Prepare unit measurements from buyer_list and st_unit_measurements
                            $jsiUnitMeasurements = collect();
                                try {
                                    $jsiUnitMeasurements = DB::connection('sqlsrv')
                                        ->table('buyer_list as bl')
                                        ->leftJoin('st_unit_measurements as sum', function($join) {
                                            // Join on buyer FK, not unit_no — unit numbers repeat and fan out the rows.
                                            $join->on('bl.id', '=', 'sum.buyer_id')
                                                 ->on('bl.application_id', '=', 'sum.application_id');
                                        })
                                        ->where('bl.application_id', $application->id)
                                        ->select(
                                            'bl.unit_no',
                                            'bl.buyer_name',
                                            'bl.buyer_title',
                                            'sum.measurement as measurement',
                                            'sum.dimension as measurement_dimension'
                                        )
                                        ->get()
                                        ->map(function($unit, $index) {
                                            $primaryMeasurement = $unit->measurement ?? $unit->measurement_dimension ?? null;

                                            return (object) [
                                                'sn' => $index + 1,
                                                'unit_no' => $unit->unit_no,
                                                'measurement' => $primaryMeasurement,
                                                'buyer_name' => $unit->buyer_name,
                                                'buyer_title' => $unit->buyer_title
                                            ];
                                        });
                                } catch (Exception $e) {
                                    $jsiUnitMeasurements = collect();
                                }

                                if ($jsiUnitMeasurements->isEmpty()) {
                                    try {
                                        $jsiUnitMeasurements = DB::connection('sqlsrv')
                                            ->table('st_unit_measurements')
                                            ->where('application_id', $application->id)
                                            ->orderBy('unit_no')
                                            ->get()
                                            ->map(function($record, $index) {
                                                $measurementValue = $record->measurement
                                                    ?? $record->dimension
                                                    ?? ($record->unit_size ?? null);

                                                return (object) [
                                                    'sn' => $index + 1,
                                                    'unit_no' => $record->unit_no,
                                                    'measurement' => $measurementValue,
                                                    'buyer_name' => null,
                                                    'buyer_title' => null,
                                                ];
                                            });
                                    } catch (Exception $e) {
                                        $jsiUnitMeasurements = collect();
                                    }
                                }

                                if ($jsiUnitMeasurements->isEmpty() && !empty($measurementEntries)) {
                                    $jsiUnitMeasurements = collect($measurementEntries)
                                        ->filter(function ($entry) {
                                            if (!is_array($entry)) {
                                                return false;
                                            }

                                            return isset($entry['dimension']) && trim((string) $entry['dimension']) !== '';
                                        })
                                        ->values()
                                        ->map(function($entry, $index) {
                                            $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
                                            return (object) [
                                                'sn' => $index + 1,
                                                'unit_no' => $description !== '' ? $description : 'Unit '.($index + 1),
                                                'measurement' => $entry['dimension'] ?? null,
                                                'buyer_name' => null,
                                                'buyer_title' => null,
                                                'append_unit' => !empty($entry['dimension']) && is_numeric($entry['dimension']),
                                            ];
                                        });
                                }

                                $unitDimensionRaw = $jointInspectionReport->unit_dimension ?? null;
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
                                    $hasMeasurementRecorded = $jsiUnitMeasurements->contains(function ($entry) {
                                        return isset($entry->measurement) && trim((string) $entry->measurement) !== '';
                                    });

                                    if (!$hasMeasurementRecorded) {
                                        if ($jsiUnitMeasurements->isEmpty()) {
                                            $unitNumberCandidates = [
                                                $jointInspectionReport->unit_number ?? null,
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

                                            $jsiUnitMeasurements = collect([(object) [
                                                'sn' => 1,
                                                'unit_no' => $unitNumber,
                                                'measurement' => $unitDimensionFormatted,
                                                'buyer_name' => null,
                                                'buyer_title' => null,
                                                'append_unit' => $unitDimensionAppendsUnit,
                                            ]]);
                                        } else {
                                            $jsiUnitMeasurements = $jsiUnitMeasurements->map(function ($entry, $index) use ($unitDimensionFormatted, $unitDimensionAppendsUnit) {
                                                if ($index === 0) {
                                                    $entry->measurement = $unitDimensionFormatted;
                                                    $entry->append_unit = $unitDimensionAppendsUnit;
                                                }
                                                return $entry;
                                            })->values();
                                        }
                                    }
                                }
                        @endphp
                        <iframe
                            src="{{ route('planning-recommendation.joint-inspection.show', $application->id) }}?print=true"
                            class="w-full border-0"
                            style="height: 1050px;"
                            title="Joint Site Inspection Report Preview">
                        </iframe>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="file-x" class="w-6 h-6 text-gray-400"></i>
                    </div>
                    <h4 class="text-lg font-medium text-gray-800 mb-2">No Joint Site Inspection Report Available</h4>
                    <p class="text-gray-600 mb-4">A joint site inspection report has not been generated for this application yet.</p>
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

{{-- JSI Approval JavaScript --}}
<script>
// Ensure CSRF token is available for AJAX requests
if (!$('meta[name="csrf-token"]').length) {
    $('head').append('<meta name="csrf-token" content="{{ csrf_token() }}">');
}

// Initialize JSI approval when this tab content loads
$(document).ready(function() {
    console.log('DEBUG: JSI Report Tab loaded');
    
    // Ensure jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded');
        return;
    }
    
    // Initialize JSI approval if function exists
    if (typeof initializeJSIApproval === 'function') {
        initializeJSIApproval();
    } else {
        console.log('DEBUG: initializeJSIApproval function not found, setting up basic handler');
        
        const fallbackConfirmJSIApproval = async (applicationType, appId) => {
            const message = `Are you sure you want to approve the Joint Site Inspection Report for ${applicationType} ${appId}?`;

            if (window.Swal && typeof window.Swal.fire === 'function') {
                const result = await window.Swal.fire({
                    title: 'Approve Joint Site Inspection?',
                    text: message,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, approve',
                    cancelButtonText: 'No, cancel',
                    reverseButtons: true,
                    focusCancel: true,
                });

                return !!result.isConfirmed;
            }

            return window.confirm(`${message}\n\nSelect "Yes" to approve or "No" to cancel.`);
        };

        // Fallback handler if the main JS file hasn't loaded yet
        $(document).on('click', '[data-jsi-approve]', async function(e) {
            e.preventDefault();
            const button = $(this);
            const applicationId = button.data('application-id');
            const subApplicationId = button.data('sub-application-id');
            
            console.log('DEBUG: JSI Approve clicked', {applicationId, subApplicationId});
            
            if (!applicationId && !subApplicationId) {
                alert('Error: Application ID is required for approval');
                return;
            }
            
            const applicationType = subApplicationId ? 'sub-application' : 'application';
            const appId = subApplicationId || applicationId;

            const userConfirmed = await fallbackConfirmJSIApproval(applicationType, appId);
            if (!userConfirmed) {
                console.log('DEBUG: User cancelled JSI approval (fallback handler)');
                return;
            }
            
            // Set loading state
            const originalText = button.html();
            button.prop('disabled', true)
                  .html('<i class="fas fa-spinner fa-spin mr-1"></i> Approving...');
            
            // Prepare request data
            const requestData = {
                _token: '{{ csrf_token() }}'
            };
            
            // Add appropriate ID
            if (subApplicationId) {
                requestData.sub_application_id = subApplicationId;
            } else {
                requestData.application_id = applicationId;
            }
            
            // Determine the correct route
            const approvalRoute = subApplicationId 
                ? '/sub-actions/planning-recommendation/joint-site-inspection/approve'
                : '/planning-recommendation/joint-site-inspection/approve';
            
            console.log('DEBUG: Making AJAX request to:', approvalRoute, requestData);
            
            // Make AJAX request
            $.ajax({
                url: approvalRoute,
                type: 'POST',
                data: requestData,
                dataType: 'json',
                success: function(response) {
                    console.log('DEBUG: JSI approval success:', response);
                    
                    if (response.success) {
                        alert(response.message || 'JSI Report approved successfully!');
                        
                        // Update UI elements
                        $('.jsi-status').removeClass('bg-yellow-100 text-yellow-800')
                                       .addClass('bg-green-100 text-green-800')
                                       .html('<i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>Approved');
                        
                        // Hide approve button container
                        button.closest('.jsi-approval-container').html(`
                            <div class="flex items-center gap-2 text-green-600 bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                <span class="font-medium">Approved</span>
                                <span class="text-sm text-gray-600">by ${response.data.approved_by || 'System'}</span>
                            </div>
                        `);

                        // Reload the page to reflect changes
                        if (button.data('reload-on-success')) {
                            location.reload();
                        }
                        
                        // Enable print and view buttons
                        $('.jsi-print-btn, .jsi-view-btn').each(function() {
                            const btn = $(this);
                            if (btn.hasClass('disabled') || btn.prop('disabled')) {
                                btn.removeClass('disabled bg-gray-400 text-gray-200 cursor-not-allowed')
                                   .prop('disabled', false);
                                
                                if (btn.hasClass('jsi-print-btn')) {
                                    btn.addClass('bg-green-600 text-white hover:bg-green-700');
                                } else {
                                    btn.addClass('bg-blue-600 text-white hover:bg-blue-700');
                                }
                            }
                        });
                        
                        // Refresh Lucide icons
                        if (typeof lucide !== 'undefined' && lucide.createIcons) {
                            lucide.createIcons();
                        }
                        
                    } else {
                        alert(response.message || 'Failed to approve JSI report');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('DEBUG: JSI approval error:', {xhr, status, error});
                    
                    let errorMessage = 'An error occurred while approving the JSI report';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMessage = 'JSI approval endpoint not found';
                    } else if (xhr.status === 422) {
                        errorMessage = 'Invalid request data';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred';
                    }
                    
                    alert(errorMessage);
                },
                complete: function() {
                    // Reset loading state only if button still exists and wasn't replaced
                    if (button.length && button.hasClass('jsi-approve-btn')) {
                        button.prop('disabled', false).html(originalText);
                    }
                }
            });
        });
    }
});
</script>
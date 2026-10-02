{{-- Joint Site Inspection Modal --}}
<div id="jointInspectionModal" class="fixed inset-0 z-50 hidden items-center justify-center">
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
            <input type="hidden" name="application_id" value="{{ $application->id }}">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Inspection Date <span class="text-red-500">*</span></label>
                    <input type="date" name="inspection_date" id="jointInspectionDate" max="{{ now()->toDateString() }}" value="{{ $jointInspectionDefaults['inspection_date'] ?? now()->toDateString() }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">LPKN Number</label>
                    <input type="text" name="lkn_number" id="jointInspectionLkn" value="{{ $jointInspectionDefaults['lkn_number'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter LPKN number">
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
                    <input type="text" name="plot_number" id="jointInspectionPlot" value="{{ $jointInspectionDefaults['plot_number'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter plot number">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Scheme Number</label>
                    <input type="text" name="scheme_number" id="jointInspectionScheme" value="{{ $jointInspectionDefaults['scheme_number'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Scheme number">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Unit Number</label>
                    <input type="text" name="unit_number" id="jointInspectionUnitNumber" value="{{ $jointInspectionDefaults['unit_number'] ?? ($jointInspectionDefaults['sections_count'] ?? '') }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter unit number">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Unit Dimension (M²)</label>
                    <input type="text" name="unit_dimension" id="jointInspectionUnitDimension" value="{{ $jointInspectionDefaults['unit_dimension'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter unit dimension">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">No. of Sections</label>
                    <input type="text"   name="sections_count" id="jointInspectionSections" value="{{ $jointInspectionDefaults['sections_count'] ?? '' }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Sections count">
                </div>
                <div>
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
                            <p class="text-sm text-gray-500">No shared utilities recorded for this application.</p>
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

            <div class="flex items-center justify-end gap-3 border-t pt-4">
                <button type="button" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50" data-joint-inspection-dismiss>Cancel</button>
                <button type="submit" id="jointInspectionSubmit" class="px-4 py-2 text-sm font-semibold bg-green-600 text-white rounded-md hover:bg-green-700">Save &amp; Generate Report</button>
            </div>
        </form>
    </div>
</div>
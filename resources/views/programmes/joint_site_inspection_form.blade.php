@php
    $editorMode = $editorMode ?? 'modal';
    $formClasses = trim(($formClasses ?? 'p-6') . ' space-y-0');
    $defaultCancelUrl = route('programmes.approvals.planning_recomm', ['url' => 'view']);
    $cancelUrl = $cancelUrl ?? (url()->previous() ?: $defaultCancelUrl);
@endphp

<form id="jointInspectionForm" class="{{ $formClasses }}">
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
    <input type="hidden" name="application_id" id="modal_application_id" value="">
    <input type="hidden" name="sub_application_id" id="modal_sub_application_id" value="">
    <input type="hidden" name="existing_site_measurement_entries" id="existing_site_measurement_entries" value="">
    <input type="hidden" name="inspection_officer_id" id="inspectionOfficerId" value="">
    <input type="hidden" name="source" id="jointInspectionSource" value="">
    <input type="hidden" name="is_conversion_file" id="jointInspectionIsConversion" value="0">
    <input type="hidden" name="location" id="jointInspectionLocation" value="">
    <input type="hidden" name="applicant_address" id="jointInspectionApplicantAddress" value="">

    @php
        $streetOptions = $streetNames ?? collect();
        $districtOptions = $districts ?? collect();
        $lgaOptions = $lgas ?? collect();
        $stateOptions = $states ?? collect();
        $jsiDetailedLandUses = collect($jsiDetailedLandUses ?? []);
        $jsiPurposesByLandUse = collect($jsiPurposesByLandUse ?? []);
        $jsiRoadCategories = collect($jsiRoadCategories ?? []);
        $jsiReservations = collect($jsiReservations ?? []);
        $landUseNameById = collect($landUseTypes ?? [])->pluck('name', 'id');
    @endphp

    <div id="jointInspectionValidationErrors" class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 mb-4"></div>

    {{-- ===== Wizard Stepper Bar ===== --}}
    <div id="jsiWizardStepper" class="mb-6">
        <div class="flex items-center justify-between">
            <button type="button" class="jsi-step-indicator flex items-center gap-2 group" data-step="1">
                <span class="jsi-step-circle flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 transition-all border-green-600 bg-green-600 text-white">1</span>
                <span class="text-xs font-medium text-gray-700 hidden sm:inline">Application</span>
            </button>
            <div class="flex-1 h-0.5 bg-gray-200 mx-2"><div class="jsi-step-bar h-full bg-green-500 transition-all" data-bar="1" style="width:0%"></div></div>
            <button type="button" class="jsi-step-indicator flex items-center gap-2 group" data-step="2">
                <span class="jsi-step-circle flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 transition-all border-gray-300 bg-white text-gray-400">2</span>
                <span class="text-xs font-medium text-gray-400 hidden sm:inline">Boundary</span>
            </button>
            <div class="flex-1 h-0.5 bg-gray-200 mx-2"><div class="jsi-step-bar h-full bg-green-500 transition-all" data-bar="2" style="width:0%"></div></div>
            <button type="button" class="jsi-step-indicator flex items-center gap-2 group" data-step="3">
                <span class="jsi-step-circle flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 transition-all border-gray-300 bg-white text-gray-400">3</span>
                <span class="text-xs font-medium text-gray-400 hidden sm:inline">Measurements</span>
            </button>
            <div class="flex-1 h-0.5 bg-gray-200 mx-2"><div class="jsi-step-bar h-full bg-green-500 transition-all" data-bar="3" style="width:0%"></div></div>
            <button type="button" class="jsi-step-indicator flex items-center gap-2 group" data-step="4">
                <span class="jsi-step-circle flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 transition-all border-gray-300 bg-white text-gray-400">4</span>
                <span class="text-xs font-medium text-gray-400 hidden sm:inline">Review</span>
            </button>
        </div>
    </div>

    {{-- ===== STEP 1: Application Details ===== --}}
    <div class="jsi-wizard-step space-y-5" data-wizard-step="1">
        <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
            <strong>Step 1 of 4 — Application Details.</strong> Enter the applicant and location information.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">File Number <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" name="file_number" id="jointInspectionFileNumber" value="" readonly class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-gray-50 cursor-not-allowed" placeholder="File number from application" data-jsi-validate>
                    <button type="button" id="jsiSelectFileNumberBtn" class="hidden shrink-0 inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-xs font-bold rounded-md hover:bg-blue-700 transition-colors shadow-sm" title="Select file number">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Select
                    </button>
                </div>
                <p id="jsiFileNumberHint" class="mt-1 text-[10px] text-gray-500">File number is locked to the current application.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Inspection Date <span class="text-red-500">*</span></label>
                <input type="date" name="inspection_date" id="jointInspectionDate" max="{{ now()->format('Y-m-d') }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" data-jsi-validate>
                <p class="mt-1 text-[11px] text-gray-500">Use the actual date the inspection took place.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Applicant Name <span class="text-red-500">*</span></label>
                <input type="text" name="applicant_name" id="jointInspectionApplicant" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Name of applicant" data-jsi-validate>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">LPKN Number</label>
                <input type="text" name="lkn_number" id="jointInspectionLkn" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter LPKN number" data-jsi-validate>
            </div>

            {{-- Address builders (shown for Conversion context) --}}
            <div id="jsiAddressBuildersRow" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2 border border-gray-200 rounded-md p-3 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-medium text-gray-700">Applicant Address <span class="text-red-500">*</span></label>
                        <span class="text-[11px] text-gray-500">Plot, Street, District, LGA, State</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Plot Number</label>
                            <input type="text" id="applicantAddressPlot" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Plot number">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Street Name</label>
                            <select id="applicantAddressStreet" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select street</option>
                                @foreach($streetOptions as $street)
                                    <option value="{{ $street->name }}">{{ $street->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" id="applicantAddressStreetOther" class="hidden w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 mt-2" placeholder="Specify street name">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">District</label>
                            <select id="applicantAddressDistrict" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select district</option>
                                @foreach($districtOptions as $district)
                                    <option value="{{ $district->name ?? $district->DistrictName ?? $district->district ?? '' }}">{{ $district->name ?? $district->DistrictName ?? $district->district ?? '' }}</option>
                                @endforeach
                            </select>
                            <input type="text" id="applicantAddressDistrictOther" class="hidden w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 mt-2" placeholder="Specify district">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">LGA</label>
                            <select id="applicantAddressLga" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select LGA</option>
                                @foreach($lgaOptions as $lga)
                                    <option value="{{ $lga->name ?? $lga->LGAName ?? '' }}">{{ $lga->name ?? $lga->LGAName ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">State</label>
                            <select id="applicantAddressState" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select state</option>
                                @forelse($stateOptions as $state)
                                    <option value="{{ $state->StateName ?? $state->name ?? '' }}" @if(($state->StateName ?? $state->name ?? '') === 'Kano') selected @endif>{{ $state->StateName ?? $state->name ?? '' }}</option>
                                @empty
                                    <option value="Kano" selected>Kano</option>
                                @endforelse
                            </select>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-600">Preview: <span id="applicantAddressPreview" class="font-medium text-gray-800">Not set</span></p>
                </div>

                <div class="space-y-2 border border-gray-200 rounded-md p-3 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-medium text-gray-700">Property Location <span class="text-red-500">*</span></label>
                        <span class="text-[11px] text-gray-500">Plot, Street, District, LGA, State</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1" id="plotNumberLabel">Plot Number <span class="text-red-500">*</span></label>
                            <input type="text" name="plot_number" id="jointInspectionPlot" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter plot number" data-jsi-validate>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Street Name</label>
                            <select id="locationStreet" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select street</option>
                                @foreach($streetOptions as $street)
                                    <option value="{{ $street->name }}">{{ $street->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" id="locationStreetOther" class="hidden w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 mt-2" placeholder="Specify street name">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">District</label>
                            <select id="locationDistrict" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select district</option>
                                @foreach($districtOptions as $district)
                                    <option value="{{ $district->name ?? $district->DistrictName ?? $district->district ?? '' }}">{{ $district->name ?? $district->DistrictName ?? $district->district ?? '' }}</option>
                                @endforeach
                            </select>
                            <input type="text" id="locationDistrictOther" class="hidden w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 mt-2" placeholder="Specify district">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">LGA</label>
                            <select id="locationLga" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select LGA</option>
                                @foreach($lgaOptions as $lga)
                                    <option value="{{ $lga->name ?? $lga->LGAName ?? '' }}">{{ $lga->name ?? $lga->LGAName ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">State</label>
                            <select id="locationState" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                                <option value="">Select state</option>
                                @forelse($stateOptions as $state)
                                    <option value="{{ $state->StateName ?? $state->name ?? '' }}" @if(($state->StateName ?? $state->name ?? '') === 'Kano') selected @endif>{{ $state->StateName ?? $state->name ?? '' }}</option>
                                @empty
                                    <option value="Kano" selected>Kano</option>
                                @endforelse
                            </select>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-600">Preview: <span id="locationAddressPreview" class="font-medium text-gray-800">Not set</span></p>
                </div>
            </div>

            {{-- Simple full-address inputs (shown for ST One Stop Shop context) --}}
            <div id="jsiAddressSimpleRow" class="md:col-span-2 space-y-4 hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Plot Number <span class="text-red-500">*</span></label>
                        <input type="text" id="jsiSimplePlotNumber" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter plot number" data-jsi-validate>
                    </div>
                    <div id="schemeNumberGroupSimple">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Scheme Number <span class="text-red-500">*</span></label>
                        <input type="text" id="jsiSimpleSchemeNumber" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Scheme number" data-jsi-validate>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Applicant Address <span class="text-red-500">*</span></label>
                        <input type="text" id="jsiSimpleApplicantAddress" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Full applicant address" data-jsi-validate>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Property Location <span class="text-red-500">*</span></label>
                        <input type="text" id="jsiSimplePropertyLocation" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Full property location" data-jsi-validate>
                    </div>
                </div>
            </div>
            <div id="schemeNumberGroup">
                <label class="block text-xs font-medium text-gray-700 mb-1">Scheme Number <span class="text-red-500">*</span></label>
                <input type="text" name="scheme_number" id="jointInspectionScheme" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Scheme number" data-jsi-validate>
            </div>
        </div>
    </div>

    {{-- ===== STEP 2: Boundary & Inspection Details ===== --}}
    <div class="jsi-wizard-step space-y-5 hidden" data-wizard-step="2">
        <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
            <strong>Step 2 of 4 — Boundary & Inspection.</strong> Describe boundaries, unit info, and inspection-specific details.
        </div>

        <div id="boundaryDescriptionSection" class="space-y-3">
            <label class="block text-xs font-medium text-gray-700">Boundary Description <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="space-y-1">
                    <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">North <span class="text-red-500">*</span></label>
                    <textarea data-boundary-direction="north" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the northern boundary" data-jsi-validate></textarea>
                </div>
                <div class="space-y-1">
                    <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">East <span class="text-red-500">*</span></label>
                    <textarea data-boundary-direction="east" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the eastern boundary" data-jsi-validate></textarea>
                </div>
                <div class="space-y-1">
                    <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">South <span class="text-red-500">*</span></label>
                    <textarea data-boundary-direction="south" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the southern boundary" data-jsi-validate></textarea>
                </div>
                <div class="space-y-1">
                    <label class="block text-[11px] font-medium text-gray-600 uppercase tracking-wide">West <span class="text-red-500">*</span></label>
                    <textarea data-boundary-direction="west" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe the western boundary" data-jsi-validate></textarea>
                </div>
            </div>
            <textarea name="boundary_description" id="jointInspectionBoundary" rows="3" class="hidden" aria-hidden="true"></textarea>
            <p class="text-xs text-gray-500">Enter a note for each direction. We'll draft the combined boundary report automatically.</p>
        </div>

        <div class="border border-amber-200 bg-amber-50 rounded-md p-4 space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-amber-800">JSI Confirmation</p>
                <span class="text-[11px] text-amber-700">Step 2</span>
            </div>

            <div>
                <p class="block text-xs font-medium text-gray-700 mb-2">Existing Structure <span class="text-red-500">*</span></p>
                <div class="flex items-center space-x-4" id="existingStructureRadioGroup">
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="radio" name="existing_structure" value="1" class="text-amber-600 focus:ring-amber-500">
                        <span class="ml-2">Yes</span>
                    </label>
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="radio" name="existing_structure" value="0" class="text-amber-600 focus:ring-amber-500">
                        <span class="ml-2">No</span>
                    </label>
                </div>
            </div>

            <div id="existingStructureDimensionsWrapper" class="hidden">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700 mb-1">Existing Structure Dimensions (L x B x H)</label>
                        <input
                            type="text"
                            id="existingStructureDimensions"
                            name="existing_structure_dimensions"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500"
                            placeholder="e.g. 2x2x3.3"
                            autocomplete="off"
                        >
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700 mb-1">Existing Structure Total (L x B x H)</label>
                        <input
                            type="number"
                            id="existingStructureAmount"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                            placeholder="Enter total"
                            step="0.01"
                            min="0"
                        >
                        <input type="hidden" id="existingStructureAmountRaw" name="existing_structure_amount" value="">
                    </div>
                </div>
                {{-- <p class="mt-1 text-[11px] text-gray-500">Betterment bill uses: L x B x H x 7.5.</p> --}}
            </div>
        </div>

        <div id="conversionFieldsSection" class="hidden md:col-span-2 border border-purple-200 bg-purple-50 rounded-md p-4 space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-purple-800">Conversion Inspection Details</p>
                <span class="text-[11px] text-purple-600">Applies only to Conversion Applications</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Service <span class="text-red-500">*</span></label>
                    <select name="purpose" id="conversionPurpose" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" data-jsi-validate>
                        <option value="">Select purpose</option>
                        <option value="Conversion">Conversion</option>
                        <option value="Contravention">Contravention</option>
                        <option value="Extension">Extension</option>
                        <option value="Subdivision">Subdivision</option>
                        <option value="Merger">Merger</option>
                        <option value="Private Layout">Private Layout</option>
                    </select>
                </div>
                <div id="privateLayoutWrapper" class="hidden">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Private Layout Number <span class="text-red-500">*</span></label>
                    <input type="text" name="private_layout_number" id="conversionPrivateLayoutNumber" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" placeholder="Enter private layout number" data-jsi-validate>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Detailed Land Use <span class="text-red-500">*</span></label>
                    <select id="conversionDetailedLandUse" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" data-jsi-validate>
                        <option value="">Select detailed land use</option>
                        @foreach($jsiDetailedLandUses as $landUse)
                            @php
                                $detailLandUseParentId = data_get($landUse, 'land_use_id');
                            @endphp
                            <option
                                value="{{ $landUse->id }}"
                                data-land-use-id="{{ $detailLandUseParentId ?? '' }}"
                                data-land-use-name="{{ $detailLandUseParentId !== null ? ($landUseNameById[$detailLandUseParentId] ?? '') : '' }}"
                            >{{ $landUse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Purpose <span class="text-red-500">*</span></label>
                    <select id="conversionPurposeLut" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" data-jsi-validate>
                        <option value="">Select purpose</option>
                    </select>
                    <div id="conversionPurposeLutOtherWrapper" class="hidden mt-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Other Purpose (Specify) <span class="text-red-500">*</span></label>
                        <input type="text" id="conversionPurposeLutOther" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" placeholder="Specify purpose">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Road Category <span class="text-red-500">*</span></label>
                    <select id="conversionRoadCategory" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" data-jsi-validate>
                        <option value="">Select road category</option>
                        @foreach($jsiRoadCategories as $roadCategory)
                            <option value="{{ $roadCategory->name }}">{{ $roadCategory->name }}</option>
                        @endforeach
                    </select>
                    <div id="conversionRoadCategoryOtherWrapper" class="hidden mt-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Observed Road Category (Specify) <span class="text-red-500">*</span></label>
                        <input type="text" id="conversionRoadCategoryOther" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" placeholder="Specify observed road category">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="block text-xs font-medium text-gray-700 mb-2">Conformity <span class="text-red-500">*</span></p>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="conformity" value="1" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">Yes</span>
                        </label>
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="conformity" value="0" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">No</span>
                        </label>
                    </div>
                </div>
                <div>
                    <p class="block text-xs font-medium text-gray-700 mb-2">Accessibility <span class="text-red-500">*</span></p>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="accessibility" value="1" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">Yes</span>
                        </label>
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="accessibility" value="0" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">No</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="space-y-3 border border-purple-200 rounded-md p-3 bg-white">
                <p class="block text-xs font-medium text-gray-700">Reservation <span class="text-red-500">*</span></p>
                <div class="flex items-center space-x-4" id="reservationRequiredRadioGroup">
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="radio" name="reservation_required" value="1" class="text-purple-600 focus:ring-purple-500">
                        <span class="ml-2">Yes</span>
                    </label>
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="radio" name="reservation_required" value="0" class="text-purple-600 focus:ring-purple-500">
                        <span class="ml-2">No</span>
                    </label>
                </div>

                <div id="reservationDetailsWrapper" class="hidden grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Existing Reservation <span class="text-red-500">*</span></label>
                        <select id="conversionRightOfWay" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" data-jsi-validate>
                            <option value="">Select existing reservation</option>
                            @foreach($jsiReservations as $reservation)
                                <option value="{{ $reservation->name }}">{{ $reservation->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Recommended  Reservation <span class="text-red-500">*</span></label>
                        <select id="conversionRecommendedReservation" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" data-jsi-validate>
                            <option value="">Select recommended</option>
                            @foreach($jsiReservations as $reservation)
                                <option value="{{ $reservation->name }}">{{ $reservation->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <div id="recommendedCommentStatusWrapper">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Comment <span class="text-red-500">*</span></label>
                            <select id="recommendedCommentStatus" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" data-jsi-validate>
                                <option value="">Select comment</option>
                                <option value="Adequate">Adequate</option>
                                <option value="Not Adequate">Not Adequate</option>
                            </select>
                        </div>
                    </div>

                    <div id="howManyMetersWrapper" class="hidden">
                        <label class="block text-xs font-medium text-gray-700 mb-1">How many meters <span class="text-red-500">*</span></label>
                        <input type="text" id="howManyMeters" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" placeholder="Specify meters">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="block text-xs font-medium text-gray-700 mb-2">Adequate Size Requirement <span class="text-red-500">*</span></p>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="adequate_size_requirement" value="1" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">Yes</span>
                        </label>
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="adequate_size_requirement" value="0" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">No</span>
                        </label>
                    </div>
                </div>
                <div>
                    <p class="block text-xs font-medium text-gray-700 mb-2">Land Traversing Utility <span class="text-red-500">*</span></p>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="land_traversing_utility" value="1" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">Yes</span>
                        </label>
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="radio" name="land_traversing_utility" value="0" class="text-purple-600 focus:ring-purple-500">
                            <span class="ml-2">No</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-700">Existing Site Measurement</p>
                    <label class="block text-[11px] font-medium text-gray-700 mb-1">Dimensions (x-separated)</label>
                    <input type="text" id="existingMeasurementInput" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" placeholder="e.g. 20x30" autocomplete="off">
                    <input type="hidden" name="existing_site_length" id="existingSiteLength">
                    <input type="hidden" name="existing_site_width" id="existingSiteWidth">
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Total Area Covered (m²)</label>
                            <input type="number" name="existing_site_area" id="existingSiteArea" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" step="0.01" min="0" placeholder="Enter total area">
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-700">Recommended Site Measurement</p>
                    <label class="block text-[11px] font-medium text-gray-700 mb-1">Dimensions (x-separated)</label>
                    <input type="text" id="recommendedMeasurementInput" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" placeholder="e.g. 20x30" autocomplete="off">
                    <input type="hidden" name="recommended_site_length" id="recommendedSiteLength">
                    <input type="hidden" name="recommended_site_width" id="recommendedSiteWidth">
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Total Area Covered (m²)</label>
                            <input type="number" name="recommended_site_area" id="recommendedSiteArea" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500" step="0.01" min="0" placeholder="Enter total area">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="unitNumberWrapper" class="hidden">
            <label class="block text-xs font-medium text-gray-700 mb-1" for="jointInspectionUnitNumber">Unit Number <span class="text-red-500">*</span></label>
            <input type="text" name="unit_number" id="jointInspectionUnitNumber" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter unit number" data-jsi-validate>
            <p class="mt-1 text-[11px] text-gray-500">Provide the specific unit identifier for sectional applications.</p>
        </div>
        <div id="unitDimensionWrapper" class="hidden">
            <label class="block text-xs font-medium text-gray-700 mb-1" for="jointInspectionUnitDimension">Unit Dimension (M²)</label>
            <input type="text" name="unit_dimension" id="jointInspectionUnitDimension" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Enter unit dimension" data-jsi-validate>
            <p class="mt-1 text-[11px] text-gray-500">For unit inspections, this value populates the Table B dimension field.</p>
        </div>
        <div id="sectionsCountGroup">
            <label class="block text-xs font-medium text-gray-700 mb-1" id="sectionsCountLabel">No. of Sections <span class="text-red-500">*</span></label>
            <input type="text" name="sections_count" id="jointInspectionSections" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Sections count" data-jsi-validate>
            <p class="mt-1 text-[11px] text-gray-500">Enter the total number of sections inspected. Use 0 if not applicable.</p>
        </div>
        <div id="nonConversionReservationSection" class="space-y-3 border border-green-200 rounded-md p-3 bg-green-50">
            <p class="block text-xs font-medium text-gray-700">Reservation <span class="text-red-500">*</span></p>
            <div class="flex items-center space-x-4" id="nonConversionReservationRadioGroup">
                <label class="inline-flex items-center text-sm text-gray-700">
                    <input type="radio" name="non_conversion_reservation_required" value="1" class="text-green-600 focus:ring-green-500">
                    <span class="ml-2">Yes</span>
                </label>
                <label class="inline-flex items-center text-sm text-gray-700">
                    <input type="radio" name="non_conversion_reservation_required" value="0" class="text-green-600 focus:ring-green-500">
                    <span class="ml-2">No</span>
                </label>
            </div>

            <div id="nonConversionReservationDetailsWrapper" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Road Reservation <span class="text-red-500">*</span></label>
                    <select name="road_reservation" id="jointInspectionRoadReservation" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" data-jsi-validate>
                        <option value="">Select road reservation</option>
                        @foreach($jsiReservations as $reservation)
                            <option value="{{ $reservation->name }}">{{ $reservation->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="jointInspectionRoadReservationOtherWrapper" class="hidden">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Others (Specify) <span class="text-red-500">*</span></label>
                    <input type="text" id="jointInspectionRoadReservationOther" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Specify other road reservation">
                </div>
            </div>
        </div>
    </div>

    {{-- ===== STEP 3: Measurements & Utilities ===== --}}
    <div class="jsi-wizard-step space-y-5 hidden" data-wizard-step="3">
        <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
            <strong>Step 3 of 4 — Measurements & Utilities.</strong> Record shared utilities and site measurements.
        </div>

        <div id="sharedUtilitiesSection" class="hidden">
            <p class="text-xs font-medium text-gray-700 mb-2">Shared Utilities</p>
            <div id="sharedUtilitiesContainer" class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4">
                <!-- Shared utilities checkboxes will be populated here by JavaScript -->
            </div>
        </div>

        <div id="measurementEntriesSection" class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-medium text-gray-700">Shared Utilities &amp; Measurements <span class="text-red-500">*</span></label>
                <button type="button" id="addMeasurementEntry" class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 border border-green-200 rounded-md hover:bg-green-50">
                    <span class="mr-1">+</span>
                    Add Entry
                </button>
            </div>
            <div id="measurementEntriesContainer" class="space-y-2"></div>
            <p class="text-xs text-gray-500">Add measurement entries for utilities found on site. Click "Add Entry" to add rows for capturing utility measurements and details.</p>
        </div>

        <div id="measurementSummarySection">
            <label class="block text-xs font-medium text-gray-700 mb-1">Utilities Measurement Summary <span class="text-red-500">*</span></label>
            <textarea name="existing_site_measurement_summary" id="jointInspectionMeasurementSummary" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Provide a short summary for the measurements section" data-jsi-validate>The following existing site measurements were observed during the joint site inspection:</textarea>
            <p class="text-xs text-gray-500 mt-1">This note appears before the site measurement list in the generated report.</p>
        </div>

        <div id="needForEiaSection" class="space-y-2 border border-emerald-200 bg-emerald-50 rounded-md p-3">
            <p class="text-xs font-medium text-gray-700">Need for Environmental Impact Analysis Report (EIAR) <span class="text-red-500">*</span></p>
            <div class="flex items-center space-x-4" id="needForEiaRadioGroup">
                <label class="inline-flex items-center text-sm text-gray-700">
                    <input type="radio" name="need_for_eia" value="1" class="text-emerald-600 focus:ring-emerald-500">
                    <span class="ml-2">Yes</span>
                </label>
                <label class="inline-flex items-center text-sm text-gray-700">
                    <input type="radio" name="need_for_eia" value="0" class="text-emerald-600 focus:ring-emerald-500">
                    <span class="ml-2">No</span>
                </label>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <label class="inline-flex items-center text-sm text-gray-700">
                <input type="checkbox" name="available_on_ground" value="1" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                <span class="ml-2">Site is available on the ground <span class="text-red-500">*</span></span>
            </label>
        </div>
    </div>

    {{-- ===== STEP 4: Land Use, Compliance & Review ===== --}}
    <div class="jsi-wizard-step space-y-5 hidden" data-wizard-step="4">
        <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
            <strong>Step 4 of 4 — Land Use & Review.</strong> Finalize land use, compliance, and save.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Prevailing Land Use <span class="text-red-500">*</span></label>
                <select name="prevailing_land_use" id="jointInspectionPrevailingLandUse" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" data-jsi-validate>
                    <option value="">Select prevailing land use</option>
                    @if(!empty($jsiDetailedLandUses ?? null) && count($jsiDetailedLandUses) > 0)
                        @foreach($jsiDetailedLandUses as $type)
                            <option value="{{ $type->name }}" data-detailed-land-use-id="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    @elseif(!empty($landUseTypes ?? null) && count($landUseTypes) > 0)
                        @foreach($landUseTypes as $type)
                            <option value="{{ $type->name }}">{{ $type->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Applied Land Use <span class="text-red-500">*</span></label>
                <select name="applied_land_use" id="jointInspectionAppliedLandUse" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" data-jsi-validate>
                    <option value="">Select applied land use</option>
                    @if(!empty($landUseTypes ?? null) && count($landUseTypes) > 0)
                        @foreach($landUseTypes as $type)
                            <option value="{{ $type->name }}">{{ $type->name }}</option>
                        @endforeach
                    @else
                        <option value="Residential">Residential</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Industrial">Industrial</option>
                        <option value="Mixed Use">Mixed Use</option>
                    @endif
                </select>
            </div>
        </div>

        <div id="sharedAreasSection" class="hidden">
            <p class="text-xs font-medium text-gray-700 mb-2">Shared Areas (from Application)</p>
            <div id="sharedAreasDisplay" class="flex flex-wrap gap-2 mb-4">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Compliance Status <span class="text-red-500">*</span></label>
                <select name="compliance_status" id="jointInspectionCompliance" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" data-jsi-validate>
                    <option value="obtainable">Obtainable</option>
                    <option value="not_obtainable">Not Obtainable</option>
                </select>
            </div>
            <div class="space-y-2">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Inspection Officer <span class="text-red-500">*</span></label>
                    @if(!empty($inspectors ?? null) && count($inspectors) > 0)
                        <select name="inspection_officer" id="jointInspectionOfficerSelect" class="w-full border border-gray-300 rounded-md px-z3 py-2 text-sm focus:ring-2 focus:ring-green-500" data-jsi-validate>
                            <option value="">Select inspector</option>
                            @foreach($inspectors as $inspector)
                                <option value="{{ $inspector->id }}" data-label="{{ $inspector->name }}" data-rank="{{ $inspector->rank }}">{{ $inspector->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="inspection_officer" id="jointInspectionOfficer" value="" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Officer name and rank" data-jsi-validate>
                    @endif
                </div>
                @if(!empty($inspectors ?? null) && count($inspectors) > 0)
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Rank</label>
                        <input type="text" id="jointInspectionOfficerRank" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-gray-100 text-gray-700" placeholder="Rank will appear here" readonly>
                    </div>
                @endif
            </div>
        </div>

        <div>
            <p class="text-xs font-medium text-gray-700 mb-2">Additional Observation <span class="text-red-500">*</span></p>
            <div class="flex items-center space-x-4">
                <label class="inline-flex items-center text-sm text-gray-700">
                    <input type="radio" name="has_additional_observations" value="1" class="text-green-600 focus:ring-green-500">
                    <span class="ml-2">Yes</span>
                </label>
                <label class="inline-flex items-center text-sm text-gray-700">
                    <input type="radio" name="has_additional_observations" value="0" class="text-green-600 focus:ring-green-500" checked>
                    <span class="ml-2">No</span>
                </label>
            </div>
        </div>

        <div id="jointInspectionObservationsWrapper" class="hidden">
            <label class="block text-xs font-medium text-gray-700 mb-1">Additional Observation Details <span class="text-red-500">*</span></label>
            <textarea name="additional_observations" id="jointInspectionObservations" rows="4" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" placeholder="Document notable observations" data-jsi-validate></textarea>
            <div class="mt-2 flex justify-end">
                <button type="button" id="jointInspectionPrintObservationBtn" hidden class="inline-flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                    Print Additional Observation
                </button>
            </div>
        </div>
    </div>

    {{-- ===== Wizard Navigation Footer ===== --}}
    <div class="flex items-center justify-between border-t pt-4 mt-6">
        <div class="flex items-center space-x-2">
            <div id="jsiWorkflowStatus" class="text-xs text-gray-500">
                <span id="statusIndicator" class="inline-flex items-center">
                    <span class="w-2 h-2 bg-gray-400 rounded-full mr-1"></span>
                    <span id="statusText">Draft</span>
                </span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if($editorMode === 'modal')
                <button type="button" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50" data-joint-inspection-dismiss>Cancel</button>
            @else
                <a href="{{ $cancelUrl }}" class="px-4 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</a>
            @endif
            <button type="button" id="jsiWizardPrev" class="hidden px-4 py-2 text-sm font-medium border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <span class="inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Previous
                </span>
            </button>
            <button type="button" id="jsiWizardNext" class="px-4 py-2 text-sm font-semibold bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                <span class="inline-flex items-center gap-1">
                    Next
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </button>
            <button type="button" id="jointInspectionSave" class="hidden px-4 py-2 text-sm font-semibold bg-gray-400 text-white rounded-md cursor-not-allowed" disabled>Save</button>
        </div>
    </div>
</form>

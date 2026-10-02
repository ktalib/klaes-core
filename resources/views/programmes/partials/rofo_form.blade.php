@php
	$modalMode = $isModal ?? false;
	$batchMode = $batchMode ?? false;
	$batchMother = $batchMother ?? null;
	$batchUnits = $batchUnits ?? collect();
	if (!$batchUnits instanceof \Illuminate\Support\Collection) {
		$batchUnits = collect($batchUnits);
	}
	$batchStats = $batchStats ?? [
		'total_units' => 0,
		'memo_generated' => 0,
		'rofo_generated' => 0,
	];
	$batchPrimaryId = $batchPrimaryId ?? ($rofo->main_application_id ?? null);

	$shopHouseNo = old('shop_house_no', optional($existingRofo)->shop_house_no ?? ($rofo->property_house_no ?: ($rofo->unit_number ?? '')));
	$floorNo = old('floor_no', optional($existingRofo)->floor_no ?? $rofo->floor_number);

	$rawPropertyLocation = trim((string) ($rofo->property_location ?? ''));
	$locationParts = array_filter([
		$rofo->property_house_no ?? null,
		$rofo->property_plot_no ?? null,
		$rofo->property_street_name ?? null,
		$rofo->property_district ?? null,
	]);
	$locationFallback = $rawPropertyLocation !== '' ? $rawPropertyLocation : trim(implode(' ', $locationParts));
	$locationValue = old('location', optional($existingRofo)->location ?? $locationFallback);

	$applicationDate = old(
		'application_date',
		optional($existingRofo)->application_date
		? \Carbon\Carbon::parse(optional($existingRofo)->application_date)->format('Y-m-d')
		: (!empty($rofo->application_date)
			? \Carbon\Carbon::parse($rofo->application_date)->format('Y-m-d')
			: \Carbon\Carbon::now()->toDateString())
	);

	$approvalDate = old(
		'approval_date',
		optional($existingRofo)->approval_date
		? \Carbon\Carbon::parse(optional($existingRofo)->approval_date)->format('Y-m-d')
		: (!empty($rofo->approval_date)
			? \Carbon\Carbon::parse($rofo->approval_date)->format('Y-m-d')
			: \Carbon\Carbon::now()->toDateString())
	);

	$signedDate = old(
		'signed_date',
		optional($existingRofo)->signed_date
		? \Carbon\Carbon::parse(optional($existingRofo)->signed_date)->format('Y-m-d')
		: \Carbon\Carbon::now()->toDateString()
	);

	$plotNo = old('plot_no', optional($existingRofo)->plot_no ?? $rofo->property_plot_no);
	$blockNo = old('block_no', optional($existingRofo)->block_no ?? $rofo->block_number);
	$planNo = old('plan_no', optional($existingRofo)->plan_no ?? (optional($surveyRecord)->approved_plan_no ?? ''));

	$recertificationFee = old('recertification_fee', optional($existingRofo)->ground_rent ?? (optional($existingRofo)->recertification_fee ?? (optional($finalBill)->recertification_fee ?? '')));

	// In batch mode, we want to consolidate fees from all units to pre-populate the form checkboxes correctly.
	// This allows the form to remember both Commercial and Residential selections for mixed use, even if the primary unit only has one.
	if ($batchMode && !empty($batchUnits)) {
		$batchFees = $batchUnits->pluck('recertification_fee')
			->merge($batchUnits->pluck('ground_rent'))
			->filter()
			->unique()
			->implode(', ');

		if (!empty($batchFees)) {
			// If we found fees in the batch, combine them with the primary unit's fee (if any)
			$currentFee = $recertificationFee;
			$combined = collect(explode(',', $currentFee))
				->merge(explode(',', $batchFees))
				->map(function ($item) {
					return trim($item);
				})
				->filter()
				->unique()
				->implode(', ');

			$recertificationFee = $combined;
		}
	}

	$developmentCharges = old('development_charges', optional($existingRofo)->development_charges ?? (optional($finalBill)->dev_charges ?? ''));
	$surveyProcessingFees = old(
		'survey_processing_fees',
		optional($existingRofo)->survey_processing_fees ?? ((optional($finalBill)->survey_fee ?? 0) + (optional($finalBill)->processing_fee ?? 0))
	);

	$memoTermYears = optional($memoRecord)->term_years ?? ($rofo->memo_term_years ?? null);
	$defaultTermYears = $memoTermYears !== null && $memoTermYears !== '' ? (int) $memoTermYears : null;
	$termYears = old('term_years', optional($existingRofo)->term_years ?? ($defaultTermYears ?? ''));
	$purposeValue = old('purpose', optional($existingRofo)->purpose ?? ($rofo->land_use ?: ($rofo->mother_land_use ?? '')));
	$improvementValue = old('improvement_value', optional($existingRofo)->improvement_value ?? '');
	$improvementTimeLimit = old('improvement_time_limit', optional($existingRofo)->improvement_time_limit ?? 2);
	if (!$improvementTimeLimit) {
		$improvementTimeLimit = 2;
	}

	$commissionerName = old('commissioner_name', optional($existingRofo)->commissioner_name ?? 'DIRECTOR SECTIONAL TITLING');
	$unitCategory = strtoupper(trim((string) ($rofo->unit_category ?? '')));
	$isSuaUnit = $unitCategory === 'SUA' || (int) ($rofo->is_sua_unit ?? 0) === 1;
	$isEditMode = request()->query('url') === 'edit_pua_rofo';
	$initiallyDisabled = $isEditMode && !$modalMode;
	$plotHasValue = strlen(trim((string) ($plotNo ?? ''))) > 0;
	$planHasValue = strlen(trim((string) ($planNo ?? ''))) > 0;
	$recertificationHasValue = trim((string) ($recertificationFee ?? '')) !== '' && (float) $recertificationFee !== 0.0;
	$developmentHasValue = trim((string) ($developmentCharges ?? '')) !== '' && (float) $developmentCharges !== 0.0;
	$surveyFeesHasValue = trim((string) ($surveyProcessingFees ?? '')) !== '' && (float) $surveyProcessingFees !== 0.0;
	$plotReadOnly = !$modalMode && !($isSuaUnit && !$plotHasValue);
	$planReadOnly = !$modalMode && !($isSuaUnit && !$planHasValue);
	$groundRentReadOnly = !$modalMode && !($isSuaUnit && !$recertificationHasValue);
	$developmentReadOnly = !$modalMode && !($isSuaUnit && !$developmentHasValue);
	$surveyFeesReadOnly = !$modalMode && !($isSuaUnit && !$surveyFeesHasValue);
@endphp

<div data-rofo-root="{{ $modalMode ? 'modal' : 'page' }}"
	class="bg-white rounded-md shadow-md p-6 max-w-5xl mx-auto rofo-form-wrapper">
	<div class="flex flex-col gap-4 mb-6 md:flex-row md:items-center md:justify-between">
		<div class="flex-1">
			@if(($existingRofo && $existingRofo->rofo_no) ?? false)
				<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
					<div class="flex items-start space-x-3">
						<i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i>
						<p class="text-sm text-green-700">
							ST ROFO No: <strong>{{ $existingRofo->rofo_no }}</strong>
						</p>
					</div>
				</div>
			@endif

			@if($modalMode)
				<h2 class="text-2xl font-bold text-gray-800">Capture RoFO Terms &amp; Fees</h2>
				<p class="text-gray-600">Provide the terms and financial details for this unit. These entries will pre-fill
					the full RoFO form when you are ready to generate the document.</p>
			@elseif($isEditMode)
				<h2 class="text-2xl font-bold text-gray-800">View/Edit Right of Occupancy (RofO)</h2>
				<p class="text-gray-600">Review the RoFO details below. Click "Edit" to modify the document.</p>
			@elseif(request()->query('edit') === 'yes')
				<h2 class="text-2xl font-bold text-gray-800">Edit Right of Occupancy (RofO)</h2>
				<p class="text-gray-600">Modify the details below to update the Right of Occupancy document.</p>
			@else
				<h2 class="text-2xl font-bold text-gray-800">Generate Right of Occupancy (RofO)</h2>
				<p class="text-gray-600">Fill out the form below to generate a Right of Occupancy document.</p>
			@endif
		</div>

		@if(!$batchMode)
			<span
				class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-blue-700 border border-blue-200">{{ $isSuaUnit ? 'Standalone (SUA)' : 'Parented Unit (PUA)' }}</span>
		@endif
	</div>

	@if($initiallyDisabled)
		<div class="mb-4 hidden">
			<button type="button" id="enableEditBtn"
				class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
				<i data-lucide="edit-3" class="w-4 h-4 mr-2 inline"></i>
				Edit RoFO
			</button>
			<button type="button" id="cancelEditBtn"
				class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors ml-2 hidden">
				<i data-lucide="x" class="w-4 h-4 mr-2 inline"></i>
				Cancel Edit
			</button>
		</div>
	@endif

	@if($batchMode && $batchMother)
		<div class="mb-6 space-y-4">
			<div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
				<div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
					<div class="space-y-1">
						<h3 class="text-lg font-semibold text-indigo-900">Batch Scope</h3>
						<p class="text-sm text-indigo-800">Primary File No: <span
								class="font-medium">{{ $batchMother->fileno ?? 'N/A' }}</span></p>
						@if(!empty($batchMother->np_fileno))
							<p class="text-sm text-indigo-800">NP File No: <span
									class="font-medium">{{ $batchMother->np_fileno }}</span></p>
						@endif
						<p class="text-sm text-indigo-800">Owner: <span
								class="font-medium">{{ $batchMother->owner_name ?? 'N/A' }}</span></p>
						@if(!empty($batchMother->property_label))
							<p class="text-sm text-indigo-800">Location: <span
									class="font-medium">{{ $batchMother->property_label }}</span></p>
						@endif
						@if($batchPrimaryId)
							<p class="text-xs uppercase tracking-wide text-indigo-700">Primary ID: {{ $batchPrimaryId }}</p>
						@endif
					</div>
					<div class="flex flex-wrap gap-2 md:justify-end">
						<span
							class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700 shadow-sm">Units:
							{{ $batchStats['total_units'] ?? $batchUnits->count() }}</span>
						<span
							class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Memos:
							{{ $batchStats['memo_generated'] ?? 0 }} /
							{{ $batchStats['total_units'] ?? $batchUnits->count() }}</span>
						<span
							class="inline-flex items-center rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">RoFO:
							{{ $batchStats['rofo_generated'] ?? 0 }} /
							{{ $batchStats['total_units'] ?? $batchUnits->count() }}</span>
					</div>
				</div>
				<p class="mt-3 text-sm text-indigo-800">Submitting this form will generate or update RoFO records for every
					unit listed below.</p>
			</div>
			<div class="rounded-lg border border-gray-200 bg-white">
				<div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
					<h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Units Affected</h4>
					<span class="text-xs text-gray-500">{{ $batchUnits->count() }} total</span>
				</div>
				<div class="max-h-64 overflow-y-auto">
					<table class="min-w-full divide-y divide-gray-200 text-sm">
						<thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
							<tr>
								<th class="px-4 py-2 text-left">Unit ST FileNo</th>
								<th class="px-4 py-2 text-left">Unit Details</th>
								<th class="px-4 py-2 text-left">Owner</th>
								<th class="px-4 py-2 text-left">Memo Status</th>
								<th class="px-4 py-2 text-left">RoFO Status</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 bg-white">
							@foreach($batchUnits as $unit)
								<tr>
									<td class="px-4 py-2 text-gray-700">{{ $unit->fileno ?? 'N/A' }}</td>
									<td class="px-4 py-2 text-gray-600">
										<div class="font-medium text-gray-800">
											{{ $unit->unit_label !== '' ? $unit->unit_label : 'N/A' }}
										</div>
										@if(!empty($unit->scheme_no))
											<div class="text-xs text-gray-500">Scheme: {{ $unit->scheme_no }}</div>
										@endif
									</td>
									<td class="px-4 py-2 text-gray-700">{{ $unit->owner_name ?? 'N/A' }}</td>
									<td class="px-4 py-2">
										@if($unit->has_st_memo ?? false)
											<span
												class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Generated</span>
											@php
												$memoDate = null;
												if ($unit->memo_uploaded_at instanceof \Carbon\CarbonInterface) {
													$memoDate = $unit->memo_uploaded_at;
												} elseif (!empty($unit->memo_uploaded_at)) {
													$memoDate = \Carbon\Carbon::parse($unit->memo_uploaded_at);
												}
											@endphp
											@if($memoDate)
												<div class="mt-0.5 text-xs text-gray-500">{{ $memoDate->format('d-m-Y') }}</div>
											@endif
										@else
											<span
												class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Pending</span>
										@endif
									</td>
									<td class="px-4 py-2">
										@if(!empty($unit->rofo_no))
											<div class="font-semibold text-gray-800">{{ $unit->rofo_no }}</div>
											<div class="text-xs text-gray-500">Prints: {{ (int) ($unit->print_counter ?? 0) }}</div>
										@else
											<span
												class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Not
												Generated</span>
										@endif
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	@endif

	@if(session('error'))
		<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
			<p>{{ session('error') }}</p>
		</div>
	@endif

	<form data-rofo-form="1" data-rofo-mode="{{ $modalMode ? 'modal' : 'page' }}" id="rofoForm"
		action="{{ route('programmes.save_rofo') }}" method="POST" class="space-y-6">
		@csrf
		<input type="hidden" name="modal" value="{{ $modalMode ? 1 : 0 }}">
		<input type="hidden" name="sub_application_id" value="{{ $rofo->id }}">
		<input type="hidden" name="application_id" value="{{ $rofo->main_application_id }}">
		<input type="hidden" name="batch" value="{{ $batchMode ? 1 : 0 }}">

		<!-- Top Section: 2 Columns -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			<!-- Left Column -->
			<div class="space-y-6">
				<!-- Unit Owner Info -->
				@unless($batchMode)
					<div class="bg-gray-50 p-4 rounded-md {{ $modalMode ? 'hidden' : '' }}">
						<h3 class="font-semibold text-lg mb-4 text-green-700">Unit Owner Information</h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-gray-700">Unit Owner Name</label>
								<input type="text" name="owner_name"
									class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100"
									value="{{ $rofo->owner_name }}" readonly>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700">Address</label>
								<input type="text" name="address"
									class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100"
									value="{{ $rofo->address }}" readonly>
							</div>
						</div>
					</div>
				@endunless

				<!-- Property Information -->
				<div class="bg-gray-50 p-4 rounded-md {{ $modalMode ? 'hidden' : '' }}">
					<h3 class="font-semibold text-lg mb-4 text-green-700">Property Information</h3>
					<div class="space-y-4">
						@if($batchMode)
							<p class="text-xs text-gray-500">Primary property details shown for context. Individual unit
								identifiers (floor, block, unit numbers) will be applied automatically.</p>
						@endif
						<div>
							<label class="block text-sm font-medium text-gray-700">Shop/House No</label>
							<input type="text" name="shop_house_no"
								class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100"
								value="{{ $shopHouseNo }}" readonly>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700">Floor No</label>
							<input type="text" name="floor_no"
								class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100"
								value="{{ $floorNo }}" readonly>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700">Location</label>
							<input type="text" name="location"
								class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100"
								value="{{ $locationValue }}" readonly>
						</div>
					</div>
				</div>

				<!-- Plot Details -->
				<div class="bg-gray-50 p-4 rounded-md {{ $modalMode ? 'hidden' : '' }}">
					<h3 class="font-semibold text-lg mb-4 text-green-700">Plot Details</h3>
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="md:col-span-2">
							<label class="block text-sm font-medium text-gray-700">Plot No</label>
							<input type="text" name="plot_no"
								class="w-full p-2 border border-gray-300 rounded-md text-sm {{ ($plotReadOnly || $initiallyDisabled) ? 'bg-gray-100' : '' }} rofo-editable-field"
								value="{{ $plotNo }}" {{ ($plotReadOnly || $initiallyDisabled) ? 'readonly' : '' }}>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700">Block No</label>
							<input type="text" name="block_no"
								class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100"
								value="{{ $blockNo }}" readonly>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700">Plan No</label>
							<input type="text" name="plan_no"
								class="w-full p-2 border border-gray-300 rounded-md text-sm {{ ($planReadOnly || $initiallyDisabled) ? 'bg-gray-100' : '' }} rofo-editable-field"
								value="{{ $planNo }}" {{ ($planReadOnly || $initiallyDisabled) ? 'readonly' : '' }}>
						</div>
					</div>
				</div>
			</div>

			<!-- Right Column -->
			<div class="space-y-6">
				<!-- Dates -->
				<div class="bg-gray-50 p-4 rounded-md {{ $modalMode ? 'hidden' : '' }}">
					<h3 class="font-semibold text-lg mb-4 text-green-700">Dates</h3>
					<div class="space-y-4">
						<div>
							<label class="block text-sm font-medium text-gray-700">Application Date <span
									class="text-red-500">*</span></label>
							<input type="date" name="application_date"
								class="w-full p-2 border border-gray-300 rounded-md text-sm {{ $initiallyDisabled ? 'bg-gray-100' : '' }} rofo-editable-field"
								required value="{{ $applicationDate }}" {{ $initiallyDisabled ? 'readonly' : '' }}>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700">Approval Date <span
									class="text-red-500">*</span></label>
							<input type="date" name="approval_date"
								class="w-full p-2 border border-gray-300 rounded-md text-sm {{ $initiallyDisabled ? 'bg-gray-100' : '' }} rofo-editable-field"
								required value="{{ $approvalDate }}" {{ $initiallyDisabled ? 'readonly' : '' }}>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700">Signed Date <span
									class="text-red-500">*</span></label>
							<input type="date" name="signed_date"
								class="w-full p-2 border border-gray-300 rounded-md text-sm {{ $initiallyDisabled ? 'bg-gray-100' : '' }} rofo-editable-field"
								required value="{{ $signedDate }}" {{ $initiallyDisabled ? 'readonly' : '' }}>
						</div>
					</div>
				</div>

				<!-- Approver Information -->
				<div class="bg-gray-50 p-4 rounded-md {{ $modalMode ? 'hidden' : '' }}">
					<h3 class="font-semibold text-lg mb-4 text-green-700">Approver Information</h3>
					<div class="space-y-4">
						<div>
							<label class="block text-sm font-medium text-gray-700">Commissioner's Name <span
									class="text-red-500">*</span></label>
							<input type="text" name="commissioner_name"
								class="w-full p-2 border border-gray-300 rounded-md text-sm {{ $initiallyDisabled ? 'bg-gray-100' : '' }} rofo-editable-field"
								required value="{{ $commissionerName }}" {{ $initiallyDisabled ? 'readonly' : '' }}>
						</div>
						<div class="mt-4">
							@if(!$existingRofo)
								<div class="bg-blue-50 border-l-4 border-blue-500 p-3 mb-3">
									<p class="text-sm text-blue-700">
										<strong>Next ROFO Number:</strong>
										{{ $nextRofoNo ?? 'ST/ROFO/' . date('Y') . '/0001' }}
									</p>
									<p class="text-sm text-gray-600 italic mt-1">
										This number will be automatically assigned when you generate the ROFO.
									</p>
								</div>
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Bottom Section: Terms and Fees -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			<div class="bg-gray-50 p-4 rounded-md">
				<h3 class="font-semibold text-lg mb-4 text-green-700">Terms and Conditions</h3>
				<div class="space-y-4">
					<div>
						<label class="block text-sm font-medium text-gray-700">Term (Years) <span
								class="text-red-500">*</span></label>
						<input type="number" name="term_years"
							class="w-full p-2 border border-gray-300 rounded-md text-sm" required
							value="{{ $termYears }}">
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700">Purpose</label>
						<input type="text" name="purpose"
							class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly
							value="{{ $purposeValue }}">
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700">Value of Improvements <span
								class="text-red-500">*</span></label>
						<input type="text" name="improvement_value"
							class="w-full p-2 border border-gray-300 rounded-md text-sm" required
							value="{{ $improvementValue }}">
						<p class="text-xs text-red-500 mt-1">Note: This value will automatically update the Unit's
							record.</p>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700">Improvement Time Limit (Years) <span
								class="text-red-500">*</span></label>
						<input type="number" name="improvement_time_limit"
							class="w-full p-2 border border-gray-300 rounded-md text-sm {{ $modalMode ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : '' }}"
							{{ $modalMode ? 'readonly' : '' }} required value="{{ $improvementTimeLimit }}">
					</div>
				</div>
			</div>

			<div class="bg-gray-50 p-4 rounded-md">
				<h3 class="font-semibold text-lg mb-4 text-green-700">Fees</h3>
				<div class="space-y-4">
					<div>
						<label class="block text-sm font-medium text-gray-700">Ground Rate</label>

						<!-- Ground Rate Calculator -->
						<div class="mt-2 p-3 bg-gray-50 border border-gray-200 rounded-md">
							@php
								$unitArea = $rofo->unit_measurement ?? 0;
								$primaryLandUse = strtolower($rofo->mother_land_use ?? '');
								$isCommercial = \Str::contains($primaryLandUse, 'commercial') || \Str::contains($primaryLandUse, 'mixed') || \Str::contains($primaryLandUse, 'industrial');
								$isResidential = \Str::contains($primaryLandUse, 'residential') || \Str::contains($primaryLandUse, 'mixed');

								// Fallback: If neither detected (or empty), show both to be safe
								if (!$isCommercial && !$isResidential) {
									$isCommercial = true;
									$isResidential = true;
								}

								// For Mixed use (where both are true), we need distinct radio group names to allow selecting one of each.
								$isMixedMode = $isCommercial && $isResidential;
								$commRadioName = $isMixedMode ? 'rent_category_comm' : 'rent_category';
								$resRadioName = $isMixedMode ? 'rent_category_res' : 'rent_category';
							@endphp

							@if($isMixedMode)
								<p class="text-xs text-red-600 mb-2 italic">
									<strong>Note:</strong> You must select one rate for EACH category below. The system will
									automatically apply the correct rate (Commercial or Residential) to each unit based on
									its <strong>Specific Land Use</strong>.
								</p>
							@endif

							<input type="hidden" id="hidden_unit_area" value="{{ $unitArea }}">

							<!-- Area Display Hidden as requested -->
							<div class="mb-2 hidden">
								<label class="text-xs font-semibold text-gray-600 block">Unit Area (sqm):</label>
								@if($unitArea > 0)
									<span class="text-sm font-bold text-gray-800">{{ number_format($unitArea, 2) }}
										sqm</span>
								@else
									<input type="number" id="manual_unit_area"
										class="w-24 p-1 text-sm border border-gray-300 rounded" placeholder="Enter Area">
								@endif
							</div>

							<div class="grid grid-cols-1 gap-2 text-xs">
								@if($isCommercial)
									<div class="mb-2">
										<p class="font-bold text-gray-600 mb-1">Commercial</p>
										<label class="flex items-center space-x-2 cursor-pointer mb-1">
											<input type="radio" name="{{ $commRadioName }}" value="100" required
												class="rent-calc-radio" {{ \Str::contains($recertificationFee, '100.00K') ? 'checked' : '' }}>
											<span class="whitespace-nowrap">Metropolitan ₦100.00K /sqm</span>
										</label>
										<label class="flex items-center space-x-2 cursor-pointer mb-1">
											<input type="radio" name="{{ $commRadioName }}" value="50" required
												class="rent-calc-radio" {{ \Str::contains($recertificationFee, '50.00K') ? 'checked' : '' }}>
											<span class="whitespace-nowrap">Non-metropolitan ₦50.00K /sqm</span>
										</label>
										<label class="flex items-center space-x-2 cursor-pointer mb-1">
											<input type="radio" name="{{ $commRadioName }}" value="20" required
												class="rent-calc-radio" {{ \Str::contains($recertificationFee, '20.00K') ? 'checked' : '' }}>
											<span class="whitespace-nowrap">Outskirts ₦20.00K /sqm</span>
										</label>
									</div>
								@endif
								@if($isResidential)
									<div>
										<p class="font-bold text-gray-600 mb-1">Residential</p>
										<label class="flex items-center space-x-2 cursor-pointer mb-1">
											<input type="radio" name="{{ $resRadioName }}" value="12" required
												class="rent-calc-radio" {{ \Str::contains($recertificationFee, '12.00K') ? 'checked' : '' }}>
											<span class="whitespace-nowrap">Low Density ₦12.00K /sqm</span>
										</label>
										<label class="flex items-center space-x-2 cursor-pointer mb-1">
											<input type="radio" name="{{ $resRadioName }}" value="10" required
												class="rent-calc-radio" {{ \Str::contains($recertificationFee, '10.00K') ? 'checked' : '' }}>
											<span class="whitespace-nowrap">Medium Density ₦10.00K /sqm</span>
										</label>
										<label class="flex items-center space-x-2 cursor-pointer mb-1">
											<input type="radio" name="{{ $resRadioName }}" value="8" class="rent-calc-radio"
												required {{ \Str::contains($recertificationFee, '8.00K') ? 'checked' : '' }}>
											<span class="whitespace-nowrap">High Density ₦8.00K /sqm</span>
										</label>
									</div>
								@endif
							</div>
						</div>
						<br>
						<input type="text" name="recertification_fee" id="recertification_fee"
							class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly
							value="{{ $recertificationFee }}">

						<script>
							(function () {
								const radios = document.querySelectorAll('.rent-calc-radio');
								const feeInput = document.getElementById('recertification_fee');

								// Updates the input with the text of selected options
								function updateRentText() {
									let selectedTexts = [];

									radios.forEach(r => {
										if (r.checked) {
											// Find the sibling span text
											const label = r.closest('label');
											const span = label.querySelector('span');
											if (span) {
												const text = span.textContent.trim();
												// Extract part starting with ₦
												const match = text.match(/(₦.*)$/);
												if (match) {
													selectedTexts.push(match[1]);
												} else {
													selectedTexts.push(text);
												}
											}
										}
									});

									// Always set the value (clears it if no selection)
									if (feeInput) {
										feeInput.value = selectedTexts.join(', ');
									}
								}

								radios.forEach(radio => {
									radio.addEventListener('change', updateRentText);
								});

								// Run initially
								updateRentText();
							})();
						</script>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700">Development Charges (₦)</label>
						<input type="text" name="development_charges"
							class="w-full p-2 border border-gray-300 rounded-md text-sm {{ $developmentReadOnly ? 'bg-gray-100' : '' }}"
							{{ $developmentReadOnly ? 'readonly' : 'required' }}
							value="{{ $developmentCharges !== '' ? $developmentCharges : 'to follow' }}">
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700">Survey/Processing Fees (₦)</label>
						<input type="number" name="survey_processing_fees" step="0.01" min="0"
							class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly
							value="{{ $surveyProcessingFees > 0 ? $surveyProcessingFees : 0 }}">
						<p class="text-xs text-red-500 mt-1">Note: The actual fee will be automatically sourced from
							the unit's Survey Fee record upon saving.</p>
					</div>
				</div>
			</div>
		</div>

		@if($batchMode)
			<div class="text-sm text-gray-600">
				Captured terms and fees will be applied to all {{ $batchStats['total_units'] ?? $batchUnits->count() }}
				unit(s) listed above.
			</div>
		@endif

		<div class="flex justify-end space-x-4">
			@if($modalMode)
				<button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
					data-rofo-dismiss="1">
					Close
				</button>
			@else
				<a href="{{ route('programmes.rofo') }}"
					class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
					Cancel
				</a>
			@endif

			<button data-rofo-submit="1" type="submit"
				class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:bg-gray-400 disabled:cursor-not-allowed {{ $initiallyDisabled ? 'hidden' : '' }}"
				id="submitBtn">
				{{ $existingRofo ? 'Update' : 'Generate' }} ROFO
			</button>
		</div>

		<div data-rofo-validation class="mt-4 text-red-500 text-sm hidden">
			Please fill in all required fields before generating the ROFO.
		</div>
	</form>
</div>

<style>
	.rofo-form-wrapper .required-field::after {
		content: "*";
		color: red;
		margin-left: 4px;
	}

	.rofo-form-wrapper .invalid-field {
		border-color: red !important;
	}

	.rofo-form-wrapper .rofo-submit-loading {
		position: relative;
		padding-left: 2.25rem;
	}

	.rofo-form-wrapper .rofo-submit-loading::before {
		content: "";
		position: absolute;
		left: 0.75rem;
		top: 50%;
		width: 14px;
		height: 14px;
		margin-top: -7px;
		border-radius: 9999px;
		border: 2px solid rgba(255, 255, 255, 0.4);
		border-top-color: #ffffff;
		animation: rofo-spinner 0.8s linear infinite;
	}

	@keyframes rofo-spinner {
		to {
			transform: rotate(360deg);
		}
	}
</style>

<script>
	window.initializeRofoForm = window.initializeRofoForm || function (root) {
		const scope = root || document;
		const form = scope.querySelector('[data-rofo-form]');

		if (!form) {
			return;
		}

		if (form.dataset.rofoInit === '1') {
			return;
		}

		form.dataset.rofoInit = '1';

		// Handle edit mode functionality
		const enableEditBtn = scope.querySelector('#enableEditBtn');
		const cancelEditBtn = scope.querySelector('#cancelEditBtn');
		const submitBtn = scope.querySelector('#submitBtn');
		const editableFields = scope.querySelectorAll('.rofo-editable-field');

		let originalValues = {};

		if (enableEditBtn) {
			// Store original values
			editableFields.forEach((field, index) => {
				originalValues[index] = field.value;
			});

			enableEditBtn.addEventListener('click', function () {
				// Enable all editable fields
				editableFields.forEach(field => {
					if (!field.hasAttribute('data-always-readonly')) {
						field.removeAttribute('readonly');
						field.classList.remove('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
						field.classList.add('bg-white');
					}
				});

				// Show/hide buttons
				this.classList.add('hidden');
				if (cancelEditBtn) cancelEditBtn.classList.remove('hidden');
				if (submitBtn) submitBtn.classList.remove('hidden');
			});

			if (cancelEditBtn) {
				cancelEditBtn.addEventListener('click', function () {
					// Restore original values and disable fields
					editableFields.forEach((field, index) => {
						field.value = originalValues[index] || '';
						field.setAttribute('readonly', true);
						field.classList.add('bg-gray-100');
						field.classList.remove('bg-white');
					});

					// Show/hide buttons
					this.classList.add('hidden');
					if (enableEditBtn) enableEditBtn.classList.remove('hidden');
					if (submitBtn) submitBtn.classList.add('hidden');
				});
			}
		}

		const requiredInputs = Array.from(form.querySelectorAll('[required]'));
		const submitButton = form.querySelector('[data-rofo-submit]');
		const validationMessage = form.querySelector('[data-rofo-validation]');
		const appliedIndicators = new WeakSet();
		const formMode = (form.dataset.rofoMode || '').toLowerCase();
		const isModalForm = formMode === 'modal';
		let isSubmitting = false;

		const addIndicator = (input) => {
			if (appliedIndicators.has(input)) {
				return;
			}

			const label = input.previousElementSibling;
			if (label && label.tagName === 'LABEL') {
				label.classList.add('required-field');
				appliedIndicators.add(input);
			}
		};

		const validateForm = () => {
			let isValid = true;

			requiredInputs.forEach((input) => {
				input.classList.remove('invalid-field');
				const value = (input.value || '').trim();

				if (!value) {
					isValid = false;
					input.classList.add('invalid-field');
					return;
				}

				if (input.type === 'number' && value) {
					const numericValue = parseFloat(value);
					const minValue = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : null;
					if (Number.isNaN(numericValue) || (minValue !== null && !Number.isNaN(minValue) && numericValue < minValue)) {
						isValid = false;
						input.classList.add('invalid-field');
					}
				}
			});

			if (validationMessage) {
				validationMessage.classList.toggle('hidden', isValid);
			}

			if (submitButton) {
				submitButton.disabled = !isValid;
			}

			return isValid;
		};

		requiredInputs.forEach((input) => {
			addIndicator(input);

			input.addEventListener('input', () => {
				validateForm();
			});

			input.addEventListener('blur', () => {
				validateForm();
			});
		});

		validateForm();

		form.addEventListener('submit', (event) => {
			if (!validateForm()) {
				event.preventDefault();
				return;
			}

			if (isModalForm || !submitButton) {
				return;
			}

			if (isSubmitting) {
				event.preventDefault();
				return;
			}

			isSubmitting = true;
			submitButton.dataset.originalText = submitButton.dataset.originalText || submitButton.textContent || '';
			submitButton.disabled = true;
			submitButton.classList.add('rofo-submit-loading');
			submitButton.textContent = 'Processing...';
		}, { passive: false });
	};
</script>
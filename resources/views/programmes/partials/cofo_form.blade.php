@php
	use Carbon\Carbon;

	$modalMode = $isModal ?? false;
	$application = $application ?? null;
	$existingCofO = $existingCofO ?? null;

	$fileNo = old('file_no', optional($existingCofO)->file_no ?? optional($application)->fileno ?? '');
	$schemeNo = old('scheme_no', optional($existingCofO)->scheme_no ?? optional($application)->scheme_no ?? '');
	$plotNo = old('plot_no', optional($existingCofO)->plot_no ?? optional($application)->property_plot_no ?? '');
	$blockNo = old('block_no', optional($existingCofO)->block_no ?? optional($application)->block_number ?? '');
	$floorNo = old('floor_no', optional($existingCofO)->floor_no ?? optional($application)->floor_number ?? '');
	$flatNo = old('flat_no', optional($existingCofO)->flat_no ?? optional($application)->unit_number ?? '');
	$landUse = old('land_use', optional($existingCofO)->land_use ?? optional($application)->land_use ?? '');

	$holderName = old('holder_name', optional($existingCofO)->holder_name ?? optional($application)->owner_name ?? '');
	$holderAddress = old('holder_address', optional($existingCofO)->holder_address ?? optional($application)->address ?? '');
	$propertyLocation = old('property_location', optional($existingCofO)->property_location ?? optional($application)->property_location ?? '');

	$startDateSource = old('start_date', optional($existingCofO)->start_date ?? ($startDate ?? Carbon::now()->toDateString()));
	$startDateValue = $startDateSource ? Carbon::parse($startDateSource)->format('Y-m-d') : Carbon::now()->toDateString();

	$totalTermValue = old('total_term', optional($existingCofO)->total_term ?? ($totalYears ?? 40));

	$signedByValue = old('signed_by', optional($existingCofO)->signed_by ?? '');
	$signedTitleValue = old('signed_title', optional($existingCofO)->signed_title ?? 'Honorable Commissioner of Land and Physical Planning');

	$certificateNumber = $certificateNumber ?? null;
	$nextAvailableCertificateNumber = $nextAvailableCertificateNumber ?? null;
	$motherApplicationId = optional($application)->mother_id ?? optional($application)->main_application_id ?? null;
@endphp

<div data-cofo-root="{{ $modalMode ? 'modal' : 'page' }}" class="bg-white rounded-md shadow-md p-6 {{ $modalMode ? 'max-w-3xl mx-auto' : 'max-w-6xl mx-auto' }} cofo-form-wrapper">
	@if($certificateNumber)
		<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
			<div class="flex items-start space-x-3">
				<i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i>
				<p class="text-sm text-green-700">ST CofO No: <strong>{{ $certificateNumber }}</strong></p>
			</div>
		</div>
	@elseif($nextAvailableCertificateNumber && !$modalMode)
		<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
			<div class="flex items-start space-x-3">
				<i data-lucide="info" class="w-5 h-5 text-blue-500"></i>
				<p class="text-sm text-blue-700">Next Available CofO No: <strong>{{ $nextAvailableCertificateNumber }}</strong></p>
			</div>
		</div>
	@endif

	@if(session('error') && !$modalMode)
		<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
			<p>{{ session('error') }}</p>
		</div>
	@endif

	<form data-cofo-form="1" data-cofo-mode="{{ $modalMode ? 'modal' : 'page' }}" action="{{ route('programmes.save_cofo') }}" method="POST" class="space-y-6">
		@csrf
		<input type="hidden" name="modal" value="{{ $modalMode ? 1 : 0 }}">
		<input type="hidden" name="sub_application_id" value="{{ optional($application)->id }}">
		<input type="hidden" name="mother_application_id" value="{{ $motherApplicationId }}">
		<input type="hidden" name="file_no" value="{{ $fileNo }}">
		<input type="hidden" name="scheme_no" value="{{ $schemeNo }}">
		<input type="hidden" name="plot_no" value="{{ $plotNo }}">
		<input type="hidden" name="block_no" value="{{ $blockNo }}">
		<input type="hidden" name="floor_no" value="{{ $floorNo }}">
		<input type="hidden" name="flat_no" value="{{ $flatNo }}">
		<input type="hidden" name="land_use" value="{{ $landUse }}">
		<input type="hidden" name="holder_name" value="{{ $holderName }}">
		<input type="hidden" name="holder_address" value="{{ $holderAddress }}">
		<input type="hidden" name="property_location" value="{{ $propertyLocation }}">
		<input type="hidden" name="property_house_no" value="{{ optional($application)->property_house_no }}">
		<input type="hidden" name="property_street_name" value="{{ optional($application)->property_street_name }}">
		<input type="hidden" name="property_district" value="{{ optional($application)->property_district }}">
		<input type="hidden" name="property_lga" value="{{ optional($application)->property_lga }}">
		<input type="hidden" name="property_state" value="{{ optional($application)->property_state ?? 'Kano' }}">
		<input type="hidden" name="signed_by" value="{{ $signedByValue }}">
		<input type="hidden" name="signed_title" value="{{ $signedTitleValue }}">

		@if(!$modalMode)
			<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
				<div class="space-y-6">
					<div class="bg-gray-50 p-4 rounded-md border border-gray-200">
						<h3 class="font-semibold text-gray-700 mb-3">File Information</h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">File Number<span class="text-red-600">*</span></label>
								<input type="text" class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" value="{{ $fileNo }}" readonly>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Scheme Number</label>
								<input type="text" class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" value="{{ $schemeNo }}" readonly>
							</div>
						</div>
					</div>

					<div class="bg-gray-50 p-4 rounded-md border border-gray-200">
						<h3 class="font-semibold text-gray-700 mb-3">Property Details</h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Plot Number</label>
								<input type="text" class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" value="{{ $plotNo }}" readonly>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Block Number</label>
								<input type="text" class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" value="{{ $blockNo }}" readonly>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Floor Number</label>
								<input type="text" class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" value="{{ $floorNo }}" readonly>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Unit/Flat Number</label>
								<input type="text" class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" value="{{ $flatNo }}" readonly>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Land Use<span class="text-red-600">*</span></label>
								<input type="text" class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" value="{{ $landUse }}" readonly>
							</div>
						</div>
					</div>

					<div class="bg-gray-50 p-4 rounded-md border border-gray-200">
						<h3 class="font-semibold text-gray-700 mb-3">Certificate Term Details</h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Start Date<span class="text-red-600">*</span></label>
								<input type="date" name="start_date" class="border border-gray-300 rounded-md w-full px-3 py-2" required value="{{ $startDateValue }}">
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Total Term (Years)<span class="text-red-600">*</span></label>
								<input type="number" name="total_term" class="border border-gray-300 rounded-md w-full px-3 py-2" min="1" max="99" required value="{{ $totalTermValue }}">
							</div>
						</div>
					</div>
				</div>

				<div class="space-y-6">
					<div class="bg-gray-50 p-4 rounded-md border border-gray-200">
						<h3 class="font-semibold text-gray-700 mb-3">Title Holder Information</h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Holder Name<span class="text-red-600">*</span></label>
								<textarea class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" rows="1" readonly>{{ $holderName }}</textarea>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Holder Address<span class="text-red-600">*</span></label>
								<textarea class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" rows="3" readonly>{{ $holderAddress }}</textarea>
							</div>
						</div>
					</div>

					<div class="bg-gray-50 p-4 rounded-md border border-gray-200">
						<h3 class="font-semibold text-gray-700 mb-3">Property Location</h3>
						<textarea class="border border-gray-300 rounded-md w-full px-3 py-2 bg-gray-100 text-gray-600" rows="3" readonly>{{ $propertyLocation }}</textarea>
					</div>

					<div class="bg-gray-50 p-4 rounded-md border border-gray-200">
						<h3 class="font-semibold text-gray-700 mb-3">Signatory Information</h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Signed By</label>
								<input type="text" name="signed_by_display" class="border border-gray-300 rounded-md w-full px-3 py-2" value="{{ $signedByValue }}" readonly>
							</div>
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">Signatory Title<span class="text-red-600">*</span></label>
								<input type="text" name="signed_title_display" class="border border-gray-300 rounded-md w-full px-3 py-2" value="{{ $signedTitleValue }}" readonly>
							</div>
						</div>
					</div>
				</div>
			</div>
		@else
			<div class="bg-gray-50 p-4 rounded-md border border-gray-200 max-w-xl mx-auto">
				<h3 class="font-semibold text-gray-700 mb-3 text-center">Certificate Term Details</h3>
				<div class="space-y-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">Start Date<span class="text-red-600">*</span></label>
						<input type="date" name="start_date" class="border border-gray-300 rounded-md w-full px-3 py-2" required value="{{ $startDateValue }}">
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">Total Term (Years)<span class="text-red-600">*</span></label>
						<input type="number" name="total_term" class="border border-gray-300 rounded-md w-full px-3 py-2" min="1" max="99" required value="{{ $totalTermValue }}">
					</div>
				</div>
			</div>
		@endif

		<div class="flex {{ $modalMode ? 'justify-end mt-6 space-x-4' : 'justify-end space-x-3 pt-4 border-t border-gray-200' }}">
			@if($modalMode)
				<button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300" data-cofo-dismiss="1">Close</button>
				<button type="submit" data-cofo-submit="1" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:bg-gray-400">{{ $existingCofO ? 'Update CofO Details' : 'Save CofO Details' }}</button>
			@else
				<a href="{{ route('programmes.certificates') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
				<button type="button" id="cofoEditButton" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Edit</button>
				<button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">{{ $existingCofO ? 'Update CofO' : 'Generate Front Page' }}</button>
			@endif
		</div>

		<div data-cofo-validation class="mt-4 text-red-500 text-sm hidden">
			Please fill in all required fields.
		</div>
	</form>
</div>

<script>
	window.initializeCofoForm = window.initializeCofoForm || function (root) {
		const scope = root || document;
		const form = scope.querySelector('[data-cofo-form]');
		if (!form || form.dataset.cofoBound === '1') {
			return;
		}

		form.dataset.cofoBound = '1';

		const requiredInputs = Array.from(form.querySelectorAll('[required]'));
		const submitButton = form.querySelector('[data-cofo-submit]');
		const validationMessage = form.querySelector('[data-cofo-validation]');
		const appliedIndicators = new WeakSet();

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
					if (Number.isNaN(numericValue)) {
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
			input.addEventListener('input', validateForm);
			input.addEventListener('blur', validateForm);
		});

		validateForm();

		form.addEventListener('submit', (event) => {
			if (!validateForm()) {
				event.preventDefault();
			}
		}, { passive: false });
	};

	@if(!$modalMode)
		document.addEventListener('DOMContentLoaded', function () {
			const root = document.querySelector('[data-cofo-root="page"]');
			if (root) {
				window.initializeCofoForm(root);
			}

			const editButton = document.getElementById('cofoEditButton');
			if (!editButton) {
				return;
			}

			let editable = false;
			editButton.addEventListener('click', () => {
				editable = !editable;
				const toggleFields = [];
				['signed_by_display', 'signed_title_display'].forEach((name) => {
					const field = document.querySelector(`[name="${name}"]`);
					if (field) {
						toggleFields.push(field);
					}
				});

				toggleFields.forEach((field) => {
					field.readOnly = !editable;
					field.classList.toggle('bg-gray-100', !editable);
					field.classList.toggle('text-gray-600', !editable);
				});

				editButton.textContent = editable ? 'Lock' : 'Edit';
			});
		});
	@endif
</script>

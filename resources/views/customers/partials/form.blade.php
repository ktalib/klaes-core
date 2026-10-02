@php
    $customerTypes = ['Individual' => 'Individual', 'Corporate' => 'Corporate', 'Multiple' => 'Multiple Owners'];
    $statuses = ['Active', 'Pending', 'Suspended', 'Retired'];
    $selectedStatus = old('status', $customer->status ?? 'Active');
    $selectedType = old('customer_type', $customer->customer_type ?? 'Individual');
    $selectedEntity = old('entity_id', $customer->entity_id ?? '');
    $reasonValue = old('reason_retired', $customer->reason_retired ?? '');
    $hideReasonField = $selectedStatus !== 'Retired' && blank($reasonValue);
    $selectedEntityModel = $customer->entity ?? null;
    $inputClass = 'mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0';
    $selectClass = 'mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0';
    $textareaClass = 'mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 resize-y';
    $checkboxClass = 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-offset-0';
@endphp
<div class="space-y-10">
    <section>
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Profile Details</h2>
                <p class="text-sm text-gray-600">Define the customer type, lifecycle state, and display name.</p>
            </div>
        </div>
        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Customer Type <span class="text-red-500">*</span></label>
                <select name="customer_type" class="{{ $selectClass }}">
                    @foreach ($customerTypes as $type => $label)
                        <option value="{{ $type }}" @selected($selectedType === $type)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('customer_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                <select name="status" id="customer-status-select" class="{{ $selectClass }}">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Customer Name <span class="text-red-500">*</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $customer->customer_name ?? '') }}" class="{{ $inputClass }}" required>
                @error('customer_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2 {{ $hideReasonField ? 'hidden' : '' }}" id="reason-retired-group">
                <label class="block text-sm font-medium text-gray-700">Reason Retired</label>
                <input type="text" name="reason_retired" id="reason-retired-input" value="{{ $reasonValue }}" class="{{ $inputClass }}" placeholder="Provide context for retired status">
                <p class="mt-1 text-xs text-gray-500">Required when status is set to Retired.</p>
                @error('reason_retired')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <section>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Contact Channels</h2>
            <p class="text-sm text-gray-600">Ensure outreach details stay current for downstream notifications.</p>
        </div>
        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" class="{{ $inputClass }}">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" class="{{ $inputClass }}">
                @error('phone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <section>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Addresses & Notes</h2>
            <p class="text-sm text-gray-600">Capture on-file locations and any relevant operational notes.</p>
        </div>
        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Property Address</label>
                <textarea name="property_address" rows="3" class="{{ $textareaClass }}">{{ old('property_address', $customer->property_address ?? '') }}</textarea>
                @error('property_address')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Physical Address</label>
                <textarea name="physical_address" rows="3" class="{{ $textareaClass }}">{{ old('physical_address', $customer->physical_address ?? '') }}</textarea>
                @error('physical_address')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" rows="4" class="{{ $textareaClass }}">{{ old('notes', $customer->notes ?? '') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <section>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Entity Association</h2>
            <p class="text-sm text-gray-600">Link the customer to an existing entity or opt to generate a new record.</p>
        </div>
        <div class="mt-6 space-y-4">
            <div>
                @include('partials.entity-select', [
                    'entities' => $entities,
                    'selectedEntityId' => $selectedEntity,
                    'selectedEntityModel' => $selectedEntityModel,
                    'selectClass' => $selectClass,
                    'labelText' => 'Link to Entity',
                    'helpText' => 'Showing the top 20 entities. Use search to find others quickly.',
                    'placeholder' => '-- Select Existing Entity --'
                ])
                @error('entity_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="inline-flex items-start gap-3 text-sm text-gray-700">
                <input type="checkbox" name="create_new_entity" value="1" class="{{ $checkboxClass }}" @checked(old('create_new_entity'))>
                <span>Create new entity from customer details</span>
            </label>
        </div>
    </section>
</div>

<script>
    (function manageReasonRetiredField() {
        const statusSelect = document.getElementById('customer-status-select');
        const reasonGroup = document.getElementById('reason-retired-group');
        const reasonInput = document.getElementById('reason-retired-input');

        if (!statusSelect || !reasonGroup || !reasonInput) {
            return;
        }

        function syncReasonField() {
            const isRetired = statusSelect.value === 'Retired';
            if (isRetired) {
                reasonGroup.classList.remove('hidden');
                reasonInput.setAttribute('required', 'required');
            } else {
                reasonInput.removeAttribute('required');
                if (!reasonInput.value.trim()) {
                    reasonGroup.classList.add('hidden');
                }
            }
        }

        statusSelect.addEventListener('change', syncReasonField);
        syncReasonField();
    })();
</script>

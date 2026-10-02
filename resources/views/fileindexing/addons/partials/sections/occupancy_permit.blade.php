<div class="form-section" id="occupancy-permit-section">
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-2">
            <i data-lucide="stamp" class="h-5 w-5 text-amber-600"></i>
            <h3 class="form-section-title" style="margin-bottom: 0;">Occupancy Permit Details</h3>
        </div>
        <div class="flex items-center">
            <input type="checkbox" id="has-occupancy-permit-toggle"
                class="mr-2 h-4 w-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500"
                {{ (isset($record) && $record->has_occupancy_permit) ? 'checked' : '' }}>
            <label for="has-occupancy-permit-toggle" class="block text-sm font-medium text-gray-700">Has Occupancy Permit</label>
        </div>
    </div>

    <hr class="mb-6 border-t border-gray-200">

    <div id="occupancy-permit-details-container" class="{{ (isset($record) && $record->has_occupancy_permit) ? '' : 'hidden' }}">
        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="occupancy-permit-instrument-type" class="block text-sm font-medium text-gray-700 mb-2">Instrument Type</label>
                <select id="occupancy-permit-instrument-type"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 sm:text-sm"
                    disabled>
                    <option value="Occupancy Permit" selected>Occupancy Permit</option>
                </select>
                <input type="hidden" name="occupancy_permit_instrument_type" value="Occupancy Permit">
            </div>
            <div class="form-group">
                <label for="occupancy-permit-status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                @php $occupancyPermitStatusValue = isset($record) ? ($record->occupancy_permit_status ?? 'Normal') : 'Normal'; @endphp
                <select id="occupancy-permit-status" name="occupancy_permit_status"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                    <option value="Normal" {{ $occupancyPermitStatusValue === 'Normal' ? 'selected' : '' }}>Normal</option>
                    <option value="Normal Cancellation" {{ $occupancyPermitStatusValue === 'Normal Cancellation' ? 'selected' : '' }}>Normal Cancellation</option>
                    <option value="Total Cancellation" {{ $occupancyPermitStatusValue === 'Total Cancellation' ? 'selected' : '' }}>Total Cancellation</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="occupancy-permit-op-type" class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                @php $occupancyPermitOpTypeValue = isset($record) ? ($record->occupancy_permit_op_type ?? '') : ''; @endphp
                {{-- This section captures Occupancy Permits only, so the list is the permit
                     row of the catalogue: Resettlement, Direct Allocation, LGA. ("LGA
                     Allocation Letter" was briefly here; it is its own instrument type, not
                     a permit Type, and belongs on the transaction card instead.)
                     LGA turns the Grantor field into a 44-LGA picker and fixes the
                     registration particulars at 0/0/0 with no deeds date or time — see
                     applyOccupancyPermitLgaRules() in public/js/fileindexing/create-indexing-dialog.js. --}}
                <select id="occupancy-permit-op-type" name="occupancy_permit_op_type"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                    <option value="">Select OP Type</option>
                    <option value="Resettlement" {{ $occupancyPermitOpTypeValue === 'Resettlement' ? 'selected' : '' }}>Resettlement</option>
                    <option value="Direct Allocation" {{ $occupancyPermitOpTypeValue === 'Direct Allocation' ? 'selected' : '' }}>Direct Allocation</option>
                    <option value="LGA" {{ $occupancyPermitOpTypeValue === 'LGA' ? 'selected' : '' }}>LGA</option>
                </select>
                <p id="occupancy-permit-lga-note" class="mt-1 text-[11px] text-amber-700 hidden">
                    Issued by a Local Government: registration number is fixed at 0/0/0 and the
                    deeds date and time are left blank.
                </p>
            </div>
            <div class="form-group" id="occupancy-permit-op-category-group">
                <label for="occupancy-permit-op-category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                @php $occupancyPermitOpCategoryValue = isset($record) ? ($record->occupancy_permit_op_category ?? '') : ''; @endphp
                {{-- Which generation of permit the paper is. Asked for EVERY permit Type,
                     LGA included. Left blank it behaves as a New one does, which is what
                     every permit captured before this field is -- see
                     applyOccupancyPermitCategoryRules() in
                     public/js/fileindexing/create-indexing-dialog.js. --}}
                <select id="occupancy-permit-op-category" name="occupancy_permit_op_category"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                    <option value="">Select category</option>
                    <option value="Old" {{ $occupancyPermitOpCategoryValue === 'Old' ? 'selected' : '' }}>Old</option>
                    <option value="New" {{ $occupancyPermitOpCategoryValue === 'New' ? 'selected' : '' }}>New</option>
                </select>
                <p id="occupancy-permit-old-op-note" class="mt-1 text-[11px] text-amber-700 hidden">
                    Old: the registration particulars (Serial No., Page No. and Vol No.) are optional.
                </p>
            </div>
            <div class="form-group">
                <label for="occupancy-permit-op-serial-number" class="block text-sm font-medium text-gray-700 mb-2">OP Serial Number</label>
                <input type="text" id="occupancy-permit-op-serial-number" name="occupancy_permit_op_serial_number"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    placeholder="Enter OP serial number"
                    value="{{ isset($record) ? ($record->occupancy_permit_op_serial_number ?? '') : '' }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="occupancy-permit-date" class="block text-sm font-medium text-gray-700 mb-2">Transaction/Commencement Date</label>
                <input type="date" id="occupancy-permit-date" name="occupancy_permit_date"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    value="{{ isset($record) ? ($record->occupancy_permit_date ?? '') : '' }}">
            </div>
            <div class="form-group">
                <label for="occupancy-permit-file-number" class="block text-sm font-medium text-gray-700 mb-2">File Number</label>
                <input type="text" id="occupancy-permit-file-number" name="occupancy_permit_file_number"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm bg-gray-50"
                    placeholder="Auto-populated from file number"
                    value="{{ isset($record) ? ($record->occupancy_permit_file_number ?? $record->file_number ?? '') : '' }}"
                    readonly>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4">
            <div class="form-group">
                <label for="occupancy-permit-land-use" class="block text-sm font-medium text-gray-700 mb-2">Land Use</label>
                <select id="occupancy-permit-land-use" name="occupancy_permit_land_use"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                    <option value="">Select Land Use</option>
                    @if(isset($landUseTypes))
                        @foreach($landUseTypes as $key => $label)
                            <option value="{{ $key }}" {{ (isset($record) && ($record->occupancy_permit_land_use ?? '') == $key) ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label for="occupancy-permit-grantor" class="block text-sm font-medium text-gray-700 mb-2">Grantor</label>
                @php $occupancyPermitGrantorValue = isset($record) ? ($record->occupancy_permit_grantor ?? 'KANO STATE GOVERNMENT') : 'KANO STATE GOVERNMENT'; @endphp
                <input type="text" id="occupancy-permit-grantor" name="occupancy_permit_grantor"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm bg-gray-100"
                    value="{{ $occupancyPermitGrantorValue }}"
                    readonly>
                {{-- Replaces the read-only input above on either LGA OP Type. It carries no name
                     attribute: the JS mirrors the chosen authority into the input, which stays the
                     single field the form posts as occupancy_permit_grantor. --}}
                <select id="occupancy-permit-lga-grantor"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm hidden"
                    aria-label="Local Government that issued the Occupancy Permit">
                    {{-- fullName() returns null for anything that is not one of the 44 (e.g. the
                         default "KANO STATE GOVERNMENT"), so the picker opens unselected rather
                         than offering the State as a Local Government. --}}
                    @include('partials.kano_lga_options', ['selected' => app(\App\Services\KanoLgaDirectory::class)->fullName($occupancyPermitGrantorValue)])
                </select>
            </div>
            <div class="form-group">
                <label for="occupancy-permit-grantee" class="block text-sm font-medium text-gray-700 mb-2">Grantee</label>
                <input type="text" id="occupancy-permit-grantee" name="occupancy_permit_grantee"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    placeholder="Enter grantee name"
                    value="{{ isset($record) ? ($record->occupancy_permit_grantee ?? '') : '' }}"
                    style="text-transform: uppercase;">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4">
            <div class="form-group">
                <label for="occupancy-permit-serial-no" class="block text-sm font-medium text-gray-700 mb-2">Serial No</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" id="occupancy-permit-serial-no" name="occupancy_permit_serial_no"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    value="{{ isset($record) ? ($record->occupancy_permit_serial_no ?? '') : '' }}">
            </div>
            <div class="form-group">
                <label for="occupancy-permit-page-no" class="block text-sm font-medium text-gray-700 mb-2">Page No</label>
                {{-- Page No always mirrors Serial No; kept read-only and populated via JS. --}}
                <input type="text" inputmode="numeric" pattern="[0-9]*" id="occupancy-permit-page-no" name="occupancy_permit_page_no"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm bg-gray-50"
                    value="{{ isset($record) ? ($record->occupancy_permit_page_no ?? $record->occupancy_permit_serial_no ?? '') : '' }}"
                    readonly style="cursor: default;">
            </div>
            <div class="form-group">
                <label for="occupancy-permit-vol-no" class="block text-sm font-medium text-gray-700 mb-2">Vol No</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" id="occupancy-permit-vol-no" name="occupancy_permit_vol_no"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    value="{{ isset($record) ? ($record->occupancy_permit_vol_no ?? '') : '' }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4">
            <div class="form-group">
                <label for="occupancy-permit-deeds-time" class="block text-sm font-medium text-gray-700 mb-2">Deeds Time</label>
                <input type="time" id="occupancy-permit-deeds-time" name="occupancy_permit_deeds_time"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    value="{{ isset($record) ? ($record->occupancy_permit_deeds_time ?? '') : '' }}">
            </div>
            <div class="form-group">
                <label for="occupancy-permit-deeds-date" class="block text-sm font-medium text-gray-700 mb-2">Deeds Date</label>
                <input type="date" id="occupancy-permit-deeds-date" name="occupancy_permit_deeds_date"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                    value="{{ isset($record) ? ($record->occupancy_permit_deeds_date ?? '') : '' }}">
            </div>
        </div>
    </div>
</div>

<div class="form-section" id="rofo-section">
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-2">
            <i data-lucide="file-text" class="h-5 w-5 text-green-600"></i>
            <h3 class="form-section-title" style="margin-bottom: 0;">Right of Occupancy (RoFO) Details</h3>
        </div>
        <div class="flex items-center">
            <input type="checkbox" id="has-rofo-toggle"
                class="mr-2 h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                {{ (isset($record) && $record->has_rofo) ? 'checked' : '' }}>
            <label for="has-rofo-toggle" class="block text-sm font-medium text-gray-700">Has RoFO</label>
        </div>
    </div>

    <hr class="mb-6 border-t border-gray-200">

    <div id="rofo-details-container" class="{{ (isset($record) && $record->has_rofo) ? '' : 'hidden' }}">
        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="rofo-instrument-type" class="block text-sm font-medium text-gray-700 mb-2">Instrument Type</label>
                <select id="rofo-instrument-type"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 sm:text-sm"
                    disabled>
                    <option value="Right of Occupancy" selected>Right of Occupancy</option>
                </select>
                <input type="hidden" name="rofo_instrument_type" value="Right of Occupancy">
            </div>
            <div class="form-group">
                <label for="rofo-status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                @php $rofoStatusValue = isset($record) ? ($record->rofo_status ?? 'Normal') : 'Normal'; @endphp
                <select id="rofo-status" name="rofo_status"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                    <option value="Normal" {{ $rofoStatusValue === 'Normal' ? 'selected' : '' }}>Normal</option>
                    <option value="Normal Cancellation" {{ $rofoStatusValue === 'Normal Cancellation' ? 'selected' : '' }}>Normal Cancellation</option>
                    <option value="Total Cancellation" {{ $rofoStatusValue === 'Total Cancellation' ? 'selected' : '' }}>Total Cancellation</option>
                  
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="form-group">
                <label for="rofo-date" class="block text-sm font-medium text-gray-700 mb-2">RoFO Date</label>
                <input type="date" id="rofo-date" name="rofo_date"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm"
                    value="{{ isset($record) ? ($record->rofo_date ?? '') : '' }}">
            </div>
            <div class="form-group">
                <label for="rofo-number" class="block text-sm font-medium text-gray-700 mb-2">RoFO Number</label>
                <input type="text" id="rofo-number" name="rofo_number"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm bg-gray-50"
                    placeholder="Auto-populated from file number"
                    value="{{ isset($record) ? ($record->rofo_number ?? $record->file_number ?? '') : '' }}"
                    readonly>
            </div>
            <div class="form-group">
                <label for="rofo-file-number" class="block text-sm font-medium text-gray-700 mb-2">File Number</label>
                <input type="text" id="rofo-file-number" name="rofo_file_number"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm bg-gray-50"
                    placeholder="Auto-populated from file number"
                    value="{{ isset($record) ? ($record->rofo_file_number ?? $record->file_number ?? '') : '' }}"
                    readonly>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="rofo-land-use" class="block text-sm font-medium text-gray-700 mb-2">Land Use</label>
                <select id="rofo-land-use" name="rofo_land_use"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                    <option value="">Select Land Use</option>
                    @if(isset($landUseTypes))
                        @foreach($landUseTypes as $key => $label)
                            <option value="{{ $key }}" {{ (isset($record) && ($record->rofo_land_use ?? '') == $key) ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4">
            <div class="form-group">
                <label for="rofo-grantor" class="block text-sm font-medium text-gray-700 mb-2">Grantor</label>
                <input type="text" id="rofo-grantor" name="rofo_grantor"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm bg-gray-100"
                    value="{{ isset($record) ? ($record->rofo_grantor ?? 'KANO STATE GOVERNMENT') : 'KANO STATE GOVERNMENT' }}"
                    readonly>
            </div>
            <div class="form-group">
                <label for="rofo-grantee" class="block text-sm font-medium text-gray-700 mb-2">Grantee</label>
                <input type="text" id="rofo-grantee" name="rofo_grantee"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm"
                    placeholder="Enter grantee name"
                    value="{{ isset($record) ? ($record->rofo_grantee ?? '') : '' }}"
                    style="text-transform: uppercase;">
            </div>
        </div>
    </div>
</div>

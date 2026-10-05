<div class="border-t pt-2 mt-2">
    <label
        class="flex items-center gap-2 text-xs text-indigo-700 bg-indigo-50 p-2 rounded border border-indigo-200 cursor-pointer w-fit max-w-full">
        <input type="checkbox" id="includeSolicitorSidebar" class="form-checkbox h-3.5 w-3.5 text-indigo-600 rounded" checked>
        <span class="font-medium">Include Solicitor?</span>
    </label>
</div>

<!-- Hidden Container for Solicitor Details -->
<div id="solicitor-sidebar-container"
    class="grid grid-cols-1 gap-3 bg-gray-50/50 p-3 rounded-lg border border-gray-100 mt-2">
    <h4 class="font-medium text-gray-800 border-b pb-1 mb-1 text-[11px] uppercase tracking-wider">Solicitor Details</h4>

    <x-instrument-input id="solicitorName" label="Name" icon="briefcase" placeholder="Law firm or lawyer name"
        value="{{ $record->solicitor_name ?? '' }}" />

    <x-instrument-input id="solicitorPhone" label="Phone No." icon="phone" placeholder="e.g. 08012345678"
        :phone="true" value="{{ $record->solicitor_phone ?? '' }}" />

    <div class="grid grid-cols-1 gap-3">
        <div>
            <label for="solicitorDistrict" class="block text-sm font-medium text-gray-700 mb-1">District</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="map-pin" class="h-4 w-4 text-gray-400"></i>
                </div>
                <select id="solicitorDistrict" name="solicitorDistrict" @if($deferDistrictOptions ?? false) data-deferred-district="Other" @endif
                    class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm appearance-none"
                    onchange="handleSolicitorDistrictChange(this)">
                    <option value="">Select District</option>
                    @foreach(($deferDistrictOptions ?? false) ? $districts->filter(fn ($district) => (is_object($district) ? $district->name : $district) === ($record->solicitor_district ?? '')) : $districts as $district)
                        @php $dName = is_object($district) ? $district->name : $district; @endphp
                        <option value="{{ $dName }}" {{ ($record->solicitor_district ?? '') === $dName ? 'selected' : '' }}>
                            {{ $dName }}
                        </option>
                    @endforeach
                    <option value="Other" {{ ($record->solicitor_district ?? '') === 'Other' ? 'selected' : '' }}>Other</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
                </div>
            </div>
            <div id="solicitorDistrictOtherWrapper" class="mt-2 hidden">
                <input type="text" id="solicitorDistrictOther" name="solicitorDistrictOther"
                    placeholder="Specify district..."
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all"
                    oninput="document.getElementById('solicitorDistrictCustom').value = this.value">
                <input type="hidden" id="solicitorDistrictCustom" name="solicitorDistrictCustom">
            </div>
        </div>
        <x-instrument-select id="solicitorState" label="State" icon="map-pin" :options="$states"
            placeholder="Select State" value="{{ $record->solicitor_state ?? 'Kano' }}" />

        <x-instrument-select id="solicitorLga" label="LGA" icon="map" :options="[]" placeholder="Select LGA"
            value="{{ $record->solicitor_lga ?? $record->solicitor_city ?? '' }}" />

        <x-instrument-textarea id="solicitorAddress" label="Office Address" icon="map-pin"
            placeholder="Auto-generated from selected district, LGA and state" rows="2"
            value="{{ $record->solicitor_address ?? '' }}" readonly />
    </div>
</div>

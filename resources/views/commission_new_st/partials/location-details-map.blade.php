{{--
    Reusable Location Details + Google Map block.

    @param string $prefix  Element-id prefix, e.g. 'pua' / 'sua'. Ids become
                           {prefix}PropertyHouseNo, {prefix}PropertyMapCanvas, ...
    @param string $mode    'backfill' — fields start read-only and are filled from the
                           parent/primary file; an Edit button unlocks them.
                           'manual'   — fields start editable (no parent to inherit from).
    @param string $hint    Sub-heading text under "Location Details".
--}}
@php
    $mode = $mode ?? 'backfill';
    $isManual = $mode === 'manual';
    $hint = $hint ?? ($isManual
        ? 'Enter the property location manually, then pin it on the map'
        : 'Backfilled automatically from the selected file number (read-only)');
    $lockClasses = $isManual ? '' : ' bg-gray-100 text-gray-500 cursor-not-allowed';

    // States/LGAs are rendered server-side so the dropdowns are usable on first
    // paint. js/primaryform/states-lga.js only fetches this same JSON after the
    // page loads, which left the selects empty whenever that fetch was slow or
    // the tab was prefilled (edit mode) before it resolved.
    $stStatesList = json_decode(@file_get_contents(public_path('js/primaryform/nigerian_states.json')) ?: '[]', true) ?: [];
    $stDefaultState = 'Kano';
    $stDefaultLgas = collect($stStatesList)->firstWhere('name', $stDefaultState)['local_governments'] ?? [];
@endphp

<div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-6" id="{{ $prefix }}LocationDetailsCard">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center">
            <div class="bg-teal-500 p-2 rounded-lg mr-3">
                <i data-lucide="map-pin" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Location Details</h3>
                <p id="{{ $prefix }}LocationDetailsHint" class="text-sm text-gray-600">{{ $hint }}</p>
            </div>
        </div>
        @unless($isManual)
            <button type="button" id="{{ $prefix }}LocationDetailsEditBtn"
                    onclick="STLocationMaps['{{ $prefix }}'].toggleEdit()"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-teal-700 bg-teal-50 border border-teal-200 rounded-md hover:bg-teal-100">
                <i data-lucide="pencil" class="w-4 h-4 mr-1"></i>
                Edit
            </button>
        @endunless
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-sm mb-1">House No.</label>
            <input type="text" id="{{ $prefix }}PropertyHouseNo" name="{{ $prefix }}_property_house_no"
                   class="{{ $prefix }}-location-field w-full p-2 border border-gray-300 rounded-md{{ $lockClasses }}"
                   placeholder="ENTER HOUSE NUMBER" style="text-transform:uppercase"
                   oninput="this.value = this.value.toUpperCase(); STLocationMaps['{{ $prefix }}'].updateAddress();"
                   @unless($isManual) disabled @endunless>
        </div>
        <div>
            <label class="block text-sm mb-1">Plot No.</label>
            <input type="text" id="{{ $prefix }}PropertyPlotNo" name="{{ $prefix }}_property_plot_no"
                   class="{{ $prefix }}-location-field w-full p-2 border border-gray-300 rounded-md{{ $lockClasses }}"
                   placeholder="ENTER PLOT NUMBER" style="text-transform:uppercase"
                   oninput="this.value = this.value.toUpperCase(); STLocationMaps['{{ $prefix }}'].updateAddress();"
                   @unless($isManual) disabled @endunless>
        </div>
        <div class="reference-select-wrap">
            <label class="block text-sm mb-1">Street Name</label>
            <select id="{{ $prefix }}PropertyStreetName" name="{{ $prefix }}_property_street_name"
                    data-reference-source="streets"
                    class="{{ $prefix }}-location-field w-full p-2 border border-gray-300 rounded-md{{ $lockClasses }}"
                    onchange="STLocationMaps['{{ $prefix }}'].updateAddress();"
                    @unless($isManual) disabled @endunless>
                <option value="">Search street name...</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="reference-select-wrap">
            <label class="block text-sm mb-1">District</label>
            <select id="{{ $prefix }}PropertyDistrict" name="{{ $prefix }}_property_district"
                    data-reference-source="districts"
                    class="{{ $prefix }}-location-field w-full p-2 border border-gray-300 rounded-md{{ $lockClasses }}"
                    onchange="STLocationMaps['{{ $prefix }}'].updateAddress();"
                    @unless($isManual) disabled @endunless>
                <option value="">Search district...</option>
            </select>
        </div>
        <div>
            <label class="block text-sm mb-1">State</label>
            <select id="{{ $prefix }}PropertyState" name="{{ $prefix }}_property_state"
                    class="{{ $prefix }}-location-field w-full p-2 border border-gray-300 rounded-md{{ $lockClasses }}"
                    onchange="STLocationMaps['{{ $prefix }}'].onStateChange();"
                    @unless($isManual) disabled @endunless>
                <option value="">Select State</option>
                @foreach($stStatesList as $stState)
                    <option value="{{ $stState['name'] }}" @selected($stState['name'] === $stDefaultState)>{{ $stState['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm mb-1">LGA</label>
            <select id="{{ $prefix }}PropertyLga" name="{{ $prefix }}_property_lga"
                    class="{{ $prefix }}-location-field w-full p-2 border border-gray-300 rounded-md{{ $lockClasses }}"
                    onchange="STLocationMaps['{{ $prefix }}'].updateAddress();"
                    @unless($isManual) disabled @endunless>
                <option value="">Select LGA</option>
                @foreach($stDefaultLgas as $stLga)
                    <option value="{{ $stLga }}">{{ $stLga }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm mb-1">Property Address:</label>
        <div class="p-2 bg-gray-100 border border-gray-300 rounded-md min-h-[40px]">
            <span id="{{ $prefix }}FullPropertyAddress" style="display: block; padding: 4px; text-transform: uppercase;"></span>
        </div>
        <input type="hidden" name="{{ $prefix }}_property_address" id="{{ $prefix }}PropertyAddressDisplay">
    </div>

    {{-- Property Location Map --}}
    <div class="mt-6 pt-6 border-t border-gray-200">
        <div class="flex items-center justify-between gap-4 mb-3">
            <div class="flex items-center gap-2 min-w-0">
                <i data-lucide="map" class="h-4 w-4 text-blue-600 flex-shrink-0"></i>
                <h4 class="text-sm font-bold uppercase tracking-wide text-gray-700 whitespace-nowrap">
                    Property Location Map
                </h4>
                <span id="{{ $prefix }}PropertyMapCoordSource" class="text-xs font-medium text-gray-400 whitespace-nowrap ml-1"></span>
            </div>
            <div class="flex items-center gap-2">
                <span id="{{ $prefix }}PropertyMapDragHint" class="hidden items-center gap-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-100 rounded-full px-3 py-1">
                    <i data-lucide="move" class="h-3.5 w-3.5"></i>
                    Drag the pin or click the map to fine-tune
                </span>
                <button type="button" onclick="STLocationMaps['{{ $prefix }}'].geocode()"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                    <i data-lucide="map-pin" class="w-4 h-4 mr-1"></i>
                    Pin on Map
                </button>
            </div>
        </div>

        {{-- Map shown once coordinates are known --}}
        <div id="{{ $prefix }}PropertyMapWrapper" class="hidden rounded-md overflow-hidden border border-gray-200 shadow-sm">
            <div id="{{ $prefix }}PropertyMapCanvas" style="height: 380px; width: 100%;"></div>
            <div class="text-sm text-gray-600 px-3 py-2 bg-gray-50 flex flex-wrap gap-x-6 gap-y-1">
                <span>Latitude: <strong id="{{ $prefix }}PropertyLatDisplay">—</strong></span>
                <span>Longitude: <strong id="{{ $prefix }}PropertyLngDisplay">—</strong></span>
            </div>
        </div>

        {{-- Empty state before a pin exists --}}
        <div id="{{ $prefix }}PropertyMapEmpty"
             class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 text-gray-400"
             style="height: 380px;">
            <i data-lucide="map-pin" class="h-8 w-8"></i>
            <p class="text-sm">No location pinned yet.</p>
            <p class="text-xs">
                @if($isManual)
                    Fill in the Location Details above, then click <strong>Pin on Map</strong>.
                @else
                    Select a file number to backfill its coordinates, or click <strong>Pin on Map</strong>.
                @endif
            </p>
        </div>

        <input type="hidden" name="{{ $prefix }}_latitude" id="{{ $prefix }}PropertyLatitude">
        <input type="hidden" name="{{ $prefix }}_longitude" id="{{ $prefix }}PropertyLongitude">
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    initLocationDetailsMap({ prefix: '{{ $prefix }}', manual: {{ $isManual ? 'true' : 'false' }} });
});
</script>

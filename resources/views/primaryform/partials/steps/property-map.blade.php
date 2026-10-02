{{--
    Property Location Map for the Primary Application form.

    The address inputs already exist in step1-basic (schemeNumber, propertyHouseNo,
    propertyPlotNo, propertyStreetName, propertyDistrict, propertyLga, propertyState),
    so this block renders the map only and binds to those ids via `fieldIds`.
    Coordinates are backfilled from the selected primary file number's indexing
    record; otherwise the user pins the address with "Pin on Map".
--}}
<div class="mt-6 pt-6 border-t border-gray-200">
    <div class="flex items-center justify-between gap-4 mb-3">
        <div class="flex items-center gap-2 min-w-0">
            <i data-lucide="map" class="h-4 w-4 text-blue-600 flex-shrink-0"></i>
            <h4 class="text-sm font-bold uppercase tracking-wide text-gray-700 whitespace-nowrap">
                Property Location Map
            </h4>
            <span id="pfPropertyMapCoordSource" class="text-xs font-medium text-gray-400 whitespace-nowrap ml-1"></span>
        </div>
        <div class="flex items-center gap-2">
            <span id="pfPropertyMapDragHint" class="hidden items-center gap-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-100 rounded-full px-3 py-1">
                <i data-lucide="move" class="h-3.5 w-3.5"></i>
                Drag the pin or click the map to fine-tune
            </span>
            <button type="button" onclick="STLocationMaps['pf'].geocode()"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                <i data-lucide="map-pin" class="w-4 h-4 mr-1"></i>
                Pin on Map
            </button>
        </div>
    </div>

    {{-- Map shown once coordinates are known --}}
    <div id="pfPropertyMapWrapper" class="hidden rounded-md overflow-hidden border border-gray-200 shadow-sm">
        <div id="pfPropertyMapCanvas" style="height: 380px; width: 100%;"></div>
        <div class="text-sm text-gray-600 px-3 py-2 bg-white flex flex-wrap gap-x-6 gap-y-1">
            <span>Latitude: <strong id="pfPropertyLatDisplay">—</strong></span>
            <span>Longitude: <strong id="pfPropertyLngDisplay">—</strong></span>
        </div>
    </div>

    {{-- Empty state before a pin exists --}}
    <div id="pfPropertyMapEmpty"
         class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 bg-white text-gray-400"
         style="height: 380px;">
        <i data-lucide="map-pin" class="h-8 w-8"></i>
        <p class="text-sm">No location pinned yet.</p>
        <p class="text-xs">Select a primary file number to backfill its coordinates, or click <strong>Pin on Map</strong>.</p>
    </div>

    <input type="hidden" name="latitude" id="pfPropertyLatitude">
    <input type="hidden" name="longitude" id="pfPropertyLongitude">
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initLocationDetailsMap !== 'function') {
        console.warn('location-map.js not loaded - property map unavailable.');
        return;
    }

    // Bind to the address inputs this form already renders; the form keeps
    // driving them (states-lga.js, togglePropertyAddressEdit), we only map.
    initLocationDetailsMap({
        prefix: 'pf',
        ownsFields: false,
        fieldIds: {
            PropertyHouseNo: 'propertyHouseNo',
            PropertyPlotNo: 'propertyPlotNo',
            PropertyStreetName: 'propertyStreetName',
            PropertyDistrict: 'propertyDistrict',
            PropertyLga: 'propertyLga',
            PropertyState: 'propertyState',
            PropertyAddressDisplay: 'propertyAddressDisplay',
            FullPropertyAddress: 'fullPropertyAddress'
        }
    });
});
</script>

<div class="border-t pt-2 mt-2">
    <label
        class="flex items-center gap-2 text-xs text-gray-700 bg-gray-50 p-2 rounded border border-gray-200 cursor-pointer w-fit">
        <input type="checkbox" id="hasThirdParty" class="form-checkbox h-3.5 w-3.5 text-blue-600 rounded">
        <span class="font-medium">Include Co-Surrenderor?</span>
    </label>
</div>

<!-- Hidden Container for Party 3 -->
<div id="thirdPartyContainer"
    class="hidden grid grid-cols-1 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200 mt-2">
    <h4 class="font-medium text-gray-800 border-b pb-1 mb-1 text-sm">Co-Surrenderor Details</h4>
    <x-instrument-input id="coSurrenderorName" label="Co-Surrenderor Name" icon="users"
        placeholder="Enter co-surrenderor name" />
    <x-instrument-textarea id="coSurrenderorAddress" label="Co-Surrenderor Address" icon="map-pin" placeholder="Address"
        rows="1" />
</div>

@include('instruments.partials.types.sidebars.solicitor_toggle')
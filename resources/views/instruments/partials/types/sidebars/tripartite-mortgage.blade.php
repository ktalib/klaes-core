<!-- Co-Mortgagor Section (Party 3) -->
<div class="border-t pt-2 mt-2">
    <label class="flex items-center gap-2 text-xs text-gray-700 bg-gray-50 p-2 rounded border border-gray-200 cursor-pointer w-fit max-w-full">
        <input type="checkbox" id="hasCoMortgagor" name="hasCoMortgagor" class="form-checkbox h-3.5 w-3.5 text-blue-600 rounded">
        <span class="font-medium">Include Co-Mortgagor Details</span>
    </label>
</div>

<div id="coMortgagorContainer" class="hidden grid grid-cols-1 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200 mt-2">
    <h4 class="font-medium text-gray-800 border-b pb-1 mb-1 text-sm">Co-Mortgagor Details</h4>
    <x-instrument-input id="coMortgagorName" label="Name" icon="user" placeholder="Enter name" value="{{ $record->party_3_name ?? '' }}" />
    
    <div class="grid grid-cols-2 gap-3">
        <x-instrument-input id="coMortgagorAddress" label="Address" icon="map-pin" placeholder="Enter address" value="{{ $record->party_3_address ?? '' }}" />
        <div>
            <x-instrument-select id="coMortgagorDistrict" label="District" icon="map-pin" :options="$districts->merge([(object)['name' => 'Others']])" placeholder="District" value="{{ $record->party_3_district ?? '' }}" optionValue="name" optionLabel="name" />
            <div id="manual_coMortgagorDistrict_container" class="mt-2 hidden">
                <x-instrument-input id="manual_coMortgagorDistrict" name="manual_coMortgagorDistrict" label="" icon="edit-3" placeholder="Specify District" />
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <x-instrument-select id="coMortgagorState" label="State" icon="map-pin" :options="$states" placeholder="State" value="{{ $record->party_3_state ?? 'Kano' }}" />
        <x-instrument-select id="coMortgagorLga" label="LGA" icon="map" :options="[]" placeholder="LGA" value="{{ $record->party_3_lga ?? '' }}" />
    </div>
    <x-instrument-input id="coMortgagorPhone" label="Phone" icon="phone" placeholder="Phone" value="{{ $record->party_3_phone ?? '' }}" />
</div>

<!-- Third Party Section (Party 4) -->
<div class="border-t pt-2 mt-2">
    <label class="flex items-center gap-2 text-xs text-gray-700 bg-gray-50 p-2 rounded border border-gray-200 cursor-pointer w-fit max-w-full">
        <input type="checkbox" id="hasThirdParty" name="hasThirdParty" class="form-checkbox h-3.5 w-3.5 text-indigo-600 rounded">
        <span class="font-medium">Include Third Party Details</span>
    </label>
</div>

<div id="thirdPartyContainer" class="hidden grid grid-cols-1 gap-4 bg-indigo-50 p-3 rounded-lg border border-indigo-200 mt-2">
    <h4 class="font-medium text-indigo-800 border-b border-indigo-200 pb-1 mb-1 text-sm">Third Party Details</h4>
    <x-instrument-input id="thirdPartyName" label="Name" icon="user" placeholder="Enter name" value="{{ $record->party_4_name ?? '' }}" />
    
    <div class="grid grid-cols-2 gap-3">
        <x-instrument-input id="thirdPartyAddress" label="Address" icon="map-pin" placeholder="Enter address" value="{{ $record->party_4_address ?? '' }}" />
        <div>
            <x-instrument-select id="thirdPartyDistrict" label="District" icon="map-pin" :options="$districts->merge([(object)['name' => 'Others']])" placeholder="District" value="{{ $record->party_4_district ?? '' }}" optionValue="name" optionLabel="name" />
            <div id="manual_thirdPartyDistrict_container" class="mt-2 hidden">
                <x-instrument-input id="manual_thirdPartyDistrict" name="manual_thirdPartyDistrict" label="" icon="edit-3" placeholder="Specify District" />
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <x-instrument-select id="thirdPartyState" label="State" icon="map-pin" :options="$states" placeholder="State" value="{{ $record->party_4_state ?? 'Kano' }}" />
        <x-instrument-select id="thirdPartyLga" label="LGA" icon="map" :options="[]" placeholder="LGA" value="{{ $record->party_4_lga ?? '' }}" />
    </div>
    <x-instrument-input id="thirdPartyPhone" label="Phone" icon="phone" placeholder="Phone" value="{{ $record->party_4_phone ?? '' }}" />
</div>

<!-- Fourth Party Section (Party 5) -->
<div class="border-t pt-2 mt-2">
    <label class="flex items-center gap-2 text-xs text-gray-700 bg-gray-50 p-2 rounded border border-gray-200 cursor-pointer w-fit max-w-full">
        <input type="checkbox" id="hasFourthParty" name="hasFourthParty" class="form-checkbox h-3.5 w-3.5 text-purple-600 rounded">
        <span class="font-medium">Include Fourth Party Details</span>
    </label>
</div>

<div id="fourthPartyContainer" class="hidden grid grid-cols-1 gap-4 bg-purple-50 p-3 rounded-lg border border-purple-200 mt-2">
    <h4 class="font-medium text-purple-800 border-b border-purple-200 pb-1 mb-1 text-sm">Fourth Party Details</h4>
    <x-instrument-input id="party5Name" label="Name" icon="user" placeholder="Enter name" value="{{ $record->party_5_name ?? '' }}" />
    
    <div class="grid grid-cols-2 gap-3">
        <x-instrument-input id="party5Address" label="Address" icon="map-pin" placeholder="Enter address" value="{{ $record->party_5_address ?? '' }}" />
        <div>
            <x-instrument-select id="party5District" label="District" icon="map-pin" :options="$districts->merge([(object)['name' => 'Others']])" placeholder="District" value="{{ $record->party_5_district ?? '' }}" optionValue="name" optionLabel="name" />
            <div id="manual_party5District_container" class="mt-2 hidden">
                <x-instrument-input id="manual_party5District" name="manual_party5District" label="" icon="edit-3" placeholder="Specify District" />
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <x-instrument-select id="party5State" label="State" icon="map-pin" :options="$states" placeholder="State" value="{{ $record->party_5_state ?? 'Kano' }}" />
        <x-instrument-select id="party5Lga" label="LGA" icon="map" :options="[]" placeholder="LGA" value="{{ $record->party_5_lga ?? '' }}" />
    </div>
    <x-instrument-input id="party5Phone" label="Phone" icon="phone" placeholder="Phone" value="{{ $record->party_5_phone ?? '' }}" />
</div>



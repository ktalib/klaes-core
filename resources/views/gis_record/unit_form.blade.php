<!-- Unit Attribution Information -->
<div class="border border-gray-200 rounded-lg p-4 bg-gray-50 mb-6">
    <h4 class="text-sm font-medium mb-3">Unit Information</h4>
    
    <!-- Unit Identification -->
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="PrimaryGISID" class="block text-sm font-medium text-gray-700">Primary GIS ID</label>
            <input type="text" id="PrimaryGISID" name="PrimaryGISID" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
        </div>
        <div>
            <label for="STFileNo" class="block text-sm font-medium text-gray-700">ST File Number</label>
            <input type="text" id="STFileNo" name="STFileNo" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
        </div>
        <div>
            <label for="app_id" class="block text-sm font-medium text-gray-700">Application ID</label>
            <input type="text" id="app_id" name="app_id" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
        </div>
    </div>
    
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Scheme Number</label>
            <input type="text" id="scheme_no_preview" class="w-full p-2 border border-gray-300 rounded-md text-sm">
            <input type="hidden" id="scheme_no" name="scheme_no">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Section Number</label>
            <input type="text" id="section_no_preview" class="w-full p-2 border border-gray-300 rounded-md text-sm">
            <input type="hidden" id="section_no" name="section_no">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Block Number</label>
            <input type="text" id="block_no_preview" class="w-full p-2 border border-gray-300 rounded-md text-sm">
            <input type="hidden" id="block_no" name="block_no">
        </div>
    </div>
    
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Unit Number</label>
            <input type="text" id="unit_no_preview" class="w-full p-2 border border-gray-300 rounded-md text-sm">
            <input type="hidden" id="unit_no" name="unit_no">
        </div>
        <div>
            <label for="unit_id" class="block text-sm font-medium text-gray-700">Unit ID</label>
            <input type="text" id="unit_id" name="unit_id" readonly class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100">
        </div>
        <div>
            <label for="landuse" class="block text-sm font-medium text-gray-700">Land Use</label>
            <input type="text" id="landuse" name="landuse" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <!-- Unit Specifications -->
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="height" class="block text-sm font-medium text-gray-700">Height</label>
            <input type="text" id="height" name="height" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="section_attribute" class="block text-sm font-medium text-gray-700">Section Attribute</label>
            <input type="text" id="section_attribute" name="section_attribute" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="base" class="block text-sm font-medium text-gray-700">Base</label>
            <input type="text" id="base" name="base" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="floor" class="block text-sm font-medium text-gray-700">Floor</label>
            <input type="text" id="floor" name="floor" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <!-- Unit Control Beacon Information -->
    <h4 class="text-sm font-medium mb-3 mt-4">Unit Control Beacon Information</h4>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="UnitControlBeaconNo" class="block text-sm font-medium text-gray-700">Unit Control Beacon No</label>
            <input type="text" id="UnitControlBeaconNo" name="UnitControlBeaconNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitControlBeaconX" class="block text-sm font-medium text-gray-700">Unit Control Beacon X</label>
            <input type="text" id="UnitControlBeaconX" name="UnitControlBeaconX" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitControlBeaconY" class="block text-sm font-medium text-gray-700">Unit Control Beacon Y</label>
            <input type="text" id="UnitControlBeaconY" name="UnitControlBeaconY" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <!-- Unit Dimensions and Position -->
    <h4 class="text-sm font-medium mb-3 mt-4">Unit Dimensions and Position</h4>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="UnitSize" class="block text-sm font-medium text-gray-700">Unit Size</label>
            <input type="text" id="UnitSize" name="UnitSize" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitDemsion" class="block text-sm font-medium text-gray-700">Unit Dimension</label>
            <input type="text" id="UnitDemsion" name="UnitDemsion" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitPosition" class="block text-sm font-medium text-gray-700">Unit Position</label>
            <input type="text" id="UnitPosition" name="UnitPosition" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <!-- Additional Information -->
    <h4 class="text-sm font-medium mb-3 mt-4">Additional Information</h4>
    <div class="grid grid-cols-1 gap-4">
        <div>
            <label for="tpreport" class="block text-sm font-medium text-gray-700">TP Report</label>
            <textarea id="tpreport" name="tpreport" rows="3" class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
        </div>
    </div>
</div>
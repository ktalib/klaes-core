@if(request()->query('is') == 'secondary' || (isset($survey) && $survey->survey_type == 'Unit Survey'))
<!-- Unit Attribution Information -->
<div class="border border-gray-200 rounded-lg p-4 bg-gray-50 mb-6">
    <h4 class="text-sm font-medium mb-3">Unit Information</h4>
    
    <!-- Unit Identification -->
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="PrimarysurveyId" class="block text-sm font-medium text-gray-700">Primary Survey FileNo</label>
            <input id="PrimarysurveyId" name="PrimarysurveyId" type="text" value="{{ $survey->PrimarysurveyId ?? old('PrimarysurveyId') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
        </div>
        <div>
            <label for="STFileNo" class="block text-sm font-medium text-gray-700">ST FileNo</label>
            <input id="STFileNo" name="STFileNo" type="text" value="{{ $survey->STFileNo ?? old('STFileNo') }}"class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
        </div>
        <div>
            <label for="scheme_no" class="block text-sm font-medium text-gray-700">Scheme No</label>
            <input id="scheme_no" name="scheme_no" type="text" value="{{ $survey->scheme_no ?? old('scheme_no') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <div class="grid grid-cols-3 gap-4 mb-4">
    
        <div>
            <label for="section_no" class="block text-sm font-medium text-gray-700">section_no</label>
            <input id="floor_number" name="section_no" type="text" value="{{ $survey->floor_number ?? old('floor_number') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>


        <div>
            <label for="unit_number" class="block text-sm font-medium text-gray-700">Unit No</label>
            <input id="unit_number" name="unit_number" type="text" value="{{ $survey->unit_number ?? old('unit_number') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="unit_id" class="block text-sm font-medium text-gray-700">Unit ID</label>
            <input id="unit_id" name="unit_id" type="text" value="{{ $survey->unit_id ?? old('unit_id') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
        </div>
    </div>
    
    <!-- Unit Specifications -->
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="height" class="block text-sm font-medium text-gray-700">Height</label>
            <input id="height" name="height" type="text" value="{{ $survey->height ?? old('height') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="app_id" class="block text-sm font-medium text-gray-700">Application ID</label>
            <input id="app_id" name="app_id" type="text" value="{{ $survey->app_id ?? old('app_id') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
        </div>
        <div>
            <label for="landuse" class="block text-sm font-medium text-gray-700">Land Use</label>
            <input id="landuse" name="landuse" type="text" value="{{ $survey->landuse ?? old('landuse') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="section_attribute" class="block text-sm font-medium text-gray-700">Section Attribute</label>
            <input id="section_attribute" name="section_attribute" type="text" value="{{ $survey->section_attribute ?? old('section_attribute') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="base" class="block text-sm font-medium text-gray-700">Base</label>
            <input id="base" name="base" type="text" value="{{ $survey->base ?? old('base') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
          <div>
            <label for="section_no" class="block text-sm font-medium text-gray-700">Floor</label>
            <input id="Floor" name="floor" type="text" value="{{ $survey->floor ?? old('floor') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>

    </div>
    
    <!-- Unit Control Beacon Information -->
    <h4 class="text-sm font-medium mb-3 mt-4">Unit Control Beacon Information</h4>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="UnitControlBeaconNo" class="block text-sm font-medium text-gray-700">Unit Control Beacon No</label>
            <input id="UnitControlBeaconNo" name="UnitControlBeaconNo" type="text" value="{{ $survey->UnitControlBeaconNo ?? old('UnitControlBeaconNo') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitControlBeaconX" class="block text-sm font-medium text-gray-700">Unit Control Beacon X</label>
            <input id="UnitControlBeaconX" name="UnitControlBeaconX" type="text" value="{{ $survey->UnitControlBeaconX ?? old('UnitControlBeaconX') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitControlBeaconY" class="block text-sm font-medium text-gray-700">Unit Control Beacon Y</label>
            <input id="UnitControlBeaconY" name="UnitControlBeaconY" type="text" value="{{ $survey->UnitControlBeaconY ?? old('UnitControlBeaconY') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <!-- Unit Dimensions and Position -->
    <h4 class="text-sm font-medium mb-3 mt-4">Unit Dimensions and Position</h4>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="UnitSize" class="block text-sm font-medium text-gray-700">Unit Size</label>
            <input id="UnitSize" name="UnitSize" type="text" value="{{ $survey->UnitSize ?? old('UnitSize') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitDemsion" class="block text-sm font-medium text-gray-700">Unit Dimension</label>
            <input id="UnitDemsion" name="UnitDemsion" type="text" value="{{ $survey->UnitDemsion ?? old('UnitDemsion') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="UnitPosition" class="block text-sm font-medium text-gray-700">Unit Position</label>
            <input id="UnitPosition" name="UnitPosition" type="text" value="{{ $survey->UnitPosition ?? old('UnitPosition') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
        </div>
    </div>
    
    <!-- Additional Information -->
    <h4 class="text-sm font-medium mb-3 mt-4">Additional Information</h4>
    <div class="grid grid-cols-1 gap-4">
        <div>
            <label for="tpreport" class="block text-sm font-medium text-gray-700">TP Report</label>
            <textarea id="tpreport" name="tpreport" rows="3" class="w-full p-2 border border-gray-300 rounded-md text-sm">{{ $survey->tpreport ?? old('tpreport') }}</textarea>
        </div>
    </div>
</div>
@endif
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
            <label for="scheme_no" class="block text-sm font-medium text-gray-700">Scheme No <span class="text-red-600">*</span></label>
            <input id="scheme_no" name="scheme_no" type="text" value="{{ $survey->scheme_no ?? old('scheme_no') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
        </div>
    </div>
    
    <!-- Unit Control Beacon Information -->
    <h4 class="text-sm font-medium mb-3 mt-4">Unit Control Beacon Information</h4>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label for="UnitControlBeaconNo" class="block text-sm font-medium text-gray-700">Unit Control Beacon No <span class="text-red-600">*</span></label>
            <input id="UnitControlBeaconNo" name="UnitControlBeaconNo" type="text" value="{{ $survey->UnitControlBeaconNo ?? old('UnitControlBeaconNo') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
        </div>
        <div>
            <label for="UnitControlBeaconX" class="block text-sm font-medium text-gray-700">Unit Control Beacon X <span class="text-red-600">*</span></label>
            <input id="UnitControlBeaconX" name="UnitControlBeaconX" type="text" value="{{ $survey->UnitControlBeaconX ?? old('UnitControlBeaconX') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
        </div>
        <div>
            <label for="UnitControlBeaconY" class="block text-sm font-medium text-gray-700">Unit Control Beacon Y <span class="text-red-600">*</span></label>
            <input id="UnitControlBeaconY" name="UnitControlBeaconY" type="text" value="{{ $survey->UnitControlBeaconY ?? old('UnitControlBeaconY') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
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
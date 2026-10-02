@extends('layouts.app')
@section('page-title')
    {{ __('Update Survey') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Update Survey Form -->
    <div class="p-6">
        <form id="update-survey-form" method="POST" action="{{ route('survey_record.update-survey') }}">
            @csrf
            <input type="hidden" name="id" value="{{ $survey->ID }}">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 space-y-6">
                <h3 class="text-lg font-medium">
                    Update {{ $survey->survey_type ?? 'Survey' }} Details
                </h3>
                
                @if(isset($survey->survey_type) && $survey->survey_type == 'Unit Survey')
                    @include('survey_record.unit_form', ['survey' => $survey])
                @endif
                
                <!-- Property Identification -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Property Identification</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="plot_no" class="block text-sm font-medium text-gray-700">Plot No</label>
                            <input id="plot_no" name="plot_no" type="text" value="{{ $survey->plot_no }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="block_no" class="block text-sm font-medium text-gray-700">Block No</label>
                            <input id="block_no" name="block_no" type="text" value="{{ $survey->block_no }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="approved_plan_no" class="block text-sm font-medium text-gray-700">Approved Plan No</label>
                            <input id="approved_plan_no" name="approved_plan_no" type="text" value="{{ $survey->approved_plan_no }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="tp_plan_no" class="block text-sm font-medium text-gray-700">TP Plan No</label>
                            <input id="tp_plan_no" name="tp_plan_no" type="text" value="{{ $survey->tp_plan_no }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                </div>

                <!-- Control Beacon Information -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Control Beacon Information</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="beacon_control_name" class="block text-sm font-medium text-gray-700">Control Beacon Name</label>
                            <input id="beacon_control_name" name="beacon_control_name" type="text" value="{{ $survey->beacon_control_name }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="Control_Beacon_Coordinate_X" class="block text-sm font-medium text-gray-700">Control Beacon X</label>
                            <input id="Control_Beacon_Coordinate_X" name="Control_Beacon_Coordinate_X" type="text" value="{{ $survey->Control_Beacon_Coordinate_X }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="Control_Beacon_Coordinate_Y" class="block text-sm font-medium text-gray-700">Control Beacon Y</label>
                            <input id="Control_Beacon_Coordinate_Y" name="Control_Beacon_Coordinate_Y" type="text" value="{{ $survey->Control_Beacon_Coordinate_Y }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                </div>

                <!-- Sheet Information -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Sheet Information</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="Metric_Sheet_Index" class="block text-sm font-medium text-gray-700">Metric Sheet Index</label>
                            <input id="Metric_Sheet_Index" name="Metric_Sheet_Index" type="text" value="{{ $survey->Metric_Sheet_Index }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="Metric_Sheet_No" class="block text-sm font-medium text-gray-700">Metric Sheet No</label>
                            <input id="Metric_Sheet_No" name="Metric_Sheet_No" type="text" value="{{ $survey->Metric_Sheet_No }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="Imperial_Sheet" class="block text-sm font-medium text-gray-700">Imperial Sheet</label>
                            <input id="Imperial_Sheet" name="Imperial_Sheet" type="text" value="{{ $survey->Imperial_Sheet }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="Imperial_Sheet_No" class="block text-sm font-medium text-gray-700">Imperial Sheet No</label>
                            <input id="Imperial_Sheet_No" name="Imperial_Sheet_No" type="text" value="{{ $survey->Imperial_Sheet_No }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Location Information</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="layout_name" class="block text-sm font-medium text-gray-700">Layout Name</label>
                            <input id="layout_name" name="layout_name" type="text" value="{{ $survey->layout_name }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="district_name" class="block text-sm font-medium text-gray-700">District Name</label>
                            <input id="district_name" name="district_name" type="text" value="{{ $survey->district_name }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="lga_name" class="block text-sm font-medium text-gray-700">LGA Name</label>
                            <input id="lga_name" name="lga_name" type="text" value="{{ $survey->lga_name }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                </div>

                <!-- Personnel Information -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Personnel Information</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="survey_by" class="block text-sm font-medium text-gray-700">Survey By</label>
                            <input id="survey_by" name="survey_by" type="text" value="{{ $survey->survey_by }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="survey_by_date" class="block text-sm font-medium text-gray-700">Survey Date</label>
                            <input id="survey_by_date" name="survey_by_date" type="date" value="{{ $survey->survey_by_date }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="drawn_by" class="block text-sm font-medium text-gray-700">Drawn By</label>
                            <input id="drawn_by" name="drawn_by" type="text" value="{{ $survey->drawn_by }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="drawn_by_date" class="block text-sm font-medium text-gray-700">Drawn Date</label>
                            <input id="drawn_by_date" name="drawn_by_date" type="date" value="{{ $survey->drawn_by_date }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="checked_by" class="block text-sm font-medium text-gray-700">Checked By</label>
                            <input id="checked_by" name="checked_by" type="text" value="{{ $survey->checked_by }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="checked_by_date" class="block text-sm font-medium text-gray-700">Checked Date</label>
                            <input id="checked_by_date" name="checked_by_date" type="date" value="{{ $survey->checked_by_date }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="approved_by" class="block text-sm font-medium text-gray-700">Approved By</label>
                            <input id="approved_by" name="approved_by" type="text" value="{{ $survey->approved_by }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                            <label for="approved_by_date" class="block text-sm font-medium text-gray-700">Approved Date</label>
                            <input id="approved_by_date" name="approved_by_date" type="date" value="{{ $survey->approved_by_date }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Survey
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

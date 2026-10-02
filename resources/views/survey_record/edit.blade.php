@extends('layouts.app')
@section('page-title')
    {{ __('Edit Survey Record') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Edit Survey Form -->
    <div class="p-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <!-- Header with back button -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">Edit Survey Record</h2>
                <a href="{{ route('survey_record.index') }}" 
                   class="flex items-center space-x-2 px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Back to List</span>
                </a>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="edit-survey-form" method="POST" action="{{ route('survey_record.update-survey') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="id" value="{{ $survey->ID }}">
                
                <div class="space-y-6">
                    <h3 class="text-lg font-medium">
                        Edit {{ $survey->survey_type ?? 'Survey' }} Details
                    </h3>
                    
                    @if(isset($survey->survey_type) && $survey->survey_type == 'Unit Survey')
                        @include('survey_record.unit_form', ['survey' => $survey])
                    @endif
                    
                    <!-- Property Identification -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h4 class="text-sm font-medium mb-3 text-green-600">Property Identification</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="plot_no" class="block text-sm font-medium text-gray-700 mb-1">Plot No</label>
                                <input id="plot_no" name="plot_no" type="text" value="{{ old('plot_no', $survey->plot_no) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="block_no" class="block text-sm font-medium text-gray-700 mb-1">Block No</label>
                                <input id="block_no" name="block_no" type="text" value="{{ old('block_no', $survey->block_no) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label for="approved_plan_no" class="block text-sm font-medium text-gray-700 mb-1">Approved Plan No</label>
                                <input id="approved_plan_no" name="approved_plan_no" type="text" value="{{ old('approved_plan_no', $survey->approved_plan_no) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="tp_plan_no" class="block text-sm font-medium text-gray-700 mb-1">TP Plan No</label>
                                <input id="tp_plan_no" name="tp_plan_no" type="text" value="{{ old('tp_plan_no', $survey->tp_plan_no) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Control Beacon Information -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h4 class="text-sm font-medium mb-3 text-green-600">Control Beacon Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="beacon_control_name" class="block text-sm font-medium text-gray-700 mb-1">Control Beacon Name</label>
                                <input id="beacon_control_name" name="beacon_control_name" type="text" value="{{ old('beacon_control_name', $survey->beacon_control_name) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="Control_Beacon_Coordinate_X" class="block text-sm font-medium text-gray-700 mb-1">Control Beacon X</label>
                                <input id="Control_Beacon_Coordinate_X" name="Control_Beacon_Coordinate_X" type="text" value="{{ old('Control_Beacon_Coordinate_X', $survey->Control_Beacon_Coordinate_X) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="Control_Beacon_Coordinate_Y" class="block text-sm font-medium text-gray-700 mb-1">Control Beacon Y</label>
                                <input id="Control_Beacon_Coordinate_Y" name="Control_Beacon_Coordinate_Y" type="text" value="{{ old('Control_Beacon_Coordinate_Y', $survey->Control_Beacon_Coordinate_Y) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Sheet Information -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h4 class="text-sm font-medium mb-3 text-green-600">Sheet Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="Metric_Sheet_Index" class="block text-sm font-medium text-gray-700 mb-1">Metric Sheet Index</label>
                                <input id="Metric_Sheet_Index" name="Metric_Sheet_Index" type="text" value="{{ old('Metric_Sheet_Index', $survey->Metric_Sheet_Index) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="Metric_Sheet_No" class="block text-sm font-medium text-gray-700 mb-1">Metric Sheet No</label>
                                <input id="Metric_Sheet_No" name="Metric_Sheet_No" type="text" value="{{ old('Metric_Sheet_No', $survey->Metric_Sheet_No) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label for="Imperial_Sheet" class="block text-sm font-medium text-gray-700 mb-1">Imperial Sheet</label>
                                <input id="Imperial_Sheet" name="Imperial_Sheet" type="text" value="{{ old('Imperial_Sheet', $survey->Imperial_Sheet) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="Imperial_Sheet_No" class="block text-sm font-medium text-gray-700 mb-1">Imperial Sheet No</label>
                                <input id="Imperial_Sheet_No" name="Imperial_Sheet_No" type="text" value="{{ old('Imperial_Sheet_No', $survey->Imperial_Sheet_No) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Location Information -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h4 class="text-sm font-medium mb-3 text-green-600">Location Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="layout_name" class="block text-sm font-medium text-gray-700 mb-1">Layout Name</label>
                                <input id="layout_name" name="layout_name" type="text" value="{{ old('layout_name', $survey->layout_name) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="district_name" class="block text-sm font-medium text-gray-700 mb-1">District Name</label>
                                <input id="district_name" name="district_name" type="text" value="{{ old('district_name', $survey->district_name) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="lga_name" class="block text-sm font-medium text-gray-700 mb-1">LGA Name</label>
                                <input id="lga_name" name="lga_name" type="text" value="{{ old('lga_name', $survey->lga_name) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Personnel Information -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h4 class="text-sm font-medium mb-3 text-green-600">Personnel Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="survey_by" class="block text-sm font-medium text-gray-700 mb-1">Survey By</label>
                                <input id="survey_by" name="survey_by" type="text" value="{{ old('survey_by', $survey->survey_by) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="survey_by_date" class="block text-sm font-medium text-gray-700 mb-1">Survey Date</label>
                                <input id="survey_by_date" name="survey_by_date" type="date" value="{{ old('survey_by_date', $survey->survey_by_date) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label for="drawn_by" class="block text-sm font-medium text-gray-700 mb-1">Drawn By</label>
                                <input id="drawn_by" name="drawn_by" type="text" value="{{ old('drawn_by', $survey->drawn_by) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="drawn_by_date" class="block text-sm font-medium text-gray-700 mb-1">Drawn Date</label>
                                <input id="drawn_by_date" name="drawn_by_date" type="date" value="{{ old('drawn_by_date', $survey->drawn_by_date) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label for="checked_by" class="block text-sm font-medium text-gray-700 mb-1">Checked By</label>
                                <input id="checked_by" name="checked_by" type="text" value="{{ old('checked_by', $survey->checked_by) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="checked_by_date" class="block text-sm font-medium text-gray-700 mb-1">Checked Date</label>
                                <input id="checked_by_date" name="checked_by_date" type="date" value="{{ old('checked_by_date', $survey->checked_by_date) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label for="approved_by" class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                                <input id="approved_by" name="approved_by" type="text" value="{{ old('approved_by', $survey->approved_by) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="approved_by_date" class="block text-sm font-medium text-gray-700 mb-1">Approved Date</label>
                                <input id="approved_by_date" name="approved_by_date" type="date" value="{{ old('approved_by_date', $survey->approved_by_date) }}" 
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Survey Plan Upload -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h4 class="text-sm font-medium mb-3 text-green-600">Survey Plan Document</h4>
                        <div class="space-y-3">
                            @if(!empty($survey->survey_plan_path))
                                <div class="flex items-center space-x-2 text-sm text-gray-600">
                                    <i data-lucide="file" class="w-4 h-4"></i>
                                    <span>Current file: {{ basename($survey->survey_plan_path) }}</span>
                                    <button type="button" onclick="viewCurrentPlan()" class="text-blue-600 hover:text-blue-800">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            @endif
                            <div>
                                <label for="survey_plan_path" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ !empty($survey->survey_plan_path) ? 'Replace Survey Plan' : 'Upload Survey Plan' }}
                                </label>
                                <input id="survey_plan_path" name="survey_plan_path" type="file" 
                                       accept=".pdf,.jpg,.jpeg,.png,.dwg,.dxf"
                                       class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Supported formats: PDF, JPG, PNG, DWG, DXF (Max: 10MB)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <a href="{{ route('survey_record.index') }}" 
                           class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i data-lucide="save" class="w-4 h-4 inline mr-1"></i>
                            Update Survey Record
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
    });

    // Function to view current plan
    function viewCurrentPlan() {
        @if(!empty($survey->survey_plan_path))
            const planPath = '{{ $survey->survey_plan_path }}';
            const fileNo = '{{ $survey->fileno ?? 'N/A' }}';
            
            // Use the same modal function from the index page
            if (typeof viewSurveyPlan === 'function') {
                viewSurveyPlan({{ $survey->ID }}, fileNo, planPath);
            } else {
                // Fallback - open in new window
                const fullPath = `{{ asset('storage') }}/${planPath}`;
                window.open(fullPath, '_blank');
            }
        @endif
    }
</script>
@endsection
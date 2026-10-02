@extends('layouts.app')
@section('page-title')
    {{ __('create New Survey') }}
@endsection

@section('content') 
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Update Survey Form -->
    <div class="p-6">
        <form id="update-survey-form" method="POST" action="{{ route('attribution.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="application_id" id="application_id" value="">
            <input type="hidden" name="sub_application_id" id="sub_application_id" value="">
            
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 space-y-6">
                <div id="application-info" class="hidden">
                    <!-- Application header will be rendered dynamically -->
                </div>
                
                
                <div>
                    <h3 class="text-lg font-medium mb-4">
                        Create A {{ request()->query('is') == 'secondary' ? 'Unit' : 'Primary' }} Survey
                    </h3>
                    
                    <!-- Selection Grid Layout -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 mb-4">
                        @if(request()->query('is') == 'secondary')
                            <!-- Grid for Unit Survey: Primary Survey + File Number -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Primary Survey Selection -->
                                <div>
                                    <label for="primary-survey-select" class="block text-sm font-medium text-gray-700 mb-1">Select Primary Survey</label>
                                    <select id="primary-survey-select" class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">-- Select Primary Survey FileNo --</option>
                                    </select>
                                </div>
                                
                                <!-- File Number Selection -->
                                <div>
                                    <label for="fileno-select" class="block text-sm font-medium text-gray-700 mb-1">Select File Number</label>
                                    <select id="fileno-select" class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">-- Select File Number --</option>
                                    </select>
                                </div>
                            </div>
                        @else
                            <!-- Single column for Primary Survey: Smart File Number Selector -->
                            <div>
                                @include('components.smart_fileno_selector')
                            </div>
                        @endif
                    </div>
                </div>

                
            @include('attribution.unit_form')
                <!-- Property Identification -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Property Identification</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="plot_no" class="block text-sm font-medium text-gray-700">Plot No <span class="text-red-600">*</span></label>
                            <input id="plot_no" name="plot_no" type="text" value="{{ old('plot_no') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="block_no" class="block text-sm font-medium text-gray-700">Block No <span class="text-red-600">*</span></label>
                            <input id="block_no" name="block_no" type="text" value="{{ old('block_no') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled required oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>
                    @if(request()->query('is') == 'secondary')
                    <div class="grid grid-cols-1 gap-4 mt-3" style="display: none;">
                        <div>
                            <label for="scheme_no" class="block text-sm font-medium text-gray-700">Scheme No <span class="text-red-600">*</span></label>
                            <input id="scheme_no" name="scheme_no" type="text" value="{{ old('scheme_no') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled>
                        </div>
                    </div>
                    @endif
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="approved_plan_no" class="block text-sm font-medium text-gray-700">Approved Plan No <span class="text-red-600">*</span></label>
                            <input id="approved_plan_no" name="approved_plan_no" type="text" value="{{ old('approved_plan_no') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="tp_plan_no" class="block text-sm font-medium text-gray-700">TP Plan No <span class="text-red-600">*</span></label>
                            <input id="tp_plan_no" name="tp_plan_no" type="text" value="{{ old('tp_plan_no') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>
                </div>

                <!-- Control Beacon Information -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">
                        Control Beacon Information
                    </h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="beacon_control_name" class="block text-sm font-medium text-gray-700">
                               Control Beacon Name <span class="text-red-600">*</span>
                            </label>
                            <input id="beacon_control_name" name="beacon_control_name" type="text" value="{{ old('beacon_control_name') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="Control_Beacon_Coordinate_X" class="block text-sm font-medium text-gray-700">
                               Control Beacon X <span class="text-red-600">*</span>
                            </label>
                            <input id="Control_Beacon_Coordinate_X" name="Control_Beacon_Coordinate_X" type="text" value="{{ old('Control_Beacon_Coordinate_X') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="Control_Beacon_Coordinate_Y" class="block text-sm font-medium text-gray-700">
                               Control Beacon Y <span class="text-red-600">*</span>
                            </label>
                            <input id="Control_Beacon_Coordinate_Y" name="Control_Beacon_Coordinate_Y" type="text" value="{{ old('Control_Beacon_Coordinate_Y') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>
                </div>

                  <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Sheet Information</h4>
                    <div class="grid grid-cols-2 gap-4">
                        

                       @include('components.metricSheetIndex')

                        
                    
                        @include('components.metricSheetNo')

                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                         @include('components.imperialSheet')

                        @include('components.imperialSheetNo')
                        
                         
                    </div>
                </div>
                <!-- Location Information -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Location Information</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="layout_name" class="block text-sm font-medium text-gray-700">Layout Name <span class="text-red-600">*</span></label>
                            <input id="layout_name" name="layout_name" type="text" value="{{ old('layout_name') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                            @include('components.District4')
                        <div>
                            <label for="lga_name" class="block text-sm font-medium text-gray-700">LGA Name <span class="text-red-600">*</span></label>
                            <select id="lga_name" name="lga_name" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                <option value="">-- Select LGA --</option>
                                <option value="">Select LGA</option>
                            <option value="Albasu">Albasu</option>
                            <option value="Bagwai">Bagwai</option>
                            <option value="Dala">Dala</option>
                            <option value="Danbatta">Danbatta</option>
                            <option value="D/Tofa">D/Tofa</option>
                            <option value="Gaya">Gaya</option>
                            <option value="Gwale">Gwale</option>
                            <option value="Doguwa">Doguwa</option>
                            <option value="Kibiya">Kibiya</option>
                            <option value="Kabo">Kabo</option>
                            <option value="Gezawa">Gezawa</option>
                            <option value="Kunchi">Kunchi</option>
                            <option value="Karaye">Karaye</option>
                            <option value="Garum Malan">Garum Malan</option>
                            <option value="Madobi">Madobi</option>
                            <option value="Gabasawa">Gabasawa</option>
                            <option value="Rimin Gado">Rimin Gado</option>
                            <option value="Rogo">Rogo</option>
                            <option value="Shanono">Shanono</option>
                            <option value="Municipal">Municipal</option>
                            <option value="Sumaila">Sumaila</option>
                            <option value="Tarauni">Tarauni</option>
                            <option value="Tsanyawa">Tsanyawa</option>
                            <option value="Tudun Wada">Tudun Wada</option>
                            <option value="Tofa">Tofa</option>
                            <option value="Takai">Takai</option>
                            <option value="Kura">Kura</option>
                            <option value="Warawa">Warawa</option>
                            <option value="Garko">Garko</option>
                            <option value="Ajingi">Ajingi</option>
                            <option value="Bichi">Bichi</option>
                            <option value="Minjinbir">Minjinbir</option>
                            <option value="Rano">Rano</option>
                            <option value="Bunkure">Bunkure</option>
                            <option value="Kiru">Kiru</option>
                            <option value="Gwarzo">Gwarzo</option>
                            <option value="Ungogo">Ungogo</option>
                            <option value="Makoda">Makoda</option>
                            <option value="Wudil">Wudil</option>
                            <option value="Nassarawa">Nassarawa</option>
                            <option value="Bebeji">Bebeji</option>
                            <option value="Faffe">Faffe</option>
                            <option value="D/Kudu">D/Kudu</option>
                            <option value="Kumbotso">Kumbotso</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Personnel Information -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-medium mb-3">Personnel Information</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="survey_by" class="block text-sm font-medium text-gray-700">Survey By <span class="text-red-600">*</span></label>
                            <input id="survey_by" name="survey_by" type="text" value="{{ old('survey_by') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="survey_by_date" class="block text-sm font-medium text-gray-700">Survey Date <span class="text-red-600">*</span></label>
                            <input id="survey_by_date" name="survey_by_date" type="date" value="{{ old('survey_by_date') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="drawn_by" class="block text-sm font-medium text-gray-700">Drawn By <span class="text-red-600">*</span></label>
                            <input id="drawn_by" name="drawn_by" type="text" value="{{ old('drawn_by') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="drawn_by_date" class="block text-sm font-medium text-gray-700">Drawn Date <span class="text-red-600">*</span></label>
                            <input id="drawn_by_date" name="drawn_by_date" type="date" value="{{ old('drawn_by_date') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="checked_by" class="block text-sm font-medium text-gray-700">Checked By <span class="text-red-600">*</span></label>
                            <input id="checked_by" name="checked_by" type="text" value="{{ old('checked_by') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="checked_by_date" class="block text-sm font-medium text-gray-700">Checked Date <span class="text-red-600">*</span></label>
                            <input id="checked_by_date" name="checked_by_date" type="date" value="{{ old('checked_by_date') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3">
                        <div>
                            <label for="approved_by" class="block text-sm font-medium text-gray-700">Approved By <span class="text-red-600">*</span></label>
                            <input id="approved_by" name="approved_by" type="text" value="{{ old('approved_by') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                            <label for="approved_by_date" class="block text-sm font-medium text-gray-700">Approved Date <span class="text-red-600">*</span></label>
                            <input id="approved_by_date" name="approved_by_date" type="date" value="{{ old('approved_by_date') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="window.history.back()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    @if(request()->query('is') == 'primary')
                        <button type="button" id="viewSurveyPlanBtn" onclick="viewSurveyPlan()" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            View Survey Plan
                        </button>
                        <button type="submit" id="save-survey-btn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200">
                            Save Survey
                        </button>
                    @else
                        <button type="button" id="uploadSurveyPlanBtn" onclick="toggleSurveyPlanSection()" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Upload Survey Plan
                        </button>
                    @endif
                </div>
                
                @if(request()->query('is') != 'primary')
                    <!-- Survey Plan Upload Section (only for secondary/unit surveys) -->
                    <div id="surveyPlanSection" class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200 hidden">
                        <h4 class="text-lg font-semibold mb-4 text-blue-800 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Survey Plan Upload <span class="text-red-600">*</span>
                        </h4>
                        <div class="space-y-4">
                            <div class="relative">
                                <input type="file" id="surveyPlan" name="survey_plan_path" accept=".pdf,.jpg,.jpeg,.png,.dwg,.dxf" 
                                       class="hidden" required onchange="handleSurveyPlanUpload(this)">
                                <label for="surveyPlan" class="flex flex-col items-center justify-center w-full h-32 border-2 border-blue-300 border-dashed rounded-lg cursor-pointer bg-blue-50 hover:bg-blue-100 transition-colors duration-200">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-4 text-blue-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                        </svg>
                                        <p class="mb-2 text-sm text-blue-600"><span class="font-semibold">Click to upload</span> survey plan</p>
                                        <p class="text-xs text-blue-500">All file types accepted (MAX. 10MB)</p>
                                    </div>
                                </label>
                            </div>
                            
                            <!-- File Preview Area -->
                            <div id="surveyPlanPreview" class="hidden">
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="text-sm font-medium text-gray-700">Survey Plan Preview</h5>
                                        <button type="button" onclick="removeSurveyPlan()" class="text-red-600 hover:text-red-800 text-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <div id="previewContent" class="text-center">
                                        <!-- Preview content will be inserted here -->
                                    </div>
                                    <div id="fileInfo" class="mt-3 text-xs text-gray-500">
                                        <!-- File info will be inserted here -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button (appears after survey plan upload) -->
                            <div class="flex justify-end space-x-3 mt-4">
                                <button type="submit" id="save-survey-btn-secondary" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200">
                                    Save Survey
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- Survey Plan View Modal (for primary surveys) -->
                @if(request()->query('is') == 'primary')
                    <div id="surveyPlanModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-900">Survey Plan</h3>
                                <button type="button" onclick="closeSurveyPlanModal()" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            <div id="surveyPlanContent" class="min-h-96">
                                <!-- Survey plan content will be loaded here -->
                                <div class="flex items-center justify-center h-96">
                                    <div class="text-gray-500">
                                        <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <p>Select a file number to view survey plan</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
 
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all form inputs and disable them initially
    const formInputs = document.querySelectorAll('#update-survey-form input:not([type="hidden"]):not([type="submit"])');
    
    const filenoSelect = document.getElementById('fileno-select');
    const saveSurveyBtn = document.getElementById('save-survey-btn');
    const applicationInfo = document.getElementById('application-info');
    
    // IDs of dropdowns/fields to control
    const controlledFields = [
        'Imperial_Sheet',
        'Imperial_Sheet_No',
        'Metric_Sheet_No',
        'Metric_Sheet_Index',
        'lga_name'
    ];
    let selectedApplication = null;
    const isSecondary = '{{ request()->query('is') }}' === 'secondary';
    
    // Disable all form inputs initially
    formInputs.forEach(input => {
        input.disabled = true;
    });
    // Explicitly disable the controlled dropdowns/fields
    controlledFields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = true;
    });

    // Initialize Select2
    $(filenoSelect).select2({
        placeholder: "Search for a file number...",
        allowClear: true,
        minimumInputLength: 0, // Changed from 2 to 0 to allow initial data load
        ajax: {
            url: '{{ route('attribution.search-fileno') }}',
            dataType: 'json',
            delay: 250,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: function(params) {
                return {
                    fileno: params.term || '', // Handle empty term for initial load
                    type: isSecondary ? 'secondary' : 'primary',
                    initial: params.term ? false : true // Flag for initial load
                };
            },
            processResults: function(data, params) {
                // Transform the data into Select2 format
                let results = [];
                
                if (data.success && data.application) {
                    // Single application result
                    results.push({
                        id: data.application.id,
                        text: data.application.fileno,
                        application: data.application
                    });
                } else if (data.success && data.applications) {
                    // Multiple applications result
                    results = data.applications.map(app => {
                        return {
                            id: app.id,
                            text: app.fileno + (app.applicant_type === 'individual' ? 
                                   ' - ' + app.first_name + ' ' + app.surname : 
                                   app.applicant_type === 'corporate' ? 
                                   ' - ' + app.corporate_name : ''),
                            application: app
                        };
                    });
                }
                
                return {
                    results: results,
                    pagination: {
                        more: data.pagination && data.pagination.more
                    }
                };
            },
            cache: true
        }
    });

    // Trigger initial data load when dropdown is opened for the first time
    $(filenoSelect).on('select2:open', function() {
        // Only load data if the dropdown is empty
        if (!$(filenoSelect).data('initial-load-done')) {
            const $search = $('.select2-search__field');
            $search.val(''); // Ensure empty search
            $search.trigger('input'); // Trigger search with empty string
            $(filenoSelect).data('initial-load-done', true); // Mark as done
        }
    });

    // Handle select change
    $(filenoSelect).on('select2:select', function(e) {
        const data = e.params.data;
        selectedApplication = data.application;
        
        if (selectedApplication) {
            // Set the fileno field - check if smart selector exists (for primary surveys)
            const filenoInput = document.getElementById('fileno');
            if (filenoInput) {
                filenoInput.value = selectedApplication.fileno || '';
            }
            
            // If smart selector exists, also call its handler
            if (typeof window.handleDropdownSelection === 'function') {
                window.handleDropdownSelection(selectedApplication);
            }
            
            // Populate hidden fields based on survey type
            if (isSecondary) {
                // For secondary survey, use the sub_application_id only
                document.getElementById('sub_application_id').value = selectedApplication.id;
                document.getElementById('application_id').value = '';
                selectedApplication.isSecondary = true;
                
                // Auto-populate unit information fields
                populateUnitInformation(selectedApplication);
            } else {
                // For primary survey, use the application_id only
                document.getElementById('application_id').value = selectedApplication.id;
                document.getElementById('sub_application_id').value = '';
                selectedApplication.isSecondary = false;
            }
            
            // Enable all form inputs
            formInputs.forEach(input => {
                input.disabled = false;
            });
            // Explicitly enable the controlled dropdowns/fields
            controlledFields.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.disabled = false;
            });
            
            // Enable save button
            if (saveSurveyBtn) {
                saveSurveyBtn.disabled = false;
            }
            
            // Enable view survey plan button for primary surveys
            if (!isSecondary) {
                const viewSurveyPlanBtn = document.getElementById('viewSurveyPlanBtn');
                if (viewSurveyPlanBtn) {
                    viewSurveyPlanBtn.disabled = false;
                    viewSurveyPlanBtn.setAttribute('data-application-id', selectedApplication.id);
                    viewSurveyPlanBtn.setAttribute('data-file-number', selectedApplication.fileno);
                }
            }
            
            // Populate the scheme_no field for secondary surveys if available
            if (isSecondary && selectedApplication.scheme_no) {
                const schemeNoInput = document.getElementById('scheme_no');
                if (schemeNoInput) {
                    schemeNoInput.value = selectedApplication.scheme_no;
                }
            }
            
            // Render application header
            renderApplicationHeader(selectedApplication);
            
            // Show success message
            Swal.fire({
                title: 'Application Selected',
                text: 'The form has been unlocked. You can now enter survey details.',
                icon: 'success',
                confirmButtonText: 'Continue'
            });
        }
    });

    // Handle clear event
    $(filenoSelect).on('select2:clear', function() {
        // Disable all form inputs
        formInputs.forEach(input => {
            input.disabled = true;
        });
        // Explicitly disable the controlled dropdowns/fields
        controlledFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = true;
        });
        
        // Disable save button
        saveSurveyBtn.disabled = true;
        
        // Hide application info
        applicationInfo.classList.add('hidden');
        
        // Clear hidden fields
        document.getElementById('application_id').value = '';
        document.getElementById('sub_application_id').value = '';
        
        selectedApplication = null;
    });
    
    // Function to populate unit information fields
    function populateUnitInformation(application) {
        // Populate scheme number
        if (application.scheme_no) {
            const schemeNoInput = document.getElementById('scheme_no');
            if (schemeNoInput) {
                schemeNoInput.value = application.scheme_no;
            }
        }
        
        // Populate floor/section number
        if (application.floor_number) {
            const floorInput = document.getElementById('floor_number');
            if (floorInput) {
                floorInput.value = application.floor_number;
            }
        }
        
     
        
        // Populate unit number
        if (application.unit_number) {
            const unitNoInput = document.getElementById('unit_number');
            if (unitNoInput) {
                unitNoInput.value = application.unit_number;
            }
        }
        
        // Populate land use
        if (application.land_use) {
            const landUseInput = document.getElementById('landuse');
            if (landUseInput) {
                landUseInput.value = application.land_use;
            }
        }
        
        // Populate app_id (application ID)
        if (application.app_id) {
            const appIdInput = document.getElementById('app_id');
            if (appIdInput) {
                appIdInput.value = application.app_id;
            }
        }
        
        // Populate unit_id
        if (application.unit_id) {
            const unitIdInput = document.getElementById('unit_id');
            if (unitIdInput) {
                unitIdInput.value = application.unit_id;
            }
        }
        
        // Populate PrimarysurveyId
        if (application.primary_fileno) {
            const primarySurveyInput = document.getElementById('PrimarysurveyId');
            if (primarySurveyInput) {
                primarySurveyInput.value = application.primary_fileno;
            }
        }
        
        // Populate STFileNo
        if (application.fileno) {
            const stFileNoInput = document.getElementById('STFileNo');
            if (stFileNoInput) {
                stFileNoInput.value = application.fileno;
            }
        }
    }
    
    function renderApplicationHeader(application) {
        // Create the header HTML
        let headerHTML = `
        <div class="flex flex-col md:flex-row items-center justify-between mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm">
            <div class="flex-1 mb-4 md:mb-0">
                <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                    <i data-lucide="home" class="w-5 h-5 text-blue-500"></i>
                    ${application.land_use} Property
                </h3>
                <div class="flex flex-wrap gap-2 mt-2 text-xs text-gray-500">
                    <span class="inline-flex items-center gap-1">
                        <i data-lucide="hash" class="w-4 h-4"></i>
                        <span class="font-medium text-gray-700">
                            ${application.isSecondary ? 'Mother FileNo: ' + (application.primary_fileno || 'N/A') : ''}
                        </span>
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <i data-lucide="folder" class="w-4 h-4"></i>
                        <span class="font-medium text-gray-700">
                            ${application.isSecondary ? 'ST FileNo: ' + (application.fileno || 'N/A') : 'FileNo: ' + (application.fileno || 'N/A')}
                        </span>
                    </span>
                    ${application.isSecondary && application.scheme_no ? `
                    <span class="inline-flex items-center gap-1">
                        <i data-lucide="layout" class="w-4 h-4"></i>
                        <span class="font-medium text-gray-700">
                            Scheme No: ${application.scheme_no}
                        </span>
                    </span>
                    ` : ''}
                </div>
            </div>
            <div class="flex-1 text-right">
                <h3 class="text-base font-semibold text-gray-800">
                    ${getApplicantName(application)}
                </h3>
                <span class="inline-flex items-center px-3 py-1 mt-2 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                    <i data-lucide="map-pin" class="w-4 h-4 mr-1"></i>
                    ${application.land_use}
                </span>
            </div>
        </div>`;
        
        // Set the HTML to the application info div
        applicationInfo.innerHTML = headerHTML;
        applicationInfo.classList.remove('hidden');
        
        // Initialize any SVG icons
        if (window.lucide) {
            lucide.createIcons();
        }
    }
    
    function getApplicantName(application) {
        if (application.applicant_type === 'individual') {
            return `${application.applicant_title} ${application.first_name} ${application.surname}`;
        } else if (application.applicant_type === 'corporate') {
            return application.corporate_name;
        } else if (application.applicant_type === 'multiple') {
            let owners = [];
            try {
                owners = JSON.parse(application.multiple_owners_names);
                return owners[0] + (owners.length > 1 ? 
                    ` <span onclick="showAllOwners(${JSON.stringify(owners)})" class="cursor-pointer text-blue-600 hover:underline">+ ${owners.length - 1} others</span>` : 
                    '');
            } catch (e) {
                return 'Multiple Owners';
            }
        }
        return 'Applicant';
    }

    // Initialize Primary Survey Select2 (only for secondary/unit surveys)
    if (isSecondary) {
        const primarySurveySelect = document.getElementById('primary-survey-select');
        
        if (primarySurveySelect) {
            $(primarySurveySelect).select2({
                placeholder: "Search for a Primary Survey FileNo...",
                allowClear: true,
                minimumInputLength: 0,
                ajax: {
                    url: '{{ route('attribution.fetch-primary-surveys') }}',
                    dataType: 'json',
                    delay: 250,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    data: function(params) {
                        return {
                            search: params.term || '',
                            initial: params.term ? false : true
                        };
                    },
                    processResults: function(data, params) {
                        let results = [];
                        
                        // Debug logging
                        console.log('Primary Survey Data:', data);
                        
                        if (data.success && data.surveys && data.surveys.length > 0) {
                            results = data.surveys.map(survey => {
                                // Create a more descriptive text for each survey option
                                let displayText = survey.fileno || 'No File No';
                                
                                // Add survey type if available
                                if (survey.survey_type) {
                                    displayText += ' | ' + survey.survey_type;
                                }
                                
                                // Add layout and location if available
                                if (survey.layout_name) {
                                    displayText += ' | ' + survey.layout_name;
                                }
                                
                                // Add plot and block info
                                displayText += ' | Plot: ' + (survey.plot_no || 'N/A');
                                displayText += ' | Block: ' + (survey.block_no || 'N/A');
                                
                                return {
                                    id: survey.ID,
                                    text: displayText,
                                    survey: survey
                                };
                            });
                        } else {
                            console.warn('No primary surveys found or data structure issue:', data);
                        }
                        
                        return {
                            results: results,
                            pagination: {
                                more: data.pagination && data.pagination.more
                            }
                        };
                    },
                    cache: true
                }
            });
            
            // Trigger initial data load when dropdown is opened
            $(primarySurveySelect).on('select2:open', function() {
                if (!$(primarySurveySelect).data('initial-load-done')) {
                    const $search = $('.select2-search__field');
                    $search.val('');
                    $search.trigger('input');
                    $(primarySurveySelect).data('initial-load-done', true);
                }
            });
            
            // Handle Primary Survey selection
            $(primarySurveySelect).on('select2:select', function(e) {
                const data = e.params.data;
                const surveyId = data.id;
                
                // Fetch detailed survey data
                fetch(`{{ url('attribution/primary-survey-details') }}/${surveyId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.survey) {
                            // Auto-populate form fields from Primary Survey
                            populateFromPrimarySurvey(data.survey);
                            
                            // Show success message as a toast notification
                            Swal.fire({
                                title: 'Primary Survey Selected',
                                text: 'Form fields have been populated based on the selected Primary Survey.',
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching survey details:', error);
                    });
            });
            
            // Handle Primary Survey clear
            $(primarySurveySelect).on('select2:clear', function() {
                // Clear Primary Survey related fields
                clearPrimarySurveyFields();
            });
        }
    }

    // Function to populate form fields from Primary Survey
    function populateFromPrimarySurvey(survey) {
        // Set Primary Survey ID
        const primarySurveyIdInput = document.getElementById('PrimarysurveyId');
        if (primarySurveyIdInput) {
            primarySurveyIdInput.value = survey.fileno || '';
        }
        
        // Populate common fields
        if (survey.scheme_no) {
            const schemeNoInput = document.getElementById('scheme_no');
            if (schemeNoInput) {
                schemeNoInput.value = survey.scheme_no;
            }
        }
        
        // Populate control beacon information
        if (survey.beacon_control_name) {
            const beaconControlNameInput = document.getElementById('beacon_control_name');
            if (beaconControlNameInput) {
                beaconControlNameInput.value = survey.beacon_control_name;
            }
        }
        
        if (survey.Control_Beacon_Coordinate_X) {
            const beaconXInput = document.getElementById('Control_Beacon_Coordinate_X');
            if (beaconXInput) {
                beaconXInput.value = survey.Control_Beacon_Coordinate_X;
            }
        }
        
        if (survey.Control_Beacon_Coordinate_Y) {
            const beaconYInput = document.getElementById('Control_Beacon_Coordinate_Y');
            if (beaconYInput) {
                beaconYInput.value = survey.Control_Beacon_Coordinate_Y;
            }
        }
        
        // Populate sheet information
        if (survey.Metric_Sheet_Index) {
            const metricSheetIndexInput = document.getElementById('Metric_Sheet_Index');
            if (metricSheetIndexInput) {
                metricSheetIndexInput.value = survey.Metric_Sheet_Index;
            }
        }
        
        if (survey.Metric_Sheet_No) {
            const metricSheetNoInput = document.getElementById('Metric_Sheet_No');
            if (metricSheetNoInput) {
                metricSheetNoInput.value = survey.Metric_Sheet_No;
            }
        }
        
        if (survey.Imperial_Sheet) {
            const imperialSheetInput = document.getElementById('Imperial_Sheet');
            if (imperialSheetInput) {
                imperialSheetInput.value = survey.Imperial_Sheet;
            }
        }
        
        if (survey.Imperial_Sheet_No) {
            const imperialSheetNoInput = document.getElementById('Imperial_Sheet_No');
            if (imperialSheetNoInput) {
                imperialSheetNoInput.value = survey.Imperial_Sheet_No;
            }
        }
        
        // Populate location information
        if (survey.layout_name) {
            const layoutNameInput = document.getElementById('layout_name');
            if (layoutNameInput) {
                layoutNameInput.value = survey.layout_name;
            }
        }
        
        if (survey.district_name) {
            const districtNameInput = document.getElementById('district_name');
            if (districtNameInput) {
                districtNameInput.value = survey.district_name;
            }
        }
        
        if (survey.lga_name) {
            const lgaNameInput = document.getElementById('lga_name');
            if (lgaNameInput) {
                lgaNameInput.value = survey.lga_name;
            }
        }
        
        // Also populate the property identification fields
        if (survey.plot_no) {
            const plotNoInput = document.getElementById('plot_no');
            if (plotNoInput) {
                plotNoInput.value = survey.plot_no;
                plotNoInput.disabled = false;
            }
        }
        
        if (survey.block_no) {
            const blockNoInput = document.getElementById('block_no');
            if (blockNoInput) {
                blockNoInput.value = survey.block_no;
                blockNoInput.disabled = false;
            }
        }
        
        // Populate Personnel Information
        if (survey.survey_by) {
            const surveyByInput = document.getElementById('survey_by');
            if (surveyByInput) {
                surveyByInput.value = survey.survey_by;
            }
        }
        
        if (survey.survey_by_date) {
            const surveyByDateInput = document.getElementById('survey_by_date');
            if (surveyByDateInput) {
                surveyByDateInput.value = survey.survey_by_date;
            }
        }
        
        if (survey.drawn_by) {
            const drawnByInput = document.getElementById('drawn_by');
            if (drawnByInput) {
                drawnByInput.value = survey.drawn_by;
            }
        }
        
        if (survey.drawn_by_date) {
            const drawnByDateInput = document.getElementById('drawn_by_date');
            if (drawnByDateInput) {
                drawnByDateInput.value = survey.drawn_by_date;
            }
        }
        
        if (survey.checked_by) {
            const checkedByInput = document.getElementById('checked_by');
            if (checkedByInput) {
                checkedByInput.value = survey.checked_by;
            }
        }
        
        if (survey.checked_by_date) {
            const checkedByDateInput = document.getElementById('checked_by_date');
            if (checkedByDateInput) {
                checkedByDateInput.value = survey.checked_by_date;
            }
        }
        
        if (survey.approved_by) {
            const approvedByInput = document.getElementById('approved_by');
            if (approvedByInput) {
                approvedByInput.value = survey.approved_by;
            }
        }
        
        if (survey.approved_by_date) {
            const approvedByDateInput = document.getElementById('approved_by_date');
            if (approvedByDateInput) {
                approvedByDateInput.value = survey.approved_by_date;
            }
        }

        // Populate Approved Plan No
        if (survey.approved_plan_no) {
            const approvedPlanNoInput = document.getElementById('approved_plan_no');
            if (approvedPlanNoInput) {
                approvedPlanNoInput.value = survey.approved_plan_no;
            }
        }
        
        // Populate TP Plan No
        if (survey.tp_plan_no) {
            const tpPlanNoInput = document.getElementById('tp_plan_no');
            if (tpPlanNoInput) {
                tpPlanNoInput.value = survey.tp_plan_no;
            }
        }
    }
    
    // Function to clear Primary Survey related fields
    function clearPrimarySurveyFields() {
        const primarySurveyIdInput = document.getElementById('PrimarysurveyId');
        if (primarySurveyIdInput) {
            primarySurveyIdInput.value = '';
        }
        
        // Note: We're not clearing all fields here to avoid overriding data that might have been
        // entered by the user or populated from other sources like the subapplication
    }
});

// Function to show all owners in a modal (accessible globally)
function showAllOwners(owners) {
    let ownersList = '';
    owners.forEach((owner, index) => {
        ownersList += `<div class="py-2 px-4 ${index % 2 === 0 ? 'bg-gray-50' : 'bg-white'} rounded">
                          <div class="flex items-center">
                              <span class="font-medium text-gray-700">${index + 1}.</span>
                              <span class="ml-2">${owner}</span>
                          </div>
                       </div>`;
    });

    Swal.fire({
        title: 'All Property Owners',
        html: `<div class="max-h-60 overflow-y-auto mt-4 divide-y divide-gray-200">${ownersList}</div>`,
        width: '500px',
        showCloseButton: true,
        showConfirmButton: false,
        focusConfirm: false
    });
}

// Survey Plan Upload and Form Validation
let surveyPlanUploaded = false;

// Toggle Survey Plan Section
function toggleSurveyPlanSection() {
    const section = document.getElementById('surveyPlanSection');
    const button = document.getElementById('uploadSurveyPlanBtn');
    
    if (section.classList.contains('hidden')) {
        section.classList.remove('hidden');
        // Hide the upload button once section is opened
        button.style.display = 'none';
        // Scroll to the section
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Handle survey plan upload
function handleSurveyPlanUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file size (10MB max)
    const maxSize = 10 * 1024 * 1024; // 10MB in bytes
    if (file.size > maxSize) {
        alert('File size must be less than 10MB');
        input.value = '';
        return;
    }
    
    // File type validation removed - all file types are now allowed
    
    surveyPlanUploaded = true;
    showSurveyPlanPreview(file);
    validateSurveyForm();
}

// Show survey plan preview
function showSurveyPlanPreview(file) {
    const previewContainer = document.getElementById('surveyPlanPreview');
    const previewContent = document.getElementById('previewContent');
    const fileInfo = document.getElementById('fileInfo');
    
    previewContainer.classList.remove('hidden');
    
    // File info
    const fileSize = (file.size / 1024 / 1024).toFixed(2);
    fileInfo.innerHTML = `
        <div class="flex items-center space-x-4">
            <span><strong>File:</strong> ${file.name}</span>
            <span><strong>Size:</strong> ${fileSize} MB</span>
            <span><strong>Type:</strong> ${file.type || 'Unknown'}</span>
        </div>
    `;
    
    // Preview content based on file type
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    if (['jpg', 'jpeg', 'png'].includes(fileExtension)) {
        // Image preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContent.innerHTML = `
                <img src="${e.target.result}" alt="Survey Plan Preview" 
                     class="max-w-full max-h-64 mx-auto rounded-lg border border-gray-200">
            `;
        };
        reader.readAsDataURL(file);
    } else if (fileExtension === 'pdf') {
        // PDF preview
        previewContent.innerHTML = `
            <div class="flex flex-col items-center space-y-2">
                <svg class="w-16 h-16 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-sm text-gray-600">PDF Document</p>
                <p class="text-xs text-gray-500">Preview not available</p>
            </div>
        `;
    } else {
        // Other file types (DWG, DXF)
        previewContent.innerHTML = `
            <div class="flex flex-col items-center space-y-2">
                <svg class="w-16 h-16 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-sm text-gray-600">${fileExtension.toUpperCase()} File</p>
                <p class="text-xs text-gray-500">Preview not available</p>
            </div>
        `;
    }
}

// Remove survey plan
function removeSurveyPlan() {
    const input = document.getElementById('surveyPlan');
    const previewContainer = document.getElementById('surveyPlanPreview');
    
    input.value = '';
    previewContainer.classList.add('hidden');
    surveyPlanUploaded = false;
    validateSurveyForm();
}

// Form validation for survey form
function validateSurveyForm() {
    const form = document.getElementById('update-survey-form');
    const saveButton = document.getElementById('save-survey-btn');
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    const surveyPlanInput = document.getElementById('surveyPlan');
    
    let allFieldsFilled = true;
    
    // Check all required fields
    requiredFields.forEach(field => {
        if (!field.disabled && !field.value.trim()) {
            allFieldsFilled = false;
        }
    });
    
    // Check if application/file number is selected
    const hasFileNumber = selectedApplication || 
                         document.getElementById('fileno').value.trim() || 
                         document.getElementById('fileno-select').value.trim();
    
    // Validate survey plan file
    const hasSurveyPlan = surveyPlanInput && surveyPlanInput.files && surveyPlanInput.files.length > 0;
    
    // Update formValid condition to use hasSurveyPlan
    const formValid = allFieldsFilled && hasFileNumber && hasSurveyPlan;
    
    if (formValid) {
        saveButton.disabled = false;
        saveButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
        saveButton.classList.add('bg-green-600', 'hover:bg-green-700');
        saveButton.textContent = 'Save Survey';
    } else {
        saveButton.disabled = true;
        saveButton.classList.add('bg-gray-400', 'cursor-not-allowed');
        saveButton.classList.remove('bg-green-600', 'hover:bg-green-700');
        let message = 'Save Survey (';
        if (!allFieldsFilled) message += 'Complete all fields';
        if (!hasSurveyPlan) message += (allFieldsFilled ? '' : ' and ') + 'Upload survey plan';
        message += ')';
        saveButton.textContent = message;
    }
}

// Add event listeners for form validation on survey form
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('update-survey-form');
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    // Add event listeners to all required fields
    requiredFields.forEach(field => {
        field.addEventListener('input', validateSurveyForm);
        field.addEventListener('change', validateSurveyForm);
    });
    
    // Initial validation
    validateSurveyForm();
    
    // Add visual feedback for required fields
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (!this.disabled && !this.value.trim()) {
                this.classList.add('border-red-300', 'bg-red-50');
            } else {
                this.classList.remove('border-red-300', 'bg-red-50');
                if (this.value.trim()) {
                    this.classList.add('border-green-300', 'bg-green-50');
                }
            }
        });
        
        field.addEventListener('focus', function() {
            this.classList.remove('border-red-300', 'bg-red-50', 'border-green-300', 'bg-green-50');
        });
    });
    
    // Override form submission to check required fields
    form.addEventListener('submit', function(e) {
        if (!selectedApplication) {
            e.preventDefault();
            alert('Please select a file number before submitting the form.');
            return false;
        }
        
        const surveyPlanInput = document.getElementById('surveyPlan');
        if (!surveyPlanInput || !surveyPlanInput.files || surveyPlanInput.files.length === 0) {
            e.preventDefault();
            alert('Please upload a survey plan file before submitting.');
            return false;
        }
        
        // Show loading state
        const saveButton = document.getElementById('save-survey-btn');
        saveButton.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
        `;
        saveButton.disabled = true;
    });
});

// Survey Plan viewing functions for primary surveys
function viewSurveyPlan() {
    const viewSurveyPlanBtn = document.getElementById('viewSurveyPlanBtn');
    if (!viewSurveyPlanBtn || viewSurveyPlanBtn.disabled) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'No File Selected',
                text: 'Please select a file number first to view its survey plan.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        } else {
            alert('Please select a file number first to view its survey plan.');
        }
        return;
    }
    
    const applicationId = viewSurveyPlanBtn.getAttribute('data-application-id');
    const fileNumber = viewSurveyPlanBtn.getAttribute('data-file-number');
    
    if (!applicationId || !fileNumber) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Error',
                text: 'Unable to retrieve application information.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        } else {
            alert('Unable to retrieve application information.');
        }
        return;
    }
    
    // Show loading state
    viewSurveyPlanBtn.innerHTML = `
        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Loading...
    `;
    viewSurveyPlanBtn.disabled = true;
    
    // Fetch survey plan from mother applications
    fetch(`/api/survey-plan/${applicationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.survey_plan) {
                showSurveyPlanModal(data.survey_plan, fileNumber);
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'No Survey Plan Found',
                        text: data.message || 'No survey plan is available for this application.',
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(data.message || 'No survey plan is available for this application.');
                }
            }
        })
        .catch(error => {
            console.error('Error fetching survey plan:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to load survey plan. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Failed to load survey plan. Please try again.');
            }
        })
        .finally(() => {
            // Reset button state
            viewSurveyPlanBtn.innerHTML = `
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Survey Plan
            `;
            viewSurveyPlanBtn.disabled = false;
        });
}

function showSurveyPlanModal(surveyPlan, fileNumber) {
    const modal = document.getElementById('surveyPlanModal');
    const content = document.getElementById('surveyPlanContent');
    
    if (!modal || !content) return;
    
    // Update modal title
    const modalTitle = modal.querySelector('h3');
    if (modalTitle) {
        modalTitle.textContent = `Survey Plan - ${fileNumber}`;
    }
    
    // Clear existing content
    content.innerHTML = '';
    
    // Create survey plan content
    const surveyPlanContainer = document.createElement('div');
    surveyPlanContainer.className = 'survey-plan-container';
    
    if (surveyPlan.path) {
        const fileExtension = surveyPlan.original_name ? surveyPlan.original_name.split('.').pop().toLowerCase() : 'unknown';
        const filePath = `/storage/${surveyPlan.path}`;
        
        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
            // Image file
            surveyPlanContainer.innerHTML = `
                <div class="text-center">
                    <img src="${filePath}" alt="Survey Plan" class="max-w-full h-auto mx-auto border border-gray-300 rounded-lg shadow-sm">
                    <div class="mt-4 text-sm text-gray-600">
                        <p><strong>File:</strong> ${surveyPlan.original_name}</p>
                        <p><strong>Uploaded:</strong> ${new Date(surveyPlan.uploaded_at).toLocaleDateString()}</p>
                    </div>
                    <div class="mt-4">
                        <a href="${filePath}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Open in New Tab
                        </a>
                    </div>
                </div>
            `;
        } else if (fileExtension === 'pdf') {
            // PDF file
            surveyPlanContainer.innerHTML = `
                <div class="text-center">
                    <div class="bg-gray-100 border border-gray-300 rounded-lg p-8 mb-4">
                        <svg class="w-16 h-16 mx-auto mb-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-lg font-medium text-gray-900 mb-2">PDF Survey Plan</p>
                        <p class="text-sm text-gray-600 mb-4">${surveyPlan.original_name}</p>
                    </div>
                    <div class="text-sm text-gray-600 mb-4">
                        <p><strong>Uploaded:</strong> ${new Date(surveyPlan.uploaded_at).toLocaleDateString()}</p>
                    </div>
                    <div class="space-x-2">
                        <a href="${filePath}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Open PDF
                        </a>
                        <a href="${filePath}" download class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            `;
        } else {
            // Other file types
            surveyPlanContainer.innerHTML = `
                <div class="text-center">
                    <div class="bg-gray-100 border border-gray-300 rounded-lg p-8 mb-4">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-lg font-medium text-gray-900 mb-2">Survey Plan Document</p>
                        <p class="text-sm text-gray-600 mb-4">${surveyPlan.original_name}</p>
                    </div>
                    <div class="text-sm text-gray-600 mb-4">
                        <p><strong>Type:</strong> ${fileExtension.toUpperCase()}</p>
                        <p><strong>Uploaded:</strong> ${new Date(surveyPlan.uploaded_at).toLocaleDateString()}</p>
                    </div>
                    <div class="space-x-2">
                        <a href="${filePath}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Open File
                        </a>
                        <a href="${filePath}" download class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            `;
        }
    } else {
        surveyPlanContainer.innerHTML = `
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-500">No survey plan file available</p>
            </div>
        `;
    }
    
    content.appendChild(surveyPlanContainer);
    
    // Show modal
    modal.classList.remove('hidden');
}

function closeSurveyPlanModal() {
    const modal = document.getElementById('surveyPlanModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('surveyPlanModal');
    if (modal && event.target === modal) {
        closeSurveyPlanModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSurveyPlanModal();
    }
});
</script>
@endsection
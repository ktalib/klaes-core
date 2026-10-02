{{-- Complete Application Data Tab --}}
@php
    $applicationDataTabActive = ($activeTab ?? null) === 'planning-form';
@endphp
<div id="planning-form-tab" class="tab-content {{ $applicationDataTabActive ? 'active' : '' }}">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b bg-blue-50">
            <h3 class="text-lg font-medium text-blue-800">📋 Complete Application Data</h3>
            <p class="text-sm text-blue-600 mt-1">Fill in all required information before generating the Physical Planning Report. Fields marked with N/A need to be completed.</p>
        </div>
        
        <form id="applicationDataForm" class="p-6 space-y-6">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="application_id" value="{{ $application->id }}">
            
            <!-- Application Information Section -->
            <div class="bg-gray-50 rounded-lg p-4 border">
                <h4 class="font-semibold text-gray-800 mb-4 flex items-center">
                    <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                    Application Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            LPKN Number <span class="text-red-500">*</span>
                        </label>
                        <div class="flex space-x-2">
                            <input type="text" name="lkn_number" id="lkn_number" 
                                   class="flex-1 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   value="{{ $surveyRecord->tp_plan_no ?? 'Piece of Land' }}" 
                                   placeholder="Enter LPKN Number">
                            <button type="button" onclick="resetField('lkn_number')" 
                                    class="px-3 py-3 bg-gray-500 text-white text-xs rounded-lg hover:bg-gray-600">
                                Reset
                            </button>
                        </div>
                        <span id="lknStatus" class="text-xs text-gray-500 mt-1 block">❌</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            TP Plan Number <span class="text-red-500">*</span>
                        </label>
                        <div class="flex space-x-2">
                            <input type="text" name="tp_plan_number" id="tp_plan_number" 
                                   class="flex-1 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   value="{{ $surveyRecord->tp_plan_no ?? 'Piece of Land' }}" 
                                   placeholder="Enter TP Plan Number">
                            <button type="button" onclick="resetField('tp_plan_number')" 
                                    class="px-3 py-3 bg-gray-500 text-white text-xs rounded-lg hover:bg-gray-600">
                                Reset
                            </button>
                        </div>
                        <span id="tpStatus" class="text-xs text-gray-500 mt-1 block">❌</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Approved Plan Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="approved_plan_number" id="approved_plan_number" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="{{ $surveyRecord->approved_plan_no ?? '' }}" 
                               placeholder="Enter Approved Plan Number">
                        <span id="approvedStatus" class="text-xs text-gray-500 mt-1 block">❌</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Scheme Plan No <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="scheme_plan_number" id="scheme_plan_number" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="{{ $surveyRecord->scheme_plan_no ?? '' }}" 
                               placeholder="Enter Scheme Plan Number">
                        <span id="schemeStatus" class="text-xs text-gray-500 mt-1 block">❌</span>
                    </div>
                </div>
            </div>

            <!-- Validation Status Section -->
            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <h4 class="font-semibold text-yellow-800 mb-4 flex items-center">
                    <i data-lucide="alert-triangle" class="w-4 h-4 mr-2"></i>
                    Completion Status
                </h4>
                <div class="bg-white rounded-lg p-4 border">
                    <div id="validationStatus" class="space-y-2">
                        <div class="flex items-center text-sm">
                            <span id="lknStatus" class="w-4 h-4 mr-2">❌</span>
                            <span>LPKN Number</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span id="tpStatus" class="w-4 h-4 mr-2">❌</span>
                            <span>TP Plan Number</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span id="approvedStatus" class="w-4 h-4 mr-2">❌</span>
                            <span>Approved Plan Number</span>
                        </div>
                    </div>
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg" id="completionMessage">
                        <p class="text-sm text-red-700">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                            Complete all required fields above to unlock the Planning Recommendation Report tab.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-between items-center pt-4 border-t">
                <div class="text-sm text-gray-600">
                    <span class="text-red-500">*</span> Required fields
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="window.history.back()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 flex items-center">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
                        Back
                    </button>
                    <button type="submit" id="saveApplicationDataBtn" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                        <i data-lucide="save" class="w-4 h-4 mr-1"></i>
                        Save Application Data
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
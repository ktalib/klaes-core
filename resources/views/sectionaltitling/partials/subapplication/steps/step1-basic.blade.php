{{-- Step 1: Basic Information --}}
<div class="form-section active-tab" id="step1">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
            <button class="text-gray-500 hover:text-gray-700" onclick="window.history.back()">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
                    <h3 class="text-lg font-bold items-center">
                        @if($isSUA)
                            Standalone Unit Application (SUA)
                        @else
                            Application for Sectional Titling - Unit Application (Secondary)
                        @endif
                    </h3>
                </div>
            </div>
            <p class="text-gray-600 mt-1">
                @if($isSUA)
                    Complete the form below to submit a standalone unit application
                @else
                    Complete the form below to submit a new unit application for sectional titling
                @endif
            </p>
        </div>

        <div class="flex items-center mb-6">
            <div class="flex items-center mr-4">
                <div class="step-circle active-tab flex items-center justify-center cursor-pointer" onclick="goToStep(1)">1</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer" onclick="goToStep(2)">2</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer" onclick="goToStep(3)">3</div>
            </div>    
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer" onclick="goToStep(4)">4</div>
            </div>
            <div class="ml-4">Step 1</div>
        </div>

        <div class="mb-6">
            <div class="text-right text-sm text-gray-500">CODE: ST FORM - 2</div>
            <hr class="my-4">
            
            @if(!$isSUA)
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Main Application Reference</h2>
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Main Application ID</label>
                            <div class="flex items-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-id-card-icon lucide-id-card"><path d="M16 10h2"/><path d="M16 14h2"/><path d="M6.17 15a3 3 0 0 1 5.66 0"/><circle cx="9" cy="11" r="2"/><rect x="2" y="5" width="20" height="14" rx="2"/></svg> 
                                    {{ $motherApplication->applicationID ?? 'N/A' }}
                                </span>
                            </div>            
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Property Location</label>
                            <div class="text-sm text-gray-600">{{ $propertyLocation }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Units</label>
                            <div class="text-lg font-semibold text-blue-600">{{ $totalUnitsInMotherApp }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Units Created</label>
                            <div class="text-lg font-semibold text-green-600">{{ $totalSubApplications }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Remaining Units</label>
                            <div class="text-lg font-semibold text-orange-600">{{ $remainingUnits }}</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @include('sectionaltitling.partials.subapplication.applicant')
        </div>

        <div class="flex justify-between">
            <button type="button" class="px-4 py-2 text-gray-600 hover:text-gray-800" onclick="window.history.back()">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back
            </button>
            <button type="button" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700" onclick="goToStep(2)">
                Next Step <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
            </button>
        </div>
    </div>
</div>
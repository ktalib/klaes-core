{{-- Step 3: Documents --}}
<div class="form-section" id="step3">
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
                <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer" onclick="goToStep(1)">1</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer" onclick="goToStep(2)">2</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle active-tab flex items-center justify-center cursor-pointer" onclick="goToStep(3)">3</div>
            </div>    
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer" onclick="goToStep(4)">4</div>
            </div>
            <div class="ml-4">Step 3</div>
        </div>

        <div class="mb-6">
            <div class="text-right text-sm text-gray-500">CODE: ST FORM - 2</div>
            <hr class="my-4">
            
            @include('sectionaltitling.partials.subapplication.documents')
        </div>

        <div class="flex justify-between">
            <button type="button" class="px-4 py-2 text-gray-600 hover:text-gray-800" onclick="goToStep(2)">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Previous
            </button>
            <button type="button" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700" onclick="goToStep(4)">
                Next Step <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
            </button>
        </div>
    </div>
</div>
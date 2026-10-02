{{-- Tracking ID --}}


 <div class="bg-gray-100 px-4 py-2 rounded-md mb-6 flex justify-between items-center max-w-xs">
    <div class="text-gray-700 font-mono text-sm font-bold whitespace-nowrap">
        <i data-lucide="file-search" class="inline h-4 w-4 mr-1"></i>
        Tracking ID: <span class="text-red-600 font-bold">
            {{ $trackingId ?? 'TRK-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)) . '-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5)) }}</span>
    </div>
</div>


<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <!-- Land Use Selection (For SuA Commission - Active) -->
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-4">
            <i data-lucide="check-circle" class="inline h-4 w-4 text-green-600 mr-1"></i>
            Land Use <span class="text-red-500">*</span>
            <span class="text-xs font-normal text-gray-500">(Select for Commission)</span>
        </label>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            {{-- Residential Option --}}
            <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-green-500 hover:bg-green-50 transition-all group">
                <input type="checkbox" name="sua_selectedLandUse" class="sr-only" value="RESIDENTIAL" onchange="handleSuaLandUseChange(this)">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center group-hover:bg-green-200 transition-all">
                        <i data-lucide="home" class="w-4 h-4 text-green-600"></i>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="font-medium text-gray-900 text-sm">Residential</span>
                        <span class="text-xs text-green-600 font-semibold bg-green-100 px-2 py-1 rounded">RES</span>
                    </div>
                </div>
                <div class="absolute top-1 right-1 w-4 h-4 bg-green-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                    <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                </div>
            </label>
            
            {{-- Commercial Option --}}
            <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all group">
                <input type="checkbox" name="sua_selectedLandUse" class="sr-only" value="COMMERCIAL" onchange="handleSuaLandUseChange(this)">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-all">
                        <i data-lucide="building" class="w-4 h-4 text-blue-600"></i>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="font-medium text-gray-900 text-sm">Commercial</span>
                        <span class="text-xs text-blue-600 font-semibold bg-blue-100 px-2 py-1 rounded">COM</span>
                    </div>
                </div>
                <div class="absolute top-1 right-1 w-4 h-4 bg-blue-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                    <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                </div>
            </label>
            
            {{-- Industry Option --}}
            <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-orange-500 hover:bg-orange-50 transition-all group">
                <input type="checkbox" name="sua_selectedLandUse" class="sr-only" value="INDUSTRY" onchange="handleSuaLandUseChange(this)">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center group-hover:bg-orange-200 transition-all">
                        <i data-lucide="factory" class="w-4 h-4 text-orange-600"></i>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="font-medium text-gray-900 text-sm">Industry</span>
                        <span class="text-xs text-orange-600 font-semibold bg-orange-100 px-2 py-1 rounded">IND</span>
                    </div>
                </div>
                <div class="absolute top-1 right-1 w-4 h-4 bg-orange-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                    <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                </div>
            </label>
        </div>
        
        <input type="hidden" name="land_use_hidden" id="sua_land_use_hidden" value="">
        <p class="text-xs text-gray-500 mt-2">
            <i data-lucide="info" class="inline h-3 w-3 mr-1"></i>
            SuA applications support Residential, Commercial, and Industry land uses.
        </p>
    </div>
</div>

<!-- SuA File Numbers Section -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-blue-100 rounded-lg">
            <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">SuA File Numbers</h3>
    </div>

    <!-- File Numbers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Primary FileNo -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Primary FileNo (Auto-generated)
            </label>
            <input type="text" 
                   name="sua_primary_fileno" id="sua_primary_fileno" readonly title="SUA Primary FileNo" 
                   class="w-full p-2 border border-gray-300 rounded-md bg-blue-100 text-blue-700 cursor-not-allowed"
                   value="Auto-generated">
        </div>

        <!-- MLS FileNo -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                MLPP FileNo
            </label>
            <input type="text" 
                   name="mls_fileno" id="mls_fileno" readonly title="MLPP FileNo (Same as Primary FileNo)"
                   class="w-full p-2 border border-gray-300 rounded-md bg-blue-100 text-blue-700 cursor-not-allowed"
                   value="Auto-generated">
        </div>

        <!-- SUA FileNo -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                SUA FileNo (Auto-generated)
            </label>
            <input type="text" 
                   name="sua_fileno" id="sua_fileno" readonly title="SUA Unit FileNo"
                   class="w-full p-2 border border-gray-300 rounded-md bg-green-100 text-green-700 cursor-not-allowed"
                   value="Auto-generated">
        </div>
    </div>

    {{-- Allocation Type Section (SuA) --}}
    <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-lg p-5 shadow-sm mb-6">
        <div class="flex items-center mb-4">
            <div class="bg-emerald-500 p-2 rounded-lg mr-3">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-white"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-900">Allocation Type <span class="text-red-500">*</span></h3>
                <p class="text-xs text-gray-600">Select the type of application</p>
            </div>
        </div>
        
        <div class="space-y-3">
            <div class="flex items-center space-x-6 bg-white rounded-lg p-4 border border-emerald-100">
                {{-- Direct Allocation Option --}}
                <label class="flex items-center cursor-pointer">
                    <input type="radio" 
                           name="application_type" 
                           value="Direct Allocation" 
                           checked 
                           onchange="handleSuaApplicationTypeChange(this)"
                           class="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500">
                    <span class="ml-2 text-sm font-medium text-gray-900">Direct Allocation</span>
                </label>
                
                {{-- Conversion Option --}}
                <label class="flex items-center cursor-pointer">
                    <input type="radio" 
                           name="application_type" 
                           value="Conversion" 
                           onchange="handleSuaApplicationTypeChange(this)"
                           class="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500">
                    <span class="ml-2 text-sm font-medium text-gray-900">Conversion</span>
                </label>
            </div>
            
            <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-200">
                <div class="flex items-start">
                    <i data-lucide="info" class="w-4 h-4 text-emerald-600 mr-2 mt-0.5 flex-shrink-0"></i>
                    <p class="text-xs text-emerald-800">
                        <strong>Direct Allocation:</strong> New standalone application with immediate assignment. 
                        <strong>Conversion:</strong> Converting an existing application.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Allocation Information (SuA). Answered here, once, at commissioning:
         the Standalone Unit Application form shows these read-only and
         back-fills them when this file number is selected. --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm mb-6">
        <div class="flex items-center mb-4">
            <div class="bg-indigo-500 p-2 rounded-lg mr-3">
                <i data-lucide="landmark" class="w-4 h-4 text-white"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-900">Allocation Information</h3>
                <p class="text-xs text-gray-600">Captured once here and carried into the Standalone Unit Application</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="sua_allocation_source" class="block text-sm font-medium text-gray-700 mb-2">
                    Allocation Source <span class="text-red-500">*</span>
                </label>
                {{-- The allocating institution itself, grouped Government / Other
                     Institution. Which group the name came from is what decides the
                     Addressee list on the Confirmation Sheet, so it is posted
                     alongside the name rather than asked again there. --}}
                <select id="sua_allocation_source" name="sua_allocation_source"
                        class="w-full p-2 border border-gray-300 rounded-md"
                        onchange="handleSuaAllocationSourceChange(this)">
                    <option value="">Loading institutions…</option>
                </select>

                {{-- Shown under "Others (Specify)": the typed name is stored, and
                     added to the list so it is offered next time. --}}
                <div id="sua_allocation_source_other_wrap" class="mt-3 hidden">
                    <label for="sua_allocation_source_other" class="block text-sm font-medium text-gray-700 mb-2">
                        Specify Institution <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="sua_allocation_source_other" name="sua_allocation_source_other"
                           class="w-full p-2 border border-gray-300 rounded-md"
                           placeholder="enter the allocating institution">
                </div>

                {{-- The parcel and the plot are one and the same number, entered once
                     under Location Details. The sheet quotes it from there and only
                     varies what it calls it, so nothing is asked for here. --}}
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="sua_allocation_ref_no" class="block text-sm font-medium text-gray-700 mb-2">
                            Allocation Slip No <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="sua_allocation_ref_no" name="sua_allocation_ref_no"
                               class="w-full p-2 border border-gray-300 rounded-md uppercase"
                               placeholder="eg: ALS/2025/001"
                               oninput="this.value = this.value.toUpperCase();">
                        <p class="text-xs text-gray-500 mt-1">Printed on the SuA Confirmation Sheet.</p>
                    </div>

                    <div>
                        {{-- Recorded against the file only: unlike the slip no, this
                             is deliberately not printed on the Confirmation Sheet. --}}
                        <label for="sua_allocation_reference_no" class="block text-sm font-medium text-gray-700 mb-2">
                            Allocation Reference No
                        </label>
                        <input type="text" id="sua_allocation_reference_no" name="sua_allocation_reference_no"
                               class="w-full p-2 border border-gray-300 rounded-md uppercase"
                               placeholder="eg: ALS/2025/001"
                               oninput="this.value = this.value.toUpperCase();">
                    </div>
                </div>
            </div>

            {{-- Only LOCAL GOVERNMENT needs a second answer: which council. Every
                 other institution names itself, so this half stays hidden. --}}
            <div id="sua_allocation_entity_wrap" class="hidden">
                <label for="sua_allocation_entity" class="block text-sm font-medium text-gray-700 mb-2">
                    LGA <span class="text-red-500">*</span>
                </label>
                <select id="sua_allocation_entity" name="sua_allocation_entity"
                        class="w-full p-2 border border-gray-300 rounded-md"
                        onchange="handleSuaAllocationEntityChange(this)">
                    <option value="">Select LGA</option>
                </select>

                {{-- Shown when "Other" is picked: the typed name is what gets stored. --}}
                <div id="sua_allocation_entity_other_wrap" class="mt-3 hidden">
                    <label for="sua_allocation_entity_other" class="block text-sm font-medium text-gray-700 mb-2">
                        Specify LGA <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="sua_allocation_entity_other" name="sua_allocation_entity_other"
                           class="w-full p-2 border border-gray-300 rounded-md"
                           placeholder="enter the LGA">
                </div>

                <p class="text-xs text-gray-500 mt-2">
                    The Confirmation Sheet is addressed to this LGA.
                </p>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-2">
            <i data-lucide="info" class="inline h-3 w-3 mr-1"></i>
            SuA applications are standalone and do not require a mother application.
        </p>
    </div>

</div>

{{-- SuA Applicant Information Component --}}
@include('commission_new_st.partials.sua-applicant', ['titles' => $titles ?? []])

{{-- Location Details + Map. SuA has no parent/mother file to inherit from,
     so the location is entered manually and pinned by the user. --}}
@include('commission_new_st.partials.location-details-map', [
    'prefix' => 'sua',
    'mode' => 'manual',
    'hint' => 'Enter the property location manually — SuA files have no parent file to inherit from',
])

<!-- Commission Information Section -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex items-center gap-3 mb-6">
        <div class="p-2 bg-green-100 rounded-lg">
            <i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Commission Information</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="sua_commissioned_by" class="block text-sm font-medium text-gray-700 mb-2">
                Commissioned By <span class="text-red-500">*</span>
            </label>
            <input type="text" id="sua_commissioned_by" name="sua_commissioned_by" 
                   value="{{ Auth::user()->name }}" readonly
                   class="w-full p-3 border border-gray-300 rounded-md bg-gray-50 text-gray-700 cursor-not-allowed">
        </div>
        <div>
            <label for="sua_commissioned_date" class="block text-sm font-medium text-gray-700 mb-2">
                Commission Date <span class="text-red-500">*</span>
            </label>
            <input type="date" id="sua_commissioned_date" name="sua_commissioned_date" 
                   value="{{ date('Y-m-d') }}" readonly
                   class="w-full p-3 border border-gray-300 rounded-md bg-gray-50 text-gray-700 cursor-not-allowed">
        </div>
    </div>

    <!-- Generate Button -->
    <div class="mt-6 flex justify-center">
        <button type="button" onclick="generateSuaFileNumbers()" 
                class="px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-medium rounded-lg hover:from-blue-700 hover:to-purple-700 focus:ring-4 focus:ring-blue-300 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
            <i data-lucide="zap" class="inline-block h-5 w-5 mr-2"></i>
            Generate SuA File Numbers
        </button>
    </div>
</div>

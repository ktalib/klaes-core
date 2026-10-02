{{-- Tracking ID --}}
<div class="bg-gray-100 px-4 py-2 rounded-md mb-6 flex justify-between items-center max-w-xs">
    <div class="text-gray-700 font-mono text-sm font-bold whitespace-nowrap">
        <i data-lucide="file-search" class="inline h-4 w-4 mr-1"></i>
        Tracking ID: <span class="text-red-600 font-bold">
            {{ $trackingId ?? 'TRK-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)) . '-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5)) }}</span>
    </div>
</div>
 

<!-- PuA File Number Section -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-purple-100 rounded-lg">
            <i data-lucide="users" class="h-5 w-5 text-purple-600"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">PuA File Number</h3>
    </div>

    <!-- Parent File Number Selection -->
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-3">
            Select Parent Primary File Number <span class="text-red-500">*</span>
        </label>
        
        <!-- Two Selection Dropdowns -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Land Use Filter -->
            <div>
                <label for="pua_land_use_filter" class="block text-xs font-medium text-gray-600 mb-1">
                    Filter by Land Use
                </label>
                <select id="pua_land_use_filter" 
                        class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                        onchange="loadPrimaryFileNumbers()">
                    <option value="">All Land Uses</option>
                    <option value="RES">Residential</option>
                    <option value="COM">Commercial</option>
                    <option value="WARE">Warehouse</option>
                    
                </select>
            </div>
            
            <!-- File Number Selection -->
            <div>
                <label for="pua_parent_file_number" class="block text-xs font-medium text-gray-600 mb-1">
                    Primary File Number
                </label>
                <select id="pua_parent_file_number" name="pua_parent_file_number"
                        class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                        onchange="handleParentFileNumberChange(this.value)">
                    <option value="">Select a PRIMARY file number...</option>
                    <!-- We'll populate this dynamically from the database -->
                </select>
            </div>
        </div>
        
        <!-- Loading and Search Info -->
        <div class="mt-2 flex items-center justify-between">
            <p class="text-xs text-gray-500">
                <i data-lucide="info" class="inline h-3 w-3 mr-1"></i>
                Only PRIMARY file numbers with status USED are available
            </p>
            <div id="pua_loading_indicator" class="hidden">
                <span class="text-xs text-blue-500">
                    <i data-lucide="loader-2" class="inline h-3 w-3 mr-1 animate-spin"></i>
                    Loading...
                </span>
            </div>
        </div>
        
        <!-- Selected Parent Details -->
        <div id="pua_parent_details" class="hidden mt-3 p-3 bg-blue-50 rounded-md">
            <h4 class="text-sm font-medium text-blue-800 mb-2">Selected Parent Details:</h4>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div><strong>File Number:</strong> <span id="parent_file_display">-</span></div>
                <div><strong>Land Use:</strong> <span id="parent_land_use_display">-</span></div>
                <div><strong>Applicant:</strong> <span id="parent_applicant_display">-</span></div>
                <div><strong>Status:</strong> <span id="parent_status_display">-</span></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- NP FileNo (NPFN) -->
        <div>
            <label for="pua_np_fileno" class="block text-sm font-medium text-gray-700 mb-2">
                NP FileNo (NPFN) (Auto-populated from Parent)
            </label>
            <input type="text" 
                   name="pua_np_fileno" id="pua_np_fileno" readonly title="NP FileNo (NPFN)" 
                   class="w-full p-3 border border-gray-300 rounded-md bg-blue-100 text-blue-700 cursor-not-allowed"
                   value="Select parent file number first">
        </div>

        <!-- Unit FileNo -->
        <div>
            <label for="pua_unit_fileno" class="block text-sm font-medium text-gray-700 mb-2">
                Unit FileNo (Auto-generated)
            </label>
            <input type="text" 
                   name="pua_unit_fileno" id="pua_unit_fileno" readonly title="Unit FileNo"
                   class="w-full p-3 border border-gray-300 rounded-md bg-green-100 text-green-700 cursor-not-allowed"
                   value="Will be generated automatically">
        </div>
    </div>

    {{-- Allocation Type Section (PuA - Inherited from Parent) --}}
    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-6 shadow-sm mt-6">
        <div class="flex items-center mb-4">
            <div class="bg-purple-500 p-2 rounded-lg mr-3">
                <i data-lucide="clipboard-list" class="w-5 h-5 text-white"></i>
            </div>
            <div class="flex-1">
                <h4 class="text-base font-semibold text-gray-900">Allocation Type</h4>
                <p class="text-sm text-gray-600">Inherited from parent PRIMARY application</p>
            </div>
            <div class="bg-purple-600 px-3 py-1 rounded-full shadow-sm">
                <span class="text-white text-xs font-medium">INHERITED</span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg border border-purple-100 p-4">
            <label for="pua_application_type" class="block text-sm font-medium text-gray-700 mb-2">
                Allocation Type <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   name="application_type" 
                   id="pua_application_type" 
                   readonly 
                   class="w-full p-3 border border-gray-300 rounded-md bg-purple-50 text-purple-800 font-medium cursor-not-allowed"
                   value="Select parent to inherit type"
                   title="Allocation Type - Inherited from parent">
        </div>
        
        <div class="mt-3 p-3 bg-purple-50 rounded-lg border border-purple-200">
            <div class="flex items-start">
                <i data-lucide="info" class="w-4 h-4 text-purple-600 mr-2 mt-0.5 flex-shrink-0"></i>
                <p class="text-xs text-purple-800">
                    The Allocation Type is automatically inherited from the parent PRIMARY file number and cannot be changed.
                </p>
            </div>
        </div>
    </div>

    <!-- Land Use Selection (Auto-selected from Parent) -->
    <div class="mt-6">
        <label class="block text-sm font-medium text-gray-700 mb-4">
            Land Use <span class="text-red-500">*</span> <span class="text-xs text-gray-500">(Auto-selected from parent)</span>
        </label>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="pua_land_use_container">
            <!-- Commercial -->
            <label class="relative flex items-center p-3 bg-gray-100 rounded-lg border-2 border-gray-300 cursor-not-allowed opacity-60 transition-all" id="pua_land_use_commercial">
                <input type="checkbox" name="pua_land_use" class="sr-only" value="COMMERCIAL" disabled onchange="handlePuaLandUseChange(this)">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center transition-all">
                        <i data-lucide="building" class="w-3 h-3 text-blue-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-500">Commercial</span>
                </div>
            </label>

            <!-- Residential -->
            <label class="relative flex items-center p-3 bg-gray-100 rounded-lg border-2 border-gray-300 cursor-not-allowed opacity-60 transition-all" id="pua_land_use_residential">
                <input type="checkbox" name="pua_land_use" class="sr-only" value="RESIDENTIAL" disabled onchange="handlePuaLandUseChange(this)">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center transition-all">
                        <i data-lucide="home" class="w-3 h-3 text-green-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-500">Residential</span>
                </div>
            </label>

            <!-- Industrial -->
            <label class="relative flex items-center p-3 bg-gray-100 rounded-lg border-2 border-gray-300 cursor-not-allowed opacity-60 transition-all" id="pua_land_use_industrial">
                <input type="checkbox" name="pua_land_use" class="sr-only" value="INDUSTRIAL" disabled onchange="handlePuaLandUseChange(this)">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center transition-all">
                        <i data-lucide="factory" class="w-3 h-3 text-orange-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-500">Industrial</span>
                </div>
            </label>

            <!-- Mixed-Use -->
            <label class="relative flex items-center p-3 bg-gray-100 rounded-lg border-2 border-gray-300 cursor-not-allowed opacity-60 transition-all" id="pua_land_use_mixed">
                <input type="checkbox" name="pua_land_use" class="sr-only" value="MIXED" disabled onchange="handlePuaLandUseChange(this)">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center transition-all">
                        <i data-lucide="layers" class="w-3 h-3 text-purple-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-500">Mixed-Use</span>
                </div>
            </label>
        </div>
        <p class="text-xs text-gray-500 mt-2">Land use will be automatically selected based on the parent PRIMARY application</p>
    </div>
</div>

<!-- Select Existing Buyer Section (NEW) -->
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 shadow-lg mb-6" id="pua_buyer_selection_card" style="display: none;">
    <div class="flex items-center mb-4">
        <div class="flex-shrink-0 bg-blue-500 p-3 rounded-lg mr-3">
            <i data-lucide="users" class="w-6 h-6 text-white"></i>
        </div>
        <div class="flex-1">
            <h4 class="text-lg font-semibold text-gray-900">Select Existing Buyer</h4>
            <p class="text-sm text-gray-600">Choose a buyer from the selected parent application to link with this PuA file number</p>
        </div>
        <div class="bg-blue-600 px-3 py-1 rounded-full shadow-sm">
            <span class="text-white text-xs font-medium">OPTIONAL</span>
        </div>
    </div>
    
    <div class="space-y-3">
        <!-- Buyer Dropdown -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Select Buyer <span class="text-gray-500">(Optional - Links buyer to PuA file number)</span>
            </label>
            <select id="pua_buyer_select" 
                    name="pua_buyer_id" 
                    class="w-full px-4 py-3 bg-white border-2 border-blue-200 rounded-lg text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                    onchange="handlePuaBuyerSelection(this)">
                <option value="">-- No buyer selected (Manual entry) --</option>
            </select>
        </div>
        
        <!-- Selected Buyer Preview -->
        <div id="pua_selected_buyer_info" class="hidden mt-3 p-4 bg-white rounded-lg border border-blue-200 shadow-sm">
            <div class="grid grid-cols-2 gap-4 mb-3">
                <div>
                    <div class="text-sm text-blue-600 font-medium">Buyer Name:</div>
                    <div id="pua_buyer_name_display" class="text-lg font-bold text-blue-900">-</div>
                </div>
                <div>
                    <div class="text-sm text-blue-600 font-medium">Unit No:</div>
                    <div id="pua_buyer_unit_display" class="font-mono text-lg font-bold text-green-700">-</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-blue-600 font-medium">Land Use:</div>
                    <div id="pua_buyer_landuse_display" class="text-lg font-bold text-blue-700">-</div>
                </div>
                <div>
                    <div class="text-sm text-blue-600 font-medium">Unit Size:</div>
                    <div id="pua_buyer_measurement_display" class="text-base font-bold text-purple-600">-</div>
                </div>
            </div>
        </div>
        
        <div class="mt-2 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-start">
                <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
                <p class="text-xs text-blue-800">
                    <strong>Note:</strong> Selecting a buyer will link this PuA file number to the buyer record. When this file number is used in sub-applications, all buyer details (Applicant Info, Unit No, Unit Size, Land Use) will be automatically populated.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Hidden field to store selected buyer ID -->
<input type="hidden" id="pua_selected_buyer_id" name="pua_selected_buyer_id" value="">

{{-- PuA Applicant Information Component --}}
@include('commission_new_st.partials.pua-applicant', ['titles' => $titles ?? []])

{{-- Location Details + Map (backfilled from the parent PRIMARY file number) --}}
@include('commission_new_st.partials.location-details-map', [
    'prefix' => 'pua',
    'mode' => 'backfill',
    'hint' => 'Backfilled automatically from the selected parent primary file number (read-only)',
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
            <label for="pua_commissioned_by" class="block text-sm font-medium text-gray-700 mb-2">
                Commissioned By <span class="text-red-500">*</span>
            </label>
            <input type="text" id="pua_commissioned_by" name="pua_commissioned_by" 
                   value="{{ Auth::user()->name }}" readonly
                   class="w-full p-3 border border-gray-300 rounded-md bg-gray-50 text-gray-700 cursor-not-allowed">
        </div>
        <div>
            <label for="pua_commissioned_date" class="block text-sm font-medium text-gray-700 mb-2">
                Commission Date <span class="text-red-500">*</span>
            </label>
            <input type="date" id="pua_commissioned_date" name="pua_commissioned_date" 
                   value="{{ date('Y-m-d') }}" readonly
                   class="w-full p-3 border border-gray-300 rounded-md bg-gray-50 text-gray-700 cursor-not-allowed">
        </div>
    </div>

    <!-- Generate Button -->
    <div class="mt-6 flex justify-center">
        <button type="button" id="pua_generate_btn" onclick="commissionPuaFileNumber()"  
                class="px-8 py-3 bg-blue-400 text-white font-medium rounded-lg  transition-all duration-200 shadow-lg">
            <i data-lucide="zap" class="inline-block h-5 w-5 mr-2"></i>
            Generate PuA File Number
        </button>
 
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load available PRIMARY file numbers for PuA generation
    loadAvailablePrimaryFileNumbers();
});

// Load available PRIMARY file numbers from database
async function loadAvailablePrimaryFileNumbers() {
    try {
        console.log('📊 Loading available PRIMARY file numbers for PuA...');
        
        const response = await fetch('/api/st-file-numbers/primary-available', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('📊 Available PRIMARY file numbers:', data);

        if (data.success && data.data && data.data.length > 0) {
            populatePrimaryFileNumberDropdown(data.data);
        } else {
            console.log('ℹ️ No PRIMARY file numbers available for PuA generation');
            // Show a message that no file numbers are available
            const dropdown = document.getElementById('pua_parent_file_number');
            if (dropdown) {
                dropdown.innerHTML = '<option value="">No PRIMARY file numbers available</option>';
            }
        }
    } catch (error) {
        console.error('❌ Error loading PRIMARY file numbers:', error);
        // Add some test data for development
        addTestPrimaryFileNumbers();
    }
}

// Populate the dropdown with available PRIMARY file numbers
function populatePrimaryFileNumberDropdown(fileNumbers) {
    const dropdown = document.getElementById('pua_parent_file_number');
    if (!dropdown) return;

    dropdown.innerHTML = '<option value="">Select a PRIMARY file number...</option>';
    
    fileNumbers.forEach(fileNumber => {
        const option = document.createElement('option');
        option.value = fileNumber.np_fileno;
        option.textContent = `${fileNumber.np_fileno} - ${fileNumber.land_use} (${fileNumber.first_name || fileNumber.corporate_name || 'N/A'})`;
        dropdown.appendChild(option);
    });
    
    console.log('✅ Populated dropdown with', fileNumbers.length, 'PRIMARY file numbers');
}

// Add test data for development (fallback)
function addTestPrimaryFileNumbers() {
    console.log('🧪 Adding test PRIMARY file numbers for development');
    const dropdown = document.getElementById('pua_parent_file_number');
    if (!dropdown) return;

    dropdown.innerHTML = `
        <option value="">Select a PRIMARY file number...</option>
        <option value="ST-COM-2025-5">ST-COM-2025-5 - COMMERCIAL</option>
        <option value="ST-RES-2025-1">ST-RES-2025-1 - RESIDENTIAL</option>
        <option value="ST-IND-2025-1">ST-IND-2025-1 - INDUSTRIAL</option>
    `;
}
</script>

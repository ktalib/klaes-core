{{-- Capture Existing File Number Modal HTML Partial --}}
{{-- Extracted from generate_fileno/capture_existing.blade.php for reuse across modules --}}
@php
    $captureLandUses = \App\Models\LandUse::all();
@endphp

<div id="captureModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-5 mx-auto p-6 border border-gray-200 w-[800px] max-w-4xl shadow-xl rounded-lg bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h3 id="modalTitle" class="text-xl font-semibold text-gray-900">Capture Existing File</h3>
                    <p class="text-sm text-gray-500 mt-1">Enter the details of an existing file number to capture it in the system</p>
                </div>
                <button onclick="closeCaptureModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <form id="captureForm" onsubmit="submitCaptureForm(event)" class="space-y-6">
                @csrf
                <input type="hidden" id="trackingId" name="tracking_id" value="{{ $trackingId ?? '' }}">

                <!-- Generator-style top configuration blocks -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-8 items-start">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i data-lucide="info" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                                Application Type
                            </label>
                            <div class="flex items-center gap-6">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="capture_application_type" value="direct" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" checked>
                                    <span class="ml-2 text-sm font-medium text-gray-700">Direct Allocation</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="capture_application_type" value="conversion" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Conversion</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            {{-- <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">File Type</label> --}}
                            <div class="grid grid-cols-2 xl:grid-cols-3 gap-2">
                                <label class="inline-flex items-center px-2.5 py-1.5 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="quick_file_option_capture" value="temporary" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" onclick="syncCaptureFileOption('temporary')">
                                    <span class="ml-2 text-[11px] font-medium text-gray-700">Temporary</span>
                                </label>
                                <label class="inline-flex items-center px-2.5 py-1.5 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="quick_file_option_capture" value="extension" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" onclick="syncCaptureFileOption('extension')">
                                    <span class="ml-2 text-[11px] font-medium text-gray-700">Extension</span>
                                </label>
                                <label class="inline-flex items-center px-2.5 py-1.5 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="quick_file_option_capture" value="miscellaneous" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" onclick="syncCaptureFileOption('miscellaneous')">
                                    <span class="ml-2 text-[11px] font-medium text-gray-700">Miscellaneous</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="captureAllocationSourceSection" class="mt-2 pt-3 border-t border-gray-200">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">
                            <i data-lucide="filter" class="w-3 h-3 inline mr-1 text-blue-500"></i>
                            Allocation Source
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <label class="flex items-center justify-center p-2 rounded-md border border-gray-200 bg-gray-50">
                                <input type="radio" name="capture_allocation_source" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" checked>
                                <span class="ml-2 text-xs font-medium text-gray-700">Default</span>
                            </label>
                            <label class="flex items-center justify-center p-2 rounded-md border border-blue-200 bg-blue-50/60">
                                <input type="radio" name="capture_allocation_source" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="ml-2 text-xs font-semibold text-gray-700">Occupancy Permit (OP)</span>
                            </label>
                            <label class="flex items-center justify-center p-2 rounded-md border border-gray-200 bg-white">
                                <input type="radio" name="capture_allocation_source" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="ml-2 text-xs font-semibold text-gray-700">Allocation List</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <label for="fileOption" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i data-lucide="settings" class="w-4 h-4 inline mr-1"></i>
                                File Options
                            </label>
                            <select id="fileOption" name="file_option"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                onchange="updateCaptureForm(this.value)" required>
                                <option value="normal" selected>Normal File</option>
                                <option value="temporary">Temporary File</option>
                                <option value="extension">Extension</option>
                                <option value="miscellaneous">Miscellaneous</option>
                                <option value="old_mls">Old MLPP</option>
                                <option value="sltr">SLTR</option>
                                <option value="sit">SIT</option>
                            </select>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                                <div>
                                    <label for="captureFileName" class="block text-xs font-medium text-gray-600 mb-1">
                                        File Name
                                    </label>
                                    <input type="text" id="captureFileName" name="file_name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Enter file name" required>
                                </div> 
                                <div>
                                    <label for="customerType" class="block text-xs font-medium text-gray-600 mb-1">
                                        Customer Type
                                    </label>
                                    <select id="customerType" name="customer_type"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="" selected>Select Customer Type</option>
                                        <option value="Individual">Individual</option>
                                        <option value="Corporate">Corporate</option>
                                        <option value="Multiple">Multiple</option>
                                    </select>
                                </div>
                                <div id="prefixSection">
                                    <label for="prefix" class="block text-xs font-medium text-gray-600 mb-1">
                                        Prefix
                                    </label>
                                    <select id="prefix" name="prefix"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        onchange="updateCapturePreview()">
                                <option value="">Select Prefix</option>
                                <optgroup label="Standard">
                                    <option value="RES">RES - Residential</option>
                                    <option value="COM">COM - Commercial</option>
                                    <option value="IND">IND - Industrial</option>
                                    <option value="AG">AG - Agricultural</option>
                                </optgroup>
                                <optgroup label="Conversion">
                                    <option value="CON-RES">CON-RES - Conversion to Residential</option>
                                    <option value="CON-COM">CON-COM - Conversion to Commercial</option>
                                    <option value="CON-IND">CON-IND - Conversion to Industrial</option>
                                    <option value="CON-AG">CON-AG - Conversion to Agricultural</option>
                                    <option value="CON-RES-RC">CON-RES-RC - Conversion to Residential</option>
                                    <option value="CON-COM-RC">CON-COM-RC - Conversion to Commercial</option>
                                    <option value="CON-AG-RC">CON-AG-RC - Conversion to Agricultural</option>
                                </optgroup>
                                <optgroup label="RC Options">
                                    <option value="RES-RC">RES-RC</option>
                                    <option value="COM-RC">COM-RC</option>
                                    <option value="AG-RC">AG-RC</option>
                                    <option value="IND-RC">IND-RC</option>
                                </optgroup>
                                    </select>
                                </div>

                                <div>
                                    <label for="captureLandUse" class="block text-xs font-medium text-gray-600 mb-1">
                                        Land Use
                                    </label>
                                    <select id="captureLandUse" name="land_use"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                        <option value="">Select Land Use</option>
                                        @foreach($captureLandUses as $lu)
                                            @php
                                                $luName = strtoupper($lu->landuse);
                                                $luCode = '';
                                                if (str_contains($luName, 'RESIDENTIAL')) $luCode = 'RES';
                                                elseif (str_contains($luName, 'COMMERCIAL')) $luCode = 'COM';
                                                elseif (str_contains($luName, 'INDUSTRIAL')) $luCode = 'IND';
                                                elseif (str_contains($luName, 'AGRICULTURAL')) $luCode = 'AG';
                                                else $luCode = substr($luName, 0, 3);
                                            @endphp
                                            <option value="{{ $luCode }}" data-id="{{ $lu->id }}">{{ $luName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="capturePurpose" class="block text-xs font-medium text-gray-600 mb-1">
                                        Purpose
                                    </label>
                                    <select id="capturePurpose" name="purpose"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                        <option value="">Select Purpose</option>
                                    </select>
                                    <input type="hidden" id="capturePurposeId" name="purpose_id">
                                </div>

                                <div id="middlePrefixSection" class="hidden md:col-span-2">
                                    <label for="middlePrefix" class="block text-xs font-medium text-gray-600 mb-1">
                                        Middle Prefix
                                    </label>
                                    <input type="text" id="middlePrefix" name="middle_prefix"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="e.g., KN" onchange="updateCapturePreview()" value="KN">
                                </div>

                                <div>
                                    <label for="capturePhoneNo" class="block text-xs font-medium text-gray-600 mb-1">
                                        Phone No of Applicant
                                    </label>
                                    <input type="text" id="capturePhoneNo" name="phone_no"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Enter phone number">
                                </div>

                                <div class="md:col-span-2">
                                    <label for="captureAddress" class="block text-xs font-medium text-gray-600 mb-1">
                                        Address of Applicant
                                    </label>
                                    <input type="text" id="captureAddress" name="address"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Enter address">
                                </div>
                            </div>
                        </div>

                        <!-- Location Details Section -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-2 mb-3">
                                <i data-lucide="map" class="w-4 h-4 inline mr-1"></i>
                                <label class="block text-sm font-semibold text-gray-700">Location Details</label>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="capturePlotNo" class="block text-xs font-medium text-gray-600 mb-1">Plot Number</label>
                                    <input type="text" id="capturePlotNo" name="plot_no"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Enter plot number">
                                </div>
                                <div>
                                    <label for="captureTpNo" class="block text-xs font-medium text-gray-600 mb-1">TP Number</label>
                                    <input type="text" id="captureTpNo" name="tp_no"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Enter TP number">
                                </div>
                                <div>
                                    <label for="captureLocation" class="block text-xs font-medium text-gray-600 mb-1">Location</label>
                                    <input type="text" id="captureLocation" name="location"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Enter location details">
                                </div>
                                <div>
                                    <label for="captureLga" class="block text-xs font-medium text-gray-600 mb-1">LGA</label>
                                    <select id="captureLga" name="lga"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                        >
                                        <option value="">Select LGA</option>
                                        @isset($lgas)
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ $lga->name }}</option>
                                            @endforeach
                                        @endisset
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div id="extensionFileSection" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="link" class="w-4 h-4 inline mr-1"></i>
                                Select Existing MLPP File Number
                            </label>

                            <div class="flex items-center mb-3 space-x-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="extension_input_type" id="extensionDropdown" value="false" class="mr-2 text-blue-600" checked>
                                    <span class="text-sm">Select from existing</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="extension_input_type" id="extensionManual" value="true" class="mr-2 text-blue-600">
                                    <span class="text-sm">Enter manually</span>
                                </label>
                            </div>

                            <div id="extensionDropdownSection">
                                <select id="existingFileNo" name="existing_file_no"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    onchange="updateCapturePreview()">
                                    <option value="">Select existing file number...</option>
                                </select>
                            </div>

                            <div id="extensionManualInput" class="hidden">
                                <input type="text" id="extensionManualFile" name="existing_file_no_manual"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter file number manually (e.g., RES-2024-0567)"
                                    oninput="updateCapturePreview()">
                                <p class="text-xs text-gray-500 mt-1">Enter the exact file number you want to extend</p>
                            </div>
                        </div>

                        <div id="temporaryFileSection" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="clock" class="w-4 h-4 inline mr-1"></i>
                                Select Existing File for Temporary Version
                            </label>

                            <div class="flex items-center mb-3 space-x-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="temporary_input_type" id="temporaryDropdown" value="false" class="mr-2 text-blue-600" checked>
                                    <span class="text-sm">Select from existing</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="temporary_input_type" id="temporaryManual" value="true" class="mr-2 text-blue-600">
                                    <span class="text-sm">Enter manually</span>
                                </label>
                            </div>

                            <div id="temporaryDropdownSection">
                                <select id="temporaryFileNo" name="existing_file_no"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    onchange="updateCapturePreview()">
                                    <option value="">Select existing file number...</option>
                                </select>
                            </div>

                            <div id="temporaryManualInput" class="hidden">
                                <input type="text" id="temporaryManualFile" name="existing_file_no_manual"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter file number manually (e.g., RES-2024-0567)"
                                    oninput="updateCapturePreview()">
                                <p class="text-xs text-gray-500 mt-1">Enter the exact file number for temporary version</p>
                            </div>
                        </div>

                        <div id="yearSection" class="grid grid-cols-2 gap-4">
                            <div style="display: none"
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                                    Year
                                </label> 
                                <input type="number" id="year" name="year"
                                    value="{{ date('Y') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    min="1900" max="2050" onchange="updateCapturePreview()">
                            </div>
 
                            <div style="display: none">
                                <label for="serialNo" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                                    Serial Number
                                </label>
                                <input type="text" id="serialNo" name="serial_no"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter serial number" onchange="updateCapturePreview()">
                            </div>
                        </div>

                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200 shadow-sm">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="eye" class="w-4 h-4 inline mr-1"></i>
                                File Number Preview
                            </label>
                            <div id="capturePreview" class="w-full px-4 py-3 bg-white border border-blue-300 rounded-md text-lg font-mono text-center text-gray-800 font-bold shadow-sm">
                                -
                            </div>

                            <div class="bg-gray-100 px-4 py-2 rounded-md flex justify-between items-center max-w-xs mt-4">
                                <div class="text-gray-700 font-mono text-sm font-bold whitespace-nowrap">
                                    <i data-lucide="file-search" class="inline h-4 w-4 mr-1"></i>
                                    Tracking ID: <span id="trackingIdDisplay" class="text-red-500 font-bold">{{ $trackingId ?? 'Awaiting grouping match' }}</span>
                                </div>
                            </div>
                            <div id="trackingIdStatus" class="text-xs text-slate-500 mt-2"></div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-4">
                            <div>
                                <label for="commissionDate" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="calendar-check" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                                    Capture Date
                                </label>
                                <input type="text" id="commissionDate" name="commission_date"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-sm"
                                    placeholder="Auto-filled" disabled value="{{ date('Y-m-d') }}">
                            </div>

                            <div>
                                <label for="commissionedBy" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i data-lucide="user-check" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                                    Captured By
                                </label>
                                <input type="text" id="commissionedBy" name="commissioned_by"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-sm"
                                    placeholder="Auto-filled" disabled value="{{ Auth::user()->name }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeCaptureModal()"
                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center space-x-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span>Submit</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var captureLandUseSelect = document.getElementById('captureLandUse');
    var capturePurposeSelect = document.getElementById('capturePurpose');
    var capturePurposeIdInput = document.getElementById('capturePurposeId');

    if (captureLandUseSelect) {
        captureLandUseSelect.addEventListener('change', function () {
            var selectedOption = this.options[this.selectedIndex];
            var landUseId = selectedOption ? selectedOption.getAttribute('data-id') : '';

            capturePurposeSelect.innerHTML = '<option value="">Select Purpose</option>';
            if (capturePurposeIdInput) capturePurposeIdInput.value = '';

            if (!landUseId) return;

            fetch('/mls-fileno/get-dependent-data?land_use_id=' + encodeURIComponent(landUseId))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data.purposes || []).forEach(function (p) {
                        var opt = document.createElement('option');
                        opt.value = p.name;
                        opt.textContent = p.name;
                        opt.setAttribute('data-id', p.id);
                        capturePurposeSelect.appendChild(opt);
                    });
                })
                .catch(function (err) {
                    console.error('Error fetching purposes for capture modal:', err);
                });
        });
    }

    if (capturePurposeSelect) {
        capturePurposeSelect.addEventListener('change', function () {
            var selectedOption = this.options[this.selectedIndex];
            if (capturePurposeIdInput) {
                capturePurposeIdInput.value = selectedOption ? (selectedOption.getAttribute('data-id') || '') : '';
            }
        });
    }

    /**
     * Global helper: set Capture Existing land use dropdown and load purposes.
     * Accepts either a code (RES, COM) or full name (RESIDENTIAL, COMMERCIAL).
     * Returns a Promise that resolves after purposes are loaded and optionally selected.
     */
    window.setCaptureLandUseAndLoadPurposes = function (landUseValue, purposeName) {
        return new Promise(function (resolve) {
            var select = document.getElementById('captureLandUse');
            var purposeSelect = document.getElementById('capturePurpose');
            var purposeIdInput = document.getElementById('capturePurposeId');

            if (!select || !landUseValue) { resolve(); return; }

            // Try direct value match first (code like RES, COM)
            select.value = landUseValue;
            var selectedOption = select.options[select.selectedIndex];

            // If direct match failed, try matching by option text (name like RESIDENTIAL)
            if (!selectedOption || selectedOption.value !== landUseValue) {
                var upperVal = landUseValue.toUpperCase();
                for (var i = 0; i < select.options.length; i++) {
                    var optText = (select.options[i].textContent || '').toUpperCase().trim();
                    if (optText === upperVal || optText.indexOf(upperVal) !== -1 || upperVal.indexOf(optText) !== -1) {
                        select.selectedIndex = i;
                        selectedOption = select.options[i];
                        break;
                    }
                }
            }

            var landUseId = selectedOption ? selectedOption.getAttribute('data-id') : '';
            if (!landUseId) { resolve(); return; }

            purposeSelect.innerHTML = '<option value="">Select Purpose</option>';
            if (purposeIdInput) purposeIdInput.value = '';

            fetch('/mls-fileno/get-dependent-data?land_use_id=' + encodeURIComponent(landUseId))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data.purposes || []).forEach(function (p) {
                        var opt = document.createElement('option');
                        opt.value = p.name;
                        opt.textContent = p.name;
                        opt.setAttribute('data-id', p.id);
                        purposeSelect.appendChild(opt);
                    });

                    if (purposeName) {
                        for (var i = 0; i < purposeSelect.options.length; i++) {
                            if (purposeSelect.options[i].value === purposeName ||
                                purposeSelect.options[i].textContent.toUpperCase() === purposeName.toUpperCase()) {
                                purposeSelect.selectedIndex = i;
                                if (purposeIdInput) {
                                    purposeIdInput.value = purposeSelect.options[i].getAttribute('data-id') || '';
                                }
                                break;
                            }
                        }
                    }
                    resolve();
                })
                .catch(function (err) {
                    console.error('Error fetching purposes:', err);
                    resolve();
                });
        });
    };
});
</script>

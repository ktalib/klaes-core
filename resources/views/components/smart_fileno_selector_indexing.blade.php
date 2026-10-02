<!-- Smart File Number Selector Component -->
<div class="smart-fileno-selector" style="max-width: 700px; margin: 0 auto;">
    <!-- Hidden input for the main fileno field that gets submitted -->
    <input type="hidden" id="fileno" name="fileno" value="">
    
    <!-- Container to align card directly at the left edge -->
    <div class="flex justify-start -mt-4 -ml-2">
        <div class="w-1/2">
            <div class="flex items-center justify-between mb-1">
                <label for="fileno-select" class="block text-xs font-medium text-gray-700">Select File Number</label>
                <button type="button" id="toggle-manual-entry" class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Enter Fileno manually
                </button>
            </div>
            
            <!-- Dropdown Selection Mode with Select2 -->
            <div id="dropdown-mode" class="fileno-mode">
                <select id="fileno-select" class="w-full p-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs select2-fileno">
                    <option value="">Search and select file number...</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Can't find your file number? <button type="button" class="text-blue-600 hover:underline text-xs" onclick="toggleFilenoMode()">Enter it manually</button></p>
                
                <!-- Selected File Number Display (in dropdown mode) -->
                <div id="selected-fileno-display" class="hidden mt-1">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded p-2 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <div class="flex-shrink-0">
                                    <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-xs font-medium text-green-800 mb-0">Selected File Number</h3>
                                    <div class="flex items-center space-x-1">
                                        <span class="text-sm font-bold text-green-900 font-mono bg-white px-2 py-0.5 rounded border border-green-200" id="selected-fileno-text"></span>
                                        <span class="inline-flex items-center px-1 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✓ Ready</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <button type="button" id="clear-selection" class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Right side intentionally left empty (half width) -->
        <div class="w-1/2"></div>
    </div>
    
    <!-- Manual Entry Mode (full width when active) -->
    <div id="manual-mode" class="fileno-mode hidden" style="display: none;">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 w-full">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h4 class="text-lg font-semibold text-blue-800">Enter File Number Information</h4>
                </div>
                <button type="button" id="back-to-dropdown" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-white border border-blue-300 rounded-md hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to dropdown
                </button>
            </div>
            
            <!-- Include the File Number Information component -->
            <div class="bg-white rounded-lg p-4 border border-blue-100">
                @include('fileindexing.partial.file_number_info')
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="button" id="confirm-manual-entry" class="inline-flex items-center px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Use This File Number
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
.smart-fileno-selector .fileno-mode {
    transition: all 0.3s ease;
}

.smart-fileno-selector .fileno-mode.hidden {
    display: none !important;
}

/* Ensure manual mode is completely hidden by default */
.smart-fileno-selector #manual-mode {
    display: none !important;
}

.smart-fileno-selector #manual-mode:not(.hidden) {
    display: block !important;
}

/* Override any conflicting styles from the included component */
.smart-fileno-selector #manual-mode.hidden,
.smart-fileno-selector #manual-mode.hidden * {
    display: none !important;
}

/* Select2 customizations for file number selector */
.select2-fileno .select2-container {
    width: 100% !important;
}

.select2-fileno .select2-selection--single {
    height: 34px !important;
    border: 1px solid #d1d5db !important;
    border-radius: 0.375rem !important;
    padding: 4px 8px !important;
    font-size: 0.75rem !important;
}

.select2-fileno .select2-selection__rendered {
    line-height: 26px !important;
    color: #374151 !important;
    font-size: 0.75rem !important;
}

.select2-fileno .select2-selection__arrow {
    height: 32px !important;
}

.select2-dropdown {
    font-size: 0.875rem !important;
}

.select2-results__option {
    padding: 8px 12px !important;
}

.select2-results__option--highlighted {
    background-color: #dbeafe !important;
    color: #1d4ed8 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeSmartFilenoSelector();
});

function initializeSmartFilenoSelector() {
    const dropdownMode = document.getElementById('dropdown-mode');
    const manualMode = document.getElementById('manual-mode');
    const toggleManualBtn = document.getElementById('toggle-manual-entry');
    const backToDropdownBtn = document.getElementById('back-to-dropdown');
    const confirmManualBtn = document.getElementById('confirm-manual-entry');
    const clearSelectionBtn = document.getElementById('clear-selection');
    const selectedDisplay = document.getElementById('selected-fileno-display');
    const selectedText = document.getElementById('selected-fileno-text');
    const filenoSelect = document.getElementById('fileno-select');
    const filenoInput = document.getElementById('fileno'); // Main fileno hidden input
    
    // Ensure manual mode is hidden on initialization
    if (manualMode) {
        manualMode.style.display = 'none';
        manualMode.classList.add('hidden');
        // Remove required attributes from hidden fields on initialization
        disableFileNumberInputsWhenHidden();
    }
    
    // Toggle between dropdown and manual modes
    function toggleFilenoMode() {
        if (dropdownMode.classList.contains('hidden')) {
            // Switch to dropdown mode
            dropdownMode.classList.remove('hidden');
            dropdownMode.style.display = 'block';
            manualMode.classList.add('hidden');
            manualMode.style.display = 'none';
            toggleManualBtn.innerHTML = `
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Enter Fileno manually
            `;
            
            // Disable file number inputs when manual mode is hidden to prevent form validation errors
            disableFileNumberInputsWhenHidden();
        } else {
            // Switch to manual mode
            dropdownMode.classList.add('hidden');
            dropdownMode.style.display = 'none';
            manualMode.classList.remove('hidden');
            manualMode.style.display = 'block';
            toggleManualBtn.innerHTML = `
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                Use dropdown
            `;
            
            // Enable file number inputs when switching to manual mode
            enableFileNumberInputs();
        }
    }
    
    // Event listeners
    if (toggleManualBtn) toggleManualBtn.addEventListener('click', toggleFilenoMode);
    if (backToDropdownBtn) backToDropdownBtn.addEventListener('click', toggleFilenoMode);
    
    // Confirm manual entry
    if (confirmManualBtn) {
        confirmManualBtn.addEventListener('click', function() {
            const activeTabEl = document.getElementById('activeFileTab');
            if (!activeTabEl) return;
            
            const activeTab = activeTabEl.value;
            let fileNumber = '';
            
            // Get the file number based on active tab
            if (activeTab === 'mlsFNo') {
                const mlsEl = document.getElementById('mlsFNo');
                fileNumber = mlsEl ? mlsEl.value : '';
            } else if (activeTab === 'kangisFileNo') {
                const kangisEl = document.getElementById('kangisFileNo');
                fileNumber = kangisEl ? kangisEl.value : '';
            } else if (activeTab === 'NewKANGISFileno') {
                const newKangisEl = document.getElementById('NewKANGISFileno');
                fileNumber = newKangisEl ? newKangisEl.value : '';
            }
            
            if (fileNumber.trim()) {
                // Set the main fileno field
                if (filenoInput) filenoInput.value = fileNumber;
                
                // Create a mock application object for manual entry
                const manualApplication = {
                    id: 'manual_' + Date.now(),
                    fileno: fileNumber,
                    applicant_type: 'manual',
                    first_name: 'Manual',
                    surname: 'Entry',
                    isManual: true
                };
                
                // Show selected file number
                if (selectedText) selectedText.textContent = fileNumber;
                if (selectedDisplay) selectedDisplay.classList.remove('hidden');
                
                // Switch back to dropdown mode
                toggleFilenoMode();
                
                // Trigger the same logic as dropdown selection
                handleFilenoSelection(manualApplication);
                
                // Show success message
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'File Number Set',
                        text: `File number "${fileNumber}" has been set. You can now enter GIS data.`,
                        icon: 'success',
                        confirmButtonText: 'Continue'
                    });
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Invalid File Number',
                        text: 'Please enter a valid file number.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
        });
    }
    
    // Clear selection
    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', function() {
            if (selectedDisplay) selectedDisplay.classList.add('hidden');
            if (selectedText) selectedText.textContent = '';
            if (filenoInput) filenoInput.value = ''; // Clear main fileno field
            
            // Clear form and disable inputs
            clearFormAndDisableInputs();
            
            // Clear dropdown selection
            if (typeof $ !== 'undefined' && filenoSelect) {
                $(filenoSelect).val(null).trigger('change');
            }
            
            // Reset manual entry form
            resetManualEntryForm();
        });
    }
    
    // Function to handle file number selection (both dropdown and manual)
    function handleFilenoSelection(application) {
        // Store the selected application globally
        window.selectedApplication = application;
        
        // Set the main fileno field
        if (filenoInput) filenoInput.value = application.fileno;
        
        // Populate hidden fields based on survey type
        const isSecondary = '{{ request()->query('is') }}' === 'secondary';
        
        const appIdEl = document.getElementById('application_id');
        const subAppIdEl = document.getElementById('sub_application_id');
        
        if (isSecondary) {
            if (subAppIdEl) subAppIdEl.value = application.id;
            if (appIdEl) appIdEl.value = '';
            application.isSecondary = true;
            
            // Auto-populate unit information fields if available
            if (typeof populateUnitInformation === 'function') {
                populateUnitInformation(application);
            }
        } else {
            if (appIdEl) appIdEl.value = application.id;
            if (subAppIdEl) subAppIdEl.value = '';
            application.isSecondary = false;
        }
        
        // Enable all form inputs
        enableFormInputs();
        
        // Render application header if function exists
        if (typeof renderApplicationHeader === 'function') {
            renderApplicationHeader(application);
        }
    }
    
    // Function to enable form inputs
    function enableFormInputs() {
        // Try different form selectors since we're in GIS record form
        const formSelectors = [
            '#update-survey-form input:not([type="hidden"]):not([type="submit"])',
            'form input:not([type="hidden"]):not([type="submit"])',
            'input:not([type="hidden"]):not([type="submit"])'
        ];
        
        let formInputs = [];
        for (const selector of formSelectors) {
            formInputs = document.querySelectorAll(selector);
            if (formInputs.length > 0) break;
        }
        
        const controlledFields = [
            'Imperial_Sheet',
            'Imperial_Sheet_No',
            'Metric_Sheet_No',
            'Metric_Sheet_Index',
            'lga_name',
            'plotNo',
            'blockNo',
            'approvedPlanNo',
            'tpPlanNo',
            'layoutName',
            'districtName'
        ];
        
        formInputs.forEach(input => {
            if (input.id !== 'fileno') { // Don't disable the main fileno input
                input.disabled = false;
            }
        });
        
        controlledFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = false;
        });
        
        // Enable save button
        const saveButton = document.getElementById('saveButton');
        if (saveButton) saveButton.disabled = false;
    }
    
    // Function to clear form and disable inputs
    function clearFormAndDisableInputs() {
        // Try different form selectors
        const formSelectors = [
            '#update-survey-form input:not([type="hidden"]):not([type="submit"])',
            'form input:not([type="hidden"]):not([type="submit"])',
            'input:not([type="hidden"]):not([type="submit"])'
        ];
        
        let formInputs = [];
        for (const selector of formSelectors) {
            formInputs = document.querySelectorAll(selector);
            if (formInputs.length > 0) break;
        }
        
        const controlledFields = [
            'Imperial_Sheet',
            'Imperial_Sheet_No',
            'Metric_Sheet_No',
            'Metric_Sheet_Index',
            'lga_name',
            'plotNo',
            'blockNo',
            'approvedPlanNo',
            'tpPlanNo',
            'layoutName',
            'districtName'
        ];
        
        formInputs.forEach(input => {
            if (input.id !== 'fileno') { // Don't disable the main fileno input
                input.disabled = true;
            }
        });
        
        controlledFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = true;
        });
        
        // Disable save button
        const saveButton = document.getElementById('saveButton');
        if (saveButton) saveButton.disabled = true;
        
        // Hide application info
        const applicationInfo = document.getElementById('application-info');
        if (applicationInfo) applicationInfo.classList.add('hidden');
        
        // Clear hidden fields
        const appIdEl = document.getElementById('application_id');
        const subAppIdEl = document.getElementById('sub_application_id');
        if (appIdEl) appIdEl.value = '';
        if (subAppIdEl) subAppIdEl.value = '';
        
        window.selectedApplication = null;
    }
    
    // Function to enable file number inputs specifically
    function enableFileNumberInputs() {
        // Enable all file number related inputs
        const fileNumberInputs = [
            'mlsFileNoPrefix', 'mlsFileYear', 'mlsFileSerial',
            'kangisFileNoPrefix', 'kangisFileNumber', 'kangisPreviewFileNumber',
            'newKangisFileNoPrefix', 'newKangisFileNumber', 'newKangisPreviewFileNumber'
        ];
        
        fileNumberInputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.disabled = false;
            }
        });
        
        // Enable radio buttons for MLS file types
        const mlsFileTypeRadios = document.querySelectorAll('input[name="mlsFileType"]');
        mlsFileTypeRadios.forEach(radio => {
            radio.disabled = false;
        });
    }
    
    // Function to disable file number inputs when manual mode is hidden
    function disableFileNumberInputsWhenHidden() {
        const manualMode = document.getElementById('manual-mode');
        if (manualMode && manualMode.classList.contains('hidden')) {
            // Remove required attributes from hidden fields to prevent form validation errors
            const requiredFields = [
                'mlsFileNoPrefix', 'mlsFileSerial', 'mlsFileOption',
                'kangisFileNoPrefix', 'kangisFileNumber',
                'newKangisFileNoPrefix', 'newKangisFileNumber'
            ];
            
            requiredFields.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.removeAttribute('required');
                }
            });
        }
    }
    
    // Function to reset manual entry form
    function resetManualEntryForm() {
        // Reset all file number inputs
        const resetFields = [
            'mlsFNo', 'kangisFileNo', 'NewKANGISFileno',
            'kangisPreviewFileNumber', 'newKangisPreviewFileNumber',
            'mlsFileYear', 'mlsFileSerial', 'kangisFileNumber', 'newKangisFileNumber',
            'mlsFileNoPrefix', 'kangisFileNoPrefix', 'newKangisFileNoPrefix'
        ];
        
        resetFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        
        // Reset MLS file type radio buttons to default (regular)
        const regularRadio = document.getElementById('mlsRegularFile');
        if (regularRadio) regularRadio.checked = true;
        
        // Reset to first tab
        const activeTabEl = document.getElementById('activeFileTab');
        if (activeTabEl) activeTabEl.value = 'mlsFNo';
        
        // Trigger tab switch to first tab
        const firstTabButton = document.querySelector('.tablinks');
        if (firstTabButton && typeof openFileTab === 'function') {
            const fakeEvent = { currentTarget: firstTabButton };
            openFileTab(fakeEvent, 'mlsFNoTab');
        }
    }
    
    // Add event listener for dropdown selection
    if (filenoSelect) {
        // Debug: Log initial dropdown options
        console.log('FileNumber Dropdown Debug - Initial options count:', filenoSelect.options.length);
        for (let i = 0; i < Math.min(5, filenoSelect.options.length); i++) {
            console.log('Option', i, ':', filenoSelect.options[i].value, filenoSelect.options[i].text);
        }
        
        filenoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const application = {
                    id: selectedOption.getAttribute('data-id'),
                    fileno: selectedOption.getAttribute('data-fileno'),
                    kangisFileNo: selectedOption.getAttribute('data-kangis-fileno'),
                    mlsfNo: selectedOption.getAttribute('data-mls-fileno'),
                    NewKANGISFileNo: selectedOption.getAttribute('data-newkangis-fileno')
                };
                
                // Show selected file number
                if (selectedText) selectedText.textContent = application.fileno;
                if (selectedDisplay) selectedDisplay.classList.remove('hidden');
                
                // Handle the selection
                handleFilenoSelection(application);
            } else {
                // Clear selection if empty option is selected
                if (selectedDisplay) selectedDisplay.classList.add('hidden');
                if (selectedText) selectedText.textContent = '';
                if (filenoInput) filenoInput.value = '';
                clearFormAndDisableInputs();
            }
        });
        
        // Debug: Check if options change after a delay (in case they're being replaced by JS)
        setTimeout(function() {
            console.log('FileNumber Dropdown Debug - Options after 2 seconds:', filenoSelect.options.length);
            for (let i = 0; i < Math.min(5, filenoSelect.options.length); i++) {
                console.log('Option', i, ':', filenoSelect.options[i].value, filenoSelect.options[i].text);
            }
        }, 2000);
    }
    
    // Make toggleFilenoMode globally accessible
    window.toggleFilenoMode = toggleFilenoMode;
    
    // Handle dropdown selection from Select2 (if used)
    window.handleDropdownSelection = function(application) {
        // Set the main fileno field
        if (filenoInput) filenoInput.value = application.fileno;
        
        // Show selected file number
        if (selectedText) selectedText.textContent = application.fileno;
        if (selectedDisplay) selectedDisplay.classList.remove('hidden');
        
        // Handle the selection
        handleFilenoSelection(application);
    };
    
    // Initialize Select2 for file number dropdown
    initializeSelect2FilenoDropdown();
}

function initializeSelect2FilenoDropdown() {
    const filenoSelect = document.getElementById('fileno-select');
    if (!filenoSelect) return;
    
    // Initialize Select2 with AJAX
    $(filenoSelect).select2({
        placeholder: 'Search and select file number...',
        allowClear: true,
        minimumInputLength: 2,
        width: '100%',
        dropdownParent: $(filenoSelect).parent(),
        ajax: {
            url: '/api/search-file-numbers',
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    search: params.term,
                    page: params.page || 1,
                    limit: 20
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                
                if (data.success) {
                    return {
                        results: data.files.map(function(file) {
                            let displayText = file.file_number;
                            if (file.file_type) {
                                displayText += ` (${file.file_type})`;
                            }
                            
                            return {
                                id: file.id,
                                text: displayText,
                                file_number: file.file_number,
                                kangis_file_no: file.kangis_file_no || '',
                                mls_file_no: file.mls_file_no || '',
                                new_kangis_file_no: file.new_kangis_file_no || '',
                                file_type: file.file_type || 'Unknown'
                            };
                        }),
                        pagination: {
                            more: data.has_more || false
                        }
                    };
                } else {
                    return {
                        results: [],
                        pagination: {
                            more: false
                        }
                    };
                }
            },
            cache: true
        },
        templateResult: function(file) {
            if (file.loading) return file.text;
            
            const container = $('<div class="select2-result-file">');
            container.append('<div class="file-number" style="font-weight: 600; color: #1f2937;">' + file.file_number + '</div>');
            
            if (file.file_type && file.file_type !== 'Unknown') {
                container.append('<div class="file-type" style="font-size: 0.75rem; color: #6b7280;">Type: ' + file.file_type + '</div>');
            }
            
            return container;
        },
        templateSelection: function(file) {
            return file.file_number || file.text;
        }
    });
    
    // Handle selection
    $(filenoSelect).on('select2:select', function (e) {
        const data = e.params.data;
        
        const application = {
            id: data.id,
            fileno: data.file_number,
            kangisFileNo: data.kangis_file_no,
            mlsfNo: data.mls_file_no,
            NewKANGISFileNo: data.new_kangis_file_no,
            file_type: data.file_type
        };
        
        // Set the main fileno field
        const filenoInput = document.getElementById('fileno');
        if (filenoInput) filenoInput.value = application.fileno;
        
        // Show selected file number
        const selectedText = document.getElementById('selected-fileno-text');
        const selectedDisplay = document.getElementById('selected-fileno-display');
        if (selectedText) selectedText.textContent = application.fileno;
        if (selectedDisplay) selectedDisplay.classList.remove('hidden');
        
        // Store selected application globally
        window.selectedApplication = application;
        
        // Handle the selection (enable form inputs, etc.)
        if (typeof handleDropdownSelection === 'function') {
            handleDropdownSelection(application);
        }
    });
    
    // Handle clearing selection
    $(filenoSelect).on('select2:clear', function (e) {
        const filenoInput = document.getElementById('fileno');
        const selectedDisplay = document.getElementById('selected-fileno-display');
        const selectedText = document.getElementById('selected-fileno-text');
        
        if (filenoInput) filenoInput.value = '';
        if (selectedDisplay) selectedDisplay.classList.add('hidden');
        if (selectedText) selectedText.textContent = '';
        
        window.selectedApplication = null;
        
        if (typeof clearFormAndDisableInputs === 'function') {
            clearFormAndDisableInputs();
        }
    });
}
</script>
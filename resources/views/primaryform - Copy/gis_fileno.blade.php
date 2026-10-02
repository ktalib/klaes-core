<style>
   .tab {
        overflow: hidden;
    }

    .tab button {
        background-color: inherit;
        float: left;
        border: none;
        outline: none;
        cursor: pointer;
        padding: 10px 16px;
        transition: 0.3s;
        font-size: 14px;
    }

    .tab button:hover {
        background-color: #ddd;
    }

    .tab button.active {
        background-color: #ccc;
    }

    /* Fix for tab content visibility */
    .tabcontent {
        display: none;
    }

    .tabcontent.active {
        display: block;
    }
</style>
<div class="bg-green-50 border border-green-100 rounded-md p-2 mb-3 items-center w-1/2">
    <div class="flex items-center mb-1">
      <i data-lucide="file" class="w-4 h-4 mr-1 text-green-600"></i>
      <span class="font-medium text-sm">File Number Information</span>
    </div>
    <p class="text-xs text-gray-600 mb-2">Select file number type and enter the details</p>
    
    <!-- Add hidden input to track active tab -->
    <input type="hidden" id="activeFileTab" name="activeFileTab" value="mlsFNo">
    
    <!-- Add hidden inputs for the actual database column names -->
    <input type="hidden" id="mlsFNo" name="mlsFNo" value="">
    <input type="hidden" id="kangisFileNo" name="kangisFileNo" value="">
    <input type="hidden" id="NewKANGISFileno" name="NewKANGISFileno" value="">
    
    <div class="bg-white p-1 rounded-md mb-2 flex space-x-4">
      <button type="button" class="tablinks active px-2 py-1 rounded-md hover:bg-gray-100 text-xs" onclick="openFileTab(event, 'mlsFNoTab')">MLS</button>
      <button type="button" class="tablinks px-2 py-1 rounded-md hover:bg-gray-100 text-xs" onclick="openFileTab(event, 'kangisFileNoTab')">KANGIS</button>
      <button type="button" class="tablinks px-2 py-1 rounded-md hover:bg-gray-100 text-xs" onclick="openFileTab(event, 'NewKANGISFilenoTab')">New KANGIS</button>
    </div>
    
  
   <div id="mlsFNoTab" class="tabcontent active">
    <p class="text-sm text-gray-600 mb-2">MLS File Number</p>
    
    <!-- File Options -->
    <div class="mb-2">
        <label for="mlsFileOption" class="block text-xs font-medium text-gray-700 mb-1">
            <i data-lucide="settings" class="w-3 h-3 inline mr-1"></i>
            File Options
        </label>
        <select id="mlsFileOption" name="mlsFileOption" 
                class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                onchange="updateMlsForm(this.value)">
            <option value="normal" selected>Normal File</option>
            <option value="temporary">Temporary File</option>
            <option value="extension">Extension</option>
            <option value="miscellaneous">Miscellaneous</option>
            <option value="old_mls">Old MLS</option>
            <option value="sltr">SLTR</option>
            <option value="sit">SIT</option>
        </select>
    </div>

    <div class="grid grid-cols-3 gap-2 mb-2">
      <!-- Prefix Section (for normal files) -->
      <div id="mlsPrefixSection">
        <label class="block text-xs mb-1">File Prefix <span class="text-red-500">*</span></label>
        <div class="relative">
          <select class="w-full p-2 text-sm border border-gray-300 rounded-md appearance-none pr-8" id="mlsFileNoPrefix" name="mlsFileNoPrefix">
            <option value="">Select prefix</option>
            <!-- Standard Options -->
            <optgroup label="Standard">
                <option value="RES">RES - Residential</option>
                <option value="COM">COM - Commercial</option>
                <option value="IND">IND - Industrial</option>
                <option value="AG">AG - Agricultural</option>
                
            </optgroup>
            <!-- Conversion Options -->
            <optgroup label="Conversion">
                <option value="CON-RES">CON-RES - Conversion to Residential</option>
                <option value="CON-COM">CON-COM - Conversion to Commercial</option>
                <option value="CON-IND">CON-IND - Conversion to Industrial</option>
                <option value="CON-AG">CON-AG - Conversion to Agricultural</option>
           
            </optgroup>
            <!-- RC Options -->
            <optgroup label="RC Options">
                <option value="RES-RC">RES-RC</option>
                <option value="COM-RC">COM-RC</option>
                <option value="AG-RC">AG-RC</option>
                <option value="IND-RC">IND-RC</option>
                <option value="CON-RES-RC">CON-RES-RC</option>
                <option value="CON-COM-RC">CON-COM-RC</option>
                <option value="CON-AG-RC">CON-AG-RC</option>
                <option value="CON-IND-RC">CON-IND-RC</option>
            </optgroup>
          </select>
          <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            <i data-lucide="chevron-down" class="w-3 h-3 text-gray-400"></i>
          </div>
        </div>
      </div>

      <!-- Middle Prefix (for miscellaneous files) -->
      <div id="mlsMiddlePrefixSection" class="hidden">
        <label for="mlsMiddlePrefix" class="block text-xs mb-1">
            <i data-lucide="tag" class="w-3 h-3 inline mr-1"></i>
            Middle Prefix
        </label>
        <input type="text" id="mlsMiddlePrefix" name="mlsMiddlePrefix" 
               class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
               placeholder="e.g., KN" onchange="updateMlsFileNumberPreview()" value="KN">
      </div>

      <!-- Extension File Selection (shown only when Extension is selected) -->
      <div id="mlsExtensionFileSection" class="hidden col-span-2">
        <label for="mlsExistingFileNo" class="block text-xs mb-1">
            <i data-lucide="link" class="w-3 h-3 inline mr-1"></i>
            Select Existing MLS File Number
        </label>
        <select id="mlsExistingFileNo" name="mlsExistingFileNo" 
                class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                onchange="updateMlsFileNumberPreview()">
            <option value="">Select existing file number...</option>
            <!-- Options will be populated via AJAX -->
        </select>
      </div>

      <!-- Year Field -->
      <div id="mlsYearSection">
        <label for="mlsYear" class="block text-xs mb-1">
            <i data-lucide="calendar" class="w-3 h-3 inline mr-1"></i>
            Year
        </label>
        <input type="number" id="mlsYear" name="mlsYear" 
               value="{{ date('Y') }}"
               class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
               min="1900" max="2050" onchange="updateMlsFileNumberPreview()">
      </div>

      <!-- Serial Number -->
      <div>
        <label class="block text-xs mb-1">Serial Number <span class="text-red-500">*</span></label>
        <input type="text" class="w-full p-2 text-sm border border-gray-300 rounded-md" id="mlsFileSerial" name="mlsFileSerial" placeholder="e.g. 572" value="{{ isset($result) ? ($result->mlsFileNumber ? explode('-', $result->mlsFileNumber)[1] ?? '' : '') : '' }}">
      </div>
    </div>

    <!-- Enhanced Full File Number Display -->
    <div class="mb-4">
      <label class="block text-xs mb-1 text-gray-700 font-medium">Generated File Number</label>
      <div class="relative w-1/2">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-2 flex items-center justify-between">
          <div id="mlsPreviewFileNumber" class="text-sm font-bold text-blue-900 tracking-wide">
            Enter details above to see preview
          </div>
          <div class="flex items-center space-x-1">
            <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
            <span class="text-xs text-blue-600 font-medium">MLS</span>
          </div>
        </div>
      </div>
    </div>
  </div>  

  <div id="kangisFileNoTab" class="tabcontent">
    <p class="text-sm text-gray-600 mb-2">KANGIS File Number</p>
    <div class="grid grid-cols-3 gap-4 mb-3">
      <div>
        <label class="block text-sm mb-1">File Prefix</label>
        <div class="relative">
          <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8"    id="kangisFileNoPrefix" name="kangisFileNoPrefix">
            <option value="">Select Prefix</option>
                        @foreach (['KNML', 'MNKL', 'MLKN', 'KNGP'] as $prefix)
                            <option value="{{ $prefix }}">{{ $prefix }}</option>
                        @endforeach
          </select>
          <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
          </div>
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Serial Number</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="kangisFileNumber" name="kangisFileNumber" placeholder="e.g. 0001 or 2500">
      </div>
       <div>
        <label class="block text-sm mb-1">Full FileNo</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md"  id="kangisPreviewFileNumber" name="kangisPreviewFileNumber"
        value="{{ isset($result) ? ($result->kangisFileNo ?: '') : '' }}" readonly>
      </div>
    </div>
  </div> 

  <div id="NewKANGISFilenoTab" class="tabcontent">
    <p class="text-sm text-gray-600 mb-2">
        New KANGIS File Number</p>
    <div class="grid grid-cols-3 gap-4 mb-3">
      <div>
        <label class="block text-sm mb-1">File Prefix</label>
        <div class="relative">
          <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8"  id="newKangisFileNoPrefix" name="newKangisFileNoPrefix">
        
            <option value="">Select Prefix</option>
            <option value="KN">KN</option>
          </select>
          <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
          </div>
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Serial Number</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md"  id="newKangisFileNumber" name="newKangisFileNumber" 
        placeholder="e.g. 1586" value="{{ isset($result) ? ($result->newKangisFileNumber ?: '') : '' }}">
      </div>
       <div>
        <label class="block text-sm mb-1">Full FileNo</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md"  id="newKangisPreviewFileNumber" name="newKangisPreviewFileNumber"
        value="{{ isset($result) ? ($result->NewKANGISFileno ?: '') : '' }}" readonly>
      </div>
    </div>
</div>
    
<script>
    // Function to load existing MLS file numbers for extension selection
    function loadExistingMlsFileNumbers() {
        const existingFileSelect = document.getElementById('mlsExistingFileNo');
        if (!existingFileSelect) return;
        
        // Show loading state
        existingFileSelect.innerHTML = '<option value="">Loading existing file numbers...</option>';
        
        // Make AJAX request to fetch existing MLS file numbers
        fetch('/api/get-existing-mls-files', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            // Clear existing options
            existingFileSelect.innerHTML = '<option value="">Select existing file number...</option>';
            
            if (data.success && data.files && data.files.length > 0) {
                // Add existing file numbers as options
                data.files.forEach(file => {
                    const option = document.createElement('option');
                    option.value = file.mlsFNo || file.file_number;
                    option.textContent = file.mlsFNo || file.file_number;
                    existingFileSelect.appendChild(option);
                });
                console.log(`Loaded ${data.files.length} existing MLS file numbers`);
            } else {
                // No existing files found
                existingFileSelect.innerHTML = '<option value="">No existing MLS files found</option>';
                console.log('No existing MLS file numbers found');
            }
        })
        .catch(error => {
            console.error('Error loading existing MLS file numbers:', error);
            existingFileSelect.innerHTML = '<option value="">Error loading file numbers</option>';
        });
    }

    // Update MLS form based on file option selection
    function updateMlsForm(type) {
        const prefixSection = document.getElementById('mlsPrefixSection');
        const middlePrefixSection = document.getElementById('mlsMiddlePrefixSection');
        const yearSection = document.getElementById('mlsYearSection');
        const extensionFileSection = document.getElementById('mlsExtensionFileSection');
        const serialNoField = document.getElementById('mlsFileSerial');
        
        // Hide all sections first
        prefixSection.classList.add('hidden');
        middlePrefixSection.classList.add('hidden');
        yearSection.classList.add('hidden');
        extensionFileSection.classList.add('hidden');
        
        // Get the prefix field to manage its required attribute
        const prefixField = document.getElementById('mlsFileNoPrefix');
        
        // Reset serial number field properties
        serialNoField.type = 'text';
        serialNoField.removeAttribute('min');
        serialNoField.removeAttribute('max');
        serialNoField.removeAttribute('step');
        serialNoField.removeAttribute('maxlength');
        serialNoField.removeAttribute('pattern');
        serialNoField.disabled = false; // Ensure it's enabled
        serialNoField.removeAttribute('required'); // Remove required first, then add back if needed
        
        // Reset prefix field required attribute
        if (prefixField) {
            prefixField.removeAttribute('required');
        }
        
        if (type === 'normal' || type === 'temporary') {
            prefixSection.classList.remove('hidden');
            yearSection.classList.remove('hidden');
            serialNoField.type = 'number';
            serialNoField.setAttribute('min', '1');
            serialNoField.setAttribute('max', '9999');
            serialNoField.placeholder = 'Enter serial number (1-9999)';
        } else if (type === 'extension') {
            extensionFileSection.classList.remove('hidden');
            yearSection.classList.remove('hidden');
            serialNoField.placeholder = 'Not required for extensions';
            serialNoField.value = '';
            serialNoField.disabled = true;
            // Don't add required attribute for extensions since field is disabled
            // Don't require prefix field for extensions
            
            // Load existing MLS file numbers when extension is selected
            loadExistingMlsFileNumbers();
        } else if (type === 'miscellaneous' || type === 'sit' || type === 'old_mls' || type === 'sltr') {
            if (type === 'miscellaneous') {
                middlePrefixSection.classList.remove('hidden');
            }
            if (type === 'sit') {
                yearSection.classList.remove('hidden');
            }
            serialNoField.type = 'text';
            serialNoField.disabled = false;
            serialNoField.setAttribute('inputmode', 'text');

            
            if (type === 'miscellaneous') {
                serialNoField.placeholder = 'Enter custom serial (e.g., 001, ABC123)';
            } else if (type === 'sit') {
                serialNoField.placeholder = 'Enter SIT serial (e.g., 001)';
            } else if (type === 'old_mls') {
                serialNoField.placeholder = 'Enter Old MLS number (e.g., 5467, 1234)';
            } else if (type === 'sltr') {
                serialNoField.placeholder = 'Enter SLTR number (e.g., 001, 1234)';
            }
            
            // Don't require prefix field for these types since they don't use the standard prefix
        }
        
        updateMlsFileNumberPreview();
    }

    // Enhanced MLS file number preview function
    function updateMlsFileNumberPreview() {
        const fileOption = document.getElementById('mlsFileOption').value;
        const prefix = document.getElementById('mlsFileNoPrefix').value;
        const middlePrefix = document.getElementById('mlsMiddlePrefix').value;
        const year = document.getElementById('mlsYear').value;
        const serialNo = document.getElementById('mlsFileSerial').value;
        const existingFileNo = document.getElementById('mlsExistingFileNo').value;
        const previewEl = document.getElementById('mlsPreviewFileNumber');
        const dbFieldEl = document.getElementById('mlsFNo');
        
        let previewText = '';
        
        if (fileOption === 'extension' && existingFileNo) {
            previewText = existingFileNo + ' AND EXTENSION';
        } else if (fileOption === 'miscellaneous' && middlePrefix && serialNo) {
            previewText = `MISC-${middlePrefix}-${serialNo}`;
        } else if (fileOption === 'old_mls' && serialNo) {
            previewText = `KN ${serialNo}`;
        } else if (fileOption === 'sit' && year && serialNo) {
            previewText = `SIT-${year}-${serialNo}`;
        } else if (fileOption === 'sltr' && serialNo) {
            previewText = `SLTR-${serialNo}`;
        } else if ((fileOption === 'normal' || fileOption === 'temporary') && prefix && year && serialNo) {
            const paddedSerial = serialNo.toString().padStart(4, '0');
            previewText = `${prefix}-${year}-${paddedSerial}`;
            
            if (fileOption === 'temporary') {
                previewText += '(T)';
            }
        } else if (prefix && serialNo) {
            previewText = prefix + '-' + serialNo;
        } else if (prefix) {
            previewText = prefix;
        } else if (serialNo) {
            previewText = serialNo;
        }

        // Update preview display
        if (previewText) {
            previewEl.textContent = previewText;
            dbFieldEl.value = previewText;
        } else {
            previewEl.textContent = 'Enter details above to see preview';
            dbFieldEl.value = '';
        }
        
        updateMainFilenoField();
    }

    // Fixed tab switching function
    function openFileTab(evt, tabName) {
        console.log("Opening tab:", tabName);
        
        // Hide all tab content
        var tabcontent = document.getElementsByClassName("tabcontent");
        for (var i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
            tabcontent[i].style.display = "none";
        }

        // Remove active class from all tab buttons
        var tablinks = document.getElementsByClassName("tablinks");
        for (var i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }

        // Show the current tab and add active class to the button
        var currentTab = document.getElementById(tabName);
        if (currentTab) {
            currentTab.classList.add("active");
            currentTab.style.display = "block";
        } else {
            console.error("Tab not found:", tabName);
        }
        
        evt.currentTarget.classList.add("active");
        
        // Set the active tab value
        if (tabName === "mlsFNoTab") {
            document.getElementById('activeFileTab').value = "mlsFNo";
        } else if (tabName === "kangisFileNoTab") {
            document.getElementById('activeFileTab').value = "kangisFileNo";
        } else if (tabName === "NewKANGISFilenoTab") {
            document.getElementById('activeFileTab').value = "NewKANGISFileno";
        }
        
        updateMainFilenoField();
    }

    // Function to update the main fileno field based on active tab
    function updateMainFilenoField() {
        const activeTab = document.getElementById('activeFileTab').value;
        const mainFilenoField = document.getElementById('fileno');
        
        if (!mainFilenoField) return;
        
        let fileNumber = '';
        
        if (activeTab === 'mlsFNo') {
            fileNumber = document.getElementById('mlsFNo').value;
        } else if (activeTab === 'kangisFileNo') {
            fileNumber = document.getElementById('kangisFileNo').value;
        } else if (activeTab === 'NewKANGISFileno') {
            fileNumber = document.getElementById('NewKANGISFileno').value;
        }
        
        mainFilenoField.value = fileNumber;
        
        if (typeof validateSurveyForm === 'function') {
            validateSurveyForm();
        }
    }

    // Format KANGIS file number preview
    function updateKangisFileNumberPreview() {
        const prefixEl = document.getElementById('kangisFileNoPrefix');
        const numberEl = document.getElementById('kangisFileNumber');
        const previewEl = document.getElementById('kangisPreviewFileNumber');
        const dbFieldEl = document.getElementById('kangisFileNo');

        const prefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (prefix && number) {
            number = number.padStart(5, '0');
            numberEl.value = number;
            const formatted = prefix + ' ' + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted;
        } else if (prefix) {
            previewEl.value = prefix;
            dbFieldEl.value = prefix;
        } else if (number) {
            previewEl.value = number;
            dbFieldEl.value = number;
        } else {
            previewEl.value = '';
            dbFieldEl.value = '';
        }
        
        updateMainFilenoField();
    }

    // Format New KANGIS file number preview
    function updateNewKangisFileNumberPreview() {
        const prefixEl = document.getElementById('newKangisFileNoPrefix');
        const numberEl = document.getElementById('newKangisFileNumber');
        const previewEl = document.getElementById('newKangisPreviewFileNumber');
        const dbFieldEl = document.getElementById('NewKANGISFileno');

        const prefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (prefix && number) {
            const formatted = prefix + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted;
        } else if (prefix) {
            previewEl.value = prefix;
            dbFieldEl.value = prefix;
        } else if (number) {
            previewEl.value = number;
            dbFieldEl.value = number;
        } else {
            previewEl.value = '';
            dbFieldEl.value = '';
        }
        
        updateMainFilenoField();
    }

    // Function to validate that at least one file number is provided
    function validateFileNumbers() {
        const mlsFNo = document.getElementById('mlsFNo').value.trim();
        const kangisFileNo = document.getElementById('kangisFileNo').value.trim();
        const newKangisFileno = document.getElementById('NewKANGISFileno').value.trim();
        
        if (!mlsFNo && !kangisFileNo && !newKangisFileno) {
            return {
                isValid: false,
                message: 'At least one file number (MLS, KANGIS, or New KANGIS) must be provided.'
            };
        }
        
        return {
            isValid: true,
            message: ''
        };
    }
    
    // Function to ensure file numbers are properly populated before form submission
    function ensureFileNumbersPopulated() {
        // Force update all file number previews to ensure hidden fields are populated
        updateMlsFileNumberPreview();
        updateKangisFileNumberPreview();
        updateNewKangisFileNumberPreview();
        
        // Also update the main fileno field
        updateMainFilenoField();
    }
    
    // Make validation function globally accessible
    window.validateFileNumbers = validateFileNumbers;
    window.ensureFileNumbersPopulated = ensureFileNumbersPopulated;

    document.addEventListener('DOMContentLoaded', function() {
        // CRITICAL FIX: Ensure File Options dropdown is never disabled
        const fileOptionSelect = document.getElementById('mlsFileOption');
        const yearField = document.getElementById('mlsYear');
        
        // Force enable these fields immediately
        if (fileOptionSelect) {
            fileOptionSelect.disabled = false;
            fileOptionSelect.style.pointerEvents = 'auto';
            fileOptionSelect.style.opacity = '1';
            fileOptionSelect.style.backgroundColor = '';
            fileOptionSelect.style.cursor = 'pointer';
        }
        
        if (yearField) {
            yearField.disabled = false;
            yearField.readOnly = false;
            yearField.style.pointerEvents = 'auto';
            yearField.style.opacity = '1';
            yearField.style.backgroundColor = '';
            yearField.style.cursor = 'text';
        }
        
        // Create a mutation observer to prevent external scripts from disabling these fields
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'disabled') {
                    const target = mutation.target;
                    if (target.id === 'mlsFileOption' || target.id === 'mlsYear') {
                        // Re-enable if something tries to disable it
                        target.disabled = false;
                        target.style.pointerEvents = 'auto';
                        target.style.opacity = '1';
                        target.style.backgroundColor = '';
                    }
                }
            });
        });
        
        // Start observing both fields
        if (fileOptionSelect) {
            observer.observe(fileOptionSelect, { attributes: true, attributeFilter: ['disabled'] });
        }
        if (yearField) {
            observer.observe(yearField, { attributes: true, attributeFilter: ['disabled'] });
        }
        
        // Also set up interval checks as backup
        setInterval(function() {
            if (fileOptionSelect && fileOptionSelect.disabled) {
                fileOptionSelect.disabled = false;
                fileOptionSelect.style.pointerEvents = 'auto';
                fileOptionSelect.style.opacity = '1';
            }
            if (yearField && (yearField.disabled || yearField.readOnly)) {
                yearField.disabled = false;
                yearField.readOnly = false;
                yearField.style.pointerEvents = 'auto';
                yearField.style.opacity = '1';
            }
        }, 500);
        
        // Initialize form with default values
        updateMlsForm('normal');
        
        // Initialize file number previews
        updateMlsFileNumberPreview();
        updateKangisFileNumberPreview();
        updateNewKangisFileNumberPreview();

        // Add event listeners for MLS file number preview updates
        document.getElementById('mlsFileNoPrefix').addEventListener('change', updateMlsFileNumberPreview);
        document.getElementById('mlsYear').addEventListener('input', updateMlsFileNumberPreview);
        document.getElementById('mlsFileSerial').addEventListener('input', updateMlsFileNumberPreview);
        document.getElementById('mlsMiddlePrefix').addEventListener('input', updateMlsFileNumberPreview);
        document.getElementById('mlsExistingFileNo').addEventListener('change', updateMlsFileNumberPreview);

        // Add event listeners for KANGIS file number preview updates
        document.getElementById('kangisFileNoPrefix').addEventListener('change', updateKangisFileNumberPreview);
        document.getElementById('kangisFileNumber').addEventListener('input', updateKangisFileNumberPreview);

        // Add event listeners for New KANGIS file number preview updates
        document.getElementById('newKangisFileNoPrefix').addEventListener('change', updateNewKangisFileNumberPreview);
        document.getElementById('newKangisFileNumber').addEventListener('input', updateNewKangisFileNumberPreview);
            
        // Set up the default active tab
        var activeTabName = document.getElementById('activeFileTab').value;
        var tabToShow = "mlsFNoTab";
        
        if (activeTabName === "kangisFileNo") {
            tabToShow = "kangisFileNoTab";
        } else if (activeTabName === "NewKANGISFileno") {
            tabToShow = "NewKANGISFilenoTab";
        }
        
        // Simulate a click on the appropriate tab button
        var tabButtons = document.getElementsByClassName("tablinks");
        for (var i = 0; i < tabButtons.length; i++) {
            if (tabButtons[i].getAttribute("onclick").includes(tabToShow)) {
                var fakeEvent = { currentTarget: tabButtons[i] };
                openFileTab(fakeEvent, tabToShow);
                break;
            }
        }
    });
</script>

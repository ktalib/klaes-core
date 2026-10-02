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
        width: 100%;
        visibility: hidden;
    }

    .tabcontent.active {
        display: block;
        visibility: visible;
    }
</style>
<div class="bg-green-50 border border-green-100 rounded-md p-4 mb-6 items-center">
    <div class="flex items-center mb-2">
      <i data-lucide="file" class="w-5 h-5 mr-2 text-green-600"></i>
      <span class="font-medium">File Number Information</span>
    </div>
    <p class="text-sm text-gray-600 mb-4">Select file number type and enter the details</p>
    
    @php
        // Use provided prefix or default to empty string
        $prefix = $prefix ?? '';
    @endphp
    
    <!-- Add hidden input to track active tab -->
    <input type="hidden" id="{{ $prefix }}activeFileTab" name="activeFileTab" value="mlsFNo">
    
    <!-- Add hidden inputs for the actual database column names -->
    <input type="hidden" id="{{ $prefix }}mlsFNo" name="mlsFNo" value="">
    <input type="hidden" id="{{ $prefix }}kangisFileNo" name="kangisFileNo" value="">
    <input type="hidden" id="{{ $prefix }}NewKANGISFileno" name="NewKANGISFileno" value="">
    
    <div class="bg-white p-2 rounded-md mb-4 flex space-x-2">
      <button type="button" class="{{ $prefix }}tablinks active px-4 py-2 rounded-md hover:bg-gray-100" onclick="openFileTab('{{ $prefix }}', event, '{{ $prefix }}mlsFNoTab')">MLS</button>
      <button type="button" class="{{ $prefix }}tablinks px-4 py-2 rounded-md hover:bg-gray-100" onclick="openFileTab('{{ $prefix }}', event, '{{ $prefix }}kangisFileNoTab')">KANGIS</button>
      <button type="button" class="{{ $prefix }}tablinks px-4 py-2 rounded-md hover:bg-gray-100" onclick="openFileTab('{{ $prefix }}', event, '{{ $prefix }}NewKANGISFilenoTab')">New KANGIS</button>
    </div>
    
    <!-- MLS Tab content -->
    <div id="{{ $prefix }}mlsFNoTab" class="tabcontent active">
      <p class="text-sm text-gray-600 mb-2">MLS File Number</p>
      
      <!-- File Options -->
      <div class="mb-3">
        <label for="{{ $prefix }}mlsFileOption" class="block text-xs font-medium text-gray-700 mb-1">
            <i data-lucide="settings" class="w-3 h-3 inline mr-1"></i>
            File Options
        </label>
        <select id="{{ $prefix }}mlsFileOption" name="mlsFileOption" 
                class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                onchange="updateMlsForm('{{ $prefix }}', this.value)" required>
            <option value="normal" selected>Normal File</option>
            <option value="temporary">Temporary File</option>
            <option value="extension">Extension</option>
            <option value="miscellaneous">Miscellaneous</option>
            <option value="old_mls">Old MLS</option>
            <option value="sltr">SLTR</option>
            <option value="sit">SIT</option>
        </select>
      </div>

      <div class="grid grid-cols-3 gap-3 mb-3">
        <!-- Prefix Section (for normal/temporary files) -->
        <div id="{{ $prefix }}mlsPrefixSection">
          <label class="block text-xs mb-1">File Prefix <span class="text-red-500">*</span></label>
          <div class="relative">
            <select class="w-full p-2 text-sm border border-gray-300 rounded-md appearance-none pr-8" id="{{ $prefix }}mlsFileNoPrefix" name="mlsFileNoPrefix" required>
              <option value="">Select prefix</option>
              <!-- Standard Options -->
              <optgroup label="Standard">
                  <option value="RES">RES - Residential</option>
                  <option value="COM">COM - Commercial</option>
                  <option value="IND">IND - Industrial</option>
                  <option value="AGR">AGR - Agricultural</option>
                  <option value="INS">INS - Institutional</option>
              </optgroup>
              <!-- Conversion Options -->
              <optgroup label="Conversion">
                  <option value="CON-RES">CON-RES - Conversion to Residential</option>
                  <option value="CON-COM">CON-COM - Conversion to Commercial</option>
                  <option value="CON-IND">CON-IND - Conversion to Industrial</option>
                  <option value="CON-AGR">CON-AGR - Conversion to Agricultural</option>
                  <option value="CON-INS">CON-INS - Conversion to Institutional</option>
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
        <div id="{{ $prefix }}mlsMiddlePrefixSection" class="hidden">
          <label for="{{ $prefix }}mlsMiddlePrefix" class="block text-xs mb-1">
              <i data-lucide="tag" class="w-3 h-3 inline mr-1"></i>
              Middle Prefix
          </label>
          <input type="text" id="{{ $prefix }}mlsMiddlePrefix" name="mlsMiddlePrefix" 
                 class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                 placeholder="e.g., KN" onchange="updateMlsFileNumberPreview('{{ $prefix }}')" value="KN">
        </div>

        <!-- Extension File Selection (shown only when Extension is selected) -->
        <div id="{{ $prefix }}mlsExtensionFileSection" class="hidden col-span-2">
          <label for="{{ $prefix }}mlsExistingFileNo" class="block text-xs mb-1">
              <i data-lucide="link" class="w-3 h-3 inline mr-1"></i>
              Select Existing MLS File Number
          </label>
          <select id="{{ $prefix }}mlsExistingFileNo" name="mlsExistingFileNo" 
                  class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                  onchange="updateMlsFileNumberPreview('{{ $prefix }}')">
              <option value="">Select existing file number...</option>
          </select>
        </div>

        <!-- Year Field -->
        <div id="{{ $prefix }}mlsYearSection">
          <label for="{{ $prefix }}mlsYear" class="block text-xs mb-1">
              <i data-lucide="calendar" class="w-3 h-3 inline mr-1"></i>
              Year
          </label>
          <input type="number" id="{{ $prefix }}mlsYear" name="mlsYear" 
                 value="{{ date('Y') }}"
                 class="w-full p-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                 min="1900" max="2050" onchange="updateMlsFileNumberPreview('{{ $prefix }}')">
        </div>

        <!-- Serial Number -->
        <div>
          <label class="block text-xs mb-1">Serial Number <span class="text-red-500">*</span></label>
          <input type="text" class="w-full p-2 text-sm border border-gray-300 rounded-md" id="{{ $prefix }}mlsFileSerial" name="mlsFileSerial" placeholder="e.g. 572" value="{{ isset($result) ? ($result->mlsFileNumber ? explode('-', $result->mlsFileNumber)[1] ?? '' : '') : '' }}">
        </div>
      </div>

      <!-- Generated File Number Display -->
      <div class="mb-4">
        <label class="block text-xs mb-1 text-gray-700 font-medium">Generated File Number</label>
        <div class="relative w-1/2">
          <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-2 flex items-center justify-between">
            <div id="{{ $prefix }}mlsPreviewFileNumber" class="text-sm font-bold text-blue-900 tracking-wide">
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

    <!-- KANGIS Tab content -->
    <div id="{{ $prefix }}kangisFileNoTab" class="tabcontent">
      <p class="text-sm text-gray-600 mb-2">KANGIS File Number</p>
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="block text-sm mb-1">File Prefix</label>
          <div class="relative">
            <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8" id="{{ $prefix }}kangisFileNoPrefix" name="kangisFileNoPrefix">
              <option value="">Select Prefix</option>
              @foreach (['KNML', 'MNKL', 'MLKN', 'KNGP'] as $prefixOption)
                  <option value="{{ $prefixOption }}">{{ $prefixOption }}</option>
              @endforeach
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm mb-1">Serial Number</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="{{ $prefix }}kangisFileNumber" name="kangisFileNumber" placeholder="e.g. 0001 or 2500">
        </div>
        <div>
          <label class="block text-sm mb-1">Full FileNo</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="{{ $prefix }}kangisPreviewFileNumber" name="kangisPreviewFileNumber"
                 value="{{ isset($result) ? ($result->kangisFileNo ?: '') : '' }}" readonly>
        </div>
      </div>
    </div> 

    <!-- New KANGIS Tab content -->
    <div id="{{ $prefix }}NewKANGISFilenoTab" class="tabcontent">
      <p class="text-sm text-gray-600 mb-2">New KANGIS File Number</p>
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="block text-sm mb-1">File Prefix</label>
          <div class="relative">
            <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8" id="{{ $prefix }}newKangisFileNoPrefix" name="newKangisFileNoPrefix">
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
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="{{ $prefix }}newKangisFileNumber" name="newKangisFileNumber" 
                 placeholder="e.g. 1586" value="{{ isset($result) ? ($result->newKangisFileNumber ?: '') : '' }}">
        </div>
        <div>
          <label class="block text-sm mb-1">Full FileNo</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="{{ $prefix }}newKangisPreviewFileNumber" name="newKangisPreviewFileNumber"
                 value="{{ isset($result) ? ($result->NewKANGISFileno ?: '') : '' }}" readonly>
        </div>
      </div>
    </div>
</div>
    
<script>
    // Load existing MLS file numbers for extension selection (supports prefixed instances)
    function loadExistingMlsFileNumbers(prefix) {
        const selectId = prefix + 'mlsExistingFileNo';
        const existingFileSelect = document.getElementById(selectId);
        if (!existingFileSelect) return;
        
        existingFileSelect.innerHTML = '<option value="">Loading existing file numbers...</option>';
        
        fetch('/api/get-existing-mls-files', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            existingFileSelect.innerHTML = '<option value="">Select existing file number...</option>';
            if (data.success && data.files && data.files.length > 0) {
                data.files.forEach(file => {
                    const option = document.createElement('option');
                    option.value = file.mlsFNo || file.file_number;
                    option.textContent = file.mlsFNo || file.file_number;
                    existingFileSelect.appendChild(option);
                });
                console.log(`Loaded ${data.files.length} existing MLS file numbers`);
            } else {
                existingFileSelect.innerHTML = '<option value="">No existing MLS files found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading existing MLS file numbers:', error);
            existingFileSelect.innerHTML = '<option value="">Error loading file numbers</option>';
        });
    }

    // Update MLS form based on selected file option (supports prefixed instances)
    function updateMlsForm(prefix, type) {
        const prefixSection = document.getElementById(prefix + 'mlsPrefixSection');
        const middlePrefixSection = document.getElementById(prefix + 'mlsMiddlePrefixSection');
        const yearSection = document.getElementById(prefix + 'mlsYearSection');
        const extensionFileSection = document.getElementById(prefix + 'mlsExtensionFileSection');
        const serialNoField = document.getElementById(prefix + 'mlsFileSerial');
        const prefixField = document.getElementById(prefix + 'mlsFileNoPrefix');
        
        if (!serialNoField) return;
        
        // Hide all sections first
        if (prefixSection) prefixSection.classList.add('hidden');
        if (middlePrefixSection) middlePrefixSection.classList.add('hidden');
        if (yearSection) yearSection.classList.add('hidden');
        if (extensionFileSection) extensionFileSection.classList.add('hidden');
        
        // Reset serial number field properties
        serialNoField.type = 'text';
        serialNoField.removeAttribute('min');
        serialNoField.removeAttribute('max');
        serialNoField.removeAttribute('step');
        serialNoField.removeAttribute('maxlength');
        serialNoField.removeAttribute('pattern');
        serialNoField.disabled = false;
        serialNoField.removeAttribute('required');
        
        // Reset prefix field required attribute
        if (prefixField) {
            prefixField.removeAttribute('required');
        }
        
        if (type === 'normal' || type === 'temporary') {
            if (prefixSection) prefixSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            serialNoField.type = 'number';
            serialNoField.setAttribute('min', '1');
            serialNoField.setAttribute('max', '9999');
            serialNoField.setAttribute('required', 'required');
            serialNoField.placeholder = 'Enter serial number (1-9999)';
            if (prefixField) prefixField.setAttribute('required', 'required');
        } else if (type === 'extension') {
            if (extensionFileSection) extensionFileSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            serialNoField.placeholder = 'Not required for extensions';
            serialNoField.value = '';
            serialNoField.disabled = true;
            loadExistingMlsFileNumbers(prefix);
        } else if (type === 'miscellaneous' || type === 'sit' || type === 'old_mls' || type === 'sltr') {
            if (type === 'miscellaneous' && middlePrefixSection) {
                middlePrefixSection.classList.remove('hidden');
            }
            if (type === 'sit' && yearSection) {
                yearSection.classList.remove('hidden');
            }
            serialNoField.type = 'text';
            serialNoField.disabled = false;
            serialNoField.setAttribute('inputmode', 'text');
            serialNoField.setAttribute('required', 'required');
            
            if (type === 'miscellaneous') {
                serialNoField.placeholder = 'Enter custom serial (e.g., 001, ABC123)';
            } else if (type === 'sit') {
                serialNoField.placeholder = 'Enter SIT serial (e.g., 001)';
            } else if (type === 'old_mls') {
                serialNoField.placeholder = 'Enter Old MLS number (e.g., 5467, 1234)';
            } else if (type === 'sltr') {
                serialNoField.placeholder = 'Enter SLTR number (e.g., 001, 1234)';
            }
        }
        
        updateMlsFileNumberPreview(prefix);
    }

    // Enhanced MLS file number preview
    function updateMlsFileNumberPreview(prefix) {
        const fileOptionEl = document.getElementById(prefix + 'mlsFileOption');
        const prefixEl = document.getElementById(prefix + 'mlsFileNoPrefix');
        const middlePrefixEl = document.getElementById(prefix + 'mlsMiddlePrefix');
        const yearEl = document.getElementById(prefix + 'mlsYear');
        const serialEl = document.getElementById(prefix + 'mlsFileSerial');
        const existingEl = document.getElementById(prefix + 'mlsExistingFileNo');
        const previewEl = document.getElementById(prefix + 'mlsPreviewFileNumber');
        const dbFieldEl = document.getElementById(prefix + 'mlsFNo');
        
        if (!fileOptionEl || !previewEl || !dbFieldEl) return;
        
        const fileOption = fileOptionEl.value;
        const prefixVal = prefixEl ? prefixEl.value : '';
        const middlePrefix = middlePrefixEl ? middlePrefixEl.value : '';
        const year = yearEl ? yearEl.value : '';
        const serialNo = serialEl ? serialEl.value : '';
        const existingFileNo = existingEl ? existingEl.value : '';
        
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
        } else if ((fileOption === 'normal' || fileOption === 'temporary') && prefixVal && year && serialNo) {
            const paddedSerial = serialNo.toString().padStart(4, '0');
            previewText = `${prefixVal}-${year}-${paddedSerial}`;
            if (fileOption === 'temporary') {
                previewText += '(T)';
            }
        } else if (prefixVal && serialNo) {
            previewText = prefixVal + '-' + serialNo;
        } else if (prefixVal) {
            previewText = prefixVal;
        } else if (serialNo) {
            previewText = serialNo;
        }
        
        if (previewText) {
            previewEl.textContent = previewText;
            dbFieldEl.value = previewText;
        } else {
            previewEl.textContent = 'Enter details above to see preview';
            dbFieldEl.value = '';
        }
        
        updateMainFilenoField(prefix);
    }

    // Updated function to maintain values across tabs and support multiple instances
    function openFileTab(prefix, evt, tabName) {
        console.log("Opening tab:", tabName, "for prefix:", prefix);
        
        try {
            // Save current values before switching tabs
            if (document.getElementById(prefix + 'activeFileTab').value === "mlsFNo") {
                updateMlsFileNumberPreview(prefix);
            } else if (document.getElementById(prefix + 'activeFileTab').value === "kangisFileNo") {
                updateKangisFileNumberPreview(prefix);
            } else if (document.getElementById(prefix + 'activeFileTab').value === "NewKANGISFileno") {
                updateNewKangisFileNumberPreview(prefix);
            }
            
            // Get all tab content for this form instance
            var tabcontent = document.querySelectorAll("[id^='" + prefix + "'][id$='Tab'][class*='tabcontent']");
            
            // Remove active class and hide all tab content first
            for (var i = 0; i < tabcontent.length; i++) {
                var tab = tabcontent[i];
                tab.classList.remove("active");
                tab.style.display = "none";
            }

            // Remove active class from all tab buttons for this form instance
            var tablinks = document.getElementsByClassName(prefix + "tablinks");
            for (var i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }

            // Show the current tab and add active class to the button
            var currentTab = document.getElementById(tabName);
            if (currentTab) {
                currentTab.classList.add("active");
                currentTab.style.display = "block";
                currentTab.style.visibility = "visible";
            } else {
                console.error("Tab not found:", tabName);
            }
            
            evt.currentTarget.classList.add("active");
            
            // Set the active tab value based on the database field names
            if (tabName === prefix + "mlsFNoTab") {
                document.getElementById(prefix + 'activeFileTab').value = "mlsFNo";
            } else if (tabName === prefix + "kangisFileNoTab") {
                document.getElementById(prefix + 'activeFileTab').value = "kangisFileNo";
            } else if (tabName === prefix + "NewKANGISFilenoTab") {
                document.getElementById(prefix + 'activeFileTab').value = "NewKANGISFileno";
            }
        } catch (error) {
            console.error("Error in openFileTab:", error);
        }
        
        updateMainFilenoField(prefix);
    }

    // Function to update the main fileno field based on active tab
    function updateMainFilenoField(prefix) {
        const activeTab = document.getElementById(prefix + 'activeFileTab');
        const mainFilenoField = document.getElementById('fileno');
        if (!activeTab || !mainFilenoField) return;
        let fileNumber = '';
        if (activeTab.value === 'mlsFNo') {
            fileNumber = document.getElementById(prefix + 'mlsFNo')?.value || '';
        } else if (activeTab.value === 'kangisFileNo') {
            fileNumber = document.getElementById(prefix + 'kangisFileNo')?.value || '';
        } else if (activeTab.value === 'NewKANGISFileno') {
            fileNumber = document.getElementById(prefix + 'NewKANGISFileno')?.value || '';
        }
        mainFilenoField.value = fileNumber;
        if (typeof validateSurveyForm === 'function') {
            try { validateSurveyForm(); } catch (e) {}
        }
    }

    // Format KANGIS file number preview with prefix
    function updateKangisFileNumberPreview(prefix) {
        const prefixEl = document.getElementById(prefix + 'kangisFileNoPrefix');
        const numberEl = document.getElementById(prefix + 'kangisFileNumber');
        const previewEl = document.getElementById(prefix + 'kangisPreviewFileNumber');
        const dbFieldEl = document.getElementById(prefix + 'kangisFileNo');

        if (!prefixEl || !numberEl || !previewEl || !dbFieldEl) return;

        const selectedPrefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (selectedPrefix && number) {
            number = number.padStart(5, '0');
            numberEl.value = number;
            const formatted = selectedPrefix + ' ' + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted;
        } else if (selectedPrefix) {
            previewEl.value = selectedPrefix;
            dbFieldEl.value = selectedPrefix;
        } else if (number) {
            previewEl.value = number;
            dbFieldEl.value = number;
        } else {
            previewEl.value = '';
            dbFieldEl.value = '';
        }
        updateMainFilenoField(prefix);
    }

    // Format New KANGIS file number preview with prefix
    function updateNewKangisFileNumberPreview(prefix) {
        const prefixEl = document.getElementById(prefix + 'newKangisFileNoPrefix');
        const numberEl = document.getElementById(prefix + 'newKangisFileNumber');
        const previewEl = document.getElementById(prefix + 'newKangisPreviewFileNumber');
        const dbFieldEl = document.getElementById(prefix + 'NewKANGISFileno');

        if (!prefixEl || !numberEl || !previewEl || !dbFieldEl) return;

        const selectedPrefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (selectedPrefix && number) {
            const formatted = selectedPrefix + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted;
        } else if (selectedPrefix) {
            previewEl.value = selectedPrefix;
            dbFieldEl.value = selectedPrefix;
        } else if (number) {
            previewEl.value = number;
            dbFieldEl.value = number;
        } else {
            previewEl.value = '';
            dbFieldEl.value = '';
        }
        updateMainFilenoField(prefix);
    }

    // Updates the form data for submission based on prefix
    function updateFormFileData(prefix) {
        updateMlsFileNumberPreview(prefix);
        updateKangisFileNumberPreview(prefix);
        updateNewKangisFileNumberPreview(prefix);
        
        // Set hidden fields based on active tab (kept for compatibility)
        const activeTab = document.getElementById(prefix + 'activeFileTab').value;
        if (activeTab === "mlsFNo") {
            // MLS db field already set by updateMlsFileNumberPreview
        } else if (activeTab === "kangisFileNo") {
            document.getElementById(prefix + 'kangisFileNo').value = document.getElementById(prefix + 'kangisPreviewFileNumber').value;
        } else if (activeTab === "NewKANGISFileno") {
            document.getElementById(prefix + 'NewKANGISFileno').value = document.getElementById(prefix + 'newKangisPreviewFileNumber').value;
        }
        return true;
    }

    // Initialize the file number component for one instance
    function initFileNumberComponent(prefix) {
        // Ensure File Options and Year fields aren't disabled by external scripts
        const fileOptionSelect = document.getElementById(prefix + 'mlsFileOption');
        const yearField = document.getElementById(prefix + 'mlsYear');
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
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'disabled') {
                    const target = mutation.target;
                    if (target.id === prefix + 'mlsFileOption' || target.id === prefix + 'mlsYear') {
                        target.disabled = false;
                        target.style.pointerEvents = 'auto';
                        target.style.opacity = '1';
                        target.style.backgroundColor = '';
                    }
                }
            });
        });
        if (fileOptionSelect) observer.observe(fileOptionSelect, { attributes: true, attributeFilter: ['disabled'] });
        if (yearField) observer.observe(yearField, { attributes: true, attributeFilter: ['disabled'] });
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

        // Initialize default MLS form state and previews
        updateMlsForm(prefix, 'normal');
        updateMlsFileNumberPreview(prefix);
        updateKangisFileNumberPreview(prefix);
        updateNewKangisFileNumberPreview(prefix);

        // Event listeners
        const mlsPrefix = document.getElementById(prefix + 'mlsFileNoPrefix');
        const mlsYear = document.getElementById(prefix + 'mlsYear');
        const mlsSerial = document.getElementById(prefix + 'mlsFileSerial');
        const mlsMiddle = document.getElementById(prefix + 'mlsMiddlePrefix');
        const mlsExisting = document.getElementById(prefix + 'mlsExistingFileNo');
        const kangisPrefix = document.getElementById(prefix + 'kangisFileNoPrefix');
        const kangisNumber = document.getElementById(prefix + 'kangisFileNumber');
        const newKangisPrefix = document.getElementById(prefix + 'newKangisFileNoPrefix');
        const newKangisNumber = document.getElementById(prefix + 'newKangisFileNumber');

        if (mlsPrefix) mlsPrefix.addEventListener('change', function() { updateMlsFileNumberPreview(prefix); });
        if (mlsYear) mlsYear.addEventListener('input', function() { updateMlsFileNumberPreview(prefix); });
        if (mlsSerial) mlsSerial.addEventListener('input', function() { updateMlsFileNumberPreview(prefix); });
        if (mlsMiddle) mlsMiddle.addEventListener('input', function() { updateMlsFileNumberPreview(prefix); });
        if (mlsExisting) mlsExisting.addEventListener('change', function() { updateMlsFileNumberPreview(prefix); });
        if (fileOptionSelect) fileOptionSelect.addEventListener('change', function() { updateMlsForm(prefix, this.value); });

        if (kangisPrefix) kangisPrefix.addEventListener('change', function() { updateKangisFileNumberPreview(prefix); });
        if (kangisNumber) kangisNumber.addEventListener('input', function() { updateKangisFileNumberPreview(prefix); });

        if (newKangisPrefix) newKangisPrefix.addEventListener('change', function() { updateNewKangisFileNumberPreview(prefix); });
        if (newKangisNumber) newKangisNumber.addEventListener('input', function() { updateNewKangisFileNumberPreview(prefix); });
            
        // Set up the default active tab
        var activeTabName = document.getElementById(prefix + 'activeFileTab').value;
        var tabToShow = prefix + "mlsFNoTab";
        if (activeTabName === "kangisFileNo") {
            tabToShow = prefix + "kangisFileNoTab";
        } else if (activeTabName === "NewKANGISFileno") {
            tabToShow = prefix + "NewKANGISFilenoTab";
        }
        var tabButtons = document.getElementsByClassName(prefix + "tablinks");
        for (var i = 0; i < tabButtons.length; i++) {
            if (tabButtons[i].getAttribute("onclick").includes(tabToShow)) {
                var fakeEvent = { currentTarget: tabButtons[i] };
                openFileTab(prefix, fakeEvent, tabToShow);
                break;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize this component instance
        try { initFileNumberComponent('{{ $prefix }}'); } catch (e) { console.error(e); }
    });
</script>

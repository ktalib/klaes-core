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
        padding: 12px 20px;
        transition: 0.3s;
        font-size: 14px;
        font-weight: 500;
    }

    .tab button:hover {
        background-color: #f3f4f6;
    }

    .tab button.active {
        background-color: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    /* Fix for tab content visibility */
    .tabcontent {
        display: none;
    }

    .tabcontent.active {
        display: block;
    }

    /* Enhanced tablinks styling */
    .tablinks.active {
        background-color: #3b82f6 !important;
        color: white !important;
        border-color: #3b82f6 !important;
    }
</style>
<div class="bg-green-50 border border-green-100 rounded-md p-3 sm:p-4 mb-3">
    <div class="flex items-center mb-2">
      <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      <span class="font-medium text-sm sm:text-base">File Number Information</span>
    </div>
    <p class="text-xs sm:text-sm text-gray-600 mb-3">Select file number type and enter the details</p>
    
    <!-- Add hidden input to track active tab -->
    <input type="hidden" id="activeFileTab" name="activeFileTab" value="mlsFNo">
    
    <!-- Add hidden inputs for the actual database column names -->
    <input type="hidden" id="mlsFNo" name="mlsFNo" value="">
    <input type="hidden" id="kangisFileNo" name="kangisFileNo" value="">
    <input type="hidden" id="NewKANGISFileno" name="NewKANGISFileno" value="">
    
    <div class="bg-white p-2 rounded-md mb-3 flex flex-wrap gap-2">
      <button type="button" class="tablinks active px-3 sm:px-4 py-2 rounded-md hover:bg-gray-100 text-xs sm:text-sm font-medium border border-gray-200 flex-1 sm:flex-none" onclick="openFileTab(event, 'mlsFNoTab')">MLPP</button>
      <button type="button" class="tablinks px-3 sm:px-4 py-2 rounded-md hover:bg-gray-100 text-xs sm:text-sm font-medium border border-gray-200 flex-1 sm:flex-none" onclick="openFileTab(event, 'kangisFileNoTab')">KANGIS</button>
      <button type="button" class="tablinks px-3 sm:px-4 py-2 rounded-md hover:bg-gray-100 text-xs sm:text-sm font-medium border border-gray-200 flex-1 sm:flex-none" onclick="openFileTab(event, 'NewKANGISFilenoTab')">New KANGIS</button>
    </div>
    
  
   <div id="mlsFNoTab" class="tabcontent active">
    <p class="text-base text-gray-600 mb-3">MLPP File Number</p>
    
    <!-- File Options -->
    <div class="mb-3">
        <label for="mlsFileOption" class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            File Options
        </label>
        <select id="mlsFileOption" name="mlsFileOption" 
                class="w-full p-3 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                onchange="updateMlsForm(this.value)" required>
            <option value="normal" selected>Normal File</option>
            <option value="temporary">Temporary File</option>
            <option value="extension">Extension</option>
            <option value="miscellaneous">Miscellaneous</option>
            <option value="old_mls">Old MLPP</option>
            <option value="sltr">SLTR</option>
            <option value="sit">SIT</option>
        </select>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4">
      <!-- Prefix Section (for normal files) -->
      <div id="mlsPrefixSection">
        <label class="block text-xs sm:text-sm mb-2">File Prefix <span class="text-red-500">*</span></label>
        <div class="relative">
          <select class="w-full p-2 sm:p-3 text-sm sm:text-base border border-gray-300 rounded-lg appearance-none pr-8" id="mlsFileNoPrefix" name="mlsFileNoPrefix" required>
            <option value="">Select prefix</option>
            <!-- Standard Options -->
            <optgroup label="Standard">
                <option value="RES">RES - Residential</option>
                <option value="COM">COM - Commercial</option>
                <option value="IND">IND - Industrial</option>
                <option value="AGR">AG - Agricultural</option>
 
            </optgroup>
            <!-- Conversion Options -->
            <optgroup label="Conversion">
                <option value="CON-RES">CON-RES - Conversion to Residential</option>
                <option value="CON-COM">CON-COM - Conversion to Commercial</option>
                <option value="CON-IND">CON-IND - Conversion to Industrial</option>
                <option value="CON-AGR">CON-AG - Conversion to Agricultural</option>
             
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
          <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </div>
        </div>
      </div>

      <!-- Middle Prefix (for miscellaneous files) -->
      <div id="mlsMiddlePrefixSection" class="hidden">
        <label for="mlsMiddlePrefix" class="block text-sm mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a1.994 1.994 0 01-1.414.586H7a4 4 0 01-4-4V7a4 4 0 014-4z"></path>
            </svg>
            Middle Prefix
        </label>
        <input type="text" id="mlsMiddlePrefix" name="mlsMiddlePrefix" 
               class="w-full p-3 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
               placeholder="e.g., KN" onchange="updateMlsFileNumberPreview()" value="KN">
      </div>

      <!-- Extension File Selection (shown only when Extension is selected) -->
      <div id="mlsExtensionFileSection" class="hidden col-span-2">
        <label for="mlsExistingFileNo" class="block text-sm mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
            </svg>
            Select Existing MLPP File Number
        </label>
        <select id="mlsExistingFileNo" name="mlsExistingFileNo" 
                class="w-full p-3 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                onchange="updateMlsFileNumberPreview()">
            <option value="">Select existing file number...</option>
            <!-- Options will be populated via AJAX -->
        </select>
      </div>

      <!-- Year Field -->
      <div id="mlsYearSection">
        <label for="mlsYear" class="block text-sm mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Year
        </label>
        <input type="number" id="mlsYear" name="mlsYear" 
               value="{{ date('Y') }}"
               class="w-full p-3 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
               min="1900" max="2050" onchange="updateMlsFileNumberPreview()">
      </div>

      <!-- Serial Number -->
      <div>
        <label class="block text-sm mb-2">Serial Number <span class="text-red-500">*</span></label>
        <input type="text" class="w-full p-3 text-base border border-gray-300 rounded-lg" id="mlsFileSerial" name="mlsFileSerial" placeholder="e.g. 572" value="{{ isset($result) ? ($result->mlsFileNumber ? explode('-', $result->mlsFileNumber)[1] ?? '' : '') : '' }}">
      </div>
    </div>

    <!-- Enhanced Full File Number Display -->
    <div class="mb-4">
      <label class="block text-sm mb-2 text-gray-700 font-medium">Generated File Number</label>
      <div class="relative w-full">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
          <div id="mlsPreviewFileNumber" class="text-lg font-bold text-blue-900 tracking-wide">
            Enter details above to see preview
          </div>
          <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span class="text-sm text-blue-600 font-medium">MLPP</span>
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
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
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
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
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
                existingFileSelect.innerHTML = '<option value="">No existing MLPP files found</option>';
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
            serialNoField.setAttribute('required', 'required'); // Add required for normal/temporary
            serialNoField.placeholder = 'Enter serial number (1-9999)';
            // Add required to prefix field when it's visible and needed
            if (prefixField) {
                prefixField.setAttribute('required', 'required');
            }
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
            serialNoField.setAttribute('required', 'required'); // Add required for these types too
            
            if (type === 'miscellaneous') {
                serialNoField.placeholder = 'Enter custom serial (e.g., 001, ABC123)';
            } else if (type === 'sit') {
                serialNoField.placeholder = 'Enter SIT serial (e.g., 001)';
            } else if (type === 'old_mls') {
                serialNoField.placeholder = 'Enter Old MLPP number (e.g., 5467, 1234)';
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
            previewText = existingFileNo.trim().replace(/-$/, '') + ' AND EXTENSION';
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
        } else {
            previewText = 'Enter details above to see preview';
        }
        
        if (previewEl) {
            previewEl.textContent = previewText;
        }
        
        // Update hidden field with the generated file number
        if (dbFieldEl) {
            dbFieldEl.value = previewText;
        }
        // Notify outer pages that a file number was selected/updated
        try {
          const isComplete = !!previewText && previewText !== 'Enter details above to see preview';
          const detail = { fileNumber: previewText, isComplete };
          document.dispatchEvent(new CustomEvent('fileNumberChanged', { detail }));
          if (window.onFileNumberSelected && typeof window.onFileNumberSelected === 'function') {
            // Provide a compact payload the parent expects
            window.onFileNumberSelected({ fileNo: previewText, fileName: previewText, isComplete });
          }
        } catch (e) {
          console.warn('notify file number change failed', e);
        }
    }

    // Tab functionality for file number types
    function openFileTab(evt, tabName) {
        const tabcontent = document.getElementsByClassName("tabcontent");
        const tablinks = document.getElementsByClassName("tablinks");
        
        // Hide all tab content
        for (let i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove('active');
        }
        
        // Remove active class from all tab buttons
        for (let i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove('active');
        }
        
        // Show the selected tab content and mark button as active
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
        
        // Update the hidden input for active tab
        const activeTabInput = document.getElementById('activeFileTab');
        if (activeTabInput) {
            if (tabName === 'mlsFNoTab') {
                activeTabInput.value = 'mlsFNo';
            } else if (tabName === 'kangisFileNoTab') {
                activeTabInput.value = 'kangisFileNo';
            } else if (tabName === 'NewKANGISFilenoTab') {
                activeTabInput.value = 'NewKANGISFileno';
            }
        }
    }

    // Update KANGIS file number preview
    function updateKangisFileNumberPreview() {
        const prefix = document.getElementById('kangisFileNoPrefix').value;
        const serialNo = document.getElementById('kangisFileNumber').value;
        const previewEl = document.getElementById('kangisPreviewFileNumber');
        const dbFieldEl = document.getElementById('kangisFileNo');
        
        let previewText = '';
        
        if (prefix && serialNo) {
            previewText = `${prefix} ${serialNo}`;
        } else if (prefix) {
            previewText = prefix + ' ';
        } else if (serialNo) {
            previewText = serialNo;
        }
        
        if (previewEl) {
            previewEl.value = previewText;
        }
        
        // Update hidden field
        if (dbFieldEl) {
            dbFieldEl.value = previewText;
        }
    // Notify parent pages about change
    try {
      const isComplete = !!previewText;
      const detail = { fileNumber: previewText, isComplete };
      document.dispatchEvent(new CustomEvent('fileNumberChanged', { detail }));
      if (window.onFileNumberSelected && typeof window.onFileNumberSelected === 'function') {
        window.onFileNumberSelected({ fileNo: previewText, fileName: previewText, isComplete });
      }
    } catch (e) { console.warn('notify file number change failed', e); }
    }

    // Update New KANGIS file number preview
    function updateNewKangisFileNumberPreview() {
        const prefix = document.getElementById('newKangisFileNoPrefix').value;
        const serialNo = document.getElementById('newKangisFileNumber').value;
        const previewEl = document.getElementById('newKangisPreviewFileNumber');
        const dbFieldEl = document.getElementById('NewKANGISFileno');
        
        let previewText = '';
        
        if (prefix && serialNo) {
            previewText = `${prefix} ${serialNo}`;
        } else if (prefix) {
            previewText = prefix + ' ';
        } else if (serialNo) {
            previewText = serialNo;
        }
        
        if (previewEl) {
            previewEl.value = previewText;
        }
        
        // Update hidden field
        if (dbFieldEl) {
            dbFieldEl.value = previewText;
        }
    // Notify parent pages about change
    try {
      const isComplete = !!previewText;
      const detail = { fileNumber: previewText, isComplete };
      document.dispatchEvent(new CustomEvent('fileNumberChanged', { detail }));
      if (window.onFileNumberSelected && typeof window.onFileNumberSelected === 'function') {
        window.onFileNumberSelected({ fileNo: previewText, fileName: previewText, isComplete });
      }
    } catch (e) { console.warn('notify file number change failed', e); }
    }

    // Add event listeners for real-time updates
    document.addEventListener('DOMContentLoaded', function() {
        // MLS events
        const mlsPrefix = document.getElementById('mlsFileNoPrefix');
        const mlsSerial = document.getElementById('mlsFileSerial');
        const mlsYear = document.getElementById('mlsYear');
        const mlsMiddlePrefix = document.getElementById('mlsMiddlePrefix');
        
        if (mlsPrefix) mlsPrefix.addEventListener('change', updateMlsFileNumberPreview);
        if (mlsSerial) mlsSerial.addEventListener('input', updateMlsFileNumberPreview);
        if (mlsYear) mlsYear.addEventListener('change', updateMlsFileNumberPreview);
        if (mlsMiddlePrefix) mlsMiddlePrefix.addEventListener('input', updateMlsFileNumberPreview);
        
        // KANGIS events
        const kangisPrefix = document.getElementById('kangisFileNoPrefix');
        const kangisSerial = document.getElementById('kangisFileNumber');
        
        if (kangisPrefix) kangisPrefix.addEventListener('change', updateKangisFileNumberPreview);
        if (kangisSerial) kangisSerial.addEventListener('input', updateKangisFileNumberPreview);
        
        // New KANGIS events
        const newKangisPrefix = document.getElementById('newKangisFileNoPrefix');
        const newKangisSerial = document.getElementById('newKangisFileNumber');
        
        if (newKangisPrefix) newKangisPrefix.addEventListener('change', updateNewKangisFileNumberPreview);
        if (newKangisSerial) newKangisSerial.addEventListener('input', updateNewKangisFileNumberPreview);
        
        // Initialize with normal file form
        updateMlsForm('normal');
    });
</script>
</div>

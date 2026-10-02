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
<div class="bg-green-50 border border-green-100 rounded-md p-4 mb-6 items-center">
    <div class="flex items-center mb-2">
      <i data-lucide="file" class="w-5 h-5 mr-2 text-green-600"></i>
      <span class="font-medium">File Number Information</span>
    </div>
    <p class="text-sm text-gray-600 mb-4">Select file number type and enter the details</p>
    
    <!-- Add hidden input to track active tab -->
    <input type="hidden" id="activeFileTab" name="activeFileTab" value="mlsFNo">
    
    <!-- Add hidden inputs for the actual database column names -->
    <input type="hidden" id="mlsFNo" name="mlsFNo" value="">
    <input type="hidden" id="kangisFileNo" name="kangisFileNo" value="">
    <input type="hidden" id="NewKANGISFileno" name="NewKANGISFileno" value="">
    
    <div class="bg-white p-2 rounded-md mb-4 flex space-x-2">
      <button type="button" class="tablinks active px-4 py-2 rounded-md hover:bg-gray-100" onclick="openFileTab(event, 'mlsFNoTab')">MLS</button>
      <button type="button" class="tablinks px-4 py-2 rounded-md hover:bg-gray-100" onclick="openFileTab(event, 'kangisFileNoTab')">KANGIS</button>
      <button type="button" class="tablinks px-4 py-2 rounded-md hover:bg-gray-100" onclick="openFileTab(event, 'NewKANGISFilenoTab')">New KANGIS</button>
    </div>
    
  
   <div id="mlsFNoTab" class="tabcontent active">
    <p class="text-sm text-gray-600 mb-2">MLS File Number</p>
    
    <!-- File Options -->
    <div class="mb-4">
        <label for="mlsFileOption" class="block text-sm font-medium text-gray-700 mb-2">
            <i data-lucide="settings" class="w-4 h-4 inline mr-1"></i>
            File Options
        </label>
        <select id="mlsFileOption" name="mlsFileOption" 
                class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
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

    <div class="grid grid-cols-3 gap-4 mb-3">
      <!-- Prefix Section (for normal files) -->
      <div id="mlsPrefixSection">
        <label class="block text-sm mb-1">File Prefix <span class="text-red-500">*</span></label>
        <div class="relative">
          <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8" id="mlsFileNoPrefix" name="mlsFileNoPrefix">
            <option value="">Select prefix</option>
            <!-- Standard Options -->
            <optgroup label="Standard">
                <option value="RES">RES - Residential</option>
                <option value="COM">COM - Commercial</option>
                <option value="IND">IND - Industrial</option>
                <option value="AGR">AG - Agricultural</option>
                
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
            <!-- Conversion Options -->
            <optgroup label="Conversion">
                <option value="CON-RES">CON-RES - Conversion to Residential</option>
                <option value="CON-COM">CON-COM - Conversion to Commercial</option>
                <option value="CON-IND">CON-IND - Conversion to Industrial</option>
                <option value="CON-AGR">CON-AG - Conversion to Agricultural</option>
           
            </optgroup>
          
          </select>
          <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
          </div>
        </div>
      </div>

      <!-- Middle Prefix (for miscellaneous files) -->
      <div id="mlsMiddlePrefixSection" class="hidden">
        <label for="mlsMiddlePrefix" class="block text-sm mb-1">
            <i data-lucide="tag" class="w-4 h-4 inline mr-1"></i>
            Middle Prefix
        </label>
        <input type="text" id="mlsMiddlePrefix" name="mlsMiddlePrefix" 
               class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
               placeholder="e.g., KN" onchange="updateMlsFileNumberPreview()" value="KN">
      </div>

      <!-- Extension File Selection (shown only when Extension is selected) -->
      <div id="mlsExtensionFileSection" class="hidden col-span-2">
        <label for="mlsExistingFileNo" class="block text-sm mb-1">
            <i data-lucide="link" class="w-4 h-4 inline mr-1"></i>
            Select Existing MLS File Number
        </label>
        <select id="mlsExistingFileNo" name="mlsExistingFileNo" 
                class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                onchange="updateMlsFileNumberPreview()">
            <option value="">Select existing file number...</option>
            <!-- Options will be populated via AJAX -->
        </select>
      </div>

      <!-- Year Field -->
      <div id="mlsYearSection">
        <label for="mlsYear" class="block text-sm mb-1">
            <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
            Year
        </label>
        <input type="number" id="mlsYear" name="mlsYear" 
               value="{{ date('Y') }}"
               class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
               min="1900" max="2050" onchange="updateMlsFileNumberPreview()">
      </div>

      <!-- Serial Number -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
        Serial Number <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" 
             id="mlsFileNumber" name="mlsFileNumber" 
             placeholder="e.g. 2022-572" 
             value="{{ isset($result) ? ($result->mlsFileNumber ?: '') : '' }}">
        <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
        <i data-lucide="edit-3" class="w-4 h-4 text-gray-400"></i>
        </div>
      </div>
    </div>

      <!-- Full FileNo Preview -->
      <div>
        <label class="block text-sm mb-1">Full FileNo</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md"   id="mlsPreviewFileNumber" name="mlsPreviewFileNumber"
        value="{{ isset($result) ? ($result->mlsFNo ?: '') : '' }}" readonly>
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
    function updateFileNumberPreview() {
        const prefixEl = document.getElementById('fileNoPrefix');
        const numberEl = document.getElementById('fileNumber');
        const previewEl = document.getElementById('Previewflenumber');

        const prefix = prefixEl.value;
        let number = numberEl.value.trim();

        // Set placeholder based on selected prefix
        if (prefix) {
            if (['KNML', 'MNKL', 'MLKN', 'KNGP'].includes(prefix)) {
                numberEl.placeholder = "e.g. 00001";
            } else if (prefix === "KN") {
                numberEl.placeholder = "e.g. 0001";
            } else if (['CON-COM', 'CON-RES', 'CON-AG', 'CON-IND', 'RES'].includes(prefix)) {
                numberEl.placeholder = "e.g. 01";
            } else {
                numberEl.placeholder = "Format example";
            }
        }

        // Format the number based on the prefix
        if (prefix && number) {
            if (['KNML', 'MNKL', 'MLKN', 'KNGP'].includes(prefix)) {
                // Ensure 5-digit format with leading zeros
                number = number.padStart(5, '0');
                numberEl.value = number;
                previewEl.value = prefix + ' ' + number;
            } else if (prefix === "KN") {
                previewEl.value = prefix + number;
            } else if (['CON-COM', 'CON-RES', 'CON-AG', 'CON-IND', 'RES'].includes(prefix)) {
                previewEl.value = prefix + '-' + number;
            } else {
                previewEl.value = prefix + '/' + number;
            }
        } else if (prefix) {
            previewEl.value = prefix;
        } else if (number) {
            previewEl.value = number;
        } else {
            previewEl.value = '';
        }

        // Validation based on prefix
        let isValid = true;
        if (prefix === "KN") {
            isValid = /^\d+$/.test(number);
        } else if (["KNML", "MNKL", "MLKN", "KNGP"].includes(prefix)) {
            isValid = /^\d{5}$/.test(number);
        } else if (['CON-COM', 'CON-RES', 'CON-AG', 'CON-IND', 'RES'].includes(prefix)) {
            isValid = /^\d+$/.test(number);
        }

        if (prefix && number && isValid) {
            prefixEl.style.color = 'red';
            numberEl.style.color = 'red';
            previewEl.style.color = 'red';
        } else {
            prefixEl.style.color = '';
            numberEl.style.color = '';
            previewEl.style.color = '';
        }
    }

    // Fixed tab switching function
    function openFileTab(evt, tabName) {
        console.log("Opening tab:", tabName); // Debug logging
        
        // Hide all tab content
        var tabcontent = document.getElementsByClassName("tabcontent");
        for (var i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
            tabcontent[i].style.display = "none"; // Explicitly hide
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
            currentTab.style.display = "block"; // Explicitly show
        } else {
            console.error("Tab not found:", tabName);
        }
        
        evt.currentTarget.classList.add("active");
        
        // Set the active tab value based on the database field names, not tab IDs
        if (tabName === "mlsFNoTab") {
            document.getElementById('activeFileTab').value = "mlsFNo";
        } else if (tabName === "kangisFileNoTab") {
            document.getElementById('activeFileTab').value = "kangisFileNo";
        } else if (tabName === "NewKANGISFilenoTab") {
            document.getElementById('activeFileTab').value = "NewKANGISFileno";
        }
    }

    // Format MLS file number preview
    function updateMlsFileNumberPreview() {
        const fileOption = document.getElementById('mlsFileOption').value;
        const prefix = document.getElementById('mlsFileNoPrefix').value;
        const middlePrefix = document.getElementById('mlsMiddlePrefix').value;
        const year = document.getElementById('mlsYear').value;
        const serialNo = document.getElementById('mlsFileNumber').value;
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
        } else if (fileOption === 'sit' && serialNo) {
            previewText = `SIT-${year}-${serialNo}`;
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

        previewEl.value = previewText;
        dbFieldEl.value = previewText;
    }

    // Update MLS form based on file option selection
    function updateMlsForm(type) {
        const prefixSection = document.getElementById('mlsPrefixSection');
        const middlePrefixSection = document.getElementById('mlsMiddlePrefixSection');
        const yearSection = document.getElementById('mlsYearSection');
        const extensionFileSection = document.getElementById('mlsExtensionFileSection');
        const serialNoField = document.getElementById('mlsFileNumber');
        
        // Hide all sections first
        prefixSection.classList.add('hidden');
        middlePrefixSection.classList.add('hidden');
        yearSection.classList.add('hidden');
        extensionFileSection.classList.add('hidden');
        
        // Reset serial number field properties
        serialNoField.type = 'text';
        serialNoField.removeAttribute('min');
        serialNoField.removeAttribute('max');
        serialNoField.removeAttribute('step');
        serialNoField.removeAttribute('maxlength');
        serialNoField.removeAttribute('pattern');
        
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
        } else if (type === 'miscellaneous' || type === 'sit' || type === 'old_mls') {
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
            }
        }
        
        // Re-enable serial field if it was disabled
        if (type !== 'extension') {
            serialNoField.disabled = false;
        }
        
        updateMlsFileNumberPreview();
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
            // Pad to 5 digits
            number = number.padStart(5, '');
            numberEl.value = number;
            const formatted = prefix + ' ' + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted; // Set the database field directly
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
    }

    // Format New KANGIS file number preview
    function updateNewKangisFileNumberPreview() {
        const prefixEl = document.getElementById('newKangisFileNoPrefix');
        const numberEl = document.getElementById('newKangisFileNumber');
        const previewEl = document.getElementById('newKangisPreviewFileNumber');
        const dbFieldEl = document.getElementById('NewKANGISFileno'); // Important: This must match DB column name

        const prefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (prefix && number) {
            const formatted = prefix + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted; // Set the database field directly
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
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize file number previews
        updateMlsFileNumberPreview();
        updateKangisFileNumberPreview();
        updateNewKangisFileNumberPreview();

        // Add event listeners for MLS file number preview updates
        document.getElementById('mlsFileNoPrefix').addEventListener('change', updateMlsFileNumberPreview);
        document.getElementById('mlsFileNumber').addEventListener('input', updateMlsFileNumberPreview);
        document.getElementById('mlsYear').addEventListener('input', updateMlsFileNumberPreview);
        document.getElementById('mlsMiddlePrefix').addEventListener('input', updateMlsFileNumberPreview);
        document.getElementById('mlsExistingFileNo').addEventListener('change', updateMlsFileNumberPreview);

        // Add event listeners for KANGIS file number preview updates
        document.getElementById('kangisFileNoPrefix').addEventListener('change', updateKangisFileNumberPreview);
        document.getElementById('kangisFileNumber').addEventListener('input', updateKangisFileNumberPreview);

        // Add event listeners for New KANGIS file number preview updates
        document.getElementById('newKangisFileNoPrefix').addEventListener('change', updateNewKangisFileNumberPreview);
        document.getElementById('newKangisFileNumber').addEventListener('input', updateNewKangisFileNumberPreview);
            
        // Make sure the active tab is properly displayed on page load
        var activeTabName = document.getElementById('activeFileTab').value;
        var tabToShow = "mlsFNoTab"; // Default
        
        if (activeTabName === "kangisFileNo") {
            tabToShow = "kangisFileNoTab";
        } else if (activeTabName === "NewKANGISFileno") {
            tabToShow = "NewKANGISFilenoTab";
        }
        
        // Simulate a click on the appropriate tab button
        var tabButtons = document.getElementsByClassName("tablinks");
        for (var i = 0; i < tabButtons.length; i++) {
            if (tabButtons[i].getAttribute("onclick").includes(tabToShow)) {
                // Create a fake event object
                var fakeEvent = { currentTarget: tabButtons[i] };
                openFileTab(fakeEvent, tabToShow);
                break;
            }
        }
    });
</script>

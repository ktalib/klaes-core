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
    <button type="button" class="tablinks active px-4 py-2 rounded-md hover:bg-gray-100"
      onclick="openFileTab(event, 'mlsFNoTab')">MLPP</button>
    <button type="button" class="tablinks px-4 py-2 rounded-md hover:bg-gray-100"
      onclick="openFileTab(event, 'kangisFileNoTab')">KANGIS</button>
    <button type="button" class="tablinks px-4 py-2 rounded-md hover:bg-gray-100"
      onclick="openFileTab(event, 'NewKANGISFilenoTab')">New KANGIS</button>
  </div>


  <div id="mlsFNoTab" class="tabcontent active">
    <p class="text-sm text-gray-600 mb-2">MLPP File Number</p>

    <!-- File type selection dropdown -->
    <div class="mb-2">
      <label class="block text-sm mb-1 text-gray-700">Type</label>
      <div class="relative">
        <select
          class="w-full p-2 text-sm border border-gray-300 rounded appearance-none pr-8 bg-white focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
          id="mlsFileType" name="mlsFileType">
          <option value="regular">Regular</option>
          <option value="temporary">Temporary</option>
          <option value="extension">Extension</option>
          <option value="miscellaneous">Miscellaneous</option>
          <option value="old_mls">Old MLPP</option>
          <option value="sltr">SLTR</option>
          <option value="sit">SIT</option>
        </select>
        <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
          <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
        </div>
      </div>
    </div>

    <!-- Conditional sections based on file type -->
    <div id="mlsRegularSection" class="grid grid-cols-3 gap-4 mb-4">
      <div>
        <label class="block text-sm mb-1">File Prefix</label>
        <div class="relative">
          <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8" id="mlsFileNoPrefix"
            name="mlsFileNoPrefix">
            <option value="">Select prefix</option>
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

            </optgroup>
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
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
          </div>
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Year</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="mlsFileYear" name="mlsFileYear"
          placeholder="e.g. 2024" maxlength="4" value="{{ isset($result) ? (date('Y')) : date('Y') }}">
      </div>
      <div>
        <label class="block text-sm mb-1">Serial No</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="mlsFileSerial" name="mlsFileSerial"
          placeholder="e.g. 572"
          value="{{ isset($result) ? ($result->mlsFileNumber ? explode('-', $result->mlsFileNumber)[1] ?? '' : '') : '' }}">
      </div>
    </div>

    <!-- Extension: Existing file selector -->
    <div id="mlsExtensionFileSection" class="hidden mb-4">
      <label class="block text-sm mb-1">Select Existing MLPP File Number</label>
      <select id="mlsExistingFileNo" name="mlsExistingFileNo" class="w-full p-2 border border-gray-300 rounded-md">
        <option value="">Select existing file number...</option>
      </select>
      <p class="text-xs text-gray-500 mt-1">Serial not required for extensions.</p>
    </div>

    <!-- Middle Prefix Section (for miscellaneous files) -->
    <div id="mlsMiddlePrefixSection" class="hidden mb-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Middle Prefix</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="mlsMiddlePrefix"
            name="mlsMiddlePrefix" placeholder="e.g. KN" value="KN">
        </div>
        <div>
          <label class="block text-sm mb-1">Serial No</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="mlsMiscSerial"
            name="mlsMiscSerial" placeholder="Enter custom serial (e.g., 001, ABC123)">
        </div>
      </div>
    </div>

    <!-- Special Serial Section (for SLTR and SIT files) -->
    <div id="mlsSpecialSerialSection" class="hidden mb-4">
      <div class="grid grid-cols-2 gap-4">
        <div id="mlsSitYearContainer" class="hidden">
          <label class="block text-sm mb-1">Year (SIT)</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="mlsSitYear" name="mlsSitYear"
            placeholder="e.g. 2025" maxlength="4" value="{{ date('Y') }}">
        </div>
        <div>
          <label class="block text-sm mb-1">Serial No</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="mlsSpecialSerial"
            name="mlsSpecialSerial" placeholder="Enter serial number">
        </div>
      </div>
    </div>

    <!-- Enhanced Full File Number Display -->
    <div class="mb-3">
      <label class="block text-xs mb-1 text-gray-700 font-medium">Generated File Number</label>
      <div class="relative w-1/2">
        <div
          class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-2 flex items-center justify-between">
          <div id="mlsPreviewFileNumber" class="text-sm font-bold text-blue-900 tracking-wide">
            Enter details above to see preview
          </div>
          <div class="flex items-center space-x-1">
            <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
            <span class="text-xs text-blue-600 font-medium">MLPP</span>
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
          <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8" id="kangisFileNoPrefix"
            name="kangisFileNoPrefix">
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
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="kangisFileNumber"
          name="kangisFileNumber" placeholder="e.g. 0001 or 2500">
      </div>
      <div>
        <label class="block text-sm mb-1">Full FileNo</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="kangisPreviewFileNumber"
          name="kangisPreviewFileNumber" value="{{ isset($result) ? ($result->kangisFileNo ?: '') : '' }}" readonly>
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
          <select class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8" id="newKangisFileNoPrefix"
            name="newKangisFileNoPrefix">

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
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="newKangisFileNumber"
          name="newKangisFileNumber" placeholder="e.g. 1586"
          value="{{ isset($result) ? ($result->newKangisFileNumber ?: '') : '' }}">
      </div>
      <div>
        <label class="block text-sm mb-1">Full FileNo</label>
        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" id="newKangisPreviewFileNumber"
          name="newKangisPreviewFileNumber" value="{{ isset($result) ? ($result->NewKANGISFileno ?: '') : '' }}"
          readonly>
      </div>
    </div>
  </div>

  <script>
    // Function to update the main fileno field based on active tab
    function updateMainFilenoField() {
      const activeTab = document.getElementById('activeFileTab').value;
      const mainFilenoField = document.getElementById('fileno');

      if (!mainFilenoField) return; // Exit if main fileno field doesn't exist

      let fileNumber = '';

      // Get the file number based on active tab (use hidden DB fields, not preview text)
      if (activeTab === 'mlsFNo') {
        fileNumber = document.getElementById('mlsFNo').value;
      } else if (activeTab === 'kangisFileNo') {
        fileNumber = document.getElementById('kangisFileNo').value;
      } else if (activeTab === 'NewKANGISFileno') {
        fileNumber = document.getElementById('NewKANGISFileno').value;
      }

      // Update the main fileno field
      mainFilenoField.value = fileNumber;

      // Trigger validation if the function exists
      if (typeof validateSurveyForm === 'function') {
        validateSurveyForm();
      }
    }

    // Updated function to maintain values across tabs
    function openFileTab(evt, tabName) {
      console.log("Opening tab:", tabName);

      // Save current values before switching tabs
      if (document.getElementById('activeFileTab').value === "mlsFNo") {
        updateMlsFileNumberPreview();
      } else if (document.getElementById('activeFileTab').value === "kangisFileNo") {
        updateKangisFileNumberPreview();
      } else if (document.getElementById('activeFileTab').value === "NewKANGISFileno") {
        updateNewKangisFileNumberPreview();
      }

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

      // Set the active tab value based on the database field names, not tab IDs
      if (tabName === "mlsFNoTab") {
        document.getElementById('activeFileTab').value = "mlsFNo";
        updateMainFilenoField();
      } else if (tabName === "kangisFileNoTab") {
        document.getElementById('activeFileTab').value = "kangisFileNo";
        updateMainFilenoField();
      } else if (tabName === "NewKANGISFilenoTab") {
        document.getElementById('activeFileTab').value = "NewKANGISFileno";
        updateMainFilenoField();
      }
    }

    // Enhanced MLS file number preview with new format and file types
    function updateMlsFileNumberPreview() {
      const prefixEl = document.getElementById('mlsFileNoPrefix');
      const yearEl = document.getElementById('mlsFileYear');
      const serialEl = document.getElementById('mlsFileSerial');
      const previewEl = document.getElementById('mlsPreviewFileNumber');
      const dbFieldEl = document.getElementById('mlsFNo');

      // Get file type from dropdown select instead of radio buttons
      const fileTypeSelect = document.getElementById('mlsFileType');
      const fileType = fileTypeSelect.value;

      // Show/hide sections based on file type
      const regularSection = document.getElementById('mlsRegularSection');
      const middlePrefixSection = document.getElementById('mlsMiddlePrefixSection');
      const specialSerialSection = document.getElementById('mlsSpecialSerialSection');
      const extensionFileSection = document.getElementById('mlsExtensionFileSection');
      const sitYearContainer = document.getElementById('mlsSitYearContainer');

      // Hide all sections first
      regularSection.classList.add('hidden');
      middlePrefixSection.classList.add('hidden');
      specialSerialSection.classList.add('hidden');
      extensionFileSection.classList.add('hidden');
      if (sitYearContainer) sitYearContainer.classList.add('hidden');

      let formatted = '';
      let displayText = '';

      if (fileType === 'regular' || fileType === 'temporary') {
        regularSection.classList.remove('hidden');

        const prefix = prefixEl.value;
        const year = yearEl.value.trim();
        const serial = serialEl.value.trim();

        if (prefix && year && serial) {
          formatted = `${prefix}-${year}-${serial.padStart(1, '0')}`;
          if (fileType === 'temporary') {
            formatted += '(T)';
          }
          displayText = formatted;
        } else if (prefix || year || serial) {
          const parts = [];
          if (prefix) parts.push(prefix);
          if (year) parts.push(year);
          if (serial) parts.push(serial.padStart(1, '0'));
          displayText = parts.join('-');
          if (parts.length > 0 && fileType === 'temporary') {
            displayText += '(T)';
          }
        }
      } else if (fileType === 'extension') {
        extensionFileSection.classList.remove('hidden');
        // Load existing MLS files on demand
        loadExistingMlsFileNumbers();
        const existing = document.getElementById('mlsExistingFileNo').value.trim();
        if (existing) {
          formatted = existing.trim().replace(/-$/, '') + ' AND EXTENSION';
          displayText = formatted;
        } else {
          displayText = 'Select an existing MLPP file number';
        }
      } else if (fileType === 'miscellaneous') {
        middlePrefixSection.classList.remove('hidden');

        const middlePrefix = document.getElementById('mlsMiddlePrefix').value.trim();
        const miscSerial = document.getElementById('mlsMiscSerial').value.trim();

        if (middlePrefix && miscSerial) {
          formatted = `MISC-${middlePrefix}-${miscSerial}`;
          displayText = formatted;
        } else if (middlePrefix || miscSerial) {
          const parts = ['MISC'];
          if (middlePrefix) parts.push(middlePrefix);
          if (miscSerial) parts.push(miscSerial);
          displayText = parts.join('-');
        }
      } else if (fileType === 'sltr' || fileType === 'sit' || fileType === 'old_mls') {
        specialSerialSection.classList.remove('hidden');
        const specialSerialEl = document.getElementById('mlsSpecialSerial');
        const specialSerial = specialSerialEl.value.trim();
        if (fileType === 'sltr') {
          specialSerialEl.placeholder = 'Enter SLTR serial (e.g., 001)';
          if (specialSerial) {
            formatted = `SLTR-${specialSerial}`;
            displayText = formatted;
          } else {
            displayText = 'SLTR-';
          }
        } else if (fileType === 'sit') {
          specialSerialEl.placeholder = 'Enter SIT serial (e.g., 001)';
          if (sitYearContainer) sitYearContainer.classList.remove('hidden');
          const sitYearEl = document.getElementById('mlsSitYear');
          const year = sitYearEl ? sitYearEl.value.trim() : '';
          if (year && specialSerial) {
            formatted = `SIT-${year}-${specialSerial}`;
            displayText = formatted;
          } else {
            displayText = 'SIT-';
          }
        } else if (fileType === 'old_mls') {
          specialSerialEl.placeholder = 'Enter Old MLPP number (e.g., 5467)';
          if (specialSerial) {
            formatted = `KN ${specialSerial}`;
            displayText = formatted;
          } else {
            displayText = 'KN ';
          }
        }
      }

      if (!displayText) {
        displayText = 'Enter details above to see preview';
      }

      previewEl.textContent = displayText;
      dbFieldEl.value = formatted ? formatted : '';

      updateMainFilenoField();
    }

    // Load existing MLS file numbers for extension selection
    function loadExistingMlsFileNumbers() {
      const select = document.getElementById('mlsExistingFileNo');
      if (!select || select.dataset.loaded === '1') return;
      select.innerHTML = '<option value="">Loading existing file numbers...</option>';
      const url = "{{ route('file-numbers.existing') }}"; // Use real route
      fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        }
      }).then(r => r.json()).then(data => {
        select.innerHTML = '<option value="">Select existing file number...</option>';
        if (Array.isArray(data) && data.length) {
          data.forEach(f => {
            const val = (f.mlsfNo || '').trim();
            if (!val) return;
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = val;
            select.appendChild(opt);
          });
          select.dataset.loaded = '1';
        } else {
          select.innerHTML = '<option value="">No existing MLPP files found</option>';
        }
      }).catch(() => {
        select.innerHTML = '<option value="">Error loading MLPP files</option>';
      });
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
        number = number.padStart(1, '0');
        numberEl.value = number;
        const formatted = prefix + ' ' + number;
        previewEl.value = formatted;
        dbFieldEl.value = formatted; // Set the database field directly
        updateMainFilenoField(); // Update main fileno field
      } else if (prefix) {
        previewEl.value = prefix;
        dbFieldEl.value = prefix;
        updateMainFilenoField();
      } else if (number) {
        previewEl.value = number;
        dbFieldEl.value = number;
        updateMainFilenoField();
      } else {
        previewEl.value = '';
        dbFieldEl.value = '';
        updateMainFilenoField();
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
        updateMainFilenoField(); // Update main fileno field
      } else if (prefix) {
        previewEl.value = prefix;
        dbFieldEl.value = prefix;
        updateMainFilenoField();
      } else if (number) {
        previewEl.value = number;
        dbFieldEl.value = number;
        updateMainFilenoField();
      } else {
        previewEl.value = '';
        dbFieldEl.value = '';
        updateMainFilenoField();
      }
    }

    // Updates the form data for submission
    function updateFormFileData() {
      // Ensure all file numbers are properly set in hidden fields via their preview updaters
      updateMlsFileNumberPreview();
      updateKangisFileNumberPreview();
      updateNewKangisFileNumberPreview();

      // Get the active tab
      const activeTab = document.getElementById('activeFileTab').value;

      // Clear non-active hidden fields so only one value is submitted
      if (activeTab === 'mlsFNo') {
        document.getElementById('kangisFileNo').value = '';
        document.getElementById('NewKANGISFileno').value = '';
      } else if (activeTab === 'kangisFileNo') {
        document.getElementById('mlsFNo').value = '';
        document.getElementById('NewKANGISFileno').value = '';
      } else if (activeTab === 'NewKANGISFileno') {
        document.getElementById('mlsFNo').value = '';
        document.getElementById('kangisFileNo').value = '';
      }

      // Sync the main fileno field
      updateMainFilenoField();

      return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
      // Initialize file number previews
      updateMlsFileNumberPreview();
      updateKangisFileNumberPreview();
      updateNewKangisFileNumberPreview();

      // Add event listeners for MLS file number preview updates
      document.getElementById('mlsFileNoPrefix').addEventListener('change', updateMlsFileNumberPreview);
      document.getElementById('mlsFileYear').addEventListener('input', updateMlsFileNumberPreview);
      document.getElementById('mlsFileSerial').addEventListener('input', updateMlsFileNumberPreview);

      // Add event listeners for new file type fields
      document.getElementById('mlsMiddlePrefix').addEventListener('input', updateMlsFileNumberPreview);
      document.getElementById('mlsMiscSerial').addEventListener('input', updateMlsFileNumberPreview);
      document.getElementById('mlsSpecialSerial').addEventListener('input', updateMlsFileNumberPreview);
      const sitYearEl = document.getElementById('mlsSitYear');
      if (sitYearEl) sitYearEl.addEventListener('input', updateMlsFileNumberPreview);

      // Add event listener for file type dropdown (changed from radio buttons)
      document.getElementById('mlsFileType').addEventListener('change', function () {
        updateMlsFileNumberPreview();
        if (this.value === 'extension') { loadExistingMlsFileNumbers(); }
      });

      // Extension: update when existing file select changes
      document.getElementById('mlsExistingFileNo').addEventListener('change', updateMlsFileNumberPreview);

      document.getElementById('kangisFileNoPrefix').addEventListener('change', updateKangisFileNumberPreview);
      document.getElementById('kangisFileNumber').addEventListener('input', updateKangisFileNumberPreview);

      document.getElementById('newKangisFileNoPrefix').addEventListener('change',
        updateNewKangisFileNumberPreview);
      document.getElementById('newKangisFileNumber').addEventListener('input',
        updateNewKangisFileNumberPreview);

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

      // Attach form submission handler to ensure all hidden fields are updated
      const form = document.querySelector('form');
      if (form) {
        form.addEventListener('submit', function (e) {
          updateFormFileData();
        });
      }
    });
  </script>
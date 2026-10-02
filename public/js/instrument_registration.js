// Use server-provided data instead of sample data
let cofoData = [];

// Base URL for API endpoints defined in blade
const baseUrl = window.baseUrl || '';

function setOriginalDisplay(el) {
  if (!el || el.dataset.originalDisplay) {
    return;
  }
  try {
    const computed = window.getComputedStyle(el).display;
    el.dataset.originalDisplay = computed && computed !== 'none' ? computed : 'block';
  } catch (error) {
    el.dataset.originalDisplay = 'block';
  }
}

function showElement(el) {
  if (!el) {
    return;
  }
  setOriginalDisplay(el);
  el.classList.remove('hidden');
  el.style.display = el.dataset.originalDisplay || 'block';
}

function hideElement(el) {
  if (!el) {
    return;
  }
  setOriginalDisplay(el);
  el.classList.add('hidden');
  el.style.display = 'none';
}

if (typeof window !== 'undefined') {
  window.showElement = window.showElement || showElement;
  window.hideElement = window.hideElement || hideElement;
}

function formatPropertyDescription(value) {
  if (!value || typeof value !== 'string') {
    return '';
  }

  const lowered = value.toLowerCase();
  return lowered.replace(/(^|[\s,;:\/\-\.'])([a-z])/g, function (match, prefix, letter) {
    return prefix + letter.toUpperCase();
  });
}

if (typeof window !== 'undefined') {
  window.formatPropertyDescription = window.formatPropertyDescription || formatPropertyDescription;
}

function normalizeFileNumber(value) {
  if (value === null || value === undefined) {
    return '';
  }
  return String(value).trim().toLowerCase();
}

function extractFileNumberFromItem(item) {
  if (!item || typeof item !== 'object') {
    return '';
  }
  return item.fileno ||
    item.fileNo ||
    item.FileNo ||
    item.file_number ||
    item.File_Number ||
    item.MLSFileNo ||
    item.mlSFileNo ||
    item.KAGISFileNo ||
    '';
}

function isSectionalInstrumentType(type) {
  if (!type) {
    return false;
  }
  const normalized = String(type).toLowerCase();
  return normalized.includes('sectional titling');
}

function isStAssignmentRegisteredForFile(fileNumber) {
  const normalizedTarget = normalizeFileNumber(fileNumber);
  if (!normalizedTarget) {
    return false;
  }
  if (typeof serverCofoData === 'undefined' || !Array.isArray(serverCofoData)) {
    return false;
  }

  return serverCofoData.some(function (record) {
    const recordFileNumber = normalizeFileNumber(
      record.fileno || record.fileNo || record.MLSFileNo || record.KAGISFileNo || record.mlSFileNo
    );
    if (!recordFileNumber || recordFileNumber !== normalizedTarget) {
      return false;
    }

    const instrumentType = (record.instrument_type || record.instrumentType || '').toLowerCase();
    const status = (record.status || '').toLowerCase();

    const isStAssignment = (instrumentType.includes('st assignment') && instrumentType.includes('transfer of title')) || instrumentType.includes('st fragmentation');

    return status === 'registered' && isStAssignment;
  });
}

if (typeof window !== 'undefined') {
  window.normalizeFileNumber = window.normalizeFileNumber || normalizeFileNumber;
  window.extractFileNumberFromItem = window.extractFileNumberFromItem || extractFileNumberFromItem;
  window.isSectionalInstrumentType = window.isSectionalInstrumentType || isSectionalInstrumentType;
  window.isStAssignmentRegisteredForFile = window.isStAssignmentRegisteredForFile || isStAssignmentRegisteredForFile;
}

// Helper function to capitalize first letter (moved to the top)
function capitalizeFirstLetter(string) {
  if (!string) return '';
  return string.charAt(0).toUpperCase() + string.slice(1);
}

// Initialize variables
// Show all instruments by default in main table
let activeTab = 'all';
let selectedUnitIndex = -1;
let selectedProperties = [];
let selectedBatchProperties = [];
let sectionalRemovalNoticeShown = false;
let nextSerialData = null;

// Update the count of selected checkboxes in batch modal
function updateSelectedCount() {
  const count = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])').length;
  const addSelectedBtn = document.getElementById('addSelectedBtn');

  if (addSelectedBtn) {
    addSelectedBtn.textContent = `Add Selected Instruments (${count})`;
    addSelectedBtn.disabled = count === 0;
    addSelectedBtn.classList.toggle('opacity-50', count === 0);
    addSelectedBtn.classList.toggle('cursor-not-allowed', count === 0);
  }

  // Update batch register button based on selectedBatchProperties array (actual added instruments)
  updateBatchRegisterButton();
}

// Separate function to update the batch register button
function updateBatchRegisterButton() {
  const batchRegisterButton = document.getElementById('batchRegisterButton');

  if (batchRegisterButton) {
    const selectedCount = selectedBatchProperties.length;
    batchRegisterButton.textContent = `Register ${selectedCount} Instruments`;

    // Only disable if there are truly no instruments
    const shouldDisable = selectedCount === 0;
    batchRegisterButton.disabled = shouldDisable;
    batchRegisterButton.classList.toggle('opacity-50', shouldDisable);
    batchRegisterButton.classList.toggle('cursor-not-allowed', shouldDisable);

    // Enable button styling when there are instruments
    if (selectedCount > 0) {
      batchRegisterButton.classList.remove('opacity-50', 'cursor-not-allowed');
      batchRegisterButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }

    // Debug logging
    console.log('updateBatchRegisterButton - selectedBatchProperties.length:', selectedCount);
    console.log('updateBatchRegisterButton - button disabled:', shouldDisable);
    console.log('updateBatchRegisterButton - selectedBatchProperties:', selectedBatchProperties);
  }
}

// Make sure critical functions are exposed to the global scope
window.populateAvailablePropertiesTable = function () {
  console.log("populateAvailablePropertiesTable called");

  // Get the table element
  const table = document.getElementById('availablePropertiesTable');
  if (!table) {
    console.error("Table element 'availablePropertiesTable' not found");
    return;
  }

  // Get search input and filter values
  const searchInput = document.getElementById('batchSearchInput')?.value?.toLowerCase() || '';
  const filterValue = document.getElementById('batchStatusFilter')?.value || '';

  console.log("Search input:", searchInput);
  console.log("Filter value:", filterValue);
  console.log("cofoData length:", cofoData ? cofoData.length : 0);

  // Validate cofoData
  if (!cofoData || !Array.isArray(cofoData) || cofoData.length === 0) {
    table.innerHTML = `
      <tr>
        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
          No instrument data available.
        </td>
      </tr>
    `;
    return;
  }

  // CRITICAL: Filter to only show PENDING instruments - exclude ALL registered ones
  let filteredData = cofoData.filter(item => {
    // Multiple checks to ensure we only get pending instruments
    const isPending = item.status === 'pending';
    const isNotRegistered = item.status !== 'registered';
    const hasNoRegDate = !item.reg_date;
    const hasNoRegNumber = !item.Deeds_Serial_No && !item.particularsRegistrationNumber;

    // Log for debugging registered instruments that might slip through
    if (!isPending) {
      console.log("Excluding non-pending instrument:", {
        id: item.id,
        fileNo: item.fileNo,
        status: item.status,
        reg_date: item.reg_date,
        Deeds_Serial_No: item.Deeds_Serial_No
      });
    }

    // Only include if it's pending AND not registered AND has no registration details
    return isPending && isNotRegistered && hasNoRegDate && hasNoRegNumber;
  });

  console.log("After pending-only filter:", filteredData.length, "instruments");
  console.log("Sample pending instruments:", filteredData.slice(0, 3).map(item => ({
    id: item.id,
    fileNo: item.fileNo,
    status: item.status,
    instrumentType: item.instrumentType
  })));

  // Apply instrument type filter based on dropdown selection
  if (filterValue) {
    filteredData = filteredData.filter(item => {
      const instrumentType = (item.instrumentType || '').toLowerCase();
      // Simple case-insensitive match since the values are exact instrument type names from DB
      return instrumentType.includes(filterValue.toLowerCase());
    });
  }

  console.log("After instrument type filter:", filteredData.length, "instruments");

  // Apply search filter if there's a search term
  if (searchInput) {
    filteredData = filteredData.filter(item => {
      const fileNo = (item.fileNo || '').toLowerCase();
      const grantor = (item.grantor || '').toLowerCase();
      const grantee = (item.grantee || '').toLowerCase();
      const instrumentType = (item.instrumentType || '').toLowerCase();

      return fileNo.includes(searchInput) ||
        grantor.includes(searchInput) ||
        grantee.includes(searchInput) ||
        instrumentType.includes(searchInput);
    });
  }

  // Debug: Print sample of filtered data
  console.log("Final filtered data:", filteredData.length, "instruments");
  console.log("Sample data after filtering:", filteredData.slice(0, 2));

  // Clear the table first
  table.innerHTML = '';

  // Check if we have data after filtering
  if (filteredData.length === 0) {
    const filterName = filterValue
      ? `${filterValue} instruments`
      : 'pending instruments';

    table.innerHTML = `
      <tr>
        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
          <div class="text-center">
            <i class="fas fa-search text-gray-300 text-3xl mb-3"></i>
            <p class="font-medium">No ${filterName} found</p>
            <p class="text-sm text-gray-400 mt-1">Only pending instruments are available for batch registration</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  // Build rows for each item
  filteredData.forEach((item) => {
    // Skip items without an id
    if (!item.id) {
      console.warn("Item without ID found:", item);
      return;
    }

    const isAlreadySelected = selectedBatchProperties.some(prop => String(prop.id) === String(item.id));
    const instrumentType = item.instrumentType || item.instrument_type || '';
    const fileNumber = extractFileNumberFromItem(item) || item.fileNo || '';
    const isSectionalInstrument = isSectionalInstrumentType(instrumentType);
    const hasRegisteredStAssignment = !isSectionalInstrument || isStAssignmentRegisteredForFile(fileNumber);
    const shouldDisableCheckbox = isSectionalInstrument && !hasRegisteredStAssignment;
    const disableReason = 'Sectional Titling CofO requires the ST Assignment (Transfer of Title) to be registered first.';

    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50';

    // Add a pending status indicator for visual confirmation
    let statusCellContent = `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
      <i class="fas fa-clock mr-1"></i> Pending
    </span>`;

    if (shouldDisableCheckbox) {
      statusCellContent += `
        <span class="block text-xs text-red-500 mt-1">
          Requires registered ST Assignment first
        </span>
      `;
      row.classList.add('opacity-60');
    }

    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap">
        <input type="checkbox" class="rounded available-property-checkbox" 
          data-id="${item.id}" 
          data-instrument-type="${instrumentType}"
          data-status="${item.status || 'pending'}"
          ${isAlreadySelected ? 'checked' : ''}>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${fileNumber || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.grantor || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.grantee || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        ${statusCellContent}
      </td>
    `;

    table.appendChild(row);

    const checkbox = row.querySelector('.available-property-checkbox');
    if (checkbox) {
      checkbox.disabled = shouldDisableCheckbox;
      if (shouldDisableCheckbox) {
        checkbox.checked = false;
        checkbox.title = disableReason;
      }
    }
  });

  // Add event listeners for checkboxes
  document.querySelectorAll('.available-property-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
  });

  // Update selected count
  updateSelectedCount();
};

// Also expose the close modal functions to global scope
window.closeBatchRegisterModal = function () {
  const modal = document.getElementById('batchRegisterModal');
  if (modal) {
    modal.style.display = 'none';
  }
};

window.closeSingleRegisterModal = function () {
  const modal = document.getElementById('singleRegisterModal');
  if (modal) {
    modal.style.display = 'none';
  }
};

// Expose the real modal‐opening logic
window.openBatchRegisterModalImplementation = function () {
  // Ensure we have the latest serial number before showing the modal
  try {
    if (typeof fetchNextSerialNumber === 'function') {
      fetchNextSerialNumber();
    }
  } catch (e) {
    console.error('Failed to fetch next serial when opening batch modal', e);
  }
  const modal = document.getElementById('batchRegisterModal');
  if (!modal) {
    console.error("Batch modal element not found");
    return;
  }
  // show modal
  modal.style.display = 'block';

  // set date/time fields
  const today = new Date();
  document.getElementById('batchDeedsDate').value = today.toISOString().split('T')[0];
  const hours = today.getHours(), minutes = today.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';
  const hh = hours % 12 || 12, mm = minutes < 10 ? '0' + minutes : minutes;
  document.getElementById('batchDeedsTime').value = `${hh}:${mm} ${ampm}`;

  // clear previous selection
  if (typeof clearSelectedProperties === 'function') {
    clearSelectedProperties();
  }

  // show loading state
  const table = document.getElementById('availablePropertiesTable');
  if (table) {
    table.innerHTML = `
      <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">
        <i class="fas fa-spinner fa-spin mr-2"></i> Loading instrument data...
      </td></tr>`;
  }

  // fetch batch data and populate table
  const baseUrl = window.location.origin;
  const filter = document.getElementById('batchStatusFilter').value;
  fetch(`${baseUrl}/instrument_registration/get-batch-data-fixed?filter=${filter}`, {
    method: 'GET',
    credentials: 'same-origin'
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Batch data received:', data);
      // Ensure data is an array
      if (Array.isArray(data)) {
        cofoData = data.map(item => ({
          id: item.id,
          fileNo: item.fileno,
          grantor: item.grantor,
          grantee: item.grantee,
          status: item.status || 'pending',
          instrumentType: item.instrument_type || '',
          source_type: item.source_type || ''
        }));
      } else if (data && data.error) {
        console.error('Server error:', data.error);
        cofoData = [];
        if (table) {
          table.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">
          Server error: ${data.error}
        </td></tr>`;
        }
        return;
      } else {
        console.error('Expected array but got:', typeof data, data);
        cofoData = [];
      }
      populateAvailablePropertiesTable();
    })
    .catch(error => {
      console.error('Error fetching batch data:', error);
      if (table) {
        table.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">
        Error loading instruments: ${error.message}
      </td></tr>`;
      }
    });

  // fetch next serial
  if (typeof fetchNextSerialNumber === 'function') {
    fetchNextSerialNumber();
  }
};

// Function to fetch batch data for a specific filter
function fetchBatchDataForFilter(filter) {
  console.log('Fetching data for filter:', filter);

  const table = document.getElementById('availablePropertiesTable');
  if (table) {
    table.innerHTML = `
      <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">
        <i class="fas fa-spinner fa-spin mr-2"></i> Loading ${filter} data...
      </td></tr>`;
  }

  const baseUrl = window.location.origin;
  fetch(`${baseUrl}/instrument_registration/get-batch-data-fixed?filter=${filter}`, {
    method: 'GET',
    credentials: 'same-origin'
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Filter data received:', data);
      // Ensure data is an array
      if (Array.isArray(data)) {
        cofoData = data.map(item => ({
          id: item.id,
          fileNo: item.fileno,
          grantor: item.grantor,
          grantee: item.grantee,
          status: item.status || 'pending',
          instrumentType: item.instrument_type || '',
          source_type: item.source_type || ''
        }));
      } else if (data && data.error) {
        console.error('Server error:', data.error);
        cofoData = [];
        if (table) {
          table.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">
          Server error: ${data.error}
        </td></tr>`;
        }
        return;
      } else {
        console.error('Expected array but got:', typeof data, data);
        cofoData = [];
      }
      populateAvailablePropertiesTable();
    })
    .catch(error => {
      console.error('Error fetching filter data:', error);
      if (table) {
        table.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">
        Error loading ${filter} data: ${error.message}
      </td></tr>`;
      }
    });
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function () {
  console.log("DOMContentLoaded event fired - initializing instruments registration page");

  // Process server data if available
  if (typeof serverCofoData !== 'undefined') {
    console.log("Server data received:", serverCofoData.length, "records");
    cofoData = serverCofoData.map(item => {
      return {
        id: item.id,
        fileNo: item.fileno || item.MLSFileNo,
        grantor: item.Grantor || '',
        grantee: item.Grantee != null ? String(item.Grantee) : '',
        instrumentType: item.instrument_type || '',
        duration: item.duration || item.leasePeriod || '',
        lga: item.lga || '',
        district: item.district || '',
        plotNumber: item.plotNumber || '',
        plotSize: item.size || '',
        plotDescription: item.propertyDescription || '',
        deeds_date: item.deeds_date || item.instrumentDate || '',
        deeds_time: item.deeds_time || '',
        rootRegistrationNumber: item.rootRegistrationNumber || item.Deeds_Serial_No || '',
        status: item.status || 'pending',  // Ensure status is set with default
        solicitorName: item.solicitorName || '',
        solicitorAddress: item.solicitorAddress || '',
        landUseType: item.landUseType || item.land_use || ''
      };
    });
    console.log("Processed data:", cofoData.length, "records");
  } else {
    console.warn("No server data available");
  }

  updateTableVisibility();
  initializeCalendars();
  fetchNextSerialNumber();

  // Add event listener for batch search input
  const batchSearchInput = document.getElementById('batchSearchInput');
  if (batchSearchInput) {
    batchSearchInput.addEventListener('input', function () {
      populateAvailablePropertiesTable();
    });
  }

  // Add event listener for batch status filter
  const batchStatusFilter = document.getElementById('batchStatusFilter');
  if (batchStatusFilter) {
    batchStatusFilter.addEventListener('change', function () {
      console.log('Filter changed to:', this.value);
      fetchBatchDataForFilter(this.value);
      // Fetches the next serial number for this specific instrument type
      fetchNextSerialNumber(this.value);
    });
  }

  // Add event listener for the search input
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      filterTableByFileNo(this.value);
    });
  }
});

// Fetch next available serial number
function fetchNextSerialNumber(instrumentType = null) {
  // Create headers
  const headers = {
    'Content-Type': 'application/json'
  };

  // Try to get CSRF token if available
  const csrfToken = document.querySelector('meta[name="csrf-token"]');
  if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
  } else {
    console.warn('CSRF token meta tag not found! CSRF protection may cause request to fail.');
  }

  // Add cache-busting parameter to ensure fresh data
  const cacheBuster = new Date().getTime();

  // Use the correct route for the instrument_registration controller
  let url = `${baseUrl}/instrument_registration/get-next-serial?_=${cacheBuster}`;
  if (instrumentType) {
    url += `&instrument_type=${encodeURIComponent(instrumentType)}`;
  }

  return fetch(url, {
    method: 'GET',
    headers: headers,
    credentials: 'same-origin', // Include cookies in the request
    cache: 'no-cache' // Ensure no caching
  })
    .then(response => {
      if (!response.ok) {
        console.error(`Server returned ${response.status}: ${response.statusText}`);
        // For debugging - log the actual response content
        return response.text().then(text => {
          console.error('Response content:', text);
          throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        });
      }
      return response.json();
    })
    .then(data => {
      console.log('Serial number data fetched:', data); // Debug log

      // Update single registration form
      if (document.getElementById('serialNo')) {
        document.getElementById('serialNo').value = data.serial_no;
        document.getElementById('pageNo').value = data.page_no;
        document.getElementById('volumeNo').value = data.volume_no;
        document.getElementById('deedsSerialNo').value = data.deeds_serial_no;
      }

      // Update batch registration form
      const batchNextSerialNo = document.getElementById('batchNextSerialNo');
      if (batchNextSerialNo) {
        batchNextSerialNo.textContent = data.deeds_serial_no;
      }

      // Also update quick-batch display if present
      const quickBatchNextSerialNo = document.getElementById('quickBatchNextSerialNo');
      if (quickBatchNextSerialNo) {
        quickBatchNextSerialNo.textContent = data.deeds_serial_no;
      }

      // Store the data globally for later use
      window.nextSerialData = data;
      nextSerialData = data;

      return data;
    })
    .catch(error => {
      console.error('Error fetching next serial number:', error);
      showToast('Error', 'Failed to get next serial number: ' + error.message, 'error');

      // Set default values in case of error
      const defaultData = {
        serial_no: 1,
        page_no: 1,
        volume_no: 1,
        deeds_serial_no: '1/1/1'
      };

      window.nextSerialData = defaultData;
      nextSerialData = defaultData;

      // Update UI with default values
      if (document.getElementById('batchNextSerialNo')) {
        document.getElementById('batchNextSerialNo').textContent = '1/1/1 (default)';
      }
      if (document.getElementById('quickBatchNextSerialNo')) {
        document.getElementById('quickBatchNextSerialNo').textContent = '1/1/1 (default)';
      }

      return defaultData;
    });
}

// Retry helper used by modal retry buttons
window.retryFetchSerialNumber = function () {
  // Re-run the primary fetch and also the quick-batch specific fetch if available
  try {
    fetchNextSerialNumber();
    if (typeof fetchNextSerialNumberForQuickBatch === 'function') {
      fetchNextSerialNumberForQuickBatch();
    }
  } catch (e) {
    console.error('Retry fetching serial number failed', e);
  }
};

// Function to manually retry fetching the serial number
function retryFetchSerialNumber() {
  const debugElem = document.getElementById('serialNumberDebug');
  const debugMsg = document.getElementById('serialNumberDebugMsg');

  if (debugElem) debugElem.classList.remove('hidden');
  if (debugMsg) debugMsg.textContent = 'Retrying...';
  // Use the centralized fetch function which updates both batch and quick-batch UI
  fetchNextSerialNumber()
    .then(data => {
      if (debugMsg) debugMsg.textContent = `Success! Data: ${JSON.stringify(data)}`;
      // Hide debug info after success
      setTimeout(() => {
        if (debugElem) debugElem.classList.add('hidden');
      }, 2000);
    })
    .catch(error => {
      if (debugMsg) debugMsg.textContent = `Error: ${error.message}`;
      console.error('Retry failed:', error);
    });
}

// Helper function to show/hide no results message
function updateNoResultsMessage(hasVisibleRows) {
  const noResultsRow = document.getElementById('noResultsRow');
  if (!hasVisibleRows) {
    if (!noResultsRow) {
      const tableBody = document.getElementById('cofoTableBody');
      const newNoResultsRow = document.createElement('tr');
      newNoResultsRow.id = 'noResultsRow';
      newNoResultsRow.innerHTML = `
        <td colspan="16" class="px-6 py-10 text-center text-gray-500">
          No results found.
        </td>
      `;
      tableBody.appendChild(newNoResultsRow);
    }
  } else if (noResultsRow) {
    // Remove the "No results" row if we have visible rows
    noResultsRow.remove();
  }
}

// Update table rows visibility based on active tab
function updateTableVisibility() {
  const rows = document.querySelectorAll('.cofo-row');

  // Track if we have visible rows
  let hasVisibleRows = false;

  rows.forEach(row => {
    const status = row.getAttribute('data-status');
    if (activeTab === 'all' || status === activeTab) {
      row.style.display = '';
      hasVisibleRows = true;
    } else {
      row.style.display = 'none';
    }
  });

  // Update "No results" message
  updateNoResultsMessage(hasVisibleRows);

  // Also apply any active search filter
  const searchInput = document.getElementById('searchInput');
  if (searchInput && searchInput.value.trim() !== '') {
    filterTableByFileNo(searchInput.value);
  }
}

// Filter table by ST FileNO
function filterTableByFileNo(searchTerm) {
  const rows = document.querySelectorAll('.cofo-row');
  const normalizedSearchTerm = searchTerm.toLowerCase().trim();

  // If empty search, just revert to tab filtering
  if (normalizedSearchTerm === '') {
    updateTableVisibility();
    return;
  }

  // Track if we have visible rows
  let hasVisibleRows = false;

  rows.forEach(row => {
    const status = row.getAttribute('data-status');
    // Skip rows that don't match the active tab
    if (activeTab !== 'all' && status !== activeTab) {
      row.style.display = 'none';
      return;
    }

    const fileNoElement = row.querySelector('.file-number');
    const fileNo = fileNoElement ? fileNoElement.textContent.toLowerCase() : '';

    if (fileNo.includes(normalizedSearchTerm)) {
      row.style.display = '';
      hasVisibleRows = true;
    } else {
      row.style.display = 'none';
    }
  });

  // Update "No results" message
  updateNoResultsMessage(hasVisibleRows);
}

// Switch between tabs
function switchTab(tab, element) {
  activeTab = tab;

  // Update active tab styling
  document.querySelectorAll('.tab-active').forEach(el => {
    el.classList.remove('tab-active');
  });
  element.classList.add('tab-active');

  // Update table visibility
  updateTableVisibility();
}

// Toggle dropdown menu
function toggleDropdown() {
  document.getElementById("registerDropdown").classList.toggle("show");
}

// Close dropdown when clicking outside
window.onclick = function (event) {
  if (!event.target.matches('.dropdown button')) {
    const dropdowns = document.getElementsByClassName("dropdown-content");
    for (let i = 0; i < dropdowns.length; i++) {
      const openDropdown = dropdowns[i];
      if (openDropdown.classList.contains('show')) {
        openDropdown.classList.remove('show');
      }
    }
  }
}

// Open single register modal with data
function openSingleRegisterModalWithData(id) {
  // Show loading state
  Swal.fire({
    title: 'Loading',
    text: 'Fetching instrument details...',
    icon: 'info',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });

  // Convert id to string to ensure consistent comparison
  id = String(id);

  // A MOTHER aggregate row ("mother_assign_<id>" / "mother_cofo_<id>") is not a
  // record — it stands for the pending units beneath it. Hand it to the batch
  // flow, which knows how to expand it; passing its synthetic id on to the
  // single-register endpoint fails as an int conversion in SQL Server.
  if (typeof window.openAggregateRegistration === 'function'
      && window.openAggregateRegistration(id)) {
    Swal.close();
    return;
  }

  // Find the application by id - first try cofoData, then serverCofoData
  let application = cofoData.find(item => String(item.id) === id);

  // If not found in cofoData, try serverCofoData (main table data)
  if (!application && typeof serverCofoData !== 'undefined') {
    const serverItem = serverCofoData.find(item => String(item.id) === id);
    if (serverItem) {
      application = {
        id: serverItem.id,
        fileNo: serverItem.fileno || serverItem.MLSFileNo,
        grantor: serverItem.Grantor || '',
        grantee: serverItem.Grantee != null ? String(serverItem.Grantee) : '',
        instrumentType: serverItem.instrument_type || '',
        duration: serverItem.duration || serverItem.leasePeriod || '',
        lga: serverItem.lga || '',
        district: serverItem.district || '',
        plotNumber: serverItem.plotNumber || '',
        plotSize: serverItem.size || '',
        plotDescription: serverItem.propertyDescription || '',
        deeds_date: serverItem.deeds_date || serverItem.instrumentDate || '',
        deeds_time: serverItem.deeds_time || '',
        rootRegistrationNumber: serverItem.rootRegistrationNumber || serverItem.Deeds_Serial_No || '',
        status: serverItem.status || 'pending',
        solicitorName: serverItem.solicitorName || '',
        solicitorAddress: serverItem.solicitorAddress || '',
        landUseType: serverItem.landUseType || serverItem.land_use || ''
      };
    }
  }

  if (!application) {
    console.error('Instrument not found with ID:', id);
    console.log('Available cofoData IDs:', cofoData.map(item => item.id));
    if (typeof serverCofoData !== 'undefined') {
      console.log('Available serverCofoData IDs:', serverCofoData.map(item => item.id));
    }
    Swal.fire({
      title: 'Error',
      text: 'Instrument not found. Please try again.',
      icon: 'error'
    });
    return;
  }

  // Show the modal
  const modal = document.getElementById('singleRegisterModal');
  const searchSection = document.getElementById('unitSearchSection');
  const detailsSection = document.getElementById('unitDetailsSection');

  showElement(modal);
  hideElement(searchSection);
  showElement(detailsSection);

  // Close loading dialog
  Swal.close();

  // Ensure the status is set to 'pending'
  application.status = 'pending';

  // Set application data with flexible fallbacks
  const modalFileNo = application.fileno || application.fileNo || application.MLSFileNo || application.KAGISFileNo || 'N/A';
  const modalPropertyDescription = application.plotDescription || application.propertyDescription || application.PropertyDescription || 'No description available';
  const formattedPropertyDescription = formatPropertyDescription(modalPropertyDescription);
  const modalInstrumentType = application.instrumentType || application.instrument_type || '';
  const modalDuration = application.duration || application.leasePeriod || '';
  const modalGrantor = application.grantor || application.Grantor || '';
  const modalGrantee = application.grantee || application.Grantee || '';
  const modalLga = application.lga || application.LGA || '';
  const modalDistrict = application.district || application.District || '';
  const modalPlotNumber = application.plotNumber || application.plot_number || '';
  const modalPlotSize = application.plotSize || application.plot_size || application.size || '';
  const modalPlotDescription = application.plotDescription || application.propertyDescription || application.PropertyDescription || '';

  document.getElementById('selectedFileNo').textContent = modalFileNo;
  document.getElementById('selectedProperty').textContent = formattedPropertyDescription;

  // Populate form fields
  document.getElementById('formInstrumentId').value = application.id;
  document.getElementById('instrumentType').value = modalInstrumentType;
  document.getElementById('duration').value = modalDuration;
  document.getElementById('grantor').value = modalGrantor;
  document.getElementById('grantee').value = modalGrantee;
  document.getElementById('lga').value = modalLga;
  document.getElementById('district').value = modalDistrict;
  document.getElementById('plotNumber').value = modalPlotNumber;
  document.getElementById('plotSize').value = modalPlotSize;
  document.getElementById('plotDescription').value = modalPlotDescription;

  // Set current date and time
  const today = new Date();
  document.getElementById('deedsDate').value = today.toISOString().split('T')[0];

  // Set current time (formatted as HH:MM AM/PM)
  const hours = today.getHours();
  const minutes = today.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';
  const formattedHours = hours % 12 || 12;
  const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
  document.getElementById('deedsTime').value = `${formattedHours}:${formattedMinutes} ${ampm}`;

  // Fetch the next serial number with fresh data
  fetchNextSerialNumber(modalInstrumentType).then(() => {
    console.log('Serial number fetched for single registration modal');
  }).catch(error => {
    console.error('Failed to fetch serial number for single registration:', error);
  });
}

// Open single register modal
function openSingleRegisterModal() {
  const modal = document.getElementById('singleRegisterModal');
  const searchSection = document.getElementById('unitSearchSection');
  const detailsSection = document.getElementById('unitDetailsSection');

  showElement(modal);
  showElement(searchSection);
  hideElement(detailsSection);

  // Populate unit search results with real data
  const unitSearchResults = document.getElementById('unitSearchResults');
  unitSearchResults.innerHTML = '';

  const pendingApplications = cofoData.filter(item => item.status === 'pending');

  if (pendingApplications.length === 0) {
    unitSearchResults.innerHTML = `
      <tr>
        <td colspan="6" class="px-6 py-10 text-center text-gray-500">
          No pending applications found.
        </td>
      </tr>
    `;
    return;
  }

  pendingApplications.forEach((item, index) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${item.stmRef}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.unitNo}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.blockNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.owner}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        <span class="badge badge-pending">Pending</span>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
        <button class="bg-blue-600 text-white px-3 py-1 rounded-md text-sm" onclick="openSingleRegisterModalWithData(${item.mother_id})">Select</button>
      </td>
    `;
    unitSearchResults.appendChild(row);
  });
}

// Close single register modal - Fix close modal function
function closeSingleRegisterModal() {
  const modal = document.getElementById('singleRegisterModal');
  if (modal) {
    hideElement(modal);
  }
  backToUnitSearch();
}

// Close batch register modal - Fix close modal function
function closeBatchRegisterModal() {
  const modal = document.getElementById('batchRegisterModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

// Add selected properties to batch - Fix function to correctly add selected instruments
function addSelectedToBatch() {
  console.log("addSelectedToBatch called");
  const selectedCheckboxes = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])');
  console.log("Selected checkboxes:", selectedCheckboxes.length);

  if (selectedCheckboxes.length === 0) {
    showToast('Warning', 'No properties selected', 'info');
    return;
  }

  try {
    // Disable the button while processing
    const addSelectedBtn = document.getElementById('addSelectedBtn');
    if (addSelectedBtn) {
      addSelectedBtn.disabled = true;
      addSelectedBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    }

    // Get next serial number if not already fetched
    if (!nextSerialData) {
      const typeFilter = document.getElementById('batchStatusFilter');
      const instrumentType = typeFilter ? typeFilter.value : null;

      fetchNextSerialNumber(instrumentType)
        .then(() => {
          processSelectedProperties(selectedCheckboxes);
          // Re-enable the button
          if (addSelectedBtn) {
            addSelectedBtn.disabled = false;
            addSelectedBtn.textContent = `Add Selected Instruments (${selectedCheckboxes.length})`;
          }
        })
        .catch((error) => {
          console.error("Error fetching serial number:", error);
          // Re-enable the button even if there's an error
          if (addSelectedBtn) {
            addSelectedBtn.disabled = false;
            addSelectedBtn.textContent = `Add Selected Instruments (${selectedCheckboxes.length})`;
          }
          // Show error
          Swal.fire({
            title: 'Error',
            text: `Failed to get serial numbers: ${error.message}`,
            icon: 'error'
          });
        });
    } else {
      processSelectedProperties(selectedCheckboxes);
      updateSelectedCount();
      // Re-enable the button
      if (addSelectedBtn) {
        addSelectedBtn.disabled = false;
        const refreshedCount = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])').length;
        addSelectedBtn.textContent = `Add Selected Instruments (${refreshedCount})`;
      }
    }
  } catch (error) {
    console.error("Error in addSelectedToBatch:", error);
    Swal.fire({
      title: 'Error',
      text: `An error occurred: ${error.message}`,
      icon: 'error'
    });
  }
}

// Process selected properties - Fix to properly handle selected instruments
function processSelectedProperties(selectedCheckboxes) {
  try {
    const selections = Array.from(selectedCheckboxes).map(checkbox => {
      const id = checkbox.getAttribute('data-id');
      console.log("Processing checkbox with ID:", id);
      const item = cofoData.find(entry => String(entry.id) === String(id));
      if (!item) {
        console.warn("Could not find data for ID:", id);
        return null;
      }
      return { checkbox, item };
    }).filter(Boolean);

    console.log("New properties to add:", selections.length);

    if (selections.length === 0) {
      return;
    }

    const blockedSectional = [];
    const allowedSelections = [];

    selections.forEach(selection => {
      const instrumentType = selection.item.instrumentType || selection.item.instrument_type || '';
      const fileNumber = extractFileNumberFromItem(selection.item) || selection.item.fileNo || '';

      if (isSectionalInstrumentType(instrumentType) && !isStAssignmentRegisteredForFile(fileNumber)) {
        blockedSectional.push({ selection, fileNumber });
        selection.checkbox.checked = false;
        return;
      }

      allowedSelections.push(selection);
    });

    if (blockedSectional.length > 0) {
      const blockedList = blockedSectional.map(entry => entry.fileNumber || entry.selection.item.id || 'Unknown').join(', ');
      Swal.fire({
        title: 'Registration Dependency',
        text: `Sectional Titling CofO instrument(s) (${blockedList}) cannot be added until the ST Assignment (Transfer of Title) is registered.`,
        icon: 'warning'
      });
    }

    if (allowedSelections.length === 0) {
      updateSelectedCount();
      return;
    }

    const newProperties = allowedSelections.map(entry => entry.item);

    // Calculate serial numbers for new properties
    let currentSerialNo = nextSerialData ? nextSerialData.serial_no : 1;
    let currentPageNo = nextSerialData ? nextSerialData.page_no : 1;
    let currentVolumeNo = nextSerialData ? nextSerialData.volume_no : 1;

    // If we already have properties, start from the last one's next serial number
    if (selectedBatchProperties.length > 0) {
      const lastProperty = selectedBatchProperties[selectedBatchProperties.length - 1];
      currentSerialNo = lastProperty.serialData.serial_no + 1;
      currentPageNo = lastProperty.serialData.page_no + 1;
      currentVolumeNo = lastProperty.serialData.volume_no;

      // Check if we need to start a new volume
      if (currentPageNo > 300) {
        currentVolumeNo++;
        currentPageNo = 1;
        currentSerialNo = 1;
      }
    }

    // Add serial data to new properties
    const propertiesWithSerial = newProperties.map((property, index) => {
      let serialNo = currentSerialNo + index;
      let pageNo = currentPageNo + index;
      let volumeNo = currentVolumeNo;

      // Check if we need to move to next volume
      if (pageNo > 300) {
        volumeNo++;
        pageNo = (pageNo - 1) % 300 + 1; // 1-300 range
        serialNo = pageNo; // Reset serial to match page within new volume
      }

      const serialData = {
        serial_no: serialNo,
        page_no: pageNo,
        volume_no: volumeNo,
        deeds_serial_no: `${serialNo}/${pageNo}/${volumeNo}`
      };

      return {
        ...property,
        serialData
      };
    });

    // Log and add to selectedBatchProperties
    console.log("Adding properties with serial:", propertiesWithSerial);
    selectedBatchProperties = [...selectedBatchProperties, ...propertiesWithSerial];
    console.log("Updated selectedBatchProperties:", selectedBatchProperties.length);

    // Update next serial data for future additions
    if (propertiesWithSerial.length > 0) {
      const lastProperty = propertiesWithSerial[propertiesWithSerial.length - 1];
      nextSerialData = {
        serial_no: lastProperty.serialData.serial_no + 1,
        page_no: lastProperty.serialData.page_no + 1,
        volume_no: lastProperty.serialData.volume_no,
        deeds_serial_no: `${lastProperty.serialData.serial_no + 1}/${lastProperty.serialData.page_no + 1}/${lastProperty.serialData.volume_no}`
      };

      // Check if we need to start a new volume
      if (nextSerialData.page_no > 300) {
        nextSerialData.volume_no++;
        nextSerialData.page_no = 1;
        nextSerialData.serial_no = 1;
        nextSerialData.deeds_serial_no = `1/1/${nextSerialData.volume_no}`;
      }
    }

    // Reflect the new next serial number in the UI
    const nextSpan = document.getElementById('batchNextSerialNo');
    if (nextSpan && nextSerialData && nextSerialData.deeds_serial_no) {
      nextSpan.textContent = nextSerialData.deeds_serial_no;
    }

    // Update the UI - Critical part that updates the selected properties table
    updateSelectedPropertiesTable();

    // Refresh available properties to show disabled checkboxes
    populateAvailablePropertiesTable();

    // Show success message
    Swal.fire({
      title: 'Success!',
      text: `${newProperties.length} instrument${newProperties.length === 1 ? '' : 's'} added to batch`,
      icon: 'success',
      toast: true,
      position: 'bottom-end',
      showConfirmButton: false,
      timer: 3000
    });

    updateSelectedCount();
  } catch (error) {
    console.error("Error in processSelectedProperties:", error);
    Swal.fire({
      title: 'Error',
      text: `An error occurred: ${error.message}`,
      icon: 'error'
    });
  }
}

// Fix updateSelectedPropertiesTable function to properly display selected instruments
function updateSelectedPropertiesTable() {
  console.log("updateSelectedPropertiesTable called with", selectedBatchProperties.length, "properties");
  const table = document.getElementById('selectedPropertiesTable');
  if (!table) {
    console.error("Selected properties table not found");
    return;
  }

  const invalidSectionals = [];
  selectedBatchProperties = selectedBatchProperties.filter(function (property) {
    const instrumentType = property.instrumentType || property.instrument_type || '';
    if (isSectionalInstrumentType(instrumentType)) {
      const fileNumber = extractFileNumberFromItem(property) || property.fileNo || '';
      if (!isStAssignmentRegisteredForFile(fileNumber)) {
        invalidSectionals.push(fileNumber || property.id || 'Unknown');
        return false;
      }
    }
    return true;
  });

  if (invalidSectionals.length > 0 && !sectionalRemovalNoticeShown) {
    Swal.fire({
      title: 'Dependency Not Met',
      text: `Removed ${invalidSectionals.length} Sectional Titling CofO instrument${invalidSectionals.length === 1 ? '' : 's'} (${invalidSectionals.join(', ')}) because their ST Assignment (Transfer of Title) is not registered.`,
      icon: 'info'
    });
    sectionalRemovalNoticeShown = true;
    updateSelectedCount();
  }

  // Clear the table
  table.innerHTML = '';

  // Update register button state using the centralized function
  updateBatchRegisterButton();

  // Show/hide no selected properties message
  if (selectedBatchProperties.length === 0) {
    table.innerHTML = `
      <tr id="noSelectedPropertiesRow">
        <td colspan="6" class="px-6 py-10 text-center text-gray-500">
          No instruments selected for registration. Use the table above to select instruments.
        </td>
      </tr>
    `;
    return;
  }

  // Populate table with selected properties
  selectedBatchProperties.forEach((property, index) => {
    console.log("Adding property to table:", property.fileNo);
    const row = document.createElement('tr');
    row.setAttribute('data-index', index);
    row.className = 'hover:bg-gray-50';

    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.fileNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.grantor || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.grantee || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        <input type="text" class="w-full px-3 py-1 border rounded-md bg-gray-100" value="${property.instrumentType || 'N/A'}" readonly>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${property.serialData.deeds_serial_no}</td>
      <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
        <button class="text-red-600 hover:text-red-800" onclick="removePropertyFromBatch(${index})">
          <i class="fas fa-times"></i>
        </button>
      </td>
    `;

    table.appendChild(row);
  });

  // Instrument types are now automatically determined, no event listeners needed
}

// Remove property from batch - Make sure it's properly defined
function removePropertyFromBatch(index) {
  console.log("Removing property at index:", index);
  // Remove the property from the array
  if (index >= 0 && index < selectedBatchProperties.length) {
    selectedBatchProperties.splice(index, 1);
    if (selectedBatchProperties.length === 0) {
      sectionalRemovalNoticeShown = false;
    }
    // Update the table
    updateSelectedPropertiesTable();
    // Refresh available properties
    populateAvailablePropertiesTable();

    // Recalculate next serial number display
    if (selectedBatchProperties.length > 0) {
      const last = selectedBatchProperties[selectedBatchProperties.length - 1].serialData;
      if (last) {
        nextSerialData = {
          serial_no: last.serial_no + 1,
          page_no: last.page_no + 1,
          volume_no: last.volume_no,
          deeds_serial_no: `${last.serial_no + 1}/${last.page_no + 1}/${last.volume_no}`
        };
        if (nextSerialData.page_no > 300) {
          nextSerialData.volume_no++;
          nextSerialData.page_no = 1;
          nextSerialData.serial_no = 1;
          nextSerialData.deeds_serial_no = `1/1/${nextSerialData.volume_no}`;
        }
        const span = document.getElementById('batchNextSerialNo');
        if (span) span.textContent = nextSerialData.deeds_serial_no;
      }
    } else {
      // No items left, refetch from server to reset the value
      if (typeof fetchNextSerialNumber === 'function') {
        fetchNextSerialNumber();
      }
    }
  } else {
    console.error("Invalid index for removal:", index);
  }
}

// Clear all selected properties with confirmation
function clearSelectedProperties() {
  if (selectedBatchProperties.length === 0) return;

  Swal.fire({
    title: 'Clear Selection?',
    text: 'Are you sure you want to clear all selected instruments?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, clear them'
  }).then((result) => {
    if (result.isConfirmed) {
      selectedBatchProperties = [];
      sectionalRemovalNoticeShown = false;
      updateSelectedPropertiesTable();
      populateAvailablePropertiesTable();
      // Reset next reg number from server
      if (typeof fetchNextSerialNumber === 'function') {
        fetchNextSerialNumber();
      }
      Swal.fire('Cleared!', 'Your selected instruments have been cleared.', 'success');
    }
  });
}

// Hide toast is not needed with SweetAlert
function hideToast() {
  // Not needed, as SweetAlert handles this automatically
}

// Setup the server data from the global variable when it exists
document.addEventListener('DOMContentLoaded', function () {
  // Check if the serverCofoData variable exists globally
  if (typeof serverCofoData !== 'undefined') {
    // Process the data
    cofoData = serverCofoData.map(item => {
      return {
        id: item.id,
        fileNo: item.fileno || item.MLSFileNo,
        grantor: item.Grantor || '',
        grantee: item.Grantee != null ? String(item.Grantee) : '',
        instrumentType: item.instrument_type || '',
        duration: item.duration || item.leasePeriod || '',
        lga: item.lga || '',
        district: item.district || '',
        plotNumber: item.plotNumber || '',
        plotSize: item.size || '',
        plotDescription: item.propertyDescription || '',
        deeds_date: item.deeds_date || item.instrumentDate || '',
        deeds_time: item.deeds_time || '',
        rootRegistrationNumber: item.rootRegistrationNumber || item.Deeds_Serial_No || '',
        status: item.status,
        solicitorName: item.solicitorName || '',
        solicitorAddress: item.solicitorAddress || '',
        landUseType: item.landUseType || item.land_use || ''
      };
    });
  }
});

// Add a dummy initializeCalendars function to prevent ReferenceError
function initializeCalendars() {
  // No-op: Placeholder for calendar initialization if needed in the future
  // You can integrate a date picker here if required
  console.log("initializeCalendars called (no-op)");
}

// Submit single registration
function submitSingleRegistration() {
  const registerBtn = document.getElementById('registerButton');
  if (registerBtn) {
    registerBtn.disabled = true;
    registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
  }

  const instrumentType = document.getElementById('instrumentType').value;
  const grantor = document.getElementById('grantor').value;
  const grantee = document.getElementById('grantee').value;
  const deedsTime = document.getElementById('deedsTime').value;
  const deedsDate = document.getElementById('deedsDate').value;

  const data = {
    mother_application_id: document.getElementById('formInstrumentId').value,
    file_no: document.getElementById('selectedFileNo').textContent,
    instrument_type: instrumentType,
    Grantor: grantor,
    GrantorAddress: "",
    Grantee: grantee,
    GranteeAddress: "",
    duration: document.getElementById('duration').value,
    propertyDescription: document.getElementById('plotDescription').value,
    lga: document.getElementById('lga').value,
    district: document.getElementById('district').value,
    plotNumber: document.getElementById('plotNumber').value,
    plotSize: document.getElementById('plotSize').value,
    deeds_time: deedsTime,
    deeds_date: deedsDate
  };

  // Use the application's base URL instead of a Blade route
  const baseUrl = window.location.origin;
  fetch(`${baseUrl}/instrument_registration/register-single`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(data)
  })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        let itype = document.getElementById('instrumentType').value;
        let isSyncable = itype.includes('Deed of Gift') || itype.includes('Deed of Assignment') || itype.includes('Power of Attorney');
        
        if (isSyncable) {
          Swal.fire({
            title: 'Registration Successful',
            icon: 'success',
            html: `
              <p class="mb-3">${res.message}</p>
              <div class="text-left bg-blue-50 p-3 rounded-lg border border-blue-100">
                <p class="text-xs font-bold text-blue-800 mb-2"><i class="fas fa-sync-alt mr-1"></i> SYSTEM SYNCHRONIZATION:</p>
                <ul class="text-[11px] text-blue-700 space-y-1 list-disc pl-4">
                  <li><strong>file_indexings:</strong> updated file_title</li>
                  <li><strong>customers_staging:</strong> updated customer_name</li>
                  <li><strong>entities_staging:</strong> updated entity_name</li>
                  <li><strong>fileNumber:</strong> updated FileName</li>
                </ul>
              </div>
            `
          }).then(() => {
            closeSingleRegisterModal();
            window.location.reload();
          });
        } else {
          Swal.fire('Success', res.message, 'success').then(() => {
            closeSingleRegisterModal();
            window.location.reload();
          });
        }
      } else {
        Swal.fire('Error', res.error || res.message, 'error');
        if (registerBtn) {
          registerBtn.disabled = false;
          registerBtn.textContent = 'Register Instrument';
        }
      }
    })
    .catch(e => {
      console.error(e);
      Swal.fire('Error', 'Request failed', 'error');
      if (registerBtn) {
        registerBtn.disabled = false;
        registerBtn.textContent = 'Register Instrument';
      }
    });
}

// Submit batch registration
function submitBatchRegistration() {
  // Debug logging
  console.log('submitBatchRegistration called');
  console.log('selectedBatchProperties:', selectedBatchProperties);
  console.log('selectedBatchProperties.length:', selectedBatchProperties.length);

  // Check if we have any selected properties
  if (selectedBatchProperties.length === 0) {
    console.error('No batch properties selected!');

    // Check if user has selected checkboxes but not added them to batch
    const checkedCount = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])').length;
    if (checkedCount > 0) {
      // Auto-add selected instruments to batch if they haven't been added yet
      console.log('Auto-adding selected instruments to batch...');
      addSelectedToBatch();

      // Wait a moment for the addition to complete, then proceed
      setTimeout(() => {
        if (selectedBatchProperties.length > 0) {
          console.log('Auto-addition successful, proceeding with registration...');
          submitBatchRegistration(); // Recursive call after auto-addition
        } else {
          Swal.fire({
            title: 'Instruments Not Added to Batch',
            html: `
              <p>You have selected <strong>${checkedCount} instruments</strong> but they couldn't be automatically added to the batch.</p>
              <p class="mt-2">Please click the <strong>"Add Selected Instruments"</strong> button first to add them to the batch for registration.</p>
            `,
            icon: 'warning',
            confirmButtonText: 'OK, I understand',
            confirmButtonColor: '#3085d6'
          });
        }
      }, 500);
      return;
    } else {
      Swal.fire('Error', 'No instruments selected for batch registration. Please select instruments first.', 'error');
    }
    return;
  }

  // Use fallbacks for missing data
  const deedsTime = document.getElementById('batchDeedsTime')?.value || new Date().toLocaleTimeString();
  const deedsDate = document.getElementById('batchDeedsDate')?.value || new Date().toISOString().split('T')[0];

  // Validate required fields
  if (!deedsTime || !deedsDate) {
    Swal.fire('Error', 'Please provide deeds date and time', 'error');
    return;
  }

  // Process batch entries with proper fallbacks for missing data
  const batchEntries = selectedBatchProperties.map((p, index) => {
    // Ensure we have a valid application ID
    let applicationId = p.id;
    if (!applicationId) {
      console.error('Property without ID found:', p);
      return null;
    }

    return {
      application_id: applicationId,
      file_no: p.fileNo || 'N/A',
      instrument_type: p.instrumentType || 'N/A',
      grantor: p.grantor || 'N/A',
      grantorAddress: p.grantorAddress || '',
      grantee: p.grantee || 'N/A',
      granteeAddress: p.granteeAddress || '',
      duration: p.duration || 'N/A',
      propertyDescription: p.plotDescription || p.propertyDescription || 'N/A',
      lga: p.lga || 'N/A',
      district: p.district || 'N/A',
      plotNumber: p.plotNumber || 'N/A',
      size: p.plotSize || p.size || 'N/A',
      serial_no: (p.serialData && p.serialData.serial_no) || (index + 1),
      page_no: (p.serialData && p.serialData.page_no) || (index + 1),
      volume_no: (p.serialData && p.serialData.volume_no) || 1,
      deeds_time: deedsTime,
      deeds_date: deedsDate
    };
  }).filter(entry => entry !== null); // Remove any null entries

  console.log('Final batchEntries to submit:', batchEntries);
  console.log('Number of entries:', batchEntries.length);

  if (batchEntries.length === 0) {
    Swal.fire('Error', 'No valid instruments to register. Please check your selection.', 'error');
    return;
  }

  // Show loading state
  Swal.fire({
    title: 'Processing Registration',
    text: `Registering ${batchEntries.length} instruments...`,
    icon: 'info',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });

  // Use the application's base URL instead of a Blade route
  const baseUrl = window.location.origin;
  fetch(`${baseUrl}/instrument_registration/register-batch`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      batch_entries: batchEntries,
      deeds_time: deedsTime,
      deeds_date: deedsDate
    })
  })
    .then(response => {
      console.log('Response status:', response.status);
      if (!response.ok) {
        return response.text().then(text => {
          console.error('Error response:', text);
          throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        });
      }
      return response.json();
    })
    .then(res => {
      console.log('Registration response:', res);
      Swal.close();
      if (res.success) {
        let successHtml = res.batch_session_id
          ? `<p class="mb-2">${res.message}</p><strong>Batch Session:</strong> ${res.batch_session_id}`
          : res.message;
        
        successHtml += `
          <div class="mt-4 text-left bg-blue-50 p-3 rounded-lg border border-blue-100">
            <p class="text-xs font-bold text-blue-800 mb-2"><i class="fas fa-sync-alt mr-1"></i> BATCH SYNCHRONIZATION:</p>
            <p class="text-[10px] text-blue-700 mb-2">Applicable deed party names synchronized across:</p>
            <ul class="text-[10px] text-blue-700 space-y-1 list-disc pl-4">
              <li><strong>file_indexings:</strong> file_title updated</li>
              <li><strong>customers_staging:</strong> customer_name updated</li>
              <li><strong>entities_staging:</strong> entity_name updated</li>
              <li><strong>fileNumber:</strong> FileName updated</li>
            </ul>
          </div>
        `;

        Swal.fire({
          title: 'Success',
          html: successHtml,
          icon: 'success'
        }).then(() => {
          closeBatchRegisterModal();
          window.location.reload();
        });
      } else {
        Swal.fire('Error', res.error || res.message || 'Unknown error occurred', 'error');
      }
    })
    .catch(e => {
      Swal.close();
      console.error('Batch registration error:', e);
      Swal.fire('Error', 'Batch request failed: ' + e.message, 'error');
    });
}

// Decline registration
function declineRegistration(id) {
  Swal.fire({
    title: 'Decline Registration',
    text: 'Are you sure you want to decline this registration?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, decline it',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      const baseUrl = window.location.origin;
      fetch(`${baseUrl}/instrument_registration/decline`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ id: id })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            Swal.fire('Success', res.message, 'success');
            window.location.reload();
          } else {
            Swal.fire('Error', res.error || res.message, 'error');
          }
        })
        .catch(e => {
          console.error(e);
          Swal.fire('Error', 'Decline failed', 'error');
        });
    }
  });
}

// Back to unit search function
function backToUnitSearch() {
  const searchSection = document.getElementById('unitSearchSection');
  const detailsSection = document.getElementById('unitDetailsSection');

  showElement(searchSection);
  hideElement(detailsSection);

  // Clear form data
  document.getElementById('singleRegistrationForm').reset();
  selectedUnitIndex = -1;
}

// Toggle select all available properties
function toggleSelectAllAvailable(checkbox) {
  const availableCheckboxes = document.querySelectorAll('.available-property-checkbox:not([disabled])');
  availableCheckboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
  updateSelectedCount();
}

// Toggle select all function for main table
function toggleSelectAll(checkbox) {
  const checkboxes = document.querySelectorAll('.cofo-row:not([style*="display: none"]) input[type="checkbox"]');
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Select unit function for single registration
function selectUnit(index) {
  selectedUnitIndex = index;
  const pendingApplications = cofoData.filter(item => item.status === 'pending');

  if (index < 0 || index >= pendingApplications.length) {
    console.error('Invalid unit index:', index);
    return;
  }

  const selectedUnit = pendingApplications[index];

  // Show unit details section
  const searchSection = document.getElementById('unitSearchSection');
  const detailsSection = document.getElementById('unitDetailsSection');

  hideElement(searchSection);
  showElement(detailsSection);

  // Populate selected unit details with fallbacks
  const unitFileNo = selectedUnit.fileno || selectedUnit.fileNo || selectedUnit.MLSFileNo || selectedUnit.KAGISFileNo || 'N/A';
  const unitPropertyDescription = selectedUnit.plotDescription || selectedUnit.propertyDescription || selectedUnit.PropertyDescription || 'No description available';
  const formattedUnitPropertyDescription = formatPropertyDescription(unitPropertyDescription);
  const unitInstrumentType = selectedUnit.instrumentType || selectedUnit.instrument_type || '';
  const unitDuration = selectedUnit.duration || selectedUnit.leasePeriod || '';
  const unitGrantor = selectedUnit.grantor || selectedUnit.Grantor || '';
  const unitGrantee = selectedUnit.grantee || selectedUnit.Grantee || '';
  const unitLga = selectedUnit.lga || selectedUnit.LGA || '';
  const unitDistrict = selectedUnit.district || selectedUnit.District || '';
  const unitPlotNumber = selectedUnit.plotNumber || selectedUnit.plot_number || '';
  const unitPlotSize = selectedUnit.plotSize || selectedUnit.plot_size || selectedUnit.size || '';
  const unitPlotDescription = selectedUnit.plotDescription || selectedUnit.propertyDescription || selectedUnit.PropertyDescription || '';

  document.getElementById('selectedFileNo').textContent = unitFileNo;
  document.getElementById('selectedProperty').textContent = formattedUnitPropertyDescription;

  // Populate form fields
  document.getElementById('formInstrumentId').value = selectedUnit.id;
  document.getElementById('instrumentType').value = unitInstrumentType;
  document.getElementById('duration').value = unitDuration;
  document.getElementById('grantor').value = unitGrantor;
  document.getElementById('grantee').value = unitGrantee;
  document.getElementById('lga').value = unitLga;
  document.getElementById('district').value = unitDistrict;
  document.getElementById('plotNumber').value = unitPlotNumber;
  document.getElementById('plotSize').value = unitPlotSize;
  document.getElementById('plotDescription').value = unitPlotDescription;

  // Set current date and time
  const today = new Date();
  document.getElementById('deedsDate').value = today.toISOString().split('T')[0];

  const hours = today.getHours();
  const minutes = today.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';
  const formattedHours = hours % 12 || 12;
  const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
  document.getElementById('deedsTime').value = `${formattedHours}:${formattedMinutes} ${ampm}`;

  // Fetch next serial number
  fetchNextSerialNumber();
}

// Remove from batch (alias for removePropertyFromBatch)
function removeFromBatch(button) {
  const row = button.closest('tr');
  const index = parseInt(row.getAttribute('data-index'), 10);
  removePropertyFromBatch(index);
}

// Show toast function for better user feedback
function showToast(title, message, type = 'success') {
  // Using SweetAlert2 for consistent UI
  const icon = type === 'error' ? 'error' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'success';

  Swal.fire({
    title: title,
    text: message,
    icon: icon,
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });
}

// Enhanced batch registration validation function
function submitBatchRegistrationWithValidation() {
  // Debug logging
  console.log('submitBatchRegistrationWithValidation called');
  console.log('selectedBatchProperties:', selectedBatchProperties);
  console.log('selectedBatchProperties.length:', selectedBatchProperties.length);

  // Check if we have any selected properties
  if (selectedBatchProperties.length === 0) {
    console.error('No batch properties selected!');

    // Check if user has selected checkboxes but not added them to batch
    const checkedCount = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])').length;
    if (checkedCount > 0) {
      Swal.fire({
        title: 'Instruments Not Added to Batch',
        html: `
                    <p>You have selected <strong>${checkedCount} instruments</strong> but haven't added them to the batch.</p>
                    <p class="mt-2">Please click the <strong>"Add Selected Instruments"</strong> button first to add them to the batch for registration.</p>
                `,
        icon: 'warning',
        confirmButtonText: 'OK, I understand',
        confirmButtonColor: '#3085d6'
      });
    } else {
      Swal.fire('Error', 'No instruments selected for batch registration. Please select instruments first.', 'error');
    }
    return;
  }

  // If we have selected properties, proceed with the original function
  submitBatchRegistration();
}

// Ensure modal functions are available globally
function closeBatchRegisterModal() {
  const modal = document.getElementById('batchRegisterModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

function closeSingleRegisterModal() {
  const modal = document.getElementById('singleRegisterModal');
  if (modal) {
    modal.style.display = 'none';
  }
}


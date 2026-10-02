// Use server-provided data instead of sample data
let cofoData = [];

// Base URL for API endpoints defined in blade
const baseUrl = window.baseUrl || '';

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
let nextSerialData = null;

// Update the count of selected checkboxes in batch modal
function updateSelectedCount() {
  const count = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])').length;
  const addSelectedBtn = document.getElementById('addSelectedBtn');
  const batchRegisterButton = document.getElementById('batchRegisterButton');
  
  if (addSelectedBtn) {
    addSelectedBtn.textContent = `Add Selected Instruments (${count})`;
    addSelectedBtn.disabled = count === 0;
    addSelectedBtn.classList.toggle('opacity-50', count === 0);
    addSelectedBtn.classList.toggle('cursor-not-allowed', count === 0);
  }

  // Update batch register button based on selectedBatchProperties array
  if (batchRegisterButton) {
    const selectedCount = selectedBatchProperties.length;
    batchRegisterButton.textContent = `Register ${selectedCount} Instruments`;
    batchRegisterButton.disabled = selectedCount === 0;
    batchRegisterButton.classList.toggle('opacity-50', selectedCount === 0);
    batchRegisterButton.classList.toggle('cursor-not-allowed', selectedCount === 0);
    
    // Debug logging
    console.log('updateSelectedCount - selectedBatchProperties.length:', selectedCount);
    console.log('updateSelectedCount - button disabled:', selectedCount === 0);
  }
}

// Make sure critical functions are exposed to the global scope
window.populateAvailablePropertiesTable = function() {
  console.log("populateAvailablePropertiesTable called");
  
  // Get the table element
  const table = document.getElementById('availablePropertiesTable');
  if (!table) {
    console.error("Table element 'availablePropertiesTable' not found");
    return;
  }
  
  // Get search input value
  const searchInput = document.getElementById('batchSearchInput')?.value?.toLowerCase() || '';
  
  console.log("Search input:", searchInput);
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
  
  // Filter data by search input only (filter is already applied by the server)
  let filteredData = [...cofoData]; // Make a copy
  
  // Apply search filter if there's a search term
  if (searchInput) {
    filteredData = filteredData.filter(item => {
      const fileNo = (item.fileNo || '').toLowerCase();
      const grantor = (item.grantor || '').toLowerCase();
      const grantee = (item.grantee || '').toLowerCase();
      return fileNo.includes(searchInput) || 
             grantor.includes(searchInput) || 
             grantee.includes(searchInput);
    });
  }
  
  // Debug: Print sample of filtered data
  console.log("Sample data after filtering:", filteredData.slice(0, 2));
  // Clear the table first
  table.innerHTML = '';
  
  // Check if we have data after filtering
  if (filteredData.length === 0) {
    table.innerHTML = `
      <tr>
        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
          No instruments found matching your criteria.
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
    
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50';
    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap">
        <input type="checkbox" class="rounded available-property-checkbox" 
          data-id="${item.id}" ${isAlreadySelected ? 'disabled checked' : ''}>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.fileNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.grantor || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.grantee || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        <span class="badge badge-${item.status || 'pending'}">
          ${capitalizeFirstLetter(item.status || 'pending')}
        </span>
      </td>
    `;
    
    table.appendChild(row);
  });
  
  // Add event listeners for checkboxes
  document.querySelectorAll('.available-property-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
  });
  
  // Update selected count
  updateSelectedCount();
};

// Also expose the close modal functions to global scope
window.closeBatchRegisterModal = function() {
  const modal = document.getElementById('batchRegisterModal');
  if (modal) {
    modal.style.display = 'none';
  }
};

window.closeSingleRegisterModal = function() {
  const modal = document.getElementById('singleRegisterModal');
  if (modal) {
    modal.style.display = 'none';
  }
};

// Expose the real modal‐opening logic
window.openBatchRegisterModalImplementation = function() {
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
  const hh = hours % 12 || 12, mm = minutes < 10 ? '0'+minutes : minutes;
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
  fetch(`${baseUrl}/instrument_registration/get-batch-data?filter=${filter}`, {
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
  fetch(`${baseUrl}/instrument_registration/get-batch-data?filter=${filter}`, {
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
document.addEventListener('DOMContentLoaded', function() {
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
    batchSearchInput.addEventListener('input', function() {
      populateAvailablePropertiesTable();
    });
  }
  
  // Add event listener for batch status filter
  const batchStatusFilter = document.getElementById('batchStatusFilter');
  if (batchStatusFilter) {
    batchStatusFilter.addEventListener('change', function() {
      console.log('Filter changed to:', this.value);
      fetchBatchDataForFilter(this.value);
    });
  }
  
  // Add event listener for the search input
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      filterTableByFileNo(this.value);
    });
  }
});

// Fetch next available serial number
function fetchNextSerialNumber() {
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
  
  // Use the correct route for the instrument_registration controller
  return fetch(`${baseUrl}/instrument_registration/get-next-serial`, {
    method: 'GET',
    headers: headers,
    credentials: 'same-origin' // Include cookies in the request
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
    console.log('Serial number data:', data); // Debug log
    
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
    
    // Store the data for later use
    nextSerialData = data;
    
    return data;
  })
  .catch(error => {
    console.error('Error fetching next serial number:', error);
    showToast('Error', 'Failed to get next serial number: ' + error.message, 'error');
    
    // Set default values in case of error
    nextSerialData = {
      serial_no: 1,
      page_no: 1,
      volume_no: 1,
      deeds_serial_no: '1/1/1'
    };
    
    // Update UI with default values
    if (document.getElementById('batchNextSerialNo')) {
      document.getElementById('batchNextSerialNo').textContent = '1/1/1 (default)';
    }
    
    return nextSerialData;
  });
}

// Function to manually retry fetching the serial number
function retryFetchSerialNumber() {
  const debugElem = document.getElementById('serialNumberDebug');
  const debugMsg = document.getElementById('serialNumberDebugMsg');
  
  if (debugElem) debugElem.classList.remove('hidden');
  if (debugMsg) debugMsg.textContent = 'Retrying...';
  
  fetch('http://klas.com.ng/st_registration/get-next-serial', {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json'
    },
    credentials: 'same-origin'
  })
  .then(response => {
    if (!response.ok) {
      if (debugMsg) debugMsg.textContent = `Server error: ${response.status} ${response.statusText}`;
      throw new Error(`Server returned ${response.status}: ${response.statusText}`);
    }
    return response.json();
  })
  .then(data => {
    if (debugMsg) debugMsg.textContent = `Success! Data: ${JSON.stringify(data)}`;
    
    // Update batch registration form
    const batchNextSerialNo = document.getElementById('batchNextSerialNo');
    if (batchNextSerialNo) {
      batchNextSerialNo.textContent = data.deeds_serial_no;
    }
    
    // Store the data
    nextSerialData = data;
    
    // Hide debug info after success
    setTimeout(() => {
      if (debugElem) debugElem.classList.add('hidden');
    }, 3000);
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
        <td colspan="13" class="px-6 py-10 text-center text-gray-500">
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
    
    // Get the ST FileNO from the row (3rd column)
    const fileNo = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
    
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
window.onclick = function(event) {
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
  document.getElementById('singleRegisterModal').style.display = 'block';
  document.getElementById('unitSearchSection').style.display = 'none';
  document.getElementById('unitDetailsSection').style.display = 'block';
  
  // Close loading dialog
  Swal.close();
  
  // Ensure the status is set to 'pending'
  application.status = 'pending';
  
  // Set application data
  document.getElementById('selectedFileNo').textContent = application.fileNo;
  document.getElementById('selectedProperty').textContent = application.plotDescription || application.propertyDescription || 'No description available';
  
  // Populate form fields
  document.getElementById('formInstrumentId').value = application.id;
  document.getElementById('instrumentType').value = application.instrumentType || '';
  document.getElementById('duration').value = application.duration || '';
  document.getElementById('grantor').value = application.grantor || '';
  document.getElementById('grantee').value = application.grantee || '';
  document.getElementById('lga').value = application.lga || '';
  document.getElementById('district').value = application.district || '';
  document.getElementById('plotNumber').value = application.plotNumber || '';
  document.getElementById('plotSize').value = application.plotSize || '';
  document.getElementById('plotDescription').value = application.plotDescription || '';
  
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
  
  // Fetch the next serial number
  fetchNextSerialNumber();
}

// Open single register modal
function openSingleRegisterModal() {
  document.getElementById('singleRegisterModal').style.display = 'block';
  document.getElementById('unitSearchSection').style.display = 'block';
  document.getElementById('unitDetailsSection').style.display = 'none';
  
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
    modal.style.display = 'none';
  }
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
      fetchNextSerialNumber()
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
      // Re-enable the button
      if (addSelectedBtn) {
        addSelectedBtn.disabled = false;
        addSelectedBtn.textContent = `Add Selected Instruments (${selectedCheckboxes.length})`;
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
    const newProperties = Array.from(selectedCheckboxes).map(checkbox => {
      const id = checkbox.getAttribute('data-id');
      console.log("Processing checkbox with ID:", id);
      const item = cofoData.find(item => String(item.id) === String(id));
      if (!item) {
        console.warn("Could not find data for ID:", id);
      }
      return item;
    }).filter(item => item); // Filter out any undefined items
    
    console.log("New properties to add:", newProperties.length);
    
    if (newProperties.length === 0) return;
    
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
      if (currentPageNo > 100) {
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
      if (pageNo > 100) {
        volumeNo++;
        pageNo = (pageNo - 1) % 100 + 1; // 1-100 range
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
      if (nextSerialData.page_no > 100) {
        nextSerialData.volume_no++;
        nextSerialData.page_no = 1;
        nextSerialData.serial_no = 1;
        nextSerialData.deeds_serial_no = `1/1/${nextSerialData.volume_no}`;
      }
    }
    
    // Update the UI - Critical part that updates the selected properties table
    updateSelectedPropertiesTable();
    
    // Refresh available properties to show disabled checkboxes
    populateAvailablePropertiesTable();
    
    // Show success message
    Swal.fire({
      title: 'Success!',
      text: `${newProperties.length} instruments added to batch`,
      icon: 'success',
      toast: true,
      position: 'bottom-end',
      showConfirmButton: false,
      timer: 3000
    });
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

  // Clear the table
  table.innerHTML = '';
  
  // Update register button state
  const batchRegisterButton = document.getElementById('batchRegisterButton');
  if (batchRegisterButton) {
    const selectedCount = selectedBatchProperties.length;
    batchRegisterButton.disabled = selectedCount === 0;
    batchRegisterButton.textContent = `Register ${selectedCount} Instruments`;
    batchRegisterButton.classList.toggle('opacity-50', selectedCount === 0);
    batchRegisterButton.classList.toggle('cursor-not-allowed', selectedCount === 0);
    
    // Debug logging
    console.log('updateSelectedPropertiesTable - selectedBatchProperties.length:', selectedCount);
    console.log('updateSelectedPropertiesTable - button disabled:', selectedCount === 0);
  }

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
    // Update the table
    updateSelectedPropertiesTable();
    // Refresh available properties
    populateAvailablePropertiesTable();
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
      updateSelectedPropertiesTable();
      populateAvailablePropertiesTable();
      Swal.fire('Cleared!', 'Your selected instruments have been cleared.', 'success');
    }
  });
}

// Hide toast is not needed with SweetAlert
function hideToast() {
  // Not needed, as SweetAlert handles this automatically
}

// Setup the server data from the global variable when it exists
document.addEventListener('DOMContentLoaded', function() {
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
  const instrumentType = document.getElementById('instrumentType').value;
  const grantor = document.getElementById('grantor').value;
  
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
  const baseUrl = window.location.origin ;
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
    if(res.success) {
      Swal.fire('Success', res.message, 'success');
      closeSingleRegisterModal();
      window.location.reload();
    } else {
      Swal.fire('Error', res.error || res.message, 'error');
    }
  })
  .catch(e => {
    console.error(e);
    Swal.fire('Error', 'Request failed', 'error');
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
    Swal.fire('Error', 'No instruments selected for batch registration. Please select instruments first.', 'error');
    return;
  }
  
  const deedsTime = document.getElementById('batchDeedsTime').value;
  const deedsDate = document.getElementById('batchDeedsDate').value;
  
  if (!deedsTime || !deedsDate) {
    Swal.fire('Error', 'Please provide deeds date and time', 'error');
    return;
  }
  
  // Instrument types are automatically determined from the source data
  
  const batchEntries = selectedBatchProperties.map(p => ({
    application_id: p.id,
    file_no: p.fileNo,
    instrument_type: p.instrumentType,
    grantor: p.grantor,
    grantorAddress: "",
    grantee: p.grantee,
    granteeAddress: "",
    duration: p.duration,
    propertyDescription: p.plotDescription,
    lga: p.lga,
    district: p.district,
    plotNumber: p.plotNumber,
    size: p.plotSize,
    serial_no: p.serialData.serial_no,
    page_no: p.serialData.page_no,
    volume_no: p.serialData.volume_no
  }));
  
  console.log('batchEntries:', batchEntries);
  
  // Use the application's base URL instead of a Blade route
  const baseUrl = window.location.origin;
  fetch(`${baseUrl}/instrument_registration/register-batch`, {
    method: 'POST',
    headers: {
      'Content-Type':'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ 
      batch_entries: batchEntries, 
      deeds_time: deedsTime, 
      deeds_date: deedsDate 
    })
  })
  .then(r => r.json())
  .then(res => {
    if(res.success) {
      Swal.fire('Success', res.message, 'success');
      closeBatchRegisterModal();
      window.location.reload();
    } else {
      Swal.fire('Error', res.error || res.message, 'error');
    }
  })
  .catch(e => {
    console.error(e);
    Swal.fire('Error', 'Batch request failed', 'error');
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
        if(res.success) {
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
  document.getElementById('unitSearchSection').style.display = 'block';
  document.getElementById('unitDetailsSection').style.display = 'none';
  
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
  document.getElementById('unitSearchSection').style.display = 'none';
  document.getElementById('unitDetailsSection').style.display = 'block';
  
  // Populate selected unit details
  document.getElementById('selectedFileNo').textContent = selectedUnit.fileNo || 'N/A';
  document.getElementById('selectedProperty').textContent = selectedUnit.plotDescription || selectedUnit.propertyDescription || 'No description available';
  
  // Populate form fields
  document.getElementById('formInstrumentId').value = selectedUnit.id;
  document.getElementById('instrumentType').value = selectedUnit.instrumentType || '';
  document.getElementById('duration').value = selectedUnit.duration || '';
  document.getElementById('grantor').value = selectedUnit.grantor || '';
  document.getElementById('grantee').value = selectedUnit.grantee || '';
  document.getElementById('lga').value = selectedUnit.lga || '';
  document.getElementById('district').value = selectedUnit.district || '';
  document.getElementById('plotNumber').value = selectedUnit.plotNumber || '';
  document.getElementById('plotSize').value = selectedUnit.plotSize || '';
  document.getElementById('plotDescription').value = selectedUnit.plotDescription || '';
  
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

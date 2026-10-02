// Use server-provided data instead of sample data
let cofoData = [];

// Helper function to capitalize first letter (moved to the top)
function capitalizeFirstLetter(string) {
  if (!string) return '';
  return string.charAt(0).toUpperCase() + string.slice(1);
}

// Get human-friendly unit type description
function getUnitTypeDisplay(unitType) {
  switch(unitType) {
    case 'commercial_type':
      return 'Commercial';
    case 'industrial_type':
      return 'Industrial';
    case 'residence_type':
      return 'Residential';
    default:
      return 'Residential';
  }
}

// Initialize variables
let activeTab = 'pending';
let selectedUnitIndex = -1;
let selectedProperties = [];
let selectedBatchProperties = [];
let nextSerialData = null;

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
  // Process server data if available
  if (typeof serverCofoData !== 'undefined') {
    cofoData = serverCofoData.map(item => {
      // Get the actual unit type value, not just which column has a value
    
      if (item.commercial_type) {
        unitType = item.commercial_type;
      } else if (item.industrial_type) {
        unitType = item.industrial_type;
      } else if (item.residence_type) {
        unitType = item.residence_type;
      }
      
      return {
        id: item.cofo_id || item.sub_id,
        stmRef: item.fileno,
        unitNo: item.unit_number,
        blockNo: item.block_number,
        owner: item.owner_name,
        propertyDescription: item.property_description,
      
        status: item.status, // This will now be correctly set from the server
        sub_id: item.sub_id,
        certificateNumber: item.certificate_number,
        reg_status: item.reg_status, // Add SectionalCofOReg status
        land_use: item.land_use,
        unit_type: unitType,
        commercial_type: item.commercial_type,
        industrial_type: item.industrial_type,
        residence_type: item.residence_type
      };
    });
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
      populateAvailablePropertiesTable();
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
  
  const base = (typeof window.baseUrl !== 'undefined' && window.baseUrl) ? window.baseUrl : window.location.origin;
  return fetch(`${base}/st_registration/get-next-serial`, {
    method: 'GET',
    headers: headers,
    credentials: 'same-origin' // Include cookies in the request
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`Server returned ${response.status}: ${response.statusText}`);
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
  
  fetch('http://localhost/gisedms/st_registration/get-next-serial', {
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
function openSingleRegisterModalWithData(subId) {
  // Show loading state
  Swal.fire({
    title: 'Loading',
    text: 'Fetching property details...',
    icon: 'info',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });
  
  // Convert subId to string to ensure consistent comparison
  subId = String(subId);
  
  // Find the application by sub_id, ensuring string comparison
  const application = cofoData.find(item => String(item.sub_id) === subId);
  
  if (!application) {
    Swal.fire({
      title: 'Error',
      text: 'Application not found. Please try again.',
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
  
  // Get the actual unit type value, not just which column has a value
 
  if (application.commercial_type) {
    unitType = application.commercial_type;
  } else if (application.industrial_type) {
    unitType = application.industrial_type;
  } else if (application.residence_type) {
    unitType = application.residence_type;
  }
  
  // Set application data
  document.getElementById('selectedStmRef').textContent = application.stmRef;
  document.getElementById('selectedProperty').textContent = application.propertyDescription;
  document.getElementById('selectedUnitNo').textContent = application.unitNo;
  document.getElementById('selectedBlockNo').textContent = application.blockNo || 'N/A';
  document.getElementById('selectedOwner').textContent = application.owner;
  document.getElementById('selectedUnitType').textContent = unitType; // Show the actual type value
  document.getElementById('selectedUnitSize').textContent = 'N/A';
  
  document.getElementById('selectedUnitSize').textContent = 'N/A';
  
  // Populate form fields
  document.getElementById('formSubApplicationId').value = application.sub_id;
  document.getElementById('sectionalTitleFileNo').value = application.stmRef;
  document.getElementById('applicantName').value = application.owner;
  document.getElementById('tenurePeriod').value = "99";
  document.getElementById('currentOwner').value = application.owner;
  document.getElementById('occupation').value = "Property Owner";
  
  // Set current date
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
        <button class="bg-blue-600 text-white px-3 py-1 rounded-md text-sm" onclick="openSingleRegisterModalWithData(${item.sub_id})">Select</button>
      </td>
    `;
    unitSearchResults.appendChild(row);
  });
}

// Close single register modal
function closeSingleRegisterModal() {
  document.getElementById('singleRegisterModal').style.display = 'none';
}

// Go back to unit search
function backToUnitSearch() {
  document.getElementById('unitSearchSection').style.display = 'block';
  document.getElementById('unitDetailsSection').style.display = 'none';
  selectedUnitIndex = -1;
}

// Open batch register modal
function openBatchRegisterModal() {
  document.getElementById('batchRegisterModal').style.display = 'block';
  
  // Set current date
  const today = new Date();
  document.getElementById('batchDeedsDate').value = today.toISOString().split('T')[0];
 
  
  // Set current time (formatted as HH:MM AM/PM)
  const hours = today.getHours();
  const minutes = today.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';
  const formattedHours = hours % 12 || 12;
  const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
  document.getElementById('batchDeedsTime').value = `${formattedHours}:${formattedMinutes} ${ampm}`;
  
  // Clear selected properties
  clearSelectedProperties();
  
  // Populate available properties table
  populateAvailablePropertiesTable();
  
  // Fetch the next serial number
  fetchNextSerialNumber();
}

// Close batch register modal
function closeBatchRegisterModal() {
  document.getElementById('batchRegisterModal').style.display = 'none';
}

// Show global search in batch register modal
function showGlobalSearch() {
  document.getElementById('globalSearchSection').style.display = 'block';
}

// Hide global search in batch register modal
function hideGlobalSearch() {
  document.getElementById('globalSearchSection').style.display = 'none';
}

// Populate available properties table
function populateAvailablePropertiesTable() {
  const table = document.getElementById('availablePropertiesTable');
  const statusFilter = document.getElementById('batchStatusFilter').value;
  const searchInput = document.getElementById('batchSearchInput').value.toLowerCase();
  
  // Filter properties
  let filteredData = cofoData;
  
  if (statusFilter === 'pending') {
    filteredData = filteredData.filter(item => item.status === 'pending');
  }
  
  if (searchInput) {
    filteredData = filteredData.filter(item => 
      (item.stmRef && item.stmRef.toLowerCase().includes(searchInput)) ||
      (item.unitNo && item.unitNo.toString().toLowerCase().includes(searchInput)) ||
      (item.blockNo && item.blockNo.toString().toLowerCase().includes(searchInput)) ||
      (item.owner && item.owner.toLowerCase().includes(searchInput))
    );
  }
  
  // Clear table
  table.innerHTML = '';
  
  if (filteredData.length === 0) {
    table.innerHTML = `
      <tr>
        <td colspan="6" class="px-6 py-10 text-center text-gray-500">
          No properties found matching your criteria.
        </td>
      </tr>
    `;
    return;
  }
  
  // Populate table with filtered data
  filteredData.forEach(item => {
    const row = document.createElement('tr');
    
    // Check if this property is already selected
    const isAlreadySelected = selectedBatchProperties.some(prop => prop.sub_id === item.sub_id);
    
    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap">
        <input type="checkbox" class="rounded available-property-checkbox" 
          data-sub-id="${item.sub_id}" ${isAlreadySelected ? 'disabled checked' : ''}>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.stmRef || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.unitNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.blockNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${item.owner || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        <span class="badge badge-${item.status}">${capitalizeFirstLetter(item.status)}</span>
      </td>
    `;
    
    table.appendChild(row);
  });
  
  // Update checkbox change listeners
  document.querySelectorAll('.available-property-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
  });
  
  // Update the selected count
  updateSelectedCount();
}

// Toggle select all available properties
function toggleSelectAllAvailable(checkbox) {
  document.querySelectorAll('.available-property-checkbox:not([disabled])').forEach(cb => {
    cb.checked = checkbox.checked;
  });
  
  updateSelectedCount();
}

// Update selected count
function updateSelectedCount() {
  const count = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])').length;
  document.getElementById('addSelectedBtn').textContent = `Add Selected Properties (${count})`;
  document.getElementById('addSelectedBtn').disabled = count === 0;
}

// Add selected properties to batch
function addSelectedToBatch() {
  const selectedCheckboxes = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])');
  
  if (selectedCheckboxes.length === 0) {
    showToast('Warning', 'No properties selected', 'info');
    return;
  }
  
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
          addSelectedBtn.textContent = `Add Selected Properties (${selectedCheckboxes.length})`;
        }
      })
      .catch(() => {
        // Re-enable the button even if there's an error
        if (addSelectedBtn) {
          addSelectedBtn.disabled = false;
          addSelectedBtn.textContent = `Add Selected Properties (${selectedCheckboxes.length})`;
        }
      });
  } else {
    processSelectedProperties(selectedCheckboxes);
    // Re-enable the button
    if (addSelectedBtn) {
      addSelectedBtn.disabled = false;
      addSelectedBtn.textContent = `Add Selected Properties (${selectedCheckboxes.length})`;
    }
  }
}

// Process selected properties
function processSelectedProperties(selectedCheckboxes) {
  const newProperties = Array.from(selectedCheckboxes).map(checkbox => {
    const subId = checkbox.getAttribute('data-sub-id');
    return cofoData.find(item => String(item.sub_id) === String(subId));
  }).filter(item => item); // Filter out any undefined items
  
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
  
  // Add to selectedBatchProperties
  selectedBatchProperties = [...selectedBatchProperties, ...propertiesWithSerial];
  
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
  
  // Update the selected properties table
  updateSelectedPropertiesTable();
  
  // Refresh available properties
  populateAvailablePropertiesTable();
  
  Swal.fire({
    title: 'Success!',
    text: `${newProperties.length} properties added to batch`,
    icon: 'success',
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 3000
  });
}

// Update selected properties table
function updateSelectedPropertiesTable() {
  const table = document.getElementById('selectedPropertiesTable');
  const noSelectedRow = document.getElementById('noSelectedPropertiesRow');
  
  // Show/hide no selected properties message
  if (selectedBatchProperties.length === 0) {
    if (noSelectedRow) {
      noSelectedRow.style.display = '';
    } else {
      table.innerHTML = `
        <tr id="noSelectedPropertiesRow">
          <td colspan="6" class="px-6 py-10 text-center text-gray-500">
            No properties selected for registration. Use the table above to select properties.
          </td>
        </tr>
      `;
    }
    document.getElementById('batchRegisterButton').disabled = true;
    document.getElementById('batchRegisterButton').textContent = 'Register 0 CofOs';
    return;
  }
  
  // Hide no selected properties message
  if (noSelectedRow) {
    noSelectedRow.style.display = 'none';
  }
  
  // Clear table except for the noSelectedRow
  Array.from(table.children).forEach(child => {
    if (child.id !== 'noSelectedPropertiesRow') {
      child.remove();
    }
  });
  
  // Populate table with selected properties
  selectedBatchProperties.forEach((property, index) => {
    const row = document.createElement('tr');
    row.setAttribute('data-index', index);
    
    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.stmRef || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.unitNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.blockNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.owner || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${property.serialData.deeds_serial_no}</td>
      <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
        <button class="text-red-600 hover:text-red-800" onclick="removePropertyFromBatch(${index})">
          <i class="fas fa-times"></i>
        </button>
      </td>
    `;
    
    table.appendChild(row);
  });
  
  // Update the register button
  document.getElementById('batchRegisterButton').disabled = false;
  document.getElementById('batchRegisterButton').textContent = `Register ${selectedBatchProperties.length} CofO${selectedBatchProperties.length !== 1 ? 's' : ''}`;
}

// Remove property from batch
function removePropertyFromBatch(index) {
  selectedBatchProperties.splice(index, 1);
  updateSelectedPropertiesTable();
  populateAvailablePropertiesTable();
}

// Clear all selected properties
function clearSelectedProperties() {
  selectedBatchProperties = [];
  updateSelectedPropertiesTable();
  populateAvailablePropertiesTable();
}

// Toggle select all checkboxes
function toggleSelectAll(checkbox) {
  const checkboxes = document.querySelectorAll('#cofoTableBody input[type="checkbox"]');
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Toggle select all search checkboxes
function toggleSelectAllSearch(checkbox) {
  const checkboxes = document.querySelectorAll('.search-checkbox');
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Toggle select all batch checkboxes
function toggleSelectAllBatch(checkbox) {
  const checkboxes = document.querySelectorAll('#selectedPropertiesTable input[type="checkbox"]');
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Initialize calendars
function initializeCalendars() {
  const today = new Date();
  
  const selectedDate = document.getElementById('selectedDate');
  if (selectedDate) selectedDate.textContent = formatDate(today);
  
  const batchSelectedDate = document.getElementById('batchSelectedDate');
  if (batchSelectedDate) batchSelectedDate.textContent = formatDate(today);
  
  const currentMonth = document.getElementById('currentMonth');
  if (currentMonth) currentMonth.textContent = formatMonth(today);
  
  const batchCurrentMonth = document.getElementById('batchCurrentMonth');
  if (batchCurrentMonth) batchCurrentMonth.textContent = formatMonth(today);
  
  renderCalendar(today, 'calendarDays');
  renderCalendar(today, 'batchCalendarDays');
}

// Render calendar
function renderCalendar(date, containerId) {
  const year = date.getFullYear();
  const month = date.getMonth();
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const daysInMonth = lastDay.getDate();
  const startingDay = firstDay.getDay();
  
  const container = document.getElementById(containerId);
  if (!container) return; // Guard against missing container
  
  container.innerHTML = '';
  
  // Add empty cells for days before the first day of the month
  for (let i = 0; i < startingDay; i++) {
    const dayElement = document.createElement('div');
    dayElement.className = 'calendar-day';
    container.appendChild(dayElement);
  }
  
  // Add days of the month
  for (let i = 1; i <= daysInMonth; i++) {
    const dayElement = document.createElement('div');
    dayElement.className = 'calendar-day';
    dayElement.textContent = i;
    
    const currentDate = new Date(year, month, i);
    if (isToday(currentDate)) {
      dayElement.classList.add('today');
    }
    
    dayElement.addEventListener('click', () => {
      selectDate(currentDate, containerId === 'calendarDays' ? 'selectedDate' : 'batchSelectedDate');
      toggleCalendar(containerId === 'calendarDays' ? 'calendarPopup' : 'batchCalendarPopup');
    });
    
    container.appendChild(dayElement);
  }
}

// Toggle calendar popup
function toggleCalendar(calendarId = 'calendarPopup') {
  const calendar = document.getElementById(calendarId);
  if (calendar) {
    calendar.style.display = calendar.style.display === 'block' ? 'none' : 'block';
  }
}

// Toggle batch calendar popup
function toggleBatchCalendar() {
  toggleCalendar('batchCalendarPopup');
}

// Select a date
function selectDate(date, elementId) {
  const element = document.getElementById(elementId);
  if (element) {
    element.textContent = formatDate(date);
  }
}

// Format date for display
function formatDate(date) {
  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}

// Format month for display
function formatMonth(date) {
  const options = { year: 'numeric', month: 'long' };
  return date.toLocaleDateString('en-US', options);
}

// Check if a date is today
function isToday(date) {
  const today = new Date();
  return date.getDate() === today.getDate() &&
         date.getMonth() === today.getMonth() &&
         date.getFullYear() === today.getFullYear();
}

// Navigate to previous month
function prevMonth() {
  const currentMonth = document.getElementById('currentMonth').textContent;
  const date = new Date(currentMonth);
  date.setMonth(date.getMonth() - 1);
  document.getElementById('currentMonth').textContent = formatMonth(date);
  renderCalendar(date, 'calendarDays');
}

// Navigate to next month
function nextMonth() {
  const currentMonth = document.getElementById('currentMonth').textContent;
  const date = new Date(currentMonth);
  date.setMonth(date.getMonth() + 1);
  document.getElementById('currentMonth').textContent = formatMonth(date);
  renderCalendar(date, 'calendarDays');
}

// Navigate to previous month in batch calendar
function prevBatchMonth() {
  const currentMonth = document.getElementById('batchCurrentMonth').textContent;
  const date = new Date(currentMonth);
  date.setMonth(date.getMonth() - 1);
  document.getElementById('batchCurrentMonth').textContent = formatMonth(date);
  renderCalendar(date, 'batchCalendarDays');
}

// Navigate to next month in batch calendar
function nextBatchMonth() {
  const currentMonth = document.getElementById('batchCurrentMonth').textContent;
  const date = new Date(currentMonth);
  date.setMonth(date.getMonth() + 1);
  document.getElementById('batchCurrentMonth').textContent = formatMonth(date);
  renderCalendar(date, 'batchCalendarDays');
}

// Validate deeds time format
function validateDeedsTime(time) {
  const regex = /^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$/;
  return regex.test(time);
}

// Clear form errors
function clearFormErrors(formId) {
  const errorElements = document.querySelectorAll(`#${formId} .text-red-500`);
  errorElements.forEach(element => {
    element.classList.add('hidden');
    element.textContent = '';
  });
}

// Show form error
function showFormError(elementId, message) {
  const errorElement = document.getElementById(elementId);
  if (errorElement) {
    errorElement.textContent = message;
    errorElement.classList.remove('hidden');
  }
}

// Submit single registration
function submitSingleRegistration() {
  // Clear previous errors
  clearFormErrors('singleRegistrationForm');
  
  // Get form data
  const form = document.getElementById('singleRegistrationForm');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());
  
  // Validate required fields
  let hasErrors = false;
  
  if (!data.sectional_title_file_no) {
    showFormError('sectionalTitleFileNoError', 'Sectional Title File No is required');
    hasErrors = true;
  }
  
  if (!data.applicant_name) {
    showFormError('applicantNameError', 'Applicant Name is required');
    hasErrors = true;
  }
  
  if (!data.tenure_period) {
    showFormError('tenurePeriodError', 'Tenure Period is required');
    hasErrors = true;
  }
  
  if (!data.current_owner) {
    showFormError('currentOwnerError', 'Current Owner is required');
    hasErrors = true;
  }
  
  if (!data.occupation) {
    showFormError('occupationError', 'Occupation is required');
    hasErrors = true;
  }
  
  if (!data.deeds_time) {
    showFormError('deedsTimeError', 'Deeds Time is required');
    hasErrors = true;
  } else if (!validateDeedsTime(data.deeds_time)) {
    showFormError('deedsTimeError', 'Deeds Time must be in format HH:MM AM/PM');
    hasErrors = true;
  }
  
  if (!data.deeds_date) {
    showFormError('deedsDateError', 'Deeds Date is required');
    hasErrors = true;
  }
  
  
  if (hasErrors) {
    return;
  }
  
  // Show loading state
  Swal.fire({
    title: 'Processing',
    text: 'Registering Certificate of Occupancy...',
    icon: 'info',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });
  
  // Disable button to prevent double submission
  const registerButton = document.getElementById('registerButton');
  registerButton.disabled = true;
  
  // Submit form via AJAX
  fetch('http://localhost/gisedms/st_registration/register-single', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        title: 'Success!',
        text: data.message,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        closeSingleRegisterModal();
        // Reload page to show updated data
        window.location.reload();
      });
    } else {
      throw new Error(data.error || 'Failed to register CofO');
    }
  })
  .catch(error => {
    console.error('Error registering CofO:', error);
    Swal.fire({
      title: 'Error',
      text: error.message,
      icon: 'error'
    });
    
    // Re-enable the button
    registerButton.disabled = false;
  });
}

// Submit batch registration
function submitBatchRegistration() {
  // Clear previous errors
  clearFormErrors('batchRegistrationForm');
  
  // Validate common fields
  let hasErrors = false;
  
  const deedsTime = document.getElementById('batchDeedsTime').value;
  const deedsDate = document.getElementById('batchDeedsDate').value;
   
  
  if (!deedsTime) {
    showFormError('batchDeedsTimeError', 'Deeds Time is required');
    hasErrors = true;
  } else if (!validateDeedsTime(deedsTime)) {
    showFormError('batchDeedsTimeError', 'Deeds Time must be in format HH:MM AM/PM');
    hasErrors = true;
  }
  
  if (!deedsDate) {
    showFormError('batchDeedsDateError', 'Deeds Date is required');
    hasErrors = true;
  }
  
 
  if (selectedBatchProperties.length === 0) {
    Swal.fire({
      title: 'Error',
      text: 'No properties selected for registration',
      icon: 'error'
    });
    return;
  }
  
  if (hasErrors) {
    return;
  }
  
  // Show loading state
  Swal.fire({
    title: 'Processing',
    text: 'Registering Certificates of Occupancy...',
    icon: 'info',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });
  
  // Disable button to prevent double submission
  const batchRegisterButton = document.getElementById('batchRegisterButton');
  batchRegisterButton.disabled = true;
  
  // Prepare batch entries
  const batchEntries = selectedBatchProperties.map(property => ({
    sub_application_id: property.sub_id,
    sectional_title_file_no: property.stmRef,
    applicant_name: property.owner,
    tenure_period: 99, // Default value
    current_owner: property.owner,
    occupation: "Property Owner", // Default value
    deeds_transfer: "", // Optional
    serial_no: property.serialData.serial_no,
    page_no: property.serialData.page_no,
    volume_no: property.serialData.volume_no
  }));
  
  // Prepare data for submission
  const batchData = {
    batch_entries: batchEntries,
    deeds_time: deedsTime,
    deeds_date: deedsDate,
 
  };
  
  // Submit form via AJAX
 fetch('http://localhost/gisedms/st_registration/register-batch', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    },
    body: JSON.stringify(batchData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        title: 'Success!',
        text: data.message,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        closeBatchRegisterModal();
        // Reload page to show updated data
        window.location.reload();
      });
    } else {
      throw new Error(data.error || 'Failed to register CofOs');
    }
  })
  .catch(error => {
    console.error('Error registering CofOs:', error);
    Swal.fire({
      title: 'Error',
      text: error.message,
      icon: 'error'
    });
    
    // Re-enable the button
    batchRegisterButton.disabled = false;
  });
}

// Replace toast notifications with SweetAlert
function showToast(title, message, type = 'success') {
  Swal.fire({
    title: title,
    text: message,
    icon: type,
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
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
        id: item.cofo_id || item.sub_id,
        stmRef: item.fileno,
        unitNo: item.unit_number,
        blockNo: item.block_number,
        owner: item.owner_name,
        propertyDescription: item.property_description,
         status: item.status,
        sub_id: item.sub_id,
        certificateNumber: item.certificate_number,
        reg_status: item.reg_status,
        unit_type: item.unit_type || 'residence_type' // Use the direct unit_type field
      };
    });
  }
});

// Function to handle declining a registration
function declineRegistration(subId) {
  Swal.fire({
    title: 'Decline Registration',
    text: 'Are you sure you want to decline this registration? This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, decline it',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading state
      Swal.fire({
        title: 'Processing',
        text: 'Declining registration...',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
          Swal.showLoading();
        }
      });
      
      // Send the decline request
      fetch('http://localhost/gisedms/st_registration/decline', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
          sub_application_id: subId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            title: 'Success!',
            text: 'Registration has been declined.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            // Reload page to show updated data
            window.location.reload();
          });
        } else {
          throw new Error(data.error || 'Failed to decline registration');
        }
      })
      .catch(error => {
        console.error('Error declining registration:', error);
        Swal.fire({
          title: 'Error',
          text: error.message,
          icon: 'error'
        });
      });
    }
  });
}

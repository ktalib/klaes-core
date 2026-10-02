// Enhanced Batch Registration Handler for Pre-selected Instruments
// This file handles the functionality for automatically opening batch registration
// when multiple instruments are selected from the main table

// Store for pre-selected instruments from main table
let preSelectedInstruments = [];

// Function to set selected instruments from main table
window.setSelectedInstrumentsForBatch = function(instruments) {
  preSelectedInstruments = instruments || [];
  console.log('Pre-selected instruments set:', preSelectedInstruments.length);
};

// Override the original batch modal implementation to handle pre-selected instruments
document.addEventListener('DOMContentLoaded', function() {
  // Store the original implementation
  const originalBatchModalImplementation = window.openBatchRegisterModalImplementation;
  
  // Override with enhanced functionality
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

    // Hide filter sections for pre-selected instruments
    const filterSection = document.querySelector('.batch-filter-section');
    const instructionText = document.querySelector('.batch-instruction-text');
    const availableSection = document.querySelector('.available-instruments-section');
    
    if (preSelectedInstruments.length > 0) {
      console.log('Processing pre-selected instruments:', preSelectedInstruments.length);
      
      // Hide filter and instruction sections for pre-selected batch
      if (filterSection) filterSection.style.display = 'none';
      if (instructionText) instructionText.style.display = 'none';
      if (availableSection) availableSection.style.display = 'none';
      
      // Update modal title
      const modalTitle = document.querySelector('#batchRegisterModal .modal-title');
      if (modalTitle) {
        modalTitle.textContent = `Batch Register ${preSelectedInstruments.length} Selected Instruments`;
      }
      
      // Clear any existing selected batch properties
      if (typeof window.selectedBatchProperties !== 'undefined') {
        window.selectedBatchProperties = [];
      }
      
      // Process pre-selected instruments directly
      setTimeout(() => {
        processPreSelectedInstruments();
      }, 100);
      
    } else {
      // Show filter and instruction sections for normal batch
      if (filterSection) filterSection.style.display = 'block';
      if (instructionText) instructionText.style.display = 'block';
      if (availableSection) availableSection.style.display = 'block';
      
      // Reset modal title
      const modalTitle = document.querySelector('#batchRegisterModal .modal-title');
      if (modalTitle) {
        modalTitle.textContent = 'Batch Registration';
      }
      
      // Clear previous selection
      if (typeof clearSelectedProperties === 'function') {
        clearSelectedProperties();
      }
      
      // Load normal batch data
      loadNormalBatchData();
    }

    // fetch next serial
    if (typeof window.fetchNextSerialNumber === 'function') {
      window.fetchNextSerialNumber();
    }
  };
  
  // Function to process pre-selected instruments
  function processPreSelectedInstruments() {
    console.log('Processing pre-selected instruments:', preSelectedInstruments);
    
    // Process instruments without serial numbers (they'll be assigned during submission)
    const processedInstruments = preSelectedInstruments.map((instrument, index) => {
      return {
        ...instrument,
        // Remove serialData - will be assigned during submission
      };
    });
    
    console.log('Processed instruments:', processedInstruments);
    
    // Set the selected batch properties
    if (typeof window.selectedBatchProperties !== 'undefined') {
      window.selectedBatchProperties = processedInstruments;
      console.log('Set selectedBatchProperties:', window.selectedBatchProperties.length);
    } else {
      // Create the global variable if it doesn't exist
      window.selectedBatchProperties = processedInstruments;
    }
    
    // Update the selected properties table using our custom function
    updateSelectedPropertiesTableForPreSelected(processedInstruments);
    
    // Clear pre-selected instruments after use
    preSelectedInstruments = [];
  }
  
  // Custom function to update the selected properties table for pre-selected instruments
  function updateSelectedPropertiesTableForPreSelected(instruments) {
    console.log('updateSelectedPropertiesTableForPreSelected called with', instruments.length, 'instruments');
    
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
      batchRegisterButton.disabled = instruments.length === 0;
      batchRegisterButton.textContent = `Register ${instruments.length} Instrument${instruments.length !== 1 ? 's' : ''}`;
    }

    // Show/hide no selected properties message
    if (instruments.length === 0) {
      table.innerHTML = `
        <tr id="noSelectedPropertiesRow">
          <td colspan="6" class="px-6 py-10 text-center text-gray-500">
            No instruments selected for registration. Use the table above to select instruments.
          </td>
        </tr>
      `;
      return;
    }
    
    // Populate table with selected properties (without serial numbers)
    instruments.forEach((property, index) => {
      console.log('Adding property to table:', property.fileNo, property);
      const row = document.createElement('tr');
      row.setAttribute('data-index', index);
      row.className = 'hover:bg-gray-50';
      
      row.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${property.fileNo || 'N/A'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">${property.grantor || 'N/A'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">${property.grantee || 'N/A'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">
          <input type="text" class="w-full px-3 py-1 border rounded-md bg-gray-100" value="${property.instrumentType || 'N/A'}" readonly>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">Auto-assigned</td>
        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
          <button class="text-red-600 hover:text-red-800" onclick="removePropertyFromBatchPreSelected(${index})">
            <i class="fas fa-times"></i>
          </button>
        </td>
      `;
      
      table.appendChild(row);
    });
    
    console.log('Table populated with', instruments.length, 'instruments');
  }
  
  // Function to remove property from pre-selected batch
  window.removePropertyFromBatchPreSelected = function(index) {
    console.log("Removing property at index:", index);
    if (typeof window.selectedBatchProperties !== 'undefined' && window.selectedBatchProperties.length > index) {
      window.selectedBatchProperties.splice(index, 1);
      updateSelectedPropertiesTableForPreSelected(window.selectedBatchProperties);
    }
  };
  
  // Function to load normal batch data
  function loadNormalBatchData() {
    const table = document.getElementById('availablePropertiesTable');
    if (table) {
      table.innerHTML = `
        <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">
          <i class="fas fa-spinner fa-spin mr-2"></i> Loading instrument data...
        </td></tr>`;
    }

    const baseUrl = window.location.origin  ;
    const filter = document.getElementById('batchStatusFilter')?.value || 'batch';
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
        if (typeof window.cofoData !== 'undefined') {
          window.cofoData = data.map(item => ({
            id: item.id,
            fileNo: item.fileno,
            grantor: item.grantor,
            grantee: item.grantee,
            status: item.status || 'pending',
            instrumentType: item.instrument_type || '',
            source_type: item.source_type || ''
          }));
        }
      } else if (data && data.error) {
        console.error('Server error:', data.error);
        if (typeof window.cofoData !== 'undefined') {
          window.cofoData = [];
        }
        if (table) {
          table.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">
            Server error: ${data.error}
          </td></tr>`;
        }
        return;
      } else {
        console.error('Expected array but got:', typeof data, data);
        if (typeof window.cofoData !== 'undefined') {
          window.cofoData = [];
        }
      }
      
      if (typeof window.populateAvailablePropertiesTable === 'function') {
        window.populateAvailablePropertiesTable();
      }
    })
    .catch(error => {
      console.error('Error fetching batch data:', error);
      if (table) {
        table.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">
          Error loading instruments: ${error.message}
        </td></tr>`;
      }
    });
  }
  
  // Function to reset batch modal when checkboxes are unchecked
  window.resetBatchModalIfNeeded = function() {
    // Check if modal is open
    const modal = document.getElementById('batchRegisterModal');
    if (!modal || modal.style.display === 'none') {
      return; // Modal is not open, no need to reset
    }
    
    // Check if there are any checked boxes
    const checkedBoxes = document.querySelectorAll('.main-table-checkbox:checked:not([disabled])');
    const checkedCount = checkedBoxes.length;
    
    console.log('resetBatchModalIfNeeded: checked count =', checkedCount);
    
    if (checkedCount < 2) {
      // Less than 2 instruments selected, reset modal to normal state
      console.log('Resetting modal to normal state');
      
      // Show filter and instruction sections
      const filterSection = document.querySelector('.batch-filter-section');
      const instructionText = document.querySelector('.batch-instruction-text');
      const availableSection = document.querySelector('.available-instruments-section');
      
      if (filterSection) filterSection.style.display = 'block';
      if (instructionText) instructionText.style.display = 'block';
      if (availableSection) availableSection.style.display = 'block';
      
      // Reset modal title
      const modalTitle = document.querySelector('#batchRegisterModal .modal-title');
      if (modalTitle) {
        modalTitle.textContent = 'Batch Registration';
      }
      
      // Clear selected properties
      if (typeof window.selectedBatchProperties !== 'undefined') {
        window.selectedBatchProperties = [];
      }
      
      // Reset the selected properties table
      const table = document.getElementById('selectedPropertiesTable');
      if (table) {
        table.innerHTML = `
          <tr id="noSelectedPropertiesRow">
            <td colspan="6" class="px-6 py-10 text-center text-gray-500">
              No instruments selected for registration. Use the table above to select instruments.
            </td>
          </tr>
        `;
      }
      
      // Reset register button
      const batchRegisterButton = document.getElementById('batchRegisterButton');
      if (batchRegisterButton) {
        batchRegisterButton.disabled = true;
        batchRegisterButton.textContent = 'Register 0 Instruments';
      }
      
      // Load normal batch data
      loadNormalBatchData();
    }
  };
});
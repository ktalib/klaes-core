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
    
    // Fetch next serial number first
    if (typeof window.fetchNextSerialNumber === 'function') {
      window.fetchNextSerialNumber().then(() => {
        // Process and add instruments with serial numbers
        const instrumentsWithSerial = preSelectedInstruments.map((instrument, index) => {
          const nextSerial = window.nextSerialData || { serial_no: 1, page_no: 1, volume_no: 1 };
          let serialNo = nextSerial.serial_no + index;
          let pageNo = nextSerial.page_no + index;
          let volumeNo = nextSerial.volume_no;
          
          if (pageNo > 100) {
            volumeNo++;
            pageNo = (pageNo - 1) % 100 + 1;
            serialNo = pageNo;
          }
          
          return {
            ...instrument,
            serialData: {
              serial_no: serialNo,
              page_no: pageNo,
              volume_no: volumeNo,
              deeds_serial_no: `${serialNo}/${pageNo}/${volumeNo}`
            }
          };
        });
        
        console.log('Instruments with serial data:', instrumentsWithSerial);
        
        // Set the selected batch properties
        if (typeof window.selectedBatchProperties !== 'undefined') {
          window.selectedBatchProperties = instrumentsWithSerial;
          console.log('Set selectedBatchProperties:', window.selectedBatchProperties.length);
        } else {
          // Create the global variable if it doesn't exist
          window.selectedBatchProperties = instrumentsWithSerial;
        }
        
        // Update the selected properties table using our custom function
        updateSelectedPropertiesTableForPreSelected(instrumentsWithSerial);
        
        // Clear pre-selected instruments after use
        preSelectedInstruments = [];
      }).catch(error => {
        console.error('Error fetching serial number:', error);
        // Fallback: use default serial numbers
        const instrumentsWithSerial = preSelectedInstruments.map((instrument, index) => {
          return {
            ...instrument,
            serialData: {
              serial_no: index + 1,
              page_no: index + 1,
              volume_no: 1,
              deeds_serial_no: `${index + 1}/${index + 1}/1`
            }
          };
        });
        
        if (typeof window.selectedBatchProperties !== 'undefined') {
          window.selectedBatchProperties = instrumentsWithSerial;
        } else {
          window.selectedBatchProperties = instrumentsWithSerial;
        }
        
        updateSelectedPropertiesTableForPreSelected(instrumentsWithSerial);
        preSelectedInstruments = [];
      });
    } else {
      console.error('fetchNextSerialNumber function not found');
      // Fallback without serial numbers
      const instrumentsWithSerial = preSelectedInstruments.map((instrument, index) => {
        return {
          ...instrument,
          serialData: {
            serial_no: index + 1,
            page_no: index + 1,
            volume_no: 1,
            deeds_serial_no: `${index + 1}/${index + 1}/1`
          }
        };
      });
      
      if (typeof window.selectedBatchProperties !== 'undefined') {
        window.selectedBatchProperties = instrumentsWithSerial;
      } else {
        window.selectedBatchProperties = instrumentsWithSerial;
      }
      
      updateSelectedPropertiesTableForPreSelected(instrumentsWithSerial);
      preSelectedInstruments = [];
    }
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
    
    // Populate table with selected properties
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
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${property.serialData ? property.serialData.deeds_serial_no : 'N/A'}</td>
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

    const baseUrl = window.location.origin + '/gisedms';
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
});
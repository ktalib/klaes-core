// Enhanced Batch Registration Handler for Pre-selected Instruments
// This file handles the functionality for automatically opening batch registration
// when multiple instruments are selected from the main table

// Store for pre-selected instruments from main table
let preSelectedInstruments = [];

// Function to set selected instruments from main table
window.setSelectedInstrumentsForBatch = function (instruments) {
  preSelectedInstruments = instruments || [];
  console.log('Pre-selected instruments set:', preSelectedInstruments.length);
};

// Override the original batch modal implementation to handle pre-selected instruments
document.addEventListener('DOMContentLoaded', function () {
  // Store the original implementation
  const originalBatchModalImplementation = window.openBatchRegisterModalImplementation;

  // Override with enhanced functionality
  window.openBatchRegisterModalImplementation = function () {
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

    // Always show the normal batch interface (filter sections, available instruments, etc.)
    const filterSection = document.querySelector('.batch-filter-section');
    const instructionText = document.querySelector('.batch-instruction-text');
    const availableSection = document.querySelector('.available-instruments-section');

    // Always show filter and instruction sections
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

    // Load normal batch data first
    loadNormalBatchData();

    // If there are pre-selected instruments, add them to the batch after loading
    if (preSelectedInstruments.length > 0) {
      console.log('Will add pre-selected instruments after loading:', preSelectedInstruments.length);
      setTimeout(() => {
        addPreSelectedInstrumentsToBatch();
      }, 500); // Give time for the normal data to load
    }

    // fetch next serial
    if (typeof window.fetchNextSerialNumber === 'function') {
      window.fetchNextSerialNumber();
    }
  };

  // Function to add pre-selected instruments to the batch (new approach)
  function addPreSelectedInstrumentsToBatch() {
    console.log('Adding pre-selected instruments to batch:', preSelectedInstruments);

    if (!preSelectedInstruments || preSelectedInstruments.length === 0) {
      return;
    }

    // Process instruments and add serial data
    const processedInstruments = preSelectedInstruments.map((instrument, index) => {
      // Get next serial data if available
      // Legacy manual prediction removed. We rely on the server response to assign numbers.
      let serialData = null;
      /*
      if (typeof window.nextSerialData !== 'undefined' && window.nextSerialData) {
        serialData = {
          serial_no: window.nextSerialData.serial_no + index,
          page_no: window.nextSerialData.page_no + index,
          volume_no: window.nextSerialData.volume_no,
          deeds_serial_no: `${window.nextSerialData.serial_no + index}/${window.nextSerialData.page_no + index}/${window.nextSerialData.volume_no}`
        };
      }
      */

      return {
        ...instrument,
        serialData: serialData
      };
    });

    console.log('Processed pre-selected instruments:', processedInstruments);

    // Set the selected batch properties
    if (typeof window.selectedBatchProperties !== 'undefined') {
      window.selectedBatchProperties = processedInstruments;
      console.log('Set selectedBatchProperties:', window.selectedBatchProperties.length);
    } else {
      // Create the global variable if it doesn't exist
      window.selectedBatchProperties = processedInstruments;
    }

    // Update the selected properties table
    if (typeof window.updateSelectedPropertiesTable === 'function') {
      window.updateSelectedPropertiesTable();
    } else {
      // Fallback update
      updateSelectedPropertiesTableFallback(processedInstruments);
    }

    // Clear pre-selected instruments after use
    preSelectedInstruments = [];
  }

  // Fallback function to update the selected properties table
  function updateSelectedPropertiesTableFallback(instruments) {
    console.log('updateSelectedPropertiesTableFallback called with', instruments.length, 'instruments');

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
        <td class="px-6 py-4 whitespace-nowrap text-sm">${property.grantee ? property.grantee : '<span class="text-gray-400 italic">Not specified</span>'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">
          <input type="text" class="w-full px-3 py-1 border rounded-md bg-gray-100" value="${property.instrumentType || 'N/A'}" readonly>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${property.serialData ? property.serialData.deeds_serial_no : 'Auto-assigned'}</td>
        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
          <button class="text-red-600 hover:text-red-800" onclick="removePropertyFromBatch(${index})">
            <i class="fas fa-times"></i>
          </button>
        </td>
      `;

      table.appendChild(row);
    });

    console.log('Table populated with', instruments.length, 'instruments');
  }

  // Function to load normal batch data
  function loadNormalBatchData() {
    const table = document.getElementById('availablePropertiesTable');
    if (table) {
      table.innerHTML = `
        <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">
          <i class="fas fa-spinner fa-spin mr-2"></i> Loading instrument data...
        </td></tr>`;
    }

    const baseUrl = window.location.origin;
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
  window.resetBatchModalIfNeeded = function () {
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
      // Less than 2 instruments selected, clear pre-selected instruments
      preSelectedInstruments = [];
      console.log('Cleared pre-selected instruments');
    }
  };
});
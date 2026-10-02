// Quick Batch Registration Handler for Pre-selected Instruments from Index Page
// This handles the simplified batch registration modal for instruments selected from the main table

// Store for quick batch instruments
let quickBatchInstruments = [];

// Function to open quick batch modal with pre-selected instruments
window.openQuickBatchModal = function (selectedInstruments) {
  console.log('Opening quick batch modal with instruments:', selectedInstruments);

  // Debug: Log each instrument's ID
  selectedInstruments.forEach((instrument, index) => {
    console.log(`Instrument ${index + 1}:`, {
      id: instrument.id,
      idType: typeof instrument.id,
      fileNo: instrument.fileNo,
      grantor: instrument.grantor
    });
  });

  // Store the instruments
  quickBatchInstruments = selectedInstruments || [];

  const modal = document.getElementById('quickBatchModal');
  if (!modal) {
    console.error("Quick batch modal element not found");
    return;
  }

  // Show modal
  modal.style.display = 'block';

  // Set date/time fields
  const today = new Date();
  document.getElementById('quickBatchDeedsDate').value = today.toISOString().split('T')[0];
  const hours = today.getHours(), minutes = today.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';
  const hh = hours % 12 || 12, mm = minutes < 10 ? '0' + minutes : minutes;
  document.getElementById('quickBatchDeedsTime').value = `${hh}:${mm} ${ampm}`;

  // Update instrument count
  const countElement = document.getElementById('quickBatchInstrumentCount');
  if (countElement) {
    countElement.textContent = `${quickBatchInstruments.length} instruments selected`;
  }

  // Populate the table with selected instruments
  populateQuickBatchTable();

  // Fetch next serial number
  fetchNextSerialNumberForQuickBatch();
};

// Function to close quick batch modal
window.closeQuickBatchModal = function () {
  const modal = document.getElementById('quickBatchModal');
  if (modal) {
    modal.style.display = 'none';
  }

  // Clear data
  quickBatchInstruments = [];
};

// Function to populate the quick batch table
function populateQuickBatchTable() {
  console.log('Populating quick batch table with', quickBatchInstruments.length, 'instruments');

  const table = document.getElementById('quickSelectedPropertiesTable');
  if (!table) {
    console.error("Quick selected properties table not found");
    return;
  }

  // Clear the table
  table.innerHTML = '';

  // Update register button state
  const registerButton = document.getElementById('quickBatchRegisterButton');
  if (registerButton) {
    registerButton.disabled = quickBatchInstruments.length === 0;
    registerButton.textContent = `Register ${quickBatchInstruments.length} Instrument${quickBatchInstruments.length !== 1 ? 's' : ''}`;
  }

  // Show/hide no selected properties message
  if (quickBatchInstruments.length === 0) {
    table.innerHTML = `
      <tr id="quickNoSelectedPropertiesRow">
        <td colspan="6" class="px-6 py-10 text-center text-gray-500">
          No instruments selected for registration.
        </td>
      </tr>
    `;
    return;
  }

  // Populate table with selected properties
  quickBatchInstruments.forEach((property, index) => {
    console.log('Adding property to quick table:', property.fileNo, 'ID:', property.id);
    const row = document.createElement('tr');
    row.setAttribute('data-index', index);
    row.className = 'hover:bg-gray-50';

    // Calculate serial number for display
    let serialDisplay = 'Auto-assigned';
    if (window.nextSerialData) {
      const serialNo = window.nextSerialData.serial_no + index;
      const pageNo = window.nextSerialData.page_no + index;
      const volumeNo = window.nextSerialData.volume_no;

      // Calculate display values if they roll over
      let displaySerial = serialNo;
      let displayPage = pageNo;
      let displayVolume = volumeNo;

      if (displayPage > 300) {
        displayVolume += Math.floor((displayPage - 1) / 300);
        displayPage = (displayPage - 1) % 300 + 1;
        displaySerial = displayPage;
      }

      serialDisplay = `${displaySerial}/${displayPage}/${displayVolume}`;
    }

    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${property.fileNo || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.grantor || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">${property.grantee || 'N/A'}</td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        <input type="text" class="w-full px-3 py-1 border rounded-md bg-gray-100" value="${property.instrumentType || 'N/A'}" readonly>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">${serialDisplay}</td>
      <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
        <button class="text-red-600 hover:text-red-800" onclick="removeFromQuickBatch(${index})">
          <i class="fas fa-times"></i>
        </button>
      </td>
    `;

    table.appendChild(row);
  });

  console.log('Quick batch table populated with', quickBatchInstruments.length, 'instruments');
}

// Function to remove instrument from quick batch
window.removeFromQuickBatch = function (index) {
  console.log("Removing instrument at index:", index);
  if (index >= 0 && index < quickBatchInstruments.length) {
    quickBatchInstruments.splice(index, 1);

    // Update count
    const countElement = document.getElementById('quickBatchInstrumentCount');
    if (countElement) {
      countElement.textContent = `${quickBatchInstruments.length} instruments selected`;
    }

    // Repopulate table
    populateQuickBatchTable();
  }
};

// Function to fetch next serial number for quick batch
function fetchNextSerialNumberForQuickBatch() {
  const headers = {
    'Content-Type': 'application/json'
  };

  const csrfToken = document.querySelector('meta[name="csrf-token"]');
  if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
  }

  const baseUrl = window.location.origin;

  // Get instrument type from quickBatchInstruments if available
  const instrumentType = quickBatchInstruments.length > 0 ? quickBatchInstruments[0].instrumentType : null;
  let url = `${baseUrl}/instrument_registration/get-next-serial`;
  if (instrumentType) {
    url += `?instrument_type=${encodeURIComponent(instrumentType)}`;
  }

  return fetch(url, {
    method: 'GET',
    headers: headers,
    credentials: 'same-origin'
  })
    .then(response => {
      if (!response.ok) {
        console.error(`Server returned ${response.status}: ${response.statusText}`);
        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Quick batch serial number data:', data);

      // Update quick batch serial number display
      const quickBatchNextSerialNo = document.getElementById('quickBatchNextSerialNo');
      if (quickBatchNextSerialNo) {
        quickBatchNextSerialNo.textContent = data.deeds_serial_no;
      }

      // Store the data for later use
      window.nextSerialData = data;

      // Refresh the table to show updated serial numbers
      populateQuickBatchTable();

      return data;
    })
    .catch(error => {
      console.error('Error fetching next serial number for quick batch:', error);

      // Set default values in case of error
      window.nextSerialData = {
        serial_no: 1,
        page_no: 1,
        volume_no: 1,
        deeds_serial_no: '1/1/1'
      };

      // Update UI with default values
      const quickBatchNextSerialNo = document.getElementById('quickBatchNextSerialNo');
      if (quickBatchNextSerialNo) {
        quickBatchNextSerialNo.textContent = '1/1/1 (default)';
      }

      return window.nextSerialData;
    });
}

// Function to submit quick batch registration
window.submitQuickBatchRegistration = function () {
  console.log('submitQuickBatchRegistration called');
  console.log('quickBatchInstruments:', quickBatchInstruments);
  console.log('quickBatchInstruments.length:', quickBatchInstruments.length);

  if (quickBatchInstruments.length === 0) {
    Swal.fire('Error', 'No instruments selected for registration', 'error');
    return;
  }

  // Use fallbacks for missing data
  const deedsTime = document.getElementById('quickBatchDeedsTime')?.value || new Date().toLocaleTimeString();
  const deedsDate = document.getElementById('quickBatchDeedsDate')?.value || new Date().toISOString().split('T')[0];

  // Process batch entries with proper fallbacks for missing data
  const batchEntries = quickBatchInstruments.map((p, index) => {
    // Calculate serial numbers
    let serialNo = 1 + index;
    let pageNo = 1 + index;
    let volumeNo = 1;

    if (window.nextSerialData) {
      serialNo = window.nextSerialData.serial_no + index;
      pageNo = window.nextSerialData.page_no + index;
      volumeNo = window.nextSerialData.volume_no;

      // Check if we need to move to next volume
      if (pageNo > 300) {
        volumeNo += Math.floor((pageNo - 1) / 300);
        pageNo = (pageNo - 1) % 300 + 1;
        serialNo = pageNo;
      }
    }

    // Handle different types of IDs properly
    let applicationId = null;
    if (p.id) {
      if (typeof p.id === 'string') {
        // Keep string IDs for composite IDs like 'instr_reg_1', '123_st_assignment', etc.
        applicationId = p.id;
      } else if (!isNaN(p.id)) {
        // Convert numeric IDs to integers
        applicationId = parseInt(p.id);
      }
    }

    console.log(`Processing instrument ${index + 1}:`, {
      originalId: p.id,
      originalIdType: typeof p.id,
      processedId: applicationId,
      processedIdType: typeof applicationId,
      fileNo: p.fileNo,
      grantor: p.grantor
    });

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
      serial_no: serialNo,
      page_no: pageNo,
      volume_no: volumeNo
    };
  });

  console.log('Quick batch entries:', batchEntries);

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

  // Submit to backend
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
    .then(r => r.json())
    .then(res => {
      Swal.close();
      if (res.success) {
        Swal.fire('Success', res.message, 'success');
        closeQuickBatchModal();

        // Clear main table checkboxes
        const checkboxes = document.querySelectorAll('.main-table-checkbox:checked');
        checkboxes.forEach(cb => cb.checked = false);

        // Reset batch button text
        const batchBtnText = document.getElementById('batchBtnText');
        if (batchBtnText) {
          batchBtnText.textContent = 'Registration';
        }

        // Reload page to refresh data
        window.location.reload();
      } else {
        Swal.fire('Error', res.error || res.message, 'error');
      }
    })
    .catch(e => {
      Swal.close();
      console.error(e);
      Swal.fire('Error', 'Batch request failed: ' + e.message, 'error');
    });
};

console.log('Quick batch handler loaded');
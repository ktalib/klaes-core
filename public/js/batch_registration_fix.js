// Batch Registration Fix - Make it work like Quick Batch Registration
// This file fixes the batch registration by using the same approach as quick batch

// Override the submitBatchRegistration function to work properly
window.submitBatchRegistration = function() {
  console.log('FIXED submitBatchRegistration called');
  
  // Get checked checkboxes from the batch modal
  const checkedBoxes = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])');
  console.log('Found checked boxes:', checkedBoxes.length);
  
  if (checkedBoxes.length === 0) {
    Swal.fire('Error', 'No instruments selected for batch registration. Please select instruments first.', 'error');
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
  
  // Process batch entries using the same approach as Quick Batch Registration
  const batchEntries = Array.from(checkedBoxes).map((checkbox, index) => {
    const id = checkbox.getAttribute('data-id');
    console.log('Processing checkbox ID:', id, 'Type:', typeof id);
    
    // Find the data in serverCofoData (main table data) - same as Quick Batch
    let instrumentData = null;
    
    if (typeof serverCofoData !== 'undefined') {
      const serverItem = serverCofoData.find(item => String(item.id) === String(id));
      if (serverItem) {
        instrumentData = {
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
          status: serverItem.status || 'pending'
        };
        console.log('Found instrument data in serverCofoData:', instrumentData);
      }
    }
    
    // Fallback to cofoData if not found in serverCofoData
    if (!instrumentData && typeof cofoData !== 'undefined') {
      instrumentData = cofoData.find(item => String(item.id) === String(id));
      console.log('Found instrument data in cofoData:', instrumentData);
    }
    
    if (!instrumentData) {
      console.error('Could not find instrument data for ID:', id);
      return null;
    }
    
    // Calculate serial numbers
    let serialNo = 1 + index;
    let pageNo = 1 + index;
    let volumeNo = 1;
    
    if (window.nextSerialData) {
      serialNo = window.nextSerialData.serial_no + index;
      pageNo = window.nextSerialData.page_no + index;
      volumeNo = window.nextSerialData.volume_no;
      
      // Check if we need to move to next volume
      if (pageNo > 100) {
        volumeNo++;
        pageNo = (pageNo - 1) % 100 + 1;
        serialNo = pageNo;
      }
    }
    
    // Handle different types of IDs properly (same as Quick Batch)
    let applicationId = null;
    if (instrumentData.id) {
      if (typeof instrumentData.id === 'string') {
        applicationId = instrumentData.id;
      } else if (!isNaN(instrumentData.id)) {
        applicationId = parseInt(instrumentData.id);
      }
    }
    
    console.log(`Processing instrument ${index + 1}:`, {
      originalId: instrumentData.id,
      originalIdType: typeof instrumentData.id,
      processedId: applicationId,
      processedIdType: typeof applicationId,
      fileNo: instrumentData.fileNo,
      grantor: instrumentData.grantor
    });
    
    return {
      application_id: applicationId,
      file_no: instrumentData.fileNo || 'N/A',
      instrument_type: instrumentData.instrumentType || 'N/A',
      grantor: instrumentData.grantor || 'N/A',
      grantorAddress: '',
      grantee: instrumentData.grantee || 'N/A',
      granteeAddress: '',
      duration: instrumentData.duration || 'N/A',
      propertyDescription: instrumentData.plotDescription || instrumentData.propertyDescription || 'N/A',
      lga: instrumentData.lga || 'N/A',
      district: instrumentData.district || 'N/A',
      plotNumber: instrumentData.plotNumber || 'N/A',
      size: instrumentData.plotSize || instrumentData.size || 'N/A',
      serial_no: serialNo,
      page_no: pageNo,
      volume_no: volumeNo
    };
  }).filter(entry => entry !== null);
  
  console.log('FIXED batch entries:', batchEntries);
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
  
  // Submit to backend (same endpoint as Quick Batch)
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
    if(res.success) {
      Swal.fire('Success', res.message, 'success').then(() => {
        // Close modal
        const modal = document.getElementById('batchRegisterModal');
        if (modal) {
          modal.style.display = 'none';
        }
        
        // Clear selections
        const checkboxes = document.querySelectorAll('.available-property-checkbox:checked');
        checkboxes.forEach(cb => cb.checked = false);
        
        // Reload page to refresh data
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
};

console.log('Batch Registration Fix loaded - submitBatchRegistration function overridden');

// Debug function to check data availability
window.debugBatchData = function() {
  console.log('=== BATCH REGISTRATION DEBUG ===');
  console.log('serverCofoData:', typeof serverCofoData !== 'undefined' ? serverCofoData.length + ' items' : 'undefined');
  console.log('cofoData:', typeof cofoData !== 'undefined' ? cofoData.length + ' items' : 'undefined');
  console.log('selectedBatchProperties:', typeof selectedBatchProperties !== 'undefined' ? selectedBatchProperties.length + ' items' : 'undefined');
  
  const checkedBoxes = document.querySelectorAll('.available-property-checkbox:checked:not([disabled])');
  console.log('Checked boxes in modal:', checkedBoxes.length);
  
  checkedBoxes.forEach((box, index) => {
    const id = box.getAttribute('data-id');
    console.log(\Checked box \: ID = \\);
    
    // Try to find in serverCofoData
    if (typeof serverCofoData !== 'undefined') {
      const found = serverCofoData.find(item => String(item.id) === String(id));
      console.log(\  - Found in serverCofoData: \\);
      if (found) console.log(\    FileNo: \, Type: \\);
    }
  });
  console.log('=== END DEBUG ===');
};

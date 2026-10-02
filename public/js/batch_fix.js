// Override the submitBatchRegistration function to fix the empty array issue
function submitBatchRegistration() {
  console.log('Fixed submitBatchRegistration called');
  console.log('selectedBatchProperties:', selectedBatchProperties);
  console.log('selectedBatchProperties.length:', selectedBatchProperties.length);
  
  // Always try to extract data from the table first
  const tableRows = document.querySelectorAll('#selectedPropertiesTable tr:not(#noSelectedPropertiesRow)');
  console.log('Found table rows:', tableRows.length);
  
  let batchData = [];
  
  if (tableRows.length > 0) {
    // Extract data from table rows
    batchData = Array.from(tableRows).map((row, index) => {
      const cells = row.querySelectorAll('td');
      if (cells.length >= 5) {
        return {
          id: `table_row_${index}`,
          fileNo: cells[0].textContent.trim(),
          grantor: cells[1].textContent.trim(),
          grantee: cells[2].textContent.trim(),
          instrumentType: cells[3].querySelector('input')?.value || cells[3].textContent.trim(),
          serialData: {
            deeds_serial_no: cells[4].textContent.trim(),
            serial_no: 1,
            page_no: 1,
            volume_no: 1
          }
        };
      }
      return null;
    }).filter(item => item !== null);
    
    console.log('Extracted data from table:', batchData);
  } else if (selectedBatchProperties.length > 0) {
    // Fallback to selectedBatchProperties if table is empty
    batchData = selectedBatchProperties;
    console.log('Using selectedBatchProperties:', batchData);
  }
  
  // Use fallbacks for missing data
  const deedsTime = document.getElementById('batchDeedsTime')?.value || new Date().toLocaleTimeString();
  const deedsDate = document.getElementById('batchDeedsDate')?.value || new Date().toISOString().split('T')[0];
  
  // Process batch entries with proper fallbacks for missing data
  const batchEntries = batchData.map(p => {
    // Ensure application_id is a valid number or null
    let applicationId = null;
    if (p.id && !isNaN(p.id)) {
      applicationId = parseInt(p.id);
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
      serial_no: (p.serialData && p.serialData.serial_no) || 1,
      page_no: (p.serialData && p.serialData.page_no) || 1,
      volume_no: (p.serialData && p.serialData.volume_no) || 1
    };
  });
  
  console.log('batchEntries:', batchEntries);
  
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
    Swal.close();
    if(res.success) {
      Swal.fire('Success', res.message, 'success');
      closeBatchRegisterModal();
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
}

console.log('Batch fix loaded - submitBatchRegistration function overridden');
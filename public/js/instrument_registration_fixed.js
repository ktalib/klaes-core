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
      Swal.fire({
        title: 'Instruments Not Added to Batch',
        text: `You have selected ${checkedCount} instruments but haven't added them to the batch. Please click "Add Selected Instruments" button first.`,
        icon: 'warning',
        confirmButtonText: 'OK'
      });
    } else {
      Swal.fire('Error', 'No instruments selected for batch registration. Please select instruments first.', 'error');
    }
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
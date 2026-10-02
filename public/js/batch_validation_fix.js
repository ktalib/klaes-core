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
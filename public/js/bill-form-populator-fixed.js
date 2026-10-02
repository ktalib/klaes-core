// Comprehensive bill form populator
$(document).ready(function() {
    
    // Function to populate betterment bill form
    function populateBettermentForm(applicationId, applicationType) {
        if (!applicationId) return;
        
        console.log('Populating betterment form for:', applicationId, applicationType);
        
        // Fetch betterment bill data
        fetch(`${window.location.origin}/betterment-bill/show/${applicationId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Betterment bill data received:', data);
                
                if (data.success && data.bill) {
                    // Populate form fields with saved data
                    if ($('#betterment-property-value').length) {
                        $('#betterment-property-value').val(parseFloat(data.bill.property_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    }
                    if ($('#betterment-rate').length) {
                        $('#betterment-rate').val(parseFloat(data.bill.betterment_rate || 0));
                    }
                    if ($('#betterment-amount').length) {
                        $('#betterment-amount').text('₦' + parseFloat(data.bill.Betterment_Charges || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    }
                    
                    // Grey out fields if bill is already saved
                    if (parseFloat(data.bill.Betterment_Charges || 0) > 0) {
                        $('#betterment-property-value').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#betterment-rate').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#calculate-betterment-btn').prop('disabled', true).text('Already Calculated').css('background-color', '#9ca3af');
                        $('#generate-betterment-btn').prop('disabled', true).text('Already Generated').css('background-color', '#9ca3af');
                    }
                }
                
                // Populate application-specific fields
                if (data.application) {
                    if ($('#betterment-land-size').length) {
                        $('#betterment-land-size').val(parseFloat(data.application.plot_size || 1200).toLocaleString('en-US'));
                    }
                    if ($('#betterment-units-count').length) {
                        $('#betterment-units-count').val(parseInt(data.application.NoOfUnits || 1));
                    }
                    
                    // Grey out application fields
                    $('#betterment-land-size').prop('readonly', true).css('background-color', '#f3f4f6');
                    $('#betterment-units-count').prop('readonly', true).css('background-color', '#f3f4f6');
                }
            })
            .catch(error => {
                console.error('Error fetching betterment bill:', error);
                
                // Still try to populate application fields even if bill doesn't exist
                fetch(`${window.location.origin}/gisedms/application-details/${applicationId}/${applicationType}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.application) {
                            if ($('#betterment-land-size').length) {
                                $('#betterment-land-size').val(parseFloat(data.application.plot_size || 1200).toLocaleString('en-US'));
                            }
                            if ($('#betterment-units-count').length) {
                                $('#betterment-units-count').val(parseInt(data.application.NoOfUnits || 1));
                            }
                        }
                    })
                    .catch(err => console.error('Error fetching application details:', err));
            });
    }
    
    // Function to populate balance bill form
    function populateBalanceForm(applicationId, applicationType) {
        if (!applicationId) return;
        
        console.log('Populating balance form for:', applicationId, applicationType);
        
        // Fetch final bill data
        fetch(`${window.location.origin}/sub-final-bill/show/${applicationId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Final bill data received:', data);
                
                if (data.success && data.bill) {
                    // Populate form fields with saved data
                    if ($('#balance-assignment-fee').length) {
                        $('#balance-assignment-fee').val(parseFloat(data.bill.assignment_fee || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    }
                    if ($('#balance-bill-balance').length) {
                        $('#balance-bill-balance').val(parseFloat(data.bill.bill_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    }
                    if ($('#balance-recertification-fee').length) {
                        $('#balance-recertification-fee').val(parseFloat(data.bill.recertification_fee || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    }
                    if ($('#balance-dev-charges').length) {
                        $('#balance-dev-charges').val(parseFloat(data.bill.dev_charges || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    }
                    if ($('#balance-bill-date').length) {
                        $('#balance-bill-date').val(data.bill.bill_date || '');
                    }
                    
                    // Grey out fields if bill is already saved
                    var totalAmount = parseFloat(data.bill.total_amount || 0);
                    if (totalAmount > 0) {
                        $('#balance-assignment-fee').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#balance-bill-balance').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#balance-recertification-fee').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#balance-dev-charges').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#balance-bill-date').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#save-balance-bill-btn').prop('disabled', true).text('Already Generated').css('background-color', '#9ca3af');
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching final bill:', error);
            });
    }
    
    // Main function to populate forms
    window.populateAllForms = function() {
        if (typeof currentApplication !== 'undefined' && currentApplication && currentApplication.fileId) {
            console.log('Populating all forms for application:', currentApplication);
            
            setTimeout(function() {
                populateBettermentForm(currentApplication.fileId, currentApplication.fileType);
                populateBalanceForm(currentApplication.fileId, currentApplication.fileType);
            }, 500);
        }
    };
    
    // Event listeners for form population
    $(document).on('click', '[data-tab="generate"]', function() {
        setTimeout(populateAllForms, 300);
    });
    
    $(document).on('click', '[data-tab="calculate"]', function() {
        setTimeout(populateAllForms, 300);
    });
    
    $(document).on('click', '[data-tab="receipt"]', function() {
        setTimeout(populateAllForms, 300);
    });
    
    $(document).on('click', '[data-tab="preview"]', function() {
        setTimeout(populateAllForms, 300);
    });
    
    // Listen for file selection
    $(document).on('change', '#filenoSelect', function() {
        setTimeout(populateAllForms, 1000);
    });
    
    // Listen for application selection
    $(document).on('click', '.file-item', function() {
        setTimeout(populateAllForms, 1000);
    });
    
    // Auto-populate on page load
    setTimeout(populateAllForms, 2000);
    
    // Periodic check to ensure forms are populated
    setInterval(function() {
        if (typeof currentApplication !== 'undefined' && currentApplication && currentApplication.fileId) {
            // Check if betterment form exists but is empty
            if ($('#betterment-property-value').length && $('#betterment-property-value').val() === '') {
                populateBettermentForm(currentApplication.fileId, currentApplication.fileType);
            }
            
            // Check if balance form exists but is empty
            if ($('#balance-assignment-fee').length && $('#balance-assignment-fee').val() === '') {
                populateBalanceForm(currentApplication.fileId, currentApplication.fileType);
            }
        }
    }, 3000);
});
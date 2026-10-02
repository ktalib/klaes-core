// Function to calculate land size factor
function calculateLandSizeFactor(landSize) {
    var size = parseFloat(landSize);
    
    if (size <= 500) return 0.8;       // Small land plots
    else if (size <= 1000) return 1.0; // Medium land plots
    else if (size <= 2000) return 1.2; // Large land plots
    else return 1.5;                   // Very large land plots
}

// Fix betterment bill calculation
$(document).ready(function() {
    // Override the existing betterment calculation function
    $(document).off('click', '#calculate-betterment-btn').on('click', '#calculate-betterment-btn', function() {
        var propertyValue = parseFloat($('#betterment-property-value').val().replace(/,/g, '')) || 0;
        var bettermentRate = parseFloat($('#betterment-rate').val()) || 0;
        var landSize = parseFloat($('#betterment-land-size').val().replace(/,/g, '')) || 1200;
        
        if (propertyValue === 0 || bettermentRate === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Input',
                text: 'Please enter valid property value and betterment rate.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }
        
        // Show loading
        Swal.fire({
            title: 'Calculating...',
            text: 'Please wait while we calculate the betterment charges.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Calculate land size factor
        var landSizeFactor = calculateLandSizeFactor(landSize);
        
        // Calculate betterment charges using the new formula: Property Value × Betterment Rate × Land Size Factor
        var bettermentAmount = propertyValue * (bettermentRate / 100) * landSizeFactor;
        
        // Simulate calculation delay
        setTimeout(() => {
            $('#betterment-amount').text('₦' + bettermentAmount.toLocaleString('en-US', {minimumFractionDigits: 2}));
            
            Swal.fire({
                icon: 'success',
                title: 'Calculation Complete!',
                text: `Betterment charges calculated: ₦${bettermentAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}`,
                confirmButtonColor: '#10b981'
            });
        }, 1500);
    });

    // Override the existing betterment bill generation function
    $(document).off('click', '#generate-betterment-btn').on('click', '#generate-betterment-btn', function() {
        var propertyValue = parseFloat($('#betterment-property-value').val().replace(/,/g, '')) || 0;
        var bettermentRate = parseFloat($('#betterment-rate').val()) || 0;
        var landSize = parseFloat($('#betterment-land-size').val().replace(/,/g, '')) || 1200;
        
        if (propertyValue === 0 || bettermentRate === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Calculation',
                text: 'Please calculate the betterment amount first.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }
        
        // Calculate land size factor and betterment amount
        var landSizeFactor = calculateLandSizeFactor(landSize);
        var bettermentAmount = propertyValue * (bettermentRate / 100) * landSizeFactor;
        
        // Show loading
        Swal.fire({
            title: 'Generating Bill...',
            text: 'Please wait while we generate your betterment bill.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Save betterment bill to database via AJAX
        var billData = {
            application_id: currentApplication.fileId,
            property_value: propertyValue,
            betterment_rate: bettermentRate,
            land_size: landSize,
            ref_id: `BB-${currentApplication.fileId}-${new Date().toISOString().slice(0,10).replace(/-/g,'')}`,
            Sectional_Title_File_No: `ST-${currentApplication.fileId}`,
            _token: $('meta[name="csrf-token"]').attr('content')
        };
        
        // Send AJAX request to save the bill
        fetch(`${window.location.origin}/betterment-bill/store`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify(billData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update receipt tab with generated bill
                var numberOfUnits = parseFloat($('#betterment-number-of-units').val()) || 1;
                var receiptHtml = getBettermentReceiptHtml(propertyValue, bettermentRate, bettermentAmount, landSize, numberOfUnits);
                
                $('#betterment-receipt-container').html(receiptHtml);
                $('.betterment-tab-button[data-tab="receipt"]').click();
                
                // Reinitialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Bill Generated & Saved Successfully!',
                    text: `Betterment bill generated for ₦${bettermentAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}`,
                    confirmButtonColor: '#10b981'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error Saving Bill',
                    text: data.message || 'Failed to save betterment bill. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            console.error('Error saving betterment bill:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save betterment bill. Please try again.',
                confirmButtonColor: '#ef4444'
            });
        });
    });

    // Function to generate betterment receipt HTML with improved UI
    window.getBettermentReceiptHtml = function(propertyValue, bettermentRate, bettermentAmount, landSize, numberOfUnits) {
        // Ensure all values are valid numbers with defaults
        var safePropertyValue = parseFloat(propertyValue) || 0;
        var safeBettermentRate = parseFloat(bettermentRate) || 0;
        var safeBettermentAmount = parseFloat(bettermentAmount) || 0;
        var safeLandSize = parseFloat(landSize) || 1200;
        var safeNumberOfUnits = parseFloat(numberOfUnits) || 1;
        
        return `
            <div class="print-area">
                <style>
                    .print-area {
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        max-width: 800px;
                        margin: 0 auto;
                    }
                    .print-header {
                        text-align: center;
                        margin-bottom: 30px;
                        border-bottom: 3px solid #dc2626;
                        padding-bottom: 20px;
                    }
                    .print-logos {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                    }
                    .print-logo {
                        width: 80px;
                        height: 80px;
                        object-fit: contain;
                    }
                    .print-title h1 {
                        color: #1e40af;
                        font-size: 24px;
                        font-weight: bold;
                        margin: 0 0 10px 0;
                        text-transform: uppercase;
                    }
                    .print-title h2 {
                        color: #dc2626;
                        font-size: 20px;
                        font-weight: 600;
                        margin: 0;
                        text-transform: uppercase;
                    }
                    .print-content {
                        line-height: 1.6;
                    }
                    .print-date-ref {
                        background: #fef2f2;
                        padding: 15px;
                        border-radius: 6px;
                        margin-bottom: 20px;
                        border-left: 4px solid #dc2626;
                    }
                    .print-date-ref p {
                        margin: 5px 0;
                        font-weight: 500;
                    }
                    .ref-highlight {
                        background: #fef3c7;
                        padding: 2px 6px;
                        border-radius: 4px;
                        font-weight: bold;
                        color: #92400e;
                    }
                    .print-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                        background: white;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .print-table th {
                        background: #dc2626;
                        color: white;
                        padding: 15px;
                        text-align: left;
                        font-weight: 600;
                        text-transform: uppercase;
                        font-size: 14px;
                    }
                    .print-table td {
                        padding: 12px 15px;
                        border-bottom: 1px solid #e5e7eb;
                    }
                    .print-table tr:nth-child(even) {
                        background: #f9fafb;
                    }
                    .print-table tr:hover {
                        background: #f3f4f6;
                    }
                    .total-row {
                        background: #fef2f2 !important;
                        border-top: 2px solid #dc2626;
                        font-weight: bold;
                    }
                    .total-row td {
                        font-size: 16px;
                        color: #991b1b;
                    }
                    .print-footer {
                        background: #f8fafc;
                        padding: 20px;
                        border-radius: 6px;
                        margin-top: 30px;
                        border-left: 4px solid #dc2626;
                    }
                    .print-footer p {
                        margin: 8px 0;
                        color: #374151;
                    }
                    @media print {
                        .no-print { display: none !important; }
                        .print-area { box-shadow: none; margin: 0; padding: 20px; }
                    }
                </style>
                
                <!-- Header with logos -->
                <div class="print-header">
                    <div class="print-logos">
                        <div class="print-logo-left">
                            <img src="/assets/logo/logo1.jpg" alt="Kano State Logo" class="print-logo">
                        </div>
                        <div class="print-title">
                            <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                            <h2>BETTERMENT CHARGES BILL</h2>
                        </div>
                        <div class="print-logo-right">
                            <img src="/assets/logo/logo3.jpeg" alt="Ministry Logo" class="print-logo">
                        </div>
                    </div>
                </div>
                
                <div class="print-content">
                    <!-- Date and Reference -->
                    <div class="print-date-ref">
                        <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p><strong>Bill Reference:</strong> <span class="ref-highlight">BB-${currentApplication.fileId}-${new Date().toISOString().slice(0,10).replace(/-/g,'')}</span></p>
                    </div>
                    
                    <!-- Introduction -->
                    <div style="margin-bottom: 20px;">
                        <p>Dear Sir/Madam,</p>
                        <p>I am directed to inform you that the betterment charges for your primary application with the following particulars:</p>
                    </div>
                    
                    <!-- Property Details -->
                    <div style="margin-bottom: 20px;">
                        <p><strong>File No:</strong> ${currentApplication.fileno}</p>
                        <p><strong>Name of Applicant:</strong> ${currentApplication.owner}</p>
                    </div>
                    
                    <!-- Calculation Table -->
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="text-align: right;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Property Value (₦)</td>
                                <td style="text-align: right;">${safePropertyValue.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td>Betterment Rate (%)</td>
                                <td style="text-align: right;">${safeBettermentRate}%</td>
                            </tr>
                            <tr>
                                <td>Land Size (sqm)</td>
                                <td style="text-align: right;">${safeLandSize.toLocaleString('en-US')}</td>
                            </tr>
                            <tr>
                                <td>Number of Units</td>
                                <td style="text-align: right;">${safeNumberOfUnits}</td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>Total Betterment Charges (₦)</strong></td>
                                <td style="text-align: right;"><strong>${safeBettermentAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Footer Text -->
                    <div class="print-footer">
                        <p>You are hereby directed to settle this bill promptly in order to accelerate the processing of your application.</p>
                        <p><strong>Note:</strong> Documentary Payments can be made at the Checkout-Point and KANGIS Cashier's Office.</p>
                        <p>Thank you.</p>
                    </div>
                </div>
                
                <!-- Action Buttons (no-print) -->
                <div class="no-print mt-6 flex gap-2">
                    <button onclick="printBettermentBill()" class="flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                        Print Bill
                    </button>
                </div>
            </div>
        `;
    };

    // Function to print betterment bill
    window.printBettermentBill = function() {
        // Open the print route in a new window
        if (currentApplication.fileId) {
            window.open(`/betterment-bill/print/${currentApplication.fileId}`, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No application selected for printing.',
                confirmButtonColor: '#ef4444'
            });
        }
    };

    // Fix balance bill preview functionality
    window.checkForSavedBalanceBill = function() {
        if (!currentApplication.fileId) {
            return;
        }

        // Make AJAX request to check for saved bill
        fetch(`${window.location.origin}/sub-final-bill/show/${currentApplication.fileId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.bill) {
                    // Display the saved bill
                    var previewHtml = getBalancePreviewHtml(
                        data.bill.assignment_fee,
                        data.bill.bill_balance,
                        data.bill.recertification_fee,
                        data.bill.dev_charges || 0,
                        data.bill.total_amount,
                        data.bill.bill_date
                    );
                    
                    $('#balance-preview-container').html(previewHtml);
                    
                    // Auto-populate and grey out the input fields if values exist
                    var assignmentFee = parseFloat(data.bill.assignment_fee) || 0;
                    var billBalance = parseFloat(data.bill.bill_balance) || 0;
                    var recertificationFee = parseFloat(data.bill.recertification_fee) || 0;
                    var devCharges = parseFloat(data.bill.dev_charges) || 0;
                    
                    if (assignmentFee > 0 || billBalance > 0 || recertificationFee > 0 || devCharges > 0) {
                        $('#balance-assignment-fee').val(assignmentFee.toLocaleString('en-US', {minimumFractionDigits: 2})).prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#balance-bill-balance').val(billBalance.toLocaleString('en-US', {minimumFractionDigits: 2})).prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#balance-recertification-fee').val(recertificationFee.toLocaleString('en-US', {minimumFractionDigits: 2})).prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#balance-dev-charges').val(devCharges.toLocaleString('en-US', {minimumFractionDigits: 2})).prop('readonly', true).css('background-color', '#f3f4f6');
                        if ($('#balance-bill-date').length) {
                            $('#balance-bill-date').val(data.bill.bill_date).prop('readonly', true).css('background-color', '#f3f4f6');
                        }
                        
                        // Disable save button
                        $('#save-balance-bill-btn').prop('disabled', true).text('Already Generated').css('background-color', '#9ca3af');
                    }
                    
                    // Reinitialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for saved balance bill:', error);
            });
    };

    // Function to check for saved betterment bill
    window.checkForSavedBettermentBill = function() {
        if (!currentApplication.fileId) {
            return;
        }

        // Make AJAX request to check for saved betterment bill
        fetch(`${window.location.origin}/betterment-bill/show/${currentApplication.fileId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.bill && data.application) {
                    // Ensure we have valid numeric values from the database
                    var propertyValue = parseFloat(data.bill.property_value) || 0;
                    var bettermentRate = parseFloat(data.bill.betterment_rate) || 0;
                    var bettermentCharges = parseFloat(data.bill.Betterment_Charges) || 0;
                    var landSize = parseFloat(data.application.plot_size) || 1200;
                    var numberOfUnits = parseInt(data.application.NoOfUnits) || 1;
                    
                    // Update the form fields with actual database values
                    $('#betterment-land-size').val(landSize.toLocaleString('en-US'));
                    $('#betterment-units-count').val(numberOfUnits);
                    
                    // Display the saved betterment bill
                    var receiptHtml = getBettermentReceiptHtml(propertyValue, bettermentRate, bettermentCharges, landSize, numberOfUnits);
                    $('#betterment-receipt-container').html(receiptHtml);
                    
                    // Auto-populate and grey out the input fields if values exist
                    if (propertyValue > 0 || bettermentRate > 0) {
                        $('#betterment-property-value').val(propertyValue.toLocaleString('en-US', {minimumFractionDigits: 2})).prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#betterment-rate').val(bettermentRate).prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#betterment-land-size').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#betterment-units-count').prop('readonly', true).css('background-color', '#f3f4f6');
                        $('#betterment-amount').text('₦' + bettermentCharges.toLocaleString('en-US', {minimumFractionDigits: 2}));
                        
                        // Disable calculation and generation buttons
                        $('#calculate-betterment-btn').prop('disabled', true).text('Already Calculated').css('background-color', '#9ca3af');
                        $('#generate-betterment-btn').prop('disabled', true).text('Already Generated').css('background-color', '#9ca3af');
                    }
                    
                    // Reinitialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                } else {
                    // No saved bill found, populate land size and units from application data if available
                    if (data.application) {
                        var landSize = parseFloat(data.application.plot_size) || 1200;
                        var numberOfUnits = parseInt(data.application.NoOfUnits) || 1;
                        
                        $('#betterment-land-size').val(landSize.toLocaleString('en-US'));
                        $('#betterment-units-count').val(numberOfUnits);
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for saved betterment bill:', error);
            });
    };

    // Function to fetch application details for betterment bill form
    window.fetchApplicationDetails = function(fileId, fileType) {
        if (!fileId || !fileType) {
            return;
        }
        
        fetch(`${window.location.origin}/gisedms/application-details/${fileId}/${fileType}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.application) {
                    var landSize = parseFloat(data.application.plot_size) || 1200;
                    var unitsCount = parseInt(data.application.NoOfUnits) || 1;
                    
                    // Update the form fields if they exist
                    if ($('#betterment-land-size').length) {
                        $('#betterment-land-size').val(landSize.toLocaleString('en-US'));
                    }
                    if ($('#betterment-units-count').length) {
                        $('#betterment-units-count').val(unitsCount);
                    }
                    
                    console.log('Application details loaded:', {
                        landSize: landSize,
                        unitsCount: unitsCount
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching application details:', error);
            });
    };

    // Function to generate balance preview HTML with improved UI
    window.getBalancePreviewHtml = function(assignmentFee, billBalance, recertificationFee, devCharges, totalAmount, billDate) {
        // Ensure all values are valid numbers with defaults
        var safeAssignmentFee = parseFloat(assignmentFee) || 0;
        var safeBillBalance = parseFloat(billBalance) || 0;
        var safeRecertificationFee = parseFloat(recertificationFee) || 0;
        var safeDevCharges = parseFloat(devCharges) || 0;
        var safeTotalAmount = parseFloat(totalAmount) || (safeAssignmentFee + safeBillBalance + safeRecertificationFee + safeDevCharges);
        var safeBillDate = billDate || new Date().toISOString().slice(0,10);
        
        return `
            <div class="print-area">
                <style>
                    .print-area {
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        max-width: 800px;
                        margin: 0 auto;
                    }
                    .print-header {
                        text-align: center;
                        margin-bottom: 30px;
                        border-bottom: 3px solid #2563eb;
                        padding-bottom: 20px;
                    }
                    .print-logos {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                    }
                    .print-logo {
                        width: 80px;
                        height: 80px;
                        object-fit: contain;
                    }
                    .print-title h1 {
                        color: #1e40af;
                        font-size: 24px;
                        font-weight: bold;
                        margin: 0 0 10px 0;
                        text-transform: uppercase;
                    }
                    .print-title h2 {
                        color: #dc2626;
                        font-size: 20px;
                        font-weight: 600;
                        margin: 0;
                        text-transform: uppercase;
                    }
                    .print-content {
                        line-height: 1.6;
                    }
                    .print-date-ref {
                        background: #f8fafc;
                        padding: 15px;
                        border-radius: 6px;
                        margin-bottom: 20px;
                        border-left: 4px solid #2563eb;
                    }
                    .print-date-ref p {
                        margin: 5px 0;
                        font-weight: 500;
                    }
                    .ref-highlight {
                        background: #fef3c7;
                        padding: 2px 6px;
                        border-radius: 4px;
                        font-weight: bold;
                        color: #92400e;
                    }
                    .print-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                        background: white;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .print-table th {
                        background: #2563eb;
                        color: white;
                        padding: 15px;
                        text-align: left;
                        font-weight: 600;
                        text-transform: uppercase;
                        font-size: 14px;
                    }
                    .print-table td {
                        padding: 12px 15px;
                        border-bottom: 1px solid #e5e7eb;
                    }
                    .print-table tr:nth-child(even) {
                        background: #f9fafb;
                    }
                    .print-table tr:hover {
                        background: #f3f4f6;
                    }
                    .total-row {
                        background: #ecfdf5 !important;
                        border-top: 2px solid #10b981;
                        font-weight: bold;
                    }
                    .total-row td {
                        font-size: 16px;
                        color: #065f46;
                    }
                    .print-footer {
                        background: #f8fafc;
                        padding: 20px;
                        border-radius: 6px;
                        margin-top: 30px;
                        border-left: 4px solid #10b981;
                    }
                    .print-footer p {
                        margin: 8px 0;
                        color: #374151;
                    }
                    @media print {
                        .no-print { display: none !important; }
                        .print-area { box-shadow: none; margin: 0; padding: 20px; }
                    }
                </style>
                
                <!-- Header with logos -->
                <div class="print-header">
                    <div class="print-logos">
                        <div class="print-logo-left">
                            <img src="/assets/logo/logo1.jpg" alt="Kano State Logo" class="print-logo">
                        </div>
                        <div class="print-title">
                            <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                            <h2>UNIT APPLICATION FINAL BILL BALANCE</h2>
                        </div>
                        <div class="print-logo-right">
                            <img src="/assets/logo/logo3.jpeg" alt="Ministry Logo" class="print-logo">
                        </div>
                    </div>
                </div>
                
                <div class="print-content">
                    <!-- Date and Reference -->
                    <div class="print-date-ref">
                        <p><strong>Date:</strong> ${new Date(safeBillDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p><strong>Bill Reference:</strong> <span class="ref-highlight">UB-${currentApplication.fileId}-${new Date().toISOString().slice(0,10).replace(/-/g,'')}</span></p>
                    </div>
                    
                    <!-- Introduction -->
                    <div style="margin-bottom: 20px;">
                        <p>Dear Sir/Madam,</p>
                        <p>Please find below the breakdown of your Unit Application Final Bill Balance for the following application:</p>
                    </div>
                    
                    <!-- Property Details -->
                    <div style="margin-bottom: 20px;">
                        <p><strong>File No:</strong> ${currentApplication.fileno}</p>
                        <p><strong>Name of Applicant:</strong> ${currentApplication.owner}</p>
                    </div>
                    
                    <!-- Bill Breakdown Table -->
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="text-align: right;">Amount (₦)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Assignment Fee</td>
                                <td style="text-align: right;">${safeAssignmentFee.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td>Bill Balance</td>
                                <td style="text-align: right;">${safeBillBalance.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td>Recertification Fee</td>
                                <td style="text-align: right;">${safeRecertificationFee.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td>Development Charges</td>
                                <td style="text-align: right;">${safeDevCharges.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>Total Amount Due</strong></td>
                                <td style="text-align: right;"><strong>${safeTotalAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Footer Text -->
                    <div class="print-footer">
                        <p>You are hereby directed to settle this bill promptly to complete the processing of your application.</p>
                        <p><strong>Note:</strong> Payments can be made at the Checkout-Point and KANGIS Cashier's Office.</p>
                        <p>Thank you for your cooperation.</p>
                    </div>
                </div>
                
                <!-- Action Buttons (no-print) -->
                <div class="no-print mt-6 flex gap-2">
                    <button onclick="printBalanceBill()" class="flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                        Print Bill
                    </button>
                </div>
            </div>
        `;
    };

    // Function to print balance bill
    window.printBalanceBill = function() {
        if (currentApplication.fileId) {
            window.open(`/sub-final-bill/print/${currentApplication.fileId}`, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No application selected for printing.',
                confirmButtonColor: '#ef4444'
            });
        }
    };

    // Function to auto-load saved bills when application is selected
    window.loadSavedBills = function() {
        if (currentApplication && currentApplication.fileId) {
            checkForSavedBalanceBill();
            checkForSavedBettermentBill();
        }
    };

    // Auto-load saved bills when page loads
    setTimeout(function() {
        loadSavedBills();
    }, 1000);

    // Listen for application selection changes
    $(document).on('click', '.application-row, .application-item', function() {
        setTimeout(function() {
            loadSavedBills();
        }, 500);
    });

    // Listen for tab changes to load bills when switching to bill tabs
    $(document).on('click', '.balance-tab-button[data-tab="preview"]', function() {
        setTimeout(function() {
            checkForSavedBalanceBill();
        }, 100);
    });

    $(document).on('click', '.betterment-tab-button[data-tab="receipt"]', function() {
        setTimeout(function() {
            checkForSavedBettermentBill();
        }, 100);
    });

    // Override the original checkForSavedBalanceBill function if it exists elsewhere
    if (typeof window.originalCheckForSavedBalanceBill === 'undefined') {
        window.originalCheckForSavedBalanceBill = window.checkForSavedBalanceBill;
    }

    // Fix balance bill save functionality
    $(document).off('click', '#save-balance-bill-btn').on('click', '#save-balance-bill-btn', function() {
        var assignmentFee = parseFloat($('#balance-assignment-fee').val()) || 0;
        var billBalance = parseFloat($('#balance-bill-balance').val()) || 0;
        var recertificationFee = parseFloat($('#balance-recertification-fee').val()) || 0;
        var devCharges = parseFloat($('#balance-dev-charges').val()) || 0;
        var totalAmount = assignmentFee + billBalance + recertificationFee + devCharges;
        var billDate = $('#balance-bill-date').val() || new Date().toISOString().slice(0,10);
        
        if (totalAmount === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Amounts',
                text: 'Please enter valid amounts for the fees.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }
        
        if (!currentApplication.fileId) {
            Swal.fire({
                icon: 'warning',
                title: 'No Application Selected',
                text: 'Please select an application first.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }
        
        // Show loading
        Swal.fire({
            title: 'Generating Balance Bill...',
            text: 'Please wait while we generate your balance bill.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Prepare bill data
        var billData = {
            sub_application_id: currentApplication.fileId,
            assignment_fee: assignmentFee,
            bill_balance: billBalance,
            recertification_fee: recertificationFee,
            dev_charges: devCharges,
            bill_date: billDate,
            bill_status: 'generated',
            _token: $('meta[name="csrf-token"]').attr('content')
        };
        
        // Send AJAX request to save the bill
        fetch(`${window.location.origin}/sub-final-bill/save`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify(billData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Balance Bill Generated & Saved!',
                    text: `Balance bill generated successfully! Total Amount: ₦${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}`,
                    confirmButtonColor: '#10b981'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error Saving Bill',
                    text: data.message || 'Failed to save balance bill. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            console.error('Error saving balance bill:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save balance bill. Please try again.',
                confirmButtonColor: '#ef4444'
            });
        });
    });
});
/**
 * Primary Application Form - Summary Functions
 * Handles form summary generation and display
 */

// Function to update application summary
function updateApplicationSummary() {
    console.log('🔄 Updating application summary...');
    
    try {
        // Collect form data from all steps
        const formData = collectFormData();
        console.log('📋 Form data collected:', formData);
        
        // Update summary sections
        updateApplicantSummary(formData);
        updatePropertySummary(formData);
        updateDocumentsSummary(formData);
        updateBuyersSummary(formData);
        updateSharedAreasSummary(formData);
        updateFeesSummary(formData);
        
        console.log('✅ Application summary updated successfully');
    } catch (error) {
        console.error('❌ Error updating application summary:', error);
    }
}

// Function to collect all form data
function collectFormData() {
    console.log('Collecting form data...');
    const formData = {};
    
    // Step 1: Basic Information
    // Try both naming conventions for applicant type
    const applicantTypeInput = document.querySelector('input[name="applicantType"]:checked') || 
                              document.querySelector('input[name="applicant_type"]:checked');
    formData.applicantType = applicantTypeInput?.value || 
                            document.querySelector('input[name="applicant_type"]')?.value || '';
    
    // Try multiple field name variants
    formData.applicantTitle = document.querySelector('select[name="applicant_title"]')?.value || 
                             document.querySelector('select[name="title"]')?.value || '';
    formData.firstName = document.querySelector('input[name="first_name"]')?.value || 
                        document.querySelector('input[name="fname"]')?.value || '';
    formData.middleName = document.querySelector('input[name="middle_name"]')?.value || 
                         document.querySelector('input[name="mname"]')?.value || '';
    formData.surname = document.querySelector('input[name="surname"]')?.value || 
                      document.querySelector('input[name="lname"]')?.value || '';
    formData.corporateName = document.querySelector('input[name="corporate_name"]')?.value || '';
    formData.rcNumber = document.querySelector('input[name="rc_number"]')?.value || '';
    formData.phoneNumber = document.querySelector('input[name="phone_number"]')?.value || 
                          document.querySelector('input[name="phone"]')?.value || '';
    formData.email = document.querySelector('input[name="owner_email"]')?.value || 
                    document.querySelector('input[name="email"]')?.value || '';
    
    // Try to get ID type from radio or select
    const idTypeRadio = document.querySelector('input[name="idType"]:checked') ||
                       document.querySelector('input[name="identification_type"]:checked');
    formData.idType = idTypeRadio?.value || 
                     document.querySelector('select[name="idType"]')?.value || 
                     document.querySelector('select[name="identification_type"]')?.value || '';
    
    // Get residence type (property type)
    formData.residenceType = document.querySelector('select[name="residenceType"]')?.value || 
                            document.querySelector('select[name="property_type"]')?.value ||
                            document.querySelector('input[name="land_use"]')?.value || '';
    
    console.log('Collected basic info:', {
        applicantType: formData.applicantType,
        firstName: formData.firstName,
        email: formData.email,
        phoneNumber: formData.phoneNumber
    });
    
    // Address information
    formData.addressHouseNo = document.querySelector('input[name="address_house_no"]')?.value || '';
    formData.ownerStreetName = document.querySelector('input[name="owner_street_name"]')?.value || '';
    formData.ownerDistrict = document.querySelector('input[name="owner_district"]')?.value || '';
    formData.ownerLga = document.querySelector('select[name="owner_lga"]')?.value || '';
    formData.ownerState = document.querySelector('select[name="owner_state"]')?.value || '';
    
    console.log('Collected address info:', {
        houseNo: formData.addressHouseNo,
        lga: formData.ownerLga,
        state: formData.ownerState
    });
    
    // Property information
    formData.schemeNo = document.querySelector('input[name="scheme_no"]')?.value || '';
    formData.propertyHouseNo = document.getElementById('propertyHouseNo')?.value || 
                              document.querySelector('input[name="property_house_no"]')?.value || '';
    formData.propertyPlotNo = document.getElementById('propertyPlotNo')?.value || 
                             document.querySelector('input[name="property_plot_no"]')?.value || '';
    formData.propertyStreetName = document.getElementById('propertyStreetName')?.value || 
                                 document.querySelector('input[name="property_street_name"]')?.value || '';
    formData.propertyDistrict = document.querySelector('input[name="propertyDistrict"]')?.value || 
                               document.querySelector('input[name="property_district"]')?.value || '';
    formData.propertyLga = document.querySelector('select[name="property_lga"]')?.value || 
                          document.getElementById('propertyLga')?.value || '';
    formData.propertyState = document.querySelector('select[name="property_state"]')?.value || '';
    formData.plotSize = document.querySelector('input[name="plot_size"]')?.value || '';
    formData.unitsCount = document.querySelector('input[name="units_count"]')?.value || '';
    formData.blocksCount = document.querySelector('input[name="blocks_count"]')?.value || '';
    formData.sectionsCount = document.querySelector('input[name="sections_count"]')?.value || '';
    
    console.log('Collected property info:', {
        propertyHouseNo: formData.propertyHouseNo,
        propertyPlotNo: formData.propertyPlotNo,
        propertyStreetName: formData.propertyStreetName,
        propertyLga: formData.propertyLga,
        propertyState: formData.propertyState,
        propertyDistrict: formData.propertyDistrict,
        unitsCount: formData.unitsCount,
        blocksCount: formData.blocksCount,
        sectionsCount: formData.sectionsCount
    });
    
    // Land use information
    formData.landUse = document.querySelector('input[name="land_use"]')?.value || '';
    
    // Step 2: Shared Areas
    formData.sharedAreas = [];
    const sharedAreaInputs = document.querySelectorAll('input[name="shared_areas[]"]');
    sharedAreaInputs.forEach(input => {
        if (input.checked) {
            formData.sharedAreas.push(input.value);
        }
    });
    
    // Step 3: Documents
    formData.documents = [];
    const documentInputs = document.querySelectorAll('input[type="file"]');
    documentInputs.forEach(input => {
        if (input.files && input.files.length > 0) {
            formData.documents.push({
                name: input.name,
                fileName: input.files[0].name
            });
        }
    });
    
    // Step 4: Buyers
    formData.buyers = [];
    const buyerRows = document.querySelectorAll('.buyer-row');
    buyerRows.forEach((row, index) => {
        const buyer = {
            title: row.querySelector(`select[name="records[${index}][buyerTitle]"]`)?.value || '',
            firstName: row.querySelector(`input[name="records[${index}][firstName]"]`)?.value || '',
            middleName: row.querySelector(`input[name="records[${index}][middleName]"]`)?.value || '',
            surname: row.querySelector(`input[name="records[${index}][surname]"]`)?.value || '',
            unitNo: row.querySelector(`input[name="records[${index}][unit_no]"]`)?.value || '',
            unitMeasurement: row.querySelector(`input[name="records[${index}][unitMeasurement]"]`)?.value || ''
        };
        
        if (buyer.firstName || buyer.surname) {
            formData.buyers.push(buyer);
        }
    });
    
    return formData;
}

// Function to update applicant summary
function updateApplicantSummary(formData) {
    console.log('Updating applicant summary with data:', formData);
    
    // Update individual elements by ID
    const applicantTypeEl = document.getElementById('summary-applicant-type');
    const nameEl = document.getElementById('summary-name');
    const emailEl = document.getElementById('summary-email');
    const phoneEl = document.getElementById('summary-phone');
    
    // Build applicant name
    let applicantName = '';
    if (formData.applicantType === 'individual') {
        applicantName = [formData.applicantTitle, formData.firstName, formData.middleName, formData.surname]
            .filter(part => part && part.trim() !== '')
            .join(' ');
    } else if (formData.applicantType === 'corporate') {
        applicantName = formData.corporateName || '';
    }
    
    // Update elements if they exist
    if (applicantTypeEl) {
        applicantTypeEl.textContent = formData.applicantType || '-';
    }
    if (nameEl) {
        nameEl.textContent = applicantName || '-';
    }
    if (emailEl) {
        emailEl.textContent = formData.email || '-';
    }
    if (phoneEl) {
        phoneEl.textContent = formData.phoneNumber || '-';
    }
    
    // Update address fields
    updateAddressSummary(formData);
    
    // Update other fields
    updateUnitSummary(formData);
}

// Function to update address summary
function updateAddressSummary(formData) {
    const houseNoEl = document.getElementById('summary-house-no');
    const streetNameEl = document.getElementById('summary-street-name');
    const districtEl = document.getElementById('summary-district');
    const lgaEl = document.getElementById('summary-lga');
    const stateEl = document.getElementById('summary-state');
    const fullAddressEl = document.getElementById('summary-full-address');
    
    if (houseNoEl) houseNoEl.textContent = formData.addressHouseNo || '-';
    if (streetNameEl) streetNameEl.textContent = formData.ownerStreetName || '-';
    if (districtEl) districtEl.textContent = formData.ownerDistrict || '-';
    if (lgaEl) lgaEl.textContent = formData.ownerLga || '-';
    if (stateEl) stateEl.textContent = formData.ownerState || '-';
    
    if (fullAddressEl) {
        const fullAddress = [formData.addressHouseNo, formData.ownerStreetName, formData.ownerDistrict, formData.ownerLga, formData.ownerState]
            .filter(part => part && part.trim() !== '')
            .join(', ');
        fullAddressEl.textContent = fullAddress || '-';
    }
}

// Function to update unit summary
function updateUnitSummary(formData) {
    const residenceTypeEl = document.getElementById('summary-residence-type');
    const blocksEl = document.getElementById('summary-blocks');
    const sectionsEl = document.getElementById('summary-sections');
    const unitsEl = document.getElementById('summary-units');
    const fileNumberEl = document.getElementById('summary-file-number');
    
    if (residenceTypeEl) residenceTypeEl.textContent = formData.residenceType || '-';
    if (blocksEl) blocksEl.textContent = formData.blocksCount || '-';
    if (sectionsEl) sectionsEl.textContent = formData.sectionsCount || '-';
    if (unitsEl) unitsEl.textContent = formData.unitsCount || '-';
    if (fileNumberEl) {
        // Try to get the file number from the hidden input or generated field
        const npFileNoInput = document.querySelector('input[name="np_fileno"]');
        if (npFileNoInput) {
            fileNumberEl.textContent = npFileNoInput.value || '-';
        }
    }
}

// Function to update property summary
function updatePropertySummary(formData) {
    console.log('Updating property summary...');
    
    // Update property address fields
    const propertyHouseNoEl = document.getElementById('summary-property-house-no');
    const propertyPlotNoEl = document.getElementById('summary-property-plot-no');
    const propertyStreetEl = document.getElementById('summary-property-street-name');
    const propertyDistrictEl = document.getElementById('summary-property-district');
    const propertyLgaEl = document.getElementById('summary-property-lga');
    const propertyStateEl = document.getElementById('summary-property-state');
    const propertyFullAddressEl = document.getElementById('summary-property-full-address');
    const schemeNoEl = document.getElementById('summary-scheme-no');
    
    if (schemeNoEl) schemeNoEl.textContent = formData.schemeNo || '-';
    if (propertyHouseNoEl) propertyHouseNoEl.textContent = formData.propertyHouseNo || '-';
    if (propertyPlotNoEl) propertyPlotNoEl.textContent = formData.propertyPlotNo || '-';
    if (propertyStreetEl) propertyStreetEl.textContent = formData.propertyStreetName || '-';
    if (propertyDistrictEl) propertyDistrictEl.textContent = formData.propertyDistrict || '-';
    if (propertyLgaEl) propertyLgaEl.textContent = formData.propertyLga || '-';
    if (propertyStateEl) propertyStateEl.textContent = formData.propertyState || '-';
    
    if (propertyFullAddressEl) {
        const fullAddress = [formData.propertyHouseNo, formData.propertyPlotNo, formData.propertyStreetName, formData.propertyDistrict, formData.propertyLga, formData.propertyState]
            .filter(part => part && part.trim() !== '')
            .join(', ');
        propertyFullAddressEl.textContent = fullAddress || '-';
    }
    
    // Update ID type if element exists
    const idTypeEl = document.getElementById('summary-id-type');
    if (idTypeEl) {
        idTypeEl.textContent = formData.idType || '-';
    }
}

// Function to update documents summary
function updateDocumentsSummary(formData) {
    const summarySection = document.getElementById('summary-documents');
    if (!summarySection) {
        console.warn('Documents summary section not found');
        return;
    }
    
    console.log('Updating documents summary with:', formData.documents);
    
    if (formData.documents.length === 0) {
        summarySection.innerHTML = '<div class="text-gray-500 col-span-2">No documents uploaded</div>';
        return;
    }
    
    const documentsHtml = formData.documents.map(doc => 
        `<div class="flex items-center space-x-2 p-2 bg-gray-50 rounded">
            <i data-lucide="file-text" class="w-4 h-4 text-blue-500"></i>
            <span class="text-sm">${doc.fileName}</span>
        </div>`
    ).join('');
    
    summarySection.innerHTML = documentsHtml;
    
    // Re-initialize Lucide icons for the new elements
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Function to update buyers summary
function updateBuyersSummary(formData) {
    const summarySection = document.getElementById('buyers-summary');
    if (!summarySection) return;
    
    if (formData.buyers.length === 0) {
        summarySection.innerHTML = '<div class="text-gray-500">No buyers added</div>';
        return;
    }
    
    const buyersHtml = formData.buyers.map((buyer, index) => {
        const buyerName = [buyer.title, buyer.firstName, buyer.middleName, buyer.surname]
            .filter(part => part.trim() !== '')
            .join(' ');
        
        return `<div class="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">
            <span>${buyerName || 'Unnamed Buyer'}</span>
            <span class="text-sm text-gray-500">Unit: ${buyer.unitNo || 'N/A'}</span>
        </div>`;
    }).join('');
    
    summarySection.innerHTML = `
        <div class="space-y-1">
            <div class="font-medium text-sm text-gray-600 mb-2">Total Buyers: ${formData.buyers.length}</div>
            ${buyersHtml}
        </div>
    `;
}

// Function to update shared areas summary
function updateSharedAreasSummary(formData) {
    const summarySection = document.getElementById('shared-areas-summary');
    if (!summarySection) {
        console.warn('Shared areas summary section not found');
        return;
    }
    
    if (formData.sharedAreas.length === 0) {
        summarySection.innerHTML = '<div class="text-gray-500">No shared areas selected</div>';
        return;
    }
    
    const areasHtml = formData.sharedAreas.map(area => 
        `<div class="flex items-center space-x-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
            <span>${area}</span>
        </div>`
    ).join('');
    
    summarySection.innerHTML = `
        <div class="space-y-2">
            ${areasHtml}
        </div>
    `;
    
    // Re-initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Function to calculate and update fees
function updateFeesSummary(formData) {
    console.log('Updating fees summary...');
    
    // Get land use type to calculate fees
    const landUse = formData.landUse || 'Residential';
    const unitsCount = parseInt(formData.unitsCount) || 1;
    
    // Fee calculation based on land use and units
    let applicationFee = 0;
    let processingFee = 0;
    let sitePlanFee = 0;
    
    switch(landUse.toLowerCase()) {
        case 'commercial':
            applicationFee = 50000;
            processingFee = 25000;
            sitePlanFee = 15000;
            break;
        case 'industrial':
            applicationFee = 75000;
            processingFee = 35000;
            sitePlanFee = 20000;
            break;
        case 'residential':
        default:
            applicationFee = 30000;
            processingFee = 15000;
            sitePlanFee = 10000;
            break;
    }
    
    // Multiply by number of units
    applicationFee *= unitsCount;
    processingFee *= unitsCount;
    // Site plan fee is fixed regardless of units
    
    const totalFee = applicationFee + processingFee + sitePlanFee;
    
    // Update fee elements
    const appFeeEl = document.getElementById('summary-application-fee');
    const procFeeEl = document.getElementById('summary-processing-fee');
    const siteFeeEl = document.getElementById('summary-site-plan-fee');
    const totalFeeEl = document.getElementById('summary-total-fee');
    const receiptEl = document.getElementById('summary-receipt-number');
    const paymentDateEl = document.getElementById('summary-payment-date');
    
    if (appFeeEl) appFeeEl.textContent = '₦' + applicationFee.toLocaleString();
    if (procFeeEl) procFeeEl.textContent = '₦' + processingFee.toLocaleString();
    if (siteFeeEl) siteFeeEl.textContent = '₦' + sitePlanFee.toLocaleString();
    if (totalFeeEl) totalFeeEl.textContent = '₦' + totalFee.toLocaleString();
    
    // Generate receipt number and set payment date to today
    const receiptNumber = 'RCP-' + Date.now().toString().slice(-8);
    const paymentDate = new Date().toLocaleDateString('en-GB');
    const dateCaptured = new Date().toLocaleDateString('en-GB');
    
    if (receiptEl) receiptEl.textContent = receiptNumber;
    if (paymentDateEl) paymentDateEl.textContent = paymentDate;
    
    // Update date captured
    const dateCapturedEl = document.getElementById('summary-date-captured');
    if (dateCapturedEl) dateCapturedEl.textContent = dateCaptured;
    
    console.log('Fees calculated:', {
        applicationFee,
        processingFee,
        sitePlanFee,
        totalFee,
        receiptNumber,
        paymentDate
    });
}

// Make functions globally accessible
window.updateApplicationSummary = updateApplicationSummary;
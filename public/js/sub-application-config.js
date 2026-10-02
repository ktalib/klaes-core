/**
 * Sub Application Form Configuration and Initialization
 * Handles draft endpoints setup and basic form functionality
 */

/**
 * Global step navigation function
 */
function goToStep(step) {
    // Hide all form sections
    document.querySelectorAll('.form-section').forEach(section => {
        section.classList.remove('active-tab');
        section.style.display = 'none';
    });
    
    // Show the selected step
    const targetStep = document.getElementById(`step${step}`);
    if (targetStep) {
        targetStep.classList.add('active-tab');
        targetStep.style.display = 'block';
    }
    
    // Update step circles
    document.querySelectorAll('.step-circle').forEach((circle, index) => {
        circle.classList.remove('active-tab');
        circle.classList.add('inactive-tab');
        if (index + 1 === step) {
            circle.classList.remove('inactive-tab');
            circle.classList.add('active-tab');
        }
    });
    
    console.log(`[Sub Application] Navigated to step ${step}`);
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize configuration from server-side data
    if (typeof window.SUB_APPLICATION_DRAFT_BOOTSTRAP !== 'undefined' && 
        typeof window.SUB_APPLICATION_DRAFT_ENDPOINTS !== 'undefined') {
        
        console.log('[Sub Application] Configuration loaded successfully');
        console.log('[Sub Application] Draft endpoints available:', Object.keys(window.SUB_APPLICATION_DRAFT_ENDPOINTS));
        
        // Initialize any additional configuration-dependent functionality
        initializeFormConfiguration();
    } else {
        console.warn('[Sub Application] Configuration not found - some features may not work');
    }
});

/**
 * Initialize form configuration-dependent functionality
 */
function initializeFormConfiguration() {
    // Add any configuration-dependent initialization here
    
    // Example: Initialize step navigation if elements exist
    const stepCircles = document.querySelectorAll('.step-circle');
    if (stepCircles.length > 0) {
        console.log(`[Sub Application] Found ${stepCircles.length} step navigation elements`);
    }
    
    // Initialize Lucide icons if available
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        console.log('[Sub Application] Lucide icons initialized');
    }
    
    // Initialize unit selection functionality
    initializeBuyerUnitSelection();
}

/**
 * Initialize Buyer/Unit Selection functionality
 */
function initializeBuyerUnitSelection() {
    const buyerSelect = document.getElementById('buyerSelect');
    if (!buyerSelect) {
        console.log('[Sub Application] Buyer select not found - skipping unit selection initialization');
        return;
    }

    // Initialize Select2 for searchable dropdown
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#buyerSelect').select2({
            placeholder: 'Type to search buyer name or unit number...',
            allowClear: true,
            width: '100%'
        });

        // Handle buyer selection
        $('#buyerSelect').on('select2:select', function(e) {
            const selectedOption = e.params.data.element;
            if (selectedOption) {
                const buyerData = {
                    buyer_title: selectedOption.dataset.buyerTitle,
                    buyer_name: selectedOption.dataset.buyerName,
                    unit_no: selectedOption.dataset.unitNo,
                    land_use: selectedOption.dataset.landUse,
                    measurement: selectedOption.dataset.measurement,
                    buyer_id: selectedOption.dataset.buyerId
                };
                fillBuyerData(buyerData);
                showSelectedBuyerInfo(buyerData);
                document.getElementById('clearBuyerSelection').style.display = 'inline-block';
            }
        });
        
        // Handle buyer clear
        $('#buyerSelect').on('select2:clear', function(e) {
            clearBuyerData();
            hideSelectedBuyerInfo();
            document.getElementById('clearBuyerSelection').style.display = 'none';
        });
        
        // Clear button functionality
        const clearButton = document.getElementById('clearBuyerSelection');
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                $('#buyerSelect').val(null).trigger('change');
                clearBuyerData();
                hideSelectedBuyerInfo();
                this.style.display = 'none';
            });
        }

        console.log('[Sub Application] Buyer unit selection initialized with Select2');
    } else {
        console.warn('[Sub Application] Select2 not available - buyer selection will use basic dropdown');
    }
}

/**
 * Fill form with buyer data
 */
function fillBuyerData(buyer) {
    // Fill hidden inputs
    document.getElementById('selectedBuyerId').value = buyer.buyer_id || '';
    document.getElementById('selectedUnitNumber').value = buyer.unit_no || '';
    document.getElementById('selectedUnitSize').value = buyer.measurement || '';
    document.getElementById('selectedUnitLandUse').value = buyer.land_use || '';

    // Fill applicant type fields if the buyer data is available
    if (buyer.buyer_title && buyer.buyer_name) {
        // Set applicant type to individual
        const individualRadio = document.querySelector('input[name="applicant_type"][value="individual"]');
        if (individualRadio) {
            individualRadio.checked = true;
            // Trigger the change event to show individual fields
            if (typeof showIndividualFields === 'function') {
                showIndividualFields();
            }
        }

        // Fill title dropdown
        const titleSelect = document.getElementById('applicantTitleSelect');
        if (titleSelect) {
            titleSelect.value = buyer.buyer_title;
            document.getElementById('applicantTitle').value = buyer.buyer_title;
        }

        // Split buyer name into parts
        const nameParts = buyer.buyer_name.trim().split(' ');
        if (nameParts.length >= 2) {
            const firstName = nameParts[0];
            const surname = nameParts[nameParts.length - 1];
            const middleName = nameParts.slice(1, -1).join(' ');

            // Fill name fields
            const firstNameInput = document.getElementById('applicantName');
            const surnameInput = document.getElementById('applicantSurname');
            const middleNameInput = document.getElementById('applicantMiddleName');

            if (firstNameInput) firstNameInput.value = firstName.toUpperCase();
            if (surnameInput) surnameInput.value = surname.toUpperCase();
            if (middleNameInput && middleName) middleNameInput.value = middleName.toUpperCase();

            // Update name preview
            if (typeof updateApplicantNamePreview === 'function') {
                updateApplicantNamePreview();
            }
        }
    }

    // Fill unit details section
    const unitSizeField = document.getElementById('unitSize');
    const landUseDisplayField = document.getElementById('landUseDisplay');
    
    if (unitSizeField && buyer.measurement) {
        unitSizeField.value = buyer.measurement;
    }
    
    if (landUseDisplayField && buyer.land_use) {
        landUseDisplayField.value = buyer.land_use;
        
        // Show ownership type section for Residential or Mixed use
        const ownershipSection = document.getElementById('ownershipTypeSection');
        if (ownershipSection && (buyer.land_use === 'Residential' || buyer.land_use === 'Mixed Use')) {
            ownershipSection.classList.remove('hidden');
        } else if (ownershipSection) {
            ownershipSection.classList.add('hidden');
        }
    }

    console.log('[Sub Application] Buyer data filled:', buyer);
}

/**
 * Clear buyer data from form
 */
function clearBuyerData() {
    // Clear hidden inputs
    document.getElementById('selectedBuyerId').value = '';
    document.getElementById('selectedUnitNumber').value = '';
    document.getElementById('selectedUnitSize').value = '';
    document.getElementById('selectedUnitLandUse').value = '';

    // Clear applicant form fields
    const titleSelect = document.getElementById('applicantTitleSelect');
    const firstNameInput = document.getElementById('applicantName');
    const surnameInput = document.getElementById('applicantSurname');
    const middleNameInput = document.getElementById('applicantMiddleName');
    const namePreview = document.getElementById('applicantNamePreview');

    if (titleSelect) titleSelect.value = '';
    if (firstNameInput) firstNameInput.value = '';
    if (surnameInput) surnameInput.value = '';
    if (middleNameInput) middleNameInput.value = '';
    if (namePreview) namePreview.value = '';

    document.getElementById('applicantTitle').value = '';

    // Clear unit details section
    const unitSizeField = document.getElementById('unitSize');
    const landUseDisplayField = document.getElementById('landUseDisplay');
    const blockNumberField = document.getElementById('blockNumber');
    const floorNumberField = document.getElementById('floorNumber');
    const schemeNumberField = document.getElementById('schemeNumber');
    
    if (unitSizeField) unitSizeField.value = '';
    if (landUseDisplayField) landUseDisplayField.value = '';
    if (blockNumberField) blockNumberField.value = '';
    if (floorNumberField) floorNumberField.value = '';
    if (schemeNumberField) schemeNumberField.value = '';

    // Hide ownership type section
    const ownershipSection = document.getElementById('ownershipTypeSection');
    if (ownershipSection) {
        ownershipSection.classList.add('hidden');
    }

    console.log('[Sub Application] Buyer data cleared');
}

/**
 * Show selected buyer information
 */
function showSelectedBuyerInfo(buyer) {
    const infoContainer = document.getElementById('selectedUnitInfo');
    const buyerNameEl = document.getElementById('selectedBuyerName');
    const unitNoEl = document.getElementById('selectedUnitNo');
    const landUseEl = document.getElementById('selectedLandUse');
    const measurementEl = document.getElementById('selectedMeasurement');

    if (infoContainer && buyerNameEl && unitNoEl && landUseEl && measurementEl) {
        buyerNameEl.textContent = `${buyer.buyer_title} ${buyer.buyer_name}`;
        unitNoEl.textContent = buyer.unit_no || 'N/A';
        landUseEl.textContent = buyer.land_use || 'N/A';
        measurementEl.textContent = buyer.measurement ? `${buyer.measurement}m²` : 'N/A';
        
        infoContainer.classList.remove('hidden');
    }
}

/**
 * Hide selected buyer information
 */
function hideSelectedBuyerInfo() {
    const infoContainer = document.getElementById('selectedUnitInfo');
    if (infoContainer) {
        infoContainer.classList.add('hidden');
    }
}

/**
 * Shared Areas JavaScript Functions
 */

// Toggle other areas textarea visibility
function toggleOtherAreasTextarea() {
    const checkbox = document.getElementById('other_areas');
    const container = document.getElementById('other_areas_container');
    
    if (checkbox && container) {
        if (checkbox.checked) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
            const detail = document.getElementById('other_areas_detail');
            if (detail) detail.value = '';
        }
    }
}

// Check all shared areas
function checkAllSharedAreas() {
    const checkboxes = document.querySelectorAll('input[name="shared_areas[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
        if (checkbox.id === 'other_areas') {
            toggleOtherAreasTextarea();
        }
    });
}

// Uncheck all shared areas
function uncheckAllSharedAreas() {
    const checkboxes = document.querySelectorAll('input[name="shared_areas[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    toggleOtherAreasTextarea(); // Hide other areas textarea
}

/**
 * Step Navigation Functions
 */
function goToStep(stepNumber) {
    // Get current active step
    const currentActiveStep = document.querySelector('.form-section.active-tab');
    let currentStepNumber = 1;
    if (currentActiveStep) {
        const stepId = currentActiveStep.id;
        currentStepNumber = parseInt(stepId.replace('step', ''));
    }
    
    if (currentStepNumber === stepNumber) return;

    // Hide all steps
    document.querySelectorAll('.form-section').forEach(step => step.classList.remove('active-tab'));
    
    // Show target step
    const targetStep = document.getElementById(`step${stepNumber}`);
    if (targetStep) {
        targetStep.classList.add('active-tab');
    }
    
    // Update step circles
    updateStepCircles(stepNumber);
    updateStepText(stepNumber);
    
    // Update application summary when reaching step 4
    if (stepNumber === 4 && typeof updateApplicationSummary === 'function') {
        updateApplicationSummary();
    }
    
    console.log(`[Sub Application] Moved to step ${stepNumber}`);
}

function updateStepCircles(activeStep) {
    document.querySelectorAll('.step-circle').forEach((circle, index) => {
        const stepNumber = index + 1;
        circle.classList.remove('active-tab', 'inactive-tab');
        if (stepNumber === activeStep) {
            circle.classList.add('active-tab');
        } else {
            circle.classList.add('inactive-tab');
        }
    });
}

function updateStepText(stepNumber) {
    const stepTextElements = document.querySelectorAll('[class*="ml-4"]:contains("Step")');
    stepTextElements.forEach(element => {
        if (element.textContent.includes('Step')) {
            element.textContent = `Step ${stepNumber}`;
        }
    });
}

/**
 * Document Upload Functions
 */
function updateFileName(input, labelId) {
    const fileName = input.files[0]?.name;
    if (fileName) {
        document.getElementById(input.id + '_name').textContent = fileName;
        document.getElementById(labelId).innerHTML = '<span>Change Document</span>';
        
        // Trigger the summary update whenever a document is uploaded
        if (typeof updateApplicationSummary === 'function') {
            updateApplicationSummary();
        }
    }
}

/**
 * Summary Functions
 */
function updateApplicationSummary() {
    // Update applicant information
    const applicantType = document.querySelector('input[name="applicant_type"]:checked')?.value || 'individual';
    const applicantTypeDisplay = document.getElementById('applicantTypeDisplay');
    if (applicantTypeDisplay) {
        applicantTypeDisplay.textContent = applicantType.charAt(0).toUpperCase() + applicantType.slice(1);
    }
    
    // Show/hide rows based on applicant type
    const individualRows = document.querySelectorAll('#individual-name-row');
    const corporateRows = document.querySelectorAll('#corporate-name-row');
    
    if (applicantType === 'corporate') {
        individualRows.forEach(row => row.style.display = 'none');
        corporateRows.forEach(row => row.style.display = 'table-row');
        
        const corporateName = document.querySelector('input[name="corporate_name"]')?.value || '';
        const corporateNameDisplay = document.getElementById('corporateNameDisplay');
        if (corporateNameDisplay) corporateNameDisplay.textContent = corporateName;
    } else {
        individualRows.forEach(row => row.style.display = 'table-row');
        corporateRows.forEach(row => row.style.display = 'none');
        
        const firstName = document.querySelector('input[name="first_name"]')?.value || '';
        const lastName = document.querySelector('input[name="last_name"]')?.value || '';
        const applicantNameDisplay = document.getElementById('applicantNameDisplay');
        if (applicantNameDisplay) applicantNameDisplay.textContent = `${firstName} ${lastName}`.trim();
    }
    
    // Update other fields
    const email = document.querySelector('input[name="email"]')?.value || '';
    const emailDisplay = document.getElementById('emailDisplay');
    if (emailDisplay) emailDisplay.textContent = email;
    
    const phone = document.querySelector('input[name="phone"]')?.value || '';
    const phoneDisplay = document.getElementById('phoneDisplay');
    if (phoneDisplay) phoneDisplay.textContent = phone;
    
    // Update shared areas
    const sharedAreas = Array.from(document.querySelectorAll('input[name="shared_areas[]"]:checked'))
        .map(checkbox => checkbox.nextElementSibling?.textContent || checkbox.value)
        .join(', ');
    const summarySharedAreas = document.getElementById('summary-shared-areas');
    if (summarySharedAreas) {
        summarySharedAreas.innerHTML = sharedAreas || '<span class="text-gray-500">No shared areas selected</span>';
    }
    
    // Update documents
    const documentSummary = document.getElementById('summary-documents');
    if (documentSummary) {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        const uploadedDocs = [];
        
        fileInputs.forEach(input => {
            if (input.files && input.files.length > 0) {
                uploadedDocs.push(`<div class="flex items-center text-sm"><i data-lucide="check-circle" class="w-4 h-4 text-green-500 mr-2"></i>${input.files[0].name}</div>`);
            }
        });
        
        documentSummary.innerHTML = uploadedDocs.length > 0 ? uploadedDocs.join('') : '<div class="text-gray-500">No documents uploaded</div>';
    }
    
    // Refresh Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Initialize summary page functionality
function initializeSummaryPage() {
    // Enable submit button when confirmation is checked
    const confirmCheckbox = document.getElementById('confirmSubmission');
    const submitButton = document.getElementById('submitApplication');
    
    if (confirmCheckbox && submitButton) {
        confirmCheckbox.addEventListener('change', function() {
            submitButton.disabled = !this.checked;
        });
    }
}

// Initialize summary page when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeSummaryPage();
});

// Make functions globally available but avoid clobbering existing implementations
if (typeof window.goToStep === 'function') {
    console.warn('window.goToStep already defined - preserving existing implementation from another script.');
} else {
    window.goToStep = goToStep;
}

window.toggleOtherAreasTextarea = toggleOtherAreasTextarea;
window.checkAllSharedAreas = checkAllSharedAreas;
window.uncheckAllSharedAreas = uncheckAllSharedAreas;
window.updateFileName = updateFileName;

if (typeof window.updateApplicationSummary === 'function') {
    console.warn('window.updateApplicationSummary already defined - preserving existing implementation.');
} else {
    window.updateApplicationSummary = updateApplicationSummary;
}
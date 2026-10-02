/**
 * Primary Application Form - Main Initialization
 * Initializes the form when DOM is loaded
 */

// Initialize Lucide icons function
function initializeLucideIcons() {
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        try {
            lucide.createIcons();
            console.log('✅ Lucide icons initialized');
        } catch (error) {
            console.warn('⚠️ Could not initialize Lucide icons:', error);
        }
    } else {
        console.warn('⚠️ Lucide not found, skipping icon initialization');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Primary Application Form - DOM loaded, initializing...');
    
    // Initialize Lucide icons
    initializeLucideIcons();
    
    // Initialize form handlers
    initializeFormHandlers();
    
    // Initialize address handlers
    initializeAddressHandlers();
    
    // Initialize file input handlers
    initializeFileInputHandlers();
    
    // Initialize buyers list if on step 4
    initializeBuyersList();
    
    // Initialize first step properly
    setTimeout(() => {
        console.log('🎯 Initializing step 1...');
        goToStep(1);
    }, 100);
    
    console.log('Primary Application Form initialized successfully');
});

// Function to initialize form handlers
function initializeFormHandlers() {
    // Form submission handler - support both possible form IDs
    const mainForm = document.getElementById('primaryForm') || document.getElementById('primaryApplicationForm');
    if (mainForm) {
        mainForm.addEventListener('submit', function(e) {
            console.log('Form submitted');
            if (typeof showLoading === 'function') showLoading();
        });
    }
    
    // Step navigation buttons
    initializeStepButtons();
    
    // Currency input handlers - skip payment date / receipt fields
    const currencyInputs = document.querySelectorAll('input[type="text"][placeholder*="₦"], input[name*="amount"], input[name*="fee"]');
    currencyInputs.forEach(input => {
        const fieldName = (input.getAttribute('name') || '').toLowerCase();
        if (
            !fieldName ||
            fieldName.includes('payment_date') ||
            fieldName.includes('receipt') ||
            input.classList.contains('initial-bill-date')
        ) {
            return;
        }

        input.addEventListener('input', function() {
            formatCurrency(this);
        });
    });
    
    // Uppercase input handlers
    const uppercaseInputs = document.querySelectorAll('input.uppercase, input[oninput*="toUpperCase"]');
    uppercaseInputs.forEach(input => {
        input.addEventListener('input', function() {
            toUpperCase(this);
        });
    });
}

// Function to initialize step navigation buttons
function initializeStepButtons() {
    // Next step buttons
    const nextButtons = document.querySelectorAll('[id^="nextStep"]');
    console.log('🔲 Found next buttons:', nextButtons.length);
    nextButtons.forEach((button, index) => {
        console.log(`   Next button ${index + 1}: ${button.id}`);
        button.addEventListener('click', function(e) {
            console.log(`🖱️ Next button clicked: ${button.id}`);
            e.preventDefault();
            goToNextStep();
        });
    });
    
    // Previous step buttons
    const backButtons = document.querySelectorAll('[id^="backStep"]');
    console.log('🔙 Found back buttons:', backButtons.length);
    backButtons.forEach((button, index) => {
        console.log(`   Back button ${index + 1}: ${button.id}`);
        button.addEventListener('click', function(e) {
            console.log(`🖱️ Back button clicked: ${button.id}`);
            e.preventDefault();
            goToPreviousStep();
        });
    });
    
    // Step circle navigation
    const stepCircles = document.querySelectorAll('.step-circle');
    console.log('🔵 Found step circles:', stepCircles.length);
    stepCircles.forEach((circle, index) => {
        circle.addEventListener('click', function(e) {
            console.log(`🖱️ Step circle clicked: ${index + 1}`);
            e.preventDefault();
            goToStep(index + 1);
        });
    });
}

// Function to initialize address handlers
function initializeAddressHandlers() {
    // Contact address fields
    const contactAddressFields = [
        'ownerHouseNo', 'ownerStreetName', 'ownerDistrict', 'ownerLga', 'ownerState'
    ];
    
    contactAddressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', updateAddressDisplay);
        }
    });
    
    // Property address fields - use IDs present in the DOM (camelCase)
    const propertyAddressFields = [
        'schemeNumber', 'propertyHouseNo', 'propertyPlotNo', 'propertyStreetName',
        'propertyDistrict', 'propertyLga', 'propertyState'
    ];

    propertyAddressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', updatePropertyAddressDisplay);
        }
    });
}

// Function to initialize file input handlers
function initializeFileInputHandlers() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            handleFileInputChange(this);
        });
    });
}

// Function to initialize buyers list functionality
function initializeBuyersList() {
    // Check if we're on the buyers list step
    const buyersContainer = document.getElementById('buyers-container');
    if (buyersContainer) {
        // Initialize remove button visibility
        const removeButtons = document.querySelectorAll('.remove-buyer');
        removeButtons.forEach(button => {
            button.style.display = removeButtons.length > 1 ? 'flex' : 'none';
        });
        
        // Initialize CSV file input handler
        const csvFileInput = document.getElementById('csvFileInput');
        if (csvFileInput) {
            csvFileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const fileName = this.files[0].name;
                    console.log('CSV file selected:', fileName);
                }
            });
        }
    }
}

// Function to handle applicant type changes
function handleApplicantTypeChange(selectElement) {
    console.log('Applicant type changed to:', selectElement.value);
    
    // Debug: Check if applicant sections exist
    console.log('Debug: Checking applicant sections...');
    console.log('individualFields exists:', !!document.getElementById('individualFields'));
    console.log('corporateFields exists:', !!document.getElementById('corporateFields'));
    console.log('multipleOwnersFields exists:', !!document.getElementById('multipleOwnersFields'));
    
    // Debug: Check if functions exist
    console.log('showIndividualFields available:', typeof showIndividualFields);
    console.log('showCorporateFields available:', typeof showCorporateFields);
    console.log('showMultipleOwnersFields available:', typeof showMultipleOwnersFields);
    
    // Set the hidden applicant type field first
    if (typeof setApplicantType === 'function') {
        setApplicantType(selectElement.value);
    } else {
        const applicantTypeInput = document.getElementById('applicantType');
        if (applicantTypeInput) {
            applicantTypeInput.value = selectElement.value;
        }
    }
    
    // Use the existing functions from applicant.blade.php if available
    switch (selectElement.value) {
        case 'individual':
            if (typeof showIndividualFields === 'function') {
                showIndividualFields();
            } else {
                // Fallback to direct DOM manipulation
                showApplicantSection('individualFields');
            }
            console.log('Showing individual fields');
            break;
        case 'corporate':
            if (typeof showCorporateFields === 'function') {
                showCorporateFields();
            } else {
                // Fallback to direct DOM manipulation
                showApplicantSection('corporateFields');
            }
            console.log('Showing corporate fields');
            break;
        case 'multiple':
            if (typeof showMultipleOwnersFields === 'function') {
                showMultipleOwnersFields();
            } else {
                // Fallback to direct DOM manipulation
                showApplicantSection('multipleOwnersFields');
            }
            console.log('Showing multiple owners fields');
            break;
        default:
            console.log('No valid applicant type selected');
    }
}

// Helper function for direct DOM manipulation (fallback)
function showApplicantSection(activeSection) {
    const sections = ['individualFields', 'corporateFields', 'multipleOwnersFields'];
    
    console.log('showApplicantSection called with:', activeSection);
    
    sections.forEach(sectionId => {
        const element = document.getElementById(sectionId);
        if (element) {
            if (sectionId === activeSection) {
                element.style.setProperty('display', 'block', 'important');
                console.log(`Showing ${sectionId} with display: block !important`);
            } else {
                element.style.setProperty('display', 'none', 'important');
                console.log(`Hiding ${sectionId} with display: none !important`);
            }
        } else {
            console.error(`Element ${sectionId} not found!`);
        }
    });
}

// Make functions globally accessible
window.handleApplicantTypeChange = handleApplicantTypeChange;

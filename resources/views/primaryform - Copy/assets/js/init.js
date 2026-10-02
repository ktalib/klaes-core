/**
 * Primary Application Form - Main Initialization
 * Initializes the form when DOM is loaded
 */

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
    // Form submission handler
    const mainForm = document.getElementById('primaryForm');
    if (mainForm) {
        mainForm.addEventListener('submit', function(e) {
            console.log('Form submitted');
            showLoading();
        });
    }
    
    // Step navigation buttons
    initializeStepButtons();
    
    // Currency input handlers
    const currencyInputs = document.querySelectorAll('input[type="text"][placeholder*="₦"], input[name*="amount"], input[name*="fee"]');
    currencyInputs.forEach(input => {
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
    
    // Property address fields
    const propertyAddressFields = [
        'property_house_no', 'property_plot_no', 'property_street_name', 
        'property_district', 'property_lga', 'property_state'
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
    const individualFields = document.getElementById('individual-fields');
    const corporateFields = document.getElementById('corporate-fields');
    const multipleOwnersFields = document.getElementById('multiple-owners-fields');
    
    if (!individualFields || !corporateFields) return;
    
    // Hide all field groups first
    individualFields.style.display = 'none';
    corporateFields.style.display = 'none';
    if (multipleOwnersFields) {
        multipleOwnersFields.style.display = 'none';
    }
    
    // Show relevant fields based on selection
    switch (selectElement.value) {
        case 'individual':
            individualFields.style.display = 'block';
            break;
        case 'corporate':
            corporateFields.style.display = 'block';
            break;
        case 'multiple':
            if (multipleOwnersFields) {
                multipleOwnersFields.style.display = 'block';
            }
            break;
    }
}

// Make functions globally accessible
window.handleApplicantTypeChange = handleApplicantTypeChange;
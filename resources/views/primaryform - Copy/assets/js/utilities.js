/**
 * Primary Application Form - Utility Functions
 * Common utility functions for form handling
 */

// Initialize Lucide icons
function initializeLucideIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Global function to update address display
function updateAddressDisplay() {
    console.log('🏠 updateAddressDisplay() called');
    
    const ownerHouseNo = document.getElementById('ownerHouseNo');
    const ownerStreetName = document.getElementById('ownerStreetName');
    const ownerDistrict = document.getElementById('ownerDistrict');
    const ownerLga = document.getElementById('ownerLga');
    const ownerState = document.getElementById('ownerState');
    
    const fullContactAddress = document.getElementById('fullContactAddress');
    const contactAddressDisplay = document.getElementById('contactAddressDisplay');
    
    console.log('📍 Elements found:', {
        ownerHouseNo: !!ownerHouseNo,
        ownerStreetName: !!ownerStreetName,
        ownerDistrict: !!ownerDistrict,
        ownerLga: !!ownerLga,
        ownerState: !!ownerState,
        fullContactAddress: !!fullContactAddress,
        contactAddressDisplay: !!contactAddressDisplay
    });
    
    if (!fullContactAddress || !contactAddressDisplay) {
        console.warn('❌ Missing required elements for address display');
        return;
    }
    
    const houseNo = ownerHouseNo?.value || '';
    const streetName = ownerStreetName?.value || '';
    const district = ownerDistrict?.value || '';
    const lga = ownerLga?.value || '';
    const state = ownerState?.value || '';
    
    console.log('📝 Address components:', {
        houseNo, streetName, district, lga, state
    });
    
    const fullAddress = [houseNo, streetName, district, lga, state]
        .filter(part => part && part.trim() !== '')
        .join(', ');
    
    console.log('✅ New address value:', fullAddress);
    
    // Update both elements
    if (fullContactAddress) {
        fullContactAddress.textContent = fullAddress || 'Enter address details above';
    }
    
    if (contactAddressDisplay) {
        contactAddressDisplay.value = fullAddress || '';
    }
}

// Function to update property address display
function updatePropertyAddressDisplay() {
    console.log('🏢 updatePropertyAddressDisplay() called');
    
    const propertyHouseNo = document.getElementById('propertyHouseNo');
    const propertyPlotNo = document.getElementById('propertyPlotNo');
    const propertyStreetName = document.getElementById('propertyStreetName');
    const propertyDistrict = document.getElementById('propertyDistrict');
    const propertyLga = document.getElementById('propertyLga');
    const propertyState = document.getElementById('propertyState');
    
    const propertyAddressDisplay = document.getElementById('propertyAddressDisplay');
    const fullPropertyAddressSpan = document.getElementById('fullPropertyAddress');
    
    console.log('🏗️ Property elements found:', {
        propertyHouseNo: !!propertyHouseNo,
        propertyPlotNo: !!propertyPlotNo,
        propertyStreetName: !!propertyStreetName,
        propertyDistrict: !!propertyDistrict,
        propertyLga: !!propertyLga,
        propertyState: !!propertyState,
        propertyAddressDisplay: !!propertyAddressDisplay,
        fullPropertyAddressSpan: !!fullPropertyAddressSpan
    });
    
    if (!propertyAddressDisplay) {
        console.warn('❌ Missing propertyAddressDisplay element');
        return;
    }
    
    const houseNo = propertyHouseNo?.value || '';
    const plotNo = propertyPlotNo?.value || '';
    const streetName = propertyStreetName?.value || '';
    const district = propertyDistrict?.value || '';
    const lga = propertyLga?.value || '';
    const state = propertyState?.value || '';
    
    console.log('🏗️ Property address components:', {
        houseNo, plotNo, streetName, district, lga, state
    });
    
    const fullPropertyAddress = [houseNo, plotNo, streetName, district, lga, state]
        .filter(part => part && part.trim() !== '')
        .join(', ');
    
    console.log('✅ Property address updated:', fullPropertyAddress);
    
    // Update both the hidden input and the visible span
    propertyAddressDisplay.value = fullPropertyAddress || '';
    
    if (fullPropertyAddressSpan) {
        fullPropertyAddressSpan.textContent = fullPropertyAddress || 'Enter property details above';
    } else {
        console.warn('❌ fullPropertyAddress span element not found');
    }
}

// Function to show loading overlay
function showLoading() {
    const loadingOverlay = document.querySelector('.loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'flex';
    }
}

// Function to hide loading overlay
function hideLoading() {
    const loadingOverlay = document.querySelector('.loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

// Function to format currency input
function formatCurrency(input) {
    let value = input.value.replace(/[^\d]/g, '');
    if (value) {
        value = parseInt(value).toLocaleString();
        input.value = '₦' + value;
    }
}

// Function to handle file input changes
function handleFileInputChange(input) {
    const fileName = input.files[0]?.name || '';
    const fileInfo = input.nextElementSibling;
    if (fileInfo) {
        fileInfo.textContent = fileName || 'No file selected';
    }
}

// Function to convert text to uppercase
function toUpperCase(input) {
    input.value = input.value.toUpperCase();
}

// Function to validate email format
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Function to validate phone number format
function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[0-9]{10,15}$/;
    return phoneRegex.test(phone.replace(/\s+/g, ''));
}

// Function to show success message
function showSuccessMessage(message, duration = 3000) {
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: message,
        timer: duration,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// Function to show error message
function showErrorMessage(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message,
        confirmButtonText: 'OK',
        confirmButtonColor: '#dc3545'
    });
}

// Function to show info message
function showInfoMessage(message, duration = 3000) {
    Swal.fire({
        icon: 'info',
        title: 'Information',
        text: message,
        timer: duration,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// Initialize address handlers when DOM is ready
function initializeAddressHandlers() {
    console.log('🎯 Initializing address handlers...');
    
    // Set up event listeners for address fields
    const ownerAddressInputs = [
        'ownerHouseNo', 'ownerStreetName', 'ownerDistrict', 'ownerLga', 'ownerState'
    ];
    
    const propertyAddressInputs = [
        'propertyHouseNo', 'propertyPlotNo', 'propertyStreetName', 'propertyDistrict', 'propertyLga', 'propertyState'
    ];
    
    // Add event listeners for owner address fields
    ownerAddressInputs.forEach(inputId => {
        const element = document.getElementById(inputId);
        if (element) {
            element.addEventListener('input', updateAddressDisplay);
            element.addEventListener('change', updateAddressDisplay);
            console.log(`✅ Added listeners to ${inputId}`);
        } else {
            console.warn(`❌ Element not found: ${inputId}`);
        }
    });
    
    // Add event listeners for property address fields
    propertyAddressInputs.forEach(inputId => {
        const element = document.getElementById(inputId);
        if (element) {
            element.addEventListener('input', updatePropertyAddressDisplay);
            element.addEventListener('change', updatePropertyAddressDisplay);
            console.log(`✅ Added listeners to ${inputId}`);
        } else {
            console.warn(`❌ Element not found: ${inputId}`);
        }
    });
    
    // Initialize address displays
    updateAddressDisplay();
    updatePropertyAddressDisplay();
}

// Call initialization when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initializeAddressHandlers, 100);
});

// Make functions globally accessible
window.updateAddressDisplay = updateAddressDisplay;
window.updatePropertyAddressDisplay = updatePropertyAddressDisplay;
window.initializeAddressHandlers = initializeAddressHandlers;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.formatCurrency = formatCurrency;
window.handleFileInputChange = handleFileInputChange;
window.toUpperCase = toUpperCase;
window.isValidEmail = isValidEmail;
window.isValidPhone = isValidPhone;
window.showSuccessMessage = showSuccessMessage;
window.showErrorMessage = showErrorMessage;
window.showInfoMessage = showInfoMessage;
window.initializeLucideIcons = initializeLucideIcons;
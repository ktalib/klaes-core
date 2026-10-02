/**
 * Commission New ST - Page Initialization and Debugging
 * Handles page setup, CSRF token, and debugging functionality
 */

// Initialize page when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Commission New ST page loaded');
    
    // Add CSRF token meta tag if not present
    if (!document.querySelector('meta[name="csrf-token"]')) {
        const csrfMeta = document.createElement('meta');
        csrfMeta.name = 'csrf-token';
        csrfMeta.content = document.querySelector('input[name="_token"]')?.value || '';
        document.head.appendChild(csrfMeta);
        console.log('CSRF token meta tag added');
    }
    
    // Debug: Check if serial number display element exists
    const serialDisplay = document.getElementById('serial-number-display');
    console.log('Serial display element found:', serialDisplay);
    
    // Debug: Check if land use checkboxes exist
    const landUseCheckboxes = document.querySelectorAll('input[name="selectedLandUse"]');
    console.log('Land use checkboxes found:', landUseCheckboxes.length);
    
    // Debug: Test serial number update function (only in development)
    if (serialDisplay && window.location.hostname === 'localhost') {
        console.log('Development mode: Testing serial number update...');
        // Test updating the serial number directly
        setTimeout(() => {
            const originalValue = serialDisplay.textContent;
            serialDisplay.textContent = 'TEST-123';
            console.log('Serial display updated to: TEST-123');
            
            // Reset back after 2 seconds
            setTimeout(() => {
                serialDisplay.textContent = originalValue;
                console.log('Serial display reset to original value');
            }, 2000);
        }, 1000);
    }
    
    // Debug: Check if file modal integration functions are available
    setTimeout(() => {
        console.log('Available functions:');
        console.log('- handleLandUseChange:', typeof window.handleLandUseChange);
        console.log('- updateNPFNDisplay:', typeof window.updateNPFNDisplay);
        console.log('- commissionFileNumber:', typeof window.commissionFileNumber);
        console.log('- openFileNumberModal:', typeof window.openFileNumberModal);
        console.log('- updateSerialNumber:', typeof window.updateSerialNumber);
    }, 500);
    
    console.log('Page initialization complete');
});

// Override handleLandUseChange to add debugging
function setupLandUseChangeOverride() {
    // Store the original function
    window.originalHandleLandUseChange = window.handleLandUseChange;
    
    // Override with debugging wrapper
    window.handleLandUseChange = function(selectedCheckbox) {
        console.log('🚀 handleLandUseChange called with:', selectedCheckbox.value);
        
        // Call original function if it exists
        if (typeof window.originalHandleLandUseChange === 'function') {
            window.originalHandleLandUseChange(selectedCheckbox);
        } else {
            console.error('❌ Original handleLandUseChange function not found!');
            
            // Fallback: simple serial update test
            const serialDisplay = document.getElementById('serial-number-display');
            if (serialDisplay) {
                const testSerial = Math.floor(Math.random() * 100) + 1;
                serialDisplay.textContent = testSerial;
                console.log('✅ Fallback: Updated serial to', testSerial);
            }
        }
    };
}

// Setup the override after a short delay to ensure other scripts have loaded
setTimeout(setupLandUseChangeOverride, 100);

// Utility function to get CSRF token
function getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
           document.querySelector('input[name="_token"]')?.value || '';
}

// Make utility functions globally available
window.getCSRFToken = getCSRFToken;
window.setupLandUseChangeOverride = setupLandUseChangeOverride;
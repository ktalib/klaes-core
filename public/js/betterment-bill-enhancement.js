// Enhanced functionality for betterment bill form population
$(document).ready(function() {
    // Enhanced function to load saved bills with better timing
    window.loadSavedBillsEnhanced = function() {
        if (typeof currentApplication !== 'undefined' && currentApplication && currentApplication.fileId) {
            console.log('Loading saved bills for application:', currentApplication.fileId);
            
            // Wait for DOM elements to be ready
            setTimeout(function() {
                if (typeof checkForSavedBalanceBill === 'function') {
                    checkForSavedBalanceBill();
                }
                if (typeof checkForSavedBettermentBill === 'function') {
                    checkForSavedBettermentBill();
                }
                if (typeof fetchApplicationDetails === 'function') {
                    fetchApplicationDetails(currentApplication.fileId, currentApplication.fileType);
                }
            }, 500);
        }
    };

    // Listen for when forms are loaded and populate them
    $(document).on('click', '[data-tab="generate"]', function() {
        setTimeout(function() {
            loadSavedBillsEnhanced();
        }, 300);
    });

    $(document).on('click', '[data-tab="calculate"]', function() {
        setTimeout(function() {
            loadSavedBillsEnhanced();
        }, 300);
    });

    // Listen for when the file is selected
    $(document).on('change', '#filenoSelect', function() {
        setTimeout(function() {
            loadSavedBillsEnhanced();
        }, 1000);
    });

    // Listen for when application is selected
    $(document).on('click', '.file-item', function() {
        setTimeout(function() {
            loadSavedBillsEnhanced();
        }, 1000);
    });

    // Auto-trigger when page loads
    setTimeout(function() {
        loadSavedBillsEnhanced();
    }, 2000);
});
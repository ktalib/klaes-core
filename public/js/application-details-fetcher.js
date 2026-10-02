// Function to fetch application details for betterment bill form
function fetchApplicationDetails(fileId, fileType) {
    if (!fileId || !fileType) {
        return;
    }
    
    fetch(`${window.location.origin}/application-details/${fileId}/${fileType}`)
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
}

// Auto-call fetchApplicationDetails when loadBillsForFile is called
$(document).ready(function() {
    // Override the loadBillsForFile function to include fetchApplicationDetails
    var originalLoadBillsForFile = window.loadBillsForFile;
    
    window.loadBillsForFile = function(fileId, fileType, fileno, owner) {
        // Call the original function
        if (originalLoadBillsForFile) {
            originalLoadBillsForFile(fileId, fileType, fileno, owner);
        }
        
        // Fetch application details for betterment bill form
        setTimeout(function() {
            fetchApplicationDetails(fileId, fileType);
        }, 500);
    };
});
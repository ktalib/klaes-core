// Fix for broken controller endpoints
// This script overrides the broken fetch calls to use working direct PHP endpoints

(function() {
    // Store original fetch
    const originalFetch = window.fetch;
    
    // Override fetch to redirect broken endpoints
    window.fetch = function(url, options) {
        // Convert relative URLs to absolute for easier matching
        const fullUrl = new URL(url, window.location.origin).href;
        
        // Redirect broken endpoints to working ones
        if (fullUrl.includes('/recertification/data')) {
            console.log('Redirecting /recertification/data to /get_recertification_data.php');
            return originalFetch('/get_recertification_data.php', options);
        }
        
        if (fullUrl.includes('/recertification/verification-data')) {
            console.log('Redirecting /recertification/verification-data to /get_recertification_data.php');
            return originalFetch('/get_recertification_data.php', options);
        }
        
        if (fullUrl.includes('/recertification/vetting-data')) {
            console.log('Redirecting /recertification/vetting-data to /get_vetting_data.php');
            return originalFetch('/get_vetting_data.php', options);
        }
        
        if (fullUrl.includes('/recertification/certification-data')) {
            console.log('Redirecting /recertification/certification-data to /get_certification_data.php');
            return originalFetch('/get_certification_data.php', options);
        }
        
        // For all other URLs, use original fetch
        return originalFetch(url, options);
    };
    
    console.log('Endpoint fix loaded - broken controller endpoints will be redirected to working PHP files');
})();
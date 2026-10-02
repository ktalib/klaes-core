{{-- Primary Application Form JavaScript Assets --}}

{{-- Core JavaScript Files --}}
<script src="{{ asset('resources/views/primaryform/assets/js/utilities.js') }}"></script>
<script src="{{ asset('resources/views/primaryform/assets/js/navigation.js') }}"></script>
<script src="{{ asset('resources/views/primaryform/assets/js/buyers.js') }}"></script>
<script src="{{ asset('resources/views/primaryform/assets/js/summary.js') }}"></script>
<script src="{{ asset('resources/views/primaryform/assets/js/init.js') }}"></script>

{{-- Inline initialization script --}}
<script>
// Initialize icons on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Initialize buyers list functionality if on step 4
    const buyersContainer = document.getElementById('buyers-container');
    if (buyersContainer) {
        // Update remove button visibility for initial state
        const removeButtons = document.querySelectorAll('.remove-buyer');
        removeButtons.forEach(button => {
            button.style.display = removeButtons.length > 1 ? 'flex' : 'none';
        });
    }
    
    // Set up form submission handling
    const form = document.getElementById('primaryApplicationForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Show loading indicator
            showLoading();
            
            // Optional: Add any final form processing here
            console.log('Form being submitted...');
        });
    }
});

// Global utility functions for backward compatibility
window.updateAddressDisplay = function() {
    if (window.updateAddressDisplay && typeof window.updateAddressDisplay === 'function') {
        window.updateAddressDisplay();
    }
};

window.updatePropertyAddressDisplay = function() {
    if (window.updatePropertyAddressDisplay && typeof window.updatePropertyAddressDisplay === 'function') {
        window.updatePropertyAddressDisplay();
    }
};
</script>
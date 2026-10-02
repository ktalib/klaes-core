{{-- Primary Application Form JavaScript Assets --}}

{{-- Global File Number Modal Component --}}
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>

{{-- Core JavaScript Files --}}
<script src="{{ asset('js/primaryform/utilities.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/primaryform/states-lga.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/primaryform/navigation.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/primaryform/buyers.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/primaryform/summary.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/primaryform/init.js') }}?v={{ time() }}"></script>

{{-- Inline initialization script --}}
<script>
// File Number Modal Functions
function openFileNumberModal() {
    console.log('Opening file number modal...');
    
    // Check if GlobalFileNoModal is available
    if (typeof GlobalFileNoModal === 'undefined') {
        console.error('GlobalFileNoModal is not defined. Make sure global-fileno-modal.js is loaded.');
        alert('Error: File selection component is not available. Please refresh the page and try again.');
        return;
    }
    
    try {
        // Use GlobalFileNoModal as an object, not a constructor
        const success = GlobalFileNoModal.open({
            callback: applySelectedFile,
            debug: true
        });
        
        if (success !== false) {
            console.log('Modal opened successfully');
        } else {
            console.error('Failed to open modal - modal not found in DOM');
            alert('Error: File selection modal not found. Please refresh the page and try again.');
        }
    } catch (error) {
        console.error('Error opening file number modal:', error);
        alert('Error opening file selection dialog. Please try again.');
    }
}

// Apply selected file from modal
function applySelectedFile(result) {
    console.log('Applying selected file:', result);
    
    if (!result || !result.fileNumber) {
        console.error('No file number provided in result:', result);
        alert('No file number was selected. Please try again.');
        return;
    }

    const fileNumber = result.fileNumber;
    const fileSystem = result.system || 'Unknown';
    const fileTab = result.tab || 'unknown';

    console.log('Applying file number:', fileNumber, 'from system:', fileSystem);

    // Update the input field with the selected file number
    const appliedFileNumberInput = document.getElementById('applied-file-number');
    
    if (appliedFileNumberInput) {
        console.log('Setting input value to:', fileNumber);
        appliedFileNumberInput.value = fileNumber;
        
        // Update styling to show it's been filled
        appliedFileNumberInput.classList.remove('bg-gray-50', 'text-gray-900');
        appliedFileNumberInput.classList.add('bg-green-50', 'text-green-900', 'border-green-300');
        
        // Trigger change event
        const changeEvent = new Event('change', { bubbles: true });
        appliedFileNumberInput.dispatchEvent(changeEvent);
        
        console.log('Input value after setting:', appliedFileNumberInput.value);
    } else {
        console.error('Could not find input element with ID: applied-file-number');
        alert('Error: Could not find the file number input field.');
        return;
    }

    // Update file details section if it exists
    const fileDetailsSection = document.getElementById('file-number-details');
    if (fileDetailsSection) {
        fileDetailsSection.classList.remove('hidden');
        
        // Update detail fields with available data
        const elements = {
            'file-type': fileSystem,
            'file-status': 'Active',
            'file-location': 'From ' + fileSystem + ' system',
            'selected-file-display': fileNumber
        };
        
        Object.keys(elements).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = elements[id];
            }
        });
    }

    // Update hidden fields for form submission
    const hiddenFields = {
        'selected-file-id': fileNumber,
        'selected-file-type': fileSystem,
        'selected-file-data': JSON.stringify(result)
    };
    
    Object.keys(hiddenFields).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.value = hiddenFields[id];
        }
    });

    // Show success feedback
    showSuccessMessage('File number applied successfully: ' + fileNumber);
    
    console.log('applySelectedFile completed successfully');
}

// Clear the selected file
function clearSelectedFile() {
    const appliedFileNumberInput = document.getElementById('applied-file-number');
    const fileDetailsSection = document.getElementById('file-number-details');
    
    if (appliedFileNumberInput) {
        appliedFileNumberInput.value = '';
        appliedFileNumberInput.placeholder = "Click 'Select File Number' to choose a file...";
        appliedFileNumberInput.classList.remove('bg-green-50', 'text-green-900', 'border-green-300');
        appliedFileNumberInput.classList.add('bg-gray-50', 'text-gray-900');
    }

    if (fileDetailsSection) {
        fileDetailsSection.classList.add('hidden');
    }

    // Clear hidden fields
    const hiddenFileId = document.getElementById('selected-file-id');
    const hiddenFileType = document.getElementById('selected-file-type');
    const hiddenFileData = document.getElementById('selected-file-data');
    
    if (hiddenFileId) hiddenFileId.value = '';
    if (hiddenFileType) hiddenFileType.value = '';
    if (hiddenFileData) hiddenFileData.value = '';

    showSuccessMessage('File selection cleared.');
}

// Show success message
function showSuccessMessage(message) {
    // You can integrate this with your existing notification system
    // For now, using a simple alert
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        // Fallback to browser alert if SweetAlert is not available
        alert(message);
    }
}

// Initialize icons on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Add debug info for file number modal functions
    console.log('Primary Form JavaScript loaded');
    console.log('jQuery available:', typeof $ !== 'undefined');
    console.log('GlobalFileNoModal available:', typeof GlobalFileNoModal !== 'undefined');
    console.log('Lucide available:', typeof lucide !== 'undefined');
    console.log('SweetAlert available:', typeof Swal !== 'undefined');
    console.log('openFileNumberModal function available:', typeof openFileNumberModal === 'function');
    
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
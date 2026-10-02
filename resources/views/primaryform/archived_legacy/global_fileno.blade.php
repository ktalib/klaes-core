<div class="bg-green-50 border border-green-100 rounded-md p-4 mb-6 items-center">
    <div class="flex items-center mb-2">
        <i data-lucide="file" class="w-5 h-5 mr-2 text-green-600"></i>
        <span class="font-medium">File Number Information</span>
    </div>
    <p class="text-sm text-gray-600 mb-4">Select file number type and enter the details</p>
    
    <!-- File Number Selection Interface -->
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
            <label class="block text-sm font-medium text-gray-700">
                <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                Applied File Number
            </label>
            <button type="button" 
                    id="open-fileno-modal-btn" 
                    class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"
                    onclick="openFileNumberModal()">
                <i data-lucide="search" class="w-4 h-4 mr-1.5"></i>
                Select File Number
            </button>
        </div>
        
        <!-- Applied File Number Display -->
        <div class="relative">
            <input type="text" 
                   id="applied-file-number" 
                   name="applied_file_number"
                   class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 font-mono text-lg cursor-pointer"
                   placeholder="Click 'Select File Number' to choose a file..."
                   readonly
                   onclick="openFileNumberModal()"
                   title="Click to select file number">
            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                <i data-lucide="file-search" class="w-5 h-5 text-gray-400"></i>
            </div>
        </div>
        
        <!-- File Number Details (shown when file is selected) -->
        <div id="file-number-details" class="mt-4 hidden">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div>
                        <span class="text-gray-600">Type:</span>
                        <span id="file-type" class="font-medium ml-1">-</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Status:</span>
                        <span id="file-status" class="font-medium ml-1">-</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Property Location:</span>
                        <span id="file-location" class="font-medium ml-1">-</span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <div class="text-sm">
                        <span class="text-gray-600">Selected File:</span>
                        <span id="selected-file-display" class="font-mono font-medium text-blue-700 ml-1">-</span>
                    </div>
                    <button type="button" 
                            class="text-xs text-blue-600 hover:text-blue-800 underline"
                            onclick="clearSelectedFile()">
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Helper Text -->
        <div class="mt-3 p-3 bg-gray-50 rounded-lg">
            <div class="flex items-start">
                <i data-lucide="info" class="w-4 h-4 text-blue-500 mr-2 mt-0.5"></i>
                <div class="text-sm text-gray-700">
                    <p class="font-medium mb-1">How to select a file number:</p>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>Click "Select File Number" to open the file selection modal</li>
                        <li>Browse through MLS, KANGIS, or New KANGIS files</li>
                        <li>Use filters and search to find the appropriate file</li>
                        <li>Click "Apply" on your chosen file to use it for this application</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hidden fields to store file data -->
    <input type="hidden" id="selected-file-id" name="selected_file_id" value="">
    <input type="hidden" id="selected-file-type" name="selected_file_type" value="">
    <input type="hidden" id="selected-file-data" name="selected_file_data" value="">
</div>

<script>
// Open the Global File Number Modal
function openFileNumberModal() {
    console.log('Attempting to open GlobalFileNoModal...');
    
    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded');
        alert('jQuery is required but not loaded. Please refresh the page.');
        return;
    }
    
    // Check if the GlobalFileNoModal is available
    if (typeof GlobalFileNoModal === 'undefined') {
        console.error('GlobalFileNoModal not found');
        alert('File Number Modal is not available. Please refresh the page and try again.');
        return;
    }

    try {
        console.log('Opening GlobalFileNoModal...');
        
        // Define the callback function
        const onApplyCallback = function(fileData) {
            console.log('GlobalFileNoModal callback triggered with data:', fileData);
            
            // Transform the data to match our expected format
            const transformedData = {
                file_number: fileData.fileNumber || fileData.file_number || '',
                fileno: fileData.fileNumber || fileData.fileno || '',
                id: fileData.id || '',
                file_type: fileData.system || fileData.tab || 'Unknown',
                type: fileData.system || fileData.tab || 'Unknown',
                status: 'Active',
                property_location: 'Not specified',
                system: fileData.system || fileData.tab || ''
            };
            
            console.log('Transformed data for applySelectedFile:', transformedData);
            
            // Apply the selected file
            applySelectedFile(transformedData);
        };
        
        console.log('Setting up callback function:', onApplyCallback);
        
        // Open the modal with callback configuration
        GlobalFileNoModal.open({
            callback: onApplyCallback, // Main callback property
            onApply: onApplyCallback,  // Fallback
            onSelect: onApplyCallback, // Another fallback
            title: 'Select File Number for Primary Application',
            showFilters: true,
            allowMultiple: false
        });
        
        console.log('GlobalFileNoModal.open() called successfully');
    } catch (error) {
        console.error('Error opening GlobalFileNoModal:', error);
        alert('Error opening file selection modal: ' + error.message);
    }
}

// Apply the selected file to the form
function applySelectedFile(fileData) {
    console.log('applySelectedFile called with data:', fileData);
    
    if (!fileData) {
        console.error('No file data received');
        alert('No file data received. Please try selecting a file again.');
        return;
    }

    // Get the file number from the data
    const fileNumber = fileData.file_number || fileData.fileno || fileData.fileNumber || '';
    
    if (!fileNumber) {
        console.error('No file number found in data:', fileData);
        alert('File number not found in the selected file data. Please try again.');
        return;
    }

    console.log('Applying file number:', fileNumber);

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
        
        // Update detail fields with safe fallbacks
        const elements = {
            'file-type': fileData.file_type || fileData.type || fileData.system || 'Unknown',
            'file-status': fileData.status || 'Active',
            'file-location': fileData.property_location || fileData.location || 'Not specified',
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
        'selected-file-id': fileData.id || '',
        'selected-file-type': fileData.file_type || fileData.type || fileData.system || '',
        'selected-file-data': JSON.stringify(fileData)
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
    document.getElementById('selected-file-id').value = '';
    document.getElementById('selected-file-type').value = '';
    document.getElementById('selected-file-data').value = '';

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

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Global File Number component loaded');
    
    // Initialize icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Add debug info
    console.log('jQuery available:', typeof $ !== 'undefined');
    console.log('GlobalFileNoModal available:', typeof GlobalFileNoModal !== 'undefined');
    console.log('Lucide available:', typeof lucide !== 'undefined');
    console.log('SweetAlert available:', typeof Swal !== 'undefined');
});
</script>
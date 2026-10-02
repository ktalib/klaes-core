/**
 * Manual File Number Modal JavaScript
 * Handles the manual file number entry modal functionality
 */

// Initialize manual file number modal when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeManualFileNumberModal();
});

function initializeManualFileNumberModal() {
    console.log('Initializing manual file number modal...');
    
    const modal = document.getElementById('manual-file-number-modal');
    const closeBtn = document.getElementById('close-manual-modal');
    const cancelBtn = document.getElementById('cancel-manual-modal');
    const applyBtn = document.getElementById('apply-manual-file-number');
    
    if (!modal) {
        console.error('Manual file number modal not found');
        return;
    }
    
    // Initialize mode switching
    initializeModeSwitching();
    
    // Close modal event listeners
    if (closeBtn) {
        closeBtn.addEventListener('click', hideManualFileNumberModal);
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', hideManualFileNumberModal);
    }
    
    // Apply file number event listener
    if (applyBtn) {
        applyBtn.addEventListener('click', applyManualFileNumber);
    }
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            hideManualFileNumberModal();
        }
    });
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            hideManualFileNumberModal();
        }
    });
    
    console.log('Manual file number modal initialized');
}

function initializeModeSwitching() {
    console.log('Initializing mode switching...');
    
    const selectorModeBtn = document.getElementById('selector-mode-btn');
    const advancedModeBtn = document.getElementById('advanced-mode-btn');
    
    const fileNumberSelector = document.getElementById('file-number-selector');
    const fileNumberManual = document.getElementById('file-number-manual');
    
    if (!selectorModeBtn || !advancedModeBtn) {
        console.error('Mode buttons not found');
        return;
    }
    
    // Search & Select mode
    selectorModeBtn.addEventListener('click', function() {
        setMode('selector');
        if (fileNumberSelector) fileNumberSelector.classList.remove('hidden');
        if (fileNumberManual) fileNumberManual.classList.add('hidden');
    });
    
    // Advanced Manual mode (opens modal)
    advancedModeBtn.addEventListener('click', function() {
        setMode('advanced');
        showManualFileNumberModal();
    });
    
    console.log('Mode switching initialized');
}

function setMode(mode) {
    console.log('Setting mode to:', mode);
    
    const selectorModeBtn = document.getElementById('selector-mode-btn');
    const advancedModeBtn = document.getElementById('advanced-mode-btn');
    
    // Reset all buttons
    [selectorModeBtn, advancedModeBtn].forEach(btn => {
        if (btn) {
            btn.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
            btn.classList.add('text-gray-600');
        }
    });
    
    // Activate selected button
    let activeBtn;
    if (mode === 'selector') activeBtn = selectorModeBtn;
    else if (mode === 'advanced') activeBtn = advancedModeBtn;
    
    if (activeBtn) {
        activeBtn.classList.remove('text-gray-600');
        activeBtn.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
    }
}

function showManualFileNumberModal() {
    console.log('showManualFileNumberModal called');
    
    const modal = document.getElementById('manual-file-number-modal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        
        // Focus on the modal for accessibility
        modal.focus();
        
        console.log('Manual file number modal shown');
    } else {
        console.error('Manual file number modal not found');
    }
}

function hideManualFileNumberModal() {
    console.log('hideManualFileNumberModal called');
    
    const modal = document.getElementById('manual-file-number-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
        
        console.log('Manual file number modal hidden');
    }
}

function applyManualFileNumber() {
    console.log('=== applyManualFileNumber called ===');
    
    // Get the Alpine.js data from the modal
    const modal = document.getElementById('manual-file-number-modal');
    if (!modal) {
        console.error('Modal not found');
        return;
    }
    
    let alpineData = null;
    let fileNumber = '';
    let fileType = '';
    
    // Method 1: Try to get data from Alpine.js directly
    console.log('Trying Method 1: Alpine.js data access...');
    try {
        if (window.Alpine && modal._x_dataStack && modal._x_dataStack[0]) {
            alpineData = modal._x_dataStack[0];
            console.log('✅ Alpine data found via _x_dataStack');
            fileNumber = alpineData.getCurrentFileNumber();
            fileType = alpineData.getFileNumberType();
            console.log('Alpine data result:', { fileNumber, fileType });
        }
    } catch (e) {
        console.log('❌ Method 1 failed:', e);
    }
    
    // Method 2: Try Alpine's $data method
    if (!fileNumber && window.Alpine) {
        console.log('Trying Method 2: Alpine $data...');
        try {
            alpineData = window.Alpine.$data(modal);
            if (alpineData) {
                console.log('✅ Alpine data found via $data');
                fileNumber = alpineData.getCurrentFileNumber();
                fileType = alpineData.getFileNumberType();
                console.log('Alpine $data result:', { fileNumber, fileType });
            }
        } catch (e) {
            console.log('❌ Method 2 failed:', e);
        }
    }
    
    // Method 3: DOM fallback method
    if (!fileNumber) {
        console.log('Trying Method 3: DOM fallback...');
        alpineData = getDOMBasedFileNumber();
        if (alpineData) {
            fileNumber = alpineData.getCurrentFileNumber();
            fileType = alpineData.getFileNumberType();
            console.log('DOM fallback result:', { fileNumber, fileType });
        }
    }
    
    // Method 4: Simple DOM inspection (last resort)
    if (!fileNumber) {
        console.log('Trying Method 4: Simple DOM inspection...');
        const result = getSimpleDOMFileNumber();
        if (result) {
            fileNumber = result.fileNumber;
            fileType = result.fileType;
            console.log('Simple DOM result:', { fileNumber, fileType });
        }
    }
    
    console.log('Final result:', { fileNumber, fileType });

    // For manual file number creation ensure we remove any leading zeros
    // from the serial portion so manual entries are preserved as typed.
    try {
        const cleaned = removeLeadingZerosFromFileNumber(fileNumber);
        if (cleaned && cleaned !== fileNumber) {
            console.log('Stripped leading zeros from manual file number:', fileNumber, '->', cleaned);
            fileNumber = cleaned;
        }
    } catch (e) {
        console.warn('Failed to remove leading zeros from manual file number:', e);
    }

    if (!fileNumber || fileNumber.trim() === '') {
        console.warn('File number not generated - validation failed');
        // Show error message
        if (typeof showToast === 'function') {
            showToast('error', 'Validation Error', 'Please enter all required fields to generate a file number.');
        } else {
            alert('Please enter all required fields to generate a file number.');
        }
        return;
    }
    
    console.log('✅ Applying file number:', fileNumber, 'Type:', fileType);
    
    // Update the file number selector
    if (typeof selectFileNumber === 'function') {
        selectFileNumber(fileNumber, fileType, 'Active', null);
        console.log('✅ File number applied via selectFileNumber function');
    } else {
        // Fallback: Update manually
        updateFileNumberDisplay(fileNumber, fileType);
        console.log('✅ File number applied via manual update');
    }
    
    // Hide the modal
    hideManualFileNumberModal();
    
    // Show success message
    if (typeof showToast === 'function') {
        showToast('success', 'File Number Applied', `File number ${fileNumber} has been successfully applied.`);
    } else {
        console.log('SUCCESS: File number applied:', fileNumber);
        alert(`File number ${fileNumber} has been successfully applied.`);
    }
}

function getSimpleDOMFileNumber() {
    console.log('getSimpleDOMFileNumber: Inspecting all form inputs...');
    
    const modal = document.getElementById('manual-file-number-modal');
    if (!modal) return null;
    
    // Get all inputs and selects in the modal
    const inputs = modal.querySelectorAll('input, select');
    const values = {};
    
    inputs.forEach(input => {
        if (input.value && input.value.trim()) {
            const name = input.getAttribute('x-model') || input.name || input.id;
            values[name] = input.value.trim();
            console.log(`Found value: ${name} = ${input.value}`);
        }
    });
    
    console.log('All form values:', values);
    
    // Determine which tab is likely active based on filled values
    let fileNumber = '';
    let fileType = '';
    
    // Check for MLSF values
    if (values.mlsPrefix && values.mlsYear && values.mlsSerial) {
        fileNumber = `${values.mlsPrefix}-${values.mlsYear}-${values.mlsSerial}`;
        fileType = 'MLSF File';
        if (values.mlsType === 'temporary') fileNumber += '(T)';
    } 
    // Check for KANGIS values
    else if (values.kangisPrefix && values.kangisNumber) {
        fileNumber = `${values.kangisPrefix} ${values.kangisNumber}`;
        fileType = 'KANGIS File';
    }
    // Check for New KANGIS values
    else if (values.newkangisPrefix && values.newkangisNumber) {
        fileNumber = `${values.newkangisPrefix}${values.newkangisNumber}`;
        fileType = 'New KANGIS File';
    }
    // Check for other MLSF types
    else if (values.mlsMiddlePrefix && values.mlsMiscSerial) {
        fileNumber = `MISC-${values.mlsMiddlePrefix}-${values.mlsMiscSerial}`;
        fileType = 'MLSF File';
    }
    else if (values.mlsYear && values.mlsSpecialSerial && values.mlsType === 'sit') {
        fileNumber = `SIT-${values.mlsYear}-${values.mlsSpecialSerial}`;
        fileType = 'MLSF File';
    }
    else if (values.mlsSpecialSerial && values.mlsType === 'sltr') {
        fileNumber = `SLTR-${values.mlsSpecialSerial}`;
        fileType = 'MLSF File';
    }
    else if (values.mlsOldSerial) {
        fileNumber = `KN ${values.mlsOldSerial}`;
        fileType = 'MLSF File';
    }
    
    console.log('Simple DOM file number result:', { fileNumber, fileType });
    
    return fileNumber ? { fileNumber, fileType } : null;
}

function getDOMBasedFileNumber() {
    console.log('Getting file number data from DOM');
    
    const modal = document.getElementById('manual-file-number-modal');
    if (!modal) {
        console.error('Modal not found');
        return null;
    }
    
    // Find which tab is active by checking the Alpine.js tab variable
    // Look for tab buttons to determine which is active
    const mlsBtn = modal.querySelector('button[\\@click*="tab = \'mls\'"]');
    const kangisBtn = modal.querySelector('button[\\@click*="tab = \'kangis\'"]');
    const newkangisBtn = modal.querySelector('button[\\@click*="tab = \'newkangis\'"]');
    
    let activeTab = 'mls'; // default
    
    // Check which button has the active styling
    if (mlsBtn && mlsBtn.classList.contains('bg-white')) {
        activeTab = 'mls';
    } else if (kangisBtn && kangisBtn.classList.contains('bg-white')) {
        activeTab = 'kangis';
    } else if (newkangisBtn && newkangisBtn.classList.contains('bg-white')) {
        activeTab = 'newkangis';
    }
    
    // Also check which tab content is visible
    const mlsTab = modal.querySelector('[x-show*="tab === \'mls\'"]');
    const kangisTab = modal.querySelector('[x-show*="tab === \'kangis\'"]');
    const newkangisTab = modal.querySelector('[x-show*="tab === \'newkangis\'"]');
    
    if (mlsTab && window.getComputedStyle(mlsTab).display !== 'none') {
        activeTab = 'mls';
    } else if (kangisTab && window.getComputedStyle(kangisTab).display !== 'none') {
        activeTab = 'kangis';  
    } else if (newkangisTab && window.getComputedStyle(newkangisTab).display !== 'none') {
        activeTab = 'newkangis';
    }
    
    console.log('Active tab detected:', activeTab);
    
    let fileNumber = '';
    let fileType = '';
    
    if (activeTab === 'mls') {
        // Get MLS data from DOM - look within the MLS tab content
        const mlsContainer = modal.querySelector('[x-show*="tab === \'mls\'"]');
        if (mlsContainer) {
            const typeSelect = mlsContainer.querySelector('select[x-model="mlsType"]');
            const prefixSelect = mlsContainer.querySelector('select[x-model="mlsPrefix"]');
            const yearInput = mlsContainer.querySelector('input[x-model="mlsYear"]');
            const serialInput = mlsContainer.querySelector('input[x-model="mlsSerial"]');
            const miscSerialInput = mlsContainer.querySelector('input[x-model="mlsMiscSerial"]');
            const middlePrefixInput = mlsContainer.querySelector('input[x-model="mlsMiddlePrefix"]');
            const specialSerialInput = mlsContainer.querySelector('input[x-model="mlsSpecialSerial"]');
            const oldSerialInput = mlsContainer.querySelector('input[x-model="mlsOldSerial"]');
            const existingSelect = mlsContainer.querySelector('select[x-model="mlsExistingSelected"]');
            
            console.log('MLS form elements found:', {
                typeSelect: !!typeSelect,
                prefixSelect: !!prefixSelect,
                yearInput: !!yearInput,
                serialInput: !!serialInput
            });
            
            if (typeSelect) {
                const type = typeSelect.value;
                console.log('MLS type:', type);
                
                if (type === 'regular' || type === 'temporary') {
                    const prefix = prefixSelect?.value || '';
                    const year = yearInput?.value || '';
                    const serial = serialInput?.value || '';
                    
                    console.log('Regular/Temporary MLS values:', { prefix, year, serial });
                    
                    if (prefix && year && serial) {
                        fileNumber = `${prefix}-${year}-${serial}`;
                        if (type === 'temporary') fileNumber += '(T)';
                    }
                } else if (type === 'extension') {
                    const existing = existingSelect?.value || '';
                    if (existing) {
                        fileNumber = existing + ' AND EXTENSION';
                    }
                } else if (type === 'miscellaneous') {
                    const middlePrefix = middlePrefixInput?.value || '';
                    const miscSerial = miscSerialInput?.value || '';
                    if (middlePrefix && miscSerial) {
                        fileNumber = `MISC-${middlePrefix}-${miscSerial}`;
                    }
                } else if (type === 'sit') {
                    const year = yearInput?.value || '';
                    const specialSerial = specialSerialInput?.value || '';
                    if (year && specialSerial) {
                        fileNumber = `SIT-${year}-${specialSerial}`;
                    }
                } else if (type === 'sltr') {
                    const specialSerial = specialSerialInput?.value || '';
                    if (specialSerial) {
                        fileNumber = `SLTR-${specialSerial}`;
                    }
                } else if (type === 'old_mls') {
                    const oldSerial = oldSerialInput?.value || '';
                    if (oldSerial) {
                        fileNumber = `KN ${oldSerial}`;
                    }
                }
            }
        }
        fileType = 'MLSF File';
        
    } else if (activeTab === 'kangis') {
        // Get KANGIS data from DOM
        const kangisContainer = modal.querySelector('[x-show*="tab === \'kangis\'"]');
        if (kangisContainer) {
            const prefixSelect = kangisContainer.querySelector('select[x-model="kangisPrefix"]');
            const numberInput = kangisContainer.querySelector('input[x-model="kangisNumber"]');
            
            console.log('KANGIS form elements found:', {
                prefixSelect: !!prefixSelect,
                numberInput: !!numberInput
            });
            
            if (prefixSelect && numberInput) {
                const prefix = prefixSelect.value;
                const number = numberInput.value;
                
                console.log('KANGIS values:', { prefix, number });
                
                if (prefix && number) {
                    fileNumber = `${prefix} ${number}`;
                }
            }
        }
        fileType = 'KANGIS File';
        
    } else if (activeTab === 'newkangis') {
        // Get New KANGIS data from DOM
        const newkangisContainer = modal.querySelector('[x-show*="tab === \'newkangis\'"]');
        if (newkangisContainer) {
            const prefixSelect = newkangisContainer.querySelector('select[x-model="newkangisPrefix"]');
            const numberInput = newkangisContainer.querySelector('input[x-model="newkangisNumber"]');
            
            console.log('New KANGIS form elements found:', {
                prefixSelect: !!prefixSelect,
                numberInput: !!numberInput
            });
            
            if (prefixSelect && numberInput) {
                const prefix = prefixSelect.value;
                const number = numberInput.value;
                
                console.log('New KANGIS values:', { prefix, number });
                
                if (prefix && number) {
                    fileNumber = `${prefix}${number}`;
                }
            }
        }
        fileType = 'New KANGIS File';
    }
    
    console.log('DOM-based file number result:', { fileNumber, fileType });
    
    return {
        fileNumber: fileNumber,
        fileType: fileType,
        getCurrentFileNumber: () => fileNumber,
        getFileNumberType: () => fileType
    };
}

function updateFileNumberDisplay(fileNumber, fileType) {
    console.log('updateFileNumberDisplay called:', fileNumber, fileType);
    
    // Update hidden input
    const hiddenInput = document.getElementById('file_number');
    if (hiddenInput) {
        hiddenInput.value = fileNumber;
        console.log('Updated hidden input with:', fileNumber);
    }

    // Update trigger button text
    const triggerValue = document.getElementById('file-number-value');
    if (triggerValue) {
        triggerValue.textContent = fileNumber;
        triggerValue.classList.remove('text-gray-500');
        triggerValue.classList.add('text-gray-900');
        console.log('Updated trigger button text');
    }

    // Update display text in selector area
    const displayText = document.getElementById('selected-file-info');
    if (displayText) {
        displayText.innerHTML = `
            <div class="flex items-center justify-between">
                <span id="selected-file-details">
                    <i class="fa-solid fa-file-check mr-1"></i>
                    Selected: <span id="selected-file-number">${fileNumber}</span>
                </span>
                <button type="button" id="change-file-number" class="text-blue-600 hover:text-blue-800 underline">
                    Change
                </button>
            </div>
            <div class="text-xs text-green-600 mt-1">${fileType}</div>
            <div class="text-xs text-gray-500">Manually entered</div>
        `;
        displayText.classList.remove('hidden');
        console.log('Updated display text');
        
        // Re-attach change button event listener
        const changeBtn = displayText.querySelector('#change-file-number');
        if (changeBtn) {
            changeBtn.addEventListener('click', function() {
                clearFileNumberSelection();
            });
        }
    }

    // Update Current Selection Preview
    const previewContainer = document.getElementById('current-selection-preview');
    const previewFileNumber = document.getElementById('preview-file-number');
    const previewFileType = document.getElementById('preview-file-type');
    
    if (previewContainer && previewFileNumber && previewFileType) {
        previewFileNumber.textContent = fileNumber;
        previewFileType.textContent = fileType;
        previewContainer.classList.remove('hidden');
        console.log('Updated current selection preview');
    }
    
    console.log('File number display updated successfully');
}

function clearFileNumberSelection() {
    console.log('clearFileNumberSelection called');
    
    // Clear hidden input
    const hiddenInput = document.getElementById('file_number');
    if (hiddenInput) {
        hiddenInput.value = '';
    }

    // Reset trigger button text
    const triggerValue = document.getElementById('file-number-value');
    if (triggerValue) {
        triggerValue.textContent = 'Search and select file number...';
        triggerValue.classList.remove('text-gray-900');
        triggerValue.classList.add('text-gray-500');
    }

    // Hide display text
    const displayText = document.getElementById('selected-file-info');
    if (displayText) {
        displayText.classList.add('hidden');
    }

    // Hide current selection preview
    const previewContainer = document.getElementById('current-selection-preview');
    if (previewContainer) {
        previewContainer.classList.add('hidden');
    }
    
    // Reset global variable
    if (typeof selectedFileNumber !== 'undefined') {
        selectedFileNumber = null;
    }
    
    console.log('File number selection cleared');
}

/**
 * Remove leading zeros from the serial number part of a file number
 * @param {string} fileNumber - The file number to clean
 * @returns {string} - The cleaned file number without leading zeros
 */
function removeLeadingZerosFromFileNumber(fileNumber) {
    if (!fileNumber) return fileNumber;
    
    // Handle different file number patterns
    // Pattern: CON-RES-2025-0003 -> CON-RES-2025-3
    // Pattern: RES-2025-0456 -> RES-2025-456
    // Pattern: COM-2016-0208 -> COM-2016-208
    // Pattern: INS-2025-0055 -> INS-2025-55
    
    // Split by dashes and process the last part (serial number)
    const parts = fileNumber.split('-');
    if (parts.length >= 2) {
        // Get the last part (serial number)
        const lastPart = parts[parts.length - 1];
        
        // Remove leading zeros but keep at least one digit
        const cleanedLastPart = lastPart.replace(/^0+/, '') || '0';
        
        // Reconstruct the file number
        parts[parts.length - 1] = cleanedLastPart;
        return parts.join('-');
    }
    
    return fileNumber;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add slight delay to ensure other components are initialized
    setTimeout(initializeManualFileNumberModal, 500);
});

// Export functions for global access
window.showManualFileNumberModal = showManualFileNumberModal;
window.hideManualFileNumberModal = hideManualFileNumberModal;
window.applyManualFileNumber = applyManualFileNumber;
window.updateFileNumberDisplay = updateFileNumberDisplay;
window.clearFileNumberSelection = clearFileNumberSelection;
window.initializeModeSwitching = initializeModeSwitching;
window.setMode = setMode;
window.getDOMBasedFileNumber = getDOMBasedFileNumber;
window.getSimpleDOMFileNumber = getSimpleDOMFileNumber;

/**
 * File Number Modal Integration for Commission New ST
 * Handles file number modal functionality and NPFN generation
 */

const primaryTrackingState = {
    requestId: 0,
};

function setPrimaryTrackingDisplay(value) {
    const display = document.getElementById('primary-tracking-display');
    if (!display) {
        return;
    }

    display.textContent = value;
}

function setPrimaryTrackingStatus(message, tone = 'muted') {
    const status = document.getElementById('primary-tracking-status');
    if (!status) {
        return;
    }

    status.textContent = message || '';

    let color = '#6b7280';
    if (tone === 'success') {
        color = '#047857';
    } else if (tone === 'error') {
        color = '#b91c1c';
    }

    status.style.color = color;
}

function resetPrimaryTracking() {
    primaryTrackingState.requestId += 1;

    const input = document.getElementById('primary-tracking-id');
    if (input) {
        input.value = '';
    }

    setPrimaryTrackingDisplay('Awaiting selection', 'muted');
    setPrimaryTrackingStatus('Select an existing file to load tracking ID.', 'muted');
}

function initializeTrackingDisplay() {
    const input = document.getElementById('primary-tracking-id');
    const existing = input && input.value ? input.value.trim() : '';

    if (existing) {
        applyPrimaryTrackingId(existing, 'Tracking ID ready.', {
            tone: 'success',
            statusTone: 'muted'
        });
    } else {
        resetPrimaryTracking();
    }
}

function applyPrimaryTrackingId(trackingId, statusMessage, options = {}) {
    const {
        statusTone = 'success',
        displayWhenMissing = 'Tracking ID not found',
        statusToneWhenMissing = 'error'
    } = options;

    const input = document.getElementById('primary-tracking-id');
    if (input) {
        input.value = trackingId ? String(trackingId).trim() : '';
    }

    if (trackingId) {
        setPrimaryTrackingDisplay(trackingId);
        setPrimaryTrackingStatus(statusMessage || 'Tracking ID linked to selection.', statusTone);
    } else {
        setPrimaryTrackingDisplay(displayWhenMissing);
        setPrimaryTrackingStatus(statusMessage || 'Tracking ID must come from the selected record before commissioning.', statusToneWhenMissing);
    }
}

function loadPrimaryTrackingId(fileNumber) {
    const normalized = (fileNumber || '').toString().trim();
    if (!normalized) {
        resetPrimaryTracking();
        return;
    }

    const input = document.getElementById('primary-tracking-id');
    if (input) {
        input.value = '';
    }

    primaryTrackingState.requestId += 1;
    const currentRequest = primaryTrackingState.requestId;

    setPrimaryTrackingDisplay('Searching...', 'muted');
    setPrimaryTrackingStatus('Checking grouping records...', 'muted');

    // Helper: fallback — look up tracking_id directly from file_indexings
    function fallbackToIndexing() {
        if (currentRequest !== primaryTrackingState.requestId) return;

        setPrimaryTrackingStatus('Checking file indexing records...', 'muted');

        fetch(`/api/file-indexings/lookup-by-number?file_number=${encodeURIComponent(normalized)}`)
            .then(r => r.json().catch(() => ({})))
            .then(payload => {
                if (currentRequest !== primaryTrackingState.requestId) return;

                const trackingId = (payload?.data?.tracking_id || '').toString().trim();
                if (trackingId) {
                    applyPrimaryTrackingId(trackingId, 'Linked to file indexing record.', {
                        statusTone: 'success'
                    });
                } else {
                    applyPrimaryTrackingId('', 'No tracking ID found for this file number.', {
                        displayWhenMissing: 'Tracking ID not found',
                        statusToneWhenMissing: 'error'
                    });
                }
            })
            .catch(() => {
                if (currentRequest !== primaryTrackingState.requestId) return;
                applyPrimaryTrackingId('', 'Unable to load tracking ID. Please verify the selected file.', {
                    displayWhenMissing: 'Tracking ID not found',
                    statusToneWhenMissing: 'error'
                });
            });
    }

    fetch(`/api/grouping/awaiting/${encodeURIComponent(normalized)}`)
        .then(response => response.json().catch(() => ({})).then(payload => ({ response, payload })))
        .then(({ response, payload }) => {
            if (currentRequest !== primaryTrackingState.requestId) {
                return;
            }

            // 404 or non-success → fall back to file_indexings
            if (!response.ok || !payload.success) {
                fallbackToIndexing();
                return;
            }

            const record = payload.data || payload;
            const trackingId = (record?.tracking_id || '').toString().trim();

            if (trackingId) {
                applyPrimaryTrackingId(trackingId, 'Linked to grouping record.', {
                    statusTone: 'success'
                });
            } else {
                // Grouping exists but no tracking_id — still try indexing
                fallbackToIndexing();
            }
        })
        .catch(error => {
            if (currentRequest !== primaryTrackingState.requestId) {
                return;
            }
            console.warn('Grouping lookup failed, trying file indexing fallback:', error.message);
            fallbackToIndexing();
        });
}


function setAppliedFileValidationState(isValid, message = '') {
    const appliedFileField = document.getElementById('applied-file-number');
    if (!appliedFileField) {
        return;
    }

    appliedFileField.classList.remove('border-green-200', 'border-red-400', 'focus:ring-red-200');
    appliedFileField.classList.add(isValid ? 'border-green-200' : 'border-red-400');

    let errorElement = document.getElementById('applied-file-number-error');
    if (!isValid) {
        if (!errorElement) {
            errorElement = document.createElement('p');
            errorElement.id = 'applied-file-number-error';
            errorElement.className = 'mt-2 text-xs text-red-600';
            appliedFileField.insertAdjacentElement('afterend', errorElement);
        }
        errorElement.textContent = message || 'Please select an existing file number.';
    } else if (errorElement) {
        errorElement.remove();
    }
}

function validateAppliedFileNumber(showAlert = false) {
    const appliedFileField = document.getElementById('applied-file-number');
    const value = appliedFileField ? appliedFileField.value.trim() : '';
    const isValid = value.length > 0;

    setAppliedFileValidationState(isValid, 'Please select an existing file number from Browse Files.');

    if (!isValid && showAlert) {
        alert('Please select an existing file number from File Number Information before generating ST file number.');
    }

    return isValid;
}

// Simple function to update serial number display
function updateSerialNumber(fileNumber) {
    console.log('🔢 Updating serial number from:', fileNumber);
    
    const serialDisplay = document.getElementById('serial-number-display');
    if (!serialDisplay) {
        console.error('❌ Serial display element not found!');
        return false;
    }
    
    if (!fileNumber) {
        console.error('❌ No file number provided!');
        return false;
    }
    
    // Extract serial number from file number format: ST-COM-2025-5
    const parts = fileNumber.split('-');
    if (parts.length >= 4) {
        const serialNumber = parts[3];
        serialDisplay.textContent = serialNumber;
        console.log('✅ Serial number updated to:', serialNumber);
        return true;
    } else {
        console.error('❌ Invalid file number format:', fileNumber);
        return false;
    }
}

// Global function to open file number modal
function openFileNumberModal() {
    console.log('Opening file number modal...');
    
    if (typeof GlobalFileNoModal !== 'undefined') {
        GlobalFileNoModal.open({
            targetFields: ['#applied-file-number'],
            initialTab: 'mls',
            callback: function(selectedData) {
                console.log('File number selected:', selectedData);
                
                // Populate the applied file number field
                const appliedFileField = document.getElementById('applied-file-number');
                if (appliedFileField && selectedData.fileNumber) {
                    appliedFileField.value = selectedData.fileNumber;
                    setAppliedFileValidationState(true);
                }
                
                // Show file details if available
                const fileDetails = document.getElementById('file-number-details');
                if (fileDetails && selectedData.details) {
                    document.getElementById('file-type').textContent = selectedData.details.type || '-';
                    document.getElementById('file-status').textContent = selectedData.details.status || '-';
                    document.getElementById('file-owner').textContent = selectedData.details.owner || '-';
                    fileDetails.classList.remove('hidden');
                }

                const trackingFromRecordRaw = selectedData?.record?.tracking_id;
                const trackingFromRecord = trackingFromRecordRaw ? String(trackingFromRecordRaw).trim() : '';

                if (trackingFromRecord) {
                    primaryTrackingState.requestId += 1;
                    applyPrimaryTrackingId(trackingFromRecord, 'Linked to grouping record.', {
                        statusTone: 'success'
                    });
                } else if (selectedData.fileNumber) {
                    loadPrimaryTrackingId(selectedData.fileNumber);
                } else {
                    resetPrimaryTracking();
                }
            }
        });
    } else {
        console.error('GlobalFileNoModal not available');
        alert('File number modal is not available. Please refresh the page and try again.');
    }
}

// Handle land use selection (checkbox behavior - only one can be selected)
function handleLandUseChange(selectedCheckbox) {
    console.log('Land use changed to:', selectedCheckbox.value);
    
    // Get all land use checkboxes
    const allCheckboxes = document.querySelectorAll('input[name="selectedLandUse"]');
    
    // Uncheck all others and remove selected class
    allCheckboxes.forEach(checkbox => {
        if (checkbox !== selectedCheckbox) {
            checkbox.checked = false;
            checkbox.closest('label').classList.remove('selected');
            // Update border colors back to default
            checkbox.closest('label').classList.remove('border-blue-400', 'border-green-400', 'border-orange-400', 'border-purple-400');
            checkbox.closest('label').classList.add('border-gray-200');
        }
    });
    
    // Handle the selected checkbox
    if (selectedCheckbox.checked) {
        selectedCheckbox.closest('label').classList.add('selected');
        
        // Update border color based on land use
        const landUse = selectedCheckbox.value;
        selectedCheckbox.closest('label').classList.remove('border-gray-200');
        
        switch(landUse) {
            case 'COMMERCIAL':
                selectedCheckbox.closest('label').classList.add('border-blue-400');
                break;
            case 'RESIDENTIAL':
                selectedCheckbox.closest('label').classList.add('border-green-400');
                break;
            case 'INDUSTRIAL':
                selectedCheckbox.closest('label').classList.add('border-orange-400');
                break;
            case 'MIXED':
                selectedCheckbox.closest('label').classList.add('border-purple-400');
                break;
        }
        
        // Update the hidden land_use field
        const hiddenLandUseField = document.querySelector('input[name="land_use"]');
        if (hiddenLandUseField) {
            hiddenLandUseField.value = landUse;
        }
        
        // Update the NPFN display with proper serial number from backend
        updateNPFNDisplay(landUse);
        
    } else {
        selectedCheckbox.closest('label').classList.remove('selected');
        selectedCheckbox.closest('label').classList.add('border-gray-200');
    }
}

// Update NPFN display based on selected land use - REAL PREVIEW with actual serial numbers
function updateNPFNDisplay(landUse) {
    console.log('Updating NPFN display for land use:', landUse);
    
    // Show loading state
    const npfnField = document.getElementById('np-fileno-display');
    if (npfnField) {
        npfnField.value = 'Loading preview...';
        npfnField.classList.remove('text-green-600');
        npfnField.classList.add('text-blue-600');
    }
    
    // Call preview API to get actual next file number
    fetch('/api/st-file-numbers/preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (typeof getCSRFToken === 'function') ? getCSRFToken() : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            land_use: landUse,
            type: 'PRIMARY'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Preview API response:', data);
        
        if (data.success && data.data) {
            const previewFileNo = data.data.preview_file_number;
            let serialNo = data.data.serial_no;
            
            // Extract serial from file number as fallback (ST-RES-2025-3 -> 3)
            if (!serialNo && previewFileNo) {
                const parts = previewFileNo.split('-');
                if (parts.length >= 4) {
                    serialNo = parseInt(parts[3], 10);
                }
            }
            
            // Update the main NPFN display field with clean preview (no placeholder text)
            if (npfnField) {
                npfnField.value = previewFileNo;
                npfnField.classList.remove('text-green-600');
                npfnField.classList.add('text-blue-600');
            }
            
            // Update hidden field for form submission
            const hiddenNpfnField = document.querySelector('input[name="np_fileno"]');
            if (hiddenNpfnField) {
                hiddenNpfnField.value = previewFileNo;
            }
            
            // Update serial number display (no padding, just the number)
            const serialSpan = document.getElementById('serial-number-display');
            if (serialSpan) {
                serialSpan.textContent = String(serialNo);
                serialSpan.style.color = '#3b82f6'; // Blue color for preview
            }
            
            // Update land use code display
            const landUseSpan = document.querySelector('.land-use-code-display');
            if (landUseSpan) {
                const landUseCode = previewFileNo.split('-')[1]; // Extract from ST-COM-2025-001
                landUseSpan.textContent = landUseCode;
                landUseSpan.style.color = '#3b82f6'; // Blue color for preview
            }
            
            console.log('✅ PRIMARY file number preview updated (clean display):', previewFileNo);
        } else {
            throw new Error(data.message || 'Failed to get preview');
        }
    })
    .catch(error => {
        console.error('Error getting preview:', error);
        
        // Fallback to placeholder on error
        if (npfnField) {
            npfnField.value = 'Error loading preview';
            npfnField.classList.add('text-red-600');
        }
    });
}

// REMOVED: Automatic API call - file numbers should only be generated when user clicks "Generate" button

// Generate ST FileNo function
function commissionFileNumber() {
    console.log('🚀 Commission button clicked - starting ST file number generation...');
    
    const form = document.getElementById('commissionPrimaryForm');
    if (!form) {
        console.error('❌ Commission form not found - looking for #commissionPrimaryForm');
        alert('Form not found. Please refresh the page and try again.');
        return;
    }
    
    console.log('✅ Form found:', form);
    
    // Get form data
    const formData = new FormData(form);
    const npFileNo = formData.get('np_fileno') || document.getElementById('np-fileno-display')?.value || '';
    const landUse = formData.get('land_use') || document.getElementById('hiddenLandUse')?.value || '';
    const applicantTypeRadio = form.querySelector('input[name="applicant_type"]:checked');
    const applicantType = applicantTypeRadio ? applicantTypeRadio.value.toLowerCase() : '';
    
    console.log('Form commission data:', {
        npFileNo: npFileNo,
        landUse: landUse,
        applicantType: applicantType
    });
    
    console.log('Form data:', {
        npFileNo: npFileNo,
        applicantType: applicantType,
        landUse: landUse
    });
    
    // Validation
    if (!applicantType) {
        alert('Please select an applicant type before generating ST file number.');
        return;
    }

    // Simple validation - check required fields
    if (applicantType === 'individual') {
        const firstName = document.getElementById('primary_first_name')?.value;
        const lastName = document.getElementById('primary_last_name')?.value;
        if (!firstName || !lastName) {
            alert('Please fill in first name and surname for individual applicant.');
            return;
        }
    } else if (applicantType === 'corporate') {
        const corporateName = document.getElementById('primary_corporate_name')?.value;
        if (!corporateName) {
            alert('Please fill in company name for corporate applicant.');
            return;
        }
    } else if (applicantType === 'multiple') {
        const firstName = document.getElementById('primary_owner_first_name')?.value;
        const lastName = document.getElementById('primary_owner_last_name')?.value;
        if (!firstName || !lastName) {
            alert('Please fill in first name and surname for primary owner.');
            return;
        }
    }

    const appliedFileNumber = document.getElementById('applied-file-number')?.value || '';

    if (!validateAppliedFileNumber(true)) {
        return;
    }
    
    // Get application type (required field)
    const applicationTypeRadio = form.querySelector('input[name="application_type"]:checked');
    const applicationType = applicationTypeRadio ? applicationTypeRadio.value : '';
    
    console.log('Application Type:', applicationType);
    
    // Validate application type
    if (!applicationType) {
        alert('Please select an application type (Direct Allocation or Conversion) before generating ST file number.');
        return;
    }
    
    const rawTrackingId = document.getElementById('primary-tracking-id')?.value;
    const trackingId = rawTrackingId ? rawTrackingId.trim() : '';

    const payload = {
        np_fileno: npFileNo,
        applied_file_number: appliedFileNumber,
        fileno: appliedFileNumber || null,
        tracking_id: trackingId || null,
        application_type: applicationType, // REQUIRED: Application Type
        applicant_type: applicantType,
        land_use: landUse,
        commissioned_by: formData.get('commissioned_by') || document.querySelector('input[name="commissioned_by"]')?.value || '',
        commissioned_date: formData.get('commissioned_date') || document.querySelector('input[name="commissioned_date"]')?.value || '',
        property_house_no: document.getElementById('propertyHouseNo')?.value || '',
        property_plot_no: document.getElementById('propertyPlotNo')?.value || '',
        property_street_name: document.getElementById('propertyStreetName')?.value || '',
        property_district: document.getElementById('propertyDistrict')?.value || '',
        property_lga: document.getElementById('propertyLga')?.value || '',
        property_state: document.getElementById('propertyState')?.value || '',
        latitude: document.getElementById('propertyLatitude')?.value || null,
        longitude: document.getElementById('propertyLongitude')?.value || null
    };

    // Add applicant-specific data
    switch (applicantType) {
        case 'individual':
            payload.first_name = document.getElementById('primary_first_name')?.value || '';
            payload.surname = document.getElementById('primary_last_name')?.value || '';
            payload.middle_name = document.getElementById('primary_middle_name')?.value || '';
            payload.applicant_title = document.getElementById('primary_title')?.value || '';
            break;
        case 'corporate':
            payload.corporate_name = document.getElementById('primary_corporate_name')?.value || '';
            payload.rc_number = document.getElementById('primary_rc_number')?.value || '';
            break;
        case 'multiple':
            payload.first_name = document.getElementById('primary_owner_first_name')?.value || '';
            payload.surname = document.getElementById('primary_owner_last_name')?.value || '';
            payload.middle_name = document.getElementById('primary_owner_middle_name')?.value || '';
            payload.applicant_title = document.getElementById('primary_owner_title')?.value || '';
            break;
    }

    console.log('Form submission payload:', {
        npFileNo: payload.np_fileno,
        applicantType: payload.applicant_type,
        landUse: payload.land_use,
        payload: payload
    });

    if (!landUse) {
        alert('Please select a land use type before generating ST file number.');
        return;
    }
    
    if (!npFileNo || npFileNo === 'Generating...') {
        alert('Please wait for file number generation to complete.');
        return;
    }
    
    // Show confirmation
    if (confirm(`Are you sure you want to commission ST file number: ${npFileNo}?`)) {
        // Show loading state
        const generateBtn = document.querySelector('button[onclick="commissionFileNumber()"]');
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Commissioning...';
        }
        
        // Send AJAX request to backend to actually commission the file number
        fetch('/commission-new-st/commission', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (typeof getCSRFToken === 'function') ? getCSRFToken() : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Shared commissioning card (sua_commission.js): where the record
                // went, plus the EDMS scan folder created for the file.
                if (typeof showStCommissioningCard === 'function') {
                    showStCommissioningCard(data, `ST file number ${npFileNo} has been commissioned successfully!`);
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `ST file number ${npFileNo} has been commissioned successfully!`,
                        confirmButtonColor: '#10b981'
                    });
                }
                console.log('ST file number commissioned:', npFileNo);
                
                // Optional: Redirect or refresh form
                // window.location.reload();
            } else {
                throw new Error(data.message || 'Failed to commission file number');
            }
        })
        .catch(error => {
            console.error('Error commissioning file number:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error: ' + error.message,
                confirmButtonColor: '#ef4444'
            });
        })
        .finally(() => {
            // Restore button state
            if (generateBtn) {
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i data-lucide="zap" class="w-4 h-4 mr-2"></i>Generate ST FileNo';
            }
        });
    }
}

// Initialize file modal integration
function initializeFileModalIntegration() {
    console.log('Initializing File Modal Integration...');

    initializeTrackingDisplay();

    const appliedFileField = document.getElementById('applied-file-number');
    if (appliedFileField) {
        appliedFileField.addEventListener('input', () => {
            if (appliedFileField.value.trim()) {
                setAppliedFileValidationState(true);
            }
        });
    }
    
    // Initialize global file modal if available
    if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.init === 'function') {
        GlobalFileNoModal.init();
        console.log('Global file number modal initialized');
    }
    
    // Initialize land use selection with default
    const defaultLandUse = document.querySelector('input[name="selectedLandUse"]:checked');
    if (defaultLandUse) {
        console.log('Found default land use:', defaultLandUse.value);
        updateNPFNDisplay(defaultLandUse.value);
    } else {
        // Set commercial as default if none selected
        const commercialCheckbox = document.querySelector('input[name="selectedLandUse"][value="COMMERCIAL"]');
        if (commercialCheckbox) {
            console.log('Setting Commercial as default land use');
            commercialCheckbox.checked = true;
            handleLandUseChange(commercialCheckbox);
        } else {
            console.log('No land use checkboxes found, will update when user selects');
        }
    }
}

// Make functions globally available
window.updateSerialNumber = updateSerialNumber;
window.openFileNumberModal = openFileNumberModal;
window.handleLandUseChange = handleLandUseChange;
window.updateNPFNDisplay = updateNPFNDisplay;
window.commissionFileNumber = commissionFileNumber;
window.initializeFileModalIntegration = initializeFileModalIntegration;

// Debug function availability
console.log('🔧 Global functions registered:', {
    commissionFileNumber: typeof window.commissionFileNumber,
    handleLandUseChange: typeof window.handleLandUseChange,
    openFileNumberModal: typeof window.openFileNumberModal
});

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('File Modal Integration script loaded');
    initializeFileModalIntegration();
    
    // Backup: Add event listener directly to the button
    setTimeout(() => {
        const generateBtn = document.querySelector('button[onclick="commissionFileNumber()"]');
        if (generateBtn) {
            console.log('✅ Found Generate ST FileNo button, adding backup event listener');
            generateBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('🎯 Button clicked via event listener');
                commissionFileNumber();
            });
        } else {
            console.error('❌ Generate ST FileNo button not found');
        }
    }, 1000);
});
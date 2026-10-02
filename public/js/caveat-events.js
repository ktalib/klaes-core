/**
 * Caveat Event Listeners
 * Handles all form input events and user interactions
 */

function initializeEventListeners() {
    const uppercaseFieldIds = ['location', 'petitioner', 'grantor', 'grantee', 'serial-no', 'volume-no', 'instructions', 'remarks'];
    const normalizeUppercase = (element) => {
        if (!element || element.disabled || element.readOnly) {
            return;
        }

        const original = element.value || '';
        const normalized = original.toUpperCase();
        if (normalized === original) {
            return;
        }

        const start = typeof element.selectionStart === 'number' ? element.selectionStart : null;
        const end = typeof element.selectionEnd === 'number' ? element.selectionEnd : null;
        element.value = normalized;

        if (start !== null && end !== null && typeof element.setSelectionRange === 'function') {
            element.setSelectionRange(start, end);
        }
    };

    uppercaseFieldIds.forEach((fieldId) => {
        const element = document.getElementById(fieldId);
        if (!element) {
            return;
        }

        element.addEventListener('input', function () {
            normalizeUppercase(element);
        });

        element.addEventListener('blur', function () {
            normalizeUppercase(element);
        });
    });

    // Encumbrance info icon click handler
    const encumbranceIcon = document.getElementById('encumbrance-info-icon');
    if (encumbranceIcon) {
        encumbranceIcon.addEventListener('click', function() {
            const descriptionDiv = document.getElementById('encumbrance-description');
            if (descriptionDiv) {
                descriptionDiv.classList.toggle('hidden');
            }
        });
    }

    // Search inputs
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            window.searchTerm = e.target.value.toLowerCase();
            if (typeof renderCaveatsList === 'function') {
                renderCaveatsList();
            }
        });
    }
    
    const liftSearchInput = document.getElementById('lift-search-input');
    if (liftSearchInput) {
        liftSearchInput.addEventListener('input', function(e) {
            window.searchTerm = e.target.value.toLowerCase();
            if (typeof renderActiveCaveatsList === 'function') {
                renderActiveCaveatsList();
            }
        });
    }
    
    const logSearchInput = document.getElementById('log-search-input');
    if (logSearchInput) {
        logSearchInput.addEventListener('input', function(e) {
            window.searchTerm = e.target.value.toLowerCase();
            if (typeof renderCaveatsTable === 'function') {
                renderCaveatsTable();
            }
        });
    }
    
    // Status filters
    const statusFilterElement = document.getElementById('status-filter');
    if (statusFilterElement) {
        statusFilterElement.addEventListener('change', function(e) {
            window.statusFilter = e.target.value;
            if (typeof renderCaveatsList === 'function') {
                renderCaveatsList();
            }
        });
    }
    
    const logStatusFilter = document.getElementById('log-status-filter');
    if (logStatusFilter) {
        logStatusFilter.addEventListener('change', function(e) {
            window.statusFilter = e.target.value;
            if (typeof renderCaveatsTable === 'function') {
                renderCaveatsTable();
            }
        });
    }

    // Form input handlers for auto-completion
    const locationInput = document.getElementById('location');
    if (locationInput) {
        locationInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.location = e.target.value;
            }
            // Validate form and update button states
            validateFormAndUpdateButtons();
        });
    }

    const petitionerInput = document.getElementById('petitioner');
    if (petitionerInput) {
        petitionerInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.petitioner = e.target.value;
            }
            // Rebuild legal search comment
            if (typeof buildLegalSearchComment === 'function') buildLegalSearchComment();
            // Validate form and update button states
            validateFormAndUpdateButtons();
        });
    }

    const granteeInput = document.getElementById('grantee');
    if (granteeInput) {
        granteeInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.grantee = e.target.value;
            }
        });
    }

    const serialNoInput = document.querySelector('#tab-place #serial-no');
    if (serialNoInput) {
        serialNoInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.serialNo = e.target.value;
            }
            
            // Auto-fill page number (same as serial number)
            updatePageNumber(e.target.value);
            
            // Generate registration number
            generateRegistrationNumber();
            
            if (typeof updateDateCreated === 'function') {
                updateDateCreated();
            }
        });
    }

    const volumeNoInput = document.querySelector('#tab-place #volume-no');
    if (volumeNoInput) {
        volumeNoInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.volumeNo = e.target.value;
            }
            
            // Generate registration number
            generateRegistrationNumber();
            
            if (typeof updateDateCreated === 'function') {
                updateDateCreated();
            }
        });
    }

    // Disable and grey out page number field on page load
    const pageNoInput = document.querySelector('#tab-place #page-no');
    if (pageNoInput) {
        pageNoInput.readOnly = true;
        pageNoInput.style.backgroundColor = '#f3f4f6';
        pageNoInput.style.color = '#6b7280';
        pageNoInput.style.cursor = 'not-allowed';
        pageNoInput.title = 'Page number is automatically set to match Serial number';
        
        // Remove any existing event listeners and prevent manual input
        pageNoInput.addEventListener('input', function(e) {
            e.preventDefault();
            return false;
        });
        
        pageNoInput.addEventListener('keydown', function(e) {
            e.preventDefault();
            return false;
        });
    }

    const instructionsInput = document.getElementById('instructions');
    if (instructionsInput) {
        instructionsInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.instructions = e.target.value;
            }
        });
    }

    const remarksInput = document.getElementById('remarks');
    if (remarksInput) {
        remarksInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.remarks = e.target.value;
            }
        });
    }

    const liftInstructionsInput = document.getElementById('lift-instructions');
    if (liftInstructionsInput) {
        liftInstructionsInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.liftInstructions = e.target.value;
            }
        });
    }

    const liftRemarksInput = document.getElementById('lift-remarks');
    if (liftRemarksInput) {
        liftRemarksInput.addEventListener('input', function(e) {
            if (window.formData) {
                window.formData.liftRemarks = e.target.value;
            }
        });
    }

    // Encumbrance type change handler
    const encumbranceTypeSelect = document.getElementById('encumbrance-type');
    if (encumbranceTypeSelect) {
        encumbranceTypeSelect.addEventListener('change', function(e) {
            const selectedType = e.target.value;
            if (window.formData) {
                window.formData.encumbranceType = selectedType;
            }
            
            // Show description
            const descriptionDiv = document.getElementById('encumbrance-description');
            const descriptionText = document.getElementById('encumbrance-description-text');
            
            if (descriptionDiv && descriptionText && window.encumbranceDescriptions) {
                if (selectedType && window.encumbranceDescriptions[selectedType]) {
                    descriptionText.textContent = window.encumbranceDescriptions[selectedType];
                    descriptionDiv.classList.remove('hidden');
                } else {
                    descriptionDiv.classList.add('hidden');
                }
            }

            // Rebuild legal search comment
            if (typeof buildLegalSearchComment === 'function') buildLegalSearchComment();
            // Validate form and update button states
            validateFormAndUpdateButtons();
        });
    }

    // Instrument type change handler
    const instrumentTypeSelect = document.getElementById('instrument-type');
    if (instrumentTypeSelect) {
        instrumentTypeSelect.addEventListener('change', function() {
            if (typeof window.revalidateButtonStates === 'function') {
                window.revalidateButtonStates();
            }
            
            // Validate form and update button states (general validation)
            validateFormAndUpdateButtons();
        });
    }

    // Type of deed change handler
    const typeOfDeedSelect = document.getElementById('type-of-deed');
    if (typeOfDeedSelect) {
        typeOfDeedSelect.addEventListener('change', function(e) {
            if (window.formData) {
                window.formData.typeOfDeed = e.target.value;
            }
        });
    }

    // Date inputs
    const startDateInput = document.getElementById('start-date');
    if (startDateInput) {
        startDateInput.addEventListener('change', function(e) {
            if (window.formData) {
                window.formData.startDate = e.target.value;
            }
            // Auto-calculate release date as 6 months after start date
            const releaseDateInput = document.getElementById('release-date');
            if (releaseDateInput && e.target.value) {
                const d = new Date(e.target.value);
                if (!isNaN(d)) {
                    d.setMonth(d.getMonth() + 6);
                    const formatted = d.toISOString().slice(0, 16);
                    releaseDateInput.value = formatted;
                    if (window.formData) window.formData.releaseDate = formatted;
                }
            }
            // Rebuild legal search comment
            if (typeof buildLegalSearchComment === 'function') buildLegalSearchComment();
        });
    }

    const endDateInput = document.getElementById('end-date');
    if (endDateInput) {
        endDateInput.addEventListener('change', function(e) {
            if (window.formData) {
                window.formData.endDate = e.target.value;
            }
        });
    }

    // Page number input is now disabled - no event listener needed
    // Registration number is a display div, not an input - no event listener needed

    // Form submission handlers
    const caveatForm = document.getElementById('caveat-form');
    if (caveatForm) {
        caveatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleCaveatSubmission();
        });
    }

    const liftForm = document.getElementById('lift-form');
    if (liftForm) {
        liftForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleLiftSubmission();
        });
    }

    // Change file number button - handled by the inline script in caveat/index.blade.php
    // which uses GlobalFileNoModal. This block is kept as a fallback.
    const changeFileNumberBtn = document.getElementById('change-file-number');
    if (changeFileNumberBtn) {
        changeFileNumberBtn.addEventListener('click', function() {
            // Clear current selection
            const fileNumberDisplay = document.getElementById('file_number_display');
            if (fileNumberDisplay) {
                fileNumberDisplay.value = '';
            }

            const selectedInfo = document.getElementById('selected-file-info');
            if (selectedInfo) {
                selectedInfo.classList.add('hidden');
            }

            const fileNumberInput = document.getElementById('file_number');
            if (fileNumberInput) {
                fileNumberInput.value = '';
            }

            // Open global file number modal if available
            if (window.GlobalFileNoModal) {
                window.GlobalFileNoModal.open({
                    targetFields: ['#file_number', '#file_number_display']
                });
            }
        });
    }

    // Form action buttons
    const placeCaveatBtn = document.getElementById('place-caveat');
    if (placeCaveatBtn) {
        placeCaveatBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleCaveatSubmission();
        });
    }

    const updateCaveatBtn = document.getElementById('update-caveat');
    if (updateCaveatBtn) {
        updateCaveatBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleCaveatSubmission(true);
        });
    }

    const saveDraftBtn = document.getElementById('save-draft');
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Call the save new record function
            if (typeof window.saveNewRecord === 'function') {
                window.saveNewRecord();
            } else {
                console.error('saveNewRecord function not available');
                Swal.fire({
                    icon: 'error',
                    title: 'Function Error',
                    text: 'Save record function is not available. Please refresh the page.',
                    confirmButtonColor: '#3b82f6'
                });
            }
        });
    }

    const resetFormBtn = document.getElementById('reset-form');
    if (resetFormBtn) {
        resetFormBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof resetCaveatForm === 'function') {
                resetCaveatForm();
            }
        });
    }

    const liftCaveatBtn = document.getElementById('lift-caveat');
    if (liftCaveatBtn) {
        liftCaveatBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleLiftSubmission();
        });
    }

    const saveLiftDraftBtn = document.getElementById('save-lift-draft');
    if (saveLiftDraftBtn) {
        saveLiftDraftBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // TODO: Implement save lift draft functionality
            Swal.fire({
                icon: 'info',
                title: 'Feature Coming Soon',
                text: 'Lift draft save functionality will be implemented in a future update.',
                confirmButtonColor: '#3b82f6'
            });
        });
    }

    const resetLiftFormBtn = document.getElementById('reset-lift-form');
    if (resetLiftFormBtn) {
        resetLiftFormBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof resetLiftForm === 'function') {
                resetLiftForm();
            }
            // Clear selection
            window.selectedCaveat = null;
            // Hide lifting details
            const liftingDetails = document.getElementById('lifting-details');
            const noCaveatSelected = document.getElementById('no-caveat-selected');
            if (liftingDetails) liftingDetails.classList.add('hidden');
            if (noCaveatSelected) noCaveatSelected.classList.remove('hidden');
        });
    }

    // Generate Acknowledgement Sheet PDF buttons
    const generateAcknowledgementBtn = document.getElementById('generate-acknowledgement');
    if (generateAcknowledgementBtn) {
        // Disable the button initially since it's only for records already in database
        generateAcknowledgementBtn.disabled = true;
        generateAcknowledgementBtn.classList.add('cursor-not-allowed', 'opacity-50');
        generateAcknowledgementBtn.title = 'Only available for records already saved in the database';
        
        generateAcknowledgementBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if this is for an existing record
            if (generateAcknowledgementBtn.disabled) {
                Swal.fire({
                    icon: 'info',
                    title: 'Feature Not Available',
                    text: 'Generate Acknowledgement Sheet is only available for records that are already saved in the database. Please save the record first.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            
            generateAcknowledgementPDF('place');
        });
    }

    const generateLiftAcknowledgementBtn = document.getElementById('generate-lift-acknowledgement');
    if (generateLiftAcknowledgementBtn) {
        generateLiftAcknowledgementBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.selectedCaveat) {
                generateAcknowledgementPDF('lift', window.selectedCaveat);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Caveat Selected',
                    text: 'Please select a caveat to generate acknowledgement sheet.',
                    confirmButtonColor: '#3b82f6'
                });
            }
        });
    }
}

// Form submission handlers
function handleCaveatSubmission(isUpdate = false) {
    console.log('Caveat form submitted');
    
    // Validate required fields - updated to match backend validation
    const requiredFields = ['encumbrance-type', 'location', 'petitioner'];
    let isValid = true;
    
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && !field.value.trim()) {
            field.classList.add('border-red-500');
            isValid = false;
        } else if (field) {
            field.classList.remove('border-red-500');
        }
    });
    
    if (!isValid) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Please fill in all required fields marked with *',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    // Phone number. This form saves by fetch, so the shared helper's submit
    // interception never fires — ask it explicitly. Mirrors the `required`
    // + NigerianPhone rule on CaveatController::store.
    const petitionerPhone = document.getElementById('petitioner-phone');
    if (petitionerPhone && window.NigerianPhone && !window.NigerianPhone.validateField(petitionerPhone, false)) {
        Swal.fire({
            icon: 'error',
            title: 'Phone Number Required',
            text: 'Enter an 11-digit phone number starting with 0, such as 08012345678.',
            confirmButtonColor: '#3b82f6'
        });
        petitionerPhone.focus();
        return;
    }
    
    // Show loading state
    const editingCaveatId = isUpdate ? window.editingCaveatId : null;
    if (isUpdate && !editingCaveatId) {
        Swal.fire('No Caveat selected', 'Select an existing Caveat before updating it.', 'warning');
        return;
    }
    const submitButton = document.getElementById(isUpdate ? 'update-caveat' : 'place-caveat');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>' + (isUpdate ? 'Updating Caveat...' : 'Placing Caveat...');
    }
    
    // Get file number from hidden input (populated by GlobalFileNoModal) or display input
    let fileNumber = null;
    const fileNumberInput = document.getElementById('file_number');
    const fileNumberDisplay = document.getElementById('file_number_display');
    
    if (fileNumberInput && fileNumberInput.value) {
        fileNumber = fileNumberInput.value;
    } else if (fileNumberDisplay && fileNumberDisplay.value) {
        fileNumber = fileNumberDisplay.value;
    }
    
    console.log('File number detection:', {
        fileNumberInput: fileNumberInput?.value,
        fileNumberDisplay: fileNumberDisplay?.value,
        finalFileNumber: fileNumber
    });
    
    // Collect form data - map to backend field names
    const encumbranceTypeRaw = document.getElementById('encumbrance-type')?.value;
    const encumbranceTypeOther = document.getElementById('encumbrance-type-other')?.value?.trim();
    const formDataToSubmit = {
        encumbrance_type: encumbranceTypeRaw === 'Other' ? (encumbranceTypeOther || 'Other') : encumbranceTypeRaw,
        instrument_type_id: document.getElementById('instrument-type')?.value ? parseInt(document.getElementById('instrument-type').value) : null,
        overwrite_instrument_type: typeof window.isInstrumentOverwriteEnabled === 'function' ? window.isInstrumentOverwriteEnabled() : false,
        file_number: fileNumber,
        location: document.getElementById('location')?.value,
        petitioner: document.getElementById('petitioner')?.value,
        // This payload is hand-built, so the new phone input sends nothing
        // until it is named here. phone_confirmed is set by
        // public/js/sms-applicant-phone.js once the officer has cleared the
        // shared-number warning; without it the SMS is recorded as skipped.
        petitioner_phone: document.getElementById('petitioner-phone')?.value || null,
        phone_confirmed: document.querySelector('#petitioner-phone ~ input[name="phone_confirmed"]')?.value === '1',
        grantee: document.getElementById('grantee')?.value || null,
        serial_no: document.querySelector('#tab-place #serial-no')?.value || null,
        page_no: document.querySelector('#tab-place #page-no')?.value || null,
        volume_no: document.querySelector('#tab-place #volume-no')?.value || null,
        registration_number: document.querySelector('#tab-place #registration-number')?.textContent !== 'Enter Serial No. and Volume No. to generate' 
            ? document.querySelector('#tab-place #registration-number')?.textContent : null,
        start_date: document.getElementById('start-date')?.value,
        release_date: document.getElementById('release-date')?.value || null,
        instructions: document.getElementById('instructions')?.value || null,
        remarks: document.getElementById('remarks')?.value || null
    };
    
    console.log('Form data to submit:', formDataToSubmit);
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Submit to backend API
    fetch(isUpdate ? '/caveat/api/' + encodeURIComponent(editingCaveatId) + '/update' : '/caveat/api', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(formDataToSubmit)
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        
        // Handle non-200 responses
        if (!response.ok) {
            return response.json().then(errorData => {
                console.error('Server error response:', errorData);
                throw new Error(errorData.error || errorData.message || `Server error: ${response.status}`);
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Success response data:', data);
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: isUpdate ? 'Caveat Updated Successfully!' : 'Caveat Placed Successfully!',
                text: `Caveat Number: ${data.data.caveat_number}`,
                confirmButtonColor: '#3b82f6'
            });
            
            // Reset form
            resetCaveatForm();
            
            // Refresh data displays
            loadCaveatsData();
            
            // Update stats
            if (typeof updateStats === 'function') {
                updateStats();
            }
        } else {
            throw new Error(data.error || 'Failed to place caveat');
        }
    })
    .catch(error => {
        console.error('Error placing caveat:', error);
        console.error('Full error details:', {
            message: error.message,
            stack: error.stack,
            formData: formDataToSubmit
        });
        
        // Handle specific error types
        if (error.message.includes('Record not found')) {
            // Use toast notification instead of SweetAlert as requested
            if (typeof showToast === 'function') {
                showToast('Record Not Found. Please create the record first before placing a caveat.', 'info');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Record Not Found',
                    text: 'Please create the record first before placing a caveat.',
                    confirmButtonColor: '#3b82f6'
                });
            }
        } else if (error.message.includes('validation') || error.message.includes('required')) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: error.message,
                confirmButtonColor: '#3b82f6'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error Placing Caveat',
                text: error.message || 'An unexpected error occurred. Please check the console for details.',
                confirmButtonColor: '#3b82f6'
            });
        }
    })
    .finally(() => {
        // Reset button state
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = isUpdate
                ? '<i class="fa-solid fa-pen-to-square mr-2"></i>Update Caveat'
                : '<i class="fa-regular fa-paper-plane mr-2"></i>Place Caveat';
        }
    });
}

function handleLiftSubmission() {
    console.log('Lift form submitted');
    
    if (!window.selectedCaveat) {
        Swal.fire({
            icon: 'warning',
            title: 'No Caveat Selected',
            text: 'Please select a caveat to lift from the left panel.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    // Validate required fields
    const releaseDateInput = document.getElementById('lift-release-date');
    if (!releaseDateInput || !releaseDateInput.value) {
        Swal.fire({
            icon: 'error',
            title: 'Missing Release Date',
            text: 'Please select a release date for lifting the caveat.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    // Show loading state
    const submitButton = document.getElementById('lift-caveat');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Lifting Caveat...';
    }
    
    // Collect lift form data - map to backend field names
    const liftData = {
        release_date: releaseDateInput.value,
        lift_instructions: document.getElementById('lift-instructions')?.value || null,
        lift_remarks: document.getElementById('lift-remarks')?.value || null
    };
    
    console.log('Lift data:', liftData);
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Submit to backend API
    fetch(`/caveat/api/${window.selectedCaveat.id}/lift`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(liftData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Caveat Lifted Successfully!',
                text: `Caveat Number: ${data.data.caveat_number} has been lifted.`,
                confirmButtonColor: '#3b82f6'
            });
            
            // Reset lift form
            resetLiftForm();
            
            // Clear selection
            window.selectedCaveat = null;
            
            // Refresh data displays
            loadCaveatsData();
            
            // Update stats
            if (typeof updateStats === 'function') {
                updateStats();
            }
            
            // Hide lifting details
            const liftingDetails = document.getElementById('lifting-details');
            const noCaveatSelected = document.getElementById('no-caveat-selected');
            if (liftingDetails) liftingDetails.classList.add('hidden');
            if (noCaveatSelected) noCaveatSelected.classList.remove('hidden');
            
        } else {
            throw new Error(data.error || 'Failed to lift caveat');
        }
    })
    .catch(error => {
        console.error('Error lifting caveat:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error Lifting Caveat',
            text: error.message,
            confirmButtonColor: '#3b82f6'
        });
    })
    .finally(() => {
        // Reset button state
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fa-regular fa-paper-plane mr-2"></i>Lift Caveat';
        }
    });
}

// Auto-fill page number function
function updatePageNumber(serialNo) {
    console.log('updatePageNumber called with:', serialNo);
    
    const pageNoInput = document.querySelector('#tab-place #page-no');
    if (pageNoInput) {
        pageNoInput.value = serialNo;
        console.log('Page number updated to:', serialNo);
    }
}

// Generate registration number function
function generateRegistrationNumber() {
    console.log('generateRegistrationNumber called');
    
    const serialNoInput = document.querySelector('#tab-place #serial-no');
    const pageNoInput = document.querySelector('#tab-place #page-no');
    const volumeNoInput = document.querySelector('#tab-place #volume-no');
    const registrationDisplay = document.querySelector('#tab-place #registration-number');
    
    console.log('Elements found:', {
        serialNoInput: !!serialNoInput,
        pageNoInput: !!pageNoInput,
        volumeNoInput: !!volumeNoInput,
        registrationDisplay: !!registrationDisplay
    });
    
    if (!registrationDisplay) {
        console.error('Registration number display element not found');
        return;
    }
    
    const serialNo = serialNoInput?.value?.trim() || '';
    const pageNo = pageNoInput?.value?.trim() || '';
    const volumeNo = volumeNoInput?.value?.trim() || '';
    
    console.log('Registration data:', { serialNo, pageNo, volumeNo });
    
    if (serialNo && volumeNo) {
        // Use pageNo if available, otherwise use serialNo (since page = serial)
        const finalPageNo = pageNo || serialNo;
        const registrationNumber = `${serialNo}/${finalPageNo}/${volumeNo}`;
        
        registrationDisplay.textContent = registrationNumber;
        registrationDisplay.style.color = '#1f2937'; // Dark gray
        registrationDisplay.style.fontWeight = 'bold';
        
        console.log('Generated registration number:', registrationNumber);
        console.log('Registration display updated:', registrationDisplay.textContent);
    } else {
        registrationDisplay.textContent = 'Enter Serial No. and Volume No. to generate';
        registrationDisplay.style.color = '#6b7280'; // Gray
        registrationDisplay.style.fontWeight = 'normal';
        
        console.log('Registration number reset - missing required fields');
        console.log('Missing:', { 
            serialNo: !serialNo ? 'Serial No is empty' : 'Serial No OK', 
            volumeNo: !volumeNo ? 'Volume No is empty' : 'Volume No OK' 
        });
    }
}

// Test function to manually trigger registration number generation
function testRegistrationNumber() {
    console.log('Testing registration number generation...');
    
    // Set test values
    const serialNoInput = document.querySelector('#tab-place #serial-no');
    const volumeNoInput = document.querySelector('#tab-place #volume-no');
    
    if (serialNoInput && volumeNoInput) {
        serialNoInput.value = '123';
        volumeNoInput.value = '456';
        
        // Trigger the function
        generateRegistrationNumber();
    } else {
        console.error('Could not find input elements for testing');
    }
}

// Update date created function
function updateDateCreated() {
    console.log('updateDateCreated called');
    
    const dateCreatedInput = document.getElementById('date-created');
    if (dateCreatedInput) {
        const now = new Date();
        const formattedDate = now.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        dateCreatedInput.value = formattedDate;
        console.log('Date created updated to:', formattedDate);
    }
}

/**
 * Validate form fields and update button states based on form completion
 * Enables "Place Caveat" button when required fields are filled
 * Enables "Save Record" button when file number is not in database
 */
function validateFormAndUpdateButtons() {
    const encumbranceType = document.getElementById('encumbrance-type')?.value?.trim();
    const location = document.getElementById('location')?.value?.trim();
    const petitioner = document.getElementById('petitioner')?.value?.trim();
    const fileNumber = document.getElementById('file_number')?.value?.trim() || document.getElementById('file_number_display')?.value?.trim();
    
    const allRequiredFieldsFilled = encumbranceType && location && petitioner && fileNumber;
    
    console.log('Form validation check:', {
        encumbranceType: !!encumbranceType,
        location: !!location,
        petitioner: !!petitioner,
        fileNumber: !!fileNumber,
        allFieldsFilled: allRequiredFieldsFilled
    });
    
    const placeCaveatBtn = document.getElementById('place-caveat');
    const fileNumberExists = window.fileNumberSearchState?.fileNumberExists;
    
    // Only enable "Place Caveat" button if:
    // 1. All required fields are filled
    // 2. File number exists in database (fileNumberExists is true or we're auto-filling from a found file)
    // 3. Instrument type matches an existing record (checked via revalidateButtonStates logic)
    
    // Call revalidateButtonStates if available to handle the specific Instrument Type vs Record check
    if (typeof window.revalidateButtonStates === 'function') {
        window.revalidateButtonStates(allRequiredFieldsFilled);
    } else {
        // Fallback to original logic if revalidateButtonStates is not defined
        if (allRequiredFieldsFilled && fileNumberExists) {
            if (placeCaveatBtn && placeCaveatBtn.disabled) {
                console.log('Enabling Place Caveat button (fallback)');
                if (typeof enablePlaceCaveatButton === 'function') {
                    enablePlaceCaveatButton();
                }
            }
        } else if (allRequiredFieldsFilled && !fileNumberExists) {
            // File number was searched and NOT found, so we should enable Save Record instead
            if (placeCaveatBtn && placeCaveatBtn.disabled) {
                console.log('File number not found yet, keeping Place Caveat button disabled');
            }
        }
    }
}

// Helper functions for form management
function resetCaveatForm() {
    // Clear the selected existing caveat data
    window.selectedExistingCaveat = null;
    window.editingCaveatId = null;

    const placeButton = document.getElementById('place-caveat');
    const updateButton = document.getElementById('update-caveat');
    if (placeButton) placeButton.classList.remove('hidden');
    if (updateButton) {
        updateButton.classList.add('hidden');
        updateButton.disabled = false;
        updateButton.innerHTML = '<i class="fa-solid fa-pen-to-square mr-2"></i>Update Caveat';
    }

    if (typeof window.resetAutofillFlags === 'function') {
        window.resetAutofillFlags();
    }
    
    // Reset all form fields
    const form = document.querySelector('#tab-place form') || document.querySelector('#tab-place');
    if (form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            } else if (!input.disabled && input.id !== 'caveat-number' && input.id !== 'date-created') {
                input.value = '';
            }
        });
    }
    
    // Reset registration number display
    const registrationDisplay = document.getElementById('registration-number');
    if (registrationDisplay) {
        registrationDisplay.textContent = 'Enter Serial No. and Volume No. to generate';
        registrationDisplay.style.color = '#6b7280';
        registrationDisplay.style.fontWeight = 'normal';
    }
    
    // Reset file number selector
    const hiddenFileNumber = document.getElementById('file_number');
    if (hiddenFileNumber) hiddenFileNumber.value = '';
    const fileNumberDisplay = document.getElementById('file_number_display');
    if (fileNumberDisplay) fileNumberDisplay.value = '';

    // Re-enable file number selection
    const selectFileBtn = document.getElementById('select-fileno-btn');
    if (selectFileBtn) {
        selectFileBtn.disabled = false;
        selectFileBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        selectFileBtn.classList.add('hover:bg-blue-700');
    }
    
    const selectedInfo = document.getElementById('selected-file-info');
    if (selectedInfo) {
        selectedInfo.classList.add('hidden');
    }

    if (typeof window.setOverwriteControlVisibility === 'function') {
        window.setOverwriteControlVisibility(false);
    }

    const selectionPreview = document.getElementById('current-selection-preview');
    if (selectionPreview) {
        selectionPreview.classList.add('hidden');
    }

    const fileNumberStatus = document.getElementById('file-number-status');
    if (fileNumberStatus) {
        fileNumberStatus.classList.add('hidden');
        fileNumberStatus.innerHTML = '';
    }
    
    // Ensure page number field remains disabled and styled correctly after reset
    const pageNoInput = document.querySelector('#tab-place #page-no');
    if (pageNoInput) {
        pageNoInput.readOnly = true;
        pageNoInput.style.backgroundColor = '#f3f4f6';
        pageNoInput.style.color = '#6b7280';
        pageNoInput.style.cursor = 'not-allowed';
        pageNoInput.value = ''; // Clear the value but keep it disabled
    }
    
    // Disable the Generate Acknowledgement Sheet button since we're back to new record mode
    const generateAcknowledgementBtn = document.getElementById('generate-acknowledgement');
    if (generateAcknowledgementBtn) {
        generateAcknowledgementBtn.disabled = true;
        generateAcknowledgementBtn.classList.add('cursor-not-allowed', 'opacity-50');
        generateAcknowledgementBtn.classList.remove('cursor-pointer');
        generateAcknowledgementBtn.title = 'Only available for records already saved in the database';
    }
    
    // Disable buttons since form was reset
    if (typeof disablePlaceCaveatButton === 'function') {
        disablePlaceCaveatButton();
    }
    if (typeof disableSaveRecordButton === 'function') {
        disableSaveRecordButton();
    }
    
    // Reset legal search comment
    const lsComment = document.getElementById('legal-search-comment');
    if (lsComment) {
        lsComment.innerHTML = '<span class="text-gray-400 italic">Auto-generated when form fields are filled</span>';
    }

    // Generate new caveat number and update date
    if (typeof updateCaveatNumber === 'function') {
        updateCaveatNumber();
    }
    if (typeof updateDateCreated === 'function') {
        updateDateCreated();
    }
    if (typeof setDefaultStartDate === 'function') {
        setDefaultStartDate();
    }
}

function resetLiftForm() {
    // Reset lift form fields
    const liftInstructions = document.getElementById('lift-instructions');
    const liftRemarks = document.getElementById('lift-remarks');
    const liftReleaseDate = document.getElementById('lift-release-date');
    
    if (liftInstructions) liftInstructions.value = '';
    if (liftRemarks) liftRemarks.value = '';
    if (liftReleaseDate) liftReleaseDate.value = '';
}

// Load caveats data from backend
function loadCaveatsData() {
    console.log('Loading caveats data from backend...');
    
    const params = new URLSearchParams({
        q: window.searchTerm || '',
        status: window.statusFilter || 'all'
    });
    
    fetch(`/caveat/api?${params}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update global caveats data
            window.caveats = data.data;
            
            // Refresh all displays
            if (typeof renderCaveatsList === 'function') {
                renderCaveatsList();
            }
            if (typeof renderActiveCaveatsList === 'function') {
                renderActiveCaveatsList();
            }
            if (typeof renderCaveatsTable === 'function') {
                renderCaveatsTable();
            }
            if (typeof updateStats === 'function') {
                updateStats();
            }
            
            console.log('Loaded', data.data.length, 'caveats from backend');
        } else {
            console.error('Failed to load caveats:', data.error);
        }
    })
    .catch(error => {
        console.error('Error loading caveats:', error);
    });
}

// Generate Acknowledgement Sheet PDF function
function generateAcknowledgementPDF(action, caveatData = null) {
    try {
        // Check if jsPDF is available
        if (typeof window.jspdf === 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'PDF Library Not Available',
                text: 'jsPDF library is not loaded. Please refresh the page and try again.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Show loading state
        const button = action === 'place' ? 
            document.getElementById('generate-acknowledgement') : 
            document.getElementById('generate-lift-acknowledgement');
        
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Generating PDF...';
        }

        // Collect data based on action type
        let data = {};
        let documentTitle = '';
        
        if (action === 'place') {
            // Check if we have an existing caveat selected
            const existingCaveat = window.selectedExistingCaveat;
            
            if (existingCaveat) {
                // Use existing caveat data for PDF generation
                data = {
                    action: 'View/Edit',
                    caveatNumber: existingCaveat.caveat_number || '',
                    encumbranceType: existingCaveat.encumbrance_type || '',
                    instrumentType: existingCaveat.instrument_type || '',
                    fileNumber: existingCaveat.file_number_kangis || existingCaveat.file_number_mlsf || existingCaveat.file_number_new_kangis || existingCaveat.file_number || '',
                    location: existingCaveat.location || '',
                    petitioner: existingCaveat.petitioner || '',
                    petitionerPhone: existingCaveat.petitioner_phone || '',
                    grantor: existingCaveat.grantor || '',
                    grantee: existingCaveat.grantee_name || '',
                    serialNo: existingCaveat.serial_no || '',
                    pageNo: existingCaveat.page_no || '',
                    volumeNo: existingCaveat.volume_no || '',
                    registrationNumber: existingCaveat.registration_number || '',
                    startDate: existingCaveat.start_date || '',
                    releaseDate: existingCaveat.release_date || '',
                    instructions: existingCaveat.instructions || '',
                    remarks: existingCaveat.remarks || '',
                    createdBy: document.getElementById('created-by-first-name')?.value || 'System',
                    dateCreated: new Date().toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    })
                };
                documentTitle = 'Caveat Record Acknowledgement';
            } else {
                // Get data from form fields (this shouldn't happen since button should be disabled)
                data = {
                    action: 'Place',
                    caveatNumber: document.getElementById('caveat-number')?.value || 'CAV-' + new Date().getFullYear() + '-' + String(Math.floor(Math.random() * 9999) + 1).padStart(4, '0'),
                    encumbranceType: document.getElementById('encumbrance-type')?.value || '',
                    instrumentType: document.getElementById('instrument-type')?.selectedOptions[0]?.text || '',
                    fileNumber: getFileNumberFromForm(),
                    location: document.getElementById('location')?.value || '',
                    petitioner: document.getElementById('petitioner')?.value || '',
                    petitionerPhone: document.getElementById('petitioner-phone')?.value || '',
                    grantor: document.getElementById('grantor')?.value || '',
                    grantee: document.getElementById('grantee')?.value || '',
                    serialNo: document.querySelector('#tab-place #serial-no')?.value || '',
                    pageNo: document.querySelector('#tab-place #page-no')?.value || '',
                    volumeNo: document.querySelector('#tab-place #volume-no')?.value || '',
                    registrationNumber: document.querySelector('#tab-place #registration-number')?.textContent !== 'Enter Serial No. and Volume No. to generate' 
                        ? document.querySelector('#tab-place #registration-number')?.textContent : '',
                    startDate: document.getElementById('start-date')?.value || '',
                    releaseDate: document.getElementById('release-date')?.value || '',
                    instructions: document.getElementById('instructions')?.value || '',
                    remarks: document.getElementById('remarks')?.value || '',
                    createdBy: document.getElementById('created-by-first-name')?.value || 'System',
                    dateCreated: new Date().toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    })
                };
                documentTitle = 'Caveat Placement Acknowledgement';
            }
        } else if (action === 'lift' && caveatData) {
            // Get data from selected caveat and lift form
            data = {
                action: 'Lift',
                caveatNumber: caveatData.caveat_number || '',
                encumbranceType: caveatData.encumbrance_type || '',
                instrumentType: caveatData.instrument_type || '',
                fileNumber: caveatData.file_number_kangis || caveatData.file_number_mlsf || caveatData.file_number_new_kangis || '',
                location: caveatData.location || '',
                petitioner: caveatData.petitioner || '',
                petitionerPhone: caveatData.petitioner_phone || '',
                grantor: caveatData.grantor || '',
                grantee: caveatData.grantee_name || '',
                serialNo: caveatData.serial_no || '',
                pageNo: caveatData.page_no || '',
                volumeNo: caveatData.volume_no || '',
                registrationNumber: caveatData.registration_number || '',
                startDate: caveatData.start_date || '',
                releaseDate: document.getElementById('lift-release-date')?.value || '',
                instructions: caveatData.instructions || '',
                remarks: caveatData.remarks || '',
                liftInstructions: document.getElementById('lift-instructions')?.value || '',
                liftRemarks: document.getElementById('lift-remarks')?.value || '',
                createdBy: document.getElementById('created-by-first-name')?.value || 'System',
                dateCreated: new Date().toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })
            };
            documentTitle = 'Caveat Lifting Acknowledgement';
        }

        // Create PDF document
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Set up document properties
        doc.setProperties({
            title: documentTitle,
            subject: `${data.action} Caveat Acknowledgement Sheet`,
            author: 'Kano State Land Registry',
            creator: 'Caveat Management System'
        });

        // Load logos as base64 via fetch for reliability
        function loadImage(url) {
            return fetch(url)
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.blob();
                })
                .then(function(blob) {
                    return new Promise(function(resolve) {
                        var reader = new FileReader();
                        reader.onloadend = function() { resolve(reader.result); };
                        reader.onerror = function() { resolve(null); };
                        reader.readAsDataURL(blob);
                    });
                })
                .catch(function(e) {
                    console.warn('Failed to load image:', url, e);
                    return null;
                });
        }

        var headerLeftLogoUrl = '/assets/logo/ministry1.jpg';
        var headerRightLogoUrl = '/assets/logo/ministry2.jpeg';
        var footerLeftLogoUrl = '/assets/logo/logo.png';
        var footerRightLogoUrl = '/assets/logo/las.jpg';

        Promise.all([
            loadImage(headerLeftLogoUrl),
            loadImage(headerRightLogoUrl),
            loadImage(footerLeftLogoUrl),
            loadImage(footerRightLogoUrl)
        ]).then(function(logos) {
            var headerLeftLogo = logos[0];
            var headerRightLogo = logos[1];
            var footerLeftLogo = logos[2];
            var footerRightLogo = logos[3];

            var leftMargin = 25;
            var rightMargin = 185;
            var lineHeight = 8;
            var headerLogoSize = 22;

            // Header logos (placed at page edges, away from centered text)
            if (headerLeftLogo) {
                doc.addImage(headerLeftLogo, 'JPEG', 10, 8, headerLogoSize, headerLogoSize);
            }
            if (headerRightLogo) {
                doc.addImage(headerRightLogo, 'JPEG', 200 - headerLogoSize, 8, headerLogoSize, headerLogoSize);
            }

            // Header text (centered between logos)
            doc.setFontSize(20);
            doc.setFont('helvetica', 'bold');
            doc.text('KANO STATE GOVERNMENT', 105, 16, { align: 'center' });

            doc.setFontSize(14);
            doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', 105, 23, { align: 'center' });

            doc.setFontSize(12);
            doc.text('DEEDS DEPARTMENT', 105, 30, { align: 'center' });

            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            doc.text(documentTitle.toUpperCase(), 105, 42, { align: 'center' });

            // Line under header
            doc.setLineWidth(0.5);
            doc.line(20, 47, 190, 47);

            // Document content
            var yPosition = 58;

        // Helper function to add a field
        function addField(label, value, isBold = false) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            // include a trailing space so value never touches the colon
            doc.text(label + ': ', leftMargin, yPosition);
            
            doc.setFont('helvetica', isBold ? 'bold' : 'normal');
            doc.setFontSize(10);
            const labelWidth = doc.getTextWidth(label + ': ') + 5; // Add a little extra padding
            
            // Handle long text wrapping
            const maxWidth = rightMargin - leftMargin - labelWidth;
            const displayValue = (value && value.toString().trim()) ? value : '-';
            const lines = doc.splitTextToSize(displayValue, maxWidth);
            
            doc.text(lines, leftMargin + labelWidth, yPosition);
            yPosition += lineHeight * lines.length;
            
            return lines.length;
        }

        // Add caveat information
        doc.setFontSize(14);
        doc.setFont('helvetica', 'bold');
        doc.text('CAVEAT INFORMATION', leftMargin, yPosition);
        yPosition += lineHeight + 2;
 
        addField('File Number', data.fileNumber, true);
        addField('Caveat Number', data.caveatNumber, true);
        addField('Encumbrance Type', data.encumbranceType);
        addField('Instrument Type', data.instrumentType);
        addField('Location/Property Description', data.location);
        addField('Date Placed', data.startDate ? new Date(data.startDate).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : '');

        yPosition += 5;

        // Parties Information
        doc.setFontSize(14);
        doc.setFont('helvetica', 'bold');
        doc.text('APPLICANT & OWNER', leftMargin, yPosition);
        yPosition += lineHeight + 2;

        addField('Applicant/Solicitor', data.petitioner);
        if (data.petitionerPhone) addField('Applicant/Solicitor Phone', data.petitionerPhone);
        if (data.grantor) addField('Grantor', data.grantor);
        addField('Property Owner', data.grantee);

        yPosition += 5;

        // Instructions section - always display
        doc.setFontSize(14);
        doc.setFont('helvetica', 'bold');
        doc.text('INSTRUCTION(S)', leftMargin, yPosition);
        yPosition += lineHeight + 2;
        
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        const instructionText = (data.instructions && data.instructions.toString().trim()) ? data.instructions : '-';
        const instructionLines = doc.splitTextToSize(instructionText, rightMargin - leftMargin);
        doc.text(instructionLines, leftMargin, yPosition);
        yPosition += lineHeight * instructionLines.length + 5;

        // Other remarks
        if (data.remarks || data.liftInstructions || data.liftRemarks) {
            if (data.remarks) addField('Remarks', data.remarks);
            if (data.liftInstructions) addField('Lifting Instructions', data.liftInstructions);
            if (data.liftRemarks) addField('Lifting Remarks', data.liftRemarks);
        }

        // Add new page if needed
        if (yPosition > 250) {
            doc.addPage();
            yPosition = 30;
        }

        yPosition += 10;

        // Signature lines
        // compute line length dynamically so there is a small gap in the middle
        var sigLineLength = ((rightMargin - leftMargin) / 2) - 10;
        doc.setLineWidth(0.5);
        // draw left signature line
        doc.line(leftMargin, yPosition, leftMargin + sigLineLength, yPosition);
        // draw right signature line
        doc.line(rightMargin - sigLineLength, yPosition, rightMargin, yPosition);
        yPosition += lineHeight;
        // labels centered under each line
        doc.setFontSize(12);
        doc.setFont('helvetica', 'normal');
        doc.text('Applicant', leftMargin + sigLineLength / 2, yPosition, { align: 'center' });
        doc.text('Deeds Director', rightMargin - sigLineLength / 2, yPosition, { align: 'center' });
        yPosition += lineHeight + 5;

        // Add disclaimer note
        yPosition += 3;
        doc.setFontSize(8.5);
        doc.setFont('helvetica', 'bold');
        
        // Calculate 6 months from Date Placed for the expiration note
        var releaseDate = 'TBD';
        if (data.startDate) {
            var placedDate = new Date(data.startDate);
            var expiryDate = new Date(placedDate);
            expiryDate.setMonth(expiryDate.getMonth() + 6);
            releaseDate = expiryDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        var noteText = 'NOTE: CAVEAT WILL BE LIFTED AUTOMATICALLY ON ' + releaseDate.toUpperCase() + ' (AFTER SIX (6) MONTHS EXPIRATION PERIOD)';
        doc.setFont('helvetica', 'bolditalic');
        doc.setTextColor(255, 0, 0);

        // Keep the note on a SINGLE line so "NOTE:" never gets orphaned.
        // Auto-shrink the font size until the whole sentence fits within the
        // printable width (fallback floor of 6pt to keep it legible).
        var availableWidth = rightMargin - leftMargin;
        var noteFontSize = 8.5;
        doc.setFontSize(noteFontSize);
        while (doc.getTextWidth(noteText) > availableWidth && noteFontSize > 6) {
            noteFontSize -= 0.25;
            doc.setFontSize(noteFontSize);
        }

        doc.text(noteText, leftMargin, yPosition);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(0, 0, 0);
        doc.setFontSize(8.5);
        yPosition += lineHeight;



        // Add footer information
            var pageCount = doc.internal.getNumberOfPages();

            for (var i = 1; i <= pageCount; i++) {
                doc.setPage(i);

                // Footer logos
                var footerLogoWidth = 30;
                var footerLogoHeight = 15;
                if (footerLeftLogo) {
                    doc.addImage(footerLeftLogo, 'JPEG', 10, 280, footerLogoWidth, footerLogoHeight);
                }
                if (footerRightLogo) {
                    doc.addImage(footerRightLogo, 'PNG', 200 - footerLogoWidth, 280, footerLogoWidth, footerLogoHeight);
                }

                // Generated-by line placed just above page number
                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                var genText = 'Generated By: ' + (data.createdBy || 'System') + ' On ' + data.dateCreated;
                doc.text(genText, 105, 272, { align: 'center' });

                doc.setFontSize(8);
                doc.setFont('helvetica', 'normal');
                
            }

            // Generate filename and save
            var timestamp = new Date().toISOString().slice(0, 19).replace(/[:-]/g, '');
            var filename = data.action + '_Caveat_Acknowledgement_' + data.caveatNumber + '_' + timestamp + '.pdf';
            doc.save(filename);

            Swal.fire({
                icon: 'success',
                title: 'PDF Generated Successfully!',
                text: documentTitle + ' has been generated and downloaded.',
                confirmButtonColor: '#3b82f6'
            });

            // Reset button state
            var btn = action === 'place' ?
                document.getElementById('generate-acknowledgement') :
                document.getElementById('generate-lift-acknowledgement');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-download mr-2"></i>Generate Acknowledgement Sheet';
            }
        }).catch(function() {
            // Fallback: generate PDF without logos
            doc.setFontSize(20);
            doc.setFont('helvetica', 'bold');
            doc.text('KANO STATE GOVERNMENT', 105, 20, { align: 'center' });
            doc.setFontSize(14);
            doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', 105, 30, { align: 'center' });
            doc.setFontSize(12);
            doc.text('DEEDS DEPARTMENT', 105, 38, { align: 'center' });

            var timestamp = new Date().toISOString().slice(0, 19).replace(/[:-]/g, '');
            var filename = data.action + '_Caveat_Acknowledgement_' + data.caveatNumber + '_' + timestamp + '.pdf';
            doc.save(filename);

            Swal.fire({
                icon: 'success',
                title: 'PDF Generated Successfully!',
                text: documentTitle + ' has been generated and downloaded.',
                confirmButtonColor: '#3b82f6'
            });

            // Reset button state
            var btn = action === 'place' ?
                document.getElementById('generate-acknowledgement') :
                document.getElementById('generate-lift-acknowledgement');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-download mr-2"></i>Generate Acknowledgement Sheet';
            }
        });

        // Return early — save/success handled inside Promise
        return;

    } catch (error) {
        console.error('Error generating PDF:', error);
        Swal.fire({
            icon: 'error',
            title: 'PDF Generation Failed',
            text: 'An error occurred while generating the PDF. Please try again.',
            confirmButtonColor: '#3b82f6'
        });

        // Reset button state on error
        const button = action === 'place' ? 
            document.getElementById('generate-acknowledgement') : 
            document.getElementById('generate-lift-acknowledgement');
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-download mr-2"></i>Generate Acknowledgement Sheet';
        }
    }
}

// Helper function to get file number from form
function getFileNumberFromForm() {
    const fileNumberInput = document.getElementById('file_number');
    const fileNumberManualInput = document.getElementById('file-number-input');
    const fileNumberValue = document.getElementById('file-number-value');
    
    if (fileNumberInput && fileNumberInput.value) {
        return fileNumberInput.value;
    } else if (fileNumberManualInput && fileNumberManualInput.value) {
        return fileNumberManualInput.value;
    } else if (fileNumberValue && fileNumberValue.textContent && fileNumberValue.textContent !== 'Search and select file number...') {
        return fileNumberValue.textContent;
    }
    
    return '';
}

// Build the Legal Search Comment from current form field values
function buildLegalSearchComment() {
    const container = document.getElementById('legal-search-comment');
    if (!container) return;

    const caveatNumber = (document.getElementById('caveat-number')?.value || '').trim();
    const petitioner = (document.getElementById('petitioner')?.value || '').trim();
    const encumbranceType = (document.getElementById('encumbrance-type')?.value || '').trim();
    const startDateRaw = (document.getElementById('start-date')?.value || '').trim();

    // If no meaningful data yet, show placeholder
    if (!caveatNumber && !petitioner && !encumbranceType && !startDateRaw) {
        container.innerHTML = '<span class="text-gray-400 italic">Auto-generated when form fields are filled</span>';
        return;
    }

    // Format date like "Mar 23, 2026, 05:12 PM"
    let formattedDate = '-';
    if (startDateRaw) {
        const d = new Date(startDateRaw);
        if (!isNaN(d)) {
            formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
                + ', ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        }
    }

    container.innerHTML =
        '<strong>Property Under Caveat</strong><br>' +
        'Caveat ID: ' + (caveatNumber || '-') + '<br>' +
        'Date: ' + formattedDate + '<br>' +
        'By: ' + (petitioner || '-') + '<br>' +
        'Encumbrance Type: ' + (encumbranceType || '-');
}

// Export functions
window.initializeEventListeners = initializeEventListeners;
window.handleCaveatSubmission = handleCaveatSubmission;
window.handleLiftSubmission = handleLiftSubmission;
window.updatePageNumber = updatePageNumber;
window.generateRegistrationNumber = generateRegistrationNumber;
window.testRegistrationNumber = testRegistrationNumber;
window.updateDateCreated = updateDateCreated;
window.validateFormAndUpdateButtons = validateFormAndUpdateButtons;
window.resetCaveatForm = resetCaveatForm;
window.resetLiftForm = resetLiftForm;
window.loadCaveatsData = loadCaveatsData;
window.generateAcknowledgementPDF = generateAcknowledgementPDF;
window.getFileNumberFromForm = getFileNumberFromForm;
window.buildLegalSearchComment = buildLegalSearchComment;

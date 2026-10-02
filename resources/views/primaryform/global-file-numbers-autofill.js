/**
 * Global File Numbers API Auto-Fill Integration
 * Integrates with ST_FILE_NUMBERS_GLOBAL_API to auto-populate form fields
 */

// Global variables
let globalFileNumbers = [];
let currentSelectedFile = null;

/**
 * Initialize the Global File Numbers system
 */
function initializeGlobalFileNumbers() {
    console.log('🚀 Initializing Global File Numbers Auto-Fill System');
    loadPrimaryFileNumbers();
    loadTopPrimaryFileNumbers();
}

/**
 * Load Primary File Numbers from the Global API
 */
async function loadPrimaryFileNumbers() {
    const loadingElement = document.getElementById('primary-file-loading');
    const selectElement = document.getElementById('primary-file-select');
    
    try {
        // Show loading state
        if (loadingElement) {
            loadingElement.classList.remove('hidden');
        }
        if (selectElement) {
            selectElement.disabled = true;
        }
        
        console.log('📡 Fetching Primary File Numbers from Global API...');
        
        // Fetch from Global File Numbers API - exclude USED status for dropdown
        const response = await fetch('/api/file-numbers/st-all?file_no_type=PRIMARY&status=ACTIVE,RESERVED&limit=500&order_by=np_fileno&order_direction=desc');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('📦 Global API Response:', data);
        
        if (data.status === 'success' && data.data) {
            globalFileNumbers = data.data;
            populatePrimaryFileDropdown(data.data);
            console.log(`✅ Loaded ${data.data.length} Primary File Numbers`);
            
            // Show success toast
            showSuccessToast(`Loaded ${data.data.length} primary file numbers`);
        } else {
            throw new Error(data.message || 'Failed to load file numbers');
        }
        
    } catch (error) {
        console.error('❌ Error loading Primary File Numbers:', error);
        showErrorToast('Failed to load file numbers. Please refresh the page.');
    } finally {
        // Hide loading state
        if (loadingElement) {
            loadingElement.classList.add('hidden');
        }
        if (selectElement) {
            selectElement.disabled = false;
        }
    }
}

/**
 * Populate the Primary File dropdown
 */
function populatePrimaryFileDropdown(fileNumbers) {
    const selectElement = document.getElementById('primary-file-select');
    
    if (!selectElement) {
        console.error('❌ Primary file select element not found');
        return;
    }
    
    // Clear existing options except the first (placeholder)
    selectElement.innerHTML = '<option value="">Select a Primary File Number...</option>';
    
    // Group files by land use for better organization
    const groupedFiles = groupFilesByLandUse(fileNumbers);
    
    // Add options for each group
    Object.keys(groupedFiles).forEach(landUse => {
        if (groupedFiles[landUse].length > 0) {
            // Add optgroup for land use
            const optgroup = document.createElement('optgroup');
            optgroup.label = `${landUse} (${groupedFiles[landUse].length} files)`;
            
            groupedFiles[landUse].forEach(file => {
                const option = document.createElement('option');
                option.value = file.id;
                option.textContent = file.np_fileno || file.fileno || `File #${file.id}`;
                option.dataset.fileData = JSON.stringify(file);
                optgroup.appendChild(option);
            });
            
            selectElement.appendChild(optgroup);
        }
    });
    
    console.log(`✅ Populated dropdown with ${fileNumbers.length} primary file numbers`);
}

/**
 * Group file numbers by land use
 */
function groupFilesByLandUse(fileNumbers) {
    const groups = {
        'COMMERCIAL': [],
        'RESIDENTIAL': [],
        'INDUSTRIAL': [],
        'MIXED-USE': [],
        'OTHER': []
    };
    
    fileNumbers.forEach(file => {
        const landUse = (file.land_use || 'OTHER').toUpperCase();
        if (groups[landUse]) {
            groups[landUse].push(file);
        } else {
            groups['OTHER'].push(file);
        }
    });
    
    return groups;
}

/**
 * Get applicant display name from file data
 */
function getApplicantDisplayName(file) {
    // Use the pre-built display_name from API if available
    if (file.display_name && file.display_name.trim()) {
        return file.display_name.trim();
    }
    
    // Fallback to building name from individual fields
    if (file.applicant_type === 'corporate' && file.corporate_name) {
        return file.corporate_name;
    } else if (file.applicant_type === 'individual') {
        const names = [file.first_name, file.middle_name, file.surname].filter(Boolean);
        return names.length > 0 ? names.join(' ') : 'Individual';
    } else if (file.applicant_type === 'multiple' && file.multiple_owners_names) {
        return file.multiple_owners_names.substring(0, 50) + '...';
    }
    return 'No Name';
}

/**
 * Handle Primary File Number selection
 */
function handlePrimaryFileSelection(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    if (!selectedOption.value) {
        // No selection, clear all fields
        clearAllAutoFilledFields();
        currentSelectedFile = null;
        console.log('🔄 Cleared primary file selection');
        return;
    }
    
    try {
        const fileData = JSON.parse(selectedOption.dataset.fileData);
        currentSelectedFile = fileData;
        
        console.log('📋 Selected Primary File:', fileData);
        
        // Update display elements
        updateSelectedFileDisplay(fileData);
        
        // Auto-fill all form fields
        autoFillAllFormFields(fileData);
        
        // Show success message
        showSuccessToast(`Primary file ${fileData.np_fileno || fileData.fileno} selected and form auto-filled`);
        
    } catch (error) {
        console.error('❌ Error handling primary file selection:', error);
        showErrorToast('Error processing selected file number');
    }
}

/**
 * Update selected file display
 */
function updateSelectedFileDisplay(fileData) {
    // Update selected file display
    const displayContainer = document.getElementById('selected-file-display');
    const fileNumberDisplay = document.getElementById('selected-file-number');
    
    if (displayContainer && fileNumberDisplay) {
        fileNumberDisplay.textContent = fileData.fileno || '-';
        displayContainer.classList.remove('hidden');
    }
    
    // Update the applied file number field in the File Number Information card
    const appliedFileNumberField = document.getElementById('applied-file-number');
    if (appliedFileNumberField) {
        appliedFileNumberField.value = fileData.fileno || '';
        appliedFileNumberField.classList.remove('bg-gray-50');
        appliedFileNumberField.classList.add('bg-green-50');
    }
    
    // Update components display
    const componentsDisplay = document.getElementById('file-components-display');
    if (componentsDisplay) {
        document.getElementById('display-np-fileno').textContent = fileData.np_fileno || '-';
        document.getElementById('display-fileno').textContent = fileData.fileno || '-';
        document.getElementById('display-land-use').textContent = fileData.land_use || '-';
        document.getElementById('display-applicant').textContent = getApplicantDisplayName(fileData);
        componentsDisplay.classList.remove('hidden');
    }
}

/**
 * Auto-fill all form fields based on selected file data
 */
function autoFillAllFormFields(fileData) {
    console.log('🔄 Auto-filling all form fields with:', fileData);
    
    // Update centralized hidden fields for backend submission
    updateMainFormField('np_fileno', fileData.np_fileno);
    updateMainFormField('fileno', fileData.fileno);
    updateMainFormField('land_use', fileData.land_use);
    updateMainFormField('primary_file_id', fileData.id);
    updateMainFormField('applicant_type', fileData.applicant_type); // Fixed: applicant_type not applicantType
    updateMainFormField('tracking_id', fileData.tra); // Use 'tra' field from API
    updateMainFormField('applied_file_number', fileData.fileno);
    updateMainFormField('selected_file_data', JSON.stringify(fileData));
    updateMainFormField('selected_file_id', fileData.id);
    updateMainFormField('selected_file_type', fileData.file_no_type || 'PRIMARY');
    updateMainFormField('ksip_ref_no', fileData.ksip_ref_no);
    updateFormField('ksipRefNoPrimary', fileData.ksip_ref_no);
    
    // Update land use badge
    updateLandUseBadge(fileData.land_use);
    
    // Show correct applicant type section
    showApplicantTypeSection(fileData.applicant_type);
    
    // Auto-fill applicant information
    autoFillApplicantFields(fileData);
    
    // Fetch additional application details (property info, scheme number, etc.)
    fetchApplicationDetails(fileData.fileno || fileData.np_fileno);
    
    // Update draft locator display if available
    updateDraftLocatorDisplay(fileData.np_fileno || fileData.fileno);
    
    console.log('✅ All form fields auto-filled successfully');
}

/**
 * Update hidden form field (both by ID and by name)
 */
function updateHiddenField(fieldId, value) {
    // Update by ID
    const field = document.getElementById(fieldId);
    if (field) {
        field.value = value || '';
    }
    
    // Also update by name attribute for main form fields
    const fieldName = fieldId.replace('hidden-', '');
    const namedField = document.querySelector(`input[name="${fieldName}"]`);
    if (namedField) {
        namedField.value = value || '';
    }
    
    console.log(`📝 Updated ${fieldId} / ${fieldName}: ${value || 'empty'}`);
}

/**
 * Update land use badge
 */
function updateLandUseBadge(landUse) {
    const badge = document.getElementById('land-use-badge');
    if (badge && landUse) {
        badge.textContent = landUse;
        badge.className = 'bg-green-100 text-green-800 px-2 py-1 rounded text-sm';
    }
}

/**
 * Auto-fill applicant fields based on file data
 */
function autoFillApplicantFields(fileData) {
    if (!fileData.applicant_type) return;
    
    // Set applicant type in both text input and hidden field
    const applicantTypeInput = document.getElementById('applicantType');
    const hiddenApplicantType = document.getElementById('hidden-applicant-type');
    const hiddenApplicantTypeByName = document.querySelector('input[name="applicant_type"]');
    
    const applicantType = fileData.applicant_type.toLowerCase();
    
    if (applicantTypeInput) {
        applicantTypeInput.value = applicantType;
        console.log(`📝 Set applicantType input: ${applicantType}`);
    }
    
    if (hiddenApplicantType) {
        hiddenApplicantType.value = applicantType;
        console.log(`📝 Set hidden-applicant-type: ${applicantType}`);
    }
    
    if (hiddenApplicantTypeByName) {
        hiddenApplicantTypeByName.value = applicantType;
        console.log(`📝 Set applicantType by name: ${applicantType}`);
    }
    
    // Show appropriate form sections
    showApplicantTypeSection(applicantType);
    
    // Update the top display
    const topApplicantTypeDisplay = document.getElementById('top-applicant-type-display');
    if (topApplicantTypeDisplay) {
        topApplicantTypeDisplay.textContent = fileData.applicant_type;
        console.log(`📝 Updated top applicant type display: ${fileData.applicant_type}`);
    }
    
    console.log(`📝 Set applicant type: ${fileData.applicant_type}`);
    
    // Wait a moment for form sections to be shown, then fill fields
    setTimeout(() => {
        // Clear ALL applicant fields first to prevent cross-contamination
        const allApplicantFields = [
            'applicantTitle', 'applicantName', 'applicantMiddleName', 'applicantSurname',
            'corporateName', 'rcNumber'
        ];
        allApplicantFields.forEach(id => {
            const field = document.getElementById(id);
            if (field) field.value = '';
        });

        // Also clear multiple owners fields
        const multipleOwnersInputs = document.querySelectorAll('input[name="multiple_owners_names[]"]');
        multipleOwnersInputs.forEach(input => {
            input.value = '';
        });

        // Fill individual fields
        if (fileData.applicant_type?.toLowerCase() === 'individual') {
            updateFormField('applicantTitle', fileData.applicant_title);
            updateFormField('applicantName', fileData.first_name); // Note: ID is 'applicantName' not 'applicantFirstName'
            updateFormField('applicantMiddleName', fileData.middle_name);
            updateFormField('applicantSurname', fileData.surname);
            
            // Update the name preview display
            const fullName = [fileData.applicant_title, fileData.first_name, fileData.middle_name, fileData.surname]
                .filter(Boolean)
                .join(' ')
                .toUpperCase();
            const namePreview = document.getElementById('applicantNamePreview');
            if (namePreview) {
                namePreview.value = fullName;
            }
            
            console.log(`📝 Populated individual applicant fields - Title: ${fileData.applicant_title}, Name: ${fileData.first_name}`);
        }
        
        // Fill corporate fields
        else if (fileData.applicant_type?.toLowerCase() === 'corporate') {
            updateFormField('corporateName', fileData.corporate_name);
            updateFormField('rcNumber', fileData.rc_number);
            
            // Update the display fields
            const corporateNameDisplay = document.getElementById('corporateNameDisplay');
            const rcNumberDisplay = document.getElementById('rcNumberDisplay');
            if (corporateNameDisplay) {
                corporateNameDisplay.textContent = fileData.corporate_name || '-';
            }
            if (rcNumberDisplay) {
                rcNumberDisplay.textContent = fileData.rc_number || '-';
            }
            
            console.log(`📝 Populated corporate applicant fields - Corporate Name: ${fileData.corporate_name}`);
        }
        
        // Fill multiple owners field
        else if (fileData.applicant_type?.toLowerCase() === 'multiple' && fileData.multiple_owners_names) {
            updateMultipleOwnersFields(fileData.multiple_owners_names);
            
            console.log(`📝 Populated multiple owners fields`);
        }
    }, 100); // Small delay to ensure form sections are visible
}

/**
 * Update form field if it exists
 */
function updateFormField(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (field && value) {
        // Handle select elements differently
        if (field.tagName === 'SELECT') {
            // For select elements, try exact match first
            let optionExists = Array.from(field.options).some(option => option.value === value);
            
            // If exact match fails, try case-insensitive match and with/without periods
            if (!optionExists && fieldId === 'applicantTitle') {
                const normalizedValue = normalizeTitle(value);
                const matchingOption = Array.from(field.options).find(option => {
                    const normalizedOptionValue = normalizeTitle(option.value);
                    return normalizedOptionValue === normalizedValue;
                });
                
                if (matchingOption) {
                    field.value = matchingOption.value;
                    console.log(`📝 Updated SELECT ${fieldId}: ${value} → ${matchingOption.value} (normalized match)`);
                    optionExists = true;
                } else {
                    console.warn(`⚠️ Option value "${value}" not found in select#${fieldId}. Available options:`, 
                        Array.from(field.options).map(o => o.value));
                }
            } else if (optionExists) {
                field.value = value;
                console.log(`📝 Updated SELECT ${fieldId}: ${value}`);
            }
        } else {
            // For input/textarea elements
            field.value = value;
            console.log(`📝 Updated INPUT ${fieldId}: ${value}`);
        }
        
        // Trigger input event to update any dependent displays
        field.dispatchEvent(new Event('input', { bubbles: true }));
        
        // Also trigger change event for form validation/updates
        field.dispatchEvent(new Event('change', { bubbles: true }));
    } else if (field && !value) {
        console.log(`⚠️ No value provided for ${fieldId}`);
    } else if (!field) {
        console.warn(`⚠️ Field not found: ${fieldId}`);
    }
}

/**
 * Normalize title for matching (handle case differences and periods)
 */
function normalizeTitle(title) {
    if (!title) return '';
    // Convert to lowercase, trim, and remove periods
    return title.toLowerCase().trim().replace(/\./g, '');
}

/**
 * Update multiple owners fields
 */
function updateMultipleOwnersFields(multipleOwnersNames) {
    if (!multipleOwnersNames) return;
    
    // Find the first multiple owners input field
    const firstOwnerInput = document.querySelector('input[name="multiple_owners_names[]"]');
    if (firstOwnerInput) {
        firstOwnerInput.value = multipleOwnersNames;
        
        // Trigger input event to update displays
        firstOwnerInput.dispatchEvent(new Event('input', { bubbles: true }));
        firstOwnerInput.dispatchEvent(new Event('change', { bubbles: true }));
        
        console.log(`📝 Updated multiple owners: ${multipleOwnersNames}`);
    }
}

/**
 * Update main form hidden field by name
 */
function updateMainFormField(fieldName, value) {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) {
        field.value = value || '';
        console.log(`📝 Updated main form ${fieldName}: ${value || 'empty'}`);
    }
}

/**
 * Fetch additional application details including property information
 */
async function fetchApplicationDetails(fileno) {
    if (!fileno) {
        console.log('⚠️ No file number provided for application details fetch');
        return;
    }
    
    try {
        console.log('📡 Fetching application details for:', fileno);
        
        const response = await fetch(`/api/application-details/${encodeURIComponent(fileno)}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            console.log('📦 Application details received:', data.data);
            
            // Fill property fields
            updateFormField('schemeNumber', data.data.scheme_no);
            updateFormField('propertyStreetName', data.data.property_street_name);
            updateFormField('propertyHouseNo', data.data.property_house_no);
            updateFormField('propertyPlotNo', data.data.property_plot_no);
            updateFormField('propertyDistrict', data.data.property_district);
            
            // Update form fields by name as well
            updateMainFormField('scheme_no', data.data.scheme_no);
            updateMainFormField('property_street_name', data.data.property_street_name);
            updateMainFormField('property_house_no', data.data.property_house_no);
            updateMainFormField('property_plot_no', data.data.property_plot_no);
            updateMainFormField('property_district', data.data.property_district);
            updateFormField('ksipRefNoPrimary', data.data.ksip_ref_no);
            updateMainFormField('ksip_ref_no', data.data.ksip_ref_no);
            
            // Handle state and LGA dropdowns
            if (data.data.property_state) {
                const stateSelect = document.getElementById('propertyState');
                if (stateSelect) {
                    stateSelect.value = data.data.property_state;
                    stateSelect.dispatchEvent(new Event('change'));
                    
                    // Wait a moment for LGA options to load, then set LGA
                    setTimeout(() => {
                        if (data.data.property_lga) {
                            const lgaSelect = document.getElementById('propertyLga');
                            if (lgaSelect) {
                                lgaSelect.value = data.data.property_lga;
                                lgaSelect.dispatchEvent(new Event('change'));
                                updateMainFormField('property_lga', data.data.property_lga);
                                updateMainFormField('property_state', data.data.property_state);
                            }
                        }
                    }, 500);
                }
            }
            
            console.log('✅ Property fields auto-filled from application details');
            
        } else {
            console.log('ℹ️ No additional application details found for file:', fileno);
        }
        
    } catch (error) {
        console.error('❌ Error fetching application details:', error);
    }
}

/**
 * Update draft locator display
 */
function updateDraftLocatorDisplay(fileNumber) {
    const locatorDisplay = document.getElementById('draftLocatorCurrentId');
    if (locatorDisplay && fileNumber) {
        locatorDisplay.textContent = fileNumber;
    }
}

/**
 * Clear all auto-filled fields
 */
function clearAllAutoFilledFields() {
    // Clear centralized hidden fields
    updateMainFormField('np_fileno', '');
    updateMainFormField('fileno', '');
    updateMainFormField('land_use', '');
    updateMainFormField('primary_file_id', '');
    updateMainFormField('applicant_type', '');
    updateMainFormField('tracking_id', '');
    updateMainFormField('applied_file_number', '');
    updateMainFormField('selected_file_data', '');
    updateMainFormField('selected_file_id', '');
    updateMainFormField('selected_file_type', '');
    updateMainFormField('ksip_ref_no', '');
    
    // Clear top dropdown
    const topSelect = document.getElementById('top-primary-file-select');
    if (topSelect) {
        topSelect.value = '';
    }
    
    // Clear other dropdown
    const primarySelect = document.getElementById('primary-file-select');
    if (primarySelect) {
        primarySelect.value = '';
    }
    
    // Clear land use badge
    const badge = document.getElementById('land-use-badge');
    if (badge) {
        badge.textContent = 'Not Selected';
        badge.className = 'bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm';
    }
    
    // Clear display elements
    const displayContainer = document.getElementById('selected-file-display');
    const componentsDisplay = document.getElementById('file-components-display');
    if (displayContainer) displayContainer.classList.add('hidden');
    if (componentsDisplay) componentsDisplay.classList.add('hidden');
    
    // Clear applied file number field
    const appliedFileNumberField = document.getElementById('applied-file-number');
    if (appliedFileNumberField) {
        appliedFileNumberField.value = '';
        appliedFileNumberField.classList.remove('bg-green-50');
        appliedFileNumberField.classList.add('bg-gray-50');
    }
    
    // Clear applicant type selection
    document.querySelectorAll('input[name="applicantType"]').forEach(radio => {
        radio.checked = false;
    });
    
    // Clear applicant fields
    const applicantFields = [
        'applicantTitle', 'applicantName', 'applicantMiddleName', 'applicantSurname',
        'corporateName', 'rcNumber'
    ];
    
    applicantFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
            // Trigger events to update any dependent displays
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    // Clear file inputs and previews
    const fileInputs = ['photoUpload', 'corporateDocumentUpload'];
    fileInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    // Call individual remove functions if they exist to clear previews
    if (typeof removePhoto === 'function') removePhoto();
    if (typeof removeCorporateDocument === 'function') removeCorporateDocument();
    
    // Clear multiple owners fields
    const multipleOwnersInputs = document.querySelectorAll('input[name="multiple_owners_names[]"]');
    multipleOwnersInputs.forEach(input => {
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const ksipField = document.getElementById('ksipRefNoPrimary');
    if (ksipField) {
        ksipField.value = '';
        ksipField.dispatchEvent(new Event('input', { bubbles: true }));
        ksipField.dispatchEvent(new Event('change', { bubbles: true }));
    }
    
    console.log('🔄 Cleared all auto-filled fields');
}

/**
 * Show success toast message
 */
function showSuccessToast(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        console.log('✅ ' + message);
    }
}

/**
 * Show error toast message
 */
function showErrorToast(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: message,
            timer: 5000,
            showConfirmButton: true,
            toast: true,
            position: 'top-end'
        });
    } else {
        console.error('❌ ' + message);
        alert(message);
    }
}

/**
 * Show the correct applicant type section based on API data
 */
function showApplicantTypeSection(applicantType) {
    // Hide all applicant sections first
    const individualFields = document.getElementById('individualFields');
    const corporateFields = document.getElementById('corporateFields');
    const multipleOwnersFields = document.getElementById('multipleOwnersFields');
    
    if (individualFields) individualFields.style.display = 'none';
    if (corporateFields) corporateFields.style.display = 'none';
    if (multipleOwnersFields) multipleOwnersFields.style.display = 'none';
    
    // Show the appropriate section based on applicant type (handle both cases)
    const lowerApplicantType = applicantType ? applicantType.toLowerCase() : '';
    switch (lowerApplicantType) {
        case 'individual':
            if (individualFields) {
                individualFields.style.display = 'block';
                console.log('✅ Showing individual applicant fields');
            }
            break;
        case 'corporate':
            if (corporateFields) {
                corporateFields.style.display = 'block';
                console.log('✅ Showing corporate applicant fields');
            }
            break;
        case 'multiple':
            if (multipleOwnersFields) {
                multipleOwnersFields.style.display = 'block';
                console.log('✅ Showing multiple owners applicant fields');
            }
            break;
        default:
            console.log('⚠️ Unknown applicant type:', applicantType);
            // Default to individual if unknown
            if (individualFields) {
                individualFields.style.display = 'block';
                console.log('✅ Defaulting to individual applicant fields');
            }
    }
}

/**
 * Load Primary File Numbers for the top dropdown
 */
async function loadTopPrimaryFileNumbers() {
    const loadingElement = document.getElementById('top-file-loading');
    const selectElement = document.getElementById('top-primary-file-select');
    
    try {
        // Show loading state
        if (loadingElement) {
            loadingElement.classList.remove('hidden');
        }
        if (selectElement) {
            selectElement.disabled = true;
        }
        
        console.log('📡 Fetching Primary File Numbers for top dropdown...');
        
        // Fetch from Global File Numbers API - exclude USED status for dropdown
        const response = await fetch('/api/file-numbers/st-all?file_no_type=PRIMARY&status=ACTIVE,RESERVED&limit=500&order_by=np_fileno&order_direction=desc');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('📦 Top Dropdown API Response:', data);
        
        if (data.status === 'success' && data.data) {
            populateTopFileDropdown(data.data);
            console.log(`✅ Loaded ${data.data.length} Primary File Numbers for top dropdown`);
        } else {
            throw new Error(data.message || 'Failed to load file numbers');
        }
        
    } catch (error) {
        console.error('❌ Error loading Primary File Numbers for top dropdown:', error);
        showErrorToast('Failed to load file numbers. Please refresh the page.');
    } finally {
        // Hide loading state
        if (loadingElement) {
            loadingElement.classList.add('hidden');
        }
        if (selectElement) {
            selectElement.disabled = false;
        }
    }
}

/**
 * Populate the top dropdown with file numbers
 */
function populateTopFileDropdown(fileNumbers) {
    const selectElement = document.getElementById('top-primary-file-select');
    
    if (!selectElement) {
        console.error('❌ Top primary file select element not found');
        return;
    }
    
    // Clear existing options except the first (placeholder)
    selectElement.innerHTML = '<option value="">🔍 Select a Primary File Number to begin...</option>';
    
    // Group files by land use for better organization
    const groupedFiles = groupFilesByLandUse(fileNumbers);
    
    // Add options for each group
    Object.keys(groupedFiles).forEach(landUse => {
        if (groupedFiles[landUse].length > 0) {
            // Add optgroup for land use
            const optgroup = document.createElement('optgroup');
            optgroup.label = `${landUse} (${groupedFiles[landUse].length} files)`;
            
            groupedFiles[landUse].forEach(file => {
                const option = document.createElement('option');
                option.value = file.id;
                option.textContent = file.np_fileno || file.fileno || `File #${file.id}`;
                option.dataset.fileData = JSON.stringify(file);
                optgroup.appendChild(option);
            });
            
            selectElement.appendChild(optgroup);
        }
    });
    
    console.log(`✅ Populated top dropdown with ${fileNumbers.length} primary file numbers`);
}

/**
 * Handle Top File Number selection
 */
function handleTopFileSelection(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    if (!selectedOption.value) {
        // No selection, hide all cards and clear fields
        hideAllCards();
        clearAllAutoFilledFields();
        currentSelectedFile = null;
        console.log('🔄 Cleared top file selection and hid cards');
        return;
    }
    
    try {
        const fileData = JSON.parse(selectedOption.dataset.fileData);
        currentSelectedFile = fileData;
        
        console.log('📋 Selected Primary File from Top:', fileData);
        
        // Update top selection preview
        updateTopSelectionPreview(fileData);
        
        // Show all the hidden cards now that we have a selection
        showAllCards();
        
        // Auto-fill all form fields
        autoFillAllFormFields(fileData);
        
        // Also update the other dropdowns to match this selection
        syncOtherDropdowns(fileData);
        
        // Show success message
        showSuccessToast(`Primary file ${fileData.np_fileno || fileData.fileno} selected and all form sections revealed`);
        
    } catch (error) {
        console.error('❌ Error handling top file selection:', error);
        showErrorToast('Error processing selected file number');
    }
}

/**
 * Update top selection preview
 */
function updateTopSelectionPreview(fileData) {
    const previewContainer = document.getElementById('top-selection-preview');
    const fileDisplay = document.getElementById('top-selected-file-display');
    const filenoDisplay = document.getElementById('top-fileno-display');
    const landUseDisplay = document.getElementById('top-land-use-display');
    const applicantDisplay = document.getElementById('top-applicant-display');
    const applicantTypeDisplay = document.getElementById('top-applicant-type-display');
    const trackingIdDisplay = document.getElementById('top-tracking-id-display');
    
    if (previewContainer && fileDisplay && landUseDisplay && applicantDisplay) {
        // Show ST file number (np_fileno) in main display
        fileDisplay.textContent = fileData.np_fileno || '-';
        
        // Show original file number (fileno) in secondary display
        if (filenoDisplay) {
            filenoDisplay.textContent = fileData.fileno || '-';
        }
        
        landUseDisplay.textContent = fileData.land_use || '-';
        applicantDisplay.textContent = getApplicantDisplayName(fileData);
        
        // Update applicant type display
        if (applicantTypeDisplay) {
            const applicantType = fileData.applicant_type || '-';
            applicantTypeDisplay.textContent = applicantType.charAt(0).toUpperCase() + applicantType.slice(1);
        }
        
        // Update tracking ID display (using 'tra' field from API)
        if (trackingIdDisplay) {
            trackingIdDisplay.textContent = fileData.tra || '-';
        }
        
        previewContainer.classList.remove('hidden');
    }
}

/**
 * Show all hidden cards
 */
function showAllCards() {
    const applicantDetailsSection = document.getElementById('applicant-details-section');
    
    if (applicantDetailsSection) {
        applicantDetailsSection.classList.remove('hidden');
        console.log('✅ Showed applicant details section');
    }
}

/**
 * Hide all cards
 */
function hideAllCards() {
    const applicantDetailsSection = document.getElementById('applicant-details-section');
    const topPreview = document.getElementById('top-selection-preview');
    
    if (applicantDetailsSection) {
        applicantDetailsSection.classList.add('hidden');
        console.log('🔄 Hid applicant details section');
    }
    
    if (topPreview) {
        topPreview.classList.add('hidden');
        console.log('🔄 Hid top selection preview');
    }
    
    // Also hide all applicant type sections
    hideAllApplicantTypeSections();
}

/**
 * Hide all applicant type sections
 */
function hideAllApplicantTypeSections() {
    const individualFields = document.getElementById('individualFields');
    const corporateFields = document.getElementById('corporateFields');
    const multipleOwnersFields = document.getElementById('multipleOwnersFields');
    
    if (individualFields) {
        individualFields.style.display = 'none';
        console.log('🔄 Hid individual applicant fields');
    }
    if (corporateFields) {
        corporateFields.style.display = 'none';
        console.log('🔄 Hid corporate applicant fields');
    }
    if (multipleOwnersFields) {
        multipleOwnersFields.style.display = 'none';
        console.log('🔄 Hid multiple owners applicant fields');
    }
}

/**
 * Sync other dropdowns to match the top selection
 */
function syncOtherDropdowns(fileData) {
    const primaryFileSelect = document.getElementById('primary-file-select');
    
    if (primaryFileSelect) {
        // Find the matching option and select it
        for (let option of primaryFileSelect.options) {
            if (option.value === fileData.id.toString()) {
                primaryFileSelect.value = fileData.id.toString();
                // Also update the display for the existing dropdown
                updateSelectedFileDisplay(fileData);
                break;
            }
        }
    }
}

/**
 * Get currently selected file data
 */
function getCurrentSelectedFile() {
    return currentSelectedFile;
}

/**
 * Refresh file numbers (reload from API)
 */
function refreshPrimaryFileNumbers() {
    console.log('🔄 Refreshing primary file numbers...');
    loadPrimaryFileNumbers();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 DOM loaded, initializing Global File Numbers system...');
    initializeGlobalFileNumbers();
});

// Export functions for global access
window.initializeGlobalFileNumbers = initializeGlobalFileNumbers;
window.handlePrimaryFileSelection = handlePrimaryFileSelection;
window.handleTopFileSelection = handleTopFileSelection;
window.getCurrentSelectedFile = getCurrentSelectedFile;
window.refreshPrimaryFileNumbers = refreshPrimaryFileNumbers;
window.showAllCards = showAllCards;
window.hideAllCards = hideAllCards;
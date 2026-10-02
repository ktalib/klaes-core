/**
 * Caveat Rendering Components
 * Handles rendering of caveat lists, tables, and cards
 */

// Helper function to get file number from caveat data
function getFileNumber(caveat) {
    return caveat.file_number_kangis || caveat.file_number_mlsf || caveat.file_number_new_kangis || caveat.file_number || 'No file number';
}

// A Caveat's contact belongs to the applicant/solicitor (the petitioner). Keep
// it optional in display for historic caveats captured before the column existed.
function getPetitionerPhone(caveat) {
    return String(caveat.petitioner_phone || '').trim();
}

// Render caveats list for search
function renderCaveatsList() {
    console.log('renderCaveatsList called');
    
    const container = document.getElementById('search-results');
    const countElement = document.getElementById('search-results-count');
    
    if (!container) {
        console.error('search-results container not found');
        return;
    }
    
    if (!countElement) {
        console.error('search-results-count element not found');
        return;
    }
    
    // Check if caveats data is available
    if (!window.caveats || !Array.isArray(window.caveats)) {
        console.error('Caveats data not available or not an array');
        container.innerHTML = '<div class="p-4 text-center text-gray-500">No caveats data available</div>';
        countElement.textContent = '0';
        return;
    }
    
    const filteredCaveats = filterCaveats();
    console.log('Filtered caveats:', filteredCaveats.length);
    
    countElement.textContent = filteredCaveats.length;
    container.innerHTML = '';
    
    if (filteredCaveats.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-gray-500">No caveats found</div>';
        return;
    }
    
    filteredCaveats.forEach(caveat => {
        const fileNumber = getFileNumber(caveat);
        const petitionerPhone = getPetitionerPhone(caveat);
        const card = document.createElement('div');
        card.className = 'bg-white border rounded-md p-3 cursor-pointer hover:bg-gray-50 transition-colors';
        card.innerHTML = `
            <div class="space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">${caveat.caveat_number || 'N/A'}</span>
                    ${getStatusBadge(caveat.status)}
                </div>
                <p class="text-xs text-gray-600">${fileNumber}</p>
                <p class="text-xs text-gray-600">${caveat.petitioner || 'N/A'}</p>
                ${petitionerPhone ? `<p class="text-xs font-medium text-emerald-700"><i class="fa-solid fa-phone mr-1"></i>${petitionerPhone}</p>` : ''}
            </div>
        `;
        
        card.addEventListener('click', function() {
            populateFormFromCaveat(caveat);
        });
        
        container.appendChild(card);
    });
    
    console.log('renderCaveatsList completed');
}

// Render active caveats list for lifting
function renderActiveCaveatsList() {
    const container = document.getElementById('active-caveats-list');
    const countElement = document.getElementById('active-caveats-count');
    
    if (!container || !countElement) return;
    
    const activeCaveats = (window.caveats || []).filter(c => c.status === 'active');
    const searchTermValue = window.searchTerm || '';
    const filteredCaveats = activeCaveats.filter(caveat => {
        const fileNumber = getFileNumber(caveat);
        return (caveat.caveat_number || '').toLowerCase().includes(searchTermValue) ||
               fileNumber.toLowerCase().includes(searchTermValue) ||
               (caveat.petitioner || '').toLowerCase().includes(searchTermValue) ||
               getPetitionerPhone(caveat).toLowerCase().includes(searchTermValue) ||
               (caveat.grantee_name || '').toLowerCase().includes(searchTermValue) ||
               (caveat.location || '').toLowerCase().includes(searchTermValue) ||
               (caveat.encumbrance_type || '').toLowerCase().includes(searchTermValue);
    });
    
    countElement.textContent = filteredCaveats.length;
    container.innerHTML = '';
    
    filteredCaveats.forEach(caveat => {
        const isSelected = window.selectedCaveat && window.selectedCaveat.id === caveat.id;
        const fileNumber = getFileNumber(caveat);
        const petitionerPhone = getPetitionerPhone(caveat);
        
        const card = document.createElement('div');
        card.className = `border rounded-md p-3 cursor-pointer transition-all ${
            isSelected 
                ? 'bg-blue-50 border-blue-200 ring-2 ring-blue-500 ring-opacity-20' 
                : 'bg-white hover:bg-gray-50 border-gray-200'
        }`;
        
        card.innerHTML = `
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-900">${caveat.caveat_number || 'N/A'}</span>
                    <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">${caveat.status}</span>
                </div>
                <div class="space-y-1 text-xs text-gray-600">
                    <p><span class="font-medium">File:</span> ${fileNumber}</p>
                    <p><span class="font-medium">Type:</span> ${caveat.encumbrance_type || 'N/A'}</p>
                    <p><span class="font-medium">Petitioner:</span> ${caveat.petitioner || 'N/A'}</p>
                    ${petitionerPhone ? `<p><span class="font-medium">Phone:</span> ${petitionerPhone}</p>` : ''}
                    <p><span class="font-medium">Location:</span> ${caveat.location || 'N/A'}</p>
                    <p><span class="font-medium">Date:</span> ${formatDate(caveat.start_date)}</p>
                </div>
                ${isSelected ? `
                    <div class="mt-2 pt-2 border-t border-blue-200">
                        <p class="text-xs text-blue-600 font-medium">
                            <i class="fa-solid fa-check mr-1"></i>
                            Selected for lifting
                        </p>
                    </div>
                ` : ''}
            </div>
        `;
        
        card.addEventListener('click', function() {
            selectCaveatForLifting(caveat);
        });
        
        container.appendChild(card);
    });
}

// Render caveats table for logs
function renderCaveatsTable() {
    console.log('renderCaveatsTable called');
    
    const tbody = document.getElementById('caveats-table-body');
    const countElement = document.getElementById('log-results-count');
    const totalCountElement = document.getElementById('total-caveats-count');
    
    if (!tbody) {
        console.error('caveats-table-body not found');
        return;
    }
    
    if (!countElement) {
        console.error('log-results-count not found');
        return;
    }
    
    // Check if caveats data is available
    if (!window.caveats || !Array.isArray(window.caveats)) {
        console.error('Caveats data not available or not an array');
        tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-gray-500">No caveats data available</td></tr>';
        countElement.textContent = '0';
        if (totalCountElement) totalCountElement.textContent = '0';
        return;
    }
    
    const filteredCaveats = filterCaveats();
    console.log('Filtered caveats for table:', filteredCaveats.length);
    
    countElement.textContent = filteredCaveats.length;
    if (totalCountElement) totalCountElement.textContent = window.caveats.length;
    tbody.innerHTML = '';
    
    if (filteredCaveats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="p-8 text-center text-gray-500">No caveats found matching your criteria</td></tr>';
        return;
    }
    
    filteredCaveats.forEach(caveat => {
        const fileNumber = getFileNumber(caveat);
        const petitionerPhone = getPetitionerPhone(caveat);
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 cursor-pointer';
        row.innerHTML = `
            <td class="p-3 text-sm font-medium text-gray-900">${caveat.caveat_number || 'N/A'}</td>
            <td class="p-3 text-sm text-gray-500">${fileNumber}</td>
            <td class="p-3 text-sm text-gray-500">${caveat.petitioner || 'N/A'}${petitionerPhone ? `<div class="mt-1 text-xs font-medium text-emerald-700"><i class="fa-solid fa-phone mr-1"></i>${petitionerPhone}</div>` : ''}</td>
            <td class="p-3 text-sm text-gray-500">${caveat.grantee_name || 'N/A'}</td>
            <td class="p-3 text-sm text-gray-500">${caveat.encumbrance_type || 'N/A'}</td>
            <td class="p-3">${getStatusBadge(caveat.status)}</td>
            <td class="p-3 text-sm text-gray-500">${formatDate(caveat.start_date)}</td>
            <td class="p-3 text-sm text-gray-500">${caveat.release_date ? formatDate(caveat.release_date) : '-'}</td>
            <td class="p-3 text-sm font-medium">
                <div class="flex space-x-2">
                    <button class="text-blue-600 hover:text-blue-900 transition-colors" onclick="viewCaveatDetails('${caveat.id}')" title="View caveat details">
                        <i class="fa-solid fa-eye mr-1"></i>View
                    </button>
                    <button class="text-green-600 hover:text-green-900 transition-colors" onclick="editCaveatFromTable('${caveat.id}')" title="Edit caveat">
                        <i class="fa-solid fa-edit mr-1"></i>Edit
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    console.log('renderCaveatsTable completed');
}

// Helper functions
function filterCaveats() {
    // Use window.caveats to ensure we're accessing the global variable
    const caveatsData = window.caveats || [];
    const searchTermValue = window.searchTerm || "";
    const statusFilterValue = window.statusFilter || "all";
    
    console.log('Filtering caveats:', {
        totalCaveats: caveatsData.length,
        searchTerm: searchTermValue,
        statusFilter: statusFilterValue
    });
    
    return caveatsData.filter(caveat => {
        const fileNumber = getFileNumber(caveat);
        const matchesSearch = !searchTermValue || 
            (caveat.caveat_number || '').toLowerCase().includes(searchTermValue) ||
            fileNumber.toLowerCase().includes(searchTermValue) ||
            (caveat.file_number_kangis || '').toLowerCase().includes(searchTermValue) ||
            (caveat.file_number_mlsf || '').toLowerCase().includes(searchTermValue) ||
            (caveat.file_number_new_kangis || '').toLowerCase().includes(searchTermValue) ||
            (caveat.petitioner || '').toLowerCase().includes(searchTermValue) ||
            getPetitionerPhone(caveat).toLowerCase().includes(searchTermValue) ||
            (caveat.grantee_name || '').toLowerCase().includes(searchTermValue) ||
            (caveat.location || '').toLowerCase().includes(searchTermValue) ||
            (caveat.encumbrance_type || '').toLowerCase().includes(searchTermValue) ||
            (caveat.registration_number || '').toLowerCase().includes(searchTermValue);
        
        const matchesStatus = statusFilterValue === 'all' || caveat.status === statusFilterValue;
        
        return matchesSearch && matchesStatus;
    });
}

function getStatusBadge(status) {
    const statusClasses = {
        'active': 'badge-active',
        'released': 'badge-released', 
        'lifted': 'badge-lifted',
        'expired': 'badge-expired',
        'draft': 'badge-draft'
    };
    
    const statusIcons = {
        'active': 'fa-solid fa-circle-check',
        'released': 'fa-solid fa-file-check',
        'lifted': 'fa-solid fa-arrow-up',
        'expired': 'fa-solid fa-clock',
        'draft': 'fa-solid fa-file-pen'
    };
    
    const className = statusClasses[status] || 'badge-draft';
    const icon = statusIcons[status] || 'fa-solid fa-circle';
    const displayStatus = status.charAt(0).toUpperCase() + status.slice(1);
    
    return `<span class="status-badge ${className}">
        <i class="${icon}"></i>
        ${displayStatus}
    </span>`;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString();
    } catch (e) {
        return dateString;
    }
}

function selectCaveatForLifting(caveat) {
    window.selectedCaveat = caveat;
    
    // Update UI to show selection
    renderActiveCaveatsList();
    
    // Show selected caveat info
    const selectedCaveatInfo = document.getElementById('selected-caveat-info');
    const noCaveatSelected = document.getElementById('no-caveat-selected');
    const liftingDetails = document.getElementById('lifting-details');
    
    if (selectedCaveatInfo) {
        selectedCaveatInfo.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-semibold text-blue-800 mb-2">Selected Caveat for Lifting</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div><strong>Caveat Number:</strong> ${caveat.caveat_number}</div>
                    <div><strong>Encumbrance Type:</strong> ${caveat.encumbrance_type}</div>
                    <div><strong>Petitioner:</strong> ${caveat.petitioner}</div>
                    <div><strong>Party 2:</strong> ${caveat.grantee_name || 'N/A'}</div>
                    <div><strong>Location:</strong> ${caveat.location}</div>
                    <div><strong>Start Date:</strong> ${formatDate(caveat.start_date)}</div>
                </div>
            </div>
        `;
    }
    
    // Set today's date as default release date
    const releaseDateInput = document.getElementById('lift-release-date');
    if (releaseDateInput) {
        const today = new Date().toISOString().split('T')[0];
        releaseDateInput.value = today;
    }
    
    // Show/hide appropriate sections
    if (noCaveatSelected) noCaveatSelected.classList.add('hidden');
    if (liftingDetails) liftingDetails.classList.remove('hidden');
}

function populateFormFromCaveat(caveat) {
    // Store the selected caveat data globally for PDF generation
    window.selectedExistingCaveat = caveat;
    window.editingCaveatId = caveat.id;
    
    // Populate form fields - updated to match backend field names
    const caveatNumberInput = document.getElementById('caveat-number');
    if (caveatNumberInput) caveatNumberInput.value = caveat.caveat_number || '';

    const createdByInput = document.getElementById('created-by-first-name');
    if (createdByInput) {
        // Use user relationship if available, otherwise fallback to created_by string
        const firstName = caveat.user ? caveat.user.first_name : (caveat.created_by || 'System');
        createdByInput.value = firstName;
    }

    const dateCreatedInput = document.getElementById('date-created');
    if (dateCreatedInput && caveat.created_at) {
        const date = new Date(caveat.created_at);
        dateCreatedInput.value = date.toISOString().slice(0, 16);
    }
    
    const encumbranceTypeSelect = document.getElementById('encumbrance-type');
    if (encumbranceTypeSelect) encumbranceTypeSelect.value = caveat.encumbrance_type || '';
    
    const instrumentTypeSelect = document.getElementById('instrument-type');
    if (instrumentTypeSelect) instrumentTypeSelect.value = caveat.instrument_type_id || '';
    
    const fileNumberInput = document.getElementById('file_number');
    if (fileNumberInput) fileNumberInput.value = getFileNumber(caveat);

    // Backfill the visible file number display
    const fileNumberDisplay = document.getElementById('file_number_display');
    const resolvedFileNo = getFileNumber(caveat);
    if (fileNumberDisplay) {
        fileNumberDisplay.value = (resolvedFileNo && resolvedFileNo !== 'No file number') ? resolvedFileNo : (caveat.file_number || '');
    }

    // Disable file number selection for existing caveats
    const selectFileBtn = document.getElementById('select-fileno-btn');
    if (selectFileBtn) {
        selectFileBtn.disabled = true;
        selectFileBtn.classList.add('opacity-50', 'cursor-not-allowed');
        selectFileBtn.classList.remove('hover:bg-blue-700');
    }

    const locationInput = document.getElementById('location');
    if (locationInput) locationInput.value = caveat.location || '';
    
    const petitionerInput = document.getElementById('petitioner');
    if (petitionerInput) petitionerInput.value = caveat.petitioner || '';

    const petitionerPhoneInput = document.getElementById('petitioner-phone');
    if (petitionerPhoneInput) petitionerPhoneInput.value = getPetitionerPhone(caveat);
    
    const grantorInput = document.getElementById('grantor');
    if (grantorInput) grantorInput.value = caveat.grantor || '';
    
    const granteeInput = document.getElementById('grantee');
    if (granteeInput) granteeInput.value = caveat.grantee_name || '';
    
    const serialNoInput = document.getElementById('serial-no');
    if (serialNoInput) serialNoInput.value = caveat.serial_no || '';
    
    const pageNoInput = document.getElementById('page-no');
    if (pageNoInput) pageNoInput.value = caveat.page_no || '';
    
    const volumeNoInput = document.getElementById('volume-no');
    if (volumeNoInput) volumeNoInput.value = caveat.volume_no || '';
    
    const startDateInput = document.getElementById('start-date');
    if (startDateInput && caveat.start_date) {
        // Convert to datetime-local format
        const date = new Date(caveat.start_date);
        startDateInput.value = date.toISOString().slice(0, 16);
    }
    
    const releaseDateInput = document.getElementById('release-date');
    if (releaseDateInput && caveat.release_date) {
        releaseDateInput.value = caveat.release_date;
    }
    
    const instructionsInput = document.getElementById('instructions');
    if (instructionsInput) instructionsInput.value = caveat.instructions || '';
    
    const remarksInput = document.getElementById('remarks');
    if (remarksInput) remarksInput.value = caveat.remarks || '';
    
    // Update registration number display
    const registrationDisplay = document.getElementById('registration-number');
    if (registrationDisplay && caveat.registration_number) {
        registrationDisplay.textContent = caveat.registration_number;
        registrationDisplay.style.color = '#1f2937';
        registrationDisplay.style.fontWeight = 'bold';
    }
    
    // Update file number selector display
    const fileNumberValue = document.getElementById('file-number-value');
    if (fileNumberValue && caveat.file_number) {
        fileNumberValue.textContent = caveat.file_number;
        fileNumberValue.classList.remove('text-gray-500');
        fileNumberValue.classList.add('text-gray-900');
    }
    
    // Show selected file info (no Change button for existing caveats)
    const selectedInfo = document.getElementById('selected-file-info');
    const displayFileNo = (resolvedFileNo && resolvedFileNo !== 'No file number') ? resolvedFileNo : caveat.file_number;
    if (selectedInfo && displayFileNo) {
        selectedInfo.innerHTML = `
            <div class="flex items-center">
                <span id="selected-file-details">
                    <i class="fa-solid fa-check-circle mr-1 text-green-600"></i>
                    File: <span id="selected-file-number" class="font-bold">${displayFileNo}</span>
                </span>
            </div>
        `;
        selectedInfo.classList.remove('hidden');
    }
    
    // Enable the Generate Acknowledgement Sheet button since this is an existing record
    const generateAcknowledgementBtn = document.getElementById('generate-acknowledgement');
    if (generateAcknowledgementBtn) {
        generateAcknowledgementBtn.disabled = false;
        generateAcknowledgementBtn.classList.remove('cursor-not-allowed', 'opacity-50');
        generateAcknowledgementBtn.classList.add('cursor-pointer');
        generateAcknowledgementBtn.title = 'Generate acknowledgement sheet for this existing caveat record';
    }

    const placeButton = document.getElementById('place-caveat');
    const updateButton = document.getElementById('update-caveat');
    if (placeButton) placeButton.classList.add('hidden');
    if (updateButton) updateButton.classList.remove('hidden');
    
    // Switch to place tab
    if (typeof setActiveTab === 'function') {
        setActiveTab('place');
    }

    // Build legal search comment from caveated_comment field (from pra/CofO_staging)
    const lsContainer = document.getElementById('legal-search-comment');
    if (lsContainer) {
        const caveatedComment = caveat.caveated_comment || null;

        if (caveatedComment) {
            // Use the stored caveated_comment directly
            lsContainer.innerHTML = '<strong>Caveated Comment:</strong><br>' +
                caveatedComment.replace(/\r?\n/g, '<br>');
        } else {
            // Fallback: build from caveat object when no stored comment exists
            let formattedDate = '-';
            if (caveat.start_date) {
                const d = new Date(caveat.start_date);
                if (!isNaN(d)) {
                    formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
                        + ', ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                }
            }

            lsContainer.innerHTML = '<strong>Property Under Caveat</strong><br>' +
                'Caveat ID: ' + (caveat.caveat_number || '-') + '<br>' +
                'Date: ' + formattedDate + '<br>' +
                'By: ' + (caveat.petitioner || '-') + '<br>' +
                'Encumbrance Type: ' + (caveat.encumbrance_type || '-');
        }
    }
}

function viewCaveatDetails(caveatId) {
    console.log('viewCaveatDetails called with ID:', caveatId, 'Type:', typeof caveatId);
    console.log('Available caveats:', window.caveats);
    
    // Convert caveatId to both string and number for comparison
    const caveat = window.caveats.find(c => {
        console.log('Comparing caveat ID:', c.id, 'Type:', typeof c.id, 'with search ID:', caveatId);
        return c.id == caveatId || c.id === caveatId || c.id === parseInt(caveatId) || c.id === caveatId.toString();
    });
    
    if (!caveat) {
        console.error('Caveat not found with ID:', caveatId);
        console.error('Available caveat IDs:', window.caveats.map(c => ({ id: c.id, type: typeof c.id })));
        
        // Show error message to user
        Swal.fire({
            icon: 'error',
            title: 'Caveat Not Found',
            text: 'The selected caveat could not be found. Please refresh the page and try again.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    console.log('Found caveat for viewing:', caveat);
    
    // Show the modal with caveat details
    showCaveatModal(caveat);
}

function showCaveatModal(caveat) {
    const modal = document.getElementById('view-caveat-modal');
    const caveatDetails = document.getElementById('caveat-details');
    
    if (!modal || !caveatDetails) {
        console.error('Modal elements not found');
        return;
    }
    
    const fileNumber = getFileNumber(caveat);
    
    // Populate modal with caveat details
    caveatDetails.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Basic Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Caveat Number</label>
                        <p class="text-sm text-gray-900 font-mono bg-gray-50 p-2 rounded">${caveat.caveat_number || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">File Number</label>
                        <p class="text-sm text-gray-900 font-mono bg-gray-50 p-2 rounded">${fileNumber}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Encumbrance Type</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.encumbrance_type || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Status</label>
                        <div class="mt-1">${getStatusBadge(caveat.status)}</div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Location</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.location || 'N/A'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Parties Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Parties Involved</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Petitioner</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.petitioner || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Applicant/Solicitor Phone</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${getPetitionerPhone(caveat) || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Party 2</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.grantee_name || 'N/A'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Registration Details -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Registration Details</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Registration Number</label>
                        <p class="text-sm text-gray-900 font-mono bg-gray-50 p-2 rounded">${caveat.registration_number || 'N/A'}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Serial No.</label>
                            <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.serial_no || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Page No.</label>
                            <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.page_no || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Volume No.</label>
                            <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.volume_no || 'N/A'}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dates -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Important Dates</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Start Date</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${formatDate(caveat.start_date)}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Release Date</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${caveat.release_date ? formatDate(caveat.release_date) : 'Not set'}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Date Placed</label>
                        <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${formatDate(caveat.created_at)}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Instructions and Remarks -->
        ${caveat.instructions || caveat.remarks ? `
            <div class="mt-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Additional Information</h3>
                ${caveat.instructions ? `
                    <div>
                        <label class="text-sm font-medium text-gray-600">Instructions</label>
                        <div class="text-sm text-gray-900 bg-gray-50 p-3 rounded whitespace-pre-wrap">${caveat.instructions}</div>
                    </div>
                ` : ''}
                ${caveat.remarks ? `
                    <div>
                        <label class="text-sm font-medium text-gray-600">Remarks</label>
                        <div class="text-sm text-gray-900 bg-gray-50 p-3 rounded whitespace-pre-wrap">${caveat.remarks}</div>
                    </div>
                ` : ''}
            </div>
        ` : ''}
    `;
    
    // Store caveat data for edit functionality
    modal.dataset.caveatId = caveat.id;
    
    // Show the modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Add event listeners for modal actions
    setupModalEventListeners(caveat);
}

function setupModalEventListeners(caveat) {
    const modal = document.getElementById('view-caveat-modal');
    const closeBtn = document.getElementById('close-modal');
    const closeBtnX = document.getElementById('close-modal-x');
    const editBtn = document.getElementById('edit-caveat-btn');
    
    // Close modal event
    const closeModal = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
        // Remove event listeners to prevent memory leaks
        closeBtn?.removeEventListener('click', closeModal);
        closeBtnX?.removeEventListener('click', closeModal);
        editBtn?.removeEventListener('click', editCaveat);
        modal.removeEventListener('click', outsideClickClose);
        document.removeEventListener('keydown', escapeKeyClose);
    };
    
    // Edit caveat event
    const editCaveat = () => {
        closeModal();
        editCaveatDetails(caveat);
    };
    
    // Close on outside click
    const outsideClickClose = (e) => {
        if (e.target === modal) {
            closeModal();
        }
    };
    
    // Close on Escape key
    const escapeKeyClose = (e) => {
        if (e.key === 'Escape') {
            closeModal();
        }
    };
    
    // Add event listeners
    closeBtn?.addEventListener('click', closeModal);
    closeBtnX?.addEventListener('click', closeModal);
    editBtn?.addEventListener('click', editCaveat);
    modal.addEventListener('click', outsideClickClose);
    document.addEventListener('keydown', escapeKeyClose);
}

function editCaveatDetails(caveat) {
    console.log('Editing caveat:', caveat);
    
    // Populate the form with caveat details
    populateFormFromCaveat(caveat);
    
    // Switch to place tab
    if (typeof window.setActiveTab === 'function') {
        window.setActiveTab('place');
    }
    
    // Show success message
    Swal.fire({
        icon: 'info',
        title: 'Edit Mode',
        text: `Caveat ${caveat.caveat_number || 'N/A'} has been loaded for editing.`,
        confirmButtonColor: '#3b82f6',
        timer: 3000,
        timerProgressBar: true
    });
}

function editCaveatFromTable(caveatId) {
    console.log('editCaveatFromTable called with ID:', caveatId, 'Type:', typeof caveatId);
    
    // Find the caveat using flexible comparison
    const caveat = window.caveats.find(c => {
        return c.id == caveatId || c.id === caveatId || c.id === parseInt(caveatId) || c.id === caveatId.toString();
    });
    
    if (!caveat) {
        console.error('Caveat not found with ID:', caveatId);
        console.error('Available caveat IDs:', window.caveats.map(c => ({ id: c.id, type: typeof c.id })));
        
        // Show error message to user
        Swal.fire({
            icon: 'error',
            title: 'Caveat Not Found',
            text: 'The selected caveat could not be found. Please refresh the page and try again.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    console.log('Found caveat for editing:', caveat);
    
    // Directly edit the caveat (same as editCaveatDetails)
    editCaveatDetails(caveat);
}

function showToast(message, type = 'info') {
    // Use the global HTML-based toast function
    if (window.showToast && window.showToast !== showToast) {
        window.showToast(message, type);
        return;
    }
    
    // Fallback to SweetAlert2 if global function not available
    const iconMap = {
        'success': 'success',
        'error': 'error',
        'warning': 'warning',
        'info': 'info'
    };
    
    Swal.fire({
        icon: iconMap[type] || 'info',
        title: message,
        confirmButtonColor: '#3b82f6',
        timer: 3000,
        timerProgressBar: true,
        toast: true,
        position: 'top-end',
        showConfirmButton: false
    });
}

// Update statistics
function updateStats() {
    console.log('updateStats called - loading from backend');
    
    // Load statistics from backend API
    fetch('/caveat/api/stats', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stats = data.data;
            console.log('Stats loaded from backend:', stats);
            
            const totalElement = document.getElementById('total-caveats');
            const activeElement = document.getElementById('active-caveats');
            const releasedElement = document.getElementById('released-caveats');
            const draftElement = document.getElementById('draft-caveats');
            
            if (totalElement) {
                totalElement.textContent = stats.total;
                console.log('Updated total caveats:', stats.total);
            } else {
                console.warn('total-caveats element not found');
            }
            
            if (activeElement) {
                activeElement.textContent = stats.active;
                console.log('Updated active caveats:', stats.active);
            } else {
                console.warn('active-caveats element not found');
            }
            
            if (releasedElement) {
                releasedElement.textContent = stats.released;
                console.log('Updated released caveats:', stats.released);
            } else {
                console.warn('released-caveats element not found');
            }
            
            if (draftElement) {
                draftElement.textContent = stats.draft;
                console.log('Updated draft caveats:', stats.draft);
            } else {
                console.warn('draft-caveats element not found');
            }
        } else {
            console.error('Failed to load stats:', data.error);
            // Fallback to client-side calculation if backend fails
            updateStatsFromClientData();
        }
    })
    .catch(error => {
        console.error('Error loading stats:', error);
        // Fallback to client-side calculation if API fails
        updateStatsFromClientData();
    });
}

// Fallback function to calculate stats from client-side data
function updateStatsFromClientData() {
    console.log('updateStatsFromClientData called - using client data as fallback');
    
    const caveatsData = window.caveats || [];
    
    const total = caveatsData.length;
    const active = caveatsData.filter(c => c.status === 'active').length;
    const released = caveatsData.filter(c => c.status === 'released').length;
    const draft = caveatsData.filter(c => c.status === 'draft').length;
    
    console.log('Stats calculated from client data:', { total, active, released, draft });
    
    const totalElement = document.getElementById('total-caveats');
    const activeElement = document.getElementById('active-caveats');
    const releasedElement = document.getElementById('released-caveats');
    const draftElement = document.getElementById('draft-caveats');
    
    if (totalElement) {
        totalElement.textContent = total;
    }
    
    if (activeElement) {
        activeElement.textContent = active;
    }
    
    if (releasedElement) {
        releasedElement.textContent = released;
    }
    
    if (draftElement) {
        draftElement.textContent = draft;
    }
}

// Export functions for global access
window.renderCaveatsList = renderCaveatsList;
window.renderActiveCaveatsList = renderActiveCaveatsList;
window.renderCaveatsTable = renderCaveatsTable;
window.updateStats = updateStats;
window.selectCaveatForLifting = selectCaveatForLifting;
window.populateFormFromCaveat = populateFormFromCaveat;
window.viewCaveatDetails = viewCaveatDetails;
window.showToast = showToast;
window.getStatusBadge = getStatusBadge;
window.formatDate = formatDate;
window.filterCaveats = filterCaveats;

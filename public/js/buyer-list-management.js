/**
 * Buyer List Management JavaScript
 * Handles CSV import, manual buyer addition, editing, and deletion
 * 
 * Dependencies:
 * - PapaParse library for CSV parsing
 * - SweetAlert2 for alerts
 * - Alpine.js for reactive UI
 */

// Load buyers list when buyers tab is clicked
function loadBuyersList() {
    const applicationId = document.getElementById('application_id')?.value;
    
    if (!applicationId) {
        document.getElementById('buyers-list-container').innerHTML = 
            '<div class="p-4 text-center text-gray-500">No application ID found.</div>';
        return;
    }
    
    fetch(`/buyer/list/${applicationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderBuyersList(data.records);
            } else {
                document.getElementById('buyers-list-container').innerHTML = 
                    '<div class="p-4 text-center text-gray-500">Error loading buyers list.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('buyers-list-container').innerHTML = 
                '<div class="p-4 text-center text-gray-500">Error loading buyers list.</div>';
        });
}

// Render buyers list in table format
function renderBuyersList(records) {
    const buyersListContainer = document.getElementById('buyers-list-container');
    if (!buyersListContainer) return;
    
    if (records.length === 0) {
        buyersListContainer.innerHTML = '<div class="p-4 text-center text-gray-500">No buyers added yet.</div>';
        return;
    }
    
    const isApproved = typeof window.isApplicationApproved !== 'undefined' ? window.isApplicationApproved : false;
    const isViewMode = new URLSearchParams(window.location.search).get('url') === 'view';

    let html = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buyer Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Block No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Land Use</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Measurement (sqm)</th>
                        ${!isViewMode ? '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>' : ''}
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
    `;
    
    records.forEach((record, index) => {
        const buyerTitle = (record.buyer_title || '').replace(/'/g, "\\'");
        const buyerName = (record.buyer_name || '').replace(/'/g, "\\'");
        const unitNo = (record.unit_no || '').replace(/'/g, "\\'");
    const landUse = (record.land_use || 'N/A').replace(/'/g, "\\'");
    const sectionNumberRaw = record.section_number || '';
    const sectionNumber = sectionNumberRaw.replace(/'/g, "\\'");
    const sectionNumberDisplay = sectionNumberRaw || 'N/A';
    const blockNoRaw = record.block_no || '';
    const blockNo = blockNoRaw.replace(/'/g, "\\'");
    const blockNoDisplay = blockNoRaw || 'N/A';
        const measurement = record.measurement || 'N/A';
        const cubicMeasurement = record.cubic_easurement || '';
        
        const fullNameWithTitle = buyerTitle && buyerTitle !== 'N/A' 
            ? `${buyerTitle} ${buyerName}`.toUpperCase() 
            : buyerName.toUpperCase();
        
        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${index + 1}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${fullNameWithTitle}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${unitNo}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${sectionNumberDisplay}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${blockNoDisplay}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${landUse}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${measurement}</td>
                ${!isViewMode ? `
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <button onclick="editBuyer(${record.id}, '${buyerTitle}', '${buyerName}', '${unitNo}', '${sectionNumber}', '${landUse}', '${measurement}', '${cubicMeasurement}', '${blockNo}')" 
                                class="text-blue-600 hover:text-blue-900 mr-3 ${isApproved ? 'opacity-50 cursor-not-allowed' : ''}"
                                ${isApproved ? 'disabled' : ''}>
                            Edit
                        </button>
                        <button onclick="deleteBuyer(${record.id}, '${buyerName}')" 
                                class="text-red-600 hover:text-red-900 ${isApproved ? 'opacity-50 cursor-not-allowed' : ''}"
                                ${isApproved ? 'disabled' : ''}>
                            Delete
                        </button>
                    </td>
                ` : ''}
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-sm text-gray-600">
            Total Buyers: <strong>${records.length}</strong>
        </div>
    `;
    
    buyersListContainer.innerHTML = html;
}

// Edit buyer function
function editBuyer(buyerId, buyerTitle, buyerName, unitNo, sectionNumber, landUse, measurement, cubicMeasurement, blockNo) {
    const applicationId = document.getElementById('application_id')?.value;

    const normalizedBuyerTitle = (buyerTitle || '').trim().toUpperCase();
    
    // Remove title from buyer name if it exists at the beginning
    let cleanName = buyerName.trim();
    if (buyerTitle && cleanName.toUpperCase().startsWith(buyerTitle.toUpperCase())) {
        // Remove the title and any following period and space
        cleanName = cleanName.substring(buyerTitle.length).replace(/^[\.\s]+/, '').trim();
    }
    
    // Parse buyer name to extract parts (assuming format: "FIRSTNAME [MIDDLENAME] SURNAME")
    const nameParts = cleanName.split(' ').filter(part => part.length > 0);
    let firstName = '';
    let middleName = '';
    let surname = '';
    
    if (nameParts.length === 1) {
        surname = nameParts[0];
    } else if (nameParts.length === 2) {
        firstName = nameParts[0];
        surname = nameParts[1];
    } else if (nameParts.length >= 3) {
        firstName = nameParts[0];
        surname = nameParts[nameParts.length - 1];
        middleName = nameParts.slice(1, -1).join(' ');
    }
    
    // Handle N/A landUse value
    const cleanLandUse = (landUse === 'N/A' || !landUse) ? '' : landUse;
    const cleanSectionNumber = (sectionNumber === 'N/A' || !sectionNumber) ? '' : sectionNumber;
    
    // Get titles options HTML
    let titlesOptionsHTML = `<option value="" ${normalizedBuyerTitle === '' ? 'selected' : ''}>No Title</option>`;
    if (typeof titles !== 'undefined' && Array.isArray(titles)) {
        titles.forEach(title => {
            const optionValue = title.title || '';
            const selected = normalizedBuyerTitle !== '' && normalizedBuyerTitle === optionValue.trim().toUpperCase() ? 'selected' : '';
            titlesOptionsHTML += `<option value="${optionValue}" ${selected}>${title.display_name}</option>`;
        });
    }
    
    // Get land use options HTML
    const landUseOptions = ['RESIDENTIAL', 'COMMERCIAL', 'INDUSTRIAL', 'MIXED'];
    let landUseOptionsHTML = '<option value="">Select Land Use</option>';
    landUseOptions.forEach(option => {
        const selected = cleanLandUse.toUpperCase() === option ? 'selected' : '';
        landUseOptionsHTML += `<option value="${option}" ${selected}>${option}</option>`;
    });
    
    const cleanMeasurement = measurement !== 'N/A' && measurement !== null && typeof measurement !== 'undefined'
        ? measurement
        : '';
    const cleanCubicMeasurement = cubicMeasurement !== 'N/A' && cubicMeasurement !== null && typeof cubicMeasurement !== 'undefined'
        ? cubicMeasurement
        : '';

    Swal.fire({
        title: 'Edit Buyer',
        html: `
            <div class="text-left">
                <div class="grid grid-cols-3 gap-4">
                    <!-- Row 1 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Title <span class="text-gray-500">(Optional)</span>
                        </label>
                        <select id="edit-buyer-title" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            ${titlesOptionsHTML}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit-first-name" value="${firstName}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md uppercase"
                               placeholder="Enter First Name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Middle Name <span class="text-gray-500">(Optional)</span>
                        </label>
                        <input type="text" id="edit-middle-name" value="${middleName}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md uppercase"
                               placeholder="Enter Middle Name">
                    </div>
                    
                    <!-- Row 2 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Surname <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit-surname" value="${surname}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md uppercase"
                               placeholder="Enter Surname">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Land Use
                        </label>
                        <select id="edit-land-use" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            ${landUseOptionsHTML}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Unit No <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit-unit-no" value="${unitNo}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md uppercase"
                               placeholder="Enter Unit No">
                    </div>
                    
                    <!-- Row 3 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Section Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit-section-number" value="${cleanSectionNumber}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md uppercase"
                               placeholder="Enter Section Number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Block No
                        </label>
                        <input type="text" id="edit-block-no" value="${(blockNo && blockNo !== 'N/A') ? blockNo : ''}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md uppercase"
                               placeholder="Enter Block No">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Unit Measurement (sqm)
                        </label>
                        <input type="number" step="0.01" id="edit-measurement" value="${cleanMeasurement}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md"
                               placeholder="e.g. 50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Unit Cubic Measurement (cbm)
                        </label>
                        <input type="number" step="0.01" id="edit-cubic-measurement" value="${cleanCubicMeasurement}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md"
                               placeholder="e.g. 50">
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Save Changes',
        cancelButtonText: 'Cancel',
        width: '800px',
        preConfirm: () => {
            const buyerTitleValue = document.getElementById('edit-buyer-title').value.trim().toUpperCase();
            const firstNameValue = document.getElementById('edit-first-name').value.trim().toUpperCase();
            const middleNameValue = document.getElementById('edit-middle-name').value.trim().toUpperCase();
            const surnameValue = document.getElementById('edit-surname').value.trim().toUpperCase();
            const unitNoValue = document.getElementById('edit-unit-no').value.trim().toUpperCase();
            const landUseValue = document.getElementById('edit-land-use').value;
            const sectionNumberValue = document.getElementById('edit-section-number').value.trim().toUpperCase();
            const blockNoValue = document.getElementById('edit-block-no').value.trim().toUpperCase();
            const measurementValue = document.getElementById('edit-measurement').value;
            const cubicMeasurementValue = document.getElementById('edit-cubic-measurement').value;

            if (!firstNameValue || !surnameValue || !unitNoValue || !sectionNumberValue) {
                Swal.showValidationMessage('Please fill in all required fields (First Name, Surname, Unit No, Section Number)');
                return false;
            }
            
            // Construct full buyer name
            const fullName = middleNameValue 
                ? `${firstNameValue} ${middleNameValue} ${surnameValue}`
                : `${firstNameValue} ${surnameValue}`;
            
            return {
                buyer_title: buyerTitleValue,
                buyer_name: fullName,
                unit_no: unitNoValue,
                section_number: sectionNumberValue,
                block_no: blockNoValue,
                land_use: landUseValue,
                measurement: measurementValue,
                cubic_easurement: cubicMeasurementValue
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Updating...',
                html: 'Please wait while we update the buyer information',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('application_id', applicationId);
            formData.append('buyer_id', buyerId);
            formData.append('buyer_title', result.value.buyer_title);
            formData.append('buyer_name', result.value.buyer_name);
            formData.append('unit_no', result.value.unit_no);
            formData.append('section_number', result.value.section_number);
            formData.append('block_no', result.value.block_no || '');
            if (result.value.land_use) {
                formData.append('land_use', result.value.land_use);
            }
            if (result.value.measurement) {
                formData.append('measurement', result.value.measurement);
            }
            if (result.value.cubic_easurement) {
                formData.append('cubic_easurement', result.value.cubic_easurement);
            }
            formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            
            fetch('/buyer/update-single', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        loadBuyersList();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to update buyer'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred'
                });
            });
        }
    });
}

// Delete buyer function
function deleteBuyer(buyerId, buyerName) {
    const applicationId = document.getElementById('application_id')?.value;
    
    Swal.fire({
        title: 'Delete Buyer',
        text: `Are you sure you want to delete ${buyerName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                html: 'Please wait while we delete the buyer',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('application_id', applicationId);
            formData.append('buyer_id', buyerId);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            
            fetch('/buyer/delete', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        loadBuyersList();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to delete buyer'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred'
                });
            });
        }
    });
}

// Initialize buyer list on document ready
document.addEventListener('DOMContentLoaded', function() {
    // Load buyers list when buyers tab is active
    const buyersTab = document.querySelector('[data-tab="buyers"]');
    if (buyersTab) {
        buyersTab.addEventListener('click', function() {
            loadBuyersList();
        });
    }
    
    // Handle form submission for adding buyers
    const buyersForm = document.getElementById('add-buyers-form');
    if (buyersForm) {
        buyersForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const applicationId = this.querySelector('input[name="application_id"]').value;
            
            if (!applicationId || applicationId.trim() === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'The application id field is required.',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show loading state
            Swal.fire({
                title: 'Saving...',
                html: 'Please wait while we process your request',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Get the form data
            const formData = new FormData(this);
            
            // Submit the form using fetch
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => Promise.reject(err));
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // The rows are in buyer_list now, so the autosaved draft has
                    // done its job and is closed rather than offered back on the
                    // next visit. See buyer-list-diagnostics.js.
                    if (window.__buyerListDiagnostics) {
                        window.__buyerListDiagnostics.noteSaved({
                            inserted: data.inserted,
                            skipped: data.skipped,
                            total: data.count
                        });
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Reset the form and refresh buyers list
                        buyersForm.reset();
                        // Restore the trace id the reset just cleared, so the rest
                        // of this page visit stays on one timeline in the log.
                        const traceField = buyersForm.querySelector('input[name="client_trace_id"]');
                        if (traceField && window.__buyerListDiagnostics) {
                            traceField.value = window.__buyerListDiagnostics.traceId();
                        }
                        // Restore application_id after reset
                        buyersForm.querySelector('input[name="application_id"]').value = applicationId;
                        // Reset Alpine.js buyers array
                        const buyersTab = document.getElementById('buyers-tab');
                        if (buyersTab && buyersTab._x_dataStack && buyersTab._x_dataStack[0]) {
                            buyersTab._x_dataStack[0].resetBuyers();
                        }
                        loadBuyersList();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to save buyers information'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Handle validation errors
                if (error.errors) {
                    let errorMessages = [];
                    Object.keys(error.errors).forEach(key => {
                        errorMessages.push(...error.errors[key]);
                    });
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Errors',
                        html: errorMessages.join('<br>'),
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'An unexpected error occurred. Please try again later.'
                    });
                }
            });
        });
    }
});

// Make functions globally available
window.loadBuyersList = loadBuyersList;
window.renderBuyersList = renderBuyersList;
window.editBuyer = editBuyer;
window.deleteBuyer = deleteBuyer;

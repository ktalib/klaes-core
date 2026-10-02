
<div id="property-edit-dialog" class="dialog-overlay hidden">
    <div class="dialog-content property-form-content">
        <!-- Update the close button to ensure it works properly -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Edit Property Record</h2>
            <button type="button" id="close-property-edit" class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form id="property-edit-form" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="property_id" id="edit_property_id" value="">
            <input type="hidden" name="action" value="edit">
            
            <div class="space-y-4 py-2 max-h-[75vh] overflow-y-auto pr-1">
                <!-- Top section with two columns -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Left column - Title Type Section -->
                    <div class="form-section">
                        <h4 class="form-section-title">Property Type Information</h4>
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="text-sm">Title Type</label>
                                <div class="flex space-x-4">
                                    <div class="flex items-center space-x-1">
                                        <input type="radio" id="edit_customary" name="titleType" value="Customary">
                                        <label for="edit_customary" class="text-sm">Customary</label>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <input type="radio" id="edit_statutory" name="titleType" value="Statutory">
                                        <label for="edit_statutory" class="text-sm">Statutory</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- File Number in Edit Form -->
                            <div class="form-section">
                                <h4 class="form-section-title">File Numbers (View Only)</h4>
                                <div class="space-y-3 all-tabs-visible">
                                    <div id="edit_property_mlsFNoTab" class="tabcontent">
                                        <div class="file-tab-header">MLS File Number</div>
                                        <input type="text" id="edit_property_mlsFNo_display" class="form-input text-sm bg-gray-100" readonly>
                                    </div>
                                    
                                    <div id="edit_property_kangisFileNoTab" class="tabcontent">
                                        <div class="file-tab-header">KANGIS File Number</div>
                                        <input type="text" id="edit_property_kangisFileNo_display" class="form-input text-sm bg-gray-100" readonly>
                                    </div>
                                    
                                    <div id="edit_property_NewKANGISFilenoTab" class="tabcontent">
                                        <div class="file-tab-header">New KANGIS File Number</div>
                                        <input type="text" id="edit_property_NewKANGISFileno_display" class="form-input text-sm bg-gray-100" readonly>
                                    </div>
                                    
                                    <!-- Hidden inputs to maintain values -->
                                    <input type="hidden" id="edit_property_mlsFNo" name="mlsFNo" value="">
                                    <input type="hidden" id="edit_property_kangisFileNo" name="kangisFileNo" value="">
                                    <input type="hidden" id="edit_property_NewKANGISFileno" name="NewKANGISFileno" value="">
                                    <input type="hidden" id="edit_property_activeFileTab" name="activeFileTab" value="mlsFNo">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right column - Property Description -->
                    <div class="form-section">
                        <h4 class="form-section-title">Property Description</h4>
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label for="edit_property_description" class="text-sm">Description</label>
                                <textarea id="edit_property_description" name="property_description" rows="4" class="form-input text-sm" placeholder="Enter property description"></textarea>
                            </div>
                            
                            <!-- LGA/City and Plot No. -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="edit_lgsaOrCity" class="text-xs text-gray-600">LGA/City</label>
                                    <input id="edit_lgsaOrCity" name="lgsaOrCity" type="text" class="form-input text-sm">
                                </div>
                                <div>
                                    <label for="edit_plotNo" class="text-xs text-gray-600">Plot No.</label>
                                    <input id="edit_plotNo" name="plot_no" type="text" class="form-input text-sm" placeholder="Enter plot number">
                                </div>
                            </div>
                            
                            <!-- Layout and Schedule -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="edit_layout" class="text-xs text-gray-600">Layout</label>
                                    <select id="edit_layout" name="layout" class="form-select text-sm">
                                        <option value="">Select Layout</option>
                                        <option value="Residential">Residential</option>
                                        <option value="Commercial">Commercial</option>
                                        <option value="Industrial">Industrial</option>
                                        <option value="Agricultural">Agricultural</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="edit_schedule" class="text-xs text-gray-600">Schedule</label>
                                    <select id="edit_schedule" name="schedule" class="form-select text-sm">
                                        <option value="">Select Schedule</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Sectional">Sectional</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction Details Section -->
                <div class="form-section">
                    <h4 class="form-section-title">Transaction Details</h4>
                    <div class="space-y-3">
                        <!-- Transaction Type and Date -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="edit_transactionType" class="text-sm">Transaction Type</label>
                                <select id="edit_transactionType" name="transactionType" class="form-select text-sm transaction-type-select">
                                    <option value="">Select type</option>
                                    <option value="Assignment">Assignment</option>
                                    <option value="Mortgage">Mortgage</option>
                                    <option value="Surrender">Surrender</option>
                                    <option value="Sub-Lease">Sub-Lease</option>
                                    <option value="Release">Release</option>
                                    <option value="Devolution Order">Devolution Order</option>
                                    <option value="Court Order">Court Order</option>
                                    <option value="Revocation">Revocation</option>
                                    <option value="Certificate of Occupancy">Certificate of Occupancy</option>
                                    <option value="Power of Attorney">Power of Attorney</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label for="edit_transactionDate" class="text-sm">Transaction Date</label>
                                <input type="date" id="edit_transactionDate" name="transactionDate" class="form-input text-sm">
                            </div>
                        </div>
                        
                        <!-- Registration Number Components -->
                        <div class="space-y-1">
                            <label class="text-sm">Registration Number Components</label>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label for="edit_serialNo" class="text-xs">Serial No.</label>
                                    <input id="edit_serialNo" name="serialNo" class="form-input text-sm" placeholder="e.g. 1">
                                </div>
                                <div>
                                    <label for="edit_pageNo" class="text-xs">Page No.</label>
                                    <input id="edit_pageNo" name="pageNo" class="form-input text-sm" placeholder="e.g. 1">
                                </div>
                                <div>
                                    <label for="edit_volumeNo" class="text-xs">Volume No.</label>
                                    <input id="edit_volumeNo" name="volumeNo" class="form-input text-sm" placeholder="e.g. 2">
                                </div>
                            </div>
                            <div id="edit_regNoPreview" class="mt-1 p-1.5 bg-green-50 border border-green-100 rounded-md hidden">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-green-700">Registration Particulars:</span>
                                    <span id="edit_regNo" class="text-sm font-medium text-green-800"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Instrument Type and Period -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="edit_instrumentType" class="text-sm">Instrument Type</label>
                                <select id="edit_instrumentType" name="instrumentType" class="form-select text-sm">
                                    <option value="">Select instrument</option>
                                    <option value="Deed of Assignment">Deed of Assignment</option>
                                    <option value="Certificate of Occupancy">Certificate of Occupancy</option>
                                    <option value="Deed of Mortgage">Deed of Mortgage</option>
                                    <option value="Deed of Lease">Deed of Lease</option>
                                    <option value="Deed of Release">Deed of Release</option>
                                    <option value="Court Order">Court Order</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label for="edit_period" class="text-sm">Period</label>
                                <div class="flex space-x-1">
                                    <input id="edit_period" name="period" type="number" class="form-input text-sm" placeholder="Period">
                                    <select id="edit_periodUnit" name="periodUnit" class="form-select text-sm w-[90px]">
                                        <option value="Days">Days</option>
                                        <option value="Months">Months</option>
                                        <option value="Years" selected>Years</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction-specific fields section -->
                <div id="edit_transaction_specific_fields" class="form-section hidden">
                    <!-- Content will be populated dynamically based on transaction type -->
                </div>
            </div>
            
            <!-- Update the cancel button to use type="button" -->
            <div class="flex justify-end space-x-3 pt-2 border-t mt-4">
                <button type="button" id="cancel-property-edit" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Record</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Removed add-property form handlers to avoid conflicts; handled in its own partial.
    
    // Function to update party fields based on transaction type
    function updatePartyFields(transactionType) {
        // Check which fields are visible based on transaction type
        switch(transactionType) {
            case 'assignment':
                if (document.getElementById('trans-assignor-record')) {
                    const assignorField = document.createElement('input');
                    assignorField.type = 'hidden';
                    assignorField.name = 'Assignor';
                    assignorField.value = document.getElementById('trans-assignor-record').value;
                    propertyForm.appendChild(assignorField);
                    
                    const assigneeField = document.createElement('input');
                    assigneeField.type = 'hidden';
                    assigneeField.name = 'Assignee';
                    assigneeField.value = document.getElementById('trans-assignee-record').value;
                    propertyForm.appendChild(assigneeField);
                }
                break;
            case 'mortgage':
                if (document.getElementById('mortgagor-record')) {
                    const mortgagorField = document.createElement('input');
                    mortgagorField.type = 'hidden';
                    mortgagorField.name = 'Mortgagor';
                    mortgagorField.value = document.getElementById('mortgagor-record').value;
                    propertyForm.appendChild(mortgagorField);
                    
                    const mortgageeField = document.createElement('input');
                    mortgageeField.type = 'hidden';
                    mortgageeField.name = 'Mortgagee';
                    mortgageeField.value = document.getElementById('mortgagee-record').value;
                    propertyForm.appendChild(mortgageeField);
                }
                break;
                // Add other transaction types as needed
        }
    }
    
    // Initialize registration number preview
    const serialNo = document.getElementById('serialNo');
    const pageNo = document.getElementById('pageNo');
    const volumeNo = document.getElementById('volumeNo');
    const regNoPreview = document.getElementById('regNoPreview');
    const regNoDisplay = document.getElementById('regNo');
    
    function updateRegNoPreview() {
        if ((serialNo && serialNo.value) || (pageNo && pageNo.value) || (volumeNo && volumeNo.value)) {
            const serial = serialNo ? (serialNo.value || '-') : '-';
            const page = pageNo ? (pageNo.value || '-') : '-';
            const volume = volumeNo ? (volumeNo.value || '-') : '-';
            if (regNoDisplay) regNoDisplay.textContent = `${serial}/${page}/${volume}`;
            if (regNoPreview) regNoPreview.classList.remove('hidden');
        } else if (regNoPreview) {
            regNoPreview.classList.add('hidden');
        }
    }
    
    if (serialNo) serialNo.addEventListener('input', updateRegNoPreview);
    if (pageNo) pageNo.addEventListener('input', updateRegNoPreview);
    if (volumeNo) volumeNo.addEventListener('input', updateRegNoPreview);
    
    // Fix name of transaction type field to match controller expected name
    const transactionTypeField = document.getElementById('transactionType-record');
    if (transactionTypeField) {
        transactionTypeField.name = 'transactionType';
    }
    
    // Fix other field names to match expected controller names
    const instrumentTypeField = document.getElementById('instrumentType');
    if (instrumentTypeField) {
        instrumentTypeField.name = 'instrumentType';
    }
    
    const periodField = document.getElementById('period');
    if (periodField) {
        periodField.name = 'period';
    }
    
    const periodUnitField = document.getElementById('periodUnit');
    if (periodUnitField) {
        periodUnitField.name = 'periodUnit';
    }
    
    const propertyDescriptionField = document.getElementById('property-description');
    if (propertyDescriptionField) {
        propertyDescriptionField.name = 'property_description';
    }
    
    const locationField = document.getElementById('property-location');
    if (locationField) {
        locationField.name = 'location';
    }
    
    const plotNoField = document.getElementById('plotNo');
    if (plotNoField) {
        plotNoField.name = 'plotNo';
    }
    
    // Also fix date field name
    const transactionDateField = document.getElementById('transactionDate');
    if (transactionDateField) {
        transactionDateField.name = 'transactionDate';
    }
    
    // Edit form handling
    const propertyEditForm = document.getElementById('property-edit-form');
    if (propertyEditForm) {
        propertyEditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Update registration number preview and hidden field
            const serialNo = document.getElementById('edit_serialNo').value || '';
            const pageNo = document.getElementById('edit_pageNo').value || '';
            const volumeNo = document.getElementById('edit_volumeNo').value || '';
            
            if (document.getElementById('edit_regNo')) {
                document.getElementById('edit_regNo').textContent = `${serialNo}/${pageNo}/${volumeNo}`;
            }
            
            // Create or update the hidden input for the reg number
            let regNoInput = document.getElementById('regNoField');
            if (!regNoInput) {
                regNoInput = document.createElement('input');
                regNoInput.type = 'hidden';
                regNoInput.id = 'regNoField';
                regNoInput.name = 'regNo';
                propertyEditForm.appendChild(regNoInput);
            }
            regNoInput.value = `${serialNo}/${pageNo}/${volumeNo}`;
            
            // Get transaction-specific fields based on the active transaction type
            const transactionType = document.getElementById('edit_transactionType').value;
            if (transactionType) {
                // Update party field names based on transaction type
                updatePartyFields(transactionType);
            }
            
            // Now actually submit the form
            console.log('Submitting edit form...');
            
            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Updating property record',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Get the form action URL as a string
            const actionUrl = propertyEditForm.getAttribute('action');
            
            // Use fetch API for AJAX submission
            fetch(actionUrl, {
                method: 'POST',
                body: new FormData(propertyEditForm),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 422) {
                        return response.json().then(data => {
                            throw new Error('Validation failed');
                        });
                    }
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Success:', data);
                if (data.status === 'success') {
                    // Show success message with SweetAlert
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Property record updated successfully!',
                        confirmButtonColor: '#3085d6'
                    }).then((result) => {
                        // Close the modal
                        document.getElementById('property-edit-dialog').classList.add('hidden');
                        
                        // Optionally reload the page to show the updated record
                        window.location.reload();
                    });
                } else {
                    // Show error message with SweetAlert
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to update property record',
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show error message with SweetAlert
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Validation failed',
                    confirmButtonColor: '#3085d6'
                });
            });
        });
    }
    
    // Function to load property record data into the edit form
    window.loadPropertyRecord = function(propertyId) {
        // Clear previous values
        propertyEditForm.reset();
        
        // Set the property ID
        document.getElementById('edit_property_id').value = propertyId;
        
        // Show loading state
        Swal.fire({
            title: 'Loading...',
            text: 'Fetching property record details',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Fetch the property record data
        fetch(`{{ url('/') }}/property-records/${propertyId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Property record data:', data);
            
            // Populate the form fields with the retrieved data
            document.getElementById('edit_property_description').value = data.property_description || '';
            document.getElementById('edit_lgsaOrCity').value = data.lgsaOrCity || '';
            document.getElementById('edit_plotNo').value = data.plot_no || '';
            document.getElementById('edit_layout').value = data.layout || '';
            document.getElementById('edit_schedule').value = data.schedule || '';
            document.getElementById('edit_transactionType').value = data.transactionType || '';
            document.getElementById('edit_transactionDate').value = data.transactionDate ? data.transactionDate.split('T')[0] : '';
            document.getElementById('edit_serialNo').value = data.serialNo || '';
            document.getElementById('edit_pageNo').value = data.pageNo || '';
            document.getElementById('edit_volumeNo').value = data.volumeNo || '';
            document.getElementById('edit_instrumentType').value = data.instrumentType || '';
            document.getElementById('edit_period').value = data.period || '';
            document.getElementById('edit_periodUnit').value = data.periodUnit || 'Years';
            
            // Update registration number preview
            updateRegNoPreview();
            
            // Show the appropriate transaction-specific fields
            showTransactionSpecificFields(data.transactionType);
            
            // Close the loading state
            Swal.close();
            
            // Show the edit dialog
            document.getElementById('property-edit-dialog').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            // Close the loading state
            Swal.close();
            
            // Show error message with SweetAlert
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An error occurred while fetching property record: ' + error.message,
                confirmButtonColor: '#3085d6'
            });
        });
    }
    
    // Function to show transaction-specific fields in the edit form
    function showTransactionSpecificFields(transactionType) {
        // Hide all transaction-specific fields
        const allTransactionFields = document.querySelectorAll('.transaction-fields');
        allTransactionFields.forEach(field => field.classList.add('hidden'));
        
        // Show the fields for the selected transaction type
        const specificFields = document.getElementById(transactionType + '-fields-record');
        if (specificFields) {
            specificFields.classList.remove('hidden');
        }
        
        // Update the visibility of the transaction-specific fields section
        const transactionSpecificFieldsSection = document.getElementById('edit_transaction_specific_fields');
        if (transactionSpecificFieldsSection) {
            transactionSpecificFieldsSection.classList.remove('hidden');
        }
    }
    
    // Initialize registration number preview for edit form
    const editSerialNo = document.getElementById('edit_serialNo');
    const editPageNo = document.getElementById('edit_pageNo');
    const editVolumeNo = document.getElementById('edit_volumeNo');
    const editRegNoPreview = document.getElementById('edit_regNoPreview');
    const editRegNoDisplay = document.getElementById('edit_regNo');
    
    function updateEditRegNoPreview() {
        if ((editSerialNo && editSerialNo.value) || (editPageNo && editPageNo.value) || (editVolumeNo && editVolumeNo.value)) {
            const serial = editSerialNo ? (editSerialNo.value || '-') : '-';
            const page = editPageNo ? (editPageNo.value || '-') : '-';
            const volume = editVolumeNo ? (editVolumeNo.value || '-') : '-';
            if (editRegNoDisplay) editRegNoDisplay.textContent = `${serial}/${page}/${volume}`;
            if (editRegNoPreview) editRegNoPreview.classList.remove('hidden');
        } else if (editRegNoPreview) {
            editRegNoPreview.classList.add('hidden');
        }
    }
    
    if (editSerialNo) editSerialNo.addEventListener('input', updateEditRegNoPreview);
    if (editPageNo) editPageNo.addEventListener('input', updateEditRegNoPreview);
    if (editVolumeNo) editVolumeNo.addEventListener('input', updateEditRegNoPreview);
    
    // Event delegation for opening edit dialog
    document.addEventListener('click', function(e) {
        if (e.target.matches('.open-edit-property')) {
            e.preventDefault();
            
            // Get the property ID from the data attribute
            const propertyId = e.target.getAttribute('data-property-id');
            if (propertyId) {
                // Load the property record into the edit form
                loadPropertyRecord(propertyId);
            }
        }
    });

    // Close and cancel buttons for property edit dialog
    const closePropertyEditBtn = document.getElementById('close-property-edit');
    if (closePropertyEditBtn) {
        closePropertyEditBtn.addEventListener('click', function() {
            document.getElementById('property-edit-dialog').classList.add('hidden');
        });
    }
    
    const cancelPropertyEditBtn = document.getElementById('cancel-property-edit');
    if (cancelPropertyEditBtn) {
        cancelPropertyEditBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('property-edit-dialog').classList.add('hidden');
        });
    }
});
</script>
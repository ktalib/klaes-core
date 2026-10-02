<div id="cofoDetailsModal" class="fixed inset-0 z-[1000] hidden bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h5 class="text-xl font-semibold">Enter Certificate of Occupancy (CofO) Details</h5>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeCofoDetailsModal()">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="px-6 py-4">
            <form id="cofoDetailsForm" enctype="application/x-www-form-urlencoded">
                @csrf
                <input type="hidden" id="cofoApplicationId" name="application_id" value="">

                <div id="cofoAutofillBanner" class="hidden mb-4 px-4 py-3 rounded-md text-sm font-medium">
                    <span id="cofoAutofillBannerMessage">Looking up CofO records…</span>
                </div>
                
                <!-- Instrument Details Section -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">🧾 Instrument Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instrument Type</label>
                            <input type="text" id="cofoTransactionType" name="transaction_type" value="Certificate of Occupancy" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CofO Number</label>
                            <input type="text" id="cofoNumber" name="cofo_no" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter CofO Number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Certificate Date</label>
                            <input type="date" id="cofoCertificateDate" name="certificate_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Registration Number Components Section -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">🧮 Registration Number Components</h3>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Serial No.</label>
                            <input type="number" id="cofoSerialNo" name="serial_no" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., 1" oninput="syncSerialToPageNo(); updateRegistrationPreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Page No.</label>
                            <input type="number" id="cofoPageNo" name="page_no" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" placeholder="e.g., 1" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Volume No.</label>
                            <input type="number" id="cofoVolumeNo" name="volume_no" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., 2" oninput="updateRegistrationPreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Registration Date</label>
                            <input type="date" id="cofoTransactionDate" name="transaction_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Registration Time</label>
                            <input type="time" id="cofoTransactionTime" name="transaction_time" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Registration Number Preview</label>
                            <input type="text" id="cofoRegNoPreview" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly placeholder="Serial/Page/Volume">
                        </div>
                    </div>
                </div>

                <!-- Land Use & Tenure Section -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">🏷 Land Use & Tenure</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                            <input type="text" id="cofoLandUse" name="land_use" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Period / Tenancy </label>
                            <input type="number" id="cofoPeriod" name="period" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., 99">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Period</label>
                            <select id="cofoPeriodUnit" name="period_unit" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="years">Years</option>
                                <option value="months">Months</option>
                                <option value="weeks">Weeks</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Transaction Parties Section -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">🔁 Transaction Parties</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Grantor</label>
                            <input type="text" id="cofoGrantor" name="grantor" value="KANO STATE GOVERNMENT" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Grantee</label>
                            <input type="text" id="cofoGrantee" name="grantee" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 uppercase" readonly>
                        </div>
                    </div>
                </div>

                <!-- Property Description Section -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">✏️ Property Description</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PROPERTY DESCRIPTION</label>
                            <textarea id="cofoPropertyDescription" name="property_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-100 uppercase" placeholder="FULL ADDRESS OF THE PROPERTY WILL BE AUTO-GENERATED..." readonly></textarea>
                            <small class="text-gray-500 text-xs mt-1">This will be auto-populated with the full address based on the property details.</small>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-between items-center bg-gray-50 px-6 py-4 rounded-b border-t">
                    <button type="button" onclick="closeCofoDetailsModal()" class="flex items-center space-x-2 px-6 py-2 bg-gray-500 text-white rounded-md shadow hover:bg-gray-600 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        <span>Cancel</span>
                    </button>
                    <button type="submit" id="submitCofoDetails" class="flex items-center space-x-2 px-6 py-2 bg-green-500 text-white rounded-md shadow hover:bg-green-600 transition-colors">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span>Submit CofO Details</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const cofoAutofillBaseClasses = ['mb-4', 'px-4', 'py-3', 'rounded-md', 'text-sm', 'font-medium'];

    function setCofoAutofillBanner(state = 'hidden', message = '') {
        const banner = document.getElementById('cofoAutofillBanner');
        const text = document.getElementById('cofoAutofillBannerMessage');

        if (!banner || !text) {
            return;
        }

        banner.className = cofoAutofillBaseClasses.join(' ');

        const stateClassMap = {
            hidden: ['hidden'],
            loading: ['bg-blue-50', 'text-blue-800', 'border', 'border-blue-100'],
            success: ['bg-green-50', 'text-green-800', 'border', 'border-green-100'],
            info: ['bg-yellow-50', 'text-yellow-800', 'border', 'border-yellow-100'],
            error: ['bg-red-50', 'text-red-800', 'border', 'border-red-100']
        };

        const classes = stateClassMap[state] || stateClassMap.info;
        classes.forEach(cls => banner.classList.add(cls));

        if (message) {
            text.textContent = message;
        } else {
            text.textContent = state === 'loading'
                ? 'Looking up CofO records…'
                : state === 'success'
                    ? 'CofO details loaded successfully.'
                    : state === 'error'
                        ? 'Unable to locate CofO details. Please enter them manually.'
                        : '';
        }
    }

    function applyCofoDetailsToForm(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        const setValue = (id, value) => {
            const element = document.getElementById(id);
            if (!element) return;
            element.value = value ?? '';
        };

        const sanitizedTime = (value) => {
            if (!value) return '';
            return value.toString().slice(0, 5);
        };

        setValue('cofoNumber', data.cofo_no ?? data.reg_no ?? '');
        setValue('cofoCertificateDate', data.certificate_date ?? '');
        setValue('cofoSerialNo', data.serial_no ?? '');
        setValue('cofoPageNo', data.page_no ?? data.serial_no ?? '');
        setValue('cofoVolumeNo', data.volume_no ?? '');
        setValue('cofoTransactionDate', data.transaction_date ?? '');
        setValue('cofoTransactionTime', sanitizedTime(data.transaction_time));

        const regPreview = document.getElementById('cofoRegNoPreview');
        if (regPreview) {
            regPreview.value = data.reg_no ?? '';
        }

        const landUseField = document.getElementById('cofoLandUse');
        if (landUseField && data.land_use) {
            landUseField.value = data.land_use;
        }

        const propertyDescriptionField = document.getElementById('cofoPropertyDescription');
        if (propertyDescriptionField && data.property_description) {
            propertyDescriptionField.value = data.property_description;
        }

        const transactionTypeField = document.getElementById('cofoTransactionType');
        if (transactionTypeField && data.cofo_type) {
            transactionTypeField.value = data.cofo_type;
        }

        updateRegistrationPreview();
    }

    function fetchCofoDetailsSequential(candidates, attempt = 0) {
        if (!Array.isArray(candidates) || candidates.length === 0) {
            setCofoAutofillBanner('info', 'No file number provided to locate CofO records. Please enter details manually.');
            return;
        }

        if (attempt >= candidates.length) {
            setCofoAutofillBanner('error', 'Unable to locate CofO details. Please enter them manually.');
            return;
        }

        const fileNumber = candidates[attempt];
        if (!fileNumber) {
            fetchCofoDetailsSequential(candidates, attempt + 1);
            return;
        }

        setCofoAutofillBanner('loading', `Looking up CofO records for ${fileNumber}…`);

        fetch(`/sectionaltitling/cofo-details?file_number=${encodeURIComponent(fileNumber)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json().catch(() => ({ success: false })))
        .then(payload => {
            if (payload && payload.success && payload.data) {
                applyCofoDetailsToForm(payload.data);
                setCofoAutofillBanner('success', `Auto-filled using CofO record for ${fileNumber}.`);
            } else {
                fetchCofoDetailsSequential(candidates, attempt + 1);
            }
        })
        .catch(() => fetchCofoDetailsSequential(candidates, attempt + 1));
    }

    // Function to open CofO Details modal
    function openCofoDetailsModal(applicationId, fileNo, npFileNo, applicantType, applicantData, propertyData) {
        // Show SweetAlert confirmation dialog first
        Swal.fire({
            title: '📋 Capture Extant CofO Details',
            text: 'Primary Applicant Have CofO?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed || result.isDismissed) {
                const isRegistered = result.isConfirmed;
                setCofoAutofillBanner('hidden');
                
                // Set the application id
                document.getElementById('cofoApplicationId').value = applicationId;
                
                // Process applicant name based on type
                let applicantName = '';
                
                if(applicantType === 'individual' && applicantData) {
                    const { applicant_title, first_name, middle_name, surname } = applicantData;
                    applicantName = [applicant_title, first_name, middle_name, surname].filter(Boolean).join(' ');
                } 
                else if(applicantType === 'corporate' && applicantData) {
                    applicantName = applicantData.corporate_name || '';
                }
                else if(applicantType === 'multiple' && applicantData) {
                    try {
                        if(typeof applicantData === 'string') {
                            const namesArray = JSON.parse(applicantData);
                            applicantName = Array.isArray(namesArray) ? namesArray.join(', ') : applicantData;
                        } else if(Array.isArray(applicantData)) {
                            applicantName = applicantData.join(', ');
                        }
                    } catch(e) {
                        applicantName = applicantData.toString();
                    }
                }
                
                // Set grantee name
                document.getElementById('cofoGrantee').value = applicantName;
                
                // Set land use
                document.getElementById('cofoLandUse').value = propertyData.land_use || '';
                
                // Generate full property description based on available data
                let propertyDescription = '';
                // Format: Plot/House No, Streetname, District, LGA and State
                const addressParts = [
                    propertyData.property_house_no,
                    propertyData.property_street_name,
                    propertyData.property_district, 
                    propertyData.property_lga,
                    (propertyData.property_state || 'Kano') + ' State'
                ].filter(Boolean);
                
                propertyDescription = addressParts.join(', ');
                
                // Set the property description
                document.getElementById('cofoPropertyDescription').value = propertyDescription;
                
                // Apply field toggle based on registration status
                toggleCofoFields(isRegistered);
                
                if (isRegistered) {
                    const lookupCandidates = [npFileNo, fileNo].filter(Boolean);
                    fetchCofoDetailsSequential(lookupCandidates);
                } else {
                    setCofoAutofillBanner('hidden');
                }
                
                // Show the modal
                document.getElementById('cofoDetailsModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        });
    }
    // Function to close CofO Details modal
    function closeCofoDetailsModal() {
        document.getElementById('cofoDetailsModal').classList.add('hidden');
        document.body.style.overflow = '';
        setCofoAutofillBanner('hidden');
        // Reset form
        document.getElementById('cofoDetailsForm').reset();
        document.getElementById('cofoRegNoPreview').value = '';
        // Reset transaction type to default
        document.getElementById('cofoTransactionType').value = 'Certificate of Occupancy';
        // Reset radio buttons
        const radioButtons = document.querySelectorAll('input[name="cofoRegistered"]');
        radioButtons.forEach(radio => radio.checked = false);
        // Reset field states to enabled
        toggleCofoFields(true);
    }
    
    // Function to toggle CofO fields based on registration status
    function toggleCofoFields(isRegistered) {
        const fieldsToToggle = [
            'cofoNumber',
            'cofoCertificateDate',
            'cofoSerialNo',
            'cofoVolumeNo',
            'cofoTransactionDate',
            'cofoTransactionTime',
            'cofoRegNoPreview'
        ];
        
        // Handle page number field separately since it's always readonly
        const pageNoField = document.getElementById('cofoPageNo');
        
        fieldsToToggle.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (isRegistered) {
                    // Enable fields and remove grey styling
                    field.disabled = false;
                    field.classList.remove('bg-gray-200', 'text-gray-500', 'cursor-not-allowed');
                    field.classList.add('focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500');
                    
                    // Clear default values for editable fields
                    if (fieldId !== 'cofoRegNoPreview') {
                        field.value = '';
                        // Clear page number when serial number is cleared
                        if (fieldId === 'cofoSerialNo') {
                            document.getElementById('cofoPageNo').value = '';
                        }
                    }
                } else {
                    // Disable fields and add grey styling
                    field.disabled = true;
                    field.classList.add('bg-gray-200', 'text-gray-500', 'cursor-not-allowed');
                    field.classList.remove('focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500');
                    
                    // Set default values
                    switch(fieldId) {
                        case 'cofoNumber':
                            field.value = 'N/A';
                            break;
                        case 'cofoCertificateDate':
                            field.value = '0000-00-00';
                            break;
                        case 'cofoSerialNo':
                            field.value = '0';
                            // Sync to page number when setting default value
                            document.getElementById('cofoPageNo').value = '0';
                            break;
                        case 'cofoVolumeNo':
                            field.value = '0';
                            break;
                        case 'cofoTransactionDate':
                            field.value = '0000-00-00';
                            break;
                        case 'cofoTransactionTime':
                            field.value = '0000';
                            break;
                        case 'cofoRegNoPreview':
                            field.value = '0/0/0';
                            break;
                    }
                }
            }
        });
        
        // Handle page number field - always readonly but style changes based on registration status
        if (pageNoField) {
            if (isRegistered) {
                pageNoField.classList.remove('bg-gray-200', 'text-gray-500', 'cursor-not-allowed');
                pageNoField.classList.add('bg-gray-100');
                pageNoField.value = '';
            } else {
                pageNoField.classList.add('bg-gray-200', 'text-gray-500', 'cursor-not-allowed');
                pageNoField.classList.remove('bg-gray-100');
                pageNoField.value = '0';
            }
        }
        
        // Update registration preview if not registered
        if (!isRegistered) {
            document.getElementById('cofoRegNoPreview').value = '0/0/0';
        }
    }
    
    // Function to sync serial number to page number
    function syncSerialToPageNo() {
        const serialNo = document.getElementById('cofoSerialNo').value;
        document.getElementById('cofoPageNo').value = serialNo;
    }
    
    // Function to update registration number preview
    function updateRegistrationPreview() {
        const serialNo = document.getElementById('cofoSerialNo').value;
        const pageNo = document.getElementById('cofoPageNo').value;
        const volumeNo = document.getElementById('cofoVolumeNo').value;
        
        if (serialNo && pageNo && volumeNo) {
            document.getElementById('cofoRegNoPreview').value = `${serialNo}/${pageNo}/${volumeNo}`;
        } else {
            document.getElementById('cofoRegNoPreview').value = '';
        }
    }
    
    // Close modal when clicking outside the modal content
    document.addEventListener('mousedown', function(event) {
        const modal = document.getElementById('cofoDetailsModal');
        if (!modal.classList.contains('hidden')) {
            const modalContent = modal.querySelector('div.bg-white');
            if (modal && !modalContent.contains(event.target)) {
                closeCofoDetailsModal();
            }
        }
    });
    
    // Handle form submission
    document.getElementById('cofoDetailsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data as URL-encoded string
        const formData = new FormData(this);
        
        // Add the registration number to form data
        const regNo = document.getElementById('cofoRegNoPreview').value;
        if (regNo) {
            formData.append('reg_no', regNo);
        }
        
        // Convert FormData to URLSearchParams for proper encoding
        const urlEncodedData = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            urlEncodedData.append(key, value);
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitCofoDetails');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Saving...</span>';
        submitBtn.disabled = true;
        
        // Submit via AJAX
        fetch('/sectionaltitling/save-cofo-details', {
            method: 'POST',
            body: urlEncodedData,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: 'CofO details saved successfully!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    closeCofoDetailsModal();
                    // Optionally reload the page or update the UI
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Failed to save CofO details',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while saving CofO details',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Initialize the form when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Set default transaction type
        document.getElementById('cofoTransactionType').value = 'Certificate of Occupancy';
    });
   
</script>
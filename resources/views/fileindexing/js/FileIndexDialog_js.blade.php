<script>
(function(){
        // Initialize Lucide icons safely
        try { if (window.lucide && lucide.createIcons) { lucide.createIcons(); } } catch (e) { /* no-op */ }

        const generateTrackingId = () => {
            // Generate random alphanumeric segments
            const segment1 = generateRandomAlphanumeric(8); // 8 characters like MESALDX6
            const segment2 = generateRandomAlphanumeric(5); // 5 characters like QWB08
            return `TRK-${segment1}-${segment2}`;
        }

        // Generate random alphanumeric string
        const generateRandomAlphanumeric = (length) => {
            const characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ123456789'; // Exclude O, 0 for clarity
            let result = '';
            for (let i = 0; i < length; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            return result;
        }

        // DOM Elements (guarded)
        const newFileDialogOverlay = document.getElementById('new-file-dialog-overlay');
        const closeDialogBtn = document.getElementById('close-dialog-btn');
        const cancelBtn = document.getElementById('cancel-btn');
        const createFileBtn = document.getElementById('create-file-btn');
        const trackingIdInput = document.getElementById('tracking-id');
        const generateTrackingBtn = document.getElementById('generate-tracking-btn');
        const districtSelect = document.getElementById('district-select');
        const customDistrictContainer = document.getElementById('custom-district-container');
        const customDistrictInput = document.getElementById('custom-district-input');
        const newFileIndexBtn = document.getElementById('new-file-index-btn');

        // Open dialog from main CTA button
        if (newFileIndexBtn) {
            newFileIndexBtn.addEventListener('click', () => showNewFileDialog());
        }

        if (districtSelect) {
            districtSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    if (customDistrictContainer) customDistrictContainer.classList.remove('hidden');
                    if (customDistrictInput) customDistrictInput.focus();
                } else {
                    if (customDistrictContainer) customDistrictContainer.classList.add('hidden');
                    if (customDistrictInput) customDistrictInput.value = '';
                }
            });
        }

        // Show new file dialog
        function showNewFileDialog() {
            if (!newFileDialogOverlay) return;
            newFileDialogOverlay.classList.remove('hidden');
            
            // Reset form fields
            const formEl = document.getElementById('new-file-form');
            if (formEl && formEl.reset) formEl.reset();
            
            // Generate initial tracking ID
            if (trackingIdInput) {
                trackingIdInput.value = generateTrackingId();
            }
            
            // Reset smart file number selector
            resetSmartFileNumberSelector();
        }
        // Expose for external callers
        window.showNewFileDialog = showNewFileDialog;

        // Reset smart file number selector to default state
        function resetSmartFileNumberSelector() {
            // Clear the main fileno field
            const filenoInput = document.getElementById('fileno');
            if (filenoInput) filenoInput.value = '';
            
            // Clear dropdown selection
            const filenoSelect = document.getElementById('fileno-select');
            if (filenoSelect) {
                filenoSelect.value = '';
                // Trigger change event to clear display
                filenoSelect.dispatchEvent(new Event('change'));
            }
            
            // Hide selected display
            const selectedDisplay = document.getElementById('selected-fileno-display');
            if (selectedDisplay) selectedDisplay.classList.add('hidden');
            
            // Reset to dropdown mode
            const dropdownMode = document.getElementById('dropdown-mode');
            const manualMode = document.getElementById('manual-mode');
            if (dropdownMode && manualMode) {
                dropdownMode.classList.remove('hidden');
                dropdownMode.style.display = 'block';
                manualMode.classList.add('hidden');
                manualMode.style.display = 'none';
            }
        }

        // Close new file dialog
        function closeNewFileDialog() {
            if (!newFileDialogOverlay) return;
            newFileDialogOverlay.classList.add('hidden');
        }

        // Helper to title-case a simple value
        function toTitleCase(val) {
            if (!val) return val;
            return val.charAt(0).toUpperCase() + val.slice(1).toLowerCase();
        }

        // Get the active file number from smart selector
        function getActiveFileNumber() {
            const filenoInput = document.getElementById('fileno');
            return filenoInput ? filenoInput.value.trim() : '';
        }

        // Get file number mapping data for backend
        function getFileNumberMappingData() {
            const selectedApplication = window.selectedApplication;

            if (selectedApplication && !selectedApplication.isManual) {
                return {
                    source_file_id: selectedApplication.id,
                    file_number_id: selectedApplication.id,
                };
            }

            return {
                source_file_id: null,
                file_number_id: null,
            };
        }

        // Create new file
        function createNewFile() {
            // Get form values (guard missing inputs)
            const fileTitleEl = document.getElementById('file-title');
            const plotNumberEl = document.getElementById('plot-number');
            const landUseTypeEl = document.getElementById('land-use-type');
            const lgaCityEl = document.getElementById('lga-city');
            const serialNoEl = document.getElementById('serial-no');
            const batchNoEl = document.getElementById('batch-no');
            const shelfLocationEl = document.getElementById('shelf-location');

            const fileTitle = (fileTitleEl?.value || '').trim();
            const fileNumber = getActiveFileNumber();
            const plotNumber = plotNumberEl?.value || '';
            const landUseTypeRaw = landUseTypeEl?.value || 'residential';
            const landUseType = toTitleCase(landUseTypeRaw);
            const lgaCity = lgaCityEl?.value || '';
            const serialNo = serialNoEl?.value || '';
            const batchNo = batchNoEl?.value || '';
            const shelfLocation = shelfLocationEl?.value || '';
            const districtValue = (districtSelect?.value === 'other') ? (customDistrictInput?.value || '') : (districtSelect?.value || '');
            
            // Phone number fields
            const countryCodeEl = document.getElementById('country-code');
            const phoneEl = document.getElementById('phone');
            const countryCode = countryCodeEl?.value || '+234';
            const phone = phoneEl?.value || '';
            
            // Resolve registry value (handle custom input when user selects Other)
            const registrySelectEl = document.getElementById('registry-select');
            const registryCustomEl = document.getElementById('custom-registry-input');
            const registryValueRaw = registrySelectEl?.value || '';
            const registryValue = (registryValueRaw && registryValueRaw.toString().toLowerCase() === 'other')
                ? (registryCustomEl?.value || '')
                : registryValueRaw;

            // Checkboxes
            const hasCofo = !!document.getElementById('has-cofo')?.checked;
            const hasTransaction = !!document.getElementById('has-transaction')?.checked;
            const isProblematic = !!document.getElementById('is-problematic')?.checked;
            const coOwnedPlot = !!document.getElementById('co-owned-plot')?.checked;
            const mergedPlot = !!document.getElementById('merged-plot')?.checked;

            if (!fileTitle) {
                alert('Please enter a file title.');
                return;
            }

            if (!fileNumber) {
                alert('Please select or enter a file number.');
                return;
            }

            if (districtSelect && districtSelect.value === 'other' && !customDistrictInput?.value.trim()) {
                alert('Please enter a district name.');
                customDistrictInput?.focus();
                return;
            }

            // Disable button to prevent double submission
            if (createFileBtn) {
                createFileBtn.disabled = true;
                createFileBtn.textContent = 'Creating...';
            }

            // Get file number mapping data
            const fileNumberMapping = getFileNumberMappingData();

                // Prepare data for submission with proper mapping
            const formData = {
                file_number: fileNumber,
                file_title: fileTitle,
                plot_number: plotNumber,
                land_use_type: landUseType,
                district: districtValue,
                registry: registryValue,
                lga: lgaCity,
                file_type: document.getElementById('file_type')?.value || null,
                dob: document.getElementById('dob')?.value || null,
                nin: document.getElementById('nin')?.value || null,
                tin: document.getElementById('tin')?.value || null,
                rc_no: document.getElementById('rc_no')?.value || null,
                residence_address: document.getElementById('residence_address')?.value || null,
                country_code: countryCode,
                phone: phone,
                has_cofo: hasCofo,
                has_transaction: hasTransaction,
                is_problematic: isProblematic,
                is_co_owned_plot: coOwnedPlot,
                is_merged: mergedPlot,
                serial_no: serialNo,
                batch_no: batchNo,
                shelf_location: shelfLocation,
                // Include file number mapping data
                ...fileNumberMapping,
                _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            };

            // Submit to server
            fetch('{{ route('fileindexing.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': formData._token
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close dialog
                    closeNewFileDialog();
                    
                    // Show success message
                    alert(`New file index created successfully!\n\nFile Number: ${fileNumber}\nTitle: ${fileTitle}\nType: ${landUseType}\nDistrict: ${districtValue}`);
                    
                    // Optionally redirect or refresh the page
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        // Refresh the current page to show the new file
                        window.location.reload();
                    }
                } else {
                    alert('Error creating file index: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating file index. Please try again.');
            })
            .finally(() => {
                // Re-enable button
                if (createFileBtn) {
                    createFileBtn.disabled = false;
                    createFileBtn.textContent = 'Create File Index';
                }
            });
        }

        // Event listeners (guarded)
        if (closeDialogBtn) closeDialogBtn.addEventListener('click', closeNewFileDialog);
        if (cancelBtn) cancelBtn.addEventListener('click', closeNewFileDialog);
        if (createFileBtn) createFileBtn.addEventListener('click', createNewFile);
        if (generateTrackingBtn && trackingIdInput) {
            generateTrackingBtn.addEventListener('click', function() {
                trackingIdInput.value = generateTrackingId();
            });
        }

        // Close dialog when clicking outside
        if (newFileDialogOverlay) {
            newFileDialogOverlay.addEventListener('click', function(e) {
                if (e.target === newFileDialogOverlay) {
                    closeNewFileDialog();
                }
            });
        }
 
        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && newFileDialogOverlay && !newFileDialogOverlay.classList.contains('hidden')) {
                closeNewFileDialog();
            }
        });

        // Initialize smart file number selector when dialog loads
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure the smart selector is initialized
            if (typeof initializeSmartFilenoSelector === 'function') {
                initializeSmartFilenoSelector();
            }
        });

    })();
    </script>

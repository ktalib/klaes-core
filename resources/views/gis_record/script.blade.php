<script>
    // Show the reason for change field only when change of ownership is Yes
    document.addEventListener('DOMContentLoaded', function() {
        const changeOfOwnershipSelect = document.getElementById('changeOfOwnership');
        const reasonForChangeField = document.getElementById('reasonForChange').parentNode;
        
        function toggleReasonField() {
            if (changeOfOwnershipSelect.value === 'Yes') {
                reasonForChangeField.style.display = 'block';
            } else {
                reasonForChangeField.style.display = 'none';
            }
        }
        
        // Initialize on page load
        toggleReasonField();
        
        // Listen for changes
        changeOfOwnershipSelect.addEventListener('change', toggleReasonField);
    });

    // Debug form data
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const debug = document.getElementById('formDebug');
        
        form.addEventListener('submit', function(e) {
            const formData = new FormData(form);
            let debugText = '';
            
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    debugText += `${key}: [File: ${value.name}]\n`;
                } else {
                    debugText += `${key}: ${value}\n`;
                }
            }
            
            debug.textContent = debugText;
            console.log(debugText);
            // Uncomment to stop form submission for debugging
            // e.preventDefault();
        });
    });

    // Primary file select functionality for unit GIS
    document.addEventListener('DOMContentLoaded', function() {
        // Only run this code on unit GIS form pages
        const primaryFileSelect = document.getElementById('primary_file_select');
        if (!primaryFileSelect) return;

        // Initialize Select2
        $(primaryFileSelect).select2({
            placeholder: 'Select Primary GIS FileNo here',
            minimumInputLength: 0, // Allow searching without any input
            ajax: {
                url: '{{ route('gis_record.search-files') }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term || '', // Send empty string for initial load
                        initial: params.term ? false : true // Flag for initial load
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(item) {
                            // Display file number in dropdown (without labels)
                            let displayText = '';
                            
                            if (item.mlsfNo) displayText = item.mlsfNo;
                            else if (item.kangisFileNo) displayText = item.kangisFileNo;
                            else if (item.NewKANGISFileno) displayText = item.NewKANGISFileno;
                            else displayText = 'File #' + item.id;
                            
                            return {
                                id: item.id,
                                text: displayText,
                                data: item
                            };
                        })
                    };
                },
                cache: true
            },
            // Ensure the selected value text is properly displayed
            templateSelection: function(data) {
                if (!data.id) return data.text; // For placeholder
                return data.text || 'Selected Primary GIS FileNo';
            }
        });

        // Trigger initial load of the dropdown options
        $.ajax({
            url: '{{ route('gis_record.search-files') }}',
            dataType: 'json',
            data: { initial: true },
            success: function(data) {
                // Process the data to the format Select2 needs
                const processedData = data.map(function(item) {
                    let displayText = '';
                    
                    if (item.mlsfNo) displayText = item.mlsfNo;
                    else if (item.kangisFileNo) displayText = item.kangisFileNo;
                    else if (item.NewKANGISFileno) displayText = item.NewKANGISFileno;
                    else displayText = 'File #' + item.id;
                    
                    return {
                        id: item.id,
                        text: displayText,
                        data: item
                    };
                });
                
                // Add the options to the select element
                if (processedData.length > 0) {
                    // Create a placeholder option
                    const placeholderOption = new Option('Select Primary GIS FileNo here', '', true, true);
                    $(primaryFileSelect).append(placeholderOption);
                    
                    // Add the file options
                    processedData.forEach(function(item) {
                        const option = new Option(item.text, item.id, false, false);
                        $(option).data('data', item.data);
                        $(primaryFileSelect).append(option);
                    });
                    
                    // Trigger change to update Select2
                    $(primaryFileSelect).trigger('change');
                }
            }
        });

        // Handle file selection
        $(primaryFileSelect).on('select2:select', function(e) {
            const data = e.params.data.data;
            
            // Set primary GIS ID
            document.getElementById('PrimaryGISID').value = data.id;
            
            // Get the selected text
            const selectedText = e.params.data.text || 'File #' + data.id;
            
            // Clear existing options and add the selected one to ensure it shows properly
            $(primaryFileSelect).empty();
            
            // Create and add the selected option
            const newOption = new Option(selectedText, data.id, true, true);
            $(primaryFileSelect).append(newOption);
            
            // This forces Select2 to use our option as the visible selection
            $(primaryFileSelect).val(data.id).trigger('change');
            
            // Update the summary header for primary file
            updatePrimaryFileSummary(data);
            
            // Populate form fields with primary data
            populateFormWithPrimaryData(data);
            
            // Show toast notification
            // showToast('Primary GIS data loaded successfully');

                Swal.fire({
                                title: 'Primary GIS Selected',
                                text: 'Primary GIS data loaded successfully.',
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
        });

        // Function to update the summary header for primary file
        function updatePrimaryFileSummary(data) {
            // Show the container
            const summaryContainer = document.getElementById('file-summary-container');
            if (summaryContainer) summaryContainer.classList.remove('hidden');
            
            // Update title
            const summaryTitle = document.getElementById('summary-title');
            if (summaryTitle) summaryTitle.textContent = 'Summary';
            
            // Show primary section, hide others
            const primarySection = document.getElementById('search-primary-file-summary');
            const motherSection = document.getElementById('mother-app-summary');
            const unitSection = document.getElementById('unit-file-summary');
            
            if (primarySection) primarySection.classList.remove('hidden');
            if (motherSection) motherSection.classList.add('hidden');
            if (unitSection) unitSection.classList.add('hidden');
            
            // Update primary file details
            if (document.getElementById('summary-primaryFileNo')) {
                let displayFileNo = '';
                if (data.mlsfNo) displayFileNo = data.mlsfNo;
                else if (data.kangisFileNo) displayFileNo = data.kangisFileNo;
                else if (data.NewKANGISFileno) displayFileNo = data.NewKANGISFileno;
                else displayFileNo = 'File #' + data.id;
                
                document.getElementById('summary-primaryFileNo').textContent = displayFileNo;
            }
            
            if (document.getElementById('summary-primaryId')) 
                document.getElementById('summary-primaryId').textContent = data.id || '-';
            
            // Update note
            if (document.getElementById('summary-note'))
                document.getElementById('summary-note').textContent = 'Primary file data has been loaded into the form below.';
        }

        // Function to populate form with primary data
        function populateFormWithPrimaryData(data) {
            // Store the primary GIS ID in the designated field
            if (document.getElementById('PrimaryGISID')) {
                document.getElementById('PrimaryGISID').value = data.id;
            }
            
            // Only update STFileNo in the unit form - this links the unit to the primary file
            // if (document.getElementById('STFileNo')) {
            //     // Set STFileNo to whichever file number is available (in order of preference)
            //     if (data.mlsfNo) document.getElementById('STFileNo').value = data.mlsfNo;
            //     else if (data.kangisFileNo) document.getElementById('STFileNo').value = data.kangisFileNo;
            //     else if (data.NewKANGISFileno) document.getElementById('STFileNo').value = data.NewKANGISFileno;
            // }
            
            // The rest of the data should go to the main form fields, not the unit form
            
            // Plot Information (main form)
            if (data.plotNo && document.getElementById('plotNo')) document.getElementById('plotNo').value = data.plotNo;
            if (data.blockNo && document.getElementById('blockNo')) document.getElementById('blockNo').value = data.blockNo;
            if (data.approvedPlanNo && document.getElementById('approvedPlanNo')) document.getElementById('approvedPlanNo').value = data.approvedPlanNo;
            if (data.tpPlanNo && document.getElementById('tpPlanNo')) document.getElementById('tpPlanNo').value = data.tpPlanNo;
            if (data.areaInHectares && document.getElementById('areaInHectares')) document.getElementById('areaInHectares').value = data.areaInHectares;
            if (data.landUse && document.getElementById('landUse')) document.getElementById('landUse').value = data.landUse;
            if (data.specifically && document.getElementById('specifically')) document.getElementById('specifically').value = data.specifically;
            
            // Location Information (main form)
            if (data.layoutName && document.getElementById('layoutName')) document.getElementById('layoutName').value = data.layoutName;
            if (data.districtName && document.getElementById('districtName')) document.getElementById('districtName').value = data.districtName;
            if (data.StateName && document.getElementById('StateName')) document.getElementById('StateName').value = data.StateName;
            if (data.streetName && document.getElementById('streetName')) document.getElementById('streetName').value = data.streetName;
            if (data.houseNo && document.getElementById('houseNo')) document.getElementById('houseNo').value = data.houseNo;
            if (data.houseType && document.getElementById('houseType')) document.getElementById('houseType').value = data.houseType;
            if (data.tenancy && document.getElementById('tenancy')) document.getElementById('tenancy').value = data.tenancy;
            
            // LGA selection
            if (data.lgaName) {
                const lgaSelectElement = document.getElementById('lgaName');
                if (lgaSelectElement) {
                    for (let i = 0; i < lgaSelectElement.options.length; i++) {
                        if (lgaSelectElement.options[i].value === data.lgaName) {
                            lgaSelectElement.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
            
            // Title Information (main form)
            if (data.oldTitleSerialNo && document.getElementById('oldTitleSerialNo')) 
                document.getElementById('oldTitleSerialNo').value = data.oldTitleSerialNo;
            if (data.oldTitlePageNo && document.getElementById('oldTitlePageNo')) 
                document.getElementById('oldTitlePageNo').value = data.oldTitlePageNo;
            if (data.oldTitleVolumeNo && document.getElementById('oldTitleVolumeNo')) 
                document.getElementById('oldTitleVolumeNo').value = data.oldTitleVolumeNo;
            if (data.deedsDate && document.getElementById('deedsDate')) 
                document.getElementById('deedsDate').value = data.deedsDate;
            if (data.deedsTime && document.getElementById('deedsTime')) 
                document.getElementById('deedsTime').value = data.deedsTime;
            if (data.certificateDate && document.getElementById('certificateDate')) 
                document.getElementById('certificateDate').value = data.certificateDate;
            if (data.CofOSerialNo && document.getElementById('CofOSerialNo')) 
                document.getElementById('CofOSerialNo').value = data.CofOSerialNo;
            if (data.titleIssuedYear && document.getElementById('titleIssuedYear')) 
                document.getElementById('titleIssuedYear').value = data.titleIssuedYear;
            
            // Owner Information (main form)
            if (data.originalAllottee && document.getElementById('originalAllottee')) 
                document.getElementById('originalAllottee').value = data.originalAllottee;
            if (data.addressOfOriginalAllottee && document.getElementById('addressOfOriginalAllottee')) 
                document.getElementById('addressOfOriginalAllottee').value = data.addressOfOriginalAllottee;
            if (data.changeOfOwnership && document.getElementById('changeOfOwnership')) 
                document.getElementById('changeOfOwnership').value = data.changeOfOwnership;
            if (data.reasonForChange && document.getElementById('reasonForChange')) 
                document.getElementById('reasonForChange').value = data.reasonForChange;
            if (data.currentAllottee && document.getElementById('currentAllottee')) 
                document.getElementById('currentAllottee').value = data.currentAllottee;
            if (data.addressOfCurrentAllottee && document.getElementById('addressOfCurrentAllottee')) 
                document.getElementById('addressOfCurrentAllottee').value = data.addressOfCurrentAllottee;
            if (data.titleOfCurrentAllottee && document.getElementById('titleOfCurrentAllottee')) 
                document.getElementById('titleOfCurrentAllottee').value = data.titleOfCurrentAllottee;
            if (data.phoneNo && document.getElementById('phoneNo')) 
                document.getElementById('phoneNo').value = data.phoneNo;
            if (data.emailAddress && document.getElementById('emailAddress')) 
                document.getElementById('emailAddress').value = data.emailAddress;
            if (data.occupation && document.getElementById('occupation')) 
                document.getElementById('occupation').value = data.occupation;
            if (data.nationality && document.getElementById('nationality')) 
                document.getElementById('nationality').value = data.nationality;
            if (data.CompanyRCNo && document.getElementById('CompanyRCNo')) 
                document.getElementById('CompanyRCNo').value = data.CompanyRCNo;
            
            // Show the reason for change field if Change of Ownership is "Yes"
            if (data.changeOfOwnership === 'Yes' && document.getElementById('reasonForChange')) {
                const reasonForChangeField = document.getElementById('reasonForChange').parentNode;
                reasonForChangeField.style.display = 'block';
            }
        }

        // Toast notification function
        function showToast(message) {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-600 text-white py-2 px-4 rounded-md shadow-lg z-50 transform transition-transform duration-300 translate-y-0';
            toast.innerHTML = message;
            document.body.appendChild(toast);
            
            // Fade out and remove after 3 seconds
            setTimeout(() => {
                toast.classList.add('translate-y-[-100%]', 'opacity-0');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }
    });
</script>

<!-- Include required libraries for Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
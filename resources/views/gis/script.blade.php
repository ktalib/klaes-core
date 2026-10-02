<script>
    // Immediately check and disable fields if on secondary GIS page
    (function() {
        const urlParams = new URLSearchParams(window.location.search);
        const isSecondary = urlParams.get('is') === 'secondary';
        console.log('URL Check - Is Secondary:', isSecondary);
        
        if (isSecondary) {
            console.log('Secondary GIS mode detected - fields should be disabled');
            // Run after a small delay to ensure DOM is fully loaded
            setTimeout(function() {
                disablePrimaryGISFields();
                // Add notice at the top of the form
                addFormNotice();
            }, 100);
        }
    })();

    // Function to add a notice at the top of the form
    function addFormNotice() {
        const formElement = document.querySelector('form');
        const headerElement = document.querySelector('form .bg-gray-50');
        
        // Remove any existing notice first
        const existingNotice = document.querySelector('form .bg-blue-50');
        if (existingNotice) {
            existingNotice.remove();
        }
        
        if (headerElement && formElement) {
            const noticeDiv = document.createElement('div');
            noticeDiv.className = 'bg-blue-50 text-blue-700 p-3 rounded-md mb-4';
            noticeDiv.innerHTML = '<p class="text-sm"><strong>Note:</strong> Primary GIS fields are read-only. Only Unit-specific information can be edited.</p>';
            formElement.insertBefore(noticeDiv, headerElement);
            console.log('Notice added to form');
        } else {
            console.warn('Could not find form header element to add notice');
        }
    }

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

        // Also check and disable fields again to be sure
        const urlParams = new URLSearchParams(window.location.search);
        const isSecondary = urlParams.get('is') === 'secondary';
        if (isSecondary) {
            console.log('DOMContentLoaded: Disabling primary fields for secondary GIS');
            disablePrimaryGISFields();
            addFormNotice();
        }
        
        // Always ensure Survey Plan Upload section works regardless of URL parameter
        ensureSurveyPlanSectionWorking();
        
        // For secondary GIS, automatically show the Survey Plan Upload section
        if (isSecondary) {
            setTimeout(function() {
                const surveyPlanSection = document.getElementById('surveyPlanSection');
                if (surveyPlanSection && surveyPlanSection.classList.contains('hidden')) {
                    console.log('Auto-showing Survey Plan section for secondary GIS');
                    toggleSurveyPlanSection();
                }
            }, 200);
        }
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

    // Function to disable all primary GIS fields
    function disablePrimaryGISFields() {
        console.log('Running disablePrimaryGISFields()');
        // Plot Information fields
        const primaryFields = [
            'plotNo', 'blockNo', 'approvedPlanNo', 'tpPlanNo', 'areaInHectares', 
            'landUse', 'specifically', 
            'layoutName', 'districtName', 'lgaName', 'StateName', 'streetName', 
            'houseNo', 'houseType', 'tenancy', 
            'oldTitleSerialNo', 'oldTitlePageNo', 'oldTitleVolumeNo', 'deedsDate', 
            'deedsTime', 'certificateDate', 'CofOSerialNo', 'titleIssuedYear', 
            'originalAllottee', 'addressOfOriginalAllottee', 'changeOfOwnership', 
            'reasonForChange', 'currentAllottee', 'addressOfCurrentAllottee', 
            'titleOfCurrentAllottee', 'phoneNo', 'emailAddress', 'occupation', 
            'nationality', 'CompanyRCNo'
        ];
        
        let fieldsDisabled = 0;
        primaryFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (field.tagName === 'SELECT') {
                    field.disabled = true;
                } else {
                    field.readOnly = true;
                }
                field.classList.add('bg-gray-100');
                fieldsDisabled++;
            }
        });
        
        console.log(`Fields disabled: ${fieldsDisabled} of ${primaryFields.length}`);
        
        // Hide the reason for change field regardless of changeOfOwnership value
        const reasonForChangeField = document.getElementById('reasonForChange');
        if (reasonForChangeField) {
            reasonForChangeField.parentNode.style.display = 'none';
        }
        
        // IMPORTANT: Ensure Survey Plan section is not affected by field disabling
        ensureSurveyPlanSectionWorking();
    }
    
    // Function to ensure Survey Plan section works properly on secondary pages
    function ensureSurveyPlanSectionWorking() {
        const surveyPlanSection = document.getElementById('surveyPlanSection');
        const surveyPlanInput = document.getElementById('surveyPlan');
        const surveyPlanLabel = document.querySelector('label[for="surveyPlan"]');
        const uploadButton = document.getElementById('uploadSurveyPlanBtn');
        
        console.log('Ensuring Survey Plan section works properly...');
        console.log('Survey Plan Section:', surveyPlanSection);
        console.log('Survey Plan Input:', surveyPlanInput);
        console.log('Survey Plan Label:', surveyPlanLabel);
        console.log('Upload Button:', uploadButton);
        
        if (surveyPlanInput) {
            // Ensure the input is not disabled
            surveyPlanInput.disabled = false;
            surveyPlanInput.readOnly = false;
            surveyPlanInput.classList.remove('bg-gray-100');
            surveyPlanInput.style.display = '';
            console.log('Survey Plan input enabled');
        }
        
        if (surveyPlanLabel) {
            // Ensure the label is clickable and visible
            surveyPlanLabel.style.pointerEvents = '';
            surveyPlanLabel.style.display = '';
            surveyPlanLabel.classList.remove('bg-gray-100', 'hidden');
            console.log('Survey Plan label ensured visible');
        }
        
        if (uploadButton) {
            // Ensure the upload button is enabled and visible
            uploadButton.disabled = false;
            uploadButton.style.display = '';
            uploadButton.classList.remove('bg-gray-100', 'hidden');
            console.log('Upload Survey Plan button enabled');
        }
        
        if (surveyPlanSection) {
            // Ensure the section itself is not disabled and all child elements are visible
            surveyPlanSection.style.pointerEvents = '';
            surveyPlanSection.classList.remove('bg-gray-100');
            
            // Force show all child elements in the survey plan section
            const allChildElements = surveyPlanSection.querySelectorAll('*');
            allChildElements.forEach(element => {
                element.style.display = '';
                element.classList.remove('hidden');
            });
            
            console.log('Survey Plan section ensured interactive with all children visible');
        }
        
        // Additional check: ensure the upload area is visible
        const uploadArea = document.querySelector('label[for="surveyPlan"]');
        if (uploadArea) {
            uploadArea.style.display = 'flex';
            uploadArea.classList.remove('hidden');
            console.log('Upload area forced visible');
        }
    }

    // Toggle Survey Plan Section
function toggleSurveyPlanSection() {
    const section = document.getElementById('surveyPlanSection');
    const button = document.getElementById('uploadSurveyPlanBtn');
    
    if (section.classList.contains('hidden')) {
        section.classList.remove('hidden');
        button.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            Hide Survey Plan Upload
        `;
        // Scroll to the section
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        section.classList.add('hidden');
        button.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            Upload Survey Plan
        `;
    }
}
</script>

<script>
    // Run one final check after page is fully loaded
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const isSecondary = urlParams.get('is') === 'secondary';
        if (isSecondary) {
            console.log('Window onload: Final check for disabling primary fields');
            disablePrimaryGISFields();
            addFormNotice();
            
            // Also check if fields were properly disabled
            setTimeout(function() {
                const plotField = document.getElementById('plotNo');
                if (plotField && !plotField.readOnly) {
                    console.warn('Fields were not properly disabled - trying again');
                    disablePrimaryGISFields();
                }
            }, 500);
        }
        
        // Always ensure Survey Plan Upload section works regardless of URL parameter
        ensureSurveyPlanSectionWorking();
        
        // For secondary GIS, automatically show the Survey Plan Upload section
        if (isSecondary) {
            setTimeout(function() {
                const surveyPlanSection = document.getElementById('surveyPlanSection');
                if (surveyPlanSection && surveyPlanSection.classList.contains('hidden')) {
                    console.log('Window onload: Auto-showing Survey Plan section for secondary GIS');
                    toggleSurveyPlanSection();
                }
            }, 500);
        }
        
        // Additional check to make sure the Upload Survey Plan button is always visible and functional
        setTimeout(function() {
            const uploadButton = document.getElementById('uploadSurveyPlanBtn');
            if (uploadButton) {
                uploadButton.style.display = '';
                uploadButton.disabled = false;
                console.log('Upload Survey Plan button ensured visible and functional');
            }
        }, 1000);
    };
</script>

<!-- Include required libraries for Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all form inputs and disable them initially
    const formInputs = document.querySelectorAll('form input:not([type="hidden"]):not([type="submit"])');
    
    const filenoSelect = document.getElementById('fileno-select');
    const saveButton = document.getElementById('saveButton');
    
    // IDs of dropdowns/fields to control
    const controlledFields = [
        'landUse',
        'specifically',
        'lgaName'
    ];
    let selectedApplication = null;
    const isSecondary = '{{ request()->get('is') }}' === 'secondary';
    
    // Disable all form inputs initially for secondary surveys
    if (isSecondary && filenoSelect) {
        formInputs.forEach(input => {
            input.disabled = true;
        });
        // Explicitly disable the controlled dropdowns/fields
        controlledFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = true;
        });
    }

    // Initialize Select2 for fileno selection (only for secondary surveys)
    if (filenoSelect && isSecondary) {
        $(filenoSelect).select2({
            placeholder: "Search for a file number...",
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: '{{ route('attribution.search-fileno') }}',
                dataType: 'json',
                delay: 250,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                data: function(params) {
                    return {
                        fileno: params.term || '',
                        type: 'secondary',
                        initial: params.term ? false : true
                    };
                },
                processResults: function(data, params) {
                    let results = [];
                    
                    if (data.success && data.application) {
                        results.push({
                            id: data.application.id,
                            text: data.application.fileno,
                            application: data.application
                        });
                    } else if (data.success && data.applications) {
                        results = data.applications.map(app => {
                            return {
                                id: app.id,
                                text: app.fileno + (app.applicant_type === 'individual' ? 
                                       ' - ' + app.first_name + ' ' + app.surname : 
                                       app.applicant_type === 'corporate' ? 
                                       ' - ' + app.corporate_name : ''),
                                application: app
                            };
                        });
                    }
                    
                    return {
                        results: results,
                        pagination: {
                            more: data.pagination && data.pagination.more
                        }
                    };
                },
                cache: true
            }
        });

        // Trigger initial data load when dropdown is opened for the first time
        $(filenoSelect).on('select2:open', function() {
            if (!$(filenoSelect).data('initial-load-done')) {
                const $search = $('.select2-search__field');
                $search.val('');
                $search.trigger('input');
                $(filenoSelect).data('initial-load-done', true);
            }
        });

        // Handle select change
        $(filenoSelect).on('select2:select', function(e) {
            const data = e.params.data;
            selectedApplication = data.application;
            
            if (selectedApplication) {
                // Enable all form inputs
                formInputs.forEach(input => {
                    input.disabled = false;
                });
                // Explicitly enable the controlled dropdowns/fields
                controlledFields.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = false;
                });
                
                // Populate form fields from selected application
                populateGISFormFields(selectedApplication);
                
                // Show success message
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Application Selected',
                        text: 'The form has been unlocked. You can now enter GIS details.',
                        icon: 'success',
                        confirmButtonText: 'Continue'
                    });
                }
            }
        });

        // Handle clear event
        $(filenoSelect).on('select2:clear', function() {
            // Disable all form inputs
            formInputs.forEach(input => {
                input.disabled = true;
            });
            // Explicitly disable the controlled dropdowns/fields
            controlledFields.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.disabled = true;
            });
            
            selectedApplication = null;
        });
    }

    // Initialize Primary Survey Select2 (only for secondary/unit surveys)
    if (isSecondary) {
        const primarySurveySelect = document.getElementById('primary-survey-select');
        
        if (primarySurveySelect) {
            $(primarySurveySelect).select2({
                placeholder: "Search for a Primary Survey FileNo...",
                allowClear: true,
                minimumInputLength: 0,
                ajax: {
                    url: '{{ route('attribution.fetch-primary-surveys') }}',
                    dataType: 'json',
                    delay: 250,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    data: function(params) {
                        return {
                            search: params.term || '',
                            initial: params.term ? false : true
                        };
                    },
                    processResults: function(data, params) {
                        let results = [];
                        
                        if (data.success && data.surveys && data.surveys.length > 0) {
                            results = data.surveys.map(survey => {
                                let displayText = survey.fileno || 'No File No';
                                
                                if (survey.survey_type) {
                                    displayText += ' | ' + survey.survey_type;
                                }
                                
                                if (survey.layout_name) {
                                    displayText += ' | ' + survey.layout_name;
                                }
                                
                                displayText += ' | Plot: ' + (survey.plot_no || 'N/A');
                                displayText += ' | Block: ' + (survey.block_no || 'N/A');
                                
                                return {
                                    id: survey.ID,
                                    text: displayText,
                                    survey: survey
                                };
                            });
                        }
                        
                        return {
                            results: results,
                            pagination: {
                                more: data.pagination && data.pagination.more
                            }
                        };
                    },
                    cache: true
                }
            });
            
            // Trigger initial data load when dropdown is opened
            $(primarySurveySelect).on('select2:open', function() {
                if (!$(primarySurveySelect).data('initial-load-done')) {
                    const $search = $('.select2-search__field');
                    $search.val('');
                    $search.trigger('input');
                    $(primarySurveySelect).data('initial-load-done', true);
                }
            });
            
            // Handle Primary Survey selection
            $(primarySurveySelect).on('select2:select', function(e) {
                const data = e.params.data;
                const surveyId = data.id;
                
                // Fetch detailed survey data
                fetch(`{{ url('attribution/primary-survey-details') }}/${surveyId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.survey) {
                            // Auto-populate form fields from Primary Survey
                            populateFromPrimarySurvey(data.survey);
                            
                            // Show success message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'Primary Survey Selected',
                                    text: 'Form fields have been populated based on the selected Primary Survey.',
                                    icon: 'success',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    timerProgressBar: true
                                });
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching survey details:', error);
                    });
            });
        }
    }

    // Function to populate GIS form fields from selected application
    function populateGISFormFields(application) {
        // Populate basic information
        if (application.land_use) {
            const landUseInput = document.getElementById('landUse');
            if (landUseInput) {
                landUseInput.value = application.land_use;
            }
        }
        
        // Add more field mappings as needed based on your application structure
    }

    // Function to populate form fields from Primary Survey
    function populateFromPrimarySurvey(survey) {
        // Populate plot information
        if (survey.plot_no) {
            const plotNoInput = document.getElementById('plotNo');
            if (plotNoInput) {
                plotNoInput.value = survey.plot_no;
            }
        }
        
        if (survey.block_no) {
            const blockNoInput = document.getElementById('blockNo');
            if (blockNoInput) {
                blockNoInput.value = survey.block_no;
            }
        }
        
        if (survey.approved_plan_no) {
            const approvedPlanNoInput = document.getElementById('approvedPlanNo');
            if (approvedPlanNoInput) {
                approvedPlanNoInput.value = survey.approved_plan_no;
            }
        }
        
        if (survey.tp_plan_no) {
            const tpPlanNoInput = document.getElementById('tpPlanNo');
            if (tpPlanNoInput) {
                tpPlanNoInput.value = survey.tp_plan_no;
            }
        }
        
        // Populate location information
        if (survey.layout_name) {
            const layoutNameInput = document.getElementById('layoutName');
            if (layoutNameInput) {
                layoutNameInput.value = survey.layout_name;
            }
        }
        
        if (survey.district_name) {
            const districtNameInput = document.getElementById('districtName');
            if (districtNameInput) {
                districtNameInput.value = survey.district_name;
            }
        }
        
        if (survey.lga_name) {
            const lgaNameInput = document.getElementById('lgaName');
            if (lgaNameInput) {
                lgaNameInput.value = survey.lga_name;
            }
        }
    }
});
</script>

<script>
// Alpine.js Survey Plan Upload Component
function surveyPlanUpload() {
    return {
        // Component data and methods can be added here if needed
        init() {
            console.log('Survey Plan Upload component initialized');
        }
    }
}

// Survey Plan Upload and Form Validation
let surveyPlanUploaded = false;

// Handle survey plan upload
function handleSurveyPlanUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file size (10MB max)
    const maxSize = 10 * 1024 * 1024; // 10MB in bytes
    if (file.size > maxSize) {
        alert('File size must be less than 10MB');
        input.value = '';
        return;
    }
    
    // Validate file type
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'application/dwg', 'application/dxf'];
    const fileExtension = file.name.split('.').pop().toLowerCase();
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'];
    
    if (!allowedExtensions.includes(fileExtension)) {
        alert('Please upload a valid file type: PDF, JPG, PNG, DWG, or DXF');
        input.value = '';
        return;
    }
    
    surveyPlanUploaded = true;
    showSurveyPlanPreview(file);
    validateForm();
}

// Check if any survey plan is uploaded (either through special section or regular file input)
function checkSurveyPlanUploaded() {
    const specialSurveyPlan = document.getElementById('surveyPlan');
    const regularSurveyPlan = document.getElementById('SurveyPlan');
    
    return (specialSurveyPlan && specialSurveyPlan.files.length > 0) || 
           (regularSurveyPlan && regularSurveyPlan.files.length > 0) ||
           surveyPlanUploaded;
}

// Show survey plan preview
function showSurveyPlanPreview(file) {
    const previewContainer = document.getElementById('surveyPlanPreview');
    const previewContent = document.getElementById('previewContent');
    const fileInfo = document.getElementById('fileInfo');
    
    previewContainer.classList.remove('hidden');
    
    // File info
    const fileSize = (file.size / 1024 / 1024).toFixed(2);
    fileInfo.innerHTML = `
        <div class="flex items-center space-x-4">
            <span><strong>File:</strong> ${file.name}</span>
            <span><strong>Size:</strong> ${fileSize} MB</span>
            <span><strong>Type:</strong> ${file.type || 'Unknown'}</span>
        </div>
    `;
    
    // Preview content based on file type
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    if (['jpg', 'jpeg', 'png'].includes(fileExtension)) {
        // Image preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContent.innerHTML = `
                <img src="${e.target.result}" alt="Survey Plan Preview" 
                     class="max-w-full max-h-64 mx-auto rounded-lg border border-gray-200">
            `;
        };
        reader.readAsDataURL(file);
    } else if (fileExtension === 'pdf') {
        // PDF preview
        previewContent.innerHTML = `
            <div class="flex flex-col items-center space-y-2">
                <svg class="w-16 h-16 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-sm text-gray-600">PDF Document</p>
                <p class="text-xs text-gray-500">Preview not available</p>
            </div>
        `;
    } else {
        // Other file types (DWG, DXF)
        previewContent.innerHTML = `
            <div class="flex flex-col items-center space-y-2">
                <svg class="w-16 h-16 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-sm text-gray-600">${fileExtension.toUpperCase()} File</p>
                <p class="text-xs text-gray-500">Preview not available</p>
            </div>
        `;
    }
}

// Remove survey plan
function removeSurveyPlan() {
    const input = document.getElementById('surveyPlan');
    const previewContainer = document.getElementById('surveyPlanPreview');
    
    input.value = '';
    previewContainer.classList.add('hidden');
    surveyPlanUploaded = false;
    validateForm();
}

// Form validation
function validateForm() {
    const form = document.querySelector('form');
    const saveButton = document.getElementById('saveButton');
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    let allFieldsFilled = true;
    
    // Check all required fields
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            allFieldsFilled = false;
        }
    });
    
    // Check if survey plan is uploaded (either method)
    const surveyPlanUploaded = checkSurveyPlanUploaded();
    const formValid = allFieldsFilled && surveyPlanUploaded;
    
    if (formValid) {
        saveButton.disabled = false;
        saveButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
        saveButton.classList.add('bg-green-600', 'hover:bg-green-700');
    } else {
        saveButton.disabled = true;
        saveButton.classList.add('bg-gray-400', 'cursor-not-allowed');
        saveButton.classList.remove('bg-green-600', 'hover:bg-green-700');
    }
}

// Smart Counters and Progress Tracking
function updateProgressCounters() {
    const form = document.querySelector('form');
    const allRequiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    // Count total and filled fields
    let totalFields = allRequiredFields.length;
    let filledFields = 0;
    
    allRequiredFields.forEach(field => {
        if (field.value && field.value.trim() !== '') {
            filledFields++;
        }
    });
    
    // Update main progress counter
    const progressPercentage = Math.round((filledFields / totalFields) * 100);
    document.getElementById('progressPercentage').textContent = progressPercentage + '%';
    document.getElementById('filledFields').textContent = filledFields;
    document.getElementById('totalFields').textContent = totalFields;
    document.getElementById('progressBar').style.width = progressPercentage + '%';
    
    // Update section counters
    updateSectionCounter('plotSectionCounter', [
        'plotNo', 'blockNo', 'approvedPlanNo', 'tpPlanNo', 'areaInHectares', 'landUse', 'specifically'
    ]);
    
    updateSectionCounter('locationSectionCounter', [
        'layoutName', 'districtName', 'lgaName', 'StateName', 'streetName', 'houseNo', 'houseType', 'tenancy'
    ]);
    
    updateSectionCounter('titleSectionCounter', [
        'oldTitleSerialNo', 'oldTitlePageNo', 'oldTitleVolumeNo', 'deedsDate', 'deedsTime', 'certificateDate', 'CofOSerialNo', 'titleIssuedYear'
    ]);
    
    updateSectionCounter('ownerSectionCounter', [
        'originalAllottee', 'addressOfOriginalAllottee', 'changeOfOwnership', 'reasonForChange', 'currentAllottee', 'addressOfCurrentAllottee', 'titleOfCurrentAllottee', 'phoneNo', 'emailAddress', 'occupation', 'nationality', 'CompanyRCNo'
    ]);
}

function updateSectionCounter(counterId, fieldIds) {
    const counterElement = document.getElementById(counterId);
    if (!counterElement) return;
    
    let totalFields = fieldIds.length;
    let filledFields = 0;
    
    fieldIds.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && field.value && field.value.trim() !== '') {
            filledFields++;
        }
    });
    
    counterElement.textContent = `${filledFields}/${totalFields}`;
    
    // Update section styling based on completion
    const sectionElement = counterElement.closest('.bg-gradient-to-r');
    if (sectionElement) {
        if (filledFields === totalFields) {
            sectionElement.classList.add('ring-2', 'ring-green-300');
            sectionElement.classList.remove('ring-yellow-300', 'ring-red-300');
        } else if (filledFields > 0) {
            sectionElement.classList.add('ring-2', 'ring-yellow-300');
            sectionElement.classList.remove('ring-green-300', 'ring-red-300');
        } else {
            sectionElement.classList.remove('ring-2', 'ring-green-300', 'ring-yellow-300');
        }
    }
}

// Add event listeners for form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    // Debug: Check if survey plan section exists
    const surveyPlanSection = document.getElementById('surveyPlanSection');
    console.log('Survey Plan Section found on DOMContentLoaded:', surveyPlanSection);
    if (surveyPlanSection) {
        console.log('Survey Plan Section classes:', surveyPlanSection.className);
        console.log('Survey Plan Section style display:', surveyPlanSection.style.display);
        console.log('Survey Plan Section computed style:', window.getComputedStyle(surveyPlanSection).display);
    }
    
    // Add event listeners to all required fields
    requiredFields.forEach(field => {
        field.addEventListener('input', function() {
            validateForm();
            updateProgressCounters();
        });
        field.addEventListener('change', function() {
            validateForm();
            updateProgressCounters();
        });
    });
    
    // Add event listeners to survey plan file inputs
    const specialSurveyPlan = document.getElementById('surveyPlan');
    const regularSurveyPlan = document.getElementById('SurveyPlan');
    
    if (specialSurveyPlan) {
        specialSurveyPlan.addEventListener('change', function() {
            validateForm();
            updateProgressCounters();
        });
    }
    
    if (regularSurveyPlan) {
        regularSurveyPlan.addEventListener('change', function() {
            validateForm();
            updateProgressCounters();
        });
    }
    
    // Initial validation and counter update
    validateForm();
    updateProgressCounters();
    
    // Add visual feedback for required fields
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.classList.add('border-red-300', 'bg-red-50');
            } else {
                this.classList.remove('border-red-300', 'bg-red-50');
                this.classList.add('border-green-300', 'bg-green-50');
            }
        });
        
        field.addEventListener('focus', function() {
            this.classList.remove('border-red-300', 'bg-red-50', 'border-green-300', 'bg-green-50');
        });
    });
    
    // Update counters every 2 seconds to catch any missed changes
    setInterval(updateProgressCounters, 2000);
});

// Enhanced form submission with validation
function updateFormFileData() {
    // Check if any survey plan is uploaded (either method)
    if (!checkSurveyPlanUploaded()) {
        alert('Please upload a survey plan before submitting the form.');
        return false;
    }
    
    const form = document.querySelector('form');
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    let missingFields = [];
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            const label = field.closest('.space-y-2')?.querySelector('label')?.textContent || field.name;
            missingFields.push(label);
        }
    });
    
    if (missingFields.length > 0) {
        alert('Please fill in the following required fields:\n\n' + missingFields.join('\n'));
        return false;
    }
    
    // Show loading state
    const saveButton = document.getElementById('saveButton');
    saveButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Saving...
    `;
    saveButton.disabled = true;
    
    return true;
}
</script>
/**
 * Primary Application Form - AJAX Submission Handler
 * Handles form submission without page reload using AJAX
 */

console.log('📝 Form submission handler loaded');

// Form submission configuration
const FormSubmission = {
    form: null,
    submitUrl: null,
    isSubmitting: false,
    draftManager: null,
    invalidFields: [],

    // Initialize the form submission handler
    init: function() {
        console.log('🔧 Initializing form submission handler...');
        
        this.form = document.getElementById('primaryApplicationForm');
        if (!this.form) {
            console.error('❌ Primary form not found!');
            return;
        }

        // Get the submit URL from global variable (ignore form action since it's set to javascript:void(0))
        const draftEndpoints = window.PRIMARY_DRAFT_ENDPOINTS || {};
        this.submitUrl = draftEndpoints.submit || window.FORM_SUBMIT_URL || '/primaryform';
        console.log('🎯 Submit URL:', this.submitUrl);
        
        // Validate that we have a proper URL
        if (!this.submitUrl || this.submitUrl.includes('javascript:')) {
            console.error('❌ Invalid submit URL:', this.submitUrl);
            this.submitUrl = '/primaryform'; // Fallback
            console.log('🔄 Using fallback URL:', this.submitUrl);
        }

        // Initialize scan upload configuration if available
        if (window.SCAN_UPLOAD_CONFIG) {
            window.SCAN_UPLOAD_CONFIG.endpoints.upload = this.submitUrl;
            console.log('🔧 Scan upload endpoint configured:', this.submitUrl);
        }

        // PREVENT ALL FORM SUBMISSIONS - AJAX ONLY
        this.form.addEventListener('submit', (event) => {
            console.log('🚫 Preventing default form submission');
            event.preventDefault();
            event.stopPropagation();
            return false;
        });
        
        // Override any existing form handlers
        this.form.onsubmit = (event) => {
            console.log('🚫 Overriding onsubmit handler');
            event.preventDefault();
            return false;
        };
        
        // Add submit button click handler
        this.attachSubmitButtonHandler();
        
        console.log('✅ Form submission handler initialized');
    },

    // Attach submit button handler
    attachSubmitButtonHandler: function() {
        // Look for submit buttons in the summary step
        const submitButtons = document.querySelectorAll('[onclick*="submitForm"], button[type="submit"], .submit-form-btn');
        
        submitButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.submitForm();
            });
        });

        // Also handle any existing onclick submit handlers
        const summarySubmitBtn = document.querySelector('.bg-green-600, .bg-green-500');
        if (summarySubmitBtn && summarySubmitBtn.textContent.includes('Submit')) {
            summarySubmitBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.submitForm();
            });
        }
    },

    // Handle form submission event
    handleSubmit: function(event) {
        console.log('🚫 Form submit event intercepted - preventing default');
        event.preventDefault();
        event.stopPropagation();
        return false;
    },

    // Main form submission function
    submitForm: async function() {
        if (this.isSubmitting) {
            console.log('⏳ Form already submitting, please wait...');
            return;
        }

        console.log('🚀 Starting form submission...');
        this.isSubmitting = true;

        // Show loading state
        this.showLoadingState();

        // Validate form before submission
        if (!this.validateForm()) {
            this.hideLoadingState();
            this.isSubmitting = false;
            return;
        }

        // Ensure pending draft changes are saved before final submission
        try {
            await this.prepareDraftState();
        } catch (error) {
            console.warn('⚠️ Unable to persist draft before submit:', error);
        }

        // Collect form data
        const formData = this.collectFormData();
        
        // Submit via AJAX
        this.performAjaxSubmission(formData);
    },

    clearValidationStyles: function() {
        this.invalidFields.forEach(field => {
            field.classList.remove('ring-2', 'ring-red-500', 'border-red-500');
            field.classList.add('border-gray-300');
        });
        this.invalidFields = [];
    },

    markFieldInvalid: function(field) {
        if (!field) {
            return;
        }

        field.classList.add('ring-2', 'ring-red-500', 'border-red-500');
        this.invalidFields.push(field);
    },

    getFieldValue: function(name) {
        const elements = this.form.querySelectorAll(`[name="${name}"]`);
        if (!elements.length) {
            return '';
        }

        const element = elements[0];

        if (element.type === 'radio') {
            const checked = Array.from(elements).find(el => el.checked);
            return checked ? checked.value : '';
        }

        if (element.type === 'checkbox') {
            return Array.from(elements).filter(el => el.checked).map(el => el.value);
        }

        return element.value ? element.value.trim() : '';
    },

    validateForm: function() {
        this.clearValidationStyles();

        const errors = [];

        // Debug: Check if form exists and has expected structure
        if (!this.form) {
            console.error('❌ Form not found during validation!');
            errors.push('Form not properly initialized. Please refresh the page.');
            return false;
        }

        // Debug: Count total form elements
        const allInputs = this.form.querySelectorAll('input, select, textarea');
        console.log(`🔍 Form validation: Found ${allInputs.length} form controls`);

        // Applicant type validation removed - no longer required
        console.log('ℹ️ Applicant type validation skipped - form will submit without this requirement');

        // Log current field values for debugging (but don't validate them)
        const schemeNoField = this.form.querySelector('[name="scheme_no"]');
        const propertyStreetField = this.form.querySelector('[name="property_street_name"]');
        const propertyLgaField = this.form.querySelector('[name="property_lga"]');
        const propertyStateField = this.form.querySelector('[name="property_state"]');
        
        console.log('📊 Current field values:');
        console.log('  scheme_no:', schemeNoField ? schemeNoField.value : 'NOT FOUND');
        console.log('  property_street_name:', propertyStreetField ? propertyStreetField.value : 'NOT FOUND');
        console.log('  property_lga:', propertyLgaField ? propertyLgaField.value : 'NOT FOUND');
        console.log('  property_state:', propertyStateField ? propertyStateField.value : 'NOT FOUND');

        // Validate buyer records if present
        const buyerRows = document.querySelectorAll('.buyer-row');
        console.log(`Found ${buyerRows.length} buyer rows to validate`);
        
        if (buyerRows.length > 0) {
            buyerRows.forEach((row, index) => {
                const firstName = row.querySelector('input[name*="[firstName]"]');
                const surname = row.querySelector('input[name*="[surname]"]');
                const unitNo = row.querySelector('input[name*="[unit_no]"]');
                const sectionNumber = row.querySelector('input[name*="[sectionNumber]"]');

                if (!firstName || !firstName.value.trim()) {
                    errors.push(`Buyer ${index + 1}: First Name is required.`);
                    this.markFieldInvalid(firstName);
                }
                if (!surname || !surname.value.trim()) {
                    errors.push(`Buyer ${index + 1}: Surname is required.`);
                    this.markFieldInvalid(surname);
                }
                if (!unitNo || !unitNo.value.trim()) {
                    errors.push(`Buyer ${index + 1}: Unit Number is required.`);
                    this.markFieldInvalid(unitNo);
                }
                if (!sectionNumber || !sectionNumber.value.trim()) {
                    errors.push(`Buyer ${index + 1}: Section Number is required.`);
                    this.markFieldInvalid(sectionNumber);
                }
            });
        }

        if (errors.length > 0) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Please fix the following',
                    html: `<ul class="text-left list-disc pl-6 space-y-1">${errors.map(err => `<li>${err}</li>`).join('')}</ul>`
                });
            } else {
                alert(errors.join('\n'));
            }

            return false;
        }

        return true;
    },

    prepareDraftState: function() {
        const manager = this.getDraftManager();
        if (manager && manager.state && manager.state.hasPendingChanges) {
            return manager.manualSave({ flash: false });
        }

        return Promise.resolve();
    },

    getDraftManager: function() {
        if (!this.draftManager && window.PrimaryDraftAutosave) {
            this.draftManager = window.PrimaryDraftAutosave;
        }

        return this.draftManager;
    },

    // Collect all form data including files
    collectFormData: function() {
        console.log('📋 Collecting form data...');
        
        const formData = new FormData(this.form);

        // Log what FormData captured initially
        console.log('📋 Initial FormData entries:', Array.from(formData.entries()).map(([key, value]) => {
            if (value instanceof File) {
                return `${key} = [File:${value.name}, size:${value.size}]`;
            }
            return `${key} = ${typeof value === 'string' ? value.substring(0, 80) : value}`;
        }));

        // Remove CSV helper inputs that should never hit the backend
        const csvFields = ['csv_file', 'csvFileInput'];
        csvFields.forEach(fieldName => {
            if (formData.has(fieldName)) {
                formData.delete(fieldName);
                console.log(`🚫 Removed CSV helper field: ${fieldName}`);
            }
        });

        // Some CSV widgets use indexed names – remove any dynamic keys that contain `csv`
        Array.from(formData.keys())
            .filter(key => key.toLowerCase().includes('csv'))
            .forEach(key => {
                formData.delete(key);
                console.log(`🚫 Removed CSV-derived field: ${key}`);
            });

        const ensureFieldValue = (fieldName, formDatasetKey = null) => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            const datasetDefault = field?.dataset?.default ?? '';
            const datasetFallback = formDatasetKey ? (this.form.dataset?.[formDatasetKey] ?? '') : '';
            const resolvedValue = (field?.value || '').trim() || datasetDefault || datasetFallback;

            if (resolvedValue) {
                formData.set(fieldName, resolvedValue);
                console.log(`✅ Ensured ${fieldName}:`, resolvedValue);
            } else {
                console.warn(`⚠️ ${fieldName} is empty after merging defaults`);
            }
        };

        // Ensure critical ST API fields are populated
        ensureFieldValue('land_use', 'defaultLandUse');
        ensureFieldValue('np_fileno', 'defaultNpFileno');
        ensureFieldValue('fileno');
        ensureFieldValue('applicant_type');

        // Critical contact + property details should never be dropped – guarantee they're present
        [
            'address', 'address_house_no', 'owner_street_name', 'owner_district', 'owner_lga', 'owner_state',
            'scheme_no', 'property_house_no', 'property_plot_no', 'property_street_name',
            'property_district', 'property_lga', 'property_state', 'plot_size',
            'units_count', 'blocks_count', 'sections_count', 'phone', 'phone_alternate', 'email',
            'contact_address', 'property_address', 'identification_type'
        ].forEach(field => ensureFieldValue(field));

        const ensureFromSelector = (fieldName, selector) => {
            const element = document.querySelector(selector);
            if (!element) {
                return;
            }

            const value = element.value ? element.value.trim() : '';
            if (value) {
                formData.set(fieldName, value);
                console.log(`🏷️ Captured ${fieldName} from ${selector}:`, value);
            }
        };

        // FORCE capture all visible form fields - don't rely on FormData
        const forceCapture = (fieldName, selector) => {
            const element = document.querySelector(selector);
            if (element) {
                const value = element.value ? element.value.trim() : '';
                if (value) {
                    formData.set(fieldName, value);
                    console.log(`✅ FORCE captured ${fieldName}:`, value.substring(0, 50));
                } else {
                    console.warn(`⚠️ Field ${fieldName} (${selector}) is empty`);
                }
            } else {
                console.warn(`⚠️ Field ${fieldName} (${selector}) NOT FOUND in DOM`);
            }
        };

        // Explicitly capture owner/contact fields
        forceCapture('address_house_no', '#ownerHouseNo');
        forceCapture('owner_street_name', '#ownerStreetName');
        forceCapture('owner_district', '#ownerDistrict');
        forceCapture('owner_lga', '#ownerLga');
        forceCapture('owner_state', '#ownerState');
        forceCapture('phone', 'input[name="phone"]');
        forceCapture('phone_alternate', 'input[name="phone_alternate"]');
        forceCapture('email', 'input[name="email"]');

        // Capture property address components
        forceCapture('scheme_no', '#schemeNumber');
        forceCapture('property_house_no', '#propertyHouseNo');
        forceCapture('property_plot_no', '#propertyPlotNo');
        forceCapture('property_street_name', '#propertyStreetName');
        forceCapture('property_district', '#propertyDistrict');
        forceCapture('property_lga', '#propertyLga');
        forceCapture('property_state', '#propertyState');
        forceCapture('plot_size', 'input[name="plot_size"]');

        // Property map pin
        forceCapture('latitude', '#pfPropertyLatitude');
        forceCapture('longitude', '#pfPropertyLongitude');

        // Capture numerical counts
        forceCapture('units_count', 'input[name="units_count"]');
        forceCapture('blocks_count', 'input[name="blocks_count"]');
        forceCapture('sections_count', 'input[name="sections_count"]');
        
        // Capture payment information
        forceCapture('application_fee', 'input[name="application_fee"]');
        forceCapture('processing_fee', 'input[name="processing_fee"]');
        forceCapture('site_plan_fee', 'input[name="site_plan_fee"]');
        
        // Capture individual payment tracking fields
        forceCapture('application_fee_payment_date', 'input[name="application_fee_payment_date"]');
        forceCapture('application_fee_receipt_number', 'input[name="application_fee_receipt_number"]');
        forceCapture('processing_fee_payment_date', 'input[name="processing_fee_payment_date"]');
        forceCapture('processing_fee_receipt_number', 'input[name="processing_fee_receipt_number"]');
        forceCapture('site_plan_fee_payment_date', 'input[name="site_plan_fee_payment_date"]');
        forceCapture('site_plan_fee_receipt_number', 'input[name="site_plan_fee_receipt_number"]');

        // Capture contact summary helpers if present
        forceCapture('contact_address', '#contactAddressDisplay');
        forceCapture('property_address', '#propertyAddressDisplay');
        
        // Capture identification type (radio buttons)
        const identType = document.querySelector('input[name="identification_type"]:checked');
        if (identType) {
            formData.set('identification_type', identType.value);
            console.log('✅ FORCE captured identification_type:', identType.value);
        }
        
        // Capture ownership type
        const ownershipType = document.querySelector('input[name="ownership_type"]:checked');
        if (ownershipType) {
            formData.set('ownership_type', ownershipType.value);
            console.log('✅ FORCE captured ownership_type:', ownershipType.value);
        }
        
        // Capture property types - FIXED: Using radio buttons not select elements
        const commercialType = document.querySelector('input[name="commercial_type"]:checked');
        if (commercialType && commercialType.value) {
            // Handle "Others" case with custom input
            if (commercialType.value === 'Others') {
                const commercialTypeOthers = document.querySelector('input[name="commercial_type_others"]');
                if (commercialTypeOthers && commercialTypeOthers.value.trim()) {
                    formData.set('commercial_type', commercialTypeOthers.value.trim());
                    console.log('✅ FORCE captured commercial_type (Others):', commercialTypeOthers.value.trim());
                } else {
                    formData.set('commercial_type', 'Others');
                    console.log('✅ FORCE captured commercial_type: Others');
                }
            } else {
                formData.set('commercial_type', commercialType.value);
                console.log('✅ FORCE captured commercial_type:', commercialType.value);
            }
        }
        
        // Note: Residential type uses "residenceType" field name in form
        const residentialType = document.querySelector('input[name="residenceType"]:checked');
        if (residentialType && residentialType.value) {
            // Handle "others" case with custom input
            if (residentialType.value === 'others') {
                const otherResidenceType = document.querySelector('input[name="otherResidenceType"]');
                if (otherResidenceType && otherResidenceType.value.trim()) {
                    formData.set('residential_type', otherResidenceType.value.trim());
                    console.log('✅ FORCE captured residential_type (others):', otherResidenceType.value.trim());
                } else {
                    formData.set('residential_type', 'Others');
                    console.log('✅ FORCE captured residential_type: Others');
                }
            } else {
                formData.set('residential_type', residentialType.value);
                console.log('✅ FORCE captured residential_type:', residentialType.value);
            }
        }
        
        const industrialType = document.querySelector('input[name="industrial_type"]:checked');
        if (industrialType && industrialType.value) {
            // Handle "Others" case with custom input
            if (industrialType.value === 'Others') {
                const industrialTypeOthers = document.querySelector('input[name="industrial_type_others"]');
                if (industrialTypeOthers && industrialTypeOthers.value.trim()) {
                    formData.set('industrial_type', industrialTypeOthers.value.trim());
                    console.log('✅ FORCE captured industrial_type (Others):', industrialTypeOthers.value.trim());
                } else {
                    formData.set('industrial_type', 'Others');
                    console.log('✅ FORCE captured industrial_type: Others');
                }
            } else {
                formData.set('industrial_type', industrialType.value);
                console.log('✅ FORCE captured industrial_type:', industrialType.value);
            }
        }

        // FORCE capture shared areas checkboxes
        const sharedAreasCheckboxes = document.querySelectorAll('input[name="shared_areas[]"]:checked');
        console.log(`🏠 Found ${sharedAreasCheckboxes.length} shared areas checkboxes checked`);
        
        if (sharedAreasCheckboxes.length > 0) {
            const sharedAreasValues = Array.from(sharedAreasCheckboxes).map(checkbox => checkbox.value);
            
            // Add each shared area separately to FormData (Laravel expects array format)
            sharedAreasValues.forEach((value, index) => {
                formData.append('shared_areas[]', value);
                console.log(`✅ FORCE captured shared_areas[${index}]:`, value);
            });
            
            console.log(`🏠 Total shared areas captured: ${sharedAreasValues.length}`);
        } else {
            console.warn('⚠️ No shared areas checkboxes checked');
        }

        // FORCE capture buyer records from the buyer table
        const buyerRows = document.querySelectorAll('.buyer-row');
        console.log(`👥 Found ${buyerRows.length} buyer rows`);
        
        if (buyerRows.length > 0) {
            buyerRows.forEach((row, index) => {
                const title = row.querySelector('select[name*="[buyerTitle]"]');
                const firstName = row.querySelector('input[name*="[firstName]"]');
                const middleName = row.querySelector('input[name*="[middleName]"]');
                const surname = row.querySelector('input[name*="[surname]"]');
                const unitNo = row.querySelector('input[name*="[unit_no]"]');
                const sectionNumber = row.querySelector('input[name*="[sectionNumber]"]');
                const unitMeasurement = row.querySelector('input[name*="[unitMeasurement]"]');
                const landUse = row.querySelector('select[name*="[landUse]"]');
                
                // Force add each buyer field
                if (title && title.value) {
                    formData.append(`records[${index}][buyerTitle]`, title.value);
                    console.log(`✅ Buyer ${index + 1} title:`, title.value);
                }
                if (firstName && firstName.value) {
                    formData.append(`records[${index}][firstName]`, firstName.value);
                    console.log(`✅ Buyer ${index + 1} firstName:`, firstName.value);
                }
                if (middleName && middleName.value) {
                    formData.append(`records[${index}][middleName]`, middleName.value);
                }
                if (surname && surname.value) {
                    formData.append(`records[${index}][surname]`, surname.value);
                    console.log(`✅ Buyer ${index + 1} surname:`, surname.value);
                }
                if (unitNo && unitNo.value) {
                    formData.append(`records[${index}][unit_no]`, unitNo.value);
                    console.log(`✅ Buyer ${index + 1} unit_no:`, unitNo.value);
                }
                if (sectionNumber && sectionNumber.value) {
                    formData.append(`records[${index}][sectionNumber]`, sectionNumber.value);
                    console.log(`✅ Buyer ${index + 1} sectionNumber:`, sectionNumber.value);
                }
                if (unitMeasurement && unitMeasurement.value) {
                    formData.append(`records[${index}][unitMeasurement]`, unitMeasurement.value);
                }
                if (landUse && landUse.value) {
                    formData.append(`records[${index}][landUse]`, landUse.value);
                    console.log(`✅ Buyer ${index + 1} landUse:`, landUse.value);
                }
            });
        }
        
        // Preserve CSV processed buyer JSON if utility scripts populate it
        const buyersJsonField = document.getElementById('buyers-json-data');
        if (buyersJsonField && buyersJsonField.value) {
            formData.set('buyers_json', buyersJsonField.value);
            console.log('🧾 Attached buyers_json payload');
        }

        // FORCE capture PASSPORT file upload (name="passport")
        let passportInput = document.querySelector('input[name="passport"]');
        if (!passportInput) {
            passportInput = document.getElementById('passportInput');
        }
        
        if (passportInput) {
            console.log('📄 Passport input found:', {
                name: passportInput.name,
                id: passportInput.id,
                type: passportInput.type,
                files_length: passportInput.files ? passportInput.files.length : 0
            });
            
            if (passportInput.files && passportInput.files.length > 0) {
                const passportFile = passportInput.files[0];
                formData.set('passport', passportFile, passportFile.name);
                console.log(`✅ FORCE captured passport: ${passportFile.name} (${passportFile.size} bytes, type: ${passportFile.type})`);
            } else {
                console.warn('⚠️ Passport input found but no file selected');
            }
        } else {
            console.error('❌ Passport input NOT FOUND in DOM');
        }
        
        // FORCE capture RC DOCUMENT file upload (name="rc_document")
        let rcDocumentInput = document.querySelector('input[name="rc_document"]');
        if (!rcDocumentInput) {
            rcDocumentInput = document.getElementById('corporateDocumentUpload');
        }
        
        if (rcDocumentInput) {
            console.log('📄 RC document input found:', {
                name: rcDocumentInput.name,
                id: rcDocumentInput.id,
                type: rcDocumentInput.type,
                files_length: rcDocumentInput.files ? rcDocumentInput.files.length : 0
            });
            
            if (rcDocumentInput.files && rcDocumentInput.files.length > 0) {
                const rcDocumentFile = rcDocumentInput.files[0];
                formData.set('rc_document', rcDocumentFile, rcDocumentFile.name);
                console.log(`✅ FORCE captured rc_document: ${rcDocumentFile.name} (${rcDocumentFile.size} bytes, type: ${rcDocumentFile.type})`);
            } else {
                console.warn('⚠️ RC document input found but no file selected');
            }
        } else {
            console.warn('⚠️ RC document input NOT FOUND in DOM');
        }

        // FORCE capture DOCUMENT files (application_letter, building_plan, etc.)
        const documentFields = [
            'application_letter',
            'building_plan', 
            'architectural_design',
            'ownership_document',
            'survey_plan'
        ];

        documentFields.forEach(fieldName => {
            let documentInput = document.querySelector(`input[name="${fieldName}"]`);
            if (!documentInput) {
                documentInput = document.getElementById(fieldName);
            }
            
            if (documentInput) {
                console.log(`📄 ${fieldName} input found:`, {
                    name: documentInput.name,
                    id: documentInput.id,
                    type: documentInput.type,
                    files_length: documentInput.files ? documentInput.files.length : 0
                });
                
                if (documentInput.files && documentInput.files.length > 0) {
                    const documentFile = documentInput.files[0];
                    formData.set(fieldName, documentFile, documentFile.name);
                    console.log(`✅ FORCE captured ${fieldName}: ${documentFile.name} (${documentFile.size} bytes, type: ${documentFile.type})`);
                } else {
                    console.warn(`⚠️ ${fieldName} input found but no file selected`);
                }
            } else {
                console.warn(`⚠️ ${fieldName} input NOT FOUND in DOM`);
            }
        });

        const selectedFileRaw = formData.get('selected_file_data') || document.getElementById('selected_file_data')?.value;
        let selectedFileMeta = null;

        if (selectedFileRaw && typeof selectedFileRaw === 'string') {
            try {
                selectedFileMeta = JSON.parse(selectedFileRaw);
            } catch (error) {
                console.warn('⚠️ Unable to parse selected_file_data JSON:', error);
            }
        } else if (selectedFileRaw && typeof selectedFileRaw === 'object') {
            selectedFileMeta = selectedFileRaw;
        }

        if (selectedFileMeta) {
            const assignIfMissing = (key, value, transform = (val) => val) => {
                if (!formData.get(key) && value) {
                    const resolvedValue = transform(value);
                    if (resolvedValue) {
                        formData.set(key, resolvedValue);
                        console.log(`🔁 Fallback applied for ${key}:`, resolvedValue);
                    }
                }
            };

            assignIfMissing('np_fileno', selectedFileMeta.np_fileno || selectedFileMeta.fileno || selectedFileMeta.full_file_number);
            assignIfMissing('fileno', selectedFileMeta.fileno || selectedFileMeta.full_file_number);
            assignIfMissing('land_use', selectedFileMeta.land_use || selectedFileMeta.land_use_code);
            assignIfMissing('tracking_id', selectedFileMeta.tra || selectedFileMeta.tracking_id);
            assignIfMissing('primary_file_id', selectedFileMeta.id);
            assignIfMissing('selected_file_id', selectedFileMeta.id);
            assignIfMissing('selected_file_type', selectedFileMeta.file_no_type);
            assignIfMissing('applied_file_number', selectedFileMeta.fileno || selectedFileMeta.full_file_number);
            assignIfMissing('applicant_type', selectedFileMeta.applicant_type, (val) => val?.toString().toLowerCase());
            assignIfMissing('applicant_title', selectedFileMeta.applicant_title);
            assignIfMissing('first_name', selectedFileMeta.first_name || selectedFileMeta.name);
            assignIfMissing('middle_name', selectedFileMeta.middle_name);
            assignIfMissing('surname', selectedFileMeta.surname);
            assignIfMissing('corporate_name', selectedFileMeta.corporate_name);
            assignIfMissing('rc_number', selectedFileMeta.rc_number);

            // Ensure selected_file_data stays as JSON string
            formData.set('selected_file_data', JSON.stringify(selectedFileMeta));
        }

        if (!formData.get('applicant_type')) {
            const camelApplicantType = formData.get('applicantType');
            if (camelApplicantType) {
                formData.set('applicant_type', camelApplicantType.toString().toLowerCase());
                console.log('🔁 Derived applicant_type from applicantType field');
            }
        }
        
        // Debug: Sync form fields to centralized hidden fields
        const syncFormField = (hiddenFieldName, sourceFieldName = null) => {
            const hiddenField = this.form.querySelector(`input[name="${hiddenFieldName}"]`);
            const sourceField = this.form.querySelector(`input[name="${sourceFieldName || hiddenFieldName}"], select[name="${sourceFieldName || hiddenFieldName}"]`);
            
            if (hiddenField && sourceField && sourceField.value) {
                hiddenField.value = sourceField.value;
                formData.set(hiddenFieldName, sourceField.value);
                console.log(`🔄 Synced ${hiddenFieldName}: ${sourceField.value}`);
            }
        };
        
        // Sync key form fields to hidden fields
        syncFormField('fname', 'fname');
        syncFormField('lname', 'lname');  
        syncFormField('title', 'title');
        syncFormField('email', 'email');
        syncFormField('phone', 'phone');
        
        // Debug: Update debug fields
        this.updateDebugFields();

        // Attach scan upload files captured via drag & drop interface
        if (window.scanUploadedFiles && window.scanUploadedFiles.length > 0) {
            console.log(`📂 Attaching ${window.scanUploadedFiles.length} scan upload file(s)`);
            window.scanUploadedFiles.forEach((file, index) => {
                try {
                    formData.append(`scan_upload_files[${index}]`, file, file.name);
                } catch (error) {
                    console.error('❌ Error appending scan upload file:', file?.name, error);
                }
            });
        } else {
            console.log('ℹ️ No scan upload files to attach');
        }

        // Add calculated fields from summary
        const summaryData = this.collectSummaryData();
        Object.keys(summaryData).forEach(key => {
            formData.append(key, summaryData[key]);
        });

        // Final verification: Ensure applicantType is in formData
        const applicantTypeInForm = formData.get('applicantType');
        console.log('� Final verification - applicantType in formData:', applicantTypeInForm);
        
        // Debug: Log all critical ST API fields
        console.log('🌍 Final land_use for submission:', formData.get('land_use'));
        console.log('🗂️ Final np_fileno for submission:', formData.get('np_fileno'));
        console.log('📁 Final fileno for submission:', formData.get('fileno'));
        console.log('👤 Final applicant_type for submission:', formData.get('applicant_type'));
        console.log('🔗 Final selected_file_data for submission:', formData.get('selected_file_data'));
        console.log('🆔 Final primary_file_id for submission:', formData.get('primary_file_id'));

        if (!applicantTypeInForm) {
            // Last resort: get from debug field or hidden field
            const debugField = this.form.querySelector('#debug-applicant-type');
            const hiddenField = this.form.querySelector('[name="applicantType"]');
            
            const fallbackValue = debugField?.value || hiddenField?.value || 'individual';
            console.log('⚠️ Adding missing applicantType with fallback value:', fallbackValue);
            formData.append('applicantType', fallbackValue);
        }

        console.log('�📦 Form data collected with applicantType confirmed');
        return formData;
    },

    // Collect summary calculation data
    collectSummaryData: function() {
        const data = {};
        
        // Get fee calculations
        const totalFees = document.getElementById('totalFees')?.textContent || '0';
        const processingFee = document.getElementById('processingFee')?.textContent || '0';
        const totalAmount = document.getElementById('totalAmount')?.textContent || '0';
        
        data.total_fees = totalFees.replace(/[^\d.]/g, '');
        data.processing_fee = processingFee.replace(/[^\d.]/g, '');
        data.total_amount = totalAmount.replace(/[^\d.]/g, '');
        
        // Get generated addresses
        data.contact_address = document.getElementById('contactAddressDisplay')?.value || '';
        data.property_address = document.getElementById('propertyAddressDisplay')?.value || '';
        
        return data;
    },

    // Perform the AJAX submission
    performAjaxSubmission: function(formData, isRetry) {
        console.log('📡 Sending AJAX request' + (isRetry ? ' (retry after CSRF refresh)' : '') + '...');

        fetch(this.submitUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            console.log('📡 Response received:', response.status);
            const status = response.status;
            const ok = response.ok;

            // CSRF token mismatch — attempt a silent token refresh then retry once
            if (status === 419) {
                if (isRetry) {
                    // Already retried — session is truly dead
                    throw { __sessionDead: true, status };
                }
                throw { __csrfExpired: true, status };
            }

            // Session dead — server rejected authentication
            if (status === 401 || status === 403) {
                throw { __sessionDead: true, status };
            }

            // PHP-level payload too large (before Laravel runs)
            if (status === 413) {
                throw { __serverError: true, status, message: 'The submission is too large. Please reduce file sizes and try again.' };
            }

            return response.text().then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('❌ Non-JSON response (status ' + status + '):', text.substring(0, 500));
                    throw { __serverError: true, status, message: 'The server returned an unexpected response (HTTP ' + status + '). Please contact support if this persists.' };
                }
                return { status, ok, data };
            });
        })
        .then(result => {
            this.handleSubmissionResponse(result);
        })
        .catch(error => {
            if (error && error.__csrfExpired) {
                // Part 2: silently refresh the CSRF token and retry once
                this._refreshCsrfAndRetry(formData);
                return;
            }

            this.hideLoadingState();
            this.isSubmitting = false;

            if (error && error.__sessionDead) {
                // Part 3: session recovery modal
                this._showSessionExpiredModal();
                return;
            }

            if (error && error.__serverError) {
                this.showError(error.message);
                return;
            }

            // True network failure (offline, DNS, CORS)
            this.showError('Network error occurred. Please check your connection and try again.');
        })
        .finally(() => {
            // Only reset state if not handing off to a retry (retry manages its own state)
            if (!this._pendingRetry) {
                this.hideLoadingState();
                this.isSubmitting = false;
            }
        });
    },

    // Part 2: fetch a fresh CSRF token then replay the submission
    _refreshCsrfAndRetry: function(formData) {
        console.log('🔄 419 received — refreshing CSRF token...');
        this._pendingRetry = true;

        const keepAliveUrl = window.SESSION_KEEPALIVE_URL || '/session/keep-alive';

        fetch(keepAliveUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(res => {
            if (!res.ok) {
                // Keep-alive itself failed — session is truly dead
                this._pendingRetry = false;
                this.hideLoadingState();
                this.isSubmitting = false;
                this._showSessionExpiredModal();
                return null;
            }
            return res.json();
        })
        .then(data => {
            if (!data) return;

            // Update CSRF token everywhere
            const newToken = data.csrf_token;
            if (newToken) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', newToken);
                document.querySelectorAll('input[name="_token"]').forEach(el => { el.value = newToken; });
                formData.set('_token', newToken);
                console.log('✅ CSRF token refreshed — retrying submission...');
            }

            this._pendingRetry = false;
            // Retry — pass isRetry=true so a second 419 goes straight to the dead-session modal
            this.performAjaxSubmission(formData, true);
        })
        .catch(() => {
            this._pendingRetry = false;
            this.hideLoadingState();
            this.isSubmitting = false;
            this._showSessionExpiredModal();
        });
    },

    // Part 3: show a recovery modal when the session is truly dead
    _showSessionExpiredModal: function() {
        const manager = typeof window.PrimaryFormDraftManager !== 'undefined'
            ? window.PrimaryFormDraftManager
            : null;

        const lastSaved = manager && manager.state && manager.state.lastSavedAt
            ? 'Your draft was last saved at ' + new Date(manager.state.lastSavedAt).toLocaleTimeString() + '.'
            : 'If autosave was active, your draft may have been preserved.';

        const loginUrl = '/login';

        if (typeof Swal === 'undefined') {
            const msg = 'Your session has expired.\n\n' + lastSaved +
                '\n\nPlease open the login page in a new tab, log back in, then click OK here to retry.';
            if (confirm(msg)) {
                window.open(loginUrl, '_blank');
            }
            return;
        }

        Swal.fire({
            title: 'Session Expired',
            html:
                '<p class="text-gray-700 mb-3">Your login session has timed out. ' +
                'Your form data is still here.</p>' +
                '<p class="text-sm text-gray-500">' + lastSaved + '</p>',
            icon: 'warning',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: true,
            confirmButtonText: 'Open Login in New Tab',
            cancelButtonText: 'Dismiss',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
        }).then(result => {
            if (result.isConfirmed) {
                const loginTab = window.open(loginUrl, '_blank');

                // After they close the login tab or come back, offer a retry
                Swal.fire({
                    title: 'Ready to retry?',
                    html: '<p class="text-gray-700">Log back in the new tab, then click <strong>Retry</strong> to submit your form.</p>',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonText: 'Retry Submission',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#6b7280',
                }).then(retryResult => {
                    if (retryResult.isConfirmed) {
                        // Re-collect form data (CSRF token may have been refreshed in the new tab's session)
                        this.isSubmitting = false;
                        this.showLoadingState();
                        this.isSubmitting = true;
                        const freshFormData = this.collectFormData();
                        this._refreshCsrfAndRetry(freshFormData);
                    }
                });
            }
        });
    },

    // Handle successful/failed submission response
    handleSubmissionResponse: function(result) {
        console.log('📋 Processing response:', result);

        if (result.ok && result.status === 200) {
            // Success
            console.log('✅ Form submitted successfully');
            this.showSuccessMessage(result.data);
            const manager = this.getDraftManager();
            if (manager && typeof manager.finalizeAfterSubmit === 'function') {
                manager.finalizeAfterSubmit();
            }
        } else {
            // Server error or validation error
            console.error('❌ Server error:', result);
            this.handleServerError(result);
        }
    },

    // Handle server/validation errors
    handleServerError: function(result) {
        let errorMessage = 'An error occurred while submitting the form.';
        
        if (result.data && result.data.message) {
            errorMessage = result.data.message;
        } else if (result.data && result.data.errors) {
            // Laravel validation errors
            const errors = Object.values(result.data.errors).flat();
            errorMessage = errors.join('\n');
        }

        this.showError(errorMessage);
    },

    // Handle network/fetch errors
    handleSubmissionError: function(error) {
        console.error('❌ Network error:', error);
        this.showError('Network error occurred. Please check your connection and try again.');
    },

    // Show success message and handle redirect
    showSuccessMessage: function(data) {
        // Enhanced success message for scan upload integration
        let successText = data.message || 'Primary application submitted successfully.';
        
        // Add scan upload info if documents were processed
        if (data.file_indexing_id) {
            successText += '\n\nDocuments have been processed for EDMS workflow. You will now be redirected to the page typing interface.';
        }

        if (data.draft_deleted) {
            successText += '\n\nAutosaved draft has been cleared.';
        }
        
        Swal.fire({
            title: 'Success!',
            text: successText,
            icon: 'success',
            confirmButtonText: 'Proceed to Page Typing',
            confirmButtonColor: '#10B981',
            showLoaderOnConfirm: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading message
                if (window.ScanUploadHelper) {
                    window.ScanUploadHelper.showLoading('Redirecting to Page Typing...');
                }
                
                // Handle redirect or form reset
                if (data.redirect_url) {
                    console.log('🔗 Redirecting to:', data.redirect_url);
                    
                    // If redirecting to blind scanning for ST EDMS, store applicant info
                    if (data.redirect_url.includes('/blind-scanning?url=st_edms') && data.data && data.data.applicant_info) {
                        localStorage.setItem('st_applicant_info', JSON.stringify({
                            ...data.data.applicant_info,
                            np_fileno: data.data.np_fileno,
                            fileno: data.data.fileno,
                            application_id: data.data.application_id
                        }));
                        console.log('💾 ST applicant info stored for blind scanning');
                    }
                    
                    window.location.href = data.redirect_url;
                } else {
                    // Default redirect to applications list
                    console.log('🔗 Default redirect to sectional titling');
                    window.location.href = '/sectional-titling';
                }
            }
        });
    },

    // Show error message
    showError: function(message) {
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#EF4444'
        });
    },

    // Show loading state
    showLoadingState: function() {
        // Show loading overlay
        const loadingOverlay = document.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
        }

        // Disable submit buttons
        const submitButtons = document.querySelectorAll('button[type="submit"], .submit-form-btn, [onclick*="submitForm"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.6';
            const originalText = btn.textContent;
            btn.setAttribute('data-original-text', originalText);
            btn.innerHTML = '<i class="animate-spin w-4 h-4 mr-2" style="border: 2px solid #fff; border-top: 2px solid transparent; border-radius: 50%;"></i>Submitting...';
        });

        console.log('⏳ Loading state activated');
    },

    // Hide loading state
    hideLoadingState: function() {
        // Hide loading overlay
        const loadingOverlay = document.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }

        // Re-enable submit buttons
        const submitButtons = document.querySelectorAll('button[type="submit"], .submit-form-btn, [onclick*="submitForm"]');
        submitButtons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            const originalText = btn.getAttribute('data-original-text');
            if (originalText) {
                btn.textContent = originalText;
            }
        });

        console.log('✅ Loading state deactivated');
    },

    // Update debug fields to show current values
    updateDebugFields: function() {
        const applicantTypeField = this.form.querySelector('input[name="applicant_type"]');
        const corporateNameField = this.form.querySelector('input[name="corporate_name"]');
        const rcNumberField = this.form.querySelector('input[name="rc_number"]');
        
        const debugApplicantType = document.getElementById('debug-applicant-type');
        const debugHiddenValue = document.getElementById('debug-hidden-value');
        const debugCorporateName = document.getElementById('debug-corporate-name');
        const debugRcNumber = document.getElementById('debug-rc-number');
        
        if (debugApplicantType && applicantTypeField) {
            debugApplicantType.value = applicantTypeField.value || 'Not Set';
        }
        if (debugHiddenValue && applicantTypeField) {
            debugHiddenValue.textContent = applicantTypeField.value || 'Not Set';
        }
        if (debugCorporateName && corporateNameField) {
            debugCorporateName.textContent = corporateNameField.value || 'Not Set';
        }
        if (debugRcNumber && rcNumberField) {
            debugRcNumber.textContent = rcNumberField.value || 'Not Set';
        }
        
        console.log('🔍 Debug fields updated:', {
            applicant_type: applicantTypeField?.value,
            corporate_name: corporateNameField?.value,
            rc_number: rcNumberField?.value
        });
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for other scripts to load
    setTimeout(() => {
        FormSubmission.init();
    }, 500);
});

// Export for global access
window.FormSubmission = FormSubmission;
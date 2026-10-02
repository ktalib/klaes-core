<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Global function to navigate to specific step
    function goToStep(stepNumber) {
        console.log('Navigating to step:', stepNumber);
        
        // Get current active step
        const currentActiveStep = document.querySelector('.form-section.active');
        let currentStepNumber = 1;
        if (currentActiveStep) {
            const stepId = currentActiveStep.id;
            currentStepNumber = parseInt(stepId.replace('step', ''));
        }
        
        // If trying to go to the same step, do nothing
        if (currentStepNumber === stepNumber) {
            return;
        }
        
        // If trying to go forward, validate current step first
        if (stepNumber > currentStepNumber) {
            let canProceed = true;
            
            // Validate based on current step
            switch (currentStepNumber) {
                case 1:
                    canProceed = validateStep1();
                    if (canProceed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Step 1 Complete!',
                            text: 'Basic information has been validated successfully.',
                            timer: 1500,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                    break;
                case 2:
                    // Step 2 (shared areas) doesn't require validation
                    canProceed = true;
                    break;
                case 3:
                    canProceed = validateStep3();
                    if (canProceed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Documents Validated!',
                            text: 'All required documents have been uploaded successfully.',
                            timer: 1500,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                    break;
                case 4:
                    canProceed = validateStep4();
                    if (canProceed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Buyers List Complete!',
                            text: 'Buyer information has been validated successfully.',
                            timer: 1500,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                    break;
            }
            
            // If validation failed, don't proceed
            if (!canProceed) {
                return;
            }
        }
        
        // Hide all steps
        const allSteps = document.querySelectorAll('.form-section');
        allSteps.forEach(step => step.classList.remove('active'));
        
        // Show target step
        const targetStep = document.getElementById(`step${stepNumber}`);
        if (targetStep) {
            targetStep.classList.add('active');
        }
        
        // Update step circles
        updateStepCircles(stepNumber);
        
        // Update step text
        updateStepText(stepNumber);
        
        // If navigating to summary step, update the summary
        if (stepNumber === 5) {
            updateApplicationSummary();
        }
    }

    // Function to update step circles visual state
    function updateStepCircles(currentStep) {
        const stepCircles = document.querySelectorAll('.step-circle');
        stepCircles.forEach((circle, index) => {
            const stepNum = index + 1;
            circle.classList.remove('active', 'inactive');
            
            if (stepNum === currentStep) {
                circle.classList.add('active');
            } else {
                circle.classList.add('inactive');
            }
        });
    }

    // Function to update step text
    function updateStepText(currentStep) {
        const stepTexts = document.querySelectorAll('[class*="Step"][class*="of"]');
        stepTexts.forEach(text => {
            text.textContent = `Step ${currentStep} of 5`;
        });
    }

    // Make goToStep globally accessible
    window.goToStep = goToStep;

    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded - initializing form handling');

        // Step navigation - Updated to remove EDMS step
        const nextStep1 = document.getElementById('nextStep1');
        const nextStep2 = document.getElementById('nextStep2');
        const nextStep3 = document.getElementById('nextStep3');
        const nextStep4 = document.getElementById('nextStep4');
        const backStep2 = document.getElementById('backStep2');
        const backStep3 = document.getElementById('backStep3');
        const backStep4 = document.getElementById('backStep4');
        const backStep5 = document.getElementById('backStep5');

        // Form sections - Updated to remove EDMS step
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        const step4 = document.getElementById('step4');
        const step5 = document.getElementById('step5');

        if (nextStep1) {
            nextStep1.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Validate required fields for step 1
                if (validateStep1()) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Step 1 Complete!',
                        text: 'Basic information has been validated successfully.',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    goToStep(2);
                }
            });
        }

        if (nextStep2) {
            nextStep2.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(3);
            });
        }

        if (nextStep3) {
            nextStep3.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Validate documents for step 3
                if (validateStep3()) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Documents Validated!',
                        text: 'All required documents have been uploaded successfully.',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    goToStep(4);
                }
            });
        }

        // Updated nextStep4 to go directly to summary (step 5)
        if (nextStep4) {
            nextStep4.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Validate buyers list for step 4 (previously step 5)
                if (validateStep4()) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Buyers List Complete!',
                        text: 'Buyer information has been validated successfully.',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    goToStep(5);
                }
            });
        }

        if (backStep2) {
            backStep2.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(1);
            });
        }

        if (backStep3) {
            backStep3.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(2);
            });
        }

        if (backStep4) {
            backStep4.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(3);
            });
        }

        if (backStep5) {
            backStep5.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(4);
            });
        }

        // Close modal buttons
        const closeModal = document.getElementById('closeModal');
        const closeModal2 = document.getElementById('closeModal2');
        const closeModal3 = document.getElementById('closeModal3');
        const closeModal4 = document.getElementById('closeModal4');

        if (closeModal) {
            closeModal.addEventListener('click', function() {
                alert('Application process canceled');
            });
        }

        if (closeModal2) {
            closeModal2.addEventListener('click', function() {
                alert('Application process canceled');
            });
        }

        if (closeModal3) {
            closeModal3.addEventListener('click', function() {
                alert('Application process canceled');
            });
        }

        if (closeModal4) {
            closeModal4.addEventListener('click', function() {
                alert('Application process canceled');
            });
        }

        // Improved contact address update functionality
        function initializeAddressUpdate() {
            console.log('Initializing address update functionality');
            
            // Get all address input fields
            const ownerHouseNo = document.getElementById('ownerHouseNo');
            const ownerStreetName = document.getElementById('ownerStreetName');
            const ownerDistrict = document.getElementById('ownerDistrict');
            const ownerLga = document.getElementById('ownerLga');
            const ownerState = document.getElementById('ownerState');
            
            // Get display elements
            const fullContactAddress = document.getElementById('fullContactAddress');
            const contactAddressDisplay = document.getElementById('contactAddressDisplay');
            
            if (!fullContactAddress || !contactAddressDisplay) {
                console.error('Address display elements not found!', {
                    fullContactAddress: !!fullContactAddress,
                    contactAddressDisplay: !!contactAddressDisplay
                });
                return;
            }
            
            // Function to update the address display
            function updateAddress() {
                const houseNo = ownerHouseNo ? ownerHouseNo.value.trim() : '';
                const streetName = ownerStreetName ? ownerStreetName.value.trim() : '';
                const district = ownerDistrict ? ownerDistrict.value.trim() : '';
                const lga = ownerLga ? ownerLga.value.trim() : '';
                const state = ownerState ? ownerState.value.trim() : '';
                
                // Build address parts array, filter out empty values
                const addressParts = [];
                if (houseNo) addressParts.push(houseNo);
                if (streetName) addressParts.push(streetName);
                if (district) addressParts.push(district);
                if (lga) addressParts.push(lga);
                if (state) addressParts.push(state);
                
                // Join with commas
                const fullAddress = addressParts.join(', ');
                
                // Update display elements
                fullContactAddress.textContent = fullAddress;
                contactAddressDisplay.value = fullAddress;
                
                console.log('Address updated:', fullAddress);
            }
            
            // Add input event listeners to all address fields
            const attachListener = (element) => {
                if (element) {
                    console.log('Attaching listener to:', element.id);
                    element.addEventListener('input', updateAddress);
                }
            };
            
            attachListener(ownerHouseNo);
            attachListener(ownerStreetName);
            attachListener(ownerDistrict);
            attachListener(ownerLga);
            attachListener(ownerState);
            
            // Initial update
            updateAddress();
            console.log('Address update initialization complete');
        }
        
        // Call the initialization function
        initializeAddressUpdate();

        // Form submission handling
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Log the form data before submission for debugging
                console.log('Form submission - preparing to submit form');
                
                // Check for file inputs and log them
                const fileInputs = form.querySelectorAll('input[type="file"]');
                fileInputs.forEach(input => {
                    if (input.files && input.files.length > 0) {
                        console.log(`File input ${input.name} has ${input.files.length} file(s)`, {
                            name: input.files[0].name,
                            type: input.files[0].type,
                            size: input.files[0].size
                        });
                    }
                });
            });
        }

        // Enhance document file upload handling
        function enhanceFileUploads() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const fileInfo = {
                            name: this.files[0].name,
                            type: this.files[0].type,
                            size: this.files[0].size
                        };
                        console.log(`File selected for ${this.name}:`, fileInfo);
                        
                        // Also update any related UI elements
                        const nameElement = document.getElementById(`${this.id}_name`);
                        if (nameElement) {
                            nameElement.textContent = this.files[0].name;
                        }
                    }
                });
            });
        }
        
        // Initialize enhanced file uploads
        enhanceFileUploads();
    });

    // Function to update file name display
    function updateFileName(input, labelId) {
        const fileName = input.files[0]?.name;
        if (fileName) {
            document.getElementById(input.id + '_name').textContent = fileName;
            document.getElementById(labelId).innerHTML = '<span>Change Document</span>';
            
            // Log for debugging
            console.log(`File selected for ${input.name}:`, {
                name: fileName,
                type: input.files[0].type,
                size: input.files[0].size
            });
        }
    }

    // Comprehensive function to update application summary
    function updateApplicationSummary() {
        console.log('Updating application summary...');
        
        // Applicant Information
        const applicantTypeEl = document.querySelector('input[name="applicantType"]:checked');
        let applicantType = '-';
        if (applicantTypeEl) {
            applicantType = applicantTypeEl.value;
        } else {
            // Check hidden field as fallback
            const hiddenApplicantType = document.getElementById('applicantType');
            if (hiddenApplicantType && hiddenApplicantType.value) {
                applicantType = hiddenApplicantType.value;
            }
        }

        // Get name based on applicant type
        let fullName = '-';
        if (applicantType === 'individual') {
            const titleEl = document.querySelector('select[name="applicant_title"]');
            const firstNameEl = document.querySelector('input[name="first_name"]');
            const middleNameEl = document.querySelector('input[name="middle_name"]');
            const surnameEl = document.querySelector('input[name="surname"]');
            
            const title = titleEl ? titleEl.value : '';
            const firstName = firstNameEl ? firstNameEl.value : '';
            const middleName = middleNameEl ? middleNameEl.value : '';
            const surname = surnameEl ? surnameEl.value : '';
            
            const nameParts = [title, firstName, middleName, surname].filter(part => part.trim() !== '');
            fullName = nameParts.join(' ') || '-';
        } else if (applicantType === 'corporate') {
            const corporateNameEl = document.querySelector('input[name="corporate_name"]');
            fullName = corporateNameEl ? corporateNameEl.value : '-';
        } else if (applicantType === 'multiple') {
            const multipleOwnersNames = document.querySelectorAll('input[name="multiple_owners_names[]"]');
            const names = Array.from(multipleOwnersNames).map(input => input.value).filter(name => name.trim() !== '');
            fullName = names.length > 0 ? names.join(', ') : '-';
        }

        // Contact Information
        const emailEl = document.querySelector('input[name="owner_email"]');
        const email = emailEl ? emailEl.value : '-';

        const phoneEls = document.querySelectorAll('input[name="phone_number[]"]');
        const phones = Array.from(phoneEls).map(el => el.value).filter(phone => phone.trim() !== '');
        const phoneDisplay = phones.length > 0 ? phones.join(', ') : '-';

        // Address Information - try both name and ID selectors
        const houseNoEl = document.querySelector('input[name="address_house_no"]') || document.getElementById('ownerHouseNo');
        const streetNameEl = document.querySelector('input[name="owner_street_name"]') || document.getElementById('ownerStreetName');
        const districtEl = document.querySelector('input[name="owner_district"]') || document.getElementById('ownerDistrict');
        const lgaEl = document.querySelector('input[name="owner_lga"]') || document.getElementById('ownerLga');
        const stateEl = document.querySelector('input[name="owner_state"]') || document.getElementById('ownerState');

        const houseNo = houseNoEl ? houseNoEl.value : '-';
        const streetName = streetNameEl ? streetNameEl.value : '-';
        const district = districtEl ? districtEl.value : '-';
        const lga = lgaEl ? lgaEl.value : '-';
        const state = stateEl ? stateEl.value : '-';
        
        // Debug logging for LGA
        console.log('LGA Debug:', {
            lgaEl: lgaEl,
            lgaValue: lga,
            lgaByName: document.querySelector('input[name="owner_lga"]'),
            lgaById: document.getElementById('ownerLga')
        });
        
        const addressParts = [houseNo, streetName, district, lga, state].filter(part => part !== '-' && part.trim() !== '');
        const fullAddress = addressParts.length > 0 ? addressParts.join(', ') : '-';

        // Property Details
        const residenceTypeEl = document.querySelector('input[name="residenceType"]:checked');
        const residenceType = residenceTypeEl ? residenceTypeEl.value : '-';
        
        const unitsCountEl = document.querySelector('input[name="units_count"]');
        const unitsCount = unitsCountEl ? unitsCountEl.value : '-';
        
        const blocksCountEl = document.querySelector('input[name="blocks_count"]');
        const blocksCount = blocksCountEl ? blocksCountEl.value : '-';
        
        const sectionsCountEl = document.querySelector('input[name="sections_count"]');
        const sectionsCount = sectionsCountEl ? sectionsCountEl.value : '-';

        // File Number - Get from active tab
        let fileNumber = '-';
        const activeFileTab = document.getElementById('activeFileTab');
        if (activeFileTab && activeFileTab.value) {
            const activeTabValue = activeFileTab.value;
            if (activeTabValue === 'mlsFNo') {
                const mlsFileNoEl = document.getElementById('mlsPreviewFileNumber');
                fileNumber = mlsFileNoEl ? mlsFileNoEl.value : '-';
            } else if (activeTabValue === 'kangisFileNo') {
                const kangisFileNoEl = document.getElementById('kangisPreviewFileNumber');
                fileNumber = kangisFileNoEl ? kangisFileNoEl.value : '-';
            } else if (activeTabValue === 'NewKANGISFileno') {
                const newKangisFileNoEl = document.getElementById('newKangisPreviewFileNumber');
                fileNumber = newKangisFileNoEl ? newKangisFileNoEl.value : '-';
            }
        }

        // Payment Information
        const appFeeEl = document.querySelector('input[name="application_fee"]');
        const procFeeEl = document.querySelector('input[name="processing_fee"]');
        const sitePlanFeeEl = document.querySelector('input[name="site_plan_fee"]');
        const receiptNumberEl = document.querySelector('input[name="receipt_number"]');
        const paymentDateEl = document.querySelector('input[name="payment_date"]');
        const dateCapturedEl = document.querySelector('input[name="date_captured"]');
        // Helper to clean formatted currency (e.g. 10,000.00 or ₦10,000.00)
        const parseMoney = (el) => {
            if (!el || !el.value) return 0;
            const raw = el.value.toString().trim();
            const cleaned = raw.replace(/[^0-9.]/g, ''); // remove commas, currency symbols
            const num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        };

        const appFee = parseMoney(appFeeEl);
        const procFee = parseMoney(procFeeEl);
        const sitePlanFee = parseMoney(sitePlanFeeEl);
        const totalFee = appFee + procFee + sitePlanFee;

        // Debug (can be removed later)
        // console.log('Fee Parse Debug:', { rawApp: appFeeEl?.value, rawProc: procFeeEl?.value, rawSite: sitePlanFeeEl?.value, appFee, procFee, sitePlanFee, totalFee });
        const receiptNumber = receiptNumberEl ? receiptNumberEl.value : '-';
        const paymentDate = paymentDateEl ? paymentDateEl.value : '-';
        const dateCaptured = dateCapturedEl ? dateCapturedEl.value : '-';

        const formatCurrency = (amount) => {
            return '₦' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        };

        // Property Address - try both name and ID selectors
        const propertyHouseNoEl = document.querySelector('input[name="property_house_no"]');
        const propertyPlotNoEl = document.querySelector('input[name="property_plot_no"]');
        const propertyStreetNameEl = document.querySelector('input[name="property_street_name"]');
        const propertyDistrictEl = document.querySelector('input[name="property_district"]');
        const propertyLgaEl = document.querySelector('select[name="property_lga"]') || document.getElementById('propertyLga');
        const propertyStateEl = document.querySelector('select[name="property_state"]') || document.getElementById('propertyState');

        const propertyHouseNo = propertyHouseNoEl ? propertyHouseNoEl.value : '-';
        const propertyPlotNo = propertyPlotNoEl ? propertyPlotNoEl.value : '-';
        const propertyStreetName = propertyStreetNameEl ? propertyStreetNameEl.value : '-';
        const propertyDistrict = propertyDistrictEl ? propertyDistrictEl.value : '-';
        const propertyLga = propertyLgaEl ? propertyLgaEl.value : '-';
        const propertyState = propertyStateEl ? propertyStateEl.value : '-';
        
        // Debug logging for Property LGA
        console.log('Property LGA Debug:', {
            propertyLgaEl: propertyLgaEl,
            propertyLgaValue: propertyLga,
            propertyLgaByName: document.querySelector('select[name="property_lga"]'),
            propertyLgaById: document.getElementById('lgaName')
        });
        
        const propertyAddressParts = [propertyHouseNo, propertyPlotNo, propertyStreetName, propertyDistrict, propertyLga, propertyState].filter(part => part !== '-' && part.trim() !== '');
        const propertyFullAddress = propertyAddressParts.length > 0 ? propertyAddressParts.join(', ') : '-';

        // Update summary fields if they exist
        const updateElement = (id, value) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        };

        updateElement('summary-applicant-type', applicantType);
        updateElement('summary-name', fullName);
        updateElement('summary-email', email);
        updateElement('summary-phone', phoneDisplay);
        
        updateElement('summary-residence-type', residenceType);
        updateElement('summary-units', unitsCount);
        updateElement('summary-blocks', blocksCount);
        updateElement('summary-sections', sectionsCount);
        updateElement('summary-file-number', fileNumber);
        
        updateElement('summary-house-no', houseNo);
        updateElement('summary-street-name', streetName);
        updateElement('summary-district', district);
        updateElement('summary-lga', lga);
        updateElement('summary-state', state);
        updateElement('summary-full-address', fullAddress);
        
        updateElement('summary-application-fee', formatCurrency(appFee));
        updateElement('summary-processing-fee', formatCurrency(procFee));
        updateElement('summary-site-plan-fee', formatCurrency(sitePlanFee));
        updateElement('summary-total-fee', formatCurrency(totalFee));
        updateElement('summary-receipt-number', receiptNumber);
        updateElement('summary-payment-date', paymentDate);
        updateElement('summary-date-captured', dateCaptured);
        
        updateElement('summary-property-house-no', propertyHouseNo);
        updateElement('summary-property-plot-no', propertyPlotNo);
        updateElement('summary-property-street-name', propertyStreetName);
        updateElement('summary-property-district', propertyDistrict);
        updateElement('summary-property-lga', propertyLga);
        updateElement('summary-property-state', propertyState);
        updateElement('summary-property-full-address', propertyFullAddress);
        
        // Update identification information based on applicant type
        let idType = '-';
        let idDocumentName = 'Not uploaded';
        
        if (applicantType === 'corporate') {
            // For corporate body, show RC document
            idType = 'RC Document';
            const corporateDocumentEl = document.getElementById('corporateDocumentUpload');
            if (corporateDocumentEl && corporateDocumentEl.files && corporateDocumentEl.files.length > 0) {
                idDocumentName = corporateDocumentEl.files[0].name;
            }
        } else {
            // For individual, show regular ID document
            const idTypeEl = document.querySelector('input[name="idType"]:checked');
            const idDocumentEl = document.getElementById('idDocumentUpload');
            
            idType = idTypeEl ? idTypeEl.value.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : '-';
            if (idDocumentEl && idDocumentEl.files && idDocumentEl.files.length > 0) {
                idDocumentName = idDocumentEl.files[0].name;
            }
        }
        
        updateElement('summary-id-type', idType);
        updateElement('summary-id-document', idDocumentName);
        
        // Update uploaded documents
        const documentsContainer = document.getElementById('summary-documents');
        if (documentsContainer) {
            documentsContainer.innerHTML = '';
            const documents = [
                { name: 'Application Letter', id: 'application_letter' },
                { name: 'Building Plan', id: 'building_plan' },
                { name: 'Architectural Design', id: 'architectural_design' },
                { name: 'Ownership Document', id: 'ownership_document' }
            ];
            
            documents.forEach(doc => {
                const input = document.getElementById(doc.id);
                const isUploaded = input && input.files && input.files.length > 0;
                const fileName = isUploaded ? input.files[0].name : '';
                
                const docElement = document.createElement('div');
                docElement.className = 'flex items-center justify-between p-2 bg-gray-50 rounded';
                
                const leftDiv = document.createElement('div');
                leftDiv.className = 'flex items-center';
                
                const statusDot = document.createElement('span');
                statusDot.className = `inline-block w-2 h-2 ${isUploaded ? 'bg-green-500' : 'bg-red-500'} rounded-full mr-2`;
                
                const docName = document.createElement('span');
                docName.className = 'text-sm font-medium';
                docName.textContent = doc.name;
                
                leftDiv.appendChild(statusDot);
                leftDiv.appendChild(docName);
                
                if (isUploaded && fileName) {
                    const fileNameSpan = document.createElement('span');
                    fileNameSpan.className = 'text-xs text-gray-500 truncate max-w-32';
                    fileNameSpan.textContent = fileName;
                    docElement.appendChild(leftDiv);
                    docElement.appendChild(fileNameSpan);
                } else {
                    docElement.appendChild(leftDiv);
                }
                
                documentsContainer.appendChild(docElement);
            });
        }
        
        console.log('Application summary updated successfully');
    }

    // Make updateApplicationSummary globally accessible
    window.updateApplicationSummary = updateApplicationSummary;

    // Validation functions - Updated to remove EDMS validation
    function validateStep1() {
        const errors = [];
        
        // Check applicant type
        const applicantType = document.querySelector('input[name="applicantType"]:checked');
        if (!applicantType) {
            errors.push('Please select an applicant type');
        }

        // Check applicant details based on type
        if (applicantType) {
            if (applicantType.value === 'individual') {
                const title = document.getElementById('applicantTitle');
                const firstName = document.getElementById('applicantName');
                const surname = document.getElementById('applicantSurname');
                
                if (!title || !title.value) errors.push('Please select a title');
                if (!firstName || !firstName.value.trim()) errors.push('Please enter first name');
                if (!surname || !surname.value.trim()) errors.push('Please enter surname');
            } else if (applicantType.value === 'corporate') {
                const corporateName = document.getElementById('corporateName');
                const rcNumber = document.getElementById('rcNumber');
                
                if (!corporateName || !corporateName.value.trim()) errors.push('Please enter corporate body name');
                if (!rcNumber || !rcNumber.value.trim()) errors.push('Please enter RC number');
            } else if (applicantType.value === 'multiple') {
                // Only validate multiple owners fields
                const ownerNames = document.querySelectorAll('input[name="multiple_owners_names[]"]');
                let hasValidOwner = false;
                ownerNames.forEach(input => {
                    if (input.value.trim()) hasValidOwner = true;
                });
                if (!hasValidOwner) errors.push('Please add at least one owner name');
                // Do NOT validate main owner phone/email/LGA/ID for multiple
            }
        }

        // Only validate main owner contact/ID if not multiple
        if (applicantType && applicantType.value !== 'multiple') {
            const phone1 = document.querySelector('input[name="phone_number[]"]');
            const email = document.querySelector('input[name="owner_email"]');
            const ownerState = document.querySelector('select[name="owner_state"]') || document.getElementById('ownerState');
            const ownerLga = document.querySelector('select[name="owner_lga"]') || document.getElementById('ownerLga');
            if (!phone1 || !phone1.value.trim()) errors.push('Please enter phone number');
            if (!ownerState || !ownerState.value) errors.push('Please select owner state');
            if (!ownerLga || !ownerLga.value) errors.push('Please select owner LGA');
            
            // Validate ID document based on applicant type
            if (applicantType.value === 'corporate') {
                // For corporate body, check the corporate document upload
                const corporateDocument = document.getElementById('corporateDocumentUpload');
                if (!corporateDocument || !corporateDocument.files || corporateDocument.files.length === 0) {
                    errors.push('Please upload ID document');
                }
            } else {
                // For individual, check the regular ID document upload
                const idType = document.querySelector('input[name="idType"]:checked');
                const idDocument = document.getElementById('idDocumentUpload');
                if (!idType) errors.push('Please select means of identification');
                if (!idDocument || !idDocument.files || idDocument.files.length === 0) {
                    errors.push('Please upload ID document');
                }
            }
        }

        // Check property details
        const unitsCount = document.querySelector('input[name="units_count"]');
        const blocksCount = document.querySelector('input[name="blocks_count"]');
        const sectionsCount = document.querySelector('input[name="sections_count"]');
        const propertyHouseNo = document.querySelector('input[name="property_house_no"]');
        const propertyStreetName = document.querySelector('input[name="property_street_name"]');
        const propertyLga = document.querySelector('select[name="property_lga"]') || document.getElementById('propertyLga');
        const propertyState = document.querySelector('select[name="property_state"]') || document.getElementById('propertyState');
        
        if (!unitsCount || !unitsCount.value.trim()) errors.push('Please enter number of units');
        if (!blocksCount || !blocksCount.value.trim()) errors.push('Please enter number of blocks');
        if (!sectionsCount || !sectionsCount.value.trim()) errors.push('Please enter number of sections');
        if (!propertyStreetName || !propertyStreetName.value.trim()) errors.push('Please enter property street name');
        if (!propertyState || !propertyState.value) errors.push('Please select property state');
        if (!propertyLga || !propertyLga.value) errors.push('Please select property LGA');
        
        // Check file number
        const activeFileTab = document.getElementById('activeFileTab');
        if (activeFileTab && activeFileTab.value) {
            if (activeFileTab.value === 'mlsFNo') {
                const prefix = document.getElementById('mlsFileNoPrefix');
                const number = document.getElementById('mlsFileNumber');
                if (!number || !number.value.trim()) errors.push('Please enter MLS file number');
            } else if (activeFileTab.value === 'kangisFileNo') {
                const prefix = document.getElementById('kangisFileNoPrefix');
                const number = document.getElementById('kangisFileNumber');
                if (!prefix || !prefix.value) errors.push('Please select KANGIS file prefix');
                if (!number || !number.value.trim()) errors.push('Please enter KANGIS file number');
            } else if (activeFileTab.value === 'NewKANGISFileno') {
                const prefix = document.getElementById('newKangisFileNoPrefix');
                const number = document.getElementById('newKangisFileNumber');
                if (!prefix || !prefix.value) errors.push('Please select New KANGIS file prefix');
                if (!number || !number.value.trim()) errors.push('Please enter New KANGIS file number');
            }
        }
        
        if (errors.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: '<div style="text-align: left;"><strong>Please fix the following errors:</strong><br><br>' + 
                      errors.map(error => '• ' + error).join('<br>') + '</div>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        return true;
    }

    function validateStep3() {
        // Documents are now optional - no validation required
        // Users can proceed without uploading any documents
        return true;
    }

    // Updated validateStep4 to validate buyers list (simplified validation)
    function validateStep4() {
        const errors = [];
        
        // Check if there's at least one buyer
        const buyerTitles = document.querySelectorAll('select[name*="[buyerTitle]"]');
        const buyerFirstNames = document.querySelectorAll('input[name*="[firstName]"]');
        const buyerSurnames = document.querySelectorAll('input[name*="[surname]"]');
        const unitNumbers = document.querySelectorAll('input[name*="[unit_no]"]');
        
        if (buyerTitles.length === 0) {
            errors.push('Please add at least one buyer');
        } else {
            // Validate each buyer
            let hasValidBuyer = false;
            
            for (let i = 0; i < buyerTitles.length; i++) {
                const title = buyerTitles[i]?.value?.trim();
                const firstName = buyerFirstNames[i]?.value?.trim();
                const surname = buyerSurnames[i]?.value?.trim();
                const unitNo = unitNumbers[i]?.value?.trim();
                
                // Check if this buyer has any data
                if (title || firstName || surname || unitNo) {
                    hasValidBuyer = true;
                    
                    // Validate required fields for this buyer
                    if (!title) {
                        errors.push(`Buyer ${i + 1}: Please select a title`);
                    }
                    if (!firstName) {
                        errors.push(`Buyer ${i + 1}: Please enter first name`);
                    }
                    if (!surname) {
                        errors.push(`Buyer ${i + 1}: Please enter surname`);
                    }
                    if (!unitNo) {
                        errors.push(`Buyer ${i + 1}: Please enter unit number`);
                    }
                }
            }
            
            if (!hasValidBuyer) {
                errors.push('Please add at least one buyer with complete information');
            }
        }
        
        // Check for duplicate unit numbers
        const unitNos = Array.from(unitNumbers)
            .map(input => input.value.trim())
            .filter(value => value !== '');
        
        const duplicateUnits = unitNos.filter((unit, index) => unitNos.indexOf(unit) !== index);
        if (duplicateUnits.length > 0) {
            errors.push(`Duplicate unit numbers found: ${[...new Set(duplicateUnits)].join(', ')}`);
        }
        
        if (errors.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Buyers List Validation Error',
                html: '<div style="text-align: left;"><strong>Please fix the following errors:</strong><br><br>' + 
                      errors.map(error => '• ' + error).join('<br>') + '</div>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        return true;
    }

    // Make validation functions globally accessible
    window.validateStep1 = validateStep1;
    window.validateStep3 = validateStep3;
    window.validateStep4 = validateStep4;
</script>
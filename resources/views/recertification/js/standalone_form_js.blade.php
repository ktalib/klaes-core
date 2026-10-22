<script>
// Application state
let currentStep = 1;
const totalSteps = 8;

// Form data state
let formData = {};
let ownersCount = 0;

// Development flags
let skipValidation = false; // Ctrl+Click on Next still bypasses for testing
let autoFillEnabled = false;

// Sample data for auto-fill
const sampleData = {
    applicationDate: new Date().toISOString().split('T')[0],
    surname: 'IBRAHIM',
    firstName: 'MUHAMMAD',
    middleName: 'ALIYU',
    title: 'MR',
    occupation: 'ENGINEER',
    dateOfBirth: '1985-05-15',
    nationality: 'NIGERIAN',
    stateOfOrigin: 'KANO',
    lgaOfOrigin: 'KANO MUNICIPAL',
    nin: '12345678901',
    gender: 'male',
    maritalStatus: 'married',
    phoneNo: '08012345678',
    addressLine1: '123 MAIN STREET',
    cityTown: 'KANO',
    state: 'KANO',
    emailAddress: 'test@example.com',
    applicantType: 'Individual',
    titleHolderSurname: 'IBRAHIM',
    titleHolderFirstName: 'MUHAMMAD',
    cofoNumber: 'KN/2023/001',
    isOriginalOwner: 'yes',
    isEncumbered: 'no',
    hasMortgage: 'no',
    plotNumber: 'PLOT 123',
    fileNumber: 'FILE/2023/001',
    plotSize: '0.5',
    layoutDistrict: 'GRA',
    lga: 'Kano Municipal',
    currentLandUse: 'residential',
    plotStatus: 'developed',
    modeOfAllocation: 'direct-allocation',
    paymentMethod: 'online',
    agreeTerms: true
};

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('Standalone Form - DOM Content Loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Set up event listeners
    setupEventListeners();
    
    // Set current date and update display
    setTimeout(() => {
        setCurrentDate();
        setupLandFilePicker();
        updateStepDisplay();
        setupDevelopmentControls();
        setupFileUploads();
        
        // Initialize applicant type toggle after DOM is fully ready
        setTimeout(() => {
            setupApplicantTypeToggle();
        }, 200);
    }, 100);
});

function setupEventListeners() {
    console.log('Setting up event listeners for standalone form...');
    
    // Navigation buttons
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', previousStep);
        console.log('Previous button event listener added');
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            console.log('Next button clicked!', e);
            nextStep(e);
        });
        console.log('Next button event listener added');
    }
    
    // Step indicator click navigation
    for (let i = 1; i <= totalSteps; i++) {
        const stepCircle = document.getElementById(`step-${i}`);
        if (stepCircle) {
            stepCircle.addEventListener('click', () => goToStep(i));
            stepCircle.style.cursor = 'pointer';
            stepCircle.title = `Go to Step ${i}`;
        }
    }
    
    // Form field updates
    const form = document.getElementById('recertification-form');
    if (form) {
        form.addEventListener('input', handleFormInput);
        form.addEventListener('change', handleFormChange);
        console.log('Form event listeners added');
    }
    
    // Conditional field displays
    setupConditionalFields();

    // Multiple owners controls
    setupMultipleOwnersControls();
    
    // Land use form type display
    setupLandUseFormTypeDisplay();
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts);
    
    console.log('Event listeners setup complete');
}

function setupApplicantTypeToggle() {
    console.log('Setting up applicant type toggle...');
    
    const typeSelect = document.getElementById('applicantType');
    const individual = document.getElementById('individual-fields');
    const corporate = document.getElementById('corporate-fields');
    const multipleOwners = document.getElementById('multiple-owners-fields');

    const setRequired = (selectorList, required) => {
        selectorList.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                if (required) {
                    el.setAttribute('required', 'required');
                } else {
                    el.removeAttribute('required');
                }
            });
        });
    };

    const updateView = (value) => {
        console.log('updateView called with:', value);
        
        // Hide all form sections first
        if (individual) individual.classList.add('hidden');
        if (corporate) corporate.classList.add('hidden');
        if (multipleOwners) multipleOwners.classList.add('hidden');

        // Get sidebar sections
        const individualPhotoSection = document.getElementById('individual-photo-section');
        const corporateDocumentSection = document.getElementById('corporate-document-section');
        const multipleOwnersSidebar = document.getElementById('multiple-owners-sidebar');
        
        console.log('Sidebar elements found:', {
            individualPhotoSection: !!individualPhotoSection,
            corporateDocumentSection: !!corporateDocumentSection,
            multipleOwnersSidebar: !!multipleOwnersSidebar
        });
        
        // Hide ALL sidebar sections first
        if (individualPhotoSection) {
            individualPhotoSection.classList.add('hidden');
            console.log('Hidden individual photo section');
        }
        if (corporateDocumentSection) {
            corporateDocumentSection.classList.add('hidden');
            console.log('Hidden corporate document section');
        }
        if (multipleOwnersSidebar) {
            multipleOwnersSidebar.classList.add('hidden');
            console.log('Hidden multiple owners sidebar');
        }

        // Clear all required fields
        setRequired([
            '#surname', '#firstName', '#occupation', '#dateOfBirth', '#nationality', '#stateOfOrigin',
            'input[name="gender"]', 'input[name="maritalStatus"]'
        ], false);
        setRequired(['#organisationName', '#cacRegistrationNumber', '#typeOfOrganisation', '#typeOfBusiness'], false);
        setOwnersRequired(false);

        // Show appropriate sections based on applicant type
        if (value === 'Individual') {
            console.log('Showing Individual sections');
            if (individual) individual.classList.remove('hidden');
            if (individualPhotoSection) {
                individualPhotoSection.classList.remove('hidden');
                console.log('Showed individual photo section');
            }
            setRequired([
                '#surname', '#firstName', '#occupation', '#dateOfBirth', '#nationality', '#stateOfOrigin',
                'input[name="gender"]', 'input[name="maritalStatus"]'
            ], true);
        } else if (value === 'Corporate') {
            console.log('Showing Corporate sections');
            if (corporate) corporate.classList.remove('hidden');
            if (corporateDocumentSection) {
                corporateDocumentSection.classList.remove('hidden');
                console.log('Showed corporate document section');
            }
            setRequired(['#organisationName', '#cacRegistrationNumber', '#typeOfOrganisation', '#typeOfBusiness'], true);
        } else if (value === 'Government Body') {
            console.log('Showing Government Body sections');
            if (corporate) corporate.classList.remove('hidden');
            if (corporateDocumentSection) {
                corporateDocumentSection.classList.remove('hidden');
                console.log('Showed corporate document section for Government Body');
            }
            const corporateHeaderEl = document.getElementById('corporate-header');
            const corporateDescEl = document.getElementById('corporate-description');
            if (corporateHeaderEl) corporateHeaderEl.textContent = 'Government Body Details';
            if (corporateDescEl) corporateDescEl.textContent = 'Please provide the following government body information:';
            setRequired(['#organisationName', '#cacRegistrationNumber', '#typeOfOrganisation', '#typeOfBusiness'], true);
        } else if (value === 'Multiple Owners') {
            console.log('Showing Multiple Owners sections');
            if (multipleOwners) multipleOwners.classList.remove('hidden');
            if (multipleOwnersSidebar) {
                multipleOwnersSidebar.classList.remove('hidden');
                console.log('Showed multiple owners sidebar');
            }
            if (ownersCount === 0) addOwnerBlock();
            setOwnersRequired(true);
        }
    };

    if (typeSelect) {
        console.log('Applicant type select found, current value:', typeSelect.value);
        
        // Set initial state
        updateView(typeSelect.value);
        
        // Add change listener
        typeSelect.addEventListener('change', (e) => {
            console.log('Applicant type changed to:', e.target.value);
            formData['applicantType'] = e.target.value;
            updateView(e.target.value);
        });
    } else {
        console.error('Applicant type select not found!');
    }
}

function setupDevelopmentControls() {
    console.log('Setting up development controls...');
    
    // Skip validation checkbox
    const skipValidationCheckbox = document.getElementById('dev-skip-validation');
    if (skipValidationCheckbox) {
        skipValidationCheckbox.checked = skipValidation;
        skipValidationCheckbox.addEventListener('change', function() {
            skipValidation = this.checked;
            showToast(
                skipValidation ? 'Validation disabled for development' : 'Validation enabled',
                skipValidation ? 'warning' : 'info'
            );
        });
    }
    
    // Auto-fill checkbox
    const autoFillCheckbox = document.getElementById('dev-auto-fill');
    if (autoFillCheckbox) {
        autoFillCheckbox.addEventListener('change', function() {
            autoFillEnabled = this.checked;
            if (autoFillEnabled) {
                autoFillForm();
                showToast('Form auto-filled with sample data', 'info');
            } else {
                resetForm();
                showToast('Form reset', 'info');
            }
        });
    }
    
    // Debug button
    const debugBtn = document.getElementById('dev-debug-btn');
    if (debugBtn) {
        debugBtn.addEventListener('click', debugFormWizard);
    }
    
    // Reset button
    const resetBtn = document.getElementById('dev-reset-btn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            resetForm();
            showToast('Form reset to initial state', 'info');
        });
    }
}

function autoFillForm() {
    console.log('Auto-filling form with sample data...');
    
    // Fill text inputs
    Object.keys(sampleData).forEach(key => {
        const element = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
        if (element) {
            if (element.type === 'checkbox') {
                element.checked = sampleData[key];
            } else if (element.type === 'radio') {
                const radioGroup = document.querySelectorAll(`[name="${key}"]`);
                radioGroup.forEach(radio => {
                    if (radio.value === sampleData[key]) {
                        radio.checked = true;
                    }
                });
            } else {
                element.value = sampleData[key];
            }
            
            // Update form data
            formData[key] = sampleData[key];
        }
    });
    
    // Trigger change events for conditional fields
    document.querySelectorAll('input[name="isOriginalOwner"]').forEach(radio => {
        if (radio.checked) radio.dispatchEvent(new Event('change'));
    });
    
    document.querySelectorAll('input[name="isEncumbered"]').forEach(radio => {
        if (radio.checked) radio.dispatchEvent(new Event('change'));
    });
    
    document.querySelectorAll('input[name="hasMortgage"]').forEach(radio => {
        if (radio.checked) radio.dispatchEvent(new Event('change'));
    });
}

function setOwnersRequired(required) {
    document.querySelectorAll('.owner-block [data-required="true"]').forEach(el => {
        if (required) {
            el.setAttribute('required', 'required');
        } else {
            el.removeAttribute('required');
        }
    });
}

function setupMultipleOwnersControls() {
    const addBtn = document.getElementById('add-owner-btn');
    if (!addBtn) return;

    addBtn.addEventListener('click', () => addOwnerBlock());
}

function addOwnerBlock() {
    ownersCount++;
    const list = document.getElementById('owners-list');
    if (!list) return;

    const index = ownersCount;
    const wrapper = document.createElement('div');
    wrapper.className = 'owner-block border border-gray-200 rounded-md p-4';
    wrapper.dataset.index = index;

    // Template replicates individual fields but with array-style names
    wrapper.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <h5 class="font-semibold">Owner #${index}</h5>
            <button type="button" class="remove-owner inline-flex items-center justify-center rounded-md font-medium text-xs px-2 py-1 transition-all cursor-pointer bg-red-600 text-white hover:bg-red-700">Remove</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">Surname <span class="text-red-500">*</span></label>
                <input type="text" name="owners[${index}][surname]" data-required="true" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="SURNAME" />
            </div>
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="owners[${index}][firstName]" data-required="true" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="FIRST NAME" />
            </div>
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">Other Names</label>
                <input type="text" name="owners[${index}][middleName]" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="MIDDLE NAME" />
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <select name="owners[${index}][title]" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                    <option value="">Select Title</option>
                    <option value="MR">MR</option>
                    <option value="MRS">MRS</option>
                    <option value="MISS">MISS</option>
                    <option value="DR">DR</option>
                    <option value="PROF">PROF</option>
                    <option value="ENG">ENG</option>
                    <option value="ARC">ARC</option>
                    <option value="ALHAJI">ALHAJI</option>
                    <option value="HAJIYA">HAJIYA</option>
                </select>
            </div>
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">Occupation <span class="text-red-500">*</span></label>
                <input type="text" name="owners[${index}][occupation]" data-required="true" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="OCCUPATION" />
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                <input type="date" name="owners[${index}][dateOfBirth]" data-required="true" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" />
            </div>
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nationality <span class="text-red-500">*</span></label>
                <input type="text" name="owners[${index}][nationality]" data-required="true" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="NIGERIAN" />
            </div>
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">State of Origin <span class="text-red-500">*</span></label>
                <input type="text" name="owners[${index}][stateOfOrigin]" data-required="true" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="STATE OF ORIGIN" />
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">LGA of Origin</label>
                <input type="text" name="owners[${index}][lgaOfOrigin]" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="LGA OF ORIGIN" />
            </div>
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">NIN</label>
                <input type="text" name="owners[${index}][nin]" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" placeholder="NATIONAL IDENTIFICATION NUMBER" />
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">Gender <span class="text-red-500">*</span></label>
                <div class="flex gap-4">
                    <label class="radio-item">
                        <input type="radio" name="owners[${index}][gender]" value="male" data-required="true" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Male</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="owners[${index}][gender]" value="female" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Female</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">Marital Status <span class="text-red-500">*</span></label>
                <div class="flex gap-4 flex-wrap">
                    <label class="radio-item">
                        <input type="radio" name="owners[${index}][maritalStatus]" value="single" data-required="true" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Single</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="owners[${index}][maritalStatus]" value="married" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Married</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="owners[${index}][maritalStatus]" value="divorced" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Divorced</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="owners[${index}][maritalStatus]" value="widowed" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Widowed</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-field mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Maiden Name (if applicable)</label>
            <input type="text" name="owners[${index}][maidenName]" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase" placeholder="MAIDEN NAME" />
        </div>
        
        <!-- Owner Passport Photo -->
        <div class="owner-photo-upload-area photo-upload-area mt-4 text-center border-2 border-dashed border-gray-300 rounded-lg p-4">
            <input type="file" name="owners[${index}][passportPhoto]" accept="image/*" class="hidden owner-photo-input" />
            <div class="owner-photo-preview-container mb-3 hidden">
                <!-- Passport Card Style Preview -->
                <div class="passport-card bg-white border-2 border-gray-300 rounded-lg p-2 mx-auto" style="width: 100px;">
                    <div class="passport-photo-frame bg-gray-100 border border-gray-300 rounded" style="width: 84px; height: 104px; margin: 0 auto;">
                        <img class="owner-photo-preview w-full h-full object-cover rounded" src="" alt="Owner Photo Preview" />
                    </div>
                    <div class="text-center mt-1">
                        <div class="text-xs font-semibold text-gray-700">PASSPORT</div>
                        <div class="text-xs text-gray-500">2" X 2"</div>
                    </div>
                </div>
            </div>
            <i data-lucide="camera" class="h-6 w-6 mb-2 text-gray-400 owner-camera-icon"></i>
            <div class="text-xs font-semibold mb-2">PASSPORT PHOTOGRAPH</div>
            <div class="text-xs text-gray-500 mb-2">(2" X 2")</div>
            <button type="button" class="owner-upload-btn inline-flex items-center justify-center rounded-md font-medium text-xs px-2.5 py-1.5 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                Upload Photo
            </button>
            <div class="owner-photo-filename text-xs text-gray-600 mt-2 hidden"></div>
            <div class="text-xs text-red-600 mt-2">
                NOTE: DO NOT put a staple pin over the face region of the photo
            </div>
        </div>
    `;

    list.appendChild(wrapper);

    // Remove owner handler
    wrapper.querySelector('.remove-owner').addEventListener('click', () => {
        wrapper.remove();
        ownersCount--;
    });

    // Wire up owner photo upload controls with preview
    const uploadBtn = wrapper.querySelector('.owner-upload-btn');
    const fileInput = wrapper.querySelector('.owner-photo-input');
    const fileNameEl = wrapper.querySelector('.owner-photo-filename');
    const previewContainer = wrapper.querySelector('.owner-photo-preview-container');
    const previewImg = wrapper.querySelector('.owner-photo-preview');
    const cameraIcon = wrapper.querySelector('.owner-camera-icon');
    
    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    showToast('Please select a valid image file', 'error');
                    this.value = '';
                    return;
                }
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('File size must be less than 5MB', 'error');
                    this.value = '';
                    return;
                }
                
                // Show file info
                if (fileNameEl) {
                    fileNameEl.textContent = `Selected: ${file.name}`;
                    fileNameEl.classList.remove('hidden');
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                    }
                    if (previewContainer) {
                        previewContainer.classList.remove('hidden');
                    }
                    if (cameraIcon) {
                        cameraIcon.classList.add('hidden');
                    }
                };
                reader.readAsDataURL(file);
                
                showToast(`Owner #${index} passport photo selected successfully`, 'success');
            } else {
                // Reset if no file selected
                if (fileNameEl) {
                    fileNameEl.textContent = '';
                    fileNameEl.classList.add('hidden');
                }
                if (previewContainer) {
                    previewContainer.classList.add('hidden');
                }
                if (cameraIcon) {
                    cameraIcon.classList.remove('hidden');
                }
            }
        });
    }

    // Re-initialize Lucide icons for the new elements
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Ensure required flags are set if Multiple Owners is active
    const typeSelect = document.getElementById('applicantType');
    if (typeSelect && typeSelect.value === 'Multiple Owners') {
        setOwnersRequired(true);
    }
}

function setupFileUploads() {
    console.log('Setting up file upload handlers...');
    
    // Main passport photo upload (Individual only)
    const passportUploadBtn = document.getElementById('passport-upload-btn');
    const passportFileInput = document.getElementById('passportPhoto');
    const passportPreview = document.getElementById('photo-preview');
    const passportPreviewContainer = document.getElementById('photo-preview-container');
    const passportFileInfo = document.getElementById('passport-file-info');
    const passportFileName = document.getElementById('passport-file-name');
    const cameraIcon = document.getElementById('camera-icon');
    
    if (passportUploadBtn && passportFileInput) {
        passportUploadBtn.addEventListener('click', () => {
            passportFileInput.click();
        });
        
        passportFileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    showToast('Please select a valid image file', 'error');
                    this.value = '';
                    return;
                }
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('File size must be less than 5MB', 'error');
                    this.value = '';
                    return;
                }
                
                // Show file info
                if (passportFileName) {
                    passportFileName.textContent = `Selected: ${file.name}`;
                }
                if (passportFileInfo) {
                    passportFileInfo.classList.remove('hidden');
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (passportPreview) {
                        passportPreview.src = e.target.result;
                    }
                    if (passportPreviewContainer) {
                        passportPreviewContainer.classList.remove('hidden');
                    }
                    if (cameraIcon) {
                        cameraIcon.classList.add('hidden');
                    }
                };
                reader.readAsDataURL(file);
                
                showToast('Passport photo selected successfully', 'success');
            } else {
                // Reset if no file selected
                if (passportFileInfo) {
                    passportFileInfo.classList.add('hidden');
                }
                if (passportPreviewContainer) {
                    passportPreviewContainer.classList.add('hidden');
                }
                if (cameraIcon) {
                    cameraIcon.classList.remove('hidden');
                }
            }
        });
    }
    
    // CAC document upload (Corporate/Government Body only)
    const cacUploadBtn = document.getElementById('cac-upload-btn');
    const cacFileInput = document.getElementById('cacDocument');
    const cacFileInfo = document.getElementById('cac-file-info');
    const cacFileName = document.getElementById('cac-file-name');
    const cacFileSize = document.getElementById('cac-file-size');
    
    if (cacUploadBtn && cacFileInput) {
        cacUploadBtn.addEventListener('click', () => {
            cacFileInput.click();
        });
        
        cacFileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validate file type
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    showToast('Please select a PDF, JPG, or PNG file', 'error');
                    this.value = '';
                    return;
                }
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('File size must be less than 5MB', 'error');
                    this.value = '';
                    return;
                }
                
                // Show file info
                if (cacFileName) {
                    cacFileName.textContent = file.name;
                }
                if (cacFileSize) {
                    const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                    cacFileSize.textContent = `${sizeInMB} MB`;
                }
                if (cacFileInfo) {
                    cacFileInfo.classList.remove('hidden');
                }
                
                showToast('CAC document selected successfully', 'success');
            } else {
                // Reset if no file selected
                if (cacFileInfo) {
                    cacFileInfo.classList.add('hidden');
                }
            }
        });
    }
    
    console.log('File upload handlers setup complete');
}

function setupConditionalFields() {
    // Original owner conditional fields
    document.querySelectorAll('input[name="isOriginalOwner"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const ownershipDetails = document.getElementById('ownership-details');
            if (ownershipDetails) {
                if (this.value === 'no') {
                    ownershipDetails.classList.remove('hidden');
                } else {
                    ownershipDetails.classList.add('hidden');
                }
            }
        });
    });
    
    // Encumbrance conditional fields
    document.querySelectorAll('input[name="isEncumbered"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const encumbranceReason = document.getElementById('encumbrance-reason');
            if (encumbranceReason) {
                if (this.value === 'yes') {
                    encumbranceReason.classList.remove('hidden');
                } else {
                    encumbranceReason.classList.add('hidden');
                }
            }
        });
    });
    
    // Mortgage conditional fields
    document.querySelectorAll('input[name="hasMortgage"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const mortgageDetails = document.getElementById('mortgage-details');
            if (mortgageDetails) {
                if (this.value === 'yes') {
                    mortgageDetails.classList.remove('hidden');
                } else {
                    mortgageDetails.classList.add('hidden');
                }
            }
        });
    });
}

function handleFormInput(event) {
    const { name, value } = event.target;
    if (name) {
        formData[name] = value;
        clearFieldError(name);
    }
}

function handleFormChange(event) {
    const { name, value, type, checked } = event.target;
    if (name) {
        if (type === 'checkbox') {
            formData[name] = checked;
        } else {
            formData[name] = value;
        }
        clearFieldError(name);
    }
}

function setCurrentDate() {
    const today = new Date().toISOString().split('T')[0];
    const applicationDateField = document.getElementById('applicationDate');
    if (applicationDateField) {
        applicationDateField.value = today;
        formData.applicationDate = today;
    }
}

// Fetch next file number for the form
async function fetchNextFileNumber() {
    try {
        const response = await fetch('/recertification/next-file-number', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        
        if (data.success && data.file_number) {
            const fileNumberInput = document.getElementById('fileNumber');
            const fileNumberDisplay = document.getElementById('file-number-display');
            
            if (fileNumberInput) {
                fileNumberInput.value = data.file_number;
                fileNumberInput.placeholder = data.file_number;
                formData.fileNumber = data.file_number;
                console.log('File number loaded:', data.file_number);
            }
            
            // Update the header display
            if (fileNumberDisplay) {
                fileNumberDisplay.textContent = data.file_number;
            }
            
            showToast(`File number generated: ${data.file_number}`, 'success');
        } else {
            throw new Error('Failed to get file number from server');
        }
    } catch (error) {
        console.error('Error fetching file number:', error);
        
        // Set fallback file number - avoid KN3000 to prevent confusion
        const fileNumberInput = document.getElementById('fileNumber');
        const fileNumberDisplay = document.getElementById('file-number-display');
        const fallbackNumber = 'KN3001';
        
        if (fileNumberInput) {
            fileNumberInput.value = fallbackNumber;
            fileNumberInput.placeholder = fallbackNumber;
            formData.fileNumber = fallbackNumber;
        }
        
        if (fileNumberDisplay) {
            fileNumberDisplay.textContent = fallbackNumber;
        }
        
        showToast('Using fallback file number: ' + fallbackNumber, 'warning');
    }
}

// Fetch New KANGIS file number for the header display
async function fetchNewKangisFileNumber() {
    try {
        const response = await fetch('/recertification/next-new-kangis-file-number', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        
        if (data.success && data.new_kangis_file_number) {
            const newKangisFileNumber = data.new_kangis_file_number;
            
            // Update the display
            const newKangisDisplay = document.getElementById('new-kangis-file-number-display');
            const newKangisInput = document.getElementById('newKangisFileNo');
            
            if (newKangisDisplay) {
                newKangisDisplay.textContent = newKangisFileNumber;
            }
            
            if (newKangisInput) {
                newKangisInput.value = newKangisFileNumber;
                formData.newKangisFileNo = newKangisFileNumber;
            }
            
            console.log('New KANGIS file number generated:', newKangisFileNumber);
            showToast(`New KANGIS file number generated: ${newKangisFileNumber}`, 'success');
        } else {
            throw new Error('Failed to get New KANGIS file number from server');
        }
        
    } catch (error) {
        console.error('Error fetching New KANGIS file number:', error);
        
        // Set fallback number
        const fallbackNumber = 'KN3001';
        const newKangisDisplay = document.getElementById('new-kangis-file-number-display');
        const newKangisInput = document.getElementById('newKangisFileNo');
        
        if (newKangisDisplay) {
            newKangisDisplay.textContent = fallbackNumber;
        }
        
        if (newKangisInput) {
            newKangisInput.value = fallbackNumber;
            formData.newKangisFileNo = fallbackNumber;
        }
        
        showToast('Using fallback New KANGIS file number: ' + fallbackNumber, 'warning');
    }
}

function updateStepDisplay() {
    console.log('Updating step display, current step:', currentStep);
    // Moving between steps saves the draft.
    if (typeof draftStepChanged === 'function') draftStepChanged();

    // Hide all step contents
    document.querySelectorAll('.step-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Show current step content
    const currentStepContent = document.getElementById(`step-content-${currentStep}`);
    if (currentStepContent) {
        currentStepContent.classList.remove('hidden');
    }
    
    // Update step indicators
    for (let i = 1; i <= totalSteps; i++) {
        const stepCircle = document.getElementById(`step-${i}`);
        const stepLine = document.getElementById(`line-${i}`);
        
        if (stepCircle) {
            if (i <= currentStep) {
                stepCircle.classList.remove('inactive');
                stepCircle.classList.add('active');
            } else {
                stepCircle.classList.remove('active');
                stepCircle.classList.add('inactive');
            }
        }
        
        if (stepLine) {
            if (i < currentStep) {
                stepLine.classList.remove('inactive');
                stepLine.classList.add('active');
            } else {
                stepLine.classList.remove('active');
                stepLine.classList.add('inactive');
            }
        }
    }
    
    // Update navigation buttons
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const nextText = nextBtn?.querySelector('.next-text');
    
    if (prevBtn) {
        prevBtn.disabled = currentStep === 1;
    }
    
    if (nextText) {
        if (currentStep === totalSteps) {
            nextText.textContent = 'Submit Application';
        } else {
            nextText.textContent = 'Next';
        }
    }
}

function previousStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepDisplay();
        showToast(`Moved to Step ${currentStep}`, 'info');
    }
}

async function nextStep(event) {
    console.log('nextStep called, currentStep:', currentStep);
    
    if (currentStep < totalSteps) {
        // Nothing goes past step 1 without a land file, bypass or not.
        if (!requireLandFile()) return;

        // Check for development bypass
        const forceSkip = event && (event.ctrlKey || event.metaKey);
        
        if (skipValidation || forceSkip || validateCurrentStep()) {
            console.log('Moving to next step...');
            currentStep++;
            
            // If moving to step 7 (summary), populate the summary
            if (currentStep === totalSteps) {
                populateApplicationSummary();
            }
            
            updateStepDisplay();
            
            if (forceSkip) {
                showToast('Validation bypassed with Ctrl+Click', 'warning');
            } else if (skipValidation) {
                showToast(`Step ${currentStep - 1} completed (validation skipped)`, 'info');
            } else {
                showToast(`Step ${currentStep - 1} completed`, 'success');
            }
        } else {
            console.log('Validation failed, staying on current step');
        }
    } else {
        console.log('On final step, submitting form...');
        await submitForm();
    }
}

function validateCurrentStep() {
    if (skipValidation) {
        return true;
    }
    
    const currentStepElement = document.getElementById(`step-content-${currentStep}`);
    if (!currentStepElement) {
        console.warn('Current step element not found');
        return true; // Allow progression if step element is missing
    }
    
    const requiredFields = currentStepElement.querySelectorAll('[required]');
    let isValid = true;
    
    // Clear previous errors
    currentStepElement.querySelectorAll('.form-field').forEach(field => {
        field.classList.remove('error');
    });
    
    // Validate required fields
    requiredFields.forEach(field => {
        const value = field.type === 'checkbox' ? field.checked : field.value;
        const isRadioGroup = field.type === 'radio';
        
        if (isRadioGroup) {
            const radioGroup = currentStepElement.querySelectorAll(`input[name="${field.name}"]`);
            const isChecked = Array.from(radioGroup).some(radio => radio.checked);
            if (!isChecked) {
                showFieldError(field.name);
                isValid = false;
            }
        } else if (!value || (typeof value === 'string' && value.trim() === '')) {
            showFieldError(field.name);
            isValid = false;
        }
    });
    
    // Additional validation for step 6 (terms agreement)
    if (currentStep === 7) {
        const agreeTerms = document.getElementById('agreeTerms');
        if (agreeTerms && !agreeTerms.checked) {
            showFieldError('agreeTerms');
            isValid = false;
        }
    }
    
    if (!isValid) {
        showToast('Please fill in all required fields correctly', 'error');
        // Scroll to first error field
        const firstErrorField = currentStepElement.querySelector('.form-field.error');
        if (firstErrorField) {
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    return isValid;
}

function showFieldError(fieldName) {
    const field = document.querySelector(`[name="${fieldName}"]`);
    if (field) {
        const formField = field.closest('.form-field');
        if (formField) {
            formField.classList.add('error');
        }
    }
}

function clearFieldError(fieldName) {
    const field = document.querySelector(`[name="${fieldName}"]`);
    if (field) {
        const formField = field.closest('.form-field');
        if (formField) {
            formField.classList.remove('error');
        }
    }
}

async function submitForm() {
    const nextBtn = document.getElementById('next-btn');
    const nextText = nextBtn?.querySelector('.next-text');
    const loadingSpinner = nextBtn?.querySelector('.loading-spinner');
    
    // Show loading state
    if (nextBtn) nextBtn.disabled = true;
    if (nextText) nextText.textContent = 'Submitting...';
    if (loadingSpinner) loadingSpinner.classList.remove('hidden');
    
    try {
        // Collect all form data with files
        const form = document.getElementById('recertification-form');
        const formBody = new FormData(form);

        // Post to backend
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formBody
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            const firstError = result?.errors ? Object.values(result.errors).flat()[0] : null;
            throw new Error(firstError || result?.message || 'Failed to submit application.');
        }

        draftClosed = true;
        clearTimeout(draftTimer);
        showToast(`Application submitted. Ref: ${result.reference}, KANGIS File No: ${result.kangis_file_no}`, 'success');
        // Redirect after short delay
        setTimeout(() => {
            window.location.href = '/recertification';
        }, 2500);

    } catch (error) {
        console.error('Error submitting application:', error);
        showToast(error.message || 'Failed to submit application. Please try again.', 'error');
    } finally {
        // Reset loading state
        if (nextBtn) nextBtn.disabled = false;
        if (nextText) nextText.textContent = 'Submit Application';
        if (loadingSpinner) loadingSpinner.classList.add('hidden');
    }
}

function resetForm() {
    // Reset step
    currentStep = 1;
    updateStepDisplay();
    
    // Reset form
    const form = document.getElementById('recertification-form');
    if (form) {
        form.reset();
    }
    
    // Reset form data
    formData = {};
    
    // Clear all errors
    document.querySelectorAll('.form-field').forEach(field => {
        field.classList.remove('error');
    });
    
    // Hide conditional fields
    const ownershipDetails = document.getElementById('ownership-details');
    const encumbranceReason = document.getElementById('encumbrance-reason');
    const mortgageDetails = document.getElementById('mortgage-details');
    
    if (ownershipDetails) ownershipDetails.classList.add('hidden');
    if (encumbranceReason) encumbranceReason.classList.add('hidden');
    if (mortgageDetails) mortgageDetails.classList.add('hidden');
    
    // Reset development controls
    const skipValidationCheckbox = document.getElementById('dev-skip-validation');
    const autoFillCheckbox = document.getElementById('dev-auto-fill');
    
    if (skipValidationCheckbox) skipValidationCheckbox.checked = false;
    if (autoFillCheckbox) autoFillCheckbox.checked = false;
    
    skipValidation = false;
    autoFillEnabled = false;
    
    // Set current date again
    setCurrentDate();
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;
    
    const toastId = `toast-${Date.now()}`;
    
    const typeClasses = {
        success: 'bg-green-600 text-white',
        error: 'bg-red-600 text-white',
        warning: 'bg-yellow-600 text-white',
        info: 'bg-blue-600 text-white'
    };
    
    const typeIcons = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `${typeClasses[type]} px-4 py-2 rounded-md shadow-lg flex items-center gap-2 transform translate-x-full transition-transform duration-300`;
    toast.innerHTML = `
        <i data-lucide="${typeIcons[type]}" class="h-4 w-4"></i>
        <span>${message}</span>
        <button onclick="removeToast('${toastId}')" class="ml-2 hover:bg-black/20 rounded p-1">
            <i data-lucide="x" class="h-3 w-3"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        removeToast(toastId);
    }, 5000);
}

function removeToast(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }
}

// Step navigation functions
function goToStep(stepNumber) {
    if (stepNumber >= 1 && stepNumber <= totalSteps) {
        currentStep = stepNumber;
        updateStepDisplay();
        showToast(`Navigated to Step ${stepNumber}`, 'info');
    }
}

function handleKeyboardShortcuts(event) {
    // Ctrl/Cmd + Arrow keys for navigation
    if (event.ctrlKey || event.metaKey) {
        switch(event.key) {
            case 'ArrowLeft':
                event.preventDefault();
                previousStep();
                break;
            case 'ArrowRight':
                event.preventDefault();
                nextStep(event);
                break;
        }
    }
    
    // Number keys to jump to steps (Ctrl/Cmd + 1-7)
    if (event.key >= '1' && event.key <= '7' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        goToStep(parseInt(event.key));
    }
    
    // Escape to reset form
    if (event.key === 'Escape' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        resetForm();
        showToast('Form reset via keyboard shortcut', 'info');
    }
}

// Debug function for development
function debugFormWizard() {
    console.log('=== Standalone Form Wizard Debug Info ===');
    console.log('Current Step:', currentStep);
    console.log('Total Steps:', totalSteps);
    console.log('Form Data:', formData);
    console.log('Skip Validation:', skipValidation);
    console.log('Auto Fill Enabled:', autoFillEnabled);
    
    // Check if all step elements exist
    for (let i = 1; i <= totalSteps; i++) {
        const stepContent = document.getElementById(`step-content-${i}`);
        const stepCircle = document.getElementById(`step-${i}`);
        console.log(`Step ${i}:`, {
            content: stepContent ? 'exists' : 'missing',
            circle: stepCircle ? 'exists' : 'missing',
            visible: stepContent && !stepContent.classList.contains('hidden')
        });
    }
    
    // Check navigation buttons
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    console.log('Navigation buttons:', {
        prev: prevBtn ? 'exists' : 'missing',
        next: nextBtn ? 'exists' : 'missing'
    });
    
    // Check sidebar sections
    const individualPhotoSection = document.getElementById('individual-photo-section');
    const corporateDocumentSection = document.getElementById('corporate-document-section');
    const multipleOwnersSidebar = document.getElementById('multiple-owners-sidebar');
    
    console.log('Sidebar sections:', {
        individualPhotoSection: individualPhotoSection ? (individualPhotoSection.classList.contains('hidden') ? 'hidden' : 'visible') : 'missing',
        corporateDocumentSection: corporateDocumentSection ? (corporateDocumentSection.classList.contains('hidden') ? 'hidden' : 'visible') : 'missing',
        multipleOwnersSidebar: multipleOwnersSidebar ? (multipleOwnersSidebar.classList.contains('hidden') ? 'hidden' : 'visible') : 'missing'
    });
    
    // Show debug info in toast
    showToast('Debug info logged to console', 'info');
}

// Make functions available globally for debugging
window.debugFormWizard = debugFormWizard;
window.goToStep = goToStep;
window.autoFillForm = autoFillForm;
window.resetForm = resetForm;

// Quick test functions
window.testNextStep = function() {
    console.log('Testing next step...');
    nextStep();
};

window.testValidation = function() {
    skipValidation = !skipValidation;
    const checkbox = document.getElementById('dev-skip-validation');
    if (checkbox) checkbox.checked = skipValidation;
    showToast(`Validation ${skipValidation ? 'disabled' : 'enabled'}`, 'info');
};

// Function to populate application summary
function populateApplicationSummary() {
    console.log('Populating application summary...');
    
    // Get form data
    const form = document.getElementById('recertification-form');
    if (!form) return;
    
    const formData = new FormData(form);
    
    // Helper function to get form value
    const getValue = (name) => {
        const element = form.querySelector(`[name="${name}"]`);
        if (!element) return '-';
        
        if (element.type === 'radio') {
            const checked = form.querySelector(`[name="${name}"]:checked`);
            return checked ? checked.value : '-';
        } else if (element.type === 'checkbox') {
            return element.checked ? 'Yes' : 'No';
        } else {
            return element.value || '-';
        }
    };
    
    // Helper function to format text
    const formatText = (text) => {
        if (!text || text === '-') return '-';
        return text.toString().toUpperCase();
    };
    
    // Helper function to format currency
    const formatCurrency = (amount) => {
        if (!amount || amount === '-') return '-';
        return `₦${parseFloat(amount).toLocaleString()}`;
    };
    
    // Application Information
    document.getElementById('summary-application-date').textContent = getValue('applicationDate') || '-';
    document.getElementById('summary-file-number').textContent = getValue('landFileNo') || '-';
    document.getElementById('summary-application-type').textContent = formatText(getValue('applicationType'));
    document.getElementById('summary-application-reason').textContent = formatText(getValue('applicationReason'));
    
    // Applicant Details - depends on applicant type
    const applicantType = getValue('applicantType');
    const applicantDetailsContainer = document.getElementById('summary-applicant-details');
    
    if (applicantType === 'Corporate') {
        applicantDetailsContainer.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><span class="font-medium text-gray-700">Applicant Type:</span> <span class="ml-2 text-gray-900">Corporate</span></div>
                <div><span class="font-medium text-gray-700">Organisation Name:</span> <span class="ml-2 text-gray-900">${formatText(getValue('organisationName'))}</span></div>
                <div><span class="font-medium text-gray-700">CAC Registration No:</span> <span class="ml-2 text-gray-900">${formatText(getValue('cacRegistrationNumber'))}</span></div>
                <div><span class="font-medium text-gray-700">Type of Organisation:</span> <span class="ml-2 text-gray-900">${formatText(getValue('typeOfOrganisation'))}</span></div>
                <div><span class="font-medium text-gray-700">Type of Business:</span> <span class="ml-2 text-gray-900">${formatText(getValue('typeOfBusiness'))}</span></div>
            </div>
        `;
    } else if (applicantType === 'Government Body') {
        applicantDetailsContainer.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><span class="font-medium text-gray-700">Applicant Type:</span> <span class="ml-2 text-gray-900">Government Body</span></div>
                <div><span class="font-medium text-gray-700">Organisation Name:</span> <span class="ml-2 text-gray-900">${formatText(getValue('organisationName'))}</span></div>
                <div><span class="font-medium text-gray-700">Registration Status:</span> <span class="ml-2 text-gray-900">${formatText(getValue('cacRegistrationStatus'))}</span></div>
                <div><span class="font-medium text-gray-700">Type of Organisation:</span> <span class="ml-2 text-gray-900">${formatText(getValue('typeOfOrganisation'))}</span></div>
                <div><span class="font-medium text-gray-700">Type of Business:</span> <span class="ml-2 text-gray-900">${formatText(getValue('typeOfBusiness'))}</span></div>
            </div>
        `;
    } else if (applicantType === 'Multiple Owners') {
        let ownersHtml = '<div class="text-sm"><span class="font-medium text-gray-700">Applicant Type:</span> <span class="ml-2 text-gray-900">Multiple Owners</span></div>';
        ownersHtml += '<div class="mt-3"><span class="font-medium text-gray-700">Owners:</span></div>';
        ownersHtml += '<div class="mt-2 space-y-2">';
        
        // Get all owner blocks
        const ownerBlocks = document.querySelectorAll('.owner-block');
        ownerBlocks.forEach((block, index) => {
            const surname = block.querySelector(`[name="owners[${index + 1}][surname]"]`)?.value || '';
            const firstName = block.querySelector(`[name="owners[${index + 1}][firstName]"]`)?.value || '';
            const occupation = block.querySelector(`[name="owners[${index + 1}][occupation]"]`)?.value || '';
            
            if (surname || firstName) {
                ownersHtml += `<div class="bg-white p-2 rounded border text-xs">
                    <strong>Owner ${index + 1}:</strong> ${formatText(surname)} ${formatText(firstName)} - ${formatText(occupation)}
                </div>`;
            }
        });
        
        ownersHtml += '</div>';
        applicantDetailsContainer.innerHTML = ownersHtml;
    } else {
        // Individual or Government Body
        applicantDetailsContainer.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><span class="font-medium text-gray-700">Applicant Type:</span> <span class="ml-2 text-gray-900">${formatText(applicantType)}</span></div>
                <div><span class="font-medium text-gray-700">Full Name:</span> <span class="ml-2 text-gray-900">${formatText(getValue('surname'))} ${formatText(getValue('firstName'))} ${formatText(getValue('middleName'))}</span></div>
                <div><span class="font-medium text-gray-700">Title:</span> <span class="ml-2 text-gray-900">${formatText(getValue('title'))}</span></div>
                <div><span class="font-medium text-gray-700">Occupation:</span> <span class="ml-2 text-gray-900">${formatText(getValue('occupation'))}</span></div>
                <div><span class="font-medium text-gray-700">Date of Birth:</span> <span class="ml-2 text-gray-900">${getValue('dateOfBirth')}</span></div>
                <div><span class="font-medium text-gray-700">Nationality:</span> <span class="ml-2 text-gray-900">${formatText(getValue('nationality'))}</span></div>
                <div><span class="font-medium text-gray-700">State of Origin:</span> <span class="ml-2 text-gray-900">${formatText(getValue('stateOfOrigin'))}</span></div>
                <div><span class="font-medium text-gray-700">Gender:</span> <span class="ml-2 text-gray-900">${formatText(getValue('gender'))}</span></div>
                <div><span class="font-medium text-gray-700">Marital Status:</span> <span class="ml-2 text-gray-900">${formatText(getValue('maritalStatus'))}</span></div>
                <div><span class="font-medium text-gray-700">NIN:</span> <span class="ml-2 text-gray-900">${getValue('nin')}</span></div>
            </div>
        `;
    }
    
    // Contact Information
    const address = [getValue('addressLine1'), getValue('addressLine2'), getValue('cityTown'), getValue('state')].filter(x => x !== '-').join(', ');
    document.getElementById('summary-phone').textContent = getValue('phoneNo');
    document.getElementById('summary-email').textContent = getValue('emailAddress');
    document.getElementById('summary-address').textContent = address || '-';
    
    // Title Holder Information
    const titleHolder = [getValue('titleHolderTitle'), getValue('titleHolderSurname'), getValue('titleHolderFirstName'), getValue('titleHolderMiddleName')].filter(x => x !== '-').join(' ');
    document.getElementById('summary-title-holder').textContent = titleHolder || '-';
    document.getElementById('summary-cofo-number').textContent = getValue('cofoNumber');
    document.getElementById('summary-original-owner').textContent = getValue('isOriginalOwner') === 'yes' ? 'Yes' : 'No';
    document.getElementById('summary-instrument-type').textContent = formatText(getValue('instrumentType'));
    
    // Plot Details
    document.getElementById('summary-plot-number').textContent = formatText(getValue('plotNumber'));
    document.getElementById('summary-plot-size').textContent = getValue('plotSize') !== '-' ? getValue('plotSize') + ' hectares' : '-';
    document.getElementById('summary-layout-district').textContent = formatText(getValue('district'));
    document.getElementById('summary-lga').textContent = formatText(getValue('lga'));
    document.getElementById('summary-land-use').textContent = formatText(getValue('currentLandUse'));
    document.getElementById('summary-plot-status').textContent = formatText(getValue('plotStatus'));
    
    // Payment Information
    document.getElementById('summary-payment-method').textContent = formatText(getValue('paymentMethod'));
    document.getElementById('summary-payment-amount').textContent = formatCurrency(getValue('paymentAmount'));
    document.getElementById('summary-receipt-no').textContent = getValue('receiptNo');
    document.getElementById('summary-bank-name').textContent = formatText(getValue('bankName'));
    
    // Supporting Documents
    const documentsContainer = document.getElementById('summary-documents');
    const selectedDocuments = form.querySelectorAll('input[name="documents[]"]:checked');
    
    if (selectedDocuments.length > 0) {
        let documentsHtml = '';
        selectedDocuments.forEach(doc => {
            documentsHtml += `<div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="h-4 w-4 text-green-600"></i>
                <span class="text-gray-900">${doc.value.replace(/-/g, ' ').toUpperCase()}</span>
            </div>`;
        });
        documentsContainer.innerHTML = documentsHtml;
    } else {
        documentsContainer.innerHTML = '<div class="text-gray-500 italic">No documents selected</div>';
    }
    
    // Uploaded Files
    const uploadedFilesContainer = document.getElementById('summary-uploaded-files');
    let uploadedFilesHtml = '';
    
    // Check for passport photo
    const passportPhoto = form.querySelector('#passportPhoto');
    if (passportPhoto && passportPhoto.files.length > 0) {
        uploadedFilesHtml += `<div class="flex items-center gap-2">
            <i data-lucide="image" class="h-4 w-4 text-blue-600"></i>
            <span class="text-gray-900">Passport Photo: ${passportPhoto.files[0].name}</span>
        </div>`;
    }
    
    // Check for CAC document
    const cacDocument = form.querySelector('#cacDocument');
    if (cacDocument && cacDocument.files.length > 0) {
        uploadedFilesHtml += `<div class="flex items-center gap-2">
            <i data-lucide="file-text" class="h-4 w-4 text-purple-600"></i>
            <span class="text-gray-900">CAC Document: ${cacDocument.files[0].name}</span>
        </div>`;
    }
    
    // Check for owner photos
    const ownerPhotos = form.querySelectorAll('.owner-photo-input');
    ownerPhotos.forEach((input, index) => {
        if (input.files.length > 0) {
            uploadedFilesHtml += `<div class="flex items-center gap-2">
                <i data-lucide="image" class="h-4 w-4 text-green-600"></i>
                <span class="text-gray-900">Owner ${index + 1} Photo: ${input.files[0].name}</span>
            </div>`;
        }
    });
    
    if (uploadedFilesHtml) {
        uploadedFilesContainer.innerHTML = uploadedFilesHtml;
    } else {
        uploadedFilesContainer.innerHTML = '<div class="text-gray-500 italic">No files uploaded</div>';
    }
    
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    console.log('Application summary populated successfully');
}

// Function to go to a specific step (updated to handle summary population)
function goToStep(stepNumber) {
    if (stepNumber > 1 && !requireLandFile()) return;
    if (stepNumber >= 1 && stepNumber <= totalSteps) {
        currentStep = stepNumber;

        // If going to the summary, populate it
        if (currentStep === totalSteps) {
            populateApplicationSummary();
        }
        
        updateStepDisplay();
        showToast(`Navigated to Step ${stepNumber}`, 'info');
    }
}

// Function to setup land use form type display
function setupLandUseFormTypeDisplay() {
    console.log('Setting up land use form type display...');
    
    // Land use mapping to form types
    const landUseFormTypes = {
        'residential': 'RESIDENTIAL FORM',
        'commercial': 'COMMERCIAL FORM',
        'industrial': 'INDUSTRIAL FORM',
        'agricultural': 'AGRICULTURAL FORM',
        'educational': 'EDUCATIONAL FORM',
        'religious': 'RELIGIOUS FORM',
        'public': 'PUBLIC FORM',
        'ngo': 'NGO FORM',
        'social': 'SOCIAL FORM',
        'petrol-station': 'PETROL STATION FORM',
        'gkn': 'GKN FORM',
        'mixed-use': 'MIXED USE FORM'
    };
    
    // Get the form type display element
    const formTypeDisplay = document.getElementById('form-type-display');
    
    // Function to update form type display
    const updateFormTypeDisplay = (landUse) => {
        if (formTypeDisplay) {
            const formType = landUseFormTypes[landUse] || 'INDIVIDUAL FORM';
            formTypeDisplay.textContent = formType;
            console.log('Form type updated to:', formType);
        }
    };
    
    const landUseSelect = document.getElementById('currentLandUse');
    if (landUseSelect) {
        landUseSelect.addEventListener('change', function() {
            updateFormTypeDisplay(this.value);
        });
        if (landUseSelect.value) {
            updateFormTypeDisplay(landUseSelect.value);
        }
    }
    
    console.log('Land use form type display setup complete');
}

// ---------------------------------------------------------------------------
// Land file: the starting point. Picked with the global file number selector;
// what the records hold is filled in and locked, the clerk confirms the rest.
// Locked fields are disabled (not posted); the server re-reads them on save.
// ---------------------------------------------------------------------------
const LAND_FILE_URL = @json(route('recertification.land-file'));
let landFileLocked = [];
let landFileFilled = []; // filled from the records but left editable (the holder's name)
let applicantTypeAutoSet = false; // Applicant Type switched because the file title is an organisation
let landFileReady = false;

function setupLandFilePicker() {
    const btn = document.getElementById('landFilePickBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        if (!window.GlobalFileNoModal) {
            showToast('The file number selector did not load. Refresh the page.', 'error');
            return;
        }
        GlobalFileNoModal.open({
            allowedTabs: ['mls'],
            autoPopulateGenericFields: false,
            callback: function (data) {
                const no = (data && data.fileNumber) ? String(data.fileNumber).trim() : '';
                if (no) loadLandFile(no);
            }
        });
    });

    const kBtn = document.getElementById('kangisFilePickBtn');
    if (kBtn) {
        kBtn.addEventListener('click', function () {
            if (!window.GlobalFileNoModal) {
                showToast('The file number selector did not load. Refresh the page.', 'error');
                return;
            }
            GlobalFileNoModal.open({
                allowedTabs: ['kangis'],
                autoPopulateGenericFields: false,
                callback: function (data) {
                    const no = (data && data.fileNumber) ? String(data.fileNumber).trim() : '';
                    if (no) setKangisFile(no, false);
                }
            });
        });
    }
    const kClear = document.getElementById('kangisFileClearBtn');
    if (kClear) kClear.addEventListener('click', () => setKangisFile('', false));
}

function requireLandFile() {
    if (landFileReady) return true;
    if (currentStep !== 1) goToStepUnchecked(1);
    showFieldError('landFileNo');
    showToast('Select the land file first. A recertification needs an existing land file.', 'error');
    return false;
}

function goToStepUnchecked(stepNumber) {
    currentStep = stepNumber;
    updateStepDisplay();
}

function setLandFileStatus(message, tone) {
    const el = document.getElementById('landFileStatus');
    if (!el) return;
    const tones = {
        info: 'text-gray-600',
        error: 'text-red-700 bg-red-50 border border-red-200 rounded-md p-2',
        warn: 'text-amber-800 bg-amber-50 border border-amber-200 rounded-md p-2'
    };
    el.className = 'mt-3 text-sm ' + (tones[tone] || tones.info);
    el.textContent = message || '';
    el.classList.toggle('hidden', !message);
}

function clearLandFile() {
    // Blank the editable names the previous file filled in.
    landFileFilled.forEach(function (el) {
        el.value = '';
        el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    landFileFilled = [];
    if (applicantTypeAutoSet) {
        setApplicantType('Individual');
        applicantTypeAutoSet = false;
    }
    // Unlock and blank everything the previous file filled in.
    landFileLocked.forEach(function (el) {
        el.disabled = false;
        el.classList.remove('bg-gray-100', 'cursor-not-allowed');
        if (el.type === 'radio' || el.type === 'checkbox') {
            el.checked = false;
        } else {
            el.value = '';
            if (window.jQuery && jQuery(el).data('select2')) jQuery(el).trigger('change');
        }
        el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    landFileLocked = [];
    landFileReady = false;
    document.getElementById('landFileNo').value = '';
    document.getElementById('landFileDisplay').value = '';
    document.getElementById('newKangisFileNo').value = '';
    document.getElementById('landFileCard').classList.add('hidden');
    setKangisDisplay('-', null);
    setKangisFile('', false);
    document.getElementById('kangisFilePickBtn').disabled = true;
    document.getElementById('kangisFileHint').textContent =
        'Select the land file first. A KANGIS number already on record is filled in and locked.';
}

// KANGIS File No: value + display; `locked` when it came from the records.
function setKangisFile(fileNo, locked) {
    const value = (fileNo || '').trim();
    document.getElementById('kangisFileNo').value = value;
    document.getElementById('kangisFileDisplay').value = value;
    document.getElementById('kangisFilePickBtn').disabled = locked;
    document.getElementById('kangisFileClearBtn').classList.toggle('hidden', locked || !value);
    if (locked) {
        document.getElementById('kangisFileHint').textContent = 'KANGIS File No from the records (locked).';
    }
}

function setKangisDisplay(number, source) {
    const display = document.getElementById('kangisFileNoDisplay');
    const header = document.getElementById('new-kangis-file-number-display');
    const badge = document.getElementById('kangisFileNoBadge');
    if (display) display.textContent = number;
    if (header) header.textContent = source === 'new' ? number + ' (on submit)' : number;
    if (!badge) return;
    badge.className = 'ml-auto text-xs px-2 py-0.5 rounded-full';
    if (source === 'existing') {
        badge.textContent = 'Existing KANGIS file';
        badge.classList.add('bg-green-100', 'text-green-800');
    } else if (source === 'new') {
        badge.textContent = 'New KN number';
        badge.classList.add('bg-blue-100', 'text-blue-800');
    } else {
        badge.classList.add('hidden');
    }
}

function lockField(el) {
    el.disabled = true;
    if (el.type !== 'radio' && el.type !== 'checkbox') el.classList.add('bg-gray-100', 'cursor-not-allowed');
    landFileLocked.push(el);
}

function fillLandField(name, value) {
    const form = document.getElementById('recertification-form');
    if (!form || value === null || value === undefined || value === '') return;
    const els = name === 'district'
        ? [document.getElementById('district')].filter(Boolean)
        : Array.from(form.querySelectorAll(`[name="${name}"]`));
    if (!els.length) return;

    const first = els[0];
    if (first.type === 'radio') {
        const match = els.find(r => r.value.toLowerCase() === String(value).toLowerCase());
        if (!match) return; // not one of the listed choices: leave it for the clerk
        match.checked = true;
        match.dispatchEvent(new Event('change', { bubbles: true }));
        els.forEach(lockField);
        return;
    }

    // Occupation has one "other" choice with a specify box: an unlisted value goes there.
    if (name === 'occupation' && first.tagName === 'SELECT') {
        const wanted = String(value).trim().toLowerCase();
        const listed = Array.from(first.options).find(o =>
            o.value !== 'other' && (o.value.trim().toLowerCase() === wanted || o.text.trim().toLowerCase() === wanted));
        const other = document.getElementById('otherOccupation');
        if (listed || !other) {
            first.value = listed ? listed.value : first.value;
            if (!listed) return;
            first.dispatchEvent(new Event('change', { bubbles: true }));
            lockField(first);
            return;
        }
        first.value = 'other';
        if (typeof toggleOtherOccupation === 'function') toggleOtherOccupation();
        other.value = String(value).trim();
        lockField(first);
        lockField(other);
        return;
    }

    if (first.tagName === 'SELECT') {
        const wanted = String(value).trim().toLowerCase();
        let option = Array.from(first.options).find(o =>
            o.value.trim().toLowerCase() === wanted || o.text.trim().toLowerCase() === wanted);
        if (!option) {
            option = new Option(String(value).trim(), String(value).trim());
            first.add(option);
        }
        first.value = option.value;
        if (window.jQuery && jQuery(first).data('select2')) jQuery(first).trigger('change');
        first.dispatchEvent(new Event('change', { bubbles: true }));
        lockField(first);
        return;
    }

    first.value = value;
    first.dispatchEvent(new Event('input', { bubbles: true }));
    lockField(first);
}

// Fill a field from the records but leave it editable (a wrong split can be corrected).
function fillEditableField(id, value) {
    const el = document.getElementById(id);
    if (!el || el.disabled || value === null || value === undefined || value === '') return;
    if (el.tagName === 'SELECT') {
        const wanted = String(value).trim().toLowerCase();
        let option = Array.from(el.options).find(o =>
            o.value.trim().toLowerCase() === wanted || o.text.trim().toLowerCase() === wanted);
        if (!option) {
            // A title the list lacks (e.g. a rank): add it above "Other".
            option = new Option(String(value).trim(), String(value).trim());
            const other = Array.from(el.options).find(o => o.value === 'Other');
            el.add(option, other || null);
        }
        el.value = option.value;
    } else {
        el.value = String(value).trim();
    }
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    landFileFilled.push(el);
}

// The file title, split on the server, goes to the applicant (Section A) and the
// title holder (Section B). An organisation's title fills the organisation name.
function setApplicantType(value) {
    const type = document.getElementById('applicantType');
    if (!type || type.disabled || type.value === value) return;
    type.value = value;
    type.dispatchEvent(new Event('change', { bubbles: true }));
}

function fillHolderName(parts) {
    if (!parts || parts.joint) return 0; // several holders: the officer fills them in
    if (parts.organisation) {
        // A company or government body: switch the Applicant Type so its name shows (changeable).
        const type = document.getElementById('applicantType');
        if (type && type.value === 'Individual') {
            setApplicantType(parts.government ? 'Government Body' : 'Corporate');
            applicantTypeAutoSet = true;
        }
        fillEditableField('organisationName', parts.organisation);
        return 1;
    }
    const map = [
        ['title', 'title', 'titleHolderTitle'],
        ['surname', 'surname', 'titleHolderSurname'],
        ['firstName', 'firstName', 'titleHolderFirstName'],
        ['middleName', 'middleName', 'titleHolderMiddleName'],
    ];
    let filled = 0;
    map.forEach(([key, applicantId, holderId]) => {
        if (!parts[key]) return;
        fillEditableField(applicantId, parts[key]);
        fillEditableField(holderId, parts[key]);
        filled++;
    });
    return filled;
}

async function loadLandFile(fileNo) {
    clearLandFile();
    document.getElementById('landFileDisplay').value = fileNo;
    setLandFileStatus('Reading ' + fileNo + ' from the records...', 'info');

    let data;
    try {
        const res = await fetch(LAND_FILE_URL + '?file_no=' + encodeURIComponent(fileNo), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || 'Could not read the land file.');
    } catch (e) {
        document.getElementById('landFileDisplay').value = '';
        setLandFileStatus(e.message, 'error');
        return false;
    }

    if (data.decommissioned) {
        document.getElementById('landFileDisplay').value = '';
        setLandFileStatus(data.land_file + ' has been decommissioned and cannot be recertified.', 'error');
        return false;
    }
    if (data.existing_application) {
        document.getElementById('landFileDisplay').value = '';
        setLandFileStatus(data.land_file + ' already has recertification application '
            + data.existing_application.reference + '.', 'error');
        return false;
    }

    document.getElementById('landFileNo').value = data.land_file;
    document.getElementById('landFileDisplay').value = data.land_file;
    clearFieldError('landFileNo');

    // New KANGIS File No (KN series): the land file's own, or the next KN on submit.
    const newKangis = data.new_kangis || {};
    if (newKangis.source === 'existing') {
        document.getElementById('newKangisFileNo').value = newKangis.file_no;
        setKangisDisplay(newKangis.file_no, 'existing');
    } else {
        setKangisDisplay(newKangis.preview || 'KN (assigned on submit)', 'new');
    }

    // KANGIS File No: locked when the records hold one, otherwise the clerk may select it.
    const kangis = data.kangis || {};
    if (kangis.file_no) {
        setKangisFile(kangis.file_no, true);
    } else {
        document.getElementById('kangisFilePickBtn').disabled = false;
        document.getElementById('kangisFileHint').textContent =
            'No KANGIS File No on record for this land file. Select it if the land has one.';
    }

    const fields = data.fields || {};
    Object.keys(fields).forEach(name => fillLandField(name, fields[name]));
    const namesFilled = fillHolderName(data.holder_parts);

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v || '-'; };
    set('landFileHolder', data.holder_name);
    set('landFilePlot', fields.plotNumber);
    set('landFileDistrict', fields.district);
    set('landFileLga', fields.lga);
    set('landFileLocation', data.location);
    document.getElementById('landFileCard').classList.remove('hidden');

    const count = Object.keys(fields).length;
    const isOrganisation = !!(data.holder_parts && data.holder_parts.organisation);
    const nameNote = !namesFilled ? ''
        : isOrganisation ? ' The file title is an organisation: Applicant Type was set to '
            + document.getElementById('applicantType').value + ' and the name put in Organisation Name (both can be changed).'
        : ' The name was split from the file title and can be edited.';
    setLandFileStatus(count
        ? count + ' field(s) filled from the records and locked. Confirm them and complete the rest.' + nameNote
        : (namesFilled ? nameNote.trim() + ' Complete the rest of the form.'
            : 'The records hold no further details for this file. Complete the form.'), count || namesFilled ? 'info' : 'warn');
    const several = [];
    if (kangis.candidates && kangis.candidates.length > 1) {
        several.push('KANGIS files ' + kangis.candidates.join(', ') + ' (' + kangis.file_no + ' is used)');
    }
    if (newKangis.candidates && newKangis.candidates.length > 1) {
        several.push('New KANGIS files ' + newKangis.candidates.join(', ') + ' (' + newKangis.file_no + ' is used)');
    }
    if (several.length) {
        setLandFileStatus('This land file is related to several ' + several.join('; ') + '.', 'warn');
    }

    landFileReady = true;
    if (typeof lucide !== 'undefined') lucide.createIcons();
    // Picking the land file is enough to start a draft (skipped while resuming one).
    scheduleDraftSave(800);
    return true;
}

// ---------------------------------------------------------------------------
// Draft mode. Once a land file is chosen the form saves itself (debounced, and
// on Save Draft) to recertification_drafts; ?draft=<key> reopens one. Uploaded
// files cannot be kept, and fields locked from the records are re-read from
// the records on resume rather than taken from the draft.
// ---------------------------------------------------------------------------
const DRAFTS_URL = @json(route('recertification.drafts.index'));
const DRAFT_AUTOSAVE_MS = 4000;
const DRAFT_SKIP = ['_token', 'draft_key', 'landFileNo', 'kangisFileNo', 'newKangisFileNo', 'applicantType'];
let draftTimer = null;
let draftSaving = false;
let draftRestoring = false;
let draftClosed = false;

document.addEventListener('DOMContentLoaded', function () {
    // After the wizard's own set-up (the applicant type toggle runs at ~300 ms).
    setTimeout(setupDrafts, 600);
});

function draftKey() {
    const el = document.getElementById('draft_key');
    return el ? el.value : '';
}

function draftCsrf() {
    const t = document.querySelector('#recertification-form input[name="_token"]');
    return t ? t.value : '';
}

function setDraftStatus(text) {
    const el = document.getElementById('draft-status');
    if (el) el.textContent = text || '';
}

function setupDrafts() {
    const form = document.getElementById('recertification-form');
    const btn = document.getElementById('save-draft-btn');
    if (!form || !btn) return;

    btn.addEventListener('click', () => saveDraft(true));
    const draftsBtn = document.getElementById('my-drafts-btn');
    if (draftsBtn) draftsBtn.addEventListener('click', () => {
        const panel = document.getElementById('drafts-panel');
        const open = panel.classList.toggle('hidden') === false;
        draftsBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) loadDraftList();
    });
    form.addEventListener('input', scheduleDraftSave);
    form.addEventListener('change', scheduleDraftSave);
    // Leaving the page (closing the tab, Back to Applications) keeps the last changes.
    window.addEventListener('pagehide', saveDraftOnLeave);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') saveDraftOnLeave();
    });

    if (!landFileReady) setDraftStatus('Autosave starts once a land file is selected');

    const key = new URLSearchParams(window.location.search).get('draft');
    if (key) {
        resumeDraft(key).finally(loadDraftList);
    } else {
        loadDraftList();
    }
}

let draftDirty = false;

function scheduleDraftSave(delay) {
    if (draftRestoring || draftClosed || !landFileReady) return;
    clearTimeout(draftTimer);
    draftDirty = true;
    setDraftStatus('Unsaved changes');
    draftTimer = setTimeout(() => saveDraft(false), typeof delay === 'number' ? delay : DRAFT_AUTOSAVE_MS);
}

function draftStepChanged() {
    if (draftRestoring || draftClosed || !landFileReady) return;
    clearTimeout(draftTimer);
    setTimeout(() => saveDraft(false), 0);
}

function draftRequestBody() {
    const values = collectDraftValues();
    return JSON.stringify({
        draft_key: draftKey(),
        land_file_no: values.landFileNo || '',
        applicant_name: draftApplicantName(values),
        current_step: currentStep,
        payload: JSON.stringify({ values: values, step: currentStep })
    });
}

// Best effort: keepalive lets the request finish after the page has gone.
function saveDraftOnLeave() {
    if (!draftDirty || draftRestoring || draftClosed || !landFileReady) return;
    clearTimeout(draftTimer);
    draftDirty = false;
    try {
        fetch(DRAFTS_URL, {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': draftCsrf()
            },
            body: draftRequestBody()
        }).catch(() => {});
    } catch (e) { /* keepalive bodies are capped at 64 KB; the 4-second autosave has normally run already */ }
}

function collectDraftValues() {
    const form = document.getElementById('recertification-form');
    const values = {};
    Array.from(form.elements).forEach(el => {
        if (!el.name || el.type === 'file' || el.type === 'button' || el.name === '_token' || el.name === 'draft_key') return;
        // Locked fields came from the records; they are read again on resume.
        if (el.disabled) return;
        if (el.type === 'radio') {
            if (el.checked) values[el.name] = el.value;
            return;
        }
        if (el.type === 'checkbox') {
            if (el.name.endsWith('[]')) {
                values[el.name] = values[el.name] || [];
                if (el.checked) values[el.name].push(el.value);
            } else {
                values[el.name] = el.checked;
            }
            return;
        }
        if (el.tagName === 'SELECT' && el.multiple) {
            values[el.name] = Array.from(el.selectedOptions).map(o => o.value);
            return;
        }
        values[el.name] = el.value;
    });
    return values;
}

function draftApplicantName(values) {
    if (values.applicantType === 'Corporate' || values.applicantType === 'Government Body') {
        return values.organisationName || '';
    }
    if (values.applicantType === 'Multiple Owners') {
        const key = Object.keys(values).find(n => /^owners\[\d+\]\[surname\]$/.test(n) && values[n]);
        return key ? values[key] + ' and others' : '';
    }
    return [values.title, values.firstName, values.surname].filter(Boolean).join(' ');
}

async function saveDraft(manual) {
    if (draftClosed) return;
    if (!landFileReady) {
        if (manual) showToast('Select the land file first. A draft is kept per land file.', 'error');
        return;
    }
    if (draftSaving) { scheduleDraftSave(1500); return; }
    clearTimeout(draftTimer);
    draftSaving = true;
    const btn = document.getElementById('save-draft-btn');
    if (btn) btn.disabled = true;
    setDraftStatus('Saving draft...');
    draftDirty = false;

    try {
        const res = await fetch(DRAFTS_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': draftCsrf()
            },
            body: draftRequestBody()
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            if (res.status === 409) draftClosed = true;
            throw new Error(data.message || 'The draft could not be saved.');
        }
        if (data.csrf_token) {
            document.querySelectorAll('#recertification-form input[name="_token"]').forEach(t => { t.value = data.csrf_token; });
        }
        const url = new URL(window.location.href);
        if (url.searchParams.get('draft') !== draftKey()) {
            url.searchParams.set('draft', draftKey());
            window.history.replaceState(null, '', url.toString());
        }
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        setDraftStatus('Draft saved ' + time);
        if (manual) showToast('Draft saved. Resume it any time from the recertification form.', 'success');
    } catch (e) {
        draftDirty = true;
        setDraftStatus('Draft not saved');
        if (manual) showToast(e.message, 'error');
    } finally {
        draftSaving = false;
        if (btn) btn.disabled = false;
    }
}

async function loadDraftList() {
    const panel = document.getElementById('drafts-panel');
    const list = document.getElementById('drafts-list');
    if (!panel || !list) return;
    let drafts = [];
    try {
        const res = await fetch(DRAFTS_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json().catch(() => ({}));
        drafts = (data.drafts || []).filter(d => d.draft_key !== draftKey());
    } catch (e) {
        return;
    }
    // The table opens from the My Saved Drafts button.
    const countEl = document.getElementById('drafts-count');
    if (countEl) countEl.textContent = drafts.length ? '(' + drafts.length + ')' : '';
    const esc = v => String(v == null || v === '' ? '-' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    list.innerHTML = drafts.length ? '' : '<tr class="border-t border-gray-200"><td colspan="6" class="py-2 text-gray-500">No saved drafts.</td></tr>';
    list.innerHTML += drafts.map((d, i) => `
        <tr class="border-t border-gray-200">
            <td class="py-1.5 pr-3">${i + 1}</td>
            <td class="py-1.5 pr-3 font-mono">${esc(d.land_file_no)}</td>
            <td class="py-1.5 pr-3">${esc(d.applicant_name)}</td>
            <td class="py-1.5 pr-3">${esc(d.current_step)} of ${totalSteps}</td>
            <td class="py-1.5 pr-3" title="${esc(d.last_saved_at)}">${esc(d.last_saved_human)}</td>
            <td class="py-1.5 text-right whitespace-nowrap">
                <a href="?draft=${encodeURIComponent(d.draft_key)}" class="inline-flex items-center gap-1 rounded-md border border-blue-600 bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                    <i data-lucide="play" class="h-3.5 w-3.5"></i> Resume
                </a>
                <button type="button" class="draft-discard ml-1 inline-flex items-center gap-1 rounded-md border border-red-300 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50" data-key="${esc(d.draft_key)}" data-file="${esc(d.land_file_no)}">
                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Discard
                </button>
            </td>
        </tr>`).join('');

    list.querySelectorAll('.draft-discard').forEach(b => b.addEventListener('click', () => discardDraft(b.dataset.key, b.dataset.file)));
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

async function discardDraft(key, fileNo) {
    const question = 'Discard the draft for ' + fileNo + '?';
    let ok;
    if (window.Swal) {
        ok = (await Swal.fire({ title: 'Discard draft', text: question, icon: 'warning', showCancelButton: true, confirmButtonText: 'Discard', confirmButtonColor: '#dc2626' })).isConfirmed;
    } else {
        ok = window.confirm(question);
    }
    if (!ok) return;
    const res = await fetch(DRAFTS_URL + '/' + encodeURIComponent(key), {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': draftCsrf() }
    });
    showToast(res.ok ? 'Draft discarded.' : 'The draft could not be discarded.', res.ok ? 'success' : 'error');
    loadDraftList();
}

function restoreDraftField(form, name, value) {
    const els = Array.from(form.querySelectorAll(`[name="${CSS.escape(name)}"]`));
    if (!els.length || els.some(el => el.disabled)) return;
    const first = els[0];
    if (first.type === 'file') return;
    if (first.type === 'radio') {
        els.forEach(r => { r.checked = (r.value === value); });
        const checked = els.find(r => r.checked);
        if (checked) checked.dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }
    if (first.type === 'checkbox') {
        els.forEach(c => {
            c.checked = Array.isArray(value) ? value.includes(c.value) : Boolean(value);
            c.dispatchEvent(new Event('change', { bubbles: true }));
        });
        return;
    }
    if (first.tagName === 'SELECT') {
        const wanted = Array.isArray(value) ? value : [value];
        wanted.forEach(v => {
            if (v !== '' && v != null && !Array.from(first.options).some(o => o.value === v)) first.add(new Option(v, v));
        });
        if (first.multiple) {
            Array.from(first.options).forEach(o => { o.selected = wanted.includes(o.value); });
        } else {
            first.value = value == null ? '' : value;
        }
        if (window.jQuery && jQuery(first).data('select2')) jQuery(first).trigger('change');
        first.dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }
    first.value = value == null ? '' : value;
    first.dispatchEvent(new Event('input', { bubbles: true }));
    first.dispatchEvent(new Event('change', { bubbles: true }));
}

async function resumeDraft(key) {
    let draft;
    try {
        const res = await fetch(DRAFTS_URL + '/' + encodeURIComponent(key), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || 'Draft not found.');
        draft = data.draft;
    } catch (e) {
        showToast('That draft could not be opened: ' + e.message, 'error');
        const url = new URL(window.location.href);
        url.searchParams.delete('draft');
        window.history.replaceState(null, '', url.toString());
        return;
    }

    const payload = draft.payload || {};
    const values = payload.values || {};
    const form = document.getElementById('recertification-form');
    draftRestoring = true;
    document.getElementById('draft_key').value = draft.draft_key;

    try {
        // The land file first: it re-reads and locks what the records hold.
        if (!values.landFileNo || !(await loadLandFile(values.landFileNo))) {
            showToast('The draft\'s land file ' + (values.landFileNo || '') + ' can no longer be used. See the message on step 1.', 'error');
            return;
        }
        const kangisBtn = document.getElementById('kangisFilePickBtn');
        if (values.kangisFileNo && kangisBtn && !kangisBtn.disabled) setKangisFile(values.kangisFileNo, false);

        // Applicant type shows its section; owner blocks are rebuilt as 1..n.
        const typeSelect = document.getElementById('applicantType');
        if (typeSelect && values.applicantType) {
            typeSelect.value = values.applicantType;
            typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const ownerIndexes = [...new Set(Object.keys(values)
            .map(n => (n.match(/^owners\[(\d+)\]/) || [])[1])
            .filter(Boolean).map(Number))].sort((a, b) => a - b);
        if (values.applicantType === 'Multiple Owners') {
            while (ownersCount < ownerIndexes.length) addOwnerBlock();
            setOwnersRequired(true);
        }
        const ownerMap = {};
        ownerIndexes.forEach((old, i) => { ownerMap[old] = i + 1; });

        Object.keys(values).forEach(name => {
            if (DRAFT_SKIP.includes(name)) return;
            const target = name.replace(/^owners\[(\d+)\]/, (m, i) => 'owners[' + (ownerMap[i] || i) + ']');
            restoreDraftField(form, target, values[name]);
        });

        const step = Math.min(Math.max(parseInt(payload.step || draft.current_step || 1, 10), 1), totalSteps - 1);
        goToStepUnchecked(step);
        setDraftStatus('Draft resumed (saved ' + (draft.last_saved_human || '') + ')');
        showToast('Draft resumed. Attach any documents again before submitting.', 'info');
        const url = new URL(window.location.href);
        url.searchParams.set('draft', draft.draft_key);
        window.history.replaceState(null, '', url.toString());
    } finally {
        draftRestoring = false;
    }
}

console.log('Standalone form wizard initialized with development features');
</script>
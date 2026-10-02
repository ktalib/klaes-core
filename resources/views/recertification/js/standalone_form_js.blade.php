<script>
// Application state
let currentStep = 1;
const totalSteps = 8;

// Form data state
let formData = {};
let ownersCount = 0;

// Development flags
let skipValidation = true; // Default to true for development
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
        fetchNextFileNumber();
        fetchNewKangisFileNumber();
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
        // Check for development bypass
        const forceSkip = event && (event.ctrlKey || event.metaKey);
        
        if (skipValidation || forceSkip || validateCurrentStep()) {
            console.log('Moving to next step...');
            currentStep++;
            
            // If moving to step 7 (summary), populate the summary
            if (currentStep === 7) {
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
    if (currentStep === 6) {
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

        const result = await response.json();
        if (!response.ok || !result.success) {
            const msg = result?.message || 'Failed to submit application.';
            throw new Error(msg);
        }

        showToast(`Application submitted successfully. Ref: ${result.reference}`, 'success');
        // Redirect after short delay
        setTimeout(() => {
            window.location.href = '/recertification';
        }, 1500);
        
    } catch (error) {
        console.error('Error submitting application:', error);
        showToast('Failed to submit application. Please try again.', 'error');
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
    
    if (skipValidationCheckbox) skipValidationCheckbox.checked = true;
    if (autoFillCheckbox) autoFillCheckbox.checked = false;
    
    skipValidation = true;
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
    document.getElementById('summary-file-number').textContent = getValue('fileNumber') || '-';
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
    document.getElementById('summary-layout-district').textContent = formatText(getValue('layoutDistrict'));
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
    if (stepNumber >= 1 && stepNumber <= totalSteps) {
        currentStep = stepNumber;
        
        // If going to step 7 (summary), populate it
        if (currentStep === 7) {
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
    
    // Add event listeners to all land use radio buttons
    document.querySelectorAll('input[name="currentLandUse"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                updateFormTypeDisplay(this.value);
                showToast(`Form type updated to ${landUseFormTypes[this.value] || 'INDIVIDUAL FORM'}`, 'info');
            }
        });
    });
    
    // Check if there's already a selected land use and update display
    const selectedLandUse = document.querySelector('input[name="currentLandUse"]:checked');
    if (selectedLandUse) {
        updateFormTypeDisplay(selectedLandUse.value);
    }
    
    console.log('Land use form type display setup complete');
}

console.log('Standalone form wizard initialized with development features');
</script>
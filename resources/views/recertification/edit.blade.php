@extends('layouts.app')
@section('page-title')
    {{ __('Edit Recertification Application') }}
@endsection

@section('content')
<script>
// Tailwind config
tailwind.config = {
  theme: { 
    extend: {
      colors: {
        primary: '#3b82f6',
        'primary-foreground': '#ffffff',
        muted: '#f3f4f6',
        'muted-foreground': '#6b7280',
        border: '#e5e7eb',
        destructive: '#ef4444',
        'destructive-foreground': '#ffffff',
        secondary: '#f1f5f9',
        'secondary-foreground': '#0f172a',
      }
    }
  }
}
</script>

@include('recertification.css.form_css')

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <div class="container mx-auto py-6 space-y-6 max-w-7xl px-4 sm:px-6 lg:px-8">
            
            <!-- Header with Back Button -->
            <div class="flex items-center gap-4 mb-6">
                <a href="{{ url('/recertification') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    Back to Applications
                </a>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-900">Edit Recertification Application</h1>
                    <p class="text-gray-600">Update the application details below</p>
                </div>
                <!-- Application Info Display -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                    <div class="text-xs font-medium text-blue-600 uppercase tracking-wide mb-1">File Number</div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
                        <span class="text-lg font-bold text-blue-900 font-mono">{{ $application->file_number ?? 'N/A' }}</span>
                    </div>
                    <div class="text-xs text-blue-500 mt-1">{{ $application->applicant_type ?? 'N/A' }}</div>
                </div>
            </div>

            <!-- Application Form -->
            <div class="bg-white rounded-lg shadow-xl border border-gray-200">
                <!-- Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="text-center">
                        <div class="space-y-1">
                            <div class="font-bold text-lg">KANO STATE GEOGRAPHIC INFORMATION SYSTEMS (KANGIS)</div>
                            <div class="text-sm text-gray-600">MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE</div>
                            <div class="text-sm font-semibold">APPLICATION FOR RE-CERTIFICATION OR RE-ISSUANCE OF C-of-O</div>
                            <div class="text-xs text-gray-500">INDIVIDUAL FORM AR01-01</div>
                        </div>
                    </div>
                </div>
                
                <!-- Step Indicator -->
                <div class="p-6 pb-0">
                    <div class="step-indicator">
                        <div id="step-1" class="step-circle active">1</div>
                        <div id="line-1" class="step-line inactive"></div>
                        <div id="step-2" class="step-circle inactive">2</div>
                        <div id="line-2" class="step-line inactive"></div>
                        <div id="step-3" class="step-circle inactive">3</div>
                        <div id="line-3" class="step-line inactive"></div>
                        <div id="step-4" class="step-circle inactive">4</div>
                        <div id="line-4" class="step-line inactive"></div>
                        <div id="step-5" class="step-circle inactive">5</div>
                        <div id="line-5" class="step-line inactive"></div>
                        <div id="step-6" class="step-circle inactive">6</div>
                    </div>
                </div>
                 
                <div class="p-6">
                    <form id="recertification-form" method="POST" action="{{ route('recertification.application.store') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="application_id" value="{{ $application->id }}">
                        
                        <!-- Include Step Partials -->
                        @include('recertification.steps.step1_personal_details')
                        @include('recertification.steps.step2_contact_details')
                        @include('recertification.steps.step3_title_holder')
                        @include('recertification.steps.step4_mortgage_encumbrance')
                        @include('recertification.steps.step5_plot_details')
                        @include('recertification.steps.step6_payment_terms')
                        
                    </form>

                    <!-- Form Navigation -->
                    <div class="flex justify-between pt-4 border-t">
                        <button
                            type="button"
                            id="prev-btn"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        
                        <button
                            type="button"
                            id="next-btn"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="next-text">Next</span>
                            <div class="loading-spinner hidden"></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
    <!-- Toast messages will be inserted here -->
</div>

<script>
// Edit form specific JavaScript
let currentStep = 1;
const totalSteps = 6;
let formData = {};
let ownersCount = 0;

// Application data from server
const applicationData = @json($application);
const ownersData = @json($owners ?? []);

document.addEventListener('DOMContentLoaded', function() {
    console.log('Edit form loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Set up event listeners
    setupEventListeners();
    
    // Pre-fill form with existing data
    setTimeout(() => {
        prefillForm();
        updateStepDisplay();
    }, 100);
});

function setupEventListeners() {
    // Navigation buttons
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', previousStep);
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', nextStep);
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
    }
    
    // Conditional field displays
    setupConditionalFields();
    setupApplicantTypeToggle();
    setupMultipleOwnersControls();
}

function prefillForm() {
    console.log('Pre-filling form with data:', applicationData);
    
    // Fill basic fields
    Object.keys(applicationData).forEach(key => {
        const element = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
        if (element && applicationData[key] !== null) {
            if (element.type === 'checkbox') {
                element.checked = Boolean(applicationData[key]);
            } else if (element.type === 'radio') {
                const radioGroup = document.querySelectorAll(`[name="${key}"]`);
                radioGroup.forEach(radio => {
                    if (radio.value === String(applicationData[key])) {
                        radio.checked = true;
                    }
                });
            } else {
                element.value = applicationData[key];
            }
            formData[key] = applicationData[key];
        }
    });
    
    // Handle special mappings
    const mappings = {
        'surname': 'surname',
        'first_name': 'firstName',
        'middle_name': 'middleName',
        'date_of_birth': 'dateOfBirth',
        'state_of_origin': 'stateOfOrigin',
        'lga_of_origin': 'lgaOfOrigin',
        'marital_status': 'maritalStatus',
        'maiden_name': 'maidenName',
        'phone_no': 'phoneNo',
        'whatsapp_phone_no': 'whatsappPhoneNo',
        'alternate_phone_no': 'alternatePhoneNo',
        'address_line1': 'addressLine1',
        'address_line2': 'addressLine2',
        'city_town': 'cityTown',
        'state_name': 'state',
        'email_address': 'emailAddress',
        'organisation_name': 'organisationName',
        'cac_registration_no': 'cacRegistrationNo',
        'type_of_organisation': 'typeOfOrganisation',
        'type_of_business': 'typeOfBusiness',
        'title_holder_surname': 'titleHolderSurname',
        'title_holder_first_name': 'titleHolderFirstName',
        'title_holder_middle_name': 'titleHolderMiddleName',
        'title_holder_title': 'titleHolderTitle',
        'cofo_number': 'cofoNumber',
        'reg_no': 'registrationNo',
        'reg_volume': 'registrationVolume',
        'reg_page': 'registrationPage',
        'reg_number': 'registrationNumber',
        'is_original_owner': 'isOriginalOwner',
        'instrument_type': 'instrumentType',
        'acquired_title_holder_name': 'titleHolderName',
        'commencement_date': 'commencementDate',
        'grant_term': 'grantTerm',
        'is_encumbered': 'isEncumbered',
        'encumbrance_reason': 'encumbranceReason',
        'has_mortgage': 'hasMortgage',
        'mortgagee_name': 'mortgageeName',
        'mortgage_registration_no': 'mortgageRegistrationNo',
        'mortgage_volume': 'mortgageVolume',
        'mortgage_page': 'mortgagePage',
        'mortgage_number': 'mortgageNumber',
        'mortgage_released': 'mortgageReleased',
        'plot_number': 'plotNumber',
        'file_number': 'fileNumber',
        'plot_size': 'plotSize',
        'layout_district': 'layoutDistrict',
        'lga_name': 'lga',
        'current_land_use': 'currentLandUse',
        'plot_status': 'plotStatus',
        'mode_of_allocation': 'modeOfAllocation',
        'start_date': 'startDate',
        'expiry_date': 'expiryDate',
        'plot_description': 'plotDescription',
        'application_type': 'applicationType',
        'application_reason': 'applicationReason',
        'other_reason': 'otherReason',
        'payment_method': 'paymentMethod',
        'receipt_no': 'receiptNo',
        'bank_name': 'bankName',
        'payment_amount': 'paymentAmount',
        'payment_date': 'paymentDate',
        'agree_terms': 'agreeTerms',
        'confirm_accuracy': 'confirmAccuracy'
    };
    
    Object.keys(mappings).forEach(dbField => {
        const formField = mappings[dbField];
        const element = document.getElementById(formField) || document.querySelector(`[name="${formField}"]`);
        
        if (element && applicationData[dbField] !== null && applicationData[dbField] !== undefined) {
            if (element.type === 'checkbox') {
                element.checked = Boolean(applicationData[dbField]);
            } else if (element.type === 'radio') {
                const radioGroup = document.querySelectorAll(`[name="${formField}"]`);
                radioGroup.forEach(radio => {
                    let value = String(applicationData[dbField]);
                    // Handle boolean to yes/no conversion
                    if (applicationData[dbField] === 1 || applicationData[dbField] === true) {
                        value = 'yes';
                    } else if (applicationData[dbField] === 0 || applicationData[dbField] === false) {
                        value = 'no';
                    }
                    if (radio.value === value) {
                        radio.checked = true;
                    }
                });
            } else {
                element.value = applicationData[dbField];
            }
            formData[formField] = applicationData[dbField];
        }
    });
    
    // Handle multiple owners
    if (applicationData.applicant_type === 'Multiple Owners' && ownersData.length > 0) {
        ownersData.forEach((owner, index) => {
            if (index === 0) {
                addOwnerBlock(); // Add first owner block
            } else {
                addOwnerBlock(); // Add additional owner blocks
            }
            
            // Fill owner data
            const ownerMappings = {
                'surname': 'surname',
                'first_name': 'firstName',
                'middle_name': 'middleName',
                'title': 'title',
                'occupation': 'occupation',
                'date_of_birth': 'dateOfBirth',
                'nationality': 'nationality',
                'state_of_origin': 'stateOfOrigin',
                'lga_of_origin': 'lgaOfOrigin',
                'nin': 'nin',
                'gender': 'gender',
                'marital_status': 'maritalStatus',
                'maiden_name': 'maidenName'
            };
            
            Object.keys(ownerMappings).forEach(dbField => {
                const formField = ownerMappings[dbField];
                const element = document.querySelector(`[name="owners[${index + 1}][${formField}]"]`);
                
                if (element && owner[dbField] !== null && owner[dbField] !== undefined) {
                    if (element.type === 'radio') {
                        const radioGroup = document.querySelectorAll(`[name="owners[${index + 1}][${formField}]"]`);
                        radioGroup.forEach(radio => {
                            if (radio.value === String(owner[dbField])) {
                                radio.checked = true;
                            }
                        });
                    } else {
                        element.value = owner[dbField];
                    }
                }
            });
        });
    }
    
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
    
    // Trigger applicant type change
    const applicantTypeSelect = document.getElementById('applicantType');
    if (applicantTypeSelect) {
        applicantTypeSelect.dispatchEvent(new Event('change'));
    }
}

// Include the rest of the form functions from standalone form
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

function setupApplicantTypeToggle() {
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
        // Default hide both
        if (individual) individual.classList.add('hidden');
        if (corporate) corporate.classList.add('hidden');
        if (multipleOwners) multipleOwners.classList.add('hidden');

        // Default: individual required
        setRequired([
            '#surname', '#firstName', '#occupation', '#dateOfBirth', '#nationality', '#stateOfOrigin',
            'input[name="gender"]', 'input[name="maritalStatus"]'
        ], false);
        setRequired(['#organisationName', '#cacRegistrationNo', '#typeOfOrganisation', '#typeOfBusiness'], false);

        if (value === 'Corporate') {
            if (corporate) corporate.classList.remove('hidden');
            setRequired(['#organisationName', '#cacRegistrationNo', '#typeOfOrganisation', '#typeOfBusiness'], true);
        } else if (value === 'Multiple Owners') {
            if (multipleOwners) multipleOwners.classList.remove('hidden');
        } else if (value === 'Individual' || value === 'Government Body') {
            if (individual) individual.classList.remove('hidden');
            setRequired([
                '#surname', '#firstName', '#occupation', '#dateOfBirth', '#nationality', '#stateOfOrigin',
                'input[name="gender"]', 'input[name="maritalStatus"]'
            ], true);
        }
    };

    if (typeSelect) {
        updateView(typeSelect.value);
        typeSelect.addEventListener('change', (e) => {
            formData['applicantType'] = e.target.value;
            updateView(e.target.value);
        });
    }
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
        <div class="grid grid-cols-3 gap-4">
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
        <!-- Add more owner fields as needed -->
    `;

    list.appendChild(wrapper);

    // Remove owner handler
    wrapper.querySelector('.remove-owner').addEventListener('click', () => {
        wrapper.remove();
    });
}

function handleFormInput(event) {
    const { name, value } = event.target;
    if (name) {
        formData[name] = value;
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
    }
}

function updateStepDisplay() {
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
            nextText.textContent = 'Update Application';
        } else {
            nextText.textContent = 'Next';
        }
    }
}

function previousStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepDisplay();
    }
}

function nextStep() {
    if (currentStep < totalSteps) {
        currentStep++;
        updateStepDisplay();
    } else {
        // Submit form
        submitForm();
    }
}

function goToStep(stepNumber) {
    if (stepNumber >= 1 && stepNumber <= totalSteps) {
        currentStep = stepNumber;
        updateStepDisplay();
    }
}

async function submitForm() {
    const nextBtn = document.getElementById('next-btn');
    const nextText = nextBtn?.querySelector('.next-text');
    const loadingSpinner = nextBtn?.querySelector('.loading-spinner');
    
    // Show loading state
    if (nextBtn) nextBtn.disabled = true;
    if (nextText) nextText.textContent = 'Updating...';
    if (loadingSpinner) loadingSpinner.classList.remove('hidden');
    
    try {
        // Collect all form data with files
        const form = document.getElementById('recertification-form');
        const formBody = new FormData(form);

        // Post to backend
        const response = await fetch(`/recertification/${applicationData.id}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formBody
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            const msg = result?.message || 'Failed to update application.';
            throw new Error(msg);
        }

        showToast('Application updated successfully', 'success');
        // Redirect after short delay
        setTimeout(() => {
            window.location.href = '/recertification';
        }, 1500);
        
    } catch (error) {
        console.error('Error updating application:', error);
        showToast('Failed to update application. Please try again.', 'error');
    } finally {
        // Reset loading state
        if (nextBtn) nextBtn.disabled = false;
        if (nextText) nextText.textContent = 'Update Application';
        if (loadingSpinner) loadingSpinner.classList.add('hidden');
    }
}

// Toast notification function
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

// Make functions available globally
window.removeToast = removeToast;
</script>

@endsection
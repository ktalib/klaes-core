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

    // Initialize the form submission handler
    init: function() {
        console.log('🔧 Initializing form submission handler...');
        
        this.form = document.getElementById('primaryApplicationForm');
        if (!this.form) {
            console.error('❌ Primary form not found!');
            return;
        }

        // Get the submit URL from global variable (ignore form action since it's set to javascript:void(0))
        this.submitUrl = window.FORM_SUBMIT_URL || '/primaryform/store';
        console.log('🎯 Submit URL:', this.submitUrl);
        
        // Validate that we have a proper URL
        if (!this.submitUrl || this.submitUrl.includes('javascript:')) {
            console.error('❌ Invalid submit URL:', this.submitUrl);
            this.submitUrl = '/primaryform/store'; // Fallback
            console.log('🔄 Using fallback URL:', this.submitUrl);
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
    submitForm: function() {
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

        // Collect form data
        const formData = this.collectFormData();
        
        // Submit via AJAX
        this.performAjaxSubmission(formData);
    },

    // Validate form data (DISABLED FOR TESTING)
    validateForm: function() {
        console.log('✅ Form validation SKIPPED for testing...');
        
        // TODO: Re-enable validation after testing
        // Basic validation checks - COMMENTED OUT FOR TESTING
        /*
        const requiredFields = [
            'ownerTitle', 'ownerFirstName', 'ownerSurname',
            'ownerState', 'ownerLga'
        ];

        for (let fieldName of requiredFields) {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (!field || !field.value.trim()) {
                this.showError(`Required field missing: ${fieldName.replace('owner', '').replace(/([A-Z])/g, ' $1').trim()}`);
                return false;
            }
        }

        // Validate buyers data
        const buyerRows = document.querySelectorAll('.buyer-row');
        if (buyerRows.length === 0) {
            this.showError('At least one buyer is required');
            return false;
        }
        */

        console.log('✅ Form validation BYPASSED - ready for testing');
        return true; // Always return true for testing
    },

    // Collect all form data including files
    collectFormData: function() {
        console.log('📋 Collecting form data...');
        
        const formData = new FormData();
        
        // Add CSRF token
        const csrfToken = document.querySelector('input[name="_token"]');
        if (csrfToken) {
            formData.append('_token', csrfToken.value);
        }

        // Collect all form inputs (excluding CSV import files)
        const inputs = this.form.querySelectorAll('input, select, textarea');
        console.log(`📋 Found ${inputs.length} form inputs to process`);
        
        inputs.forEach(input => {
            // Skip CSV file inputs - they're only for local processing
            if (input.name === 'csv_file' || input.id === 'csvFileInput' || 
                (input.type === 'file' && (input.name?.includes('csv') || input.id?.includes('csv')))) {
                console.log('🚫 Skipping CSV file input:', input.name || input.id, input.type);
                return;
            }
            
            if (input.type === 'file') {
                // Handle file inputs (document uploads only)
                if (input.files && input.files.length > 0) {
                    console.log(`📎 Adding file input: ${input.name} (${input.files.length} files)`);
                    
                    // For single file inputs, don't use array notation
                    if (input.files.length === 1) {
                        formData.append(input.name, input.files[0]);
                    } else {
                        // For multiple files, use array notation
                        Array.from(input.files).forEach((file, index) => {
                            formData.append(`${input.name}[${index}]`, file);
                        });
                    }
                }
            } else if (input.type === 'checkbox' || input.type === 'radio') {
                // Handle checkboxes and radio buttons
                if (input.checked) {
                    console.log(`☑️ Adding checked input: ${input.name} = ${input.value}`);
                    formData.append(input.name, input.value);
                }
            } else if (input.name && input.value !== '') {
                // Handle regular inputs
                console.log(`📝 Adding input: ${input.name} = ${input.value?.substring(0, 50)}${input.value?.length > 50 ? '...' : ''}`);
                formData.append(input.name, input.value);
            }
        });

        // Add calculated fields from summary
        const summaryData = this.collectSummaryData();
        Object.keys(summaryData).forEach(key => {
            formData.append(key, summaryData[key]);
        });

        console.log('📦 Form data collected');
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
    performAjaxSubmission: function(formData) {
        console.log('📡 Sending AJAX request...');

        fetch(this.submitUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            console.log('📡 Response received:', response.status);
            return response.json().then(data => ({
                status: response.status,
                ok: response.ok,
                data: data
            }));
        })
        .then(result => {
            this.handleSubmissionResponse(result);
        })
        .catch(error => {
            console.error('❌ Submission error:', error);
            this.handleSubmissionError(error);
        })
        .finally(() => {
            this.hideLoadingState();
            this.isSubmitting = false;
        });
    },

    // Handle successful/failed submission response
    handleSubmissionResponse: function(result) {
        console.log('📋 Processing response:', result);

        if (result.ok && result.status === 200) {
            // Success
            console.log('✅ Form submitted successfully');
            this.showSuccessMessage(result.data);
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
        Swal.fire({
            title: 'Success!',
            text: 'Primary application submitted successfully.',
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#10B981'
        }).then((result) => {
            if (result.isConfirmed) {
                // Handle redirect or form reset
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    // Default redirect to applications list
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
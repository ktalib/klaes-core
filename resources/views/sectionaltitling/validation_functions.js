// Complete Form Validation Functions for Sub Application

// Step 1 Validation - Basic Information
function validateStep1() {
    const errors = [];

    // Check if applicant type is selected
    const applicantType = document.querySelector('input[name="applicantType"]:checked');
    if (!applicantType) {
        errors.push('Please select an applicant type');
    } else {
        const type = applicantType.value;

        // Validate based on applicant type
        if (type === 'individual') {
            // Individual validation
            const title = document.getElementById('applicantTitle')?.value;
            const firstName = document.getElementById('applicantName')?.value;
            const surname = document.getElementById('applicantSurname')?.value;

            if (!title) errors.push('Please select a title');
            if (!firstName || firstName.trim() === '') errors.push('Please enter first name');
            if (!surname || surname.trim() === '') errors.push('Please enter surname');

            // Validate passport photo
            const passport = document.getElementById('photoUpload')?.files[0];
            if (!passport) errors.push('Please upload a passport photo');

        } else if (type === 'corporate') {
            // Corporate validation
            const corporateName = document.getElementById('corporateName')?.value;
            const rcNumber = document.getElementById('rcNumber')?.value;
            const rcDocument = document.getElementById('subCorporateDocumentUpload')?.files[0];

            if (!corporateName || corporateName.trim() === '') errors.push('Please enter corporate body name');
            if (!rcNumber || rcNumber.trim() === '') errors.push('Please enter RC number');
            if (!rcDocument) errors.push('Please upload RC document');

        } else if (type === 'multiple') {
            // Multiple owners validation
            const ownerRows = document.querySelectorAll('#ownersContainer > div');
            if (ownerRows.length === 0) {
                errors.push('Please add at least one owner');
            } else {
                ownerRows.forEach((row, index) => {
                    const nameInput = row.querySelector('input[name="multiple_owners_names[]"]');
                    const addressInput = row.querySelector('textarea[name="multiple_owners_address[]"]');
                    const identificationInput = row.querySelector('input[name="multiple_owners_identification_image[]"]');

                    if (!nameInput?.value || nameInput.value.trim() === '') {
                        errors.push(`Please enter name for owner ${index + 1}`);
                    }
                    if (!addressInput?.value || addressInput.value.trim() === '') {
                        errors.push(`Please enter address for owner ${index + 1}`);
                    }
                    if (!identificationInput?.files[0]) {
                        errors.push(`Please upload identification for owner ${index + 1}`);
                    }
                });
            }
        }
    }

    // Validate address fields (only for individual and corporate)
    if (applicantType && applicantType.value !== 'multiple') {
        const state = document.getElementById('ownerState')?.value;
        const lga = document.getElementById('ownerLga')?.value;
        const district = document.getElementById('ownerDistrict')?.value;

        if (!state) errors.push('Please select a state');
        if (!lga) errors.push('Please select an LGA');
        if (!district || district.trim() === '') errors.push('Please enter district');

        // Validate phone number
        const phoneInputs = document.querySelectorAll('input[name="phone_number[]"]');
        let hasValidPhone = false;
        phoneInputs.forEach(input => {
            if (input.value && input.value.trim() !== '') {
                hasValidPhone = true;
                // Basic phone validation
                const phoneRegex = /^[\d\s\-\+\(\)]{10,}$/;
                if (!phoneRegex.test(input.value.replace(/\s/g, ''))) {
                    errors.push('Please enter a valid phone number');
                }
            }
        });
        if (!hasValidPhone) errors.push('Please enter at least one phone number');

        // Validate email
        const email = document.querySelector('input[name="owner_email"]')?.value;
        if (email && email.trim() !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errors.push('Please enter a valid email address');
            }
        }
    }

    // Validate unit details
    const blockNumber = document.querySelector('input[name="block_number"]')?.value;
    const floorNumber = document.querySelector('input[name="floor_number"]')?.value;
    const unitNumber = document.querySelector('input[name="unit_number"]')?.value;

    if (!blockNumber || blockNumber.trim() === '') errors.push('Please enter block number');
    if (!floorNumber || floorNumber.trim() === '') errors.push('Please enter floor number');
    if (!unitNumber || unitNumber.trim() === '') errors.push('Please enter unit number');

    // Validate scheme number
    const schemeNo = document.getElementById('schemeName')?.value;
    if (!schemeNo || schemeNo.trim() === '') errors.push('Please enter scheme number');

    // Validate payment information
    const receiptNumber = document.querySelector('input[name="receipt_number"]')?.value;
    const paymentDate = document.querySelector('input[name="payment_date"]')?.value;

    if (!receiptNumber || receiptNumber.trim() === '') errors.push('Please enter receipt number');
    if (!paymentDate) errors.push('Please select payment date');

    return errors;
}

// Step 2 Validation - Shared Areas
function validateStep2() {
    const errors = [];

    // Check if at least one shared area is selected
    const sharedAreas = document.querySelectorAll('input[name="shared_areas[]"]:checked');
    if (sharedAreas.length === 0) {
        errors.push('Please select at least one shared area');
    }

    // If "Other" is selected, check if details are provided
    const otherCheckbox = document.getElementById('other_areas');
    if (otherCheckbox && otherCheckbox.checked) {
        const otherDetails = document.getElementById('other_areas_detail')?.value;
        if (!otherDetails || otherDetails.trim() === '') {
            errors.push('Please specify other shared areas');
        }
    }

    return errors;
}

// Step 3 Validation - Documents
function validateStep3() {
    const errors = [];

    // Check required documents
    const requiredDocs = [
        { name: 'application_letter', label: 'Application Letter' },
        { name: 'building_plan', label: 'Building Plan' },
        { name: 'architectural_design', label: 'Architectural Design' },
        { name: 'ownership_document', label: 'Ownership Document' }
    ];

    requiredDocs.forEach(doc => {
        const fileInput = document.getElementById(doc.name);
        if (!fileInput || !fileInput.files[0]) {
            errors.push(`Please upload ${doc.label}`);
        } else {
            // Validate file size (5MB limit)
            const file = fileInput.files[0];
            if (file.size > 5 * 1024 * 1024) {
                errors.push(`${doc.label} file size must be less than 5MB`);
            }

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                errors.push(`${doc.label} must be a JPG, PNG, or PDF file`);
            }
        }
    });

    return errors;
}

// Step 4 Validation - Summary (Optional - usually just review)
function validateStep4() {
    const errors = [];
    
    // Step 4 is typically just a summary/review step
    // Add any final validation checks here if needed
    
    return errors;
}

// Show validation errors using SweetAlert
function showValidationErrors(errors) {
    if (errors.length > 0) {
        // Create formatted error list for SweetAlert
        const errorList = errors.map(error => `• ${error}`).join('<br>');

        // Show SweetAlert with validation errors
        Swal.fire({
            icon: 'error',
            title: 'Please correct the following errors:',
            html: `<div style="text-align: left; font-size: 14px; line-height: 1.6;">${errorList}</div>`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc2626',
            customClass: {
                popup: 'swal-validation-popup',
                title: 'swal-validation-title',
                htmlContainer: 'swal-validation-content'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            }
        });

        return false;
    }

    return true;
}

// Utility function to validate email format
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Utility function to validate phone number format
function isValidPhone(phone) {
    const phoneRegex = /^[\d\s\-\+\(\)]{10,}$/;
    return phoneRegex.test(phone.replace(/\s/g, ''));
}

// Utility function to validate file type
function isValidFileType(file, allowedTypes) {
    return allowedTypes.includes(file.type);
}

// Utility function to validate file size
function isValidFileSize(file, maxSizeInMB) {
    const maxSizeInBytes = maxSizeInMB * 1024 * 1024;
    return file.size <= maxSizeInBytes;
}

// Make validation functions globally available
window.validateStep1 = validateStep1;
window.validateStep2 = validateStep2;
window.validateStep3 = validateStep3;
window.validateStep4 = validateStep4;
window.showValidationErrors = showValidationErrors;
window.isValidEmail = isValidEmail;
window.isValidPhone = isValidPhone;
window.isValidFileType = isValidFileType;
window.isValidFileSize = isValidFileSize;
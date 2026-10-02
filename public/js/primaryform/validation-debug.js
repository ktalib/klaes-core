/**
 * Primary Form Validation Fix
 * This script adds debugging and fixes common validation issues
 */

console.log('🔧 Loading Primary Form Validation Fix...');

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('📝 Primary Form Validation Fix - DOM Ready');
    
    // Add debugging to form submission
    const form = document.getElementById('primaryApplicationForm');
    if (form) {
        console.log('✅ Found primary application form');
        
        // Add debug button to the form
        addDebugButton();
        
        // Override the existing validation function with debugging
        if (window.FormSubmission && window.FormSubmission.validateForm) {
            const originalValidate = window.FormSubmission.validateForm;
            window.FormSubmission.validateForm = function() {
                console.log('🔍 Running enhanced validation with debugging...');
                
                // Add field state debugging
                debugFieldStates();
                
                // Run original validation
                return originalValidate.call(this);
            };
        }
    } else {
        console.log('❌ Primary application form not found');
    }
});

function addDebugButton() {
    // Create debug button
    const debugBtn = document.createElement('button');
    debugBtn.type = 'button';
    debugBtn.className = 'px-4 py-2 bg-blue-600 text-white rounded-md';
    debugBtn.textContent = '🔍 Debug Form';
    debugBtn.onclick = function() {
        debugFormState();
    };
    
    // Add to the first step navigation
    const firstStepNav = document.querySelector('.flex.justify-between');
    if (firstStepNav) {
        firstStepNav.appendChild(debugBtn);
        console.log('✅ Added debug button to form');
    }
}

function debugFieldStates() {
    console.log('📊 === Field State Debug ===');
    
    const form = document.getElementById('primaryApplicationForm');
    if (!form) return;
    
    const fieldsToCheck = [
        'applicantType',
        'scheme_no', 
        'property_street_name',
        'property_lga',
        'property_state'
    ];
    
    fieldsToCheck.forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (field) {
            console.log(`🔎 ${fieldName}:`, {
                element: field.tagName,
                type: field.type,
                value: `"${field.value}"`,
                disabled: field.disabled,
                visible: field.offsetParent !== null,
                id: field.id,
                classes: field.className
            });
        } else {
            console.log(`❌ ${fieldName}: NOT FOUND`);
        }
    });
    
    console.log('📊 === End Field Debug ===');
}

function debugFormState() {
    console.log('🚀 === Full Form Debug ===');
    
    const form = document.getElementById('primaryApplicationForm');
    if (!form) {
        alert('❌ Form not found!');
        return;
    }
    
    // Check form data
    const formData = new FormData(form);
    console.log('📦 FormData entries:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    // Check specific fields
    debugFieldStates();
    
    // Check if auto-fill has run
    const fileNoField = form.querySelector('[name="fileno"]');
    const npFileNoField = form.querySelector('[name="np_fileno"]');
    
    console.log('📂 File number fields:');
    console.log('  fileno:', fileNoField ? fileNoField.value : 'NOT FOUND');
    console.log('  np_fileno:', npFileNoField ? npFileNoField.value : 'NOT FOUND');
    
    // Simulate validation
    console.log('🧪 Simulating validation...');
    const errors = [];
    
    const applicantType = form.querySelector('[name="applicantType"]')?.value || 
                         form.querySelector('[name="applicant_type"]')?.value;
    const schemeNo = form.querySelector('[name="scheme_no"]')?.value;
    const propertyStreet = form.querySelector('[name="property_street_name"]')?.value;
    const propertyLga = form.querySelector('[name="property_lga"]')?.value;
    const propertyState = form.querySelector('[name="property_state"]')?.value;
    
    if (!applicantType || !applicantType.trim()) errors.push('Applicant type required');
    if (!schemeNo || !schemeNo.trim()) errors.push('Scheme number required');
    if (!propertyStreet || !propertyStreet.trim()) errors.push('Property street required');
    if (!propertyLga || !propertyLga.trim()) errors.push('Property LGA required');
    if (!propertyState || !propertyState.trim()) errors.push('Property state required');
    
    if (errors.length > 0) {
        console.log('❌ Validation errors:', errors);
        alert('Validation Errors:\n' + errors.join('\n'));
    } else {
        console.log('✅ All validation checks passed!');
        alert('✅ All validation checks passed!');
    }
    
    console.log('🚀 === End Full Form Debug ===');
}

// Make functions globally available for testing
window.debugFormState = debugFormState;
window.debugFieldStates = debugFieldStates;

console.log('✅ Primary Form Validation Fix loaded successfully');
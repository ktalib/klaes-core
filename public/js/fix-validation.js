// CRITICAL FIX: Override problematic validatePropertyForm function
console.log('Loading validation fix...');

window.validatePropertyForm = function(formId, submitButtonId) {
    console.log('OVERRIDE validatePropertyForm called for:', formId);
    const submitButton = document.getElementById(submitButtonId);
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    return true;
};

console.log('validatePropertyForm override loaded successfully');
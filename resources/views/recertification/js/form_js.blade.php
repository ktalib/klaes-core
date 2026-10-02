
<script>
// Application state
let currentStep = 1;
const totalSteps = 6;

// Form data state
let formData = {};

// Track if event listeners have been set up
let eventListenersSetup = false;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM Content Loaded');
  
  // Initialize Lucide icons
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }
  
  // Set up event listeners
  setupEventListeners();
  
  // Set current date (only if elements exist)
  setTimeout(() => {
    setCurrentDate();
    fetchNextFileNumber();
    updateStepDisplay();
  }, 100);
});

function setupEventListeners() {
  console.log('Setting up event listeners...');
  
  // Modal controls
  const closeBtn = document.getElementById('close-modal');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeModal);
    console.log('Close button event listener added');
  } else {
    console.error('Close button not found');
  }
  
  // Navigation buttons
  const prevBtn = document.getElementById('prev-btn');
  const nextBtn = document.getElementById('next-btn');
  
  if (prevBtn) {
    prevBtn.addEventListener('click', previousStep);
    console.log('Previous button event listener added');
  } else {
    console.error('Previous button not found');
  }
  
  if (nextBtn) {
    nextBtn.addEventListener('click', function(e) {
      console.log('Next button clicked!', e);
      nextStep(e);
    });
    console.log('Next button event listener added');
  } else {
    console.error('Next button not found');
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
  } else {
    console.error('Form not found');
  }
  
  // Conditional field displays
  setupConditionalFields();
  
  // Close modal on backdrop click
  const modal = document.getElementById('new-recertification-modal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
    console.log('Modal backdrop event listener added');
  } else {
    console.error('Modal not found');
  }
  
  // Add keyboard shortcuts
  document.addEventListener('keydown', handleKeyboardShortcuts);
  
  console.log('Event listeners setup complete');
}

function setupConditionalFields() {
  // Original owner conditional fields
  document.querySelectorAll('input[name="isOriginalOwner"]').forEach(radio => {
    radio.addEventListener('change', function() {
      const ownershipDetails = document.getElementById('ownership-details');
      if (this.value === 'no') {
        ownershipDetails.classList.remove('hidden');
      } else {
        ownershipDetails.classList.add('hidden');
      }
    });
  });
  
  // Encumbrance conditional fields
  document.querySelectorAll('input[name="isEncumbered"]').forEach(radio => {
    radio.addEventListener('change', function() {
      const encumbranceReason = document.getElementById('encumbrance-reason');
      if (this.value === 'yes') {
        encumbranceReason.classList.remove('hidden');
      } else {
        encumbranceReason.classList.add('hidden');
      }
    });
  });
  
  // Mortgage conditional fields
  document.querySelectorAll('input[name="hasMortgage"]').forEach(radio => {
    radio.addEventListener('change', function() {
      const mortgageDetails = document.getElementById('mortgage-details');
      if (this.value === 'yes') {
        mortgageDetails.classList.remove('hidden');
      } else {
        mortgageDetails.classList.add('hidden');
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
  document.getElementById('applicationDate').value = today;
  formData.applicationDate = today;
}

function updateStepDisplay() {
  // Hide all step contents
  document.querySelectorAll('.step-content').forEach(content => {
    content.classList.add('hidden');
  });
  
  // Show current step content
  document.getElementById(`step-content-${currentStep}`).classList.remove('hidden');
  
  // Update step indicators
  for (let i = 1; i <= totalSteps; i++) {
    const stepCircle = document.getElementById(`step-${i}`);
    const stepLine = document.getElementById(`line-${i}`);
    
    if (i <= currentStep) {
      stepCircle.classList.remove('inactive');
      stepCircle.classList.add('active');
    } else {
      stepCircle.classList.remove('active');
      stepCircle.classList.add('inactive');
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
  const nextText = nextBtn.querySelector('.next-text');
  
  prevBtn.disabled = currentStep === 1;
  
  if (currentStep === totalSteps) {
    nextText.textContent = 'Submit Application';
  } else {
    nextText.textContent = 'Next';
  }
}

function previousStep() {
  if (currentStep > 1) {
    currentStep--;
    updateStepDisplay();
  }
}

async function nextStep() {
  console.log('nextStep called, currentStep:', currentStep);
  
  if (currentStep < totalSteps) {
    // For testing purposes, allow skipping validation with Ctrl+Click
    const skipValidation = event && (event.ctrlKey || event.metaKey);
    
    // TEMPORARY: Skip validation for debugging
    const tempSkipValidation = true; // Set to false to enable validation
    
    if (skipValidation || tempSkipValidation || validateCurrentStep()) {
      console.log('Moving to next step...');
      currentStep++;
      updateStepDisplay();
      
      if (skipValidation) {
        showToast('Validation skipped for testing', 'warning');
      } else if (tempSkipValidation) {
        showToast('Validation temporarily disabled for debugging', 'info');
      }
    } else {
      console.log('Validation failed, staying on current step');
    }
  } else {
    console.log('On final step, submitting form...');
    // Submit form
    await submitForm();
  }
}

function validateCurrentStep() {
  const currentStepElement = document.getElementById(`step-content-${currentStep}`);
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
    if (!agreeTerms.checked) {
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
  const nextText = nextBtn.querySelector('.next-text');
  const loadingSpinner = nextBtn.querySelector('.loading-spinner');
  
  // Show loading state
  nextBtn.disabled = true;
  nextText.textContent = 'Submitting...';
  loadingSpinner.classList.remove('hidden');
  
  try {
    // Collect all form data
    const form = document.getElementById('recertification-form');
    const currentFormData = new FormData(form);
    const applicationData = Object.fromEntries(currentFormData.entries());
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    console.log('Recertification application submitted:', applicationData);
    
    // Show success message
    showToast('Application submitted successfully!', 'success');
    
    // Close modal after short delay
    setTimeout(() => {
      closeModal();
    }, 2000);
    
  } catch (error) {
    console.error('Error submitting application:', error);
    showToast('Failed to submit application. Please try again.', 'error');
  } finally {
    // Reset loading state
    nextBtn.disabled = false;
    nextText.textContent = 'Submit Application';
    loadingSpinner.classList.add('hidden');
  }
}

function closeModal() {
  const modal = document.getElementById('new-recertification-modal');
  modal.style.display = 'none';
  document.body.style.overflow = 'auto';
  resetForm();
}

function resetForm() {
  // Reset step
  currentStep = 1;
  updateStepDisplay();
  
  // Reset form
  document.getElementById('recertification-form').reset();
  
  // Reset form data
  formData = {};
  
  // Clear all errors
  document.querySelectorAll('.form-field').forEach(field => {
    field.classList.remove('error');
  });
  
  // Hide conditional fields
  document.getElementById('ownership-details').classList.add('hidden');
  document.getElementById('encumbrance-reason').classList.add('hidden');
  document.getElementById('mortgage-details').classList.add('hidden');
  
  // Set current date again
  setCurrentDate();
}

function showToast(message, type = 'info') {
  const toastContainer = document.getElementById('toast-container');
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
  lucide.createIcons();
  
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
  // Only handle shortcuts when modal is open
  const modal = document.getElementById('new-recertification-modal');
  if (modal.style.display !== 'flex') return;
  
  // Prevent default behavior for our shortcuts
  if (event.ctrlKey || event.metaKey) {
    switch(event.key) {
      case 'ArrowLeft':
        event.preventDefault();
        previousStep();
        break;
      case 'ArrowRight':
        event.preventDefault();
        nextStep();
        break;
      case 'Escape':
        event.preventDefault();
        closeModal();
        break;
    }
  }
  
  // Number keys to jump to steps
  if (event.key >= '1' && event.key <= '6' && (event.ctrlKey || event.metaKey)) {
    event.preventDefault();
    goToStep(parseInt(event.key));
  }
}

// Debug function for development
function debugFormWizard() {
  console.log('=== Form Wizard Debug Info ===');
  console.log('Current Step:', currentStep);
  console.log('Total Steps:', totalSteps);
  console.log('Form Data:', formData);
  
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
}

// Make debug function available globally
window.debugFormWizard = debugFormWizard;

// API for external usage (can be called from parent page)
window.NewRecertificationDialog = {
  open: function() {
    const modal = document.getElementById('new-recertification-modal');
    if (modal) {
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      
      // Re-setup event listeners when modal opens (in case they weren't set up initially)
      setTimeout(() => {
        setupEventListeners();
        updateStepDisplay();
      }, 100);
    } else {
      console.error('Modal not found when trying to open');
    }
  },
  close: closeModal,
  reset: resetForm,
  goToStep: goToStep,
  getCurrentStep: () => currentStep,
  debug: debugFormWizard
};

// Also add a simple test function to manually trigger next step
window.testNextStep = function() {
  console.log('Testing next step...');
  nextStep();
}; 

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
      if (fileNumberInput) {
        fileNumberInput.value = data.file_number;
        fileNumberInput.placeholder = data.file_number;
        formData.fileNumber = data.file_number;
        console.log('File number loaded:', data.file_number);
      }
    } else {
      console.error('Failed to fetch file number:', data);
      // Set fallback file number
      const fileNumberInput = document.getElementById('fileNumber');
      if (fileNumberInput) {
        fileNumberInput.value = 'KN3000';
        fileNumberInput.placeholder = 'KN3000';
        formData.fileNumber = 'KN3000';
      }
    }
  } catch (error) {
    console.error('Error fetching file number:', error);
    // Set fallback file number
    const fileNumberInput = document.getElementById('fileNumber');
    if (fileNumberInput) {
      fileNumberInput.value = 'KN3000';
      fileNumberInput.placeholder = 'KN3000';
      formData.fileNumber = 'KN3000';
    }
  }
}
</script>
 


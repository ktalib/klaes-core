/**
 * Primary Application Form - Navigation Functions
 * Handles step-by-step navigation without validation
 */

// Global function to navigate to specific step (without validation)
function goToStep(stepNumber) {
    console.log('🚀 Navigating to step:', stepNumber);
    
    // Hide all steps aggressively
    const allSteps = document.querySelectorAll('.form-section');
    console.log('📋 Found form sections:', allSteps.length);
    allSteps.forEach((step, index) => {
        step.classList.remove('active');
        // Aggressively hide all steps
        step.style.display = 'none';
        step.style.visibility = 'hidden';
        step.style.opacity = '0';
        step.style.height = '0';
        step.style.overflow = 'hidden';
        console.log(`   Step ${index + 1}: ${step.id} - forcefully hidden`);
    });
    
    // Show target step aggressively
    const targetStep = document.getElementById(`step${stepNumber}`);
    if (targetStep) {
        targetStep.classList.add('active');
        // Aggressively show the target step
        targetStep.style.display = 'block';
        targetStep.style.visibility = 'visible';
        targetStep.style.opacity = '1';
        targetStep.style.height = 'auto';
        targetStep.style.overflow = 'visible';
        console.log(`✅ Activated step: ${targetStep.id}`);
    } else {
        console.error(`❌ Target step not found: step${stepNumber}`);
        return;
    }
    
    // Double-check that only the target step is visible
    setTimeout(() => {
        const allStepsCheck = document.querySelectorAll('.form-section');
        console.log(`🔍 Verification: Checking all ${allStepsCheck.length} steps`);
        
        allStepsCheck.forEach(step => {
            if (step.id === `step${stepNumber}`) {
                // Ensure target step is visible
                if (!step.classList.contains('active') || step.style.display === 'none') {
                    step.classList.add('active');
                    step.style.display = 'block';
                    step.style.visibility = 'visible';
                    step.style.opacity = '1';
                    step.style.height = 'auto';
                    console.log(`🔧 Re-activated: ${step.id}`);
                }
            } else {
                // Ensure non-target steps are hidden
                if (step.classList.contains('active') || step.style.display !== 'none') {
                    step.classList.remove('active');
                    step.style.display = 'none';
                    step.style.visibility = 'hidden';
                    step.style.opacity = '0';
                    step.style.height = '0';
                    console.log(`🔧 Force-hidden: ${step.id}`);
                }
            }
        });
        
        const activeSteps = document.querySelectorAll('.form-section.active');
        console.log(`✅ Final verification: ${activeSteps.length} step(s) active`);
    }, 100);
    
    // Update step circles
    updateStepCircles(stepNumber);
    
    // Update step text
    updateStepText(stepNumber);
    
    // If navigating to summary step, update the summary
    if (stepNumber === 5) {
        if (typeof window.updateApplicationSummary === 'function') {
            window.updateApplicationSummary();
        }
    }
    
    // Reinitialize icons for the new step
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Show success message for step completion (without validation)
    showStepSuccessMessage(stepNumber);
    
    console.log(`🎉 Successfully navigated to step ${stepNumber}`);
}

// Function to show step completion message
function showStepSuccessMessage(stepNumber) {
    const stepMessages = {
        1: 'Step 1 accessed - Basic information section',
        2: 'Step 2 accessed - Shared areas section', 
        3: 'Step 3 accessed - Documents section',
        4: 'Step 4 accessed - Buyers list section',
        5: 'Step 5 accessed - Summary section'
    };
    
    if (stepMessages[stepNumber] && stepNumber > 1) {
        Swal.fire({
            icon: 'info',
            title: 'Navigation',
            text: stepMessages[stepNumber],
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
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

// Function to go to next step
function goToNextStep() {
    console.log('🔄 goToNextStep() called');
    const currentActiveStep = document.querySelector('.form-section.active');
    if (currentActiveStep) {
        const stepId = currentActiveStep.id;
        const currentStepNumber = parseInt(stepId.replace('step', ''));
        const nextStep = currentStepNumber + 1;
        
        console.log(`📍 Current step: ${currentStepNumber}, Next step: ${nextStep}`);
        
        if (nextStep <= 5) {
            goToStep(nextStep);
        } else {
            console.log('⚠️ Cannot go beyond step 5');
        }
    } else {
        console.error('❌ No active step found');
    }
}

// Function to go to previous step
function goToPreviousStep() {
    const currentActiveStep = document.querySelector('.form-section.active');
    if (currentActiveStep) {
        const stepId = currentActiveStep.id;
        const currentStepNumber = parseInt(stepId.replace('step', ''));
        const previousStep = currentStepNumber - 1;
        
        if (previousStep >= 1) {
            goToStep(previousStep);
        }
    }
}

// Make functions globally accessible
window.goToStep = goToStep;
window.goToNextStep = goToNextStep;
window.goToPreviousStep = goToPreviousStep;
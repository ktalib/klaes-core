/**
 * Primary Application Form - Navigation Functions
 * Handles step-by-step navigation without validation
 */

// Global function to navigate to specific step (without validation)
function goToStep(stepNumber) {
    console.log('🚀 Navigation.js - Navigating to step:', stepNumber);
    
    try {
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
            targetStep.style.position = 'relative'; // Ensure proper positioning
            targetStep.style.zIndex = '1';
            console.log(`✅ Activated step: ${targetStep.id}`);
            
            // Special handling for step 5 
            if (stepNumber === 5) {
                console.log('🎯 Special handling for step 5 - Summary');
                targetStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Force visibility again after a moment
                setTimeout(() => {
                    targetStep.style.display = 'block';
                    targetStep.style.visibility = 'visible';
                    targetStep.style.opacity = '1';
                    targetStep.style.height = 'auto';
                    console.log('🔧 Step 5 forced visible again');
                }, 50);
            }
            
        } else {
            console.error(`❌ Target step not found: step${stepNumber}`);
            // Try alternative approach
            const altStep = document.querySelector(`[data-step="${stepNumber}"], #summary-step, .step-${stepNumber}`);
            if (altStep) {
                console.log('🔄 Found alternative step element:', altStep.id || altStep.className);
                altStep.style.display = 'block';
                altStep.style.visibility = 'visible';
                altStep.style.opacity = '1';
            } else {
                alert(`Error: Step ${stepNumber} not found. Please refresh the page.`);
                return;
            }
        }
    } catch (error) {
        console.error('❌ Error in goToStep:', error);
        alert('Navigation error occurred. Please refresh the page and try again.');
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
        
        // Log step 5 specific verification
        if (stepNumber === 5) {
            const step5 = document.getElementById('step5');
            if (step5) {
                const computed = window.getComputedStyle(step5);
                console.log('🎯 Step 5 Final Status:');
                console.log(`   Display: ${computed.display}`);
                console.log(`   Visibility: ${computed.visibility}`);
                console.log(`   Opacity: ${computed.opacity}`);
                console.log(`   Height: ${computed.height}`);
                console.log(`   Active class: ${step5.classList.contains('active')}`);
            }
        }
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
    const totalSteps = 5;

    document.querySelectorAll('[data-step-indicator]').forEach(elem => {
        const total = elem.dataset.stepTotal || totalSteps;
        const label = elem.dataset.stepLabel;

        if (label) {
            elem.textContent = `Step ${currentStep} - ${label}`;
        } else {
            elem.textContent = `Step ${currentStep} of ${total}`;
        }
    });

    const instructionsIndicator = document.getElementById('currentStepIndicator');
    if (instructionsIndicator) {
        instructionsIndicator.textContent = `Step ${currentStep} of ${totalSteps}`;
    }
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

// Make functions globally accessible but avoid overwriting existing implementations
if (typeof window.goToStep === 'function') {
    console.warn('window.goToStep already defined by another script - preserving existing implementation.');
} else {
    window.goToStep = goToStep;
}

if (typeof window.goToNextStep === 'function') {
    console.warn('window.goToNextStep already defined by another script - preserving existing implementation.');
} else {
    window.goToNextStep = goToNextStep;
}

if (typeof window.goToPreviousStep === 'function') {
    console.warn('window.goToPreviousStep already defined by another script - preserving existing implementation.');
} else {
    window.goToPreviousStep = goToPreviousStep;
}
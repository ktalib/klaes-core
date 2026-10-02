// SIMPLE STEP NAVIGATION FIX - NO CONFLICTS
console.log('🚀 Loading Simple Step Navigation Fix...');

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Initializing step navigation...');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Clear any existing navigation functions to prevent conflicts
    window.goToStep = null;
    
    // Simple, reliable navigation function
    function goToStep(stepNumber) {
        console.log(`🚀 Navigating to step ${stepNumber}`);
        
        // Hide all steps
        const allSteps = document.querySelectorAll('.form-section');
        console.log(`Found ${allSteps.length} steps to manage`);
        
        allSteps.forEach((step, index) => {
            step.classList.remove('active-tab');
            step.style.display = 'none';
            console.log(`Hidden step ${index + 1}: ${step.id}`);
        });
        
        // Show target step
        const targetStep = document.getElementById(`step${stepNumber}`);
        if (targetStep) {
            targetStep.classList.add('active-tab');
            targetStep.style.display = 'block';
            
            // Force visibility for step 3 and 4 specifically
            if (stepNumber === 3 || stepNumber === 4) {
                targetStep.style.visibility = 'visible';
                targetStep.style.opacity = '1';
                targetStep.style.position = 'static';
                
                // Ensure all child elements are visible
                const children = targetStep.querySelectorAll('*');
                children.forEach(child => {
                    if (child.style.display === 'none') {
                        child.style.display = '';
                    }
                });
            }
            
            console.log(`✅ Showed step ${stepNumber}`);
            
            // Update step circles
            updateStepCircles(stepNumber);
            
            // Update step text
            updateStepText(stepNumber);
            
            // Scroll to top of step
            setTimeout(() => {
                targetStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
            
        } else {
            console.error(`❌ Step not found: step${stepNumber}`);
            console.log('Available steps:', Array.from(document.querySelectorAll('[id^="step"]')).map(s => s.id));
        }
    }
    
    function updateStepCircles(currentStep) {
        const stepCircles = document.querySelectorAll('.step-circle');
        console.log(`Updating ${stepCircles.length} step circles for step ${currentStep}`);
        
        stepCircles.forEach((circle, index) => {
            const stepNum = index + 1;
            circle.classList.remove('active-tab', 'inactive-tab');
            
            if (stepNum === currentStep) {
                circle.classList.add('active-tab');
            } else {
                circle.classList.add('inactive-tab');
            }
        });
    }
    
    function updateStepText(currentStep) {
        // Update any step text indicators
        const stepTexts = document.querySelectorAll('[class*="Step"][class*="of"]');
        stepTexts.forEach(text => {
            text.textContent = `Step ${currentStep} of 4`;
        });
        
        // Update specific step text elements
        const stepTextElements = document.querySelectorAll('.ml-4');
        stepTextElements.forEach(element => {
            if (element.textContent.includes('Step')) {
                element.textContent = `Step ${currentStep} of 4`;
            }
        });
    }
    
    // Make function globally accessible
    window.goToStep = goToStep;
    
    // Remove onclick attributes and attach clean event listeners
    function attachCleanListeners() {
        console.log('🔗 Attaching clean event listeners...');
        
        // Step circles
        document.querySelectorAll('.step-circle').forEach((circle, index) => {
            const stepNum = index + 1;
            
            // Remove any existing onclick attributes
            circle.removeAttribute('onclick');
            
            // Clone element to remove all existing event listeners
            const newCircle = circle.cloneNode(true);
            if (circle.parentNode) {
                circle.parentNode.replaceChild(newCircle, circle);
            }
            
            // Add clean event listener
            newCircle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log(`🔘 Step circle ${stepNum} clicked`);
                goToStep(stepNum);
            });
            
            console.log(`✅ Clean listener attached to step circle ${stepNum}`);
        });
        
        // Navigation buttons
        const navigationButtons = [
            { id: 'nextStep1', target: 2 },
            { id: 'nextStep2', target: 3 },
            { id: 'nextStep3', target: 4 },
            { id: 'backStep2', target: 1 },
            { id: 'backStep3', target: 2 },
            { id: 'backStep4', target: 3 }
        ];
        
        navigationButtons.forEach(btn => {
            const element = document.getElementById(btn.id);
            if (element) {
                // Clone to remove existing listeners
                const newElement = element.cloneNode(true);
                if (element.parentNode) {
                    element.parentNode.replaceChild(newElement, element);
                }
                
                newElement.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log(`🔘 Button ${btn.id} clicked, going to step ${btn.target}`);
                    goToStep(btn.target);
                });
                
                console.log(`✅ Clean listener attached to ${btn.id}`);
            }
        });
        
        // Submit button
        const submitBtn = document.getElementById('submitApplication');
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                console.log('📝 Submit button clicked');
                document.getElementById('subApplicationForm').submit();
            });
        }
    }
    
    // Other areas toggle function
    function toggleOtherAreasTextarea() {
        const checkbox = document.getElementById('other_areas');
        const container = document.getElementById('other_areas_container');

        if (checkbox && container) {
            if (checkbox.checked) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                const textarea = document.getElementById('other_areas_detail');
                if (textarea) textarea.value = '';
            }
        }
    }
    
    // Initialize other areas toggle
    const otherAreasCheckbox = document.getElementById('other_areas');
    if (otherAreasCheckbox) {
        otherAreasCheckbox.addEventListener('change', toggleOtherAreasTextarea);
        toggleOtherAreasTextarea();
    }
    
    // Make toggle function globally accessible
    window.toggleOtherAreasTextarea = toggleOtherAreasTextarea;
    
    // Initialize everything
    attachCleanListeners();
    
    // Ensure step 1 is visible initially
    setTimeout(() => {
        const step1 = document.getElementById('step1');
        if (step1) {
            step1.classList.add('active-tab');
            step1.style.display = 'block';
            console.log('✅ Step 1 ensured visible');
        }
        
        // Debug: Check all steps
        const allSteps = document.querySelectorAll('[id^="step"]');
        console.log(`📊 Debug - Found ${allSteps.length} steps:`);
        allSteps.forEach(step => {
            console.log(`  - ${step.id}: display=${step.style.display}, hasActiveTab=${step.classList.contains('active-tab')}`);
        });
    }, 500);
    
    console.log('🎉 Simple step navigation initialized successfully!');
});

// Additional initialization for late-loading content
setTimeout(() => {
    if (typeof window.goToStep !== 'function') {
        console.log('🔄 Retrying navigation initialization...');
        // Re-run initialization if it didn't work the first time
        const event = new Event('DOMContentLoaded');
        document.dispatchEvent(event);
    }
}, 2000);
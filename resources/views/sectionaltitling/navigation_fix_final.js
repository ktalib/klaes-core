// FINAL CLEAN STEP NAVIGATION FIX
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Final Clean Step Navigation Loading...');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Clear any existing navigation functions
    window.goToStep = null;
    
    // Simple navigation function
    function goToStep(stepNumber) {
        console.log(`Navigating to step ${stepNumber}`);
        
        // Hide all steps
        const allSteps = document.querySelectorAll('.form-section');
        allSteps.forEach(step => {
            step.classList.remove('active-tab');
        });
        
        // Show target step
        const targetStep = document.getElementById(`step${stepNumber}`);
        if (targetStep) {
            targetStep.classList.add('active-tab');
            console.log(`✅ Showed step ${stepNumber}`);
        }
        
        // Update step circles
        const stepCircles = document.querySelectorAll('.step-circle');
        stepCircles.forEach((circle, index) => {
            const stepNum = index + 1;
            circle.classList.remove('active-tab', 'inactive-tab');
            
            if (stepNum === stepNumber) {
                circle.classList.add('active-tab');
            } else {
                circle.classList.add('inactive-tab');
            }
        });
        
        // Update step text
        const stepTexts = document.querySelectorAll('[class*="Step"][class*="of"]');
        stepTexts.forEach(text => {
            text.textContent = `Step ${stepNumber} of 4`;
        });
    }
    
    // Make function globally accessible
    window.goToStep = goToStep;
    
    // Attach click listeners to step circles
    document.querySelectorAll('.step-circle').forEach((circle, index) => {
        const stepNum = index + 1;
        
        // Remove any existing onclick attributes
        circle.removeAttribute('onclick');
        
        circle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log(`Step circle ${stepNum} clicked`);
            goToStep(stepNum);
        });
        
        console.log(`✅ Attached listener to step circle ${stepNum}`);
    });
    
    // Attach listeners to navigation buttons
    const nextButtons = ['nextStep1', 'nextStep2', 'nextStep3'];
    const backButtons = ['backStep2', 'backStep3', 'backStep4'];
    
    nextButtons.forEach((id, index) => {
        const button = document.getElementById(id);
        if (button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                console.log(`Next button ${id} clicked`);
                goToStep(index + 2);
            });
            console.log(`✅ Attached listener to ${id}`);
        }
    });
    
    backButtons.forEach((id, index) => {
        const button = document.getElementById(id);
        if (button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                console.log(`Back button ${id} clicked`);
                goToStep(index + 1);
            });
            console.log(`✅ Attached listener to ${id}`);
        }
    });
    
    // Submit button
    const submitBtn = document.getElementById('submitApplication');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            document.getElementById('subApplicationForm').submit();
        });
    }
    
    // Other areas toggle
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
    
    // Ensure step 1 is visible initially
    setTimeout(() => {
        const step1 = document.getElementById('step1');
        if (step1) {
            step1.classList.add('active-tab');
            console.log('✅ Step 1 ensured visible');
        }
    }, 100);
    
    console.log('✅ Final clean navigation initialized successfully!');
    
    // Debug info
    const steps = document.querySelectorAll('[id^="step"]');
    console.log(`📊 Found ${steps.length} steps:`, Array.from(steps).map(s => s.id));
});
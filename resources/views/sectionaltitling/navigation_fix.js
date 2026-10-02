// Clean step navigation system
(function() {
    'use strict';
    
    function initializeNavigation() {
        console.log('Initializing step navigation...');
        
        // Simple step navigation function
        function goToStep(stepNumber) {
            console.log('Navigating to step:', stepNumber);
            
            // Hide all steps
            const allSteps = document.querySelectorAll('.form-section');
            allSteps.forEach(step => {
                step.classList.remove('active-tab');
                step.style.display = 'none';
            });
            
            // Show target step
            const targetStep = document.getElementById(`step${stepNumber}`);
            if (targetStep) {
                targetStep.classList.add('active-tab');
                targetStep.style.display = 'block';
                console.log('Step', stepNumber, 'is now visible');
            } else {
                console.error('Step not found:', `step${stepNumber}`);
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
            const stepTextElements = document.querySelectorAll('.ml-4');
            stepTextElements.forEach(element => {
                if (element.textContent.includes('Step')) {
                    element.textContent = `Step ${stepNumber}`;
                }
            });
        }
        
        // Make function globally available
        window.goToStep = goToStep;
        
        // Initialize with step 1
        goToStep(1);
        
        // Add event listeners to navigation buttons
        const nextStep1 = document.getElementById('nextStep1');
        if (nextStep1) {
            nextStep1.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(2);
            });
        }
        
        const nextStep2 = document.getElementById('nextStep2');
        if (nextStep2) {
            nextStep2.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(3);
            });
        }
        
        const nextStep3 = document.getElementById('nextStep3');
        if (nextStep3) {
            nextStep3.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(4);
            });
        }
        
        const backStep2 = document.getElementById('backStep2');
        if (backStep2) {
            backStep2.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(1);
            });
        }
        
        const backStep3 = document.getElementById('backStep3');
        if (backStep3) {
            backStep3.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(2);
            });
        }
        
        const backStep4 = document.getElementById('backStep4');
        if (backStep4) {
            backStep4.addEventListener('click', function(e) {
                e.preventDefault();
                goToStep(3);
            });
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeNavigation);
    } else {
        initializeNavigation();
    }
})();
// STEP 3 SPECIFIC DEBUG AND FIX
console.log('🔍 Step 3 Debug Fix Loading...');

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Step 3 Debug Fix Initializing...');
    
    // Function to debug and fix step 3 specifically
    function debugAndFixStep3() {
        console.log('🔍 Debugging Step 3...');
        
        const step3 = document.getElementById('step3');
        if (!step3) {
            console.error('❌ Step 3 element not found!');
            return;
        }
        
        console.log('✅ Step 3 element found:', step3);
        console.log('Step 3 current styles:', {
            display: step3.style.display,
            visibility: step3.style.visibility,
            opacity: step3.style.opacity,
            height: step3.style.height,
            width: step3.style.width
        });
        
        console.log('Step 3 computed styles:', {
            display: window.getComputedStyle(step3).display,
            visibility: window.getComputedStyle(step3).visibility,
            opacity: window.getComputedStyle(step3).opacity,
            height: window.getComputedStyle(step3).height,
            width: window.getComputedStyle(step3).width
        });
        
        console.log('Step 3 classes:', step3.className);
        console.log('Step 3 innerHTML length:', step3.innerHTML.length);
        console.log('Step 3 children count:', step3.children.length);
        
        // Check if content exists
        const content = step3.querySelector('.p-6');
        if (content) {
            console.log('✅ Step 3 content container found');
            console.log('Content children:', content.children.length);
        } else {
            console.error('❌ Step 3 content container not found');
        }
        
        // Force step 3 to be visible
        step3.style.cssText = `
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: static !important;
            height: auto !important;
            width: auto !important;
            z-index: 1 !important;
        `;
        
        // Force all children to be visible
        const allChildren = step3.querySelectorAll('*');
        allChildren.forEach((child, index) => {
            if (window.getComputedStyle(child).display === 'none') {
                child.style.display = 'block';
                console.log(`Fixed child ${index} display`);
            }
            if (window.getComputedStyle(child).visibility === 'hidden') {
                child.style.visibility = 'visible';
                console.log(`Fixed child ${index} visibility`);
            }
        });
        
        console.log('🔧 Step 3 forced to be visible');
    }
    
    // Enhanced goToStep function with step 3 specific handling
    function enhancedGoToStep(stepNumber) {
        console.log(`🚀 Enhanced navigation to step ${stepNumber}`);
        
        // Hide all steps first
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
            
            // Special handling for step 3
            if (stepNumber === 3) {
                console.log('🔧 Applying special step 3 fixes...');
                
                // Force visibility with multiple methods
                targetStep.style.cssText = `
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    position: static !important;
                    height: auto !important;
                    width: auto !important;
                    z-index: 1 !important;
                `;
                
                // Ensure Lucide icons are loaded
                if (typeof lucide !== 'undefined') {
                    setTimeout(() => {
                        lucide.createIcons();
                        console.log('🎨 Lucide icons refreshed for step 3');
                    }, 100);
                }
                
                // Force all children to be visible
                setTimeout(() => {
                    const allChildren = targetStep.querySelectorAll('*');
                    allChildren.forEach(child => {
                        if (child.style.display === 'none') {
                            child.style.display = '';
                        }
                        if (child.style.visibility === 'hidden') {
                            child.style.visibility = 'visible';
                        }
                    });
                    console.log('🔧 All step 3 children forced visible');
                }, 200);
                
                // Debug after fixes
                setTimeout(() => {
                    debugAndFixStep3();
                }, 300);
            }
            
            console.log(`✅ Step ${stepNumber} should now be visible`);
        } else {
            console.error(`❌ Step ${stepNumber} not found`);
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
    }
    
    // Override the global goToStep function
    window.goToStep = enhancedGoToStep;
    
    // Add specific event listeners for step 3
    setTimeout(() => {
        // Step 3 circle
        const step3Circle = document.querySelector('.step-circle[onclick*="3"]');
        if (step3Circle) {
            step3Circle.removeAttribute('onclick');
            step3Circle.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('🔘 Step 3 circle clicked');
                enhancedGoToStep(3);
            });
            console.log('✅ Step 3 circle listener attached');
        }
        
        // Next button from step 2
        const nextStep2 = document.getElementById('nextStep2');
        if (nextStep2) {
            nextStep2.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('➡️ Next to step 3 clicked');
                enhancedGoToStep(3);
            });
            console.log('✅ Next to step 3 listener attached');
        }
        
        // Back button from step 4
        const backStep4 = document.getElementById('backStep4');
        if (backStep4) {
            backStep4.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('⬅️ Back to step 3 clicked');
                enhancedGoToStep(3);
            });
            console.log('✅ Back to step 3 listener attached');
        }
    }, 1000);
    
    // Test step 3 visibility on load
    setTimeout(() => {
        console.log('🧪 Testing step 3 visibility...');
        debugAndFixStep3();
    }, 2000);
    
    console.log('🎉 Step 3 debug fix initialized');
});

// Additional fallback - force step 3 to be visible if clicked
document.addEventListener('click', function(e) {
    if (e.target.textContent === '3' && e.target.classList.contains('step-circle')) {
        console.log('🔘 Step 3 clicked via event delegation');
        setTimeout(() => {
            const step3 = document.getElementById('step3');
            if (step3) {
                step3.style.cssText = `
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    position: static !important;
                    height: auto !important;
                    width: auto !important;
                `;
                console.log('🔧 Step 3 forced visible via click delegation');
            }
        }, 100);
    }
});
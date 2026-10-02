// ULTIMATE CLEAN NAVIGATION FIX - Enhanced Version
(function() {
    'use strict';
    
    console.log('🚀 ULTIMATE navigation fix loading (Enhanced)...');
    
    // Wait for DOM and all scripts to load
    function initializeNavigation() {
        console.log('🔧 Initializing enhanced navigation system...');
        
        // Clear any existing navigation functions
        window.goToStep = null;
        window.goToStepFinal = null;
        
        // Simple validation functions
        function validateStep1() {
            const applicantType = document.querySelector('input[name="applicantType"]:checked');
            if (!applicantType) return ['Please select an applicant type'];
            
            const schemeNo = document.getElementById('schemeName')?.value;
            if (!schemeNo || schemeNo.trim() === '') return ['Please enter scheme number'];
            
            return [];
        }
        
        function validateStep2() {
            const sharedAreas = document.querySelectorAll('input[name="shared_areas[]"]:checked');
            return sharedAreas.length === 0 ? ['Please select at least one shared area'] : [];
        }
        
        function validateStep3() {
            const docs = ['application_letter', 'building_plan', 'architectural_design', 'ownership_document'];
            const hasDoc = docs.some(id => document.getElementById(id)?.files[0]);
            return hasDoc ? [] : ['Please upload at least one document'];
        }
        
        function showErrors(errors) {
            if (errors.length > 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: errors.map(e => `• ${e}`).join('<br>'),
                    confirmButtonColor: '#dc2626'
                });
                return false;
            }
            return true;
        }
        
        // Enhanced navigation function with better step visibility handling
        window.goToStep = function(targetStep, skipValidation = false) {
            console.log(`📍 Navigation: Going to step ${targetStep}, skipValidation: ${skipValidation}`);
            
            // Get current step
            const currentActive = document.querySelector('.form-section.active-tab');
            const currentStep = currentActive ? parseInt(currentActive.id.replace('step', '')) : 1;
            
            console.log(`📍 Current step: ${currentStep}, Target: ${targetStep}`);
            
            if (currentStep === targetStep) {
                console.log('📍 Already on target step');
                return;
            }
            
            // Validate if moving forward and not skipping
            if (targetStep > currentStep && !skipValidation) {
                let errors = [];
                switch (currentStep) {
                    case 1: errors = validateStep1(); break;
                    case 2: errors = validateStep2(); break;
                    case 3: errors = validateStep3(); break;
                }
                
                if (!showErrors(errors)) {
                    console.log('📍 Validation failed, staying on current step');
                    return;
                }
            }
            
            // Hide all steps with multiple methods to ensure they're completely hidden
            document.querySelectorAll('.form-section').forEach((step, index) => {
                step.classList.remove('active-tab');
                step.style.display = 'none';
                step.style.visibility = 'hidden';
                step.style.opacity = '0';
                step.style.position = 'absolute';
                step.style.left = '-9999px';
                step.setAttribute('hidden', 'true');
                step.setAttribute('aria-hidden', 'true');
                console.log(`🔒 Hidden step ${index + 1}: ${step.id}`);
            });
            
            // Show target step with multiple methods to ensure it's completely visible
            const targetElement = document.getElementById(`step${targetStep}`);
            if (targetElement) {
                // Remove all hiding styles
                targetElement.classList.add('active-tab');
                targetElement.style.display = 'block';
                targetElement.style.visibility = 'visible';
                targetElement.style.opacity = '1';
                targetElement.style.position = 'static';
                targetElement.style.left = 'auto';
                targetElement.removeAttribute('hidden');
                targetElement.removeAttribute('aria-hidden');
                
                // Force layout recalculation
                targetElement.offsetHeight;
                
                // Additional CSS fixes for step 3 specifically
                if (targetStep === 3) {
                    console.log('🔧 Applying special fixes for step 3...');
                    
                    // Fix any nested grid issues
                    const nestedGrids = targetElement.querySelectorAll('.grid');
                    nestedGrids.forEach(grid => {
                        grid.style.display = 'grid';
                        grid.style.visibility = 'visible';
                    });
                    
                    // Ensure all child elements are visible
                    const allChildren = targetElement.querySelectorAll('*');
                    allChildren.forEach(child => {
                        if (child.style.display === 'none' && !child.hasAttribute('hidden')) {
                            child.style.display = '';
                        }
                    });
                    
                    // Force re-render
                    targetElement.style.transform = 'translateZ(0)';
                    setTimeout(() => {
                        targetElement.style.transform = '';
                    }, 10);
                }
                
                console.log(`✅ Successfully navigated to step ${targetStep}`);
                console.log(`📊 Step ${targetStep} visibility:`, {
                    display: targetElement.style.display,
                    visibility: targetElement.style.visibility,
                    opacity: targetElement.style.opacity,
                    position: targetElement.style.position,
                    hasActiveTab: targetElement.classList.contains('active-tab'),
                    offsetHeight: targetElement.offsetHeight,
                    scrollHeight: targetElement.scrollHeight,
                    clientHeight: targetElement.clientHeight
                });
                
                // Update step circles
                document.querySelectorAll('.step-circle').forEach((circle, index) => {
                    circle.classList.remove('active-tab', 'inactive-tab');
                    circle.classList.add(index + 1 === targetStep ? 'active-tab' : 'inactive-tab');
                });
                
                // Update step text
                document.querySelectorAll('[class*="Step"][class*="of"]').forEach(text => {
                    text.textContent = `Step ${targetStep} of 4`;
                });
                
                // Scroll to step with additional checks
                setTimeout(() => {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    // Additional visibility check after scroll
                    setTimeout(() => {
                        const computedStyle = window.getComputedStyle(targetElement);
                        console.log(`🔍 Post-scroll check for step ${targetStep}:`, {
                            isVisible: targetElement.offsetHeight > 0,
                            computedDisplay: computedStyle.display,
                            computedVisibility: computedStyle.visibility,
                            computedOpacity: computedStyle.opacity,
                            computedPosition: computedStyle.position,
                            boundingRect: targetElement.getBoundingClientRect()
                        });
                        
                        // If step 3 is still not visible, apply emergency fixes
                        if (targetStep === 3 && targetElement.offsetHeight === 0) {
                            console.log('🚨 Emergency fix for step 3 visibility...');
                            targetElement.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: static !important;';
                            targetElement.classList.add('active-tab');
                        }
                    }, 500);
                }, 100);
            } else {
                console.error(`❌ Step element not found: step${targetStep}`);
                console.log('Available steps:', Array.from(document.querySelectorAll('[id^="step"]')).map(s => s.id));
            }
        };
        
        // Attach event listeners
        function attachListeners() {
            console.log('🔗 Attaching enhanced event listeners...');
            
            // Step circles - allow direct navigation
            document.querySelectorAll('.step-circle').forEach((circle, index) => {
                const stepNum = index + 1;
                
                // Remove existing listeners by cloning
                const newCircle = circle.cloneNode(true);
                circle.parentNode.replaceChild(newCircle, circle);
                
                newCircle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log(`🔘 Step circle ${stepNum} clicked`);
                    window.goToStep(stepNum, true); // Skip validation for direct clicks
                });
                
                newCircle.style.cursor = 'pointer';
                console.log(`✅ Attached enhanced listener to step circle ${stepNum}`);
            });
            
            // Next buttons - use validation
            [
                { id: 'nextStep1', target: 2 },
                { id: 'nextStep2', target: 3 },
                { id: 'nextStep3', target: 4 }
            ].forEach(btn => {
                const element = document.getElementById(btn.id);
                if (element) {
                    const newElement = element.cloneNode(true);
                    element.parentNode.replaceChild(newElement, element);
                    
                    newElement.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log(`➡️ ${btn.id} clicked`);
                        window.goToStep(btn.target, false); // Use validation
                    });
                    
                    console.log(`✅ Attached enhanced listener to ${btn.id}`);
                } else {
                    console.log(`⚠️ Button not found: ${btn.id}`);
                }
            });
            
            // Back buttons - skip validation
            [
                { id: 'backStep2', target: 1 },
                { id: 'backStep3', target: 2 },
                { id: 'backStep4', target: 3 }
            ].forEach(btn => {
                const element = document.getElementById(btn.id);
                if (element) {
                    const newElement = element.cloneNode(true);
                    element.parentNode.replaceChild(newElement, element);
                    
                    newElement.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log(`⬅️ ${btn.id} clicked`);
                        window.goToStep(btn.target, true); // Skip validation
                    });
                    
                    console.log(`✅ Attached enhanced listener to ${btn.id}`);
                }
            });
        }
        
        // Initialize
        attachListeners();
        
        // Ensure step 1 is visible initially
        setTimeout(() => {
            const step1 = document.getElementById('step1');
            if (step1) {
                step1.classList.add('active-tab');
                step1.style.display = 'block';
                step1.style.visibility = 'visible';
                step1.style.opacity = '1';
                step1.style.position = 'static';
                step1.removeAttribute('hidden');
                console.log('✅ Step 1 ensured visible with enhanced methods');
            }
        }, 100);
        
        console.log('🎉 Enhanced navigation system initialized successfully!');
        
        // Debug info
        const steps = document.querySelectorAll('[id^="step"]');
        console.log(`📊 Found ${steps.length} steps:`, Array.from(steps).map(s => ({
            id: s.id,
            offsetHeight: s.offsetHeight,
            display: s.style.display,
            visibility: s.style.visibility
        })));
        
        // Test step 3 specifically
        const step3 = document.getElementById('step3');
        if (step3) {
            console.log('🔍 Step 3 analysis:', {
                exists: true,
                offsetHeight: step3.offsetHeight,
                scrollHeight: step3.scrollHeight,
                childElementCount: step3.childElementCount,
                innerHTML: step3.innerHTML.length + ' characters'
            });
        } else {
            console.log('❌ Step 3 not found!');
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initializeNavigation, 3000);
        });
    } else {
        setTimeout(initializeNavigation, 3000);
    }
})();
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== Caveat Application Initialization ===');
        
        // Check if required functions are loaded
        const requiredFunctions = [
            'initializeTabs',
            'initializeEventListeners', 
            'updateStats',
            'renderCaveatsList',
            'renderActiveCaveatsList', 
            'renderCaveatsTable',
            'updateCaveatNumber',
            'updateDateCreated',
            'setDefaultStartDate'
        ];
        
        console.log('Checking required functions...');
        requiredFunctions.forEach(funcName => {
            if (typeof window[funcName] === 'function') {
                console.log('✅', funcName, 'loaded');
            } else {
                console.error('❌', funcName, 'NOT loaded');
            }
        });
        
        // Check if required DOM elements exist
        const requiredElements = [
            '.tab-trigger',
            '#tab-place',
            '#tab-lift', 
            '#tab-log'
        ];
        
        console.log('Checking required DOM elements...');
        requiredElements.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                console.log('✅', selector, `found (${elements.length})`);
            } else {
                console.error('❌', selector, 'NOT found');
            }
        });
        
        // Initialize all components with error handling
        try {
            console.log('🚀 Initializing tabs...');
            if (typeof initializeTabs === 'function') {
                initializeTabs();
                console.log('✅ Tabs initialized');
            } else {
                console.error('❌ initializeTabs not available');
            }
        } catch (error) {
            console.error('❌ Error initializing tabs:', error);
        }
        
        try {
            console.log('🚀 Initializing event listeners...');
            if (typeof initializeEventListeners === 'function') {
                initializeEventListeners();
                console.log('✅ Event listeners initialized');
            } else {
                console.error('❌ initializeEventListeners not available');
            }
        } catch (error) {
            console.error('❌ Error initializing event listeners:', error);
        }
        
        try {
            console.log('🚀 Updating statistics...');
            if (typeof updateStats === 'function') {
                updateStats();
                console.log('✅ Statistics updated');
            } else {
                console.error('❌ updateStats not available');
            }
        } catch (error) {
            console.error('❌ Error updating stats:', error);
        }
        
        try {
            console.log('🚀 Initializing form auto-fill functionality...');
            if (typeof generateRegistrationNumber === 'function') {
                generateRegistrationNumber();
                console.log('✅ Registration number generation initialized');
            } else {
                console.error('❌ generateRegistrationNumber not available');
            }
            
            if (typeof updateDateCreated === 'function') {
                updateDateCreated();
                console.log('✅ Date created updated');
            } else {
                console.error('❌ updateDateCreated not available');
            }
            
            // Set current date for start date
            const startDateInput = document.getElementById('start-date');
            if (startDateInput && !startDateInput.value) {
                const now = new Date();
                const isoString = now.toISOString().slice(0, 16); // Format for datetime-local
                startDateInput.value = isoString;
                console.log('✅ Start date set to current time');
            }
        } catch (error) {
            console.error('❌ Error initializing form auto-fill:', error);
        }
        
        try {
            console.log('🚀 Loading caveats data from backend...');
            if (typeof loadCaveatsData === 'function') {
                loadCaveatsData();
                console.log('✅ Caveats data loading initiated');
            } else {
                console.error('❌ loadCaveatsData not available');
                // Fallback to rendering with empty data
                try {
                    if (typeof renderCaveatsList === 'function') renderCaveatsList();
                    if (typeof renderActiveCaveatsList === 'function') renderActiveCaveatsList();
                    if (typeof renderCaveatsTable === 'function') renderCaveatsTable();
                } catch (fallbackError) {
                    console.error('❌ Error in fallback rendering:', fallbackError);
                }
            }
        } catch (error) {
            console.error('❌ Error loading caveats data:', error);
        }
        
        try {
            console.log('🚀 Updating caveat number...');
            if (typeof updateCaveatNumber === 'function') {
                updateCaveatNumber();
                console.log('✅ Caveat number updated');
            } else {
                console.error('❌ updateCaveatNumber not available');
            }
        } catch (error) {
            console.error('❌ Error updating caveat number:', error);
        }
        
        try {
            console.log('🚀 Updating date created...');
            if (typeof updateDateCreated === 'function') {
                updateDateCreated();
                console.log('✅ Date created updated');
            } else {
                console.error('❌ updateDateCreated not available');
            }
        } catch (error) {
            console.error('❌ Error updating date created:', error);
        }
        
        try {
            console.log('🚀 Setting default start date...');
            if (typeof setDefaultStartDate === 'function') {
                setDefaultStartDate();
                console.log('✅ Default start date set');
            } else {
                console.error('❌ setDefaultStartDate not available');
            }
        } catch (error) {
            console.error('❌ Error setting default start date:', error);
        }
        
        console.log('=== Caveat Application Initialization Complete ===');

        // Build initial legal search comment after all fields are set
        if (typeof buildLegalSearchComment === 'function') {
            buildLegalSearchComment();
        }
        
        // Test tab functionality manually
        setTimeout(() => {
            console.log('🔍 Testing tab functionality...');
            const tabTriggers = document.querySelectorAll('.tab-trigger');
            console.log('Tab triggers found:', tabTriggers.length);
            
            tabTriggers.forEach((trigger, index) => {
                const tabName = trigger.getAttribute('data-tab');
                console.log(`Tab ${index}: ${tabName}`, trigger);
            });
        }, 1000);
    });
</script>

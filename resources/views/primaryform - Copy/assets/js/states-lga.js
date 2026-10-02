/**
 * Primary Application Form - States and LGA Handler
 * Manages Nigerian states and Local Government Areas selection
 */

// Nigerian States and LGAs data
let nigerianStatesData = [];

// Initialize states and LGAs when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    loadNigerianStatesData();
});

// Load Nigerian states data from JSON file
async function loadNigerianStatesData() {
    try {
        const response = await fetch('/resources/views/primaryform/nigerian_states.json');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        nigerianStatesData = await response.json();
        
        // Initialize state dropdowns
        initializeStateDropdowns();
        
        console.log('Nigerian states data loaded successfully');
    } catch (error) {
        console.error('Error loading Nigerian states data:', error);
        // Fallback: use inline data if JSON file fails to load
        initializeWithFallbackData();
    }
}

// Initialize state dropdowns with data
function initializeStateDropdowns() {
    const ownerStateSelect = document.getElementById('ownerState');
    const propertyStateSelect = document.getElementById('propertyState');
    
    // Populate owner state dropdown
    if (ownerStateSelect) {
        populateStateSelect(ownerStateSelect);
    }
    
    // Populate property state dropdown
    if (propertyStateSelect) {
        populateStateSelect(propertyStateSelect);
    }
}

// Populate a state select element with options
function populateStateSelect(selectElement) {
    // Clear existing options except the first one
    while (selectElement.children.length > 1) {
        selectElement.removeChild(selectElement.lastChild);
    }
    
    // Add state options
    nigerianStatesData.forEach(state => {
        const option = document.createElement('option');
        option.value = state.name;
        option.textContent = state.name;
        option.setAttribute('data-capital', state.capital);
        option.setAttribute('data-short-name', state.short_name);
        selectElement.appendChild(option);
    });
}

// Handle owner state selection and populate LGA
function selectOwnerLGA(stateSelect) {
    const selectedStateName = stateSelect.value;
    const lgaSelect = document.getElementById('ownerLga');
    
    if (!lgaSelect) {
        console.error('Owner LGA select element not found');
        return;
    }
    
    // Clear LGA options
    lgaSelect.innerHTML = '<option value="">SELECT LGA</option>';
    
    if (selectedStateName) {
        const selectedState = nigerianStatesData.find(state => state.name === selectedStateName);
        
        if (selectedState && selectedState.local_governments) {
            // Populate LGA options
            selectedState.local_governments.forEach(lga => {
                const option = document.createElement('option');
                option.value = lga;
                option.textContent = lga;
                lgaSelect.appendChild(option);
            });
            
            // Enable LGA select
            lgaSelect.disabled = false;
            lgaSelect.classList.remove('opacity-50');
        }
    } else {
        // Disable LGA select if no state selected
        lgaSelect.disabled = true;
        lgaSelect.classList.add('opacity-50');
    }
    
    // Update address display
    if (typeof updateAddressDisplay === 'function') {
        updateAddressDisplay();
    }
}

// Handle property state selection and populate LGA
function selectPropertyLGA(stateSelect) {
    const selectedStateName = stateSelect.value;
    const lgaSelect = document.getElementById('propertyLga');
    
    if (!lgaSelect) {
        console.error('Property LGA select element not found');
        return;
    }
    
    // Clear LGA options
    lgaSelect.innerHTML = '<option value="">Select LGA</option>';
    
    if (selectedStateName) {
        const selectedState = nigerianStatesData.find(state => state.name === selectedStateName);
        
        if (selectedState && selectedState.local_governments) {
            // Populate LGA options
            selectedState.local_governments.forEach(lga => {
                const option = document.createElement('option');
                option.value = lga;
                option.textContent = lga;
                lgaSelect.appendChild(option);
            });
            
            // Enable LGA select
            lgaSelect.disabled = false;
            lgaSelect.classList.remove('opacity-50');
        }
    } else {
        // Don't disable - just visually indicate it's not ready
        // Keep it enabled so validation can check it
        lgaSelect.classList.add('opacity-50');
    }
    
    // Update property address display
    if (typeof updatePropertyAddressDisplay === 'function') {
        updatePropertyAddressDisplay();
    }
}

// Fallback data in case JSON file fails to load
function initializeWithFallbackData() {
    nigerianStatesData = [
        {
            "name": "Abia",
            "short_name": "AB",
            "capital": "Umuahia",
            "local_governments": [
                "Aba North", "Aba South", "Arochukwu", "Bende", "Ikwuano",
                "Isiala Ngwa North", "Isiala Ngwa South", "Isuikwuato",
                "Obi Ngwa", "Ohafia", "Osisioma Ngwa", "Ugwunagbo",
                "Ukwa East", "Ukwa West", "Umuahia North", "Umuahia South", "Umu Nneochi"
            ]
        },
        {
            "name": "Lagos",
            "short_name": "LA",
            "capital": "Ikeja",
            "local_governments": [
                "Agege", "Ajeromi-Ifelodun", "Alimosho", "Amuwo-Odofin", "Apapa",
                "Badagry", "Epe", "Eti-Osa", "Ibeju-Lekki", "Ifako-Ijaiye",
                "Ikeja", "Ikorodu", "Kosofe", "Lagos Island", "Lagos Mainland",
                "Mushin", "Ojo", "Oshodi-Isolo", "Shomolu", "Surulere"
            ]
        },
        // Add more states as needed for fallback
    ];
    
    initializeStateDropdowns();
    console.log('Fallback Nigerian states data initialized');
}

// Get all states
function getAllStates() {
    return nigerianStatesData.map(state => ({
        name: state.name,
        shortName: state.short_name,
        capital: state.capital
    }));
}

// Get LGAs for a specific state
function getLGAsForState(stateName) {
    const state = nigerianStatesData.find(state => state.name === stateName);
    return state ? state.local_governments : [];
}

// Validate state and LGA combination
function isValidStateLGACombination(stateName, lgaName) {
    const state = nigerianStatesData.find(state => state.name === stateName);
    if (!state) return false;
    
    return state.local_governments.includes(lgaName);
}

// Make functions globally accessible
window.selectOwnerLGA = selectOwnerLGA;
window.selectPropertyLGA = selectPropertyLGA;
window.loadNigerianStatesData = loadNigerianStatesData;
window.getAllStates = getAllStates;
window.getLGAsForState = getLGAsForState;
window.isValidStateLGACombination = isValidStateLGACombination;

// Export for use in other modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        selectOwnerLGA,
        selectPropertyLGA,
        loadNigerianStatesData,
        getAllStates,
        getLGAsForState,
        isValidStateLGACombination
    };
}
/**
 * Primary Application Form - States and LGA Handler
 * Manages Nigerian states and Local Government Areas selection
 */

// Nigerian States and LGAs data
let nigerianStatesData = [];

// Initialize states and LGAs when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add a delay to ensure other scripts load first
    setTimeout(() => {
        loadNigerianStatesData();
    }, 500);
});

// Load Nigerian states data from JSON file
async function loadNigerianStatesData() {
    try {
        console.log('Loading Nigerian states data...');
        const response = await fetch('/js/primaryform/nigerian_states.json');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        nigerianStatesData = await response.json();
        
        // Initialize state dropdowns
        initializeStateDropdowns();
        
        console.log('Nigerian states data loaded successfully:', nigerianStatesData.length, 'states loaded');
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
    // Keep whatever is already selected (server-rendered defaults, or a value a
    // prefill set while this data was still loading).
    const previousValue = selectElement.value;

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

    if (previousValue) selectElement.value = previousValue;
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

// Create a reference table for states and LGAs
function createStatesLGATable() {
    if (nigerianStatesData.length === 0) {
        console.warn('No states data available to create table');
        return null;
    }

    const table = document.createElement('table');
    table.className = 'w-full border-collapse border border-gray-300 text-sm';
    
    // Create header
    const thead = document.createElement('thead');
    thead.innerHTML = `
        <tr class="bg-gray-100">
            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">State</th>
            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Capital</th>
            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">LGAs Count</th>
            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Local Government Areas</th>
        </tr>
    `;
    table.appendChild(thead);
    
    // Create body
    const tbody = document.createElement('tbody');
    nigerianStatesData.forEach((state, index) => {
        const row = document.createElement('tr');
        row.className = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
        
        row.innerHTML = `
            <td class="border border-gray-300 px-3 py-2 font-medium">${state.name}</td>
            <td class="border border-gray-300 px-3 py-2">${state.capital}</td>
            <td class="border border-gray-300 px-3 py-2 text-center">${state.local_governments.length}</td>
            <td class="border border-gray-300 px-3 py-2">
                <div class="max-h-24 overflow-y-auto text-xs">
                    ${state.local_governments.join(', ')}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    table.appendChild(tbody);
    
    return table;
}

// Show states and LGAs reference modal
function showStatesLGAReference() {
    const table = createStatesLGATable();
    if (!table) {
        alert('States data not loaded yet. Please wait and try again.');
        return;
    }

    // Create modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-6xl max-h-[90vh] overflow-hidden">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Nigerian States and Local Government Areas Reference</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="overflow-auto max-h-[70vh]">
                ${table.outerHTML}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Add reference button functionality
window.showStatesLGAReference = showStatesLGAReference;
window.createStatesLGATable = createStatesLGATable;

// Export for use in other modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        selectOwnerLGA,
        selectPropertyLGA,
        loadNigerianStatesData,
        getAllStates,
        getLGAsForState,
        isValidStateLGACombination,
        showStatesLGAReference,
        createStatesLGATable
    };
}
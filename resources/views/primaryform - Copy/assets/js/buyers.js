/**
 * Primary Application Form - Buyers List Management
 * Handles buyer addition, removal, and CSV import
 */

const defaultLandUseConfig = {
    isMixed: true,
    fixedValue: null,
    allowedOptions: ['Residential', 'Commercial', 'Industrial'],
};

function isMixedLandUseConfig() {
    return !!resolveLandUseConfig().isMixed;
}

function getAllowedLandUseOptions() {
    return resolveLandUseConfig().allowedOptions || [];
}

function getFixedLandUseValue() {
    const config = resolveLandUseConfig();
    if (config.isMixed) {
        return null;
    }
    return config.fixedValue || config.allowedOptions?.[0] || null;
}

function sanitizeLandUseValue(value) {
    if (!value) {
        return '';
    }
    const trimmed = String(value).trim();
    if (!trimmed) {
        return '';
    }
    const options = getAllowedLandUseOptions();
    const directMatch = options.find(option => option === trimmed);
    if (directMatch) {
        return directMatch;
    }
    const caseInsensitiveMatch = options.find(option => option.toLowerCase() === trimmed.toLowerCase());
    return caseInsensitiveMatch || '';
}

function resolveLandUseConfig() {
    const incoming = window.primaryFormLandUseConfig || {};
    const merged = Object.assign({}, defaultLandUseConfig, incoming);
    merged.allowedOptions = Array.isArray(merged.allowedOptions) && merged.allowedOptions.length > 0
        ? merged.allowedOptions
        : defaultLandUseConfig.allowedOptions;
    if (!merged.isMixed) {
        merged.fixedValue = merged.fixedValue || merged.allowedOptions[0] || null;
    }
    return merged;
}

function createLandUseFieldHtml(index, selectedValue = '') {
    const isMixed = isMixedLandUseConfig();
    const options = getAllowedLandUseOptions();
    const fixedValue = getFixedLandUseValue();
    const effectiveValue = isMixed ? sanitizeLandUseValue(selectedValue) : (fixedValue || options[0] || '');

    let optionsMarkup = '';

    if (isMixed) {
        optionsMarkup += '<option value="">Select Land Use</option>';
        options.forEach(option => {
            const isSelected = effectiveValue === option ? ' selected' : '';
            optionsMarkup += `<option value="${option}"${isSelected}>${option}</option>`;
        });
    } else {
        const displayValue = fixedValue || options[0] || '';
        optionsMarkup += `<option value="${displayValue}" selected>${displayValue}</option>`;
    }

    const selectClasses = [
        'w-full',
        'py-2',
        'px-3',
        'border',
        'border-gray-300',
        'rounded-md',
        'text-sm',
        'focus:ring-2',
        'focus:ring-blue-500',
        'focus:border-blue-500',
        'transition-all',
        'shadow-sm',
        !isMixed ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : '',
    ].filter(Boolean).join(' ');

    const selectAttributes = isMixed
        ? `name="records[${index}][landUse]"`
        : 'data-land-use-display="true"';

    const disabledAttr = isMixed ? '' : 'disabled';

    const hiddenInput = isMixed
        ? ''
        : `<input type="hidden" name="records[${index}][landUse]" value="${fixedValue || options[0] || ''}" data-land-use-hidden="true">`;

    return `
        ${hiddenInput}
        <select ${selectAttributes} class="${selectClasses}" ${disabledAttr} data-land-use-select>
            ${optionsMarkup}
        </select>
    `;
}

function applyLandUseToRow(rowElement, selectedValue = '') {
    if (!rowElement) {
        return;
    }

    const isMixed = isMixedLandUseConfig();
    const options = getAllowedLandUseOptions();
    const fixedValue = getFixedLandUseValue();
    const select = rowElement.querySelector('[data-land-use-select]');
    const hidden = rowElement.querySelector('input[data-land-use-hidden="true"]');

    if (!select) {
        return;
    }

    if (isMixed) {
        const sanitized = sanitizeLandUseValue(selectedValue);
        select.value = sanitized || '';
        if (!sanitized && select.options.length > 0) {
            // Keep placeholder selected when no match
            select.selectedIndex = 0;
        }
    } else {
        const enforcedValue = fixedValue || options[0] || '';
        select.value = enforcedValue;
        if (hidden) {
            hidden.value = enforcedValue;
        }
    }
}

function initializeLandUseFields() {
    document.querySelectorAll('.buyer-row').forEach(row => applyLandUseToRow(row));
}

let buyersCount = 1;

// Function to add a new buyer row
function addBuyer() {
    console.log('Adding buyer...');
    const container = document.getElementById('buyers-container');
    const newBuyerHtml = createBuyerRowHtml(buyersCount);
    container.insertAdjacentHTML('beforeend', newBuyerHtml);
    const newRow = container.lastElementChild;
    applyLandUseToRow(newRow);
    buyersCount++;
    updateRemoveButtons();
    
    // Reinitialize Lucide icons for new elements
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Function to remove a buyer row
function removeBuyer(button) {
    const buyerRow = button.closest('.buyer-row');
    buyerRow.remove();
    buyersCount--;
    updateBuyerNumbers();
    updateRemoveButtons();
}

function ensureBuyerRowCount(targetCount) {
    const container = document.getElementById('buyers-container');
    if (!container) {
        return;
    }

    const desired = Math.max(1, Number.isFinite(Number(targetCount)) ? Number(targetCount) : 1);
    let currentRows = container.querySelectorAll('.buyer-row').length;

    while (currentRows < desired) {
        addBuyer();
        currentRows = container.querySelectorAll('.buyer-row').length;
    }

    while (currentRows > desired && currentRows > 1) {
        const lastRow = container.querySelector('.buyer-row:last-of-type');
        if (!lastRow) {
            break;
        }

        lastRow.remove();
        currentRows--;
        buyersCount = Math.max(1, buyersCount - 1);
    }

    buyersCount = Math.max(1, currentRows);
    updateBuyerNumbers();
    updateRemoveButtons();
    initializeLandUseFields();

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Function to create HTML for a new buyer row
function createBuyerRowHtml(index) {
    return `
        <div class="border border-gray-200 rounded-lg p-4 mb-4 bg-white buyer-row" data-index="${index}">
            <div class="flex justify-between items-start mb-4">
                <h4 class="text-sm font-medium text-gray-700">Buyer <span class="buyer-number">${index + 1}</span></h4>
                <button type="button" 
                        class="bg-red-500 text-white p-1.5 rounded-md hover:bg-red-600 flex items-center justify-center remove-buyer" 
                        onclick="removeBuyer(this)">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                    <select name="records[${index}][buyerTitle]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase">
                        <option value="">Select title</option>
                        <option value="Mr.">Mr.</option>
                        <option value="Mrs.">Mrs.</option>
                        <option value="Chief">Chief</option>
                        <option value="Master">Master</option>
                        <option value="Capt">Capt</option>
                        <option value="Coln">Coln</option>
                        <option value="HRH">HRH</option>
                        <option value="Mallam">Mallam</option>
                        <option value="Prof">Prof</option>
                        <option value="Dr.">Dr.</option>
                        <option value="Alhaji">Alhaji</option>
                        <option value="Hajia">Hajia</option>
                        <option value="High Chief">High Chief</option>
                        <option value="Senator">Senator</option>
                        <option value="Messr">Messr</option>
                        <option value="Honorable">Honorable</option>
                        <option value="Miss">Miss</option>
                        <option value="Barr.">Barr.</option>
                        <option value="Arc.">Arc.</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="records[${index}][firstName]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter First Name" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                    <input type="text" name="records[${index}][middleName]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter Middle Name" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Surname <span class="text-red-500">*</span></label>
                    <input type="text" name="records[${index}][surname]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter Surname" oninput="this.value = this.value.toUpperCase()">
                </div>
            </div>

            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Land Use <span class="text-red-500">*</span></label>
                    ${createLandUseFieldHtml(index)}
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Number <span class="text-red-500">*</span></label>
                    <input type="text" name="records[${index}][unit_no]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter Unit Number" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Section Number <span class="text-red-500">*</span></label>
                    <input type="text" name="records[${index}][sectionNumber]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm" placeholder="Enter Section Number">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Measurement (Optional)</label>
                    <input type="text" name="records[${index}][unitMeasurement]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm" placeholder="e.g. 50sqm">
                </div>
            </div>
        </div>
    `;
}

// Function to update buyer numbers
function updateBuyerNumbers() {
    const buyerRows = document.querySelectorAll('.buyer-row');
    buyerRows.forEach((row, index) => {
        const buyerNumber = row.querySelector('.buyer-number');
        if (buyerNumber) {
            buyerNumber.textContent = index + 1;
        }
        
        // Update form field names
        const inputs = row.querySelectorAll('input, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name && name.includes('records[')) {
                const newName = name.replace(/records\[\d+\]/, `records[${index}]`);
                input.setAttribute('name', newName);
            }
        });
        
        row.setAttribute('data-index', index);
    });
}

// Function to update remove buttons visibility
function updateRemoveButtons() {
    const buyerRows = document.querySelectorAll('.buyer-row');
    buyerRows.forEach((row, index) => {
        const removeButton = row.querySelector('.remove-buyer');
        if (removeButton) {
            removeButton.style.display = buyerRows.length > 1 ? 'flex' : 'none';
        }
    });
}

// Function to handle CSV import
function handleCsvImport() {
    const fileInput = document.getElementById('csvFileInput');
    const resultDiv = document.getElementById('csv-result');

    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        resultDiv.innerHTML = '<div class="text-red-600 text-sm bg-red-50 border border-red-200 p-2 rounded">Please select a CSV file first.</div>';
        return;
    }

    const file = fileInput.files[0];
    if (!file.name.toLowerCase().endsWith('.csv')) {
        resultDiv.innerHTML = '<div class="text-red-600 text-sm bg-red-50 border border-red-200 p-2 rounded">Please select a valid CSV file.</div>';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const text = e.target.result;
            const lines = text.split('\n').filter(line => line.trim() !== '');
            
            if (lines.length < 2) {
                resultDiv.innerHTML = '<div class="text-yellow-700 bg-yellow-50 border border-yellow-200 p-2 rounded text-sm">CSV file must have at least a header row and one data row.</div>';
                return;
            }

            // Parse CSV with proper comma handling
            function parseCSVLine(line) {
                const result = [];
                let current = '';
                let inQuotes = false;
                
                for (let i = 0; i < line.length; i++) {
                    const char = line[i];
                    if (char === '"') {
                        inQuotes = !inQuotes;
                    } else if (char === ',' && !inQuotes) {
                        result.push(current.trim());
                        current = '';
                    } else {
                        current += char;
                    }
                }
                result.push(current.trim());
                return result.map(v => v.replace(/"/g, ''));
            }
            
            const headers = parseCSVLine(lines[0]).map(h => h.toLowerCase());
            const buyers = [];
            
            console.log('CSV Headers found:', headers);

            for (let i = 1; i < lines.length; i++) {
                const values = parseCSVLine(lines[i]);
                const buyer = {};
                
                console.log(`Processing line ${i}:`, values);
                
                headers.forEach((header, index) => {
                    const value = values[index] || '';
                    console.log(`  Header: "${header}" -> Value: "${value}"`);
                    switch(header) {
                        case 'title':
                            buyer.buyerTitle = value;
                            break;
                        case 'first name':
                        case 'first_name':
                            buyer.firstName = value.toUpperCase();
                            break;
                        case 'middle name':
                        case 'middle_name':
                            buyer.middleName = value.toUpperCase();
                            break;
                        case 'surname':
                        case 'last name':
                        case 'last_name':
                            buyer.surname = value.toUpperCase();
                            break;
                        case 'unit number':
                        case 'unit_no':
                            buyer.unit_no = value.toUpperCase();
                            break;
                        case 'land use':
                        case 'land_use':
                        case 'landuse':
                            const normalizedLandUse = sanitizeLandUseValue(value);
                            buyer.landUse = normalizedLandUse;
                            console.log(`Parsed land use: "${value}" -> normalized: "${normalizedLandUse}" for buyer`);
                            break;
                        case 'unit measurement':
                        case 'unit_measurement':
                            buyer.unitMeasurement = value;
                            break;
                        case 'section number':
                        case 'section_number':
                        case 'section':
                            buyer.sectionNumber = value;
                            break;
                    }
                });
                
                if (buyer.firstName || buyer.surname) {
                    console.log(`Final buyer object:`, buyer);
                    buyers.push(buyer);
                }
            }

            if (buyers.length > 0) {
                populateBuyersFromCsv(buyers);
                resultDiv.innerHTML = `<div class="text-green-700 bg-green-50 border border-green-200 p-2 rounded text-sm">Successfully imported ${buyers.length} buyer(s) from CSV.</div>`;
            } else {
                resultDiv.innerHTML = '<div class="text-yellow-700 bg-yellow-50 border border-yellow-200 p-2 rounded text-sm">No valid buyer data found in CSV.</div>';
            }
        } catch (err) {
            console.error('CSV parse error:', err);
            resultDiv.innerHTML = '<div class="text-red-700 bg-red-50 border border-red-200 p-2 rounded text-sm">Error parsing CSV file. Please check the format.</div>';
        }
    };

    reader.onerror = function() {
        resultDiv.innerHTML = '<div class="text-red-700 bg-red-50 border border-red-200 p-2 rounded text-sm">Failed to read the file.</div>';
    };

    reader.readAsText(file);
}

// Function to populate buyers from CSV data
function populateBuyersFromCsv(buyers) {
    const container = document.getElementById('buyers-container');
    container.innerHTML = ''; // Clear existing buyers
    
    buyers.forEach((buyer, index) => {
        const buyerHtml = createBuyerRowHtml(index);
        console.log(`Generated HTML for buyer ${index}:`, buyerHtml.substring(0, 200) + '...');
        container.insertAdjacentHTML('beforeend', buyerHtml);
        
        // Populate the fields
        const buyerRow = container.children[index];
        console.log(`Buyer row ${index} DOM element:`, buyerRow);
        const titleSelect = buyerRow.querySelector(`select[name="records[${index}][buyerTitle]"]`);
        const firstNameInput = buyerRow.querySelector(`input[name="records[${index}][firstName]"]`);
        const middleNameInput = buyerRow.querySelector(`input[name="records[${index}][middleName]"]`);
        const surnameInput = buyerRow.querySelector(`input[name="records[${index}][surname]"]`);
        const unitNoInput = buyerRow.querySelector(`input[name="records[${index}][unit_no]"]`);
        const landUseSelect = buyerRow.querySelector('[data-land-use-select]');
        const landUseHidden = buyerRow.querySelector('input[data-land-use-hidden="true"]');
        const unitMeasurementInput = buyerRow.querySelector(`input[name="records[${index}][unitMeasurement]"]`);
        const sectionNumberInput = buyerRow.querySelector(`input[name="records[${index}][sectionNumber]"]`);
        
        if (titleSelect) titleSelect.value = buyer.buyerTitle || '';
        if (firstNameInput) firstNameInput.value = buyer.firstName || '';
        if (middleNameInput) middleNameInput.value = buyer.middleName || '';
        if (surnameInput) surnameInput.value = buyer.surname || '';
        if (unitNoInput) unitNoInput.value = buyer.unit_no || '';
        applyLandUseToRow(buyerRow, buyer.landUse);
        if (unitMeasurementInput) unitMeasurementInput.value = buyer.unitMeasurement || '';
        if (sectionNumberInput) sectionNumberInput.value = buyer.sectionNumber || '';
        if (landUseHidden && !isMixedLandUseConfig()) {
            landUseHidden.value = getFixedLandUseValue() || '';
        }
    });
    
    buyersCount = buyers.length;
    updateRemoveButtons();
    
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Immediate debug check after population
    setTimeout(() => {
        console.log('=== Immediate post-CSV import check ===');
        debugLandUseFields();
    }, 100);
}

// Debug function to check land use fields
function debugLandUseFields() {
    console.log('=== Debugging Land Use Fields ===');
    const buyerRows = document.querySelectorAll('.buyer-row');
    console.log(`Found ${buyerRows.length} buyer rows`);
    
    buyerRows.forEach((row, index) => {
        const landUseSelect = row.querySelector('[data-land-use-select]');
        console.log(`Buyer ${index + 1}:`);
        console.log(`  - Land use field exists:`, !!landUseSelect);
        if (landUseSelect) {
            console.log(`  - Field name:`, landUseSelect.name);
            console.log(`  - Field value:`, landUseSelect.value);
            console.log(`  - Field visible:`, landUseSelect.offsetParent !== null);
            console.log(`  - Field options count:`, landUseSelect.options.length);
        }
    });
    console.log('=== End Debug ===');
}

// Make functions globally accessible
window.addBuyer = addBuyer;
window.removeBuyer = removeBuyer;
window.handleCsvImport = handleCsvImport;
window.debugLandUseFields = debugLandUseFields;
window.ensureBuyerRowCount = ensureBuyerRowCount;

document.addEventListener('DOMContentLoaded', () => {
    initializeLandUseFields();
});
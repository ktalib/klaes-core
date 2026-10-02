@php
    $renderJointInspectionModalMarkup = $renderJointInspectionModalMarkup ?? true;
@endphp

@if ($renderJointInspectionModalMarkup)
<!-- Joint Site Inspection Modal -->
<div id="jointInspectionModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900 bg-opacity-50"></div>
    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div id="jsiModalHeader" class="flex items-center justify-between px-5 py-3 border-b">
            <h3 id="jsiModalTitle" class="text-lg font-semibold text-gray-800">Joint Site Inspection Report Details</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-joint-inspection-dismiss>
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        @include('programmes.partials.joint_site_inspection_form', [
            'editorMode' => 'modal',
            'formClasses' => 'p-6 space-y-6'
        ])
    </div>
</div>
@endif

@include('components.global-fileno-modal')

<script>
// Make shared utilities data available to modal if it exists in parent page
if (typeof sharedUtilitiesOptions !== 'undefined') {
    window.sharedUtilitiesOptions = sharedUtilitiesOptions;
}

if (typeof window.populateUnitSpecificData !== 'function') {
    window.populateUnitSpecificData = function(unitData = {}) {
        if (!unitData || typeof unitData !== 'object') {
            return;
        }

        const unitField = document.getElementById('jointInspectionUnitNumber');
        if (unitField) {
            const current = typeof unitField.value === 'string' ? unitField.value.trim() : '';
            const candidate = unitData.unit_number ?? unitData.unitNumber ?? unitData.unitNo ?? null;
            const normalized = candidate === null || typeof candidate === 'undefined'
                ? ''
                : String(candidate).trim();

            if (normalized !== '' && current === '') {
                unitField.value = normalized;
            }
        }

        const blockField = document.getElementById('jointInspectionSections');
        if (blockField) {
            const current = typeof blockField.value === 'string' ? blockField.value.trim() : '';
            const candidate = unitData.block_number ?? unitData.blockNumber ?? null;
            const normalized = candidate === null || typeof candidate === 'undefined'
                ? ''
                : String(candidate).trim();

            if (normalized !== '' && current === '') {
                blockField.value = normalized;
            }
        }

        const dimensionField = document.getElementById('jointInspectionUnitDimension');
        if (dimensionField) {
            const current = typeof dimensionField.value === 'string' ? dimensionField.value.trim() : '';
            const candidate = unitData.unit_dimension
                ?? unitData.unitDimension
                ?? unitData.unit_size
                ?? unitData.unitSize
                ?? null;
            const normalized = candidate === null || typeof candidate === 'undefined'
                ? ''
                : String(candidate).trim();

            if (normalized !== '' && current === '') {
                dimensionField.value = normalized;
            }
        }

        const applicantField = document.getElementById('jointInspectionApplicant');
        if (applicantField) {
            const current = typeof applicantField.value === 'string' ? applicantField.value.trim() : '';
            const candidate = unitData.buyer_name ?? unitData.buyerName ?? null;
            const normalized = candidate === null || typeof candidate === 'undefined'
                ? ''
                : String(candidate).trim();

            if (normalized !== '' && current === '') {
                applicantField.value = normalized;
            }
        }
    };
}

const populateUnitSpecificData = window.populateUnitSpecificData;

    // Function to fetch shared utilities data from database
    async function fetchSharedUtilitiesData(applicationId, subApplicationId = null) {
        try {
            console.log('DEBUG - Fetching shared utilities data for:', { applicationId, subApplicationId });
            
            const url = `/api/shared-utilities/${applicationId}${subApplicationId ? `/${subApplicationId}` : ''}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                console.log('DEBUG - Shared utilities API response:', data);
                
                if (data.success && data.data && data.data.length > 0) {
                    return data.data;
                }
            } else {
                console.warn('DEBUG - Shared utilities API request failed:', response.status);
            }
            
            return [];
        } catch (error) {
            console.error('DEBUG - Error fetching shared utilities data:', error);
            return [];
        }
    }

    // Function to convert shared utilities data to measurement entries JSON format
    function convertSharedUtilitiesToMeasurementEntries(sharedUtilitiesData) {
        if (!Array.isArray(sharedUtilitiesData) || sharedUtilitiesData.length === 0) {
            return [];
        }

        return sharedUtilitiesData.map((utility, index) => ({
            sn: index + 1,
            description: utility.utility_type || '',
            count: utility.count !== undefined && utility.count !== null && String(utility.count).trim() !== ''
                ? String(utility.count).trim()
                : '1',
            measurement_dimension: utility.measurement_dimension !== undefined && utility.measurement_dimension !== null
                ? String(utility.measurement_dimension)
                : '',
            dimension: utility.dimension !== undefined && utility.dimension !== null
                ? String(utility.dimension)
                : ''
        }));
    }

    // Function to populate measurement entries from shared utilities
    function populateMeasurementEntriesFromSharedUtilities(sharedUtilitiesData) {
        const measurementEntries = convertSharedUtilitiesToMeasurementEntries(sharedUtilitiesData);
        
        if (measurementEntries.length > 0) {
            console.log('DEBUG - Converting shared utilities to measurement entries:', measurementEntries);
            
            // Store in global state
            window.measurementEntriesState = measurementEntries;
            
            // Populate the UI
            renderMeasurementEntries();
            
            // Update the existing_site_measurement_entries hidden field
            updateExistingSiteMeasurementEntries();
            
            return measurementEntries;
        }
        
        return [];
    }

document.addEventListener('DOMContentLoaded', function() {
    let measurementEntryEventsBound = false;
    // Function to handle boundary description visibility and field labels based on application type
    function handleBoundaryDescriptionVisibility() {
        const subApplicationIdField = document.getElementById('modal_sub_application_id');
        const boundarySection = document.getElementById('boundaryDescriptionSection');
        const plotNumberLabel = document.getElementById('plotNumberLabel');
        const unitNumberWrapper = document.getElementById('unitNumberWrapper');
        const unitNumberField = document.getElementById('jointInspectionUnitNumber');
        const sectionsCountLabel = document.getElementById('sectionsCountLabel');
        const sectionsCountInput = document.getElementById('jointInspectionSections');
        const unitDimensionWrapper = document.getElementById('unitDimensionWrapper');
        const unitDimensionField = document.getElementById('jointInspectionUnitDimension');
        
        if (subApplicationIdField && boundarySection) {
            // Hide boundary description for units (sub-applications)
            const subApplicationId = subApplicationIdField.value;
            const isUnitApplication = subApplicationId && subApplicationId.trim() !== '';
            
            if (isUnitApplication) {
                boundarySection.style.display = 'block';
                
                if (unitNumberWrapper) unitNumberWrapper.classList.remove('hidden');
                if (sectionsCountLabel) sectionsCountLabel.textContent = 'Block Number';
                if (sectionsCountInput) {
                    sectionsCountInput.placeholder = 'Block number';
                    sectionsCountInput.type = 'text';
                }
                if (unitDimensionWrapper) {
                    unitDimensionWrapper.classList.remove('hidden');
                }
            } else {
                boundarySection.style.display = 'block';
                
                if (unitNumberWrapper) unitNumberWrapper.classList.add('hidden');
                if (unitNumberField) {
                    unitNumberField.value = '';
                }
                if (sectionsCountLabel) sectionsCountLabel.textContent = 'No. of Sections';
                if (sectionsCountInput) {
                    sectionsCountInput.placeholder = 'Sections count';
                    sectionsCountInput.type = 'text';
                }
                if (unitDimensionWrapper) {
                    unitDimensionWrapper.classList.add('hidden');
                }
                if (unitDimensionField) {
                    unitDimensionField.value = '';
                }
            }
        }
    }
    
    // Function to format date for HTML date input
    function formatDateForInput(dateValue) {
        if (!dateValue) {
            return '';
        }

        if (typeof dateValue === 'string') {
            const trimmed = dateValue.trim();
            if (trimmed === '') {
                return '';
            }

            // ISO8601 and database timestamps (e.g. 2025-10-14T00:00:00.000000Z)
            const isoMatch = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (isoMatch) {
                return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
            }

            // Common human formats (e.g. 14/10/2025 or 14-10-2025)
            const altMatch = trimmed.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
            if (altMatch) {
                const day = altMatch[1].padStart(2, '0');
                const month = altMatch[2].padStart(2, '0');
                const year = altMatch[3];
                return `${year}-${month}-${day}`;
            }
        }

        const parsedDate = (() => {
            if (dateValue instanceof Date && !isNaN(dateValue.getTime())) {
                return dateValue;
            }

            const constructed = new Date(dateValue);
            return isNaN(constructed.getTime()) ? null : constructed;
        })();

        if (!parsedDate) {
            console.warn('Invalid date format:', dateValue);
            return '';
        }

        const year = parsedDate.getUTCFullYear();
        const month = String(parsedDate.getUTCMonth() + 1).padStart(2, '0');
        const day = String(parsedDate.getUTCDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Function to populate shared utilities checkboxes
    function populateSharedUtilities(utilities = []) {
        const container = document.getElementById('sharedUtilitiesContainer');
        if (!container) return;
        
        // Get utilities from multiple sources
        let availableUtilities = [];
        let selectedUtilities = [];
        
        // First try from passed utilities parameter
        if (utilities && utilities.length > 0) {
            availableUtilities = utilities;
            selectedUtilities = utilities; // If passed explicitly, assume they are selected
        } 
        // Then try from window.sharedUtilitiesOptions (from backend query)
        else if (window.sharedUtilitiesOptions && window.sharedUtilitiesOptions.length > 0) {
            availableUtilities = window.sharedUtilitiesOptions;
            // If utilities come from database, they should be checked by default
            selectedUtilities = window.jointInspectionDefaults?.shared_utilities || 
                               window.jointInspectionSavedReport?.shared_utilities || 
                               window.sharedUtilitiesOptions; // Default to all from database
        }
        // Try to get from parent page's sharedUtilitiesOptions variable
        else if (typeof sharedUtilitiesOptions !== 'undefined' && sharedUtilitiesOptions.length > 0) {
            availableUtilities = sharedUtilitiesOptions;
            selectedUtilities = window.jointInspectionDefaults?.shared_utilities || 
                               window.jointInspectionSavedReport?.shared_utilities || 
                               sharedUtilitiesOptions; // Default to all if no specific selection
        }
        // Then try from window data
        else if (window.jointInspectionDefaults?.shared_utilities?.length > 0) {
            availableUtilities = window.jointInspectionDefaults.shared_utilities;
            selectedUtilities = window.jointInspectionDefaults.shared_utilities;
        }
        // Then try from saved report data
        else if (window.jointInspectionSavedReport?.shared_utilities?.length > 0) {
            availableUtilities = window.jointInspectionSavedReport.shared_utilities;
            selectedUtilities = window.jointInspectionSavedReport.shared_utilities;
        }
        
        console.log('DEBUG - Available utilities:', availableUtilities);
        console.log('DEBUG - Selected utilities:', selectedUtilities);
        console.log('DEBUG - Window data:', {
            sharedUtilitiesOptions: window.sharedUtilitiesOptions,
            jointInspectionDefaults: window.jointInspectionDefaults,
            jointInspectionSavedReport: window.jointInspectionSavedReport
        });
        
        // If no utilities found, show message
        if (!availableUtilities || availableUtilities.length === 0) {
            container.innerHTML = '<p class="text-sm text-gray-500">No shared utilities recorded for this application.</p>';
            return;
        }
        
        const checkboxesHtml = availableUtilities.map(utility => {
            // Check if this utility is selected
            let isSelected = false;
            if (Array.isArray(selectedUtilities)) {
                isSelected = selectedUtilities.includes(utility);
            } else if (selectedUtilities === utility) {
                isSelected = true;
            }
            
            // If utilities come from database (sharedUtilitiesOptions), they should be checked by default
            if (!isSelected && window.sharedUtilitiesOptions && window.sharedUtilitiesOptions.includes(utility)) {
                isSelected = true;
            }
            
            const displayName = utility.replace(/[_-]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            return `
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="shared_utilities[]" value="${utility}" 
                           class="rounded border-gray-300 text-green-600 focus:ring-green-500" 
                           ${isSelected ? 'checked' : ''}>
                    <span class="text-sm text-gray-700">${displayName}</span>
                </label>
            `;
        }).join('');
        
        container.innerHTML = checkboxesHtml;
    }
    
    // Function to fix date field formatting
    function fixDateFieldFormatting() {
        const inspectionDateField = document.getElementById('jointInspectionDate');
        if (inspectionDateField && inspectionDateField.value) {
            const currentValue = inspectionDateField.value;
            const formattedDate = formatDateForInput(currentValue);
            if (formattedDate && formattedDate !== currentValue) {
                inspectionDateField.value = formattedDate;
                console.log('DEBUG - Fixed inspection date from:', currentValue, 'to:', formattedDate);
            }
        }
    }
    
    // Monitor for changes to the date field value
    const inspectionDateField = document.getElementById('jointInspectionDate');
    if (inspectionDateField) {
        // Use MutationObserver to watch for value changes
        const dateObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    fixDateFieldFormatting();
                }
            });
        });
        
        dateObserver.observe(inspectionDateField, {
            attributes: true,
            attributeFilter: ['value']
        });
        
        // Also check when the field changes
        inspectionDateField.addEventListener('input', fixDateFieldFormatting);
        inspectionDateField.addEventListener('change', fixDateFieldFormatting);
        
        // Fix it immediately if there's already a value
        setTimeout(fixDateFieldFormatting, 100);
    }
    
    // Override the original populateJointInspectionForm if it exists
    if (window.populateJointInspectionForm) {
        const originalPopulate = window.populateJointInspectionForm;
        window.populateJointInspectionForm = function(values = {}) {
            // Call original function first
            originalPopulate(values);
            
            // Then fix the date field and repopulate shared utilities
            setTimeout(() => {
                const form = document.getElementById('jointInspectionForm');
                const inspectionDateField = form?.querySelector('[name="inspection_date"]');
                if (inspectionDateField && values.inspection_date) {
                    const formattedDate = formatDateForInput(values.inspection_date);
                    if (formattedDate) {
                        inspectionDateField.value = formattedDate;
                        console.log('DEBUG - Set inspection date to:', formattedDate);
                    }
                }
                
                // Handle unit-specific data population
                const subApplicationId = document.getElementById('modal_sub_application_id')?.value;
                if (subApplicationId && subApplicationId.trim() !== '') {
                    // Update labels and visibility for unit applications
                    handleBoundaryDescriptionVisibility();
                    
                    // First populate from existing values if available
                    populateUnitSpecificData(values);
                    
                    // Then try to get fresh unit data from preloaded data
                    if (window.unitDataOptions && window.unitDataOptions[subApplicationId]) {
                        const unitData = window.unitDataOptions[subApplicationId];
                        populateUnitSpecificData(unitData);
                        console.log('DEBUG - Populated unit data from preloaded data:', unitData);
                    } else {
                        console.log('DEBUG - No preloaded unit data available for subApplicationId:', subApplicationId);
                    }
                }
                
                // Fetch and populate shared utilities from database
                const applicationId = document.getElementById('modal_application_id')?.value;
                const currentSubApplicationId = document.getElementById('modal_sub_application_id')?.value;
                
                if (applicationId) {
                    // First try to fetch shared utilities data from database
                    fetchSharedUtilitiesData(applicationId, currentSubApplicationId).then(sharedUtilitiesData => {
                        if (sharedUtilitiesData && sharedUtilitiesData.length > 0) {
                            console.log('DEBUG - Found shared utilities from database:', sharedUtilitiesData);
                            
                            // Convert to measurement entries and populate
                            populateMeasurementEntriesFromSharedUtilities(sharedUtilitiesData);
                            
                            // Also populate the utility types for checkbox display
                            const utilityTypes = sharedUtilitiesData.map(u => u.utility_type).filter(Boolean);
                            if (utilityTypes.length > 0) {
                                populateSharedUtilities(utilityTypes);
                            }
                        } else {
                            console.log('DEBUG - No shared utilities found in database, using existing values');
                            
                            // Fall back to existing data
                            if (values.shared_utilities) {
                                populateSharedUtilities(values.shared_utilities);
                                console.log('DEBUG - Repopulated shared utilities from existing values:', values.shared_utilities);
                            }
                        }
                    }).catch(error => {
                        console.error('DEBUG - Error fetching shared utilities:', error);
                        
                        // Fall back to existing data
                        if (values.shared_utilities) {
                            populateSharedUtilities(values.shared_utilities);
                            console.log('DEBUG - Repopulated shared utilities from existing values (fallback):', values.shared_utilities);
                        }
                    });
                } else {
                    // No application ID, use existing data
                    if (values.shared_utilities) {
                        populateSharedUtilities(values.shared_utilities);
                        console.log('DEBUG - Repopulated shared utilities:', values.shared_utilities);
                    }
                }
                
                // Repopulate measurement entries with existing data
                let parsedMeasurementEntries = [];
                if (values.existing_site_measurement_entries) {
                    if (Array.isArray(values.existing_site_measurement_entries)) {
                        parsedMeasurementEntries = values.existing_site_measurement_entries;
                    } else if (typeof values.existing_site_measurement_entries === 'string') {
                        try {
                            parsedMeasurementEntries = JSON.parse(values.existing_site_measurement_entries);
                        } catch (error) {
                            console.warn('DEBUG - Unable to parse existing_site_measurement_entries JSON:', error, values.existing_site_measurement_entries);
                        }
                    } else if (typeof values.existing_site_measurement_entries === 'object') {
                        parsedMeasurementEntries = Object.values(values.existing_site_measurement_entries);
                    }
                }

                if (parsedMeasurementEntries && parsedMeasurementEntries.length > 0) {
                    window.measurementEntriesState = parsedMeasurementEntries.map((entry, index) => ({
                        sn: index + 1,
                        description: entry.description || entry.utility_type || '',
                        count: entry.count !== undefined && entry.count !== null
                            ? String(entry.count)
                            : (entry.quantity ? String(entry.quantity) : ''),
                        measurement_dimension: entry.measurement_dimension !== undefined && entry.measurement_dimension !== null
                            ? String(entry.measurement_dimension)
                            : (entry.dimension_display ? String(entry.dimension_display) : ''),
                        dimension: typeof entry.dimension === 'number' ? String(entry.dimension) : (entry.dimension || '')
                    }));
                } else if (values.shared_utilities_data && Array.isArray(values.shared_utilities_data)) {
                    // Handle database structure from shared_utilities table
                    window.measurementEntriesState = values.shared_utilities_data.map((entry, index) => ({
                        sn: index + 1,
                        description: entry.utility_type || '',
                        count: entry.count !== undefined && entry.count !== null && String(entry.count).trim() !== ''
                            ? String(entry.count).trim()
                            : '1',
                        measurement_dimension: entry.measurement_dimension !== undefined && entry.measurement_dimension !== null
                            ? String(entry.measurement_dimension)
                            : '',
                        dimension: typeof entry.dimension === 'number' ? String(entry.dimension) : (entry.dimension || '')
                    }));
                }
                // If no existing measurement entries but we have shared utilities from database, use those
                else if (window.sharedUtilitiesOptions && window.sharedUtilitiesOptions.length > 0) {
                    window.measurementEntriesState = window.sharedUtilitiesOptions.map((utility, index) => ({
                        sn: index + 1,
                        description: utility.replace(/[_-]/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                        count: '1',
                        measurement_dimension: '',
                        dimension: '' // Empty for user to fill
                    }));
                    console.log('DEBUG - Created measurement entries from shared utilities:', window.measurementEntriesState);
                }
                
                if (window.measurementEntriesState && window.measurementEntriesState.length > 0) {
                    renderMeasurementEntries();
                    console.log('DEBUG - Repopulated measurement entries:', window.measurementEntriesState);
                }
                
                // If loading existing data, set appropriate button states
                if (values && Object.keys(values).length > 0) {
                    // Check if already submitted - if so, disable all buttons
                    if (values.is_submitted === '1' || values.is_submitted === 1 || values.submitted_at) {
                        disableAllButtons();
                        console.log('DEBUG - Report already submitted, all buttons disabled');
                    }
                    // Check if generated but not submitted - enable all except Submit is ready
                    else if (values.is_generated === '1' || values.is_generated === 1 || values.generated_at) {
                        enableSaveButton();
                        enableGenerateButton();
                        enableSubmitButton();
                        console.log('DEBUG - Report already generated, all buttons enabled');
                    }
                    // If saved but not generated - enable Save and Generate
                    else {
                        enableSaveButton();
                        enableGenerateButton();
                        disableSubmitButton();
                        console.log('DEBUG - Report saved but not generated, Save and Generate enabled');
                    }
                } else {
                    // For new reports - disable all buttons initially
                    disableSaveButton();
                    disableGenerateButton();
                    disableSubmitButton();
                }
            }, 50);
        };
    }
    
    // Function to enable Save button
    function enableSaveButton() {
        const saveBtn = document.getElementById('jointInspectionSave');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            saveBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
        }
    }
    
    // Function to disable Save button
    function disableSaveButton() {
        const saveBtn = document.getElementById('jointInspectionSave');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            saveBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        }
    }
    
    // Function to enable Generate button after save
    function enableGenerateButton() {
        const generateBtn = document.getElementById('jointInspectionGenerate');
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            generateBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        }
    }
    
    // Function to disable Generate button
    function disableGenerateButton() {
        const generateBtn = document.getElementById('jointInspectionGenerate');
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            generateBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        }
    }
    
    // Function to enable Submit button after generate
    function enableSubmitButton() {
        const submitBtn = document.getElementById('jointInspectionSubmit');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            submitBtn.classList.add('bg-purple-600', 'hover:bg-purple-700');
        }
    }
    
    // Function to disable Submit button
    function disableSubmitButton() {
        const submitBtn = document.getElementById('jointInspectionSubmit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
            submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        }
    }
    
    // Function to disable all buttons after submission
    function disableAllButtons() {
        const saveBtn = document.getElementById('jointInspectionSave');
        const generateBtn = document.getElementById('jointInspectionGenerate');
        const submitBtn = document.getElementById('jointInspectionSubmit');
        
        // Disable Save button
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            saveBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        }
        
        // Disable Generate button
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            generateBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        }
        
        // Disable Submit button
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
            submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        }
        
        // Update status indicator
        const statusText = document.getElementById('statusText');
        const statusIndicator = document.querySelector('#statusIndicator .w-2');
        if (statusText) statusText.textContent = 'Submitted';
        if (statusIndicator) {
            statusIndicator.classList.remove('bg-gray-400');
            statusIndicator.classList.add('bg-green-500');
        }
    }

    function markReportAsDirty() {
        if (window.jsiWorkflowState && !window.jsiWorkflowState.isSubmitted) {
            window.jsiWorkflowState.isSaved = false;
            window.jsiWorkflowState.isGenerated = false;
            if (typeof window.updateJointInspectionWorkflowStatus === 'function') {
                window.updateJointInspectionWorkflowStatus();
            }
        }
    }
    
    function resolveJointInspectionIdentifiers() {
        let applicationId = document.getElementById('modal_application_id')?.value || '';
        let subApplicationId = document.getElementById('modal_sub_application_id')?.value || '';

        if (!applicationId) {
            applicationId = window.jointInspectionSavedReport?.application_id
                || window.jointInspectionDefaults?.application_id
                || window.currentApplicationId
                || '';
        }

        if (!subApplicationId) {
            subApplicationId = window.jointInspectionSavedReport?.sub_application_id
                || window.jointInspectionDefaults?.sub_application_id
                || window.currentSubApplicationId
                || '';
        }

        return {
            applicationId: applicationId ? String(applicationId) : '',
            subApplicationId: subApplicationId ? String(subApplicationId) : ''
        };
    }

    async function postJointInspectionStatusUpdate(payload) {
        const form = document.getElementById('jointInspectionForm');
        const token = form?.querySelector('input[name="_token"]')?.value;

        if (!token) {
            throw new Error('Security token missing. Please refresh the page and try again.');
        }

        const response = await fetch('{{ route('joint-inspection.update-status') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(payload)
        });

        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            // Ignore JSON parse errors; will handle below
        }

        if (!response.ok || !data.success) {
            const message = data.message || 'Failed to update joint inspection status.';
            throw new Error(message);
        }

        return data;
    }

    async function updateGeneratedStatus() {
        const { applicationId, subApplicationId } = resolveJointInspectionIdentifiers();

        if (!applicationId) {
            throw new Error('Application ID is required to mark the report as generated.');
        }

        const payload = {
            application_id: applicationId,
            sub_application_id: subApplicationId || null,
            is_generated: true,
            generated_at: new Date().toISOString()
        };

        const data = await postJointInspectionStatusUpdate(payload);

        if (window.jsiWorkflowState) {
            window.jsiWorkflowState.isGenerated = true;
            window.jsiWorkflowState.isSaved = true;
            if (typeof window.updateJointInspectionWorkflowStatus === 'function') {
                window.updateJointInspectionWorkflowStatus();
            }
        }

        return data;
    }

    async function updateSubmittedStatus() {
        const { applicationId, subApplicationId } = resolveJointInspectionIdentifiers();

        if (!applicationId) {
            throw new Error('Application ID is required to submit this report.');
        }

        const payload = {
            application_id: applicationId,
            sub_application_id: subApplicationId || null,
            is_submitted: true,
            submitted_at: new Date().toISOString()
        };

        const data = await postJointInspectionStatusUpdate(payload);

        if (window.jsiWorkflowState) {
            window.jsiWorkflowState.isSubmitted = true;
            window.jsiWorkflowState.isGenerated = true;
            if (typeof window.updateJointInspectionWorkflowStatus === 'function') {
                window.updateJointInspectionWorkflowStatus();
            }
        }

        return data;
    }

    // Add event listener to Save button
    const saveBtn = document.getElementById('jointInspectionSave');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            // Enable generate button after save is clicked
            // Note: This should ideally be enabled after successful save response
            setTimeout(() => {
                enableGenerateButton();
            }, 1000); // Small delay to allow save to process
        });
    }
    
    // Add event listener to Generate button
    const generateBtn = document.getElementById('jointInspectionGenerate');
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            // Update is_generated flag in database
            updateGeneratedStatus()
                .then(() => {
                    enableSubmitButton();
                    console.log('DEBUG - Generate button clicked, is_generated updated, Submit button enabled');
                })
                .catch(error => {
                    console.error('Error updating generated status:', error);
                    // Still enable submit button even if update fails
                    enableSubmitButton();
                });
        });
    }
    
    // Add event listener to Submit button
    const submitBtn = document.getElementById('jointInspectionSubmit');
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            // Update is_submitted flag in database and disable all buttons
            updateSubmittedStatus()
                .then(() => {
                    disableAllButtons();
                    console.log('DEBUG - Submit button clicked, is_submitted updated, all buttons disabled');
                })
                .catch(error => {
                    console.error('Error updating submitted status:', error);
                    // Still disable buttons even if update fails for UI consistency
                    disableAllButtons();
                });
        });
    }
    
    // Initialize measurement entries system
    function initializeMeasurementEntries(forceDefault = false) {
        console.log('DEBUG - Initializing measurement entries with data:', {
            sharedUtilitiesOptions: window.sharedUtilitiesOptions,
            parentSharedUtilities: typeof sharedUtilitiesOptions !== 'undefined' ? sharedUtilitiesOptions : 'undefined',
            forceDefault: forceDefault
        });
        
        // Always create at least one default entry first for immediate display
        createDefaultMeasurementEntry();
        
        // If not forcing default, try to fetch data from database
        if (!forceDefault) {
            const applicationId = document.getElementById('modal_application_id')?.value;
            const subApplicationId = document.getElementById('modal_sub_application_id')?.value;
            
            if (applicationId && applicationId.trim() !== '') {
                console.log('DEBUG - Fetching measurement entries from database for app:', applicationId, 'sub:', subApplicationId);
                
                    fetchSharedUtilitiesData(applicationId, currentSubApplicationId).then(sharedUtilitiesData => {
                    if (sharedUtilitiesData && sharedUtilitiesData.length > 0) {
                        // Convert database data to measurement entries format
                        window.measurementEntriesState = sharedUtilitiesData.map((utility, index) => ({
                            sn: index + 1,
                            description: utility.utility_type || '',
                            count: utility.count !== undefined && utility.count !== null
                                ? String(utility.count)
                                : '',
                            measurement_dimension: utility.measurement_dimension !== undefined && utility.measurement_dimension !== null
                                ? String(utility.measurement_dimension)
                                : '',
                            dimension: utility.dimension || ''
                        }));
                        console.log('DEBUG - Loaded measurement entries from database:', window.measurementEntriesState);
                        renderMeasurementEntries();
                    } else {
                        console.log('DEBUG - No database data found, keeping default entry');
                    }
                }).catch(error => {
                    console.error('DEBUG - Error fetching measurement entries:', error);
                    console.log('DEBUG - Keeping default entry due to error');
                });
            } else {
                console.log('DEBUG - No application ID available, using default entry');
            }
        }
        
        // Helper function to create default entry
        function createDefaultMeasurementEntry() {
            if (!window.measurementEntriesState || window.measurementEntriesState.length === 0) {
                window.measurementEntriesState = [{ sn: 1, description: '', count: '1', measurement_dimension: '', dimension: '' }];
                console.log('DEBUG - Created default measurement entry');
            }
            renderMeasurementEntries();
        }
        
        if (!measurementEntryEventsBound) {
            const addButton = document.getElementById('addMeasurementEntry');
            const measurementContainer = document.getElementById('measurementEntriesContainer');

            if (addButton) {
                addButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    addMeasurementEntry();
                    // Enable save button when user adds an entry
                    enableSaveButton();
                    markReportAsDirty();
                    console.log('DEBUG - Save button enabled due to adding measurement entry');
                });
            }

            if (measurementContainer) {
                measurementContainer.addEventListener('input', handleMeasurementEntryInput);
                measurementContainer.addEventListener('click', handleMeasurementEntryClick);
            }

            if (addButton || measurementContainer) {
                measurementEntryEventsBound = true;
            }
        }
    }
    
    // Function to render measurement entries
    function renderMeasurementEntries() {
        const container = document.getElementById('measurementEntriesContainer');
        if (!container) return;

        const currentEntries = Array.isArray(window.measurementEntriesState)
            ? window.measurementEntriesState
            : [];

        const normalizedEntries = currentEntries.map((entry, index) => {
            const descriptionValue = (entry?.description || '').toString();
            const countValue = entry?.count !== undefined && entry?.count !== null
                ? String(entry.count).trim()
                : '';
            const measurementDimensionValue = entry?.measurement_dimension !== undefined && entry?.measurement_dimension !== null
                ? String(entry.measurement_dimension).trim()
                : '';
            const dimensionValue = entry?.dimension !== undefined && entry?.dimension !== null
                ? String(entry.dimension).trim()
                : '';

            return {
                sn: index + 1,
                description: descriptionValue,
                count: countValue !== '' ? countValue : '1',
                measurement_dimension: measurementDimensionValue,
                dimension: dimensionValue,
            };
        });

        window.measurementEntriesState = normalizedEntries.length > 0
            ? normalizedEntries
            : [{ sn: 1, description: '', count: '1', measurement_dimension: '', dimension: '' }];

        const entries = window.measurementEntriesState;

        const rowsHtml = entries.map((entry, index) => {
            return `
            <div class="border border-gray-200 rounded-md p-3 bg-gray-50" data-entry-index="${index}">
                <input type="hidden" name="existing_site_measurement_entries[${index}][sn]" value="${index + 1}">
                <div class="flex flex-col md:flex-row md:items-start md:gap-4">
                    <div class="flex items-center mb-2 md:mb-0">
                        <span class="text-xs font-medium text-gray-500 mr-2">SN</span>
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 bg-white text-sm font-semibold text-gray-700">${index + 1}</span>
                    </div>
                    <div class="flex-1 space-y-1 md:mr-4">
                        <label class="text-xs font-medium text-gray-600">Utility</label>
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" 
                               name="existing_site_measurement_entries[${index}][description]" 
                               data-measurement-input="true" data-field="description" data-index="${index}" 
                               value="${entry.description || ''}" placeholder="Enter utility description">
                    </div>
                    <div class="w-full md:w-32 space-y-1 md:mr-4">
                        <label class="text-xs font-medium text-gray-600">Count</label>
               <input type="number" min="0" step="1" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                   name="existing_site_measurement_entries[${index}][count]"
                   data-measurement-input="true" data-field="count" data-index="${index}"
                   value="${entry.count || '1'}" placeholder="1">
                    </div>
                    <div class="flex-1 space-y-1 md:mr-4">
                           <label class="text-xs font-medium text-gray-600">Dimension</label>
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" 
                               name="existing_site_measurement_entries[${index}][measurement_dimension]" 
                               data-measurement-input="true" data-field="measurement_dimension" data-index="${index}" 
                               value="${entry.measurement_dimension || ''}" placeholder="Enter dimension">
                    </div>
                    <div class="flex-1 space-y-1 md:mr-4">
                        <label class="text-xs font-medium text-gray-600">Measurement (m²)</label>
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" 
                               name="existing_site_measurement_entries[${index}][dimension]" 
                               data-measurement-input="true" data-field="dimension" data-index="${index}" 
                               value="${entry.dimension || ''}" placeholder="Enter measurement">
                    </div>
                    <div class="flex items-start mt-2 md:mt-0">
                        <button type="button" class="inline-flex items-center px-3 py-1 text-xs font-medium text-red-600 border border-red-200 rounded-md hover:bg-red-50" 
                                data-action="remove-entry" data-index="${index}" ${entries.length <= 1 ? 'disabled' : ''}>Remove</button>
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
        container.innerHTML = rowsHtml;
        
        // Update the hidden JSON field whenever entries are rendered
        updateExistingSiteMeasurementEntries();
    }

    function applyMeasurementEntriesToUI(entries = []) {
        const sourceArray = Array.isArray(entries) ? entries : [];
        let normalized = sourceArray.map((entry, index) => ({
            sn: index + 1,
            description: (entry?.description || entry?.utility_type || '').toString().trim(),
            count: entry?.count !== undefined && entry?.count !== null
                ? String(entry.count).trim()
                : (entry?.quantity && String(entry.quantity).trim() !== '' ? String(entry.quantity).trim() : '1'),
            measurement_dimension: entry?.measurement_dimension !== undefined && entry?.measurement_dimension !== null
                ? String(entry.measurement_dimension).trim()
                : (entry?.dimension_display ? String(entry.dimension_display).trim() : ''),
            dimension: entry?.dimension !== undefined && entry?.dimension !== null
                ? String(entry.dimension).trim()
                : (entry?.measurement ? String(entry.measurement).trim() : '')
        }));

        if (normalized.length === 0) {
            normalized = [{ sn: 1, description: '', count: '1', measurement_dimension: '', dimension: '' }];
        } else {
            normalized = normalized.map((entry, index) => ({
                sn: index + 1,
                description: entry.description,
                count: entry.count !== undefined && entry.count !== null && String(entry.count).trim() !== ''
                    ? String(entry.count).trim()
                    : '1',
                measurement_dimension: entry.measurement_dimension || '',
                dimension: entry.dimension
            }));
        }

        window.measurementEntriesState = normalized;
        renderMeasurementEntries();
    }

    window.__jointInspectionUI = window.__jointInspectionUI || {};
    window.__jointInspectionUI.setMeasurementEntries = applyMeasurementEntriesToUI;
    window.__jointInspectionUI.getMeasurementEntries = function() {
        const entries = Array.isArray(window.measurementEntriesState)
            ? window.measurementEntriesState
            : [];
        return entries.map((entry, index) => ({
            sn: index + 1,
            description: entry.description || '',
            count: entry.count ?? '',
            measurement_dimension: entry.measurement_dimension || '',
            dimension: entry.dimension || ''
        }));
    };
    window.__jointInspectionUI.clearMeasurementEntries = function() {
        applyMeasurementEntriesToUI([]);
    };

    if (Array.isArray(window.pendingMeasurementEntriesDisplay) && window.pendingMeasurementEntriesDisplay.length > 0) {
        applyMeasurementEntriesToUI(window.pendingMeasurementEntriesDisplay);
        window.pendingMeasurementEntriesDisplay = null;
        window.pendingMeasurementEntries = null;
    } else if (Array.isArray(window.pendingMeasurementEntries) && window.pendingMeasurementEntries.length > 0) {
        applyMeasurementEntriesToUI(window.pendingMeasurementEntries);
        window.pendingMeasurementEntriesDisplay = null;
        window.pendingMeasurementEntries = null;
    }
    
    // Function to update the existing_site_measurement_entries hidden field with JSON data
    function updateExistingSiteMeasurementEntries() {
        const hiddenField = document.getElementById('existing_site_measurement_entries');
        if (!hiddenField) return;
        
        const entries = window.measurementEntriesState || [];
        const jsonData = entries
            .filter(entry => entry.description && entry.description.trim()) // Only include entries with descriptions
            .map(entry => ({
                sn: entry.sn,
                description: entry.description || '',
                count: entry.count !== undefined && entry.count !== null && String(entry.count).trim() !== ''
                    ? String(entry.count).trim()
                    : '1',
                measurement_dimension: entry.measurement_dimension || null,
                dimension: entry.dimension || null
            }));
        
        hiddenField.value = JSON.stringify(jsonData);
        console.log('DEBUG - Updated existing_site_measurement_entries JSON:', jsonData);
    }
    
    // Function to add measurement entry
    function addMeasurementEntry() {
        if (!window.measurementEntriesState) {
            window.measurementEntriesState = [];
        }
        
        window.measurementEntriesState.push({
            sn: window.measurementEntriesState.length + 1,
            description: '',
            count: '1',
            measurement_dimension: '',
            dimension: ''
        });
        
        renderMeasurementEntries();
    }
    
    // Function to handle measurement entry input
    function handleMeasurementEntryInput(event) {
        const target = event.target;
        if (!target.hasAttribute('data-measurement-input')) return;
        
        const index = parseInt(target.getAttribute('data-index'));
        const field = target.getAttribute('data-field');
        
        if (!window.measurementEntriesState[index]) return;
        
        window.measurementEntriesState[index][field] = target.value;
        
        // Update the JSON field when user types
        updateExistingSiteMeasurementEntries();
        
        // Enable save button when measurement entry is modified
        enableSaveButton();
        markReportAsDirty();
        console.log('DEBUG - Save button enabled due to measurement entry input');
    }
    
    // Function to handle measurement entry clicks
    function handleMeasurementEntryClick(event) {
        if (event.target.getAttribute('data-action') === 'remove-entry') {
            const index = parseInt(event.target.getAttribute('data-index'));
            
            if (window.measurementEntriesState.length <= 1) return;
            
            window.measurementEntriesState.splice(index, 1);
            
            // Renumber entries
            window.measurementEntriesState = window.measurementEntriesState.map((entry, newIndex) => ({
                ...entry,
                sn: newIndex + 1
            }));
            
            renderMeasurementEntries();
            markReportAsDirty();
        }
    }
    
    // Initialize both shared utilities and measurement entries together
    // Force immediate initialization with default entries
    console.log('DEBUG - Modal initialization - immediate setup');
    populateSharedUtilities();
    initializeMeasurementEntries(true); // Force default entries immediately
    
    // Then use timeout to load real data if available
    setTimeout(() => {
        console.log('DEBUG - Modal initialization - checking data availability:', {
            windowSharedUtilities: window.sharedUtilitiesOptions,
            parentSharedUtilities: typeof sharedUtilitiesOptions !== 'undefined' ? sharedUtilitiesOptions : 'undefined'
        });
        
        // Try to load real data from backend
        initializeMeasurementEntries(false);
        
        // Double-check measurement entries container always has content
        setTimeout(() => {
            const container = document.getElementById('measurementEntriesContainer');
            if (container && (!container.innerHTML || container.innerHTML.trim() === '')) {
                console.log('DEBUG - Measurement entries container is empty, forcing default entry');
                window.measurementEntriesState = [{ sn: 1, description: '', count: '1', measurement_dimension: '', dimension: '' }];
                renderMeasurementEntries();
            }
        }, 1000);
    }, 300);
    
    // Monitor changes to the sub_application_id field
    const subApplicationIdField = document.getElementById('modal_sub_application_id');
    if (subApplicationIdField) {
        // Initial check and unit data loading
        handleBoundaryDescriptionVisibility();
        loadUnitDataIfNeeded();
        
        // Watch for changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    handleBoundaryDescriptionVisibility();
                    loadUnitDataIfNeeded();
                }
            });
        });
        
        observer.observe(subApplicationIdField, {
            attributes: true,
            attributeFilter: ['value']
        });
        
        // Also listen for input changes
        subApplicationIdField.addEventListener('input', function() {
            handleBoundaryDescriptionVisibility();
            loadUnitDataIfNeeded();
        });
        subApplicationIdField.addEventListener('change', function() {
            handleBoundaryDescriptionVisibility();
            loadUnitDataIfNeeded();
        });
    }
    
    // Function to load unit data when needed
    function loadUnitDataIfNeeded() {
        const subApplicationId = document.getElementById('modal_sub_application_id')?.value;
        const applicationId = document.getElementById('modal_application_id')?.value;
        
        console.log('DEBUG - Checking if unit data needed:', {
            subApplicationId: subApplicationId,
            applicationId: applicationId,
            availableUnitData: window.unitDataOptions
        });
        
        if (subApplicationId && subApplicationId.trim() !== '') {
            console.log('DEBUG - Loading unit data for sub-application:', subApplicationId);
            
            // Try to get unit data from preloaded data first
            if (window.unitDataOptions && window.unitDataOptions[subApplicationId]) {
                const unitData = window.unitDataOptions[subApplicationId];
                populateUnitSpecificData(unitData);
                console.log('DEBUG - Successfully populated unit data from preloaded data:', unitData);
                return;
            }
            
            console.warn('DEBUG - No preloaded unit data found for subApplicationId:', subApplicationId);
            console.log('DEBUG - Available unit data keys:', window.unitDataOptions ? Object.keys(window.unitDataOptions) : 'No unit data');
            
            // For now, we'll rely on preloaded data only to avoid API issues
            // If needed, the API call can be added back later
        }
    }
    
    // Initialize save button as disabled when page loads
    disableSaveButton();
    console.log('DEBUG - Save button initialized as disabled');
    
    // Function to setup form event listeners
    function setupFormEventListeners() {
        const form = document.getElementById('jointInspectionForm');
        if (form) {
            // Add event listeners to form inputs to enable save button when user types
            const formInputs = form.querySelectorAll('input:not([type="hidden"]), textarea, select');
            console.log('DEBUG - Found', formInputs.length, 'form inputs for save button enablement');
            
            formInputs.forEach((input, index) => {
                console.log('DEBUG - Adding listeners to input', index, ':', input.name || input.id || input.tagName);
                
                input.addEventListener('input', function() {
                    enableSaveButton();
                    markReportAsDirty();
                    console.log('DEBUG - Save button enabled due to form input:', this.name || this.id);
                });
                input.addEventListener('change', function() {
                    enableSaveButton();
                    markReportAsDirty();
                    console.log('DEBUG - Save button enabled due to form change:', this.name || this.id);
                });
                input.addEventListener('keyup', function() {
                    enableSaveButton();
                    markReportAsDirty();
                    console.log('DEBUG - Save button enabled due to keyup:', this.name || this.id);
                });
            });
        } else {
            console.error('DEBUG - Form not found for event listener setup');
        }
    }
    
    // Setup form event listeners immediately
    setupFormEventListeners();
    
    // Also setup listeners after a delay to catch dynamically created inputs
    setTimeout(() => {
        setupFormEventListeners();
        console.log('DEBUG - Re-setup form event listeners after delay');
    }, 1000);
});



 
</script>
<script>
    // IMMEDIATE: Block conflicting JSI functions before they can be defined
    if (window.location.pathname.includes('/sub-actions/')) {
        // Immediately define blocking functions to prevent conflicts
        window.openJointInspectionModal = function() { 
            console.log('Blocked conflicting openJointInspectionModal call');
            return false; 
        };
        window.loadExistingJointInspectionData = function() { 
            console.log('Blocked conflicting loadExistingJointInspectionData call');
            return false; 
        };
        window.loadExistingInspectionReport = function() { 
            console.log('Blocked conflicting loadExistingInspectionReport call');
            return false; 
        };
    }
    
    console.log("Sub-actions JSI loaded successfully"); // Debug log

    let jointInspectionDefaultsState = window.jointInspectionDefaults || {};

    const createUtilityLookupKey = (value) => {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .toLowerCase()
            .replace(/[_\s-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    };
    const normalizeSharedUtilities = (value) => {
        if (!value) {
            return [];
        }

        let items = [];

        if (Array.isArray(value)) {
            items = value;
        } else if (typeof value === 'string') {
            try {
                const parsed = JSON.parse(value);
                if (Array.isArray(parsed)) {
                    items = parsed;
                } else if (parsed && typeof parsed === 'object') {
                    items = Object.values(parsed);
                } else {
                    items = value.split(',');
                }
            } catch (error) {
                items = value.split(',');
            }
        } else if (typeof value === 'object') {
            items = Object.values(value);
        } else {
            items = [value];
        }

        const seen = new Set();
        return items
            .map(item => {
                if (item === null || item === undefined) {
                    return '';
                }

                return typeof item === 'string' ? item.trim() : String(item).trim();
            })
            .filter(item => {
                if (!item) {
                    return false;
                }

                const key = createUtilityLookupKey(item);
                if (!key || seen.has(key)) {
                    return false;
                }

                seen.add(key);
                return true;
            });
    };

    const formatUtilityLabel = (value) => {
        if (value === null || value === undefined) {
            return '';
        }

        const normalized = String(value)
            .replace(/[_\-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        if (!normalized) {
            return '';
        }

        return normalized
            .split(' ')
            .map(segment => segment.charAt(0).toUpperCase() + segment.slice(1))
            .join(' ');
    };

    const escapeHtml = (value) => {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const sanitizeMeasurementEntries = (entries) => {
        const rawUtilities = Array.isArray(jointInspectionDefaultsState.shared_utilities)
            ? jointInspectionDefaultsState.shared_utilities
            : [];

        const utilitiesLookup = new Map();
        const utilitiesOrdered = [];

        rawUtilities.forEach((utility) => {
            const value = typeof utility === 'string' ? utility.trim() : String(utility ?? '').trim();
            if (!value) {
                return;
            }

            const key = createUtilityLookupKey(value);
            if (!key || utilitiesLookup.has(key)) {
                return;
            }

            utilitiesLookup.set(key, value);
            utilitiesOrdered.push(value);
        });

        const manualEntries = [];
        const utilityEntries = new Map();

        if (Array.isArray(entries)) {
            entries.forEach((entry) => {
                if (!entry || typeof entry !== 'object') {
                    return;
                }

                const rawDescription = entry.description ?? entry.utility_type ?? '';
                const rawMeasurementDimension = entry.measurement_dimension ?? entry.dimension_display ?? '';
                const rawDimension = entry.dimension ?? entry.measurement ?? '';
                const rawCount = entry.count ?? entry.quantity ?? '';

                const description = typeof rawDescription === 'string' ? rawDescription.trim() : String(rawDescription ?? '').trim();
                const measurementDimension = typeof rawMeasurementDimension === 'string' ? rawMeasurementDimension.trim() : String(rawMeasurementDimension ?? '').trim();
                const dimension = typeof rawDimension === 'string' ? rawDimension.trim() : String(rawDimension ?? '').trim();
                const countTrimmed = typeof rawCount === 'string' ? rawCount.trim() : String(rawCount ?? '').trim();
                const normalizedCount = countTrimmed === '' ? '1' : countTrimmed;

                if (description === '' && measurementDimension === '' && dimension === '' && normalizedCount === '1') {
                    return;
                }

                const key = createUtilityLookupKey(description);
                if (description && key && utilitiesLookup.has(key)) {
                    const existing = utilityEntries.get(key);
                    const shouldReplace = !existing
                        || ((!existing.dimension || existing.dimension === '') && dimension !== '')
                        || ((!existing.count || existing.count === '') && normalizedCount !== '');

                    if (shouldReplace) {
                        utilityEntries.set(key, {
                            description: utilitiesLookup.get(key),
                            measurement_dimension: measurementDimension,
                            dimension,
                            count: normalizedCount,
                        });
                    }
                } else {
                    manualEntries.push({
                        description,
                        measurement_dimension: measurementDimension,
                        dimension,
                        count: normalizedCount,
                    });
                }
            });
        }

        const resultEntries = [];

        if (utilitiesOrdered.length) {
            utilitiesOrdered.forEach((utility) => {
                const key = createUtilityLookupKey(utility);
                const matched = key ? utilityEntries.get(key) : null;
                resultEntries.push({
                    description: utility,
                    measurement_dimension: matched && matched.measurement_dimension ? matched.measurement_dimension : '',
                    dimension: matched && matched.dimension ? matched.dimension : '',
                    count: matched && matched.count ? matched.count : '1',
                });
            });
        }

        manualEntries.forEach(entry => {
            resultEntries.push({
                description: entry.description,
                measurement_dimension: entry.measurement_dimension,
                dimension: entry.dimension,
                count: entry.count === '' ? '1' : entry.count,
            });
        });

        if (!resultEntries.length) {
            resultEntries.push({
                description: '',
                measurement_dimension: '',
                dimension: '',
                count: '1',
            });
        }

        return resultEntries.map((entry, index) => ({
            sn: index + 1,
            description: entry.description,
            count: entry.count === '' ? '1' : entry.count,
            measurement_dimension: entry.measurement_dimension,
            dimension: entry.dimension,
        }));
    };

    const defaultBoundarySegments = {
        north: '',
        east: '',
        south: '',
        west: '',
    };
    const sanitizeBoundarySegments = (segments) => {
        const sanitized = { ...defaultBoundarySegments };
        if (!segments || typeof segments !== 'object') {
            return sanitized;
        }

        Object.keys(sanitized).forEach(direction => {
            const rawValue = segments[direction];
            if (typeof rawValue === 'string') {
                sanitized[direction] = rawValue.trim();
            } else if (rawValue !== null && rawValue !== undefined) {
                sanitized[direction] = String(rawValue).trim();
            } else {
                sanitized[direction] = '';
            }
        });

        return sanitized;
    };
    const buildBoundaryDescription = (segments) => {
        const sanitized = sanitizeBoundarySegments(segments);
        const lines = ['Boundary demarcation:'];
        lines.push(`- North: ${sanitized.north}`.trimEnd());
        lines.push(`- East: ${sanitized.east}`.trimEnd());
        lines.push(`- South: ${sanitized.south}`.trimEnd());
        lines.push(`- West: ${sanitized.west}`.trimEnd());
        return lines.join('\n');
    };

    if (!jointInspectionDefaultsState || typeof jointInspectionDefaultsState !== 'object') {
        jointInspectionDefaultsState = {};
    }

    jointInspectionDefaultsState = {
        ...jointInspectionDefaultsState,
        available_on_ground: Boolean(jointInspectionDefaultsState.available_on_ground),
        has_additional_observations: Boolean(jointInspectionDefaultsState.has_additional_observations),
        shared_utilities: normalizeSharedUtilities(jointInspectionDefaultsState.shared_utilities)
    };

    let boundarySegmentsState = sanitizeBoundarySegments(
        window.jointInspectionBoundarySegments
        || jointInspectionDefaultsState.boundary_segments
        || {}
    );
    jointInspectionDefaultsState.boundary_segments = { ...boundarySegmentsState };
    jointInspectionDefaultsState.boundary_description = buildBoundaryDescription(boundarySegmentsState);
    window.jointInspectionBoundarySegments = boundarySegmentsState;

    let measurementEntriesState = sanitizeMeasurementEntries(jointInspectionDefaultsState.existing_site_measurement_entries);
    jointInspectionDefaultsState.existing_site_measurement_entries = measurementEntriesState.map(entry => ({ ...entry }));
    window.jointInspectionDefaults = jointInspectionDefaultsState;

    let jointInspectionReportUrl = window.jointInspectionExistingReportUrl || '';
    let jointInspectionReportSavedForSubmission = Boolean(jointInspectionReportUrl);
    let shouldSubmitAfterJointInspection = false;

    function syncMeasurementEntriesToDefaults() {
        jointInspectionDefaultsState.existing_site_measurement_entries = measurementEntriesState.map(entry => ({
            sn: entry.sn,
            description: entry.description,
            count: (() => {
                const value = entry.count ?? '';
                const normalized = typeof value === 'string' ? value.trim() : String(value ?? '').trim();
                return normalized === '' ? '1' : normalized;
            })(),
            measurement_dimension: entry.measurement_dimension,
            dimension: entry.dimension,
        }));
        window.jointInspectionDefaults = jointInspectionDefaultsState;
    }

    function renderMeasurementEntries() {
        const container = document.getElementById('measurementEntriesContainer');
        if (!container) {
            return;
        }

        if (!Array.isArray(measurementEntriesState) || measurementEntriesState.length === 0) {
            measurementEntriesState = [{ sn: 1, description: '', count: '1', measurement_dimension: '', dimension: '' }];
        }

        measurementEntriesState = measurementEntriesState.map((entry, index) => {
            const rawCount = entry?.count ?? '';
            const normalizedCount = typeof rawCount === 'string' ? rawCount.trim() : String(rawCount ?? '').trim();

            return {
                sn: index + 1,
                description: entry?.description ?? '',
                count: normalizedCount === '' ? '1' : normalizedCount,
                measurement_dimension: entry?.measurement_dimension ?? '',
                dimension: entry?.dimension ?? '',
            };
        });

        const normalizedUtilities = Array.isArray(jointInspectionDefaultsState.shared_utilities)
            ? jointInspectionDefaultsState.shared_utilities
            : [];
        const utilitiesLookup = new Map();
        normalizedUtilities.forEach((utility) => {
            const rawValue = typeof utility === 'string' ? utility.trim() : String(utility ?? '').trim();
            if (!rawValue) {
                return;
            }

            const key = createUtilityLookupKey(rawValue);
            if (key && !utilitiesLookup.has(key)) {
                utilitiesLookup.set(key, rawValue);
            }
        });

        const rowsHtml = measurementEntriesState.map((entry, index) => {
            const rawDescription = entry.description ?? '';
            const normalizedDescription = typeof rawDescription === 'string' ? rawDescription.trim() : String(rawDescription ?? '').trim();
            const lookupKey = createUtilityLookupKey(normalizedDescription);
            const isLinkedUtility = lookupKey !== '' && utilitiesLookup.has(lookupKey);
            const utilityRawValue = isLinkedUtility ? utilitiesLookup.get(lookupKey) : rawDescription;
            const utilityLabel = isLinkedUtility ? formatUtilityLabel(utilityRawValue) : rawDescription;

            const descriptionFieldName = `existing_site_measurement_entries[${index}][description]`;
            const descriptionFieldContent = isLinkedUtility
                ? `
                        <input type="hidden" name="${descriptionFieldName}" value="${escapeHtml(utilityRawValue)}">
                        <div class="w-full border border-gray-200 rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700">${escapeHtml(utilityLabel)}</div>
                    `
                : `
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" name="${descriptionFieldName}" data-measurement-input="true" data-field="description" data-index="${index}" value="${escapeHtml(rawDescription)}">
                    `;

            const removeDisabled = isLinkedUtility || measurementEntriesState.length <= 1;
            const removeDisabledAttr = removeDisabled ? 'disabled' : '';
            const removeButtonClasses = removeDisabled
                ? 'inline-flex items-center px-3 py-1 text-xs font-medium text-red-400 border border-red-200 rounded-md bg-red-50 cursor-not-allowed'
                : 'inline-flex items-center px-3 py-1 text-xs font-medium text-red-600 border border-red-200 rounded-md hover:bg-red-50';

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
                        ${descriptionFieldContent}
                    </div>
                    <div class="w-full md:w-32 space-y-1 md:mr-4">
                        <label class="text-xs font-medium text-gray-600">Count</label>
                        <input type="number" min="0" step="1" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" name="existing_site_measurement_entries[${index}][count]" data-measurement-input="true" data-field="count" data-index="${index}" value="${escapeHtml(entry.count)}" placeholder="1">
                    </div>
                    <div class="flex-1 space-y-1 md:mr-4">
                        <label class="text-xs font-medium text-gray-600">Dimension</label>
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" name="existing_site_measurement_entries[${index}][measurement_dimension]" data-measurement-input="true" data-field="measurement_dimension" data-index="${index}" value="${escapeHtml(entry.measurement_dimension)}">
                    </div>
                    <div class="flex-1 space-y-1 md:mr-4">
                        <label class="text-xs font-medium text-gray-600">Measurement (m²)</label>
                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" name="existing_site_measurement_entries[${index}][dimension]" data-measurement-input="true" data-field="dimension" data-index="${index}" value="${escapeHtml(entry.dimension)}">
                    </div>
                    <div class="flex items-start mt-2 md:mt-0">
                        <button type="button" class="${removeButtonClasses}" data-action="remove-entry" data-index="${index}" ${removeDisabledAttr}>Remove</button>
                    </div>
                </div>
            </div>
            `;
        }).join('');

        container.innerHTML = rowsHtml;
        syncMeasurementEntriesToDefaults();
    }

    function setMeasurementEntries(entries) {
        measurementEntriesState = sanitizeMeasurementEntries(entries);
        renderMeasurementEntries();
    }

    function addMeasurementEntry() {
        measurementEntriesState.push({
            sn: measurementEntriesState.length + 1,
            description: '',
            count: '1',
            measurement_dimension: '',
            dimension: '',
        });
        renderMeasurementEntries();
        markJointInspectionAsDirty();

        requestAnimationFrame(() => {
            const container = document.getElementById('measurementEntriesContainer');
            const targetInput = container?.querySelector(`input[data-field="description"][data-index="${measurementEntriesState.length - 1}"]`);
            targetInput?.focus();
        });
    }

    function removeMeasurementEntry(index) {
        if (measurementEntriesState.length <= 1) {
            return;
        }

        measurementEntriesState.splice(index, 1);

        if (measurementEntriesState.length === 0) {
            measurementEntriesState.push({ sn: 1, description: '', count: '1', measurement_dimension: '', dimension: '' });
        }

        renderMeasurementEntries();
        markJointInspectionAsDirty();
    }

    function handleMeasurementEntryInput(event) {
        const target = event.target;
        if (!target || target.dataset.measurementInput !== 'true') {
            return;
        }

        const index = Number(target.dataset.index);
        if (!Number.isInteger(index) || index < 0 || !measurementEntriesState[index]) {
            return;
        }

        const field = target.dataset.field;
        if (!['description', 'measurement_dimension', 'dimension', 'count'].includes(field)) {
            return;
        }

        if (field === 'count') {
            const normalized = (target.value ?? '').toString().trim();
            const nextValue = normalized === '' ? '1' : normalized;
            measurementEntriesState[index][field] = nextValue;
            if (normalized === '') {
                target.value = nextValue;
            }
        } else {
            measurementEntriesState[index][field] = target.value;
        }
        syncMeasurementEntriesToDefaults();
        markJointInspectionAsDirty();
    }

    function handleMeasurementEntryClick(event) {
        const removeButton = event.target.closest('[data-action="remove-entry"]');
        if (!removeButton) {
            return;
        }

        event.preventDefault();

        const index = Number(removeButton.dataset.index);
        if (!Number.isInteger(index) || index < 0) {
            return;
        }

        removeMeasurementEntry(index);
    }

    function handleSharedUtilitiesChange() {
        const form = document.getElementById('jointInspectionForm');
        if (!form) {
            return;
        }

        const previousUtilities = Array.isArray(jointInspectionDefaultsState.shared_utilities)
            ? jointInspectionDefaultsState.shared_utilities
            : [];
        const previousKeys = new Set(
            previousUtilities
                .map(utility => createUtilityLookupKey(typeof utility === 'string' ? utility.trim() : String(utility ?? '').trim()))
                .filter(Boolean)
        );

        const selectedUtilities = Array.from(form.querySelectorAll('input[name="shared_utilities[]"]:checked')).map(input => input.value);
        const normalizedSelection = normalizeSharedUtilities(selectedUtilities);
        const nextKeys = new Set(normalizedSelection.map(utility => createUtilityLookupKey(utility)).filter(Boolean));

        measurementEntriesState = measurementEntriesState.filter(entry => {
            if (!entry || typeof entry !== 'object') {
                return false;
            }

            const description = typeof entry.description === 'string' ? entry.description.trim() : String(entry.description ?? '').trim();
            if (!description) {
                return true;
            }

            const key = createUtilityLookupKey(description);
            if (previousKeys.has(key) && !nextKeys.has(key)) {
                return false;
            }

            return true;
        });

        jointInspectionDefaultsState.shared_utilities = normalizedSelection;
        measurementEntriesState = sanitizeMeasurementEntries(measurementEntriesState);
        renderMeasurementEntries();
        markJointInspectionAsDirty();
    }

    function updateJointInspectionReportLink(url) {
        const link = document.getElementById('jointInspectionReportLink');
        if (!link) {
            return;
        }

        if (url) {
            link.href = url;
            link.classList.remove('hidden');
        } else {
            link.href = '#';
            if (!link.classList.contains('hidden')) {
                link.classList.add('hidden');
            }
        }
    }

    function updateBoundaryDescriptionField({ markDirty = false } = {}) {
        const description = buildBoundaryDescription(boundarySegmentsState);
        const hiddenField = document.getElementById('jointInspectionBoundary');
        if (hiddenField) {
            hiddenField.value = description;
        }

        jointInspectionDefaultsState.boundary_description = description;
        jointInspectionDefaultsState.boundary_segments = { ...boundarySegmentsState };
        window.jointInspectionDefaults = jointInspectionDefaultsState;
        window.jointInspectionBoundarySegments = boundarySegmentsState;

        if (markDirty) {
            markJointInspectionAsDirty();
        }

        return description;
    }

    function setBoundarySegments(segments, { markDirty = false } = {}) {
        boundarySegmentsState = sanitizeBoundarySegments(segments);

        const form = document.getElementById('jointInspectionForm');
        if (form) {
            Object.entries(boundarySegmentsState).forEach(([direction, value]) => {
                const input = form.querySelector(`[data-boundary-direction="${direction}"]`);
                if (input) {
                    input.value = value;
                }
            });
        }

        updateBoundaryDescriptionField({ markDirty });
    }

    function handleBoundarySegmentInput(event) {
        const target = event?.target;
        if (!target) {
            return;
        }

        const direction = target.dataset?.boundaryDirection;
        if (!direction || !(direction in boundarySegmentsState)) {
            return;
        }

        boundarySegmentsState[direction] = target.value || '';
        updateBoundaryDescriptionField({ markDirty: true });
    }

    function populateJointInspectionForm(values = {}) {
        const form = document.getElementById('jointInspectionForm');
        if (!form) {
            return;
        }

        console.log('DEBUG - values passed to populate:', values);
        console.log('DEBUG - jointInspectionDefaultsState:', jointInspectionDefaultsState);
        
        const mergedValues = {
            ...jointInspectionDefaultsState,
            ...(values || {})
        };
        
        console.log('DEBUG - mergedValues after merge:', mergedValues);

    const normalizedUtilities = normalizeSharedUtilities(mergedValues.shared_utilities);
    jointInspectionDefaultsState.shared_utilities = normalizedUtilities;
    setMeasurementEntries(mergedValues.existing_site_measurement_entries);
    setBoundarySegments(mergedValues.boundary_segments ?? boundarySegmentsState, { markDirty: false });

        const inspectionDateField = form.querySelector('[name="inspection_date"]');
        if (inspectionDateField) {
            let dateValue = mergedValues.inspection_date;
            
            // Format the date properly for HTML date input
            if (dateValue) {
                // Handle datetime strings like "2025-10-08T00:00:00.000000Z"
                if (typeof dateValue === 'string' && (dateValue.includes('T') || dateValue.includes(' '))) {
                    dateValue = dateValue.split('T')[0].split(' ')[0];
                }
                inspectionDateField.value = dateValue;
            } else {
                // Only set default date if no existing date
                inspectionDateField.value = new Date().toISOString().slice(0, 10);
            }
        }

        const textMappings = [
            { selector: '[name="lkn_number"]', value: mergedValues.lkn_number ?? '' },
            { selector: '[name="applicant_name"]', value: mergedValues.applicant_name ?? '' },
            { selector: '[name="location"]', value: mergedValues.location ?? '' },
            { selector: '[name="plot_number"]', value: mergedValues.plot_number ?? '' },
            { selector: '[name="scheme_number"]', value: mergedValues.scheme_number ?? '' },
            { selector: '[name="unit_dimension"]', value: mergedValues.unit_dimension ?? '' },
            { selector: '[name="existing_site_measurement_summary"]', value: mergedValues.existing_site_measurement_summary ?? '' },
            { selector: '[name="road_reservation"]', value: mergedValues.road_reservation ?? '' },
            { selector: '[name="prevailing_land_use"]', value: mergedValues.prevailing_land_use ?? '' },
            { selector: '[name="applied_land_use"]', value: mergedValues.applied_land_use ?? '' },
            { selector: '[name="inspection_officer"]', value: mergedValues.inspection_officer ?? '' }
        ];

        textMappings.forEach(mapping => {
            const field = form.querySelector(mapping.selector);
            if (field) {
                field.value = mapping.value ?? '';
            }
        });

        const unitField = form.querySelector('[name="unit_number"]');
        if (unitField) {
            const unitValue = mergedValues.unit_number ?? mergedValues.sections_count ?? '';
            unitField.value = unitValue ?? '';
        }

        const availableCheckbox = form.querySelector('input[name="available_on_ground"]');
        if (availableCheckbox) {
            availableCheckbox.checked = Boolean(mergedValues.available_on_ground);
        }

    const sharedUtilitiesSelected = new Set(normalizedUtilities);
        const sharedUtilityInputs = form.querySelectorAll('input[name="shared_utilities[]"]');
        sharedUtilityInputs.forEach(input => {
            input.checked = sharedUtilitiesSelected.has(input.value);
        });

        const complianceSelect = form.querySelector('[name="compliance_status"]');
        if (complianceSelect) {
            complianceSelect.value = mergedValues.compliance_status || 'obtainable';
        }

        const hasObservationsValue = mergedValues.has_additional_observations ? '1' : '0';
        const observationRadios = form.querySelectorAll('input[name="has_additional_observations"]');
        observationRadios.forEach(radio => {
            radio.checked = radio.value === hasObservationsValue;
        });

        const observationWrapper = document.getElementById('jointInspectionObservationsWrapper');
        if (observationWrapper) {
            if (hasObservationsValue === '1') {
                observationWrapper.classList.remove('hidden');
            } else {
                observationWrapper.classList.add('hidden');
            }
        }

        const additionalObservationsField = form.querySelector('[name="additional_observations"]');
        if (additionalObservationsField) {
            additionalObservationsField.value = mergedValues.additional_observations ?? '';
        }
    }

    function openSubApplicationJointInspectionModal() {
        const modal = document.getElementById('jointInspectionModal');
        if (!modal) {
            return;
        }

        // Use processed defaults data which includes fallbacks from application data
        const savedReport = window.jointInspectionSavedReport;
        const defaultsData = window.jointInspectionDefaults;
        
        if (savedReport && defaultsData) {
            console.log('DEBUG - Using processed jointInspectionDefaults with fallbacks');
            // Use the processed defaults which already have fallback values
            populateJointInspectionForm(defaultsData);
            
            // Update workflow button states based on saved report
            updateJSIWorkflowButtons(savedReport);
            updateJSIWorkflowStatus(savedReport);
        } else if (savedReport) {
            console.log('DEBUG - Using raw savedReport data');
            // Fallback to raw saved report data
            const formData = {
                inspection_date: savedReport.inspection_date ? savedReport.inspection_date.split('T')[0] : null,
                lkn_number: savedReport.lkn_number,
                applicant_name: savedReport.applicant_name,
                location: savedReport.location,
                plot_number: savedReport.plot_number,
                scheme_number: savedReport.scheme_number,
                unit_dimension: savedReport.unit_dimension,
                available_on_ground: savedReport.available_on_ground,
                boundary_description: savedReport.boundary_description,
                sections_count: savedReport.sections_count,
                unit_number: savedReport.unit_number,
                road_reservation: savedReport.road_reservation,
                prevailing_land_use: savedReport.prevailing_land_use,
                applied_land_use: savedReport.applied_land_use,
                shared_utilities: savedReport.shared_utilities,
                compliance_status: savedReport.compliance_status,
                has_additional_observations: savedReport.has_additional_observations,
                additional_observations: savedReport.additional_observations,
                inspection_officer: savedReport.inspection_officer,
                existing_site_measurement_summary: savedReport.existing_site_measurement_summary,
                existing_site_measurement_entries: savedReport.existing_site_measurement_entries
            };
            
            populateJointInspectionForm(formData);
            updateJSIWorkflowButtons(savedReport);
            updateJSIWorkflowStatus(savedReport);
        } else {
            console.log('DEBUG - No saved report, using empty defaults');
            populateJointInspectionForm();
        }
        
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        setTimeout(() => {
            document.getElementById('jointInspectionDate')?.focus();
        }, 100);
    }

    function closeJointInspectionModal() {
        const modal = document.getElementById('jointInspectionModal');
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.style.display = 'none';
    }

    function markJointInspectionAsDirty() {
        jointInspectionReportSavedForSubmission = false;
    }

    function collectJointInspectionPayload(form) {
        const payload = {};

        updateBoundaryDescriptionField({ markDirty: false });

        const applicationField = form.querySelector('[name="application_id"]');
        const subApplicationField = form.querySelector('[name="sub_application_id"]');

        let applicationId = applicationField ? parseInt(applicationField.value, 10) : NaN;
        if (Number.isNaN(applicationId) || applicationId <= 0) {
            applicationId = null;
        }
        payload.application_id = applicationId;

        let subApplicationId = subApplicationField ? parseInt(subApplicationField.value, 10) : NaN;
        if (Number.isNaN(subApplicationId) || subApplicationId <= 0) {
            subApplicationId = null;
        }
        payload.sub_application_id = subApplicationId;

        payload.inspection_date = (form.querySelector('[name="inspection_date"]').value || '').trim();
        payload.lkn_number = (form.querySelector('[name="lkn_number"]').value || '').trim() || null;
        payload.applicant_name = (form.querySelector('[name="applicant_name"]').value || '').trim() || null;
        payload.location = (form.querySelector('[name="location"]').value || '').trim() || null;
        payload.plot_number = (form.querySelector('[name="plot_number"]').value || '').trim() || null;
        payload.scheme_number = (form.querySelector('[name="scheme_number"]').value || '').trim() || null;
        const boundaryDescriptionValue = buildBoundaryDescription(boundarySegmentsState);
        const boundaryHasValues = Object.values(boundarySegmentsState).some(value => (value || '').trim() !== '');
        payload.boundary_segments = { ...boundarySegmentsState };
        payload.boundary_description = boundaryHasValues ? boundaryDescriptionValue : null;

    const measurementSummaryField = form.querySelector('[name="existing_site_measurement_summary"]');
    const measurementSummaryValue = measurementSummaryField ? measurementSummaryField.value.trim() : '';
    payload.existing_site_measurement_summary = measurementSummaryValue !== '' ? measurementSummaryValue : null;

    const unitField = form.querySelector('[name="unit_number"]');
    const unitValueRaw = unitField ? unitField.value : '';
    const unitValueTrimmed = typeof unitValueRaw === 'string' ? unitValueRaw.trim() : unitValueRaw;
    const unitNumber = unitValueTrimmed === '' ? null : unitValueTrimmed;
    payload.unit_number = unitNumber;
    payload.sections_count = null;

    const unitDimensionField = form.querySelector('[name="unit_dimension"]');
    const unitDimensionValue = unitDimensionField ? unitDimensionField.value.trim() : '';
    payload.unit_dimension = unitDimensionValue !== '' ? unitDimensionValue : null;

        payload.road_reservation = (form.querySelector('[name="road_reservation"]').value || '').trim() || null;
        payload.prevailing_land_use = (form.querySelector('[name="prevailing_land_use"]').value || '').trim() || null;
        payload.applied_land_use = (form.querySelector('[name="applied_land_use"]').value || '').trim() || null;

        const sharedUtilities = Array.from(form.querySelectorAll('input[name="shared_utilities[]"]:checked')).map(input => input.value);
        payload.shared_utilities = sharedUtilities;

        const availableCheckbox = form.querySelector('input[name="available_on_ground"]');
        payload.available_on_ground = availableCheckbox && availableCheckbox.checked ? 1 : 0;

        const complianceSelect = form.querySelector('[name="compliance_status"]');
        payload.compliance_status = complianceSelect ? (complianceSelect.value || null) : null;

        const hasAdditionalRadio = form.querySelector('input[name="has_additional_observations"]:checked');
        payload.has_additional_observations = hasAdditionalRadio ? Number(hasAdditionalRadio.value) : 0;

        const additionalObservationsField = form.querySelector('[name="additional_observations"]');
        const additionalObservationsValue = additionalObservationsField ? additionalObservationsField.value.trim() : '';
        payload.additional_observations = additionalObservationsValue !== '' ? additionalObservationsValue : null;

        payload.inspection_officer = (form.querySelector('[name="inspection_officer"]').value || '').trim() || null;

        payload.existing_site_measurement_entries = measurementEntriesState
            .map((entry, index) => {
                const description = typeof entry.description === 'string' ? entry.description.trim() : '';
                const measurementDimensionValue = typeof entry.measurement_dimension === 'string' ? entry.measurement_dimension.trim() : '';
                const dimension = typeof entry.dimension === 'string' ? entry.dimension.trim() : '';
                const countValueRaw = entry.count ?? '';
                const countTrimmed = typeof countValueRaw === 'string' ? countValueRaw.trim() : String(countValueRaw ?? '').trim();
                const count = countTrimmed === '' ? '1' : countTrimmed;

                if (description === '' && measurementDimensionValue === '' && dimension === '' && count === '1') {
                    return null;
                }

                return {
                    sn: index + 1,
                    description: description !== '' ? description : null,
                    count,
                    measurement_dimension: measurementDimensionValue !== '' ? measurementDimensionValue : null,
                    dimension: dimension !== '' ? dimension : null,
                };
            })
            .filter(Boolean);

        return payload;
    }

    function saveJointInspectionReport(event) {
        event.preventDefault();

        const form = event.target.closest('form') || document.getElementById('jointInspectionForm');
        const saveButton = document.getElementById('jsi-save-btn');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
        }

        const payload = collectJointInspectionPayload(form);
        const csrfToken = form.querySelector('input[name="_token"]').value;

        fetch('{{ route('sub-actions.planning-recommendation.joint-inspection.store') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw {
                            status: response.status,
                            data: data
                        };
                    });
                }

                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw { data };
                }

                jointInspectionReportSavedForSubmission = true;
                jointInspectionReportUrl = data.view_url || jointInspectionReportUrl;
                window.jointInspectionExistingReportUrl = jointInspectionReportUrl;

                const normalizedPayloadUtilities = normalizeSharedUtilities(payload.shared_utilities);
                jointInspectionDefaultsState = {
                    ...jointInspectionDefaultsState,
                    shared_utilities: normalizedPayloadUtilities,
                };

                const savedMeasurementEntries = sanitizeMeasurementEntries(payload.existing_site_measurement_entries || []);
                measurementEntriesState = savedMeasurementEntries;
                boundarySegmentsState = sanitizeBoundarySegments(payload.boundary_segments || boundarySegmentsState);

                jointInspectionDefaultsState = {
                    ...jointInspectionDefaultsState,
                    ...payload,
                    shared_utilities: normalizedPayloadUtilities,
                    available_on_ground: payload.available_on_ground === 1,
                    has_additional_observations: payload.has_additional_observations === 1,
                    boundary_segments: { ...boundarySegmentsState },
                    boundary_description: buildBoundaryDescription(boundarySegmentsState),
                    existing_site_measurement_entries: savedMeasurementEntries.map(entry => ({ ...entry })),
                };
                window.jointInspectionDefaults = jointInspectionDefaultsState;

                setBoundarySegments(boundarySegmentsState, { markDirty: false });
                renderMeasurementEntries();

                updateJointInspectionReportLink(jointInspectionReportUrl);
                updateJSIWorkflowButtons(data);
                updateJSIWorkflowStatus(data);

                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.textContent = 'Save';
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Joint site inspection saved',
                    text: 'The joint site inspection report was saved successfully.',
                    confirmButtonColor: '#10B981'
                }).then(() => {
                    if (shouldSubmitAfterJointInspection) {
                        shouldSubmitAfterJointInspection = false;
                        submitPlanningRecommendation('Approved');
                    }
                });
            })
            .catch(error => {
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.textContent = 'Save';
                }

                jointInspectionReportSavedForSubmission = false;

                let message = 'Failed to save joint site inspection report.';
                if (error?.data?.message) {
                    message = error.data.message;
                } else if (error?.data?.errors) {
                    const firstError = Object.values(error.data.errors)[0];
                    if (Array.isArray(firstError) && firstError.length) {
                        message = firstError[0];
                    }
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Save failed',
                    text: message,
                    confirmButtonColor: '#EF4444'
                });
            });
    }

    function generateJointInspectionReport(event) {
        event.preventDefault();

        const generateButton = document.getElementById('jsi-generate-btn');
        if (generateButton) {
            generateButton.disabled = true;
            generateButton.textContent = 'Generating...';
        }

        const payload = {
            application_id: window.jointInspectionDefaults?.application_id || null,
            sub_application_id: {{ $application->id ?? 'null' }}
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('{{ route('sub-actions.planning-recommendation.joint-inspection.generate') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (generateButton) {
                generateButton.disabled = false;
                generateButton.textContent = 'Generate';
            }

            if (data.success) {
                updateJSIWorkflowButtons(data);
                updateJSIWorkflowStatus(data);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Report Generated',
                    text: data.message,
                    confirmButtonColor: '#10B981'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Generation Failed',
                    text: data.message || 'Failed to generate joint site inspection report.',
                    confirmButtonColor: '#EF4444'
                });
            }
        })
        .catch(error => {
            if (generateButton) {
                generateButton.disabled = false;
                generateButton.textContent = 'Generate';
            }

            Swal.fire({
                icon: 'error',
                title: 'Generation Failed',
                text: 'Failed to generate joint site inspection report.',
                confirmButtonColor: '#EF4444'
            });
        });
    }

    function submitJointInspectionReport(event) {
        event.preventDefault();

        const submitButton = document.getElementById('jsi-submit-btn');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';
        }

        const payload = {
            application_id: window.jointInspectionDefaults?.application_id || null,
            sub_application_id: {{ $application->id ?? 'null' }}
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('{{ route('sub-actions.planning-recommendation.joint-inspection.submit') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Submit';
            }

            if (data.success) {
                updateJSIWorkflowButtons(data);
                updateJSIWorkflowStatus(data);
                closeJointInspectionModal();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Report Submitted',
                    text: data.message,
                    confirmButtonColor: '#10B981'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: data.message || 'Failed to submit joint site inspection report.',
                    confirmButtonColor: '#EF4444'
                });
            }
        })
        .catch(error => {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Submit';
            }

            Swal.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: 'Failed to submit joint site inspection report.',
                confirmButtonColor: '#EF4444'
            });
        });
    }

    function updateJSIWorkflowButtons(data) {
        const saveBtn = document.getElementById('jsi-save-btn');
        const generateBtn = document.getElementById('jsi-generate-btn');
        const submitBtn = document.getElementById('jsi-submit-btn');
        
        // If buttons don't exist, skip the update
        if (!saveBtn || !generateBtn || !submitBtn) {
            console.log('JSI workflow buttons not found, skipping update');
            return;
        }

        if (data.is_submitted) {
            // Report is submitted - disable all buttons except cancel
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            if (generateBtn) {
                generateBtn.disabled = true;
                generateBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        } else if (data.is_generated) {
            // Report is generated - enable submit, disable save and generate
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            if (generateBtn) {
                generateBtn.disabled = true;
                generateBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        } else {
            // Report exists but not generated - enable generate, keep save enabled
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            if (generateBtn) {
                generateBtn.disabled = false;
                generateBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
    }

    function updateJSIWorkflowStatus(data) {
        const statusElement = document.getElementById('jsi-workflow-status');
        if (!statusElement) {
            console.log('JSI workflow status element not found, skipping update');
            return;
        }
        
        if (statusElement) {
            if (data.is_submitted) {
                statusElement.textContent = '✅ Submitted';
            } else if (data.is_generated) {
                statusElement.textContent = '📋 Generated';
            } else {
                statusElement.textContent = '💾 Saved';
            }
        }
    }

    // Override with our specific function after page loads
    window.openJointInspectionModal = function(applicationId, subApplicationId) {
        console.log('Using sub-application JSI modal override');
        openSubApplicationJointInspectionModal();
        return false;
    };

    // First let's clean up the repeated code by creating one DOMContentLoaded handler
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM Content Loaded - Sub-Application JSI");
        
        // Remove any conflicting event listeners that might have been added
        setTimeout(function() {
            const jsiButtons = document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#jointInspectionModal"], [onclick*="openJointInspectionModal"]');
            jsiButtons.forEach(button => {
                // Remove any existing onclick handlers
                button.removeAttribute('onclick');
                // Add our specific handler
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openSubApplicationJointInspectionModal();
                });
            });
        }, 100); // Short delay to let other scripts load first

        updateJointInspectionReportLink(jointInspectionReportUrl);

        const boundaryInputs = document.querySelectorAll('[data-boundary-direction]');
        if (boundaryInputs.length > 0) {
            boundaryInputs.forEach(input => {
                input.addEventListener('input', handleBoundarySegmentInput);
            });
            setBoundarySegments(boundarySegmentsState, { markDirty: false });
        } else {
            updateBoundaryDescriptionField({ markDirty: false });
        }

        const measurementEntriesContainer = document.getElementById('measurementEntriesContainer');
        if (measurementEntriesContainer) {
            measurementEntriesContainer.addEventListener('input', handleMeasurementEntryInput);
            measurementEntriesContainer.addEventListener('change', handleMeasurementEntryInput);
            measurementEntriesContainer.addEventListener('click', handleMeasurementEntryClick);
            renderMeasurementEntries();
        }

        const addMeasurementEntryButton = document.getElementById('addMeasurementEntry');
        if (addMeasurementEntryButton) {
            addMeasurementEntryButton.addEventListener('click', (event) => {
                event.preventDefault();
                addMeasurementEntry();
            });
        }

        const sharedUtilityInputs = document.querySelectorAll('input[name="shared_utilities[]"]');
        if (sharedUtilityInputs.length > 0) {
            sharedUtilityInputs.forEach(input => {
                input.addEventListener('change', handleSharedUtilitiesChange);
            });
        }

        const jointInspectionForm = document.getElementById('jointInspectionForm');
        if (jointInspectionForm) {
            // Prevent form submission and handle with buttons instead
            jointInspectionForm.addEventListener('submit', (event) => {
                event.preventDefault();
            });

            jointInspectionForm.addEventListener('input', (event) => {
                const target = event.target;
                if (!target) {
                    return;
                }

                if (['application_id', '_token', 'sub_application_id'].includes(target.name)) {
                    return;
                }

                if (target.type === 'submit') {
                    return;
                }

                markJointInspectionAsDirty();
            });

            jointInspectionForm.addEventListener('change', (event) => {
                const target = event.target;
                if (!target) {
                    return;
                }

                if (['application_id', '_token', 'sub_application_id'].includes(target.name)) {
                    return;
                }

                if (target.type === 'submit') {
                    return;
                }

                markJointInspectionAsDirty();
            });
        }

        // Add event listeners for workflow buttons
        const jsiSaveBtn = document.getElementById('jsi-save-btn');
        if (jsiSaveBtn) {
            jsiSaveBtn.addEventListener('click', saveJointInspectionReport);
        }

        const jsiGenerateBtn = document.getElementById('jsi-generate-btn');
        if (jsiGenerateBtn) {
            jsiGenerateBtn.addEventListener('click', generateJointInspectionReport);
        }

        const jsiSubmitBtn = document.getElementById('jsi-submit-btn');
        if (jsiSubmitBtn) {
            jsiSubmitBtn.addEventListener('click', submitJointInspectionReport);
        }

        const jointDismissElements = document.querySelectorAll('[data-joint-inspection-dismiss]');
        jointDismissElements.forEach(element => {
            element.addEventListener('click', () => {
                shouldSubmitAfterJointInspection = false;
                closeJointInspectionModal();
            });
        });

        const jointObservationRadios = document.querySelectorAll('input[name="has_additional_observations"]');
        const jointObservationWrapper = document.getElementById('jointInspectionObservationsWrapper');
        jointObservationRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === '1' && radio.checked) {
                    jointObservationWrapper?.classList.remove('hidden');
                }
                if (radio.value === '0' && radio.checked) {
                    jointObservationWrapper?.classList.add('hidden');
                }
                markJointInspectionAsDirty();
            });
        });

        // Tab switching functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        tabButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                if (this.disabled || this.classList.contains('tab-button--disabled')) {
                    const disabledMessage = this.dataset.disabledMessage || this.getAttribute('title');
                    if (disabledMessage) {
                        alert(disabledMessage);
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }

                const tabId = this.getAttribute('data-tab');
                const targetTab = document.getElementById(`${tabId}-tab`);
                if (!targetTab) {
                    return;
                }

                // Deactivate all tabs
                tabButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-selected', 'false');
                });
                tabContents.forEach(content => content.classList.remove('active'));

                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                targetTab.classList.add('active');
            });
        });

        // Close modal button
        const closeModalBtn = document.getElementById('closeModal');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                // In a real application, this would close the modal
                alert('Modal closed');
            });
        }

        // Print Planning Recommendation using a new window
        const printBtn = document.getElementById('print-planning-recommendation');
        if (printBtn) {
            printBtn.addEventListener('click', function(e) {
                e.preventDefault();
                try {
                    console.log('Print button clicked'); // Debug log

                    // Create a new window with just the planning recommendation content
                    const printWindow = window.open('', '_blank', 'height=800,width=800');

                    // Get the direct URL to the planning recommendation with the print parameter
                    const applicationId = document.getElementById('application_id').value;
                    const printUrl =
                        `{{ url('sub-actions/planning-recommendation/print') }}/${applicationId}?url=print`;

                    // Navigate the new window to this URL
                    printWindow.location.href = printUrl;

                    // Set up listener for when content is loaded
                    printWindow.onload = function() {
                        setTimeout(function() {
                            printWindow.focus();
                            printWindow.print();
                        }, 1000); // Short delay to ensure content is fully loaded
                    };
                } catch (error) {
                    console.error('Error printing:', error);
                    alert('There was an error during printing. See console for details.');
                }
            });
        }

        // Toggle reason field based on decision and observations field
        const decisionRadios = document.querySelectorAll('input[name="decision"]');
        const reasonContainer = document.getElementById('reasonContainer');
        const observationsContainer = document.getElementById('observationsContainer');
        
        decisionRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (reasonContainer) {
                    reasonContainer.style.display = (this.value === 'Declined') ? 'block' : 'none';
                }
                if (observationsContainer) {
                    observationsContainer.style.display = (this.value === 'Approved') ? 'block' : 'none';
                }

                if (this.value === 'Approved') {
                    if (!this.disabled && this.checked && !jointInspectionReportSavedForSubmission) {
                        openSubApplicationJointInspectionModal();
                    }
                } else {
                    shouldSubmitAfterJointInspection = false;
                    closeJointInspectionModal();
                }
            });
        });

        // FIX: Add event listener for saving observations - with improved debugging
        const saveObservationsBtn = document.getElementById('saveObservations');
        if (saveObservationsBtn) {
            console.log("Found save observations button", saveObservationsBtn);
            
            // IMPORTANT: Remove any existing listeners to avoid conflicts
            const newBtn = saveObservationsBtn.cloneNode(true);
            saveObservationsBtn.parentNode.replaceChild(newBtn, saveObservationsBtn);
            
            // Add the click handler to the new button
            newBtn.addEventListener('click', function() {
                console.log("Save Observations button clicked");
                
                const applicationId = document.getElementById('application_id').value;
                const additionalObservations = document.getElementById('additionalObservations').value;
                
                console.log("Application ID:", applicationId);
                console.log("Observations:", additionalObservations);
                
                if (!applicationId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Application ID not found'
                    });
                    return;
                }
                
                // Show loading indicator
                newBtn.disabled = true;
                newBtn.textContent = 'Saving...';
                
                // Define the CSRF token explicitly from meta tag
                const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const formToken = document.querySelector('input[name="_token"]')?.value;
                const csrfToken = formToken || metaToken || '{{ csrf_token() }}';
                
                console.log("Using CSRF token:", csrfToken ? "Present" : "Missing");
                
                // Direct fetch with debug logs
                fetch('{{ route("sub_pr_memos.save-observations") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        sub_application_id: applicationId,
                        additional_observations: additionalObservations
                    })
                })
                .then(response => {
                    console.log("Response status:", response.status);
                    // Don't throw on non-200, let the json parser try
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error("Error parsing JSON:", e);
                            console.log("Raw response:", text);
                            return { success: false, message: "Invalid server response" };
                        }
                    });
                })
                .then(data => {
                    console.log("Response data:", data);
                    newBtn.disabled = false;
                    newBtn.textContent = 'Save Observations';
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Additional observations saved successfully',
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to save observations'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error saving observations:', error);
                    newBtn.disabled = false;
                    newBtn.textContent = 'Save Observations';
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while saving observations. See console for details.'
                    });
                });
            });
            
            // Also add direct onclick handler as backup
            newBtn.onclick = function() {
                console.log("Save Observations clicked via onclick");
            };
        } else {
            console.error('Save Observations button not found in the DOM');
        }
        
        // Direct onclick handlers for the decline reason buttons
        const saveDeclineReasonsBtn = document.getElementById('saveDeclineReasons');
        if (saveDeclineReasonsBtn) {
            console.log("Adding click handler to Save Reasons button");
            saveDeclineReasonsBtn.onclick = function() {
                console.log("Save Reasons button clicked");
                saveDeclineReasons(false);
            };
        }
        
        const saveAndViewBtn = document.getElementById('saveAndViewDeclineReasons');
        if (saveAndViewBtn) {
            console.log("Adding click handler to Save & View Memo button");
            saveAndViewBtn.onclick = function() {
                console.log("Save & View Memo button clicked");
                saveDeclineReasons(true);
            };
        }
    });

    // Separate the form handling function
    function handlePlanningRecommendation(e) {
        if (e) {
            e.preventDefault();
        }

        const decisionInput = document.querySelector('input[name="decision"]:checked');
        if (!decisionInput) {
            Swal.fire({
                icon: 'warning',
                title: 'No decision selected',
                text: 'Please choose to approve or decline before submitting.',
                confirmButtonColor: '#10B981'
            });
            return false;
        }

        const decision = decisionInput.value;

        if (decision === 'Approved' && !jointInspectionReportSavedForSubmission) {
            shouldSubmitAfterJointInspection = true;
            openSubApplicationJointInspectionModal();
            Swal.fire({
                icon: 'info',
                title: 'Joint site inspection required',
                text: 'Please complete and save the joint site inspection report before submitting this approval.',
                confirmButtonColor: '#10B981'
            });
            return false;
        }

        shouldSubmitAfterJointInspection = false;
        submitPlanningRecommendation(decision);
        return false;
    }

    function submitPlanningRecommendation(decision) {
        showPreloader();
        const submitBtn = document.getElementById('planningRecommendationSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        const applicationId = document.getElementById('application_id').value;
        const approvalDate = document.getElementById('approval-date').value;
        const comments = document.getElementById('comments')?.value || '';

        fetch('{{ url('sub-actions/planning-recommendation/update') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                application_id: applicationId,
                status: decision,
                approval_date: approvalDate,
                comments: comments
            })
        })
            .then(response => response.json())
            .then(data => {
                hidePreloader();
                if (submitBtn) {
                    submitBtn.disabled = false;
                }

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Planning recommendation updated successfully!',
                        confirmButtonColor: '#10B981'
                    }).then(() => {
                        if (decision === 'Approved') {
                            const latestJointInspectionUrl = jointInspectionReportUrl || window.jointInspectionExistingReportUrl || `{{ route('sub-actions.planning-recommendation.joint-inspection.show', $application->id) }}`;
                            window.location.href = latestJointInspectionUrl;
                        } else if (decision === 'Declined') {
                            window.location.href = `{{ url('sub_pr_memos/declination') }}?id=${applicationId}`;
                        } else {
                            window.location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Something went wrong!',
                        confirmButtonColor: '#EF4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hidePreloader();
                if (submitBtn) {
                    submitBtn.disabled = false;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while updating planning recommendation.',
                    confirmButtonColor: '#EF4444'
                });
            });
    }

    function showPreloader() {
        Swal.fire({
            title: 'Processing...',
            html: 'Approval',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function hidePreloader() {
        Swal.close();
    }
    
    // Add the missing toggleModal and toggleModalEnhanced functions
    function toggleModal(show) {
        const modal = document.getElementById('declineReasonModal');
        if (!modal) return console.error("Modal not found");
        
        if (show) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        } else {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }
    
    // Enhanced modal toggle that also refreshes inputs
    function toggleModalEnhanced(show) {
        toggleModal(show);
        if (show) {
            // Initialize details sections visibility based on checkbox state
            document.querySelectorAll('.decline-reason-check').forEach(check => {
                const detailsId = check.id.replace('Check', 'Details');
                const detailsSection = document.getElementById(detailsId);
                if (detailsSection) {
                    detailsSection.style.display = check.checked ? 'block' : 'none';
                }
            });
            
            // Make sure close buttons work
            const closeButtons = document.querySelectorAll('#closeDeclineModal, #cancelDeclineReasons');
            closeButtons.forEach(button => {
                button.onclick = function() {
                    toggleModal(false);
                };
            });
        }
    }
    
    // Add toggleDetails function for checkbox toggles
    function toggleDetails(checkbox, detailsId) {
        const detailsSection = document.getElementById(detailsId);
        if (detailsSection) {
            detailsSection.style.display = checkbox.checked ? 'block' : 'none';
        }
    }

    // Add the missing saveDeclineReasons function for sub-applications
    function saveDeclineReasons(viewMemo = false) {
        console.log("saveDeclineReasons called with viewMemo =", viewMemo); // Debug log
        
        // Gather all reason data
        const applicationId = document.getElementById('application_id').value;
        console.log("Sub-Application ID:", applicationId); // Debug log
        
        // Main reason flags
        const accessibilitySelected = document.getElementById('accessibilityCheck')?.checked ? 1 : 0;
        const landUseSelected = document.getElementById('conformityCheck')?.checked ? 1 : 0;
        const utilitySelected = document.getElementById('utilityCheck')?.checked ? 1 : 0;
        const roadReservationSelected = document.getElementById('roadReservationCheck')?.checked ? 1 : 0;
        
        // Check if at least one reason is selected
        if (!accessibilitySelected && !landUseSelected && !utilitySelected && !roadReservationSelected) {
            Swal.fire({
                icon: 'warning',
                title: 'No Reasons Selected',
                text: 'Please select at least one reason for declining this application.',
                confirmButtonColor: '#10B981'
            });
            return;
        }
        
        // Get form fields - use optional chaining to prevent errors
        const accessibilitySpecificDetails = document.getElementById('accessibilitySpecificDetails')?.value || '';
        const accessibilityObstructions = document.getElementById('accessibilityObstructions')?.value || '';
        
        const landUseDetails = document.getElementById('landUseDetails')?.value || '';
        const landUseDeviations = document.getElementById('landUseDeviations')?.value || '';
        
        const utilityIssueDetails = document.getElementById('utilityIssueDetails')?.value || '';
        const utilityTypeDetails = document.getElementById('utilityTypeDetails')?.value || '';
        
        const roadReservationIssues = document.getElementById('roadReservationIssues')?.value || '';
        const roadMeasurements = document.getElementById('roadMeasurements')?.value || '';
        
        // Generate the formatted reason text
        let reasonText = "In view of the following deficiencies, the application for Sectional Titling cannot be recommended for approval at this time:\n\n";
        let reasonCount = 1;
        
        if (accessibilitySelected) {
            reasonText += reasonCount + ". Accessibility Issues\n";
            reasonText += "• Condition: The property/site must have adequate accessibility to ensure ease of movement and compliance with urban planning standards.\n";
            reasonText += "• Findings:\n";
            
            if (accessibilitySpecificDetails) {
                reasonText += "  • " + accessibilitySpecificDetails + "\n";
            }
            
            if (accessibilityObstructions) {
                reasonText += "  • Obstructions/barriers: " + accessibilityObstructions + "\n";
            }
            
            if (!accessibilitySpecificDetails && !accessibilityObstructions) {
                reasonText += "  • The property lacks adequate accessibility features required by planning standards.\n";
            }
            
            reasonText += "• Conclusion: The property/site does not satisfy the accessibility requirement.\n\n";
            reasonCount++;
        }
        
        if (landUseSelected) {
            // ...similar pattern for land use...
            reasonText += reasonCount + ". Land Use Conformity Issues\n";
            reasonText += "• Condition: The property/site must conform to the existing land use designation of the area as per the Kano State Physical Development Plan.\n";
            reasonText += "• Findings:\n";
            
            if (landUseDetails) {
                reasonText += "  • " + landUseDetails + "\n";
            }
            
            if (landUseDeviations) {
                reasonText += "  • " + landUseDeviations + "\n";
            }
            
            if (!landUseDetails && !landUseDeviations) {
                reasonText += "  • The property does not conform to the approved land use plan for the area.\n";
            }
            
            reasonText += "• Conclusion: The property/site does not conform to the existing land use regulations.\n\n";
            reasonCount++;
        }
        
        if (utilitySelected) {
            // ...similar pattern for utility...
            reasonText += reasonCount + ". Utility Line Interference\n";
            reasonText += "• Condition: The property/site must not transverse or interfere with existing utility lines (e.g., electricity, water, sewage).\n";
            reasonText += "• Findings:\n";
            
            if (utilityIssueDetails) {
                reasonText += "  • " + utilityIssueDetails + "\n";
            }
            
            if (utilityTypeDetails) {
                reasonText += "  • " + utilityTypeDetails + "\n";
            }
            
            if (!utilityIssueDetails && !utilityTypeDetails) {
                reasonText += "  • The property interferes with existing utility infrastructure in the area.\n";
            }
            
            reasonText += "• Conclusion: The property/site violates the no-transverse utility line condition.\n\n";
            reasonCount++;
        }
        
        if (roadReservationSelected) {
            // ...similar pattern for road reservation...
            reasonText += reasonCount + ". Road Reservation Issues\n";
            reasonText += "• Condition: The property/site must have an adequate access road or comply with minimum road reservation standards as stipulated in KAMMA guidelines.\n";
            reasonText += "• Findings:\n";
            
            if (roadReservationIssues) {
                reasonText += "  • " + roadReservationIssues + "\n";
            }
            
            if (roadMeasurements) {
                reasonText += "  • Measurements: " + roadMeasurements + "\n";
            }
            
            if (!roadReservationIssues && !roadMeasurements) {
                reasonText += "  • The property lacks an adequate access road network as required by planning standards.\n";
            }
            
            reasonText += "• Conclusion: The property/site does not meet the requirements for adequate access road/road reservation.\n\n";
        }
        
        reasonText += "We advise the applicant to address the identified issues and resubmit the application for reconsideration.";
        
        // Set the formatted text to the hidden field
        const commentsField = document.getElementById('comments');
        if (commentsField) {
            commentsField.value = reasonText;
        }
        
        // Update the button text to show reasons are provided
        const openDeclineReasonModal = document.getElementById('openDeclineReasonModal');
        if (openDeclineReasonModal) {
            openDeclineReasonModal.textContent = 'Decline reasons provided ✓';
            openDeclineReasonModal.classList.add('bg-red-50');
        }
        
        // Show preloader
        showPreloader();
        console.log("Sending data to server..."); // Debug log
        
        // Get approval date - with fallback to today's date
        const approvalDate = document.getElementById('approval-date')?.value || new Date().toISOString().split('T')[0];
        
        // Prepare the data for sending
        const postData = {
            sub_application_id: applicationId, // Use sub_application_id for sub-applications
            submitted_by: {{ auth()->id() ?? 1 }},
            approval_date: approvalDate,
            
            // Main reason flags
            accessibility_selected: accessibilitySelected,
            land_use_selected: landUseSelected,
            utility_selected: utilitySelected,
            road_reservation_selected: roadReservationSelected,
            
            // Simplified fields
            access_road_details: accessibilitySpecificDetails,
            pedestrian_details: accessibilityObstructions,
            zoning_details: landUseDetails,
            density_details: landUseDeviations,
            overhead_details: utilityIssueDetails,
            underground_details: utilityTypeDetails,
            right_of_way_details: roadReservationIssues,
            road_width_details: roadMeasurements,
            
            // Complete reason summary
            reason_summary: reasonText
        };
        
        console.log("Posting data:", postData); // Debug log
        
        // Save to database - use sub-specific route
        fetch('{{ route("sub_pr_memos.declination.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(postData)
        })
        .then(response => {
            console.log("Response status:", response.status); // Debug log
            return response.json();
        })
        .then(data => {
            console.log("Response data:", data); // Debug log
            hidePreloader();
            
            if (data.success) {
                // Hide the modal
                const declineReasonModal = document.getElementById('declineReasonModal');
                if (declineReasonModal) {
                    declineReasonModal.classList.add('hidden');
                    declineReasonModal.style.display = 'none';
                }
                
                // Handle redirect based on viewMemo parameter
                if (viewMemo) {
                    console.log("Redirecting to declination memo..."); // Debug log
                    // Redirect immediately to the declination memo for sub-application
                    window.location.href = `{{ url('sub_pr_memos/declination') }}?id=${applicationId}`;
                } else {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Decline reasons saved successfully!',
                        confirmButtonColor: '#10B981'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to save decline reasons.',
                    confirmButtonColor: '#EF4444'
                });
            }
        })
        .catch(error => {
            console.error('Error saving decline reasons:', error); // Debug log
            hidePreloader();
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while saving decline reasons.',
                confirmButtonColor: '#EF4444'
            });
        });
    }

    // Add direct event handlers when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Direct onclick handlers for the decline reason buttons
        const saveDeclineReasonsBtn = document.getElementById('saveDeclineReasons');
        if (saveDeclineReasonsBtn) {
            console.log("Adding click handler to Save Reasons button");
            saveDeclineReasonsBtn.onclick = function() {
                console.log("Save Reasons button clicked");
                saveDeclineReasons(false);
            };
        }
        
        const saveAndViewBtn = document.getElementById('saveAndViewDeclineReasons');
        if (saveAndViewBtn) {
            console.log("Adding click handler to Save & View Memo button");
            saveAndViewBtn.onclick = function() {
                console.log("Save & View Memo button clicked");
                saveDeclineReasons(true);
            };
        }
    });

    // Create global saveObservations function for direct onclick usage
    function saveObservations() {
        console.log("saveObservations global function called");
        const applicationId = document.getElementById('application_id').value;
        const additionalObservations = document.getElementById('additionalObservations').value;
        const saveObservationsBtn = document.getElementById('saveObservations');
        
        if (!applicationId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Application ID not found'
            });
            return;
        }
        
        // Show loading indicator
        if (saveObservationsBtn) {
            saveObservationsBtn.disabled = true;
            saveObservationsBtn.textContent = 'Saving...';
        }
        
        // Define the CSRF token explicitly
        const csrfToken = document.querySelector('input[name="_token"]')?.value || '{{ csrf_token() }}';
        
        fetch('{{ route("sub_pr_memos.save-observations") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                sub_application_id: applicationId,
                additional_observations: additionalObservations
            })
        })
        .then(response => response.json())
        .then(data => {
            if (saveObservationsBtn) {
                saveObservationsBtn.disabled = false;
                saveObservationsBtn.textContent = 'Save Observations';
            }
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Additional observations saved successfully',
                    timer: 1500
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to save observations'
                });
            }
        })
        .catch(error => {
            console.error('Error saving observations:', error);
            if (saveObservationsBtn) {
                saveObservationsBtn.disabled = false;
                saveObservationsBtn.textContent = 'Save Observations';
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while saving observations'
            });
        });
    }
</script>

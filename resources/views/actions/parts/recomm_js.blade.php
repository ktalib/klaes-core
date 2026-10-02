<script>
    console.log("JS loaded successfully"); // Debug log

    const runWhenReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: false });
        } else {
            callback();
        }
    };

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
                const rawMeasurementDimension = entry.measurement_dimension ?? entry.measurement_dimension_display ?? entry.dimension_display ?? '';
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

    if (!jointInspectionDefaultsState || typeof jointInspectionDefaultsState !== 'object') {
        jointInspectionDefaultsState = {};
    }

    jointInspectionDefaultsState = {
        ...jointInspectionDefaultsState,
        available_on_ground: Boolean(jointInspectionDefaultsState.available_on_ground),
        has_additional_observations: Boolean(jointInspectionDefaultsState.has_additional_observations),
        shared_utilities: normalizeSharedUtilities(jointInspectionDefaultsState.shared_utilities),
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

        const mergedValues = {
            ...jointInspectionDefaultsState,
            ...(values || {})
        };

    const normalizedUtilities = normalizeSharedUtilities(mergedValues.shared_utilities);
    jointInspectionDefaultsState.shared_utilities = normalizedUtilities;
    setMeasurementEntries(mergedValues.existing_site_measurement_entries);
        setBoundarySegments(mergedValues.boundary_segments ?? boundarySegmentsState, { markDirty: false });

        const inspectionDateField = form.querySelector('[name="inspection_date"]');
        if (inspectionDateField) {
            inspectionDateField.value = mergedValues.inspection_date || new Date().toISOString().slice(0, 10);
        }

        const textMappings = [
            { selector: '[name="lkn_number"]', value: mergedValues.lkn_number ?? '' },
            { selector: '[name="applicant_name"]', value: mergedValues.applicant_name ?? '' },
            { selector: '[name="location"]', value: mergedValues.location ?? '' },
            { selector: '[name="plot_number"]', value: mergedValues.plot_number ?? '' },
            { selector: '[name="unit_number"]', value: mergedValues.unit_number ?? '' },
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

        const sectionsField = form.querySelector('[name="sections_count"]');
        if (sectionsField) {
            sectionsField.value = mergedValues.sections_count ?? '';
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

        const additionalObservationsField = form.querySelector('[name="additional_observations"]');
        if (additionalObservationsField) {
            additionalObservationsField.value = mergedValues.additional_observations ?? '';
        }

        const observationWrapper = document.getElementById('jointInspectionObservationsWrapper');
        if (observationWrapper) {
            if (hasObservationsValue === '1') {
                observationWrapper.classList.remove('hidden');
            } else {
                observationWrapper.classList.add('hidden');
            }
        }
    }

    function openJointInspectionModal() {
        const modal = document.getElementById('jointInspectionModal');
        if (!modal) {
            return;
        }

        populateJointInspectionForm();
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
        let applicationId = applicationField ? parseInt(applicationField.value, 10) : NaN;
        if (Number.isNaN(applicationId) || applicationId <= 0) {
            applicationId = null;
        }
        payload.application_id = applicationId;
        payload.inspection_date = (form.querySelector('[name="inspection_date"]').value || '').trim();
        payload.lkn_number = (form.querySelector('[name="lkn_number"]').value || '').trim() || null;
        payload.applicant_name = (form.querySelector('[name="applicant_name"]').value || '').trim() || null;
        payload.location = (form.querySelector('[name="location"]').value || '').trim() || null;
        payload.plot_number = (form.querySelector('[name="plot_number"]').value || '').trim() || null;
        const unitField = form.querySelector('[name="unit_number"]');
        const unitValueRaw = unitField ? unitField.value : '';
        const unitValueTrimmed = typeof unitValueRaw === 'string' ? unitValueRaw.trim() : unitValueRaw;
        payload.unit_number = unitValueTrimmed === '' ? null : unitValueTrimmed;
        payload.scheme_number = (form.querySelector('[name="scheme_number"]').value || '').trim() || null;
        const boundaryDescriptionValue = buildBoundaryDescription(boundarySegmentsState);
        const boundaryHasValues = Object.values(boundarySegmentsState).some(value => (value || '').trim() !== '');
        payload.boundary_segments = { ...boundarySegmentsState };
        payload.boundary_description = boundaryHasValues ? boundaryDescriptionValue : null;

    const measurementSummaryField = form.querySelector('[name="existing_site_measurement_summary"]');
    const measurementSummaryValue = measurementSummaryField ? measurementSummaryField.value.trim() : '';
    payload.existing_site_measurement_summary = measurementSummaryValue !== '' ? measurementSummaryValue : null;

    const sectionsField = form.querySelector('[name="sections_count"]');
    const sectionsValue = sectionsField ? sectionsField.value : '';
    payload.sections_count = sectionsValue !== '' ? Number(sectionsValue) : null;

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
                const countValue = entry.count ?? '';
                const countTrimmed = typeof countValue === 'string' ? countValue.trim() : String(countValue ?? '').trim();
                const count = countTrimmed === '' ? '1' : countTrimmed;

                if (description === '' && measurementDimensionValue === '' && dimension === '') {
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

        const form = event.target;
        const submitButton = document.getElementById('jointInspectionSubmit');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        }

        const payload = collectJointInspectionPayload(form);
        const csrfToken = form.querySelector('input[name="_token"]').value;

        fetch('{{ route('planning-recommendation.joint-inspection.store') }}', {
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
                closeJointInspectionModal();

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Save & Generate Report';
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Joint site inspection saved',
                    text: 'The joint site inspection report was saved successfully.',
                    confirmButtonColor: '#10B981'
                }).then(() => {
                    if (shouldSubmitAfterJointInspection) {
                        shouldSubmitAfterJointInspection = false;
                        submitPlanningRecommendation('approve');
                    }
                });
            })
            .catch(error => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Save & Generate Report';
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

        runWhenReady(function() {
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
                    // Deactivate all tabs
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });

            // Close modal button
            const legacyCloseModal = document.getElementById('closeModal');
            if (legacyCloseModal) {
                legacyCloseModal.addEventListener('click', function() {
                    alert('Modal closed');
                });
            }

            // Print Planning Recommendation using a new window
            const printPlanningRecommendationBtn = document.getElementById('print-planning-recommendation');
            if (printPlanningRecommendationBtn) {
                printPlanningRecommendationBtn.addEventListener('click', function(e) {
                e.preventDefault();
                try {
                    console.log('Print button clicked'); // Debug log

                    // Create a new window with just the planning recommendation content
                    const printWindow = window.open('', '_blank', 'height=800,width=800');

                    // Get the direct URL to the planning recommendation with the print parameter
                    const applicationId = document.getElementById('application_id').value;
                    const printUrl =
                        `{{ url('planning-recommendation/print') }}/${applicationId}?url=print`;

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

            // Joint Site Inspection modal controls
            const jointInspectionForm = document.getElementById('jointInspectionForm');
            const approveRadio = document.getElementById('planning-decision-approve');
            const jointDismissElements = document.querySelectorAll('[data-joint-inspection-dismiss]');
            updateJointInspectionReportLink(jointInspectionReportUrl);

            // Form handler moved to _scripts.blade.php to avoid conflicts

            // Bind decline reason modal handlers
            const openDeclineReasonModal = document.getElementById('openDeclineReasonModal');
            if (openDeclineReasonModal) {
                openDeclineReasonModal.addEventListener('click', function() {
                    if (typeof window.toggleModalEnhanced === 'function') {
                        window.toggleModalEnhanced(true);
                    } else {
                        const modal = document.getElementById('declineReasonModal');
                        if (modal) {
                            modal.classList.remove('hidden');
                            modal.style.display = 'flex';
                        }
                    }
                });
            }

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

            if (document.getElementById('jointInspectionModal')) {
                if (approveRadio && !approveRadio.disabled) {
                    approveRadio.addEventListener('change', function() {
                        if (this.checked) {
                            openJointInspectionModal();
                        }
                    });
                }

                jointDismissElements.forEach(element => {
                    element.addEventListener('click', () => {
                        shouldSubmitAfterJointInspection = false;
                        closeJointInspectionModal();
                    });
                });

                if (jointInspectionForm) {
                    jointInspectionForm.addEventListener('submit', saveJointInspectionReport);
                    jointInspectionForm.addEventListener('input', () => {
                        markJointInspectionAsDirty();
                    });
                    jointInspectionForm.addEventListener('change', (event) => {
                        const target = event.target;
                        if (!target) {
                            return;
                        }

                        if (['application_id', '_token'].includes(target.name)) {
                            return;
                        }

                        if (target.type === 'submit') {
                            return;
                        }

                        markJointInspectionAsDirty();
                    });
                }
            }

            const observationRadios = document.querySelectorAll('input[name="has_additional_observations"]');
            const observationWrapper = document.getElementById('jointInspectionObservationsWrapper');
            observationRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    if (radio.value === '1' && radio.checked) {
                        observationWrapper?.classList.remove('hidden');
                    }
                    if (radio.value === '0' && radio.checked) {
                        observationWrapper?.classList.add('hidden');
                    }
                    markJointInspectionAsDirty();
                });
            });

            // Toggle reason field based on decision
            // const decisionRadios = document.querySelectorAll('input[name="decision"]');
            // const reasonContainer = document.getElementById('reasonContainer');
            // decisionRadios.forEach(radio => {
            //     radio.addEventListener('change', function() {
            //         reasonContainer.style.display = (this.value === 'decline') ? 'block' : 'none';
            //     });
            // });

            // DEBUG: Check if modal elements exist
            console.log("Modal button exists:", !!document.getElementById('openDeclineReasonModal'));
            console.log("Modal container exists:", !!document.getElementById('declineReasonModal'));

            // Ensure the modal functionality is properly initialized
            const initializeDeclineModal = function() {
                const declineReasonModal = document.getElementById('declineReasonModal');
                const openDeclineReasonModal = document.getElementById('openDeclineReasonModal');
                const closeDeclineModal = document.getElementById('closeDeclineModal');
                const cancelDeclineReasons = document.getElementById('cancelDeclineReasons');
                const saveDeclineReasons = document.getElementById('saveDeclineReasons');
                const commentsField = document.getElementById('comments');
                
                if (!openDeclineReasonModal || !declineReasonModal) {
                    console.error("Modal elements not found:", {
                        button: openDeclineReasonModal,
                        modal: declineReasonModal
                    });
                    return;
                }
                
                // Show modal when button is clicked - add direct click handler
                openDeclineReasonModal.onclick = function() {
                    console.log("Opening modal");
                    declineReasonModal.classList.remove('hidden');
                    declineReasonModal.style.display = 'flex';
                };
                
                // Close modal functions
                if (closeDeclineModal) {
                    closeDeclineModal.onclick = function() {
                        console.log("Closing modal");
                        declineReasonModal.classList.add('hidden');
                        declineReasonModal.style.display = 'none';
                    };
                }
                
                if (cancelDeclineReasons) {
                    cancelDeclineReasons.onclick = function() {
                        console.log("Canceling reasons");
                        declineReasonModal.classList.add('hidden');
                        declineReasonModal.style.display = 'none';
                    };
                }
                
                // Handle checkbox toggles for showing/hiding details sections
                const reasonChecks = document.querySelectorAll('.decline-reason-check');
                reasonChecks.forEach(check => {
                    check.addEventListener('change', function() {
                        const detailsId = this.id.replace('Check', 'Details');
                        const detailsSection = document.getElementById(detailsId);
                        if (detailsSection) {
                            detailsSection.style.display = this.checked ? 'block' : 'none';
                        }
                    });
                });

                // Handle sub-reason checkboxes
                const subReasonChecks = document.querySelectorAll('.sub-reason-check');
                subReasonChecks.forEach(check => {
                    check.addEventListener('change', function() {
                        const inputsId = this.id.replace('Check', 'Inputs');
                        const inputsSection = document.getElementById(inputsId);
                        if (inputsSection) {
                            inputsSection.style.display = this.checked ? 'block' : 'none';
                        }
                    });
                });
                
                // Save decline reasons
                if (saveDeclineReasons) {
                    saveDeclineReasons.addEventListener('click', function() {
                        let reasonText = "In view of the following deficiencies, the application cannot be recommended for approval at this time:\n\n";
                        let hasReasons = false;
                        let reasonCount = 1;
                        
                        // 1. Accessibility
                        if (document.getElementById('accessibilityCheck').checked) {
                            hasReasons = true;
                            reasonText += reasonCount + ". Accessibility Issues\n";
                            reasonText += "• Condition: The property/site must have adequate accessibility to ensure ease of movement and compliance with urban planning standards.\n";
                            reasonText += "• Findings:\n";
                            
                            // Check for sub-reasons
                            if (document.getElementById('accessRoadCheck').checked) {
                                const location = document.getElementById('accessRoadLocation').value;
                                const type = document.getElementById('accessRoadType').value;
                                const measurement = document.getElementById('accessRoadMeasurement').value;
                                const details = document.getElementById('accessRoadDetails').value;
                                
                                reasonText += "  - Road Access Issues: " + (location ? location + ". " : "") + 
                                             (type ? "Issue type: " + type + ". " : "") + 
                                             (measurement ? "Measurements: " + measurement + ". " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (document.getElementById('pedestrianCheck').checked) {
                                const location = document.getElementById('pedestrianLocation').value;
                                const type = document.getElementById('pedestrianIssueType').value;
                                const measurement = document.getElementById('pedestrianMeasurement').value;
                                const details = document.getElementById('pedestrianDetails').value;
                                
                                reasonText += "  - Pedestrian Movement Issues: " + (location ? location + ". " : "") + 
                                             (type ? "Issue type: " + type + ". " : "") + 
                                             (measurement ? "Measurements: " + measurement + ". " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (!document.getElementById('accessRoadCheck').checked && 
                                !document.getElementById('pedestrianCheck').checked) {
                                reasonText += "  - Inadequate accessibility.\n";
                            }
                            
                            reasonText += "• Conclusion: The property/site does not satisfy the accessibility requirement.\n\n";
                            reasonCount++;
                        }
                        
                        // 2. Land Use Conformity
                        if (document.getElementById('conformityCheck').checked) {
                            hasReasons = true;
                            reasonText += reasonCount + ". Land Use Conformity Issues\n";
                            reasonText += "• Condition: The property/site must conform to the existing land use designation of the area as per the Kano State Physical Development Plan.\n";
                            reasonText += "• Findings:\n";
                            
                            if (document.getElementById('zoningCheck').checked) {
                                const current = document.getElementById('currentZoning').value;
                                const proposed = document.getElementById('proposedUse').value;
                                const details = document.getElementById('zoningDetails').value;
                                
                                reasonText += "  - Zoning Violation: " + 
                                             (current ? "Current zoning: " + current + ". " : "") + 
                                             (proposed ? "Proposed use: " + proposed + ". " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (document.getElementById('densityCheck').checked) {
                                const allowed = document.getElementById('allowedDensity').value;
                                const proposed = document.getElementById('proposedDensity').value;
                                const details = document.getElementById('densityDetails').value;
                                
                                reasonText += "  - Density/Intensity Violation: " + 
                                             (allowed ? "Allowed density: " + allowed + ". " : "") + 
                                             (proposed ? "Proposed density: " + proposed + ". " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (!document.getElementById('zoningCheck').checked && 
                                !document.getElementById('densityCheck').checked) {
                                reasonText += "  - Non-conformity with existing land use regulations.\n";
                            }
                            
                            reasonText += "• Conclusion: The property/site does not conform to the existing land use regulations.\n\n";
                            reasonCount++;
                        }
                        
                        // 3. Utility Line Interference
                        if (document.getElementById('utilityCheck').checked) {
                            hasReasons = true;
                            reasonText += reasonCount + ". Utility Line Interference\n";
                            reasonText += "• Condition: The property/site must not transverse or interfere with existing utility lines (e.g., electricity, water, sewage).\n";
                            reasonText += "• Findings:\n";
                            
                            if (document.getElementById('overheadCheck').checked) {
                                const type = document.getElementById('overheadUtilityType').value;
                                const distance = document.getElementById('overheadDistance').value;
                                const details = document.getElementById('overheadDetails').value;
                                
                                reasonText += "  - Overhead Utility Interference: " + 
                                             (type ? "Utility type: " + type + ". " : "") + 
                                             (distance ? "Distance: " + distance + "m. " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (document.getElementById('undergroundCheck').checked) {
                                const type = document.getElementById('undergroundUtilityType').value;
                                const depth = document.getElementById('undergroundDepth').value;
                                const details = document.getElementById('undergroundDetails').value;
                                
                                reasonText += "  - Underground Utility Interference: " + 
                                             (type ? "Utility type: " + type + ". " : "") + 
                                             (depth ? "Depth: " + depth + "m. " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (!document.getElementById('overheadCheck').checked && 
                                !document.getElementById('undergroundCheck').checked) {
                                reasonText += "  - Interference with utility lines detected.\n";
                            }
                            
                            reasonText += "• Conclusion: The property/site violates the no-transverse utility line condition.\n\n";
                            reasonCount++;
                        }
                        
                        // 4. Road Reservation Issues
                        if (document.getElementById('roadReservationCheck').checked) {
                            hasReasons = true;
                            reasonText += reasonCount + ". Road Reservation Issues\n";
                            reasonText += "• Condition: The property/site must have an adequate access road or comply with minimum road reservation standards as stipulated in KAMMA guidelines.\n";
                            reasonText += "• Findings:\n";
                            
                            if (document.getElementById('rightOfWayCheck').checked) {
                                const required = document.getElementById('requiredSetback').value;
                                const actual = document.getElementById('actualSetback').value;
                                const details = document.getElementById('rightOfWayDetails').value;
                                
                                reasonText += "  - Right-of-Way Encroachment: " + 
                                             (required ? "Required setback: " + required + "m. " : "") + 
                                             (actual ? "Actual/proposed setback: " + actual + "m. " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (document.getElementById('roadWidthCheck').checked) {
                                const required = document.getElementById('requiredWidth').value;
                                const actual = document.getElementById('actualWidth').value;
                                const details = document.getElementById('roadWidthDetails').value;
                                
                                reasonText += "  - Inadequate Road Width: " + 
                                             (required ? "Required width: " + required + "m. " : "") + 
                                             (actual ? "Actual/available width: " + actual + "m. " : "") + 
                                             (details ? details : "") + "\n";
                            }
                            
                            if (!document.getElementById('rightOfWayCheck').checked && 
                                !document.getElementById('roadWidthCheck').checked) {
                                reasonText += "  - Inadequate access road/road reservation.\n";
                            }
                            
                            reasonText += "• Conclusion: The property/site does not meet the requirements for adequate access road/road reservation.\n\n";
                        }
                        
                        reasonText += "We advise the applicant to address the identified issues and resubmit the application for reconsideration.";
                        
                        if (!hasReasons) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No Reasons Selected',
                                text: 'Please select at least one reason for declining this application.',
                                confirmButtonColor: '#10B981'
                            });
                            return;
                        }
                        
                        // Set the formatted text to the hidden field
                        commentsField.value = reasonText;
                        
                        // Update the button text to show reasons are provided
                        openDeclineReasonModal.textContent = 'Decline reasons provided ✓';
                        openDeclineReasonModal.classList.add('bg-red-50');
                        
                        // Hide the modal
                        declineReasonModal.classList.add('hidden');
                        declineReasonModal.style.display = 'none';
                    });
                }
            };

            // Initialize modal functionality
            initializeDeclineModal();

            // Toggle reason field based on decision
            const decisionRadios = document.querySelectorAll('input[name="decision"]');
            const reasonContainer = document.getElementById('reasonContainer');
            decisionRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    reasonContainer.style.display = (this.value === 'decline') ? 'block' : 'none';
                });
            });
    });

        // Ensure buttons work outside DOMContentLoaded too
        window.toggleModal = function(show) {
            const modal = document.getElementById('declineReasonModal');
            if (!modal) return console.error("Modal not found");
            
            if (show) {
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
            } else {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        };
        
        // Toggle sub-reason inputs visibility - improved version with !important flag
        window.toggleSubReason = function(checkbox, inputsId) {
            console.log("toggleSubReason called for", inputsId);
            const inputsSection = document.getElementById(inputsId);
            if (inputsSection) {
                // Use !important to override any conflicting styles
                inputsSection.style.cssText = checkbox.checked ? 'display: block !important' : 'display: none !important';
                console.log("Set display to", inputsSection.style.display, "for", inputsId);
                
                // Double-check after a short delay to ensure it took effect
                setTimeout(() => {
                    if ((checkbox.checked && inputsSection.style.display !== 'block') || 
                        (!checkbox.checked && inputsSection.style.display !== 'none')) {
                        console.warn("Display state didn't match checkbox state, forcing update");
                        inputsSection.style.cssText = checkbox.checked ? 'display: block !important' : 'display: none !important';
                    }
                }, 50);
            } else {
                console.error("Could not find inputs section:", inputsId);
                
                // Try to find by alternative ID patterns
                const alternativeId = inputsId.replace('Inputs', '-inputs');
                const altSection = document.getElementById(alternativeId);
                if (altSection) {
                    console.log("Found alternative section:", alternativeId);
                    altSection.style.cssText = checkbox.checked ? 'display: block !important' : 'display: none !important';
                }
            }
        };

        // Direct check for all sub-reason inputs on modal open
        window.refreshSubReasonInputs = function() {
            document.querySelectorAll('.sub-reason-check').forEach(check => {
                const inputsId = check.id.replace('Check', 'Inputs');
                const inputsSection = document.getElementById(inputsId);
                if (inputsSection) {
                    inputsSection.style.display = check.checked ? 'block' : 'none';
                }
            });
        };

        // Enhanced modal toggle that also refreshes inputs
        window.toggleModalEnhanced = function(show) {
            toggleModal(show);
            if (show) {
                // Initialize all sub-reason visibility states when the modal opens
                setTimeout(initializeAllSubReasonVisibility, 100);
                
                // Add event listeners to all checkboxes in the modal
                document.querySelectorAll('.sub-reason-check').forEach(check => {
                    // Add direct onclick handler for immediate effect
                    check.onclick = function() {
                        const inputsId = this.id.replace('Check', 'Inputs');
                        const inputsSection = document.getElementById(inputsId);
                        if (inputsSection) {
                            // Apply style directly with !important for maximum reliability
                            inputsSection.style.cssText = this.checked ? 'display: block !important' : 'display: none !important';
                        }
                    };
                });
                
                // Force check current states after a slight delay
                setTimeout(function() {
                    document.querySelectorAll('.sub-reason-check').forEach(check => {
                        if (check.checked) {
                            const inputsId = check.id.replace('Check', 'Inputs');
                            const inputsSection = document.getElementById(inputsId);
                            if (inputsSection) {
                                inputsSection.style.cssText = 'display: block !important';
                            }
                        }
                    });
                }, 300);
            }
        };

        // Separate the form handling function
        window.handlePlanningRecommendation = function (e) {
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

            if (decision === 'approve' && !jointInspectionReportSavedForSubmission) {
                shouldSubmitAfterJointInspection = true;
                openJointInspectionModal();
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
        };

        function submitPlanningRecommendation(decision) {
            showPreloader();
            const submitBtn = document.getElementById('planningRecommendationSubmitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            const applicationId = document.getElementById('application_id').value;
            const approvalDate = document.getElementById('approval-date').value;
            const comments = document.getElementById('comments')?.value || '';

            fetch('{{ url('planning-recommendation/update') }}', {
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
                            if (decision === 'approve') {
                                const latestJointInspectionUrl = jointInspectionReportUrl || window.jointInspectionExistingReportUrl || `{{ route('planning-recommendation.joint-inspection.show', $application->id) }}`;
                                window.location.href = latestJointInspectionUrl;
                            } else if (decision === 'decline') {
                                window.location.href = `{{ url('pr_memos/declination') }}?id=${applicationId}`;
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

        // Save decline reasons to database
        function saveDeclineReasons(viewMemo = false) {
            // Gather all reason data
            const applicationId = document.getElementById('application_id').value;
            
            // Main reason flags
            const accessibilitySelected = document.getElementById('accessibilityCheck').checked ? 1 : 0;
            const landUseSelected = document.getElementById('conformityCheck').checked ? 1 : 0;
            const utilitySelected = document.getElementById('utilityCheck').checked ? 1 : 0;
            const roadReservationSelected = document.getElementById('roadReservationCheck').checked ? 1 : 0;
            
            // Simplified form values
            const accessibilitySpecificDetails = document.getElementById('accessibilitySpecificDetails')?.value || '';
            const accessibilityObstructions = document.getElementById('accessibilityObstructions')?.value || '';
            
            const landUseDetails = document.getElementById('landUseDetails')?.value || '';
            const landUseDeviations = document.getElementById('landUseDeviations')?.value || '';
            
            const utilityIssueDetails = document.getElementById('utilityIssueDetails')?.value || '';
            const utilityTypeDetails = document.getElementById('utilityTypeDetails')?.value || '';
            
            const roadReservationIssues = document.getElementById('roadReservationIssues')?.value || '';
            const roadMeasurements = document.getElementById('roadMeasurements')?.value || '';
            
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
            
            // Generate the formatted reason text
            let reasonText = "In view of the following deficiencies, the application cannot be recommended for approval at this time:\n\n";
            let reasonCount = 1;
            
            if (accessibilitySelected) {
                reasonText += reasonCount + ". Accessibility Issues\n";
                reasonText += "• Condition: The property/site must have adequate accessibility to ensure ease of movement and compliance with urban planning standards.\n";
                reasonText += "• Findings:\n";
                
                if (accessibilitySpecificDetails) {
                    reasonText += "  - " + accessibilitySpecificDetails + "\n";
                }
                
                if (accessibilityObstructions) {
                    reasonText += "  - Obstructions/barriers: " + accessibilityObstructions + "\n";
                }
                
                reasonText += "• Conclusion: The property/site does not satisfy the accessibility requirement.\n\n";
                reasonCount++;
            }
            
            if (landUseSelected) {
                reasonText += reasonCount + ". Land Use Conformity Issues\n";
                reasonText += "• Condition: The property/site must conform to the existing land use designation of the area as per the Kano State Physical Development Plan.\n";
                reasonText += "• Findings:\n";
                
                if (landUseDetails) {
                    reasonText += "  - " + landUseDetails + "\n";
                }
                
                if (landUseDeviations) {
                    reasonText += "  - " + landUseDeviations + "\n";
                }
                
                reasonText += "• Conclusion: The property/site does not conform to the existing land use regulations.\n\n";
                reasonCount++;
            }
            
            if (utilitySelected) {
                reasonText += reasonCount + ". Utility Line Interference\n";
                reasonText += "• Condition: The property/site must not transverse or interfere with existing utility lines (e.g., electricity, water, sewage).\n";
                reasonText += "• Findings:\n";
                
                if (utilityIssueDetails) {
                    reasonText += "  - " + utilityIssueDetails + "\n";
                }
                
                if (utilityTypeDetails) {
                    reasonText += "  - " + utilityTypeDetails + "\n";
                }
                
                reasonText += "• Conclusion: The property/site violates the no-transverse utility line condition.\n\n";
                reasonCount++;
            }
            
            if (roadReservationSelected) {
                reasonText += reasonCount + ". Road Reservation Issues\n";
                reasonText += "• Condition: The property/site must have an adequate access road or comply with minimum road reservation standards as stipulated in KAMMA guidelines.\n";
                reasonText += "• Findings:\n";
                
                if (roadReservationIssues) {
                    reasonText += "  - " + roadReservationIssues + "\n";
                }
                
                if (roadMeasurements) {
                    reasonText += "  - Measurements: " + roadMeasurements + "\n";
                }
                
                reasonText += "• Conclusion: The property/site does not meet the requirements for adequate access road/road reservation.\n\n";
            }
            
            reasonText += "We advise the applicant to address the identified issues and resubmit the application for reconsideration.";
            
            // Set the formatted text to the hidden field
            document.getElementById('comments').value = reasonText;
            
            // Show preloader
            showPreloader();
            
            // Save to database with simplified fields
            fetch('{{ route("pr_memos.declination.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    application_id: applicationId,
                    submitted_by: {{ auth()->user()->id ?? 1 }},
                    approval_date: document.getElementById('approval-date').value,
                    
                    // Main reason flags
                    accessibility_selected: accessibilitySelected,
                    land_use_selected: landUseSelected,
                    utility_selected: utilitySelected,
                    road_reservation_selected: roadReservationSelected,
                    
                    // Simplified fields - store in existing database columns for compatibility
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
                })
            })
            .then(response => response.json())
            .then(data => {
                hidePreloader();
                
                if (data.success) {
                    // Update UI to show success
                    const openDeclineReasonModal = document.getElementById('openDeclineReasonModal');
                    if (openDeclineReasonModal) {
                        openDeclineReasonModal.textContent = 'Decline reasons provided ✓';
                        openDeclineReasonModal.classList.add('bg-red-50');
                    }
                    
                    // Hide the modal
                    const declineReasonModal = document.getElementById('declineReasonModal');
                    if (declineReasonModal) {
                        declineReasonModal.classList.add('hidden');
                        declineReasonModal.style.display = 'none';
                    }
                    
                    // Show success message and redirect
                    if (viewMemo) {
                        // Redirect immediately to the declination memo
                        window.location.href = `{{ url('pr_memos/declination') }}?id=${applicationId}`;
                    } else {
                        // Show success message first, then redirect when confirmed
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Decline reasons saved successfully! View the declination memo?',
                            showCancelButton: true,
                            confirmButtonColor: '#10B981',
                            cancelButtonColor: '#6B7280',
                            confirmButtonText: 'View Memo',
                            cancelButtonText: 'Stay Here'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Redirect to declination memo if confirmed
                                window.location.href = `{{ url('pr_memos/declination') }}?id=${applicationId}`;
                            }
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
                console.error('Error saving decline reasons:', error);
                hidePreloader();
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while saving decline reasons.',
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

        // Run this function when page loads to initialize everything properly
        window.initializeAllSubReasonVisibility = function() {
            console.log("Initializing all sub-reason visibility states");
            // First ensure all main reason details are correctly displayed
            document.querySelectorAll('.decline-reason-check').forEach(check => {
                const detailsId = check.id.replace('Check', 'Details');
                const detailsSection = document.getElementById(detailsId);
                if (detailsSection) {
                    detailsSection.style.cssText = check.checked ? 'display: block !important' : 'display: none !important';
                }
            });
            
            // Then ensure all sub-reason inputs are correctly displayed
            document.querySelectorAll('.sub-reason-check').forEach(check => {
                const inputsId = check.id.replace('Check', 'Inputs');
                const inputsSection = document.getElementById(inputsId);
                if (inputsSection) {
                    inputsSection.style.cssText = check.checked ? 'display: block !important' : 'display: none !important';
                }
            });
        };

        // Enhanced modal toggle that also refreshes inputs
        window.toggleModalEnhanced = function(show) {
            toggleModal(show);
            if (show) {
                // Initialize all sub-reason visibility states when the modal opens
                setTimeout(initializeAllSubReasonVisibility, 100);
                
                // Add event listeners to all checkboxes in the modal
                document.querySelectorAll('.sub-reason-check').forEach(check => {
                    // Remove any existing listeners to avoid duplicates
                    const newCheck = check.cloneNode(true);
                    check.parentNode.replaceChild(newCheck, check);
                    
                    // Add the event listener
                    newCheck.addEventListener('change', function() {
                        const inputsId = this.id.replace('Check', 'Inputs');
                        toggleSubReason(this, inputsId);
                    });
                    
                    // Also add onclick for redundancy
                    newCheck.onclick = function() {
                        const inputsId = this.id.replace('Check', 'Inputs');
                        toggleSubReason(this, inputsId);
                    };
                });
            }
        };

        // Add an automatic initialization call
        runWhenReady(function() {
            // Initialize sub-reason visibility when page loads
            setTimeout(initializeAllSubReasonVisibility, 500);
        });

        // Add event listeners for the new buttons
        runWhenReady(function() {
            // Save button - Save decline reasons
            const saveDeclineReasonsBtn = document.getElementById('saveDeclineReasons');
            if (saveDeclineReasonsBtn) {
                saveDeclineReasonsBtn.addEventListener('click', function() {
                    saveDeclineReasons(false);
                });
            }
            
            // Save & View button - Save decline reasons and view memo
            const saveAndViewBtn = document.getElementById('saveAndViewDeclineReasons');
            if (saveAndViewBtn) {
                saveAndViewBtn.addEventListener('click', function() {
                    saveDeclineReasons(true);
                });
            }
        });

        // Simple direct toggle function that works without relying on complex event handlers
        window.toggleDetails = function(checkbox, detailsId) {
            const detailsSection = document.getElementById(detailsId);
            if (detailsSection) {
                detailsSection.style.display = checkbox.checked ? 'block' : 'none';
            }
        };

        // Add event listener for saving observations
        runWhenReady(function() {
            const saveObservationsBtn = document.getElementById('saveObservations');
            if (saveObservationsBtn && saveObservationsBtn.dataset.observationsHandlerAttached !== 'true') {
                console.warn('Save observations fallback handler engaged; primary handler may have failed to attach.');
            }
        });
    </script>
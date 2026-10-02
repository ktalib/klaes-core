<!-- Joint Site Inspection Modal JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Joint Site Inspection Modal functionality
    let currentApplicationId = null;
    let currentSubApplicationId = null;
    let isUnitApplication = false;
    const isPageEditor = String(window.jointInspectionEditorMode || '').toLowerCase() === 'page';
    const pageLockedFieldIds = new Set(['jointInspectionFileNumber', 'jointInspectionApplicant']);
    const boundaryDirections = ['north', 'east', 'south', 'west'];

    // Lookup maps for resolving numeric IDs stored in address components
    const statIdToName = {
        @foreach(DB::connection('sqlsrv')->table('States')->select('StateID','StateName')->get() as $st)
        '{{ $st->StateID }}': '{{ $st->StateName }}',
        @endforeach
    };
    const lgaIdToName = {
        @foreach(DB::connection('sqlsrv')->table('StatLGAs')->select('LGAID','LGAName')->get() as $lg)
        '{{ $lg->LGAID }}': '{{ $lg->LGAName }}',
        @endforeach
    };
    const districtIdToName = {
        @foreach(DB::connection('sqlsrv')->table('districts')->select('id','name')->get() as $dist)
        '{{ $dist->id }}': '{{ $dist->name }}',
        @endforeach
    };
    const EXISTING_STRUCTURE_META_DESCRIPTION = '__existing_structure__';
    const NEED_FOR_EIA_META_DESCRIPTION = '__need_for_eia__';
    const CONVERSION_PURPOSE_LUT_META_DESCRIPTION = '__conversion_purpose_lut__';
    const CONVERSION_RESERVATION_META_DESCRIPTION = '__conversion_reservation_meta__';
    let jsiContext = { isConversion: false, fileNumber: '', source: null };
    window.jsiContext = jsiContext;

    const referenceApi = {
        states: '{{ route('api.reference.states') }}',
        lgas: '{{ route('api.reference.lgas') }}',
        districts: '{{ route('api.reference.districts') }}',
        streets: '{{ route('api.reference.streets') }}',
    };
    const fileNumberInput = document.getElementById('jointInspectionFileNumber');

    const unitSurveyEndpointTemplate = '{{ route('programmes.planning.unit.survey', ['id' => '__UNIT_ID__']) }}';

    async function resolveApplicationId(applicationId, subApplicationId) {
        if (applicationId) {
            return String(applicationId).trim();
        }

        if (!subApplicationId) {
            return '';
        }

        const endpoint = unitSurveyEndpointTemplate.replace('__UNIT_ID__', encodeURIComponent(subApplicationId));

        try {
            const response = await fetch(endpoint, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Unable to load linked primary application details for this unit.');
            }

            if (!payload.success) {
                throw new Error(payload.message || 'Unable to load linked primary application details for this unit.');
            }

            const resolvedId = payload.data?.primary_id ?? null;

            if (!resolvedId) {
                return '';
            }

            return String(resolvedId).trim();
        } catch (error) {
            if (error instanceof SyntaxError) {
                throw new Error('Received an unexpected response while looking up the linked primary application.');
            }
            throw error;
        }
    }

    function hideAllActionMenus() {
        document.querySelectorAll('.action-menu').forEach(menu => menu.classList.add('hidden'));
    }

    function setConversionContext(isConversion, source = null, fileNumber = '') {
        jsiContext.isConversion = Boolean(isConversion);
        jsiContext.fileNumber = fileNumber || '';
        jsiContext.source = source || (jsiContext.isConversion ? 'Conversion Applications' : 'ST One Stop Shop');

        const sourceField = document.getElementById('jointInspectionSource');
        if (sourceField) {
            sourceField.value = jsiContext.source || '';
        }

        const isConversionField = document.getElementById('jointInspectionIsConversion');
        if (isConversionField) {
            isConversionField.value = jsiContext.isConversion ? '1' : '0';
        }

        toggleConversionUi(jsiContext.isConversion);
        filterInspectorOptionsByDepartment(jsiContext.isConversion);
    }

    // Restrict the Inspection Officer dropdown to the department that matches the
    // active JSI context: Sectional Titling for ST Unit, Physical Planning for Conversion.
    function filterInspectorOptionsByDepartment(isConversion) {
        const officerSelect = document.getElementById('jointInspectionOfficerSelect');
        if (!officerSelect) {
            return;
        }

        const wantAttr = isConversion ? 'deptPp' : 'deptSt';
        let selectedHidden = false;

        Array.from(officerSelect.options).forEach(option => {
            // Always keep the empty "Select inspector" placeholder available.
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const belongs = String(option.dataset[wantAttr] || '') === '1';
            option.hidden = !belongs;
            option.disabled = !belongs;

            if (!belongs && option.selected) {
                selectedHidden = true;
            }
        });

        if (selectedHidden) {
            officerSelect.value = '';
        }

        if (typeof syncInspectorRankDisplay === 'function') {
            syncInspectorRankDisplay();
        }
    }

    function applyModalHeaderStyle(isConversion) {
        const header = document.getElementById('jsiModalHeader');
        const title = document.getElementById('jsiModalTitle');
        if (!header) return;

        // Remove previous context classes
        header.classList.remove(
            'bg-amber-50', 'bg-amber-100', 'border-amber-200',
            'bg-blue-50', 'bg-blue-100', 'border-blue-200'
        );
        if (title) {
            title.classList.remove('text-amber-800', 'text-amber-900', 'text-blue-800', 'text-blue-900', 'text-gray-800');
        }

        if (isConversion) {
            header.classList.add('bg-amber-100', 'border-b', 'border-amber-300');
            if (title) {
                title.classList.add('text-amber-900', 'font-bold');
                title.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-200 text-amber-800 mr-2 uppercase tracking-tight">Conversion</span> Joint Site Inspection Report';
            }
        } else {
            header.classList.add('bg-blue-100', 'border-b', 'border-blue-300');
            if (title) {
                title.classList.add('text-blue-900', 'font-bold');
                title.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-200 text-blue-800 mr-2 uppercase tracking-tight">ST Unit</span> Joint Site Inspection Report';
            }
        }
    }

    function toggleConversionUi(isConversion) {
        const conversionSection = document.getElementById('conversionFieldsSection');
        const sectionsCountGroup = document.getElementById('sectionsCountGroup');
        const nonConversionReservationSection = document.getElementById('nonConversionReservationSection');
        const measurementEntriesSection = document.getElementById('measurementEntriesSection');
        const measurementSummarySection = document.getElementById('measurementSummarySection');
        const sharedAreasSection = document.getElementById('sharedAreasSection');
        const schemeNumberGroup = document.getElementById('schemeNumberGroup');

        if (conversionSection) {
            conversionSection.classList.toggle('hidden', !isConversion);
        }

        // Sections/measurement/shared areas/EIAR: show for ST OSS, hide for conversion
        // NOTE: EIAR is now for Conversion only per user request.
        const needForEiaSection = document.getElementById('needForEiaSection');

        [sectionsCountGroup, nonConversionReservationSection, measurementEntriesSection, measurementSummarySection, sharedAreasSection].forEach(section => {
            if (!section) return;
            section.classList.toggle('hidden', isConversion);
        });

        if (needForEiaSection) {
            needForEiaSection.classList.toggle('hidden', !isConversion);
        }

        // The old scheme number group is now always hidden — ST OSS uses the simple row, Conversion doesn't need it
        if (schemeNumberGroup) {
            schemeNumberGroup.classList.add('hidden');
        }

        const measurementSummaryField = document.getElementById('jointInspectionMeasurementSummary');
        if (measurementSummaryField) {
            if (isConversion) {
                measurementSummaryField.value = 'Not applicable for conversion inspections.';
            } else if (!measurementSummaryField.value || measurementSummaryField.value.trim() === '' || measurementSummaryField.value === 'Not applicable for conversion inspections.') {
                measurementSummaryField.value = 'The following existing site measurements were observed during the joint site inspection:';
            }
        }

        // Show/hide file number selector button and update hint text
        const selectFileBtn = document.getElementById('jsiSelectFileNumberBtn');
        const fileNumberHint = document.getElementById('jsiFileNumberHint');
        const fileNumberField = document.getElementById('jointInspectionFileNumber');

        if (selectFileBtn) {
            selectFileBtn.classList.toggle('hidden', !isConversion);
        }

        if (fileNumberHint) {
            fileNumberHint.textContent = isConversion
                ? 'Use the Select button to pick a file number.'
                : 'File number is locked to the current application.';
        }

        if (fileNumberField && isConversion) {
            // For conversion context, keep the field readonly — only the Select button should be used
            fileNumberField.readOnly = true;
            fileNumberField.classList.add('bg-gray-50', 'cursor-not-allowed');
            fileNumberField.classList.remove('focus:ring-2', 'focus:ring-blue-500');
            fileNumberField.placeholder = 'Use Select button to pick a file number';
        }

        // Toggle address sections: Conversion uses structured builders, ST OSS uses simple text inputs
        const addressBuildersRow = document.getElementById('jsiAddressBuildersRow');
        const addressSimpleRow = document.getElementById('jsiAddressSimpleRow');

        if (addressBuildersRow) {
            addressBuildersRow.classList.toggle('hidden', !isConversion);
        }
        if (addressSimpleRow) {
            addressSimpleRow.classList.toggle('hidden', isConversion);
        }
    }

    function syncPrivateLayoutVisibility() {
        const purposeSelect = document.getElementById('conversionPurpose');
        const privateLayoutWrapper = document.getElementById('privateLayoutWrapper');

        if (!purposeSelect || !privateLayoutWrapper) {
            return;
        }

        const requiresLayout = purposeSelect.value === 'Private Layout';
        privateLayoutWrapper.classList.toggle('hidden', !requiresLayout);
    }

    function calculateAreaFromInputs(lengthInput, widthInput, targetInput) {
        if (!lengthInput || !widthInput || !targetInput) {
            return;
        }

        const length = parseFloat(lengthInput.value);
        const width = parseFloat(widthInput.value);

        if (Number.isFinite(length) && Number.isFinite(width) && length >= 0 && width >= 0) {
            targetInput.value = (length * width).toFixed(2);
        } else {
            targetInput.value = '';
        }
    }

    function parseMeasurementInput(raw) {
        if (!raw || typeof raw !== 'string') {
            return [];
        }

        return raw
            .split(/[xX×]/)
            .map(part => part.trim())
            .filter(part => part !== '')
            .map(part => Number(part))
            .filter(num => Number.isFinite(num) && num >= 0);
    }

    function normalizeMeasurementValue(value) {
        if (value === null || typeof value === 'undefined' || String(value).trim() === '') {
            return '';
        }

        const numeric = Number(value);

        return Number.isFinite(numeric)
            ? String(numeric)
            : String(value).trim();
    }

    function syncSiteMeasurementField(config, options = {}) {
        const inputField = document.getElementById(config.inputId);
        const lengthField = document.getElementById(config.lengthId);
        const widthField = document.getElementById(config.widthId);
        const areaField = document.getElementById(config.areaId);
        const autoFillArea = options.autoFillArea !== false;

        if (!inputField) {
            return { valid: true, raw: '', values: [] };
        }

        const raw = String(inputField.value || '').trim();

        if (lengthField) {
            lengthField.value = '';
        }

        if (widthField) {
            widthField.value = '';
        }

        if (raw === '') {
            return { valid: true, raw, values: [] };
        }

        const values = parseMeasurementInput(raw);

        if (values.length === 2) {
            if (lengthField) {
                lengthField.value = values[0];
            }

            if (widthField) {
                widthField.value = values[1];
            }

            if (autoFillArea && areaField && String(areaField.value || '').trim() === '') {
                areaField.value = (values[0] * values[1]).toFixed(2);
            }

            return { valid: true, raw, values };
        }

        // Freeform dimensions are persisted in the length column for legacy/converted data.
        if (lengthField) {
            lengthField.value = raw;
        }

        if (widthField) {
            widthField.value = '';
        }

        return { valid: true, raw, values: [] };
    }

    function syncAllSiteMeasurementFields(options = {}) {
        const configs = [
            {
                inputId: 'existingMeasurementInput',
                lengthId: 'existingSiteLength',
                widthId: 'existingSiteWidth',
                areaId: 'existingSiteArea',
                label: 'Existing site measurement',
            },
            {
                inputId: 'recommendedMeasurementInput',
                lengthId: 'recommendedSiteLength',
                widthId: 'recommendedSiteWidth',
                areaId: 'recommendedSiteArea',
                label: 'Recommended site measurement',
            },
        ];

        const results = configs.map(config => syncSiteMeasurementField(config, options));
        const firstInvalid = results.find(result => !result.valid);

        return {
            valid: !firstInvalid,
            firstInvalid,
            results,
        };
    }

    function populateSiteMeasurementInput(inputId, lengthValue, widthValue) {
        const inputField = document.getElementById(inputId);

        if (!inputField) {
            return;
        }

        const parts = [normalizeMeasurementValue(lengthValue), normalizeMeasurementValue(widthValue)]
            .filter(value => value !== '');

        inputField.value = parts.join(' x ');
    }

    function isExistingStructureMetaDescription(description) {
        return String(description || '').trim().toLowerCase() === EXISTING_STRUCTURE_META_DESCRIPTION;
    }

    function isNeedForEiaMetaDescription(description) {
        return String(description || '').trim().toLowerCase() === NEED_FOR_EIA_META_DESCRIPTION;
    }

    function isConversionPurposeLutMetaDescription(description) {
        return String(description || '').trim().toLowerCase() === CONVERSION_PURPOSE_LUT_META_DESCRIPTION;
    }

    function isConversionReservationMetaDescription(description) {
        return String(description || '').trim().toLowerCase() === CONVERSION_RESERVATION_META_DESCRIPTION;
    }

    function isMetaMeasurementDescription(description) {
        return isExistingStructureMetaDescription(description)
            || isNeedForEiaMetaDescription(description)
            || isConversionPurposeLutMetaDescription(description)
            || isConversionReservationMetaDescription(description);
    }

    function syncReservationVisibility() {
        const selected = document.querySelector('input[name="reservation_required"]:checked');
        const detailsWrapper = document.getElementById('reservationDetailsWrapper');

        if (!detailsWrapper) {
            return;
        }

        const isYes = selected && selected.value === '1';
        detailsWrapper.classList.toggle('hidden', !isYes);
    }

    function syncRightOfWayOtherVisibility() {
        const commentSelect = document.getElementById('recommendedCommentStatus');
        const metersWrapper = document.getElementById('howManyMetersWrapper');
        const metersInput = document.getElementById('howManyMeters');

        if (!metersWrapper) {
            return;
        }

        const commentValue = commentSelect ? String(commentSelect.value || '').trim() : '';
        const showMeters = commentValue === 'Not Adequate';
        metersWrapper.classList.toggle('hidden', !showMeters);

        if (!showMeters && metersInput) {
            metersInput.value = '';
        }
    }

    function syncRoadCategoryOtherVisibility() {
        const roadCategorySelect = document.getElementById('conversionRoadCategory');
        const otherWrapper = document.getElementById('conversionRoadCategoryOtherWrapper');
        const otherField = document.getElementById('conversionRoadCategoryOther');

        if (!roadCategorySelect || !otherWrapper) {
            return;
        }

        const hasSelection = String(roadCategorySelect.value || '').trim() !== '';
        otherWrapper.classList.toggle('hidden', !hasSelection);

        if (!hasSelection && otherField) {
            otherField.value = '';
        }
    }

    function syncNonConversionReservationVisibility() {
        const selected = document.querySelector('input[name="non_conversion_reservation_required"]:checked');
        const detailsWrapper = document.getElementById('nonConversionReservationDetailsWrapper');

        if (!detailsWrapper) {
            return;
        }

        const isYes = selected && selected.value === '1';
        detailsWrapper.classList.toggle('hidden', !isYes);

        if (!isYes) {
            const reservationSelect = document.getElementById('jointInspectionRoadReservation');
            const otherField = document.getElementById('jointInspectionRoadReservationOther');
            if (reservationSelect) {
                reservationSelect.value = '';
            }
            if (otherField) {
                otherField.value = '';
            }
        }

        syncNonConversionReservationOtherVisibility();
    }

    function syncNonConversionReservationOtherVisibility() {
        const reservationSelect = document.getElementById('jointInspectionRoadReservation');
        const otherWrapper = document.getElementById('jointInspectionRoadReservationOtherWrapper');

        if (!reservationSelect || !otherWrapper) {
            return;
        }

        const showOther = String(reservationSelect.value || '').trim().toUpperCase() === 'OTHER';
        otherWrapper.classList.toggle('hidden', !showOther);

        if (!showOther) {
            const otherField = document.getElementById('jointInspectionRoadReservationOther');
            if (otherField) {
                otherField.value = '';
            }
        }
    }

    function applyNonConversionReservationFromValue(rawValue) {
        const normalizedValue = String(rawValue || '').trim();
        const reservationSelect = document.getElementById('jointInspectionRoadReservation');
        const otherField = document.getElementById('jointInspectionRoadReservationOther');

        const yesRadio = document.querySelector('input[name="non_conversion_reservation_required"][value="1"]');
        const noRadio = document.querySelector('input[name="non_conversion_reservation_required"][value="0"]');

        if (!normalizedValue) {
            if (yesRadio) {
                yesRadio.checked = false;
            }
            if (noRadio) {
                noRadio.checked = true;
            }
            if (reservationSelect) {
                reservationSelect.value = '';
            }
            if (otherField) {
                otherField.value = '';
            }
            syncNonConversionReservationVisibility();
            return;
        }

        if (yesRadio) {
            yesRadio.checked = true;
        }
        if (noRadio) {
            noRadio.checked = false;
        }

        let matchedOptionValue = '';
        if (reservationSelect) {
            const options = Array.from(reservationSelect.options || []);
            const foundOption = options.find(option => String(option.value || '').trim().toUpperCase() === normalizedValue.toUpperCase());

            if (foundOption) {
                matchedOptionValue = foundOption.value;
                reservationSelect.value = foundOption.value;
            } else {
                reservationSelect.value = 'OTHER';
                matchedOptionValue = 'OTHER';
            }
        }

        if (otherField) {
            otherField.value = matchedOptionValue === 'OTHER' ? normalizedValue : '';
        }

        syncNonConversionReservationVisibility();
        syncNonConversionReservationOtherVisibility();
    }

    function collectConversionPurposeLutPayload() {
        const detailedLandUseField = document.getElementById('conversionDetailedLandUse');
        const purposeLutField = document.getElementById('conversionPurposeLut');
        const purposeLutOtherField = document.getElementById('conversionPurposeLutOther');
        const selectedPurpose = purposeLutField ? String(purposeLutField.value || '').trim() : '';
        const specifiedPurpose = purposeLutOtherField ? String(purposeLutOtherField.value || '').trim() : '';
        const useSpecifiedPurpose = isPurposeOtherOption(selectedPurpose) && specifiedPurpose !== '';

        return {
            detailed_land_use_id: detailedLandUseField ? String(detailedLandUseField.value || '').trim() : '',
            purpose_lut: useSpecifiedPurpose ? specifiedPurpose : selectedPurpose,
            purpose_lut_option: selectedPurpose,
            purpose_lut_other: specifiedPurpose
        };
    }

    function buildConversionPurposeLutMetaEntry(payload) {
        if (!payload || !payload.purpose_lut) {
            return null;
        }

        const detail = {
            detailed_land_use_id: payload.detailed_land_use_id || '',
            purpose_lut_option: payload.purpose_lut_option || '',
            purpose_lut_other: payload.purpose_lut_other || ''
        };

        return {
            sn: 1,
            description: CONVERSION_PURPOSE_LUT_META_DESCRIPTION,
            count: '1',
            measurement_dimension: payload.purpose_lut,
            dimension: JSON.stringify(detail)
        };
    }

    function extractConversionPurposeLutMetaFromEntries(entries = []) {
        if (!Array.isArray(entries) || entries.length === 0) {
            return null;
        }

        return entries.find(entry => isConversionPurposeLutMetaDescription(entry?.description || '')) || null;
    }

    function collectConversionReservationPayload() {
        const reservationRequired = document.querySelector('input[name="reservation_required"]:checked');
        const roadCategoryField = document.getElementById('conversionRoadCategory');
        const roadCategoryOtherField = document.getElementById('conversionRoadCategoryOther');
        const existingReservationField = document.getElementById('conversionRightOfWay');
        const recommendedReservationField = document.getElementById('conversionRecommendedReservation');
        const recommendedCommentStatusField = document.getElementById('recommendedCommentStatus');
        const howManyMetersField = document.getElementById('howManyMeters');

        const roadCategoryOption = roadCategoryField ? String(roadCategoryField.value || '').trim() : '';
        const observedRoadCategory = roadCategoryOtherField ? String(roadCategoryOtherField.value || '').trim() : '';
        const roadCategory = observedRoadCategory || roadCategoryOption;
        const existingReservation = existingReservationField ? String(existingReservationField.value || '').trim() : '';
        const recommendedReservation = recommendedReservationField ? String(recommendedReservationField.value || '').trim() : '';
        const recommendedCommentStatus = recommendedCommentStatusField ? String(recommendedCommentStatusField.value || '').trim() : '';
        const howManyMeters = howManyMetersField ? String(howManyMetersField.value || '').trim() : '';

        return {
            reservation_required: reservationRequired ? reservationRequired.value : null,
            road_category: roadCategory,
            road_category_option: roadCategoryOption,
            observed_road_category: observedRoadCategory,
            road_category_other: observedRoadCategory,
            right_of_way: existingReservation,
            right_of_way_other: recommendedReservation,
            recommended_reservation: recommendedReservation,
            recommended_comment_status: recommendedCommentStatus,
            how_many_meters: howManyMeters
        };
    }

    function buildConversionReservationMetaEntry(payload) {
        if (!payload || payload.reservation_required === null) {
            return null;
        }

        const detail = {
            road_category: payload.road_category || '',
            road_category_option: payload.road_category_option || '',
            observed_road_category: payload.observed_road_category || '',
            road_category_other: payload.road_category_other || '',
            right_of_way: payload.right_of_way || '',
            right_of_way_other: payload.right_of_way_other || '',
            recommended_reservation: payload.recommended_reservation || '',
            recommended_comment_status: payload.recommended_comment_status || '',
            how_many_meters: payload.how_many_meters || ''
        };

        return {
            sn: 1,
            description: CONVERSION_RESERVATION_META_DESCRIPTION,
            count: payload.reservation_required === '1' ? '1' : '0',
            measurement_dimension: payload.road_category || '',
            dimension: JSON.stringify(detail)
        };
    }

    function extractConversionReservationMetaFromEntries(entries = []) {
        if (!Array.isArray(entries) || entries.length === 0) {
            return null;
        }

        return entries.find(entry => isConversionReservationMetaDescription(entry?.description || '')) || null;
    }

    function buildPurposesByLandUseMap() {
        const source = window.jsiDropdownData || {};
        const purposes = Array.isArray(source.jsiPurposes) ? source.jsiPurposes : [];
        const grouped = {};

        purposes.forEach(item => {
            const landUseId = item && item.land_use_id !== undefined && item.land_use_id !== null
                ? String(item.land_use_id)
                : '';
            const purposeName = item && item.name ? String(item.name).trim() : '';

            if (!landUseId || !purposeName) {
                return;
            }

            if (!grouped[landUseId]) {
                grouped[landUseId] = [];
            }

            grouped[landUseId].push(purposeName);
        });

        return grouped;
    }

    function isPurposeOtherOption(value) {
        const normalized = String(value || '').trim().toUpperCase();
        return normalized === 'OTHER' || normalized === 'OTHERS';
    }

    function hasSelectOption(select, value) {
        if (!select || !value) {
            return false;
        }

        const expected = String(value);
        return Array.from(select.options || []).some(option => String(option.value || '') === expected);
    }

    function normalizeLandUseKey(value) {
        const raw = String(value || '').trim().toLowerCase();
        if (raw === '') {
            return '';
        }

        if (raw.includes('res')) return 'residential';
        if (raw.includes('com')) return 'commercial';
        if (raw.includes('ind')) return 'industrial';
        if (raw.includes('agr')) return 'agricultural';
        if (raw.includes('mix')) return 'mixed';

        return raw.replace(/\s+use$/g, '').trim();
    }

    function getLandUseNameById(landUseId) {
        const source = window.jsiDropdownData || {};
        const landUseTypes = Array.isArray(source.landUseTypes) ? source.landUseTypes : [];
        const idText = String(landUseId || '').trim();
        if (!idText) {
            return '';
        }

        const matched = landUseTypes.find(item => String(item && item.id !== undefined ? item.id : '').trim() === idText);
        return matched && matched.name ? String(matched.name) : '';
    }

    function getDetailedOptionAppliedLandUseKey(option) {
        if (!option) {
            return '';
        }

        if (String(option.value || '').trim() === '') {
            return '';
        }

        const fromName = normalizeLandUseKey(option.getAttribute('data-land-use-name') || '');
        if (fromName) {
            return fromName;
        }

        const fromId = option.getAttribute('data-land-use-id') || '';
        const resolvedName = getLandUseNameById(fromId);
        if (resolvedName) {
            return normalizeLandUseKey(resolvedName);
        }

        // Fallback for environments where detailed_land_use has no land_use_id column:
        // derive mapping from the visible detailed land use label.
        const fromLabel = normalizeLandUseKey(option.textContent || '');
        if (fromLabel) {
            return fromLabel;
        }

        return '';
    }

    function getAppliedLandUseOptionSource() {
        const appliedSelect = document.getElementById('jointInspectionAppliedLandUse');
        if (!appliedSelect) {
            return [];
        }

        if (Array.isArray(window.__jsiAppliedLandUseOptions) && window.__jsiAppliedLandUseOptions.length > 0) {
            return window.__jsiAppliedLandUseOptions;
        }

        const source = Array.from(appliedSelect.options || [])
            .map(option => ({
                value: String(option && option.value ? option.value : '').trim(),
                label: String(option && option.textContent ? option.textContent : '').trim()
            }))
            .filter(option => option.value !== '');

        window.__jsiAppliedLandUseOptions = source;
        return source;
    }

    function getAppliedLandUseKeyFromPrevailingSelection() {
        const prevailingSelect = document.getElementById('jointInspectionPrevailingLandUse');
        if (!prevailingSelect) {
            return '';
        }

        const selectedValue = String(prevailingSelect.value || '').trim();
        if (!selectedValue) {
            return '';
        }

        return normalizeLandUseKey(selectedValue);
    }

    function rebuildAppliedLandUseOptions(filteredOptions, preferredValue = '') {
        const appliedSelect = document.getElementById('jointInspectionAppliedLandUse');
        if (!appliedSelect) {
            return;
        }

        const preservedValue = String(preferredValue || appliedSelect.value || '').trim();
        const markup = ['<option value="">Select applied land use</option>'];

        filteredOptions.forEach(option => {
            const selectedAttr = preservedValue === option.value ? ' selected' : '';
            markup.push(`<option value="${option.value}"${selectedAttr}>${option.label}</option>`);
        });

        appliedSelect.innerHTML = markup.join('');

        if (!preservedValue) {
            return;
        }

        if (filteredOptions.some(option => option.value === preservedValue)) {
            appliedSelect.value = preservedValue;
            return;
        }

        const fallback = filteredOptions.find(option => normalizeLandUseKey(option.value || option.label) === normalizeLandUseKey(preservedValue));
        appliedSelect.value = fallback ? fallback.value : '';
    }

    function getSelectedPrevailingDetailedLandUseId() {
        const prevailingSelect = document.getElementById('jointInspectionPrevailingLandUse');
        if (!prevailingSelect) {
            return '';
        }

        const selectedOption = prevailingSelect.options[prevailingSelect.selectedIndex] || null;
        const fromDataAttr = String(selectedOption && selectedOption.getAttribute('data-detailed-land-use-id') ? selectedOption.getAttribute('data-detailed-land-use-id') : '').trim();
        if (fromDataAttr) {
            return fromDataAttr;
        }

        const selectedLabel = String(prevailingSelect.value || '').trim().toLowerCase();
        if (!selectedLabel) {
            return '';
        }

        const conversionDetailedSelect = document.getElementById('conversionDetailedLandUse');
        if (!conversionDetailedSelect) {
            return '';
        }

        const matchedOption = Array.from(conversionDetailedSelect.options || []).find(option =>
            String(option && option.textContent ? option.textContent : '').trim().toLowerCase() === selectedLabel
        );

        return String(matchedOption && matchedOption.value ? matchedOption.value : '').trim();
    }

    function getPurposeOptionsByDetailedLandUseId(detailedLandUseId) {
        const idText = String(detailedLandUseId || '').trim();
        if (!idText) {
            return [];
        }

        const groupedPurposes = buildPurposesByLandUseMap();
        const list = Array.isArray(groupedPurposes[idText]) ? groupedPurposes[idText] : [];
        return list
            .map(name => {
                const text = String(name || '').trim();
                return text ? { value: text, label: text } : null;
            })
            .filter(Boolean);
    }

    function populateAppliedLandUseByDetailedLandUse() {
        const detailedSelect = document.getElementById('conversionDetailedLandUse');
        const appliedSelect = document.getElementById('jointInspectionAppliedLandUse');
        if (!appliedSelect) {
            return;
        }

        const selectedAppliedValue = String(appliedSelect.value || '').trim();
        const selectedDetailOption = detailedSelect
            ? (detailedSelect.options[detailedSelect.selectedIndex] || null)
            : null;
        const detailKey = getDetailedOptionAppliedLandUseKey(selectedDetailOption);
        const prevailingKey = getAppliedLandUseKeyFromPrevailingSelection();
        const activeKey = detailKey || prevailingKey;
        const allAppliedOptions = getAppliedLandUseOptionSource();
        const prevailingDetailedLandUseId = getSelectedPrevailingDetailedLandUseId();
        const purposeLutOptions = getPurposeOptionsByDetailedLandUseId(prevailingDetailedLandUseId);

        const filteredOptions = purposeLutOptions.length > 0
            ? purposeLutOptions
            : (activeKey
                ? allAppliedOptions.filter(option => normalizeLandUseKey(option.value || option.label) === activeKey)
                : allAppliedOptions);

        let preferredValue = selectedAppliedValue;
        if (!preferredValue && prevailingKey) {
            const prevailingSelect = document.getElementById('jointInspectionPrevailingLandUse');
            preferredValue = String(prevailingSelect && prevailingSelect.value ? prevailingSelect.value : '').trim();
        }

        rebuildAppliedLandUseOptions(filteredOptions, preferredValue);
    }

    function syncConversionPurposeOtherVisibility() {
        const purposeSelect = document.getElementById('conversionPurposeLut');
        const otherWrapper = document.getElementById('conversionPurposeLutOtherWrapper');
        const otherField = document.getElementById('conversionPurposeLutOther');

        if (!otherWrapper || !otherField) {
            return;
        }

        const shouldShow = purposeSelect && isPurposeOtherOption(purposeSelect.value);
        otherWrapper.classList.toggle('hidden', !shouldShow);

        if (!shouldShow) {
            otherField.value = '';
        }
    }



 
     
    function populateConversionPurposeOptions(selectedLandUseId = '', selectedPurpose = '') {
        const purposeSelect = document.getElementById('conversionPurposeLut');
        if (!purposeSelect) {
            return;
        }

        const landUseId = String(selectedLandUseId || '').trim();
        const purposeValue = String(selectedPurpose || '').trim();
        const groupedPurposes = buildPurposesByLandUseMap();
        const purposeList = landUseId && Array.isArray(groupedPurposes[landUseId])
            ? groupedPurposes[landUseId]
            : [];

        const options = ['<option value="">Select purpose</option>'];
        purposeList.forEach(name => {
            const selectedAttr = purposeValue === name ? ' selected' : '';
            options.push(`<option value="${name}"${selectedAttr}>${name}</option>`);
        });

        purposeSelect.innerHTML = options.join('');

        if (purposeValue && !purposeList.includes(purposeValue)) {
            purposeSelect.value = '';
        }

        syncConversionPurposeOtherVisibility();
    }

    function syncConversionPurposeByDetailedLandUse() {
        const landUseSelect = document.getElementById('conversionDetailedLandUse');
        const purposeSelect = document.getElementById('conversionPurposeLut');
        if (!landUseSelect || !purposeSelect) {
            return;
        }

        populateConversionPurposeOptions(landUseSelect.value, purposeSelect.value);
        populateAppliedLandUseByDetailedLandUse();
    }

    function applyConversionPurposeLutFromMeta(meta) {
        const detailedLandUseField = document.getElementById('conversionDetailedLandUse');
        const purposeLutField = document.getElementById('conversionPurposeLut');
        const purposeLutOtherField = document.getElementById('conversionPurposeLutOther');
        if (!purposeLutField) {
            return;
        }

        let detailedLandUseId = '';
        let purposeOptionValue = '';
        let purposeOtherValue = '';

        if (meta && meta.dimension) {
            try {
                const detail = typeof meta.dimension === 'string' ? JSON.parse(meta.dimension) : (meta.dimension || {});
                detailedLandUseId = detail && detail.detailed_land_use_id ? String(detail.detailed_land_use_id) : '';
                purposeOptionValue = detail && detail.purpose_lut_option ? String(detail.purpose_lut_option) : '';
                purposeOtherValue = detail && detail.purpose_lut_other ? String(detail.purpose_lut_other) : '';
            } catch (error) {
                detailedLandUseId = String(meta.dimension || '');
            }
        }

        const purposeValue = meta && meta.measurement_dimension ? String(meta.measurement_dimension) : '';

        if (!detailedLandUseId && purposeValue) {
            const groupedPurposes = buildPurposesByLandUseMap();
            Object.keys(groupedPurposes).some(key => {
                if (Array.isArray(groupedPurposes[key]) && groupedPurposes[key].includes(purposeValue)) {
                    detailedLandUseId = key;
                    return true;
                }
                return false;
            });
        }

        if (detailedLandUseField) {
            detailedLandUseField.value = detailedLandUseId;
        }

        const preferredPurposeValue = purposeOptionValue || purposeValue;
        populateConversionPurposeOptions(detailedLandUseId, preferredPurposeValue);
        populateAppliedLandUseByDetailedLandUse();

        if (isPurposeOtherOption(purposeOptionValue) && hasSelectOption(purposeLutField, purposeOptionValue)) {
            purposeLutField.value = purposeOptionValue;
        } else if (isPurposeOtherOption(purposeValue) && hasSelectOption(purposeLutField, purposeValue)) {
            purposeLutField.value = purposeValue;
        }

        if (purposeLutOtherField) {
            purposeLutOtherField.value = isPurposeOtherOption(purposeLutField.value)
                ? (purposeOtherValue || purposeValue)
                : '';
        }

        syncConversionPurposeOtherVisibility();
    }

    function applyConversionReservationFromMeta(meta) {
        const reservationSelection = meta ? (Number(meta.count) === 1 ? '1' : '0') : null;
        document.querySelectorAll('input[name="reservation_required"]').forEach(radio => {
            radio.checked = reservationSelection !== null && radio.value === reservationSelection;
        });

        const roadCategoryField = document.getElementById('conversionRoadCategory');
        const roadCategoryOtherField = document.getElementById('conversionRoadCategoryOther');
        const existingReservationField = document.getElementById('conversionRightOfWay');
        const recommendedReservationField = document.getElementById('conversionRecommendedReservation');
        const recommendedCommentStatusField = document.getElementById('recommendedCommentStatus');
        const howManyMetersField = document.getElementById('howManyMeters');

        let detail = {};
        if (meta && meta.dimension) {
            try {
                detail = typeof meta.dimension === 'string' ? JSON.parse(meta.dimension) : (meta.dimension || {});
            } catch (error) {
                detail = {};
            }
        }

        if (roadCategoryField) {
            const metaRoadCategory = meta && meta.measurement_dimension ? String(meta.measurement_dimension) : '';
            const roadCategoryOption = detail.road_category_option ? String(detail.road_category_option) : '';
            const observedRoadCategory = detail.observed_road_category
                ? String(detail.observed_road_category)
                : (detail.road_category_other ? String(detail.road_category_other) : '');

            if (roadCategoryOption) {
                roadCategoryField.value = roadCategoryOption;
            } else {
                const hasDirectOption = Array.from(roadCategoryField.options || []).some(option => String(option.value || '').trim() === metaRoadCategory);
                roadCategoryField.value = hasDirectOption ? metaRoadCategory : (metaRoadCategory ? 'OTHER' : '');
            }

            if (roadCategoryOtherField) {
                if (String(roadCategoryField.value || '').trim() !== '') {
                    roadCategoryOtherField.value = observedRoadCategory || metaRoadCategory;
                } else {
                    roadCategoryOtherField.value = '';
                }
            }
        }

        if (existingReservationField) {
            existingReservationField.value = detail.right_of_way ? String(detail.right_of_way) : '';
        }

        if (recommendedReservationField) {
            recommendedReservationField.value = detail.recommended_reservation
                ? String(detail.recommended_reservation)
                : (detail.right_of_way_other ? String(detail.right_of_way_other) : '');
        }

        if (recommendedCommentStatusField) {
            recommendedCommentStatusField.value = detail.recommended_comment_status
                ? String(detail.recommended_comment_status)
                : '';
        }

        if (howManyMetersField) {
            howManyMetersField.value = detail.how_many_meters
                ? String(detail.how_many_meters)
                : '';
        }

        syncReservationVisibility();
        syncRoadCategoryOtherVisibility();
        syncRightOfWayOtherVisibility();
    }

    function applyConversionReservationFromReport(report) {
        const roadCategoryField = document.getElementById('conversionRoadCategory');
        const roadCategoryOtherField = document.getElementById('conversionRoadCategoryOther');
        const existingReservationField = document.getElementById('conversionRightOfWay');
        const recommendedReservationField = document.getElementById('conversionRecommendedReservation');
        const recommendedCommentStatusField = document.getElementById('recommendedCommentStatus');
        const howManyMetersField = document.getElementById('howManyMeters');

        const observedRoadCategory = String(report?.observed_road_category || '').trim();
        const existingReservation = String(report?.existing_road_reservation || '').trim();
        const recommendedReservation = String(report?.recommended_road_reservation || '').trim();
        const recommendedCommentStatus = String(report?.recommended_comment_status || '').trim();
        const howManyMeters = String(report?.how_many_meters || '').trim();

        const hasReservationData = [
            observedRoadCategory,
            existingReservation,
            recommendedReservation,
            recommendedCommentStatus,
            howManyMeters,
        ].some(value => value !== '');

        const reservationSelection = hasReservationData ? '1' : '0';
        document.querySelectorAll('input[name="reservation_required"]').forEach(radio => {
            radio.checked = radio.value === reservationSelection;
        });

        if (roadCategoryField) {
            const matchedRoadCategory = Array.from(roadCategoryField.options || []).find(option =>
                String(option.value || '').trim().toLowerCase() === observedRoadCategory.toLowerCase()
            );
            if (matchedRoadCategory) {
                roadCategoryField.value = matchedRoadCategory.value;
                if (roadCategoryOtherField) {
                    roadCategoryOtherField.value = observedRoadCategory;
                }
            } else if (observedRoadCategory !== '') {
                const otherOption = Array.from(roadCategoryField.options || []).find(option =>
                    String(option.value || '').trim().toLowerCase() === 'other'
                );
                roadCategoryField.value = otherOption ? otherOption.value : '';
                if (roadCategoryOtherField) {
                    roadCategoryOtherField.value = observedRoadCategory;
                }
            } else {
                roadCategoryField.value = '';
                if (roadCategoryOtherField) {
                    roadCategoryOtherField.value = '';
                }
            }
        }

        if (existingReservationField) {
            const matchedExisting = Array.from(existingReservationField.options || []).find(option =>
                String(option.value || '').trim().toLowerCase() === existingReservation.toLowerCase()
            );
            existingReservationField.value = matchedExisting ? matchedExisting.value : '';
        }

        if (recommendedReservationField) {
            const matchedRecommended = Array.from(recommendedReservationField.options || []).find(option =>
                String(option.value || '').trim().toLowerCase() === recommendedReservation.toLowerCase()
            );
            recommendedReservationField.value = matchedRecommended ? matchedRecommended.value : '';
        }

        if (recommendedCommentStatusField) {
            recommendedCommentStatusField.value = recommendedCommentStatus;
        }

        if (howManyMetersField) {
            howManyMetersField.value = howManyMeters;
        }

        syncReservationVisibility();
        syncRoadCategoryOtherVisibility();
        syncRightOfWayOtherVisibility();
    }

    function parseExistingStructureSelectionFromMeta(entry) {
        const rawCount = entry && entry.count !== undefined && entry.count !== null
            ? Number(entry.count)
            : null;

        if (rawCount === 1) {
            return '1';
        }

        if (rawCount === 0) {
            return '0';
        }

        return null;
    }

    function extractExistingStructureMetaFromEntries(entries = []) {
        if (!Array.isArray(entries) || entries.length === 0) {
            return null;
        }

        return entries.find(entry => isExistingStructureMetaDescription(entry?.description || '')) || null;
    }

    function setExistingStructureState(selection = null, dimensions = '', amount = '') {
        document.querySelectorAll('input[name="existing_structure"]').forEach(radio => {
            radio.checked = selection !== null && radio.value === String(selection);
        });

        const dimensionsField = document.getElementById('existingStructureDimensions');
        if (dimensionsField) {
            dimensionsField.value = dimensions || '';
        }

        syncExistingStructureDimensionsVisibility();

        const amountField = document.getElementById('existingStructureAmount');
        const amountRawField = document.getElementById('existingStructureAmountRaw');
        const numericAmount = Number(amount);

        if (amountField) {
            amountField.value = Number.isFinite(numericAmount) ? numericAmount.toFixed(2) : '';
        }

        if (amountRawField) {
            amountRawField.value = Number.isFinite(numericAmount) ? numericAmount.toFixed(2) : '';
        }
    }

    function syncExistingStructureAmountRaw() {
        const amountField = document.getElementById('existingStructureAmount');
        const amountRawField = document.getElementById('existingStructureAmountRaw');
        if (!amountRawField || !amountField) {
            return;
        }
        amountRawField.value = amountField.value;
    }

    function syncExistingStructureDimensionsVisibility() {
        const selected = document.querySelector('input[name="existing_structure"]:checked');
        const dimensionsWrapper = document.getElementById('existingStructureDimensionsWrapper');
        if (!dimensionsWrapper) {
            return;
        }

        const isYes = selected && selected.value === '1';
        dimensionsWrapper.classList.toggle('hidden', !isYes);
    }

    function collectExistingStructurePayload() {
        const selected = document.querySelector('input[name="existing_structure"]:checked');
        const dimensionsField = document.getElementById('existingStructureDimensions');
        const amountField = document.getElementById('existingStructureAmount');
        const amountRawField = document.getElementById('existingStructureAmountRaw');
        const dimensions = dimensionsField ? (dimensionsField.value || '').trim() : '';
        const parsed = parseMeasurementInput(dimensions);
        const volume = parsed.length > 0
            ? parsed.reduce((acc, curr) => acc * curr, 1)
            : null;
        const amountRaw = amountRawField && amountRawField.value !== ''
            ? Number(amountRawField.value)
            : NaN;
        const amountFromDisplay = amountField && amountField.value !== ''
            ? Number(String(amountField.value).replace(/[^0-9.\-]/g, ''))
            : NaN;
        const amount = Number.isFinite(amountRaw)
            ? amountRaw
            : (Number.isFinite(amountFromDisplay)
                ? amountFromDisplay
                : (Number.isFinite(volume) ? volume : null));

        return {
            selection: selected ? selected.value : null,
            dimensions,
            parsed,
            volume,
            amount
        };
    }

    function buildExistingStructureMetaEntry(payload) {
        if (!payload || payload.selection === null) {
            return null;
        }

        return {
            sn: 1,
            description: EXISTING_STRUCTURE_META_DESCRIPTION,
            count: payload.selection === '1' ? '1' : '0',
            measurement_dimension: payload.selection === '1' ? (payload.dimensions || '') : '',
            dimension: payload.selection === '1' && Number.isFinite(payload.amount)
                ? Number(payload.amount).toFixed(2)
                : ''
        };
    }

    function parseNeedForEiaSelectionFromMeta(entry) {
        const rawCount = entry && entry.count !== undefined && entry.count !== null
            ? Number(entry.count)
            : null;

        if (rawCount === 1) {
            return '1';
        }

        if (rawCount === 0) {
            return '0';
        }

        return null;
    }

    function extractNeedForEiaMetaFromEntries(entries = []) {
        if (!Array.isArray(entries) || entries.length === 0) {
            return null;
        }

        return entries.find(entry => isNeedForEiaMetaDescription(entry?.description || '')) || null;
    }

    function setNeedForEiaState(selection = null) {
        document.querySelectorAll('input[name="need_for_eia"]').forEach(radio => {
            radio.checked = selection !== null && radio.value === String(selection);
        });
    }

    function collectNeedForEiaPayload() {
        const selected = document.querySelector('input[name="need_for_eia"]:checked');

        return {
            selection: selected ? selected.value : null
        };
    }

    function buildNeedForEiaMetaEntry(payload) {
        if (!payload || payload.selection === null) {
            return null;
        }

        return {
            sn: 1,
            description: NEED_FOR_EIA_META_DESCRIPTION,
            count: payload.selection === '1' ? '1' : '0',
            measurement_dimension: '',
            dimension: ''
        };
    }

    function renderParsedDimensions() {}

    function updateMeasurementGroupFromInput() {}

    function isOtherOptionValue(value) {
        const normalized = String(value || '').trim().toLowerCase();
        return normalized === 'other' || normalized === 'others';
    }

    const addressFieldMap = {
        applicant: {
            plot: 'applicantAddressPlot',
            street: 'applicantAddressStreet',
            streetOther: 'applicantAddressStreetOther',
            district: 'applicantAddressDistrict',
            districtOther: 'applicantAddressDistrictOther',
            lga: 'applicantAddressLga',
            state: 'applicantAddressState',
            hidden: 'jointInspectionApplicantAddress',
            preview: 'applicantAddressPreview',
        },
        location: {
            plot: 'jointInspectionPlot',
            street: 'locationStreet',
            streetOther: 'locationStreetOther',
            district: 'locationDistrict',
            districtOther: 'locationDistrictOther',
            lga: 'locationLga',
            state: 'locationState',
            hidden: 'jointInspectionLocation',
            preview: 'locationAddressPreview',
        },
    };

    async function fetchReferenceOptions(url, params = {}) {
        try {
            const endpoint = new URL(url, window.location.origin);
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== null && `${value}`.trim() !== '') {
                    endpoint.searchParams.set(key, value);
                }
            });

            const response = await fetch(endpoint.toString(), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return [];
            }

            const payload = await response.json();
            return payload?.data ?? [];
        } catch (error) {
            console.warn('Reference lookup failed:', error);
            return [];
        }
    }

    function setSelectOptions(selectEl, options, placeholder = 'Select', allowOther = false) {
        if (!selectEl) {
            return;
        }

        const currentValue = (selectEl.value || '').trim();
        const normalizedCurrent = currentValue.toLowerCase();
        selectEl.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        selectEl.appendChild(placeholderOption);

        options.forEach(option => {
            if (!option) return;
            const value = option.id ?? option.name ?? option.value ?? '';
            const label = option.name ?? option.label ?? option.value ?? '';
            if (`${value}`.trim() === '') {
                return;
            }
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = label;
            selectEl.appendChild(opt);
        });

        if (allowOther) {
            const hasOther = Array.from(selectEl.options).some(o =>
                isOtherOptionValue(o.value)
                || isOtherOptionValue(o.textContent)
            );
            if (!hasOther) {
                const otherOpt = document.createElement('option');
                otherOpt.value = 'Other';
                otherOpt.textContent = 'Other';
                selectEl.appendChild(otherOpt);
            }
        }

        const matchByValue = Array.from(selectEl.options).find(o => (o.value || '').trim() === currentValue);
        const matchByLabel = Array.from(selectEl.options).find(o => (o.textContent || '').trim().toLowerCase() === normalizedCurrent);

        if (!matchByValue && matchByLabel) {
            selectEl.value = matchByLabel.value;
            return;
        }

        if (currentValue && !matchByValue) {
            const opt = document.createElement('option');
            opt.value = currentValue;
            opt.textContent = currentValue;
            selectEl.appendChild(opt);
        }

        if (currentValue && (matchByValue || matchByLabel)) {
            selectEl.value = matchByValue ? currentValue : matchByLabel?.value;
        } else if (currentValue) {
            selectEl.value = currentValue;
        } else {
            selectEl.value = '';
        }
    }

    async function loadStates(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) return;
        const stateSelect = document.getElementById(map.state);
        if (!stateSelect || stateSelect.dataset.jsiLocked === 'true') {
            return;
        }
        const options = await fetchReferenceOptions(referenceApi.states);
        setSelectOptions(stateSelect, options, 'Select state');
    }

    async function loadLgas(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) return;
        const stateSelect = document.getElementById(map.state);
        const lgaSelect = document.getElementById(map.lga);
        if (!lgaSelect || lgaSelect.dataset.jsiLocked === 'true') {
            return;
        }

        const stateId = stateSelect ? stateSelect.value : '';
        const options = await fetchReferenceOptions(referenceApi.lgas, { state_id: stateId });
        setSelectOptions(lgaSelect, options, 'Select LGA');
    }

    async function loadDistricts(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) return;
        const lgaSelect = document.getElementById(map.lga);
        const districtSelect = document.getElementById(map.district);
        if (!districtSelect || districtSelect.dataset.jsiLocked === 'true') {
            return;
        }

        const lgaId = lgaSelect ? lgaSelect.value : '';
        const options = await fetchReferenceOptions(referenceApi.districts, { lga_id: lgaId });
        setSelectOptions(districtSelect, options, 'Select district', true);
        toggleOtherField(map.district, map.districtOther);
    }

    async function loadStreets(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) return;
        const districtSelect = document.getElementById(map.district);
        const streetSelect = document.getElementById(map.street);
        if (!streetSelect || streetSelect.dataset.jsiLocked === 'true') {
            return;
        }

        const districtId = districtSelect ? districtSelect.value : '';
        const options = await fetchReferenceOptions(referenceApi.streets, { district_id: districtId });
        setSelectOptions(streetSelect, options, 'Select street', true);
        toggleOtherField(map.street, map.streetOther);
    }

    function wireAddressCascade(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) return;

        const stateSelect = document.getElementById(map.state);
        const lgaSelect = document.getElementById(map.lga);
        const districtSelect = document.getElementById(map.district);

        if (stateSelect) {
            stateSelect.addEventListener('change', () => {
                if (stateSelect.dataset.jsiLocked === 'true') return;
                setSelectOptions(lgaSelect, [], 'Select LGA');
                setSelectOptions(districtSelect, [], 'Select district');
                loadLgas(prefix).then(() => {
                    loadDistricts(prefix);
                    loadStreets(prefix);
                    setAddressOutputs(prefix);
                });
            });
        }

        if (lgaSelect) {
            lgaSelect.addEventListener('change', () => {
                if (lgaSelect.dataset.jsiLocked === 'true') return;
                setSelectOptions(districtSelect, [], 'Select district');
                loadDistricts(prefix).then(() => {
                    loadStreets(prefix);
                    setAddressOutputs(prefix);
                });
            });
        }

        if (districtSelect) {
            districtSelect.addEventListener('change', () => {
                if (districtSelect.dataset.jsiLocked === 'true') return;
                loadStreets(prefix).then(() => {
                    toggleOtherField(map.district, map.districtOther);
                    setAddressOutputs(prefix);
                });
            });
        }

        const streetSelect = document.getElementById(map.street);
        if (streetSelect) {
            streetSelect.addEventListener('change', () => {
                toggleOtherField(map.street, map.streetOther);
                setAddressOutputs(prefix);
            });
        }
    }

    async function initializeAddressPickers() {
        ['applicant', 'location'].forEach(prefix => wireAddressCascade(prefix));
        await loadStates('applicant');
        await loadStates('location');
        await loadLgas('applicant');
        await loadLgas('location');
        await loadDistricts('applicant');
        await loadDistricts('location');
        await loadStreets('applicant');
        await loadStreets('location');
    }

    function collectAddressComponents(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) {
            return {};
        }

        const valueOf = (id) => {
            const el = document.getElementById(id);
            if (!el) return '';
            return (el.value || '').trim();
        };

        const labelOf = (id) => {
            const el = document.getElementById(id);
            if (!el || !el.options) return '';
            const opt = el.options[el.selectedIndex];
            const label = opt ? (opt.textContent || '').trim() : '';
            return label === '' || opt.value === '' ? '' : label;
        };

        return {
            plot_number: valueOf(map.plot),
            street: valueOf(map.street),
            street_label: labelOf(map.street),
            street_other: valueOf(map.streetOther),
            district: valueOf(map.district),
            district_label: labelOf(map.district),
            district_other: valueOf(map.districtOther),
            lga: valueOf(map.lga),
            lga_label: labelOf(map.lga),
            state: valueOf(map.state),
            state_label: labelOf(map.state),
        };
    }

    /**
     * Replace raw dropdown IDs with their display labels for server submission.
     * Keeps plot_number, street_other, district_other as-is.
     */
    function resolveComponentLabels(components = {}) {
        const resolved = { ...components };

        // street: use label unless value is 'Other'
        if (resolved.street && !isOtherOptionValue(resolved.street) && !isOtherOptionValue(resolved.street_label)) {
            resolved.street = resolved.street_label || resolved.street;
        }
        // district: use label unless value is 'Other'
        if (resolved.district && !isOtherOptionValue(resolved.district) && !isOtherOptionValue(resolved.district_label)) {
            resolved.district = resolved.district_label || resolved.district;
        }
        // lga & state: always use label
        if (resolved.lga_label) resolved.lga = resolved.lga_label;
        if (resolved.state_label) resolved.state = resolved.state_label;

        // Remove _label keys — server doesn't expect them
        delete resolved.street_label;
        delete resolved.district_label;
        delete resolved.lga_label;
        delete resolved.state_label;

        return resolved;
    }

    function compileAddressStringFromComponents(components = {}) {
        // Prefer plot; only fall back to street when plot is empty
        const streetSelected = (isOtherOptionValue(components.street) || isOtherOptionValue(components.street_label))
            ? components.street_other
            : (components.street_label || components.street);

        const districtSelected = (isOtherOptionValue(components.district) || isOtherOptionValue(components.district_label))
            ? components.district_other
            : (components.district_label || components.district);

        const lgaSelected = components.lga_label || components.lga;
        const stateSelected = components.state_label || components.state;

        const parts = [];

        if (components.plot_number) {
            parts.push(components.plot_number);
        } else if (streetSelected) {
            parts.push(streetSelected);
        }

        if (districtSelected) {
            parts.push(districtSelected);
        }
        if (lgaSelected) {
            parts.push(`${lgaSelected} LGA`);
        }
        if (stateSelected) {
            parts.push(`${stateSelected} State`);
        }

        return parts.filter(Boolean).join(', ');
    }

    function setAddressOutputs(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) return;

        const components = collectAddressComponents(prefix);
        const compiled = compileAddressStringFromComponents(components);

        const hidden = document.getElementById(map.hidden);
        if (hidden) {
            hidden.value = compiled;
        }

        const preview = document.getElementById(map.preview);
        if (preview) {
            preview.textContent = compiled || 'Not set';
        }

        return components;
    }

    function setAddressComponents(prefix, components = {}) {
        const map = addressFieldMap[prefix];
        if (!map) return;

        const assign = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.value = value || '';
            }
        };

        const assignSelect = (id, value) => {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }

            const normalized = value === null || typeof value === 'undefined'
                ? ''
                : String(value).trim();

            if (normalized === '') {
                el.value = '';
                return;
            }

            const byValue = Array.from(el.options || []).find(option =>
                String(option.value || '').trim().toLowerCase() === normalized.toLowerCase()
            );

            if (byValue) {
                el.value = byValue.value;
                return;
            }

            const byLabel = Array.from(el.options || []).find(option =>
                String(option.textContent || '').trim().toLowerCase() === normalized.toLowerCase()
            );

            if (byLabel) {
                el.value = byLabel.value;
                return;
            }

            const opt = document.createElement('option');
            opt.value = normalized;
            opt.textContent = normalized;
            el.appendChild(opt);
            el.value = normalized;
        };

        // Resolve numeric LGA/state/district IDs to names
        let lgaValue = components.lga ?? '';
        if (lgaValue && /^\d+$/.test(String(lgaValue).trim())) {
            lgaValue = lgaIdToName[String(lgaValue).trim()] || lgaValue;
        }
        let stateValue = components.state ?? '';
        if (stateValue && /^\d+$/.test(String(stateValue).trim())) {
            stateValue = statIdToName[String(stateValue).trim()] || stateValue;
        }
        let districtValue = components.district ?? '';
        if (districtValue && /^\d+$/.test(String(districtValue).trim())) {
            districtValue = districtIdToName[String(districtValue).trim()] || districtValue;
        }

        assign(map.plot, components.plot_number ?? '');
        assignSelect(map.street, components.street ?? '');
        assign(map.streetOther, components.street_other ?? '');
        assignSelect(map.district, districtValue);
        assign(map.districtOther, components.district_other ?? '');
        assignSelect(map.lga, lgaValue);
        assignSelect(map.state, stateValue);

        toggleOtherField(map.street, map.streetOther);
        toggleOtherField(map.district, map.districtOther);

        setAddressOutputs(prefix);
    }

    function toggleOtherField(selectId, otherInputId) {
        const selectEl = document.getElementById(selectId);
        const otherEl = document.getElementById(otherInputId);

        if (!selectEl || !otherEl) {
            return;
        }

        const selectedOption = selectEl.options ? selectEl.options[selectEl.selectedIndex] : null;
        const selectedLabel = selectedOption ? (selectedOption.textContent || '') : '';
        const shouldShow = isOtherOptionValue(selectEl.value) || isOtherOptionValue(selectedLabel);
        otherEl.classList.toggle('hidden', !shouldShow);
        if (!shouldShow) {
            otherEl.value = '';
        }
    }

    /**
     * Convert a string to Title Case, preserving ST-format file numbers and common abbreviations.
     * E.g. "26715 LANGOSH MOTORWAY, OYI" → "26715 Langosh Motorway, Oyi"
     */
    function toTitleCase(str) {
        if (!str) return '';
        return String(str).replace(/\w\S*/g, function(word) {
            // Keep ST file numbers, Roman numerals II/III/IV, LGA, etc. as-is
            if (/^(ST|LGA|FCT|II|III|IV|VI|VII|VIII|IX|XI|XII)$/i.test(word) && word === word.toUpperCase()) {
                return word;
            }
            // Keep fully numeric tokens as-is
            if (/^\d+$/.test(word)) return word;
            return word.charAt(0).toUpperCase() + word.substr(1).toLowerCase();
        });
    }

    /**
     * Strip duplicate plot_no from a full address string when house_no is already present.
     * If the address starts with "house_no, plot_no, ..." it returns "house_no, ...".
     */
    function stripPlotFromAddress(addressText, houseNo, plotNo) {
        if (!addressText || !houseNo || !plotNo) return addressText || '';
        const h = String(houseNo).trim();
        const p = String(plotNo).trim();
        if (!h || !p || h === p) return addressText;

        // Pattern: "house_no, plot_no, rest" or "house_no plot_no, rest"
        const escapedH = h.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const escapedP = p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp('^(' + escapedH + ')\\s*[,\\s]+' + escapedP + '\\s*[,\\s]*(.*)', 'i');
        const match = addressText.match(regex);
        if (match) {
            const rest = (match[2] || '').trim().replace(/^,\s*/, '');
            return rest ? match[1] + ', ' + rest : match[1];
        }
        return addressText;
    }

    // ===== WIZARD NAVIGATION =====
    const JSI_WIZARD_TOTAL_STEPS = 4;
    let jsiWizardCurrentStep = 1;

    /**
     * Navigate the wizard to the given step number.
     * Updates step visibility, stepper bar progress, and button states.
     */
    function jsiWizardGoToStep(step) {
        if (step < 1 || step > JSI_WIZARD_TOTAL_STEPS) return;

        // Hide validation errors when changing steps
        const errBox = document.getElementById('jointInspectionValidationErrors');
        if (errBox) errBox.classList.add('hidden');

        jsiWizardCurrentStep = step;

        // Show/hide step panels
        document.querySelectorAll('.jsi-wizard-step').forEach(function(panel) {
            const panelStep = parseInt(panel.getAttribute('data-wizard-step'), 10);
            if (panelStep === step) {
                panel.classList.remove('hidden');
            } else {
                panel.classList.add('hidden');
            }
        });

        // Update stepper circles
        document.querySelectorAll('.jsi-step-indicator').forEach(function(btn) {
            const indicatorStep = parseInt(btn.getAttribute('data-step'), 10);
            const circle = btn.querySelector('.jsi-step-circle');
            const label = btn.querySelector('span:not(.jsi-step-circle)');
            if (!circle) return;

            if (indicatorStep < step) {
                // Completed step
                circle.className = 'jsi-step-circle flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 transition-all border-green-600 bg-green-600 text-white';
                circle.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
                if (label) label.className = 'text-xs font-medium text-green-700 hidden sm:inline';
            } else if (indicatorStep === step) {
                // Current step
                circle.className = 'jsi-step-circle flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 transition-all border-green-600 bg-green-600 text-white';
                circle.textContent = indicatorStep;
                if (label) label.className = 'text-xs font-medium text-gray-700 hidden sm:inline';
            } else {
                // Future step
                circle.className = 'jsi-step-circle flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 transition-all border-gray-300 bg-white text-gray-400';
                circle.textContent = indicatorStep;
                if (label) label.className = 'text-xs font-medium text-gray-400 hidden sm:inline';
            }
        });

        // Update progress bars
        document.querySelectorAll('.jsi-step-bar').forEach(function(bar) {
            const barIndex = parseInt(bar.getAttribute('data-bar'), 10);
            bar.style.width = (barIndex < step) ? '100%' : '0%';
        });

        // Update navigation buttons
        const prevBtn = document.getElementById('jsiWizardPrev');
        const nextBtn = document.getElementById('jsiWizardNext');
        const saveBtn = document.getElementById('jointInspectionSave');

        if (prevBtn) {
            if (step === 1) {
                prevBtn.classList.add('hidden');
            } else {
                prevBtn.classList.remove('hidden');
            }
        }

        if (nextBtn) {
            if (step === JSI_WIZARD_TOTAL_STEPS) {
                nextBtn.classList.add('hidden');
            } else {
                nextBtn.classList.remove('hidden');
            }
        }

        if (saveBtn) {
            if (step === JSI_WIZARD_TOTAL_STEPS) {
                saveBtn.classList.remove('hidden');
                // Re-apply enabled/disabled state based on workflow
                if (typeof updateWorkflowStatus === 'function') {
                    updateWorkflowStatus();
                }
            } else {
                saveBtn.classList.add('hidden');
            }
        }

        // Scroll modal content to top
        const modalContent = document.querySelector('#jointInspectionModal .overflow-y-auto');
        if (modalContent) {
            modalContent.scrollTop = 0;
        }
    }

    /**
     * Validate fields visible on the current wizard step.
     * Returns true if valid, false otherwise.
     */
    function jsiWizardValidateCurrentStep() {
        const currentPanel = document.querySelector('.jsi-wizard-step[data-wizard-step="' + jsiWizardCurrentStep + '"]');
        if (!currentPanel) return true;

        const errors = [];
        let firstInvalidField = null;

        const pushError = function(field, message) {
            errors.push(message);
            if (field) {
                field.classList.add('border-red-500');
                field.classList.remove('border-gray-300');
            }
            if (!firstInvalidField && field) {
                firstInvalidField = field;
            }
        };

        // Check required visible fields in current step
        currentPanel.querySelectorAll('[data-jsi-validate]').forEach(function(field) {
            // Skip hidden fields (inside hidden parents)
            if (field.closest('.hidden')) return;
            // Skip fields that are not required based on context
            if (field.hasAttribute('readonly') && field.id === 'jointInspectionFileNumber') return;

            const val = (field.value || '').trim();
            const label = field.closest('div')?.querySelector('label')?.textContent?.replace('*', '').trim() || field.name || field.id;

            if (val === '' && field.hasAttribute('required')) {
                // Only check explicit required
                pushError(field, label + ' is required.');
            }
        });

        // Step-specific validations
        if (jsiWizardCurrentStep === 1) {
            // Date validation
            const dateField = document.getElementById('jointInspectionDate');
            if (dateField && !dateField.value) {
                pushError(dateField, 'Inspection date is required.');
            }
            // Applicant name
            const applicantField = document.getElementById('jointInspectionApplicant');
            if (applicantField && !(applicantField.value || '').trim()) {
                pushError(applicantField, 'Applicant name is required.');
            }
            // File number
            const fileNoField = document.getElementById('jointInspectionFileNumber');
            if (fileNoField && !(fileNoField.value || '').trim()) {
                pushError(fileNoField, 'File number is required.');
            }
        }

        if (jsiWizardCurrentStep === 2) {
            // Boundary descriptions
            ['north', 'east', 'south', 'west'].forEach(function(dir) {
                const textarea = document.querySelector('[data-boundary-direction="' + dir + '"]');
                if (textarea && !(textarea.value || '').trim()) {
                    pushError(textarea, dir.charAt(0).toUpperCase() + dir.slice(1) + ' boundary is required.');
                }
            });
        }

        if (errors.length > 0) {
            const errBox = document.getElementById('jointInspectionValidationErrors');
            if (errBox) {
                errBox.innerHTML = '<p class="font-semibold mb-1">Please fix the following before proceeding:</p><ul class="list-disc list-inside space-y-0.5">' +
                    errors.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul>';
                errBox.classList.remove('hidden');
            }
            if (firstInvalidField) {
                firstInvalidField.focus();
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        return true;
    }

    // Initialize wizard event listeners (runs immediately since we're already inside DOMContentLoaded)
    (function() {
        // Next button
        const nextBtn = document.getElementById('jsiWizardNext');
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                jsiWizardGoToStep(jsiWizardCurrentStep + 1);
            });
        }

        // Previous button
        const prevBtn = document.getElementById('jsiWizardPrev');
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                jsiWizardGoToStep(jsiWizardCurrentStep - 1);
            });
        }

        // Stepper circle clicks (allow jumping to completed steps)
        document.querySelectorAll('.jsi-step-indicator').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetStep = parseInt(btn.getAttribute('data-step'), 10);
                if (targetStep < jsiWizardCurrentStep) {
                    // Can always go back
                    jsiWizardGoToStep(targetStep);
                } else if (targetStep > jsiWizardCurrentStep) {
                    // Allow forward navigation; validation is handled on final actions.
                    jsiWizardGoToStep(targetStep);
                }
            });
        });
    })();

    const lockStyleClasses = ['bg-gray-100', 'text-gray-600', 'cursor-not-allowed'];
    const lockableFieldIds = [
        'jointInspectionFileNumber',
        'jointInspectionApplicant',
        'jointInspectionLkn',
        'jointInspectionPlot',
        'applicantAddressPlot',
        'applicantAddressStreet',
        'applicantAddressStreetOther',
        'applicantAddressDistrict',
        'applicantAddressDistrictOther',
        'applicantAddressLga',
        'applicantAddressState',
        'locationStreet',
        'locationStreetOther',
        'locationDistrict',
        'locationDistrictOther',
        'locationLga',
        'locationState',
        'jsiSimplePlotNumber',
        'jsiSimpleApplicantAddress',
        'jsiSimplePropertyLocation',
        'jsiSimpleSchemeNumber',
    ];

    function lockField(field) {
        if (!field) {
            return;
        }

        if (isPageEditor && !pageLockedFieldIds.has(field.id)) {
            return;
        }

        if (field.id === 'jointInspectionApplicant') {
            field.disabled = true;
        } else if (field.tagName === 'SELECT') {
            field.disabled = true;
        } else {
            field.readOnly = true;
        }

        lockStyleClasses.forEach(cls => field.classList.add(cls));
        field.dataset.jsiLocked = 'true';
    }

    function lockFieldIfValue(field) {
        if (!field) {
            return;
        }

        if (isPageEditor && !pageLockedFieldIds.has(field.id)) {
            return;
        }

        const value = typeof field.value === 'string' ? field.value.trim() : field.value;
        if (value !== '' && value !== null && typeof value !== 'undefined') {
            lockField(field);
        }
    }

    function enforcePageStepOneLocks() {
        if (!isPageEditor) {
            return;
        }

        pageLockedFieldIds.forEach(id => lockField(document.getElementById(id)));
    }

    function unlockField(field) {
        if (!field) {
            return;
        }

        if (field.id === 'jointInspectionApplicant') {
            field.disabled = false;
        } else if (field.tagName === 'SELECT') {
            field.disabled = false;
        } else {
            field.readOnly = false;
        }

        lockStyleClasses.forEach(cls => field.classList.remove(cls));
        delete field.dataset.jsiLocked;
    }

    function unlockPrefilledFields() {
        lockableFieldIds.forEach(id => unlockField(document.getElementById(id)));
    }

    function lockAddressFields(prefix) {
        const map = addressFieldMap[prefix];
        if (!map) {
            return;
        }

        [
            map.plot,
            map.street,
            map.streetOther,
            map.district,
            map.districtOther,
            map.lga,
            map.state,
        ].forEach(id => lockFieldIfValue(document.getElementById(id)));
    }

    function syncInspectorRankDisplay() {
        const officerSelect = document.getElementById('jointInspectionOfficerSelect');
        const rankField = document.getElementById('jointInspectionOfficerRank');
        const hiddenOfficerId = document.getElementById('inspectionOfficerId');

        const selectedOption = officerSelect && officerSelect.options ? officerSelect.options[officerSelect.selectedIndex] : null;
        const rank = selectedOption ? (selectedOption.dataset.rank || '').trim() : '';
        const officerId = selectedOption ? (selectedOption.value || '') : '';

        if (rankField) {
            rankField.value = rank;
        }

        if (hiddenOfficerId) {
            hiddenOfficerId.value = officerId;
        }
    }

    function wireConversionCalculations() {
        // Length and width inputs were removed; measurement parsing now drives area calculations.
    }

    function showErrorMessage(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: message || 'An unexpected error occurred while processing your request.'
            });
        } else {
            alert(message || 'An unexpected error occurred while processing your request.');
        }
    }

    function showSuccessMessage(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message || 'Operation completed successfully!',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            alert(message || 'Operation completed successfully!');
        }
    }

    const validationSummary = document.getElementById('jointInspectionValidationErrors');

    function resetFieldValidationState() {
        if (!jointInspectionForm) {
            return;
        }

        jointInspectionForm.querySelectorAll('[data-jsi-validate]').forEach(field => {
            field.classList.remove('border-red-400', 'ring-1', 'ring-red-500', 'focus:ring-red-500', 'bg-red-50');
            field.removeAttribute('aria-invalid');
        });

        if (validationSummary) {
            validationSummary.classList.add('hidden');
            validationSummary.innerHTML = '';
        }
    }

    function markFieldInvalid(field) {
        if (!field) {
            return;
        }

        field.classList.add('border-red-400', 'ring-1', 'ring-red-500', 'focus:ring-red-500', 'bg-red-50');
        field.setAttribute('aria-invalid', 'true');
    }

    function renderValidationErrors(errors) {
        if (!validationSummary) {
            return;
        }

        if (!errors || errors.length === 0) {
            validationSummary.classList.add('hidden');
            validationSummary.innerHTML = '';
            return;
        }

        validationSummary.innerHTML = `
            <p class="font-semibold">Please fix the following to continue:</p>
            <ul class="mt-1 list-disc space-y-1 pl-4">
                ${errors.map(error => `<li>${error}</li>`).join('')}
            </ul>
        `;
        validationSummary.classList.remove('hidden');
    }

    function validateJointInspectionForm(context = {}) {
        const errors = [];
        let firstInvalidField = null;

        resetFieldValidationState();

        const pushError = (field, message) => {
            errors.push(message);
            markFieldInvalid(field);
            if (!firstInvalidField && field) {
                firstInvalidField = field;
            }
        };

        const dateField = document.getElementById('jointInspectionDate');
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (!dateField || !dateField.value) {
            pushError(dateField, 'Inspection date is required.');
        } else {
            // Parse as local time (YYYY-MM-DD → local midnight) to avoid UTC offset issues
            const parts = dateField.value.split('-');
            const enteredDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            if (Number.isNaN(enteredDate.getTime())) {
                pushError(dateField, 'Inspection date must be a valid date.');
            } else if (enteredDate > today) {
                pushError(dateField, 'Inspection date cannot be in the future.');
            }
        }

        const isConversion = jsiContext.isConversion === true;

        // Ensure compiled address outputs are refreshed before validation (only for builder mode)
        if (isConversion) {
            setAddressOutputs('applicant');
            setAddressOutputs('location');
        }

        const textFieldRules = [
            { id: 'jointInspectionApplicant', name: 'Applicant name', required: true, maxLength: 255 },
            { id: 'jointInspectionFileNumber', name: 'File number', required: true, maxLength: 255 },
            // Builder hidden fields - only validate when in Conversion context
            { id: 'jointInspectionLocation', name: 'Location', required: isConversion, maxLength: 255 },
            { id: 'jointInspectionApplicantAddress', name: 'Applicant address', required: isConversion, maxLength: 500 },
            { id: 'jointInspectionPlot', name: 'Plot number', required: isConversion, maxLength: 255 },
            { id: 'jointInspectionScheme', name: 'Scheme number', required: false, maxLength: 255 },
            { id: 'jointInspectionLkn', name: 'LPKN number', required: false, maxLength: 255 },
            { id: 'jointInspectionMeasurementSummary', name: 'Utilities measurement summary', required: !isConversion, maxLength: 1000 },
            // Simple address fields - only validate when in ST OSS context
            { id: 'jsiSimplePlotNumber', name: 'Plot number', required: !isConversion, maxLength: 255 },
            { id: 'jsiSimpleApplicantAddress', name: 'Applicant address', required: !isConversion, maxLength: 500 },
            { id: 'jsiSimplePropertyLocation', name: 'Property location', required: !isConversion, maxLength: 500 },
            { id: 'jsiSimpleSchemeNumber', name: 'Scheme number', required: !isConversion, maxLength: 255 },
        ];

        textFieldRules.forEach(rule => {
            const field = document.getElementById(rule.id);
            if (!field) {
                return;
            }

            const value = (field.value || '').trim();

            if (rule.required && value === '') {
                pushError(field, `${rule.name} is required.`);
                return;
            }

            if (rule.maxLength && value.length > rule.maxLength) {
                pushError(field, `${rule.name} must not exceed ${rule.maxLength} characters.`);
            }
        });

        // Address component validation — only applies to Conversion context (builder mode)
        if (isConversion) {
            [
                { prefix: 'applicant', label: 'Applicant address' },
                { prefix: 'location', label: 'Location address' }
            ].forEach(entry => {
                const components = collectAddressComponents(entry.prefix);
                const map = addressFieldMap[entry.prefix];

                if ((isOtherOptionValue(components.street) || isOtherOptionValue(components.street_label)) && components.street_other === '') {
                    pushError(document.getElementById(map.streetOther) || document.getElementById(map.street), `${entry.label}: specify street when selecting Other.`);
                }

                if ((isOtherOptionValue(components.district) || isOtherOptionValue(components.district_label)) && components.district_other === '') {
                    pushError(document.getElementById(map.districtOther) || document.getElementById(map.district), `${entry.label}: specify district when selecting Other.`);
                }

                if (!components.state) {
                    pushError(document.getElementById(map.state), `${entry.label}: select a state.`);
                }

                if (!components.lga) {
                    pushError(document.getElementById(map.lga), `${entry.label}: select an LGA.`);
                }
            });
        }

        const officerSelect = document.getElementById('jointInspectionOfficerSelect');
        if (officerSelect) {
            if ((officerSelect.value || '').trim() === '') {
                pushError(officerSelect, 'Select an inspection officer.');
            }
        } else {
            const officerInput = document.getElementById('jointInspectionOfficer');
            if (officerInput && officerInput.value.trim() === '') {
                pushError(officerInput, 'Inspection officer / rank is required.');
            }
        }

        const sectionsField = document.getElementById('jointInspectionSections');
        if (!isConversion && sectionsField) {
            const rawValue = (sectionsField.value || '').trim();
            if (rawValue === '') {
                pushError(sectionsField, 'Number of sections is required. Use 0 if not applicable.');
            }
        }

        if (!isConversion) {
            const reservationRequiredField = document.querySelector('input[name="non_conversion_reservation_required"]:checked');
            if (!reservationRequiredField) {
                const anchor = document.querySelector('input[name="non_conversion_reservation_required"]');
                pushError(anchor, 'Reservation selection is required.');
            } else if (reservationRequiredField.value === '1') {
                const reservationField = document.getElementById('jointInspectionRoadReservation');
                const reservationOtherField = document.getElementById('jointInspectionRoadReservationOther');

                if (reservationField && String(reservationField.value || '').trim() === '') {
                    pushError(reservationField, 'Road reservation is required when reservation is Yes.');
                }

                if (reservationField && String(reservationField.value || '').trim().toUpperCase() === 'OTHER') {
                    if (reservationOtherField && String(reservationOtherField.value || '').trim() === '') {
                        pushError(reservationOtherField, 'Please specify the other road reservation value.');
                    }
                }
            }
        }

        const prevailingSelect = document.getElementById('jointInspectionPrevailingLandUse');
        if (prevailingSelect && prevailingSelect.value.trim() === '') {
            pushError(prevailingSelect, 'Select the prevailing land use.');
        }

        const appliedSelect = document.getElementById('jointInspectionAppliedLandUse');
        if (appliedSelect && appliedSelect.value.trim() === '') {
            pushError(appliedSelect, 'Select the applied land use.');
        }

        const complianceSelect = document.getElementById('jointInspectionCompliance');
        if (complianceSelect && complianceSelect.value.trim() === '') {
            pushError(complianceSelect, 'Select the compliance status.');
        }

        const boundarySegments = context.boundarySegments || {};
        const boundaryValues = Object.values(boundarySegments)
            .map(value => (value || '').trim())
            .filter(value => value !== '');

        if (boundaryValues.length === 0) {
            const northField = document.querySelector('[data-boundary-direction="north"]');
            pushError(northField, 'Provide at least one boundary description.');
        } else {
            ['north', 'east', 'south', 'west'].forEach(direction => {
                const value = (boundarySegments[direction] || '').trim();
                if (value.length > 1000) {
                    const field = document.querySelector(`[data-boundary-direction="${direction}"]`);
                    pushError(field, `${direction.charAt(0).toUpperCase() + direction.slice(1)} boundary must not exceed 1000 characters.`);
                }
            });
        }

        if (!isConversion) {
            const measurementEntries = Array.isArray(context.measurementEntries)
                ? context.measurementEntries
                : [];

            measurementEntries.forEach((entry, index) => {
                if (!entry) {
                    return;
                }

                const description = (entry.description || '').trim();
                const dimension = (entry.dimension || '').trim();
                const countRaw = (entry.count || '').toString().trim();

                if (description === '' && (dimension !== '' || countRaw !== '')) {
                    pushError(null, `Measurement entry ${index + 1} requires a description.`);
                }

                if (countRaw !== '') {
                    const numericCount = Number(countRaw);
                    if (!Number.isFinite(numericCount) || numericCount < 0) {
                        pushError(null, `Measurement entry ${index + 1} count must be zero or a positive number.`);
                    }
                }
            });
        }

        const observationToggle = jointInspectionForm
            ? jointInspectionForm.querySelector('input[name="has_additional_observations"]:checked')
            : null;

        if (observationToggle && observationToggle.value === '1') {
            const observationField = document.getElementById('jointInspectionObservations');
            if (!observationField || observationField.value.trim() === '') {
                pushError(observationField, 'Enter the additional observation details or switch to "No".');
            }
        }

        if (isConversion) {
            const purposeField = document.getElementById('conversionPurpose');
            if (purposeField && purposeField.value.trim() === '') {
                pushError(purposeField, 'Purpose is required for conversion inspections.');
            }

            const detailedLandUseField = document.getElementById('conversionDetailedLandUse');
            if (detailedLandUseField && detailedLandUseField.value.trim() === '') {
                pushError(detailedLandUseField, 'Detailed land use is required.');
            }

            const purposeLutField = document.getElementById('conversionPurposeLut');
            if (purposeLutField && purposeLutField.value.trim() === '') {
                pushError(purposeLutField, 'Purpose is required.');
            }

            if (purposeLutField && isPurposeOtherOption(purposeLutField.value)) {
                const purposeLutOtherField = document.getElementById('conversionPurposeLutOther');
                if (purposeLutOtherField && purposeLutOtherField.value.trim() === '') {
                    pushError(purposeLutOtherField, 'Please specify the other purpose.');
                }
            }

            if (purposeField && purposeField.value === 'Private Layout') {
                const privateLayoutField = document.getElementById('conversionPrivateLayoutNumber');
                if (privateLayoutField && privateLayoutField.value.trim() === '') {
                    pushError(privateLayoutField, 'Private layout number is required when purpose is Private Layout.');
                }
            }

            const requireRadio = (name, label) => {
                const selected = document.querySelector(`input[name="${name}"]:checked`);
                if (!selected) {
                    const anchor = document.querySelector(`input[name="${name}"]`);
                    pushError(anchor, `${label} is required for conversion inspections.`);
                }
            };

            requireRadio('conformity', 'Conformity');
            requireRadio('accessibility', 'Accessibility');
            requireRadio('adequate_size_requirement', 'Adequate size requirement');
            requireRadio('land_traversing_utility', 'Land traversing utility');

            const numericField = (id, label) => {
                const field = document.getElementById(id);
                if (!field || field.value.trim() === '') {
                    return null;
                }
                const value = Number(field.value);
                if (!Number.isFinite(value) || value < 0) {
                    pushError(field, `${label} must be zero or a positive number.`);
                    return null;
                }
                return value;
            };

            numericField('existingSiteArea', 'Existing area covered');
            numericField('recommendedSiteArea', 'Recommended area covered');

            const siteMeasurementSync = syncAllSiteMeasurementFields({ autoFillArea: false });
            if (!siteMeasurementSync.valid && siteMeasurementSync.firstInvalid) {
                pushError(siteMeasurementSync.firstInvalid.field, siteMeasurementSync.firstInvalid.message);
            }

            const reservationRequiredField = document.querySelector('input[name="reservation_required"]:checked');
            if (!reservationRequiredField) {
                const reservationAnchor = document.querySelector('input[name="reservation_required"]');
                pushError(reservationAnchor, 'Reservation selection is required.');
            } else if (reservationRequiredField.value === '1') {
                const roadCategoryField = document.getElementById('conversionRoadCategory');
                const roadCategoryOtherField = document.getElementById('conversionRoadCategoryOther');
                const existingReservationField = document.getElementById('conversionRightOfWay');
                const recommendedReservationField = document.getElementById('conversionRecommendedReservation');
                const recommendedCommentStatusField = document.getElementById('recommendedCommentStatus');
                const howManyMetersField = document.getElementById('howManyMeters');

                if (roadCategoryField && roadCategoryField.value.trim() === '') {
                    pushError(roadCategoryField, 'Road category is required when reservation is Yes.');
                }

                if (roadCategoryField && String(roadCategoryField.value || '').trim() !== '') {
                    if (roadCategoryOtherField && roadCategoryOtherField.value.trim() === '') {
                        pushError(roadCategoryOtherField, 'Please specify the observed road category.');
                    }
                }

                if (existingReservationField && existingReservationField.value.trim() === '') {
                    pushError(existingReservationField, 'Existing reservation is required when reservation is Yes.');
                }

                if (recommendedReservationField && recommendedReservationField.value.trim() === '') {
                    pushError(recommendedReservationField, 'Recommended reservation is required when reservation is Yes.');
                }

                if (recommendedReservationField && ['Recommended', 'Comment', 'Recommended Comment'].includes(String(recommendedReservationField.value || '').trim())) {
                    if (recommendedCommentStatusField && recommendedCommentStatusField.value.trim() === '') {
                        pushError(recommendedCommentStatusField, 'Please select Adequate or Not Adequate.');
                    }

                    if (recommendedCommentStatusField && String(recommendedCommentStatusField.value || '').trim() === 'Not Adequate') {
                        if (howManyMetersField && howManyMetersField.value.trim() === '') {
                            pushError(howManyMetersField, 'Please specify how many meters.');
                        }
                    }
                }
            }
        }

        const existingStructureSelected = document.querySelector('input[name="existing_structure"]:checked');
        if (!existingStructureSelected) {
            const existingStructureAnchor = document.querySelector('input[name="existing_structure"]');
            pushError(existingStructureAnchor, 'Existing structure selection is required.');
        } else if (existingStructureSelected.value === '1') {
            const dimensionsField = document.getElementById('existingStructureDimensions');
            const amountField = document.getElementById('existingStructureAmount');
            const dimensionsRaw = dimensionsField ? dimensionsField.value.trim() : '';
            const parsedDimensions = parseMeasurementInput(dimensionsRaw);

            if (!dimensionsRaw) {
                pushError(dimensionsField, 'Provide existing structure dimensions in L x B x H format.');
            } else if (parsedDimensions.length !== 3) {
                pushError(dimensionsField, 'Existing structure dimensions must contain exactly 3 values (L x B x H).');
            }

            if (!amountField || amountField.value.trim() === '') {
                pushError(amountField, 'Existing structure total is required and is calculated from dimensions.');
            } else {
                const amountValue = Number(amountField.value);
                if (!Number.isFinite(amountValue) || amountValue < 0) {
                    pushError(amountField, 'Existing structure total must be zero or a positive number.');
                }
            }
        }

        if (jsiContext.isConversion) {
            const needForEiaSelected = document.querySelector('input[name="need_for_eia"]:checked');
            if (!needForEiaSelected) {
                const needForEiaAnchor = document.querySelector('input[name="need_for_eia"]');
                pushError(needForEiaAnchor, 'Need for Environmental Impact Analysis Report (EIAR) selection is required.');
            }
        }

        renderValidationErrors(errors);

        return {
            valid: errors.length === 0,
            errors,
            firstInvalidField,
        };
    }

    function setBoundarySegmentFields(segments = {}) {
        boundaryDirections.forEach(direction => {
            const field = document.querySelector(`[data-boundary-direction="${direction}"]`);

            if (!field) {
                return;
            }

            const value = segments && typeof segments === 'object'
                ? (segments[direction] ?? '')
                : '';

            field.value = value === null ? '' : String(value);
        });
    }

    function parseBoundaryDescriptionSegments(description) {
        if (!description || typeof description !== 'string') {
            return null;
        }

        const normalized = description.replace(/\s+/g, ' ').trim();

        if (!normalized) {
            return null;
        }

        const pattern = /On the\s+(north|east|south|west)\s*:?[\s-]*(.*?)(?=(?:On the\s+(?:north|east|south|west)\s*:)|$)/gi;
        const segments = {};
        let match;

        while ((match = pattern.exec(normalized)) !== null) {
            const direction = match[1].toLowerCase();
            let text = match[2] || '';
            text = text.trim().replace(/[\s.;,]+$/, '');

            if (text !== '') {
                segments[direction] = text;
            }
        }

        if (Object.keys(segments).length > 0) {
            return segments;
        }

        const fallbackSegments = {};
        normalized.split(/\s*;\s*/).forEach(part => {
            if (!part) {
                return;
            }

            const fallbackMatch = part.match(/On the\s+(north|east|south|west)\s*:?[\s-]*(.*)/i);
            if (!fallbackMatch) {
                return;
            }

            const direction = fallbackMatch[1].toLowerCase();
            let text = (fallbackMatch[2] || '').trim().replace(/[\s.;,]+$/, '');

            if (text !== '') {
                fallbackSegments[direction] = text;
            }
        });

        return Object.keys(fallbackSegments).length > 0 ? fallbackSegments : null;
    }
 
    // Function to open the editor (modal or page)
    function openJointInspectionModal(applicationId, subApplicationId = null, options = {}) {
        const modal = document.getElementById('jointInspectionModal');
        const modalAvailable = Boolean(modal);
        const shouldDisplayModal = options.showModal !== false && modalAvailable;

        const fallbackApplicationId = window.jointInspectionDefaults?.application_id ?? null;
        const fallbackSubApplicationId = window.jointInspectionDefaults?.sub_application_id ?? null;

        const resolvedApplicationId = applicationId ?? fallbackApplicationId ?? null;
        const resolvedSubApplicationId = subApplicationId ?? fallbackSubApplicationId ?? null;

        const normalizedFileNumber = (options.fileNumber || options.file_number || '').toString().toUpperCase();
        const isConversionFile = options.isConversionFile === true
            || options.is_conversion_file === true
            || normalizedFileNumber.startsWith('CON-');

        setConversionContext(isConversionFile, options.source, normalizedFileNumber);

        currentApplicationId = resolvedApplicationId !== null && resolvedApplicationId !== undefined && resolvedApplicationId !== ''
            ? String(resolvedApplicationId).trim()
            : '';
        currentSubApplicationId = resolvedSubApplicationId !== null && resolvedSubApplicationId !== undefined && resolvedSubApplicationId !== ''
            ? String(resolvedSubApplicationId).trim()
            : '';
        window.currentApplicationId = currentApplicationId;
        window.currentSubApplicationId = currentSubApplicationId;
        isUnitApplication = currentSubApplicationId !== '';

        console.log('Opening Joint Inspection Editor:', {
            applicationId: currentApplicationId,
            subApplicationId: currentSubApplicationId,
            isUnitApplication,
            shouldDisplayModal
        });

        // Set form values
        const modalApplicationField = document.getElementById('modal_application_id');
        const modalSubApplicationField = document.getElementById('modal_sub_application_id');

        if (modalApplicationField) {
            modalApplicationField.value = currentApplicationId;
            modalApplicationField.setAttribute('value', currentApplicationId);
            modalApplicationField.dispatchEvent(new Event('input', { bubbles: true }));
            modalApplicationField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (modalSubApplicationField) {
            modalSubApplicationField.value = currentSubApplicationId;
            modalSubApplicationField.setAttribute('value', currentSubApplicationId);
            modalSubApplicationField.dispatchEvent(new Event('input', { bubbles: true }));
            modalSubApplicationField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Reset form and clear previous data before loading new data
        if (jointInspectionForm && options.preserveValues !== true) {
            jointInspectionForm.reset();
            resetFieldValidationState();
            unlockPrefilledFields();
            enforcePageStepOneLocks();
        }
        if (options.preserveValues === true) {
            unlockPrefilledFields();
            enforcePageStepOneLocks();
        }
        resetMeasurementEntriesUI();

        // For Conversion context, keep the file number field readonly (select-only)
        if (jsiContext.isConversion) {
            const fnField = document.getElementById('jointInspectionFileNumber');
            if (fnField) {
                fnField.readOnly = true;
                fnField.classList.add('bg-gray-50', 'cursor-not-allowed');
                fnField.classList.remove('focus:ring-2', 'focus:ring-blue-500');
                fnField.placeholder = 'Use Select button to pick a file number';
            }
        }

        // Apply context-specific header styling
        applyModalHeaderStyle(jsiContext.isConversion);

        // Reset wizard to step 1
        jsiWizardGoToStep(1);

        const fileNumberField = document.getElementById('jointInspectionFileNumber');
        if (fileNumberField) {
            fileNumberField.value = normalizedFileNumber || '';
        }

        // Hide shared areas section
        const sharedAreasSection = document.getElementById('sharedAreasSection');
        if (sharedAreasSection) {
            sharedAreasSection.classList.add('hidden');
        }

        if (shouldDisplayModal) {
            hideAllActionMenus();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        } else if (modalAvailable) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        // Reset workflow state
        jsiWorkflowState = {
            isSaved: false,
            isGenerated: false,
            isSubmitted: false,
            recordId: null
        };
        window.jsiWorkflowState = jsiWorkflowState;
        const hydratedFromSaved = hydrateJointInspectionFromSavedReport(window.jointInspectionSavedReport);

        if (!hydratedFromSaved) {
            updateWorkflowStatus();
        }

        // Load existing data if available
        loadExistingJointInspectionData(
            currentApplicationId || null,
            currentSubApplicationId || null,
            { skipMeasurementHydration: hydratedFromSaved }
        );
    }

    // Function to close the editor
    function closeJointInspectionModal(options = {}) {
        const modal = document.getElementById('jointInspectionModal');
        const modalVisible = modal && modal.classList.contains('flex');

        if (modalVisible) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        if (jointInspectionForm && options.preserveValues !== true) {
            jointInspectionForm.reset();
            resetFieldValidationState();
            unlockPrefilledFields();
        }
        if (options.preserveMeasurements !== true) {
            resetMeasurementEntriesUI();
        }
        hideAllActionMenus();

        if (modalVisible && options.reload) {
            window.location.reload();
            return;
        }

        if (!modalVisible) {
            if (options.reload) {
                window.location.reload();
                return;
            }

            if (options.redirect !== false) {
                const targetUrl = options.returnUrl || window.jointInspectionReturnUrl;
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            }
        }
    }

    // Function to load existing data
    async function loadExistingJointInspectionData(applicationId, subApplicationId = null, options = {}) {
        const { skipMeasurementHydration = false } = options;
        console.log('Loading existing data for:', { applicationId, subApplicationId, skipMeasurementHydration });
        
        try {
            let endpoint = '';
            let data = null;
            
            if (subApplicationId) {
                // Load unit application data
                endpoint = '{{ route("programmes.planning.unit.survey", ["id" => "__UNIT_ID__"]) }}'.replace('__UNIT_ID__', subApplicationId);
            } else if (applicationId) {
                // Load primary application data
                endpoint = '{{ route("programmes.planning.primary.survey", ["id" => "__PRIMARY_ID__"]) }}'.replace('__PRIMARY_ID__', applicationId);
            }
            
            if (endpoint) {
                const response = await fetch(endpoint, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.data) {
                        data = result.data;
                    }
                }
            }
            
            // Pre-populate form fields based on loaded data
            if (data) {
                const compiledFromComponents = (components) => {
                    if (!components || typeof components !== 'object') {
                        return '';
                    }
                    return compileAddressStringFromComponents(components);
                };

                // Set file number
                const fileNumberField = document.getElementById('jointInspectionFileNumber');
                if (fileNumberField) {
                    const fileNumber = data.file_number
                        || data.file_no
                        || data.fileno
                        || data.file_no_primary
                        || '';
                    if (fileNumber) {
                        fileNumberField.value = String(fileNumber).toUpperCase();
                    }
                    lockFieldIfValue(fileNumberField);
                }

                // Set applicant name from owner_name if available
                const applicantField = document.getElementById('jointInspectionApplicant');
                const applicantName = data.applicant_name || data.owner_name || '';
                if (applicantField && !(applicantField.value || '').trim() && applicantName) {
                    applicantField.value = toTitleCase(applicantName);
                    lockField(applicantField);
                }

                // Set location from components or fallback text (full address preview)
                const locationField = document.getElementById('jointInspectionLocation');
                const locationComponents = data.location_components || null;
                const locationText = data.property_location || data.location || '';
                if (locationComponents) {
                    setAddressComponents('location', locationComponents);
                    const compiledLocation = compiledFromComponents(locationComponents) || locationText;
                    if (locationField && compiledLocation) {
                        locationField.value = compiledLocation;
                    }
                    setAddressOutputs('location');
                } else if (locationField && locationText) {
                    locationField.value = locationText;
                    const preview = document.getElementById('locationAddressPreview');
                    if (preview) {
                        preview.textContent = locationText;
                    }
                    const hiddenLocation = document.getElementById('jointInspectionLocation');
                    if (hiddenLocation) {
                        hiddenLocation.value = locationText;
                    }
                }

                // Derive applicant address from unit application address_* columns when components are absent
                const derivedApplicantComponents = (!data.applicant_address_components && subApplicationId && (
                    data.address_street_name || data.address_district || data.address_lga || data.address_state || data.address_plot_no || data.address_house_no
                )) ? {
                    plot_number: data.address_house_no || data.address_plot_no || '',
                    street: data.address_street_name || data.owner_street_name || '',
                    street_other: '',
                    district: data.address_district || '',
                    district_other: '',
                    lga: data.address_lga || '',
                    state: data.address_state || data.state || data.state_name || ''
                } : null;

                const applicantAddressComponents = data.applicant_address_components || derivedApplicantComponents || null;
                const applicantAddressText = data.applicant_address || data.address || '';
                if (applicantAddressComponents) {
                    setAddressComponents('applicant', applicantAddressComponents);
                    const compiledApplicant = compiledFromComponents(applicantAddressComponents) || applicantAddressText;
                    const applicantAddressHidden = document.getElementById('jointInspectionApplicantAddress');
                    if (applicantAddressHidden && compiledApplicant) {
                        applicantAddressHidden.value = compiledApplicant;
                    }
                    setAddressOutputs('applicant');
                } else if (applicantAddressText) {
                    const applicantAddressHidden = document.getElementById('jointInspectionApplicantAddress');
                    if (applicantAddressHidden && !applicantAddressHidden.value) {
                        applicantAddressHidden.value = applicantAddressText;
                    }
                    const preview = document.getElementById('applicantAddressPreview');
                    if (preview) {
                        preview.textContent = applicantAddressText;
                    }
                }

                // Populate simple address inputs (for ST OSS context)
                const simpleApplicantAddr = document.getElementById('jsiSimpleApplicantAddress');
                const simpleLocation = document.getElementById('jsiSimplePropertyLocation');
                const simplePlot = document.getElementById('jsiSimplePlotNumber');
                const simpleScheme = document.getElementById('jsiSimpleSchemeNumber');

                let resolvedApplicantAddress = toTitleCase(applicantAddressText || (applicantAddressComponents ? compileAddressStringFromComponents(applicantAddressComponents) : ''));
                let resolvedLocation = toTitleCase(locationText || (locationComponents ? compileAddressStringFromComponents(locationComponents) : ''));

                // When both house_no and plot_no exist, strip the plot_no from the full address
                const appHouseNo = data.address_house_no || data.property_house_no || '';
                const appPlotNo = data.address_plot_no || data.property_plot_no || data.plot_no || '';
                if (appHouseNo && appPlotNo && appHouseNo !== appPlotNo) {
                    resolvedApplicantAddress = stripPlotFromAddress(resolvedApplicantAddress, appHouseNo, appPlotNo);
                }

                const locHouseNo = data.property_house_no || '';
                const locPlotNo = data.property_plot_no || data.plot_no || '';
                if (locHouseNo && locPlotNo && locHouseNo !== locPlotNo) {
                    resolvedLocation = stripPlotFromAddress(resolvedLocation, locHouseNo, locPlotNo);
                }

                if (simpleApplicantAddr && !simpleApplicantAddr.value && resolvedApplicantAddress) {
                    simpleApplicantAddr.value = resolvedApplicantAddress;
                    lockFieldIfValue(simpleApplicantAddr);
                }
                if (simpleLocation && !simpleLocation.value && resolvedLocation) {
                    simpleLocation.value = resolvedLocation;
                    lockFieldIfValue(simpleLocation);
                }
                if (simplePlot && !simplePlot.value) {
                    const plotValue = data.plot_no || data.plot_number || data.property_plot_no || data.address_plot_no || '';
                    if (plotValue) {
                        simplePlot.value = plotValue;
                        lockFieldIfValue(simplePlot);
                    }
                }
                if (simpleScheme && !simpleScheme.value) {
                    const schemeValue = data.scheme_plan_number || data.scheme_no || '';
                    if (schemeValue) {
                        simpleScheme.value = schemeValue;
                        lockFieldIfValue(simpleScheme);
                    }
                }
                
                // Set scheme number
                const schemeField = document.getElementById('jointInspectionScheme');
                if (schemeField && !schemeField.value && data.scheme_plan_number) {
                    schemeField.value = data.scheme_plan_number;
                } else if (schemeField && !schemeField.value && data.scheme_no) {
                    schemeField.value = data.scheme_no;
                }
                
                // Set LPKN number
                const lknField = document.getElementById('jointInspectionLkn');
                if (lknField && !lknField.value && data.lkn_number) {
                    lknField.value = data.lkn_number;
                }
                lockFieldIfValue(lknField);

                lockFieldIfValue(document.getElementById('jointInspectionPlot'));
                
                // Display shared areas from application
                if (data.shared_utilities && Array.isArray(data.shared_utilities) && data.shared_utilities.length > 0) {
                    const sharedAreasSection = document.getElementById('sharedAreasSection');
                    const sharedAreasDisplay = document.getElementById('sharedAreasDisplay');
                    
                    if (sharedAreasSection && sharedAreasDisplay) {
                        // Clear previous content
                        sharedAreasDisplay.innerHTML = '';
                        
                        // Add each shared area as a badge
                        data.shared_utilities.forEach(area => {
                            const badge = document.createElement('span');
                            badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200';
                            badge.textContent = area;
                            sharedAreasDisplay.appendChild(badge);
                        });
                        
                        // Show the section
                        sharedAreasSection.classList.remove('hidden');
                    }
                } else {
                    // Hide the section if no shared areas
                    const sharedAreasSection = document.getElementById('sharedAreasSection');
                    if (sharedAreasSection) {
                        sharedAreasSection.classList.add('hidden');
                    }
                }
                
                // Auto-populate measurement entries from shared utilities and measurements
                const measurementEntries = [];

                if (Array.isArray(data.shared_utilities) && data.shared_utilities.length > 0) {
                    data.shared_utilities.forEach(rawUtility => {
                        const description = (rawUtility || '')
                            .toString()
                            .replace(/[_-]/g, ' ')
                            .replace(/\b\w/g, letter => letter.toUpperCase())
                            .trim();

                        if (description) {
                            measurementEntries.push({
                                description,
                                count: '1',
                                dimension: ''
                            });
                        }
                    });
                }

                if (Array.isArray(data.site_measurements) && data.site_measurements.length > 0) {
                    data.site_measurements.forEach(measurement => {
                        const description = (measurement.utility_type || measurement.description || '')
                            .toString()
                            .trim();
                        const dimensionValue = measurement.measurement || measurement.dimension || '';

                        if (!description) {
                            return;
                        }

                        const existing = measurementEntries.find(entry => entry.description.toLowerCase() === description.toLowerCase());
                        if (existing) {
                            existing.dimension = dimensionValue !== undefined && dimensionValue !== null
                                ? String(dimensionValue)
                                : '';
                            existing.count = measurement.count !== undefined && measurement.count !== null
                                ? String(measurement.count)
                                : (measurement.quantity !== undefined && measurement.quantity !== null
                                    ? String(measurement.quantity)
                                    : (existing.count && String(existing.count).trim() !== '' ? String(existing.count).trim() : '1'));
                        } else {
                            measurementEntries.push({
                                description,
                                count: measurement.count !== undefined && measurement.count !== null
                                    ? String(measurement.count)
                                    : (measurement.quantity !== undefined && measurement.quantity !== null
                                        ? String(measurement.quantity)
                                        : '1'),
                                dimension: dimensionValue !== undefined && dimensionValue !== null
                                    ? String(dimensionValue)
                                    : ''
                            });
                        }
                    });
                }

                const hasExistingMeasurements = hasHydratedMeasurementEntries();

                if (!skipMeasurementHydration) {
                    if (measurementEntries.length > 0) {
                        propagateMeasurementEntries(measurementEntries);
                    } else if (!hasExistingMeasurements) {
                        resetMeasurementEntriesUI();
                    }
                }
            }
            
            // Load existing joint inspection report if it exists
            if (applicationId || subApplicationId) {
                await loadExistingInspectionReport(applicationId, subApplicationId);
            }
            
            // Update workflow status after loading data
            updateWorkflowStatus();
            
        } catch (error) {
            console.error('Error loading existing data:', error);
            // Don't show error to user as this is optional pre-population
        }
    }
    
    // Load existing inspection report data
    async function loadExistingInspectionReport(applicationId, subApplicationId) {
        try {
            // Determine the correct route based on application type
            let route = '';
            if (subApplicationId) {
                route = '{{ route("sub-actions.planning-recommendation.joint-inspection.show", ["subApplication" => "__SUB_ID__"]) }}'.replace('__SUB_ID__', subApplicationId);
            } else if (applicationId) {
                route = '{{ route("planning-recommendation.joint-inspection.show", ["application" => "__APP_ID__"]) }}'.replace('__APP_ID__', applicationId);
            }
            
            if (!route) {
                console.log('No valid route found for loading existing inspection report');
                return;
            }
            
            const response = await fetch(route, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data) {
                    const report = result.data;
                    
                    // Set workflow state based on existing report
                    jsiWorkflowState.recordId = report.id;
                    jsiWorkflowState.isSaved = true; // If record exists, it's been saved
                    jsiWorkflowState.isGenerated = Boolean(report.is_generated === 1 || report.is_generated === true);
                    jsiWorkflowState.isSubmitted = Boolean(report.is_submitted === 1 || report.is_submitted === true);

                    const inferredConversion = report.source === 'Conversion Applications'
                        || (typeof report.is_conversion_file !== 'undefined' && (report.is_conversion_file === 1 || report.is_conversion_file === true || report.is_conversion_file === '1'))
                        || (report.purpose && String(report.purpose).toUpperCase().startsWith('CON'));

                    if (inferredConversion) {
                        setConversionContext(true, report.source || 'Conversion Applications', report.file_number || jsiContext.fileNumber);
                    }
                    
                    console.log('DEBUG - Loaded existing report workflow state:', jsiWorkflowState);
                    
                    // Populate form fields from existing report
                    populateFormFromReport(report);
                    
                    // Update workflow status after loading
                    updateWorkflowStatus();
                }
            } else if (response.status === 404) {
                // No existing report found - this is normal for new inspections
                console.log('No existing inspection report found (404)');
            } else {
                console.warn('Error loading existing inspection report:', response.status, response.statusText);
            }
        } catch (error) {
            console.error('Error loading existing inspection report:', error);
        }
    }

    function normalizeInspectionDateValue(dateValue) {
        if (!dateValue) {
            return '';
        }

        if (typeof window.formatDateForInput === 'function') {
            const formatted = window.formatDateForInput(dateValue);
            if (formatted) {
                return formatted;
            }
        }

        if (typeof dateValue === 'string') {
            const isoMatch = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (isoMatch) {
                return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
            }
        }

        try {
            const parsed = new Date(dateValue);
            if (!isNaN(parsed.getTime())) {
                const year = parsed.getUTCFullYear();
                const month = String(parsed.getUTCMonth() + 1).padStart(2, '0');
                const day = String(parsed.getUTCDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
        } catch (error) {
            console.warn('Unable to normalize inspection date value:', dateValue, error);
        }

        return '';
    }

    // Populate form fields from existing report data
    function populateFormFromReport(report) {
        // Populate basic fields
        const fieldMappings = {
            'jointInspectionDate': 'inspection_date',
            'jointInspectionLkn': 'lkn_number',
            'jointInspectionApplicant': 'applicant_name',
            'jointInspectionFileNumber': 'file_number',
            'jointInspectionLocation': 'location',
            'jointInspectionApplicantAddress': 'applicant_address',
            'jointInspectionPlot': 'plot_number',  
            'jointInspectionUnitNumber': 'unit_number',
            'jointInspectionScheme': 'scheme_number',
            'jointInspectionSections': 'sections_count',
            'jointInspectionUnitDimension': 'unit_dimension',
            'jointInspectionPrevailingLandUse': 'prevailing_land_use',
            'jointInspectionAppliedLandUse': 'applied_land_use',
            'jointInspectionCompliance': 'compliance_status',
            'jointInspectionOfficer': 'inspection_officer',
            'jointInspectionMeasurementSummary': 'existing_site_measurement_summary',
            'jointInspectionObservations': 'additional_observations'
        };

        Object.entries(fieldMappings).forEach(([fieldId, reportKey]) => {
            const field = document.getElementById(fieldId);
            if (!field || !(reportKey in report)) {
                return;
            }

            let value = report[reportKey];

            if (value === null || typeof value === 'undefined') {
                return;
            }

            if (fieldId === 'jointInspectionDate') {
                value = normalizeInspectionDateValue(value);
                if (!value) {
                    return;
                }
            }

            if (typeof value === 'object') {
                return;
            }

            // Normalize casing for name and address fields
            if (['jointInspectionApplicant', 'jointInspectionApplicantAddress', 'jointInspectionLocation'].includes(fieldId)) {
                value = toTitleCase(value);
            }

            field.value = value;
        });

        if (report.location_components) {
            setAddressComponents('location', report.location_components);
        } else if (report.location) {
            const locationHidden = document.getElementById('jointInspectionLocation');
            if (locationHidden) {
                locationHidden.value = report.location;
            }
            const locationPreview = document.getElementById('locationAddressPreview');
            if (locationPreview) {
                locationPreview.textContent = report.location;
            }
        }

        if (report.applicant_address_components) {
            setAddressComponents('applicant', report.applicant_address_components);
        } else if (report.applicant_address) {
            const applicantHidden = document.getElementById('jointInspectionApplicantAddress');
            if (applicantHidden) {
                applicantHidden.value = report.applicant_address;
            }
            const applicantPreview = document.getElementById('applicantAddressPreview');
            if (applicantPreview) {
                applicantPreview.textContent = report.applicant_address;
            }
        }

        if (report.applicant_name !== null && typeof report.applicant_name !== 'undefined' && String(report.applicant_name).trim() !== '') {
            lockField(document.getElementById('jointInspectionApplicant'));
        }
        lockFieldIfValue(document.getElementById('jointInspectionLkn'));
        lockFieldIfValue(document.getElementById('jointInspectionPlot'));

        // Populate simple address inputs from report data
        const simpleApplicantAddr = document.getElementById('jsiSimpleApplicantAddress');
        const simpleLocation = document.getElementById('jsiSimplePropertyLocation');
        const simplePlot = document.getElementById('jsiSimplePlotNumber');
        const simpleScheme = document.getElementById('jsiSimpleSchemeNumber');

        if (simpleApplicantAddr && report.applicant_address) {
            simpleApplicantAddr.value = toTitleCase(report.applicant_address);
            lockFieldIfValue(simpleApplicantAddr);
        }
        if (simpleLocation && report.location) {
            simpleLocation.value = toTitleCase(report.location);
            lockFieldIfValue(simpleLocation);
        }
        if (simplePlot && report.plot_number) {
            simplePlot.value = report.plot_number;
            lockFieldIfValue(simplePlot);
        }
        if (simpleScheme && report.scheme_number) {
            simpleScheme.value = report.scheme_number;
            lockFieldIfValue(simpleScheme);
        }

        applyNonConversionReservationFromValue(report.road_reservation || '');

        if (report.source) {
            const inferredConversion = report.source === 'Conversion Applications';
            if (inferredConversion) {
                setConversionContext(true, report.source, report.file_number || jsiContext.fileNumber);
            }
        }

        const purposeField = document.getElementById('conversionPurpose');
        if (purposeField && typeof report.purpose !== 'undefined') {
            purposeField.value = report.purpose || '';
            syncPrivateLayoutVisibility();
        }

        const privateLayoutField = document.getElementById('conversionPrivateLayoutNumber');
        if (privateLayoutField && typeof report.private_layout_number !== 'undefined') {
            privateLayoutField.value = report.private_layout_number || '';
        }

        const applyRadioValue = (name, value) => {
            if (typeof value === 'undefined' || value === null) {
                return;
            }
            const normalized = value === true || value === 1 || value === '1' ? '1' : '0';
            const radio = document.querySelector(`input[name="${name}"][value="${normalized}"]`);
            if (radio) {
                radio.checked = true;
            }
        };

        applyRadioValue('conformity', report.conformity);
        applyRadioValue('accessibility', report.accessibility);
        applyRadioValue('adequate_size_requirement', report.adequate_size_requirement);
        applyRadioValue('land_traversing_utility', report.land_traversing_utility);

        const numericFieldMappings = [
            { id: 'existingSiteArea', key: 'existing_site_area' },
            { id: 'recommendedSiteArea', key: 'recommended_site_area' },
        ];

        numericFieldMappings.forEach(mapping => {
            const field = document.getElementById(mapping.id);
            if (!field || !(mapping.key in report)) {
                return;
            }
            const value = report[mapping.key];
            if (value === null || typeof value === 'undefined') {
                return;
            }
            const numeric = Number(value);
            field.value = Number.isFinite(numeric) ? numeric : value;
        });

        populateSiteMeasurementInput('existingMeasurementInput', report.existing_site_length, report.existing_site_width);
        populateSiteMeasurementInput('recommendedMeasurementInput', report.recommended_site_length, report.recommended_site_width);
        syncAllSiteMeasurementFields({ autoFillArea: false });

        const unitField = document.getElementById('jointInspectionUnitNumber');
        if (unitField) {
            const currentUnitRaw = typeof unitField.value === 'string' ? unitField.value.trim() : unitField.value;
            const hasUnitValue = currentUnitRaw !== undefined && currentUnitRaw !== null && String(currentUnitRaw).trim() !== '';

            if (!hasUnitValue) {
                const reportUnit = report.unit_number ?? report.unitNumber ?? null;
                const defaultsUnit = window.jointInspectionDefaults ? window.jointInspectionDefaults.unit_number : null;
                const savedReportUnit = window.jointInspectionSavedReport ? window.jointInspectionSavedReport.unit_number : null;

                const fallback = reportUnit ?? defaultsUnit ?? savedReportUnit;

                if (typeof fallback === 'string' && fallback.trim() !== '') {
                    unitField.value = fallback.trim();
                } else if (typeof fallback === 'number' && !Number.isNaN(fallback)) {
                    unitField.value = String(fallback);
                }
            }
        }

        // Handle checkboxes and radio buttons
        const availableCheckbox = document.querySelector('input[name="available_on_ground"]');
        if (availableCheckbox) {
            const availableValue = report.available_on_ground;
            const availableOnGround = availableValue === true || availableValue === 1 || availableValue === '1';
            availableCheckbox.checked = availableOnGround;
        }

        const officerSelect = document.getElementById('jointInspectionOfficerSelect');
        if (officerSelect) {
            let officerId = report.inspection_officer_id || report.inspection_officerid || null;

            if ((officerId === null || officerId === undefined || officerId === '') && report.inspection_officer) {
                const matchedId = String(report.inspection_officer).match(/\[ID:\s*(\d+)\]/i);
                if (matchedId && matchedId[1]) {
                    officerId = matchedId[1];
                }
            }

            if (officerId !== null && officerId !== undefined && officerId !== '') {
                officerSelect.value = String(officerId);
            }
            syncInspectorRankDisplay();
        } else {
            const officerInput = document.getElementById('jointInspectionOfficer');
            if (officerInput && typeof report.inspection_officer !== 'undefined') {
                officerInput.value = report.inspection_officer || '';
            }
        }

        if (report.has_additional_observations !== null && typeof report.has_additional_observations !== 'undefined') {
            const rawValue = report.has_additional_observations;
            const normalized = rawValue === true || rawValue === 1 || rawValue === '1' ? '1' : '0';
            const radio = document.querySelector(`input[name="has_additional_observations"][value="${normalized}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        }

        // Handle boundary descriptions
        let boundarySegments = null;

        if (report.boundary_segments) {
            try {
                boundarySegments = typeof report.boundary_segments === 'string'
                    ? JSON.parse(report.boundary_segments)
                    : report.boundary_segments;
            } catch (error) {
                console.warn('Error parsing boundary segments:', error);
            }
        }

        if ((!boundarySegments || Object.keys(boundarySegments).length === 0) && report.boundary_description) {
            const parsedSegments = parseBoundaryDescriptionSegments(report.boundary_description);
            if (parsedSegments) {
                boundarySegments = parsedSegments;
            }
        }

        setBoundarySegmentFields(boundarySegments || {});

        const boundaryField = document.getElementById('jointInspectionBoundary');
        if (boundaryField) {
            const description = report.boundary_description || generateBoundaryDescription(boundarySegments || {});
            boundaryField.value = description;
        }

        // Handle measurement entries
        if (report.existing_site_measurement_entries) {
            try {
                const measurements = typeof report.existing_site_measurement_entries === 'string'
                    ? JSON.parse(report.existing_site_measurement_entries)
                    : report.existing_site_measurement_entries;

                if (Array.isArray(measurements)) {
                    const existingStructureMeta = extractExistingStructureMetaFromEntries(measurements);
                    if (existingStructureMeta) {
                        setExistingStructureState(
                            parseExistingStructureSelectionFromMeta(existingStructureMeta),
                            existingStructureMeta.measurement_dimension || '',
                            existingStructureMeta.dimension || ''
                        );
                    } else {
                        setExistingStructureState(null, '');
                    }

                    const needForEiaMeta = extractNeedForEiaMetaFromEntries(measurements);
                    if (needForEiaMeta) {
                        setNeedForEiaState(parseNeedForEiaSelectionFromMeta(needForEiaMeta));
                    } else {
                        setNeedForEiaState(null);
                    }

                    const conversionPurposeLutMeta = extractConversionPurposeLutMetaFromEntries(measurements);
                    if (conversionPurposeLutMeta) {
                        applyConversionPurposeLutFromMeta(conversionPurposeLutMeta);
                    } else if (report.purpose) {
                        applyConversionPurposeLutFromMeta({ measurement_dimension: String(report.purpose) });
                    } else {
                        applyConversionPurposeLutFromMeta(null);
                    }

                    const conversionReservationMeta = extractConversionReservationMetaFromEntries(measurements);
                    if (conversionReservationMeta) {
                        applyConversionReservationFromMeta(conversionReservationMeta);
                    } else {
                        applyConversionReservationFromReport(report);
                    }

                    const normalized = measurements
                        .map((entry, index) => normalizeMeasurementEntry(entry, index))
                        .filter(entry => entry.description && !isMetaMeasurementDescription(entry.description));

                    if (normalized.length > 0) {
                        propagateMeasurementEntries(normalized);
                    } else {
                        resetMeasurementEntriesUI();
                    }
                }
            } catch (error) {
                console.warn('Error parsing measurement entries:', error);
                setExistingStructureState(null, '');
                setNeedForEiaState(null);
                if (report.purpose) {
                    applyConversionPurposeLutFromMeta({ measurement_dimension: String(report.purpose) });
                } else {
                    applyConversionPurposeLutFromMeta(null);
                }
                applyConversionReservationFromReport(report);
                resetMeasurementEntriesUI();
            }
        } else {
            setExistingStructureState(null, '');
            setNeedForEiaState(null);
            if (report.purpose) {
                applyConversionPurposeLutFromMeta({ measurement_dimension: String(report.purpose) });
            } else {
                applyConversionPurposeLutFromMeta(null);
            }
            applyConversionReservationFromReport(report);
            resetMeasurementEntriesUI();
        }
    }

    function hydrateJointInspectionFromSavedReport(savedReport) {
        if (!savedReport) {
            return false;
        }

        try {
            const reportApplicationId = savedReport.application_id ?? savedReport.applicationId ?? null;
            const reportSubApplicationId = savedReport.sub_application_id ?? savedReport.subApplicationId ?? null;

            if (!reportApplicationId && !reportSubApplicationId) {
                return false;
            }

            if (currentApplicationId && reportApplicationId && String(reportApplicationId) !== String(currentApplicationId)) {
                if (!reportSubApplicationId || !currentSubApplicationId || String(reportSubApplicationId) !== String(currentSubApplicationId)) {
                    return false;
                }
            }

            if (currentSubApplicationId && reportSubApplicationId && String(reportSubApplicationId) !== String(currentSubApplicationId)) {
                return false;
            }

            populateFormFromReport(savedReport);

            jsiWorkflowState.isSaved = true;
            if (savedReport.id || savedReport.report_id) {
                jsiWorkflowState.recordId = savedReport.id ?? savedReport.report_id;
            }
            if (typeof savedReport.is_generated !== 'undefined') {
                jsiWorkflowState.isGenerated = Boolean(savedReport.is_generated);
            }
            if (typeof savedReport.is_submitted !== 'undefined') {
                jsiWorkflowState.isSubmitted = Boolean(savedReport.is_submitted);
            }

            updateWorkflowStatus();
            return true;
        } catch (error) {
            console.warn('Hydration from saved report failed:', error);
            return false;
        }
    }

    // Handle form submission
    const jointInspectionForm = document.getElementById('jointInspectionForm');
    if (!jointInspectionForm) {
        console.warn('Joint inspection form element not found. Aborting modal setup.');
        return;
    }

    initializeAddressPickers();

    const conversionPurposeSelect = document.getElementById('conversionPurpose');
    if (conversionPurposeSelect) {
        conversionPurposeSelect.addEventListener('change', syncPrivateLayoutVisibility);
    }

    const conversionDetailedLandUseSelect = document.getElementById('conversionDetailedLandUse');
    if (conversionDetailedLandUseSelect) {
        conversionDetailedLandUseSelect.addEventListener('change', syncConversionPurposeByDetailedLandUse);
    }

    const prevailingLandUseSelect = document.getElementById('jointInspectionPrevailingLandUse');
    if (prevailingLandUseSelect) {
        prevailingLandUseSelect.addEventListener('change', populateAppliedLandUseByDetailedLandUse);
    }

    const conversionPurposeLutSelect = document.getElementById('conversionPurposeLut');
    if (conversionPurposeLutSelect) {
        conversionPurposeLutSelect.addEventListener('change', syncConversionPurposeOtherVisibility);
    }

    const conversionRoadCategorySelect = document.getElementById('conversionRoadCategory');
    if (conversionRoadCategorySelect) {
        conversionRoadCategorySelect.addEventListener('change', syncRoadCategoryOtherVisibility);
    }

    wireConversionCalculations();

    document.querySelectorAll('input[name="existing_structure"]').forEach(radio => {
        radio.addEventListener('change', syncExistingStructureDimensionsVisibility);
    });

    document.querySelectorAll('input[name="reservation_required"]').forEach(radio => {
        radio.addEventListener('change', syncReservationVisibility);
    });

    document.querySelectorAll('input[name="non_conversion_reservation_required"]').forEach(radio => {
        radio.addEventListener('change', syncNonConversionReservationVisibility);
    });

    const recommendedCommentStatusSelect = document.getElementById('recommendedCommentStatus');
    if (recommendedCommentStatusSelect) {
        recommendedCommentStatusSelect.addEventListener('change', syncRightOfWayOtherVisibility);
    }

    const nonConversionReservationSelect = document.getElementById('jointInspectionRoadReservation');
    if (nonConversionReservationSelect) {
        nonConversionReservationSelect.addEventListener('change', syncNonConversionReservationOtherVisibility);
    }

    const existingStructureAmountInput = document.getElementById('existingStructureAmount');
    if (existingStructureAmountInput) {
        existingStructureAmountInput.addEventListener('input', syncExistingStructureAmountRaw);
    }

    syncExistingStructureDimensionsVisibility();
    syncReservationVisibility();
    syncRoadCategoryOtherVisibility();
    syncRightOfWayOtherVisibility();
    populateAppliedLandUseByDetailedLandUse();
    syncNonConversionReservationVisibility();
    syncNonConversionReservationOtherVisibility();
    syncConversionPurposeByDetailedLandUse();
    syncConversionPurposeOtherVisibility();

    const addressInputs = [
        'applicantAddressPlot',
        'applicantAddressStreet',
        'applicantAddressStreetOther',
        'applicantAddressDistrict',
        'applicantAddressDistrictOther',
        'applicantAddressLga',
        'applicantAddressState',
        'jointInspectionPlot',
        'locationStreet',
        'locationStreetOther',
        'locationDistrict',
        'locationDistrictOther',
        'locationLga',
        'locationState',
    ];

    addressInputs.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const handler = () => {
            if (id.endsWith('Street')) {
                toggleOtherField(id, id.includes('applicant') ? 'applicantAddressStreetOther' : 'locationStreetOther');
            }
            if (id.endsWith('District')) {
                toggleOtherField(id, id.includes('applicant') ? 'applicantAddressDistrictOther' : 'locationDistrictOther');
            }
            setAddressOutputs('applicant');
            setAddressOutputs('location');
        };

        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
    });

    toggleOtherField('applicantAddressStreet', 'applicantAddressStreetOther');
    toggleOtherField('applicantAddressDistrict', 'applicantAddressDistrictOther');
    toggleOtherField('locationStreet', 'locationStreetOther');
    toggleOtherField('locationDistrict', 'locationDistrictOther');

    // Initialize address outputs on load
    setAddressOutputs('applicant');
    setAddressOutputs('location');

    const officerSelect = document.getElementById('jointInspectionOfficerSelect');
    if (officerSelect) {
        officerSelect.addEventListener('change', syncInspectorRankDisplay);
        syncInspectorRankDisplay();
    }

    // Shared function to prepare form data
    function prepareFormData() {
        syncAllSiteMeasurementFields();

        const formData = new FormData(jointInspectionForm);
        const csrfTokenInput = jointInspectionForm.querySelector('input[name="_token"]');

        if (!csrfTokenInput) {
            throw new Error('Security token is missing. Please refresh the page and try again.');
        }
        
        // Ensure conversion source flags are included
        formData.set('is_conversion_file', jsiContext.isConversion ? '1' : '0');
        formData.set('source', jsiContext.source || (jsiContext.isConversion ? 'Conversion Applications' : 'ST One Stop Shop'));

        // Applicant field may be disabled after auto-fill; force include current value.
        const applicantField = document.getElementById('jointInspectionApplicant');
        if (applicantField) {
            formData.set('applicant_name', String(applicantField.value || '').trim());
        }

        if (jsiContext.isConversion) {
            // Conversion context: Build addresses from structured builder components
            const applicantComponents = collectAddressComponents('applicant');
            const applicantAddress = compileAddressStringFromComponents(applicantComponents);
            formData.set('applicant_address', applicantAddress || '');
            // Resolve dropdown IDs to display labels before sending to server
            const applicantServer = resolveComponentLabels(applicantComponents);
            Object.entries(applicantServer || {}).forEach(([key, value]) => {
                const normalized = typeof value === 'string' ? value.trim() : value;
                if (normalized !== null && normalized !== undefined && normalized !== '') {
                    formData.set(`applicant_address_components[${key}]`, normalized);
                }
            });

            const locationComponents = collectAddressComponents('location');
            const locationAddress = compileAddressStringFromComponents(locationComponents);
            formData.set('location', locationAddress || '');
            const locationServer = resolveComponentLabels(locationComponents);
            Object.entries(locationServer || {}).forEach(([key, value]) => {
                const normalized = typeof value === 'string' ? value.trim() : value;
                if (normalized !== null && normalized !== undefined && normalized !== '') {
                    formData.set(`location_components[${key}]`, normalized);
                }
            });
        } else {
            // ST One Stop Shop context: Read directly from simple text inputs
            const simpleApplicantAddr = (document.getElementById('jsiSimpleApplicantAddress')?.value || '').trim();
            const simpleLocation = (document.getElementById('jsiSimplePropertyLocation')?.value || '').trim();
            const simplePlot = (document.getElementById('jsiSimplePlotNumber')?.value || '').trim();
            const simpleScheme = (document.getElementById('jsiSimpleSchemeNumber')?.value || '').trim();
            const reservationRequired = document.querySelector('input[name="non_conversion_reservation_required"]:checked');
            const reservationSelect = document.getElementById('jointInspectionRoadReservation');
            const reservationOther = document.getElementById('jointInspectionRoadReservationOther');

            formData.set('applicant_address', simpleApplicantAddr);
            formData.set('location', simpleLocation);
            formData.set('plot_number', simplePlot);
            formData.set('scheme_number', simpleScheme);
            formData.set('non_conversion_reservation_required', reservationRequired ? reservationRequired.value : '');

            if (reservationRequired && reservationRequired.value === '1') {
                const reservationValue = reservationSelect ? String(reservationSelect.value || '').trim() : '';
                const reservationOtherValue = reservationOther ? String(reservationOther.value || '').trim() : '';

                if (reservationValue.toUpperCase() === 'OTHER') {
                    formData.set('road_reservation', reservationOtherValue);
                    formData.set('road_reservation_other', reservationOtherValue);
                } else {
                    formData.set('road_reservation', reservationValue);
                    formData.set('road_reservation_other', '');
                }
            } else {
                formData.set('road_reservation', '');
                formData.set('road_reservation_other', '');
            }
        }

        // Collect boundary segments
        const boundarySegments = {};
        boundaryDirections.forEach(direction => {
            const field = document.querySelector(`[data-boundary-direction="${direction}"]`);
            boundarySegments[direction] = field ? field.value.trim() : '';
        });

        const measurementEntries = collectMeasurementEntries();

        const validationResult = validateJointInspectionForm({
            boundarySegments,
            measurementEntries
        });

        if (!validationResult.valid) {
            if (validationResult.firstInvalidField && typeof validationResult.firstInvalidField.focus === 'function') {
                validationResult.firstInvalidField.focus({ preventScroll: false });
                validationResult.firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            throw new Error('Please review the highlighted fields before saving the report.');
        }
        
        // Generate boundary description
        const boundaryDescription = generateBoundaryDescription(boundarySegments);
        const boundaryField = document.getElementById('jointInspectionBoundary');
        if (boundaryField) {
            boundaryField.value = boundaryDescription;
        }
        formData.set('boundary_description', boundaryDescription);
        
        formData.delete('boundary_segments');
        boundaryDirections.forEach(direction => {
            formData.set(`boundary_segments[${direction}]`, boundarySegments[direction] || '');
        });

        // Ensure we always send an array, even if empty
        const measurementEntriesArray = Array.isArray(measurementEntries) ? measurementEntries : [];
        const nonMetaMeasurementEntries = measurementEntriesArray.filter(entry => !isMetaMeasurementDescription(entry?.description || ''));
        const existingStructurePayload = collectExistingStructurePayload();
        const existingStructureMetaEntry = buildExistingStructureMetaEntry(existingStructurePayload);
        
        if (existingStructureMetaEntry) {
            nonMetaMeasurementEntries.unshift(existingStructureMetaEntry);
        }

        // Only add conversion-specific meta entries for conversion applications
        if (jsiContext.isConversion) {
            const needForEiaPayload = collectNeedForEiaPayload();
            const needForEiaMetaEntry = buildNeedForEiaMetaEntry(needForEiaPayload);
            const conversionPurposeLutPayload = collectConversionPurposeLutPayload();
            const conversionPurposeLutMetaEntry = buildConversionPurposeLutMetaEntry(conversionPurposeLutPayload);
            const conversionReservationPayload = collectConversionReservationPayload();
            const conversionReservationMetaEntry = buildConversionReservationMetaEntry(conversionReservationPayload);

            if (needForEiaMetaEntry) {
                nonMetaMeasurementEntries.unshift(needForEiaMetaEntry);
            }

            if (conversionPurposeLutMetaEntry) {
                nonMetaMeasurementEntries.unshift(conversionPurposeLutMetaEntry);
            }

            if (conversionReservationMetaEntry) {
                nonMetaMeasurementEntries.unshift(conversionReservationMetaEntry);
            }
            
            // Also set the specific form fields for conversion
            formData.set('need_for_eia', needForEiaPayload.selection ?? '');
            formData.set('detailed_land_use_id', conversionPurposeLutPayload.detailed_land_use_id || '');
            formData.set('purpose_lut', conversionPurposeLutPayload.purpose_lut || '');
            formData.set('purpose_lut_option', conversionPurposeLutPayload.purpose_lut_option || '');
            formData.set('purpose_lut_other', conversionPurposeLutPayload.purpose_lut_other || '');
            formData.set('reservation_required', conversionReservationPayload.reservation_required ?? '');
            formData.set('road_category', conversionReservationPayload.road_category || '');
            formData.set('observed_road_category', conversionReservationPayload.observed_road_category || '');
            formData.set('road_category_other', conversionReservationPayload.road_category_other || '');
            formData.set('right_of_way', conversionReservationPayload.right_of_way || '');
            formData.set('right_of_way_other', conversionReservationPayload.right_of_way_other || '');
            formData.set('existing_road_reservation', conversionReservationPayload.right_of_way || '');
            formData.set('recommended_road_reservation', conversionReservationPayload.recommended_reservation || '');
            formData.set('recommended_comment_status', conversionReservationPayload.recommended_comment_status || '');
            formData.set('how_many_meters', conversionReservationPayload.how_many_meters || '');
        } else {
            // For non-conversion (ST), ensure these are empty/null
            formData.set('need_for_eia', '');
            formData.set('detailed_land_use_id', '');
            formData.set('purpose_lut', '');
            formData.set('reservation_required', '');
        }

        formData.set('existing_structure', existingStructurePayload.selection ?? '');
        formData.set('existing_structure_dimensions', existingStructurePayload.dimensions || '');
        formData.set('existing_structure_amount', Number.isFinite(existingStructurePayload.amount) ? Number(existingStructurePayload.amount).toFixed(2) : '');

        console.log('Measurement entries being sent:', nonMetaMeasurementEntries);
        
        // Send measurement entries as proper form array data
        nonMetaMeasurementEntries.forEach((entry, index) => {
            formData.set(`existing_site_measurement_entries[${index}][sn]`, entry.sn || '');
            formData.set(`existing_site_measurement_entries[${index}][description]`, entry.description || '');
            formData.set(`existing_site_measurement_entries[${index}][count]`, entry.count && String(entry.count).trim() !== '' ? String(entry.count).trim() : '1');
            formData.set(`existing_site_measurement_entries[${index}][measurement_dimension]`, entry.measurement_dimension || '');
            formData.set(`existing_site_measurement_entries[${index}][dimension]`, entry.dimension || '');
        });

        // Capture inspection officer selection (prefer dropdown)
        const officerSelect = document.getElementById('jointInspectionOfficerSelect');
        if (officerSelect) {
            const selectedOption = officerSelect.options[officerSelect.selectedIndex];
            const selectedValue = selectedOption ? selectedOption.value : '';
            const selectedLabel = selectedOption ? (selectedOption.dataset.label || selectedOption.textContent || '').trim() : '';

            formData.set('inspection_officer_id', selectedValue || '');
            formData.set('inspection_officer', selectedLabel);
        } else {
            const officerInput = document.getElementById('jointInspectionOfficer');
            if (officerInput) {
                formData.set('inspection_officer', officerInput.value || '');
            }
        }
        
        return { formData, csrfToken: csrfTokenInput.value };
    }

    // Shared function to submit form data
    function submitFormData(formData, csrfToken, action = 'save') {
        // Add action type to form data
        formData.set('action', action);
        
        // Determine the correct route based on application type
        const route = isUnitApplication 
            ? '{{ route("sub-actions.planning-recommendation.joint-inspection.store") }}'
            : '{{ route("planning-recommendation.joint-inspection.store") }}';
        
        return fetch(route, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(async response => {
            let data;
            try {
                data = await response.json();
            } catch (parseError) {
                console.error('Failed to parse response JSON:', parseError);
                throw new Error('Received an unexpected response from the server.');
            }

            if (!response.ok) {
                throw new Error(data.message || 'An error occurred while processing the request.');
            }

            if (!data.success) {
                throw new Error(data.message || 'An error occurred while processing the request.');
            }

            return data;
        });
    }

    // JSI Workflow State Management
    let jsiWorkflowState = {
        isSaved: false,
        isGenerated: false,
        isSubmitted: false,
        recordId: null
    };

    window.jsiWorkflowState = jsiWorkflowState;

    // Update workflow status display
    function updateWorkflowStatus() {
        const statusIndicator = document.getElementById('statusIndicator');
        const statusText = document.getElementById('statusText');
        const saveBtn = document.getElementById('jointInspectionSave');
        const generateBtn = document.getElementById('jointInspectionGenerate');
        const submitBtn = document.getElementById('jointInspectionSubmit');

        // Check if elements exist before updating
        if (!statusIndicator || !statusText || !saveBtn) {
            console.warn('Required workflow elements not found, skipping status update');
            return;
        }

        if (jsiWorkflowState.isSubmitted) {
            // Final state: All buttons disabled
            statusIndicator.innerHTML = '<span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>';
            statusText.textContent = 'Submitted';
            
            saveBtn.disabled = true;
            saveBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            saveBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            
            if (generateBtn) {
                generateBtn.disabled = true;
                generateBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                generateBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            }
            
        } else if (jsiWorkflowState.isGenerated) {
            // Generated state: Save enabled for revisions, Generate disabled, Submit enabled
            statusIndicator.innerHTML = '<span class="w-2 h-2 bg-blue-500 rounded-full mr-1"></span>';
            statusText.textContent = 'Generated';
            
            saveBtn.disabled = false;
            saveBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            saveBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            
            if (generateBtn) {
                generateBtn.disabled = true;
                generateBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                generateBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            }

            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                submitBtn.classList.add('bg-purple-600', 'hover:bg-purple-700');
            }
        } else if (jsiWorkflowState.isSaved) {
            // Saved state prior to auto-generation fallback: Save enabled, Generate enabled, Submit disabled
            statusIndicator.innerHTML = '<span class="w-2 h-2 bg-blue-400 rounded-full mr-1"></span>';
            statusText.textContent = 'Saved';
            
            saveBtn.disabled = false;
            saveBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            saveBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            
            if (generateBtn) {
                generateBtn.disabled = false;
                generateBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                generateBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            }
            
        } else {
            // Draft state: Only Save enabled
            statusIndicator.innerHTML = '<span class="w-2 h-2 bg-gray-400 rounded-full mr-1"></span>';
            statusText.textContent = 'Draft';
            
            saveBtn.disabled = false;
            saveBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            saveBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            
            if (generateBtn) {
                generateBtn.disabled = true;
                generateBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                generateBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            }
        }
    }

    window.updateJointInspectionWorkflowStatus = updateWorkflowStatus;


    // Handle Save button click
    const saveButton = document.getElementById('jointInspectionSave');
    if (saveButton) {
        saveButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (saveButton.disabled) {
                return;
            }
            
            try {
                const { formData, csrfToken } = prepareFormData();
                
                // Add record ID if we have one (for updates)
                if (jsiWorkflowState.recordId) {
                    formData.set('record_id', jsiWorkflowState.recordId);
                }
                
                saveButton.disabled = true;
                saveButton.textContent = 'Saving...';
                saveButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                saveButton.classList.add('bg-gray-400', 'cursor-not-allowed');
                
                submitFormData(formData, csrfToken, 'save')
                .then(data => {
                    jsiWorkflowState.isSaved = true;
                    jsiWorkflowState.recordId = data.report_id || data.record_id;
                    
                    // Set workflow flags based on server response
                    if (data.is_generated !== undefined) {
                        jsiWorkflowState.isGenerated = Boolean(data.is_generated);
                    }
                    if (data.is_submitted !== undefined) {
                        jsiWorkflowState.isSubmitted = Boolean(data.is_submitted);
                    }
                    
                    saveButton.disabled = false;
                    saveButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    saveButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    saveButton.textContent = 'Save';

                    const fallbackUrl = data.report_url || data.view_url || null;
                    if (fallbackUrl) {
                        window.lastJointInspectionViewUrl = fallbackUrl;
                    }

                    updateWorkflowStatus();
                    showSuccessMessage(data.message || 'Joint Site Inspection Report saved successfully!');

                    // Reload the page after a brief delay so the table reflects the saved report
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                })
                .catch(error => {
                    console.error('Error saving joint inspection report:', error);
                    showErrorMessage(error.message || 'An error occurred while saving the report.');
                    
                    // Reset button state on error
                    saveButton.disabled = false;
                    saveButton.textContent = 'Save';
                    saveButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    saveButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
                });
            } catch (error) {
                showErrorMessage(error.message);
                
                // Reset button state on error
                saveButton.disabled = false;
                saveButton.textContent = 'Save';
                saveButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                saveButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }
        });
    }

    // Handle Generate Report button click
    const generateButton = document.getElementById('jointInspectionGenerate');
    if (generateButton) {
        generateButton.addEventListener('click', function(e) {
            e.preventDefault();

            if (generateButton.disabled) {
                return;
            }

            if (!jsiWorkflowState.isSaved) {
                showErrorMessage('Please save the Joint Site Inspection Report before marking it as generated.');
                return;
            }

            // If the report is already generated, just refresh the status flag without calling the generate endpoint again
            if (jsiWorkflowState.isGenerated && typeof updateGeneratedStatus === 'function') {
                generateButton.disabled = true;
                generateButton.textContent = 'Updating...';
                generateButton.classList.remove('bg-green-600', 'hover:bg-green-700');
                generateButton.classList.add('bg-gray-400', 'cursor-not-allowed');

                updateGeneratedStatus()
                    .then(() => {
                        showSuccessMessage('Joint Site Inspection Report marked as generated.');
                        generateButton.textContent = 'Generate';
                        updateWorkflowStatus();
                    })
                    .catch(error => {
                        console.error('Error updating generated status:', error);
                        showErrorMessage(error.message || 'Could not update the generated status.');
                        generateButton.disabled = false;
                        generateButton.textContent = 'Generate';
                        generateButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                        generateButton.classList.add('bg-green-600', 'hover:bg-green-700');
                    });
                return;
            }

            try {
                const { formData, csrfToken } = prepareFormData();
                if (jsiWorkflowState.recordId) {
                    formData.set('record_id', jsiWorkflowState.recordId);
                }

                generateButton.disabled = true;
                generateButton.textContent = 'Generating...';
                generateButton.classList.remove('bg-green-600', 'hover:bg-green-700');
                generateButton.classList.add('bg-gray-400', 'cursor-not-allowed');

                submitFormData(formData, csrfToken, 'generate')
                    .then(data => {
                        jsiWorkflowState.isGenerated = true;

                        if (data.is_generated !== undefined) {
                            jsiWorkflowState.isGenerated = Boolean(data.is_generated);
                        }
                        if (data.is_submitted !== undefined) {
                            jsiWorkflowState.isSubmitted = Boolean(data.is_submitted);
                        }

                        updateWorkflowStatus();

                        if (data.report_url) {
                            window.lastJointInspectionViewUrl = data.report_url;
                            window.open(data.report_url, '_blank');
                        }

                        showSuccessMessage(data.message || 'Joint Site Inspection Report generated successfully!');
                        generateButton.textContent = 'Generate';
                        updateWorkflowStatus();
                    })
                    .catch(error => {
                        console.error('Error generating joint inspection report:', error);
                        showErrorMessage(error.message || 'An error occurred while generating the report.');

                        generateButton.disabled = false;
                        generateButton.textContent = 'Generate';
                        generateButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                        generateButton.classList.add('bg-green-600', 'hover:bg-green-700');
                    });
            } catch (error) {
                showErrorMessage(error.message);
                generateButton.disabled = false;
                generateButton.textContent = 'Generate';
                generateButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                generateButton.classList.add('bg-green-600', 'hover:bg-green-700');
            }
        });
    }

    // Handle Submit button click
    const submitButton = document.getElementById('jointInspectionSubmit');
    if (submitButton) {
        submitButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Prevent submit if not generated or already submitted
            if (!jsiWorkflowState.isGenerated || jsiWorkflowState.isSubmitted || submitButton.disabled) {
                console.log('Submit prevented: not generated, already submitted, or button disabled');
                return;
            }
            
            // Confirm submission
            if (!confirm('Are you sure you want to submit this Joint Site Inspection Report? This action cannot be undone.')) {
                return;
            }
            
            try {
                const { formData, csrfToken } = prepareFormData();
                if (jsiWorkflowState.recordId) {
                    formData.set('record_id', jsiWorkflowState.recordId);
                }
                
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
                submitButton.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                submitButton.classList.add('bg-gray-400', 'cursor-not-allowed');
                
                submitFormData(formData, csrfToken, 'submit')
                .then(data => {
                    jsiWorkflowState.isSubmitted = true;
                    
                    // Set workflow flags based on server response
                    if (data.is_submitted !== undefined) {
                        jsiWorkflowState.isSubmitted = Boolean(data.is_submitted);
                    }
                    
                    updateWorkflowStatus();
                    showSuccessMessage(data.message || 'Joint Site Inspection Report submitted successfully!');
                    
                    setTimeout(() => {
                        const closeOptions = {
                            reload: true,
                            returnUrl: data.redirect_url || data.return_url || data.back_url || window.jointInspectionReturnUrl,
                            redirect: data.redirect === false ? false : true
                        };
                        closeJointInspectionModal(closeOptions);
                    }, 2000);
                })
                .catch(error => {
                    console.error('Error submitting joint inspection report:', error);
                    showErrorMessage(error.message || 'An error occurred while submitting the report.');
                    
                    // Reset button state on error
                    submitButton.disabled = false;
                    submitButton.textContent = 'Submit';
                    submitButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    submitButton.classList.add('bg-purple-600', 'hover:bg-purple-700');
                });
            } catch (error) {
                showErrorMessage(error.message);
                submitButton.disabled = false;
                submitButton.textContent = 'Submit';
            }
        });
    }

    // Prevent default form submission
    jointInspectionForm.addEventListener('submit', function(e) {
        e.preventDefault();
    });

    // Handle modal close events
    document.querySelectorAll('[data-joint-inspection-dismiss]').forEach(element => {
        element.addEventListener('click', closeJointInspectionModal);
    });

    // Handle file number selector button (Conversion context)
    const jsiSelectFileNumberBtn = document.getElementById('jsiSelectFileNumberBtn');
    if (jsiSelectFileNumberBtn) {
        jsiSelectFileNumberBtn.addEventListener('click', function() {
            if (!window.GlobalFileNoModal) {
                console.error('GlobalFileNoModal is not available. Ensure global-fileno-modal.js is loaded.');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'File number selector is not available. Please refresh the page.' });
                }
                return;
            }

            window.GlobalFileNoModal.open({
                targetFields: ['#jointInspectionFileNumber'],
                autoPopulateGenericFields: false,
                callback: function(data) {
                    if (!data || !data.fileNumber) return;

                    const fileNumberField = document.getElementById('jointInspectionFileNumber');
                    if (fileNumberField) {
                        fileNumberField.value = String(data.fileNumber).toUpperCase();
                        fileNumberField.readOnly = true;
                        fileNumberField.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
                        fileNumberField.classList.remove('focus:ring-2', 'focus:ring-blue-500');
                        fileNumberField.dataset.jsiLocked = 'true';
                        fileNumberField.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    // Update context file number
                    jsiContext.fileNumber = String(data.fileNumber).toUpperCase();

                    // Set the application_id from the selected record so the save endpoint finds it
                    if (data.record && data.record.id) {
                        const modalAppField = document.getElementById('modal_application_id');
                        if (modalAppField) {
                            modalAppField.value = data.record.id;
                            currentApplicationId = String(data.record.id);
                            window.currentApplicationId = currentApplicationId;
                        }
                    }

                    // Auto-populate other fields from the file record if available
                    if (data.record) {
                        const r = data.record;

                        const applicantField = document.getElementById('jointInspectionApplicant');
                        if (applicantField && !(applicantField.value || '').trim() && (r.file_name || r.owner_name)) {
                            applicantField.value = toTitleCase(r.file_name || r.owner_name);
                            lockField(applicantField);
                        }

                        const plotField = document.getElementById('jointInspectionPlot');
                        if (plotField && !plotField.value && r.plot_no) {
                            plotField.value = r.plot_no;
                        }

                        const lknField = document.getElementById('jointInspectionLkn');
                        if (lknField && !lknField.value && (r.lkn_number || r.tp_plan_no)) {
                            lknField.value = r.lkn_number || r.tp_plan_no;
                        }
                    }
                }
            });
        });
    }

    // Handle additional observations toggle
    document.querySelectorAll('input[name="has_additional_observations"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const wrapper = document.getElementById('jointInspectionObservationsWrapper');
            if (this.value === '1') {
                wrapper.classList.remove('hidden');
            } else {
                wrapper.classList.add('hidden');
            }
        });
    });

    const printObservationButton = document.getElementById('jointInspectionPrintObservationBtn');
    if (printObservationButton) {
        printObservationButton.addEventListener('click', function() {
            const observationField = document.getElementById('jointInspectionObservations');
            const fileNumber = (document.getElementById('jointInspectionFileNumber')?.value || '').trim();
            const applicantName = (document.getElementById('jointInspectionApplicant')?.value || '').trim();
            const inspectionDate = (document.getElementById('jointInspectionDate')?.value || '').trim();
            const observationText = observationField ? (observationField.value || '').trim() : '';

            if (!observationText) {
                showErrorMessage('Please enter Additional Observation Details before printing.');
                return;
            }

            const printWindow = window.open('', '_blank', 'width=900,height=700');
            if (!printWindow) {
                showErrorMessage('Unable to open print window. Please allow popups and try again.');
                return;
            }

            const logoLeft = '/assets/logo/logo1.jpg';
            const logoRight = '/assets/logo/logo3.jpeg';
            const issuedDate = new Date().toLocaleDateString();

            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Additional Observation Details</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 24px; color: #1f2937; }
                        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #166534; padding-bottom: 10px; margin-bottom: 16px; }
                        .logo { width: 64px; height: 64px; object-fit: contain; }
                        .title { text-align: center; flex: 1; }
                        .title h1 { margin: 0; font-size: 14px; color: #166534; }
                        .title h2 { margin: 4px 0 0; font-size: 13px; color: #1f2937; }
                        .meta { margin-bottom: 14px; font-size: 12px; }
                        .meta p { margin: 4px 0; }
                        .content { border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; min-height: 180px; white-space: pre-wrap; line-height: 1.5; }
                        .label { font-weight: 700; margin-bottom: 6px; display: block; }
                        @media print { body { margin: 14px; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <img src="${logoLeft}" alt="Left Logo" class="logo" />
                        <div class="title">
                            <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                            <h2>Additional Observation Details</h2>
                        </div>
                        <img src="${logoRight}" alt="Right Logo" class="logo" />
                    </div>

                    <div class="meta">
                        <p><strong>File Number:</strong> ${fileNumber || 'N/A'}</p>
                        <p><strong>Applicant:</strong> ${applicantName || 'N/A'}</p>
                        <p><strong>Inspection Date:</strong> ${inspectionDate || 'N/A'}</p>
                        <p><strong>Printed On:</strong> ${issuedDate}</p>
                    </div>

                    <span class="label">Observation</span>
                    <div class="content">${observationText.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                </body>
                </html>
            `);

            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 400);
        });
    }

    // Handle boundary description generation
    function generateBoundaryDescription(segments) {
        const descriptions = [];

        boundaryDirections.forEach(direction => {
            if (segments[direction] && segments[direction].trim()) {
                descriptions.push(`On the ${direction}: ${segments[direction].trim()}`);
            }
        });
        
        if (descriptions.length === 0) {
            return 'Boundary description not provided.';
        }
        
        return descriptions.join('; ') + '.';
    }

    function normalizeMeasurementEntry(entry, index) {
        const description = (entry?.description || entry?.utility_type || '').toString().trim();

        const measurementDimensionValue = entry?.measurement_dimension !== undefined && entry?.measurement_dimension !== null
            ? String(entry.measurement_dimension)
            : (entry?.measurement_dimension_display !== undefined && entry?.measurement_dimension_display !== null
                ? String(entry.measurement_dimension_display)
                : (entry?.dimension_display ? String(entry.dimension_display) : ''));

        const dimensionValue = entry?.dimension !== undefined && entry?.dimension !== null
            ? String(entry.dimension)
            : (entry?.measurement ? String(entry.measurement) : '');
        const countValue = entry?.count !== undefined && entry?.count !== null
            ? String(entry.count)
            : (entry?.quantity ? String(entry.quantity) : '');

        return {
            sn: index + 1,
            description,
            count: countValue.trim() !== '' ? countValue.trim() : '1',
            measurement_dimension: measurementDimensionValue.trim(),
            dimension: dimensionValue.trim()
        };
    }

    function hasHydratedMeasurementEntries() {
        if (Array.isArray(window.pendingMeasurementEntries) && window.pendingMeasurementEntries.length > 0) {
            return true;
        }

        if (window.__jointInspectionUI && typeof window.__jointInspectionUI.getMeasurementEntries === 'function') {
            const existingEntries = window.__jointInspectionUI.getMeasurementEntries();
            if (Array.isArray(existingEntries)) {
                return existingEntries.some(entry => entry && (entry.description || entry.utility_type));
            }
        }

        return false;
    }

    function propagateMeasurementEntries(entries = []) {
        const sourceArray = Array.isArray(entries) ? entries : [];
        const normalized = sourceArray
            .map((entry, index) => normalizeMeasurementEntry(entry, index))
            .filter(entry => entry.description);

        const displayEntries = normalized.length > 0
            ? normalized
            : [{ sn: 1, description: '', count: '1', measurement_dimension: '', dimension: '' }];

        window.pendingMeasurementEntries = normalized;
        window.pendingMeasurementEntriesDisplay = displayEntries;

        if (window.__jointInspectionUI && typeof window.__jointInspectionUI.setMeasurementEntries === 'function') {
            window.__jointInspectionUI.setMeasurementEntries(displayEntries);
        }
    }

    function resetMeasurementEntriesUI() {
        propagateMeasurementEntries([]);
    }

    function collectMeasurementEntries() {
        try {
            if (window.__jointInspectionUI && typeof window.__jointInspectionUI.getMeasurementEntries === 'function') {
                const entries = window.__jointInspectionUI.getMeasurementEntries();
                if (Array.isArray(entries)) {
                    return entries
                        .map((entry, index) => normalizeMeasurementEntry(entry, index))
                        .filter(entry => entry.description);
                }
            }

            if (Array.isArray(window.pendingMeasurementEntries)) {
                return window.pendingMeasurementEntries
                    .map((entry, index) => normalizeMeasurementEntry(entry, index))
                    .filter(entry => entry.description);
            }

            const hiddenField = document.getElementById('existing_site_measurement_entries');
            if (hiddenField && hiddenField.value) {
                const parsed = JSON.parse(hiddenField.value);
                if (Array.isArray(parsed)) {
                    return parsed
                        .map((entry, index) => normalizeMeasurementEntry(entry, index))
                        .filter(entry => entry.description);
                }
            }
        } catch (error) {
            console.warn('Error collecting measurement entries:', error);
        }

        return [];
    }



    // Global functions to allow external triggers
    window.openJointInspectionModal = openJointInspectionModal;
    window.initializeJointInspectionEditor = function(applicationId, subApplicationId = null, options = {}) {
        const config = Object.assign({ showModal: false }, options || {});
        openJointInspectionModal(applicationId, subApplicationId, config);
    };

    // Handle "Enter Inspection Details" button clicks
    document.addEventListener('click', async function(e) {
        if (e.target.closest('.joint-inspection-trigger')) {
            e.preventDefault();
            const button = e.target.closest('.joint-inspection-trigger');
            if (!button) {
                return;
            }

            hideAllActionMenus();

            let applicationId = (button.dataset.applicationId || '').trim();
            const subApplicationId = (button.dataset.subApplicationId || '').trim();

            const normalizedSubApplicationId = subApplicationId || null;

            try {
                applicationId = await resolveApplicationId(applicationId, normalizedSubApplicationId);
            } catch (error) {
                console.error('Joint inspection trigger error:', error);
                showErrorMessage(error.message || 'Unable to open the joint inspection modal right now.');
                return;
            }

            if (!applicationId && !normalizedSubApplicationId) {
                showErrorMessage('Unable to open inspection modal because the application ID is missing.');
                return;
            }

            openJointInspectionModal(applicationId || '', normalizedSubApplicationId);
        }
    });
});
</script>


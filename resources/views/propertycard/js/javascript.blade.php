<script src="{{ asset('js/pra/helpers.js') }}"></script>
<script src="{{ asset('js/pra/state.js') }}"></script>
<script src="{{ asset('js/pra/modal.js') }}"></script>
<script src="{{ asset('js/pra/form-controller.js') }}?v={{ @filemtime(public_path('js/pra/form-controller.js')) }}"></script>
<script>
    const PraHelpers = window.PraHelpers || {};
    const isIndexAssistant = window.location.pathname.includes('/property-index-card');

    // **PIC/PRA ROUTING FIX**: Automatically set record_mode based on current page
    // This ensures PIC submissions go to 'pic' table and PRA submissions go to 'pra' table
    document.addEventListener('DOMContentLoaded', function () {
        const recordModeInput = document.querySelector('input[name="record_mode"]');
        if (recordModeInput) {
            // Set record_mode based on the page we're on
            const targetMode = isIndexAssistant ? 'index' : 'property';
            recordModeInput.value = targetMode;
            console.log(`[PIC/PRA Routing] Auto-set record_mode to '${targetMode}' (isIndexAssistant: ${isIndexAssistant})`);
        }
    });

    const normalizeText = typeof PraHelpers.normalizeText === 'function'
        ? PraHelpers.normalizeText
        : function (value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value).trim();
        };

    const buildStreetAndCityFallback = typeof PraHelpers.buildStreetAndCityFallback === 'function'
        ? PraHelpers.buildStreetAndCityFallback
        : function (property) {
            if (!property || typeof property !== 'object') {
                return '';
            }

            const parts = [];
            const houseNumber = normalizeText(property.house_no || property.houseNo);
            if (houseNumber) {
                parts.push(houseNumber);
            }

            const street = normalizeText(property.streetName || property.street_name);
            if (street) {
                parts.push(street);
            }

            const lgaCity = normalizeText(property.lgsaOrCity || property.lgsa_or_city);
            if (lgaCity) {
                parts.push(lgaCity);
            }

            return parts.join(', ');
        };

    const resolveDescriptionField = typeof PraHelpers.resolveDescriptionField === 'function'
        ? PraHelpers.resolveDescriptionField
        : function (property, derivedStreetAndCity) {
            if (!property || typeof property !== 'object') {
                return 'No description available';
            }

            const rawDescription = normalizeText(property.property_description || property.description);
            const hasMeaningfulDescription = rawDescription && rawDescription.toLowerCase() !== 'no description available';

            if (hasMeaningfulDescription) {
                return property.property_description || property.description;
            }

            if (isIndexAssistant && derivedStreetAndCity) {
                return derivedStreetAndCity;
            }

            return rawDescription || 'No description available';
        };

    const resolveLocationField = typeof PraHelpers.resolveLocationField === 'function'
        ? PraHelpers.resolveLocationField
        : function (property, derivedStreetAndCity) {
            if (!property || typeof property !== 'object') {
                return 'N/A';
            }

            const rawLocation = normalizeText(property.location);
            const hasMeaningfulLocation = rawLocation && rawLocation.toLowerCase() !== 'n/a';

            if (hasMeaningfulLocation) {
                return property.location;
            }

            if (isIndexAssistant && derivedStreetAndCity) {
                return derivedStreetAndCity;
            }

            const rawDescription = normalizeText(property.property_description || property.description);
            if (rawDescription && rawDescription.toLowerCase() !== 'no description available') {
                return property.property_description || property.description;
            }

            return rawLocation || 'N/A';
        };

    const normalizeFileNumberCandidate = typeof PraHelpers.normalizeFileNumberCandidate === 'function'
        ? PraHelpers.normalizeFileNumberCandidate
        : function (value) {
            if (value === null || value === undefined) {
                return '';
            }

            return String(value).trim();
        };

    const collectFileNumberCandidatesFromSource = typeof PraHelpers.collectFileNumberCandidatesFromSource === 'function'
        ? PraHelpers.collectFileNumberCandidatesFromSource
        : function (source) {
            const candidates = [];
            const pushCandidate = (value) => {
                const normalized = normalizeFileNumberCandidate(value);
                if (!normalized) {
                    return;
                }

                const lower = normalized.toLowerCase();
                if (lower === 'n/a' || lower === 'na') {
                    return;
                }

                if (!candidates.includes(normalized)) {
                    candidates.push(normalized);
                }
            };

            if (!source) {
                return candidates;
            }

            if (Array.isArray(source)) {
                source.forEach(pushCandidate);
                return candidates;
            }

            pushCandidate(source.mlsFNo || source.mlsfNo || source.mls_f_no);
            pushCandidate(source.mlsFileno || source.mls_fileno || source.mlsFileNumber);
            pushCandidate(source.kangisFileNo || source.kangisfileno || source.kangis_file_no);
            pushCandidate(source.kangisFileno || source.kangisFileNumber);
            pushCandidate(source.NewKANGISFileno || source.newKANGISFileno || source.newkangisfileno || source.newkangis_file_no);
            pushCandidate(source.newKangisFileno || source.newKangisFileNumber);
            pushCandidate(source.fileno || source.file_no || source.fileNumber || source.file_number);
            pushCandidate(source.primary_file_number || source.primaryFileNumber);
            pushCandidate(source.temp_fileno || source.tempFileNo || source.tempFileNumber);

            if (source.master) {
                pushCandidate(source.master.mlsFNo);
                pushCandidate(source.master.mlsFileno);
                pushCandidate(source.master.kangisFileNo);
                pushCandidate(source.master.kangisFileno);
                pushCandidate(source.master.NewKANGISFileno);
                pushCandidate(source.master.newKangisFileno);
                pushCandidate(source.master.primary_file_number);
                pushCandidate(source.master.primaryFileNumber);
                pushCandidate(source.master.temp_fileno);
            }

            if (source.record) {
                pushCandidate(source.record.file_number);
                pushCandidate(source.record.mlsFNo);
                pushCandidate(source.record.mlsFileno);
                pushCandidate(source.record.kangisFileNo);
                pushCandidate(source.record.kangisFileno);
                pushCandidate(source.record.NewKANGISFileno);
                pushCandidate(source.record.newKangisFileno);
                pushCandidate(source.record.primary_file_number);
                pushCandidate(source.record.primaryFileNumber);
                pushCandidate(source.record.temp_fileno);
            }

            return candidates;
        };

    const buildPrimarySelectionPayloadFromModal = typeof PraHelpers.buildPrimarySelectionPayloadFromModal === 'function'
        ? PraHelpers.buildPrimarySelectionPayloadFromModal
        : function (data) {
            if (!data || typeof data !== 'object') {
                return null;
            }

            const record = data.record && typeof data.record === 'object' ? data.record : {};
            const master = data.master && typeof data.master === 'object' ? data.master : {};

            const payload = {
                fileno: normalizeFileNumberCandidate(
                    data.fileNumber ||
                    data.fileno ||
                    data.file_number ||
                    data.primary_file_number ||
                    record.file_number ||
                    record.primary_file_number ||
                    master.primary_file_number ||
                    ''
                ),
                mlsFileno: normalizeFileNumberCandidate(
                    data.mlsFNo ||
                    data.mlsFileno ||
                    record.mlsFNo ||
                    record.mlsFileno ||
                    master.mlsFNo ||
                    master.mlsFileno ||
                    ''
                ),
                kangisFileno: normalizeFileNumberCandidate(
                    data.kangisFileNo ||
                    data.kangisFileno ||
                    record.kangisFileNo ||
                    record.kangisFileno ||
                    master.kangisFileNo ||
                    master.kangisFileno ||
                    ''
                ),
                newKangisFileno: normalizeFileNumberCandidate(
                    data.NewKANGISFileno ||
                    data.newKANGISFileno ||
                    data.newKangisFileno ||
                    record.NewKANGISFileno ||
                    record.newKANGISFileno ||
                    master.NewKANGISFileno ||
                    master.newKangisFileno ||
                    ''
                ),
                sourceId: data.id || data.fileId || data.file_id || record.id || master.id || null,
            };

            if (!payload.fileno) {
                payload.fileno = payload.mlsFileno || payload.kangisFileno || payload.newKangisFileno || '';
            }

            return payload;
        };

    const PraStore = window.PraStore || {
        cache: new Map(),
        state: window.praAutoFillState || {},
        set() { },
        get() { return undefined; },
        resetState() { },
    };

    const PRA_RECORD_BY_FILE_BASE_URL = @json(url('/api/pra/v1/records/by-file'));
    const praRecordCache = PraStore.cache;
    window.praAutoFillState = PraStore.state;

    async function fetchPraRecordByFileNumber(fileNumber) {
        const normalized = normalizeFileNumberCandidate(fileNumber);
        if (!normalized) {
            return { record: null, status: 'invalid' };
        }

        const cacheKey = normalized.toUpperCase();
        if (praRecordCache.has(cacheKey)) {
            return praRecordCache.get(cacheKey);
        }

        try {
            const response = await fetch(`${PRA_RECORD_BY_FILE_BASE_URL}/${encodeURIComponent(normalized)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (response.status === 404) {
                const result = { record: null, status: 404 };
                praRecordCache.set(cacheKey, result);
                return result;
            }

            if (response.status === 403) {
                console.warn('PRA API returned 403 response', normalized);
                const result = { record: null, status: 403 };
                praRecordCache.set(cacheKey, result);
                return result;
            }

            if (!response.ok) {
                console.error('Unexpected PRA API response', response.status);
                const result = { record: null, status: response.status };
                praRecordCache.set(cacheKey, result);
                return result;
            }

            const payload = await response.json();
            if (payload && payload.success && payload.data) {
                const result = { record: payload.data, status: response.status };
                praRecordCache.set(cacheKey, result);
                return result;
            }

            const result = { record: null, status: 'empty' };
            praRecordCache.set(cacheKey, result);
            return result;
        } catch (error) {
            console.error('Failed to fetch PRA record', normalized, error);
            return { record: null, status: 'error' };
        }
    }

    async function queuePraPrefillForFileNumber(fileNumberCandidates, source = 'unknown') {
        const state = PraStore.state;
        const candidates = collectFileNumberCandidatesFromSource(fileNumberCandidates);
        const lookupRequestId = Date.now() + Math.random();

        state.source = source;
        state.recordSource = null;
        state.lookupRequestId = lookupRequestId;
        state.pendingApplyAttempts = 0;
        state.lastStatus = null;

        if (!candidates.length) {
            state.lastFileNumber = null;
            state.record = null;
            state.appliedToken = null;
            state.isLoading = false;
            state.error = null;
            updatePraUiIndicators(null);
            return null;
        }

        state.lastFileNumber = candidates[0];
        state.record = null;
        state.appliedToken = null;
        state.isLoading = true;
        state.error = null;
        updatePraUiIndicators(null);

        for (const candidate of candidates) {
            const { record, status } = await fetchPraRecordByFileNumber(candidate);

            // Ignore stale async responses from older lookups.
            if (state.lookupRequestId !== lookupRequestId) {
                return null;
            }

            state.lastStatus = status;
            state.lastFileNumber = candidate;

            if (record) {
                state.isLoading = false;
                state.record = record;
                state.recordSource = source;
                state.appliedToken = null;
                state.error = null;
                updatePraUiIndicators(record);

                /*
                if (isPropertyFormOpen()) {
                    applyPraPrefillToForm(true);
                }
                */

                return record;
            }

            if (status === 403) {
                state.error = 'unknown';
                break;
            }
        }

        state.isLoading = false;
        if (!state.error) {
            if (state.lastStatus === 404) {
                state.error = 'not-found';
            } else if (state.lastStatus === 'error') {
                state.error = 'network';
            } else if (state.lastStatus === 'empty') {
                state.error = 'empty';
            } else if (state.lastStatus === 'invalid') {
                state.error = 'invalid';
            } else {
                state.error = 'unknown';
            }
        }

        updatePraUiIndicators(null);

        return null;
    }

    function updatePraUiIndicators(record) {
        const badge = document.getElementById('file-type-badge');
        if (!badge) {
            return;
        }

        if (!badge.dataset.defaultText) {
            badge.dataset.defaultText = badge.textContent || '';
        }

        if (!badge.dataset.defaultClass) {
            badge.dataset.defaultClass = badge.className;
        }

        const state = PraStore.state;
        const banner = document.getElementById('pra-status-banner');

        if (banner && !banner.dataset.baseClass) {
            banner.dataset.baseClass = banner.className;
        }

        const hideBanner = () => {
            if (!banner) {
                return;
            }

            banner.textContent = '';
            banner.removeAttribute('title');
            banner.className = banner.dataset.baseClass || '';
            if (!banner.classList.contains('hidden')) {
                banner.classList.add('hidden');
            }
        };

        const showBanner = (text, extraClass = '', title = '') => {
            if (!banner) {
                return;
            }

            const base = (banner.dataset.baseClass || '').replace(/\bhidden\b/g, '').trim();
            banner.className = `${base} ${extraClass}`.trim();
            banner.textContent = text;
            if (title) {
                banner.title = title;
            } else {
                banner.removeAttribute('title');
            }
            banner.classList.remove('hidden');
        };

        const applyBadgeStyle = (text, extraClass = '', title = '') => {
            badge.textContent = text;
            badge.title = title;
            badge.className = `${badge.dataset.defaultClass} ${extraClass}`.trim();
        };

        let handled = false;

        if (state.isLoading) {
            applyBadgeStyle(
                'Loading PRA data…',
                'bg-blue-100 text-blue-800 animate-pulse',
                state.lastFileNumber ? `Looking up ${state.lastFileNumber}` : 'Loading PRA data'
            );
            showBanner(
                state.lastFileNumber ? `Loading PRA details for ${state.lastFileNumber}…` : 'Loading PRA details…',
                'bg-blue-50 text-blue-800 border border-blue-200'
            );
            handled = true;
        } else if (record) {
            const descriptorCandidates = collectFileNumberCandidatesFromSource(record);
            const descriptor = descriptorCandidates.length ? descriptorCandidates[0] : (state.lastFileNumber || 'selected file');
            applyBadgeStyle(
                'PRA data applied',
                'bg-green-100 text-green-800',
                record.prop_id ? `Prop ID: ${record.prop_id}` : 'PRA data applied'
            );
            showBanner(
                `PRA data applied from ${descriptor}.`,
                'bg-green-50 text-green-800 border border-green-200',
                record.prop_id ? `Prop ID ${record.prop_id}` : ''
            );
            handled = true;
        } else if (state.error) {
            switch (state.error) {
                case 'not-found':
                    applyBadgeStyle(
                        'No PRA match',
                        'bg-red-100 text-red-800',
                        state.lastFileNumber ? `No PRA record found for ${state.lastFileNumber}` : 'No PRA record found'
                    );
                    showBanner(
                        state.lastFileNumber ? `No PRA record matched ${state.lastFileNumber}.` : 'No matching PRA record found.',
                        'bg-red-50 text-red-800 border border-red-200'
                    );
                    break;
                case 'network':
                    applyBadgeStyle(
                        'PRA lookup failed',
                        'bg-red-100 text-red-800',
                        'Unable to reach the PRA API'
                    );
                    showBanner(
                        'Unable to contact the PRA service. Please try again.',
                        'bg-red-50 text-red-800 border border-red-200'
                    );
                    break;
                case 'empty':
                    applyBadgeStyle(
                        'PRA data unavailable',
                        'bg-gray-200 text-gray-700',
                        'PRA API returned no data for this file number'
                    );
                    showBanner(
                        'PRA service returned no data for this file number.',
                        'bg-gray-100 text-gray-700 border border-gray-300'
                    );
                    break;
                case 'invalid':
                    applyBadgeStyle(
                        'Invalid file number',
                        'bg-red-100 text-red-800',
                        'Please confirm the file number formatting'
                    );
                    showBanner(
                        'Check the file number formatting and try again.',
                        'bg-red-50 text-red-800 border border-red-200'
                    );
                    break;
                case 'unknown':
                default:
                    applyBadgeStyle(
                        'PRA lookup unavailable',
                        'bg-gray-200 text-gray-700',
                        'PRA lookup did not complete'
                    );
                    showBanner(
                        'PRA lookup did not complete. You can retry or continue manually.',
                        'bg-gray-100 text-gray-700 border border-gray-300'
                    );
                    break;
            }
            handled = true;
        }

        if (!handled) {
            applyBadgeStyle(
                badge.dataset.defaultText,
                '',
                ''
            );
            hideBanner();
        }
    }

    function isPropertyFormOpen() {
        const dialog = document.getElementById('property-form-dialog');
        return dialog && !dialog.classList.contains('hidden');
    }

    function applyPraPrefillToForm(force = false) {
        const state = PraStore.state;

        if (!state.record) {
            return;
        }

        const token = (state.record.prop_id ? String(state.record.prop_id) : '') || (state.lastFileNumber ? state.lastFileNumber.toUpperCase() : '');
        if (!force && state.appliedToken === token) {
            return;
        }

        const component = getPropertyFormComponent();
        if (!component) {
            state.pendingApplyAttempts = (state.pendingApplyAttempts || 0) + 1;
            if (state.pendingApplyAttempts <= 10) {
                setTimeout(() => applyPraPrefillToForm(force), 150);
            }
            return;
        }

        state.pendingApplyAttempts = 0;

        populatePropertyFormFromPra(component, state.record, state.recordSource || state.source || 'unknown');
        state.appliedToken = token;

        const container = window.PraFormController ? window.PraFormController.container : null;
        if (container) {
            container.dispatchEvent(new CustomEvent('pra-record-applied', {
                bubbles: true,
                detail: {
                    record: state.record,
                    fileNumber: state.lastFileNumber,
                    source: state.source || 'unknown'
                }
            }));
        }
    }

    function getPropertyFormComponent() {
        if (window.PraFormController && typeof window.PraFormController.getPublicApi === 'function') {
            return window.PraFormController.getPublicApi();
        }

        const container = window.PraFormController ? window.PraFormController.container : null;
        if (container && container._x_dataStack && container._x_dataStack.length) {
            return container._x_dataStack[0];
        }

        return null;
    }

    function getPropertyFormRoot() {
        return document.getElementById('property-form-dialog');
    }

    function findFormElementById(id) {
        const root = getPropertyFormRoot();
        if (!root) {
            return null;
        }

        try {
            return root.querySelector(`#${CSS.escape(id)}`);
        } catch (error) {
            return null;
        }
    }

    function populatePropertyFormFromPra(component, record, prefillSource = 'unknown') {
        if (!component || !record) {
            return;
        }

        const transactionType = record.transaction_type || record.transactionType || '';
        if (transactionType) {
            component.transactionType = transactionType;
        }

        const assignParties = () => {
            const mappedParties = mapPraParties(transactionType, record.parties || {});
            if (mappedParties.first) {
                component.firstParty = mappedParties.first;
            }
            if (mappedParties.second) {
                component.secondParty = mappedParties.second;
            }
            if (mappedParties.third) {
                component.thirdParty = mappedParties.third;
            }
            if (mappedParties.fourth) {
                component.fourthParty = mappedParties.fourth;
            }
            if (mappedParties.fifth) {
                component.fifthParty = mappedParties.fifth;
            }
            
            // Auto-enable tripartite toggle if 4th or 5th party exists
            if ((mappedParties.fourth || mappedParties.fifth) && !component.tripartiteHasThird) {
                component.tripartiteHasThird = true;
            } else if (transactionType === 'Tripartite Mortgage' && mappedParties.third && !component.tripartiteHasThird) {
                // For Tripartite, P3 is usually always there
                // We don't necessarily need the toggle for P3 as it's always shown now in form-controller.js
            }
        };

        if (typeof component.$nextTick === 'function') {
            component.$nextTick(assignParties);
        } else {
            setTimeout(assignParties, 50);
        }

        const dateMap = [
            ['transactionDate', record.transaction_date || record.transactionDate],
            ['regDate', record.reg_date || record.regDate],
            ['deedsDate', record.deeds_date || record.deedsDate],
        ];

        dateMap.forEach(([key, value]) => {
            const formatted = formatPraDate(value);
            if (formatted) {
                component[key] = formatted;
            }
        });

        const timeMap = [
            ['regTime', record.reg_time || record.regTime],
            ['deedsTime', record.deeds_time || record.deedsTime],
        ];

        timeMap.forEach(([key, value]) => {
            const formatted = formatPraTime(value);
            if (formatted) {
                component[key] = formatted;
            }
        });

        if (record.serialNo) {
            component.serialNo = record.serialNo;
            component.pageNo = record.pageNo || record.serialNo;
        } else if (record.pageNo) {
            component.pageNo = record.pageNo;
        }

        if (record.volumeNo) {
            component.volumeNo = record.volumeNo;
        }

        const landUseValue = record.land_use || record.landUse;
        if (landUseValue) {
            component.landUse = landUseValue;
        }

        const descriptionValue = record.property_description || record.location;
        if (descriptionValue) {
            component.propertyDescription = descriptionValue;
        }

        setFieldValueIfPresent('property-description', component.propertyDescription);

        const master = record.master && typeof record.master === 'object' ? record.master : {};

        const resolveFirstAvailable = (...candidates) => {
            for (const candidate of candidates) {
                const resolved = normalizeText(candidate);
                if (resolved) {
                    return resolved;
                }
            }

            return '';
        };

        const houseNo = resolveFirstAvailable(
            record.house_no,
            record.houseNo,
            record.property_house_no,
            record.address_house_no,
            master.house_no,
            master.houseNo,
            master.property_house_no
        );
        if (houseNo) {
            component.houseNo = houseNo;
            setFieldValueIfPresent('houseNo', houseNo);
        }

        const plotNo = resolveFirstAvailable(
            record.plot_no,
            record.plotNo,
            record.plot_number,
            master.plot_no,
            master.plotNo,
            master.plot_number
        );
        if (plotNo) {
            component.plotNo = plotNo;
            setFieldValueIfPresent('plotNo', plotNo);
        }

        const tpNoValue = resolveFirstAvailable(
            record.tp_no,
            record.tpNo,
            master.tp_no,
            master.tpNo
        );
        if (tpNoValue) {
            component.tpNo = tpNoValue;
            setFieldValueIfPresent('tp_no_prop', tpNoValue);
        }

        const lpknNoValue = resolveFirstAvailable(
            record.lpkn_no,
            record.lpknNo,
            master.lpkn_no,
            master.lpknNo
        );
        if (lpknNoValue) {
            component.lpknNo = lpknNoValue;
            setFieldValueIfPresent('lpkn_no_prop', lpknNoValue);
        }

        const approvedPlanValue = resolveFirstAvailable(
            record.approved_plan_no,
            record.approvedPlanNo,
            master.approved_plan_no,
            master.approvedPlanNo
        );
        if (approvedPlanValue) {
            component.approvedPlanNo = approvedPlanValue;
            setFieldValueIfPresent('approved_plan_no_prop', approvedPlanValue);
        }

        const plotSizeValue = resolveFirstAvailable(
            record.plot_size,
            record.plotSize,
            master.plot_size,
            master.plotSize
        );
        if (plotSizeValue) {
            component.plotSize = plotSizeValue;
            setFieldValueIfPresent('plot_size_prop', plotSizeValue);
        }

        const dateRecommendedValue = resolveFirstAvailable(
            record.date_recommended,
            record.dateRecommended,
            master.date_recommended,
            master.dateRecommended
        );
        if (dateRecommendedValue) {
            const formatted = formatPraDate(dateRecommendedValue);
            if (formatted) {
                component.dateRecommended = formatted;
                setFieldValueIfPresent('date_recommended_prop', formatted);
            }
        }

        const dateApprovedValue = resolveFirstAvailable(
            record.date_approved,
            record.dateApproved,
            master.date_approved,
            master.dateApproved
        );
        if (dateApprovedValue) {
            const formatted = formatPraDate(dateApprovedValue);
            if (formatted) {
                component.dateApproved = formatted;
                setFieldValueIfPresent('date_approved_prop', formatted);
            }
        }

        const leaseBeginsValue = resolveFirstAvailable(
            record.lease_begins,
            record.leaseBegins,
            master.lease_begins,
            master.leaseBegins
        );
        if (leaseBeginsValue) {
            const formatted = formatPraDate(leaseBeginsValue);
            if (formatted) {
                component.leaseBegins = formatted;
                setFieldValueIfPresent('lease_begins_prop', formatted);
            }
        }

        const leaseExpiresValue = resolveFirstAvailable(
            record.lease_expires,
            record.leaseExpires,
            master.lease_expires,
            master.leaseExpires
        );
        if (leaseExpiresValue) {
            const formatted = formatPraDate(leaseExpiresValue);
            if (formatted) {
                component.leaseExpires = formatted;
                setFieldValueIfPresent('lease_expires_prop', formatted);
            }
        }

        const metricSheetValue = resolveFirstAvailable(
            record.metric_sheet,
            record.metricSheet,
            master.metric_sheet,
            master.metricSheet
        );
        if (metricSheetValue) {
            const metricSelect = findFormElementById('metric_sheet_prop');
            const metricSelection = ensureSelectOptionWithCustom(metricSelect, metricSheetValue, {
                useOtherOption: false,
            });
            if (metricSelection) {
                component.metricSheet = metricSelection.value;
            } else {
                component.metricSheet = metricSheetValue;
            }
        }

        const streetValue = resolveFirstAvailable(
            record.street_name,
            record.streetName,
            record.street,
            record.street_address,
            master.street_name,
            master.streetName,
            master.street
        );
        if (streetValue) {
            const streetSelect = findFormElementById('streetName');
            const streetSelection = ensureSelectOptionWithCustom(streetSelect, streetValue, {
                useOtherOption: true,
                otherOptionValue: 'other',
                otherInputId: 'otherStreetName',
            });
            component.streetName = streetSelection ? streetSelection.value : streetValue;
            component.currentStreetName = streetSelection ? streetSelection.display : streetValue;
        }

        const districtValue = resolveFirstAvailable(
            record.district,
            record.district_name,
            record.districtName,
            record.ward,
            record.zone,
            master.district,
            master.district_name,
            master.districtName
        );
        if (districtValue) {
            const districtSelect = findFormElementById('district');
            const districtSelection = ensureSelectOptionWithCustom(districtSelect, districtValue, {
                useOtherOption: true,
                otherOptionValue: 'other',
                otherInputId: 'otherDistrict',
            });
            component.district = districtSelection ? districtSelection.value : districtValue;
            component.currentDistrict = districtSelection ? districtSelection.display : districtValue;
        }

        const lgaValue = resolveFirstAvailable(
            record.lga,
            record.lga_name,
            record.lgaName,
            record.lgsaOrCity,
            record.lgsa_or_city,
            record.city,
            master.lga,
            master.lga_name,
            master.lgaName,
            master.lgsaOrCity,
            master.city
        );
        if (lgaValue) {
            const lgaSelect = findFormElementById('lga');
            const lgaSelection = ensureSelectOptionWithCustom(lgaSelect, lgaValue, {
                useOtherOption: false,
            });
            if (lgaSelection) {
                component.lga = lgaSelection.display;
            } else {
                component.lga = lgaValue;
            }
        }

        const stateValue = resolveFirstAvailable(
            record.state,
            record.state_name,
            record.stateName,
            master.state,
            master.state_name,
            master.stateName
        );
        if (stateValue) {
            setFieldValueIfPresent('state', stateValue);
        }

        const relatedFileNumber = resolveFirstAvailable(
            record.related_file_number,
            record.relatedFileNumber,
            master.related_file_number,
            master.relatedFileNumber
        );
        if (relatedFileNumber) {
            setFieldValueIfPresent('related_file_number', relatedFileNumber);
        }

        console.debug('PRA address payload applied', {
            houseNo,
            plotNo,
            streetValue,
            districtValue,
            lgaValue,
            stateValue,
            tpNoValue,
            lpknNoValue,
            approvedPlanValue,
            plotSizeValue,
            dateRecommendedValue,
            dateApprovedValue,
            leaseBeginsValue,
            leaseExpiresValue,
            metricSheetValue,
            relatedFileNumber,
        });

        const numbers = {
            mls: record.mlsFNo || (record.master && record.master.mlsFNo),
            kangis: record.kangisFileNo || (record.master && record.master.kangisFileNo),
            newKangis: record.NewKANGISFileno || (record.master && record.master.NewKANGISFileno),
            temp: record.temp_fileno || (record.master && record.master.temp_fileno),
        };

        const primaryNumber = numbers.mls || numbers.kangis || numbers.newKangis || numbers.temp || PraStore.state.lastFileNumber || '';

        // When this prefill was triggered by the Related File Number picker, only
        // property/address details should be pulled in. The primary file number the
        // user already selected must NOT be overwritten with the related file's number.
        const isRelatedLookup = (prefillSource === 'related-file-number');

        if (!isRelatedLookup) {
            if (primaryNumber) {
                component.fileNumber = primaryNumber;
            }

            setFieldValueIfPresent('mlsFNo', numbers.mls || '');
            setFieldValueIfPresent('kangisFileNo', numbers.kangis || '');
            setFieldValueIfPresent('NewKANGISFileno', numbers.newKangis || '');
            setFieldValueIfPresent('fileno', primaryNumber);

            const activeTab = numbers.mls ? 'mls' : numbers.kangis ? 'kangis' : numbers.newKangis ? 'newkangis' : '';
            setFieldValueIfPresent('activeFileTab', activeTab);

            const displayContainer = document.getElementById('selected-fileno-display');
            const displayText = document.getElementById('selected-fileno-text');
            if (displayText && primaryNumber) {
                displayText.textContent = primaryNumber;
                if (displayContainer) {
                    displayContainer.classList.remove('hidden');
                }
            } else if (displayContainer) {
                displayContainer.classList.add('hidden');
            }
        }

        component.hasOfficialFileNumber = true;
        component.showManualEntry = false;

        setFieldValueIfPresent('transactionType-record', component.transactionType);
        setFieldValueIfPresent('transactionDate', component.transactionDate);
        setFieldValueIfPresent('serialNo', component.serialNo);
        setFieldValueIfPresent('pageNo', component.pageNo);
        setFieldValueIfPresent('volumeNo', component.volumeNo);
        setFieldValueIfPresent('regDate', component.regDate);
        setFieldValueIfPresent('regTime', component.regTime);
        setFieldValueIfPresent('deeds_date', component.deedsDate);
        setFieldValueIfPresent('deeds_time', component.deedsTime);
        setFieldValueIfPresent('landUse', component.landUse);

        const manualToggle = findFormElementById('no-file-number-toggle');
        if (manualToggle) {
            manualToggle.checked = false;
        }

        if (typeof component.updatePropertyDescription === 'function') {
            const refreshDescription = () => component.updatePropertyDescription();
            if (typeof component.$nextTick === 'function') {
                component.$nextTick(refreshDescription);
            } else {
                setTimeout(refreshDescription, 50);
            }
        }

        console.info('PRA record data applied to Property Record form', {
            propId: record.prop_id || null,
            source: PraStore.state.source || 'unknown'
        });
    }

    function setFieldValueIfPresent(id, value) {
        const element = findFormElementById(id);
        if (!element) {
            return;
        }

        const finalValue = value === undefined || value === null ? '' : value;
        if (element.tagName === 'SELECT') {
            // Ensure the select has an option for this value (creates one if needed)
            const ensured = ensureSelectOptionWithCustom(element, finalValue, { useOtherOption: false });
            if (!ensured) {
                return;
            }
            // If the select already has the value selected, skip events
            if (element.value === ensured.value) {
                return;
            }
            element.value = ensured.value;
        } else {
            if (element.value === finalValue) {
                return;
            }
            element.value = finalValue;
        }

        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function findMatchingSelectOption(selectElement, candidate) {
        if (!selectElement) {
            return null;
        }

        const normalizedCandidate = normalizeText(candidate).toLowerCase();
        if (!normalizedCandidate) {
            return null;
        }

        const options = Array.from(selectElement.options || []);
        return options.find(option => {
            const optionValue = normalizeText(option.value).toLowerCase();
            const optionText = normalizeText(option.text).toLowerCase();
            return optionValue === normalizedCandidate || optionText === normalizedCandidate;
        }) || null;
    }

    function ensureGeneratedOption(selectElement, candidate) {
        const normalizedCandidate = normalizeText(candidate);
        if (!normalizedCandidate) {
            return null;
        }

        const existing = Array.from(selectElement.options || []).find(option => normalizeText(option.value).toLowerCase() === normalizedCandidate.toLowerCase());
        if (existing) {
            return existing;
        }

        const option = document.createElement('option');
        option.value = normalizedCandidate;
        option.text = normalizedCandidate;
        option.dataset.generated = 'true';
        selectElement.add(option);

        return option;
    }

    function ensureSelectOptionWithCustom(selectElement, candidate, options = {}) {
        if (!selectElement) {
            return null;
        }

        const normalizedCandidate = normalizeText(candidate);
        if (!normalizedCandidate) {
            return null;
        }

        const {
            useOtherOption = false,
            otherOptionValue = 'other',
            otherInputId = null,
        } = options;

        const match = findMatchingSelectOption(selectElement, normalizedCandidate);
        if (match) {
            if (selectElement.value !== match.value) {
                selectElement.value = match.value;
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
            }

            return {
                value: match.value,
                display: match.text || match.value,
                isCustom: false,
            };
        }

        if (useOtherOption) {
            if (selectElement.value !== otherOptionValue) {
                selectElement.value = otherOptionValue;
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (otherInputId) {
                const otherInput = findFormElementById(otherInputId);
                if (otherInput && otherInput.value !== normalizedCandidate) {
                    otherInput.value = normalizedCandidate;
                    otherInput.dispatchEvent(new Event('input', { bubbles: true }));
                    otherInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            return {
                value: normalizedCandidate,
                display: normalizedCandidate,
                isCustom: true,
            };
        }

        const option = ensureGeneratedOption(selectElement, normalizedCandidate);
        if (!option) {
            return null;
        }

        if (selectElement.value !== option.value) {
            selectElement.value = option.value;
            selectElement.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return {
            value: option.value,
            display: option.text || option.value,
            isCustom: true,
        };
    }

    function formatPraDate(value) {
        if (!value) {
            return '';
        }

        if (value instanceof Date && !isNaN(value.getTime())) {
            return value.toISOString().slice(0, 10);
        }

        if (typeof value === 'string') {
            const match = value.match(/^(\d{4}-\d{2}-\d{2})/);
            if (match) {
                return match[1];
            }

            const parsed = new Date(value);
            if (!isNaN(parsed.getTime())) {
                return parsed.toISOString().slice(0, 10);
            }
        }

        return '';
    }

    function formatPraTime(value) {
        if (!value) {
            return '';
        }

        if (value instanceof Date && !isNaN(value.getTime())) {
            return value.toISOString().slice(11, 16);
        }

        if (typeof value === 'string') {
            const match = value.match(/^(\d{2}:\d{2})/);
            if (match) {
                return match[1];
            }
        }

        return '';
    }

    function mapPraParties(transactionType, parties) {
        const normalizedType = (transactionType || '').toLowerCase();
        const data = parties || {};
        const result = { first: '', second: '', third: '', fourth: '', fifth: '' };

        switch (normalizedType) {
            case 'assignment':
            case 'deed of assignment':
            case 'st assignment':
                result.first = data.assignor || data.grantor || data.party_1 || '';
                result.second = data.assignee || data.grantee || data.party_2 || '';
                break;
            case 'mortgage':
            case 'deed of mortgage':
                result.first = data.mortgagor || data.grantor || data.party_1 || '';
                result.second = data.mortgagee || data.grantee || data.party_2 || '';
                result.third = data.Mortgagor_2 || data.party_3 || '';
                result.fourth = data.Mortgagor_3 || data.party_4 || '';
                break;
            case 'tripartite mortgage':
                result.first = data.mortgagor || data.grantor || data.party_1 || '';
                result.second = data.mortgagee || data.grantee || data.party_2 || '';
                result.third = data.Mortgagor_2 || data.party_3 || '';
                result.fourth = data.Mortgagor_3 || data.party_4 || '';
                break;
            case 'surrender':
            case 'deed of surrender':
            case 'deed of surrender and release':
                result.first = data.surrenderor || data.grantor || data.party_1 || '';
                result.second = data.surrenderee || data.grantee || data.party_2 || '';
                result.third = data.Surrenderor_2 || data.party_3 || '';
                break;
            case 'sub-lease':
            case 'deed of sub lease':
            case 'deed of sub under lease':
            case 'lease':
            case 'indenture of lease':
            case 'tenancy agreement':
                result.first = data.lessor || data.grantor || data.party_1 || '';
                result.second = data.lessee || data.grantee || data.party_2 || '';
                break;
            default:
                result.first = data.grantor || data.assignor || data.mortgagor || data.party_1 || '';
                result.second = data.grantee || data.assignee || data.mortgagee || data.party_2 || '';
                result.third = data.party_3 || '';
                result.fourth = data.party_4 || '';
                result.fifth = data.party_5 || '';
                break;
        }

        return result;
    }

    window.collectFileNumberCandidatesFromSource = collectFileNumberCandidatesFromSource;
    window.queuePraPrefillForFileNumber = queuePraPrefillForFileNumber;
    window.applyPraPrefillToForm = applyPraPrefillToForm;

    // Initialize the file number component for property form
    function initFileNumberComponent(prefix) {
        console.log("Initializing file number component with prefix:", prefix);

        try {
            // Ensure the DOM elements are actually available
            const activeFileTab = document.getElementById(prefix + 'activeFileTab');
            if (!activeFileTab) {
                console.error("Active file tab element not found for prefix:", prefix);
                return;
            }

            // Initialize file number previews
            updateMlsFileNumberPreview(prefix);
            updateKangisFileNumberPreview(prefix);
            updateNewKangisFileNumberPreview(prefix);

            // Add event listeners for file number preview updates
            const mlsPrefix = document.getElementById(prefix + 'mlsFileNoPrefix');
            const mlsNumber = document.getElementById(prefix + 'mlsFileNumber');
            const kangisPrefix = document.getElementById(prefix + 'kangisFileNoPrefix');
            const kangisNumber = document.getElementById(prefix + 'kangisFileNumber');
            const newKangisPrefix = document.getElementById(prefix + 'newKangisFileNoPrefix');
            const newKangisNumber = document.getElementById(prefix + 'newKangisFileNumber');

            if (mlsPrefix) mlsPrefix.addEventListener('change', function () { updateMlsFileNumberPreview(prefix); });
            if (mlsNumber) mlsNumber.addEventListener('input', function () { updateMlsFileNumberPreview(prefix); });
            if (kangisPrefix) kangisPrefix.addEventListener('change', function () { updateKangisFileNumberPreview(prefix); });
            if (kangisNumber) kangisNumber.addEventListener('input', function () { updateKangisFileNumberPreview(prefix); });
            if (newKangisPrefix) newKangisPrefix.addEventListener('change', function () { updateNewKangisFileNumberPreview(prefix); });
            if (newKangisNumber) newKangisNumber.addEventListener('input', function () { updateNewKangisFileNumberPreview(prefix); });

            // Force show the first tab (MLS) by default
            const firstTab = document.getElementById(prefix + 'mlsFNoTab');
            const firstTabButton = document.querySelector('.' + prefix + 'tablinks');

            if (firstTab && firstTabButton) {
                console.log("Forcing first tab to show:", prefix + 'mlsFNoTab');

                // Hide all tabs first
                const allTabs = document.querySelectorAll("[id^='" + prefix + "'][id$='Tab'][class*='tabcontent']");
                allTabs.forEach(tab => {
                    tab.classList.remove('active');
                    tab.style.display = 'none';
                });

                // Remove active class from all buttons
                const allButtons = document.getElementsByClassName(prefix + "tablinks");
                for (let i = 0; i < allButtons.length; i++) {
                    allButtons[i].classList.remove('active');
                }

                // Show the first tab
                firstTab.classList.add('active');
                firstTab.style.display = 'block';
                firstTab.style.visibility = 'visible';
                firstTabButton.classList.add('active');

                // Set the active tab value
                document.getElementById(prefix + 'activeFileTab').value = "mlsFNo";

                console.log("First tab should now be visible. Display:", getComputedStyle(firstTab).display);
            }
        } catch (error) {
            console.error("Error initializing file number component:", error);
        }
    }

    // Format MLS file number preview with prefix
    function updateMlsFileNumberPreview(prefix) {
        const prefixEl = document.getElementById(prefix + 'mlsFileNoPrefix');
        const numberEl = document.getElementById(prefix + 'mlsFileNumber');
        const previewEl = document.getElementById(prefix + 'mlsPreviewFileNumber');
        const dbFieldEl = document.getElementById(prefix + 'mlsFNo');

        if (!prefixEl || !numberEl || !previewEl || !dbFieldEl) return;

        const selectedPrefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (selectedPrefix && number) {
            const formatted = selectedPrefix + '-' + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted; // Set the database field
        } else if (selectedPrefix) {
            previewEl.value = selectedPrefix;
            dbFieldEl.value = selectedPrefix;
        } else if (number) {
            previewEl.value = number;
            dbFieldEl.value = number;
        } else {
            previewEl.value = '';
            dbFieldEl.value = '';
        }
    }

    // Format KANGIS file number preview with prefix
    function updateKangisFileNumberPreview(prefix) {
        const prefixEl = document.getElementById(prefix + 'kangisFileNoPrefix');
        const numberEl = document.getElementById(prefix + 'kangisFileNumber');
        const previewEl = document.getElementById(prefix + 'kangisPreviewFileNumber');
        const dbFieldEl = document.getElementById(prefix + 'kangisFileNo');

        if (!prefixEl || !numberEl || !previewEl || !dbFieldEl) return;

        const selectedPrefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (selectedPrefix && number) {
            // Pad to 5 digits
            number = number.padStart(5, '0');
            numberEl.value = number;
            const formatted = selectedPrefix + ' ' + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted; // Set the database field
        } else if (selectedPrefix) {
            previewEl.value = selectedPrefix;
            dbFieldEl.value = selectedPrefix;
        } else if (number) {
            previewEl.value = number;
            dbFieldEl.value = number;
        } else {
            previewEl.value = '';
            dbFieldEl.value = '';
        }
    }

    // Format New KANGIS file number preview with prefix
    function updateNewKangisFileNumberPreview(prefix) {
        const prefixEl = document.getElementById(prefix + 'newKangisFileNoPrefix');
        const numberEl = document.getElementById(prefix + 'newKangisFileNumber');
        const previewEl = document.getElementById(prefix + 'newKangisPreviewFileNumber');
        const dbFieldEl = document.getElementById(prefix + 'NewKANGISFileno');

        if (!prefixEl || !numberEl || !previewEl || !dbFieldEl) return;

        const selectedPrefix = prefixEl.value;
        let number = numberEl.value.trim();

        if (selectedPrefix && number) {
            const formatted = selectedPrefix + number;
            previewEl.value = formatted;
            dbFieldEl.value = formatted; // Set the database field
        } else if (selectedPrefix) {
            previewEl.value = selectedPrefix;
            dbFieldEl.value = selectedPrefix;
        } else if (number) {
            previewEl.value = number;
            dbFieldEl.value = number;
        } else {
            previewEl.value = '';
            dbFieldEl.value = '';
        }
    }

    // Updates the form data for submission based on prefix
    function updateFormFileData(prefix) {
        // Ensure all file numbers are properly set in hidden fields
        updateMlsFileNumberPreview(prefix);
        updateKangisFileNumberPreview(prefix);
        updateNewKangisFileNumberPreview(prefix);

        // Get the active tab
        const activeTab = document.getElementById(prefix + 'activeFileTab').value;

        // Set the active file number based on the active tab
        if (activeTab === "mlsFNo") {
            document.getElementById(prefix + 'mlsFNo').value = document.getElementById(prefix + 'mlsPreviewFileNumber').value;
        } else if (activeTab === "kangisFileNo") {
            document.getElementById(prefix + 'kangisFileNo').value = document.getElementById(prefix + 'kangisPreviewFileNumber').value;
        } else if (activeTab === "NewKANGISFileno") {
            document.getElementById(prefix + 'NewKANGISFileno').value = document.getElementById(prefix + 'newKangisPreviewFileNumber').value;
        }

        return true;
    }

    // Updated function to maintain values across tabs and support multiple instances
    function openFileTab(prefix, evt, tabName) {
        console.log("Opening tab:", tabName, "for prefix:", prefix);

        try {
            // Save current values before switching tabs
            const activeFileTab = document.getElementById(prefix + 'activeFileTab');
            if (activeFileTab) {
                if (activeFileTab.value === "mlsFNo") {
                    updateMlsFileNumberPreview(prefix);
                } else if (activeFileTab.value === "kangisFileNo") {
                    updateKangisFileNumberPreview(prefix);
                } else if (activeFileTab.value === "NewKANGISFileno") {
                    updateNewKangisFileNumberPreview(prefix);
                }
            }

            // Hide all tab content for this form instance
            var tabcontent = document.querySelectorAll("[id^='" + prefix + "'][id$='Tab'][class*='tabcontent']");
            console.log("Found", tabcontent.length, "tab content elements for prefix", prefix);

            for (var i = 0; i < tabcontent.length; i++) {
                var tab = tabcontent[i];
                console.log("Hiding tab:", tab.id);
                tab.classList.remove("active");
                tab.style.display = "none";
                tab.style.visibility = "hidden";
            }

            // Remove active class from all tab buttons for this form instance
            var tablinks = document.getElementsByClassName(prefix + "tablinks");
            for (var i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }

            // Show the current tab and add active class to the button
            var currentTab = document.getElementById(tabName);
            if (currentTab) {
                console.log("Showing tab:", tabName);
                currentTab.classList.add("active");
                currentTab.style.display = "block";
                currentTab.style.visibility = "visible";
                console.log("Tab display after change:", getComputedStyle(currentTab).display);
            } else {
                console.error("Tab not found:", tabName);
            }

            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add("active");
            }

            // Set the active tab value based on the database field names
            if (tabName === prefix + "mlsFNoTab") {
                document.getElementById(prefix + 'activeFileTab').value = "mlsFNo";
            } else if (tabName === prefix + "kangisFileNoTab") {
                document.getElementById(prefix + 'activeFileTab').value = "kangisFileNo";
            } else if (tabName === prefix + "NewKANGISFilenoTab") {
                document.getElementById(prefix + 'activeFileTab').value = "NewKANGISFileno";
            }
        } catch (error) {
            console.error("Error in openFileTab:", error);
        }
    }

    let globalFileModalInitialized = false;
    function initializeGlobalFileModalIfNeeded() {
        if (globalFileModalInitialized) return;
        if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.init === 'function') {
            try {
                GlobalFileNoModal.init();
                globalFileModalInitialized = true;
            } catch (error) {
                console.error('Failed to initialize GlobalFileNoModal:', error);
            }
        }
    }

    window.initializeGlobalFileModalIfNeeded = initializeGlobalFileModalIfNeeded;

    window.openRelatedFileNumberModal = function (inputId) {
        initializeGlobalFileModalIfNeeded();

        if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
            alert('Global file number selector is not available yet. Please refresh the page.');
            return;
        }

        const input = document.getElementById(inputId);
        const currentValue = input ? input.value : '';

        GlobalFileNoModal.open({
            initialValue: currentValue || '',
            callback: function (fileData) {
                if (!fileData) return;
                const selectedNumber =
                    fileData.fileNumber ||
                    fileData.fileno ||
                    fileData.mlsFNo ||
                    fileData.kangisFileNo ||
                    fileData.NewKANGISFileno ||
                    (fileData.record && (fileData.record.file_number || fileData.record.mlsFNo || fileData.record.kangisFileNo)) ||
                    '';

                if (input && selectedNumber) {
                    input.value = selectedNumber;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (typeof window.queuePraPrefillForFileNumber === 'function') {
                    const candidates = collectFileNumberCandidatesFromSource(fileData);
                    const payload = candidates.length ? candidates : (selectedNumber ? [selectedNumber] : []);
                    // Use a dedicated source so the prefill pulls property/address details
                    // WITHOUT overwriting the primary file number the user already picked.
                    window.queuePraPrefillForFileNumber(payload, 'related-file-number');
                }

                if (typeof GlobalFileNoModal.close === 'function') {
                    GlobalFileNoModal.close();
                }
            }
        });
    };

    window.openPrimaryFileNumberModal = function () {
        initializeGlobalFileModalIfNeeded();

        if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
            alert('Global file number selector is not available yet. Please refresh the page.');
            return;
        }

        const filenoInput = document.getElementById('fileno');
        const currentValue = filenoInput ? filenoInput.value : '';

        GlobalFileNoModal.open({
            initialValue: currentValue || '',
            callback: function (fileData) {
                if (!fileData) {
                    return;
                }

                const payload = buildPrimarySelectionPayloadFromModal(fileData);
                if (payload && typeof window.applyPrimaryFileSelection === 'function') {
                    window.applyPrimaryFileSelection(payload, 'global-fileno-modal');
                }

                if (typeof GlobalFileNoModal.close === 'function') {
                    GlobalFileNoModal.close();
                }
            }
        });
    };

    // DOMContentLoaded event handler
    document.addEventListener('DOMContentLoaded', function () {
        console.log('DOM fully loaded');
        initializeGlobalFileModalIfNeeded();

        // Property Record Form transaction fields handling
        initializeTransactionForm(
            'property-record-form',
            'transactionType-record',
            'transaction-specific-fields-record',
            'assignment-fields-record',
            'mortgage-fields-record',
            'surrender-fields-record',
            'lease-fields-record',
            'default-fields-record'
        );

        // Shared function to handle transaction form fields
        function initializeTransactionForm(
            formId,
            transactionTypeId,
            specificFieldsId,
            assignmentFieldsId,
            mortgageFieldsId,
            surrenderFieldsId,
            leaseFieldsId,
            defaultFieldsId
        ) {
            const form = document.getElementById(formId);
            if (!form) return;

            const transactionTypeSelect = document.getElementById(transactionTypeId);
            const transactionSpecificFields = document.getElementById(specificFieldsId);

            // Get references to all transaction field containers for this form
            const assignmentFields = document.getElementById(assignmentFieldsId);
            const mortgageFields = document.getElementById(mortgageFieldsId);
            const surrenderFields = document.getElementById(surrenderFieldsId);
            const leaseFields = document.getElementById(leaseFieldsId);
            const defaultFields = document.getElementById(defaultFieldsId);

            // Initially hide the transaction details section and all field sets
            if (transactionSpecificFields) transactionSpecificFields.classList.add('hidden');

            // Hide all transaction field sets
            if (assignmentFields) assignmentFields.classList.add('hidden');
            if (mortgageFields) mortgageFields.classList.add('hidden');
            if (surrenderFields) surrenderFields.classList.add('hidden');
            if (leaseFields) leaseFields.classList.add('hidden');
            if (defaultFields) defaultFields.classList.add('hidden');

            // Handle transaction type selection for this form
            if (transactionTypeSelect) {
                transactionTypeSelect.addEventListener('change', function () {
                    const selectedType = this.value;

                    // Hide all field sets first
                    if (assignmentFields) assignmentFields.classList.add('hidden');
                    if (mortgageFields) mortgageFields.classList.add('hidden');
                    if (surrenderFields) surrenderFields.classList.add('hidden');
                    if (leaseFields) leaseFields.classList.add('hidden');
                    if (defaultFields) defaultFields.classList.add('hidden');

                    // Handle "Other" transaction type
                    const otherTransactionDiv = document.getElementById('other-transaction-type');
                    const otherTransactionInput = document.getElementById('otherTransactionType');

                    if (selectedType === 'Other') {
                        if (otherTransactionDiv) {
                            otherTransactionDiv.classList.remove('hidden');
                            if (otherTransactionInput) {
                                otherTransactionInput.required = true;
                            }
                        }
                    } else {
                        if (otherTransactionDiv) {
                            otherTransactionDiv.classList.add('hidden');
                            if (otherTransactionInput) {
                                otherTransactionInput.required = false;
                                otherTransactionInput.value = '';
                            }
                        }
                    }

                    // Auto-fill Grantor for government transactions
                    const grantorField = document.getElementById('grantor-record');
                    if (selectedType === 'Certificate of Occupancy' || selectedType === 'ST Certificate of Occupancy' || selectedType === 'SLTR Certificate of Occupancy' || selectedType === 'Customary Right of Occupancy') {
                        if (grantorField) {
                            grantorField.value = 'KANO STATE GOVERNMENT';
                            grantorField.readOnly = true;
                            grantorField.classList.add('bg-gray-100');
                        }
                    } else {
                        if (grantorField) {
                            grantorField.value = '';
                            grantorField.readOnly = false;
                            grantorField.classList.remove('bg-gray-100');
                        }
                    }

                    // If no transaction type selected, hide the entire section
                    if (!selectedType) {
                        if (transactionSpecificFields) transactionSpecificFields.classList.add('hidden');
                        return;
                    }

                    // Show the transaction details section
                    if (transactionSpecificFields) transactionSpecificFields.classList.remove('hidden');

                    // Show the appropriate field set based on transaction type
                    if (selectedType === 'Assignment' && assignmentFields) {
                        assignmentFields.classList.remove('hidden');
                    }
                    else if (selectedType === 'Mortgage' && mortgageFields) {
                        mortgageFields.classList.remove('hidden');
                    }
                    else if (selectedType === 'Surrender' && surrenderFields) {
                        surrenderFields.classList.remove('hidden');
                    }
                    else if (['Sub-Lease', 'lease', 'sub-under-lease'].includes(selectedType) && leaseFields) {
                        leaseFields.classList.remove('hidden');
                    }
                    else if (selectedType === 'Other') {
                        // For "Other" transaction type, don't show any specific fields initially
                        // Default fields will be shown/hidden based on custom type input
                    }
                    else if (defaultFields) {
                        // For Certificate of Occupancy, Right Of Occupancy, and other transaction types, show the default fields
                        defaultFields.classList.remove('hidden');
                    }
                });
            }

            // Add event listener for "Other" transaction type input
            const otherTransactionInput = document.getElementById('otherTransactionType');
            if (otherTransactionInput) {
                otherTransactionInput.addEventListener('input', function () {
                    const customType = this.value.trim();
                    const defaultFields = document.getElementById(defaultFieldsId);

                    if (customType && defaultFields) {
                        // Hide Grantor and Grantee fields when custom type is specified
                        defaultFields.classList.add('hidden');
                    } else if (defaultFields) {
                        // Show Grantor and Grantee fields when custom type is cleared
                        defaultFields.classList.remove('hidden');
                    }
                });
            }
        }

        // Property form dialog setup
        setupPropertyFormDialog();

        // Setup the property form dialog
        function setupPropertyFormDialog() {
            const propertyButton = document.getElementById('add-property-btn');
            const indexCardButton = document.getElementById('index-card-btn');
            const dialog = document.getElementById('property-form-dialog');
            const closeButton = document.getElementById('close-property-form');
            const cancelButton = document.getElementById('cancel-property-form');

            if (!dialog) {
                console.error("Property form dialog setup failed - Dialog not found");
                return;
            }

            function showDialog(mode = 'property') {
                dialog.classList.remove('hidden');

                // Set the form mode in Alpine.js with a more robust approach
                setTimeout(() => {
                    const component = getPropertyFormComponent();
                    if (component) {
                        component.formMode = mode;
                        if (typeof component.$nextTick === 'function') {
                            component.$nextTick(() => { });
                        }
                    }

                    const controllerContainer = window.PraFormController ? window.PraFormController.container : null;
                    if (controllerContainer) {
                        controllerContainer.dispatchEvent(new CustomEvent('form-mode-changed', {
                            detail: { mode }
                        }));
                    }

                    if (window.PraFormController && typeof window.PraFormController.resetUpdateMode === 'function') {
                        window.PraFormController.resetUpdateMode();
                    }

                    // Direct title update as fallback
                    const titleElement = dialog.querySelector('#form-title');
                    if (titleElement) {
                        if (titleElement.dataset.customTitle) {
                            titleElement.textContent = titleElement.dataset.customTitle;
                        } else {
                            titleElement.textContent = mode === 'index' ? 'Index Card' : 'Add New Property Record';
                        }
                    }
                }, 100);

                // Initialize manual file number component when dialog opens
                setTimeout(() => {
                    console.log('Initializing manual file number component for property form...');

                    // Setup form validation when dialog opens
                    setupFormValidation('property-record-form', 'property-submit-btn');

                    if (typeof window.applyPraPrefillToForm === 'function') {
                        window.applyPraPrefillToForm(true);
                    }
                }, 300);
            }

            function hideDialog() {
                dialog.classList.add('hidden');
            }

            // Add event listeners for Property Record mode
            if (propertyButton) {
                propertyButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    showDialog('property');
                });
            }

            // Add event listeners for Index Card mode
            if (indexCardButton) {
                indexCardButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    showDialog('index');
                });
            }

            if (closeButton) closeButton.addEventListener('click', hideDialog);
            if (cancelButton) cancelButton.addEventListener('click', hideDialog);

            // Bind add property card to the same action as the add property button
            const addPropertyCard = document.getElementById('add-property-card');
            if (addPropertyCard) {
                addPropertyCard.addEventListener('click', function () {
                    showDialog('property');
                });
            }
        }

        function setupPropertyActions() {
            // View property details
            document.querySelectorAll('.view-property, .view-property-details').forEach(button => {
                button.addEventListener('click', function () {
                    const propertyId = this.getAttribute('data-id');
                    viewPropertyDetails(propertyId);
                });
            });

            // Edit property
            document.querySelectorAll('.edit-property').forEach(button => {
                button.addEventListener('click', function () {
                    const propertyId = this.getAttribute('data-id');
                    editProperty(propertyId);
                });
            });

            // Delete property
            document.querySelectorAll('.delete-property').forEach(button => {
                button.addEventListener('click', function () {
                    const propertyId = this.getAttribute('data-id');
                    deleteProperty(propertyId);
                });
            });

            // Property options menu
            document.querySelectorAll('.property-options').forEach(button => {
                button.addEventListener('click', function () {
                    const propertyId = this.getAttribute('data-id');
                    showPropertyOptions(propertyId, this);
                });
            });

            // Add click handlers to table rows to show details in Dynamic Property Cards
            setupTableRowClickHandlers();
        }

        // Expose action binder globally so other modules (like DataTables) can reattach events after redraw
        window.setupPropertyActions = setupPropertyActions;

        // Setup property action buttons
        setupPropertyActions();

        function setupTableRowClickHandlers() {
            // Get all table rows with property data
            document.querySelectorAll('.table tbody tr').forEach(row => {
                // Skip empty rows or rows without property data
                const cells = row.querySelectorAll('td');
                if (cells.length <= 1) return;

                // Find the view button to get the property ID
                const viewButton = row.querySelector('.view-property');
                if (!viewButton) return;

                const propertyId = viewButton.getAttribute('data-id');

                // Add click handler to the entire row (excluding action buttons)
                row.style.cursor = 'pointer';
                row.addEventListener('click', function (e) {
                    // Don't trigger if clicking on action buttons
                    if (e.target.closest('.view-property, .edit-property, .delete-property')) {
                        return;
                    }

                    // Highlight the selected row
                    document.querySelectorAll('.table tbody tr').forEach(r => {
                        r.classList.remove('bg-blue-50', 'selected-row');
                    });
                    this.classList.add('bg-blue-50', 'selected-row');

                    // Load and display property details in Dynamic Property Cards
                    loadPropertyDetailsInCards(propertyId);
                });
            });
        }

        function loadPropertyDetailsInCards(propertyId) {
            // Show loading state in the cards section
            showLoadingInCards();

            // Fetch property data
            fetch(`{{ url('/property-records') }}/${propertyId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch property data');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.data) {
                        displayPropertyDetailsInCards(data.data);
                    } else {
                        throw new Error(data.message || 'Failed to load property data');
                    }
                })
                .catch(error => {
                    console.error('Error loading property details:', error);
                    showErrorInCards(error.message);
                });
        }
        // Make available globally for Blade inline script
        window.loadPropertyDetailsInCards = loadPropertyDetailsInCards;

        function showLoadingInCards() {
            const cardsContainer = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-4.mb-6');
            if (!cardsContainer) return;

            // Hide existing property cards but keep the "Add New" card and the server-side rendered selected card
            const propertyCards = cardsContainer.querySelectorAll('.border:not(#add-property-card):not(#selected-property-detail-card)');
            propertyCards.forEach(card => card.style.display = 'none');

            // Add loading card if it doesn't exist
            let loadingCard = document.getElementById('loading-property-card');
            if (!loadingCard) {
                loadingCard = document.createElement('div');
                loadingCard.id = 'loading-property-card';
                loadingCard.className = 'border rounded-lg shadow-sm p-6 text-center col-span-2';
                loadingCard.innerHTML = `
                    <div class="flex flex-col items-center justify-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-3"></div>
                        <p class="text-gray-500">Loading property details...</p>
                    </div>
                `;
                cardsContainer.appendChild(loadingCard);
            } else {
                loadingCard.style.display = 'block';
            }
        }

        function showErrorInCards(errorMessage) {
            const cardsContainer = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-4.mb-6');
            if (!cardsContainer) return;

            // Remove loading card
            const loadingCard = document.getElementById('loading-property-card');
            if (loadingCard) loadingCard.remove();

            // Add error card
            let errorCard = document.getElementById('error-property-card');
            if (!errorCard) {
                errorCard = document.createElement('div');
                errorCard.id = 'error-property-card';
                errorCard.className = 'border rounded-lg shadow-sm p-6 text-center col-span-2 border-red-200 bg-red-50';
                cardsContainer.appendChild(errorCard);
            }

            errorCard.innerHTML = `
                <div class="flex flex-col items-center justify-center">
                    <div class="h-8 w-8 text-red-500 mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-red-600 font-medium">Error loading property details</p>
                    <p class="text-red-500 text-sm mt-1">${errorMessage}</p>
                </div>
            `;
            errorCard.style.display = 'block';
        }

        function displayPropertyDetailsInCards(property) {
            const cardsContainer = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-4.mb-6');
            if (!cardsContainer) return;

            // Remove loading and error cards
            const loadingCard = document.getElementById('loading-property-card');
            const errorCard = document.getElementById('error-property-card');
            if (loadingCard) loadingCard.remove();
            if (errorCard) errorCard.remove();

            // Hide existing property cards but keep the "Add New" card and preserve server-side rendered card
            const propertyCards = cardsContainer.querySelectorAll('.border:not(#add-property-card):not(#selected-property-detail-card)');
            propertyCards.forEach(card => card.style.display = 'none');

            // Create or update the selected property detail card
            let detailCard = document.getElementById('selected-property-detail-card');
            if (!detailCard) {
                detailCard = document.createElement('div');
                detailCard.id = 'selected-property-detail-card';
                detailCard.className = 'border rounded-lg shadow-lg overflow-hidden col-span-2 bg-blue-50 border-blue-200';
                cardsContainer.appendChild(detailCard);
            }

            // Get file number to display (fall back to temporary numbers if official numbers are missing)
            const fileNumber = property.mlsFNo
                || property.kangisFileNo
                || property.NewKANGISFileno
                || property.fileno
                || property.temp_fileno
                || 'No File Number';

            // Get party information based on transaction type
            let fromParty = '';
            let toParty = '';
            let fromLabel = 'From';
            let toLabel = 'To';

            switch ((property.transaction_type || '').toLowerCase()) {
                case 'assignment':
                    fromParty = property.Assignor || '';
                    toParty = property.Assignee || '';
                    fromLabel = 'Assignor';
                    toLabel = 'Assignee';
                    break;
                case 'mortgage':
                    fromParty = property.Mortgagor || '';
                    toParty = property.Mortgagee || '';
                    fromLabel = 'Mortgagor';
                    toLabel = 'Mortgagee';
                    break;
                case 'surrender':
                    fromParty = property.Surrenderor || '';
                    toParty = property.Surrenderee || '';
                    fromLabel = 'Surrenderor';
                    toLabel = 'Surrenderee';
                    break;
                case 'sub-lease':
                case 'lease':
                    fromParty = property.Lessor || '';
                    toParty = property.Lessee || '';
                    fromLabel = 'Lessor';
                    toLabel = 'Lessee';
                    break;
                default:
                    fromParty = property.Grantor || '';
                    toParty = property.Grantee || '';
                    fromLabel = 'Grantor';
                    toLabel = 'Grantee';
            }

            const derivedStreetAndCity = buildStreetAndCityFallback(property);
            const resolvedDescription = resolveDescriptionField(property, derivedStreetAndCity);
            const resolvedLocation = resolveLocationField(property, derivedStreetAndCity);

            detailCard.innerHTML = `
                <div class="bg-blue-100 p-4 border-b border-blue-200">
                    <div class="flex justify-between items-center">
                        <span class="bg-blue-200 text-blue-800 border-blue-300 px-3 py-1 rounded-full text-sm font-medium">
                            ${property.title_type || 'N/A'} - Selected Record
                        </span>
                        <button class="text-blue-600 hover:text-blue-800 property-options" data-id="${property.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                <circle cx="12" cy="12" r="1"></circle>
                                <circle cx="12" cy="5" r="1"></circle>
                                <circle cx="12" cy="19" r="1"></circle>
                            </svg>
                        </button>
                    </div>
                    <h3 class="mt-2 font-bold text-lg text-blue-900">${fileNumber}</h3>
                </div>
                <div class="p-4">
                    <div class="space-y-4">
                        <div class="text-sm">
                            <strong>Description:</strong> ${resolvedDescription}
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <strong>LGA/City:</strong> ${property.lgsaOrCity || 'N/A'}
                            </div>
                            <div>
                                <strong>Plot Number:</strong> ${property.plot_no || 'N/A'}
                            </div>
                            <div>
                                <strong>Layout:</strong> ${property.layout || 'N/A'}
                            </div>
                            <div>
                                <strong>Location:</strong> ${resolvedLocation}
                            </div>
                        </div>

                        <div class="border-t pt-3">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <strong>Transaction Type:</strong> ${property.transaction_type || 'N/A'}
                                </div>
                                <div>
                                    <strong>Transaction Date:</strong> ${property.transaction_date ? new Date(property.transaction_date).toLocaleDateString() : 'N/A'}
                                </div>
                                <div>
                                    <strong>Registration No:</strong> ${property.regNo || 'N/A'}
                                </div>
                                <div>
                                    <strong>Instrument Type:</strong> ${property.instrument_type || 'N/A'}
                                </div>
                            </div>
                        </div>
                        
                        ${fromParty || toParty ? `
                        <div class="border-t pt-3">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                ${fromParty ? `<div><strong>${fromLabel}:</strong> ${fromParty}</div>` : ''}
                                ${toParty ? `<div><strong>${toLabel}:</strong> ${toParty}</div>` : ''}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="p-4 pt-0 flex justify-between border-t bg-white">
                    <div class="text-xs text-gray-500">
                        <div>File Numbers:</div>
                        ${property.mlsFNo ? `<div>MLPP: ${property.mlsFNo}</div>` : ''}
                        ${property.kangisFileNo ? `<div>KANGIS: ${property.kangisFileNo}</div>` : ''}
                        ${property.NewKANGISFileno ? `<div>New KANGIS: ${property.NewKANGISFileno}</div>` : ''}
                    </div>
                    <div class="flex gap-2">
                        <button class="px-3 py-1 border rounded-md text-sm flex items-center view-property-details bg-blue-600 text-white hover:bg-blue-700" data-id="${property.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-1">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            View Full Details
                        </button>
                     
                    </div>
                </div>
            `;

            const praCandidates = collectFileNumberCandidatesFromSource(property);
            if (praCandidates.length) {
                detailCard.setAttribute('data-pra-candidates', JSON.stringify(praCandidates));
            } else {
                detailCard.removeAttribute('data-pra-candidates');
            }

            detailCard.style.display = 'block';

            // Re-attach event listeners to the new buttons
            const viewButton = detailCard.querySelector('.view-property-details');
            const editButton = detailCard.querySelector('.edit-property');
            const optionsButton = detailCard.querySelector('.property-options');

            if (viewButton) {
                viewButton.addEventListener('click', function () {
                    viewPropertyDetails(property.id);
                });
            }

            if (editButton) {
                editButton.addEventListener('click', function () {
                    editProperty(property.id);
                });
            }

            if (optionsButton) {
                optionsButton.addEventListener('click', function () {
                    showPropertyOptions(property.id, this);
                });
            }

            // Scroll to the cards section to show the details
            cardsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function resetCardsView() {
            const cardsContainer = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-4.mb-6');
            if (!cardsContainer) return;

            // Remove the selected property detail card
            const detailCard = document.getElementById('selected-property-detail-card');
            if (detailCard) detailCard.remove();

            // Remove loading and error cards
            const loadingCard = document.getElementById('loading-property-card');
            const errorCard = document.getElementById('error-property-card');
            if (loadingCard) loadingCard.remove();
            if (errorCard) errorCard.remove();

            // Show all original property cards
            const propertyCards = cardsContainer.querySelectorAll('.border:not(#add-property-card)');
            propertyCards.forEach(card => card.style.display = 'block');

            // Remove selection from table rows
            document.querySelectorAll('.table tbody tr').forEach(row => {
                row.classList.remove('bg-blue-50', 'selected-row');
            });
        }

        function viewPropertyDetails(propertyId) {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                text: 'Fetching property details',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Fetch property data
            fetch(`{{ url('/property-records') }}/${propertyId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch property data');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.data) {
                        displayPropertyDetails(data.data);
                        // Close loading indicator
                        Swal.close();
                        // Show view dialog
                        document.getElementById('property-view-dialog').classList.remove('hidden');
                    } else {
                        throw new Error(data.message || 'Failed to load property data');
                    }
                })
                .catch(error => {
                    console.error('Error loading property details:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load property details: ' + error.message
                    });
                });
        }

        // Edit property - Updated function
        function editProperty(propertyId) {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                text: 'Fetching property data',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Fetch property data
            fetch(`{{ url('/property-records') }}/${propertyId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 422) {
                            return response.json().then(errorData => {
                                throw new Error(`Validation failed`);
                            });
                        }
                        throw new Error('Failed to fetch property data');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.data) {
                        const property = data.data;
                        loadPropertyForEditing(property);
                        // Close loading indicator
                        Swal.close();
                        // Show edit form
                        document.getElementById('property-edit-dialog').classList.remove('hidden');
                        // Initialize file number component
                        setTimeout(() => {
                            initFileNumberComponent('edit_property_');

                            // Setup validation for edit form
                            setupFormValidation('property-edit-form', 'property-edit-submit-btn');
                        }, 100);
                    } else {
                        throw new Error(data.message || 'Failed to load property data');
                    }
                })
                .catch(error => {
                    console.error('Error loading property:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Validation failed'
                    });
                });
        }

        // Function to populate edit form with property data
        function loadPropertyForEditing(property) {
            // Set form action
            const form = document.getElementById('property-edit-form');
            form.action = "{{ url('/property-records') }}/" + property.id;

            // Set property ID
            document.getElementById('edit_property_id').value = property.id;

            // Set title type
            if (property.title_type === 'Customary') {
                document.getElementById('edit_customary').checked = true;
            } else {
                document.getElementById('edit_statutory').checked = true;
            }

            // Set file numbers - populate both hidden and display fields
            fillAndDisableFileNumber('edit_property_mlsFNo', property.mlsFNo || '');
            fillAndDisableFileNumber('edit_property_kangisFileNo', property.kangisFileNo || '');
            fillAndDisableFileNumber('edit_property_NewKANGISFileno', property.NewKANGISFileno || '');

            // Debug output to verify file numbers
            console.log('File numbers being loaded:', {
                mlsFNo: property.mlsFNo,
                kangisFileNo: property.kangisFileNo,
                NewKANGISFileno: property.NewKANGISFileno
            });

            // Ensure all file number fields are visible but disabled
            const fileNumberTabs = ['mlsFNoTab', 'kangisFileNoTab', 'NewKANGISFilenoTab'];
            fileNumberTabs.forEach(tab => {
                const tabElement = document.getElementById('edit_property_' + tab);
                if (tabElement) {
                    tabElement.style.display = 'block';
                    tabElement.classList.add('disabled-tab');
                }
            });

            // Set property details
            document.getElementById('edit_property_description').value = property.property_description || '';
            document.getElementById('edit_lgsaOrCity').value = property.lgsaOrCity || '';
            document.getElementById('edit_plotNo').value = property.plot_no || '';

            if (property.layout) {
                document.getElementById('edit_layout').value = property.layout;
            }

            if (property.schedule) {
                document.getElementById('edit_schedule').value = property.schedule;
            }

            // Set transaction details
            if (property.transaction_type) {
                document.getElementById('edit_transactionType').value = property.transaction_type;
            }

            if (property.transaction_date) {
                document.getElementById('edit_transactionDate').value = property.transaction_date.split('T')[0];
            }

            // Set registration number components
            document.getElementById('edit_serialNo').value = property.serialNo || '';
            document.getElementById('edit_pageNo').value = property.pageNo || '';
            document.getElementById('edit_volumeNo').value = property.volumeNo || '';

            // Update registration number preview
            const regNoDisplay = document.getElementById('edit_regNo');
            if (regNoDisplay) {
                regNoDisplay.textContent = property.regNo || '';
                document.getElementById('edit_regNoPreview').classList.remove('hidden');
            }

            // Set instrument type and period
            if (property.instrument_type) {
                document.getElementById('edit_instrumentType').value = property.instrument_type;
            }

            document.getElementById('edit_period').value = property.period || '';

            if (property.period_unit) {
                document.getElementById('edit_periodUnit').value = property.period_unit;
            }

            // Load party information fields based on transaction type
            loadPartyFields(property);

            // Create transaction-specific fields if not already present
            createEditTransactionFields();

            // Show the transaction-specific section based on the transaction type
            showEditTransactionFields(property.transaction_type.toLowerCase());
        }

        // New helper function to fill and disable file number inputs
        function fillAndDisableFileNumber(elementId, value) {
            // Fill the hidden input field
            const element = document.getElementById(elementId);
            if (element) {
                element.value = value;
                element.disabled = true;
                element.classList.add('bg-gray-100');
            }

            // Also fill the display field
            const displayId = `${elementId}_display`;
            const displayElement = document.getElementById(displayId);
            if (displayElement) {
                displayElement.value = value;
                displayElement.disabled = true;
                displayElement.classList.add('bg-gray-100');
            } else {
                console.warn(`Display element not found: ${displayId}`);
            }
        }

        // Function to load party information fields
        function loadPartyFields(property) {
            // Create inputs if they don't exist yet
            createPartyFieldsIfNeeded('edit_transaction_specific_fields');

            // Load the data
            switch (property.transaction_type.toLowerCase()) {
                case 'assignment':
                case 'deed of assignment':
                case 'st assignment':
                    document.getElementById('edit_trans-assignor-record').value = property.Assignor || '';
                    document.getElementById('edit_trans-assignee-record').value = property.Assignee || '';
                    break;
                case 'mortgage':
                case 'deed of mortgage':
                case 'tripartite mortgage':
                    document.getElementById('edit_mortgagor-record').value = property.Mortgagor || '';
                    document.getElementById('edit_mortgagee-record').value = property.Mortgagee || '';
                    
                    const mortgagor2 = property.Mortgagor_2 || property.party_3 || '';
                    const party4 = property.party_4 || '';
                    const hasTripartite = (mortgagor2 || party4) ? true : false;

                    const checkbox = document.getElementById('edit_tripartite_has_third');
                    const mortgagor2Input = document.getElementById('edit_mortgagor_2-record');
                    const party4Input = document.getElementById('edit_party_4-record');
                    const mortgagor2Wrapper = document.getElementById('edit_mortgagor_2_wrapper');
                    const party4Wrapper = document.getElementById('edit_party_4_wrapper');

                    if (checkbox) {
                        checkbox.checked = hasTripartite;
                        if (hasTripartite) {
                            if (mortgagor2Wrapper) mortgagor2Wrapper.classList.remove('hidden');
                            if (party4Wrapper) party4Wrapper.classList.remove('hidden');
                        } else {
                            if (mortgagor2Wrapper) mortgagor2Wrapper.classList.add('hidden');
                            if (party4Wrapper) party4Wrapper.classList.add('hidden');
                        }
                    }
                    if (mortgagor2Input) mortgagor2Input.value = mortgagor2;
                    if (party4Input) party4Input.value = party4;
                    break;
                case 'surrender':
                case 'deed of surrender':
                case 'deed of surrender and release':
                    document.getElementById('edit_surrenderor-record').value = property.Surrenderor || '';
                    document.getElementById('edit_surrenderee-record').value = property.Surrenderee || '';
                    break;
                case 'lease':
                case 'sub-lease':
                case 'sublease':
                    document.getElementById('edit_lessor-record').value = property.Lessor || '';
                    document.getElementById('edit_lessee-record').value = property.Lessee || '';
                    break;
                default:
                    document.getElementById('edit_grantor-record').value = property.Grantor || '';
                    document.getElementById('edit_grantee-record').value = property.Grantee || '';
                    break;
            }
        }

        // Create party fields for edit form
        function createPartyFieldsIfNeeded(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;

            // Check if we need to create assignment fields
            if (!document.getElementById('edit_assignment-fields-record')) {
                container.innerHTML += `
                    <div id="edit_assignment-fields-record" class="transaction-fields hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="edit_trans-assignor-record" class="text-sm">Assignor</label>
                                <input id="edit_trans-assignor-record" name="Assignor" class="form-input text-sm" placeholder="Enter assignor name">
                            </div>
                            <div class="space-y-1">
                                <label for="edit_trans-assignee-record" class="text-sm">Assignee</label>
                                <input id="edit_trans-assignee-record" name="Assignee" class="form-input text-sm" placeholder="Enter assignee name">
                            </div>
                        </div>
                    </div>
                `;
            }

            // Check if we need to create mortgage fields
            if (!document.getElementById('edit_mortgage-fields-record')) {
                container.innerHTML += `
                    <div id="edit_mortgage-fields-record" class="transaction-fields hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="edit_mortgagor-record" class="text-sm">Mortgagor</label>
                                <input id="edit_mortgagor-record" name="Mortgagor" class="form-input text-sm" placeholder="Enter mortgagor name">
                            </div>
                            <div class="space-y-1">
                                <label for="edit_mortgagee-record" class="text-sm">Mortgagee</label>
                                <input id="edit_mortgagee-record" name="Mortgagee" class="form-input text-sm" placeholder="Enter mortgagee name">
                            </div>
                        </div>
                        <div class="mt-3 space-y-3 tripartite-fields-edit">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" id="edit_tripartite_has_third" name="tripartite_has_third" class="form-checkbox h-4 w-4 text-indigo-600" value="1">
                                <span>Include Third Party / Co-Mortgagor</span>
                            </label>
                            
                            <div id="edit_mortgagor_2_wrapper" class="space-y-1 hidden">
                                <label for="edit_mortgagor_2-record" class="text-sm">Co-Mortgagor / Third Party</label>
                                <input id="edit_mortgagor_2-record" name="Mortgagor_2" class="form-input text-sm" placeholder="Enter co-mortgagor/third party name">
                            </div>

                            <div id="edit_party_4_wrapper" class="space-y-1 hidden">
                                <label for="edit_party_4-record" class="text-sm">Fourth Party</label>
                                <input id="edit_party_4-record" name="party_4" class="form-input text-sm" placeholder="Enter fourth party name">
                            </div>
                        </div>
                    </div>
                `;

                // Add event listener for the checkbox to show/hide additional fields
                setTimeout(() => {
                    const checkbox = document.getElementById('edit_tripartite_has_third');
                    const mortgagor2Wrapper = document.getElementById('edit_mortgagor_2_wrapper');
                    const party4Wrapper = document.getElementById('edit_party_4_wrapper');
                    
                    if (checkbox && mortgagor2Wrapper && party4Wrapper) {
                        checkbox.addEventListener('change', function() {
                            if (this.checked) {
                                mortgagor2Wrapper.classList.remove('hidden');
                                party4Wrapper.classList.remove('hidden');
                            } else {
                                mortgagor2Wrapper.classList.add('hidden');
                                party4Wrapper.classList.add('hidden');
                            }
                        });
                    }
                }, 100);
            }

            // Check if we need to create surrender fields
            if (!document.getElementById('edit_surrender-fields-record')) {
                container.innerHTML += `
                    <div id="edit_surrender-fields-record" class="transaction-fields hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="edit_surrenderor-record" class="text-sm">Surrenderor</label>
                                <input id="edit_surrenderor-record" name="Surrenderor" class="form-input text-sm" placeholder="Enter surrenderor name">
                            </div>
                            <div class="space-y-1">
                                <label for="edit_surrenderee-record" class="text-sm">Surrenderee</label>
                                <input id="edit_surrenderee-record" name="Surrenderee" class="form-input text-sm" placeholder="Enter surrenderee name">
                            </div>
                        </div>
                    </div>
                `;
            }

            // Check if we need to create lease fields
            if (!document.getElementById('edit_lease-fields-record')) {
                container.innerHTML += `
                    <div id="edit_lease-fields-record" class="transaction-fields hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="edit_lessor-record" class="text-sm">Lessor</label>
                                <input id="edit_lessor-record" name="Lessor" class="form-input text-sm" placeholder="Enter lessor name">
                            </div>
                            <div class="space-y-1">
                                <label for="edit_lessee-record" class="text-sm">Lessee</label>
                                <input id="edit_lessee-record" name="Lessee" class="form-input text-sm" placeholder="Enter lessee name">
                            </div>
                        </div>
                    </div>
                `;
            }

            // Check if we need to create default/grant fields
            if (!document.getElementById('edit_default-fields-record')) {
                container.innerHTML += `
                    <div id="edit_default-fields-record" class="transaction-fields hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="edit_grantor-record" class="text-sm">Grantor</label>
                                <input id="edit_grantor-record" name="Grantor" class="form-input text-sm" placeholder="Enter grantor name">
                            </div>
                            <div class="space-y-1">
                                <label for="edit_grantee-record" class="text-sm">Grantee</label>
                                <input id="edit_grantee-record" name="Grantee" class="form-input text-sm" placeholder="Enter grantee name">
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        // Create all edit transaction fields at once
        function createEditTransactionFields() {
            createPartyFieldsIfNeeded('edit_transaction_specific_fields');
        }

        // Show specific transaction fields in edit form
        function showEditTransactionFields(transactionType) {
            // Hide all fields first
            const transactionFields = document.querySelectorAll('#edit_transaction_specific_fields .transaction-fields');
            transactionFields.forEach(field => field.classList.add('hidden'));

            // Get the container
            const container = document.getElementById('edit_transaction_specific_fields');
            if (container) {
                container.classList.remove('hidden');
            }

            // Show the appropriate fields
            let fieldId = '';
            switch (transactionType) {
                case 'assignment':
                case 'deed of assignment':
                case 'st assignment':
                    fieldId = 'edit_assignment-fields-record';
                    break;
                case 'mortgage':
                case 'deed of mortgage':
                case 'tripartite mortgage':
                    fieldId = 'edit_mortgage-fields-record';
                    break;
                case 'surrender':
                case 'deed of surrender':
                case 'deed of surrender and release':
                    fieldId = 'edit_surrender-fields-record';
                    break;
                case 'sub-lease':
                case 'sublease':
                case 'lease':
                    fieldId = 'edit_lease-fields-record';
                    break;
                default:
                    fieldId = 'edit_default-fields-record';
                    break;
            }

            const fieldElement = document.getElementById(fieldId);
            if (fieldElement) {
                fieldElement.classList.remove('hidden');
            }
        }

        // Form validation functions
        function validatePropertyForm(formId, submitButtonId) {
            const form = document.getElementById(formId);
            const submitButton = document.getElementById(submitButtonId);
            if (!form || !submitButton) return false;

            // Get all required fields
            const requiredInputs = form.querySelectorAll('input[required], select[required], textarea[required]');

            // Check if all required fields are filled
            let isValid = true;

            requiredInputs.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                }
            });

            // Check for active file number (at least one must be filled)
            if (formId === 'property-record-form') {
                // For manual property form, check manual file number fields
                const activeFileTab = document.getElementById('manual_activeFileTab');

                if (activeFileTab) {
                    const activeTabValue = activeFileTab.value;
                    let fileNumberFilled = false;

                    if (activeTabValue === 'mlsFNo') {
                        const mlsNumber = document.getElementById('manual_mlsFileNumber');
                        if (mlsNumber && mlsNumber.value.trim()) {
                            fileNumberFilled = true;
                        }
                    } else if (activeTabValue === 'kangisFileNo') {
                        const kangisNumber = document.getElementById('manual_kangisFileNumber');
                        if (kangisNumber && kangisNumber.value.trim()) {
                            fileNumberFilled = true;
                        }
                    } else if (activeTabValue === 'NewKANGISFileno') {
                        const newKangisNumber = document.getElementById('manual_newKangisFileNumber');
                        if (newKangisNumber && newKangisNumber.value.trim()) {
                            fileNumberFilled = true;
                        }
                    }

                    if (!fileNumberFilled) {
                        isValid = false;
                    }
                }
            } else {
                // For edit form, use the original logic
                const prefix = 'edit_property_';
                const activeFileTab = document.getElementById(prefix + 'activeFileTab');

                if (activeFileTab) {
                    const activeTabValue = activeFileTab.value;
                    let fileNumberFilled = false;

                    if (activeTabValue === 'mlsFNo') {
                        const mlsNumber = document.getElementById(prefix + 'mlsFileNumber');
                        if (mlsNumber && mlsNumber.value.trim()) {
                            fileNumberFilled = true;
                        }
                    } else if (activeTabValue === 'kangisFileNo') {
                        const kangisNumber = document.getElementById(prefix + 'kangisFileNumber');
                        if (kangisNumber && kangisNumber.value.trim()) {
                            fileNumberFilled = true;
                        }
                    } else if (activeTabValue === 'NewKANGISFileno') {
                        const newKangisNumber = document.getElementById(prefix + 'newKangisFileNumber');
                        if (newKangisNumber && newKangisNumber.value.trim()) {
                            fileNumberFilled = true;
                        }
                    }

                    if (!fileNumberFilled) {
                        isValid = false;
                    }
                }
            }

            // Also validate transaction type and relevant fields
            const transactionSelect = form.querySelector('select.transaction-type-select');
            if (transactionSelect && !transactionSelect.value) {
                isValid = false;
            } else if (transactionSelect && transactionSelect.value) {
                // For create form, skip party field validation and let server enforce if needed
                if (formId !== 'property-record-form') {
                    // Validate fields specific to the transaction type (edit form only)
                    const transType = transactionSelect.value.toLowerCase();
                    let partyFieldsValid = true;

                    if (transType === 'assignment') {
                        const assignorField = document.getElementById(prefix.replace('property_', '') + 'trans-assignor-record');
                        const assigneeField = document.getElementById(prefix.replace('property_', '') + 'trans-assignee-record');
                        if ((!assignorField || !assignorField.value.trim()) || (!assigneeField || !assigneeField.value.trim())) {
                            partyFieldsValid = false;
                        }
                    } else if (transType === 'mortgage') {
                        const mortgagorField = document.getElementById(prefix.replace('property_', '') + 'mortgagor-record');
                        const mortgageeField = document.getElementById(prefix.replace('property_', '') + 'mortgagee-record');
                        if ((!mortgagorField || !mortgagorField.value.trim()) || (!mortgageeField || !mortgageeField.value.trim())) {
                            partyFieldsValid = false;
                        }
                    } else if (transType === 'surrender') {
                        const surrenderorField = document.getElementById(prefix.replace('property_', '') + 'surrenderor-record');
                        const surrendereeField = document.getElementById(prefix.replace('property_', '') + 'surrenderee-record');
                        if ((!surrenderorField || !surrenderorField.value.trim()) || (!surrendereeField || !surrendereeField.value.trim())) {
                            partyFieldsValid = false;
                        }
                    } else if (transType === 'lease') {
                        const lessorField = document.getElementById(prefix.replace('property_', '') + 'lessor-record');
                        const lesseeField = document.getElementById(prefix.replace('property_', '') + 'lessee-record');
                        if ((!lessorField || !lessorField.value.trim()) || (!lesseeField || !lesseeField.value.trim())) {
                            partyFieldsValid = false;
                        }
                    } else if (transType !== 'other') { // For default fields but not "Other" type
                        const grantorField = document.getElementById(prefix.replace('property_', '') + 'grantor-record');
                        const granteeField = document.getElementById(prefix.replace('property_', '') + 'grantee-record');
                        if ((!grantorField || !grantorField.value.trim()) || (!granteeField || !granteeField.value.trim())) {
                            partyFieldsValid = false;
                        }
                    }

                    if (!partyFieldsValid) {
                        isValid = false;
                    }
                }
            }

            // Update button state
            if (isValid) {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            }

            return isValid;
        }

        // Setup form validation and mark required fields
        function setupFormValidation(formId, submitButtonId) {
            const form = document.getElementById(formId);
            if (!form) return;

            // Mark key fields as required
            markRequiredFields(form);

            // Initially validate and disable the button
            validatePropertyForm(formId, submitButtonId);

            // Add event listeners to all form inputs
            const allInputs = form.querySelectorAll('input, select, textarea');
            allInputs.forEach(input => {
                input.addEventListener('input', () => validatePropertyForm(formId, submitButtonId));
                input.addEventListener('change', () => validatePropertyForm(formId, submitButtonId));
            });
        }

        // Mark required fields with the 'required' attribute
        function markRequiredFields(form) {
            // File number fields are required indirectly via validation function

            // Property description fields
            const requiredPropertyFields = [
                'lgsaOrCity'
            ];

            // Transaction fields (transaction date is optional on client side)
            form.querySelector('select.transaction-type-select')?.setAttribute('required', 'true');
            // Serial/Page/Volume will be validated server-side; avoid blocking client-side submit

            // For each required field, add the required attribute
            requiredPropertyFields.forEach(fieldId => {
                const field = form.querySelector('#' + fieldId);
                if (field) field.setAttribute('required', 'true');
            });
        }

        // Create Edit Property Dialog with validation
        let editPropertyDialog = document.getElementById('property-edit-dialog');
        if (!editPropertyDialog) {
            // Create the edit dialog if it doesn't exist
            editPropertyDialog = document.createElement('div');
            editPropertyDialog.id = 'property-edit-dialog';
            editPropertyDialog.className = 'dialog-overlay hidden';
            editPropertyDialog.innerHTML = `
                <div class="dialog-content property-form-content">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Edit Property Record</h2>
                        <button id="close-property-edit" class="text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    
                    <form id="property-edit-form" class="space-y-6 max-h-[75vh] overflow-y-auto">
                        <!-- Content will be dynamically populated -->
                    </form>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t mt-4">
                        <button id="property-edit-submit-btn" class="btn btn-primary" type="submit">Save Changes</button>
                        <button id="close-edit" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(editPropertyDialog);

            // Add event listeners for the new edit dialog
            document.getElementById('close-property-edit').addEventListener('click', function () {
                editPropertyDialog.classList.add('hidden');
            });

            document.getElementById('close-edit').addEventListener('click', function () {
                editPropertyDialog.classList.add('hidden');
            });
        }

        // Create View Property Dialog
        let viewPropertyDialog = document.getElementById('property-view-dialog');
        if (!viewPropertyDialog) {
            // Create the view dialog if it doesn't exist
            viewPropertyDialog = document.createElement('div');
            viewPropertyDialog.id = 'property-view-dialog';
            viewPropertyDialog.className = 'dialog-overlay hidden';
            viewPropertyDialog.innerHTML = `
                <div class="dialog-content property-form-content">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Property Record Details</h2>
                        <button id="close-property-view" class="text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    
                    <div id="property-view-content" class="space-y-6 max-h-[75vh] overflow-y-auto">
                        <!-- Content will be dynamically populated -->
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t mt-4">
                        <button id="edit-from-view" data-property-id="" class="btn btn-secondary">Edit</button>
                        <button id="close-view" class="btn btn-primary">Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(viewPropertyDialog);

            // Add event listeners for the new view dialog
            document.getElementById('close-property-view').addEventListener('click', function () {
                viewPropertyDialog.classList.add('hidden');
            });

            document.getElementById('close-view').addEventListener('click', function () {
                viewPropertyDialog.classList.add('hidden');
            });

            document.getElementById('edit-from-view').addEventListener('click', function () {
                const propertyId = this.getAttribute('data-property-id');
                viewPropertyDialog.classList.add('hidden');
                editProperty(propertyId);
            });
        }

        // Function to handle deletion of property records
        function deleteProperty(propertyId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! The property record will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send DELETE request to server
                    fetch(`{{ url('/property-records') }}/${propertyId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then((data) => {
                            if (data.status === 'success') {
                                Swal.fire(
                                    'Deleted!',
                                    'Property record has been deleted.',
                                    'success'
                                ).then(() => {
                                    // Reload the page to refresh the property list
                                    window.location.reload();
                                });
                            } else {
                                throw new Error(data.message || 'Failed to delete property record');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Error!',
                                'Failed to delete property record: ' + error.message,
                                'error'
                            );
                        });
                }
            });
        }

        // Function to show property options menu
        function showPropertyOptions(propertyId, buttonElement) {
            const rect = buttonElement.getBoundingClientRect();

            Swal.fire({
                title: 'Property Options',
                html: `
                    <div class="flex flex-col space-y-2">
                        <button type="button" class="btn btn-secondary w-full" id="view-option">View Details</button>
                        <button type="button" class="btn btn-secondary w-full" id="edit-option">Edit Record</button>
                        <button type="button" class="btn btn-danger w-full" id="delete-option">Delete Record</button>
                    </div>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Close',
                customClass: {
                    container: 'property-options-menu'
                },
                didOpen: () => {
                    // Add click event listeners to the option buttons
                    document.getElementById('view-option').addEventListener('click', function () {
                        Swal.close();
                        viewPropertyDetails(propertyId);
                    });

                    document.getElementById('edit-option').addEventListener('click', function () {
                        Swal.close();
                        editProperty(propertyId);
                    });

                    document.getElementById('delete-option').addEventListener('click', function () {
                        Swal.close();
                        deleteProperty(propertyId);
                    });
                }
            });
        }

        // Initialize property search functionality
        const propertySearch = document.getElementById('property-search');
        if (propertySearch) {
            const filterPropertyCards = (normalizedTerm) => {
                document.querySelectorAll('#property-cards-container .border').forEach(card => {
                    if (card.id === 'add-property-card') {
                        return;
                    }

                    const cardText = (card.textContent || '').toLowerCase();
                    card.style.display = cardText.includes(normalizedTerm) ? '' : 'none';
                });
            };

            const filterTableFallback = (normalizedTerm) => {
                document.querySelectorAll('#property-records-table tbody tr').forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length <= 1) {
                        return;
                    }

                    const fileNumber = (cells[0].textContent || '').toLowerCase();
                    const description = (cells[1]?.textContent || '').toLowerCase();
                    const location = (cells[2]?.textContent || '').toLowerCase();

                    const matches = fileNumber.includes(normalizedTerm) || description.includes(normalizedTerm) || location.includes(normalizedTerm);
                    row.style.display = matches ? '' : 'none';
                });
            };

            propertySearch.addEventListener('input', function () {
                const rawValue = this.value || '';
                const normalizedTerm = rawValue.toLowerCase();

                filterPropertyCards(normalizedTerm);

                if (typeof window.propertyRecordsTableSearch === 'function') {
                    window.propertyRecordsTableSearch(rawValue);
                } else {
                    filterTableFallback(normalizedTerm);
                }
            });
        }

        console.log('JavaScript initialization complete');

        // Tab switching functionality
        initializeTabSwitching();

        // Assistant toggle functionality
        const assistantToggle = document.getElementById('assistant-toggle');
        const manualAssistant = document.getElementById('manual-assistant');
        const aiAssistant = document.getElementById('ai-assistant');

        if (assistantToggle && manualAssistant && aiAssistant) {
            assistantToggle.addEventListener('change', function () {
                if (this.checked) {
                    manualAssistant.style.display = 'none';
                    aiAssistant.style.display = 'block';
                } else {
                    manualAssistant.style.display = 'block';
                    aiAssistant.style.display = 'none';
                }
            });
        }

        // Initialize tab switching functionality
        function initializeTabSwitching() {
            const propertyRecordsTab = document.getElementById('property-records-tab');
            const cofoTab = document.getElementById('cofo-tab');
            const propertyRecordsContent = document.getElementById('property-records-content');
            const cofoContent = document.getElementById('cofo-content');

            if (propertyRecordsTab && cofoTab && propertyRecordsContent && cofoContent) {
                // Property Records tab click handler
                propertyRecordsTab.addEventListener('click', function () {
                    console.log('Property Records tab clicked');
                    // Update tab buttons
                    propertyRecordsTab.classList.add('active');
                    cofoTab.classList.remove('active');

                    // Update tab content
                    propertyRecordsContent.classList.add('active');
                    propertyRecordsContent.style.display = 'block';
                    cofoContent.classList.remove('active');
                    cofoContent.style.display = 'none';
                });

                // CofO tab click handler
                cofoTab.addEventListener('click', function () {
                    console.log('CofO tab clicked');
                    // Update tab buttons
                    cofoTab.classList.add('active');
                    propertyRecordsTab.classList.remove('active');

                    // Update tab content
                    cofoContent.classList.add('active');
                    cofoContent.style.display = 'block';
                    propertyRecordsContent.classList.remove('active');
                    propertyRecordsContent.style.display = 'none';

                    // Trigger CofO table initialization after a short delay
                    setTimeout(function () {
                        console.log('Triggering CofO table initialization...');
                        // Trigger a custom event that the CofO table can listen for
                        const event = new CustomEvent('cofoTabActivated');
                        document.dispatchEvent(event);

                        // Also trigger data loading if table exists
                        if (window.loadCofOData) {
                            window.loadCofOData();
                        }
                    }, 200);
                });
            }
        }

        // Expose key functions globally so other scripts (e.g., DataTables) can call them
        window.viewPropertyDetails = viewPropertyDetails;
        window.editProperty = editProperty;
        window.deleteProperty = deleteProperty;
        window.showPropertyOptions = showPropertyOptions;
    });

</script>